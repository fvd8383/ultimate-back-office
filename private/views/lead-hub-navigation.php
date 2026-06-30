<?php

if (!function_exists('lead_hub_href')) {
    function lead_hub_href(string $path, int $businessId): string
    {
        if ($businessId <= 0) {
            return $path;
        }

        $separator = strpos($path, '?') !== false ? '&' : '?';
        return $path . $separator . 'business_id=' . urlencode((string) $businessId);
    }
}

if (!function_exists('lead_hub_nav_items')) {
    function lead_hub_nav_items(int $businessId, string $current, string $appRootPrefix = ''): array
    {
        $appRootPrefix = rtrim($appRootPrefix, '/');
        $appRootPrefix = $appRootPrefix !== '' ? $appRootPrefix . '/' : '';

        return [
            ['label' => 'Dashboard', 'href' => lead_hub_href($appRootPrefix . 'lead-hub/dashboard.php', $businessId), 'current' => $current === 'dashboard'],
            ['label' => 'Leads', 'href' => lead_hub_href($appRootPrefix . 'lead-hub/leads.php', $businessId), 'current' => $current === 'leads'],
            ['label' => 'Contacts', 'href' => lead_hub_href($appRootPrefix . 'lead-hub/contacts.php', $businessId), 'current' => $current === 'contacts'],
            ['label' => 'Tasks', 'href' => lead_hub_href($appRootPrefix . 'lead-hub/tasks.php', $businessId), 'current' => $current === 'tasks'],
            ['label' => 'Notes', 'href' => lead_hub_href($appRootPrefix . 'lead-hub/notes.php', $businessId), 'current' => $current === 'notes'],
        ];
    }
}
