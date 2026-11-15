<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\TahunAkademik;
use DateTimeImmutable;
use Throwable;

/**
 * Controller pengelolaan jadwal kuliah untuk Administrator.
 *
 * Catatan struktur aktual:
 * - Semester tidak berada di tabel jadwal_kuliah, melainkan di tahun_akademik.
 * - Ruangan belum memiliki tabel master; pilihan berasal dari ruangan yang
 *   sudah pernah dipakai dan admin tetap boleh mengetik ruangan baru.
 * - kelas_mahasiswa adalah KRS per jadwal, bukan tabel absensi.
 */
class JadwalController extends Controller
{
    private Jadwal $jadwalModel;
    private AuditLog $auditLog;
    private TahunAkademik $tahunModel;
    private MataKuliah $mataKuliahModel;
    private Dosen $dosenModel;
    private Kelas $kelasModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->jadwalModel = new Jadwal();
        $this->auditLog = new AuditLog();
        $this->tahunModel = new TahunAkademik();
        $this->mataKuliahModel = new MataKuliah();
        $this->dosenModel = new Dosen();
        $this->kelasModel = new Kelas();
    }

    /**
     * Daftar jadwal dengan filter hari, kelas, dosen, mata kuliah, dan TA.
     */
    public function index(): void
    {
        $filters = $this->collectFilters();

        $this->render('admin/jadwal/index', [
            'title'     => 'Manajemen Jadwal Kuliah - STMIK IKMI Cirebon',
            'schedules' => $this->jadwalModel->getAllWithDetails($filters),
            'filters'   => $filters,
            'options'   => $this->jadwalModel->getFormOptions(),
            'days'      => Jadwal::DAYS,
        ]);
    }

    /**
     * Form tambah jadwal.
     */
    public function create(): void
    {
        $activeTa = $this->tahunModel->getActive();

        $this->renderForm(null, [
            'tahun_akademik_id'       => (int) ($activeTa['id'] ?? 0),
            'hari'                    => '',
            'mata_kuliah_id'          => '',
            'dosen_id'                => '',
            'kelas_id'                => '',
            'jam_mulai'               => '08:00',
            'jam_selesai'             => '10:30',
            'ruangan'                 => '',
            'toleransi_keterlambatan' => 15,
            'is_active'               => 1,
            'salin_peserta'           => 1,
        ]);
    }

    /**
     * Simpan jadwal baru.
     */
    public function store(): void
    {
        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        $dbData = $this->toDatabase($input);

        if (empty($errors)) {
            $conflicts = $this->jadwalModel->findConflicts($dbData);
            if (!empty($conflicts)) {
                $errors['conflict'] = [$this->formatConflictMessage($conflicts)];
            }
        }

        if (!empty($errors)) {
            $this->backWithErrors('admin/jadwal/create', $errors, $input);
            return;
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $id = (int) $this->jadwalModel->insert($dbData);

            $copied = 0;
            if (!empty($input['salin_peserta'])) {
                $copied = $this->jadwalModel->copyParticipantsFromClass(
                    $id,
                    (int) $input['kelas_id'],
                    (int) $input['tahun_akademik_id']
                );
            }

            $this->auditLog->logActivity(
                $this->currentUserId(),
                'JADWAL_CREATE',
                'jadwal_kuliah',
                $id,
                null,
                array_merge($dbData, ['salin_peserta' => $copied])
            );

            $db->commit();

            $message = 'Jadwal berhasil ditambahkan.';
            if ($copied > 0) {
                $message .= " {$copied} peserta aktif disalin dari jadwal lain pada kelas yang sama.";
            } elseif (!empty($input['salin_peserta'])) {
                $message .= ' Belum ada peserta yang dapat disalin; pastikan KRS kelas sudah tersedia.';
            }

            $ta = $this->tahunModel->find((int) $input['tahun_akademik_id']);
            if ($ta && (int) $ta['is_active'] !== 1) {
                $message .= ' Tahun akademik yang dipilih nonaktif; jadwal belum tampil di mahasiswa sampai tahun akademik diaktifkan.';
            }

            Session::setFlash('success', $message);
            $this->redirect(base_url('admin/jadwal'));
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Jadwal] Gagal menambah jadwal: ' . $e->getMessage());
            $this->backWithErrors(
                'admin/jadwal/create',
                ['general' => ['Jadwal gagal disimpan. Periksa kembali data dan koneksi database.']],
                $input
            );
        }
    }

    /**
     * Detail satu jadwal beserta peserta dan riwayat terkait.
     */
    public function show(mixed $id): void
    {
        $jadwalId = (int) $id;
        $schedule = $this->jadwalModel->findDetailed($jadwalId);

        if (!$schedule) {
            Session::setFlash('error', 'Jadwal tidak ditemukan.');
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $this->render('admin/jadwal/show', [
            'title'        => 'Detail Jadwal - STMIK IKMI Cirebon',
            'schedule'     => $schedule,
            'participants' => $this->jadwalModel->getParticipants($jadwalId),
            'relatedCounts' => $this->jadwalModel->getRelatedCounts($jadwalId),
        ]);
    }

    /**
     * Form edit jadwal.
     */
    public function edit(mixed $id): void
    {
        $jadwalId = (int) $id;
        $schedule = $this->jadwalModel->findDetailed($jadwalId);

        if (!$schedule) {
            Session::setFlash('error', 'Jadwal tidak ditemukan.');
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $this->renderForm(
            $schedule,
            $this->scheduleToForm($schedule),
            $this->jadwalModel->getRelatedCounts($jadwalId)
        );
    }

    /**
     * Perbarui jadwal.
     */
    public function update(mixed $id): void
    {
        $jadwalId = (int) $id;
        $existing = $this->jadwalModel->findDetailed($jadwalId);

        if (!$existing) {
            Session::setFlash('error', 'Jadwal tidak ditemukan.');
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        $related = $this->jadwalModel->getRelatedCounts($jadwalId);
        $dbData = $this->toDatabase($input);

        // Kelas, mata kuliah, dan tahun akademik menentukan identitas KRS/riwayat.
        // Jika sudah ada data terkait, perubahan identitas tersebut ditolak agar
        // riwayat absensi tidak berpindah kelas/mata kuliah secara diam-diam.
        if ($related['total'] > 0) {
            foreach (['tahun_akademik_id', 'mata_kuliah_id', 'kelas_id'] as $field) {
                if ((string) $existing[$field] !== (string) $input[$field]) {
                    $errors['locked'][] = 'Jadwal ini sudah memiliki peserta/riwayat. Kelas, mata kuliah, dan tahun akademik tidak dapat diubah. Nonaktifkan jadwal ini lalu buat jadwal baru bila diperlukan.';
                    break;
                }
            }
        }

        if (empty($errors)) {
            $conflicts = $this->jadwalModel->findConflicts($dbData, $jadwalId);
            if (!empty($conflicts)) {
                $errors['conflict'] = [$this->formatConflictMessage($conflicts)];
            }
        }

        if (!empty($errors)) {
            $this->backWithErrors("admin/jadwal/{$jadwalId}/edit", $errors, $input);
            return;
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $this->jadwalModel->update($jadwalId, $dbData);

            $copied = 0;
            if (!empty($input['salin_peserta']) && $related['total'] === 0) {
                $copied = $this->jadwalModel->copyParticipantsFromClass(
                    $jadwalId,
                    (int) $input['kelas_id'],
                    (int) $input['tahun_akademik_id']
                );
            }

            $this->auditLog->logActivity(
                $this->currentUserId(),
                'JADWAL_UPDATE',
                'jadwal_kuliah',
                $jadwalId,
                $this->auditSnapshot($existing),
                array_merge($this->auditSnapshot($dbData), ['salin_peserta' => $copied])
            );

            $db->commit();

            $message = 'Jadwal berhasil diperbarui.';
            if ($copied > 0) {
                $message .= " {$copied} peserta aktif disalin dari jadwal lain pada kelas yang sama.";
            }

            $ta = $this->tahunModel->find((int) $input['tahun_akademik_id']);
            if ($ta && (int) $ta['is_active'] !== 1) {
                $message .= ' Tahun akademik yang dipilih nonaktif; jadwal belum tampil di mahasiswa sampai tahun akademik diaktifkan.';
            }

            Session::setFlash('success', $message);
            $this->redirect(base_url('admin/jadwal'));
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Jadwal] Gagal memperbarui jadwal: ' . $e->getMessage());
            $this->backWithErrors(
                "admin/jadwal/{$jadwalId}/edit",
                ['general' => ['Jadwal gagal diperbarui. Periksa kembali data dan koneksi database.']],
                $input
            );
        }
    }

    /**
     * Aktifkan / nonaktifkan jadwal tanpa menghapus riwayat.
     */
    public function toggle(mixed $id): void
    {
        $jadwalId = (int) $id;
        $schedule = $this->jadwalModel->findDetailed($jadwalId);

        if (!$schedule) {
            Session::setFlash('error', 'Jadwal tidak ditemukan.');
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $newStatus = ((int) $schedule['is_active'] === 1) ? 0 : 1;

        if ($newStatus === 1) {
            $conflicts = $this->jadwalModel->findConflicts($this->scheduleToDatabase($schedule), $jadwalId);
            if (!empty($conflicts)) {
                Session::setFlash('error', $this->formatConflictMessage($conflicts));
                $this->redirect(base_url('admin/jadwal'));
                return;
            }
        }

        $this->jadwalModel->update($jadwalId, ['is_active' => $newStatus]);

        $this->auditLog->logActivity(
            $this->currentUserId(),
            $newStatus === 1 ? 'JADWAL_ACTIVATE' : 'JADWAL_DEACTIVATE',
            'jadwal_kuliah',
            $jadwalId,
            ['is_active' => (int) $schedule['is_active']],
            ['is_active' => $newStatus]
        );

        Session::setFlash(
            'success',
            $newStatus === 1
                ? 'Jadwal berhasil diaktifkan kembali.'
                : 'Jadwal berhasil dinonaktifkan. Riwayat absensi dan KRS tetap tersimpan.'
        );
        $this->redirect(base_url('admin/jadwal'));
    }

    /**
     * Hapus permanen hanya jika belum memiliki data terkait. Jika sudah ada
     * peserta, pertemuan, absensi, atau izin, jadwal dinonaktifkan.
     */
    public function destroy(mixed $id): void
    {
        $jadwalId = (int) $id;
        $schedule = $this->jadwalModel->findDetailed($jadwalId);

        if (!$schedule) {
            Session::setFlash('error', 'Jadwal tidak ditemukan.');
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $related = $this->jadwalModel->getRelatedCounts($jadwalId);

        if ($related['total'] > 0) {
            $this->jadwalModel->update($jadwalId, ['is_active' => 0]);

            $this->auditLog->logActivity(
                $this->currentUserId(),
                'JADWAL_DEACTIVATE',
                'jadwal_kuliah',
                $jadwalId,
                ['is_active' => (int) $schedule['is_active']],
                ['is_active' => 0, 'reason' => 'has_related_data', 'related' => $related]
            );

            Session::setFlash(
                'success',
                'Jadwal tidak dihapus permanen karena sudah memiliki data terkait '
                . "({$related['peserta']} peserta, {$related['pertemuan']} pertemuan, "
                . "{$related['absensi']} absensi, {$related['izin']} izin). "
                . 'Jadwal telah dinonaktifkan dan riwayat tetap aman.'
            );
            $this->redirect(base_url('admin/jadwal'));
            return;
        }

        $before = $this->auditSnapshot($schedule);
        $this->jadwalModel->delete($jadwalId);

        $this->auditLog->logActivity(
            $this->currentUserId(),
            'JADWAL_DELETE',
            'jadwal_kuliah',
            $jadwalId,
            $before,
            null
        );

        Session::setFlash('success', 'Jadwal berhasil dihapus permanen karena belum memiliki data terkait.');
        $this->redirect(base_url('admin/jadwal'));
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function renderForm(?array $schedule, array $formData, array $relatedCounts = []): void
    {
        $errors = Session::get('_jadwal_errors', []);
        $oldInput = Session::get('_jadwal_old');

        if (is_array($oldInput)) {
            $formData = array_merge($formData, $oldInput);
            Session::remove('_jadwal_old');
        }

        // Identitas yang dikunci harus tetap menampilkan nilai tersimpan,
        // bukan nilai yang baru saja ditolak validasi.
        if ($schedule !== null && (int) ($relatedCounts['total'] ?? 0) > 0) {
            $formData['tahun_akademik_id'] = (int) $schedule['tahun_akademik_id'];
            $formData['mata_kuliah_id'] = (int) $schedule['mata_kuliah_id'];
            $formData['kelas_id'] = (int) $schedule['kelas_id'];
        }

        Session::remove('_jadwal_errors');

        $this->render('admin/jadwal/form', [
            'title'         => ($schedule ? 'Edit Jadwal' : 'Tambah Jadwal') . ' - STMIK IKMI Cirebon',
            'isEdit'        => $schedule !== null,
            'schedule'      => $schedule,
            'formData'      => $formData,
            'errors'        => is_array($errors) ? $errors : [],
            'relatedCounts' => $relatedCounts,
            'options'       => $this->jadwalModel->getFormOptions(),
            'days'          => Jadwal::DAYS,
        ]);
    }

    private function collectFilters(): array
    {
        $hari = (string) $this->request->get('hari', '');
        if (!in_array($hari, Jadwal::DAYS, true)) {
            $hari = '';
        }

        $status = (string) $this->request->get('status', '');
        $isActive = '';
        if ($status === 'aktif') {
            $isActive = 1;
        } elseif ($status === 'nonaktif') {
            $isActive = 0;
        } else {
            $status = '';
        }

        return [
            'hari'              => $hari,
            'kelas_id'          => (int) $this->request->get('kelas_id', 0),
            'dosen_id'          => (int) $this->request->get('dosen_id', 0),
            'mata_kuliah_id'    => (int) $this->request->get('mata_kuliah_id', 0),
            'tahun_akademik_id' => (int) $this->request->get('tahun_akademik_id', 0),
            'status'            => $status,
            'is_active'         => $isActive,
        ];
    }

    private function collectInput(): array
    {
        $ruangan = (string) $this->request->raw('ruangan', '');
        $ruangan = trim(strip_tags(html_entity_decode($ruangan, ENT_QUOTES, 'UTF-8')));
        $ruangan = preg_replace('/\s+/', ' ', $ruangan) ?? '';

        $jamMulaiRaw = trim((string) $this->request->raw('jam_mulai', ''));
        $jamSelesaiRaw = trim((string) $this->request->raw('jam_selesai', ''));
        $jamMulai = $this->normalizeTime($jamMulaiRaw);
        $jamSelesai = $this->normalizeTime($jamSelesaiRaw);

        return [
            'tahun_akademik_id'       => (int) $this->request->post('tahun_akademik_id', 0),
            'mata_kuliah_id'          => (int) $this->request->post('mata_kuliah_id', 0),
            'dosen_id'                => (int) $this->request->post('dosen_id', 0),
            'kelas_id'                => (int) $this->request->post('kelas_id', 0),
            'hari'                    => (string) $this->request->post('hari', ''),
            'jam_mulai'               => $jamMulai !== null ? substr($jamMulai, 0, 5) : $jamMulaiRaw,
            'jam_selesai'             => $jamSelesai !== null ? substr($jamSelesai, 0, 5) : $jamSelesaiRaw,
            'ruangan'                 => $ruangan,
            'toleransi_keterlambatan' => (int) $this->request->post('toleransi_keterlambatan', 15),
            'is_active'               => $this->request->post('is_active') !== null ? 1 : 0,
            'salin_peserta'           => $this->request->post('salin_peserta') !== null ? 1 : 0,
        ];
    }

    private function validateInput(array $input): array
    {
        $errors = [];

        if (!in_array($input['hari'], Jadwal::DAYS, true)) {
            $errors['hari'][] = 'Hari wajib dipilih antara Senin sampai Sabtu.';
        }

        if ($input['tahun_akademik_id'] <= 0 || !$this->tahunModel->find($input['tahun_akademik_id'])) {
            $errors['tahun_akademik_id'][] = 'Tahun akademik tidak valid.';
        }

        if ($input['mata_kuliah_id'] <= 0 || !$this->mataKuliahModel->find($input['mata_kuliah_id'])) {
            $errors['mata_kuliah_id'][] = 'Mata kuliah tidak valid.';
        }

        if ($input['dosen_id'] <= 0 || !$this->dosenModel->find($input['dosen_id'])) {
            $errors['dosen_id'][] = 'Dosen pengampu tidak valid.';
        }

        if ($input['kelas_id'] <= 0 || !$this->kelasModel->find($input['kelas_id'])) {
            $errors['kelas_id'][] = 'Kelas tidak valid.';
        }

        if ($input['ruangan'] === '') {
            $errors['ruangan'][] = 'Ruangan wajib diisi.';
        } elseif (mb_strlen($input['ruangan']) > 50) {
            $errors['ruangan'][] = 'Ruangan maksimal 50 karakter.';
        }

        if ($input['toleransi_keterlambatan'] < 0 || $input['toleransi_keterlambatan'] > 120) {
            $errors['toleransi_keterlambatan'][] = 'Toleransi keterlambatan harus antara 0 sampai 120 menit.';
        }

        $start = $this->normalizeTime((string) $input['jam_mulai']);
        $end = $this->normalizeTime((string) $input['jam_selesai']);

        if ($start === null) {
            $errors['jam_mulai'][] = 'Jam mulai tidak valid. Gunakan format HH:MM.';
        }

        if ($end === null) {
            $errors['jam_selesai'][] = 'Jam selesai tidak valid. Gunakan format HH:MM.';
        }

        if ($start !== null && $end !== null && $end <= $start) {
            $errors['jam_selesai'][] = 'Jam selesai harus lebih besar daripada jam mulai.';
        }

        return $errors;
    }

    private function toDatabase(array $input): array
    {
        return [
            'tahun_akademik_id'       => (int) $input['tahun_akademik_id'],
            'mata_kuliah_id'          => (int) $input['mata_kuliah_id'],
            'dosen_id'                => (int) $input['dosen_id'],
            'kelas_id'                => (int) $input['kelas_id'],
            'hari'                    => (string) $input['hari'],
            'jam_mulai'               => $this->normalizeTime((string) $input['jam_mulai']) ?? '00:00:00',
            'jam_selesai'             => $this->normalizeTime((string) $input['jam_selesai']) ?? '00:00:00',
            'ruangan'                 => (string) $input['ruangan'],
            'toleransi_keterlambatan' => (int) $input['toleransi_keterlambatan'],
            'is_active'               => (int) $input['is_active'],
        ];
    }

    private function scheduleToForm(array $schedule): array
    {
        return [
            'tahun_akademik_id'       => (int) $schedule['tahun_akademik_id'],
            'mata_kuliah_id'          => (int) $schedule['mata_kuliah_id'],
            'dosen_id'                => (int) $schedule['dosen_id'],
            'kelas_id'                => (int) $schedule['kelas_id'],
            'hari'                    => (string) $schedule['hari'],
            'jam_mulai'               => substr((string) $schedule['jam_mulai'], 0, 5),
            'jam_selesai'             => substr((string) $schedule['jam_selesai'], 0, 5),
            'ruangan'                 => (string) $schedule['ruangan'],
            'toleransi_keterlambatan' => (int) $schedule['toleransi_keterlambatan'],
            'is_active'               => (int) $schedule['is_active'],
            'salin_peserta'           => 0,
        ];
    }

    private function scheduleToDatabase(array $schedule): array
    {
        return [
            'tahun_akademik_id'       => (int) $schedule['tahun_akademik_id'],
            'mata_kuliah_id'          => (int) $schedule['mata_kuliah_id'],
            'dosen_id'                => (int) $schedule['dosen_id'],
            'kelas_id'                => (int) $schedule['kelas_id'],
            'hari'                    => (string) $schedule['hari'],
            'jam_mulai'               => (string) $schedule['jam_mulai'],
            'jam_selesai'             => (string) $schedule['jam_selesai'],
            'ruangan'                 => (string) $schedule['ruangan'],
            'toleransi_keterlambatan' => (int) $schedule['toleransi_keterlambatan'],
            'is_active'               => (int) $schedule['is_active'],
        ];
    }

    private function auditSnapshot(array $data): array
    {
        $keys = [
            'tahun_akademik_id',
            'mata_kuliah_id',
            'dosen_id',
            'kelas_id',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan',
            'toleransi_keterlambatan',
            'is_active',
        ];

        $snapshot = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $snapshot[$key] = $data[$key];
            }
        }

        return $snapshot;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        foreach (['H:i:s', 'H:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('H:i:s');
            }
        }

        return null;
    }

    private function formatConflictMessage(array $conflicts): string
    {
        $labels = [
            'dosen'   => 'dosen',
            'kelas'   => 'kelas',
            'ruangan' => 'ruangan',
        ];

        $items = [];
        foreach (array_slice($conflicts, 0, 5) as $conflict) {
            $types = array_map(
                fn($type) => $labels[$type] ?? $type,
                $conflict['conflict_types'] ?? []
            );

            $items[] = sprintf(
                '%s %s-%s: %s %s (%s) bentrok pada %s',
                $conflict['hari'],
                substr((string) $conflict['jam_mulai'], 0, 5),
                substr((string) $conflict['jam_selesai'], 0, 5),
                $conflict['kode_mk'],
                $conflict['nama_mk'],
                $conflict['nama_kelas'],
                implode(', ', $types)
            );
        }

        $message = 'Jadwal bentrok dengan jadwal aktif lain dan tidak dapat disimpan. ' . implode('; ', $items);

        if (count($conflicts) > 5) {
            $message .= '; dan ' . (count($conflicts) - 5) . ' jadwal lain.';
        }

        return $message;
    }

    private function backWithErrors(string $path, array $errors, array $input): void
    {
        $firstMessage = 'Periksa kembali data jadwal.';

        foreach ($errors as $messages) {
            if (is_array($messages) && !empty($messages)) {
                $firstMessage = (string) reset($messages);
                break;
            }
            if (is_string($messages) && $messages !== '') {
                $firstMessage = $messages;
                break;
            }
        }

        Session::set('_jadwal_errors', $errors);
        Session::set('_jadwal_old', $input);
        Session::setFlash('error', $firstMessage);

        $this->redirect(base_url($path));
    }

    private function currentUserId(): int
    {
        $user = Session::get('user');
        return (int) ($user['id'] ?? 0);
    }
}
