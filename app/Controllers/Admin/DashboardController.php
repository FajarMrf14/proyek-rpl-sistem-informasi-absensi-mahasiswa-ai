<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\MataKuliah;
use App\Models\Jadwal;
use App\Models\Absensi;
use App\Models\SettingKampus;

class DashboardController extends Controller
{
    private Mahasiswa $mhsModel;
    private Dosen $dosenModel;
    private MataKuliah $mkModel;
    private Jadwal $jadwalModel;
    private Absensi $absensiModel;
    private SettingKampus $settingModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->mhsModel = new Mahasiswa();
        $this->dosenModel = new Dosen();
        $this->mkModel = new MataKuliah();
        $this->jadwalModel = new Jadwal();
        $this->absensiModel = new Absensi();
        $this->settingModel = new SettingKampus();
    }

    /**
     * Dashboard Admin / Bagian Akademik
     */
    public function index(): void
    {
        $user = Session::get('user');
        $campus = $this->settingModel->getActiveSetting();

        // Hitung statistik master data
        $totalMahasiswa = $this->mhsModel->count();
        $totalDosen     = $this->dosenModel->count();
        $totalMk        = $this->mkModel->count();
        $totalJadwal    = $this->jadwalModel->count('is_active = 1');
        $absensiTotal   = $this->absensiModel->count();

        $data = [
            'title'  => 'Dashboard Admin Akademik - STMIK IKMI Cirebon',
            'user'   => $user,
            'campus' => $campus,
            'stats'  => [
                'total_mahasiswa' => $totalMahasiswa,
                'total_dosen'     => $totalDosen,
                'total_mk'        => $totalMk,
                'total_jadwal'    => $totalJadwal,
                'total_absensi'   => $absensiTotal
            ]
        ];

        $this->render('admin/dashboard', $data);
    }
}
