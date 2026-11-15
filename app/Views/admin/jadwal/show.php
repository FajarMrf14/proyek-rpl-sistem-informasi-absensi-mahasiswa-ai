<?php
$isActive = (int) $schedule['is_active'] === 1;
$taActive = (int) ($schedule['ta_is_active'] ?? 0) === 1;
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h4 class="fw-bold text-dark mb-0">
                    <i class="bi bi-calendar2-week-fill text-primary me-2"></i> Detail Jadwal Kuliah
                </h4>
                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-3 py-1 text-uppercase fs-8 fw-semibold">
                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                </span>
                <?php if (!$taActive): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1 fs-8">Tahun Akademik Nonaktif</span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0 fs-7">
                <?= htmlspecialchars($schedule['hari']) ?>,
                <?= htmlspecialchars(substr((string) $schedule['jam_mulai'], 0, 5)) ?>&ndash;<?= htmlspecialchars(substr((string) $schedule['jam_selesai'], 0, 5)) ?> WIB
                &middot; <?= htmlspecialchars($schedule['nama_mk']) ?> &middot; <?= htmlspecialchars($schedule['nama_kelas']) ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('admin/jadwal') ?>" class="btn btn-light border rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="<?= base_url('admin/jadwal/' . (int) $schedule['id'] . '/edit') ?>" class="btn btn-primary rounded-pill px-3">
                <i class="bi bi-pencil-square me-1"></i> Edit Jadwal
            </a>
            <form action="<?= base_url('admin/jadwal/' . (int) $schedule['id'] . '/toggle') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill px-3">
                    <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                    <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-top border-primary border-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Mata Kuliah</div>
            <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($schedule['nama_mk']) ?></div>
            <div class="fs-8 text-muted mt-1">
                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($schedule['kode_mk']) ?></span>
                <?= (int) $schedule['sks'] ?> SKS &middot; Semester paket <?= (int) $schedule['semester_paket'] ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-top border-success border-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Dosen Pengampu</div>
            <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($schedule['nama_dosen']) ?></div>
            <div class="fs-8 text-muted font-monospace mt-1">NIDN: <?= htmlspecialchars($schedule['nidn']) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-top border-info border-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Kelas</div>
            <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($schedule['nama_kelas']) ?></div>
            <div class="fs-8 text-muted mt-1"><?= htmlspecialchars($schedule['prodi']) ?> &middot; <?= htmlspecialchars((string) $schedule['angkatan']) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-top border-warning border-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Ruangan</div>
            <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($schedule['ruangan']) ?></div>
            <div class="fs-8 text-muted mt-1">Toleransi terlambat <?= (int) $schedule['toleransi_keterlambatan'] ?> menit</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle-fill text-primary me-1"></i> Informasi Periode</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Tahun Akademik</div>
            <div class="fw-semibold text-dark"><?= htmlspecialchars($schedule['nama_tahun']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Semester</div>
            <div class="fw-semibold text-dark"><?= htmlspecialchars(ucfirst((string) $schedule['semester'])) ?></div>
            <div class="fs-8 text-muted">Mengikuti periode tahun akademik.</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted fs-8 fw-semibold text-uppercase">Dibuat / Diperbarui</div>
            <div class="fs-8 text-dark"><?= htmlspecialchars((string) ($schedule['created_at'] ?? '-')) ?></div>
            <div class="fs-8 text-muted"><?= htmlspecialchars((string) ($schedule['updated_at'] ?? '-')) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
            <div class="fs-2 fw-bold text-primary"><?= (int) ($relatedCounts['peserta'] ?? 0) ?></div>
            <div class="fs-8 text-muted text-uppercase fw-semibold">Peserta / KRS</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
            <div class="fs-2 fw-bold text-success"><?= (int) ($relatedCounts['pertemuan'] ?? 0) ?></div>
            <div class="fs-8 text-muted text-uppercase fw-semibold">Pertemuan</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
            <div class="fs-2 fw-bold text-info"><?= (int) ($relatedCounts['absensi'] ?? 0) ?></div>
            <div class="fs-8 text-muted text-uppercase fw-semibold">Absensi</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
            <div class="fs-2 fw-bold text-warning"><?= (int) ($relatedCounts['izin'] ?? 0) ?></div>
            <div class="fs-8 text-muted text-uppercase fw-semibold">Izin / Sakit</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
    <div class="p-4 pb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-1"></i> Peserta Terdaftar</h6>
            <p class="text-muted fs-8 mb-0">Data berasal dari tabel KRS <span class="font-monospace">kelas_mahasiswa</span>, bukan tabel absensi baru.</p>
        </div>
        <span class="badge bg-light text-dark border rounded-pill px-3 py-2"><?= count($participants) ?> Mahasiswa</span>
    </div>

    <?php if (empty($participants)): ?>
        <div class="px-4 pb-4">
            <div class="alert alert-light border rounded-3 text-center py-4 mb-0">
                <i class="bi bi-person-plus text-muted fs-2 d-block mb-2"></i>
                <h6 class="fw-bold text-dark mb-1">Belum Ada Peserta</h6>
                <p class="text-muted fs-7 mb-0">
                    Jadwal belum muncul di dashboard mahasiswa sampai KRS peserta tersedia.
                    Gunakan opsi salin peserta dari jadwal lain pada kelas yang sama saat membuat jadwal.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-8 text-uppercase">
                    <tr>
                        <th class="ps-4">NIM</th>
                        <th>Nama Mahasiswa</th>
                        <th>Prodi / Angkatan</th>
                        <th>Status Akademik</th>
                        <th class="pe-4 text-center">Status KRS</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($participants as $p): ?>
                        <tr>
                            <td class="ps-4 font-monospace fw-semibold"><?= htmlspecialchars($p['nim']) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['prodi']) ?> &middot; <?= htmlspecialchars((string) $p['angkatan']) ?></td>
                            <td>
                                <span class="badge <?= $p['status_akademik'] === 'aktif' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> text-uppercase fs-8">
                                    <?= htmlspecialchars($p['status_akademik']) ?>
                                </span>
                            </td>
                            <td class="pe-4 text-center">
                                <span class="badge <?= $p['status'] === 'aktif' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?> text-uppercase fs-8">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="fs-8 text-muted">
            <i class="bi bi-shield-check me-1"></i>
            Penghapusan permanen hanya dilakukan jika belum ada peserta, pertemuan, absensi, atau izin.
            Jika sudah ada riwayat, jadwal akan dinonaktifkan.
        </div>
        <form action="<?= base_url('admin/jadwal/' . (int) $schedule['id'] . '/delete') ?>" method="POST"
              onsubmit="return confirm('Hapus jadwal ini? Jika sudah memiliki peserta atau riwayat, jadwal akan dinonaktifkan agar riwayat tetap aman.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger rounded-pill px-3">
                <i class="bi bi-trash me-1"></i> Hapus / Nonaktifkan
            </button>
        </form>
    </div>
</div>
