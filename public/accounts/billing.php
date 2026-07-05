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

function accounts_billing_billable_status(string $status): bool
{
    return in_array($status, ['active', 'past_due', 'pending_payment'], true);
}

function accounts_billing_current_monthly_charges(array $subscriptions): float
{
    $total = 0.0;

    foreach ($subscriptions as $subscription) {
        if (!accounts_billing_billable_status((string) ($subscription['subscription_status'] ?? ''))) {
            continue;
        }

        $total += (float) ($subscription['monthly_fee'] ?? 0);
    }

    return $total;
}

function accounts_billing_next_renewal(array $subscriptions): string
{
    $today = strtotime('today');
    $renewals = [];

    foreach ($subscriptions as $subscription) {
        $status = (string) ($subscription['subscription_status'] ?? '');
        $startedAt = trim((string) ($subscription['started_at'] ?? ''));

        if (!in_array($status, ['active', 'past_due'], true) || $startedAt === '') {
            continue;
        }

        $renewal = strtotime($startedAt);
        if ($renewal === false) {
            continue;
        }

        while ($renewal < $today) {
            $nextRenewal = strtotime('+1 month', $renewal);
            if ($nextRenewal === false || $nextRenewal <= $renewal) {
                break;
            }
            $renewal = $nextRenewal;
        }

        $renewals[] = $renewal;
    }

    if (count($renewals) === 0) {
        return 'Appears after payment setup is complete.';
    }

    return date('M j, Y', min($renewals));
}

function accounts_billing_payment_method_status(array $subscriptions): array
{
    $hasActive = false;
    $needsAttention = false;
    $hasTrial = false;

    foreach ($subscriptions as $subscription) {
        $status = (string) ($subscription['subscription_status'] ?? '');
        $hasActive = $hasActive || $status === 'active';
        $needsAttention = $needsAttention || in_array($status, ['pending_payment', 'past_due'], true);
        $hasTrial = $hasTrial || $status === 'trial';
    }

    if ($needsAttention) {
        return ['label' => 'Payment attention needed', 'type' => 'role'];
    }

    if ($hasActive) {
        return ['label' => 'Payment setup complete', 'type' => 'status'];
    }

    if ($hasTrial) {
        return ['label' => 'Payment setup not complete', 'type' => 'role'];
    }

    return ['label' => 'No payment status yet', 'type' => 'role'];
}

function accounts_billing_launch_payment_state(array $subscriptions): array
{
    $has247sp = false;
    $hasActive247sp = false;
    $hasIncomplete247sp = false;

    foreach ($subscriptions as $subscription) {
        if ((string) ($subscription['product_key'] ?? '') !== '247sp') {
            continue;
        }

        $has247sp = true;
        $status = (string) ($subscription['subscription_status'] ?? '');
        $hasActive247sp = $hasActive247sp || $status === 'active';
        $hasIncomplete247sp = $hasIncomplete247sp || $status !== 'active';
    }

    if ($hasActive247sp && !$hasIncomplete247sp) {
        return ['label' => 'Payment ready for launch', 'type' => 'status', 'detail' => '24/7 Sales Partner billing is active.'];
    }

    if ($has247sp) {
        return ['label' => 'Payment needed before launch', 'type' => 'role', 'detail' => 'Complete payment after your website preview is ready.'];
    }

    return ['label' => 'No launch payment needed', 'type' => 'status', 'detail' => 'No 24/7 Sales Partner subscription is connected yet.'];
}

function accounts_billing_invoice_download_href(array $payment): string
{
    $reference = trim((string) ($payment['transaction_reference'] ?? ''));

    if (preg_match('/^https?:\/\//i', $reference) !== 1) {
        return '';
    }

    return $reference;
}

$currentMonthlyCharges = accounts_billing_current_monthly_charges($subscriptions);
$nextRenewal = accounts_billing_next_renewal($subscriptions);
$paymentMethodStatus = accounts_billing_payment_method_status($subscriptions);
$launchPaymentState = accounts_billing_launch_payment_state($subscriptions);

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
    <p class="muted">View payment method status, current charges, upcoming renewal, and invoice history for your linked businesses.</p>
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
        <h2>Billing Summary</h2>
        <div class="summary-list billing-summary-list">
            <div><dt>Current Monthly Charges</dt><dd><?= e(accounts_billing_money($currentMonthlyCharges)) ?></dd></div>
            <div><dt>Upcoming Renewal</dt><dd><?= e($nextRenewal) ?></dd></div>
            <div><dt>Payment Method Status</dt><dd><?= ui_badge($paymentMethodStatus['label'], $paymentMethodStatus['type']) ?></dd></div>
            <div><dt>Launch Readiness Payment State</dt><dd><?= ui_badge($launchPaymentState['label'], $launchPaymentState['type']) ?></dd></div>
        </div>
        <p class="muted"><?= e($launchPaymentState['detail']) ?></p>
    </section>

    <section class="dashboard-card">
        <h2>Current Charges</h2>
        <div class="business-list">
            <?php foreach ($subscriptions as $subscription): ?>
                <?php
                    $billingStatus = accounts_billing_status($subscription['subscription_status']);
                    $paymentMethod = (string) $subscription['subscription_status'] === 'active' ? 'Payment setup complete' : 'Payment setup not complete';
                ?>
                <article class="business-list__item">
                    <div>
                        <h3><?= e($subscription['business_name']) ?></h3>
                        <p class="muted"><?= e($subscription['plan_name'] ?: 'No plan recorded') ?></p>
                        <?php if (in_array((string) $subscription['subscription_status'], ['pending_payment', 'past_due'], true)): ?>
                            <?= ui_alert('Billing status needs attention.', 'warning') ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-list billing-summary-list">
                        <div><dt>Payment Method</dt><dd><?= e($paymentMethod) ?></dd></div>
                        <div><dt>Billing Status</dt><dd><?= ui_badge($billingStatus, in_array((string) $subscription['subscription_status'], ['past_due', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                        <div><dt>Monthly Fee</dt><dd><?= e(accounts_billing_money($subscription['monthly_fee'])) ?></dd></div>
                        <div><dt>Setup Fee</dt><dd><?= e(accounts_billing_money($subscription['setup_fee'])) ?></dd></div>
                        <div><dt>Start Date</dt><dd><?= e($subscription['started_at'] ?: 'Not started') ?></dd></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dashboard-card">
        <h2>Invoice History</h2>
        <?php if (count($payments) === 0): ?>
            <section class="empty-state">
                <h2>No invoices yet</h2>
                <p>Invoices will appear here after your first payment.</p>
            </section>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($payments as $payment): ?>
                    <?php $downloadHref = accounts_billing_invoice_download_href($payment); ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e(accounts_billing_payment_label($payment['payment_type'] ?? 'Payment')) ?></h3>
                            <p class="muted"><?= e($payment['business_name']) ?> · <?= e($payment['plan_name']) ?></p>
                            <?php if ($downloadHref !== ''): ?>
                                <?= ui_button('Download invoice', $downloadHref, 'secondary', ['class' => 'ubo-button--compact']) ?>
                            <?php else: ?>
                                <p class="muted">Download will appear when an invoice file is available.</p>
                            <?php endif; ?>
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
