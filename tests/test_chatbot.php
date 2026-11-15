<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Test Suite: Chatbot AI Khusus Absensi Mahasiswa (Google Gemini REST API)
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\User;
use App\Services\NvidiaChatbotService;
use App\Controllers\ChatbotController;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - CHATBOT AI GOOGLE GEMINI TEST SUITE\n";
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
        echo "  [FAIL] {$description} - {$details}\n";
    }
}

function simulateChatApi(string $message, ?array $user, ?string $csrfToken = null): array
{
    $_SESSION = [];
    if ($user) {
        Session::set('user', $user);
    }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/chatbot/message';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

    $token = $csrfToken ?? Session::generateCsrfToken();
    $_POST = [
        'message' => $message,
        '_csrf_token' => $token
    ];
    $_GET = [];

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
            'json'   => null
        ];
    }
    $output = ob_get_clean();

    return [
        'status' => $response->getStatusCode(),
        'output' => $output,
        'json'   => json_decode($output, true)
    ];
}

$userModel = new User();
$mhsUser = $userModel->getFullProfile(4); // Mahasiswa 1: Rizky Pratama (NIM 4122001)

echo "--- 1. PENGUJIAN BATAS TOPIK & HEURISTIC GUARDRAILS ---\n";

$chatbotService = new NvidiaChatbotService();

// Test 1: Skenario 3 - Bertanya harga Bitcoin (Ditolak karena di luar topik)
$outBitcoin = $chatbotService->checkOutOfTopic('Berapa harga Bitcoin dan crypto hari ini?');
assertTest("Skenario 3: Pertanyaan 'harga Bitcoin' ditolak oleh guardrail batas topik", $outBitcoin !== null && str_contains($outBitcoin, 'Asisten Virtual Presensi STMIK IKMI Cirebon') && str_contains($outBitcoin, 'hanya dapat menjawab'));

// Test 2: Skenario 3 via API Endpoint - Bertanya harga Bitcoin
$resBtc = simulateChatApi('Berapa prediksi harga Bitcoin besok?', $mhsUser);
assertTest("Skenario 3 via API: Pertanyaan Bitcoin menghasilkan respons sopan batas topik", $resBtc['status'] === 200 && ($resBtc['json']['success'] ?? false) === true && str_contains($resBtc['json']['reply'] ?? '', 'Asisten Virtual Presensi STMIK IKMI Cirebon'));

// Test 3: Skenario 3 (Tambahan) - Tutorial coding umum di luar topik
$outCode = $chatbotService->checkOutOfTopic('Tolong buatkan python script untuk web scraping tokopedia');
assertTest("Pertanyaan tutorial coding umum di luar topik ditolak oleh guardrail", $outCode !== null && str_contains($outCode, 'Asisten Virtual Presensi STMIK IKMI Cirebon'));

echo "\n--- 2. PENGUJIAN PRIVASI & AKSES LINTAS MAHASISWA ---\n";

// Test 4: Skenario 4 - Mahasiswa bertanya data mahasiswa lain (NIM berbeda)
$privacyCheck = $chatbotService->checkPrivacyGuard('Tolong tampilkan data absensi untuk NIM 4122002', $mhsUser);
assertTest("Skenario 4: Mahasiswa menanyakan NIM mahasiswa lain ditolak", $privacyCheck !== null && str_contains($privacyCheck, 'hak akses') && str_contains($privacyCheck, 'milik Anda sendiri'));

// Test 5: Skenario 4 via API Endpoint - Bertanya data kehadiran mahasiswa lain
$resOther = simulateChatApi('Tolong tampilkan data absensi untuk NIM 4122002', $mhsUser);
assertTest("Skenario 4 via API: Menanyakan NIM mahasiswa lain ditolak oleh filter privasi", $resOther['status'] === 200 && ($resOther['json']['success'] ?? false) === true && str_contains($resOther['json']['reply'] ?? '', 'hak akses'));

echo "\n--- 3. PENGUJIAN OTORISASI & PENGGUNA BELUM LOGIN ---\n";

// Test 6: Skenario 6 - Pengguna belum login mencoba akses API chatbot
$resGuest = simulateChatApi('Bagaimana cara absensi?', null);
assertTest("Skenario 6: Pengguna belum login diblokir mengakses API Chatbot (HTTP 302 ke /login atau 401)", ($resGuest['status'] === 302 || $resGuest['status'] === 401));

echo "\n--- 4. PENGUJIAN PENANGANAN API KEY TIDAK TERSEDIA ---\n";

// Test 7: Skenario 5 - API Key tidak tersedia / belum dikonfigurasi
$cleanQuery = "Bagaimana cara melakukan absensi kelas?";
// Simulasi service tanpa API key
$emptyKeyService = new class extends NvidiaChatbotService {
    public function isConfigured(): bool {
        return false;
    }
};
$resNoKey = $emptyKeyService->sendMessage($cleanQuery, $mhsUser);
assertTest("Skenario 5: Tanpa API key menghasilkan pesan error yang aman tanpa crash", $resNoKey['success'] === false && ($resNoKey['is_config_error'] ?? false) === true && str_contains($resNoKey['message'], 'API key Gemini belum dikonfigurasi'));
assertTest("Skenario 5: Tidak membocorkan detail internal atau rahasia server", !str_contains($resNoKey['message'], 'stack trace') && !str_contains($resNoKey['message'], 'password'));

echo "\n--- 5. PENGUJIAN KONTEN PENGETAHUAN SISTEM ABSENSI (KONTEN PROMPT) ---\n";

// Test 8: Skenario 1 - System Prompt memuat alur absensi 4 tahap
$sysPrompt = $chatbotService->buildSystemPrompt($mhsUser);
assertTest("Skenario 1: Basis pengetahuan memuat batasan konteks dan aturan resmi UAS", str_contains($sysPrompt, 'BATASAN KONTEKS MUTLAK') && str_contains($sysPrompt, 'TOLAK dengan sopan') && str_contains($sysPrompt, 'ATURAN RESMI SYARAT UAS'));

// Test 9: Skenario 2 - System Prompt memuat panduan & solusi kendala GPS
$campusRadius = (int) (\App\Helpers\GeoHelper::getCampusConfig()['radius_meters'] ?? 100);
assertTest("Skenario 2: Basis pengetahuan memuat panduan toleransi & kendala GPS {$campusRadius}m", str_contains($sysPrompt, "radius {$campusRadius} meter") && str_contains($sysPrompt, 'Untuk kendala GPS'));

// Test 10: Konteks database menyematkan data sah milik mahasiswa yang login
$dbContext = $chatbotService->fetchDatabaseContext($mhsUser, 'Berapa persentase kehadiran saya?');
assertTest("Konteks resmi menyematkan NIM dan rekap kehadiran mahasiswa login", str_contains($dbContext, '4122001') && str_contains($dbContext, 'Rekap kehadiran per mata kuliah'));

// Test 11: Validasi input kosong
$resEmpty = simulateChatApi('   ', $mhsUser);
assertTest("Pesan kosong ditolak oleh validasi input (HTTP 422)", $resEmpty['status'] === 422);

// Test 12: Validasi input melebihi 500 karakter
$resLong = simulateChatApi(str_repeat('a', 501), $mhsUser);
assertTest("Pesan melebihi 500 karakter ditolak oleh validasi input (HTTP 422)", $resLong['status'] === 422);

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN: {$passCount} PASS | {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
