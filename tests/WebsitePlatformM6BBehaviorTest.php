<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM6BDatabase.php';
$assertions = 0;
function m6b(bool $ok, string $why): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($why); }
function m6deny(callable $call, ?string $classification = null): void {
    try { $call(); } catch (SiteServiceException $e) { m6b($classification === null || $classification === $e->classification(), 'Safe denial: ' . $e->classification()); return; }
    throw new RuntimeException('Expected denial');
}

$db = WebsitePlatformM6BDatabase::fixture();
$before = serialize([$db->read->base->sites, $db->read->base->revisions, $db->read->base->approvals]);
$job = $db->request(); $duplicate = $db->request(2, ['correlation_id' => 'another-actor']);
m6b($job['id'] === $duplicate['id'] && $duplicate['existing'] && !$job['existing'], 'Duplicate keeps job identity');
m6b($db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'] === 1 && $db->events('site_build_requested') === 1, 'Original requester and one requested event');
$claim = $db->claim();
m6b($claim['attempt']['execution_number'] === 1 && $claim['attempt']['attempt_number'] === 1, 'Initial execution allocation');
m6b($db->runtime->prepared === 2 && $db->runtime->verified === 0, 'Projection precedes intent; no real artifact verification');
$receipt = $db->receipt($claim, 'sealed');
$success = SiteBuildService::completeBuildSuccess($claim['lease'], $receipt);
m6b($success['job']['status'] === 'succeeded' && count($db->tables['site_releases']) === 1 && count($db->tables['site_release_validations']) === 2, 'Atomic synthetic release and validation persistence');
$snapshot = $db->snapshot(); $verified = $db->runtime->verified;
$replay = SiteBuildService::completeBuildSuccess($claim['lease'], $receipt);
m6b($snapshot === $db->snapshot() && $verified === $db->runtime->verified && $replay['replayed'], 'Completion replay has no new verification or event');
m6deny(fn () => SiteBuildService::completeBuildSuccess($claim['lease'], ['receipt_key' => SiteServiceSupport::uuidV4()]), 'conflict');
m6b($before === serialize([$db->read->base->sites, $db->read->base->revisions, $db->read->base->approvals]), 'No lifecycle, approval or global publication mutation');
$history = json_encode(SiteBuildService::buildJobForActor(2, $job['id']));
foreach ([$claim['lease']['token'], hash('sha256', $claim['lease']['token']), 'SOURCE-SENTINEL', 'FACTS-SENTINEL', 'METADATA-SENTINEL', 'synthetic/'] as $secret) {
    m6b(!str_contains($history, $secret), 'Safe job history excludes private data');
}
m6b(count(SiteBuildService::releasesForSite(2, 10, ['limit' => 1])) === 1, 'Bounded release history');
m6b(SiteBuildService::releaseManifestForActor(2, $success['release']['id'])['files'][0]['path'] === 'index.html', 'Manifest requires private verifier and sanitized file DTO');
m6deny(fn () => SiteBuildService::buildJobForActor(3, $job['id']), 'unauthorized');
m6deny(fn () => SiteBuildService::releasesForSite(2, 10, ['limit' => 101]), 'invalid_request');

foreach ([
    'customer-only' => fn ($d) => $d->read->base->approvals[701]['state'] = 'requested',
    'customer-revoked' => fn ($d) => $d->read->base->approvals[700]['revoked_at'] = '2026-09-19',
    'internal-revoked' => fn ($d) => $d->read->base->approvals[701]['revoked_at'] = '2026-09-19',
    'missing-review-evidence' => fn ($d) => $d->read->base->revisions[100]['review_ready_at'] = null,
    'wrong-revision-site' => fn ($d) => $d->read->base->revisions[100]['site_id'] = 20,
    'association' => fn ($d) => $d->read->associations[0]['status'] = 'ended',
    'association-business' => fn ($d) => $d->read->associations[0]['business_id'] = 60,
    'business-inactive' => fn ($d) => $d->read->businesses[50]['status'] = 'inactive',
    'business-suspended' => fn ($d) => $d->read->businesses[50]['is_suspended'] = 1,
    'module-disabled' => fn ($d) => $d->read->businesses[50]['module_active'] = 0,
    'global-module-disabled' => fn ($d) => $d->read->moduleActive = false,
    'composition-tamper' => function ($d) { $id = array_key_first($d->read->base->sections); $d->read->base->sections[$id]['configuration_json'] = '{}'; },
] as $name => $change) {
    $db = WebsitePlatformM6BDatabase::fixture(); $change($db); $snap = $db->snapshot();
    m6deny(fn () => $db->request());
    m6b($db->snapshot() === $snap, 'Zero intent mutation: ' . $name);
}
foreach (['draft','pending_customer','pending_internal_review','active','suspended','archived','conversion_pending','cancellation_pending'] as $state) {
    $db = WebsitePlatformM6BDatabase::fixture(); $db->read->base->sites[10]['lifecycle_status'] = $state;
    m6deny(fn () => $db->request(), 'invalid_transition');
}
foreach (['draft','ready_for_review','customer_approved','changes_requested','restored','superseded','validation_failed','published'] as $state) {
    $db = WebsitePlatformM6BDatabase::fixture(); $db->read->base->revisions[100]['lifecycle_status'] = $state;
    m6deny(fn () => $db->request(), 'invalid_transition');
}
foreach (['emd','internal_demo'] as $purpose) {
    $db = WebsitePlatformM6BDatabase::fixture(); $db->read->base->sites[10]['purpose'] = $purpose;
    m6deny(fn () => $db->request(), 'future_gate_required');
}
foreach ([['material','draft',false],['non_material','internally_approved',false],['undetermined','draft',false],['non_material','draft',true]] as [$materiality,$status,$allowed]) {
    $db = WebsitePlatformM6BDatabase::fixture();
    $db->read->base->revisions[101] = array_replace($db->read->base->revisions[100], ['id' => 101,'revision_number' => 2,'materiality' => $materiality,'lifecycle_status' => $status]);
    if ($allowed) m6b($db->request()['status'] === 'requested', 'Nonmaterial classified draft does not stale candidate');
    else m6deny(fn () => $db->request(), 'invalid_transition');
}
$db = WebsitePlatformM6BDatabase::fixture();
$db->read->base->revisions[99] = array_replace($db->read->base->revisions[100], ['id' => 99,'revision_number' => 1]);
$db->read->base->revisions[100]['revision_number'] = 2; $db->read->base->revisions[100]['materiality'] = 'non_material';
$db->read->base->approvals[700]['revision_id'] = 99;
m6b($db->request()['status'] === 'requested', 'Nonmaterial accepts exactly one earlier customer baseline without publication');
$db->read->base->approvals[702] = array_replace($db->read->base->approvals[700], ['id' => 702]);
m6deny(fn () => $db->request(), 'conflict');
unset($db->read->base->approvals[700], $db->read->base->approvals[702]);
m6deny(fn () => $db->request(), 'invalid_transition');

foreach (['unknown','prohibited','expired','wrong-business','invalid-digest'] as $case) {
    $db = WebsitePlatformM6BDatabase::fixture();
    $db->read->base->revisionAssets[1] = ['site_id' => 10,'revision_id' => 100,'asset_id' => 1];
    if ($case === 'expired') $db->read->base->siteAssets[1]['rights_expires_at'] = '2026-01-01';
    elseif ($case === 'wrong-business') $db->read->base->siteAssets[1]['business_id'] = 60;
    elseif ($case === 'invalid-digest') $db->read->base->siteAssets[1]['checksum_sha256'] = 'not-a-digest';
    else $db->read->base->siteAssets[1]['rights_classification'] = $case;
    m6deny(fn () => $db->request(), 'conflict');
}

// Native placeholders and audited rollback: persistence and audit failures are atomic.
foreach (['site_build_jobs','site_build_attempts','site_releases','site_release_validations','site_events'] as $table) {
    $db = WebsitePlatformM6BDatabase::fixture();
    if ($table === 'site_build_jobs') { $db->failTable = $table; $before = $db->snapshot(); m6deny(fn () => $db->request(), 'database_failure'); }
    elseif ($table === 'site_build_attempts') { $db->request(); $db->failTable = $table; $before = $db->snapshot(); m6deny(fn () => $db->claim(), 'database_failure'); }
    else { $db->request(); $claim = $db->claim(); $receipt = $db->receipt($claim,'sealed'); $db->failTable = $table; $before = $db->snapshot(); m6deny(fn () => SiteBuildService::completeBuildSuccess($claim['lease'],$receipt), 'database_failure'); }
    m6b($before === $db->snapshot(), 'Atomic rollback on ' . $table);
}
$db = WebsitePlatformM6BDatabase::fixture(); $db->request(); $claim = $db->claim(); $receipt = $db->receipt($claim,'sealed');
$db->runtime->duringVerify = fn () => $db->loseCommitAck = true;
m6deny(fn () => SiteBuildService::completeBuildSuccess($claim['lease'],$receipt), 'database_failure');
m6b(SiteBuildService::completeBuildSuccess($claim['lease'],$receipt)['job']['status'] === 'succeeded' && $db->events('site_build_succeeded') === 1, 'Lost acknowledgement resolves committed result exactly once');

$db = WebsitePlatformM6BDatabase::fixture();
(new ReflectionProperty(SiteBuildService::class, 'dependencies'))->setValue(null, null);
m6deny(fn () => $db->request(), 'future_gate_required');
m6b($db->tables['site_build_jobs'] === [], 'Unwired input infrastructure cannot allocate intent');
// PR #126 P1: an incompatible candidate is retired once, without poisoning the batch.
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$beforeOld=$db->tables['site_build_jobs'][$old['id']];
$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];$current=$db->request(2);$claim=$db->claim();
m6b($claim['lease']['job_id']===$current['id'],'P1 A: later compatible job is claimed in the same bounded poll');
$retired=$db->tables['site_build_jobs'][$old['id']];
m6b($retired['status']==='failed'&&$retired['failure_code']==='builder_unavailable'&&$retired['next_attempt_at']===null,'P1 A: old builder gets terminal configuration failure');
foreach(['status','failure_category','failure_code','safe_summary','next_attempt_at','completed_at','updated_at','lock_version']as$key){unset($beforeOld[$key],$retired[$key]);}
m6b($beforeOld===$retired&&count($db->tables['site_build_attempts'])===1,'P1 A: old identity/requester/input/release/counters preserved; only compatible execution allocated');
$snap=$db->snapshot();SiteBuildService::claimBuild([]);
m6b($db->snapshot()===$snap&&$db->events('site_build_failed')===1,'P1 D: repeated polling cannot retire or allocate twice');

$db=WebsitePlatformM6BDatabase::fixture();
for($i=0;$i<25;$i++){$db->runtime->builderOverrides=['builder_code_sha'=>sha1('old-builder-'.$i)];$db->request();}
$db->runtime->builderOverrides=[];$current=$db->request();
m6b(SiteBuildService::claimBuild([])===null&&$db->events('site_build_failed')===20,'P1 B/C: first invocation retires at most its 20 candidates');
m6b($db->tables['site_build_attempts']===[]&&array_sum(array_column($db->tables['site_build_jobs'],'execution_count'))===0,'P1 B: incompatible batch consumes no execution budget');
m6b($db->claim()['lease']['job_id']===$current['id']&&$db->events('site_build_failed')===25,'P1 C: next bounded poll reaches compatible work beyond one batch');
$snap=$db->snapshot();SiteBuildService::claimBuild([]);m6b($snap===$db->snapshot(),'P1 C/D: later polls leave retired history unchanged');

foreach(['dirty','untrusted','unidentified','database']as$case){
    $db=WebsitePlatformM6BDatabase::fixture();$db->request();$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];
    if($case==='dirty')$db->runtime->clean=false;
    elseif($case==='untrusted')$db->runtime->trusted=false;
    elseif($case==='unidentified')$db->runtime->builderOverrides=['builder_code_sha'=>'unknown'];
    else $db->failAuthorization=true;
    $snap=$db->snapshot();m6deny(fn()=>SiteBuildService::claimBuild([]));
    m6b($snap===$db->snapshot(),'P1 E: global failure cannot mass-retire candidates: '.$case);
}
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$claim=$db->claim();
$db->tables['site_build_jobs'][$old['id']]['status']='requested';$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];
SiteBuildService::claimBuild([]);
m6b($db->tables['site_build_jobs'][$old['id']]['status']==='reconciliation_required'&&$db->events('site_build_failed')===0
    &&count($db->tables['site_build_attempts'])===1,'P1 F: active prior owner remains recovery-required, not a safe old-builder failure');
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$db->tables['site_build_jobs'][$old['id']]['input_manifest_json']='invalid-json';
m6b(SiteBuildService::claimBuild([])===null&&$db->tables['site_build_jobs'][$old['id']]['failure_code']==='input_mismatch','P1 malformed persisted input retires safely without broad exception handling');
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];
$db->failTable='site_events';$snap=$db->snapshot();m6deny(fn()=>SiteBuildService::claimBuild([]),'database_failure');
m6b($snap===$db->snapshot(),'P1 retirement and audit rollback together on database error');
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$claim=$db->claim();
SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'storage_unavailable']+$db->receipt($claim,'safe_absence'));
$attempts=$db->tables['site_build_attempts'];$db->advance(30);$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];
SiteBuildService::claimBuild([]);$row=$db->tables['site_build_jobs'][$old['id']];
m6b($row['status']==='failed'&&$row['execution_count']===1&&$row['attempt_count']===1&&$attempts===$db->tables['site_build_attempts'],'P1 retry retirement preserves prior attempts and budgets');
$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();$db->runtime->builderOverrides=['builder_code_sha'=>str_repeat('b',40)];$db->read->users[1]['status']='inactive';
SiteBuildService::claimBuild([]);
m6b($db->events('site_build_cancelled')===1&&$db->events('site_build_failed')===0,'P1 B17 cancellation retains precedence over builder incompatibility');

function m6bSuccessfulFixture(): array {
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$claim=$db->claim();
    $success=SiteBuildService::completeBuildSuccess($claim['lease'],$db->receipt($claim,'sealed'));
    return [$db,$job,$success];
}
foreach(['approval','newer-material','inactive-requester','deleted-requester','archived','module']as$case){
    [$db,$job,$success]=m6bSuccessfulFixture();
    if($case==='approval')$db->read->base->approvals[700]['revoked_at']='2026-09-19';
    elseif($case==='newer-material')$db->read->base->revisions[101]=array_replace($db->read->base->revisions[100],['id'=>101,'revision_number'=>2,'materiality'=>'material']);
    elseif($case==='inactive-requester')$db->read->users[1]['status']='inactive';
    elseif($case==='deleted-requester'){unset($db->read->users[1]);$db->tables['site_build_jobs'][$job['id']]['requested_by_user_id']=null;}
    elseif($case==='archived')$db->read->base->sites[10]['lifecycle_status']='archived';
    else $db->read->businesses[50]['module_active']=0;
    $snap=$db->snapshot();$effects=[$db->runtime->prepared,$db->runtime->verified];$db->queries=[];
    $replay=$db->request(2);
    m6b($replay['existing']&&$replay['replayed']&&$replay['id']===$job['id']&&$replay['release']['id']===$success['release']['id'],'P2 G/H/I exact recorded success: '.$case);
    m6b($snap===$db->snapshot()&&$effects===[$db->runtime->prepared,$db->runtime->verified],'P2 history has zero DB/preparation/artifact effects: '.$case);
    m6b(!str_contains(implode("\n",$db->queries),'site-m6:build-approvals')&&!str_contains(implode("\n",$db->queries),'site-m6:build-successors'),'P2 historical replay precedes new-build approval/freshness checks: '.$case);
}
foreach(['inactive-caller','demoted-caller','foreign-site','wrong-hash','profile']as$case){
    [$db,$job]=m6bSuccessfulFixture();$input=[];
    if($case==='inactive-caller')$db->read->users[2]['status']='inactive';
    elseif($case==='demoted-caller')$db->read->users[2]['roles']=[];
    elseif($case==='foreign-site')$input=['site_id'=>20];
    elseif($case==='wrong-hash')$input=['expected_snapshot_hash'=>str_repeat('f',64)];
    else $input=['build_profile'=>'production'];
    $snap=$db->snapshot();$effects=[$db->runtime->prepared,$db->runtime->verified];
    m6deny(fn()=>$db->request(2,$input));
    m6b($snap===$db->snapshot()&&$effects===[$db->runtime->prepared,$db->runtime->verified],'P2 J/K/L caller and exact request identity required: '.$case);
}
foreach([['builder_code_sha'=>str_repeat('b',40)],['builder_version'=>'synthetic-m6b-v2'],
    ['registry_manifest_digest'=>str_repeat('c',64)],['toolchain_contract'=>['canonicalization'=>2]]]as$change){
    [$db,$job]=m6bSuccessfulFixture();$db->runtime->builderOverrides=$change;
    $new=$db->request(2);
    m6b(!$new['existing']&&!$new['replayed']&&$new['id']!==$job['id']&&$db->events('site_build_requested')===2,'P2 K changed builder contract creates a distinct eligible request');
    $db->read->base->approvals[700]['revoked_at']='2026-09-19';$snap=$db->snapshot();
    m6deny(fn()=>$db->request(2),'invalid_transition');
    m6b($snap===$db->snapshot(),'P2 K differing builder never borrows old success to bypass gates');
}
[$db,$job]=m6bSuccessfulFixture();$db->read->base->revisions[100]['facts_snapshot_json']='{"changed":"immutable-source-test"}';
$db->read->base->revisions[100]['snapshot_hash']=SiteRevisionSnapshotHasher::hashStoredRevision($db,100);
$db->read->base->approvals[700]['revoked_at']='2026-09-19';$snap=$db->snapshot();
m6deny(fn()=>$db->request(2),'invalid_transition');m6b($snap===$db->snapshot(),'P2 K changed source/input hash is a new gated request');

foreach(['manifest','input-hash','identity-key','coherent-input-rewrite','release','source-content','ambiguous']as$case){
    [$db,$job]=m6bSuccessfulFixture();$id=$job['id'];
    if($case==='manifest')$db->tables['site_build_jobs'][$id]['input_manifest_json']='{}';
    elseif($case==='input-hash')$db->tables['site_build_jobs'][$id]['build_input_hash']=str_repeat('f',64);
    elseif($case==='identity-key')$db->tables['site_build_jobs'][$id]['idempotency_key']=str_repeat('f',64);
    elseif($case==='release'){$releaseId=array_key_first($db->tables['site_releases']);$db->tables['site_releases'][$releaseId]['build_input_hash']=str_repeat('f',64);}
    elseif($case==='source-content')$db->read->base->revisions[100]['facts_snapshot_json']='{"tampered":true}';
    else{
        $row=$db->tables['site_build_jobs'][$id];$manifest=SiteBuildContract::decode($row['input_manifest_json']);$manifest['public_facts']=['changed'=>'different-input'];
        $row['input_manifest_json']=CanonicalJson::encode($manifest);$row['build_input_hash']=CanonicalJson::hash($manifest);
        $row['idempotency_key']=CanonicalJson::hash(['site_key'=>$db->read->base->sites[10]['site_key'],'revision_id'=>100,
            'build_input_hash'=>$row['build_input_hash'],'builder_version'=>$row['builder_version'],'builder_code_sha'=>$row['builder_code_sha']]);
        if($case==='ambiguous'){$row['id']=1000;$row['release_key']=SiteServiceSupport::uuidV4();$db->tables['site_build_jobs'][1000]=$row;}
        else $db->tables['site_build_jobs'][$id]=$row;
    }
    $snap=$db->snapshot();$effects=[$db->runtime->prepared,$db->runtime->verified];
    m6deny(fn()=>$db->request(2),'conflict');
    m6b($snap===$db->snapshot()&&$effects===[$db->runtime->prepared,$db->runtime->verified],'P2 L corrupt/ambiguous evidence is never latest-success fallback: '.$case);
}
// Deterministic interleaving only: a winner completes while the outer request prepares.
foreach([false,true]as$differentInput){
    $db=WebsitePlatformM6BDatabase::fixture();$winningSnapshot=null;
    $db->runtime->duringPrepare=function()use($db,&$winningSnapshot,$differentInput):void{
        $db->runtime->duringPrepare=null;$winner=$db->request(2);$claim=$db->claim();
        SiteBuildService::completeBuildSuccess($claim['lease'],$db->receipt($claim,'sealed'));
        $db->read->base->approvals[700]['revoked_at']='2026-09-19';
        if($differentInput)$db->runtime->projectionOverrides=['public_facts'=>['name'=>'different prepared input']];
        $winningSnapshot=$db->snapshot();
    };
    if($differentInput)m6deny(fn()=>$db->request(),'conflict');
    else {$replay=$db->request();m6b($replay['replayed']&&$replay['status']==='succeeded','P2 M mid-preparation winner is returned before propagating changed gates');}
    m6b($db->snapshot()===$winningSnapshot&&$db->events('site_build_requested')===1&&$db->runtime->verified===1,'P2 M winner comparison has no duplicate effects or requester transfer');
}
// Simulate 1062 after a concurrent commit. The retry hook must reauthorize/read BEFORE gates.
foreach([false,true]as$revokeCaller){
    [$db,$job]=m6bSuccessfulFixture();$winningTables=$db->tables;$winningEvents=$db->read->base->events;
    foreach($db->tables as$key=>$rows)$db->tables[$key]=[];
    $db->read->base->events=array_values(array_filter($winningEvents,fn($event)=>!str_starts_with($event['event_type'],'site_build_')));
    $db->onSql=function($sql,$p,$d)use($winningTables,$winningEvents,$revokeCaller):void{
        if(!str_starts_with($sql,'INSERT INTO site_build_jobs'))return;
        $d->onSql=null;
        $d->beforeTransaction=function($next)use($winningTables,$winningEvents,$revokeCaller):void{
            $next->tables=$winningTables;$next->read->base->events=$winningEvents;
            $next->read->base->approvals[700]['revoked_at']='2026-09-19';
            if($revokeCaller)$next->read->users[2]['status']='inactive';
            $next->queries=[];
        };
        $error=new PDOException('SYNTHETIC-DUPLICATE');$error->errorInfo=['23000',1062,'SYNTHETIC-DUPLICATE'];throw $error;
    };
    if($revokeCaller)m6deny(fn()=>$db->request(2),'unauthorized');
    else {$replay=$db->request(2);m6b($replay['replayed']&&$replay['id']===$job['id'],'P2 M duplicate-insert rollback resolves exact winner before changed eligibility');}
    m6b($db->tables===$winningTables&&$db->read->base->events===$winningEvents
        &&!str_contains(implode("\n",$db->queries),'site-m6:build-approvals'),'P2 M fresh authorized winner lookup precedes new-build gates and has zero writes');
}

$db=WebsitePlatformM6BDatabase::fixture();
$db->runtime->duringPrepare=function()use($db):void{$db->runtime->duringPrepare=null;$db->loseCommitAck=true;};
m6deny(fn()=>$db->request(),'database_failure');$snap=$db->snapshot();$retried=$db->request(2);
m6b($retried['existing']&&$retried['replayed']&&$snap===$db->snapshot()&&$db->events('site_build_requested')===1
    &&$db->tables['site_build_jobs'][$retried['id']]['requested_by_user_id']===1,'P2 M uncertain commit propagates; explicit lost-response retry preserves one operation and original requester');

echo "Website platform M6B behavior: $assertions assertions passed.\n";
