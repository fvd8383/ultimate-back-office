<?php

declare(strict_types=1);

require_once __DIR__ . '/ComponentRegistry.php';

final class ThemeRegistry
{
    public static function manifest(): array
    {
        return [
            'local_service@1' => [
                'theme_key' => 'local_service', 'theme_version' => 1,
                'authorable' => true, 'renderable' => true,
            ],
            'legacy_247sp_starter@1' => [
                'theme_key' => 'legacy_247sp_starter', 'theme_version' => 1,
                'authorable' => false, 'renderable' => true,
            ],
        ];
    }

    public static function normalize(object $connection, array $input, bool $forAuthoring): array
    {
        $key = trim((string) ($input['theme_key'] ?? ''));
        $version = (int) ($input['theme_version'] ?? 0);
        $identity = $key . '@' . $version;
        $manifest = self::manifest();
        if (!isset($manifest[$identity])) {
            throw new SiteServiceException('invalid_request', 'The theme implementation is unknown.');
        }
        if (!$manifest[$identity]['renderable'] || ($forAuthoring && !$manifest[$identity]['authorable'])) {
            throw new SiteServiceException('invalid_request', 'The theme is not selectable for new composition.');
        }
        if (!$forAuthoring && $identity === 'legacy_247sp_starter@1') {
            return $input;
        }

        $allowed = ['theme_key', 'theme_version', 'primary_color', 'secondary_color', 'typography', 'configuration', 'assets'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new SiteServiceException('invalid_request', 'Theme input contains an unknown field.');
        }
        $colorSchema = ['type' => 'string', 'format' => 'color', 'minLength' => 7, 'maxLength' => 7];
        $primary = ComponentSchemaValidator::validate($input['primary_color'] ?? null, $colorSchema, 'theme.primary_color');
        $secondary = ComponentSchemaValidator::validate($input['secondary_color'] ?? null, $colorSchema, 'theme.secondary_color');
        $typography = ComponentSchemaValidator::validate($input['typography'] ?? null, [
            'type' => 'object',
            'required' => ['heading_family', 'body_family', 'scale'],
            'properties' => [
                'heading_family' => ['type' => 'string', 'enum' => ['system_sans', 'system_serif']],
                'body_family' => ['type' => 'string', 'enum' => ['system_sans', 'system_serif']],
                'scale' => ['type' => 'string', 'enum' => ['compact', 'standard', 'large']],
            ],
        ], 'theme.typography');
        $configuration = ComponentSchemaValidator::validate($input['configuration'] ?? null, [
            'type' => 'object',
            'required' => ['section_spacing', 'corner_style', 'button_style', 'layouts'],
            'properties' => [
                'section_spacing' => ['type' => 'string', 'enum' => ['compact', 'standard', 'relaxed']],
                'corner_style' => ['type' => 'string', 'enum' => ['square', 'soft', 'rounded']],
                'button_style' => ['type' => 'string', 'enum' => ['square', 'rounded', 'pill']],
                'layouts' => [
                    'type' => 'object', 'required' => ['site_header', 'site_footer', 'mobile_cta'],
                    'properties' => [
                        'site_header' => self::layoutSelectionSchema(),
                        'site_footer' => self::layoutSelectionSchema(),
                        'mobile_cta' => self::layoutSelectionSchema(),
                    ],
                ],
            ],
        ], 'theme.configuration');
        foreach ($configuration['layouts'] as $slot => &$selection) {
            if ($selection['component_key'] !== $slot) {
                throw new SiteServiceException('invalid_request', 'Theme layout selection does not match its slot.');
            }
            $definition = ComponentRegistry::resolve(
                $connection,
                $selection['component_key'],
                $selection['implementation_version'],
                $selection['variant_key'],
                $selection['configuration_schema_version'],
                'layout',
                $forAuthoring
            );
            $selection['configuration'] = ComponentRegistry::validateConfiguration(
                $definition,
                $selection['variant_key'],
                $selection['configuration_schema_version'],
                $selection['configuration']
            );
        }
        unset($selection);

        return [
            'theme_key' => $key, 'theme_version' => $version,
            'primary_color' => strtoupper($primary), 'secondary_color' => strtoupper($secondary),
            'typography' => $typography, 'configuration' => $configuration,
            'assets' => self::normalizeAssetInputs($input['assets'] ?? [], 'theme.assets'),
        ];
    }

    public static function contentHash(array $theme): string
    {
        $copy = $theme;
        unset($copy['assets'], $copy['content_hash'], $copy['id']);
        return CanonicalJson::hash($copy);
    }

    public static function normalizeAssetInputs(mixed $assets, string $path): array
    {
        return ComponentSchemaValidator::validate($assets, [
            'type' => 'array', 'minItems' => 0, 'maxItems' => 25,
            'items' => [
                'type' => 'object', 'required' => ['asset_id', 'usage_key'],
                'properties' => [
                    'asset_id' => ['type' => 'integer', 'minimum' => 1],
                    'usage_key' => ['type' => 'string', 'format' => 'asset-usage-key', 'minLength' => 1, 'maxLength' => 100],
                    'source_reference' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 500],
                ],
            ],
        ], $path);
    }

    private static function layoutSelectionSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['component_key', 'implementation_version', 'variant_key', 'configuration_schema_version', 'configuration'],
            'properties' => [
                'component_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'implementation_version' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
                'variant_key' => ['type' => 'string', 'format' => 'token', 'minLength' => 1, 'maxLength' => 100],
                'configuration_schema_version' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'configuration' => ['type' => 'object', 'passthrough' => true],
            ],
        ];
    }

    private static function layoutConfigurationProperties(): array
    {
        return [
            'logo_usage_key' => ['type' => 'string', 'nullable' => true, 'format' => 'asset-usage-key', 'minLength' => 1, 'maxLength' => 100],
            'tagline' => ['type' => 'string', 'nullable' => true, 'minLength' => 1, 'maxLength' => 160],
            'show_phone' => ['type' => 'boolean'],
            'copyright_text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
            'show_navigation' => ['type' => 'boolean'],
            'show_contact' => ['type' => 'boolean'],
            'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
            'action' => ['type' => 'string', 'enum' => ['call', 'contact', 'email']],
        ];
    }
}
