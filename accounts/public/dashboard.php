<?php

require_once dirname(__DIR__, 2) . '/private/classes/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Dashboard | Ultimate Back Office';
require dirname(__DIR__, 2) . '/private/views/header.php';
?>

<section class="dashboard-panel">
    <h1>Dashboard</h1>
    <p>Welcome back, <?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?>.</p>

    <dl class="account-meta">
        <dt>First Name</dt>
        <dd><?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt>Email</dt>
        <dd><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></dd>
    </dl>

    <a class="button" href="logout.php">Log Out</a>
</section>

<?php require dirname(__DIR__, 2) . '/private/views/footer.php'; ?>
