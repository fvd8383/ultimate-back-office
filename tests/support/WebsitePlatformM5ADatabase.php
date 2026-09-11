<?php

declare(strict_types=1);

require_once __DIR__ . '/WebsitePlatformM3ServiceDatabase.php';
require_once dirname(__DIR__, 2) . '/private/classes/SiteCompositionEditor.php';
require_once dirname(__DIR__, 2) . '/private/classes/SiteCustomerPreview.php';

final class WebsitePlatformM5AStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private WebsitePlatformM5ADatabase $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->db->executeSql($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { $rows = $this->rows; $this->rows = []; return $rows; }
    public function fetchColumn(int $column = 0): mixed { $row = array_shift($this->rows); return is_array($row) ? (array_values($row)[$column] ?? false) : false; }
}

/** Read-only SQL fixture; real M3 storage/validation is delegated to its existing fixture. */
final class WebsitePlatformM5ADatabase extends PDO
{
    public WebsitePlatformM3ServiceDatabase $base;
    public array $users = [];
    public array $businesses = [];
    public array $memberships = [];
    public array $associations = [];
    public bool $moduleActive = true;
    public array $queries = [];
    public $beforeTransaction = null;
    public function __construct() {}

    public static function fixture(): self
    {
        $db = new self();
        $db->base = WebsitePlatformM3ServiceDatabase::fixture();
        useWebsitePlatformM3ServiceDatabase($db->base);
        $db->base->sites[10]['lifecycle_status'] = 'draft';
        $db->base->revisions[100]['facts_snapshot_json'] = '{"private":"FACTS-SENTINEL"}';
        $db->base->revisions[100]['source_references_json'] = '{"private":"SOURCE-SENTINEL"}';
        SiteCompositionEditor::apply(1, 100, ['operation' => 'initialize_new', 'expected_snapshot_hash' => $db->base->revisions[100]['snapshot_hash']]);
        $db->base->revisions[100]['lifecycle_status'] = 'ready_for_review';
        $db->base->sites[10]['lifecycle_status'] = 'pending_customer';
        $db->base->approvals[700] = self::request(700, 100);
        $db->users = [
            1 => ['status' => 'active', 'roles' => ['Admin']],
            2 => ['status' => 'active', 'roles' => ['Super Admin']],
            3 => ['status' => 'active', 'roles' => []],
            4 => ['status' => 'active', 'roles' => []],
            5 => ['status' => 'active', 'roles' => []],
            6 => ['status' => 'active', 'roles' => []],
        ];
        $db->businesses = [50 => ['id' => 50, 'business_name' => 'Acme <Review> & Sons', 'status' => 'active', 'is_suspended' => 0, 'module_active' => 1],
            60 => ['id' => 60, 'business_name' => 'Other tenant', 'status' => 'active', 'is_suspended' => 0, 'module_active' => 1]];
        foreach ([1 => 'Owner', 2 => 'Owner', 3 => 'Owner', 4 => 'Admin', 5 => 'Employee'] as $user => $role) {
            $db->memberships[$user][50] = ['status' => 'active', 'business_role' => $role, 'is_owner' => $user === 3 ? 1 : 0];
        }
        $db->memberships[6][60] = ['status' => 'active', 'business_role' => 'Owner', 'is_owner' => 1];
        $db->associations = [['site_id' => 10, 'business_id' => 50, 'association_role' => 'customer', 'status' => 'active']];
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $db);
        return $db;
    }

    public static function request(int $id, int $revisionId): array
    {
        return ['id' => $id, 'site_id' => 10, 'revision_id' => $revisionId, 'approval_type' => 'customer', 'state' => 'requested',
            'actor_user_id' => 1, 'actor_type' => 'internal_admin', 'comments' => 'INTERNAL-COMMENT', 'reason' => 'INTERNAL-REASON',
            'requested_at' => '2026-09-10 12:00:00', 'decided_at' => null, 'revoked_at' => null,
            'metadata_json' => '{"private":"METADATA-SENTINEL"}', 'correlation_id' => 'CORRELATION-SENTINEL'];
    }

    public function snapshot(): string
    {
        return serialize([$this->base->sites, $this->base->revisions, $this->base->approvals, $this->base->sitePages,
            $this->base->revisionPages, $this->base->sections, $this->base->themes, $this->base->siteAssets,
            $this->base->revisionAssets, $this->base->events, $this->businesses, $this->associations, $this->memberships, $this->users]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        if (count($matches[1]) !== count(array_unique($matches[1]))) throw new RuntimeException('Repeated native placeholder');
        return new WebsitePlatformM5AStatement($this, $query);
    }
    public function beginTransaction(): bool
    {
        if ($this->beforeTransaction !== null) { $hook = $this->beforeTransaction; $this->beforeTransaction = null; $hook($this); }
        return $this->base->beginTransaction();
    }
    public function commit(): bool { return $this->base->commit(); }
    public function rollBack(): bool { return $this->base->rollBack(); }
    public function inTransaction(): bool { return $this->base->inTransaction(); }

    public function executeSql(string $sql, array $p): array
    {
        $this->queries[] = $sql;
        if (preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', str_replace('FOR UPDATE', '', $sql))) {
            throw new RuntimeException('Domain mutation attempted by M5A');
        }
        if (str_contains($sql, 'SELECT u.id, u.status, r.name AS role_name')) {
            $user = $this->users[$p['user_id']] ?? null;
            if ($user === null) return [];
            return array_map(fn ($role) => ['id' => $p['user_id'], 'status' => $user['status'], 'role_name' => $role], $user['roles'] ?: [null]);
        }
        if (str_contains($sql, 'site-m5a:business')) {
            foreach ($this->businesses as $id => $b) {
                $member = $this->memberships[$p['actor_id']][$id] ?? null;
                if (isset($p['business_id']) && $id !== $p['business_id']) continue;
                if (isset($p['default_status']) && $b['status'] !== $p['default_status']) continue;
                if ($member === null || $member['status'] !== 'active') continue;
                return [array_merge($b, ['business_role' => $member['business_role'], 'is_owner' => $member['is_owner'],
                    'module_active' => $this->moduleActive ? $b['module_active'] : 0])];
            }
            return [];
        }
        if (str_contains($sql, 'site-m5a:sites')) {
            $rows = [];
            foreach ($this->associations as $a) {
                if ($a['business_id'] === $p['business_id'] && $a['status'] === $p['status'] && $a['association_role'] === $p['role']
                    && ($this->base->sites[$a['site_id']]['purpose'] ?? '') === $p['purpose']) $rows[] = ['id' => $a['site_id']];
            }
            return $rows;
        }
        if (str_contains($sql, 'site-m5a:associations')) {
            return array_values(array_map(fn ($a) => ['business_id' => $a['business_id']], array_filter($this->associations,
                fn ($a) => $a['site_id'] === $p['site_id'] && $a['status'] === $p['status'] && $a['association_role'] === $p['role'])));
        }
        if (str_contains($sql, 'SELECT s.id AS site_id, sba.business_id, bu.is_owner')) {
            foreach ($this->associations as $a) {
                $b = $this->businesses[$a['business_id']] ?? null;
                $m = $this->memberships[$p['user_id']][$a['business_id']] ?? null;
                if ($a['site_id'] === $p['site_id'] && $a['status'] === 'active' && $a['association_role'] === 'customer'
                    && $b && $b['status'] === 'active' && !$b['is_suspended'] && $b['module_active'] && $this->moduleActive
                    && $m && $m['status'] === 'active' && $this->base->sites[$a['site_id']]['purpose'] === '247sp') {
                    return [['site_id' => $a['site_id'], 'business_id' => $a['business_id'], 'is_owner' => $m['is_owner'], 'business_role' => $m['business_role']]];
                }
            }
            return [];
        }
        if (str_contains($sql, 'site-m5a:issued-reviews')) {
            $rows = [];
            foreach ($this->base->approvals as $a) {
                if ($a['site_id'] !== $p['site_id'] || $a['approval_type'] !== $p['type']) continue;
                $r = $this->base->revisions[$a['revision_id']] ?? null;
                $rows[] = $a + ['revision_site_id' => $r['site_id'] ?? null, 'revision_number' => $r['revision_number'] ?? null];
            }
            usort($rows, fn ($a, $b) => [$b['revision_number'], $b['requested_at'], $b['id']] <=> [$a['revision_number'], $a['requested_at'], $a['id']]);
            return $rows;
        }
        return $this->base->executeSql($sql, $p)[0];
    }
}
