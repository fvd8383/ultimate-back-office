<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/Csrf.php';
require_once __DIR__ . '/../../../private/classes/SiteAdminWorkspace.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$actingUserId = (int) $context['user']['id'];
try {
    SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
} catch (SiteServiceException) {
    admin_access_denied($context);
    exit;
}

$error = '';
$form = [
    'purpose' => (string) ($_POST['purpose'] ?? '247sp'),
    'business_id' => (string) ($_POST['business_id'] ?? ''),
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::requireValid($_POST['csrf_token'] ?? null, SITE_PLATFORM_CSRF_SCOPE);
        if ((string) ($_POST['action'] ?? '') !== 'create_site') {
            throw new InvalidArgumentException('The requested Site Platform action is invalid.');
        }

        $purpose = (string) ($_POST['purpose'] ?? '');
        $businessId = $purpose === '247sp' ? (int) ($_POST['business_id'] ?? 0) : null;
        $created = SiteManager::createSite($actingUserId, $purpose, $businessId, null);
        Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
        header(
            'Location: site.php?site_id=' . urlencode((string) $created['site_id']) . '&created_site=1',
            true,
            303
        );
        exit;
    }
} catch (SiteServiceException | CsrfException | InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'The Site Platform action could not be completed.';
}

$purposeFilter = (string) ($_GET['purpose'] ?? '');
$lifecycleFilter = (string) ($_GET['lifecycle_status'] ?? '');
$sites = [];
$eligibleBusinesses = [];
$loadError = '';
try {
    $sites = SiteAdminWorkspace::listSites($actingUserId, [
        'purpose' => $purposeFilter,
        'lifecycle_status' => $lifecycleFilter,
    ]);
    $eligibleBusinesses = SiteAdminWorkspace::eligible247spBusinesses($actingUserId);
} catch (SiteServiceException | InvalidArgumentException $exception) {
    $loadError = $exception->getMessage();
} catch (Throwable $exception) {
    $loadError = 'Site Platform information could not be loaded.';
}

admin_begin('Site Platform', 'sites', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Site Platform</p>
    <h1>Generic site workspace</h1>
    <p class="muted">Create and inspect dormant generic sites. The legacy 247SP website runtime and Website Manager remain authoritative.</p>
</section>

<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>
<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php endif; ?>

<section class="business-switcher">
    <h2>Create generic site</h2>
    <form method="post" action="sites.php" class="form-stack" id="site-platform-create-form">
        <?= Csrf::input(SITE_PLATFORM_CSRF_SCOPE) ?>
        <input type="hidden" name="action" value="create_site">
        <label>Purpose
            <select name="purpose" id="site-platform-purpose" required>
                <?php foreach (SiteManager::PURPOSES as $purpose): ?>
                    <option value="<?= e($purpose) ?>" <?= $form['purpose'] === $purpose ? 'selected' : '' ?>><?= e(AdminPortal::statusLabel($purpose)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="site-platform-business-field">Eligible 247SP business
            <select name="business_id">
                <option value="">Choose an eligible business</option>
                <?php foreach ($eligibleBusinesses as $business): ?>
                    <option value="<?= e($business['id']) ?>" <?= $form['business_id'] === (string) $business['id'] ? 'selected' : '' ?>>
                        <?= e($business['business_name']) ?><?= $business['email'] !== '' ? ' · ' . e($business['email']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <span class="form-help">EMD and internal demo sites do not fabricate a customer business association.</span>
        <?= ui_button('Create Site') ?>
    </form>
</section>

<section class="business-switcher">
    <h2>Generic sites</h2>
    <form method="get" action="sites.php" class="form-stack">
        <label>Purpose filter
            <select name="purpose">
                <option value="">All purposes</option>
                <?php foreach (SiteManager::PURPOSES as $purpose): ?>
                    <option value="<?= e($purpose) ?>" <?= $purposeFilter === $purpose ? 'selected' : '' ?>><?= e(AdminPortal::statusLabel($purpose)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Lifecycle filter
            <select name="lifecycle_status">
                <option value="">All lifecycle states</option>
                <?php foreach (['draft', 'demo', 'pending_customer', 'pending_internal_review', 'approved', 'active', 'suspended', 'cancellation_pending', 'conversion_pending', 'archived'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $lifecycleFilter === $status ? 'selected' : '' ?>><?= e(AdminPortal::statusLabel($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?= ui_button('Apply Filters', '', 'secondary') ?>
    </form>

    <div class="admin-table admin-table--websites">
        <div class="admin-table__head">
            <span>Site</span><span>Purpose</span><span>Business</span><span>Lifecycle</span><span>Workflow</span><span></span>
        </div>
        <?php foreach ($sites as $site): ?>
            <div class="admin-table__row">
                <span>#<?= e($site['id']) ?><br><small><?= e($site['site_key']) ?></small></span>
                <span><?= e(AdminPortal::statusLabel($site['purpose'])) ?></span>
                <span><?= e($site['business_name'] ?: 'No customer association') ?></span>
                <span><?= ui_badge(AdminPortal::statusLabel($site['lifecycle_status']), 'status') ?></span>
                <span><?= e($site['revision_count']) ?> revisions · <?= e($site['brief_count']) ?> briefs<?= $site['mutable_revision_id'] !== null ? ' · Draft #' . e($site['mutable_revision_id']) : '' ?></span>
                <span><a href="site.php?site_id=<?= e($site['id']) ?>">Open</a></span>
            </div>
        <?php endforeach; ?>
        <?php if ($sites === []): ?>
            <p class="muted">No generic sites match these filters.</p>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var purpose = document.getElementById('site-platform-purpose');
    var businessField = document.getElementById('site-platform-business-field');
    if (!purpose || !businessField) return;
    function syncBusiness() {
        businessField.hidden = purpose.value !== '247sp';
        var select = businessField.querySelector('select');
        if (select) select.required = purpose.value === '247sp';
    }
    purpose.addEventListener('change', syncBusiness);
    syncBusiness();
}());
</script>
<?php admin_end(); ?>
