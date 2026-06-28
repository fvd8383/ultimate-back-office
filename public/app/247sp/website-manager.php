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

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$businessName = (string) ($business['business_name'] ?? '');
$businessContent = $bundle['content'] ?? [];
$homeContent = sp247_manager_content($pages, 'home');
$aboutContent = sp247_manager_content($pages, 'about');
$contactContent = sp247_manager_content($pages, 'contact');

$homeHeadline = sp247_manager_value($overrides, 'home', 'headline', (string) ($homeContent['headline'] ?? $businessName));
$homeSubheadline = sp247_manager_value($overrides, 'home', 'subheadline', (string) ($homeContent['subheadline'] ?? $homeContent['business_description'] ?? $businessContent['business_description'] ?? ''));
$homeCallToAction = sp247_manager_value($overrides, 'home', 'call_to_action', (string) ($homeContent['call_to_action'] ?? ('Call ' . $businessName . ' today to request service.')));
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
                    <label>Call To Action
                        <input type="text" name="home_call_to_action" value="<?= e($homeCallToAction) ?>" required>
                    </label>
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
