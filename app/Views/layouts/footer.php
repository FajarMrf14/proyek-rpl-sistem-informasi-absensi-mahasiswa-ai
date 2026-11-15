    <!-- Bootstrap 5.3.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>

    <!-- Core App JS -->
    <script src="<?= asset('js/app.js') ?>"></script>

    <!-- Flash Message Notification Handler -->
    <?php if (has_flash('success')): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= addslashes(flash('success')) ?>',
                timer: 3500,
                showConfirmButton: false,
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>

    <?php if (has_flash('error')): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Perhatian!',
                text: '<?= addslashes(flash('error')) ?>',
                confirmButtonColor: '#1e3a8a',
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>

    <?php if (has_flash('info')): ?>
        <script>
            Swal.fire({
                icon: 'info',
                title: 'Informasi',
                text: '<?= addslashes(flash('info')) ?>',
                customClass: { popup: 'rounded-4' }
            });
        </script>
    <?php endif; ?>
</body>
</html>
