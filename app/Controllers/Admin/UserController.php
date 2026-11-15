<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\FonnteService;

/**
 * Controller Manajemen User untuk Administrator
 */
class UserController extends Controller
{
    private User $userModel;
    private AuditLog $auditLog;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->userModel = new User();
        $this->auditLog = new AuditLog();
    }

    /**
     * Tampilkan daftar seluruh pengguna
     */
    public function index(): void
    {
        $db = \App\Core\Database::getConnection();
        $sql = "
            SELECT 
                u.id, 
                u.username, 
                u.email, 
                COALESCE(u.nama_lengkap, d.nama_lengkap, m.nama_lengkap, '') AS nama_lengkap,
                u.role, 
                u.is_active, 
                u.last_login_at,
                d.nidn, 
                m.nim,
                COALESCE(u.no_hp, m.no_hp, d.no_hp) AS no_hp
            FROM users u
            LEFT JOIN dosen d ON d.user_id = u.id
            LEFT JOIN mahasiswa m ON m.user_id = u.id
            ORDER BY FIELD(u.role, 'admin', 'dosen', 'mahasiswa'), u.id ASC
        ";
        $users = $db->query($sql)->fetchAll();

        $this->render('admin/users', [
            'title' => 'Manajemen User - STMIK IKMI Cirebon',
            'users' => $users
        ]);
    }

    /**
     * Update nama lengkap dan data profil pengguna
     */
    public function update(int $id): void
    {
        $rawNama = (string)($this->request->raw('nama_lengkap') ?? $this->request->post('nama_lengkap') ?? '');
        $namaLengkap = trim(strip_tags(html_entity_decode($rawNama, ENT_QUOTES, 'UTF-8')));
        $email = trim((string)$this->request->post('email'));

        if ($namaLengkap === '') {
            Session::setFlash('error', 'Nama lengkap pengguna tidak boleh kosong.');
            $this->redirect(base_url('admin/users'));
            return;
        }

        if (mb_strlen($namaLengkap) > 100) {
            Session::setFlash('error', 'Nama lengkap maksimal 100 karakter.');
            $this->redirect(base_url('admin/users'));
            return;
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            Session::setFlash('error', 'Pengguna tidak ditemukan.');
            $this->redirect(base_url('admin/users'));
            return;
        }

        $profile = $this->userModel->getFullProfile($id);
        $oldPhone = $profile['no_hp'] ?? null;
        $phoneProvided = $this->request->raw('no_hp', null) !== null
            && in_array($user['role'], ['mahasiswa', 'dosen', 'admin'], true);
        $normalizedPhone = null;

        if ($phoneProvided) {
            $rawPhone = trim(strip_tags(html_entity_decode(
                (string) $this->request->raw('no_hp', ''),
                ENT_QUOTES,
                'UTF-8'
            )));

            if ($rawPhone !== '') {
                $normalizedPhone = FonnteService::normalizePhone($rawPhone);
                if ($normalizedPhone === null) {
                    Session::setFlash(
                        'error',
                        'Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx, 628xxxxxxxxxx, atau +62xxxxxxxxxx.'
                    );
                    $this->redirect(base_url('admin/users'));
                    return;
                }
            }
        }

        // Simpan perubahan nama lengkap
        $this->userModel->updateFullName($id, $namaLengkap);

        // Update email jika diisi
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :id");
            $stmt->execute(['email' => $email, 'id' => $id]);
        }

        // Simpan nomor WhatsApp pada profil sesuai role:
        // mahasiswa.no_hp, dosen.no_hp, atau users.no_hp untuk admin.
        if ($phoneProvided) {
            $this->userModel->updateContactPhone($id, $normalizedPhone);
        }

        // Catat audit log perubahan tanpa menyimpan nomor lengkap.
        $currentAdmin = Session::get('user');
        $this->auditLog->logActivity(
            (int)($currentAdmin['id'] ?? 1),
            'USER_PROFILE_UPDATE',
            'users',
            $id,
            [
                'old_name' => $user['nama_lengkap'] ?? '',
                'old_phone_masked' => !empty($oldPhone) ? FonnteService::maskPhone((string) $oldPhone) : '',
            ],
            [
                'new_name' => $namaLengkap,
                'new_phone_masked' => !empty($normalizedPhone) ? FonnteService::maskPhone($normalizedPhone) : '',
            ]
        );

        // Jika yang diubah adalah akun admin yang sedang login, perbarui session aktif
        if ((int)($currentAdmin['id'] ?? 0) === $id) {
            $freshProfile = $this->userModel->getFullProfile($id);
            unset($freshProfile['password_hash']);
            Session::set('user', $freshProfile);
        }

        $message = "Data pengguna [{$user['username']}] berhasil diperbarui.";
        if ($phoneProvided) {
            $message .= $normalizedPhone !== null
                ? ' Nomor WhatsApp diperbarui.'
                : ' Nomor WhatsApp dikosongkan.';
        }

        Session::setFlash('success', $message);
        $this->redirect(base_url('admin/users'));
    }
}
