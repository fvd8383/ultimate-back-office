<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

function m6dRecovery(M6DDatabase $db,int $id): array
{
    SiteDeploymentService::claimDeploymentRecovery($id,[]); $db->advance(31);
    return SiteDeploymentService::claimDeploymentRecovery($id,[]);
}
foreach (['stage','activated','null-compensation','unknown','compensation-failed'] as $scenario) {
    $db=new M6DDatabase(); $op=$db->request(); $lease=$db->claim()['lease'];
    $oldContext=null;
    $db->runtime->publisher->hook=static function($method,$context) use($scenario,&$oldContext): void {
        if($method==='activate') $oldContext=$context;
        if($scenario==='stage' && $method==='health' && $context['phase']==='candidate') throw new SiteServiceException('conflict','Synthetic stopped execution after durable staging.');
    };
    if ($scenario!=='stage') SiteDeploymentService::executeDeployment($lease);
    else {
        m6dDeny(fn()=>SiteDeploymentService::executeDeployment($lease),'conflict');
        m6dAssert($db->tables['site_deployments'][$op['deployment_id']]['status']==='artifact_staged','C03 durable staged receipt before stopped execution');
    }
    $db->runtime->publisher->hook=null;
    $db->advance(121); $claim=m6dRecovery($db,$op['deployment_id']);
    m6dAssert($claim['claimed'] && $claim['lease']['attempt_kind']==='recovery' && $claim['lease']['fence_epoch']>$lease['fence_epoch'],'Fresh independent recovery '.$scenario);
    m6dDeny(fn()=>SiteDeploymentService::renewDeploymentLease($lease),'conflict');
    if($oldContext!==null) {
        $external=$db->runtime->publisher->pointers;
        m6dDeny(fn()=>$db->runtime->publisher->activateRelease([],['release_key'=>null,'artifact_hash'=>null,'deployment_id'=>null],$oldContext),'conflict');
        m6dAssert($db->runtime->publisher->pointers===$external,'D13 model rejects resumed old effect after fresh fence');
    }
    m6dDeny(fn()=>SiteDeploymentService::executeDeployment($claim['lease']),'invalid_request');
    if(in_array($scenario,['null-compensation','compensation-failed'],true)) $db->inner->read->base->sites[10]['lifecycle_status']='suspended';
    if($scenario==='unknown') $db->runtime->publisher->unknown=true;
    if($scenario==='compensation-failed') $db->runtime->publisher->compensationFails=true;
    $priorCalls=count(array_filter($db->runtime->publisher->calls,fn($c)=>in_array($c,['stage','activate'],true)));
    $r=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],SiteDeploymentService::reconcileDeployment($claim['lease']));
    $status=$scenario==='activated' ? 'succeeded' : (in_array($scenario,['unknown','compensation-failed'],true) ? 'reconciliation_required' : 'failed');
    m6dAssert($r['operation_status']===$status,'Recovery disposition '.$scenario.' got '.$r['operation_status']);
    m6dAssert(count(array_filter($db->runtime->publisher->calls,fn($c)=>in_array($c,['stage','activate'],true)))===$priorCalls,'No recovery stage/activation');
    if($scenario==='null-compensation') {
        m6dAssert($db->runtime->publisher->pointers[20]===null && $r['target']['current_deployment_id']===null,'D17 NULL pointer and independent absent external state');
        $absent=array_filter($db->tables['site_deployment_health_checks'],fn($c)=>$c['expected_absent']===1 && $c['http_status']===503);
        m6dAssert(count($absent)>0,'First-deployment unavailable health evidence');
    }
    if(in_array($scenario,['unknown','compensation-failed'],true)) m6dAssert($r['target']['active_deployment_id']===$op['deployment_id'],'Unresolved target remains occupied');
    $before=$db->snapshot(); $replay=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],['receipt_key'=>$op['deployment_key']]);
    m6dAssert($db->snapshot()===$before && $replay['operation_status']===$r['operation_status'],'C05 recovery result replay');
}

$db=new M6DDatabase(); $op=$db->request();
for($i=1;$i<=3;$i++) {
    $lease=$db->claim()['lease'];
    if($i<3) { SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'storage_unavailable']); $db->advance($i===1 ? 31 : 121); }
    else SiteDeploymentService::executeDeployment($lease);
}
$db->advance(121); $claim=m6dRecovery($db,$op['deployment_id']);
$row=$db->tables['site_deployments'][$op['deployment_id']];
m6dAssert([$row['attempt_count'],$row['execution_count'],$row['recovery_count']] === [4,3,1],'C08 total4 execution3 recovery1');
$db->advance(121); SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]); $db->advance(121);
$next=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
m6dAssert($next['claimed'] && $next['lease']['fence_epoch']>$claim['lease']['fence_epoch'],'C11 recovery restart fresh fence');
m6dDeny(fn()=>SiteDeploymentService::renewDeploymentLease($claim['lease']),'conflict');
$db->advance(121); $exhaust=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
m6dAssert(!$exhaust['claimed'] && $db->tables['site_deployments'][$op['deployment_id']]['recovery_status']==='blocked','C12 automatic budget exhausted');
m6dAssert(SiteDeploymentService::claimDeployment([])===null,'No fourth execution');
$operator=['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'retry_recovery','original_execution_id'=>$lease['attempt_id']];
$again=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$operator);
m6dAssert($again['claimed'],'Bounded explicit operator recovery');
$before=$db->snapshot(); $repeat=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$operator);
m6dAssert(!$repeat['claimed'] && $repeat['replayed'] && !isset($repeat['lease']) && $db->snapshot()===$before,'Operator replay no second lease');
m6dDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],array_replace($operator,['reason_code'=>'verify_compensation'])),'conflict');

$db=new M6DDatabase(); $op=$db->request(); $lease=$db->claim()['lease']; $receipt=SiteDeploymentService::executeDeployment($lease);
$db->hook=static function($sql,$p,$db): void { if(str_contains($sql,'UPDATE site_deployment_targets SET current_deployment_id')) $db->loseCommit=true; };
m6dDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($lease,$receipt),'database_failure');
$db->hook=null; $before=$db->snapshot();
$r=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
m6dAssert($r['operation_status']==='succeeded' && $db->snapshot()===$before,'C09 lost acknowledgement committed result returned once');
m6dAssert(!in_array('compensate',$db->runtime->publisher->calls,true),'Committed success never compensated');
$db->publish(); $r=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
m6dAssert(!$r['is_current'] && $r['operation_status']==='succeeded','C09 historical commit survives later winner');

$db=new M6DDatabase(); $op=$db->request(); $lease=$db->claim()['lease']; SiteDeploymentService::executeDeployment($lease);
$db->advance(121); $claim=m6dRecovery($db,$op['deployment_id']); $db->unavailable=true; $calls=$db->runtime->publisher->calls;
m6dDeny(fn()=>SiteDeploymentService::reconcileDeployment($claim['lease']),'database_failure');
m6dAssert($db->runtime->publisher->calls===$calls,'Unavailable authoritative DB prevents external compensation');
$db=new M6DDatabase(); $op=$db->publish();
$execution=$db->tables['site_deployments'][$op['deployment_id']]['current_attempt_id'];
$db->runtime->publisher->pointers[20]=null;
$claim=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],[
    'request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'inspect_external_drift','original_execution_id'=>$execution]);
$done=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],SiteDeploymentService::reconcileDeployment($claim['lease']));
m6dAssert($done['operation_status']==='succeeded' && $done['recovery_status']==='blocked'
    && $done['target']['current_deployment_id']===$op['deployment_id'],'C02 drift freezes target without rewriting historical success');
m6dAssert(!in_array('compensate',$db->runtime->publisher->calls,true) && $db->events('site_deployment_succeeded')===1,'C02 committed drift never causes blind rollback');

echo 'M6D recovery: '.$GLOBALS['m6d_assertions']." assertions PASS (modeled PDO/clock/external state).\n";
