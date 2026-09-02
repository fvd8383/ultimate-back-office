<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteRevisionSnapshotHasher.php';

$assertions = 0;
function assertM3LegacyHash(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class WebsitePlatformM3LegacyHashStatement
{
    private array $rows = [];

    public function __construct(private WebsitePlatformM3LegacyHashDatabase $database, private string $sql)
    {
    }

    public function execute(?array $parameters = null): bool
    {
        $this->rows = $this->database->rows($this->sql);
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
}

final class WebsitePlatformM3LegacyHashDatabase
{
    public function __construct(public mixed $navigationLabel)
    {
    }

    public function prepare(string $sql): WebsitePlatformM3LegacyHashStatement
    {
        return new WebsitePlatformM3LegacyHashStatement($this, $sql);
    }

    public function rows(string $sql): array
    {
        return match (true) {
            str_contains($sql, 'hash-revision') => [[
                'snapshot_schema_version' => 1, 'facts_snapshot_json' => '{}',
                'source_references_json' => '{}', 'generation_brief_id' => null,
            ]],
            str_contains($sql, 'hash-pages') => [[
                'id' => 10, 'page_key' => 'home', 'title' => 'Home', 'slug' => '', 'page_type' => 'home',
                'navigation_label' => $this->navigationLabel, 'sort_order' => 10,
                'seo_json' => '{}', 'presentation_json' => '{}', 'content_hash' => str_repeat('a', 64),
            ]],
            str_contains($sql, 'hash-sections') => [[
                'section_key' => 'legacy-page-snapshot', 'component_key' => 'legacy_247sp_page',
                'implementation_version' => 'legacy-preview-v1', 'variant_key' => 'home',
                'variant_configuration_schema_version' => 1, 'sort_order' => 10,
                'configuration_schema_version' => 1, 'configuration_json' => '{"headline":"Home"}',
                'content_hash' => str_repeat('b', 64),
            ]],
            str_contains($sql, 'hash-theme') => [[
                'theme_key' => 'legacy_247sp_starter', 'theme_version' => 1,
                'primary_color' => null, 'secondary_color' => null,
                'typography_json' => '{}', 'configuration_json' => '{}', 'content_hash' => str_repeat('c', 64),
            ]],
            str_contains($sql, 'hash-assets') => [],
            default => throw new RuntimeException('Unexpected legacy hash SQL: ' . $sql),
        };
    }
}

foreach ([
    'empty' => ['', null],
    'whitespace' => [" \t ", null],
    'null' => [null, null],
    'normal' => ['  Main Nav  ', 'Main Nav'],
] as $case => [$stored, $expectedLegacy]) {
    $database = new WebsitePlatformM3LegacyHashDatabase($stored);
    $generic = SiteRevisionSnapshotHasher::storedRepresentation($database, 1);
    $legacy = SiteRevisionSnapshotHasher::storedRepresentation($database, 1, SiteRevisionSnapshotHasher::MODE_LEGACY_M1);
    assertM3LegacyHash($generic['pages'][0]['navigation_label'] === $stored, "Generic {$case} value must remain raw.");
    assertM3LegacyHash($legacy['pages'][0]['navigation_label'] === $expectedLegacy, "Legacy M1 {$case} value must use trim-and-null normalization.");
    $expectedRepresentation = $generic;
    $expectedRepresentation['pages'][0]['navigation_label'] = $expectedLegacy;
    assertM3LegacyHash(
        SiteRevisionSnapshotHasher::hashStoredRevision($database, 1, SiteRevisionSnapshotHasher::MODE_LEGACY_M1)
            === CanonicalJson::hash($expectedRepresentation),
        "Legacy M1 {$case} hash must equal the pre-M3 representation."
    );
}

$importer = file_get_contents(__DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php');
assertM3LegacyHash(str_contains((string) $importer, 'SiteRevisionSnapshotHasher::MODE_LEGACY_M1'), 'Legacy importer must explicitly select the M1 compatibility path.');

echo "Website platform M3 legacy hash compatibility: {$assertions} assertions passed.\n";
