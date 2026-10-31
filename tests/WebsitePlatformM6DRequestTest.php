<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

foreach (['publish','restore'] as $kind) {
    $db=new M6DDatabase(); $source=null;
    if($kind==='restore') $source=$db->publish()['deployment_id'];
    $input=$db->input(['restored_from_deployment_id'=>$source,'reason_code'=>$kind==='restore' ? 'restore_known_good' : 'publish_approved_release']);
    $request=static fn(int $actor,array $v): array=>$kind==='restore'
        ? SiteDeploymentService::requestRestore($actor,$v) : SiteDeploymentService::requestDeployment($actor,$v);
    $first=$request(1,$input); $before=$db->snapshot(); $calls=$db->runtime->artifactCalls;
    $upper=$input; $upper['request_key']=strtoupper($upper['request_key']);
    $replay=$request(2,$upper);
    m6dAssert($replay['deployment_id']===$first['deployment_id'] && $replay['replayed'] && $replay['existing_operation'],'D19 matching scoped replay');
    m6dAssert($db->snapshot()===$before && $db->runtime->artifactCalls===$calls,'D19 replay has no mutations or artifact work');
    m6dAssert($db->tables['site_deployments'][$first['deployment_id']]['requested_by_user_id']===1,'Original requester retained');
    foreach (['release_id'=>999,'expected_current_deployment_id'=>$source===null ? 999 : null,'expected_pointer_version'=>99,
        'binding_version'=>2,'deployment_approval_id'=>999,'reason_code'=>'repair_verified_release'] as $field=>$value) {
        m6dDeny(fn()=>$request(1,array_replace($input,[$field=>$value])),'conflict');
        m6dAssert($db->snapshot()===$before,'D23 immutable changed '.$field);
    }
    if($kind==='restore') {
        m6dDeny(fn()=>$request(1,array_replace($input,['restored_from_deployment_id'=>999])),'conflict');
        m6dDeny(fn()=>SiteDeploymentService::requestDeployment(1,array_replace($input,['restored_from_deployment_id'=>null])),'conflict');
    }
    else m6dDeny(fn()=>SiteDeploymentService::requestRestore(1,array_replace($input,['restored_from_deployment_id'=>999])),'conflict');
    $claim=$db->claim(); $receipt=SiteDeploymentService::executeDeployment($claim['lease']);
    $done=SiteDeploymentService::completeDeploymentSuccess($claim['lease'],$receipt);
    m6dAssert($done['operation_status']==='succeeded' && $done['is_current'],'D01 success');
    $historical=$request(2,$input);
    m6dAssert($historical['operation_status']==='succeeded' && $historical['is_current'],'D20 success replay ignores obsolete expected pointer');
    $later=$db->publish();
    $before=$db->snapshot(); $db->runtime->ready=false; $db->tables['site_deployment_targets'][20]['enabled']=0;
    $historical=$request(2,$input);
    m6dAssert(!$historical['is_current'] && $historical['target']['current_deployment_id']===$later['deployment_id'],'D21 actual current pointer');
    m6dDeny(fn()=>$request(3,$input),'unauthorized');
    foreach (['release_id'=>1.0,'expected_pointer_version'=>'0','binding_version'=>true,'path'=>'/tmp/arbitrary','worker_policy'=>[]] as $field=>$value) {
        m6dDeny(fn()=>$request(1,array_replace($input,[$field=>$value])),'invalid_request');
    }
}
$db=new M6DDatabase(); $input=$db->input(); $op=$db->request($input); $claim=$db->claim();
$db->runtime->publisher->healthOverrides['candidate']=['result'=>'fail'];
$failed=SiteDeploymentService::executeDeployment($claim['lease']);
m6dAssert($failed['completed']['operation_status']==='failed','Candidate failure verified safe');
$before=$db->snapshot(); $replay=SiteDeploymentService::requestDeployment(2,$input);
m6dAssert($replay['operation_status']==='failed' && $db->snapshot()===$before,'D26 failed history no retry');
$db=new M6DDatabase(); $db->inner->read->users[1]['status']='inactive';
$independent=SiteDeploymentService::requestDeployment(2,$db->input());
m6dAssert($independent['operation_status']==='requested','Build requester loss does not deny independently authorized deployment');
foreach(['publish','restore'] as $kind) foreach(['candidate','active'] as $phase) {
    $db=new M6DDatabase(); $source=$kind==='restore' ? $db->publish()['deployment_id'] : null;
    $v=$db->input(['restored_from_deployment_id'=>$source,'reason_code'=>$kind==='restore' ? 'restore_known_good' : 'publish_approved_release']);
    $request=static fn(int $actor)=>$kind==='restore' ? SiteDeploymentService::requestRestore($actor,$v) : SiteDeploymentService::requestDeployment($actor,$v);
    $op=$request(1); $lease=$db->claim()['lease']; $db->runtime->publisher->healthOverrides[$phase]=['result'=>'fail'];
    $receipt=SiteDeploymentService::executeDeployment($lease);
    $result=$receipt['completed'] ?? SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
    m6dAssert($result['operation_status']===($phase==='candidate'?'failed':'reconciliation_required'),'D26 actual failed/unknown disposition '.$kind);
    $before=$db->snapshot(); $effects=[$db->runtime->artifactCalls,$db->runtime->publisher->calls]; $replay=$request(2);
    m6dAssert($replay['operation_status']===$result['operation_status'] && $replay['recovery_status']===$result['recovery_status']
        && $db->snapshot()===$before && [$db->runtime->artifactCalls,$db->runtime->publisher->calls]===$effects,'D26 replay has no scheduling/lease/external effects '.$kind);
    $db->inner->read->users[2]['status']='inactive'; $before=$db->snapshot();
    m6dDeny(fn()=>$request(2),'unauthorized');
    m6dAssert($db->snapshot()===$before,'D24 current role required for failed/unknown history '.$kind);
}
echo 'M6D request: '.($GLOBALS['m6d_assertions'] ?? 0)." assertions PASS (modeled PDO/publisher).\n";
