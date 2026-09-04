<?php

declare(strict_types=1);

require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionEditor.php';
require_once __DIR__ . '/../private/classes/SiteReviewAdminWorkflow.php';

$assertions = 0;
function checkM4C(bool $condition, string $message): void { global $assertions; $assertions++; if (!$condition) throw new RuntimeException($message); }
function rejectM4C(callable $call, string $classification): void {
    try { $call(); } catch (SiteServiceException $e) { checkM4C($e->classification() === $classification, "Expected $classification, got {$e->classification()}: {$e->getMessage()}"); return; }
    catch (InvalidArgumentException) { checkM4C($classification === 'invalid_request', 'Expected safe invalid request.'); return; }
    throw new RuntimeException("Expected $classification");
}
function fixtureM4C(): WebsitePlatformM3ServiceDatabase {
    $db = WebsitePlatformM3ServiceDatabase::fixture(); useWebsitePlatformM3ServiceDatabase($db);
    $db->sites[10]['lifecycle_status'] = 'draft'; $db->revisions[100]['materiality'] = 'undetermined';
    SiteCompositionEditor::apply(1, 100, ['operation' => 'initialize_new', 'expected_snapshot_hash' => $db->revisions[100]['snapshot_hash']]);
    return $db;
}
function customerApprovalM4C(WebsitePlatformM3ServiceDatabase $db, int $revisionId = 100): int {
    $id = 800 + count($db->approvals); $db->approvals[$id] = ['id' => $id, 'site_id' => 10, 'revision_id' => $revisionId,
        'approval_type' => 'customer', 'state' => 'approved', 'actor_user_id' => 9, 'actor_type' => 'customer', 'comments' => 'Customer approved',
        'reason' => null, 'supersedes_approval_id' => null, 'requested_at' => '2026-09-04', 'decided_at' => '2026-09-04',
        'revoked_at' => null, 'correlation_id' => 'm5-customer', 'metadata_json' => '{}', 'created_at' => '2026-09-04']; return $id;
}

$db = fixtureM4C(); $before = serialize([$db->sites, $db->revisions, $db->approvals, $db->events]);
$workspace = SiteReviewAdminWorkflow::workspace(1, 100);
checkM4C($workspace['site']['id'] === 10 && $workspace['revision']['site_id'] === 10, 'Read model resolves revision-owned site.');
checkM4C($workspace['capabilities']['can_classify_materiality'] && !$workspace['capabilities']['has_effective_customer_baseline'], 'Initial capabilities are safe and baseline-aware.');
checkM4C($workspace['approvals'] === [] && serialize([$db->sites, $db->revisions, $db->approvals, $db->events]) === $before, 'Workflow read loads timeline without mutation.');
$db->internalRole = 'Super Admin'; checkM4C(SiteReviewAdminWorkflow::workspace(1, 100)['site']['id'] === 10, 'Super Admin is authorized.');
$db->internalAdmin = false; rejectM4C(fn () => SiteReviewAdminWorkflow::workspace(1, 100), 'unauthorized'); $db->internalAdmin = true; $db->internalRole = 'Admin';

rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'forged', 'reason' => 'x']), 'invalid_request');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'material', 'reason' => '']), 'invalid_request');
$events = count($db->events); SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'material', 'reason' => 'Customer-visible launch']);
checkM4C($db->revisions[100]['materiality'] === 'material' && count($db->events) === $events + 1, 'Material classification delegates and audits once.');
$afterClassificationEvents = count($db->events); rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'non_material', 'reason' => 'again']), 'conflict');
checkM4C(count($db->events) === $afterClassificationEvents, 'Failed reclassification creates no event.');
SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []);
checkM4C($db->revisions[100]['lifecycle_status'] === 'ready_for_review', 'Valid stored composition passes the M3 review gate.');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'material', 'reason' => 'late']), 'conflict');

$requested = SiteReviewAdminWorkflow::apply(1, 100, 'request_customer_review', ['comment' => '<safe> request']);
checkM4C($requested['approval_type'] === 'customer' && $db->sites[10]['lifecycle_status'] === 'pending_customer', 'Material customer request uses existing approval/site lifecycle service.');
$duplicate = SiteReviewAdminWorkflow::apply(1, 100, 'request_customer_review', []);
checkM4C($duplicate['idempotent'] && count($db->approvals) === 1, 'Duplicate customer request is one durable logical request.');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'decide_internal_approval', ['approval_id' => $requested['approval_id'], 'decision' => 'approved']), 'invalid_request');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'customer_approve', ['approval_id' => $requested['approval_id']]), 'invalid_request');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', []), 'invalid_transition');

$db->approvals[$requested['approval_id']]['state'] = 'approved'; $db->approvals[$requested['approval_id']]['decided_at'] = '2026-09-04';
$db->revisions[100]['lifecycle_status'] = 'customer_approved'; $db->sites[10]['lifecycle_status'] = 'pending_internal_review';
$internal = SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', ['comment' => 'Internal QA']);
checkM4C($internal['approval_type'] === 'internal' && count($db->approvals) === 2, 'Material internal request waits for current customer approval.');
checkM4C(SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', [])['idempotent'], 'Duplicate internal request is idempotent.');
SiteReviewAdminWorkflow::apply(1, 100, 'decide_internal_approval', ['approval_id' => $internal['approval_id'], 'decision' => 'approved', 'comment' => 'Approved']);
checkM4C($db->revisions[100]['lifecycle_status'] === 'internally_approved' && $db->sites[10]['lifecycle_status'] === 'approved', 'Internal approval keeps existing approved lifecycle path.');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'decide_internal_approval', ['approval_id' => $internal['approval_id'], 'decision' => 'rejected']), 'invalid_request');

$db = fixtureM4C(); SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'material', 'reason' => 'Material']); SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []);
$customer = customerApprovalM4C($db); $db->revisions[100]['lifecycle_status'] = 'customer_approved'; $db->sites[10]['lifecycle_status'] = 'pending_internal_review';
$internal = SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', []);
SiteReviewAdminWorkflow::apply(1, 100, 'decide_internal_approval', ['approval_id' => $internal['approval_id'], 'decision' => 'rejected']);
checkM4C($db->revisions[100]['lifecycle_status'] === 'changes_requested' && $db->sites[10]['lifecycle_status'] === 'draft', 'Internal rejection keeps existing changes-requested path.');
checkM4C($db->approvals[$customer]['state'] === 'superseded', 'Rejection preserves approval supersession.');

$db = fixtureM4C(); SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'non_material', 'reason' => 'Technical']); SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []);
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'request_customer_review', []), 'invalid_transition');
rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', []), 'invalid_transition');
$db->revisions[90] = $db->revisions[100]; $db->revisions[90]['id'] = 90; $db->revisions[90]['revision_number'] = 0; $db->revisions[90]['lifecycle_status'] = 'customer_approved';
customerApprovalM4C($db, 90); $internal = SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', []);
checkM4C($internal['approval_type'] === 'internal', 'Non-material request uses effective earlier customer-approved baseline.');

$db = fixtureM4C(); $events = count($db->events); rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []), 'conflict');
checkM4C($db->revisions[100]['lifecycle_status'] === 'draft' && count($db->approvals) === 0 && count($db->events) === $events, 'Undetermined review failure has no partial lifecycle, approval, or false event.');
$db->revisions[100]['materiality'] = 'material'; $db->revisions[100]['snapshot_hash'] = str_repeat('f', 64); rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []), 'conflict');
checkM4C($db->revisions[100]['lifecycle_status'] === 'draft' && count($db->approvals) === 0, 'Tampered stored composition fails closed.');
$db = WebsitePlatformM3ServiceDatabase::fixture(); useWebsitePlatformM3ServiceDatabase($db); $db->sites[10]['lifecycle_status'] = 'draft';
$events = count($db->events); rejectM4C(fn () => SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []), 'conflict');
checkM4C(count($db->events) === $events && $db->revisions[100]['lifecycle_status'] === 'draft', 'Structurally empty composition fails without a false event or transition.');

echo "Website platform M4C behavior: {$assertions} assertions passed.\n";
