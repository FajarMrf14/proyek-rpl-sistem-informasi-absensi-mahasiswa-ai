<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i> Manajemen Izin & Sakit</h4>
            <p class="text-muted mb-0 fs-7">Verifikasi dan kelola pengajuan izin serta surat keterangan sakit mahasiswa.</p>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <form method="GET" action="<?= base_url('admin/izin') ?>" class="row g-3 align-items-end mb-4">
        <div class="col-md-3">
            <label class="form-label fs-8 text-muted fw-semibold">Status Persetujuan</label>
            <select name="status" class="form-select fs-7">
                <option value="">Semua Status</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Menunggu (Pending)</option>
                <option value="disetujui" <?= ($filters['status'] ?? '') === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= ($filters['status'] ?? '') === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8 text-muted fw-semibold">Jenis Pengajuan</label>
            <select name="tipe" class="form-select fs-7">
                <option value="">Semua Jenis</option>
                <option value="izin" <?= ($filters['tipe'] ?? '') === 'izin' ? 'selected' : '' ?>>Izin</option>
                <option value="sakit" <?= ($filters['tipe'] ?? '') === 'sakit' ? 'selected' : '' ?>>Sakit</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fs-8 text-muted fw-semibold">Tanggal (Opsional)</label>
            <input type="date" name="tanggal" class="form-control fs-7" value="<?= htmlspecialchars($filters['tanggal'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold shadow-sm"><i class="bi bi-funnel"></i> Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-8 text-uppercase">
                <tr>
                    <th>Tgl Pengajuan</th>
                    <th>Mahasiswa</th>
                    <th>Mata Kuliah</th>
                    <th>Tipe & Alasan</th>
                    <th>Dokumen</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-7">
                <?php if (empty($leaves)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">Tidak ada data pengajuan yang ditemukan.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($leaves as $l): ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($l['created_at'])) ?></strong><br>
                            <span class="text-muted fs-8">Sesi: <?= date('d/m/Y', strtotime($l['tanggal_mulai'])) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($l['nama_mahasiswa']) ?></strong><br>
                            <span class="text-muted fs-8">NIM: <?= htmlspecialchars($l['nim']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($l['nama_mk']) ?></strong><br>
                            <span class="text-muted fs-8">Dosen: <?= htmlspecialchars($l['nama_dosen']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $l['tipe'] === 'sakit' ? 'bg-warning text-dark' : 'bg-info' ?> text-uppercase mb-1">
                                <?= htmlspecialchars($l['tipe']) ?>
                            </span>
                            <div class="text-dark fs-7" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($l['alasan']) ?>">
                                <?= htmlspecialchars($l['alasan']) ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($l['bukti_surat'])): ?>
                                <a href="<?= base_url(htmlspecialchars($l['bukti_surat'])) ?>" target="_blank" class="badge bg-light text-dark border text-decoration-none">
                                    <i class="bi bi-paperclip me-1"></i> Buka Surat
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><i class="bi bi-dash"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $statusBadge = match($l['status_persetujuan']) {
                                'disetujui' => 'bg-success',
                                'ditolak'   => 'bg-danger',
                                default     => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?> px-3 py-1 rounded-pill text-uppercase fs-8 fw-semibold">
                                <?= htmlspecialchars($l['status_persetujuan']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalProses<?= $l['id'] ?>">
                                Detail
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Proses -->
                    <div class="modal fade" id="modalProses<?= $l['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-4 border-0 shadow">
                                <form action="<?= base_url('admin/izin/proses/' . $l['id']) ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <div class="modal-header border-bottom-0 pb-0">
                                        <h5 class="modal-title fw-bold">Detail Pengajuan</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul class="list-group list-group-flush fs-7 mb-3">
                                            <li class="list-group-item px-0"><strong>Mahasiswa:</strong> <?= htmlspecialchars($l['nama_mahasiswa']) ?> (<?= htmlspecialchars($l['nim']) ?>)</li>
                                            <li class="list-group-item px-0"><strong>Mata Kuliah:</strong> <?= htmlspecialchars($l['nama_mk']) ?></li>
                                            <li class="list-group-item px-0"><strong>Tipe:</strong> <?= strtoupper(htmlspecialchars($l['tipe'])) ?></li>
                                            <li class="list-group-item px-0"><strong>Tanggal Berlaku:</strong> <?= date('d/m/Y', strtotime($l['tanggal_mulai'])) ?></li>
                                            <li class="list-group-item px-0"><strong>Alasan:</strong><br><?= nl2br(htmlspecialchars($l['alasan'])) ?></li>
                                            <?php if (!empty($l['bukti_surat'])): ?>
                                            <li class="list-group-item px-0">
                                                <strong>Bukti Surat:</strong><br>
                                                <a href="<?= base_url(htmlspecialchars($l['bukti_surat'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-1"><i class="bi bi-box-arrow-up-right me-1"></i> Lihat Dokumen Lengkap</a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                        
                                        <?php if ($l['status_persetujuan'] === 'pending'): ?>
                                            <div class="mb-3">
                                                <label class="form-label fs-8 text-muted fw-semibold mb-1">Keputusan</label>
                                                <select name="status_persetujuan" class="form-select fs-7" required>
                                                    <option value="">-- Pilih --</option>
                                                    <option value="disetujui">Setujui</option>
                                                    <option value="ditolak">Tolak</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label fs-8 text-muted fw-semibold mb-1">Catatan (Opsional)</label>
                                                <textarea name="catatan_dosen" class="form-control fs-7" rows="2" placeholder="Catatan untuk mahasiswa..."></textarea>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info py-2 fs-7 mb-0">
                                                <strong>Status Saat Ini:</strong> <?= strtoupper($l['status_persetujuan']) ?><br>
                                                <strong>Catatan:</strong> <?= htmlspecialchars($l['catatan_dosen'] ?? '-') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer border-top-0 pt-0">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                                        <?php if ($l['status_persetujuan'] === 'pending'): ?>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Simpan Keputusan</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
