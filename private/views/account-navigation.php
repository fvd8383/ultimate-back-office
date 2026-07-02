<?php

require_once __DIR__ . '/../classes/AdminPortal.php';

if (!function_exists('application_shell_base_urls')) {
    function application_shell_base_urls(string $area): array
    {
        $fallbacks = [
            'accounts' => ['accounts' => '.', 'app' => '../app'],
            'app' => ['accounts' => '../accounts', 'app' => '.'],
            'app_lead_hub' => ['accounts' => '../../accounts', 'app' => '..'],
            'app_247sp' => ['accounts' => '../../accounts', 'app' => '..'],
            'app_admin' => ['accounts' => '../../accounts', 'app' => '..'],
        ];
        $fallback = $fallbacks[$area] ?? $fallbacks['accounts'];

        try {
            $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL', $fallback['accounts']), '/');
            $appBaseUrl = rtrim((string) Database::config('APP_BASE_URL', $fallback['app']), '/');
        } catch (Throwable $exception) {
            $accountsBaseUrl = $fallback['accounts'];
            $appBaseUrl = $fallback['app'];
        }

        return [
            'accounts' => $accountsBaseUrl !== '' ? $accountsBaseUrl : $fallback['accounts'],
            'app' => $appBaseUrl !== '' ? $appBaseUrl : $fallback['app'],
        ];
    }
}

if (!function_exists('application_shell_business')) {
    function application_shell_business(array $options): ?array
    {
        if (isset($options['business']) && is_array($options['business'])) {
            return $options['business'];
        }

        try {
            $user = $options['user'] ?? Auth::currentUser();
            if (!is_array($user)) {
                return null;
            }

            return BusinessFoundation::firstBusinessForUser((int) $user['id']);
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('application_shell_admin_visible')) {
    function application_shell_admin_visible(array $options): bool
    {
        try {
            $user = $options['user'] ?? Auth::currentUser();
            return is_array($user) && AdminPortal::currentUserIsAdmin((int) $user['id']);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('application_shell_href')) {
    function application_shell_href(string $baseUrl, string $path, ?array $business = null): string
    {
        $href = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        if ($business !== null && isset($business['id']) && (int) $business['id'] > 0) {
            $separator = strpos($href, '?') !== false ? '&' : '?';
            $href .= $separator . 'business_id=' . urlencode((string) $business['id']);
        }

        return $href;
    }
}

if (!function_exists('application_shell_section')) {
    function application_shell_section(string $title, array $items): string
    {
        if (count($items) === 0) {
            return '';
        }

        $html = '<section class="account-nav__section">';
        $html .= '<p class="account-nav__section-title">' . e($title) . '</p>';
        $html .= '<div class="account-nav__section-items">';

        foreach ($items as $item) {
            $isModule = (string) ($item['type'] ?? '') === 'module';
            $children = $item['children'] ?? [];
            $hasChildren = is_array($children) && count($children) > 0;
            $itemClass = 'account-nav__item' . ($isModule ? ' account-nav__item--module' : '') . ($hasChildren ? ' is-expanded' : '');

            $html .= '<a class="' . e($itemClass) . '" href="' . e((string) $item['href']) . '"' . (!empty($item['current']) ? ' aria-current="page"' : '') . '>';
            if ($isModule) {
                $html .= '<span class="account-nav__module-state" aria-hidden="true">' . ($hasChildren ? '&#9662;' : '&#9656;') . '</span>';
            }
            $html .= application_shell_item_icon($item);
            $html .= '<span>' . e((string) $item['label']) . '</span>';
            $html .= '</a>';

            if ($hasChildren) {
                $html .= '<nav class="account-nav__subnav" aria-label="' . e((string) $item['label'] . ' navigation') . '">';
                foreach ($children as $child) {
                    $html .= '<a class="account-nav__subitem" href="' . e((string) ($child['href'] ?? '#')) . '"' . (!empty($child['current']) ? ' aria-current="page"' : '') . '>';
                    $html .= e((string) ($child['label'] ?? ''));
                    $html .= '</a>';
                }
                $html .= '</nav>';
            }
        }

        return $html . '</div></section>';
    }
}

if (!function_exists('application_shell_item_icon')) {
    function application_shell_item_icon(array $item): string
    {
        $logo = trim((string) ($item['logo'] ?? ''));
        if ($logo !== '') {
            return '<span class="account-nav__logo" aria-hidden="true"><img src="' . e($logo) . '" alt=""></span>';
        }

        return '<span class="account-nav__icon" aria-hidden="true">' . e((string) ($item['icon'] ?? '')) . '</span>';
    }
}

if (!function_exists('application_navigation')) {
    function application_navigation(string $current, array $options = []): string
    {
        $area = (string) ($options['area'] ?? 'accounts');
        $baseUrls = application_shell_base_urls($area);
        $business = application_shell_business($options);

        $accountItems = [
            ['icon' => '🏠', 'label' => 'Home', 'href' => $baseUrls['accounts'] . '/dashboard.php', 'current' => $current === 'home' || $current === 'dashboard'],
            ['icon' => '🏢', 'label' => 'Businesses', 'href' => $baseUrls['accounts'] . '/businesses.php', 'current' => $current === 'businesses'],
            ['icon' => '💳', 'label' => 'Billing', 'href' => $baseUrls['accounts'] . '/billing.php', 'current' => $current === 'billing'],
            ['icon' => '🌐', 'label' => 'Domains', 'href' => $baseUrls['accounts'] . '/domains.php', 'current' => $current === 'domains'],
            ['icon' => '✉️', 'label' => 'Email', 'href' => $baseUrls['accounts'] . '/email.php', 'current' => $current === 'email'],
            ['icon' => '👤', 'label' => 'Profile', 'href' => $baseUrls['accounts'] . '/profile.php', 'current' => $current === 'profile'],
        ];

        $leadHubItem = [
            'type' => 'module',
            'logo' => $baseUrls['app'] . '/assets/img/lead-hub-logo.svg',
            'label' => 'Lead Hub',
            'href' => application_shell_href($baseUrls['app'], 'dashboard.php', $business),
            'current' => $current === 'lead_hub',
        ];
        if ($current === 'lead_hub' && isset($options['secondary_nav']) && is_array($options['secondary_nav'])) {
            $leadHubItem['children'] = $options['secondary_nav'];
        }

        $salesPartnerItem = [
            'type' => 'module',
            'logo' => $baseUrls['app'] . '/assets/img/247sp-logo.svg',
            'label' => '24/7 Sales Partner',
            'href' => application_shell_href($baseUrls['app'], '247sp/dashboard.php', $business),
            'current' => $current === '247sp',
        ];
        if ($current === '247sp' && isset($options['secondary_nav']) && is_array($options['secondary_nav'])) {
            $salesPartnerItem['children'] = $options['secondary_nav'];
        }

        $workspaceItems = [
            $leadHubItem,
            $salesPartnerItem,
        ];

        $adminItems = application_shell_admin_visible($options)
            ? [['icon' => '⚙', 'label' => 'Admin Portal', 'href' => $baseUrls['app'] . '/admin/dashboard.php', 'current' => $current === 'admin']]
            : [];

        $html = '<aside class="account-sidebar application-sidebar" aria-label="Application navigation">';
        $html .= '<div class="account-sidebar__brand"><h2>Ultimate Back Office</h2></div>';
        $html .= '<nav class="account-nav">';
        $html .= application_shell_section('Account', $accountItems);
        $html .= application_shell_section('Workspace', $workspaceItems);
        $html .= application_shell_section('Admin', $adminItems);
        $html .= '</nav>';
        $html .= '<div class="account-nav__footer">';
        $html .= '<a class="account-nav__item account-nav__logout" href="' . e($baseUrls['accounts'] . '/logout.php') . '">';
        $html .= '<span class="account-nav__icon" aria-hidden="true">↪</span><span>Log out</span>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</aside>';

        return $html;
    }
}

if (!function_exists('application_shell_begin')) {
    function application_shell_begin(string $current, array $options = []): void
    {
        $layoutClass = trim('account-layout application-layout ' . (string) ($options['layout_class'] ?? ''));
        echo '<section class="' . e($layoutClass) . '">';
        echo application_navigation($current, $options);
        echo '<div class="account-content application-content">';
    }
}

if (!function_exists('application_shell_end')) {
    function application_shell_end(): void
    {
        echo '</div></section>';
    }
}

if (!function_exists('account_shell_begin')) {
    function account_shell_begin(string $current, array $options = []): void
    {
        $mapped = $current === 'dashboard' ? 'home' : $current;
        $options['area'] = $options['area'] ?? 'accounts';
        application_shell_begin($mapped, $options);
    }
}

if (!function_exists('account_shell_end')) {
    function account_shell_end(): void
    {
        application_shell_end();
    }
}

if (!function_exists('application_module_nav')) {
    function application_module_nav(array $items, string $label): string
    {
        if (count($items) === 0) {
            return '';
        }

        $html = '<nav class="module-secondary-nav" aria-label="' . e($label) . '">';
        foreach ($items as $item) {
            $html .= '<a href="' . e((string) ($item['href'] ?? '#')) . '"' . (!empty($item['current']) ? ' aria-current="page"' : '') . '>' . e((string) ($item['label'] ?? '')) . '</a>';
        }

        return $html . '</nav>';
    }
}
