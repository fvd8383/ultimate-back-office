<?php

function marketing_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function marketing_asset_url(string $path): string
{
    return ltrim($path, '/');
}

function marketing_signup_url(array $config, string $location): string
{
    $url = (string) $config['signup']['url'];
    $separator = str_contains($url, '?') ? '&' : '?';

    return $url . $separator . http_build_query(['utm_content' => $location]);
}

function marketing_signup_cta(
    array $config,
    string $location,
    string $class = 'button button--primary',
    ?string $analyticsEvent = null
): string {
    $event = $analyticsEvent ?? $config['analytics_events']['primary_cta'];

    return sprintf(
        '<a class="%s" href="%s" data-signup-cta data-analytics-event="%s" data-analytics-location="%s">%s</a>',
        marketing_e($class),
        marketing_e(marketing_signup_url($config, $location)),
        marketing_e($event),
        marketing_e($location),
        marketing_e($config['signup']['label'])
    );
}

function marketing_section_heading(string $eyebrow, string $headline, string $copy = '', string $alignment = ''): string
{
    $class = trim('section-heading ' . ($alignment !== '' ? 'section-heading--' . $alignment : ''));
    $html = '<div class="' . marketing_e($class) . '">';
    $html .= '<p class="eyebrow">' . marketing_e($eyebrow) . '</p>';
    $html .= '<h2>' . marketing_e($headline) . '</h2>';
    if ($copy !== '') {
        $html .= '<p>' . marketing_e($copy) . '</p>';
    }

    return $html . '</div>';
}

function marketing_document_head(array $config, array $page): void
{
    $path = $page['path'] ?? '';
    $canonical = rtrim((string) $config['brand']['canonical_url'], '/') . '/' . ltrim($path, '/');
    ?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= marketing_e($page['title']) ?></title>
    <meta name="description" content="<?= marketing_e($page['description']) ?>">
    <link rel="canonical" href="<?= marketing_e($canonical) ?>">
    <link rel="icon" href="<?= marketing_e(marketing_asset_url($config['brand']['favicon'])) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/marketing.css">
    <script>document.documentElement.classList.remove('no-js');document.documentElement.classList.add('js');</script>
<?php if (!empty($page['social'])): ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="24/7 Sales Partner">
    <meta property="og:title" content="<?= marketing_e($page['title']) ?>">
    <meta property="og:description" content="<?= marketing_e($page['description']) ?>">
    <meta property="og:url" content="<?= marketing_e($canonical) ?>">
    <meta property="og:image" content="<?= marketing_e(rtrim($config['brand']['canonical_url'], '/') . $config['brand']['social_image']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= marketing_e($page['title']) ?>">
    <meta name="twitter:description" content="<?= marketing_e($page['description']) ?>">
    <meta name="twitter:image" content="<?= marketing_e(rtrim($config['brand']['canonical_url'], '/') . $config['brand']['social_image']) ?>">
<?php endif; ?>
</head>
<body class="<?= marketing_e($page['body_class'] ?? '') ?>" data-page-name="<?= marketing_e($page['page_name'] ?? 'marketing') ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php
}

function marketing_header(array $config, bool $compact = false): void
{
    ?>
<header class="site-header" id="top">
    <div class="container site-header__inner">
        <a class="brand" href="<?= $compact ? 'index.php' : '#top' ?>" aria-label="24/7 Sales Partner home">
            <img src="<?= marketing_e(marketing_asset_url($config['brand']['logo'])) ?>" alt="24/7 Sales Partner" width="320" height="80">
        </a>
<?php if (!$compact): ?>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
            <span class="nav-toggle__label">Menu</span><span class="nav-toggle__icon" aria-hidden="true"></span>
        </button>
        <nav id="primary-navigation" class="primary-nav" aria-label="Primary navigation">
            <a href="#how-it-works">How It Works</a>
            <a href="#included">What's Included</a>
            <a href="#pricing">Pricing</a>
            <a href="#faq">FAQ</a>
            <a class="primary-nav__mobile-cta" href="<?= marketing_e(marketing_signup_url($config, 'mobile_navigation')) ?>" data-signup-cta data-analytics-event="<?= marketing_e($config['analytics_events']['primary_cta']) ?>" data-analytics-location="mobile_navigation">Get Started</a>
        </nav>
        <?= marketing_signup_cta($config, 'header', 'button button--small site-header__cta') ?>
<?php else: ?>
        <a class="utility-back" href="index.php">Back to the homepage</a>
<?php endif; ?>
    </div>
</header>
<?php
}

function marketing_footer(array $config): void
{
    ?>
<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <img src="<?= marketing_e(marketing_asset_url($config['brand']['logo'])) ?>" alt="24/7 Sales Partner" width="320" height="80">
            <p>Part of the Ultimate Back Office ecosystem.</p>
        </div>
        <nav aria-label="Footer navigation">
            <a href="<?= marketing_e(ltrim($config['legal']['privacy_url'], '/')) ?>">Privacy Policy</a>
            <a href="<?= marketing_e(ltrim($config['legal']['terms_url'], '/')) ?>">Terms of Service</a>
            <a href="<?= marketing_e(ltrim($config['legal']['contact_url'], '/')) ?>">Contact / Support</a>
        </nav>
        <p class="site-footer__copyright">&copy; <?= date('Y') ?> Ultimate Back Office. 24/7 Sales Partner.</p>
    </div>
</footer>
<script src="assets/js/marketing.js" defer></script>
</body>
</html>
<?php
}
