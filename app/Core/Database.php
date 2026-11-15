<?php

namespace App\Core;

use App\Config\Database as DbConfig;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor untuk mencegah instansiasi langsung (Singleton)
     */
    private function __construct() {}

    /**
     * Mendapatkan instance koneksi PDO tunggal
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = DbConfig::config();
            $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                // Di mode production, jangan tampilkan detail error database ke publik
                if (Env::get('APP_DEBUG', true)) {
                    die("Koneksi Database Gagal: " . $e->getMessage());
                } else {
                    die("Terjadi kesalahan pada sistem basis data. Silakan hubungi administrator.");
                }
            }
        }

        return self::$instance;
    }

    /**
     * Memulai Database Transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Commit Transaction
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Rollback Transaction
     */
    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }
}
