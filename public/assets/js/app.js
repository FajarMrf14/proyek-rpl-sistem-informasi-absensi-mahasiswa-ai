/**
 * SISTEM ABSENSI MAHASISWA BERBASIS WEB DENGAN AI - STMIK IKMI CIREBON
 * Core Application JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // Inisialisasi Tooltip Bootstrap jika ada
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    console.log("STMIK IKMI Attendance System Foundation initialized successfully.");
});
