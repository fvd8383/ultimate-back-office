<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args=array_slice($argv,1);
if ($args===['--help']) {
    echo "Usage: php scripts/reconcile-site-jobs.php --build JOB_ID\n"
        . "       php scripts/reconcile-site-jobs.php --deployment DEPLOYMENT_ID\n"
        . "One bounded recovery claim; protected environment capability required. No cleanup or ordinary execution.\n";
    exit(0);
}
if (count($args)!==2 || !in_array($args[0],['--build','--deployment'],true) || !preg_match('/^[1-9][0-9]{0,9}$/D',$args[1])) {
    fwrite(STDERR,"Invalid arguments. Use --help.\n"); exit(2);
}
try {
    $status='no_work_claimed';
    if ($args[0]==='--build') {
        require_once dirname(__DIR__).'/private/classes/SiteBuildService.php';
        $claim=SiteBuildService::claimBuildRecovery((int)$args[1],[]);
        if ($claim['claimed'] ?? false) {
            $result=SiteBuildService::completeBuildRecovery($claim['lease'],SiteBuildService::reconcileBuild($claim['lease']));
            $status=$result['job']['status'];
        }
    } else {
        require_once dirname(__DIR__).'/private/classes/SiteDeploymentService.php';
        SiteDeploymentRuntime::boot();
        $claim=SiteDeploymentService::claimDeploymentRecovery((int)$args[1],[]);
        if ($claim['claimed'] ?? false) {
            SiteDeploymentRuntime::bindLease($claim['lease']);
            $result=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],SiteDeploymentService::reconcileDeployment($claim['lease']));
            $status=$result['operation_status'];
        }
    }
    echo 'Reconciliation outcome: '.$status.".\n";
    $exit=in_array($status,['failed','reconciliation_required'],true) ? 1 : 0;
} catch(Throwable $e) {
    $code=$e instanceof SiteServiceException ? $e->classification() : 'recovery_unavailable';
    fwrite(STDERR,'Reconciliation invocation stopped: '.$code.".\n"); $exit=1;
} finally {
    if($args[0]==='--deployment'&&class_exists(SiteDeploymentRuntime::class,false)) {
        try{SiteDeploymentRuntime::finishInvocation();}
        catch(Throwable){fwrite(STDERR,"Publisher settlement unresolved.\n");$exit=1;}
    }
}
exit($exit);
