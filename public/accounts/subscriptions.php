<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BillingFoundation.php';

Session::requireAuth('login.php');

$loadError = '';
$user = null;
$subscriptions = [];
$availablePlans = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $subscriptions = BillingFoundation::customerSubscriptionsForUser((int) $user['id']);
    $availablePlans = BillingFoundation::activePlans();
} catch (Throwable $exception) {
    $loadError = 'Subscriptions could not be loaded. Check the environment and database setup.';
}

$productSubscriptions = array_values(array_filter($subscriptions, static function (array $subscription): bool {
    return (int) ($subscription['subscription_id'] ?? 0) > 0;
}));

function accounts_subscription_money($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Not set';
    }

    return '$' . number_format((float) $amount, 2);
}

function accounts_subscription_status(?string $status): string
{
    if ($status === null || $status === '') {
        return 'No subscription';
    }

    return ucwords(str_replace('_', ' ', $status));
}

function accounts_subscription_access_label($isActive): string
{
    if ($isActive === null || $isActive === '') {
        return 'No product access linked';
    }

    return (int) $isActive === 1 ? 'Active' : 'Inactive';
}

function accounts_subscription_plan_available(array $plan, array $subscriptions): bool
{
    foreach ($subscriptions as $subscription) {
        if ((string) ($subscription['product_key'] ?? '') === (string) ($plan['product_key'] ?? '')) {
            return false;
        }
    }

    return true;
}

$pageTitle = 'Subscriptions - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('subscriptions');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Accounts</p>
    <h1>Subscriptions</h1>
    <p class="muted">Manage the products and services connected to your account.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif (count($productSubscriptions) === 0): ?>
    <section class="dashboard-card">
        <h2>No active products yet</h2>
        <p class="muted">Create a business and select a product before subscription details can be shown.</p>
        <?= ui_button('Create Business', 'business-create.php') ?>
    </section>
<?php else: ?>
    <section class="dashboard-card">
        <h2>Current Products</h2>
        <div class="business-list">
            <?php foreach ($productSubscriptions as $subscription): ?>
                <?php
                    $accessActive = (int) ($subscription['module_access_active'] ?? 0) === 1;
                    $accessLabel = accounts_subscription_access_label($subscription['module_access_active'] ?? null);
                    $status = (string) ($subscription['subscription_status'] ?? '');
                ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($subscription['plan_name'] ?: 'Product subscription') ?></h3>
                        <p class="muted"><?= e($subscription['business_name']) ?></p>
                        <?php if (in_array($status, ['pending_payment', 'past_due'], true)): ?>
                            <?= ui_alert('This subscription needs billing attention before everything is fully ready.', 'warning') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Plan</dt><dd><?= e($subscription['plan_name'] ?: 'No plan recorded') ?></dd></div>
                        <div><dt>Subscription Status</dt><dd><?= ui_badge(accounts_subscription_status($status), in_array($status, ['past_due', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_subscription_money($subscription['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_subscription_money($subscription['setup_fee'])) ?></dd></div>
                        <div><dt>Product Access</dt><dd><?= ui_badge($accessLabel, $accessActive ? 'status' : 'role') ?></dd></div>
                        <div><dt>Start Date</dt><dd><?= e($subscription['started_at'] ?: 'Not started') ?></dd></div>
                    </div>
                    <p class="muted">Product changes, upgrades, and cancellations are handled with support.</p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($loadError === '' && count($availablePlans) > 0): ?>
    <section class="dashboard-card">
        <h2>Available Products</h2>
        <p class="muted">Review products that can be connected to your account.</p>
        <div class="business-list">
            <?php foreach ($availablePlans as $plan): ?>
                <?php $isAvailable = accounts_subscription_plan_available($plan, $productSubscriptions); ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($plan['name']) ?></h3>
                        <p class="muted"><?= $isAvailable ? 'Available to discuss with support.' : 'Already connected to one of your businesses.' ?></p>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_subscription_money($plan['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_subscription_money($plan['setup_fee'])) ?></dd></div>
                        <div><dt>Status</dt><dd><?= ui_badge($isAvailable ? 'Available' : 'Connected', 'status') ?></dd></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Manage Subscription</h2>
    <p class="muted">Upgrade, change, and cancellation requests can be reviewed with support. Payment setup and product changes are handled directly with the support team.</p>
</section>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
