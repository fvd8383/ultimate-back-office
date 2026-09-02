<?php

declare(strict_types=1);

require_once __DIR__ . '/ComponentSchemaValidator.php';

final class ComponentRegistry
{
    public const AUTHORED_VERSION = '1.0.0';

    public static function manifest(): array
    {
        $text = self::text();
        $heading = self::text(1, 160);
        $body = self::text(1, 4000);
        $optionalHeading = self::nullable($heading);
        $usage = ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'format' => 'asset-usage-key'];
        $cta = self::object([
            'label' => self::text(1, 80),
            'action' => self::enum(['call', 'contact', 'email']),
        ], ['label', 'action']);
        $itemText = self::object(['title' => self::text(1, 120), 'body' => self::text(1, 1000)], ['title', 'body']);

        $definitions = [
            self::component('hero', 'Hero', 'content', 'section', 'hero', ['default', 'split_media'], self::object([
                'headline' => $heading, 'eyebrow' => self::nullable(self::text(1, 100)),
                'body' => self::nullable($body), 'primary_cta' => self::nullable($cta),
                'secondary_cta' => self::nullable($cta), 'media_alt' => self::nullable(self::text(1, 200)),
                'media_usage_key' => self::nullable($usage),
            ], ['headline']), ['home', 'service', 'about', 'contact', 'landing', 'standard', 'legal'], 1,
                [
                    'default' => [['field' => 'media_usage_key', 'asset_type' => 'image', 'required' => false]],
                    'split_media' => [['field' => 'media_usage_key', 'asset_type' => 'image', 'required' => true]],
                ]),
            self::component('statistics', 'Statistics', 'content', 'section', 'statistics', ['default'], self::object([
                'items' => self::list(self::object(['value' => self::text(1, 40), 'label' => self::text(1, 100)], ['value', 'label']), 1, 4),
            ], ['items']), ['home', 'landing', 'standard'], 1),
            self::component('service_grid', 'Service Grid', 'services', 'section', 'service_grid', ['cards'], self::object([
                'heading' => $heading, 'intro' => self::nullable($body),
                'services' => self::list(self::object([
                    'name' => self::text(1, 120), 'description' => self::text(1, 1000),
                    'path' => self::nullable(['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'format' => 'relative-path']),
                ], ['name', 'description']), 1, 12),
            ], ['heading', 'services']), ['home', 'landing', 'standard'], 1),
            self::component('service_detail', 'Service Detail', 'services', 'section', 'service_detail', ['default'], self::object([
                'heading' => $heading, 'body' => $body,
                'included_items' => self::list(self::text(1, 200), 0, 12),
                'media_usage_key' => self::nullable($usage), 'media_alt' => self::nullable(self::text(1, 200)),
            ], ['heading', 'body', 'included_items']), ['service'], 1,
                ['default' => [['field' => 'media_usage_key', 'asset_type' => 'image', 'required' => false]]]),
            self::component('trust_cards', 'Trust Cards', 'content', 'section', 'trust_cards', ['default'], self::object([
                'heading' => $optionalHeading, 'cards' => self::list($itemText, 1, 6),
            ], ['cards']), ['home', 'service', 'about', 'landing', 'standard'], 1),
            self::component('about_content', 'About Content', 'content', 'section', 'about_content', ['default'], self::object([
                'heading' => $heading, 'body' => $body,
                'highlights' => self::list(self::text(1, 200), 0, 8),
                'media_usage_key' => self::nullable($usage), 'media_alt' => self::nullable(self::text(1, 200)),
            ], ['heading', 'body']), ['about', 'home', 'standard'], 1,
                ['default' => [['field' => 'media_usage_key', 'asset_type' => 'image', 'required' => false]]]),
            self::component('contact_content', 'Contact Content', 'content', 'section', 'contact_content', ['default'], self::object([
                'heading' => $heading, 'body' => $body, 'phone' => self::nullable(self::text(1, 50)),
                'email' => self::nullable(self::text(1, 254)), 'service_area' => self::nullable(self::text(1, 255)),
            ], ['heading', 'body']), ['contact', 'landing', 'standard'], 1),
            self::component('cta', 'Call To Action', 'conversion', 'section', 'cta', ['banner', 'inline'], self::object([
                'heading' => $optionalHeading, 'body' => self::nullable($body),
                'label' => self::text(1, 80), 'action' => self::enum(['call', 'contact', 'email']),
            ], ['label', 'action']), ['home', 'service', 'about', 'contact', 'landing', 'standard', 'legal'], 3),
            self::component('lead_form', 'Lead Form', 'conversion', 'section', 'lead_form', ['default'], self::object([
                'heading' => $heading, 'body' => self::nullable($body), 'submit_label' => self::text(1, 80),
                'fields' => self::list(self::enum(['name', 'email', 'phone', 'service', 'message']), 1, 5, true),
                'required_fields' => self::list(self::enum(['name', 'email', 'phone', 'service', 'message']), 0, 5, true),
            ], ['heading', 'submit_label', 'fields', 'required_fields']), ['home', 'contact', 'landing'], 1),
            self::component('pricing_list', 'Pricing List', 'content', 'section', 'pricing_list', ['link'], self::object([
                'label' => self::text(1, 120), 'description' => self::nullable(self::text(1, 1000)),
                'document_usage_key' => $usage,
            ], ['label', 'document_usage_key']), ['home', 'service', 'landing', 'standard'], 1,
                ['link' => [['field' => 'document_usage_key', 'asset_type' => 'document', 'mime_type' => 'application/pdf', 'required' => true]]]),
            self::component('faq', 'Frequently Asked Questions', 'content', 'section', 'faq', ['accordion'], self::object([
                'heading' => $optionalHeading,
                'items' => self::list(self::object(['question' => self::text(1, 300), 'answer' => self::text(1, 2000)], ['question', 'answer']), 1, 20),
            ], ['items']), ['home', 'service', 'contact', 'landing', 'standard', 'legal'], 1),
            self::component('text_block', 'Text Block', 'content', 'section', 'text_block', ['default'], self::object([
                'heading' => $optionalHeading, 'body' => $body, 'alignment' => self::enum(['left', 'center']),
            ], ['body', 'alignment']), ['home', 'service', 'about', 'contact', 'landing', 'standard', 'legal'], 6),
            self::component('site_header', 'Site Header', 'layout', 'layout', 'site_header', ['standard', 'centered'], self::object([
                'logo_usage_key' => self::nullable($usage), 'tagline' => self::nullable(self::text(1, 160)),
                'show_phone' => ['type' => 'boolean'],
            ], ['show_phone']), [], 1, ['standard' => [['field' => 'logo_usage_key', 'asset_type' => 'image', 'required' => false]], 'centered' => [['field' => 'logo_usage_key', 'asset_type' => 'image', 'required' => false]]]),
            self::component('site_footer', 'Site Footer', 'layout', 'layout', 'site_footer', ['default'], self::object([
                'copyright_text' => self::text(1, 200), 'show_navigation' => ['type' => 'boolean'],
                'show_contact' => ['type' => 'boolean'],
            ], ['copyright_text', 'show_navigation', 'show_contact']), [], 1),
            self::component('mobile_cta', 'Mobile CTA', 'layout', 'layout', 'mobile_cta', ['default'], self::object([
                'label' => self::text(1, 80), 'action' => self::enum(['call', 'contact', 'email']),
            ], ['label', 'action']), [], 1),
            [
                'component_key' => 'legacy_247sp_page', 'implementation_version' => 'legacy-preview-v1',
                'label' => 'Legacy 247SP Page Snapshot', 'category' => 'legacy_import', 'scope' => 'legacy',
                'authorable' => false, 'renderable' => true, 'snapshot_compatibility' => true,
                'configuration_schema_version' => 1, 'variants' => array_fill_keys(['home', 'service', 'about', 'contact'], ['label' => 'Legacy snapshot', 'schema_version' => 1]),
                'allowed_page_types' => [], 'cardinality' => 1, 'configuration_schema' => self::object([], []),
                'asset_requirements' => [], 'renderer' => 'legacy_snapshot',
            ],
        ];
        $manifest = [];
        foreach ($definitions as $definition) {
            $manifest[self::identity($definition['component_key'], $definition['implementation_version'])] = $definition;
        }
        self::validateManifest($manifest);
        return $manifest;
    }

    public static function validateManifest(array $manifest): void
    {
        $seen = [];
        foreach ($manifest as $definition) {
            $identity = self::identity((string) ($definition['component_key'] ?? ''), (string) ($definition['implementation_version'] ?? ''));
            if (isset($seen[$identity])) {
                throw new LogicException("Duplicate repository component identity: {$identity}.");
            }
            $seen[$identity] = true;
            if (!in_array($definition['scope'] ?? null, ['section', 'layout', 'legacy'], true)) {
                throw new LogicException("Invalid repository component scope: {$identity}.");
            }
        }
    }

    public static function definition(string $componentKey, string $implementationVersion): array
    {
        $manifest = self::manifest();
        $identity = self::identity($componentKey, $implementationVersion);
        if (!isset($manifest[$identity])) {
            throw new SiteServiceException('invalid_request', 'The component implementation is not registered in repository code.');
        }
        return $manifest[$identity];
    }

    public static function validateConfiguration(array $definition, string $variantKey, int $schemaVersion, mixed $configuration): array
    {
        if (!isset($definition['variants'][$variantKey])) {
            throw new SiteServiceException('invalid_request', 'The component variant is not registered in repository code.');
        }
        if ($schemaVersion !== (int) $definition['configuration_schema_version']
            || $schemaVersion !== (int) $definition['variants'][$variantKey]['schema_version']) {
            throw new SiteServiceException('conflict', 'The component configuration schema version does not match repository code.');
        }
        $normalized = ComponentSchemaValidator::validate($configuration, $definition['configuration_schema']);
        if ($definition['component_key'] === 'hero' && $variantKey === 'split_media'
            && empty($normalized['media_usage_key'])) {
            throw new SiteServiceException('invalid_request', 'Hero split_media requires media_usage_key.');
        }
        if ($definition['component_key'] === 'lead_form') {
            foreach ($normalized['required_fields'] as $field) {
                if (!in_array($field, $normalized['fields'], true)) {
                    throw new SiteServiceException('invalid_request', 'Lead form required_fields must be selected fields.');
                }
            }
        }
        return $normalized;
    }

    public static function resolve(
        object $connection,
        string $componentKey,
        string $implementationVersion,
        string $variantKey,
        int $schemaVersion,
        string $requiredScope,
        bool $forAuthoring
    ): array {
        $definition = self::definition($componentKey, $implementationVersion);
        if ((string) $definition['scope'] !== $requiredScope) {
            throw new SiteServiceException('invalid_request', 'The component is not valid in this composition scope.');
        }
        if ($forAuthoring && !$definition['authorable']) {
            throw new SiteServiceException('invalid_request', 'The component is not authorable.');
        }
        if (!$definition['renderable']) {
            throw new SiteServiceException('conflict', 'The component implementation is not renderable.');
        }
        if (!isset($definition['variants'][$variantKey])) {
            throw new SiteServiceException('invalid_request', 'The component variant is unknown.');
        }
        $statement = $connection->prepare(
            '/* site-m3:resolve-component */
             SELECT cd.id AS definition_id, cd.label, cd.category, cd.configuration_schema_version,
                    cd.status AS definition_status, cd.metadata_json AS definition_metadata_json,
                    cv.id AS variant_id, cv.configuration_schema_version AS variant_schema_version,
                    cv.status AS variant_status, cv.metadata_json AS variant_metadata_json
             FROM component_definitions cd
             INNER JOIN component_variants cv ON cv.component_definition_id = cd.id
             WHERE cd.component_key = :component_key
               AND cd.implementation_version = :implementation_version
               AND cv.variant_key = :variant_key
             LIMIT 1'
        );
        $statement->execute([
            'component_key' => $componentKey,
            'implementation_version' => $implementationVersion,
            'variant_key' => $variantKey,
        ]);
        $row = $statement->fetch();
        if (!$row) {
            throw new SiteServiceException('conflict', 'Component registry metadata is missing.');
        }
        if ((int) $row['configuration_schema_version'] !== $schemaVersion
            || (int) $row['variant_schema_version'] !== $schemaVersion
            || $schemaVersion !== (int) $definition['configuration_schema_version']) {
            throw new SiteServiceException('conflict', 'Component registry schema metadata has drifted.');
        }
        if ($forAuthoring && ((string) $row['definition_status'] !== 'active' || (string) $row['variant_status'] !== 'active')) {
            throw new SiteServiceException('conflict', 'Inactive component metadata cannot be newly authored.');
        }
        $metadata = self::decodeMetadata($row['definition_metadata_json']);
        if (($metadata['scope'] ?? $definition['scope']) !== $definition['scope']
            || isset($metadata['authorable']) && (bool) $metadata['authorable'] !== (bool) $definition['authorable']) {
            throw new SiteServiceException('conflict', 'Component registry metadata has drifted.');
        }
        return $definition + ['definition_id' => (int) $row['definition_id'], 'variant_id' => (int) $row['variant_id'], 'variant_key' => $variantKey];
    }

    public static function verifyDatabase(object $connection): array
    {
        $manifest = self::manifest();
        $issues = [];
        $statement = $connection->prepare(
            '/* site-m3:verify-registry */
             SELECT cd.id AS definition_id, cd.component_key, cd.implementation_version,
                    cd.configuration_schema_version, cd.status AS definition_status,
                    cd.metadata_json AS definition_metadata_json, cv.id AS variant_id,
                    cv.variant_key, cv.configuration_schema_version AS variant_schema_version,
                    cv.status AS variant_status
             FROM component_definitions cd
             LEFT JOIN component_variants cv ON cv.component_definition_id = cd.id
             ORDER BY cd.component_key, cd.implementation_version, cv.variant_key'
        );
        $statement->execute();
        $rows = $statement->fetchAll();
        $database = [];
        foreach ($rows as $row) {
            $identity = self::identity((string) $row['component_key'], (string) $row['implementation_version']);
            $database[$identity][] = $row;
            if (!isset($manifest[$identity]) && (string) $row['definition_status'] === 'active') {
                $issues[] = "unknown active DB definition {$identity}";
            }
        }
        foreach ($manifest as $identity => $definition) {
            if (!isset($database[$identity])) {
                $issues[] = "missing DB definition {$identity}";
                continue;
            }
            $variants = [];
            foreach ($database[$identity] as $row) {
                if ((int) $row['configuration_schema_version'] !== (int) $definition['configuration_schema_version']) {
                    $issues[] = "definition schema mismatch {$identity}";
                }
                if ($definition['authorable'] && (string) $row['definition_status'] !== 'active') {
                    $issues[] = "authorable definition inactive {$identity}";
                }
                $metadata = self::decodeMetadata($row['definition_metadata_json']);
                if (($metadata['scope'] ?? $definition['scope']) !== $definition['scope']
                    || isset($metadata['authorable']) && (bool) $metadata['authorable'] !== (bool) $definition['authorable']) {
                    $issues[] = "definition metadata mismatch {$identity}";
                }
                if ($row['variant_key'] !== null) {
                    $variants[(string) $row['variant_key']] = $row;
                }
            }
            foreach ($definition['variants'] as $variantKey => $variant) {
                if (!isset($variants[$variantKey])) {
                    $issues[] = "missing DB variant {$identity}:{$variantKey}";
                } elseif ((int) $variants[$variantKey]['variant_schema_version'] !== (int) $variant['schema_version']) {
                    $issues[] = "variant schema mismatch {$identity}:{$variantKey}";
                } elseif ($definition['authorable'] && (string) $variants[$variantKey]['variant_status'] !== 'active') {
                    $issues[] = "authorable variant inactive {$identity}:{$variantKey}";
                }
            }
            foreach ($variants as $variantKey => $row) {
                if (!isset($definition['variants'][$variantKey]) && (string) $row['variant_status'] === 'active') {
                    $issues[] = "unknown active DB variant {$identity}:{$variantKey}";
                }
            }
        }
        sort($issues, SORT_STRING);
        return ['pass' => $issues === [], 'issues' => $issues, 'repository_definitions' => count($manifest), 'database_rows' => count($rows)];
    }

    public static function assetRequirements(array $definition, string $variantKey): array
    {
        return $definition['asset_requirements'][$variantKey] ?? [];
    }

    private static function component(string $key, string $label, string $category, string $scope, string $renderer, array $variants, array $schema, array $pageTypes, int $cardinality, array $assetRequirements = []): array
    {
        $variantRows = [];
        foreach ($variants as $variant) {
            $variantRows[$variant] = ['label' => ucwords(str_replace('_', ' ', $variant)), 'schema_version' => 1];
        }
        return [
            'component_key' => $key, 'implementation_version' => self::AUTHORED_VERSION,
            'label' => $label, 'category' => $category, 'scope' => $scope,
            'authorable' => true, 'renderable' => true, 'snapshot_compatibility' => false,
            'configuration_schema_version' => 1, 'variants' => $variantRows,
            'allowed_page_types' => $pageTypes, 'cardinality' => $cardinality,
            'configuration_schema' => $schema, 'asset_requirements' => $assetRequirements,
            'renderer' => $renderer,
        ];
    }

    private static function object(array $properties, array $required): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required];
    }

    private static function list(array $items, int $min, int $max, bool $unique = false): array
    {
        return ['type' => 'array', 'items' => $items, 'minItems' => $min, 'maxItems' => $max, 'uniqueItems' => $unique];
    }

    private static function text(int $min = 0, int $max = 255): array
    {
        return ['type' => 'string', 'minLength' => $min, 'maxLength' => $max, 'plainText' => true];
    }

    private static function enum(array $values): array
    {
        return ['type' => 'string', 'enum' => $values, 'minLength' => 1, 'maxLength' => 100];
    }

    private static function nullable(array $schema): array
    {
        $schema['nullable'] = true;
        return $schema;
    }

    private static function identity(string $key, string $version): string
    {
        return $key . '@' . $version;
    }

    private static function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
