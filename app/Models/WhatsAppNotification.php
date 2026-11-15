<?php

namespace App\Models;

use App\Core\Model;
use PDOException;
use Throwable;

class WhatsAppNotification extends Model
{
    protected string $table = 'whatsapp_notifications';
    protected string $primaryKey = 'id';

    /**
     * Masukkan ke outbox secara idempoten. Null berarti event sudah pernah ada.
     */
    public function enqueue(array $data): ?int
    {
        try {
            return (int) $this->insert($data);
        } catch (PDOException $e) {
            // 23000 = pelanggaran UNIQUE (event_key). Ini deduplikasi yang diharapkan.
            if ((string) $e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }
    }

    public function eventExists(string $eventKey): bool
    {
        $rows = $this->query(
            "SELECT 1 FROM whatsapp_notifications WHERE event_key = :event_key LIMIT 1",
            ['event_key' => $eventKey]
        );

        return !empty($rows);
    }

    /**
     * Eksekusi statement tulis (UPDATE/INSERT) tanpa fetch.
     */
    public function execSql(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function getStatusCounts(): array
    {
        $counts = [
            'pending' => 0,
            'processing' => 0,
            'accepted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        try {
            $rows = $this->query("SELECT status, COUNT(*) AS total FROM whatsapp_notifications GROUP BY status");
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (Throwable) {
            // Tabel belum dimigrasikan atau DB tidak tersedia.
        }

        return $counts;
    }

    /**
     * Waktu pengiriman/percobaan terakhir untuk dashboard admin.
     */
    public function getLastSendSummary(): array
    {
        $summary = [
            'last_accepted_at' => null,
            'last_attempt_at' => null,
        ];

        try {
            $rows = $this->query(
                "SELECT MAX(accepted_at) AS last_accepted_at,
                        MAX(last_attempt_at) AS last_attempt_at
                 FROM whatsapp_notifications"
            );
            if (!empty($rows[0])) {
                $summary['last_accepted_at'] = $rows[0]['last_accepted_at'] ?: null;
                $summary['last_attempt_at'] = $rows[0]['last_attempt_at'] ?: null;
            }
        } catch (Throwable) {
            // Tabel belum dimigrasikan atau DB tidak tersedia.
        }

        return $summary;
    }

    /**
     * Riwayat ringkas untuk dashboard: status, penerima, jadwal, dan alasan skip.
     * Nomor telepon tidak pernah diambil dari outbox.
     */
    public function getRecentDetailed(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        try {
            $rows = $this->query(
                "SELECT wn.id, wn.event_key, wn.notification_type, wn.status, wn.recipient_type,
                        wn.recipient_id, wn.recipient_ref, wn.payload_json, wn.attempts,
                        wn.scheduled_at, wn.last_attempt_at, wn.accepted_at,
                        wn.provider_message_id, wn.provider_request_id, wn.error_message,
                        wn.simulated, wn.created_at,
                        m.nim, m.nama_lengkap AS mahasiswa_nama,
                        d.nidn, d.nama_lengkap AS dosen_nama,
                        ua.username AS admin_username, ua.nama_lengkap AS admin_nama
                 FROM whatsapp_notifications wn
                 LEFT JOIN mahasiswa m
                    ON wn.recipient_type = 'mahasiswa' AND m.id = wn.recipient_id
                 LEFT JOIN dosen d
                    ON wn.recipient_type = 'dosen' AND d.id = wn.recipient_id
                 LEFT JOIN users ua
                    ON wn.recipient_type = 'admin' AND ua.id = wn.recipient_id
                 ORDER BY wn.id DESC
                 LIMIT {$limit}"
            );
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as &$row) {
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Ambil kontak penerima beserta alasan aman bila kontak tidak bisa dipakai.
     * Nomor lengkap hanya dikembalikan untuk kebutuhan pengiriman, bukan untuk log.
     *
     * @return array{phone: ?string, reason: ?string}
     */
    public function getRecipientContact(string $recipientType, ?int $recipientId): array
    {
        $result = ['phone' => null, 'reason' => 'Penerima notifikasi tidak ditemukan.'];

        if ($recipientId === null || $recipientId <= 0) {
            return $result;
        }

        if ($recipientType === 'mahasiswa') {
            $rows = $this->query(
                "SELECT m.no_hp, m.status_akademik, u.is_active, u.role
                 FROM mahasiswa m
                 JOIN users u ON u.id = m.user_id
                 WHERE m.id = :id
                 LIMIT 1",
                ['id' => $recipientId]
            );

            if (empty($rows)) {
                $result['reason'] = 'Data mahasiswa penerima tidak ditemukan.';
                return $result;
            }

            $row = $rows[0];
            if ((string) ($row['role'] ?? '') !== 'mahasiswa') {
                $result['reason'] = 'Akun penerima bukan mahasiswa.';
                return $result;
            }

            if ((int) ($row['is_active'] ?? 0) !== 1) {
                $result['reason'] = 'Akun mahasiswa tidak aktif.';
                return $result;
            }

            if ((string) ($row['status_akademik'] ?? '') !== 'aktif') {
                $result['reason'] = 'Status akademik mahasiswa tidak aktif.';
                return $result;
            }

            $phone = trim((string) ($row['no_hp'] ?? ''));
            if ($phone === '') {
                $result['reason'] = 'Nomor WhatsApp belum diisi.';
                return $result;
            }

            $result['phone'] = $phone;
            $result['reason'] = null;
            return $result;
        }

        if ($recipientType === 'dosen') {
            $rows = $this->query(
                "SELECT d.no_hp, u.is_active, u.role
                 FROM dosen d
                 JOIN users u ON u.id = d.user_id
                 WHERE d.id = :id
                 LIMIT 1",
                ['id' => $recipientId]
            );

            if (empty($rows)) {
                $result['reason'] = 'Data dosen penerima tidak ditemukan.';
                return $result;
            }

            $row = $rows[0];
            if ((string) ($row['role'] ?? '') !== 'dosen') {
                $result['reason'] = 'Akun penerima bukan dosen.';
                return $result;
            }

            if ((int) ($row['is_active'] ?? 0) !== 1) {
                $result['reason'] = 'Akun dosen tidak aktif.';
                return $result;
            }

            $phone = trim((string) ($row['no_hp'] ?? ''));
            if ($phone === '') {
                $result['reason'] = 'Nomor WhatsApp belum diisi.';
                return $result;
            }

            $result['phone'] = $phone;
            $result['reason'] = null;
            return $result;
        }

        if ($recipientType === 'admin') {
            try {
                $rows = $this->query(
                    "SELECT u.no_hp, u.is_active, u.role
                     FROM users u
                     WHERE u.id = :id
                     LIMIT 1",
                    ['id' => $recipientId]
                );
            } catch (Throwable) {
                $result['reason'] = 'Kolom nomor WhatsApp admin belum tersedia.';
                return $result;
            }

            if (empty($rows)) {
                $result['reason'] = 'Data admin penerima tidak ditemukan.';
                return $result;
            }

            $row = $rows[0];
            if ((string) ($row['role'] ?? '') !== 'admin') {
                $result['reason'] = 'Akun penerima bukan admin.';
                return $result;
            }

            if ((int) ($row['is_active'] ?? 0) !== 1) {
                $result['reason'] = 'Akun admin tidak aktif.';
                return $result;
            }

            $phone = trim((string) ($row['no_hp'] ?? ''));
            if ($phone === '') {
                $result['reason'] = 'Nomor WhatsApp belum diisi.';
                return $result;
            }

            $result['phone'] = $phone;
            $result['reason'] = null;
            return $result;
        }

        $result['reason'] = 'Jenis penerima notifikasi tidak dikenal.';
        return $result;
    }

    /**
     * Ambil nomor dari profil yang benar-benar ada. Admin tidak memiliki kolom nomor.
     */
    public function getRecipientPhone(string $recipientType, ?int $recipientId): ?string
    {
        return $this->getRecipientContact($recipientType, $recipientId)['phone'];
    }

    /**
     * Klaim antrean secara atomik agar dua worker tidak memproses baris yang sama.
     */
    public function claimPending(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $this->recoverStale();

        // Hormati transaksi luar (misalnya test suite). Klaim tetap atomik, tetapi
        // commit/rollback hanya dilakukan bila metode ini yang memulai transaksi.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->query(
                "SELECT * FROM whatsapp_notifications
                 WHERE status = 'pending' AND scheduled_at <= NOW()
                 ORDER BY scheduled_at ASC, id ASC
                 LIMIT {$limit}
                 FOR UPDATE SKIP LOCKED"
            );
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                if ($ownsTransaction) {
                    $this->db->commit();
                }
                return [];
            }

            $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update = $this->db->prepare(
                "UPDATE whatsapp_notifications
                 SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW()
                 WHERE id IN ({$placeholders})"
            );
            $update->execute($ids);

            foreach ($rows as &$row) {
                $row['status'] = 'processing';
                $row['attempts'] = (int) $row['attempts'] + 1;
            }
            unset($row);

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $rows;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Kembalikan baris processing yang tertinggal karena worker berhenti.
     */
    public function recoverStale(int $minutes = 15): int
    {
        $minutes = max(1, min(1440, $minutes));
        $affected = 0;

        $failed = $this->db->exec(
            "UPDATE whatsapp_notifications
             SET status = 'failed', error_message = 'Worker berhenti sebelum menyelesaikan pengiriman.'
             WHERE status = 'processing'
               AND attempts >= 3
               AND last_attempt_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        );
        $affected += is_int($failed) ? $failed : 0;

        $pending = $this->db->exec(
            "UPDATE whatsapp_notifications
             SET status = 'pending', error_message = 'Antrean dipulihkan setelah worker berhenti.'
             WHERE status = 'processing'
               AND attempts < 3
               AND last_attempt_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        );
        $affected += is_int($pending) ? $pending : 0;

        return $affected;
    }

    /**
     * Jangan kirim notifikasi yang sudah terlalu lama tertahan di antrean.
     */
    public function expirePending(int $hours = 24): int
    {
        $hours = max(1, min(720, $hours));

        $affected = $this->db->exec(
            "UPDATE whatsapp_notifications
             SET status = 'skipped', error_message = 'Kedaluwarsa karena tertahan di antrean lebih dari {$hours} jam.'
             WHERE status = 'pending'
               AND scheduled_at < DATE_SUB(NOW(), INTERVAL {$hours} HOUR)"
        );

        return is_int($affected) ? $affected : 0;
    }

    public function markAccepted(int $id, string $providerMessageId, string $requestId, bool $simulated = false): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE whatsapp_notifications
             SET status = 'accepted', accepted_at = NOW(), error_message = NULL,
                 provider_message_id = :message_id, provider_request_id = :request_id,
                 simulated = :simulated
             WHERE id = :id"
        );

        return $stmt->execute([
            'message_id' => mb_substr($providerMessageId, 0, 100),
            'request_id' => mb_substr($requestId, 0, 100),
            'simulated' => $simulated ? 1 : 0,
            'id' => $id,
        ]);
    }

    /**
     * Admin aktif yang memiliki nomor WhatsApp valid.
     *
     * @return array<int, array{id: int, nama: string, no_hp: string}>
     */
    public function getActiveAdmins(): array
    {
        try {
            $rows = $this->query(
                "SELECT u.id, u.nama_lengkap AS nama, u.no_hp
                 FROM users u
                 WHERE u.role = 'admin'
                   AND u.is_active = 1
                   AND u.no_hp IS NOT NULL
                   AND TRIM(u.no_hp) <> ''
                 ORDER BY u.id ASC"
            );
        } catch (Throwable) {
            return [];
        }

        $admins = [];
        foreach ($rows as $row) {
            $phone = trim((string) ($row['no_hp'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $admins[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nama' => (string) ($row['nama'] ?? ''),
                'no_hp' => $phone,
            ];
        }

        return $admins;
    }

    /**
     * Riwayat pesan gagal untuk panel kegagalan admin.
     */
    public function getFailedDetailed(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        try {
            return $this->query(
                "SELECT id, event_key, notification_type, recipient_type, recipient_id,
                        recipient_ref, status, attempts, last_attempt_at, error_message, created_at
                 FROM whatsapp_notifications
                 WHERE status = 'failed'
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
        } catch (Throwable) {
            return [];
        }
    }

    public function markFailed(int $id, string $errorMessage, bool $retryable, int $attempts): bool
    {
        $errorMessage = mb_substr(trim($errorMessage), 0, 255);
        if ($errorMessage === '') {
            $errorMessage = 'Pengiriman gagal tanpa keterangan.';
        }

        if ($retryable && $attempts < 3) {
            $stmt = $this->db->prepare(
                "UPDATE whatsapp_notifications
                 SET status = 'pending', error_message = :error,
                     scheduled_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                 WHERE id = :id"
            );
        } else {
            $stmt = $this->db->prepare(
                "UPDATE whatsapp_notifications
                 SET status = 'failed', error_message = :error
                 WHERE id = :id"
            );
        }

        return $stmt->execute(['error' => $errorMessage, 'id' => $id]);
    }

    public function markSkipped(int $id, string $reason): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE whatsapp_notifications
             SET status = 'skipped', error_message = :reason
             WHERE id = :id"
        );

        return $stmt->execute([
            'reason' => mb_substr(trim($reason), 0, 255),
            'id' => $id,
        ]);
    }

    public function getRecent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        return $this->query(
            "SELECT notification_type, status, recipient_ref, created_at, error_message
             FROM whatsapp_notifications
             ORDER BY id DESC
             LIMIT {$limit}"
        );
    }

    /**
     * Simpan riwayat pengumuman WhatsApp yang dibuat admin.
     */
    public function createAnnouncement(array $data): ?int
    {
        try {
            return $this->insertToTable('whatsapp_announcements', $data);
        } catch (Throwable) {
            return null;
        }
    }

    public function updateAnnouncementRecipientCount(int $id, int $count): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE whatsapp_announcements SET recipient_count = :count WHERE id = :id"
            );
            $stmt->execute(['count' => max(0, $count), 'id' => $id]);
        } catch (Throwable) {
            // Riwayat pengumuman bersifat informatif; kegagalan update tidak
            // boleh menggagalkan proses enqueue.
        }
    }

    public function getRecentAnnouncements(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        try {
            return $this->query(
                "SELECT a.id, a.judul, a.pesan, a.target_type, a.target_ref,
                        a.recipient_count, a.created_at, u.username AS admin_username
                 FROM whatsapp_announcements a
                 LEFT JOIN users u ON u.id = a.created_by
                 ORDER BY a.id DESC
                 LIMIT {$limit}"
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Insert generik ke tabel tertentu tanpa mengubah properti $table model.
     */
    private function insertToTable(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }
}
