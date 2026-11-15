<?php

namespace App\Models;

use App\Core\Model;

class SettingKampus extends Model
{
    protected string $table = 'settings_kampus';
    protected string $primaryKey = 'id';

    /**
     * Mengambil konfigurasi aktif lokasi kampus
     */
    public function getActiveSetting(): ?array
    {
        $records = $this->all(['*'], 'id DESC');
        return $records[0] ?? null;
    }
}
