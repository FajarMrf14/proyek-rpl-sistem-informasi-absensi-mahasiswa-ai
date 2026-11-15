<?php

namespace App\Core;

class Response
{
    private static ?Response $current = null;
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    public function __construct()
    {
        self::$current = $this;
    }

    /**
     * Mengambil instance Response yang sedang aktif
     */
    public static function getCurrent(): self
    {
        if (self::$current === null) {
            self::$current = new self();
        }
        return self::$current;
    }

    /**
     * Set HTTP status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Ambil HTTP status code saat ini
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Tambahkan HTTP Header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Ambil seluruh headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Kirim respons JSON
     */
    public function json(mixed $data, int $status = 200): void
    {
        $this->setStatusCode($status);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->content = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->sendHeaders();
        echo $this->content;
        
        if (!defined('TESTING_MODE')) {
            exit;
        }
    }

    /**
     * Mengambil body respons JSON terakhir.
     * Dipakai oleh automated test; pada request web normal body langsung dikirim ke browser.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Redirect ke URL tertentu
     */
    public function redirect(string $url, int $status = 302): void
    {
        $this->setStatusCode($status);
        $this->setHeader('Location', $url);
        $this->sendHeaders();

        if (!defined('TESTING_MODE')) {
            exit;
        }
    }

    /**
     * Kirim headers yang tersimpan
     */
    public function sendHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
    }
}
