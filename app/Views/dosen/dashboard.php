<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Dashboard Dosen Pengampu</h4>
            <p class="text-muted mb-0 fs-7">
                Selamat datang, <strong><?= htmlspecialchars($user['nama_dengan_gelar'] ?? $user['nama_lengkap'] ?? $user['username']) ?></strong> 
                (NIDN: <span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($user['nidn'] ?? '-') ?></span>)
            </p>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fs-7 fw-semibold">
                <i class="bi bi-person-workspace me-1"></i> Dosen Pengampu Aktif
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-primary border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Total Kelas Diampu</div>
            <div class="fs-2 fw-bold text-primary my-1"><?= $stats['total_kelas'] ?></div>
            <div class="fs-8 text-muted">Jadwal Kelas Semester Ini</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-success border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Jadwal Hari Ini (<?= htmlspecialchars($hari_ini) ?>)</div>
            <div class="fs-2 fw-bold text-success my-1"><?= $stats['jadwal_hari_ini'] ?></div>
            <div class="fs-8 text-muted"><?= format_date_id(date('Y-m-d')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-top border-warning border-4 h-100">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Pengajuan Izin Pending</div>
            <div class="fs-2 fw-bold text-warning my-1"><?= $stats['izin_pending'] ?></div>
            <div class="fs-8 text-muted">Menunggu Verifikasi Dosen</div>
        </div>
    </div>
</div>

<!-- Jadwal Mengajar Hari Ini -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-easel-fill me-2 text-primary"></i> Jadwal Mengajar Hari Ini</h5>
        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($hari_ini) ?>, <?= date('d/m/Y') ?></span>
    </div>

    <?php if (empty($today_classes)): ?>
        <div class="alert alert-light border rounded-3 text-center py-4 mb-0">
            <i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">Tidak Ada Jadwal Mengajar Hari Ini</h6>
            <p class="text-muted fs-8 mb-0">Anda tidak memiliki agenda perkuliahan aktif pada hari ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-8 text-uppercase">
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>Kelas</th>
                        <th>Jam</th>
                        <th>Ruangan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($today_classes as $tc): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary-subtle text-primary font-monospace me-1"><?= htmlspecialchars($tc['kode_mk']) ?></span>
                                <strong><?= htmlspecialchars($tc['nama_mk']) ?></strong> (<?= $tc['sks'] ?> SKS)
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($tc['nama_kelas']) ?></span></td>
                            <td><?= substr($tc['jam_mulai'], 0, 5) ?> - <?= substr($tc['jam_selesai'], 0, 5) ?> WIB</td>
                            <td><?= htmlspecialchars($tc['ruangan']) ?></td>
                            <td class="text-center">
                                <a href="<?= base_url('dosen/kelas/' . (int) $tc['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                    <i class="bi bi-people me-1"></i> Lihat Kelas
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
