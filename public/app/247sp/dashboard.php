<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/BillingFoundation.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerator.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$user = null;
$business = null;
$summary = [];
$website = null;
$billing = null;
$loadError = '';
$actionError = '';
$accessDenied = false;
$completed = isset($_GET['completed']);
$generated = isset($_GET['generated']);
$regenerated = isset($_GET['regenerated']);

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
    $requestedBusinessId = $requestedBusinessId > 0 ? $requestedBusinessId : null;
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId, (int) $user['id']);

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);

        if (!$accessDenied && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['generate_website'])) {
                SiteGenerator::generateWebsite($businessId, (int) $user['id']);
                header('Location: dashboard.php?business_id=' . $businessId . '&generated=1');
                exit;
            }

            if (isset($_POST['regenerate_website'])) {
                SiteGenerator::regenerateWebsite($businessId, (int) $user['id']);
                header('Location: dashboard.php?business_id=' . $businessId . '&regenerated=1');
                exit;
            }
        }

        if (!$accessDenied) {
            $summary = TwentyFourSevenSalesPartner::dashboardSummary($businessId);
            $website = SiteGenerator::websiteForBusiness($businessId);
            $billing = BillingFoundation::subscriptionForBusiness($businessId);

            if ($website !== null) {
                $summary['website_status'] = (string) $website['status'];
            }
        }
    }
} catch (InvalidArgumentException $exception) {
    $actionError = $exception->getMessage();

    if ($business !== null && !$accessDenied) {
        $summary = TwentyFourSevenSalesPartner::dashboardSummary((int) $business['id']);
        $website = SiteGenerator::websiteForBusiness((int) $business['id']);
        $billing = BillingFoundation::subscriptionForBusiness((int) $business['id']);

        if ($website !== null) {
            $summary['website_status'] = (string) $website['status'];
        }
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
        'generated' => 'Generated',
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
$sp247NavItems = [
    ['label' => '247SP Dashboard', 'href' => 'dashboard.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : ''), 'current' => true],
    ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
    ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
    ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php'],
    ['label' => 'Lead Hub', 'href' => '../dashboard.php'],
];
if ($businessIdForLinks > 0 && !$accessDenied) {
    array_splice($sp247NavItems, 4, 0, [[
        'label' => 'Website Manager',
        'href' => 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks),
    ]]);
}
$pageTitle = '24/7 Sales Partner - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
?>
<section class="app-layout">
    <?= ui_sidebar('24/7 Sales Partner', $sp247NavItems, '24/7 Sales Partner') ?>

    <div class="app-content">
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">24/7 Sales Partner</p>
            <h1><?= $business ? e($business['business_name']) : '247SP onboarding' ?></h1>
            <p class="muted">Collect the website setup details needed for the build handoff.</p>
        </section>

        <?php if ($completed): ?>
            <?= ui_alert('247SP onboarding is complete and ready for build.', 'success') ?>
        <?php endif; ?>
        <?php if ($generated): ?>
            <?= ui_alert('Website generated. Preview is available inside 247SP.', 'success') ?>
        <?php endif; ?>
        <?php if ($regenerated): ?>
            <?= ui_alert('Website regenerated from the latest onboarding data.', 'success') ?>
        <?php endif; ?>
        <?php if ($actionError !== ''): ?>
            <?= ui_alert($actionError, 'error') ?>
        <?php endif; ?>
        <?php if ($billing !== null && in_array((string) $billing['status'], ['pending_payment', 'past_due'], true)): ?>
            <?= ui_alert('Billing status is ' . sp247_status_label((string) $billing['status']) . '. 24/7 Sales Partner remains available while billing is handled manually.', 'warning') ?>
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
                    <div><dt>Template</dt><dd><?= e($website['template_name'] ?? 'Not assigned') ?></dd></div>
                    <div><dt>Generation Date</dt><dd><?= e($website['generated_at'] ?? 'Not generated') ?></dd></div>
                    <div><dt>Billing Status</dt><dd><?= e($billing ? sp247_status_label((string) $billing['status']) : 'No Subscription') ?></dd></div>
                </div>
                <div class="button-row">
                    <?= ui_button($summary['setup_status'] === 'complete' ? 'Review onboarding' : 'Continue onboarding', $summary['setup_status'] === 'complete'
                        ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks)
                        : 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=' . urlencode((string) $summary['current_step'])) ?>
                    <?= ui_button('Lead Hub included', '../dashboard.php', 'secondary') ?>
                </div>
            </section>

            <section class="business-switcher">
                <h2>Website Generation</h2>
                <p class="muted">Generate a six-page private preview from completed onboarding data. This does not register domains, update DNS, provision email, add analytics, or generate AI content.</p>
                <form method="post" action="dashboard.php" class="button-row">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
                    <?php if ($website === null): ?>
                        <?= ui_button('Generate Website', '', 'primary', ['name' => 'generate_website', 'value' => '1', 'disabled' => $summary['setup_status'] !== 'complete']) ?>
                    <?php else: ?>
                        <?= ui_button('Regenerate Website', '', 'primary', ['name' => 'regenerate_website', 'value' => '1']) ?>
                        <?= ui_button('Preview Website', 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Website Manager', 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                    <?php endif; ?>
                </form>
            </section>

            <section class="business-switcher">
                <h2>Lead Hub Included</h2>
                <p class="muted">24/7 Sales Partner includes Lead Hub access. Website leads will flow into Lead Hub in a future sprint after site generation and lead capture are built.</p>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
