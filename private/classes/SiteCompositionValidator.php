<?php

declare(strict_types=1);

require_once __DIR__ . '/ThemeRegistry.php';
require_once __DIR__ . '/SiteRevisionSnapshotHasher.php';

final class SiteCompositionValidator
{
    public const PAGE_TYPES = ['home', 'service', 'about', 'contact', 'landing', 'standard', 'legal'];
    private const RIGHTS_ALLOWED = ['platform_owned', 'customer_owned', 'customer_licensed_for_site', 'third_party_licensed'];

    public static function normalizeForAuthoring(object $connection, array $site, array $input): array
    {
        $allowed = ['expected_snapshot_hash', 'pages', 'theme'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new SiteServiceException('invalid_request', 'Composition input contains an unknown field.');
        }
        if (!isset($input['pages']) || !is_array($input['pages']) || !array_is_list($input['pages']) || $input['pages'] === []) {
            throw new SiteServiceException('invalid_request', 'Composition requires at least one page.');
        }
        if (!isset($input['theme']) || !is_array($input['theme'])) {
            throw new SiteServiceException('invalid_request', 'Composition requires one theme.');
        }
        return self::normalize($connection, $site, $input['pages'], $input['theme'], true);
    }

    public static function validateStoredRevision(object $connection, array $site, array $revision): array
    {
        $loaded = self::loadStoredInput($connection, (int) $site['id'], (int) $revision['id']);
        if (self::isLegacySnapshot($loaded['pages'])) {
            return self::validateLegacyStoredRevision($connection, $site, $revision, $loaded);
        }
        $forAuthoring = (string) $revision['lifecycle_status'] !== 'restored';
        $normalized = self::normalize($connection, $site, $loaded['pages'], $loaded['theme'], $forAuthoring);

        foreach ($normalized['pages'] as $page) {
            $storedPage = $loaded['stored_page_hashes'][$page['page_key']] ?? null;
            if ($storedPage === null || !hash_equals($storedPage, $page['content_hash'])) {
                throw new SiteServiceException('conflict', 'Stored page content hash does not match its canonical content.');
            }
            foreach ($page['sections'] as $section) {
                $storedSection = $loaded['stored_section_hashes'][$page['page_key']][$section['section_key']] ?? null;
                if ($storedSection === null || !hash_equals($storedSection, $section['content_hash'])) {
                    throw new SiteServiceException('conflict', 'Stored section content hash does not match its canonical content.');
                }
            }
        }
        if (!hash_equals((string) $loaded['stored_theme_hash'], (string) $normalized['theme']['content_hash'])) {
            throw new SiteServiceException('conflict', 'Stored theme content hash does not match its canonical content.');
        }
        $actual = SiteRevisionSnapshotHasher::hashStoredRevision($connection, (int) $revision['id']);
        if (!hash_equals((string) $revision['snapshot_hash'], $actual)) {
            throw new SiteServiceException('conflict', 'Stored revision snapshot hash does not match its canonical composition.');
        }
        return $normalized + ['snapshot_hash' => $actual];
    }

    private static function normalize(object $connection, array $site, array $pagesInput, array $themeInput, bool $forAuthoring): array
    {
        $pages = [];
        $pageKeys = [];
        $slugs = [];
        $pageOrders = [];
        $usageKeys = [];
        $allAssets = [];
        foreach ($pagesInput as $index => $pageInput) {
            if (!is_array($pageInput) || array_is_list($pageInput)) {
                throw new SiteServiceException('invalid_request', "pages[{$index}] must be an object.");
            }
            $page = self::normalizePage($connection, $pageInput, $forAuthoring, $index);
            foreach ([[$pageKeys, $page['page_key'], 'page_key'], [$slugs, $page['slug'], 'slug'], [$pageOrders, (string) $page['sort_order'], 'sort_order']] as $duplicate) {
                if (isset($duplicate[0][$duplicate[1]])) {
                    throw new SiteServiceException('invalid_request', "Composition contains a duplicate page {$duplicate[2]}.");
                }
            }
            $pageKeys[$page['page_key']] = true;
            $slugs[$page['slug']] = true;
            $pageOrders[(string) $page['sort_order']] = true;
            foreach ($page['sections'] as $section) {
                foreach ($section['assets'] as $assetInput) {
                    self::registerUsage($usageKeys, $assetInput['usage_key']);
                    $asset = self::validateAsset($connection, $site, $assetInput);
                    $asset['page_key'] = $page['page_key'];
                    $asset['section_key'] = $section['section_key'];
                    $allAssets[] = $asset;
                }
            }
            $pages[] = $page;
        }

        usort($pages, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        self::assertPurposePageRules((string) $site['purpose'], $pages);

        $theme = ThemeRegistry::normalize($connection, $themeInput, $forAuthoring);
        foreach ($theme['assets'] as $assetInput) {
            self::registerUsage($usageKeys, $assetInput['usage_key']);
            $asset = self::validateAsset($connection, $site, $assetInput);
            $asset['page_key'] = null;
            $asset['section_key'] = null;
            $allAssets[] = $asset;
        }

        $assetsByUsage = [];
        foreach ($allAssets as $asset) {
            $assetsByUsage[$asset['usage_key']] = $asset;
        }
        foreach ($pages as &$page) {
            foreach ($page['sections'] as &$section) {
                self::assertAssetRequirements($section, $assetsByUsage, $page['page_key']);
                $section['content_hash'] = CanonicalJson::hash([
                    'component_key' => $section['component_key'],
                    'implementation_version' => $section['implementation_version'],
                    'variant_key' => $section['variant_key'],
                    'configuration_schema_version' => $section['configuration_schema_version'],
                    'configuration' => $section['configuration'],
                ]);
            }
            unset($section);
            $page['content_hash'] = CanonicalJson::hash([
                'page_key' => $page['page_key'], 'title' => $page['title'], 'slug' => $page['slug'],
                'page_type' => $page['page_type'], 'navigation_label' => $page['navigation_label'],
                'sort_order' => $page['sort_order'], 'seo' => $page['seo'],
                'presentation' => $page['presentation'],
                'sections' => array_map(static fn (array $section): array => [
                    'section_key' => $section['section_key'],
                    'component_key' => $section['component_key'],
                    'implementation_version' => $section['implementation_version'],
                    'variant_key' => $section['variant_key'],
                    'sort_order' => $section['sort_order'],
                    'content_hash' => $section['content_hash'],
                ], $page['sections']),
            ]);
        }
        unset($page);
        self::assertThemeAssetRequirements($theme, $assetsByUsage);
        $theme['content_hash'] = ThemeRegistry::contentHash($theme);
        return ['pages' => $pages, 'theme' => $theme, 'assets' => $allAssets];
    }

    private static function normalizePage(object $connection, array $input, bool $forAuthoring, int $index): array
    {
        $allowed = ['page_key', 'title', 'slug', 'page_type', 'navigation_label', 'sort_order', 'seo', 'presentation', 'sections'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new SiteServiceException('invalid_request', "pages[{$index}] contains an unknown field.");
        }
        $schema = [
            'type' => 'object', 'required' => ['page_key', 'title', 'slug', 'page_type', 'sort_order', 'sections'],
            'properties' => [
                'page_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                'slug' => ['type' => 'string', 'format' => 'slug', 'minLength' => 0, 'maxLength' => 255],
                'page_type' => ['type' => 'string', 'enum' => self::PAGE_TYPES],
                'navigation_label' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 150],
                'sort_order' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000],
                'seo' => self::seoSchema(), 'presentation' => self::presentationSchema(),
                'sections' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => self::sectionInputSchema()],
            ],
        ];
        $input += [
            'navigation_label' => null,
            'seo' => ['robots' => 'index_follow', 'canonical_policy' => 'self'],
            'presentation' => ['layout_width' => 'standard', 'show_in_navigation' => true],
        ];
        $page = ComponentSchemaValidator::validate($input, $schema, "pages[{$index}]");
        $sections = [];
        $keys = [];
        $orders = [];
        $counts = [];
        foreach ($page['sections'] as $sectionIndex => $sectionInput) {
            $definition = ComponentRegistry::resolve(
                $connection,
                $sectionInput['component_key'], $sectionInput['implementation_version'],
                $sectionInput['variant_key'], $sectionInput['configuration_schema_version'],
                'section', $forAuthoring
            );
            if (!in_array($page['page_type'], $definition['allowed_page_types'], true)) {
                throw new SiteServiceException('invalid_request', 'The component is not allowed on this page type.');
            }
            if (isset($keys[$sectionInput['section_key']]) || isset($orders[(string) $sectionInput['sort_order']])) {
                throw new SiteServiceException('invalid_request', 'Page contains a duplicate section key or sort order.');
            }
            $keys[$sectionInput['section_key']] = true;
            $orders[(string) $sectionInput['sort_order']] = true;
            $componentKey = $sectionInput['component_key'];
            $counts[$componentKey] = ($counts[$componentKey] ?? 0) + 1;
            if ($counts[$componentKey] > (int) $definition['cardinality']) {
                throw new SiteServiceException('invalid_request', 'Component cardinality is exceeded on the page.');
            }
            $sections[] = [
                'section_key' => $sectionInput['section_key'],
                'component_key' => $componentKey,
                'implementation_version' => $sectionInput['implementation_version'],
                'variant_key' => $sectionInput['variant_key'],
                'configuration_schema_version' => $sectionInput['configuration_schema_version'],
                'sort_order' => $sectionInput['sort_order'],
                'configuration' => ComponentRegistry::validateConfiguration(
                    $definition, $sectionInput['variant_key'],
                    $sectionInput['configuration_schema_version'], $sectionInput['configuration']
                ),
                'assets' => $sectionInput['assets'] ?? [],
                'variant_id' => $definition['variant_id'],
                'asset_requirements' => ComponentRegistry::assetRequirements($definition, $sectionInput['variant_key']),
            ];
        }
        usort($sections, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        $page['sections'] = $sections;
        return $page;
    }

    private static function validateAsset(object $connection, array $site, array $input): array
    {
        $statement = $connection->prepare(
            '/* site-m3:resolve-asset */
             SELECT a.id, a.site_id, a.business_id, a.asset_type, a.storage_key,
                    a.checksum_sha256, a.mime_type, a.byte_size, a.rights_classification,
                    a.rights_expires_at, a.lifecycle_status,
                    sba.business_id AS active_business_id
             FROM site_assets a
             LEFT JOIN site_business_associations sba
               ON sba.site_id = a.site_id AND sba.association_role = :association_role
              AND sba.status = :association_status
             WHERE a.id = :asset_id AND a.site_id = :site_id
             LIMIT 1'
        );
        $statement->execute([
            'association_role' => 'customer', 'association_status' => 'active',
            'asset_id' => $input['asset_id'], 'site_id' => (int) $site['id'],
        ]);
        $asset = $statement->fetch();
        if (!$asset) {
            throw new SiteServiceException('invalid_request', 'Referenced asset is not owned by this site.');
        }
        if ((string) $asset['lifecycle_status'] !== 'ready') {
            throw new SiteServiceException('conflict', 'Referenced asset is not ready.');
        }
        if (!in_array((string) $asset['rights_classification'], self::RIGHTS_ALLOWED, true)) {
            throw new SiteServiceException('conflict', 'Referenced asset rights do not permit composition.');
        }
        if ($asset['rights_expires_at'] !== null && strtotime((string) $asset['rights_expires_at']) <= time()) {
            throw new SiteServiceException('conflict', 'Referenced asset rights have expired.');
        }
        if ((string) $site['purpose'] === '247sp'
            && in_array((string) $asset['rights_classification'], ['customer_owned', 'customer_licensed_for_site'], true)
            && ((int) ($asset['active_business_id'] ?? 0) < 1 || (int) $asset['business_id'] !== (int) $asset['active_business_id'])) {
            throw new SiteServiceException('conflict', 'Customer asset business ownership does not match the site.');
        }
        return [
            'asset_id' => (int) $asset['id'], 'usage_key' => $input['usage_key'],
            'source_reference' => $input['source_reference'] ?? null,
            'asset_type' => (string) $asset['asset_type'], 'storage_key' => (string) $asset['storage_key'],
            'checksum_sha256' => (string) $asset['checksum_sha256'], 'mime_type' => (string) $asset['mime_type'],
            'byte_size' => (int) $asset['byte_size'],
        ];
    }

    private static function assertAssetRequirements(array $section, array $assetsByUsage, string $pageKey): void
    {
        foreach ($section['asset_requirements'] as $requirement) {
            $usageKey = $section['configuration'][$requirement['field']] ?? null;
            if (($usageKey === null || $usageKey === '') && !$requirement['required']) {
                continue;
            }
            if (!is_string($usageKey) || !isset($assetsByUsage[$usageKey])) {
                throw new SiteServiceException('invalid_request', 'A required component asset usage is missing.');
            }
            $asset = $assetsByUsage[$usageKey];
            if ($asset['page_key'] !== $pageKey || $asset['section_key'] !== $section['section_key']) {
                throw new SiteServiceException('invalid_request', 'Component asset usage targets the wrong section.');
            }
            if ($asset['asset_type'] !== $requirement['asset_type']
                || isset($requirement['mime_type']) && $asset['mime_type'] !== $requirement['mime_type']) {
                throw new SiteServiceException('invalid_request', 'Component asset type is not permitted.');
            }
        }
    }

    private static function assertThemeAssetRequirements(array $theme, array $assetsByUsage): void
    {
        $header = $theme['configuration']['layouts']['site_header']['configuration'];
        $usage = $header['logo_usage_key'] ?? null;
        if ($usage !== null && (!isset($assetsByUsage[$usage]) || $assetsByUsage[$usage]['page_key'] !== null
            || $assetsByUsage[$usage]['asset_type'] !== 'image')) {
            throw new SiteServiceException('invalid_request', 'Header logo usage must reference a theme image asset.');
        }
    }

    private static function registerUsage(array &$usageKeys, string $usageKey): void
    {
        if (isset($usageKeys[$usageKey])) {
            throw new SiteServiceException('invalid_request', 'Asset usage keys must be unique within a revision.');
        }
        $usageKeys[$usageKey] = true;
    }

    private static function assertPurposePageRules(string $purpose, array $pages): void
    {
        $types = array_column($pages, 'page_type');
        if ($purpose === '247sp' && count(array_filter($types, static fn (string $type): bool => $type === 'home')) !== 1) {
            throw new SiteServiceException('invalid_request', 'A 247SP composition requires exactly one home page.');
        }
        if ($purpose === 'emd' && count(array_filter($types, static fn (string $type): bool => in_array($type, ['home', 'landing'], true))) !== 1) {
            throw new SiteServiceException('invalid_request', 'An EMD composition requires exactly one home or landing entry page.');
        }
    }

    private static function loadStoredInput(object $connection, int $siteId, int $revisionId): array
    {
        $assetStatement = $connection->prepare(
            '/* site-m3:load-stored-assets */
             SELECT sra.asset_id, sra.usage_key, sra.source_reference,
                    sp.page_key, sps.section_key
             FROM site_revision_assets sra
             LEFT JOIN site_revision_pages srp ON srp.id = sra.site_revision_page_id
             LEFT JOIN site_pages sp ON sp.id = srp.site_page_id
             LEFT JOIN site_page_sections sps ON sps.id = sra.site_page_section_id
             WHERE sra.site_id = :site_id AND sra.revision_id = :revision_id
             ORDER BY sra.usage_key'
        );
        $assetStatement->execute(['site_id' => $siteId, 'revision_id' => $revisionId]);
        $sectionAssets = [];
        $themeAssets = [];
        foreach ($assetStatement->fetchAll() as $asset) {
            $input = ['asset_id' => (int) $asset['asset_id'], 'usage_key' => (string) $asset['usage_key'], 'source_reference' => $asset['source_reference']];
            if ($asset['page_key'] === null && $asset['section_key'] === null) {
                $themeAssets[] = $input;
            } else {
                if ($asset['page_key'] === null || $asset['section_key'] === null) {
                    throw new SiteServiceException('conflict', 'Stored asset target is incomplete.');
                }
                $sectionAssets[(string) $asset['page_key']][(string) $asset['section_key']][] = $input;
            }
        }

        $pageStatement = $connection->prepare(
            '/* site-m3:load-stored-pages */
             SELECT srp.id, sp.page_key, srp.title, srp.slug, srp.page_type,
                    srp.navigation_label, srp.sort_order, srp.seo_json,
                    srp.presentation_json, srp.content_hash
             FROM site_revision_pages srp
             INNER JOIN site_pages sp ON sp.id = srp.site_page_id AND sp.site_id = srp.site_id
             WHERE srp.site_id = :site_id AND srp.revision_id = :revision_id
             ORDER BY srp.sort_order, srp.id'
        );
        $pageStatement->execute(['site_id' => $siteId, 'revision_id' => $revisionId]);
        $pages = [];
        $pageHashes = [];
        $sectionHashes = [];
        foreach ($pageStatement->fetchAll() as $row) {
            $pageKey = (string) $row['page_key'];
            $sectionStatement = $connection->prepare(
                '/* site-m3:load-stored-sections */
                 SELECT s.section_key, s.sort_order, s.configuration_schema_version,
                        s.configuration_json, s.content_hash, cd.component_key,
                        cd.implementation_version, cv.variant_key
                 FROM site_page_sections s
                 INNER JOIN component_variants cv ON cv.id = s.component_variant_id
                 INNER JOIN component_definitions cd ON cd.id = cv.component_definition_id
                 WHERE s.site_id = :site_id AND s.revision_id = :revision_id
                   AND s.revision_page_id = :revision_page_id
                 ORDER BY s.sort_order, s.id'
            );
            $sectionStatement->execute(['site_id' => $siteId, 'revision_id' => $revisionId, 'revision_page_id' => (int) $row['id']]);
            $sections = [];
            foreach ($sectionStatement->fetchAll() as $section) {
                $sectionKey = (string) $section['section_key'];
                $sections[] = [
                    'section_key' => $sectionKey, 'component_key' => (string) $section['component_key'],
                    'implementation_version' => (string) $section['implementation_version'],
                    'variant_key' => (string) $section['variant_key'],
                    'configuration_schema_version' => (int) $section['configuration_schema_version'],
                    'sort_order' => (int) $section['sort_order'],
                    'configuration' => self::decodeJson($section['configuration_json']),
                    'assets' => $sectionAssets[$pageKey][$sectionKey] ?? [],
                ];
                $sectionHashes[$pageKey][$sectionKey] = (string) $section['content_hash'];
            }
            $pages[] = [
                'page_key' => $pageKey, 'title' => (string) $row['title'], 'slug' => (string) $row['slug'],
                'page_type' => (string) $row['page_type'], 'navigation_label' => $row['navigation_label'],
                'sort_order' => (int) $row['sort_order'], 'seo' => self::decodeJson($row['seo_json']),
                'presentation' => self::decodeJson($row['presentation_json']), 'sections' => $sections,
            ];
            $pageHashes[$pageKey] = (string) $row['content_hash'];
        }
        $themeStatement = $connection->prepare(
            '/* site-m3:load-stored-theme */
             SELECT theme_key, theme_version, primary_color, secondary_color,
                    typography_json, configuration_json, content_hash
             FROM site_themes WHERE site_id = :site_id AND revision_id = :revision_id LIMIT 1'
        );
        $themeStatement->execute(['site_id' => $siteId, 'revision_id' => $revisionId]);
        $themeRow = $themeStatement->fetch();
        if (!$themeRow) {
            throw new SiteServiceException('conflict', 'Stored revision theme is missing.');
        }
        return [
            'pages' => $pages,
            'theme' => [
                'theme_key' => (string) $themeRow['theme_key'], 'theme_version' => (int) $themeRow['theme_version'],
                'primary_color' => $themeRow['primary_color'], 'secondary_color' => $themeRow['secondary_color'],
                'typography' => self::decodeJson($themeRow['typography_json']),
                'configuration' => self::decodeJson($themeRow['configuration_json']), 'assets' => $themeAssets,
            ],
            'stored_page_hashes' => $pageHashes, 'stored_section_hashes' => $sectionHashes,
            'stored_theme_hash' => (string) $themeRow['content_hash'],
        ];
    }

    private static function seoSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['robots', 'canonical_policy'],
            'properties' => [
                'title' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 255],
                'description' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 500],
                'robots' => ['type' => 'string', 'enum' => ['index_follow', 'noindex_follow', 'noindex_nofollow']],
                'canonical_policy' => ['type' => 'string', 'enum' => ['self', 'none']],
            ],
        ];
    }

    private static function presentationSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['layout_width', 'show_in_navigation'],
            'properties' => [
                'layout_width' => ['type' => 'string', 'enum' => ['narrow', 'standard', 'wide']],
                'show_in_navigation' => ['type' => 'boolean'],
            ],
        ];
    }

    private static function sectionInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['section_key', 'component_key', 'implementation_version', 'variant_key', 'configuration_schema_version', 'sort_order', 'configuration'],
            'properties' => [
                'section_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'component_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'implementation_version' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
                'variant_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'configuration_schema_version' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'sort_order' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000],
                'configuration' => ['type' => 'object', 'passthrough' => true],
                'assets' => ['type' => 'array', 'minItems' => 0, 'maxItems' => 25, 'items' => self::assetInputSchema()],
            ],
        ];
    }

    private static function assetInputSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['asset_id', 'usage_key'],
            'properties' => [
                'asset_id' => ['type' => 'integer', 'minimum' => 1],
                'usage_key' => ['type' => 'string', 'format' => 'asset-usage-key', 'minLength' => 1, 'maxLength' => 100],
                'source_reference' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 500],
            ],
        ];
    }

    private static function isLegacySnapshot(array $pages): bool
    {
        foreach ($pages as $page) {
            foreach ($page['sections'] as $section) {
                if ($section['component_key'] === 'legacy_247sp_page'
                    && $section['implementation_version'] === 'legacy-preview-v1') {
                    return true;
                }
            }
        }
        return false;
    }

    private static function validateLegacyStoredRevision(object $connection, array $site, array $revision, array $loaded): array
    {
        if ((string) $revision['lifecycle_status'] !== 'restored') {
            throw new SiteServiceException('conflict', 'Legacy snapshot components are compatibility-only.');
        }
        if ($loaded['pages'] === []) {
            throw new SiteServiceException('conflict', 'A restored revision requires at least one page.');
        }
        foreach ($loaded['pages'] as $page) {
            if ($page['sections'] === []) {
                throw new SiteServiceException('conflict', 'Every restored legacy page requires a snapshot section.');
            }
            foreach ($page['sections'] as $section) {
                $definition = ComponentRegistry::resolve(
                    $connection, $section['component_key'], $section['implementation_version'],
                    $section['variant_key'], $section['configuration_schema_version'], 'legacy', false
                );
                if (!$definition['snapshot_compatibility']) {
                    throw new SiteServiceException('conflict', 'Stored legacy component is not snapshot compatible.');
                }
                $calculated = CanonicalJson::hash($section['configuration']);
                if (!hash_equals((string) $loaded['stored_section_hashes'][$page['page_key']][$section['section_key']], $calculated)) {
                    throw new SiteServiceException('conflict', 'Stored legacy section hash does not match its snapshot.');
                }
                if (!hash_equals((string) $loaded['stored_page_hashes'][$page['page_key']], $calculated)) {
                    throw new SiteServiceException('conflict', 'Stored legacy page hash does not match its snapshot.');
                }
                foreach ($section['assets'] as $assetInput) {
                    self::validateAsset($connection, $site, $assetInput);
                }
            }
        }
        foreach ($loaded['theme']['assets'] as $assetInput) {
            self::validateAsset($connection, $site, $assetInput);
        }
        $themeHash = CanonicalJson::hash([
            'primary_color' => $loaded['theme']['primary_color'],
            'secondary_color' => $loaded['theme']['secondary_color'],
            'configuration' => $loaded['theme']['configuration'],
        ]);
        if (!hash_equals((string) $loaded['stored_theme_hash'], $themeHash)) {
            throw new SiteServiceException('conflict', 'Stored legacy theme hash does not match its snapshot.');
        }
        $actual = SiteRevisionSnapshotHasher::hashStoredRevision($connection, (int) $revision['id']);
        if (!hash_equals((string) $revision['snapshot_hash'], $actual)) {
            throw new SiteServiceException('conflict', 'Stored revision snapshot hash does not match its canonical composition.');
        }
        return ['pages' => $loaded['pages'], 'theme' => $loaded['theme'], 'snapshot_hash' => $actual, 'historical' => true];
    }

    private static function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_array($value) ? $value : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
}
