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

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$content = sp247_preview_content($currentPage);

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
            <nav class="step-nav step-nav--247sp" aria-label="Generated website pages">
                <?php foreach ($pages as $page): ?>
                    <a class="<?= $currentPage && (int) $currentPage['id'] === (int) $page['id'] ? 'is-active' : '' ?>" href="<?= e(sp247_preview_href($businessIdForLinks, (string) $page['slug'])) ?>">
                        <?= e($page['title']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section class="site-preview-shell">
                <header class="site-preview-header">
                    <strong><?= e($business['business_name']) ?></strong>
                    <span><?= e($business['phone']) ?></span>
                </header>

                <?php if (($currentPage['page_type'] ?? '') === 'home'): ?>
                    <section class="site-preview-hero">
                        <h2><?= e($content['headline'] ?? $business['business_name']) ?></h2>
                        <p><?= e($content['business_description'] ?? '') ?></p>
                        <p class="site-preview-cta"><?= e($content['call_to_action'] ?? '') ?></p>
                    </section>
                    <section class="site-preview-section">
                        <h3>Services</h3>
                        <div class="service-page-grid">
                            <?php foreach (($content['service_highlights'] ?? []) as $service): ?>
                                <article class="mini-card">
                                    <h3><?= e($service['name'] ?? '') ?></h3>
                                    <p class="muted"><?= e($service['description'] ?? '') ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="site-preview-section">
                        <h3>Service Area</h3>
                        <p><?= e($content['service_area'] ?? '') ?></p>
                        <?php if (($content['special_offer'] ?? '') !== ''): ?>
                            <p><strong>Special Offer:</strong> <?= e($content['special_offer']) ?></p>
                        <?php endif; ?>
                        <?php if (($content['financing_available'] ?? false) === true): ?>
                            <p><strong>Financing available.</strong></p>
                        <?php endif; ?>
                    </section>
                <?php elseif (($currentPage['page_type'] ?? '') === 'service'): ?>
                    <section class="site-preview-hero">
                        <h2><?= e($content['service_name'] ?? $currentPage['title']) ?></h2>
                        <p><?= e($content['service_description'] ?? '') ?></p>
                        <p class="site-preview-cta"><?= e($content['call_to_action'] ?? '') ?></p>
                    </section>
                <?php elseif (($currentPage['page_type'] ?? '') === 'about'): ?>
                    <section class="site-preview-hero">
                        <h2>About <?= e($business['business_name']) ?></h2>
                        <p><?= e($content['company_description'] ?? '') ?></p>
                    </section>
                    <section class="site-preview-section">
                        <h3>Experience</h3>
                        <p><?= e($content['years_in_business'] ?? '0') ?> years in business serving <?= e($content['service_area'] ?? '') ?>.</p>
                    </section>
                <?php else: ?>
                    <section class="site-preview-hero">
                        <h2>Contact <?= e($business['business_name']) ?></h2>
                        <p>Phone: <?= e($content['phone'] ?? '') ?></p>
                        <p>Email: <?= e($content['email'] ?? '') ?></p>
                    </section>
                    <section class="site-preview-section site-preview-form-placeholder">
                        <h3>Contact Form</h3>
                        <p><?= e($content['contact_form_placeholder'] ?? '') ?></p>
                        <div class="form-grid">
                            <label>Name<input disabled value=""></label>
                            <label>Email<input disabled value=""></label>
                            <label>Phone<input disabled value=""></label>
                        </div>
                        <label>Message<textarea disabled rows="4"></textarea></label>
                    </section>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
