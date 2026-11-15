<?php

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;

class GuestMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (Session::has('user') && !empty(Session::get('user')['id'])) {
            $role = Session::get('user')['role'] ?? 'mahasiswa';

            $targetUrl = match ($role) {
                'admin'     => base_url('admin/dashboard'),
                'dosen'     => base_url('dosen/dashboard'),
                'mahasiswa' => base_url('mahasiswa/dashboard'),
                default     => base_url('/')
            };

            Response::getCurrent()->redirect($targetUrl);
            return null;
        }

        return $next($request);
    }
}
