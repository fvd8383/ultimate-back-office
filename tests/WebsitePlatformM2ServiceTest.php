<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteApprovalManager.php';

$assertions = 0;
function assertM2Service(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectM2Error(callable $callback, string $classification): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM2Service($exception->classification() === $classification, "Expected {$classification}; got {$exception->classification()}.");
        return;
    }
    throw new RuntimeException("Expected {$classification} service error.");
}

$support = file_get_contents(__DIR__ . '/../private/classes/SiteServiceSupport.php');
$policy = file_get_contents(__DIR__ . '/../private/classes/SiteAuthorizationPolicy.php');
$sites = file_get_contents(__DIR__ . '/../private/classes/SiteManager.php');
$revisions = file_get_contents(__DIR__ . '/../private/classes/SiteRevisionManager.php');
$approvals = file_get_contents(__DIR__ . '/../private/classes/SiteApprovalManager.php');
if (!is_string($support) || !is_string($policy) || !is_string($sites)
    || !is_string($revisions) || !is_string($approvals)) {
    throw new RuntimeException('M2 service files must be readable.');
}

foreach ([
    'invalid_request', 'not_found', 'unauthorized', 'invalid_transition',
    'immutable_revision', 'stale_write', 'conflict', 'future_gate_required', 'database_failure',
] as $classification) {
    $error = new SiteServiceException($classification, 'Safe message.');
    assertM2Service($error->classification() === $classification, "{$classification} must be supported.");
}
assertM2Service((new SiteServiceException('unknown', 'unsafe'))->classification() === 'database_failure', 'Unknown errors must collapse safely.');
assertM2Service((new SiteServiceException('unknown', 'unsafe'))->getMessage() === 'The website operation could not be completed.', 'Unknown messages must not leak.');

$generatedCorrelation = SiteServiceSupport::correlationId(null);
assertM2Service(preg_match('/^site:[a-f0-9]{32}$/', $generatedCorrelation) === 1, 'Generated correlation IDs must be safe and random.');
assertM2Service(SiteServiceSupport::correlationId('M2.review_1:test-2') === 'M2.review_1:test-2', 'Safe supplied correlation IDs must survive.');
expectM2Error(static fn () => SiteServiceSupport::correlationId('bad correlation'), 'invalid_request');
expectM2Error(static fn () => SiteServiceSupport::correlationId(str_repeat('a', 101)), 'invalid_request');
expectM2Error(static fn () => SiteServiceSupport::positiveId(0, 'ID'), 'invalid_request');
expectM2Error(static fn () => SiteServiceSupport::reason(''), 'invalid_request');
expectM2Error(static fn () => SiteServiceSupport::assertSnapshotHash('abc'), 'invalid_request');
expectM2Error(static fn () => SiteServiceSupport::snapshotJson('<script>', 'Snapshot'), 'invalid_request');
assertM2Service(SiteServiceSupport::assertSnapshotHash(str_repeat('A', 64)) === str_repeat('a', 64), 'Snapshot hashes must normalize.');
assertM2Service(SiteServiceSupport::snapshotJson(['safe' => true], 'Snapshot') === '{"safe":true}', 'Snapshot arrays must encode as inert JSON data.');
assertM2Service(preg_match('/^[a-f0-9-]{36}$/', SiteServiceSupport::uuidV4()) === 1, 'Site keys must be UUID-shaped.');
assertM2Service(substr(SiteServiceSupport::uuidV4(), 14, 1) === '4', 'Site keys must use UUID version 4.');

assertM2Service(SiteManager::PURPOSES === ['247sp', 'emd', 'internal_demo'], 'Only approved site purposes may be created.');
assertM2Service(SiteManager::FUTURE_GATED_STATES === ['active', 'cancellation_pending', 'conversion_pending'], 'Future site states must remain explicit.');
assertM2Service(SiteManager::TRANSITIONS['archived'] === [], 'Archive must be terminal in M2.');
assertM2Service(!in_array('active', SiteManager::TRANSITIONS['approved'], true), 'Active must be unreachable in M2.');
assertM2Service(!method_exists(SiteManager::class, 'changePurpose'), 'Purpose mutation must be unavailable.');
assertM2Service(!method_exists(SiteManager::class, 'convertSite'), 'Conversion must be unavailable.');
assertM2Service(str_contains($sites, "targetStatus === 'demo'") && str_contains($sites, "!== 'internal_demo'"), 'Demo state must be internal-demo-only.');
assertM2Service(str_contains($sites, 'current_published_revision_id') && str_contains($sites, 'deployment retirement'), 'Published sites must not archive in M2.');
assertM2Service(str_contains($sites, 'lock_version = lock_version + 1'), 'Lifecycle writes must increment lock_version.');
assertM2Service(str_contains($sites, "'stale_write'"), 'Stale lifecycle writes must have a stable classification.');
assertM2Service(str_contains($sites, 'FROM businesses b WHERE b.id = :business_id FOR UPDATE'), '247SP creation must lock the business.');
assertM2Service(str_contains($sites, 'FROM site_business_associations sba') && str_contains($sites, 'FOR UPDATE'), 'Duplicate 247SP association checks must lock relevant rows.');
assertM2Service(str_contains($sites, 'm.module_key = :module_key') && str_contains($sites, 'm.is_active = 1'), '247SP creation must enforce active module access.');

assertM2Service(in_array('validation_failed', SiteRevisionManager::TRANSITIONS['draft'], true), 'Draft may fail validation.');
assertM2Service(in_array('ready_for_review', SiteRevisionManager::TRANSITIONS['validation_failed'], true), 'Validation-failed may become review-ready.');
assertM2Service(in_array('ready_for_review', SiteRevisionManager::TRANSITIONS['restored'], true), 'Restore candidates may become review-ready.');
assertM2Service(SiteRevisionManager::TRANSITIONS['changes_requested'] === [], 'Changes-requested revisions must be terminal.');
assertM2Service(SiteRevisionManager::TRANSITIONS['superseded'] === [], 'Superseded revisions must be terminal.');
assertM2Service(!in_array('published', SiteRevisionManager::TRANSITIONS['internally_approved'], true), 'M2 must not publish revisions.');

$mutableAssertion = new ReflectionMethod(SiteRevisionManager::class, 'assertRevisionMutableForCompositionRow');
$mutableAssertion->invoke(null, ['lifecycle_status' => 'draft']);
$mutableAssertion->invoke(null, ['lifecycle_status' => 'validation_failed']);
foreach (['ready_for_review', 'customer_approved', 'internally_approved', 'published', 'changes_requested', 'superseded', 'restored'] as $immutable) {
    expectM2Error(static fn () => $mutableAssertion->invoke(null, ['lifecycle_status' => $immutable]), 'immutable_revision');
}

assertM2Service(str_contains($revisions, 'COALESCE(MAX(revision_number), 0) + 1'), 'Revision numbering must allocate the next site number.');
assertM2Service(strpos($revisions, 'SiteManager::lockSite($connection, $siteId)') < strpos($revisions, 'nextRevisionNumber($connection, $siteId)'), 'Site lock must precede revision allocation.');
assertM2Service(str_contains($revisions, 'assertSameSiteRevision') && str_contains($revisions, 'assertSameSiteBrief'), 'Revision ancestry and brief ownership must be checked.');
assertM2Service(str_contains($revisions, "!== 'undetermined'"), 'Materiality must be write-once.');
assertM2Service(str_contains($revisions, 'material_successor_revision'), 'Material successors must supersede older customer approvals.');
assertM2Service(str_contains($revisions, "\$materiality === 'material'"), 'Supersession must be conditional on materiality.');
assertM2Service(str_contains($revisions, 'Every revision page requires at least one section.'), 'Review readiness must require sections on every page.');
assertM2Service(str_contains($revisions, 'Exactly one same-site revision theme is required.'), 'Review readiness must require one theme.');
assertM2Service(str_contains($revisions, 'sps.revision_page_id <> sra.site_revision_page_id'), 'Asset page/section consistency must be checked.');
assertM2Service(str_contains($revisions, "'lifecycle_status' => 'restored'"), 'Restore must create an unpublished restored revision.');
assertM2Service(str_contains($revisions, "'materiality' => 'material'"), 'Restore must be material.');
assertM2Service(str_contains($revisions, '$pageMap') && str_contains($revisions, '$sectionMap'), 'Restore must remap page and section identifiers.');
assertM2Service(str_contains($revisions, "'asset_id' => \$asset['asset_id']"), 'Restore must reuse site assets.');
assertM2Service(!preg_match('/INSERT INTO site_assets/', $revisions), 'Restore must not duplicate asset rows.');

assertM2Service(SiteApprovalManager::IMPLEMENTED_TYPES === ['customer', 'internal'], 'Only customer/internal approvals are implemented.');
assertM2Service(SiteApprovalManager::FUTURE_GATED_TYPES === ['production', 'conversion'], 'Production/conversion approvals must remain future-gated.');
assertM2Service(str_contains($approvals, 'requested_by_user_id') && str_contains($approvals, 'requested_by_actor_type'), 'Requester identity must be preserved in metadata.');
assertM2Service(str_contains($approvals, 'Only a requested approval can be decided.'), 'Second approval decisions must be rejected.');
assertM2Service(str_contains($approvals, 'Material revisions require current customer approval first.'), 'Material internal requests must be customer-gated.');
assertM2Service(str_contains($approvals, 'Non-material internal approval requires a review-ready revision.'), 'Non-material revisions may request internal approval directly.');
assertM2Service(str_contains($approvals, "\$revisionTarget = 'ready_for_review'"), 'Revocation must provide a review-ready fallback.');
assertM2Service(str_contains($approvals, "\$revisionTarget = 'customer_approved'"), 'Material internal revocation must preserve current customer approval.');
assertM2Service(str_contains($approvals, "'site_revision_changes_requested'"), 'Rejection must audit changes requested.');
assertM2Service(str_contains($approvals, 'supersedes_approval_id'), 'Truthful customer decision linkage must be supported.');

foreach (['createSite', 'transitionLifecycle', 'siteForActor', 'sitesForBusiness', 'activeBusinessAssociation'] as $method) {
    assertM2Service(method_exists(SiteManager::class, $method), "SiteManager::{$method} must exist.");
}
foreach (['createRevision', 'updateDraftSnapshot', 'assertRevisionMutableForComposition', 'classifyMateriality',
          'markReadyForReview', 'createRestoreCandidate', 'latestRevision', 'revisionForActor', 'revisionsForSite'] as $method) {
    assertM2Service(method_exists(SiteRevisionManager::class, $method), "SiteRevisionManager::{$method} must exist.");
}
foreach (['requestApproval', 'decideApproval', 'revokeApproval', 'approvalsForRevision'] as $method) {
    assertM2Service(method_exists(SiteApprovalManager::class, $method), "SiteApprovalManager::{$method} must exist.");
}

echo "Website platform M2 services: {$assertions} assertions passed.\n";
