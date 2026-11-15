<?php

namespace App\Config;

use App\Core\Env;
use App\Models\AppSetting;

class App
{
    /**
     * Konfigurasi Umum Aplikasi
     */
    public static function config(): array
    {
        $dbName = AppSetting::get('app_name');
        $dbTimezone = AppSetting::get('app_timezone');
        $dbSessionLifetime = AppSetting::get('session_lifetime');
        $dbDebug = AppSetting::get('app_debug');
        $dbApiKey = AppSetting::get('gemini_api_key');
        $dbModel = AppSetting::get('gemini_model');
        $dbBaseUrl = AppSetting::get('gemini_base_url');
        $dbCampusName = AppSetting::get('campus_name');

        return [
            'name' => !empty($dbName) ? $dbName : Env::get('APP_NAME', 'Absensi Mahasiswa AI - STMIK IKMI Cirebon'),
            'description' => AppSetting::get('app_description', 'Sistem Absensi Mahasiswa Berbasis Web dengan AI - STMIK IKMI Cirebon'),
            'logo' => AppSetting::get('app_logo', ''),
            'contact_email' => AppSetting::get('contact_email', 'info@ikmi.ac.id'),
            'env' => Env::get('APP_ENV', 'development'),
            'debug' => ($dbDebug !== null) ? (bool)$dbDebug : (bool)Env::get('APP_DEBUG', true),
            'url' => rtrim(Env::get('APP_URL', 'http://localhost/absensi_mahasiswa/public'), '/'),
            'timezone' => !empty($dbTimezone) ? $dbTimezone : Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
            'session_lifetime' => !empty($dbSessionLifetime) ? (int)$dbSessionLifetime : (int)Env::get('SESSION_LIFETIME', 7200),
            'csrf_protection' => Env::get('CSRF_PROTECTION', true),
            'app_key' => Env::get('APP_KEY', 'default_secret_key_change_in_env'),
            'maintenance_mode' => (bool)AppSetting::get('maintenance_mode', false),
            'campus' => [
                'name' => !empty($dbCampusName) ? $dbCampusName : Env::get('CAMPUS_NAME', 'STMIK IKMI Cirebon'),
                'latitude' => (float) Env::get('CAMPUS_LATITUDE', -6.7441584),
                'longitude' => (float) Env::get('CAMPUS_LONGITUDE', 108.5441566),
                'radius_meters' => (int) Env::get('CAMPUS_RADIUS_METERS', 100),
            ],
            'academic' => [
                'default_late_tolerance' => (int)AppSetting::get('default_late_tolerance', 15),
                'min_attendance_percent' => (float)AppSetting::get('min_attendance_percent', 75.0),
            ],
            'ai' => [
                'similarity_threshold' => (float) Env::get('FACE_SIMILARITY_THRESHOLD', 0.55),
                'model_path' => Env::get('FACE_MODEL_PATH', '/models'),
            ],
            'max_upload_size' => (int) Env::get('MAX_UPLOAD_SIZE', 5120), // 5MB
            'chatbot' => [
                // Provider dikunci ke Google Gemini. Pilihan provider tidak lagi
                // dibuka ke admin agar integrasi tetap konsisten dengan service.
                'provider' => 'google_gemini',
                'api_key'  => !empty($dbApiKey) ? $dbApiKey : Env::get('GEMINI_API_KEY', ''),
                'model'    => !empty($dbModel) ? $dbModel : Env::get('GEMINI_MODEL', 'gemini-3.6-flash'),
                'base_url' => rtrim(!empty($dbBaseUrl) ? $dbBaseUrl : Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
            ],
        ];
    }
}
