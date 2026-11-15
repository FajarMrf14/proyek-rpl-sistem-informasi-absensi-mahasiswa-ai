<?php

namespace App\Core;

class Request
{
    private array $getParams;
    private array $postParams;
    private array $files;
    private array $server;
    private ?array $jsonBody = null;

    public function __construct()
    {
        $this->getParams = $_GET;
        $this->postParams = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;

        // Parse JSON body jika content-type application/json
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            $this->jsonBody = json_decode($rawInput, true) ?? [];
        }
    }

    /**
     * Mendapatkan HTTP Method (GET, POST, dll)
     */
    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        
        // Support method spoofing melalui _method
        if ($method === 'POST' && isset($this->postParams['_method'])) {
            return strtoupper($this->postParams['_method']);
        }

        return $method;
    }

    /**
     * Mendapatkan path URI yang diminta
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $scriptName = dirname($this->server['SCRIPT_NAME'] ?? '');

        // Hapus query string jika ada
        if (str_contains($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // Normalisasi path jika berada di dalam subfolder (contoh: /absensi_mahasiswa/public)
        if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName));
        }

        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    /**
     * Mendapatkan parameter GET yang disanitasi
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return isset($this->getParams[$key]) ? $this->sanitize($this->getParams[$key]) : $default;
    }

    /**
     * Mendapatkan parameter POST yang disanitasi
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return isset($this->postParams[$key]) ? $this->sanitize($this->postParams[$key]) : $default;
    }

    /**
     * Mendapatkan data dari JSON body
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            return $default;
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->jsonBody[$key] ?? $default;
    }

    /**
     * Mengambil seluruh input gabungan (GET, POST, JSON)
     */
    public function all(): array
    {
        $data = array_merge($this->getParams, $this->postParams);
        if ($this->jsonBody !== null) {
            $data = array_merge($data, $this->jsonBody);
        }

        return array_map([$this, 'sanitize'], $data);
    }

    /**
     * Mengambil raw input tanpa sanitasi (misal untuk password atau base64 gambar)
     */
    public function raw(string $key, mixed $default = null): mixed
    {
        if (isset($this->postParams[$key])) {
            return $this->postParams[$key];
        }
        if (isset($this->getParams[$key])) {
            return $this->getParams[$key];
        }
        if ($this->jsonBody !== null && isset($this->jsonBody[$key])) {
            return $this->jsonBody[$key];
        }
        return $default;
    }

    /**
     * Mengambil file upload
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Cek apakah request berupa AJAX / JSON Fetch
     */
    public function isAjax(): bool
    {
        return (!empty($this->server['HTTP_X_REQUESTED_WITH']) &&
            strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /**
     * Sanitasi input data string / array
     */
    private function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }

        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }

        return $data;
    }
}
