<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-calendar2-check-fill text-primary me-2"></i> Pemilihan Mata Kuliah Presensi</h4>
            <p class="text-muted mb-0 fs-7">
                Pilih mata kuliah yang memiliki jadwal aktif hari ini (<strong><?= htmlspecialchars($hari_ini) ?>, <?= format_date_id(date('Y-m-d')) ?></strong>) untuk memulai alur presensi.
            </p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 rounded-pill fs-7 fw-semibold shadow-sm">
                <i class="bi bi-clock-fill me-1"></i> <?= htmlspecialchars($hari_ini) ?>, <?= date('H:i') ?> WIB
            </span>
        </div>
    </div>
</div>

<!-- Informasi Alur Presensi Mahasiswa -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-primary-gradient text-white mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-white text-primary rounded-4 fs-2 shadow-sm d-none d-md-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
            <i class="bi bi-diagram-3-fill"></i>
        </div>
        <div>
            <h6 class="fw-bold text-white mb-1">Alur Presensi Cerdas STMIK IKMI Cirebon:</h6>
            <p class="mb-0 text-white-80 fs-7">
                <strong>1. Pilih Mata Kuliah</strong> $\rightarrow$ <strong>2. Cek Geolocation GPS</strong> $\rightarrow$ <strong>3. Verifikasi Wajah AI</strong> $\rightarrow$ <strong>4. Foto Kegiatan Kelas</strong> $\rightarrow$ <strong>5. Absensi Berhasil</strong>.
            </p>
        </div>
    </div>
</div>

<!-- SECTION 1: MATA KULIAH HARI INI (SIAP ABSENSI) -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-check2-circle text-success me-2"></i> Mata Kuliah Hari Ini (<?= htmlspecialchars($hari_ini) ?>)
        </h5>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fs-8">
            <?= count($today_schedules) ?> Mata Kuliah Terjadwal
        </span>
    </div>

    <?php if (empty($today_schedules)): ?>
        <div class="alert alert-light border rounded-3 text-center py-5 mb-0">
            <div class="p-3 bg-light rounded-circle d-inline-flex mb-3">
                <i class="bi bi-calendar-x text-muted fs-1"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">Tidak Ada Jadwal Kuliah untuk Anda Hari Ini (<?= htmlspecialchars($hari_ini) ?>)</h6>
            <p class="text-muted fs-7 mb-3">
                Sistem presensi hanya mengizinkan pengisian kehadiran pada mata kuliah yang memiliki jadwal aktif pada hari ini.
            </p>
            <a href="#jadwal-lainnya" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                <i class="bi bi-eye me-1"></i> Lihat Jadwal Hari Lainnya
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($today_schedules as $s): ?>
                <div class="col-lg-6">
                    <div class="card border-primary border-2 shadow-sm rounded-4 p-4 h-100 bg-white d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-primary font-monospace fs-8 me-1"><?= htmlspecialchars($s['kode_mk']) ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8"><?= (int)$s['sks'] ?> SKS</span>
                                <span class="badge bg-light text-dark border fs-8"><?= htmlspecialchars($s['nama_kelas']) ?></span>
                            </div>
                            <span class="badge bg-success px-2 py-1 rounded-pill fs-8">
                                <i class="bi bi-circle-fill fs-9 me-1"></i> Aktif Hari Ini
                            </span>
                        </div>

                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($s['nama_mk']) ?></h5>
                        
                        <div class="bg-light rounded-3 p-3 mb-3 fs-7 text-secondary">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-person-fill text-primary"></i>
                                <span>Dosen: <strong><?= htmlspecialchars($s['nama_dosen']) ?></strong></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-clock-fill text-primary"></i>
                                <span>Waktu: <strong><?= substr($s['jam_mulai'], 0, 5) ?> - <?= substr($s['jam_selesai'], 0, 5) ?> WIB</strong></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-danger"></i>
                                <span>Ruangan: <strong><?= htmlspecialchars($s['ruangan']) ?></strong></span>
                            </div>
                        </div>

                        <div class="mt-auto pt-2">
                            <?php if (!empty($s['absensi_id'])): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 bg-success-subtle text-success rounded-3">
                                    <div class="fs-8 fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Anda Sudah Absen Hari Ini
                                    </div>
                                    <a href="<?= base_url('mahasiswa/riwayat') ?>" class="btn btn-sm btn-success rounded-pill px-3 fs-8">
                                        Lihat Riwayat
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="<?= base_url('mahasiswa/absensi/proses/' . $s['id'] . ($hari_ini !== $real_day ? '?hari=' . urlencode($hari_ini) : '')) ?>" 
                                   class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-check-circle-fill"></i> Pilih Mata Kuliah & Mulai Absensi
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 2: JADWAL KULIAH HARI LAINNYA (BUKAN HARI INI) -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white" id="jadwal-lainnya">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-calendar3 me-2 text-secondary"></i> Jadwal Mata Kuliah Hari Lainnya (Semester Aktif)
        </h5>
        <span class="badge bg-light text-muted border">Informasi Jadwal Perkuliahan</span>
    </div>
    
    <p class="text-muted fs-7 mb-4">
        Mata kuliah di bawah ini tidak dapat dipilih untuk presensi saat ini karena terjadwal pada hari yang berbeda.
    </p>

    <!-- Nav Tabs Hari -->
    <ul class="nav nav-pills gap-2 mb-4 pb-2 border-bottom overflow-auto flex-nowrap" id="scheduleDaysTab" role="tablist">
        <?php foreach (get_indonesian_days() as $idx => $day): ?>
            <?php 
            $count = count($schedules_by_day[$day] ?? []);
            $isCurrent = ($day === $hari_ini);
            ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 fs-8 fw-semibold <?= $isCurrent ? 'active bg-primary' : 'text-secondary bg-light' ?>" 
                        id="tab-<?= strtolower($day) ?>" 
                        data-bs-toggle="pill" 
                        data-bs-target="#content-<?= strtolower($day) ?>" 
                        type="button" 
                        role="tab">
                    <?= $day ?> <span class="badge <?= $isCurrent ? 'bg-white text-primary' : 'bg-secondary text-white' ?> ms-1 rounded-pill"><?= $count ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="scheduleDaysTabContent">
        <?php foreach (get_indonesian_days() as $day): ?>
            <?php 
            $daySchedules = $schedules_by_day[$day] ?? [];
            $isCurrent = ($day === $hari_ini);
            ?>
            <div class="tab-pane fade <?= $isCurrent ? 'show active' : '' ?>" id="content-<?= strtolower($day) ?>" role="tabpanel">
                <?php if (empty($daySchedules)): ?>
                    <div class="alert alert-light border rounded-3 text-center py-4 mb-0">
                        <i class="bi bi-info-circle text-muted fs-3 d-block mb-2"></i>
                        <p class="text-muted fs-7 mb-0">Tidak ada jadwal mata kuliah pada hari <strong><?= $day ?></strong>.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($daySchedules as $ds): ?>
                            <div class="col-md-6">
                                <div class="card border rounded-3 p-3 bg-light h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($ds['kode_mk']) ?></span>
                                        <span class="badge bg-white text-dark border">
                                            <i class="bi bi-clock me-1"></i> <?= substr($ds['jam_mulai'], 0, 5) ?> - <?= substr($ds['jam_selesai'], 0, 5) ?> WIB
                                        </span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($ds['nama_mk']) ?></h6>
                                    <div class="fs-8 text-muted mb-1"><i class="bi bi-person me-1"></i> Dosen: <?= htmlspecialchars($ds['nama_dosen']) ?></div>
                                    <div class="fs-8 text-muted mb-3"><i class="bi bi-geo-alt me-1"></i> Ruangan: <?= htmlspecialchars($ds['ruangan']) ?> (<?= htmlspecialchars($ds['nama_kelas']) ?>)</div>
                                    
                                    <div class="mt-auto">
                                        <?php if ($isCurrent): ?>
                                            <a href="<?= base_url('mahasiswa/absensi/proses/' . $ds['id'] . ($hari_ini !== $real_day ? '?hari=' . urlencode($hari_ini) : '')) ?>" 
                                               class="btn btn-sm btn-primary rounded-pill px-3">
                                                <i class="bi bi-camera-video me-1"></i> Masuk Presensi
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary opacity-75 rounded-pill px-3" disabled>
                                                <i class="bi bi-lock-fill me-1"></i> Bukan Jadwal Hari Ini
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
