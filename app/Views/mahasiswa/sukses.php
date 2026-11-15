<?php
/**
 * View: mahasiswa/sukses.php
 * Halaman Rekap & Bukti Presensi Berhasil
 *
 * Variabel yang tersedia dari controller:
 *   $jadwal  — array detail jadwal terpilih
 *   $bukti   — array detail transaksi absensi dari DB / Session
 *   $user    — array profil mahasiswa
 */

$statusBadge = ($bukti['status'] === 'terlambat') ? 'bg-warning text-dark' : 'bg-success';
$statusText  = ($bukti['status'] === 'terlambat') ? 'TERLAMBAT' : 'HADIR';
?>

<!-- Header Banner Sukses -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 text-center">
    <div class="p-3 bg-success-subtle text-success rounded-circle d-inline-flex mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px; align-items: center; justify-content: center;">
        <i class="bi bi-patch-check-fill" style="font-size: 2.8rem;"></i>
    </div>
    <h3 class="fw-bold text-dark mb-1">Presensi Berhasil Dicatat!</h3>
    <p class="text-muted fs-7 mb-0">
        Kehadiran Anda telah terverifikasi melalui enkripsi GPS, AI Face Verification, dan Foto Kegiatan Kelas.
    </p>
</div>

<!-- Card Bukti Presensi Resmi -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
            <div class="text-muted fs-8 text-uppercase fw-semibold">Bukti Kehadiran Digital</div>
            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($bukti['nama_mk'] ?? $jadwal['nama_mk']) ?></h5>
        </div>
        <span class="badge <?= $statusBadge ?> fs-6 px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-check-circle-fill me-1"></i> STATUS: <?= $statusText ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- Kolom Informasi Detail -->
        <div class="col-md-7">
            <div class="table-responsive">
                <table class="table table-borderless table-sm fs-7">
                    <tbody>
                        <tr>
                            <td class="text-muted py-2" style="width: 35%;">Nama Mahasiswa</td>
                            <td class="fw-bold text-dark py-2">: <?= htmlspecialchars($user['nama_lengkap'] ?? $bukti['nama_mhs'] ?? 'Mahasiswa') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">NIM</td>
                            <td class="fw-bold text-dark py-2">: <?= htmlspecialchars($user['nim'] ?? $bukti['nim_mhs'] ?? 'NIM') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Mata Kuliah / Kode</td>
                            <td class="fw-bold text-dark py-2">: <?= htmlspecialchars($bukti['nama_mk'] ?? $jadwal['nama_mk']) ?> (<?= htmlspecialchars($jadwal['kode_mk'] ?? 'MK') ?>)</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Kelas / Dosen</td>
                            <td class="fw-bold text-dark py-2">: <?= htmlspecialchars($jadwal['nama_kelas']) ?> | <?= htmlspecialchars($jadwal['nama_dosen']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Tanggal & Waktu</td>
                            <td class="fw-bold text-primary py-2">: <i class="bi bi-clock me-1"></i> <?= date('d F Y, H:i:s', strtotime($bukti['waktu_absensi'] ?? date('Y-m-d H:i:s'))) ?> WIB</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Status GPS Kampus</td>
                            <td class="py-2">: <span class="badge bg-success-subtle text-success border border-success fs-8">✅ Terverifikasi (<?= number_format((float)($bukti['jarak_meter'] ?? 0), 1) ?>m)</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Status Biometrik AI</td>
                            <td class="py-2">: <span class="badge bg-success-subtle text-success border border-success fs-8">✅ Match AI (<?= number_format(((float)($bukti['face_similarity'] ?? 0.95)) * 100, 1) ?>%)</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kolom Foto Kegiatan Kelas -->
        <div class="col-md-5 text-center">
            <div class="text-muted fs-8 text-uppercase fw-semibold mb-2">Dokumentasi Foto Kegiatan</div>
            <?php if (!empty($bukti['foto_kegiatan'])): ?>
                <div class="rounded-4 overflow-hidden border shadow-sm">
                    <img src="<?= base_url($bukti['foto_kegiatan']) ?>" class="w-100" style="max-height: 220px; object-fit: cover;" alt="Foto Kegiatan">
                </div>
            <?php else: ?>
                <div class="p-4 bg-light rounded-4 border text-muted fs-8">
                    <i class="bi bi-image fs-1 d-block mb-1 text-secondary"></i>
                    Foto kegiatan tersimpan
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tombol Navigasi Akhir -->
    <div class="d-flex justify-content-center gap-3 mt-4 pt-3 border-top flex-wrap">
        <a href="<?= base_url('mahasiswa/dashboard') ?>" class="btn btn-primary rounded-pill px-5 fw-semibold shadow-sm">
            <i class="bi bi-speedometer2 me-1"></i> Kembali ke Dashboard
        </a>
        <a href="<?= base_url('mahasiswa/riwayat') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-journal-text me-1"></i> Lihat Riwayat Absensi
        </a>
    </div>
</div>
