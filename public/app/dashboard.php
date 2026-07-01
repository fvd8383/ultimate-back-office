<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';
require_once __DIR__ . '/../../private/classes/LeadHub.php';

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
    $activeModules = $business ? BusinessFoundation::customerActiveModules((int) $business['id']) : [];
    $productModules = array_values(array_filter($activeModules, static function (array $module): bool {
        return ($module['module_key'] ?? '') !== 'lead_hub';
    }));
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
    $recentWebsiteLeads = $business ? LeadHub::recent247spWebsiteLeads((int) $business['id'], 5) : [];
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $business = null;
    $activeModules = [];
    $productModules = [];
    $hasLeadHubAccess = false;
    $has247spAccess = false;
    $summary = [
        'contact_count' => 0,
        'task_count' => 0,
        'recent_activity' => [],
    ];
    $recentWebsiteLeads = [];
    $loadError = 'Workspace dashboard could not be loaded. Check the environment and database setup.';
}

$pageTitle = 'Lead Hub Dashboard - Ultimate Back Office';
$bodyClass = 'app-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
require __DIR__ . '/../../private/views/lead-hub-navigation.php';
$accountsDashboardHref = $accountsBaseUrl . '/dashboard.php';
$businessIdForLinks = $business ? (int) $business['id'] : 0;
$leadHubNavItems = lead_hub_nav_items($businessIdForLinks, 'dashboard');
?>
<?php application_shell_begin('lead_hub', ['area' => 'app', 'user' => $user, 'business' => $business, 'secondary_nav' => $leadHubNavItems]); ?>
        <section class="hero-panel">
            <p class="eyebrow">Lead Hub</p>
            <h1><?= $business ? e($business['business_name']) : 'CRM workspace' ?></h1>
            <p class="muted">Manage leads, contacts, tasks, notes, and recent customer activity for this business.</p>
            <?php if ($user): ?>
                <p class="muted">Signed in as <?= e($user['first_name']) ?> <?= e($user['last_name']) ?> &lt;<?= e($user['email']) ?>&gt;</p>
            <?php endif; ?>
        </section>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>No matching business is linked to this account. Return to Accounts to create or manage a business.</p>
                <?= ui_button('Accounts Dashboard', $accountsDashboardHref, 'primary') ?>
            </section>
        <?php else: ?>
            <?php if (!$hasLeadHubAccess): ?>
                <?= ui_alert('CRM workspace access is not active for this business.', 'warning') ?>
            <?php endif; ?>

            <section class="business-switcher">
                <h2>CRM Actions</h2>
                <div class="button-row">
                    <?= ui_button('Add Contact', 'lead-hub/contact.php?business_id=' . urlencode((string) $businessIdForLinks) . '&type=contact', 'primary') ?>
                    <?= ui_button('View Contacts', 'lead-hub/contacts.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                    <?= ui_button('View Leads', 'lead-hub/leads.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                </div>
            </section>

            <section class="business-switcher">
                <h2>Product Status</h2>
                <div class="pill-list">
                    <?php foreach ($productModules as $module): ?>
                        <?= ui_badge((string) $module['name'] . ' · ' . (string) $module['activation_source'], 'module') ?>
                    <?php endforeach; ?>
                    <?php if (count($productModules) === 0): ?>
                        <?= ui_badge('No active products', 'status') ?>
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

            <section class="metrics-grid" id="lead-hub" aria-label="Lead Hub summary">
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
                <h2>Recent 247SP Website Submissions</h2>
                <?php if (count($recentWebsiteLeads) === 0): ?>
                    <p class="muted">No 247SP website submissions yet.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentWebsiteLeads as $lead): ?>
                            <?php $leadName = trim((string) $lead['first_name'] . ' ' . (string) $lead['last_name']); ?>
                            <article>
                                <strong><a href="lead-hub/lead.php?business_id=<?= e($business['id']) ?>&contact_id=<?= e($lead['id']) ?>"><?= e($leadName !== '' ? $leadName : 'Website lead') ?></a></strong>
                                <p><?= e($lead['source_detail'] ?: '247SP website') ?></p>
                                <span><?= e($lead['submitted_at'] ?: $lead['updated_at']) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="business-switcher">
                <h2>Recent Activity</h2>
                <?php if (count($summary['recent_activity']) === 0): ?>
                    <p class="muted">No lead, contact, task, or note activity yet.</p>
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
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
