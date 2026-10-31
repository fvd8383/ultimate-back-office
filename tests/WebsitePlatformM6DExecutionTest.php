<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

foreach (['publish','restore'] as $kind) {
    $db=new M6DDatabase(); $source=$kind==='restore' ? $db->publish()['deployment_id'] : null;
    $v=$db->input(['restored_from_deployment_id'=>$source,'reason_code'=>$kind==='restore' ? 'restore_known_good' : 'publish_approved_release']);
    $request=static fn()=>$kind==='restore' ? SiteDeploymentService::requestRestore(1,$v) : SiteDeploymentService::requestDeployment(1,$v);
    $startingEvents=$db->events('site_deployment_requested');
    $db->hook=static function($sql,$p,$db) use($request): void {
        if(!str_starts_with($sql,'INSERT INTO site_deployments ')) return;
        $db->hook=null;
        $db->afterRollback=static function($db) use($request): void {
            m6dAssert(!$db->inTransaction(),'D25 losing transaction rolled back before winner reread');
            $request(); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l); SiteDeploymentService::completeDeploymentSuccess($l,$r);
            $db->runtime->ready=false; $db->tables['site_deployment_targets'][20]['enabled']=0;
        };
        $e=new PDOException('SYNTHETIC-DUPLICATE'); $e->errorInfo=['23000',1062,'PRIVATE']; throw $e;
    };
    $r=$request();
    m6dAssert($r['replayed'] && $r['operation_status']==='succeeded' && $db->events('site_deployment_requested')===$startingEvents+1,'D25 winner before new gates '.$kind);
}

$db=new M6DDatabase(); $a=$db->request(); $b=$db->request(); $l=$db->claim()['lease'];
m6dAssert(SiteDeploymentService::claimDeployment([])===null,'D05 occupied target cannot allocate overlapping owner');
$deadline=$db->tables['site_deployment_attempts'][$l['attempt_id']]['deadline_at']; $db->advance(90);
$renew=SiteDeploymentService::renewDeploymentLease($l);
m6dAssert($renew['deadline_at']===$deadline && $renew['lease_expires_at']<=$deadline,'Renewal bounded by fixed deadline');
$db->beginTransaction(); $calls=$db->runtime->publisher->calls;
m6dDeny(fn()=>SiteDeploymentService::executeDeployment($l),'conflict');
m6dAssert($db->runtime->publisher->calls===$calls,'D14 actual execute seam rejects transactional external work'); $db->rollBack();
$r=SiteDeploymentService::executeDeployment($l); SiteDeploymentService::completeDeploymentSuccess($l,$r);
m6dDeny(fn()=>SiteDeploymentService::claimDeployment([]),'stale_write');
m6dAssert($db->tables['site_deployments'][$b['deployment_id']]['execution_count']===0,'Old pointer request cannot execute');
m6dAssert($db->inner->read->base->sites[10]['lifecycle_status']==='approved' && $db->inner->read->base->sites[10]['current_published_revision_id']===null,'Staging leaves global publication alone');

foreach (['http_status'=>302,'site_key'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','release_key'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'artifact_hash'=>str_repeat('e',64),'marker_digest'=>false,'page_sanity'=>false,'css_integrity'=>false,'media_integrity'=>false,
    'origin_integrity'=>false,'tls_verified'=>false,'redirected'=>true,'cache_bypassed'=>false,'no_store'=>false] as $field=>$value) {
    $db=new M6DDatabase(); $op=$db->request(); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
    $db->runtime->publisher->healthOverrides['active']=[$field=>$value];
    try { $result=SiteDeploymentService::completeDeploymentSuccess($l,$r); m6dAssert($result['operation_status']!=='succeeded','D09 invalid active health '.$field); }
    catch(SiteServiceException $e) { m6dAssert(in_array($e->classification(),['invalid_request','conflict'],true),'Strict health identity denial'); }
    m6dAssert($db->tables['site_deployment_targets'][20]['current_deployment_id']===null,'Invalid health no pointer '.$field);
}

foreach (['site_events','site_deployment_health_checks'] as $fault) {
    $db=new M6DDatabase(); $op=$db->request(); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
    $before=$db->snapshot(); $external=$db->runtime->publisher->pointers; $db->failInsert=$fault;
    m6dDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'database_failure');
    m6dAssert($db->tables['site_deployment_targets'][20]['current_deployment_id']===null
        && $db->events('site_deployment_succeeded')===0,'D12 result audit/pointer rollback '.$fault);
    m6dAssert($db->runtime->publisher->pointers===$external,'External candidate remains distinct after DB failure');
    $db->failInsert=null; $db->advance(121); SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]); $db->advance(31);
    $recovery=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
    $done=SiteDeploymentService::completeDeploymentRecovery($recovery['lease'],SiteDeploymentService::reconcileDeployment($recovery['lease']));
    m6dAssert($done['operation_status']==='succeeded' && $db->events('site_deployment_succeeded')===1,'Recovery commits once after result fault');
}

$db=new M6DDatabase(); $known=$db->publish();
$db->inner->read->base->revisions[100]['lifecycle_status']='superseded';
foreach($db->inner->read->base->approvals as &$approval) $approval['state']='superseded'; unset($approval);
$input=$db->input(['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good']);
$restored=SiteDeploymentService::requestRestore(1,$input); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
$done=SiteDeploymentService::completeDeploymentSuccess($l,$r);
m6dAssert($done['operation_status']==='succeeded' && $restored['deployment_id']!==$known['deployment_id'],'R03 historical release reuse with superseded approvals');
m6dAssert($db->inner->read->base->approvals[700]['state']==='superseded','Restore never revives old approval');
$db->inner->read->businesses[50]['is_suspended']=1;
m6dDeny(fn()=>SiteDeploymentService::requestRestore(1,$db->input(['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good'])));

foreach(['candidate','active'] as $phase) {
    $db=new M6DDatabase(); $known=$db->publish(); $previous=$db->runtime->publisher->pointers[20];
    $op=SiteDeploymentService::requestRestore(1,$db->input(['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good']));
    $l=$db->claim()['lease']; $db->runtime->publisher->healthOverrides[$phase]=['result'=>'fail'];
    $r=SiteDeploymentService::executeDeployment($l);
    $failed=$r['completed'] ?? SiteDeploymentService::completeDeploymentSuccess($l,$r);
    if($phase==='active') {
        $db->advance(31); $recovery=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
        $failed=SiteDeploymentService::completeDeploymentRecovery($recovery['lease'],SiteDeploymentService::reconcileDeployment($recovery['lease']));
    }
    m6dAssert($failed['operation_status']==='failed' && $failed['target']['current_deployment_id']===$known['deployment_id'],'R04/R05 verified compensation '.$phase);
    m6dAssert($db->runtime->publisher->pointers[20]['deployment_id']===$known['deployment_id'],'External previous pointer restored '.$phase);
}

foreach(['recovery_authorized_by_user_id'=>2,'worker_id'=>'forged','environment'=>'production'] as $key=>$value) {
    $db=new M6DDatabase(); $op=$db->request(); $before=$db->snapshot();
    m6dDeny(fn()=>SiteDeploymentService::claimDeployment([$key=>$value]),'invalid_request');
    m6dDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[$key=>$value]),'invalid_request');
    m6dAssert($db->snapshot()===$before,'U01 forged claim actor before counters/fence');
}
$db=new M6DDatabase(); $op=$db->request(); $failedLease=$db->claim()['lease'];
SiteDeploymentService::completeDeploymentFailure($failedLease,['code'=>'storage_unavailable']); $db->advance(31);
$winner=$db->claim()['lease']; $receipt=SiteDeploymentService::executeDeployment($winner);
SiteDeploymentService::completeDeploymentSuccess($winner,$receipt); $before=$db->snapshot();
m6dDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($failedLease,$receipt),'conflict');
m6dAssert($db->snapshot()===$before,'Failed earlier attempt cannot claim a later committed success receipt');

echo 'M6D execution: '.$GLOBALS['m6d_assertions']." assertions PASS (service decisions and modeled faults).\n";
