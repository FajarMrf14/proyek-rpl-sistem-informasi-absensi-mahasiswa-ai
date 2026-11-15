<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | STMIK IKMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="container text-center py-5">
        <div class="card border-0 shadow-lg rounded-4 p-5 mx-auto max-w-lg bg-white">
            <div class="display-1 fw-bold text-primary mb-3">404</div>
            <h3 class="fw-bold text-dark mb-2">Halaman Tidak Ditemukan</h3>
            <p class="text-muted mb-4">Maaf, tautan atau halaman yang Anda akses tidak tersedia atau telah dipindahkan.</p>
            <div>
                <a href="<?= base_url() ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold">
                    <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>
