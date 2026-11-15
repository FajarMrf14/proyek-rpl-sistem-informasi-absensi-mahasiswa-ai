<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Dashboard Administrator Akademik</h4>
            <p class="text-muted mb-0 fs-7">
                Panel Pusat Kontrol & Manajemen Sistem Presensi <strong>STMIK IKMI Cirebon</strong> | Masuk sebagai: <strong><?= htmlspecialchars(!empty($user['nama_lengkap']) ? ($user['nama_lengkap'] . ' (' . $user['username'] . ')') : $user['username']) ?></strong>
            </p>
        </div>
        <div>
            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fs-7 fw-semibold">
                <i class="bi bi-shield-lock-fill me-1"></i> Hak Akses Administrator Penuh
            </span>
        </div>
    </div>
</div>

<!-- Master Data Statistics -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-bottom border-primary border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Total Mahasiswa</div>
            <div class="fs-2 fw-bold text-primary my-1"><?= $stats['total_mahasiswa'] ?></div>
            <div class="fs-8 text-muted">Terdaftar di Sistem</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-bottom border-success border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Total Dosen</div>
            <div class="fs-2 fw-bold text-success my-1"><?= $stats['total_dosen'] ?></div>
            <div class="fs-8 text-muted">Dosen Pengampu</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-bottom border-info border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Mata Kuliah</div>
            <div class="fs-2 fw-bold text-info my-1"><?= $stats['total_mk'] ?></div>
            <div class="fs-8 text-muted">Kurikulum Aktif</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-bottom border-warning border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Jadwal Kelas</div>
            <div class="fs-2 fw-bold text-warning my-1"><?= $stats['total_jadwal'] ?></div>
            <a href="<?= base_url('admin/jadwal') ?>" class="fs-8 fw-semibold text-warning text-decoration-none">
                Kelola Jadwal <i class="bi bi-chevron-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- Konfigurasi Lokasi & Radius Kampus STMIK IKMI -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Konfigurasi Lokasi Kampus & Radius Geofence</h5>
        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="Swal.fire('Info', 'Modul pengubahan koordinat radius kampus akan diaktifkan pada tahap modul admin.', 'info')">
            <i class="bi bi-pencil-square me-1"></i> Edit Pengaturan
        </button>
    </div>
    <div class="row g-3 p-3 bg-light rounded-3">
        <div class="col-md-4">
            <label class="form-label text-muted fs-8 mb-1">Nama Kampus</label>
            <div class="fw-bold text-dark fs-7"><?= htmlspecialchars($campus['nama_kampus'] ?? 'STMIK IKMI Cirebon') ?></div>
            <div class="text-muted fs-8"><?= htmlspecialchars($campus['alamat'] ?? '-') ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label text-muted fs-8 mb-1">Titik Pusat Koordinat (GPS)</label>
            <div class="fw-bold text-dark font-monospace fs-7">
                Lat: <?= htmlspecialchars($campus['latitude'] ?? '-6.74415840') ?><br>
                Long: <?= htmlspecialchars($campus['longitude'] ?? '108.54415660') ?>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label text-muted fs-8 mb-1">Radius Toleransi Presensi</label>
            <div class="fs-4 fw-bold text-success mb-0"><?= htmlspecialchars($campus['radius_meter'] ?? '100') ?> Meter</div>
            <div class="text-muted fs-8">Threshold Wajah: <?= htmlspecialchars($campus['toleransi_wajah_similarity'] ?? '0.55') ?></div>
        </div>
    </div>
</div>
