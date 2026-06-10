<?php

require_once __DIR__ . '/../../private/classes/Auth.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $businesses = Auth::linkedBusinesses((int) $user['id']);
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $businesses = [];
    $loadError = 'Lead Hub could not be loaded. Check the environment and database setup.';
}

$pageTitle = 'Lead Hub - Ultimate Back Office';
$bodyClass = 'app-dashboard';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="app-layout">
    <aside class="app-sidebar">
        <h2>Lead Hub</h2>
        <nav aria-label="Lead Hub">
            <a href="dashboard.php" aria-current="page">Dashboard</a>
            <span>Contacts</span>
            <span>Tasks</span>
            <span>Activity</span>
        </nav>
    </aside>

    <div class="app-content">
        <section class="hero-panel">
            <p class="eyebrow">Lead Hub</p>
            <h1>Platform foundation</h1>
            <?php if ($user): ?>
                <p class="muted">Signed in as <?= e($user['first_name']) ?> <?= e($user['last_name']) ?> &lt;<?= e($user['email']) ?>&gt;</p>
            <?php endif; ?>
        </section>

        <?php if ($loadError !== ''): ?>
            <div class="error"><?= e($loadError) ?></div>
        <?php elseif (count($businesses) === 0): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>No business is linked to this account yet. Lead Hub will become available after a business is created and connected to this user.</p>
            </section>
        <?php else: ?>
            <section class="business-switcher">
                <h2>Linked Businesses</h2>
                <div class="business-grid">
                    <?php foreach ($businesses as $business): ?>
                        <article>
                            <h3><?= e($business['business_name']) ?></h3>
                            <p><?= e($business['city']) ?>, <?= e($business['state']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="metrics-grid" aria-label="Lead Hub summary">
                <article>
                    <span>Contacts</span>
                    <strong>Ready</strong>
                </article>
                <article>
                    <span>Tasks</span>
                    <strong>Ready</strong>
                </article>
                <article>
                    <span>Activity</span>
                    <strong>Ready</strong>
                </article>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
