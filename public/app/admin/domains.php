<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/DomainAutomation.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$notice = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        DomainAutomation::updateDomainRequest(
            (int) ($_POST['request_id'] ?? 0),
            (int) $context['user']['id'],
            (string) ($_POST['domain_status'] ?? ''),
            $_POST
        );
        $notice = 'Domain request updated.';
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Domain request could not be updated.';
}

$loadError = '';
$domains = [];

try {
    $domains = DomainAutomation::adminDomainRequests();
} catch (Throwable $exception) {
    $loadError = 'Domain requests could not be loaded. Run the Sprint 7 migration and check the database setup.';
}

function admin_domain_money($amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }

    return number_format((float) $amount, 2, '.', '');
}

admin_begin('Domains', 'domains', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Domains</p>
    <h1>Domain management</h1>
    <p class="muted">Track 24/7 Sales Partner domain requests, manual purchase details, assignment, ownership lifecycle, and publish-readiness. No registrar, DNS, SSL, email, payment, or publishing automation runs here.</p>
</section>

<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="business-switcher">
        <div class="admin-table admin-table--domains">
            <div class="admin-table__head">
                <span>Business</span><span>Domain</span><span>Status</span><span>Registrar</span><span>Expiration</span><span>Publish</span><span>Controls</span>
            </div>
            <?php foreach ($domains as $domain): ?>
                <div class="admin-table__row">
                    <span><a href="business.php?business_id=<?= e($domain['business_id']) ?>"><?= e($domain['business_name']) ?></a></span>
                    <span>
                        <strong><?= e($domain['requested_domain']) ?></strong>
                        <?php if (($domain['assigned_domain'] ?? '') !== ''): ?>
                            <small>Assigned: <?= e($domain['assigned_domain']) ?></small>
                        <?php endif; ?>
                    </span>
                    <span><?= ui_badge(DomainAutomation::statusLabel($domain['domain_status']), in_array((string) $domain['domain_status'], ['expired', 'cancelled'], true) ? 'role' : 'status') ?></span>
                    <span><?= e($domain['registrar'] ?: 'Not set') ?></span>
                    <span><?= e($domain['expiration_date'] ?: 'Not set') ?></span>
                    <span><?= e(DomainAutomation::statusLabel($domain['publish_status'] ?? 'draft')) ?></span>
                    <span>
                        <form method="post" action="domains.php" class="domain-status-form">
                            <input type="hidden" name="request_id" value="<?= e($domain['id']) ?>">
                            <label>
                                <span>Status</span>
                                <select name="domain_status" aria-label="Domain status for <?= e($domain['requested_domain']) ?>">
                                    <?php foreach (DomainAutomation::STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $domain['domain_status'] === $status ? 'selected' : '' ?>>
                                            <?= e(DomainAutomation::statusLabel($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Registrar</span>
                                <input type="text" name="registrar" value="<?= e($domain['registrar']) ?>" maxlength="100">
                            </label>
                            <label>
                                <span>Annual Cost</span>
                                <input type="number" name="annual_cost" value="<?= e(admin_domain_money($domain['annual_cost'])) ?>" min="0" step="0.01">
                            </label>
                            <label>
                                <span>Purchase Date</span>
                                <input type="date" name="purchase_date" value="<?= e($domain['purchase_date']) ?>">
                            </label>
                            <label>
                                <span>Expiration Date</span>
                                <input type="date" name="expiration_date" value="<?= e($domain['expiration_date']) ?>">
                            </label>
                            <?= ui_button('Save', '', 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (count($domains) === 0): ?>
                <p class="muted">No domain requests have been created yet.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
