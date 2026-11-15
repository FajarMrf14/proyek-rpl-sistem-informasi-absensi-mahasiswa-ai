<?php

namespace App\Controllers\Dosen;

use App\Core\Controller;
use App\Core\Session;
use App\Config\App as AppConfig;
use App\Models\Jadwal;
use App\Models\Absensi;

class KelasController extends Controller
{
    private Jadwal $jadwalModel;
    private Absensi $absensiModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->jadwalModel = new Jadwal();
        $this->absensiModel = new Absensi();
    }

    /**
     * Tampilkan daftar seluruh kelas yang diampu dosen
     */
    public function index(): void
    {
        $user = Session::get('user');
        $dosenId = (int)($user['dosen_id'] ?? 0);

        $sql = "
            SELECT jk.*, mk.kode_mk, mk.nama_mk, mk.sks, k.nama_kelas, ta.semester
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
            WHERE jk.dosen_id = :dosen_id AND jk.is_active = 1
            ORDER BY FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jk.jam_mulai ASC
        ";
        
        $classes = $this->jadwalModel->query($sql, ['dosen_id' => $dosenId]);

        $data = [
            'title'   => 'Monitoring Kelas - Dosen STMIK IKMI',
            'user'    => $user,
            'classes' => $classes
        ];

        $this->render('dosen/kelas_index', $data);
    }

    /**
     * Tampilkan detail kelas dan rekap absensi mahasiswa
     */
    public function detail(mixed $id): void
    {
        $user = Session::get('user');
        $dosenId = (int)($user['dosen_id'] ?? 0);
        $jadwalId = (int)$id;

        // Validasi otoritas: pastikan jadwal ini benar-benar diampu oleh dosen yang login
        $sqlJadwal = "
            SELECT jk.*, mk.kode_mk, mk.nama_mk, mk.sks, k.nama_kelas, ta.semester
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
            WHERE jk.id = :id AND jk.dosen_id = :dosen_id AND jk.is_active = 1
            LIMIT 1
        ";
        
        $jadwalData = $this->jadwalModel->query($sqlJadwal, [
            'id' => $jadwalId,
            'dosen_id' => $dosenId
        ]);

        if (empty($jadwalData)) {
            Session::setFlash('error', 'Akses ditolak atau kelas tidak ditemukan.');
            $this->response->redirect(base_url('dosen/kelas'));
            return;
        }

        $jadwal = $jadwalData[0];

        // Ambil rekap kehadiran mahasiswa dari VIEW yang sudah ada di database
        $sqlRekap = "
            SELECT * 
            FROM v_rekap_kehadiran_mahasiswa 
            WHERE jadwal_id = :jadwal_id 
            ORDER BY nim ASC
        ";
        
        $rekap = $this->absensiModel->query($sqlRekap, ['jadwal_id' => $jadwalId]);

        // Ambil riwayat pertemuan perkuliahan pada kelas ini
        $sqlPertemuan = "
            SELECT pk.*, 
                   (SELECT COUNT(*) FROM absensi a WHERE a.pertemuan_id = pk.id) as total_absensi
            FROM pertemuan_kuliah pk
            WHERE pk.jadwal_id = :jadwal_id
            ORDER BY pk.pertemuan_ke ASC
        ";
        $pertemuan = $this->absensiModel->query($sqlPertemuan, ['jadwal_id' => $jadwalId]);

        $data = [
            'title'          => 'Detail Kelas ' . $jadwal['nama_mk'] . ' - Dosen',
            'user'           => $user,
            'jadwal'         => $jadwal,
            'rekap'          => $rekap,
            'pertemuan'      => $pertemuan,
            'min_attendance' => (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0)
        ];

        $this->render('dosen/kelas_detail', $data);
    }
}
