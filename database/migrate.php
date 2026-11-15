<?php

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Database Migration & Seeder CLI Runner
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/app/Core/Env.php';
require_once dirname(__DIR__) . '/app/Config/Database.php';

use App\Core\Env;
use App\Config\Database as DbConfig;

echo "===============================================================\n";
echo "  STMIK IKMI CIREBON - DATABASE MIGRATION & SEEDER RUNNER\n";
echo "===============================================================\n\n";

// 1. Muat Konfigurasi .env
$envPath = dirname(__DIR__) . '/.env';
Env::load($envPath);

$config = DbConfig::config();
$dbHost = $config['host'];
$dbPort = $config['port'];
$dbName = $config['database'];
$dbUser = $config['username'];
$dbPass = $config['password'];

echo "[1/4] Menghubungkan ke MySQL Server ({$dbHost}:{$dbPort})...\n";

try {
    // Koneksi awal tanpa database untuk memastikan database ada
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "      -> Terhubung ke MySQL Server versi " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

    // Pastikan database ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `{$dbName}`;");
    echo "      -> Database `{$dbName}` siap digunakan.\n\n";

    // 2. Jalankan Schema Migration
    echo "[2/4] Menjalankan Migration Schema (`database/schema.sql`)...\n";
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("File schema.sql tidak ditemukan di: {$schemaFile}");
    }

    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo "      -> Migration skema tabel dan view berhasil dieksekusi!\n\n";

    // 3. Jalankan Database Seeder
    echo "[3/4] Menjalankan Seeder Data Awal (`database/seeder.sql`)...\n";
    $seederFile = __DIR__ . '/seeder.sql';
    if (!file_exists($seederFile)) {
        throw new Exception("File seeder.sql tidak ditemukan di: {$seederFile}");
    }

    $seederSql = file_get_contents($seederFile);
    $pdo->exec($seederSql);
    echo "      -> Seeder data master & dummy berhasil dimasukkan!\n\n";

    // 4. Verifikasi dan Tampilkan Daftar Tabel & Rekap
    echo "[4/4] Verifikasi Struktur Database:\n";
    echo "---------------------------------------------------------------\n";
    echo sprintf("%-30s | %-12s\n", "Nama Tabel / View", "Tipe");
    echo "---------------------------------------------------------------\n";

    $stmt = $pdo->query("SHOW FULL TABLES IN `{$dbName}`");
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $row) {
        $name = $row[0];
        $type = $row[1] === 'VIEW' ? 'VIEW (Query)' : 'BASE TABLE';
        echo sprintf("%-30s | %-12s\n", $name, $type);
    }
    echo "---------------------------------------------------------------\n";
    echo "Total Tabel/View: " . count($tables) . "\n\n";

    // Test View Persentase Kehadiran
    echo "Pengujian Kalkulasi Persentase Kehadiran (v_rekap_kehadiran_mahasiswa):\n";
    echo "------------------------------------------------------------------------------------------------------\n";
    echo sprintf("%-10s | %-18s | %-10s | %-6s | %-6s | %-6s | %-6s | %-10s\n", "NIM", "Nama Mahasiswa", "Mata Kuliah", "Hadir", "T'lambat", "Izin", "Alpha", "Kehadiran");
    echo "------------------------------------------------------------------------------------------------------\n";

    $viewStmt = $pdo->query("SELECT * FROM v_rekap_kehadiran_mahasiswa");
    while ($r = $viewStmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf(
            "%-10s | %-18s | %-10s | %-6d | %-8d | %-6d | %-6d | %6.2f%%\n",
            $r['nim'],
            substr($r['nama_mahasiswa'], 0, 18),
            $r['kode_mk'],
            $r['total_hadir'],
            $r['total_terlambat'],
            $r['total_izin'] + $r['total_sakit'],
            $r['total_alpha'],
            $r['persentase_kehadiran']
        );
    }
    echo "------------------------------------------------------------------------------------------------------\n\n";
    echo "[SUKSES] Seluruh proses migrasi database dan pengujian relasi berhasil 100% tanpa error!\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Terjadi kesalahan database: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n[ERROR] Terjadi kesalahan: " . $e->getMessage() . "\n";
    exit(1);
}
