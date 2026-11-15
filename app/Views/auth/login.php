<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-primary-gradient text-white text-center py-4 border-0">
                <div class="login-brand-icon mx-auto mb-2 bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm">
                    <i class="bi bi-shield-lock-fill fs-3"></i>
                </div>
                <h4 class="fw-bold mb-1 tracking-wide">STMIK IKMI CIREBON</h4>
                <p class="mb-0 fs-8 text-white-80">Portal Presensi & Akademik Terpadu</p>
            </div>

            <div class="card-body p-4">

                <form action="<?= base_url('login') ?>" method="POST" id="formLogin">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold text-dark fs-7">NIM / NIDN / Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" class="form-control bg-light border-start-0 rounded-end-3 py-2" id="username" name="username" placeholder="Masukkan NIM, NIDN, atau Username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold text-dark fs-7 mb-0">Password</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control bg-light border-start-0 border-end-0 py-2" id="password" name="password" placeholder="Masukkan Password" required>
                            <button class="btn btn-light border-start-0 rounded-end-3 text-muted" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 shadow-sm mb-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                    </button>

                    <div class="text-center">
                        <a href="<?= base_url() ?>" class="text-decoration-none fs-8 text-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
                        </a>
                    </div>
                </form>
            </div>

            <div class="card-footer bg-light text-center py-3 border-0 fs-8 text-muted">
                <i class="bi bi-shield-check text-success me-1"></i> Dilindungi oleh Session & CSRF Token Protection
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
});
</script>
