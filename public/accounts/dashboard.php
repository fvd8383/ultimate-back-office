<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

Session::requireAuth('login.php');

$testingNotice = '';
$normalizeEnterpriseModules = true;

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $showTestingTools = dashboard_testing_tools_enabled();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['testing_action'] ?? '') !== '' && $showTestingTools) {
        $businessId = (int) ($_POST['business_id'] ?? 0);
        $testingAction = (string) ($_POST['testing_action'] ?? '');

        if ($testingAction === 'reset_onboarding') {
            $testingNotice = BusinessFoundation::resetOnboardingForTesting($businessId, (int) $user['id'])
                ? 'Onboarding status was reset.'
                : 'That business could not be found for this account.';
        } elseif ($testingAction === 'remove_modules') {
            if (BusinessFoundation::removeModuleAssignmentsForTesting($businessId, (int) $user['id'])) {
                $testingNotice = 'Module assignments were removed.';
                $normalizeEnterpriseModules = false;
            } else {
                $testingNotice = 'That business could not be found for this account.';
            }
        }
    }

    $businesses = BusinessFoundation::businessesForDashboard((int) $user['id'], $normalizeEnterpriseModules);
    $canAddBusiness = dashboard_has_enterprise_access($businesses);
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $businesses = [];
    $showTestingTools = false;
    $canAddBusiness = false;
    $loadError = 'Dashboard data could not be loaded. Check the environment and database setup.';
}

function dashboard_testing_tools_enabled(): bool
{
    try {
        return strtolower((string) Database::config('APP_ENV', 'production')) === 'staging'
            && (bool) Database::config('APP_DEBUG', false);
    } catch (Throwable $exception) {
        return false;
    }
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
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
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
        <?= ui_button('Log out', 'logout.php') ?>
    </div>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php endif; ?>

<?php if ($testingNotice !== ''): ?>
    <?= ui_alert($testingNotice, 'info') ?>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Linked Businesses</h2>

    <?php if (count($businesses) === 0): ?>
        <p class="muted">No business is linked to this account yet. Business setup is required before Lead Hub can be used.</p>
        <?= ui_button('Create Business', 'business-create.php') ?>
    <?php else: ?>
        <?php if ($canAddBusiness): ?>
            <?= ui_alert('Account Plan: Enterprise', 'info') ?>
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
                                <?= ui_badge((string) $module['name'], 'module') ?>
                            <?php endforeach; ?>
                            <?php if (count($business['active_modules']) === 0): ?>
                                <?= ui_badge('No active modules', 'status') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="business-list__meta">
                        <?php $roleName = (string) ($business['role_name'] ?? 'No role'); ?>
                        <?php $isOwner = (int) $business['is_owner'] === 1; ?>
                        <?php if (!($isOwner && strcasecmp($roleName, 'Owner') === 0)): ?>
                            <?= ui_badge($roleName, 'role') ?>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                            <?= ui_badge('Owner', 'role') ?>
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
            <p class="secondary-link"><?= ui_button('Add Business', 'business-create.php') ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($showTestingTools && count($businesses) > 0): ?>
    <section class="dashboard-card">
        <p class="eyebrow">Staging only</p>
        <h2>Testing Tools</h2>
        <div class="business-list">
            <?php foreach ($businesses as $business): ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($business['business_name']) ?></h3>
                        <p class="muted">Use these tools only for staging onboarding tests.</p>
                    </div>
                    <div class="business-list__meta">
                        <form method="post" action="dashboard.php">
                            <input type="hidden" name="business_id" value="<?= e($business['id']) ?>">
                            <input type="hidden" name="testing_action" value="reset_onboarding">
                            <?= ui_button('Reset onboarding status', '', 'primary', ['class' => 'ubo-button--compact']) ?>
                        </form>
                        <form method="post" action="dashboard.php" onsubmit="return confirm('Remove all module assignments for this business?');">
                            <input type="hidden" name="business_id" value="<?= e($business['id']) ?>">
                            <input type="hidden" name="testing_action" value="remove_modules">
                            <?= ui_button('Remove module assignments', '', 'secondary', ['class' => 'ubo-button--compact']) ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
