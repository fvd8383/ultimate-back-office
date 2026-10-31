<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteArtifactFiles.php';
require_once __DIR__ . '/SiteCompositionRenderer.php';

/** Explicit public DTO projection. No source references, locators, brief or lifecycle data. */
final class SitePublicRenderContext
{
    /** @return array{public_facts:array,public_composition:array} Assets are resolved separately. */
    public static function project(array $source): array
    {
        if (($source['site']['purpose'] ?? null) !== '247sp'
            || ($source['composition']['legacy_compatibility'] ?? false)) SiteArtifactFiles::fail('input_mismatch');
        $facts = SiteBuildContract::decode($source['revision']['facts_snapshot_json']);
        $business = self::fields($facts['business'] ?? [], ['display_name','phone','email']);
        $business['public_address'] = self::fields($facts['business']['public_address'] ?? [],
            ['line_1','line_2','city','state','postal_code','country']);
        $profile = self::fields($facts['profile'] ?? [], ['default_language','short_description','long_description','value_proposition']);
        $profile['default_language'] ??= 'en';
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $profile['default_language'])) SiteArtifactFiles::fail('input_mismatch');
        $public = ['business' => $business, 'profile' => $profile, 'services' => [], 'faqs' => []];
        foreach (['selected','custom'] as $kind) {
            $rows = $facts['services'][$kind] ?? [];
            if (count($rows) > 200) SiteArtifactFiles::fail('input_mismatch');
            $public['services'][$kind] = array_map(static fn ($r) => self::fields($r, ['name','category_name']), $rows);
        }
        $area = $facts['service_area'] ?? [];
        $public['service_area'] = self::fields($area, ['mode','radius_miles']);
        $public['service_area']['base_location'] = self::fields($area['base_location'] ?? [], ['city','state','postal_code','country']);
        if (($area['customers_visit_business'] ?? false) === true) {
            $public['service_area']['base_location'] += self::fields($area['base_location'] ?? [], ['line_1']);
        }
        $faqs = $facts['faqs'] ?? [];
        if (count($faqs) > 200) SiteArtifactFiles::fail('input_mismatch');
        foreach ($faqs as $faq) {
            if (in_array($faq['channel_scope'] ?? 'website', ['all','website'], true)) $public['faqs'][] = self::fields($faq, ['question','answer']);
        }
        $composition = $source['composition'];
        $pages = $composition['pages'];
        if (!array_is_list($pages) || count($pages) < 1 || count($pages) > 100) SiteArtifactFiles::fail('input_mismatch');
        $result = ['validated_for_rendering' => true, 'pages' => [], 'theme' => []];
        $routes = []; $home = 0;
        foreach ($pages as $page) {
            $route = SiteArtifactFiles::route($page['slug']);
            if (isset($routes[$route])) SiteArtifactFiles::fail('input_mismatch');
            $routes[$route] = true;
            if ($page['page_type'] === 'home') { $home++; if ($page['slug'] !== '') SiteArtifactFiles::fail('input_mismatch'); }
            elseif ($page['slug'] === '') SiteArtifactFiles::fail('input_mismatch');
            $p = self::fields($page, ['page_key','title','slug','page_type','navigation_label','sort_order']);
            $p['presentation'] = self::fields($page['presentation'], ['layout_width','show_in_navigation']);
            $p['seo'] = self::fields($page['seo'], ['title','description','robots','canonical_policy']);
            $p['sections'] = [];
            if (count($page['sections']) > 100) SiteArtifactFiles::fail('input_mismatch');
            foreach ($page['sections'] as $section) {
                $p['sections'][] = self::selection($section, 'section') + self::fields($section, ['section_key','sort_order']);
            }
            $result['pages'][] = $p;
        }
        if ($home !== 1) SiteArtifactFiles::fail('input_mismatch');
        $theme = $composition['theme'];
        if ($theme['theme_key'] !== 'local_service' || $theme['theme_version'] !== 1) SiteArtifactFiles::fail('builder_unavailable');
        $schemas = ThemeRegistry::authoringSchemas();
        $result['theme'] = self::fields($theme, ['theme_key','theme_version']);
        foreach (['primary_color'=>'color','secondary_color'=>'color','typography'=>'typography','configuration'=>'configuration'] as $field => $schema) {
            $result['theme'][$field] = ComponentSchemaValidator::validate($theme[$field], $schemas[$schema], 'theme.' . $field);
        }
        foreach ($result['theme']['configuration']['layouts'] as $slot => &$selection) {
            if ($slot !== $selection['component_key']) SiteArtifactFiles::fail('input_mismatch');
            $selection = self::selection($selection, 'layout');
        }
        unset($selection);
        $projection = ['public_facts' => $public, 'public_composition' => $result];
        SiteBuildContract::json($projection, 1048576);
        return $projection;
    }

    private static function fields(array $from, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) if (array_key_exists($key, $from)) {
            $v = $from[$key];
            if (!is_scalar($v) && $v !== null) SiteArtifactFiles::fail('input_mismatch');
            if (is_string($v) && (strlen($v) > 16000 || preg_match('//u', $v) !== 1)) SiteArtifactFiles::fail('input_mismatch');
            $out[$key] = $v;
        }
        return $out;
    }

    private static function selection(array $s, string $scope): array
    {
        $out = self::fields($s, ['component_key','implementation_version','variant_key','configuration_schema_version']);
        $definition = ComponentRegistry::definition($s['component_key'], $s['implementation_version']);
        if ($definition['scope'] !== $scope || !$definition['renderable']) SiteArtifactFiles::fail('builder_unavailable');
        // Repository schemas reject unknown properties recursively; no passthrough configuration is exported.
        $out['configuration'] = ComponentRegistry::validateConfiguration($definition, $s['variant_key'], $s['configuration_schema_version'], $s['configuration']);
        return $out;
    }

    /** @return array{asset_urls:array,phone_label:string,email_label:string,call_href:?string,email_href:?string,contact_href:?string} */
    public static function renderer(array $input): array
    {
        $business = $input['public_facts']['business'];
        $phone = (string) ($business['phone'] ?? ''); $email = (string) ($business['email'] ?? '');
        $context = ['asset_urls' => [], 'phone_label' => $phone, 'email_label' => $email,
            'call_href' => preg_match('/^\+?[0-9() .-]{3,30}$/D', $phone) ? 'tel:' . $phone : null,
            'email_href' => filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/[?&#%]/', $email) ? 'mailto:' . $email : null,
            'contact_href' => null];
        foreach ($input['public_composition']['pages'] as $page) if ($page['page_type'] === 'contact') $context['contact_href'] = '/' . $page['slug'];
        foreach ($input['ordered_asset_digests'] as $asset) {
            $context['asset_urls'][$asset['usage_key']] = '/assets/' . $asset['sha256'] . '.' . SiteArtifactValidator::extension($asset['mime_type']);
        }
        return $context;
    }
}
