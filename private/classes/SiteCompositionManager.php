<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteRevisionManager.php';
require_once __DIR__ . '/SiteCompositionValidator.php';

final class SiteCompositionManager
{
    public static function replaceDraftComposition(
        int $actingUserId,
        int $revisionId,
        array $input,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        if (!array_key_exists('expected_snapshot_hash', $input)) {
            throw new SiteServiceException('invalid_request', 'Expected snapshot hash is required.');
        }
        $expectedHash = SiteServiceSupport::assertSnapshotHash((string) $input['expected_snapshot_hash']);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actor, $revisionId, $input, $expectedHash, $correlationId
        ): array {
            $siteLookup = $connection->prepare(
                '/* site-m3:composition-revision-site */
                 SELECT site_id FROM site_revisions WHERE id = :revision_id LIMIT 1'
            );
            $siteLookup->execute(['revision_id' => $revisionId]);
            $siteId = $siteLookup->fetchColumn();
            if ($siteId === false) {
                throw new SiteServiceException('not_found', 'The revision was not found.');
            }
            $siteId = (int) $siteId;
            $revision = SiteRevisionManager::lockMutableRevisionForComposition($connection, $siteId, $revisionId);
            if (!hash_equals((string) $revision['snapshot_hash'], $expectedHash)) {
                throw new SiteServiceException('stale_write', 'The revision composition changed after it was loaded.');
            }
            $site = ['id' => $siteId, 'purpose' => self::sitePurpose($connection, $siteId)];
            $normalized = SiteCompositionValidator::normalizeForAuthoring($connection, $site, $input);
            $stablePageIds = self::stablePageIds($connection, $siteId, $normalized['pages']);

            self::deleteRevisionComposition($connection, $siteId, $revisionId);
            [$pageIds, $sectionIds] = self::insertPagesAndSections(
                $connection, $siteId, $revisionId, $normalized['pages'], $stablePageIds
            );
            self::insertTheme($connection, $siteId, $revisionId, $normalized['theme']);
            self::insertAssets($connection, $siteId, $revisionId, $normalized['assets'], $pageIds, $sectionIds);

            $newHash = SiteRevisionSnapshotHasher::hashStoredRevision($connection, $revisionId);
            SiteRevisionManager::applyCompositionSnapshotHash($connection, $revision, $newHash);
            $componentCounts = [];
            $sectionCount = 0;
            foreach ($normalized['pages'] as $page) {
                foreach ($page['sections'] as $section) {
                    $identity = $section['component_key'] . '@' . $section['implementation_version'];
                    $componentCounts[$identity] = ($componentCounts[$identity] ?? 0) + 1;
                    $sectionCount++;
                }
            }
            ksort($componentCounts, SORT_STRING);
            SiteServiceSupport::event(
                $connection, $siteId, $revisionId, $actor,
                'site_revision_composition_replaced', $correlationId, null,
                [
                    'page_count' => count($normalized['pages']),
                    'section_count' => $sectionCount,
                    'asset_reference_count' => count($normalized['assets']),
                    'previous_snapshot_hash' => $expectedHash,
                    'new_snapshot_hash' => $newHash,
                    'component_identity_counts' => $componentCounts,
                ]
            );
            return [
                'revision_id' => $revisionId, 'site_id' => $siteId,
                'snapshot_hash' => $newHash, 'page_count' => count($normalized['pages']),
                'section_count' => $sectionCount, 'asset_reference_count' => count($normalized['assets']),
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function compositionForActor(int $actingUserId, int $revisionId): array
    {
        $revision = SiteRevisionManager::revisionForActor($actingUserId, $revisionId);
        return SiteServiceSupport::read(static function (object $connection) use ($revision): array {
            $state = $connection->prepare(
                '/* site-m3:composition-state */
                 SELECT
                   (SELECT COUNT(*) FROM site_revision_pages WHERE revision_id = :page_revision_id) AS page_count,
                   (SELECT COUNT(*) FROM site_themes WHERE revision_id = :theme_revision_id) AS theme_count,
                   (SELECT COUNT(*) FROM site_revision_assets WHERE revision_id = :asset_revision_id) AS asset_count'
            );
            $state->execute([
                'page_revision_id' => (int) $revision['id'],
                'theme_revision_id' => (int) $revision['id'],
                'asset_revision_id' => (int) $revision['id'],
            ]);
            $counts = $state->fetch();
            $base = [
                'revision_id' => (int) $revision['id'], 'site_id' => (int) $revision['site_id'],
                'revision_number' => (int) $revision['revision_number'],
                'lifecycle_status' => (string) $revision['lifecycle_status'],
                'snapshot_hash' => (string) $revision['snapshot_hash'],
            ];
            if ((int) ($counts['page_count'] ?? 0) === 0
                && (int) ($counts['theme_count'] ?? 0) === 0
                && (int) ($counts['asset_count'] ?? 0) === 0) {
                return $base + ['composition_state' => 'empty', 'pages' => [], 'theme' => null, 'assets' => []];
            }
            $representation = SiteRevisionSnapshotHasher::storedRepresentation($connection, (int) $revision['id']);
            $assetIds = [];
            $assetStatement = $connection->prepare(
                '/* site-m3:composition-editor-assets */
                 SELECT asset_id, usage_key FROM site_revision_assets
                 WHERE revision_id = :revision_id ORDER BY usage_key'
            );
            $assetStatement->execute(['revision_id' => (int) $revision['id']]);
            foreach ($assetStatement->fetchAll() as $assetRow) {
                $assetIds[(string) $assetRow['usage_key']] = (int) $assetRow['asset_id'];
            }
            return $base + [
                'composition_state' => 'composed',
                'pages' => $representation['pages'], 'theme' => $representation['theme'],
                'assets' => array_map(static fn (array $asset): array => [
                    'asset_id' => $assetIds[$asset['usage_key']],
                    'asset_type' => $asset['asset_type'], 'checksum_sha256' => $asset['checksum_sha256'],
                    'mime_type' => $asset['mime_type'], 'byte_size' => $asset['byte_size'],
                    'usage_key' => $asset['usage_key'], 'page_key' => $asset['page_key'],
                    'section_key' => $asset['section_key'],
                ], $representation['assets']),
            ];
        });
    }

    public static function validatedCompositionForActor(int $actingUserId, int $revisionId): array
    {
        $revision = SiteRevisionManager::revisionForActor($actingUserId, $revisionId);
        return SiteServiceSupport::read(static function (object $connection) use ($revision): array {
            $site = [
                'id' => (int) $revision['site_id'],
                'purpose' => self::sitePurpose($connection, (int) $revision['site_id']),
            ];
            $validated = SiteCompositionValidator::validateStoredRevision(
                $connection,
                $site,
                $revision,
                SiteCompositionValidator::MODE_RENDER_READ
            );
            return [
                'revision_id' => (int) $revision['id'],
                'site_id' => (int) $revision['site_id'],
                'snapshot_hash' => (string) $validated['snapshot_hash'],
                'composition_state' => 'composed',
                'validated_for_rendering' => true,
                'historical' => ($validated['historical'] ?? false) === true,
                'legacy_compatibility' => ($validated['legacy_compatibility'] ?? false) === true,
                'pages' => array_map([self::class, 'renderPage'], $validated['pages']),
                'theme' => self::renderTheme($validated['theme']),
                'assets' => array_map(static fn (array $asset): array => [
                    'usage_key' => (string) $asset['usage_key'],
                    'asset_type' => (string) $asset['asset_type'],
                    'mime_type' => (string) $asset['mime_type'],
                    'checksum_sha256' => (string) $asset['checksum_sha256'],
                    'byte_size' => (int) $asset['byte_size'],
                    'page_key' => $asset['page_key'],
                    'section_key' => $asset['section_key'],
                ], $validated['assets']),
            ];
        });
    }

    private static function renderPage(array $page): array
    {
        return [
            'page_key' => (string) $page['page_key'], 'title' => (string) $page['title'],
            'slug' => (string) $page['slug'], 'page_type' => (string) $page['page_type'],
            'navigation_label' => $page['navigation_label'], 'sort_order' => (int) $page['sort_order'],
            'seo' => $page['seo'], 'presentation' => $page['presentation'],
            'sections' => array_map([self::class, 'renderSelection'], $page['sections']),
        ];
    }

    private static function renderTheme(array $theme): array
    {
        $configuration = $theme['configuration'];
        foreach (['site_header', 'site_footer', 'mobile_cta'] as $layoutKey) {
            if (isset($configuration['layouts'][$layoutKey])) {
                $configuration['layouts'][$layoutKey] = self::renderSelection($configuration['layouts'][$layoutKey]);
            }
        }
        return [
            'theme_key' => (string) $theme['theme_key'], 'theme_version' => (int) $theme['theme_version'],
            'primary_color' => $theme['primary_color'], 'secondary_color' => $theme['secondary_color'],
            'typography' => $theme['typography'], 'configuration' => $configuration,
        ];
    }

    private static function renderSelection(array $selection): array
    {
        return [
            'section_key' => $selection['section_key'] ?? null,
            'component_key' => (string) $selection['component_key'],
            'implementation_version' => (string) $selection['implementation_version'],
            'variant_key' => (string) $selection['variant_key'],
            'configuration_schema_version' => (int) $selection['configuration_schema_version'],
            'sort_order' => isset($selection['sort_order']) ? (int) $selection['sort_order'] : null,
            'configuration' => $selection['configuration'],
        ];
    }

    private static function sitePurpose(object $connection, int $siteId): string
    {
        $statement = $connection->prepare('SELECT purpose FROM sites WHERE id = :site_id LIMIT 1');
        $statement->execute(['site_id' => $siteId]);
        $purpose = $statement->fetchColumn();
        if ($purpose === false) {
            throw new SiteServiceException('not_found', 'The site was not found.');
        }
        return (string) $purpose;
    }

    private static function stablePageIds(object $connection, int $siteId, array $pages): array
    {
        $ids = [];
        foreach ($pages as $page) {
            $statement = $connection->prepare(
                'SELECT id, retired_at FROM site_pages
                 WHERE site_id = :site_id AND page_key = :page_key LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['site_id' => $siteId, 'page_key' => $page['page_key']]);
            $row = $statement->fetch();
            if ($row) {
                if ($row['retired_at'] !== null) {
                    throw new SiteServiceException('conflict', 'A retired logical page cannot be reused.');
                }
                $ids[$page['page_key']] = (int) $row['id'];
                continue;
            }
            $insert = $connection->prepare(
                'INSERT INTO site_pages (site_id, page_key, created_at)
                 VALUES (:site_id, :page_key, NOW())'
            );
            $insert->execute(['site_id' => $siteId, 'page_key' => $page['page_key']]);
            $ids[$page['page_key']] = (int) $connection->lastInsertId();
        }
        return $ids;
    }

    private static function deleteRevisionComposition(object $connection, int $siteId, int $revisionId): void
    {
        foreach (['site_revision_assets', 'site_page_sections', 'site_revision_pages', 'site_themes'] as $table) {
            $statement = $connection->prepare(
                "DELETE FROM {$table} WHERE site_id = :site_id AND revision_id = :revision_id"
            );
            $statement->execute(['site_id' => $siteId, 'revision_id' => $revisionId]);
        }
    }

    private static function insertPagesAndSections(object $connection, int $siteId, int $revisionId, array $pages, array $stablePageIds): array
    {
        $pageIds = [];
        $sectionIds = [];
        foreach ($pages as $page) {
            $insertPage = $connection->prepare(
                'INSERT INTO site_revision_pages (
                    site_id, revision_id, site_page_id, title, slug, page_type,
                    navigation_label, sort_order, seo_json, presentation_json, content_hash, created_at
                 ) VALUES (
                    :site_id, :revision_id, :site_page_id, :title, :slug, :page_type,
                    :navigation_label, :sort_order, :seo_json, :presentation_json, :content_hash, NOW()
                 )'
            );
            $insertPage->execute([
                'site_id' => $siteId, 'revision_id' => $revisionId,
                'site_page_id' => $stablePageIds[$page['page_key']], 'title' => $page['title'],
                'slug' => $page['slug'], 'page_type' => $page['page_type'],
                'navigation_label' => $page['navigation_label'], 'sort_order' => $page['sort_order'],
                'seo_json' => CanonicalJson::encode($page['seo']),
                'presentation_json' => CanonicalJson::encode($page['presentation']),
                'content_hash' => $page['content_hash'],
            ]);
            $pageIds[$page['page_key']] = (int) $connection->lastInsertId();
            foreach ($page['sections'] as $section) {
                $insertSection = $connection->prepare(
                    'INSERT INTO site_page_sections (
                        site_id, revision_id, revision_page_id, section_key, component_variant_id,
                        sort_order, configuration_schema_version, configuration_json, content_hash, created_at
                     ) VALUES (
                        :site_id, :revision_id, :revision_page_id, :section_key, :component_variant_id,
                        :sort_order, :configuration_schema_version, :configuration_json, :content_hash, NOW()
                     )'
                );
                $insertSection->execute([
                    'site_id' => $siteId, 'revision_id' => $revisionId,
                    'revision_page_id' => $pageIds[$page['page_key']], 'section_key' => $section['section_key'],
                    'component_variant_id' => $section['variant_id'], 'sort_order' => $section['sort_order'],
                    'configuration_schema_version' => $section['configuration_schema_version'],
                    'configuration_json' => CanonicalJson::encode($section['configuration']),
                    'content_hash' => $section['content_hash'],
                ]);
                $sectionIds[$page['page_key']][$section['section_key']] = (int) $connection->lastInsertId();
            }
        }
        return [$pageIds, $sectionIds];
    }

    private static function insertTheme(object $connection, int $siteId, int $revisionId, array $theme): void
    {
        $statement = $connection->prepare(
            'INSERT INTO site_themes (
                site_id, revision_id, theme_key, theme_version, primary_color, secondary_color,
                typography_json, configuration_json, content_hash, created_at
             ) VALUES (
                :site_id, :revision_id, :theme_key, :theme_version, :primary_color, :secondary_color,
                :typography_json, :configuration_json, :content_hash, NOW()
             )'
        );
        $statement->execute([
            'site_id' => $siteId, 'revision_id' => $revisionId,
            'theme_key' => $theme['theme_key'], 'theme_version' => $theme['theme_version'],
            'primary_color' => $theme['primary_color'], 'secondary_color' => $theme['secondary_color'],
            'typography_json' => CanonicalJson::encode($theme['typography']),
            'configuration_json' => CanonicalJson::encode($theme['configuration']),
            'content_hash' => $theme['content_hash'],
        ]);
    }

    private static function insertAssets(object $connection, int $siteId, int $revisionId, array $assets, array $pageIds, array $sectionIds): void
    {
        foreach ($assets as $asset) {
            $pageId = $asset['page_key'] === null ? null : ($pageIds[$asset['page_key']] ?? null);
            $sectionId = $asset['section_key'] === null ? null : ($sectionIds[$asset['page_key']][$asset['section_key']] ?? null);
            if (($asset['page_key'] !== null && $pageId === null) || ($asset['section_key'] !== null && $sectionId === null)) {
                throw new SiteServiceException('conflict', 'Asset target could not be mapped to stored composition.');
            }
            $statement = $connection->prepare(
                'INSERT INTO site_revision_assets (
                    site_id, revision_id, asset_id, usage_key, site_revision_page_id,
                    site_page_section_id, source_reference, created_at
                 ) VALUES (
                    :site_id, :revision_id, :asset_id, :usage_key, :revision_page_id,
                    :section_id, :source_reference, NOW()
                 )'
            );
            $statement->execute([
                'site_id' => $siteId, 'revision_id' => $revisionId, 'asset_id' => $asset['asset_id'],
                'usage_key' => $asset['usage_key'], 'revision_page_id' => $pageId,
                'section_id' => $sectionId, 'source_reference' => $asset['source_reference'],
            ]);
        }
    }
}
