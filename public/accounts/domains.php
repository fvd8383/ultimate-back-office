<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/DomainAutomation.php';

Session::requireAuth('login.php');

$loadError = '';
$user = null;
$domains = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $domains = DomainAutomation::customerDomainsForUser((int) $user['id']);
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
    <p class="muted">View requested domains, ownership status, purchase dates, and expiration dates for your linked businesses.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif (count($domains) === 0): ?>
    <section class="dashboard-card">
        <h2>No domain requests found</h2>
        <p class="muted">Complete 24/7 Sales Partner onboarding and choose a domain before domain status can be shown.</p>
    </section>
<?php else: ?>
    <section class="dashboard-card">
        <h2>Domain Requests</h2>
        <div class="business-list">
            <?php foreach ($domains as $domain): ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($domain['requested_domain']) ?></h3>
                        <p class="muted"><?= e($domain['business_name']) ?></p>
                        <?php if (($domain['publish_status'] ?? '') === 'ready'): ?>
                            <?= ui_alert('This domain is assigned and your website is ready for final review.', 'success') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Requested Domain</dt><dd><?= e($domain['requested_domain']) ?></dd></div>
                        <div><dt>Status</dt><dd><?= ui_badge(DomainAutomation::statusLabel($domain['domain_status']), in_array((string) $domain['domain_status'], ['expired', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Assigned Domain</dt><dd><?= e($domain['assigned_domain'] ?: 'Not assigned') ?></dd></div>
                        <div><dt>Registrar</dt><dd><?= e($domain['registrar'] ?: 'Not set') ?></dd></div>
                        <div><dt>Annual Cost</dt><dd><?= e(accounts_domain_money($domain['annual_cost'])) ?></dd></div>
                        <div><dt>Purchase Date</dt><dd><?= e($domain['purchase_date'] ?: 'Not purchased') ?></dd></div>
                        <div><dt>Expiration Date</dt><dd><?= e($domain['expiration_date'] ?: 'Not set') ?></dd></div>
                        <div><dt>Publish Status</dt><dd><?= e(DomainAutomation::statusLabel($domain['publish_status'] ?? 'draft')) ?></dd></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
