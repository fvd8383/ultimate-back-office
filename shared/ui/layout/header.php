<?php

$pageTitle = $pageTitle ?? 'Ultimate Back Office';
$bodyClass = $bodyClass ?? '';
$designSystemPath = $designSystemPath ?? '/assets/css/design-system.css';
$layoutUserName = $layoutUserName ?? '';
$layoutLogoutHref = $layoutLogoutHref ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($designSystemPath) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
    <div class="site-header__inner">
        <a class="site-header__brand" href="<?= e($layoutHomeHref ?? '#') ?>">
            <span class="site-header__mark">UBO</span>
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
