-- ==============================================================================
-- Migrasi aditif: Integrasi Notifikasi WhatsApp (Fonnte)
-- TIDAK menghapus tabel/data yang sudah ada.
-- Jalankan hanya file ini, JANGAN jalankan database/migrate.php (mengandung DROP TABLE).
-- ==============================================================================

-- 1. Tambahkan kelompok pengaturan baru tanpa mengubah baris lama.
ALTER TABLE `app_settings`
    MODIFY COLUMN `setting_group` ENUM('umum', 'chatbot', 'akademik', 'sistem', 'whatsapp')
    NOT NULL DEFAULT 'umum';

-- 2. Outbox/riwayat notifikasi. Nomor telepon dan token TIDAK disimpan di sini.
CREATE TABLE IF NOT EXISTS `whatsapp_notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_key` VARCHAR(191) NOT NULL COMMENT 'Kunci idempotensi untuk mencegah pengiriman duplikat',
    `notification_type` ENUM('schedule_reminder', 'attendance_confirmation', 'low_attendance', 'lecturer_recap', 'test') NOT NULL,
    `recipient_type` ENUM('mahasiswa', 'dosen', 'admin', 'test') NOT NULL,
    `recipient_id` INT NULL COMMENT 'ID profil penerima; NULL untuk pesan uji manual',
    `recipient_ref` VARCHAR(60) NULL COMMENT 'Referensi minimal penerima, bukan nomor telepon lengkap',
    `payload_json` JSON NULL COMMENT 'Data minimal untuk menyusun pesan; tanpa nomor telepon/token',
    `status` ENUM('pending', 'processing', 'accepted', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_attempt_at` DATETIME NULL,
    `accepted_at` DATETIME NULL,
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

-- 3. Nilai awal yang aman. Integrasi nonaktif sampai admin mengisi token dan mengaktifkannya.
INSERT IGNORE INTO `app_settings`
    (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `is_encrypted`, `description`)
VALUES
    ('whatsapp_enabled', '0', 'whatsapp', 'boolean', 0, 'Status integrasi WhatsApp/Fonnte (1=Aktif, 0=Nonaktif)'),
    ('fonnte_token', '', 'whatsapp', 'string', 1, 'Token Fonnte (Terenkripsi, jangan pernah ditampilkan)'),
    ('whatsapp_schedule_reminder_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim pengingat jadwal sebelum kelas'),
    ('whatsapp_attendance_confirmation_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim konfirmasi setelah absensi tercatat'),
    ('whatsapp_low_attendance_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim peringatan kehadiran rendah'),
    ('whatsapp_lecturer_recap_enabled', '0', 'whatsapp', 'boolean', 0, 'Kirim rekap absensi mingguan ke dosen'),
    ('whatsapp_reminder_minutes', '30', 'whatsapp', 'integer', 0, 'Menit sebelum kelas untuk pengingat');
