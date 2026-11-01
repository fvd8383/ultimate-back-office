<?php
declare(strict_types=1);

function m6dNativeAuthorityOrder(string $sub,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    [$who,$phase,$order]=explode('-',$sub); $g=m6dNativeProductionActors($db,$f,$d);
    $f+=['environment'=>'production','authority'=>$who,'phase'=>$phase,'order'=>$order,'grant_approval_id'=>$g['approval_id']];
    $v=m6dNativeInput($db,$f,['deployment_approval_id'=>$g['deployment_approval_id']]);
    $op=SiteDeploymentService::requestDeployment($f['requester'],$v);
    if($phase!=='claim') $f['lease']=SiteDeploymentService::claimDeployment([])['lease'];
    if($phase==='success') $f['receipt']=SiteDeploymentService::executeDeployment($f['lease']);
    m6dNativeRecord($f); [$work,$workId]=m6dNativeChild($a,$f,'boundary');
    $observerId=(int)$observer->query('SELECT CONNECTION_ID()')->fetchColumn();
    $mutation=static function(PDO $connection) use($f,$who): void {
        if($who==='requester') $connection->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
        elseif($who==='approver') $connection->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$f['operator']]);
        else $connection->prepare("UPDATE site_approvals SET state='revoked',revoked_at=UTC_TIMESTAMP(6) WHERE id=? AND site_id=?")->execute([$f['grant_approval_id'],$f['site_id']]);
    };
    if($order==='denial') {
        if($phase!=='claim') { $work->send('START'); m6dNativeCheck($work->line(10)==='BOUNDARY','exact_boundary_reached'); }
        $before=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
        $beforeGrant=m6dNativeObservedRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id']);
        $model=m6dNativeModel($f);
        $observer->beginTransaction(); $mutation($observer);
        $work->send($phase==='claim' ? 'START' : 'CONTINUE');
        m6dNativeLockWait($db->pdo,$workId,$observerId); $observer->commit();
        $r=m6dNativeChildResult($work);
        m6dNativeCheck($phase==='claim' ? $r===null : $r===['denied'=>'unauthorized'],'denial_precedes_exact_boundary');
        $after=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
        $row=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
        m6dNativeCheck($after===$before && m6dNativeObservedRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id'])===$beforeGrant,'denied_boundary_zero_pointer_slot_fence_grant_effects');
        m6dNativeCheck(m6dNativeModel($f)===$model && m6dNativeEvents($observer,$f,'site_deployment_succeeded',$op['deployment_id'])===0,'denied_boundary_zero_external_or_success');
        if($phase==='claim') m6dNativeCheck($row['status']==='cancelled' && (int)$row['attempt_count']===0
            && m6dNativeEvents($observer,$f,'site_deployment_cancelled',$op['deployment_id'])===1,'denied_queue_atomic_cancel');
        else {
            m6dNativeCheck($row['status']===($phase==='activation' ? 'artifact_staged' : 'health_check_pending'),'post_claim_not_blindly_cancelled');
            m6dNativeExpire($observer,$f['lease']); $recovery=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
            m6dNativeCheck($recovery['claimed'] && (int)m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])['active_deployment_id']===$op['deployment_id'],'independent_recovery_retains_slot');
        }
        return ['ordering'=>m6dNativeOrder('denial-before-boundary',$observerId,$workId)];
    }
    $work->send('START'); m6dNativeCheck($work->line(10)==='LOCKED','authorized_boundary_transaction_held');
    [$loss,$lossId]=m6dNativeChild($a,$f,'mutate'); $loss->send('START');
    m6dNativeLockWait($observer,$lossId,$workId); $work->send('RELEASE');
    m6dNativeCheck($work->line(10)==='COMMITTED','boundary_commit_before_revocation');
    m6dNativeCheck(m6dNativeChildResult($loss)===['mutated'=>true],'later_authority_loss_committed');
    $committed=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
    m6dNativeCheck($committed['status']===match($phase) {'claim'=>'running','activation'=>'activation_attempted','success'=>'succeeded'},'independent_committed_boundary');
    $work->send('CONTINUE'); $r=m6dNativeChildResult($work);
    if($phase==='claim') {
        m6dNativeCheck(($r['claimed'] ?? false)===true,'authorized_claim_preserved');
        $lease=json_decode(file_get_contents(M6DNativeRuntime::ROOT.'/state/lease-'.$f['site_id'].'.json'),true,16,JSON_THROW_ON_ERROR);
        m6dNativeDeny(fn()=>SiteDeploymentService::executeDeployment($lease),'unauthorized');
    } elseif($phase==='activation') {
        m6dNativeCheck(isset($r['receipt_key']),'already_authorized_activation_can_finish');
        m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($f['lease'],$r),'unauthorized');
        m6dNativeCheck((int)m6dNativeObservedRow($observer,'site_deployment_approvals',$f,$g['deployment_approval_id'])['consumed_by_deployment_id']===$op['deployment_id'],'committed_activation_consumption_retained');
    } else {
        $snapshot=m6dNativeSnapshot($observer,$f);
        m6dNativeCheck($r['operation_status']==='succeeded' && SiteDeploymentService::completeDeploymentSuccess($f['lease'],$f['receipt'])['operation_status']==='succeeded'
            && m6dNativeSnapshot($observer,$f)===$snapshot,'committed_success_survives_later_authority_loss');
    }
    return ['ordering'=>m6dNativeOrder('boundary-before-denial',$workId,$lossId)];
}

function m6dNativeIndependent(string $sub,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    $v=m6dNativeInput($db,$f); $op=SiteDeploymentService::requestDeployment($f['requester'],$v);
    if($sub==='same-site-environments') {
        $other=$f; $other['target_id']=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'production',
            'publisher_key'=>'m6d-native-model','binding_key'=>'other-'.$f['site_id'],'binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
        $g=m6dNativeProduction($db,$other,$d); $other['requester']=$f['operator']; $d->fixture=$other;
        $second=SiteDeploymentService::requestDeployment($other['requester'],m6dNativeInput($db,$other,['deployment_approval_id'=>$g['deployment_approval_id'],'request_key'=>$v['request_key']]));
        m6dNativeCheck($second['deployment_id']!==$op['deployment_id'],'same_key_distinct_target_namespace');
        $production=SiteDeploymentService::claimDeployment([])['lease'];
        m6dNativeCheck($production['deployment_id']===$second['deployment_id'],'environment_scoped_claim');
        $d->environment='staging'; $staging=SiteDeploymentService::claimDeployment([])['lease'];
        m6dNativeCheck($staging['deployment_id']===$op['deployment_id'],'other_environment_not_borrowed');
        m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($production),'unauthorized');
        $wrong=$staging; $wrong['target_id']=$other['target_id']; m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($wrong),'unauthorized');
        m6dNativeCheck((int)m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])['active_deployment_id']===$op['deployment_id']
            && (int)m6dNativeRow($observer,'site_deployment_targets',$f,$other['target_id'])['active_deployment_id']===$second['deployment_id'],'two_environment_slots_preserved');
        return [];
    }
    $other=m6dNativeFixture($db); $otherD=new M6DNativeDependencies($db,$other); $otherD->wire();
    $other['input']=m6dNativeInput($db,$other,['request_key'=>$v['request_key']]);
    if($sub!=='occupied-target-progress') $second=SiteDeploymentService::requestDeployment($other['requester'],$other['input']);
    $d->wire();
    if($sub==='occupied-target-progress') {
        $lease=SiteDeploymentService::claimDeployment([])['lease'];
        SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
        $target=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
        $otherD->wire(); $second=SiteDeploymentService::requestDeployment($other['requester'],$other['input']); $available=SiteDeploymentService::claimDeployment([])['lease'];
        m6dNativeCheck($available['deployment_id']===$second['deployment_id'] && m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])===$target,'busy_target_preserved_available_progress');
        $wrong=$available; $wrong['attempt_id']=$lease['attempt_id']; m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($wrong),'not_found');
        return [];
    }
    // Hold site A in a real independent transaction. B must request and claim before release.
    $observer->beginTransaction(); $observer->prepare('SELECT id FROM sites WHERE id=? FOR UPDATE')->execute([$f['site_id']]);
    $firstId=(int)$observer->query('SELECT CONNECTION_ID()')->fetchColumn();
    // A is already owned so the scan does not first wait on its ordinary requested row.
    // Commit/restart with the service-created owner before measuring B's progress.
    $observer->rollBack(); SiteDeploymentService::claimDeployment([]);
    $observer->beginTransaction(); $observer->prepare('SELECT id FROM sites WHERE id=? FOR UPDATE')->execute([$f['site_id']]);
    m6dNativeRecord($other); [$p,$id]=m6dNativeChild($a,$other,'claim'); $p->send('START'); $r=m6dNativeChildResult($p);
    m6dNativeCheck($observer->inTransaction() && ($r['deployment_id'] ?? null)===$second['deployment_id'],'other_site_progress_while_first_locked');
    $observer->commit();
    m6dNativeCheck((int)m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id'])['active_deployment_id']===$op['deployment_id'],'first_site_owner_unchanged');
    return ['ordering'=>m6dNativeOrder('independent-progress',$firstId,$id)];
}

function m6dNativeRequestOutcome(string $kind,string $sub,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    $known=$kind==='restore' ? m6dNativePublish($db,$f)['deployment_id'] : null;
    $v=m6dNativeInput($db,$f,['restored_from_deployment_id'=>$known,'reason_code'=>$kind==='restore' ? 'restore_known_good' : 'publish_approved_release']);
    if(str_starts_with($sub,'duplicate')) {
        $f['input']=$v; m6dNativeRecord($f); [$loser,$loserId]=m6dNativeChild($a,$f,'duplicate',$kind); $loser->send('START');
        m6dNativeCheck($loser->line(10)==='ROLLED_BACK','actual_rollback_before_winner');
        m6dNativeCheck(m6dNativeRows($observer,'SELECT id FROM site_deployments WHERE site_id=? AND target_id=? AND request_key=?',[$f['site_id'],$f['target_id'],$v['request_key']])===[],'rolled_back_loser_not_visible');
        // Same service locks on an independent connection; no synthetic winner row.
        [$winner,$winnerId]=m6dNativeChild($a,$f,'request',$kind); $winner->send('START'); $op=m6dNativeChildResult($winner);
        m6dNativeCheck($winnerId!==$loserId && !$op['replayed'],'independent_service_winner');
        if($sub==='duplicate-winner-advanced') {
            $lease=SiteDeploymentService::claimDeployment([])['lease'];
            SiteDeploymentService::completeDeploymentSuccess($lease,SiteDeploymentService::executeDeployment($lease));
        }
        $before=m6dNativeSnapshot($observer,$f); $loser->send('RETRY'); $r=m6dNativeChildResult($loser);
        m6dNativeCheck($r['replayed'] && $r['deployment_id']===$op['deployment_id'] && $r['operation_status']===($sub==='duplicate-winner-advanced' ? 'succeeded' : 'requested')
            && m6dNativeSnapshot($observer,$f)===$before,'rollback_then_authoritative_progressed_winner_reread');
    } else {
        $op=m6dNativeRequestKind($kind,$f['requester'],$v);
        if($sub==='lost-response') { unset($op); $op=m6dNativeRequestKind($kind,$f['operator'],$v); m6dNativeCheck($op['replayed'],'discarded_request_response_recovered'); }
        elseif($sub==='failed-history') { $lease=SiteDeploymentService::claimDeployment([])['lease']; SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'health_failed']); }
        elseif($sub==='cancelled-history') {
            $observer->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$f['requester']]);
            m6dNativeCheck(SiteDeploymentService::claimDeployment([])===null,'service_cancelled_unauthorized_intent');
        } elseif($sub==='reconciliation-history') {
            $lease=SiteDeploymentService::claimDeployment([])['lease']; SiteDeploymentService::executeDeployment($lease);
            SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'outcome_unknown']);
        } elseif($sub==='access-loss') {
            m6dNativeDeny(fn()=>m6dNativeRequestKind($kind,$f['owner'],$v),'unauthorized');
            $observer->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
            $snapshot=m6dNativeSnapshot($observer,$f);
            m6dNativeDeny(fn()=>m6dNativeRequestKind($kind,$f['requester'],$v),'unauthorized');
            m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'current_access_before_history');
        }
        $before=m6dNativeSnapshot($observer,$f); $d->ready=false;
        $r=m6dNativeRequestKind($kind,$f['operator'],$v); $d->ready=true;
        $expected=match($sub) {'failed-history'=>'failed','cancelled-history'=>'cancelled','reconciliation-history'=>'reconciliation_required',default=>'requested'};
        m6dNativeCheck($r['replayed'] && $r['deployment_id']===$op['deployment_id'] && $r['operation_status']===$expected
            && m6dNativeSnapshot($observer,$f)===$before,'immutable_history_no_new_gates_or_effects');
    }
    $row=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
    $target=m6dNativeRow($observer,'site_deployment_targets',$f,$f['target_id']);
    m6dNativeCheck($r['recovery_status']===$row['recovery_status'] && $r['target']['current_deployment_id']==($target['current_deployment_id']===null ? null : (int)$target['current_deployment_id'])
        && $r['target']['pointer_version']===(int)$target['pointer_version'],'actual_recovery_and_current_target_history_dto');
    m6dNativeCheck((int)$row['requested_by_user_id']===$f['requester'] && m6dNativeEvents($observer,$f,'site_deployment_requested',$op['deployment_id'])===1,'one_original_requester_and_event');
    $snapshot=m6dNativeSnapshot($observer,$f);
    m6dNativeDeny(fn()=>m6dNativeRequestKind($kind,$f['operator'],array_replace($v,['reason_code'=>'repair_verified_release'])),'conflict');
    m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'changed_payload_never_replaces_history');
    return ['mechanism'=>str_starts_with($sub,'duplicate') ? 'injected_duplicate_real_rollback' : ($sub==='lost-response' ? 'discarded_service_response' : 'service_history')];
}
