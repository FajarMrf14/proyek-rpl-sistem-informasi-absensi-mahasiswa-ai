<?php

namespace App\Services;

use App\Config\App as AppConfig;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Lapisan query database untuk Chatbot Admin.
 *
 * Semua jawaban deterministik di kelas ini berasal dari query parameterized.
 * AI hanya dipakai untuk merangkai jawaban umum setelah konteks database
 * diberikan; angka dan status tidak boleh dikarang di luar hasil query.
 */
class ChatbotDatabaseService
{
    private const MAX_ROWS = 50;

    private DateTimeZone $timezone;
    private DateTimeImmutable $now;
    private PDO $db;

    public function __construct()
    {
        $timezoneName = (string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta');
        if ($timezoneName === '') {
            $timezoneName = 'Asia/Jakarta';
        }

        try {
            $this->timezone = new DateTimeZone($timezoneName);
        } catch (Throwable) {
            $this->timezone = new DateTimeZone('Asia/Jakarta');
        }

        $this->now = new DateTimeImmutable('now', $this->timezone);
        $this->db = Database::getConnection();
    }

    /**
     * Menyelesaikan pertanyaan data secara langsung.
     *
     * @return array|null Null berarti bukan pertanyaan data spesifik; caller
     *                    boleh melanjutkan ke AI dengan konteks database.
     */
    public function resolve(string $query, array $user, array $context = []): ?array
    {
        $q = $this->normalize($query);
        if ($q === '') {
            return null;
        }

        $intent = $this->detectIntent($q, $context, $user);
        $periodContext = $this->shouldUseContextPeriod($q, $context, $intent) ? $context : [];
        $period = $this->parsePeriod($q, $periodContext);

        // Query umum seperti "tampilkan data September 2026" tetap harus
        // dijawab dari database, bukan dilempar ke AI tanpa konteks.
        if ($intent === null && $period !== null
            && preg_match('/\b(data|tampilkan|lihat|rekap|ringkasan|statistik)\b/ui', $q)
        ) {
            $intent = 'period_summary';
        }

        if ($intent === null) {
            return null;
        }

        // Jangan pernah menjalankan query tanpa filter ketika periode yang
        // diminta tidak ditemukan; itu bisa mencampur seluruh riwayat.
        if ($period !== null && !empty($period['clarification'])) {
            return $this->result(
                (string) $period['clarification'],
                array_merge($context, ['intent' => $intent, 'period' => $period, 'last_query' => $query]),
                'database_clarification'
            );
        }

        $baseContext = array_merge($context, [
            'intent' => $intent,
            'period' => $period,
            'last_query' => $query,
        ]);
        unset($baseContext['pending_intent'], $baseContext['pending_query']);
        if ($intent !== 'student_history') {
            unset($baseContext['student_id'], $baseContext['student_nim'], $baseContext['student_name']);
        }

        if ($this->intentNeedsPeriod($intent) && $period === null) {
            return $this->clarification(
                'Periode waktunya belum jelas. Sebutkan periode yang Anda maksud, misalnya: hari ini, kemarin, minggu ini, bulan ini, bulan lalu, September 2026, semester ini, semester sebelumnya, tahun akademik tertentu, atau seluruh riwayat.',
                ['pending_intent' => $intent, 'pending_query' => $query]
            );
        }

        if ($period !== null && !empty($period['clarification'])) {
            return $this->clarification(
                (string) $period['clarification'],
                ['pending_intent' => $intent, 'pending_query' => $query, 'period' => $period]
            );
        }

        try {
            return match ($intent) {
                'late_count' => $this->lateCount($q, $user, $period, $baseContext),
                'late_list' => $this->lateList($q, $user, $period, $baseContext),
                'late_ranking' => $this->lateRanking($q, $user, $period, $baseContext),
                'absent_count' => $this->absentCount($q, $user, $period, $baseContext),
                'absent_list' => $this->absentList($q, $user, $period, $baseContext),
                'attendance_count' => $this->attendanceCount($q, $user, $period, $baseContext),
                'low_attendance' => $this->lowAttendance($q, $user, $period, $baseContext),
                'best_attendance' => $this->bestAttendance($q, $user, $period, $baseContext),
                'izin_sakit_count' => $this->izinSakitCount($q, $user, $period, $baseContext),
                'izin_sakit_list' => $this->izinSakitList($q, $user, $period, $baseContext),
                'course_absence' => $this->courseAbsence($q, $user, $period, $baseContext),
                'student_history' => $this->studentHistory($q, $user, $period, $baseContext),
                'semester_recap' => $this->semesterRecap($q, $user, $period, $baseContext),
                'compare_attendance' => $this->compareAttendance($q, $user, $period, $baseContext),
                'period_summary' => $this->periodSummary($q, $user, $period, $baseContext),
                'lecturer_by_class' => $this->lecturerByClass($q, $user, $period, $baseContext),
                'schedule' => $this->schedule($q, $user, $period, $baseContext),
                'overview' => $this->overview($q, $user, $period, $baseContext),
                'config' => $this->config($q, $user, $period, $baseContext),
                'student_list' => $this->studentList($q, $user, $period, $baseContext),
                'student_detail' => $this->studentDetail($q, $user, $period, $baseContext),
                'profile' => $this->profile($q, $user, $period, $baseContext),
                'entity_count' => $this->entityCount($q, $user, $period, $baseContext),
                'course_list' => $this->courseList($q, $user, $period, $baseContext),
                'lecturer_list' => $this->lecturerList($q, $user, $period, $baseContext),
                default => null,
            };
        } catch (Throwable $e) {
            error_log('[Chatbot DB] ' . $e->getMessage());
            return [
                'success' => true,
                'reply' => 'Data tidak dapat dibaca dari database pada permintaan ini. Saya tidak akan mengarang angka. Silakan coba lagi atau periksa koneksi database.',
                'source' => 'database_error',
                'context' => $baseContext,
            ];
        }
    }

    /**
     * Konteks database ringkas untuk fallback AI.
     */
    public function buildAiContext(array $user, string $query = '', array $context = []): string
    {
        $q = $this->normalize($query);
        $intent = $this->detectIntent($q, $context, $user);
        $period = $this->parsePeriod($q, $this->shouldUseContextPeriod($q, $context, $intent) ? $context : []);
        $lines = [];

        $lines[] = 'Waktu server: ' . $this->now->format('Y-m-d H:i:s') . ' (' . $this->timezone->getName() . ')';
        $lines[] = 'Tanggal hari ini: ' . $this->formatDate($this->now->format('Y-m-d'));

        $activeTa = $this->activeTahunAkademik();
        if ($activeTa !== null) {
            $expired = $this->isTaExpired($activeTa);
            $lines[] = 'Tahun akademik aktif di sistem: ' . $activeTa['nama_tahun']
                . ' (' . $activeTa['tanggal_mulai'] . ' s.d. ' . $activeTa['tanggal_selesai'] . ')'
                . ($expired ? ' — SUDAH BERAKHIR, jangan sebut masih berlangsung.' : ' — masih dalam rentang tanggal.');
        } else {
            $lines[] = 'Tidak ada tahun akademik aktif di sistem.';
        }

        try {
            $counts = $this->scopedOverviewCounts($user);
            $lines[] = 'Ringkasan jumlah: ' . json_encode($counts, JSON_UNESCAPED_UNICODE);

            if ($period !== null) {
                $lines[] = 'Periode yang diminta: ' . $period['label'];
                $summary = $this->attendanceSummary($user, $period, []);
                $lines[] = 'Ringkasan presensi periode: ' . json_encode($summary, JSON_UNESCAPED_UNICODE);
            } else {
                $todayPeriod = $this->periodForDay($this->now->format('Y-m-d'), 'hari ini');
                $summary = $this->attendanceSummary($user, $todayPeriod, []);
                $lines[] = 'Ringkasan presensi hari ini: ' . json_encode($summary, JSON_UNESCAPED_UNICODE);
            }

            $recent = $this->attendanceRows($user, $period, [], 20);
            $lines[] = 'Catatan presensi terbaru (maksimal 20): ' . json_encode($recent, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[Chatbot DB context] ' . $e->getMessage());
            $lines[] = 'Konteks database tambahan tidak tersedia. Jangan mengarang data.';
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // PERIODE
    // =========================================================================

    private function parsePeriod(string $q, array $context = []): ?array
    {
        $today = $this->now->format('Y-m-d');

        if (preg_match('/\b(semua|seluruh)\s+riwayat\b|\bsepanjang\s+waktu\b|\ball\s+time\b/ui', $q)) {
            return $this->periodResult('all', null, null, 'seluruh riwayat yang tersedia');
        }

        if (preg_match('/\b(semester\s+ini|semester\s+sekarang|semester\s+aktif)\b/ui', $q)) {
            $active = $this->activeTahunAkademik();
            if ($active === null) {
                return $this->periodResult('ta', null, null, 'semester ini', null, null, false, null, true, 'Tidak ada tahun akademik aktif di sistem. Sebutkan tahun akademik yang ingin dilihat.');
            }
            return $this->periodFromTa($active, 'semester ini');
        }

        if (preg_match('/\b(semester\s+lalu|semester\s+sebelumnya)\b/ui', $q)) {
            $active = $this->activeTahunAkademik();
            $prev = $this->previousTahunAkademik($active);
            if ($prev === null) {
                return $this->periodResult('ta', null, null, 'semester sebelumnya', null, null, false, null, true, 'Tahun akademik sebelumnya tidak ditemukan.');
            }
            return $this->periodFromTa($prev, 'semester sebelumnya');
        }

        if (preg_match('/\b(?:ta|tahun\s+akademik)\s*([0-9]{5})\b/ui', $q, $m)) {
            $ta = $this->findTaByCode($m[1]);
            if ($ta === null) {
                return $this->periodResult('ta', null, null, 'tahun akademik ' . $m[1], null, null, false, null, true, 'Tahun akademik dengan kode ' . $m[1] . ' tidak ditemukan.');
            }
            return $this->periodFromTa($ta, 'tahun akademik ' . $ta['nama_tahun']);
        }

        if (preg_match('/\b([0-9]{4})\s*[\/\-]\s*([0-9]{4})\s*(ganjil|genap|pendek)\b/ui', $q, $m)) {
            $name = $m[1] . '/' . $m[2] . ' ' . ucfirst(strtolower($m[3]));
            $ta = $this->findTaByName($name);
            if ($ta === null) {
                return $this->periodResult('ta', null, null, $name, null, null, false, null, true, 'Tahun akademik ' . $name . ' tidak ditemukan.');
            }
            return $this->periodFromTa($ta, 'tahun akademik ' . $ta['nama_tahun']);
        }

        if (preg_match('/\b([0-9]{4})-([0-9]{2})-([0-9]{2})\b/', $q, $m)) {
            return $this->periodForDay($m[0], 'tanggal ' . $m[0]);
        }

        if (preg_match('/\b([0-9]{1,2})[\/\-]([0-9]{1,2})[\/\-]([0-9]{4})\b/', $q, $m)) {
            $date = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            return $this->periodForDay($date, 'tanggal ' . $this->formatDate($date));
        }

        $monthNames = 'januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember';
        if (preg_match('/\b([0-9]{1,2})\s+(' . $monthNames . ')\s+([0-9]{4})\b/ui', $q, $m)) {
            $month = $this->monthNumber($m[2]);
            if ($month > 0) {
                $date = sprintf('%04d-%02d-%02d', (int) $m[3], $month, (int) $m[1]);
                if (checkdate($month, (int) $m[1], (int) $m[3])) {
                    return $this->periodForDay($date, 'tanggal ' . $this->formatDate($date));
                }
            }
        }

        $monthMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        ];
        foreach ($monthMap as $name => $month) {
            if (preg_match('/\b' . $name . '\s*(?:([0-9]{4}))?\b/ui', $q, $m)) {
                $year = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : (int) $this->now->format('Y');
                if (!isset($m[1]) || $m[1] === '') {
                    $candidate = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $this->timezone);
                    if ($candidate > $this->now->setTime(0, 0, 0)) {
                        $year--;
                    }
                }
                $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $this->timezone);
                $end = $start->modify('last day of this month');
                return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), $this->monthName($month) . ' ' . $year);
            }
        }

        if (preg_match('/\bhari\s+ini\b/ui', $q)) {
            return $this->periodForDay($today, 'hari ini');
        }

        if (preg_match('/\bkemarin\b/ui', $q)) {
            $date = $this->now->modify('-1 day')->format('Y-m-d');
            return $this->periodForDay($date, 'kemarin');
        }

        if (preg_match('/\bminggu\s+ini\b|\bpekan\s+ini\b/ui', $q)) {
            $dow = (int) $this->now->format('N');
            $start = $this->now->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
            $end = $start->modify('+6 days');
            return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), 'minggu ini');
        }

        if (preg_match('/\bminggu\s+lalu\b|\bpekan\s+lalu\b/ui', $q)) {
            $dow = (int) $this->now->format('N');
            $start = $this->now->modify('-' . ($dow - 1) . ' days')->modify('-7 days')->setTime(0, 0, 0);
            $end = $start->modify('+6 days');
            return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), 'minggu lalu');
        }

        if (preg_match('/\bbulan\s+ini\b/ui', $q)) {
            $start = $this->now->modify('first day of this month')->setTime(0, 0, 0);
            $end = $start->modify('last day of this month');
            return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), 'bulan ini');
        }

        if (preg_match('/\bbulan\s+(lalu|sebelumnya)\b/ui', $q)) {
            $start = $this->now->modify('first day of last month')->setTime(0, 0, 0);
            $end = $start->modify('last day of this month');
            return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), 'bulan lalu');
        }

        if (preg_match('/\bminggu\s+sebelumnya\b/ui', $q)) {
            $dow = (int) $this->now->format('N');
            $start = $this->now->modify('-' . ($dow - 1) . ' days')->modify('-7 days')->setTime(0, 0, 0);
            $end = $start->modify('+6 days');
            return $this->periodFromDates($start->format('Y-m-d'), $end->format('Y-m-d'), 'minggu lalu');
        }

        if (preg_match('/\bbesok\b/ui', $q)) {
            $date = $this->now->modify('+1 day')->format('Y-m-d');
            return $this->periodForDay($date, 'besok');
        }

        if (!empty($context['period']) && is_array($context['period'])) {
            $period = $context['period'];
            $period['explicit'] = false;
            return $period;
        }

        return null;
    }

    private function periodForDay(string $date, string $label): array
    {
        return $this->periodFromDates($date, $date, $label);
    }

    private function periodFromDates(string $start, string $end, string $label): array
    {
        $ta = $this->findTaForPeriod($start, $end);
        $taId = null;
        $taLabel = null;
        $taExpired = false;
        $note = null;
        $clarification = null;

        if (count($ta) === 1) {
            $taId = (int) $ta[0]['id'];
            $taLabel = (string) $ta[0]['nama_tahun'];
            $taExpired = $this->isTaExpired($ta[0]);
            if ($taExpired) {
                $note = 'Tahun akademik ' . $taLabel . ' sudah berakhir pada ' . $this->formatDate((string) $ta[0]['tanggal_selesai']) . '.';
            }
        } elseif (count($ta) === 0) {
            $note = 'Tidak ada tahun akademik yang mencakup periode ini. Data tetap dicari berdasarkan tanggal, tanpa mencampur tahun akademik lain.';
        } else {
            $names = array_map(static fn($row) => (string) $row['nama_tahun'], $ta);
            $clarification = 'Periode ' . $label . ' mencakup lebih dari satu tahun akademik (' . implode(', ', $names) . '). Pilih tahun akademik atau persempit periodenya.';
        }

        return $this->periodResult('date', $start, $end, $label, $taId, $taLabel, $taExpired, $note, true, $clarification);
    }

    private function periodFromTa(array $ta, string $label): array
    {
        $expired = $this->isTaExpired($ta);
        $note = $expired
            ? 'Tahun akademik ' . $ta['nama_tahun'] . ' sudah berakhir pada ' . $this->formatDate((string) $ta['tanggal_selesai']) . '; data berikut adalah data historis periode tersebut, bukan periode yang sedang berlangsung.'
            : null;

        return $this->periodResult(
            'ta',
            (string) $ta['tanggal_mulai'],
            (string) $ta['tanggal_selesai'],
            $label . ' (' . $ta['nama_tahun'] . ')',
            (int) $ta['id'],
            (string) $ta['nama_tahun'],
            $expired,
            $note
        );
    }

    private function periodResult(
        string $type,
        ?string $start,
        ?string $end,
        string $label,
        ?int $taId = null,
        ?string $taLabel = null,
        bool $taExpired = false,
        ?string $note = null,
        bool $explicit = true,
        ?string $clarification = null
    ): array {
        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'ta_id' => $taId,
            'ta_label' => $taLabel,
            'ta_expired' => $taExpired,
            'note' => $note,
            'explicit' => $explicit,
            'clarification' => $clarification,
        ];
    }

    private function findTaForPeriod(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tahun_akademik
             WHERE tanggal_mulai IS NOT NULL AND tanggal_selesai IS NOT NULL
               AND tanggal_mulai <= :end AND tanggal_selesai >= :start
             ORDER BY tanggal_mulai ASC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function activeTahunAkademik(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM tahun_akademik WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function previousTahunAkademik(?array $active): ?array
    {
        if ($active === null) {
            $stmt = $this->db->query("SELECT * FROM tahun_akademik ORDER BY tanggal_mulai DESC, id DESC LIMIT 1 OFFSET 1");
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM tahun_akademik
                 WHERE (tanggal_mulai < :start OR (tanggal_mulai = :start2 AND id < :id))
                 ORDER BY tanggal_mulai DESC, id DESC LIMIT 1"
            );
            $stmt->execute([
                'start' => $active['tanggal_mulai'],
                'start2' => $active['tanggal_mulai'],
                'id' => (int) $active['id'],
            ]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findTaByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tahun_akademik WHERE kode_tahun = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findTaByName(string $name): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tahun_akademik WHERE LOWER(nama_tahun) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function isTaExpired(array $ta): bool
    {
        if (empty($ta['tanggal_selesai'])) {
            return false;
        }
        return (string) $ta['tanggal_selesai'] < $this->now->format('Y-m-d');
    }

    private function shouldUseContextPeriod(string $q, array $context, ?string $intent): bool
    {
        if (empty($context['period']) || !is_array($context['period'])) {
            return false;
        }

        if (!empty($context['pending_intent'])) {
            return true;
        }

        if (preg_match('/^(kalau|bagaimana dengan|jika|dan|terus|lalu|berapa jumlahnya)\b/ui', $q)
            || preg_match('/\b(paling sering|urutkan|sortir)\b/ui', $q)
        ) {
            return true;
        }

        $contextIntent = (string) ($context['intent'] ?? '');
        return $intent !== null && $contextIntent !== '' && $intent === $contextIntent;
    }

    private function defaultRecapPeriod(): array
    {
        $active = $this->activeTahunAkademik();
        if ($active !== null) {
            return $this->periodFromTa($active, 'semester ini');
        }
        return $this->periodResult('all', null, null, 'seluruh riwayat yang tersedia');
    }

    private function intentNeedsPeriod(string $intent): bool
    {
        return in_array($intent, [
            'late_count', 'late_list', 'late_ranking', 'absent_count', 'absent_list',
            'attendance_count', 'izin_sakit_count', 'izin_sakit_list', 'course_absence',
            'compare_attendance',
        ], true);
    }

    // =========================================================================
    // INTENT & ENTITAS
    // =========================================================================

    private function detectIntent(string $q, array $context, array $user = []): ?string
    {
        $contextIntent = (string) ($context['intent'] ?? '');
        // "Siapa saja ..." dan "Siapa yang ..." adalah pertanyaan baru yang
        // harus dideteksi dari isi pertanyaannya, bukan dianggap follow-up
        // yang mewarisi intent lama (misalnya entity_count).
        $isFollowUp = preg_match('/^(kalau|bagaimana dengan|jika|dan|terus|lalu|berapa jumlahnya|siapa yang|paling sering|urutkan|sortir)\b/ui', $q) === 1;

        if (preg_match('/\bbandingkan\b|\bperbandingan\b|\bdibanding(?:kan)?\b|\bvs\b/ui', $q)) {
            return 'compare_attendance';
        }

        if (preg_match('/\b(mata\s+kuliah|matkul|kelas)\b.*\b(ketidakhadiran|alpha|alpa|paling\s+banyak|terbanyak)\b/ui', $q)) {
            return 'course_absence';
        }

        if (preg_match('/\b(terlambat|telat|late)\b/ui', $q)) {
            if (preg_match('/\b(paling\s+sering|terbanyak|paling\s+banyak|ranking|peringkat|top)\b/ui', $q)) {
                return 'late_ranking';
            }
            if (preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q)) {
                return 'late_count';
            }
            return 'late_list';
        }

        if (preg_match('/\b(tidak\s+hadir|tidak\s+masuk|alpha|alpa)\b/ui', $q)) {
            if (preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q)) {
                return 'absent_count';
            }
            return 'absent_list';
        }

        if (preg_match('/\b(kehadiran|hadir|absensi|presensi|persentase)\b.*\b(rendah|terendah|paling\s+rendah|sedikit|paling\s+sedikit|kurang|minim|di\s+bawah|belum\s+memenuhi)\b/ui', $q)
            || preg_match('/\b(di\s+bawah|kurang\s+dari)\s*[0-9]+(?:[.,][0-9]+)?\s*%/ui', $q)
        ) {
            return 'low_attendance';
        }

        if (preg_match('/\b(kehadiran|hadir|absensi|presensi)\b.*\b(terbaik|tertinggi|paling\s+tinggi|paling\s+rajin|terajin)\b/ui', $q)) {
            return 'best_attendance';
        }

        if (preg_match('/\b(izin|sakit|surat)\b/ui', $q)) {
            if (preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q)) {
                return 'izin_sakit_count';
            }
            return 'izin_sakit_list';
        }

        if (preg_match('/\b(riwayat|history)\b/ui', $q)
            && preg_match('/\b(absen|absensi|presensi|kehadiran|lokasi|gps|wajah|face)\b/ui', $q)
        ) {
            return 'student_history';
        }

        if (preg_match('/\b(rekap|ringkasan)\b/ui', $q)
            && preg_match('/\b(semester|tahun\s+akademik|ta)\b/ui', $q)
        ) {
            return 'semester_recap';
        }

        // 1. Deteksi pertanyaan jumlah (Entity Count)
        // Harus sebelum schedule agar "Berapa jumlah mata kuliah?" dan "Berapa jumlah jadwal kelas?"
        // tidak salah masuk ke jadwal kuliah.
        if (preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q)
            && preg_match('/\b(mahasiswa|dosen|admin|mata\s+kuliah|matkul|jadwal(?:\s+kelas)?|kelas|pertemuan|pengguna|user|keterlambatan|terlambat|telat|absensi)\b/ui', $q)
            && !preg_match('/\b(hadir|kehadiran|alpha|alpa|izin|sakit)\b/ui', $q)
            && !preg_match('/\b(ranking|peringkat|top|paling|sering)\b/ui', $q)
        ) {
            return 'entity_count';
        }

        // 2. Deteksi pertanyaan daftar mata kuliah ("Apa saja mata kuliah yang tersedia?")
        if (preg_match('/\b(mata\s+kuliah|matkul)\b/ui', $q)
            && preg_match('/\b(apa\s+saja|daftar|list|sebutkan|tampilkan|lihat|ada\s+apa\s+saja|tersedia)\b/ui', $q)
            && !preg_match('/\b(berapa|jumlah|total)\b/ui', $q)
        ) {
            return 'course_list';
        }

        // 3. Deteksi pertanyaan daftar dosen ("Siapa saja dosen yang terdaftar?")
        if (preg_match('/\bdosen\b/ui', $q)
            && preg_match('/\b(siapa\s+saja|daftar|list|sebutkan|tampilkan|lihat|ada\s+siapa\s+saja|terdaftar)\b/ui', $q)
            && !preg_match('/\b(berapa|jumlah|total)\b/ui', $q)
        ) {
            return 'lecturer_list';
        }

        if (preg_match('/\b(siapa|dosen)\b.*\b(mengajar|pengampu)\b/ui', $q)) {
            return 'lecturer_by_class';
        }

        if (preg_match('/\b(jadwal|kuliah|mengajar)\b/ui', $q)) {
            return 'schedule';
        }

        if (preg_match('/\b(radius|konfigurasi|pengaturan|syarat|minimal|uas|tahun\s+akademik\s+aktif|zona\s+waktu)\b/ui', $q)) {
            return 'config';
        }

        // Pertanyaan daftar/detail mahasiswa harus dibedakan dari pertanyaan
        // jumlah. Jangan biarkan konteks "entity_count" lama menimpa daftar.
        $mentionsStudents = preg_match('/\bmahasiswa\b/ui', $q) === 1;
        $asksCount = preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q) === 1;
        if ($mentionsStudents && !$asksCount) {
            $hasNim = preg_match('/\b[0-9]{7,20}\b/', $q) === 1;
            $hasStrongListCue = preg_match('/\b(siapa\s+saja|daftar|semua|nama-nama|nama\s+nama|data\s+mahasiswa)\b/ui', $q) === 1;
            $hasWeakListCue = preg_match('/\b(tampilkan|lihat|sebutkan)\b/ui', $q) === 1;
            $mentionsNim = preg_match('/\bnim\b/ui', $q) === 1;
            $hasDetailCue = preg_match('/\b(siapa\s+mahasiswa|mahasiswa\s+dengan|cari\s+mahasiswa|profil\s+mahasiswa|nim)\b/ui', $q) === 1;

            if ($hasNim) {
                return 'student_detail';
            }
            if ($hasStrongListCue) {
                return 'student_list';
            }
            if ($mentionsNim) {
                return 'student_detail';
            }
            if ($hasWeakListCue) {
                return 'student_list';
            }
            if ($hasDetailCue) {
                return 'student_detail';
            }
        }

        if (preg_match('/\b(profil|data\s+mahasiswa|data\s+dosen|identitas)\b/ui', $q)) {
            return 'profile';
        }

        if (preg_match('/\b(berapa|jumlah|total|banyak)\b.*\b(hadir|kehadiran|mahasiswa)\b/ui', $q)
            && !preg_match('/\b(mahasiswa|dosen|admin|mata\s+kuliah|matkul|jadwal|kelas|pertemuan)\b/ui', $q)
        ) {
            return 'attendance_count';
        }

        if (preg_match('/\b(berapa|jumlah|total|banyak)\b.*\b(hadir|kehadiran)\b/ui', $q)) {
            return 'attendance_count';
        }

        if (preg_match('/\b(berapa|jumlah|total)\b.*\b(mahasiswa|dosen|admin|mata\s+kuliah|matkul|jadwal|kelas|pertemuan)\b/ui', $q)) {
            return 'entity_count';
        }

        if (preg_match('/\b(ringkasan|statistik|dashboard|overview|jumlah)\b/ui', $q)) {
            return 'overview';
        }

        if ($isFollowUp && $contextIntent !== '') {
            if (preg_match('/\b(paling\s+sering|terbanyak|paling\s+banyak|ranking|peringkat|top)\b/ui', $q)) {
                return match ($contextIntent) {
                    'late_list', 'late_count', 'late_ranking' => 'late_ranking',
                    default => $contextIntent,
                };
            }
            if (preg_match('/\b(berapa|jumlah|total|banyak)\b/ui', $q)) {
                return match ($contextIntent) {
                    'late_list', 'late_ranking', 'late_count' => 'late_count',
                    'absent_list', 'absent_count' => 'absent_count',
                    'izin_sakit_list', 'izin_sakit_count' => 'izin_sakit_count',
                    default => $contextIntent,
                };
            }
            return $contextIntent;
        }

        if ($contextIntent !== ''
            && mb_strlen($q) <= 25
            && preg_match('/\b(hari ini|kemarin|minggu|bulan|semester|tahun|tanggal|[0-9]{4})\b/ui', $q)
        ) {
            return $contextIntent;
        }

        if (!empty($context['pending_intent'])) {
            return (string) $context['pending_intent'];
        }

        return null;
    }

    private function findStudent(string $q, array $user): array
    {
        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));
        $selfId = (int) ($user['mahasiswa_id'] ?? 0);
        if ($role === 'mahasiswa' && $selfId <= 0 && !empty($user['id'])) {
            $selfId = $this->mahasiswaIdForUser((int) $user['id']);
        }

        if (preg_match('/\b(saya|aku|milik\s+saya|data\s+saya|profil\s+saya)\b/ui', $q) && $role === 'mahasiswa') {
            $student = $this->studentById($selfId);
            return $student ? ['status' => 'found', 'student' => $student] : ['status' => 'none'];
        }

        $nim = null;
        if (preg_match('/\b([0-9]{7,20})\b/', $q, $m)) {
            $nim = $m[1];
        }

        $students = $this->db->query("SELECT * FROM mahasiswa ORDER BY nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);
        if ($nim !== null) {
            foreach ($students as $student) {
                if ((string) $student['nim'] === $nim) {
                    if ($role === 'mahasiswa' && (int) $student['id'] !== $selfId) {
                        return ['status' => 'forbidden'];
                    }
                    if ($role === 'dosen' && !$this->dosenCanAccessStudent((int) ($user['id'] ?? 0), (int) $student['id'])) {
                        return ['status' => 'forbidden'];
                    }
                    return ['status' => 'found', 'student' => $student];
                }
            }
            return ['status' => 'none'];
        }

        $matches = [];
        foreach ($students as $student) {
            $name = $this->normalize((string) $student['nama_lengkap']);
            $nameTokens = array_values(array_filter(explode(' ', $name), static fn($t) => mb_strlen($t) >= 3));
            $allTokens = $nameTokens !== [];
            foreach ($nameTokens as $token) {
                if (!str_contains($q, $token)) {
                    $allTokens = false;
                    break;
                }
            }
            if (str_contains($q, $name) || ($allTokens && count($nameTokens) >= 2)) {
                $matches[] = $student;
            }
        }

        if (count($matches) === 0) {
            // Dukung penyebutan satu nama depan/belakang yang unik, misalnya "Arya".
            $singleTokenMatches = [];
            foreach ($students as $student) {
                $nameTokens = array_values(array_filter(
                    explode(' ', $this->normalize((string) $student['nama_lengkap'])),
                    static fn($t) => mb_strlen($t) >= 4
                ));
                foreach ($nameTokens as $token) {
                    if (str_contains($q, $token)) {
                        $singleTokenMatches[$student['id']] = $student;
                        break;
                    }
                }
            }
            if (count($singleTokenMatches) === 1) {
                $matches = array_values($singleTokenMatches);
            }
        }

        if (count($matches) === 1) {
            $student = $matches[0];
            if ($role === 'mahasiswa' && (int) $student['id'] !== $selfId) {
                return ['status' => 'forbidden'];
            }
            if ($role === 'dosen' && !$this->dosenCanAccessStudent((int) ($user['id'] ?? 0), (int) $student['id'])) {
                return ['status' => 'forbidden'];
            }
            return ['status' => 'found', 'student' => $student];
        }

        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'options' => $matches];
        }

        return ['status' => 'none'];
    }

    private function findLecturer(string $q): array
    {
        $lecturers = $this->db->query("SELECT * FROM dosen ORDER BY nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (preg_match('/\b([0-9]{7,20})\b/', $q, $m)) {
            foreach ($lecturers as $lecturer) {
                if ((string) $lecturer['nidn'] === $m[1]) {
                    return ['status' => 'found', 'lecturer' => $lecturer];
                }
            }
        }

        $matches = [];
        foreach ($lecturers as $lecturer) {
            $name = $this->normalize((string) $lecturer['nama_lengkap']);
            $tokens = array_values(array_filter(explode(' ', $name), static fn($t) => mb_strlen($t) >= 3));
            $all = $tokens !== [];
            foreach ($tokens as $token) {
                if (!str_contains($q, $token)) {
                    $all = false;
                    break;
                }
            }
            if (str_contains($q, $name) || ($all && count($tokens) >= 2)) {
                $matches[] = $lecturer;
            }
        }

        if (count($matches) === 0) {
            $singleTokenMatches = [];
            foreach ($lecturers as $lecturer) {
                $tokens = array_values(array_filter(
                    explode(' ', $this->normalize((string) $lecturer['nama_lengkap'])),
                    static fn($t) => mb_strlen($t) >= 4
                ));
                foreach ($tokens as $token) {
                    if (str_contains($q, $token)) {
                        $singleTokenMatches[$lecturer['id']] = $lecturer;
                        break;
                    }
                }
            }
            if (count($singleTokenMatches) === 1) {
                $matches = array_values($singleTokenMatches);
            }
        }

        if (count($matches) === 1) {
            return ['status' => 'found', 'lecturer' => $matches[0]];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'options' => $matches];
        }
        return ['status' => 'none'];
    }

    private function findCourse(string $q): array
    {
        $courses = $this->db->query("SELECT * FROM mata_kuliah ORDER BY nama_mk ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (preg_match('/\b([A-Z]{2,5}-[0-9]{2,4})\b/i', $q, $m)) {
            foreach ($courses as $course) {
                if (strcasecmp((string) $course['kode_mk'], $m[1]) === 0) {
                    return ['status' => 'found', 'course' => $course];
                }
            }
        }

        $matches = [];
        foreach ($courses as $course) {
            $name = $this->normalize((string) $course['nama_mk']);
            $tokens = array_values(array_filter(explode(' ', $name), static fn($t) => mb_strlen($t) >= 4));
            $all = $tokens !== [];
            foreach ($tokens as $token) {
                if (!str_contains($q, $token)) {
                    $all = false;
                    break;
                }
            }
            if (str_contains($q, $name) || ($all && count($tokens) >= 2)) {
                $matches[] = $course;
            }
        }

        if (count($matches) === 0) {
            $singleTokenMatches = [];
            foreach ($courses as $course) {
                $tokens = array_values(array_filter(
                    explode(' ', $this->normalize((string) $course['nama_mk'])),
                    static fn($t) => mb_strlen($t) >= 5
                ));
                foreach ($tokens as $token) {
                    if (str_contains($q, $token)) {
                        $singleTokenMatches[$course['id']] = $course;
                        break;
                    }
                }
            }
            if (count($singleTokenMatches) === 1) {
                $matches = array_values($singleTokenMatches);
            }
        }

        if (count($matches) === 1) {
            return ['status' => 'found', 'course' => $matches[0]];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'options' => $matches];
        }
        return ['status' => 'none'];
    }

    private function findClass(string $q): array
    {
        $classes = $this->db->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($classes as $class) {
            $name = $this->normalize((string) $class['nama_kelas']);
            if (str_contains($q, $name)) {
                return ['status' => 'found', 'class' => $class];
            }
        }
        return ['status' => 'none'];
    }

    private function studentById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT * FROM mahasiswa WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function mahasiswaIdForUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT id FROM mahasiswa WHERE user_id = :uid LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function dosenIdForUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT id FROM dosen WHERE user_id = :uid LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function dosenCanAccessStudent(int $userId, int $studentId): bool
    {
        $dosenId = $this->dosenIdForUser($userId);
        if ($dosenId <= 0 || $studentId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM kelas_mahasiswa km
             JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
             WHERE km.mahasiswa_id = :student_id
               AND jk.dosen_id = :dosen_id
               AND km.status = 'aktif'"
        );
        $stmt->execute(['student_id' => $studentId, 'dosen_id' => $dosenId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // =========================================================================
    // QUERY PRESENSI
    // =========================================================================

    private function attendanceJoins(): string
    {
        return " FROM absensi a
            JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
            JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
            JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
            JOIN kelas k ON k.id = jk.kelas_id
            JOIN dosen d ON d.id = jk.dosen_id
            JOIN mahasiswa m ON m.id = a.mahasiswa_id
            JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id";
    }

    private function attendanceWhere(array $user, ?array $period, array $filters, array &$params): string
    {
        $where = ['1 = 1'];
        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));

        // Periode tanggal memakai filter tanggal; periode tahun akademik memakai
        // filter TA saja agar tidak mencampur data dari TA lain.
        if ($period !== null && ($period['type'] ?? '') === 'date' && !empty($period['start']) && !empty($period['end'])) {
            $where[] = 'a.waktu_absensi >= :period_start';
            $where[] = 'a.waktu_absensi < :period_end_next';
            $params['period_start'] = $period['start'] . ' 00:00:00';
            $params['period_end_next'] = (new DateTimeImmutable($period['end'], $this->timezone))->modify('+1 day')->format('Y-m-d 00:00:00');
        }

        if ($period !== null && !empty($period['ta_id'])) {
            $where[] = 'jk.tahun_akademik_id = :period_ta_id';
            $params['period_ta_id'] = (int) $period['ta_id'];
        } elseif ($period !== null && ($period['type'] ?? '') === 'ta') {
            $where[] = '1 = 0';
        }

        if (!empty($filters['status']) && is_array($filters['status'])) {
            $statusPlaceholders = [];
            foreach (array_values($filters['status']) as $i => $status) {
                $key = 'status_' . $i;
                $statusPlaceholders[] = ':' . $key;
                $params[$key] = $status;
            }
            if ($statusPlaceholders !== []) {
                $where[] = 'a.status IN (' . implode(', ', $statusPlaceholders) . ')';
            }
        }

        foreach (['student_id' => 'a.mahasiswa_id', 'course_id' => 'mk.id', 'class_id' => 'k.id', 'schedule_id' => 'jk.id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $filters[$key];
            }
        }

        if (!empty($filters['closed_only'])) {
            $where[] = "pk.status = 'ditutup'";
        }

        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            $where[] = 'jk.dosen_id = :scope_dosen_id';
            $params['scope_dosen_id'] = $dosenId;
        } elseif ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            $where[] = 'a.mahasiswa_id = :scope_mahasiswa_id';
            $params['scope_mahasiswa_id'] = $mahasiswaId;
        } elseif ($role !== 'admin') {
            $where[] = '1 = 0';
        }

        return implode(' AND ', $where);
    }

    private function attendanceRows(array $user, ?array $period, array $filters = [], int $limit = self::MAX_ROWS): array
    {
        $params = [];
        $where = $this->attendanceWhere($user, $period, $filters, $params);
        $limit = max(1, min(self::MAX_ROWS, $limit));

        $sql = "SELECT a.id, a.waktu_absensi, a.status, a.latitude, a.longitude,
                       a.jarak_meter, a.status_lokasi, a.face_verified, a.face_similarity,
                       a.foto_kegiatan, a.keterangan,
                       m.id AS mahasiswa_id, m.nim, m.nama_lengkap AS nama_mahasiswa, m.prodi,
                       pk.id AS pertemuan_id, pk.pertemuan_ke, pk.tanggal, pk.materi_pembahasan,
                       jk.id AS jadwal_id, jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan,
                       mk.kode_mk, mk.nama_mk, k.nama_kelas, d.nama_lengkap AS nama_dosen,
                       ta.id AS tahun_akademik_id, ta.nama_tahun, ta.semester"
            . $this->attendanceJoins()
            . " WHERE {$where}
                ORDER BY a.waktu_absensi DESC, a.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function attendanceSummary(array $user, ?array $period, array $filters = []): array
    {
        $params = [];
        $where = $this->attendanceWhere($user, $period, $filters, $params);

        $sql = "SELECT COUNT(*) AS total_catatan,
                       COUNT(DISTINCT a.mahasiswa_id) AS mahasiswa_unik,
                       SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
                       SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                       SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS izin,
                       SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS sakit,
                       SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS alpha,
                       MIN(a.waktu_absensi) AS pertama,
                       MAX(a.waktu_absensi) AS terakhir"
            . $this->attendanceJoins()
            . " WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function lateCount(string $q, array $user, ?array $period, array $context): array
    {
        $summary = $this->attendanceSummary($user, $period, ['status' => ['terlambat']]);
        $count = (int) ($summary['total_catatan'] ?? 0);

        if ($count === 0) {
            return $this->emptyReply('tidak ada catatan keterlambatan', $period, $context);
        }

        $reply = "Pada **{$period['label']}**, tercatat **{$count} keterlambatan** dari **" . (int) $summary['mahasiswa_unik'] . " mahasiswa**.\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function lateList(string $q, array $user, ?array $period, array $context): array
    {
        $rows = $this->attendanceRows($user, $period, ['status' => ['terlambat']], 30);
        if ($rows === []) {
            return $this->emptyReply('tidak ada catatan keterlambatan', $period, $context);
        }

        $reply = "Daftar mahasiswa terlambat pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']})\n";
            $reply .= "   - {$row['nama_mk']} ({$row['nama_kelas']}), pertemuan {$row['pertemuan_ke']}\n";
            $reply .= "   - Waktu: " . $this->formatDateTime((string) $row['waktu_absensi']) . " WIB\n";
            $reply .= "   - Dosen: {$row['nama_dosen']}\n\n";
            $no++;
        }
        if (count($rows) >= 30) {
            $reply .= "_Ditampilkan maksimal 30 catatan terbaru._\n\n";
        }
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function lateRanking(string $q, array $user, ?array $period, array $context): array
    {
        $params = [];
        $where = $this->attendanceWhere($user, $period, ['status' => ['terlambat']], $params);
        $limit = $this->extractLimit($q);

        $sql = "SELECT m.id, m.nim, m.nama_lengkap AS nama_mahasiswa,
                       COUNT(*) AS jumlah_terlambat,
                       COUNT(DISTINCT mk.id) AS jumlah_mata_kuliah,
                       MIN(a.waktu_absensi) AS pertama,
                       MAX(a.waktu_absensi) AS terakhir"
            . $this->attendanceJoins()
            . " WHERE {$where}
                GROUP BY m.id, m.nim, m.nama_lengkap
                ORDER BY jumlah_terlambat DESC, m.nama_lengkap ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->emptyReply('tidak ada catatan keterlambatan', $period, $context);
        }

        $reply = "Peringkat mahasiswa paling sering terlambat pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']}) — **{$row['jumlah_terlambat']} kali** dari {$row['jumlah_mata_kuliah']} mata kuliah\n";
            $reply .= "   - Terakhir: " . $this->formatDateTime((string) $row['terakhir']) . " WIB\n";
            $no++;
        }
        $reply .= "\n" . $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function absentCount(string $q, array $user, ?array $period, array $context): array
    {
        $summary = $this->attendanceSummary($user, $period, ['status' => ['alpha']]);
        $count = (int) ($summary['total_catatan'] ?? 0);

        if ($count === 0) {
            return $this->emptyReply('tidak ada catatan status alpha', $period, $context);
        }

        $reply = "Pada **{$period['label']}**, tercatat **{$count} status alpha** dari **" . (int) $summary['mahasiswa_unik'] . " mahasiswa**.\n\n";
        $reply .= "_Catatan: angka ini hanya menghitung status alpha yang tercatat. Mahasiswa yang belum memiliki catatan presensi tidak otomatis dianggap alpha._\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function absentList(string $q, array $user, ?array $period, array $context): array
    {
        $rows = $this->attendanceRows($user, $period, ['status' => ['alpha']], 30);
        if ($rows === []) {
            return $this->emptyReply('tidak ada catatan status alpha', $period, $context);
        }

        $reply = "Daftar status alpha pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']}) — {$row['nama_mk']} ({$row['nama_kelas']}), pertemuan {$row['pertemuan_ke']}\n";
            $no++;
        }
        $reply .= "\n_Catatan: hanya status alpha yang tercatat; mahasiswa tanpa catatan presensi tidak otomatis dianggap alpha._\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function attendanceCount(string $q, array $user, ?array $period, array $context): array
    {
        $summary = $this->attendanceSummary($user, $period, []);
        if ((int) ($summary['total_catatan'] ?? 0) === 0) {
            return $this->emptyReply('tidak ada catatan presensi', $period, $context);
        }

        $hadir = (int) ($summary['hadir'] ?? 0);
        $terlambat = (int) ($summary['terlambat'] ?? 0);
        $totalHadir = $hadir + $terlambat;

        $reply = "Rekap jumlah presensi pada **{$period['label']}**:\n\n";
        $reply .= "- Hadir: **{$hadir}**\n";
        $reply .= "- Terlambat: **{$terlambat}**\n";
        $reply .= "- Hadir + terlambat: **{$totalHadir}**\n";
        $reply .= "- Izin: **" . (int) ($summary['izin'] ?? 0) . "**\n";
        $reply .= "- Sakit: **" . (int) ($summary['sakit'] ?? 0) . "**\n";
        $reply .= "- Alpha: **" . (int) ($summary['alpha'] ?? 0) . "**\n";
        $reply .= "- Mahasiswa unik dengan catatan: **" . (int) ($summary['mahasiswa_unik'] ?? 0) . "**\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function lowAttendance(string $q, array $user, ?array $period, array $context): array
    {
        $period = $period ?? $this->defaultRecapPeriod();
        $threshold = $this->extractThreshold($q);
        $rows = $this->attendanceRecapRows($user, $period, [], 'ASC', 30);

        if ($rows === []) {
            return $this->emptyReply('tidak ada pertemuan tertutup yang bisa dihitung untuk rekap kehadiran', $period, $context);
        }

        $low = array_values(array_filter($rows, static fn($r) => (float) $r['persentase_kehadiran'] < $threshold));
        $display = $low !== [] ? $low : array_slice($rows, 0, 5);

        $reply = "Rekap kehadiran pada **{$period['label']}** dengan batas **" . number_format($threshold, 2) . "%**:\n\n";
        if ($low === []) {
            $reply .= "Tidak ditemukan mahasiswa di bawah batas tersebut. Berikut nilai terendah yang tersedia:\n\n";
        }

        $no = 1;
        foreach ($display as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']})\n";
            $reply .= "   - {$row['nama_mk']} ({$row['nama_kelas']})\n";
            $persen = (float) $row['persentase_kehadiran'];
            $reply .= "   - Kehadiran: **" . number_format($persen, 2) . "%** ";
            $reply .= "({$row['total_hadir_terlambat']} dari {$row['total_pertemuan']} pertemuan tertutup)\n";
            $reply .= "   - Rincian: hadir {$row['hadir']}, terlambat {$row['terlambat']}, izin {$row['izin']}, sakit {$row['sakit']}, alpha {$row['alpha']}\n";
            $reply .= '   - Status Syarat UAS: **' . ($persen < $threshold ? 'Belum memenuhi syarat UAS' : 'Memenuhi syarat UAS') . "**\n\n";
            $no++;
        }

        $reply .= "📌 **Dasar Perhitungan Kehadiran:**\n";
        $reply .= "Rumus: `(hadir + terlambat) / total pertemuan tertutup × 100%`.\n";
        $reply .= 'Batas minimal UAS: **' . number_format($threshold, 2) . "%**. Mahasiswa di bawah batas tersebut belum memenuhi syarat UAS.\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function bestAttendance(string $q, array $user, ?array $period, array $context): array
    {
        $period = $period ?? $this->defaultRecapPeriod();
        $rows = $this->attendanceRecapRows($user, $period, [], 'DESC', 10);
        if ($rows === []) {
            return $this->emptyReply('tidak ada pertemuan tertutup yang bisa dihitung untuk rekap kehadiran', $period, $context);
        }

        $reply = "Mahasiswa dengan kehadiran terbaik pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']}) — **" . number_format((float) $row['persentase_kehadiran'], 2) . "%**\n";
            $reply .= "   - {$row['nama_mk']} ({$row['nama_kelas']}): {$row['total_hadir_terlambat']} dari {$row['total_pertemuan']} pertemuan\n";
            $no++;
        }
        $reply .= "\n" . $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function attendanceRecapRows(array $user, ?array $period, array $filters = [], string $direction = 'ASC', int $limit = 30): array
    {
        $params = [];
        $where = ['km.status = \'aktif\'', 'pk.status = \'ditutup\''];

        if ($period !== null && ($period['type'] ?? '') === 'date' && !empty($period['start']) && !empty($period['end'])) {
            $where[] = 'pk.tanggal >= :period_start_date';
            $where[] = 'pk.tanggal <= :period_end_date';
            $params['period_start_date'] = $period['start'];
            $params['period_end_date'] = $period['end'];
        }

        if ($period !== null && !empty($period['ta_id'])) {
            $where[] = 'jk.tahun_akademik_id = :period_ta_id';
            $params['period_ta_id'] = (int) $period['ta_id'];
        } elseif ($period !== null && ($period['type'] ?? '') === 'ta') {
            $where[] = '1 = 0';
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'km.mahasiswa_id = :student_id';
            $params['student_id'] = (int) $filters['student_id'];
        }
        if (!empty($filters['course_id'])) {
            $where[] = 'mk.id = :course_id';
            $params['course_id'] = (int) $filters['course_id'];
        }
        if (!empty($filters['class_id'])) {
            $where[] = 'k.id = :class_id';
            $params['class_id'] = (int) $filters['class_id'];
        }

        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));
        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            $where[] = 'jk.dosen_id = :scope_dosen_id';
            $params['scope_dosen_id'] = $dosenId;
        } elseif ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            $where[] = 'km.mahasiswa_id = :scope_mahasiswa_id';
            $params['scope_mahasiswa_id'] = $mahasiswaId;
        } elseif ($role !== 'admin') {
            $where[] = '1 = 0';
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $limit = max(1, min(self::MAX_ROWS, $limit));

        $sql = "SELECT m.id, m.nim, m.nama_lengkap AS nama_mahasiswa,
                       jk.id AS jadwal_id, mk.kode_mk, mk.nama_mk, k.nama_kelas,
                       d.nama_lengkap AS nama_dosen,
                       COUNT(DISTINCT pk.id) AS total_pertemuan,
                       COUNT(DISTINCT CASE WHEN a.status IN ('hadir', 'terlambat') THEN pk.id END) AS total_hadir_terlambat,
                       SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
                       SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                       SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS izin,
                       SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS sakit,
                       SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS alpha,
                       ROUND(
                           COUNT(DISTINCT CASE WHEN a.status IN ('hadir', 'terlambat') THEN pk.id END)
                           / NULLIF(COUNT(DISTINCT pk.id), 0) * 100, 2
                       ) AS persentase_kehadiran
                FROM kelas_mahasiswa km
                JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                JOIN dosen d ON d.id = jk.dosen_id
                JOIN mahasiswa m ON m.id = km.mahasiswa_id
                JOIN pertemuan_kuliah pk ON pk.jadwal_id = jk.id
                LEFT JOIN absensi a ON a.pertemuan_id = pk.id AND a.mahasiswa_id = km.mahasiswa_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY m.id, m.nim, m.nama_lengkap, jk.id, mk.kode_mk, mk.nama_mk, k.nama_kelas, d.nama_lengkap
                HAVING total_pertemuan > 0
                ORDER BY persentase_kehadiran {$direction}, total_pertemuan DESC, m.nama_lengkap ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function izinSakitCount(string $q, array $user, ?array $period, array $context): array
    {
        $summary = $this->izinSakitSummary($user, $period);
        $total = (int) ($summary['total'] ?? 0);
        if ($total === 0) {
            return $this->emptyReply('tidak ada pengajuan izin atau sakit', $period, $context);
        }

        $reply = "Rekap pengajuan izin/sakit pada **{$period['label']}**:\n\n";
        $reply .= "- Izin: **" . (int) ($summary['izin'] ?? 0) . "**\n";
        $reply .= "- Sakit: **" . (int) ($summary['sakit'] ?? 0) . "**\n";
        $reply .= "- Disetujui: **" . (int) ($summary['disetujui'] ?? 0) . "**\n";
        $reply .= "- Pending: **" . (int) ($summary['pending'] ?? 0) . "**\n";
        $reply .= "- Ditolak: **" . (int) ($summary['ditolak'] ?? 0) . "**\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function izinSakitList(string $q, array $user, ?array $period, array $context): array
    {
        $rows = $this->izinSakitRows($user, $period, 30);
        if ($rows === []) {
            return $this->emptyReply('tidak ada pengajuan izin atau sakit', $period, $context);
        }

        $reply = "Daftar pengajuan izin/sakit pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']}) — **{$row['tipe']}**\n";
            $reply .= "   - {$row['nama_mk']} ({$row['nama_kelas']})\n";
            $reply .= "   - Tanggal: " . $this->formatDate((string) $row['tanggal_mulai']) . " s.d. " . $this->formatDate((string) $row['tanggal_selesai']) . "\n";
            $reply .= "   - Status: {$row['status_persetujuan']}\n";
            $reply .= "   - Alasan: " . mb_substr((string) $row['alasan'], 0, 160) . "\n\n";
            $no++;
        }
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function izinSakitSummary(array $user, ?array $period): array
    {
        $params = [];
        $where = $this->izinWhere($user, $period, $params);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN iz.tipe = 'izin' THEN 1 ELSE 0 END) AS izin,
                       SUM(CASE WHEN iz.tipe = 'sakit' THEN 1 ELSE 0 END) AS sakit,
                       SUM(CASE WHEN iz.status_persetujuan = 'disetujui' THEN 1 ELSE 0 END) AS disetujui,
                       SUM(CASE WHEN iz.status_persetujuan = 'pending' THEN 1 ELSE 0 END) AS pending,
                       SUM(CASE WHEN iz.status_persetujuan = 'ditolak' THEN 1 ELSE 0 END) AS ditolak
                FROM izin_sakit iz
                JOIN mahasiswa m ON m.id = iz.mahasiswa_id
                JOIN jadwal_kuliah jk ON jk.id = iz.jadwal_id
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function izinSakitRows(array $user, ?array $period, int $limit = 30): array
    {
        $params = [];
        $where = $this->izinWhere($user, $period, $params);
        $limit = max(1, min(self::MAX_ROWS, $limit));
        $sql = "SELECT iz.id, iz.tipe, iz.tanggal_mulai, iz.tanggal_selesai, iz.alasan,
                       iz.status_persetujuan, iz.bukti_surat,
                       m.nim, m.nama_lengkap AS nama_mahasiswa,
                       mk.nama_mk, k.nama_kelas
                FROM izin_sakit iz
                JOIN mahasiswa m ON m.id = iz.mahasiswa_id
                JOIN jadwal_kuliah jk ON jk.id = iz.jadwal_id
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                WHERE {$where}
                ORDER BY iz.tanggal_mulai DESC, iz.id DESC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function izinWhere(array $user, ?array $period, array &$params): string
    {
        $where = ['1 = 1'];
        if ($period !== null && ($period['type'] ?? '') === 'date' && !empty($period['start']) && !empty($period['end'])) {
            $where[] = 'iz.tanggal_mulai <= :izin_end';
            $where[] = 'iz.tanggal_selesai >= :izin_start';
            $params['izin_start'] = $period['start'];
            $params['izin_end'] = $period['end'];
        }
        if ($period !== null && !empty($period['ta_id'])) {
            $where[] = 'jk.tahun_akademik_id = :izin_ta_id';
            $params['izin_ta_id'] = (int) $period['ta_id'];
        } elseif ($period !== null && ($period['type'] ?? '') === 'ta') {
            $where[] = '1 = 0';
        }

        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));
        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            $where[] = 'jk.dosen_id = :izin_scope_dosen';
            $params['izin_scope_dosen'] = $dosenId;
        } elseif ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            $where[] = 'iz.mahasiswa_id = :izin_scope_mhs';
            $params['izin_scope_mhs'] = $mahasiswaId;
        } elseif ($role !== 'admin') {
            $where[] = '1 = 0';
        }

        return implode(' AND ', $where);
    }

    private function courseAbsence(string $q, array $user, ?array $period, array $context): array
    {
        $params = [];
        $where = $this->attendanceWhere($user, $period, ['status' => ['alpha']], $params);
        $limit = $this->extractLimit($q);
        $sql = "SELECT mk.id, mk.kode_mk, mk.nama_mk, k.nama_kelas,
                       COUNT(*) AS total_alpha,
                       COUNT(DISTINCT a.mahasiswa_id) AS jumlah_mahasiswa
                FROM absensi a
                JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
                JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                WHERE {$where}
                GROUP BY mk.id, mk.kode_mk, mk.nama_mk, k.nama_kelas
                ORDER BY total_alpha DESC, mk.nama_mk ASC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->emptyReply('tidak ada catatan alpha per mata kuliah', $period, $context);
        }

        $reply = "Mata kuliah dengan ketidakhadiran (status alpha) terbanyak pada **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mk']}** ({$row['kode_mk']} - {$row['nama_kelas']}) — **{$row['total_alpha']} alpha** dari {$row['jumlah_mahasiswa']} mahasiswa\n";
            $no++;
        }
        $reply .= "\n" . $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function studentHistory(string $q, array $user, ?array $period, array $context): array
    {
        $studentResult = $this->findStudent($q, $user);

        // Pertanyaan lanjutan seperti "kalau bulan ini?" memakai mahasiswa
        // yang sama dari konteks sesi, tanpa menebak nama baru.
        if (($studentResult['status'] ?? '') === 'none'
            && !empty($context['student_id'])
            && preg_match('/^(kalau|bagaimana dengan|jika|dan|terus|lalu)\b|\b(bulan|minggu|semester|tahun|hari|kemarin|tadi|tersebut|dia|nya)\b/ui', $q)
        ) {
            $contextStudent = $this->studentById((int) $context['student_id']);
            if ($contextStudent !== null) {
                $studentResult = ['status' => 'found', 'student' => $contextStudent];
            }
        }

        if (($studentResult['status'] ?? '') === 'forbidden') {
            return $this->result('Anda tidak memiliki hak akses untuk melihat data mahasiswa tersebut.', $context, 'privacy_guard');
        }
        if (($studentResult['status'] ?? '') === 'ambiguous') {
            return $this->studentClarification($studentResult['options'] ?? [], $context);
        }
        if (($studentResult['status'] ?? '') !== 'found') {
            return $this->result('Saya belum dapat mengidentifikasi mahasiswa yang dimaksud. Sebutkan nama lengkap atau NIM agar saya tidak salah mengambil data.', $context, 'database_clarification');
        }

        $student = $studentResult['student'];
        $context['student_id'] = (int) $student['id'];
        $context['student_nim'] = (string) $student['nim'];
        $context['student_name'] = (string) $student['nama_lengkap'];
        $period = $period ?? $this->periodResult('all', null, null, 'seluruh riwayat yang tersedia');
        $rows = $this->attendanceRows($user, $period, ['student_id' => (int) $student['id']], 40);
        $summary = $this->attendanceSummary($user, $period, ['student_id' => (int) $student['id']]);

        if ($rows === []) {
            return $this->emptyReply('tidak ada riwayat presensi untuk ' . $student['nama_lengkap'], $period, $context);
        }

        $reply = "Riwayat presensi **{$student['nama_lengkap']}** (NIM: {$student['nim']}) pada **{$period['label']}**:\n\n";
        $reply .= "Ringkasan: hadir " . (int) ($summary['hadir'] ?? 0)
            . ", terlambat " . (int) ($summary['terlambat'] ?? 0)
            . ", izin " . (int) ($summary['izin'] ?? 0)
            . ", sakit " . (int) ($summary['sakit'] ?? 0)
            . ", alpha " . (int) ($summary['alpha'] ?? 0) . ".\n\n";

        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. " . $this->formatDateTime((string) $row['waktu_absensi']) . " WIB — **{$row['status']}**\n";
            $reply .= "   - {$row['nama_mk']} ({$row['nama_kelas']}), pertemuan {$row['pertemuan_ke']}\n";
            if ($row['jarak_meter'] !== null) {
                $reply .= "   - Lokasi: {$row['jarak_meter']} m, status {$row['status_lokasi']}, koordinat {$row['latitude']}, {$row['longitude']}\n";
            }
            $reply .= "   - Wajah: " . ((int) $row['face_verified'] === 1 ? 'terverifikasi' : 'belum/tidak terverifikasi');
            if ($row['face_similarity'] !== null) {
                $reply .= " (similarity {$row['face_similarity']})";
            }
            $reply .= "\n";
            if (!empty($row['foto_kegiatan'])) {
                $reply .= "   - Foto bukti: {$row['foto_kegiatan']}\n";
            }
            $reply .= "\n";
            $no++;
        }
        if (count($rows) >= 40) {
            $reply .= "_Ditampilkan maksimal 40 catatan terbaru._\n\n";
        }
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function semesterRecap(string $q, array $user, ?array $period, array $context): array
    {
        $period = $period ?? $this->defaultRecapPeriod();
        $rows = $this->attendanceRecapRows($user, $period, [], 'ASC', 40);
        if ($rows === []) {
            return $this->emptyReply('tidak ada pertemuan tertutup untuk rekap semester', $period, $context);
        }

        $reply = "Rekap kehadiran **{$period['label']}**:\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mahasiswa']}** (NIM: {$row['nim']}) — {$row['nama_mk']} ({$row['nama_kelas']}): **" . number_format((float) $row['persentase_kehadiran'], 2) . "%**\n";
            $reply .= "   - Hadir {$row['hadir']}, terlambat {$row['terlambat']}, izin {$row['izin']}, sakit {$row['sakit']}, alpha {$row['alpha']} dari {$row['total_pertemuan']} pertemuan tertutup\n";
            $no++;
        }
        $reply .= "\n" . $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function compareAttendance(string $q, array $user, ?array $period, array $context): array
    {
        $periods = $this->parseComparisonPeriods($q, $context);
        if ($periods === null) {
            return $this->clarification(
                'Sebutkan dua periode yang ingin dibandingkan, misalnya: "bandingkan kehadiran bulan ini dengan bulan lalu" atau "bandingkan September 2025 dengan Oktober 2025".',
                ['pending_intent' => 'compare_attendance', 'pending_query' => $q]
            );
        }

        [$periodA, $periodB] = $periods;
        $summaryA = $this->attendanceSummary($user, $periodA, []);
        $summaryB = $this->attendanceSummary($user, $periodB, []);

        $reply = "Perbandingan presensi:\n\n";
        $reply .= "**{$periodA['label']}**\n";
        $reply .= "- Total catatan: " . (int) ($summaryA['total_catatan'] ?? 0) . "\n";
        $reply .= "- Hadir: " . (int) ($summaryA['hadir'] ?? 0) . ", terlambat: " . (int) ($summaryA['terlambat'] ?? 0) . ", izin: " . (int) ($summaryA['izin'] ?? 0) . ", sakit: " . (int) ($summaryA['sakit'] ?? 0) . ", alpha: " . (int) ($summaryA['alpha'] ?? 0) . "\n\n";
        $reply .= "**{$periodB['label']}**\n";
        $reply .= "- Total catatan: " . (int) ($summaryB['total_catatan'] ?? 0) . "\n";
        $reply .= "- Hadir: " . (int) ($summaryB['hadir'] ?? 0) . ", terlambat: " . (int) ($summaryB['terlambat'] ?? 0) . ", izin: " . (int) ($summaryB['izin'] ?? 0) . ", sakit: " . (int) ($summaryB['sakit'] ?? 0) . ", alpha: " . (int) ($summaryB['alpha'] ?? 0) . "\n\n";

        $hadirA = (int) ($summaryA['hadir'] ?? 0) + (int) ($summaryA['terlambat'] ?? 0);
        $hadirB = (int) ($summaryB['hadir'] ?? 0) + (int) ($summaryB['terlambat'] ?? 0);
        $diff = $hadirB - $hadirA;
        $reply .= "Selisih kehadiran (hadir + terlambat): **" . ($diff >= 0 ? '+' : '') . $diff . "** catatan dari periode pertama ke periode kedua.\n\n";
        $reply .= $this->periodNote($periodA) . "\n" . $this->periodNote($periodB);
        return $this->result($reply, $context);
    }

    private function parseComparisonPeriods(string $q, array $context): ?array
    {
        $parts = preg_split('/\b(?:dengan|vs\.?|dibanding(?:kan)?|dan)\b/ui', $q);
        if (!is_array($parts) || count($parts) < 2) {
            return null;
        }

        $periodA = $this->parsePeriod(trim($parts[0]), []);
        $periodB = $this->parsePeriod(trim($parts[1]), []);
        if ($periodA === null || $periodB === null || !empty($periodA['clarification']) || !empty($periodB['clarification'])) {
            return null;
        }

        return [$periodA, $periodB];
    }

    private function periodSummary(string $q, array $user, ?array $period, array $context): array
    {
        $summary = $this->attendanceSummary($user, $period, []);
        $izin = $this->izinSakitSummary($user, $period);
        $total = (int) ($summary['total_catatan'] ?? 0);

        $reply = "Data presensi pada **{$period['label']}**:\n\n";
        if ($total === 0) {
            $reply .= "Tidak ditemukan catatan presensi pada periode tersebut berdasarkan query database.\n\n";
        } else {
            $reply .= "- Total catatan: **{$total}**\n";
            $reply .= "- Mahasiswa unik: **" . (int) ($summary['mahasiswa_unik'] ?? 0) . "**\n";
            $reply .= "- Hadir: **" . (int) ($summary['hadir'] ?? 0) . "**\n";
            $reply .= "- Terlambat: **" . (int) ($summary['terlambat'] ?? 0) . "**\n";
            $reply .= "- Izin: **" . (int) ($summary['izin'] ?? 0) . "**\n";
            $reply .= "- Sakit: **" . (int) ($summary['sakit'] ?? 0) . "**\n";
            $reply .= "- Alpha: **" . (int) ($summary['alpha'] ?? 0) . "**\n\n";
        }

        $reply .= "Pengajuan izin/sakit: total **" . (int) ($izin['total'] ?? 0)
            . "** (izin " . (int) ($izin['izin'] ?? 0)
            . ", sakit " . (int) ($izin['sakit'] ?? 0)
            . ", pending " . (int) ($izin['pending'] ?? 0) . ").\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function lecturerByClass(string $q, array $user, ?array $period, array $context): array
    {
        $classResult = $this->findClass($q);
        $courseResult = $this->findCourse($q);

        if (($classResult['status'] ?? '') !== 'found' && ($courseResult['status'] ?? '') !== 'found') {
            return $this->clarification(
                'Sebutkan nama kelas (misalnya TI-2023-A) atau nama/kode mata kuliah agar saya dapat mengambil data dosen pengampu dari database.',
                ['pending_intent' => 'lecturer_by_class', 'pending_query' => $q]
            );
        }

        $params = [];
        $where = ['jk.is_active = 1'];
        $scopeLabel = [];

        if (($classResult['status'] ?? '') === 'found') {
            $where[] = 'jk.kelas_id = :lecturer_class_id';
            $params['lecturer_class_id'] = (int) $classResult['class']['id'];
            $scopeLabel[] = 'kelas ' . $classResult['class']['nama_kelas'];
        }
        if (($courseResult['status'] ?? '') === 'found') {
            $where[] = 'jk.mata_kuliah_id = :lecturer_course_id';
            $params['lecturer_course_id'] = (int) $courseResult['course']['id'];
            $scopeLabel[] = 'mata kuliah ' . $courseResult['course']['nama_mk'];
        }

        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));
        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            $where[] = 'jk.dosen_id = :lecturer_scope_dosen';
            $params['lecturer_scope_dosen'] = $dosenId;
        } elseif ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            $where[] = "jk.id IN (SELECT jadwal_id FROM kelas_mahasiswa WHERE mahasiswa_id = :lecturer_scope_mhs AND status = 'aktif')";
            $params['lecturer_scope_mhs'] = $mahasiswaId;
        } elseif ($role !== 'admin') {
            $where[] = '1 = 0';
        }

        $sql = "SELECT DISTINCT d.id, d.nidn, d.nama_lengkap, d.gelar_depan, d.gelar_belakang,
                       mk.nama_mk, k.nama_kelas, ta.nama_tahun
                FROM jadwal_kuliah jk
                JOIN dosen d ON d.id = jk.dosen_id
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.nama_lengkap ASC, mk.nama_mk ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->emptyReply('dosen pengampu yang cocok', $period, $context);
        }

        $reply = 'Dosen pengampu untuk ' . implode(' dan ', $scopeLabel) . " berdasarkan database:\n\n";
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['id'] . '|' . $row['nama_lengkap'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'nama' => trim(($row['gelar_depan'] ? $row['gelar_depan'] . ' ' : '') . $row['nama_lengkap'] . ($row['gelar_belakang'] ? ', ' . $row['gelar_belakang'] : '')),
                    'nidn' => $row['nidn'],
                    'courses' => [],
                ];
            }
            $grouped[$key]['courses'][] = $row['nama_mk'] . ' (' . $row['nama_kelas'] . ')';
        }

        $no = 1;
        foreach ($grouped as $item) {
            $reply .= "{$no}. **{$item['nama']}** (NIDN: {$item['nidn']})\n";
            $reply .= '   - Mengajar: ' . implode('; ', array_values(array_unique($item['courses']))) . "\n";
            $no++;
        }

        return $this->result($reply, $context);
    }

    private function schedule(string $q, array $user, ?array $period, array $context): array
    {
        $params = [];
        $where = ['jk.is_active = 1'];

        if ($period !== null && !empty($period['ta_id'])) {
            $where[] = 'jk.tahun_akademik_id = :schedule_ta_id';
            $params['schedule_ta_id'] = (int) $period['ta_id'];
        } elseif ($period !== null && ($period['type'] ?? '') === 'ta') {
            $where[] = '1 = 0';
        }

        if (preg_match('/\bhari\s+ini\b/ui', $q)) {
            $where[] = 'jk.hari = :schedule_day';
            $params['schedule_day'] = $this->dayName($this->now->format('N'));
        } elseif (preg_match('/\bbesok\b/ui', $q)) {
            $where[] = 'jk.hari = :schedule_day';
            $params['schedule_day'] = $this->dayName($this->now->modify('+1 day')->format('N'));
        }

        $classResult = $this->findClass($q);
        if (($classResult['status'] ?? '') === 'found') {
            $where[] = 'jk.kelas_id = :schedule_class_id';
            $params['schedule_class_id'] = (int) $classResult['class']['id'];
        }

        $courseResult = $this->findCourse($q);
        if (($courseResult['status'] ?? '') === 'found') {
            $where[] = 'jk.mata_kuliah_id = :schedule_course_id';
            $params['schedule_course_id'] = (int) $courseResult['course']['id'];
        }

        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));
        if ($role === 'admin') {
            $lecturerResult = $this->findLecturer($q);
            if (($lecturerResult['status'] ?? '') === 'found') {
                $where[] = 'jk.dosen_id = :schedule_lecturer_id';
                $params['schedule_lecturer_id'] = (int) $lecturerResult['lecturer']['id'];
            }
        }
        $join = '';
        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            $where[] = 'jk.dosen_id = :schedule_dosen_id';
            $params['schedule_dosen_id'] = $dosenId;
        } elseif ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            $join = ' JOIN kelas_mahasiswa km ON km.jadwal_id = jk.id AND km.mahasiswa_id = :schedule_mhs_id AND km.status = \'aktif\'';
            $params['schedule_mhs_id'] = $mahasiswaId;
        } elseif ($role !== 'admin') {
            $where[] = '1 = 0';
        }

        $sql = "SELECT jk.id, jk.hari, jk.jam_mulai, jk.jam_selesai, jk.ruangan,
                       mk.kode_mk, mk.nama_mk, k.nama_kelas, d.nama_lengkap AS nama_dosen,
                       ta.nama_tahun
                FROM jadwal_kuliah jk
                JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                JOIN kelas k ON k.id = jk.kelas_id
                JOIN dosen d ON d.id = jk.dosen_id
                JOIN tahun_akademik ta ON ta.id = jk.tahun_akademik_id
                {$join}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY jk.hari, jk.jam_mulai
                LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->emptyReply('tidak ada jadwal yang cocok', $period, $context);
        }

        $reply = "Jadwal kuliah" . ($period !== null ? " pada **{$period['label']}**" : '') . ":\n\n";
        $no = 1;
        foreach ($rows as $row) {
            $reply .= "{$no}. **{$row['nama_mk']}** ({$row['kode_mk']}) — {$row['nama_kelas']}\n";
            $reply .= "   - {$row['hari']}, " . substr((string) $row['jam_mulai'], 0, 5) . "–" . substr((string) $row['jam_selesai'], 0, 5) . " WIB, {$row['ruangan']}\n";
            $reply .= "   - Dosen: {$row['nama_dosen']}\n";
            $reply .= "   - Tahun akademik: {$row['nama_tahun']}\n\n";
            $no++;
        }
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function overview(string $q, array $user, ?array $period, array $context): array
    {
        $counts = $this->scopedOverviewCounts($user);
        $reply = "Ringkasan data aplikasi:\n\n";
        $reply .= "- Mahasiswa: **{$counts['mahasiswa']}**\n";
        $reply .= "- Dosen: **{$counts['dosen']}**\n";
        $reply .= "- Admin: **{$counts['admin']}**\n";
        $reply .= "- Mata kuliah: **{$counts['mata_kuliah']}**\n";
        $reply .= "- Kelas: **{$counts['kelas']}**\n";
        $reply .= "- Jadwal kuliah: **{$counts['jadwal']}**\n";
        $reply .= "- Pertemuan: **{$counts['pertemuan']}**\n";
        $reply .= "- Catatan absensi: **{$counts['absensi']}**\n";
        $reply .= "- Pengajuan izin/sakit: **{$counts['izin_sakit']}**\n\n";
        $active = $this->activeTahunAkademik();
        if ($active !== null) {
            $reply .= "Tahun akademik aktif di sistem: **{$active['nama_tahun']}** ({$active['tanggal_mulai']} s.d. {$active['tanggal_selesai']}).\n";
            if ($this->isTaExpired($active)) {
                $reply .= "⚠️ Periode tersebut sudah berakhir; data di dalamnya adalah data historis, bukan periode yang sedang berlangsung.\n";
            }
        } else {
            $reply .= "Tidak ada tahun akademik aktif di sistem.\n";
        }
        return $this->result($reply, $context);
    }

    private function config(string $q, array $user, ?array $period, array $context): array
    {
        $minAttendance = (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
        $campus = \App\Helpers\GeoHelper::getCampusConfig();
        $active = $this->activeTahunAkademik();

        $reply = "Konfigurasi akademik dan geofence yang aktif:\n\n";
        $reply .= "- Kampus: **{$campus['name']}**\n";
        $reply .= "- Koordinat pusat: {$campus['latitude']}, {$campus['longitude']}\n";
        $reply .= "- Radius geofence: **{$campus['radius_meters']} meter**\n";
        $reply .= "- Batas minimal kehadiran UAS: **" . number_format($minAttendance, 2) . "%**\n";
        $reply .= "- Zona waktu: **" . $this->timezone->getName() . "**\n";
        if ($active !== null) {
            $reply .= "- Tahun akademik aktif: **{$active['nama_tahun']}** ({$active['tanggal_mulai']} s.d. {$active['tanggal_selesai']})\n";
            if ($this->isTaExpired($active)) {
                $reply .= "⚠️ Tahun akademik tersebut sudah berakhir pada " . $this->formatDate((string) $active['tanggal_selesai']) . ". Jangan menganggapnya sebagai periode yang sedang berlangsung.\n";
            }
        } else {
            $reply .= "- Tahun akademik aktif: tidak ada\n";
        }

        $source = preg_match('/\b(uas|syarat|minimal|persentase)\b/ui', $q)
            ? 'app_knowledge'
            : 'app_database';
        return $this->result($reply, $context, $source);
    }

    private function studentList(string $q, array $user, ?array $period, array $context): array
    {
        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));

        if ($role === 'mahasiswa') {
            if (preg_match('/\b(saya|aku|milik\s+saya|data\s+saya|profil\s+saya)\b/ui', $q)) {
                return $this->studentDetail($q, $user, $period, $context);
            }
            return $this->result(
                'Anda hanya dapat melihat data mahasiswa Anda sendiri. Daftar seluruh mahasiswa tidak dapat ditampilkan untuk akun mahasiswa.',
                $context,
                'privacy_guard'
            );
        }

        $studentResult = $this->findStudent($q, $user);
        $studentStatus = $studentResult['status'] ?? '';
        if ($studentStatus === 'forbidden') {
            return $this->result('Data mahasiswa tersebut tidak dapat ditampilkan untuk akun Anda.', $context, 'privacy_guard');
        }
        if ($studentStatus === 'ambiguous') {
            return $this->studentClarification($studentResult['options'] ?? [], $context);
        }

        $pluralList = preg_match('/\b(daftar|siapa\s+saja|semua|nama-nama|nama\s+nama)\b/ui', $q) === 1;
        if ($studentStatus === 'found' && !$pluralList) {
            return $this->studentDetail($q, $user, $period, $context);
        }

        $where = [];
        $params = [];
        $labels = [];

        if ($studentStatus === 'found') {
            $where[] = 'm.id = :student_id';
            $params['student_id'] = (int) $studentResult['student']['id'];
            $labels[] = $studentResult['student']['nama_lengkap'] . ' (' . $studentResult['student']['nim'] . ')';
        } elseif (preg_match('/\b([0-9]{7,20})\b/', $q, $nimMatch)) {
            return $this->result(
                "Tidak ditemukan mahasiswa dengan NIM **{$nimMatch[1]}** di database.",
                $context
            );
        }

        $status = $this->studentStatusFilter($q);
        if ($status !== null) {
            $where[] = 'm.status_akademik = :status';
            $params['status'] = $status;
            $labels[] = 'status ' . $status;
        }

        $classResult = $this->findClass($q);
        if (($classResult['status'] ?? '') === 'found') {
            $where[] = 'k.id = :class_id';
            $params['class_id'] = (int) $classResult['class']['id'];
            $labels[] = 'kelas ' . $classResult['class']['nama_kelas'];
        }

        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            if ($dosenId <= 0) {
                return $this->result('Data kelas untuk akun dosen ini tidak ditemukan.', $context);
            }
            $where[] = 'jk.dosen_id = :dosen_id';
            $params['dosen_id'] = $dosenId;
        }

        $sql = "SELECT m.id, m.nim, m.nama_lengkap, m.prodi, m.angkatan, m.status_akademik,
                       GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas
                FROM mahasiswa m
                LEFT JOIN kelas_mahasiswa km ON km.mahasiswa_id = m.id AND km.status = 'aktif'
                LEFT JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                LEFT JOIN kelas k ON k.id = jk.kelas_id";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY m.id, m.nim, m.nama_lengkap, m.prodi, m.angkatan, m.status_akademik
                  ORDER BY m.nama_lengkap ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            $filterText = $labels !== [] ? ' dengan ' . implode(', ', $labels) : '';
            return $this->result(
                "Tidak ditemukan mahasiswa{$filterText} di database. Saya tidak akan mengarang nama atau NIM.",
                $context
            );
        }

        $showAll = preg_match('/\b(detail|lengkap|semua\s+data|data\s+lengkap)\b/ui', $q) === 1;
        $showProdi = $showAll || preg_match('/\b(prodi(?:nya)?|program\s+studi(?:nya)?)\b/ui', $q) === 1;
        $showAngkatan = $showAll || preg_match('/\bangkatan(?:nya)?\b/ui', $q) === 1;
        $showClass = $showAll || preg_match('/\b(kelas(?:nya)?|rombel)\b/ui', $q) === 1;
        $showStatus = $showAll || $status !== null || preg_match('/\bstatus(?:nya)?\b/ui', $q) === 1;

        $filterText = $labels !== [] ? ' (' . implode(', ', $labels) . ')' : '';
        $reply = 'Daftar mahasiswa' . $filterText . ' — total **' . count($rows) . "**:\n\n";
        foreach ($rows as $index => $row) {
            $reply .= ($index + 1) . '. **' . $row['nama_lengkap'] . '** (NIM: ' . $row['nim'] . ")\n";
            if ($showProdi) {
                $reply .= '   - Program studi: ' . $row['prodi'] . "\n";
            }
            if ($showAngkatan) {
                $reply .= '   - Angkatan: ' . $row['angkatan'] . "\n";
            }
            if ($showClass) {
                $classes = $row['kelas'] !== null && $row['kelas'] !== '' ? $row['kelas'] : 'Belum terdaftar';
                $reply .= '   - Kelas: ' . $classes . "\n";
            }
            if ($showStatus) {
                $reply .= '   - Status akademik: ' . $row['status_akademik'] . "\n";
            }
            $reply .= "\n";
        }

        return $this->result($reply, $context);
    }

    private function studentDetail(string $q, array $user, ?array $period, array $context): array
    {
        $studentResult = $this->findStudent($q, $user);
        $status = $studentResult['status'] ?? '';

        if ($status === 'forbidden') {
            return $this->result('Data mahasiswa tersebut tidak dapat ditampilkan untuk akun Anda.', $context, 'privacy_guard');
        }
        if ($status === 'ambiguous') {
            return $this->studentClarification(
                $studentResult['options'] ?? [],
                array_merge($context, ['pending_intent' => 'student_detail'])
            );
        }
        if ($status !== 'found') {
            return $this->clarification(
                'Sebutkan nama lengkap atau NIM mahasiswa yang ingin dilihat. Saya hanya akan menampilkan data yang benar-benar ada di database.',
                array_merge($context, ['pending_intent' => 'student_detail'])
            );
        }

        $student = $studentResult['student'];
        $classes = $this->studentClassNames((int) $student['id'], $user);

        $reply = "Data mahasiswa:\n\n";
        $reply .= '- Nama: **' . $student['nama_lengkap'] . "**\n";
        $reply .= '- NIM: ' . $student['nim'] . "\n";
        $reply .= '- Program studi: ' . $student['prodi'] . "\n";
        $reply .= '- Angkatan: ' . $student['angkatan'] . "\n";
        $reply .= '- Status akademik: ' . $student['status_akademik'] . "\n";
        $reply .= '- Kelas: ' . ($classes !== '' ? $classes : 'Belum terdaftar') . "\n";

        return $this->result($reply, array_merge($context, [
            'student_id' => (int) $student['id'],
            'student_nim' => (string) $student['nim'],
            'student_name' => (string) $student['nama_lengkap'],
        ]));
    }

    private function studentClassNames(int $studentId, array $user): string
    {
        $sql = "SELECT GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ')
                FROM kelas_mahasiswa km
                JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                JOIN kelas k ON k.id = jk.kelas_id
                WHERE km.mahasiswa_id = :student_id AND km.status = 'aktif'";
        $params = ['student_id' => $studentId];

        if (strtolower((string) ($user['role'] ?? '')) === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            if ($dosenId <= 0) {
                return '';
            }
            $sql .= ' AND jk.dosen_id = :dosen_id';
            $params['dosen_id'] = $dosenId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function studentStatusFilter(string $q): ?string
    {
        if (preg_match('/\b(non[-\s]?aktif|tidak\s+aktif)\b/ui', $q)) {
            return 'non-aktif';
        }
        foreach (['aktif', 'cuti', 'lulus'] as $status) {
            if (preg_match('/\b' . $status . '\b/ui', $q)) {
                return $status;
            }
        }
        return null;
    }

    private function profile(string $q, array $user, ?array $period, array $context): array
    {
        $studentResult = $this->findStudent($q, $user);
        if (($studentResult['status'] ?? '') === 'found') {
            $s = $studentResult['student'];
            $reply = "Profil mahasiswa:\n\n";
            $reply .= "- Nama: **{$s['nama_lengkap']}**\n";
            $reply .= "- NIM: {$s['nim']}\n";
            $reply .= "- Program studi: {$s['prodi']}\n";
            $reply .= "- Angkatan: {$s['angkatan']}\n";
            $reply .= "- Status akademik: {$s['status_akademik']}\n";
            $reply .= "- Wajah terdaftar: " . ((int) $s['face_registered'] === 1 ? 'ya' : 'belum') . "\n";
            return $this->result($reply, $context);
        }
        if (($studentResult['status'] ?? '') === 'ambiguous') {
            return $this->studentClarification($studentResult['options'] ?? [], $context);
        }

        $lecturerResult = $this->findLecturer($q);
        if (($lecturerResult['status'] ?? '') === 'found') {
            $l = $lecturerResult['lecturer'];
            $name = trim(($l['gelar_depan'] ? $l['gelar_depan'] . ' ' : '') . $l['nama_lengkap'] . ($l['gelar_belakang'] ? ', ' . $l['gelar_belakang'] : ''));
            $reply = "Profil dosen:\n\n";
            $reply .= "- Nama: **{$name}**\n";
            $reply .= "- NIDN: {$l['nidn']}\n";
            $reply .= "- Email: " . ($l['email'] ?: '-') . "\n";
            return $this->result($reply, $context);
        }
        if (($lecturerResult['status'] ?? '') === 'ambiguous') {
            $options = array_map(static fn($l) => $l['nama_lengkap'] . ' (' . $l['nidn'] . ')', $lecturerResult['options']);
            return $this->clarification('Saya menemukan beberapa dosen: ' . implode(', ', $options) . '. Sebutkan NIDN atau nama lengkapnya.', ['pending_intent' => 'profile']);
        }

        return $this->clarification('Sebutkan nama atau NIM mahasiswa, atau nama/NIDN dosen yang profilnya ingin dilihat.', ['pending_intent' => 'profile']);
    }

    private function entityCount(string $q, array $user, ?array $period, array $context): array
    {
        // 1. Mata Kuliah (dihitung dari tabel mata_kuliah sesuai dashboard admin)
        if (preg_match('/\b(mata\s+kuliah|matkul)\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM mata_kuliah")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 2. Jadwal Kelas / Jadwal Kuliah (dihitung dari jadwal_kuliah is_active = 1 sesuai dashboard admin)
        if (preg_match('/\bjadwal\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM jadwal_kuliah WHERE is_active = 1")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 3. Dosen (dihitung dari tabel dosen sesuai dashboard admin)
        if (preg_match('/\bdosen\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 4. Mahasiswa (dihitung dari tabel mahasiswa sesuai dashboard admin)
        if (preg_match('/\bmahasiswa\b/ui', $q)) {
            $studentStatus = $this->studentStatusFilter($q);
            if ($studentStatus !== null) {
                $count = $this->countStudentsByStatus($user, $studentStatus);
            } else {
                $count = (int) $this->db->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
            }
            return $this->result((string) $count, $context);
        }

        // 5. Pengguna / User (dihitung dari tabel users)
        if (preg_match('/\b(pengguna|user|akun)\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 6. Keterlambatan / Terlambat (dihitung dari tabel absensi status = 'terlambat')
        if (preg_match('/\b(keterlambatan|terlambat|telat)\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM absensi WHERE status = 'terlambat'")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 7. Kelas (dihitung dari tabel kelas)
        if (preg_match('/\bkelas\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 8. Admin (dihitung dari tabel users role = 'admin')
        if (preg_match('/\badmin\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 9. Pertemuan (dihitung dari tabel pertemuan_kuliah)
        if (preg_match('/\bpertemuan\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM pertemuan_kuliah")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        // 10. Absensi (dihitung dari tabel absensi)
        if (preg_match('/\babsensi\b/ui', $q)) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM absensi")->fetchColumn();
            return $this->result((string) $count, $context);
        }

        return $this->overview($q, $user, $period, $context);
    }

    private function courseList(string $q, array $user, ?array $period, array $context): array
    {
        $rows = $this->db->query("SELECT nama_mk FROM mata_kuliah ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($rows)) {
            return $this->result("Tidak ada data mata kuliah yang tersedia di database.", $context);
        }

        $lines = [];
        foreach ($rows as $index => $name) {
            $lines[] = ($index + 1) . '. ' . $name;
        }

        return $this->result(implode("\n", $lines), $context);
    }

    private function lecturerList(string $q, array $user, ?array $period, array $context): array
    {
        $rows = $this->db->query("SELECT nama_lengkap, gelar_depan, gelar_belakang FROM dosen ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return $this->result("Tidak ada data dosen yang terdaftar di database.", $context);
        }

        $lines = [];
        foreach ($rows as $index => $row) {
            $name = trim(($row['gelar_depan'] ? $row['gelar_depan'] . ' ' : '') . $row['nama_lengkap'] . ($row['gelar_belakang'] ? ', ' . $row['gelar_belakang'] : ''));
            $lines[] = ($index + 1) . '. ' . ($name !== '' ? $name : $row['nama_lengkap']);
        }

        return $this->result(implode("\n", $lines), $context);
    }

    private function countStudentsByStatus(array $user, string $status): int
    {
        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));

        if ($role === 'admin') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM mahasiswa WHERE status_akademik = :status");
            $stmt->execute(['status' => $status]);
            return (int) $stmt->fetchColumn();
        }

        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            if ($dosenId <= 0) {
                return 0;
            }
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT m.id)
                 FROM mahasiswa m
                 JOIN kelas_mahasiswa km ON km.mahasiswa_id = m.id AND km.status = 'aktif'
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE m.status_akademik = :status AND jk.dosen_id = :dosen_id"
            );
            $stmt->execute(['status' => $status, 'dosen_id' => $dosenId]);
            return (int) $stmt->fetchColumn();
        }

        if ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            if ($mahasiswaId <= 0) {
                return 0;
            }
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM mahasiswa WHERE id = :id AND status_akademik = :status"
            );
            $stmt->execute(['id' => $mahasiswaId, 'status' => $status]);
            return (int) $stmt->fetchColumn();
        }

        return 0;
    }

    private function overviewCounts(): array
    {
        $sql = "SELECT
            (SELECT COUNT(*) FROM mahasiswa) AS mahasiswa,
            (SELECT COUNT(*) FROM dosen) AS dosen,
            (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admin,
            (SELECT COUNT(*) FROM mata_kuliah) AS mata_kuliah,
            (SELECT COUNT(*) FROM kelas) AS kelas,
            (SELECT COUNT(*) FROM jadwal_kuliah WHERE is_active = 1) AS jadwal,
            (SELECT COUNT(*) FROM pertemuan_kuliah) AS pertemuan,
            (SELECT COUNT(*) FROM absensi) AS absensi,
            (SELECT COUNT(*) FROM izin_sakit) AS izin_sakit";
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return array_map('intval', $row ?: []);
    }

    /**
     * Angka ringkasan dibatasi sesuai role agar dosen/mahasiswa tidak melihat
     * agregat data milik pengguna lain.
     */
    private function scopedOverviewCounts(array $user): array
    {
        $counts = $this->overviewCounts();
        $role = strtolower((string) ($user['role'] ?? 'mahasiswa'));

        if ($role === 'admin') {
            return $counts;
        }

        $scalar = function (string $sql, array $params): int {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };

        if ($role === 'dosen') {
            $dosenId = (int) ($user['dosen_id'] ?? 0);
            if ($dosenId <= 0 && !empty($user['id'])) {
                $dosenId = $this->dosenIdForUser((int) $user['id']);
            }
            if ($dosenId <= 0) {
                return array_map(static fn() => 0, $counts);
            }

            $counts['mahasiswa'] = $scalar(
                "SELECT COUNT(DISTINCT km.mahasiswa_id)
                 FROM kelas_mahasiswa km
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE jk.dosen_id = :dosen_id AND km.status = 'aktif'",
                ['dosen_id' => $dosenId]
            );
            $counts['jadwal'] = $scalar(
                "SELECT COUNT(*) FROM jadwal_kuliah WHERE dosen_id = :dosen_id AND is_active = 1",
                ['dosen_id' => $dosenId]
            );
            $counts['pertemuan'] = $scalar(
                "SELECT COUNT(*) FROM pertemuan_kuliah pk
                 JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
                 WHERE jk.dosen_id = :dosen_id",
                ['dosen_id' => $dosenId]
            );
            $counts['absensi'] = $scalar(
                "SELECT COUNT(*) FROM absensi a
                 JOIN pertemuan_kuliah pk ON pk.id = a.pertemuan_id
                 JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
                 WHERE jk.dosen_id = :dosen_id",
                ['dosen_id' => $dosenId]
            );
            $counts['izin_sakit'] = $scalar(
                "SELECT COUNT(*) FROM izin_sakit iz
                 JOIN jadwal_kuliah jk ON jk.id = iz.jadwal_id
                 WHERE jk.dosen_id = :dosen_id",
                ['dosen_id' => $dosenId]
            );
            return $counts;
        }

        if ($role === 'mahasiswa') {
            $mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
            if ($mahasiswaId <= 0 && !empty($user['id'])) {
                $mahasiswaId = $this->mahasiswaIdForUser((int) $user['id']);
            }
            if ($mahasiswaId <= 0) {
                return array_map(static fn() => 0, $counts);
            }

            $counts['mahasiswa'] = 1;
            $counts['dosen'] = $scalar(
                "SELECT COUNT(DISTINCT jk.dosen_id)
                 FROM kelas_mahasiswa km
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE km.mahasiswa_id = :mahasiswa_id AND km.status = 'aktif'",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['mata_kuliah'] = $scalar(
                "SELECT COUNT(DISTINCT jk.mata_kuliah_id)
                 FROM kelas_mahasiswa km
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE km.mahasiswa_id = :mahasiswa_id AND km.status = 'aktif'",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['kelas'] = $scalar(
                "SELECT COUNT(DISTINCT jk.kelas_id)
                 FROM kelas_mahasiswa km
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE km.mahasiswa_id = :mahasiswa_id AND km.status = 'aktif'",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['jadwal'] = $scalar(
                "SELECT COUNT(DISTINCT jk.id)
                 FROM kelas_mahasiswa km
                 JOIN jadwal_kuliah jk ON jk.id = km.jadwal_id
                 WHERE km.mahasiswa_id = :mahasiswa_id AND km.status = 'aktif'",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['pertemuan'] = $scalar(
                "SELECT COUNT(*) FROM pertemuan_kuliah pk
                 JOIN jadwal_kuliah jk ON jk.id = pk.jadwal_id
                 JOIN kelas_mahasiswa km ON km.jadwal_id = jk.id
                 WHERE km.mahasiswa_id = :mahasiswa_id AND km.status = 'aktif'",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['absensi'] = $scalar(
                "SELECT COUNT(*) FROM absensi WHERE mahasiswa_id = :mahasiswa_id",
                ['mahasiswa_id' => $mahasiswaId]
            );
            $counts['izin_sakit'] = $scalar(
                "SELECT COUNT(*) FROM izin_sakit WHERE mahasiswa_id = :mahasiswa_id",
                ['mahasiswa_id' => $mahasiswaId]
            );
            return $counts;
        }

        return array_map(static fn() => 0, $counts);
    }

    // =========================================================================
    // FORMAT & UTIL
    // =========================================================================

    private function result(string $reply, array $context, string $source = 'app_database'): array
    {
        return [
            'success' => true,
            'reply' => $reply,
            'source' => $source,
            'context' => $context,
        ];
    }

    private function emptyReply(string $what, ?array $period, array $context): array
    {
        if (str_starts_with($what, 'tidak ada ')) {
            $what = substr($what, strlen('tidak ada '));
        }
        $label = $period['label'] ?? 'periode yang diminta';
        $reply = "Tidak ditemukan {$what} pada **{$label}** berdasarkan query database.\n\n";
        $reply .= $this->periodNote($period);
        return $this->result($reply, $context);
    }

    private function clarification(string $message, array $context = []): array
    {
        return [
            'success' => true,
            'reply' => $message,
            'source' => 'database_clarification',
            'context' => $context,
        ];
    }

    private function studentClarification(array $students, array $context): array
    {
        $options = array_map(static fn($s) => $s['nama_lengkap'] . ' (' . $s['nim'] . ')', $students);
        return $this->clarification('Saya menemukan lebih dari satu mahasiswa: ' . implode(', ', $options) . '. Sebutkan NIM atau nama lengkapnya.', $context);
    }

    private function periodNote(?array $period): string
    {
        if ($period === null) {
            return '';
        }
        $notes = [];
        if (!empty($period['ta_label'])) {
            $notes[] = 'Tahun akademik: ' . $period['ta_label'] . '.';
        }
        if (!empty($period['note'])) {
            $notes[] = (string) $period['note'];
        }
        return $notes !== [] ? '_' . implode(' ', $notes) . '_' : '';
    }

    private function extractThreshold(string $q): float
    {
        if (preg_match('/\b(?:di\s+bawah|kurang\s+dari|minimal|batas)\s*([0-9]+(?:[.,][0-9]+)?)\s*%/ui', $q, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return (float) (AppConfig::config()['academic']['min_attendance_percent'] ?? 75.0);
    }

    private function extractLimit(string $q): int
    {
        if (preg_match('/\b(?:top|limit)\s*([0-9]{1,2})\b/ui', $q, $m)) {
            return max(1, min(self::MAX_ROWS, (int) $m[1]));
        }
        if (preg_match('/\b([0-9]{1,2})\s*teratas\b/ui', $q, $m)) {
            return max(1, min(self::MAX_ROWS, (int) $m[1]));
        }
        return 10;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return $value;
    }

    private function formatDate(string $date): string
    {
        try {
            $dt = new DateTimeImmutable($date, $this->timezone);
        } catch (Throwable) {
            return $date;
        }
        return $dt->format('j') . ' ' . $this->monthName((int) $dt->format('n')) . ' ' . $dt->format('Y');
    }

    private function formatDateTime(string $dateTime): string
    {
        try {
            $dt = new DateTimeImmutable($dateTime, $this->timezone);
        } catch (Throwable) {
            return $dateTime;
        }
        return $this->formatDate($dt->format('Y-m-d')) . ' ' . $dt->format('H:i');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    private function monthNumber(string $name): int
    {
        return [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        ][mb_strtolower($name)] ?? 0;
    }

    private function dayName(string $isoDay): string
    {
        return [
            '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis',
            '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
        ][$isoDay] ?? 'Senin';
    }
}
