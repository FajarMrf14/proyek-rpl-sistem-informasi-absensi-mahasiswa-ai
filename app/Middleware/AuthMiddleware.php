<?php

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!Session::has('user') || empty(Session::get('user')['id'])) {
            if ($request->isAjax()) {
                Response::getCurrent()->json([
                    'success' => false,
                    'message' => 'Sesi login telah berakhir. Silakan login kembali.'
                ], 401);
                return null;
            }

            Session::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
            Response::getCurrent()->redirect(base_url('login'));
            return null;
        }

        return $next($request);
    }
}
