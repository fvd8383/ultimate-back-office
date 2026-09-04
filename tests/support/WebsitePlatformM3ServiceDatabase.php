<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/private/classes/SiteCompositionManager.php';

final class WebsitePlatformM3ServiceStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private WebsitePlatformM3ServiceDatabase $database, private string $sql)
    {
    }

    public function execute(?array $params = null): bool
    {
        [$this->rows, $this->affected] = $this->database->executeSql($this->sql, $params ?? []);
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

final class WebsitePlatformM3ServiceDatabase extends PDO
{
    public array $sites = [];
    public array $revisions = [];
    public array $sitePages = [];
    public array $revisionPages = [];
    public array $sections = [];
    public array $themes = [];
    public array $siteAssets = [];
    public array $revisionAssets = [];
    public array $events = [];
    public array $definitions = [];
    public array $variants = [];
    public bool $failAfterDeletion = false;
    public bool $internalAdmin = true;
    public string $internalRole = 'Admin';
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;

    private bool $transaction = false;
    private array $backup = [];
    private int $nextId = 1000;
    private int $lastId = 0;

    public function __construct()
    {
    }

    public static function fixture(): self
    {
        $database = new self();
        $database->sites[10] = [
            'id' => 10, 'site_key' => 'site-ten', 'purpose' => '247sp', 'lifecycle_status' => 'active',
            'current_published_revision_id' => null, 'lock_version' => 0,
        ];
        $database->revisions[100] = [
            'id' => 100, 'site_id' => 10, 'revision_number' => 1, 'lifecycle_status' => 'draft',
            'based_on_revision_id' => null, 'restored_from_revision_id' => null,
            'generation_brief_id' => null, 'materiality' => 'material', 'snapshot_schema_version' => 1,
            'facts_snapshot_json' => '{}', 'source_references_json' => '{}',
            'snapshot_hash' => str_repeat('0', 64), 'created_by_user_id' => 1,
            'review_ready_at' => null, 'published_at' => null, 'created_at' => '2026-09-02', 'updated_at' => '2026-09-02',
        ];
        $database->siteAssets[1] = [
            'id' => 1, 'site_id' => 10, 'business_id' => 50, 'active_business_id' => 50,
            'asset_type' => 'image', 'storage_key' => 'sites/10/hero.jpg',
            'checksum_sha256' => str_repeat('a', 64), 'mime_type' => 'image/jpeg', 'byte_size' => 1234,
            'source' => 'upload', 'rights_classification' => 'customer_owned', 'rights_metadata_json' => '{}',
            'rights_expires_at' => null, 'lifecycle_status' => 'ready',
        ];
        $definitionId = 1;
        $variantId = 100;
        foreach (ComponentRegistry::manifest() as $definition) {
            $identity = $definition['component_key'] . '@' . $definition['implementation_version'];
            $database->definitions[$identity] = [
                'definition_id' => $definitionId++, 'component_key' => $definition['component_key'],
                'implementation_version' => $definition['implementation_version'], 'label' => $definition['label'],
                'category' => $definition['category'], 'configuration_schema_version' => $definition['configuration_schema_version'],
                'definition_status' => 'active', 'definition_metadata_json' => json_encode([
                    'scope' => $definition['scope'], 'authorable' => $definition['authorable'],
                ], JSON_THROW_ON_ERROR), 'variants' => [],
            ];
            foreach ($definition['variants'] as $key => $variant) {
                $row = [
                    'variant_id' => $variantId, 'variant_key' => $key,
                    'variant_schema_version' => $variant['schema_version'], 'variant_status' => 'active',
                    'variant_metadata_json' => '{}', 'component_key' => $definition['component_key'],
                    'implementation_version' => $definition['implementation_version'],
                ];
                $database->definitions[$identity]['variants'][$key] = $row;
                $database->variants[$variantId++] = $row;
            }
        }
        return $database;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        if (count($matches[1]) !== count(array_unique($matches[1]))) {
            throw new RuntimeException('Duplicate native PDO placeholder in service SQL.');
        }
        return new WebsitePlatformM3ServiceStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('Nested transaction.');
        }
        $this->backup = [];
        foreach ($this->stateProperties() as $property) {
            $this->backup[$property] = $this->{$property};
        }
        $this->backup['nextId'] = $this->nextId;
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

    public function executeSql(string $sql, array $p): array
    {
        $n = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql);
        if (str_contains($n, 'site-m4b:asset-catalog')) {
            $rows = [];
            foreach ($this->siteAssets as $row) {
                if ((int) $row['site_id'] === (int) $p['site_id']) {
                    $rows[] = $row + ['purpose' => $this->sites[$p['site_id']]['purpose']];
                }
            }
            return [$rows, 0];
        }
        if (str_contains($n, 'site-m4b:asset-provenance')) {
            return [array_values(array_filter($this->revisionAssets, fn ($row) => (int) $row['revision_id'] === (int) $p['revision_id'])), 0];
        }
        if (str_contains($n, 'from users u')) {
            return [[[ 'id' => 1, 'status' => 'active', 'role_name' => $this->internalAdmin ? $this->internalRole : null ]], 0];
        }
        if (str_contains($n, 'from sites s') && str_contains($n, 'inner join site_business_associations')) {
            return [[], 0];
        }
        if (str_contains($n, 'site-m3:composition-revision-site') || $n === 'select site_id from site_revisions where id = :revision_id limit 1') {
            $row = $this->revisions[(int) $p['revision_id']] ?? null;
            return [$row ? [['site_id' => $row['site_id']]] : [], 0];
        }
        if (str_contains($n, 'from sites where id = :site_id for update')) {
            $row = $this->sites[(int) $p['site_id']] ?? null;
            return [$row ? [$row] : [], 0];
        }
        if (str_contains($n, 'from site_revisions where id = :revision_id for update')) {
            $row = $this->revisions[(int) $p['revision_id']] ?? null;
            return [$row ? [$row] : [], 0];
        }
        if (str_contains($n, 'from site_revisions where id = :revision_id limit 1')) {
            $row = $this->revisions[(int) $p['revision_id']] ?? null;
            return [$row ? [$row] : [], 0];
        }
        if ($n === 'select purpose from sites where id = :site_id limit 1') {
            $row = $this->sites[(int) $p['site_id']] ?? null;
            return [$row ? [['purpose' => $row['purpose']]] : [], 0];
        }
        if (str_contains($n, 'site-m3:composition-state')) {
            $revisionId = (int) $p['page_revision_id'];
            return [[[
                'page_count' => count(array_filter($this->revisionPages, fn ($r) => (int) $r['revision_id'] === $revisionId)),
                'theme_count' => count(array_filter($this->themes, fn ($r) => (int) $r['revision_id'] === $revisionId)),
                'asset_count' => count(array_filter($this->revisionAssets, fn ($r) => (int) $r['revision_id'] === $revisionId)),
            ]], 0];
        }
        if (str_contains($n, 'site-m3:composition-editor-assets')) {
            return [array_values(array_map(fn ($r) => ['asset_id' => $r['asset_id'], 'usage_key' => $r['usage_key']], array_filter(
                $this->revisionAssets, fn ($r) => (int) $r['revision_id'] === (int) $p['revision_id']
            ))), 0];
        }
        if (str_contains($n, 'site-m3:resolve-component')) {
            $definition = $this->definitions[$p['component_key'] . '@' . $p['implementation_version']] ?? null;
            $variant = $definition['variants'][$p['variant_key']] ?? null;
            return [$definition && $variant ? [[
                'definition_id' => $definition['definition_id'], 'label' => $definition['label'], 'category' => $definition['category'],
                'configuration_schema_version' => $definition['configuration_schema_version'],
                'definition_status' => $definition['definition_status'], 'definition_metadata_json' => $definition['definition_metadata_json'],
                'variant_id' => $variant['variant_id'], 'variant_schema_version' => $variant['variant_schema_version'],
                'variant_status' => $variant['variant_status'], 'variant_metadata_json' => '{}',
            ]] : [], 0];
        }
        if (str_contains($n, 'site-m3:resolve-asset')) {
            $row = $this->siteAssets[(int) $p['asset_id']] ?? null;
            return [$row && (int) $row['site_id'] === (int) $p['site_id'] ? [$row] : [], 0];
        }
        if (str_contains($n, 'from site_pages where site_id = :site_id and page_key = :page_key')) {
            foreach ($this->sitePages as $row) {
                if ((int) $row['site_id'] === (int) $p['site_id'] && $row['page_key'] === $p['page_key']) {
                    return [[['id' => $row['id'], 'retired_at' => $row['retired_at']]], 0];
                }
            }
            return [[], 0];
        }
        if (str_starts_with($n, 'insert into site_pages')) {
            $id = $this->newId();
            $this->sitePages[$id] = ['id' => $id, 'retired_at' => null] + $p;
            return [[], 1];
        }
        if (preg_match('/^delete from (site_revision_assets|site_page_sections|site_revision_pages|site_themes)/', $n, $m) === 1) {
            $property = ['site_revision_assets' => 'revisionAssets', 'site_page_sections' => 'sections', 'site_revision_pages' => 'revisionPages', 'site_themes' => 'themes'][$m[1]];
            $before = count($this->{$property});
            $this->{$property} = array_filter($this->{$property}, fn ($r) => !((int) $r['site_id'] === (int) $p['site_id'] && (int) $r['revision_id'] === (int) $p['revision_id']));
            return [[], $before - count($this->{$property})];
        }
        if (str_starts_with($n, 'insert into site_revision_pages')) {
            if ($this->failAfterDeletion) {
                throw new RuntimeException('Injected failure after deletion.');
            }
            $id = $this->newId();
            $this->revisionPages[$id] = ['id' => $id] + $p;
            return [[], 1];
        }
        if (str_starts_with($n, 'insert into site_page_sections')) {
            $id = $this->newId();
            $this->sections[$id] = ['id' => $id] + $p;
            return [[], 1];
        }
        if (str_starts_with($n, 'insert into site_themes')) {
            $id = $this->newId();
            $this->themes[$id] = ['id' => $id] + $p;
            return [[], 1];
        }
        if (str_starts_with($n, 'insert into site_revision_assets')) {
            $id = $this->newId();
            $this->revisionAssets[$id] = ['id' => $id] + $p;
            return [[], 1];
        }
        if (str_contains($n, 'legacy-import:hash-revision')) {
            $r = $this->revisions[(int) $p['revision_id']] ?? null;
            return [$r ? [[
                'snapshot_schema_version' => $r['snapshot_schema_version'], 'facts_snapshot_json' => $r['facts_snapshot_json'],
                'source_references_json' => $r['source_references_json'], 'generation_brief_id' => $r['generation_brief_id'],
            ]] : [], 0];
        }
        if (str_contains($n, 'legacy-import:hash-pages')) {
            return [$this->hashPages((int) $p['revision_id']), 0];
        }
        if (str_contains($n, 'legacy-import:hash-sections')) {
            return [$this->hashSections((int) $p['revision_page_id']), 0];
        }
        if (str_contains($n, 'legacy-import:hash-theme')) {
            foreach ($this->themes as $r) {
                if ((int) $r['revision_id'] === (int) $p['revision_id']) {
                    return [[[$r + ['typography_json' => $r['typography_json'] ?? null]][0]], 0];
                }
            }
            return [[], 0];
        }
        if (str_contains($n, 'legacy-import:hash-assets')) {
            return [$this->hashAssets((int) $p['revision_id']), 0];
        }
        if (str_starts_with($n, 'update site_revisions set snapshot_hash')) {
            $id = (int) $p['revision_id'];
            if (!isset($this->revisions[$id]) || $this->revisions[$id]['lifecycle_status'] !== $p['lifecycle_status']) {
                return [[], 0];
            }
            $this->revisions[$id]['snapshot_hash'] = $p['snapshot_hash'];
            return [[], 1];
        }
        if (str_starts_with($n, 'insert into site_events')) {
            $id = $this->newId();
            $this->events[$id] = ['id' => $id] + $p;
            return [[], 1];
        }
        if (str_contains($n, 'select srp.id, count(sps.id) as section_count')) {
            $rows = [];
            foreach ($this->revisionPages as $page) {
                if ((int) $page['revision_id'] === (int) $p['revision_id'] && (int) $page['site_id'] === (int) $p['site_id']) {
                    $rows[] = ['id' => $page['id'], 'section_count' => count(array_filter($this->sections, fn ($s) => (int) $s['revision_page_id'] === (int) $page['id']))];
                }
            }
            return [$rows, 0];
        }
        if (str_starts_with($n, 'select count(*) from site_themes')) {
            $count = count(array_filter($this->themes, fn ($r) => (int) $r['revision_id'] === (int) $p['revision_id'] && (int) $r['site_id'] === (int) $p['site_id']));
            return [[['count' => $count]], 0];
        }
        if (str_contains($n, 'from site_revision_assets sra') && str_contains($n, 'ownership is inconsistent') === false && str_contains($n, 'select count(*)')) {
            return [[['count' => 0]], 0];
        }
        if (str_contains($n, 'site-m3:load-stored-assets')) {
            return [$this->loadedAssets((int) $p['revision_id']), 0];
        }
        if (str_contains($n, 'site-m3:load-stored-pages')) {
            return [$this->loadedPages((int) $p['revision_id']), 0];
        }
        if (str_contains($n, 'site-m3:load-stored-sections')) {
            return [$this->loadedSections((int) $p['revision_page_id']), 0];
        }
        if (str_contains($n, 'site-m3:load-stored-theme')) {
            foreach ($this->themes as $r) {
                if ((int) $r['revision_id'] === (int) $p['revision_id']) {
                    return [[$r], 0];
                }
            }
            return [[], 0];
        }
        if (str_starts_with($n, 'update site_revisions set lifecycle_status')) {
            $id = (int) $p['revision_id'];
            if ($this->revisions[$id]['lifecycle_status'] !== $p['current_status']) {
                return [[], 0];
            }
            $this->revisions[$id]['lifecycle_status'] = $p['target_status'];
            $this->revisions[$id]['review_ready_at'] = $p['set_review_ready'] ? '2026-09-02' : $this->revisions[$id]['review_ready_at'];
            return [[], 1];
        }
        throw new RuntimeException('Unexpected service SQL: ' . $sql);
    }

    private function hashPages(int $revisionId): array
    {
        $rows = [];
        foreach ($this->revisionPages as $r) {
            if ((int) $r['revision_id'] === $revisionId) {
                $page = $this->sitePages[(int) $r['site_page_id']];
                $rows[] = ['page_key' => $page['page_key']] + $r;
            }
        }
        usort($rows, fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        return $rows;
    }

    private function hashSections(int $revisionPageId): array
    {
        $rows = [];
        foreach ($this->sections as $r) {
            if ((int) $r['revision_page_id'] === $revisionPageId) {
                $v = $this->variants[(int) $r['component_variant_id']];
                $rows[] = [
                    'component_key' => $v['component_key'], 'implementation_version' => $v['implementation_version'],
                    'variant_key' => $v['variant_key'], 'variant_configuration_schema_version' => $v['variant_schema_version'],
                ] + $r;
            }
        }
        usort($rows, fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        return $rows;
    }

    private function hashAssets(int $revisionId): array
    {
        $rows = [];
        foreach ($this->revisionAssets as $r) {
            if ((int) $r['revision_id'] !== $revisionId) {
                continue;
            }
            $asset = $this->siteAssets[(int) $r['asset_id']];
            $page = $r['revision_page_id'] === null ? null : $this->revisionPages[(int) $r['revision_page_id']];
            $stable = $page === null ? null : $this->sitePages[(int) $page['site_page_id']];
            $section = $r['section_id'] === null ? null : $this->sections[(int) $r['section_id']];
            $rows[] = [
                'page_key' => $stable['page_key'] ?? null, 'section_key' => $section['section_key'] ?? null,
                'source_reference' => $r['source_reference'], 'usage_key' => $r['usage_key'],
            ] + $asset;
        }
        usort($rows, fn ($a, $b) => [$a['usage_key'], $a['storage_key'], $a['page_key']] <=> [$b['usage_key'], $b['storage_key'], $b['page_key']]);
        return $rows;
    }

    private function loadedPages(int $revisionId): array
    {
        return $this->hashPages($revisionId);
    }

    private function loadedSections(int $revisionPageId): array
    {
        return $this->hashSections($revisionPageId);
    }

    private function loadedAssets(int $revisionId): array
    {
        return array_map(fn ($r) => [
            'asset_id' => $r['id'], 'usage_key' => $r['usage_key'], 'source_reference' => $r['source_reference'],
            'page_key' => $r['page_key'], 'section_key' => $r['section_key'],
        ], $this->hashAssets($revisionId));
    }

    private function newId(): int
    {
        $this->lastId = ++$this->nextId;
        return $this->lastId;
    }

    private function stateProperties(): array
    {
        return ['sites', 'revisions', 'sitePages', 'revisionPages', 'sections', 'themes', 'siteAssets', 'revisionAssets', 'events'];
    }
}

function useWebsitePlatformM3ServiceDatabase(WebsitePlatformM3ServiceDatabase $database): void
{
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $database);
}
