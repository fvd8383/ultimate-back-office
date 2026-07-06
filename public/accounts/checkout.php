<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BillingFoundation.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';
require_once __DIR__ . '/../../private/classes/StripeBilling.php';

Session::requireAuth('login.php');

$user = null;
$business = null;
$subscription = null;
$error = '';
$notice = '';

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    $businessId = (int) ($_GET['business_id'] ?? $_POST['business_id'] ?? 0);
    $business = $businessId > 0
        ? BusinessFoundation::businessForUser($businessId, (int) $user['id'])
        : BusinessFoundation::firstBusinessForUser((int) $user['id']);

    if ($business === null) {
        throw new InvalidArgumentException('Choose a business before starting payment setup.');
    }

    $subscription = BillingFoundation::subscriptionForBusiness((int) $business['id'], '247sp');
    if ($subscription === null) {
        throw new InvalidArgumentException('24/7 Sales Partner is not connected to this business yet.');
    }

    if ((string) ($subscription['status'] ?? '') === 'active') {
        $notice = 'Payment setup is already complete for this subscription.';
    } else {
        $session = StripeBilling::createCheckoutSession($user, $business, $subscription);
        header('Location: ' . (string) $session['url'], true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Payment setup is not available right now. Please contact support if this continues.';

    if ((bool) Database::config('APP_DEBUG', false)) {
        $error .= ' Configuration detail: ' . $exception->getMessage();
    }
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
    <p class="muted">Complete payment for 24/7 Sales Partner through secure Stripe Checkout.</p>
</section>

<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
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
