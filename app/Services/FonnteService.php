<?php

namespace App\Services;

use App\Models\AppSetting;
use Throwable;

/**
 * Klien tipis untuk endpoint resmi Fonnte.
 *
 * Keamanan:
 * - Token hanya dibaca dari AppSetting (terenkripsi) dan tidak pernah dikembalikan
 *   ke controller/view/log.
 * - Endpoint dikunci ke host resmi; redirect tidak diikuti agar token tidak
 *   terkirim ke host lain.
 * - Respons API disanitasi sebelum dikembalikan ke lapisan aplikasi.
 */
class FonnteService
{
    public const ENDPOINT = 'https://api.fonnte.com/send';
    public const DEVICE_ENDPOINT = 'https://api.fonnte.com/device';
    public const MAX_MESSAGE_LENGTH = 60000;

    private string $token;
    private int $timeout = 15;
    private int $connectTimeout = 5;

    public function __construct(?string $token = null)
    {
        if ($token !== null) {
            $this->token = trim($token);
            return;
        }

        try {
            $this->token = trim((string) AppSetting::get('fonnte_token', ''));
        } catch (Throwable) {
            $this->token = '';
        }
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * Normalisasi nomor Indonesia ke format internasional 62... tanpa "+".
     * Hanya satu nomor yang diterima; pemisah koma tidak diizinkan.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[\s\-\.\(\)]/', '', $phone) ?? '';
        if ($phone === '' || str_contains($phone, ',')) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (!preg_match('/^[0-9]{9,15}$/', $phone)) {
            return null;
        }

        if (str_starts_with($phone, '0')) {
            if (!str_starts_with($phone, '08')) {
                return null;
            }
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        if (!str_starts_with($phone, '62') || strlen($phone) < 10 || strlen($phone) > 15) {
            return null;
        }

        return $phone;
    }

    /**
     * Kirim satu pesan. Hasil "success" berarti API Fonnte menerima permintaan
     * dan biasanya menaruhnya di antrean, BUKAN bukti pesan sudah dibaca penerima.
     */
    public function send(string $target, string $message): array
    {
        if (!$this->isConfigured()) {
            return $this->failure('Token Fonnte belum dikonfigurasi.', 0, 0, false);
        }

        $normalizedTarget = self::normalizePhone($target);
        if ($normalizedTarget === null) {
            return $this->failure('Nomor tujuan tidak valid. Gunakan format 08xxxxxxxxxx atau 62xxxxxxxxxx.', 0, 0, false);
        }

        $message = trim($message);
        if ($message === '') {
            return $this->failure('Isi pesan tidak boleh kosong.', 0, 0, false);
        }

        if (mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            return $this->failure('Isi pesan melebihi batas 60.000 karakter.', 0, 0, false);
        }

        $payload = [
            'target'      => $normalizedTarget,
            'message'     => $message,
            // Nomor sudah dinormalisasi ke 62..., jadi matikan penambahan prefiks otomatis.
            'countryCode' => '0',
        ];

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return $this->failure('Ekstensi cURL tidak tersedia di server.', 0, 0, false);
        }

        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_USERAGENT      => 'AbsensiMahasiswa-Fonnte/1.0',
        ];

        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);

        $startedAt = microtime(true);
        $rawResponse = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        curl_close($ch);

        if ($rawResponse === false || $curlErrno !== 0) {
            // Timeout bersifat ambigu: permintaan bisa saja sudah diterima Fonnte.
            // Jangan retry otomatis untuk mencegah duplikasi.
            $retryable = in_array($curlErrno, [6, 7], true);
            return $this->failure(
                'Koneksi ke Fonnte gagal. Periksa koneksi internet server.',
                $httpCode,
                $latencyMs,
                $retryable,
                $curlErrno === 28
            );
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            return $this->failure(
                'Respons Fonnte tidak dapat dibaca sebagai JSON.',
                $httpCode,
                $latencyMs,
                $httpCode === 429 || $httpCode >= 500
            );
        }

        $apiStatus = $this->toBool($decoded['status'] ?? false);
        $process = $this->sanitizeText($decoded['process'] ?? '', 30);
        $detail = $this->sanitizeText($decoded['detail'] ?? '', 180);
        $requestId = $this->sanitizeIdentifier($decoded['requestid'] ?? null);

        if ($httpCode < 200 || $httpCode >= 300) {
            $reason = $this->sanitizeText($decoded['reason'] ?? '', 120);
            return $this->failure(
                $this->mapFailureReason($reason, $httpCode),
                $httpCode,
                $latencyMs,
                in_array($httpCode, [429, 500, 502, 503, 504], true)
            );
        }

        if (!$apiStatus) {
            $reason = $this->sanitizeText($decoded['reason'] ?? '', 120);
            return $this->failure(
                $this->mapFailureReason($reason, $httpCode),
                $httpCode,
                $latencyMs,
                $this->isRetryableReason($reason, $httpCode)
            );
        }

        $messageId = '';
        if (isset($decoded['id']) && is_array($decoded['id'])) {
            $messageId = $this->sanitizeIdentifier($decoded['id'][0] ?? null);
        } elseif (isset($decoded['id'])) {
            $messageId = $this->sanitizeIdentifier($decoded['id']);
        }

        return [
            'success'             => true,
            'api_accepted'        => true,
            'queued'              => true,
            'http_code'           => $httpCode,
            'latency_ms'          => $latencyMs,
            'provider_status'     => $process !== '' ? $process : 'pending',
            'provider_message_id' => $messageId,
            'request_id'          => $requestId,
            'detail'              => $detail,
            'error_message'       => null,
            'retryable'           => false,
            'ambiguous'           => false,
        ];
    }

    /**
     * Masking nomor untuk audit/UI. Tidak pernah mengembalikan nomor lengkap.
     */
    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        $len = strlen($digits);

        if ($len <= 4) {
            return str_repeat('*', max(4, $len));
        }

        return substr($digits, 0, 4) . str_repeat('*', max(4, $len - 6)) . substr($digits, -2);
    }

    private function failure(
        string $message,
        int $httpCode,
        int $latencyMs,
        bool $retryable,
        bool $ambiguous = false
    ): array {
        return [
            'success'             => false,
            'api_accepted'        => false,
            'queued'              => false,
            'http_code'           => $httpCode,
            'latency_ms'          => $latencyMs,
            'provider_status'     => null,
            'provider_message_id' => '',
            'request_id'          => '',
            'detail'              => '',
            'error_message'       => $message,
            'retryable'           => $retryable,
            'ambiguous'           => $ambiguous,
        ];
    }

    private function mapFailureReason(string $reason, int $httpCode): string
    {
        $normalized = strtolower(trim($reason));

        return match (true) {
            str_contains($normalized, 'token invalid') => 'Token Fonnte tidak valid atau sudah kedaluwarsa.',
            str_contains($normalized, 'target invalid') => 'Nomor tujuan ditolak oleh Fonnte.',
            str_contains($normalized, 'insufficient quota') => 'Kuota pengiriman Fonnte tidak mencukupi.',
            str_contains($normalized, 'device') && str_contains($normalized, 'disconnect') => 'Perangkat WhatsApp Fonnte sedang tidak terhubung.',
            str_contains($normalized, 'input invalid') => 'Parameter permintaan ditolak oleh Fonnte.',
            str_contains($normalized, 'json format invalid') => 'Format permintaan ditolak oleh Fonnte.',
            $httpCode === 401, $httpCode === 403 => 'Autentikasi Fonnte ditolak. Periksa token.',
            $httpCode === 429 => 'Batas/kuota pengiriman Fonnte tercapai (HTTP 429).',
            $httpCode >= 500 => 'Server Fonnte sedang bermasalah (HTTP ' . $httpCode . ').',
            default => 'Permintaan ditolak oleh Fonnte' . ($httpCode > 0 ? ' (HTTP ' . $httpCode . ')' : '') . '.',
        };
    }

    /**
     * Perangkat terputus dan gangguan sementara boleh dicoba ulang. Token atau
     * nomor yang ditolak tidak diulang karena tidak akan berubah dengan sendirinya.
     */
    private function isRetryableReason(string $reason, int $httpCode): bool
    {
        $normalized = strtolower(trim($reason));

        if ($httpCode === 429 || $httpCode >= 500) {
            return true;
        }

        foreach (['device disconnect', 'device disconnected', 'server issue', 'server down', 'server not responding'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Periksa status perangkat Fonnte tanpa mengirim pesan.
     * Token tidak pernah dikembalikan ke pemanggil.
     */
    public function getDeviceStatus(): array
    {
        $result = [
            'success' => false,
            'connected' => false,
            'device' => '',
            'name' => '',
            'quota' => null,
            'message' => 'Status perangkat tidak dapat diperiksa.',
        ];

        if (!$this->isConfigured()) {
            $result['message'] = 'Token Fonnte belum dikonfigurasi.';
            return $result;
        }

        if (!function_exists('curl_init')) {
            $result['message'] = 'Ekstensi cURL tidak tersedia di server.';
            return $result;
        }

        $ch = curl_init(self::DEVICE_ENDPOINT);
        if ($ch === false) {
            $result['message'] = 'Gagal menginisialisasi koneksi.';
            return $result;
        }

        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_USERAGENT      => 'AbsensiMahasiswa-Fonnte/1.0',
        ];

        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);
        $rawResponse = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false || $curlErrno !== 0) {
            $result['message'] = $curlErrno === 28
                ? 'Pemeriksaan perangkat melewati batas waktu.'
                : 'Koneksi ke Fonnte gagal.';
            return $result;
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            $result['message'] = 'Respons status perangkat tidak dapat dibaca.';
            return $result;
        }

        $connected = $this->toBool($decoded['status'] ?? false);
        $result['success'] = $httpCode >= 200 && $httpCode < 300;
        $result['connected'] = $connected;
        $result['device'] = $this->sanitizeText($decoded['device'] ?? '', 60);
        $result['name'] = $this->sanitizeText($decoded['name'] ?? '', 60);
        $quota = $decoded['quota'] ?? null;
        $result['quota'] = is_numeric($quota) ? (int) $quota : null;
        $result['message'] = $connected
            ? 'Perangkat WhatsApp Fonnte terhubung.'
            : $this->sanitizeText($decoded['reason'] ?? 'Perangkat WhatsApp Fonnte tidak terhubung.', 180);

        return $result;
    }

    private function sanitizeText(mixed $value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    private function sanitizeIdentifier(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string) $value) ?? '', 0, 100);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
