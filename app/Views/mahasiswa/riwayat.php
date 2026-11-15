<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history text-primary me-2"></i> Riwayat Kehadiran Mahasiswa</h4>
            <p class="text-muted mb-0 fs-7">Seluruh catatan log presensi perkuliahan yang telah Anda lakukan pada semester aktif.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 rounded-pill fs-7 fw-semibold">
                Total Presensi: <?= count($history) ?> Record
            </span>
        </div>
    </div>
</div>

<!-- Tabel Log Riwayat Presensi -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <?php if (empty($history)): ?>
        <div class="alert alert-light border rounded-3 text-center py-5 mb-0">
            <i class="bi bi-calendar-x text-muted fs-2 d-block mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">Belum Ada Riwayat Presensi</h6>
            <p class="text-muted fs-7 mb-0">Anda belum melakukan presensi pada sesi perkuliahan manapun.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-8 text-uppercase">
                    <tr>
                        <th>Waktu Presensi</th>
                        <th>Mata Kuliah</th>
                        <th>Pertemuan</th>
                        <th>Dosen</th>
                        <th>Jarak GPS</th>
                        <th>Verifikasi AI</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($h['waktu_absensi'])) ?></strong><br>
                                <span class="text-muted fs-8"><i class="bi bi-clock me-1"></i> <?= date('H:i:s', strtotime($h['waktu_absensi'])) ?> WIB</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark font-monospace border me-1"><?= htmlspecialchars($h['kode_mk']) ?></span>
                                <strong><?= htmlspecialchars($h['nama_mk']) ?></strong><br>
                                <span class="text-muted fs-8">Kelas: <?= htmlspecialchars($h['nama_kelas']) ?> (<?= htmlspecialchars($h['ruangan']) ?>)</span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fw-bold">Sesi <?= (int)$h['pertemuan_ke'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($h['nama_dosen']) ?></td>
                            <td>
                                <?php if ($h['jarak_meter'] !== null): ?>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= number_format((float)$h['jarak_meter'], 1) ?> m
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fs-8">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($h['face_verified'])): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-shield-check me-1"></i> Wajah Cocok
                                    </span>
                                    <?php if ($h['face_similarity'] !== null): ?>
                                        <div class="fs-8 text-muted">Score: <?= number_format((float)$h['face_similarity'], 3) ?></div>
                                    <?php endif; ?>
                                <?php elseif ($h['status'] === 'izin' || $h['status'] === 'sakit'): ?>
                                    <span class="badge bg-light text-secondary border">Perizinan</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Manual / Tidak Terverifikasi</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $statusBadge = match($h['status']) {
                                    'hadir'     => 'bg-success',
                                    'terlambat' => 'bg-warning text-dark',
                                    'izin'      => 'bg-info',
                                    'sakit'     => 'bg-primary',
                                    default     => 'bg-danger'
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?> px-3 py-1 rounded-pill text-uppercase fs-8 fw-semibold">
                                    <?= htmlspecialchars($h['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
