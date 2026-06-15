<?php

require_once __DIR__ . '/../../private/classes/Auth.php';

Session::start();

if (Session::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$email = trim($_GET['email'] ?? '');
$notice = isset($_GET['signup']) ? 'Account created. Request a one-time code to sign in.' : '';
$error = '';
$displayCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            $result = Auth::requestLoginCode($email);
            $notice = $result['message'];
            $displayCode = $result['display_code'];
        } catch (Throwable $exception) {
            $error = 'The login code could not be prepared. Check the environment and database setup.';
        }
    }
}

$pageTitle = 'Login - Ultimate Back Office';
$bodyClass = 'accounts-page';
$layoutHomeHref = 'login.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="auth-panel">
    <div class="auth-panel__content">
        <p class="eyebrow">Accounts</p>
        <h1>Sign in with a one-time code</h1>
        <p class="muted">Enter your email address to request a secure login code.</p>

        <?php if ($notice !== ''): ?>
            <?= ui_alert($notice, 'success') ?>
        <?php endif; ?>

        <?php if ($displayCode !== null): ?>
            <div class="dev-code">
                <span>Staging code</span>
                <strong><?= e($displayCode) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <?= ui_alert($error, 'error') ?>
        <?php endif; ?>

        <form method="post" action="login.php" class="form-stack">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">
            <?= ui_button('Request code') ?>
        </form>

        <p class="secondary-link">
            Already have a code? <a href="verify.php<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>">Verify it</a>
        </p>
        <p class="secondary-link">
            New to Ultimate Back Office? <a href="signup.php<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>">Create account</a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
