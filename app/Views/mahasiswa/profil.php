<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h4 class="fw-bold text-dark mb-1"><i class="bi bi-person-circle text-primary me-2"></i> Profil Data Mahasiswa</h4>
    <p class="text-muted mb-0 fs-7">Informasi identitas sivitas akademika dan status biometrik wajah terdaftar.</p>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Informasi Pribadi & Akademik -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-card-heading text-primary me-2"></i> Data Akademik</h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Nomor Induk Mahasiswa (NIM)</label>
                    <div class="fs-6 fw-bold text-dark font-monospace"><?= htmlspecialchars($user['nim'] ?? $user['username']) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Nama Lengkap</label>
                    <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($user['nama_lengkap'] ?? $user['username']) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Program Studi</label>
                    <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($user['prodi'] ?? 'Teknik Informatika') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Tahun Angkatan</label>
                    <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($user['angkatan'] ?? '2023') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Jenis Kelamin</label>
                    <div class="fs-6 fw-bold text-dark"><?= ($user['jenis_kelamin'] ?? 'L') === 'L' ? 'Laki-Laki' : 'Perempuan' ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Status Akademik</label>
                    <div>
                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill fw-semibold text-uppercase">
                            <?= htmlspecialchars($user['status_akademik'] ?? 'aktif') ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Alamat Email</label>
                    <div class="fs-7 text-dark"><?= htmlspecialchars($user['email'] ?? $user['user_email'] ?? '-') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 mb-1">Nomor Telepon / WhatsApp</label>
                    <div class="fs-7 text-dark"><?= htmlspecialchars($user['no_hp'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Status Registrasi Wajah AI -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-bounding-box text-primary me-2"></i> Biometrik AI Wajah</h5>
            
            <div class="my-3">
                <?php if (!empty($user['face_registered'])): ?>
                    <div class="avatar-face mx-auto mb-3 bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px;">
                        <i class="bi bi-shield-check display-4"></i>
                    </div>
                    <span class="badge bg-success px-3 py-2 rounded-pill fs-7 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Terdaftar & Terverifikasi
                    </span>
                    <p class="text-muted fs-8 mt-3 mb-0">
                        Embedding wajah biometrik 128 dimensi telah tersimpan di sistem untuk pencocokan presensi AI.
                    </p>
                <?php else: ?>
                    <div class="avatar-face mx-auto mb-3 bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px;">
                        <i class="bi bi-camera-fill display-4"></i>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-7 fw-semibold">
                        <i class="bi bi-exclamation-triangle me-1"></i> Belum Registrasi Wajah
                    </span>
                    <p class="text-muted fs-8 mt-3 mb-0">
                        Silakan lakukan pendaftaran foto wajah biometrik saat modul Face Recognition diaktifkan.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
