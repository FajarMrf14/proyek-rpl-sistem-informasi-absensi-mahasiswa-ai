<?php

namespace App\Models;

use App\Core\Model;

class Absensi extends Model
{
    protected string $table = 'absensi';
    protected string $primaryKey = 'id';

    /**
     * Cek apakah mahasiswa sudah melakukan absensi pada pertemuan tertentu
     */
    public function hasAttended(int $pertemuanId, int $mahasiswaId): bool
    {
        $sql = "SELECT id FROM absensi WHERE pertemuan_id = :pertemuan_id AND mahasiswa_id = :mahasiswa_id LIMIT 1";
        $result = $this->query($sql, [
            'pertemuan_id' => $pertemuanId,
            'mahasiswa_id' => $mahasiswaId
        ]);

        return !empty($result);
    }

    /**
     * Hitung ringkasan persentase kehadiran mahasiswa dari database view per mata kuliah
     */
    public function getStudentAttendanceSummary(int $mahasiswaId, ?int $jadwalId = null): array
    {
        $sql = "SELECT * FROM v_rekap_kehadiran_mahasiswa WHERE mahasiswa_id = :mahasiswa_id";
        $params = ['mahasiswa_id' => $mahasiswaId];

        if ($jadwalId !== null) {
            $sql .= " AND jadwal_id = :jadwal_id";
            $params['jadwal_id'] = $jadwalId;
        }

        return $this->query($sql, $params);
    }

    /**
     * Mengambil statistik akumulatif kehadiran mahasiswa secara keseluruhan dari database
     */
    public function getStudentOverallStats(int $mahasiswaId): array
    {
        $mkSummaries = $this->getStudentAttendanceSummary($mahasiswaId);

        $totalPertemuan = 0;
        $totalHadir = 0;
        $totalTerlambat = 0;
        $totalIzin = 0;
        $totalSakit = 0;
        $totalAlpha = 0;

        foreach ($mkSummaries as $mk) {
            $selesai = (int)($mk['total_pertemuan_selesai'] ?? 0);
            $h = (int)($mk['total_hadir'] ?? 0);
            $t = (int)($mk['total_terlambat'] ?? 0);
            $i = (int)($mk['total_izin'] ?? 0);
            $s = (int)($mk['total_sakit'] ?? 0);
            $a = (int)($mk['total_alpha'] ?? 0);

            // Jika ada pertemuan selesai tapi belum ada record absensi, hitung sebagai alpha otomatis
            $unrecorded = max(0, $selesai - ($h + $t + $i + $s + $a));
            $a += $unrecorded;

            $totalPertemuan += $selesai;
            $totalHadir += $h;
            $totalTerlambat += $t;
            $totalIzin += $i;
            $totalSakit += $s;
            $totalAlpha += $a;
        }

        $kehadiranEfektif = $totalHadir + $totalTerlambat;
        $percentage = ($totalPertemuan > 0)
            ? round(($kehadiranEfektif / $totalPertemuan) * 100, 1)
            : 100.0;

        return [
            'total_pertemuan'      => $totalPertemuan,
            'hadir'                => $totalHadir,
            'terlambat'            => $totalTerlambat,
            'total_kehadiran'      => $kehadiranEfektif,
            'izin'                 => $totalIzin,
            'sakit'                => $totalSakit,
            'alpha'                => $totalAlpha,
            'persentase_kehadiran' => $percentage,
            'mk_summaries'         => $mkSummaries
        ];
    }

    /**
     * Mengambil riwayat log absensi lengkap mahasiswa dari database
     */
    public function getStudentHistory(int $mahasiswaId): array
    {
        $sql = "
            SELECT 
                a.*, 
                pk.pertemuan_ke, pk.tanggal as tanggal_pertemuan, pk.materi_pembahasan,
                jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan,
                mk.kode_mk, mk.nama_mk,
                d.nama_lengkap as nama_dosen, d.gelar_depan, d.gelar_belakang,
                k.nama_kelas
            FROM absensi a
            JOIN pertemuan_kuliah pk ON a.pertemuan_id = pk.id
            JOIN jadwal_kuliah jk ON pk.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            WHERE a.mahasiswa_id = :mahasiswa_id
            ORDER BY a.waktu_absensi DESC, pk.tanggal DESC
        ";

        return $this->query($sql, ['mahasiswa_id' => $mahasiswaId]);
    }
}
