<?php

namespace App\Core;

use App\Config\App as AppConfig;
use Throwable;

class Session
{
    private static ?int $lifetimeCache = null;
    private static bool $starting = false;

    /**
     * Memulai sesi aman jika belum dimulai.
     * Masa aktif sesi mengikuti konfigurasi "session_lifetime" (detik).
     */
    public static function start(): void
    {
        if (self::$starting) {
            return;
        }

        self::$starting = true;

        try {
            $lifetime = self::lifetime();

            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                ini_set('session.cookie_httponly', '1');
                ini_set('session.use_only_cookies', '1');
                ini_set('session.gc_maxlifetime', (string) $lifetime);

                $cookieParams = session_get_cookie_params();
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path'     => $cookieParams['path'] ?: '/',
                    'domain'   => $cookieParams['domain'] ?? '',
                    'secure'   => (bool) ($cookieParams['secure'] ?? false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                session_start();
            }

            if (isset($_SESSION)) {
                // Idle timeout: akhiri sesi bila tidak ada aktivitas melebihi batas konfigurasi.
                if (isset($_SESSION['_last_activity'])
                    && (time() - (int) $_SESSION['_last_activity']) > $lifetime
                ) {
                    $_SESSION = [];

                    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                        session_regenerate_id(true);
                    }

                    $_SESSION['_flash_messages'] = [
                        'error' => 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.'
                    ];
                }

                $_SESSION['_last_activity'] = time();

                // Kelola flash message expiration
                if (!isset($_SESSION['_flash_messages'])) {
                    $_SESSION['_flash_messages'] = [];
                }
            }
        } finally {
            self::$starting = false;
        }
    }

    /**
     * Ambil masa aktif sesi dari konfigurasi aplikasi (fallback: .env / 2 jam).
     */
    private static function lifetime(): int
    {
        if (self::$lifetimeCache !== null) {
            return self::$lifetimeCache;
        }

        $lifetime = 7200;

        try {
            $configured = (int) (AppConfig::config()['session_lifetime'] ?? 7200);
            if ($configured >= 900 && $configured <= 86400) {
                $lifetime = $configured;
            }
        } catch (Throwable) {
            // Gunakan nilai default bila konfigurasi database belum tersedia.
        }

        return self::$lifetimeCache = $lifetime;
    }

    /**
     * Set nilai session
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        if (isset($_SESSION)) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Ambil nilai session
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Cek apakah key ada dalam session
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Hapus key dari session
     */
    public static function remove(string $key): void
    {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Menghancurkan seluruh sesi (Logout)
     */
    public static function destroy(): void
    {
        self::start();
        if (isset($_SESSION)) {
            $_SESSION = [];
        }

        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Regenerasi ID Session (mencegah Session Fixation)
     */
    public static function regenerate(): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }

    /**
     * Set Flash Message (hanya bertahan 1 request berikutnya)
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        if (isset($_SESSION)) {
            $_SESSION['_flash_messages'][$type] = $message;
        }
    }

    /**
     * Ambil Flash Message dan hapus dari sesi
     */
    public static function getFlash(string $type): ?string
    {
        self::start();
        if (isset($_SESSION['_flash_messages'][$type])) {
            $msg = $_SESSION['_flash_messages'][$type];
            unset($_SESSION['_flash_messages'][$type]);
            return $msg;
        }
        return null;
    }

    /**
     * Cek keberadaan Flash Message
     */
    public static function hasFlash(string $type): bool
    {
        self::start();
        return isset($_SESSION['_flash_messages'][$type]);
    }

    /**
     * Generate CSRF Token
     */
    public static function generateCsrfToken(): string
    {
        self::start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Validasi CSRF Token
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::start();
        if (empty($token) || !isset($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
