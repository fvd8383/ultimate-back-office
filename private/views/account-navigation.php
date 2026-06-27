<?php

if (!function_exists('account_navigation_items')) {
    function account_navigation_items(string $current): array
    {
        return [
            ['key' => 'dashboard', 'icon' => '🏠', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'current' => $current === 'dashboard'],
            ['key' => 'businesses', 'icon' => '🏢', 'label' => 'Businesses', 'href' => 'dashboard.php#businesses', 'current' => $current === 'businesses'],
            ['key' => 'billing', 'icon' => '💳', 'label' => 'Billing', 'href' => 'billing.php', 'current' => $current === 'billing'],
            ['key' => 'domains', 'icon' => '🌐', 'label' => 'Domains', 'href' => 'domains.php', 'current' => $current === 'domains'],
            ['key' => 'email', 'icon' => '✉️', 'label' => 'Email', 'href' => 'email.php', 'current' => $current === 'email'],
            ['key' => 'profile', 'icon' => '👤', 'label' => 'Profile', 'href' => 'profile.php', 'current' => $current === 'profile'],
        ];
    }
}

if (!function_exists('account_navigation')) {
    function account_navigation(string $current): string
    {
        $html = '<aside class="account-sidebar" aria-label="Account navigation">';
        $html .= '<div><p class="eyebrow">Account</p><h2>Navigation</h2></div>';
        $html .= '<nav class="account-nav">';

        foreach (account_navigation_items($current) as $item) {
            $html .= '<a class="account-nav__item" href="' . e((string) $item['href']) . '"' . (!empty($item['current']) ? ' aria-current="page"' : '') . '>';
            $html .= '<span class="account-nav__icon" aria-hidden="true">' . e((string) $item['icon']) . '</span>';
            $html .= '<span>' . e((string) $item['label']) . '</span>';
            $html .= '</a>';
        }

        $html .= '</nav>';
        $html .= '<div class="account-nav__footer">';
        $html .= '<a class="account-nav__item account-nav__logout" href="logout.php">';
        $html .= '<span class="account-nav__icon" aria-hidden="true">↪</span><span>Logout</span>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</aside>';

        return $html;
    }
}

if (!function_exists('account_shell_begin')) {
    function account_shell_begin(string $current): void
    {
        echo '<section class="account-layout">';
        echo account_navigation($current);
        echo '<div class="account-content">';
    }
}

if (!function_exists('account_shell_end')) {
    function account_shell_end(): void
    {
        echo '</div></section>';
    }
}
