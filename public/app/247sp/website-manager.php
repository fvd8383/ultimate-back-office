<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerator.php';
require_once __DIR__ . '/../../../private/classes/WebsiteManager.php';

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
$website = null;
$pages = [];
$branding = [];
$serviceImages = [];
$overrides = [];
$loadError = '';
$actionError = '';
$accessDenied = false;
$saved = isset($_GET['saved']);

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

        if (!$accessDenied && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? 'save');

            if ($action === 'save_regenerate') {
                WebsiteManager::saveAndRegenerate($businessId, (int) $user['id'], $_POST, $_FILES);
                header('Location: site-preview.php?business_id=' . urlencode((string) $businessId) . '&regenerated=1');
                exit;
            }

            WebsiteManager::saveWebsiteManager($businessId, (int) $user['id'], $_POST, $_FILES);
            header('Location: website-manager.php?business_id=' . urlencode((string) $businessId) . '&saved=1');
            exit;
        }

        if (!$accessDenied) {
            $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
            $website = SiteGenerator::websiteForBusiness($businessId);
            $pages = $website !== null ? SiteGenerator::pagesForWebsite((int) $website['id']) : [];
            $branding = WebsiteManager::brandingForBusiness($businessId);
            $serviceImages = WebsiteManager::serviceImagesForBusiness($businessId);
            $overrides = WebsiteManager::contentOverridesForBusiness($businessId);
        }
    }
} catch (InvalidArgumentException $exception) {
    $actionError = $exception->getMessage();

    if ($business !== null && !$accessDenied) {
        $businessId = (int) $business['id'];
        $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
        $website = SiteGenerator::websiteForBusiness($businessId);
        $pages = $website !== null ? SiteGenerator::pagesForWebsite((int) $website['id']) : [];
        $branding = WebsiteManager::brandingForBusiness($businessId);
        $serviceImages = WebsiteManager::serviceImagesForBusiness($businessId);
        $overrides = WebsiteManager::contentOverridesForBusiness($businessId);
    }
} catch (Throwable $exception) {
    $loadError = '247SP Website Manager could not be loaded. Check the database setup and try again.';
}

function sp247_manager_nav(int $businessId, string $current, bool $showManager): array
{
    $suffix = $businessId > 0 ? '?business_id=' . urlencode((string) $businessId) : '';

    $items = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php' . $suffix, 'current' => $current === 'dashboard'],
        ['label' => 'Onboarding', 'href' => 'onboarding.php' . $suffix, 'current' => $current === 'onboarding'],
        ['label' => 'Review', 'href' => 'review.php' . $suffix, 'current' => $current === 'review'],
        ['label' => 'Preview', 'href' => 'site-preview.php' . $suffix, 'current' => $current === 'preview'],
    ];

    if ($showManager) {
        array_splice($items, 4, 0, [[
            'label' => 'Website Manager',
            'href' => 'website-manager.php' . $suffix,
            'current' => $current === 'manager',
        ]]);
    }

    return $items;
}

function sp247_manager_content(array $pages, string $pageType, ?int $serviceNumber = null): array
{
    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') !== $pageType) {
            continue;
        }

        $decoded = json_decode((string) $page['content_json'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($pageType !== 'service' || (int) ($decoded['service_number'] ?? 0) === $serviceNumber) {
            return $decoded;
        }
    }

    return [];
}

function sp247_manager_value(array $overrides, string $pageKey, string $fieldKey, string $fallback): string
{
    $value = trim((string) ($overrides[$pageKey][$fieldKey] ?? ''));

    return $value !== '' ? $value : $fallback;
}

function sp247_manager_image_preview(?string $path, string $label): string
{
    if ($path === null || $path === '') {
        return '<span class="website-manager-empty">No ' . e($label) . ' uploaded</span>';
    }

    return '<img src="' . e($path) . '" alt="' . e($label) . ' preview">';
}

function sp247_manager_file_preview(?string $path, string $label): string
{
    if ($path === null || $path === '') {
        return '<span class="website-manager-empty">No ' . e($label) . ' uploaded</span>';
    }

    return '<a href="' . e($path) . '" target="_blank" rel="noopener">View uploaded ' . e($label) . '</a>';
}

function sp247_manager_primary_cta_labels(): array
{
    return ['Call Now', 'Request Service', 'Book Appointment', 'Instant Quote', 'Get Estimate', 'Request Inspection', 'Apply Now', 'Reserve Spot'];
}

function sp247_manager_secondary_cta_labels(): array
{
    return ['Free Estimate', 'Contact Us', 'View Pricing', 'Learn More'];
}

function sp247_manager_cta_behaviors(): array
{
    return [
        'call_now' => 'Call',
        'contact_form' => 'Contact Form',
        'view_pricing' => 'View Pricing',
    ];
}

function sp247_manager_cta_type(array $overrides, string $slot, string $fallback): string
{
    $value = (string) ($overrides['home'][$slot . '_cta_type'] ?? $fallback);

    if (in_array($value, ['call_now', 'contact_form', 'view_pricing'], true)) {
        return $value;
    }

    if (in_array($value, ['schedule_service', 'request_service', 'instant_quote'], true)) {
        return 'contact_form';
    }

    return in_array($fallback, ['call_now', 'contact_form', 'view_pricing'], true) ? $fallback : 'contact_form';
}

function sp247_manager_stat(array $overrides, array $homeContent, int $number, string $fallbackValue, string $fallbackLabel): array
{
    $generatedStats = $homeContent['stats'] ?? [];
    if (!is_array($generatedStats)) {
        $generatedStats = [];
    }

    $index = $number - 1;

    return [
        'value' => sp247_manager_value($overrides, 'home', 'stat_' . $number . '_value', (string) ($generatedStats[$index]['value'] ?? $fallbackValue)),
        'label' => sp247_manager_value($overrides, 'home', 'stat_' . $number . '_label', (string) ($generatedStats[$index]['label'] ?? $fallbackLabel)),
    ];
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$businessName = (string) ($business['business_name'] ?? '');
$businessContent = $bundle['content'] ?? [];
$homeContent = sp247_manager_content($pages, 'home');
$aboutContent = sp247_manager_content($pages, 'about');
$contactContent = sp247_manager_content($pages, 'contact');

$homeHeadline = sp247_manager_value($overrides, 'home', 'headline', (string) ($homeContent['headline'] ?? $businessName));
$homeSubheadline = sp247_manager_value($overrides, 'home', 'subheadline', (string) ($homeContent['subheadline'] ?? $homeContent['business_description'] ?? $businessContent['business_description'] ?? ''));
$homeCallToAction = sp247_manager_value($overrides, 'home', 'call_to_action', (string) ($homeContent['call_to_action'] ?? ('Call ' . $businessName . ' today to request service.')));
$primaryCtaFallback = (string) ($homeContent['primary_cta']['label'] ?? '');
if ($primaryCtaFallback === '') {
    $primaryCtaFallback = $homeCallToAction !== '' ? $homeCallToAction : 'Call Now';
}
$primaryCtaLabel = sp247_manager_value($overrides, 'home', 'primary_cta_label', $primaryCtaFallback);
$primaryCtaType = sp247_manager_cta_type($overrides, 'primary', (string) ($homeContent['primary_cta']['type'] ?? 'call_now'));
$secondaryCtaLabel = sp247_manager_value($overrides, 'home', 'secondary_cta_label', (string) ($homeContent['secondary_cta']['label'] ?? 'Contact Us'));
$secondaryCtaType = sp247_manager_cta_type($overrides, 'secondary', (string) ($homeContent['secondary_cta']['type'] ?? 'contact_form'));
$pricingListPath = sp247_manager_value($overrides, 'home', 'pricing_list_path', (string) ($homeContent['pricing_list_path'] ?? ''));
$homeStats = [
    1 => sp247_manager_stat($overrides, $homeContent, 1, 'Local', 'Service'),
    2 => sp247_manager_stat($overrides, $homeContent, 2, (string) count($bundle['service_pages']), 'Core services available'),
    3 => sp247_manager_stat($overrides, $homeContent, 3, 'Clear', 'Communication from request to service'),
];
$aboutHeading = sp247_manager_value($overrides, 'about', 'heading', (string) ($aboutContent['about_heading'] ?? ('About ' . $businessName)));
$aboutDescription = sp247_manager_value($overrides, 'about', 'description', (string) ($aboutContent['company_description'] ?? $businessContent['about_company'] ?? ''));
$contactHeading = sp247_manager_value($overrides, 'contact', 'heading', (string) ($contactContent['contact_heading'] ?? ('Contact ' . $businessName)));
$contactDescription = sp247_manager_value($overrides, 'contact', 'description', (string) ($contactContent['contact_description'] ?? 'Tell us what you need and we will help you take the next step.'));

$pageTitle = '247SP Website Manager - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
?>
<?php application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user, 'business' => $business, 'secondary_nav' => sp247_manager_nav($businessIdForLinks, 'manager', $businessIdForLinks > 0 && !$accessDenied)]); ?>
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">Website Manager</p>
            <h1><?= $business ? e($business['business_name']) : '247SP website manager' ?></h1>
            <p class="muted">Manage branding, images, and editable content for the private website preview.</p>
        </section>

        <?php if ($saved): ?>
            <?= ui_alert('Website manager settings saved.', 'success') ?>
        <?php endif; ?>
        <?php if ($actionError !== ''): ?>
            <?= ui_alert($actionError, 'error') ?>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before managing a 24/7 Sales Partner website.</p>
            </section>
        <?php elseif ($accessDenied): ?>
            <section class="empty-state">
                <h2>Access denied</h2>
                <p>24/7 Sales Partner is not active for this business.</p>
            </section>
        <?php else: ?>
            <form method="post" action="website-manager.php" enctype="multipart/form-data" class="website-manager-form">
                <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">

                <section class="business-switcher website-manager-section">
                    <h2>Branding</h2>
                    <div class="form-grid">
                        <label>Primary Brand Color
                            <input type="text" name="primary_color" value="<?= e($branding['primary_color'] ?? WebsiteManager::DEFAULT_PRIMARY_COLOR) ?>" maxlength="7" required>
                        </label>
                        <label>Secondary Brand Color
                            <input type="text" name="secondary_color" value="<?= e($branding['secondary_color'] ?? '') ?>" maxlength="7" placeholder="#D1892A">
                        </label>
                    </div>
                    <div class="form-grid website-manager-upload-grid">
                        <label>Logo
                            <span class="website-manager-preview"><?= sp247_manager_image_preview($branding['logo_path'] ?? null, 'logo') ?></span>
                            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml">
                        </label>
                        <label>Hero Image
                            <span class="website-manager-preview"><?= sp247_manager_image_preview($branding['hero_image_path'] ?? null, 'hero image') ?></span>
                            <input type="file" name="hero_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                        </label>
                        <label>About Image
                            <span class="website-manager-preview"><?= sp247_manager_image_preview($branding['about_image_path'] ?? null, 'about image') ?></span>
                            <input type="file" name="about_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                        </label>
                    </div>
                </section>

                <section class="business-switcher website-manager-section">
                    <h2>Homepage</h2>
                    <label>Headline
                        <input type="text" name="home_headline" value="<?= e($homeHeadline) ?>" required>
                    </label>
                    <label>Subheadline
                        <textarea name="home_subheadline" rows="4" required><?= e($homeSubheadline) ?></textarea>
                    </label>
                    <label>Primary CTA Label
                        <select name="home_call_to_action" required>
                            <?php foreach (sp247_manager_primary_cta_labels() as $label): ?>
                                <option value="<?= e($label) ?>" <?= $primaryCtaLabel === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                            <?php if ($primaryCtaLabel !== '' && !in_array($primaryCtaLabel, sp247_manager_primary_cta_labels(), true)): ?>
                                <option value="<?= e($primaryCtaLabel) ?>" selected><?= e($primaryCtaLabel) ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                    <div class="form-grid">
                        <label>Primary CTA Behavior
                            <select name="primary_cta_type">
                                <?php foreach (sp247_manager_cta_behaviors() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $primaryCtaType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Secondary CTA Label
                            <select name="secondary_cta_label">
                                <?php foreach (sp247_manager_secondary_cta_labels() as $label): ?>
                                    <option value="<?= e($label) ?>" <?= $secondaryCtaLabel === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                                <?php if ($secondaryCtaLabel !== '' && !in_array($secondaryCtaLabel, sp247_manager_secondary_cta_labels(), true)): ?>
                                    <option value="<?= e($secondaryCtaLabel) ?>" selected><?= e($secondaryCtaLabel) ?></option>
                                <?php endif; ?>
                            </select>
                        </label>
                        <label>Secondary CTA Behavior
                            <select name="secondary_cta_type">
                                <?php foreach (sp247_manager_cta_behaviors() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $secondaryCtaType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>Pricing List
                        <span class="website-manager-preview"><?= sp247_manager_file_preview($pricingListPath, 'pricing list') ?></span>
                        <input type="file" name="pricing_list" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp">
                    </label>
                    <div class="form-grid">
                        <?php foreach ($homeStats as $statNumber => $stat): ?>
                            <fieldset>
                                <legend><?= e('Homepage Stat ' . $statNumber) ?></legend>
                                <label>Value
                                    <input type="text" name="stat_<?= e($statNumber) ?>_value" value="<?= e($stat['value']) ?>">
                                </label>
                                <label>Label
                                    <input type="text" name="stat_<?= e($statNumber) ?>_label" value="<?= e($stat['label']) ?>">
                                </label>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="business-switcher website-manager-section">
                    <h2>About Page</h2>
                    <label>About Heading
                        <input type="text" name="about_heading" value="<?= e($aboutHeading) ?>" required>
                    </label>
                    <label>About Description
                        <textarea name="about_description" rows="6" required><?= e($aboutDescription) ?></textarea>
                    </label>
                </section>

                <section class="business-switcher website-manager-section">
                    <h2>Contact Page</h2>
                    <label>Contact Heading
                        <input type="text" name="contact_heading" value="<?= e($contactHeading) ?>" required>
                    </label>
                    <label>Contact Description
                        <textarea name="contact_description" rows="4" required><?= e($contactDescription) ?></textarea>
                    </label>
                </section>

                <section class="business-switcher website-manager-section">
                    <h2>Service Pages</h2>
                    <div class="service-page-grid website-manager-services">
                        <?php foreach ($bundle['service_pages'] as $servicePage): ?>
                            <?php
                            $serviceNumber = (int) $servicePage['service_number'];
                            $serviceKey = 'service_' . $serviceNumber;
                            $generatedServiceContent = sp247_manager_content($pages, 'service', $serviceNumber);
                            $serviceTitle = sp247_manager_value($overrides, $serviceKey, 'title', (string) ($generatedServiceContent['service_name'] ?? $servicePage['service_name']));
                            $serviceDescription = sp247_manager_value($overrides, $serviceKey, 'description', (string) ($generatedServiceContent['service_description'] ?? $servicePage['short_description']));
                            ?>
                            <fieldset>
                                <legend><?= e('Service ' . $serviceNumber) ?></legend>
                                <label>Service Title
                                    <input type="text" name="service_<?= e($serviceNumber) ?>_title" value="<?= e($serviceTitle) ?>" required>
                                </label>
                                <label>Service Description
                                    <textarea name="service_<?= e($serviceNumber) ?>_description" rows="5" required><?= e($serviceDescription) ?></textarea>
                                </label>
                                <label>Service Image
                                    <span class="website-manager-preview"><?= sp247_manager_image_preview($serviceImages[$serviceNumber] ?? null, 'service ' . $serviceNumber . ' image') ?></span>
                                    <input type="file" name="service_image_<?= e($serviceNumber) ?>" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                                </label>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="business-switcher">
                    <h2>Preview</h2>
                    <p class="muted">Saving stores the website customization. Save & Regenerate Website rebuilds the private preview from these settings and returns to Preview. No public publishing occurs.</p>
                    <div class="button-row">
                        <?= ui_button('Save', '', 'secondary', ['name' => 'action', 'value' => 'save']) ?>
                        <?= ui_button('Save & Regenerate Website', '', 'primary', ['name' => 'action', 'value' => 'save_regenerate']) ?>
                        <?php if ($website !== null): ?>
                            <?= ui_button('Open Preview', 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                        <?php endif; ?>
                    </div>
                </section>
            </form>
        <?php endif; ?>
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
