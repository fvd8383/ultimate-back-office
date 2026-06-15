<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$userId = (int) ($_GET['user_id'] ?? 0);
$loadError = '';
$managedUser = null;
$businesses = [];
$activeModules = [];
$websiteCount = 0;

try {
    $managedUser = $userId > 0 ? AdminPortal::user($userId) : null;
    if ($managedUser !== null) {
        $businesses = AdminPortal::linkedBusinessesForUser($userId);
        $activeModules = AdminPortal::activeModulesForUser($userId);
        $websiteCount = AdminPortal::websiteCountForUser($userId);
    }
} catch (Throwable $exception) {
    $loadError = 'User detail could not be loaded.';
}

admin_begin('User Detail', 'users', $context);
?>
<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif ($managedUser === null): ?>
    <section class="empty-state">
        <h1>User not found</h1>
        <p>No matching user exists for this request.</p>
    </section>
<?php else: ?>
    <section class="hero-panel">
        <p class="eyebrow">User Detail</p>
        <h1><?= e(trim((string) $managedUser['first_name'] . ' ' . (string) $managedUser['last_name'])) ?></h1>
        <p class="muted"><?= e($managedUser['email']) ?></p>
    </section>

    <section class="business-switcher">
        <h2>User Information</h2>
        <div class="summary-list">
            <div><dt>Email</dt><dd><?= e($managedUser['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($managedUser['phone'] ?: 'Not set') ?></dd></div>
            <div><dt>Status</dt><dd><?= e(AdminPortal::statusLabel($managedUser['status'])) ?></dd></div>
            <div><dt>Created</dt><dd><?= e($managedUser['created_at']) ?></dd></div>
            <div><dt>Website Count</dt><dd><?= e($websiteCount) ?></dd></div>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Active Modules</h2>
        <div class="pill-list"><?= admin_module_badges($activeModules) ?></div>
    </section>

    <section class="business-switcher">
        <h2>Linked Businesses</h2>
        <div class="business-list">
            <?php foreach ($businesses as $business): ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($business['business_name']) ?></h3>
                        <p><?= e(AdminPortal::statusLabel($business['internal_status'])) ?> · <?= e($business['role_name'] ?: 'No role') ?></p>
                        <div class="pill-list"><?= admin_module_badges($business['active_modules']) ?></div>
                    </div>
                    <div class="business-list__meta">
                        <a href="business.php?business_id=<?= e($business['id']) ?>">Open Business</a>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (count($businesses) === 0): ?>
                <p class="muted">No businesses are linked to this user.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
