<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-calendar3-week-fill text-primary me-2"></i> Manajemen Jadwal Kuliah
            </h4>
            <p class="text-muted mb-0 fs-7">
                Kelola jadwal Senin&ndash;Sabtu, dosen pengampu, kelas, ruangan, dan tahun akademik.
                Semester mengikuti periode tahun akademik yang dipilih.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fs-7 fw-semibold">
                <i class="bi bi-calendar-check me-1"></i> <?= count($schedules) ?> Jadwal Ditampilkan
            </span>
            <a href="<?= base_url('admin/jadwal/create') ?>" class="btn btn-primary rounded-pill px-3 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <form method="GET" action="<?= base_url('admin/jadwal') ?>" class="row g-3 align-items-end">
        <div class="col-md-4 col-lg-2">
            <label class="form-label fs-8 text-muted fw-semibold">Hari</label>
            <select name="hari" class="form-select fs-7">
                <option value="">Semua Hari</option>
                <?php foreach ($days as $day): ?>
                    <option value="<?= htmlspecialchars($day) ?>" <?= ($filters['hari'] ?? '') === $day ? 'selected' : '' ?>>
                        <?= htmlspecialchars($day) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label fs-8 text-muted fw-semibold">Kelas</label>
            <select name="kelas_id" class="form-select fs-7">
                <option value="">Semua Kelas</option>
                <?php foreach ($options['kelas'] as $kelas): ?>
                    <option value="<?= (int) $kelas['id'] ?>" <?= (int) ($filters['kelas_id'] ?? 0) === (int) $kelas['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label fs-8 text-muted fw-semibold">Dosen</label>
            <select name="dosen_id" class="form-select fs-7">
                <option value="">Semua Dosen</option>
                <?php foreach ($options['dosen'] as $dosen): ?>
                    <option value="<?= (int) $dosen['id'] ?>" <?= (int) ($filters['dosen_id'] ?? 0) === (int) $dosen['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dosen['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label fs-8 text-muted fw-semibold">Mata Kuliah</label>
            <select name="mata_kuliah_id" class="form-select fs-7">
                <option value="">Semua Mata Kuliah</option>
                <?php foreach ($options['mata_kuliah'] as $mk): ?>
                    <option value="<?= (int) $mk['id'] ?>" <?= (int) ($filters['mata_kuliah_id'] ?? 0) === (int) $mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode_mk'] . ' - ' . $mk['nama_mk']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label fs-8 text-muted fw-semibold">Tahun Akademik</label>
            <select name="tahun_akademik_id" class="form-select fs-7">
                <option value="">Semua Tahun</option>
                <?php foreach ($options['tahun_akademik'] as $ta): ?>
                    <option value="<?= (int) $ta['id'] ?>" <?= (int) ($filters['tahun_akademik_id'] ?? 0) === (int) $ta['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ta['nama_tahun'] . ' - ' . ucfirst($ta['semester'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 col-lg-1">
            <label class="form-label fs-8 text-muted fw-semibold">Status</label>
            <select name="status" class="form-select fs-7">
                <option value="">Semua</option>
                <option value="aktif" <?= ($filters['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= ($filters['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>

        <div class="col-md-4 col-lg-1 d-grid">
            <button type="submit" class="btn btn-primary rounded-pill fw-semibold">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
        <small class="text-muted fs-8">
            <i class="bi bi-info-circle me-1"></i>
            Hapus akan menonaktifkan jadwal jika sudah memiliki peserta, pertemuan, absensi, atau izin.
        </small>
        <a href="<?= base_url('admin/jadwal') ?>" class="btn btn-sm btn-light border rounded-pill px-3 fs-8">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 text-secondary text-uppercase fs-8">Hari &amp; Jam</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Mata Kuliah</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Kelas</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Dosen</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Ruangan</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Tahun / Semester</th>
                    <th class="py-3 text-center text-secondary text-uppercase fs-8">Peserta</th>
                    <th class="py-3 text-center text-secondary text-uppercase fs-8">Status</th>
                    <th class="pe-4 py-3 text-end text-secondary text-uppercase fs-8">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-muted"></i>
                            <h6 class="fw-bold text-dark mb-1">Belum Ada Jadwal</h6>
                            <p class="fs-7 mb-0">Tidak ada jadwal yang sesuai dengan filter. Coba reset filter atau tambah jadwal baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $s): ?>
                        <?php
                        $isActive = (int) $s['is_active'] === 1;
                        $taActive = (int) ($s['ta_is_active'] ?? 0) === 1;
                        ?>
                        <tr class="<?= $isActive ? '' : 'table-light text-muted' ?>">
                            <td class="ps-4">
                                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= htmlspecialchars($s['hari']) ?></span>
                                <div class="fs-8 text-muted mt-1">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= htmlspecialchars(substr((string) $s['jam_mulai'], 0, 5)) ?> &ndash;
                                    <?= htmlspecialchars(substr((string) $s['jam_selesai'], 0, 5)) ?> WIB
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace me-1"><?= htmlspecialchars($s['kode_mk']) ?></span>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($s['nama_mk']) ?></div>
                                <div class="fs-8 text-muted"><?= (int) $s['sks'] ?> SKS &middot; Semester paket <?= (int) $s['semester_paket'] ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($s['nama_kelas']) ?></div>
                                <div class="fs-8 text-muted"><?= htmlspecialchars($s['prodi']) ?> &middot; <?= htmlspecialchars((string) $s['angkatan']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($s['nama_dosen']) ?></div>
                                <div class="fs-8 text-muted font-monospace"><?= htmlspecialchars($s['nidn']) ?></div>
                            </td>
                            <td class="fs-7 text-dark"><?= htmlspecialchars($s['ruangan']) ?></td>
                            <td>
                                <div class="fs-7 text-dark"><?= htmlspecialchars($s['nama_tahun']) ?></div>
                                <div class="fs-8 text-muted">
                                    Semester <?= htmlspecialchars(ucfirst((string) $s['semester'])) ?>
                                    <?php if (!$taActive): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis ms-1">TA Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border rounded-pill px-2">
                                    <i class="bi bi-people me-1"></i><?= (int) $s['total_peserta'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-2 py-1 text-uppercase fs-8 fw-semibold">
                                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= base_url('admin/jadwal/' . (int) $s['id']) ?>"
                                       class="btn btn-sm btn-outline-secondary rounded-pill px-2"
                                       title="Lihat detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('admin/jadwal/' . (int) $s['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary rounded-pill px-2"
                                       title="Edit jadwal">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="<?= base_url('admin/jadwal/' . (int) $s['id'] . '/toggle') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill px-2"
                                                title="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                        </button>
                                    </form>
                                    <form action="<?= base_url('admin/jadwal/' . (int) $s['id'] . '/delete') ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus jadwal ini? Jika sudah memiliki peserta atau riwayat absensi, jadwal akan dinonaktifkan agar riwayat tetap aman.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Hapus / nonaktifkan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
