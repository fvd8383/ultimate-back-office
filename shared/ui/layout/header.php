<?php

$pageTitle = $pageTitle ?? 'Ultimate Back Office';
$bodyClass = $bodyClass ?? '';
$designSystemPath = $designSystemPath ?? '/assets/css/design-system.css';
$layoutLogoPath = $layoutLogoPath ?? '/assets/img/ubo-logo.svg';
$layoutFaviconPath = $layoutFaviconPath ?? '/assets/img/favicon.svg';
$layoutUserName = $layoutUserName ?? '';
$layoutLogoutHref = $layoutLogoutHref ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e($layoutFaviconPath) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($designSystemPath) ?>">
    <?php if (!empty($layoutHeadHtml)): ?>
        <?= $layoutHeadHtml ?>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
    <div class="site-header__inner">
        <a class="site-header__brand" href="<?= e($layoutHomeHref ?? '#') ?>">
            <img class="site-header__logo" src="<?= e($layoutLogoPath) ?>" alt="Ultimate Back Office">
            <span>Ultimate Back Office</span>
        </a>
        <?php if ($layoutUserName !== '' || $layoutLogoutHref !== ''): ?>
            <div class="site-header__account">
                <?php if ($layoutUserName !== ''): ?>
                    <span><?= e($layoutUserName) ?></span>
                <?php endif; ?>
                <?php if ($layoutLogoutHref !== ''): ?>
                    <?= ui_button('Log out', $layoutLogoutHref, 'secondary', ['class' => 'ubo-button--compact']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>
<main class="page-shell">
