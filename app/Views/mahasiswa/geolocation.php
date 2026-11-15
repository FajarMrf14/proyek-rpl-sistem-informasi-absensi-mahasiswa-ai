<?php

use App\Core\Session;

/**
 * View: mahasiswa/geolocation.php
 * Halaman Pengecekan Lokasi GPS — Tahap 2 Alur Presensi
 *
 * Variabel yang tersedia dari controller:
 *   $jadwal  — array detail jadwal terpilih
 *   $campus  — array { name, latitude, longitude, radius_meters }
 *   $user    — array profil mahasiswa
 */

$isVerified  = !empty($_GET['verified']);  // parameter dari redirect sukses
$geoVerified = Session::get("geo_verified_{$jadwal['id']}");
$alreadyOk   = $isVerified && !empty($geoVerified);
?>

<!-- Breadcrumb & Header -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 fs-8">
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi') ?>" class="text-decoration-none text-muted">Pilih Mata Kuliah</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi/proses/' . $jadwal['id']) ?>" class="text-decoration-none text-muted">Konfirmasi Jadwal</a></li>
                    <li class="breadcrumb-item active text-primary fw-bold">Cek Lokasi GPS</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-geo-alt-fill text-primary me-2"></i> Pengecekan Lokasi GPS</h4>
            <p class="text-muted mb-0 fs-7">
                Sistem membutuhkan izin lokasi untuk memverifikasi bahwa Anda berada di dalam area kampus
                <strong><?= htmlspecialchars($campus['name']) ?></strong>.
            </p>
        </div>
        <div>
            <a href="<?= base_url('mahasiswa/absensi/proses/' . $jadwal['id']) ?>" class="btn btn-outline-secondary rounded-pill px-3 fs-8">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<!-- Tahapan Alur Presensi -->
<div class="card border-0 shadow-sm rounded-4 px-4 pt-4 pb-3 bg-white mb-4">
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="card border-success border-2 rounded-3 p-2 bg-success-subtle text-center h-100">
                <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                <div class="fw-semibold text-dark fs-8">✅ Pilih Mata Kuliah</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-primary border-2 rounded-3 p-2 text-center h-100 <?= $alreadyOk ? 'bg-success-subtle border-success' : '' ?>">
                <?php if ($alreadyOk): ?>
                    <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                    <div class="fw-semibold text-dark fs-8">✅ Lokasi Terverifikasi</div>
                <?php else: ?>
                    <i class="bi bi-geo-alt-fill text-primary fs-4 mb-1"></i>
                    <div class="fw-semibold text-dark fs-8">🔵 Cek Lokasi GPS</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border rounded-3 p-2 bg-light text-center h-100 opacity-75">
                <i class="bi bi-person-bounding-box text-muted fs-4 mb-1"></i>
                <div class="text-muted fs-8">Verifikasi Wajah AI</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border rounded-3 p-2 bg-light text-center h-100 opacity-75">
                <i class="bi bi-camera-fill text-muted fs-4 mb-1"></i>
                <div class="text-muted fs-8">Foto Kegiatan Kelas</div>
            </div>
        </div>
    </div>
</div>

<!-- Konfigurasi Kampus -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-building me-2 text-primary"></i> Informasi Lokasi Kampus</h5>
    <div class="row g-3 p-3 bg-light rounded-3">
        <div class="col-md-4">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Nama Kampus</div>
            <div class="fw-bold text-dark"><?= htmlspecialchars($campus['name']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Koordinat Pusat (GPS)</div>
            <div class="fw-bold text-dark font-monospace fs-8">
                <?= $campus['latitude'] ?>, <?= $campus['longitude'] ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Radius Toleransi</div>
            <div class="fw-bold text-success fs-5"><?= $campus['radius_meters'] ?> Meter</div>
        </div>
    </div>
</div>

<!-- Area Utama: Tombol Cek Lokasi & Status -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold text-dark mb-4"><i class="bi bi-radar me-2 text-primary"></i> Verifikasi Posisi Anda</h5>

    <?php if ($alreadyOk): ?>
        <!-- Sudah terverifikasi -->
        <div class="alert alert-success border-success rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2"><i class="bi bi-check-circle-fill text-success"></i></div>
                <div>
                    <h5 class="fw-bold text-success mb-1">Lokasi Terverifikasi!</h5>
                    <p class="mb-1">Anda berada <strong><?= number_format((float)($geoVerified['distance'] ?? 0), 1) ?> m</strong> dari titik pusat kampus (dalam radius <?= $campus['radius_meters'] ?> m).</p>
                    <div class="fs-8 text-muted">
                        📍 Lat: <code><?= number_format((float)($geoVerified['latitude'] ?? 0), 7) ?></code>
                        | Lon: <code><?= number_format((float)($geoVerified['longitude'] ?? 0), 7) ?></code>
                        | Akurasi: ±<?= number_format((float)($geoVerified['accuracy'] ?? 0), 0) ?> m
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center">
            <a href="<?= base_url('mahasiswa/absensi/wajah/' . $jadwal['id']) ?>" class="btn btn-success btn-lg rounded-pill px-5 fw-bold shadow-sm">
                <i class="bi bi-person-bounding-box me-2"></i> Lanjut ke Verifikasi Wajah AI
            </a>
        </div>
    <?php else: ?>
        <!-- Belum diverifikasi atau gagal -->
        <div class="text-center py-2 mb-4" id="geo-status-area">
            <div id="geo-idle" class="">
                <div class="geo-pulse-ring mx-auto mb-4">
                    <div class="p-4 bg-primary-subtle text-primary rounded-circle d-inline-flex shadow-sm" style="width:90px;height:90px;align-items:center;justify-content:center;">
                        <i class="bi bi-geo-alt-fill" style="font-size:2.5rem;"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Izinkan Akses Lokasi</h5>
                <p class="text-muted fs-7 mb-4">
                    Klik tombol di bawah untuk mengaktifkan GPS dan memeriksa apakah Anda berada di dalam area kampus.
                    Pastikan Anda sudah mengizinkan akses lokasi di browser.
                </p>
                <button id="btn-get-location" class="btn btn-primary btn-lg rounded-pill px-5 fw-semibold shadow-sm" type="button">
                    <i class="bi bi-crosshair me-2"></i> Aktifkan GPS & Cek Lokasi
                </button>
            </div>

            <!-- State: Sedang Memuat -->
            <div id="geo-loading" class="d-none">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div>
                <h5 class="fw-bold text-dark mb-1">Mengambil Posisi GPS...</h5>
                <p class="text-muted fs-7">Mohon tunggu, sistem sedang mendeteksi lokasi perangkat Anda.</p>
            </div>

            <!-- State: Berhasil (dalam radius) -->
            <div id="geo-success" class="d-none">
                <div class="p-4 bg-success-subtle text-success rounded-circle d-inline-flex mb-3" style="width:90px;height:90px;align-items:center;justify-content:center;">
                    <i class="bi bi-check-circle-fill" style="font-size:2.5rem;"></i>
                </div>
                <h5 class="fw-bold text-success mb-1">Lokasi Terverifikasi!</h5>
                <p class="text-muted fs-7 mb-1" id="geo-success-msg"></p>
                <div class="text-muted fs-8 mb-3" id="geo-success-detail"></div>
                <div class="spinner-border spinner-border-sm text-success me-2"></div>
                <small class="text-muted">Mengarahkan ke tahap berikutnya...</small>
            </div>

            <!-- State: Gagal (luar radius / tolak izin) -->
            <div id="geo-fail" class="d-none">
                <div class="p-4 bg-danger-subtle text-danger rounded-circle d-inline-flex mb-3" style="width:90px;height:90px;align-items:center;justify-content:center;">
                    <i class="bi bi-x-circle-fill" style="font-size:2.5rem;"></i>
                </div>
                <h5 class="fw-bold text-danger mb-1">Lokasi Ditolak!</h5>
                <p class="text-muted fs-7 mb-3" id="geo-fail-msg"></p>
                <button id="btn-retry" class="btn btn-outline-primary rounded-pill px-4" type="button">
                    <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                </button>
            </div>
        </div>

        <!-- Panduan Izin Lokasi -->
        <div class="alert alert-light border rounded-3 p-3 fs-8 text-muted mb-0">
            <i class="bi bi-info-circle me-1 text-primary"></i>
            <strong>Tips:</strong> Jika browser meminta izin lokasi, klik <strong>"Izinkan / Allow"</strong>.
            Untuk akurasi terbaik, aktifkan GPS perangkat dan pastikan Anda berada di area terbuka.
            Absensi hanya dapat dilakukan dalam radius <strong><?= $campus['radius_meters'] ?> m</strong> dari kampus.
        </div>
    <?php endif; ?>
</div>

<!-- Form tersembunyi untuk submit server-side -->
<form id="form-geo" method="POST"
      action="<?= base_url('mahasiswa/absensi/geolocation/' . $jadwal['id']) ?>"
      class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="latitude"  id="input-lat">
    <input type="hidden" name="longitude" id="input-lon">
    <input type="hidden" name="accuracy"  id="input-acc">
</form>

<script>
(function () {
    const jadwalId  = <?= (int)$jadwal['id'] ?>;
    const campusLat = <?= (float)$campus['latitude'] ?>;
    const campusLon = <?= (float)$campus['longitude'] ?>;
    const radius    = <?= (int)$campus['radius_meters'] ?>;

    const btnGet   = document.getElementById('btn-get-location');
    const btnRetry = document.getElementById('btn-retry');
    const states   = {
        idle   : document.getElementById('geo-idle'),
        loading: document.getElementById('geo-loading'),
        success: document.getElementById('geo-success'),
        fail   : document.getElementById('geo-fail'),
    };

    function showState(name) {
        Object.values(states).forEach(el => el?.classList.add('d-none'));
        states[name]?.classList.remove('d-none');
    }

    // Haversine di sisi client (hanya preview — validasi asli di server)
    function haversine(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
        return Math.round(2 * R * Math.asin(Math.sqrt(a)) * 10) / 10;
    }

    function startGeo() {
        if (!navigator.geolocation) {
            showState('fail');
            document.getElementById('geo-fail-msg').textContent =
                'Browser Anda tidak mendukung Geolocation API. Coba gunakan browser modern seperti Chrome atau Firefox.';
            return;
        }

        showState('loading');

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat      = pos.coords.latitude;
                const lon      = pos.coords.longitude;
                const accuracy = pos.coords.accuracy;
                const dist     = haversine(lat, lon, campusLat, campusLon);

                // Preview client-side (informasi saja)
                console.log(`GPS: ${lat}, ${lon} | Jarak: ${dist}m | Akurasi: ${accuracy}m`);

                // Isi form dan kirim ke server untuk validasi ASLI
                document.getElementById('input-lat').value = lat;
                document.getElementById('input-lon').value = lon;
                document.getElementById('input-acc').value = accuracy;

                // Kirim via fetch (AJAX) untuk UX yang lebih baik
                const form  = document.getElementById('form-geo');
                const body  = new FormData(form);
                const token = document.querySelector('input[name="_csrf_token"]')?.value;

                // Set header X-Requested-With agar server mengenali sebagai AJAX
                fetch(form.action, {
                    method : 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body   : body
                })
                .then(r => r.json())
                .then(function (res) {
                    if (res.success) {
                        showState('success');
                        document.getElementById('geo-success-msg').textContent    = res.message;
                        document.getElementById('geo-success-detail').innerHTML   =
                            `📍 Lat: <code>${lat.toFixed(7)}</code> | Lon: <code>${lon.toFixed(7)}</code> | Akurasi: ±${accuracy.toFixed(0)} m`;
                        // Reload halaman dengan ?verified=1 setelah 1.5 detik
                        setTimeout(() => {
                            window.location.href = window.location.pathname + '?verified=1';
                        }, 1500);
                    } else {
                        showState('fail');
                        document.getElementById('geo-fail-msg').textContent = res.message;
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    showState('fail');
                    document.getElementById('geo-fail-msg').textContent =
                        'Terjadi kesalahan jaringan saat memvalidasi lokasi. Periksa koneksi internet Anda.';
                });
            },
            function (err) {
                showState('fail');
                const msgs = {
                    1: 'Izin lokasi ditolak. Klik ikon kunci di address bar browser dan izinkan akses lokasi, lalu muat ulang halaman.',
                    2: 'Posisi GPS tidak dapat ditentukan. Pastikan GPS perangkat aktif dan Anda berada di area terbuka.',
                    3: 'Waktu pengambilan lokasi habis. Pastikan GPS aktif dan coba lagi.'
                };
                document.getElementById('geo-fail-msg').textContent = msgs[err.code] || 'Gagal mendapatkan lokasi. Coba lagi.';
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    btnGet?.addEventListener('click', startGeo);
    btnRetry?.addEventListener('click', function () { showState('idle'); });
})();
</script>
