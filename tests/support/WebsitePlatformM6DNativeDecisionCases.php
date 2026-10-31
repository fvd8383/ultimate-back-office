<?php
declare(strict_types=1);

/** A second approved synthetic source, but a real service-created build/release. */
function m6dNativeAlternativeRelease(M6DNativeConnection $db,array $f,M6DNativeDependencies $d): array
{
    $next=$f;
    $next['revision_id']=m6mysqlInsert($db,'site_revisions',['site_id'=>$f['site_id'],'revision_number'=>2,'materiality'=>'material',
        'snapshot_schema_version'=>1,'facts_snapshot_json'=>'{"business":{"display_name":"Alternative synthetic"}}',
        'source_references_json'=>CanonicalJson::encode(['business'=>['business_id'=>$f['business_id']]]),'snapshot_hash'=>str_repeat('0',64)]);
    SiteCompositionEditor::apply($f['requester'],$next['revision_id'],['operation'=>'initialize_new','expected_snapshot_hash'=>str_repeat('0',64)]);
    $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved',review_ready_at=UTC_TIMESTAMP() WHERE id=? AND site_id=?")->execute([$next['revision_id'],$f['site_id']]);
    foreach(['customer','internal'] as $type) m6mysqlInsert($db,'site_approvals',['site_id'=>$f['site_id'],'revision_id'=>$next['revision_id'],
        'approval_type'=>$type,'actor_type'=>$type==='customer' ? 'customer' : 'internal_admin','state'=>'approved',
        'requested_at'=>SiteBuildStore::now($db),'decided_at'=>SiteBuildStore::now($db),'metadata_json'=>'{}']);
    $next['hash']=m6dNativeRows($db,'SELECT snapshot_hash FROM site_revisions WHERE id=?',[$next['revision_id']])[0]['snapshot_hash'];
    $runtime=m6mysqlWire($db); $request=m6mysqlRequest($next); $claim=SiteBuildService::claimBuild([]); $job=m6mysqlJob($db,$request['id']);
    SiteBuildService::completeBuildSuccess($claim['lease'],$runtime->receipt($job,$claim['lease']['attempt_id'],'sealed'));
    $next['release_id']=(int)m6dNativeRows($db,'SELECT id FROM site_releases WHERE build_job_id=?',[$job['id']])[0]['id'];
    $db->prepare("UPDATE site_releases SET build_profile='fixture-production-v1' WHERE id=? AND site_id=?")->execute([$next['release_id'],$f['site_id']]);
    $d->wire(); return $next;
}

function m6dNativeGrantField(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    $g=m6dNativeProductionActors($db,$f,$d); $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]);
    if($sub==='consumed-history') {
        $op=SiteDeploymentService::requestDeployment($f['requester'],$v); $l=SiteDeploymentService::claimDeployment([])['lease'];
        SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l));
        $fresh=array_replace($v,['request_key'=>SiteServiceSupport::uuidV4()]);
        m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['requester'],$fresh),'conflict');
        SiteApprovalManager::revokeProductionRelease($f['operator'],$f['site_id'],$g['deployment_approval_id'],'retained-history');
        $observer->prepare('UPDATE site_deployment_approvals SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')->execute([$g['deployment_approval_id'],$f['site_id']]);
        $before=m6dNativeSnapshot($observer,$f);
        m6dNativeCheck(SiteDeploymentService::requestDeployment($f['requester'],$v)['deployment_id']===$op['deployment_id'] && m6dNativeSnapshot($observer,$f)===$before,'consumed_expired_revoked_history_no_new_authority');
        return [];
    }
    if($sub==='missing') {
        $before=m6dNativeSnapshot($observer,$f); $v['deployment_approval_id']=null;
        m6dNativeDeny(fn()=>SiteDeploymentService::requestDeployment($f['requester'],$v),'unauthorized');
        m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$before,'missing_grant_zero_effects'); return [];
    }
    // Exercise the exact grant owner with authoritative, existing alternative records.
    // Full request/claim/activation/result gates are separately exercised by phase cases.
    $release=m6dNativeRow($observer,'site_releases',$f,$f['release_id']);
    $target=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
    $tuple=$v+['operation'=>'publish','source_revision_id'=>$f['revision_id']];
    if(in_array($sub,['release','source'],true)) {
        $other=m6dNativeAlternativeRelease($db,$f,$d);
        if($sub==='release') { $tuple['release_id']=$other['release_id']; $release=m6dNativeRow($observer,'site_releases',$f,$other['release_id']); }
        else $tuple['source_revision_id']=$other['revision_id'];
    } elseif($sub==='site') {
        $other=m6dNativeFixture($db); $tuple['site_id']=$other['site_id']; $d->wire();
    } elseif(in_array($sub,['target','environment'],true)) {
        $id=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'staging','publisher_key'=>'m6d-native-model',
            'binding_key'=>'staging-'.$f['site_id'],'binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
        $tuple['target_id']=$id;
        if($sub==='environment') $target=m6dNativeRow($observer,'site_deployment_targets',$f,$id);
    } elseif($sub==='artifact') $release['artifact_hash']=str_repeat('f',64); // Explicit artifact-identity corruption, no claim of actual bytes.
    elseif($sub==='operation') $tuple['operation']='restore';
    elseif($sub==='binding') $tuple['binding_version']=2;
    elseif($sub==='expected-version') $tuple['expected_pointer_version']=1;
    elseif($sub==='expected-pointer') {
        $op=SiteDeploymentService::requestDeployment($f['requester'],$v); $lease=SiteDeploymentService::claimDeployment([])['lease'];
        SiteDeploymentService::completeDeploymentSuccess($lease,SiteDeploymentService::executeDeployment($lease));
        $tuple['expected_current_deployment_id']=$op['deployment_id'];
    }
    $before=m6dNativeSnapshot($observer,$f);
    m6dNativeDeny(fn()=>SiteBuildStore::transaction(static function($c) use($f,$tuple,$release,$target): void {
        SiteManager::lockSite($c,$f['site_id']); SiteApprovalManager::lockProductionGrant($c,$tuple,$release,$target,SiteBuildStore::now($c));
    }),'unauthorized');
    m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$before,'exact_grant_binding_no_consumption_or_effects');
    return ['mechanism'=>'authoritative_grant_owner_tuple'];
}

function m6dNativeInvalidate(string $cause,PDO $observer,array $f,array $g): void
{
    if($cause==='expiry') $observer->prepare('UPDATE site_deployment_approvals SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')->execute([$g['deployment_approval_id'],$f['site_id']]);
    elseif($cause==='revocation') SiteApprovalManager::revokeProductionRelease($f['requester'],$f['site_id'],$g['deployment_approval_id'],'phase-revocation');
    else $observer->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$f['operator']]);
}
function m6dNativeGrantPhase(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    [$cause,$phase]=explode('-',$sub); $g=m6dNativeProductionActors($db,$f,$d);
    $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]); $op=SiteDeploymentService::requestDeployment($f['requester'],$v);
    if($phase==='claim') {
        m6dNativeInvalidate($cause,$observer,$f,$g);
        m6dNativeCheck(SiteDeploymentService::claimDeployment([])===null,'invalid_grant_not_claimed');
        $row=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
        m6dNativeCheck($row['status']==='cancelled' && (int)$row['attempt_count']===0,'invalid_grant_queue_cancel_zero_attempts');
    } else {
        $l=SiteDeploymentService::claimDeployment([])['lease'];
        if($phase==='activation') {
            $d->model->afterCall=static function($method) use($d,$cause,$observer,$f,$g): void {
                if($method==='inspectCurrentRelease') { $d->model->afterCall=null; m6dNativeInvalidate($cause,$observer,$f,$g); }
            };
            m6dNativeDeny(fn()=>SiteDeploymentService::executeDeployment($l),'unauthorized');
        } else {
            $r=SiteDeploymentService::executeDeployment($l); m6dNativeInvalidate($cause,$observer,$f,$g);
            m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'unauthorized');
            m6dNativeExpire($observer,$l); $rec=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
            $done=SiteDeploymentService::completeDeploymentRecovery($rec['lease'],SiteDeploymentService::reconcileDeployment($rec['lease']));
            m6dNativeCheck($done['operation_status']==='failed' && (m6dNativeModel($f)['pointers'][$f['target_id']] ?? null)===null,'invalid_grant_recovery_only_compensates');
        }
    }
    $grant=m6dNativeRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id']);
    m6dNativeCheck($grant['consumed_by_deployment_id']==($phase==='success' ? $op['deployment_id'] : null),'consumption_only_at_activation_boundary');
    m6dNativeCheck(m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])['current_deployment_id']===null
        && m6dNativeEvents($observer,$f,'site_deployment_succeeded',$op['deployment_id'])===0,'denied_grant_no_new_success');
    if($phase!=='success') m6dNativeCheck(!in_array('activate',m6dNativeModel($f)['calls'],true),'invalid_grant_no_activation');
    return ['mechanism'=>$cause==='expiry' ? 'controlled_expiry_metadata' : 'current_grant_authority'];
}

function m6dNativeRestoreCase(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    if($sub==='production-fresh-grant') {
        $g=m6dNativeProductionActors($db,$f,$d); $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]);
        $known=SiteDeploymentService::requestDeployment($f['requester'],$v); $l=SiteDeploymentService::claimDeployment([])['lease'];
        SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l));
        $v=m6dNativeInput($db,$f,['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good','deployment_approval_id'=>$g['deployment_approval_id']]);
        m6dNativeDeny(fn()=>SiteDeploymentService::requestRestore($f['requester'],$v),'unauthorized');
        $fresh=m6dNativeGrant($db,$f,['operation'=>'restore','restored_from_deployment_id'=>$known['deployment_id']]);
        $v['deployment_approval_id']=$fresh['deployment_approval_id']; $op=SiteDeploymentService::requestRestore($f['requester'],$v);
        $l=SiteDeploymentService::claimDeployment([])['lease']; SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l));
        SiteApprovalManager::revokeProductionRelease($f['operator'],$f['site_id'],$fresh['deployment_approval_id'],'restore-history');
        $observer->prepare('UPDATE site_deployment_approvals SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')->execute([$fresh['deployment_approval_id'],$f['site_id']]);
        $before=m6dNativeSnapshot($observer,$f);
        m6dNativeCheck(SiteDeploymentService::requestRestore($f['requester'],$v)['deployment_id']===$op['deployment_id'] && m6dNativeSnapshot($observer,$f)===$before,'fresh_restore_grant_then_revoked_history'); return [];
    }
    $known=m6dNativePublish($db,$f); $v=m6dNativeInput($db,$f,['restored_from_deployment_id'=>$known['deployment_id'],'reason_code'=>'restore_known_good']);
    $expected='invalid_transition';
    if($sub==='never-successful') {
        $pending=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f)); $v['restored_from_deployment_id']=$pending['deployment_id'];
    } elseif(in_array($sub,['foreign-site','wrong-target'],true)) {
        $other=m6dNativeFixture($db); $otherD=new M6DNativeDependencies($db,$other); $otherD->wire(); $foreign=m6dNativePublish($db,$other); $d->wire();
        $v['restored_from_deployment_id']=$foreign['deployment_id']; $expected='not_found';
        if($sub==='wrong-target') {
            // Existing same-site source on its other environment is meaningful target history.
            $other=$f; $other['target_id']=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'production','publisher_key'=>'m6d-native-model',
                'binding_key'=>'restore-other-'.$f['site_id'],'binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
            $g=m6dNativeProduction($db,$other,$d); $d->fixture=$other;
            $p=SiteDeploymentService::requestDeployment($f['operator'],m6dNativeInput($db,$other,['deployment_approval_id'=>$g['deployment_approval_id']]));
            $l=SiteDeploymentService::claimDeployment([])['lease']; SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l));
            $d->environment='staging'; $d->fixture=$f; $v['restored_from_deployment_id']=$p['deployment_id']; $expected='invalid_transition';
        }
    } elseif($sub==='artifact-identity') { $d->artifactOverride=['artifact_hash'=>str_repeat('f',64)]; $expected='conflict'; }
    elseif($sub==='tenant-prohibited') { $observer->prepare("UPDATE sites SET lifecycle_status='suspended' WHERE id=?")->execute([$f['site_id']]); }
    elseif($sub==='business-prohibited') { $observer->prepare('UPDATE businesses SET is_suspended=1 WHERE id=?')->execute([$f['business_id']]); $expected='conflict'; }
    elseif($sub==='asset-rights') { $observer->prepare("UPDATE site_assets SET rights_classification='prohibited' WHERE id=? AND site_id=?")->execute([$f['asset_id'],$f['site_id']]); $expected='conflict'; }
    elseif($sub==='target-disabled') { $observer->prepare('UPDATE site_deployment_targets SET enabled=0 WHERE id=? AND site_id=?')->execute([$f['target_id'],$f['site_id']]); $expected='future_gate_required'; }
    else {
        $observer->prepare("UPDATE site_revisions SET lifecycle_status='superseded' WHERE id=? AND site_id=?")->execute([$f['revision_id'],$f['site_id']]);
        $observer->prepare("UPDATE site_approvals SET state='superseded' WHERE site_id=? AND approval_type IN ('internal','customer')")->execute([$f['site_id']]);
        $original=m6dNativeRow($observer,'site_deployments',$f,$known['deployment_id']);
        $op=SiteDeploymentService::requestRestore($f['requester'],$v); $l=SiteDeploymentService::claimDeployment([])['lease'];
        $done=SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l));
        m6dNativeCheck($done['operation_status']==='succeeded' && $op['deployment_id']!==$known['deployment_id'] && m6dNativeRow($observer,'site_deployments',$f,$known['deployment_id'])===$original,'historical_restore_preserves_source');
        m6dNativeCheck(m6dNativeRows($observer,"SELECT id FROM site_approvals WHERE site_id=? AND state<>'superseded'",[$f['site_id']])===[],'restore_never_revives_approval'); return [];
    }
    $before=m6dNativeSnapshot($observer,$f); m6dNativeDeny(fn()=>SiteDeploymentService::requestRestore($f['requester'],$v),$expected);
    m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$before,'invalid_restore_no_publisher_or_new_intent'); return [];
}

function m6dNativeHealthCase(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    $production=in_array($sub,['activation-audit-grant','result-audit-grant','third-grant-expired','third-grant-revoked'],true);
    $g=$production ? m6dNativeProductionActors($db,$f,$d) : null;
    $known=in_array($sub,['candidate-failure','active-health-failure','compensation-failed','compensation-unknown'],true) ? m6dNativePublish($db,$f) : null;
    $restore=$known!==null;
    $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id'] ?? null,
        'restored_from_deployment_id'=>$known['deployment_id'] ?? null,'reason_code'=>$restore ? 'restore_known_good' : 'publish_approved_release']);
    $op=m6dNativeRequestKind($restore ? 'restore' : 'publish',$f['requester'],$v);
    $l=str_starts_with($sub,'third-') ? m6dNativeThird($db,$op) : SiteDeploymentService::claimDeployment([])['lease'];
    $prior=m6dNativeModel($f)['pointers'];
    if($sub==='candidate-failure') $d->model->healthOverrides=['candidate'=>['result'=>'fail']];
    if($sub==='activation-audit-grant') {
        $db->faultEvent='site_activation_attempted'; m6dNativeDeny(fn()=>SiteDeploymentService::executeDeployment($l),'database_failure'); $db->faultEvent=null;
        m6dNativeCheck(m6dNativeRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id'])['consumed_by_deployment_id']===null
            && m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id'])['activation_fence']===null
            && !in_array('activate',m6dNativeModel($f)['calls'],true),'activation_audit_rollback_restores_grant_before_external_action'); return [];
    }
    $oldContext=null;
    if($sub==='newer-winner') $d->model->afterCall=static function($method,$args) use(&$oldContext): void { if($method==='activateRelease') $oldContext=$args[2]; };
    $r=SiteDeploymentService::executeDeployment($l);
    if($sub==='candidate-failure') {
        m6dNativeCheck($r['completed']['operation_status']==='failed' && m6dNativeModel($f)['pointers']===$prior,'restore_candidate_failure_no_switch'); return [];
    }
    if($sub==='result-audit-grant') {
        $opBefore=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
        $targetBefore=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
        $siteBefore=m6dNativeRows($observer,'SELECT * FROM sites WHERE id=?',[$f['site_id']]);
        $revisionBefore=m6dNativeRows($observer,'SELECT * FROM site_revisions WHERE id=?',[$f['revision_id']]);
        $healthBefore=m6dNativeRows($observer,'SELECT * FROM site_deployment_health_checks WHERE deployment_id=? ORDER BY id',[$op['deployment_id']]);
        $db->faultEvent='site_deployment_succeeded';
        m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'database_failure'); $db->faultEvent=null;
        // Health probes are model observations; owning DB state is checked separately.
        m6dNativeCheck(m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])['current_deployment_id']===null
            && (int)m6dNativeRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id'])['consumed_by_deployment_id']===$op['deployment_id']
            && m6dNativeEvents($observer,$f,'site_deployment_succeeded',$op['deployment_id'])===0,'result_rollback_keeps_activation_consumption_only');
        m6dNativeCheck($opBefore===m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id'])
            && $targetBefore===m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])
            && $siteBefore===m6dNativeRows($observer,'SELECT * FROM sites WHERE id=?',[$f['site_id']])
            && $revisionBefore===m6dNativeRows($observer,'SELECT * FROM site_revisions WHERE id=?',[$f['revision_id']])
            && $healthBefore===m6dNativeRows($observer,'SELECT * FROM site_deployment_health_checks WHERE deployment_id=? ORDER BY id',[$op['deployment_id']]),'result_transaction_lifecycle_health_pointer_atomic_rollback');
    } elseif($sub==='newer-winner') {
        $d->model->afterCall=null;
        m6dNativeExpire($observer,$l); $oldRecovery=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
        m6dNativeExpire($observer,$oldRecovery['lease']); $newRecovery=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
        SiteDeploymentService::completeDeploymentRecovery($newRecovery['lease'],SiteDeploymentService::reconcileDeployment($newRecovery['lease']));
        $winner=m6dNativePublish($db,$f); $before=m6dNativeSnapshot($observer,$f);
        m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'conflict');
        m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentRecovery($oldRecovery['lease'],$r),'conflict');
        m6dNativeDeny(fn()=>$d->model->activateRelease([],['release_key'=>null,'artifact_hash'=>null,'deployment_id'=>null],$oldContext),'conflict');
        $old=SiteDeploymentService::deploymentForActor($f['requester'],$op['deployment_id']);
        m6dNativeCheck(!$old['is_current'] && $old['target']['current_deployment_id']===$winner['deployment_id'] && m6dNativeSnapshot($observer,$f)===$before,'old_result_cannot_overwrite_newer_winner');
        m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($l),'conflict'); return [];
    } else {
        if($sub==='active-health-failure' || str_starts_with($sub,'health-')) {
            $d->model->healthOverrides=['active'=>$sub==='active-health-failure' ? ['result'=>'fail'] : m6dNativeHealthFault($sub)];
            if($sub==='health-site') { m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'invalid_request'); $failed=SiteDeploymentService::completeDeploymentFailure($l,['code'=>'health_failed']); }
            else $failed=SiteDeploymentService::completeDeploymentSuccess($l,$r);
            m6dNativeCheck($failed['operation_status']==='reconciliation_required','active_health_requires_reconciliation');
        }
        elseif($sub==='third-grant-expired') m6dNativeInvalidate('expiry',$observer,$f,$g);
        elseif($sub==='third-grant-revoked') m6dNativeInvalidate('revocation',$observer,$f,$g);
        else $observer->prepare("UPDATE sites SET lifecycle_status='suspended' WHERE id=?")->execute([$f['site_id']]);
    }
    m6dNativeExpire($observer,$l); $recovery=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
    if($sub==='compensation-failed') $d->model->compensationFails=true;
    if($sub==='compensation-unknown') $d->model->unknown=true;
    $beforeCalls=m6dNativeModel($f)['calls'];
    $done=SiteDeploymentService::completeDeploymentRecovery($recovery['lease'],SiteDeploymentService::reconcileDeployment($recovery['lease']));
    $blocked=in_array($sub,['compensation-failed','compensation-unknown'],true);
    $expected=$sub==='result-audit-grant' ? 'succeeded' : ($blocked ? 'reconciliation_required' : 'failed');
    m6dNativeCheck($done['operation_status']===$expected,'model_compensation_database_disposition');
    $afterCalls=m6dNativeModel($f)['calls'];
    m6dNativeCheck(count(array_intersect($beforeCalls,['stage','activate']))===count(array_intersect($afterCalls,['stage','activate'])),'recovery_never_stages_or_activates');
    if($blocked) m6dNativeCheck($done['target']['active_deployment_id']===$op['deployment_id'] && SiteDeploymentService::claimDeployment([])===null,'unresolved_effects_keep_slot');
    elseif($sub!=='result-audit-grant') {
        $identity=static fn($p)=>$p===null ? null : array_intersect_key($p,array_flip(['release_key','artifact_hash','deployment_id']));
        m6dNativeCheck($identity(m6dNativeModel($f)['pointers'][$f['target_id']] ?? null)===$identity($prior[$f['target_id']] ?? null)
            && $done['target']['current_deployment_id']===($known['deployment_id'] ?? null),'verified_model_prior_identity');
        if($known===null) m6dNativeCheck(m6dNativeRows($observer,"SELECT id FROM site_deployment_health_checks WHERE deployment_id=? AND expected_absent=1 AND http_status=503 AND result='pass'",[$op['deployment_id']])!==[],'first_deployment_absence_evidence');
    }
    $snapshot=m6dNativeSnapshot($observer,$f); SiteDeploymentService::completeDeploymentRecovery($recovery['lease'],['receipt_key'=>$op['deployment_key']]);
    m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'recovery_completion_replay_zero_effects'); return [];
}
function m6dNativeHealthFault(string $sub): array
{
    return match($sub) {
        'health-status'=>['http_status'=>302], 'health-site'=>['site_key'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],
        'health-release'=>['release_key'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],'health-artifact'=>['artifact_hash'=>str_repeat('e',64)],
        'health-marker'=>['home_marker'=>false],'health-page'=>['page_sanity'=>false],'health-css'=>['css_integrity'=>false],
        'health-media'=>['media_integrity'=>false],'health-origin'=>['origin_integrity'=>false],'health-tls'=>['tls_verified'=>false],
        'health-redirect'=>['redirected'=>true],'health-cache'=>['cache_bypassed'=>false],'health-no-store'=>['no_store'=>false],
        'health-timeout'=>['http_status'=>0,'result'=>'fail'],default=>throw new RuntimeException('unknown_health_fault'),
    };
}
