<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Automated Auth & RBAC Verification Test Suite
 * ==============================================================================
 */

define('TESTING_MODE', true);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\Mahasiswa\DashboardController as MahasiswaDashboard;
use App\Controllers\Dosen\DashboardController as DosenDashboard;
use App\Controllers\Admin\DashboardController as AdminDashboard;
use App\Models\User;
use App\Models\AuditLog;

echo "====================================================================\n";
echo "  STMIK IKMI CIREBON - AUTHENTICATION & RBAC AUTOMATED TEST SUITE\n";
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

// -----------------------------------------------------------------------------
// SETUP ROUTER UNTUK PENGUJIAN MIDDLEWARE & GUARD
// -----------------------------------------------------------------------------
function buildTestRouter(): Router
{
    $router = new Router();
    $router->registerMiddleware('auth', \App\Middleware\AuthMiddleware::class);
    $router->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class);
    $router->registerMiddleware('role', \App\Middleware\RoleMiddleware::class);

    $router->get('/login', [AuthController::class, 'showLoginForm'], ['guest']);
    $router->post('/login', [AuthController::class, 'login'], ['guest']);
    $router->get('/logout', [AuthController::class, 'logout']);
    $router->get('/mahasiswa/dashboard', [MahasiswaDashboard::class, 'index'], ['auth', 'role:mahasiswa']);
    $router->get('/dosen/dashboard', [DosenDashboard::class, 'index'], ['auth', 'role:dosen']);
    $router->get('/admin/dashboard', [AdminDashboard::class, 'index'], ['auth', 'role:admin']);

    return $router;
}

// Mock Helper untuk menjalankan Request ke Router
function simulateRequest(string $method, string $uri, array $postData = []): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_POST = $postData;
    $_GET = [];

    $token = Session::generateCsrfToken();
    $_POST['_csrf_token'] = $token;

    $req = new Request();
    $res = new Response();

    $router = buildTestRouter();
    
    ob_start();
    $router->dispatch($req, $res);
    $output = ob_get_clean();

    $headers = $res->getHeaders();
    $redirectUrl = $headers['Location'] ?? null;

    return [
        'status'   => $res->getStatusCode(),
        'redirect' => $redirectUrl,
        'output'   => $output
    ];
}

// =============================================================================
// TEST GROUP 1: LOGIN AUTHENTICATION (PASSWORD_VERIFY & MULTI-ROLE)
// =============================================================================
echo "--- [TEST GROUP 1] LOGIN AUTHENTICATION & PASSWORD_VERIFY ---\n";

$userModel = new User();

// Test 1.1: Mahasiswa Login (NIM: 4122001, Pass: mhs123)
Session::destroy();
$mhsUser = $userModel->findByUsername('4122001');
assertTest(
    "User Mahasiswa ditemukan di database",
    !empty($mhsUser) && $mhsUser['role'] === 'mahasiswa'
);
assertTest(
    "Password Mahasiswa terverifikasi via password_verify()",
    password_verify('mhs123', $mhsUser['password_hash'] ?? '')
);

$fullMhs = $userModel->getFullProfile((int)$mhsUser['id']);
assertTest(
    "Profil Mahasiswa memuat relasi data mahasiswa (NIM: {$fullMhs['nim']})",
    !empty($fullMhs['mahasiswa_id']) && $fullMhs['nim'] === '4122001'
);

// Test 1.2: Dosen Login (NIDN: 0412088501, Pass: dosen123)
$dosenUser = $userModel->findByUsername('0412088501');
assertTest(
    "User Dosen ditemukan di database",
    !empty($dosenUser) && $dosenUser['role'] === 'dosen'
);
assertTest(
    "Password Dosen terverifikasi via password_verify()",
    password_verify('dosen123', $dosenUser['password_hash'] ?? '')
);

$fullDosen = $userModel->getFullProfile((int)$dosenUser['id']);
assertTest(
    "Profil Dosen memuat gelar lengkap ({$fullDosen['nama_dengan_gelar']})",
    !empty($fullDosen['dosen_id']) && str_contains($fullDosen['nama_dengan_gelar'], 'Dr. Ahmad Fauzi')
);

// Test 1.3: Admin Login (Username: admin, Pass: admin123)
$adminUser = $userModel->findByUsername('admin');
assertTest(
    "User Admin ditemukan di database",
    !empty($adminUser) && $adminUser['role'] === 'admin'
);
assertTest(
    "Password Admin terverifikasi via password_verify()",
    password_verify('admin123', $adminUser['password_hash'] ?? '')
);

// Test 1.4: Password Salah
assertTest(
    "Password salah ditolak oleh password_verify()",
    password_verify('wrong_password_123', $mhsUser['password_hash']) === false
);

// =============================================================================
// TEST GROUP 2: END-TO-END POST LOGIN & REDIRECTION CONTROLLER
// =============================================================================
echo "\n--- [TEST GROUP 2] END-TO-END POST LOGIN & REDIRECTION ---\n";

// 2.1: Submit Form Login Mahasiswa
Session::destroy();
$loginMhsReq = simulateRequest('POST', '/login', [
    'username' => '4122001',
    'password' => 'mhs123'
]);
assertTest(
    "POST /login Mahasiswa sukses -> Redirect ke /mahasiswa/dashboard",
    $loginMhsReq['status'] === 302 && str_contains($loginMhsReq['redirect'] ?? '', 'mahasiswa/dashboard') && Session::get('user')['role'] === 'mahasiswa'
);

// 2.2: Submit Form Login Dosen
Session::destroy();
$loginDosenReq = simulateRequest('POST', '/login', [
    'username' => '0412088501',
    'password' => 'dosen123'
]);
assertTest(
    "POST /login Dosen sukses -> Redirect ke /dosen/dashboard",
    $loginDosenReq['status'] === 302 && str_contains($loginDosenReq['redirect'] ?? '', 'dosen/dashboard') && Session::get('user')['role'] === 'dosen'
);

// 2.3: Submit Form Login Admin
Session::destroy();
$loginAdminReq = simulateRequest('POST', '/login', [
    'username' => 'admin',
    'password' => 'admin123'
]);
assertTest(
    "POST /login Admin sukses -> Redirect ke /admin/dashboard",
    $loginAdminReq['status'] === 302 && str_contains($loginAdminReq['redirect'] ?? '', 'admin/dashboard') && Session::get('user')['role'] === 'admin'
);

// 2.4: Submit Form Login Gagal (Password salah)
Session::destroy();
$loginFailReq = simulateRequest('POST', '/login', [
    'username' => 'admin',
    'password' => 'passwordsalah'
]);
assertTest(
    "POST /login password salah -> Ditolak & Redirect kembali ke /login dengan flash error",
    $loginFailReq['status'] === 302 && str_contains($loginFailReq['redirect'] ?? '', 'login') && !Session::has('user') && Session::hasFlash('error')
);

// =============================================================================
// TEST GROUP 3: ROLE-BASED ACCESS CONTROL (RBAC) & GUARDS
// =============================================================================
echo "\n--- [TEST GROUP 3] ROLE-BASED ACCESS CONTROL (RBAC) & GUARDS ---\n";

// 3.1: GUEST ACCESS (Belum Login)
Session::destroy();
$guestToMhs = simulateRequest('GET', '/mahasiswa/dashboard');
assertTest(
    "Guest mencoba akses /mahasiswa/dashboard dialihkan (Redirect 302 ke /login)",
    $guestToMhs['status'] === 302 && str_contains($guestToMhs['redirect'] ?? '', 'login')
);

$guestToDosen = simulateRequest('GET', '/dosen/dashboard');
assertTest(
    "Guest mencoba akses /dosen/dashboard dialihkan (Redirect 302 ke /login)",
    $guestToDosen['status'] === 302 && str_contains($guestToDosen['redirect'] ?? '', 'login')
);

$guestToAdmin = simulateRequest('GET', '/admin/dashboard');
assertTest(
    "Guest mencoba akses /admin/dashboard dialihkan (Redirect 302 ke /login)",
    $guestToAdmin['status'] === 302 && str_contains($guestToAdmin['redirect'] ?? '', 'login')
);

// 3.2: MAHASISWA ROLE ACCESS
Session::destroy();
unset($fullMhs['password_hash']);
Session::set('user', $fullMhs); // Login sebagai Mahasiswa

$mhsSelf = simulateRequest('GET', '/mahasiswa/dashboard');
assertTest(
    "Mahasiswa mengakses /mahasiswa/dashboard berhasil (HTTP 200)",
    $mhsSelf['status'] === 200 && str_contains($mhsSelf['output'], 'Dashboard Mahasiswa')
);

$mhsToDosen = simulateRequest('GET', '/dosen/dashboard');
assertTest(
    "Mahasiswa mengakses /dosen/dashboard DITOLAK (HTTP 403 Forbidden)",
    $mhsToDosen['status'] === 403 || str_contains($mhsToDosen['output'], '403')
);

$mhsToAdmin = simulateRequest('GET', '/admin/dashboard');
assertTest(
    "Mahasiswa mengakses /admin/dashboard DITOLAK (HTTP 403 Forbidden)",
    $mhsToAdmin['status'] === 403 || str_contains($mhsToAdmin['output'], '403')
);

// 3.3: DOSEN ROLE ACCESS
Session::destroy();
unset($fullDosen['password_hash']);
Session::set('user', $fullDosen); // Login sebagai Dosen

$dosenSelf = simulateRequest('GET', '/dosen/dashboard');
assertTest(
    "Dosen mengakses /dosen/dashboard berhasil (HTTP 200)",
    $dosenSelf['status'] === 200 && str_contains($dosenSelf['output'], 'Dashboard Dosen')
);

$dosenToAdmin = simulateRequest('GET', '/admin/dashboard');
assertTest(
    "Dosen mengakses /admin/dashboard DITOLAK (HTTP 403 Forbidden)",
    $dosenToAdmin['status'] === 403 || str_contains($dosenToAdmin['output'], '403')
);

$dosenToMhs = simulateRequest('GET', '/mahasiswa/dashboard');
assertTest(
    "Dosen mengakses /mahasiswa/dashboard DITOLAK (HTTP 403 Forbidden)",
    $dosenToMhs['status'] === 403 || str_contains($dosenToMhs['output'], '403')
);

// 3.4: ADMIN ROLE ACCESS
Session::destroy();
$fullAdmin = $userModel->getFullProfile((int)$adminUser['id']);
unset($fullAdmin['password_hash']);
Session::set('user', $fullAdmin); // Login sebagai Admin

$adminSelf = simulateRequest('GET', '/admin/dashboard');
assertTest(
    "Admin mengakses /admin/dashboard berhasil (HTTP 200)",
    $adminSelf['status'] === 200 && str_contains($adminSelf['output'], 'Dashboard Admin')
);

// 3.5: GUEST MIDDLEWARE (Pengguna yang sudah login mengakses /login)
$adminToLogin = simulateRequest('GET', '/login');
assertTest(
    "Pengguna yang sudah login mengakses /login dialihkan ke dashboard role-nya (Redirect 302)",
    $adminToLogin['status'] === 302 && str_contains($adminToLogin['redirect'] ?? '', 'admin/dashboard')
);

// =============================================================================
// TEST GROUP 4: LOGOUT & AUDIT LOGS
// =============================================================================
echo "\n--- [TEST GROUP 4] LOGOUT & AUDIT LOGS ---\n";

$authController = new AuthController(new Request(), new Response());

// Logout test
$authController->logout();
assertTest(
    "Logout berhasil menghapus sesi pengguna",
    !Session::has('user')
);

// Audit logs test
$auditModel = new AuditLog();
$recentLogs = $auditModel->all(['*'], 'id DESC');
assertTest(
    "Audit trail mencatat aktivitas ke tabel audit_logs",
    count($recentLogs) > 0
);

echo "\n====================================================================\n";
echo "  HASIL AKHIR PENGUJIAN: {$passedCount} / {$totalTests} TESTS BERHASIL (" . round(($passedCount / $totalTests) * 100, 1) . "%)\n";
echo "====================================================================\n";
