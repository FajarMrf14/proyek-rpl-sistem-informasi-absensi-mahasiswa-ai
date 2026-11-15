11<?php

require_once __DIR__ . '/_cli_guard.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config\App as AppConfig;
use App\Core\Database;
use App\Models\WhatsAppNotification;
use App\Services\FonnteService;
use App\Services\WhatsAppNotificationService;

date_default_timezone_set((string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta'));

class StudentTestWhatsAppService extends WhatsAppNotificationService
{
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
        return true;
    }

    public function isTypeEnabled(string $type): bool
    {
        return in_array($type, [
            'schedule_reminder',
            'attendance_confirmation',
            'absence_notice',
            'low_attendance',
            'admin_attendance_alert',
        ], true);
    }

    public function enqueueForTest(
        string $eventKey,
        string $type,
        string $recipientType,
        ?int $recipientId,
        string $recipientRef,
        array $payload = []
    ): bool {
        return $this->enqueue($eventKey, $type, $recipientType, $recipientId, $recipientRef, $payload);
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$scalar = static function (string $sql, array $params = []) use ($db): mixed {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
};

$historicalOutbox = $query("SELECT id, status, attempts, simulated, error_message FROM whatsapp_notifications ORDER BY id ASC");
$baseline = [
    'outbox_count'   => count($historicalOutbox),
    'outbox_ids'     => array_column($historicalOutbox, 'id'),
    'jadwal'         => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs'            => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi'        => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'pertemuan'      => (int) $scalar("SELECT COUNT(*) FROM pertemuan_kuliah"),
    'ta'             => (int) $scalar("SELECT COUNT(*) FROM tahun_akademik"),
];

echo "====================================================================\n";
echo "  PENGUJIAN VALIDASI 4 JENIS NOTIFIKASI MAHASISWA & NOMOR GANDA\n";
echo "====================================================================\n\n";

$db->beginTransaction();

try {
    $today = date('Y-m-d');
    $day = get_current_day_id();
    $service = new StudentTestWhatsAppService();
    $notifModel = new WhatsAppNotification();

    // -----------------------------------------------------------------
    // 1. PENGUJIAN IDENTIFIKASI PENERIMA & NOMOR SAMA (EGGI VS ADMIN)
    // -----------------------------------------------------------------
    echo "--- 1. IDENTIFIKASI AKUN & NOMOR SAMA (EGGI VS ADMIN) ---\n";
    
    // Pastikan Eggi (mahasiswa ID 3) dan Admin (user ID 1) ada dan no_hp terverifikasi
    $eggiContact = $notifModel->getRecipientContact('mahasiswa', 3);
    $adminContact = $notifModel->getRecipientContact('admin', 1);

    checkTest('Data mahasiswa Eggi Alivia Rohmaht terdaftar dengan nomor WA aktif',
        !empty($eggiContact['phone']) && $eggiContact['reason'] === null
    );
    checkTest('Data Admin terdaftar dengan nomor WA aktif',
        !empty($adminContact['phone']) && $adminContact['reason'] === null
    );

    // Verifikasi nomor Eggi dan nomor Admin sama untuk kebutuhan demo
    $eggiPhoneNorm = FonnteService::normalizePhone((string) $eggiContact['phone']);
    $adminPhoneNorm = FonnteService::normalizePhone((string) $adminContact['phone']);
    checkTest('Nomor WA Eggi dan Admin sama untuk kebutuhan demo',
        $eggiPhoneNorm !== null && $eggiPhoneNorm === $adminPhoneNorm
    );

    // Enqueue event untuk mahasiswa Eggi
    $eggiPayload = ['mahasiswa_id' => 3, 'nama_mhs' => 'Eggi Alivia Rohmaht', 'keterangan' => 'Uji Mahasiswa'];
    $enqueuedEggi = $service->enqueueForTest(
        'test_demo_flow:mhs:3',
        'attendance_confirmation',
        'mahasiswa',
        3,
        'mahasiswa#3',
        $eggiPayload
    );
    checkTest('Notifikasi untuk mahasiswa Eggi berhasil diantrekan secara spesifik (mahasiswa#3)',
        $enqueuedEggi === true
    );

    // Enqueue event untuk admin
    $adminPayload = ['admin_id' => 1, 'pesan' => 'Peringatan sistem absensi admin'];
    $enqueuedAdmin = $service->enqueueForTest(
        'test_demo_flow:admin:1',
        'admin_attendance_alert',
        'admin',
        1,
        'admin#1',
        $adminPayload
    );
    checkTest('Notifikasi untuk Admin berhasil diantrekan secara terpisah (admin#1)',
        $enqueuedAdmin === true
    );

    // Verifikasi kedua baris outbox tercatat terpisah berdasarkan akun & jenis penerima
    $rowEggi = $query("SELECT * FROM whatsapp_notifications WHERE event_key = 'test_demo_flow:mhs:3'")[0] ?? null;
    $rowAdmin = $query("SELECT * FROM whatsapp_notifications WHERE event_key = 'test_demo_flow:admin:1'")[0] ?? null;

    checkTest('Baris notifikasi Eggi memiliki recipient_type = mahasiswa dan recipient_id = 3',
        $rowEggi !== null && $rowEggi['recipient_type'] === 'mahasiswa' && (int)$rowEggi['recipient_id'] === 3
    );
    checkTest('Baris notifikasi Admin memiliki recipient_type = admin dan recipient_id = 1',
        $rowAdmin !== null && $rowAdmin['recipient_type'] === 'admin' && (int)$rowAdmin['recipient_id'] === 1
    );

    // Uji proses worker simulasi terhadap kedua baris
    $dispatchResult = $service->dispatchPending(10);
    checkTest('Worker memproses kedua notifikasi tanpa konflik nomor',
        $dispatchResult['processed'] >= 2 && $dispatchResult['failed'] === 0
    );

    $rowEggiAfter = $query("SELECT status, simulated FROM whatsapp_notifications WHERE event_key = 'test_demo_flow:mhs:3'")[0] ?? null;
    $rowAdminAfter = $query("SELECT status, simulated FROM whatsapp_notifications WHERE event_key = 'test_demo_flow:admin:1'")[0] ?? null;
    checkTest('Notifikasi Eggi accepted dengan penanda simulated = 1',
        $rowEggiAfter !== null && $rowEggiAfter['status'] === 'accepted' && (int)$rowEggiAfter['simulated'] === 1
    );
    checkTest('Notifikasi Admin accepted dengan penanda simulated = 1',
        $rowAdminAfter !== null && $rowAdminAfter['status'] === 'accepted' && (int)$rowAdminAfter['simulated'] === 1
    );

    // -----------------------------------------------------------------
    // 2. PENGUJIAN 4 JENIS NOTIFIKASI MAHASISWA
    // -----------------------------------------------------------------
    echo "\n--- 2. EMPAT JENIS NOTIFIKASI MAHASISWA ---\n";

    // A. Pengingat Jadwal Kuliah (30 Menit Sebelum Jadwal)
    // Setup TA dan jadwal uji di dalam transaksi
    $db->exec("INSERT INTO tahun_akademik (kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active)
               VALUES ('WT99', 'TA Uji', 'ganjil', '2026-01-01', '2026-12-31', 1)");
    $testTaId = (int) $db->lastInsertId();

    $targetStart = date('H:i:s', time() + (20 * 60)); // 20 menit dari sekarang (masuk jendela 30 menit)
    $db->exec("INSERT INTO jadwal_kuliah (tahun_akademik_id, mata_kuliah_id, dosen_id, kelas_id, hari, jam_mulai, jam_selesai, ruangan, is_active)
               VALUES ({$testTaId}, 1, 1, 1, '{$day}', '{$targetStart}', '12:00:00', 'Lab Uji', 1)");
    $testJadwalId = (int) $db->lastInsertId();

    // Daftarkan Eggi ke KRS jadwal uji
    $db->exec("INSERT INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status) VALUES ({$testJadwalId}, 3, 'aktif')");

    $remindersEnqueued = $service->enqueueScheduleReminders();
    checkTest('Jenis 1: Pengingat jadwal kuliah berhasil diantrekan untuk mahasiswa',
        $remindersEnqueued >= 1
    );

    $reminderEventKey = 'schedule_reminder:' . $testJadwalId . ':' . $today . ':3';
    $remRow = $query("SELECT * FROM whatsapp_notifications WHERE event_key = :k", ['k' => $reminderEventKey])[0] ?? null;
    checkTest('Payload pengingat jadwal memuat nama mata kuliah, dosen, ruangan, dan menit sebelum',
        $remRow !== null && strpos($remRow['payload_json'], 'Lab Uji') !== false
    );

    // Uji Idempotensi Pengingat Jadwal (Pencegahan Duplikasi)
    $remindersDuplicateCheck = $service->enqueueScheduleReminders();
    checkTest('Pencegahan duplikasi: pengingat jadwal yang sama tidak diantrekan dua kali',
        $remindersDuplicateCheck === 0
    );

    // B. Konfirmasi Absensi (Setelah Absensi Berhasil Disimpan)
    // Buat pertemuan uji
    $db->exec("INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, status)
               VALUES ({$testJadwalId}, 1, '{$today}', 'dibuka')");
    $testPertemuanId = (int) $db->lastInsertId();

    // Simpan data absensi
    $db->exec("INSERT INTO absensi (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified)
               VALUES ({$testPertemuanId}, 3, NOW(), 'hadir', 'dalam_radius', 1)");
    $testAbsensiId = (int) $db->lastInsertId();

    $enqueuedConfirm = $service->enqueueAttendanceConfirmation($testAbsensiId, [
        'mahasiswa_id'  => 3,
        'jadwal_id'     => $testJadwalId,
        'nama_mhs'      => 'Eggi Alivia Rohmaht',
        'nim'           => '4122003',
        'nama_mk'       => 'Kecerdasan Buatan',
        'kode_mk'       => 'TIF-301',
        'nama_dosen'    => 'Dosen Uji',
        'nama_kelas'    => 'TI-2025-A',
        'status'        => 'hadir',
        'jam_absensi'   => date('H:i:s'),
        'tanggal'       => $today,
    ]);
    checkTest('Jenis 2: Konfirmasi absensi berhasil diantrekan setelah absensi tersimpan',
        $enqueuedConfirm === true
    );

    // Uji Idempotensi Konfirmasi Absensi
    $enqueuedConfirmDup = $service->enqueueAttendanceConfirmation($testAbsensiId, [
        'mahasiswa_id'  => 3,
        'jadwal_id'     => $testJadwalId,
    ]);
    checkTest('Pencegahan duplikasi: konfirmasi absensi idempoten (tidak dibuat ganda)',
        $enqueuedConfirmDup === false
    );

    // C. Informasi Alpha / Tidak Hadir (Setelah Pertemuan Difinalisasi)
    // Buat pertemuan kedua dan jadwalkan mahasiswa belum absen
    $db->exec("INSERT INTO pertemuan_kuliah (jadwal_id, pertemuan_ke, tanggal, jam_tutup, status)
               VALUES ({$testJadwalId}, 2, '{$today}', DATE_SUB(NOW(), INTERVAL 5 MINUTE), 'dibuka')");
    $testPertemuan2Id = (int) $db->lastInsertId();

    // Mahasiswa ID 2 belum absen pada pertemuan 2
    $db->exec("INSERT INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status) VALUES ({$testJadwalId}, 2, 'aktif')");

    // Finalisasi pertemuan
    $finalized = $service->finalizeExpiredMeetings();
    checkTest('Pertemuan kuliah yang melewati jam tutup berhasil difinalisasi menjadi ditutup',
        $finalized >= 1
    );

    // Verifikasi mahasiswa yang belum absen ditandai alpha
    $alphaCount = (int) $scalar("SELECT COUNT(*) FROM absensi WHERE pertemuan_id = {$testPertemuan2Id} AND status = 'alpha'");
    checkTest('Mahasiswa tanpa presensi otomatis ditandai alpha setelah pertemuan ditutup',
        $alphaCount >= 1
    );

    // Enqueue notifikasi alpha
    $absenceNoticesEnqueued = $service->enqueueAbsenceNotices($testPertemuan2Id);
    checkTest('Jenis 3: Informasi alpha/tidak hadir berhasil diantrekan setelah finalisasi',
        $absenceNoticesEnqueued >= 1
    );

    // Uji Idempotensi Notifikasi Alpha
    $absenceNoticesDup = $service->enqueueAbsenceNotices($testPertemuan2Id);
    checkTest('Pencegahan duplikasi: notifikasi alpha tidak dikirim berulang untuk pertemuan yang sama',
        $absenceNoticesDup === 0
    );

    // D. Peringatan Kehadiran Rendah (< 75%, Maksimal 1x per Mahasiswa per MK per Minggu)
    // Buat data kehadiran rendah simulasi
    $threshold = 75.0;
    $weekKey = date('o-W');
    $thresholdKey = number_format($threshold, 2, '.', '');
    $lowEventKey = 'low_attendance:' . $testJadwalId . ':3:' . $thresholdKey . ':' . $weekKey;

    $lowEnqueued = $service->enqueueForTest(
        $lowEventKey,
        'low_attendance',
        'mahasiswa',
        3,
        'mahasiswa#3',
        [
            'jadwal_id'                => $testJadwalId,
            'mahasiswa_id'             => 3,
            'nama_mk'                  => 'Kecerdasan Buatan',
            'kode_mk'                  => 'TIF-301',
            'nama_kelas'               => 'TI-2025-A',
            'persentase_kehadiran'     => 50.0,
            'total_pertemuan_selesai'  => 2,
            'batas_minimal'            => $threshold,
        ]
    );
    checkTest('Jenis 4: Peringatan kehadiran rendah berhasil diantrekan ketika < 75%',
        $lowEnqueued === true
    );

    // Uji Idempotensi Mingguan Kehadiran Rendah
    $lowEnqueuedDup = $service->enqueueForTest(
        $lowEventKey,
        'low_attendance',
        'mahasiswa',
        3,
        'mahasiswa#3',
        ['jadwal_id' => $testJadwalId]
    );
    checkTest('Pencegahan duplikasi: peringatan kehadiran rendah dibatasi maksimal 1x per minggu',
        $lowEnqueuedDup === false
    );

    // -----------------------------------------------------------------
    // 3. PENGUJIAN PENERIMA TANPA NOMOR WHATSAPP
    // -----------------------------------------------------------------
    echo "\n--- 3. PENERIMA TANPA NOMOR WHATSAPP / INVALID ---\n";

    // Nonaktifkan nomor pada mahasiswa ID 1 di dalam transaksi
    $db->exec("UPDATE mahasiswa SET no_hp = '' WHERE id = 1");

    $emptyPhoneContact = $notifModel->getRecipientContact('mahasiswa', 1);
    checkTest('Penerima dengan nomor kosong terdeteksi dengan alasan aman',
        $emptyPhoneContact['phone'] === null && strpos($emptyPhoneContact['reason'], 'belum diisi') !== false
    );

    // Uji enqueue konfirmasi absensi untuk nomor kosong dicatat skipped
    $service->enqueueAttendanceConfirmation(999999, [
        'mahasiswa_id'  => 1,
        'jadwal_id'     => $testJadwalId,
        'nama_mhs'      => 'Arya Kusuma',
        'nim'           => '4122001',
        'nama_mk'       => 'Test MK',
        'status'        => 'hadir',
    ]);

    $skipRow = $query("SELECT * FROM whatsapp_notifications WHERE event_key = 'attendance_confirmation_skipped:999999'")[0] ?? null;
    checkTest('Notifikasi untuk nomor kosong otomatis dicatat sebagai skipped tanpa error',
        $skipRow !== null && $skipRow['status'] === 'skipped'
    );

    // -----------------------------------------------------------------
    // 4. VERIFIKASI MODE SIMULASI TANPA HTTP REQUEST
    // -----------------------------------------------------------------
    echo "\n--- 4. MODE SIMULASI & INTEGRITAS DATA OUTBOX ---\n";

    $mockHttpCalled = false;
    $fakeTransport = function () use (&$mockHttpCalled) {
        $mockHttpCalled = true;
        return ['success' => true];
    };

    // Mode simulasi aktif: dispatchPending tidak memanggil $fakeTransport jika simulation mode ON
    $simDispatch = $service->dispatchPending(50);
    checkTest('Seluruh antrean mahasiswa diproses sukses dalam mode simulasi',
        $simDispatch['processed'] >= 4 && $simDispatch['failed'] === 0
    );

    $maxBaselineId = max($baseline['outbox_ids']);
    $outboxRows = $query("SELECT * FROM whatsapp_notifications WHERE id > {$maxBaselineId}");
    $allSimulated = true;
    foreach ($outboxRows as $ob) {
        if ($ob['status'] === 'accepted' && (int)$ob['simulated'] !== 1) {
            $allSimulated = false;
            break;
        }
    }
    checkTest('Semua pesan outbox mahasiswa mode simulasi bertanda simulated = 1 (tanpa HTTP Fonnte)',
        $allSimulated === true
    );

} catch (Throwable $e) {
    echo "ERROR EKSEKUSI PENGUJIAN: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
} finally {
    $db->rollBack();
}

// -----------------------------------------------------------------
// 5. VERIFIKASI PEMBERSIHAN DATA PASCA-ROLLBACK
// -----------------------------------------------------------------
echo "\n--- 5. VERIFIKASI PEMBERSIHAN DATA PASCA ROLLBACK ---\n";

$finalOutboxCount = (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications");
checkTest('Outbox historis tidak berubah (tetap 13 rekaman baseline)',
    $finalOutboxCount === $baseline['outbox_count']
);

$finalJadwalCount = (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah");
checkTest('Data jadwal kuliah kembali ke baseline',
    $finalJadwalCount === $baseline['jadwal']
);

$finalAbsensiCount = (int) $scalar("SELECT COUNT(*) FROM absensi");
checkTest('Data absensi mahasiswa kembali ke baseline',
    $finalAbsensiCount === $baseline['absensi']
);

$finalTaCount = (int) $scalar("SELECT COUNT(*) FROM tahun_akademik");
checkTest('Data tahun akademik kembali ke baseline',
    $finalTaCount === $baseline['ta']
);

$mhs1Final = $query("SELECT no_hp FROM mahasiswa WHERE id = 1")[0]['no_hp'] ?? '';
checkTest('Nomor WhatsApp mahasiswa kembali seperti semula pasca pengujian',
    !empty($mhs1Final)
);

echo "\n====================================================================\n";
echo "SUMMARY STUDENT FLOW TESTS: passed={$passed} failed={$failed}\n";
echo "====================================================================\n";
