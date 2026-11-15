<?php

namespace App\Helpers;

use App\Config\App;
use App\Models\SettingKampus;

class GeoHelper
{
    // Konstanta radius bumi dalam meter
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Menghitung jarak antara dua koordinat GPS menggunakan Formula Haversine
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo   = deg2rad($lat2);
        $lonTo   = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * self::EARTH_RADIUS_METERS, 2);
    }

    /**
     * Memeriksa apakah posisi pengguna berada di dalam radius toleransi kampus
     */
    public static function checkGeofence(float $userLat, float $userLon, float $campusLat, float $campusLon, int $radiusMeters): array
    {
        $distance = self::calculateDistance($userLat, $userLon, $campusLat, $campusLon);
        $isValid  = ($distance <= $radiusMeters);

        return [
            'is_valid' => $isValid,
            'distance' => $distance,
            'radius'   => $radiusMeters,
            'message'  => $isValid
                ? "Lokasi Anda terverifikasi berada dalam radius kampus ({$distance} m dari titik pusat)."
                : "Lokasi Anda di luar radius kampus! Jarak Anda: {$distance} m (Maksimal diperbolehkan: {$radiusMeters} m)."
        ];
    }

    /**
     * Memuat konfigurasi kampus: prioritas dari DB settings_kampus, fallback ke .env/App::config()
     */
    public static function getCampusConfig(): array
    {
        try {
            $setting = (new SettingKampus())->getActiveSetting();
            if ($setting) {
                return [
                    'name'          => $setting['nama_kampus'] ?? 'STMIK IKMI Cirebon',
                    'latitude'      => (float)$setting['latitude'],
                    'longitude'     => (float)$setting['longitude'],
                    'radius_meters' => (int)$setting['radius_meter']
                ];
            }
        } catch (\Throwable) {
            // Fallback ke config jika DB gagal
        }

        $config = App::config();
        return [
            'name'          => $config['campus']['name'],
            'latitude'      => $config['campus']['latitude'],
            'longitude'     => $config['campus']['longitude'],
            'radius_meters' => $config['campus']['radius_meters']
        ];
    }

    /**
     * Validasi format koordinat latitude dan longitude
     */
    public static function isValidCoordinate(?float $lat, ?float $lon): bool
    {
        if ($lat === null || $lon === null) {
            return false;
        }
        return ($lat >= -90 && $lat <= 90) && ($lon >= -180 && $lon <= 180);
    }
}
