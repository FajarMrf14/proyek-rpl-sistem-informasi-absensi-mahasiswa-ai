<?php

namespace App\Controllers\Dosen;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Jadwal;
use App\Models\Pertemuan;
use App\Models\IzinSakit;

class DashboardController extends Controller
{
    private Jadwal $jadwalModel;
    private Pertemuan $pertemuanModel;
    private IzinSakit $izinModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->jadwalModel = new Jadwal();
        $this->pertemuanModel = new Pertemuan();
        $this->izinModel = new IzinSakit();
    }

    /**
     * Dashboard Dosen Pengampu
     */
    public function index(): void
    {
        $user = Session::get('user');
        $dosenId = (int)($user['dosen_id'] ?? 0);

        // Ambil jadwal aktif yang diampu dosen ini pada tahun akademik aktif.
        $allClasses = $this->jadwalModel->query(
            "SELECT jk.id
             FROM jadwal_kuliah jk
             JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
             WHERE jk.dosen_id = :dosen_id AND jk.is_active = 1",
            ['dosen_id' => $dosenId]
        );

        $hariIni = match (date('w')) {
            '1' => 'Senin',
            '2' => 'Selasa',
            '3' => 'Rabu',
            '4' => 'Kamis',
            '5' => 'Jumat',
            '6' => 'Sabtu',
            default => 'Minggu'
        };

        $sqlToday = "
            SELECT jk.*, mk.kode_mk, mk.nama_mk, mk.sks, k.nama_kelas, ta.semester
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.mata_kuliah_id = mk.id
            JOIN kelas k ON jk.kelas_id = k.id
            JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id AND ta.is_active = 1
            WHERE jk.dosen_id = :dosen_id AND jk.hari = :hari AND jk.is_active = 1
            ORDER BY jk.jam_mulai ASC
        ";
        $todayClasses = $this->jadwalModel->query($sqlToday, [
            'dosen_id' => $dosenId,
            'hari'     => $hariIni
        ]);

        // Hitung pengajuan izin yang pending
        $pendingIzin = $this->izinModel->count("status_persetujuan = 'pending'");

        $data = [
            'title'         => 'Dashboard Dosen - STMIK IKMI Cirebon',
            'user'          => $user,
            'today_classes' => $todayClasses,
            'hari_ini'      => $hariIni,
            'stats' => [
                'total_kelas'       => count($allClasses),
                'jadwal_hari_ini'   => count($todayClasses),
                'izin_pending'      => $pendingIzin
            ]
        ];

        $this->render('dosen/dashboard', $data);
    }
}
