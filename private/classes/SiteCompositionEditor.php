<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteAuthoringCatalog.php';
require_once __DIR__ . '/SiteSchemaForm.php';

final class SiteCompositionEditor
{
    public static function workspace(int $actorId, int $revisionId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actorId);
        $revision = SiteRevisionManager::assertRevisionMutableForComposition($actorId, $revisionId);
        $composition = SiteCompositionManager::compositionForActor($actorId, $revisionId);
        return ['revision' => $revision, 'composition' => $composition,
            'catalog' => SiteAuthoringCatalog::forActor($actorId),
            'assets' => SiteAuthoringCatalog::assetsForActor($actorId, $revisionId)];
    }

    public static function pageFormSchema(bool $new): array
    {
        $schema = SiteCompositionValidator::pageSchema();
        unset($schema['properties']['sort_order'], $schema['properties']['sections']);
        if (!$new) {
            unset($schema['properties']['page_key']);
        }
        $schema['required'] = array_values(array_intersect($schema['required'], array_keys($schema['properties'])));
        return $schema;
    }

    public static function themeFormSchema(): array
    {
        $schemas = ThemeRegistry::authoringSchemas();
        $configuration = $schemas['configuration'];
        unset($configuration['properties']['layouts']);
        $configuration['required'] = array_values(array_diff($configuration['required'], ['layouts']));
        return ['type' => 'object', 'required' => ['primary_color', 'secondary_color', 'typography', 'configuration'],
            'properties' => ['primary_color' => $schemas['color'], 'secondary_color' => $schemas['color'],
                'typography' => $schemas['typography'], 'configuration' => $configuration]];
    }

    public static function apply(int $actorId, int $revisionId, array $post): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actorId);
        $revision = SiteRevisionManager::assertRevisionMutableForComposition($actorId, $revisionId);
        $expected = SiteServiceSupport::assertSnapshotHash(self::text($post, 'expected_snapshot_hash'));
        $current = SiteCompositionManager::compositionForActor($actorId, $revisionId);
        // Preserve the browser token. Never reload/retry with a fresh write token.
        if (!hash_equals($current['snapshot_hash'], $expected)) {
            throw new SiteServiceException('stale_write', 'The revision composition changed after it was loaded. Reload and review the latest composition.');
        }
        $catalog = SiteAuthoringCatalog::forActor($actorId);
        $operation = self::text($post, 'operation');
        if (in_array($operation, ['initialize_new', 'initialize_from_based_on'], true)) {
            if ($current['composition_state'] !== 'empty') {
                throw new SiteServiceException('conflict', 'Only an empty draft can be initialized.');
            }
            if ($operation === 'initialize_new') {
                $input = self::initial($catalog);
            } else {
                $sourceId = (int) ($revision['based_on_revision_id'] ?? 0);
                if ($sourceId < 1) {
                    throw new SiteServiceException('invalid_request', 'This revision has no based-on source.');
                }
                $source = SiteRevisionManager::revisionForActor($actorId, $sourceId);
                if ((int) $source['site_id'] !== (int) $revision['site_id']
                    || in_array($source['lifecycle_status'], SiteRevisionManager::MUTABLE_COMPOSITION_STATES, true)) {
                    throw new SiteServiceException('invalid_request', 'The based-on source must be an immutable revision of this site.');
                }
                $input = self::storedInput($actorId, $sourceId);
            }
        } else {
            if ($current['composition_state'] !== 'composed') {
                throw new SiteServiceException('conflict', 'Initialize the empty composition first.');
            }
            $input = self::storedInput($actorId, $revisionId, $current);
            if ($operation === 'update_theme') {
                $theme = SiteSchemaForm::parse(self::themeFormSchema(), $post['theme'] ?? null);
                $identity = self::text($post, 'theme_identity');
                $definition = SiteAuthoringCatalog::themes()[$identity] ?? null;
                if ($definition === null || $identity !== $input['theme']['theme_key'] . '@' . $input['theme']['theme_version']) {
                    throw new SiteServiceException('invalid_request', 'The exact authorable theme identity is required.');
                }
                $theme += ['theme_key' => $definition['theme_key'], 'theme_version' => $definition['theme_version'], 'assets' => []];
                foreach ($input['theme']['configuration']['layouts'] as $slot => $stored) {
                    $selection = self::selection($catalog, $post['layouts'][$slot] ?? [], $stored, 'layout');
                    $theme['configuration']['layouts'][$slot] = $selection;
                }
                $theme['assets'] = self::assets($post['theme_assets'] ?? [], $input['theme']['assets']);
                $input['theme'] = $theme;
            } elseif ($operation === 'add_page') {
                $page = SiteSchemaForm::parse(self::pageFormSchema(true), $post['page'] ?? null);
                $page['sections'] = [self::draftSection($catalog)];
                $input['pages'][] = $page;
            } else {
                $p = self::index($input['pages'], 'page_key', self::text($post, 'page_key'));
                if ($operation === 'update_page') {
                    $page = SiteSchemaForm::parse(self::pageFormSchema(false), $post['page'] ?? null);
                    $input['pages'][$p] = $page + array_intersect_key($input['pages'][$p], array_flip(['page_key', 'sections', 'sort_order']));
                } elseif ($operation === 'remove_page') {
                    array_splice($input['pages'], $p, 1);
                } elseif ($operation === 'move_page') {
                    self::move($input['pages'], $p, self::text($post, 'direction'));
                } elseif ($operation === 'add_section') {
                    $selection = self::selection($catalog, $post, null, 'section');
                    $selection += ['section_key' => self::text($post, 'section_key'), 'assets' => self::assets($post['assets'] ?? [])];
                    $input['pages'][$p]['sections'][] = $selection;
                } elseif (in_array($operation, ['update_section', 'remove_section', 'move_section'], true)) {
                    $s = self::index($input['pages'][$p]['sections'], 'section_key', self::text($post, 'section_key'));
                    if ($operation === 'remove_section') {
                        array_splice($input['pages'][$p]['sections'], $s, 1);
                    } elseif ($operation === 'move_section') {
                        self::move($input['pages'][$p]['sections'], $s, self::text($post, 'direction'));
                    } else {
                        $stored = $input['pages'][$p]['sections'][$s];
                        $input['pages'][$p]['sections'][$s] = self::selection($catalog, $post, $stored, 'section')
                            + ['section_key' => $stored['section_key'], 'assets' => self::assets($post['assets'] ?? [], $stored['assets'])];
                    }
                } else {
                    throw new SiteServiceException('invalid_request', 'The composition operation is unknown.');
                }
            }
        }
        // Recheck every retained identity, including based-on and unchanged selections.
        self::assertAuthorable($catalog, $input);
        foreach ($input['pages'] as $p => &$page) {
            $page['sort_order'] = ($p + 1) * 10;
            foreach ($page['sections'] as $s => &$section) {
                $section['sort_order'] = ($s + 1) * 10;
            }
            unset($section);
        }
        unset($page);
        $input['expected_snapshot_hash'] = $expected;
        return SiteCompositionManager::replaceDraftComposition($actorId, $revisionId, $input);
    }

    private static function storedInput(int $actorId, int $revisionId, ?array $current = null): array
    {
        $editor = $current ?? SiteCompositionManager::compositionForActor($actorId, $revisionId);
        if ($editor['composition_state'] !== 'composed') {
            throw new SiteServiceException('conflict', 'The source has no composition.');
        }
        // Based-on sources must validate as stored. Existing drafts can be repaired
        // (for example by removing an expired asset); M3 validates the resulting graph.
        if ($current === null) {
            $validated = SiteCompositionManager::validatedCompositionForActor($actorId, $revisionId);
            if (!hash_equals($editor['snapshot_hash'], $validated['snapshot_hash'])) {
                throw new SiteServiceException('stale_write', 'The composition changed during the read. Reload and review it.');
            }
        }
        // Read provenance internally; it is neither editable nor printed in forms.
        $references = SiteServiceSupport::read(static function (object $connection) use ($revisionId): array {
            $statement = $connection->prepare('/* site-m4b:asset-provenance */ SELECT usage_key, source_reference FROM site_revision_assets WHERE revision_id = :revision_id');
            $statement->execute(['revision_id' => $revisionId]);
            return array_column($statement->fetchAll(), 'source_reference', 'usage_key');
        });
        $pages = $editor['pages'];
        foreach ($pages as &$page) {
            unset($page['content_hash']);
            foreach ($page['sections'] as &$section) {
                unset($section['content_hash'], $section['variant_configuration_schema_version']);
                $section['assets'] = self::storedAssets($editor['assets'], $references, $page['page_key'], $section['section_key']);
            }
            unset($section);
        }
        unset($page);
        $theme = $editor['theme'];
        unset($theme['content_hash']);
        $theme['assets'] = self::storedAssets($editor['assets'], $references, null, null);
        return ['pages' => $pages, 'theme' => $theme];
    }

    private static function storedAssets(array $assets, array $references, ?string $page, ?string $section): array
    {
        $result = [];
        foreach ($assets as $asset) {
            if ($asset['page_key'] === $page && $asset['section_key'] === $section) {
                $result[] = ['asset_id' => $asset['asset_id'], 'usage_key' => $asset['usage_key'],
                    'source_reference' => $references[$asset['usage_key']] ?? null];
            }
        }
        return $result;
    }

    private static function assets(mixed $rows, array $existing = []): array
    {
        if (!is_array($rows) || count($rows) > 25) {
            throw new SiteServiceException('invalid_request', 'Asset selections are invalid.');
        }
        $assets = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new SiteServiceException('invalid_request', 'Asset selection is invalid.');
            }
            $id = self::text($row, 'asset_id');
            if ($id === '') {
                continue;
            }
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new SiteServiceException('invalid_request', 'Asset ID is invalid.');
            }
            $asset = ['asset_id' => (int) $id, 'usage_key' => self::text($row, 'usage_key')];
            foreach ($existing as $prior) {
                if ($prior['asset_id'] === $asset['asset_id'] && $prior['usage_key'] === $asset['usage_key']) {
                    $asset['source_reference'] = $prior['source_reference'] ?? null;
                }
            }
            $assets[] = $asset;
        }
        return $assets;
    }

    private static function selection(array $catalog, array $post, ?array $stored, string $scope): array
    {
        $identity = self::text($post, 'component_identity');
        if ($stored !== null && $identity !== $stored['component_key'] . '@' . $stored['implementation_version']) {
            throw new SiteServiceException('invalid_request', 'A stored component identity cannot change. Remove and add a section explicitly.');
        }
        $definition = $catalog[$identity] ?? null;
        $variant = self::text($post, 'variant_key');
        if ($definition === null || $definition['scope'] !== $scope || !isset($definition['variants'][$variant])) {
            throw new SiteServiceException('invalid_request', 'The selected component or variant is not authorable.');
        }
        return ['component_key' => $definition['component_key'], 'implementation_version' => $definition['implementation_version'],
            'configuration_schema_version' => $definition['configuration_schema_version'], 'variant_key' => $variant,
            'configuration' => SiteSchemaForm::parse($definition['configuration_schema'], $post['configuration'] ?? null)];
    }

    private static function assertAuthorable(array $catalog, array $input): void
    {
        if (!isset(SiteAuthoringCatalog::themes()[$input['theme']['theme_key'] . '@' . $input['theme']['theme_version']])) {
            throw new SiteServiceException('conflict', 'The exact stored theme is no longer authorable.');
        }
        $selections = array_values($input['theme']['configuration']['layouts']);
        foreach ($input['pages'] as $page) {
            array_push($selections, ...$page['sections']);
        }
        foreach ($selections as $selection) {
            $definition = $catalog[$selection['component_key'] . '@' . $selection['implementation_version']] ?? null;
            if ($definition === null || !isset($definition['variants'][$selection['variant_key']])
                || $selection['configuration_schema_version'] !== $definition['configuration_schema_version']) {
                throw new SiteServiceException('conflict', 'An exact stored component identity is no longer authorable. No version was upgraded.');
            }
        }
    }

    private static function initial(array $catalog): array
    {
        $layouts = [];
        foreach (['site_header' => ['show_phone' => false],
            'site_footer' => ['copyright_text' => 'Content pending review', 'show_navigation' => true, 'show_contact' => false],
            'mobile_cta' => ['label' => 'Content pending review', 'action' => 'contact']] as $key => $config) {
            $layouts[$key] = self::draftSelection($catalog, $key, $config);
        }
        return ['pages' => [['page_key' => 'home', 'title' => 'Draft page', 'slug' => '', 'page_type' => 'home',
            'navigation_label' => null, 'seo' => ['robots' => 'noindex_nofollow', 'canonical_policy' => 'none'],
            'presentation' => ['layout_width' => 'standard', 'show_in_navigation' => true],
            'sections' => [self::draftSection($catalog)]]],
            'theme' => ['theme_key' => 'local_service', 'theme_version' => 1, 'primary_color' => '#2457A7', 'secondary_color' => '#172B4D',
                'typography' => ['heading_family' => 'system_sans', 'body_family' => 'system_sans', 'scale' => 'standard'],
                'configuration' => ['section_spacing' => 'standard', 'corner_style' => 'soft', 'button_style' => 'rounded', 'layouts' => $layouts],
                'assets' => []]];
    }

    private static function draftSection(array $catalog): array
    {
        return self::draftSelection($catalog, 'text_block', ['body' => 'Content pending review', 'alignment' => 'left'])
            + ['section_key' => 'draft-content', 'assets' => []];
    }

    private static function draftSelection(array $catalog, string $key, array $config): array
    {
        $identity = $key . '@' . ComponentRegistry::AUTHORED_VERSION;
        $definition = $catalog[$identity] ?? null;
        if ($definition === null) {
            throw new SiteServiceException('conflict', 'A required draft component is unavailable for authoring.');
        }
        return ['component_key' => $key, 'implementation_version' => $definition['implementation_version'],
            'configuration_schema_version' => $definition['configuration_schema_version'],
            'variant_key' => array_key_first($definition['variants']), 'configuration' => $config];
    }

    private static function index(array $rows, string $field, string $key): int
    {
        foreach ($rows as $i => $row) {
            if ($row[$field] === $key) {
                return $i;
            }
        }
        throw new SiteServiceException('invalid_request', 'The requested page or section is not in this revision.');
    }

    private static function move(array &$rows, int $index, string $direction): void
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new SiteServiceException('invalid_request', 'The move direction is invalid.');
        }
        $target = $index + ($direction === 'up' ? -1 : 1);
        if (!isset($rows[$target])) {
            throw new SiteServiceException('invalid_request', 'The item cannot move further in that direction.');
        }
        [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];
    }

    private static function text(array $input, string $key): string
    {
        if (!is_string($input[$key] ?? null)) {
            throw new SiteServiceException('invalid_request', 'A required operation field is invalid.');
        }
        return $input[$key];
    }
}
