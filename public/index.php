<?php

/**
 * Front Controller - penanganan error API yang aman.
 */

define('APP_START_TIME', microtime(true));

require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $app = new \App\Core\App();
    $app->run();
} catch (\Throwable $e) {
    // Catat detail lengkap hanya di log server, bukan di browser.
    error_log('[Application Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isChatbotApi = strpos($requestUri, '/api/chatbot/') !== false;

    if ($isChatbotApi) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $debug = (bool) \App\Core\Env::get('APP_DEBUG', false);
        $message = 'Terjadi kesalahan di server saat memproses chatbot. Periksa log PHP/Apache Laragon.';
        if ($debug) {
            // Detail terbatas untuk pengembangan lokal; jangan aktifkan debug di server publik.
            $message .= ' Detail: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        }

        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ((bool) \App\Core\Env::get('APP_DEBUG', false)) {
        http_response_code(500);
        echo '<div style="font-family:sans-serif;background:#fff0f0;border:1px solid #ffcccc;color:#b30000;padding:20px;border-radius:8px;margin:20px">';
        echo '<h2>Application Error</h2>';
        echo '<p><strong>Pesan:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' (Baris: ' . (int) $e->getLine() . ')</p>';
        echo '</div>';
    } else {
        http_response_code(500);
        echo '<h1>500 Internal Server Error</h1><p>Terjadi kesalahan pada sistem. Silakan hubungi admin.</p>';
    }
}
