<?php
/**
 * Component: Chatbot AI Floating Widget
 * STMIK IKMI Cirebon
 */
use App\Core\Session;

$user = Session::get('user', []);
$userDisplayName = !empty($user['nama_dengan_gelar']) ? $user['nama_dengan_gelar'] : (!empty($user['nama_lengkap']) ? $user['nama_lengkap'] : ($user['username'] ?? 'Pengguna'));
$userName = htmlspecialchars($userDisplayName);
$userRole = htmlspecialchars($user['role'] ?? 'mahasiswa');
?>

<!-- Chatbot Floating Button & Window Styles -->
<style>
.chatbot-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1050;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(30, 58, 138, 0.35);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    outline: none;
}
.chatbot-fab:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 12px 28px rgba(30, 58, 138, 0.45);
    color: #fff;
}
.chatbot-fab .fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 16px;
    height: 16px;
    background-color: #10b981;
    border: 2px solid #fff;
    border-radius: 50%;
}

.chatbot-window {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 520px;
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    z-index: 1050;
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
    animation: chatSlideUp 0.25s ease-out;
}
@keyframes chatSlideUp {
    from { opacity: 0; transform: translateY(16px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.chatbot-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chatbot-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background-color: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chatbot-footer {
    padding: 12px 16px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
}

/* Chat Message Bubbles */
.chat-msg {
    display: flex;
    gap: 8px;
    max-width: 85%;
    font-size: 0.85rem;
    line-height: 1.45;
}
.chat-msg.assistant {
    align-self: flex-start;
}
.chat-msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.chat-bubble {
    padding: 10px 14px;
    border-radius: 1rem;
    word-break: break-word;
}
.chat-msg.assistant .chat-bubble {
    background-color: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 0.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.chat-msg.user .chat-bubble {
    background-color: #2563eb;
    color: #ffffff;
    border-top-right-radius: 0.25rem;
}

/* Quick Suggestion Chips */
.quick-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}
.chip-btn {
    background: #e2e8f0;
    border: none;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 0.75rem;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s;
}
.chip-btn:hover {
    background: #cbd5e1;
    color: #0f172a;
}

/* Typing Indicator Animation */
.typing-dots {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
}
.typing-dots span {
    width: 6px;
    height: 6px;
    background-color: #64748b;
    border-radius: 50%;
    animation: typingBounce 1.4s infinite ease-in-out both;
}
.typing-dots span:nth-child(1) { animation-delay: -0.32s; }
.typing-dots span:nth-child(2) { animation-delay: -0.16s; }
@keyframes typingBounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

@media (max-width: 576px) {
    .chatbot-window {
        bottom: 86px;
        right: 12px;
        left: 12px;
        width: auto;
        height: 480px;
    }
}
</style>

<!-- Floating Button -->
<button type="button" id="chatbot-fab" class="chatbot-fab" title="Tanya Asisten AI Presensi" aria-label="Buka Chatbot AI">
    <i class="bi bi-robot fs-3"></i>
    <span class="fab-badge" title="Online"></span>
</button>

<!-- Chat Window Card -->
<div id="chatbot-window" class="chatbot-window" aria-hidden="true">
    <!-- Header -->
    <div class="chatbot-header">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white rounded-circle p-1 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-robot fs-6"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold fs-7 text-white">Asisten Data Kampus</h6>
                <div class="fs-9 text-white-50 d-flex align-items-center gap-1">
                    <span class="badge bg-success p-1 rounded-circle" style="font-size: 4px;"> </span> STMIK IKMI Cirebon
                </div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button type="button" id="btn-reset-chat" class="btn btn-sm text-white-50 p-1" title="Hapus Riwayat Chat">
                <i class="bi bi-arrow-counterclockwise fs-6"></i>
            </button>
            <button type="button" id="btn-close-chat" class="btn btn-sm text-white-50 p-1" title="Tutup">
                <i class="bi bi-x-lg fs-6"></i>
            </button>
        </div>
    </div>

    <!-- Body / Chat Logs -->
    <div id="chatbot-messages" class="chatbot-body">
        <!-- Welcome Bubble -->
        <div class="chat-msg assistant">
            <div class="chat-bubble">
                Halo <strong><?= $userName ?></strong>! 👋<br>
                Saya Asisten AI Presensi & Data Kampus STMIK IKMI Cirebon. Saya membaca data aktual dari database. Silakan tanyakan hal seputar:
                <div class="quick-chips mt-2">
                    <?php if ($userRole === 'dosen'): ?>
                        <button type="button" class="chip-btn" data-query="Siapa saya?">Siapa Saya?</button>
                        <button type="button" class="chip-btn" data-query="Apa tugas dosen dalam aplikasi ini?">Tugas Dosen</button>
                        <button type="button" class="chip-btn" data-query="Siapa mahasiswa dengan kehadiran sedikit?">Kehadiran Rendah</button>
                        <button type="button" class="chip-btn" data-query="Apa saja jadwal mengajar saya?">Jadwal Mengajar</button>
                        <button type="button" class="chip-btn" data-query="Berapa persentase kehadiran minimal untuk ikut UAS?">Syarat UAS</button>
                    <?php elseif ($userRole === 'admin'): ?>
                        <button type="button" class="chip-btn" data-query="Siapa yang terlambat bulan ini?">Terlambat Bulan Ini</button>
                        <button type="button" class="chip-btn" data-query="Berapa mahasiswa yang hadir hari ini?">Hadir Hari Ini</button>
                        <button type="button" class="chip-btn" data-query="Siapa yang paling sering terlambat?">Paling Sering Terlambat</button>
                        <button type="button" class="chip-btn" data-query="Siapa mahasiswa dengan kehadiran di bawah 75% semester ini?">Kehadiran &lt; 75%</button>
                        <button type="button" class="chip-btn" data-query="Bandingkan kehadiran bulan ini dengan bulan lalu">Bandingkan Bulan</button>
                    <?php else: ?>
                        <button type="button" class="chip-btn" data-query="Siapa saya?">Siapa Saya?</button>
                        <button type="button" class="chip-btn" data-query="Berapa persentase kehadiran minimal untuk ikut UAS?">Syarat UAS</button>
                        <button type="button" class="chip-btn" data-query="Bagaimana cara melakukan absensi?">Cara Absensi</button>
                        <button type="button" class="chip-btn" data-query="Kenapa lokasi GPS saya gagal di luar radius?">Kendala GPS</button>
                        <button type="button" class="chip-btn" data-query="Bagaimana cara kerja verifikasi wajah AI?">Wajah AI</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="chatbot-typing" class="px-3 py-1 bg-white border-top border-light d-none">
        <div class="typing-dots">
            <small class="text-muted fs-9 me-1">AI sedang mengetik</small>
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Footer Input Form -->
    <div class="chatbot-footer">
        <form id="form-chatbot" class="d-flex gap-2 align-items-center mb-0">
            <?= csrf_field() ?>
            <input type="text" id="chat-input" class="form-control form-control-sm rounded-pill px-3 fs-8" 
                   placeholder="Tanya seputar absensi..." maxlength="500" autocomplete="off" required>
            <button type="submit" id="btn-send-chat" class="btn btn-sm btn-primary rounded-circle d-flex align-items-center justify-content-center" 
                    style="width: 34px; height: 34px;" title="Kirim">
                <i class="bi bi-send-fill fs-8"></i>
            </button>
        </form>
    </div>
</div>

<!-- Chatbot Client-Side Script -->
<script>
(function() {
    const fab = document.getElementById('chatbot-fab');
    const win = document.getElementById('chatbot-window');
    const btnClose = document.getElementById('btn-close-chat');
    const btnReset = document.getElementById('btn-reset-chat');
    const form = document.getElementById('form-chatbot');
    const input = document.getElementById('chat-input');
    const msgBox = document.getElementById('chatbot-messages');
    const typing = document.getElementById('chatbot-typing');
    const btnSend = document.getElementById('btn-send-chat');

    let isOpen = false;

    // Toggle Chat Window
    function toggleChat() {
        isOpen = !isOpen;
        if (isOpen) {
            win.style.display = 'flex';
            win.setAttribute('aria-hidden', 'false');
            input.focus();
            scrollToBottom();
        } else {
            win.style.display = 'none';
            win.setAttribute('aria-hidden', 'true');
        }
    }

    fab?.addEventListener('click', toggleChat);
    btnClose?.addEventListener('click', toggleChat);

    function scrollToBottom() {
        msgBox.scrollTop = msgBox.scrollHeight;
    }

    // Append Message to UI
    function appendMessage(role, text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-msg ${role}`;

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        
        // Escape HTML tapi izinkan markdown sederhana seperti line breaks dan bolding
        let formatted = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        bubble.innerHTML = formatted;
        msgDiv.appendChild(bubble);
        msgBox.appendChild(msgDiv);
        scrollToBottom();
    }

    // Send Message AJAX
    async function sendMessage(text) {
        const query = text.trim();
        if (!query) return;

        appendMessage('user', query);
        input.value = '';
        input.disabled = true;
        btnSend.disabled = true;
        typing.classList.remove('d-none');
        scrollToBottom();

        try {
            const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
            const endpoint = '<?= base_url("api/chatbot/message") ?>';
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    message: query,
                    _csrf_token: csrfToken
                })
            });

            // Baca sebagai teks dulu supaya error PHP/redirect HTML
            // tidak tertutup oleh pesan generik "Gagal terhubung".
            
const raw = await res.text();
let data = null;

try {
    data = JSON.parse(raw);
} catch (e) {
    // Coba pulihkan JSON jika ada peringatan PHP sebelum JSON.
    const start = raw.indexOf('{');
    const end = raw.lastIndexOf('}');

    if (start !== -1 && end > start) {
        try {
            data = JSON.parse(raw.substring(start, end + 1));
        } catch (e2) {
            data = null;
        }
    }
}

if (!data || typeof data !== 'object') {
    console.error('[Chatbot] Respons asli server:', raw);

    const detail = raw
        .replace(/<script[\s\S]*?<\/script>/gi, '')
        .replace(/<style[\s\S]*?<\/style>/gi, '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 700);

    appendMessage(
        'assistant',
        'Respons server tidak valid. Detail: ' +
        (detail || 'Server mengirim respons kosong.')
    );
    return;
}


            if (!res.ok) {
                console.error('[Chatbot] HTTP error:', res.status, data);
                appendMessage('assistant', `⚠️ Server chatbot error HTTP ${res.status}: ${data.message || 'Permintaan gagal.'}`);
                return;
            }

            if (data.success) {
                appendMessage('assistant', data.reply);
            } else {
                appendMessage('assistant', `⚠️ ${data.message || 'Terjadi kesalahan saat memproses jawaban.'}`);
            }
        } catch (err) {
            console.error('[Chatbot] Gagal mengirim request:', err);
            appendMessage('assistant', `⚠️ Browser gagal menghubungi endpoint chatbot. Error: ${err.message || 'network error'}. Coba refresh halaman dan pastikan Laragon Apache berjalan.`);
        } finally {
            typing.classList.add('d-none');
            input.disabled = false;
            btnSend.disabled = false;
            input.focus();
        }
    }

    form?.addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    // Quick Chips delegation
    msgBox?.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('chip-btn')) {
            const query = e.target.getAttribute('data-query');
            if (query) {
                sendMessage(query);
            }
        }
    });

    // Reset History
    btnReset?.addEventListener('click', async function() {
        const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
        try {
            await fetch('<?= base_url("api/chatbot/reset") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf_token: csrfToken })
            });
            // Clear message UI except welcome
            const welcome = msgBox.firstElementChild;
            msgBox.innerHTML = '';
            if (welcome) msgBox.appendChild(welcome);
        } catch (err) {}
    });
})();
</script>
