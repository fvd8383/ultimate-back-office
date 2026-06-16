<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BillingFoundation.php';

Session::requireAuth('login.php');

$loadError = '';
$user = null;
$subscriptions = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $subscriptions = BillingFoundation::customerSubscriptionsForUser((int) $user['id']);
} catch (Throwable $exception) {
    $loadError = 'Billing information could not be loaded. Check the environment and database setup.';
}

function accounts_billing_money($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Not set';
    }

    return '$' . number_format((float) $amount, 2);
}

function accounts_billing_status(?string $status): string
{
    if ($status === null || $status === '') {
        return 'No Subscription';
    }

    return ucwords(str_replace('_', ' ', $status));
}

$pageTitle = 'Billing - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="dashboard-grid">
    <div class="dashboard-card dashboard-card--wide">
        <p class="eyebrow">Accounts</p>
        <h1>Billing</h1>
        <p class="muted">View plan, fee, and subscription status information for your linked businesses.</p>
    </div>

    <div class="dashboard-card">
        <h2>Navigation</h2>
        <div class="button-row">
            <?= ui_button('Dashboard', 'dashboard.php', 'secondary') ?>
            <?= ui_button('Businesses', 'business.php', 'secondary') ?>
            <?= ui_button('Domains', 'domains.php', 'secondary') ?>
            <?= ui_button('Logout', 'logout.php') ?>
        </div>
    </div>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif (count($subscriptions) === 0): ?>
    <section class="dashboard-card">
        <h2>No businesses found</h2>
        <p class="muted">Create a business before billing information can be shown.</p>
        <?= ui_button('Create Business', 'business-create.php') ?>
    </section>
<?php else: ?>
    <section class="dashboard-card">
        <h2>Current Billing</h2>
        <div class="business-list">
            <?php foreach ($subscriptions as $subscription): ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($subscription['business_name']) ?></h3>
                        <p class="muted">Business ID <?= e($subscription['business_id']) ?></p>
                        <?php if (in_array((string) $subscription['subscription_status'], ['pending_payment', 'past_due'], true)): ?>
                            <?= ui_alert('Billing status needs attention. 24/7 Sales Partner remains available during this manual billing sprint.', 'warning') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Current Plan</dt><dd><?= e($subscription['plan_name'] ?: 'No plan recorded') ?></dd></div>
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_billing_money($subscription['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_billing_money($subscription['setup_fee'])) ?></dd></div>
                        <div><dt>Subscription Status</dt><dd><?= ui_badge(accounts_billing_status($subscription['subscription_status']), in_array((string) $subscription['subscription_status'], ['past_due', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Start Date</dt><dd><?= e($subscription['started_at'] ?: 'Not started') ?></dd></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
