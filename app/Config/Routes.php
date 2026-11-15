<?php

/**
 * ==============================================================================
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Web Routes Configuration
 * ==============================================================================
 */

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\Mahasiswa\DashboardController as MahasiswaDashboard;
use App\Controllers\Mahasiswa\AbsensiController;
use App\Controllers\Dosen\DashboardController as DosenDashboard;
use App\Controllers\Admin\DashboardController as AdminDashboard;
use App\Controllers\Admin\JadwalController as AdminJadwal;

/** @var \App\Core\Router $router */

// -----------------------------------------------------------------------------
// Public Routes
// -----------------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HomeController::class, 'health']);

// -----------------------------------------------------------------------------
// Authentication Routes (Guest Only)
// -----------------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLoginForm'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout']);

// -----------------------------------------------------------------------------
// Mahasiswa Routes (Role: mahasiswa)
// -----------------------------------------------------------------------------
$router->get('/mahasiswa/dashboard', [MahasiswaDashboard::class, 'index'], ['auth', 'role:mahasiswa']);
$router->get('/mahasiswa/absensi', [MahasiswaDashboard::class, 'absensi'], ['auth', 'role:mahasiswa']);

// Step 1: Pilih mata kuliah → tampilkan halaman geolocation
$router->get('/mahasiswa/absensi/proses/{id}', [MahasiswaDashboard::class, 'prosesAbsensi'], ['auth', 'role:mahasiswa']);
$router->post('/mahasiswa/absensi/proses/{id}', [MahasiswaDashboard::class, 'prosesAbsensi'], ['auth', 'role:mahasiswa']);

// Step 2: Validasi lokasi (Geolocation) — POST server-side
$router->post('/mahasiswa/absensi/geolocation/{id}', [AbsensiController::class, 'validasiGeolocation'], ['auth', 'role:mahasiswa']);

// Step 2 GET: hasil geolocation (redirect dari POST jika gagal/berhasil)
$router->get('/mahasiswa/absensi/geolocation/{id}', [AbsensiController::class, 'halamanGeolocation'], ['auth', 'role:mahasiswa']);

// Step 3: Verifikasi Wajah AI — GET kamera & POST biometrik matcher
$router->get('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'halamanWajah'], ['auth', 'role:mahasiswa']);
$router->post('/mahasiswa/absensi/wajah/{id}', [AbsensiController::class, 'validasiWajah'], ['auth', 'role:mahasiswa']);

// Step 4: Foto Kegiatan Kelas & Submit Presensi ke Database
$router->get('/mahasiswa/absensi/kegiatan/{id}', [AbsensiController::class, 'halamanKegiatan'], ['auth', 'role:mahasiswa']);
$router->post('/mahasiswa/absensi/kegiatan/{id}', [AbsensiController::class, 'validasiKegiatan'], ['auth', 'role:mahasiswa']);

// Step Final: Bukti & Rekap Presensi Berhasil
$router->get('/mahasiswa/absensi/sukses/{id}', [AbsensiController::class, 'halamanSukses'], ['auth', 'role:mahasiswa']);

$router->get('/mahasiswa/riwayat', [MahasiswaDashboard::class, 'riwayat'], ['auth', 'role:mahasiswa']);
$router->get('/mahasiswa/izin', [MahasiswaDashboard::class, 'izin'], ['auth', 'role:mahasiswa']);
$router->post('/mahasiswa/izin/submit', [\App\Controllers\Mahasiswa\IzinController::class, 'submit'], ['auth', 'role:mahasiswa']);
$router->get('/mahasiswa/profil', [MahasiswaDashboard::class, 'profil'], ['auth', 'role:mahasiswa']);

// -----------------------------------------------------------------------------
// Dosen Routes (Role: dosen)
// -----------------------------------------------------------------------------
$router->get('/dosen/dashboard', [DosenDashboard::class, 'index'], ['auth', 'role:dosen']);
$router->get('/dosen/kelas', [\App\Controllers\Dosen\KelasController::class, 'index'], ['auth', 'role:dosen']);
$router->get('/dosen/kelas/{id}', [\App\Controllers\Dosen\KelasController::class, 'detail'], ['auth', 'role:dosen']);

// -----------------------------------------------------------------------------
// Admin / Akademik Routes (Role: admin)
// -----------------------------------------------------------------------------
$router->get('/admin/dashboard', [AdminDashboard::class, 'index'], ['auth', 'role:admin']);

// Izin / Sakit Management Admin
$router->get('/admin/izin', [\App\Controllers\Admin\IzinController::class, 'index'], ['auth', 'role:admin']);
$router->post('/admin/izin/proses/{id}', [\App\Controllers\Admin\IzinController::class, 'proses'], ['auth', 'role:admin']);

// User Management Admin
$router->get('/admin/users', [\App\Controllers\Admin\UserController::class, 'index'], ['auth', 'role:admin']);
$router->post('/admin/users/update/{id}', [\App\Controllers\Admin\UserController::class, 'update'], ['auth', 'role:admin']);

// Jadwal Kuliah Admin
$router->get('/admin/jadwal', [AdminJadwal::class, 'index'], ['auth', 'role:admin']);
$router->get('/admin/jadwal/create', [AdminJadwal::class, 'create'], ['auth', 'role:admin']);
$router->post('/admin/jadwal/store', [AdminJadwal::class, 'store'], ['auth', 'role:admin']);
$router->get('/admin/jadwal/{id}', [AdminJadwal::class, 'show'], ['auth', 'role:admin']);
$router->get('/admin/jadwal/{id}/edit', [AdminJadwal::class, 'edit'], ['auth', 'role:admin']);
$router->post('/admin/jadwal/{id}/update', [AdminJadwal::class, 'update'], ['auth', 'role:admin']);
$router->post('/admin/jadwal/{id}/toggle', [AdminJadwal::class, 'toggle'], ['auth', 'role:admin']);
$router->post('/admin/jadwal/{id}/delete', [AdminJadwal::class, 'destroy'], ['auth', 'role:admin']);

// Konfigurasi Sistem & Aplikasi Admin
$router->get('/admin/konfigurasi', [\App\Controllers\Admin\KonfigurasiController::class, 'index'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/umum', [\App\Controllers\Admin\KonfigurasiController::class, 'updateUmum'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/chatbot', [\App\Controllers\Admin\KonfigurasiController::class, 'updateChatbot'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/test-ai', [\App\Controllers\Admin\KonfigurasiController::class, 'testChatbotConnection'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/whatsapp', [\App\Controllers\Admin\KonfigurasiController::class, 'updateWhatsapp'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/test-whatsapp', [\App\Controllers\Admin\KonfigurasiController::class, 'testWhatsapp'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/announcement', [\App\Controllers\Admin\KonfigurasiController::class, 'sendAnnouncement'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/whatsapp-device', [\App\Controllers\Admin\KonfigurasiController::class, 'checkDevice'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/akademik', [\App\Controllers\Admin\KonfigurasiController::class, 'updateAkademik'], ['auth', 'role:admin']);
$router->post('/admin/konfigurasi/sistem', [\App\Controllers\Admin\KonfigurasiController::class, 'updateSistem'], ['auth', 'role:admin']);


// -----------------------------------------------------------------------------
// Chatbot AI Routes (Authenticated Users: mahasiswa, dosen, admin)
// -----------------------------------------------------------------------------
$router->post('/api/chatbot/message', [\App\Controllers\ChatbotController::class, 'sendMessage'], ['auth']);
$router->post('/api/chatbot/reset', [\App\Controllers\ChatbotController::class, 'resetHistory'], ['auth']);

