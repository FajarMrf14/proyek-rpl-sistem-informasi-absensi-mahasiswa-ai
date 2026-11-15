<?php

namespace App\Controllers\Mahasiswa;

use App\Core\Controller;
use App\Core\Session;
use App\Models\IzinSakit;
use App\Models\User;

class IzinController extends Controller
{
    private IzinSakit $izinModel;
    private User $userModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->izinModel = new IzinSakit();
        $this->userModel = new User();
    }

    public function submit(): void
    {
        $sessionUser = Session::get('user');
        $userId = (int)($sessionUser['id'] ?? 0);
        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;
        $mahasiswaId = (int)($user['mahasiswa_id'] ?? 0);

        if (!$mahasiswaId) {
            Session::setFlash('error', 'Akses ditolak.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        $jadwalId = (int) $this->request->post('jadwal_id');
        $tipe = $this->request->post('tipe');
        $tanggal = $this->request->post('tanggal');
        $alasan = trim($this->request->post('alasan') ?? '');

        if (!$jadwalId || !in_array($tipe, ['izin', 'sakit']) || !$tanggal || empty($alasan)) {
            Session::setFlash('error', 'Semua kolom wajib diisi dengan benar.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        // Upload File Processing
        $file = $_FILES['bukti_surat'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::setFlash('error', 'Gagal mengunggah file bukti surat.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        // Validation Ext & MIME
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) {
            Session::setFlash('error', 'Format file tidak didukung. Hanya PDF, JPG, PNG.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        if ($file['size'] > $maxSize) {
            Session::setFlash('error', 'Ukuran file maksimal 2MB.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        // Generate safe unique name
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/izin';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeName = sprintf('%s_%d_%d_%s.%s', $tipe, $mahasiswaId, $jadwalId, date('YmdHis'), $ext);
        $filePath = $uploadDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            Session::setFlash('error', 'Gagal menyimpan file ke server.');
            $this->response->redirect(base_url('mahasiswa/izin'));
            return;
        }

        // DB Insert
        $izinId = $this->izinModel->insert([
            'mahasiswa_id' => $mahasiswaId,
            'jadwal_id' => $jadwalId,
            'tipe' => $tipe,
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
            'alasan' => $alasan,
            'bukti_surat' => 'uploads/izin/' . $safeName,
            'file_original_name' => substr($file['name'], 0, 250),
            'file_size_kb' => round($file['size'] / 1024),
            'status_persetujuan' => 'pending'
        ]);

        // Notifikasi admin hanya di-enqueue setelah pengajuan tersimpan.
        // Kegagalan WhatsApp tidak boleh menggagalkan pengajuan izin/sakit.
        try {
            $jadwalRows = $this->izinModel->query(
                "SELECT mk.nama_mk, k.nama_kelas
                 FROM jadwal_kuliah jk
                 JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                 JOIN kelas k ON k.id = jk.kelas_id
                 WHERE jk.id = :jadwal_id
                 LIMIT 1",
                ['jadwal_id' => $jadwalId]
            );
            $jadwalInfo = $jadwalRows[0] ?? [];
            $namaMhs = (string) ($user['nama_lengkap'] ?? $user['username'] ?? 'Mahasiswa');
            $nimMhs = (string) ($user['nim'] ?? $user['username'] ?? '');

            $pesan = 'Pengajuan ' . ucfirst($tipe) . " baru menunggu persetujuan.\n"
                . "Mahasiswa: {$namaMhs}\n"
                . ($nimMhs !== '' ? "NIM: {$nimMhs}\n" : '')
                . 'Mata kuliah: ' . (string) ($jadwalInfo['nama_mk'] ?? '-') . "\n"
                . 'Kelas: ' . (string) ($jadwalInfo['nama_kelas'] ?? '-') . "\n"
                . "Tanggal: {$tanggal}\n"
                . 'Alasan: ' . mb_substr($alasan, 0, 180);

            (new \App\Services\WhatsAppNotificationService())->notifyAdmins(
                'admin_approval',
                'admin_approval:' . (int) $izinId,
                ['pesan' => $pesan, 'tanggal' => $tanggal]
            );
        } catch (\Throwable $e) {
            error_log('[WhatsApp] Gagal enqueue notifikasi pengajuan admin: ' . get_class($e));
        }

        Session::setFlash('success', 'Pengajuan ' . ucfirst($tipe) . ' berhasil dikirim dan menunggu persetujuan.');
        $this->response->redirect(base_url('mahasiswa/izin'));
    }
}
