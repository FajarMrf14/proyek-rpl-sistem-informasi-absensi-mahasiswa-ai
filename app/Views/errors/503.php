<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Sistem Dalam Pemeliharaan | <?= htmlspecialchars(app_name()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="container text-center py-5">
        <div class="card border-0 shadow-lg rounded-4 p-5 mx-auto bg-white" style="max-width: 540px;">
            <div class="display-1 fw-bold text-warning mb-3">
                <i class="bi bi-tools"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Mode Pemeliharaan Aktif</h3>
            <p class="text-muted mb-4">
                Sistem saat ini sedang dalam peningkatan performa atau pemeliharaan berkala oleh Tim Akademik. Silakan kembali dalam beberapa saat.
            </p>
            <div class="p-3 bg-light rounded-3 text-secondary small mb-4">
                <i class="bi bi-info-circle me-1 text-primary"></i> Jika Anda administrator, Anda dapat masuk ke panel kontrol untuk mengelola sistem.
            </div>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= base_url('login') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                    <i class="bi bi-shield-lock me-1"></i> Login Administrator
                </a>
            </div>
        </div>
    </div>
</body>
</html>
