<?php require __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/navbar.php'; ?>

<div class="main-wrapper py-4">
    <div class="container-fluid px-lg-4">
        <?php if (is_logged_in()): ?>
            <div class="row g-4">
                <div class="col-lg-3 col-md-4">
                    <?php require __DIR__ . '/sidebar.php'; ?>
                </div>
                <div class="col-lg-9 col-md-8">
                    <?= $content ?? '' ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <?= $content ?? '' ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer mt-auto py-3 bg-white border-top text-center text-muted fs-8">
    <div class="container">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(campus_name()) ?> - <?= htmlspecialchars(app_name()) ?>.</span>
    </div>
</footer>

<?php if (is_logged_in()): ?>
    <?php require dirname(__DIR__) . '/components/chatbot.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
