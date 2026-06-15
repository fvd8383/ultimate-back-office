<?php

require_once __DIR__ . '/../../private/classes/Auth.php';

Session::start();

if (Session::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($firstName === '') {
        $error = 'First name is required.';
    } elseif ($lastName === '') {
        $error = 'Last name is required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            Auth::createActiveUser($firstName, $lastName, $email);
            header('Location: login.php?email=' . urlencode($email) . '&signup=1');
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'The account could not be created. Check the environment and database setup.';
        }
    }
}

$pageTitle = 'Create Account - Ultimate Back Office';
$bodyClass = 'accounts-page';
$layoutHomeHref = 'login.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="auth-panel">
    <div class="auth-panel__content">
        <p class="eyebrow">Accounts</p>
        <h1>Create account</h1>
        <p class="muted">Create your account, then sign in with a one-time code. No password is required.</p>

        <?php if ($error !== ''): ?>
            <?= ui_alert($error, 'error') ?>
        <?php endif; ?>

        <form method="post" action="signup.php" class="form-stack">
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" autocomplete="given-name" required value="<?= e($firstName) ?>">

            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" autocomplete="family-name" required value="<?= e($lastName) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">

            <?= ui_button('Create account') ?>
        </form>

        <p class="secondary-link">
            Already have an account? <a href="login.php<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>">Sign in</a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
