<?php
/**
 * View: mahasiswa/wajah.php
 * Halaman Verifikasi Wajah AI — Tahap 3 Alur Presensi
 *
 * Variabel yang tersedia dari controller:
 *   $jadwal               — array detail jadwal terpilih
 *   $geoSession           — array data verifikasi GPS dari Tahap 2
 *   $user                 — array profil mahasiswa
 *   $wajahData            — array data biometrik wajah referensi (jika ada)
 *   $isFaceRegistered     — bool apakah wajah terdaftar di DB
 *   $similarityThreshold  — float threshold similarity
 */

use App\Core\Session;

$isVerified   = !empty($_GET['verified']);
$faceSession  = Session::get("face_verified_{$jadwal['id']}");
$alreadyOk    = $isVerified && !empty($faceSession);
?>

<!-- Style Khusus Kamera & Scanner Wajah -->
<style>
.webcam-container {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
    background: #000;
    border-radius: 1.5rem;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.webcam-video {
    width: 100%;
    height: 380px;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror camera effect */
}

/* Face Oval Detection Guide Overlay */
.face-oval-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 220px;
    height: 290px;
    border: 3px dashed rgba(13, 110, 253, 0.8);
    border-radius: 50%;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);
    pointer-events: none;
    transition: all 0.3s ease;
}

.face-oval-guide.active-scanning {
    border-color: #198754;
    border-style: solid;
    animation: pulseBorder 1.5s infinite;
}

@keyframes pulseBorder {
    0% { box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45), 0 0 15px rgba(25, 135, 84, 0.8); }
    50% { box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45), 0 0 30px rgba(25, 135, 84, 1); }
    100% { box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45), 0 0 15px rgba(25, 135, 84, 0.8); }
}

/* Scanning Line Animation */
.scanner-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #0d6efd, #00f2fe, transparent);
    box-shadow: 0 0 15px #00f2fe;
    animation: scanMove 2.5s infinite ease-in-out;
    display: none;
}

@keyframes scanMove {
    0% { top: 10%; opacity: 0; }
    15% { opacity: 1; }
    85% { opacity: 1; }
    100% { top: 90%; opacity: 0; }
}
</style>

<!-- Breadcrumb & Header -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 fs-8">
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi') ?>" class="text-decoration-none text-muted">Pilih Mata Kuliah</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi/geolocation/' . $jadwal['id']) ?>" class="text-decoration-none text-muted">Cek Lokasi GPS</a></li>
                    <li class="breadcrumb-item active text-primary fw-bold">Verifikasi Wajah AI</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-person-bounding-box text-primary me-2"></i> Verifikasi Wajah AI Biometrik</h4>
            <p class="text-muted mb-0 fs-7">
                Posisikan wajah Anda tepat di tengah bingkai panduan kamera untuk memverifikasi identitas kehadiran.
            </p>
        </div>
        <div>
            <a href="<?= base_url('mahasiswa/absensi/geolocation/' . $jadwal['id']) ?>" class="btn btn-outline-secondary rounded-pill px-3 fs-8">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke GPS
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
                <div class="fw-semibold text-dark fs-8">✅ Cek Lokasi GPS (<?= number_format((float)($geoSession['distance'] ?? 0), 1) ?>m)</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-primary border-2 rounded-3 p-2 text-center h-100 <?= $alreadyOk ? 'bg-success-subtle border-success' : '' ?>">
                <?php if ($alreadyOk): ?>
                    <i class="bi bi-check-circle-fill text-success fs-4 mb-1"></i>
                    <div class="fw-semibold text-dark fs-8">✅ Wajah Terverifikasi</div>
                <?php else: ?>
                    <i class="bi bi-person-bounding-box text-primary fs-4 mb-1"></i>
                    <div class="fw-semibold text-dark fs-8">🔵 Verifikasi Wajah AI</div>
                <?php endif; ?>
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

<!-- Cek Status Pendaftaran Biometrik -->
<?php if (!$isFaceRegistered): ?>
    <div class="alert alert-warning border-warning rounded-4 p-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div>
                <h5 class="fw-bold text-dark mb-1">Data Biometrik Wajah Belum Terdaftar</h5>
                <p class="mb-0 fs-7">
                    Anda belum mendaftarkan biometrik wajah pada sistem STMIK IKMI Cirebon. Silakan hubungi Sekretariat Akademik atau lakukan registrasi wajah awal di menu Profil.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Area Utama: Interface Kamera Web & Verifikasi -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold text-dark mb-4"><i class="bi bi-camera me-2 text-primary"></i> Kamera Pemindai Wajah</h5>

    <?php if ($alreadyOk): ?>
        <!-- State: Sudah Terverifikasi -->
        <div class="alert alert-success border-success rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2"><i class="bi bi-patch-check-fill text-success"></i></div>
                <div>
                    <h5 class="fw-bold text-success mb-1">Wajah Berhasil Terverifikasi!</h5>
                    <p class="mb-1">Pencocokan biometrik AI terverifikasi cocok dengan data referensi (Tingkat Kemiripan: <strong><?= number_format((float)($faceSession['similarity'] ?? 0), 1) ?>%</strong>).</p>
                    <div class="fs-8 text-muted">
                        🕒 Diverifikasi pada: <?= $faceSession['verified_at'] ?? date('Y-m-d H:i:s') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center">
            <a href="<?= base_url('mahasiswa/absensi/kegiatan/' . $jadwal['id']) ?>" class="btn btn-success btn-lg rounded-pill px-5 fw-bold shadow-sm">
                <i class="bi bi-camera-fill me-2"></i> Lanjut ke Foto Kegiatan Kelas
            </a>
        </div>
    <?php else: ?>
        <!-- State: Kamera Live & Verifikasi -->
        <div class="row align-items-center g-4">
            <div class="col-lg-7 text-center">
                <div class="webcam-container">
                    <video id="webcam-video" class="webcam-video" autoplay playsinline muted></video>
                    <div id="face-guide" class="face-oval-guide"></div>
                    <div id="scanner-line" class="scanner-line"></div>
                </div>
                <canvas id="snapshot-canvas" class="d-none"></canvas>

                <!-- Tombol Kontrol Kamera -->
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                    <button id="btn-start-camera" class="btn btn-outline-primary rounded-pill px-4" type="button">
                        <i class="bi bi-camera-video me-1"></i> Aktifkan Kamera
                    </button>
                    <button id="btn-capture-verify" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" type="button" disabled>
                        <i class="bi bi-shield-lock me-1"></i> Ambil Snapshot & Verifikasi Wajah
                    </button>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="p-3 bg-light rounded-4 border">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-lightbulb me-1 text-warning"></i> Panduan Verifikasi Wajah AI</h6>
                    <ul class="fs-8 text-muted ps-3 mb-3">
                        <li class="mb-2">Pastikan pencahayaan ruangan cukup dan tidak membelakangi sinar terang (backlight).</li>
                        <li class="mb-2">Lepas kacamata hitam, topi, atau masker yang menutupi area wajah.</li>
                        <li class="mb-2">Posisikan wajah tepat di dalam garis oval panduan kamera.</li>
                        <li class="mb-2">Tatap langsung ke arah kamera dan bersikap tenang saat pengambilan snapshot.</li>
                    </ul>

                    <div class="p-3 bg-white rounded-3 border">
                        <div class="text-muted fs-8">Ambang Batas Kemiripan (Threshold AI):</div>
                        <div class="fw-bold text-primary fs-6">≥ <?= (float)($similarityThreshold * 100) ?>% Match</div>
                        <div class="text-muted fs-8 mt-1">Data biometrik diproses secara aman menggunakan algoritma enkripsi vektor.</div>
                    </div>

                    <!-- Status Response UI -->
                    <div id="face-status-area" class="mt-3 d-none">
                        <div id="face-loading" class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 mb-0 d-none">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="fs-8">Memproses analisis biometrik AI...</span>
                        </div>
                        <div id="face-error" class="alert alert-danger border-0 rounded-3 fs-8 mb-0 d-none"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Form tersembunyi untuk POST snapshot -->
<form id="form-wajah" method="POST"
      action="<?= base_url('mahasiswa/absensi/wajah/' . $jadwal['id']) ?>"
      class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="image" id="input-face-image">
    <input type="hidden" name="descriptor" id="input-face-descriptor">
</form>

<script>
(function () {
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('snapshot-canvas');
    const btnStart = document.getElementById('btn-start-camera');
    const btnCapture = document.getElementById('btn-capture-verify');
    const faceGuide = document.getElementById('face-guide');
    const scannerLine = document.getElementById('scanner-line');
    const statusArea = document.getElementById('face-status-area');
    const statusLoading = document.getElementById('face-loading');
    const statusError = document.getElementById('face-error');

    let stream = null;

    // Aktifkan Kamera
    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire('Browser Tidak Mendukung', 'Browser Anda tidak mendukung akses webcam.', 'error');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                audio: false
            });
            video.srcObject = stream;
            btnStart.classList.add('d-none');
            btnCapture.disabled = false;
            scannerLine.style.display = 'block';
            faceGuide.classList.add('active-scanning');
        } catch (err) {
            console.error('Gagal mengakses kamera:', err);
            Swal.fire('Akses Kamera Ditolak', 'Izinkan browser untuk mengaktifkan perangkat kamera.', 'error');
        }
    }

    // Ambil Snapshot & Kirim ke Server
    function captureAndVerify() {
        if (!stream) return;

        btnCapture.disabled = true;
        statusArea.classList.remove('d-none');
        statusLoading.classList.remove('d-none');
        statusError.classList.add('d-none');

        // Draw image ke canvas
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        
        // Mirroring horizontally agar sesuai preview webcam
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Ambil DataURL Base64 PNG
        const imageDataUrl = canvas.toDataURL('image/png', 0.9);
        document.getElementById('input-face-image').value = imageDataUrl;

        // Kirim AJAX POST
        const form = document.getElementById('form-wajah');
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            statusLoading.classList.add('d-none');
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Verifikasi Wajah AI Berhasil!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = window.location.pathname + '?verified=1';
                });
            } else {
                btnCapture.disabled = false;
                statusError.classList.remove('d-none');
                statusError.textContent = data.message || 'Verifikasi wajah gagal. Coba lagi.';
            }
        })
        .catch(err => {
            console.error(err);
            btnCapture.disabled = false;
            statusLoading.classList.add('d-none');
            statusError.classList.remove('d-none');
            statusError.textContent = 'Terjadi kesalahan jaringan saat memproses verifikasi biometrik.';
        });
    }

    btnStart?.addEventListener('click', startCamera);
    btnCapture?.addEventListener('click', captureAndVerify);
})();
</script>
