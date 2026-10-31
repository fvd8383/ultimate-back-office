<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(array_slice($argv,1)===['--help']) { echo "Internal M6D DB-only worker; launched by the admitted native coordinator.\n"; exit(0); }
try {
    if(count($argv)!==7 || !in_array($argv[4],['request','claim','request-hold','claim-hold'],true)
        || !preg_match('/^[1-9][0-9]{0,9}$/D',$argv[5]) || !in_array($argv[6],['publish','restore'],true)) throw new RuntimeException('worker_arguments');
    require_once __DIR__.'/WebsitePlatformM6DNativeFixture.php';
    $a=M6DNativeRuntime::admit($argv[1],$argv[2],$argv[3]);
    pcntl_async_signals(true); pcntl_signal(SIGALRM,static fn()=>throw new RuntimeException('worker_deadline')); pcntl_alarm(45);
    $path=M6DNativeRuntime::ROOT.'/state/fixture-'.$argv[5].'.json'; $s=lstat($path);
    M6DNativeContract::need($s!==false && $s['uid']===2000 && ($s['mode']&0777)===0600 && $s['nlink']===1 && $s['size']<=8192 && realpath($path)===$path,'worker_fixture');
    $f=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    M6DNativeContract::need((string)$f['site_id']===$argv[5],'worker_scope');
    $db=new M6DNativeConnection(M6DNativeRuntime::connect($a)); $d=new M6DNativeDependencies($db,$f);
    $d->environment=$f['environment'] ?? 'staging'; $d->production=$d->environment==='production'; $d->wire();
    if(str_ends_with($argv[4],'-hold')) $db->holdEvent=str_starts_with($argv[4],'request') ? 'site_deployment_requested' : 'site_deployment_started';
    echo 'READY '.(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn()."\n"; flush();
    M6DNativeContract::need(trim((string)fgets(STDIN))==='START','worker_start');
    try {
        $r=str_starts_with($argv[4],'claim') ? SiteDeploymentService::claimDeployment([])
            : ($argv[6]==='restore' ? SiteDeploymentService::requestRestore($f['requester'],$f['input']) : SiteDeploymentService::requestDeployment($f['requester'],$f['input']));
        // Never export lease tokens through child diagnostics.
        if(isset($r['lease'])) $r=['claimed'=>true,'attempt_id'=>$r['lease']['attempt_id'],'deployment_id'=>$r['lease']['deployment_id']];
        echo 'RESULT '.json_encode($r,JSON_THROW_ON_ERROR)."\n";
    } catch(SiteServiceException $e) { echo 'DENIED '.$e->classification()."\n"; }
    exit(0);
} catch(Throwable $e) {
    $code=isset($a) ? M6DNativeContract::safeFailure($e) : ['class'=>get_class($e),'code'=>'worker_admission_failed'];
    fwrite(STDERR,json_encode($code)."\n"); exit(1);
}
