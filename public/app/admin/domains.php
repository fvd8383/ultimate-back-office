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
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $adminUserId = (int) $context['user']['id'];
        $action = (string) ($_POST['action'] ?? 'save_status');

        if ($action === 'check_availability') {
            DomainAutomation::checkAvailability($requestId, $adminUserId);
            $notice = 'Domain availability checked.';
        } elseif ($action === 'purchase_domain') {
            DomainAutomation::purchaseDomain($requestId, $adminUserId);
            $notice = 'Domain purchase submitted.';
        } elseif ($action === 'sync_dns') {
            DomainAutomation::syncDnsRecords($requestId, $adminUserId);
            $notice = 'DNS records submitted to the registrar.';
        } elseif ($action === 'verify_dns') {
            DomainAutomation::verifyDns($requestId, $adminUserId);
            $notice = 'DNS verification checked.';
        } elseif ($action === 'refresh_status') {
            DomainAutomation::refreshRegistrarStatus($requestId, $adminUserId);
            $notice = 'Registrar status refreshed.';
        } elseif ($action === 'update_ssl') {
            DomainAutomation::updateSslStatus($requestId, $adminUserId, (string) ($_POST['ssl_status'] ?? 'pending'));
            $notice = 'SSL status updated.';
        } elseif ($action === 'mark_live') {
            DomainAutomation::markLive($requestId, $adminUserId);
            $notice = 'Domain marked live.';
        } else {
            DomainAutomation::updateDomainRequest(
                $requestId,
                $adminUserId,
                (string) ($_POST['domain_status'] ?? ''),
                $_POST
            );
            $notice = 'Domain request updated.';
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Domain action could not be completed.';

    try {
        if ((bool) Database::config('APP_DEBUG', false)) {
            $error .= ' Detail: ' . $exception->getMessage();
        }
    } catch (Throwable $configException) {
        // Keep the customer-safe admin error if config cannot be loaded.
    }
}

$loadError = '';
$domains = [];

try {
    $domains = DomainAutomation::adminDomainRequests();
} catch (Throwable $exception) {
    $loadError = 'Domain requests could not be loaded. Run the domain migrations and check the database setup.';
}

function admin_domain_money($amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }

    return number_format((float) $amount, 2, '.', '');
}

function admin_domain_badge_type(string $status): string
{
    return in_array($status, ['error', 'expired', 'cancelled'], true) ? 'role' : 'status';
}

admin_begin('Domains', 'domains', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Domains</p>
    <h1>Domain management</h1>
    <p class="muted">Manage 24/7 Sales Partner domain requests, registrar workflow, DNS records, SSL status, launch readiness, and domain history.</p>
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
                <span>Business</span><span>Domain</span><span>Status</span><span>DNS / SSL</span><span>Registrar</span><span>Controls</span>
            </div>
            <?php foreach ($domains as $domain): ?>
                <?php
                    $events = DomainAutomation::domainEvents((int) $domain['id']);
                    $records = DomainAutomation::dnsRecordsForRequest((int) $domain['id']);
                    $progress = is_array($domain['progress'] ?? null) ? $domain['progress'] : [];
                    $response = trim((string) ($domain['registrar_response_json'] ?? ''));
                    $responsePreview = $response !== '' ? substr($response, 0, 500) : '';
                ?>
                <div class="admin-table__row">
                    <span><a href="business.php?business_id=<?= e($domain['business_id']) ?>"><?= e($domain['business_name']) ?></a></span>
                    <span>
                        <strong><?= e($domain['requested_domain']) ?></strong>
                        <small><?= e((string) ($domain['request_type'] ?? 'purchase') === 'existing' ? 'Customer-owned domain' : '247SP purchase request') ?></small>
                        <?php if (($domain['assigned_domain'] ?? '') !== ''): ?>
                            <small>Assigned: <?= e($domain['assigned_domain']) ?></small>
                        <?php endif; ?>
                        <small>Next: <?= e(DomainAutomation::nextActionForDomain($domain)) ?></small>
                    </span>
                    <span>
                        <?= ui_badge(DomainAutomation::statusLabel($domain['domain_status']), admin_domain_badge_type((string) $domain['domain_status'])) ?>
                        <?php if (($domain['last_error'] ?? '') !== ''): ?>
                            <small class="admin-table__cell-note"><?= e($domain['last_error']) ?></small>
                        <?php endif; ?>
                    </span>
                    <span>
                        <small class="admin-table__cell-note">DNS: <?= e(DomainAutomation::statusLabel($domain['dns_status'] ?? 'not_started')) ?></small><br>
                        <small class="admin-table__cell-note">SSL: <?= e(DomainAutomation::statusLabel($domain['ssl_status'] ?? 'pending')) ?></small><br>
                        <small class="admin-table__cell-note">Publish: <?= e(DomainAutomation::statusLabel($domain['publish_status'] ?? 'draft')) ?></small>
                    </span>
                    <span>
                        <small class="admin-table__cell-note">Registrar: <?= e($domain['registrar'] ?: 'Default') ?></small><br>
                        <small class="admin-table__cell-note">Domain ID: <?= e($domain['registrar_domain_id'] ?: 'Not recorded') ?></small><br>
                        <small class="admin-table__cell-note">Order: <?= e($domain['registrar_order_id'] ?: 'Not recorded') ?></small><br>
                        <small class="admin-table__cell-note">Expires: <?= e($domain['expiration_date'] ?: 'Not set') ?></small>
                    </span>
                    <span>
                        <form method="post" action="domains.php" class="domain-status-form">
                            <input type="hidden" name="request_id" value="<?= e($domain['id']) ?>">
                            <input type="hidden" name="action" value="save_status">
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
                                <span>Type</span>
                                <select name="request_type">
                                    <option value="purchase" <?= ($domain['request_type'] ?? '') === 'purchase' ? 'selected' : '' ?>>247SP purchase</option>
                                    <option value="existing" <?= ($domain['request_type'] ?? '') === 'existing' ? 'selected' : '' ?>>Customer owned</option>
                                </select>
                            </label>
                            <label>
                                <span>Registrar</span>
                                <input type="text" name="registrar" value="<?= e($domain['registrar']) ?>" maxlength="100" placeholder="namecheap">
                            </label>
                            <label>
                                <span>DNS Status</span>
                                <select name="dns_status">
                                    <?php foreach (DomainAutomation::DNS_STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>" <?= ($domain['dns_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(DomainAutomation::statusLabel($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>SSL Status</span>
                                <select name="ssl_status">
                                    <?php foreach (DomainAutomation::SSL_STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>" <?= ($domain['ssl_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(DomainAutomation::statusLabel($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                            <label>
                                <span>Next Action</span>
                                <input type="text" name="next_action" value="<?= e($domain['next_action']) ?>" maxlength="255">
                            </label>
                            <?= ui_button('Save', '', 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>

                        <form method="post" action="domains.php" class="button-row">
                            <input type="hidden" name="request_id" value="<?= e($domain['id']) ?>">
                            <?= ui_button('Check Availability', '', 'secondary', ['name' => 'action', 'value' => 'check_availability', 'class' => 'ubo-button--compact']) ?>
                            <?= ui_button('Purchase Domain', '', 'primary', ['name' => 'action', 'value' => 'purchase_domain', 'class' => 'ubo-button--compact']) ?>
                            <?= ui_button('Refresh Status', '', 'secondary', ['name' => 'action', 'value' => 'refresh_status', 'class' => 'ubo-button--compact']) ?>
                            <?= ui_button('Sync DNS', '', 'secondary', ['name' => 'action', 'value' => 'sync_dns', 'class' => 'ubo-button--compact']) ?>
                            <?= ui_button('Verify DNS', '', 'secondary', ['name' => 'action', 'value' => 'verify_dns', 'class' => 'ubo-button--compact']) ?>
                            <?= ui_button('Mark Live', '', 'secondary', ['name' => 'action', 'value' => 'mark_live', 'class' => 'ubo-button--compact']) ?>
                        </form>

                        <form method="post" action="domains.php" class="button-row">
                            <input type="hidden" name="request_id" value="<?= e($domain['id']) ?>">
                            <select name="ssl_status" aria-label="SSL status update for <?= e($domain['requested_domain']) ?>">
                                <?php foreach (DomainAutomation::SSL_STATUSES as $status): ?>
                                    <option value="<?= e($status) ?>" <?= ($domain['ssl_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(DomainAutomation::statusLabel($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= ui_button('Update SSL', '', 'secondary', ['name' => 'action', 'value' => 'update_ssl', 'class' => 'ubo-button--compact']) ?>
                        </form>
                    </span>
                </div>
                <div class="admin-table__row">
                    <span>Progress</span>
                    <span>
                        <?php foreach ($progress as $item): ?>
                            <?= ui_badge($item['label'] . ': ' . (!empty($item['complete']) ? 'Complete' : 'Pending'), !empty($item['complete']) ? 'status' : 'role') ?>
                        <?php endforeach; ?>
                    </span>
                    <span>DNS Records</span>
                    <span>
                        <?php if (count($records) === 0): ?>
                            <small class="admin-table__cell-note">No planned records yet. Save or sync this request to generate records.</small>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <small class="admin-table__cell-note">
                                    <?= e($record['record_type']) ?> <?= e($record['host']) ?> → <?= e($record['value']) ?>
                                    <?= $record['priority'] !== null ? ' priority ' . e($record['priority']) : '' ?>
                                    · <?= e(DomainAutomation::statusLabel($record['status'])) ?>
                                </small><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </span>
                    <span></span>
                    <span></span>
                </div>
                <div class="admin-table__row">
                    <span>History</span>
                    <span>
                        <?php if (count($events) === 0): ?>
                            <small class="admin-table__cell-note">No domain events recorded yet.</small>
                        <?php else: ?>
                            <?php foreach (array_slice($events, 0, 4) as $event): ?>
                                <small class="admin-table__cell-note">
                                    <?= e($event['created_at']) ?> · <?= e(DomainAutomation::statusLabel($event['event_type'])) ?> · <?= e($event['message']) ?>
                                </small><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </span>
                    <span>Registrar Response</span>
                    <span>
                        <small class="admin-table__cell-note"><?= e($responsePreview !== '' ? $responsePreview : 'No registrar response recorded yet.') ?></small>
                    </span>
                    <span></span>
                    <span></span>
                </div>
            <?php endforeach; ?>
            <?php if (count($domains) === 0): ?>
                <p class="muted">No domain requests have been created yet.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
