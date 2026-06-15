<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$user = null;
$business = null;
$summary = [];
$loadError = '';
$accessDenied = false;
$completed = isset($_GET['completed']);

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = isset($_GET['business_id']) ? (int) $_GET['business_id'] : null;
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId, (int) $user['id']);

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);
        $summary = $accessDenied ? [] : TwentyFourSevenSalesPartner::dashboardSummary($businessId);
    }
} catch (Throwable $exception) {
    $loadError = '24/7 Sales Partner could not be loaded. Check the environment and database setup.';
}

function sp247_status_label(string $status): string
{
    $labels = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'ready_for_build' => 'Ready For Build',
        'published' => 'Published',
        'not_selected' => 'Not Selected',
        'pending' => 'Pending',
        'registered' => 'Registered',
        'active' => 'Active',
        'complete' => 'Complete',
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$pageTitle = '24/7 Sales Partner - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
?>
<section class="app-layout">
    <?= ui_sidebar('24/7 Sales Partner', [
        ['label' => '247SP Dashboard', 'href' => 'dashboard.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : ''), 'current' => true],
        ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
        ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
        ['label' => 'Lead Hub', 'href' => '../dashboard.php'],
    ], '24/7 Sales Partner') ?>

    <div class="app-content">
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">24/7 Sales Partner</p>
            <h1><?= $business ? e($business['business_name']) : '247SP onboarding' ?></h1>
            <p class="muted">Collect the website setup details needed for the build handoff.</p>
        </section>

        <?php if ($completed): ?>
            <?= ui_alert('247SP onboarding is complete and ready for build.', 'success') ?>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before starting 24/7 Sales Partner onboarding.</p>
            </section>
        <?php elseif ($accessDenied): ?>
            <section class="empty-state">
                <h2>Access denied</h2>
                <p>24/7 Sales Partner is not active for this business.</p>
                <div class="button-row">
                    <?= ui_button('Activate modules', $accountsBaseUrl . '/business-create.php?step=modules&business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                </div>
            </section>
        <?php else: ?>
            <section class="metrics-grid" aria-label="247SP status summary">
                <article>
                    <span>Website Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['website_status'])) ?></strong>
                </article>
                <article>
                    <span>Domain Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['domain_status'])) ?></strong>
                </article>
                <article>
                    <span>Email Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['email_status'])) ?></strong>
                </article>
            </section>

            <section class="business-switcher">
                <h2>Onboarding Progress</h2>
                <div class="summary-list">
                    <div><dt>Setup Status</dt><dd><?= e(sp247_status_label((string) $summary['setup_status'])) ?></dd></div>
                    <div><dt>Current Step</dt><dd><?= e(sp247_status_label((string) $summary['current_step'])) ?></dd></div>
                    <div><dt>Completed At</dt><dd><?= e($summary['completed_at'] ?: 'Not complete') ?></dd></div>
                </div>
                <div class="button-row">
                    <?= ui_button($summary['setup_status'] === 'complete' ? 'Review onboarding' : 'Continue onboarding', $summary['setup_status'] === 'complete'
                        ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks)
                        : 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=' . urlencode((string) $summary['current_step'])) ?>
                    <?= ui_button('Lead Hub included', '../dashboard.php', 'secondary') ?>
                </div>
            </section>

            <section class="business-switcher">
                <h2>Lead Hub Included</h2>
                <p class="muted">24/7 Sales Partner includes Lead Hub access. Website leads will flow into Lead Hub in a future sprint after site generation and lead capture are built.</p>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
