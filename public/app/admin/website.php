<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$websiteId = (int) ($_POST['website_id'] ?? $_GET['website_id'] ?? 0);
$businessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
$notice = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($businessId <= 0) {
            throw new InvalidArgumentException('Business is required for website actions.');
        }

        if ($action === 'generate_website') {
            $website = AdminPortal::generateWebsiteForBusiness($businessId, (int) $context['user']['id'], false);
            header('Location: website.php?website_id=' . urlencode((string) $website['id']) . '&generated=1');
            exit;
        }

        if ($action === 'regenerate_website') {
            $website = AdminPortal::generateWebsiteForBusiness($businessId, (int) $context['user']['id'], true);
            header('Location: website.php?website_id=' . urlencode((string) $website['id']) . '&regenerated=1');
            exit;
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Website action could not be completed.';
}

if (isset($_GET['generated'])) {
    $notice = 'Website generated.';
}
if (isset($_GET['regenerated'])) {
    $notice = 'Website regenerated.';
}

$loadError = '';
$website = null;
$business = null;
$assets = [
    'logo_assigned' => false,
    'primary_color_assigned' => false,
    'secondary_color_assigned' => false,
    'image_count' => 0,
];

try {
    if ($websiteId > 0) {
        $website = AdminPortal::website($websiteId);
        if ($website !== null) {
            $businessId = (int) $website['business_id'];
            $assets = AdminPortal::websiteAssetsSummary((int) $website['id']);
        }
    }

    if ($businessId > 0) {
        $business = AdminPortal::business($businessId);
        if ($website === null) {
            $website = AdminPortal::websiteForBusiness($businessId);
            if ($website !== null) {
                $websiteId = (int) $website['id'];
                $assets = AdminPortal::websiteAssetsSummary((int) $website['id']);
            }
        }
    }
} catch (Throwable $exception) {
    $loadError = 'Website detail could not be loaded.';
}

$previewHref = $businessId > 0
    ? $context['app_base_url'] . '/247sp/site-preview.php?business_id=' . urlencode((string) $businessId)
    : '';

admin_begin('Website Detail', 'websites', $context);
?>
<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif ($business === null && $website === null): ?>
    <section class="empty-state">
        <h1>Website not found</h1>
        <p>No matching generated website exists for this request.</p>
    </section>
<?php else: ?>
    <section class="hero-panel">
        <p class="eyebrow">Website Detail</p>
        <h1><?= e($business['business_name'] ?? $website['business_name'] ?? 'Website') ?></h1>
        <p class="muted">Read-only generated website controls for internal staff.</p>
    </section>

    <section class="business-switcher">
        <h2>Website Information</h2>
        <div class="summary-list">
            <div><dt>Business</dt><dd><?= e($business['business_name'] ?? $website['business_name'] ?? 'Not set') ?></dd></div>
            <div><dt>Template</dt><dd><?= e($website['template_name'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Website Status</dt><dd><?= e(AdminPortal::statusLabel($website['status'] ?? ($business['website_status'] ?? 'not_started'))) ?></dd></div>
            <div><dt>Generated Date</dt><dd><?= e($website['generated_at'] ?? 'Not generated') ?></dd></div>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Website Assets Summary</h2>
        <div class="metrics-grid admin-assets-grid">
            <article><span>Logo Assigned</span><strong><?= e(admin_yes_no($assets['logo_assigned'])) ?></strong></article>
            <article><span>Primary Color Assigned</span><strong><?= e(admin_yes_no($assets['primary_color_assigned'])) ?></strong></article>
            <article><span>Secondary Color Assigned</span><strong><?= e(admin_yes_no($assets['secondary_color_assigned'])) ?></strong></article>
            <article><span>Image Count</span><strong><?= e($assets['image_count']) ?></strong></article>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Website Actions</h2>
        <div class="button-row">
            <?php if ($website === null): ?>
                <form method="post" action="website.php">
                    <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                    <input type="hidden" name="action" value="generate_website">
                    <?= ui_button('Generate Website', '', 'primary') ?>
                </form>
            <?php else: ?>
                <form method="post" action="website.php">
                    <input type="hidden" name="website_id" value="<?= e($website['id']) ?>">
                    <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                    <input type="hidden" name="action" value="regenerate_website">
                    <?= ui_button('Regenerate Website', '', 'primary') ?>
                </form>
                <?= ui_button('Open Preview', $previewHref, 'secondary') ?>
            <?php endif; ?>
            <?php if ($businessId > 0): ?>
                <?= ui_button('Open Business', 'business.php?business_id=' . urlencode((string) $businessId), 'secondary') ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
