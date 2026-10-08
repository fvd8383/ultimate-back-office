<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM6BDatabase.php';
$assertions = 0;
function m6a(bool $ok, string $why): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($why); }
function m6ad(callable $call, ?string $classification = null): void {
    try { $call(); } catch (SiteServiceException $e) { m6a($classification === null || $classification === $e->classification(), 'Safe denial: ' . $e->classification()); return; }
    throw new RuntimeException('Expected denial');
}
foreach (['inactive','deleted','null-requester','business-admin','no-role'] as $case) {
    $db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request();
    if ($case === 'inactive') $db->read->users[1]['status'] = 'inactive';
    elseif ($case === 'deleted') { unset($db->read->users[1]); $db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] = null; }
    elseif ($case === 'null-requester') $db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] = null;
    else $db->read->users[1]['roles'] = []; // Business membership does not enter the internal role join.
    m6a(SiteBuildService::claimBuild([]) === null, 'B17/B19/B20 denied candidate');
    $row = $db->tables['site_build_jobs'][$job['id']];
    m6a($row['status'] === 'cancelled' && $row['failure_code'] === 'requester_not_authorized', 'Cancellation commits instead of throwing auth rollback');
    m6a($row['execution_count'] === 0 && $row['attempt_count'] === 0 && $row['current_attempt_id'] === null
        && $db->tables['site_build_attempts'] === [] && $db->events('site_build_started') === 0, 'Zero execution allocation on requester loss');
    m6a($db->events('site_build_cancelled') === 1 && $db->runtime->prepared === 1, 'One bounded cancellation, no claim input preparation');
    $snap = $db->snapshot(); SiteBuildService::claimBuild([]);
    m6a($snap === $db->snapshot(), 'Repeated polls do not repeat cancellation');
    m6ad(fn () => SiteBuildService::retryBuild(2,$job['id'],'retry'), $case === 'null-requester' ? 'unauthorized' : null);
    m6a(SiteBuildService::buildJobForActor(2,$job['id'])['status'] === 'cancelled', 'Safe internal history survives requester loss');
}
foreach ([1 => 'Super Admin', 2 => 'Admin'] as $user => $role) {
    $db = WebsitePlatformM6BDatabase::fixture(); $db->request($user); $db->read->users[$user]['roles'] = [$role];
    m6a($db->claim()['attempt']['execution_number'] === 1, 'B18 current role allows Admin/Super Admin changes');
}
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $db->runtime->trusted = false;
$db->read->users[1]['status'] = 'inactive'; $snap = $db->snapshot();
m6ad(fn () => SiteBuildService::claimBuild([]),'unauthorized'); m6a($snap === $db->snapshot(), 'Untrusted worker cannot cancel');
$db->runtime->trusted = true; $db->runtime->clean = false;
m6ad(fn () => SiteBuildService::claimBuild([]),'conflict'); m6a($snap === $db->snapshot(), 'Unidentified builder cannot execute or cancel');

$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $db->failAuthorization = true; $snap = $db->snapshot();
m6ad(fn () => SiteBuildService::claimBuild([]),'database_failure');
m6a($snap === $db->snapshot(), 'Authorization database error is not definite denial');
foreach ([['trusted' => true],['user_id' => 1],['requested_by_user_id' => 2],['actor_type' => 'super_admin'],['recovery_authorized_by_user_id' => 2]] as $forged) {
    $db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $snap = $db->snapshot();
    m6ad(fn () => SiteBuildService::claimBuild($forged),'invalid_request');
    m6ad(fn () => $db->request(1,$forged),'invalid_request');
    m6a($snap === $db->snapshot(), 'B26 forged identity has no writes');
}

// B21 same persisted requester through retry, different authorized caller, no transfer.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim();
SiteBuildService::completeBuildFailure($claim['lease'], ['code' => 'storage_unavailable'] + $db->receipt($claim,'safe_absence'));
$db->read->users[1]['status'] = 'inactive'; $snap = $db->snapshot();
m6ad(fn () => SiteBuildService::retryBuild(2,$job['id'],'retry'),'unauthorized');
m6a($snap === $db->snapshot(), 'Revoked requester cannot be replaced by retry caller');
m6a(SiteBuildService::claimBuild([]) === null, 'Backoff remains due before cancellation scan');
$db->advance(30); SiteBuildService::claimBuild([]);
m6a($db->tables['site_build_jobs'][$job['id']]['execution_count'] === 1 && $db->events('site_build_cancelled') === 1, 'Requeued retry cancels with prior attempt retained');

// B23 accepted claim can have real prior work: synthetic sealed evidence cannot bypass revoked authorization.
foreach (['inactive','deleted'] as $case) {
    $db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim(); $hint = $db->receipt($claim,'sealed');
    $db->runtime->duringVerify = function () use ($db,$job,$case): void {
        if ($case === 'deleted') { unset($db->read->users[1]); $db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] = null; }
        else $db->read->users[1]['status'] = 'inactive';
    };
    $result = SiteBuildService::completeBuildSuccess($claim['lease'],$hint);
    m6a($result['job']['status'] === 'reconciliation_required' && $result['job']['failure_code'] === 'requester_not_authorized', 'B23 postclaim denial retains uncertain effects');
    m6a($db->tables['site_releases'] === [] && $db->tables['site_release_validations'] === [] && $db->events('site_build_succeeded') === 0, 'No new success evidence after revocation');
    $attempt = $db->tables['site_build_attempts'][$claim['lease']['attempt_id']];
    m6a($attempt['candidate_artifact_hash'] !== null && $result['job']['execution_count'] === 1, 'Candidate and actual execution remain history');
    $db->advance(30); $recovery = SiteBuildService::claimBuildRecovery($job['id'],[]);
    m6a($recovery['claimed'] && !isset($recovery['input']), 'Requester-independent recovery grants no BuildInput');
    $settled = SiteBuildService::completeBuildRecovery($recovery['lease'], ['disposition' => 'safely_failed'] + $db->receipt($recovery,'quarantined'));
    m6a($settled['job']['status'] === 'failed' && $settled['job']['recovery_status'] === 'resolved', 'Safe recovery settlement does not require original requester');
}
// B25 recorded success is recognized before revoked requester or stale input gates.
// Uncertain queue evidence is removed from ordinary scheduling, never cancelled.
$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();
$db->tables['site_build_jobs'][$job['id']]['recovery_status']='required';
$db->tables['site_build_jobs'][$job['id']]['next_recovery_at']=SiteBuildStore::after($db->now,30);
$db->read->users[1]['status']='inactive';
m6a(SiteBuildService::claimBuild([])===null,'Uncertain candidate has no execution lease');
m6a($db->tables['site_build_jobs'][$job['id']]['status']==='reconciliation_required'
    &&$db->tables['site_build_jobs'][$job['id']]['next_attempt_at']===null
    &&$db->events('site_build_cancelled')===0,'B17 uncertainty cannot masquerade as safe cancellation');

$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $claim = $db->claim(); $hint = $db->receipt($claim,'sealed');
SiteBuildService::completeBuildSuccess($claim['lease'],$hint);
unset($db->read->users[1]); $db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] = null;
$db->read->base->sites[10]['lifecycle_status'] = 'archived'; $snap = $db->snapshot();
m6a(SiteBuildService::completeBuildSuccess($claim['lease'],$hint)['job']['status'] === 'succeeded', 'B25 immutable success survives original requester deletion');
m6a(SiteBuildService::claimBuildRecovery($job['id'],[])['recorded_success'], 'Known success needs no recovery lease');
m6a($snap === $db->snapshot(), 'B25 history/replay has zero new effects');

// Behavioral lock ordering and deadlock retry; real serialization belongs to the MySQL harness.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request();
$db->queries = []; $db->claim();
$query = implode("\n", $db->queries);
m6a(strpos($query, 'FROM sites WHERE') < strpos($query, 'SELECT id FROM users'), 'Site/input precede authorization');
m6a(strpos($query, 'SELECT id FROM users') < strpos($query, 'SELECT role_id FROM user_roles')
    && strpos($query, 'SELECT role_id FROM user_roles') < strpos($query, 'SELECT r.id FROM roles'), 'Policy parent/grants/definitions lock order preserved');
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $once = true;
$db->onSql = function ($sql,$p,$d) use (&$once): void {
    if ($once && str_starts_with($sql,'INSERT INTO site_build_attempts')) {
        $once = false; $d->read->users[1]['status'] = 'inactive';
        $e = new PDOException('PRIVATE-DEADLOCK'); $e->errorInfo = ['40001',1213,'PRIVATE-DEADLOCK']; throw $e;
    }
};
m6a(SiteBuildService::claimBuild([]) === null && $db->events('site_build_cancelled') === 1
    && $db->tables['site_build_attempts'] === [], 'Deadlock retry reauthorizes in a fresh transaction');
// Requester FK changes after its identity read: restart with fresh locks, then cancel.
$db = WebsitePlatformM6BDatabase::fixture(); $job = $db->request(); $once = true;
$db->onSql = function ($sql,$p,$d) use (&$once,$job): void {
    if ($once && str_contains($sql,'FROM site_build_jobs WHERE id = :id AND site_id = :site_id FOR UPDATE')) {
        $once=false; $d->tables['site_build_jobs'][$job['id']]['requested_by_user_id']=null;
        // Model a committed external FK action across rollback, then fresh reads.
        $d->beforeTransaction=fn($next)=>$next->tables['site_build_jobs'][$job['id']]['requested_by_user_id']=null;
    }
};
m6a(SiteBuildService::claimBuild([])===null && $db->events('site_build_cancelled')===1,'B19 requester NULL race is reassessed without any execution allocation');

$db = WebsitePlatformM6BDatabase::fixture(); $job=$db->request(); $claim=$db->claim(); $hint=$db->receipt($claim,'sealed');
$db->runtime->duringVerify=fn()=>$db->runtime->trusted=false; $before=$db->snapshot();
m6ad(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$hint),'unauthorized');
m6a($before===$db->snapshot(),'Current worker trust is rechecked after external verification');
echo "Website platform M6B authorization: $assertions assertions passed.\n";
