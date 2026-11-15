<?php

use App\Core\Session;

if (!function_exists('auth_user')) {
    /**
     * Mengambil data user yang sedang login dari session
     */
    function auth_user(): ?array
    {
        return Session::get('user');
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * Cek apakah user sedang dalam kondisi login
     */
    function is_logged_in(): bool
    {
        return Session::has('user') && !empty(Session::get('user')['id']);
    }
}

if (!function_exists('user_role')) {
    /**
     * Mengambil role user saat ini ('admin', 'dosen', 'mahasiswa')
     */
    function user_role(): ?string
    {
        $user = auth_user();
        return $user['role'] ?? null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return user_role() === 'admin';
    }
}

if (!function_exists('is_dosen')) {
    function is_dosen(): bool
    {
        return user_role() === 'dosen';
    }
}

if (!function_exists('is_mahasiswa')) {
    function is_mahasiswa(): bool
    {
        return user_role() === 'mahasiswa';
    }
}
