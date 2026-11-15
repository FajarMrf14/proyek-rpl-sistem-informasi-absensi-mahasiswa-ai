<?php

namespace App\Controllers\Mahasiswa;

use App\Core\Controller;
use App\Core\Session;
use App\Config\App as AppConfig;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\IzinSakit;
use App\Models\User;

class DashboardController extends Controller
{
    private Absensi $absensiModel;
    private Jadwal $jadwalModel;
    private IzinSakit $izinModel;
    private User $userModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->absensiModel = new Absensi();
        $this->jadwalModel = new Jadwal();
        $this->izinModel = new IzinSakit();
        $this->userModel = new User();
    }

    /**
     * Halaman Utama Dashboard Mahasiswa
     */
    public function index(): void
    {
        $sessionUser = Session::get('user');
        $userId = (int)($sessionUser['id'] ?? 0);

        // Ambil profil data mahasiswa terbaru dari database
        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        // Ambil data statistik akumulatif kehadiran mahasiswa dari database
        $stats = $this->absensiModel->getStudentOverallStats($mahasiswaId);

        // Batas minimal kehadiran mengikuti konfigurasi akademik yang tersimpan.
        $minAttendance = (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
        $minAttendanceText = number_format($minAttendance, 1);

        // Tentukan kategori & pesan motivasi kehadiran
        $pct = $stats['persentase_kehadiran'];
        if ($pct >= 85) {
            $motivation = [
                'status'  => 'Sangat Baik',
                'badge'   => 'bg-success',
                'text'    => 'Kehadiran Anda sangat memuaskan! Pertahankan tingkat kehadiran ini untuk hasil akademik maksimal.',
                'icon'    => 'bi-emoji-smile-fill',
                'color'   => '#10b981',
                'is_safe' => true
            ];
        } elseif ($pct >= $minAttendance) {
            $motivation = [
                'status'  => 'Cukup Baik (Waspada)',
                'badge'   => 'bg-warning text-dark',
                'text'    => "Tingkat kehadiran Anda mendekati batas minimum {$minAttendanceText}%. Jangan lewatkan pertemuan perkuliahan berikutnya!",
                'icon'    => 'bi-exclamation-triangle-fill',
                'color'   => '#f59e0b',
                'is_safe' => true
            ];
        } else {
            $motivation = [
                'status'  => "Perhatian Kritis (Di Bawah {$minAttendanceText}%)",
                'badge'   => 'bg-danger',
                'text'    => "PERINGATAN: Kehadiran Anda di bawah batas minimal {$minAttendanceText}%. Anda berisiko tidak memenuhi syarat mengikuti UAS.",
                'icon'    => 'bi-exclamation-octagon-fill',
                'color'   => '#ef4444',
                'is_safe' => false
            ];
        }

        // Ambil jadwal perkuliahan hari ini khusus mahasiswa ini
        $hariIni = $this->request->get('hari') ?? get_current_day_id();
        $todaySchedules = $this->jadwalModel->getStudentSchedules($mahasiswaId, $hariIni);

        // Ambil riwayat presensi terbaru (5 record terakhir)
        $recentHistory = array_slice($this->absensiModel->getStudentHistory($mahasiswaId), 0, 5);

        $data = [
            'title'           => 'Dashboard Mahasiswa - STMIK IKMI Cirebon',
            'user'            => $user,
            'stats'           => $stats,
            'motivation'      => $motivation,
            'min_attendance'  => $minAttendance,
            'today_schedules' => $todaySchedules,
            'recent_history'  => $recentHistory,
            'hari_ini'        => $hariIni
        ];

        $this->render('mahasiswa/dashboard', $data);
    }

    /**
     * Halaman Pemilihan Mata Kuliah untuk Presensi
     */
    public function absensi(): void
    {
        $sessionUser = Session::get('user');
        $userId = (int)($sessionUser['id'] ?? 0);
        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        // Hari saat ini (mendukung parameter ?hari= jika testing / simulasi)
        $hariIni = $this->request->get('hari') ?? get_current_day_id();
        $realDay = get_current_day_id();

        // Ambil jadwal mata kuliah mahasiswa khusus hari yang aktif
        $todaySchedules = $this->jadwalModel->getStudentSchedules($mahasiswaId, $hariIni);

        // Ambil seluruh jadwal mata kuliah mahasiswa selama satu semester
        $allSchedules = $this->jadwalModel->getStudentSchedules($mahasiswaId);

        // Kelompokkan jadwal berdasarkan hari
        $schedulesByDay = [];
        foreach (get_indonesian_days() as $day) {
            $schedulesByDay[$day] = array_filter($allSchedules, fn($s) => $s['hari'] === $day);
        }

        $data = [
            'title'            => 'Pilih Mata Kuliah Presensi - STMIK IKMI Cirebon',
            'user'             => $user,
            'today_schedules'  => $todaySchedules,
            'all_schedules'    => $allSchedules,
            'schedules_by_day' => $schedulesByDay,
            'hari_ini'         => $hariIni,
            'real_day'         => $realDay
        ];

        $this->render('mahasiswa/absensi', $data);
    }

    /**
     * Proses Pemilihan & Pengecekan Jadwal Mata Kuliah Mahasiswa
     */
    public function prosesAbsensi(mixed $id = null): void
    {
        $sessionUser = Session::get('user');
        $userId = (int)($sessionUser['id'] ?? 0);
        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $jadwalId = (int)($id ?? $this->request->get('id') ?? $this->request->post('jadwal_id') ?? 0);

        if ($jadwalId <= 0) {
            Session::setFlash('error', 'Silakan pilih mata kuliah terlebih dahulu.');
            $this->response->redirect(base_url('mahasiswa/absensi'));
            return;
        }

        // Ambil detail jadwal dan verifikasi bahwa mahasiswa benar-benar mengambil mata kuliah ini
        $jadwal = $this->jadwalModel->getStudentScheduleDetail($jadwalId, $mahasiswaId);

        if (!$jadwal) {
            Session::setFlash('error', 'Akses ditolak! Anda tidak terdaftar pada mata kuliah ini atau jadwal tidak aktif.');
            $this->response->redirect(base_url('mahasiswa/absensi'));
            return;
        }

        // Validasi Aturan Wajib: Mata kuliah harus memiliki jadwal pada HARI INI
        $currentDay = $this->request->get('hari') ?? get_current_day_id();

        if ($jadwal['hari'] !== $currentDay) {
            Session::setFlash(
                'error', 
                "Presensi Ditolak! Mata kuliah '{$jadwal['nama_mk']}' terjadwal pada hari {$jadwal['hari']}, bukan hari ini ({$currentDay}). Anda tidak dapat melakukan presensi di luar jadwal."
            );
            $this->response->redirect(base_url('mahasiswa/absensi'));
            return;
        }

        // Periksa apakah mahasiswa sudah melakukan presensi hari ini pada sesi ini
        $alreadyAttended = !empty($jadwal['absensi_id']);

        $data = [
            'title'            => 'Pengecekan Presensi - ' . $jadwal['nama_mk'],
            'user'             => $user,
            'jadwal'           => $jadwal,
            'hari_ini'         => $currentDay,
            'already_attended' => $alreadyAttended
        ];

        $this->render('mahasiswa/proses_absensi', $data);
    }

    /**
     * Halaman Riwayat Presensi Lengkap
     */
    public function riwayat(): void
    {
        $user = Session::get('user');
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $history = $this->absensiModel->getStudentHistory($mahasiswaId);
        $stats = $this->absensiModel->getStudentOverallStats($mahasiswaId);

        $data = [
            'title'   => 'Riwayat Kehadiran - STMIK IKMI Cirebon',
            'user'    => $user,
            'history' => $history,
            'stats'   => $stats
        ];

        $this->render('mahasiswa/riwayat', $data);
    }

    /**
     * Halaman Riwayat & Pengajuan Izin/Sakit
     */
    public function izin(): void
    {
        $user = Session::get('user');
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $leaves = $this->izinModel->getStudentLeaves($mahasiswaId);
        $schedules = $this->jadwalModel->getStudentSchedules($mahasiswaId);

        $data = [
            'title'     => 'Pengajuan Izin & Sakit - STMIK IKMI Cirebon',
            'user'      => $user,
            'leaves'    => $leaves,
            'schedules' => $schedules
        ];

        $this->render('mahasiswa/izin', $data);
    }

    /**
     * Halaman Profil Mahasiswa & Biometrik Wajah
     */
    public function profil(): void
    {
        $sessionUser = Session::get('user');
        $userId = (int)($sessionUser['id'] ?? 0);

        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;

        $data = [
            'title' => 'Profil Mahasiswa - STMIK IKMI Cirebon',
            'user'  => $user
        ];

        $this->render('mahasiswa/profil', $data);
    }
}
