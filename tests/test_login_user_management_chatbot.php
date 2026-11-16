<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Comprehensive Test Suite: Login, User Management, & Admin Chatbot Context
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

echo "====================================================================\n";
echo "  TEST SUITE: LOGIN, USER MANAGEMENT & ADMIN CHATBOT REVISION\n";
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

function dispatchApp(string $uri, ?array $user = null, string $method = 'GET', array $post = [], bool $isAjax = false): array
{
    $_SESSION = [];
    if ($user) {
        Session::set('user', $user);
    }

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    if ($isAjax) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    } else {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    $token = Session::generateCsrfToken();
    if ($method === 'POST') {
        if (!isset($post['_csrf_token'])) {
            $post['_csrf_token'] = $token;
        }
        $_POST = $post;
    } else {
        $_POST = [];
    }
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
            'headers' => $response->getHeaders()
        ];
    }
    $output = ob_get_clean();

    return [
        'status' => $response->getStatusCode(),
        'output' => $output,
        'headers' => $response->getHeaders()
    ];
}

$userModel = new User();
$db = \App\Core\Database::getConnection();

echo "--- 1. PENGUJIAN HALAMAN LOGIN TANPA PILIHAN ROLE ---\n";

// 1.1 Halaman login bebas dari pill role dan script fillLogin
$resLoginGet = dispatchApp('/login');
assertTest(
    "Halaman login berhasil dimuat (HTTP 200)",
    $resLoginGet['status'] === 200
);
assertTest(
    "Halaman login TIDAK memuat teks 'AKUN UJI COBA CEPAT'",
    !str_contains(strtoupper($resLoginGet['output']), 'AKUN UJI COBA CEPAT')
);
assertTest(
    "Halaman login TIDAK memuat fungsi JavaScript fillLogin",
    !str_contains($resLoginGet['output'], 'fillLogin')
);
assertTest(
    "Halaman login memuat input username dan password yang bersih",
    str_contains($resLoginGet['output'], 'id="username"') && str_contains($resLoginGet['output'], 'id="password"')
);

// 1.2 Login Multi-Role berdasarkan database tanpa input role
$resLoginAdmin = dispatchApp('/login', null, 'POST', ['username' => 'admin', 'password' => 'admin123']);
assertTest(
    "Login admin mengarahkan (redirect) ke /admin/dashboard",
    $resLoginAdmin['status'] === 302 && str_contains($resLoginAdmin['headers']['Location'] ?? '', '/admin/dashboard')
);

$resLoginDosen = dispatchApp('/login', null, 'POST', ['username' => '0412088501', 'password' => 'dosen123']);
assertTest(
    "Login dosen mengarahkan ke /dosen/dashboard",
    $resLoginDosen['status'] === 302 && str_contains($resLoginDosen['headers']['Location'] ?? '', '/dosen/dashboard')
);

$resLoginMhs = dispatchApp('/login', null, 'POST', ['username' => '4122001', 'password' => 'mhs123']);
assertTest(
    "Login mahasiswa mengarahkan ke /mahasiswa/dashboard",
    $resLoginMhs['status'] === 302 && str_contains($resLoginMhs['headers']['Location'] ?? '', '/mahasiswa/dashboard')
);

echo "\n--- 2. PENGUJIAN MANAJEMEN USER & NAMA LENGKAP ADMIN ---\n";

$adminProfileBefore = $userModel->getFullProfile(1);

// 2.1 Akses halaman Manajemen User oleh Admin
$resUsersPage = dispatchApp('/admin/users', $adminProfileBefore);
assertTest(
    "Admin dapat membuka halaman Manajemen User (HTTP 200)",
    $resUsersPage['status'] === 200 && str_contains($resUsersPage['output'], 'Manajemen Pengguna')
);

// 2.2 Perubahan nama lengkap admin menjadi 'Fajar Ma'ruf' via Manajemen User
$resUpdateName = dispatchApp('/admin/users/update/1', $adminProfileBefore, 'POST', [
    'nama_lengkap' => "Fajar Ma'ruf",
    'email' => 'admin@ikmi.ac.id'
]);
assertTest(
    "Update nama lengkap admin berhasil dan mengarahkan kembali ke /admin/users",
    $resUpdateName['status'] === 302 && str_contains($resUpdateName['headers']['Location'] ?? '', '/admin/users')
);

// 2.3 Verifikasi database aktual
$adminFromDb = $db->query("SELECT id, username, nama_lengkap FROM users WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
assertTest(
    "Database users ID 1 menyimpan nama lengkap 'Fajar Ma\'ruf'",
    $adminFromDb['nama_lengkap'] === "Fajar Ma'ruf" && $adminFromDb['username'] === 'admin'
);

// 2.4 Login ulang dengan username lama tetap berhasil dan membawa nama lengkap baru
$resReLoginAdmin = dispatchApp('/login', null, 'POST', ['username' => 'admin', 'password' => 'admin123']);
$loggedUser = Session::get('user');
assertTest(
    "Login dengan username lama 'admin' berhasil dan session memuat nama lengkap 'Fajar Ma\'ruf'",
    $resReLoginAdmin['status'] === 302 && ($loggedUser['nama_lengkap'] ?? '') === "Fajar Ma'ruf"
);

// 2.5 Tampilan dashboard admin memuat nama lengkap
$resAdminDash = dispatchApp('/admin/dashboard', $loggedUser);
assertTest(
    "Dashboard admin menampilkan nama lengkap 'Fajar Ma\'ruf'",
    str_contains(html_entity_decode($resAdminDash['output'], ENT_QUOTES, 'UTF-8'), "Fajar Ma'ruf")
);

// 2.6 Konsistensi nama dosen dan mahasiswa
$dosenProfile = $userModel->getFullProfile(2);
assertTest(
    "Nama profil dosen ID 2 memuat 'Dr. Ahmad Fauzi'",
    str_contains($dosenProfile['nama_dengan_gelar'] ?? $dosenProfile['nama_lengkap'], 'Dr. Ahmad Fauzi')
);

$mhsProfile = $userModel->getFullProfile(4);
assertTest(
    "Nama profil mahasiswa ID 4 memuat 'Rizky Pratama'",
    ($mhsProfile['nama_lengkap'] ?? '') === 'Rizky Pratama'
);

echo "\n--- 3. PENGUJIAN IDENTITAS & KEAMANAN CHATBOT DENGAN NAMA LENGKAP ---\n";

$chatbotService = new NvidiaChatbotService();

// 3.1 Chatbot 'siapa saya' untuk admin yang sudah diperbarui namanya
$resChatAdmin = $chatbotService->sendMessage('siapa saya', $loggedUser);
assertTest(
    "Chatbot menjawab 'siapa saya' admin dengan nama lengkap 'Fajar Ma\'ruf' dan peran admin",
    $resChatAdmin['success'] === true
    && str_contains($resChatAdmin['reply'], "Fajar Ma'ruf")
    && str_contains(strtolower($resChatAdmin['reply']), "admin")
);

// 3.2 Chatbot 'siapa saya' untuk dosen
$resChatDosen = $chatbotService->sendMessage('siapa saya', $dosenProfile);
assertTest(
    "Chatbot menjawab 'siapa saya' dosen dengan nama 'Dr. Ahmad Fauzi' dan NIDN",
    $resChatDosen['success'] === true
    && str_contains($resChatDosen['reply'], 'Dr. Ahmad Fauzi')
    && str_contains($resChatDosen['reply'], '0412088501')
);

// 3.3 Chatbot 'siapa saya' untuk mahasiswa
$resChatMhs = $chatbotService->sendMessage('siapa saya', $mhsProfile);
assertTest(
    "Chatbot menjawab 'siapa saya' mahasiswa dengan nama 'Rizky Pratama' dan NIM",
    $resChatMhs['success'] === true
    && str_contains($resChatMhs['reply'], 'Rizky Pratama')
    && str_contains($resChatMhs['reply'], '4122001')
);

// 3.4 Mahasiswa diblokir meminta data mahasiswa lain (privacy guard)
$resPrivacy = $chatbotService->sendMessage('Tampilkan data absensi mahasiswa lain NIM 4122002', $mhsProfile);
assertTest(
    "Mahasiswa dilarang melihat data absensi mahasiswa lain",
    str_contains($resPrivacy['reply'], 'data pribadi, jadwal, dan riwayat presensi milik Anda sendiri')
);

echo "\n--- 4. PENGUJIAN KONTEKS DATABASE ADMIN & ATURAN RESMI UAS ---\n";

// 4.1 System prompt admin memuat aturan resmi UAS 75%
$sysPromptAdmin = $chatbotService->buildSystemPrompt($loggedUser);
assertTest(
    "System prompt memuat aturan resmi minimal kehadiran 75% untuk UAS",
    str_contains($sysPromptAdmin, '75%') && str_contains($sysPromptAdmin, 'UAS')
);
assertTest(
    "System prompt admin menegaskan hak akses lengkap data presensi",
    str_contains($sysPromptAdmin, 'Admin berhak mengakses seluruh data mahasiswa')
);

// 4.2 Database context untuk admin memuat seluruh komponen yang diminta
$reflection = new ReflectionClass($chatbotService);
$fetchMethod = $reflection->getMethod('fetchDatabaseContext');
$fetchMethod->setAccessible(true);
$dbContextAdmin = $fetchMethod->invoke($chatbotService, $loggedUser);

assertTest(
    "Konteks database admin memuat 'Daftar mahasiswa terdaftar'",
    str_contains($dbContextAdmin, 'Daftar mahasiswa terdaftar') && str_contains($dbContextAdmin, '4122001')
);
assertTest(
    "Konteks database admin memuat 'Daftar dosen pengampu'",
    str_contains($dbContextAdmin, 'Daftar dosen pengampu') && str_contains($dbContextAdmin, 'Dr. Ahmad Fauzi')
);
assertTest(
    "Konteks database admin memuat 'Daftar kelas'",
    str_contains($dbContextAdmin, 'Daftar kelas') && str_contains($dbContextAdmin, 'TI-2023-A')
);
assertTest(
    "Konteks database admin memuat 'Jadwal perkuliahan aktif'",
    str_contains($dbContextAdmin, 'Jadwal perkuliahan aktif')
);
assertTest(
    "Konteks database admin memuat 'Rekap kehadiran mahasiswa dan evaluasi syarat UAS'",
    str_contains($dbContextAdmin, 'Rekap kehadiran mahasiswa dan evaluasi syarat UAS')
    && str_contains($dbContextAdmin, 'status_syarat_uas')
);

echo "\n--- 5. PENGUJIAN PENOLAKAN OUT-OF-TOPIC & PENANGANAN ERROR HTTP 500 ---\n";

// 5.1 Penolakan topik umum
$outRecipe = $chatbotService->checkOutOfTopic('Bagaimana resep membuat nasi goreng spesial?');
assertTest(
    "Pertanyaan resep masakan ditolak langsung oleh backend guard",
    $outRecipe !== null && str_contains($outRecipe, 'Asisten Virtual Presensi STMIK IKMI Cirebon')
);

$outCode = $chatbotService->checkOutOfTopic('Buatkan kode Python untuk web scraping');
assertTest(
    "Permintaan kode pemrograman ditolak langsung oleh backend guard",
    $outCode !== null && str_contains($outCode, 'Asisten Virtual Presensi STMIK IKMI Cirebon')
);

// 5.2 Pengujian penanganan error controller (tidak menghasilkan HTTP 500 mentah)
$mockServiceFail = new class extends NvidiaChatbotService {
    public function sendMessage(string $userMessage, array $currentUser, array $history = [], array $context = []): array {
        return [
            'success' => false,
            'message' => 'Layanan AI sedang mengalami lonjakan beban. Silakan coba kembali dalam beberapa saat.'
        ];
    }
};

$_SESSION = [];
Session::set('user', $loggedUser);
$_POST = [
    'message' => 'Bagaimana cara presensi?'
];

$reqFail = new Request();
$resFail = new Response();
$chatbotController = new \App\Controllers\ChatbotController($reqFail, $resFail);

$refProp = new ReflectionProperty(\App\Controllers\ChatbotController::class, 'chatbotService');
$refProp->setAccessible(true);
$refProp->setValue($chatbotController, $mockServiceFail);

ob_start();
$chatbotController->sendMessage();
$outputFail = ob_get_clean();
$jsonFail = json_decode($outputFail, true);

assertTest(
    "Kegagalan eksternal AI ditangani dengan HTTP 200 dan pesan ramah (bukan HTTP 500)",
    $resFail->getStatusCode() === 200 && ($jsonFail['success'] ?? true) === false && str_contains($jsonFail['message'] ?? '', 'lonjakan beban')
);

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN LENGKAP: {$passCount} PASS | {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
