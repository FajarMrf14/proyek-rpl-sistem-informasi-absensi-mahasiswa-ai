<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Deterministic Chatbot Database Layer
 * ==============================================================================
 *
 * Pengujian ini memakai data aktual dari database. Assertion dibuat dinamis
 * agar tetap valid ketika data absensi berubah.
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Services\ChatbotDatabaseService;
use App\Services\NvidiaChatbotService;

echo "====================================================================\n";
echo "  TEST SUITE: CHATBOT DATABASE LAYER (PERIODE, INTENT, ROLE, KONTEKS)\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertDbCheck(string $description, bool $condition, string $details = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}" . ($details !== '' ? " ({$details})" : '') . "\n";
    }
}

function dbAsk(ChatbotDatabaseService $service, string $query, array $user, array $context = []): ?array
{
    return $service->resolve($query, $user, $context);
}

function dbReply(?array $result): string
{
    return is_array($result) ? (string) ($result['reply'] ?? '') : '';
}

function dbSource(?array $result): string
{
    return is_array($result) ? (string) ($result['source'] ?? '') : '';
}

function dbContainsAnyName(string $text, array $students): bool
{
    foreach ($students as $student) {
        if ($student['nama_lengkap'] !== '' && str_contains($text, (string) $student['nama_lengkap'])) {
            return true;
        }
    }
    return false;
}

function dbContainsAllStudents(string $text, array $students): bool
{
    if ($students === []) {
        return false;
    }
    foreach ($students as $student) {
        if (!str_contains($text, (string) $student['nama_lengkap'])
            || !str_contains($text, (string) $student['nim'])
        ) {
            return false;
        }
    }
    return true;
}

function dbFindUserForDosen(User $userModel, array $lecturers, int $dosenId): ?array
{
    foreach ($lecturers as $lecturer) {
        if ((int) $lecturer['id'] === $dosenId) {
            $profile = $userModel->getFullProfile((int) $lecturer['user_id']);
            return is_array($profile) ? $profile : null;
        }
    }
    return null;
}

$db = Database::getConnection();
$userModel = new User();
$dbService = new ChatbotDatabaseService();
$chatService = new NvidiaChatbotService();

$adminUser = $userModel->getFullProfile(1);
$dosenUsers = [];
foreach ([2, 3] as $userId) {
    $profile = $userModel->getFullProfile($userId);
    if (is_array($profile)) {
        $dosenUsers[] = $profile;
    }
}
$mahasiswaUsers = [];
foreach ([4, 5, 6] as $userId) {
    $profile = $userModel->getFullProfile($userId);
    if (is_array($profile)) {
        $mahasiswaUsers[] = $profile;
    }
}

$students = $db->query("SELECT * FROM mahasiswa ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$lecturers = $db->query("SELECT * FROM dosen ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$classes = $db->query("SELECT * FROM kelas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$courses = $db->query("SELECT * FROM mata_kuliah ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$activeTa = $db->query("SELECT * FROM tahun_akademik WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$activeTa) {
    $activeTa = $db->query("SELECT * FROM tahun_akademik ORDER BY tanggal_mulai DESC, id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

$timezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $timezone);
$today = $now->format('Y-m-d');
$yesterday = $now->modify('-1 day')->format('Y-m-d');
$monthStart = $now->modify('first day of this month')->format('Y-m-d');
$monthEnd = $now->modify('last day of this month')->format('Y-m-d');

$lateRows = $db->query(
    "SELECT a.id, a.status, a.waktu_absensi, a.mahasiswa_id, pk.tanggal, pk.pertemuan_ke,
            jk.id AS jadwal_id, jk.dosen_id, m.nama_lengkap, m.nim, mk.nama_mk, k.nama_kelas
     FROM absensi a
     JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
     JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
     JOIN mahasiswa m ON m.id = a.mahasiswa_id
     JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
     JOIN kelas k ON k.id = jk.kelas_id
     WHERE a.status = 'terlambat'
     ORDER BY a.waktu_absensi DESC, a.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);
$lateRow = $lateRows[0] ?? null;

$lateDosenUser = null;
$otherDosenUser = null;
if ($lateRow !== null) {
    $lateDosenUser = dbFindUserForDosen($userModel, $lecturers, (int) $lateRow['dosen_id']);
    foreach ($lecturers as $lecturer) {
        if ((int) $lecturer['id'] !== (int) $lateRow['dosen_id']) {
            $otherDosenUser = $userModel->getFullProfile((int) $lecturer['user_id']);
            break;
        }
    }
}

$firstStudent = $students[0] ?? null;
$secondStudent = $students[1] ?? null;
$firstClass = $classes[0] ?? null;
$firstCourse = $courses[0] ?? null;
$firstLecturer = $lecturers[0] ?? null;

// -----------------------------------------------------------------------------
// 1. PERIODE, TIMEZONE, DAN ISOLASI TAHUN AKADEMIK
// -----------------------------------------------------------------------------
echo "--- 1. PERIODE, TIMEZONE & ISOLASI TAHUN AKADEMIK ---\n";

$res = dbAsk($dbService, 'Siapa yang terlambat hari ini?', $adminUser);
assertDbCheck(
    'Periode "hari ini" memakai Asia/Jakarta dan rentang tanggal hari ini',
    dbSource($res) === 'app_database'
    && ($res['context']['period']['type'] ?? '') === 'date'
    && ($res['context']['period']['start'] ?? '') === $today
    && ($res['context']['period']['end'] ?? '') === $today,
    'today=' . $today
);

$res = dbAsk($dbService, 'Siapa yang terlambat kemarin?', $adminUser);
assertDbCheck(
    'Periode "kemarin" menghasilkan tanggal kemarin',
    ($res['context']['period']['start'] ?? '') === $yesterday
    && ($res['context']['period']['end'] ?? '') === $yesterday
);

$res = dbAsk($dbService, 'Siapa yang terlambat bulan ini?', $adminUser);
assertDbCheck(
    'Periode "bulan ini" menghasilkan awal dan akhir bulan berjalan',
    ($res['context']['period']['type'] ?? '') === 'date'
    && ($res['context']['period']['start'] ?? '') === $monthStart
    && ($res['context']['period']['end'] ?? '') === $monthEnd
);

$taSeptember2025 = $db->query(
    "SELECT id FROM tahun_akademik
     WHERE tanggal_mulai <= '2025-09-30' AND tanggal_selesai >= '2025-09-01'
     ORDER BY id ASC LIMIT 1"
)->fetchColumn();

$res = dbAsk($dbService, 'Siapa yang terlambat September 2025?', $adminUser);
assertDbCheck(
    'Bulan spesifik September 2025 memakai rentang tanggal yang benar',
    ($res['context']['period']['start'] ?? '') === '2025-09-01'
    && ($res['context']['period']['end'] ?? '') === '2025-09-30'
);

assertDbCheck(
    'Bulan spesifik terikat ke tahun akademik yang mencakup periode tersebut',
    $taSeptember2025 !== false
    && (int) ($res['context']['period']['ta_id'] ?? 0) === (int) $taSeptember2025
);

if ($activeTa !== null) {
    $res = dbAsk($dbService, 'Siapa yang terlambat semester ini?', $adminUser);
    $expired = (string) $activeTa['tanggal_selesai'] < $today;
    assertDbCheck(
        'Semester ini memakai tahun akademik aktif dan menandai TA yang sudah berakhir',
        ($res['context']['period']['type'] ?? '') === 'ta'
        && (int) ($res['context']['period']['ta_id'] ?? 0) === (int) $activeTa['id']
        && (!$expired || str_contains(dbReply($res), 'sudah berakhir')),
        'active_ta=' . $activeTa['nama_tahun']
    );

    $res = dbAsk($dbService, 'Siapa yang terlambat ' . $activeTa['nama_tahun'] . '?', $adminUser);
    assertDbCheck(
        'Tahun akademik spesifik memakai filter TA, bukan mencampur semua data',
        ($res['context']['period']['type'] ?? '') === 'ta'
        && (int) ($res['context']['period']['ta_id'] ?? 0) === (int) $activeTa['id']
    );
}

$res = dbAsk($dbService, 'Siapa yang terlambat seluruh riwayat?', $adminUser);
assertDbCheck(
    'Periode "seluruh riwayat" memakai tipe all tanpa filter TA',
    ($res['context']['period']['type'] ?? '') === 'all'
);

// Isolasi: record terlambat 31 Oktober 2026 tidak boleh muncul di September 2025.
$res = dbAsk($dbService, 'Siapa yang terlambat September 2025?', $adminUser);
assertDbCheck(
    'Data di luar periode tidak bocor ke jawaban September 2025',
    !str_contains(dbReply($res), '31 Oktober 2026')
);

$res = dbAsk($dbService, 'Siapa yang terlambat September 2026?', $adminUser);
assertDbCheck(
    'Periode tanpa data menghasilkan jawaban kosong, bukan data periode lain',
    str_contains(dbReply($res), 'Tidak ditemukan')
);

// -----------------------------------------------------------------------------
// 2. INTENT PERTANYAAN ADMIN
// -----------------------------------------------------------------------------
echo "\n--- 2. INTENT PERTANYAAN ADMIN ---\n";

$lateSeptemberNames = $db->query(
    "SELECT DISTINCT m.nama_lengkap
     FROM absensi a
     JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
     JOIN mahasiswa m ON m.id = a.mahasiswa_id
     WHERE a.status = 'terlambat' AND pk.tanggal BETWEEN '2025-09-01' AND '2025-09-30'
     ORDER BY m.nama_lengkap ASC"
)->fetchAll(PDO::FETCH_COLUMN);

$res = dbAsk($dbService, 'Siapa yang terlambat September 2025?', $adminUser);
assertDbCheck(
    'Intent daftar terlambat mengambil nama dari database',
    dbSource($res) === 'app_database'
    && ($lateSeptemberNames === []
        ? str_contains(dbReply($res), 'Tidak ditemukan')
        : str_contains(dbReply($res), (string) $lateSeptemberNames[0]))
);

$lateSeptemberCount = (int) $db->query(
    "SELECT COUNT(DISTINCT a.mahasiswa_id)
     FROM absensi a
     JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
     WHERE a.status = 'terlambat' AND pk.tanggal BETWEEN '2025-09-01' AND '2025-09-30'"
)->fetchColumn();

$res = dbAsk($dbService, 'Berapa mahasiswa yang terlambat September 2025?', $adminUser);
assertDbCheck(
    'Intent hitung terlambat mengembalikan jumlah mahasiswa yang benar',
    $lateSeptemberCount > 0
        ? str_contains(dbReply($res), (string) $lateSeptemberCount)
        : str_contains(dbReply($res), 'Tidak ditemukan')
);

$res = dbAsk($dbService, 'Siapa yang paling sering terlambat September 2025?', $adminUser);
assertDbCheck(
    'Intent ranking terlambat menghasilkan peringkat',
    str_contains(mb_strtolower(dbReply($res)), 'peringkat')
    && ($lateSeptemberNames === [] || str_contains(dbReply($res), (string) $lateSeptemberNames[0]))
);

$alphaSeptemberCount = (int) $db->query(
    "SELECT COUNT(*)
     FROM absensi a
     JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
     WHERE a.status = 'alpha' AND pk.tanggal BETWEEN '2025-09-01' AND '2025-09-30'"
)->fetchColumn();

$res = dbAsk($dbService, 'Siapa yang tidak hadir September 2025?', $adminUser);
assertDbCheck(
    'Intent daftar tidak hadir konsisten dengan data alpha',
    $alphaSeptemberCount > 0
        ? dbSource($res) === 'app_database' && dbContainsAnyName(dbReply($res), $students)
        : str_contains(dbReply($res), 'Tidak ditemukan')
);

$hadirSeptemberCount = (int) $db->query(
    "SELECT COUNT(*)
     FROM absensi a
     JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
     WHERE a.status IN ('hadir', 'terlambat') AND pk.tanggal BETWEEN '2025-09-01' AND '2025-09-30'"
)->fetchColumn();

$res = dbAsk($dbService, 'Berapa jumlah mahasiswa yang hadir September 2025?', $adminUser);
assertDbCheck(
    'Intent hitung kehadiran tidak salah dikenali sebagai hitung entitas',
    $hadirSeptemberCount > 0
        ? str_contains(dbReply($res), (string) $hadirSeptemberCount)
        : str_contains(dbReply($res), 'Tidak ditemukan')
);

$res = dbAsk($dbService, 'Siapa mahasiswa dengan kehadiran di bawah 75% semester ini?', $adminUser);
assertDbCheck(
    'Intent kehadiran rendah menyertakan dasar perhitungan dan aturan UAS',
    str_contains(dbReply($res), 'Dasar Perhitungan Kehadiran')
    && str_contains(dbReply($res), 'UAS')
    && preg_match('/\d+\.\d{2}%/', dbReply($res)) === 1
);

$res = dbAsk($dbService, 'Siapa mahasiswa dengan kehadiran terbaik semester ini?', $adminUser);
assertDbCheck(
    'Intent kehadiran terbaik menghasilkan nama mahasiswa aktual',
    str_contains(mb_strtolower(dbReply($res)), 'terbaik')
    && dbContainsAnyName(dbReply($res), $students)
);

$izinTaCount = 0;
if ($activeTa !== null) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM izin_sakit iz
         JOIN jadwal_kuliah jk ON jk.id = iz.jadwal_id
         WHERE jk.tahun_akademik_id = :ta_id"
    );
    $stmt->execute(['ta_id' => (int) $activeTa['id']]);
    $izinTaCount = (int) $stmt->fetchColumn();
}
$res = dbAsk($dbService, 'Berapa total izin dan sakit semester ini?', $adminUser);
assertDbCheck(
    'Intent rekap izin/sakit menampilkan angka dari database',
    str_contains(mb_strtolower(dbReply($res)), 'izin')
    && str_contains(mb_strtolower(dbReply($res)), 'sakit')
    && str_contains(dbReply($res), (string) $izinTaCount)
);

$res = dbAsk($dbService, 'Mata kuliah apa yang paling banyak ketidakhadirannya semester ini?', $adminUser);
assertDbCheck(
    'Intent mata kuliah paling banyak alpha berjalan tanpa error',
    dbSource($res) === 'app_database'
    && (str_contains(dbReply($res), 'Tidak ditemukan') || dbContainsAnyName(dbReply($res), $students))
);

if ($firstStudent !== null) {
    $res = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $firstStudent['nama_lengkap'], $adminUser);
    assertDbCheck(
        'Intent riwayat mahasiswa menampilkan nama dan NIM dari database',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) $firstStudent['nama_lengkap'])
        && str_contains(dbReply($res), (string) $firstStudent['nim'])
    );
}

$res = dbAsk($dbService, 'Bagaimana rekap kehadiran semester ini?', $adminUser);
assertDbCheck(
    'Intent rekap semester menampilkan mahasiswa aktual',
    str_contains(mb_strtolower(dbReply($res)), 'rekap kehadiran')
    && dbContainsAnyName(dbReply($res), $students)
);

$res = dbAsk($dbService, 'Bandingkan kehadiran bulan ini dengan bulan lalu', $adminUser);
assertDbCheck(
    'Intent perbandingan menampilkan kedua periode',
    dbSource($res) === 'app_database'
    && str_contains(dbReply($res), 'Perbandingan presensi')
    && str_contains(dbReply($res), 'bulan ini')
    && str_contains(dbReply($res), 'bulan lalu')
);

$res = dbAsk($dbService, 'Apa saja jadwal kuliah semester ini?', $adminUser);
assertDbCheck(
    'Intent jadwal menampilkan mata kuliah dan dosen dari database',
    dbSource($res) === 'app_database'
    && str_contains(mb_strtolower(dbReply($res)), 'jadwal kuliah')
    && ($firstCourse === null || str_contains(dbReply($res), (string) $firstCourse['nama_mk']))
    && ($firstLecturer === null || str_contains(dbReply($res), (string) $firstLecturer['nama_lengkap']))
);

if ($firstClass !== null) {
    $res = dbAsk($dbService, 'Siapa dosen yang mengajar kelas ' . $firstClass['nama_kelas'] . '?', $adminUser);
    assertDbCheck(
        'Intent dosen pengampu kelas menjawab langsung dari relasi jadwal',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), 'Dosen pengampu')
        && ($firstLecturer === null || str_contains(dbReply($res), (string) $firstLecturer['nama_lengkap']))
    );
}

$res = dbAsk($dbService, 'Berapa persentase kehadiran minimal untuk ikut UAS?', $adminUser);
assertDbCheck(
    'Aturan UAS memakai sumber app_knowledge dan nilai 75.00%',
    dbSource($res) === 'app_knowledge'
    && str_contains(dbReply($res), '75.00%')
);

$res = dbAsk($dbService, 'Tampilkan data September 2026', $adminUser);
assertDbCheck(
    'Query data umum dengan periode dijawab sebagai period_summary',
    dbSource($res) === 'app_database'
    && ($res['context']['intent'] ?? '') === 'period_summary'
    && str_contains(dbReply($res), 'Data presensi')
);

if ($firstStudent !== null) {
    $res = dbAsk($dbService, 'Profil mahasiswa ' . $firstStudent['nama_lengkap'], $adminUser);
    assertDbCheck(
        'Intent profil mahasiswa menampilkan nama dan NIM aktual',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) $firstStudent['nama_lengkap'])
        && str_contains(dbReply($res), (string) $firstStudent['nim'])
    );
}

$res = dbAsk($dbService, 'Berapa jumlah mahasiswa?', $adminUser);
assertDbCheck(
    'Intent hitung entitas mahasiswa mengembalikan jumlah aktual, bukan daftar nama',
    dbSource($res) === 'app_database'
    && ($res['context']['intent'] ?? '') === 'entity_count'
    && str_contains(dbReply($res), (string) count($students))
    && !dbContainsAnyName(dbReply($res), $students)
);

// -----------------------------------------------------------------------------
// 2B. DAFTAR & DETAIL MAHASISWA
// -----------------------------------------------------------------------------
echo "\n--- 2B. DAFTAR & DETAIL MAHASISWA DARI DATABASE ---\n";

$studentClasses = [];
$classRows = $db->query(
    "SELECT km.mahasiswa_id,
            GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas
     FROM kelas_mahasiswa km
     JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
     JOIN kelas k ON k.id = jk.kelas_id
     WHERE km.status = 'aktif'
     GROUP BY km.mahasiswa_id"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($classRows as $classRow) {
    $studentClasses[(int) $classRow['mahasiswa_id']] = (string) ($classRow['kelas'] ?? '');
}

$countContext = $res['context'] ?? [];
$res = dbAsk($dbService, 'Siapa saja nama mahasiswa di sini?', $adminUser, $countContext);
assertDbCheck(
    'Pertanyaan daftar setelah entity_count tidak mewarisi intent jumlah',
    dbSource($res) === 'app_database'
    && ($res['context']['intent'] ?? '') === 'student_list'
    && ($students === []
        ? str_contains(dbReply($res), 'Tidak ditemukan')
        : dbContainsAllStudents(dbReply($res), $students))
);

$activeStudents = array_values(array_filter(
    $students,
    static fn(array $student): bool => (string) ($student['status_akademik'] ?? '') === 'aktif'
));
$inactiveStudents = array_values(array_filter(
    $students,
    static fn(array $student): bool => (string) ($student['status_akademik'] ?? '') !== 'aktif'
));
$res = dbAsk($dbService, 'Tampilkan daftar mahasiswa aktif.', $adminUser);
assertDbCheck(
    'Pertanyaan mahasiswa aktif hanya menampilkan mahasiswa berstatus aktif',
    dbSource($res) === 'app_database'
    && ($res['context']['intent'] ?? '') === 'student_list'
    && ($activeStudents === []
        ? str_contains(dbReply($res), 'Tidak ditemukan')
        : dbContainsAllStudents(dbReply($res), $activeStudents))
    && !dbContainsAnyName(dbReply($res), $inactiveStudents)
);

if ($firstStudent !== null) {
    $res = dbAsk($dbService, 'Siapa mahasiswa dengan NIM ' . $firstStudent['nim'] . '?', $adminUser);
    $expectedClass = $studentClasses[(int) $firstStudent['id']] ?? '';
    assertDbCheck(
        'Pertanyaan berdasarkan NIM menampilkan detail mahasiswa yang sesuai',
        dbSource($res) === 'app_database'
        && ($res['context']['intent'] ?? '') === 'student_detail'
        && str_contains(dbReply($res), (string) $firstStudent['nama_lengkap'])
        && str_contains(dbReply($res), (string) $firstStudent['nim'])
        && str_contains(dbReply($res), (string) $firstStudent['prodi'])
        && str_contains(dbReply($res), (string) $firstStudent['status_akademik'])
        && ($expectedClass === '' || str_contains(dbReply($res), $expectedClass))
    );
}

$res = dbAsk($dbService, 'Tampilkan daftar mahasiswa beserta NIM dan kelasnya.', $adminUser);
$classesMatch = true;
foreach ($students as $student) {
    $classText = $studentClasses[(int) $student['id']] ?? '';
    if (!str_contains(dbReply($res), (string) $student['nama_lengkap'])
        || !str_contains(dbReply($res), (string) $student['nim'])
        || ($classText !== '' && !str_contains(dbReply($res), $classText))
    ) {
        $classesMatch = false;
        break;
    }
}
assertDbCheck(
    'Pertanyaan daftar dengan NIM dan kelas menampilkan data aktual',
    dbSource($res) === 'app_database'
    && ($res['context']['intent'] ?? '') === 'student_list'
    && ($students === []
        ? str_contains(dbReply($res), 'Tidak ditemukan')
        : $classesMatch)
);

$res = dbAsk($dbService, 'Siapa mahasiswa dengan NIM 9999999?', $adminUser);
assertDbCheck(
    'NIM yang tidak ada pada pertanyaan detail tidak menghasilkan nama palsu',
    dbSource($res) === 'database_clarification'
    && !dbContainsAnyName(dbReply($res), $students)
);

if ($mahasiswaUsers !== []) {
    $res = dbAsk($dbService, 'Siapa saja nama mahasiswa?', $mahasiswaUsers[0]);
    assertDbCheck(
        'Mahasiswa tidak dapat meminta daftar seluruh mahasiswa',
        dbSource($res) === 'privacy_guard'
    );
}

// -----------------------------------------------------------------------------
// 3. ROLE SCOPING
// -----------------------------------------------------------------------------
echo "\n--- 3. ROLE SCOPING (ADMIN, DOSEN, MAHASISWA) ---\n";

if ($lateRow !== null && $lateDosenUser !== null) {
    $res = dbAsk($dbService, 'Siapa yang terlambat bulan ini?', $lateDosenUser);
    assertDbCheck(
        'Dosen dapat melihat keterlambatan pada kelas yang diampu',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) $lateRow['nama_lengkap'])
    );

    if ($otherDosenUser !== null) {
        $res = dbAsk($dbService, 'Siapa yang terlambat bulan ini?', $otherDosenUser);
        assertDbCheck(
            'Dosen lain tidak melihat keterlambatan dari kelas dosen lain',
            dbSource($res) === 'app_database'
            && !str_contains(dbReply($res), (string) $lateRow['nama_lengkap'])
        );
    }

    $res = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $lateRow['nama_lengkap'], $lateDosenUser);
    assertDbCheck(
        'Dosen dapat melihat riwayat mahasiswa di kelasnya',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) $lateRow['nama_lengkap'])
    );
}

if ($mahasiswaUsers !== [] && $firstStudent !== null) {
    $selfUser = $mahasiswaUsers[0];
    $res = dbAsk($dbService, 'Tampilkan riwayat absensi saya', $selfUser);
    assertDbCheck(
        'Mahasiswa dapat melihat riwayatnya sendiri',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) ($selfUser['nama_lengkap'] ?? ''))
    );

    $selfStudentStmt = $db->prepare("SELECT id FROM mahasiswa WHERE user_id = :user_id LIMIT 1");
    $selfStudentStmt->execute(['user_id' => (int) $selfUser['id']]);
    $selfStudentId = (int) $selfStudentStmt->fetchColumn();

    $otherStudent = null;
    foreach ($students as $student) {
        if ((int) $student['id'] !== $selfStudentId) {
            $otherStudent = $student;
            break;
        }
    }
    if ($otherStudent !== null) {
        $res = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $otherStudent['nama_lengkap'], $selfUser);
        assertDbCheck(
            'Mahasiswa ditolak saat meminta riwayat mahasiswa lain',
            dbSource($res) === 'privacy_guard'
        );

        $res = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $otherStudent['nama_lengkap'], $adminUser);
        assertDbCheck(
            'Admin tetap dapat melihat riwayat mahasiswa lain',
            dbSource($res) === 'app_database'
            && str_contains(dbReply($res), (string) $otherStudent['nama_lengkap'])
        );
    }
}

// -----------------------------------------------------------------------------
// 4. KONTEKS PERCAKAPAN & KLARIFIKASI
// -----------------------------------------------------------------------------
echo "\n--- 4. KONTEKS PERCAKAPAN & KLARIFIKASI ---\n";

$lateContext = [];
$res1 = dbAsk($dbService, 'Siapa yang terlambat bulan ini?', $adminUser);
if (is_array($res1) && !empty($res1['context'])) {
    $lateContext = $res1['context'];
}
$res2 = dbAsk($dbService, 'Siapa yang paling sering?', $adminUser, $lateContext);
assertDbCheck(
    'Follow-up "Siapa yang paling sering?" memakai periode dan intent sebelumnya',
    dbSource($res2) === 'app_database'
    && ($res2['context']['intent'] ?? '') === 'late_ranking'
    && ($res2['context']['period']['label'] ?? '') === 'bulan ini'
);

if ($firstStudent !== null) {
    $res1 = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $firstStudent['nama_lengkap'], $adminUser);
    $historyContext = is_array($res1) ? ($res1['context'] ?? []) : [];
    $res2 = dbAsk($dbService, 'kalau bulan ini?', $adminUser, $historyContext);
    assertDbCheck(
        'Follow-up periode pada riwayat mahasiswa memakai mahasiswa yang sama',
        dbSource($res2) === 'app_database'
        && ($res2['context']['intent'] ?? '') === 'student_history'
        && str_contains(dbReply($res2), (string) $firstStudent['nama_lengkap'])
    );
}

$clarification = dbAsk($dbService, 'Siapa yang paling sering terlambat?', $adminUser);
assertDbCheck(
    'Pertanyaan tanpa periode meminta klarifikasi dan menyimpan pending intent',
    dbSource($clarification) === 'database_clarification'
    && ($clarification['context']['pending_intent'] ?? '') === 'late_ranking'
);

$clarificationContext = is_array($clarification) ? ($clarification['context'] ?? []) : [];
$answered = dbAsk($dbService, 'bulan ini', $adminUser, $clarificationContext);
assertDbCheck(
    'Jawaban periode setelah klarifikasi melanjutkan intent yang tertunda',
    dbSource($answered) === 'app_database'
    && ($answered['context']['intent'] ?? '') === 'late_ranking'
    && ($answered['context']['period']['label'] ?? '') === 'bulan ini'
);

$periodSummary = dbAsk($dbService, 'Tampilkan data September 2026', $adminUser);
$periodSummaryContext = is_array($periodSummary) ? ($periodSummary['context'] ?? []) : [];
$newQuestion = dbAsk($dbService, 'Siapa yang terlambat?', $adminUser, $periodSummaryContext);
assertDbCheck(
    'Pertanyaan baru tanpa periode tidak diam-diam memakai periode lama',
    dbSource($newQuestion) === 'database_clarification'
);

// -----------------------------------------------------------------------------
// 5. TAHUN AKADEMIK BERAKHIR & DATA KOSONG
// -----------------------------------------------------------------------------
echo "\n--- 5. TAHUN AKADEMIK BERAKHIR & DATA KOSONG ---\n";

if ($activeTa !== null && (string) $activeTa['tanggal_selesai'] < $today) {
    $res = dbAsk($dbService, 'Bagaimana rekap kehadiran semester ini?', $adminUser);
    assertDbCheck(
        'TA aktif yang sudah berakhir ditandai sebagai data historis',
        str_contains(dbReply($res), 'sudah berakhir')
        && str_contains(dbReply($res), 'bukan periode yang sedang berlangsung')
    );

    $res = dbAsk($dbService, 'Siapa yang terlambat semester ini?', $adminUser);
    assertDbCheck(
        'Jawaban presensi semester ini menyertakan peringatan TA berakhir',
        str_contains(dbReply($res), 'sudah berakhir')
    );

    $res = dbAsk($dbService, 'Berapa persentase kehadiran minimal untuk ikut UAS?', $adminUser);
    assertDbCheck(
        'Konfigurasi UAS tidak mengklaim TA lama masih berlangsung',
        str_contains(dbReply($res), 'sudah berakhir')
        && str_contains(dbReply($res), 'Jangan menganggapnya')
    );
}

$res = dbAsk($dbService, 'Siapa yang terlambat Januari 2024?', $adminUser);
assertDbCheck(
    'Periode kosong tidak mengarang nama mahasiswa',
    dbSource($res) === 'app_database'
    && str_contains(dbReply($res), 'Tidak ditemukan')
    && !dbContainsAnyName(dbReply($res), $students)
);

$res = dbAsk($dbService, 'Tampilkan data Januari 2024', $adminUser);
assertDbCheck(
    'Period summary untuk periode kosong menjelaskan tidak ada catatan',
    dbSource($res) === 'app_database'
    && str_contains(dbReply($res), 'Tidak ditemukan catatan presensi')
);

$res = dbAsk($dbService, 'Berapa total izin dan sakit Januari 2024?', $adminUser);
assertDbCheck(
    'Rekap izin/sakit periode kosong tidak mengarang angka',
    dbSource($res) === 'app_database'
    && str_contains(dbReply($res), 'Tidak ditemukan')
);

if ($firstStudent !== null) {
    $res = dbAsk($dbService, 'Tampilkan riwayat absensi ' . $firstStudent['nama_lengkap'] . ' Januari 2024', $adminUser);
    assertDbCheck(
        'Riwayat mahasiswa pada periode kosong tetap menyebut nama yang diminta',
        dbSource($res) === 'app_database'
        && str_contains(dbReply($res), (string) $firstStudent['nama_lengkap'])
        && str_contains(dbReply($res), 'Tidak ditemukan')
    );
}

// -----------------------------------------------------------------------------
// 6. ANTI-HALUSINASI & KEAMANAN QUERY
// -----------------------------------------------------------------------------
echo "\n--- 6. ANTI-HALUSINASI & KEAMANAN QUERY ---\n";

$res = dbAsk($dbService, 'Tampilkan riwayat absensi NIM 9999999', $adminUser);
assertDbCheck(
    'NIM yang tidak ada tidak menghasilkan nama mahasiswa palsu',
    dbSource($res) === 'database_clarification'
    && !dbContainsAnyName(dbReply($res), $students)
);

$res = dbAsk($dbService, 'Profil mahasiswa NIM 9999999', $adminUser);
assertDbCheck(
    'Profil dengan NIM tidak dikenal tidak menghasilkan data palsu',
    dbSource($res) === 'database_clarification'
    && !dbContainsAnyName(dbReply($res), $students)
);

$res = dbAsk($dbService, 'Siapa presiden Indonesia?', $adminUser);
assertDbCheck(
    'Pertanyaan di luar domain tidak dipaksa dijawab oleh layer database',
    $res === null
);

$res = dbAsk($dbService, 'Siapa yang terlambat?', $adminUser);
assertDbCheck(
    'Pertanyaan data tanpa periode tidak menjalankan query semua riwayat',
    dbSource($res) === 'database_clarification'
);

$injections = [
    "Tampilkan riwayat absensi ' OR 1=1 --",
    "Tampilkan riwayat absensi '; DROP TABLE absensi; --",
    "Siapa yang terlambat UNION SELECT * FROM users --",
];
$injectionSafe = true;
foreach ($injections as $injection) {
    try {
        $res = dbAsk($dbService, $injection, $adminUser);
        if (is_array($res)) {
            $reply = dbReply($res);
            if (dbSource($res) !== 'database_clarification' || dbContainsAnyName($reply, $students)) {
                $injectionSafe = false;
            }
        }
    } catch (Throwable $e) {
        $injectionSafe = false;
    }
}
$absensiStillExists = (int) $db->query("SELECT COUNT(*) FROM absensi")->fetchColumn() > 0;
assertDbCheck(
    'Input SQL injection tidak dieksekusi dan tidak membocorkan semua data',
    $injectionSafe && $absensiStillExists
);

// -----------------------------------------------------------------------------
// 7. INTEGRASI SERVICE CHATBOT (GUARD + DATABASE)
// -----------------------------------------------------------------------------
echo "\n--- 7. INTEGRASI SERVICE CHATBOT ---\n";

$res = $chatService->sendMessage('Siapa yang terlambat bulan ini?', $adminUser, [], []);
$expectedLateReply = $lateRow !== null ? (string) $lateRow['nama_lengkap'] : 'Tidak ditemukan';
assertDbCheck(
    'Service chatbot memakai layer database sebelum fallback AI',
    ($res['source'] ?? '') === 'app_database'
    && str_contains((string) ($res['reply'] ?? ''), $expectedLateReply)
);

$followUpContext = is_array($res) ? ($res['context'] ?? []) : [];
$res = $chatService->sendMessage('bulan ini', $adminUser, [], $followUpContext);
assertDbCheck(
    'Follow-up periode tetap diproses meskipun tidak memiliki sinyal topik eksplisit',
    ($res['source'] ?? '') === 'app_database'
    && ($res['context']['intent'] ?? '') === 'late_list'
);

$res = $chatService->sendMessage('Tampilkan data September 2026', $adminUser, [], []);
assertDbCheck(
    'Query data dengan periode tidak ditolak oleh guard topik',
    ($res['source'] ?? '') === 'app_database'
);

$res = $chatService->sendMessage('Siapa presiden Indonesia?', $adminUser, [], []);
assertDbCheck(
    'Pertanyaan di luar topik tetap ditolak oleh guard',
    ($res['source'] ?? '') === 'context_guard'
);

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN DATABASE CHATBOT: {$passCount} PASS | {$failCount} FAIL\n";
echo "====================================================================\n";

exit($failCount > 0 ? 1 : 0);
