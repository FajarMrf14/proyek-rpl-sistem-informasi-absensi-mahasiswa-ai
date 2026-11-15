<?php

namespace App\Models;

use App\Core\Model;

class Mahasiswa extends Model
{
    protected string $table = 'mahasiswa';
    protected string $primaryKey = 'id';

    /**
     * Cari mahasiswa berdasarkan NIM
     */
    public function findByNim(string $nim): ?array
    {
        return $this->firstWhere('nim', $nim);
    }

    /**
     * Cari data mahasiswa berdasarkan User ID
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->firstWhere('user_id', $userId);
    }

    /**
     * Update Face Descriptor (biometrik AI)
     */
    public function updateFaceDescriptor(int $id, string $descriptorJson, string $photoPath): bool
    {
        return $this->update($id, [
            'face_descriptor' => $descriptorJson,
            'face_photo'      => $photoPath
        ]);
    }
}
