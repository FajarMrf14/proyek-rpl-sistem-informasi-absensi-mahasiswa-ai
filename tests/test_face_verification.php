<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Fitur AI Face Verification & Biometric Matcher
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\Mahasiswa\AbsensiController;
use App\Services\FaceService;
use App\Models\User;
use App\Models\Jadwal;
use App\Models\WajahMahasiswa;
use App\Models\AuditLog;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - AI FACE VERIFICATION TEST SUITE\n";
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

    $router->get('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'halamanWajah'], ['auth', 'role:mahasiswa']);
    $router->post('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'validasiWajah'], ['auth', 'role:mahasiswa']);

    return $router;
}

function testSimulate(string $uri, array $sessionUserData, string $method = 'GET', array $postParams = [], bool $isAjax = false, array $extraSession = []): array
{
    $_SESSION = [];
    Session::set('user', $sessionUserData);
    foreach ($extraSession as $k => $v) {
        $_SESSION[$k] = $v;
    }

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
        'redirect' => $response->getHeaders()['Location'] ?? null,
    ];
}

// Data Dummy Mahasiswa 1 (Rizky Pratama - Biometrik Terdaftar) & Mahasiswa 3 (M. Bintang - Belum Terdaftar)
$userModel = new User();
$mhsRaw1 = $userModel->findByUsername('4122001');
$mhsUser1 = $userModel->getFullProfile((int)$mhsRaw1['id']);

$mhsRaw3 = $userModel->findByUsername('4122003');
$mhsUser3 = $userModel->getFullProfile((int)$mhsRaw3['id']);

// -----------------------------------------------------------------------------
// 1. UNIT TEST FACE SERVICE & BIOMETRIC MATCHER
// -----------------------------------------------------------------------------
echo "1. UNIT TEST FACE SERVICE & BIOMETRIC MATCHER\n";
echo "--------------------------------------------------------------------\n";

// Vektor 128 dimensi sampel
$vec1 = array_fill(0, 128, 0.05);
$vec2 = array_fill(0, 128, 0.05); // Vektor identik (distance 0)
$vec3 = array_fill(0, 128, 0.50); // Vektor jauh berbeda

// Test Euclidean Distance
$distZero = FaceService::calculateEuclideanDistance($vec1, $vec2);
assertTest("Euclidean distance dua vektor biometrik identik harus 0.0", $distZero == 0.0, "Dist: {$distZero}");

$distDiff = FaceService::calculateEuclideanDistance($vec1, $vec3);
assertTest("Euclidean distance dua vektor berbeda > 0.6", $distDiff > 0.6, "Dist: {$distDiff}");

// Test Distance to Similarity
$sim100 = FaceService::distanceToSimilarity(0.0);
assertTest("Distance 0.0 menghasilkan similarity 100.0%", $sim100 == 100.0);

// Test Compare Descriptors Match
$resMatch = FaceService::compareDescriptors($vec1, $vec2, 0.55);
assertTest("Vektor identik menghasilkan IS_MATCH = TRUE", $resMatch['is_match'] === true);

// Test Compare Descriptors Mismatch
$resMismatch = FaceService::compareDescriptors($vec1, $vec3, 0.55);
assertTest("Vektor jauh berbeda menghasilkan IS_MATCH = FALSE", $resMismatch['is_match'] === false);

echo "\n";

// -----------------------------------------------------------------------------
// 2. CONTROLLER INTEGRATION TEST (FACE VERIFICATION)
// -----------------------------------------------------------------------------
echo "2. CONTROLLER & GUARDING INTEGRATION TEST\n";
echo "--------------------------------------------------------------------\n";

$jadwalModel = new Jadwal();
$schedules1  = $jadwalModel->getStudentSchedules((int)$mhsUser1['mahasiswa_id']);

if (!empty($schedules1)) {
    $jadwal1 = $schedules1[0];
    $jadwalId = (int)$jadwal1['id'];
    $hariJadwal = $jadwal1['hari'];

    // Test 1: GET /mahasiswa/absensi/wajah/{id} TANPA Session Geolocation -> Harus DITOLAK (Redirect 302)
    $_SESSION = [];
    Session::set('user', $mhsUser1);
    $resNoGeo = testSimulate("/mahasiswa/absensi/wajah/{$jadwalId}?hari={$hariJadwal}", $mhsUser1, 'GET');
    assertTest("GET halaman Wajah TANPA session Geolocation DITOLAK (Redirect 302 ke GPS)", $resNoGeo['status'] === 302);

    // Test 2: GET /mahasiswa/absensi/wajah/{id} DENGAN Session Geolocation Valid -> Harus BERHASIL (HTTP 200)
    $geoSess1 = [
        "geo_verified_{$jadwalId}" => [
            'latitude'     => -6.7441584,
            'longitude'    => 108.5441566,
            'distance'     => 12.5,
            'verified_at'  => date('Y-m-d H:i:s'),
            'mahasiswa_id' => (int)$mhsUser1['mahasiswa_id'],
            'jadwal_id'    => $jadwalId
        ]
    ];

    $resWithGeo = testSimulate("/mahasiswa/absensi/wajah/{$jadwalId}?hari={$hariJadwal}", $mhsUser1, 'GET', [], false, $geoSess1);
    assertTest("GET halaman Wajah DENGAN session Geolocation BERHASIL (HTTP 200)", $resWithGeo['status'] === 200);
    assertTest("Render halaman memuat komponen Kamera & AI Face Verification", str_contains($resWithGeo['output'], 'Verifikasi Wajah AI Biometrik'));

    // Test 3: POST Verifikasi Wajah — Mahasiswa Tanpa Data Biometrik (NIM 4122003) -> DITOLAK (HTTP 422)
    $geoSess3 = [
        "geo_verified_{$jadwalId}" => [
            'latitude'     => -6.7441584,
            'longitude'    => 108.5441566,
            'distance'     => 10.0,
            'mahasiswa_id' => (int)$mhsUser3['mahasiswa_id'],
            'jadwal_id'    => $jadwalId
        ]
    ];

    $sampleBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $resNoBio = testSimulate("/mahasiswa/absensi/wajah/{$jadwalId}?hari={$hariJadwal}", $mhsUser3, 'POST', [
        'image' => $sampleBase64
    ], true, $geoSess3);

    assertTest("POST Verifikasi Wajah Mahasiswa tanpa data biometrik DITOLAK (HTTP 422)", $resNoBio['status'] === 422);
    $jsonNoBio = json_decode($resNoBio['output'], true);
    assertTest("Pesan error memberitahu data biometrik belum terdaftar", str_contains($jsonNoBio['message'] ?? '', 'belum terdaftar'));

    // Test 4: POST Verifikasi Wajah — Mahasiswa Valid (Rizky Pratama) -> BERHASIL (HTTP 200 & JSON Success)
    $resValidFace = testSimulate("/mahasiswa/absensi/wajah/{$jadwalId}?hari={$hariJadwal}", $mhsUser1, 'POST', [
        'image' => $sampleBase64
    ], true, $geoSess1);

    assertTest("POST Verifikasi Wajah Mahasiswa terdaftar BERHASIL (HTTP 200)", $resValidFace['status'] === 200);
    $jsonValidFace = json_decode($resValidFace['output'], true);
    assertTest("Response JSON success = true", isset($jsonValidFace['success']) && $jsonValidFace['success'] === true);
    assertTest("Verifikasi session face_verified_{jadwal_id} terbuat", !empty($_SESSION["face_verified_{$jadwalId}"]));

} else {
    echo "  [WARN] Tidak ada data jadwal untuk testing controller.\n";
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "TEST RESULTS: {$passedCount} / {$totalTests} PASSED\n";
echo "--------------------------------------------------------------------\n\n";

if ($passedCount === $totalTests) {
    echo "🎉 SELAMAT! Seluruh pengujian AI Face Verification BERHASIL (100% PASS).\n";
    exit(0);
} else {
    echo "⚠️ Terdapat " . ($totalTests - $passedCount) . " test yang gagal. Silakan perbaiki!\n";
    exit(1);
}
