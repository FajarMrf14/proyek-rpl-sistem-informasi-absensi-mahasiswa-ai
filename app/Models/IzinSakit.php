<?php

namespace App\Models;

use App\Core\Model;

class IzinSakit extends Model
{
    protected string $table = 'izin_sakit';
    protected string $primaryKey = 'id';

    /**
     * Mengambil daftar riwayat pengajuan izin/sakit mahasiswa dari database
     */
    public function getStudentLeaves(int $mahasiswaId): array
    {
        $sql = "
            SELECT 
                iz.*,
                mk.kode_mk, mk.nama_mk,
                d.nama_lengkap as nama_dosen,
                pk.pertemuan_ke
            FROM izin_sakit iz
            JOIN jadwal_kuliah jk ON iz.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            LEFT JOIN pertemuan_kuliah pk ON iz.pertemuan_id = pk.id
            WHERE iz.mahasiswa_id = :mahasiswa_id
            ORDER BY iz.created_at DESC
        ";

        return $this->query($sql, ['mahasiswa_id' => $mahasiswaId]);
    }
    public function getAllLeaves(array $filters = []): array
    {
        $sql = "
            SELECT 
                iz.*,
                m.nim, m.nama_lengkap as nama_mahasiswa,
                mk.kode_mk, mk.nama_mk,
                d.nama_lengkap as nama_dosen
            FROM izin_sakit iz
            JOIN mahasiswa m ON iz.mahasiswa_id = m.id
            JOIN jadwal_kuliah jk ON iz.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND iz.status_persetujuan = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['tipe'])) {
            $sql .= " AND iz.tipe = :tipe";
            $params['tipe'] = $filters['tipe'];
        }

        if (!empty($filters['tanggal'])) {
            $sql .= " AND (iz.tanggal_mulai <= :tanggal AND iz.tanggal_selesai >= :tanggal)";
            $params['tanggal'] = $filters['tanggal'];
        }

        $sql .= " ORDER BY iz.created_at DESC";

        return $this->query($sql, $params);
    }
}
