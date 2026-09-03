<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/support/WebsitePlatformM4AServiceDatabase.php';

$assertions = 0;
function assertM4ABehavior(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expectM4ABehaviorError(callable $callback, string $classification): SiteServiceException
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM4ABehavior($exception->classification() === $classification, "Expected {$classification}; got {$exception->classification()}.");
        return $exception;
    }
    throw new RuntimeException("Expected {$classification}.");
}
function m4aBriefInput(string $summary): array
{
    return [
        'summary' => $summary,
        'target_audience' => 'Local homeowners',
        'tone_notes' => 'Calm and direct',
        'design_notes' => 'Clean blue presentation',
        'conversion_notes' => 'Make estimate requests prominent',
        'page_notes' => 'Home and services pages first',
    ];
}
function m4aDatabase(): WebsitePlatformM4AServiceDatabase
{
    $database = WebsitePlatformM4AServiceDatabase::fixture();
    useWebsitePlatformM4AServiceDatabase($database);
    return $database;
}

// Actual generation-brief mutations and reads.
$database = m4aDatabase();
$briefOneInput = m4aBriefInput('Version one creative direction');
$briefOne = SiteGenerationBriefManager::createBrief(1, 10, $briefOneInput);
assertM4ABehavior($briefOne['brief_version'] === 1 && count($database->briefs) === 1, 'Internal Admin must create brief version one.');
assertM4ABehavior(count($database->events) === 1 && array_values($database->events)[0]['event_type'] === 'site_generation_brief_created', 'Brief create must emit exactly one success event.');
$storedOne = $database->briefs[$briefOne['brief_id']];
assertM4ABehavior($storedOne['brief_json'] === CanonicalJson::encode($briefOneInput), 'Stored brief JSON must use actual canonical bytes.');
assertM4ABehavior($storedOne['content_hash'] === CanonicalJson::hash($briefOneInput), 'Stored content hash must match the canonical brief.');
$storedOneBytes = serialize($storedOne);

$briefTwoInput = array_reverse(m4aBriefInput('Version two creative direction'), true);
$briefTwo = SiteGenerationBriefManager::createBrief(2, 10, $briefTwoInput);
assertM4ABehavior($briefTwo['brief_version'] === 2 && count($database->briefs) === 2, 'Super Admin must create monotonically increasing version two.');
assertM4ABehavior(serialize($database->briefs[$briefOne['brief_id']]) === $storedOneBytes, 'Creating a successor must not change any earlier brief bytes.');
assertM4ABehavior(count($database->events) === 2, 'Each successful brief create must emit exactly one event.');

$readOne = SiteGenerationBriefManager::briefForActor(1, $briefOne['brief_id']);
$history = SiteGenerationBriefManager::briefsForSite(1, 10);
$latest = SiteGenerationBriefManager::latestCurrentBrief(2, 10);
assertM4ABehavior(CanonicalJson::encode($readOne['brief']) === CanonicalJson::encode($briefOneInput), 'briefForActor must execute and return canonical content.');
assertM4ABehavior(array_column($history, 'brief_version') === [2, 1], 'briefsForSite must return newest-first append-only history.');
assertM4ABehavior($latest !== null && $latest['id'] === $briefTwo['brief_id'], 'latestCurrentBrief must derive the latest admin_manual version.');

$beforeDuplicate = [count($database->briefs), count($database->events)];
expectM4ABehaviorError(static fn () => SiteGenerationBriefManager::createBrief(1, 10, $briefTwoInput), 'conflict');
assertM4ABehavior([count($database->briefs), count($database->events)] === $beforeDuplicate, 'Duplicate brief conflict must create no row or event.');
expectM4ABehaviorError(static fn () => SiteGenerationBriefManager::briefForActor(3, $briefOne['brief_id']), 'unauthorized');
expectM4ABehaviorError(static fn () => SiteGenerationBriefManager::createBrief(3, 10, m4aBriefInput('Denied')), 'unauthorized');

$database = m4aDatabase();
expectM4ABehaviorError(static fn () => SiteGenerationBriefManager::createBrief(1, 50, m4aBriefInput('Archived denied')), 'invalid_transition');
assertM4ABehavior($database->briefs === [] && $database->events === [], 'Archived brief mutation must leave no row or event.');

$database = m4aDatabase();
$database->failEventInsert = true;
expectM4ABehaviorError(static fn () => SiteGenerationBriefManager::createBrief(1, 10, m4aBriefInput('Rollback brief')), 'database_failure');
assertM4ABehavior($database->briefs === [] && $database->events === [] && $database->rollbackCount === 1, 'Brief event failure must roll back row and event.');

// Actual authoritative snapshot builds.
$database = m4aDatabase();
$briefPhrase = 'BRIEF-PROSE-MUST-NOT-BECOME-FACTS';
SiteGenerationBriefManager::createBrief(1, 10, m4aBriefInput($briefPhrase));
$snapshot = SiteRevisionSnapshotBuilder::buildForSite(1, 10);
assertM4ABehavior($snapshot['site_key'] === 'site-247sp' && $snapshot['purpose'] === '247sp', '247SP snapshot must use the correct site identity.');
assertM4ABehavior($snapshot['business_id'] === 100 && $snapshot['facts_snapshot']['business']['display_name'] === 'Acme Home Services', '247SP snapshot must use the active business association and Shared Business Profile.');
assertM4ABehavior($snapshot['facts_snapshot']['services']['selected'][0]['name'] === 'Leak Repair', 'Selected authoritative services must be represented.');
assertM4ABehavior($snapshot['facts_snapshot']['services']['custom'][0]['name'] === 'Historic Fixture Repair', 'Custom authoritative services must be represented.');
assertM4ABehavior($snapshot['facts_snapshot']['service_area']['base_location']['city'] === 'Richmond' && $snapshot['facts_snapshot']['service_area']['radius_miles'] === 25, 'Current service-area facts must be represented safely.');
assertM4ABehavior($snapshot['facts_snapshot']['hours'][0]['opens_at'] === '08:00:00', 'Public business hours must be represented.');
assertM4ABehavior(count($snapshot['facts_snapshot']['faqs']) === 2 && array_column($snapshot['facts_snapshot']['faqs'], 'channel_scope') === ['all', 'website'], 'Only active website/all FAQs must enter presentation facts.');
assertM4ABehavior(
    count($snapshot['facts_snapshot']['pricing_guidance']) === 1
        && $snapshot['facts_snapshot']['pricing_guidance'][0]['guidance_text'] === 'Starting at $99'
        && !str_contains(CanonicalJson::encode($snapshot['facts_snapshot']['pricing_guidance']), 'Inactive private guidance'),
    'Inactive pricing guidance must be excluded.'
);
assertM4ABehavior($snapshot['source_references']['faqs']['row_ids'] === [903, 904], 'FAQ source references must match rows consumed by the snapshot.');
assertM4ABehavior($snapshot['source_references']['pricing_guidance']['row_ids'] === [906], 'Pricing source references must match active rows consumed by the snapshot.');
assertM4ABehavior($snapshot['source_references']['selected_services']['sub_service_ids'] === [701]
    && $snapshot['source_references']['custom_services']['business_custom_service_ids'] === [702], 'Service source-reference IDs must match fixture authority.');
$encodedSnapshot = CanonicalJson::encode($snapshot);
foreach (['cus_sensitive', 'sensitive-auth-value', 'private internal note', 'private provider value', 'private-alerts@example.test', '+15555550100', 'must never enter snapshot', $briefPhrase] as $sensitive) {
    assertM4ABehavior(!str_contains($encodedSnapshot, $sensitive), "Snapshot must exclude sensitive or non-authoritative value: {$sensitive}");
}

$emdSnapshot = SiteRevisionSnapshotBuilder::buildForSite(1, 20);
$demoSnapshot = SiteRevisionSnapshotBuilder::buildForSite(2, 30);
foreach ([$emdSnapshot, $demoSnapshot] as $minimal) {
    assertM4ABehavior($minimal['business_id'] === null && $minimal['facts_snapshot']['business'] === null, 'EMD/internal-demo snapshot must contain no customer business.');
    assertM4ABehavior($minimal['source_references']['customer_business_fabricated'] === false, 'EMD/internal-demo snapshot must explicitly reject fabricated businesses.');
}

// Exact eligibility-race hooks: snapshot builds while eligible; state changes before the mutation transaction.
$raceCases = [
    'association change' => static function (WebsitePlatformM4AServiceDatabase $db): void { $db->associations[1]['status'] = 'inactive'; },
    'business suspension' => static function (WebsitePlatformM4AServiceDatabase $db): void { $db->businesses[100]['is_suspended'] = 1; },
    'business inactive' => static function (WebsitePlatformM4AServiceDatabase $db): void { $db->businesses[100]['status'] = 'inactive'; },
    'module removal' => static function (WebsitePlatformM4AServiceDatabase $db): void { $db->businessModules[1]['status'] = 'inactive'; },
];
foreach ($raceCases as $label => $mutation) {
    $database = m4aDatabase();
    $briefId = $database->addBrief(10);
    $database->beforeTransactionHook = $mutation;
    $before = [count($database->revisions), count($database->events)];
    expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId), 'conflict');
    assertM4ABehavior([count($database->revisions), count($database->events)] === $before, "{$label} race must create no revision or false event.");
}

// Actual authored revision behavior.
$database = m4aDatabase();
$briefId = $database->addBrief(10);
$builtBeforeCreate = SiteRevisionSnapshotBuilder::buildForSite(1, 10);
$created = SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId);
$storedRevision = $database->revisions[$created['revision_id']];
assertM4ABehavior($storedRevision['lifecycle_status'] === 'draft' && $storedRevision['materiality'] === 'undetermined', 'Authored revision must be a normal undetermined draft.');
assertM4ABehavior($storedRevision['restored_from_revision_id'] === null && $created['composition_state'] === 'empty', 'Authored revision must have null restore provenance and empty composition.');
assertM4ABehavior($storedRevision['generation_brief_id'] === $briefId, 'Authored revision must reference the selected same-site brief.');
assertM4ABehavior(json_decode($storedRevision['facts_snapshot_json'], true, 512, JSON_THROW_ON_ERROR) === $builtBeforeCreate['facts_snapshot'], 'Facts snapshot must be server generated.');
assertM4ABehavior(json_decode($storedRevision['source_references_json'], true, 512, JSON_THROW_ON_ERROR) === $builtBeforeCreate['source_references'], 'Source references must be server generated.');
$expectedHash = SiteRevisionSnapshotBuilder::seedHash($builtBeforeCreate, $database->briefs[$briefId], null);
assertM4ABehavior($storedRevision['snapshot_hash'] === $expectedHash && $created['snapshot_hash'] === $expectedHash, 'Stored snapshot hash must equal the deterministic server seed.');
assertM4ABehavior(preg_match('/^site:[a-f0-9]{32}$/', $storedRevision['correlation_id']) === 1, 'Revision correlation ID must be server generated.');
assertM4ABehavior(count($database->events) === 1 && array_values($database->events)[0]['event_type'] === 'site_authored_draft_created', 'Valid authored revision must emit exactly one success event.');
$secondError = expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId), 'conflict');
assertM4ABehavior(str_contains($secondError->getMessage(), (string) $created['revision_id']) && count($database->events) === 1, 'Second mutable draft conflict must identify the existing revision and emit no event.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$otherBriefId = $database->addBrief(40);
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $otherBriefId), 'invalid_request');
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, 999999), 'invalid_request');
assertM4ABehavior($database->revisions === [] && $database->events === [], 'Missing/cross-site brief rejection must produce no partial mutation.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$otherPrior = $database->addRevision(20, 'changes_requested');
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId, $otherPrior), 'invalid_request');
assertM4ABehavior(count($database->revisions) === 1 && $database->events === [], 'Cross-site ancestry rejection must add no revision or event.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$mutablePrior = $database->addRevision(10, 'draft', $briefId);
$mutableError = expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId, $mutablePrior), 'conflict');
assertM4ABehavior(str_contains($mutableError->getMessage(), (string) $mutablePrior), 'Mutable ancestry must be rejected by the one-mutable-draft rule.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$immutablePrior = $database->addRevision(10, 'changes_requested', $briefId);
$successor = SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId, $immutablePrior);
assertM4ABehavior($successor['based_on_revision_id'] === $immutablePrior && count($database->revisions) === 2, 'Same-site immutable changes_requested ancestry must permit a successor.');

$database = m4aDatabase();
$archivedBrief = $database->addBrief(50);
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 50, $archivedBrief), 'invalid_transition');
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(3, 10, $database->addBrief(10)), 'unauthorized');
assertM4ABehavior($database->revisions === [] && $database->events === [], 'Archived/non-admin revision attempts must create no revision or event.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$database->failRevisionInsert = true;
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId), 'database_failure');
assertM4ABehavior($database->revisions === [] && $database->events === [] && $database->rollbackCount === 1, 'Revision insert failure must roll back without a partial revision or false event.');

$database = m4aDatabase();
$briefId = $database->addBrief(10);
$database->failEventInsert = true;
expectM4ABehaviorError(static fn () => SiteRevisionManager::createAuthoredDraftRevision(1, 10, $briefId), 'database_failure');
assertM4ABehavior($database->revisions === [] && $database->events === [] && $database->rollbackCount === 1, 'Revision event failure must roll back the revision and false event.');

// Actual admin workspace list/detail reads.
$database = m4aDatabase();
$briefId = $database->addBrief(10);
$revisionId = $database->addRevision(10, 'draft', $briefId);
$database->approvals[1100] = [
    'id' => 1100, 'site_id' => 10, 'revision_id' => $revisionId,
    'approval_type' => 'internal', 'state' => 'requested',
    'requested_at' => '2026-09-02 13:00:00', 'decided_at' => null, 'revoked_at' => null,
    'comments' => 'unsafe private comments', 'reason' => 'unsafe private reason',
    'metadata_json' => '{"secret":"unsafe"}',
];
$adminSites = SiteAdminWorkspace::listSites(1);
$superSites = SiteAdminWorkspace::listSites(2, ['purpose' => '247sp']);
assertM4ABehavior(count($adminSites) === 5 && count($superSites) === 3, 'Internal Admin and Super Admin must execute listSites successfully with filters.');
$listed247sp = array_values(array_filter($adminSites, static fn (array $site): bool => $site['id'] === 10))[0];
assertM4ABehavior($listed247sp['business_id'] === 100 && $listed247sp['revision_count'] === 1
    && $listed247sp['brief_count'] === 1 && $listed247sp['mutable_revision_id'] === $revisionId,
    'Workspace list must summarize the active association, histories, and mutable revision correctly.');
expectM4ABehaviorError(static fn () => SiteAdminWorkspace::listSites(3), 'unauthorized');
expectM4ABehaviorError(static fn () => SiteAdminWorkspace::listSites(1, ['purpose' => 'forged']), 'invalid_request');
expectM4ABehaviorError(static fn () => SiteAdminWorkspace::listSites(1, ['lifecycle_status' => 'forged']), 'invalid_request');

$detail = SiteAdminWorkspace::siteDetail(1, 10);
$superDetail = SiteAdminWorkspace::siteDetail(2, 10);
assertM4ABehavior($detail['business_association']['business_id'] === 100 && $superDetail['site']['id'] === 10, 'Internal Admin and Super Admin must execute siteDetail with the correct association.');
assertM4ABehavior(count($detail['briefs']) === 1 && count($detail['revisions']) === 1 && count($detail['approvals']) === 1, 'Workspace detail must expose brief, revision, and approval history.');
assertM4ABehavior($detail['mutable_revision']['id'] === $revisionId, 'Workspace detail must identify the mutable revision.');
assertM4ABehavior($detail['approvals'][0]['approval_type'] === 'internal' && $detail['approvals'][0]['state'] === 'requested'
    && $detail['approvals'][0]['revision_id'] === $revisionId, 'Workspace detail must return the correct safe approval summary.');
assertM4ABehavior(array_intersect(['comments', 'reason', 'metadata_json'], array_keys($detail['approvals'][0])) === [], 'Workspace approval read model must omit unsafe comments, reasons, and metadata.');
assertM4ABehavior(SiteAdminWorkspace::siteDetail(1, 20)['business_association'] === null, 'EMD workspace detail must not fabricate a business.');
assertM4ABehavior(SiteAdminWorkspace::siteDetail(1, 30)['business_association'] === null, 'Internal-demo workspace detail must not fabricate a business.');
expectM4ABehaviorError(static fn () => SiteAdminWorkspace::siteDetail(3, 10), 'unauthorized');

echo "Website platform M4A service behavior: {$assertions} assertions passed.\n";
