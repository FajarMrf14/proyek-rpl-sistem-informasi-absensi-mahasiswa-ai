<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Fitur Jadwal Kuliah & Pemilihan Mata Kuliah untuk Absensi
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\Mahasiswa\DashboardController as MahasiswaDashboard;
use App\Models\User;
use App\Models\Jadwal;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - JADWAL & PEMILIHAN MATA KULIAH TEST SUITE\n";
echo "====================================================================\n\n";

$passedCount = 0;
$totalTests = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passedCount, $totalTests;
    $totalTests++;
    if ($condition) {
        $passedCount++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description} - {$details}\n";
    }
}

function buildTestRouter(): Router
{
    $router = new Router();
    $router->registerMiddleware('auth', \App\Middleware\AuthMiddleware::class);
    $router->registerMiddleware('role', \App\Middleware\RoleMiddleware::class);

    $router->get('/mahasiswa/dashboard', [MahasiswaDashboard::class, 'index'], ['auth', 'role:mahasiswa']);
    $router->get('/mahasiswa/absensi', [MahasiswaDashboard::class, 'absensi'], ['auth', 'role:mahasiswa']);
    $router->get('/mahasiswa/absensi/proses/{id}', [MahasiswaDashboard::class, 'prosesAbsensi'], ['auth', 'role:mahasiswa']);

    return $router;
}

function testSimulate(string $uri, array $sessionUserData, string $method = 'GET', array $params = []): array
{
    $_SESSION = [];
    Session::set('user', $sessionUserData);

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_POST = $method === 'POST' ? $params : [];
    
    // Parse query params into $_GET
    $parsed = parse_url($uri);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $_GET);
    } else {
        $_GET = [];
    }

    $req = new Request();
    $res = new Response();

    $router = buildTestRouter();
    
    ob_start();
    $router->dispatch($req, $res);
    $output = ob_get_clean();

    $headers = $res->getHeaders();

    return [
        'status'   => $res->getStatusCode(),
        'redirect' => $headers['Location'] ?? null,
        'output'   => $output
    ];
}

$userModel = new User();
$jadwalModel = new Jadwal();

$mhs = $userModel->findByUsername('4122001');
$profile = $userModel->getFullProfile((int)$mhs['id']);
unset($profile['password_hash']);
$mahasiswaId = (int)$profile['mahasiswa_id'];

// =============================================================================
// TEST GROUP 1: DATABASE QUERY MATA KULIAH & JADWAL MAHASISWA
// =============================================================================
echo "--- [TEST GROUP 1] DATABASE QUERY MATA KULIAH MAHASISWA ---\n";

$allSchedules = $jadwalModel->getStudentSchedules($mahasiswaId);
assertTest(
    "Query mengambil seluruh mata kuliah mahasiswa yang terdaftar di kelas_mahasiswa",
    count($allSchedules) >= 3
);

$seninSchedules = $jadwalModel->getStudentSchedules($mahasiswaId, 'Senin');
assertTest(
    "Query filter jadwal mahasiswa pada hari Senin menghasilkan mata kuliah valid (TIF-301)",
    count($seninSchedules) >= 1 && $seninSchedules[0]['kode_mk'] === 'TIF-301'
);

$rabuSchedules = $jadwalModel->getStudentSchedules($mahasiswaId, 'Rabu');
assertTest(
    "Query filter jadwal mahasiswa pada hari Rabu menghasilkan mata kuliah valid (TIF-302)",
    count($rabuSchedules) >= 1 && $rabuSchedules[0]['kode_mk'] === 'TIF-302'
);

// =============================================================================
// TEST GROUP 2: RENDERING HALAMAN PEMILIHAN MATA KULIAH (/mahasiswa/absensi)
// =============================================================================
echo "\n--- [TEST GROUP 2] RENDERING HALAMAN PEMILIHAN MATA KULIAH ---\n";

$absensiPage = testSimulate('/mahasiswa/absensi?hari=Senin', $profile);
assertTest(
    "Halaman /mahasiswa/absensi merespons HTTP 200",
    $absensiPage['status'] === 200
);
assertTest(
    "Halaman menampilkan mata kuliah aktif hari ini (TIF-301 Kecerdasan Buatan & Machine Learning)",
    str_contains($absensiPage['output'], 'TIF-301') && str_contains($absensiPage['output'], 'Kecerdasan Buatan')
);
assertTest(
    "Halaman menampilkan tombol 'Pilih Mata Kuliah & Mulai Absensi'",
    str_contains($absensiPage['output'], 'Pilih Mata Kuliah &amp; Mulai Absensi') || str_contains($absensiPage['output'], 'Pilih Mata Kuliah & Mulai Absensi')
);
assertTest(
    "Halaman menampilkan jadwal hari lainnya dengan status nonaktif/informasi",
    str_contains($absensiPage['output'], 'Jadwal Mata Kuliah Hari Lainnya')
);

// =============================================================================
// TEST GROUP 3: PEMILIHAN MATA KULIAH PADA HARI JADWAL YANG SESUAI (VALID)
// =============================================================================
echo "\n--- [TEST GROUP 3] PEMILIHAN MATA KULIAH VALID (SESUAI HARI JADWAL) ---\n";

// Jadwal 1 (TIF-301) dijadwalkan pada hari SENIN
$validSelection = testSimulate('/mahasiswa/absensi/proses/1?hari=Senin', $profile);
assertTest(
    "Pemilihan mata kuliah hari Senin untuk Jadwal 1 berhasil (HTTP 200)",
    $validSelection['status'] === 200
);
assertTest(
    "Halaman proses absensi memuat detail mata kuliah terpilih (TIF-301 Kecerdasan Buatan & Machine Learning)",
    str_contains($validSelection['output'], 'Kecerdasan Buatan & Machine Learning') || str_contains($validSelection['output'], 'Kecerdasan Buatan &amp; Machine Learning')
);
assertTest(
    "Halaman memuat alur tahapan presensi (Tahap 1 Selesai -> Siap lanjut ke GPS & AI)",
    str_contains($validSelection['output'], 'Tahap 1') && str_contains($validSelection['output'], 'Pilih Mata Kuliah')
);

// =============================================================================
// TEST GROUP 4: ATURAN VALIDASI: PENOLAKAN ABSENSI DI LUAR HARI JADWAL
// =============================================================================
echo "\n--- [TEST GROUP 4] PENOLAKAN ABSENSI DI LUAR HARI JADWAL (RULE ENFORCEMENT) ---\n";

// Jadwal 1 (TIF-301) adalah hari SENIN, coba pilih pada hari SELASA
$invalidDaySelection = testSimulate('/mahasiswa/absensi/proses/1?hari=Selasa', $profile);
assertTest(
    "Percobaan absensi Jadwal 1 (Senin) pada hari Selasa DITOLAK (Redirect 302 ke /mahasiswa/absensi)",
    $invalidDaySelection['status'] === 302 && str_contains($invalidDaySelection['redirect'] ?? '', 'mahasiswa/absensi')
);
assertTest(
    "Pesan error penolakan jadwal tercatat di Session Flash Error",
    Session::hasFlash('error') && str_contains(Session::getFlash('error'), 'Presensi Ditolak!')
);

// Jadwal 2 (TIF-302) adalah hari RABU, coba pilih pada hari KAMIS
$invalidDaySelection2 = testSimulate('/mahasiswa/absensi/proses/2?hari=Kamis', $profile);
assertTest(
    "Percobaan absensi Jadwal 2 (Rabu) pada hari Kamis DITOLAK (Redirect 302)",
    $invalidDaySelection2['status'] === 302
);

// Percobaan akses jadwal yang tidak terdaftar / invalid ID (Jadwal ID: 9999)
$invalidScheduleId = testSimulate('/mahasiswa/absensi/proses/9999', $profile);
assertTest(
    "Percobaan absensi jadwal yang tidak diambil/tidak ada DITOLAK (Redirect 302)",
    $invalidScheduleId['status'] === 302 && str_contains(Session::getFlash('error'), 'Akses ditolak')
);

echo "\n====================================================================\n";
echo "  HASIL AKHIR PENGUJIAN: {$passedCount} / {$totalTests} TESTS BERHASIL (" . round(($passedCount / $totalTests) * 100, 1) . "%)\n";
echo "====================================================================\n";
