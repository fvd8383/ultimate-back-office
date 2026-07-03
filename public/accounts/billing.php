<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BillingFoundation.php';

Session::requireAuth('login.php');

$loadError = '';
$user = null;
$subscriptions = [];
$payments = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $subscriptions = BillingFoundation::customerSubscriptionsForUser((int) $user['id']);
    $payments = BillingFoundation::customerPaymentsForUser((int) $user['id']);
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

function accounts_billing_payment_label(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Not recorded';
    }

    return ucwords(str_replace('_', ' ', $value));
}

$pageTitle = 'Billing - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('billing');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Accounts</p>
    <h1>Billing</h1>
    <p class="muted">View payment method status, invoices, charges, fees, and billing status for your linked businesses.</p>
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
        <h2>Billing Status</h2>
        <div class="business-list">
            <?php foreach ($subscriptions as $subscription): ?>
                <?php
                    $billingStatus = accounts_billing_status($subscription['subscription_status']);
                ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($subscription['business_name']) ?></h3>
                        <p class="muted">Business ID <?= e($subscription['business_id']) ?></p>
                        <?php if (in_array((string) $subscription['subscription_status'], ['pending_payment', 'past_due'], true)): ?>
                            <?= ui_alert('Billing status needs attention.', 'warning') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Payment Method</dt><dd><?= e('Payment setup not on file yet') ?></dd></div>
                        <div><dt>Billing Status</dt><dd><?= ui_badge($billingStatus, in_array((string) $subscription['subscription_status'], ['past_due', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_billing_money($subscription['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_billing_money($subscription['setup_fee'])) ?></dd></div>
                        <div><dt>Product</dt><dd><?= e($subscription['plan_name'] ?: 'No plan recorded') ?></dd></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dashboard-card">
        <h2>Past Invoices</h2>
        <section class="empty-state">
            <h2>No invoices yet</h2>
            <p>Invoices will appear here after your first payment.</p>
        </section>
    </section>

    <section class="dashboard-card">
        <h2>Charges and Payments</h2>
        <?php if (count($payments) === 0): ?>
            <section class="empty-state">
                <h2>No charges yet</h2>
                <p>Charges and payment activity will appear here after billing begins.</p>
            </section>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($payments as $payment): ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e(accounts_billing_payment_label($payment['payment_type'] ?? 'Payment')) ?></h3>
                            <p class="muted"><?= e($payment['business_name']) ?> · <?= e($payment['plan_name']) ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Amount</dt><dd><?= e(accounts_billing_money($payment['amount'])) ?></dd></div>
                            <div><dt>Status</dt><dd><?= ui_badge(accounts_billing_payment_label($payment['status'] ?? ''), in_array((string) ($payment['status'] ?? ''), ['failed', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                            <div><dt>Date</dt><dd><?= e($payment['created_at'] ?: 'Not recorded') ?></dd></div>
                            <div><dt>Reference</dt><dd><?= e($payment['transaction_reference'] ?: 'Not recorded') ?></dd></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
