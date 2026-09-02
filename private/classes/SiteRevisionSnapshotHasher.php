<?php

declare(strict_types=1);

require_once __DIR__ . '/CanonicalJson.php';
require_once __DIR__ . '/SiteServiceSupport.php';

final class SiteRevisionSnapshotHasher
{
    public const MODE_GENERIC = 'generic';
    public const MODE_LEGACY_M1 = 'legacy_m1';

    public static function hashStoredRevision(object $connection, int $revisionId, string $mode = self::MODE_GENERIC): string
    {
        return CanonicalJson::hash(self::storedRepresentation($connection, $revisionId, $mode));
    }

    public static function storedRepresentation(object $connection, int $revisionId, string $mode = self::MODE_GENERIC): array
    {
        if (!in_array($mode, [self::MODE_GENERIC, self::MODE_LEGACY_M1], true)) {
            throw new InvalidArgumentException('Unknown revision snapshot hash mode.');
        }
        $revision = self::one($connection,
            '/* legacy-import:hash-revision */ SELECT snapshot_schema_version, facts_snapshot_json,
                    source_references_json, generation_brief_id
             FROM site_revisions WHERE id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        if ($revision === null) {
            throw new SiteServiceException('not_found', 'The revision snapshot was not found.');
        }

        $generationBrief = null;
        if ($revision['generation_brief_id'] !== null) {
            $brief = self::one($connection,
                '/* legacy-import:hash-brief */ SELECT brief_version, state, brief_json, source_type,
                        source_reference, content_hash
                 FROM site_generation_briefs WHERE id = :brief_id LIMIT 1',
                ['brief_id' => (int) $revision['generation_brief_id']]
            );
            if ($brief === null) {
                throw new SiteServiceException('conflict', 'The revision generation brief is missing.');
            }
            $generationBrief = [
                'brief_version' => (int) $brief['brief_version'],
                'state' => (string) $brief['state'],
                'brief' => self::json($brief['brief_json']),
                'source_type' => (string) $brief['source_type'],
                'source_reference' => self::nullableString($brief['source_reference'], $mode),
                'content_hash' => (string) $brief['content_hash'],
            ];
        }

        $pages = self::all($connection,
            '/* legacy-import:hash-pages */ SELECT rp.id, p.page_key, rp.title, rp.slug, rp.page_type,
                    navigation_label, sort_order, seo_json, presentation_json, content_hash
             FROM site_revision_pages rp
             INNER JOIN site_pages p ON p.id = rp.site_page_id AND p.site_id = rp.site_id
             WHERE rp.revision_id = :revision_id ORDER BY rp.sort_order, rp.id',
            ['revision_id' => $revisionId]
        );
        foreach ($pages as &$page) {
            $sections = self::all($connection,
                '/* legacy-import:hash-sections */ SELECT s.section_key, cd.component_key,
                        cd.implementation_version, cv.variant_key,
                        cv.configuration_schema_version AS variant_configuration_schema_version,
                        s.sort_order, s.configuration_schema_version, s.configuration_json, s.content_hash
                 FROM site_page_sections s
                 INNER JOIN component_variants cv ON cv.id = s.component_variant_id
                 INNER JOIN component_definitions cd ON cd.id = cv.component_definition_id
                 WHERE s.revision_page_id = :revision_page_id ORDER BY s.sort_order, s.id',
                ['revision_page_id' => (int) $page['id']]
            );
            foreach ($sections as &$section) {
                $section = [
                    'section_key' => (string) $section['section_key'],
                    'component_key' => (string) $section['component_key'],
                    'implementation_version' => (string) $section['implementation_version'],
                    'variant_key' => (string) $section['variant_key'],
                    'variant_configuration_schema_version' => (int) $section['variant_configuration_schema_version'],
                    'sort_order' => (int) $section['sort_order'],
                    'configuration_schema_version' => (int) $section['configuration_schema_version'],
                    'configuration' => self::json($section['configuration_json']),
                    'content_hash' => (string) $section['content_hash'],
                ];
            }
            unset($section);
            $page = [
                'page_key' => (string) $page['page_key'],
                'title' => (string) $page['title'],
                'slug' => (string) $page['slug'],
                'page_type' => (string) $page['page_type'],
                'navigation_label' => self::nullableString($page['navigation_label'], $mode),
                'sort_order' => (int) $page['sort_order'],
                'seo' => self::json($page['seo_json']),
                'presentation' => self::json($page['presentation_json']),
                'content_hash' => (string) $page['content_hash'],
                'sections' => $sections,
            ];
        }
        unset($page);

        $themeRow = self::one($connection,
            '/* legacy-import:hash-theme */ SELECT theme_key, theme_version, primary_color,
                    secondary_color, typography_json, configuration_json, content_hash
             FROM site_themes WHERE revision_id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        if ($themeRow === null) {
            throw new SiteServiceException('conflict', 'The revision theme is missing.');
        }
        $theme = [
            'theme_key' => (string) $themeRow['theme_key'],
            'theme_version' => (int) $themeRow['theme_version'],
            'primary_color' => self::nullableString($themeRow['primary_color'], $mode),
            'secondary_color' => self::nullableString($themeRow['secondary_color'], $mode),
            'typography' => self::json($themeRow['typography_json']),
            'configuration' => self::json($themeRow['configuration_json']),
            'content_hash' => (string) $themeRow['content_hash'],
        ];

        $assets = self::all($connection,
            '/* legacy-import:hash-assets */ SELECT a.asset_type, a.storage_key, a.checksum_sha256,
                    a.mime_type, a.byte_size, ra.usage_key, ra.source_reference,
                    p.page_key, s.section_key
             FROM site_revision_assets ra
             INNER JOIN site_assets a ON a.id = ra.asset_id
             LEFT JOIN site_revision_pages rp ON rp.id = ra.site_revision_page_id
             LEFT JOIN site_pages p ON p.id = rp.site_page_id
             LEFT JOIN site_page_sections s ON s.id = ra.site_page_section_id
             WHERE ra.revision_id = :revision_id ORDER BY ra.usage_key, a.storage_key, p.page_key',
            ['revision_id' => $revisionId]
        );
        foreach ($assets as &$asset) {
            $asset = [
                'asset_type' => (string) $asset['asset_type'],
                'storage_key' => (string) $asset['storage_key'],
                'checksum_sha256' => (string) $asset['checksum_sha256'],
                'mime_type' => (string) $asset['mime_type'],
                'byte_size' => (int) $asset['byte_size'],
                'usage_key' => (string) $asset['usage_key'],
                'source_reference' => self::nullableString($asset['source_reference'], $mode),
                'page_key' => self::nullableString($asset['page_key'], $mode),
                'section_key' => self::nullableString($asset['section_key'], $mode),
            ];
        }
        unset($asset);

        return [
            'schema_version' => (int) $revision['snapshot_schema_version'],
            'facts_snapshot' => self::json($revision['facts_snapshot_json']),
            'source_references' => self::json($revision['source_references_json']),
            'generation_brief' => $generationBrief,
            'pages' => $pages,
            'theme' => $theme,
            'assets' => $assets,
        ];
    }

    private static function one(object $connection, string $sql, array $parameters): ?array
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetch() ?: null;
    }

    private static function all(object $connection, string $sql, array $parameters): array
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private static function json(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function nullableString(mixed $value, string $mode): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = (string) $value;
        if ($mode === self::MODE_LEGACY_M1) {
            $string = trim($string);
            return $string === '' ? null : $string;
        }
        return $string;
    }
}
