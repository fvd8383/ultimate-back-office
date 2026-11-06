<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args=array_slice($argv,1);
if ($args===['--help']) {
    echo "Usage: php scripts/site-deployment-worker.php --once\nVerified protected staging host contract required; default denied.\n";
    exit(0);
}
if ($args!==['--once']) { fwrite(STDERR,"Invalid arguments. Use --help.\n"); exit(2); }
require_once dirname(__DIR__).'/private/classes/SiteDeploymentService.php';
try {
    SiteDeploymentRuntime::boot();
    $claim=SiteDeploymentService::claimDeployment([]); $status='no_work_claimed';
    if ($claim!==null) {
        SiteDeploymentRuntime::bindLease($claim['lease']);
        $receipt=SiteDeploymentService::executeDeployment($claim['lease']);
        $result=$receipt['completed'] ?? SiteDeploymentService::completeDeploymentSuccess($claim['lease'],$receipt);
        $status=$result['operation_status'];
    }
    $exit=in_array($status,['failed','retry_wait','reconciliation_required'],true) ? 1 : 0;
} catch(Throwable $e) {
    // Unknown commit outcome remains for independent reconciliation; no blind compensation here.
    $code=$e instanceof SiteServiceException ? $e->classification() : 'publisher_unavailable';
    fwrite(STDERR,'Deployment invocation stopped: '.$code.".\n"); $exit=1;
} finally {
    try {SiteDeploymentRuntime::finishInvocation();}
    catch(Throwable) {fwrite(STDERR,"Publisher settlement unresolved.\n");$exit=1;}
}
if(isset($status))echo 'Deployment outcome: '.$status.".\n";
exit($exit);
