<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteComponentRenderers.php';
require_once __DIR__ . '/ThemeRegistry.php';

final class SiteCompositionRenderer
{
    public static function render(array $composition, array $context = []): string
    {
        if (($composition['validated_for_rendering'] ?? false) !== true) {
            throw new SiteServiceException('invalid_request', 'A validated composition render model is required.');
        }
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
            $html .= self::renderSelection($layouts['site_header'], $context, 'layout');
        }
        foreach ($pages as $page) {
            $html .= '<main data-page-key="' . SiteComponentRenderers::escape($page['page_key']) . '">';
            $sections = $page['sections'];
            usort($sections, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
            foreach ($sections as $section) {
                $html .= self::renderSelection($section, $context, 'section');
            }
            $html .= '</main>';
        }
        if (isset($layouts['site_footer'])) {
            $html .= self::renderSelection($layouts['site_footer'], $context, 'layout');
        }
        if (isset($layouts['mobile_cta'])) {
            $html .= self::renderSelection($layouts['mobile_cta'], $context, 'layout');
        }
        return $html . '</div>';
    }

    private static function renderSelection(array $selection, array $context, string $scope): string
    {
        $definition = ComponentRegistry::definition(
            (string) $selection['component_key'],
            (string) $selection['implementation_version']
        );
        if ($definition['scope'] !== $scope || !$definition['renderable']) {
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
