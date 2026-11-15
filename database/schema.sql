-- ==============================================================================
-- SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
-- Database Schema Definition (MySQL 8.0+)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS `db_absensi_ikmi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_absensi_ikmi`;

-- -----------------------------------------------------------------------------
-- 1. TABEL: users (Akun Pengguna & Autentikasi Multi-Role)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL COMMENT 'NIM untuk Mahasiswa, NIDN untuk Dosen, Username untuk Admin',
    `email` VARCHAR(100) NULL,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt / Argon2 hashed password',
    `role` ENUM('admin', 'dosen', 'mahasiswa') NOT NULL DEFAULT 'mahasiswa',
    `avatar` VARCHAR(255) NULL,
    `no_hp` VARCHAR(20) NULL COMMENT 'Nomor WhatsApp admin (format 62...)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Aktif, 0 = Nonaktif/Blokir',
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel master akun pengguna sistem';

-- -----------------------------------------------------------------------------
-- 2. TABEL: tahun_akademik (Semester & Periode Akademik Aktif)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tahun_akademik`;
CREATE TABLE `tahun_akademik` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_tahun` VARCHAR(10) NOT NULL COMMENT 'Contoh: 20251 untuk 2025/2026 Ganjil',
    `nama_tahun` VARCHAR(50) NOT NULL COMMENT 'Contoh: 2025/2026 Ganjil',
    `semester` ENUM('ganjil', 'genap', 'pendek') NOT NULL DEFAULT 'ganjil',
    `tanggal_mulai` DATE NULL,
    `tanggal_selesai` DATE NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Hanya 1 periode yang aktif bernilai 1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tahun_akademik_kode` (`kode_tahun`),
    INDEX `idx_tahun_akademik_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel periode semester akademik';

-- -----------------------------------------------------------------------------
-- 3. TABEL: dosen (Data Profil Dosen Pengampu)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `dosen`;
CREATE TABLE `dosen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Relasi ke tabel users',
    `nidn` VARCHAR(20) NOT NULL COMMENT 'Nomor Induk Dosen Nasional',
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `gelar_depan` VARCHAR(20) NULL,
    `gelar_belakang` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `no_hp` VARCHAR(20) NULL,
    `jenis_kelamin` ENUM('L', 'P') NOT NULL DEFAULT 'L',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_dosen_user` (`user_id`),
    UNIQUE KEY `uk_dosen_nidn` (`nidn`),
    CONSTRAINT `fk_dosen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel profil dosen pengampu';

-- -----------------------------------------------------------------------------
-- 4. TABEL: mahasiswa (Data Profil Mahasiswa)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mahasiswa`;
CREATE TABLE `mahasiswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Relasi ke tabel users',
    `nim` VARCHAR(20) NOT NULL COMMENT 'Nomor Induk Mahasiswa',
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `prodi` VARCHAR(50) NOT NULL DEFAULT 'Teknik Informatika' COMMENT 'Program Studi STMIK IKMI',
    `angkatan` YEAR NOT NULL DEFAULT '2023',
    `jenis_kelamin` ENUM('L', 'P') NOT NULL DEFAULT 'L',
    `email` VARCHAR(100) NULL,
    `no_hp` VARCHAR(20) NULL,
    `status_akademik` ENUM('aktif', 'cuti', 'lulus', 'non-aktif') NOT NULL DEFAULT 'aktif',
    `face_registered` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Status apakah sudah mendaftarkan data biometrik wajah',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_mahasiswa_user` (`user_id`),
    UNIQUE KEY `uk_mahasiswa_nim` (`nim`),
    INDEX `idx_mahasiswa_prodi` (`prodi`),
    INDEX `idx_mahasiswa_status` (`status_akademik`),
    CONSTRAINT `fk_mahasiswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel data profil mahasiswa';

-- -----------------------------------------------------------------------------
-- 5. TABEL: wajah_mahasiswa (Biometrik Data Wajah AI - Sensitif)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `wajah_mahasiswa`;
CREATE TABLE `wajah_mahasiswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mahasiswa_id` INT NOT NULL,
    `descriptor` LONGTEXT NOT NULL COMMENT '128-dimensional floating point array vektor biometrik face-api.js dalam format JSON',
    `foto_path` VARCHAR(255) NOT NULL COMMENT 'Path berkas foto referensi wajah mahasiswa',
    `file_hash` VARCHAR(64) NULL COMMENT 'SHA256 checksum integritas foto referensi',
    `status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'verified',
    `verified_by` INT NULL COMMENT 'ID Admin yang memvalidasi data biometrik',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_wajah_mahasiswa` (`mahasiswa_id`),
    CONSTRAINT `fk_wajah_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_wajah_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel biometrik wajah mahasiswa untuk verifikasi AI';

-- -----------------------------------------------------------------------------
-- 6. TABEL: mata_kuliah (Katalog Mata Kuliah)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mata_kuliah`;
CREATE TABLE `mata_kuliah` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_mk` VARCHAR(20) NOT NULL COMMENT 'Kode Mata Kuliah (misal: TIF-301)',
    `nama_mk` VARCHAR(100) NOT NULL,
    `sks` INT NOT NULL DEFAULT 3 COMMENT 'Bobot Satuan Kredit Semester',
    `semester_paket` INT NOT NULL DEFAULT 1 COMMENT 'Rekomendasi semester (1-8)',
    `kategori` ENUM('wajib', 'pilihan') NOT NULL DEFAULT 'wajib',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_mata_kuliah_kode` (`kode_mk`),
    INDEX `idx_mk_semester` (`semester_paket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel kurikulum mata kuliah';

-- -----------------------------------------------------------------------------
-- 7. TABEL: kelas (Rombongan Belajar / Kelas Mahasiswa)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `kelas`;
CREATE TABLE `kelas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kelas` VARCHAR(50) NOT NULL COMMENT 'Contoh: TI-2023-KGS-A, IF-2023-P1',
    `prodi` VARCHAR(50) NOT NULL DEFAULT 'Teknik Informatika',
    `angkatan` YEAR NOT NULL DEFAULT '2023',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_kelas_nama` (`nama_kelas`),
    INDEX `idx_kelas_prodi_angkatan` (`prodi`, `angkatan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel rombongan belajar/kelas';

-- -----------------------------------------------------------------------------
-- 8. TABEL: jadwal_kuliah (Jadwal Perkuliahan)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `jadwal_kuliah`;
CREATE TABLE `jadwal_kuliah` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tahun_akademik_id` INT NOT NULL,
    `mata_kuliah_id` INT NOT NULL,
    `dosen_id` INT NOT NULL,
    `kelas_id` INT NOT NULL,
    `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') NOT NULL,
    `jam_mulai` TIME NOT NULL,
    `jam_selesai` TIME NOT NULL,
    `ruangan` VARCHAR(50) NOT NULL DEFAULT 'Lab Komputer 1',
    `toleransi_keterlambatan` INT NOT NULL DEFAULT 15 COMMENT 'Toleransi keterlambatan dalam satuan menit',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_jadwal_hari_jam` (`hari`, `jam_mulai`),
    CONSTRAINT `fk_jadwal_tahun` FOREIGN KEY (`tahun_akademik_id`) REFERENCES `tahun_akademik` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jadwal_mk` FOREIGN KEY (`mata_kuliah_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jadwal_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jadwal_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel jadwal perkuliahan terpadu';

-- -----------------------------------------------------------------------------
-- 9. TABEL: kelas_mahasiswa (KRS / Mahasiswa dalam Kelas Jadwal)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `kelas_mahasiswa`;
CREATE TABLE `kelas_mahasiswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jadwal_id` INT NOT NULL,
    `mahasiswa_id` INT NOT NULL,
    `status` ENUM('aktif', 'batal', 'mengulang') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_kelas_mhs_jadwal` (`jadwal_id`, `mahasiswa_id`),
    CONSTRAINT `fk_km_jadwal` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_km_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel pendaftaran mahasiswa pada jadwal kelas (KRS)';

-- -----------------------------------------------------------------------------
-- 10. TABEL: pertemuan_kuliah (Sesi Pertemuan Perkuliahan 1 s.d 16)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pertemuan_kuliah`;
CREATE TABLE `pertemuan_kuliah` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jadwal_id` INT NOT NULL,
    `pertemuan_ke` INT NOT NULL COMMENT 'Nomor urut sesi pertemuan (1-16)',
    `tanggal` DATE NOT NULL COMMENT 'Tanggal pelaksanaan pertemuan',
    `jam_buka` DATETIME NULL COMMENT 'Waktu presensi mulai dibuka oleh dosen',
    `jam_tutup` DATETIME NULL COMMENT 'Waktu presensi resmi ditutup',
    `materi_pembahasan` TEXT NULL COMMENT 'Materi / Topik perkuliahan pada pertemuan ini',
    `metode_kuliah` ENUM('tatap_muka', 'online', 'hybrid') NOT NULL DEFAULT 'tatap_muka',
    `status` ENUM('draft', 'dibuka', 'ditutup', 'dibatalkan') NOT NULL DEFAULT 'draft',
    `opened_by` INT NULL COMMENT 'ID Dosen yang membuka sesi presensi',
    `latitude_sesi` DECIMAL(10, 8) NULL COMMENT 'Koordinat spesifik sesi (jika berbeda dari default kampus)',
    `longitude_sesi` DECIMAL(11, 8) NULL,
    `radius_meter_sesi` INT NULL COMMENT 'Radius toleransi khusus untuk sesi ini (meter)',
    `qr_code_token` VARCHAR(100) NULL COMMENT 'Token dinamis jika absensi menggunakan validasi QR',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_pertemuan_jadwal_ke` (`jadwal_id`, `pertemuan_ke`),
    INDEX `idx_pertemuan_tanggal_status` (`tanggal`, `status`),
    CONSTRAINT `fk_pertemuan_jadwal` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pertemuan_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel sesi pertemuan perkuliahan per jadwal';

-- -----------------------------------------------------------------------------
-- 11. TABEL: absensi (Log & Record Presensi Mahasiswa per Pertemuan)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `absensi`;
CREATE TABLE `absensi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pertemuan_id` INT NOT NULL,
    `mahasiswa_id` INT NOT NULL,
    `waktu_absensi` DATETIME NOT NULL COMMENT 'Timestamp saat presensi berhasil tercatat',
    `status` ENUM('hadir', 'terlambat', 'izin', 'sakit', 'alpha') NOT NULL DEFAULT 'hadir',
    `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitude GPS perangkat mahasiswa saat presensi',
    `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitude GPS perangkat mahasiswa saat presensi',
    `jarak_meter` DECIMAL(8, 2) NULL COMMENT 'Jarak fisik perangkat ke titik kampus (dalam meter)',
    `status_lokasi` ENUM('dalam_radius', 'luar_radius', 'lokasi_tidak_terdeteksi') NOT NULL DEFAULT 'dalam_radius',
    `face_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Wajah cocok dengan AI, 0 = Gagal/Manual',
    `face_similarity` DECIMAL(5, 4) NULL COMMENT 'Confidence score kemiripan biometrik (0.0000 - 1.0000)',
    `foto_kegiatan` VARCHAR(255) NULL COMMENT 'Path berkas foto selfie/kegiatan kelas saat presensi',
    `device_info` VARCHAR(255) NULL COMMENT 'User-Agent / Device model mahasiswa',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP Address mahasiswa saat absensi',
    `keterangan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_absensi_pertemuan_mhs` (`pertemuan_id`, `mahasiswa_id`) COMMENT 'Mencegah absensi ganda pada pertemuan yang sama',
    INDEX `idx_absensi_status` (`status`),
    INDEX `idx_absensi_waktu` (`waktu_absensi`),
    CONSTRAINT `fk_absensi_pertemuan` FOREIGN KEY (`pertemuan_id`) REFERENCES `pertemuan_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_absensi_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel transaksi absensi mahasiswa';

-- -----------------------------------------------------------------------------
-- 12. TABEL: izin_sakit (Pengajuan & Verifikasi Surat Izin/Sakit)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `izin_sakit`;
CREATE TABLE `izin_sakit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mahasiswa_id` INT NOT NULL,
    `jadwal_id` INT NOT NULL,
    `pertemuan_id` INT NULL COMMENT 'Terkait sesi pertemuan tertentu atau null jika berlaku tanggal',
    `tipe` ENUM('izin', 'sakit') NOT NULL DEFAULT 'izin',
    `tanggal_mulai` DATE NOT NULL,
    `tanggal_selesai` DATE NOT NULL,
    `alasan` TEXT NOT NULL COMMENT 'Keterangan alasan izin atau diagnosa sakit',
    `bukti_surat` VARCHAR(255) NOT NULL COMMENT 'Path berkas upload surat dokter / surat tugas',
    `file_original_name` VARCHAR(255) NULL,
    `file_size_kb` INT NULL,
    `status_persetujuan` ENUM('pending', 'disetujui', 'ditolak') NOT NULL DEFAULT 'pending',
    `diverifikasi_oleh` INT NULL COMMENT 'User ID Dosen/Admin yang memverifikasi',
    `catatan_dosen` TEXT NULL COMMENT 'Catatan persetujuan/penolakan dari Dosen',
    `waktu_verifikasi` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_izin_status` (`status_persetujuan`),
    INDEX `idx_izin_mahasiswa` (`mahasiswa_id`),
    CONSTRAINT `fk_izin_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_izin_jadwal` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_izin_pertemuan` FOREIGN KEY (`pertemuan_id`) REFERENCES `pertemuan_kuliah` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_izin_verifier` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel pengajuan izin dan surat sakit mahasiswa';

-- -----------------------------------------------------------------------------
-- 13. TABEL: settings_kampus (Pengaturan Geofencing Kampus STMIK IKMI)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings_kampus`;
CREATE TABLE `settings_kampus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kampus` VARCHAR(100) NOT NULL DEFAULT 'STMIK IKMI Cirebon',
    `alamat` TEXT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL DEFAULT -6.74415840 COMMENT 'Latitude pusat kampus STMIK IKMI Cirebon',
    `longitude` DECIMAL(11, 8) NOT NULL DEFAULT 108.54415660 COMMENT 'Longitude pusat kampus STMIK IKMI Cirebon',
    `radius_meter` INT NOT NULL DEFAULT 100 COMMENT 'Radius toleransi geofencing default dalam meter',
    `toleransi_wajah_similarity` DECIMAL(4, 2) NOT NULL DEFAULT 0.55 COMMENT 'Threshold threshold kemiripan AI (face-api.js distance < 0.55)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel konfigurasi koordinat dan geofencing kampus';

-- -----------------------------------------------------------------------------
-- 14. TABEL: audit_logs (Audit Trail & Rekam Jejak Aktivitas Sistem)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT 'User ID pelaku aksi (bisa null untuk guest/gagal login)',
    `action` VARCHAR(100) NOT NULL COMMENT 'Jenis aktivitas (LOGIN, ABSEN_SUBMIT, IZIN_SUBMIT, UPDATE_GEOFENCE, dll)',
    `table_name` VARCHAR(50) NULL COMMENT 'Tabel yang terkena dampak',
    `record_id` INT NULL COMMENT 'ID baris data terkait',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload_before` JSON NULL COMMENT 'Snapshot data sebelum perubahan (format JSON)',
    `payload_after` JSON NULL COMMENT 'Snapshot data setelah perubahan (format JSON)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel log audit keamanan dan riwayat aktivitas';

-- -----------------------------------------------------------------------------
-- 15. TABEL: app_settings (Konfigurasi Terpusat Sistem & Aplikasi)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` LONGTEXT NULL,
    `setting_group` ENUM('umum', 'chatbot', 'akademik', 'sistem', 'whatsapp') NOT NULL DEFAULT 'umum',
    `setting_type` VARCHAR(20) NOT NULL DEFAULT 'string',
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `description` VARCHAR(255) NULL,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_app_settings_key` (`setting_key`),
    INDEX `idx_app_settings_group` (`setting_group`),
    CONSTRAINT `fk_app_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel konfigurasi terpusat aplikasi';

-- -----------------------------------------------------------------------------
-- 16. TABEL: whatsapp_notifications (Outbox Notifikasi WhatsApp/Fonnte)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_notifications`;
CREATE TABLE `whatsapp_notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_key` VARCHAR(191) NOT NULL COMMENT 'Kunci idempotensi untuk mencegah pengiriman duplikat',
    `notification_type` ENUM('schedule_reminder', 'attendance_confirmation', 'absence_notice', 'low_attendance', 'lecturer_teaching_reminder', 'lecturer_attendance', 'lecturer_attendance_summary', 'lecturer_recap', 'admin_attendance_alert', 'admin_approval', 'admin_whatsapp_failure', 'admin_device_issue', 'admin_attendance_summary', 'announcement', 'test') NOT NULL,
    `recipient_type` ENUM('mahasiswa', 'dosen', 'admin', 'test') NOT NULL,
    `recipient_id` INT NULL COMMENT 'ID profil penerima; NULL untuk pesan uji manual',
    `recipient_ref` VARCHAR(60) NULL COMMENT 'Referensi minimal penerima, bukan nomor telepon lengkap',
    `payload_json` JSON NULL COMMENT 'Data minimal untuk menyusun pesan; tanpa nomor telepon/token',
    `status` ENUM('pending', 'processing', 'accepted', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_attempt_at` DATETIME NULL,
    `accepted_at` DATETIME NULL,
    `simulated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = hanya simulasi, tidak dikirim ke Fonnte',
    `provider_message_id` VARCHAR(100) NULL,
    `provider_request_id` VARCHAR(100) NULL,
    `error_message` VARCHAR(255) NULL COMMENT 'Pesan kegagalan yang sudah disanitasi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_whatsapp_event` (`event_key`),
    INDEX `idx_whatsapp_queue` (`status`, `scheduled_at`),
    INDEX `idx_whatsapp_type_created` (`notification_type`, `created_at`),
    INDEX `idx_whatsapp_recipient` (`recipient_type`, `recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbox dan riwayat pengiriman notifikasi WhatsApp';

-- -----------------------------------------------------------------------------
-- 16b. TABEL: whatsapp_announcements (Riwayat Pengumuman WhatsApp Admin)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_announcements`;
CREATE TABLE `whatsapp_announcements` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(120) NOT NULL,
    `pesan` TEXT NOT NULL,
    `target_type` VARCHAR(30) NOT NULL COMMENT 'all_mahasiswa|all_dosen|all_users|kelas|mahasiswa|dosen',
    `target_ref` VARCHAR(191) NULL COMMENT 'ID kelas atau daftar ID penerima, bukan nomor telepon',
    `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_announcement_created` (`created_at`),
    CONSTRAINT `fk_announcement_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat pengumuman WhatsApp dari admin';

-- -----------------------------------------------------------------------------
-- VIEW: v_rekap_kehadiran_mahasiswa (View Rekapitulasi Persentase Kehadiran)
-- -----------------------------------------------------------------------------
DROP VIEW IF EXISTS `v_rekap_kehadiran_mahasiswa`;
CREATE VIEW `v_rekap_kehadiran_mahasiswa` AS
SELECT 
    km.jadwal_id,
    jk.mata_kuliah_id,
    mk.kode_mk,
    mk.nama_mk,
    k.nama_kelas,
    d.nama_lengkap AS nama_dosen,
    m.id AS mahasiswa_id,
    m.nim,
    m.nama_lengkap AS nama_mahasiswa,
    -- Hitung total pertemuan yang telah selesai/ditutup pada jadwal ini
    (SELECT COUNT(*) FROM pertemuan_kuliah pk WHERE pk.jadwal_id = km.jadwal_id AND pk.status = 'ditutup') AS total_pertemuan_selesai,
    -- Hitung rincian status presensi mahasiswa
    COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) AS total_hadir,
    COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) AS total_terlambat,
    COUNT(CASE WHEN a.status = 'izin' THEN 1 END) AS total_izin,
    COUNT(CASE WHEN a.status = 'sakit' THEN 1 END) AS total_sakit,
    COUNT(CASE WHEN a.status = 'alpha' THEN 1 END) AS total_alpha,
    -- Formula Persentase Kehadiran: ((Hadir + Terlambat) / Total Pertemuan Selesai) * 100
    CASE 
        WHEN (SELECT COUNT(*) FROM pertemuan_kuliah pk WHERE pk.jadwal_id = km.jadwal_id AND pk.status = 'ditutup') > 0 
        THEN ROUND(((COUNT(CASE WHEN a.status IN ('hadir', 'terlambat') THEN 1 END)) / 
             (SELECT COUNT(*) FROM pertemuan_kuliah pk WHERE pk.jadwal_id = km.jadwal_id AND pk.status = 'ditutup')) * 100, 2)
        ELSE 100.00 
    END AS persentase_kehadiran
FROM kelas_mahasiswa km
JOIN jadwal_kuliah jk ON km.jadwal_id = jk.id
JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
JOIN kelas k ON jk.kelas_id = k.id
JOIN dosen d ON jk.dosen_id = d.id
JOIN mahasiswa m ON km.mahasiswa_id = m.id
LEFT JOIN pertemuan_kuliah pk ON pk.jadwal_id = km.jadwal_id AND pk.status = 'ditutup'
LEFT JOIN absensi a ON a.pertemuan_id = pk.id AND a.mahasiswa_id = m.id
GROUP BY km.jadwal_id, jk.mata_kuliah_id, mk.kode_mk, mk.nama_mk, k.nama_kelas, d.nama_lengkap, m.id, m.nim, m.nama_lengkap;

SET FOREIGN_KEY_CHECKS = 1;
