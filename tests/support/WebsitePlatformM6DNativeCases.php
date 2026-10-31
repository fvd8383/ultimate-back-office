<?php
declare(strict_types=1);

function m6dNativeCheck(bool $ok,string $code): void
{ $GLOBALS['m6d_native_assertions']=($GLOBALS['m6d_native_assertions'] ?? 0)+1; M6DNativeContract::need($ok,$code); }
function m6dNativeDeny(callable $call): void
{
    try { $call(); } catch(SiteServiceException) { m6dNativeCheck(true,'expected_denial'); return; }
    throw new RuntimeException('missing_denial');
}
function m6dNativeRecord(array $f): void
{
    $path=M6DNativeRuntime::ROOT.'/state/fixture-'.$f['site_id'].'.json';
    $old=umask(0077); $stream=fopen($path,'xb'); umask($old); M6DNativeContract::need(is_resource($stream),'fixture_record');
    try { $json=json_encode($f,JSON_THROW_ON_ERROR); M6DNativeContract::need(strlen($json)<=8192 && fwrite($stream,$json)===strlen($json) && fflush($stream),'fixture_record_write'); }
    finally { fclose($stream); }
}
function m6dNativeChild(array $a,array $f,string $mode,string $kind='publish'): array
{
    $p=new M6CNativeProcess([PHP_BINARY,'-d','memory_limit=256M','-d','zend.exception_ignore_args=1','-d','display_errors=stderr',
        __DIR__.'/WebsitePlatformM6DNativeWorker.php',$a['source_sha'],$a['image_id'],$a['run_token'],$mode,(string)$f['site_id'],$kind],45,65536,
        ['PATH'=>'/usr/bin:/bin','HOME'=>'/nonexistent','LANG'=>'C','TMPDIR'=>M6DNativeRuntime::ROOT.'/state']);
    $line=$p->line(); M6DNativeContract::need((bool)preg_match('/^READY ([0-9]+)$/D',$line,$m),'child_ready');
    return [$p,(int)$m[1]];
}
function m6dNativeLockWait(PDO $observer,int $waiting,int $blocking): void
{
    $end=microtime(true)+10; $seen=false;
    do {
        $s=$observer->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w
            INNER JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID
            INNER JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID
            WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?'); $s->execute([$waiting,$blocking]);
        if((int)$s->fetchColumn()>0) { $seen=true; break; } usleep(10000);
    } while(microtime(true)<$end);
    m6dNativeCheck($seen,'independent_mysql_lock_wait');
}
function m6dNativeChildResult(M6CNativeProcess $p): mixed
{
    $line=$p->line(); $r=$p->finish();
    m6dNativeCheck($r['exit']===0 && !$r['signaled'] && $r['stderr']==='','native_child_result');
    if(str_starts_with($line,'DENIED ')) return ['denied'=>substr($line,7)];
    M6DNativeContract::need(str_starts_with($line,'RESULT '),'child_result_shape');
    return json_decode(substr($line,7),true,32,JSON_THROW_ON_ERROR);
}
function m6dNativeProduction(PDO $db,array $f,M6DNativeDependencies $d): array
{
    $db->prepare("UPDATE site_deployment_targets SET environment='production' WHERE id=? AND site_id=?")->execute([$f['target_id'],$f['site_id']]);
    // Explicit isolated fixture profile; no real production artifact/profile is introduced.
    $db->prepare("UPDATE site_releases SET build_profile='fixture-production-v1' WHERE id=? AND site_id=?")->execute([$f['release_id'],$f['site_id']]);
    $d->production=true; $d->environment='production';
    return SiteApprovalManager::approveProductionRelease($f['operator'],['site_id'=>$f['site_id'],'target_id'=>$f['target_id'],
        'release_id'=>$f['release_id'],'operation'=>'publish','expected_current_deployment_id'=>null,'expected_pointer_version'=>0,
        'binding_version'=>1,'restored_from_deployment_id'=>null,'ttl_seconds'=>300,'supersedes_approval_id'=>null,'correlation_id'=>'native-grant']);
}
function m6dNativeCase(string $name,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    $check=static fn(string $sql,array $p=[])=>SiteBuildStore::one($observer,$sql,$p);
    if(in_array($name,['publish-request-history','restore-request-history','publish-payload-fields','restore-payload-fields'],true)) {
        $restore=str_starts_with($name,'restore'); $source=$restore ? m6dNativePublish($db,$f)['deployment_id'] : null;
        $v=m6dNativeInput($db,$f,['restored_from_deployment_id'=>$source,'reason_code'=>$restore ? 'restore_known_good' : 'publish_approved_release']);
        $request=static fn(array $in,int $actor)=>$restore ? SiteDeploymentService::requestRestore($actor,$in) : SiteDeploymentService::requestDeployment($actor,$in);
        $op=$request($v,$f['requester']); $again=$request($v,$f['operator']);
        m6dNativeCheck($again['replayed'] && $again['deployment_id']===$op['deployment_id'],'both_request_identity');
        foreach(['release_id'=>PHP_INT_MAX,'expected_current_deployment_id'=>$source===null ? PHP_INT_MAX : null,'expected_pointer_version'=>999,
            'deployment_approval_id'=>PHP_INT_MAX,'binding_version'=>99,'reason_code'=>'repair_verified_release'] as $field=>$value) {
            m6dNativeDeny(fn()=>$request(array_replace($v,[$field=>$value]),$f['operator']));
        }
        if($restore) m6dNativeDeny(fn()=>$request(array_replace($v,['restored_from_deployment_id'=>PHP_INT_MAX]),$f['operator']));
        else m6dNativeDeny(fn()=>SiteDeploymentService::requestRestore($f['operator'],array_replace($v,['restored_from_deployment_id'=>PHP_INT_MAX])));
        $queueCase=in_array($name,['publish-request-history','restore-request-history'],true);
        if($queueCase) {
            $intentB=array_replace($v,['request_key'=>SiteServiceSupport::uuidV4()]); $b=$request($intentB,$f['requester']);
            $beforeB=$check('SELECT * FROM site_deployments WHERE id=? AND site_id=?',[$b['deployment_id'],$f['site_id']]);
        }
        $l=SiteDeploymentService::claimDeployment([])['lease']; $r=SiteDeploymentService::executeDeployment($l); SiteDeploymentService::completeDeploymentSuccess($l,$r);
        if($queueCase) {
            // A/B/C use the actual services; this independent PDO observes committed outcomes, never seeds them.
            $savedA=$check('SELECT * FROM site_deployments WHERE id=? AND site_id=?',[$op['deployment_id'],$f['site_id']]);
            $target=$check('SELECT * FROM site_deployment_targets WHERE id=? AND site_id=?',[$f['target_id'],$f['site_id']]);
            $intentC=array_replace($v,['request_key'=>SiteServiceSupport::uuidV4(),'expected_current_deployment_id'=>$op['deployment_id'],
                'expected_pointer_version'=>(int)$target['pointer_version']]);
            $c=$request($intentC,$f['operator']);
            $publisherPath=M6DNativeRuntime::ROOT.'/state/publisher-'.$f['site_id'].'.json'; $publisherHash=hash_file('sha256',$publisherPath);
            $claim=SiteDeploymentService::claimDeployment([]);
            m6dNativeCheck(($claim['lease']['deployment_id'] ?? null)===$c['deployment_id'],'queue_c_claimed_after_stale_b');
            $afterB=$check('SELECT * FROM site_deployments WHERE id=? AND site_id=?',[$b['deployment_id'],$f['site_id']]);
            m6dNativeCheck($afterB['status']==='cancelled' && $afterB['failure_code']==='expected_pointer_changed'
                && $afterB['current_attempt_id']===null && (int)$afterB['attempt_count']===0,'queue_b_terminal_zero_attempts');
            foreach(['status','failure_category','failure_code','safe_summary','completed_at','updated_at'] as $key) { unset($beforeB[$key],$afterB[$key]); }
            m6dNativeCheck($beforeB===$afterB,'queue_b_immutable_intent_and_counters');
            $afterTarget=$check('SELECT * FROM site_deployment_targets WHERE id=? AND site_id=?',[$f['target_id'],$f['site_id']]);
            m6dNativeCheck((int)$afterTarget['current_deployment_id']===$op['deployment_id']
                && $afterTarget['pointer_version']===$target['pointer_version']
                && (int)$afterTarget['fence_epoch']===(int)$target['fence_epoch']+1
                && $savedA===$check('SELECT * FROM site_deployments WHERE id=? AND site_id=?',[$op['deployment_id'],$f['site_id']]),'queue_a_history_and_only_c_fence');
            m6dNativeCheck(SiteDeploymentService::claimDeployment([])===null,'queue_repeat_no_duplicate_claim');
            $events=$check("SELECT COUNT(*) AS n FROM site_events WHERE site_id=? AND event_type='site_deployment_cancelled'
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.deployment_id')) AS UNSIGNED)=?",[$f['site_id'],$b['deployment_id']]);
            m6dNativeCheck((int)$events['n']===1 && $request($intentB,$f['operator'])['operation_status']==='cancelled','queue_one_audit_and_historical_replay');
            m6dNativeCheck(hash_file('sha256',$publisherPath)===$publisherHash,'queue_no_publisher_model_effect');
            $r=SiteDeploymentService::executeDeployment($claim['lease']); $later=SiteDeploymentService::completeDeploymentSuccess($claim['lease'],$r);
        } else $later=m6dNativePublish($db,$f);
        $d->ready=false;
        $again=$request($v,$f['operator']); m6dNativeCheck(!$again['is_current'] && $again['target']['current_deployment_id']===$later['deployment_id'],'historical_current_pointer');
        return [];
    }
    if(in_array($name,['concurrent-request-identity','concurrent-target-claims'],true)) {
        $f['input']=m6dNativeInput($db,$f); $claim=$name==='concurrent-target-claims';
        if($claim) SiteDeploymentService::requestDeployment($f['requester'],$f['input']);
        m6dNativeRecord($f);
        [$one,$oneId]=m6dNativeChild($a,$f,$claim ? 'claim-hold' : 'request-hold'); $one->send('START');
        m6dNativeCheck($one->line()==='LOCKED','first_service_transaction_locked');
        [$two,$twoId]=m6dNativeChild($a,$f,$claim ? 'claim' : 'request'); $two->send('START');
        m6dNativeLockWait($observer,$twoId,$oneId); $one->send('RELEASE');
        $r1=m6dNativeChildResult($one); $r2=m6dNativeChildResult($two);
        m6dNativeCheck($claim ? (($r1['claimed'] ?? false) && $r2===null) : ($r1['deployment_id']===$r2['deployment_id'] && $r2['replayed']),'serialized_service_outcomes');
        m6dNativeCheck((int)$check('SELECT COUNT(*) AS n FROM site_deployments WHERE site_id=?',[$f['site_id']])['n']===1,'one_scoped_intent');
        return ['lock_wait_observed'=>true,'connections'=>[$oneId,$twoId]];
    }
    if($name==='production-role-lock') {
        $g=m6dNativeProduction($db,$f,$d); $f['requester']=$f['operator']; $f['environment']='production';
        $f['input']=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]);
        SiteDeploymentService::requestDeployment($f['operator'],$f['input']); m6dNativeRecord($f);
        $observer->beginTransaction(); $observer->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['operator']]);
        [$p,$pid]=m6dNativeChild($a,$f,'claim'); $p->send('START');
        m6dNativeLockWait($db->pdo,$pid,(int)$observer->query('SELECT CONNECTION_ID()')->fetchColumn()); $observer->commit();
        m6dNativeCheck(m6dNativeChildResult($p)===null,'revocation_serialized_before_claim');
        $cancelled=$check('SELECT status,failure_code,attempt_count FROM site_deployments WHERE site_id=?',[$f['site_id']]);
        m6dNativeCheck($cancelled['status']==='cancelled' && $cancelled['failure_code']==='requester_not_authorized'
            && (int)$cancelled['attempt_count']===0,'revoked_requester_durable_disposition');
        m6dNativeCheck((int)$check('SELECT COUNT(*) AS n FROM site_deployment_attempts WHERE site_id=?',[$f['site_id']])['n']===0,'no_claim_after_revocation');
        return ['lock_wait_observed'=>true];
    }
    if($name==='scoped-current-access') {
        $v=m6dNativeInput($db,$f); $op=SiteDeploymentService::requestDeployment($f['requester'],$v);
        m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['owner'],$v));
        m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['requester'],array_replace($v,['target_id'=>PHP_INT_MAX])));
        $observer->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
        m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['requester'],$v));
        m6dNativeCheck(SiteDeploymentService::deploymentForActor($f['operator'],$op['deployment_id'])['deployment_id']===$op['deployment_id'],'current_other_actor_history'); return [];
    }
    if(in_array($name,['production-grant-boundaries','staging-production-lifecycle'],true)) {
        if($name==='staging-production-lifecycle') {
            m6dNativePublish($db,$f);
            m6dNativeCheck($check('SELECT current_published_revision_id FROM sites WHERE id=?',[$f['site_id']])['current_published_revision_id']===null,'staging_no_global_publication');
            // A separate production target is required; the staging target identity is immutable.
            $f['target_id']=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'production','publisher_key'=>'m6d-native-model',
                'binding_key'=>'m6d-production-'.$f['site_id'],'binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
        }
        $g=m6dNativeProduction($db,$f,$d); $d->fixture=$f;
        $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]);
        $d->production=false; m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['operator'],$v)); $d->production=true;
        $op=SiteDeploymentService::requestDeployment($f['operator'],$v); $l=SiteDeploymentService::claimDeployment([])['lease'];
        $r=SiteDeploymentService::executeDeployment($l); $done=SiteDeploymentService::completeDeploymentSuccess($l,$r);
        m6dNativeCheck($done['operation_status']==='succeeded','simulated_production_success');
        m6dNativeCheck((int)$check('SELECT current_published_revision_id FROM sites WHERE id=?',[$f['site_id']])['current_published_revision_id']===$f['revision_id'],'atomic_global_publication');
        SiteApprovalManager::revokeProductionRelease($f['operator'],$f['site_id'],$g['deployment_approval_id'],'native-revoke');
        $observer->prepare('UPDATE site_deployment_approvals SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')->execute([$g['deployment_approval_id'],$f['site_id']]);
        m6dNativeCheck(SiteDeploymentService::requestDeployment($f['operator'],$v)['operation_status']==='succeeded','revoked_expired_grant_history'); return [];
    }
    if($name==='known-good-restore') {
        $known=m6dNativePublish($db,$f);
        $observer->prepare("UPDATE site_revisions SET lifecycle_status='superseded' WHERE id=? AND site_id=?")->execute([$f['revision_id'],$f['site_id']]);
        $observer->prepare("UPDATE site_approvals SET state='superseded' WHERE site_id=?")->execute([$f['site_id']]);
        SiteDeploymentService::requestRestore($f['requester'],m6dNativeInput($db,$f,['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good']));
        $l=SiteDeploymentService::claimDeployment([])['lease']; $r=SiteDeploymentService::executeDeployment($l); $r=SiteDeploymentService::completeDeploymentSuccess($l,$r);
        m6dNativeCheck($r['operation_status']==='succeeded' && $r['deployment_id']!==$known['deployment_id'],'known_good_new_operation'); return [];
    }
    $op=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f)); $op['site_id']=$f['site_id'];
    $l=SiteDeploymentService::claimDeployment([])['lease'];
    if($name==='third-execution-recovery') {
        for($i=1;$i<3;$i++) {
            SiteDeploymentService::completeDeploymentFailure($l,['code'=>'storage_unavailable']);
            $observer->prepare('UPDATE site_deployments SET next_attempt_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')->execute([$op['deployment_id'],$f['site_id']]);
            $l=SiteDeploymentService::claimDeployment([])['lease'];
        }
    }
    $r=SiteDeploymentService::executeDeployment($l);
    if($name==='committed-ack-history') {
        $db->loseSuccessAck=true; m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r));
        m6dNativeCheck($check('SELECT status FROM site_deployments WHERE id=?',[$op['deployment_id']])['status']==='succeeded','independent_committed_result');
        m6dNativePublish($db,$f); $repeat=SiteDeploymentService::completeDeploymentSuccess($l,$r);
        m6dNativeCheck(!$repeat['is_current'] && $repeat['operation_status']==='succeeded','lost_ack_old_success'); return [];
    }
    if($name==='result-audit-rollback') {
        $db->faultEvent='site_deployment_succeeded'; m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r)); $db->faultEvent=null;
        m6dNativeCheck($check('SELECT current_deployment_id FROM site_deployment_targets WHERE id=?',[$f['target_id']])['current_deployment_id']===null,'real_mysql_audit_rollback');
    }
    m6dNativeExpire($observer,$l); $claim=m6dNativeRecover($observer,$op);
    m6dNativeCheck($claim['claimed'],'independent_recovery_claim');
    m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($l));
    if($name==='third-execution-recovery') {
        $counts=$check('SELECT attempt_count,execution_count,recovery_count FROM site_deployments WHERE id=?',[$op['deployment_id']]);
        m6dNativeCheck(array_map('intval',array_values($counts))===[4,3,1],'execution_three_recovery_four');
    }
    if($name==='unavailable-result-no-compensation') {
        $path=M6DNativeRuntime::ROOT.'/state/publisher-'.$f['site_id'].'.json'; $before=hash_file('sha256',$path); $db->unavailable=true;
        m6dNativeDeny(fn()=>SiteDeploymentService::reconcileDeployment($claim['lease'])); $db->unavailable=false;
        m6dNativeCheck(hash_file('sha256',$path)===$before,'unknown_database_no_external_effect'); return [];
    }
    if(in_array($name,['recovery-budget-operator','deleted-recovery-actor'],true)) {
        m6dNativeExpire($observer,$claim['lease']); $claim=m6dNativeRecover($observer,$op);
        m6dNativeExpire($observer,$claim['lease']); $blocked=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
        m6dNativeCheck(!$blocked['claimed'],'automatic_recovery_exhausted');
        $request=['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'retry_recovery','original_execution_id'=>$l['attempt_id']];
        $claim=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request);
        m6dNativeCheck($claim['claimed'] && !SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request)['claimed'],'operator_claim_replay');
        if($name==='deleted-recovery-actor') {
            $observer->prepare('DELETE FROM users WHERE id=?')->execute([$f['operator']]);
            $attempt=$check('SELECT recovery_authorized_by_user_id,recovery_trigger,operator_request_key FROM site_deployment_attempts WHERE id=?',[$claim['lease']['attempt_id']]);
            m6dNativeCheck($attempt['recovery_authorized_by_user_id']===null && $attempt['recovery_trigger']==='operator' && $attempt['operator_request_key']===$request['request_key'],'nullable_historical_actor_fk');
            m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request));
        }
    }
    $done=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],SiteDeploymentService::reconcileDeployment($claim['lease']));
    m6dNativeCheck($done['operation_status']==='succeeded','native_recovery_finalization');
    return [];
}
