<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

Session::requireAuth('login.php');

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $businesses = BusinessFoundation::businessesForDashboard((int) $user['id'], false);
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $businesses = [];
    $loadError = 'Dashboard data could not be loaded. Check the environment and database setup.';
}

$businessCount = count($businesses);
$completedProfiles = count(array_filter($businesses, static fn (array $business): bool => (int) ($business['profile_completion'] ?? 0) >= 100));
$activeProductNames = [];

foreach ($businesses as $business) {
    foreach ($business['active_modules'] ?? [] as $module) {
        if (($module['module_key'] ?? '') === 'lead_hub') {
            continue;
        }

        $activeProductNames[(string) ($module['module_key'] ?? $module['name'])] = (string) $module['name'];
    }
}

$pageTitle = 'Accounts Dashboard - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('dashboard');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Accounts</p>
    <h1>Welcome<?= $user ? ', ' . e($user['first_name']) : '' ?></h1>
    <?php if ($user): ?>
        <p class="muted"><?= e($user['email']) ?></p>
    <?php endif; ?>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Overview</h2>
    <p class="muted">Review linked business counts and setup progress from your account home.</p>
    <dl class="summary-list">
        <div><dt>Linked Businesses</dt><dd><?= e((string) $businessCount) ?></dd></div>
        <div><dt>Complete Profiles</dt><dd><?= e((string) $completedProfiles) ?></dd></div>
        <div><dt>Active Products</dt><dd><?= e((string) count($activeProductNames)) ?></dd></div>
    </dl>
    <div class="button-row">
        <?= ui_button('View Businesses', 'businesses.php', 'secondary') ?>
        <?php if ($businessCount === 0): ?>
            <?= ui_button('Create Business', 'business-create.php') ?>
        <?php endif; ?>
    </div>
</section>

<section class="dashboard-card">
    <h2>Alerts</h2>
    <?php if ($businessCount === 0): ?>
        <p class="muted">Create a business profile to start setting up your workspace.</p>
    <?php else: ?>
        <p class="muted">No alerts need your attention right now.</p>
    <?php endif; ?>
</section>

<section class="dashboard-card">
    <h2>Reporting</h2>
    <p class="muted">Your business overview, alerts, and performance reports will appear here.</p>
</section>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
