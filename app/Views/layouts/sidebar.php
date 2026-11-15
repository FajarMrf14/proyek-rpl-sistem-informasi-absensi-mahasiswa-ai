<?php
$user = auth_user();
$role = $user['role'] ?? 'mahasiswa';
$currentUri = (new \App\Core\Request())->uri();
?>

<?php
$displayName = !empty($user['nama_dengan_gelar']) ? $user['nama_dengan_gelar'] : (!empty($user['nama_lengkap']) ? $user['nama_lengkap'] : ($user['username'] ?? 'Pengguna'));
$avatarLetter = strtoupper(substr($displayName, 0, 1));
?>

<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <div class="d-flex align-items-center gap-3 pb-3 border-bottom mb-3">
        <div class="avatar-box bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4">
            <?= $avatarLetter ?>
        </div>
        <div class="overflow-hidden">
            <h6 class="mb-0 fw-bold text-dark text-truncate"><?= htmlspecialchars($displayName) ?></h6>
            <div class="d-flex align-items-center gap-1 mt-1">
                <span class="badge bg-primary-subtle text-primary text-uppercase fs-8"><?= htmlspecialchars($role) ?></span>
                <?php if ($role === 'mahasiswa' && !empty($user['nim'])): ?>
                    <span class="text-muted fs-8 font-monospace"><?= htmlspecialchars($user['nim']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills flex-column gap-1">
        <?php if ($role === 'mahasiswa'): ?>
            <!-- Menu 1: Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/mahasiswa/dashboard' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('mahasiswa/dashboard') ?>">
                    <i class="bi bi-grid-fill"></i> Dashboard
                </a>
            </li>
            <!-- Menu 2: Absensi -->
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/mahasiswa/absensi' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('mahasiswa/absensi') ?>">
                    <i class="bi bi-camera-video-fill"></i> Absensi
                </a>
            </li>
            <!-- Menu 3: Riwayat Absensi -->
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/mahasiswa/riwayat' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('mahasiswa/riwayat') ?>">
                    <i class="bi bi-clock-history"></i> Riwayat Absensi
                </a>
            </li>
            <!-- Menu 4: Izin/Sakit -->
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/mahasiswa/izin' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('mahasiswa/izin') ?>">
                    <i class="bi bi-file-earmark-medical-fill"></i> Izin/Sakit
                </a>
            </li>
            <!-- Menu 5: Profil -->
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/mahasiswa/profil' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('mahasiswa/profil') ?>">
                    <i class="bi bi-person-circle"></i> Profil
                </a>
            </li>

        <?php elseif ($role === 'dosen'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/dosen/dashboard' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('dosen/dashboard') ?>">
                    <i class="bi bi-grid-fill"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_starts_with($currentUri, '/dosen/kelas') ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('dosen/kelas') ?>">
                    <i class="bi bi-people-fill"></i> Monitoring Mahasiswa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary d-flex align-items-center gap-2 rounded-3" href="#" onclick="Swal.fire('Info', 'Modul Validasi Izin akan diaktifkan pada tahap berikutnya.', 'info'); return false;">
                    <i class="bi bi-clipboard-check-fill"></i> Validasi Izin/Sakit
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/admin/dashboard' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('admin/dashboard') ?>">
                    <i class="bi bi-grid-fill"></i> Dashboard Admin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_starts_with($currentUri, '/admin/users') ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('admin/users') ?>">
                    <i class="bi bi-people-fill"></i> Manajemen User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_starts_with($currentUri, '/admin/jadwal') ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('admin/jadwal') ?>">
                    <i class="bi bi-calendar3-week-fill"></i> Jadwal Kuliah
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary d-flex align-items-center gap-2 rounded-3" href="#" onclick="Swal.fire('Info', 'Modul Master Data akan diaktifkan pada tahap berikutnya.', 'info'); return false;">
                    <i class="bi bi-database-fill"></i> Master Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentUri === '/admin/izin' ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('admin/izin') ?>">
                    <i class="bi bi-clipboard-check-fill"></i> Verifikasi Izin/Sakit
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_starts_with($currentUri, '/admin/konfigurasi') ? 'active' : 'text-secondary' ?> d-flex align-items-center gap-2 rounded-3" href="<?= base_url('admin/konfigurasi') ?>">
                    <i class="bi bi-sliders"></i> Konfigurasi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary d-flex align-items-center gap-2 rounded-3" href="#" onclick="Swal.fire('Info', 'Modul Rekapitulasi Laporan akan diaktifkan pada tahap berikutnya.', 'info'); return false;">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan & Rekap
                </a>
            </li>
        <?php endif; ?>

        <!-- Menu 6: Logout -->
        <li class="nav-item mt-3 pt-2 border-top">
            <a class="nav-link text-danger d-flex align-items-center gap-2 rounded-3" href="<?= base_url('logout') ?>">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>
