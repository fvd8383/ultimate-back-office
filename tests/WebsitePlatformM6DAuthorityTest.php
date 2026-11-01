<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

function m6dProduction(M6DDatabase $db): array
{
    $db->tables['site_deployment_targets'][20]['environment']='production';
    $db->runtime->environment='production'; $db->runtime->production=true;
    $id=$db->releaseId(); $db->inner->tables['site_releases'][$id]['build_profile']='fixture-production-v1';
    $db->runtime->artifacts[$id]=$db->inner->tables['site_releases'][$id];
    return ['site_id'=>10,'target_id'=>20,'release_id'=>$id,'operation'=>'publish','expected_current_deployment_id'=>null,
        'expected_pointer_version'=>0,'binding_version'=>1,'restored_from_deployment_id'=>null,'ttl_seconds'=>60,
        'supersedes_approval_id'=>null,'correlation_id'=>'fixture-grant'];
}
$db=new M6DDatabase(); $grantInput=m6dProduction($db);
m6dDeny(fn()=>SiteApprovalManager::approveProductionRelease(1,$grantInput),'unauthorized');
$grant=SiteApprovalManager::approveProductionRelease(2,$grantInput);
$input=$db->input(['deployment_approval_id'=>$grant['deployment_approval_id']]);
m6dDeny(fn()=>SiteDeploymentService::requestDeployment(1,$input),'unauthorized');
$op=SiteDeploymentService::requestDeployment(2,$input); $lease=$db->claim()['lease'];
$receipt=SiteDeploymentService::executeDeployment($lease);
m6dAssert($db->tables['site_deployment_approvals'][$grant['deployment_approval_id']]['consumed_by_deployment_id']===$op['deployment_id'],'Exact grant consumed at activation');
$done=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
m6dAssert($done['operation_status']==='succeeded','D15 simulated production success');
m6dDeny(fn()=>SiteBuildStore::transaction(fn($c)=>SiteRevisionManager::lockBuildEligibility($c,10,100,
    $db->inner->read->base->revisions[100]['snapshot_hash'])),'invalid_transition');
m6dAssert($db->inner->read->base->sites[10]['lifecycle_status']==='active'
    && $db->inner->read->base->sites[10]['current_published_revision_id']===100
    && $db->inner->read->base->revisions[100]['lifecycle_status']==='published','Guarded production lifecycle owners');
SiteApprovalManager::revokeProductionRelease(2,10,$grant['deployment_approval_id'],'revoke-fixture');
$db->advance(100); $db->runtime->production=false; $before=$db->snapshot();
$history=SiteDeploymentService::requestDeployment(2,$input);
m6dAssert($history['operation_status']==='succeeded' && $db->snapshot()===$before,'D22 expired revoked disabled history survives');
$repeat=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
m6dAssert($repeat['operation_status']==='succeeded' && $db->snapshot()===$before,'Committed result before grant/lease/new-success checks');

foreach (['release_id','target_id','site_id','operation','artifact_hash','binding_version','expected_pointer_version','expected_current_deployment_id'] as $field) {
    $db=new M6DDatabase(); $g=SiteApprovalManager::approveProductionRelease(2,m6dProduction($db));
    $r=&$db->tables['site_deployment_approvals'][$g['deployment_approval_id']];
    $r[$field]=is_string($r[$field]) ? ($field==='artifact_hash' ? str_repeat('f',64) : 'restore') : 999;
    m6dDeny(fn()=>SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$g['deployment_approval_id']])));
    m6dAssert($db->runtime->publisher->calls===[],'D03 no external publisher on wrong grant '.$field);
}
foreach (['expiry','revocation','role','runtime','profile','target'] as $boundary) {
    $db=new M6DDatabase(); $g=SiteApprovalManager::approveProductionRelease(2,m6dProduction($db));
    $v=$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]);
    match($boundary) {
        'expiry'=>$db->advance(61),
        'revocation'=>SiteApprovalManager::revokeProductionRelease(2,10,$g['deployment_approval_id'],'fixture-revoke'),
        'role'=>$db->inner->read->users[2]['status']='inactive',
        'runtime'=>$db->runtime->production=false,
        'profile'=>$db->inner->tables['site_releases'][$db->releaseId()]['build_profile']=SiteBuildContract::PROFILE,
        'target'=>$db->tables['site_deployment_targets'][20]['enabled']=0,
    };
    m6dDeny(fn()=>SiteDeploymentService::requestDeployment(2,$v));
    m6dAssert($db->runtime->publisher->calls===[] && $db->tables['site_deployments']===[],'D02/D04 denial '.$boundary);
}
$db=new M6DDatabase(); $v=m6dProduction($db); $g=SiteApprovalManager::approveProductionRelease(2,$v);
m6dDeny(fn()=>SiteApprovalManager::approveProductionRelease(2,$v),'conflict');
$g2=SiteApprovalManager::approveProductionRelease(2,array_replace($v,['supersedes_approval_id'=>$g['approval_id']]));
m6dAssert($db->inner->read->base->approvals[$g['approval_id']]['state']==='superseded','Explicit grant supersession preserves unique current approval');
m6dAssert($g2['approval_id']!==$g['approval_id'],'Replacement decision is distinct');

$db=new M6DDatabase(); $db->inner->read->base->sites[10]['lifecycle_status']='active';
$db->inner->read->base->sites[10]['current_published_revision_id']=100;
foreach(['draft','pending_customer','pending_internal_review','approved'] as $state) {
    $before=$db->snapshot();
    $r=SiteServiceSupport::transaction(fn($c)=>SiteManager::applyLifecycleTransition($c,SiteManager::lockSite($c,10),$state,null,
        ['acting_user_id'=>1,'actor_type'=>'internal_admin'],'live-review','successor_review',true));
    m6dAssert($r['lifecycle_status']==='active' && $db->snapshot()===$before,'D16 live serving state preserved '.$state);
}
m6dDeny(fn()=>SiteServiceSupport::transaction(fn($c)=>SiteRevisionManager::applyLifecycleTransition($c,$db->inner->read->base->revisions[100],'published')),'future_gate_required');

foreach(['stage','activate','health','inspect','compensate'] as $action) {
    $db=new M6DDatabase(); $db->beginTransaction();
    m6dDeny(fn()=>SiteDeploymentService::guardExternal(),'conflict');
    m6dAssert($db->runtime->publisher->calls===[] && $db->runtime->artifactCalls===0,'D14 transactional guard before '.$action);
    $db->rollBack();
}
(new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->setValue(null,null);
m6dDeny(fn()=>SiteDeploymentService::claimDeployment([]),'future_gate_required');
m6dDeny(fn()=>SitePublisherFactory::forTarget(['publisher_key'=>M6DPublisher::class]),'future_gate_required');

foreach(['expiry','revocation','role'] as $change) foreach(['claim','activation','success'] as $boundary) {
    $db=new M6DDatabase(); $g=SiteApprovalManager::approveProductionRelease(2,m6dProduction($db));
    $op=SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]));
    $invalidate=static function() use($db,$g,$change): void {
        if($change==='expiry') $db->tables['site_deployment_approvals'][$g['deployment_approval_id']]['expires_at']=$db->inner->now;
        elseif($change==='revocation') SiteApprovalManager::revokeProductionRelease(2,10,$g['deployment_approval_id'],'boundary-revoke');
        else $db->inner->read->users[2]['status']='inactive';
    };
    if($boundary==='claim') {
        $invalidate();
        m6dAssert(SiteDeploymentService::claimDeployment([])===null
            && $db->tables['site_deployments'][$op['deployment_id']]['status']==='cancelled'
            && $db->tables['site_deployments'][$op['deployment_id']]['execution_count']===0,'D04 denied queued authority is settled without a lease');
    }
    else {
        $l=$db->claim()['lease'];
        if($boundary==='activation') {
            $db->runtime->publisher->hook=static function($method,$context) use($invalidate): void {
                if($method==='health' && $context['phase']==='candidate') $invalidate();
            };
            m6dDeny(fn()=>SiteDeploymentService::executeDeployment($l));
        } else {
            $receipt=SiteDeploymentService::executeDeployment($l); $invalidate();
            m6dDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$receipt));
            $db->advance(121); SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]); $db->advance(31);
            $recovery=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
            $done=SiteDeploymentService::completeDeploymentRecovery($recovery['lease'],SiteDeploymentService::reconcileDeployment($recovery['lease']));
            m6dAssert($done['operation_status']==='failed' && $db->runtime->publisher->pointers[20]===null,'C10 invalid grant compensation '.$change);
        }
    }
    m6dAssert($db->tables['site_deployment_targets'][20]['current_deployment_id']===null && $db->events('site_deployment_succeeded')===0,'D04 boundary '.$boundary.' '.$change);
    if($boundary!=='success') m6dAssert(!in_array('activate',$db->runtime->publisher->calls,true),'No activation with invalid grant');
}

$db=new M6DDatabase(); $g=SiteApprovalManager::approveProductionRelease(2,m6dProduction($db));
$v=$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]);
$known=SiteDeploymentService::requestDeployment(2,$v); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
SiteDeploymentService::completeDeploymentSuccess($l,$r);
$restore=$db->input(['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good','deployment_approval_id'=>$g['deployment_approval_id']]);
m6dDeny(fn()=>SiteDeploymentService::requestRestore(2,$restore));
$grantInput=['site_id'=>10,'target_id'=>20,'release_id'=>$db->releaseId(),'operation'=>'restore','expected_current_deployment_id'=>$known['deployment_id'],
    'expected_pointer_version'=>1,'binding_version'=>1,'restored_from_deployment_id'=>$known['deployment_id'],'ttl_seconds'=>60,
    'supersedes_approval_id'=>$g['approval_id'],'correlation_id'=>'restore-grant'];
$rg=SiteApprovalManager::approveProductionRelease(2,$grantInput); $restore['deployment_approval_id']=$rg['deployment_approval_id'];
$restored=SiteDeploymentService::requestRestore(2,$restore); $l=$db->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
SiteDeploymentService::completeDeploymentSuccess($l,$r);
SiteApprovalManager::revokeProductionRelease(2,10,$rg['deployment_approval_id'],'revoke-restore'); $db->advance(61);
$before=$db->snapshot(); $history=SiteDeploymentService::requestRestore(2,$restore);
m6dAssert($history['operation_status']==='succeeded' && $db->snapshot()===$before,'D22 restore expired revoked consumed history');
foreach(['activation','result'] as $fault) {
    $db=new M6DDatabase(); $g=SiteApprovalManager::approveProductionRelease(2,m6dProduction($db));
    $op=SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]));
    $lease=$db->claim()['lease'];
    $revisionBefore=$db->inner->read->base->revisions[100];
    $db->hook=static function($sql,$p,$db) use($fault): void {
        if(str_starts_with($sql,'INSERT INTO site_events') && ($p['event_type'] ?? '')===
            ($fault==='activation' ? 'site_activation_attempted' : 'site_deployment_succeeded')) throw new PDOException('SYNTHETIC-AUDIT-FAILURE');
    };
    if($fault==='activation') m6dDeny(fn()=>SiteDeploymentService::executeDeployment($lease),'database_failure');
    else {
        $receipt=SiteDeploymentService::executeDeployment($lease);
        m6dDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($lease,$receipt),'database_failure');
    }
    m6dAssert($db->tables['site_deployment_targets'][20]['current_deployment_id']===null
        && $db->inner->read->base->sites[10]['current_published_revision_id']===null
        && $db->inner->read->base->revisions[100]===$revisionBefore
        && $db->events('site_deployment_succeeded')===0,'D12 production pointer/lifecycle/audit rollback '.$fault);
    m6dAssert($db->tables['site_deployment_approvals'][$g['deployment_approval_id']]['consumed_by_deployment_id']===
        ($fault==='activation' ? null : $op['deployment_id']),'Grant consumption atomic with activation authorization; retained across later result rollback');
    m6dAssert(in_array('activate',$db->runtime->publisher->calls,true)===($fault==='result'),'No effect before committed activation authority');
}

$db=new M6DDatabase(); $grantInput=m6dProduction($db); $g=SiteApprovalManager::approveProductionRelease(2,$grantInput);
$a=SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]));
$b=SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$g['deployment_approval_id']]));
$lease=$db->claim()['lease']; SiteDeploymentService::completeDeploymentSuccess($lease,SiteDeploymentService::executeDeployment($lease));
$aRow=$db->tables['site_deployments'][$a['deployment_id']];
$nextGrant=SiteApprovalManager::approveProductionRelease(2,array_replace($grantInput,[
    'expected_current_deployment_id'=>$a['deployment_id'],'expected_pointer_version'=>1,'supersedes_approval_id'=>$g['approval_id']]));
$c=SiteDeploymentService::requestDeployment(2,$db->input(['deployment_approval_id'=>$nextGrant['deployment_approval_id']]));
$claim=$db->claim();
m6dAssert($claim['lease']['deployment_id']===$c['deployment_id'] && $db->tables['site_deployments'][$b['deployment_id']]['status']==='cancelled'
    && $db->tables['site_deployments'][$b['deployment_id']]['failure_code']==='grant_not_valid','A consumed grant cannot let B starve a newly granted C');
m6dAssert($db->tables['site_deployments'][$a['deployment_id']]===$aRow && $db->tables['site_deployments'][$b['deployment_id']]['execution_count']===0,
    'Production queue cancellation preserves committed A and gives B no execution');
m6dAssert($db->tables['site_deployment_approvals'][$g['deployment_approval_id']]['consumed_by_deployment_id']===$a['deployment_id']
    && $db->tables['site_deployment_approvals'][$nextGrant['deployment_approval_id']]['consumed_by_deployment_id']===null,'Queue cancellation neither reconsumes nor transfers grants');

echo 'M6D authority: '.$GLOBALS['m6d_assertions']." assertions PASS (simulated service/DB fixtures; production disabled).\n";
