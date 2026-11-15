<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: End-to-End Alur Lengkap Absensi Mahasiswa (Prompt 8)
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\Mahasiswa\AbsensiController;
use App\Models\User;
use App\Models\Jadwal;
use App\Models\Absensi;
use App\Models\Pertemuan;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - END-TO-END FULL ABSENSI FLOW TEST SUITE\n";
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

    $router->get('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'halamanWajah'], ['auth', 'role:mahasiswa']);
    $router->post('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'validasiWajah'], ['auth', 'role:mahasiswa']);

    $router->get('/mahasiswa/absensi/kegiatan/{id}', [AbsensiController::class, 'halamanKegiatan'], ['auth', 'role:mahasiswa']);
    $router->post('/mahasiswa/absensi/kegiatan/{id}', [AbsensiController::class, 'validasiKegiatan'], ['auth', 'role:mahasiswa']);

    $router->get('/mahasiswa/absensi/sukses/{id}', [AbsensiController::class, 'halamanSukses'], ['auth', 'role:mahasiswa']);

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

// User data dummy (Mahasiswa 1 Rizky Pratama)
$userModel = new User();
$mhsRaw = $userModel->findByUsername('4122001');
$mhsUser = $userModel->getFullProfile((int)$mhsRaw['id']);
$mahasiswaId = (int)$mhsUser['mahasiswa_id'];

$jadwalModel = new Jadwal();
$schedules   = $jadwalModel->getStudentSchedules($mahasiswaId);

if (!empty($schedules)) {
    $jadwal = $schedules[0];
    $jadwalId = (int)$jadwal['id'];
    $hariJadwal = $jadwal['hari'];

    // -------------------------------------------------------------------------
    // 1. STRICT GUARDING TESTS (TAHAP 4 HABIS / SYARAT BELUM TERPENUHI)
    // -------------------------------------------------------------------------
    echo "1. STRICT GUARDING & VALIDATION TESTS (TAHAP 4)\n";
    echo "--------------------------------------------------------------------\n";

    // Test 1: GET Halaman Kegiatan TANPA Geolocation & TANPA Face Verification -> DITOLAK (Redirect 302 ke Geolocation)
    $resNoAll = testSimulate("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'GET');
    assertTest("GET Halaman Kegiatan TANPA Geolocation & Face AI DITOLAK (Redirect 302)", $resNoAll['status'] === 302);

    // Test 2: GET Halaman Kegiatan HANYA Geolocation (TANPA Face AI) -> DITOLAK (Redirect 302 ke Wajah)
    $geoOnly = [
        "geo_verified_{$jadwalId}" => [
            'latitude' => -6.7441584, 'longitude' => 108.5441566, 'distance' => 10.0, 'mahasiswa_id' => $mahasiswaId
        ]
    ];
    $resNoFace = testSimulate("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'GET', [], false, $geoOnly);
    assertTest("GET Halaman Kegiatan TANPA Face AI DITOLAK (Redirect 302 ke Wajah)", $resNoFace['status'] === 302);

    // Test 3: GET Halaman Kegiatan DENGAN Geolocation + DENGAN Face AI -> BERHASIL (HTTP 200)
    $geoAndFace = [
        "geo_verified_{$jadwalId}" => [
            'latitude' => -6.7441584, 'longitude' => 108.5441566, 'distance' => 15.0, 'mahasiswa_id' => $mahasiswaId
        ],
        "face_verified_{$jadwalId}" => [
            'mahasiswa_id' => $mahasiswaId, 'similarity' => 92.5, 'distance' => 0.05, 'face_verified' => 1
        ]
    ];
    $resReady = testSimulate("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'GET', [], false, $geoAndFace);
    assertTest("GET Halaman Kegiatan DENGAN Geolocation + Face AI BERHASIL (HTTP 200)", $resReady['status'] === 200);
    assertTest("Render halaman memuat komponen Foto Kegiatan Kelas", str_contains($resReady['output'], 'Dokumentasi Foto Kegiatan Kelas'));

    // Test 4: POST Commit Absensi TANPA Foto -> DITOLAK (HTTP 422)
    $resNoPhoto = testSimulate("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'POST', [
        'image' => ''
    ], true, $geoAndFace);
    assertTest("POST Commit Absensi TANPA foto DITOLAK (HTTP 422)", $resNoPhoto['status'] === 422);

    echo "\n";

    // -------------------------------------------------------------------------
    // 2. END-TO-END ABSENSI SUCCESSFUL SUBMISSION
    // -------------------------------------------------------------------------
    echo "2. END-TO-END ABSENSI SUCCESSFUL SUBMISSION TO DATABASE\n";
    echo "--------------------------------------------------------------------\n";

    // Pastikan data absensi historis seeder (pertemuan 1 & 2) tetap ada
    $db = \App\Core\Database::getConnection();
    $db->prepare("INSERT IGNORE INTO absensi (id, pertemuan_id, mahasiswa_id, waktu_absensi, status, latitude, longitude, jarak_meter, status_lokasi, face_verified, face_similarity, foto_kegiatan, device_info, ip_address) VALUES (1, 1, 1, '2025-09-08 08:05:22', 'hadir', -6.74416000, 108.54415800, 3.42, 'dalam_radius', 1, 0.9450, 'uploads/attendance/att_p1_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'), (4, 2, 1, '2025-09-15 08:03:10', 'hadir', -6.74415800, 108.54415600, 1.20, 'dalam_radius', 1, 0.9580, 'uploads/attendance/att_p2_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50')")->execute();

    // Hapus absensi tes sesi hari ini saja agar pengujian berjalan bersih tanpa menghapus seeder
    $db->prepare("DELETE FROM absensi WHERE mahasiswa_id = :mhs AND pertemuan_id IN (SELECT id FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE())")
       ->execute(['mhs' => $mahasiswaId, 'jdw' => $jadwalId]);

    $sampleBase64 = 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $resSubmit = testSimulate("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari={$hariJadwal}", $mhsUser, 'POST', [
        'image' => $sampleBase64
    ], true, $geoAndFace);

    assertTest("POST Submit Absensi Lengkap (Tahap 1-4) BERHASIL (HTTP 200 OK)", $resSubmit['status'] === 200);
    $jsonSubmit = json_decode($resSubmit['output'], true);
    assertTest("Response JSON success = true", isset($jsonSubmit['success']) && $jsonSubmit['success'] === true);

    // Verifikasi Database Record
    $absensiModel = new Absensi();
    $history = $absensiModel->getStudentHistory($mahasiswaId, 1);
    assertTest("Record absensi baru berhasil tersimpan di tabel database 'absensi'", !empty($history));
    
    if (!empty($history)) {
        $rec = $history[0];
        assertTest("Data status lokasi tersimpan sebagai 'dalam_radius'", $rec['status_lokasi'] === 'dalam_radius');
        assertTest("Data face_verified tersimpan 1", (int)$rec['face_verified'] === 1);
        assertTest("Data path foto_kegiatan tersimpan di uploads/kegiatan/", str_contains($rec['foto_kegiatan'] ?? '', 'uploads/kegiatan/'));
    }

    // Test GET Halaman Sukses Presensi
    $resSuksesPage = testSimulate("/mahasiswa/absensi/sukses/{$jadwalId}", $mhsUser, 'GET');
    assertTest("GET Halaman Rekap Sukses Presensi BERHASIL (HTTP 200)", $resSuksesPage['status'] === 200);
    assertTest("Halaman rekap memuat pesan 'Presensi Berhasil Dicatat!'", str_contains($resSuksesPage['output'], 'Presensi Berhasil Dicatat!'));

} else {
    echo "  [WARN] Tidak ada data jadwal untuk testing end-to-end.\n";
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "TEST RESULTS: {$passedCount} / {$totalTests} PASSED\n";
echo "--------------------------------------------------------------------\n\n";

if ($passedCount === $totalTests) {
    // Cleanup data absensi test sesi hari ini agar database kembali ke kondisi seeder awal
    $db = \App\Core\Database::getConnection();
    $db->prepare("DELETE FROM absensi WHERE mahasiswa_id = :mhs AND pertemuan_id IN (SELECT id FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE())")
       ->execute(['mhs' => $mahasiswaId, 'jdw' => $jadwalId]);
    $db->prepare("DELETE FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE()")->execute(['jdw' => $jadwalId]);
    $db->prepare("INSERT IGNORE INTO absensi (id, pertemuan_id, mahasiswa_id, waktu_absensi, status, latitude, longitude, jarak_meter, status_lokasi, face_verified, face_similarity, foto_kegiatan, device_info, ip_address) VALUES (1, 1, 1, '2025-09-08 08:05:22', 'hadir', -6.74416000, 108.54415800, 3.42, 'dalam_radius', 1, 0.9450, 'uploads/attendance/att_p1_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'), (4, 2, 1, '2025-09-15 08:03:10', 'hadir', -6.74415800, 108.54415600, 1.20, 'dalam_radius', 1, 0.9580, 'uploads/attendance/att_p2_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50')")->execute();

    echo "🎉 SELAMAT! Seluruh pengujian End-to-End Alur Absensi BERHASIL (100% PASS).\n";
    exit(0);
} else {
    echo "⚠️ Terdapat " . ($totalTests - $passedCount) . " test yang gagal. Silakan perbaiki!\n";
    exit(1);
}
