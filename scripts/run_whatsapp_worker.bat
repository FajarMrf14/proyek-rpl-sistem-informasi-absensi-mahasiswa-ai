@echo off
REM ==============================================================================
REM SISTEM ABSENSI MAHASISWA - STMIK IKMI CIREBON
REM Worker Otomatis Notifikasi WhatsApp (Fonnte Gateway)
REM
REM PENGGUNAAN:
REM   1. Mode Sekali Jalan (Cocok untuk Windows Task Scheduler / Cron tiap 5 menit):
REM      run_whatsapp_worker.bat
REM
REM   2. Mode Loop Terus-Menerus (Cocok untuk Terminal / Development):
REM      run_whatsapp_worker.bat --loop
REM      run_whatsapp_worker.bat --interval=60
REM
REM   3. Cek Status Antrean & Konfigurasi:
REM      run_whatsapp_worker.bat status
REM ==============================================================================

setlocal enabledelayedexpansion

REM Pindah ke direktori root proyek
cd /d "%~dp0\.."

REM Deteksi binary PHP
set "PHP_BIN=php"
where php >nul 2>&1
if errorlevel 1 (
    if exist "C:\laragon\bin\php\php-8.4.13-nts-Win32-vs17-x64\php.exe" (
        set "PHP_BIN=C:\laragon\bin\php\php-8.4.13-nts-Win32-vs17-x64\php.exe"
    ) else (
        echo [ERROR] PHP executable tidak ditemukan di PATH maupun Laragon.
        exit /b 1
    )
)

if "%1"=="" (
    "%PHP_BIN%" app\Console\whatsapp.php run
) else (
    "%PHP_BIN%" app\Console\whatsapp.php %*
)

