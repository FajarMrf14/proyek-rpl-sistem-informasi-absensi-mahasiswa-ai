<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 fs-8">
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('mahasiswa/absensi') ?>" class="text-decoration-none text-muted">Pilih Mata Kuliah</a></li>
                    <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Konfirmasi Jadwal</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-2"></i> Mata Kuliah Berhasil Dipilih</h4>
            <p class="text-muted mb-0 fs-7">Jadwal hari ini terverifikasi. Lanjutkan ke pengecekan lokasi GPS.</p>
        </div>
        <div>
            <a href="<?= base_url('mahasiswa/absensi') ?>" class="btn btn-outline-secondary rounded-pill px-3 fs-8">
                <i class="bi bi-arrow-left me-1"></i> Pilih Mata Kuliah Lain
            </a>
        </div>
    </div>
</div>

<!-- Detail Mata Kuliah Terpilih -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary font-monospace fs-7 px-3 py-2"><?= htmlspecialchars($jadwal['kode_mk']) ?></span>
            <span class="badge bg-success px-3 py-2 rounded-pill fs-8">
                <i class="bi bi-check-circle-fill me-1"></i> Jadwal Valid Hari Ini
            </span>
        </div>
        <span class="badge bg-light text-secondary border fs-8">
            <?= htmlspecialchars($jadwal['tahun_akademik']) ?>
        </span>
    </div>

    <h3 class="fw-bold text-dark mb-3"><?= htmlspecialchars($jadwal['nama_mk']) ?></h3>

    <div class="row g-3 p-3 bg-light rounded-3 mb-2">
        <div class="col-md-3 col-6">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Kelas & Prodi</div>
            <div class="fw-bold text-dark fs-7"><?= htmlspecialchars($jadwal['nama_kelas']) ?> (<?= htmlspecialchars($jadwal['prodi']) ?>)</div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Bobot SKS</div>
            <div class="fw-bold text-dark fs-7"><?= (int)$jadwal['sks'] ?> SKS (Semester <?= (int)$jadwal['semester_paket'] ?>)</div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Waktu & Hari</div>
            <div class="fw-bold text-dark fs-7">
                <i class="bi bi-clock me-1 text-primary"></i> <?= htmlspecialchars($jadwal['hari']) ?>, <?= substr($jadwal['jam_mulai'], 0, 5) ?> - <?= substr($jadwal['jam_selesai'], 0, 5) ?> WIB
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-muted fs-8 text-uppercase fw-semibold">Ruang Kuliah</div>
            <div class="fw-bold text-dark fs-7">
                <i class="bi bi-geo-alt me-1 text-danger"></i> <?= htmlspecialchars($jadwal['ruangan']) ?>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 pt-2 text-muted fs-7">
        <i class="bi bi-person-badge-fill text-primary"></i>
        <span>Dosen: <strong class="text-dark"><?= htmlspecialchars($jadwal['nama_dosen']) ?></strong> (NIDN: <?= htmlspecialchars($jadwal['nidn']) ?>)</span>
    </div>
</div>

<!-- Alur Tahapan Presensi -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-2"></i> Tahapan Presensi Perkuliahan</h5>
    <div class="row g-3">
        <!-- Step 1 SELESAI -->
        <div class="col-md-3 col-6">
            <div class="card border-success border-2 rounded-4 p-3 bg-success-subtle h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-success rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">
                        <i class="bi bi-check-lg text-white"></i>
                    </span>
                    <span class="badge bg-success fs-8">Tahap 1</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Pilih Mata Kuliah</h6>
                <p class="text-muted fs-8 mb-0">✅ Jadwal hari ini terverifikasi valid.</p>
            </div>
        </div>
        <!-- Step 2 AKTIF BERIKUTNYA -->
        <div class="col-md-3 col-6">
            <div class="card border-primary border-2 rounded-4 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">
                        <i class="bi bi-geo-alt-fill text-white fs-8"></i>
                    </span>
                    <span class="badge bg-primary fs-8">Tahap 2</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Cek Lokasi GPS</h6>
                <p class="text-muted fs-8 mb-0">Pengecekan radius <?= (int)($campus_radius ?? 100) ?>m dari kampus.</p>
            </div>
        </div>
        <!-- Step 3 -->
        <div class="col-md-3 col-6">
            <div class="card border rounded-4 p-3 bg-light h-100 opacity-75">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">3</span>
                    <span class="badge bg-light text-secondary border fs-8">Tahap 3</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Verifikasi Wajah AI</h6>
                <p class="text-muted fs-8 mb-0">Pencocokan biometrik wajah.</p>
            </div>
        </div>
        <!-- Step 4 -->
        <div class="col-md-3 col-6">
            <div class="card border rounded-4 p-3 bg-light h-100 opacity-75">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">4</span>
                    <span class="badge bg-light text-secondary border fs-8">Tahap 4</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Foto Kegiatan Kelas</h6>
                <p class="text-muted fs-8 mb-0">Dokumentasi visual kehadiran.</p>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Lanjut ke Pengecekan Lokasi -->
<div class="alert alert-info border-0 shadow-sm rounded-4 p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-info-subtle text-info rounded-circle fs-3">
            <i class="bi bi-geo-alt-fill"></i>
        </div>
        <div>
            <h6 class="fw-bold text-dark mb-1">Mata Kuliah Berhasil Dipilih!</h6>
            <p class="text-muted fs-7 mb-0">
                Klik <strong>"Lanjut Cek Lokasi GPS"</strong> untuk memulai pengecekan posisi Anda di dalam radius kampus.
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('mahasiswa/absensi') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            Kembali
        </a>
        <a href="<?= base_url('mahasiswa/absensi/geolocation/' . $jadwal['id']) ?>"
           class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill"></i> Lanjut Cek Lokasi GPS
        </a>
    </div>
</div>
