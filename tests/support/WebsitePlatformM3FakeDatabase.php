<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/private/classes/ComponentRegistry.php';

final class WebsitePlatformM3FakeStatement
{
    private array $parameters = [];
    private array $rows = [];

    public function __construct(private WebsitePlatformM3FakeDatabase $database, private string $sql)
    {
    }

    public function execute(?array $parameters = null): bool
    {
        $this->parameters = $parameters ?? [];
        $this->rows = $this->database->rows($this->sql, $this->parameters);
        return true;
    }

    public function fetch(): array|false
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchAll(): array
    {
        $rows = $this->rows;
        $this->rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch();
        return $row === false ? false : array_values($row)[$column];
    }
}

final class WebsitePlatformM3FakeDatabase
{
    public array $definitions = [];
    public array $assets = [];

    public function __construct()
    {
        $definitionId = 1;
        $variantId = 100;
        foreach (ComponentRegistry::manifest() as $definition) {
            $identity = $definition['component_key'] . '@' . $definition['implementation_version'];
            $row = [
                'definition_id' => $definitionId++,
                'component_key' => $definition['component_key'],
                'implementation_version' => $definition['implementation_version'],
                'label' => $definition['label'], 'category' => $definition['category'],
                'configuration_schema_version' => $definition['configuration_schema_version'],
                'definition_status' => 'active',
                'definition_metadata_json' => json_encode([
                    'scope' => $definition['scope'], 'authorable' => $definition['authorable'], 'manifest_version' => 1,
                ], JSON_THROW_ON_ERROR),
                'variants' => [],
            ];
            foreach ($definition['variants'] as $variantKey => $variant) {
                $row['variants'][$variantKey] = [
                    'variant_id' => $variantId++, 'variant_key' => $variantKey,
                    'variant_schema_version' => $variant['schema_version'],
                    'variant_status' => 'active', 'variant_metadata_json' => '{}',
                ];
            }
            $this->definitions[$identity] = $row;
        }
        $this->assets[1] = [
            'id' => 1, 'site_id' => 10, 'business_id' => 50, 'active_business_id' => 50,
            'asset_type' => 'image', 'storage_key' => 'sites/10/hero.jpg',
            'checksum_sha256' => str_repeat('a', 64), 'mime_type' => 'image/jpeg',
            'byte_size' => 1234, 'rights_classification' => 'customer_owned',
            'rights_expires_at' => null, 'lifecycle_status' => 'ready',
        ];
        $this->assets[2] = [
            'id' => 2, 'site_id' => 10, 'business_id' => 50, 'active_business_id' => 50,
            'asset_type' => 'document', 'storage_key' => 'sites/10/pricing.pdf',
            'checksum_sha256' => str_repeat('b', 64), 'mime_type' => 'application/pdf',
            'byte_size' => 4321, 'rights_classification' => 'customer_licensed_for_site',
            'rights_expires_at' => '2099-01-01 00:00:00', 'lifecycle_status' => 'ready',
        ];
    }

    public function prepare(string $sql): WebsitePlatformM3FakeStatement
    {
        return new WebsitePlatformM3FakeStatement($this, $sql);
    }

    public function rows(string $sql, array $parameters): array
    {
        if (str_contains($sql, 'site-m3:resolve-component')) {
            $identity = $parameters['component_key'] . '@' . $parameters['implementation_version'];
            $definition = $this->definitions[$identity] ?? null;
            $variant = $definition['variants'][$parameters['variant_key']] ?? null;
            return $definition && $variant ? [[
                'definition_id' => $definition['definition_id'], 'label' => $definition['label'],
                'category' => $definition['category'],
                'configuration_schema_version' => $definition['configuration_schema_version'],
                'definition_status' => $definition['definition_status'],
                'definition_metadata_json' => $definition['definition_metadata_json'],
                'variant_id' => $variant['variant_id'], 'variant_schema_version' => $variant['variant_schema_version'],
                'variant_status' => $variant['variant_status'], 'variant_metadata_json' => '{}',
            ]] : [];
        }
        if (str_contains($sql, 'site-m3:verify-registry')) {
            $rows = [];
            foreach ($this->definitions as $definition) {
                if ($definition['variants'] === []) {
                    $rows[] = $definition + ['variant_id' => null, 'variant_key' => null, 'variant_schema_version' => null, 'variant_status' => null];
                    continue;
                }
                foreach ($definition['variants'] as $variant) {
                    $rows[] = [
                        'definition_id' => $definition['definition_id'],
                        'component_key' => $definition['component_key'],
                        'implementation_version' => $definition['implementation_version'],
                        'configuration_schema_version' => $definition['configuration_schema_version'],
                        'definition_status' => $definition['definition_status'],
                        'definition_metadata_json' => $definition['definition_metadata_json'],
                    ] + $variant;
                }
            }
            return $rows;
        }
        if (str_contains($sql, 'site-m3:resolve-asset')) {
            $asset = $this->assets[(int) $parameters['asset_id']] ?? null;
            return $asset && (int) $asset['site_id'] === (int) $parameters['site_id'] ? [$asset] : [];
        }
        throw new RuntimeException('Unexpected M3 fake SQL: ' . $sql);
    }
}
