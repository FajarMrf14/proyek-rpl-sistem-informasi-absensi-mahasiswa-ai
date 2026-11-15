<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Manajemen Pengguna (User Management)</h4>
            <p class="text-muted mb-0 fs-7">
                Kelola nama lengkap, email, dan nomor WhatsApp untuk notifikasi Fonnte sivitas akademika STMIK IKMI Cirebon.
            </p>
        </div>
        <div>
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fs-7 fw-semibold">
                <i class="bi bi-people-fill me-1"></i> Total <?= count($users) ?> Akun Terdaftar
            </span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 text-secondary text-uppercase fs-8">ID & Username</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Nama Lengkap Pemilik Akun</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Role</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Email</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">WhatsApp</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">NIM / NIDN</th>
                    <th class="py-3 text-secondary text-uppercase fs-8">Login Terakhir</th>
                    <th class="pe-4 py-3 text-end text-secondary text-uppercase fs-8">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php 
                        $roleBadge = match($u['role']) {
                            'admin'     => 'bg-danger-subtle text-danger',
                            'dosen'     => 'bg-success-subtle text-success',
                            'mahasiswa' => 'bg-primary-subtle text-primary',
                            default     => 'bg-secondary-subtle text-secondary'
                        };
                        $identitas = !empty($u['nim']) ? $u['nim'] : (!empty($u['nidn']) ? $u['nidn'] : '-');
                        $displayName = !empty($u['nama_lengkap']) ? $u['nama_lengkap'] : '<span class="text-muted fst-italic">Belum disetel</span>';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-dark">#<?= $u['id'] ?></span>
                            <span class="badge bg-light text-dark border ms-1 font-monospace"><?= htmlspecialchars($u['username']) ?></span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= $displayName ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $roleBadge ?> text-uppercase fs-8 px-2 py-1 fw-semibold">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td class="fs-7 text-muted">
                            <?= htmlspecialchars($u['email'] ?? '-') ?>
                        </td>
                        <td class="fs-7">
                            <?php if (in_array($u['role'], ['mahasiswa', 'dosen', 'admin'], true)): ?>
                                <?php if (!empty($u['no_hp'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($u['no_hp']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fs-8 fst-italic">Belum diisi</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="fs-7 font-monospace">
                            <?= htmlspecialchars($identitas) ?>
                        </td>
                        <td class="fs-8 text-muted">
                            <?= !empty($u['last_login_at']) ? htmlspecialchars($u['last_login_at']) : 'Belum pernah' ?>
                        </td>
                        <td class="pe-4 text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal<?= $u['id'] ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit Nama
                            </button>

                            <!-- Modal Edit Pengguna -->
                            <div class="modal fade text-start" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <form action="<?= base_url('admin/users/update/' . $u['id']) ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Edit Profil Akun</h5>
                                                <button type="button" class="btn-close" data-bs-toggle="modal"></button>
                                            </div>
                                            <div class="modal-body py-3">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted fs-8 mb-1">Username Login (Tetap)</label>
                                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($u['username']) ?>" readonly disabled>
                                                    <div class="form-text fs-8">Username login tidak diubah agar kredensial tetap valid.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label text-muted fs-8 mb-1">Role Akun</label>
                                                    <input type="text" class="form-control bg-light text-uppercase fw-semibold" value="<?= htmlspecialchars($u['role']) ?>" readonly disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="nama_lengkap_<?= $u['id'] ?>" class="form-label fw-semibold text-dark fs-7 mb-1">
                                                        Nama Lengkap Pemilik Akun <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control rounded-3 py-2" 
                                                           id="nama_lengkap_<?= $u['id'] ?>" 
                                                           name="nama_lengkap" 
                                                           value="<?= htmlspecialchars($u['nama_lengkap'] ?? '') ?>" 
                                                           placeholder="Contoh: Fajar Ma'ruf" 
                                                           required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="email_<?= $u['id'] ?>" class="form-label fw-semibold text-dark fs-7 mb-1">
                                                        Alamat Email
                                                    </label>
                                                    <input type="email" 
                                                           class="form-control rounded-3 py-2" 
                                                           id="email_<?= $u['id'] ?>" 
                                                           name="email" 
                                                           value="<?= htmlspecialchars($u['email'] ?? '') ?>" 
                                                           placeholder="email@ikmi.ac.id">
                                                </div>
                                                <?php if (in_array($u['role'], ['mahasiswa', 'dosen', 'admin'], true)): ?>
                                                    <div class="mb-3">
                                                        <label for="no_hp_<?= $u['id'] ?>" class="form-label fw-semibold text-dark fs-7 mb-1">
                                                            Nomor WhatsApp (Fonnte)
                                                        </label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light border-end-0 text-success"><i class="bi bi-whatsapp"></i></span>
                                                            <input type="tel"
                                                                   class="form-control border-start-0 rounded-end-3 py-2"
                                                                   id="no_hp_<?= $u['id'] ?>"
                                                                   name="no_hp"
                                                                   value="<?= htmlspecialchars($u['no_hp'] ?? '') ?>"
                                                                   placeholder="08xxxxxxxxxx"
                                                                   inputmode="tel"
                                                                   autocomplete="tel"
                                                                   maxlength="20">
                                                        </div>
                                                        <div class="form-text fs-8">
                                                            Dipakai untuk notifikasi WhatsApp (absensi, pengingat jadwal, pengumuman). Nomor disimpan dalam format <code>62...</code>. Kosongkan untuk menghapus nomor.
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary rounded-pill px-4">
                                                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
