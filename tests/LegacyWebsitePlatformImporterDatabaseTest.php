<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php';

final class LegacyImportDatabaseStatement extends PDOStatement
{
    private array $rows = [];
    private array $bound = [];
    private int $affected = 0;

    public function __construct(private LegacyImportDatabaseConnection $connection, private string $sql)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[ltrim((string) $param, ':')] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $params = array_merge($this->bound, $params ?? []);
        [$this->rows, $this->affected] = $this->connection->executeMarked($this->sql, $params);
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->rows;
        $this->rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class LegacyImportDatabaseConnection extends PDO
{
    public array $legacyWebsites = [];
    public array $legacyPages = [];
    public array $mappings = [];
    public array $sites = [];
    public array $associations = [];
    public array $briefs = [];
    public array $revisions = [];
    public array $sitePages = [];
    public array $revisionPages = [];
    public array $sections = [];
    public array $themes = [];
    public array $pageMappings = [];
    public array $events = [];
    public ?int $failOnSiteInsertForLegacyId = null;
    public ?int $failOnPageMappingForLegacyId = null;
    public bool $failOnQuarantineWrite = false;
    public ?int $mutateOnLockedLoadForLegacyId = null;
    public int $verifiedBoundPageMappings = 0;
    public int $verifiedMappingFinalizations = 0;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;

    private bool $transaction = false;
    private array $backup = [];
    private int $nextId = 0;
    private int $lastId = 0;
    private int $currentLegacyId = 0;

    public function __construct()
    {
    }

    public static function fixture(): self
    {
        $connection = new self();
        for ($id = 1; $id <= 6; $id++) {
            $connection->legacyWebsites[$id] = [
                'id' => $id,
                'business_id' => 100 + $id,
                'onboarding_id' => 200 + $id,
                'template_id' => 1,
                'status' => 'generated',
                'generated_at' => '2026-08-30 12:00:00',
                'published_at' => null,
                'created_at' => '2026-08-30 12:00:00',
                'updated_at' => '2026-08-30 12:00:00',
                'resolved_business_id' => 100 + $id,
                'resolved_onboarding_id' => 200 + $id,
                'onboarding_business_id' => 100 + $id,
                'resolved_template_id' => 1,
                'template_key' => 'starter_local_service',
            ];
            $connection->legacyPages[$id] = [[
                'id' => 1000 + $id,
                'website_id' => $id,
                'business_id' => 100 + $id,
                'page_type' => 'home',
                'title' => 'Home',
                'slug' => 'home',
                'content_json' => json_encode(['headline' => 'Business ' . $id], JSON_THROW_ON_ERROR),
                'status' => 'generated',
                'sort_order' => 10,
                'created_at' => '2026-08-30 12:00:00',
                'updated_at' => '2026-08-30 12:00:00',
            ]];
        }
        $connection->legacyPages[3][0]['content_json'] = '{broken';
        return $connection;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $placeholders);
        if (count($placeholders[1]) !== count(array_unique($placeholders[1]))) {
            throw new RuntimeException('Duplicate named placeholder rejected by native-prepares test contract.');
        }
        return new LegacyImportDatabaseStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('Nested test transaction.');
        }
        $this->backup = [];
        foreach ($this->stateProperties() as $property) {
            $this->backup[$property] = $this->{$property};
        }
        $this->backup['nextId'] = $this->nextId;
        $this->backup['lastId'] = $this->lastId;
        $this->transaction = true;
        $this->beginCount++;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        $this->backup = [];
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        foreach ($this->stateProperties() as $property) {
            $this->{$property} = $this->backup[$property];
        }
        $this->nextId = $this->backup['nextId'];
        $this->lastId = $this->backup['lastId'];
        $this->transaction = false;
        $this->backup = [];
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function executeMarked(string $sql, array $params): array
    {
        $marker = $this->marker($sql);
        if ($marker === 'list-batch') {
            $ids = array_values(array_filter(array_keys($this->legacyWebsites), static fn (int $id): bool => $id > (int) $params['after_id']));
            sort($ids);
            return [array_map(static fn (int $id): array => ['id' => $id], array_slice($ids, 0, (int) $params['batch_size'])), 0];
        }
        if ($marker === 'has-more') {
            foreach (array_keys($this->legacyWebsites) as $id) {
                if ($id > (int) $params['after_id']) {
                    return [[[1]], 0];
                }
            }
            return [[], 0];
        }
        if ($marker === 'load-website') {
            $this->currentLegacyId = (int) $params['legacy_website_id'];
            if (str_contains($sql, 'FOR UPDATE') && $this->mutateOnLockedLoadForLegacyId === $this->currentLegacyId) {
                $this->legacyPages[$this->currentLegacyId][0]['content_json'] = json_encode(['headline' => 'Changed during import'], JSON_THROW_ON_ERROR);
                $this->mutateOnLockedLoadForLegacyId = null;
            }
            $row = $this->legacyWebsites[$this->currentLegacyId] ?? null;
            return [$row === null ? [] : [$row], 0];
        }
        if ($marker === 'load-pages') {
            $rows = $this->legacyPages[(int) $params['website_id']] ?? [];
            usort($rows, static fn (array $a, array $b): int => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
            return [$rows, 0];
        }
        if (in_array($marker, ['load-overrides', 'load-service-images', 'load-service-pages', 'load-selected-service-references', 'load-custom-service-references'], true)) {
            return [[], 0];
        }
        if (in_array($marker, ['load-branding', 'load-integrations', 'load-configuration', 'load-business-content', 'load-profile-reference'], true)) {
            return [[], 0];
        }
        if (in_array($marker, ['lock-mapping', 'read-mapping'], true)) {
            $row = $this->mappingByLegacy((int) $params['legacy_website_id']);
            return [$row === null ? [] : [$row], 0];
        }
        if ($marker === 'insert-mapping') {
            $id = $this->newId();
            $this->mappings[$id] = [
                'id' => $id, 'legacy_website_id' => (int) $params['legacy_website_id'],
                'site_id' => null, 'import_revision_id' => null, 'import_status' => $params['status'],
                'source_hash' => $params['source_hash'], 'imported_hash' => null, 'attempt_count' => 1,
            ];
            return [[], 1];
        }
        if ($marker === 'retry-mapping') {
            $row = &$this->mappings[(int) $params['mapping_id']];
            $row['import_status'] = $params['status'];
            $row['source_hash'] = $params['source_hash'];
            $row['attempt_count']++;
            $row['error_code'] = null;
            return [[], 1];
        }
        if ($marker === 'site-key-collision') {
            foreach ($this->sites as $site) {
                if ($site['site_key'] === $params['site_key']) {
                    return [[['id' => $site['id']]], 0];
                }
            }
            return [[], 0];
        }
        if ($marker === 'insert-site') {
            if ($this->failOnSiteInsertForLegacyId === $this->currentLegacyId) {
                throw new RuntimeException('Injected database failure with private_table.secret.');
            }
            $id = $this->newId();
            $this->sites[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-association') {
            $id = $this->newId();
            $this->associations[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-brief') {
            $id = $this->newId();
            $this->briefs[$id] = ['id' => $id, 'brief_version' => 1] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-revision') {
            $id = $this->newId();
            $this->revisions[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'bind-mapping') {
            $row = &$this->mappings[(int) $params['mapping_id']];
            if ($row['site_id'] !== null
                || $row['import_revision_id'] !== null
                || $row['import_status'] !== $params['pending_status']
            ) {
                return [[], 0];
            }
            $row['site_id'] = (int) $params['site_id'];
            $row['import_revision_id'] = (int) $params['revision_id'];
            return [[], 1];
        }
        if ($marker === 'load-variants') {
            return [[
                ['id' => 1, 'variant_key' => 'about'], ['id' => 2, 'variant_key' => 'contact'],
                ['id' => 3, 'variant_key' => 'home'], ['id' => 4, 'variant_key' => 'service'],
            ], 0];
        }
        if ($marker === 'insert-logical-page') {
            $id = $this->newId();
            $this->sitePages[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-revision-page') {
            foreach ($this->revisionPages as $revisionPage) {
                if ((int) $revisionPage['revision_id'] === (int) $params['revision_id']
                    && (int) $revisionPage['sort_order'] === (int) $params['sort_order']
                ) {
                    throw new RuntimeException('Simulated UNIQUE (revision_id, sort_order) failure.');
                }
            }
            $id = $this->newId();
            $this->revisionPages[$id] = ['id' => $id, 'seo_json' => null] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-section') {
            $id = $this->newId();
            $this->sections[$id] = ['id' => $id, 'sort_order' => 10, 'configuration_schema_version' => 1] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-page-mapping') {
            $mapping = $this->mappings[(int) $params['mapping_id']] ?? null;
            $revisionPage = $this->revisionPages[(int) $params['revision_page_id']] ?? null;
            if ($mapping === null
                || (int) ($mapping['site_id'] ?? 0) !== (int) $params['site_id']
                || (int) ($mapping['import_revision_id'] ?? 0) !== (int) ($revisionPage['revision_id'] ?? 0)
            ) {
                throw new RuntimeException('Simulated composite mapping/site foreign-key failure.');
            }
            $this->verifiedBoundPageMappings++;
            if ($this->failOnPageMappingForLegacyId === $this->currentLegacyId) {
                throw new RuntimeException('Injected post-bind page-mapping failure.');
            }
            $id = $this->newId();
            $this->pageMappings[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-theme') {
            $id = $this->newId();
            $this->themes[$id] = ['id' => $id, 'theme_version' => 1, 'typography_json' => null] + $params;
            return [[], 1];
        }
        if ($marker === 'insert-event') {
            $id = $this->newId();
            $this->events[$id] = ['id' => $id] + $params;
            return [[], 1];
        }
        if ($marker === 'hash-pages') {
            $rows = [];
            foreach ($this->revisionPages as $row) {
                if ((int) $row['revision_id'] === (int) $params['revision_id']) {
                    $page = $this->sitePages[(int) $row['site_page_id']];
                    $rows[] = ['page_key' => $page['page_key']] + $row;
                }
            }
            usort($rows, static fn (array $a, array $b): int => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
            return [$rows, 0];
        }
        if ($marker === 'hash-sections') {
            $rows = [];
            foreach ($this->sections as $row) {
                if ((int) $row['revision_page_id'] === (int) $params['revision_page_id']) {
                    $variantKeys = [1 => 'about', 2 => 'contact', 3 => 'home', 4 => 'service'];
                    $rows[] = [
                        'component_key' => 'legacy_247sp_page',
                        'implementation_version' => 'legacy-preview-v1',
                        'variant_key' => $variantKeys[(int) $row['variant_id']],
                        'variant_configuration_schema_version' => 1,
                    ] + $row;
                }
            }
            return [$rows, 0];
        }
        if ($marker === 'hash-theme') {
            foreach ($this->themes as $row) {
                if ((int) $row['revision_id'] === (int) $params['revision_id']) {
                    return [[$row], 0];
                }
            }
            return [[], 0];
        }
        if ($marker === 'hash-assets') {
            return [[], 0];
        }
        if ($marker === 'hash-revision') {
            $row = $this->revisions[(int) $params['revision_id']] ?? null;
            return [$row === null ? [] : [[
                'snapshot_schema_version' => $row['schema_version'],
                'facts_snapshot_json' => $row['facts_json'],
                'source_references_json' => $row['references_json'],
                'generation_brief_id' => $row['brief_id'],
            ]], 0];
        }
        if ($marker === 'hash-brief') {
            $row = $this->briefs[(int) $params['brief_id']] ?? null;
            return [$row === null ? [] : [[
                'brief_version' => $row['brief_version'],
                'state' => $row['state'],
                'brief_json' => $row['brief_json'],
                'source_type' => $row['source_type'],
                'source_reference' => $row['source_reference'],
                'content_hash' => $row['content_hash'],
            ]], 0];
        }
        if ($marker === 'stored-revision-hash') {
            $row = $this->revisions[(int) $params['revision_id']] ?? null;
            return [$row === null ? [] : [['snapshot_hash' => $row['snapshot_hash']]], 0];
        }
        if ($marker === 'complete-mapping') {
            $row = &$this->mappings[(int) $params['mapping_id']];
            if ((int) ($row['site_id'] ?? 0) !== (int) $params['site_id']
                || (int) ($row['import_revision_id'] ?? 0) !== (int) $params['revision_id']
                || $row['import_status'] !== $params['pending_status']
            ) {
                return [[], 0];
            }
            $row['import_status'] = $params['imported_status'];
            $row['source_hash'] = $params['source_hash'];
            $row['imported_hash'] = $params['imported_hash'];
            $row['error_code'] = null;
            $this->verifiedMappingFinalizations++;
            return [[], 1];
        }
        if ($marker === 'page-count') {
            $count = 0;
            foreach ($this->revisionPages as $row) {
                $count += (int) $row['revision_id'] === (int) $params['revision_id'] ? 1 : 0;
            }
            return [[['page_count' => $count]], 0];
        }
        if ($marker === 'reconcile-mapping') {
            $row = &$this->mappings[(int) $params['mapping_id']];
            $row['import_status'] = $params['status'];
            $row['attempt_count']++;
            $row['error_code'] = null;
            return [[], 1];
        }
        if ($marker === 'quarantine-source') {
            return isset($this->legacyWebsites[(int) $params['legacy_website_id']]) ? [[['id' => (int) $params['legacy_website_id']]], 0] : [[], 0];
        }
        if ($marker === 'record-quarantine') {
            if ($this->failOnQuarantineWrite) {
                throw new RuntimeException('Injected quarantine persistence failure with private_table.secret.');
            }
            $row = $this->mappingByLegacy((int) $params['legacy_website_id']);
            if ($row === null) {
                $id = $this->newId();
                $this->mappings[$id] = [
                    'id' => $id, 'legacy_website_id' => (int) $params['legacy_website_id'],
                    'site_id' => null, 'import_revision_id' => null, 'import_status' => $params['status'],
                    'source_hash' => $params['source_hash'], 'imported_hash' => null, 'attempt_count' => 1,
                    'error_code' => $params['error_code'], 'error_summary' => $params['error_summary'],
                ];
            } else {
                $id = (int) $row['id'];
                $this->mappings[$id]['import_status'] = $params['status'];
                $this->mappings[$id]['source_hash'] ??= $params['source_hash'];
                $this->mappings[$id]['attempt_count']++;
                $this->mappings[$id]['error_code'] = $params['error_code'];
                $this->mappings[$id]['error_summary'] = $params['error_summary'];
            }
            return [[], 1];
        }

        throw new RuntimeException('Unexpected importer SQL marker: ' . $marker);
    }

    public function mappingByLegacyForTest(int $legacyWebsiteId): ?array
    {
        return $this->mappingByLegacy($legacyWebsiteId);
    }

    private function marker(string $sql): string
    {
        if (preg_match('/\/\* legacy-import:([a-z0-9-]+) \*\//', $sql, $matches) !== 1) {
            throw new RuntimeException('Unmarked importer SQL.');
        }
        return $matches[1];
    }

    private function newId(): int
    {
        $this->lastId = ++$this->nextId;
        return $this->lastId;
    }

    private function mappingByLegacy(int $legacyWebsiteId): ?array
    {
        foreach ($this->mappings as $row) {
            if ((int) $row['legacy_website_id'] === $legacyWebsiteId) {
                return $row;
            }
        }
        return null;
    }

    private function stateProperties(): array
    {
        return ['mappings', 'sites', 'associations', 'briefs', 'revisions', 'sitePages', 'revisionPages', 'sections', 'themes', 'pageMappings', 'events'];
    }
}

$assertions = 0;
function assertLegacyDatabase(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function useLegacyDatabaseConnection(LegacyImportDatabaseConnection $connection): void
{
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $connection);
}

$databaseSource = file_get_contents(__DIR__ . '/../private/classes/Database.php');
$importerSource = file_get_contents(__DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php');
assertLegacyDatabase(is_string($databaseSource) && str_contains($databaseSource, 'PDO::ATTR_EMULATE_PREPARES => false'), 'The database contract must retain native PDO prepares.');
assertLegacyDatabase(
    is_string($importerSource)
        && str_contains($importerSource, 'cd.status = :definition_status')
        && str_contains($importerSource, 'cv.status = :variant_status'),
    'Component variant loading must use distinct native-PDO status placeholders.'
);

$connection = LegacyImportDatabaseConnection::fixture();
useLegacyDatabaseConnection($connection);

$firstBatch = LegacyWebsitePlatformImporter::importBatch(2, 0);
assertLegacyDatabase($firstBatch['processed'] === 2 && $firstBatch['imported'] === 2, 'Two eligible sites must import in one bounded batch.');
assertLegacyDatabase($firstBatch['has_more'] === true && $firstBatch['next_after_id'] === 2, 'Batch cursor must report remaining work.');
assertLegacyDatabase(count($connection->sites) === 2 && count($connection->revisions) === 2, 'Each eligible legacy site must create one site and baseline revision.');
assertLegacyDatabase(count($connection->associations) === 2 && count($connection->revisionPages) === 2, 'Ownership and page composition must be imported per site.');
assertLegacyDatabase($connection->verifiedBoundPageMappings === 2, 'Every page mapping insert must observe an already-bound mapping/site parent tuple.');
assertLegacyDatabase($connection->verifiedMappingFinalizations === 2, 'Every completed import must finalize the same bound mapping relationship.');
$firstMapping = $connection->mappingByLegacyForTest(1);
$firstRevision = $connection->revisions[(int) $firstMapping['import_revision_id']];
assertLegacyDatabase(
    (int) $firstMapping['site_id'] === (int) $firstRevision['site_id'],
    'Successful finalization must preserve the mapping site/revision ownership established before page mappings.'
);
assertLegacyDatabase(
    hash_equals((string) $firstRevision['snapshot_hash'], (string) $firstMapping['imported_hash']),
    'The revision snapshot hash and mapping imported hash must store the same canonical evidence.'
);

$duplicateOrder = LegacyImportDatabaseConnection::fixture();
$duplicateOrder->legacyPages[1] = [
    ['id' => 101, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'home', 'title' => 'Home', 'slug' => 'home', 'content_json' => '{"name":"Home"}', 'status' => 'generated', 'sort_order' => 10, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 102, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'service', 'title' => 'Service 1', 'slug' => 'service-one', 'content_json' => '{"name":"Service 1"}', 'status' => 'generated', 'sort_order' => 20, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 103, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'service', 'title' => 'Service 2', 'slug' => 'service-two', 'content_json' => '{"name":"Service 2"}', 'status' => 'generated', 'sort_order' => 30, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 104, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'service', 'title' => 'Service 3', 'slug' => 'service-three', 'content_json' => '{"name":"Service 3"}', 'status' => 'generated', 'sort_order' => 40, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 105, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'service', 'title' => 'Service 4', 'slug' => 'service-four', 'content_json' => '{"name":"Service 4"}', 'status' => 'generated', 'sort_order' => 50, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 106, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'about', 'title' => 'About', 'slug' => 'about', 'content_json' => '{"name":"About"}', 'status' => 'generated', 'sort_order' => 50, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
    ['id' => 107, 'website_id' => 1, 'business_id' => 101, 'page_type' => 'contact', 'title' => 'Contact', 'slug' => 'contact', 'content_json' => '{"name":"Contact"}', 'status' => 'generated', 'sort_order' => 60, 'created_at' => '2026-08-30 12:00:00', 'updated_at' => '2026-08-30 12:00:00'],
];
useLegacyDatabaseConnection($duplicateOrder);
$duplicateResult = LegacyWebsitePlatformImporter::importWebsite(1);
assertLegacyDatabase($duplicateResult['result'] === 'imported', 'Duplicate raw legacy sort orders must import without quarantine.');
$duplicateMapping = $duplicateOrder->mappingByLegacyForTest(1);
$duplicateRevisionId = (int) $duplicateMapping['import_revision_id'];
$duplicateRevisionPages = array_values(array_filter(
    $duplicateOrder->revisionPages,
    static fn (array $page): bool => (int) $page['revision_id'] === $duplicateRevisionId
));
usort($duplicateRevisionPages, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
assertLegacyDatabase(array_column($duplicateRevisionPages, 'sort_order') === [10, 20, 30, 40, 50, 60, 70], 'Duplicate legacy order must produce unique ordinal generic sort orders.');
assertLegacyDatabase(count(array_unique(array_column($duplicateRevisionPages, 'sort_order'))) === 7, 'The fake database unique-order contract must observe zero generic duplicates.');
$duplicatePresentation = array_map(static fn (array $page): array => json_decode((string) $page['presentation_json'], true, 512, JSON_THROW_ON_ERROR), $duplicateRevisionPages);
assertLegacyDatabase(array_column($duplicatePresentation, 'legacy_page_id') === [101, 102, 103, 104, 105, 106, 107], 'Generic page sequence must preserve the legacy sort-order and ID sequence.');
assertLegacyDatabase(array_column($duplicatePresentation, 'legacy_sort_order') === [10, 20, 30, 40, 50, 50, 60], 'Imported presentation metadata must retain duplicate raw legacy orders.');
assertLegacyDatabase(hash_equals((string) $duplicateOrder->revisions[$duplicateRevisionId]['snapshot_hash'], (string) $duplicateMapping['imported_hash']), 'Duplicate-order import must preserve canonical preflight/import hash equality.');
$duplicateComparison = LegacyWebsitePlatformImporter::compareWebsite(1);
assertLegacyDatabase($duplicateComparison['source_matches'] === true, 'Duplicate-order import must reconcile its raw legacy source hash.');
assertLegacyDatabase($duplicateComparison['import_matches'] === true && $duplicateComparison['revision_hash_matches'] === true, 'Duplicate-order calculated, stored revision, and mapping hashes must reconcile.');

useLegacyDatabaseConnection($connection);

$siteCount = count($connection->sites);
$revisionCount = count($connection->revisions);
$rerun = LegacyWebsitePlatformImporter::importWebsite(1);
assertLegacyDatabase($rerun['result'] === 'reconciled', 'A completed duplicate run must reconcile.');
assertLegacyDatabase(count($connection->sites) === $siteCount && count($connection->revisions) === $revisionCount, 'A rerun must not create another site or revision.');

$legacyFourBefore = serialize([$connection->legacyWebsites[4], $connection->legacyPages[4]]);
$mixedBatch = LegacyWebsitePlatformImporter::importBatch(2, 2);
assertLegacyDatabase($mixedBatch['quarantined'] === 1 && $mixedBatch['imported'] === 1, 'One malformed site must not prevent the next site from importing.');
assertLegacyDatabase(($connection->mappingByLegacyForTest(3)['error_code'] ?? null) === 'malformed_page_json', 'Malformed JSON must have actionable quarantine evidence.');
assertLegacyDatabase(($mixedBatch['units'][0]['quarantine_evidence'] ?? null) === 'recorded', 'A quarantined result must explicitly confirm durable evidence.');
assertLegacyDatabase(serialize([$connection->legacyWebsites[4], $connection->legacyPages[4]]) === $legacyFourBefore, 'A successful import must not mutate legacy source rows.');

$connection->failOnSiteInsertForLegacyId = 5;
$sitesBeforeFailure = count($connection->sites);
$failed = LegacyWebsitePlatformImporter::importWebsite(5);
assertLegacyDatabase($failed['result'] === 'quarantined' && $failed['error_code'] === 'database_failure', 'A database failure must quarantine with a safe error.');
assertLegacyDatabase($failed['quarantine_evidence'] === 'recorded', 'A database failure must report that quarantine evidence was recorded.');
assertLegacyDatabase(count($connection->sites) === $sitesBeforeFailure && $connection->rollbackCount > 0, 'A failed unit must roll back all generic writes.');

$connection->legacyPages[3][0]['content_json'] = json_encode(['headline' => 'Business 3 repaired'], JSON_THROW_ON_ERROR);
$repaired = LegacyWebsitePlatformImporter::importWebsite(3);
assertLegacyDatabase($repaired['result'] === 'imported', 'A repaired quarantined source must be retryable.');

$sitesBeforeDrift = count($connection->sites);
$connection->legacyPages[1][0]['content_json'] = json_encode(['headline' => 'Changed after baseline'], JSON_THROW_ON_ERROR);
$drift = LegacyWebsitePlatformImporter::importWebsite(1);
assertLegacyDatabase($drift['result'] === 'quarantined' && $drift['error_code'] === 'source_changed', 'Changed legacy presentation must not overwrite the baseline revision.');
assertLegacyDatabase(count($connection->sites) === $sitesBeforeDrift, 'Source drift must not create another site.');

$unmappedSix = LegacyWebsitePlatformImporter::compareWebsite(6);
$connection->mappings[999] = [
    'id' => 999,
    'legacy_website_id' => 6,
    'site_id' => 999,
    'import_revision_id' => null,
    'import_status' => 'imported',
    'source_hash' => $unmappedSix['source_hash'],
    'imported_hash' => null,
    'attempt_count' => 1,
];
$collision = LegacyWebsitePlatformImporter::importWebsite(6);
assertLegacyDatabase($collision['result'] === 'quarantined' && $collision['error_code'] === 'mapping_collision', 'An inconsistent duplicate mapping must quarantine instead of duplicating data.');

assertLegacyDatabase($connection->beginCount === $connection->commitCount + $connection->rollbackCount, 'Every importer transaction must finish with commit or rollback.');

$concurrent = LegacyImportDatabaseConnection::fixture();
$concurrent->mutateOnLockedLoadForLegacyId = 1;
useLegacyDatabaseConnection($concurrent);
$retryable = LegacyWebsitePlatformImporter::importWebsite(1);
assertLegacyDatabase($retryable['result'] === 'retryable' && $retryable['error_code'] === 'source_changed_during_import', 'A DB source change between preflight and lock must return a retryable result.');
assertLegacyDatabase(count($concurrent->sites) === 0 && count($concurrent->mappings) === 0, 'A mixed preflight/locked snapshot must create no generic rows.');

$quarantineFailure = LegacyImportDatabaseConnection::fixture();
$quarantineFailure->failOnQuarantineWrite = true;
useLegacyDatabaseConnection($quarantineFailure);
$notDurable = LegacyWebsitePlatformImporter::importWebsite(3);
assertLegacyDatabase($notDurable['result'] === 'failed', 'A quarantine persistence failure must not be reported as quarantined.');
assertLegacyDatabase($notDurable['quarantine_evidence'] === 'persistence_failed', 'A quarantine persistence failure must be explicit.');
assertLegacyDatabase($notDurable['quarantine_failure_class'] === RuntimeException::class, 'Only bounded failure classification may be returned.');
assertLegacyDatabase(!str_contains(json_encode($notDurable, JSON_THROW_ON_ERROR), 'private_table.secret'), 'Raw quarantine database failures must not leak.');

$hashMismatch = LegacyImportDatabaseConnection::fixture();
useLegacyDatabaseConnection($hashMismatch);
LegacyWebsitePlatformImporter::importWebsite(1);
$mapping = $hashMismatch->mappingByLegacyForTest(1);
$hashMismatch->revisions[(int) $mapping['import_revision_id']]['snapshot_hash'] = str_repeat('0', 64);
$mismatch = LegacyWebsitePlatformImporter::importWebsite(1);
assertLegacyDatabase($mismatch['result'] === 'quarantined' && $mismatch['error_code'] === 'revision_hash_mismatch', 'Reconciliation must verify the stored revision snapshot hash.');

$postBind = LegacyImportDatabaseConnection::fixture();
useLegacyDatabaseConnection($postBind);
$postBind->failOnPageMappingForLegacyId = 1;
$legacyBeforePostBindFailure = serialize([$postBind->legacyWebsites[1], $postBind->legacyPages[1]]);
$postBindFailure = LegacyWebsitePlatformImporter::importWebsite(1);
$postBindMapping = $postBind->mappingByLegacyForTest(1);
assertLegacyDatabase($postBindFailure['result'] === 'quarantined' && $postBindFailure['error_code'] === 'database_failure', 'A failure after mapping bind must quarantine safely.');
assertLegacyDatabase($postBind->verifiedBoundPageMappings === 1, 'The injected failure must occur only after the mapping parent tuple was bound.');
assertLegacyDatabase(count($postBind->sites) === 0 && count($postBind->revisions) === 0 && count($postBind->pageMappings) === 0, 'Post-bind rollback must remove the complete partial generic aggregate.');
assertLegacyDatabase($postBindMapping !== null && $postBindMapping['site_id'] === null && $postBindMapping['import_revision_id'] === null, 'Fresh-import quarantine evidence must not retain rolled-back mapping ownership.');
assertLegacyDatabase(serialize([$postBind->legacyWebsites[1], $postBind->legacyPages[1]]) === $legacyBeforePostBindFailure, 'Post-bind failure must not mutate legacy source data.');

$postBindRetry = LegacyWebsitePlatformImporter::importWebsite(1);
$postBindRetryMapping = $postBind->mappingByLegacyForTest(1);
assertLegacyDatabase($postBindRetry['result'] === 'quarantined' && $postBindRetry['error_code'] === 'database_failure', 'A quarantined mapping retry must remain rollback-safe after binding.');
assertLegacyDatabase($postBindRetryMapping !== null && $postBindRetryMapping['site_id'] === null && $postBindRetryMapping['import_revision_id'] === null, 'Retried quarantine evidence must not retain rolled-back mapping ownership.');
assertLegacyDatabase(count($postBind->sites) === 0 && count($postBind->revisions) === 0 && count($postBind->pageMappings) === 0, 'Retried post-bind failure must leave no partial generic aggregate.');

$boundary = LegacyImportDatabaseConnection::fixture();
useLegacyDatabaseConnection($boundary);
$boundary->beginTransaction();
try {
    $reflection = new ReflectionMethod(LegacyWebsitePlatformImporter::class, 'collectAssetEvidence');
    $reflection->invoke(null, []);
    throw new RuntimeException('Filesystem collection was allowed inside a transaction.');
} catch (ReflectionException $exception) {
    throw $exception;
} catch (Throwable $exception) {
    $cause = $exception instanceof ReflectionException ? $exception : ($exception->getPrevious() ?? $exception);
    assertLegacyDatabase($cause instanceof LegacyWebsiteImportException && $cause->importErrorCode() === 'filesystem_in_transaction', 'Filesystem inspection must be rejected while a transaction is active.');
} finally {
    if ($boundary->inTransaction()) {
        $boundary->rollBack();
    }
}

echo "Legacy website importer database contract: {$assertions} assertions passed.\n";
