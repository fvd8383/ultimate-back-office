<?php

require_once dirname(__DIR__, 2) . '/private/classes/Auth.php';

Auth::startSession();

if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['first_name'] = trim($_POST['first_name'] ?? '');
    $values['last_name'] = trim($_POST['last_name'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    if ($values['first_name'] === '') {
        $errors['first_name'] = 'First name is required.';
    }

    if ($values['last_name'] === '') {
        $errors['last_name'] = 'Last name is required.';
    }

    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($passwordConfirmation === '') {
        $errors['password_confirmation'] = 'Password confirmation is required.';
    } elseif ($password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        try {
            $result = Auth::register($values['first_name'], $values['last_name'], $values['email'], $password);

            if ($result['success']) {
                header('Location: login.php?registered=1');
                exit;
            }

            $errors['email'] = $result['message'];
        } catch (Throwable $exception) {
            $errors['form'] = 'Unable to create your account right now.';
        }
    }
}

$pageTitle = 'Sign Up | Ultimate Back Office';
require dirname(__DIR__, 2) . '/private/views/header.php';
?>

<section class="auth-panel">
    <h1>Create Account</h1>
    <p>Set up your Ultimate Back Office account.</p>

    <?php if ($errors): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="signup.php" novalidate>
        <div class="form-group">
            <label for="first_name">First Name</label>
            <input id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars($values['first_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name" required>
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars($values['last_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
        </div>

        <div class="form-actions">
            <button type="submit">Create Account</button>
            <a class="button button-secondary" href="login.php">Log In</a>
        </div>
    </form>
</section>

<?php require dirname(__DIR__, 2) . '/private/views/footer.php'; ?>
