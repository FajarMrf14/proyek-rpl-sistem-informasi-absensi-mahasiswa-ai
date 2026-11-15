<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    /**
     * Cari user berdasarkan username (NIM / NIDN / Admin username)
     */
    public function findByUsername(string $username): ?array
    {
        return $this->firstWhere('username', $username);
    }

    /**
     * Cari user dengan profil lengkap sesuai role
     */
    public function getFullProfile(int $userId): ?array
    {
        $user = $this->find($userId);
        if (!$user) return null;

        if ($user['role'] === 'mahasiswa') {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id, u.username, u.email as user_email, u.role, u.avatar, u.is_active, u.last_login_at,
                    m.id as mahasiswa_id, m.nim, m.nama_lengkap, m.prodi, m.angkatan, m.jenis_kelamin, 
                    m.no_hp, m.status_akademik, m.face_registered,
                    w.id as wajah_id, w.status as status_wajah, w.foto_path as foto_wajah
                FROM users u
                LEFT JOIN mahasiswa m ON m.user_id = u.id
                LEFT JOIN wajah_mahasiswa w ON w.mahasiswa_id = m.id
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $userId]);
            return $stmt->fetch() ?: $user;
        }

        if ($user['role'] === 'dosen') {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id, u.username, u.email as user_email, u.role, u.avatar, u.is_active, u.last_login_at,
                    d.id as dosen_id, d.nidn, d.nama_lengkap, d.gelar_depan, d.gelar_belakang, d.no_hp, d.jenis_kelamin
                FROM users u
                LEFT JOIN dosen d ON d.user_id = u.id
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $userId]);
            $res = $stmt->fetch();
            if ($res) {
                $nama = $res['nama_lengkap'];
                // Hindari duplikasi gelar depan jika nama sudah diawali gelar tersebut
                if (!empty($res['gelar_depan']) && !str_starts_with($nama, $res['gelar_depan'])) {
                    $nama = $res['gelar_depan'] . ' ' . $nama;
                }
                // Tambahkan gelar belakang jika ada dan belum ada di akhir nama
                if (!empty($res['gelar_belakang']) && !str_ends_with($nama, $res['gelar_belakang'])) {
                    $nama .= ', ' . $res['gelar_belakang'];
                }
                $res['nama_dengan_gelar'] = $nama;
                return $res;
            }
            return $user;
        }

        if ($user['role'] === 'admin') {
            if (!empty($user['nama_lengkap'])) {
                $user['nama_dengan_gelar'] = $user['nama_lengkap'];
            }
            return $user;
        }

        return $user;
    }

    /**
     * Update nama lengkap user & sinkronkan ke tabel profil terkait
     */
    public function updateFullName(int $userId, string $namaLengkap): bool
    {
        $namaLengkap = trim($namaLengkap);
        $user = $this->find($userId);
        if (!$user) return false;

        $stmt = $this->db->prepare("UPDATE users SET nama_lengkap = :nama WHERE id = :id");
        $stmt->execute(['nama' => $namaLengkap, 'id' => $userId]);

        if ($user['role'] === 'dosen') {
            $stmtD = $this->db->prepare("UPDATE dosen SET nama_lengkap = :nama WHERE user_id = :uid");
            $stmtD->execute(['nama' => $namaLengkap, 'uid' => $userId]);
        } elseif ($user['role'] === 'mahasiswa') {
            $stmtM = $this->db->prepare("UPDATE mahasiswa SET nama_lengkap = :nama WHERE user_id = :uid");
            $stmtM->execute(['nama' => $namaLengkap, 'uid' => $userId]);
        }

        return true;
    }

    /**
     * Update nomor WhatsApp pada tabel profil yang sesuai role.
     * Nomor disimpan sebagai null bila dikosongkan.
     */
    public function updateContactPhone(int $userId, ?string $phone): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        $phone = $phone !== null ? trim($phone) : null;
        if ($phone === '') {
            $phone = null;
        }

        if ($user['role'] === 'dosen') {
            $stmt = $this->db->prepare("UPDATE dosen SET no_hp = :phone WHERE user_id = :uid");
            return $stmt->execute(['phone' => $phone, 'uid' => $userId]);
        }

        if ($user['role'] === 'mahasiswa') {
            $stmt = $this->db->prepare("UPDATE mahasiswa SET no_hp = :phone WHERE user_id = :uid");
            return $stmt->execute(['phone' => $phone, 'uid' => $userId]);
        }

        if ($user['role'] === 'admin') {
            $stmt = $this->db->prepare("UPDATE users SET no_hp = :phone WHERE id = :uid");
            return $stmt->execute(['phone' => $phone, 'uid' => $userId]);
        }

        return false;
    }

    /**
     * Update timestamp login terakhir
     */
    public function updateLastLogin(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $userId]);
    }
}
