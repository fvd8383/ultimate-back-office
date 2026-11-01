<?php
declare(strict_types=1);

// Not a *Test.php: ordinary standalone discovery cannot execute native SQL.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(array_slice($argv,1)===['--help']) {
    echo "M6D DB-only native validation (NOT EXECUTED by local regression discovery).\n"
        . "Usage: php -d memory_limit=256M tests/WebsitePlatformM6DNative.php --admit SOURCE_SHA IMAGE_ID RUN_TOKEN\n"
        . "       php -d memory_limit=256M tests/WebsitePlatformM6DNative.php --run SOURCE_SHA IMAGE_ID RUN_TOKEN\n"
        . "Requires separately reviewed disposable MySQL 8.4, root-owned admission and isolated resources.\n"
        . "--admit performs read-only prerequisites; --run uses and drops only the exact admitted disposable schema.\n";
    exit(0);
}
if(count($argv)!==5 || !in_array($argv[1],['--admit','--run'],true)) { fwrite(STDERR,"Invalid native arguments. Use --help.\n"); exit(2); }
require_once __DIR__.'/support/WebsitePlatformM6DNativeRuntime.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeControl.php';
$report=M6DNativeContract::initial($argv[2],$argv[3],$argv[4]); $a=null; $db=null; $raw=null; $observer=null; $owned=false; $case=null; $sub=null;
try {
    $a=M6DNativeRuntime::admit($argv[2],$argv[3],$argv[4]);
    if($argv[1]==='--admit') { echo "M6D prerequisites PASS; no database connected and no cases executed.\n"; exit(0); }
    // Separate operator-created execution receipt; a successful --admit is not execution approval.
    $authorization=M6DNativeRuntime::ROOT.'/EXECUTE'; $s=lstat($authorization);
    M6DNativeContract::need($s!==false && ($s['mode']&0170000)===0100000 && $s['uid']===0 && ($s['mode']&0777)===0644
        && $s['nlink']===1 && $s['size']<=128 && realpath($authorization)===$authorization
        && trim(file_get_contents($authorization))===$a['run_token'].':'.M6DNativeContract::PROFILE,'separate_execution_authorization');
    pcntl_async_signals(true); pcntl_signal(SIGALRM,static fn()=>throw new RuntimeException('native_deadline')); pcntl_alarm(600);
    require_once __DIR__.'/support/WebsitePlatformM6DNativeFixture.php';
    require_once __DIR__.'/support/WebsitePlatformM6DNativeExpansion.php';
    $raw=M6DNativeRuntime::connect($a); $observer=M6DNativeRuntime::connect($a);
    foreach(['sites','users','businesses','site_deployments','site_build_jobs'] as $table) {
        M6DNativeContract::need((int)$raw->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===0,'disposable_empty_baseline');
    }
    $schema=$raw->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll();
    $report['schema_inventory_sha256']=hash('sha256',json_encode($schema,JSON_THROW_ON_ERROR));
    $expected=['site_build_jobs','site_build_attempts','site_releases','site_release_validations','site_deployment_targets',
        'site_deployments','site_deployment_attempts','site_deployment_health_checks','site_deployment_approvals'];
    M6DNativeContract::need(array_diff($expected,array_column($schema,'TABLE_NAME'))===[],'migration_025_required');
    $owned=true; $db=new M6DNativeConnection($raw);
    foreach(M6DNativeContract::CASES as $case) {
        $report['cases'][$case]['status']='RUNNING'; $report['cases'][$case]['assertions']=0;
        foreach(M6DNativeContract::subcases($case) as $sub) {
            $start=hrtime(true); $count=$GLOBALS['m6d_native_assertions'] ?? 0; $GLOBALS['m6d_native_sites']=[];
            $report['cases'][$case]['subcases'][$sub]=['status'=>'RUNNING'];
            $f=m6dNativeFixture($db,$case==='restore-eligibility' && $sub==='asset-rights'); $d=new M6DNativeDependencies($db,$f); $d->wire();
            $evidence=m6dNativeExpandedCase($case,$sub,$db,$observer,$a,$f,$d);
            $assertions=($GLOBALS['m6d_native_assertions'] ?? 0)-$count;
            $report['cases'][$case]['subcases'][$sub]=['status'=>'PASS','assertions'=>$assertions,
                'seconds'=>(hrtime(true)-$start)/1e9,'site_id'=>$f['site_id']]+$evidence;
            $report['cases'][$case]['assertions']+=$assertions;
            // Post-observation fixture retirement, never credited as a tested disposition.
            foreach($GLOBALS['m6d_native_sites'] as $siteId)
                $observer->prepare("UPDATE site_deployments SET status='cancelled' WHERE site_id=? AND status IN ('requested','retry_wait')")->execute([$siteId]);
            echo 'PASS '.$case.'/'.$sub."\n";
        }
        $report['cases'][$case]['status']='PASS';
        echo 'PASS '.$case."\n";
    }
    $report['capture']='PASS';
} catch(Throwable $error) {
    M6CNativeProcess::observeFailure(); $report['primary_failure']=M6DNativeContract::safeFailure($error);
    if($case!==null) $report['cases'][$case]['status']='FAIL';
    if($case!==null && $sub!==null) $report['cases'][$case]['subcases'][$sub]['status']='FAIL';
    $report['failure_context']=['case'=>$case,'subcase'=>$sub];
    $report['capture']='PASS';
    fwrite(STDERR,'M6D native FAIL: '.$report['primary_failure']['code']."\n");
} finally {
    if(function_exists('pcntl_alarm')) pcntl_alarm(0);
    $report['worker_results']=$GLOBALS['m6d_native_child_results'] ?? [];
    $report['transport_observations']=$GLOBALS['m6d_wire_observations'] ?? [];
    $report['authoritative_observations']=$GLOBALS['m6d_wire_authoritative'] ?? [];
    M6DNativeControl::cleanup($report,$owned,static fn()=>M6CNativeProcess::cleanup(),static function() use($raw,$observer,$a): void {
            if($raw->inTransaction()) $raw->rollBack(); if($observer->inTransaction()) $observer->rollBack();
            // Exact disposable schema identity is re-observed before the only destructive cleanup.
            $cleanup=M6DNativeRuntime::connect($a);
            $name=M6DNativeControl::ownedSchema($a);
            $cleanup->exec('DROP DATABASE `'.$name.'`');
            $q=$cleanup->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?'); $q->execute([$a['database_name']]);
            M6DNativeContract::need((int)$q->fetchColumn()===0,'owned_schema_cleanup');
            // State/report tmpfs teardown belongs to the separately authorized namespace owner.
    });
    $report['status']=M6DNativeContract::complete($report,$a) ? 'PASS' : 'FAIL';
    if($a!==null && $argv[1]==='--run') {
        M6DNativeControl::publish($report,static function(array &$r): void { M6DNativeRuntime::publish($r); },$a);
        if($report['publication']==='FAIL') fwrite(STDERR,'M6D publication FAIL: '.$report['publication_failure']['code']."\n");
    }
}
exit($report['status']==='PASS' && $report['publication']==='PASS' ? 0 : 1);
