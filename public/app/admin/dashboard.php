<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$loadError = '';
$metrics = [];
$recentSignups = [];
$recentBusinesses = [];
$recentWebsites = [];

try {
    $metrics = AdminPortal::dashboardMetrics();
    $recentSignups = AdminPortal::recentSignups();
    $recentBusinesses = AdminPortal::recentBusinesses();
    $recentWebsites = AdminPortal::recentWebsiteGenerations();
} catch (Throwable $exception) {
    $loadError = 'Admin dashboard could not be loaded. Run the Sprint 5 migration and check the database setup.';
}

admin_begin('Dashboard', 'dashboard', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Internal Admin</p>
    <h1>Operational control center</h1>
    <p class="muted">Manage users, businesses, onboarding progress, generated websites, and support activity.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="metrics-grid admin-metrics" aria-label="Platform metrics">
        <a class="metric-card" href="users.php"><span>Total Users</span><strong><?= e($metrics['total_users']) ?></strong></a>
        <a class="metric-card" href="businesses.php"><span>Total Businesses</span><strong><?= e($metrics['total_businesses']) ?></strong></a>
        <a class="metric-card" href="websites.php"><span>Total Websites</span><strong><?= e($metrics['total_websites']) ?></strong></a>
        <a class="metric-card" href="websites.php"><span>Businesses Ready For Build</span><strong><?= e($metrics['ready_for_build']) ?></strong></a>
        <a class="metric-card" href="websites.php"><span>Generated Websites</span><strong><?= e($metrics['generated_websites']) ?></strong></a>
    </section>

    <section class="admin-panels">
        <article class="business-switcher">
            <h2>Recent Signups</h2>
            <div class="admin-list">
                <?php foreach ($recentSignups as $signup): ?>
                    <a href="user.php?user_id=<?= e($signup['id']) ?>">
                        <strong><?= e(trim((string) $signup['first_name'] . ' ' . (string) $signup['last_name'])) ?></strong>
                        <span><?= e($signup['email']) ?> · <?= e($signup['created_at']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="business-switcher">
            <h2>Recent Businesses</h2>
            <div class="admin-list">
                <?php foreach ($recentBusinesses as $business): ?>
                    <a href="business.php?business_id=<?= e($business['id']) ?>">
                        <strong><?= e($business['business_name']) ?></strong>
                        <span><?= e($business['owner_first_name'] . ' ' . $business['owner_last_name']) ?> · <?= e(AdminPortal::statusLabel($business['internal_status'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="business-switcher">
            <h2>Recent Website Generations</h2>
            <div class="admin-list">
                <?php foreach ($recentWebsites as $website): ?>
                    <a href="website.php?website_id=<?= e($website['id']) ?>">
                        <strong><?= e($website['business_name']) ?></strong>
                        <span><?= e($website['template_name']) ?> · <?= e($website['generated_at']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (count($recentWebsites) === 0): ?>
                    <p class="muted">No websites generated yet.</p>
                <?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
