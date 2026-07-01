<?php

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = $pageTitle ?? 'Ultimate Back Office';
$bodyClass = $bodyClass ?? '';

require_once __DIR__ . '/../../shared/ui/components/buttons.php';
require_once __DIR__ . '/../../shared/ui/components/cards.php';
require_once __DIR__ . '/../../shared/ui/components/badges.php';
require_once __DIR__ . '/../../shared/ui/components/alerts.php';
require_once __DIR__ . '/../../shared/ui/components/launch-readiness.php';
require_once __DIR__ . '/../../shared/ui/layout/sidebar.php';
require __DIR__ . '/../../shared/ui/layout/header.php';
