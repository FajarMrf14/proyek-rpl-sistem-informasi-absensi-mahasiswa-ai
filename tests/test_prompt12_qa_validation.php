<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Prompt 12 — Final QA, Bug Fixing, dan Validasi Sistem
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Database;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Absensi;
use App\Models\Pertemuan;
use App\Models\IzinSakit;
use App\Services\FaceService;
use App\Helpers\GeoHelper;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON — PROMPT 12 FINAL QA & VALIDASI SISTEM\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;
$blockedCount = 0;
$results = [];

function recordTest(string $category, string $description, string $status, string $details = ''): void
{
    global $passCount, $failCount, $blockedCount, $results;
    if ($status === 'PASS') {
        $passCount++;
        echo "  [PASS] [{$category}] {$description}\n";
    } elseif ($status === 'FAIL') {
        $failCount++;
        echo "  [FAIL] [{$category}] {$description} - {$details}\n";
    } else {
        $blockedCount++;
        echo "  [BLOCKED] [{$category}] {$description} - {$details}\n";
    }

    $results[] = [
        'category' => $category,
        'description' => $description,
        'status' => $status,
        'details' => $details
    ];
}

// -----------------------------------------------------------------------------
// Helper Router Dispatcher
// -----------------------------------------------------------------------------
function dispatchApp(string $uri, ?array $user = null, string $method = 'GET', array $post = [], bool $isAjax = false, array $extraSession = [], ?string $customCsrf = null): array
{
    $_SESSION = [];
    if ($user) {
        Session::set('user', $user);
    }
    foreach ($extraSession as $k => $v) {
        $_SESSION[$k] = $v;
    }

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    if ($method === 'POST') {
        if ($customCsrf !== null) {
            $post['_csrf_token'] = $customCsrf;
        } elseif (!isset($post['_csrf_token'])) {
            $post['_csrf_token'] = Session::generateCsrfToken();
        }
    }

    $_POST = $post;

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
    $router = new Router();
    $router->registerMiddleware('auth', \App\Middleware\AuthMiddleware::class);
    $router->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class);
    $router->registerMiddleware('role', \App\Middleware\RoleMiddleware::class);

    require dirname(__DIR__) . '/app/Config/Routes.php';

    ob_start();
    try {
        $router->dispatch(new Request(), $response);
    } catch (\Throwable $e) {
        ob_end_clean();
        return [
            'status' => 500,
            'output' => $e->getMessage(),
            'redirect' => null,
            'flash' => null
        ];
    }
    $output = ob_get_clean();

    return [
        'status'   => $response->getStatusCode(),
        'headers'  => $response->getHeaders(),
        'output'   => $output,
        'flash'    => Session::getFlash('error') ?? Session::getFlash('success') ?? Session::getFlash('info'),
        'redirect' => $response->getHeaders()['Location'] ?? null,
    ];
}

// Load test users dari database
$userModel = new User();
$userMhs1 = $userModel->getFullProfile(4); // ID 4: Rizky Pratama (NIM 4122001)
$userMhs2 = $userModel->getFullProfile(5); // ID 5: Putri Lestari (NIM 4122002)
$userDosen = $userModel->getFullProfile(2); // ID 2: Dosen Dr. Ahmad Fauzi (NIDN 0412088501)
$userAdmin = $userModel->getFullProfile(1); // ID 1: Admin

echo "--- 1. AUDIT ROLE & ACCESS CONTROL (RBAC) ---\n";
// 1.1 Mahasiswa dashboard
$res = dispatchApp('/mahasiswa/dashboard', $userMhs1);
recordTest('RBAC', 'Mahasiswa dapat mengakses dashboard sendiri', $res['status'] === 200 ? 'PASS' : 'FAIL');

// 1.2 Mahasiswa ke Dosen & Admin dashboard
$res = dispatchApp('/dosen/dashboard', $userMhs1);
recordTest('RBAC', 'Mahasiswa diblokir dari dashboard Dosen (403)', $res['status'] === 403 ? 'PASS' : 'FAIL');

$res = dispatchApp('/admin/dashboard', $userMhs1);
recordTest('RBAC', 'Mahasiswa diblokir dari dashboard Admin (403)', $res['status'] === 403 ? 'PASS' : 'FAIL');

// 1.3 Dosen dashboard
$res = dispatchApp('/dosen/dashboard', $userDosen);
recordTest('RBAC', 'Dosen dapat mengakses dashboard Dosen', $res['status'] === 200 ? 'PASS' : 'FAIL');

// 1.4 Dosen ke Mahasiswa & Admin dashboard
$res = dispatchApp('/mahasiswa/dashboard', $userDosen);
recordTest('RBAC', 'Dosen diblokir dari dashboard Mahasiswa (403)', $res['status'] === 403 ? 'PASS' : 'FAIL');

$res = dispatchApp('/admin/dashboard', $userDosen);
recordTest('RBAC', 'Dosen diblokir dari dashboard Admin (403)', $res['status'] === 403 ? 'PASS' : 'FAIL');

// 1.5 Admin dashboard
$res = dispatchApp('/admin/dashboard', $userAdmin);
recordTest('RBAC', 'Admin dapat mengakses dashboard Admin', $res['status'] === 200 ? 'PASS' : 'FAIL');

// 1.6 Guest redirect
$res = dispatchApp('/mahasiswa/dashboard', null);
recordTest('RBAC', 'Guest diblokir dan dialihkan ke login (302)', $res['status'] === 302 && str_contains($res['redirect'] ?? '', 'login') ? 'PASS' : 'FAIL');

echo "\n--- 2. AUDIT KEAMANAN (SECURITY & INTEGRITY) ---\n";
// 2.1 CSRF Protection (Form submission ditolak & dialihkan dengan flash error, AJAX ditolak HTTP 403)
$resForm = dispatchApp('/login', null, 'POST', ['username' => 'admin', 'password' => 'admin123'], false, [], 'invalid_token');
$resAjax = dispatchApp('/login', null, 'POST', ['username' => 'admin', 'password' => 'admin123'], true, [], 'invalid_token');
$csrfOk = ($resForm['status'] === 302 && str_contains($resForm['flash'] ?? '', 'kedaluwarsa')) && ($resAjax['status'] === 403);
recordTest('Security', 'POST dengan CSRF token tidak valid ditolak (Redirect 302 / AJAX 403)', $csrfOk ? 'PASS' : 'FAIL');

// 2.2 Password Hash Verification
$adminRow = $userModel->findByUsername('admin');
$pwOk = password_verify('admin123', $adminRow['password_hash']);
recordTest('Security', 'Password tersimpan menggunakan Bcrypt yang valid', $pwOk ? 'PASS' : 'FAIL');

// 2.3 IDOR / Cross-student access: Mahasiswa mencoba akses jadwal yang bukan miliknya
$res = dispatchApp('/mahasiswa/absensi/proses/9999', $userMhs1);
recordTest('Security', 'Akses ke jadwal tidak valid/bukan KRS ditolak (Redirect 302)', $res['status'] === 302 ? 'PASS' : 'FAIL');

// 2.4 Dosen IDOR: Dosen 1 mencoba melihat kelas dosen 2 (Jadwal 3)
$res = dispatchApp('/dosen/kelas/3', $userDosen); // Jadwal 3 diampu dosen 2 (Siti Nurhaliza)
recordTest('Security', 'Dosen diblokir melihat kelas dosen lain (Redirect 302)', $res['status'] === 302 ? 'PASS' : 'FAIL');

echo "\n--- 3. AUDIT ALUR ABSENSI MAHASISWA & GUARDING ---\n";
$jadwalId = 1; // Jadwal Senin TIF-301

// 3.1 Penolakan jadwal di luar hari
$res = dispatchApp("/mahasiswa/absensi/proses/{$jadwalId}?hari=Selasa", $userMhs1);
recordTest('Absensi Alur', 'Absensi di luar hari jadwal ditolak dengan pesan error', $res['status'] === 302 && !empty($res['flash']) ? 'PASS' : 'FAIL');

// 3.2 GPS Di Luar Radius
$res = dispatchApp("/mahasiswa/absensi/geolocation/{$jadwalId}?hari=Senin", $userMhs1, 'POST', [
    'latitude' => -6.900000,
    'longitude' => 107.600000,
    'accuracy' => 10.0
], true);
recordTest('Absensi Alur', 'GPS di luar radius ditolak (HTTP 422)', $res['status'] === 422 ? 'PASS' : 'FAIL');

// 3.3 GPS Tanpa Koordinat
$res = dispatchApp("/mahasiswa/absensi/geolocation/{$jadwalId}?hari=Senin", $userMhs1, 'POST', [
    'latitude' => '',
    'longitude' => '',
    'accuracy' => 0
], true);
recordTest('Absensi Alur', 'GPS tanpa koordinat ditolak (HTTP 422)', $res['status'] === 422 ? 'PASS' : 'FAIL');

// 3.4 GPS Berhasil dalam Radius
$res = dispatchApp("/mahasiswa/absensi/geolocation/{$jadwalId}?hari=Senin", $userMhs1, 'POST', [
    'latitude' => -6.74415840,
    'longitude' => 108.54415660,
    'accuracy' => 10.0
], true);
recordTest('Absensi Alur', 'GPS dalam radius diterima (HTTP 200)', $res['status'] === 200 ? 'PASS' : 'FAIL');

// 3.5 Guarding Tahap 3 (Wajah tanpa GPS)
$res = dispatchApp("/mahasiswa/absensi/wajah/{$jadwalId}?hari=Senin", $userMhs1);
recordTest('Absensi Alur', 'Bypass langsung ke Wajah tanpa GPS ditolak (Redirect 302)', $res['status'] === 302 ? 'PASS' : 'FAIL');

// 3.6 Guarding Tahap 4 (Kegiatan tanpa Wajah AI)
$geoSession = [
    "geo_verified_{$jadwalId}" => [
        'latitude' => -6.7441584, 'longitude' => 108.5441566, 'distance' => 5.0, 'mahasiswa_id' => $userMhs1['mahasiswa_id']
    ]
];
$res = dispatchApp("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari=Senin", $userMhs1, 'GET', [], false, $geoSession);
recordTest('Absensi Alur', 'Bypass langsung ke Foto Kegiatan tanpa Wajah AI ditolak (Redirect 302)', $res['status'] === 302 ? 'PASS' : 'FAIL');

// 3.7 AI Verification: Sesi wajah lolos
$faceSession = [
    "face_verified_{$jadwalId}" => [
        'mahasiswa_id' => $userMhs1['mahasiswa_id'], 'similarity' => 95.0, 'distance' => 0.05, 'face_verified' => 1
    ]
];
$geoAndFaceSession = array_merge($geoSession, $faceSession);

// 3.8 Submit Absensi Lengkap Berhasil
$db = Database::getConnection();
$db->prepare("INSERT IGNORE INTO absensi (id, pertemuan_id, mahasiswa_id, waktu_absensi, status, latitude, longitude, jarak_meter, status_lokasi, face_verified, face_similarity, foto_kegiatan, device_info, ip_address) VALUES (1, 1, 1, '2025-09-08 08:05:22', 'hadir', -6.74416000, 108.54415800, 3.42, 'dalam_radius', 1, 0.9450, 'uploads/attendance/att_p1_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'), (4, 2, 1, '2025-09-15 08:03:10', 'hadir', -6.74415800, 108.54415600, 1.20, 'dalam_radius', 1, 0.9580, 'uploads/attendance/att_p2_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50')")->execute();
$db->prepare("DELETE FROM absensi WHERE mahasiswa_id = :mhs AND pertemuan_id IN (SELECT id FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE())")
   ->execute(['mhs' => $userMhs1['mahasiswa_id'], 'jdw' => $jadwalId]);

$sampleBase64 = 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$res = dispatchApp("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari=Senin", $userMhs1, 'POST', [
    'image' => $sampleBase64
], true, $geoAndFaceSession);

recordTest('Absensi Alur', 'Submit absensi lengkap berhasil disimpan ke DB (HTTP 200)', $res['status'] === 200 ? 'PASS' : 'FAIL');

// 3.9 Double Submission Prevention: Percobaan absensi kedua kali untuk sesi/pertemuan yang sama
$resDouble = dispatchApp("/mahasiswa/absensi/kegiatan/{$jadwalId}?hari=Senin", $userMhs1, 'POST', [
    'image' => $sampleBase64
], true, $geoAndFaceSession);

recordTest('Absensi Alur', 'Pengiriman berulang (double submission) ditolak (HTTP 422)', $resDouble['status'] === 422 ? 'PASS' : 'FAIL');

// Cleanup data test absensi sesi hari ini saja agar tidak menghapus seeder
$db->prepare("DELETE FROM absensi WHERE mahasiswa_id = :mhs AND pertemuan_id IN (SELECT id FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE())")
   ->execute(['mhs' => $userMhs1['mahasiswa_id'], 'jdw' => $jadwalId]);
$db->prepare("DELETE FROM pertemuan_kuliah WHERE jadwal_id = :jdw AND tanggal = CURDATE()")->execute(['jdw' => $jadwalId]);
$db->prepare("INSERT IGNORE INTO absensi (id, pertemuan_id, mahasiswa_id, waktu_absensi, status, latitude, longitude, jarak_meter, status_lokasi, face_verified, face_similarity, foto_kegiatan, device_info, ip_address) VALUES (1, 1, 1, '2025-09-08 08:05:22', 'hadir', -6.74416000, 108.54415800, 3.42, 'dalam_radius', 1, 0.9450, 'uploads/attendance/att_p1_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'), (4, 2, 1, '2025-09-15 08:03:10', 'hadir', -6.74415800, 108.54415600, 1.20, 'dalam_radius', 1, 0.9580, 'uploads/attendance/att_p2_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50')")->execute();

echo "\n--- 4. PERHITUNGAN KEHADIRAN & STATUS AKADEMIK ---\n";
// 4.1 Statistik Kehadiran Mahasiswa
$absensiModel = new Absensi();
$statsMhs1 = $absensiModel->getStudentOverallStats((int)$userMhs1['mahasiswa_id']);
recordTest('Perhitungan', 'Statistik total kehadiran Mahasiswa 1 valid (persentase numeric)', is_numeric($statsMhs1['persentase_kehadiran']) ? 'PASS' : 'FAIL');

// 4.2 Rekapitulasi View DB
$sqlView = "SELECT * FROM v_rekap_kehadiran_mahasiswa WHERE mahasiswa_id = :mid LIMIT 1";
$rekapView = $absensiModel->query($sqlView, ['mid' => (int)$userMhs1['mahasiswa_id']]);
recordTest('Perhitungan', 'Database VIEW v_rekap_kehadiran_mahasiswa menghasilkan data valid', !empty($rekapView) ? 'PASS' : 'FAIL');

// 4.3 Dosen Flow: Dosen monitoring kelas yang diampu
$res = dispatchApp('/dosen/kelas', $userDosen);
recordTest('Dosen Flow', 'Dosen dapat melihat daftar kelas yang diampu', $res['status'] === 200 && str_contains($res['output'], 'TIF-301') ? 'PASS' : 'FAIL');

$res = dispatchApp('/dosen/kelas/1', $userDosen);
recordTest('Dosen Flow', 'Dosen dapat melihat rekap kehadiran mahasiswa per kelas', $res['status'] === 200 && str_contains($res['output'], 'Rekap Kehadiran') ? 'PASS' : 'FAIL');
recordTest('Dosen Flow', 'Dosen tidak melakukan absensi untuk mahasiswa', str_contains($res['output'], 'Dosen <strong>tidak melakukan absensi</strong>') ? 'PASS' : 'FAIL');

// 4.4 Admin Flow: Admin manajemen izin & sakit
$res = dispatchApp('/admin/izin', $userAdmin);
recordTest('Admin Flow', 'Admin dapat melihat pengajuan izin & sakit mahasiswa', $res['status'] === 200 && str_contains($res['output'], 'Manajemen Izin & Sakit') ? 'PASS' : 'FAIL');

echo "\n--- 5. INTEGRASI LAYANAN PENDUKUNG (GPS & AI VERIFICATION) ---\n";
// 5.1 Geofencing Haversine Helper
$dist = GeoHelper::calculateDistance(-6.74415840, 108.54415660, -6.74415840, 108.54415660);
recordTest('Integrasi GPS', 'Formula Haversine menghitung koordinat identik = 0.0m', $dist === 0.0 ? 'PASS' : 'FAIL');

// 5.2 Geofencing Radius Verification
$campus = GeoHelper::getCampusConfig();
$checkIn = GeoHelper::checkGeofence($campus['latitude'], $campus['longitude'], $campus['latitude'], $campus['longitude'], $campus['radius_meters']);
recordTest('Integrasi GPS', 'Titik pusat kampus terdeteksi dalam radius (is_valid = true)', $checkIn['is_valid'] === true ? 'PASS' : 'FAIL');

// 5.3 Biometric Matcher Math (Euclidean Distance & Similarity)
$vecA = array_fill(0, 128, 0.1);
$vecB = array_fill(0, 128, 0.1);
$distMatch = FaceService::calculateEuclideanDistance($vecA, $vecB);
recordTest('Integrasi AI', 'Biometric matcher Euclidean Distance vektor identik = 0.0', $distMatch === 0.0 ? 'PASS' : 'FAIL');

// 5.4 AI Face Recognition Model Weights Status
$modelFiles = glob(dirname(__DIR__) . '/public/models/*.json');
$hasLocalWeights = !empty($modelFiles);
if ($hasLocalWeights) {
    recordTest('Integrasi AI', 'Bobot model AI face-api tersedia secara lokal', 'PASS');
} else {
    recordTest('Integrasi AI', 'Bobot model AI face-api lokal (public/models/*.json)', 'BLOCKED', 'public/models hanya berisi .gitkeep. Sistem beroperasi dalam mode hybrid AI client-server fallback.');
}

echo "\n====================================================================\n";
echo "HASIL AKHIR: {$passCount} PASS | {$failCount} FAIL | {$blockedCount} BLOCKED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
