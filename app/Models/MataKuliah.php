<?php

namespace App\Models;

use App\Core\Model;

class MataKuliah extends Model
{
    protected string $table = 'mata_kuliah';
    protected string $primaryKey = 'id';

    public function findByKode(string $kode): ?array
    {
        return $this->firstWhere('kode_mk', $kode);
    }
}
