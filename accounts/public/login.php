<?php

require_once dirname(__DIR__, 2) . '/private/classes/Auth.php';

Auth::startSession();

if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (!$errors) {
        try {
            if (Auth::login($email, $password)) {
                header('Location: dashboard.php');
                exit;
            }

            $errors['form'] = 'Invalid email or password.';
        } catch (Throwable $exception) {
            $errors['form'] = 'Unable to log in right now.';
        }
    }
}

$pageTitle = 'Log In | Ultimate Back Office';
require dirname(__DIR__, 2) . '/private/views/header.php';
?>

<section class="auth-panel">
    <h1>Log In</h1>
    <p>Access your Ultimate Back Office account.</p>

    <?php if ($registered): ?>
        <div class="notice-success">Your account has been created. You can log in now.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>

        <div class="form-actions">
            <button type="submit">Log In</button>
            <a class="button button-secondary" href="signup.php">Sign Up</a>
        </div>
    </form>
</section>

<?php require dirname(__DIR__, 2) . '/private/views/footer.php'; ?>
