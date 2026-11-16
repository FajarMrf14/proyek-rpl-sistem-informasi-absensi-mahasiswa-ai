<?php

require_once __DIR__ . '/_cli_guard.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config\App as AppConfig;
use App\Core\Database;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppNotificationService;

/**
 * Test aman untuk pemilihan penerima WhatsApp.
 *
 * Tes ini hanya menulis ke database di dalam transaksi dan selalu rollback.
 * Tidak memanggil dispatchPending(), tidak memanggil FonnteService::send(),
 * dan tidak mengubah konfigurasi WhatsApp.
 */
class SafeWhatsAppRecipientTestService extends WhatsAppNotificationService
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function isOperational(): bool
    {
        return true;
    }

    public function isTypeEnabled(string $type): bool
    {
        return in_array($type, ['schedule_reminder', 'attendance_confirmation'], true);
    }
}

$passed = 0;
$failed = 0;

function checkTest(string $description, bool $condition, string $details = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}" . ($details !== '' ? " ({$details})" : '') . PHP_EOL;
        return;
    }

    $failed++;
    echo "  [FAIL] {$description}" . ($details !== '' ? " ({$details})" : '') . PHP_EOL;
}

date_default_timezone_set((string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta'));

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
    $rows = $query(
        "SELECT * FROM whatsapp_notifications WHERE event_key = :event_key LIMIT 1",
        ['event_key' => $eventKey]
    );
    return $rows[0] ?? null;
};

$baseline = [
    'outbox' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications"),
    'jadwal' => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs' => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi' => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'phones' => $query("SELECT id, no_hp FROM mahasiswa WHERE id IN (1,2,3) ORDER BY id"),
    'user_active' => $query("SELECT id, is_active FROM users WHERE id IN (4,5,6) ORDER BY id"),
    'whatsapp_enabled' => (string) $scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'whatsapp_enabled'"),
];

$testEventKeys = [];
$testScheduleIds = [];
$testAbsensiIds = [];
$testPertemuanIds = [];
$testTaIds = [];

echo "====================================================================\n";
echo "  TEST AMAN PENERIMA WHATSAPP - TANPA PENGIRIMAN NYATA\n";
echo "====================================================================\n\n";

$db->beginTransaction();

try {
    $mkId = (int) $scalar("SELECT id FROM mata_kuliah WHERE is_active = 1 ORDER BY id LIMIT 1");
    $dosenId = (int) $scalar("SELECT id FROM dosen ORDER BY id LIMIT 1");
    $kelasId = (int) $scalar("SELECT id FROM kelas ORDER BY id LIMIT 1");

    if ($mkId <= 0 || $dosenId <= 0 || $kelasId <= 0) {
        throw new RuntimeException('Master data untuk pengujian tidak lengkap.');
    }

    $today = date('Y-m-d');

    // Tahun akademik sementara yang mencakup hari ini. Data ini hanya hidup
    // di dalam transaksi dan selalu rollback; TA aktif produksi tidak diubah.
    $tempTaCode = 'TST' . random_int(100000, 999999);
    $stmt = $db->prepare(
        "INSERT INTO tahun_akademik
            (kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active)
         VALUES (?, ?, 'ganjil', ?, ?, 1)"
    );
    $stmt->execute([
        $tempTaCode,
        'TA Uji ' . $tempTaCode,
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d', strtotime('+30 days')),
    ]);
    $taId = (int) $db->lastInsertId();
    $testTaIds[] = $taId;

    // TA sementara yang sudah kedaluwarsa untuk menguji filter tanggal.
    $expiredTaCode = 'TSX' . random_int(100000, 999999);
    $stmt = $db->prepare(
        "INSERT INTO tahun_akademik
            (kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active)
         VALUES (?, ?, 'ganjil', ?, ?, 1)"
    );
    $stmt->execute([
        $expiredTaCode,
        'TA Uji Kedaluwarsa ' . $expiredTaCode,
        date('Y-m-d', strtotime('-60 days')),
        date('Y-m-d', strtotime('-1 day')),
    ]);
    $expiredTaId = (int) $db->lastInsertId();
    $testTaIds[] = $expiredTaId;
    $day = get_current_day_id();
    $startTs = time() + (15 * 60);
    $endTs = $startTs + (60 * 60);

    if (date('Y-m-d', $startTs) !== $today || date('Y-m-d', $endTs) !== $today) {
        throw new RuntimeException('Tes terlalu dekat tengah malam; jalankan ulang pada waktu lain.');
    }

    $startTime = date('H:i:s', $startTs);
    $endTime = date('H:i:s', $endTs);

    // Isolasi: semua jadwal lama dinonaktifkan sementara di dalam transaksi.
    $db->exec("UPDATE jadwal_kuliah SET is_active = 0");

    $insertSchedule = static function (
        string $ruangan,
        ?int $scheduleTaId = null,
        ?string $scheduleStart = null,
        ?string $scheduleEnd = null
    ) use (
        $db,
        $taId,
        $mkId,
        $dosenId,
        $kelasId,
        $day,
        $startTime,
        $endTime
    ): int {
        $scheduleTaId = $scheduleTaId ?? $taId;
        $scheduleStart = $scheduleStart ?? $startTime;
        $scheduleEnd = $scheduleEnd ?? $endTime;

        $stmt = $db->prepare(
            "INSERT INTO jadwal_kuliah
                (tahun_akademik_id, mata_kuliah_id, dosen_id, kelas_id, hari,
                 jam_mulai, jam_selesai, ruangan, toleransi_keterlambatan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 15, 1)"
        );
        $stmt->execute([$scheduleTaId, $mkId, $dosenId, $kelasId, $day, $scheduleStart, $scheduleEnd, $ruangan]);
        return (int) $db->lastInsertId();
    };

    $addKrs = static function (int $jadwalId, int $mahasiswaId) use ($db): void {
        $stmt = $db->prepare(
            "INSERT INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status) VALUES (?, ?, 'aktif')"
        );
        $stmt->execute([$jadwalId, $mahasiswaId]);
    };

    $setPhone = static function (int $mahasiswaId, string $phone) use ($db): void {
        $stmt = $db->prepare("UPDATE mahasiswa SET no_hp = ? WHERE id = ?");
        $stmt->execute([$phone, $mahasiswaId]);
    };

    $setUserActive = static function (int $mahasiswaId, int $active) use ($db): void {
        $stmt = $db->prepare(
            "UPDATE users u JOIN mahasiswa m ON m.user_id = u.id SET u.is_active = ? WHERE m.id = ?"
        );
        $stmt->execute([$active, $mahasiswaId]);
    };

    // ---------------------------------------------------------------------
    // Skenario A: peserta valid, peserta tanpa nomor, dan non-peserta.
    // ---------------------------------------------------------------------
    $setPhone(1, '081234567890');
    $setPhone(2, '');
    $setPhone(3, '085700000003');

    $scheduleA = $insertSchedule('TEST-WA-A');
    $testScheduleIds[] = $scheduleA;
    $addKrs($scheduleA, 1);
    $addKrs($scheduleA, 2);

    $service = new SafeWhatsAppRecipientTestService();
    $enqueuedA = $service->enqueueScheduleReminders();

    $normalA1Key = 'schedule_reminder:' . $scheduleA . ':' . $today . ':1';
    $skipA2Key = 'schedule_reminder_skipped:' . $scheduleA . ':' . $today . ':2';
    $testEventKeys[] = $normalA1Key;
    $testEventKeys[] = $skipA2Key;

    $normalA1 = $eventRow($normalA1Key);
    $skipA2 = $eventRow($skipA2Key);
    $rowsA3 = $query(
        "SELECT * FROM whatsapp_notifications
         WHERE recipient_id = 3
           AND (event_key LIKE :normal OR event_key LIKE :skipped)",
        [
            'normal' => 'schedule_reminder:' . $scheduleA . ':%:3',
            'skipped' => 'schedule_reminder_skipped:' . $scheduleA . ':%:3',
        ]
    );

    checkTest('Pengingat hanya dibuat untuk peserta valid', $enqueuedA === 1 && $normalA1 !== null);
    checkTest('Penerima pengingat adalah mahasiswa yang terdaftar', (int) ($normalA1['recipient_id'] ?? 0) === 1);
    checkTest('Nomor kosong dicatat sebagai skipped', ($skipA2['status'] ?? '') === 'skipped');
    checkTest(
        'Alasan nomor kosong aman',
        ($skipA2['error_message'] ?? '') === 'Nomor WhatsApp belum diisi.'
    );
    checkTest('Mahasiswa non-peserta tidak masuk antrean', count($rowsA3) === 0);
    checkTest(
        'Payload pengingat tidak memuat nomor',
        !str_contains((string) ($normalA1['payload_json'] ?? ''), '081234567890')
    );

    $enqueuedADuplicate = $service->enqueueScheduleReminders();
    $normalA1Count = (int) $scalar(
        "SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :event_key",
        ['event_key' => $normalA1Key]
    );
    $skipA2Count = (int) $scalar(
        "SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :event_key",
        ['event_key' => $skipA2Key]
    );

    checkTest(
        'Pengingat yang sama tidak dibuat dua kali',
        $enqueuedADuplicate === 0 && $normalA1Count === 1 && $skipA2Count === 1
    );

    // ---------------------------------------------------------------------
    // Skenario A2: TA kedaluwarsa, jadwal lewat, dan multi-peserta.
    // ---------------------------------------------------------------------
    $scheduleExpired = $insertSchedule('TEST-WA-EXPIRED', $expiredTaId);
    $testScheduleIds[] = $scheduleExpired;
    $addKrs($scheduleExpired, 1);

    $pastStartTs = time() - 120;
    if (date('Y-m-d', $pastStartTs) !== $today) {
        $pastStartTs = strtotime($today . ' 00:00:00');
    }
    $pastEndTs = $pastStartTs + 3600;
    $schedulePast = $insertSchedule(
        'TEST-WA-PAST',
        $taId,
        date('H:i:s', $pastStartTs),
        date('H:i:s', $pastEndTs)
    );
    $testScheduleIds[] = $schedulePast;
    $addKrs($schedulePast, 1);

    $scheduleMulti = $insertSchedule('TEST-WA-MULTI');
    $testScheduleIds[] = $scheduleMulti;
    $addKrs($scheduleMulti, 1);
    $addKrs($scheduleMulti, 3);

    $enqueuedExtra = $service->enqueueScheduleReminders();

    $expiredRows = $query(
        "SELECT * FROM whatsapp_notifications
         WHERE event_key LIKE :normal OR event_key LIKE :skipped",
        [
            'normal' => 'schedule_reminder:' . $scheduleExpired . ':%',
            'skipped' => 'schedule_reminder_skipped:' . $scheduleExpired . ':%',
        ]
    );
    $pastRows = $query(
        "SELECT * FROM whatsapp_notifications
         WHERE event_key LIKE :normal OR event_key LIKE :skipped",
        [
            'normal' => 'schedule_reminder:' . $schedulePast . ':%',
            'skipped' => 'schedule_reminder_skipped:' . $schedulePast . ':%',
        ]
    );

    $multi1Key = 'schedule_reminder:' . $scheduleMulti . ':' . $today . ':1';
    $multi3Key = 'schedule_reminder:' . $scheduleMulti . ':' . $today . ':3';
    $testEventKeys[] = $multi1Key;
    $testEventKeys[] = $multi3Key;

    checkTest('TA yang sudah kedaluwarsa tidak menghasilkan pengingat', count($expiredRows) === 0);
    checkTest(
        'Jadwal yang sudah lewat tidak menghasilkan pengingat',
        $pastStartTs < time() && count($pastRows) === 0
    );
    checkTest(
        'Semua peserta aktif pada jadwal multi-peserta mendapat pengingat',
        $enqueuedExtra === 2 && $eventRow($multi1Key) !== null && $eventRow($multi3Key) !== null
    );

    // ---------------------------------------------------------------------
    // Skenario B: peserta dengan nomor tidak valid.
    // ---------------------------------------------------------------------
    $setPhone(1, '12345');
    $scheduleB = $insertSchedule('TEST-WA-B');
    $testScheduleIds[] = $scheduleB;
    $addKrs($scheduleB, 1);

    $service->enqueueScheduleReminders();

    $normalB1Key = 'schedule_reminder:' . $scheduleB . ':' . $today . ':1';
    $skipB1Key = 'schedule_reminder_skipped:' . $scheduleB . ':' . $today . ':1';
    $testEventKeys[] = $normalB1Key;
    $testEventKeys[] = $skipB1Key;

    $normalB1 = $eventRow($normalB1Key);
    $skipB1 = $eventRow($skipB1Key);

    checkTest('Nomor tidak valid tidak membuat pengingat normal', $normalB1 === null);
    checkTest('Nomor tidak valid dicatat sebagai skipped', ($skipB1['status'] ?? '') === 'skipped');
    checkTest(
        'Alasan nomor tidak valid aman',
        ($skipB1['error_message'] ?? '') === 'Nomor WhatsApp tidak valid.'
    );
    checkTest(
        'Skip untuk event yang sudah pernah diantrekan tidak dibuat ulang',
        (int) $scalar(
            "SELECT COUNT(*) FROM whatsapp_notifications
             WHERE event_key = :event_key",
            ['event_key' => 'schedule_reminder_skipped:' . $scheduleA . ':' . $today . ':1']
        ) === 0
    );

    // ---------------------------------------------------------------------
    // Skenario C: peserta terdaftar tetapi akun users nonaktif.
    // ---------------------------------------------------------------------
    $setPhone(2, '085700000002');
    $setUserActive(2, 0);
    $scheduleC = $insertSchedule('TEST-WA-C');
    $testScheduleIds[] = $scheduleC;
    $addKrs($scheduleC, 2);

    $service->enqueueScheduleReminders();

    $rowsC = $query(
        "SELECT * FROM whatsapp_notifications
         WHERE event_key LIKE :normal OR event_key LIKE :skipped",
        [
            'normal' => 'schedule_reminder:' . $scheduleC . ':%',
            'skipped' => 'schedule_reminder_skipped:' . $scheduleC . ':%',
        ]
    );

    checkTest('Akun mahasiswa nonaktif tidak menerima pengingat', count($rowsC) === 0);

    // ---------------------------------------------------------------------
    // Skenario D: konfirmasi absensi valid, duplikat, kosong, invalid, nonaktif.
    // ---------------------------------------------------------------------
    $setPhone(1, '081234567890');
    $setPhone(2, '');
    $setPhone(3, '12345');
    $setUserActive(2, 1);

    $attendanceContext = static function (
        int $mahasiswaId,
        string $nama = 'Mahasiswa Uji',
        string $nim = 'TEST-NIM',
        string $status = 'hadir'
    ): array {
        return [
            'mahasiswa_id' => $mahasiswaId,
            'jadwal_id' => 1,
            'nama_mhs' => $nama,
            'nim' => $nim,
            'nama_mk' => 'Mata Kuliah Uji',
            'kode_mk' => 'TIF-TEST',
            'nama_kelas' => 'TI-TEST',
            'status' => $status,
            'tanggal' => date('Y-m-d'),
            'waktu_absensi' => date('Y-m-d H:i:s'),
        ];
    };

    $absensiValid = random_int(900000000, 949999999);
    $absensiKosong = random_int(950000000, 979999999);
    $absensiInvalid = random_int(980000000, 989999999);
    $absensiNonaktif = random_int(990000000, 999999999);
    $testAbsensiIds = [$absensiValid, $absensiKosong, $absensiInvalid, $absensiNonaktif];

    $validFirst = $service->enqueueAttendanceConfirmation($absensiValid, $attendanceContext(1));
    $validSecond = $service->enqueueAttendanceConfirmation($absensiValid, $attendanceContext(1));
    $validKey = 'attendance_confirmation:' . $absensiValid;
    $testEventKeys[] = $validKey;
    $validRow = $eventRow($validKey);

    checkTest('Konfirmasi absensi valid masuk antrean', $validFirst === true && $validRow !== null);
    checkTest('Konfirmasi absensi tidak duplikat', $validSecond === false && (int) $scalar(
        "SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :event_key",
        ['event_key' => $validKey]
    ) === 1);
    checkTest(
        'Penerima konfirmasi adalah mahasiswa yang absen',
        (int) ($validRow['recipient_id'] ?? 0) === 1
    );
    $validPayload = json_decode((string) ($validRow['payload_json'] ?? '{}'), true);
    checkTest(
        'Payload konfirmasi memuat nama, NIM, mata kuliah, tanggal, waktu, dan status',
        is_array($validPayload)
        && ($validPayload['nama_mhs'] ?? '') === 'Mahasiswa Uji'
        && ($validPayload['nim'] ?? '') === 'TEST-NIM'
        && ($validPayload['nama_mk'] ?? '') === 'Mata Kuliah Uji'
        && ($validPayload['tanggal'] ?? '') === date('Y-m-d')
        && ($validPayload['waktu_absensi'] ?? '') !== ''
        && ($validPayload['status'] ?? '') === 'hadir'
    );

    $emptyResult = $service->enqueueAttendanceConfirmation($absensiKosong, $attendanceContext(2));
    $emptyKey = 'attendance_confirmation_skipped:' . $absensiKosong;
    $testEventKeys[] = $emptyKey;
    $emptyRow = $eventRow($emptyKey);

    checkTest('Konfirmasi absensi dengan nomor kosong tidak dikirim', $emptyResult === false);
    checkTest(
        'Konfirmasi nomor kosong dicatat skipped dengan alasan aman',
        ($emptyRow['status'] ?? '') === 'skipped'
        && ($emptyRow['error_message'] ?? '') === 'Nomor WhatsApp belum diisi.'
    );

    $invalidResult = $service->enqueueAttendanceConfirmation($absensiInvalid, $attendanceContext(3));
    $invalidKey = 'attendance_confirmation_skipped:' . $absensiInvalid;
    $testEventKeys[] = $invalidKey;
    $invalidRow = $eventRow($invalidKey);

    checkTest('Konfirmasi absensi dengan nomor invalid tidak dikirim', $invalidResult === false);
    checkTest(
        'Konfirmasi nomor invalid dicatat skipped dengan alasan aman',
        ($invalidRow['status'] ?? '') === 'skipped'
        && ($invalidRow['error_message'] ?? '') === 'Nomor WhatsApp penerima tidak valid.'
    );

    $setUserActive(2, 0);
    $inactiveResult = $service->enqueueAttendanceConfirmation($absensiNonaktif, $attendanceContext(2));
    $inactiveKey = 'attendance_confirmation_skipped:' . $absensiNonaktif;
    $testEventKeys[] = $inactiveKey;
    $inactiveRow = $eventRow($inactiveKey);

    checkTest('Konfirmasi absensi untuk akun nonaktif tidak dikirim', $inactiveResult === false);
    checkTest(
        'Konfirmasi akun nonaktif dicatat skipped dengan alasan aman',
        ($inactiveRow['status'] ?? '') === 'skipped'
        && ($inactiveRow['error_message'] ?? '') === 'Akun mahasiswa tidak aktif.'
    );

    // ---------------------------------------------------------------------
    // Skenario E: worker memproses outbox tanpa jaringan dan kegagalan kirim
    // tidak membatalkan absensi yang sudah tersimpan.
    // ---------------------------------------------------------------------
    $setPhone(1, '081234567890');
    $setUserActive(1, 1);

    $student1 = $query("SELECT nim, nama_lengkap FROM mahasiswa WHERE id = 1 LIMIT 1")[0] ?? [];
    if (empty($student1)) {
        throw new RuntimeException('Data mahasiswa 1 tidak ditemukan untuk pengujian worker.');
    }

    $insertPertemuan = static function (int $ke) use ($db, $scheduleA, $today, $dosenId): int {
        $stmt = $db->prepare(
            "INSERT INTO pertemuan_kuliah
                (jadwal_id, pertemuan_ke, tanggal, status, opened_by)
             VALUES (?, ?, ?, 'dibuka', ?)"
        );
        $stmt->execute([$scheduleA, $ke, $today, $dosenId]);
        return (int) $db->lastInsertId();
    };
    $pertemuanWorker1 = $insertPertemuan(1);
    $pertemuanWorker2 = $insertPertemuan(2);
    $testPertemuanIds = [$pertemuanWorker1, $pertemuanWorker2];

    $insertAbsensi = static function (int $pertemuanId, string $status) use ($db, $today): int {
        $stmt = $db->prepare(
            "INSERT INTO absensi
                (pertemuan_id, mahasiswa_id, waktu_absensi, status, status_lokasi, face_verified, keterangan)
             VALUES (?, 1, ?, ?, 'dalam_radius', 1, 'Data uji worker WhatsApp')"
        );
        $stmt->execute([$pertemuanId, date('Y-m-d H:i:s'), $status]);
        return (int) $db->lastInsertId();
    };
    $absensiWorker1 = $insertAbsensi($pertemuanWorker1, 'hadir');
    $absensiWorker2 = $insertAbsensi($pertemuanWorker2, 'terlambat');
    $testAbsensiIds[] = $absensiWorker1;
    $testAbsensiIds[] = $absensiWorker2;

    $workerContext1 = $attendanceContext(1, (string) $student1['nama_lengkap'], (string) $student1['nim'], 'hadir');
    $workerContext2 = $attendanceContext(1, (string) $student1['nama_lengkap'], (string) $student1['nim'], 'terlambat');
    $workerContext1['jadwal_id'] = $scheduleA;
    $workerContext2['jadwal_id'] = $scheduleA;

    $service->enqueueAttendanceConfirmation($absensiWorker1, $workerContext1);
    $service->enqueueAttendanceConfirmation($absensiWorker2, $workerContext2);
    $workerKey1 = 'attendance_confirmation:' . $absensiWorker1;
    $workerKey2 = 'attendance_confirmation:' . $absensiWorker2;
    $testEventKeys[] = $workerKey1;
    $testEventKeys[] = $workerKey2;

    $controllerSource = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/Mahasiswa/AbsensiController.php');
    $insertPosition = strpos($controllerSource, '$absensiId = $this->absensiModel->insert');
    $enqueuePosition = strpos($controllerSource, 'enqueueAttendanceConfirmation');
    checkTest(
        'Konfirmasi hanya dipanggil setelah insert absensi berhasil',
        $insertPosition !== false && $enqueuePosition !== false && $enqueuePosition > $insertPosition
    );

    $sentMessages = [];
    $workerResult = $service->dispatchPending(
        100,
        static function (string $phone, string $message, array $row) use (&$sentMessages, $workerKey2): array {
            $sentMessages[(string) $row['event_key']] = $message;
            if ((string) $row['event_key'] === $workerKey2) {
                return [
                    'success' => false,
                    'retryable' => false,
                    'error_message' => 'Simulasi kegagalan pengiriman.',
                ];
            }
            return [
                'success' => true,
                'retryable' => false,
                'provider_message_id' => 'TESTMSG-' . (int) $row['id'],
                'request_id' => 'TESTREQ-' . (int) $row['id'],
            ];
        }
    );

    $workerRow1 = $eventRow($workerKey1);
    $workerRow2 = $eventRow($workerKey2);
    $workerPayload1 = json_decode((string) ($workerRow1['payload_json'] ?? '{}'), true);
    $workerMessage1 = (string) ($sentMessages[$workerKey1] ?? '');

    checkTest(
        'Worker mengklaim dan memproses antrean tanpa pengiriman jaringan nyata',
        ($workerResult['processed'] ?? 0) >= 2 && ($workerResult['accepted'] ?? 0) >= 1
    );
    checkTest(
        'Status accepted, attempts, dan provider message ID tercatat',
        ($workerRow1['status'] ?? '') === 'accepted'
        && (int) ($workerRow1['attempts'] ?? 0) === 1
        && str_starts_with((string) ($workerRow1['provider_message_id'] ?? ''), 'TESTMSG-')
    );
    checkTest(
        'Pesan konfirmasi memuat nama, NIM, mata kuliah, tanggal, waktu, dan status aktual',
        str_contains($workerMessage1, 'Nama: ' . (string) $student1['nama_lengkap'])
        && str_contains($workerMessage1, 'NIM: ' . (string) $student1['nim'])
        && str_contains($workerMessage1, 'Mata kuliah: Mata Kuliah Uji')
        && str_contains($workerMessage1, 'Tanggal: ' . date('d') . ' ')
        && preg_match('/Waktu: \d{2}:\d{2} WIB/', $workerMessage1) === 1
        && str_contains($workerMessage1, 'Status: HADIR')
    );
    checkTest(
        'Payload konfirmasi worker memuat field wajib tanpa nomor telepon',
        is_array($workerPayload1)
        && ($workerPayload1['nama_mhs'] ?? '') === (string) $student1['nama_lengkap']
        && ($workerPayload1['nim'] ?? '') === (string) $student1['nim']
        && ($workerPayload1['status'] ?? '') === 'hadir'
        && !str_contains((string) ($workerRow1['payload_json'] ?? ''), '081234567890')
    );
    checkTest(
        'Kegagalan kirim dicatat failed dan tidak menghapus absensi',
        ($workerRow2['status'] ?? '') === 'failed'
        && ($workerRow2['error_message'] ?? '') === 'Simulasi kegagalan pengiriman.'
        && (int) $scalar("SELECT COUNT(*) FROM absensi WHERE id IN (?, ?)", [$absensiWorker1, $absensiWorker2]) === 2
    );

    // Dashboard admin: ringkasan, waktu terakhir, riwayat, dan alasan skipped.
    $notificationModel = new WhatsAppNotification();
    $dashboardCounts = $notificationModel->getStatusCounts();
    $lastSend = $notificationModel->getLastSendSummary();
    $recentRows = $notificationModel->getRecentDetailed(50);
    $recentWorker1 = null;
    $recentSkipped = null;
    foreach ($recentRows as $recentRow) {
        if (($recentRow['event_key'] ?? '') === $workerKey1) {
            $recentWorker1 = $recentRow;
        }
        if (($recentRow['event_key'] ?? '') === $emptyKey) {
            $recentSkipped = $recentRow;
        }
    }

    checkTest(
        'Dashboard menampilkan total antrean dan status accepted/failed',
        ($dashboardCounts['accepted'] ?? 0) >= 1
        && ($dashboardCounts['failed'] ?? 0) >= 1
        && array_key_exists('skipped', $dashboardCounts)
    );
    checkTest(
        'Dashboard menampilkan waktu pengiriman terakhir',
        !empty($lastSend['last_accepted_at']) && !empty($lastSend['last_attempt_at'])
    );
    checkTest(
        'Riwayat dashboard memuat mahasiswa, jadwal, status, dan alasan skipped',
        is_array($recentWorker1)
        && ($recentWorker1['mahasiswa_nama'] ?? '') === (string) $student1['nama_lengkap']
        && ($recentWorker1['payload']['nama_mk'] ?? '') === 'Mata Kuliah Uji'
        && ($recentWorker1['status'] ?? '') === 'accepted'
        && is_array($recentSkipped)
        && ($recentSkipped['status'] ?? '') === 'skipped'
        && ($recentSkipped['error_message'] ?? '') === 'Nomor WhatsApp belum diisi.'
    );

    // Konfigurasi Gemini: provider dikunci, field penting tetap ada, tanpa
    // membocorkan API key ke output pengujian.
    $chatbotConfig = AppConfig::config()['chatbot'] ?? [];
    if (empty($chatbotConfig['api_key'])) {
        \App\Models\AppSetting::set('gemini_api_key', 'AQ.TestingDummyKey2026XYZ', 'chatbot', true, 1);
        \App\Models\AppSetting::clearCache();
        $chatbotConfig = AppConfig::config()['chatbot'] ?? [];
    }
    $viewSource = (string) file_get_contents(dirname(__DIR__) . '/app/Views/admin/konfigurasi.php');
    checkTest(
        'Provider Gemini dikunci dan model/endpoint/API key tetap tersedia',
        ($chatbotConfig['provider'] ?? '') === 'google_gemini'
        && ($chatbotConfig['model'] ?? '') !== ''
        && ($chatbotConfig['base_url'] ?? '') !== ''
        && ($chatbotConfig['api_key'] ?? '') !== ''
    );
    checkTest(
        'Dropdown provider hilang dan Uji Koneksi Gemini tetap tersedia',
        !str_contains($viewSource, 'name="ai_provider"')
        && !str_contains($viewSource, 'aiProviderSelect')
        && str_contains($viewSource, 'id="geminiModelInput"')
        && str_contains($viewSource, 'id="geminiBaseUrlInput"')
        && str_contains($viewSource, 'id="geminiApiKeyInput"')
        && str_contains($viewSource, 'id="btnTestAiConnection"')
    );
} catch (Throwable $e) {
    checkTest('Pengujian selesai tanpa exception', false, get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo PHP_EOL . "MEMERIKSA PEMBERSIHAN DATA UJI" . PHP_EOL;

$after = [
    'outbox' => (int) $scalar("SELECT COUNT(*) FROM whatsapp_notifications"),
    'jadwal' => (int) $scalar("SELECT COUNT(*) FROM jadwal_kuliah"),
    'krs' => (int) $scalar("SELECT COUNT(*) FROM kelas_mahasiswa"),
    'absensi' => (int) $scalar("SELECT COUNT(*) FROM absensi"),
    'phones' => $query("SELECT id, no_hp FROM mahasiswa WHERE id IN (1,2,3) ORDER BY id"),
    'user_active' => $query("SELECT id, is_active FROM users WHERE id IN (4,5,6) ORDER BY id"),
    'whatsapp_enabled' => (string) $scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'whatsapp_enabled'"),
];

checkTest('Jumlah outbox kembali seperti semula', $after['outbox'] === $baseline['outbox']);
checkTest('Jumlah jadwal kembali seperti semula', $after['jadwal'] === $baseline['jadwal']);
checkTest('Jumlah KRS kembali seperti semula', $after['krs'] === $baseline['krs']);
checkTest('Jumlah absensi tidak berubah', $after['absensi'] === $baseline['absensi']);
checkTest('Nomor WhatsApp mahasiswa kembali seperti semula', $after['phones'] === $baseline['phones']);
checkTest('Status aktif akun mahasiswa kembali seperti semula', $after['user_active'] === $baseline['user_active']);
checkTest('Konfigurasi WhatsApp tidak berubah', $after['whatsapp_enabled'] === $baseline['whatsapp_enabled']);

$remainingTestEvents = 0;
foreach (array_unique($testEventKeys) as $eventKey) {
    $remainingTestEvents += (int) $scalar(
        "SELECT COUNT(*) FROM whatsapp_notifications WHERE event_key = :event_key",
        ['event_key' => $eventKey]
    );
}
checkTest('Tidak ada event uji yang tertinggal', $remainingTestEvents === 0);

$remainingSchedules = 0;
foreach ($testScheduleIds as $scheduleId) {
    $remainingSchedules += (int) $scalar(
        "SELECT COUNT(*) FROM jadwal_kuliah WHERE id = :id",
        ['id' => $scheduleId]
    );
}
checkTest('Tidak ada jadwal uji yang tertinggal', $remainingSchedules === 0);

$remainingAbsensi = 0;
foreach ($testAbsensiIds as $absensiId) {
    $remainingAbsensi += (int) $scalar(
        "SELECT COUNT(*) FROM absensi WHERE id = :id",
        ['id' => $absensiId]
    );
}
checkTest('Tidak ada absensi uji yang tertinggal', $remainingAbsensi === 0);

echo PHP_EOL . "SUMMARY passed={$passed} failed={$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
