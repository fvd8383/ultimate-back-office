<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM5ADatabase.php';
require_once __DIR__ . '/WebsitePlatformM6BDependencies.php';

final class WebsitePlatformM6BStatement extends PDOStatement
{
    private array $rows = []; private int $affected = 0;
    public function __construct(private WebsitePlatformM6BDatabase $db, private string $sql) {}
    public function execute(?array $params = null): bool { [$this->rows, $this->affected] = $this->db->executeSql($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { $rows = $this->rows; $this->rows = []; return $rows; }
    public function fetchColumn(int $column = 0): mixed { $row = array_shift($this->rows); return is_array($row) ? (array_values($row)[$column] ?? false) : false; }
    public function rowCount(): int { return $this->affected; }
}

/** Behavioral SQL fixture. It does NOT prove MySQL DDL, locks, FKs or concurrency. */
final class WebsitePlatformM6BDatabase extends PDO
{
    public WebsitePlatformM5ADatabase $read;
    public WebsitePlatformM6BDependencies $runtime;
    public array $tables = ['site_build_jobs' => [], 'site_build_attempts' => [], 'site_releases' => [], 'site_release_validations' => []];
    public string $now = '2026-09-19 20:00:00.000000';
    public array $queries = [];
    public $beforeTransaction = null;
    public $onSql = null;
    public ?string $failTable = null;
    public bool $failAuthorization = false;
    public bool $loseCommitAck = false;
    private array $backup = [];
    private int $lastId = 0;
    public function __construct() {}
    public static function fixture(): self
    {
        $db = new self(); $db->read = WebsitePlatformM5ADatabase::fixture();
        $base = $db->read->base;
        $base->sites[10]['site_key'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $base->sites[10]['lifecycle_status'] = 'approved';
        $base->revisions[100]['lifecycle_status'] = 'internally_approved';
        $base->revisions[100]['review_ready_at'] = '2026-09-19 10:00:00';
        $base->revisions[100]['source_references_json'] = '{"business":{"business_id":50},"private":"SOURCE-SENTINEL"}';
        useWebsitePlatformM3ServiceDatabase($base);
        $base->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($base, 100);
        $base->approvals[700]['state'] = 'approved';
        $base->approvals[701] = array_replace($base->approvals[700], ['id' => 701, 'approval_type' => 'internal']);
        $db->read->associations[0]['id'] = 9;
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $db);
        $db->runtime = WebsitePlatformM6BDependencies::wire();
        return $db;
    }
    public function request(int $actor = 1, array $override = []): array
    {
        return SiteBuildService::requestBuild($actor, array_replace(['site_id' => 10, 'revision_id' => 100,
            'expected_snapshot_hash' => $this->read->base->revisions[100]['snapshot_hash'],
            'build_profile' => SiteBuildContract::PROFILE], $override));
    }
    public function claim(): array { return SiteBuildService::claimBuild([]) ?? throw new RuntimeException('Expected claim'); }
    public function receipt(array $claim, string $disposition): array
    {
        $job = $this->tables['site_build_jobs'][$claim['lease']['job_id']];
        $attempt = $this->tables['site_build_attempts'][$claim['lease']['attempt_id']];
        return $this->runtime->receipt($job, (int) ($attempt['recovery_of_attempt_id'] ?? $attempt['id']), $disposition);
    }
    public function advance(int $seconds): void { $this->now = SiteBuildStore::after($this->now, $seconds); }
    public function events(string $type): int
    {
        return count(array_filter($this->read->base->events, static fn ($e): bool => $e['event_type'] === $type));
    }
    public function snapshot(): string { return serialize([$this->tables, $this->read->snapshot()]); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        if (count($matches[1]) !== count(array_unique($matches[1]))) throw new RuntimeException('Repeated native placeholder');
        return new WebsitePlatformM6BStatement($this, $query);
    }
    public function beginTransaction(): bool
    {
        if ($this->beforeTransaction !== null) { $hook = $this->beforeTransaction; $this->beforeTransaction = null; $hook($this); }
        $this->backup = $this->tables;
        return $this->read->beginTransaction();
    }
    public function commit(): bool
    {
        $result = $this->read->commit(); $this->backup = [];
        if ($this->loseCommitAck) { $this->loseCommitAck = false; throw new PDOException('PRIVATE-LOST-ACK'); }
        return $result;
    }
    public function rollBack(): bool { $this->tables = $this->backup; $this->backup = []; return $this->read->rollBack(); }
    public function inTransaction(): bool { return $this->read->inTransaction(); }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
    public function executeSql(string $sql, array $p): array
    {
        $this->queries[] = $sql;
        if ($this->onSql !== null) ($this->onSql)($sql, $p, $this);
        $n = preg_replace('/\s+/', ' ', trim($sql));
        if ($this->failTable !== null && str_starts_with($n, 'INSERT INTO ' . $this->failTable)) {
            throw new PDOException('SECRET-RAW-SQL-PROVIDER-ERROR');
        }
        if (str_starts_with($n, 'SELECT UTC_TIMESTAMP')) return [[['build_now' => $this->now]], 0];
        if (preg_match('/^INSERT INTO (site_build_jobs|site_build_attempts|site_releases|site_release_validations) /', $n, $m)) {
            $table = $m[1]; $this->lastId++;
            $this->tables[$table][$this->lastId] = $p + ['id' => $this->lastId];
            return [[], 1];
        }
        if (preg_match('/^UPDATE (site_build_jobs|site_build_attempts) SET /', $n, $m)) {
            $table = $m[1]; $id = $p['owned_id']; $site = $p['owned_site_id'];
            if (($this->tables[$table][$id]['site_id'] ?? null) !== $site) return [[], 0];
            unset($p['owned_id'], $p['owned_site_id']);
            $this->tables[$table][$id] = array_replace($this->tables[$table][$id], $p);
            return [[], 1];
        }
        if (preg_match('/FROM (site_build_jobs|site_build_attempts|site_releases) WHERE /', $n, $m)) {
            $table = $m[1]; $rows = array_values($this->tables[$table]);
            foreach (['id' => 'id','site_id' => 'site_id','identity' => 'idempotency_key','job_id' => 'build_job_id','request_key' => 'operator_request_key',
                'revision_id' => 'revision_id','snapshot_hash' => 'snapshot_hash','build_profile' => 'build_profile',
                'builder_version' => 'builder_version','builder_code_sha' => 'builder_code_sha'] as $param => $column) {
                if (array_key_exists($param, $p)) $rows = array_values(array_filter($rows, static fn ($r): bool => ($r[$column] ?? null) === $p[$param]));
            }
            if (str_contains($n, "status IN ('requested','retry_wait')")) {
                $rows = array_values(array_filter($rows, fn ($r): bool => in_array($r['status'], ['requested','retry_wait'], true)
                    && ($r['next_attempt_at'] === null || $r['next_attempt_at'] <= $this->now)));
            }
            if (isset($p['cursor_time'])) $rows = array_values(array_filter($rows, static fn ($r): bool =>
                $r['created_at'] < $p['cursor_time'] || ($r['created_at'] === $p['same_time'] && $r['id'] < $p['cursor_id'])));
            if (str_contains($n, 'ORDER BY attempt_number DESC')) usort($rows, static fn ($a,$b): int => $b['attempt_number'] <=> $a['attempt_number']);
            elseif (str_contains($n, 'ORDER BY created_at DESC')) usort($rows, static fn ($a,$b): int => [$b['created_at'],$b['id']] <=> [$a['created_at'],$a['id']]);
            elseif (str_contains($n, 'ORDER BY id')) usort($rows, static fn ($a,$b): int => $a['id'] <=> $b['id']);
            if (preg_match('/LIMIT (\d+)/', $n, $limit)) $rows = array_slice($rows, 0, (int) $limit[1]);
            return [$rows, 0];
        }
        if (str_starts_with($n, 'SELECT id FROM users')) {
            if ($this->failAuthorization) throw new PDOException('PRIVATE-AUTH-DATABASE-FAILURE');
            return [isset($this->read->users[$p['user_id']]) ? [['id' => $p['user_id']]] : [], 0];
        }
        if (str_starts_with($n, 'SELECT role_id FROM user_roles') || str_starts_with($n, 'SELECT r.id FROM roles')) return [[], 0];
        if (str_contains($n, 'SELECT u.id, u.status')) return [$this->read->executeSql($sql, $p), 0];
        if (str_contains($n, 'site-m6:build-successors')) return [array_values(array_filter($this->read->base->revisions,
            static fn ($r): bool => $r['site_id'] === $p['site_id'] && $r['revision_number'] > $p['revision_number'])), 0];
        if (str_contains($n, 'site-m6:build-approvals')) return [array_values(array_filter($this->read->base->approvals,
            static fn ($a): bool => $a['site_id'] === $p['site_id'])), 0];
        if (str_contains($n, 'SELECT sba.business_id, b.status AS business_status')) {
            foreach ($this->read->associations as $a) {
                if ($a['site_id'] !== $p['site_id'] || $a['association_role'] !== $p['association_role'] || $a['status'] !== $p['association_status']) continue;
                $b = $this->read->businesses[$a['business_id']] ?? null;
                if ($b) return [[['business_id' => $b['id'], 'business_status' => $b['status'], 'is_suspended' => $b['is_suspended']]], 0];
            }
            return [[], 0];
        }
        if (str_contains($n, 'SELECT bm.id AS business_module_id')) return [
            $this->read->moduleActive && ($this->read->businesses[$p['business_id']]['module_active'] ?? false)
                ? [['business_module_id' => 1,'module_id' => 1]] : [], 0];
        if (str_contains($n, 'site-m6:build-association')) return [array_values(array_filter($this->read->associations,
            static fn ($a): bool => $a['site_id'] === $p['site_id'] && $a['association_role'] === 'customer' && $a['status'] === 'active')), 0];
        if (str_contains($n, 'site-m6:build-assets')) {
            $rows = [];
            foreach ($this->read->base->revisionAssets as $a) if ($a['revision_id'] === $p['revision_id'] && $a['site_id'] === $p['site_id']) {
                $asset = $this->read->base->siteAssets[$a['asset_id']];
                $rows[] = $asset + ['rights_current' => $asset['rights_expires_at'] === null || $asset['rights_expires_at'] > $this->now ? 1 : 0];
            }
            return [$rows, 0];
        }
        if (str_contains($n, 'SELECT cd.id, cv.id AS variant_id')) return [[], 0];
        return $this->read->base->executeSql($sql, $p);
    }
}
