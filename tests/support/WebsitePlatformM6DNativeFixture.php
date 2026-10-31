<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeRuntime.php';
require_once __DIR__.'/WebsitePlatformM6DFixture.php';
require_once __DIR__.'/WebsitePlatformM6BMySqlSupport.php';

/** Actual MySQL connection; instrumentation changes only explicit fault/barrier behavior. */
final class M6DNativeConnection extends PDO
{
    public ?string $faultEvent=null;
    public bool $loseSuccessAck=false;
    public ?string $holdEvent=null;
    public bool $success=false;
    public bool $hold=false;
    public bool $unavailable=false;
    public function __construct(public PDO $pdo) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    { if($this->unavailable) throw new PDOException('native_connection_unavailable'); return new M6DNativeStatement($this,$this->pdo->prepare($query,$options),$query); }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false { return $this->pdo->query($query); }
    public function exec(string $statement): int|false { return $this->pdo->exec($statement); }
    public function beginTransaction(): bool { if($this->unavailable) throw new PDOException('native_connection_unavailable'); return $this->pdo->beginTransaction(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    public function rollBack(): bool { $this->success=false; $this->hold=false; return $this->pdo->rollBack(); }
    public function lastInsertId(?string $name=null): string|false { return $this->pdo->lastInsertId($name); }
    public function commit(): bool
    {
        if($this->hold) { echo "LOCKED\n"; flush(); M6DNativeContract::need(trim((string)fgets(STDIN))==='RELEASE','barrier_release'); $this->hold=false; }
        $r=$this->pdo->commit();
        if($this->loseSuccessAck && $this->success) { $this->success=false; $this->loseSuccessAck=false; throw new PDOException('injected_post_commit_ack'); }
        $this->success=false; return $r;
    }
}
final class M6DNativeStatement extends PDOStatement
{
    public function __construct(private M6DNativeConnection $owner,private PDOStatement $inner,private string $sql) {}
    public function execute(?array $params=null): bool
    {
        if(str_starts_with($this->sql,'INSERT INTO site_events')) {
            $event=$params['event_type'] ?? null;
            if($event===$this->owner->faultEvent) throw new PDOException('injected_audit_failure');
            if($event===$this->owner->holdEvent) $this->owner->hold=true;
            if($event==='site_deployment_succeeded') $this->owner->success=true;
        }
        return $this->inner->execute($params);
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->inner->fetch($mode,$cursorOrientation,$cursorOffset); }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->inner->fetchAll($mode,...$args); }
    public function fetchColumn(int $column=0): mixed { return $this->inner->fetchColumn($column); }
    public function rowCount(): int { return $this->inner->rowCount(); }
}
final class M6DNativeDependencies implements SiteDeploymentDependencies
{
    public M6DNativePublisher $model;
    public bool $production=false;
    public string $environment='staging';
    public bool $ready=true;
    public function __construct(public PDO $db,public array $fixture) { $this->model=new M6DNativePublisher($this); }
    public function workerIdentity(string $capability): array
    {
        M6DNativeContract::need(posix_geteuid()===2000 && in_array($capability,['execution','recovery'],true),'native_worker');
        return ['worker_id'=>'m6d-native-fixture','environment'=>$this->environment];
    }
    public function operatorId(): int { return $this->fixture['operator']; }
    public function assertReady(array $target,array $release): void
    {
        if(!$this->ready || ($target['environment']==='production' && (!$this->production || $release['build_profile']!=='fixture-production-v1'))) {
            throw new SiteServiceException('future_gate_required','DB-only fixture environment is disabled.');
        }
    }
    public function publisher(array $target): SitePublisher { return $this->model; }
    public function verifyArtifact(array $release): array
    {
        SiteDeploymentService::guardExternal();
        // Explicit artifact model: immutable DB identity, never a claim of actual release-byte validation.
        $row=SiteDeploymentStore::row($this->db,'site_releases',(int)$release['site_id'],(int)$release['id']);
        M6DNativeContract::need($row===$release,'native_release_identity');
        return ['release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],'artifact_hash'=>$release['artifact_hash'],
            'manifest_hash'=>$release['manifest_hash'],'result'=>'pass'];
    }
    public function now(): string { return SiteBuildStore::now($this->db); }
    public function waitForNextProbe(): void { SiteDeploymentService::guardExternal(); usleep(1000000); }
    public function publisherAuthority(array $c): array
    {
        return [SiteDeploymentStore::row($this->db,'site_deployment_attempts',$c['site_id'],$c['attempt_id']),
            SiteDeploymentStore::row($this->db,'site_deployment_targets',$c['site_id'],$c['target_id'])];
    }
    public function wire(): void
    {
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$this->db);
        (new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->setValue(null,$this);
    }
}
/** Private JSON model shared by independent workers; never a site/current symlink. */
final class M6DNativePublisher implements SitePublisher
{
    public array $healthOverrides=[];
    public bool $unknown=false;
    public bool $compensationFails=false;
    public function __construct(private M6DNativeDependencies $runtime) {}
    private function invoke(string $method,array $arguments): array
    {
        SiteDeploymentService::guardExternal();
        $path=M6DNativeRuntime::ROOT.'/state/publisher-'.$this->runtime->fixture['site_id'].'.json';
        $old=umask(0077); $f=fopen($path,'c+b'); umask($old); M6DNativeContract::need(is_resource($f),'publisher_file');
        try {
            $end=microtime(true)+5;
            while(!flock($f,LOCK_EX|LOCK_NB)) { M6DNativeContract::need(microtime(true)<$end,'publisher_lock_timeout'); usleep(10000); }
            $s=fstat($f); M6DNativeContract::need($s['size']<=65536 && $s['uid']===2000 && ($s['mode']&0777)===0600 && $s['nlink']===1,'publisher_file_bounds');
            $bytes=stream_get_contents($f,65537); $state=$bytes==='' ? ['pointers'=>[],'fences'=>[],'calls'=>[]] : json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
            $model=new M6DPublisher($this->runtime); $model->pointers=$state['pointers']; $model->fences=$state['fences']; $model->calls=$state['calls'];
            $model->healthOverrides=$this->healthOverrides; $model->unknown=$this->unknown; $model->compensationFails=$this->compensationFails;
            $result=$model->$method(...$arguments);
            $out=json_encode(['pointers'=>$model->pointers,'fences'=>$model->fences,'calls'=>$model->calls],JSON_THROW_ON_ERROR);
            M6DNativeContract::need(strlen($out)<=65536 && ftruncate($f,0) && rewind($f) && fwrite($f,$out)===strlen($out) && fflush($f),'publisher_write');
            return $result;
        } finally { flock($f,LOCK_UN); fclose($f); }
    }
    public function stageRelease(array $release,array $target,array $context): array { return $this->invoke(__FUNCTION__,func_get_args()); }
    public function activateRelease(array $staged,array $expectedCurrent,array $context): array { return $this->invoke(__FUNCTION__,func_get_args()); }
    public function healthCheck(array $expected,array $target,array $context): array { return $this->invoke(__FUNCTION__,func_get_args()); }
    public function inspectCurrentRelease(array $target): array { return $this->invoke(__FUNCTION__,func_get_args()); }
    public function restoreRelease(array $stagedKnownGood,array $expectedCurrent,array $context): array { return $this->invoke(__FUNCTION__,func_get_args()); }
    public function reconcileExternalState(array $intent,array $target,array $context): array { return $this->invoke(__FUNCTION__,func_get_args()); }
}
function m6dNativeFixture(PDO $db): array
{
    $f=m6mysqlFixture($db); $request=m6mysqlRequest($f); $claim=SiteBuildService::claimBuild([]);
    $runtime=(new ReflectionProperty(SiteBuildService::class,'dependencies'))->getValue();
    $job=m6mysqlJob($db,$request['id']);
    SiteBuildService::completeBuildSuccess($claim['lease'],$runtime->receipt($job,$claim['lease']['attempt_id'],'sealed'));
    $release=SiteBuildStore::one($db,'SELECT id FROM site_releases WHERE build_job_id = :id',['id'=>$job['id']]);
    $target=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'staging','publisher_key'=>'m6d-native-model',
        'binding_key'=>'m6d-'.$f['site_id'],'binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
    return $f+['release_id'=>(int)$release['id'],'target_id'=>$target];
}
function m6dNativeInput(PDO $db,array $f,array $override=[]): array
{
    $t=SiteDeploymentStore::row($db,'site_deployment_targets',$f['site_id'],$f['target_id']);
    return array_replace(['site_id'=>$f['site_id'],'target_id'=>$f['target_id'],'release_id'=>$f['release_id'],
        'request_key'=>SiteServiceSupport::uuidV4(),'expected_current_deployment_id'=>$t['current_deployment_id']===null ? null : (int)$t['current_deployment_id'],
        'expected_pointer_version'=>(int)$t['pointer_version'],'binding_version'=>1,'reason_code'=>'publish_approved_release',
        'restored_from_deployment_id'=>null,'deployment_approval_id'=>null],$override);
}
function m6dNativePublish(PDO $db,array $f): array
{
    SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
    $l=SiteDeploymentService::claimDeployment([])['lease']; $r=SiteDeploymentService::executeDeployment($l);
    return $r['completed'] ?? SiteDeploymentService::completeDeploymentSuccess($l,$r);
}
function m6dNativeExpire(PDO $db,array $lease): void
{
    $db->prepare('UPDATE site_deployment_attempts SET leased_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 121 SECOND),
        lease_expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND deployment_id=? AND site_id=?')
        ->execute([$lease['attempt_id'],$lease['deployment_id'],$lease['site_id']]);
}
function m6dNativeRecover(PDO $db,array $op): array
{
    SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
    $db->prepare('UPDATE site_deployments SET next_recovery_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')
        ->execute([$op['deployment_id'],$op['site_id']]);
    return SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
}
