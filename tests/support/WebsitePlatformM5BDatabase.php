<?php

declare(strict_types=1);

require_once __DIR__ . '/WebsitePlatformM5ADatabase.php';

final class WebsitePlatformM5BStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;
    public function __construct(private WebsitePlatformM5BDatabase $db, private string $sql) {}
    public function execute(?array $params = null): bool { [$this->rows, $this->affected] = $this->db->executeSql($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { $rows = $this->rows; $this->rows = []; return $rows; }
    public function fetchColumn(int $column = 0): mixed { $row = array_shift($this->rows); return is_array($row) ? (array_values($row)[$column] ?? false) : false; }
    public function rowCount(): int { return $this->affected; }
}

/** Local deterministic SQL fixture. Does not simulate InnoDB locking or concurrency. */
final class WebsitePlatformM5BDatabase extends PDO
{
    public WebsitePlatformM5ADatabase $read;
    public array $queries = [];
    public $beforeTransaction = null;
    public bool $failEvent = false;
    public ?int $eventErrorCode = null;
    public function __construct() {}
    public static function fixture(): self
    {
        $db = new self(); $db->read = WebsitePlatformM5ADatabase::fixture();
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $db);
        $_SESSION = [];
        return $db;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        if (count($matches[1]) !== count(array_unique($matches[1]))) throw new RuntimeException('Repeated native placeholder');
        return new WebsitePlatformM5BStatement($this, $query);
    }
    public function addImage(): void
    {
        $base = $this->read->base;
        useWebsitePlatformM3ServiceDatabase($base);
        $base->sites[10]['lifecycle_status'] = 'draft';
        $base->revisions[100]['lifecycle_status'] = 'draft';
        $input = (new ReflectionMethod(SiteCompositionEditor::class, 'storedInput'))->invoke(null, 1, 100);
        $input['expected_snapshot_hash'] = $base->revisions[100]['snapshot_hash'];
        $input['pages'][0]['sections'][0] = ['section_key' => 'hero', 'component_key' => 'hero',
            'implementation_version' => '1.0.0', 'variant_key' => 'split_media', 'configuration_schema_version' => 1,
            'sort_order' => 1, 'configuration' => ['headline' => 'A customer image', 'media_usage_key' => 'hero_image'],
            'assets' => [['asset_id' => 1, 'usage_key' => 'hero_image']]];
        SiteCompositionManager::replaceDraftComposition(1, 100, $input);
        $base->sites[10]['lifecycle_status'] = 'pending_customer';
        $base->revisions[100]['lifecycle_status'] = 'ready_for_review';
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $this);
    }
    public function beginTransaction(): bool
    {
        if ($this->beforeTransaction !== null) { $hook = $this->beforeTransaction; $this->beforeTransaction = null; $hook($this); }
        return $this->read->beginTransaction();
    }
    public function commit(): bool { return $this->read->commit(); }
    public function rollBack(): bool { return $this->read->rollBack(); }
    public function inTransaction(): bool { return $this->read->inTransaction(); }
    public function lastInsertId(?string $name = null): string|false { return $this->read->base->lastInsertId(); }
    public function executeSql(string $sql, array $p): array
    {
        $this->queries[] = $sql;
        if (str_contains($sql, 'site-m2:prior-customer-decision')) {
            $rows = array_values(array_filter($this->read->base->approvals, fn ($r) => $r['site_id'] === $p['site_id']
                && $r['approval_type'] === 'customer' && in_array($r['state'], ['superseded', 'revoked'], true)
                && $this->read->base->revisions[$r['revision_id']]['revision_number'] < $p['current_revision_number']));
            return [$rows, 0];
        }
        if (str_starts_with($sql, 'SELECT id FROM users')) return [isset($this->read->users[$p['user_id']]) ? [['id' => $p['user_id']]] : [], 0];
        if (str_starts_with($sql, 'SELECT role_id FROM user_roles')) return [[], 0];
        if (str_starts_with($sql, 'SELECT r.id FROM roles')) return [[], 0];
        if (str_starts_with($sql, 'SELECT id FROM businesses')) return [[['id' => $p['business_id']]], 0];
        if (str_contains($sql, 'site-m5b:business-sites')) {
            $rows = [];
            foreach ($this->read->associations as $association) {
                if ($association['business_id'] === $p['business_id']) $rows[] = $association + ['id' => $association['site_id'], 'purpose' => $this->read->base->sites[$association['site_id']]['purpose']];
            }
            return [$rows, 0];
        }
        if (str_contains($sql, 'site-m5b:issued-reviews')) return [$this->read->executeSql('/* site-m5a:issued-reviews */', $p), 0];
        if (str_starts_with($sql, 'SELECT business_id FROM site_business_associations')) return [$this->read->executeSql('/* site-m5a:associations */', $p), 0];
        if (str_starts_with($sql, 'SELECT a.id FROM site_assets') || str_starts_with($sql, 'SELECT cd.id, cv.id AS variant_id')) return [[], 0];
        if (str_starts_with($sql, 'SELECT id FROM site_approvals WHERE revision_id')) {
            $rows = array_values(array_filter($this->read->base->approvals, fn ($r) => $r['revision_id'] === $p['revision_id'] && $r['approval_type'] === $p['type']));
            usort($rows, fn ($a, $b) => [$b['requested_at'], $b['id']] <=> [$a['requested_at'], $a['id']]);
            return [$rows, 0];
        }
        if (str_starts_with($sql, 'UPDATE site_approvals SET metadata_json')) {
            $row = &$this->read->base->approvals[$p['approval_id']];
            if ($row['state'] !== $p['state']) return [[], 0];
            $row['metadata_json'] = $p['metadata_json']; return [[], 1];
        }
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $sql)) {
            if ($this->failEvent && str_contains($sql, 'INSERT INTO site_events')) {
                if ($this->eventErrorCode !== null) throw new PDOException('Simulated lock failure', $this->eventErrorCode);
                throw new RuntimeException('Injected audit failure');
            }
            return $this->read->base->executeSql($sql, $p);
        }
        return [$this->read->executeSql($sql, $p), 0];
    }
}
