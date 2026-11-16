-- ==============================================================================
-- SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
-- Database Initial Seeder (Dummy & Master Data)
-- ==============================================================================

USE `db_absensi_ikmi`;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Seeder Tahun Akademik
TRUNCATE TABLE `tahun_akademik`;
INSERT INTO `tahun_akademik` (`id`, `kode_tahun`, `nama_tahun`, `semester`, `tanggal_mulai`, `tanggal_selesai`, `is_active`) VALUES
(1, '20251', '2025/2026 Ganjil', 'ganjil', '2025-09-01', '2026-01-31', 1),
(2, '20252', '2025/2026 Genap', 'genap', '2026-02-01', '2026-06-30', 0);

-- 2. Seeder Pengaturan Titik Kampus STMIK IKMI Cirebon
-- Titik Koordinat: Lat -6.7441584, Long 108.5441566 | Radius: 100 Meter
TRUNCATE TABLE `settings_kampus`;
INSERT INTO `settings_kampus` (`id`, `nama_kampus`, `alamat`, `latitude`, `longitude`, `radius_meter`, `toleransi_wajah_similarity`, `is_active`)
VALUES (
    1,
    'STMIK IKMI Cirebon',
    'Jl. Perjuangan No.10B, Karyamulya, Kec. Kesambi, Kota Cirebon, Jawa Barat 45131',
    -6.74415840,
    108.54415660,
    100,
    0.55,
    1
);

-- 3. Seeder Akun Pengguna (users)
-- Password Hash Bcrypt:
-- admin / admin123 ($2y$10$SayK4vQPjj.UnOi.ichSru1GqRbMpMBDGBB1ih1xzHk5cK5nvdHuK)
-- 0412088501 / dosen123 ($2y$10$pi1cujCYpWw91Mdrlqag4uE0YyDcLC4xiLa39rWfZ0qMrX.tFpnvy)
-- 0425039002 / dosen123 ($2y$10$pi1cujCYpWw91Mdrlqag4uE0YyDcLC4xiLa39rWfZ0qMrX.tFpnvy)
-- 4122001 / mhs123 ($2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS)
-- 4122002 / mhs123 ($2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS)
-- 4122003 / mhs123 ($2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS)
TRUNCATE TABLE `users`;
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`) VALUES
(1, 'admin', 'admin@ikmi.ac.id', '$2y$10$SayK4vQPjj.UnOi.ichSru1GqRbMpMBDGBB1ih1xzHk5cK5nvdHuK', 'admin', 1),
(2, '0412088501', 'ahmad.fauzi@ikmi.ac.id', '$2y$10$pi1cujCYpWw91Mdrlqag4uE0YyDcLC4xiLa39rWfZ0qMrX.tFpnvy', 'dosen', 1),
(3, '0425039002', 'siti.nurhaliza@ikmi.ac.id', '$2y$10$pi1cujCYpWw91Mdrlqag4uE0YyDcLC4xiLa39rWfZ0qMrX.tFpnvy', 'dosen', 1),
(4, '4122001', 'rizky.pratama@mhs.ikmi.ac.id', '$2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS', 'mahasiswa', 1),
(5, '4122002', 'putri.lestari@mhs.ikmi.ac.id', '$2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS', 'mahasiswa', 1),
(6, '4122003', 'muhammad.bintang@mhs.ikmi.ac.id', '$2y$10$W8gwsB7bLvLv92B4V4imReJIlr4.17HjzK9ewICQlQuYk8wcmxPiS', 'mahasiswa', 1);

-- 4. Seeder Data Profil Dosen
TRUNCATE TABLE `dosen`;
INSERT INTO `dosen` (`id`, `user_id`, `nidn`, `nama_lengkap`, `gelar_depan`, `gelar_belakang`, `email`, `no_hp`, `jenis_kelamin`) VALUES
(1, 2, '0412088501', 'Dr. Ahmad Fauzi', 'Dr.', 'M.Kom.', 'ahmad.fauzi@ikmi.ac.id', '081234567890', 'L'),
(2, 3, '0425039002', 'Siti Nurhaliza', NULL, 'M.Cs.', 'siti.nurhaliza@ikmi.ac.id', '081234567891', 'P');

-- 5. Seeder Data Profil Mahasiswa
TRUNCATE TABLE `mahasiswa`;
INSERT INTO `mahasiswa` (`id`, `user_id`, `nim`, `nama_lengkap`, `prodi`, `angkatan`, `jenis_kelamin`, `email`, `no_hp`, `status_akademik`, `face_registered`) VALUES
(1, 4, '4122001', 'Rizky Pratama', 'Teknik Informatika', 2023, 'L', 'rizky.pratama@mhs.ikmi.ac.id', '085711223344', 'aktif', 1),
(2, 5, '4122002', 'Putri Lestari', 'Teknik Informatika', 2023, 'P', 'putri.lestari@mhs.ikmi.ac.id', '085711223355', 'aktif', 1),
(3, 6, '4122003', 'Muhammad Bintang', 'Teknik Informatika', 2023, 'L', 'muhammad.bintang@mhs.ikmi.ac.id', '085711223366', 'aktif', 0);

-- 6. Seeder Biometrik Wajah Mahasiswa (Sample JSON Descriptors 128 Dimension)
TRUNCATE TABLE `wajah_mahasiswa`;
INSERT INTO `wajah_mahasiswa` (`id`, `mahasiswa_id`, `descriptor`, `foto_path`, `file_hash`, `status`, `verified_by`) VALUES
(1, 1, '[-0.1245,0.0876,0.0432,-0.0987,0.1123,-0.0345,0.0543,-0.0876,0.1234,-0.0456,0.0789,-0.0234,0.0987,-0.0654,0.0321,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876,0.0543,-0.0321,0.0654,-0.0876,0.0432,-0.0213,0.0654,-0.0876]', 'uploads/faces/4122001_sample_face.jpg', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'verified', 1),
(2, 2, '[-0.1045,0.0976,0.0332,-0.0887,0.1223,-0.0245,0.0643,-0.0776,0.1134,-0.0556,0.0889,-0.0134,0.0887,-0.0754,0.0421,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776,0.0643,-0.0221,0.0754,-0.0776,0.0532,-0.0113,0.0754,-0.0776]', 'uploads/faces/4122002_sample_face.jpg', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'verified', 1);

-- 7. Seeder Katalog Mata Kuliah
TRUNCATE TABLE `mata_kuliah`;
INSERT INTO `mata_kuliah` (`id`, `kode_mk`, `nama_mk`, `sks`, `semester_paket`, `kategori`, `is_active`) VALUES
(1, 'TIF-301', 'Kecerdasan Buatan & Machine Learning', 3, 4, 'wajib', 1),
(2, 'TIF-302', 'Pemrograman Web Lanjut', 3, 4, 'wajib', 1),
(3, 'TIF-303', 'Rekayasa Perangkat Lunak', 3, 4, 'wajib', 1),
(4, 'TIF-304', 'Basis Data Terdistribusi', 3, 4, 'wajib', 1);

-- 8. Seeder Data Kelas
TRUNCATE TABLE `kelas`;
INSERT INTO `kelas` (`id`, `nama_kelas`, `prodi`, `angkatan`) VALUES
(1, 'TI-2023-A', 'Teknik Informatika', 2023),
(2, 'TI-2023-B', 'Teknik Informatika', 2023),
(3, 'SI-2023-A', 'Sistem Informasi', 2023);

-- 9. Seeder Jadwal Perkuliahan
TRUNCATE TABLE `jadwal_kuliah`;
INSERT INTO `jadwal_kuliah` (`id`, `tahun_akademik_id`, `mata_kuliah_id`, `dosen_id`, `kelas_id`, `hari`, `jam_mulai`, `jam_selesai`, `ruangan`, `toleransi_keterlambatan`, `is_active`) VALUES
(1, 1, 1, 1, 1, 'Senin', '08:00:00', '10:30:00', 'Lab AI & Data Science', 15, 1),
(2, 1, 2, 1, 1, 'Rabu', '13:00:00', '15:30:00', 'Lab Komputer 2', 15, 1),
(3, 1, 3, 2, 1, 'Jumat', '09:00:00', '11:30:00', 'Ruang Teori 301', 15, 1);

-- 10. Seeder Anggota Mahasiswa dalam Kelas (KRS)
TRUNCATE TABLE `kelas_mahasiswa`;
INSERT INTO `kelas_mahasiswa` (`id`, `jadwal_id`, `mahasiswa_id`, `status`) VALUES
(1, 1, 1, 'aktif'),
(2, 1, 2, 'aktif'),
(3, 1, 3, 'aktif'),
(4, 2, 1, 'aktif'),
(5, 2, 2, 'aktif'),
(6, 2, 3, 'aktif'),
(7, 3, 1, 'aktif'),
(8, 3, 2, 'aktif'),
(9, 3, 3, 'aktif');

-- 11. Seeder Pertemuan Kuliah (Contoh: Jadwal 1 memiliki 4 sesi pertemuan)
TRUNCATE TABLE `pertemuan_kuliah`;
INSERT INTO `pertemuan_kuliah` (`id`, `jadwal_id`, `pertemuan_ke`, `tanggal`, `jam_buka`, `jam_tutup`, `materi_pembahasan`, `metode_kuliah`, `status`, `opened_by`, `latitude_sesi`, `longitude_sesi`, `radius_meter_sesi`) VALUES
(1, 1, 1, '2025-09-08', '2025-09-08 08:00:00', '2025-09-08 10:30:00', 'Pengantar Kecerdasan Buatan & Konsep Agen Cerdas', 'tatap_muka', 'ditutup', 1, -6.74415840, 108.54415660, 100),
(2, 1, 2, '2025-09-15', '2025-09-15 08:00:00', '2025-09-15 10:30:00', 'Metode Searching (BFS, DFS, & A* Algorithm)', 'tatap_muka', 'ditutup', 1, -6.74415840, 108.54415660, 100),
(3, 1, 3, '2025-09-22', '2025-09-22 08:00:00', NULL, 'Machine Learning: Supervised vs Unsupervised Learning', 'tatap_muka', 'dibuka', 1, -6.74415840, 108.54415660, 100),
(4, 1, 4, '2025-09-29', NULL, NULL, 'Neural Networks & Deep Learning Basics', 'tatap_muka', 'draft', NULL, NULL, NULL, NULL);

-- 12. Seeder Log Absensi Mahasiswa
TRUNCATE TABLE `absensi`;
INSERT INTO `absensi` (`id`, `pertemuan_id`, `mahasiswa_id`, `waktu_absensi`, `status`, `latitude`, `longitude`, `jarak_meter`, `status_lokasi`, `face_verified`, `face_similarity`, `foto_kegiatan`, `device_info`, `ip_address`) VALUES
-- Pertemuan 1
(1, 1, 1, '2025-09-08 08:05:22', 'hadir', -6.74416000, 108.54415800, 3.42, 'dalam_radius', 1, 0.9450, 'uploads/attendance/att_p1_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'),
(2, 1, 2, '2025-09-08 08:08:15', 'hadir', -6.74415500, 108.54415000, 8.12, 'dalam_radius', 1, 0.9120, 'uploads/attendance/att_p1_m2.jpg', 'Mozilla/5.0 (iPhone; iOS 17)', '192.168.1.51'),
(3, 1, 3, '2025-09-08 08:22:40', 'terlambat', -6.74417000, 108.54416000, 12.80, 'dalam_radius', 0, NULL, 'uploads/attendance/att_p1_m3.jpg', 'Mozilla/5.0 (Windows NT 10.0)', '192.168.1.52'),

-- Pertemuan 2
(4, 2, 1, '2025-09-15 08:03:10', 'hadir', -6.74415800, 108.54415600, 1.20, 'dalam_radius', 1, 0.9580, 'uploads/attendance/att_p2_m1.jpg', 'Mozilla/5.0 (Android 14; Mobile)', '192.168.1.50'),
(5, 2, 2, '2025-09-15 08:00:00', 'izin', NULL, NULL, NULL, 'dalam_radius', 0, NULL, NULL, NULL, NULL);

-- 13. Seeder Pengajuan Izin/Sakit
TRUNCATE TABLE `izin_sakit`;
INSERT INTO `izin_sakit` (`id`, `mahasiswa_id`, `jadwal_id`, `pertemuan_id`, `tipe`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `bukti_surat`, `file_original_name`, `file_size_kb`, `status_persetujuan`, `diverifikasi_oleh`, `catatan_dosen`, `waktu_verifikasi`) VALUES
(1, 2, 1, 2, 'izin', '2025-09-15', '2025-09-15', 'Mengikuti Lomba Hackathon Tingkat LLDIKTI IV', 'uploads/surat_izin/surat_tugas_hackathon_4122002.pdf', 'Surat_Tugas_LLDIKTI.pdf', 342, 'disetujui', 2, 'Disetujui untuk mewakili institusi.', '2025-09-15 07:45:00');

-- 14. Seeder Audit Log Aktivitas
TRUNCATE TABLE `audit_logs`;
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload_before`, `payload_after`) VALUES
(1, 1, 'SYSTEM_INIT_MIGRATION', 'settings_kampus', 1, '127.0.0.1', 'CLI-Migration-Runner', NULL, '{"status": "initialized", "tables_count": 14}');
-- 15. Seeder Konfigurasi Aplikasi (app_settings)
TRUNCATE TABLE `app_settings`;
INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `is_encrypted`, `description`) VALUES
('app_name', 'Absensi Mahasiswa AI - STMIK IKMI Cirebon', 'umum', 'string', 0, 'Nama resmi aplikasi presensi'),
('app_description', 'Sistem Absensi Mahasiswa Berbasis Web dengan AI - STMIK IKMI Cirebon', 'umum', 'string', 0, 'Deskripsi meta aplikasi'),
('app_logo', '', 'umum', 'string', 0, 'Path logo aplikasi'),
('campus_name', 'STMIK IKMI Cirebon', 'umum', 'string', 0, 'Nama institusi / perguruan tinggi'),
('contact_email', 'info@ikmi.ac.id', 'umum', 'string', 0, 'Email kontak resmi institusi'),
('app_timezone', 'Asia/Jakarta', 'umum', 'string', 0, 'Zona waktu operasional aplikasi'),

('ai_provider', 'google_gemini', 'chatbot', 'string', 0, 'Provider kecerdasan buatan'),
('gemini_model', 'gemini-3.6-flash', 'chatbot', 'string', 0, 'Model Gemini aktif'),
('gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta', 'chatbot', 'string', 0, 'Base URL endpoint API'),
('gemini_api_key', '', 'chatbot', 'string', 1, 'API Key Gemini (Terenkripsi)'),

('default_late_tolerance', '15', 'akademik', 'integer', 0, 'Default toleransi keterlambatan (menit)'),
('min_attendance_percent', '75', 'akademik', 'integer', 0, 'Batas minimal kehadiran syarat UAS (%)'),

('maintenance_mode', '0', 'sistem', 'boolean', 0, 'Status mode pemeliharaan (1=Aktif, 0=Nonaktif)'),
('session_lifetime', '7200', 'sistem', 'integer', 0, 'Masa aktif sesi pengguna dalam detik'),
('app_debug', '1', 'sistem', 'boolean', 0, 'Mode debugging aplikasi'),

('whatsapp_enabled', '0', 'whatsapp', 'boolean', 0, 'Status integrasi WhatsApp/Fonnte (1=Aktif, 0=Nonaktif)'),
('fonnte_token', '', 'whatsapp', 'string', 1, 'Token Fonnte (Terenkripsi, jangan pernah ditampilkan)'),
('whatsapp_simulation_mode', '1', 'whatsapp', 'boolean', 0, 'Mode simulasi pengiriman WhatsApp (1=Simulasi, 0=Nyata)'),
('whatsapp_schedule_reminder_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim pengingat jadwal sebelum kelas'),
('whatsapp_attendance_confirmation_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim konfirmasi setelah absensi tercatat'),
('whatsapp_low_attendance_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim peringatan kehadiran rendah'),
('whatsapp_lecturer_recap_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim rekap absensi mingguan ke dosen'),
('whatsapp_reminder_minutes', '30', 'whatsapp', 'integer', 0, 'Menit sebelum kelas untuk pengingat');

SET FOREIGN_KEY_CHECKS = 1;

