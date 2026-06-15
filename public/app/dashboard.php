<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_GET['business_id'] ?? 0);
    $business = $requestedBusinessId > 0
        ? BusinessFoundation::businessForUser($requestedBusinessId, (int) $user['id'])
        : BusinessFoundation::firstBusinessForUser((int) $user['id']);
    $activeModules = $business ? BusinessFoundation::activeModules((int) $business['id']) : [];
    $hasLeadHubAccess = false;
    $has247spAccess = false;
    foreach ($activeModules as $module) {
        if (($module['module_key'] ?? '') === 'lead_hub') {
            $hasLeadHubAccess = true;
        }

        if (($module['module_key'] ?? '') === '247sp') {
            $has247spAccess = true;
        }
    }
    $summary = $business ? BusinessFoundation::leadHubSummary((int) $business['id']) : [
        'contact_count' => 0,
        'task_count' => 0,
        'recent_activity' => [],
    ];
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $business = null;
    $activeModules = [];
    $hasLeadHubAccess = false;
    $has247spAccess = false;
    $summary = [
        'contact_count' => 0,
        'task_count' => 0,
        'recent_activity' => [],
    ];
    $loadError = 'Lead Hub could not be loaded. Check the environment and database setup.';
}

$pageTitle = 'Lead Hub - Ultimate Back Office';
$bodyClass = 'app-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="app-layout">
    <?php
    $accountsDashboardHref = $accountsBaseUrl . '/dashboard.php';
    $businessesHref = $business
        ? $accountsBaseUrl . '/business.php?business_id=' . urlencode((string) $business['id'])
        : $accountsDashboardHref;
    $modulesHref = $business
        ? $accountsBaseUrl . '/business-create.php?business_id=' . urlencode((string) $business['id']) . '&step=modules'
        : $accountsDashboardHref;

    $sidebarItems = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php', 'current' => true],
        ['label' => 'Businesses', 'href' => $businessesHref],
        ['label' => 'Modules', 'href' => $modulesHref],
    ];

    if ($business && $has247spAccess) {
        $sidebarItems[] = ['label' => '24/7 Sales Partner', 'href' => '247sp/dashboard.php?business_id=' . urlencode((string) $business['id'])];
    }
    ?>
    <?= ui_sidebar('Lead Hub', $sidebarItems, 'Lead Hub') ?>

    <div class="app-content">
        <section class="hero-panel">
            <p class="eyebrow">Lead Hub</p>
            <h1><?= $business ? e($business['business_name']) : 'Platform foundation' ?></h1>
            <?php if ($user): ?>
                <p class="muted">Signed in as <?= e($user['first_name']) ?> <?= e($user['last_name']) ?> &lt;<?= e($user['email']) ?>&gt;</p>
            <?php endif; ?>
        </section>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>No matching business is linked to this account. Lead Hub is available only for businesses connected to the signed-in user.</p>
            </section>
        <?php else: ?>
            <?php if (!$hasLeadHubAccess): ?>
                <?= ui_alert('Lead Hub is not active for this business.', 'warning') ?>
            <?php endif; ?>

            <section class="business-switcher">
                <h2>Module Status</h2>
                <div class="pill-list">
                    <?php foreach ($activeModules as $module): ?>
                        <?= ui_badge((string) $module['name'] . ' · ' . (string) $module['activation_source'], 'module') ?>
                    <?php endforeach; ?>
                    <?php if (count($activeModules) === 0): ?>
                        <?= ui_badge('No active modules', 'status') ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($has247spAccess): ?>
                <section class="business-switcher product-action-card">
                    <p class="eyebrow">Active module</p>
                    <h2>24/7 Sales Partner</h2>
                    <p class="muted">Open the website onboarding dashboard for this business.</p>
                    <?= ui_button('Open 24/7 Sales Partner', '247sp/dashboard.php?business_id=' . urlencode((string) $business['id']), 'primary', ['class' => 'ubo-dashboard-action ubo-dashboard-action--247sp']) ?>
                </section>
            <?php endif; ?>

            <section class="metrics-grid" aria-label="Lead Hub summary">
                <article>
                    <span>Contacts</span>
                    <strong><?= e($summary['contact_count']) ?></strong>
                </article>
                <article>
                    <span>Tasks</span>
                    <strong><?= e($summary['task_count']) ?></strong>
                </article>
                <article>
                    <span>Activity</span>
                    <strong><?= e(count($summary['recent_activity'])) ?></strong>
                </article>
            </section>

            <section class="business-switcher">
                <h2>Recent Activity</h2>
                <?php if (count($summary['recent_activity']) === 0): ?>
                    <p class="muted">No recent activity yet.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($summary['recent_activity'] as $activity): ?>
                            <article>
                                <strong><?= e($activity['subject'] ?: $activity['activity_type']) ?></strong>
                                <?php if ($activity['description']): ?>
                                    <p><?= e($activity['description']) ?></p>
                                <?php endif; ?>
                                <span><?= e($activity['created_at']) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
