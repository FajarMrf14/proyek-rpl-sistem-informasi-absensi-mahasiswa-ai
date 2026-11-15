<?php
/**
 * View: mahasiswa/kegiatan.php
 * Halaman Foto Kegiatan Kelas — Tahap 4 Alur Presensi
 *
 * Variabel yang tersedia dari controller:
 *   $jadwal       — array detail jadwal terpilih
 *   $geoSession   — array data verifikasi GPS dari Tahap 2
 *   $faceSession  — array data verifikasi Wajah AI dari Tahap 3
 *   $user         — array profil mahasiswa
 */
?>

<!-- Breadcrumb & Header -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 fs-8">
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi') ?>" class="text-decoration-none text-muted">Pilih Mata Kuliah</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi/geolocation/' . $jadwal['id']) ?>" class="text-decoration-none text-muted">Cek Lokasi GPS</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi/wajah/' . $jadwal['id']) ?>" class="text-decoration-none text-muted">Verifikasi Wajah AI</a></li>
                    <li class="breadcrumb-item active text-primary fw-bold">Foto Kegiatan Kelas</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-camera-fill text-primary me-2"></i> Dokumentasi Foto Kegiatan Kelas</h4>
            <p class="text-muted mb-0 fs-7">
                Ambil foto kegiatan suasana kelas/pembelajaran untuk melengkapi bukti presensi perkuliahan hari ini.
            </p>
        </div>
        <div>
            <a href="<?= base_url('mahasiswa/absensi/wajah/' . $jadwal['id']) ?>" class="btn btn-outline-secondary rounded-pill px-3 fs-8">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Wajah AI
            </a>
        </div>
    </div>
</div>

<!-- Alur Tahapan Presensi -->
<div class="card border-0 shadow-sm rounded-4 px-4 pt-4 pb-3 bg-white mb-4">
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="card border-success border-2 rounded-3 p-2 bg-success-subtle text-center h-100">
                <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                <div class="fw-semibold text-dark fs-8">✅ Pilih Mata Kuliah</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-success border-2 rounded-3 p-2 bg-success-subtle text-center h-100">
                <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                <div class="fw-semibold text-dark fs-8">✅ GPS (<?= number_format((float)($geoSession['distance'] ?? 0), 1) ?>m)</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-success border-2 rounded-3 p-2 bg-success-subtle text-center h-100">
                <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                <div class="fw-semibold text-dark fs-8">✅ Wajah AI (<?= number_format((float)($faceSession['similarity'] ?? 0), 1) ?>%)</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-primary border-2 rounded-3 p-2 text-center h-100">
                <i class="bi bi-camera-fill text-primary fs-4 mb-1"></i>
                <div class="fw-semibold text-dark fs-8">🔵 Foto Kegiatan Kelas</div>
            </div>
        </div>
    </div>
</div>

<!-- Kamera Foto Kegiatan Kelas -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-camera me-2 text-primary"></i> Ambil Foto Suasana Perkuliahan</h5>
    <p class="text-muted fs-7 mb-4">
        Arahkan kamera ke arah kegiatan kelas atau foto selfie berlatar ruang kuliah sebagai bukti visual kehadiran.
    </p>

    <div class="row align-items-center g-4">
        <div class="col-lg-7 text-center">
            <div class="rounded-4 overflow-hidden shadow-sm border bg-dark mx-auto style-kegiatan-box" style="max-width: 520px;">
                <video id="kegiatan-video" class="w-100" style="height: 360px; object-fit: cover;" autoplay playsinline muted></video>
                <img id="kegiatan-preview" class="w-100 d-none" style="height: 360px; object-fit: cover;" alt="Preview Foto Kegiatan">
            </div>
            <canvas id="kegiatan-canvas" class="d-none"></canvas>

            <!-- Tombol Kontrol Kamera -->
            <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                <button id="btn-start-cam" class="btn btn-outline-primary rounded-pill px-4" type="button">
                    <i class="bi bi-camera-video me-1"></i> Buka Kamera
                </button>
                <button id="btn-snap" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" type="button" disabled>
                    <i class="bi bi-camera me-1"></i> Ambil Foto Kegiatan
                </button>
                <button id="btn-retake" class="btn btn-outline-secondary rounded-pill px-4 d-none" type="button">
                    <i class="bi bi-arrow-clockwise me-1"></i> Foto Ulang
                </button>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border p-4 bg-light rounded-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clipboard-check me-2 text-success"></i> Konfirmasi Data Presensi</h6>
                <div class="fs-8 text-muted mb-3">
                    Seluruh tahapan (Lokasi GPS, Wajah AI, dan Foto Kegiatan) telah terpenuhi dan siap dikirim ke database.
                </div>

                <div class="p-3 bg-white rounded-3 border mb-3">
                    <div class="d-flex justify-content-between fs-8 border-bottom pb-2 mb-2">
                        <span class="text-muted">Mata Kuliah:</span>
                        <strong class="text-dark"><?= htmlspecialchars($jadwal['nama_mk']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between fs-8 border-bottom pb-2 mb-2">
                        <span class="text-muted">Mahasiswa:</span>
                        <strong class="text-dark"><?= htmlspecialchars($user['nama_lengkap'] ?? $user['username']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between fs-8 border-bottom pb-2 mb-2">
                        <span class="text-muted">Verifikasi Lokasi GPS:</span>
                        <span class="badge bg-success">Dalam Radius (<?= number_format((float)($geoSession['distance'] ?? 0), 1) ?>m)</span>
                    </div>
                    <div class="d-flex justify-content-between fs-8">
                        <span class="text-muted">Verifikasi Wajah AI:</span>
                        <span class="badge bg-success">Match (<?= number_format((float)($faceSession['similarity'] ?? 0), 1) ?>%)</span>
                    </div>
                </div>

                <button id="btn-submit-absensi" class="btn btn-success btn-lg rounded-pill w-100 fw-bold shadow-sm" type="button" disabled>
                    <i class="bi bi-check-circle-fill me-2"></i> Kirim Presensi Sekarang
                </button>

                <div id="status-kegiatan" class="mt-3 fs-8 text-center text-muted d-none">
                    <div class="spinner-border spinner-border-sm text-primary me-1"></div> Mengunggah & mencatat absensi...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form Hidden -->
<form id="form-kegiatan" method="POST" action="<?= base_url('mahasiswa/absensi/kegiatan/' . $jadwal['id']) ?>" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="image" id="input-kegiatan-image">
</form>

<script>
(function () {
    const video = document.getElementById('kegiatan-video');
    const preview = document.getElementById('kegiatan-preview');
    const canvas = document.getElementById('kegiatan-canvas');
    const btnStart = document.getElementById('btn-start-cam');
    const btnSnap = document.getElementById('btn-snap');
    const btnRetake = document.getElementById('btn-retake');
    const btnSubmit = document.getElementById('btn-submit-absensi');
    const statusBox = document.getElementById('status-kegiatan');

    let stream = null;

    async function startCam() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'environment' },
                audio: false
            });
            video.srcObject = stream;
            btnStart.classList.add('d-none');
            btnSnap.disabled = false;
        } catch (err) {
            console.error(err);
            Swal.fire('Error Kamera', 'Gagal mengakses kamera perangkat.', 'error');
        }
    }

    function snapPhoto() {
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        document.getElementById('input-kegiatan-image').value = dataUrl;

        preview.src = dataUrl;
        preview.classList.remove('d-none');
        video.classList.add('d-none');

        btnSnap.classList.add('d-none');
        btnRetake.classList.remove('d-none');
        btnSubmit.disabled = false;
    }

    function retakePhoto() {
        preview.classList.add('d-none');
        video.classList.remove('d-none');
        btnSnap.classList.remove('d-none');
        btnRetake.classList.add('d-none');
        btnSubmit.disabled = true;
        document.getElementById('input-kegiatan-image').value = '';
    }

    function submitAbsensi() {
        btnSubmit.disabled = true;
        statusBox.classList.remove('d-none');

        const form = document.getElementById('form-kegiatan');
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            statusBox.classList.add('d-none');
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Absensi Berhasil!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = data.redirect || ('<?= base_url('mahasiswa/absensi/sukses/' . $jadwal['id']) ?>');
                });
            } else {
                btnSubmit.disabled = false;
                Swal.fire('Absensi Ditolak', data.message || 'Gagal menyimpan absensi.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            btnSubmit.disabled = false;
            statusBox.classList.add('d-none');
            Swal.fire('Error', 'Terjadi kesalahan jaringan.', 'error');
        });
    }

    btnStart?.addEventListener('click', startCam);
    btnSnap?.addEventListener('click', snapPhoto);
    btnRetake?.addEventListener('click', retakePhoto);
    btnSubmit?.addEventListener('click', submitAbsensi);
})();
</script>
