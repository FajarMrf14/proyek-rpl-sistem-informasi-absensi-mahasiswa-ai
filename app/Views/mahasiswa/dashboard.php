<!-- 1. Header Identitas Mahasiswa -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-lg bg-primary-gradient text-white rounded-4 d-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm" style="width: 64px; height: 64px;">
                <?= strtoupper(substr($user['nama_lengkap'] ?? $user['username'] ?? 'M', 0, 1)) ?>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user['nama_lengkap'] ?? $user['username']) ?></h4>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fs-8 fw-semibold">
                        Mahasiswa Aktif
                    </span>
                </div>
                <div class="text-muted fs-7">
                    <span>NIM: <strong class="text-dark font-monospace"><?= htmlspecialchars($user['nim'] ?? $user['username']) ?></strong></span>
                    <span class="mx-2 text-black-50">•</span>
                    <span>Prodi: <strong class="text-dark"><?= htmlspecialchars($user['prodi'] ?? 'Teknik Informatika') ?></strong></span>
                    <span class="mx-2 text-black-50">•</span>
                    <span>Angkatan: <strong class="text-dark"><?= htmlspecialchars($user['angkatan'] ?? '2023') ?></strong></span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('mahasiswa/absensi') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-camera-video-fill"></i> Masuk Presensi
            </a>
        </div>
    </div>
</div>

<!-- 2. Hero Card: Persentase Kehadiran & Motivator Kehadiran -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 overflow-hidden position-relative">
    <div class="row align-items-center g-4">
        <!-- Kolom Nilai Persentase Besar -->
        <div class="col-lg-4 col-md-5 text-center text-md-start border-end-md">
            <div class="text-muted fs-8 text-uppercase fw-bold tracking-wide mb-1">Tingkat Kehadiran Kumulatif</div>
            <div class="d-flex align-items-baseline justify-content-center justify-content-md-start gap-2 my-1">
                <span class="display-3 fw-bold" style="color: <?= $motivation['color'] ?>;">
                    <?= number_format($stats['persentase_kehadiran'], 1) ?>%
                </span>
            </div>
            <div class="mt-2">
                <span class="badge <?= $motivation['badge'] ?> px-3 py-2 rounded-pill fs-7 fw-semibold">
                    <i class="bi <?= $motivation['icon'] ?> me-1"></i> <?= $motivation['status'] ?>
                </span>
            </div>
        </div>

        <!-- Kolom Progress Bar & Pesan Motivasi -->
        <div class="col-lg-8 col-md-7">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1 fs-7 fw-semibold">
                    <span>Progress Kehadiran Mahasiswa</span>
                    <span class="text-muted">Target Minimal: <strong><?= number_format((float)($min_attendance ?? 75.0), 1) ?>%</strong></span>
                </div>
                <div class="progress rounded-pill bg-light border" style="height: 18px;">
                    <div class="progress-bar rounded-pill <?= $stats['persentase_kehadiran'] >= 85 ? 'bg-success' : ($stats['persentase_kehadiran'] >= ($min_attendance ?? 75.0) ? 'bg-warning' : 'bg-danger') ?> progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: <?= min(100, max(5, $stats['persentase_kehadiran'])) ?>%;" 
                         aria-valuenow="<?= $stats['persentase_kehadiran'] ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
            </div>
            <div class="p-3 bg-light rounded-3 border-start border-4" style="border-color: <?= $motivation['color'] ?> !important;">
                <p class="mb-0 fs-7 text-secondary">
                    <i class="bi bi-info-circle-fill me-1" style="color: <?= $motivation['color'] ?>;"></i>
                    <?= $motivation['text'] ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- 3. Rincian Statistik Kehadiran (Hadir, Izin, Sakit, Alpa) -->
<div class="row g-3 mb-4">
    <!-- Jumlah Kehadiran -->
    <div class="col-xl-3 col-md-6 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-success border-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted fs-8 fw-semibold text-uppercase">Jumlah Kehadiran</div>
                    <div class="fs-2 fw-bold text-success my-1"><?= $stats['total_kehadiran'] ?></div>
                    <div class="fs-8 text-muted">Hadir Tepat Waktu / Terlambat</div>
                </div>
                <div class="p-2 bg-success-subtle text-success rounded-3">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Jumlah Izin -->
    <div class="col-xl-3 col-md-6 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-info border-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted fs-8 fw-semibold text-uppercase">Jumlah Izin</div>
                    <div class="fs-2 fw-bold text-info my-1"><?= $stats['izin'] ?></div>
                    <div class="fs-8 text-muted">Dengan Surat Tugas / Izin</div>
                </div>
                <div class="p-2 bg-info-subtle text-info rounded-3">
                    <i class="bi bi-file-earmark-text-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Jumlah Sakit -->
    <div class="col-xl-3 col-md-6 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-warning border-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted fs-8 fw-semibold text-uppercase">Jumlah Sakit</div>
                    <div class="fs-2 fw-bold text-warning my-1"><?= $stats['sakit'] ?></div>
                    <div class="fs-8 text-muted">Dengan Surat Keterangan Dokter</div>
                </div>
                <div class="p-2 bg-warning-subtle text-warning rounded-3">
                    <i class="bi bi-heart-pulse-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Jumlah Alpa -->
    <div class="col-xl-3 col-md-6 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-danger border-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted fs-8 fw-semibold text-uppercase">Jumlah Alpa</div>
                    <div class="fs-2 fw-bold text-danger my-1"><?= $stats['alpha'] ?></div>
                    <div class="fs-8 text-muted">Tidak Hadir Tanpa Keterangan</div>
                </div>
                <div class="p-2 bg-danger-subtle text-danger rounded-3">
                    <i class="bi bi-x-circle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Rincian Kehadiran per Mata Kuliah -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-journal-check me-2 text-primary"></i> Rekapitulasi Presensi per Mata Kuliah
        </h5>
        <a href="<?= base_url('mahasiswa/riwayat') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fs-8">
            Lihat Riwayat Lengkap <i class="bi bi-chevron-right ms-1"></i>
        </a>
    </div>

    <?php if (empty($stats['mk_summaries'])): ?>
        <div class="alert alert-light border rounded-3 text-center py-4 mb-0">
            <i class="bi bi-info-circle text-muted fs-3 d-block mb-2"></i>
            <p class="text-muted fs-7 mb-0">Belum ada mata kuliah yang terdaftar di sistem pada semester aktif ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-8 text-uppercase">
                    <tr>
                        <th>Kode & Nama Mata Kuliah</th>
                        <th>Kelas</th>
                        <th>Dosen Pengampu</th>
                        <th class="text-center">Sesi Terlaksana</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Izin / Sakit</th>
                        <th class="text-center">Alpa</th>
                        <th class="text-center">Persentase</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($stats['mk_summaries'] as $mk): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark font-monospace border me-1"><?= htmlspecialchars($mk['kode_mk']) ?></span>
                                <strong><?= htmlspecialchars($mk['nama_mk']) ?></strong>
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($mk['nama_kelas']) ?></span></td>
                            <td><?= htmlspecialchars($mk['nama_dosen']) ?></td>
                            <td class="text-center fw-semibold"><?= (int)$mk['total_pertemuan_selesai'] ?> Pertemuan</td>
                            <td class="text-center text-success fw-bold"><?= (int)$mk['total_hadir'] + (int)$mk['total_terlambat'] ?></td>
                            <td class="text-center text-info"><?= (int)$mk['total_izin'] + (int)$mk['total_sakit'] ?></td>
                            <td class="text-center text-danger"><?= (int)$mk['total_alpha'] ?></td>
                            <td class="text-center">
                                <?php 
                                $mkPct = (float)$mk['persentase_kehadiran'];
                                $badgeClass = $mkPct >= 85 ? 'bg-success' : ($mkPct >= ($min_attendance ?? 75.0) ? 'bg-warning text-dark' : 'bg-danger');
                                ?>
                                <span class="badge <?= $badgeClass ?> px-3 py-1 rounded-pill fw-semibold">
                                    <?= number_format($mkPct, 1) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 5. Jadwal Perkuliahan Hari Ini -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-calendar2-day-fill me-2 text-primary"></i> Jadwal Kuliah Hari Ini (<?= htmlspecialchars($hari_ini) ?>)
        </h5>
        <span class="badge bg-light text-secondary border"><?= format_date_id(date('Y-m-d')) ?></span>
    </div>

    <?php if (empty($today_schedules)): ?>
        <div class="alert alert-light border rounded-3 text-center py-4 mb-0">
            <i class="bi bi-calendar2-check text-muted fs-3 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">Tidak Ada Jadwal Kuliah Hari Ini</h6>
            <p class="text-muted fs-8 mb-0">Anda tidak memiliki jadwal perkuliahan pada hari <?= htmlspecialchars($hari_ini) ?>.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($today_schedules as $j): ?>
                <div class="col-md-6">
                    <div class="card border rounded-3 p-3 bg-light h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary font-monospace"><?= htmlspecialchars($j['kode_mk']) ?></span>
                            <span class="badge bg-white text-dark border fw-semibold">
                                <i class="bi bi-clock me-1"></i> <?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?> WIB
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($j['nama_mk']) ?></h6>
                        <div class="fs-8 text-muted mb-1"><i class="bi bi-person me-1"></i> Dosen: <?= htmlspecialchars($j['nama_dosen']) ?></div>
                        <div class="fs-8 text-muted mb-3"><i class="bi bi-geo-alt me-1"></i> Ruangan: <strong><?= htmlspecialchars($j['ruangan']) ?></strong></div>
                        <div class="mt-auto">
                            <a href="<?= base_url('mahasiswa/absensi') ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                <i class="bi bi-camera-video me-1"></i> Buka Presensi
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
