<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Session;
use App\Models\IzinSakit;

class IzinController extends Controller
{
    private IzinSakit $izinModel;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->izinModel = new IzinSakit();
    }

    public function index(): void
    {
        $user = Session::get('user');

        $filters = [
            'status' => $this->request->get('status'),
            'tipe' => $this->request->get('tipe'),
            'tanggal' => $this->request->get('tanggal')
        ];

        $leaves = $this->izinModel->getAllLeaves($filters);

        $data = [
            'title' => 'Manajemen Izin & Sakit - Admin Akademik',
            'user' => $user,
            'leaves' => $leaves,
            'filters' => $filters
        ];

        $this->render('admin/izin', $data);
    }

    public function proses(mixed $id): void
    {
        $izinId = (int)$id;
        $status = $this->request->post('status_persetujuan');
        $catatan = trim($this->request->post('catatan_dosen') ?? '');

        if (!in_array($status, ['disetujui', 'ditolak'])) {
            Session::setFlash('error', 'Status tidak valid.');
            $this->response->redirect(base_url('admin/izin'));
            return;
        }

        $izin = $this->izinModel->find($izinId);
        if (!$izin) {
            Session::setFlash('error', 'Data tidak ditemukan.');
            $this->response->redirect(base_url('admin/izin'));
            return;
        }

        $user = Session::get('user');

        $this->izinModel->update($izinId, [
            'status_persetujuan' => $status,
            'catatan_dosen' => $catatan,
            'diverifikasi_oleh' => $user['id'],
            'waktu_verifikasi' => date('Y-m-d H:i:s')
        ]);

        if ($status === 'disetujui') {
            $pertemuanModel = new \App\Models\Pertemuan();
            $absensiModel = new \App\Models\Absensi();

            $ptm = $pertemuanModel->query(
                "SELECT id FROM pertemuan_kuliah WHERE jadwal_id = :jid AND tanggal = :tgl LIMIT 1",
                ['jid' => $izin['jadwal_id'], 'tgl' => $izin['tanggal_mulai']]
            );
            $pertemuanId = $ptm[0]['id'] ?? null;

            if (!$pertemuanId) {
                $nextKe = $pertemuanModel->count("jadwal_id = :jid", ['jid' => $izin['jadwal_id']]) + 1;
                $pertemuanId = $pertemuanModel->insert([
                    'jadwal_id' => $izin['jadwal_id'],
                    'pertemuan_ke' => $nextKe,
                    'tanggal' => $izin['tanggal_mulai'],
                    'status' => 'ditutup',
                ]);
            }

            $existing = $absensiModel->query(
                "SELECT id FROM absensi WHERE pertemuan_id = :pid AND mahasiswa_id = :mid",
                ['pid' => $pertemuanId, 'mid' => $izin['mahasiswa_id']]
            );

            $absensiId = 0;
            if (empty($existing)) {
                $absensiId = (int) $absensiModel->insert([
                    'pertemuan_id' => $pertemuanId,
                    'mahasiswa_id' => $izin['mahasiswa_id'],
                    'waktu_absensi' => date('Y-m-d H:i:s'),
                    'status' => $izin['tipe'],
                    'keterangan' => 'Disetujui Admin: ' . $catatan
                ]);
            } else {
                $absensiId = (int) $existing[0]['id'];
                $absensiModel->update($absensiId, [
                    'status' => $izin['tipe'],
                    'keterangan' => 'Disetujui Admin: ' . $catatan
                ]);
            }

            // Notifikasi konfirmasi absensi (izin/sakit disetujui) ke mahasiswa dan dosen
            if ($absensiId > 0) {
                try {
                    $mhsRows = $absensiModel->query(
                        "SELECT m.nama_lengkap, m.nim, jk.id AS jadwal_id, jk.hari, jk.jam_mulai, jk.jam_selesai,
                                jk.ruangan, mk.nama_mk, mk.kode_mk, k.nama_kelas, d.nama_lengkap AS nama_dosen
                         FROM mahasiswa m
                         JOIN jadwal_kuliah jk ON jk.id = :jid
                         JOIN mata_kuliah mk ON mk.id = jk.mata_kuliah_id
                         JOIN kelas k ON k.id = jk.kelas_id
                         JOIN dosen d ON d.id = jk.dosen_id
                         WHERE m.id = :mid LIMIT 1",
                        ['jid' => (int) $izin['jadwal_id'], 'mid' => (int) $izin['mahasiswa_id']]
                    );
                    if (!empty($mhsRows[0])) {
                        $info = $mhsRows[0];
                        (new \App\Services\WhatsAppNotificationService())->enqueueAttendanceConfirmation(
                            $absensiId,
                            [
                                'mahasiswa_id'  => (int) $izin['mahasiswa_id'],
                                'jadwal_id'     => (int) $izin['jadwal_id'],
                                'nama_mhs'      => (string) ($info['nama_lengkap'] ?? 'Mahasiswa'),
                                'nim'           => (string) ($info['nim'] ?? ''),
                                'nama_mk'       => (string) ($info['nama_mk'] ?? ''),
                                'kode_mk'       => (string) ($info['kode_mk'] ?? ''),
                                'nama_dosen'    => (string) ($info['nama_dosen'] ?? ''),
                                'nama_kelas'    => (string) ($info['nama_kelas'] ?? ''),
                                'hari'          => (string) ($info['hari'] ?? ''),
                                'jam_mulai'     => (string) ($info['jam_mulai'] ?? ''),
                                'jam_selesai'   => (string) ($info['jam_selesai'] ?? ''),
                                'ruangan'       => (string) ($info['ruangan'] ?? ''),
                                'status'        => (string) $izin['tipe'],
                                'tanggal'       => (string) $izin['tanggal_mulai'],
                                'waktu_absensi' => date('Y-m-d H:i:s'),
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    error_log('[WhatsApp] Gagal enqueue konfirmasi izin disetujui: ' . get_class($e));
                }
            }
        } elseif ($status === 'ditolak') {
            // Notifikasi pemberitahuan izin ditolak kepada mahasiswa bersangkutan
            try {
                $service = new \App\Services\WhatsAppNotificationService();
                $judul = 'Pengajuan ' . ucfirst((string) $izin['tipe']) . ' Ditolak';
                $pesan = 'Pengajuan ' . strtolower((string) $izin['tipe']) . ' Anda untuk tanggal ' . (string) $izin['tanggal_mulai'] . ' tidak disetujui oleh admin.';
                if ($catatan !== '') {
                    $pesan .= ' Catatan: ' . $catatan;
                }
                $service->enqueueAnnouncement(
                    $judul,
                    $pesan,
                    'mahasiswa',
                    (string) $izin['mahasiswa_id'],
                    (int) ($user['id'] ?? 1)
                );
            } catch (\Throwable $e) {
                error_log('[WhatsApp] Gagal enqueue notifikasi izin ditolak: ' . get_class($e));
            }
        }

        Session::setFlash('success', 'Status pengajuan berhasil diperbarui.');
        $this->response->redirect(base_url('admin/izin'));
    }
}
