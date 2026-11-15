<?php

namespace App\Models;

use App\Core\Model;

class WajahMahasiswa extends Model
{
    protected string $table = 'wajah_mahasiswa';
    protected string $primaryKey = 'id';

    /**
     * Ambil descriptor biometrik wajah berdasarkan ID Mahasiswa
     */
    public function getDescriptorByMahasiswaId(int $mahasiswaId): ?array
    {
        return $this->firstWhere('mahasiswa_id', $mahasiswaId);
    }
}
