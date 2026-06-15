<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$allowedSteps = TwentyFourSevenSalesPartner::STEPS;
$step = (string) ($_POST['step'] ?? $_GET['step'] ?? 'business_information');
if (!in_array($step, $allowedSteps, true)) {
    $step = 'business_information';
}

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
$categories = [];
$errors = [];
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
        $categories = BusinessFoundation::categories();

        if (!$accessDenied && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($step === 'business_information') {
                TwentyFourSevenSalesPartner::saveBusinessInformation($businessId, (int) $user['id'], $_POST);
                header('Location: onboarding.php?business_id=' . $businessId . '&step=service_area');
                exit;
            }

            if ($step === 'service_area') {
                TwentyFourSevenSalesPartner::saveServiceArea($businessId, (int) $user['id'], $_POST);
                header('Location: onboarding.php?business_id=' . $businessId . '&step=services');
                exit;
            }

            if ($step === 'services') {
                TwentyFourSevenSalesPartner::saveServices($businessId, (int) $user['id'], $_POST);
                header('Location: onboarding.php?business_id=' . $businessId . '&step=website_content');
                exit;
            }

            if ($step === 'website_content') {
                TwentyFourSevenSalesPartner::saveWebsiteContent($businessId, (int) $user['id'], $_POST);
                header('Location: onboarding.php?business_id=' . $businessId . '&step=domain_selection');
                exit;
            }

            if ($step === 'domain_selection') {
                TwentyFourSevenSalesPartner::saveDomainSelection($businessId, (int) $user['id'], $_POST);
                header('Location: onboarding.php?business_id=' . $businessId . '&step=email_selection');
                exit;
            }

            if ($step === 'email_selection') {
                TwentyFourSevenSalesPartner::saveEmailSelection($businessId, (int) $user['id'], $_POST);
                header('Location: review.php?business_id=' . $businessId);
                exit;
            }
        }

        if (!$accessDenied) {
            $business = BusinessFoundation::businessForUser($businessId, (int) $user['id']) ?? $business;
            $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
        }
    }
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    if ($business !== null && !$accessDenied) {
        $bundle = TwentyFourSevenSalesPartner::bundle((int) $business['id']);
    }
} catch (Throwable $exception) {
    $errors[] = '247SP onboarding could not be loaded or saved. Check the database setup and try again.';
}

function sp247_form_value(?array $source, string $key, string $default = ''): string
{
    if (isset($_POST[$key])) {
        return (string) $_POST[$key];
    }

    return (string) ($source[$key] ?? $default);
}

function sp247_service_value(array $servicePages, int $serviceNumber, string $key): string
{
    $postKey = 'service_' . $serviceNumber . '_' . ($key === 'service_name' ? 'name' : 'description');
    if (isset($_POST[$postKey])) {
        return (string) $_POST[$postKey];
    }

    foreach ($servicePages as $servicePage) {
        if ((int) $servicePage['service_number'] === $serviceNumber) {
            return (string) ($servicePage[$key] ?? '');
        }
    }

    return '';
}

function sp247_step_href(string $step, int $businessId): string
{
    return 'onboarding.php?business_id=' . urlencode((string) $businessId) . '&step=' . urlencode($step);
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$configuration = $bundle['configuration'];
$content = $bundle['content'];
$domain = $bundle['domain'];
$email = $bundle['email'];
$onboarding = $bundle['onboarding'];

$pageTitle = '247SP Onboarding - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
?>
<section class="app-layout">
    <?= ui_sidebar('24/7 Sales Partner', [
        ['label' => '247SP Dashboard', 'href' => $businessIdForLinks > 0 ? 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks) : 'dashboard.php'],
        ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php', 'current' => true],
        ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
        ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php'],
        ['label' => 'Lead Hub', 'href' => '../dashboard.php'],
    ], '24/7 Sales Partner') ?>

    <div class="app-content">
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">247SP onboarding</p>
            <h1><?= $business ? e($business['business_name']) : 'Start onboarding' ?></h1>
            <p class="muted">Save each step as you collect the website build inputs.</p>
        </section>

        <?php foreach ($errors as $error): ?>
            <?= ui_alert($error, 'error') ?>
        <?php endforeach; ?>

        <?php if ($business === null): ?>
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
            <nav class="step-nav step-nav--247sp" aria-label="247SP onboarding steps">
                <a class="<?= $step === 'business_information' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('business_information', $businessIdForLinks)) ?>">1. Business</a>
                <a class="<?= $step === 'service_area' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('service_area', $businessIdForLinks)) ?>">2. Area</a>
                <a class="<?= $step === 'services' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('services', $businessIdForLinks)) ?>">3. Services</a>
                <a class="<?= $step === 'website_content' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('website_content', $businessIdForLinks)) ?>">4. Content</a>
                <a class="<?= $step === 'domain_selection' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('domain_selection', $businessIdForLinks)) ?>">5. Domain</a>
                <a class="<?= $step === 'email_selection' ? 'is-active' : '' ?>" href="<?= e(sp247_step_href('email_selection', $businessIdForLinks)) ?>">6. Email</a>
            </nav>

            <?php if (($onboarding['setup_status'] ?? '') === 'complete'): ?>
                <?= ui_alert('Onboarding is complete. Editing a step will save the new selection but the record stays marked complete until submitted again from review.', 'info') ?>
            <?php endif; ?>

            <?php if ($step === 'business_information'): ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="business_information">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <div class="form-grid">
                        <label>Business Name
                            <input name="business_name" required value="<?= e(sp247_form_value($business, 'business_name')) ?>">
                        </label>
                        <label>Contact Name
                            <input name="contact_name" required value="<?= e(sp247_form_value($onboarding, 'contact_name', trim((string) $user['first_name'] . ' ' . (string) $user['last_name']))) ?>">
                        </label>
                        <label>Email
                            <input name="email" type="email" required value="<?= e(sp247_form_value($business, 'email')) ?>">
                        </label>
                        <label>Phone
                            <input name="phone" required value="<?= e(sp247_form_value($business, 'phone')) ?>">
                        </label>
                    </div>

                    <?= ui_button('Save and continue') ?>
                </form>
            <?php elseif ($step === 'service_area'): ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="service_area">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <div class="form-grid">
                        <label>Address
                            <input name="address_line_1" required value="<?= e(sp247_form_value($configuration, 'service_area_address', (string) $business['address_line_1'])) ?>">
                        </label>
                        <label>City
                            <input name="city" required value="<?= e(sp247_form_value($configuration, 'service_area_city', (string) $business['city'])) ?>">
                        </label>
                        <label>State
                            <input name="state" required value="<?= e(sp247_form_value($configuration, 'service_area_state', (string) $business['state'])) ?>">
                        </label>
                        <label>ZIP
                            <input name="postal_code" required value="<?= e(sp247_form_value($configuration, 'service_area_postal_code', (string) $business['postal_code'])) ?>">
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="service_area_business" value="1" <?= (int) sp247_form_value($configuration, 'service_area_business', (int) !$business['is_public_physical_location']) === 1 ? 'checked' : '' ?>>
                            Service Area Business
                        </label>
                    </div>

                    <div class="button-row">
                        <?= ui_button('Back', sp247_step_href('business_information', $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Save and continue') ?>
                    </div>
                </form>
            <?php elseif ($step === 'services'): ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="services">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <label>Primary Service Category
                        <select name="primary_category_id" required>
                            <option value="">Select category</option>
                            <?php $selectedCategoryId = (int) ($_POST['primary_category_id'] ?? ($configuration['primary_category_id'] ?? $business['primary_category_id'] ?? 0)); ?>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['id']) ?>" <?= $selectedCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="service-page-grid">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <fieldset>
                                <legend>Service <?= e($i) ?></legend>
                                <label>Service Name
                                    <input name="service_<?= e($i) ?>_name" required value="<?= e(sp247_service_value($bundle['service_pages'], $i, 'service_name')) ?>">
                                </label>
                                <label>Short Description
                                    <textarea name="service_<?= e($i) ?>_description" required rows="4"><?= e(sp247_service_value($bundle['service_pages'], $i, 'short_description')) ?></textarea>
                                </label>
                            </fieldset>
                        <?php endfor; ?>
                    </div>

                    <div class="button-row">
                        <?= ui_button('Back', sp247_step_href('service_area', $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Save and continue') ?>
                    </div>
                </form>
            <?php elseif ($step === 'website_content'): ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="website_content">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <label>Business Description
                        <textarea name="business_description" required rows="4"><?= e(sp247_form_value($content, 'business_description')) ?></textarea>
                    </label>
                    <label>About Company
                        <textarea name="about_company" required rows="4"><?= e(sp247_form_value($content, 'about_company')) ?></textarea>
                    </label>
                    <div class="form-grid">
                        <label>Years In Business
                            <input name="years_in_business" inputmode="numeric" required value="<?= e(sp247_form_value($content, 'years_in_business')) ?>">
                        </label>
                        <label>Special Offer
                            <input name="special_offer" value="<?= e(sp247_form_value($content, 'special_offer')) ?>">
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="financing_available" value="1" <?= (int) sp247_form_value($content, 'financing_available') === 1 ? 'checked' : '' ?>>
                            Financing Available
                        </label>
                    </div>

                    <div class="button-row">
                        <?= ui_button('Back', sp247_step_href('services', $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Save and continue') ?>
                    </div>
                </form>
            <?php elseif ($step === 'domain_selection'): ?>
                <?php $domainType = (string) ($_POST['domain_selection_type'] ?? ($domain['selection_type'] ?? 'existing')); ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="domain_selection">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <fieldset class="package-options">
                        <legend>Domain Selection</legend>
                        <label class="module-option">
                            <input type="radio" name="domain_selection_type" value="existing" <?= $domainType !== 'purchase' ? 'checked' : '' ?>>
                            <strong>Bring Existing Domain</strong>
                            <span>Customer retains ownership. No domain registration is performed here.</span>
                        </label>
                        <label class="module-option">
                            <input type="radio" name="domain_selection_type" value="purchase" <?= $domainType === 'purchase' ? 'checked' : '' ?>>
                            <strong>Purchase Through 247SP</strong>
                            <span>Stores the desired domain as pending for a future sprint.</span>
                        </label>
                    </fieldset>

                    <div class="form-grid">
                        <label data-domain-field="existing">Domain Name
                            <input name="existing_domain_name" value="<?= e($domainType === 'existing' ? sp247_form_value($domain, 'domain_name') : '') ?>" placeholder="example.com">
                        </label>
                        <label data-domain-field="purchase">Desired Domain Name
                            <input name="desired_domain_name" value="<?= e($domainType === 'purchase' ? sp247_form_value($domain, 'domain_name') : '') ?>" placeholder="example.com">
                        </label>
                    </div>

                    <div class="button-row">
                        <?= ui_button('Back', sp247_step_href('website_content', $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Save and continue') ?>
                    </div>
                </form>
            <?php else: ?>
                <form method="post" action="onboarding.php" class="dashboard-card form-stack">
                    <input type="hidden" name="step" value="email_selection">
                    <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                    <label>Primary Mailbox Name
                        <input name="primary_mailbox_name" required value="<?= e(sp247_form_value($email, 'primary_mailbox_name', 'info')) ?>" placeholder="info">
                    </label>
                    <p class="muted">This stores the mailbox request only. Email is not provisioned in Sprint 3.</p>

                    <div class="button-row">
                        <?= ui_button('Back', sp247_step_href('domain_selection', $businessIdForLinks), 'secondary') ?>
                        <?= ui_button('Save and review') ?>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('input[name="domain_selection_type"]').forEach(function (input) {
    input.addEventListener('change', updateDomainFields);
});

function updateDomainFields() {
    var selected = document.querySelector('input[name="domain_selection_type"]:checked');
    var type = selected ? selected.value : 'existing';

    document.querySelectorAll('[data-domain-field]').forEach(function (field) {
        var isActive = field.getAttribute('data-domain-field') === type;
        field.hidden = !isActive;
        field.querySelectorAll('input').forEach(function (input) {
            input.disabled = !isActive;
        });
    });
}

updateDomainFields();
</script>

<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
