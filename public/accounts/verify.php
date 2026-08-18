<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/SignupContext.php';

Session::start();
$productContext = SignupContext::fromRequest($_POST, $_GET);

if (Session::isAuthenticated()) {
    header('Location: ' . SignupContext::destination($productContext));
    exit;
}

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$code = trim($_GET['code'] ?? '');
$error = '';
$notice = isset($_GET['requested']) ? 'Code requested. Enter the one-time code below to sign in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    try {
        if (Auth::verifyLoginCode($email, $code)) {
            header('Location: ' . SignupContext::destination($productContext));
            exit;
        }

        $error = 'The email and code combination is invalid or expired.';
    } catch (Throwable $exception) {
        $error = 'The code could not be verified. Check the environment and database setup.';
    }
}

$pageTitle = SignupContext::is247sp($productContext)
    ? 'Verify 24/7 Sales Partner Signup - Ultimate Back Office'
    : 'Verify Login - Ultimate Back Office';
$bodyClass = 'accounts-page';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="auth-panel">
    <div class="auth-panel__content">
        <p class="eyebrow"><?= SignupContext::is247sp($productContext) ? '24/7 Sales Partner signup' : 'Accounts' ?></p>
        <h1>Verify your login code</h1>
        <p class="muted">Codes expire after 10 minutes and can only be used once.</p>

        <?php if ($notice !== ''): ?>
            <?= ui_alert($notice, 'success') ?>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <?= ui_alert($error, 'error') ?>
        <?php endif; ?>

        <form method="post" action="verify.php" class="form-stack">
            <?php if ($productContext !== null): ?>
                <input type="hidden" name="product" value="<?= e($productContext) ?>">
            <?php endif; ?>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">

            <label for="code">One-time code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required value="<?= e($code) ?>">

            <?= ui_button('Verify code') ?>
        </form>

        <p class="secondary-link">
            Need a new code? <a href="login.php<?= e(SignupContext::query($productContext, $email !== '' ? ['email' => $email] : [])) ?>">Request one</a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
