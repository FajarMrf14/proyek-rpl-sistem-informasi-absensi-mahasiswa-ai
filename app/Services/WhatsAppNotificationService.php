<?php

namespace App\Services;

use App\Config\App as AppConfig;
use App\Models\AppSetting;
use App\Models\WhatsAppNotification;
use Throwable;

/**
 * Orkestrasi notifikasi WhatsApp: enqueue yang idempoten + worker outbox.
 *
 * Prinsip keamanan:
 * - Tidak ada pengiriman jaringan dari halaman konfigurasi kecuali admin
 *   menekan tombol uji dan mengonfirmasi nomor tujuan.
 * - Proses absensi hanya melakukan enqueue setelah insert absensi berhasil;
 *   kegagalan WhatsApp tidak boleh menggagalkan transaksi absensi.
 * - Nomor penerima selalu diambil dari profil masing-masing pengguna.
 * - Token Fonnte tidak pernah masuk payload, log, atau tampilan.
 */
class WhatsAppNotificationService
{
    private WhatsAppNotification $notifications;

    /** Batas percobaan pengiriman sebelum pesan dinyatakan gagal permanen. */
    private const MAX_ATTEMPTS = 3;

    /** Jendela hari untuk memproses pertemuan tertutup (absence & ringkasan). */
    private const CLOSED_MEETING_WINDOW_DAYS = 14;

    public function __construct()
    {
        $this->notifications = new WhatsAppNotification();
        $this->ensureTimezone();
    }

    public function isEnabled(): bool
    {
        try {
            return $this->toBool(AppSetting::get('whatsapp_enabled', false));
        } catch (Throwable) {
            return false;
        }
    }

    public function isOperational(): bool
    {
        if ($this->isSimulationMode()) {
            return true;
        }

        if (!$this->isEnabled()) {
            return false;
        }

        try {
            return trim((string) AppSetting::get('fonnte_token', '')) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Mode simulasi mencegah pengiriman nyata ke Fonnte. Pesan tetap dicatat
     * di outbox dengan penanda simulated = 1.
     */
    public function isSimulationMode(): bool
    {
        try {
            return $this->toBool(AppSetting::get('whatsapp_simulation_mode', true));
        } catch (Throwable) {
            return true;
        }
    }

    public function isTypeEnabled(string $type): bool
    {
        $key = match ($type) {
            'schedule_reminder' => 'whatsapp_schedule_reminder_enabled',
            'attendance_confirmation' => 'whatsapp_attendance_confirmation_enabled',
            'absence_notice' => 'whatsapp_absence_notice_enabled',
            'low_attendance' => 'whatsapp_low_attendance_enabled',
            'lecturer_teaching_reminder' => 'whatsapp_lecturer_teaching_reminder_enabled',
            'lecturer_attendance' => 'whatsapp_lecturer_attendance_enabled',
            'lecturer_attendance_summary' => 'whatsapp_lecturer_attendance_enabled',
            'lecturer_recap' => 'whatsapp_lecturer_recap_enabled',
            'admin_attendance_alert' => 'whatsapp_admin_attendance_alert_enabled',
            'admin_approval' => 'whatsapp_admin_approval_enabled',
            'admin_whatsapp_failure' => 'whatsapp_admin_whatsapp_failure_enabled',
            'admin_device_issue' => 'whatsapp_admin_device_issue_enabled',
            'admin_attendance_summary' => 'whatsapp_admin_attendance_summary_enabled',
            'announcement' => 'whatsapp_announcement_enabled',
            'test' => 'whatsapp_enabled',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        try {
            return $this->toBool(AppSetting::get($key, false));
        } catch (Throwable) {
            return false;
        }
    }

    public function getReminderMinutes(): int
    {
        try {
            $minutes = (int) AppSetting::get('whatsapp_reminder_minutes', 30);
        } catch (Throwable) {
            $minutes = 30;
        }

        return max(5, min(180, $minutes));
    }

    public function getLecturerAttendanceMode(): string
    {
        try {
            $mode = strtolower(trim((string) AppSetting::get('whatsapp_lecturer_attendance_mode', 'per_student')));
        } catch (Throwable) {
            $mode = 'per_student';
        }

        return in_array($mode, ['per_student', 'summary'], true) ? $mode : 'per_student';
    }

    // =========================================================================
    // NOTIFIKASI MAHASISWA
    // =========================================================================

    /**
     * Enqueue konfirmasi setelah absensi sukses. Dipanggil setelah insert absensi,
     * di luar blok try/catch transaksi absensi.
     */
    public function enqueueAttendanceConfirmation(int $absensiId, array $context): bool
    {
        if ($absensiId <= 0 || !$this->isOperational() || !$this->isTypeEnabled('attendance_confirmation')) {
            return false;
        }

        $mahasiswaId = (int) ($context['mahasiswa_id'] ?? 0);
        if ($mahasiswaId <= 0) {
            return false;
        }

        $payload = $this->attendancePayload($context);

        $contact = $this->notifications->getRecipientContact('mahasiswa', $mahasiswaId);
        $phone = $contact['phone'];
        $recipientRef = 'mahasiswa#' . $mahasiswaId;
        $eventKey = 'attendance_confirmation:' . $absensiId;
        $alreadyQueued = $this->notifications->eventExists($eventKey);

        if ($phone === null) {
            if (!$alreadyQueued) {
                $this->recordSkipped(
                    'attendance_confirmation_skipped:' . $absensiId,
                    'attendance_confirmation',
                    'mahasiswa',
                    $mahasiswaId,
                    $recipientRef,
                    $payload,
                    (string) ($contact['reason'] ?? 'Nomor WhatsApp penerima tidak tersedia.')
                );
            }
        } elseif (FonnteService::normalizePhone($phone) === null) {
            if (!$alreadyQueued) {
                $this->recordSkipped(
                    'attendance_confirmation_skipped:' . $absensiId,
                    'attendance_confirmation',
                    'mahasiswa',
                    $mahasiswaId,
                    $recipientRef,
                    $payload,
                    'Nomor WhatsApp penerima tidak valid.'
                );
            }
        }

        $queued = $phone !== null && FonnteService::normalizePhone($phone) !== null
            && $this->enqueue(
                $eventKey,
                'attendance_confirmation',
                'mahasiswa',
                $mahasiswaId,
                $recipientRef,
                $payload
            );

        // Dosen dan admin tetap diberi tahu meskipun mahasiswa tidak punya nomor.
        // Kegagalan salah satu jalur tidak boleh menggagalkan jalur lain.
        try {
            $this->enqueueLecturerAttendanceNotice($absensiId, $context, $payload);
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal enqueue notifikasi dosen: ' . get_class($e));
        }

        try {
            $this->enqueueAdminAttendanceAlert($absensiId, $payload);
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal enqueue notifikasi admin: ' . get_class($e));
        }

        return $queued;
    }

    /**
     * Pengingat untuk mahasiswa yang terdaftar aktif pada jadwal hari ini.
     */
    public function enqueueScheduleReminders(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('schedule_reminder')) {
            return 0;
        }

        $this->ensureTimezone();
        $reminderMinutes = $this->getReminderMinutes();
        $today = date('Y-m-d');
        $day = get_current_day_id();

        $schedules = $this->notifications->query(
            "SELECT jk.id, jk.jam_mulai, jk.jam_selesai, jk.ruangan, jk.hari,
                    mk.nama_mk, mk.kode_mk, k.nama_kelas,
                    d.nama_lengkap AS nama_dosen
             FROM jadwal_kuliah jk
             JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id AND mk.is_active = 1
             JOIN kelas k ON k.id = jk.kelas_id
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
             WHERE jk.is_active = 1
               AND jk.hari = :hari
               AND ta.tanggal_mulai IS NOT NULL
               AND ta.tanggal_selesai IS NOT NULL
               AND :tanggal BETWEEN ta.tanggal_mulai AND ta.tanggal_selesai
               AND NOT EXISTS (
                   SELECT 1 FROM pertemuan_kuliah pk
                   WHERE pk.jadwal_id = jk.id
                     AND pk.tanggal = :tanggal_batal
                     AND pk.status = 'dibatalkan'
               )
             ORDER BY jk.jam_mulai ASC",
            ['hari' => $day, 'tanggal' => $today, 'tanggal_batal' => $today]
        );

        $enqueued = 0;
        $now = time();

        foreach ($schedules as $schedule) {
            $startTs = strtotime($today . ' ' . (string) $schedule['jam_mulai']);
            if ($startTs === false) {
                continue;
            }

            $diffSeconds = $startTs - $now;
            if ($diffSeconds < 0 || $diffSeconds > ($reminderMinutes * 60)) {
                continue;
            }

            $students = $this->notifications->query(
                "SELECT km.mahasiswa_id, m.nim, m.nama_lengkap, m.no_hp, m.status_akademik, u.is_active, u.role
                 FROM kelas_mahasiswa km
                 JOIN mahasiswa m ON m.id = km.mahasiswa_id
                 JOIN users u ON u.id = m.user_id
                 WHERE km.jadwal_id = :jadwal_id
                   AND km.status = 'aktif'
                   AND m.status_akademik = 'aktif'
                   AND u.is_active = 1
                   AND u.role = 'mahasiswa'",
                ['jadwal_id' => (int) $schedule['id']]
            );

            $minutesBefore = max(0, (int) ceil($diffSeconds / 60));
            foreach ($students as $student) {
                $mahasiswaId = (int) $student['mahasiswa_id'];
                $rawPhone = trim((string) ($student['no_hp'] ?? ''));
                $normalizedPhone = $rawPhone !== '' ? FonnteService::normalizePhone($rawPhone) : null;
                $eventKey = 'schedule_reminder:' . (int) $schedule['id'] . ':' . $today . ':' . $mahasiswaId;
                $alreadyQueued = $this->notifications->eventExists($eventKey);

                $payload = [
                    'jadwal_id'      => (int) $schedule['id'],
                    'mahasiswa_id'   => $mahasiswaId,
                    'nama_mhs'       => $this->cleanText($student['nama_lengkap'] ?? '', 100),
                    'nim'            => $this->cleanText($student['nim'] ?? '', 30),
                    'nama_mk'        => (string) $schedule['nama_mk'],
                    'kode_mk'        => (string) $schedule['kode_mk'],
                    'nama_dosen'     => (string) $schedule['nama_dosen'],
                    'nama_kelas'     => (string) $schedule['nama_kelas'],
                    'hari'           => (string) $schedule['hari'],
                    'jam_mulai'      => (string) $schedule['jam_mulai'],
                    'jam_selesai'    => (string) $schedule['jam_selesai'],
                    'ruangan'        => (string) $schedule['ruangan'],
                    'menit_sebelum'  => $minutesBefore,
                    'tanggal'        => $today,
                ];

                if ($normalizedPhone === null) {
                    if (!$alreadyQueued) {
                        $reason = $rawPhone === ''
                            ? 'Nomor WhatsApp belum diisi.'
                            : 'Nomor WhatsApp tidak valid.';

                        $this->recordSkipped(
                            'schedule_reminder_skipped:' . (int) $schedule['id'] . ':' . $today . ':' . $mahasiswaId,
                            'schedule_reminder',
                            'mahasiswa',
                            $mahasiswaId,
                            'mahasiswa#' . $mahasiswaId,
                            $payload,
                            $reason
                        );
                    }
                    continue;
                }

                if ($this->enqueue(
                    $eventKey,
                    'schedule_reminder',
                    'mahasiswa',
                    $mahasiswaId,
                    'mahasiswa#' . $mahasiswaId,
                    $payload
                )) {
                    $enqueued++;
                }
            }
        }

        return $enqueued;
    }

    /**
     * Peringatan kehadiran rendah. Maksimal satu per jadwal/mahasiswa per minggu
     * (ISO week) untuk menghindari spam. Ambang memakai aturan akademik yang sudah ada.
     */
    public function enqueueLowAttendanceWarnings(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('low_attendance')) {
            return 0;
        }

        $threshold = (float) AppSetting::get('min_attendance_percent', 75.0);
        if ($threshold <= 0 || $threshold > 100) {
            return 0;
        }

        $weekKey = date('o-W');
        $thresholdKey = number_format($threshold, 2, '.', '');
        $rows = $this->notifications->query(
            "SELECT v.jadwal_id, v.mahasiswa_id, v.nama_mk, v.kode_mk, v.nama_kelas,
                    v.persentase_kehadiran, v.total_pertemuan_selesai, m.no_hp
             FROM v_rekap_kehadiran_mahasiswa v
             JOIN jadwal_kuliah jk ON jk.id = v.jadwal_id AND jk.is_active = 1
             JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
             JOIN kelas_mahasiswa km ON km.jadwal_id = v.jadwal_id
                                     AND km.mahasiswa_id = v.mahasiswa_id
                                     AND km.status = 'aktif'
             JOIN mahasiswa m ON m.id = v.mahasiswa_id AND m.status_akademik = 'aktif'
             WHERE v.total_pertemuan_selesai > 0
               AND v.persentase_kehadiran < :threshold
               AND m.no_hp IS NOT NULL AND TRIM(m.no_hp) <> ''
             ORDER BY v.jadwal_id ASC, v.mahasiswa_id ASC",
            ['threshold' => $threshold]
        );

        $enqueued = 0;
        foreach ($rows as $row) {
            $mahasiswaId = (int) $row['mahasiswa_id'];
            $jadwalId = (int) $row['jadwal_id'];
            if (!FonnteService::normalizePhone((string) $row['no_hp'])) {
                continue;
            }

            $payload = [
                'jadwal_id'                => $jadwalId,
                'mahasiswa_id'             => $mahasiswaId,
                'nama_mk'                  => (string) $row['nama_mk'],
                'kode_mk'                  => (string) $row['kode_mk'],
                'nama_kelas'               => (string) $row['nama_kelas'],
                'persentase_kehadiran'     => (float) $row['persentase_kehadiran'],
                'total_pertemuan_selesai'  => (int) $row['total_pertemuan_selesai'],
                'batas_minimal'            => $threshold,
            ];

            if ($this->enqueue(
                'low_attendance:' . $jadwalId . ':' . $mahasiswaId . ':' . $thresholdKey . ':' . $weekKey,
                'low_attendance',
                'mahasiswa',
                $mahasiswaId,
                'mahasiswa#' . $mahasiswaId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    /**
     * Pemberitahuan ketidakhadiran hanya untuk pertemuan yang sudah DITUTUP.
     * Status alpha dianggap final setelah sesi ditutup; tidak ada kesimpulan
     * sebelum aturan waktu terpenuhi.
     */
    public function enqueueAbsenceNotices(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('absence_notice')) {
            return 0;
        }

        $window = self::CLOSED_MEETING_WINDOW_DAYS;
        $rows = $this->notifications->query(
            "SELECT pk.id AS pertemuan_id, pk.jadwal_id, pk.pertemuan_ke, pk.tanggal,
                    jk.hari, mk.nama_mk, mk.kode_mk, k.nama_kelas,
                    d.nama_lengkap AS nama_dosen,
                    m.id AS mahasiswa_id, m.nama_lengkap, m.no_hp,
                    a.status AS absensi_status
             FROM pertemuan_kuliah pk
             JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id AND jk.is_active = 1
             JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
             JOIN kelas k ON k.id = jk.kelas_id
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN kelas_mahasiswa km ON km.jadwal_id = pk.jadwal_id AND km.status = 'aktif'
             JOIN mahasiswa m ON m.id = km.mahasiswa_id
             JOIN users u ON u.id = m.user_id AND u.is_active = 1 AND u.role = 'mahasiswa'
             LEFT JOIN absensi a ON a.pertemuan_id = pk.id AND a.mahasiswa_id = m.id
             WHERE pk.status = 'ditutup'
               AND pk.tanggal >= DATE_SUB(CURDATE(), INTERVAL {$window} DAY)
               AND (a.id IS NULL OR a.status = 'alpha')
               AND m.status_akademik = 'aktif'
             ORDER BY pk.id ASC, m.id ASC"
        );

        $enqueued = 0;
        foreach ($rows as $row) {
            $mahasiswaId = (int) $row['mahasiswa_id'];
            $pertemuanId = (int) $row['pertemuan_id'];
            $rawPhone = trim((string) ($row['no_hp'] ?? ''));
            $normalized = $rawPhone !== '' ? FonnteService::normalizePhone($rawPhone) : null;

            $payload = [
                'pertemuan_id'  => $pertemuanId,
                'jadwal_id'     => (int) $row['jadwal_id'],
                'mahasiswa_id'  => $mahasiswaId,
                'nama_mhs'      => $this->cleanText($row['nama_lengkap'] ?? '', 100),
                'nama_mk'       => (string) $row['nama_mk'],
                'kode_mk'       => (string) $row['kode_mk'],
                'nama_dosen'    => (string) $row['nama_dosen'],
                'nama_kelas'    => (string) $row['nama_kelas'],
                'hari'          => (string) $row['hari'],
                'pertemuan_ke'  => (int) $row['pertemuan_ke'],
                'tanggal'       => (string) $row['tanggal'],
                'status'        => 'alpha',
            ];

            if ($normalized === null) {
                $this->recordSkipped(
                    'absence_notice_skipped:' . $pertemuanId . ':' . $mahasiswaId,
                    'absence_notice',
                    'mahasiswa',
                    $mahasiswaId,
                    'mahasiswa#' . $mahasiswaId,
                    $payload,
                    $rawPhone === '' ? 'Nomor WhatsApp belum diisi.' : 'Nomor WhatsApp tidak valid.'
                );
                continue;
            }

            if ($this->enqueue(
                'absence_notice:' . $pertemuanId . ':' . $mahasiswaId,
                'absence_notice',
                'mahasiswa',
                $mahasiswaId,
                'mahasiswa#' . $mahasiswaId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    // =========================================================================
    // NOTIFIKASI DOSEN
    // =========================================================================

    /**
     * Pengingat jadwal mengajar, satu pesan per jadwal per hari per dosen.
     */
    public function enqueueLecturerTeachingReminders(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('lecturer_teaching_reminder')) {
            return 0;
        }

        $this->ensureTimezone();
        $reminderMinutes = $this->getReminderMinutes();
        $today = date('Y-m-d');
        $day = get_current_day_id();

        $schedules = $this->notifications->query(
            "SELECT jk.id, jk.dosen_id, jk.jam_mulai, jk.jam_selesai, jk.ruangan, jk.hari,
                    mk.nama_mk, mk.kode_mk, k.nama_kelas,
                    d.nama_lengkap AS nama_dosen, d.no_hp AS dosen_no_hp
             FROM jadwal_kuliah jk
             JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id AND mk.is_active = 1
             JOIN kelas k ON k.id = jk.kelas_id
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN users u ON u.id = d.user_id AND u.is_active = 1 AND u.role = 'dosen'
             JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
             WHERE jk.is_active = 1
               AND jk.hari = :hari
               AND ta.tanggal_mulai IS NOT NULL
               AND ta.tanggal_selesai IS NOT NULL
               AND :tanggal BETWEEN ta.tanggal_mulai AND ta.tanggal_selesai
               AND NOT EXISTS (
                   SELECT 1 FROM pertemuan_kuliah pk
                   WHERE pk.jadwal_id = jk.id
                     AND pk.tanggal = :tanggal_batal
                     AND pk.status = 'dibatalkan'
               )
             ORDER BY jk.jam_mulai ASC",
            ['hari' => $day, 'tanggal' => $today, 'tanggal_batal' => $today]
        );

        $enqueued = 0;
        $now = time();

        foreach ($schedules as $schedule) {
            $startTs = strtotime($today . ' ' . (string) $schedule['jam_mulai']);
            if ($startTs === false) {
                continue;
            }

            $diffSeconds = $startTs - $now;
            if ($diffSeconds < 0 || $diffSeconds > ($reminderMinutes * 60)) {
                continue;
            }

            $dosenId = (int) $schedule['dosen_id'];
            $eventKey = 'lecturer_teaching_reminder:' . (int) $schedule['id'] . ':' . $today . ':' . $dosenId;
            $minutesBefore = max(0, (int) ceil($diffSeconds / 60));

            $payload = [
                'jadwal_id'     => (int) $schedule['id'],
                'dosen_id'      => $dosenId,
                'nama_dosen'    => (string) $schedule['nama_dosen'],
                'nama_mk'       => (string) $schedule['nama_mk'],
                'kode_mk'       => (string) $schedule['kode_mk'],
                'nama_kelas'    => (string) $schedule['nama_kelas'],
                'hari'          => (string) $schedule['hari'],
                'jam_mulai'     => (string) $schedule['jam_mulai'],
                'jam_selesai'   => (string) $schedule['jam_selesai'],
                'ruangan'       => (string) $schedule['ruangan'],
                'menit_sebelum' => $minutesBefore,
                'tanggal'       => $today,
            ];

            $rawPhone = trim((string) ($schedule['dosen_no_hp'] ?? ''));
            if ($rawPhone === '' || FonnteService::normalizePhone($rawPhone) === null) {
                $this->recordSkipped(
                    'lecturer_teaching_reminder_skipped:' . (int) $schedule['id'] . ':' . $today . ':' . $dosenId,
                    'lecturer_teaching_reminder',
                    'dosen',
                    $dosenId,
                    'dosen#' . $dosenId,
                    $payload,
                    $rawPhone === '' ? 'Nomor WhatsApp belum diisi.' : 'Nomor WhatsApp tidak valid.'
                );
                continue;
            }

            if ($this->enqueue(
                $eventKey,
                'lecturer_teaching_reminder',
                'dosen',
                $dosenId,
                'dosen#' . $dosenId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    /**
     * Notifikasi per mahasiswa ke dosen pengampu. Hanya untuk kelas yang
     * menjadi tanggung jawab dosen tersebut (relasi jadwal_kuliah.dosen_id).
     */
    private function enqueueLecturerAttendanceNotice(int $absensiId, array $context, array $payload): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('lecturer_attendance')) {
            return 0;
        }

        if ($this->getLecturerAttendanceMode() !== 'per_student') {
            return 0;
        }

        $jadwalId = (int) ($context['jadwal_id'] ?? 0);
        if ($jadwalId <= 0) {
            return 0;
        }

        $rows = $this->notifications->query(
            "SELECT jk.dosen_id, d.nama_lengkap AS nama_dosen, d.no_hp AS dosen_no_hp,
                    u.is_active, u.role
             FROM jadwal_kuliah jk
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN users u ON u.id = d.user_id
             WHERE jk.id = :jadwal_id
             LIMIT 1",
            ['jadwal_id' => $jadwalId]
        );

        if (empty($rows)) {
            return 0;
        }

        $row = $rows[0];
        $dosenId = (int) ($row['dosen_id'] ?? 0);
        if ($dosenId <= 0 || (string) ($row['role'] ?? '') !== 'dosen' || (int) ($row['is_active'] ?? 0) !== 1) {
            return 0;
        }

        $payload['nama_dosen'] = $this->cleanText($row['nama_dosen'] ?? '', 100);
        $rawPhone = trim((string) ($row['dosen_no_hp'] ?? ''));
        if ($rawPhone === '' || FonnteService::normalizePhone($rawPhone) === null) {
            $this->recordSkipped(
                'lecturer_attendance_skipped:' . $absensiId . ':' . $dosenId,
                'lecturer_attendance',
                'dosen',
                $dosenId,
                'dosen#' . $dosenId,
                $payload,
                $rawPhone === '' ? 'Nomor WhatsApp belum diisi.' : 'Nomor WhatsApp tidak valid.'
            );
            return 0;
        }

        return $this->enqueue(
            'lecturer_attendance:' . $absensiId . ':' . $dosenId,
            'lecturer_attendance',
            'dosen',
            $dosenId,
            'dosen#' . $dosenId,
            $payload
        ) ? 1 : 0;
    }

    /**
     * Ringkasan kehadiran per pertemuan tertutup untuk dosen (mode summary).
     * Tidak dikirim bila pertemuan belum final.
     */
    public function enqueueLecturerAttendanceSummaries(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('lecturer_attendance_summary')) {
            return 0;
        }

        if ($this->getLecturerAttendanceMode() !== 'summary') {
            return 0;
        }

        $window = self::CLOSED_MEETING_WINDOW_DAYS;
        $rows = $this->notifications->query(
            "SELECT pk.id AS pertemuan_id, pk.pertemuan_ke, pk.tanggal,
                    jk.id AS jadwal_id, jk.hari, jk.dosen_id,
                    mk.nama_mk, mk.kode_mk, k.nama_kelas,
                    d.nama_lengkap AS nama_dosen, d.no_hp AS dosen_no_hp,
                    u.is_active AS dosen_active,
                    COUNT(a.id) AS total_absensi,
                    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS total_hadir,
                    SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) AS total_terlambat,
                    SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS total_izin,
                    SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
                    SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS total_alpha,
                    (SELECT COUNT(*) FROM kelas_mahasiswa km2
                     JOIN mahasiswa m2 ON m2.id = km2.mahasiswa_id AND m2.status_akademik = 'aktif'
                     JOIN users u2 ON u2.id = m2.user_id AND u2.is_active = 1
                     WHERE km2.jadwal_id = jk.id AND km2.status = 'aktif') AS total_peserta
             FROM pertemuan_kuliah pk
             JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id AND jk.is_active = 1
             JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
             JOIN kelas k ON k.id = jk.kelas_id
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN users u ON u.id = d.user_id
             LEFT JOIN absensi a ON a.pertemuan_id = pk.id
             WHERE pk.status = 'ditutup'
               AND pk.tanggal >= DATE_SUB(CURDATE(), INTERVAL {$window} DAY)
             GROUP BY pk.id, pk.pertemuan_ke, pk.tanggal, jk.id, jk.hari, jk.dosen_id,
                      mk.nama_mk, mk.kode_mk, k.nama_kelas, d.nama_lengkap, d.no_hp, u.is_active
             ORDER BY pk.id ASC"
        );

        $enqueued = 0;
        foreach ($rows as $row) {
            $dosenId = (int) $row['dosen_id'];
            $pertemuanId = (int) $row['pertemuan_id'];
            $rawPhone = trim((string) ($row['dosen_no_hp'] ?? ''));

            if ((int) ($row['dosen_active'] ?? 0) !== 1
                || $rawPhone === ''
                || FonnteService::normalizePhone($rawPhone) === null
            ) {
                continue;
            }

            $totalAbsensi = (int) $row['total_absensi'];
            $totalPeserta = (int) $row['total_peserta'];

            $payload = [
                'pertemuan_id'      => $pertemuanId,
                'jadwal_id'         => (int) $row['jadwal_id'],
                'dosen_id'          => $dosenId,
                'nama_dosen'        => (string) $row['nama_dosen'],
                'nama_mk'           => (string) $row['nama_mk'],
                'kode_mk'           => (string) $row['kode_mk'],
                'nama_kelas'        => (string) $row['nama_kelas'],
                'hari'              => (string) $row['hari'],
                'pertemuan_ke'      => (int) $row['pertemuan_ke'],
                'tanggal'           => (string) $row['tanggal'],
                'total_hadir'       => (int) $row['total_hadir'],
                'total_terlambat'   => (int) $row['total_terlambat'],
                'total_izin'        => (int) $row['total_izin'],
                'total_sakit'       => (int) $row['total_sakit'],
                'total_alpha'       => (int) $row['total_alpha'],
                'total_belum'       => max(0, $totalPeserta - $totalAbsensi),
            ];

            if ($this->enqueue(
                'lecturer_attendance_summary:' . $pertemuanId . ':' . $dosenId,
                'lecturer_attendance_summary',
                'dosen',
                $dosenId,
                'dosen#' . $dosenId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    /**
     * Rekap agregat mingguan per jadwal untuk dosen.
     */
    public function enqueueLecturerRecaps(): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('lecturer_recap')) {
            return 0;
        }

        $weekKey = date('o-W');
        $schedules = $this->notifications->query(
            "SELECT jk.id, jk.dosen_id, mk.nama_mk, mk.kode_mk, k.nama_kelas,
                    d.no_hp AS dosen_no_hp
             FROM jadwal_kuliah jk
             JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
             JOIN kelas k ON k.id = jk.kelas_id
             JOIN dosen d ON d.id = jk.dosen_id
             JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
             WHERE jk.is_active = 1
               AND d.no_hp IS NOT NULL AND TRIM(d.no_hp) <> ''
             ORDER BY jk.id ASC"
        );

        $enqueued = 0;
        foreach ($schedules as $schedule) {
            $dosenId = (int) $schedule['dosen_id'];
            if (!FonnteService::normalizePhone((string) $schedule['dosen_no_hp'])) {
                continue;
            }

            $summaryRows = $this->notifications->query(
                "SELECT COUNT(DISTINCT pk.id) AS total_pertemuan_selesai,
                        COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) AS total_hadir,
                        COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) AS total_terlambat,
                        COUNT(CASE WHEN a.status = 'izin' THEN 1 END) AS total_izin,
                        COUNT(CASE WHEN a.status = 'sakit' THEN 1 END) AS total_sakit,
                        COUNT(CASE WHEN a.status = 'alpha' THEN 1 END) AS total_alpha
                 FROM pertemuan_kuliah pk
                 LEFT JOIN absensi a ON a.pertemuan_id = pk.id
                 WHERE pk.jadwal_id = :jadwal_id AND pk.status = 'ditutup'",
                ['jadwal_id' => (int) $schedule['id']]
            );

            $summary = $summaryRows[0] ?? [];
            $totalMeetings = (int) ($summary['total_pertemuan_selesai'] ?? 0);
            if ($totalMeetings <= 0) {
                continue;
            }

            $payload = [
                'jadwal_id'               => (int) $schedule['id'],
                'dosen_id'                => $dosenId,
                'nama_mk'                 => (string) $schedule['nama_mk'],
                'kode_mk'                 => (string) $schedule['kode_mk'],
                'nama_kelas'              => (string) $schedule['nama_kelas'],
                'total_pertemuan_selesai' => $totalMeetings,
                'total_hadir'             => (int) ($summary['total_hadir'] ?? 0),
                'total_terlambat'         => (int) ($summary['total_terlambat'] ?? 0),
                'total_izin'              => (int) ($summary['total_izin'] ?? 0),
                'total_sakit'             => (int) ($summary['total_sakit'] ?? 0),
                'total_alpha'             => (int) ($summary['total_alpha'] ?? 0),
            ];

            if ($this->enqueue(
                'lecturer_recap:' . (int) $schedule['id'] . ':' . $weekKey,
                'lecturer_recap',
                'dosen',
                $dosenId,
                'dosen#' . $dosenId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    // =========================================================================
    // NOTIFIKASI ADMIN & PENGUMUMAN
    // =========================================================================

    /**
     * Kirim notifikasi ke setiap admin aktif yang punya nomor, diproses terpisah.
     */
    public function notifyAdmins(string $type, string $eventKeyPrefix, array $payload): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled($type)) {
            return 0;
        }

        $enqueued = 0;
        foreach ($this->notifications->getActiveAdmins() as $admin) {
            $adminId = (int) $admin['id'];
            if ($adminId <= 0 || FonnteService::normalizePhone((string) $admin['no_hp']) === null) {
                continue;
            }

            if ($this->enqueue(
                $eventKeyPrefix . ':' . $adminId,
                $type,
                'admin',
                $adminId,
                'admin#' . $adminId,
                $payload
            )) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    /**
     * Notifikasi admin ketika ada absensi yang perlu perhatian (terlambat/alpha).
     */
    private function enqueueAdminAttendanceAlert(int $absensiId, array $payload): int
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (!in_array($status, ['terlambat', 'alpha'], true)) {
            return 0;
        }

        $nama = (string) ($payload['nama_mhs'] ?? 'Mahasiswa');
        $nim = (string) ($payload['nim'] ?? '');
        $mk = (string) ($payload['nama_mk'] ?? '');
        $kelas = (string) ($payload['nama_kelas'] ?? '');
        $waktu = (string) ($payload['jam_absensi'] ?? '');

        $pesan = "Absensi perlu perhatian: {$nama}"
            . ($nim !== '' ? " ({$nim})" : '')
            . ' tercatat ' . $this->statusLabel($status)
            . ($mk !== '' ? " pada {$mk}" : '')
            . ($kelas !== '' ? " kelas {$kelas}" : '')
            . ($waktu !== '' ? " pukul {$waktu} WIB" : '') . '.';

        return $this->notifyAdmins(
            'admin_attendance_alert',
            'admin_attendance_alert:' . $absensiId,
            ['pesan' => $pesan, 'tanggal' => (string) ($payload['tanggal'] ?? date('Y-m-d'))]
        );
    }

    /**
     * Ringkasan absensi harian untuk admin. Default: kemarin (data sudah final).
     */
    public function enqueueAdminAttendanceSummary(?string $date = null): int
    {
        if (!$this->isOperational() || !$this->isTypeEnabled('admin_attendance_summary')) {
            return 0;
        }

        $date = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            ? $date
            : date('Y-m-d', strtotime('-1 day'));

        $rows = $this->notifications->query(
            "SELECT COUNT(*) AS total_absensi,
                    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS total_hadir,
                    SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) AS total_terlambat,
                    SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS total_izin,
                    SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
                    SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS total_alpha
             FROM absensi a
             JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
             WHERE DATE(a.waktu_absensi) = :tanggal",
            ['tanggal' => $date]
        );

        $summary = $rows[0] ?? [];
        $total = (int) ($summary['total_absensi'] ?? 0);
        if ($total <= 0) {
            return 0;
        }

        $payload = [
            'tanggal'          => $date,
            'total_absensi'    => $total,
            'total_hadir'      => (int) ($summary['total_hadir'] ?? 0),
            'total_terlambat'  => (int) ($summary['total_terlambat'] ?? 0),
            'total_izin'       => (int) ($summary['total_izin'] ?? 0),
            'total_sakit'      => (int) ($summary['total_sakit'] ?? 0),
            'total_alpha'      => (int) ($summary['total_alpha'] ?? 0),
        ];

        return $this->notifyAdmins(
            'admin_attendance_summary',
            'admin_attendance_summary:' . $date,
            $payload
        );
    }

    /**
     * Resolusi penerima pengumuman dari data yang benar-benar ada.
     *
     * @return array<int, array{type: string, id: int, nama: string, phone: string}>
     */
    public function getAnnouncementRecipients(string $targetType, ?string $targetRef = null): array
    {
        $targetType = strtolower(trim($targetType));
        $targetRef = $targetRef !== null ? trim($targetRef) : null;

        $mahasiswaSql = "SELECT 'mahasiswa' AS recipient_type, m.id, m.nama_lengkap AS nama, m.no_hp
                         FROM mahasiswa m
                         JOIN users u ON u.id = m.user_id
                         WHERE u.role = 'mahasiswa' AND u.is_active = 1 AND m.status_akademik = 'aktif'";
        $dosenSql = "SELECT 'dosen' AS recipient_type, d.id, d.nama_lengkap AS nama, d.no_hp
                     FROM dosen d
                     JOIN users u ON u.id = d.user_id
                     WHERE u.role = 'dosen' AND u.is_active = 1";
        $adminSql = "SELECT 'admin' AS recipient_type, u.id, u.nama_lengkap AS nama, u.no_hp
                     FROM users u
                     WHERE u.role = 'admin' AND u.is_active = 1";

        $sql = null;
        $params = [];

        switch ($targetType) {
            case 'all_mahasiswa':
                $sql = $mahasiswaSql;
                break;

            case 'all_dosen':
                $sql = $dosenSql;
                break;

            case 'all_users':
                $sql = "({$mahasiswaSql}) UNION ALL ({$dosenSql}) UNION ALL ({$adminSql})";
                break;

            case 'kelas':
                $kelasId = (int) ($targetRef ?? 0);
                if ($kelasId <= 0) {
                    return [];
                }
                $sql = "SELECT DISTINCT 'mahasiswa' AS recipient_type, m.id, m.nama_lengkap AS nama, m.no_hp
                        FROM kelas_mahasiswa km
                        JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id AND jk.is_active = 1 AND jk.kelas_id = :kelas_id
                        JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
                        JOIN mahasiswa m ON m.id = km.mahasiswa_id
                        JOIN users u ON u.id = m.user_id
                        WHERE km.status = 'aktif'
                          AND u.role = 'mahasiswa' AND u.is_active = 1
                          AND m.status_akademik = 'aktif'";
                $params['kelas_id'] = $kelasId;
                break;

            case 'mahasiswa':
            case 'dosen':
                $ids = $this->parseIdList($targetRef);
                if (empty($ids)) {
                    return [];
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $base = $targetType === 'mahasiswa'
                    ? "SELECT 'mahasiswa' AS recipient_type, m.id, m.nama_lengkap AS nama, m.no_hp
                       FROM mahasiswa m JOIN users u ON u.id = m.user_id
                       WHERE u.role = 'mahasiswa' AND u.is_active = 1 AND m.status_akademik = 'aktif'
                         AND m.id IN ({$placeholders})"
                    : "SELECT 'dosen' AS recipient_type, d.id, d.nama_lengkap AS nama, d.no_hp
                       FROM dosen d JOIN users u ON u.id = d.user_id
                       WHERE u.role = 'dosen' AND u.is_active = 1
                         AND d.id IN ({$placeholders})";
                $rows = $this->notifications->query($base, $ids);
                return $this->normalizeRecipients($rows);

            default:
                return [];
        }

        $rows = $this->notifications->query($sql, $params);
        return $this->normalizeRecipients($rows);
    }

    /**
     * Buat pengumuman dan enqueue satu pesan per penerima.
     *
     * @return array{announcement_id: ?int, recipients: int, enqueued: int, skipped: int}
     */
    public function enqueueAnnouncement(
        string $judul,
        string $pesan,
        string $targetType,
        ?string $targetRef,
        int $createdBy
    ): array {
        $result = ['announcement_id' => null, 'recipients' => 0, 'enqueued' => 0, 'skipped' => 0];

        if (!$this->isOperational() || !$this->isTypeEnabled('announcement')) {
            return $result;
        }

        $judul = $this->cleanText($judul, 120);
        $pesan = mb_substr(trim(strip_tags($pesan)), 0, 2000, 'UTF-8');
        if ($judul === '' || $pesan === '') {
            return $result;
        }

        $recipients = $this->getAnnouncementRecipients($targetType, $targetRef);
        $result['recipients'] = count($recipients);
        if (empty($recipients)) {
            return $result;
        }

        $announcementId = $this->notifications->createAnnouncement([
            'judul' => $judul,
            'pesan' => $pesan,
            'target_type' => mb_substr($targetType, 0, 30),
            'target_ref' => $targetRef !== null ? mb_substr($targetRef, 0, 191) : null,
            'recipient_count' => 0,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ]);

        if ($announcementId === null) {
            return $result;
        }
        $result['announcement_id'] = $announcementId;

        $payload = [
            'judul' => $judul,
            'pesan' => $pesan,
            'tanggal' => date('Y-m-d'),
        ];

        foreach ($recipients as $recipient) {
            $type = $recipient['type'];
            $id = $recipient['id'];
            $eventKey = 'announcement:' . $announcementId . ':' . $type . ':' . $id;

            if (FonnteService::normalizePhone($recipient['phone']) === null) {
                $this->recordSkipped(
                    'announcement_skipped:' . $announcementId . ':' . $type . ':' . $id,
                    'announcement',
                    $type,
                    $id,
                    $type . '#' . $id,
                    $payload,
                    $recipient['phone'] === '' ? 'Nomor WhatsApp belum diisi.' : 'Nomor WhatsApp tidak valid.'
                );
                $result['skipped']++;
                continue;
            }

            if ($this->enqueue(
                $eventKey,
                'announcement',
                $type,
                $id,
                $type . '#' . $id,
                $payload
            )) {
                $result['enqueued']++;
            }
        }

        $this->notifications->updateAnnouncementRecipientCount($announcementId, $result['enqueued']);

        return $result;
    }

    // =========================================================================
    // WORKER
    // =========================================================================

    /**
     * Tutup pertemuan yang sudah melewati jam_tutup dan tandai mahasiswa tanpa
     * absensi sebagai alpha. Hanya pertemuan baru yang memiliki jam_tutup yang
     * diproses, sehingga data historis tanpa jam_tutup tidak berubah.
     */
    public function finalizeExpiredMeetings(): int
    {
        $window = self::CLOSED_MEETING_WINDOW_DAYS;
        $meetings = $this->notifications->query(
            "SELECT pk.id, pk.jadwal_id
             FROM pertemuan_kuliah pk
             WHERE pk.status = 'dibuka'
               AND pk.jam_tutup IS NOT NULL
               AND pk.jam_tutup < NOW()
               AND pk.tanggal >= DATE_SUB(CURDATE(), INTERVAL {$window} DAY)
             ORDER BY pk.id ASC"
        );

        $finalized = 0;
        foreach ($meetings as $meeting) {
            $pertemuanId = (int) $meeting['id'];
            $jadwalId = (int) $meeting['jadwal_id'];

            $update = $this->notifications->execSql(
                "UPDATE pertemuan_kuliah SET status = 'ditutup' WHERE id = :id AND status = 'dibuka'",
                ['id' => $pertemuanId]
            );
            unset($update);

            try {
                $this->notifications->execSql(
                    "INSERT INTO absensi
                        (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified, keterangan)
                     SELECT :pertemuan_id, km.mahasiswa_id, NOW(), 'alpha', 'lokasi_tidak_terdeteksi', 0,
                            'Alpha otomatis: tidak ada absensi hingga sesi ditutup'
                     FROM kelas_mahasiswa km
                     JOIN mahasiswa m ON m.id = km.mahasiswa_id AND m.status_akademik = 'aktif'
                     JOIN users u ON u.id = m.user_id AND u.is_active = 1 AND u.role = 'mahasiswa'
                     WHERE km.jadwal_id = :jadwal_id
                       AND km.status = 'aktif'
                       AND NOT EXISTS (
                           SELECT 1 FROM absensi a
                           WHERE a.pertemuan_id = :pertemuan_cek
                             AND a.mahasiswa_id = km.mahasiswa_id
                       )",
                    [
                        'pertemuan_id' => $pertemuanId,
                        'jadwal_id' => $jadwalId,
                        'pertemuan_cek' => $pertemuanId,
                    ]
                );
            } catch (Throwable $e) {
                error_log('[WhatsApp] Gagal menandai alpha otomatis: ' . get_class($e));
            }

            $finalized++;
        }

        return $finalized;
    }

    /**
     * Proses outbox. Klaim atomik mencegah dua worker mengirim baris yang sama.
     *
     * Parameter $transport hanya untuk pengujian worker: closure menerima
     * (string $phone, string $message, array $row) dan mengembalikan array
     * dengan format yang sama seperti FonnteService::send(). Bila null,
     * pengiriman memakai FonnteService asli.
     */
    public function dispatchPending(int $limit = 20, ?callable $transport = null): array
    {
        $stats = [
            'processed' => 0,
            'accepted' => 0,
            'failed' => 0,
            'skipped' => 0,
            'simulated' => 0,
            'error' => null,
        ];

        // Notifikasi yang tertahan terlalu lama tidak boleh dikirim terlambat
        // setelah scheduler/token pulih. Kegagalan pembersihan tidak menghalangi dispatch.
        try {
            $this->notifications->expirePending(24);
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal menandai antrean kedaluwarsa: ' . get_class($e));
        }

        // Transport eksplisit selalu dihormati (dipakai pengujian), sedangkan
        // mode simulasi hanya berlaku untuk pengiriman produksi tanpa transport khusus.
        $simulation = $transport === null && $this->isSimulationMode();

        if ($transport === null && !$simulation && !$this->isEnabled()) {
            $stats['error'] = 'Integrasi WhatsApp sedang nonaktif.';
            return $stats;
        }

        $client = null;
        if (!$simulation && $transport === null) {
            $client = new FonnteService();
            if (!$client->isConfigured()) {
                $stats['error'] = 'Token Fonnte belum dikonfigurasi.';
                return $stats;
            }
        }

        try {
            $rows = $this->notifications->claimPending(max(1, min(100, $limit)));
        } catch (Throwable $e) {
            $stats['error'] = 'Gagal mengklaim antrean: ' . get_class($e);
            return $stats;
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $type = (string) $row['notification_type'];
            $stats['processed']++;

            try {
                if ($type === 'test') {
                    $this->notifications->markSkipped($id, 'Pesan uji manual tidak diproses oleh worker.');
                    $stats['skipped']++;
                    continue;
                }

                if (!$this->isTypeEnabled($type)) {
                    $this->notifications->markSkipped($id, 'Jenis notifikasi sedang dinonaktifkan.');
                    $stats['skipped']++;
                    continue;
                }

                $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $contact = $this->notifications->getRecipientContact(
                    (string) $row['recipient_type'],
                    $row['recipient_id'] !== null ? (int) $row['recipient_id'] : null
                );
                $phone = $contact['phone'];

                if ($phone === null) {
                    $this->notifications->markSkipped(
                        $id,
                        (string) ($contact['reason'] ?? 'Nomor WhatsApp penerima tidak tersedia.')
                    );
                    $stats['skipped']++;
                    continue;
                }

                if (FonnteService::normalizePhone($phone) === null) {
                    $this->notifications->markSkipped($id, 'Nomor WhatsApp penerima tidak valid.');
                    $stats['skipped']++;
                    continue;
                }

                $message = $this->buildMessage($type, $payload);
                if ($message === null || trim($message) === '') {
                    $this->notifications->markFailed($id, 'Data pesan tidak lengkap.', false, (int) $row['attempts']);
                    $stats['failed']++;
                    continue;
                }

                if ($simulation) {
                    $this->notifications->markAccepted($id, 'SIMULASI', 'SIMULASI', true);
                    $stats['accepted']++;
                    $stats['simulated']++;
                    continue;
                }

                $result = $transport !== null
                    ? $transport($phone, $message, $row)
                    : $client->send($phone, $message);

                if (!is_array($result)) {
                    $result = [
                        'success' => false,
                        'retryable' => false,
                        'error_message' => 'Transport pengujian mengembalikan hasil tidak valid.',
                    ];
                }

                if (!empty($result['success'])) {
                    $this->notifications->markAccepted(
                        $id,
                        (string) ($result['provider_message_id'] ?? ''),
                        (string) ($result['request_id'] ?? ''),
                        false
                    );
                    $stats['accepted']++;
                } else {
                    $errorMessage = (string) ($result['error_message'] ?? 'Pengiriman gagal.');
                    $retryable = !empty($result['retryable']);
                    $this->notifications->markFailed($id, $errorMessage, $retryable, (int) $row['attempts']);
                    $stats['failed']++;

                    // Notifikasi admin tidak boleh memicu notifikasi admin lagi.
                    if (!in_array($type, ['admin_whatsapp_failure', 'admin_device_issue'], true)) {
                        $willRetry = $retryable && (int) $row['attempts'] < self::MAX_ATTEMPTS;
                        if (!$willRetry) {
                            $this->alertFailureToAdmins($type, $row, $errorMessage);
                        }
                        if (str_contains($errorMessage, 'Perangkat WhatsApp Fonnte')) {
                            $this->alertDeviceIssue($errorMessage);
                        }
                    }
                }
            } catch (Throwable $e) {
                try {
                    $this->notifications->markFailed($id, 'Kesalahan internal worker.', false, (int) $row['attempts']);
                } catch (Throwable) {
                    // Baris akan dipulihkan oleh recoverStale pada siklus berikutnya.
                }
                $stats['failed']++;
                error_log('[WhatsApp] Dispatch error: ' . get_class($e));
            }
        }

        return $stats;
    }

    public function runScheduler(): array
    {
        $finalized = 0;
        try {
            $finalized = $this->finalizeExpiredMeetings();
        } catch (Throwable $e) {
            error_log('[WhatsApp] Finalisasi pertemuan gagal: ' . get_class($e));
        }

        return [
            'finalized_meetings' => $finalized,
            'schedule_reminders' => $this->enqueueScheduleReminders(),
            'lecturer_teaching_reminders' => $this->enqueueLecturerTeachingReminders(),
            'absence_notices' => $this->enqueueAbsenceNotices(),
            'lecturer_attendance_summaries' => $this->enqueueLecturerAttendanceSummaries(),
            'low_attendance' => $this->enqueueLowAttendanceWarnings(),
            'lecturer_recap' => $this->enqueueLecturerRecaps(),
            'admin_attendance_summary' => $this->enqueueAdminAttendanceSummary(),
            'dispatch' => $this->dispatchPending(20),
        ];
    }

    public function runRecap(): array
    {
        return [
            'lecturer_recap' => $this->enqueueLecturerRecaps(),
            'lecturer_attendance_summaries' => $this->enqueueLecturerAttendanceSummaries(),
            'dispatch' => $this->dispatchPending(20),
        ];
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    private function attendancePayload(array $context): array
    {
        return [
            'absensi_id'     => (int) ($context['absensi_id'] ?? 0),
            'jadwal_id'      => (int) ($context['jadwal_id'] ?? 0),
            'mahasiswa_id'   => (int) ($context['mahasiswa_id'] ?? 0),
            'nama_mhs'       => $this->cleanText($context['nama_mhs'] ?? '', 100),
            'nim'            => $this->cleanText($context['nim'] ?? '', 30),
            'nama_mk'        => $this->cleanText($context['nama_mk'] ?? '', 100),
            'kode_mk'        => $this->cleanText($context['kode_mk'] ?? '', 20),
            'nama_dosen'     => $this->cleanText($context['nama_dosen'] ?? '', 100),
            'nama_kelas'     => $this->cleanText($context['nama_kelas'] ?? '', 50),
            'hari'           => $this->cleanText($context['hari'] ?? '', 20),
            'jam_mulai'      => $this->cleanText($context['jam_mulai'] ?? '', 8),
            'jam_selesai'    => $this->cleanText($context['jam_selesai'] ?? '', 8),
            'ruangan'        => $this->cleanText($context['ruangan'] ?? '', 50),
            'status'         => $this->cleanText($context['status'] ?? '', 20),
            'tanggal'        => $this->cleanText($context['tanggal'] ?? '', 20),
            'waktu_absensi'  => $this->cleanText($context['waktu_absensi'] ?? '', 30),
        ];
    }

    protected function enqueue(
        string $eventKey,
        string $type,
        string $recipientType,
        int $recipientId,
        string $recipientRef,
        array $payload
    ): bool {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return false;
        }

        try {
            return $this->notifications->enqueue([
                'event_key'         => $eventKey,
                'notification_type' => $type,
                'recipient_type'    => $recipientType,
                'recipient_id'      => $recipientId,
                'recipient_ref'     => $recipientRef,
                'payload_json'      => $payloadJson,
                'status'            => 'pending',
                'scheduled_at'      => date('Y-m-d H:i:s'),
            ]) !== null;
        } catch (Throwable $e) {
            error_log('[WhatsApp] Enqueue error: ' . get_class($e));
            return false;
        }
    }

    /**
     * Catat event yang sengaja dilewati tanpa menyimpan nomor lengkap.
     * Event key terpisah agar nomor yang diperbaiki kemudian tetap bisa
     * menghasilkan pengingat normal pada hari yang sama.
     */
    private function recordSkipped(
        string $eventKey,
        string $type,
        string $recipientType,
        int $recipientId,
        string $recipientRef,
        array $payload,
        string $reason
    ): bool {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return false;
        }

        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Nomor WhatsApp penerima tidak tersedia.';
        }

        try {
            return $this->notifications->enqueue([
                'event_key'         => $eventKey,
                'notification_type' => $type,
                'recipient_type'    => $recipientType,
                'recipient_id'      => $recipientId,
                'recipient_ref'     => $recipientRef,
                'payload_json'      => $payloadJson,
                'status'            => 'skipped',
                'scheduled_at'      => date('Y-m-d H:i:s'),
                'error_message'     => mb_substr($reason, 0, 255, 'UTF-8'),
            ]) !== null;
        } catch (Throwable $e) {
            error_log('[WhatsApp] Skip record error: ' . get_class($e));
            return false;
        }
    }

    private function alertFailureToAdmins(string $failedType, array $row, string $errorMessage): void
    {
        try {
            $label = $this->typeLabel($failedType);
            $pesan = "Pengiriman WhatsApp gagal permanen.\n"
                . "Jenis: {$label}\n"
                . 'Penerima: ' . (string) ($row['recipient_ref'] ?? '-') . "\n"
                . "Penyebab: {$errorMessage}";

            $this->notifyAdmins(
                'admin_whatsapp_failure',
                'admin_whatsapp_failure:' . date('YmdH') . ':' . $failedType,
                ['pesan' => $pesan, 'tanggal' => date('Y-m-d')]
            );
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal mengirim notifikasi kegagalan ke admin: ' . get_class($e));
        }
    }

    private function alertDeviceIssue(string $errorMessage): void
    {
        try {
            $this->notifyAdmins(
                'admin_device_issue',
                'admin_device_issue:' . date('YmdH'),
                [
                    'pesan' => "Perangkat WhatsApp Fonnte bermasalah.\nPenyebab: {$errorMessage}\n"
                        . 'Periksa koneksi perangkat di dashboard Fonnte.',
                    'tanggal' => date('Y-m-d'),
                ]
            );
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal mengirim notifikasi perangkat ke admin: ' . get_class($e));
        }
    }

    private function buildMessage(string $type, array $payload): ?string
    {
        $appName = (string) (AppConfig::config()['name'] ?? 'Sistem Absensi Mahasiswa');
        $vars = $this->templateVars($payload, $appName);

        $templateKey = match ($type) {
            'schedule_reminder' => 'whatsapp_template_schedule_reminder',
            'attendance_confirmation' => 'whatsapp_template_attendance_confirmation',
            'absence_notice' => 'whatsapp_template_absence_notice',
            'lecturer_teaching_reminder' => 'whatsapp_template_lecturer_teaching_reminder',
            'lecturer_attendance' => 'whatsapp_template_lecturer_attendance',
            'lecturer_attendance_summary' => 'whatsapp_template_lecturer_attendance_summary',
            'announcement' => 'whatsapp_template_announcement',
            'admin_attendance_summary' => 'whatsapp_template_admin_attendance_summary',
            'admin_attendance_alert', 'admin_approval', 'admin_whatsapp_failure', 'admin_device_issue' => 'whatsapp_template_admin_alert',
            default => null,
        };

        if ($templateKey !== null) {
            $template = $this->getTemplate($templateKey, $this->defaultTemplate($templateKey));
            $message = $this->renderTemplate($template, $vars);

            return trim($message) !== '' ? $message : null;
        }

        $mk = $this->cleanText($payload['nama_mk'] ?? '', 100);
        $kelas = $this->cleanText($payload['nama_kelas'] ?? '', 50);

        if ($mk === '') {
            return null;
        }

        return match ($type) {
            'low_attendance' => $this->buildLowAttendanceMessage($appName, $mk, $kelas, $payload),
            'lecturer_recap' => $this->buildLecturerRecapMessage($appName, $mk, $kelas, $payload),
            default => null,
        };
    }

    private function getTemplate(string $key, string $default): string
    {
        try {
            $value = (string) AppSetting::get($key, '');
        } catch (Throwable) {
            $value = '';
        }

        return trim($value) !== '' ? $value : $default;
    }

    private function renderTemplate(string $template, array $vars): string
    {
        $rendered = preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($vars): string {
                $key = strtolower($matches[1]);
                return array_key_exists($key, $vars) ? (string) $vars[$key] : '';
            },
            $template
        );

        return trim((string) $rendered);
    }

    private function templateVars(array $payload, string $appName): array
    {
        $tanggal = $this->cleanText($payload['tanggal'] ?? '', 20);
        $hari = $this->cleanText($payload['hari'] ?? '', 20);
        $waktuAbsensi = $this->cleanText($payload['waktu_absensi'] ?? '', 30);
        $jamAbsensi = '';
        if (preg_match('/\b(\d{2}:\d{2})(?::\d{2})?\b/', $waktuAbsensi, $m)) {
            $jamAbsensi = $m[1];
        }

        if ($tanggal === '' && $waktuAbsensi !== '') {
            $tanggal = substr($waktuAbsensi, 0, 10);
        }

        return [
            'app_name'       => $appName,
            'nama_mhs'       => $this->cleanText($payload['nama_mhs'] ?? '', 100),
            'nim'            => $this->cleanText($payload['nim'] ?? '', 30),
            'nama_dosen'     => $this->cleanText($payload['nama_dosen'] ?? '', 100),
            'nama_mk'        => $this->cleanText($payload['nama_mk'] ?? '', 100),
            'kode_mk'        => $this->cleanText($payload['kode_mk'] ?? '', 20),
            'nama_kelas'     => $this->cleanText($payload['nama_kelas'] ?? '', 50),
            'status'         => $this->statusLabel((string) ($payload['status'] ?? '')),
            'tanggal'        => $this->formatDateId($tanggal),
            'hari_tanggal'   => $this->formatDayDate($hari, $tanggal),
            'jam_mulai'      => $this->formatTime((string) ($payload['jam_mulai'] ?? '')),
            'jam_selesai'    => $this->formatTime((string) ($payload['jam_selesai'] ?? '')),
            'jam_absensi'    => $jamAbsensi,
            'waktu_absensi'  => $waktuAbsensi,
            'ruangan'        => $this->cleanText($payload['ruangan'] ?? '', 50),
            'menit_sebelum'  => (int) ($payload['menit_sebelum'] ?? 0),
            'pertemuan_ke'   => (int) ($payload['pertemuan_ke'] ?? 0),
            'total_absensi'  => (int) ($payload['total_absensi'] ?? 0),
            'total_hadir'    => (int) ($payload['total_hadir'] ?? 0),
            'total_terlambat' => (int) ($payload['total_terlambat'] ?? 0),
            'total_izin'     => (int) ($payload['total_izin'] ?? 0),
            'total_sakit'    => (int) ($payload['total_sakit'] ?? 0),
            'total_alpha'    => (int) ($payload['total_alpha'] ?? 0),
            'total_belum'    => (int) ($payload['total_belum'] ?? 0),
            'judul'          => $this->cleanText($payload['judul'] ?? '', 120),
            'pesan'          => mb_substr(trim((string) ($payload['pesan'] ?? '')), 0, 2000, 'UTF-8'),
        ];
    }

    private function defaultTemplate(string $key): string
    {
        return match ($key) {
            'whatsapp_template_schedule_reminder' =>
                "*Pengingat Jadwal Kuliah*\n\nHalo {nama_mhs},\nMata kuliah: {nama_mk}\nDosen: {nama_dosen}\n"
                . "Kelas: {nama_kelas}\nHari/Tanggal: {hari_tanggal}\nWaktu: {jam_mulai}-{jam_selesai} WIB\n"
                . "Ruang: {ruangan}\n\nKuliah dimulai sekitar {menit_sebelum} menit lagi.\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_attendance_confirmation' =>
                "*Absensi Tercatat*\n\nNama: {nama_mhs}\nNIM: {nim}\nMata kuliah: {nama_mk}\n"
                . "Dosen: {nama_dosen}\nKelas: {nama_kelas}\nTanggal: {tanggal}\nWaktu: {jam_absensi} WIB\n"
                . "Status: {status}\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_lecturer_teaching_reminder' =>
                "PENGINGAT JADWAL MENGAJAR\n\nYth. Bapak/Ibu {nama_dosen},\n\n"
                . "Anda memiliki jadwal mengajar {menit_sebelum} menit lagi.\n\n"
                . "Mata Kuliah: {nama_mk}\nKelas: {nama_kelas}\nHari/Tanggal: {hari_tanggal}\n"
                . "Waktu: {jam_mulai}-{jam_selesai} WIB\nRuang: {ruangan}\n\n"
                . "Mohon mempersiapkan kegiatan perkuliahan.\n\nPesan ini dikirim otomatis oleh {app_name}.",

            'whatsapp_template_lecturer_attendance' =>
                "*Absensi Mahasiswa*\n\nMahasiswa: {nama_mhs}\nNIM: {nim}\nMata kuliah: {nama_mk}\n"
                . "Kelas: {nama_kelas}\nWaktu: {jam_absensi} WIB\nStatus: {status}\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_lecturer_attendance_summary' =>
                "*Ringkasan Kehadiran Kelas*\n\nMata kuliah: {nama_mk}\nKelas: {nama_kelas}\n"
                . "Pertemuan: {pertemuan_ke}\nHari/Tanggal: {hari_tanggal}\n\n"
                . "Hadir: {total_hadir}\nTerlambat: {total_terlambat}\nIzin: {total_izin}\n"
                . "Sakit: {total_sakit}\nAlpha: {total_alpha}\nBelum absen: {total_belum}\n\n"
                . "Pesan otomatis dari {app_name}.",

            'whatsapp_template_absence_notice' =>
                "*Informasi Ketidakhadiran*\n\nHalo {nama_mhs},\nAnda tercatat TIDAK HADIR pada:\n"
                . "Mata kuliah: {nama_mk}\nKelas: {nama_kelas}\nPertemuan: {pertemuan_ke}\n"
                . "Hari/Tanggal: {hari_tanggal}\nStatus: {status}\n\n"
                . "Jika terdapat kekeliruan, hubungi dosen atau bagian akademik.\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_announcement' =>
                "*{judul}*\n\n{pesan}\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_admin_alert' =>
                "*Notifikasi Admin*\n\n{pesan}\n\nPesan otomatis dari {app_name}.",

            'whatsapp_template_admin_attendance_summary' =>
                "*Ringkasan Absensi Harian*\n\nTanggal: {hari_tanggal}\nTotal absensi tercatat: {total_absensi}\n"
                . "Hadir: {total_hadir}\nTerlambat: {total_terlambat}\nIzin: {total_izin}\n"
                . "Sakit: {total_sakit}\nAlpha: {total_alpha}\n\nPesan otomatis dari {app_name}.",

            default => '',
        };
    }

    private function buildLowAttendanceMessage(string $appName, string $mk, string $kelas, array $payload): string
    {
        $percentage = (float) ($payload['persentase_kehadiran'] ?? 0);
        $minimum = (float) ($payload['batas_minimal'] ?? 0);
        $total = (int) ($payload['total_pertemuan_selesai'] ?? 0);

        return "*Peringatan Kehadiran*\n"
            . "Mata kuliah: {$mk}\n"
            . ($kelas !== '' ? "Kelas: {$kelas}\n" : '')
            . 'Kehadiran saat ini: ' . number_format($percentage, 2, ',', '.') . "%\n"
            . 'Batas minimal: ' . number_format($minimum, 2, ',', '.') . "%\n"
            . "Pertemuan selesai: {$total}\n"
            . "\nMohon segera ditindaklanjuti. Pesan otomatis dari {$appName}.";
    }

    private function buildLecturerRecapMessage(string $appName, string $mk, string $kelas, array $payload): string
    {
        $total = (int) ($payload['total_pertemuan_selesai'] ?? 0);
        $hadir = (int) ($payload['total_hadir'] ?? 0);
        $terlambat = (int) ($payload['total_terlambat'] ?? 0);
        $izin = (int) ($payload['total_izin'] ?? 0);
        $sakit = (int) ($payload['total_sakit'] ?? 0);
        $alpha = (int) ($payload['total_alpha'] ?? 0);

        return "*Rekap Absensi Dosen*\n"
            . "Mata kuliah: {$mk}\n"
            . ($kelas !== '' ? "Kelas: {$kelas}\n" : '')
            . "Pertemuan selesai: {$total}\n"
            . "Hadir: {$hadir} | Terlambat: {$terlambat}\n"
            . "Izin: {$izin} | Sakit: {$sakit} | Alpha: {$alpha}\n"
            . "\nRekap agregat, tanpa data pribadi mahasiswa. Pesan otomatis dari {$appName}.";
    }

    private function formatDateId(string $date): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return $date;
        }

        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        $month = (int) $m[2];

        return isset($months[$month]) ? "{$m[3]} {$months[$month]} {$m[1]}" : $date;
    }

    private function formatDayDate(string $hari, string $date): string
    {
        $days = [
            1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu',
        ];

        $dateText = $this->formatDateId($date);
        $dayName = '';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $ts = strtotime($date);
            if ($ts !== false) {
                $dayName = $days[(int) date('N', $ts)] ?? '';
            }
        }

        if ($dayName === '') {
            $dayName = $this->cleanText($hari, 20);
        }

        if ($dayName !== '' && $dateText !== '' && $dateText !== $date) {
            return $dayName . ', ' . $dateText;
        }

        return $dateText !== '' ? $dateText : $dayName;
    }

    private function formatTime(string $time): string
    {
        if (preg_match('/^(\d{2}):(\d{2})/', $time, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return '';
    }

    private function statusLabel(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'hadir' => 'HADIR',
            'terlambat' => 'TERLAMBAT',
            'izin' => 'IZIN',
            'sakit' => 'SAKIT',
            'alpha' => 'ALPHA',
            default => $status !== '' ? strtoupper($status) : '-',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'schedule_reminder' => 'Pengingat jadwal mahasiswa',
            'attendance_confirmation' => 'Konfirmasi absensi',
            'absence_notice' => 'Pemberitahuan ketidakhadiran',
            'low_attendance' => 'Peringatan kehadiran rendah',
            'lecturer_teaching_reminder' => 'Pengingat jadwal mengajar',
            'lecturer_attendance' => 'Absensi mahasiswa ke dosen',
            'lecturer_attendance_summary' => 'Ringkasan kehadiran dosen',
            'lecturer_recap' => 'Rekap dosen',
            'admin_attendance_alert' => 'Peringatan absensi admin',
            'admin_approval' => 'Pengajuan menunggu persetujuan',
            'admin_whatsapp_failure' => 'Kegagalan WhatsApp',
            'admin_device_issue' => 'Gangguan perangkat Fonnte',
            'admin_attendance_summary' => 'Ringkasan absensi harian',
            'announcement' => 'Pengumuman',
            default => $type,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{type: string, id: int, nama: string, phone: string}>
     */
    private function normalizeRecipients(array $rows): array
    {
        $recipients = [];
        $seen = [];

        foreach ($rows as $row) {
            $type = (string) ($row['recipient_type'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            if (!in_array($type, ['mahasiswa', 'dosen', 'admin'], true) || $id <= 0) {
                continue;
            }

            $key = $type . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $recipients[] = [
                'type' => $type,
                'id' => $id,
                'nama' => $this->cleanText($row['nama'] ?? '', 100),
                'phone' => trim((string) ($row['no_hp'] ?? '')),
            ];
        }

        return $recipients;
    }

    /**
     * @return array<int, int>
     */
    private function parseIdList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        return mb_substr(trim($text), 0, $maxLength, 'UTF-8');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    private function ensureTimezone(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            $timezone = (string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta');
            date_default_timezone_set($timezone !== '' ? $timezone : 'Asia/Jakarta');
        } catch (Throwable) {
            date_default_timezone_set('Asia/Jakarta');
        }

        $done = true;
    }
}
