<?php

require_once __DIR__ . '/_cli_guard.php';

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Comprehensive Chatbot Verification Test Suite
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
echo "  STMIK IKMI CIREBON - CHATBOT VERIFICATION & SECURITY TEST SUITE\n";
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

$service = new NvidiaChatbotService();

// Fixtures berdasarkan seeder database resmi STMIK IKMI
$mhsUser = [
    'id' => 4,
    'username' => '4122001',
    'nama_lengkap' => 'Rizky Pratama',
    'role' => 'mahasiswa',
    'nim' => '4122001',
    'prodi' => 'Teknik Informatika'
];

$dosenUser = [
    'id' => 2,
    'username' => '0412088501',
    'nama_lengkap' => 'Dr. Ahmad Fauzi',
    'role' => 'dosen',
    'nidn' => '0412088501'
];

$adminUser = [
    'id' => 1,
    'username' => 'admin',
    'nama_lengkap' => 'Administrator',
    'role' => 'admin'
];

echo "--- 1. PENGUJIAN IDENTITAS CHATBOT & PENGGUNA ---\n";

// 1.1 Chatbot Identity
$resIdentityBot = $service->sendMessage('kamu siapa', $mhsUser);
assertTest(
    "Chatbot menjawab identitas dirinya ('kamu siapa')",
    $resIdentityBot['success'] === true
    && str_contains($resIdentityBot['reply'], 'Asisten Virtual Presensi STMIK IKMI Cirebon')
    && $resIdentityBot['source'] === 'identity_guard'
);

// 1.2 User Identity - Mahasiswa
$resIdentityMhs = $service->sendMessage('saya siapa', $mhsUser);
assertTest(
    "Chatbot menjawab identitas mahasiswa login beserta NIM",
    $resIdentityMhs['success'] === true
    && str_contains($resIdentityMhs['reply'], 'Rizky Pratama')
    && str_contains($resIdentityMhs['reply'], '4122001')
    && $resIdentityMhs['source'] === 'identity_guard'
);

// 1.3 User Identity - Dosen
$resIdentityDosen = $service->sendMessage('siapa saya?', $dosenUser);
assertTest(
    "Chatbot menjawab identitas dosen login beserta NIDN",
    $resIdentityDosen['success'] === true
    && str_contains($resIdentityDosen['reply'], '0412088501')
    && str_contains($resIdentityDosen['reply'], 'dosen')
    && $resIdentityDosen['source'] === 'identity_guard'
);

// 1.4 User Identity - Admin
$resIdentityAdmin = $service->sendMessage('siapa saya', $adminUser);
assertTest(
    "Chatbot menjawab identitas admin login",
    $resIdentityAdmin['success'] === true
    && str_contains($resIdentityAdmin['reply'], 'admin')
    && $resIdentityAdmin['source'] === 'identity_guard'
);

echo "\n--- 2. PENGUJIAN PENOLAKAN KELUAR DARI KONTEKS (OUT OF TOPIC) ---\n";

// 2.1 Permintaan membuat kode Python
$resPy = $service->sendMessage('Tolong buatkan kode python untuk scraping data', $mhsUser);
assertTest(
    "Permintaan kode Python ditolak oleh backend guard",
    $resPy['success'] === true
    && $resPy['source'] === 'context_guard'
    && str_contains($resPy['reply'], 'Asisten Virtual Presensi STMIK IKMI Cirebon')
);

// 2.2 Permintaan membuat kode PHP
$resPhp = $service->sendMessage('buatkan script php koneksi database', $mhsUser);
assertTest(
    "Permintaan kode PHP ditolak oleh backend guard",
    $resPhp['success'] === true
    && $resPhp['source'] === 'context_guard'
);

// 2.3 Permintaan membuat query SQL
$resSql = $service->sendMessage('tuliskan sql query select all from users', $mhsUser);
assertTest(
    "Permintaan query SQL ditolak oleh backend guard",
    $resSql['success'] === true
    && $resSql['source'] === 'context_guard'
);

// 2.4 Resep masakan
$resRecipe = $service->sendMessage('Bagaimana resep dan cara membuat nasi goreng spesial?', $mhsUser);
assertTest(
    "Pertanyaan resep masakan ditolak oleh backend guard",
    $resRecipe['success'] === true
    && $resRecipe['source'] === 'context_guard'
);

// 2.5 Matematika umum / kalkulus
$resMath = $service->sendMessage('Hitunglah turunan dan integral dari sin(2x)', $mhsUser);
assertTest(
    "Pertanyaan matematika kalkulus ditolak oleh backend guard",
    $resMath['success'] === true
    && $resMath['source'] === 'context_guard'
);

$resArithmetic = $service->sendMessage('25 * 40 = ?', $mhsUser);
assertTest(
    "Ekspresi aritmatika acak ditolak oleh backend guard",
    $resArithmetic['success'] === true
    && $resArithmetic['source'] === 'context_guard'
);

// 2.6 Pengetahuan umum & politik
$resPol = $service->sendMessage('Siapa presiden pertama republik indonesia?', $mhsUser);
assertTest(
    "Pertanyaan politik/tokoh umum ditolak oleh backend guard",
    $resPol['success'] === true
    && $resPol['source'] === 'context_guard'
);

// 2.7 Topik acak tanpa sinyal domain presensi
$resRandom = $service->sendMessage('Ceritakan sebuah dongeng tentang dinosaurus terbang di luar angkasa', $mhsUser);
assertTest(
    "Cerita/dongeng acak tanpa sinyal presensi ditolak oleh backend guard",
    $resRandom['success'] === true
    && $resRandom['source'] === 'context_guard'
);

echo "\n--- 3. PENGUJIAN PRIVASI & ISOLASI DATA ANTAR PENGGUNA ---\n";

// 3.1 Mahasiswa menanyakan NIM mahasiswa lain
$resOtherNim = $service->sendMessage('Tolong tampilkan data presensi untuk NIM 4122002', $mhsUser);
assertTest(
    "Mahasiswa meminta presensi NIM lain diblokir oleh privacy guard",
    $resOtherNim['success'] === true
    && $resOtherNim['source'] === 'privacy_guard'
    && str_contains($resOtherNim['reply'], 'hanya memiliki hak akses untuk melihat data pribadi')
);

// 3.2 Mahasiswa menanyakan data teman / mahasiswa lain
$resOtherPeer = $service->sendMessage('Berapa rekap kehadiran mahasiswa lain di kelas saya?', $mhsUser);
assertTest(
    "Mahasiswa meminta rekap mahasiswa lain diblokir oleh privacy guard",
    $resOtherPeer['success'] === true
    && $resOtherPeer['source'] === 'privacy_guard'
);

echo "\n--- 4. PENGUJIAN PERTANYAAN DALAM KONTEKS (IN-TOPIC) ---\n";

$inContextQueries = [
    'Bagaimana cara melakukan absensi?',
    'Kenapa lokasi GPS saya gagal di luar radius?',
    'Bagaimana cara kerja verifikasi wajah AI?',
    'Berapa persentase kehadiran minimal untuk ikut UAS?',
    'Apa saja jadwal kuliah saya hari ini?',
    'Bagaimana cara mengajukan surat izin sakit?'
];

foreach ($inContextQueries as $q) {
    $out = $service->checkOutOfTopic($q);
    assertTest("Pertanyaan in-context '{$q}' lolos pemeriksaan konteks", $out === null);
}

echo "\n--- 5. PENGUJIAN SYSTEM PROMPT & ROLE BOUNDARIES ---\n";

// 5.1 System prompt mahasiswa menyematkan batasan dan identitas mahasiswa
$mhsPrompt = $service->buildSystemPrompt($mhsUser, 'Tanggal: 2026-10-18');
assertTest(
    "System prompt mahasiswa memuat batasan konteks mutlak dan NIM",
    str_contains($mhsPrompt, 'BATASAN KONTEKS MUTLAK')
    && str_contains($mhsPrompt, 'INTEGRITAS DATA')
    && str_contains($mhsPrompt, '4122001')
);

// 5.2 System prompt dosen menyematkan NIDN dan batasan dosen
$dosenPrompt = $service->buildSystemPrompt($dosenUser, 'Tanggal: 2026-10-18');
assertTest(
    "System prompt dosen memuat batasan dosen dan NIDN",
    str_contains($dosenPrompt, '0412088501')
    && str_contains($dosenPrompt, 'dosen')
);

echo "\n--- 6. PENGUJIAN CONTROLLER & SESI (INPUT & HISTORY) ---\n";

$dbConnected = false;
try {
    $dbConfig = \App\Config\Database::config();
    $dsn = "{$dbConfig['driver']}:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdoTest = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_TIMEOUT => 1, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbConnected = true;
} catch (\Throwable $e) {
    $dbConnected = false;
}

if ($dbConnected) {
    // 6.1 Akses tanpa sesi login (Guest)
    $_SESSION = [];
    $reqGuest = new Request();
    $respGuest = new Response();
    $ctrlGuest = new \App\Controllers\ChatbotController($reqGuest, $respGuest);
    ob_start();
    $ctrlGuest->sendMessage();
    ob_end_clean();
    assertTest(
        "Akses Chatbot tanpa login ditolak dengan HTTP 401",
        $respGuest->getStatusCode() === 401
    );

    // 6.2 Validasi input kosong (dengan login)
    Session::set('user', $mhsUser);
    $_POST = ['message' => '   '];
    $reqEmpty = new Request();
    $respEmpty = new Response();
    $ctrlEmpty = new \App\Controllers\ChatbotController($reqEmpty, $respEmpty);
    ob_start();
    $ctrlEmpty->sendMessage();
    ob_end_clean();
    assertTest(
        "Pesan kosong ditolak oleh controller dengan HTTP 422",
        $respEmpty->getStatusCode() === 422
    );

    // 6.3 Validasi input melebihi 500 karakter
    $_POST = ['message' => str_repeat('a', 501)];
    $reqLong = new Request();
    $respLong = new Response();
    $ctrlLong = new \App\Controllers\ChatbotController($reqLong, $respLong);
    ob_start();
    $ctrlLong->sendMessage();
    ob_end_clean();
    assertTest(
        "Pesan melebihi 500 karakter ditolak oleh controller dengan HTTP 422",
        $respLong->getStatusCode() === 422
    );

    // 6.4 Verifikasi penolakan out-of-topic tidak mencemari riwayat sesi percakapan
    Session::set('user', $mhsUser);
    Session::remove('chatbot_history');
    $_POST = ['message' => 'tolong buatkan kode python'];
    $reqGuard = new Request();
    $respGuard = new Response();
    $ctrlGuard = new \App\Controllers\ChatbotController($reqGuard, $respGuard);
    ob_start();
    $ctrlGuard->sendMessage();
    $bodyGuard = ob_get_clean();
    $jsonGuard = json_decode($bodyGuard, true);

    $savedHistory = Session::get('chatbot_history', []);
    assertTest(
        "Penolakan out-of-topic via controller berhasil dan mengembalikan context_guard",
        ($jsonGuard['success'] ?? false) === true
        && ($jsonGuard['source'] ?? '') === 'context_guard'
    );
    assertTest(
        "Pesan yang ditolak guard TIDAK disimpan ke riwayat sesi chatbot_history",
        empty($savedHistory)
    );
} else {
    echo "  [INFO LINGKUNGAN] Server MySQL lokal (Laragon) saat ini belum aktif (port 3306 tidak merespons).\n";
    echo "  [INFO LINGKUNGAN] Pengujian Controller yang membutuhkan koneksi User Model dilewati dengan aman.\n";
}

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN: {$passCount} PASS | {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
