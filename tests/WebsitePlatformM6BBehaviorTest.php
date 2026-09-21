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

// PR #126 policy P1: only the FIRST job's persisted policy is incompatible.
/** Valid persisted peer with distinct canonical input, unchanged current builder/profile/options.
 * Direct fixture insertion avoids request-history ambiguity; it is not concurrency evidence. */
function m6bQueuePeer(WebsitePlatformM6BDatabase $db,array $seed,int $ordinal):array {
    $manifest=SiteBuildContract::decode($seed['input_manifest_json']);
    $projection=array_intersect_key($manifest,array_flip(['public_facts','public_composition','ordered_asset_digests']));
    $projection['public_facts']['fixture_ordinal']=$ordinal;
    $source=['site'=>$db->read->base->sites[10],'revision'=>$db->read->base->revisions[100]];
    $row=array_replace($seed,SiteBuildContract::input($source,$projection,$db->runtime->builderIdentity()),[
        'job_key'=>SiteServiceSupport::uuidV4(),'release_key'=>SiteServiceSupport::uuidV4()]);
    unset($row['id']);$row['id']=SiteBuildStore::insert($db,'site_build_jobs',$row);
    m6b(SiteBuildContract::builderMatches($row,SiteBuildContract::recordedInput($source,$row),$db->runtime->builderIdentity()),
        'Queue peer has a valid unique input and matching current builder');
    return $row;
}
function m6bRetired(WebsitePlatformM6BDatabase $db,array $before,string $code='policy_unsupported'):void {
    $after=$db->tables['site_build_jobs'][$before['id']];
    m6b($after['status']==='failed'&&$after['failure_code']===$code&&$after['failure_category']==='configuration'
        &&$after['next_attempt_at']===null&&$after['completed_at']===$db->now&&$after['updated_at']===$db->now
        &&$after['lock_version']===$before['lock_version']+1,'Safe retirement has fixed failure, DB times and one lock increment');
    $events=array_filter($db->read->base->events,static fn($e)=>$e['event_type']==='site_build_failed'&&$e['reason']===$code
        &&json_decode($e['metadata_json'],true)['build_job_id']===$before['id']);
    m6b(count($events)===1,'Exactly one bounded failure event for this job/disposition');
    foreach(['status','failure_category','failure_code','safe_summary','next_attempt_at','completed_at','updated_at','lock_version']as$key){unset($before[$key],$after[$key]);}
    m6b($before===$after,'Retirement preserves every identity/input/requester/policy/limit/counter/ownership field');
}
// Candidate audit: a safely settled retry with a supported, exhausted limit is SQL-representable.
$db=WebsitePlatformM6BDatabase::fixture();$exhausted=$db->request();$lease=$db->claim();
SiteBuildService::completeBuildFailure($lease['lease'],['code'=>'storage_unavailable']+$db->receipt($lease,'safe_absence'));
$db->tables['site_build_jobs'][$exhausted['id']]['max_execution_attempts']=1;$db->advance(30);
$budgetBefore=$db->tables['site_build_jobs'][$exhausted['id']];$attempts=$db->tables['site_build_attempts'];
$snap=$db->snapshot();SiteBuildService::claimBuild([]);
m6b($db->tables['site_build_jobs'][$exhausted['id']]['status']==='failed',
    'Candidate audit: exhausted safe queue must retire; unchanged_due_queue='.($snap===$db->snapshot()?'yes':'no'));
m6bRetired($db,$budgetBefore,'execution_exhausted');
m6b($attempts===$db->tables['site_build_attempts'],'Exhausted queue preserves original settled attempt, deadline and receipt');
$snap=$db->snapshot();SiteBuildService::claimBuild([]);m6b($snap===$db->snapshot(),'Exhausted candidate cannot stay due or repeat its event');

$db=WebsitePlatformM6BDatabase::fixture();$old=$db->request();
$db->runtime->projectionOverrides=['public_facts'=>['name'=>'Second compatible synthetic input']];
$current=$db->request(2);
$source=['site'=>$db->read->base->sites[10],'revision'=>$db->read->base->revisions[100]];
foreach([$old,$current]as$queued){
    $row=$db->tables['site_build_jobs'][$queued['id']];
    m6b(SiteBuildContract::builderMatches($row,SiteBuildContract::recordedInput($source,$row),$db->runtime->builderIdentity())
        &&SiteBuildContract::policy($row)===SiteBuildContract::POLICY,'Policy fixture has compatible input/profile/options/registry/toolchain/builder');
}
$db->tables['site_build_jobs'][$old['id']]['worker_policy_version']='build-worker-unsupported';
$policyBefore=$db->tables['site_build_jobs'][$old['id']];$prepared=$db->runtime->prepared;
$snap=$db->snapshot();$caught=null;$claim=null;
try{$claim=SiteBuildService::claimBuild([]);}catch(SiteServiceException $e){$caught=$e->classification();}
m6b($caught===null&&($claim['lease']['job_id']??null)===$current['id'],
    'Policy-only P1: later compatible job must be leased; caught='.($caught??'none').'; unchanged_due_queue='.($snap===$db->snapshot()?'yes':'no'));
m6bRetired($db,$policyBefore);
m6b(count($db->tables['site_build_attempts'])===1&&$db->events('site_build_started')===1&&$db->runtime->prepared===$prepared
    &&$db->runtime->verified===0&&$claim['input']['build_input_hash']===$current['build_input_hash'],'Only compatible job gets an attempt/lease/BuildInput; no external work');
$snap=$db->snapshot();SiteBuildService::claimBuild([]);m6b($snap===$db->snapshot(),'Repeated poll does not duplicate policy retirement');
m6deny(fn()=>SiteBuildService::retryBuild(2,$old['id'],SiteServiceSupport::uuidV4()),'conflict');
m6b($snap===$db->snapshot(),'Unsupported policy cannot enter transient retry or rewrite its limits');

// Valid JSON scalars/arrays/incomplete objects, unsupported bounds inside JSON and oversized JSON are SQL-representable.
$badPayloads=[CanonicalJson::encode(array_replace(SiteBuildContract::POLICY,['lease_seconds'=>121])),
    'null','[]','{"lease_seconds":0}',CanonicalJson::encode(array_replace(SiteBuildContract::POLICY,['execution_timeout_seconds'=>0])),
    CanonicalJson::encode(['padding'=>str_repeat('x',4100)])];
foreach($badPayloads as$payload){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']=$payload;
    $before=$db->tables['site_build_jobs'][$job['id']];$prepared=$db->runtime->prepared;
    m6b(SiteBuildService::claimBuild([])===null,'Invalid/unsupported policy payload returns no lease');m6bRetired($db,$before);
    m6b($db->tables['site_build_attempts']===[]&&$db->events('site_build_started')===0&&$db->runtime->prepared===$prepared
        &&$db->runtime->verified===0,'Policy payload failure has no execution or external effects');
}
// Defensive fake-only rows: native JSON syntax and CHECK constraints prohibit these stored states.
foreach([['worker_policy_json'=>'{bad'],['max_execution_attempts'=>0],['max_execution_attempts'=>4],
    ['max_automatic_recoveries'=>-1],['max_automatic_recoveries'=>3]]as$invalid){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();
    $before=$db->tables['site_build_jobs'][$job['id']]=array_replace($db->tables['site_build_jobs'][$job['id']],$invalid);
    m6b(SiteBuildService::claimBuild([])===null,'Defensive fake-only malformed JSON/column bounds fail closed');m6bRetired($db,$before);
}
$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();
$ordered=json_encode(array_reverse(SiteBuildContract::POLICY,true),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']=$ordered;
$db->tables['site_build_jobs'][$job['id']]['max_execution_attempts']=1;$db->tables['site_build_jobs'][$job['id']]['max_automatic_recoveries']=0;
m6b($db->claim()['lease']['job_id']===$job['id']&&$db->events('site_build_failed')===0,'Semantic policy comparison accepts reordered JSON and lower supported limits');
m6b($db->tables['site_build_jobs'][$job['id']]['worker_policy_json']===$ordered,'Supported stored JSON is never rewritten');

$db=WebsitePlatformM6BDatabase::fixture();$first=$db->request();$seed=$db->tables['site_build_jobs'][$first['id']];$beforeRows=[$seed];
for($i=1;$i<25;$i++)$beforeRows[]=m6bQueuePeer($db,$seed,$i);
$compatible=m6bQueuePeer($db,$seed,25);
foreach($beforeRows as&$row){$row['worker_policy_version']='build-worker-old';$db->tables['site_build_jobs'][$row['id']]=$row;}unset($row);
m6b(SiteBuildService::claimBuild([])===null&&$db->events('site_build_failed')===20,'Policy first poll retires exactly the bounded 20 candidates');
m6b($db->tables['site_build_attempts']===[]&&array_sum(array_column($db->tables['site_build_jobs'],'attempt_count'))===0,'Policy batch consumes no execution/recovery/total attempt budget');
m6b($db->claim()['lease']['job_id']===$compatible['id']&&$db->events('site_build_failed')===25,'Policy next poll reaches compatible work after 25 retirements');
foreach($beforeRows as$row)m6bRetired($db,$row);
$snap=$db->snapshot();SiteBuildService::claimBuild([]);m6b($snap===$db->snapshot(),'No duplicate policy batch event/attempt on repeat polling');

$db=WebsitePlatformM6BDatabase::fixture();$first=$db->request();$seed=$db->tables['site_build_jobs'][$first['id']];
$badInput=m6bQueuePeer($db,$seed,1);$badPolicy=m6bQueuePeer($db,$seed,2);$compatible=m6bQueuePeer($db,$seed,3);
$db->tables['site_build_jobs'][$first['id']]['builder_code_sha']=str_repeat('b',40);
// Recompute the first job's legitimate old-builder identity; do not conflate it with input corruption.
$db->tables['site_build_jobs'][$first['id']]['idempotency_key']=CanonicalJson::hash(['site_key'=>$db->read->base->sites[10]['site_key'],
    'revision_id'=>100,'build_input_hash'=>$seed['build_input_hash'],'builder_version'=>$seed['builder_version'],'builder_code_sha'=>str_repeat('b',40)]);
$db->tables['site_build_jobs'][$badInput['id']]['build_input_hash']=str_repeat('f',64);
$db->tables['site_build_jobs'][$badPolicy['id']]['worker_policy_json']='{}';
m6b($db->claim()['lease']['job_id']===$compatible['id'],'Mixed builder/input/policy batch progresses to compatible work');
m6b(array_column(array_slice($db->tables['site_build_jobs'],0,3),'failure_code')===['builder_unavailable','input_mismatch','policy_unsupported']
    &&$db->events('site_build_failed')===3&&count($db->tables['site_build_attempts'])===1,'Mixed rejection classes remain distinct without extra attempts');

foreach(['dirty','untrusted','unidentified','unavailable','database','changed-worker']as$case){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';
    if($case==='dirty')$db->runtime->clean=false;
    elseif($case==='untrusted')$db->runtime->trusted=false;
    elseif($case==='unidentified')$db->runtime->builderOverrides=['builder_code_sha'=>'unknown'];
    elseif($case==='unavailable')(new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,null);
    elseif($case==='database')$db->failAuthorization=true;
    else $db->onSql=function($sql)use($db):void{if(str_contains($sql,'SELECT * FROM site_build_jobs')&&str_contains($sql,'FOR UPDATE'))$db->runtime->trusted=false;};
    $snap=$db->snapshot();m6deny(fn()=>SiteBuildService::claimBuild([]));
    m6b($snap===$db->snapshot(),'Global/current worker failure cannot retire unsupported-policy jobs: '.$case);
}
foreach(['audit','update','unexpected']as$case){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';
    if($case==='audit')$db->failTable='site_events';
    else $db->onSql=function($sql)use($case):void{if(str_starts_with($sql,'UPDATE site_build_jobs')){
        if($case==='unexpected')throw new LogicException('Synthetic unexpected failure');throw new PDOException('Synthetic SQL failure');}};
    $snap=$db->snapshot();m6deny(fn()=>SiteBuildService::claimBuild([]),'database_failure');
    m6b($snap===$db->snapshot(),'Policy retirement and audit roll back atomically; no catch-all classification: '.$case);
}
foreach([1205,1213]as$driverCode){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';
    $before=$db->tables['site_build_jobs'][$job['id']];$once=false;
    $db->onSql=function($sql)use(&$once,$driverCode):void{if(!$once&&str_starts_with($sql,'INSERT INTO site_events')){
        $once=true;$e=new PDOException('Synthetic rolled-back conflict');$e->errorInfo=['40001',$driverCode,'synthetic'];throw $e;}};
    SiteBuildService::claimBuild([]);m6bRetired($db,$before);
    m6b($once&&$db->tables['site_build_attempts']===[],'Known rolled-back DB conflict retries the locked retirement without consuming attempts');
}
$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';
$before=$db->tables['site_build_jobs'][$job['id']];$db->loseCommitAck=true;
m6deny(fn()=>SiteBuildService::claimBuild([]),'database_failure');m6bRetired($db,$before);
$snap=$db->snapshot();SiteBuildService::claimBuild([]);m6b($snap===$db->snapshot(),'Uncertain commit propagates; explicit next poll cannot duplicate committed policy failure');

$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$claim=$db->claim();
SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'storage_unavailable']+$db->receipt($claim,'safe_absence'));
$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';$db->advance(30);
$before=$db->tables['site_build_jobs'][$job['id']];$attempts=$db->tables['site_build_attempts'];$receipts=$db->runtime->receipts;$events=$db->read->base->events;
SiteBuildService::claimBuild([]);m6bRetired($db,$before);
m6b($attempts===$db->tables['site_build_attempts']&&$receipts===$db->runtime->receipts
    &&array_slice($db->read->base->events,0,count($events),true)===$events,'Policy retirement preserves previous attempts/deadlines/receipt evidence and audit history');

$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';$db->read->users[1]['status']='inactive';
SiteBuildService::claimBuild([]);$snap=$db->snapshot();SiteBuildService::claimBuild([]);
m6b($db->events('site_build_cancelled')===1&&$db->events('site_build_failed')===0&&$snap===$db->snapshot(),'B17 cancellation retains precedence over unsupported policy and remains one-time');

foreach(['active','unresolved','blocked']as$case){
    $db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$claim=$db->claim();
    if($case!=='active')SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'outcome_unknown']+$db->receipt($claim,'unknown'));
    // SQL-representable inconsistency: a due status with an existing owner or unresolved effects.
    $db->tables['site_build_jobs'][$job['id']]['status']='retry_wait';$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';
    if($case==='blocked')$db->tables['site_build_jobs'][$job['id']]['recovery_status']='blocked';
    $before=$db->tables['site_build_jobs'][$job['id']];$attempts=$db->tables['site_build_attempts'];$verified=$db->runtime->verified;
    SiteBuildService::claimBuild([]);$after=$db->tables['site_build_jobs'][$job['id']];
    m6b($after['status']==='reconciliation_required'&&$after['failure_code']==='outcome_unknown'&&$after['next_attempt_at']===null
        &&$after['recovery_status']===($case==='blocked'?'blocked':'required')&&$db->events('site_build_failed')===0,'Uncertain effects retain required/blocked recovery, never false policy safe-failure: '.$case);
    foreach(['status','failure_category','failure_code','safe_summary','next_attempt_at','completed_at','updated_at','lock_version','recovery_status','next_recovery_at']as$key){unset($before[$key],$after[$key]);}
    m6b($before===$after&&$attempts===$db->tables['site_build_attempts'],'Unresolved policy case preserves ownership/identity/policy/budgets/attempts');
    $snap=$db->snapshot();SiteBuildService::claimBuild([]);m6deny(fn()=>SiteBuildService::claimBuildRecovery($job['id'],[]),'conflict');
    m6b($snap===$db->snapshot()&&$verified===$db->runtime->verified,'No fallback-policy recovery or repeated ordinary-queue effects');
}
[$db,$job,$success]=m6bSuccessfulFixture();$db->tables['site_build_jobs'][$job['id']]['worker_policy_version']='historical-policy';
$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';$db->read->base->approvals[700]['revoked_at']='2026-09-19';
$snap=$db->snapshot();$prepared=$db->runtime->prepared;$verified=$db->runtime->verified;$replay=$db->request(2);
$expected=$success['job']+['existing'=>true,'replayed'=>true,'release'=>$success['release']];
m6b($replay===$expected,'Historical unsupported policy does not invalidate exact authorized committed success replay');
m6b($snap===$db->snapshot()&&$prepared===$db->runtime->prepared&&$verified===$db->runtime->verified,'History-only replay never prepares/verifies/rewrites policy or allocates effects');

echo "Website platform M6B behavior: $assertions assertions passed.\n";
