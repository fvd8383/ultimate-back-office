<?php

require_once __DIR__ . '/../../private/classes/Auth.php';

Session::start();

if (Session::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$code = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    try {
        if (Auth::verifyLoginCode($email, $code)) {
            header('Location: dashboard.php');
            exit;
        }

        $error = 'The email and code combination is invalid or expired.';
    } catch (Throwable $exception) {
        $error = 'The code could not be verified. Check the environment and database setup.';
    }
}

$pageTitle = 'Verify Login - Ultimate Back Office';
$bodyClass = 'accounts-page';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="auth-panel">
    <div class="auth-panel__content">
        <p class="eyebrow">Accounts</p>
        <h1>Verify your login code</h1>
        <p class="muted">Codes expire after 10 minutes and can only be used once.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="verify.php" class="form-stack">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">

            <label for="code">One-time code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required value="<?= e($code) ?>">

            <button type="submit">Verify code</button>
        </form>

        <p class="secondary-link">
            Need a new code? <a href="login.php<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>">Request one</a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
