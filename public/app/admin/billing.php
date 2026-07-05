<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/BillingFoundation.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$notice = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        BillingFoundation::setSubscriptionStatus(
            (int) ($_POST['subscription_id'] ?? 0),
            (int) $context['user']['id'],
            (string) ($_POST['status'] ?? '')
        );
        $notice = 'Subscription status updated.';
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Subscription status could not be updated.';
}

$loadError = '';
$metrics = [];
$subscriptions = [];

try {
    $metrics = BillingFoundation::adminMetrics();
    $subscriptions = BillingFoundation::adminSubscriptions();
} catch (Throwable $exception) {
    $loadError = 'Billing data could not be loaded. Run the Sprint 6 migration and check the database setup.';
}

function billing_money($amount): string
{
    return '$' . number_format((float) $amount, 2);
}

function billing_module_access_label($isActive): string
{
    return (int) $isActive === 1 ? 'Active' : 'Inactive';
}

admin_begin('Billing', 'billing', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Billing</p>
    <h1>Subscription management</h1>
    <p class="muted">Track 24/7 Sales Partner plans, Stripe billing references, subscription status, active module access, and recurring revenue.</p>
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
    <section class="metrics-grid admin-metrics" aria-label="Billing metrics">
        <article><span>Total Active Subscriptions</span><strong><?= e($metrics['active_subscriptions']) ?></strong></article>
        <article><span>Total Trial Accounts</span><strong><?= e($metrics['trial_accounts']) ?></strong></article>
        <article><span>Total Past Due Accounts</span><strong><?= e($metrics['past_due_accounts']) ?></strong></article>
        <article><span>Monthly Recurring Revenue</span><strong><?= e(billing_money($metrics['mrr'])) ?></strong></article>
    </section>

    <section class="business-switcher">
        <div class="admin-table admin-table--billing">
            <div class="admin-table__head">
                <span>Business</span><span>Plan</span><span>Status</span><span>Module Access</span><span>Fees</span><span>Stripe</span><span>Latest Payment</span><span>Controls</span>
            </div>
            <?php foreach ($subscriptions as $subscription): ?>
                <?php
                    $moduleAccessActive = (int) ($subscription['module_access_active'] ?? 0) === 1;
                    $hasAccessMismatch = (string) ($subscription['product_key'] ?? '') === '247sp' && !$moduleAccessActive;
                ?>
                <div class="admin-table__row">
                    <span><a href="business.php?business_id=<?= e($subscription['business_id']) ?>"><?= e($subscription['business_name']) ?></a></span>
                    <span><?= e($subscription['plan_name']) ?></span>
                    <span><?= ui_badge(AdminPortal::statusLabel($subscription['status']), $subscription['status'] === 'past_due' ? 'role' : 'status') ?></span>
                    <span>
                        <?= ui_badge(billing_module_access_label($subscription['module_access_active']), $moduleAccessActive ? 'status' : 'role') ?>
                        <?php if ($hasAccessMismatch): ?>
                            <small class="admin-table__cell-note">Subscription exists; module access inactive.</small>
                        <?php endif; ?>
                    </span>
                    <span>
                        <strong>Setup:</strong> <?= e(billing_money($subscription['setup_fee'])) ?><br>
                        <strong>Monthly:</strong> <?= e(billing_money($subscription['monthly_fee'])) ?><br>
                        <small class="admin-table__cell-note">Starts: <?= e($subscription['started_at'] ?: 'Not started') ?></small>
                    </span>
                    <span>
                        <small class="admin-table__cell-note">Customer: <?= e($subscription['stripe_customer_id'] ?: 'Not recorded') ?></small><br>
                        <small class="admin-table__cell-note">Subscription: <?= e($subscription['stripe_subscription_id'] ?: 'Not recorded') ?></small><br>
                        <small class="admin-table__cell-note">Payment: <?= e($subscription['payment_method_status'] ?: 'Not recorded') ?></small>
                    </span>
                    <span>
                        <small class="admin-table__cell-note">Status: <?= e($subscription['latest_payment_status'] ?: 'No payment yet') ?></small><br>
                        <small class="admin-table__cell-note">Invoice: <?= e($subscription['latest_stripe_invoice_id'] ?: 'Not recorded') ?></small><br>
                        <small class="admin-table__cell-note">At: <?= e($subscription['latest_payment_at'] ?: 'Not recorded') ?></small>
                    </span>
                    <span>
                        <form method="post" action="billing.php" class="billing-status-form">
                            <input type="hidden" name="subscription_id" value="<?= e($subscription['id']) ?>">
                            <select name="status" aria-label="Subscription status for <?= e($subscription['business_name']) ?>">
                                <?php foreach (BillingFoundation::STATUSES as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $subscription['status'] === $status ? 'selected' : '' ?>>
                                        <?= e(AdminPortal::statusLabel($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= ui_button('Save', '', 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (count($subscriptions) === 0): ?>
                <p class="muted">No subscriptions have been created yet.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
