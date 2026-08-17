<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BillingFoundation.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';
require_once __DIR__ . '/../../private/classes/Csrf.php';
require_once __DIR__ . '/../../private/classes/StripeBilling.php';

Session::requireAuth('login.php');

$user = null;
$business = null;
$subscription = null;
$error = '';
$notice = '';
$csrfScope = '247sp-checkout';

if ((string) ($_GET['payment'] ?? '') === 'unavailable') {
    $error = 'Your signup and locked pricing are complete, but payment setup is temporarily unavailable. Please try again.';
}

try {
    $user = Auth::currentUser();
    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $businessId = $isPost ? (int) ($_POST['business_id'] ?? 0) : (int) ($_GET['business_id'] ?? 0);
    if ($isPost && $businessId <= 0) {
        throw new InvalidArgumentException('Choose a business before starting payment setup.');
    }
    $business = $businessId > 0
        ? BusinessFoundation::businessForUser($businessId, (int) $user['id'])
        : BusinessFoundation::firstBusinessForUser((int) $user['id']);
    if ($business === null || (string) ($business['status'] ?? '') !== 'active') {
        throw new InvalidArgumentException('Choose an active business before starting payment setup.');
    }

    $subscription = BillingFoundation::subscriptionForBusiness((int) $business['id'], '247sp');
    if ($subscription === null) {
        throw new InvalidArgumentException('24/7 Sales Partner is not connected to this business yet.');
    }
    if ((int) ($subscription['commercial_terms_id'] ?? 0) <= 0) {
        throw new InvalidArgumentException('Pricing will be assigned when this business signup is completed.');
    }

    if ($isPost) {
        Csrf::requireValid($_POST['csrf_token'] ?? null, $csrfScope);

        if (in_array((string) ($subscription['status'] ?? ''), ['active', 'trial'], true)
            && (string) ($subscription['payment_method_status'] ?? '') === 'complete'
        ) {
            $notice = 'Payment setup is already complete for this subscription.';
        } else {
            $session = StripeBilling::createCheckoutSession($user, $business, $subscription);
            if (!empty($session['ubo_already_complete'])) {
                header('Location: billing.php?checkout=success&business_id=' . urlencode((string) $business['id']), true, 303);
                exit;
            }
            header('Location: ' . StripeBilling::checkoutSessionRedirectUrl($session), true, 303);
            exit;
        }
    }
} catch (CsrfException $exception) {
    $error = $exception->getMessage();
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[Checkout] payment setup failed: ' . get_class($exception));
    $error = 'Payment setup is temporarily unavailable. Your signup and locked pricing are safe; please try again.';
}

$pageTitle = 'Checkout - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('billing');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Billing</p>
    <h1>Payment Setup</h1>
    <p class="muted">Review your locked 24/7 Sales Partner terms, then continue through secure Stripe Checkout.</p>
</section>

<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>
<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>

<?php if ($subscription !== null && (int) ($subscription['commercial_terms_id'] ?? 0) > 0): ?>
    <section class="dashboard-card">
        <h2><?= e($subscription['cohort_display_name'] ?: 'Locked pricing') ?></h2>
        <dl class="summary-list billing-summary-list">
            <div><dt>Locked monthly price</dt><dd>$<?= e(number_format((float) $subscription['monthly_fee'], 2)) ?></dd></div>
            <div><dt>One-time setup</dt><dd>$<?= e(number_format((float) $subscription['setup_fee'], 2)) ?></dd></div>
            <?php if ((int) ($subscription['free_introductory_months'] ?? 0) > 0): ?>
                <div><dt>Free through</dt><dd><?= e($subscription['introductory_period_expires_at']) ?></dd></div>
                <div><dt>Recurring billing begins</dt><dd><?= e($subscription['recurring_billing_starts_at']) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ((string) ($subscription['status'] ?? '') !== 'cancelled'
            && !(in_array((string) ($subscription['status'] ?? ''), ['active', 'trial'], true)
                && (string) ($subscription['payment_method_status'] ?? '') === 'complete')): ?>
            <form method="post" action="checkout.php">
                <?= Csrf::input($csrfScope) ?>
                <input type="hidden" name="business_id" value="<?= e($business['id']) ?>">
                <?= ui_button('Continue to secure payment setup') ?>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="dashboard-card">
    <h2>Next Step</h2>
    <p class="muted">Return to Billing to review subscription status, payment history, and invoice records.</p>
    <div class="button-row">
        <?= ui_button('View Billing', 'billing.php' . ($business ? '?business_id=' . urlencode((string) $business['id']) : ''), 'primary') ?>
        <?= ui_button('View Subscriptions', 'subscriptions.php', 'secondary') ?>
    </div>
</section>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
