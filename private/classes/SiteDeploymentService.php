<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteApprovalManager.php';
require_once __DIR__ . '/SiteDeploymentContract.php';
require_once __DIR__ . '/SiteDeploymentStore.php';
require_once __DIR__ . '/SiteDeploymentRuntime.php';

/** Provider-neutral durable authority. No route, publisher path or deployment enablement. */
final class SiteDeploymentService
{
    private static ?SiteDeploymentDependencies $dependencies = null;
    private function __construct(private object $resultConnection, private array $resultEvidence) {}
    public function __serialize(): array { throw new LogicException('Transaction evidence cannot be serialized.'); }
    public function __unserialize(array $data): void { throw new LogicException('Transaction evidence cannot be deserialized.'); }
    /** @internal Consumed synchronously by lifecycle owners, never exported as a DTO. */
    public function verifiedResult(object $db): array
    {
        if ($db !== $this->resultConnection || !$db->inTransaction()) throw new SiteServiceException('conflict','Verified result transaction is required.');
        return $this->resultEvidence;
    }
    private static function dependencies(): SiteDeploymentDependencies { return self::$dependencies ??= new SiteDeploymentRuntime(); }
    public static function guardExternal(): void
    {
        $db=(new ReflectionProperty(Database::class,'connection'))->getValue();
        if ($db !== null && $db->inTransaction()) throw new SiteServiceException('conflict','External deployment work cannot run in a transaction.');
    }
    private static function external(callable $call): mixed { self::guardExternal(); return $call(); }
    private static function worker(string $capability): array
    {
        $w=self::dependencies()->workerIdentity($capability);
        SiteDeploymentContract::keys($w,['worker_id','environment']); SiteBuildContract::key($w['worker_id']);
        if (!in_array($w['environment'],['staging','production'],true)) throw new SiteServiceException('unauthorized','Worker environment is invalid.');
        return $w;
    }
    private static function actor(object $db, int $id, string $environment): array
    {
        $actor=SiteAuthorizationPolicy::actorContext($id,$db);
        if (!$actor[$environment === 'production' ? 'is_super_admin' : 'is_internal_admin']) {
            throw new SiteServiceException('unauthorized','Current scoped deployment authority is required.');
        }
        return $actor;
    }
    public static function requestDeployment(int $actingUserId, array $input): array { return self::request($actingUserId,$input,'publish'); }
    public static function requestRestore(int $actingUserId, array $input): array { return self::request($actingUserId,$input,'restore'); }
    private static function request(int $actorId, array $input, string $kind): array
    {
        $v=SiteDeploymentContract::request($input,$kind); SiteBuildContract::id($actorId);
        // Replay is always the first authorized path, including the transaction after artifact verification.
        $run=static function(bool $insert) use($actorId,$v): array {
            return SiteBuildStore::transaction(static function(object $db) use($actorId,$v,$insert): array {
                SiteManager::lockSite($db,$v['site_id']);
                // Current role locks precede the first consistent identity read.
                $actor=SiteAuthorizationPolicy::actorContext($actorId,$db);
                $target=SiteDeploymentStore::row($db,'site_deployment_targets',$v['site_id'],$v['target_id']);
                if (!$actor[$target['environment'] === 'production' ? 'is_super_admin' : 'is_internal_admin']) throw new SiteServiceException('unauthorized','Target access is denied.');
                $payload=SiteDeploymentContract::payload($v,$target['environment']); $json=SiteBuildContract::json($payload,4096); $hash=hash('sha256',$json);
                $existing=SiteBuildStore::one($db,'SELECT * FROM site_deployments WHERE site_id = :site_id AND target_id = :target_id AND request_key = :request_key',
                    ['site_id'=>$v['site_id'],'target_id'=>$v['target_id'],'request_key'=>$v['request_key']]);
                if ($existing !== null) {
                    if (!hash_equals($hash,$existing['request_payload_hash'])
                        || SiteBuildContract::json(SiteBuildContract::decode($existing['request_payload_json'],4096),4096) !== $json) {
                        throw new SiteServiceException('conflict','The request identity already records different intent.');
                    }
                    return self::dto($db,$existing,$target,true);
                }
                $subject=self::subject($db,$v,true); $release=$subject['release']; $target=$subject['target'];
                if (!$insert) return ['preflight_release'=>$release];
                $now=SiteBuildStore::now($db);
                $op=['site_id'=>$v['site_id'],'deployment_key'=>SiteServiceSupport::uuidV4(),'target_id'=>$v['target_id'],
                    'release_id'=>$v['release_id'],'source_revision_id'=>(int)$release['source_revision_id'],'operation'=>$v['operation'],
                    'request_key'=>$v['request_key'],'request_payload_hash'=>$hash,'request_payload_json'=>$json,'reason_code'=>$v['reason_code'],
                    'expected_current_deployment_id'=>$v['expected_current_deployment_id'],'expected_pointer_version'=>$v['expected_pointer_version'],
                    'restored_from_deployment_id'=>$v['restored_from_deployment_id'],'deployment_approval_id'=>$v['deployment_approval_id'],
                    'requested_by_user_id'=>$actorId,'actor_type'=>$actor['actor_type'],'correlation_id'=>$v['correlation_id'],
                    'binding_version'=>$v['binding_version'],'status'=>'requested','next_attempt_at'=>null,'current_attempt_id'=>null,
                    'attempt_count'=>0,'execution_count'=>0,'recovery_count'=>0,'max_execution_attempts'=>3,'max_automatic_recoveries'=>2,
                    'automatic_recovery_count'=>0,'recovery_status'=>'none','next_recovery_at'=>null,
                    'worker_policy_version'=>SiteDeploymentContract::POLICY_VERSION,'worker_policy_json'=>CanonicalJson::encode(SiteDeploymentContract::POLICY),
                    'previous_deployment_id'=>null,'activation_fence'=>null,'external_reference'=>null,
                    'failure_category'=>null,'failure_code'=>null,'safe_summary'=>null,'started_at'=>null,'staged_at'=>null,
                    'activation_attempted_at'=>null,'health_verified_at'=>null,'completed_at'=>null,'created_at'=>$now,'updated_at'=>$now];
                $op['id']=SiteDeploymentStore::insert($db,'site_deployments',$op);
                self::event($db,$op,'site_deployment_requested',$actor);
                if ($v['operation'] === 'restore') self::event($db,$op,'site_restore_requested',$actor);
                return self::dto($db,$op,$target,false);
            },true);
        };
        $first=$run(false);
        if (!isset($first['preflight_release'])) return $first;
        self::verifyArtifact($first['preflight_release']);
        return $run(true);
    }
    private static function verifyArtifact(array $release): void
    {
        $e=self::external(static fn(): array=>self::dependencies()->verifyArtifact($release));
        SiteDeploymentContract::artifact($e,$release);
    }
    /** @internal Approval owner invokes the same release/restore gates without requiring the grant being created. */
    public static function lockGrantSubject(object $db, array $request): array { return self::subject($db,$request,false); }
    private static function subject(object $db, array $op, bool $grant, bool $queued = false): array
    {
        $release=SiteDeploymentStore::row($db,'site_releases',(int)$op['site_id'],(int)$op['release_id']);
        if ($queued) {
            // A changed immutable identity is corruption, not ordinary lifecycle ineligibility.
            $revision=SiteRevisionManager::lockRevision($db,(int)$release['source_revision_id']);
            if ((int)$revision['site_id'] !== (int)$op['site_id'] || $revision['snapshot_hash'] !== $release['source_snapshot_hash']) {
                throw new SiteServiceException('conflict','Queued release source identity differs.');
            }
        }
        try { $source=SiteRevisionManager::lockDeploymentEligibility($db,$release,$op['operation'] === 'restore'); }
        catch (SiteServiceException $error) {
            // Only this owner's lifecycle/approval denial is candidate-local. Integrity/configuration errors escape.
            if (!$queued || $error->classification() !== 'invalid_transition') throw $error;
            return ['queue_denial'=>'source_not_eligible'];
        }
        // Resolve grant/approval and its current actor before the target/operation write locks.
        $probe=SiteDeploymentStore::row($db,'site_deployment_targets',(int)$op['site_id'],(int)$op['target_id']);
        $v=$op+['source_revision_id'=>(int)$release['source_revision_id']];
        self::dependencies()->assertReady($probe,$release);
        if ($probe['environment'] === 'production') {
            if ($grant) {
                if ($queued) {
                    $record=SiteBuildStore::one($db,'SELECT * FROM site_deployment_approvals WHERE id = :id AND site_id = :site_id FOR UPDATE',
                        ['id'=>$op['deployment_approval_id'],'site_id'=>(int)$op['site_id']]);
                    if ($record === null || $record['artifact_hash'] !== $release['artifact_hash']) throw new SiteServiceException('conflict','Queued grant identity is inconsistent.');
                    foreach (['site_id','release_id','source_revision_id','target_id','operation','expected_current_deployment_id','expected_pointer_version','binding_version'] as $key) {
                        if ($record[$key] != $v[$key]) throw new SiteServiceException('conflict','Queued grant intent is inconsistent.');
                    }
                    if ($record['consumed_by_deployment_id'] !== null && (int)$record['consumed_by_deployment_id'] !== (int)$op['id']) {
                        return ['queue_denial'=>'grant_not_valid']; // This exact one-use grant already belongs to another intent.
                    }
                }
                try { SiteApprovalManager::lockProductionGrant($db,$v,$release,$probe,SiteBuildStore::now($db)); }
                catch (SiteServiceException $error) {
                    // Exact immutable intent was checked above; only current grant/approver denial is local.
                    if (!$queued || $error->classification() !== 'unauthorized') throw $error;
                    return ['queue_denial'=>'grant_not_valid'];
                }
            }
        } elseif ($op['deployment_approval_id'] !== null) throw new SiteServiceException('invalid_request','Staging cannot consume a production grant.');
        if ($op['operation'] === 'restore') {
            $old=SiteDeploymentStore::row($db,'site_deployments',(int)$op['site_id'],(int)$op['restored_from_deployment_id']);
            if ($old['status'] !== 'succeeded' || (int)$old['target_id'] !== (int)$op['target_id']
                || (int)$old['release_id'] !== (int)$release['id'] || $old['health_verified_at'] === null) {
                throw new SiteServiceException('invalid_transition','A successful same-target release is required.');
            }
            $checks=SiteBuildStore::rows($db,"SELECT * FROM site_deployment_health_checks WHERE site_id = :site_id
                AND deployment_id = :deployment_id AND phase = 'active' AND result = 'pass' ORDER BY id",
                ['site_id'=>(int)$op['site_id'],'deployment_id'=>(int)$old['id']]);
            if (count($checks)<2) throw new SiteServiceException('conflict','Known-good health evidence is missing.');
        }
        $target=SiteManager::lockDeploymentTarget($db,(int)$op['site_id'],(int)$op['target_id']);
        if ($queued) {
            if ((int)$target['binding_version'] !== (int)$op['binding_version']) return ['queue_denial'=>'binding_changed'];
            if ($target['current_deployment_id'] != $op['expected_current_deployment_id']
                || (int)$target['pointer_version'] !== (int)$op['expected_pointer_version']) return ['queue_denial'=>'expected_pointer_changed'];
            if ((int)$target['enabled'] !== 1 || $target['reconciliation_status'] !== 'clean') return ['queue_skip'=>true];
        }
        if ((int)$target['enabled'] !== 1 || (int)$target['binding_version'] !== (int)$op['binding_version']) throw new SiteServiceException('future_gate_required','The exact target binding is not enabled.');
        self::pointer($target,$op);
        if ($target['reconciliation_status'] !== 'clean' && (int)$target['active_deployment_id'] !== (int)($op['id'] ?? 0)) {
            throw new SiteServiceException('conflict','The target requires reconciliation.');
        }
        return ['site'=>$source['site'],'source'=>$source,'release'=>$release,'target'=>$target];
    }
    private static function pointer(array $target, array $op): void
    {
        if ($target['current_deployment_id'] != $op['expected_current_deployment_id']
            || (int)$target['pointer_version'] !== (int)$op['expected_pointer_version']) throw new SiteServiceException('stale_write','The target pointer changed.');
    }
    public static function claimDeployment(array $workerContext): ?array
    {
        SiteDeploymentContract::keys($workerContext,[]); $worker=self::worker('execution');
        $candidates=SiteServiceSupport::read(static fn(object $db): array=>SiteBuildStore::rows($db,
            "SELECT d.id, d.site_id FROM site_deployments d INNER JOIN site_deployment_targets t ON t.id = d.target_id AND t.site_id = d.site_id
             WHERE d.status IN ('requested','retry_wait') AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= UTC_TIMESTAMP(6))
             AND t.environment = :environment ORDER BY d.id LIMIT 25",['environment'=>$worker['environment']]));
        foreach ($candidates as $candidate) {
            $claim=SiteBuildStore::transaction(static function(object $db) use($candidate,$worker): ?array {
                SiteManager::lockSite($db,(int)$candidate['site_id']);
                if (self::worker('execution') !== $worker) throw new SiteServiceException('unauthorized','Worker trust changed.');
                $identity=SiteDeploymentStore::row($db,'site_deployments',(int)$candidate['site_id'],(int)$candidate['id']);
                $now=SiteBuildStore::now($db);
                // The scan is only a hint. Root-serialized current state precedes all new-claim gates.
                if (!self::queueDue($identity,$now)) return null;
                $target=SiteDeploymentStore::row($db,'site_deployment_targets',(int)$identity['site_id'],(int)$identity['target_id']);
                if ($worker['environment'] !== $target['environment']) throw new SiteServiceException('unauthorized','Worker target scope differs.');
                SiteDeploymentContract::policy($identity);
                $release=SiteDeploymentStore::row($db,'site_releases',(int)$identity['site_id'],(int)$identity['release_id']);
                // Runtime failure is systemic, including when this candidate's requester is no longer authorized.
                self::dependencies()->assertReady($target,$release);
                $denial=$identity['requested_by_user_id'] === null ? 'requester_not_authorized' : null;
                if ($denial === null) {
                    try { self::actor($db,(int)$identity['requested_by_user_id'],$target['environment']); }
                    catch (SiteServiceException $error) {
                        if ($error->classification() !== 'unauthorized') throw $error;
                        $denial='requester_not_authorized';
                    }
                }
                $subject=$denial === null ? self::subject($db,$identity,true,true) : ['queue_denial'=>$denial];
                // Lifecycle/actor/grant locks above retain their order ahead of target/operation/attempt locks.
                $target=SiteManager::lockDeploymentTarget($db,(int)$identity['site_id'],(int)$identity['target_id']);
                $op=SiteDeploymentStore::row($db,'site_deployments',(int)$identity['site_id'],(int)$identity['id'],true);
                $now=SiteBuildStore::now($db);
                if (!self::queueDue($op,$now)) return null;
                $prior=self::current($db,$op);
                if (self::worker('execution') !== $worker) throw new SiteServiceException('unauthorized','Worker trust changed.');
                self::dependencies()->assertReady($target,$release);
                if ($prior !== null && in_array($prior['status'],['leased','running'],true)
                    && $prior['lease_expires_at'] > $now && $prior['deadline_at'] > $now) return null;
                if (!self::safeQueuedIntent($op,$prior) || (int)$target['active_deployment_id'] === (int)$op['id']) {
                    // Only existing matching ownership can establish recovery; never borrow/free another slot.
                    if ($prior === null || (int)$target['active_deployment_id'] !== (int)$op['id']
                        || (int)$target['fence_epoch'] !== (int)$prior['fence_epoch']) throw new SiteServiceException('conflict','Queued effects require authoritative recovery ownership.');
                    self::requireRecovery($db,$op,$prior,$now,'outcome_unknown',
                        $prior['attempt_kind'] === 'recovery' && $prior['recovery_trigger'] === 'operator');
                    return null;
                }
                if ($target['active_deployment_id'] !== null || $target['reconciliation_status'] !== 'clean' || isset($subject['queue_skip'])) return null;
                if (isset($subject['queue_denial'])) {
                    self::cancelQueuedIntent($db,$op,$subject['queue_denial'],$now);
                    return null; // Return normally so disposition and event COMMIT before scanning onward.
                }
                if ((int)$op['execution_count'] >= (int)$op['max_execution_attempts']) {
                    SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>'failed','next_attempt_at'=>null,
                        'failure_code'=>'execution_exhausted','failure_category'=>SiteDeploymentContract::FAILURES['execution_exhausted'],
                        'safe_summary'=>'No execution budget remains.','completed_at'=>$now,'updated_at'=>$now]);
                    self::event($db,$op,'site_deployment_failed',SiteServiceSupport::systemActor(),['code'=>'execution_exhausted']);
                    return null;
                }
                return self::allocate($db,$op,$target,$worker,$now,null,null);
            });
            if ($claim !== null) return $claim;
        }
        return null;
    }
    private static function queueDue(array $op, string $now): bool
    {
        return in_array($op['status'],['requested','retry_wait'],true) && in_array($op['recovery_status'],['none','resolved'],true)
            && ($op['next_attempt_at'] === null || $op['next_attempt_at'] <= $now);
    }
    private static function safeQueuedIntent(array $op, ?array $prior): bool
    {
        if ($op['activation_attempted_at'] !== null || $op['activation_fence'] !== null || $op['health_verified_at'] !== null) return false;
        if ($prior === null) return $op['status'] === 'requested' && (int)$op['attempt_count'] === 0
            && (int)$op['execution_count'] === 0 && (int)$op['recovery_count'] === 0
            && $op['staged_at'] === null && $op['external_reference'] === null && $op['previous_deployment_id'] === null;
        if ($prior['attempt_kind'] !== 'execution' || $prior['status'] !== 'failed' || $prior['receipt_json'] === null) return false;
        $receipt=SiteBuildContract::decode($prior['receipt_json'],16384);
        return $op['status'] === 'retry_wait' && ($receipt['receipt_key'] ?? null) === $op['deployment_key']
            && ($receipt['disposition'] ?? null) === 'safely_failed' && in_array($receipt['code'] ?? null,['storage_unavailable','network_unavailable'],true);
    }
    private static function cancelQueuedIntent(object $db, array $op, string $reason, string $now): void
    {
        if (!in_array($reason,['requester_not_authorized','source_not_eligible','grant_not_valid','binding_changed','expected_pointer_changed'],true)) {
            throw new LogicException('Unsupported queued deployment disposition.');
        }
        SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>'cancelled','next_attempt_at'=>null,'completed_at'=>$now,'updated_at'=>$now,
            'failure_code'=>$reason,'failure_category'=>in_array($reason,['requester_not_authorized','grant_not_valid'],true) ? 'authorization' : 'eligibility',
            'safe_summary'=>'Queued intent no longer has execution authority; no unresolved effects are recorded.']);
        self::event($db,$op,'site_deployment_cancelled',SiteServiceSupport::systemActor(),
            ['code'=>$reason,'previous_status'=>$op['status'],'next_status'=>'cancelled']);
    }
    private static function current(object $db, array $op): ?array
    {
        return $op['current_attempt_id'] === null ? null : self::attempt($db,$op,(int)$op['current_attempt_id']);
    }
    private static function attempt(object $db, array $op, int $id): array
    {
        $a=SiteDeploymentStore::row($db,'site_deployment_attempts',(int)$op['site_id'],$id,true);
        if ((int)$a['deployment_id'] !== (int)$op['id']) throw new SiteServiceException('conflict','Attempt ownership differs.');
        return $a;
    }
    private static function allocate(object $db, array $op, array $target, array $worker, string $now, ?array $producer, ?array $operator): array
    {
        $kind=$producer === null ? 'execution' : 'recovery';
        if (self::worker($kind) !== $worker) throw new SiteServiceException('unauthorized','Worker trust changed.');
        $target=SiteManager::reserveDeploymentSlot($db,$target,$op,$kind === 'recovery',$now);
        $token=bin2hex(random_bytes(32));
        $a=['site_id'=>(int)$op['site_id'],'deployment_id'=>(int)$op['id'],'attempt_number'=>(int)$op['attempt_count']+1,
            'attempt_kind'=>$kind,'execution_number'=>$kind === 'execution' ? (int)$op['execution_count']+1 : null,
            'recovery_number'=>$kind === 'recovery' ? (int)$op['recovery_count']+1 : null,
            'worker_id'=>$worker['worker_id'],'lease_token_hash'=>hash('sha256',$token),
            'recovery_of_attempt_id'=>$producer === null ? null : (int)$producer['id'],
            'recovery_trigger'=>$producer === null ? null : ($operator === null ? 'automatic' : 'operator'),
            'operator_request_key'=>$operator['request_key'] ?? null,'recovery_authorized_by_user_id'=>$operator['actor']['acting_user_id'] ?? null,
            'recovery_actor_type'=>$producer === null ? null : ($operator['actor']['actor_type'] ?? 'system'),
            'recovery_reason_code'=>$producer === null ? null : ($operator['reason_code'] ?? 'inspect_unknown_outcome'),
            'status'=>'leased','leased_at'=>$now,'deadline_at'=>SiteBuildStore::after($now,300),
            'lease_expires_at'=>SiteBuildStore::after($now,120),'heartbeat_at'=>$now,'started_at'=>$now,'completed_at'=>null,
            'external_reference'=>null,'failure_category'=>null,'failure_code'=>null,'safe_summary'=>null,
            'correlation_id'=>$op['correlation_id'],'fence_epoch'=>(int)$target['fence_epoch'],'receipt_json'=>null,'created_at'=>$now];
        SiteDeploymentContract::claimActor($a,$operator['actor'] ?? null);
        $a['id']=SiteDeploymentStore::insert($db,'site_deployment_attempts',$a);
        $values=['current_attempt_id'=>$a['id'],'attempt_count'=>$a['attempt_number'],'updated_at'=>$now];
        if ($kind === 'execution') $values+=['status'=>'running','execution_count'=>$a['execution_number'],'next_attempt_at'=>null,
            'started_at'=>$op['started_at'] ?? $now];
        else $values+=['recovery_count'=>$a['recovery_number'],'automatic_recovery_count'=>(int)$op['automatic_recovery_count']+($operator === null ? 1 : 0),
            'recovery_status'=>'running','next_recovery_at'=>null];
        $op=SiteDeploymentStore::update($db,'site_deployments',$op,$values);
        if ($operator !== null) self::event($db,$op,'site_recovery_requested',$operator['actor'],['attempt_id'=>$a['id'],'reason_code'=>$operator['reason_code']]);
        self::event($db,$op,$kind === 'execution' ? 'site_deployment_started' : 'site_recovery_started',SiteServiceSupport::systemActor(),
            ['attempt_id'=>$a['id'],'attempt_kind'=>$kind,'original_execution_id'=>$a['recovery_of_attempt_id'],
                'fence_epoch'=>$a['fence_epoch'],'attempt_count'=>$op['attempt_count'],'execution_count'=>$op['execution_count'],
                'recovery_count'=>$op['recovery_count'],'trigger'=>$a['recovery_trigger'],'deadline_at'=>$a['deadline_at'],
                'policy_version'=>$op['worker_policy_version']]);
        return ['claimed'=>true,'lease'=>['site_id'=>(int)$op['site_id'],'target_id'=>(int)$op['target_id'],
            'deployment_id'=>(int)$op['id'],'attempt_id'=>$a['id'],'attempt_kind'=>$kind,'worker_id'=>$worker['worker_id'],
            'environment'=>$worker['environment'],'token'=>$token,'fence_epoch'=>$a['fence_epoch']]];
    }
    private static function locked(object $db, array $lease, array $worker, bool $live = true): array
    {
        $site=SiteManager::lockSite($db,$lease['site_id']);
        $target=SiteManager::lockDeploymentTarget($db,$lease['site_id'],$lease['target_id']);
        $op=SiteDeploymentStore::row($db,'site_deployments',$lease['site_id'],$lease['deployment_id'],true);
        $a=self::attempt($db,$op,$lease['attempt_id']); $now=SiteBuildStore::now($db);
        if ($worker !== self::worker($lease['attempt_kind']) || $worker['environment'] !== $target['environment']
            || $lease['environment'] !== $target['environment'] || (int)$op['target_id'] !== $lease['target_id']
            || $lease['worker_id'] !== $worker['worker_id'] || $a['worker_id'] !== $worker['worker_id']
            || $a['attempt_kind'] !== $lease['attempt_kind'] || (int)$a['fence_epoch'] !== $lease['fence_epoch']
            || !hash_equals($a['lease_token_hash'],hash('sha256',$lease['token']))) throw new SiteServiceException('unauthorized','Deployment lease identity differs.');
        SiteDeploymentContract::policy($op);
        if ($live && ((int)$op['current_attempt_id'] !== $lease['attempt_id'] || !in_array($a['status'],['leased','running'],true)
            || (int)$target['binding_version'] !== (int)$op['binding_version']
            || $a['lease_expires_at'] <= $now || $a['deadline_at'] <= $now
            || (int)$target['active_deployment_id'] !== (int)$op['id'] || (int)$target['fence_epoch'] !== $lease['fence_epoch'])) {
            throw new SiteServiceException('conflict','Deployment ownership expired or was replaced.');
        }
        return ['site'=>$site,'target'=>$target,'operation'=>$op,'attempt'=>$a,'now'=>$now];
    }
    public static function renewDeploymentLease(array $lease): array
    {
        SiteDeploymentContract::lease($lease); $worker=self::worker($lease['attempt_kind']);
        return SiteBuildStore::transaction(static function(object $db) use($lease,$worker): array {
            $e=self::locked($db,$lease,$worker); $a=$e['attempt'];
            $expiry=min($a['deadline_at'],SiteBuildStore::after($e['now'],120));
            SiteDeploymentStore::update($db,'site_deployment_attempts',$a,['lease_expires_at'=>$expiry,'heartbeat_at'=>$e['now']]);
            return ['lease_expires_at'=>$expiry,'deadline_at'=>$a['deadline_at']];
        });
    }

    private static function entry(array $lease, bool $eligible = false, bool $live = true): array
    {
        $worker=self::worker($lease['attempt_kind']);
        return SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$eligible,$live): array {
            SiteManager::lockSite($db,$lease['site_id']);
            $op=SiteDeploymentStore::row($db,'site_deployments',$lease['site_id'],$lease['deployment_id']);
            $subject=[];
            if ($eligible) {
                self::actor($db,(int)($op['requested_by_user_id'] ?? 0),$worker['environment']);
                $subject=self::subject($db,$op,true);
            }
            $e=self::locked($db,$lease,$worker,$live);
            $e['release']=$subject['release'] ?? SiteDeploymentStore::row($db,'site_releases',$lease['site_id'],(int)$op['release_id']);
            $e['producer']=$e['attempt']['attempt_kind'] === 'execution' ? $e['attempt'] : self::attempt($db,$op,(int)$e['attempt']['recovery_of_attempt_id']);
            if ($e['producer']['attempt_kind'] !== 'execution') throw new SiteServiceException('conflict','Original execution evidence is missing.');
            $previous=$op['expected_current_deployment_id'] === null ? null : SiteDeploymentStore::row($db,'site_deployments',$lease['site_id'],(int)$op['expected_current_deployment_id']);
            if ($previous !== null && ($previous['status'] !== 'succeeded' || (int)$previous['target_id'] !== $lease['target_id'])) {
                throw new SiteServiceException('conflict','Previous deployment evidence is inconsistent.');
            }
            $e['previous']=$previous === null ? null : SiteDeploymentStore::row($db,'site_releases',$lease['site_id'],(int)$previous['release_id']);
            $e['previous_operation']=$previous;
            return $e;
        });
    }
    private static function context(array $e, array $lease, string $phase): array
    {
        return ['site_id'=>$lease['site_id'],'target_id'=>$lease['target_id'],'deployment_id'=>$lease['deployment_id'],
            'deployment_key'=>$e['operation']['deployment_key'],'attempt_id'=>$lease['attempt_id'],'attempt_kind'=>$lease['attempt_kind'],
            'producer_attempt_id'=>(int)$e['producer']['id'],'worker_id'=>$lease['worker_id'],'environment'=>$lease['environment'],
            'lease_token'=>$lease['token'],'lease_expires_at'=>$e['attempt']['lease_expires_at'],'deadline_at'=>$e['attempt']['deadline_at'],
            'fence_epoch'=>$lease['fence_epoch'],'binding_version'=>(int)$e['operation']['binding_version'],
            'release_key'=>$e['release']['release_key'],'artifact_hash'=>$e['release']['artifact_hash'],
            'operation'=>$e['operation']['operation'],'phase'=>$phase,'phase_timeout_seconds'=>30,
            'connect_timeout_seconds'=>2,'request_timeout_seconds'=>5,'correlation_id'=>$e['operation']['correlation_id']];
    }
    private static function expected(array $e, bool $previous = false): array
    {
        $r=$previous ? $e['previous'] : $e['release'];
        return ['site_key'=>$e['site']['site_key'],'release_id'=>$r === null ? null : (int)$r['id'],
            'release_key'=>$r['release_key'] ?? null,'artifact_hash'=>$r['artifact_hash'] ?? null,
            'deployment_id'=>$previous ? ($e['previous_operation'] === null ? null : (int)$e['previous_operation']['id']) : (int)$e['operation']['id']];
    }
    private static function publisher(array $e): SitePublisher
    {
        return self::external(static fn(): SitePublisher=>self::dependencies()->publisher(SiteDeploymentContract::target($e['target'])));
    }
    private static function observe(array $e): array
    {
        $p=self::publisher($e); $target=SiteDeploymentContract::target($e['target']);
        $o=SiteDeploymentContract::observation(self::external(static fn(): array=>$p->inspectCurrentRelease($target)));
        if ($o['site_id'] !== $target['site_id'] || $o['target_id'] !== $target['target_id'] || $o['binding_version'] !== $target['binding_version']
            || $o['fence_epoch'] > (int)$e['attempt']['fence_epoch']) throw new SiteServiceException('conflict','External target authority differs.');
        return $o;
    }
    private static function matches(array $o, array $e, bool $previous): bool
    {
        $expected=self::expected($e,$previous);
        return $o['integrity'] === 'pass' && $o['state'] === ($expected['release_key'] === null ? 'absent' : 'present')
            && $o['release_key'] === $expected['release_key'] && $o['artifact_hash'] === $expected['artifact_hash']
            && $o['deployment_id'] === $expected['deployment_id']
            && ($previous || ($o['producer_attempt_id'] === (int)$e['producer']['id']
                && $e['operation']['activation_fence'] !== null && $o['fence_epoch'] >= (int)$e['operation']['activation_fence']));
    }
    private static function probe(array $e, array $lease, string $phase, bool $previous = false): array
    {
        self::renewDeploymentLease($lease);
        $e=self::entry($lease); $p=self::publisher($e); $target=SiteDeploymentContract::target($e['target']);
        $context=self::context($e,$lease,$phase); $expected=self::expected($e,$previous);
        $health=self::external(static fn(): array=>$p->healthCheck($expected,$target,$context));
        $now=SiteServiceSupport::read(static fn(object $db): string=>SiteBuildStore::now($db));
        return SiteDeploymentContract::health($health,$expected,$target,$context,$now);
    }
    private static function activeChecks(array $e,array $lease): array
    {
        $one=self::probe($e,$lease,'active');
        self::external(static fn()=>self::dependencies()->waitForNextProbe());
        $two=self::probe($e,$lease,'active');
        if ($two['check_key']===$one['check_key'] || $two['checked_at']<SiteBuildStore::after($one['checked_at'],1)) {
            throw new SiteServiceException('conflict','Two consecutive separated active observations are required.');
        }
        return [$one,$two];
    }
    private static function saveHealth(object $db, array $e, array $health, bool $previous = false): void
    {
        $op=$e['operation']; $expected=self::expected($e,$previous);
        SiteDeploymentStore::insert($db,'site_deployment_health_checks',['site_id'=>(int)$op['site_id'],
            'deployment_id'=>(int)$op['id'],'deployment_attempt_id'=>(int)$e['attempt']['id'],'check_key'=>$health['check_key'],
            'phase'=>$health['phase'],'probe_profile'=>$health['probe_profile'],'expected_release_id'=>$expected['release_id'],
            'expected_absent'=>$health['expected_absent'] ? 1 : 0,'observed_release_key'=>$health['release_key'],
            'observed_artifact_hash'=>$health['artifact_hash'],'http_status'=>$health['http_status'],'duration_ms'=>$health['duration_ms'],
            'result'=>$health['result'],'failure_code'=>$health['result'] === 'pass' ? null : 'health_failed',
            'summary_json'=>SiteBuildContract::json($health,4096),'checked_at'=>$health['checked_at'],'correlation_id'=>$op['correlation_id'],'created_at'=>$e['now']]);
        self::event($db,$op,'site_deployment_health_checked',SiteServiceSupport::systemActor(),
            ['attempt_id'=>(int)$e['attempt']['id'],'check_key'=>$health['check_key'],'phase'=>$health['phase'],'result'=>$health['result']]);
    }
    public static function executeDeployment(array $lease): array
    {
        SiteDeploymentContract::lease($lease,'execution');
        $old=self::entry($lease,false,false);
        if ($old['operation']['status'] === 'succeeded') return ['completed'=>self::workerDTO($old)];
        $e=self::entry($lease,true);
        if ($e['operation']['status'] !== 'running') throw new SiteServiceException('conflict','Execution phase cannot be replayed as a new activation.');
        self::verifyArtifact($e['release']); self::renewDeploymentLease($lease);
        $p=self::publisher($e); $target=SiteDeploymentContract::target($e['target']); $context=self::context($e,$lease,'stage');
        $r=SiteDeploymentContract::release($e['release'],$e['site']);
        $staged=SiteDeploymentContract::receipt(self::external(static fn(): array=>$p->stageRelease($r,$target,$context)),$context,'stage');
        if ($staged['result'] !== 'pass') return ['completed'=>self::completeDeploymentFailure($lease,['code'=>'outcome_unknown'])];
        $worker=self::worker('execution');
        SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$staged): void {
            $e=self::locked($db,$lease,$worker);
            SiteDeploymentStore::update($db,'site_deployments',$e['operation'],['status'=>'artifact_staged','staged_at'=>$e['now'],
                'external_reference'=>$staged['external_reference'],'updated_at'=>$e['now']]);
            self::event($db,$e['operation'],'site_release_staged',SiteServiceSupport::systemActor(),['attempt_id'=>$lease['attempt_id'],'receipt'=>$staged]);
        });
        $health=self::probe($e,$lease,'candidate');
        SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$health,$e): void {
            $locked=self::locked($db,$lease,$worker); self::saveHealth($db,array_replace($e,$locked),$health);
        });
        if ($health['result'] !== 'pass') return ['completed'=>self::completeDeploymentFailure($lease,['code'=>'health_failed'])];
        // Verify actual prior identity before authorizing activation. The publisher must CAS it again.
        $e=self::entry($lease,true);
        if (!self::matches(self::observe($e),$e,true)) return ['completed'=>self::completeDeploymentFailure($lease,['code'=>'outcome_unknown'])];
        SiteBuildStore::transaction(static function(object $db) use($lease,$worker): void {
            SiteManager::lockSite($db,$lease['site_id']);
            $op=SiteDeploymentStore::row($db,'site_deployments',$lease['site_id'],$lease['deployment_id']);
            self::actor($db,(int)($op['requested_by_user_id'] ?? 0),$worker['environment']);
            $subject=self::subject($db,$op,true); $e=self::locked($db,$lease,$worker);
            if ($e['operation']['status'] !== 'artifact_staged') throw new SiteServiceException('conflict','Activation was already reserved.');
            if ($subject['target']['environment'] === 'production') SiteApprovalManager::lockProductionGrant($db,$op,$subject['release'],$subject['target'],$e['now'],true);
            SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>'activation_attempted',
                'previous_deployment_id'=>$op['expected_current_deployment_id'],'activation_fence'=>$op['activation_fence'] ?? $lease['fence_epoch'],
                'activation_attempted_at'=>$op['activation_attempted_at'] ?? $e['now'],'updated_at'=>$e['now']]);
            self::event($db,$op,'site_activation_attempted',SiteServiceSupport::systemActor(),['attempt_id'=>$lease['attempt_id'],'fence_epoch'=>$lease['fence_epoch']]);
        });
        $e=self::entry($lease); $context=self::context($e,$lease,'activation'); $expected=self::expected($e,true);
        $activated=SiteDeploymentContract::receipt(self::external(static fn(): array=>$e['operation']['operation'] === 'restore'
            ? $p->restoreRelease($staged,$expected,$context) : $p->activateRelease($staged,$expected,$context)),$context,'activation');
        if ($activated['result'] !== 'pass' || $activated['previous_release_key'] !== $expected['release_key']) {
            return ['completed'=>self::completeDeploymentFailure($lease,['code'=>'outcome_unknown'])];
        }
        SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$activated): void {
            $e=self::locked($db,$lease,$worker);
            SiteDeploymentStore::update($db,'site_deployments',$e['operation'],['status'=>'health_check_pending','updated_at'=>$e['now']]);
            self::event($db,$e['operation'],'site_activation_observed',SiteServiceSupport::systemActor(),['attempt_id'=>$lease['attempt_id'],'receipt'=>$activated]);
        });
        return ['receipt_key'=>$e['operation']['deployment_key']];
    }
    private static function hint(array $hint, array $op): void
    {
        SiteDeploymentContract::keys($hint,['receipt_key']);
        if (SiteBuildContract::uuid($hint['receipt_key']) !== $op['deployment_key']) throw new SiteServiceException('conflict','Deployment receipt differs.');
    }
    public static function completeDeploymentSuccess(array $lease, array $receipt): array
    {
        SiteDeploymentContract::lease($lease,'execution');
        return self::success($lease,$receipt);
    }
    private static function success(array $lease, array $receipt): array
    {
        // Resolve committed history before eligibility, expiry or external compensation.
        $e=self::entry($lease,false,false); self::hint($receipt,$e['operation']);
        if ($e['operation']['status'] === 'succeeded') {
            self::committedSuccessReceipt($e);
            return self::workerDTO($e);
        }
        $e=self::entry($lease,true);
        if (!in_array($e['operation']['status'],['activation_attempted','health_check_pending','reconciliation_required'],true)
            || $e['operation']['activation_fence'] === null) throw new SiteServiceException('conflict','No recorded activation can be finalized.');
        self::verifyArtifact($e['release']);
        if (!self::matches(self::observe($e),$e,false)) throw new SiteServiceException('conflict','The exact active candidate is not verified.');
        $checks=self::activeChecks($e,$lease);
        if ($checks[0]['result'] !== 'pass' || $checks[1]['result'] !== 'pass' || $checks[0]['check_key'] === $checks[1]['check_key']) {
            if ($lease['attempt_kind'] === 'recovery') self::compensate($lease,$e,self::observe($e));
            return self::completeDeploymentFailure($lease,['code'=>'health_failed']);
        }
        $worker=self::worker($lease['attempt_kind']);
        return SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$checks,$e): array {
            SiteManager::lockSite($db,$lease['site_id']);
            $op=SiteDeploymentStore::row($db,'site_deployments',$lease['site_id'],$lease['deployment_id']);
            if ($op['status'] === 'succeeded') {
                $committed=self::locked($db,$lease,$worker,false); self::committedSuccessReceipt($committed);
                return self::workerDTO($committed);
            }
            self::actor($db,(int)($op['requested_by_user_id'] ?? 0),$worker['environment']);
            $subject=self::subject($db,$op,true); $locked=self::locked($db,$lease,$worker);
            $e=array_replace($e,$subject,$locked);
            foreach ($checks as $check) {
                SiteDeploymentContract::health($check,self::expected($e),SiteDeploymentContract::target($e['target']),self::context($e,$lease,'active'),$e['now']);
                self::saveHealth($db,$e,$check);
            }
            if ($checks[1]['checked_at'] < $checks[0]['checked_at']) throw new SiteServiceException('conflict','Health observation order differs.');
            if ($e['target']['environment'] === 'production') {
                $grant=SiteApprovalManager::lockProductionGrant($db,$op,$e['release'],$e['target'],$e['now']);
                if ((int)$grant['consumed_by_deployment_id'] !== (int)$op['id']) throw new SiteServiceException('unauthorized','Activation grant was not consumed by this operation.');
            }
            SiteManager::recordVerifiedDeployment($db,new self($db,$e));
            $op=SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>'succeeded','recovery_status'=>$lease['attempt_kind'] === 'recovery' ? 'resolved' : 'none',
                'health_verified_at'=>$e['now'],'completed_at'=>$e['now'],'updated_at'=>$e['now'],'next_attempt_at'=>null,'next_recovery_at'=>null,
                'failure_category'=>null,'failure_code'=>null,'safe_summary'=>null]);
            self::finishAttempt($db,$e['attempt'],'succeeded',$e['now'],['receipt_key'=>$op['deployment_key'],'disposition'=>'succeeded']);
            self::event($db,$op,'site_deployment_succeeded',SiteServiceSupport::systemActor(),['attempt_id'=>$lease['attempt_id'],'fence_epoch'=>$lease['fence_epoch']]);
            if ($op['operation'] === 'restore') self::event($db,$op,'site_restore_succeeded',SiteServiceSupport::systemActor());
            if ($lease['attempt_kind'] === 'recovery') self::event($db,$op,'site_recovery_completed',SiteServiceSupport::systemActor(),['attempt_id'=>$lease['attempt_id'],'disposition'=>'finalized']);
            $target=SiteManager::lockDeploymentTarget($db,$lease['site_id'],$lease['target_id']);
            return self::dto($db,$op,$target,false);
        });
    }
    private static function committedSuccessReceipt(array $e): void
    {
        $receipt=$e['attempt']['receipt_json'] === null ? [] : SiteBuildContract::decode($e['attempt']['receipt_json'],16384);
        if ($e['attempt']['status'] !== 'succeeded' || ($receipt['disposition'] ?? null) !== 'succeeded'
            || ($receipt['receipt_key'] ?? null) !== $e['operation']['deployment_key']) {
            throw new SiteServiceException('conflict','This attempt did not commit this success receipt.');
        }
    }
    private static function finishAttempt(object $db, array $attempt, string $status, string $now, array $receipt): void
    {
        SiteDeploymentStore::update($db,'site_deployment_attempts',$attempt,['status'=>$status,'completed_at'=>$now,
            'receipt_json'=>SiteBuildContract::json($receipt,16384)]);
    }

    public static function completeDeploymentFailure(array $lease, array $failure): array
    {
        SiteDeploymentContract::lease($lease); SiteDeploymentContract::keys($failure,['code']);
        $code=$failure['code'];
        if (!is_string($code) || !isset(SiteDeploymentContract::FAILURES[$code])) SiteDeploymentContract::invalid();
        $e=self::entry($lease,false,false);
        if ($e['operation']['status'] === 'succeeded') return self::workerDTO($e);
        if ($e['attempt']['receipt_json'] !== null) {
            $receipt=SiteBuildContract::decode($e['attempt']['receipt_json'],16384);
            if (($receipt['code'] ?? null) !== $code) throw new SiteServiceException('conflict','Failure replay differs.');
            return self::workerDTO($e);
        }
        $e=self::entry($lease); $safe=false; $health=null;
        try {
            $safe=self::matches(self::observe($e),$e,true);
            if ($safe) { $health=self::probe($e,$lease,'rollback',true); $safe=$health['result'] === 'pass'; }
        } catch (Throwable) { $safe=false; }
        $worker=self::worker($lease['attempt_kind']);
        return SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$e,$safe,$health,$code): array {
            $locked=self::locked($db,$lease,$worker); $e=array_replace($e,$locked); $op=$e['operation'];
            self::pointer($e['target'],$op);
            if ($health !== null) {
                SiteDeploymentContract::health($health,self::expected($e,true),SiteDeploymentContract::target($e['target']),
                    self::context($e,$lease,'rollback'),$e['now']);
                self::saveHealth($db,$e,$health,true);
            }
            if ($safe) {
                $retry=$lease['attempt_kind'] === 'execution' && $op['activation_attempted_at'] === null
                    && in_array($code,['storage_unavailable','network_unavailable'],true)
                    && (int)$op['execution_count'] < (int)$op['max_execution_attempts'];
                $op=SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>$retry ? 'retry_wait' : 'failed',
                    'next_attempt_at'=>$retry ? SiteBuildStore::after($e['now'],SiteDeploymentContract::POLICY['execution_retry_delays_seconds'][(int)$op['execution_count']-1]) : null,
                    'recovery_status'=>$lease['attempt_kind'] === 'recovery' ? 'resolved' : $op['recovery_status'],
                    'next_recovery_at'=>null,'failure_code'=>$code,'failure_category'=>SiteDeploymentContract::FAILURES[$code],
                    'safe_summary'=>'The previous external identity was verified.','completed_at'=>$retry ? null : $e['now'],'updated_at'=>$e['now']]);
                self::finishAttempt($db,$e['attempt'],$lease['attempt_kind'] === 'recovery' ? 'succeeded' : 'failed',$e['now'],
                    ['receipt_key'=>$op['deployment_key'],'disposition'=>'safely_failed','code'=>$code]);
                SiteManager::settleDeploymentSlot($db,$op,$e['attempt'],'clean',true,$e['now']);
                self::event($db,$op,'site_deployment_failed',SiteServiceSupport::systemActor(),['code'=>$code,'verified_previous'=>true]);
                if ($op['operation'] === 'restore') self::event($db,$op,'site_restore_failed',SiteServiceSupport::systemActor());
                if ($lease['attempt_kind'] === 'recovery') self::event($db,$op,'site_recovery_completed',SiteServiceSupport::systemActor(),['disposition'=>'safely_failed']);
            } else {
                self::finishAttempt($db,$e['attempt'],'failed',$e['now'],['receipt_key'=>$op['deployment_key'],'disposition'=>'unknown','code'=>$code]);
                $op=self::requireRecovery($db,$op,$e['attempt'],$e['now'],$code,$lease['attempt_kind'] === 'recovery'
                    && ($e['attempt']['recovery_trigger'] === 'operator' || !in_array($code,['network_unavailable','database_unavailable','outcome_unknown'],true)));
            }
            return self::dto($db,$op,SiteManager::lockDeploymentTarget($db,$lease['site_id'],$lease['target_id']),false);
        });
    }
    private static function requireRecovery(object $db, array $op, array $attempt, string $now, string $code, bool $forceBlocked = false): array
    {
        $blocked=$forceBlocked || (int)$op['automatic_recovery_count'] >= (int)$op['max_automatic_recoveries'];
        $due=$blocked ? null : ($op['next_recovery_at'] ?? SiteBuildStore::after($now,
            SiteDeploymentContract::POLICY['automatic_recovery_delays_seconds'][(int)$op['automatic_recovery_count']]));
        $op=SiteDeploymentStore::update($db,'site_deployments',$op,['status'=>$op['status'] === 'succeeded' ? 'succeeded' : 'reconciliation_required',
            'recovery_status'=>$blocked ? 'blocked' : 'required','next_recovery_at'=>$due,'next_attempt_at'=>null,
            'failure_code'=>$op['status'] === 'succeeded' ? $op['failure_code'] : $code,
            'failure_category'=>$op['status'] === 'succeeded' ? $op['failure_category'] : SiteDeploymentContract::FAILURES[$code],
            'updated_at'=>$now]);
        SiteManager::settleDeploymentSlot($db,$op,$attempt,$blocked ? 'blocked' : 'required',false,$now);
        self::event($db,$op,$blocked ? 'site_recovery_blocked' : 'site_reconciliation_required',SiteServiceSupport::systemActor(),
            ['attempt_id'=>(int)$attempt['id'],'code'=>$code,'automatic_recovery_count'=>(int)$op['automatic_recovery_count']]);
        return $op;
    }
    public static function claimDeploymentRecovery(int $deploymentId, array $workerContext, ?array $operatorRequest = null): array
    {
        SiteBuildContract::id($deploymentId); SiteDeploymentContract::keys($workerContext,[]); $worker=self::worker('recovery');
        $operatorId=null;
        if ($operatorRequest !== null) {
            SiteDeploymentContract::keys($operatorRequest,['request_key','reason_code','original_execution_id']);
            $operatorRequest['request_key']=SiteBuildContract::uuid($operatorRequest['request_key']);
            SiteBuildContract::id($operatorRequest['original_execution_id']);
            if (!in_array($operatorRequest['reason_code'],SiteDeploymentContract::RECOVERY_REASONS,true)) SiteDeploymentContract::invalid();
            $operatorId=SiteBuildContract::id(self::dependencies()->operatorId());
        }
        $identity=SiteServiceSupport::read(static fn(object $db): ?array=>SiteBuildStore::one($db,'SELECT site_id FROM site_deployments WHERE id = :id',['id'=>$deploymentId]));
        if ($identity === null) throw new SiteServiceException('not_found','Deployment was not found.');
        return SiteBuildStore::transaction(static function(object $db) use($deploymentId,$identity,$worker,$operatorRequest,$operatorId): array {
            SiteManager::lockSite($db,(int)$identity['site_id']);
            $op=SiteDeploymentStore::row($db,'site_deployments',(int)$identity['site_id'],$deploymentId);
            $target=SiteDeploymentStore::row($db,'site_deployment_targets',(int)$identity['site_id'],(int)$op['target_id']);
            if ($target['environment'] !== $worker['environment']) throw new SiteServiceException('unauthorized','Recovery environment differs.');
            $operator=$operatorRequest;
            if ($operator !== null) $operator['actor']=self::actor($db,$operatorId,$target['environment']);
            $target=SiteManager::lockDeploymentTarget($db,(int)$identity['site_id'],(int)$op['target_id']);
            $op=SiteDeploymentStore::row($db,'site_deployments',(int)$identity['site_id'],$deploymentId,true);
            SiteDeploymentContract::policy($op); $now=SiteBuildStore::now($db); $current=self::current($db,$op);
            if ($operator !== null) {
                $old=SiteBuildStore::one($db,'SELECT * FROM site_deployment_attempts WHERE site_id = :site_id AND deployment_id = :deployment_id AND operator_request_key = :request_key',
                    ['site_id'=>(int)$op['site_id'],'deployment_id'=>$deploymentId,'request_key'=>$operator['request_key']]);
                if ($old !== null) {
                    if ((int)$old['recovery_of_attempt_id'] !== $operator['original_execution_id'] || $old['recovery_reason_code'] !== $operator['reason_code']) {
                        throw new SiteServiceException('conflict','Recovery request intent differs.');
                    }
                    return ['claimed'=>false,'replayed'=>true,'attempt_id'=>(int)$old['id'],'attempt_status'=>$old['status']];
                }
            }
            if ($op['status'] === 'succeeded' && ($operator['reason_code'] ?? null) !== 'inspect_external_drift'
                && in_array($op['recovery_status'],['none','resolved'],true)) return ['claimed'=>false,'completed'=>self::dto($db,$op,$target,true)];
            if ($current === null) throw new SiteServiceException('conflict','Recovery requires an original execution.');
            $producer=$current['attempt_kind'] === 'execution' ? $current : self::attempt($db,$op,(int)$current['recovery_of_attempt_id']);
            if ($producer['attempt_kind'] !== 'execution' || ($operator !== null && $operator['original_execution_id'] !== (int)$producer['id'])) {
                throw new SiteServiceException('conflict','Recovery execution identity differs.');
            }
            if (in_array($current['status'],['leased','running'],true) && $current['lease_expires_at'] > $now && $current['deadline_at'] > $now) return ['claimed'=>false,'busy'=>true];
            if ((int)$target['binding_version'] !== (int)$op['binding_version']) throw new SiteServiceException('conflict','Recovery binding changed.');
            if ($op['status'] === 'succeeded' && ((int)$target['current_deployment_id'] !== $deploymentId || $target['active_deployment_id'] !== null)) {
                return ['claimed'=>false,'completed'=>self::dto($db,$op,$target,true)];
            }
            if ($op['status'] !== 'succeeded' && ((int)$target['active_deployment_id'] !== $deploymentId)) throw new SiteServiceException('conflict','Recovery target slot is not owned.');
            if (in_array($current['status'],['leased','running'],true)) {
                SiteDeploymentStore::update($db,'site_deployment_attempts',$current,['status'=>'expired','completed_at'=>$now]);
                $op=self::requireRecovery($db,$op,$current,$now,'lease_expired',
                    $current['attempt_kind'] === 'recovery' && $current['recovery_trigger'] === 'operator');
            }
            if ($operator === null) {
                if ($op['recovery_status'] !== 'required' || $op['next_recovery_at'] === null || $op['next_recovery_at'] > $now
                    || (int)$op['automatic_recovery_count'] >= (int)$op['max_automatic_recoveries']) return ['claimed'=>false,'recovery_status'=>$op['recovery_status']];
            }
            return self::allocate($db,$op,$target,$worker,$now,$producer,$operator);
        },true);
    }
    public static function reconcileDeployment(array $lease): array
    {
        SiteDeploymentContract::lease($lease,'recovery');
        // It is impossible to compensate until this fresh authoritative transaction has succeeded.
        $e=self::entry($lease,false,false);
        if ($e['attempt']['receipt_json'] !== null) return ['receipt_key'=>$e['operation']['deployment_key']];
        $e=self::entry($lease);
        $observation=self::observe($e);
        if ($e['operation']['status'] === 'succeeded') {
            // Committed success is never undone. Actual drift remains blocked for explicit repair.
            return ['receipt_key'=>$e['operation']['deployment_key']];
        }
        if (self::matches($observation,$e,false)) {
            try { self::entry($lease,true); return ['receipt_key'=>$e['operation']['deployment_key']]; }
            catch (SiteServiceException $error) {
                if ($error->classification() === 'database_failure') throw $error;
            }
            // New-success authority is gone. Only the exact prior identity can be restored.
            self::compensate($lease,$e,$observation);
        }
        return ['receipt_key'=>$e['operation']['deployment_key']];
    }
    private static function compensate(array $lease, array $e, array $observed): void
    {
        // A fresh DB read closes the lost-ack window immediately before any external effect.
        $e=self::entry($lease);
        if ($e['operation']['status'] === 'succeeded') return;
        self::pointer($e['target'],$e['operation']);
        if (!self::matches($observed,$e,false) || $e['operation']['activation_fence'] === null) throw new SiteServiceException('conflict','Compensation identity is unverified.');
        if ($e['previous'] !== null) self::verifyArtifact($e['previous']);
        $p=self::publisher($e); $target=SiteDeploymentContract::target($e['target']); $context=self::context($e,$lease,'compensation');
        $intent=['action'=>'compensate','original_execution_id'=>(int)$e['producer']['id'],'observed'=>$observed,
            'expected_previous'=>self::expected($e,true),'activation_fence'=>(int)$e['operation']['activation_fence']];
        SiteDeploymentContract::receipt(self::external(static fn(): array=>$p->reconcileExternalState($intent,$target,$context)),$context,'compensation');
    }
    public static function completeDeploymentRecovery(array $lease, array $result): array
    {
        SiteDeploymentContract::lease($lease,'recovery'); $e=self::entry($lease,false,false); self::hint($result,$e['operation']);
        if ($e['attempt']['receipt_json'] !== null) return self::workerDTO($e);
        $e=self::entry($lease);
        try { $o=self::observe($e); }
        catch (Throwable) { return self::blockRecovery($lease,'outcome_unknown'); }
        if ($e['operation']['status'] === 'succeeded') {
            if (!self::matches($o,$e,false)) return self::blockRecovery($lease,'outcome_unknown',true);
            $checks=self::activeChecks($e,$lease);
            if ($checks[0]['result'] !== 'pass' || $checks[1]['result'] !== 'pass') return self::blockRecovery($lease,'health_failed',true);
            $worker=self::worker('recovery');
            return SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$e,$checks): array {
                $locked=self::locked($db,$lease,$worker); $e=array_replace($e,$locked);
                if ((int)$e['target']['current_deployment_id'] !== $lease['deployment_id']) throw new SiteServiceException('conflict','A later deployment is current.');
                foreach ($checks as $check) {
                    SiteDeploymentContract::health($check,self::expected($e),SiteDeploymentContract::target($e['target']),
                        self::context($e,$lease,'active'),$e['now']);
                    self::saveHealth($db,$e,$check);
                }
                $op=SiteDeploymentStore::update($db,'site_deployments',$e['operation'],['recovery_status'=>'resolved','next_recovery_at'=>null,'updated_at'=>$e['now']]);
                self::finishAttempt($db,$e['attempt'],'succeeded',$e['now'],['receipt_key'=>$op['deployment_key'],'disposition'=>'confirmed']);
                SiteManager::settleDeploymentSlot($db,$op,$e['attempt'],'clean',true,$e['now']);
                self::event($db,$op,'site_recovery_completed',SiteServiceSupport::systemActor(),['disposition'=>'confirmed']);
                return self::dto($db,$op,SiteManager::lockDeploymentTarget($db,$lease['site_id'],$lease['target_id']),false);
            });
        }
        if (self::matches($o,$e,false)) {
            try { return self::success($lease,$result); }
            catch (SiteServiceException $error) {
                // An unavailable commit outcome is not permission to rollback.
                if ($error->classification() === 'database_failure') throw $error;
            }
            // Re-resolve success before compensation, including after a lost acknowledgement.
            $e=self::entry($lease);
            if ($e['operation']['status'] === 'succeeded') return self::workerDTO($e);
            try { self::compensate($lease,$e,$o); }
            catch (Throwable) { return self::blockRecovery($lease,'outcome_unknown'); }
        }
        // This repeats actual prior-identity inspection and rollback health; caller booleans cannot settle it.
        return self::completeDeploymentFailure($lease,['code'=>'outcome_unknown']);
    }
    private static function blockRecovery(array $lease, string $code, bool $permanent = false): array
    {
        $worker=self::worker('recovery');
        return SiteBuildStore::transaction(static function(object $db) use($lease,$worker,$code,$permanent): array {
            $e=self::locked($db,$lease,$worker);
            self::finishAttempt($db,$e['attempt'],'failed',$e['now'],['receipt_key'=>$e['operation']['deployment_key'],'disposition'=>'blocked','code'=>$code]);
            $op=self::requireRecovery($db,$e['operation'],$e['attempt'],$e['now'],$code,
                $permanent || $e['attempt']['recovery_trigger'] === 'operator');
            return self::dto($db,$op,SiteManager::lockDeploymentTarget($db,$lease['site_id'],$lease['target_id']),false);
        });
    }
    public static function deploymentForActor(int $actingUserId, int $deploymentId): array
    {
        SiteBuildContract::id($deploymentId);
        $identity=SiteServiceSupport::read(static fn(object $db): ?array=>SiteBuildStore::one($db,'SELECT site_id FROM site_deployments WHERE id = :id',['id'=>$deploymentId]));
        if ($identity === null) throw new SiteServiceException('not_found','Deployment was not found.');
        return SiteBuildStore::transaction(static function(object $db) use($actingUserId,$deploymentId,$identity): array {
            SiteManager::lockSite($db,(int)$identity['site_id']);
            $actor=SiteAuthorizationPolicy::actorContext($actingUserId,$db);
            $op=SiteDeploymentStore::row($db,'site_deployments',(int)$identity['site_id'],$deploymentId);
            $target=SiteDeploymentStore::row($db,'site_deployment_targets',(int)$op['site_id'],(int)$op['target_id']);
            if (!$actor[$target['environment'] === 'production' ? 'is_super_admin' : 'is_internal_admin']) throw new SiteServiceException('unauthorized','Deployment history is unavailable.');
            return self::dto($db,$op,$target,true);
        });
    }
    public static function deploymentsForSite(int $actingUserId, int $siteId, string $environment, array $cursor = []): array
    {
        SiteBuildContract::id($siteId); SiteDeploymentContract::keys($cursor,['before_id','limit'],[]);
        if (!in_array($environment,['staging','production'],true)) SiteDeploymentContract::invalid();
        $before=isset($cursor['before_id']) ? SiteBuildContract::id($cursor['before_id']) : PHP_INT_MAX;
        $limit=isset($cursor['limit']) ? SiteBuildContract::id($cursor['limit']) : 25;
        if ($limit>100) SiteDeploymentContract::invalid();
        return SiteBuildStore::transaction(static function(object $db) use($actingUserId,$siteId,$environment,$before,$limit): array {
            SiteManager::lockSite($db,$siteId); self::actor($db,$actingUserId,$environment);
            $target=SiteBuildStore::one($db,'SELECT * FROM site_deployment_targets WHERE site_id = :site_id AND environment = :environment',
                ['site_id'=>$siteId,'environment'=>$environment]);
            if ($target === null) return [];
            $rows=SiteBuildStore::rows($db,'SELECT * FROM site_deployments WHERE site_id = :site_id AND target_id = :target_id AND id < :before_id ORDER BY id DESC LIMIT ' . $limit,
                ['site_id'=>$siteId,'target_id'=>(int)$target['id'],'before_id'=>$before]);
            return array_map(static fn(array $op): array=>self::dto($db,$op,$target,true),$rows);
        });
    }
    private static function workerDTO(array $e): array { return SiteServiceSupport::read(static fn(object $db): array=>self::dto($db,$e['operation'],$e['target'],true)); }
    private static function dto(object $db, array $op, array $target, bool $replayed): array
    {
        $current=$target['current_deployment_id'] === null ? null : SiteDeploymentStore::row($db,'site_deployments',(int)$op['site_id'],(int)$target['current_deployment_id']);
        return ['deployment_id'=>(int)$op['id'],'deployment_key'=>$op['deployment_key'],'request_key'=>$op['request_key'],
            'existing_operation'=>$replayed,'replayed'=>$replayed,'operation'=>$op['operation'],'operation_status'=>$op['status'],
            'recovery_status'=>$op['recovery_status'],'operation_release_id'=>(int)$op['release_id'],
            'target'=>['target_id'=>(int)$target['id'],'environment'=>$target['environment'],
                'current_deployment_id'=>$current === null ? null : (int)$current['id'],'current_release_id'=>$current === null ? null : (int)$current['release_id'],
                'pointer_version'=>(int)$target['pointer_version'],'active_deployment_id'=>$target['active_deployment_id'] === null ? null : (int)$target['active_deployment_id'],
                'reconciliation_status'=>$target['reconciliation_status']],
            'is_current'=>$current !== null && (int)$current['id'] === (int)$op['id']];
    }
    private static function event(object $db, array $op, string $type, array $actor, array $metadata = []): void
    {
        SiteServiceSupport::event($db,(int)$op['site_id'],(int)$op['source_revision_id'],$actor,$type,$op['correlation_id'],null,
            ['deployment_id'=>(int)$op['id'],'target_id'=>(int)$op['target_id'],'release_id'=>(int)$op['release_id'],'operation'=>$op['operation']]+$metadata);
    }
}
