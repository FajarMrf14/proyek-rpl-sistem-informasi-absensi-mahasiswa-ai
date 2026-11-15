-- ==============================================================================
-- Migrasi aditif: Ekspansi Notifikasi WhatsApp (Fonnte) untuk Mahasiswa,
-- Dosen, dan Admin.
--
-- SIFAT: ADITIF. Tidak menghapus tabel, kolom, atau baris data yang sudah ada.
-- Jalankan HANYA file ini, JANGAN jalankan database/migrate.php (mengandung DROP).
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. Nomor WhatsApp untuk admin disimpan pada tabel users.
--    Mahasiswa memakai mahasiswa.no_hp dan dosen memakai dosen.no_hp.
--    Guard INFORMATION_SCHEMA membuat migrasi aman dijalankan ulang.
-- ------------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'no_hp'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `no_hp` VARCHAR(20) NULL COMMENT ''Nomor WhatsApp admin (format 62...)'' AFTER `avatar`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------------------
-- 2. Perluas jenis notifikasi pada outbox. Nilai lama tetap valid.
-- ------------------------------------------------------------------------------
ALTER TABLE `whatsapp_notifications`
    MODIFY COLUMN `notification_type` ENUM(
        'schedule_reminder',
        'attendance_confirmation',
        'absence_notice',
        'low_attendance',
        'lecturer_teaching_reminder',
        'lecturer_attendance',
        'lecturer_attendance_summary',
        'lecturer_recap',
        'admin_attendance_alert',
        'admin_approval',
        'admin_whatsapp_failure',
        'admin_device_issue',
        'admin_attendance_summary',
        'announcement',
        'test'
    ) NOT NULL;

-- Penanda pesan yang hanya disimulasikan (tidak dikirim ke Fonnte).
SET @sim_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_notifications'
      AND COLUMN_NAME = 'simulated'
);
SET @sql := IF(
    @sim_exists = 0,
    'ALTER TABLE `whatsapp_notifications` ADD COLUMN `simulated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = hanya simulasi, tidak dikirim ke Fonnte'' AFTER `accepted_at`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------------------
-- 3. Riwayat pengumuman yang dikirim admin.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_announcements` (
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

-- ------------------------------------------------------------------------------
-- 4. Pengaturan baru. Semua jenis notifikasi baru NONAKTIF secara default.
--    Mode simulasi AKTIF secara default agar tidak ada pesan nyata terkirim
--    sebelum admin mematikannya dengan sadar.
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `app_settings`
    (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `is_encrypted`, `description`)
VALUES
    ('whatsapp_lecturer_teaching_reminder_enabled', '0', 'whatsapp', 'boolean', 0, 'Pengingat jadwal mengajar untuk dosen'),
    ('whatsapp_lecturer_attendance_enabled', '0', 'whatsapp', 'boolean', 0, 'Notifikasi absensi mahasiswa untuk dosen'),
    ('whatsapp_lecturer_attendance_mode', 'per_student', 'whatsapp', 'string', 0, 'Mode notifikasi absensi dosen: per_student atau summary'),
    ('whatsapp_absence_notice_enabled', '0', 'whatsapp', 'boolean', 0, 'Pemberitahuan ketidakhadiran (alpha) ke mahasiswa'),
    ('whatsapp_admin_attendance_alert_enabled', '0', 'whatsapp', 'boolean', 0, 'Notifikasi admin untuk absensi yang butuh perhatian'),
    ('whatsapp_admin_approval_enabled', '0', 'whatsapp', 'boolean', 0, 'Notifikasi admin untuk pengajuan yang menunggu persetujuan'),
    ('whatsapp_admin_whatsapp_failure_enabled', '0', 'whatsapp', 'boolean', 0, 'Notifikasi admin saat pengiriman WhatsApp gagal permanen'),
    ('whatsapp_admin_device_issue_enabled', '0', 'whatsapp', 'boolean', 0, 'Notifikasi admin saat perangkat Fonnte terputus'),
    ('whatsapp_admin_attendance_summary_enabled', '0', 'whatsapp', 'boolean', 0, 'Ringkasan absensi harian untuk admin'),
    ('whatsapp_announcement_enabled', '1', 'whatsapp', 'boolean', 0, 'Izinkan admin mengirim pengumuman WhatsApp'),
    ('whatsapp_simulation_mode', '1', 'whatsapp', 'boolean', 0, 'Mode simulasi: pesan tidak dikirim ke Fonnte'),
    ('whatsapp_template_schedule_reminder', '*Pengingat Jadwal Kuliah*\n\nHalo {nama_mhs},\nMata kuliah: {nama_mk}\nDosen: {nama_dosen}\nKelas: {nama_kelas}\nHari/Tanggal: {hari_tanggal}\nWaktu: {jam_mulai}-{jam_selesai} WIB\nRuang: {ruangan}\n\nKuliah dimulai sekitar {menit_sebelum} menit lagi.\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template pengingat jadwal mahasiswa'),
    ('whatsapp_template_attendance_confirmation', '*Absensi Tercatat*\n\nNama: {nama_mhs}\nNIM: {nim}\nMata kuliah: {nama_mk}\nDosen: {nama_dosen}\nKelas: {nama_kelas}\nTanggal: {tanggal}\nWaktu: {jam_absensi} WIB\nStatus: {status}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template konfirmasi absensi mahasiswa'),
    ('whatsapp_template_lecturer_teaching_reminder', 'PENGINGAT JADWAL MENGAJAR\n\nYth. Bapak/Ibu {nama_dosen},\n\nAnda memiliki jadwal mengajar {menit_sebelum} menit lagi.\n\nMata Kuliah: {nama_mk}\nKelas: {nama_kelas}\nHari/Tanggal: {hari_tanggal}\nWaktu: {jam_mulai}-{jam_selesai} WIB\nRuang: {ruangan}\n\nMohon mempersiapkan kegiatan perkuliahan.\n\nPesan ini dikirim otomatis oleh {app_name}.', 'whatsapp', 'text', 0, 'Template pengingat jadwal mengajar dosen'),
    ('whatsapp_template_lecturer_attendance', '*Absensi Mahasiswa*\n\nMahasiswa: {nama_mhs}\nNIM: {nim}\nMata kuliah: {nama_mk}\nKelas: {nama_kelas}\nWaktu: {jam_absensi} WIB\nStatus: {status}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template notifikasi absensi mahasiswa ke dosen'),
    ('whatsapp_template_lecturer_attendance_summary', '*Ringkasan Kehadiran Kelas*\n\nMata kuliah: {nama_mk}\nKelas: {nama_kelas}\nPertemuan: {pertemuan_ke}\nHari/Tanggal: {hari_tanggal}\n\nHadir: {total_hadir}\nTerlambat: {total_terlambat}\nIzin: {total_izin}\nSakit: {total_sakit}\nAlpha: {total_alpha}\nBelum absen: {total_belum}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template ringkasan kehadiran kelas untuk dosen'),
    ('whatsapp_template_absence_notice', '*Informasi Ketidakhadiran*\n\nHalo {nama_mhs},\nAnda tercatat TIDAK HADIR pada:\nMata kuliah: {nama_mk}\nKelas: {nama_kelas}\nPertemuan: {pertemuan_ke}\nHari/Tanggal: {hari_tanggal}\nStatus: {status}\n\nJika terdapat kekeliruan, hubungi dosen atau bagian akademik.\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template pemberitahuan ketidakhadiran mahasiswa'),
    ('whatsapp_template_announcement', '*{judul}*\n\n{pesan}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template pengumuman WhatsApp'),
    ('whatsapp_template_admin_alert', '*Notifikasi Admin*\n\n{pesan}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template notifikasi aktivitas penting untuk admin'),
    ('whatsapp_template_admin_attendance_summary', '*Ringkasan Absensi Harian*\n\nTanggal: {hari_tanggal}\nTotal absensi tercatat: {total_absensi}\nHadir: {total_hadir}\nTerlambat: {total_terlambat}\nIzin: {total_izin}\nSakit: {total_sakit}\nAlpha: {total_alpha}\n\nPesan otomatis dari {app_name}.', 'whatsapp', 'text', 0, 'Template ringkasan absensi harian untuk admin');
