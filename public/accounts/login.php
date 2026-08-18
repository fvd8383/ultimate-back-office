<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/SignupContext.php';

Session::start();
$productContext = SignupContext::fromRequest($_POST, $_GET);

if (Session::isAuthenticated()) {
    header('Location: ' . SignupContext::destination($productContext));
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
            $query = [
                'email' => $email,
                'requested' => '1',
            ];

            if ($productContext !== null) {
                $query['product'] = $productContext;
            }

            if (($result['display_code'] ?? null) !== null) {
                $query['code'] = (string) $result['display_code'];
            }

            header('Location: verify.php?' . http_build_query($query));
            exit;
        } catch (Throwable $exception) {
            $error = 'The login code could not be prepared. Check the environment and database setup.';
        }
    }
}

$pageTitle = SignupContext::is247sp($productContext)
    ? 'Continue 24/7 Sales Partner Signup - Ultimate Back Office'
    : 'Login - Ultimate Back Office';
$bodyClass = 'accounts-page';
$layoutHomeHref = 'login.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="auth-panel">
    <div class="auth-panel__content">
        <p class="eyebrow"><?= SignupContext::is247sp($productContext) ? '24/7 Sales Partner signup' : 'Accounts' ?></p>
        <h1>Sign in with a one-time code</h1>
        <p class="muted"><?= SignupContext::is247sp($productContext)
            ? "Continue your 24/7 Sales Partner signup through Ultimate Back Office. Enter your email address to request a secure login code."
            : 'Enter your email address to request a secure login code.' ?></p>

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
            <?php if ($productContext !== null): ?>
                <input type="hidden" name="product" value="<?= e($productContext) ?>">
            <?php endif; ?>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">
            <?= ui_button('Request code') ?>
        </form>

        <p class="secondary-link">
            Already have a code? <a href="verify.php<?= e(SignupContext::query($productContext, $email !== '' ? ['email' => $email] : [])) ?>">Verify it</a>
        </p>
        <p class="secondary-link">
            New to Ultimate Back Office? <a href="signup.php<?= e(SignupContext::query($productContext, $email !== '' ? ['email' => $email] : [])) ?>">Create account</a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
