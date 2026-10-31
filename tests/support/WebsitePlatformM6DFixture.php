<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM6BDatabase.php';
require_once dirname(__DIR__,2) . '/private/classes/SiteDeploymentService.php';

final class M6DStatement extends PDOStatement
{
    private array $rows=[]; private int $affected=0;
    public function __construct(private M6DDatabase $db,private string $sql) {}
    public function execute(?array $params=null): bool { [$this->rows,$this->affected]=$this->db->sql($this->sql,$params ?? []); return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { $r=$this->rows; $this->rows=[]; return $r; }
    public function fetchColumn(int $column=0): mixed { $r=array_shift($this->rows); return $r === null ? false : array_values($r)[$column]; }
    public function rowCount(): int { return $this->affected; }
}

/** Sequential behavioral PDO model. Native constraints, row locks and concurrency are NOT modeled. */
final class M6DDatabase extends PDO
{
    public WebsitePlatformM6BDatabase $inner;
    public M6DDependencies $runtime;
    public array $tables=['site_deployments'=>[],'site_deployment_attempts'=>[],'site_deployment_targets'=>[],
        'site_deployment_health_checks'=>[],'site_deployment_approvals'=>[]];
    public array $queries=[];
    public $hook=null;
    public $afterRollback=null;
    public bool $unavailable=false;
    public ?string $failInsert=null;
    public bool $loseCommit=false;
    private array $backup=[];
    private int $last=1000;
    public function __construct()
    {
        $this->inner=WebsitePlatformM6BDatabase::fixture();
        $request=$this->inner->request(); $claim=$this->inner->claim();
        SiteBuildService::completeBuildSuccess($claim['lease'],$this->inner->receipt($claim,'sealed'));
        $release=array_values($this->inner->tables['site_releases'])[0];
        $this->tables['site_deployment_targets'][20]=['id'=>20,'site_id'=>10,'environment'=>'staging','publisher_key'=>'test-only',
            'binding_key'=>'fixture-staging','binding_version'=>1,'enabled'=>1,'current_deployment_id'=>null,'active_deployment_id'=>null,
            'pointer_version'=>0,'fence_epoch'=>0,'reconciliation_status'=>'clean','last_observed_at'=>null,'updated_at'=>$this->inner->now];
        $this->runtime=new M6DDependencies($this); $this->runtime->artifacts[(int)$release['id']]=$release;
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$this);
        (new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->setValue(null,$this->runtime);
    }
    public function releaseId(): int { return (int)array_key_first($this->inner->tables['site_releases']); }
    public function input(array $override=[]): array
    {
        return array_replace(['site_id'=>10,'target_id'=>20,'release_id'=>$this->releaseId(),'request_key'=>SiteServiceSupport::uuidV4(),
            'expected_current_deployment_id'=>$this->tables['site_deployment_targets'][20]['current_deployment_id'],
            'expected_pointer_version'=>$this->tables['site_deployment_targets'][20]['pointer_version'],
            'binding_version'=>1,'reason_code'=>'publish_approved_release','restored_from_deployment_id'=>null,'deployment_approval_id'=>null],$override);
    }
    public function request(array $input=[]): array { return SiteDeploymentService::requestDeployment(1,$input === [] ? $this->input() : $input); }
    public function claim(): array { return SiteDeploymentService::claimDeployment([]) ?? throw new RuntimeException('Expected deployment claim'); }
    public function publish(array $input=[]): array
    {
        $this->request($input); $claim=$this->claim(); $receipt=SiteDeploymentService::executeDeployment($claim['lease']);
        return $receipt['completed'] ?? SiteDeploymentService::completeDeploymentSuccess($claim['lease'],$receipt);
    }
    public function advance(int $seconds): void { $this->inner->advance($seconds); }
    public function events(string $type): int { return $this->inner->events($type); }
    public function snapshot(): string { return serialize([$this->tables,$this->inner->snapshot()]); }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        preg_match_all('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/',$query,$m);
        if (count($m[1]) !== count(array_unique($m[1]))) throw new RuntimeException('Repeated native placeholder');
        return new M6DStatement($this,$query);
    }
    public function beginTransaction(): bool
    {
        if ($this->unavailable) throw new PDOException('PRIVATE-DATABASE-UNAVAILABLE');
        $this->backup=$this->tables; return $this->inner->beginTransaction();
    }
    public function commit(): bool
    {
        $r=$this->inner->commit(); $this->backup=[];
        if ($this->loseCommit) { $this->loseCommit=false; throw new PDOException('PRIVATE-COMMIT-ACK'); }
        return $r;
    }
    public function rollBack(): bool
    {
        $this->tables=$this->backup; $this->backup=[]; $r=$this->inner->rollBack();
        if ($this->afterRollback !== null) { $f=$this->afterRollback; $this->afterRollback=null; $f($this); }
        return $r;
    }
    public function inTransaction(): bool { return $this->inner->inTransaction(); }
    public function lastInsertId(?string $name=null): string|false { return (string)$this->last; }
    public function sql(string $sql,array $p): array
    {
        $this->queries[]=$sql;
        if ($this->unavailable) throw new PDOException('PRIVATE-DATABASE-UNAVAILABLE');
        if ($this->hook !== null) ($this->hook)($sql,$p,$this);
        $n=preg_replace('/\s+/',' ',trim(preg_replace('~/\*.*?\*/~s','',$sql)));
        if ($this->failInsert !== null && str_starts_with($n,'INSERT INTO ' . $this->failInsert)) throw new PDOException('PRIVATE-INSERT-FAULT');
        if (preg_match('/^INSERT INTO (site_deployment\w+) /',$n,$m)) {
            $this->last++; $this->tables[$m[1]][$this->last]=$p+['id'=>$this->last]; return [[],1];
        }
        if (str_contains($sql,'site-m6d:create-production-approval')) {
            $this->last++; $this->inner->read->base->approvals[$this->last]=$p+['id'=>$this->last,'revoked_at'=>null]; return [[],1];
        }
        if (str_contains($sql,'site-m6d:current-production-approval')) return [array_values(array_filter($this->inner->read->base->approvals,
            static fn($r)=>$r['site_id']===$p['site_id'] && $r['revision_id']===$p['revision_id'] && $r['approval_type']==='production'
                && $r['state']==='approved' && $r['revoked_at']===null)),0];
        if (str_contains($sql,'site-m6d:supersede-grant') || str_contains($sql,'site-m6d:revoke-grant')) {
            $r=&$this->inner->read->base->approvals[$p['approval_id']];
            if ($r['site_id']!==$p['site_id']) return [[],0];
            $r['state']=$p['state']; if (isset($p['now'])) $r['revoked_at']=$p['now']; return [[],1];
        }
        if (str_contains($sql,'site-m6d:publish-site')) {
            $r=&$this->inner->read->base->sites[$p['site_id']];
            if ($r['lock_version']!==$p['lock_version']) return [[],0];
            $r['lifecycle_status']=$p['state']; $r['current_published_revision_id']=$p['revision_id']; $r['lock_version']++; return [[],1];
        }
        if (str_contains($sql,'site-m6d:publish-revision') || str_contains($sql,'site-m6d:supersede-publication')) {
            $r=&$this->inner->read->base->revisions[$p['revision_id']];
            if ($r['site_id']!==$p['site_id'] || $r['lifecycle_status']!==$p['prior_state']) return [[],0];
            $r['lifecycle_status']=$p['state']; if(isset($p['published_at'])) $r['published_at']??=$p['published_at']; return [[],1];
        }
        if (preg_match('/^UPDATE (site_deployment\w+) SET /',$n,$m)) {
            $table=$m[1]; $id=$p['owned_id'] ?? $p['id']; $site=$p['owned_site_id'] ?? $p['site_id'];
            $r=$this->tables[$table][$id] ?? null;
            if ($r === null || $r['site_id']!==$site || (isset($p['old_fence']) && ($r['fence_epoch']!==$p['old_fence'] || $r['pointer_version']!==$p['old_version']))) return [[],0];
            if ($table==='site_deployment_approvals') $values=['consumed_by_deployment_id'=>$p['deployment_id'],'consumed_at'=>$p['now']];
            else $values=array_diff_key($p,array_flip(['owned_id','owned_site_id','old_fence','old_version']));
            $this->tables[$table][$id]=array_replace($r,$values); return [[],1];
        }
        if (str_contains($n,'FROM site_deployments d INNER JOIN')) {
            return [array_values(array_filter($this->tables['site_deployments'],fn($r)=>in_array($r['status'],['requested','retry_wait'],true)
                && ($r['next_attempt_at']===null || $r['next_attempt_at']<=$this->inner->now)
                && $this->tables['site_deployment_targets'][$r['target_id']]['environment']===$p['environment'])),0];
        }
        if (preg_match('/FROM (site_deployment\w+) WHERE /',$n,$m)) {
            $rows=array_values($this->tables[$m[1]]);
            foreach (['id','site_id','target_id','deployment_id','environment','request_key'] as $k) if (array_key_exists($k,$p)) {
                $column=$k==='request_key' && $m[1]==='site_deployment_attempts' ? 'operator_request_key' : $k;
                $rows=array_values(array_filter($rows,static fn($r)=>($r[$column] ?? null)===$p[$k]));
            }
            if (isset($p['before_id'])) $rows=array_values(array_filter($rows,static fn($r)=>$r['id']<$p['before_id']));
            if (str_contains($n,"phase = 'active'")) $rows=array_values(array_filter($rows,static fn($r)=>$r['phase']==='active' && $r['result']==='pass'));
            if (str_contains($n,'ORDER BY id DESC')) usort($rows,static fn($a,$b)=>$b['id']<=>$a['id']);
            if (preg_match('/LIMIT (\d+)/',$n,$limit)) $rows=array_slice($rows,0,(int)$limit[1]);
            return [$rows,0];
        }
        return $this->inner->executeSql($sql,$p);
    }
}

/** Test-only authority seam, installed with reflection; no config/class name selects it. */
final class M6DDependencies implements SiteDeploymentDependencies
{
    public M6DPublisher $publisher;
    public bool $trusted=true;
    public bool $ready=true;
    public bool $production=false;
    public string $environment='staging';
    public int $operator=2;
    public array $artifacts=[];
    public int $artifactCalls=0;
    public function __construct(public M6DDatabase $db) { $this->publisher=new M6DPublisher($this); }
    public function workerIdentity(string $capability): array
    {
        if (!$this->trusted || !in_array($capability,['execution','recovery'],true)) throw new SiteServiceException('unauthorized','Untrusted synthetic worker.');
        return ['worker_id'=>'m6d-fixture','environment'=>$this->environment];
    }
    public function operatorId(): int { return $this->operator; }
    public function assertReady(array $target,array $release): void
    {
        if (!$this->ready || ($target['environment']==='production' && (!$this->production || $release['build_profile']!=='fixture-production-v1'))) {
            throw new SiteServiceException('future_gate_required','Synthetic environment is disabled.');
        }
    }
    public function publisher(array $target): SitePublisher { return $this->publisher; }
    public function now(): string { return $this->db->inner->now; }
    public function waitForNextProbe(): void { SiteDeploymentService::guardExternal(); $this->db->advance(1); }
    public function publisherAuthority(array $context): array
    {
        return [$this->db->tables['site_deployment_attempts'][$context['attempt_id']],
            $this->db->tables['site_deployment_targets'][$context['target_id']]];
    }
    public function verifyArtifact(array $release): array
    {
        SiteDeploymentService::guardExternal(); $this->artifactCalls++;
        if (($this->artifacts[(int)$release['id']] ?? null) !== $release) throw new SiteServiceException('conflict','Synthetic artifact differs.');
        return ['release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],'artifact_hash'=>$release['artifact_hash'],
            'manifest_hash'=>$release['manifest_hash'],'result'=>'pass'];
    }
}

/** External pointer and fence are independent of the database pointer. No filesystem switch/HTTP. */
final class M6DPublisher implements SitePublisher
{
    public array $pointers=[];
    public array $fences=[];
    public array $calls=[];
    public array $healthOverrides=[];
    public bool $unknown=false;
    public bool $compensationFails=false;
    public $hook=null;
    public function __construct(private object $runtime) {}
    private function call(string $method, ?array $context=null): void
    {
        SiteDeploymentService::guardExternal(); $this->calls[]=$method;
        if ($context!==null) {
            SiteDeploymentContract::lease(['site_id'=>$context['site_id'],'target_id'=>$context['target_id'],'deployment_id'=>$context['deployment_id'],
                'attempt_id'=>$context['attempt_id'],'attempt_kind'=>$context['attempt_kind'],'worker_id'=>$context['worker_id'],
                'environment'=>$context['environment'],'token'=>$context['lease_token'],'fence_epoch'=>$context['fence_epoch']]);
            [$a,$t]=$this->runtime->publisherAuthority($context);
            if (!hash_equals($a['lease_token_hash'],hash('sha256',$context['lease_token']))
                || !in_array($a['status'],['leased','running'],true) || $a['attempt_kind']!==$context['attempt_kind']
                || $a['worker_id']!==$context['worker_id'] || (int)$a['deployment_id']!==$context['deployment_id']
                || $a['lease_expires_at'] <= $this->runtime->now() || $a['deadline_at'] <= $this->runtime->now()
                || (int)$t['fence_epoch']!==$context['fence_epoch'] || (int)$t['active_deployment_id']!==$context['deployment_id']
                || $context['fence_epoch']<($this->fences[$context['target_id']] ?? 0)) throw new SiteServiceException('conflict','Stale modeled external fence.');
            $this->fences[$context['target_id']]=$context['fence_epoch'];
        }
        if ($this->hook!==null) ($this->hook)($method,$context,$this);
    }
    private function receipt(array $c,string $phase,?string $previous,string $result='pass'): array
    {
        return ['phase'=>$phase,'result'=>$result,'deployment_id'=>$c['deployment_id'],'attempt_id'=>$c['attempt_id'],
            'fence_epoch'=>$c['fence_epoch'],'binding_version'=>$c['binding_version'],'release_key'=>$c['release_key'],
            'artifact_hash'=>$c['artifact_hash'],'previous_release_key'=>$previous,'external_reference'=>'fixture:'.$c['deployment_id']];
    }
    public function stageRelease(array $release,array $target,array $context): array
    {
        if($context['attempt_kind']!=='execution') throw new LogicException('Recovery staged candidate');
        $this->call('stage',$context); return $this->receipt($context,'stage',null);
    }
    public function activateRelease(array $staged,array $expectedCurrent,array $context): array
    {
        if($context['attempt_kind']!=='execution') throw new LogicException('Recovery activated candidate');
        $this->call('activate',$context); $old=$this->pointers[$context['target_id']] ?? null;
        if (($old['release_key'] ?? null)!==$expectedCurrent['release_key']
            || ($old['artifact_hash'] ?? null)!==$expectedCurrent['artifact_hash']
            || ($old['deployment_id'] ?? null)!==$expectedCurrent['deployment_id']) return $this->receipt($context,'activation',$old['release_key'] ?? null,'conflict');
        $this->pointers[$context['target_id']]=['release_key'=>$context['release_key'],'artifact_hash'=>$context['artifact_hash'],
            'deployment_id'=>$context['deployment_id'],'producer_attempt_id'=>$context['producer_attempt_id']];
        return $this->receipt($context,'activation',$old['release_key'] ?? null,$this->unknown ? 'unknown' : 'pass');
    }
    public function restoreRelease(array $stagedKnownGood,array $expectedCurrent,array $context): array
    { $this->calls[]='restore'; return $this->activateRelease($stagedKnownGood,$expectedCurrent,$context); }
    public function inspectCurrentRelease(array $target): array
    {
        $this->call('inspect'); $r=$this->pointers[$target['target_id']] ?? null;
        return ['state'=>$this->unknown ? 'unknown' : ($r===null ? 'absent' : 'present'),'site_id'=>$target['site_id'],
            'target_id'=>$target['target_id'],'binding_version'=>$target['binding_version'],'release_key'=>$r['release_key'] ?? null,
            'artifact_hash'=>$r['artifact_hash'] ?? null,'deployment_id'=>$r['deployment_id'] ?? null,
            'producer_attempt_id'=>$r['producer_attempt_id'] ?? null,'fence_epoch'=>$this->fences[$target['target_id']] ?? 0,
            'integrity'=>$this->unknown ? 'unknown' : 'pass','external_reference'=>'fixture:'.$target['target_id']];
    }
    public function healthCheck(array $expected,array $target,array $context): array
    {
        $this->call('health',$context); $absent=$expected['release_key']===null;
        $actual=$this->pointers[$target['target_id']] ?? null;
        $matches=$context['phase']==='candidate' || (($actual['release_key'] ?? null)===$expected['release_key']
            && ($actual['artifact_hash'] ?? null)===$expected['artifact_hash'] && ($actual['deployment_id'] ?? null)===$expected['deployment_id']);
        return array_replace(['check_key'=>SiteServiceSupport::uuidV4(),'phase'=>$context['phase'],
            'probe_profile'=>$absent ? 'unavailable-v1' : 'release-v1','site_key'=>$expected['site_key'],
            'release_key'=>$expected['release_key'],'artifact_hash'=>$expected['artifact_hash'],'binding_key'=>$target['binding_key'],
            'binding_version'=>$target['binding_version'],'attempt_id'=>$context['attempt_id'],'fence_epoch'=>$context['fence_epoch'],
            'expected_absent'=>$absent,'http_status'=>$absent ? 503 : 200,'duration_ms'=>2,'checked_at'=>$this->runtime->now(),
            'result'=>$matches ? 'pass' : 'fail','marker_digest'=>!$absent,'home_marker'=>!$absent,'page_sanity'=>true,'css_integrity'=>true,
            'media_integrity'=>true,'origin_integrity'=>true,'tls_verified'=>true,'redirected'=>false,'cache_bypassed'=>true,
            'no_store'=>true,'no_current_link'=>$absent],$this->healthOverrides[$context['phase']] ?? []);
    }
    public function reconcileExternalState(array $intent,array $target,array $context): array
    {
        if($context['attempt_kind']!=='recovery') throw new LogicException('Compensation requires recovery authority');
        $this->call('compensate',$context); $r=$this->pointers[$target['target_id']] ?? null;
        if($this->compensationFails || $r===null || $r['release_key']!==$intent['observed']['release_key']
            || $r['deployment_id']!==$context['deployment_id'] || $r['producer_attempt_id']!==$intent['original_execution_id']) {
            return $this->receipt($context,'compensation',null,'unknown');
        }
        $old=$intent['expected_previous'];
        $this->pointers[$target['target_id']]=$old['release_key']===null ? null : ['release_key'=>$old['release_key'],
            'artifact_hash'=>$old['artifact_hash'],'deployment_id'=>$old['deployment_id'],'producer_attempt_id'=>1];
        return $this->receipt($context,'compensation',$old['release_key']);
    }
}

function m6dAssert(bool $ok,string $message): void
{
    $GLOBALS['m6d_assertions']=($GLOBALS['m6d_assertions'] ?? 0)+1;
    if(!$ok) throw new RuntimeException($message);
}
function m6dDeny(callable $call,?string $classification=null): void
{
    try { $call(); } catch(SiteServiceException $e) {
        m6dAssert($classification===null || $e->classification()===$classification,'Unexpected classification '.$e->classification()); return;
    }
    throw new RuntimeException('Expected denial');
}
