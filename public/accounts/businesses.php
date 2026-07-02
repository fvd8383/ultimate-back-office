<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

Session::requireAuth('login.php');

$testingNotice = '';
$normalizeEnterpriseModules = false;

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $showTestingTools = businesses_testing_tools_enabled();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['testing_action'] ?? '') !== '' && $showTestingTools) {
        $businessId = (int) ($_POST['business_id'] ?? 0);
        $testingAction = (string) ($_POST['testing_action'] ?? '');

        if ($testingAction === 'reset_onboarding') {
            $testingNotice = BusinessFoundation::resetOnboardingForTesting($businessId, (int) $user['id'])
                ? 'Onboarding status was reset.'
                : 'That business could not be found for this account.';
        } elseif ($testingAction === 'remove_modules') {
            if (BusinessFoundation::removeModuleAssignmentsForTesting($businessId, (int) $user['id'])) {
                $testingNotice = 'Module assignments were removed. Billing subscription records were not changed.';
                $normalizeEnterpriseModules = false;
            } else {
                $testingNotice = 'That business could not be found for this account.';
            }
        }
    }

    $businesses = BusinessFoundation::businessesForDashboard((int) $user['id'], $normalizeEnterpriseModules);
    $canAddBusiness = businesses_can_add_business($businesses);
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $businesses = [];
    $showTestingTools = false;
    $canAddBusiness = false;
    $loadError = 'Businesses could not be loaded. Check the environment and database setup.';
}

function businesses_testing_tools_enabled(): bool
{
    try {
        return strtolower((string) Database::config('APP_ENV', 'production')) === 'staging'
            && (bool) Database::config('APP_DEBUG', false);
    } catch (Throwable $exception) {
        return false;
    }
}

function businesses_can_add_business(array $businesses): bool
{
    return count($businesses) === 0;
}

function businesses_location(array $business): string
{
    $location = trim(implode(', ', array_filter([
        (string) ($business['city'] ?? ''),
        (string) ($business['state'] ?? ''),
    ])));

    return $location !== '' ? $location : 'Location not set';
}

$pageTitle = 'Businesses - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('businesses');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Account</p>
    <h1>Businesses</h1>
    <p class="muted">View and manage the business profiles linked to your account.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php endif; ?>

<?php if ($testingNotice !== ''): ?>
    <?= ui_alert($testingNotice, 'info') ?>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Linked Businesses</h2>
    <p class="muted">Business profiles control the details used across active products.</p>

    <?php if (count($businesses) === 0): ?>
        <section class="empty-state">
            <h2>No businesses linked yet</h2>
            <p>Create a business profile before using workspace tools.</p>
            <?php if ($canAddBusiness): ?>
                <?= ui_button('Create Business', 'business-create.php') ?>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="business-list">
            <?php foreach ($businesses as $business): ?>
                <?php
                $activeProductModules = $business['active_modules'] ?? [];
                ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($business['business_name']) ?></h3>
                        <p><?= e(businesses_location($business)) ?></p>
                        <p class="muted">Status: <?= e(ucwords(str_replace('_', ' ', (string) $business['setup_status']))) ?> · Profile <?= e($business['profile_completion']) ?>%</p>
                        <div class="pill-list">
                            <?php foreach ($activeProductModules as $module): ?>
                                <?= ui_badge((string) $module['name'], 'module') ?>
                            <?php endforeach; ?>
                            <?php if (count($activeProductModules) === 0): ?>
                                <?= ui_badge('No active modules', 'status') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="business-list__meta">
                        <div class="business-list__roles">
                            <?php $roleName = (string) ($business['role_name'] ?? 'No role'); ?>
                            <?php $isOwner = (int) $business['is_owner'] === 1; ?>
                            <?php if (!($isOwner && strcasecmp($roleName, 'Owner') === 0)): ?>
                                <?= ui_badge($roleName, 'role') ?>
                            <?php endif; ?>
                            <?php if ($isOwner): ?>
                                <?= ui_badge('Owner', 'role') ?>
                            <?php endif; ?>
                        </div>
                        <div class="business-actions" aria-label="Business actions for <?= e($business['business_name']) ?>">
                            <?= ui_button('Edit Business', 'business.php?business_id=' . urlencode((string) $business['id']), 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
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
                        <p class="muted">Use these tools only for onboarding tests.</p>
                    </div>
                    <div class="business-list__meta">
                        <form method="post" action="businesses.php">
                            <input type="hidden" name="business_id" value="<?= e($business['id']) ?>">
                            <input type="hidden" name="testing_action" value="reset_onboarding">
                            <?= ui_button('Reset onboarding status', '', 'primary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>
                        <form method="post" action="businesses.php" onsubmit="return confirm('Remove all module assignments for this business?');">
                            <input type="hidden" name="business_id" value="<?= e($business['id']) ?>">
                            <input type="hidden" name="testing_action" value="remove_modules">
                            <?= ui_button('Remove module assignments', '', 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
