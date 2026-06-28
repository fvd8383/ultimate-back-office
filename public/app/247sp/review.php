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
$bundle = [
    'onboarding' => null,
    'configuration' => null,
    'content' => null,
    'service_pages' => [],
    'domain' => null,
    'email' => null,
];
$categoryName = 'Not selected';
$errors = [];
$notice = '';
$accessDenied = false;

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId > 0 ? $requestedBusinessId : null, (int) $user['id']);

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);

        if (!$accessDenied && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_onboarding'])) {
            TwentyFourSevenSalesPartner::completeOnboarding($businessId, (int) $user['id']);
            header('Location: dashboard.php?business_id=' . $businessId . '&completed=1');
            exit;
        }

        if (!$accessDenied) {
            $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
            $categoryId = (int) ($bundle['configuration']['primary_category_id'] ?? 0);

            if ($categoryId > 0) {
                foreach (BusinessFoundation::categories() as $category) {
                    if ((int) $category['id'] === $categoryId) {
                        $categoryName = (string) $category['name'];
                        break;
                    }
                }
            }

            $errors = TwentyFourSevenSalesPartner::readinessErrors($businessId);
        }
    }
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
} catch (Throwable $exception) {
    $errors[] = '247SP onboarding review could not be loaded. Check the database setup and try again.';
}

function sp247_review_href(string $step, int $businessId): string
{
    return 'onboarding.php?business_id=' . urlencode((string) $businessId) . '&step=' . urlencode($step);
}

function sp247_yes_no($value): string
{
    return (int) $value === 1 ? 'Yes' : 'No';
}

function sp247_domain_type(?array $domain): string
{
    if ($domain === null) {
        return 'Not selected';
    }

    return ($domain['selection_type'] ?? '') === 'purchase' ? 'Purchase Through 247SP' : 'Bring Existing Domain';
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$sp247NavItems = [
    ['label' => '247SP Dashboard', 'href' => $businessIdForLinks > 0 ? 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks) : 'dashboard.php'],
    ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
    ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php', 'current' => true],
    ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php'],
];
if ($businessIdForLinks > 0 && !$accessDenied) {
    array_splice($sp247NavItems, 4, 0, [[
        'label' => 'Website Manager',
        'href' => 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks),
    ]]);
}
$onboarding = $bundle['onboarding'];
$configuration = $bundle['configuration'];
$content = $bundle['content'];
$domain = $bundle['domain'];
$email = $bundle['email'];

$pageTitle = 'Review 247SP Onboarding - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
?>
<?php application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user, 'business' => $business]); ?>
        <?= application_module_nav($sp247NavItems, '24/7 Sales Partner navigation') ?>

        <section class="hero-panel product-hero product-hero--247sp">
            <img class="product-hero__logo" src="../assets/img/247sp-logo.svg" alt="24/7 Sales Partner">
            <p class="eyebrow">247SP review</p>
            <h1><?= $business ? e($business['business_name']) : 'Review onboarding' ?></h1>
            <p class="muted">Confirm the collected setup information before marking onboarding complete.</p>
        </section>

        <?php if ($notice !== ''): ?>
            <?= ui_alert($notice, 'success') ?>
        <?php endif; ?>

        <?php if ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before reviewing 24/7 Sales Partner onboarding.</p>
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
            <?php foreach ($errors as $error): ?>
                <?= ui_alert($error, 'error') ?>
            <?php endforeach; ?>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Business Information</h2>
                    <?= ui_button('Edit', sp247_review_href('business_information', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Business</dt><dd><?= e($business['business_name']) ?></dd></div>
                    <div><dt>Contact</dt><dd><?= e($onboarding['contact_name'] ?? 'Not provided') ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($business['email']) ?></dd></div>
                    <div><dt>Phone</dt><dd><?= e($business['phone']) ?></dd></div>
                </dl>
            </section>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Service Area</h2>
                    <?= ui_button('Edit', sp247_review_href('service_area', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Address</dt><dd><?= e($configuration['service_area_address'] ?? $business['address_line_1']) ?></dd></div>
                    <div><dt>City</dt><dd><?= e($configuration['service_area_city'] ?? $business['city']) ?></dd></div>
                    <div><dt>State</dt><dd><?= e($configuration['service_area_state'] ?? $business['state']) ?></dd></div>
                    <div><dt>ZIP</dt><dd><?= e($configuration['service_area_postal_code'] ?? $business['postal_code']) ?></dd></div>
                    <div><dt>Service Area Business</dt><dd><?= e(sp247_yes_no($configuration['service_area_business'] ?? 0)) ?></dd></div>
                </dl>
            </section>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Services</h2>
                    <?= ui_button('Edit', sp247_review_href('services', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Primary Category</dt><dd><?= e($categoryName) ?></dd></div>
                </dl>
                <div class="service-page-grid">
                    <?php foreach ($bundle['service_pages'] as $servicePage): ?>
                        <article class="mini-card">
                            <h3><?= e($servicePage['service_name']) ?></h3>
                            <p class="muted"><?= e($servicePage['short_description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Website Content</h2>
                    <?= ui_button('Edit', sp247_review_href('website_content', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Description</dt><dd><?= e($content['business_description'] ?? 'Not provided') ?></dd></div>
                    <div><dt>About</dt><dd><?= e($content['about_company'] ?? 'Not provided') ?></dd></div>
                    <div><dt>Years In Business</dt><dd><?= e($content['years_in_business'] ?? 'Not provided') ?></dd></div>
                    <div><dt>Financing Available</dt><dd><?= e(sp247_yes_no($content['financing_available'] ?? 0)) ?></dd></div>
                    <div><dt>Special Offer</dt><dd><?= e($content['special_offer'] ?? 'None') ?></dd></div>
                </dl>
            </section>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Domain Selection</h2>
                    <?= ui_button('Edit', sp247_review_href('domain_selection', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Type</dt><dd><?= e(sp247_domain_type($domain)) ?></dd></div>
                    <div><dt>Domain</dt><dd><?= e($domain['domain_name'] ?? 'Not selected') ?></dd></div>
                    <div><dt>Status</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($domain['status'] ?? 'not_selected')))) ?></dd></div>
                </dl>
            </section>

            <section class="review-section">
                <div class="review-section__header">
                    <h2>Email Selection</h2>
                    <?= ui_button('Edit', sp247_review_href('email_selection', $businessIdForLinks), 'secondary', ['class' => 'ubo-button--compact']) ?>
                </div>
                <dl class="summary-list">
                    <div><dt>Primary Mailbox</dt><dd><?= e($email['primary_mailbox_name'] ?? 'Not selected') ?></dd></div>
                    <div><dt>Status</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($email['status'] ?? 'not_selected')))) ?></dd></div>
                </dl>
            </section>

            <section class="business-switcher">
                <h2>Submit</h2>
                <p class="muted">Submitting marks setup_status complete and makes the website status Ready For Build. No website, domain, DNS, email, billing, analytics, or AI provisioning runs from this action.</p>
                <form method="post" action="review.php" class="button-row">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
                    <?= ui_button('Back to email step', sp247_review_href('email_selection', $businessIdForLinks), 'secondary') ?>
                    <?= ui_button('Mark onboarding complete', '', 'primary', ['name' => 'complete_onboarding', 'value' => '1', 'disabled' => count($errors) > 0]) ?>
                </form>
            </section>
        <?php endif; ?>
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
