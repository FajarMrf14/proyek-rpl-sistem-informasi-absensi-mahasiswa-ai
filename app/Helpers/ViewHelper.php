<?php

use App\Config\App;
use App\Core\Session;

if (!function_exists('base_url')) {
    /**
     * Menghasilkan URL absolut aplikasi
     */
    function base_url(string $path = ''): string
    {
        $config = App::config();
        $baseUrl = rtrim($config['url'], '/');
        $path = ltrim($path, '/');

        return empty($path) ? $baseUrl : "{$baseUrl}/{$path}";
    }
}

if (!function_exists('asset')) {
    /**
     * Menghasilkan URL aset publik (css, js, images)
     */
    function asset(string $path = ''): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Mengambil CSRF token saat ini
     */
    function csrf_token(): string
    {
        return Session::generateCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Menghasilkan tag input hidden CSRF token
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('flash')) {
    /**
     * Menampilkan atau mengambil flash message
     */
    function flash(string $type): ?string
    {
        return Session::getFlash($type);
    }
}

if (!function_exists('has_flash')) {
    /**
     * Cek ketersediaan flash message
     */
    function has_flash(string $type): bool
    {
        return Session::hasFlash($type);
    }
}

if (!function_exists('get_indonesian_days')) {
    /**
     * Daftar nama hari dalam Bahasa Indonesia
     */
    function get_indonesian_days(): array
    {
        return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    }
}

if (!function_exists('get_current_day_id')) {
    /**
     * Mengambil nama hari saat ini dalam Bahasa Indonesia
     */
    function get_current_day_id(): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return $days[(int)date('w')];
    }
}

if (!function_exists('format_date_id')) {
    /**
     * Format tanggal ke format Bahasa Indonesia
     */
    function format_date_id(string $date, bool $withDay = true, bool $withTime = false): string
    {
        if (empty($date)) return '-';

        $timestamp = strtotime($date);
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $dayName = $days[date('w', $timestamp)];
        $day = date('j', $timestamp);
        $month = $months[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);

        $result = "{$day} {$month} {$year}";

        if ($withDay) {
            $result = "{$dayName}, {$result}";
        }

        if ($withTime) {
            $time = date('H:i', $timestamp);
            $result .= " pukul {$time} WIB";
        }

        return $result;
    }
}

if (!function_exists('setting')) {
    /**
     * Mengambil nilai pengaturan sistem / aplikasi
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\AppSetting::get($key, $default);
    }
}

if (!function_exists('app_name')) {
    /**
     * Mengambil nama resmi aplikasi yang aktif
     */
    function app_name(): string
    {
        return (string) (\App\Config\App::config()['name'] ?? 'Absensi Mahasiswa AI - STMIK IKMI Cirebon');
    }
}

if (!function_exists('campus_name')) {
    /**
     * Mengambil nama perguruan tinggi / institusi
     */
    function campus_name(): string
    {
        return (string) (\App\Config\App::config()['campus']['name'] ?? 'STMIK IKMI Cirebon');
    }
}

if (!function_exists('app_logo')) {
    /**
     * Mengambil URL logo aplikasi jika ada
     */
    function app_logo(): ?string
    {
        $logo = \App\Config\App::config()['logo'] ?? '';
        if (!empty($logo)) {
            return base_url(ltrim($logo, '/'));
        }
        return null;
    }
}
