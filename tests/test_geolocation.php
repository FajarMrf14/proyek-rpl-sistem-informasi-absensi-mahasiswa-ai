<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Fitur Geolocation GPS & Server-Side Geofencing untuk Absensi
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\Mahasiswa\AbsensiController;
use App\Helpers\GeoHelper;
use App\Models\User;
use App\Models\Jadwal;
use App\Models\AuditLog;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - GEOLOCATION & GEOFENCING TEST SUITE\n";
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

    $router->get('/mahasiswa/absensi/geolocation/{id}', [AbsensiController::class, 'halamanGeolocation'], ['auth', 'role:mahasiswa']);
    $router->post('/mahasiswa/absensi/geolocation/{id}', [AbsensiController::class, 'validasiGeolocation'], ['auth', 'role:mahasiswa']);

    return $router;
}

function testSimulate(string $uri, array $sessionUserData, string $method = 'GET', array $postParams = [], bool $isAjax = false): array
{
    $_SESSION = [];
    Session::set('user', $sessionUserData);

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    
    if ($method === 'POST' && !isset($postParams['_csrf_token'])) {
        $postParams['_csrf_token'] = Session::generateCsrfToken();
    }
    
    $_POST = $postParams;

    $parsed = parse_url($uri);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $_GET);
    } else {
        $_GET = [];
    }

    if ($isAjax) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    } else {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    $response = new Response();
    $router = buildTestRouter();

    ob_start();
    $router->dispatch(new Request(), $response);
    $output = ob_get_clean();

    return [
        'status'   => $response->getStatusCode(),
        'headers'  => $response->getHeaders(),
        'output'   => $output,
        'flash'    => Session::getFlash('error') ?? Session::getFlash('success') ?? Session::getFlash('info'),
        'flash_type' => Session::getFlash('error') ? 'error' : (Session::getFlash('success') ? 'success' : 'info')
    ];
}

// User data dummy (Mahasiswa)
$userModel = new User();
$mhsRaw = $userModel->findByUsername('4122001'); // NIM 4122001 - Ahmad Rizki
$mhsUser = $userModel->getFullProfile((int)$mhsRaw['id']);

// -----------------------------------------------------------------------------
// Unit Testing Helper Geolocation
// -----------------------------------------------------------------------------
echo "1. UNIT TEST GEOLOCATION HELPER\n";
echo "--------------------------------------------------------------------\n";

// Titik Kampus STMIK IKMI Cirebon dari config
$campusLat = -6.7441584;
$campusLon = 108.5441566;

// Posisi persis di kampus (jarak = 0 meter)
$distZero = GeoHelper::calculateDistance($campusLat, $campusLon, $campusLat, $campusLon);
assertTest("Haversine distance koordinat identik harus 0 meter", $distZero == 0.0, "Jarak: {$distZero}m");

// Posisi ~50m dari kampus (-6.7441584, 108.5446100)
$distClose = GeoHelper::calculateDistance($campusLat, $campusLon, -6.7441584, 108.5446100);
assertTest("Haversine distance posisi dekat kampus (~50m)", $distClose > 30 && $distClose < 70, "Jarak terhitung: {$distClose}m");

// Posisi jauh dari kampus (~5km di alun-alun Cirebon -6.719, 108.564)
$distFar = GeoHelper::calculateDistance($campusLat, $campusLon, -6.7190000, 108.5640000);
assertTest("Haversine distance posisi jauh (>1km)", $distFar > 1000, "Jarak terhitung: {$distFar}m");

// Geofence test: dalam radius 100m
$geoIn = GeoHelper::checkGeofence($campusLat, $campusLon, $campusLat, $campusLon, 100);
assertTest("Geofence check titik kampus (0m) harus IS_VALID = TRUE", $geoIn['is_valid'] === true);

// Geofence test: luar radius 100m
$geoOut = GeoHelper::checkGeofence(-6.7190000, 108.5640000, $campusLat, $campusLon, 100);
assertTest("Geofence check titik jauh (5km) harus IS_VALID = FALSE", $geoOut['is_valid'] === false);

// Validasi sintaks koordinat
assertTest("isValidCoordinate(-6.74, 108.54) = true", GeoHelper::isValidCoordinate(-6.744, 108.544));
assertTest("isValidCoordinate(null, 108.54) = false", GeoHelper::isValidCoordinate(null, 108.544) === false);
assertTest("isValidCoordinate(-95.0, 108.54) = false", GeoHelper::isValidCoordinate(-95.0, 108.544) === false);

echo "\n";

// -----------------------------------------------------------------------------
// Controller Integration Testing
// -----------------------------------------------------------------------------
echo "2. CONTROLLER & SERVER-SIDE GEOFENCING INTEGRATION TEST\n";
echo "--------------------------------------------------------------------\n";

$jadwalModel = new Jadwal();
// Cari jadwal aktif untuk mahasiswa ini
$schedules = $jadwalModel->getStudentSchedules((int)$mhsUser['mahasiswa_id']);

if (!empty($schedules)) {
    $firstJadwal = $schedules[0];
    $jadwalId = (int)$firstJadwal['id'];

    $hariJadwal = $firstJadwal['hari'];

    // Test GET Halaman Geolocation
    $resGet = testSimulate("/mahasiswa/absensi/geolocation/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'GET');
    assertTest("GET halaman geolocation mengembalikan status HTTP 200 OK", $resGet['status'] === 200);
    assertTest("Render halaman mengandung judul Geolocation GPS", str_contains($resGet['output'], 'Pengecekan Lokasi GPS'));

    // Test POST Geolocation — AJAX Valid (Dalam Radius 100m)
    $resPostValid = testSimulate("/mahasiswa/absensi/geolocation/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'POST', [
        'latitude'  => $campusLat,
        'longitude' => $campusLon,
        'accuracy'  => 15
    ], isAjax: true);

    assertTest("POST Geolocation (Dalam Radius) HTTP Status 200 OK", $resPostValid['status'] === 200);
    $jsonValid = json_decode($resPostValid['output'], true);
    assertTest("Response JSON success = true", isset($jsonValid['success']) && $jsonValid['success'] === true);
    assertTest("Verifikasi session geo_verified terbuat", !empty($_SESSION["geo_verified_{$jadwalId}"]));

    // Test POST Geolocation — AJAX Invalid (Luar Radius >100m)
    $resPostFar = testSimulate("/mahasiswa/absensi/geolocation/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'POST', [
        'latitude'  => -6.7190000,
        'longitude' => 108.5640000,
        'accuracy'  => 10
    ], isAjax: true);

    assertTest("POST Geolocation (Luar Radius) HTTP Status 422 Unprocessable Entity", $resPostFar['status'] === 422);
    $jsonFar = json_decode($resPostFar['output'], true);
    assertTest("Response JSON success = false", isset($jsonFar['success']) && $jsonFar['success'] === false);
    assertTest("Pesan penolakan mengandung informasi jarak di luar kampus", str_contains($jsonFar['message'] ?? '', 'di luar kampus') || str_contains($jsonFar['message'] ?? '', 'luar radius'));

    // Test POST Geolocation — Tanpa Koordinat / Empty Input
    $resPostNull = testSimulate("/mahasiswa/absensi/geolocation/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'POST', [
        'latitude'  => '',
        'longitude' => ''
    ], isAjax: true);

    assertTest("POST Geolocation (Tanpa Koordinat) HTTP Status 422", $resPostNull['status'] === 422);
    $jsonNull = json_decode($resPostNull['output'], true);
    assertTest("Pesan error koordinat tidak valid", str_contains($jsonNull['message'] ?? '', 'tidak valid'));

} else {
    echo "  [WARN] Tidak ada data jadwal perkuliahan untuk testing controller.\n";
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "TEST RESULTS: {$passedCount} / {$totalTests} PASSED\n";
echo "--------------------------------------------------------------------\n\n";

if ($passedCount === $totalTests) {
    echo "🎉 SELAMAT! Seluruh pengujian Fitur Geolocation Absensi BERHASIL (100% PASS).\n";
    exit(0);
} else {
    echo "⚠️ Terdapat " . ($totalTests - $passedCount) . " test yang gagal. Silakan perbaiki!\n";
    exit(1);
}
