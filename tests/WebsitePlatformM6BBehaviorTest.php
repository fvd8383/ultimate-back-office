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
echo "Website platform M6B behavior: $assertions assertions passed.\n";
