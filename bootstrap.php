<?php

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Global Application Bootstrapper
 * ==============================================================================
 */

define('ROOT_PATH', __DIR__);

// Autoloader: Composer Vendor atau Native PSR-4 Fallback
$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = ROOT_PATH . '/app/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });

    // Helper functions
    require_once ROOT_PATH . '/app/Helpers/ViewHelper.php';
    require_once ROOT_PATH . '/app/Helpers/AuthHelper.php';
    require_once ROOT_PATH . '/app/Helpers/GeoHelper.php';
}

// Muat konfigurasi .env
\App\Core\Env::load(ROOT_PATH . '/.env');
