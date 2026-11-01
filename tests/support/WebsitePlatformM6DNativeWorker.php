<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(array_slice($argv,1)===['--help']) { echo "Internal M6D DB-only worker; launched by the admitted native coordinator.\n"; exit(0); }
try {
    if(count($argv)!==7 || !in_array($argv[4],['request','claim','request-hold','claim-hold','boundary','mutate','duplicate'],true)
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
    if($argv[4]==='duplicate') {
        $db->duplicateOnce=true;
        $db->afterRollback=static fn()=>M6DNativeProtocol::barrier('ROLLED_BACK');
    }
    if($argv[4]==='boundary') {
        M6DNativeContract::need(in_array($f['phase'],['claim','activation','success'],true) && in_array($f['order'],['denial','work'],true),'boundary_shape');
        if($f['order']==='work') {
            $db->holdEvent=match($f['phase']) {'claim'=>'site_deployment_started','activation'=>'site_activation_attempted','success'=>'site_deployment_succeeded'};
            $db->pauseAfterCommit=true;
        } elseif($f['phase']!=='claim') {
            $active=0;
            $d->model->afterCall=static function(string $method,array $args) use($f,&$active,$d): void {
                $at=$f['phase']==='activation' ? $method==='inspectCurrentRelease'
                    : ($method==='healthCheck' && $args[2]['phase']==='active' && ++$active===2);
                if($at) { $d->model->afterCall=null; M6DNativeProtocol::barrier('BOUNDARY'); }
            };
        }
    }
    echo 'READY '.(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn()."\n"; flush();
    M6DNativeContract::need(trim((string)fgets(STDIN))==='START','worker_start');
    try {
        if($argv[4]==='mutate') {
            $db->beginTransaction();
            if($f['authority']==='requester') $db->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
            elseif($f['authority']==='approver') $db->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$f['operator']]);
            else $db->prepare("UPDATE site_approvals SET state='revoked',revoked_at=UTC_TIMESTAMP(6) WHERE id=? AND site_id=?")->execute([$f['grant_approval_id'],$f['site_id']]);
            $db->commit(); $r=['mutated'=>true];
        } elseif($argv[4]==='boundary') $r=match($f['phase']) {
            'claim'=>SiteDeploymentService::claimDeployment([]),
            'activation'=>SiteDeploymentService::executeDeployment($f['lease']),
            'success'=>SiteDeploymentService::completeDeploymentSuccess($f['lease'],$f['receipt']),
        };
        else $r=str_starts_with($argv[4],'claim') ? SiteDeploymentService::claimDeployment([])
            : ($argv[6]==='restore' ? SiteDeploymentService::requestRestore($f['requester'],$f['input']) : SiteDeploymentService::requestDeployment($f['requester'],$f['input']));
        // Never export lease tokens through child diagnostics.
        if(isset($r['lease'])) {
            M6DNativeProtocol::privateRecord(M6DNativeRuntime::ROOT.'/state/lease-'.$f['site_id'].'.json',$r['lease']);
            $r=['claimed'=>true,'attempt_id'=>$r['lease']['attempt_id'],'deployment_id'=>$r['lease']['deployment_id']];
        }
    } catch(SiteServiceException $e) { $r=['denied'=>$e->classification()]; }
    echo M6DNativeProtocol::frame(M6DNativeProtocol::identity($a),$r)."\nDONE\n";
    exit(0);
} catch(Throwable $e) {
    $code=isset($a) ? M6DNativeContract::safeFailure($e) : ['class'=>get_class($e),'code'=>'worker_admission_failed'];
    fwrite(STDERR,json_encode($code)."\n"); exit(1);
}
