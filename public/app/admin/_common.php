<?php

require_once __DIR__ . '/../../../private/classes/AdminPortal.php';

function admin_bootstrap(): array
{
    try {
        $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
        $appBaseUrl = rtrim((string) Database::config('APP_BASE_URL'), '/');
    } catch (Throwable $exception) {
        $accountsBaseUrl = '../accounts';
        $appBaseUrl = '../app';
    }

    Session::requireAuth($accountsBaseUrl . '/login.php');

    $user = Auth::currentUser();
    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    return [
        'accounts_base_url' => $accountsBaseUrl,
        'app_base_url' => $appBaseUrl,
        'user' => $user,
        'is_admin' => AdminPortal::currentUserIsAdmin((int) $user['id']),
    ];
}

function admin_begin(string $title, string $current, array $context): void
{
    $user = $context['user'];
    $accountsBaseUrl = $context['accounts_base_url'];

    $pageTitle = $title . ' - Ultimate Back Office Admin';
    $bodyClass = 'app-dashboard theme-ubo admin-portal';
    $layoutHomeHref = 'dashboard.php';
    $layoutUserName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
    $layoutLogoutHref = $accountsBaseUrl . '/logout.php';
    $designSystemPath = '/assets/css/design-system.css';

    require __DIR__ . '/../../../private/views/header.php';
    ?>
    <section class="app-layout admin-layout">
        <?= ui_sidebar('Admin Portal', [
            ['label' => 'Dashboard', 'href' => 'dashboard.php', 'current' => $current === 'dashboard'],
            ['label' => 'Users', 'href' => 'users.php', 'current' => $current === 'users'],
            ['label' => 'Businesses', 'href' => 'businesses.php', 'current' => $current === 'businesses'],
            ['label' => 'Websites', 'href' => 'websites.php', 'current' => $current === 'websites'],
            ['label' => 'Billing', 'href' => 'billing.php', 'current' => $current === 'billing'],
            ['label' => 'Domains', 'href' => 'domains.php', 'current' => $current === 'domains'],
            ['label' => 'Email', 'href' => 'email.php', 'current' => $current === 'email'],
        ], 'Admin navigation') ?>
        <div class="app-content">
    <?php
}

function admin_end(): void
{
    ?>
        </div>
    </section>
    <?php
    require __DIR__ . '/../../../private/views/footer.php';
}

function admin_access_denied(array $context): void
{
    admin_begin('Access Denied', '', $context);
    ?>
    <section class="empty-state">
        <p class="eyebrow">Admin Portal</p>
        <h1>Access Denied</h1>
        <p>Only internal Super Admin and Admin roles may access these pages.</p>
    </section>
    <?php
    admin_end();
}

function admin_module_badges(array $modules): string
{
    if (count($modules) === 0) {
        return ui_badge('No active modules', 'status');
    }

    $html = '';
    foreach ($modules as $module) {
        $html .= ui_badge((string) $module['name'], 'module');
    }

    return $html;
}

function admin_yes_no($value): string
{
    return (int) $value === 1 || $value === true ? 'Yes' : 'No';
}
