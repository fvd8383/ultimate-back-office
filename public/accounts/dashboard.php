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

    $businesses = BusinessFoundation::businessesForDashboard((int) $user['id']);
    $canAddBusiness = dashboard_has_enterprise_access($businesses);
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $businesses = [];
    $canAddBusiness = false;
    $loadError = 'Dashboard data could not be loaded. Check the environment and database setup.';
}

function dashboard_has_enterprise_access(array $businesses): bool
{
    foreach ($businesses as $business) {
        if (!empty($business['has_enterprise'])) {
            return true;
        }
    }

    return false;
}

$pageTitle = 'Accounts Dashboard - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="dashboard-grid">
    <div class="dashboard-card dashboard-card--wide">
        <p class="eyebrow">Accounts</p>
        <h1>Welcome<?= $user ? ', ' . e($user['first_name']) : '' ?></h1>
        <?php if ($user): ?>
            <p class="muted"><?= e($user['email']) ?></p>
        <?php endif; ?>
    </div>

    <div class="dashboard-card">
        <h2>Session</h2>
        <a class="button-link" href="logout.php">Log out</a>
    </div>
</section>

<?php if ($loadError !== ''): ?>
    <div class="error"><?= e($loadError) ?></div>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Linked Businesses</h2>

    <?php if (count($businesses) === 0): ?>
        <p class="muted">No business is linked to this account yet. Business setup is required before Lead Hub can be used.</p>
        <a class="button-link" href="business-create.php">Create Business</a>
    <?php else: ?>
        <?php if ($canAddBusiness): ?>
            <div class="notice">Account Plan: Enterprise</div>
        <?php endif; ?>
        <div class="business-list">
            <?php foreach ($businesses as $business): ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($business['business_name']) ?></h3>
                        <p><?= e($business['city']) ?>, <?= e($business['state']) ?></p>
                        <p class="muted">Status: <?= e($business['setup_status']) ?> · Profile <?= e($business['profile_completion']) ?>%</p>
                        <div class="pill-list">
                            <?php foreach ($business['active_modules'] as $module): ?>
                                <span><?= e($module['name']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($business['active_modules']) === 0): ?>
                                <span>No active modules</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="business-list__meta">
                        <?php $roleName = (string) ($business['role_name'] ?? 'No role'); ?>
                        <?php $isOwner = (int) $business['is_owner'] === 1; ?>
                        <?php if (!($isOwner && strcasecmp($roleName, 'Owner') === 0)): ?>
                            <span><?= e($roleName) ?></span>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                            <span>Owner</span>
                        <?php endif; ?>
                        <a href="business.php?business_id=<?= e($business['id']) ?>">Edit Profile</a>
                        <a href="business-create.php?business_id=<?= e($business['id']) ?>&step=modules">Manage Modules</a>
                        <?php if ($business['setup_status'] !== 'complete'): ?>
                            <a href="business-create.php?business_id=<?= e($business['id']) ?>">Continue Setup</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($canAddBusiness): ?>
            <p class="secondary-link"><a class="button-link" href="business-create.php">Add Business</a></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
