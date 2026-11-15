<?php

namespace App\Models;

use App\Core\Model;

class Dosen extends Model
{
    protected string $table = 'dosen';
    protected string $primaryKey = 'id';

    public function findByNidn(string $nidn): ?array
    {
        return $this->firstWhere('nidn', $nidn);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->firstWhere('user_id', $userId);
    }
}
