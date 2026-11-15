<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i> Monitoring Mahasiswa</h4>
            <p class="text-muted mb-0 fs-7">Pilih mata kuliah yang Anda ampu untuk melihat data presensi mahasiswa.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($classes)): ?>
        <div class="col-12">
            <div class="alert alert-light border rounded-3 text-center py-5 mb-0">
                <i class="bi bi-journal-x text-muted fs-2 d-block mb-2"></i>
                <h6 class="fw-bold text-dark mb-1">Belum Ada Kelas</h6>
                <p class="text-muted fs-7 mb-0">Anda belum ditugaskan untuk mengampu kelas pada semester ini.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($classes as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-primary-subtle text-primary fw-semibold fs-8 rounded-pill px-3 py-2">
                                <i class="bi bi-building me-1"></i> Kelas <?= htmlspecialchars($c['nama_kelas']) ?>
                            </span>
                            <span class="badge bg-light text-dark border fs-8">
                                <?= htmlspecialchars($c['sks']) ?> SKS
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($c['nama_mk']) ?></h5>
                        <p class="text-muted fs-8 font-monospace mb-3"><?= htmlspecialchars($c['kode_mk']) ?></p>
                        
                        <div class="d-flex align-items-center text-muted fs-7 mb-2">
                            <i class="bi bi-calendar-event me-2"></i> <?= htmlspecialchars($c['hari']) ?>
                        </div>
                        <div class="d-flex align-items-center text-muted fs-7 mb-4">
                            <i class="bi bi-clock me-2"></i> <?= date('H:i', strtotime($c['jam_mulai'])) ?> - <?= date('H:i', strtotime($c['jam_selesai'])) ?> WIB
                        </div>
                        
                        <a href="<?= base_url('dosen/kelas/' . $c['id']) ?>" class="btn btn-outline-primary w-100 rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Pantau Kelas
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>
