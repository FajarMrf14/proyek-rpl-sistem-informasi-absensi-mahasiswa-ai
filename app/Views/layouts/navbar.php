<nav class="navbar navbar-expand-lg navbar-dark bg-primary-gradient sticky-top shadow-sm py-2">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white tracking-wide" href="<?= base_url() ?>">
            <?php if ($logoUrl = app_logo()): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="rounded-3 shadow-sm bg-white p-1" style="width: 38px; height: 38px; object-fit: contain;">
            <?php else: ?>
                <div class="brand-icon-box bg-white text-primary rounded-3 d-flex align-items-center justify-content-center shadow-sm">
                    <i class="bi bi-camera-video-fill fs-5"></i>
                </div>
            <?php endif; ?>
            <div>
                <span class="d-block lh-1 fs-5 fw-bold"><?= htmlspecialchars(campus_name()) ?></span>
                <span class="d-block lh-1 text-white-50 fs-8">Presensi Mahasiswa AI</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <i class="bi bi-list fs-2"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-2">
                <?php if (is_logged_in()): ?>
                    <?php $user = auth_user(); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <span class="badge bg-white text-primary fw-semibold text-uppercase px-2 py-1"><?= htmlspecialchars($user['role']) ?></span>
                            <span class="fw-semibold"><?= htmlspecialchars($user['nama_dengan_gelar'] ?? $user['nama_lengkap'] ?? $user['username']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2">
                            <li><h6 class="dropdown-header">Masuk Sebagai <?= ucfirst(htmlspecialchars($user['role'])) ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger d-flex align-items-center gap-2" href="<?= base_url('logout') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white-50 hover-white px-3" href="<?= base_url() ?>"><i class="bi bi-house-door me-1"></i> Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light text-primary fw-semibold px-4 rounded-pill shadow-sm" href="<?= base_url('login') ?>">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk / Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
