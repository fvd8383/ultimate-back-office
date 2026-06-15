<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$loadError = '';
$users = [];

try {
    $users = AdminPortal::users();
} catch (Throwable $exception) {
    $loadError = 'Users could not be loaded.';
}

admin_begin('Users', 'users', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Users</p>
    <h1>User management</h1>
    <p class="muted">View platform users and open user detail pages.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="business-switcher">
        <div class="admin-table">
            <div class="admin-table__head">
                <span>Name</span><span>Email</span><span>Status</span><span>Created Date</span><span></span>
            </div>
            <?php foreach ($users as $user): ?>
                <div class="admin-table__row">
                    <span><?= e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?></span>
                    <span><?= e($user['email']) ?></span>
                    <span><?= ui_badge(AdminPortal::statusLabel($user['status']), 'status') ?></span>
                    <span><?= e($user['created_at']) ?></span>
                    <span><a href="user.php?user_id=<?= e($user['id']) ?>">Open</a></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
