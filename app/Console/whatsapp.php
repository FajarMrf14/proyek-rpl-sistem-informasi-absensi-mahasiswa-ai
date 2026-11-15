<?php

/**
 * Worker CLI notifikasi WhatsApp.
 *
 * Contoh (dari root proyek):
 *   php app/Console/whatsapp.php run
 *   php app/Console/whatsapp.php dispatch --limit=20
 *   php app/Console/whatsapp.php remind
 *   php app/Console/whatsapp.php remind-lecturer
 *   php app/Console/whatsapp.php absence
 *   php app/Console/whatsapp.php finalize
 *   php app/Console/whatsapp.php low-attendance
 *   php app/Console/whatsapp.php recap
 *   php app/Console/whatsapp.php admin-summary
 *   php app/Console/whatsapp.php status
 *
 * Skrip ini tidak boleh diakses lewat web. Direktori app/ sudah diblokir .htaccess,
 * dan guard PHP_SAPI di bawah adalah pertahanan kedua.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only.\n");
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Config\App as AppConfig;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppNotificationService;

try {
    date_default_timezone_set((string) (AppConfig::config()['timezone'] ?? 'Asia/Jakarta'));
} catch (Throwable) {
    date_default_timezone_set('Asia/Jakarta');
}

$pauseLockFile = dirname(__DIR__, 2) . '/logs/worker_paused.lock';
if (file_exists($pauseLockFile) && ($argv[1] ?? '') !== 'status') {
    fwrite(STDOUT, "Worker ditangguhkan sementara (worker_paused.lock aktif).\n");
    exit(0);
}

$command = strtolower(trim((string) ($argv[1] ?? 'run')));
$limit = 20;
$interval = 0;
foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', (string) $argument, $matches)) {
        $limit = max(1, min(100, (int) $matches[1]));
    }
    if (preg_match('/^--interval=(\d+)$/', (string) $argument, $matches)) {
        $interval = max(5, min(3600, (int) $matches[1]));
    }
    if ($argument === '--loop') {
        $interval = $interval > 0 ? $interval : 60;
    }
}

$logDir = dirname(__DIR__, 2) . '/logs';
$logFile = $logDir . '/whatsapp_worker.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$print = static function (string $label, array $data) use ($logFile): void {
    $timestamp = date('Y-m-d H:i:s');
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    fwrite(STDOUT, $label . ': ' . $payload . PHP_EOL);
    @file_put_contents($logFile, "[{$timestamp}] {$label}: {$payload}" . PHP_EOL, FILE_APPEND | LOCK_EX);
};

try {
    $service = new WhatsAppNotificationService();

    switch ($command) {
        case 'run':
            if ($interval > 0) {
                fwrite(STDOUT, "Worker WhatsApp berjalan dalam loop setiap {$interval} detik. Tekan Ctrl+C untuk berhenti.\n");
                while (true) {
                    $result = $service->runScheduler();
                    $print(date('Y-m-d H:i:s') . ' scheduler', $result);
                    sleep($interval);
                }
            } else {
                $result = $service->runScheduler();
                $print('scheduler', $result);
            }
            break;

        case 'dispatch':
            $result = $service->dispatchPending($limit);
            $print('dispatch', $result);
            break;

        case 'remind':
            $print('schedule_reminders_enqueued', ['total' => $service->enqueueScheduleReminders()]);
            break;

        case 'remind-lecturer':
            $print('lecturer_teaching_reminders_enqueued', ['total' => $service->enqueueLecturerTeachingReminders()]);
            break;

        case 'absence':
            $print('absence_notices_enqueued', ['total' => $service->enqueueAbsenceNotices()]);
            break;

        case 'finalize':
            $print('finalized_meetings', ['total' => $service->finalizeExpiredMeetings()]);
            break;

        case 'low-attendance':
            $print('low_attendance_enqueued', ['total' => $service->enqueueLowAttendanceWarnings()]);
            break;

        case 'recap':
            $result = $service->runRecap();
            $print('recap', $result);
            break;

        case 'admin-summary':
            $print('admin_attendance_summary_enqueued', ['total' => $service->enqueueAdminAttendanceSummary()]);
            break;

        case 'status':
            $counts = (new WhatsAppNotification())->getStatusCounts();
            $print('status', [
                'enabled' => $service->isEnabled(),
                'operational' => $service->isOperational(),
                'simulation_mode' => $service->isSimulationMode(),
                'lecturer_attendance_mode' => $service->getLecturerAttendanceMode(),
                'reminder_minutes' => $service->getReminderMinutes(),
                'timezone' => date_default_timezone_get(),
                'queue' => $counts,
            ]);
            break;

        case 'help':
        case '--help':
        case '-h':
            fwrite(STDOUT, "Perintah: run | dispatch | remind | remind-lecturer | absence | finalize | low-attendance | recap | admin-summary | status\n");
            fwrite(STDOUT, "Opsi: --limit=1..100 (untuk dispatch)\n");
            break;

        default:
            fwrite(STDERR, "Perintah tidak dikenal: {$command}\n");
            fwrite(STDERR, "Gunakan: run | dispatch | remind | remind-lecturer | absence | finalize | low-attendance | recap | admin-summary | status\n");
            exit(2);
    }
} catch (Throwable $e) {
    // Jangan cetak pesan exception mentah ke STDOUT: bisa memuat detail query/data.
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] ERROR: " . get_class($e) . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
    fwrite(STDERR, 'Worker gagal: ' . get_class($e) . PHP_EOL);
    error_log('[WhatsApp CLI] ' . get_class($e));
    exit(1);
}
