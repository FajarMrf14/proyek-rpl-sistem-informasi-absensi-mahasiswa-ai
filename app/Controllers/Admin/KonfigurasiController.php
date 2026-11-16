<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Env;
use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Config\App as AppConfig;
use App\Models\AppSetting;
use App\Models\SettingKampus;
use App\Models\TahunAkademik;
use App\Models\AuditLog;
use App\Models\WhatsAppNotification;
use App\Services\FonnteService;
use DateTimeZone;
use Throwable;

/**
 * Controller Konfigurasi Sistem & Aplikasi
 * Khusus Administrator / Bagian Akademik
 */
class KonfigurasiController extends Controller
{
    private SettingKampus $settingKampusModel;
    private TahunAkademik $tahunAkademikModel;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);

        // Pertahanan ganda verifikasi otorisasi admin di backend
        $user = Session::get('user');
        if (empty($user) || ($user['role'] ?? '') !== 'admin') {
            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda wajib memiliki role Administrator.'
                ], 403);
                exit;
            }
            Session::setFlash('error', 'Akses ditolak. Halaman ini hanya untuk Administrator.');
            $this->response->redirect(base_url('login'));
            exit;
        }

        $this->settingKampusModel = new SettingKampus();
        $this->tahunAkademikModel = new TahunAkademik();
    }

    /**
     * Halaman Utama Menu Konfigurasi (Tampilan Tab Terpadu)
     * GET /admin/konfigurasi
     */
    public function index(): void
    {
        $activeTab = $this->request->get('tab', 'umum');
        if (!in_array($activeTab, ['umum', 'chatbot', 'akademik', 'sistem', 'whatsapp'], true)) {
            $activeTab = 'umum';
        }

        $appConfig = AppConfig::config();
        $groupedSettings = AppSetting::getAllGrouped();
        $campus = $this->settingKampusModel->getActiveSetting() ?? [
            'id' => 1,
            'nama_kampus' => 'STMIK IKMI Cirebon',
            'alamat' => 'Jl. Perjuangan No.10B, Karyamulya, Kec. Kesambi, Kota Cirebon, Jawa Barat 45131',
            'latitude' => -6.7441584,
            'longitude' => 108.5441566,
            'radius_meter' => 100,
            'toleransi_wajah_similarity' => 0.55
        ];

        $daftarTahunAkademik = $this->tahunAkademikModel->all(['*'], 'id DESC');
        $activeTahun = $this->tahunAkademikModel->getActive();

        // Siapkan pilihan model Gemini. Daftar ini adalah model yang kompatibel
        // dengan endpoint generateContent yang dipakai NvidiaChatbotService.
        $availableGeminiModels = [
            'gemini-3.6-flash' => 'Gemini 3.6 Flash (Direkomendasikan - Cepat & Cerdas)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash (Next-Gen Multimodal)',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Ringan & Hemat Kuota)',
            'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Kemampuan Analisis Kompleks)',
        ];

        $activeModel = (string) ($groupedSettings['chatbot']['gemini_model']['value']
            ?? $appConfig['chatbot']['model']);

        // Jangan hilangkan model aktif dari pilihan meskipun tersimpan dari konfigurasi manual.
        if ($activeModel !== '' && !isset($availableGeminiModels[$activeModel])) {
            $availableGeminiModels[$activeModel] = $activeModel . ' (Model tersimpan)';
        }

        // Masking aman untuk API Key. Kunci dari .env tidak pernah ditampilkan,
        // termasuk potongan awal/akhirnya.
        $storedApiKey = trim((string) AppSetting::get('gemini_api_key', ''));
        $envApiKey = trim((string) Env::get('GEMINI_API_KEY', ''));
        $isStoredApiKeyConfigured = $storedApiKey !== '';
        $isApiKeyConfigured = $isStoredApiKeyConfigured || $envApiKey !== '';
        $apiKeySource = $isStoredApiKeyConfigured ? 'database' : ($envApiKey !== '' ? 'env' : 'none');

        // Kunci API tidak perlu diteruskan ke view dalam bentuk apa pun,
        // termasuk potongan awal/akhir (masking) yang masih membocorkan sebagian rahasia.
        unset($appConfig['chatbot']['api_key']);

        // Server diagnostics
        $serverInfo = [
            'php_version'    => PHP_VERSION,
            'curl_enabled'   => function_exists('curl_version'),
            'openssl'        => extension_loaded('openssl'),
            'max_upload'     => ini_get('upload_max_filesize'),
            'post_max_size'  => ini_get('post_max_size'),
            'server_time'    => date('Y-m-d H:i:s T'),
        ];

        // Status integrasi WhatsApp. Token hanya diteruskan sebagai status boolean;
        // nilai token atau potongannya tidak pernah masuk ke view/HTML.
        $whatsappEnabled = filter_var(AppSetting::get('whatsapp_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $whatsappTokenConfigured = trim((string) AppSetting::get('fonnte_token', '')) !== '';
        $whatsappTypeEnabled = [
            'schedule_reminder'           => filter_var(AppSetting::get('whatsapp_schedule_reminder_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'attendance_confirmation'     => filter_var(AppSetting::get('whatsapp_attendance_confirmation_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'absence_notice'              => filter_var(AppSetting::get('whatsapp_absence_notice_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'low_attendance'              => filter_var(AppSetting::get('whatsapp_low_attendance_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'lecturer_teaching_reminder'  => filter_var(AppSetting::get('whatsapp_lecturer_teaching_reminder_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'lecturer_attendance'         => filter_var(AppSetting::get('whatsapp_lecturer_attendance_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'lecturer_recap'              => filter_var(AppSetting::get('whatsapp_lecturer_recap_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'admin_attendance_alert'      => filter_var(AppSetting::get('whatsapp_admin_attendance_alert_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'admin_approval'              => filter_var(AppSetting::get('whatsapp_admin_approval_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'admin_whatsapp_failure'      => filter_var(AppSetting::get('whatsapp_admin_whatsapp_failure_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'admin_device_issue'          => filter_var(AppSetting::get('whatsapp_admin_device_issue_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'admin_attendance_summary'    => filter_var(AppSetting::get('whatsapp_admin_attendance_summary_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'announcement'                => filter_var(AppSetting::get('whatsapp_announcement_enabled', true), FILTER_VALIDATE_BOOLEAN),
        ];
        $whatsappReminderMinutes = max(5, min(180, (int) AppSetting::get('whatsapp_reminder_minutes', 30)));
        $whatsappIntegrationActive = $whatsappEnabled && $whatsappTokenConfigured;
        $whatsappSimulationMode = filter_var(AppSetting::get('whatsapp_simulation_mode', true), FILTER_VALIDATE_BOOLEAN);
        $whatsappLecturerMode = strtolower((string) AppSetting::get('whatsapp_lecturer_attendance_mode', 'per_student'));
        if (!in_array($whatsappLecturerMode, ['per_student', 'summary'], true)) {
            $whatsappLecturerMode = 'per_student';
        }

        $whatsappTemplateKeys = [
            'schedule_reminder' => 'whatsapp_template_schedule_reminder',
            'attendance_confirmation' => 'whatsapp_template_attendance_confirmation',
            'absence_notice' => 'whatsapp_template_absence_notice',
            'lecturer_teaching_reminder' => 'whatsapp_template_lecturer_teaching_reminder',
            'lecturer_attendance' => 'whatsapp_template_lecturer_attendance',
            'lecturer_attendance_summary' => 'whatsapp_template_lecturer_attendance_summary',
            'announcement' => 'whatsapp_template_announcement',
            'admin_alert' => 'whatsapp_template_admin_alert',
            'admin_attendance_summary' => 'whatsapp_template_admin_attendance_summary',
        ];
        $whatsappTemplates = [];
        foreach ($whatsappTemplateKeys as $templateKey) {
            $whatsappTemplates[$templateKey] = (string) AppSetting::get($templateKey, '');
        }

        $whatsappModel = new WhatsAppNotification();
        $whatsappQueueCounts = $whatsappModel->getStatusCounts();
        $whatsappLastSend = $whatsappModel->getLastSendSummary();
        $whatsappRecent = $whatsappModel->getRecentDetailed(15);
        $whatsappFailures = $whatsappModel->getFailedDetailed(10);
        $whatsappAnnouncements = $whatsappModel->getRecentAnnouncements(10);

        $whatsappClasses = [];
        $whatsappRecipientStats = ['mahasiswa' => 0, 'dosen' => 0, 'admin' => 0];
        try {
            $db = \App\Core\Database::getConnection();
            $whatsappClasses = $db->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
            $whatsappRecipientStats = [
                'mahasiswa' => (int) $db->query(
                    "SELECT COUNT(*) FROM mahasiswa m JOIN users u ON u.id = m.user_id
                     WHERE u.role = 'mahasiswa' AND u.is_active = 1 AND m.status_akademik = 'aktif'"
                )->fetchColumn(),
                'dosen' => (int) $db->query(
                    "SELECT COUNT(*) FROM dosen d JOIN users u ON u.id = d.user_id
                     WHERE u.role = 'dosen' AND u.is_active = 1"
                )->fetchColumn(),
                'admin' => (int) $db->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1"
                )->fetchColumn(),
            ];
        } catch (Throwable) {
            // Statistik penerima bersifat informatif.
        }

        $minAttendancePercent = (float) ($appConfig['academic']['min_attendance_percent'] ?? 75.0);

        $data = [
            'title'                 => 'Konfigurasi Sistem - Admin STMIK IKMI Cirebon',
            'activeTab'             => $activeTab,
            'config'                => $appConfig,
            'grouped'               => $groupedSettings,
            'campus'                => $campus,
            'daftarTahunAkademik'   => $daftarTahunAkademik,
            'activeTahunId'         => $activeTahun['id'] ?? null,
            'availableGeminiModels' => $availableGeminiModels,
            'activeModel'           => $activeModel,
            'isApiKeyConfigured'    => $isApiKeyConfigured,
            'isStoredApiKeyConfigured' => $isStoredApiKeyConfigured,
            'apiKeySource'          => $apiKeySource,
            'serverInfo'            => $serverInfo,
            'whatsappEnabled'       => $whatsappEnabled,
            'whatsappTokenConfigured' => $whatsappTokenConfigured,
            'whatsappIntegrationActive' => $whatsappIntegrationActive,
            'whatsappTypeEnabled'   => $whatsappTypeEnabled,
            'whatsappReminderMinutes' => $whatsappReminderMinutes,
            'whatsappSimulationMode' => $whatsappSimulationMode,
            'whatsappLecturerMode'  => $whatsappLecturerMode,
            'whatsappTemplates'     => $whatsappTemplates,
            'whatsappQueueCounts'   => $whatsappQueueCounts,
            'whatsappLastSend'      => $whatsappLastSend,
            'whatsappRecent'        => $whatsappRecent,
            'whatsappFailures'      => $whatsappFailures,
            'whatsappAnnouncements' => $whatsappAnnouncements,
            'whatsappClasses'       => $whatsappClasses,
            'whatsappRecipientStats' => $whatsappRecipientStats,
            'whatsappEndpoint'      => FonnteService::ENDPOINT,
            'minAttendancePercent'  => $minAttendancePercent,
            'timezones'             => [
                'Asia/Jakarta'  => 'Asia/Jakarta (WIB - UTC+7)',
                'Asia/Makassar' => 'Asia/Makassar (WITA - UTC+8)',
                'Asia/Jayapura' => 'Asia/Jayapura (WIT - UTC+9)',
                'UTC'           => 'UTC (Universal Coordinated Time)',
            ]
        ];

        $this->render('admin/konfigurasi', $data);
    }

    /**
     * Simpan Pengaturan Umum (Kategori A)
     * POST /admin/konfigurasi/umum
     */
    public function updateUmum(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        $appName = trim((string)$this->request->post('app_name'));
        $appDesc = trim((string)$this->request->post('app_description'));
        $campusName = trim((string)$this->request->post('campus_name'));
        $contactEmail = trim((string)$this->request->post('contact_email'));
        $timezone = trim((string)$this->request->post('app_timezone'));
        $removeLogo = $this->request->post('remove_logo') === '1';

        // Validasi Backend
        if (mb_strlen($appName) < 3 || mb_strlen($appName) > 100) {
            Session::setFlash('error', 'Nama aplikasi wajib diisi antara 3 sampai 100 karakter.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
            return;
        }

        if (mb_strlen($campusName) < 3 || mb_strlen($campusName) > 100) {
            Session::setFlash('error', 'Nama kampus wajib diisi antara 3 sampai 100 karakter.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
            return;
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            Session::setFlash('error', 'Format email kontak tidak valid.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
            return;
        }

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            Session::setFlash('error', 'Zona waktu yang dipilih tidak dikenali sistem.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
            return;
        }

        // Handle upload logo
        $logoPath = null;
        if ($removeLogo) {
            AppSetting::set('app_logo', '', 'umum', false, $userId);
        } elseif (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo_file'];
            $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedMimes, true)) {
                Session::setFlash('error', 'Format file logo harus berupa PNG, JPG, WEBP, atau SVG.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
                return;
            }

            if ($file['size'] > (2 * 1024 * 1024)) { // 2MB
                Session::setFlash('error', 'Ukuran file logo maksimal 2MB.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
                return;
            }

            // Ekstensi ditentukan dari MIME yang sudah diverifikasi, bukan dari nama file klien.
            $allowedExtensions = [
                'image/png'      => 'png',
                'image/jpeg'     => 'jpg',
                'image/webp'     => 'webp',
                'image/svg+xml'  => 'svg',
            ];
            $ext = $allowedExtensions[$mime] ?? null;

            if ($ext === null) {
                Session::setFlash('error', 'Format file logo tidak didukung.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
                return;
            }

            $targetDir = ROOT_PATH . '/public/uploads/logo';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $targetDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $logoPath = 'uploads/logo/' . $filename;
                AppSetting::set('app_logo', $logoPath, 'umum', false, $userId);
            }
        }

        // Simpan nilai ke tabel app_settings
        $settingsSaved = true;
        $settingsSaved = AppSetting::set('app_name', $appName, 'umum', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('app_description', $appDesc, 'umum', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('campus_name', $campusName, 'umum', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('contact_email', $contactEmail, 'umum', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('app_timezone', $timezone, 'umum', false, $userId) && $settingsSaved;

        // Sinkronisasi nama kampus di tabel settings_kampus jika ada record aktif
        $campusSaved = true;
        $activeCampus = $this->settingKampusModel->getActiveSetting();
        if ($activeCampus) {
            $campusSaved = $this->settingKampusModel->update($activeCampus['id'], [
                'nama_kampus' => $campusName,
                'updated_by'  => $userId
            ]);
        }

        if (!$settingsSaved || !$campusSaved) {
            Session::setFlash('error', 'Sebagian pengaturan umum gagal disimpan. Periksa log server dan coba kembali.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
            return;
        }

        // Catat di Audit Trail
        AuditLog::logActivity(
            $userId,
            'UPDATE_CONFIG_UMUM',
            'app_settings',
            null,
            null,
            ['app_name' => $appName, 'campus_name' => $campusName, 'timezone' => $timezone]
        );

        Session::setFlash('success', 'Pengaturan Umum berhasil diperbarui dan diterapkan ke seluruh sistem.');
        $this->response->redirect(base_url('admin/konfigurasi?tab=umum'));
    }

    /**
     * Simpan Pengaturan Chatbot AI (Kategori B)
     * POST /admin/konfigurasi/chatbot
     */
    public function updateChatbot(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        // Validasi Provider AI: Hanya google_gemini yang didukung.
        $rawProvider = trim((string)$this->request->post('ai_provider', 'google_gemini'));
        if ($rawProvider !== '' && $rawProvider !== 'google_gemini') {
            Session::setFlash('error', 'Provider AI yang dipilih tidak didukung.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
            return;
        }

        // Provider AI dikunci di backend. Admin hanya dapat mengubah model,
        // endpoint, dan API key; nilai dari request tidak pernah dipercaya.
        $provider = 'google_gemini';
        $model = trim((string)$this->request->post('gemini_model', 'gemini-3.6-flash'));
        $baseUrl = rtrim(trim((string)$this->request->post('gemini_base_url')), '/');
        // Gunakan nilai mentah agar karakter khusus pada API key tidak berubah.
        $newApiKey = trim((string)$this->request->raw('gemini_api_key', ''));
        $removeApiKey = $this->request->post('remove_api_key') === '1';

        // Validasi Model
        if (empty($model) || mb_strlen($model) > 60
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $model)
        ) {
            Session::setFlash('error', 'Nama Model AI tidak valid.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
            return;
        }

        // Validasi Base URL
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (empty($baseUrl) || !filter_var($baseUrl, FILTER_VALIDATE_URL)
            || !in_array($scheme, ['http', 'https'], true)
        ) {
            Session::setFlash('error', 'Base URL endpoint API tidak valid.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
            return;
        }

        if ($newApiKey !== '') {
            if (strlen($newApiKey) < 10 || strlen($newApiKey) > 512
                || preg_match('/[\x00-\x1F\x7F]/', $newApiKey)
            ) {
                Session::setFlash('error', 'Format API Key tidak valid.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
                return;
            }
        }

        $settingsSaved = true;
        $settingsSaved = AppSetting::set('ai_provider', $provider, 'chatbot', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('gemini_model', $model, 'chatbot', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('gemini_base_url', $baseUrl, 'chatbot', false, $userId) && $settingsSaved;

        // Pengelolaan API Key Sensitif
        if ($removeApiKey) {
            $settingsSaved = AppSetting::set('gemini_api_key', '', 'chatbot', true, $userId) && $settingsSaved;
            $keyStatusMsg = 'API Key berhasil dihapus dari penyimpanan database.';
        } elseif ($newApiKey !== '') {
            // Admin memasukkan API key baru -> Enkripsi dan simpan
            $settingsSaved = AppSetting::set('gemini_api_key', $newApiKey, 'chatbot', true, $userId) && $settingsSaved;
            $keyStatusMsg = 'API Key baru berhasil dienkripsi dan disimpan dengan aman.';
        } else {
            // Kolom API key kosong -> Pertahankan key lama tanpa ditimpa
            $keyStatusMsg = 'API Key sebelumnya tetap dipertahankan.';
        }

        if (!$settingsSaved) {
            Session::setFlash('error', 'Konfigurasi Chatbot AI gagal disimpan. Periksa log server dan coba kembali.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
            return;
        }

        // Catat di Audit Trail (Jangan pernah mencatat API Key mentah!)
        AuditLog::logActivity(
            $userId,
            'UPDATE_CONFIG_CHATBOT',
            'app_settings',
            null,
            null,
            ['provider' => $provider, 'model' => $model, 'base_url' => $baseUrl, 'api_key_action' => $removeApiKey ? 'cleared' : ($newApiKey !== '' ? 'updated' : 'preserved')]
        );

        Session::setFlash('success', "Konfigurasi Chatbot AI berhasil diperbarui. {$keyStatusMsg}");
        $this->response->redirect(base_url('admin/konfigurasi?tab=chatbot'));
    }

    /**
     * Endpoint Pengujian Nyata Koneksi AI Chatbot (AJAX)
     * POST /admin/konfigurasi/test-ai
     */
    public function testChatbotConnection(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        // Validasi Provider AI: Hanya google_gemini yang didukung.
        $rawProvider = trim((string)$this->request->post('ai_provider', 'google_gemini'));
        if ($rawProvider !== '' && $rawProvider !== 'google_gemini') {
            $this->response->json([
                'success' => false,
                'message' => 'Provider AI tidak didukung.'
            ], 200);
            return;
        }

        // Provider dikunci; endpoint pengujian selalu memakai Google Gemini.
        $model = trim((string)$this->request->post('gemini_model', 'gemini-3.6-flash'));
        $baseUrl = rtrim(trim((string)$this->request->post('gemini_base_url')), '/');
        $inputKey = trim((string)$this->request->raw('gemini_api_key', ''));

        // Jika input API key kosong saat pengujian, gunakan key aktif dari database / config
        $activeConfig = AppConfig::config()['chatbot'] ?? [];
        $apiKey = ($inputKey !== '') ? $inputKey : ($activeConfig['api_key'] ?? '');

        if ($baseUrl === '') {
            $baseUrl = $activeConfig['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
        }
        if ($model === '') {
            $model = $activeConfig['model'] ?? 'gemini-3.6-flash';
        }

        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)
            || !in_array($scheme, ['http', 'https'], true)
        ) {
            $this->response->json([
                'success' => false,
                'message' => 'Base URL endpoint API tidak valid.'
            ], 200);
            return;
        }

        if (mb_strlen($model) > 60 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $model)) {
            $this->response->json([
                'success' => false,
                'message' => 'Nama model AI tidak valid.'
            ], 200);
            return;
        }

        if (empty($apiKey)) {
            $this->response->json([
                'success' => false,
                'message' => 'API Key belum dikonfigurasi. Masukkan API key pada formulir terlebih dahulu untuk melakukan pengujian.'
            ], 200);
            return;
        }

        // Lakukan pengujian koneksi nyata ke Google Gemini endpoint
        $endpoint = $baseUrl . '/models/' . rawurlencode($model) . ':generateContent';
        $testPayload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Ping uji koneksi dari Sistem Absensi Mahasiswa STMIK IKMI Cirebon. Balas hanya dengan satu kata: PONG.']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10,
                'temperature'     => 0.1
            ]
        ];

        $jsonPayload = json_encode($testPayload);
        $startTime = microtime(true);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'x-goog-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
        curl_close($ch);

        if ($curlError !== '') {
            $this->response->json([
                'success'    => false,
                'message'    => 'Koneksi jaringan gagal: ' . $curlError,
                'latency_ms' => $latencyMs,
                'http_code'  => $httpCode
            ], 200);
            return;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode((string)$rawResponse, true);
            $candidateText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $replyClean = trim($candidateText);

            $this->response->json([
                'success'    => true,
                'message'    => "Koneksi ke Gemini AI berhasil! Model '{$model}' merespons dalam {$latencyMs} ms.",
                'reply'      => $replyClean ?: 'PONG',
                'latency_ms' => $latencyMs,
                'model'      => $model,
                'http_code'  => $httpCode
            ]);
            return;
        }

        // Parsing error yang aman tanpa membocorkan data sensitif
        $errorMessage = match ($httpCode) {
            400 => 'Permintaan tidak valid (Bad Request). Format payload atau nama model mungkin tidak didukung.',
            401, 403 => 'Autentikasi ditolak (HTTP ' . $httpCode . '). API Key tidak valid, kadaluarsa, atau tidak memiliki izin akses ke model ini.',
            404 => "Model '{$model}' tidak ditemukan pada endpoint API yang ditentukan (HTTP 404).",
            429 => 'Batas kuota harian atau laju panggilan API telah tercapai (HTTP 429: Rate Limit / Quota Exceeded).',
            500, 503 => 'Server AI Gemini sedang mengalami kendala sementara (HTTP ' . $httpCode . '). Coba beberapa saat lagi.',
            default => 'Koneksi gagal dengan status HTTP ' . $httpCode . '.'
        };

        $this->response->json([
            'success'    => false,
            'message'    => $errorMessage,
            'latency_ms' => $latencyMs,
            'http_code'  => $httpCode
        ], 200);
    }

    /**
     * Simpan Pengaturan Notifikasi WhatsApp/Fonnte (Kategori E)
     * POST /admin/konfigurasi/whatsapp
     */
    public function updateWhatsapp(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        $enabled = $this->request->post('whatsapp_enabled') === '1';
        $reminderMinutes = (int)$this->request->post('whatsapp_reminder_minutes', 30);
        $newToken = trim((string)$this->request->raw('fonnte_token', ''));
        $removeToken = $this->request->post('remove_fonnte_token') === '1';

        $typeFlags = [
            'whatsapp_schedule_reminder_enabled'          => $this->request->post('whatsapp_schedule_reminder_enabled') === '1',
            'whatsapp_attendance_confirmation_enabled'    => $this->request->post('whatsapp_attendance_confirmation_enabled') === '1',
            'whatsapp_absence_notice_enabled'             => $this->request->post('whatsapp_absence_notice_enabled') === '1',
            'whatsapp_low_attendance_enabled'             => $this->request->post('whatsapp_low_attendance_enabled') === '1',
            'whatsapp_lecturer_teaching_reminder_enabled' => $this->request->post('whatsapp_lecturer_teaching_reminder_enabled') === '1',
            'whatsapp_lecturer_attendance_enabled'        => $this->request->post('whatsapp_lecturer_attendance_enabled') === '1',
            'whatsapp_lecturer_recap_enabled'             => $this->request->post('whatsapp_lecturer_recap_enabled') === '1',
            'whatsapp_admin_attendance_alert_enabled'     => $this->request->post('whatsapp_admin_attendance_alert_enabled') === '1',
            'whatsapp_admin_approval_enabled'             => $this->request->post('whatsapp_admin_approval_enabled') === '1',
            'whatsapp_admin_whatsapp_failure_enabled'     => $this->request->post('whatsapp_admin_whatsapp_failure_enabled') === '1',
            'whatsapp_admin_device_issue_enabled'         => $this->request->post('whatsapp_admin_device_issue_enabled') === '1',
            'whatsapp_admin_attendance_summary_enabled'   => $this->request->post('whatsapp_admin_attendance_summary_enabled') === '1',
            'whatsapp_announcement_enabled'               => $this->request->post('whatsapp_announcement_enabled') === '1',
        ];

        $simulationMode = $this->request->post('whatsapp_simulation_mode') === '1';
        $lecturerMode = strtolower(trim((string) $this->request->post('whatsapp_lecturer_attendance_mode', 'per_student')));
        if (!in_array($lecturerMode, ['per_student', 'summary'], true)) {
            $lecturerMode = 'per_student';
        }

        if ($reminderMinutes < 5 || $reminderMinutes > 180) {
            Session::setFlash('error', 'Waktu pengingat harus antara 5 sampai 180 menit sebelum kelas.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        // Template dibatasi panjangnya agar tidak melebihi batas pesan Fonnte dan
        // tidak menyimpan data tak terbatas dari form.
        $templateKeys = [
            'whatsapp_template_schedule_reminder',
            'whatsapp_template_attendance_confirmation',
            'whatsapp_template_absence_notice',
            'whatsapp_template_lecturer_teaching_reminder',
            'whatsapp_template_lecturer_attendance',
            'whatsapp_template_lecturer_attendance_summary',
            'whatsapp_template_announcement',
            'whatsapp_template_admin_alert',
            'whatsapp_template_admin_attendance_summary',
        ];
        $templates = [];
        foreach ($templateKeys as $templateKey) {
            $value = trim((string) $this->request->raw($templateKey, ''));
            if (mb_strlen($value, 'UTF-8') > 4000) {
                Session::setFlash('error', 'Salah satu template pesan melebihi 4000 karakter.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
                return;
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                Session::setFlash('error', 'Template pesan mengandung karakter kontrol yang tidak diizinkan.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
                return;
            }
            $templates[$templateKey] = $value;
        }

        if ($newToken !== '') {
            if (strlen($newToken) < 10 || strlen($newToken) > 512
                || preg_match('/[\x00-\x1F\x7F]/', $newToken)
            ) {
                Session::setFlash('error', 'Format token Fonnte tidak valid.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
                return;
            }

            // Jangan pernah menyimpan token sebagai plaintext jika enkripsi tidak tersedia.
            if (!extension_loaded('openssl')) {
                Session::setFlash('error', 'Ekstensi OpenSSL tidak aktif. Token tidak disimpan agar tidak tersimpan sebagai plaintext.');
                $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
                return;
            }
        }

        $settingsSaved = true;
        $settingsSaved = AppSetting::set('whatsapp_enabled', $enabled, 'whatsapp', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('whatsapp_reminder_minutes', $reminderMinutes, 'whatsapp', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('whatsapp_simulation_mode', $simulationMode, 'whatsapp', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('whatsapp_lecturer_attendance_mode', $lecturerMode, 'whatsapp', false, $userId) && $settingsSaved;

        foreach ($typeFlags as $key => $value) {
            $settingsSaved = AppSetting::set($key, $value, 'whatsapp', false, $userId) && $settingsSaved;
        }

        foreach ($templates as $templateKey => $templateValue) {
            $settingsSaved = AppSetting::set($templateKey, $templateValue, 'whatsapp', false, $userId) && $settingsSaved;
        }

        $tokenAction = 'preserved';
        if ($removeToken) {
            $settingsSaved = AppSetting::set('fonnte_token', '', 'whatsapp', true, $userId) && $settingsSaved;
            $tokenAction = 'cleared';
            $tokenStatusMsg = 'Token Fonnte dihapus dari database.';
        } elseif ($newToken !== '') {
            // Token hanya disimpan lewat mekanisme enkripsi AppSetting yang sudah ada.
            $settingsSaved = AppSetting::set('fonnte_token', $newToken, 'whatsapp', true, $userId) && $settingsSaved;
            $tokenAction = 'updated';
            $tokenStatusMsg = 'Token Fonnte baru berhasil dienkripsi dan disimpan.';
        } else {
            $tokenStatusMsg = 'Token Fonnte lama tetap dipertahankan.';
        }

        if (!$settingsSaved) {
            Session::setFlash('error', 'Konfigurasi WhatsApp gagal disimpan. Pastikan migrasi WhatsApp sudah dijalankan dan periksa log server.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        // Audit hanya mencatat tindakan dan status, bukan token/nomor.
        AuditLog::logActivity(
            $userId,
            'UPDATE_CONFIG_WHATSAPP',
            'app_settings',
            null,
            null,
            [
                'enabled' => $enabled,
                'simulation_mode' => $simulationMode,
                'notification_types' => array_keys(array_filter($typeFlags)),
                'reminder_minutes' => $reminderMinutes,
                'lecturer_attendance_mode' => $lecturerMode,
                'templates_changed' => array_keys(array_filter($templates, static fn(string $value): bool => trim($value) !== '')),
                'token_action' => $tokenAction,
            ]
        );

        $tokenConfigured = !$removeToken && ($newToken !== '' || trim((string)AppSetting::get('fonnte_token', '')) !== '');
        $message = "Konfigurasi WhatsApp berhasil diperbarui. {$tokenStatusMsg}";
        if ($simulationMode) {
            $message .= ' Mode simulasi AKTIF: pesan dicatat di antrean tetapi tidak dikirim ke Fonnte.';
        }
        if ($enabled && !$tokenConfigured) {
            $message .= ' Integrasi aktif, tetapi token belum diisi sehingga tidak ada pesan yang akan dikirim.';
        }
        if ($enabled && !in_array(true, $typeFlags, true)) {
            $message .= ' Tidak ada jenis notifikasi yang dipilih.';
        }

        Session::setFlash('success', $message);
        $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
    }

    /**
     * Uji pengiriman nyata ke nomor yang dipilih admin (AJAX).
     * Wajib konfirmasi eksplisit; tidak pernah dipanggil saat halaman dimuat.
     * POST /admin/konfigurasi/test-whatsapp
     */
    public function testWhatsapp(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        if ($this->request->post('test_confirm') !== '1') {
            $this->response->json([
                'success' => false,
                'message' => 'Konfirmasi pengujian diperlukan. Tidak ada pesan yang dikirim.'
            ], 200);
            return;
        }

        $recipient = trim((string)$this->request->raw('test_recipient', ''));
        $normalized = FonnteService::normalizePhone($recipient);
        if ($normalized === null) {
            $this->response->json([
                'success' => false,
                'message' => 'Nomor tujuan tidak valid. Gunakan format 08xxxxxxxxxx atau 62xxxxxxxxxx.'
            ], 200);
            return;
        }

        $client = new FonnteService();
        $whatsappService = new \App\Services\WhatsAppNotificationService();
        $maskedRecipient = FonnteService::maskPhone($normalized);

        // Mode simulasi: tidak ada panggilan jaringan sama sekali. Hasil dicatat
        // sebagai accepted dengan penanda simulated agar tidak disalahartikan
        // sebagai bukti pengiriman nyata.
        if ($whatsappService->isSimulationMode()) {
            $notificationId = null;
            try {
                $notificationId = (int)(new WhatsAppNotification())->insert([
                    'event_key'         => 'test:' . $userId . ':' . bin2hex(random_bytes(8)),
                    'notification_type' => 'test',
                    'recipient_type'    => 'test',
                    'recipient_id'      => null,
                    'recipient_ref'     => 'admin#' . $userId,
                    'payload_json'      => json_encode([
                        'recipient_masked' => $maskedRecipient,
                        'simulation'       => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status'            => 'accepted',
                    'attempts'          => 1,
                    'scheduled_at'      => date('Y-m-d H:i:s'),
                    'last_attempt_at'   => date('Y-m-d H:i:s'),
                    'accepted_at'       => date('Y-m-d H:i:s'),
                    'simulated'         => 1,
                    'provider_message_id' => 'SIMULASI',
                    'provider_request_id' => 'SIMULASI',
                ]);
            } catch (Throwable $e) {
                error_log('[WhatsApp] Gagal mencatat simulasi tes: ' . get_class($e));
            }

            try {
                AuditLog::logActivity(
                    $userId,
                    'WHATSAPP_TEST_SIMULATION',
                    'whatsapp_notifications',
                    $notificationId ?: null,
                    null,
                    ['recipient_masked' => $maskedRecipient, 'simulation' => true]
                );
            } catch (Throwable $e) {
                error_log('[WhatsApp] Gagal mencatat audit simulasi: ' . get_class($e));
            }

            $this->response->json([
                'success' => true,
                'simulated' => true,
                'api_accepted' => false,
                'queued' => false,
                'message' => 'Mode simulasi aktif. Pesan TIDAK dikirim ke Fonnte; hasil hanya dicatat di riwayat sebagai simulasi.',
                'recipient_masked' => $maskedRecipient,
            ], 200);
            return;
        }

        if (!$client->isConfigured()) {
            $this->response->json([
                'success' => false,
                'message' => 'Token Fonnte belum dikonfigurasi. Simpan token terlebih dahulu.'
            ], 200);
            return;
        }

        $appName = (string)(AppConfig::config()['name'] ?? 'Sistem Absensi Mahasiswa');
        $message = "*Tes Koneksi WhatsApp*\n"
            . "Pesan uji dari {$appName}.\n\n"
            . "Jika Anda menerima pesan ini, koneksi Fonnte berhasil diproses. "
            . "Status sukses API hanya berarti pesan masuk antrean Fonnte, bukan bukti pesan sudah dibaca.";

        $result = $client->send($normalized, $message);
        $accepted = !empty($result['success']);

        $notificationId = null;
        try {
            $notificationId = (int)(new WhatsAppNotification())->insert([
                'event_key'         => 'test:' . $userId . ':' . bin2hex(random_bytes(8)),
                'notification_type' => 'test',
                'recipient_type'    => 'test',
                'recipient_id'      => null,
                'recipient_ref'     => 'admin#' . $userId,
                'payload_json'      => json_encode([
                    'recipient_masked' => $maskedRecipient,
                    'http_code'        => (int)($result['http_code'] ?? 0),
                    'api_accepted'     => $accepted,
                    'queued'           => (bool)($result['queued'] ?? false),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'            => $accepted ? 'accepted' : 'failed',
                'attempts'          => 1,
                'scheduled_at'      => date('Y-m-d H:i:s'),
                'last_attempt_at'   => date('Y-m-d H:i:s'),
                'accepted_at'       => $accepted ? date('Y-m-d H:i:s') : null,
                'provider_message_id' => ($result['provider_message_id'] ?? '') !== '' ? $result['provider_message_id'] : null,
                'provider_request_id' => ($result['request_id'] ?? '') !== '' ? $result['request_id'] : null,
                'error_message'     => $accepted ? null : mb_substr((string)($result['error_message'] ?? 'Pengiriman gagal.'), 0, 255),
            ]);
        } catch (Throwable $e) {
            // Kegagalan mencatat log tidak boleh mengubah hasil tes, dan tidak boleh membocorkan data.
            error_log('[WhatsApp] Gagal mencatat hasil tes: ' . get_class($e));
        }

        try {
            AuditLog::logActivity(
                $userId,
                'WHATSAPP_TEST_SEND',
                'whatsapp_notifications',
                $notificationId ?: null,
                null,
                [
                    'recipient_masked' => $maskedRecipient,
                    'http_code' => (int)($result['http_code'] ?? 0),
                    'request_id' => (string)($result['request_id'] ?? ''),
                    'api_accepted' => $accepted,
                    'queued' => (bool)($result['queued'] ?? false),
                    'provider_status' => $result['provider_status'] ?? null,
                ]
            );
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal mencatat audit tes: ' . get_class($e));
        }

        if ($accepted) {
            $requestId = (string)($result['request_id'] ?? '');
            $this->response->json([
                'success' => true,
                'api_accepted' => true,
                'queued' => true,
                'message' => 'API Fonnte menerima pesan uji dan menempatkannya dalam antrean'
                    . ($requestId !== '' ? " (request ID: {$requestId})" : '')
                    . '. Ini bukan bukti pesan sudah diterima atau dibaca penerima.',
                'http_code' => (int)($result['http_code'] ?? 0),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
                'request_id' => $requestId,
                'provider_status' => $result['provider_status'] ?? null,
                'recipient_masked' => $maskedRecipient,
            ], 200);
            return;
        }

        $this->response->json([
            'success' => false,
            'api_accepted' => false,
            'queued' => false,
            'message' => (string)($result['error_message'] ?? 'Pengiriman uji gagal.'),
            'http_code' => (int)($result['http_code'] ?? 0),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
            'request_id' => (string)($result['request_id'] ?? ''),
            'recipient_masked' => $maskedRecipient,
        ], 200);
    }

    /**
     * Kirim pengumuman WhatsApp ke penerima yang dipilih admin.
     * POST /admin/konfigurasi/announcement
     */
    public function sendAnnouncement(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 0);
        $judul = trim((string) $this->request->raw('judul', ''));
        $pesan = trim((string) $this->request->raw('pesan', ''));
        $targetType = strtolower(trim((string) $this->request->post('target_type', '')));
        $targetRef = trim((string) $this->request->raw('target_ref', ''));

        $allowedTargets = ['all_mahasiswa', 'all_dosen', 'all_users', 'kelas', 'mahasiswa', 'dosen'];
        if (!in_array($targetType, $allowedTargets, true)) {
            Session::setFlash('error', 'Sasaran pengumuman tidak valid.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        if ($judul === '' || mb_strlen($judul, 'UTF-8') > 120) {
            Session::setFlash('error', 'Judul pengumuman wajib diisi dan maksimal 120 karakter.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        if ($pesan === '' || mb_strlen($pesan, 'UTF-8') > 2000) {
            Session::setFlash('error', 'Isi pengumuman wajib diisi dan maksimal 2000 karakter.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        if (in_array($targetType, ['kelas', 'mahasiswa', 'dosen'], true) && $targetRef === '') {
            Session::setFlash('error', 'Pilih kelas atau isi daftar ID penerima untuk sasaran pengumuman.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        if (in_array($targetType, ['mahasiswa', 'dosen'], true)) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $targetRef)), static fn(int $id): bool => $id > 0));
            if (empty($ids) || count($ids) > 500) {
                Session::setFlash('error', 'Daftar ID penerima tidak valid (maksimal 500 ID).');
                $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
                return;
            }
            $targetRef = implode(',', $ids);
        }

        $service = new \App\Services\WhatsAppNotificationService();
        $result = $service->enqueueAnnouncement($judul, $pesan, $targetType, $targetRef !== '' ? $targetRef : null, $userId);

        try {
            AuditLog::logActivity(
                $userId,
                'WHATSAPP_ANNOUNCEMENT',
                'whatsapp_announcements',
                $result['announcement_id'] ?? null,
                null,
                [
                    'target_type' => $targetType,
                    'recipients' => (int) ($result['recipients'] ?? 0),
                    'enqueued' => (int) ($result['enqueued'] ?? 0),
                    'skipped' => (int) ($result['skipped'] ?? 0),
                ]
            );
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal mencatat audit pengumuman: ' . get_class($e));
        }

        if (($result['announcement_id'] ?? null) === null) {
            Session::setFlash('error', 'Pengumuman tidak dapat dibuat. Pastikan integrasi WhatsApp aktif, jenis pengumuman diizinkan, dan ada penerima yang sesuai.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        if ((int) ($result['enqueued'] ?? 0) === 0) {
            Session::setFlash('error', 'Pengumuman tercatat, tetapi tidak ada penerima dengan nomor WhatsApp valid. Periksa nomor pada Manajemen Pengguna.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
            return;
        }

        $message = 'Pengumuman "' . $judul . '" masuk antrean untuk ' . (int) $result['enqueued'] . ' penerima.';
        if ((int) ($result['skipped'] ?? 0) > 0) {
            $message .= ' ' . (int) $result['skipped'] . ' penerima dilewati karena nomor tidak tersedia/tidak valid.';
        }
        if ($service->isSimulationMode()) {
            $message .= ' Mode simulasi aktif, pesan tidak dikirim ke Fonnte.';
        }

        Session::setFlash('success', $message);
        $this->response->redirect(base_url('admin/konfigurasi?tab=whatsapp'));
    }

    /**
     * Periksa status perangkat Fonnte (AJAX). Tidak mengirim pesan dan tidak
     * pernah mengembalikan token.
     * POST /admin/konfigurasi/whatsapp-device
     */
    public function checkDevice(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 0);
        $client = new FonnteService();

        if (!$client->isConfigured()) {
            $this->response->json([
                'success' => false,
                'connected' => false,
                'message' => 'Token Fonnte belum dikonfigurasi.',
            ], 200);
            return;
        }

        $status = $client->getDeviceStatus();

        try {
            AuditLog::logActivity(
                $userId,
                'WHATSAPP_DEVICE_CHECK',
                'app_settings',
                null,
                null,
                [
                    'connected' => (bool) ($status['connected'] ?? false),
                    'success' => (bool) ($status['success'] ?? false),
                ]
            );
        } catch (Throwable $e) {
            error_log('[WhatsApp] Gagal mencatat audit cek perangkat: ' . get_class($e));
        }

        $this->response->json([
            'success' => (bool) ($status['success'] ?? false),
            'connected' => (bool) ($status['connected'] ?? false),
            'device' => (string) ($status['device'] ?? ''),
            'name' => (string) ($status['name'] ?? ''),
            'quota' => $status['quota'] ?? null,
            'message' => (string) ($status['message'] ?? 'Status perangkat tidak dapat diperiksa.'),
        ], 200);
    }

    /**
     * Simpan Pengaturan Akademik (Kategori C)
     * POST /admin/konfigurasi/akademik
     */
    public function updateAkademik(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        $tahunId = (int)$this->request->post('tahun_akademik_id');
        $lateTolerance = (int)$this->request->post('default_late_tolerance');
        $minAttendance = (float)$this->request->post('min_attendance_percent');
        $radiusMeter = (int)$this->request->post('radius_meter');
        $faceThreshold = (float)$this->request->post('toleransi_wajah_similarity');
        $latitudeRaw = trim((string)$this->request->post('latitude'));
        $longitudeRaw = trim((string)$this->request->post('longitude'));
        $alamat = trim((string)$this->request->post('alamat'));

        // Validasi Tahun Akademik
        $tahun = $this->tahunAkademikModel->find($tahunId);
        if (!$tahun) {
            Session::setFlash('error', 'Tahun akademik yang dipilih tidak valid.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        // Jangan biarkan input kosong berubah menjadi koordinat 0,0.
        if ($latitudeRaw === '' || $longitudeRaw === ''
            || !is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)
        ) {
            Session::setFlash('error', 'Koordinat latitude dan longitude wajib diisi dengan angka.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        $latitude = (float)$latitudeRaw;
        $longitude = (float)$longitudeRaw;

        // Validasi Parameter
        if ($lateTolerance < 0 || $lateTolerance > 120) {
            Session::setFlash('error', 'Toleransi keterlambatan harus antara 0 sampai 120 menit.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        if ($minAttendance < 50.0 || $minAttendance > 100.0) {
            Session::setFlash('error', 'Batas minimal kehadiran harus antara 50% sampai 100%.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        if ($radiusMeter < 10 || $radiusMeter > 5000) {
            Session::setFlash('error', 'Radius toleransi geofencing harus antara 10 sampai 5000 meter.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        if ($faceThreshold < 0.20 || $faceThreshold > 0.90) {
            Session::setFlash('error', 'Ambang batas similarity wajah AI harus antara 0.20 sampai 0.90.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            Session::setFlash('error', 'Koordinat GPS latitude/longitude di luar rentang geografis yang sah.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        $activeCampus = $this->settingKampusModel->getActiveSetting();
        if (!$activeCampus) {
            Session::setFlash('error', 'Pengaturan kampus aktif tidak ditemukan. Jalankan migrasi/seeder basis data terlebih dahulu.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        // 1. Perbarui Tahun Akademik Aktif
        $tahunUpdated = $this->tahunAkademikModel->setActive($tahunId);

        // 2. Perbarui Pengaturan Lokasi & Geofence di settings_kampus
        $campusUpdated = $this->settingKampusModel->update($activeCampus['id'], [
            'alamat'                     => $alamat !== '' ? $alamat : $activeCampus['alamat'],
            'latitude'                   => $latitude,
            'longitude'                  => $longitude,
            'radius_meter'               => $radiusMeter,
            'toleransi_wajah_similarity' => $faceThreshold,
            'updated_by'                 => $userId
        ]);

        // 3. Simpan parameter akademik ke app_settings
        $lateSaved = AppSetting::set('default_late_tolerance', $lateTolerance, 'akademik', false, $userId);
        $attendanceSaved = AppSetting::set('min_attendance_percent', $minAttendance, 'akademik', false, $userId);

        if (!$tahunUpdated || !$campusUpdated || !$lateSaved || !$attendanceSaved) {
            Session::setFlash('error', 'Sebagian pengaturan akademik gagal disimpan. Periksa log server dan coba kembali.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
            return;
        }

        // Catat di Audit Trail
        AuditLog::logActivity(
            $userId,
            'UPDATE_CONFIG_AKADEMIK',
            'settings_kampus',
            $activeCampus['id'] ?? null,
            null,
            [
                'tahun_akademik_id' => $tahunId,
                'nama_tahun'        => $tahun['nama_tahun'],
                'radius_meter'      => $radiusMeter,
                'face_threshold'    => $faceThreshold,
                'late_tolerance'    => $lateTolerance,
                'min_attendance'    => $minAttendance,
            ]
        );

        Session::setFlash('success', "Pengaturan Akademik berhasil diperbarui. Periode aktif: {$tahun['nama_tahun']}.");
        $this->response->redirect(base_url('admin/konfigurasi?tab=akademik'));
    }

    /**
     * Simpan Pengaturan Sistem (Kategori D)
     * POST /admin/konfigurasi/sistem
     */
    public function updateSistem(): void
    {
        $userId = (int)(Session::get('user')['id'] ?? 1);

        $maintenance = $this->request->post('maintenance_mode') === '1' ? '1' : '0';
        $sessionLifetimeMinutes = (int)$this->request->post('session_lifetime_minutes', 120);
        $appDebug = $this->request->post('app_debug') === '1' ? '1' : '0';

        if ($sessionLifetimeMinutes < 15 || $sessionLifetimeMinutes > 1440) {
            Session::setFlash('error', 'Waktu aktif sesi harus antara 15 sampai 1440 menit (24 jam).');
            $this->response->redirect(base_url('admin/konfigurasi?tab=sistem'));
            return;
        }

        $sessionLifetimeSeconds = $sessionLifetimeMinutes * 60;

        $settingsSaved = true;
        $settingsSaved = AppSetting::set('maintenance_mode', $maintenance, 'sistem', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('session_lifetime', $sessionLifetimeSeconds, 'sistem', false, $userId) && $settingsSaved;
        $settingsSaved = AppSetting::set('app_debug', $appDebug, 'sistem', false, $userId) && $settingsSaved;

        if (!$settingsSaved) {
            Session::setFlash('error', 'Pengaturan sistem gagal disimpan. Periksa log server dan coba kembali.');
            $this->response->redirect(base_url('admin/konfigurasi?tab=sistem'));
            return;
        }

        // Catat di Audit Trail
        AuditLog::logActivity(
            $userId,
            'UPDATE_CONFIG_SISTEM',
            'app_settings',
            null,
            null,
            [
                'maintenance_mode' => $maintenance,
                'session_lifetime' => $sessionLifetimeSeconds,
                'app_debug'        => $appDebug
            ]
        );

        $modeText = $maintenance === '1' ? 'Mode Pemeliharaan DIAKTIFKAN.' : 'Mode Pemeliharaan Dinonaktifkan.';
        Session::setFlash('success', "Pengaturan Sistem berhasil disimpan. {$modeText}");
        $this->response->redirect(base_url('admin/konfigurasi?tab=sistem'));
    }
}
