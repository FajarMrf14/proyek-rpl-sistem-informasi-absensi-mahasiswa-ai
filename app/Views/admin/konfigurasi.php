<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fs-8 fw-semibold">
                    <i class="bi bi-shield-check me-1"></i> Area Administrator
                </span>
                <span class="text-muted fs-8">
                    <i class="bi bi-clock me-1"></i> Terakhir dimuat: <?= date('H:i:s') ?> WIB
                </span>
            </div>
            <h4 class="fw-bold text-dark mb-1">Konfigurasi Sistem & Aplikasi</h4>
            <p class="text-muted mb-0 fs-7">
                Pusat kendali parameter operasional, integrasi AI Chatbot, notifikasi WhatsApp, aturan akademik, dan preferensi global sistem.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary rounded-pill px-3 py-2 fs-7 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Navigasi Tab Kategori Konfigurasi -->
<div class="card border-0 shadow-sm rounded-4 bg-white p-2 mb-4">
    <ul class="nav nav-pills nav-fill gap-2" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $activeTab === 'umum' ? 'active' : 'text-secondary' ?>" 
                    id="tab-umum-btn" data-bs-toggle="pill" data-bs-target="#tab-umum" type="button" role="tab">
                <i class="bi bi-sliders2 fs-5"></i>
                <div class="text-start d-none d-sm-block">
                    <div class="lh-1">Pengaturan Umum</div>
                    <small class="fs-8 fw-normal opacity-75">Identitas & Logo</small>
                </div>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $activeTab === 'chatbot' ? 'active' : 'text-secondary' ?>" 
                    id="tab-chatbot-btn" data-bs-toggle="pill" data-bs-target="#tab-chatbot" type="button" role="tab">
                <i class="bi bi-robot fs-5"></i>
                <div class="text-start d-none d-sm-block">
                    <div class="lh-1">Chatbot AI</div>
                    <small class="fs-8 fw-normal opacity-75">Gemini Provider & API</small>
                </div>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $activeTab === 'akademik' ? 'active' : 'text-secondary' ?>" 
                    id="tab-akademik-btn" data-bs-toggle="pill" data-bs-target="#tab-akademik" type="button" role="tab">
                <i class="bi bi-mortarboard fs-5"></i>
                <div class="text-start d-none d-sm-block">
                    <div class="lh-1">Aturan Akademik</div>
                    <small class="fs-8 fw-normal opacity-75">Semester & Geofence</small>
                </div>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $activeTab === 'sistem' ? 'active' : 'text-secondary' ?>" 
                    id="tab-sistem-btn" data-bs-toggle="pill" data-bs-target="#tab-sistem" type="button" role="tab">
                <i class="bi bi-hdd-network fs-5"></i>
                <div class="text-start d-none d-sm-block">
                    <div class="lh-1">Pengaturan Sistem</div>
                    <small class="fs-8 fw-normal opacity-75">Maintenance & Sesi</small>
                </div>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 py-2 px-3 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $activeTab === 'whatsapp' ? 'active' : 'text-secondary' ?>" 
                    id="tab-whatsapp-btn" data-bs-toggle="pill" data-bs-target="#tab-whatsapp" type="button" role="tab">
                <i class="bi bi-whatsapp fs-5"></i>
                <div class="text-start d-none d-sm-block">
                    <div class="lh-1">Notifikasi WhatsApp</div>
                    <small class="fs-8 fw-normal opacity-75">Fonnte Gateway</small>
                </div>
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="configTabsContent">

    <!-- ======================================================================= -->
    <!-- KATEGORI A: PENGATURAN UMUM                                            -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $activeTab === 'umum' ? 'show active' : '' ?>" id="tab-umum" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="border-bottom pb-3 mb-4">
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-sliders2 text-primary me-2"></i>A. Pengaturan Umum Aplikasi</h5>
                <p class="text-muted mb-0 fs-7">Atur identitas aplikasi, nama institusi perguruan tinggi, email kontak, logo, dan zona waktu resmi.</p>
            </div>

            <form action="<?= base_url('admin/konfigurasi/umum') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <!-- Nama Aplikasi -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Nama Aplikasi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-window"></i></span>
                            <input type="text" name="app_name" class="form-control border-start-0" 
                                   value="<?= htmlspecialchars((string)($grouped['umum']['app_name']['value'] ?? $config['name'])) ?>" 
                                   required maxlength="100" placeholder="Contoh: Absensi Mahasiswa AI - STMIK IKMI Cirebon">
                        </div>
                        <small class="text-muted fs-8">Ditampilkan pada bilah navigasi, header tab browser, dan laporan.</small>
                    </div>

                    <!-- Nama Institusi Kampus -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Nama Kampus / Institusi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-building"></i></span>
                            <input type="text" name="campus_name" class="form-control border-start-0" 
                                   value="<?= htmlspecialchars((string)($grouped['umum']['campus_name']['value'] ?? $campus['nama_kampus'])) ?>" 
                                   required maxlength="100" placeholder="STMIK IKMI Cirebon">
                        </div>
                        <small class="text-muted fs-8">Nama resmi perguruan tinggi penyelenggara perkuliahan.</small>
                    </div>

                    <!-- Deskripsi Meta Aplikasi -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark fs-7">Deskripsi Aplikasi</label>
                        <textarea name="app_description" class="form-control" rows="2" maxlength="255"
                                  placeholder="Deskripsi singkat sistem untuk informasi pengguna dan meta tag"><?= htmlspecialchars((string)($grouped['umum']['app_description']['value'] ?? $config['description'])) ?></textarea>
                        <small class="text-muted fs-8">Digunakan pada meta deskripsi HTML dan informasi footer.</small>
                    </div>

                    <!-- Email Kontak & Zona Waktu -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Email Kontak Institusi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="contact_email" class="form-control border-start-0" 
                                   value="<?= htmlspecialchars((string)($grouped['umum']['contact_email']['value'] ?? $config['contact_email'])) ?>" 
                                   required placeholder="info@ikmi.ac.id">
                        </div>
                        <small class="text-muted fs-8">Alamat korespondensi bantuan teknis dan pertanyaan mahasiswa.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Zona Waktu Sistem (Timezone) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-globe"></i></span>
                            <select name="app_timezone" class="form-select border-start-0" required>
                                <?php 
                                $currentTimezone = $grouped['umum']['app_timezone']['value'] ?? $config['timezone'];
                                foreach ($timezones as $tzKey => $tzLabel): 
                                ?>
                                    <option value="<?= $tzKey ?>" <?= $currentTimezone === $tzKey ? 'selected' : '' ?>>
                                        <?= $tzLabel ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="text-muted fs-8">Mempengaruhi pencatatan waktu presensi, keterlambatan, dan sesi kelas.</small>
                    </div>

                    <!-- Upload Logo Aplikasi -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark fs-7">Logo Aplikasi</label>
                        <div class="d-flex flex-column flex-sm-row align-items-start gap-4 p-3 bg-light rounded-4 border">
                            <div class="logo-preview-box bg-white rounded-3 shadow-sm d-flex align-items-center justify-content-center p-2 border" 
                                 style="width: 80px; height: 80px;">
                                <?php if ($logoUrl = app_logo()): ?>
                                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo Preview" class="img-fluid" style="max-height: 64px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="bi bi-camera-video-fill text-primary display-6"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="logo_file" class="form-control form-control-sm mb-2" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                <div class="fs-8 text-muted mb-2">
                                    <i class="bi bi-info-circle me-1"></i> Format yang didukung: PNG, JPG, WEBP, atau SVG. Ukuran maksimal 2MB. Logo akan ditampilkan di navbar atas.
                                </div>
                                <?php if (!empty($grouped['umum']['app_logo']['value'])): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogoCheck">
                                        <label class="form-check-label text-danger fs-8 fw-semibold" for="removeLogoCheck">
                                            <i class="bi bi-trash3 me-1"></i> Hapus logo saat ini dan gunakan ikon default
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan Umum
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- KATEGORI B: KONFIGURASI CHATBOT AI                                     -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $activeTab === 'chatbot' ? 'show active' : '' ?>" id="tab-chatbot" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-1"><i class="bi bi-robot text-primary me-2"></i>B. Konfigurasi Chatbot AI</h5>
                    <p class="text-muted mb-0 fs-7">Provider dikunci ke Google Gemini. Admin hanya mengatur model, endpoint, dan API key untuk asisten percakapan presensi mahasiswa.</p>
                </div>
                <div>
                    <?php if ($isApiKeyConfigured): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-7 fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i> Provider AI Terkonfigurasi
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fs-7 fw-semibold">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> API Key Belum Dikonfigurasi
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <form id="formChatbotConfig" action="<?= base_url('admin/konfigurasi/chatbot') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <!-- Model AI -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Model AI Aktif <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-layers"></i></span>
                            <select name="gemini_model" class="form-select border-start-0" id="geminiModelInput" required>
                                <?php 
                                $activeModel = $activeModel ?? $grouped['chatbot']['gemini_model']['value'] ?? $config['chatbot']['model'];
                                foreach ($availableGeminiModels as $mKey => $mLabel): 
                                ?>
                                    <option value="<?= $mKey ?>" <?= $activeModel === $mKey ? 'selected' : '' ?>>
                                        <?= $mLabel ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="text-muted fs-8">Model aktif yang digunakan untuk memproses pertanyaan presensi dan jadwal.</small>
                    </div>

                    <!-- Base URL Endpoint -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark fs-7">Endpoint Base URL <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" name="gemini_base_url" id="geminiBaseUrlInput" class="form-control border-start-0 font-monospace fs-7" 
                                   value="<?= htmlspecialchars((string)($grouped['chatbot']['gemini_base_url']['value'] ?? $config['chatbot']['base_url'])) ?>" 
                                   required placeholder="https://generativelanguage.googleapis.com/v1beta">
                        </div>
                        <small class="text-muted fs-8">Root endpoint API provider AI.</small>
                    </div>

                    <!-- API Key (Keamanan Tingkat Tinggi) -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold text-dark fs-7 mb-0">
                                    <i class="bi bi-key-fill text-warning me-1"></i> API Key Google Gemini
                                </label>
                                <?php if ($isStoredApiKeyConfigured): ?>
                                    <span class="badge bg-secondary-subtle text-secondary fs-8">
                                        <i class="bi bi-lock-fill me-1"></i> API Key tersimpan terenkripsi di database
                                    </span>
                                <?php elseif ($apiKeySource === 'env'): ?>
                                    <span class="badge bg-info-subtle text-info fs-8">
                                        <i class="bi bi-file-earmark-lock me-1"></i> Menggunakan GEMINI_API_KEY dari .env
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="input-group mb-2">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="gemini_api_key" id="geminiApiKeyInput" 
                                       class="form-control border-start-0 font-monospace fs-7" 
                                       autocomplete="new-password"
                                       placeholder="<?= $isApiKeyConfigured ? '•••••••••••••••• (Tersimpan - Kosongkan jika tidak ingin mengubah kunci)' : 'Masukkan API Key Gemini baru' ?>">
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleKeyVisibility" title="Lihat/Sembunyikan">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-2">
                                <div class="fs-8 text-muted">
                                    <i class="bi bi-shield-check text-success me-1"></i> API Key yang disimpan melalui form ini dienkripsi AES-256-CBC + HMAC-SHA256 di database dan tidak pernah dikirim kembali ke browser client.
                                    <?php if ($apiKeySource === 'env'): ?>
                                        <br><span class="text-info"><i class="bi bi-info-circle me-1"></i>Kunci aktif saat ini dibaca dari variabel GEMINI_API_KEY di file .env dan tidak ditampilkan di halaman ini.</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isStoredApiKeyConfigured): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_api_key" value="1" id="removeApiKeyCheck">
                                        <label class="form-check-label text-danger fs-8 fw-semibold" for="removeApiKeyCheck">
                                            Hapus API Key tersimpan
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi & Uji Koneksi -->
                <div class="border-top pt-4 mt-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <button type="button" class="btn btn-outline-success px-4 py-2 rounded-pill fw-semibold shadow-sm" id="btnTestAiConnection">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="testAiSpinner" role="status"></span>
                            <i class="bi bi-broadcast me-1" id="testAiIcon"></i> Uji Koneksi AI Sekarang
                        </button>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Konfigurasi Chatbot
                        </button>
                    </div>
                </div>
            </form>

            <!-- Box Hasil Pengujian Koneksi AI -->
            <div id="aiTestResultBox" class="mt-4 d-none">
                <div class="card border-0 rounded-4 p-3" id="aiTestResultCard">
                    <div class="d-flex align-items-start gap-3">
                        <div id="aiTestStatusIcon" class="fs-3"></div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1" id="aiTestTitle">Hasil Uji Koneksi</h6>
                            <p class="mb-2 fs-7" id="aiTestMessage"></p>
                            <div class="d-flex flex-wrap gap-2 fs-8 font-monospace text-muted" id="aiTestMeta"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- KATEGORI C: PENGATURAN AKADEMIK                                         -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $activeTab === 'akademik' ? 'show active' : '' ?>" id="tab-akademik" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="border-bottom pb-3 mb-4">
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-mortarboard text-primary me-2"></i>C. Pengaturan Parameter Akademik & Presensi</h5>
                <p class="text-muted mb-0 fs-7">Atur periode semester aktif, batas toleransi keterlambatan, syarat kehadiran UAS, serta geofencing lokasi kampus.</p>
            </div>

            <form action="<?= base_url('admin/konfigurasi/akademik') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <!-- Tahun Akademik & Semester Aktif -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Tahun Akademik & Semester Aktif <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-calendar-check"></i></span>
                            <select name="tahun_akademik_id" class="form-select border-start-0" required>
                                <?php foreach ($daftarTahunAkademik as $th): ?>
                                    <option value="<?= $th['id'] ?>" <?= ((int)$activeTahunId === (int)$th['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($th['nama_tahun']) ?> (Semester <?= ucfirst($th['semester']) ?>) <?= $th['is_active'] ? '— [AKTIF SAAT INI]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="text-muted fs-8">Menentukan periode perkuliahan, jadwal kelas, dan pertemuan aktif.</small>
                    </div>

                    <!-- Toleransi Keterlambatan -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-dark fs-7">Toleransi Keterlambatan <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="default_late_tolerance" class="form-control" 
                                   value="<?= (int)($grouped['akademik']['default_late_tolerance']['value'] ?? $config['academic']['default_late_tolerance'] ?? 15) ?>" 
                                   min="0" max="120" required>
                            <span class="input-group-text bg-light text-muted fs-7">Menit</span>
                        </div>
                        <small class="text-muted fs-8">Nilai bawaan untuk jadwal yang belum memiliki toleransi khusus. Presensi setelah batas ini berstatus 'Terlambat'.</small>
                    </div>

                    <!-- Minimal Kehadiran UAS -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-dark fs-7">Syarat Kehadiran UAS <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="min_attendance_percent" class="form-control" 
                                   value="<?= (float)($grouped['akademik']['min_attendance_percent']['value'] ?? $config['academic']['min_attendance_percent'] ?? 75.0) ?>" 
                                   min="50" max="100" step="1" required>
                            <span class="input-group-text bg-light text-muted fs-7">%</span>
                        </div>
                        <small class="text-muted fs-8">Batas kehadiran minimal untuk syarat ikut UAS.</small>
                    </div>

                    <!-- Header Koordinat & Geofence -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Parameter Geofencing Kampus STMIK IKMI Cirebon</h6>
                            <p class="text-muted fs-8 mb-3">Koordinat pusat kampus digunakan oleh fungsi validasi geolocation Haversine pada presensi mahasiswa.</p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark fs-7">Radius Geofencing <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="radius_meter" class="form-control font-monospace" 
                                               value="<?= (int)$campus['radius_meter'] ?>" min="10" max="5000" required>
                                        <span class="input-group-text bg-white text-muted fs-7">Meter</span>
                                    </div>
                                    <small class="text-muted fs-8">Jarak radius maksimum mahasiswa dari titik pusat.</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark fs-7">Latitude Pusat Kampus <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" name="latitude" class="form-control font-monospace" 
                                           value="<?= (float)$campus['latitude'] ?>" required>
                                    <small class="text-muted fs-8">Koordinat garis lintang kampus.</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark fs-7">Longitude Pusat Kampus <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" name="longitude" class="form-control font-monospace" 
                                           value="<?= (float)$campus['longitude'] ?>" required>
                                    <small class="text-muted fs-8">Koordinat garis bujur kampus.</small>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold text-dark fs-7">Alamat Fisik Kampus</label>
                                    <input type="text" name="alamat" class="form-control" 
                                           value="<?= htmlspecialchars((string)($campus['alamat'] ?? '')) ?>" 
                                           placeholder="Jl. Perjuangan No.10B, Karyamulya, Kesambi, Cirebon">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark fs-7">AI Face Similarity Threshold <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="toleransi_wajah_similarity" class="form-control font-monospace" 
                                               value="<?= (float)($campus['toleransi_wajah_similarity'] ?? 0.55) ?>" min="0.20" max="0.90" step="0.01" required>
                                        <span class="input-group-text bg-white text-muted fs-7">Score</span>
                                    </div>
                                    <small class="text-muted fs-8">Jarak Euclidean distance face-api.js (Default: 0.55).</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan Akademik
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- KATEGORI D: PENGATURAN SISTEM                                          -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $activeTab === 'sistem' ? 'show active' : '' ?>" id="tab-sistem" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="border-bottom pb-3 mb-4">
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-hdd-network text-primary me-2"></i>D. Pengaturan Sistem & Keamanan Operasional</h5>
                <p class="text-muted mb-0 fs-7">Pengaturan mode pemeliharaan darurat, masa berlaku sesi autentikasi, serta diagnostik server.</p>
            </div>

            <form action="<?= base_url('admin/konfigurasi/sistem') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <!-- Mode Pemeliharaan (Maintenance Mode) -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0">
                                <div>
                                    <label class="form-check-label fw-bold text-dark fs-7 mb-1" for="maintenanceSwitch">
                                        <i class="bi bi-cone-striped text-warning me-1"></i> Mode Pemeliharaan (Maintenance Mode)
                                    </label>
                                    <p class="text-muted fs-8 mb-0">
                                        Jika diaktifkan, seluruh pengguna umum (Dosen & Mahasiswa) akan dialihkan ke halaman pemeliharaan 503. Administrator tetap memiliki akses penuh.
                                    </p>
                                </div>
                                <input class="form-check-input ms-3 fs-4" type="checkbox" role="switch" name="maintenance_mode" value="1" 
                                       id="maintenanceSwitch" <?= !empty($config['maintenance_mode']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <!-- Batas Waktu Sesi (Session Lifetime) -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Batas Waktu Aktif Sesi Pengguna <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-hourglass-split"></i></span>
                            <input type="number" name="session_lifetime_minutes" class="form-control border-start-0" 
                                   value="<?= (int)round(($config['session_lifetime'] ?? 7200) / 60) ?>" 
                                   min="15" max="1440" required>
                            <span class="input-group-text bg-light text-muted fs-7">Menit</span>
                        </div>
                        <small class="text-muted fs-8">Pengguna otomatis logout jika tidak aktif selama periode ini (Default: 120 menit / 2 jam).</small>
                    </div>

                    <!-- Mode Debugging -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark fs-7">Mode Debugging Aplikasi (App Debug)</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input fs-5" type="checkbox" role="switch" name="app_debug" value="1" 
                                   id="debugSwitch" <?= !empty($config['debug']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-7 text-dark ms-2" for="debugSwitch">
                                Tampilkan pesan rincian error PHP untuk penelusuran bug
                            </label>
                        </div>
                        <small class="text-muted fs-8">Disarankan nonaktif di lingkungan produksi untuk keamanan.</small>
                    </div>

                    <!-- Status Diagnostik Server -->
                    <div class="col-12">
                        <div class="card border-0 bg-light rounded-4 p-3">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-1"></i> Informasi Lingkungan & Server</h6>
                            <div class="row g-2 fs-8 font-monospace">
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">PHP Version:</span> <span class="badge bg-secondary-subtle text-secondary"><?= $serverInfo['php_version'] ?></span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">cURL Library:</span> 
                                    <span class="badge <?= $serverInfo['curl_enabled'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= $serverInfo['curl_enabled'] ? 'Tersedia (Ready)' : 'Tidak Tersedia' ?>
                                    </span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">OpenSSL Encryption:</span> 
                                    <span class="badge <?= $serverInfo['openssl'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= $serverInfo['openssl'] ? 'Aktif (AES-256)' : 'Tidak Aktif' ?>
                                    </span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Max Upload Limit:</span> <span class="fw-bold text-dark"><?= $serverInfo['max_upload'] ?></span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">POST Max Size:</span> <span class="fw-bold text-dark"><?= $serverInfo['post_max_size'] ?></span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Waktu Server Saat Ini:</span> <span class="fw-bold text-dark"><?= $serverInfo['server_time'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan Sistem
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- KATEGORI E: NOTIFIKASI WHATSAPP (FONNTE)                               -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $activeTab === 'whatsapp' ? 'show active' : '' ?>" id="tab-whatsapp" role="tabpanel">
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
                    <div class="border-bottom pb-3 mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-whatsapp text-success me-2"></i>E. Notifikasi WhatsApp (Fonnte)
                                </h5>
                                <p class="text-muted mb-0 fs-7">Integrasi resmi Fonnte untuk pengingat jadwal mahasiswa & dosen, konfirmasi absensi, pemberitahuan ketidakhadiran, notifikasi admin, dan pengumuman.</p>
                            </div>
                            <?php if ($whatsappIntegrationActive): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i> Integrasi Aktif
                                </span>
                            <?php elseif ($whatsappTokenConfigured): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-2">
                                    <i class="bi bi-pause-circle me-1"></i> Nonaktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                                    <i class="bi bi-key me-1"></i> Token Belum Ada
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="alert alert-light border rounded-4 p-3 mb-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-shield-lock-fill text-primary fs-4"></i>
                            <div class="fs-8 text-muted">
                                Token disimpan terenkripsi di server melalui mekanisme <code>AppSetting</code> yang sudah ada.
                                Token tidak pernah dikirim ke browser, log, atau audit trail. Endpoint dikunci ke
                                <code><?= htmlspecialchars($whatsappEndpoint) ?></code> dan redirect tidak diikuti.
                            </div>
                        </div>
                    </div>

                    <form action="<?= base_url('admin/konfigurasi/whatsapp') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input fs-5" type="checkbox" role="switch" name="whatsapp_enabled" value="1"
                                       id="whatsappEnabledSwitch" <?= $whatsappEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-dark ms-2" for="whatsappEnabledSwitch">
                                    Aktifkan integrasi WhatsApp
                                </label>
                            </div>
                            <small class="text-muted fs-8">Jika nonaktif, tidak ada notifikasi otomatis yang dikirim meskipun token tersimpan.</small>
                        </div>

                        <div class="mb-4 border rounded-4 p-3 <?= $whatsappSimulationMode ? 'border-warning-subtle bg-warning-subtle' : 'bg-light-subtle' ?>">
                            <div class="form-check form-switch">
                                <input class="form-check-input fs-5" type="checkbox" role="switch" name="whatsapp_simulation_mode" value="1"
                                       id="whatsappSimulationSwitch" <?= $whatsappSimulationMode ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-dark ms-2" for="whatsappSimulationSwitch">
                                    Mode simulasi (tidak mengirim pesan nyata)
                                </label>
                            </div>
                            <small class="text-muted fs-8 d-block mt-1">
                                Saat aktif, worker tetap memproses antrean tetapi pesan hanya dicatat sebagai <strong>simulasi</strong> dan tidak dikirim ke Fonnte.
                                Matikan hanya setelah Anda siap menerima pengiriman nyata.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark fs-7">Token Fonnte</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" id="fonnteTokenInput" name="fonnte_token"
                                       class="form-control border-start-0 border-end-0"
                                       autocomplete="new-password" spellcheck="false"
                                       placeholder="<?= $whatsappTokenConfigured ? '•••••••• (token tersimpan; kosongkan untuk mempertahankan)' : 'Masukkan token perangkat Fonnte' ?>">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="btnToggleWhatsappToken" title="Tampilkan/sembunyikan input token">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
                                <small class="text-muted fs-8">
                                    Status:
                                    <?php if ($whatsappTokenConfigured): ?>
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-lock-fill me-1"></i>Tersimpan terenkripsi</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Belum dikonfigurasi</span>
                                    <?php endif; ?>
                                </small>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_fonnte_token" value="1" id="removeFonnteToken">
                                    <label class="form-check-label fs-8 text-danger" for="removeFonnteToken">Hapus token tersimpan</label>
                                </div>
                            </div>
                            <small class="text-muted fs-8 d-block mt-1">Kosongkan kolom token jika tidak ingin mengubah token yang sudah ada.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark fs-7">Jenis Notifikasi yang Diaktifkan</label>

                            <div class="text-uppercase text-muted fs-8 fw-bold mb-2 mt-1">Mahasiswa</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_schedule_reminder_enabled" value="1" id="waScheduleReminder" <?= $whatsappTypeEnabled['schedule_reminder'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waScheduleReminder">Pengingat jadwal</label>
                                        </div>
                                        <small class="text-muted fs-8">Dikirim ke mahasiswa aktif yang terdaftar pada jadwal, sebelum kelas dimulai.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_attendance_confirmation_enabled" value="1" id="waAttendanceConfirmation" <?= $whatsappTypeEnabled['attendance_confirmation'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAttendanceConfirmation">Konfirmasi absensi</label>
                                        </div>
                                        <small class="text-muted fs-8">Dikirim setelah absensi berhasil dicatat; kegagalan WhatsApp tidak membatalkan absensi.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_absence_notice_enabled" value="1" id="waAbsenceNotice" <?= $whatsappTypeEnabled['absence_notice'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAbsenceNotice">Pemberitahuan ketidakhadiran</label>
                                        </div>
                                        <small class="text-muted fs-8">Hanya setelah pertemuan ditutup dan status alpha final. Tidak menyimpulkan sebelum sesi berakhir.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_low_attendance_enabled" value="1" id="waLowAttendance" <?= $whatsappTypeEnabled['low_attendance'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waLowAttendance">Peringatan kehadiran rendah</label>
                                        </div>
                                        <small class="text-muted fs-8">Maksimal sekali per mata kuliah/mahasiswa per minggu, berdasarkan ambang akademik.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-uppercase text-muted fs-8 fw-bold mb-2">Dosen</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_lecturer_teaching_reminder_enabled" value="1" id="waLecturerTeachingReminder" <?= $whatsappTypeEnabled['lecturer_teaching_reminder'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waLecturerTeachingReminder">Pengingat mengajar</label>
                                        </div>
                                        <small class="text-muted fs-8">Dikirim ke dosen sebelum jadwal mengajar, memakai nomor pada profil dosen.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_lecturer_attendance_enabled" value="1" id="waLecturerAttendance" <?= $whatsappTypeEnabled['lecturer_attendance'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waLecturerAttendance">Informasi absensi kelas</label>
                                        </div>
                                        <small class="text-muted fs-8">Hanya untuk kelas yang diampu dosen tersebut. Pilih mode di bawah.</small>
                                        <div class="mt-2">
                                            <label class="form-label fs-8 text-muted mb-1" for="whatsappLecturerMode">Mode notifikasi</label>
                                            <select class="form-select form-select-sm" id="whatsappLecturerMode" name="whatsapp_lecturer_attendance_mode">
                                                <option value="per_student" <?= $whatsappLecturerMode === 'per_student' ? 'selected' : '' ?>>Per mahasiswa (setiap absensi)</option>
                                                <option value="summary" <?= $whatsappLecturerMode === 'summary' ? 'selected' : '' ?>>Ringkasan per pertemuan (kurangi pesan)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_lecturer_recap_enabled" value="1" id="waLecturerRecap" <?= $whatsappTypeEnabled['lecturer_recap'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waLecturerRecap">Rekap mingguan dosen</label>
                                        </div>
                                        <small class="text-muted fs-8">Rekap agregat per jadwal, tanpa data pribadi mahasiswa.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-uppercase text-muted fs-8 fw-bold mb-2">Admin & Pengumuman</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_admin_attendance_alert_enabled" value="1" id="waAdminAttendanceAlert" <?= $whatsappTypeEnabled['admin_attendance_alert'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAdminAttendanceAlert">Aktivitas absensi penting</label>
                                        </div>
                                        <small class="text-muted fs-8">Absensi terlambat atau alpha yang membutuhkan perhatian admin.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_admin_approval_enabled" value="1" id="waAdminApproval" <?= $whatsappTypeEnabled['admin_approval'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAdminApproval">Pengajuan menunggu persetujuan</label>
                                        </div>
                                        <small class="text-muted fs-8">Izin/sakit baru yang masuk ke antrean persetujuan admin.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_admin_whatsapp_failure_enabled" value="1" id="waAdminFailure" <?= $whatsappTypeEnabled['admin_whatsapp_failure'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAdminFailure">Kegagalan pengiriman WhatsApp</label>
                                        </div>
                                        <small class="text-muted fs-8">Dikirim saat pesan gagal permanen, maksimal sekali per jam per jenis.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_admin_device_issue_enabled" value="1" id="waAdminDevice" <?= $whatsappTypeEnabled['admin_device_issue'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAdminDevice">Gangguan perangkat Fonnte</label>
                                        </div>
                                        <small class="text-muted fs-8">Dikirim saat perangkat WhatsApp Fonnte terputus.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_admin_attendance_summary_enabled" value="1" id="waAdminSummary" <?= $whatsappTypeEnabled['admin_attendance_summary'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAdminSummary">Ringkasan absensi harian</label>
                                        </div>
                                        <small class="text-muted fs-8">Ringkasan data kemarin (sudah final) untuk admin.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_announcement_enabled" value="1" id="waAnnouncement" <?= $whatsappTypeEnabled['announcement'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold fs-7" for="waAnnouncement">Pengumuman WhatsApp</label>
                                        </div>
                                        <small class="text-muted fs-8">Mengizinkan admin mengirim pengumuman ke penerima terpilih.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark fs-7" for="whatsappReminderMinutes">Pengingat sebelum kelas</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="whatsappReminderMinutes" name="whatsapp_reminder_minutes"
                                           min="5" max="180" step="5" value="<?= (int)$whatsappReminderMinutes ?>" required>
                                    <span class="input-group-text">menit</span>
                                </div>
                                <small class="text-muted fs-8">Rentang 5–180 menit.</small>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark fs-7">Ambang kehadiran rendah</label>
                                <div class="border rounded-3 px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark"><?= number_format($minAttendancePercent, 2, ',', '.') ?>%</span>
                                    <a href="<?= base_url('admin/konfigurasi?tab=akademik') ?>" class="fs-8 text-decoration-none">Ubah di Aturan Akademik</a>
                                </div>
                                <small class="text-muted fs-8">Menggunakan <code>min_attendance_percent</code> yang sudah ada; tidak membuat aturan duplikat.</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="accordion" id="waTemplateAccordion">
                                <div class="accordion-item border rounded-4 overflow-hidden">
                                    <h2 class="accordion-header" id="waTemplateHeading">
                                        <button class="accordion-button collapsed fw-semibold fs-7" type="button" data-bs-toggle="collapse" data-bs-target="#waTemplateCollapse" aria-expanded="false" aria-controls="waTemplateCollapse">
                                            <i class="bi bi-chat-square-text me-2 text-primary"></i> Template Pesan (opsional)
                                        </button>
                                    </h2>
                                    <div id="waTemplateCollapse" class="accordion-collapse collapse" aria-labelledby="waTemplateHeading" data-bs-parent="#waTemplateAccordion">
                                        <div class="accordion-body">
                                            <div class="alert alert-light border rounded-3 fs-8 text-muted mb-3">
                                                Gunakan placeholder seperti <code>{nama_mhs}</code>, <code>{nama_dosen}</code>, <code>{nama_mk}</code>,
                                                <code>{nama_kelas}</code>, <code>{hari_tanggal}</code>, <code>{jam_mulai}</code>, <code>{jam_selesai}</code>,
                                                <code>{ruangan}</code>, <code>{status}</code>, <code>{tanggal}</code>, <code>{jam_absensi}</code>,
                                                <code>{menit_sebelum}</code>, <code>{pertemuan_ke}</code>, <code>{total_hadir}</code>,
                                                <code>{total_terlambat}</code>, <code>{total_izin}</code>, <code>{total_sakit}</code>,
                                                <code>{total_alpha}</code>, <code>{total_belum}</code>, <code>{judul}</code>, <code>{pesan}</code>,
                                                dan <code>{app_name}</code>. Kosongkan untuk memakai template bawaan.
                                            </div>
                                            <?php
                                            $waTemplateFields = [
                                                'schedule_reminder' => ['Pengingat jadwal mahasiswa', 'whatsapp_template_schedule_reminder'],
                                                'attendance_confirmation' => ['Konfirmasi absensi mahasiswa', 'whatsapp_template_attendance_confirmation'],
                                                'absence_notice' => ['Pemberitahuan ketidakhadiran', 'whatsapp_template_absence_notice'],
                                                'lecturer_teaching_reminder' => ['Pengingat mengajar dosen', 'whatsapp_template_lecturer_teaching_reminder'],
                                                'lecturer_attendance' => ['Absensi mahasiswa ke dosen', 'whatsapp_template_lecturer_attendance'],
                                                'lecturer_attendance_summary' => ['Ringkasan kehadiran dosen', 'whatsapp_template_lecturer_attendance_summary'],
                                                'announcement' => ['Pengumuman', 'whatsapp_template_announcement'],
                                                'admin_alert' => ['Notifikasi admin (aktivitas, approval, kegagalan, perangkat)', 'whatsapp_template_admin_alert'],
                                                'admin_attendance_summary' => ['Ringkasan absensi harian admin', 'whatsapp_template_admin_attendance_summary'],
                                            ];
                                            ?>
                                            <?php foreach ($waTemplateFields as $waTemplateField): ?>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold fs-8 text-dark mb-1" for="<?= htmlspecialchars($waTemplateField[1], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($waTemplateField[0], ENT_QUOTES, 'UTF-8') ?>
                                                    </label>
                                                    <textarea class="form-control font-monospace fs-8" rows="4"
                                                              id="<?= htmlspecialchars($waTemplateField[1], ENT_QUOTES, 'UTF-8') ?>"
                                                              name="<?= htmlspecialchars($waTemplateField[1], ENT_QUOTES, 'UTF-8') ?>"
                                                              maxlength="4000"><?= htmlspecialchars((string) ($whatsappTemplates[$waTemplateField[1]] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 mt-4 text-end">
                            <button type="submit" class="btn btn-success px-4 py-2 rounded-pill fw-semibold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Simpan Konfigurasi WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-5">
                <?php
                $waFormatTime = static function (?string $value): string {
                    if ($value === null || $value === '') {
                        return 'Belum ada';
                    }
                    $ts = strtotime($value);
                    return $ts === false ? $value : date('d/m/Y H:i', $ts);
                };
                ?>
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-send-check text-success me-1"></i> Uji Kirim / Simulasi</h6>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Manual</span>
                    </div>
                    <p class="text-muted fs-8 mb-3">
                        Pesan uji hanya diproses setelah Anda mencentang konfirmasi dan menekan tombol. Halaman ini tidak mengirim pesan saat dimuat.
                    </p>
                    <?php if ($whatsappSimulationMode): ?>
                        <div class="alert alert-warning border-0 rounded-3 fs-8 py-2">
                            <i class="bi bi-shield-check me-1"></i> Mode simulasi aktif: pesan uji tidak akan dikirim ke Fonnte.
                        </div>
                    <?php endif; ?>

                    <form id="formTestWhatsApp" action="<?= base_url('admin/konfigurasi/test-whatsapp') ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-7" for="whatsappTestRecipient">Nomor tujuan uji</label>
                            <input type="tel" class="form-control" id="whatsappTestRecipient" name="test_recipient"
                                   placeholder="08xxxxxxxxxx" autocomplete="off" inputmode="tel"
                                   <?= !$whatsappTokenConfigured ? 'disabled' : '' ?>>
                            <small class="text-muted fs-8">Nomor dipilih sendiri oleh admin; tidak disimpan sebagai nomor lengkap di outbox.</small>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="whatsappTestConfirm" name="test_confirm" value="1">
                            <label class="form-check-label fs-8 text-dark" for="whatsappTestConfirm">
                                Saya memahami ini mengirim satu pesan WhatsApp nyata ke nomor tersebut.
                            </label>
                        </div>
                        <button type="button" class="btn btn-outline-success w-100 rounded-pill fw-semibold"
                                id="btnTestWhatsApp" <?= !$whatsappTokenConfigured ? 'disabled' : '' ?>>
                            <span id="whatsappTestSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                            <i class="bi bi-whatsapp me-1" id="whatsappTestIcon"></i> Kirim Pesan Uji
                        </button>
                        <?php if (!$whatsappTokenConfigured): ?>
                            <small class="text-danger fs-8 d-block mt-2">Simpan token Fonnte terlebih dahulu untuk mengaktifkan tombol uji.</small>
                        <?php endif; ?>
                    </form>

                    <div id="whatsappTestResultBox" class="mt-3 d-none">
                        <div id="whatsappTestResultCard" class="card border-0 rounded-4 p-3 bg-light">
                            <div class="d-flex align-items-start gap-2">
                                <span id="whatsappTestStatusIcon"><i class="bi bi-info-circle-fill text-secondary"></i></span>
                                <div>
                                    <div id="whatsappTestTitle" class="fw-bold fs-7 text-dark">Menunggu</div>
                                    <div id="whatsappTestMessage" class="fs-8 text-muted"></div>
                                    <div id="whatsappTestMeta" class="mt-2 d-flex flex-wrap gap-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 rounded-4 mt-3 mb-0 fs-8">
                        <i class="bi bi-info-circle me-1"></i>
                        Respons sukses dari Fonnte berarti pesan <strong>masuk antrean API</strong>, bukan bukti pesan sudah diterima atau dibaca penerima. Status pengiriman akhir bergantung pada perangkat WhatsApp Fonnte.
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-phone text-primary me-1"></i> Status Perangkat Fonnte</h6>
                        <span class="badge bg-secondary-subtle text-secondary">Manual</span>
                    </div>
                    <p class="text-muted fs-8 mb-3">
                        Pemeriksaan hanya membaca status perangkat. Tidak mengirim pesan dan tidak menampilkan token.
                    </p>
                    <button type="button" class="btn btn-outline-primary w-100 rounded-pill fw-semibold" id="btnCheckWaDevice" <?= !$whatsappTokenConfigured ? 'disabled' : '' ?>>
                        <span id="waDeviceSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                        <i class="bi bi-arrow-repeat me-1" id="waDeviceIcon"></i> Periksa Status Perangkat
                    </button>
                    <div id="waDeviceResult" class="mt-3 d-none">
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="d-flex align-items-start gap-2">
                                <span id="waDeviceStatusIcon"><i class="bi bi-info-circle-fill text-secondary"></i></span>
                                <div>
                                    <div id="waDeviceTitle" class="fw-bold fs-7 text-dark">Menunggu</div>
                                    <div id="waDeviceMessage" class="fs-8 text-muted"></div>
                                    <div id="waDeviceMeta" class="mt-2 d-flex flex-wrap gap-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$whatsappTokenConfigured): ?>
                        <small class="text-danger fs-8 d-block mt-2">Simpan token Fonnte terlebih dahulu.</small>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-megaphone text-warning me-1"></i> Pengumuman WhatsApp</h6>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Admin</span>
                    </div>
                    <p class="text-muted fs-8 mb-3">
                        Pengumuman hanya masuk antrean setelah Anda menekan kirim. Penerima diambil dari data aktif yang memiliki nomor WhatsApp.
                    </p>
                    <form action="<?= base_url('admin/konfigurasi/announcement') ?>" method="POST" id="formWaAnnouncement">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label fw-semibold fs-8" for="waAnnouncementTitle">Judul</label>
                            <input type="text" class="form-control form-control-sm" id="waAnnouncementTitle" name="judul" maxlength="120" required placeholder="Contoh: Perubahan jadwal UTS">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold fs-8" for="waAnnouncementBody">Isi pengumuman</label>
                            <textarea class="form-control form-control-sm" id="waAnnouncementBody" name="pesan" rows="4" maxlength="2000" required placeholder="Tulis informasi yang akan dikirim..."></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold fs-8" for="waAnnouncementTarget">Sasaran</label>
                            <select class="form-select form-select-sm" id="waAnnouncementTarget" name="target_type" required>
                                <option value="all_mahasiswa">Semua mahasiswa aktif (<?= (int) ($whatsappRecipientStats['mahasiswa'] ?? 0) ?>)</option>
                                <option value="all_dosen">Semua dosen aktif (<?= (int) ($whatsappRecipientStats['dosen'] ?? 0) ?>)</option>
                                <option value="all_users">Semua mahasiswa & dosen</option>
                                <option value="kelas">Kelas tertentu</option>
                                <option value="mahasiswa">Mahasiswa terpilih (ID)</option>
                                <option value="dosen">Dosen terpilih (ID)</option>
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="waAnnouncementClassWrap">
                            <label class="form-label fw-semibold fs-8" for="waAnnouncementClass">Kelas</label>
                            <select class="form-select form-select-sm" id="waAnnouncementClass" name="target_ref_class">
                                <option value="">Pilih kelas</option>
                                <?php foreach (($whatsappClasses ?? []) as $waKelas): ?>
                                    <option value="<?= (int) $waKelas['id'] ?>"><?= htmlspecialchars((string) $waKelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="waAnnouncementIdsWrap">
                            <label class="form-label fw-semibold fs-8" for="waAnnouncementIds">Daftar ID penerima</label>
                            <input type="text" class="form-control form-control-sm" id="waAnnouncementIds" name="target_ref_ids" placeholder="Contoh: 1,2,3" inputmode="numeric">
                            <small class="text-muted fs-8">Gunakan ID profil mahasiswa/dosen, bukan NIM/NIDN atau nomor telepon.</small>
                        </div>
                        <input type="hidden" name="target_ref" id="waAnnouncementTargetRef" value="">
                        <button type="submit" class="btn btn-warning w-100 rounded-pill fw-semibold mt-2" <?= !$whatsappIntegrationActive ? 'disabled' : '' ?>>
                            <i class="bi bi-send me-1"></i> Masukkan Pengumuman ke Antrean
                        </button>
                        <?php if (!$whatsappIntegrationActive): ?>
                            <small class="text-danger fs-8 d-block mt-2">Aktifkan integrasi dan simpan token terlebih dahulu.</small>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($whatsappAnnouncements)): ?>
                        <hr class="my-3">
                        <h6 class="fw-bold fs-8 text-muted text-uppercase mb-2">Riwayat Pengumuman</h6>
                        <div class="list-group list-group-flush">
                            <?php foreach ($whatsappAnnouncements as $waAnnouncement): ?>
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="fw-semibold fs-8 text-dark"><?= htmlspecialchars((string) ($waAnnouncement['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <span class="badge bg-light text-secondary"><?= (int) ($waAnnouncement['recipient_count'] ?? 0) ?> penerima</span>
                                    </div>
                                    <div class="text-muted fs-8">
                                        <?= htmlspecialchars((string) ($waAnnouncement['target_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($waFormatTime($waAnnouncement['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-1"></i> Antrean & Penjadwalan</h6>
                    <?php
                    $waTypeLabels = [
                        'schedule_reminder' => 'Pengingat jadwal',
                        'attendance_confirmation' => 'Konfirmasi absensi',
                        'absence_notice' => 'Ketidakhadiran',
                        'low_attendance' => 'Kehadiran rendah',
                        'lecturer_teaching_reminder' => 'Pengingat mengajar',
                        'lecturer_attendance' => 'Absensi ke dosen',
                        'lecturer_attendance_summary' => 'Ringkasan dosen',
                        'lecturer_recap' => 'Rekap dosen',
                        'admin_attendance_alert' => 'Peringatan admin',
                        'admin_approval' => 'Approval admin',
                        'admin_whatsapp_failure' => 'Kegagalan WA',
                        'admin_device_issue' => 'Gangguan perangkat',
                        'admin_attendance_summary' => 'Ringkasan harian',
                        'announcement' => 'Pengumuman',
                        'test' => 'Uji manual',
                    ];
                    $waStatusClasses = [
                        'pending' => 'bg-secondary-subtle text-secondary',
                        'processing' => 'bg-info-subtle text-info-emphasis',
                        'accepted' => 'bg-success-subtle text-success',
                        'failed' => 'bg-danger-subtle text-danger',
                        'skipped' => 'bg-warning-subtle text-warning-emphasis',
                    ];
                    $waStatusLabels = [
                        'pending' => 'Pending',
                        'processing' => 'Diproses',
                        'accepted' => 'Diterima API',
                        'failed' => 'Gagal',
                        'skipped' => 'Dilewati',
                    ];
                    ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-secondary-subtle text-secondary">Pending: <?= (int)($whatsappQueueCounts['pending'] ?? 0) ?></span>
                        <span class="badge bg-info-subtle text-info-emphasis">Processing: <?= (int)($whatsappQueueCounts['processing'] ?? 0) ?></span>
                        <span class="badge bg-success-subtle text-success">Diterima API: <?= (int)($whatsappQueueCounts['accepted'] ?? 0) ?></span>
                        <span class="badge bg-danger-subtle text-danger">Gagal: <?= (int)($whatsappQueueCounts['failed'] ?? 0) ?></span>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Dilewati: <?= (int)($whatsappQueueCounts['skipped'] ?? 0) ?></span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-2 h-100 bg-light">
                                <div class="text-muted fs-8">Pengiriman terakhir diterima API</div>
                                <div class="fw-semibold fs-7"><?= htmlspecialchars($waFormatTime($whatsappLastSend['last_accepted_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-2 h-100 bg-light">
                                <div class="text-muted fs-8">Percobaan pengiriman terakhir</div>
                                <div class="fw-semibold fs-7"><?= htmlspecialchars($waFormatTime($whatsappLastSend['last_attempt_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark fs-7 mb-2"><i class="bi bi-clock-history me-1"></i> Riwayat Notifikasi Terbaru</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-8">Waktu</th>
                                    <th class="fs-8">Jenis</th>
                                    <th class="fs-8">Penerima</th>
                                    <th class="fs-8">Jadwal</th>
                                    <th class="fs-8">Status</th>
                                    <th class="fs-8">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($whatsappRecent)): ?>
                                <tr><td colspan="6" class="text-center text-muted fs-8 py-3">Belum ada riwayat notifikasi.</td></tr>
                            <?php else: ?>
                                <?php foreach ($whatsappRecent as $waRow): ?>
                                    <?php
                                    $waPayload = [];
                                    if (!empty($waRow['payload_json'])) {
                                        $decodedPayload = json_decode((string) $waRow['payload_json'], true);
                                        $waPayload = is_array($decodedPayload) ? $decodedPayload : [];
                                    }
                                    $waRecipient = (string) ($waRow['mahasiswa_nama'] ?? $waRow['dosen_nama'] ?? $waRow['admin_nama'] ?? $waRow['recipient_ref'] ?? '-');
                                    $waIdentity = (string) ($waRow['nim'] ?? $waRow['nidn'] ?? $waRow['admin_username'] ?? '');
                                    $waSchedule = trim((string) ($waPayload['nama_mk'] ?? ''));
                                    $waDate = trim((string) ($waPayload['tanggal'] ?? ''));
                                    $waStatus = (string) ($waRow['status'] ?? '');
                                    $waSimulated = (int) ($waRow['simulated'] ?? 0) === 1;
                                    $waNote = (string) ($waRow['error_message'] ?? '');
                                    if ($waStatus === 'accepted' && $waSimulated) {
                                        $waNote = 'Simulasi: tidak dikirim ke Fonnte.';
                                    } elseif ($waStatus === 'accepted' && !empty($waRow['provider_message_id'])) {
                                        $waNote = 'Provider ID: ' . (string) $waRow['provider_message_id'];
                                    }
                                    ?>
                                    <tr>
                                        <td class="fs-8 text-nowrap"><?= htmlspecialchars($waFormatTime($waRow['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fs-8"><?= htmlspecialchars($waTypeLabels[(string) ($waRow['notification_type'] ?? '')] ?? (string) ($waRow['notification_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fs-8">
                                            <div class="fw-semibold"><?= htmlspecialchars($waRecipient !== '' ? $waRecipient : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if ($waIdentity !== ''): ?><div class="text-muted"><?= htmlspecialchars($waIdentity, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                        </td>
                                        <td class="fs-8">
                                            <div><?= htmlspecialchars($waSchedule !== '' ? $waSchedule : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if ($waDate !== ''): ?><div class="text-muted"><?= htmlspecialchars($waDate, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $waStatusClasses[$waStatus] ?? 'bg-light text-dark' ?>">
                                                <?= htmlspecialchars($waStatusLabels[$waStatus] ?? ($waStatus !== '' ? $waStatus : '-'), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if ($waSimulated): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis">Simulasi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fs-8 text-muted"><?= htmlspecialchars($waNote !== '' ? $waNote : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted fs-8 mb-2">
                        Notifikasi otomatis dimasukkan ke outbox dan dikirim oleh worker. Jalankan dari Laragon:
                    </p>
                    <pre class="bg-dark text-light rounded-3 p-2 fs-8 mb-2"><code>php app/Console/whatsapp.php run</code></pre>
                    <p class="text-muted fs-8 mb-2">
                        Worker memproses pengingat mahasiswa & dosen, menutup sesi yang sudah lewat jam tutup, mengirim pemberitahuan ketidakhadiran, ringkasan dosen/admin, dan antrean. Perintah lain:
                    </p>
                    <pre class="bg-dark text-light rounded-3 p-2 fs-8 mb-2"><code>php app/Console/whatsapp.php dispatch --limit=20
php app/Console/whatsapp.php status</code></pre>
                    <small class="text-muted fs-8 d-block mt-2">
                        Tanpa scheduler, pesan tetap tersimpan sebagai pending dan tidak terkirim otomatis.
                        Mode simulasi akan menandai pesan sebagai simulasi tanpa memanggil Fonnte.
                        Pembuatan Windows Task Scheduler tidak dilakukan otomatis oleh aplikasi.
                    </small>

                    <?php if (!empty($whatsappFailures)): ?>
                        <hr class="my-3">
                        <h6 class="fw-bold fs-8 text-muted text-uppercase mb-2">Kegagalan Terakhir</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fs-8">Waktu</th>
                                        <th class="fs-8">Jenis</th>
                                        <th class="fs-8">Penerima</th>
                                        <th class="fs-8">Penyebab</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($whatsappFailures as $waFailure): ?>
                                    <tr>
                                        <td class="fs-8 text-nowrap"><?= htmlspecialchars($waFormatTime($waFailure['last_attempt_at'] ?? $waFailure['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fs-8"><?= htmlspecialchars($waTypeLabels[(string) ($waFailure['notification_type'] ?? '')] ?? (string) ($waFailure['notification_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fs-8"><?= htmlspecialchars((string) ($waFailure['recipient_ref'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fs-8 text-danger"><?= htmlspecialchars((string) ($waFailure['error_message'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script Interaktif Uji Koneksi AI & Toggle Visibility -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Toggle Visibility API Key
    const toggleBtn = document.getElementById('btnToggleKeyVisibility');
    const apiKeyInput = document.getElementById('geminiApiKeyInput');
    if (toggleBtn && apiKeyInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = apiKeyInput.type === 'password';
            apiKeyInput.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    }

    // 2. Tombol Uji Koneksi AI (AJAX)
    const btnTest = document.getElementById('btnTestAiConnection');
    const spinner = document.getElementById('testAiSpinner');
    const icon = document.getElementById('testAiIcon');
    const resultBox = document.getElementById('aiTestResultBox');
    const resultCard = document.getElementById('aiTestResultCard');
    const statusIcon = document.getElementById('aiTestStatusIcon');
    const testTitle = document.getElementById('aiTestTitle');
    const testMessage = document.getElementById('aiTestMessage');
    const testMeta = document.getElementById('aiTestMeta');

    if (btnTest) {
        btnTest.addEventListener('click', function () {
            const model = document.getElementById('geminiModelInput')?.value || '';
            const baseUrl = document.getElementById('geminiBaseUrlInput')?.value || '';
            const apiKey = apiKeyInput?.value || '';

            // Loading state
            btnTest.disabled = true;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            resultBox.classList.add('d-none');

            const csrfInput = document.querySelector('input[name="_csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('gemini_model', model);
            formData.append('gemini_base_url', baseUrl);
            formData.append('gemini_api_key', apiKey);

            fetch('<?= base_url('admin/konfigurasi/test-ai') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnTest.disabled = false;
                spinner.classList.add('d-none');
                icon.classList.remove('d-none');
                resultBox.classList.remove('d-none');

                if (data.success) {
                    resultCard.className = 'card border-0 rounded-4 p-3 bg-success-subtle text-success border border-success-subtle';
                    statusIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                    testTitle.textContent = 'Koneksi AI Berhasil!';
                    testMessage.textContent = data.message;
                    testMeta.innerHTML = `
                        <span class="badge bg-success text-white">Model: ${data.model}</span>
                        <span class="badge bg-success-subtle text-success border border-success">Latency: ${data.latency_ms} ms</span>
                        <span class="badge bg-light text-dark border">HTTP Status: ${data.http_code}</span>
                    `;

                    Swal.fire({
                        icon: 'success',
                        title: 'Koneksi Berhasil!',
                        text: data.message,
                        timer: 3500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-4' }
                    });
                } else {
                    resultCard.className = 'card border-0 rounded-4 p-3 bg-danger-subtle text-danger border border-danger-subtle';
                    statusIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                    testTitle.textContent = 'Uji Koneksi Gagal';
                    testMessage.textContent = data.message;
                    testMeta.innerHTML = `
                        <span class="badge bg-danger text-white">HTTP ${data.http_code || 'Err'}</span>
                        <span class="badge bg-secondary-subtle text-secondary">Latency: ${data.latency_ms || 0} ms</span>
                    `;

                    Swal.fire({
                        icon: 'error',
                        title: 'Uji Koneksi Gagal',
                        text: data.message,
                        confirmButtonColor: '#1e3a8a',
                        customClass: { popup: 'rounded-4' }
                    });
                }
            })
            .catch(err => {
                btnTest.disabled = false;
                spinner.classList.add('d-none');
                icon.classList.remove('d-none');
                resultBox.classList.remove('d-none');

                resultCard.className = 'card border-0 rounded-4 p-3 bg-danger-subtle text-danger';
                statusIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                testTitle.textContent = 'Kendala Jaringan / Server';
                testMessage.textContent = 'Gagal menghubungi backend untuk pengujian: ' + err.message;
                testMeta.innerHTML = '';
            });
        });
    }

    // 3. Toggle token WhatsApp & kontrol hapus token
    const waTokenInput = document.getElementById('fonnteTokenInput');
    const waToggleToken = document.getElementById('btnToggleWhatsappToken');
    const waRemoveToken = document.getElementById('removeFonnteToken');
    if (waToggleToken && waTokenInput) {
        waToggleToken.addEventListener('click', function () {
            const isPassword = waTokenInput.type === 'password';
            waTokenInput.type = isPassword ? 'text' : 'password';
            waToggleToken.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    }
    if (waRemoveToken && waTokenInput) {
        waRemoveToken.addEventListener('change', function () {
            waTokenInput.disabled = waRemoveToken.checked;
            if (waRemoveToken.checked) {
                waTokenInput.value = '';
            }
        });
    }

    // 4. Uji WhatsApp: hanya setelah konfirmasi eksplisit admin.
    const waTestBtn = document.getElementById('btnTestWhatsApp');
    const waTestSpinner = document.getElementById('whatsappTestSpinner');
    const waTestIcon = document.getElementById('whatsappTestIcon');
    const waTestResultBox = document.getElementById('whatsappTestResultBox');
    const waTestResultCard = document.getElementById('whatsappTestResultCard');
    const waTestStatusIcon = document.getElementById('whatsappTestStatusIcon');
    const waTestTitle = document.getElementById('whatsappTestTitle');
    const waTestMessage = document.getElementById('whatsappTestMessage');
    const waTestMeta = document.getElementById('whatsappTestMeta');
    const waTestRecipient = document.getElementById('whatsappTestRecipient');
    const waTestConfirm = document.getElementById('whatsappTestConfirm');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });

    if (waTestBtn) {
        waTestBtn.addEventListener('click', async function () {
            const recipient = (waTestRecipient?.value || '').trim();
            if (!recipient) {
                Swal.fire({ icon: 'warning', title: 'Nomor belum diisi', text: 'Masukkan nomor tujuan uji terlebih dahulu.', customClass: { popup: 'rounded-4' } });
                return;
            }
            if (!waTestConfirm?.checked) {
                Swal.fire({ icon: 'warning', title: 'Konfirmasi diperlukan', text: 'Centang pernyataan bahwa Anda memahami pesan uji akan dikirim ke nomor tersebut.', customClass: { popup: 'rounded-4' } });
                return;
            }

            const confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Kirim pesan uji nyata?',
                text: 'Satu pesan WhatsApp akan dikirim ke nomor ' + recipient + '.',
                showCancelButton: true,
                confirmButtonText: 'Ya, kirim',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#198754',
                customClass: { popup: 'rounded-4' }
            });
            if (!confirmation.isConfirmed) {
                return;
            }

            const csrfInput = document.querySelector('#formTestWhatsApp input[name="_csrf_token"]');
            const formData = new FormData();
            formData.append('_csrf_token', csrfInput ? csrfInput.value : '');
            formData.append('test_recipient', recipient);
            formData.append('test_confirm', '1');

            waTestBtn.disabled = true;
            waTestSpinner?.classList.remove('d-none');
            waTestIcon?.classList.add('d-none');
            waTestResultBox?.classList.add('d-none');

            fetch('<?= base_url('admin/konfigurasi/test-whatsapp') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, data };
            })
            .then(({ ok, data }) => {
                waTestBtn.disabled = false;
                waTestSpinner?.classList.add('d-none');
                waTestIcon?.classList.remove('d-none');
                waTestResultBox?.classList.remove('d-none');

                const success = ok && data.success === true;
                const simulated = data.simulated === true;
                const cardClass = success
                    ? (simulated ? 'bg-warning-subtle border-warning-subtle' : 'bg-success-subtle border-success-subtle')
                    : 'bg-danger-subtle border-danger-subtle';
                waTestResultCard.className = 'card border-0 rounded-4 p-3 border ' + cardClass;
                waTestStatusIcon.innerHTML = success
                    ? (simulated
                        ? '<i class="bi bi-shield-check text-warning-emphasis"></i>'
                        : '<i class="bi bi-check-circle-fill text-success"></i>')
                    : '<i class="bi bi-x-circle-fill text-danger"></i>';
                waTestTitle.textContent = success
                    ? (simulated ? 'Simulasi Dicatat (Tidak Dikirim)' : 'Diterima API Fonnte (Antrean)')
                    : 'Uji Gagal';
                waTestMessage.textContent = data.message || 'Tidak ada keterangan dari server.';

                const meta = [];
                if (simulated) meta.push('<span class="badge bg-warning-subtle text-warning-emphasis">Mode simulasi</span>');
                if (data.http_code) meta.push('<span class="badge bg-light text-dark border">HTTP ' + escapeHtml(data.http_code) + '</span>');
                if (data.latency_ms) meta.push('<span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(data.latency_ms) + ' ms</span>');
                if (data.recipient_masked) meta.push('<span class="badge bg-light text-dark border">Tujuan: ' + escapeHtml(data.recipient_masked) + '</span>');
                if (data.request_id) meta.push('<span class="badge bg-info-subtle text-info-emphasis">Req: ' + escapeHtml(data.request_id) + '</span>');
                if (data.provider_status) meta.push('<span class="badge bg-success-subtle text-success">Status: ' + escapeHtml(data.provider_status) + '</span>');
                waTestMeta.innerHTML = meta.join('');

                Swal.fire({
                    icon: success ? (simulated ? 'info' : 'success') : 'error',
                    title: success ? (simulated ? 'Simulasi Tercatat' : 'Diterima API Fonnte') : 'Uji Gagal',
                    text: data.message || 'Periksa konfigurasi dan koneksi server.',
                    confirmButtonColor: '#1e3a8a',
                    customClass: { popup: 'rounded-4' }
                });
            })
            .catch(err => {
                waTestBtn.disabled = false;
                waTestSpinner?.classList.add('d-none');
                waTestIcon?.classList.remove('d-none');
                waTestResultBox?.classList.remove('d-none');
                waTestResultCard.className = 'card border-0 rounded-4 p-3 bg-danger-subtle border border-danger-subtle';
                waTestStatusIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                waTestTitle.textContent = 'Kendala Jaringan / Server';
                waTestMessage.textContent = 'Gagal menghubungi backend: ' + err.message;
                waTestMeta.innerHTML = '';
            });
        });
    }

    // 5. Periksa status perangkat Fonnte (read-only, tanpa token di respons).
    const waDeviceBtn = document.getElementById('btnCheckWaDevice');
    if (waDeviceBtn) {
        waDeviceBtn.addEventListener('click', function () {
            const csrf = document.querySelector('input[name="_csrf_token"]');
            const formData = new FormData();
            formData.append('_csrf_token', csrf ? csrf.value : '');

            const spinnerEl = document.getElementById('waDeviceSpinner');
            const iconEl = document.getElementById('waDeviceIcon');
            const resultBox = document.getElementById('waDeviceResult');
            const titleEl = document.getElementById('waDeviceTitle');
            const messageEl = document.getElementById('waDeviceMessage');
            const metaEl = document.getElementById('waDeviceMeta');
            const statusIconEl = document.getElementById('waDeviceStatusIcon');

            waDeviceBtn.disabled = true;
            spinnerEl?.classList.remove('d-none');
            iconEl?.classList.add('d-none');
            resultBox?.classList.add('d-none');

            fetch('<?= base_url('admin/konfigurasi/whatsapp-device') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, data };
            })
            .then(({ ok, data }) => {
                waDeviceBtn.disabled = false;
                spinnerEl?.classList.add('d-none');
                iconEl?.classList.remove('d-none');
                resultBox?.classList.remove('d-none');

                const connected = ok && data.connected === true;
                statusIconEl.innerHTML = connected
                    ? '<i class="bi bi-check-circle-fill text-success"></i>'
                    : '<i class="bi bi-exclamation-triangle-fill text-warning-emphasis"></i>';
                titleEl.textContent = connected ? 'Perangkat Terhubung' : 'Perangkat Tidak Terhubung';
                messageEl.textContent = data.message || 'Status tidak dapat diperiksa.';

                const meta = [];
                if (data.device) meta.push('<span class="badge bg-light text-dark border">Device: ' + escapeHtml(data.device) + '</span>');
                if (data.name) meta.push('<span class="badge bg-light text-dark border">Nama: ' + escapeHtml(data.name) + '</span>');
                if (data.quota !== null && data.quota !== undefined) meta.push('<span class="badge bg-info-subtle text-info-emphasis">Kuota: ' + escapeHtml(data.quota) + '</span>');
                metaEl.innerHTML = meta.join('');
            })
            .catch(err => {
                waDeviceBtn.disabled = false;
                spinnerEl?.classList.add('d-none');
                iconEl?.classList.remove('d-none');
                resultBox?.classList.remove('d-none');
                statusIconEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                titleEl.textContent = 'Pemeriksaan Gagal';
                messageEl.textContent = 'Gagal menghubungi backend: ' + err.message;
                metaEl.innerHTML = '';
            });
        });
    }

    // 6. Form pengumuman: gabungkan sasaran kelas/ID ke target_ref.
    const waAnnTarget = document.getElementById('waAnnouncementTarget');
    const waAnnClassWrap = document.getElementById('waAnnouncementClassWrap');
    const waAnnIdsWrap = document.getElementById('waAnnouncementIdsWrap');
    const waAnnClass = document.getElementById('waAnnouncementClass');
    const waAnnIds = document.getElementById('waAnnouncementIds');
    const waAnnRef = document.getElementById('waAnnouncementTargetRef');
    const waAnnForm = document.getElementById('formWaAnnouncement');

    const syncAnnouncementTarget = function () {
        const target = waAnnTarget?.value || '';
        waAnnClassWrap?.classList.toggle('d-none', target !== 'kelas');
        waAnnIdsWrap?.classList.toggle('d-none', target !== 'mahasiswa' && target !== 'dosen');
        if (!waAnnRef) return;
        if (target === 'kelas') {
            waAnnRef.value = waAnnClass?.value || '';
        } else if (target === 'mahasiswa' || target === 'dosen') {
            waAnnRef.value = (waAnnIds?.value || '').trim();
        } else {
            waAnnRef.value = '';
        }
    };

    waAnnTarget?.addEventListener('change', syncAnnouncementTarget);
    waAnnClass?.addEventListener('change', syncAnnouncementTarget);
    waAnnIds?.addEventListener('input', syncAnnouncementTarget);
    waAnnForm?.addEventListener('submit', function (event) {
        syncAnnouncementTarget();
        const target = waAnnTarget?.value || '';
        const ref = (waAnnRef?.value || '').trim();
        if ((target === 'kelas' || target === 'mahasiswa' || target === 'dosen') && ref === '') {
            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Sasaran belum dipilih',
                text: target === 'kelas' ? 'Pilih kelas terlebih dahulu.' : 'Isi daftar ID penerima terlebih dahulu.',
                customClass: { popup: 'rounded-4' }
            });
        }
    });
    syncAnnouncementTarget();
});
</script>
