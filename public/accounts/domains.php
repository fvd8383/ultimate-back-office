<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';
require_once __DIR__ . '/../../private/classes/DomainAutomation.php';

Session::requireAuth('login.php');

$loadError = '';
$notice = '';
$error = '';
$user = null;
$domains = [];
$businesses = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $businessId = (int) ($_POST['business_id'] ?? 0);
        $business = BusinessFoundation::businessForUser($businessId, (int) $user['id']);
        if ($business === null) {
            throw new InvalidArgumentException('Choose a business connected to your account.');
        }

        DomainAutomation::createCustomerRequest(
            $businessId,
            (int) $user['id'],
            (string) ($_POST['domain_name'] ?? ''),
            (string) ($_POST['request_type'] ?? 'purchase')
        );
        $notice = 'Domain request saved.';
    }

    $domains = DomainAutomation::customerDomainsForUser((int) $user['id']);
    $businesses = DomainAutomation::customerBusinessesForUser((int) $user['id']);
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
    try {
        $domains = $user ? DomainAutomation::customerDomainsForUser((int) $user['id']) : [];
        $businesses = $user ? DomainAutomation::customerBusinessesForUser((int) $user['id']) : [];
    } catch (Throwable $loadException) {
        $loadError = 'Domain information could not be loaded. Check the environment and database setup.';
    }
} catch (Throwable $exception) {
    $loadError = 'Domain information could not be loaded. Check the environment and database setup.';
}

function accounts_domain_money($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Not set';
    }

    return '$' . number_format((float) $amount, 2);
}

function accounts_domain_badge_type(string $status): string
{
    return in_array($status, ['error', 'expired', 'cancelled'], true) ? 'role' : 'status';
}

$pageTitle = 'Domains - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('domains');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Accounts</p>
    <h1>Domains</h1>
    <p class="muted">Request a new domain, connect a domain you already own, and follow DNS, SSL, and launch progress for your linked businesses.</p>
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
    <section class="dashboard-card">
        <h2>Add Domain</h2>
        <?php if (count($businesses) === 0): ?>
            <p class="muted">Create a business before requesting a domain.</p>
        <?php else: ?>
            <form method="post" action="domains.php" class="business-form">
                <label>
                    <span>Business</span>
                    <select name="business_id" required>
                        <?php foreach ($businesses as $business): ?>
                            <option value="<?= e($business['id']) ?>"><?= e($business['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Domain</span>
                    <input type="text" name="domain_name" placeholder="example.com" required>
                </label>
                <label>
                    <span>Domain path</span>
                    <select name="request_type" required>
                        <option value="purchase">Request a new domain through 24/7 Sales Partner</option>
                        <option value="existing">Connect a domain I already own</option>
                    </select>
                </label>
                <?= ui_button('Save Domain Request', '', 'primary') ?>
            </form>
        <?php endif; ?>
    </section>

    <?php if (count($domains) === 0): ?>
        <section class="dashboard-card">
            <h2>No domain requests found</h2>
            <p class="muted">Complete 24/7 Sales Partner onboarding or add a domain request here to begin domain setup.</p>
        </section>
    <?php else: ?>
        <section class="dashboard-card">
            <h2>Domain Requests</h2>
            <div class="business-list">
                <?php foreach ($domains as $domain): ?>
                    <?php
                        $progress = is_array($domain['progress'] ?? null) ? $domain['progress'] : [];
                        $dnsRecords = DomainAutomation::dnsRecordsForRequest((int) $domain['id']);
                    ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e($domain['requested_domain']) ?></h3>
                            <p class="muted"><?= e($domain['business_name']) ?> · <?= e((string) ($domain['request_type'] ?? 'purchase') === 'existing' ? 'Existing domain' : 'New domain request') ?></p>
                            <?php if (($domain['domain_status'] ?? '') === 'ready'): ?>
                                <?= ui_alert('This domain is ready for website launch.', 'success') ?>
                            <?php elseif (($domain['domain_status'] ?? '') === 'live'): ?>
                                <?= ui_alert('Your website is live on this domain.', 'success') ?>
                            <?php endif; ?>
                            <p class="muted"><strong>Next action:</strong> <?= e(DomainAutomation::nextActionForDomain($domain)) ?></p>
                            <p class="muted"><strong>Estimated timing:</strong> <?= e(DomainAutomation::timingForDomain($domain)) ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Status</dt><dd><?= ui_badge(DomainAutomation::statusLabel($domain['domain_status']), accounts_domain_badge_type((string) $domain['domain_status'])) ?></dd></div>
                            <div><dt>Assigned Domain</dt><dd><?= e($domain['assigned_domain'] ?: 'Not assigned') ?></dd></div>
                            <div><dt>Registrar</dt><dd><?= e($domain['registrar'] ?: 'Not set') ?></dd></div>
                            <div><dt>DNS Status</dt><dd><?= e(DomainAutomation::statusLabel($domain['dns_status'] ?? 'not_started')) ?></dd></div>
                            <div><dt>SSL Status</dt><dd><?= e(DomainAutomation::statusLabel($domain['ssl_status'] ?? 'pending')) ?></dd></div>
                            <div><dt>Annual Cost</dt><dd><?= e(accounts_domain_money($domain['annual_cost'])) ?></dd></div>
                            <div><dt>Purchase Date</dt><dd><?= e($domain['purchase_date'] ?: 'Not purchased') ?></dd></div>
                            <div><dt>Expiration Date</dt><dd><?= e($domain['expiration_date'] ?: 'Not set') ?></dd></div>
                            <div><dt>Publish Status</dt><dd><?= e(DomainAutomation::statusLabel($domain['publish_status'] ?? 'draft')) ?></dd></div>
                        </div>

                        <?php if (count($progress) > 0): ?>
                            <div class="summary-list billing-summary-list">
                                <?php foreach ($progress as $item): ?>
                                    <div><dt><?= e($item['label']) ?></dt><dd><?= ui_badge(!empty($item['complete']) ? 'Complete' : 'In Progress', !empty($item['complete']) ? 'status' : 'role') ?></dd></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (count($dnsRecords) > 0): ?>
                            <div class="summary-list billing-summary-list">
                                <?php foreach ($dnsRecords as $record): ?>
                                    <div>
                                        <dt><?= e($record['record_type']) ?> <?= e($record['host']) ?></dt>
                                        <dd><?= e($record['value']) ?><?= $record['priority'] !== null ? ' · Priority ' . e($record['priority']) : '' ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
