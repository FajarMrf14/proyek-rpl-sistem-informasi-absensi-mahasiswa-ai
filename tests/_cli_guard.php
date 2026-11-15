<?php

/**
 * Guard keamanan test suite.
 *
 * Semua script di folder tests hanya boleh dijalankan dari CLI.
 * Guard ini mencegah eksekusi melalui HTTP yang berpotensi mengubah database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo "403 Forbidden: test suite hanya dapat dijalankan melalui CLI.\n";
    exit(1);
}
