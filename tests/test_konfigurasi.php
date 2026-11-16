<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Comprehensive Konfigurasi Feature & Security Automated Test Suite
 * ==============================================================================
 */

define('TESTING_MODE', true);

// Buffer seluruh output agar session_start() tetap dapat dijalankan di CLI
// setelah header laporan dicetak.
ob_start();

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Database;
use App\Config\App as AppConfig;
use App\Models\AppSetting;
use App\Models\SettingKampus;
use App\Models\TahunAkademik;
use App\Models\AuditLog;
use App\Controllers\Admin\KonfigurasiController;
use App\Services\NvidiaChatbotService;
use App\Helpers\GeoHelper;
use App\Services\FaceService;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - KONFIGURASI SYSTEM & SECURITY TEST SUITE\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description} " . ($details !== '' ? "({$details})" : '') . "\n";
    }
}

// User fixtures
$adminUser = [
    'id' => 1,
    'username' => 'admin',
    'nama_lengkap' => 'Administrator Utama',
    'role' => 'admin',
    'email' => 'admin@ikmi.ac.id'
];

$mhsUser = [
    'id' => 4,
    'username' => '4122001',
    'nama_lengkap' => 'Rizky Pratama',
    'role' => 'mahasiswa',
    'email' => 'rizky.pratama@mhs.ikmi.ac.id'
];

$dosenUser = [
    'id' => 2,
    'username' => '0412088501',
    'nama_lengkap' => 'Dr. Ahmad Fauzi',
    'role' => 'dosen',
    'email' => 'ahmad.fauzi@ikmi.ac.id'
];

// Helper: Create Router with full middlewares and routes
function buildRouter(): Router
{
    $router = new Router();
    $router->registerMiddleware('auth', \App\Middleware\AuthMiddleware::class);
    $router->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class);
    $router->registerMiddleware('role', \App\Middleware\RoleMiddleware::class);

    $router->get('/admin/konfigurasi', [KonfigurasiController::class, 'index'], ['auth', 'role:admin']);
    $router->post('/admin/konfigurasi/umum', [KonfigurasiController::class, 'updateUmum'], ['auth', 'role:admin']);
    $router->post('/admin/konfigurasi/chatbot', [KonfigurasiController::class, 'updateChatbot'], ['auth', 'role:admin']);
    $router->post('/admin/konfigurasi/test-ai', [KonfigurasiController::class, 'testChatbotConnection'], ['auth', 'role:admin']);
    $router->post('/admin/konfigurasi/akademik', [KonfigurasiController::class, 'updateAkademik'], ['auth', 'role:admin']);
    $router->post('/admin/konfigurasi/sistem', [KonfigurasiController::class, 'updateSistem'], ['auth', 'role:admin']);

    return $router;
}

// -----------------------------------------------------------------------------
// GROUP 1: ROLE-BASED ACCESS CONTROL (RBAC) & BACKEND AUTHORIZATION
// -----------------------------------------------------------------------------
echo "--- 1. PENGUJIAN OTORISASI BACKEND & RBAC MENU KONFIGURASI ---\n";

// Test 1.1: Guest ditolak & dialihkan ke /login
Session::start();
Session::destroy();
Session::start();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi';
$req1 = new Request();
$res1 = new Response();
$router1 = buildRouter();
$router1->dispatch($req1, $res1);
assertTest('Guest mengakses /admin/konfigurasi dialihkan (Redirect ke /login)', $res1->getStatusCode() === 302);

// Test 1.2: Mahasiswa ditolak (HTTP 403 Forbidden)
Session::set('user', $mhsUser);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi';
$req2 = new Request();
$res2 = new Response();
$router2 = buildRouter();
ob_start();
$router2->dispatch($req2, $res2);
ob_end_clean();
assertTest('Mahasiswa mengakses /admin/konfigurasi DITOLAK (HTTP 403 Forbidden)', $res2->getStatusCode() === 403);

// Test 1.3: Dosen ditolak (HTTP 403 Forbidden)
Session::set('user', $dosenUser);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi';
$req3 = new Request();
$res3 = new Response();
$router3 = buildRouter();
ob_start();
$router3->dispatch($req3, $res3);
ob_end_clean();
assertTest('Dosen mengakses /admin/konfigurasi DITOLAK (HTTP 403 Forbidden)', $res3->getStatusCode() === 403);

// Test 1.4: Admin berhasil mengakses (HTTP 200)
Session::set('user', $adminUser);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi';
$req4 = new Request();
$res4 = new Response();
$router4 = buildRouter();
ob_start();
$router4->dispatch($req4, $res4);
$output4 = ob_get_clean();
assertTest('Admin mengakses /admin/konfigurasi BERHASIL (HTTP 200)', $res4->getStatusCode() === 200);
assertTest('Tampilan menu konfigurasi memuat 4 kategori (Umum, Chatbot AI, Akademik, Sistem)', 
    str_contains($output4, 'Pengaturan Umum') && 
    str_contains($output4, 'Chatbot AI') && 
    str_contains($output4, 'Aturan Akademik') && 
    str_contains($output4, 'Pengaturan Sistem')
);

$envKeyForLeakTest = (string) \App\Core\Env::get('GEMINI_API_KEY', '');
assertTest('Halaman konfigurasi tidak membocorkan API key .env ke HTML', 
    $envKeyForLeakTest === '' || !str_contains($output4, $envKeyForLeakTest)
);

// -----------------------------------------------------------------------------
// GROUP 2: PENGATURAN UMUM (GENERAL SETTINGS) PERSISTENCE & VALIDATION
// -----------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN PENGATURAN UMUM (PERSISTENSI & VALIDASI) ---\n";

$originalAppName = AppSetting::get('app_name', 'Absensi Mahasiswa AI - STMIK IKMI Cirebon');
$originalAppDescription = AppSetting::get('app_description', 'Sistem Absensi Mahasiswa Berbasis Web dengan AI - STMIK IKMI Cirebon');
$campusOriginal = (new SettingKampus())->getActiveSetting();
$testAppName = 'Sistem Presensi Cerdas STMIK IKMI 2026';

// Test 2.1: Update Pengaturan Umum via Controller
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/umum';
$_POST = [
    '_csrf_token'     => Session::generateCsrfToken(),
    'app_name'        => $testAppName,
    'app_description' => 'Aplikasi Presensi AI STMIK IKMI Cirebon',
    'campus_name'     => 'STMIK IKMI Cirebon Kampus Utama',
    'contact_email'   => 'pusat.bantuan@ikmi.ac.id',
    'app_timezone'    => 'Asia/Jakarta',
];

$req5 = new Request();
$res5 = new Response();
$router5 = buildRouter();
$router5->dispatch($req5, $res5);

// Cek persistensi di database
AppSetting::clearCache();
$savedAppName = AppSetting::get('app_name');
$savedEmail = AppSetting::get('contact_email');
$savedCampus = AppSetting::get('campus_name');

assertTest("Pengaturan nama aplikasi berhasil disimpan persisten ('{$savedAppName}')", $savedAppName === $testAppName);
assertTest("Pengaturan email kontak berhasil disimpan persisten ('{$savedEmail}')", $savedEmail === 'pusat.bantuan@ikmi.ac.id');
assertTest("Fungsi helper app_name() merefleksikan nilai konfigurasi aktif", app_name() === $testAppName);
assertTest("Fungsi helper campus_name() merefleksikan nilai institusi baru", campus_name() === 'STMIK IKMI Cirebon Kampus Utama');

// Test 2.2: Validasi email tidak valid ditolak
$_POST['contact_email'] = 'bukan-email-valid';
$res6 = new Response();
$router6 = buildRouter();
$router6->dispatch(new Request(), $res6);
assertTest("Email tidak valid ditolak oleh validasi backend", has_flash('error'));

// Kembalikan seluruh data uji ke kondisi semula
AppSetting::set('app_name', $originalAppName, 'umum', false, 1);
AppSetting::set('app_description', $originalAppDescription, 'umum', false, 1);
AppSetting::set('campus_name', 'STMIK IKMI Cirebon', 'umum', false, 1);
AppSetting::set('contact_email', 'info@ikmi.ac.id', 'umum', false, 1);

$campusCleanup = new SettingKampus();
if ($campusOriginal) {
    $campusCleanup->update($campusOriginal['id'], [
        'nama_kampus'                => $campusOriginal['nama_kampus'],
        'alamat'                     => $campusOriginal['alamat'],
        'latitude'                   => $campusOriginal['latitude'],
        'longitude'                  => $campusOriginal['longitude'],
        'radius_meter'               => $campusOriginal['radius_meter'],
        'toleransi_wajah_similarity' => $campusOriginal['toleransi_wajah_similarity'],
        'updated_by'                 => $campusOriginal['updated_by'],
    ]);
}

AppSetting::clearCache();

// -----------------------------------------------------------------------------
// GROUP 3: KONFIGURASI CHATBOT AI & KEAMANAN API KEY
// -----------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN KONFIGURASI CHATBOT AI & KEAMANAN API KEY ---\n";

// Test 3.1: Enkripsi & Masking API Key
$testSecretKey = 'AQ.TestGeminiSecretKey_ABC123XYZ';
$encryptedSecret = AppSetting::encryptValue($testSecretKey);
$decryptedSecret = AppSetting::decryptValue($encryptedSecret);
$maskedSecret = AppSetting::maskSecret($testSecretKey);

assertTest('API Key berhasil dienkripsi (Ciphertext tidak sama dengan plaintext)', $encryptedSecret !== $testSecretKey);
assertTest('API Key berhasil didekripsi dengan integritas HMAC sempurna', $decryptedSecret === $testSecretKey);
assertTest("Masking API Key melindungi kerahasiaan ('{$maskedSecret}')", 
    !str_contains($maskedSecret, 'SecretKey_ABC123') && str_starts_with($maskedSecret, 'AQ.T') && str_ends_with($maskedSecret, '3XYZ')
);

// Simpan rahasia sementara agar pengujian kebocoran benar-benar menguji nilai terenkripsi.
$pdoSecretTest = Database::getConnection();
$origSecretRow = $pdoSecretTest->query(
    "SELECT setting_value, is_encrypted, updated_by, updated_at
       FROM app_settings WHERE setting_key = 'gemini_api_key' LIMIT 1"
)->fetch(\PDO::FETCH_ASSOC) ?: null;

AppSetting::set('gemini_api_key', $testSecretKey, 'chatbot', true, 1);
AppSetting::clearCache();
$groupedForSecretTest = AppSetting::getAllGrouped();
$secretEntry = $groupedForSecretTest['chatbot']['gemini_api_key'] ?? [];
$groupedJson = json_encode($groupedForSecretTest, JSON_UNESCAPED_UNICODE);

assertTest('AppSetting::getAllGrouped() tidak mengembalikan nilai rahasia mentah',
    array_key_exists('value', $secretEntry)
    && $secretEntry['value'] === null
    && ($secretEntry['is_configured'] ?? false) === true
    && !str_contains((string)($secretEntry['masked'] ?? ''), 'SecretKey_ABC123')
    && !str_contains($groupedJson, $testSecretKey)
);

// Kembalikan baris API key ke kondisi semula (termasuk metadata audit)
if ($origSecretRow !== null) {
    $restoreSecret = $pdoSecretTest->prepare(
        "UPDATE app_settings
            SET setting_value = ?, is_encrypted = ?, updated_by = ?, updated_at = ?
          WHERE setting_key = 'gemini_api_key'"
    );
    $restoreSecret->execute([
        $origSecretRow['setting_value'],
        $origSecretRow['is_encrypted'],
        $origSecretRow['updated_by'],
        $origSecretRow['updated_at'],
    ]);
}
AppSetting::clearCache();

// Test 3.1b: Simpan API Key baru melalui controller (bukan hanya helper enkripsi)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/chatbot';
$_POST = [
    '_csrf_token'     => Session::generateCsrfToken(),
    'ai_provider'     => 'google_gemini',
    'gemini_model'    => 'gemini-3.6-flash',
    'gemini_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    'gemini_api_key'  => $testSecretKey,
];

$resSaveKey = new Response();
$routerSaveKey = buildRouter();
$routerSaveKey->dispatch(new Request(), $resSaveKey);

AppSetting::clearCache();
$savedSecretRow = $pdoSecretTest->query(
    "SELECT setting_value, is_encrypted FROM app_settings WHERE setting_key = 'gemini_api_key' LIMIT 1"
)->fetch(\PDO::FETCH_ASSOC) ?: [];
$savedCipher = (string) ($savedSecretRow['setting_value'] ?? '');

assertTest('API Key baru via controller tersimpan terenkripsi dan dapat didekripsi',
    (int) ($savedSecretRow['is_encrypted'] ?? 0) === 1
    && $savedCipher !== ''
    && $savedCipher !== $testSecretKey
    && AppSetting::decryptValue($savedCipher) === $testSecretKey
);

// Halaman konfigurasi tidak boleh memuat plaintext, ciphertext, atau potongan key.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi?tab=chatbot';
$resKeyPage = new Response();
$routerKeyPage = buildRouter();
ob_start();
$routerKeyPage->dispatch(new Request(), $resKeyPage);
$keyPageHtml = ob_get_clean();

assertTest('Halaman konfigurasi tidak membocorkan API Key tersimpan (plaintext/ciphertext)',
    !str_contains($keyPageHtml, $testSecretKey)
    && ($savedCipher === '' || !str_contains($keyPageHtml, $savedCipher))
);

// Kembalikan baris API key ke kondisi semula setelah pengujian penyimpanan.
if ($origSecretRow !== null) {
    $restoreSecret->execute([
        $origSecretRow['setting_value'],
        $origSecretRow['is_encrypted'],
        $origSecretRow['updated_by'],
        $origSecretRow['updated_at'],
    ]);
}
AppSetting::clearCache();

// Test 3.2: Menyimpan tanpa mengubah API Key (kolom kosong) mempertahankan key lama
$origKeyInEnv = \App\Core\Env::get('GEMINI_API_KEY', '');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/chatbot';
$_POST = [
    '_csrf_token'     => Session::generateCsrfToken(),
    'ai_provider'     => 'google_gemini',
    'gemini_model'    => 'gemini-3.6-flash',
    'gemini_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    'gemini_api_key'  => '', // Dikosongkan
];

$res7 = new Response();
$router7 = buildRouter();
$router7->dispatch(new Request(), $res7);

AppSetting::clearCache();
$currentChatbotKey = AppConfig::config()['chatbot']['api_key'];
assertTest('Kolom API Key kosong mempertahankan kunci aktif tanpa menimpa', !empty($currentChatbotKey) && $currentChatbotKey === $origKeyInEnv);

// Test 3.2b: Provider di luar layanan yang dipakai harus ditolak
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/chatbot';
$_POST = [
    '_csrf_token'     => Session::generateCsrfToken(),
    'ai_provider'     => 'openai',
    'gemini_model'    => 'gemini-3.6-flash',
    'gemini_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    'gemini_api_key'  => '',
];
$resInvalidProvider = new Response();
buildRouter()->dispatch(new Request(), $resInvalidProvider);
AppSetting::clearCache();
assertTest('Provider AI yang tidak didukung ditolak dan tidak mengubah konfigurasi',
    has_flash('error') && AppSetting::get('ai_provider') === 'google_gemini'
);

// Test 3.3: Chatbot Service membaca konfigurasi yang sama
$chatbotService = new NvidiaChatbotService();
assertTest('NvidiaChatbotService membaca konfigurasi aktif yang sama dengan Konfigurasi', $chatbotService->isConfigured());

// Test 3.4: Uji Koneksi ke Google Gemini Endpoint (Deterministik & Opsional Nyata)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/test-ai';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST = [
    '_csrf_token'     => Session::generateCsrfToken(),
    'ai_provider'     => 'google_gemini',
    'gemini_model'    => 'gemini-3.6-flash',
    'gemini_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    'gemini_api_key'  => '', // Gunakan key aktif
];

$activeChatbotKey = (string)(AppConfig::config()['chatbot']['api_key'] ?? '');
$hasRealApiKey = $activeChatbotKey !== ''
    && !str_starts_with($activeChatbotKey, 'AQ.Test')
    && !str_starts_with($activeChatbotKey, 'AQ.Ab8RN6')
    && !str_contains(strtolower($activeChatbotKey), 'dummy')
    && !str_contains(strtolower($activeChatbotKey), 'placeholder');

$res8 = new Response();
$router8 = buildRouter();

if ($hasRealApiKey) {
    echo "  [INFO] Menjalankan pengujian koneksi nyata ke Google Gemini...\n";
    ob_start();
    $router8->dispatch(new Request(), $res8);
    ob_end_clean();
    $testAiOutput = json_decode($res8->getContent(), true);
    $aiConnected = is_array($testAiOutput) && ($testAiOutput['success'] ?? false) === true;
    if ($aiConnected) {
        assertTest("Uji Koneksi AI berhasil terhubung ke Google Gemini (Latency: " . ($testAiOutput['latency_ms'] ?? 0) . " ms)", true);
    } else {
        $skipMessage = is_array($testAiOutput) ? ($testAiOutput['message'] ?? 'koneksi gagal') : 'koneksi gagal';
        echo "  [SKIP] Pengujian koneksi API Gemini nyata dilewati (jaringan tidak tersedia: {$skipMessage})\n";
    }
} else {
    // Pengujian deterministik tanpa ketergantungan koneksi jaringan / secret API key
    $_POST['gemini_base_url'] = 'bukan-url-valid';
    ob_start();
    $router8->dispatch(new Request(), $res8);
    ob_end_clean();
    echo "  [SKIP] Pengujian koneksi API Gemini nyata dilewati (API key rahasia tidak tersedia)\n";
}

assertTest("Respons Uji Koneksi aman dan tidak memuat API key mentah", $origKeyInEnv === '' || !str_contains($res8->getContent(), $origKeyInEnv));

// -----------------------------------------------------------------------------
// GROUP 4: PENGATURAN AKADEMIK & GEOFENCING PERSISTENCE
// -----------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN PENGATURAN AKADEMIK & GEOFENCING ---\n";

$tahunModel = new TahunAkademik();
$allTahun = $tahunModel->all(['*'], 'id ASC');
$targetTahun = $allTahun[1] ?? $allTahun[0]; // Tahun ID 2

// Simpan baseline agar seluruh perubahan uji dapat dikembalikan persis.
$campusSettingModel = new SettingKampus();
$campusBaseline = $campusSettingModel->getActiveSetting();

// Test 4.0: Koordinat kosong tidak boleh berubah menjadi 0,0
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/akademik';
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
$_POST = [
    '_csrf_token'                 => Session::generateCsrfToken(),
    'tahun_akademik_id'           => $targetTahun['id'],
    'default_late_tolerance'      => '20',
    'min_attendance_percent'      => '80',
    'radius_meter'                => '125',
    'toleransi_wajah_similarity'  => '0.50',
    'latitude'                    => '',
    'longitude'                   => '',
    'alamat'                      => 'Jl. Perjuangan No.10B STMIK IKMI Cirebon'
];
$resEmptyCoords = new Response();
buildRouter()->dispatch(new Request(), $resEmptyCoords);
$campusAfterInvalidCoords = $campusSettingModel->getActiveSetting();
assertTest('Koordinat kosong ditolak dan tidak mengubah data kampus',
    has_flash('error') && (int)$campusAfterInvalidCoords['radius_meter'] === (int)$campusBaseline['radius_meter']
);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/akademik';
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
$_POST = [
    '_csrf_token'                 => Session::generateCsrfToken(),
    'tahun_akademik_id'           => $targetTahun['id'],
    'default_late_tolerance'      => '20',
    'min_attendance_percent'      => '80',
    'radius_meter'                => '125',
    'toleransi_wajah_similarity'  => '0.50',
    'latitude'                    => '-6.7442000',
    'longitude'                   => '108.5442000',
    'alamat'                      => 'Jl. Perjuangan No.10B STMIK IKMI Cirebon'
];

$res9 = new Response();
$router9 = buildRouter();
$router9->dispatch(new Request(), $res9);

// Verifikasi perubahan tahun aktif
$activeAfter = $tahunModel->getActive();
assertTest("Pergantian tahun akademik aktif berhasil diperbarui (ID: {$activeAfter['id']})", (int)$activeAfter['id'] === (int)$targetTahun['id']);

// Verifikasi radius geofencing kampus di DB
$activeCampus = $campusSettingModel->getActiveSetting();
assertTest("Radius geofencing tersimpan persisten ke tabel settings_kampus (125 meter)", (int)$activeCampus['radius_meter'] === 125);
assertTest("Threshold similarity wajah AI tersimpan persisten ke tabel settings_kampus (0.50)", (float)$activeCampus['toleransi_wajah_similarity'] === 0.50);

// Verifikasi GeoHelper & FaceService membaca perubahan
$campusConfig = GeoHelper::getCampusConfig();
$faceThreshold = FaceService::getThresholdConfig();
assertTest("GeoHelper::getCampusConfig() secara langsung menggunakan radius baru (125 meter)", (int)$campusConfig['radius_meters'] === 125);
assertTest("FaceService::getThresholdConfig() secara langsung menggunakan threshold baru (0.50)", (float)$faceThreshold === 0.50);

// Kembalikan tahun akademik dan seluruh pengaturan kampus ke baseline.
$tahunModel->setActive(1);
$campusSettingModel->update($campusBaseline['id'], [
    'nama_kampus'                => $campusBaseline['nama_kampus'],
    'alamat'                     => $campusBaseline['alamat'],
    'radius_meter'               => $campusBaseline['radius_meter'],
    'toleransi_wajah_similarity' => $campusBaseline['toleransi_wajah_similarity'],
    'latitude'                   => $campusBaseline['latitude'],
    'longitude'                  => $campusBaseline['longitude'],
    'updated_by'                 => $campusBaseline['updated_by']
]);
AppSetting::set('default_late_tolerance', 15, 'akademik', false, 1);
AppSetting::set('min_attendance_percent', 75, 'akademik', false, 1);
AppSetting::clearCache();

// -----------------------------------------------------------------------------
// GROUP 5: PENGATURAN SISTEM (SYSTEM SETTINGS)
// -----------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN PENGATURAN SISTEM ---\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/konfigurasi/sistem';
$_POST = [
    '_csrf_token'              => Session::generateCsrfToken(),
    'maintenance_mode'         => '0',
    'session_lifetime_minutes' => '180',
    'app_debug'                => '1'
];

$res10 = new Response();
$router10 = buildRouter();
$router10->dispatch(new Request(), $res10);

AppSetting::clearCache();
$savedLifetime = AppConfig::config()['session_lifetime'];
assertTest("Masa aktif sesi berhasil dikonfigurasi (180 menit = 10800 detik)", (int)$savedLifetime === 10800);

// Kembalikan ke 7200 detik
AppSetting::set('session_lifetime', 7200, 'sistem', false, 1);
AppSetting::clearCache();

// -----------------------------------------------------------------------------
// GROUP 6: AUDIT LOG KEAMANAN
// -----------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN PENCATATAN AUDIT TRAIL ---\n";

$auditModel = new AuditLog();
$recentLogs = $auditModel->where('action', 'UPDATE_CONFIG_UMUM');
assertTest('Aktivitas perubahan konfigurasi tercatat secara persisten di tabel audit_logs', count($recentLogs) > 0);

// -----------------------------------------------------------------------------
// GROUP 7: REKAPITULASI HASIL
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "HASIL PENGUJIAN KONFIGURASI: {$passCount} PASS | {$failCount} FAIL\n";
echo "====================================================================\n";

// Kirim seluruh output yang sebelumnya dibuffer.
if (ob_get_level() > 0) {
    ob_end_flush();
}

if ($failCount > 0) {
    exit(1);
}
