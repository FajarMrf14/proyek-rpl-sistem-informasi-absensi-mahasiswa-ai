<?php

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;

class RoleMiddleware implements Middleware
{
    private string $allowedRoles;

    /**
     * @param string $roles Role atau daftar role yang diizinkan (dipisah koma jika multiple, misal: 'admin,dosen')
     */
    public function __construct(?string $roles = '')
    {
        $this->allowedRoles = $roles ?? '';
    }

    public function handle(Request $request, callable $next): mixed
    {
        $user = Session::get('user');

        if (!$user) {
            Response::getCurrent()->redirect(base_url('login'));
            return null;
        }

        $rolesArray = array_map('trim', explode(',', $this->allowedRoles));

        if (!empty($this->allowedRoles) && !in_array($user['role'], $rolesArray)) {
            if ($request->isAjax()) {
                Response::getCurrent()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.'
                ], 403);
                return null;
            }

            // Tampilkan halaman 403 Forbidden
            $response = Response::getCurrent();
            $response->setStatusCode(403);
            $response->sendHeaders();

            $forbiddenView = dirname(__DIR__) . '/Views/errors/403.php';
            if (file_exists($forbiddenView)) {
                require $forbiddenView;
            } else {
                echo "<h1>403 Akses Ditolak</h1><p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>";
            }
            return null;
        }

        return $next($request);
    }
}
