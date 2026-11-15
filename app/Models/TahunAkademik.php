<?php

namespace App\Models;

use App\Core\Model;
use Throwable;

class TahunAkademik extends Model
{
    protected string $table = 'tahun_akademik';
    protected string $primaryKey = 'id';

    /**
     * Mengambil tahun akademik yang sedang aktif
     */
    public function getActive(): ?array
    {
        return $this->firstWhere('is_active', 1);
    }

    /**
     * Mengatur satu tahun akademik sebagai aktif dan menonaktifkan lainnya
     */
    public function setActive(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            // 1. Set semua menjadi nonaktif
            $sqlDeactivate = "UPDATE {$this->table} SET is_active = 0";
            $this->db->exec($sqlDeactivate);

            // 2. Set yang dipilih menjadi aktif
            $updated = $this->update($id, ['is_active' => 1]);

            if (!$updated) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[TahunAkademik::setActive] ' . $e->getMessage());
            return false;
        }
    }
}
