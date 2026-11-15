<div class="container py-4">
    <!-- Hero Section -->
    <div class="card border-0 rounded-4 shadow-sm overflow-hidden bg-primary-gradient text-white p-4 p-md-5 mb-5 position-relative">
        <div class="row align-items-center position-relative z-1">
            <div class="col-lg-8">
                <span class="badge bg-white-20 text-white rounded-pill px-3 py-2 mb-3 fw-semibold">
                    <i class="bi bi-stars me-1"></i> Smart Campus Attendance 2026
                </span>
                <h1 class="display-5 fw-bold text-white mb-3">Sistem Absensi Mahasiswa Berbasis Web dengan AI</h1>
                <p class="lead text-white-80 mb-4">
                    Presensi perkuliahan modern untuk sivitas akademika <strong><?= htmlspecialchars($campus_name) ?></strong> dengan integrasi Geolocation Geofencing, Face Recognition AI, dan dokumentasi visual kelas.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= base_url('login') ?>" class="btn btn-light btn-lg px-4 fw-bold text-primary rounded-pill shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Portal
                    </a>
                    <a href="#fitur" class="btn btn-outline-light btn-lg px-4 rounded-pill">
                        <i class="bi bi-info-circle me-2"></i> Pelajari Fitur
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center">
                <div class="hero-illustration p-4">
                    <i class="bi bi-shield-check text-white-50" style="font-size: 8rem;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Parameter Kampus Card -->
    <div class="row g-4 mb-5" id="parameter-kampus">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-circle bg-primary-subtle text-primary rounded-3 p-3">
                        <i class="bi bi-building fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted fs-8 text-uppercase fw-semibold">Nama Kampus</div>
                        <div class="fs-5 fw-bold text-dark"><?= htmlspecialchars($campus_name) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border-start border-success border-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-circle bg-success-subtle text-success rounded-3 p-3">
                        <i class="bi bi-geo-alt-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted fs-8 text-uppercase fw-semibold">Titik Koordinat Kampus</div>
                        <div class="fs-6 fw-bold text-dark font-monospace"><?= htmlspecialchars($coords) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border-start border-info border-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-circle bg-info-subtle text-info rounded-3 p-3">
                        <i class="bi bi-broadcast fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted fs-8 text-uppercase fw-semibold">Radius Geofence</div>
                        <div class="fs-5 fw-bold text-dark"><?= htmlspecialchars($radius) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div id="fitur" class="mb-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark">Arsitektur & Alur Presensi Mahasiswa</h2>
            <p class="text-muted">Proses presensi dirancang otomatis, aman, dan meminimalkan kecurangan titip absen.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($features as $f): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100 feature-card bg-white">
                        <div class="feature-icon-wrapper mb-3 text-primary bg-primary-subtle rounded-3 d-inline-flex p-3">
                            <i class="bi <?= $f['icon'] ?> fs-3"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($f['title']) ?></h5>
                        <p class="text-muted fs-7 mb-0"><?= htmlspecialchars($f['desc']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Attendance Step Workflow Preview -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-5">
        <h4 class="fw-bold text-dark mb-4 text-center">Tahapan Alur Presensi Mahasiswa</h4>
        <div class="row text-center g-4">
            <div class="col-md-2 col-4">
                <div class="step-circle bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5">1</div>
                <div class="fw-semibold text-dark fs-7">Login NIM</div>
            </div>
            <div class="col-md-2 col-4">
                <div class="step-circle bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5">2</div>
                <div class="fw-semibold text-dark fs-7">Pilih Mata Kuliah</div>
            </div>
            <div class="col-md-2 col-4">
                <div class="step-circle bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5">3</div>
                <div class="fw-semibold text-dark fs-7">Cek Geolocation</div>
            </div>
            <div class="col-md-2 col-4">
                <div class="step-circle bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5">4</div>
                <div class="fw-semibold text-dark fs-7">Verifikasi Wajah AI</div>
            </div>
            <div class="col-md-2 col-4">
                <div class="step-circle bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5">5</div>
                <div class="fw-semibold text-dark fs-7">Foto Kegiatan</div>
            </div>
            <div class="col-md-2 col-4">
                <div class="step-circle bg-success text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fw-bold fs-5"><i class="bi bi-check-lg"></i></div>
                <div class="fw-semibold text-success fs-7">Presensi Berhasil</div>
            </div>
        </div>
    </div>
</div>
