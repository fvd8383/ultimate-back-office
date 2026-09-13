<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM5BDatabase.php';
$assertions = 0;
function checkM5B(bool $ok, string $message): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($message); }
function denyM5B(callable $call, ?string $classification = null): void {
    try { $call(); } catch (SiteServiceException $e) { checkM5B($classification === null || $classification === $e->classification(), 'Expected safe denial: ' . $e->classification()); return; }
    throw new RuntimeException('Expected denied operation');
}
function formM5B(int $actor = 3, string $action = 'feedback'): array {
    $review = SiteCustomerReviewWorkflow::workspaceWithForms($actor, 50);
    return ['action' => $action, 'review_handle' => $review['submission']['handle'], 'submission_nonce' => $review['submission']['nonces'][$action], 'text' => 'Please make the heading clearer.'];
}
function submitM5B(array $form, int $actor = 3): array { return SiteCustomerReviewWorkflow::submit($actor, $form, [], 1000); }

foreach ([3, 4] as $actor) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B($actor); $beforeHash = $db->read->base->revisions[100]['snapshot_hash'];
    $receipt = submitM5B($form, $actor);
    checkM5B($receipt['mutated'], 'Owner and business Admin can submit');
    $metadata = json_decode($db->read->base->approvals[700]['metadata_json'], true);
    checkM5B($metadata['private'] === 'METADATA-SENTINEL' && count($metadata['customer_review_v1']['entries']) === 1, 'Preserves unrelated metadata and appends once');
    checkM5B($beforeHash === $db->read->base->revisions[100]['snapshot_hash'], 'Feedback preserves immutable hash');
    $snapshot = $db->read->snapshot(); $replay = submitM5B($form, $actor);
    checkM5B(!$replay['mutated'] && $snapshot === $db->read->snapshot(), 'Replay preserves original receipt and emits no write/event');
    denyM5B(fn () => submitM5B(array_replace($form, ['text' => 'Different']), $actor), 'conflict');
}
$db = WebsitePlatformM5BDatabase::fixture();
$db->read->memberships[3][50]['business_role'] = 'Employee';
checkM5B(submitM5B(formM5B())['mutated'], 'is_owner authorizes without Owner role');
$db = WebsitePlatformM5BDatabase::fixture();
checkM5B(SiteCustomerReviewWorkflow::workspaceWithForms(5, 50)['submission'] === null, 'Lower role has no mutation controls');

$cases = [
    'actor inactive' => fn ($d) => $d->read->users[3]['status'] = 'inactive',
    'membership inactive' => fn ($d) => $d->read->memberships[3][50]['status'] = 'inactive',
    'membership removed' => fn ($d) => $d->read->memberships[3] = [],
    'lower role' => function ($d) { $d->read->memberships[3][50]['is_owner'] = 0; $d->read->memberships[3][50]['business_role'] = 'Employee'; },
    'business inactive' => fn ($d) => $d->read->businesses[50]['status'] = 'inactive',
    'business suspended' => fn ($d) => $d->read->businesses[50]['is_suspended'] = 1,
    'business module disabled' => fn ($d) => $d->read->businesses[50]['module_active'] = 0,
    'global module disabled' => fn ($d) => $d->read->moduleActive = false,
    'internal Admin grant' => fn ($d) => $d->read->users[3]['roles'] = ['Admin'],
    'Super Admin grant' => fn ($d) => $d->read->users[3]['roles'] = ['Super Admin'],
    'tenant reassigned' => fn ($d) => $d->read->associations[0]['business_id'] = 60,
    'site suspended' => fn ($d) => $d->read->base->sites[10]['lifecycle_status'] = 'suspended',
    'site archived' => fn ($d) => $d->read->base->sites[10]['lifecycle_status'] = 'archived',
    'site version' => fn ($d) => $d->read->base->sites[10]['lock_version']++,
    'hash tamper' => fn ($d) => $d->read->base->revisions[100]['snapshot_hash'] = str_repeat('f', 64),
    'request superseded' => fn ($d) => $d->read->base->approvals[700]['state'] = 'superseded',
    'request revoked' => fn ($d) => $d->read->base->approvals[700]['state'] = 'revoked',
    'request replaced' => fn ($d) => $d->read->base->approvals[701] = WebsitePlatformM5ADatabase::request(701, 100),
    'revision changes requested' => fn ($d) => $d->read->base->revisions[100]['lifecycle_status'] = 'changes_requested',
    'composition tamper' => function ($d) { $id = array_key_first($d->read->base->sections); $d->read->base->sections[$id]['configuration_json'] = '{}'; },
];
foreach ($cases as $name => $change) {
    foreach (['feedback', 'approve_revision', 'request_changes'] as $action) {
        $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, $action);
        // The change runs after preflight, immediately before the service transaction.
        $db->beforeTransaction = $change; $events = count($db->read->base->events);
        denyM5B(fn () => submitM5B($form));
        checkM5B(count($db->read->base->events) === $events && $db->read->base->approvals[700]['metadata_json'] === '{"private":"METADATA-SENTINEL"}', 'No event or append after ' . $name);
    }
}
foreach (['approve_revision' => ['approved', 'customer_approved', 'pending_internal_review'], 'request_changes' => ['rejected', 'changes_requested', 'draft']] as $action => $states) {
    $db = WebsitePlatformM5BDatabase::fixture(); $feedback = formM5B(); submitM5B($feedback);
    $form = formM5B(3, $action); if ($action === 'approve_revision') $form['text'] = '';
    $metadata = $db->read->base->approvals[700]['metadata_json']; submitM5B($form);
    checkM5B([$db->read->base->approvals[700]['state'], $db->read->base->revisions[100]['lifecycle_status'], $db->read->base->sites[10]['lifecycle_status']] === $states, 'Existing M2 lifecycle: ' . $action);
    checkM5B(count($db->read->base->approvals) === 1 && $db->read->base->sites[10]['current_published_revision_id'] === null, 'No automatic internal request or publication');
    checkM5B($metadata === $db->read->base->approvals[700]['metadata_json'], 'Decision preserves feedback metadata');
    $snapshot = $db->read->snapshot(); denyM5B(fn () => submitM5B($form), 'conflict');
    checkM5B($snapshot === $db->read->snapshot(), 'Second decision cannot overwrite winner');
    checkM5B(!submitM5B($feedback)['mutated'], 'Matching feedback receipt survives close');
    denyM5B(fn () => submitM5B(array_replace($feedback, ['text' => 'changed'])), 'conflict');
    $db->read->users[3]['status'] = 'inactive'; denyM5B(fn () => submitM5B($feedback));
}
$db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, 'request_changes');
denyM5B(fn () => submitM5B(array_replace($form, ['text' => ''])));
$db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(); $snapshot = $db->read->snapshot(); $db->failEvent = true;
denyM5B(fn () => submitM5B($form), 'database_failure');
checkM5B($snapshot === $db->read->snapshot(), 'Audit failure rolls metadata back');
foreach (['tone' => ['professional', 'friendly', 'concise'], 'emphasis' => ['services', 'trust', 'contact']] as $target => $values) {
    foreach ($values as $value) { $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, 'presentation_preference'); checkM5B(submitM5B($form + ['target' => $target, 'value' => $value])['mutated'], 'Allowed presentation preference'); }
}
$db = WebsitePlatformM5BDatabase::fixture();
checkM5B(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)['image_targets'] === [], 'No image target action for image-free composition');
for ($i = 0; $i < 20; $i++) submitM5B(formM5B());
$snapshot = $db->read->snapshot(); denyM5B(fn () => submitM5B(formM5B()), 'conflict');
checkM5B($snapshot === $db->read->snapshot(), 'Entry cap has no partial mutation');
checkM5B(submitM5B(formM5B(3, 'approve_revision'))['mutated'], 'Feedback cap does not disable decision');
$db = WebsitePlatformM5BDatabase::fixture(); $db->addImage();
$review = SiteCustomerReviewWorkflow::workspaceWithForms(3, 50); $target = array_key_first($review['image_targets']);
checkM5B(count($review['image_targets']) === 1 && is_string($target), 'Exact image usage listed');
$form = formM5B(3, 'image_replacement_request') + ['target' => $target];
$assets = $db->read->base->revisionAssets; $hash = $db->read->base->revisions[100]['snapshot_hash'];
checkM5B(submitM5B($form)['mutated'], 'Image request accepted for exact usage');
checkM5B($assets === $db->read->base->revisionAssets && $hash === $db->read->base->revisions[100]['snapshot_hash'], 'Image request does not alter assets or hash');
foreach (['1', 'https://example.com/image.jpg', str_repeat('0', 64), hash('sha256', '101:hero_image')] as $badTarget) {
    $badForm = formM5B(3, 'image_replacement_request') + ['target' => $badTarget]; denyM5B(fn () => submitM5B($badForm));
}
foreach (['rights_classification' => 'prohibited', 'rights_expires_at' => '2000-01-01', 'lifecycle_status' => 'deleted', 'business_id' => 60] as $field => $value) {
    $db = WebsitePlatformM5BDatabase::fixture(); $db->addImage(); $form = formM5B(3, 'approve_revision');
    $db->beforeTransaction = fn ($d) => $d->read->base->siteAssets[1][$field] = $value;
    $before = count($db->read->base->events); denyM5B(fn () => submitM5B($form));
    checkM5B($db->read->base->approvals[700]['state'] === 'requested' && count($db->read->base->events) === $before, 'Invalid current asset eligibility blocks approval');
}
foreach (['material', 'non_material'] as $materiality) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, 'approve_revision');
    $db->read->base->revisions[101] = array_replace($db->read->base->revisions[100], ['id' => 101, 'revision_number' => 2, 'materiality' => $materiality, 'lifecycle_status' => 'draft']);
    if ($materiality === 'material') denyM5B(fn () => submitM5B($form));
    else checkM5B(submitM5B($form)['mutated'], 'Later non-material draft alone does not replace the material review');
}
$db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B();
foreach (['business_id', 'site_id', 'revision_id', 'request_id', 'actor_user_id', 'correlation_id', 'provider'] as $field) denyM5B(fn () => submitM5B($form + [$field => '999']));
denyM5B(fn () => SiteCustomerReviewWorkflow::submit(3, $form, ['upload' => []], 1000));
denyM5B(fn () => SiteCustomerReviewWorkflow::submit(3, $form, [], 16385));
denyM5B(fn () => submitM5B(array_replace($form, ['action' => 'legacy_save'])));
denyM5B(fn () => submitM5B(array_replace($form, ['text' => ['graph' => 'bad']])));
denyM5B(fn () => submitM5B($form, 6));
foreach (['business_id' => 60, 'site_id' => 20, 'revision_id' => 101, 'request_id' => 701] as $field => $value) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B();
    $_SESSION['customer_reviews'][$form['review_handle']]['context'][$field] = $value;
    denyM5B(fn () => submitM5B($form));
}
$db = WebsitePlatformM5BDatabase::fixture();
$db->beforeTransaction = fn ($d) => $d->read->users[3]['roles'] = ['Admin'];
denyM5B(fn () => SiteApprovalManager::decideApproval(3, 700, 'approved'));
checkM5B($db->read->base->approvals[700]['state'] === 'requested', 'Existing non-M5 customer caller receives transaction-time reauthorization');
foreach (['{"customer_review_v1":null}', '{"customer_review_v2":{"entries":[]}}'] as $json) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(); $db->read->base->approvals[700]['metadata_json'] = $json;
    $snapshot = $db->read->snapshot(); denyM5B(fn () => submitM5B($form));
    checkM5B($snapshot === $db->read->snapshot(), 'Corrupt history fails closed without replacement');
}
$db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B();
$db->read->base->sites[20] = array_replace($db->read->base->sites[10], ['id' => 20]);
$db->read->associations[] = array_replace($db->read->associations[0], ['site_id' => 20]);
denyM5B(fn () => submitM5B($form));
$db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, 'approve_revision');
$db->read->base->revisions[101] = array_replace($db->read->base->revisions[100], ['id' => 101, 'revision_number' => 2, 'materiality' => 'non_material']);
$db->read->base->approvals[701] = WebsitePlatformM5ADatabase::request(701, 101);
denyM5B(fn () => submitM5B($form));
checkM5B($db->read->base->approvals[700]['state'] === 'requested', 'New issued request cannot silently switch the presentation');
foreach ([1205, 1213] as $code) {
    foreach (['feedback', 'approve_revision', 'request_changes'] as $action) {
        $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(3, $action); $snapshot = $db->read->snapshot();
        $db->failEvent = true; $db->eventErrorCode = $code;
        denyM5B(fn () => submitM5B($form), 'database_failure');
        checkM5B($snapshot === $db->read->snapshot(), 'Simulated timeout/deadlock rolls back all domain writes and events');
    }
}
// Original service receipts, not merely workflow success messages, must survive
// only a consistent decision on the still-selected review.
foreach (['customer_approved', 'internally_approved', 'changes_requested'] as $terminal) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B();
    $binding = SiteCustomerReviewSession::resolve($form['review_handle'], $form['submission_nonce'], 'feedback', 3);
    $record = fn () => SiteApprovalManager::recordCustomerFeedback(3, $binding['context'], ['kind' => 'feedback', 'text' => $form['text']], $binding['submission_key_hash']);
    $original = $record();
    submitM5B(formM5B(3, $terminal === 'changes_requested' ? 'request_changes' : 'approve_revision'));
    if ($terminal === 'internally_approved') {
        $internal = SiteApprovalManager::requestApproval(1, 100, 'internal');
        SiteApprovalManager::decideApproval(1, $internal['approval_id'], 'approved');
    }
    checkM5B($binding['context']['lock_version'] !== $db->read->base->sites[10]['lock_version'], 'Decision changes the original site version');
    $snapshot = $db->read->snapshot(); $replay = $record();
    checkM5B($replay === array_replace($original, ['replayed' => true]), 'Original receipt returned for selected ' . $terminal);
    checkM5B($snapshot === $db->read->snapshot(), 'Terminal receipt has no metadata/event/lifecycle writes');
}
$obsolete = [
    'superseded' => fn ($d) => $d->read->base->approvals[700]['state'] = 'superseded',
    'revoked' => fn ($d) => $d->read->base->approvals[700]['state'] = 'revoked',
    'revoked timestamp' => fn ($d) => $d->read->base->approvals[700]['revoked_at'] = '2026-09-13',
    'same revision replacement' => fn ($d) => $d->read->base->approvals[701] = WebsitePlatformM5ADatabase::request(701, 100),
    'newer material' => fn ($d) => $d->read->base->revisions[101] = array_replace($d->read->base->revisions[100], ['id' => 101, 'revision_number' => 2, 'lifecycle_status' => 'draft']),
    'inconsistent site' => fn ($d) => $d->read->base->sites[10]['lifecycle_status'] = 'draft',
    'inconsistent revision' => fn ($d) => $d->read->base->revisions[100]['lifecycle_status'] = 'changes_requested',
    'missing decision timestamp' => fn ($d) => $d->read->base->approvals[700]['decided_at'] = null,
];
foreach ($obsolete as $name => $change) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(); submitM5B($form);
    submitM5B(formM5B(3, 'approve_revision')); $change($db); $snapshot = $db->read->snapshot();
    denyM5B(fn () => submitM5B($form), 'conflict');
    checkM5B($snapshot === $db->read->snapshot(), 'Obsolete matching replay has no receipt/write/event: ' . $name);
}
foreach (['actor inactive', 'membership inactive', 'business inactive', 'business suspended', 'business module disabled', 'global module disabled', 'internal Admin grant', 'tenant reassigned', 'site suspended'] as $name) {
    $db = WebsitePlatformM5BDatabase::fixture(); $form = formM5B(); submitM5B($form);
    submitM5B(formM5B(3, 'approve_revision')); $cases[$name]($db); $snapshot = $db->read->snapshot();
    denyM5B(fn () => submitM5B($form));
    checkM5B($snapshot === $db->read->snapshot(), 'Matching receipt cannot bypass current authority: ' . $name);
}
echo "Website platform M5B behavior: $assertions assertions passed.\n";
