<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeWire.php';

function m6dWireState(PDO $db,array $f): array
{
    $r=[];
    foreach(['site_deployments','site_deployment_attempts','site_deployment_targets','site_deployment_health_checks','site_deployment_approvals','site_events','site_revisions'] as $table)
        $r[$table]=m6dNativeRows($db,'SELECT * FROM '.$table.' WHERE site_id=? ORDER BY id',[$f['site_id']]);
    $r['sites']=m6dNativeRows($db,'SELECT * FROM sites WHERE id=?',[$f['site_id']]); return $r;
}
function m6dWireHash(array $v): string { return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR)); }
function m6dWireEffects(array $f): array
{
    $calls=m6dNativeModel($f)['calls']; $r=[];
    foreach(['stage','activate','restore','compensate','health'] as $key)
        $r[$key]=count(array_filter($calls,static fn($c)=>$c===$key));
    return $r;
}
function m6dWireStart(string $file,array $a,array $b): M6CNativeProcess
{
    // M6CNativeProcess registers immediately in its constructor, before any barrier read.
    $p=new M6CNativeProcess([PHP_BINARY,'-d','memory_limit=256M','-d','zend.exception_ignore_args=1',__DIR__.'/'.$file,
        $a['source_sha'],$a['image_id'],$a['run_token'],(string)$b['site_id'],$b['subcase'],$b['leg']],45,65536);
    $p->diagnosticContext=['case'=>'result-commit-transport','subcase'=>$b['subcase'],'leg'=>$b['leg'],
        'site_id'=>$b['site_id'],'deployment_id'=>$b['deployment_id'],'attempt_id'=>$b['attempt_id']]; return $p;
}
function m6dWireLeg(string $sub,string $leg,array $a,array $f,array $lease,array $receipt,PDO $observer,array &$beforeBoundary): array
{
    $b=['identity'=>M6DNativeProtocol::identity($a),'case'=>'result-commit-transport','subcase'=>$sub,'leg'=>$leg,
        'site_id'=>$f['site_id'],'target_id'=>$f['target_id'],'deployment_id'=>$lease['deployment_id'],
        'attempt_id'=>$lease['attempt_id'],'channel'=>bin2hex(random_bytes(16))];
    $mode=M6DNativeWire::mode($sub,$leg); $path=M6DNativeWire::jobPath($f['site_id'],$sub,$leg);
    M6DNativeProtocol::privateRecord($path,['binding'=>$b,'coordinator_pid'=>getmypid(),'fixture'=>$f,'lease'=>$lease,'receipt'=>$receipt]);
    $relay=m6dWireStart('WebsitePlatformM6DNativeRelay.php',$a,$b);
    $endpoint=M6DNativeWire::read($relay,'LISTEN',$b); M6DNativeWire::endpoint($endpoint,$relay->pid);
    M6DNativeProtocol::privateRecord($path.'.endpoint',['binding'=>$b,'endpoint'=>$endpoint]);
    $worker=m6dWireStart('WebsitePlatformM6DNativeWireWorker.php',$a,$b);
    m6dNativeCheck(M6DNativeWire::read($worker,'WORKER',$b)===['worker_pid'=>$worker->pid],'owned_wire_worker');
    $relay->send('ACCEPT'); $worker->send(M6DNativeWire::frame('LISTEN',$b,$endpoint));
    $ready=M6DNativeWire::read($worker,'READY',$b);
    m6dNativeCheck(array_keys($ready)===['connection','backend_verified'] && is_int($ready['connection']) && $ready['connection']>0 && $ready['backend_verified']===true,'wire_backend_verified');
    $worker->send('START'); $phase=$leg==='reread' ? 'READ' : 'COMMIT';
    m6dNativeCheck(M6DNativeWire::read($worker,$phase,$b)===['connection'=>$ready['connection'],'drained'=>true],'exact_result_boundary');
    // Includes the already committed health-probe lease renewals, excludes the held result transaction.
    $beforeBoundary=m6dWireState($observer,$f);
    $modelBefore=m6dNativeModel($f); $effectsBefore=m6dWireEffects($f); $fault=null;
    if($mode!=='unarmed') {
        $relay->send('ARM'); m6dNativeCheck(M6DNativeWire::read($relay,'ARMED',$b)===['mode'=>$mode],'armed_before_release');
    }
    $worker->send('CONTINUE');
    if($mode!=='unarmed') {
        $fault=M6DNativeWire::read($relay,'FAULT',$b);
        m6dNativeCheck($fault===['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack','ack_suppressed'=>$mode==='drop-ack'],'exact_wire_fault');
    }
    $out=M6DNativeWire::read($worker,'OUTCOME',$b);
    if($mode==='unarmed') {
        m6dNativeCheck($out===['returned'=>'succeeded','error'=>null,'commit_returned'=>true,'transport_failure'=>null],'unarmed_service_success');
        $relay->send('STOP'); m6dNativeCheck(M6DNativeWire::read($relay,'STOPPED',$b)===['unarmed'=>true],'unarmed_relay_stop');
    } else {
        m6dNativeCheck($out['returned']===null && $out['commit_returned']===false && in_array($out['transport_failure']['driver'] ?? null,[2006,2013],true),'actual_native_connection_loss');
        // An escaped rollback/other PDO failure is retained in OUTCOME but blocks acceptance.
        m6dNativeCheck(($out['error']['class'] ?? null)===SiteServiceException::class && ($out['error']['classification'] ?? null)==='database_failure','service_failure_classification');
    }
    $relayResult=M6DNativeWire::finish($relay); $worker->send('CLOSE');
    $workerResult=M6DNativeWire::finish($worker,$mode==='unarmed' ? 0 : 1);
    m6dNativeCheck(m6dNativeModel($f)===$modelBefore && m6dWireEffects($f)===$effectsBefore,'no_effect_after_selected_boundary');
    return ['binding'=>$b,'mode'=>$mode,'endpoint'=>$endpoint,'connection'=>$ready['connection'],'backend_verified'=>true,'drained_before_arm'=>true,
        'sequence'=>$mode==='unarmed' ? ['LISTEN','WORKER','READY',$phase,'CONTINUE','OUTCOME','STOPPED'] : ['LISTEN','WORKER','READY',$phase,'ARMED','CONTINUE','FAULT','OUTCOME'],
        'fault'=>$fault,'outcome'=>$out,'worker'=>$workerResult,'relay'=>$relayResult,'last_barrier'=>['worker'=>'OUTCOME','relay'=>$mode==='unarmed' ? 'STOPPED' : 'FAULT'],
        'model_unchanged_after_boundary'=>true,'effects'=>$effectsBefore];
}
function m6dWireObserve(array $a,array $f,array $lease,bool $committed,array $before,array $connections): array
{
    // Brand-new direct connection, never the coordinator's previous snapshot or failed PDO.
    $fresh=M6DNativeRuntime::connect($a); $cid=(int)$fresh->query('SELECT CONNECTION_ID()')->fetchColumn();
    m6dNativeCheck(!in_array($cid,$connections,true),'fresh_authoritative_connection');
    $state=m6dWireState($fresh,$f); $op=m6dNativeRow($fresh,'site_deployments',$f,$lease['deployment_id']);
    $target=m6dNativeRow($fresh,'site_deployment_targets',$f,$f['target_id']); $attempt=m6dNativeRow($fresh,'site_deployment_attempts',$f,$lease['attempt_id']);
    $success=m6dNativeEvents($fresh,$f,'site_deployment_succeeded',$lease['deployment_id']);
    $health=(int)m6dNativeRows($fresh,"SELECT COUNT(*) AS n FROM site_deployment_health_checks WHERE site_id=? AND deployment_id=? AND deployment_attempt_id=? AND phase='active' AND result='pass'",[$f['site_id'],$lease['deployment_id'],$lease['attempt_id']])[0]['n'];
    $GLOBALS['m6d_wire_authoritative'][]=['connection'=>$cid,'site_id'=>$f['site_id'],'target_id'=>$f['target_id'],
        'deployment_id'=>$lease['deployment_id'],'attempt_id'=>$lease['attempt_id'],'state_sha256'=>m6dWireHash($state),
        'status'=>$op['status'],'attempt_status'=>$attempt['status'],'current_deployment'=>$target['current_deployment_id'],
        'active_deployment'=>$target['active_deployment_id'],'success_events'=>$success,'active_health'=>$health];
    if($committed) {
        m6dNativeCheck($op['status']==='succeeded' && $attempt['status']==='succeeded' && $success===1 && $health===2,'fresh_committed_result_health_audit');
        m6dNativeCheck((int)$target['current_deployment_id']===$lease['deployment_id'] && (int)$target['pointer_version']===1
            && $target['active_deployment_id']===null && $target['reconciliation_status']==='clean','fresh_committed_pointer_slot');
        m6dNativeCheck($state['sites'][0]['lifecycle_status']==='active' && (int)$state['sites'][0]['current_published_revision_id']===$f['revision_id']
            && m6dNativeRow($fresh,'site_revisions',$f,$f['revision_id'])['lifecycle_status']==='published','fresh_committed_lifecycle');
    } else {
        m6dNativeCheck($state===$before && $op['status']==='health_check_pending' && $attempt['status']==='leased'
            && $success===0 && $health===0 && $target['current_deployment_id']===null,'fresh_uncommitted_entire_result');
    }
    $grant=m6dNativeRow($fresh,'site_deployment_approvals',$f,(int)$op['deployment_approval_id']);
    m6dNativeCheck((int)$grant['consumed_by_deployment_id']===$lease['deployment_id'] && $op['activation_fence']!==null
        && m6dNativeEvents($fresh,$f,'site_activation_attempted',$lease['deployment_id'])===1,'prior_activation_grant_retained');
    m6dNativeCheck((int)$op['execution_count']===3 && (int)$op['attempt_count']===3 && (int)$op['recovery_count']===0,'wire_execution_three_no_reset');
    return ['connection'=>$cid,'before_replay'=>true,'state_sha256'=>m6dWireHash($state),'status'=>$op['status'],
        'committed'=>$committed,'success_events'=>$success,'active_health'=>$health,'pointer_committed'=>$committed,'lifecycle_committed'=>$committed,
        'slot_settled'=>$committed,'activation_retained'=>true,'grant_retained'=>true,'execution_count'=>3,'attempt_count'=>3,'recovery_count'=>0];
}
function m6dNativeWireCase(string $sub,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    $grant=m6dNativeProductionActors($db,$f,$d); $input=m6dNativeInput($db,$f,['deployment_approval_id'=>$grant['deployment_approval_id']]);
    $op=SiteDeploymentService::requestDeployment($f['requester'],$input); $lease=m6dNativeThird($db,$op);
    $receipt=SiteDeploymentService::executeDeployment($lease); $before=m6dWireState($observer,$f);
    $legs=['result'=>m6dWireLeg($sub,'result',$a,$f,$lease,$receipt,$observer,$before)];
    $connections=[(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn(),(int)$observer->query('SELECT CONNECTION_ID()')->fetchColumn(),$legs['result']['connection']];
    $authoritative=['result'=>m6dWireObserve($a,$f,$lease,$sub!=='commit-not-delivered',$before,$connections)];
    $unknown=null; $history=null; $recovery=null; $d->wire();
    if($sub==='authoritative-unavailable') {
        $state=m6dWireState($observer,$f); $model=m6dNativeModel($f);
        $readBefore=[]; $legs['reread']=m6dWireLeg($sub,'reread',$a,$f,$lease,$receipt,$observer,$readBefore);
        $authoritative['reread']=m6dWireObserve($a,$f,$lease,true,$before,[...$connections,$authoritative['result']['connection'],$legs['reread']['connection']]);
        m6dNativeCheck(m6dWireState($observer,$f)===$state && m6dNativeModel($f)===$model,'unavailable_read_no_mutation_or_compensation');
        $unknown=['read_intercepted'=>true,'service_returned'=>false,'database_unchanged'=>true,'model_unchanged'=>true];
    }
    if($sub==='commit-not-delivered') {
        $effects=m6dWireEffects($f); m6dNativeExpire($observer,$lease);
        $claim=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]); m6dNativeCheck($claim['claimed'],'wire_recovery_claim');
        $r=$claim['lease']; $attempt=m6dNativeRow($observer,'site_deployment_attempts',$f,$r['attempt_id']);
        m6dNativeCheck($r['attempt_id']!==$lease['attempt_id'] && (int)$attempt['recovery_of_attempt_id']===$lease['attempt_id'],'wire_original_producer_retained');
        m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($lease),'conflict');
        $done=SiteDeploymentService::completeDeploymentRecovery($r,SiteDeploymentService::reconcileDeployment($r));
        $counts=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']); $after=m6dWireEffects($f);
        m6dNativeCheck($done['operation_status']==='succeeded' && (int)$counts['attempt_count']===4 && (int)$counts['execution_count']===3
            && (int)$counts['recovery_count']===1 && (int)$counts['automatic_recovery_count']===1,'wire_prior_effect_recovery_budget');
        foreach(['stage','activate','restore','compensate'] as $key)m6dNativeCheck($after[$key]===$effects[$key],'no_new_candidate_or_compensation');
        m6dNativeCheck(m6dNativeEvents($observer,$f,'site_deployment_succeeded',$op['deployment_id'])===1,'wire_recovery_one_success');
        $recovery=['original_attempt'=>$lease['attempt_id'],'recovery_attempt'=>$r['attempt_id'],'attempt_count'=>4,'execution_count'=>3,'recovery_count'=>1,
            'automatic_recovery_count'=>1,'stale_owner_denied'=>true,'new_candidate_effects'=>0,'success_events'=>1];
    } else {
        $snapshot=m6dNativeSnapshot($observer,$f);
        $done=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
        m6dNativeCheck($done['operation_status']==='succeeded' && m6dNativeSnapshot($observer,$f)===$snapshot,'wire_replay_without_duplicate_effect');
        if($sub==='ack-withheld') {
            SiteApprovalManager::revokeProductionRelease($f['operator'],$f['site_id'],$grant['deployment_approval_id'],'wire-history');
            $nextGrant=m6dNativeGrant($db,$f); $nextInput=m6dNativeInput($db,$f,['deployment_approval_id'=>$nextGrant['deployment_approval_id']]);
            $next=SiteDeploymentService::requestDeployment($f['requester'],$nextInput); $nextLease=SiteDeploymentService::claimDeployment([])['lease'];
            $nextDone=SiteDeploymentService::completeDeploymentSuccess($nextLease,SiteDeploymentService::executeDeployment($nextLease));
            $observer->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
            $snapshot=m6dNativeSnapshot($observer,$f); $old=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
            $recognized=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
            m6dNativeDeny(fn()=>SiteDeploymentService::deploymentForActor($f['requester'],$op['deployment_id']),'unauthorized');
            $view=SiteDeploymentService::deploymentForActor($f['operator'],$op['deployment_id']);
            m6dNativeCheck($nextDone['is_current'] && !$old['is_current'] && !$view['is_current'] && $old['operation_status']==='succeeded'
                && !$recognized['claimed'] && $old['target']['current_deployment_id']===$next['deployment_id']
                && m6dNativeSnapshot($observer,$f)===$snapshot,'wire_historical_success_cannot_undo_winner');
            $history=['later_deployment'=>$next['deployment_id'],'old_success_retained'=>true,'no_effects'=>true,'grant_changed'=>true,'actor_denied'=>true,'authorized_history'=>true];
        }
    }
    return ['transport'=>['legs'=>$legs,'authoritative'=>$authoritative,'unknown'=>$unknown,'history'=>$history,'recovery'=>$recovery,'final_disposition'=>$done['operation_status']]];
}
