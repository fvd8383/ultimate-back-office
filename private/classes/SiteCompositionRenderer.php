<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteComponentRenderers.php';
require_once __DIR__ . '/ThemeRegistry.php';

final class SiteCompositionRenderer
{
    public static function render(array $composition, array $context = []): string
    {
        return self::renderPages($composition, $context, null);
    }

    /** One page's main content, preserving navigation from the complete composition. */
    public static function renderPage(array $composition, string $pageKey, array $context = []): string
    {
        if (count(array_filter($composition['pages'] ?? [], static fn ($p) => $p['page_key'] === $pageKey)) !== 1) {
            throw new SiteServiceException('invalid_request', 'Exactly one stored page is required.');
        }
        return self::renderPages($composition, $context, $pageKey);
    }

    private static function renderPages(array $composition, array $context, ?string $pageKey): string
    {
        if (($composition['validated_for_rendering'] ?? false) !== true) {
            throw new SiteServiceException('invalid_request', 'A validated composition render model is required.');
        }
        $legacyCompatibility = ($composition['historical'] ?? false) === true
            && ($composition['legacy_compatibility'] ?? false) === true;
        $pages = $composition['pages'] ?? [];
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new SiteServiceException('invalid_request', 'Validated composition pages are required.');
        }
        usort($pages, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
        $navigation = [];
        foreach ($pages as $page) {
            if (($page['presentation']['show_in_navigation'] ?? false) === true) {
                $slug = (string) $page['slug'];
                $navigation[] = [
                    'label' => (string) ($page['navigation_label'] ?: $page['title']),
                    'href' => $slug === '' ? '/' : '/' . $slug,
                ];
            }
        }
        $context['navigation'] = $navigation;
        $theme = $composition['theme'] ?? null;
        if (!is_array($theme)) {
            throw new SiteServiceException('invalid_request', 'Validated composition theme is required.');
        }
        $layouts = $theme['configuration']['layouts'] ?? [];
        $html = '<div class="site-composition" data-theme="' . SiteComponentRenderers::escape($theme['theme_key']) . '">';
        if (isset($layouts['site_header'])) {
            $html .= self::renderSelection($layouts['site_header'], $context, 'layout', false);
        }
        foreach ($pages as $page) {
            if ($pageKey !== null && $page['page_key'] !== $pageKey) continue;
            $width = $pageKey === null ? '' : ' class="layout-' . SiteComponentRenderers::escape($page['presentation']['layout_width']) . '"';
            $html .= '<main data-page-key="' . SiteComponentRenderers::escape($page['page_key']) . '"' . $width . '>';
            if (($context['preview_mode'] ?? false) === true) {
                $html .= '<p class="preview-page-label">Page: ' . SiteComponentRenderers::escape($page['title']) . '</p>';
            }
            $sections = $page['sections'];
            usort($sections, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
            foreach ($sections as $section) {
                $html .= self::renderSelection($section, $context, 'section', $legacyCompatibility);
            }
            $html .= '</main>';
        }
        if (isset($layouts['site_footer'])) {
            $html .= self::renderSelection($layouts['site_footer'], $context, 'layout', false);
        }
        if (isset($layouts['mobile_cta'])) {
            $html .= self::renderSelection($layouts['mobile_cta'], $context, 'layout', false);
        }
        return $html . '</div>';
    }

    private static function renderSelection(array $selection, array $context, string $scope, bool $legacyCompatibility): string
    {
        $definition = ComponentRegistry::definition(
            (string) $selection['component_key'],
            (string) $selection['implementation_version']
        );
        $scopeAllowed = $definition['scope'] === $scope
            || ($scope === 'section' && $legacyCompatibility && $definition['scope'] === 'legacy');
        if (!$scopeAllowed || !$definition['renderable']) {
            throw new SiteServiceException('conflict', 'Component is unavailable in the rendering scope.');
        }
        $variant = (string) $selection['variant_key'];
        if (!isset($definition['variants'][$variant])) {
            throw new SiteServiceException('conflict', 'Component variant is unavailable for rendering.');
        }
        return SiteComponentRenderers::render(
            (string) $definition['renderer'],
            $variant,
            (array) $selection['configuration'],
            $context
        );
    }
}
