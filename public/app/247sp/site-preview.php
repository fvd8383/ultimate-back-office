<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerator.php';
require_once __DIR__ . '/../../../private/classes/AdminPortal.php';
require_once __DIR__ . '/../../../private/classes/WebsiteManager.php';

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
$regenerated = isset($_GET['regenerated']);

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

function sp247_preview_tel_href(string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', $phone);

    return $digits !== '' ? 'tel:' . $digits : '#';
}

function sp247_preview_service_reasons(string $serviceName): array
{
    $serviceLabel = strtolower($serviceName !== '' ? $serviceName : 'service');

    return [
        'You need a clear assessment before a small ' . $serviceLabel . ' issue becomes a bigger problem.',
        'You want reliable help from a local business that explains the next step clearly.',
        'You are ready to schedule ' . $serviceLabel . ' and want the job handled professionally.',
    ];
}

function sp247_preview_style(array $branding): string
{
    $primary = (string) ($branding['primary_color'] ?? WebsiteManager::DEFAULT_PRIMARY_COLOR);
    $secondary = (string) ($branding['secondary_color'] ?? $primary);

    return '--preview-blue:' . $primary . ';--preview-blue-dark:' . $primary . ';--preview-accent:' . $secondary . ';';
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
        $content['hero_image_path'] = (string) ($branding['hero_image_path'] ?? ($content['hero_image_path'] ?? ''));
    }

    if ($pageType === 'about') {
        $content['about_heading'] = (string) (($overrides['about']['heading'] ?? '') ?: ($content['about_heading'] ?? ''));
        $content['company_description'] = (string) (($overrides['about']['description'] ?? '') ?: ($content['company_description'] ?? ''));
        $content['about_image_path'] = (string) ($branding['about_image_path'] ?? ($content['about_image_path'] ?? ''));
    }

    if ($pageType === 'contact') {
        $content['contact_heading'] = (string) (($overrides['contact']['heading'] ?? '') ?: ($content['contact_heading'] ?? ''));
        $content['contact_description'] = (string) (($overrides['contact']['description'] ?? '') ?: ($content['contact_description'] ?? ''));
    }

    if ($pageType === 'service' && $serviceNumber > 0) {
        $serviceKey = 'service_' . $serviceNumber;
        $content['service_name'] = (string) (($overrides[$serviceKey]['title'] ?? '') ?: ($content['service_name'] ?? ''));
        $content['service_description'] = (string) (($overrides[$serviceKey]['description'] ?? '') ?: ($content['service_description'] ?? ''));
        $content['hero_image_path'] = (string) ($branding['hero_image_path'] ?? ($content['hero_image_path'] ?? ''));
        $content['service_image_path'] = (string) ($serviceImages[$serviceNumber] ?? ($content['service_image_path'] ?? ''));
    }

    return $content;
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$sp247NavItems = [
    ['label' => '247SP Dashboard', 'href' => $businessIdForLinks > 0 ? 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks) : 'dashboard.php'],
    ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
    ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
    ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php', 'current' => true],
    ['label' => 'Lead Hub', 'href' => '../dashboard.php'],
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
$serviceArea = (string) (($content['service_area'] ?? '') ?: ($homeContent['service_area'] ?? '') ?: ($contactContent['service_area'] ?? ''));
$phone = (string) (($contactContent['phone'] ?? '') ?: ($business['phone'] ?? ''));
$email = (string) (($contactContent['email'] ?? '') ?: ($business['email'] ?? ''));
$phoneHref = sp247_preview_tel_href($phone);
$currentPageType = (string) ($currentPage['page_type'] ?? '');
$heroImage = (string) (($content['hero_image_path'] ?? '') ?: ($homeContent['hero_image_path'] ?? ''));
if (!in_array($currentPageType, ['home', 'service'], true)) {
    $heroImage = '';
}
$aboutImage = (string) ($content['about_image_path'] ?? '');
$serviceImage = (string) ($content['service_image_path'] ?? '');
$primaryCta = (string) (($content['call_to_action'] ?? '') ?: ($homeContent['call_to_action'] ?? 'Call ' . ($phone ?: 'Now')));

$pageTitle = '247SP Website Preview - Ultimate Back Office';
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
            <p class="eyebrow">Private website preview</p>
            <h1><?= $business ? e($business['business_name']) : '247SP preview' ?></h1>
            <p class="muted">Review the generated website pages and page-specific content.</p>
        </section>

        <?php if ($regenerated): ?>
            <?= ui_alert('Website preview regenerated from the latest website manager settings.', 'success') ?>
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
                <h2>Website not generated</h2>
                <p>Complete onboarding and generate the website before opening the private preview.</p>
                <div class="button-row">
                    <?= ui_button('Back to dashboard', 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                </div>
            </section>
        <?php else: ?>
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
                        <?php $navServiceNumber = 0; ?>
                        <?php foreach ($pages as $page): ?>
                            <?php
                            $navTitle = (string) $page['title'];
                            if (($page['page_type'] ?? '') === 'service') {
                                $navServiceNumber++;
                                $navContent = sp247_preview_content($page);
                                $storedNavServiceNumber = (int) ($navContent['service_number'] ?? 0);
                                $effectiveNavServiceNumber = $storedNavServiceNumber > 0 ? $storedNavServiceNumber : $navServiceNumber;
                                $navTitle = (string) (($contentOverrides['service_' . $effectiveNavServiceNumber]['title'] ?? '') ?: $navTitle);
                            }
                            ?>
                            <a class="<?= $currentPage && (int) $currentPage['id'] === (int) $page['id'] ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, (string) $page['slug'])) ?>">
                                <?= e($navTitle) ?>
                            </a>
                        <?php endforeach; ?>
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
                            <a class="site-preview-button" href="<?= e($phoneHref) ?>"><?= e($primaryCta) ?></a>
                            <a class="site-preview-button site-preview-button--secondary" href="<?= e(sp247_preview_href($businessIdForLinks, 'contact')) ?>">Request Service</a>
                        </div>
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
                    $serviceReasons = sp247_preview_service_reasons($serviceName);
                    ?>
                    <section class="site-preview-section site-preview-feature">
                        <div>
                            <p class="site-preview-kicker">What is included</p>
                            <h3><?= e($serviceName) ?> made straightforward</h3>
                            <p><?= e($business['business_name']) ?> helps customers understand the issue, choose a practical next step, and get service scheduled without confusion.</p>
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
                            <h3>Why choose <?= e($business['business_name']) ?> for <?= e($serviceName) ?></h3>
                        </div>
                        <div class="site-preview-trust-grid">
                            <article>
                                <strong>Local</strong>
                                <span><?= e($serviceArea !== '' ? 'Serving ' . $serviceArea : 'Service near you') ?></span>
                            </article>
                            <article>
                                <strong>Clear</strong>
                                <span>Simple communication before work begins</span>
                            </article>
                            <article>
                                <strong>Ready</strong>
                                <span>Call or request service when you need help</span>
                            </article>
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
                        <article>
                            <strong><?= e((string) ($content['years_in_business'] ?? '0')) ?></strong>
                            <span>Years in business</span>
                        </article>
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
                            <div class="site-preview-form-placeholder">
                                <div class="form-grid">
                                    <label>Name<input disabled value=""></label>
                                    <label>Phone<input disabled value=""></label>
                                </div>
                                <label>Email<input disabled value=""></label>
                                <label>How can we help?<textarea disabled rows="5"></textarea></label>
                            </div>
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
                            <article>
                                <strong><?= e((string) ($aboutContent['years_in_business'] ?? 'Local')) ?></strong>
                                <span><?= isset($aboutContent['years_in_business']) ? 'Years in business' : 'Local experience' ?></span>
                            </article>
                            <article>
                                <strong><?= e(count($previewServices)) ?></strong>
                                <span>Core services available</span>
                            </article>
                            <article>
                                <strong><?= ($homeContent['financing_available'] ?? false) === true ? 'Yes' : 'Clear' ?></strong>
                                <span><?= ($homeContent['financing_available'] ?? false) === true ? 'Financing available' : 'Communication from request to service' ?></span>
                            </article>
                        </div>
                    </section>

                    <section class="site-preview-section site-preview-area">
                        <div>
                            <p class="site-preview-kicker">Service area</p>
                            <h3><?= e($serviceArea !== '' ? $serviceArea : 'Local service area') ?></h3>
                            <p><?= e($business['business_name']) ?> provides reliable local service with a simple way to call, request help, and choose the service you need.</p>
                        </div>
                        <a class="site-preview-button site-preview-button--secondary" href="<?= e(sp247_preview_href($businessIdForLinks, 'contact')) ?>">Get In Touch</a>
                    </section>

                    <section class="site-preview-section site-preview-contact">
                        <div>
                            <p class="site-preview-kicker">Contact</p>
                            <h3>Ready to schedule service?</h3>
                            <p>Call now or send a request through the contact form placeholder.</p>
                        </div>
                        <div class="site-preview-contact-card">
                            <a href="<?= e($phoneHref) ?>"><?= e($phone ?: 'Phone pending') ?></a>
                            <span><?= e($email ?: 'Email pending') ?></span>
                            <div class="site-preview-form-placeholder">
                                <div class="form-grid">
                                    <label>Name<input disabled value=""></label>
                                    <label>Phone<input disabled value=""></label>
                                </div>
                                <label>How can we help?<textarea disabled rows="4"></textarea></label>
                            </div>
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
    </div>
</section>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
