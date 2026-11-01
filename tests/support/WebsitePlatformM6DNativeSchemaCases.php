<?php
declare(strict_types=1);

/** Assert the intended MySQL diagnostic, never publish raw SQL/server messages. */
function m6dNativeConstraint(PDO $db,callable $invalid,int $driver,string $constraint,?string $sub=null): void
{
    $expected=M6DNativeConstraintFailure::expected($driver,$constraint);
    if($sub!==null && !in_array($sub,M6DNativeContract::subcases('deployment-schema'),true))
        throw new LogicException('unsupported_constraint_subcase');
    if($db->inTransaction()) throw new LogicException('constraint_requires_own_transaction');
    if(!$db->beginTransaction()) throw new RuntimeException('constraint_transaction_failed');
    $primary=null; $rollback=null;
    try {
        try { $invalid(); }
        catch(PDOException $e) {
            $actual=M6DNativeConstraintFailure::observed($e);
            if(array_intersect_key($actual,$expected)!==$expected) throw new M6DNativeConstraintFailure($driver,$constraint,$e,$sub);
            m6dNativeCheck(true,'exact_constraint_'.$constraint);
            $matched=true;
        }
        if(!isset($matched)) throw new M6DNativeConstraintFailure($driver,$constraint,null,$sub);
    } catch(Throwable $e) { $primary=$e; }
    finally {
        try {
            if(!$db->inTransaction() || !$db->rollBack()) throw new RuntimeException('constraint_rollback_failed');
            if($primary instanceof M6DNativeConstraintFailure) $primary->rolledBack(true);
        } catch(Throwable $e) {
            $rollback=new RuntimeException('constraint_rollback_failed');
            if($primary instanceof M6DNativeConstraintFailure) $primary->rolledBack(false);
        }
    }
    if($primary!==null) throw $primary;
    if($rollback!==null) throw $rollback;
}

/** Only the three schema-negative writes outside the production store's ownership. */
function m6dNativeSchemaWrite(PDO $observer,string $table,array $values,int $id,int $siteId): void
{
    $sql=match(true) {
        $table==='site_deployment_targets' && array_keys($values)===['current_deployment_id']=>
            'UPDATE site_deployment_targets SET current_deployment_id=:current_deployment_id WHERE id=:id AND site_id=:site_id',
        $table==='site_deployment_targets' && array_keys($values)===['active_deployment_id']=>
            'UPDATE site_deployment_targets SET active_deployment_id=:active_deployment_id WHERE id=:id AND site_id=:site_id',
        $table==='site_deployment_approvals' && array_keys($values)===['consumed_by_deployment_id','consumed_at']=>
            'UPDATE site_deployment_approvals SET consumed_by_deployment_id=:consumed_by_deployment_id, consumed_at=:consumed_at WHERE id=:id AND site_id=:site_id',
        default=>throw new LogicException('unsupported_schema_negative_write'),
    };
    $s=$observer->prepare($sql); $s->execute($values+['id'=>$id,'site_id'=>$siteId]);
    m6dNativeCheck($s->rowCount()===1,'schema_negative_owned_row');
}

/** A service-created request dedicated to target ownership, never passed to claim. */
function m6dNativeUnclaimedRequest(PDO $service,PDO $observer,array $f): array
{
    $op=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($service,$f));
    m6dNativeUnreferencedRequest($observer,$f,$op['deployment_id']);
    return $op;
}
function m6dNativeUnreferencedRequest(PDO $observer,array $f,int $id): void
{
    $row=m6dNativeRow($observer,'site_deployments',$f,$id);
    m6dNativeCheck($row['status']==='requested' && (int)$row['target_id']===$f['target_id']
        && (int)$row['release_id']===$f['release_id'] && $row['operation']==='publish'
        && (int)$row['attempt_count']===0 && (int)$row['execution_count']===0 && (int)$row['recovery_count']===0,
        'target_owner_valid_unclaimed_request');
    foreach(['current_attempt_id','expected_current_deployment_id','previous_deployment_id','restored_from_deployment_id','deployment_approval_id'] as $key)
        m6dNativeCheck($row[$key]===null,'target_owner_no_outgoing_'.$key);
    // All incoming deployment FKs in canonical 025. Read every dependent table; no pointers are cleared.
    foreach([
        'site_deployment_targets'=>['current_deployment_id','active_deployment_id'],
        'site_deployments'=>['previous_deployment_id','expected_current_deployment_id','restored_from_deployment_id'],
        'site_deployment_attempts'=>['deployment_id'],
        'site_deployment_health_checks'=>['deployment_id'],
        'site_deployment_approvals'=>['expected_current_deployment_id','consumed_by_deployment_id'],
    ] as $table=>$columns) foreach($columns as $column) {
        $rows=m6dNativeRows($observer,'SELECT id FROM '.$table.' WHERE '.$column.' = :deployment_id AND site_id = :site_id',
            ['deployment_id'=>$id,'site_id'=>$f['site_id']]);
        m6dNativeCheck($rows===[],'target_owner_no_incoming_'.$table.'_'.$column);
    }
}
function m6dNativeSchemaCase(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    $op=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
    $lease=SiteDeploymentService::claimDeployment([])['lease'];
    $attempt=m6dNativeRow($observer,'site_deployment_attempts',$f,$lease['attempt_id']);
    $other=m6dNativeFixture($db); $otherD=new M6DNativeDependencies($db,$other); $otherD->wire();
    $foreign=SiteDeploymentService::requestDeployment($other['requester'],m6dNativeInput($db,$other)); $foreignLease=SiteDeploymentService::claimDeployment([])['lease']; $d->wire();
    $copy=$attempt; unset($copy['id']); $copy['attempt_number']=2; $copy['execution_number']=2;
    $recovery=array_replace($copy,['attempt_kind'=>'recovery','execution_number'=>null,'recovery_number'=>1,
        'recovery_of_attempt_id'=>$lease['attempt_id'],'recovery_trigger'=>'automatic','recovery_actor_type'=>'system','recovery_reason_code'=>'inspect_unknown_outcome']);
    $driver=1452;
    $update=static fn(string $table,array $values,int $id)=>SiteDeploymentStore::update($observer,$table,['id'=>$id,'site_id'=>$f['site_id']],$values);
    switch($sub) {
        case 'target-owner':
            // Create after all claims so no queue scan can reserve this dedicated request.
            $unclaimed=m6dNativeUnclaimedRequest($db,$observer,$f);
            $constraint='fk_sd_target'; $invalid=fn()=>$update('site_deployments',['target_id'=>$other['target_id']],$unclaimed['deployment_id']); break;
        case 'release-owner': $constraint='fk_sd_release'; $invalid=fn()=>$update('site_deployments',['release_id'=>$other['release_id']],$op['deployment_id']); break;
        case 'expected-target': $constraint='fk_sd_expected'; $invalid=fn()=>$update('site_deployments',['expected_current_deployment_id'=>$foreign['deployment_id']],$op['deployment_id']); break;
        case 'restore-target': $constraint='fk_sd_restore'; $invalid=fn()=>$update('site_deployments',['operation'=>'restore','restored_from_deployment_id'=>$foreign['deployment_id']],$op['deployment_id']); break;
        case 'current-target': $constraint='fk_sdt_current'; $invalid=fn()=>m6dNativeSchemaWrite($observer,'site_deployment_targets',['current_deployment_id'=>$foreign['deployment_id']],$f['target_id'],$f['site_id']); break;
        case 'active-target': $constraint='fk_sdt_active'; $invalid=fn()=>m6dNativeSchemaWrite($observer,'site_deployment_targets',['active_deployment_id'=>$foreign['deployment_id']],$f['target_id'],$f['site_id']); break;
        case 'request-unique':
            $second=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
            $driver=1062; $constraint='uq_sd_request'; $invalid=fn()=>$update('site_deployments',['request_key'=>$op['request_key']],$second['deployment_id']); break;
        case 'attempt-parent': $constraint='fk_sda_parent'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($copy,['deployment_id'=>$foreign['deployment_id']])); break;
        case 'attempt-original': $constraint='fk_sda_original'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($recovery,['recovery_of_attempt_id'=>$foreignLease['attempt_id']])); break;
        case 'current-attempt': $constraint='fk_sd_current_attempt'; $invalid=fn()=>$update('site_deployments',['current_attempt_id'=>$foreignLease['attempt_id']],$op['deployment_id']); break;
        case 'execution-four': $driver=3819; $constraint='chk_sda_shape'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($copy,['attempt_number'=>4,'execution_number'=>4])); break;
        case 'execution-shape': $driver=3819; $constraint='chk_sda_shape'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($copy,['recovery_number'=>1])); break;
        case 'recovery-shape': $driver=3819; $constraint='chk_sda_shape'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($recovery,['recovery_trigger'=>'operator'])); break;
        case 'counter-shape': $driver=3819; $constraint='chk_sd_counters'; $invalid=fn()=>$update('site_deployments',['attempt_count'=>7],$op['deployment_id']); break;
        case 'actor-fk': $constraint='fk_sda_actor'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_attempts',array_replace($recovery,[
            'recovery_trigger'=>'operator','operator_request_key'=>SiteServiceSupport::uuidV4(),'recovery_actor_type'=>'super_admin','recovery_authorized_by_user_id'=>PHP_INT_MAX])); break;
        case 'history-restrict': $driver=1451; $constraint='fk_sd_current_attempt'; $invalid=fn()=>$observer->prepare('DELETE FROM site_deployment_attempts WHERE id=? AND site_id=?')->execute([$lease['attempt_id'],$f['site_id']]); break;
        case 'health-attempt':
            $checkedAt=SiteBuildStore::now($observer);
            $constraint='fk_sdhc_attempt'; $invalid=fn()=>m6mysqlInsert($observer,'site_deployment_health_checks',[
                'site_id'=>$f['site_id'],'deployment_id'=>$op['deployment_id'],'deployment_attempt_id'=>$foreignLease['attempt_id'],
                'check_key'=>SiteServiceSupport::uuidV4(),'phase'=>'candidate','probe_profile'=>'release-v1','expected_release_id'=>$f['release_id'],
                'expected_absent'=>0,'duration_ms'=>1,'result'=>'pass','summary_json'=>'{}','checked_at'=>$checkedAt,'correlation_id'=>'constraint-fixture']); break;
        case 'grant-consumption':
            $g=m6dNativeProductionActors($db,$f,$d); $constraint='fk_sdap_consumed';
            $now=SiteBuildStore::now($observer);
            $invalid=fn()=>m6dNativeSchemaWrite($observer,'site_deployment_approvals',['consumed_by_deployment_id'=>$foreign['deployment_id'],'consumed_at'=>$now],$g['deployment_approval_id'],$f['site_id']); break;
        default: throw new RuntimeException('unknown_schema_subcase');
    }
    $before=m6dNativeSnapshot($observer,$f); $foreignBefore=m6dNativeSnapshot($observer,$other);
    m6dNativeConstraint($observer,$invalid,$driver,$constraint,$sub);
    m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$before,'invalid_dml_rollback_preserves_owned_history');
    m6dNativeCheck(m6dNativeSnapshot($observer,$other)===$foreignBefore,'invalid_dml_rollback_preserves_foreign_history');
    return ['constraint'=>$constraint,'driver'=>$driver];
}

function m6dNativeHistoryCase(string $sub,M6DNativeConnection $db,PDO $observer,array $f,M6DNativeDependencies $d): array
{
    $op=SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
    if($sub==='operator-deletion') {
        $lease=m6dNativeThird($db,$op); SiteDeploymentService::executeDeployment($lease);
        m6dNativeExpire($observer,$lease); $one=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
        $count=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
        m6dNativeCheck([(int)$count['attempt_count'],(int)$count['execution_count'],(int)$count['recovery_count']] === [4,3,1],'valid_recovery_after_execution_three');
        m6dNativeExpire($observer,$one['lease']); $two=m6dNativeRecover($observer,$op+['site_id'=>$f['site_id']]);
        m6dNativeCheck($two['lease']['fence_epoch']>$one['lease']['fence_epoch'],'recovery_restart_fresh_authority');
        m6dNativeDeny(fn()=>SiteDeploymentService::renewDeploymentLease($one['lease']),'conflict');
        m6dNativeExpire($observer,$two['lease']); $blocked=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
        m6dNativeCheck(!$blocked['claimed'] && SiteDeploymentService::claimDeployment([])===null,'budget_blocked_no_execution_four');
        $request=['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'retry_recovery','original_execution_id'=>$lease['attempt_id']];
        $snapshot=m6dNativeSnapshot($observer,$f);
        m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],['recovery_authorized_by_user_id'=>$f['operator']]),'invalid_request');
        m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],array_replace($request,['recovery_authorized_by_user_id'=>$f['operator']])),'invalid_request');
        m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'forged_actor_fields_rejected_before_allocation');
        foreach([0,$f['owner']] as $invalidActor) {
            $d->fixture['operator']=$invalidActor; $snapshot=m6dNativeSnapshot($observer,$f);
            m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request),$invalidActor===0 ? 'invalid_request' : 'unauthorized');
            m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'invalid_operator_zero_allocation');
        }
        $d->fixture=$f; $observer->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['operator']]);
        $snapshot=m6dNativeSnapshot($observer,$f);
        m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request),'unauthorized');
        m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'inactive_operator_zero_allocation');
        $observer->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$f['operator']]);
        $d->fixture=$f; $operator=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request);
        $attempt=m6dNativeRow($observer,'site_deployment_attempts',$f,$operator['lease']['attempt_id']);
        $counts=m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id']);
        $events=m6dNativeRows($observer,'SELECT * FROM site_events WHERE site_id=? ORDER BY id',[$f['site_id']]);
        $observer->prepare('DELETE FROM users WHERE id=?')->execute([$f['operator']]);
        $after=m6dNativeRow($observer,'site_deployment_attempts',$f,$operator['lease']['attempt_id']);
        m6dNativeCheck($after['recovery_authorized_by_user_id']===null,'historical_actor_set_null');
        $attempt['recovery_authorized_by_user_id']=null;
        m6dNativeCheck($attempt===$after && m6dNativeRow($observer,'site_deployments',$f,$op['deployment_id'])===$counts,'trigger_key_reason_producer_counts_retained');
        $afterEvents=m6dNativeRows($observer,'SELECT * FROM site_events WHERE site_id=? ORDER BY id',[$f['site_id']]);
        foreach($events as &$event) if((int)$event['actor_user_id']===$f['operator']) $event['actor_user_id']=null; unset($event);
        m6dNativeCheck($events===$afterEvents,'audit_retained_after_actor_delete');
        $snapshot=m6dNativeSnapshot($observer,$f);
        m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request),'unauthorized');
        $d->fixture['operator']=$f['owner']; m6dNativeDeny(fn()=>SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[],$request),'unauthorized');
        m6dNativeCheck(m6dNativeSnapshot($observer,$f)===$snapshot,'null_history_no_authority_transfer');
        return [];
    }
    for($i=0;$i<31;$i++) SiteDeploymentService::requestDeployment($f['requester'],m6dNativeInput($db,$f));
    $index=$sub==='queue-index' ? 'idx_sd_due' : 'idx_sd_history';
    $columns=m6dNativeRows($observer,'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',['site_deployments',$index]);
    m6dNativeCheck(array_column($columns,'COLUMN_NAME')===($sub==='queue-index' ? ['status','next_attempt_at','id'] : ['target_id','created_at','id']),'required_index_columns');
    $sql=$sub==='queue-index'
        ? "SELECT d.id,d.site_id FROM site_deployments d INNER JOIN site_deployment_targets t ON t.id=d.target_id AND t.site_id=d.site_id WHERE d.status IN ('requested','retry_wait') AND (d.next_attempt_at IS NULL OR d.next_attempt_at<=UTC_TIMESTAMP(6)) AND t.environment='staging' ORDER BY d.id LIMIT 25"
        : 'SELECT * FROM site_deployments WHERE site_id=? AND target_id=? AND id<? ORDER BY id DESC LIMIT 25';
    $params=$sub==='queue-index' ? [] : [$f['site_id'],$f['target_id'],PHP_INT_MAX];
    $plan=m6dNativeRows($observer,'EXPLAIN '.$sql,$params); $deploymentPlan=array_values(array_filter($plan,fn($r)=>in_array($r['table'],['d','site_deployments'],true)));
    m6dNativeCheck(count($deploymentPlan)===1 && in_array($index,explode(',',(string)$deploymentPlan[0]['possible_keys']),true),'service_query_has_usable_required_index');
    $rows=m6dNativeRows($observer,$sql,$params); m6dNativeCheck(count($rows)===25,'bounded_actual_query_access');
    return ['index'=>$index,'plan_sha256'=>hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR)),
        'access_type'=>$deploymentPlan[0]['type'],'chosen_index'=>$deploymentPlan[0]['key']];
}
