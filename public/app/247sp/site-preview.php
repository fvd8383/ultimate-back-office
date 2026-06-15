<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
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
$website = null;
$pages = [];
$currentPage = null;
$loadError = '';
$accessDenied = false;

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_GET['business_id'] ?? 0);
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId > 0 ? $requestedBusinessId : null, (int) $user['id']);

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);

        if (!$accessDenied) {
            $website = SiteGenerator::websiteForBusiness($businessId);

            if ($website !== null) {
                $pages = SiteGenerator::pagesForWebsite((int) $website['id']);
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

function sp247_preview_services(array $pages): array
{
    $services = [];

    foreach ($pages as $page) {
        if (($page['page_type'] ?? '') !== 'service') {
            continue;
        }

        $content = sp247_preview_content($page);
        $services[] = [
            'title' => (string) ($content['service_name'] ?? $page['title']),
            'description' => (string) ($content['service_description'] ?? ''),
            'slug' => (string) $page['slug'],
        ];
    }

    return $services;
}

function sp247_preview_tel_href(string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', $phone);

    return $digits !== '' ? 'tel:' . $digits : '#';
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$content = sp247_preview_content($currentPage);
$homeContent = sp247_preview_page_content($pages, 'home');
$aboutContent = sp247_preview_page_content($pages, 'about');
$contactContent = sp247_preview_page_content($pages, 'contact');
$previewServices = sp247_preview_services($pages);
$serviceArea = (string) (($content['service_area'] ?? '') ?: ($homeContent['service_area'] ?? '') ?: ($contactContent['service_area'] ?? ''));
$phone = (string) (($contactContent['phone'] ?? '') ?: ($business['phone'] ?? ''));
$email = (string) (($contactContent['email'] ?? '') ?: ($business['email'] ?? ''));
$phoneHref = sp247_preview_tel_href($phone);
$currentPageType = (string) ($currentPage['page_type'] ?? '');

$pageTitle = '247SP Website Preview - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
?>
<section class="app-layout">
    <?= ui_sidebar('24/7 Sales Partner', [
        ['label' => '247SP Dashboard', 'href' => $businessIdForLinks > 0 ? 'dashboard.php?business_id=' . urlencode((string) $businessIdForLinks) : 'dashboard.php'],
        ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
        ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
        ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php', 'current' => true],
        ['label' => 'Lead Hub', 'href' => '../dashboard.php'],
    ], '24/7 Sales Partner') ?>

    <div class="app-content">
        <section class="hero-panel product-hero product-hero--247sp">
            <p class="eyebrow">Private website preview</p>
            <h1><?= $business ? e($business['business_name']) : '247SP preview' ?></h1>
            <p class="muted">Review generated pages before any future publishing workflow.</p>
        </section>

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
            <section class="site-preview-shell">
                <header class="site-preview-header">
                    <a class="site-preview-brand" href="<?= e(sp247_preview_href($businessIdForLinks, 'home')) ?>">
                        <span><?= e(substr((string) $business['business_name'], 0, 1)) ?></span>
                        <strong><?= e($business['business_name']) ?></strong>
                    </a>
                    <nav class="site-preview-nav" aria-label="Preview website navigation">
                        <?php foreach ($pages as $page): ?>
                            <a class="<?= $currentPage && (int) $currentPage['id'] === (int) $page['id'] ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, (string) $page['slug'])) ?>">
                                <?= e($page['title']) ?>
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
                            <h2>About <?= e($business['business_name']) ?></h2>
                            <p><?= e($content['company_description'] ?? '') ?></p>
                        <?php elseif ($currentPageType === 'contact'): ?>
                            <h2>Contact <?= e($business['business_name']) ?></h2>
                            <p>Tell us what you need and we will help you take the next step.</p>
                        <?php else: ?>
                            <h2><?= e($content['headline'] ?? $business['business_name']) ?></h2>
                            <p><?= e($content['business_description'] ?? '') ?></p>
                        <?php endif; ?>
                        <div class="site-preview-actions">
                            <a class="site-preview-button" href="<?= e($phoneHref) ?>">Call <?= e($phone ?: 'Now') ?></a>
                            <a class="site-preview-button site-preview-button--secondary" href="<?= e(sp247_preview_href($businessIdForLinks, 'contact')) ?>">Request Service</a>
                        </div>
                    </div>
                    <aside class="site-preview-hero-card">
                        <strong>Ready to help</strong>
                        <span><?= e($phone ?: 'Phone pending') ?></span>
                        <span><?= e($email ?: 'Email pending') ?></span>
                        <?php if (($homeContent['special_offer'] ?? '') !== ''): ?>
                            <p><?= e($homeContent['special_offer']) ?></p>
                        <?php endif; ?>
                    </aside>
                </section>

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
