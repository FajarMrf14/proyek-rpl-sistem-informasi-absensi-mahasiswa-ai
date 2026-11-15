<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Config\App as AppConfig;

class HomeController extends Controller
{
    /**
     * Halaman Landing Page / Portal Utama
     */
    public function index(): void
    {
        $config = AppConfig::config();

        $data = [
            'title'       => 'Portal Presensi Mahasiswa Berbasis AI',
            'campus_name' => $config['campus']['name'],
            'coords'      => $config['campus']['latitude'] . ', ' . $config['campus']['longitude'],
            'radius'      => $config['campus']['radius_meters'] . ' Meter',
            'features'    => [
                [
                    'icon'  => 'bi-geo-alt-fill',
                    'title' => 'Geofencing Kampus',
                    'desc'  => 'Validasi radius presensi berbasis GPS presisi menggunakan Haversine Formula.'
                ],
                [
                    'icon'  => 'bi-person-bounding-box',
                    'title' => 'AI Face Verification',
                    'desc'  => 'Pencocokan biometrik wajah mahasiswa secara real-time untuk mencegah kecurangan.'
                ],
                [
                    'icon'  => 'bi-camera-fill',
                    'title' => 'Foto Kegiatan Kelas',
                    'desc'  => 'Bukti dokumentasi kehadiran visual interaktif pada setiap sesi perkuliahan.'
                ],
                [
                    'icon'  => 'bi-shield-check',
                    'title' => 'Multi-Role Access',
                    'desc'  => 'Portal terpadu untuk Mahasiswa, Dosen Pengampu, dan Admin Akademik.'
                ]
            ]
        ];

        $this->render('home/index', $data);
    }

    /**
     * API Health Check Endpoint
     */
    public function health(): void
    {
        $this->json([
            'status'    => 'ok',
            'app'       => AppConfig::config()['name'],
            'timestamp' => date('Y-m-d H:i:s'),
            'php_ver'   => PHP_VERSION,
            'timezone'  => date_default_timezone_get()
        ]);
    }
}
