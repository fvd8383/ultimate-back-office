<?php

declare(strict_types=1);

require_once __DIR__ . '/ComponentRegistry.php';

final class SiteComponentRenderers
{
    public static function render(string $renderer, string $variantKey, array $configuration, array $context = []): string
    {
        return match ($renderer) {
            'hero' => self::hero($variantKey, $configuration, $context),
            'statistics' => self::statistics($configuration),
            'service_grid' => self::serviceGrid($configuration),
            'service_detail' => self::serviceDetail($configuration, $context),
            'trust_cards' => self::cards($configuration, 'trust-cards'),
            'about_content' => self::content($configuration, 'about-content', $context),
            'contact_content' => self::content($configuration, 'contact-content', $context),
            'cta' => self::cta($variantKey, $configuration, $context),
            'lead_form' => self::leadForm($configuration, $context),
            'pricing_list' => self::pricing($configuration, $context),
            'faq' => self::faq($configuration),
            'text_block' => self::textBlock($configuration),
            'site_header' => self::header($variantKey, $configuration, $context),
            'site_footer' => self::footer($configuration, $context),
            'mobile_cta' => self::mobileCta($configuration, $context),
            'legacy_snapshot' => self::legacySnapshot($variantKey, $configuration, $context),
            default => throw new SiteServiceException('conflict', 'Repository renderer is unavailable.'),
        };
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function hero(string $variantKey, array $c, array $context): string
    {
        $class = match ($variantKey) {
            'default' => 'hero hero--default',
            'split_media' => 'hero hero--split-media',
            default => throw new SiteServiceException('conflict', 'Repository hero variant is unavailable.'),
        };
        $html = '<section class="' . $class . '">';
        if (!empty($c['eyebrow'])) {
            $html .= '<p class="eyebrow">' . self::escape($c['eyebrow']) . '</p>';
        }
        $html .= '<h1>' . self::escape($c['headline']) . '</h1>';
        if (!empty($c['body'])) {
            $html .= '<p>' . self::escape($c['body']) . '</p>';
        }
        foreach (['primary_cta', 'secondary_cta'] as $key) {
            if (!empty($c[$key])) {
                $html .= self::actionLink($c[$key]['label'], $c[$key]['action'], $context);
            }
        }
        if (!empty($c['media_usage_key'])) {
            $url = self::assetUrl($c['media_usage_key'], $context);
            if ($url !== null) {
                $html .= '<img src="' . self::escape($url) . '" alt="' . self::escape($c['media_alt'] ?? '') . '">';
            }
        }
        return $html . '</section>';
    }

    private static function statistics(array $c): string
    {
        $html = '<section class="statistics"><ul>';
        foreach ($c['items'] as $item) {
            $html .= '<li><strong>' . self::escape($item['value']) . '</strong> ' . self::escape($item['label']) . '</li>';
        }
        return $html . '</ul></section>';
    }

    private static function serviceGrid(array $c): string
    {
        $html = '<section class="service-grid"><h2>' . self::escape($c['heading']) . '</h2>';
        if (!empty($c['intro'])) {
            $html .= '<p>' . self::escape($c['intro']) . '</p>';
        }
        $html .= '<div class="service-cards">';
        foreach ($c['services'] as $service) {
            $name = self::escape($service['name']);
            if (!empty($service['path'])) {
                $name = '<a href="/' . self::escape($service['path']) . '">' . $name . '</a>';
            }
            $html .= '<article><h3>' . $name . '</h3><p>' . self::escape($service['description']) . '</p></article>';
        }
        return $html . '</div></section>';
    }

    private static function serviceDetail(array $c, array $context): string
    {
        $html = '<section class="service-detail"><h2>' . self::escape($c['heading']) . '</h2><p>' . self::escape($c['body']) . '</p>';
        if (($c['included_items'] ?? []) !== []) {
            $html .= '<ul>';
            foreach ($c['included_items'] as $item) {
                $html .= '<li>' . self::escape($item) . '</li>';
            }
            $html .= '</ul>';
        }
        if (!empty($c['media_usage_key']) && ($url = self::assetUrl($c['media_usage_key'], $context)) !== null) {
            $html .= '<img src="' . self::escape($url) . '" alt="' . self::escape($c['media_alt'] ?? '') . '">';
        }
        return $html . '</section>';
    }

    private static function cards(array $c, string $class): string
    {
        $html = '<section class="' . $class . '">';
        if (!empty($c['heading'])) {
            $html .= '<h2>' . self::escape($c['heading']) . '</h2>';
        }
        foreach ($c['cards'] as $card) {
            $html .= '<article><h3>' . self::escape($card['title']) . '</h3><p>' . self::escape($card['body']) . '</p></article>';
        }
        return $html . '</section>';
    }

    private static function content(array $c, string $class, array $context): string
    {
        $html = '<section class="' . $class . '"><h2>' . self::escape($c['heading']) . '</h2><p>' . self::escape($c['body']) . '</p>';
        if (!empty($c['highlights'])) {
            $html .= '<ul>';
            foreach ($c['highlights'] as $highlight) {
                $html .= '<li>' . self::escape($highlight) . '</li>';
            }
            $html .= '</ul>';
        }
        foreach (['phone', 'email', 'service_area'] as $field) {
            if (!empty($c[$field])) {
                $html .= '<p class="' . $field . '">' . self::escape($c[$field]) . '</p>';
            }
        }
        if (!empty($c['media_usage_key']) && ($url = self::assetUrl($c['media_usage_key'], $context)) !== null) {
            $html .= '<img src="' . self::escape($url) . '" alt="' . self::escape($c['media_alt'] ?? '') . '">';
        }
        return $html . '</section>';
    }

    private static function cta(string $variantKey, array $c, array $context): string
    {
        $class = match ($variantKey) {
            'banner' => 'cta cta--banner',
            'inline' => 'cta cta--inline',
            default => throw new SiteServiceException('conflict', 'Repository CTA variant is unavailable.'),
        };
        $html = '<section class="' . $class . '">';
        if (!empty($c['heading'])) {
            $html .= '<h2>' . self::escape($c['heading']) . '</h2>';
        }
        if (!empty($c['body'])) {
            $html .= '<p>' . self::escape($c['body']) . '</p>';
        }
        return $html . self::actionLink($c['label'], $c['action'], $context) . '</section>';
    }

    private static function leadForm(array $c, array $context): string
    {
        $action = self::safeFormAction($context['lead_form_action'] ?? null);
        $html = '<section class="lead-form"><h2>' . self::escape($c['heading']) . '</h2>';
        if (!empty($c['body'])) {
            $html .= '<p>' . self::escape($c['body']) . '</p>';
        }
        $interactive = $action !== '';
        $html .= $interactive
            ? '<form method="post" action="' . self::escape($action) . '">'
            : '<div class="lead-form-preview" role="group" data-preview="inert">';
        foreach ($c['fields'] as $field) {
            $required = in_array($field, $c['required_fields'], true) ? ' required' : '';
            $disabled = $interactive ? '' : ' disabled';
            $html .= '<label>' . self::escape(ucfirst($field)) . '<input name="' . self::escape($field) . '"' . $required . $disabled . '></label>';
        }
        return $html . ($interactive
            ? '<button type="submit">' . self::escape($c['submit_label']) . '</button></form></section>'
            : '<button type="button" disabled>' . self::escape($c['submit_label']) . '</button></div></section>');
    }

    private static function pricing(array $c, array $context): string
    {
        $url = self::assetUrl($c['document_usage_key'], $context);
        $html = '<section class="pricing-list">';
        if ($url !== null) {
            $html .= '<a href="' . self::escape($url) . '">' . self::escape($c['label']) . '</a>';
        } else {
            $html .= '<span>' . self::escape($c['label']) . '</span>';
        }
        if (!empty($c['description'])) {
            $html .= '<p>' . self::escape($c['description']) . '</p>';
        }
        return $html . '</section>';
    }

    private static function faq(array $c): string
    {
        $html = '<section class="faq">';
        if (!empty($c['heading'])) {
            $html .= '<h2>' . self::escape($c['heading']) . '</h2>';
        }
        foreach ($c['items'] as $item) {
            $html .= '<details><summary>' . self::escape($item['question']) . '</summary><p>' . self::escape($item['answer']) . '</p></details>';
        }
        return $html . '</section>';
    }

    private static function textBlock(array $c): string
    {
        $html = '<section class="text-block align-' . self::escape($c['alignment']) . '">';
        if (!empty($c['heading'])) {
            $html .= '<h2>' . self::escape($c['heading']) . '</h2>';
        }
        return $html . '<p>' . self::escape($c['body']) . '</p></section>';
    }

    private static function header(string $variantKey, array $c, array $context): string
    {
        $class = match ($variantKey) {
            'standard' => 'site-header site-header--standard',
            'centered' => 'site-header site-header--centered',
            default => throw new SiteServiceException('conflict', 'Repository header variant is unavailable.'),
        };
        $html = '<header class="' . $class . '">';
        if (!empty($c['logo_usage_key']) && ($url = self::assetUrl($c['logo_usage_key'], $context)) !== null) {
            $html .= '<img src="' . self::escape($url) . '" alt="">';
        }
        if (!empty($c['tagline'])) {
            $html .= '<p>' . self::escape($c['tagline']) . '</p>';
        }
        if (($c['show_phone'] ?? false) === true) {
            $html .= self::actionLink((string) ($context['phone_label'] ?? 'Call us'), 'call', $context);
        }
        return $html . self::navigation($context['navigation'] ?? []) . '</header>';
    }

    private static function footer(array $c, array $context): string
    {
        $html = '<footer class="site-footer">';
        if ($c['show_navigation']) {
            $html .= self::navigation($context['navigation'] ?? []);
        }
        if ($c['show_contact']) {
            $html .= '<div class="site-footer__contact">'
                . self::actionLink((string) ($context['phone_label'] ?? 'Call us'), 'call', $context)
                . self::actionLink((string) ($context['email_label'] ?? 'Email us'), 'email', $context)
                . self::actionLink((string) ($context['contact_label'] ?? 'Contact us'), 'contact', $context)
                . '</div>';
        }
        return $html . '<p>' . self::escape($c['copyright_text']) . '</p></footer>';
    }

    private static function legacySnapshot(string $variantKey, array $c, array $context): string
    {
        $allowed = ['home', 'service', 'about', 'contact'];
        if (!in_array($variantKey, $allowed, true)) {
            throw new SiteServiceException('conflict', 'Repository legacy snapshot variant is unavailable.');
        }

        $html = '<section class="legacy-snapshot legacy-snapshot--' . self::escape($variantKey) . '" data-snapshot="compatibility">';
        $headingFields = match ($variantKey) {
            'home' => ['headline'],
            'service' => ['service_name'],
            'about' => ['about_heading'],
            'contact' => ['contact_heading'],
        };
        foreach ($headingFields as $field) {
            if (!empty($c[$field])) {
                $html .= '<h1>' . self::escape($c[$field]) . '</h1>';
            }
        }

        $textFields = match ($variantKey) {
            'home' => ['subheadline', 'business_description', 'service_area', 'special_offer', 'call_to_action'],
            'service' => ['service_description', 'included_heading', 'included_description', 'trust_heading', 'call_to_action'],
            'about' => ['company_description', 'years_in_business', 'service_area'],
            'contact' => ['contact_description', 'phone', 'email', 'service_area', 'contact_form_prompt'],
        };
        foreach ($textFields as $field) {
            if (isset($c[$field]) && (is_string($c[$field]) || is_numeric($c[$field]))) {
                $html .= '<p class="legacy-' . self::escape(str_replace('_', '-', $field)) . '">' . self::escape($c[$field]) . '</p>';
            }
        }

        foreach (['service_highlights', 'included_items', 'stats'] as $listField) {
            if (!isset($c[$listField]) || !is_array($c[$listField])) {
                continue;
            }
            $html .= '<ul class="legacy-' . self::escape(str_replace('_', '-', $listField)) . '">';
            foreach ($c[$listField] as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $html .= '<li>' . self::escape($item) . '</li>';
                    continue;
                }
                if (!is_array($item)) {
                    continue;
                }
                $primary = $item['name'] ?? $item['value'] ?? null;
                $secondary = $item['description'] ?? $item['label'] ?? null;
                if (is_string($primary) || is_numeric($primary)) {
                    $html .= '<li><strong>' . self::escape($primary) . '</strong>';
                    if (is_string($secondary) || is_numeric($secondary)) {
                        $html .= ' ' . self::escape($secondary);
                    }
                    $html .= '</li>';
                }
            }
            $html .= '</ul>';
        }

        if ($variantKey === 'service' && isset($c['trust_cards']) && is_array($c['trust_cards'])) {
            foreach ($c['trust_cards'] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $title = $card['title'] ?? null;
                $body = $card['text'] ?? null;
                if (is_string($title) || is_numeric($title)) {
                    $html .= '<article><h2>' . self::escape($title) . '</h2>';
                    if (is_string($body) || is_numeric($body)) {
                        $html .= '<p>' . self::escape($body) . '</p>';
                    }
                    $html .= '</article>';
                }
            }
        }

        foreach (['primary_cta', 'secondary_cta'] as $ctaField) {
            if (!isset($c[$ctaField]) || !is_array($c[$ctaField]) || !is_string($c[$ctaField]['label'] ?? null)) {
                continue;
            }
            $action = match ($c[$ctaField]['type'] ?? '') {
                'call_now' => 'call',
                'contact_form' => 'contact',
                default => null,
            };
            $html .= $action === null
                ? '<span class="action-unavailable">' . self::escape($c[$ctaField]['label']) . '</span>'
                : self::actionLink($c[$ctaField]['label'], $action, $context);
        }

        return $html . '</section>';
    }

    private static function mobileCta(array $c, array $context): string
    {
        return '<div class="mobile-cta">' . self::actionLink($c['label'], $c['action'], $context) . '</div>';
    }

    private static function navigation(array $items): string
    {
        $html = '<nav><ul>';
        foreach ($items as $item) {
            $html .= '<li><a href="' . self::escape($item['href']) . '">' . self::escape($item['label']) . '</a></li>';
        }
        return $html . '</ul></nav>';
    }

    private static function actionLink(string $label, string $action, array $context): string
    {
        $keys = ['call' => 'call_href', 'contact' => 'contact_href', 'email' => 'email_href'];
        $href = self::safeActionHref($action, $context[$keys[$action]] ?? null);
        return $href === null
            ? '<span class="action-unavailable">' . self::escape($label) . '</span>'
            : '<a class="action" href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
    }

    private static function safeActionHref(string $action, mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $valid = match ($action) {
            'call' => preg_match('/^tel:\+?[0-9() .-]{3,30}$/', $value) === 1,
            'email' => preg_match('/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/', $value) === 1,
            'contact' => preg_match('#^/(?!/)(?!.*\.\.)[A-Za-z0-9/_-]*$#', $value) === 1,
            default => false,
        };
        return $valid ? $value : null;
    }

    private static function safeFormAction(mixed $value): string
    {
        return is_string($value) && preg_match('#^/(?!/)(?!.*\.\.)[A-Za-z0-9/_-]+$#', $value) === 1 ? $value : '';
    }

    private static function assetUrl(string $usageKey, array $context): ?string
    {
        $url = $context['asset_urls'][$usageKey] ?? null;
        if (!is_string($url) || preg_match('#^(?:https://[A-Za-z0-9.-]+(?::[0-9]+)?/|/)(?!/)(?!.*\.\.)[^\s"<>]*$#', $url) !== 1) {
            return null;
        }
        return $url;
    }
}
