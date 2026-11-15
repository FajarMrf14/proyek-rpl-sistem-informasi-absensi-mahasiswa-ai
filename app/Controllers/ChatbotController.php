<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\NvidiaChatbotService;

/**
 * Controller Chatbot AI Khusus Sistem Absensi STMIK IKMI Cirebon
 */
class ChatbotController extends Controller
{
    private User $userModel;
    private NvidiaChatbotService $chatbotService;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->userModel = new User();
        $this->chatbotService = new NvidiaChatbotService();
    }

    /**
     * Endpoint API POST /api/chatbot/message
     */
    public function sendMessage(): void
    {
        // 1. Verifikasi Autentikasi Pengguna
        $sessionUser = Session::get('user');
        if (empty($sessionUser) || empty($sessionUser['id'])) {
            $this->response->json([
                'success' => false,
                'message' => 'Akses tidak sah. Anda wajib login terlebih dahulu untuk menggunakan asisten AI.'
            ], 401);
            return;
        }

        $userId = (int)$sessionUser['id'];
        $user = $this->userModel->getFullProfile($userId) ?? $sessionUser;

        // 2. Ambil & Validasi Pesan Input
        $message = trim((string)($this->request->post('message') ?? $this->request->json('message') ?? ''));

        if (empty($message)) {
            $this->response->json([
                'success' => false,
                'message' => 'Pertanyaan tidak boleh kosong.'
            ], 422);
            return;
        }

        if (mb_strlen($message) > 500) {
            $this->response->json([
                'success' => false,
                'message' => 'Pertanyaan terlalu panjang (maksimal 500 karakter).'
            ], 422);
            return;
        }

        // 3. Rate Limiting Sederhana (Maksimal 1 pesan per 1 detik per pengguna)
        $now = microtime(true);
        $lastReq = (float)Session::get('last_chatbot_request_time', 0.0);
        if (($now - $lastReq) < 1.0) {
            $this->response->json([
                'success' => false,
                'message' => 'Mohon tunggu sejenak sebelum mengirim pesan berikutnya.'
            ], 429);
            return;
        }
        Session::set('last_chatbot_request_time', $now);

        // 4. Ambil Riwayat Sesi Percakapan & Konteks Query Database
        $history = Session::get('chatbot_history', []);
        if (!is_array($history)) {
            $history = [];
        }

        $chatbotContext = Session::get('chatbot_context', []);
        if (!is_array($chatbotContext)) {
            $chatbotContext = [];
        }

        // 5. Kirim ke Service Chatbot (query database deterministik + fallback AI)
        $result = $this->chatbotService->sendMessage($message, $user, $history, $chatbotContext);

        if (!$result['success']) {
            $this->response->json([
                'success'         => false,
                'message'         => $result['message'],
                'is_config_error' => $result['is_config_error'] ?? false
            ], 200);
            return;
        }

        // 6. Simpan Pesan Baru ke Riwayat Sesi (Maksimal 10 putaran) hanya jika bukan penolakan guard
        if (!in_array($result['source'] ?? '', ['context_guard', 'privacy_guard'], true)) {
            $history[] = ['role' => 'user', 'content' => $message];
            $history[] = ['role' => 'assistant', 'content' => $result['reply']];
            if (count($history) > 20) {
                $history = array_slice($history, -20);
            }
            Session::set('chatbot_history', $history);
        }

        // 7. Simpan konteks terstruktur untuk pertanyaan lanjutan.
        //    Konteks hanya diperbarui jika hasil benar-benar membawa konteks baru.
        if (!empty($result['context']) && is_array($result['context'])) {
            Session::set('chatbot_context', $result['context']);
        }

        $this->response->json([
            'success' => true,
            'reply'   => $result['reply'],
            'source'  => $result['source'] ?? 'gemini_ai'
        ]);
    }

    /**
     * Endpoint API POST /api/chatbot/reset
     * Mereset riwayat percakapan sesi pengguna
     */
    public function resetHistory(): void
    {
        Session::remove('chatbot_history');
        Session::remove('chatbot_context');
        $this->response->json([
            'success' => true,
            'message' => 'Riwayat percakapan berhasil direset.'
        ]);
    }
}
