<?php

declare(strict_types=1);
// Included only by the verified disposable native-MySQL harness. DML fixtures are
// rolled back. These inert deployment rows exercise schema, never orchestration.
$other=m6mysqlFixture($db);
$db->beginTransaction();
try{
    $now=SiteBuildStore::now($db);
    $operatorId=m6mysqlInsert($db,'users',['first_name'=>'Synthetic','last_name'=>'Recovery actor',
        'email'=>bin2hex(random_bytes(12)).'@example.invalid','status'=>'active']);
    $original=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>$claim['lease']['attempt_id']]);
    $recovery=$original;unset($recovery['id']);
    $recovery=array_replace($recovery,['attempt_number'=>2,'attempt_kind'=>'recovery','execution_number'=>null,'recovery_number'=>1,
        'recovery_of_attempt_id'=>(int)$original['id'],'recovery_trigger'=>'operator','operator_request_key'=>SiteServiceSupport::uuidV4(),
        'recovery_authorized_by_user_id'=>$operatorId,'recovery_actor_type'=>'internal_admin','recovery_reason_code'=>'inspect_unknown_outcome',
        'lease_token_hash'=>str_repeat('1',64),'external_reference'=>null]);
    SiteBuildContract::claimActor(array_intersect_key($recovery,array_flip(['attempt_kind','recovery_trigger','operator_request_key',
        'recovery_authorized_by_user_id','recovery_actor_type','recovery_reason_code'])),
        ['acting_user_id'=>$operatorId,'actor_type'=>'internal_admin','is_internal_admin'=>true]);
    $recoveryId=m6mysqlInsert($db,'site_build_attempts',$recovery);
    $targetId=m6mysqlInsert($db,'site_deployment_targets',['site_id'=>$fixture['site_id'],'environment'=>'staging',
        'publisher_key'=>'synthetic-disabled','binding_key'=>'synthetic-'.bin2hex(random_bytes(8)),'binding_version'=>1,'updated_at'=>$now]);
    mysqlCheck((int)$db->query('SELECT enabled FROM site_deployment_targets WHERE id='.$targetId)->fetchColumn()===0,'Real disabled target default');
    $deploymentPolicy=SiteBuildContract::POLICY;$deploymentPolicy['execution_timeout_seconds']=300;
    $deployment=['site_id'=>$fixture['site_id'],'deployment_key'=>SiteServiceSupport::uuidV4(),'target_id'=>$targetId,
        'release_id'=>$success['release']['id'],'source_revision_id'=>$fixture['revision_id'],'operation'=>'publish',
        'request_key'=>SiteServiceSupport::uuidV4(),'request_payload_hash'=>str_repeat('f',64),'request_payload_json'=>'{}',
        'reason_code'=>'synthetic_schema_only','expected_pointer_version'=>0,'actor_type'=>'system','correlation_id'=>'synthetic',
        'binding_version'=>1,'worker_policy_version'=>'deployment-worker-v1','worker_policy_json'=>CanonicalJson::encode($deploymentPolicy),'updated_at'=>$now];
    $deploymentId=m6mysqlInsert($db,'site_deployments',$deployment);
    $da=$original;unset($da['id'],$da['build_job_id'],$da['candidate_storage_key'],$da['candidate_artifact_hash']);
    $da=array_replace($da,['deployment_id'=>$deploymentId,'fence_epoch'=>1,'external_reference'=>null]);
    $executionId=m6mysqlInsert($db,'site_deployment_attempts',$da);
    $dra=$recovery;unset($dra['id'],$dra['build_job_id'],$dra['candidate_storage_key'],$dra['candidate_artifact_hash']);
    $dra=array_replace($dra,['deployment_id'=>$deploymentId,'fence_epoch'=>2,'recovery_of_attempt_id'=>$executionId]);
    $deploymentRecoveryId=m6mysqlInsert($db,'site_deployment_attempts',$dra);
    $validationId=m6mysqlInsert($db,'site_release_validations',['site_id'=>$fixture['site_id'],'release_id'=>$success['release']['id'],
        'build_attempt_id'=>$recoveryId,'validation_key'=>SiteServiceSupport::uuidV4(),'validation_phase'=>'reconcile',
        'validator_version'=>'synthetic','result'=>'pass','summary_json'=>'{}','checked_at'=>$now,'correlation_id'=>'synthetic']);
    $healthId=m6mysqlInsert($db,'site_deployment_health_checks',['site_id'=>$fixture['site_id'],'deployment_id'=>$deploymentId,
        'deployment_attempt_id'=>$deploymentRecoveryId,'check_key'=>SiteServiceSupport::uuidV4(),'phase'=>'candidate',
        'probe_profile'=>'synthetic_no_probe','expected_release_id'=>$success['release']['id'],'duration_ms'=>0,
        'result'=>'pass','summary_json'=>'{}','checked_at'=>$now,'correlation_id'=>'synthetic']);
    // The schema intentionally does not encode production approval authority.
    // An internal approval is used solely to test the extension FK, in rollback.
    $approval=(int)SiteBuildStore::one($db,"SELECT id FROM site_approvals WHERE revision_id=:revision AND approval_type='internal'",['revision'=>$fixture['revision_id']])['id'];
    $approvalId=m6mysqlInsert($db,'site_deployment_approvals',['site_id'=>$fixture['site_id'],'approval_id'=>$approval,
        'release_id'=>$success['release']['id'],'source_revision_id'=>$fixture['revision_id'],'target_id'=>$targetId,
        'operation'=>'publish','artifact_hash'=>$success['release']['artifact_hash'],'expected_pointer_version'=>0,
        'binding_version'=>1,'expires_at'=>SiteBuildStore::after($now,300),'correlation_id'=>'synthetic']);
    $samples=['site_build_jobs'=>$jobId,'site_build_attempts'=>$recoveryId,'site_releases'=>$success['release']['id'],
        'site_release_validations'=>$validationId,'site_deployment_targets'=>$targetId,'site_deployments'=>$deploymentId,
        'site_deployment_attempts'=>$deploymentRecoveryId,'site_deployment_health_checks'=>$healthId,'site_deployment_approvals'=>$approvalId];
    // Every FK, including deferred nullable/circular links, is exercised with a
    // violating tuple; other same-row shape predicates remain valid.
    $fks=SiteBuildStore::rows($db,'SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND ORDINAL_POSITION=1');
    foreach($fks as$fk){
        if(!isset($samples[$fk['TABLE_NAME']]))continue;
        $table=$fk['TABLE_NAME'];$column=$fk['COLUMN_NAME'];$rowId=$samples[$table];
        $extra=match($column){
            'consumed_by_deployment_id'=>', consumed_at=UTC_TIMESTAMP(6)',
            'restored_from_deployment_id'=>", operation='restore'",
            default=>'',
        };
        mysqlReject($db,fn()=>$db->prepare('UPDATE '.$table.' SET '.$column.'=9223372036854775807'.$extra.' WHERE id=?')->execute([$rowId]),[1451,1452]);
    }
    foreach(['site_build_jobs'=>$jobId,'site_deployments'=>$deploymentId]as$table=>$id){
        foreach(['attempt_count=999','max_execution_attempts=4','execution_count=4, attempt_count=4',
            'max_automatic_recoveries=3','automatic_recovery_count=3','automatic_recovery_count=1, recovery_count=0',
            "recovery_status='invalid'","status='invalid'"]as$set)mysqlReject($db,fn()=>$db->exec('UPDATE '.$table.' SET '.$set.' WHERE id='.$id),[3819]);
    }
    mysqlReject($db,fn()=>$db->exec('UPDATE site_build_jobs SET business_id=NULL WHERE id='.$jobId),[3819]);
    foreach(['site_build_attempts'=>$recoveryId,'site_deployment_attempts'=>$deploymentRecoveryId]as$table=>$id){
        foreach(['attempt_number=0','execution_number=1','recovery_number=NULL','recovery_number=0','recovery_of_attempt_id=NULL',
            'recovery_trigger=NULL','recovery_actor_type=NULL','recovery_reason_code=NULL','operator_request_key=NULL',
            "attempt_kind='invalid'","recovery_actor_type='customer'","status='invalid'",'lease_expires_at=leased_at',
            'lease_expires_at=deadline_at+INTERVAL 1 SECOND']as$set)mysqlReject($db,fn()=>$db->exec('UPDATE '.$table.' SET '.$set.' WHERE id='.$id),[3819]);
    }
    foreach(['release_id=NULL, build_attempt_id=NULL',"validation_phase='invalid'","result='invalid'",
        "summary_json=JSON_OBJECT('large',REPEAT('x',16385))"]as$set)mysqlReject($db,fn()=>$db->exec('UPDATE site_release_validations SET '.$set.' WHERE id='.$validationId),[3819]);
    foreach(["environment='other'","reconciliation_status='other'",'enabled=2']as$set)mysqlReject($db,fn()=>$db->exec('UPDATE site_deployment_targets SET '.$set.' WHERE id='.$targetId),[3819]);
    mysqlReject($db,fn()=>$db->exec("UPDATE site_deployments SET operation='restore' WHERE id=".$deploymentId),[3819]);
    mysqlReject($db,fn()=>$db->exec("UPDATE site_deployments SET restored_from_deployment_id=".$deploymentId.' WHERE id='.$deploymentId),[3819]);
    foreach(["phase='invalid'","result='invalid'",'expected_release_id=NULL','expected_absent=1',
        "summary_json=JSON_OBJECT('large',REPEAT('x',4097))"]as$set)mysqlReject($db,fn()=>$db->exec('UPDATE site_deployment_health_checks SET '.$set.' WHERE id='.$healthId),[3819]);
    foreach(["operation='invalid'",'consumed_at=UTC_TIMESTAMP(6)','consumed_by_deployment_id='.$deploymentId]as$set)mysqlReject($db,fn()=>$db->exec('UPDATE site_deployment_approvals SET '.$set.' WHERE id='.$approvalId),[3819]);
    mysqlReject($db,fn()=>$db->exec("UPDATE site_deployment_attempts SET receipt_json=JSON_OBJECT('large',REPEAT('x',16385)) WHERE id=".$deploymentRecoveryId),[3819]);
    $duplicate=$deployment;$duplicate['deployment_key']=SiteServiceSupport::uuidV4();
    mysqlReject($db,fn()=>m6mysqlInsert($db,'site_deployments',$duplicate),[1062]);
    $duplicate['request_key']=SiteServiceSupport::uuidV4();
    mysqlCheck(m6mysqlInsert($db,'site_deployments',$duplicate)>0,'Same payload digest with new request key is not a duplicate identity');
    // Both NULL-able operator actor FKs perform SET NULL without CHECK failure.
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$operatorId]);
    foreach(['site_build_attempts'=>$recoveryId,'site_deployment_attempts'=>$deploymentRecoveryId]as$table=>$id){
        $row=$db->query('SELECT * FROM '.$table.' WHERE id='.$id)->fetch();
        mysqlCheck($row['recovery_authorized_by_user_id']===null&&$row['recovery_trigger']==='operator'
            &&$row['recovery_actor_type']==='internal_admin'&&$row['operator_request_key']!==null,'Historical actor deletion preserves '.$table);
    }
    // Safe committed history remains available after real original requester deletion.
    $originalRequester=m6mysqlJob($db,$jobId)['requested_by_user_id'];
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$originalRequester]);
    m6mysqlWire($db);
    // Pure read after deletion; service transaction is tested after this fixture rolls back.
    mysqlCheck(m6mysqlJob($db,$jobId)['requested_by_user_id']===null,'Real original-requester SET NULL retains successful job');
}finally{
    $db->rollBack();m6mysqlWire($db);
}
