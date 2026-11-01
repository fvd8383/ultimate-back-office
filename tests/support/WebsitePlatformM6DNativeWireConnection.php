<?php
declare(strict_types=1);

/** Test-only real-PDO decorator. No post-commit exception, reconnect or substituted SQL. */
final class M6DNativeWireConnection extends PDO
{
    public const READ_SQL='SELECT id, site_key, purpose, lifecycle_status, current_published_revision_id, lock_version
             FROM sites WHERE id = :site_id FOR UPDATE';
    private bool $result=false;
    private bool $used=false;
    public bool $commitReturned=false;
    public ?array $transportFailure=null;
    public function __construct(private PDO $inner,private array $target,private string $mode,private Closure $boundary) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    { return new M6DNativeWireStatement($this,$this->inner->prepare($query,$options),$query); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false { return $this->inner->query($query); }
    public function exec(string $statement): int|false { return $this->inner->exec($statement); }
    public function lastInsertId(?string $name=null): string|false { return $this->inner->lastInsertId($name); }
    public function beginTransaction(): bool { $this->result=false; return $this->inner->beginTransaction(); }
    public function inTransaction(): bool { return $this->inner->inTransaction(); }
    public function rollBack(): bool { $this->result=false; return $this->inner->rollBack(); }
    public function beforeStatement(string $sql,?array $params): void
    {
        // Only Git checkout line endings may differ in this fixed PHP source literal.
        if($this->mode==='drop-query' && !$this->used && str_replace("\r\n","\n",$sql)===str_replace("\r\n","\n",self::READ_SQL)) {
            M6DNativeContract::need($params===['site_id'=>$this->target['site_id']] && $this->inner->inTransaction(),'wire_read_target');
            $this->used=true; ($this->boundary)('READ');
            // The caller immediately invokes this already-prepared statement's real execute().
        }
    }
    public function afterStatement(string $sql,?array $params): void
    {
        if(!str_starts_with($sql,'INSERT INTO site_events') || ($params['event_type'] ?? null)!=='site_deployment_succeeded') return;
        $m=json_decode($params['metadata_json'],true,16,JSON_THROW_ON_ERROR);
        M6DNativeContract::need($this->inner->inTransaction() && ($params['site_id'] ?? null)===$this->target['site_id']
            && ($m['deployment_id'] ?? null)===$this->target['deployment_id'] && ($m['target_id'] ?? null)===$this->target['target_id']
            && ($m['attempt_id'] ?? null)===$this->target['attempt_id'] && !$this->result && !$this->used,'wire_result_target');
        $this->result=true;
    }
    public function failed(PDOException $e): void
    {
        $this->transportFailure=['sqlstate'=>preg_match('/^[A-Z0-9]{5}$/D',(string)($e->errorInfo[0] ?? '')) ? $e->errorInfo[0] : null,
            'driver'=>is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : null];
    }
    public function commit(): bool
    {
        $selected=$this->result;
        if($selected) {
            M6DNativeContract::need(!$this->used && $this->mode!=='drop-query','wire_commit_phase');
            $this->used=true; $this->result=false; ($this->boundary)('COMMIT');
        }
        // No SQL, probes, timers or synthetic exceptions between the barrier and inner commit.
        try { $r=$this->inner->commit(); }
        catch(PDOException $e) { if($selected)$this->failed($e); throw $e; }
        if($selected)$this->commitReturned=$r;
        return $r;
    }
}
final class M6DNativeWireStatement extends PDOStatement
{
    public function __construct(private M6DNativeWireConnection $owner,private PDOStatement $inner,private string $sql) {}
    public function execute(?array $params=null): bool
    {
        $this->owner->beforeStatement($this->sql,$params);
        try { $ok=$this->inner->execute($params); }
        catch(PDOException $e) { $this->owner->failed($e); throw $e; }
        if($ok)$this->owner->afterStatement($this->sql,$params);
        return $ok;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->inner->fetch($mode,$cursorOrientation,$cursorOffset); }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->inner->fetchAll($mode,...$args); }
    public function fetchColumn(int $column=0): mixed { return $this->inner->fetchColumn($column); }
    public function rowCount(): int { return $this->inner->rowCount(); }
}
