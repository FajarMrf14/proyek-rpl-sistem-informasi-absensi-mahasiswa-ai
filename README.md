# SISTEM INFORMASI ABSENSI MAHASISWA BERBASIS WEB DENGAN INTEGRASI AI

## Identitas Mahasiswa

| Keterangan | Informasi |
|---|---|
| Nama | Fajar Ma'ruf |
| NIM | 41257191 |
| Program Studi | S1 Teknik Informatika |
| Perguruan Tinggi | STMIK IKMI Cirebon |
| Kelompok | Kere Hore |
| Mata Kuliah | Rekayasa Perangkat Lunak |
| Dosen Pengampu | Yudhistira Arie Wijaya, M.Kom. |

## Tema Topik Kelompok

Sistem Informasi Berbasis Web di Lingkungan Kampus

## Deskripsi Aplikasi

Sistem Informasi Absensi Mahasiswa Berbasis Web dengan Integrasi AI merupakan aplikasi yang dirancang untuk membantu pengelolaan presensi mahasiswa secara digital di lingkungan STMIK IKMI Cirebon.

Aplikasi menyediakan fitur pengelolaan data pengguna, jadwal perkuliahan, presensi mahasiswa, pengajuan izin dan sakit, pemantauan kehadiran, serta rekapitulasi presensi.

Sistem menggunakan teknologi berbasis web dengan arsitektur MVC dan database MySQL.

## Fitur Utama Aplikasi

### 1. Administrator
- Manajemen pengguna.
- Pengelolaan jadwal perkuliahan.
- Pengelolaan konfigurasi sistem.
- Verifikasi pengajuan izin dan sakit.
- Pemantauan dan rekapitulasi presensi.

### 2. Dosen
- Melihat jadwal mengajar.
- Melihat daftar kelas.
- Memantau pertemuan perkuliahan.
- Melihat rekapitulasi kehadiran mahasiswa.

### 3. Mahasiswa
- Melihat dashboard kehadiran.
- Melihat jadwal kuliah.
- Melakukan presensi berbasis GPS dan verifikasi wajah.
- Mengajukan izin dan sakit.
- Melihat riwayat presensi.

### 4. Integrasi Artificial Intelligence (AI)

**A. AI Face Recognition**

Menggunakan face-api.js untuk mengambil fitur wajah berupa descriptor 128 dimensi melalui kamera browser. Backend membandingkan descriptor wajah dengan data wajah mahasiswa yang telah terdaftar menggunakan perhitungan Euclidean Distance dan similarity.

**B. AI Academic Chatbot**

Menggunakan Google Gemini API dengan pendekatan Retrieval-Augmented Generation (RAG). Chatbot mengambil informasi akademik dari database, seperti jadwal kuliah, data kehadiran, keterlambatan, dan informasi akademik mahasiswa, kemudian menggunakan Gemini untuk menyusun jawaban berdasarkan konteks data tersebut.

### 5. Notifikasi WhatsApp
- Pengingat jadwal kuliah otomatis.
- Integrasi WhatsApp Gateway menggunakan Fonnte.

## Teknologi yang Digunakan

- PHP 8.4
- JavaScript ES6+
- HTML5 dan CSS3
- Bootstrap 5 / Tailwind CSS (sesuaikan dengan implementasi aktual)
- MySQL 8
- PDO
- face-api.js
- Google Gemini API
- Fonnte WhatsApp Gateway
- Git dan GitHub
- GitHub Actions

## Video Presentasi

| Tahapan | Link Video |
|---|---|
| Video Final | https://youtu.be/KWRxGauew7s?si=iivwwF5g6t3gna8O |

## Laporan-Laporan

| Laporan PRD | https://drive.google.com/file/d/1iFn9c6WxjfsHY9OQ64N4Yc9YL1wSYIDD/view?usp=drive_link 
| Laporan SDD | https://drive.google.com/file/d/1Tv90r8x8gpTjdCsp_PL9Okg4vt_PxD63/view?usp=sharing |

## Petunjuk Instalasi Aplikasi

### Persyaratan
- Windows atau sistem operasi yang mendukung PHP.
- Laragon atau Apache/Nginx.
- PHP 8.4.
- MySQL 8.
- Browser modern.
- Koneksi internet untuk integrasi AI dan WhatsApp.

### Langkah Instalasi

1. Clone repository:

   ```bash
   git clone https://github.com/USERNAME/proyek-rpl-absensi-mahasiswa-ai.git
   ```

2. Masuk ke direktori proyek:

   ```bash
   cd proyek-rpl-absensi-mahasiswa-ai
   ```

3. Tempatkan proyek pada direktori web server jika menggunakan Laragon.

4. Buat database MySQL sesuai konfigurasi aplikasi.

5. Import file database SQL yang disediakan dalam repository.

6. Sesuaikan konfigurasi koneksi database melalui konfigurasi lokal aplikasi.

7. Atur konfigurasi integrasi yang diperlukan:
   - Google Gemini API Key.
   - Fonnte API Token.
   - Pengaturan database.
   - Pengaturan aplikasi lainnya.

8. Jalankan Apache dan MySQL melalui Laragon.

9. Buka aplikasi melalui browser menggunakan alamat lokal sesuai konfigurasi web server.

### Catatan Konfigurasi

Jangan mengunggah file yang berisi password database, API Key Gemini, token Fonnte, atau informasi rahasia lainnya ke repository publik.

Gunakan file konfigurasi lokal dan sediakan contoh konfigurasi tanpa kredensial jika diperlukan.

## Testing

Pengujian otomatis dilakukan menggunakan unit test untuk memeriksa fungsi utama aplikasi.

Perintah menjalankan pengujian:

```bash
[ISI PERINTAH TESTING SESUAI IMPLEMENTASI PROYEK]
```

## Continuous Integration / Continuous Deployment (CI/CD)

Repository ini menggunakan GitHub Actions untuk menjalankan proses otomatisasi pengujian ketika terdapat perubahan kode yang dikirim ke GitHub.

Lokasi workflow:

```text
.github/workflows/ci.yml
```

Status CI/CD: [TAMBAHKAN BADGE SETELAH WORKFLOW BERHASIL]

## Struktur Repository

```text
proyek-rpl-absensi-mahasiswa-ai/
├── .github/
│   └── workflows/
│       └── ci.yml
├── app/
├── public/
├── database/
├── tests/
├── README.md
└── .gitignore
```

Struktur di atas merupakan gambaran umum. Sesuaikan dengan struktur folder proyek yang sebenarnya.

## Penutup

Proyek ini dikembangkan sebagai implementasi tugas akhir mandiri mata kuliah Rekayasa Perangkat Lunak, Program Studi S1 Teknik Informatika, STMIK IKMI Cirebon.
