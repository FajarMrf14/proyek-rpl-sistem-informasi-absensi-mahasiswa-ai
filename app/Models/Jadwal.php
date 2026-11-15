<?php

namespace App\Models;

use App\Core\Model;

class Jadwal extends Model
{
    protected string $table = 'jadwal_kuliah';
    protected string $primaryKey = 'id';

    /**
     * Hari yang boleh dipilih pada form admin. Skema database masih memuat
     * enum Minggu untuk kompatibilitas data lama, tetapi jadwal baru dibatasi
     * Senin-Sabtu sesuai kebutuhan akademik.
     */
    public const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public const DAY_ORDER_SQL = "FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')";

    /**
     * Mengambil jadwal perkuliahan lengkap beserta informasi mata kuliah, dosen, dan kelas
     */
    public function getJadwalWithDetails(?string $hari = null, ?int $tahunAkademikId = null): array
    {
        $sql = "
            SELECT 
                jk.*, 
                mk.kode_mk, mk.nama_mk, mk.sks, mk.semester_paket,
                d.nidn, d.nama_lengkap as nama_dosen, d.gelar_depan, d.gelar_belakang,
                k.nama_kelas, k.prodi,
                ta.nama_tahun as tahun_akademik, ta.semester
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON jk.tahun_akademik_id = ta.id
            WHERE jk.is_active = 1
        ";

        $params = [];
        if ($hari !== null) {
            $sql .= " AND jk.hari = :hari";
            $params['hari'] = $hari;
        }

        if ($tahunAkademikId !== null) {
            $sql .= " AND jk.tahun_akademik_id = :ta_id";
            $params['ta_id'] = $tahunAkademikId;
        }

        $sql .= " ORDER BY " . self::DAY_ORDER_SQL . ", jk.jam_mulai ASC";

        return $this->query($sql, $params);
    }

    /**
     * Daftar jadwal untuk halaman admin, termasuk jadwal nonaktif.
     */
    public function getAllWithDetails(array $filters = []): array
    {
        $sql = "
            SELECT
                jk.*,
                mk.kode_mk, mk.nama_mk, mk.sks, mk.semester_paket,
                d.nidn, d.nama_lengkap AS nama_dosen, d.gelar_depan, d.gelar_belakang,
                k.nama_kelas, k.prodi, k.angkatan,
                ta.nama_tahun, ta.semester, ta.is_active AS ta_is_active,
                (SELECT COUNT(*) FROM kelas_mahasiswa km
                 WHERE km.jadwal_id = jk.id AND km.status = 'aktif') AS total_peserta
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON jk.tahun_akademik_id = ta.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['hari'])) {
            $sql .= " AND jk.hari = :hari";
            $params['hari'] = (string) $filters['hari'];
        }

        if (!empty($filters['kelas_id'])) {
            $sql .= " AND jk.kelas_id = :kelas_id";
            $params['kelas_id'] = (int) $filters['kelas_id'];
        }

        if (!empty($filters['dosen_id'])) {
            $sql .= " AND jk.dosen_id = :dosen_id";
            $params['dosen_id'] = (int) $filters['dosen_id'];
        }

        if (!empty($filters['mata_kuliah_id'])) {
            $sql .= " AND jk.mata_kuliah_id = :mata_kuliah_id";
            $params['mata_kuliah_id'] = (int) $filters['mata_kuliah_id'];
        }

        if (!empty($filters['tahun_akademik_id'])) {
            $sql .= " AND jk.tahun_akademik_id = :tahun_akademik_id";
            $params['tahun_akademik_id'] = (int) $filters['tahun_akademik_id'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $sql .= " AND jk.is_active = :is_active";
            $params['is_active'] = (int) $filters['is_active'];
        }

        $sql .= " ORDER BY " . self::DAY_ORDER_SQL . ", jk.jam_mulai ASC, k.nama_kelas ASC";

        return $this->query($sql, $params);
    }

    /**
     * Detail satu jadwal untuk halaman admin.
     */
    public function findDetailed(int $id): ?array
    {
        $sql = "
            SELECT
                jk.*,
                mk.kode_mk, mk.nama_mk, mk.sks, mk.semester_paket,
                d.nidn, d.nama_lengkap AS nama_dosen, d.gelar_depan, d.gelar_belakang,
                k.nama_kelas, k.prodi, k.angkatan,
                ta.nama_tahun, ta.semester, ta.is_active AS ta_is_active,
                (SELECT COUNT(*) FROM kelas_mahasiswa km
                 WHERE km.jadwal_id = jk.id AND km.status = 'aktif') AS total_peserta
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON jk.tahun_akademik_id = ta.id
            WHERE jk.id = :id
            LIMIT 1
        ";

        $rows = $this->query($sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    /**
     * Pilihan master data untuk form jadwal.
     */
    public function getFormOptions(): array
    {
        return [
            'mata_kuliah' => $this->query(
                "SELECT id, kode_mk, nama_mk, sks, semester_paket, kategori, is_active
                 FROM mata_kuliah
                 ORDER BY is_active DESC, semester_paket ASC, kode_mk ASC"
            ),
            'dosen' => $this->query(
                "SELECT id, nidn, nama_lengkap, gelar_depan, gelar_belakang
                 FROM dosen
                 ORDER BY nama_lengkap ASC"
            ),
            'kelas' => $this->query(
                "SELECT id, nama_kelas, prodi, angkatan
                 FROM kelas
                 ORDER BY nama_kelas ASC"
            ),
            'tahun_akademik' => $this->query(
                "SELECT id, kode_tahun, nama_tahun, semester, tanggal_mulai, tanggal_selesai, is_active
                 FROM tahun_akademik
                 ORDER BY is_active DESC, tanggal_mulai DESC"
            ),
            'ruangan' => $this->query(
                "SELECT DISTINCT ruangan
                 FROM jadwal_kuliah
                 WHERE ruangan IS NOT NULL AND TRIM(ruangan) <> ''
                 ORDER BY ruangan ASC"
            ),
        ];
    }

    /**
     * Cari jadwal aktif yang bentrok pada dosen, kelas, atau ruangan yang sama.
     * Bentrok hanya dihitung dalam tahun akademik dan hari yang sama.
     */
    public function findConflicts(array $data, ?int $excludeId = null): array
    {
        $sql = "
            SELECT
                jk.id, jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan,
                jk.dosen_id, jk.kelas_id, jk.mata_kuliah_id,
                mk.kode_mk, mk.nama_mk,
                d.nama_lengkap AS nama_dosen,
                k.nama_kelas
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            WHERE jk.is_active = 1
              AND jk.tahun_akademik_id = :tahun_akademik_id
              AND jk.hari = :hari
              AND jk.jam_mulai < :jam_selesai
              AND jk.jam_selesai > :jam_mulai
              AND (
                    jk.dosen_id = :dosen_id
                    OR jk.kelas_id = :kelas_id
                    OR LOWER(TRIM(jk.ruangan)) = LOWER(TRIM(:ruangan))
              )
        ";

        $params = [
            'tahun_akademik_id' => (int) $data['tahun_akademik_id'],
            'hari'              => (string) $data['hari'],
            'jam_mulai'         => (string) $data['jam_mulai'],
            'jam_selesai'       => (string) $data['jam_selesai'],
            'dosen_id'          => (int) $data['dosen_id'],
            'kelas_id'          => (int) $data['kelas_id'],
            'ruangan'           => (string) $data['ruangan'],
        ];

        if ($excludeId !== null) {
            $sql .= " AND jk.id <> :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " ORDER BY jk.jam_mulai ASC, jk.id ASC";

        $rows = $this->query($sql, $params);

        foreach ($rows as &$row) {
            $types = [];
            if ((int) $row['dosen_id'] === (int) $data['dosen_id']) {
                $types[] = 'dosen';
            }
            if ((int) $row['kelas_id'] === (int) $data['kelas_id']) {
                $types[] = 'kelas';
            }
            if (mb_strtolower(trim((string) $row['ruangan'])) === mb_strtolower(trim((string) $data['ruangan']))) {
                $types[] = 'ruangan';
            }
            $row['conflict_types'] = $types;
        }
        unset($row);

        return $rows;
    }

    /**
     * Hitung data terkait untuk menentukan hapus permanen vs nonaktifkan.
     */
    public function getRelatedCounts(int $id): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM kelas_mahasiswa WHERE jadwal_id = :id_peserta) AS peserta,
                (SELECT COUNT(*) FROM pertemuan_kuliah WHERE jadwal_id = :id_pertemuan) AS pertemuan,
                (SELECT COUNT(*) FROM absensi a
                 JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
                 WHERE pk.jadwal_id = :id_absensi) AS absensi,
                (SELECT COUNT(*) FROM izin_sakit WHERE jadwal_id = :id_izin) AS izin
        ";

        $rows = $this->query($sql, [
            'id_peserta'   => $id,
            'id_pertemuan' => $id,
            'id_absensi'   => $id,
            'id_izin'      => $id,
        ]);

        $counts = $rows[0] ?? [];
        $counts = [
            'peserta'   => (int) ($counts['peserta'] ?? 0),
            'pertemuan' => (int) ($counts['pertemuan'] ?? 0),
            'absensi'   => (int) ($counts['absensi'] ?? 0),
            'izin'      => (int) ($counts['izin'] ?? 0),
        ];
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Daftar peserta/KRS pada satu jadwal.
     */
    public function getParticipants(int $id): array
    {
        $sql = "
            SELECT
                km.id, km.status,
                m.id AS mahasiswa_id, m.nim, m.nama_lengkap, m.prodi, m.angkatan,
                m.status_akademik
            FROM kelas_mahasiswa km
            JOIN mahasiswa m ON m.id = km.mahasiswa_id
            WHERE km.jadwal_id = :jadwal_id
            ORDER BY m.nim ASC
        ";

        return $this->query($sql, ['jadwal_id' => $id]);
    }

    /**
     * Salin peserta aktif dari jadwal lain pada kelas yang sama. Ini membantu
     * jadwal baru langsung terhubung ke KRS/absensi tanpa membuat mekanisme
     * absensi baru. Jika tidak ada jadwal sumber, tidak ada peserta yang disalin.
     */
    public function copyParticipantsFromClass(int $newJadwalId, int $kelasId, int $tahunAkademikId): int
    {
        $sourceRows = $this->query(
            "SELECT km.jadwal_id
             FROM kelas_mahasiswa km
             JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
             WHERE jk.kelas_id = :kelas_id
               AND jk.tahun_akademik_id = :tahun_akademik_id
               AND km.status = 'aktif'
               AND jk.id <> :new_jadwal_id
             GROUP BY km.jadwal_id
             ORDER BY COUNT(*) DESC, jk.id DESC
             LIMIT 1",
            [
                'kelas_id'            => $kelasId,
                'new_jadwal_id'       => $newJadwalId,
                'tahun_akademik_id'   => $tahunAkademikId,
            ]
        );

        if (empty($sourceRows)) {
            return 0;
        }

        $sourceId = (int) $sourceRows[0]['jadwal_id'];

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO kelas_mahasiswa (jadwal_id, mahasiswa_id, status)
             SELECT :new_jadwal_id, km.mahasiswa_id, 'aktif'
             FROM kelas_mahasiswa km
             WHERE km.jadwal_id = :source_jadwal_id
               AND km.status = 'aktif'"
        );
        $stmt->execute([
            'new_jadwal_id'    => $newJadwalId,
            'source_jadwal_id' => $sourceId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Mengambil daftar jadwal mata kuliah yang diambil oleh mahasiswa tertentu
     */
    public function getStudentSchedules(int $mahasiswaId, ?string $hari = null): array
    {
        $sql = "
            SELECT 
                jk.*, 
                mk.kode_mk, mk.nama_mk, mk.sks, mk.semester_paket,
                d.nidn, d.nama_lengkap as nama_dosen, d.gelar_depan, d.gelar_belakang,
                k.nama_kelas, k.prodi,
                ta.nama_tahun as tahun_akademik, ta.semester,
                pk.id as pertemuan_id, pk.pertemuan_ke, pk.tanggal as tanggal_pertemuan, pk.status as status_pertemuan,
                a.id as absensi_id, a.status as status_absensi, a.waktu_absensi
            FROM kelas_mahasiswa km
            JOIN jadwal_kuliah jk ON km.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON jk.tahun_akademik_id = ta.id
            LEFT JOIN pertemuan_kuliah pk ON pk.id = (
                SELECT pk2.id
                FROM pertemuan_kuliah pk2
                WHERE pk2.jadwal_id = jk.id
                  AND (pk2.status = 'dibuka' OR pk2.tanggal = CURDATE())
                ORDER BY (pk2.tanggal = CURDATE()) DESC,
                         (pk2.status = 'dibuka') DESC,
                         pk2.tanggal DESC,
                         pk2.pertemuan_ke DESC
                LIMIT 1
            )
            LEFT JOIN absensi a ON a.pertemuan_id = pk.id AND a.mahasiswa_id = :mhs_id_abs
            WHERE km.mahasiswa_id = :mahasiswa_id 
              AND km.status = 'aktif'
              AND jk.is_active = 1
              AND ta.is_active = 1
        ";

        $params = [
            'mahasiswa_id'   => $mahasiswaId,
            'mhs_id_abs'     => $mahasiswaId
        ];

        if ($hari !== null) {
            $sql .= " AND jk.hari = :hari";
            $params['hari'] = $hari;
        }

        $sql .= " ORDER BY FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jk.jam_mulai ASC";

        return $this->query($sql, $params);
    }

    /**
     * Mengambil detail satu jadwal spesifik mahasiswa dan memverifikasi kepemilikannya
     */
    public function getStudentScheduleDetail(int $jadwalId, int $mahasiswaId): ?array
    {
        $sql = "
            SELECT 
                jk.*, 
                mk.kode_mk, mk.nama_mk, mk.sks, mk.semester_paket,
                d.nidn, d.nama_lengkap as nama_dosen, d.gelar_depan, d.gelar_belakang, d.no_hp as no_hp_dosen,
                k.nama_kelas, k.prodi,
                ta.nama_tahun as tahun_akademik, ta.semester,
                pk.id as pertemuan_id, pk.pertemuan_ke, pk.tanggal as tanggal_pertemuan, pk.materi_pembahasan, pk.status as status_pertemuan,
                a.id as absensi_id, a.status as status_absensi, a.waktu_absensi
            FROM kelas_mahasiswa km
            JOIN jadwal_kuliah jk ON km.jadwal_id = jk.id
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN dosen d ON jk.dosen_id = d.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON jk.tahun_akademik_id = ta.id
            LEFT JOIN pertemuan_kuliah pk ON pk.id = (
                SELECT pk2.id
                FROM pertemuan_kuliah pk2
                WHERE pk2.jadwal_id = jk.id
                  AND (pk2.status = 'dibuka' OR pk2.tanggal = CURDATE())
                ORDER BY (pk2.tanggal = CURDATE()) DESC,
                         (pk2.status = 'dibuka') DESC,
                         pk2.tanggal DESC,
                         pk2.pertemuan_ke DESC
                LIMIT 1
            )
            LEFT JOIN absensi a ON a.pertemuan_id = pk.id AND a.mahasiswa_id = :mhs_id_abs
            WHERE km.mahasiswa_id = :mahasiswa_id 
              AND jk.id = :jadwal_id
              AND km.status = 'aktif'
              AND jk.is_active = 1
              AND ta.is_active = 1
            LIMIT 1
        ";

        $result = $this->query($sql, [
            'mahasiswa_id' => $mahasiswaId,
            'mhs_id_abs'   => $mahasiswaId,
            'jadwal_id'    => $jadwalId
        ]);

        return !empty($result) ? $result[0] : null;
    }
}
