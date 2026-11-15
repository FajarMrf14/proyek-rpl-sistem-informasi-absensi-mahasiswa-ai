<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i> Pengajuan Izin & Surat Sakit</h4>
            <p class="text-muted mb-0 fs-7">Kelola riwayat permohonan izin atau surat keterangan sakit perkuliahan Anda.</p>
        </div>
        <div>
            <button class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalIzinSakit">
                <i class="bi bi-plus-circle me-1"></i> Ajukan Izin / Sakit
            </button>
        </div>
    </div>
</div>

<!-- Modal Pengajuan Izin/Sakit -->
<div class="modal fade" id="modalIzinSakit" tabindex="-1" aria-labelledby="modalIzinSakitLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="<?= base_url('mahasiswa/izin/submit') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalIzinSakitLabel">Formulir Izin / Sakit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="jadwal_id" class="form-label fs-8 text-muted fw-semibold mb-1">Mata Kuliah</label>
                        <select class="form-select fs-7" id="jadwal_id" name="jadwal_id" required>
                            <option value="">-- Pilih Mata Kuliah --</option>
                            <?php foreach ($schedules ?? [] as $s): ?>
                                <option value="<?= htmlspecialchars($s['id']) ?>">
                                    <?= htmlspecialchars($s['nama_mk'] . ' - ' . $s['hari']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tipe" class="form-label fs-8 text-muted fw-semibold mb-1">Jenis Pengajuan</label>
                            <select class="form-select fs-7" id="tipe" name="tipe" required>
                                <option value="izin">Izin</option>
                                <option value="sakit">Sakit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label fs-8 text-muted fw-semibold mb-1">Tanggal Berlaku</label>
                            <input type="date" class="form-control fs-7" id="tanggal" name="tanggal" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alasan" class="form-label fs-8 text-muted fw-semibold mb-1">Alasan / Keterangan</label>
                        <textarea class="form-control fs-7" id="alasan" name="alasan" rows="3" placeholder="Tuliskan alasan izin atau keterangan sakit dengan jelas..." required></textarea>
                    </div>
                    
                    <div class="mb-2">
                        <label for="bukti_surat" class="form-label fs-8 text-muted fw-semibold mb-1">Upload Bukti Surat (PDF/JPG/PNG)</label>
                        <input type="file" class="form-control fs-7" id="bukti_surat" name="bukti_surat" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text fs-8 text-muted">Maksimal ukuran file 2MB.</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Daftar Pengajuan Izin/Sakit -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text text-primary me-2"></i> Riwayat Pengajuan Izin / Sakit</h5>

    <?php if (empty($leaves)): ?>
        <div class="alert alert-light border rounded-3 text-center py-5 mb-0">
            <i class="bi bi-file-earmark-check text-muted fs-2 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">Belum Ada Pengajuan Izin / Sakit</h6>
            <p class="text-muted fs-7 mb-0">Anda belum pernah mengajukan permohonan izin atau surat sakit pada semester ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-8 text-uppercase">
                    <tr>
                        <th>Tgl Pengajuan</th>
                        <th>Mata Kuliah</th>
                        <th>Tipe & Alasan</th>
                        <th>Dokumen Bukti</th>
                        <th>Catatan Dosen</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($leaves as $l): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($l['created_at'])) ?></strong><br>
                                <span class="text-muted fs-8">Tgl: <?= date('d/m/Y', strtotime($l['tanggal_mulai'])) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark font-monospace border me-1"><?= htmlspecialchars($l['kode_mk']) ?></span>
                                <strong><?= htmlspecialchars($l['nama_mk']) ?></strong><br>
                                <span class="text-muted fs-8">Dosen: <?= htmlspecialchars($l['nama_dosen']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $l['tipe'] === 'sakit' ? 'bg-warning text-dark' : 'bg-info' ?> text-uppercase mb-1">
                                    <?= htmlspecialchars($l['tipe']) ?>
                                </span>
                                <div class="text-dark fs-7"><?= htmlspecialchars($l['alasan']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($l['bukti_surat'])): ?>
                                    <a href="<?= base_url(htmlspecialchars($l['bukti_surat'])) ?>" target="_blank" class="badge bg-light text-dark border text-decoration-none">
                                        <i class="bi bi-paperclip me-1"></i> <?= htmlspecialchars($l['file_original_name'] ?? 'Surat_Bukti') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-dash"></i> Tidak ada file
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-muted fs-8"><?= htmlspecialchars($l['catatan_dosen'] ?? '-') ?></span>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
