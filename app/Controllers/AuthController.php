<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Models\AuditLog;

class AuthController extends Controller
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
     * Menampilkan Form Login
     */
    public function showLoginForm(): void
    {
        $this->render('auth/login', [
            'title' => 'Login Sivitas Akademika - STMIK IKMI Cirebon'
        ], 'auth');
    }

    /**
     * Memproses Otentikasi Login
     */
    public function login(): void
    {
        $username = trim((string)$this->request->post('username'));
        $password = (string)$this->request->raw('password');

        // Validasi format input
        $validation = $this->validate([
            'username' => $username,
            'password' => $password
        ], [
            'username' => 'required',
            'password' => 'required|min:4'
        ]);

        if (!$validation['isValid']) {
            $errorMsg = $validation['errors']['username'][0] ?? $validation['errors']['password'][0] ?? 'Input tidak valid.';
            Session::setFlash('error', $errorMsg);
            $this->redirect(base_url('login'));
            return;
        }

        try {
            // Cari user berdasarkan username (NIM / NIDN / username admin)
            $user = $this->userModel->findByUsername($username);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Periksa apakah akun aktif
                if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $this->auditLog->logActivity(
                        (int)$user['id'],
                        'LOGIN_BLOCKED_INACTIVE',
                        'users',
                        (int)$user['id'],
                        null,
                        ['username' => $username, 'status' => 'inactive']
                    );

                    Session::setFlash('error', 'Akun Anda saat ini dinonaktifkan. Silakan hubungi bagian Akademik STMIK IKMI.');
                    $this->redirect(base_url('login'));
                    return;
                }

                // Ambil profil lengkap sesuai role (Mahasiswa, Dosen, Admin)
                $fullProfile = $this->userModel->getFullProfile((int)$user['id']);

                // Hapus hash password sebelum disimpan ke session untuk keamanan
                unset($fullProfile['password_hash']);

                // Perbarui timestamp login & catat audit log
                $this->userModel->updateLastLogin((int)$user['id']);
                $this->auditLog->logActivity(
                    (int)$user['id'],
                    'LOGIN_SUCCESS',
                    'users',
                    (int)$user['id'],
                    null,
                    ['role' => $user['role'], 'username' => $username]
                );

                // Regenerasi session ID untuk mencegah serangan Session Fixation
                Session::regenerate();
                Session::set('user', $fullProfile);

                // Tentukan nama sapaan
                $displayName = $fullProfile['nama_dengan_gelar'] ?? $fullProfile['nama_lengkap'] ?? $fullProfile['username'];

                // Pengalihan berdasarkan role pengguna
                $redirectMap = [
                    'admin'     => base_url('admin/dashboard'),
                    'dosen'     => base_url('dosen/dashboard'),
                    'mahasiswa' => base_url('mahasiswa/dashboard')
                ];

                $targetUrl = $redirectMap[$user['role']] ?? base_url('/');
                $this->redirect($targetUrl, [
                    'success' => "Selamat datang, {$displayName}! Anda berhasil masuk sebagai " . ucfirst($user['role']) . "."
                ]);
                return;
            }

            // Jika user tidak ditemukan atau password tidak cocok
            $this->auditLog->logActivity(
                null,
                'LOGIN_FAILED',
                'users',
                null,
                null,
                ['attempted_username' => $username]
            );

        } catch (\PDOException $e) {
            Session::setFlash('error', 'Koneksi basis data bermasalah. Silakan pastikan server MySQL telah aktif.');
            $this->redirect(base_url('login'));
            return;
        }

        Session::setFlash('error', 'NIM / NIDN / Username atau Password yang Anda masukkan tidak sesuai.');
        $this->redirect(base_url('login'));
    }

    /**
     * Memproses Logout
     */
    public function logout(): void
    {
        $user = Session::get('user');
        if ($user) {
            $this->auditLog->logActivity(
                (int)$user['id'],
                'LOGOUT_SUCCESS',
                'users',
                (int)$user['id']
            );
        }

        Session::destroy();
        $this->redirect(base_url('login'), [
            'success' => 'Anda telah berhasil keluar dari sistem presensi.'
        ]);
    }
}
