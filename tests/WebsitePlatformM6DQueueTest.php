<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

function queueRequest(M6DDatabase $db,string $kind,?int $source,int $actor=1): array
{
    $v=$db->input(['restored_from_deployment_id'=>$source,'reason_code'=>$kind==='restore'?'restore_known_good':'publish_approved_release']);
    return [$v,$kind==='restore'?SiteDeploymentService::requestRestore($actor,$v):SiteDeploymentService::requestDeployment($actor,$v)];
}
function queueEvents(M6DDatabase $db,int $id): array
{
    return array_values(array_filter($db->inner->read->base->events,static fn($e)=>$e['event_type']==='site_deployment_cancelled'
        && (json_decode($e['metadata_json'],true)['deployment_id'] ?? null)===$id));
}
function queueEffects(M6DDatabase $db): array
{ return [$db->runtime->artifactCalls,$db->runtime->publisher->calls,$db->tables['site_deployment_approvals']]; }
function queueFinish(M6DDatabase $db,array $lease): array
{ return SiteDeploymentService::completeDeploymentSuccess($lease,SiteDeploymentService::executeDeployment($lease)); }

// A second complete tenant/revision fixture, with releases created by the real build service.
function queueOtherSite(M6DDatabase $db): array
{
    $base=$db->inner->read->base;
    $refs=['id','site_id','revision_id','site_page_id','revision_page_id','section_id','asset_id','based_on_revision_id','restored_from_revision_id'];
    foreach(['sites','revisions','sitePages','revisionPages','sections','themes','siteAssets','revisionAssets','approvals'] as $table) {
        foreach($base->$table as $id=>$row) {
            if (($table==='sites' ? $row['id'] : $row['site_id'])!==10) continue;
            foreach($refs as $key) if(isset($row[$key])) $row[$key]+=10000;
            foreach(['business_id','active_business_id'] as $key) if(isset($row[$key])) $row[$key]=60;
            $base->{$table}[$id+10000]=$row;
        }
    }
    $base->sites[10010]['site_key']='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    $refs=json_decode($base->revisions[10100]['source_references_json'],true); $refs['business']['business_id']=60;
    $base->revisions[10100]['source_references_json']=json_encode($refs);
    $db->inner->read->associations[]=['id'=>19,'site_id'=>10010,'business_id'=>60,'association_role'=>'customer','status'=>'active'];
    $base->revisions[10100]['snapshot_hash']=SiteRevisionSnapshotHasher::hashStoredRevision($base,10100);
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$db->inner);
    try {
        $job=SiteBuildService::requestBuild(2,['site_id'=>10010,'revision_id'=>10100,'expected_snapshot_hash'=>$base->revisions[10100]['snapshot_hash'],
            'build_profile'=>SiteBuildContract::PROFILE]);
        $lease=SiteBuildService::claimBuild([]); SiteBuildService::completeBuildSuccess($lease['lease'],$db->inner->receipt($lease,'sealed'));
    } finally { (new ReflectionProperty(Database::class,'connection'))->setValue(null,$db); }
    $releases=array_values(array_filter($db->inner->tables['site_releases'],static fn($r)=>$r['site_id']===10010));
    m6dAssert(count($releases)===1,'Independent tenant has one service-created release'); $release=$releases[0];
    $db->runtime->artifacts[$release['id']]=$release;
    $db->tables['site_deployment_targets'][21]=array_replace($db->tables['site_deployment_targets'][20],[
        'id'=>21,'site_id'=>10010,'binding_key'=>'fixture-other','current_deployment_id'=>null,'active_deployment_id'=>null,
        'pointer_version'=>0,'fence_epoch'=>0,'reconciliation_status'=>'clean']);
    return $db->input(['site_id'=>10010,'target_id'=>21,'release_id'=>$release['id'],'expected_current_deployment_id'=>null,'expected_pointer_version'=>0]);
}

foreach(['publish','restore'] as $kind) {
    $db=new M6DDatabase(); $source=$kind==='restore'?$db->publish()['deployment_id']:null;
    [, $a]=queueRequest($db,$kind,$source); [$intent,$b]=queueRequest($db,$kind,$source);
    queueFinish($db,$db->claim()['lease']); $aRow=$db->tables['site_deployments'][$a['deployment_id']];
    $bRow=$db->tables['site_deployments'][$b['deployment_id']]; $target=$db->tables['site_deployment_targets'][20];
    [, $c]=queueRequest($db,$kind,$source,2); $effects=queueEffects($db); $attempts=count($db->tables['site_deployment_attempts']);
    $claim=$db->claim(); $settled=$db->tables['site_deployments'][$b['deployment_id']];
    m6dAssert($claim['lease']['deployment_id']===$c['deployment_id'] && $settled['status']==='cancelled'
        && $settled['failure_code']==='expected_pointer_changed','A/B/C durable disposition and progress '.$kind);
    foreach(['status','failure_category','failure_code','safe_summary','completed_at','updated_at'] as $key) { unset($settled[$key],$bRow[$key]); }
    m6dAssert($settled===$bRow,'B identity, counters, original requester and evidence retained '.$kind);
    m6dAssert(count($db->tables['site_deployment_attempts'])===$attempts+1 && $db->tables['site_deployment_targets'][20]['fence_epoch']===$target['fence_epoch']+1,
        'Only C obtains a new attempt/fence '.$kind);
    m6dAssert($db->tables['site_deployments'][$a['deployment_id']]===$aRow
        && $db->tables['site_deployment_targets'][20]['current_deployment_id']===$a['deployment_id']
        && $db->tables['site_deployment_targets'][20]['pointer_version']===$target['pointer_version'],'A committed history and pointer preserved '.$kind);
    m6dAssert(queueEffects($db)===$effects && count(queueEvents($db,$b['deployment_id']))===1,'No publisher/artifact/grant effects; one audit '.$kind);
    $before=$db->snapshot(); m6dAssert(SiteDeploymentService::claimDeployment([])===null && $db->snapshot()===$before,'Repeated scan no duplicate outcome '.$kind);
    $replay=$kind==='restore'?SiteDeploymentService::requestRestore(2,$intent):SiteDeploymentService::requestDeployment(2,$intent);
    m6dAssert($replay['operation_status']==='cancelled' && $replay['deployment_id']===$b['deployment_id'] && $db->snapshot()===$before,'Cancelled history replay '.$kind);
    $changed=array_replace($intent,['reason_code'=>'repair_verified_release']);
    m6dDeny(fn()=>$kind==='restore'?SiteDeploymentService::requestRestore(2,$changed):SiteDeploymentService::requestDeployment(2,$changed),'conflict');
}

foreach(['inactive','role','missing','deleted'] as $loss) foreach(['publish','restore'] as $kind) {
    $db=new M6DDatabase(); $source=$kind==='restore'?$db->publish()['deployment_id']:null;
    [, $b]=queueRequest($db,$kind,$source); [, $c]=queueRequest($db,$kind,$source,2);
    if($loss==='inactive') $db->inner->read->users[1]['status']='inactive';
    elseif($loss==='role') $db->inner->read->users[1]['roles']=[];
    elseif($loss==='missing') unset($db->inner->read->users[1]);
    else { unset($db->inner->read->users[1]); $db->tables['site_deployments'][$b['deployment_id']]['requested_by_user_id']=null; }
    $effects=queueEffects($db); $claim=$db->claim();
    m6dAssert($claim['lease']['deployment_id']===$c['deployment_id'] && $db->tables['site_deployments'][$b['deployment_id']]['failure_code']==='requester_not_authorized',
        'Requester denial is local '.$loss.' '.$kind);
    m6dAssert($db->tables['site_deployments'][$b['deployment_id']]['execution_count']===0 && queueEffects($db)===$effects
        && count(queueEvents($db,$b['deployment_id']))===1,'Requester loss no effects or ownership transfer '.$loss.' '.$kind);
}

foreach(['busy','disabled','not-due','succeeded','running','reconciliation','uncertain','lifecycle'] as $state) {
    $db=new M6DDatabase(); $other=queueOtherSite($db); $a=$db->request();
    if($state==='busy') { $lease=$db->claim()['lease']; $a=$db->request(); }
    if($state==='disabled') $db->tables['site_deployment_targets'][20]['enabled']=0;
    $c=SiteDeploymentService::requestDeployment(2,$other);
    if($state==='lifecycle') $db->inner->read->base->sites[10]['lifecycle_status']='suspended';
    $protected=null;
    if(in_array($state,['not-due','succeeded','running','reconciliation','uncertain'],true)) {
        $db->afterQueueSelection=static function($db) use($state,$a,&$protected): void {
            $lease=$db->claim()['lease'];
            if($state==='not-due') SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'storage_unavailable']);
            elseif($state==='succeeded') queueFinish($db,$lease);
            elseif(in_array($state,['reconciliation','uncertain'],true)) {
                SiteDeploymentService::executeDeployment($lease);
                SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'outcome_unknown']);
                if($state==='uncertain') { $db->tables['site_deployments'][$a['deployment_id']]['status']='retry_wait'; $db->tables['site_deployments'][$a['deployment_id']]['recovery_status']='none'; }
            }
            $protected=[$db->tables['site_deployments'][$a['deployment_id']],$db->tables['site_deployment_targets'][20],$db->tables['site_deployment_attempts'],queueEffects($db)];
        };
    }
    $claim=$db->claim(); m6dAssert($claim['lease']['deployment_id']===$c['deployment_id'],'Available target progresses past '.$state);
    $op=$db->tables['site_deployments'][$a['deployment_id']];
    m6dAssert(count(queueEvents($db,$a['deployment_id']))===($state==='lifecycle'?1:0),'No guessed cancellation '.$state);
    if($state==='uncertain') {
        m6dAssert($op['status']==='reconciliation_required' && $op['recovery_status']==='required'
            && $op['execution_count']===$protected[0]['execution_count'] && $op['current_attempt_id']===$protected[0]['current_attempt_id']
            && $db->tables['site_deployment_targets'][20]['active_deployment_id']===$a['deployment_id'],'Uncertain own effects retain recovery authority');
    } elseif($protected!==null) m6dAssert($op===$protected[0] && $db->tables['site_deployment_targets'][20]===$protected[1],'Current selected state preserved '.$state);
    if($protected!==null) {
        m6dAssert(array_intersect_key($db->tables['site_deployment_attempts'],$protected[2])===$protected[2] && queueEffects($db)===$protected[3],'Prior evidence/no external inspection '.$state);
    }
}

foreach(['audit','database','programming','worker','worker-lost','runtime','source-integrity','policy','not-found'] as $fault) {
    $db=new M6DDatabase(); $a=$db->request(); $b=$db->request(); queueFinish($db,$db->claim()['lease']); $c=$db->request();
    if($fault==='worker') $db->runtime->trusted=false;
    elseif($fault==='runtime') $db->runtime->ready=false;
    elseif($fault==='source-integrity') $db->inner->read->base->revisions[100]['snapshot_hash']=str_repeat('d',64);
    elseif($fault==='policy') $db->tables['site_deployments'][$b['deployment_id']]['worker_policy_version']='unknown-policy';
    elseif($fault==='not-found') unset($db->inner->tables['site_releases'][$db->releaseId()]);
    elseif($fault==='database') $db->unavailable=true;
    else $db->hook=static function($sql,$p,$db) use($fault): void {
        if($fault==='worker-lost' && str_starts_with($sql,'SELECT * FROM site_deployment_attempts')) $db->runtime->trusted=false;
        if(str_starts_with($sql,'INSERT INTO site_events') && ($p['event_type'] ?? null)==='site_deployment_cancelled') {
            if($fault==='audit') throw new PDOException('SYNTHETIC-QUEUE-AUDIT');
            if($fault==='programming') throw new LogicException('SYNTHETIC-QUEUE-PROGRAMMING');
        }
        if($fault==='worker-lost' && str_contains($sql,'FROM site_deployments WHERE') && str_ends_with($sql,'FOR UPDATE')) $db->runtime->trusted=false;
    };
    $before=$db->snapshot(); $effects=queueEffects($db);
    $classification=match($fault) {'worker','worker-lost'=>'unauthorized','runtime'=>'future_gate_required','source-integrity','policy'=>'conflict','not-found'=>'not_found',default=>'database_failure'};
    m6dDeny(fn()=>SiteDeploymentService::claimDeployment([]),$classification);
    m6dAssert($db->snapshot()===$before && queueEffects($db)===$effects,'Systemic failure rolls back, no progress '.$fault);
}

$db=new M6DDatabase(); $a=$db->request(); $b=$db->request(); queueFinish($db,$db->claim()['lease']); $c=$db->request();
$faults=0; $db->hook=static function($sql,$p) use(&$faults): void {
    if(str_starts_with($sql,'INSERT INTO site_events') && ($p['event_type'] ?? null)==='site_deployment_cancelled' && $faults++===0) {
        $e=new PDOException('SYNTHETIC-DEADLOCK'); $e->errorInfo=['40001',1213,'PRIVATE']; throw $e;
    }
};
m6dAssert($db->claim()['lease']['deployment_id']===$c['deployment_id'] && count(queueEvents($db,$b['deployment_id']))===1,'Bounded rolled-back deadlock retry commits one disposition');

$db=new M6DDatabase(); $b=$db->request(); $lease=$db->claim()['lease'];
SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'storage_unavailable']); $db->advance(31);
$prior=$db->tables['site_deployment_attempts']; $row=$db->tables['site_deployments'][$b['deployment_id']];
$c=SiteDeploymentService::requestDeployment(2,$db->input()); $db->inner->read->users[1]['status']='inactive';
m6dAssert($db->claim()['lease']['deployment_id']===$c['deployment_id'],'Safe retry can be cancelled while later authorized work progresses');
$cancelled=$db->tables['site_deployments'][$b['deployment_id']];
foreach(['status','next_attempt_at','failure_code','failure_category','safe_summary','completed_at','updated_at'] as $key) { unset($row[$key],$cancelled[$key]); }
m6dAssert($row===$cancelled && array_intersect_key($db->tables['site_deployment_attempts'],$prior)===$prior,'Safe retry cancellation preserves original attempt/counter evidence');

$db=new M6DDatabase(); $old=[]; for($i=0;$i<27;$i++) $old[]=$db->request(); $valid=SiteDeploymentService::requestDeployment(2,$db->input());
$db->inner->read->users[1]['status']='inactive'; $attempts=$db->tables['site_deployment_attempts'];
m6dAssert(SiteDeploymentService::claimDeployment([])===null && $db->events('site_deployment_cancelled')===25
    && $db->tables['site_deployment_attempts']===$attempts,'Exactly 25 candidates examined, no unbounded scan');
m6dAssert($db->claim()['lease']['deployment_id']===$valid['deployment_id'] && $db->events('site_deployment_cancelled')===27,'Next bounded scan progresses after permanent rejections');
$before=$db->snapshot(); m6dAssert(SiteDeploymentService::claimDeployment([])===null && $db->snapshot()===$before,'Bounds/repeats preserve one-time dispositions');

echo 'M6D queue: '.$GLOBALS['m6d_assertions']." assertions PASS (modeled transactions, no MySQL locking proof).\n";
