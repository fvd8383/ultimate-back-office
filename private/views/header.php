<?php

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = $pageTitle ?? 'Ultimate Back Office';
$assetPath = $assetPath ?? 'assets/css/app.css';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($assetPath) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
    <div class="site-header__brand">
        <span class="site-header__mark">UBO</span>
        <span>Ultimate Back Office</span>
    </div>
</header>
<main class="page-shell">
