<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/BusinessFoundation.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

function lead_hub_bootstrap(): array
{
    global $accountsBaseUrl;

    $loadError = '';
    $user = null;
    $business = null;

    try {
        $user = Auth::currentUser();

        if ($user === null) {
            Session::logout();
            header('Location: ' . $accountsBaseUrl . '/login.php');
            exit;
        }

        $requestedBusinessId = (int) ($_GET['business_id'] ?? $_POST['business_id'] ?? 0);
        $business = $requestedBusinessId > 0
            ? BusinessFoundation::businessForUser($requestedBusinessId, (int) $user['id'])
            : BusinessFoundation::firstBusinessForUser((int) $user['id']);
    } catch (Throwable $exception) {
        $loadError = 'Lead Hub could not be loaded. Check the environment and database setup.';
    }

    return [
        'accounts_base_url' => $accountsBaseUrl,
        'user' => $user,
        'business' => $business,
        'business_id' => $business ? (int) $business['id'] : 0,
        'load_error' => $loadError,
    ];
}

function lead_hub_page(string $current, string $title, string $description): void
{
    $context = lead_hub_bootstrap();
    $ready = lead_hub_shell_begin($context, $current, $title);

    if ($ready) {
        ?>
            <section class="empty-state">
                <h2>No records yet</h2>
                <p><?= e($description) ?></p>
            </section>
        <?php
    }

    lead_hub_shell_end();
}

function lead_hub_shell_begin(array $context, string $current, string $title): bool
{
    $user = $context['user'];
    $business = $context['business'];
    $businessId = (int) $context['business_id'];
    $loadError = (string) $context['load_error'];
    $accountsBaseUrl = (string) $context['accounts_base_url'];

    $pageTitle = $title . ' - Lead Hub - Ultimate Back Office';
    $bodyClass = 'app-dashboard';
    $layoutHomeHref = '../dashboard.php';
    $layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
    $layoutLogoutHref = $accountsBaseUrl . '/logout.php';

    require __DIR__ . '/../../../private/views/header.php';
    require __DIR__ . '/../../../private/views/account-navigation.php';
    require __DIR__ . '/../../../private/views/lead-hub-navigation.php';

    $leadHubNavItems = lead_hub_nav_items($businessId, $current, '..');
    application_shell_begin('lead_hub', ['area' => 'app_lead_hub', 'user' => $user, 'business' => $business, 'secondary_nav' => $leadHubNavItems]);
    ?>
        <section class="hero-panel">
            <p class="eyebrow">Lead Hub</p>
            <h1><?= e($title) ?></h1>
            <p class="muted"><?= e($business ? $business['business_name'] : 'CRM workspace') ?></p>
        </section>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
            <?php return false; ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before using Lead Hub.</p>
                <?= ui_button('Accounts Dashboard', $accountsBaseUrl . '/dashboard.php', 'primary') ?>
            </section>
            <?php return false; ?>
        <?php endif; ?>
    <?php

    return true;
}

function lead_hub_shell_end(): void
{
    application_shell_end();
    require __DIR__ . '/../../../private/views/footer.php';
}
