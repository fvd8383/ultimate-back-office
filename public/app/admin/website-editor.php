<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$businessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
$notice = '';
$error = '';
$loadError = '';
$business = null;
$website = null;
$bundle = [
    'onboarding' => null,
    'configuration' => null,
    'content' => null,
    'service_pages' => [],
    'domain' => null,
    'email' => null,
];
$pages = [];
$branding = [];
$serviceImages = [];
$overrides = [];
$has247spAccess = false;

function admin_site_editor_content(array $pages, string $pageType, ?int $serviceNumber = null): array
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

function admin_site_editor_value(array $overrides, string $pageKey, string $fieldKey, string $fallback): string
{
    $value = trim((string) ($overrides[$pageKey][$fieldKey] ?? ''));

    return $value !== '' ? $value : $fallback;
}

function admin_site_editor_image_preview(?string $path, string $label): string
{
    if ($path === null || $path === '') {
        return '<span class="website-manager-empty">No ' . e($label) . ' uploaded</span>';
    }

    return '<img src="' . e($path) . '" alt="' . e($label) . ' preview">';
}

function admin_site_editor_service_area(?array $configuration): string
{
    if ($configuration === null) {
        return 'Not set';
    }

    $serviceArea = trim(implode(', ', array_filter([
        $configuration['service_area_city'] ?? '',
        $configuration['service_area_state'] ?? '',
        $configuration['service_area_postal_code'] ?? '',
    ])));

    return $serviceArea !== '' ? $serviceArea : 'Not set';
}

function admin_site_editor_service_included_items(array $overrides, string $serviceKey, array $generatedContent, string $serviceName): array
{
    $serviceLabel = strtolower($serviceName !== '' ? $serviceName : 'service');
    $generatedItems = $generatedContent['included_items'] ?? [];
    if (!is_array($generatedItems)) {
        $generatedItems = [];
    }

    return [
        admin_site_editor_value($overrides, $serviceKey, 'included_item_1', (string) ($generatedItems[0] ?? 'You need a clear assessment before a small ' . $serviceLabel . ' issue becomes a bigger problem.')),
        admin_site_editor_value($overrides, $serviceKey, 'included_item_2', (string) ($generatedItems[1] ?? 'You want reliable help from a local business that explains the next step clearly.')),
        admin_site_editor_value($overrides, $serviceKey, 'included_item_3', (string) ($generatedItems[2] ?? 'You are ready to schedule ' . $serviceLabel . ' and want the job handled professionally.')),
    ];
}

function admin_site_editor_service_trust_cards(array $overrides, string $serviceKey, array $generatedContent, string $serviceArea): array
{
    $generatedCards = $generatedContent['trust_cards'] ?? [];
    if (!is_array($generatedCards)) {
        $generatedCards = [];
    }

    return [
        [
            'title' => admin_site_editor_value($overrides, $serviceKey, 'trust_1_title', (string) ($generatedCards[0]['title'] ?? 'Local')),
            'text' => admin_site_editor_value($overrides, $serviceKey, 'trust_1_text', (string) ($generatedCards[0]['text'] ?? ($serviceArea !== '' ? 'Serving ' . $serviceArea : 'Service near you'))),
        ],
        [
            'title' => admin_site_editor_value($overrides, $serviceKey, 'trust_2_title', (string) ($generatedCards[1]['title'] ?? 'Clear')),
            'text' => admin_site_editor_value($overrides, $serviceKey, 'trust_2_text', (string) ($generatedCards[1]['text'] ?? 'Simple communication before work begins')),
        ],
        [
            'title' => admin_site_editor_value($overrides, $serviceKey, 'trust_3_title', (string) ($generatedCards[2]['title'] ?? 'Ready')),
            'text' => admin_site_editor_value($overrides, $serviceKey, 'trust_3_text', (string) ($generatedCards[2]['text'] ?? 'Call or request service when you need help')),
        ],
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($businessId <= 0) {
            throw new InvalidArgumentException('Business is required for website editing.');
        }

        if (!TwentyFourSevenSalesPartner::businessHasAccess($businessId)) {
            throw new InvalidArgumentException('24/7 Sales Partner must be active before editing this website.');
        }

        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'save_regenerate') {
            WebsiteManager::saveAndRegenerate($businessId, (int) $context['user']['id'], $_POST, $_FILES);
            header('Location: website-editor.php?business_id=' . urlencode((string) $businessId) . '&regenerated=1');
            exit;
        }

        if ($action === 'regenerate') {
            AdminPortal::generateWebsiteForBusiness($businessId, (int) $context['user']['id'], true);
            header('Location: website-editor.php?business_id=' . urlencode((string) $businessId) . '&regenerated=1');
            exit;
        }

        WebsiteManager::saveWebsiteManager($businessId, (int) $context['user']['id'], $_POST, $_FILES);
        header('Location: website-editor.php?business_id=' . urlencode((string) $businessId) . '&saved=1');
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Website editor action could not be completed.';
}

if (isset($_GET['saved'])) {
    $notice = 'Website edits saved.';
}
if (isset($_GET['regenerated'])) {
    $notice = 'Website regenerated with saved edits.';
}

try {
    if ($businessId <= 0) {
        throw new InvalidArgumentException('Business is required for website editing.');
    }

    $business = AdminPortal::business($businessId);
    if ($business === null) {
        throw new InvalidArgumentException('Business could not be found.');
    }

    $has247spAccess = TwentyFourSevenSalesPartner::businessHasAccess($businessId);
    $website = SiteGenerator::websiteForBusiness($businessId);
    $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
    $pages = $website !== null ? SiteGenerator::pagesForWebsite((int) $website['id']) : [];
    $branding = WebsiteManager::brandingForBusiness($businessId);
    $serviceImages = WebsiteManager::serviceImagesForBusiness($businessId);
    $overrides = WebsiteManager::contentOverridesForBusiness($businessId);
} catch (InvalidArgumentException $exception) {
    $loadError = $exception->getMessage();
} catch (Throwable $exception) {
    $loadError = 'Website editor could not be loaded.';
}

$businessName = (string) ($business['business_name'] ?? '');
$businessContent = $bundle['content'] ?? [];
$configuration = $bundle['configuration'] ?? null;
$serviceAreaDisplay = admin_site_editor_service_area($configuration);
$serviceAreaForCopy = $serviceAreaDisplay !== 'Not set' ? $serviceAreaDisplay : '';
$homeContent = admin_site_editor_content($pages, 'home');
$aboutContent = admin_site_editor_content($pages, 'about');
$contactContent = admin_site_editor_content($pages, 'contact');

$homeHeadline = admin_site_editor_value($overrides, 'home', 'headline', (string) ($homeContent['headline'] ?? $businessName));
$homeSubheadline = admin_site_editor_value($overrides, 'home', 'subheadline', (string) ($homeContent['subheadline'] ?? $homeContent['business_description'] ?? $businessContent['business_description'] ?? ''));
$homeCallToAction = admin_site_editor_value($overrides, 'home', 'call_to_action', (string) ($homeContent['call_to_action'] ?? ('Call ' . $businessName . ' today to request service.')));
$aboutHeading = admin_site_editor_value($overrides, 'about', 'heading', (string) ($aboutContent['about_heading'] ?? ('About ' . $businessName)));
$aboutDescription = admin_site_editor_value($overrides, 'about', 'description', (string) ($aboutContent['company_description'] ?? $businessContent['about_company'] ?? ''));
$aboutHeroImage = admin_site_editor_value($overrides, 'about', 'hero_image_path', (string) ($aboutContent['hero_image_path'] ?? $branding['about_image_path'] ?? ''));
$contactHeading = admin_site_editor_value($overrides, 'contact', 'heading', (string) ($contactContent['contact_heading'] ?? ('Contact ' . $businessName)));
$contactDescription = admin_site_editor_value($overrides, 'contact', 'description', (string) ($contactContent['contact_description'] ?? 'Tell us what you need and we will help you take the next step.'));
$contactHeroImage = admin_site_editor_value($overrides, 'contact', 'hero_image_path', (string) ($contactContent['hero_image_path'] ?? ''));

$detailHref = $website !== null
    ? 'website.php?website_id=' . urlencode((string) $website['id'])
    : 'website.php?business_id=' . urlencode((string) $businessId);
$previewHref = $businessId > 0
    ? $context['app_base_url'] . '/247sp/site-preview.php?business_id=' . urlencode((string) $businessId)
    : '';

admin_begin('Website Editor', 'websites', $context);
?>
<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
    <section class="empty-state">
        <h1>Website editor unavailable</h1>
        <p>Open a generated website or business with active 24/7 Sales Partner access before editing.</p>
        <?= ui_button('Return to Websites', 'websites.php', 'secondary') ?>
    </section>
<?php elseif (!$has247spAccess): ?>
    <section class="empty-state">
        <h1>24/7 Sales Partner is not active</h1>
        <p>Activate 24/7 Sales Partner for this business before editing its website.</p>
        <?= ui_button('Return to Website Detail', $detailHref, 'secondary') ?>
    </section>
<?php else: ?>
    <section class="hero-panel">
        <p class="eyebrow">DFY Site Editor</p>
        <h1><?= e($businessName) ?></h1>
        <p class="muted">Edit branding, content, service pages, and generated preview settings for this 24/7 Sales Partner website.</p>
        <div class="button-row">
            <?= ui_button('Return to Website Detail', $detailHref, 'secondary') ?>
            <?php if ($previewHref !== '' && $website !== null): ?>
                <?= ui_button('Preview Site', $previewHref, 'secondary') ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Business Display Context</h2>
        <p class="muted">Phone, email, and service area are sourced from the business profile and 247SP onboarding records. Edit those records from the business profile or 247SP onboarding if they need to change.</p>
        <div class="summary-list">
            <div><dt>Display Phone</dt><dd><?= e($business['phone'] ?? 'Not set') ?></dd></div>
            <div><dt>Display Email</dt><dd><?= e($business['email'] ?? 'Not set') ?></dd></div>
            <div><dt>Service Area</dt><dd><?= e($serviceAreaDisplay) ?></dd></div>
        </div>
    </section>

    <form method="post" action="website-editor.php" enctype="multipart/form-data" class="website-manager-form">
        <input type="hidden" name="business_id" value="<?= e($businessId) ?>">

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
                    <span class="website-manager-preview"><?= admin_site_editor_image_preview($branding['logo_path'] ?? null, 'logo') ?></span>
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml">
                </label>
                <label>Home Hero Image
                    <span class="website-manager-preview"><?= admin_site_editor_image_preview($branding['hero_image_path'] ?? null, 'home hero image') ?></span>
                    <input type="file" name="hero_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                </label>
                <label>About Image
                    <span class="website-manager-preview"><?= admin_site_editor_image_preview($branding['about_image_path'] ?? null, 'about image') ?></span>
                    <input type="file" name="about_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                </label>
            </div>
        </section>

        <section class="business-switcher website-manager-section">
            <h2>Homepage</h2>
            <label>Homepage Heading
                <input type="text" name="home_headline" value="<?= e($homeHeadline) ?>" required>
            </label>
            <label>Homepage Description
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
            <label>About Hero Image
                <span class="website-manager-preview"><?= admin_site_editor_image_preview($aboutHeroImage, 'about hero image') ?></span>
                <input type="file" name="about_hero_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
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
            <label>Contact Hero Image
                <span class="website-manager-preview"><?= admin_site_editor_image_preview($contactHeroImage, 'contact hero image') ?></span>
                <input type="file" name="contact_hero_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
            </label>
        </section>

        <section class="business-switcher website-manager-section">
            <h2>Service Pages</h2>
            <div class="service-page-grid website-manager-services">
                <?php foreach ($bundle['service_pages'] as $servicePage): ?>
                    <?php
                    $serviceNumber = (int) $servicePage['service_number'];
                    $serviceKey = 'service_' . $serviceNumber;
                    $generatedServiceContent = admin_site_editor_content($pages, 'service', $serviceNumber);
                    $serviceTitle = admin_site_editor_value($overrides, $serviceKey, 'title', (string) ($generatedServiceContent['service_name'] ?? $servicePage['service_name']));
                    $serviceDescription = admin_site_editor_value($overrides, $serviceKey, 'description', (string) ($generatedServiceContent['service_description'] ?? $servicePage['short_description']));
                    $serviceHeroImage = admin_site_editor_value($overrides, $serviceKey, 'hero_image_path', (string) ($generatedServiceContent['hero_image_path'] ?? $branding['hero_image_path'] ?? ''));
                    $serviceIncludedHeading = admin_site_editor_value($overrides, $serviceKey, 'included_heading', (string) ($generatedServiceContent['included_heading'] ?? ($serviceTitle . ' made straightforward')));
                    $serviceIncludedDescription = admin_site_editor_value($overrides, $serviceKey, 'included_description', (string) ($generatedServiceContent['included_description'] ?? ($businessName . ' helps customers understand the issue, choose a practical next step, and get service scheduled without confusion.')));
                    $serviceIncludedItems = admin_site_editor_service_included_items($overrides, $serviceKey, $generatedServiceContent, $serviceTitle);
                    $serviceTrustHeading = admin_site_editor_value($overrides, $serviceKey, 'trust_heading', (string) ($generatedServiceContent['trust_heading'] ?? ('Why choose ' . $businessName . ' for ' . $serviceTitle)));
                    $serviceTrustCards = admin_site_editor_service_trust_cards($overrides, $serviceKey, $generatedServiceContent, $serviceAreaForCopy);
                    ?>
                    <fieldset>
                        <legend><?= e('Service ' . $serviceNumber) ?></legend>
                        <label>Service Title
                            <input type="text" name="service_<?= e($serviceNumber) ?>_title" value="<?= e($serviceTitle) ?>" required>
                        </label>
                        <label>Service Description
                            <textarea name="service_<?= e($serviceNumber) ?>_description" rows="5" required><?= e($serviceDescription) ?></textarea>
                        </label>
                        <label>Service Hero Image
                            <span class="website-manager-preview"><?= admin_site_editor_image_preview($serviceHeroImage, 'service ' . $serviceNumber . ' hero image') ?></span>
                            <input type="file" name="service_<?= e($serviceNumber) ?>_hero_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                        </label>
                        <label>Service Image
                            <span class="website-manager-preview"><?= admin_site_editor_image_preview($serviceImages[$serviceNumber] ?? null, 'service ' . $serviceNumber . ' image') ?></span>
                            <input type="file" name="service_image_<?= e($serviceNumber) ?>" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                        </label>
                        <label>What Is Included Heading
                            <input type="text" name="service_<?= e($serviceNumber) ?>_included_heading" value="<?= e($serviceIncludedHeading) ?>">
                        </label>
                        <label>What Is Included Description
                            <textarea name="service_<?= e($serviceNumber) ?>_included_description" rows="4"><?= e($serviceIncludedDescription) ?></textarea>
                        </label>
                        <?php foreach ($serviceIncludedItems as $index => $item): ?>
                            <label><?= e('Included Bullet ' . ($index + 1)) ?>
                                <textarea name="service_<?= e($serviceNumber) ?>_included_item_<?= e($index + 1) ?>" rows="3"><?= e($item) ?></textarea>
                            </label>
                        <?php endforeach; ?>
                        <label>Trust Section Heading
                            <input type="text" name="service_<?= e($serviceNumber) ?>_trust_heading" value="<?= e($serviceTrustHeading) ?>">
                        </label>
                        <?php foreach ($serviceTrustCards as $index => $trustCard): ?>
                            <div class="form-grid">
                                <label><?= e('Trust Card ' . ($index + 1) . ' Title') ?>
                                    <input type="text" name="service_<?= e($serviceNumber) ?>_trust_<?= e($index + 1) ?>_title" value="<?= e($trustCard['title'] ?? '') ?>">
                                </label>
                                <label><?= e('Trust Card ' . ($index + 1) . ' Text') ?>
                                    <input type="text" name="service_<?= e($serviceNumber) ?>_trust_<?= e($index + 1) ?>_text" value="<?= e($trustCard['text'] ?? '') ?>">
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="business-switcher">
            <h2>Save And Review</h2>
            <p class="muted">Save keeps edits in the shared 247SP website manager tables. Save & Regenerate Site rebuilds the private preview from saved edits and preserves them for customer Website Manager.</p>
            <div class="button-row">
                <?= ui_button('Save Changes', '', 'secondary', ['name' => 'action', 'value' => 'save']) ?>
                <?= ui_button('Save & Regenerate Site', '', 'primary', ['name' => 'action', 'value' => 'save_regenerate']) ?>
                <?php if ($website !== null): ?>
                    <?= ui_button('Regenerate Site', '', 'secondary', ['name' => 'action', 'value' => 'regenerate']) ?>
                    <?= ui_button('Preview Site', $previewHref, 'secondary') ?>
                <?php endif; ?>
                <?= ui_button('Return to Website Detail', $detailHref, 'secondary') ?>
            </div>
        </section>
    </form>
<?php endif; ?>
<?php admin_end(); ?>
