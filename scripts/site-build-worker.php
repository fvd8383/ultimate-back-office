<?php

declare(strict_types=1);

// Parse before bootstrap/config/database access. No arguments can select an identity or root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args = array_slice($argv,1);
if ($args === ['--help']) {
    echo "Usage: php scripts/site-build-worker.php --once\n"
        . "       php scripts/site-build-worker.php --recover JOB_ID\n"
        . "Protected host configuration is required. Build only; no publication.\n";
    exit(0);
}
if ($args !== ['--once'] && !(count($args) === 2 && $args[0] === '--recover' && preg_match('/^[1-9][0-9]{0,9}$/D',$args[1]))) {
    fwrite(STDERR,"Invalid arguments. Use --help.\n"); exit(2);
}
require_once dirname(__DIR__) . '/private/classes/SiteBuildService.php';
require_once dirname(__DIR__) . '/private/classes/SiteBuildRuntime.php';
try {
    $result = null;
    // Service resolves the only supported private runtime; argument parsing cannot start work.
    if ($args[0] === '--recover') {
        $claim = SiteBuildService::claimBuildRecovery((int)$args[1],[]);
        if ($claim['claimed'] ?? false) $result = SiteBuildService::completeBuildRecovery($claim['lease'],SiteBuildService::reconcileBuild($claim['lease']));
    } else {
        $claim = SiteBuildService::claimBuild([]);
        if ($claim !== null) {
            try { $receipt = SiteBuildService::executeBuild($claim['lease']); }
            catch (SiteArtifactException $e) {
                SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$e->failureCode,'receipt_key'=>$claim['input']['release_key']]);
                throw $e;
            }
            // An unknown DB completion outcome is never compensated or retried here.
            $result = $receipt['completed'] ?? SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
        }
    }
    $status = $result['job']['status'] ?? 'no_work_claimed';
    echo "Build-only outcome: " . $status . ".\n";
    if (in_array($status,['failed','retry_wait','reconciliation_required'],true)) exit(1);
} catch (Throwable $e) {
    $code = $e instanceof SiteArtifactException ? $e->failureCode : ($e instanceof SiteServiceException ? $e->classification() : 'builder_unavailable');
    fwrite(STDERR,"Build-only invocation stopped: " . $code . ".\n"); exit(1);
}
