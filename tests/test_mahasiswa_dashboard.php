<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Dashboard Mahasiswa & Menu Navigasi
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
use App\Models\Absensi;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - MAHASISWA DASHBOARD & MENU TEST SUITE\n";
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
    $router->get('/mahasiswa/riwayat', [MahasiswaDashboard::class, 'riwayat'], ['auth', 'role:mahasiswa']);
    $router->get('/mahasiswa/izin', [MahasiswaDashboard::class, 'izin'], ['auth', 'role:mahasiswa']);
    $router->get('/mahasiswa/profil', [MahasiswaDashboard::class, 'profil'], ['auth', 'role:mahasiswa']);

    return $router;
}

function testSimulate(string $uri, array $sessionUserData): array
{
    $_SESSION = [];
    Session::set('user', $sessionUserData);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_POST = [];
    $_GET = [];

    $req = new Request();
    $res = new Response();

    $router = buildTestRouter();
    
    ob_start();
    $router->dispatch($req, $res);
    $output = ob_get_clean();

    return [
        'status' => $res->getStatusCode(),
        'output' => $output
    ];
}

$userModel = new User();
$absensiModel = new Absensi();

// =============================================================================
// TEST GROUP 1: STATISTIK DATABASE MAHASISWA 1 (Rizky Pratama - 4122001)
// =============================================================================
echo "--- [TEST GROUP 1] STATISTIK DATABASE MAHASISWA 1 (Rizky Pratama) ---\n";

$mhs1 = $userModel->findByUsername('4122001');
$profile1 = $userModel->getFullProfile((int)$mhs1['id']);
unset($profile1['password_hash']);

$stats1 = $absensiModel->getStudentOverallStats((int)$profile1['mahasiswa_id']);

assertTest(
    "Data Nama & NIM Mahasiswa 1 valid (Rizky Pratama - 4122001)",
    $profile1['nama_lengkap'] === 'Rizky Pratama' && $profile1['nim'] === '4122001'
);
assertTest(
    "Kalkulasi Kehadiran Mahasiswa 1 akurat (2 Hadir, 0 Izin, 0 Sakit, 0 Alpha = 100%)",
    $stats1['total_kehadiran'] === 2 && $stats1['persentase_kehadiran'] === 100.0
);

// =============================================================================
// TEST GROUP 2: RENDERING DASHBOARD & SEMUA MENU MAHASISWA 1
// =============================================================================
echo "\n--- [TEST GROUP 2] RENDERING VIEW DASHBOARD & MENU MAHASISWA ---\n";

// 2.1: Dashboard View
$dash1 = testSimulate('/mahasiswa/dashboard', $profile1);
assertTest(
    "Halaman /mahasiswa/dashboard merespons HTTP 200",
    $dash1['status'] === 200
);
assertTest(
    "Dashboard menampilkan Nama Mahasiswa 'Rizky Pratama'",
    str_contains($dash1['output'], 'Rizky Pratama')
);
assertTest(
    "Dashboard menampilkan NIM '4122001'",
    str_contains($dash1['output'], '4122001')
);
assertTest(
    "Dashboard menampilkan Persentase Kehadiran '100.0%'",
    str_contains($dash1['output'], '100.0%')
);
assertTest(
    "Dashboard menampilkan Motivasi 'Sangat Baik'",
    str_contains($dash1['output'], 'Sangat Baik')
);
assertTest(
    "Dashboard memuat Menu Navigasi: Dashboard, Absensi, Riwayat, Izin, Profil, Logout",
    str_contains($dash1['output'], 'Riwayat Absensi') && 
    str_contains($dash1['output'], 'Izin/Sakit') && 
    str_contains($dash1['output'], 'Profil') && 
    str_contains($dash1['output'], 'Logout')
);

// 2.2: Menu Absensi View
$absensiRes = testSimulate('/mahasiswa/absensi', $profile1);
assertTest(
    "Menu /mahasiswa/absensi merespons HTTP 200 dan menampilkan daftar mata kuliah",
    $absensiRes['status'] === 200 && (str_contains($absensiRes['output'], 'Pemilihan Mata Kuliah') || str_contains($absensiRes['output'], 'Presensi Perkuliahan'))
);

// 2.3: Menu Riwayat Absensi View
$riwayatRes = testSimulate('/mahasiswa/riwayat', $profile1);
assertTest(
    "Menu /mahasiswa/riwayat merespons HTTP 200 dan memuat log presensi",
    $riwayatRes['status'] === 200 && str_contains($riwayatRes['output'], 'Riwayat Kehadiran Mahasiswa')
);

// 2.4: Menu Izin/Sakit View
$izinRes = testSimulate('/mahasiswa/izin', $profile1);
assertTest(
    "Menu /mahasiswa/izin merespons HTTP 200 dan memuat form/list izin",
    $izinRes['status'] === 200 && str_contains($izinRes['output'], 'Pengajuan Izin & Surat Sakit')
);

// 2.5: Menu Profil View
$profilRes = testSimulate('/mahasiswa/profil', $profile1);
assertTest(
    "Menu /mahasiswa/profil merespons HTTP 200 dan memuat status biometrik AI",
    $profilRes['status'] === 200 && str_contains($profilRes['output'], 'Profil Data Mahasiswa')
);

// =============================================================================
// TEST GROUP 3: PENGUJIAN MAHASISWA 2 (Putri Lestari - ada Izin) & MAHASISWA 3 (Bintang)
// =============================================================================
echo "\n--- [TEST GROUP 3] PENGUJIAN MAHASISWA DENGAN STATUS IZIN & TERLAMBAT ---\n";

// Mahasiswa 2: Putri Lestari (4122002) - Hadir 1, Izin 1
$mhs2 = $userModel->findByUsername('4122002');
$profile2 = $userModel->getFullProfile((int)$mhs2['id']);
unset($profile2['password_hash']);

$dash2 = testSimulate('/mahasiswa/dashboard', $profile2);
$stats2 = $absensiModel->getStudentOverallStats((int)$profile2['mahasiswa_id']);

assertTest(
    "Mahasiswa 2 (Putri Lestari) memiliki 1 Izin tercatat dari database",
    $stats2['izin'] === 1
);
assertTest(
    "Persentase Mahasiswa 2 terhitung 50.0% dengan status peringatan",
    $stats2['persentase_kehadiran'] === 50.0 && str_contains($dash2['output'], 'Perhatian Kritis')
);

echo "\n====================================================================\n";
echo "  HASIL AKHIR PENGUJIAN: {$passedCount} / {$totalTests} TESTS BERHASIL (" . round(($passedCount / $totalTests) * 100, 1) . "%)\n";
echo "====================================================================\n";
