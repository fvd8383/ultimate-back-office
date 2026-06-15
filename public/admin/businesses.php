<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$loadError = '';
$businesses = [];

try {
    $businesses = AdminPortal::businesses();
} catch (Throwable $exception) {
    $loadError = 'Businesses could not be loaded.';
}

admin_begin('Businesses', 'businesses', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Businesses</p>
    <h1>Business management</h1>
    <p class="muted">View onboarding status, website status, active modules, and internal status.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="business-switcher">
        <div class="admin-table admin-table--businesses">
            <div class="admin-table__head">
                <span>Business Name</span><span>Owner</span><span>Onboarding Status</span><span>Website Status</span><span>Active Modules</span><span>Internal Status</span><span></span>
            </div>
            <?php foreach ($businesses as $business): ?>
                <div class="admin-table__row">
                    <span><?= e($business['business_name']) ?></span>
                    <span><?= e($business['owner_name'] ?: 'Not set') ?></span>
                    <span><?= e(AdminPortal::statusLabel($business['onboarding_status'])) ?></span>
                    <span><?= e(AdminPortal::statusLabel($business['website_status'])) ?></span>
                    <span><?= e($business['active_modules'] ?: 'None') ?></span>
                    <span>
                        <?= ui_badge(AdminPortal::statusLabel($business['internal_status']), (int) $business['is_suspended'] === 1 ? 'role' : 'status') ?>
                        <?php if ((int) $business['is_test_account'] === 1): ?>
                            <?= ui_badge('Test', 'role') ?>
                        <?php endif; ?>
                    </span>
                    <span><a href="business.php?business_id=<?= e($business['id']) ?>">Open</a></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
