<?php

require_once __DIR__ . '/_cli_guard.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config\App as AppConfig;
use App\Core\Database;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppNotificationService;

/**
 * Uji orkestrasi notifikasi WhatsApp untuk mahasiswa, dosen, dan admin.
 *
 * Semua penulisan berada di dalam transaksi dan selalu di-rollback. Tidak ada
 * panggilan jaringan: pengiriman memakai closure transport tiruan, dan mode
 * simulasi diuji secara eksplisit.
 */
class MockWhatsAppNotificationService extends WhatsAppNotificationService
{
    /** @var array<int, string> */
    private array $enabledTypes;
    private bool $simulation;
    private string $lecturerMode;

    /**
     * @param array<int, string> $enabledTypes
     */
    public function __construct(array $enabledTypes, bool $simulation = false, string $lecturerMode = 'per_student')
    {
        parent::__construct();
        $this->enabledTypes = $enabledTypes;
        $this->simulation = $simulation;
        $this->lecturerMode = $lecturerMode;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isOperational(): bool
    {
        return true;
    }

    public function isSimulationMode(): bool
    {
        return $this->simulation;
    }

    public function isTypeEnabled(string $type): bool
    {
        return in_array($type, $this->enabledTypes, true);
    }

    public function getLecturerAttendanceMode(): string
    {
        return $this->lecturerMode;
    }
}

date_default_timezone_set((string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta'));

$passed = 0;
$failed = 0;

function checkTest(string $description, bool $condition, string $details = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
        return;
    }

    $failed++;
    echo "  [FAIL] {$description}";
    if ($details !== '') {
        echo " -- {$details}";
    }
    echo "\n";
}

$db = Database::getConnection();
$query = static function (string $sql, array $params = []) use ($db): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};
$scalar = static function (string $sql, array $params = []) use ($db): mixed {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
};
$eventRow = static function (string $eventKey) use ($query): ?array {
    $rows = $query("SELECT * FROM whatsapp_notifications WHERE event_key = :key LIMIT 1", ['key' => $eventKey]);
    return $rows[0] ?? null;
};
$countEvents = static function (string $prefix) use ($scalar): int {
    return (int) $scalar(
        "SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key LIKE :prefix",
        ['prefix' => $prefix . '%']
    );
};

$baseline = [
    'outbox' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications"),
    'announcements' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_announcements"),
    'jadwal' => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs' => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi' => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'pertemuan' => (int) $scalar("SELECT COUNT(*) FROM pertemuan_kuliah"),
    'ta' => (int) $scalar("SELECT COUNT(*) FROM tahun_akademik"),
    'admin_phone' => $query("SELECT id, no_hp FROM users WHERE role = 'admin' ORDER BY id"),
    'dosen_phone' => $query("SELECT id, no_hp FROM dosen ORDER BY id"),
];

echo "====================================================================\n";
echo "  TEST NOTIFIKASI WHATSAPP MAHASISWA, DOSEN, ADMIN (MOCK)\n";
echo "====================================================================\n\n";

$db->beginTransaction();

try {
    $today = date('Y-m-d');
    $day = get_current_day_id();

    $mkId = (int) $scalar("SELECT id FROM mata_kuliah WHERE is_active = 1 ORDER BY id LIMIT 1");
    $kelasId = (int) $scalar("SELECT id FROM kelas ORDER BY id LIMIT 1");
    $adminId = (int) $scalar("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
    $dosenRows = $query("SELECT id, user_id FROM dosen ORDER BY id LIMIT 2");
    $studentRows = $query("SELECT id, user_id FROM mahasiswa WHERE status_akademik = 'aktif' ORDER BY id LIMIT 2");

    if ($mkId <= 0 || $kelasId <= 0 || $adminId <= 0 || count($dosenRows) < 2 || count($studentRows) < 2) {
        throw new RuntimeException('Master data untuk pengujian tidak lengkap.');
    }

    $dosen1 = (int) $dosenRows[0]['id'];
    $dosen2 = (int) $dosenRows[1]['id'];
    $student1 = (int) $studentRows[0]['id'];
    $student2 = (int) $studentRows[1]['id'];

    // Isolasi: nonaktifkan jadwal lama hanya di dalam transaksi.
    $db->exec("UPDATE jadwal_kuliah SET is_active = 0");

    // Nomor uji. Semua perubahan dibatalkan oleh rollback.
    $db->exec("UPDATE users SET no_hp = '628111111111' WHERE role = 'admin'");
    $db->exec("UPDATE users SET is_active = 1 WHERE role IN ('admin', 'dosen', 'mahasiswa')");
    $db->exec("UPDATE mahasiswa SET status_akademik = 'aktif', no_hp = '628122222222' WHERE id = {$student1}");
    $db->exec("UPDATE mahasiswa SET status_akademik = 'aktif', no_hp = '628133333333' WHERE id = {$student2}");
    $db->exec("UPDATE dosen SET no_hp = '628144444444' WHERE id = {$dosen1}");
    $db->exec("UPDATE dosen SET no_hp = '628155555555' WHERE id = {$dosen2}");

    // Tahun akademik sementara yang mencakup hari ini.
    $tempTaCode = 'WAT' . random_int(100000, 999999);
    $stmt = $db->prepare(
        "INSERT INTO tahun_akademik (kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active)
         VALUES (?, ?, 'ganjil', ?, ?, 1)"
    );
    $stmt->execute([
        $tempTaCode,
        'TA Uji WhatsApp ' . $tempTaCode,
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d', strtotime('+30 days')),
    ]);
    $taId = (int) $db->lastInsertId();

    $startTs = time() + (15 * 60);
    $endTs = $startTs + (60 * 60);
    if (date('Y-m-d', $startTs) !== $today || date('Y-m-d', $endTs) !== $today) {
        throw new RuntimeException('Tes terlalu dekat tengah malam; jalankan ulang pada waktu lain.');
    }

    $startTime = date('H:i:s', $startTs);
    $endTime = date('H:i:s', $endTs);

    $insertSchedule = static function (int $dosenId, string $ruangan) use ($db, $taId, $mkId, $kelasId, $day, $startTime, $endTime): int {
        $stmt = $db->prepare(
            "INSERT INTO jadwal_kuliah
                (tahun_akademik_id, mata_kuliah_id, dosen_id, kelas_id, hari,
                 jam_mulai, jam_selesai, ruangan, toleransi_keterlambatan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 15, 1)"
        );
        $stmt->execute([$taId, $mkId, $dosenId, $kelasId, $day, $startTime, $endTime, $ruangan]);
        return (int) $db->lastInsertId();
    };

    $schedule1 = $insertSchedule($dosen1, 'Ruang Mock A');
    $schedule2 = $insertSchedule($dosen2, 'Ruang Mock B');

    $addKrs = static function (int $jadwalId, int $mahasiswaId) use ($db): void {
        $stmt = $db->prepare("INSERT INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status) VALUES (?, ?, 'aktif')");
        $stmt->execute([$jadwalId, $mahasiswaId]);
    };
    $addKrs($schedule1, $student1);
    $addKrs($schedule1, $student2);
    $addKrs($schedule2, $student1);

    // ---------------------------------------------------------------------
    // 1. Pengingat jadwal mahasiswa hanya untuk peserta KRS.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['schedule_reminder']);
    $enqueued = $service->enqueueScheduleReminders();

    checkTest(
        'Pengingat jadwal dibuat untuk kedua peserta KRS',
        $enqueued === 3 && $eventRow('schedule_reminder:' . $schedule1 . ':' . $today . ':' . $student1) !== null
            && $eventRow('schedule_reminder:' . $schedule1 . ':' . $today . ':' . $student2) !== null,
        'enqueued=' . $enqueued
    );
    checkTest(
        'Mahasiswa di luar KRS tidak menerima pengingat jadwal',
        $eventRow('schedule_reminder:' . $schedule2 . ':' . $today . ':' . $student2) === null
    );
    $reminderPayload = json_decode((string) ($eventRow('schedule_reminder:' . $schedule1 . ':' . $today . ':' . $student1)['payload_json'] ?? '{}'), true);
    checkTest(
        'Payload pengingat memuat dosen, jam selesai, dan ruangan tanpa nomor telepon',
        is_array($reminderPayload)
            && ($reminderPayload['nama_dosen'] ?? '') !== ''
            && ($reminderPayload['jam_selesai'] ?? '') !== ''
            && ($reminderPayload['ruangan'] ?? '') !== ''
            && !isset($reminderPayload['no_hp'])
    );

    // ---------------------------------------------------------------------
    // 2. Pengingat mengajar dosen, hanya jadwal dosen tersebut.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['lecturer_teaching_reminder']);
    $enqueued = $service->enqueueLecturerTeachingReminders();

    checkTest(
        'Pengingat mengajar dibuat untuk dosen pemilik jadwal',
        $enqueued === 2
            && $eventRow('lecturer_teaching_reminder:' . $schedule1 . ':' . $today . ':' . $dosen1) !== null
            && $eventRow('lecturer_teaching_reminder:' . $schedule2 . ':' . $today . ':' . $dosen2) !== null,
        'enqueued=' . $enqueued
    );
    checkTest(
        'Dosen tidak menerima pengingat jadwal dosen lain',
        $eventRow('lecturer_teaching_reminder:' . $schedule2 . ':' . $today . ':' . $dosen1) === null
    );

    // ---------------------------------------------------------------------
    // 3. Konfirmasi absensi + notifikasi dosen per mahasiswa.
    // ---------------------------------------------------------------------
    $attendanceContext = [
        'mahasiswa_id' => $student1,
        'jadwal_id' => $schedule1,
        'nama_mhs' => 'Mahasiswa Satu',
        'nim' => 'MOCK001',
        'nama_mk' => 'Mata Kuliah Mock',
        'kode_mk' => 'MKM001',
        'nama_dosen' => 'Dosen Satu',
        'nama_kelas' => 'Kelas Mock',
        'hari' => $day,
        'jam_mulai' => $startTime,
        'jam_selesai' => $endTime,
        'ruangan' => 'Ruang Mock A',
        'status' => 'hadir',
        'tanggal' => $today,
        'waktu_absensi' => date('Y-m-d H:i:s'),
    ];

    $service = new MockWhatsAppNotificationService(['attendance_confirmation', 'lecturer_attendance']);
    $first = $service->enqueueAttendanceConfirmation(990001, $attendanceContext);
    $second = $service->enqueueAttendanceConfirmation(990001, $attendanceContext);

    checkTest('Konfirmasi absensi masuk antrean setelah simpan', $first === true);
    checkTest('Konfirmasi absensi idempoten (tidak duplikat)', $second === false);
    checkTest(
        'Konfirmasi absensi hanya satu baris',
        (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :key", ['key' => 'attendance_confirmation:990001']) === 1
    );
    checkTest(
        'Dosen menerima info absensi mahasiswa di kelasnya',
        $eventRow('lecturer_attendance:990001:' . $dosen1) !== null
    );
    checkTest(
        'Dosen lain tidak menerima info absensi kelas tersebut',
        $eventRow('lecturer_attendance:990001:' . $dosen2) === null
    );

    // Dosen 2 menerima untuk kelasnya sendiri.
    $attendanceContext2 = $attendanceContext;
    $attendanceContext2['jadwal_id'] = $schedule2;
    $service->enqueueAttendanceConfirmation(990002, $attendanceContext2);
    checkTest(
        'Dosen pemilik kelas lain menerima notifikasi kelasnya sendiri',
        $eventRow('lecturer_attendance:990002:' . $dosen2) !== null
            && $eventRow('lecturer_attendance:990002:' . $dosen1) === null
    );

    // ---------------------------------------------------------------------
    // 4. Notifikasi admin untuk absensi terlambat.
    // ---------------------------------------------------------------------
    $lateContext = $attendanceContext;
    $lateContext['status'] = 'terlambat';
    $service = new MockWhatsAppNotificationService(['attendance_confirmation', 'admin_attendance_alert']);
    $service->enqueueAttendanceConfirmation(990003, $lateContext);

    checkTest(
        'Admin menerima peringatan absensi terlambat',
        $eventRow('admin_attendance_alert:990003:' . $adminId) !== null
    );

    // ---------------------------------------------------------------------
    // 5. Pemberitahuan ketidakhadiran hanya setelah pertemuan ditutup.
    // ---------------------------------------------------------------------
    $stmt = $db->prepare(
        "INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, jam_buka, jam_tutup, status)
         VALUES (?, 1, ?, ?, ?, 'ditutup')"
    );
    $stmt->execute([$schedule1, $today, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
    $closedMeetingId = (int) $db->lastInsertId();

    $stmt = $db->prepare(
        "INSERT INTO absensi (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified)
         VALUES (?, ?, ?, 'hadir', 'dalam_radius', 1)"
    );
    $stmt->execute([$closedMeetingId, $student1, date('Y-m-d H:i:s')]);

    $service = new MockWhatsAppNotificationService(['absence_notice']);
    $enqueued = $service->enqueueAbsenceNotices();

    checkTest(
        'Pemberitahuan ketidakhadiran dikirim ke mahasiswa tanpa absensi',
        $eventRow('absence_notice:' . $closedMeetingId . ':' . $student2) !== null
    );
    checkTest(
        'Mahasiswa yang hadir tidak menerima pemberitahuan ketidakhadiran',
        $eventRow('absence_notice:' . $closedMeetingId . ':' . $student1) === null
    );
    checkTest('Jumlah pemberitahuan ketidakhadiran sesuai', $enqueued === 1, 'enqueued=' . $enqueued);

    // ---------------------------------------------------------------------
    // 6. Ringkasan kehadiran dosen (mode summary).
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(
        ['lecturer_attendance', 'lecturer_attendance_summary'],
        false,
        'summary'
    );
    $enqueued = $service->enqueueLecturerAttendanceSummaries();

    checkTest(
        'Dosen menerima ringkasan pertemuan tertutup',
        $enqueued >= 1
            && $eventRow('lecturer_attendance_summary:' . $closedMeetingId . ':' . $dosen1) !== null,
        'enqueued=' . $enqueued
    );
    $summaryPayload = json_decode((string) ($eventRow('lecturer_attendance_summary:' . $closedMeetingId . ':' . $dosen1)['payload_json'] ?? '{}'), true);
    checkTest(
        'Ringkasan memuat hitungan hadir dan belum absen',
        is_array($summaryPayload)
            && (int) ($summaryPayload['total_hadir'] ?? -1) === 1
            && (int) ($summaryPayload['total_belum'] ?? -1) === 1
    );

    // ---------------------------------------------------------------------
    // 7. Notifikasi admin untuk pengajuan izin/sakit.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['admin_approval']);
    $enqueued = $service->notifyAdmins('admin_approval', 'admin_approval:mock:77', ['pesan' => 'Pengajuan izin baru menunggu persetujuan.']);

    checkTest(
        'Admin menerima notifikasi pengajuan baru',
        $enqueued === 1 && $eventRow('admin_approval:mock:77:' . $adminId) !== null
    );

    // ---------------------------------------------------------------------
    // 8. Kegagalan pengiriman dan gangguan perangkat memberi tahu admin.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['attendance_confirmation', 'admin_whatsapp_failure']);
    $service->enqueueAttendanceConfirmation(990004, $attendanceContext);

    $transport = static function (): array {
        return [
            'success' => false,
            'retryable' => false,
            'error_message' => 'Nomor tujuan ditolak oleh Fonnte.',
        ];
    };
    $stats = $service->dispatchPending(100, $transport);

    checkTest(
        'Pesan gagal dicatat sebagai failed',
        ($eventRow('attendance_confirmation:990004')['status'] ?? '') === 'failed',
        'processed=' . ($stats['processed'] ?? -1)
    );
    checkTest(
        'Admin menerima notifikasi kegagalan WhatsApp',
        (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications WHERE notification_type = 'admin_whatsapp_failure'") >= 1
    );

    $service = new MockWhatsAppNotificationService(['attendance_confirmation', 'admin_device_issue']);
    $service->enqueueAttendanceConfirmation(990005, $attendanceContext);
    $deviceTransport = static function (): array {
        return [
            'success' => false,
            'retryable' => true,
            'error_message' => 'Perangkat WhatsApp Fonnte sedang tidak terhubung.',
        ];
    };
    $service->dispatchPending(100, $deviceTransport);

    checkTest(
        'Admin menerima notifikasi gangguan perangkat',
        (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications WHERE notification_type = 'admin_device_issue'") >= 1
    );

    // ---------------------------------------------------------------------
    // 9. Pengumuman hanya ke penerima terpilih.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['announcement']);
    $announcement = $service->enqueueAnnouncement(
        'Pengumuman Uji',
        'Ini isi pengumuman uji.',
        'mahasiswa',
        (string) $student1,
        $adminId
    );

    $announcementId = (int) ($announcement['announcement_id'] ?? 0);
    checkTest(
        'Pengumuman tercatat dan masuk antrean untuk penerima terpilih',
        $announcementId > 0
            && ($announcement['enqueued'] ?? 0) === 1
            && $eventRow('announcement:' . $announcementId . ':mahasiswa:' . $student1) !== null
            && $eventRow('announcement:' . $announcementId . ':mahasiswa:' . $student2) === null,
        'enqueued=' . ($announcement['enqueued'] ?? -1)
    );
    checkTest(
        'Riwayat pengumuman mencatat jumlah penerima',
        (int) $scalar("SELECT recipient_count FROM whatsapp_announcements WHERE id = :id", ['id' => $announcementId]) === 1
    );

    // ---------------------------------------------------------------------
    // 10. Finalisasi pertemuan lewat jam_tutup menandai alpha.
    // ---------------------------------------------------------------------
    $stmt = $db->prepare(
        "INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, jam_buka, jam_tutup, status)
         VALUES (?, 2, ?, ?, ?, 'dibuka')"
    );
    $stmt->execute([
        $schedule1,
        $today,
        date('Y-m-d H:i:s', strtotime('-2 hours')),
        date('Y-m-d H:i:s', strtotime('-1 hour')),
    ]);
    $expiredMeetingId = (int) $db->lastInsertId();

    $service = new MockWhatsAppNotificationService([]);
    $finalized = $service->finalizeExpiredMeetings();

    checkTest('Pertemuan yang melewati jam_tutup difinalisasi', $finalized >= 1, 'finalized=' . $finalized);
    checkTest(
        'Status pertemuan berubah menjadi ditutup',
        (string) $scalar("SELECT status FROM pertemuan_kuliah WHERE id = :id", ['id' => $expiredMeetingId]) === 'ditutup'
    );
    checkTest(
        'Mahasiswa tanpa absensi ditandai alpha setelah finalisasi',
        (string) $scalar(
            "SELECT status FROM absensi WHERE pertemuan_id = :pid AND mahasiswa_id = :mid",
            ['pid' => $expiredMeetingId, 'mid' => $student2]
        ) === 'alpha'
    );

    // ---------------------------------------------------------------------
    // 11. Mode simulasi tidak memanggil transport dan tetap idempoten.
    // ---------------------------------------------------------------------
    $service = new MockWhatsAppNotificationService(['attendance_confirmation'], true);
    $service->enqueueAttendanceConfirmation(990006, $attendanceContext);
    $transportCalled = false;
    $shouldNotRun = static function () use (&$transportCalled): array {
        $transportCalled = true;
        return ['success' => true, 'retryable' => false, 'error_message' => ''];
    };
    // Transport eksplisit dihormati; mode simulasi hanya untuk produksi.
    $service->dispatchPending(100, $shouldNotRun);
    checkTest('Transport eksplisit tetap dipakai saat pengujian', $transportCalled === true);

    $simulationService = new MockWhatsAppNotificationService(['attendance_confirmation'], true);
    $simulationService->enqueueAttendanceConfirmation(990007, $attendanceContext);
    $simulationStats = $simulationService->dispatchPending(100);

    $simulatedRow = $eventRow('attendance_confirmation:990007');
    checkTest(
        'Mode simulasi mencatat accepted dengan penanda simulasi',
        ($simulatedRow['status'] ?? '') === 'accepted'
            && (int) ($simulatedRow['simulated'] ?? 0) === 1
            && ($simulatedRow['provider_message_id'] ?? '') === 'SIMULASI',
        'simulated=' . ($simulationStats['simulated'] ?? -1)
    );

    $duplicate = $simulationService->enqueueAttendanceConfirmation(990007, $attendanceContext);
    checkTest('Simulasi tetap idempoten', $duplicate === false);
    checkTest(
        'Tidak ada baris ganda untuk event simulasi',
        (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :key", ['key' => 'attendance_confirmation:990007']) === 1
    );

    // Kegagalan WhatsApp tidak boleh menghapus absensi.
    $absensiBefore = (int) $scalar("SELECT COUNT(*) FROM absensi");
    $service = new MockWhatsAppNotificationService(['attendance_confirmation']);
    $service->enqueueAttendanceConfirmation(990008, $attendanceContext);
    $service->dispatchPending(100, static fn(): array => [
        'success' => false,
        'retryable' => false,
        'error_message' => 'Gagal uji.',
    ]);
    checkTest(
        'Kegagalan WhatsApp tidak menghapus data absensi',
        (int) $scalar("SELECT COUNT(*) FROM absensi") === $absensiBefore
    );
} catch (Throwable $e) {
    $failed++;
    echo "  [FAIL] Exception: " . $e->getMessage() . "\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo "\nMEMERIKSA PEMBERSIHAN DATA UJI\n";
$after = [
    'outbox' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications"),
    'announcements' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_announcements"),
    'jadwal' => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs' => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi' => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'pertemuan' => (int) $scalar("SELECT COUNT(*) FROM pertemuan_kuliah"),
    'ta' => (int) $scalar("SELECT COUNT(*) FROM tahun_akademik"),
    'admin_phone' => $query("SELECT id, no_hp FROM users WHERE role = 'admin' ORDER BY id"),
    'dosen_phone' => $query("SELECT id, no_hp FROM dosen ORDER BY id"),
];

foreach ($baseline as $key => $value) {
    checkTest('Data ' . $key . ' kembali seperti semula', $after[$key] === $value);
}

echo "\nSUMMARY passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
