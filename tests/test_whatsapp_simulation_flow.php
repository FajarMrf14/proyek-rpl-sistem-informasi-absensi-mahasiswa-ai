<?php

require_once __DIR__ . '/_cli_guard.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config\App as AppConfig;
use App\Core\Database;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppNotificationService;

date_default_timezone_set((string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta'));

/**
 * Service pengujian aliran simulasi WhatsApp terisolasi.
 *
 * Mengunci isSimulationMode() = true dan isEnabled() = true secara eksplisit
 * agar seluruh alur berjalan melalui cabang simulasi murni tanpa request jaringan.
 */
class SimulationFlowTestService extends WhatsAppNotificationService
{
    /** @var array<int, string> */
    private array $enabledTypes;
    private string $lecturerMode;

    /**
     * @param array<int, string> $enabledTypes
     */
    public function __construct(array $enabledTypes, string $lecturerMode = 'per_student')
    {
        parent::__construct();
        $this->enabledTypes = $enabledTypes;
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
        return true; // Mode simulasi selalu aktif untuk pengujian ini
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

// Snapshot baseline sebelum pengujian
$historicalOutbox = $query("SELECT id, status, attempts, simulated, error_message FROM whatsapp_notifications ORDER BY id ASC");
$baseline = [
    'outbox_count'   => count($historicalOutbox),
    'outbox_ids'     => array_column($historicalOutbox, 'id'),
    'announcements'  => (int) $scalar("SELECT COUNT(*) FROM whatsapp_announcements"),
    'jadwal'         => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs'            => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi'        => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'pertemuan'      => (int) $scalar("SELECT COUNT(*) FROM pertemuan_kuliah"),
    'ta'             => (int) $scalar("SELECT COUNT(*) FROM tahun_akademik"),
];

echo "====================================================================\n";
echo "  PENGUJIAN SIMULASI END-TO-END NOTIFIKASI WHATSAPP (FONNTE)\n";
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

    $dosen1 = (int) $dosenRows[0]['id'];
    $dosen2 = (int) $dosenRows[1]['id'];
    $student1 = (int) $studentRows[0]['id'];
    $student2 = (int) $studentRows[1]['id'];

    // Update kontak sementara di dalam transaksi
    $db->exec("UPDATE users SET no_hp = '628111111111' WHERE role = 'admin'");
    $db->exec("UPDATE users SET is_active = 1 WHERE role IN ('admin', 'dosen', 'mahasiswa')");
    $db->exec("UPDATE mahasiswa SET status_akademik = 'aktif', no_hp = '628122222222' WHERE id = {$student1}");
    $db->exec("UPDATE mahasiswa SET status_akademik = 'aktif', no_hp = '628133333333' WHERE id = {$student2}");
    $db->exec("UPDATE dosen SET no_hp = '628144444444' WHERE id = {$dosen1}");
    $db->exec("UPDATE dosen SET no_hp = '628155555555' WHERE id = {$dosen2}");

    // Nonaktifkan jadwal lama di dalam transaksi agar isolasi terjamin
    $db->exec("UPDATE jadwal_kuliah SET is_active = 0");

    // Tahun akademik sementara
    $tempTaCode = 'WS' . random_int(100000, 999999);
    $stmt = $db->prepare(
        "INSERT INTO tahun_akademik (kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active)
         VALUES (?, ?, 'ganjil', ?, ?, 1)"
    );
    $stmt->execute([
        $tempTaCode,
        'TA Simulasi ' . $tempTaCode,
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d', strtotime('+30 days')),
    ]);
    $taId = (int) $db->lastInsertId();

    // Jadwal dimulai 20 menit dari sekarang (masuk jendela pengingat 30 menit)
    $startTs = time() + (20 * 60);
    $endTs = $startTs + (60 * 60);
    $startTime = date('H:i:s', $startTs);
    $endTime = date('H:i:s', $endTs);

    $stmtJadwal = $db->prepare(
        "INSERT INTO jadwal_kuliah
            (tahun_akademik_id, mata_kuliah_id, dosen_id, kelas_id, hari,
             jam_mulai, jam_selesai, ruangan, toleransi_keterlambatan, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 15, 1)"
    );
    $stmtJadwal->execute([$taId, $mkId, $dosen1, $kelasId, $day, $startTime, $endTime, 'Lab Komputer AI']);
    $jadwalId = (int) $db->lastInsertId();

    // Daftarkan mahasiswa ke jadwal (KRS)
    $stmtKrs = $db->prepare("INSERT INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status) VALUES (?, ?, 'aktif')");
    $stmtKrs->execute([$jadwalId, $student1]);
    $stmtKrs->execute([$jadwalId, $student2]);

    echo "--- 1. PENGUJIAN NOTIFIKASI MAHASISWA (SIMULASI) ---\n";

    // 1.1 Pengingat Jadwal Kuliah 30 Menit Sebelum Kelas
    $serviceMhs = new SimulationFlowTestService(['schedule_reminder', 'attendance_confirmation', 'absence_notice']);
    $enqueuedMhsRemind = $serviceMhs->enqueueScheduleReminders();
    checkTest('Pengingat jadwal mahasiswa berhasil diantrekan (2 peserta KRS)', $enqueuedMhsRemind === 2);

    $dispatchMhs1 = $serviceMhs->dispatchPending(10);
    checkTest('Worker memproses pengingat jadwal mahasiswa dalam mode simulasi', $dispatchMhs1['simulated'] === 2 && $dispatchMhs1['accepted'] === 2);

    $rowRemindMhs = $eventRow('schedule_reminder:' . $jadwalId . ':' . $today . ':' . $student1);
    checkTest(
        'Pesan pengingat mahasiswa berstatus accepted dengan penanda simulated = 1',
        $rowRemindMhs !== null && $rowRemindMhs['status'] === 'accepted' && (int)$rowRemindMhs['simulated'] === 1 && $rowRemindMhs['provider_message_id'] === 'SIMULASI'
    );

    // 1.2 Konfirmasi Absensi Mahasiswa
    $stmtPertemuan = $db->prepare(
        "INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, jam_buka, jam_tutup, materi_pembahasan, status)
         VALUES (?, 1, ?, ?, ?, 'Materi AI', 'dibuka')"
    );
    $stmtPertemuan->execute([$jadwalId, $today, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
    $pertemuanId = (int) $db->lastInsertId();

    $stmtAbsen = $db->prepare(
        "INSERT INTO absensi (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified)
         VALUES (?, ?, ?, 'hadir', 'dalam_radius', 1)"
    );
    $stmtAbsen->execute([$pertemuanId, $student1, date('Y-m-d H:i:s')]);
    $absensiId1 = (int) $db->lastInsertId();

    $attendanceContext = [
        'mahasiswa_id' => $student1,
        'jadwal_id'    => $jadwalId,
        'nama_mhs'     => 'Arya Kusuma',
        'nim'          => '4122001',
        'nama_mk'      => 'Kecerdasan Buatan',
        'kode_mk'      => 'MK001',
        'nama_dosen'   => 'Dr. Yudhistira',
        'nama_kelas'   => 'TI-2022-A',
        'hari'         => $day,
        'jam_mulai'    => $startTime,
        'jam_selesai'  => $endTime,
        'ruangan'      => 'Lab Komputer AI',
        'status'       => 'hadir',
        'tanggal'      => $today,
        'waktu_absensi'=> date('Y-m-d H:i:s'),
    ];

    $enqueuedConfirm = $serviceMhs->enqueueAttendanceConfirmation($absensiId1, $attendanceContext);
    checkTest('Konfirmasi absensi mahasiswa berhasil diantrekan', $enqueuedConfirm === true);

    $dispatchConfirm = $serviceMhs->dispatchPending(10);
    checkTest('Worker memproses konfirmasi absensi dalam mode simulasi', $dispatchConfirm['simulated'] >= 1 && $dispatchConfirm['accepted'] >= 1);

    $rowConfirm = $eventRow('attendance_confirmation:' . $absensiId1);
    checkTest(
        'Pesan konfirmasi absensi berstatus accepted dengan simulated = 1',
        $rowConfirm !== null && $rowConfirm['status'] === 'accepted' && (int)$rowConfirm['simulated'] === 1
    );

    // 1.3 Informasi Ketidakhadiran (Absence Notice) Mahasiswa saat Pertemuan Ditutup
    $db->prepare("UPDATE pertemuan_kuliah SET status = 'ditutup', jam_tutup = ? WHERE id = ?")
       ->execute([date('Y-m-d H:i:s'), $pertemuanId]);

    // Student2 belum absen di pertemuan tertutup ini -> tercatat alpha
    $enqueuedAbsence = $serviceMhs->enqueueAbsenceNotices();
    checkTest('Pemberitahuan ketidakhadiran berhasil diantrekan untuk mahasiswa alpha', $enqueuedAbsence >= 1);

    $dispatchAbsence = $serviceMhs->dispatchPending(10);
    checkTest('Worker memproses pemberitahuan ketidakhadiran dengan simulated = 1', $dispatchAbsence['simulated'] >= 1);

    echo "\n--- 2. PENGUJIAN NOTIFIKASI DOSEN (SIMULASI) ---\n";

    $serviceDosen = new SimulationFlowTestService([
        'attendance_confirmation',
        'lecturer_teaching_reminder',
        'lecturer_attendance',
        'lecturer_attendance_summary',
    ], 'per_student');

    // 2.1 Pengingat Jadwal Mengajar 30 Menit Sebelum Kelas
    $enqueuedDosenRemind = $serviceDosen->enqueueLecturerTeachingReminders();
    checkTest('Pengingat jadwal mengajar dosen berhasil diantrekan', $enqueuedDosenRemind === 1);

    $dispatchDosenRemind = $serviceDosen->dispatchPending(10);
    checkTest('Worker memproses pengingat mengajar dosen dalam mode simulasi', $dispatchDosenRemind['simulated'] === 1);

    $rowRemindDosen = $eventRow('lecturer_teaching_reminder:' . $jadwalId . ':' . $today . ':' . $dosen1);
    checkTest(
        'Pesan pengingat dosen berstatus accepted dengan simulated = 1',
        $rowRemindDosen !== null && $rowRemindDosen['status'] === 'accepted' && (int)$rowRemindDosen['simulated'] === 1
    );

    // 2.2 Informasi Absensi Mahasiswa ke Dosen Pengampu (mode per_student)
    $absensiId2 = 99802;
    $attendanceContext2 = array_merge($attendanceContext, [
        'mahasiswa_id' => $student2,
        'nama_mhs'     => 'Galang Marcellino',
        'nim'          => '4122002',
    ]);
    $serviceDosen->enqueueAttendanceConfirmation($absensiId2, $attendanceContext2);

    $dispatchLecturerAttend = $serviceDosen->dispatchPending(10);
    checkTest('Worker memproses notifikasi absensi mahasiswa ke dosen', $dispatchLecturerAttend['simulated'] >= 1);

    $rowLecturerAttend = $eventRow('lecturer_attendance:' . $absensiId2 . ':' . $dosen1);
    checkTest(
        'Pesan notifikasi absensi ke dosen tercatat dengan simulated = 1',
        $rowLecturerAttend !== null && $rowLecturerAttend['status'] === 'accepted' && (int)$rowLecturerAttend['simulated'] === 1
    );

    // 2.3 Ringkasan Kehadiran Kelas (Pertemuan Selesai / Ditutup)
    $serviceSummary = new SimulationFlowTestService(['lecturer_attendance', 'lecturer_attendance_summary'], 'summary');
    $enqueuedSummary = $serviceSummary->enqueueLecturerAttendanceSummaries();
    checkTest('Ringkasan kehadiran kelas berhasil diantrekan untuk dosen', $enqueuedSummary >= 1);

    $dispatchSummary = $serviceSummary->dispatchPending(10);
    checkTest('Worker memproses ringkasan kehadiran kelas dengan simulated = 1', $dispatchSummary['simulated'] >= 1);

    echo "\n--- 3. PENGUJIAN NOTIFIKASI ADMIN (SIMULASI) ---\n";

    $serviceAdmin = new SimulationFlowTestService([
        'attendance_confirmation',
        'admin_attendance_alert',
        'admin_approval',
        'admin_attendance_summary',
    ]);

    // 3.1 Peringatan Absensi Terlambat / Alpha untuk Admin
    $absensiIdLate = 99803;
    $lateContext = array_merge($attendanceContext, [
        'status' => 'terlambat',
    ]);
    $serviceAdmin->enqueueAttendanceConfirmation($absensiIdLate, $lateContext);

    $dispatchLate = $serviceAdmin->dispatchPending(10);
    checkTest('Worker memproses peringatan absensi terlambat ke admin', $dispatchLate['simulated'] >= 1);

    $rowAdminLate = $eventRow('admin_attendance_alert:' . $absensiIdLate . ':' . $adminId);
    checkTest(
        'Pesan peringatan keterlambatan admin berstatus accepted dengan simulated = 1',
        $rowAdminLate !== null && $rowAdminLate['status'] === 'accepted' && (int)$rowAdminLate['simulated'] === 1
    );

    // 3.2 Notifikasi Pengajuan Izin Menunggu Persetujuan
    $izinIdMock = 99901;
    $enqueuedApproval = $serviceAdmin->notifyAdmins(
        'admin_approval',
        'admin_approval:' . $izinIdMock,
        ['pesan' => 'Pengajuan izin sakit mahasiswa memerlukan persetujuan.', 'tanggal' => $today]
    );
    checkTest('Notifikasi pengajuan izin berhasil diantrekan ke admin', $enqueuedApproval >= 1);

    $dispatchApproval = $serviceAdmin->dispatchPending(10);
    checkTest('Worker memproses notifikasi approval izin ke admin dengan simulated = 1', $dispatchApproval['simulated'] >= 1);

    // 3.2b Notifikasi Persetujuan Izin (Disetujui Admin) ke Mahasiswa & Dosen
    $stmtPertemuanIzin = $db->prepare(
        "INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, jam_buka, jam_tutup, materi_pembahasan, status)
         VALUES (?, 2, ?, ?, ?, 'Materi AI 2', 'ditutup')"
    );
    $stmtPertemuanIzin->execute([$jadwalId, $today, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
    $pertemuanIdIzin = (int) $db->lastInsertId();

    $stmtAbsenIzin = $db->prepare(
        "INSERT INTO absensi (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified)
         VALUES (?, ?, ?, 'izin', 'dalam_radius', 1)"
    );
    $stmtAbsenIzin->execute([$pertemuanIdIzin, $student1, date('Y-m-d H:i:s')]);
    $absensiIdIzin = (int) $db->lastInsertId();

    $izinContext = array_merge($attendanceContext, [
        'status' => 'izin',
    ]);
    $serviceMhs->enqueueAttendanceConfirmation($absensiIdIzin, $izinContext);
    $dispatchIzin = $serviceMhs->dispatchPending(10);
    checkTest('Mahasiswa menerima konfirmasi absensi saat izin disetujui admin', $dispatchIzin['simulated'] >= 1);

    $rowIzinConfirm = $eventRow('attendance_confirmation:' . $absensiIdIzin);
    checkTest(
        'Pesan konfirmasi izin berstatus accepted dan memuat status IZIN',
        $rowIzinConfirm !== null && $rowIzinConfirm['status'] === 'accepted' && (int)$rowIzinConfirm['simulated'] === 1
    );

    // 3.3 Ringkasan Absensi Harian untuk Admin
    $enqueuedAdminSummary = $serviceAdmin->enqueueAdminAttendanceSummary($today);
    checkTest('Ringkasan absensi harian berhasil diantrekan ke admin', $enqueuedAdminSummary >= 1);

    $dispatchAdminSummary = $serviceAdmin->dispatchPending(10);
    checkTest('Worker memproses ringkasan harian admin dengan simulated = 1', $dispatchAdminSummary['simulated'] >= 1);

    echo "\n--- 4. PENGUJIAN PENGUMUMAN MASSAL (SIMULASI - 6 TARGET) ---\n";

    $serviceAnn = new SimulationFlowTestService(['announcement']);

    // 4.1 Pengumuman ke Seluruh Mahasiswa
    $ann1 = $serviceAnn->enqueueAnnouncement(
        'Pengumuman Ujian Tengah Semester',
        'Jadwal UTS telah dirilis, harap cek kartu ujian.',
        'all_mahasiswa',
        null,
        $adminId
    );
    checkTest('Pengumuman all_mahasiswa tercatat dengan jumlah penerima tepat (3 mahasiswa)', $ann1['recipients'] === 3 && $ann1['announcement_id'] > 0);

    // 4.2 Pengumuman ke Seluruh Dosen
    $ann2 = $serviceAnn->enqueueAnnouncement(
        'Rapat Dosen Pengampu',
        'Rapat koordinasi nilai semester ganjil diadakan besok.',
        'all_dosen',
        null,
        $adminId
    );
    checkTest('Pengumuman all_dosen tercatat dengan jumlah penerima tepat (2 dosen)', $ann2['recipients'] === 2 && $ann2['announcement_id'] > 0);

    // 4.3 Pengumuman ke Seluruh Pengguna (all_users)
    $annAll = $serviceAnn->enqueueAnnouncement(
        'Hari Libur Nasional',
        'Kampus libur bersama menyambut hari besar nasional.',
        'all_users',
        null,
        $adminId
    );
    checkTest('Pengumuman all_users mencakup mahasiswa, dosen, dan admin aktif (6 pengguna)', $annAll['recipients'] === 6 && $annAll['announcement_id'] > 0);

    // 4.4 Pengumuman Berdasarkan Kelas Spesifik
    $ann3 = $serviceAnn->enqueueAnnouncement(
        'Perubahan Ruang Kuliah',
        'Kuliah AI dialihkan ke Lab Komputer 2.',
        'kelas',
        (string) $kelasId,
        $adminId
    );
    checkTest('Pengumuman spesifik kelas tercatat dan masuk antrean', $ann3['announcement_id'] > 0 && $ann3['recipients'] > 0);

    // 4.5 Pengumuman Berdasarkan ID Mahasiswa Tertentu
    $annMhsId = $serviceAnn->enqueueAnnouncement(
        'Panggilan Mahasiswa',
        'Harap menghadap bagian akademik.',
        'mahasiswa',
        (string) $student1,
        $adminId
    );
    checkTest('Pengumuman target mahasiswa spesifik tercatat untuk 1 penerima', $annMhsId['recipients'] === 1 && $annMhsId['announcement_id'] > 0);

    // 4.6 Pengumuman Berdasarkan ID Dosen Tertentu
    $annDsnId = $serviceAnn->enqueueAnnouncement(
        'Pemberitahuan Dosen',
        'Batas akhir input nilai perkuliahan.',
        'dosen',
        (string) $dosen1,
        $adminId
    );
    checkTest('Pengumuman target dosen spesifik tercatat untuk 1 penerima', $annDsnId['recipients'] === 1 && $annDsnId['announcement_id'] > 0);

    // 4.7 Pengiriman Simulasi Semua Pengumuman
    $dispatchAnn = $serviceAnn->dispatchPending(50);
    checkTest(
        'Worker memproses seluruh antrean pengumuman dalam mode simulasi',
        $dispatchAnn['simulated'] >= ($ann1['recipients'] + $ann2['recipients'] + $annAll['recipients'] + $ann3['recipients'] + $annMhsId['recipients'] + $annDsnId['recipients'])
    );

    // Verifikasi pesan pengumuman berstatus accepted dan simulated = 1
    $sampleAnnRow = $query(
        "SELECT id, status, simulated, provider_message_id FROM whatsapp_notifications
         WHERE notification_type = 'announcement' AND event_key LIKE :prefix LIMIT 1",
        ['prefix' => 'announcement:' . $ann1['announcement_id'] . ':%']
    );
    checkTest(
        'Pesan pengumuman berstatus accepted dengan penanda simulated = 1',
        !empty($sampleAnnRow) && $sampleAnnRow[0]['status'] === 'accepted' && (int)$sampleAnnRow[0]['simulated'] === 1
    );

    echo "\n--- 5. INTEGRITAS DATA OUTBOX HISTORIS (DALAM TRANSAKSI) ---\n";

    $placeholders = implode(',', array_fill(0, count($baseline['outbox_ids']), '?'));
    $historicalNow = $query("SELECT id, status, attempts, simulated, error_message FROM whatsapp_notifications WHERE id IN ({$placeholders}) ORDER BY id ASC", $baseline['outbox_ids']);
    checkTest('Seluruh rekaman outbox historis (' . count($baseline['outbox_ids']) . ' baris) tidak tersentuh atau diubah selama pengujian berlangsung', $historicalNow === $historicalOutbox);

} finally {
    // Selalu rollback seluruh data pengujian
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo "\n--- 6. VERIFIKASI PEMBERSIHAN DATA PASCA-ROLLBACK ---\n";

$afterOutbox = $query("SELECT id, status, attempts, simulated, error_message FROM whatsapp_notifications ORDER BY id ASC");
checkTest('Jumlah baris outbox kembali tepat ' . $baseline['outbox_count'] . ' rekaman', count($afterOutbox) === $baseline['outbox_count']);
checkTest('ID dan status outbox historis 100% identik dengan baseline', $afterOutbox === $historicalOutbox);
checkTest('Tabel riwayat pengumuman bersih (kembali ke baseline)', (int)$scalar("SELECT COUNT(*) FROM whatsapp_announcements") === $baseline['announcements']);
checkTest('Tabel jadwal kuliah bersih dari data sementara', (int)$scalar("SELECT COUNT(*) FROM jadwal_kuliah") === $baseline['jadwal']);
checkTest('Tabel kelas_mahasiswa (KRS) bersih', (int)$scalar("SELECT COUNT(*) FROM kelas_mahasiswa") === $baseline['krs']);
checkTest('Tabel absensi bersih dari data sementara', (int)$scalar("SELECT COUNT(*) FROM absensi") === $baseline['absensi']);
checkTest('Tabel tahun_akademik bersih', (int)$scalar("SELECT COUNT(*) FROM tahun_akademik") === $baseline['ta']);

echo "\n====================================================================\n";
echo "SUMMARY SIMULATION FLOW: passed={$passed} failed={$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
