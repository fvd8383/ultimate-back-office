<?php

require_once __DIR__ . '/../../private/classes/Auth.php';

Session::requireAuth('login.php');

$loadError = '';
$user = null;

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }
} catch (Throwable $exception) {
    $loadError = 'Profile information could not be loaded. Check the environment and database setup.';
}

$pageTitle = 'Profile - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
require __DIR__ . '/../../private/views/account-navigation.php';
account_shell_begin('profile');
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Accounts</p>
    <h1>Profile</h1>
    <p class="muted">View the signed-in account details used across Ultimate Back Office.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif ($user !== null): ?>
    <section class="dashboard-card">
        <h2>Account Details</h2>
        <dl class="summary-list">
            <div><dt>Name</dt><dd><?= e(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($user['phone'] ?: 'Not set') ?></dd></div>
            <div><dt>Status</dt><dd><?= ui_badge(ucwords(str_replace('_', ' ', (string) $user['status'])), 'status') ?></dd></div>
        </dl>
    </section>
<?php endif; ?>
<?php account_shell_end(); ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
