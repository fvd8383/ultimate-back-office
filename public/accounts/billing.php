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

function accounts_billing_module_access_label($isActive): string
{
    if ($isActive === null || $isActive === '') {
        return 'No Module Linked';
    }

    return (int) $isActive === 1 ? 'Active' : 'Inactive';
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
        <p class="muted">View subscription records, fees, and active product access for your linked businesses. Billing records and module access are tracked separately.</p>
    </div>

    <div class="dashboard-card">
        <h2>Navigation</h2>
        <div class="button-row">
            <?= ui_button('Dashboard', 'dashboard.php', 'secondary') ?>
            <?= ui_button('Businesses', 'business.php', 'secondary') ?>
            <?= ui_button('Billing', 'billing.php', 'secondary') ?>
            <?= ui_button('Domains', 'domains.php', 'secondary') ?>
            <?= ui_button('Email', 'email.php', 'secondary') ?>
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
                <?php
                    $hasSubscription = (int) ($subscription['subscription_id'] ?? 0) > 0;
                    $is247spSubscription = (string) ($subscription['product_key'] ?? '') === '247sp';
                    $moduleAccessActive = (int) ($subscription['module_access_active'] ?? 0) === 1;
                    $moduleAccessLabel = accounts_billing_module_access_label($subscription['module_access_active'] ?? null);
                ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($subscription['business_name']) ?></h3>
                        <p class="muted">Business ID <?= e($subscription['business_id']) ?></p>
                        <?php if (in_array((string) $subscription['subscription_status'], ['pending_payment', 'past_due'], true)): ?>
                            <?= ui_alert('Billing status needs attention. Active module access is shown separately below.', 'warning') ?>
                        <?php endif; ?>
                        <?php if ($hasSubscription && $is247spSubscription && !$moduleAccessActive): ?>
                            <?= ui_alert('Subscription exists, but 24/7 Sales Partner module access is not active.', 'warning') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Current Plan</dt><dd><?= e($subscription['plan_name'] ?: 'No plan recorded') ?></dd></div>
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_billing_money($subscription['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_billing_money($subscription['setup_fee'])) ?></dd></div>
                        <div><dt>Subscription Status</dt><dd><?= ui_badge(accounts_billing_status($subscription['subscription_status']), in_array((string) $subscription['subscription_status'], ['past_due', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Module Access</dt><dd><?= ui_badge($moduleAccessLabel, $moduleAccessActive ? 'status' : 'role') ?></dd></div>
                        <div><dt>Start Date</dt><dd><?= e($subscription['started_at'] ?: 'Not started') ?></dd></div>
                    </div>
                    <p class="muted">Module access changes do not automatically cancel or deactivate billing subscription records.</p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
