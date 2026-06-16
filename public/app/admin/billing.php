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

admin_begin('Billing', 'billing', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Billing</p>
    <h1>Subscription management</h1>
    <p class="muted">Track 24/7 Sales Partner plans, manual billing statuses, and recurring revenue. No payment processing runs here.</p>
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
                <span>Business</span><span>Plan</span><span>Status</span><span>Setup Fee</span><span>Monthly Fee</span><span>Start Date</span><span>Controls</span>
            </div>
            <?php foreach ($subscriptions as $subscription): ?>
                <div class="admin-table__row">
                    <span><a href="business.php?business_id=<?= e($subscription['business_id']) ?>"><?= e($subscription['business_name']) ?></a></span>
                    <span><?= e($subscription['plan_name']) ?></span>
                    <span><?= ui_badge(AdminPortal::statusLabel($subscription['status']), $subscription['status'] === 'past_due' ? 'role' : 'status') ?></span>
                    <span><?= e(billing_money($subscription['setup_fee'])) ?></span>
                    <span><?= e(billing_money($subscription['monthly_fee'])) ?></span>
                    <span><?= e($subscription['started_at'] ?: 'Not started') ?></span>
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
