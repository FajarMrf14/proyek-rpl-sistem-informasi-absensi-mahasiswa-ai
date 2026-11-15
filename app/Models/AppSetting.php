<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Env;
use Throwable;

class AppSetting extends Model
{
    protected string $table = 'app_settings';
    protected string $primaryKey = 'id';

    /**
     * Cache memori per siklus request untuk performa tinggi
     */
    private static ?array $memoryCache = null;

    /**
     * Ambil nilai konfigurasi berdasarkan key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadCacheIfNeeded();

        if (self::$memoryCache !== null && array_key_exists($key, self::$memoryCache)) {
            $item = self::$memoryCache[$key];
            $value = $item['setting_value'];

            if (!empty($item['is_encrypted']) && $value !== null && $value !== '') {
                $value = self::decryptValue((string)$value);
            }

            return self::castValue($value, $item['setting_type'] ?? 'string');
        }

        return $default;
    }

    /**
     * Simpan atau perbarui nilai konfigurasi
     */
    public static function set(
        string $key,
        mixed $value,
        string $group = 'umum',
        bool $isEncrypted = false,
        ?int $updatedBy = null
    ): bool {
        try {
            $instance = new self();
            $pdo = $instance->db;

            $stringValue = (is_array($value) || is_object($value))
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : (string)$value;

            if ($isEncrypted && $stringValue !== '') {
                $storedValue = self::encryptValue($stringValue);
            } else {
                $storedValue = $stringValue;
            }

            // Tentukan tipe
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_int($value)) {
                $type = 'integer';
            } elseif (is_float($value)) {
                $type = 'float';
            } elseif (is_array($value) || is_object($value)) {
                $type = 'json';
            }

            $sql = "INSERT INTO app_settings (setting_key, setting_value, setting_group, setting_type, is_encrypted, updated_by, updated_at)
                    VALUES (:key, :val, :grp, :type, :enc, :uid, NOW())
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        setting_group = VALUES(setting_group),
                        setting_type = VALUES(setting_type),
                        is_encrypted = VALUES(is_encrypted),
                        updated_by = VALUES(updated_by),
                        updated_at = NOW()";

            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([
                ':key'  => $key,
                ':val'  => $storedValue,
                ':grp'  => $group,
                ':type' => $type,
                ':enc'  => $isEncrypted ? 1 : 0,
                ':uid'  => $updatedBy,
            ]);

            // Bersihkan cache agar data terbaru termuat
            self::clearCache();

            return $res;
        } catch (Throwable $e) {
            error_log('[AppSetting::set] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil semua konfigurasi dalam format terstruktur per kelompok
     */
    public static function getAllGrouped(): array
    {
        self::loadCacheIfNeeded();

        $grouped = [
            'umum'     => [],
            'chatbot'  => [],
            'akademik' => [],
            'sistem'   => [],
            'whatsapp' => []
        ];

        foreach (self::$memoryCache ?? [] as $key => $item) {
            $group = $item['setting_group'] ?? 'umum';
            $val = $item['setting_value'];

            if (!empty($item['is_encrypted']) && $val !== null && $val !== '') {
                $decrypted = self::decryptValue((string)$val);
                $masked = self::maskSecret($decrypted);
                $isConfigured = !empty($decrypted);

                // Rahasia terenkripsi tidak pernah dikembalikan mentah ke lapisan tampilan.
                $valueForUi = null;
            } else {
                $decrypted = $val;
                $masked = null;
                $isConfigured = !empty($val);
                $valueForUi = $val;
            }

            $entry = [
                'key'           => $key,
                'value'         => $valueForUi,
                'masked'        => $masked,
                'group'         => $group,
                'type'          => $item['setting_type'] ?? 'string',
                'is_encrypted'  => !empty($item['is_encrypted']),
                'is_configured' => $isConfigured,
                'description'   => $item['description'] ?? '',
                'updated_at'    => $item['updated_at'] ?? null,
            ];

            $grouped[$group] ??= [];
            $grouped[$group][$key] = $entry;
        }

        return $grouped;
    }

    /**
     * Memuat seluruh record tabel app_settings ke memori statis
     */
    private static function loadCacheIfNeeded(): void
    {
        if (self::$memoryCache !== null) {
            return;
        }

        self::$memoryCache = [];

        try {
            $instance = new self();
            $rows = $instance->all();
            foreach ($rows as $row) {
                self::$memoryCache[$row['setting_key']] = $row;
            }
        } catch (Throwable) {
            // Jika DB belum ada/gagal koneksi, biarkan array kosong
            self::$memoryCache = [];
        }
    }

    /**
     * Hapus cache statis di memori
     */
    public static function clearCache(): void
    {
        self::$memoryCache = null;
    }

    /**
     * Tipe data casting
     */
    private static function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true),
            'integer' => (int)$value,
            'float'   => (float)$value,
            'json'    => is_string($value) ? (json_decode($value, true) ?? []) : (array)$value,
            default   => (string)$value,
        };
    }

    /**
     * Enkripsi string sensitif menggunakan AES-256-CBC dengan HMAC-SHA256
     */
    public static function encryptValue(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        try {
            $cipher = 'aes-256-cbc';
            $masterKey = hash('sha256', (string)Env::get('APP_KEY', 'ikmi_default_secret_fallback_key'), true);
            $ivLen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivLen);

            $encrypted = openssl_encrypt($plainText, $cipher, $masterKey, OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                return $plainText;
            }

            $hmac = hash_hmac('sha256', $iv . $encrypted, $masterKey, true);
            return base64_encode($iv . $hmac . $encrypted);
        } catch (Throwable) {
            return $plainText;
        }
    }

    /**
     * Dekripsi string terenkripsi dengan verifikasi integritas HMAC
     */
    public static function decryptValue(string $cipherPayload): string
    {
        if ($cipherPayload === '') {
            return '';
        }

        try {
            $raw = base64_decode($cipherPayload, true);
            if ($raw === false) {
                return $cipherPayload;
            }

            $cipher = 'aes-256-cbc';
            $masterKey = hash('sha256', (string)Env::get('APP_KEY', 'ikmi_default_secret_fallback_key'), true);
            $ivLen = openssl_cipher_iv_length($cipher);
            $hmacLen = 32; // SHA-256 menghasilkan 32 bytes binary

            if (strlen($raw) < ($ivLen + $hmacLen)) {
                return $cipherPayload;
            }

            $iv = substr($raw, 0, $ivLen);
            $hmac = substr($raw, $ivLen, $hmacLen);
            $ciphertext = substr($raw, $ivLen + $hmacLen);

            $calcHmac = hash_hmac('sha256', $iv . $ciphertext, $masterKey, true);
            if (!hash_equals($hmac, $calcHmac)) {
                // HMAC tidak cocok atau data teks biasa belum dienkripsi
                return $cipherPayload;
            }

            $decrypted = openssl_decrypt($ciphertext, $cipher, $masterKey, OPENSSL_RAW_DATA, $iv);
            return $decrypted !== false ? $decrypted : $cipherPayload;
        } catch (Throwable) {
            return $cipherPayload;
        }
    }

    /**
     * Sensor / mask string rahasia untuk preview aman di tampilan UI
     */
    public static function maskSecret(?string $secret): string
    {
        if (empty($secret)) {
            return '';
        }

        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', 8);
        }

        $prefix = substr($secret, 0, 4);
        $suffix = substr($secret, -4);
        return $prefix . str_repeat('•', 12) . $suffix;
    }
}
