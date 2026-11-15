<?php

namespace App\Controllers\Mahasiswa;

use App\Core\Controller;
use App\Core\Session;
use App\Config\App as AppConfig;
use App\Helpers\GeoHelper;
use App\Services\FaceService;
use App\Services\WhatsAppNotificationService;
use App\Models\Jadwal;
use App\Models\Absensi;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WajahMahasiswa;
use App\Models\Pertemuan;

/**
 * AbsensiController — Menangani seluruh 4 alur lengkap absensi mahasiswa:
 *   Tahap 1: GET  /mahasiswa/absensi/proses/{id}     → Konfirmasi jadwal & validasi hari
 *   Tahap 2: GET/POST /mahasiswa/absensi/geolocation/{id} → Validasi Geolocation GPS & Radius Kampus
 *   Tahap 3: GET/POST /mahasiswa/absensi/wajah/{id}       → Validasi AI Face Verification Biometrik
 *   Tahap 4: GET/POST /mahasiswa/absensi/kegiatan/{id}    → Foto Kegiatan Kelas & Commit Absensi ke DB
 *   Final:   GET  /mahasiswa/absensi/sukses/{id}      → Ringkasan / Bukti Absensi Berhasil
 */
class AbsensiController extends Controller
{
    private Jadwal $jadwalModel;
    private Absensi $absensiModel;
    private User $userModel;
    private WajahMahasiswa $wajahModel;
    private Pertemuan $pertemuanModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->jadwalModel    = new Jadwal();
        $this->absensiModel   = new Absensi();
        $this->userModel      = new User();
        $this->wajahModel     = new WajahMahasiswa();
        $this->pertemuanModel = new Pertemuan();
    }

    // =========================================================================
    // Tahap 2 GET: Halaman Pengecekan Geolocation
    // =========================================================================
    public function halamanGeolocation(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId);
        if (!$jadwal) {
            return;
        }

        $campus = GeoHelper::getCampusConfig();

        if (!empty($jadwal['absensi_id'])) {
            Session::setFlash('info', "Anda sudah melakukan absensi pada mata kuliah ini hari ini.");
            $this->response->redirect(base_url('mahasiswa/riwayat'));
            return;
        }

        $data = [
            'title'  => 'Cek Lokasi GPS - ' . $jadwal['nama_mk'],
            'user'   => $user,
            'jadwal' => $jadwal,
            'campus' => $campus,
        ];

        $this->render('mahasiswa/geolocation', $data);
    }

    // =========================================================================
    // Tahap 2 POST: Validasi Geolocation Server-Side
    // =========================================================================
    public function validasiGeolocation(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);
        $isAjax      = $this->request->isAjax();

        $rawLat   = $this->request->raw('latitude');
        $rawLon   = $this->request->raw('longitude');
        $accuracy = (float)($this->request->raw('accuracy') ?? 0);

        $lat = ($rawLat !== null && $rawLat !== '') ? (float)$rawLat : null;
        $lon = ($rawLon !== null && $rawLon !== '') ? (float)$rawLon : null;

        if (!GeoHelper::isValidCoordinate($lat, $lon)) {
            $msg = 'Koordinat GPS tidak valid atau izin lokasi ditolak. Pastikan Anda mengizinkan akses lokasi di browser.';
            $this->geoReject($isAjax, $jadwalId, $msg);
            return;
        }

        if ($accuracy > 200) {
            $msg = "Akurasi GPS Anda terlalu rendah ({$accuracy}m). Pindah ke area terbuka atau aktifkan Wi-Fi.";
            $this->geoReject($isAjax, $jadwalId, $msg);
            return;
        }

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId, returnNull: true);
        if (!$jadwal) {
            $msg = 'Akses ditolak! Jadwal tidak valid atau Anda tidak terdaftar pada mata kuliah ini.';
            $this->geoReject($isAjax, $jadwalId, $msg);
            return;
        }

        $currentDay = $this->request->get('hari') ?? get_current_day_id();
        if ($jadwal['hari'] !== $currentDay) {
            $msg = "Presensi Ditolak! Mata kuliah '{$jadwal['nama_mk']}' terjadwal pada hari {$jadwal['hari']}, bukan hari ini ({$currentDay}).";
            $this->geoReject($isAjax, $jadwalId, $msg);
            return;
        }

        if (!empty($jadwal['absensi_id'])) {
            $msg = 'Anda sudah melakukan absensi pada mata kuliah ini hari ini.';
            $this->geoReject($isAjax, $jadwalId, $msg, alreadyAttended: true);
            return;
        }

        $campus = GeoHelper::getCampusConfig();
        $geo    = GeoHelper::checkGeofence(
            $lat, $lon,
            $campus['latitude'],
            $campus['longitude'],
            $campus['radius_meters']
        );

        if (!$geo['is_valid']) {
            $msg = "Lokasi Anda di luar kampus! Jarak Anda: {$geo['distance']} m dari titik pusat kampus (radius maksimal: {$campus['radius_meters']} m).";
            AuditLog::logActivity(
                $user['id'], 'GEOLOCATION_REJECTED', 'absensi', null,
                null, ['lat' => $lat, 'lon' => $lon, 'distance' => $geo['distance'], 'jadwal_id' => $jadwalId]
            );
            $this->geoReject($isAjax, $jadwalId, $msg, distance: $geo['distance'], radius: $campus['radius_meters']);
            return;
        }

        Session::set("geo_verified_{$jadwalId}", [
            'latitude'      => $lat,
            'longitude'     => $lon,
            'accuracy'      => $accuracy,
            'distance'      => $geo['distance'],
            'campus_lat'    => $campus['latitude'],
            'campus_lon'    => $campus['longitude'],
            'radius'        => $campus['radius_meters'],
            'verified_at'   => date('Y-m-d H:i:s'),
            'mahasiswa_id'  => $mahasiswaId,
            'jadwal_id'     => $jadwalId,
        ]);

        AuditLog::logActivity(
            $user['id'], 'GEOLOCATION_VERIFIED', 'absensi', null,
            null, ['lat' => $lat, 'lon' => $lon, 'distance' => $geo['distance'], 'jadwal_id' => $jadwalId]
        );

        $successMsg = "Lokasi terverifikasi! Anda berada {$geo['distance']} m dari kampus (dalam radius {$campus['radius_meters']} m).";

        if ($isAjax) {
            $this->response->json([
                'success'  => true,
                'message'  => $successMsg,
                'distance' => $geo['distance'],
                'radius'   => $campus['radius_meters'],
                'redirect' => base_url("mahasiswa/absensi/wajah/{$jadwalId}")
            ]);
            return;
        }

        Session::setFlash('success', $successMsg);
        $this->response->redirect(base_url("mahasiswa/absensi/wajah/{$jadwalId}"));
    }

    // =========================================================================
    // Tahap 3 GET: Halaman Kamera Verifikasi Wajah AI
    // =========================================================================
    public function halamanWajah(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId);
        if (!$jadwal) {
            return;
        }

        // --- Strict Guarding Tahap 2: Geolocation harus sudah lolos ---
        $geoSession = Session::get("geo_verified_{$jadwalId}");
        if (empty($geoSession)) {
            Session::setFlash('error', 'Wajib melakukan verifikasi lokasi GPS kampus terlebih dahulu sebelum verifikasi wajah.');
            $this->response->redirect(base_url("mahasiswa/absensi/geolocation/{$jadwalId}"));
            return;
        }

        $wajahData = $this->wajahModel->getDescriptorByMahasiswaId($mahasiswaId);
        $isFaceRegistered = !empty($wajahData) && ($wajahData['status'] === 'verified');
        $threshold = FaceService::getThresholdConfig();

        $data = [
            'title'               => 'Verifikasi Wajah AI - ' . $jadwal['nama_mk'],
            'user'                => $user,
            'jadwal'              => $jadwal,
            'geoSession'          => $geoSession,
            'wajahData'           => $wajahData,
            'isFaceRegistered'    => $isFaceRegistered,
            'similarityThreshold' => $threshold,
        ];

        $this->render('mahasiswa/wajah', $data);
    }

    // =========================================================================
    // Tahap 3 POST: Validasi Biometrik Wajah Server-Side
    // =========================================================================
    public function validasiWajah(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);
        $isAjax      = $this->request->isAjax();

        // --- Strict Guarding Tahap 2 ---
        $geoSession = Session::get("geo_verified_{$jadwalId}");
        if (empty($geoSession)) {
            $msg = 'Sesi lokasi GPS tidak ditemukan atau kedaluwarsa. Silakan lakukan pengecekan lokasi kembali.';
            $this->faceReject($isAjax, $jadwalId, $msg);
            return;
        }

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId, returnNull: true);
        if (!$jadwal) {
            $msg = 'Akses ditolak! Jadwal tidak valid atau Anda tidak terdaftar pada mata kuliah ini.';
            $this->faceReject($isAjax, $jadwalId, $msg);
            return;
        }

        $imageSnapshot  = $this->request->raw('image');
        $liveDescriptor = $this->request->raw('descriptor');

        if (empty($imageSnapshot)) {
            $msg = 'Snapshot foto wajah tidak ditemukan. Pastikan Anda mengizinkan akses kamera browser.';
            $this->faceReject($isAjax, $jadwalId, $msg);
            return;
        }

        $wajahData = $this->wajahModel->getDescriptorByMahasiswaId($mahasiswaId);

        if (empty($wajahData) || empty($wajahData['descriptor'])) {
            $msg = 'Data biometrik wajah Anda belum terdaftar/terverifikasi di sistem STMIK IKMI. Silakan hubungi bagian Akademik.';
            AuditLog::logActivity(
                $user['id'], 'FACE_VERIFICATION_UNREGISTERED', 'absensi', null,
                null, ['jadwal_id' => $jadwalId, 'mahasiswa_id' => $mahasiswaId]
            );
            $this->faceReject($isAjax, $jadwalId, $msg);
            return;
        }

        $result = FaceService::verifyFaceImage($imageSnapshot, $wajahData['descriptor'], $liveDescriptor);

        if (!$result['is_match']) {
            AuditLog::logActivity(
                $user['id'], 'FACE_VERIFICATION_FAILED', 'absensi', null,
                null, [
                    'jadwal_id'    => $jadwalId,
                    'similarity'   => $result['similarity'] ?? 0.0,
                    'distance'     => $result['distance'] ?? 999.0,
                    'threshold'    => $result['threshold'] ?? 0.55
                ]
            );
            $this->faceReject($isAjax, $jadwalId, $result['message'], $result['similarity'] ?? 0.0);
            return;
        }

        Session::set("face_verified_{$jadwalId}", [
            'mahasiswa_id'    => $mahasiswaId,
            'jadwal_id'       => $jadwalId,
            'similarity'      => $result['similarity'],
            'distance'        => $result['distance'],
            'verified_at'     => date('Y-m-d H:i:s'),
            'face_verified'   => 1,
        ]);

        AuditLog::logActivity(
            $user['id'], 'FACE_VERIFICATION_SUCCESS', 'absensi', null,
            null, [
                'jadwal_id'  => $jadwalId,
                'similarity' => $result['similarity'],
                'distance'   => $result['distance']
            ]
        );

        $successMsg = "Verifikasi Wajah AI Berhasil! Kemiripan biometrik terverifikasi {$result['similarity']}%.";

        if ($isAjax) {
            $this->response->json([
                'success'    => true,
                'message'    => $successMsg,
                'similarity' => $result['similarity'],
                'redirect'   => base_url("mahasiswa/absensi/kegiatan/{$jadwalId}")
            ]);
            return;
        }

        Session::setFlash('success', $successMsg);
        $this->response->redirect(base_url("mahasiswa/absensi/kegiatan/{$jadwalId}"));
    }

    // =========================================================================
    // Tahap 4 GET: Halaman Kamera Foto Kegiatan Kelas
    // =========================================================================
    public function halamanKegiatan(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId);
        if (!$jadwal) {
            return;
        }

        // --- Strict Guarding Tahap 2 & 3 ---
        $geoSession  = Session::get("geo_verified_{$jadwalId}");
        $faceSession = Session::get("face_verified_{$jadwalId}");

        if (empty($geoSession)) {
            Session::setFlash('error', 'Wajib menyelesaikan verifikasi lokasi GPS kampus terlebih dahulu.');
            $this->response->redirect(base_url("mahasiswa/absensi/geolocation/{$jadwalId}"));
            return;
        }

        if (empty($faceSession)) {
            Session::setFlash('error', 'Wajib menyelesaikan verifikasi wajah AI biometrik terlebih dahulu.');
            $this->response->redirect(base_url("mahasiswa/absensi/wajah/{$jadwalId}"));
            return;
        }

        $data = [
            'title'       => 'Foto Kegiatan Kelas - ' . $jadwal['nama_mk'],
            'user'        => $user,
            'jadwal'      => $jadwal,
            'geoSession'  => $geoSession,
            'faceSession' => $faceSession,
        ];

        $this->render('mahasiswa/kegiatan', $data);
    }

    // =========================================================================
    // Tahap 4 POST: Commit & Simpan Foto Kegiatan + Data Absensi ke DB
    // =========================================================================
    public function validasiKegiatan(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);
        $isAjax      = $this->request->isAjax();

        // --- Strict Guarding Tahap 1, 2, & 3 ---
        $geoSession  = Session::get("geo_verified_{$jadwalId}");
        $faceSession = Session::get("face_verified_{$jadwalId}");

        if (empty($geoSession)) {
            $this->kegiatanReject($isAjax, $jadwalId, 'Verifikasi lokasi GPS tidak ditemukan. Absensi ditolak.');
            return;
        }

        if (empty($faceSession)) {
            $this->kegiatanReject($isAjax, $jadwalId, 'Verifikasi wajah AI belum dilakukan. Absensi ditolak.');
            return;
        }

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId, returnNull: true);
        if (!$jadwal) {
            $this->kegiatanReject($isAjax, $jadwalId, 'Akses ditolak! Jadwal tidak valid.');
            return;
        }

        $currentDay = $this->request->get('hari') ?? get_current_day_id();
        if ($jadwal['hari'] !== $currentDay) {
            $this->kegiatanReject($isAjax, $jadwalId, "Absensi Ditolak! Jadwal mata kuliah bukan hari ini.");
            return;
        }

        // Ambil foto snapshot kegiatan kelas
        $rawImageBase64 = $this->request->raw('image');
        if (empty($rawImageBase64)) {
            $this->kegiatanReject($isAjax, $jadwalId, 'Foto kegiatan kelas wajib diambil via kamera.');
            return;
        }

        // Decode & simpan foto secara aman
        $fotoPath = $this->saveKegiatanPhoto($rawImageBase64, $mahasiswaId, $jadwalId);
        if (!$fotoPath) {
            $this->kegiatanReject($isAjax, $jadwalId, 'Gagal mengunggah foto kegiatan kelas. Format tidak valid.');
            return;
        }

        // --- Cari / Buat Sesi Pertemuan Kuliah Hari Ini ---
        $pertemuan = $this->pertemuanModel->getActiveSession($jadwalId);
        $pertemuanId = $pertemuan['id'] ?? null;

        if (!$pertemuanId) {
            // Ambil nomor pertemuan terakhir / default pertemuan ke-1
            $allPertemuan = $this->pertemuanModel->getByJadwal($jadwalId);
            $nextKe = count($allPertemuan) + 1;

            // jam_tutup dipakai worker untuk menutup sesi secara final. Tanpa
            // nilai ini, pertemuan lama tidak akan diubah oleh worker.
            $jamSelesai = (string) ($jadwal['jam_selesai'] ?? '');
            $jamTutup = $jamSelesai !== ''
                ? date('Y-m-d') . ' ' . $jamSelesai
                : date('Y-m-d H:i:s', strtotime('+2 hours'));

            $pertemuanId = $this->pertemuanModel->insert([
                'jadwal_id'     => $jadwalId,
                'pertemuan_ke'  => $nextKe,
                'tanggal'       => date('Y-m-d'),
                'jam_buka'      => date('Y-m-d H:i:s'),
                'jam_tutup'     => $jamTutup,
                'status'        => 'dibuka',
            ]);
        }

        // --- Hitung Status Kehadiran (Hadir / Terlambat) ---
        $now = date('H:i:s');
        $jamMulai = $jadwal['jam_mulai'] ?? '08:00:00';
        // Gunakan toleransi khusus jadwal; fallback ke default akademik hanya
        // bila kolom tersebut benar-benar tidak tersedia (bukan saat bernilai 0).
        $toleransiMinutes = (isset($jadwal['toleransi_keterlambatan']) && $jadwal['toleransi_keterlambatan'] !== null)
            ? (int)$jadwal['toleransi_keterlambatan']
            : (int)(AppConfig::config()['academic']['default_late_tolerance'] ?? 15);

        $jamBatasToleransi = date('H:i:s', strtotime("{$jamMulai} +{$toleransiMinutes} minutes"));

        $statusKehadiran = ($now <= $jamBatasToleransi) ? 'hadir' : 'terlambat';

        // --- SIMPAN TRANSAKSI ABSENSI KE DATABASE ---
        $absensiData = [
            'pertemuan_id'    => (int)$pertemuanId,
            'mahasiswa_id'    => $mahasiswaId,
            'waktu_absensi'   => date('Y-m-d H:i:s'),
            'status'          => $statusKehadiran,
            'latitude'        => $geoSession['latitude'],
            'longitude'       => $geoSession['longitude'],
            'jarak_meter'     => $geoSession['distance'],
            'status_lokasi'   => 'dalam_radius',
            'face_verified'   => 1,
            'face_similarity' => round(($faceSession['similarity'] ?? 90.0) / 100.0, 4),
            'foto_kegiatan'   => $fotoPath,
            'device_info'     => substr($_SERVER['HTTP_USER_AGENT'] ?? 'WebBrowser', 0, 250),
            'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'keterangan'      => "Presensi Mandiri GPS + Face AI ({$statusKehadiran})"
        ];

        try {
            $absensiId = $this->absensiModel->insert($absensiData);
            $absensiData['id'] = $absensiId;
        } catch (\Throwable $e) {
            // Handing jika duplikasi absensi (UNIQUE KEY UK_absensi_pertemuan_mhs)
            $this->kegiatanReject($isAjax, $jadwalId, 'Anda sudah pernah mencatat absensi pada pertemuan perkuliahan hari ini.');
            return;
        }

        // Simpan data sukses ke session bukti
        Session::set("absensi_sukses_{$jadwalId}", array_merge($absensiData, [
            'nama_mk'     => $jadwal['nama_mk'],
            'kode_mk'     => $jadwal['kode_mk'],
            'nama_kelas'  => $jadwal['nama_kelas'],
            'nama_dosen'  => $jadwal['nama_dosen'],
            'ruangan'     => $jadwal['ruangan'],
            'jam_mulai'   => $jadwal['jam_mulai'],
            'jam_selesai' => $jadwal['jam_selesai'],
            'nama_mhs'    => $user['nama_lengkap'] ?? $user['username'],
            'nim_mhs'     => $user['nim'] ?? $user['username'],
        ]));

        // Hapus session transient untuk mencegah penggunaan ulang
        Session::remove("geo_verified_{$jadwalId}");
        Session::remove("face_verified_{$jadwalId}");

        AuditLog::logActivity(
            $user['id'], 'ATTENDANCE_SUCCESS', 'absensi', (int)$absensiId,
            null, $absensiData
        );

        // Notifikasi WhatsApp hanya di-enqueue SETELAH absensi tersimpan.
        // Kegagalan di sini tidak boleh membatalkan atau mengubah hasil absensi.
        try {
            (new WhatsAppNotificationService())->enqueueAttendanceConfirmation(
                (int)$absensiId,
                [
                    'mahasiswa_id'  => $mahasiswaId,
                    'jadwal_id'     => (int)$jadwalId,
                    'nama_mhs'      => $user['nama_lengkap'] ?? $user['username'] ?? '',
                    'nim'           => $user['nim'] ?? $user['username'] ?? '',
                    'nama_mk'       => $jadwal['nama_mk'] ?? '',
                    'kode_mk'       => $jadwal['kode_mk'] ?? '',
                    'nama_dosen'    => $jadwal['nama_dosen'] ?? '',
                    'nama_kelas'    => $jadwal['nama_kelas'] ?? '',
                    'hari'          => $jadwal['hari'] ?? '',
                    'jam_mulai'     => $jadwal['jam_mulai'] ?? '',
                    'jam_selesai'   => $jadwal['jam_selesai'] ?? '',
                    'ruangan'       => $jadwal['ruangan'] ?? '',
                    'status'        => $statusKehadiran,
                    'tanggal'       => substr((string)$absensiData['waktu_absensi'], 0, 10),
                    'waktu_absensi' => $absensiData['waktu_absensi'],
                ]
            );
        } catch (\Throwable $e) {
            error_log('[WhatsApp] Gagal enqueue konfirmasi absensi: ' . get_class($e));
        }

        $successMsg = "Absensi Berhasil Dicatat! Status: " . strtoupper($statusKehadiran);

        if ($isAjax) {
            $this->response->json([
                'success'  => true,
                'message'  => $successMsg,
                'redirect' => base_url("mahasiswa/absensi/sukses/{$jadwalId}")
            ]);
            return;
        }

        Session::setFlash('success', $successMsg);
        $this->response->redirect(base_url("mahasiswa/absensi/sukses/{$jadwalId}"));
    }

    // =========================================================================
    // Final GET: Halaman Sukses & Rekap Presensi
    // =========================================================================
    public function halamanSukses(mixed $id = null): void
    {
        $user        = $this->getAuthUser();
        $jadwalId    = $this->resolveId($id);
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        $jadwal = $this->validateJadwal($jadwalId, $mahasiswaId);
        $bukti  = Session::get("absensi_sukses_{$jadwalId}");

        if (empty($bukti)) {
            // Fallback ambil absensi terakhir dari DB
            $lastAtt = $this->absensiModel->getStudentHistory($mahasiswaId, 1);
            if (!empty($lastAtt)) {
                $bukti = $lastAtt[0];
            } else {
                Session::setFlash('info', 'Tidak ada data presensi aktif.');
                $this->response->redirect(base_url('mahasiswa/dashboard'));
                return;
            }
        }

        $data = [
            'title'  => 'Absensi Berhasil - ' . ($jadwal['nama_mk'] ?? 'Mata Kuliah'),
            'user'   => $user,
            'jadwal' => $jadwal,
            'bukti'  => $bukti,
        ];

        $this->render('mahasiswa/sukses', $data);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function getAuthUser(): array
    {
        $sessionUser = Session::get('user');
        $userId      = (int)($sessionUser['id'] ?? 0);
        return $this->userModel->getFullProfile($userId) ?? $sessionUser;
    }

    private function resolveId(mixed $id): int
    {
        return (int)($id ?? $this->request->get('id') ?? $this->request->post('jadwal_id') ?? 0);
    }

    private function validateJadwal(int $jadwalId, int $mahasiswaId, bool $returnNull = false): ?array
    {
        if ($jadwalId <= 0) {
            if (!$returnNull) {
                Session::setFlash('error', 'Jadwal tidak ditemukan. Silakan pilih mata kuliah kembali.');
                $this->response->redirect(base_url('mahasiswa/absensi'));
            }
            return null;
        }

        $jadwal = $this->jadwalModel->getStudentScheduleDetail($jadwalId, $mahasiswaId);
        if (!$jadwal) {
            if (!$returnNull) {
                Session::setFlash('error', 'Akses ditolak! Anda tidak terdaftar pada mata kuliah ini.');
                $this->response->redirect(base_url('mahasiswa/absensi'));
            }
            return null;
        }

        return $jadwal;
    }

    private function geoReject(
        bool   $isAjax,
        int    $jadwalId,
        string $message,
        bool   $alreadyAttended = false,
        float  $distance = 0,
        int    $radius = 0
    ): void {
        if ($isAjax) {
            $this->response->json([
                'success'          => false,
                'message'          => $message,
                'already_attended' => $alreadyAttended,
                'distance'         => $distance,
                'radius'           => $radius,
            ], 422);
            return;
        }

        Session::setFlash('error', $message);
        $this->response->redirect(base_url("mahasiswa/absensi/geolocation/{$jadwalId}"));
    }

    private function faceReject(
        bool   $isAjax,
        int    $jadwalId,
        string $message,
        float  $similarity = 0.0
    ): void {
        if ($isAjax) {
            $this->response->json([
                'success'    => false,
                'message'    => $message,
                'similarity' => $similarity,
            ], 422);
            return;
        }

        Session::setFlash('error', $message);
        $this->response->redirect(base_url("mahasiswa/absensi/wajah/{$jadwalId}"));
    }

    private function kegiatanReject(bool $isAjax, int $jadwalId, string $message): void
    {
        if ($isAjax) {
            $this->response->json([
                'success' => false,
                'message' => $message,
            ], 422);
            return;
        }

        Session::setFlash('error', $message);
        $this->response->redirect(base_url("mahasiswa/absensi/kegiatan/{$jadwalId}"));
    }

    /**
     * Menyimpan foto snapshot kegiatan kelas ke folder uploads/kegiatan
     */
    private function saveKegiatanPhoto(string $base64Data, int $mahasiswaId, int $jadwalId): ?string
    {
        if (str_contains($base64Data, ',')) {
            $base64Data = explode(',', $base64Data)[1];
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false || strlen($decoded) < 10) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/kegiatan';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = sprintf('kegiatan_mhs%d_jdw%d_%s.jpg', $mahasiswaId, $jadwalId, date('Ymd_His'));
        $filePath = $uploadDir . '/' . $filename;

        if (file_put_contents($filePath, $decoded) !== false) {
            return 'uploads/kegiatan/' . $filename;
        }

        return null;
    }
}
