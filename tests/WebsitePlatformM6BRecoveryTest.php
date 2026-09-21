<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM6BDatabase.php';
$assertions = 0;
function m6r(bool $ok, string $why): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($why); }
function m6rd(callable $call, ?string $classification = null): void {
    try { $call(); } catch (SiteServiceException $e) { m6r($classification === null || $classification === $e->classification(), 'Safe denial: ' . $e->classification()); return; }
    throw new RuntimeException('Expected denial');
}

$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim();
$tokenHash = $db->tables['site_build_attempts'][$claim['lease']['attempt_id']]['lease_token_hash'];
m6r($tokenHash === hash('sha256',$claim['lease']['token']) && $tokenHash !== $claim['lease']['token'], 'Only 256-bit token hash stored');
for ($i=0;$i<8;$i++) { $db->advance(100); $renewed = SiteBuildService::renewBuildLease($claim['lease']); }
m6r($renewed['lease_expires_at'] === $claim['attempt']['deadline_at'], 'Renewal bounded by persisted absolute 900-second deadline');
$db->advance(100); m6rd(fn () => SiteBuildService::renewBuildLease($claim['lease']),'conflict');
m6rd(fn () => SiteBuildService::completeBuildSuccess($claim['lease'],$db->receipt($claim,'sealed')),'conflict');
$waiting = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r(!$waiting['claimed'] && $waiting['job']['recovery_status'] === 'required', 'Expiry detected and scheduled once');
$due = $db->tables['site_build_jobs'][$job['id']]['next_recovery_at'];
$db->advance(10); SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r($due === $db->tables['site_build_jobs'][$job['id']]['next_recovery_at'], 'Polling does not slide recovery due time');
$db->advance(20); $recovery = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r($recovery['claimed'] && $recovery['lease']['token'] !== $claim['lease']['token'], 'Fresh recovery ownership token');
m6rd(fn () => SiteBuildService::executeBuild($recovery['lease']),'invalid_request');
m6rd(fn () => SiteBuildService::completeBuildSuccess($recovery['lease'],$db->receipt($recovery,'sealed')),'invalid_request');
m6rd(fn () => SiteBuildService::renewBuildLease($claim['lease']),'conflict');
$old = $db->tables['site_build_attempts'][$claim['lease']['attempt_id']];
$hint = $db->receipt($recovery,'sealed');
$result = SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition'=>'adopted'] + $hint);
m6r($result['job']['status'] === 'succeeded' && $result['job']['recovery_status'] === 'resolved', 'Synthetic adoption commits atomic release and recovery success');
m6r($old === $db->tables['site_build_attempts'][$claim['lease']['attempt_id']], 'Original expired execution remains unchanged');
$snap = $db->snapshot(); SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition'=>'adopted'] + $hint);
m6r($snap === $db->snapshot(), 'Matching recovery completion is effect-free');
m6r(SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition'=>'recorded_success'] + $hint)['recorded_success'],
    'A committed recovery adoption is recognized as recorded success');
m6rd(fn () => SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition'=>'blocked'] + $hint),'conflict');

// Three ordinary executions, then total attempt FOUR as recovery, with requester deleted.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request();
for ($i=1;$i<=3;$i++) {
    $claim = $db->claim();
    m6r($claim['attempt']['execution_number'] === $i, 'Monotonic execution number');
    $result = SiteBuildService::completeBuildFailure($claim['lease'], ['code'=>$i === 3 ? 'outcome_unknown' : 'storage_unavailable']
        + $db->receipt($claim,$i === 3 ? 'unknown' : 'safe_absence'));
    if ($i<3) {
        m6r($result['job']['status'] === 'retry_wait' && SiteBuildService::claimBuild([]) === null, 'Safe transient retry has persisted backoff');
        $db->advance($i === 1 ? 30 : 120);
    }
}
unset($db->read->users[1]); $db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] = null;
$db->advance(30); $recovery = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r($recovery['attempt']['attempt_number'] === 4 && $recovery['attempt']['recovery_number'] === 1
    && $recovery['attempt']['execution_number'] === null, 'B24 total four is recovery, not a fourth execution');
$result = SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition'=>'safely_failed'] + $db->receipt($recovery,'safe_absence'));
m6r($result['job']['execution_count'] === 3 && $result['job']['recovery_count'] === 1 && $result['job']['status'] === 'failed', 'Safe settlement at exhausted execution budget without original requester');
m6r(SiteBuildService::claimBuild([]) === null, 'No fourth execution');

// Two automatic failures consume lifetime limit; operator recovery is a new bounded request.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim();
SiteBuildService::completeBuildFailure($claim['lease'], ['code'=>'outcome_unknown'] + $db->receipt($claim,'unknown'));
$db->advance(30); $first = SiteBuildService::claimBuildRecovery($job['id'],[]);
SiteBuildService::completeBuildRecovery($first['lease'], ['disposition'=>'retryable_recovery_failure'] + $db->receipt($first,'unknown'));
$db->advance(119); m6r(!SiteBuildService::claimBuildRecovery($job['id'],[])['claimed'], 'Second automatic backoff is 120 seconds');
$db->advance(1); $second = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r($second['attempt']['recovery_of_attempt_id'] === $claim['lease']['attempt_id'], 'Recovery restart binds original execution');
$db->advance(121); $blocked = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r(!$blocked['claimed'] && $blocked['job']['recovery_status'] === 'blocked' && $blocked['job']['automatic_recovery_count'] === 2, 'Second automatic expiry blocks permanently');
$request = ['request_key' => SiteServiceSupport::uuidV4(), 'reason_code' => 'retry_recovery'];
foreach ([['acting_user_id'=>2],['recovery_authorized_by_user_id'=>2],['actor_type'=>'super_admin'],['trusted'=>true]] as $forged) {
    $snap = $db->snapshot();
    m6rd(fn () => SiteBuildService::claimBuildRecovery($job['id'],[], $request + $forged),'invalid_request');
    m6r($snap === $db->snapshot(),'Forged operator fields rejected before claims/counters/ownership');
}
$db->runtime->operator = 3; $snap = $db->snapshot();
m6rd(fn () => SiteBuildService::claimBuildRecovery($job['id'],[],$request),'unauthorized');
m6r($snap === $db->snapshot(),'Business owner cannot authorize recovery');
$db->runtime->operator = 2; $operator = SiteBuildService::claimBuildRecovery($job['id'],[],$request);
m6r($operator['claimed'] && $operator['attempt']['recovery_number'] === 3, 'Authorized operator gets exactly one additional bounded claim');
$snap = $db->snapshot(); $replay = SiteBuildService::claimBuildRecovery($job['id'],[],$request);
m6r($replay['replayed'] && !isset($replay['lease']) && $snap === $db->snapshot(),'Operator request replay is summary only, never reusable lease');
m6rd(fn () => SiteBuildService::claimBuildRecovery($job['id'],[],array_replace($request,['reason_code'=>'verify_quarantine'])),'conflict');
$hint = $db->receipt($operator,'unknown');
SiteBuildService::completeBuildRecovery($operator['lease'],['disposition'=>'blocked']+$hint);
m6r($db->tables['site_build_jobs'][$job['id']]['recovery_status'] === 'blocked', 'Failed operator recovery never schedules automatically');
$db->tables['site_build_attempts'][$operator['lease']['attempt_id']]['recovery_authorized_by_user_id'] = null;
unset($db->read->users[2]); $db->runtime->operator = 1;
$replay = SiteBuildService::claimBuildRecovery($job['id'],[],$request);
m6r($replay['replayed'] && $replay['attempt']['recovery_trigger'] === 'operator', 'Historical nullable actor does not rewrite trigger or deny independently authorized replay');
$db->read->users[1]['status'] = 'inactive';
m6rd(fn () => SiteBuildService::claimBuildRecovery($job['id'],[],$request),'unauthorized');

// Recovery adoption rechecks requester; it cannot turn independent inspection into new success authority.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim();
$db->advance(121); SiteBuildService::claimBuildRecovery($job['id'],[]); $db->advance(30);
$recovery = SiteBuildService::claimBuildRecovery($job['id'],[]); $db->read->users[1]['status'] = 'inactive';
$result = SiteBuildService::completeBuildRecovery($recovery['lease'],['disposition'=>'adopted']+$db->receipt($recovery,'sealed'));
m6r($result['job']['status'] === 'reconciliation_required' && $db->tables['site_releases'] === [], 'B23 adoption cannot bypass requester');
m6r($result['job']['recovery_status'] === 'blocked', 'Denied adoption needs explicit further recovery');

// Expired operator attempt cannot silently reactivate the automatic scheduler.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim();
SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'outcome_unknown']+$db->receipt($claim,'unknown'));
$operator = SiteBuildService::claimBuildRecovery($job['id'],[],['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'inspect_unknown_outcome']);
$db->advance(121); $result = SiteBuildService::claimBuildRecovery($job['id'],[]);
m6r($result['job']['recovery_status'] === 'blocked' && $result['job']['automatic_recovery_count'] === 0,'Operator expiry stays blocked without spending automatic budget');
// Explicit retry after resolved transient uncertainty keeps identity/counters and locks both users in ascending order.
$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request(2);$claim=$db->claim();
SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'storage_unavailable']+$db->receipt($claim,'unknown'));
$db->advance(30);$r=SiteBuildService::claimBuildRecovery($job['id'],[]);
SiteBuildService::completeBuildRecovery($r['lease'],['disposition'=>'safely_failed']+$db->receipt($r,'quarantined'));
$order=[];$db->onSql=function($sql,$p)use(&$order):void{if(str_starts_with($sql,'SELECT id FROM users'))$order[]=$p['user_id'];};
$retried=SiteBuildService::retryBuild(1,$job['id'],'explicit-retry');
m6r($order===[1,2]&&$retried['execution_count']===1&&$retried['recovery_count']===1,'Two-user retry ordering and increment-only accounting');
m6r($db->tables['site_build_jobs'][$job['id']]['requested_by_user_id']===2,'Authorized retry caller cannot take ownership');
$db->read->users[2]['roles']=[];$db->advance(30);SiteBuildService::claimBuild([]);
m6r($db->tables['site_build_jobs'][$job['id']]['status']==='cancelled','Revocation after explicit retry is checked at claim');

$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$claim=$db->claim();$hint=$db->receipt($claim,'sealed');
$db->runtime->receipts[$hint['receipt_key']]['producer_attempt_id']=9999;
$result=SiteBuildService::completeBuildSuccess($claim['lease'],$hint);
m6r($result['job']['recovery_status']==='blocked'&&$db->tables['site_releases']===[],'Conflicting verified identity immediately blocks adoption');
echo "Website platform M6B recovery: $assertions assertions passed.\n";
