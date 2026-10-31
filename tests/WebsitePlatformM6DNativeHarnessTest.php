<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DNativeFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeExpansion.php';
$assertions=0;
$check=static function(bool $ok,string $code) use(&$assertions): void { $assertions++; if(!$ok) throw new RuntimeException($code); };
$deny=static function(callable $call,string $expected) use($check): void {
    try { $call(); } catch(RuntimeException $e) { $check($e->getMessage()===$expected,'expected_'.$expected.'_got_'.$e->getMessage()); return; }
    throw new RuntimeException('missing_expected_failure');
};
$identity=['profile'=>M6DNativeContract::PROFILE,'source_sha'=>str_repeat('a',40),'image_id'=>'sha256:'.str_repeat('b',64),'run_token'=>str_repeat('c',32)];
try {
    foreach(['pass','early','malformed','wrong','partial','diagnostic','timeout','overflow'] as $mode) {
        $p=new M6CNativeProcess([PHP_BINARY,__DIR__.'/support/WebsitePlatformM6DNativeSyntheticChild.php',$mode],$mode==='timeout' ? 1 : 5,$mode==='overflow' ? 1024 : 16384);
        $run=static function() use($p,$identity): mixed { M6DNativeProtocol::ready($p); $p->send('START'); return M6DNativeProtocol::result($p,$identity); };
        if($mode==='pass') $check($run()===['denied'=>'unauthorized'],'framed_exact_denial');
        else $deny($run,match($mode) {'early','partial'=>'native_child_before_barrier','malformed'=>'child_result_json','wrong'=>'child_result_binding',
            'diagnostic'=>'native_child_diagnostics','timeout'=>'native_child_timeout','overflow'=>'native_child_output_limit'});
        $cleanup=M6CNativeProcess::cleanup(); foreach($cleanup as $record) $check(!$record['running'],'owned_child_reaped');
    }
    foreach(['profile','source_sha','image_id','run_token'] as $key) {
        $wrong=$identity; $wrong[$key]='wrong'; $deny(fn()=>M6DNativeProtocol::decode(M6DNativeProtocol::frame($wrong,null),$identity),'child_result_binding');
    }
    $p=new M6CNativeProcess([PHP_BINARY,__DIR__.'/support/WebsitePlatformM6DNativeSyntheticChild.php','barriers'],5,16384);
    M6DNativeProtocol::ready($p); $p->send('START');
    foreach(['LOCKED'=>'RELEASE','COMMITTED'=>'CONTINUE','BOUNDARY'=>'CONTINUE','ROLLED_BACK'=>'RETRY'] as $marker=>$reply) {
        $check($p->line(2)===$marker,'actual_shared_barrier_'.$marker); $p->send($reply);
    }
    $check(M6DNativeProtocol::result($p,$identity)===['denied'=>'unauthorized'],'all_barriers_complete'); M6CNativeProcess::cleanup();
    $p=new M6CNativeProcess([PHP_BINARY,__DIR__.'/support/WebsitePlatformM6DNativeSyntheticChild.php','barriers'],5,16384);
    M6DNativeProtocol::ready($p); $p->send('START'); $check($p->line(2)==='LOCKED','negative_barrier_reached'); $p->send('WRONG');
    $deny(fn()=>M6DNativeProtocol::result($p,$identity),'native_child_before_barrier');
    $observed=$p->observation(); $check($observed['exit']===1 && str_contains($observed['stderr'],'barrier_release'),'wrong_release_preserves_failure'); M6CNativeProcess::cleanup();
    foreach([['token'=>str_repeat('a',64)],['value'=>"private\ncontrol"],['value'=>str_repeat('x',201)]] as $value)
        $deny(fn()=>M6DNativeProtocol::decode(M6DNativeProtocol::frame($identity,$value),$identity),isset($value['token']) ? 'child_private_key' : 'child_private_value');
    $deny(fn()=>M6DNativeProtocol::decode("RESULT {}\x1b",$identity),'child_result_shape');
    $duplicate='RESULT {"identity":'.json_encode($identity).',"value":null,"value":null}';
    $deny(fn()=>M6DNativeProtocol::decode($duplicate,$identity),'child_result_encoding');
    $deny(fn()=>m6dNativeDeny(static fn()=>throw new SiteServiceException('database_failure','PRIVATE'),'unauthorized'),'exact_denial_unauthorized');
    foreach(['correct','driver','state','constraint'] as $fault) {
        $fake=new M6DDatabase(); $before=$fake->snapshot();
        $invalid=static function() use($fault): void { $e=new PDOException('PRIVATE SQL');
            $e->errorInfo=[$fault==='state' ? 'HY000' : '23000',$fault==='driver' ? 1062 : 1452,$fault==='constraint' ? 'other_constraint' : 'fk_sda_actor']; throw $e; };
        if($fault==='correct') m6dNativeConstraint($fake,$invalid,1452,'fk_sda_actor');
        else $deny(fn()=>m6dNativeConstraint($fake,$invalid,1452,'fk_sda_actor'),'exact_constraint_fk_sda_actor');
        $check(!$fake->inTransaction() && $fake->snapshot()===$before,'constraint_helper_rollback_'.$fault);
    }
    $private=sys_get_temp_dir().'/ubo-m6d-protocol-'.bin2hex(random_bytes(12)).'.json';
    try {
        M6DNativeProtocol::privateRecord($private,['site_id'=>1,'input'=>['request_key'=>'synthetic']]);
        $check(json_decode(file_get_contents($private),true)['site_id']===1 && filesize($private)<8192,'actual_private_fixture_record');
        $deny(fn()=>M6DNativeProtocol::privateRecord($private.'-oversize',['value'=>str_repeat('x',8192)]),'fixture_record_bound');
        $check(!file_exists($private.'-oversize'),'oversize_no_partial_fixture');
    } finally { if(is_file($private)) unlink($private); }
    foreach(['publish','restore'] as $kind) foreach([false,true] as $advance) {
        $fake=new M6DDatabase(); $known=$kind==='restore' ? $fake->publish()['deployment_id'] : null;
        $v=$fake->input(['restored_from_deployment_id'=>$known,'reason_code'=>$kind==='restore' ? 'restore_known_good' : 'publish_approved_release']);
        $connection=new M6DNativeConnection($fake); $connection->duplicateOnce=true;
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$connection);
        $winner=null; $rollback=false;
        $connection->afterRollback=static function() use($fake,$connection,$kind,$v,$advance,&$winner,&$rollback,$check): void {
            $rollback=!$connection->inTransaction(); $check($rollback,'wrapper_rolled_back_before_winner');
            $winner=m6dNativeRequestKind($kind,1,$v);
            if($advance) { $l=$fake->claim()['lease']; SiteDeploymentService::completeDeploymentSuccess($l,SiteDeploymentService::executeDeployment($l)); }
        };
        $result=m6dNativeRequestKind($kind,1,$v);
        $check($rollback && $result['replayed'] && $result['deployment_id']===$winner['deployment_id']
            && $result['operation_status']===($advance ? 'succeeded' : 'requested'),'actual_adapter_duplicate_winner_reread');
        $check($fake->events('site_deployment_requested')===($kind==='restore' ? 2 : 1),'duplicate_one_request_event');
        $row=$fake->tables['site_deployments'][$result['deployment_id']];
        $check($row['requested_by_user_id']===1 && $row['execution_count']===($advance ? 1 : 0),'duplicate_preserved_intent_effect_counts');
    }
    // Exercise the actual authored health fault definitions through the unchanged service locally.
    foreach(M6DNativeContract::subcases('health-compensation') as $sub) {
        if(!str_starts_with($sub,'health-')) continue;
        $fake=new M6DDatabase(); $op=$fake->request(); $l=$fake->claim()['lease']; $r=SiteDeploymentService::executeDeployment($l);
        $fake->runtime->publisher->healthOverrides=['active'=>m6dNativeHealthFault($sub)];
        if($sub==='health-site') {
            m6dNativeDeny(fn()=>SiteDeploymentService::completeDeploymentSuccess($l,$r),'invalid_request');
            $failed=SiteDeploymentService::completeDeploymentFailure($l,['code'=>'health_failed']);
        } else $failed=SiteDeploymentService::completeDeploymentSuccess($l,$r);
        $check($failed['operation_status']==='reconciliation_required' && $failed['target']['current_deployment_id']===null,'modeled_health_cannot_commit_'.$sub);
        $fake->advance(31); $rec=SiteDeploymentService::claimDeploymentRecovery($op['deployment_id'],[]);
        $done=SiteDeploymentService::completeDeploymentRecovery($rec['lease'],SiteDeploymentService::reconcileDeployment($rec['lease']));
        $check($done['operation_status']==='failed' && $fake->runtime->publisher->pointers[20]===null,'modeled_fault_exact_absence_'.$sub);
    }
    // Safe prerequisite failure uses the real admitted entry point, never a fake-PDO fallback.
    $result=M6CNativeProcess::capture([PHP_BINARY,__DIR__.'/WebsitePlatformM6DNative.php','--admit',$identity['source_sha'],$identity['image_id'],$identity['run_token']],10,16384);
    $check($result['exit']===1 && !str_contains($result['stdout'],'prerequisites PASS') && str_contains($result['stderr'],'M6D native FAIL:'),'native_admission_fail_closed_without_database');
    $check(!str_contains($result['stdout'].$result['stderr'],'PRIVATE'),'private_fixture_not_disclosed');
    $assertions+=($GLOBALS['m6d_native_assertions'] ?? 0);
    echo "M6D native harness: $assertions assertions PASS (real bounded local children; modeled PDO; native NOT EXECUTED).\n";
} finally { M6CNativeProcess::cleanup(); }
