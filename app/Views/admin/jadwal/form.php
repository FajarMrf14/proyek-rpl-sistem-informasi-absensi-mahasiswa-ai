<?php
$lockedIdentity = $isEdit && ((int) ($relatedCounts['total'] ?? 0) > 0);

$selectedTa = $lockedIdentity
    ? (int) ($schedule['tahun_akademik_id'] ?? 0)
    : (int) ($formData['tahun_akademik_id'] ?? 0);
$selectedMk = $lockedIdentity
    ? (int) ($schedule['mata_kuliah_id'] ?? 0)
    : (int) ($formData['mata_kuliah_id'] ?? 0);
$selectedKelas = $lockedIdentity
    ? (int) ($schedule['kelas_id'] ?? 0)
    : (int) ($formData['kelas_id'] ?? 0);

$formAction = $isEdit
    ? base_url('admin/jadwal/' . (int) $schedule['id'] . '/update')
    : base_url('admin/jadwal/store');
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                <?= $isEdit ? 'Edit Jadwal Kuliah' : 'Tambah Jadwal Kuliah' ?>
            </h4>
            <p class="text-muted mb-0 fs-7">
                <?= $isEdit ? 'Perbarui informasi jadwal tanpa menghilangkan riwayat absensi.' : 'Buat jadwal baru untuk hari Senin sampai Sabtu.' ?>
            </p>
        </div>
        <a href="<?= base_url('admin/jadwal') ?>" class="btn btn-light border rounded-pill px-3 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> Data belum dapat disimpan</h6>
        <ul class="mb-0 fs-7">
            <?php foreach ($errors as $messages): ?>
                <?php foreach ((array) $messages as $message): ?>
                    <li><?= htmlspecialchars((string) $message) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($lockedIdentity): ?>
    <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4 fs-7">
        <i class="bi bi-lock-fill me-1"></i>
        Jadwal ini sudah memiliki <strong><?= (int) ($relatedCounts['peserta'] ?? 0) ?> peserta</strong>,
        <strong><?= (int) ($relatedCounts['pertemuan'] ?? 0) ?> pertemuan</strong>,
        <strong><?= (int) ($relatedCounts['absensi'] ?? 0) ?> absensi</strong>, dan
        <strong><?= (int) ($relatedCounts['izin'] ?? 0) ?> izin</strong>.
        Kelas, mata kuliah, dan tahun akademik dikunci agar riwayat tidak berpindah.
        Jika perlu mengganti identitas tersebut, nonaktifkan jadwal ini lalu buat jadwal baru.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <form id="jadwalForm" action="<?= $formAction ?>" method="POST">
        <?= csrf_field() ?>

        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">1</span>
            <h6 class="fw-bold text-dark mb-0">Informasi Jadwal</h6>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="hari" class="form-label fw-semibold text-dark fs-7">Hari <span class="text-danger">*</span></label>
                <select id="hari" name="hari" class="form-select rounded-3" required>
                    <option value="">-- Pilih Hari --</option>
                    <?php foreach ($days as $day): ?>
                        <option value="<?= htmlspecialchars($day) ?>" <?= (string) ($formData['hari'] ?? '') === $day ? 'selected' : '' ?>>
                            <?= htmlspecialchars($day) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text fs-8">Jadwal baru dibatasi Senin&ndash;Sabtu.</div>
            </div>

            <div class="col-md-4">
                <label for="tahun_akademik_id" class="form-label fw-semibold text-dark fs-7">Tahun Akademik <span class="text-danger">*</span></label>
                <?php if ($lockedIdentity): ?>
                    <input type="hidden" name="tahun_akademik_id" value="<?= $selectedTa ?>">
                <?php endif; ?>
                <select id="tahun_akademik_id" name="tahun_akademik_id" class="form-select rounded-3" required <?= $lockedIdentity ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Tahun Akademik --</option>
                    <?php foreach ($options['tahun_akademik'] as $ta): ?>
                        <option value="<?= (int) $ta['id'] ?>" <?= $selectedTa === (int) $ta['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['nama_tahun']) ?> &middot; Semester <?= htmlspecialchars(ucfirst((string) $ta['semester'])) ?>
                            <?= (int) $ta['is_active'] === 1 ? ' (Aktif)' : ' (Nonaktif)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text fs-8">Semester tersimpan di periode tahun akademik.</div>
            </div>

            <div class="col-md-4">
                <label for="mata_kuliah_id" class="form-label fw-semibold text-dark fs-7">Mata Kuliah <span class="text-danger">*</span></label>
                <?php if ($lockedIdentity): ?>
                    <input type="hidden" name="mata_kuliah_id" value="<?= $selectedMk ?>">
                <?php endif; ?>
                <select id="mata_kuliah_id" name="mata_kuliah_id" class="form-select rounded-3" required <?= $lockedIdentity ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php foreach ($options['mata_kuliah'] as $mk): ?>
                        <option value="<?= (int) $mk['id'] ?>" <?= $selectedMk === (int) $mk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mk['kode_mk'] . ' - ' . $mk['nama_mk']) ?>
                            (<?= (int) $mk['sks'] ?> SKS<?= (int) $mk['is_active'] === 1 ? '' : ', nonaktif' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="dosen_id" class="form-label fw-semibold text-dark fs-7">Dosen Pengampu <span class="text-danger">*</span></label>
                <select id="dosen_id" name="dosen_id" class="form-select rounded-3" required>
                    <option value="">-- Pilih Dosen --</option>
                    <?php foreach ($options['dosen'] as $dosen): ?>
                        <option value="<?= (int) $dosen['id'] ?>" <?= (int) ($formData['dosen_id'] ?? 0) === (int) $dosen['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dosen['nama_lengkap']) ?> (<?= htmlspecialchars($dosen['nidn']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="kelas_id" class="form-label fw-semibold text-dark fs-7">Kelas <span class="text-danger">*</span></label>
                <?php if ($lockedIdentity): ?>
                    <input type="hidden" name="kelas_id" value="<?= $selectedKelas ?>">
                <?php endif; ?>
                <select id="kelas_id" name="kelas_id" class="form-select rounded-3" required <?= $lockedIdentity ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($options['kelas'] as $kelas): ?>
                        <option value="<?= (int) $kelas['id'] ?>" <?= $selectedKelas === (int) $kelas['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kelas['nama_kelas']) ?> &middot; <?= htmlspecialchars($kelas['prodi']) ?> <?= htmlspecialchars((string) $kelas['angkatan']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="ruangan" class="form-label fw-semibold text-dark fs-7">Ruangan <span class="text-danger">*</span></label>
                <input type="text" id="ruangan" name="ruangan" class="form-control rounded-3"
                       value="<?= htmlspecialchars((string) ($formData['ruangan'] ?? '')) ?>"
                       list="ruanganOptions" maxlength="50" placeholder="Contoh: Lab Komputer 1" required>
                <datalist id="ruanganOptions">
                    <?php foreach ($options['ruangan'] as $room): ?>
                        <option value="<?= htmlspecialchars((string) $room['ruangan']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="form-text fs-8">Daftar berasal dari ruangan yang sudah dipakai; ruangan baru tetap dapat diketik.</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">2</span>
            <h6 class="fw-bold text-dark mb-0">Waktu &amp; Pengaturan</h6>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="jam_mulai" class="form-label fw-semibold text-dark fs-7">Jam Mulai <span class="text-danger">*</span></label>
                <input type="time" id="jam_mulai" name="jam_mulai" class="form-control rounded-3"
                       value="<?= htmlspecialchars(substr((string) ($formData['jam_mulai'] ?? ''), 0, 5)) ?>" required>
            </div>

            <div class="col-md-3">
                <label for="jam_selesai" class="form-label fw-semibold text-dark fs-7">Jam Selesai <span class="text-danger">*</span></label>
                <input type="time" id="jam_selesai" name="jam_selesai" class="form-control rounded-3"
                       value="<?= htmlspecialchars(substr((string) ($formData['jam_selesai'] ?? ''), 0, 5)) ?>" required>
                <div class="form-text fs-8">Harus lebih besar dari jam mulai.</div>
            </div>

            <div class="col-md-3">
                <label for="toleransi_keterlambatan" class="form-label fw-semibold text-dark fs-7">Toleransi Terlambat (menit)</label>
                <input type="number" id="toleransi_keterlambatan" name="toleransi_keterlambatan"
                       class="form-control rounded-3" min="0" max="120"
                       value="<?= (int) ($formData['toleransi_keterlambatan'] ?? 15) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold text-dark fs-7">Status Jadwal</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                           <?= (int) ($formData['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fs-7 text-dark" for="is_active">Aktif</label>
                </div>
            </div>
        </div>

        <?php if (!$lockedIdentity): ?>
            <div class="border rounded-4 p-3 bg-light mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="salin_peserta" name="salin_peserta" value="1"
                           <?= (int) ($formData['salin_peserta'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fs-7 text-dark fw-semibold" for="salin_peserta">
                        Salin peserta/KRS dari jadwal lain pada kelas dan tahun akademik yang sama
                    </label>
                </div>
                <div class="form-text fs-8 mt-1">
                    Membantu jadwal baru langsung muncul di dashboard mahasiswa dan terhubung ke sesi absensi.
                    Jika belum ada jadwal lain pada kelas tersebut, peserta tidak dapat disalin otomatis.
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-light border rounded-4 fs-8 mb-4">
                <i class="bi bi-info-circle me-1"></i>
                Perubahan waktu, dosen, ruangan, toleransi, dan status tetap dapat dilakukan. Identitas kelas/mata kuliah/tahun akademik dikunci karena sudah ada riwayat.
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-end gap-2 pt-3 border-top">
            <a href="<?= base_url('admin/jadwal') ?>" class="btn btn-light border rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                <i class="bi bi-save me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Jadwal' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('jadwalForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const start = form.querySelector('[name="jam_mulai"]')?.value || '';
        const end = form.querySelector('[name="jam_selesai"]')?.value || '';

        if (start && end && end <= start) {
            event.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jam tidak valid',
                    text: 'Jam selesai harus lebih besar daripada jam mulai.',
                    customClass: { popup: 'rounded-4' }
                });
            } else {
                alert('Jam selesai harus lebih besar daripada jam mulai.');
            }
        }
    });
});
</script>
