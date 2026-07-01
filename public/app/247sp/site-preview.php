<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerator.php';
require_once __DIR__ . '/../../../private/classes/AdminPortal.php';
require_once __DIR__ . '/../../../private/classes/WebsiteManager.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$user = null;
$business = null;
$website = null;
$pages = [];
$currentPage = null;
$branding = [];
$contentOverrides = [];
$serviceImages = [];
$loadError = '';
$accessDenied = false;
$previewUpdated = isset($_GET['regenerated']);
$leadNotice = '';
$leadError = '';

if (($_GET['lead_status'] ?? '') === 'success') {
    $leadNotice = 'Thanks. Your request was sent successfully.';
}

if (($_GET['lead_status'] ?? '') === 'error') {
    $leadErrors = [
        'name' => 'Please enter your name.',
        'contact' => 'Please enter a phone number or email address.',
        'email' => 'Please enter a valid email address.',
        'spam' => 'This request could not be accepted. Please call or try again.',
        'website' => 'This request could not be matched to a website.',
    ];
    $leadError = $leadErrors[(string) ($_GET['lead_error'] ?? '')] ?? 'Your request could not be sent. Please call or try again.';
}

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_GET['business_id'] ?? 0);
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId > 0 ? $requestedBusinessId : null, (int) $user['id']);

    if ($business === null && $requestedBusinessId > 0 && AdminPortal::currentUserIsAdmin((int) $user['id'])) {
        $business = AdminPortal::business($requestedBusinessId);
    }

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);

        if (!$accessDenied) {
            $website = SiteGenerator::websiteForBusiness($businessId);

            if ($website !== null) {
                $pages = SiteGenerator::pagesForWebsite((int) $website['id']);
                $branding = WebsiteManager::brandingForBusiness($businessId);
                $contentOverrides = WebsiteManager::contentOverridesForBusiness($businessId);
                $serviceImages = WebsiteManager::serviceImagesForBusiness($businessId);
                $requestedSlug = (string) ($_GET['page'] ?? ($pages[0]['slug'] ?? ''));
                $currentPage = $requestedSlug !== '' ? SiteGenerator::pageBySlug((int) $website['id'], $requestedSlug) : null;
                $currentPage = $currentPage ?? ($pages[0] ?? null);
            }
        }
    }
} catch (Throwable $exception) {
    $loadError = '247SP website preview could not be loaded. Check the database setup and try again.';
}

function sp247_preview_content(?array $page): array
{
    if ($page === null) {
        return [];
    }

    $decoded = json_decode((string) $page['content_json'], true);

    return is_array($decoded) ? $decoded : [];
}

function sp247_preview_href(int $businessId, string $slug): string
{
    return 'site-preview.php?business_id=' . urlencode((string) $businessId) . '&page=' . urlencode($slug);
}

function sp247_preview_page_content(array $pages, string $slug): array
{
    foreach ($pages as $page) {
        if ((string) $page['slug'] === $slug) {
            return sp247_preview_content($page);
        }
    }

    return [];
}

function sp247_preview_services(array $pages, array $overrides, array $serviceImages): array
{
    $services = [];
    $serviceNumber = 0;

    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') !== 'service') {
            continue;
        }

        $serviceNumber++;
        $content = sp247_preview_content($page);
        $storedServiceNumber = (int) ($content['service_number'] ?? 0);
        $effectiveServiceNumber = $storedServiceNumber > 0 ? $storedServiceNumber : $serviceNumber;
        $serviceKey = 'service_' . $effectiveServiceNumber;
        $services[] = [
            'title' => (string) (($overrides[$serviceKey]['title'] ?? '') ?: ($content['service_name'] ?? $page['title'])),
            'description' => (string) (($overrides[$serviceKey]['description'] ?? '') ?: ($content['service_description'] ?? '')),
            'slug' => (string) $page['slug'],
            'image_path' => (string) ($serviceImages[$effectiveServiceNumber] ?? ($content['service_image_path'] ?? '')),
        ];
    }

    return $services;
}

function sp247_preview_page_slug(array $pages, string $pageType, string $fallback): string
{
    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') === $pageType) {
            return (string) $page['slug'];
        }
    }

    return $fallback;
}

function sp247_preview_service_nav_tree(array $pages, array $overrides): array
{
    $itemsByServicePageId = [];
    $rootIds = [];
    $childrenByParentId = [];
    $serviceNumberFallback = 0;

    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') !== 'service') {
            continue;
        }

        $serviceNumberFallback++;
        $content = sp247_preview_content($page);
        $serviceNumber = (int) ($content['service_number'] ?? 0);
        $serviceNumber = $serviceNumber > 0 ? $serviceNumber : $serviceNumberFallback;
        $serviceKey = 'service_' . $serviceNumber;
        $servicePageId = (int) ($content['service_page_id'] ?? 0);
        if ($servicePageId <= 0) {
            $servicePageId = -1 * $serviceNumberFallback;
        }

        $itemsByServicePageId[$servicePageId] = [
            'service_page_id' => $servicePageId,
            'parent_service_page_id' => (int) ($content['parent_service_page_id'] ?? 0),
            'title' => (string) (($overrides[$serviceKey]['title'] ?? '') ?: ($content['service_name'] ?? $page['title'])),
            'slug' => (string) $page['slug'],
            'page_id' => (int) $page['id'],
            'children' => [],
        ];
    }

    foreach ($itemsByServicePageId as $servicePageId => $item) {
        $parentId = (int) $item['parent_service_page_id'];
        if ($parentId > 0 && isset($itemsByServicePageId[$parentId])) {
            $childrenByParentId[$parentId][] = $servicePageId;
            continue;
        }

        $rootIds[] = $servicePageId;
    }

    $tree = [];
    foreach ($rootIds as $rootId) {
        $rootItem = $itemsByServicePageId[$rootId];
        foreach ($childrenByParentId[$rootId] ?? [] as $childId) {
            $rootItem['children'][] = $itemsByServicePageId[$childId];
        }
        $tree[] = $rootItem;
    }

    return $tree;
}

function sp247_preview_tel_href(string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', $phone);

    return $digits !== '' ? 'tel:' . $digits : '#';
}

function sp247_preview_cta(array $content, array $overrides, string $slot, string $defaultType, string $defaultLabel, int $businessId, string $phoneHref, string $pricingListPath): array
{
    $generated = $content[$slot . '_cta'] ?? [];
    if (!is_array($generated)) {
        $generated = [];
    }

    $type = (string) (($overrides['home'][$slot . '_cta_type'] ?? '') ?: ($generated['type'] ?? $defaultType));
    if (in_array($type, ['schedule_service', 'request_service', 'instant_quote'], true)) {
        $type = 'contact_form';
    }
    if (!in_array($type, ['call_now', 'contact_form', 'view_pricing'], true)) {
        $type = in_array($defaultType, ['call_now', 'contact_form', 'view_pricing'], true) ? $defaultType : 'contact_form';
    }

    $label = (string) (($overrides['home'][$slot . '_cta_label'] ?? '') ?: ($generated['label'] ?? $defaultLabel));
    if (strcasecmp($label, 'View Pricing') === 0) {
        $type = 'view_pricing';
    }

    $href = sp247_preview_href($businessId, 'contact');
    if ($type === 'call_now') {
        $href = $phoneHref;
    }
    if ($type === 'view_pricing' && $pricingListPath !== '') {
        $href = $pricingListPath;
    }

    return [
        'type' => $type,
        'label' => $label,
        'href' => $href,
    ];
}

function sp247_preview_cta_is_pricing(array $cta): bool
{
    return ($cta['type'] ?? '') === 'view_pricing' || strcasecmp((string) ($cta['label'] ?? ''), 'View Pricing') === 0;
}

function sp247_preview_home_stats(array $homeContent, array $overrides, int $serviceCount): array
{
    $generatedStats = $homeContent['stats'] ?? [];
    if (!is_array($generatedStats)) {
        $generatedStats = [];
    }

    $stats = [
        [
            'value' => (string) ($generatedStats[0]['value'] ?? 'Local'),
            'label' => (string) ($generatedStats[0]['label'] ?? 'Service'),
        ],
        [
            'value' => (string) ($generatedStats[1]['value'] ?? $serviceCount),
            'label' => (string) ($generatedStats[1]['label'] ?? 'Core services available'),
        ],
        [
            'value' => (string) ($generatedStats[2]['value'] ?? (($homeContent['financing_available'] ?? false) === true ? 'Yes' : 'Clear')),
            'label' => (string) ($generatedStats[2]['label'] ?? (($homeContent['financing_available'] ?? false) === true ? 'Financing available' : 'Communication from request to service')),
        ],
    ];

    foreach ($stats as $index => &$stat) {
        $statNumber = $index + 1;
        $stat['value'] = (string) (($overrides['home']['stat_' . $statNumber . '_value'] ?? '') ?: $stat['value']);
        $stat['label'] = (string) (($overrides['home']['stat_' . $statNumber . '_label'] ?? '') ?: $stat['label']);
    }
    unset($stat);

    return array_values(array_filter($stats, static function (array $stat): bool {
        return trim((string) $stat['value']) !== '' && trim((string) $stat['label']) !== '';
    }));
}

function sp247_preview_service_included_items(array $content, array $overrides, string $serviceKey, string $serviceName): array
{
    $serviceLabel = strtolower($serviceName !== '' ? $serviceName : 'service');
    $generatedItems = $content['included_items'] ?? [];
    if (!is_array($generatedItems)) {
        $generatedItems = [];
    }

    return [
        (string) (($overrides[$serviceKey]['included_item_1'] ?? '') ?: ($generatedItems[0] ?? 'You need a clear assessment before a small ' . $serviceLabel . ' issue becomes a bigger problem.')),
        (string) (($overrides[$serviceKey]['included_item_2'] ?? '') ?: ($generatedItems[1] ?? 'You want reliable help from a local business that explains the next step clearly.')),
        (string) (($overrides[$serviceKey]['included_item_3'] ?? '') ?: ($generatedItems[2] ?? 'You are ready to schedule ' . $serviceLabel . ' and want the job handled professionally.')),
    ];
}

function sp247_preview_service_trust_cards(array $content, array $overrides, string $serviceKey, string $serviceArea): array
{
    $generatedCards = $content['trust_cards'] ?? [];
    if (!is_array($generatedCards)) {
        $generatedCards = [];
    }

    return [
        [
            'title' => (string) (($overrides[$serviceKey]['trust_1_title'] ?? '') ?: ($generatedCards[0]['title'] ?? 'Local')),
            'text' => (string) (($overrides[$serviceKey]['trust_1_text'] ?? '') ?: ($generatedCards[0]['text'] ?? ($serviceArea !== '' ? 'Serving ' . $serviceArea : 'Service near you'))),
        ],
        [
            'title' => (string) (($overrides[$serviceKey]['trust_2_title'] ?? '') ?: ($generatedCards[1]['title'] ?? 'Clear')),
            'text' => (string) (($overrides[$serviceKey]['trust_2_text'] ?? '') ?: ($generatedCards[1]['text'] ?? 'Simple communication before work begins')),
        ],
        [
            'title' => (string) (($overrides[$serviceKey]['trust_3_title'] ?? '') ?: ($generatedCards[2]['title'] ?? 'Ready')),
            'text' => (string) (($overrides[$serviceKey]['trust_3_text'] ?? '') ?: ($generatedCards[2]['text'] ?? 'Call or request service when you need help')),
        ],
    ];
}

function sp247_preview_style(array $branding): string
{
    $primary = (string) ($branding['primary_color'] ?? WebsiteManager::DEFAULT_PRIMARY_COLOR);
    $secondary = (string) ($branding['secondary_color'] ?? $primary);

    return '--preview-blue:' . $primary . ';--preview-blue-dark:' . $primary . ';--preview-accent:' . $secondary . ';';
}

function sp247_preview_lead_form(int $businessId, ?array $website, ?array $currentPage, string $sourcePage, string $serviceName, int $rows = 4): void
{
    $websiteId = (int) ($website['id'] ?? 0);
    $pageId = (int) ($currentPage['id'] ?? 0);
    $sourceSlug = (string) ($currentPage['slug'] ?? '');
    ?>
    <form method="post" action="lead-submit.php" class="site-preview-lead-form">
        <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
        <input type="hidden" name="website_id" value="<?= e($websiteId) ?>">
        <input type="hidden" name="page_id" value="<?= e($pageId) ?>">
        <input type="hidden" name="source_page" value="<?= e($sourcePage) ?>">
        <input type="hidden" name="source_slug" value="<?= e($sourceSlug) ?>">
        <input type="hidden" name="service_name" value="<?= e($serviceName) ?>">
        <label class="lead-capture-honeypot" aria-hidden="true">Company website
            <input type="text" name="company_website" tabindex="-1" autocomplete="off">
        </label>
        <div class="form-grid">
            <label>Name
                <input name="name" maxlength="150" required autocomplete="name">
            </label>
            <label>Phone
                <input name="phone" maxlength="50" autocomplete="tel">
            </label>
        </div>
        <label>Email
            <input type="email" name="email" maxlength="255" autocomplete="email">
        </label>
        <label>How can we help?
            <textarea name="message" maxlength="2000" rows="<?= e($rows) ?>"></textarea>
        </label>
        <button class="site-preview-button" type="submit">Send Request</button>
    </form>
    <?php
}

function sp247_preview_service_number(array $pages, ?array $currentPage): int
{
    if ($currentPage === null || ($currentPage['page_type'] ?? '') !== 'service') {
        return 0;
    }

    $content = sp247_preview_content($currentPage);
    $storedServiceNumber = (int) ($content['service_number'] ?? 0);
    if ($storedServiceNumber > 0) {
        return $storedServiceNumber;
    }

    $serviceNumber = 0;
    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') !== 'service') {
            continue;
        }

        $serviceNumber++;
        if ((int) ($page['id'] ?? 0) === (int) ($currentPage['id'] ?? 0)) {
            return $serviceNumber;
        }
    }

    return 0;
}

function sp247_preview_apply_overrides(array $content, string $pageType, int $serviceNumber, array $overrides, array $branding, array $serviceImages): array
{
    if ($pageType === 'home') {
        $content['headline'] = (string) (($overrides['home']['headline'] ?? '') ?: ($content['headline'] ?? ''));
        $content['subheadline'] = (string) (($overrides['home']['subheadline'] ?? '') ?: ($content['subheadline'] ?? $content['business_description'] ?? ''));
        $content['business_description'] = $content['subheadline'];
        $content['call_to_action'] = (string) (($overrides['home']['call_to_action'] ?? '') ?: ($content['call_to_action'] ?? ''));
        $pricingListPath = (string) (($overrides['home']['pricing_list_path'] ?? '') ?: ($content['pricing_list_path'] ?? ''));
        $content['pricing_list_path'] = $pricingListPath;
        $content['primary_cta'] = sp247_preview_cta($content, $overrides, 'primary', 'call_now', 'Call Now', 0, '#', $pricingListPath);
        $content['secondary_cta'] = sp247_preview_cta($content, $overrides, 'secondary', 'contact_form', 'Contact Us', 0, '#', $pricingListPath);
        $content['hero_image_path'] = (string) (($overrides['home']['hero_image_path'] ?? '') ?: ($branding['hero_image_path'] ?? ($content['hero_image_path'] ?? '')));
    }

    if ($pageType === 'about') {
        $content['about_heading'] = (string) (($overrides['about']['heading'] ?? '') ?: ($content['about_heading'] ?? ''));
        $content['company_description'] = (string) (($overrides['about']['description'] ?? '') ?: ($content['company_description'] ?? ''));
        $content['hero_image_path'] = (string) (($overrides['about']['hero_image_path'] ?? '') ?: ($content['hero_image_path'] ?? ($branding['about_image_path'] ?? '')));
        $content['about_image_path'] = (string) ($branding['about_image_path'] ?? ($content['about_image_path'] ?? ''));
    }

    if ($pageType === 'contact') {
        $content['contact_heading'] = (string) (($overrides['contact']['heading'] ?? '') ?: ($content['contact_heading'] ?? ''));
        $content['contact_description'] = (string) (($overrides['contact']['description'] ?? '') ?: ($content['contact_description'] ?? ''));
        $content['hero_image_path'] = (string) (($overrides['contact']['hero_image_path'] ?? '') ?: ($content['hero_image_path'] ?? ''));
    }

    if ($pageType === 'service' && $serviceNumber > 0) {
        $serviceKey = 'service_' . $serviceNumber;
        $content['service_name'] = (string) (($overrides[$serviceKey]['title'] ?? '') ?: ($content['service_name'] ?? ''));
        $content['service_description'] = (string) (($overrides[$serviceKey]['description'] ?? '') ?: ($content['service_description'] ?? ''));
        $content['included_heading'] = (string) (($overrides[$serviceKey]['included_heading'] ?? '') ?: ($content['included_heading'] ?? (($content['service_name'] ?? 'Service') . ' made straightforward')));
        $content['included_description'] = (string) (($overrides[$serviceKey]['included_description'] ?? '') ?: ($content['included_description'] ?? 'This service helps customers understand the issue, choose a practical next step, and get service scheduled without confusion.'));
        $content['included_items'] = sp247_preview_service_included_items($content, $overrides, $serviceKey, (string) $content['service_name']);
        $content['trust_heading'] = (string) (($overrides[$serviceKey]['trust_heading'] ?? '') ?: ($content['trust_heading'] ?? ('Why choose this team for ' . ($content['service_name'] ?? 'service'))));
        $content['trust_cards'] = sp247_preview_service_trust_cards($content, $overrides, $serviceKey, (string) ($content['service_area'] ?? ''));
        $content['hero_image_path'] = (string) (($overrides[$serviceKey]['hero_image_path'] ?? '') ?: ($content['hero_image_path'] ?? ($branding['hero_image_path'] ?? '')));
        $content['service_image_path'] = (string) ($serviceImages[$serviceNumber] ?? ($content['service_image_path'] ?? ''));
    }

    return $content;
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$sp247NavItems = [
    ['label' => 'Dashboard', 'href' => $businessIdForLinks > 0 ? 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks) : 'dashboard.php'],
    ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
    ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
    ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php', 'current' => true],
];
if ($businessIdForLinks > 0 && !$accessDenied) {
    array_splice($sp247NavItems, 4, 0, [[
        'label' => 'Website Manager',
        'href' => 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks),
    ]]);
}
$currentServiceNumber = sp247_preview_service_number($pages, $currentPage);
$content = sp247_preview_apply_overrides(
    sp247_preview_content($currentPage),
    (string) ($currentPage['page_type'] ?? ''),
    $currentServiceNumber,
    $contentOverrides,
    $branding,
    $serviceImages
);
$homeContent = sp247_preview_apply_overrides(sp247_preview_page_content($pages, 'home'), 'home', 0, $contentOverrides, $branding, $serviceImages);
$aboutContent = sp247_preview_apply_overrides(sp247_preview_page_content($pages, 'about'), 'about', 0, $contentOverrides, $branding, $serviceImages);
$contactContent = sp247_preview_apply_overrides(sp247_preview_page_content($pages, 'contact'), 'contact', 0, $contentOverrides, $branding, $serviceImages);
$previewServices = sp247_preview_services($pages, $contentOverrides, $serviceImages);
$serviceNavItems = sp247_preview_service_nav_tree($pages, $contentOverrides);
$serviceArea = (string) (($content['service_area'] ?? '') ?: ($homeContent['service_area'] ?? '') ?: ($contactContent['service_area'] ?? ''));
$phone = BusinessFoundation::formatPhoneForDisplay((string) (($contactContent['phone'] ?? '') ?: ($business['phone'] ?? '')));
$email = (string) (($contactContent['email'] ?? '') ?: ($business['email'] ?? ''));
$phoneHref = sp247_preview_tel_href($phone);
$currentPageType = (string) ($currentPage['page_type'] ?? '');
$currentServiceName = $currentPageType === 'service' ? (string) ($content['service_name'] ?? $currentPage['title'] ?? '') : '';
$currentSourcePage = (string) ($currentPage['title'] ?? ucfirst($currentPageType ?: 'page'));
$heroImage = (string) (($content['hero_image_path'] ?? '') ?: ($currentPageType === 'home' ? ($homeContent['hero_image_path'] ?? '') : ''));
$aboutImage = (string) ($content['about_image_path'] ?? '');
$serviceImage = (string) ($content['service_image_path'] ?? '');
$pricingListPath = (string) ($homeContent['pricing_list_path'] ?? '');
$primaryCta = sp247_preview_cta($homeContent, $contentOverrides, 'primary', 'call_now', (string) (($homeContent['call_to_action'] ?? '') ?: 'Call Now'), $businessIdForLinks, $phoneHref, $pricingListPath);
$secondaryCta = sp247_preview_cta($homeContent, $contentOverrides, 'secondary', 'contact_form', 'Contact Us', $businessIdForLinks, $phoneHref, $pricingListPath);
$showPricingCta = $pricingListPath !== '' && !sp247_preview_cta_is_pricing($primaryCta) && !sp247_preview_cta_is_pricing($secondaryCta);
$showPricingFallbackCopy = $pricingListPath === '' && (sp247_preview_cta_is_pricing($primaryCta) || sp247_preview_cta_is_pricing($secondaryCta));
$homeStats = sp247_preview_home_stats($homeContent, $contentOverrides, count($previewServices));
$homeSlug = sp247_preview_page_slug($pages, 'home', 'home');
$aboutSlug = sp247_preview_page_slug($pages, 'about', 'about');
$contactSlug = sp247_preview_page_slug($pages, 'contact', 'contact');

$pageTitle = '247SP Website Preview - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
?>
<?php application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user, 'business' => $business, 'secondary_nav' => $sp247NavItems]); ?>
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">Private website preview</p>
            <h1><?= $business ? e($business['business_name']) : '247SP preview' ?></h1>
            <p class="muted">Review the website pages and page-specific content.</p>
        </section>

        <?php if ($previewUpdated): ?>
            <?= ui_alert('Website preview updated from the latest website manager settings.', 'success') ?>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before viewing a 24/7 Sales Partner preview.</p>
            </section>
        <?php elseif ($accessDenied): ?>
            <section class="empty-state">
                <h2>Access denied</h2>
                <p>24/7 Sales Partner is not active for this business.</p>
            </section>
        <?php elseif ($website === null): ?>
            <section class="empty-state">
                <h2>Website preview unavailable</h2>
                <p>Complete onboarding so the 247SP team can prepare your private preview.</p>
                <div class="button-row">
                    <?= ui_button('Back to dashboard', 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                </div>
            </section>
        <?php else: ?>
            <?php if ($leadNotice !== ''): ?>
                <?= ui_alert($leadNotice, 'success') ?>
            <?php endif; ?>
            <?php if ($leadError !== ''): ?>
                <?= ui_alert($leadError, 'error') ?>
            <?php endif; ?>
            <section class="site-preview-shell" style="<?= e(sp247_preview_style($branding)) ?>">
                <header class="site-preview-header">
                    <a class="site-preview-brand" href="<?= e(sp247_preview_href($businessIdForLinks, 'home')) ?>">
                        <span>
                            <?php if (($branding['logo_path'] ?? '') !== ''): ?>
                                <img class="site-preview-logo" src="<?= e($branding['logo_path']) ?>" alt="<?= e($business['business_name']) ?> logo">
                            <?php else: ?>
                                <?= e(substr((string) $business['business_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </span>
                        <strong><?= e($business['business_name']) ?></strong>
                    </a>
                    <nav class="site-preview-nav" aria-label="Preview website navigation">
                        <a class="<?= $currentPageType === 'home' ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, $homeSlug)) ?>">Home</a>
                        <?php if (count($serviceNavItems) > 0): ?>
                            <details class="site-preview-nav-menu <?= $currentPageType === 'service' ? 'is-active' : '' ?>">
                                <summary class="site-preview-nav-trigger">Services</summary>
                                <div class="site-preview-nav-dropdown">
                                    <?php foreach ($serviceNavItems as $serviceNavItem): ?>
                                        <a class="<?= $currentPage && (int) $currentPage['id'] === (int) $serviceNavItem['page_id'] ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, (string) $serviceNavItem['slug'])) ?>">
                                            <?= e($serviceNavItem['title']) ?>
                                        </a>
                                        <?php foreach ($serviceNavItem['children'] as $childServiceNavItem): ?>
                                            <a class="site-preview-nav-child <?= $currentPage && (int) $currentPage['id'] === (int) $childServiceNavItem['page_id'] ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, (string) $childServiceNavItem['slug'])) ?>">
                                                <?= e($childServiceNavItem['title']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        <a class="<?= $currentPageType === 'about' ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, $aboutSlug)) ?>">About</a>
                        <a class="<?= $currentPageType === 'contact' ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, $contactSlug)) ?>">Contact</a>
                    </nav>
                    <a class="site-preview-phone" href="<?= e($phoneHref) ?>">Call <?= e($phone ?: 'Today') ?></a>
                </header>

                <section class="site-preview-hero">
                    <div>
                        <p class="site-preview-kicker"><?= e($serviceArea !== '' ? 'Serving ' . $serviceArea : 'Local service professionals') ?></p>
                        <?php if ($currentPageType === 'service'): ?>
                            <h2><?= e($content['service_name'] ?? $currentPage['title']) ?></h2>
                            <p><?= e($content['service_description'] ?? '') ?></p>
                        <?php elseif ($currentPageType === 'about'): ?>
                            <h2><?= e($content['about_heading'] ?? ('About ' . $business['business_name'])) ?></h2>
                            <p><?= e($content['company_description'] ?? '') ?></p>
                        <?php elseif ($currentPageType === 'contact'): ?>
                            <h2><?= e($content['contact_heading'] ?? ('Contact ' . $business['business_name'])) ?></h2>
                            <p><?= e($content['contact_description'] ?? 'Tell us what you need and we will help you take the next step.') ?></p>
                        <?php else: ?>
                            <h2><?= e($content['headline'] ?? $business['business_name']) ?></h2>
                            <p><?= e($content['subheadline'] ?? $content['business_description'] ?? '') ?></p>
                        <?php endif; ?>
                        <div class="site-preview-actions">
                            <a class="site-preview-button" href="<?= e($primaryCta['href']) ?>"><?= e($primaryCta['label']) ?></a>
                            <a class="site-preview-button site-preview-button--secondary" href="<?= e($secondaryCta['href']) ?>"><?= e($secondaryCta['label']) ?></a>
                            <?php if ($showPricingCta): ?>
                                <a class="site-preview-button site-preview-button--secondary" href="<?= e($pricingListPath) ?>" target="_blank" rel="noopener">View Pricing</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($showPricingFallbackCopy): ?>
                            <p class="site-preview-cta-note">Tell us what you need and we will help with pricing and next steps.</p>
                        <?php endif; ?>
                    </div>
                    <aside class="site-preview-hero-card">
                        <?php if ($heroImage !== ''): ?>
                            <img class="site-preview-image" src="<?= e($heroImage) ?>" alt="<?= e($business['business_name']) ?> preview image">
                        <?php endif; ?>
                        <strong>Ready to help</strong>
                        <span><?= e($phone ?: 'Phone pending') ?></span>
                        <span><?= e($email ?: 'Email pending') ?></span>
                        <?php if (($homeContent['special_offer'] ?? '') !== ''): ?>
                            <p><?= e($homeContent['special_offer']) ?></p>
                        <?php endif; ?>
                    </aside>
                </section>

                <?php if ($currentPageType === 'service'): ?>
                    <?php
                    $serviceName = (string) ($content['service_name'] ?? $currentPage['title']);
                    $serviceReasons = $content['included_items'] ?? sp247_preview_service_included_items($content, $contentOverrides, 'service_' . $currentServiceNumber, $serviceName);
                    $serviceTrustCards = $content['trust_cards'] ?? sp247_preview_service_trust_cards($content, $contentOverrides, 'service_' . $currentServiceNumber, $serviceArea);
                    $serviceIncludedDescription = trim((string) ($content['included_description'] ?? ''));
                    $serviceTrustHeading = trim((string) ($content['trust_heading'] ?? ''));
                    ?>
                    <section class="site-preview-section site-preview-feature">
                        <div>
                            <p class="site-preview-kicker">What is included</p>
                            <h3><?= e($content['included_heading'] ?? ($serviceName . ' made straightforward')) ?></h3>
                            <p><?= e($serviceIncludedDescription !== '' ? $serviceIncludedDescription : ($business['business_name'] . ' helps customers understand the issue, choose a practical next step, and get service scheduled without confusion.')) ?></p>
                        </div>
                        <div class="site-preview-feature-stack">
                            <?php if ($serviceImage !== ''): ?>
                                <img class="site-preview-image" src="<?= e($serviceImage) ?>" alt="<?= e($serviceName) ?> image">
                            <?php endif; ?>
                            <ul class="site-preview-check-list">
                                <?php foreach ($serviceReasons as $reason): ?>
                                    <li><?= e($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-trust site-preview-trust--focused">
                        <div class="site-preview-section-heading">
                            <p class="site-preview-kicker">Why choose us</p>
                            <h3><?= e($serviceTrustHeading !== '' ? $serviceTrustHeading : ('Why choose ' . $business['business_name'] . ' for ' . $serviceName)) ?></h3>
                        </div>
                        <div class="site-preview-trust-grid">
                            <?php foreach ($serviceTrustCards as $trustCard): ?>
                                <article>
                                    <strong><?= e($trustCard['title'] ?? '') ?></strong>
                                    <span><?= e($trustCard['text'] ?? '') ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-contact site-preview-contact--compact">
                        <div>
                            <p class="site-preview-kicker">Request service</p>
                            <h3>Need <?= e(strtolower($serviceName)) ?>?</h3>
                            <p>Call now or send a request and <?= e($business['business_name']) ?> will help with the next step.</p>
                        </div>
                        <div class="site-preview-contact-card">
                            <a href="<?= e($phoneHref) ?>"><?= e($phone ?: 'Phone pending') ?></a>
                            <span><?= e($email ?: 'Email pending') ?></span>
                            <?php sp247_preview_lead_form($businessIdForLinks, $website, $currentPage, $currentSourcePage, $currentServiceName, 4); ?>
                        </div>
                    </section>
                <?php elseif ($currentPageType === 'about'): ?>
                    <section class="site-preview-section site-preview-story">
                        <div class="site-preview-section-heading">
                            <p class="site-preview-kicker">Company story</p>
                            <h3><?= e($content['about_heading'] ?? 'Local service with a personal touch') ?></h3>
                        </div>
                        <?php if ($aboutImage !== ''): ?>
                            <img class="site-preview-story-image" src="<?= e($aboutImage) ?>" alt="<?= e($business['business_name']) ?> about image">
                        <?php endif; ?>
                        <p><?= e($content['company_description'] ?? '') ?></p>
                    </section>

                    <section class="site-preview-section site-preview-experience">
                        <?php if ((int) ($content['years_in_business'] ?? 0) > 0): ?>
                            <article>
                                <strong><?= e((string) $content['years_in_business']) ?></strong>
                                <span>Years in business</span>
                            </article>
                        <?php else: ?>
                            <article>
                                <strong>Local</strong>
                                <span>Service</span>
                            </article>
                        <?php endif; ?>
                        <div>
                            <p class="site-preview-kicker">Service area</p>
                            <h3><?= e($serviceArea !== '' ? $serviceArea : 'Local service area') ?></h3>
                            <p><?= e($business['business_name']) ?> is built to serve nearby homeowners and small businesses with clear communication and dependable service.</p>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-trust site-preview-trust--focused">
                        <div class="site-preview-section-heading">
                            <p class="site-preview-kicker">Trust</p>
                            <h3>What customers can expect</h3>
                        </div>
                        <div class="site-preview-trust-grid">
                            <article><strong>Local</strong><span>Focused on nearby customers</span></article>
                            <article><strong>Helpful</strong><span>Simple answers and clear next steps</span></article>
                            <article><strong>Responsive</strong><span>Easy phone and request options</span></article>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-area">
                        <div>
                            <p class="site-preview-kicker">Get started</p>
                            <h3>Ready to talk with <?= e($business['business_name']) ?>?</h3>
                            <p>Reach out with your service need and get a simple path forward.</p>
                        </div>
                        <a class="site-preview-button" href="<?= e(sp247_preview_href($businessIdForLinks, 'contact')) ?>">Contact Us</a>
                    </section>
                <?php elseif ($currentPageType === 'contact'): ?>
                    <section class="site-preview-section site-preview-contact site-preview-contact--primary">
                        <div>
                            <p class="site-preview-kicker">Contact details</p>
                            <h3><?= e($content['contact_heading'] ?? 'Call or send a service request') ?></h3>
                            <p><?= e($content['contact_description'] ?? ($business['business_name'] . ' is ready to hear what you need and help you take the next step.')) ?></p>
                            <div class="site-preview-contact-list">
                                <a href="<?= e($phoneHref) ?>"><?= e($phone ?: 'Phone pending') ?></a>
                                <span><?= e($email ?: 'Email pending') ?></span>
                            </div>
                        </div>
                        <div class="site-preview-contact-card">
                            <strong>Service request</strong>
                            <?php sp247_preview_lead_form($businessIdForLinks, $website, $currentPage, $currentSourcePage, '', 5); ?>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-area">
                        <div>
                            <p class="site-preview-kicker">Service area</p>
                            <h3><?= e($serviceArea !== '' ? $serviceArea : 'Local service area') ?></h3>
                            <p>Use this page to call, email, or request help for service in the local area.</p>
                        </div>
                        <a class="site-preview-button site-preview-button--secondary" href="<?= e($phoneHref) ?>">Call <?= e($phone ?: 'Today') ?></a>
                    </section>
                <?php else: ?>
                    <section class="site-preview-section site-preview-services" id="services">
                        <div class="site-preview-section-heading">
                            <p class="site-preview-kicker">Services</p>
                            <h3>How <?= e($business['business_name']) ?> can help</h3>
                        </div>
                        <div class="site-preview-card-grid">
                            <?php foreach ($previewServices as $service): ?>
                                <article class="site-preview-service-card">
                                    <h4><?= e($service['title']) ?></h4>
                                    <p><?= e($service['description']) ?></p>
                                    <a href="<?= e(sp247_preview_href($businessIdForLinks, $service['slug'])) ?>">View service</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-trust">
                        <div class="site-preview-section-heading">
                            <p class="site-preview-kicker">Why choose us</p>
                            <h3>Built around fast, clear service</h3>
                        </div>
                        <div class="site-preview-trust-grid">
                            <?php foreach ($homeStats as $stat): ?>
                                <article>
                                    <strong><?= e($stat['value']) ?></strong>
                                    <span><?= e($stat['label']) ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-area">
                        <div>
                            <p class="site-preview-kicker">Service area</p>
                            <h3><?= e($serviceArea !== '' ? $serviceArea : 'Local service area') ?></h3>
                            <p><?= e($business['business_name']) ?> provides reliable local service with a simple way to call, request help, and choose the service you need.</p>
                        </div>
                        <a class="site-preview-button site-preview-button--secondary" href="<?= e($secondaryCta['href']) ?>"><?= e($secondaryCta['label']) ?></a>
                    </section>

                    <section class="site-preview-section site-preview-contact">
                        <div>
                            <p class="site-preview-kicker">Contact</p>
                            <h3>Ready to schedule service?</h3>
                            <p>Call now or send a request through the contact form.</p>
                        </div>
                        <div class="site-preview-contact-card">
                            <a href="<?= e($phoneHref) ?>"><?= e($phone ?: 'Phone pending') ?></a>
                            <span><?= e($email ?: 'Email pending') ?></span>
                            <?php sp247_preview_lead_form($businessIdForLinks, $website, $currentPage, $currentSourcePage, '', 4); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <footer class="site-preview-footer">
                    <strong><?= e($business['business_name']) ?></strong>
                    <span><?= e($serviceArea !== '' ? $serviceArea : 'Local service business') ?></span>
                    <span><?= e($phone ?: $email ?: 'Ready for local service requests') ?></span>
                </footer>
            </section>
        <?php endif; ?>
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
