<?php

namespace App\Models;

use App\Core\Model;

class Pertemuan extends Model
{
    protected string $table = 'pertemuan_kuliah';
    protected string $primaryKey = 'id';

    /**
     * Ambil daftar pertemuan pada jadwal tertentu
     */
    public function getByJadwal(int $jadwalId): array
    {
        return $this->where('jadwal_id', $jadwalId, null, ['*']);
    }

    /**
     * Ambil sesi pertemuan yang sedang dibuka hari ini untuk suatu jadwal
     */
    public function getActiveSession(int $jadwalId): ?array
    {
        $sql = "
            SELECT pk.*, jk.toleransi_keterlambatan, mk.nama_mk, k.nama_kelas
            FROM pertemuan_kuliah pk
            JOIN jadwal_kuliah jk ON pk.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN kelas k ON jk.kelas_id = k.id
            WHERE pk.jadwal_id = :jadwal_id 
              AND pk.status = 'dibuka'
              AND pk.tanggal = CURDATE()
            LIMIT 1
        ";

        $result = $this->query($sql, ['jadwal_id' => $jadwalId]);
        return $result[0] ?? null;
    }
}
