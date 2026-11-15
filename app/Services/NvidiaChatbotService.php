<?php

namespace App\Services;

use App\Config\App as AppConfig;
use App\Core\Database;
use App\Core\Env;
use App\Helpers\GeoHelper;
use PDO;
use Throwable;

class NvidiaChatbotService
{
    public const REFUSAL_MESSAGE = 'Maaf, saya adalah Asisten Virtual Presensi STMIK IKMI Cirebon. Saya hanya dapat menjawab pertanyaan seputar sistem absensi mahasiswa, jadwal kuliah, riwayat kehadiran, pengajuan izin/sakit, fitur aplikasi (GPS & verifikasi wajah AI), serta identitas akun Anda.';

    private string $provider;
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout = 60;
    private ChatbotDatabaseService $databaseService;

    public function __construct()
    {
        $this->databaseService = new ChatbotDatabaseService();
        $config = AppConfig::config()['chatbot'] ?? [];

        $this->provider = trim((string) ($config['provider'] ?? 'google_gemini'));
        if ($this->provider === '') {
            $this->provider = 'google_gemini';
        }

        $this->apiKey = trim(
            (string) ($config['api_key'] ?? '')
        );

        if ($this->apiKey === '') {
            $this->apiKey = trim(
                (string) Env::get('GEMINI_API_KEY', '')
            );
        }

        $this->model = trim(
            (string) ($config['model'] ?? Env::get('GEMINI_MODEL', 'gemini-3.6-flash'))
        );

        if ($this->model === '') {
            $this->model = 'gemini-3.6-flash';
        }

        $this->baseUrl = rtrim(
            trim(
                (string) ($config['base_url'] ?? Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'))
            ),
            '/'
        );
    }

    public function isConfigured(): bool
    {
        return $this->isProviderSupported() && $this->apiKey !== '';
    }

    private function isProviderSupported(): bool
    {
        return $this->provider === 'google_gemini';
    }

    /**
     * Memeriksa apakah pertanyaan keluar dari ranah sistem absensi STMIK IKMI Cirebon.
     */
    public function checkOutOfTopic(string $query): ?string
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        if ($q === '') {
            return null;
        }

        // 1. Permintaan penulisan kode / programming / script
        if (
            preg_match('/\b(buatkan|bikin|tuliskan|tulis|generate|create|koding|coding|contohkan)\b.*\b(kode|code|skrip|script|program|fungsi|function|class|method|algoritma|query|sintaks|syntax)\b/u', $q)
            || preg_match('/\b(script|kode|code|program)\s+(python|php|javascript|js|java|c\+\+|c\#|golang|rust|ruby|html|css|sql|bash|powershell)\b/u', $q)
            || preg_match('/\b(python|php|javascript|js|java|c\+\+|c\#|golang|rust|ruby|html|css|sql|bash|powershell)\s+(script|kode|code|program)\b/u', $q)
            || preg_match('/\b(buatkan|bikin|tuliskan|tulis)\b.*\b(python|php|javascript|java|c\+\+|c\#|golang|rust|ruby|html|css|sql)\b/u', $q)
            || preg_match('/\b(def\s+[a-z_]|import\s+numpy|console\.log|<script|<\?php|select\s+.*\s+from\s+users)\b/ui', $q)
        ) {
            return self::REFUSAL_MESSAGE;
        }

        // 2. Resep & Masakan
        if (preg_match('/\b(resep|masakan|memasak|bumbu|nasi goreng|resep kue|bahan masakan|kuliner)\b/u', $q)) {
            return self::REFUSAL_MESSAGE;
        }

        // 3. Matematika umum / kalkulus / rumus eksak non-presensi
        $qClean = trim(preg_replace('/[?!.,;:]+$/u', '', $q));
        if (
            preg_match('/^\s*\d+[\s\+\-\*\/x\^\(\)]+\d+[\s\+\-\*\/x\^\d\=\?\s]*$/u', $qClean)
            || preg_match('/\b(kalkulus|integral|turunan|aljabar|sin\s*\(|cos\s*\(|tan\s*\(|rumus fisika|rumus kimia|hitunglah\s+\d+|berapa\s+\d+)\b/u', $qClean)
        ) {
            return self::REFUSAL_MESSAGE;
        }

        // 4. Pengetahuan umum, politik, hiburan, dongeng, lelucon
        if (preg_match('/\b(presiden|menteri|pilpres|pemilu|partai|politik|dpr|ibukota|cuaca hari ini|gempa hari ini|zodiak|horoskop|ramalan|chord gitar|lirik lagu|sinopsis film|cerpen|dongeng|puisi|lelucon|joke|tebak-tebakan|cheat game)\b/u', $qClean)) {
            return self::REFUSAL_MESSAGE;
        }

        // 5. Validasi sinyal domain presensi STMIK IKMI
        $domainSignals = [
            'absen', 'absensi', 'presensi', 'hadir', 'kehadiran', 'terlambat', 'telat', 'jam', 'masuk', 'pulang',
            'alpha', 'alpa', 'izin', 'sakit', 'surat', 'dispensasi', 'rekap', 'riwayat', 'persentase', 'syarat', 'uas',
            'gps', 'lokasi', 'jarak', 'radius', 'meter', 'geofence', 'geofencing', 'koordinat', 'peta', 'map',
            'wajah', 'face', 'kamera', 'foto', 'selfie', 'biometrik', 'descriptor', 'verifikasi', 'deteksi', 'scan',
            'kuliah', 'jadwal', 'kelas', 'mata kuliah', 'matkul', 'ruangan', 'lab', 'dosen', 'mahasiswa', 'nim', 'nidn',
            'prodi', 'jurusan', 'semester', 'akademik', 'krs', 'pertemuan', 'materi', 'stmik', 'ikmi', 'cirebon', 'kampus',
            'aplikasi', 'sistem', 'web', 'error', 'kendala', 'gagal', 'bantuan', 'help', 'tutorial', 'cara', 'panduan',
            'profil', 'akun', 'user', 'login', 'logout', 'password', 'sandi', 'kamu', 'anda', 'saya', 'aku', 'siapa',
            'tugas', 'fungsi', 'peran', 'kewenangan', 'wewenang', 'mengajar', 'pengampu', 'sedikit', 'rendah',
            'halo', 'hai', 'assalamualaikum', 'pagi', 'siang', 'sore', 'malam', 'terima kasih', 'makasih', 'tes', 'ping'
        ];

        $hasSignal = false;
        foreach ($domainSignals as $sig) {
            if (mb_strpos($qClean, $sig) !== false) {
                $hasSignal = true;
                break;
            }
        }

        // Sinyal tambahan untuk pertanyaan data administratif yang menyebut
        // periode/angka, misalnya "tampilkan data September 2026".
        if (!$hasSignal
            && preg_match('/\b(data|tampilkan|lihat|berapa|jumlah|total|bandingkan|perbandingan|rekap|ringkasan|statistik)\b/ui', $qClean)
            && preg_match('/\b(mahasiswa|dosen|admin|absensi|presensi|kehadiran|kelas|jadwal|mata kuliah|matkul|semester|tahun|bulan|minggu|tanggal|[0-9]{4})\b/ui', $qClean)
        ) {
            $hasSignal = true;
        }

        if (!$hasSignal) {
            return self::REFUSAL_MESSAGE;
        }

        return null;
    }

    /**
     * Memeriksa perlindungan privasi data antar mahasiswa.
     */
    public function checkPrivacyGuard(
        string $query,
        array $currentUser
    ): ?string {
        $role = strtolower((string) ($currentUser['role'] ?? 'mahasiswa'));
        $q = mb_strtolower(trim($query), 'UTF-8');

        if ($role === 'mahasiswa') {
            $myNim = (string) ($currentUser['nim'] ?? '');

            // Deteksi jika mahasiswa menanyakan NIM lain
            if (
                preg_match('/\bnim\s*:?\s*([a-z0-9-]{5,20})\b/u', $q, $matches)
                && $myNim !== ''
                && strcasecmp($matches[1], $myNim) !== 0
            ) {
                return 'Sebagai mahasiswa, Anda hanya memiliki hak akses untuk melihat data pribadi, jadwal, dan riwayat presensi milik Anda sendiri.';
            }

            // Deteksi jika mahasiswa meminta data orang lain / kelas orang lain / peringkat kehadiran teman
            if (
                preg_match('/\b(mahasiswa lain|teman saya|teman sekelas|orang lain|user lain|akun lain|semua mahasiswa|kehadiran sedikit|kehadiran paling sedikit|kehadiran rendah)\b/u', $q)
                && preg_match('/\b(presensi|absensi|kehadiran|riwayat|profil|data|nilai|rekap|foto|wajah|izin|sakit|siapa)\b/u', $q)
            ) {
                return 'Sebagai mahasiswa, Anda hanya memiliki hak akses untuk melihat data pribadi, jadwal, dan riwayat presensi milik Anda sendiri.';
            }
        }

        return null;
    }

    /**
     * Menyelesaikan pertanyaan data aplikasi secara langsung dan aman dari database & spesifikasi resmi.
     */
    public function resolveApplicationQuery(string $query, array $currentUser): ?array
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $qClean = trim(preg_replace('/[?!.,;:]+$/u', '', $q));
        $role = strtolower((string) ($currentUser['role'] ?? 'mahasiswa'));
        $minAttendance = (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
        $minAttendanceText = number_format($minAttendance, 2);
        $campusRadius = (int) (GeoHelper::getCampusConfig()['radius_meters'] ?? 100);

        // -----------------------------------------------------------------------------
        // 1. Pertanyaan: Siapa mahasiswa dengan kehadiran paling sedikit / rendah
        // -----------------------------------------------------------------------------
        $isLowAttendance = (
            preg_match('/\b(kehadiran|hadir|absensi|presensi)\b.*\b(sedikit|paling\s+sedikit|rendah|paling\s+rendah|terendah|kurang|minim|di\s+bawah|belum\s+memenuhi)\b/ui', $qClean)
            || preg_match('/\b(siapa|siapa\s+saja|daftar)\b.*\b(mahasiswa|anak)\b.*\b(kehadiran|hadir|absensi|presensi)\b.*\b(sedikit|rendah|terendah|kurang)\b/ui', $qClean)
            || preg_match('/\b(mahasiswa\s+kehadiran\s+sedikit|kehadiran\s+sedikit|kehadiran\s+paling\s+sedikit|persentase\s+rendah)\b/ui', $qClean)
            || (preg_match('/\b(siapa|siapa\s+saja|daftar)\s+mahasiswa\b/ui', $qClean) && preg_match('/\b(belum\s+memenuhi\s+syarat\s+uas|tidak\s+ikut\s+uas|tidak\s+memenuhi\s+uas)\b/ui', $qClean))
        );

        if ($isLowAttendance) {
            if ($role === 'mahasiswa') {
                return [
                    'success' => true,
                    'reply'   => 'Sebagai mahasiswa, Anda hanya memiliki hak akses untuk melihat data pribadi, jadwal, dan riwayat presensi milik Anda sendiri.',
                    'source'  => 'privacy_guard'
                ];
            }

            try {
                $db = Database::getConnection();

                if ($role === 'dosen') {
                    $dosenId = (int) ($currentUser['dosen_id'] ?? 0);
                    if ($dosenId <= 0 && !empty($currentUser['id'])) {
                        $stmtU = $db->prepare('SELECT id FROM dosen WHERE user_id = :uid LIMIT 1');
                        $stmtU->execute(['uid' => (int) $currentUser['id']]);
                        $dosenId = (int) ($stmtU->fetchColumn() ?: 0);
                    }

                    $sql = "
                        SELECT * 
                        FROM v_rekap_kehadiran_mahasiswa
                        WHERE jadwal_id IN (SELECT id FROM jadwal_kuliah WHERE dosen_id = :did AND is_active = 1)
                        ORDER BY total_pertemuan_selesai DESC, persentase_kehadiran ASC, nama_mahasiswa ASC
                    ";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['did' => $dosenId]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Admin: dapat melihat seluruh kelas
                    $sql = "
                        SELECT * 
                        FROM v_rekap_kehadiran_mahasiswa
                        ORDER BY total_pertemuan_selesai DESC, persentase_kehadiran ASC, nama_mahasiswa ASC
                    ";
                    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                }

                if (empty($rows)) {
                    return [
                        'success' => true,
                        'reply'   => 'Saat ini belum ada data kelas atau mahasiswa aktif yang tercatat pada sistem presensi.',
                        'source'  => 'app_database'
                    ];
                }

                // Saring kelas yang sudah menyelenggarakan pertemuan perkuliahan selesai (total_pertemuan_selesai > 0)
                $classesWithMeetings = array_values(array_filter($rows, fn($r) => (int) ($r['total_pertemuan_selesai'] ?? 0) > 0));

                if (empty($classesWithMeetings)) {
                    $reply = "Berdasarkan data sistem presensi STMIK IKMI Cirebon saat ini:\n\n";
                    $reply .= "Seluruh jadwal perkuliahan aktif belum menyelenggarakan sesi pertemuan yang telah ditutup/selesai (total pertemuan selesai = 0).\n";
                    $reply .= "Oleh karena itu, persentase kehadiran seluruh mahasiswa masih berada pada nilai awal (100.00%) dan belum ada catatan kehadiran rendah yang tercatat di database.";

                    return [
                        'success' => true,
                        'reply'   => $reply,
                        'source'  => 'app_database'
                    ];
                }

                // Ambil mahasiswa dengan kehadiran terendah atau di bawah syarat UAS
                $lowStudents = array_values(array_filter($classesWithMeetings, function ($r) use ($minAttendance) {
                    return (float) ($r['persentase_kehadiran'] ?? 0.0) < $minAttendance;
                }));

                if (empty($lowStudents)) {
                    // Jika semua sudah memenuhi batas, tampilkan yang terendah dari yang ada
                    $lowStudents = array_slice($classesWithMeetings, 0, 5);
                }

                $reply = "Berdasarkan data rekapitulasi kehadiran resmi pada mata kuliah yang telah menyelenggarakan pertemuan, mahasiswa dengan persentase kehadiran paling sedikit adalah:\n\n";
                $no = 1;
                foreach ($lowStudents as $r) {
                    $persen = number_format((float) $r['persentase_kehadiran'], 2);
                    $uasStatus = ((float) $r['persentase_kehadiran'] >= $minAttendance)
                        ? 'Memenuhi syarat UAS'
                        : "Belum memenuhi syarat UAS (< {$minAttendanceText}%)";

                    $tot = (int) $r['total_pertemuan_selesai'];
                    $hadir = (int) $r['total_hadir'];
                    $terlambat = (int) $r['total_terlambat'];
                    $izin = (int) $r['total_izin'];
                    $sakit = (int) $r['total_sakit'];
                    $alpha = (int) $r['total_alpha'];

                    $reply .= "{$no}. **{$r['nama_mahasiswa']}** (NIM: {$r['nim']})\n";
                    $reply .= "   * **Mata Kuliah:** {$r['nama_mk']} ({$r['nama_kelas']})\n";
                    $reply .= "   * **Dosen Pengampu:** {$r['nama_dosen']}\n";
                    $reply .= "   * **Persentase Kehadiran:** **{$persen}%**\n";
                    $reply .= "   * **Rincian Presensi:** Hadir: {$hadir}, Terlambat: {$terlambat}, Izin: {$izin}, Sakit: {$sakit}, Alpha: {$alpha} (dari {$tot} pertemuan selesai)\n";
                    $reply .= "   * **Status Syarat UAS:** {$uasStatus}\n\n";
                    $no++;
                }

                $reply .= "📌 **Dasar Perhitungan Kehadiran:**\n";
                $reply .= "- Persentase dihitung dengan rumus: `(Total Hadir + Terlambat) / Total Pertemuan Selesai × 100%`.\n";
                $reply .= "- Berdasarkan konfigurasi akademik aplikasi, batas minimal kehadiran untuk dapat mengikuti Ujian Akhir Semester (UAS) adalah **{$minAttendanceText}%**. Mahasiswa dengan kehadiran di bawah batas tersebut dinyatakan belum memenuhi syarat UAS.";

                return [
                    'success' => true,
                    'reply'   => $reply,
                    'source'  => 'app_database'
                ];
            } catch (Throwable $e) {
                error_log('[Chatbot] Gagal memproses query kehadiran: ' . $e->getMessage());
            }
        }

        // -----------------------------------------------------------------------------
        // 2. Pertanyaan: Apa tugas dosen dalam aplikasi ini
        // -----------------------------------------------------------------------------
        $isDosenDuties = (
            preg_match('/\b(apa\s+tugas\s+dosen|tugas\s+dosen\s+apa|fungsi\s+dosen|peran\s+dosen|tanggung\s+jawab\s+dosen|kewenangan\s+dosen)\b/ui', $qClean)
            || (preg_match('/\b(apa\s+saja|tugas)\b/ui', $qClean) && preg_match('/\bdosen\b/ui', $qClean) && preg_match('/\b(aplikasi|sistem|ikmi)\b/ui', $qClean))
            || preg_match('/^apa tugas dosen\??$/ui', trim($qClean))
        );

        if ($isDosenDuties) {
            $reply = "Dalam Sistem Absensi Mahasiswa STMIK IKMI Cirebon, tugas dan wewenang dosen meliputi:\n\n"
                . "1. **Monitoring Jadwal & Kelas Mengajar**:\n"
                . "   Melihat daftar jadwal perkuliahan aktif yang diampu, mencakup kode dan nama mata kuliah, kelas, bobot SKS, ruangan, serta jam mulai dan selesai perkuliahan.\n\n"
                . "2. **Memantau Pertemuan Perkuliahan**:\n"
                . "   Melihat riwayat dan agenda sesi pertemuan kuliah pada kelas yang diampu, tanggal pelaksanaan, materi pembahasan, serta jumlah mahasiswa yang telah melakukan absensi pada setiap pertemuan.\n\n"
                . "3. **Melihat Rekapitulasi Presensi & Evaluasi Syarat UAS**:\n"
                . "   Memantau rekapitulasi kehadiran mahasiswa pada kelas yang diampu secara real-time (jumlah hadir, terlambat, izin, sakit, dan alpha), persentase kehadiran, serta status pemenuhan syarat minimal kehadiran UAS ({$minAttendanceText}%).\n\n"
                . "4. **Meninjau Pengajuan Izin/Sakit Mahasiswa**:\n"
                . "   Memantau permohonan surat izin atau sakit yang diajukan oleh mahasiswa pada jadwal mata kuliah yang diampunya.\n\n"
                . "*(Catatan: Presensi perkuliahan dilakukan langsung secara mandiri oleh mahasiswa melalui 4 tahapan: validasi jadwal, GPS radius kampus {$campusRadius}m, verifikasi biometrik wajah AI, dan upload foto kegiatan).*";

            return [
                'success' => true,
                'reply'   => $reply,
                'source'  => 'app_knowledge'
            ];
        }

        // -----------------------------------------------------------------------------
        // 3. Pertanyaan: Siapa dosen yang mengajar kelas tertentu / mata kuliah tertentu
        // -----------------------------------------------------------------------------
        $isLecturerQuery = (
            preg_match('/\b(siapa\s+dosen\s+yang\s+mengajar|siapa\s+dosen\s+pengampu|dosen\s+pengampu\s+mata\s+kuliah|dosen\s+pengampu\s+matkul|dosen\s+yang\s+mengajar|siapa\s+dosen\s+kelas)\b/ui', $qClean)
            || (preg_match('/\b(siapa\s+dosen)\b/ui', $qClean) && preg_match('/\b(mengajar|kelas|matkul|mata kuliah)\b/ui', $qClean))
        );

        if ($isLecturerQuery) {
            try {
                $db = Database::getConnection();

                $sql = "
                    SELECT
                        jk.id,
                        d.nama_lengkap AS nama_dosen,
                        d.gelar_depan,
                        d.gelar_belakang,
                        d.nidn,
                        mk.kode_mk,
                        mk.nama_mk,
                        mk.sks,
                        k.nama_kelas,
                        jk.hari,
                        jk.jam_mulai,
                        jk.jam_selesai,
                        jk.ruangan
                    FROM jadwal_kuliah jk
                    JOIN dosen d ON d.id = jk.dosen_id
                    JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                    JOIN kelas k ON k.id = jk.kelas_id
                    WHERE jk.is_active = 1
                    ORDER BY k.nama_kelas, jk.hari, jk.jam_mulai
                ";
                $schedules = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

                if (empty($schedules)) {
                    return [
                        'success' => true,
                        'reply'   => 'Saat ini belum ada data jadwal dan dosen pengampu aktif yang terdaftar di sistem.',
                        'source'  => 'app_database'
                    ];
                }

                // Cek apakah user menanyakan kelas spesifik (misal TI-2023-A)
                $filtered = $schedules;
                if (preg_match('/\b(ti-2023-[a-z]|si-2023-[a-z])\b/ui', $qClean, $mKls)) {
                    $klsTarget = strtoupper($mKls[1]);
                    $filtered = array_values(array_filter($schedules, fn($s) => strtoupper($s['nama_kelas']) === $klsTarget));
                }

                $reply = "Daftar dosen pengampu pada jadwal perkuliahan aktif STMIK IKMI Cirebon:\n\n";
                $no = 1;
                foreach ($filtered as $s) {
                    $namaDosen = trim(($s['gelar_depan'] ? $s['gelar_depan'] . ' ' : '') . $s['nama_dosen'] . ($s['gelar_belakang'] ? ', ' . $s['gelar_belakang'] : ''));
                    $reply .= "{$no}. **{$namaDosen}** (NIDN: {$s['nidn']})\n";
                    $reply .= "   * **Mata Kuliah:** {$s['nama_mk']} ({$s['kode_mk']} - {$s['sks']} SKS)\n";
                    $reply .= "   * **Kelas:** {$s['nama_kelas']}\n";
                    $reply .= "   * **Jadwal:** {$s['hari']}, " . substr($s['jam_mulai'], 0, 5) . " - " . substr($s['jam_selesai'], 0, 5) . " WIB (Ruangan: {$s['ruangan']})\n\n";
                    $no++;
                }

                return [
                    'success' => true,
                    'reply'   => $reply,
                    'source'  => 'app_database'
                ];
            } catch (Throwable $e) {
                error_log('[Chatbot] Gagal memproses query dosen pengampu: ' . $e->getMessage());
            }
        }

        // -----------------------------------------------------------------------------
        // 4. Pertanyaan: Berapa syarat kehadiran UAS
        // -----------------------------------------------------------------------------
        $isUasRule = (
            preg_match('/\b(syarat|minimal|kehadiran\s+minimal|ketentuan|persentase|persen)\b.*\b(uas|ujian\s+akhir\s+semester)\b/ui', $qClean)
            || preg_match('/\b(uas|ujian\s+akhir\s+semester)\b.*\b(syarat|minimal|kehadiran|persentase|persen|berapa)\b/ui', $qClean)
            || preg_match('/\b(syarat\s+uas|ikut\s+uas|mengikuti\s+uas)\b/ui', $qClean)
        );

        if ($isUasRule) {
            $reply = "Berdasarkan konfigurasi akademik pada aplikasi absensi STMIK IKMI Cirebon, batas persentase minimal kehadiran mahasiswa untuk dapat mengikuti Ujian Akhir Semester (UAS) adalah **{$minAttendanceText}%**.\n\n"
                . "- **Status Memenuhi Syarat UAS**: Persentase kehadiran ≥ {$minAttendanceText}%.\n"
                . "- **Status Belum Memenuhi Syarat UAS**: Persentase kehadiran < {$minAttendanceText}%.\n\n"
                . "Persentase dihitung dari: `(Total Hadir + Terlambat) / Total Pertemuan Selesai × 100%`. Mahasiswa disarankan untuk memantau rekap presensi dan segera melengkapi permohonan izin/sakit berdokumen jika berhalangan hadir.";

            return [
                'success' => true,
                'reply'   => $reply,
                'source'  => 'app_knowledge'
            ];
        }

        return null;
    }

    /**
     * Membangun prompt sistem untuk Gemini dengan identitas dan instruksi konteks.
     */
    public function buildSystemPrompt(
        array $user,
        string $databaseContext = ''
    ): string {
        $role = strtolower(
            (string) ($user['role'] ?? 'mahasiswa')
        );

        $name = (string) (
            $user['nama_dengan_gelar']
            ?? $user['nama_lengkap']
            ?? $user['username']
            ?? 'Pengguna'
        );

        $minAttendance = (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
        $minAttendanceText = number_format($minAttendance, 2);
        $campusRadius = (int) (GeoHelper::getCampusConfig()['radius_meters'] ?? 100);

        $prompt = "Anda adalah Asisten Virtual Presensi STMIK IKMI Cirebon.\n";
        $prompt .= "Jawab dalam Bahasa Indonesia, secara natural, jelas, sopan, akurat, dan langsung sesuai pertanyaan.\n";
        $prompt .= "Jangan menambahkan komentar berlebihan, pengantar panjang, atau kesimpulan yang tidak diminta.\n";
        $prompt .= "Jawab pertanyaan identitas seperti 'kamu siapa?' dan 'saya siapa?' secara tepat berdasarkan identitas aplikasi dan sesi login pengguna.\n";
        $prompt .= "BATASAN KONTEKS MUTLAK: Anda HANYA boleh menjawab seputar sistem presensi mahasiswa STMIK IKMI Cirebon, jadwal kuliah, riwayat kehadiran, pengajuan izin/sakit, fitur GPS/geofencing, verifikasi wajah AI, dan kendala teknis aplikasi ini.\n";
        $prompt .= "TOLAK dengan sopan semua permintaan pembuatan kode pemrograman (Python, PHP, JavaScript, SQL, dll.), resep makanan, matematika umum, cerita/puisi fiksi, atau topik apa pun di luar presensi STMIK IKMI.\n";
        $prompt .= "INTEGRITAS DATA: Gunakan data pada KONTEKS DATABASE sebagai sumber kebenaran utama. JANGAN PERNAH mengarang (halusinasi) nama, NIM, NIDN, jumlah, tanggal, mata kuliah, status, atau statistik jika data tersebut tidak tercantum dalam konteks database. Jika data tidak tersedia, sampaikan bahwa data tersebut belum tercatat di sistem.\n";
        $prompt .= "ATURAN PERIODE: Jangan mencampur data antar tahun akademik/semester. Jika pengguna menyebut periode yang tidak jelas, tanyakan klarifikasi periode sebelum memberi angka. Jika tahun akademik aktif sudah melewati tanggal selesai, sebutkan bahwa periode itu sudah berakhir dan data tersebut bersifat historis; jangan menyebutnya sedang berlangsung.\n";
        $prompt .= "ATURAN ANGKA: Setiap angka yang Anda tulis harus ada di KONTEKS DATABASE. Jangan menghitung ulang atau mengarang angka yang tidak tersedia. Jika konteks hanya memuat sebagian data, katakan bahwa data yang tersedia terbatas.\n";
        $prompt .= "ATURAN JAWABAN JUMLAH: Jika pengguna bertanya jumlah dengan pola 'Berapa jumlah ...?' (seperti dosen, mahasiswa, mata kuliah, jadwal kelas, pengguna, keterlambatan), jawab HANYA dengan angka saja tanpa kata pengantar, label, penjelasan, atau tanda baca (contoh: 4).\n";
        $prompt .= "ATURAN DAFTAR DATA: Jika pengguna meminta daftar atau bertanya 'apa saja' atau 'siapa saja' (seperti 'Apa saja mata kuliah yang tersedia?', 'Siapa saja dosen yang terdaftar?', 'Apa saja jadwal kelas?', 'Siapa saja mahasiswa yang terdaftar?'), tampilkan daftar bernomor dari data aktual di KONTEKS DATABASE, bukan hanya angka.\n";
        $prompt .= "BATASAN HAK AKSES ROLE: Mahasiswa hanya boleh mengakses data dirinya sendiri. Dosen hanya boleh mengakses jadwal dan rekap kelas yang diampunya. Admin berhak mengakses seluruh data mahasiswa, dosen, kelas, jadwal, dan rekap kehadiran presensi di sistem.\n";
        $prompt .= "ATURAN RESMI SYARAT UAS: Berdasarkan konfigurasi akademik pada aplikasi absensi STMIK IKMI Cirebon, batas persentase minimal kehadiran mahasiswa untuk dapat mengikuti Ujian Akhir Semester (UAS) adalah {$minAttendanceText}%. Mahasiswa dengan kehadiran di bawah {$minAttendanceText}% dinyatakan belum memenuhi syarat UAS. Gunakan aturan ini jika ditanya syarat kehadiran UAS dan jangan mengarang persentase lain.\n";
        $prompt .= "KEAMANAN: Dilarang keras mengungkap password, password hash, API key, token rahasia, alamat IP, atau data rahasia lainnya.\n\n";

        $prompt .= "IDENTITAS PENGGUNA LOGIN\n";
        $prompt .= "Nama: {$name}\n";
        $prompt .= "Peran: {$role}\n";

        if (!empty($user['nim'])) {
            $prompt .= 'NIM: ' . $user['nim'] . "\n";
        }

        if (!empty($user['nidn'])) {
            $prompt .= 'NIDN: ' . $user['nidn'] . "\n";
        }

        if (!empty($user['prodi'])) {
            $prompt .= 'Program studi: ' . $user['prodi'] . "\n";
        }

        $prompt .= "\nKONTEKS DATABASE\n";
        $prompt .= $databaseContext !== ''
            ? $databaseContext
            : 'Data tambahan tidak tersedia.';

        $prompt .= "\n\nPENGETAHUAN PROYEK\n";
        $prompt .= "Aplikasi merupakan sistem presensi mahasiswa berbasis web dengan AI untuk STMIK IKMI Cirebon. Modulnya mencakup autentikasi akun, profil pengguna, jadwal kuliah, pertemuan perkuliahan, absensi dengan Geolocation GPS (radius {$campusRadius} meter kampus), verifikasi wajah biometrik (face-api.js), dan pengajuan izin/sakit dengan upload bukti.\n";
        $prompt .= "Untuk kendala GPS: sarankan cek izin lokasi browser dan pastikan berada dalam radius kampus STMIK IKMI (Jl. Perjuangan No.10B Cirebon). Untuk kendala kamera: periksa izin kamera browser dan pencahayaan wajah.\n";

        return $prompt;
    }

    /**
     * Membaca konteks database yang relevan sesuai role pengguna dan topik pertanyaan.
     */
    public function fetchDatabaseContext(array $user, string $query = ''): string
    {
        $role = strtolower(
            (string) ($user['role'] ?? 'mahasiswa')
        );

        $userId = (int) ($user['id'] ?? 0);
        $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
        $dosenId = (int) ($user['dosen_id'] ?? 0);

        $q = mb_strtolower(trim($query), 'UTF-8');
        $lines = [];
        $minAttendance = (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
        $minAttendanceText = number_format($minAttendance, 2);

        try {
            $db = Database::getConnection();

            $lines[] = 'Tanggal: ' . date('Y-m-d');
            $lines[] = 'Hari: ' . date('l');

            if ($role === 'mahasiswa') {
                if ($mahasiswaId <= 0 && $userId > 0) {
                    $stmt = $db->prepare('SELECT id FROM mahasiswa WHERE user_id = :uid LIMIT 1');
                    $stmt->execute(['uid' => $userId]);
                    $mahasiswaId = (int) ($stmt->fetchColumn() ?: 0);
                }

                if ($mahasiswaId > 0) {
                    $stmt = $db->prepare('SELECT nim, nama_lengkap, prodi, angkatan, status_akademik, face_registered FROM mahasiswa WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $mahasiswaId]);
                    $lines[] = 'Profil mahasiswa: ' . json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE);

                    $sqlJadwal = "
                        SELECT mk.kode_mk, mk.nama_mk, jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan, k.nama_kelas, d.nama_lengkap AS nama_dosen
                        FROM kelas_mahasiswa km
                        JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                        JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                        JOIN kelas k ON k.id = jk.kelas_id
                        JOIN dosen d ON d.id = jk.dosen_id
                        WHERE km.mahasiswa_id = :mid AND km.status = 'aktif' AND jk.is_active = 1
                        ORDER BY jk.hari, jk.jam_mulai LIMIT 20
                    ";
                    $stmt = $db->prepare($sqlJadwal);
                    $stmt->execute(['mid' => $mahasiswaId]);
                    $lines[] = 'Jadwal mahasiswa: ' . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

                    $sqlStat = "
                        SELECT COUNT(*) AS total_catatan,
                               COALESCE(SUM(status = 'hadir'), 0) AS hadir,
                               COALESCE(SUM(status = 'terlambat'), 0) AS terlambat,
                               COALESCE(SUM(status = 'izin'), 0) AS izin,
                               COALESCE(SUM(status = 'sakit'), 0) AS sakit,
                               COALESCE(SUM(status = 'alpha'), 0) AS alpha
                        FROM absensi WHERE mahasiswa_id = :mid
                    ";
                    $stmt = $db->prepare($sqlStat);
                    $stmt->execute(['mid' => $mahasiswaId]);
                    $lines[] = 'Statistik presensi mahasiswa: ' . json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE);

                    $sqlRekap = "SELECT * FROM v_rekap_kehadiran_mahasiswa WHERE mahasiswa_id = :mid";
                    $stmt = $db->prepare($sqlRekap);
                    $stmt->execute(['mid' => $mahasiswaId]);
                    $lines[] = 'Rekap kehadiran per mata kuliah: ' . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
                }
            } elseif ($role === 'dosen') {
                if ($dosenId <= 0 && $userId > 0) {
                    $stmt = $db->prepare('SELECT id FROM dosen WHERE user_id = :uid LIMIT 1');
                    $stmt->execute(['uid' => $userId]);
                    $dosenId = (int) ($stmt->fetchColumn() ?: 0);
                }

                if ($dosenId > 0) {
                    $stmt = $db->prepare('SELECT nidn, nama_lengkap, gelar_depan, gelar_belakang, email FROM dosen WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $dosenId]);
                    $lines[] = 'Profil dosen: ' . json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE);

                    $sqlJadwal = "
                        SELECT jk.id AS jadwal_id, mk.kode_mk, mk.nama_mk, jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan, k.nama_kelas,
                               (SELECT COUNT(*) FROM kelas_mahasiswa km WHERE km.jadwal_id = jk.id AND km.status = 'aktif') AS jumlah_mahasiswa
                        FROM jadwal_kuliah jk
                        JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                        JOIN kelas k ON k.id = jk.kelas_id
                        WHERE jk.dosen_id = :did AND jk.is_active = 1
                        ORDER BY jk.hari, jk.jam_mulai LIMIT 25
                    ";
                    $stmt = $db->prepare($sqlJadwal);
                    $stmt->execute(['did' => $dosenId]);
                    $lines[] = 'Jadwal dosen: ' . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

                    $sqlRekap = "
                        SELECT * 
                        FROM v_rekap_kehadiran_mahasiswa
                        WHERE jadwal_id IN (SELECT id FROM jadwal_kuliah WHERE dosen_id = :did AND is_active = 1)
                        ORDER BY persentase_kehadiran ASC LIMIT 30
                    ";
                    $stmt = $db->prepare($sqlRekap);
                    $stmt->execute(['did' => $dosenId]);
                    $lines[] = 'Rekap kehadiran kelas dosen: ' . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
                }
            } elseif ($role === 'admin') {
                $queries = [
                    'Ringkasan statistik sistem' =>
                        "SELECT 
                            (SELECT COUNT(*) FROM users) AS total_akun,
                            (SELECT COUNT(*) FROM mahasiswa) AS total_mahasiswa,
                            (SELECT COUNT(*) FROM dosen) AS total_dosen,
                            (SELECT COUNT(*) FROM jadwal_kuliah WHERE is_active = 1) AS jadwal_aktif",

                    'Daftar mahasiswa terdaftar' =>
                        "SELECT nim, nama_lengkap, prodi, angkatan, status_akademik
                         FROM mahasiswa ORDER BY nim ASC LIMIT 25",

                    'Daftar dosen pengampu' =>
                        "SELECT nidn, nama_lengkap, gelar_depan, gelar_belakang, email, no_hp
                         FROM dosen ORDER BY nama_lengkap ASC LIMIT 20",

                    'Daftar kelas' =>
                        "SELECT id, nama_kelas, prodi, angkatan FROM kelas ORDER BY nama_kelas ASC LIMIT 10",

                    'Jadwal perkuliahan aktif' => "
                        SELECT
                            mk.kode_mk,
                            mk.nama_mk,
                            k.nama_kelas,
                            d.nama_lengkap AS nama_dosen,
                            jk.hari,
                            jk.jam_mulai,
                            jk.jam_selesai,
                            jk.ruangan
                        FROM jadwal_kuliah jk
                        JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                        JOIN kelas k ON k.id = jk.kelas_id
                        JOIN dosen d ON d.id = jk.dosen_id
                        WHERE jk.is_active = 1
                        ORDER BY jk.hari, jk.jam_mulai
                        LIMIT 25
                    ",

                    "Rekap kehadiran mahasiswa dan evaluasi syarat UAS (minimal {$minAttendanceText}%)" => "
                        SELECT
                            nim,
                            nama_mahasiswa,
                            nama_mk,
                            nama_kelas,
                            total_pertemuan_selesai,
                            total_hadir,
                            total_terlambat,
                            total_izin,
                            total_sakit,
                            total_alpha,
                            persentase_kehadiran,
                            CASE 
                                WHEN persentase_kehadiran >= {$minAttendanceText} THEN 'Memenuhi syarat UAS'
                                ELSE 'Belum memenuhi syarat UAS (di bawah {$minAttendanceText}%)'
                            END AS status_syarat_uas
                        FROM v_rekap_kehadiran_mahasiswa
                        ORDER BY persentase_kehadiran ASC, nama_mk ASC
                        LIMIT 25
                    ",

                    'Pengajuan izin dan sakit mahasiswa terbaru' => "
                        SELECT
                            iz.id,
                            m.nim,
                            m.nama_lengkap,
                            iz.tipe,
                            iz.tanggal_mulai,
                            iz.tanggal_selesai,
                            iz.status_persetujuan,
                            iz.alasan
                        FROM izin_sakit iz
                        JOIN mahasiswa m ON m.id = iz.mahasiswa_id
                        ORDER BY iz.created_at DESC
                        LIMIT 15
                    "
                ];

                foreach ($queries as $label => $sql) {
                    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                    $lines[] = $label . ': ' . json_encode($rows, JSON_UNESCAPED_UNICODE);
                }
            }
        } catch (Throwable $e) {
            error_log('[Chatbot] Gagal membaca database: ' . $e->getMessage());
            $lines[] = 'Data database tambahan tidak tersedia pada permintaan ini. Jangan mengarang data.';
        }

        return implode("\n", $lines);
    }

    /**
     * Menganalisis dan mengklasifikasikan error yang diterima dari Gemini API atau cURL.
     */
    private function parseGeminiError(int $http, string $raw, string $curlError = ''): array
    {
        // 1. Error Jaringan / cURL
        if ($curlError !== '' || $raw === '') {
            $isTimeout = (str_contains($curlError, 'timed out') || str_contains($curlError, 'Operation timed out') || str_contains($curlError, '28'));
            $isDns = (str_contains($curlError, 'Could not resolve host') || str_contains($curlError, 'Failed to connect'));

            if ($isTimeout) {
                $msg = 'Permintaan ke layanan Gemini AI melampaui batas waktu (timeout). Silakan periksa koneksi dan coba beberapa saat lagi.';
            } elseif ($isDns) {
                $msg = 'Tidak dapat terhubung ke server Gemini AI. Silakan periksa koneksi internet atau pengaturan DNS.';
            } else {
                $msg = 'Gagal terhubung ke layanan Gemini AI karena masalah jaringan. Silakan coba kembali beberapa saat.';
            }

            error_log('[Chatbot Gemini] Koneksi cURL error: ' . $curlError);
            return [
                'success' => false,
                'message' => $msg,
                'is_config_error' => false
            ];
        }

        // 2. HTTP Error Response
        $data = json_decode($raw, true);
        $apiMessage = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
        $status = is_array($data) ? (string) ($data['error']['status'] ?? '') : '';

        // Catat log teknis secara aman (masking API key / rahasia)
        $safeLog = preg_replace('/(key=|[a-zA-Z0-9_-]{25,})/i', '***', substr($raw, 0, 500));
        error_log("[Chatbot Gemini] HTTP {$http} ({$status}): {$safeLog}");

        // HTTP 429: Bedakan Kuota Harian Habis (Free Tier) vs Rate Limit (RPM/TPM)
        if ($http === 429) {
            $isDailyQuota = (
                stripos($apiMessage, 'quota') !== false
                || stripos($apiMessage, 'free_tier_requests') !== false
                || stripos($apiMessage, 'requests per day') !== false
                || stripos($apiMessage, 'limit: 20') !== false
                || stripos($raw, 'Generate content requests per day limit exceeded') !== false
            );

            if ($isDailyQuota) {
                $retryNote = '';
                if (preg_match('/Please retry in\s+([0-9a-z\.]+)/i', $apiMessage, $m)) {
                    $retryNote = ' Silakan coba kembali dalam ' . trim($m[1]) . ' (saat periode reset kuota).';
                } else {
                    $retryNote = ' Kuota akan direset pada siklus harian berikutnya.';
                }

                return [
                    'success' => false,
                    'is_config_error' => false,
                    'is_quota_exhausted' => true,
                    'message' => 'Batas kuota harian Gemini API pada paket gratis telah habis (limit 20 request/hari tercapai).' . $retryNote
                ];
            }

            return [
                'success' => false,
                'is_config_error' => false,
                'message' => 'Layanan Gemini sedang menerima terlalu banyak permintaan dalam waktu singkat (rate limit). Mohon tunggu sejenak lalu coba kembali.'
            ];
        }

        // HTTP 400: Bad Request
        if ($http === 400) {
            return [
                'success' => false,
                'is_config_error' => false,
                'message' => 'Format permintaan ke Gemini API tidak valid (HTTP 400). Mohon sesuaikan pertanyaan Anda.'
            ];
        }

        // HTTP 401 / 403: Kredensial / Hak Akses Ditolak
        if ($http === 401 || $http === 403) {
            return [
                'success' => false,
                'is_config_error' => true,
                'message' => 'Akses ke Gemini API ditolak (HTTP ' . $http . '). API key tidak valid atau tidak memiliki izin akses model. Periksa API key pada menu Admin → Konfigurasi → Chatbot AI atau GEMINI_API_KEY di file .env.'
            ];
        }

        // HTTP 404: Model Tidak Ditemukan
        if ($http === 404) {
            return [
                'success' => false,
                'is_config_error' => true,
                'message' => "Model AI '{$this->model}' tidak ditemukan atau endpoint tidak tersedia pada layanan Gemini (HTTP 404). Periksa GEMINI_MODEL di file .env."
            ];
        }

        // HTTP 5xx: Gangguan Server AI
        if ($http >= 500 && $http < 600) {
            return [
                'success' => false,
                'is_config_error' => false,
                'message' => "Server Gemini AI sedang mengalami gangguan sementara atau kelebihan beban (HTTP {$http}). Silakan coba beberapa saat lagi."
            ];
        }

        // Error Lainnya
        return [
            'success' => false,
            'is_config_error' => false,
            'message' => 'Layanan Gemini mengembalikan status HTTP ' . $http . ($apiMessage !== '' ? ': ' . mb_substr($apiMessage, 0, 150) : '.')
        ];
    }

    /**
     * Mengirim pesan percakapan dan mengembalikan respons yang aman.
     */
    public function sendMessage(
        string $userMessage,
        array $currentUser,
        array $history = [],
        array $context = []
    ): array {
        $message = trim($userMessage);

        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Pertanyaan tidak boleh kosong.'
            ];
        }

        // -----------------------------------------------------------------------------
        // 1. Guard Identitas ("kamu siapa" & "siapa saya")
        // -----------------------------------------------------------------------------
        $identity = mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($message)),
            'UTF-8'
        );

        $identity = preg_replace(
            '/[?!.,;:]+$/u',
            '',
            $identity
        );

        // Pertanyaan identitas asisten bot
        if (preg_match(
            '/^(kamu siapa|siapa kamu|anda siapa|siapa anda|nama kamu siapa|siapa nama kamu|identitas kamu|siapakah kamu|siapakah anda)$/u',
            $identity
        )) {
            return [
                'success' => true,
                'reply'   => 'Saya adalah Asisten Virtual Presensi STMIK IKMI Cirebon. Saya membantu informasi penggunaan aplikasi presensi, jadwal, kehadiran, izin/sakit, GPS, verifikasi wajah, dan kendala teknis aplikasi.',
                'source'  => 'identity_guard'
            ];
        }

        // Pertanyaan identitas pengguna login
        $isUserIdentity = (
            preg_match('/^(saya siapa|siapa saya|aku siapa|siapa aku|nama saya siapa|siapa nama saya|nama aku siapa|siapa nama aku|siapakah saya|siapakah aku|identitas saya|profil saya|akun saya|informasi akun saya|siapa yang sedang login|siapa saya yang sedang login|kamu tahu siapa saya|apakah kamu tahu siapa saya)$/u', $identity)
            || preg_match('/\b(siapa saya|saya siapa|siapakah saya|siapa nama saya|nama saya siapa)\b/u', $identity)
        );

        if ($isUserIdentity) {
            $name = !empty($currentUser['nama_dengan_gelar'])
                ? trim((string) $currentUser['nama_dengan_gelar'])
                : (!empty($currentUser['nama_lengkap'])
                    ? trim((string) $currentUser['nama_lengkap'])
                    : trim((string) ($currentUser['username'] ?? 'Pengguna')));

            $role = strtolower((string) ($currentUser['role'] ?? 'pengguna'));

            $reply = "Anda sedang login sebagai {$name} dengan peran {$role}.";

            if ($role === 'mahasiswa' && !empty($currentUser['nim'])) {
                $reply .= ' NIM: ' . $currentUser['nim'] . '.';
            }

            if ($role === 'dosen' && !empty($currentUser['nidn'])) {
                $reply .= ' NIDN: ' . $currentUser['nidn'] . '.';
            }

            if (!empty($currentUser['prodi'])) {
                $reply .= ' Program studi: ' . $currentUser['prodi'] . '.';
            }

            return [
                'success' => true,
                'reply'   => $reply,
                'source'  => 'identity_guard'
            ];
        }

        // -----------------------------------------------------------------------------
        // 2. Guard Privasi Antar Pengguna
        // -----------------------------------------------------------------------------
        $privacy = $this->checkPrivacyGuard(
            $message,
            $currentUser
        );

        if ($privacy !== null) {
            return [
                'success' => true,
                'reply'   => $privacy,
                'source'  => 'privacy_guard'
            ];
        }

        // -----------------------------------------------------------------------------
        // 3. Query Database Deterministik (Admin/Dosen/Mahasiswa)
        // Dijalankan sebelum guard topik agar jawaban lanjutan seperti "bulan ini"
        // tetap dapat diproses dari konteks sesi.
        // -----------------------------------------------------------------------------
        $databaseResult = $this->databaseService->resolve($message, $currentUser, $context);
        if ($databaseResult !== null) {
            return $databaseResult;
        }

        // -----------------------------------------------------------------------------
        // 4. Guard Penolakan Keluar dari Topik (Out of Topic)
        // -----------------------------------------------------------------------------
        $outOfTopic = $this->checkOutOfTopic($message);

        if ($outOfTopic !== null) {
            return [
                'success' => true,
                'reply'   => $outOfTopic,
                'source'  => 'context_guard'
            ];
        }

        // -----------------------------------------------------------------------------
        // 5. Resolusi Pertanyaan Spesifik Data Aplikasi (Legacy, untuk kompatibilitas)
        // -----------------------------------------------------------------------------
        $appResult = $this->resolveApplicationQuery($message, $currentUser);
        if ($appResult !== null) {
            return $appResult;
        }

        // -----------------------------------------------------------------------------
        // 5. Verifikasi Provider & Kredensial AI yang Aktif
        // -----------------------------------------------------------------------------
        if (!$this->isProviderSupported()) {
            return [
                'success'         => false,
                'is_config_error' => true,
                'message'         => "Provider AI '{$this->provider}' tidak didukung oleh layanan chatbot ini. Gunakan provider google_gemini melalui menu Admin → Konfigurasi → Chatbot AI."
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success'         => false,
                'is_config_error' => true,
                'message'         => 'API key Gemini belum dikonfigurasi. Simpan API key melalui menu Admin → Konfigurasi → Chatbot AI atau isi GEMINI_API_KEY di file .env.'
            ];
        }

        // -----------------------------------------------------------------------------
        // 6. Siapkan Konteks Database Terarah & Payload Gemini
        // -----------------------------------------------------------------------------
        $databaseContext = $this->fetchDatabaseContext(
            $currentUser,
            $message
        );

        try {
            $queryContext = $this->databaseService->buildAiContext($currentUser, $message, $context);
            if ($queryContext !== '') {
                $databaseContext .= "\n\n=== KONTEKS QUERY DATABASE ===\n" . $queryContext;
            }
        } catch (Throwable $e) {
            error_log('[Chatbot DB context] ' . $e->getMessage());
        }

        $systemPrompt = $this->buildSystemPrompt(
            $currentUser,
            $databaseContext
        );

        $contents = [];

        foreach (array_slice($history, -6) as $item) {
            if (!isset($item['role'], $item['content'])) {
                continue;
            }

            $itemRole = in_array($item['role'], ['assistant', 'model'], true) ? 'model' : 'user';
            $content = trim(strip_tags((string) $item['content']));

            if ($content === '') {
                continue;
            }

            $contents[] = [
                'role'  => $itemRole,
                'parts' => [
                    ['text' => mb_substr($content, 0, 1500)]
                ]
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [
                ['text' => mb_substr($message, 0, 4000)]
            ]
        ];

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => 0.25,
                'maxOutputTokens' => 1200,
                'topP'            => 0.85
            ]
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Pesan tidak dapat diproses.'
            ];
        }

        $url = $this->baseUrl . '/models/' . rawurlencode($this->model) . ':generateContent';

        $maxAttempts = 2;
        $attempt = 0;
        $raw = false;
        $http = 0;
        $curlError = '';

        while ($attempt < $maxAttempts) {
            $attempt++;
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'x-goog-api-key: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $raw = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Jika sukses atau client error pasti (400, 401, 403, 404), hentikan pengulangan
            if ($raw !== false && $curlError === '' && $http >= 200 && $http < 300) {
                break;
            }

            // Jika 429 karena kuota harian habis, jangan retry sia-sia
            if ($http === 429 && stripos((string) $raw, 'quota') !== false) {
                break;
            }

            // Jika error sementara (503 / timeout / transient rate limit), tunggu jeda sebelum retry 1x
            if ($attempt < $maxAttempts && ($http === 503 || $http === 500 || $curlError !== '')) {
                usleep(1500000); // 1.5 detik
            } else {
                break;
            }
        }

        if ($raw === false || $curlError !== '' || $http < 200 || $http >= 300) {
            return $this->parseGeminiError($http, (string) $raw, (string) $curlError);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            error_log('[Chatbot Gemini] Respons bukan JSON: ' . substr((string) $raw, 0, 300));
            return [
                'success' => false,
                'message' => 'Respons Gemini tidak dapat dibaca oleh sistem.'
            ];
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $reply = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $reply .= $part['text'];
            }
        }

        $reply = trim($reply);

        if ($reply === '') {
            return [
                'success' => false,
                'message' => 'Gemini tidak menghasilkan jawaban. Silakan coba pertanyaan lain.'
            ];
        }

        return [
            'success'  => true,
            'reply'    => $reply,
            'provider' => $this->provider,
            'model'    => $this->model,
            'source'   => 'google_gemini'
        ];
    }
}
