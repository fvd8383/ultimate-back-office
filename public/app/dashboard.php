<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

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

    $business = BusinessFoundation::firstBusinessForUser((int) $user['id']);
    $activeModules = $business ? BusinessFoundation::activeModules((int) $business['id']) : [];
    $summary = $business ? BusinessFoundation::leadHubSummary((int) $business['id']) : [
        'contact_count' => 0,
        'task_count' => 0,
        'recent_activity' => [],
    ];
    $loadError = '';
} catch (Throwable $exception) {
    $user = null;
    $business = null;
    $activeModules = [];
    $summary = [
        'contact_count' => 0,
        'task_count' => 0,
        'recent_activity' => [],
    ];
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
            <h1><?= $business ? e($business['business_name']) : 'Platform foundation' ?></h1>
            <?php if ($user): ?>
                <p class="muted">Signed in as <?= e($user['first_name']) ?> <?= e($user['last_name']) ?> &lt;<?= e($user['email']) ?>&gt;</p>
            <?php endif; ?>
        </section>

        <?php if ($loadError !== ''): ?>
            <div class="error"><?= e($loadError) ?></div>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>No business is linked to this account yet. Lead Hub will become available after a business is created and connected to this user.</p>
            </section>
        <?php else: ?>
            <section class="business-switcher">
                <h2>Module Status</h2>
                <div class="pill-list">
                    <?php foreach ($activeModules as $module): ?>
                        <span><?= e($module['name']) ?> · <?= e($module['activation_source']) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($activeModules) === 0): ?>
                        <span>No active modules</span>
                    <?php endif; ?>
                </div>
            </section>

            <section class="metrics-grid" aria-label="Lead Hub summary">
                <article>
                    <span>Contacts</span>
                    <strong><?= e($summary['contact_count']) ?></strong>
                </article>
                <article>
                    <span>Tasks</span>
                    <strong><?= e($summary['task_count']) ?></strong>
                </article>
                <article>
                    <span>Activity</span>
                    <strong><?= e(count($summary['recent_activity'])) ?></strong>
                </article>
            </section>

            <section class="business-switcher">
                <h2>Recent Activity</h2>
                <?php if (count($summary['recent_activity']) === 0): ?>
                    <p class="muted">No recent activity yet.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($summary['recent_activity'] as $activity): ?>
                            <article>
                                <strong><?= e($activity['subject'] ?: $activity['activity_type']) ?></strong>
                                <?php if ($activity['description']): ?>
                                    <p><?= e($activity['description']) ?></p>
                                <?php endif; ?>
                                <span><?= e($activity['created_at']) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
