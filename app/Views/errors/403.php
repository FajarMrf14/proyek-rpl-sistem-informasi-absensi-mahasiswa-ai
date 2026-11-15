<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak | STMIK IKMI Cirebon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="container text-center py-5">
        <div class="card border-0 shadow-lg rounded-4 p-5 mx-auto max-w-lg bg-white" style="max-width: 520px;">
            <div class="display-1 fw-bold text-danger mb-3">
                <i class="bi bi-shield-x"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">403 - Akses Ditolak</h3>
            <p class="text-muted mb-4">
                Role akun Anda saat ini (<strong><?= htmlspecialchars(user_role() ?? 'Guest') ?></strong>) tidak memiliki izin untuk membuka halaman ini.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <?php if (is_logged_in()): ?>
                    <?php 
                    $role = user_role();
                    $dashUrl = match($role) {
                        'admin' => base_url('admin/dashboard'),
                        'dosen' => base_url('dosen/dashboard'),
                        'mahasiswa' => base_url('mahasiswa/dashboard'),
                        default => base_url('/')
                    };
                    ?>
                    <a href="<?= $dashUrl ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-grid-fill me-1"></i> Dashboard Saya
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('login') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Akun
                    </a>
                <?php endif; ?>
                <a href="<?= base_url() ?>" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-semibold">
                    <i class="bi bi-house-door me-1"></i> Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>
