<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 fs-8">
                    <li class="breadcrumb-item"><a href="<?= base_url('dosen/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('dosen/kelas') ?>" class="text-decoration-none text-muted">Monitoring Kelas</a></li>
                    <li class="breadcrumb-item active text-primary fw-bold">Detail Kelas</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-journal-check text-primary me-2"></i> <?= htmlspecialchars($jadwal['nama_mk']) ?></h4>
            <p class="text-muted mb-0 fs-7">
                Kelas <?= htmlspecialchars($jadwal['nama_kelas']) ?> | <?= htmlspecialchars($jadwal['hari']) ?>, <?= date('H:i', strtotime($jadwal['jam_mulai'])) ?> - <?= date('H:i', strtotime($jadwal['jam_selesai'])) ?> WIB
            </p>
        </div>
        <div>
            <a href="<?= base_url('dosen/kelas') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<!-- Navigasi Tabs -->
<ul class="nav nav-pills mb-4 gap-2" id="kelasTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-4 fw-semibold" id="rekap-tab" data-bs-toggle="pill" data-bs-target="#rekap" type="button" role="tab">
            <i class="bi bi-people-fill me-1"></i> Rekap Kehadiran Mahasiswa
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-4 fw-semibold" id="pertemuan-tab" data-bs-toggle="pill" data-bs-target="#pertemuan" type="button" role="tab">
            <i class="bi bi-calendar3 me-1"></i> Sesi Pertemuan
        </button>
    </li>
</ul>

<div class="tab-content" id="kelasTabContent">
    <!-- Tab Rekap -->
    <div class="tab-pane fade show active" id="rekap" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-primary me-2"></i> Persentase Kehadiran</h5>
                <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-8 text-uppercase">
                        <tr>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th class="text-center">Hadir / Terlambat</th>
                            <th class="text-center">Izin / Sakit</th>
                            <th class="text-center">Alpha</th>
                            <th class="text-center">Total Sesi</th>
                            <th class="text-center">Persentase</th>
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        <?php if (empty($rekap)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada data mahasiswa / absensi pada kelas ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($rekap as $r): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td class="font-monospace text-muted"><?= htmlspecialchars($r['nim']) ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($r['nama_mahasiswa']) ?></td>
                                    <td class="text-center text-success fw-bold"><?= (int)$r['total_hadir'] + (int)$r['total_terlambat'] ?></td>
                                    <td class="text-center text-info fw-bold"><?= (int)$r['total_izin'] + (int)$r['total_sakit'] ?></td>
                                    <td class="text-center text-danger fw-bold"><?= (int)$r['total_alpha'] ?></td>
                                    <td class="text-center"><?= (int)$r['total_pertemuan_selesai'] ?></td>
                                    <td class="text-center">
                                        <?php 
                                            $pct = (float)$r['persentase_kehadiran'];
                                            $pctClass = $pct >= ($min_attendance ?? 75.0) ? 'bg-success' : 'bg-danger';
                                        ?>
                                        <span class="badge <?= $pctClass ?> rounded-pill px-3 py-2 fs-8">
                                            <?= number_format($pct, 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted fs-8">
                * Persentase dihitung dari total pertemuan yang telah ditutup/selesai.<br>
                * Sesuai ketentuan, Dosen <strong>tidak melakukan absensi</strong>. Presensi dilakukan mandiri oleh mahasiswa melalui GPS dan AI Face Verification.
            </div>
        </div>
    </div>

    <!-- Tab Pertemuan -->
    <div class="tab-pane fade" id="pertemuan" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-calendar-check text-primary me-2"></i> Daftar Sesi Pertemuan</h5>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-8 text-uppercase">
                        <tr>
                            <th class="text-center">Pertemuan Ke-</th>
                            <th>Tanggal</th>
                            <th>Materi Pembahasan</th>
                            <th class="text-center">Total Absensi</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        <?php if (empty($pertemuan)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada sesi pertemuan yang tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pertemuan as $p): ?>
                                <tr>
                                    <td class="text-center fw-bold">Sesi <?= $p['pertemuan_ke'] ?></td>
                                    <td><?= date('d F Y', strtotime($p['tanggal'])) ?></td>
                                    <td>
                                        <?php if (!empty($p['materi_pembahasan'])): ?>
                                            <?= htmlspecialchars($p['materi_pembahasan']) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Belum ada materi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1">
                                            <?= (int)$p['total_absensi'] ?> Mahasiswa
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $statusBadge = match($p['status']) {
                                                'dibuka' => 'bg-success',
                                                'ditutup' => 'bg-secondary',
                                                'dibatalkan' => 'bg-danger',
                                                default => 'bg-light text-dark border'
                                            };
                                        ?>
                                        <span class="badge <?= $statusBadge ?> px-3 py-1 text-uppercase fs-8 fw-semibold rounded-pill">
                                            <?= htmlspecialchars($p['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
