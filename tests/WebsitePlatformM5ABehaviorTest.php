<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM5ADatabase.php';

$assertions = 0;
function checkM5A(bool $ok, string $message): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($message); }
function denyM5A(callable $call): void
{
    try { $call(); } catch (SiteServiceException $e) { checkM5A(in_array($e->classification(), ['unauthorized', 'not_found', 'conflict', 'invalid_request'], true), 'Safe denial'); return; }
    throw new RuntimeException('Expected denial');
}

$db = WebsitePlatformM5ADatabase::fixture();
$before = $db->snapshot();
foreach ([3, 4, 5] as $actor) {
    $review = SiteCustomerReviewWorkflow::workspace($actor, 50);
    checkM5A($review['revision_number'] === 1 && $review['preview_available'], 'Associated Owner/Admin/lower role reads issued review');
    checkM5A($review['decision_read_only'] === ($actor === 5), 'Future decision capability is advisory');
    $preview = SiteCustomerPreview::render($actor, 50, 700);
    checkM5A(str_contains($preview['document'], 'Content pending review'), 'Actual M3 immutable stored composition renders');
}
checkM5A($before === $db->snapshot(), 'All actual read/preview services cause zero domain mutation');
checkM5A(SiteCustomerReviewWorkflow::workspace(3)['revision_number'] === 1, 'Initial default business resolves');
foreach ([1, 2, 6, 99] as $actor) denyM5A(fn () => SiteCustomerReviewWorkflow::workspace($actor, 50));
foreach ([60, 999, 0] as $business) denyM5A(fn () => SiteCustomerReviewWorkflow::workspace(3, $business));
foreach ([null, '', '0', '-1', '1x', '1.2', '01', [], '999999999999999999999999999'] as $value) denyM5A(fn () => SiteCustomerReviewWorkflow::positiveId($value));
checkM5A(SiteCustomerReviewWorkflow::positiveId('50') === 50, 'Strict scalar ID accepted');
foreach ([701, 100, 99999] as $request) denyM5A(fn () => SiteCustomerPreview::render(3, 50, $request));
$safe = json_encode($review) . $preview['document'];
foreach (['FACTS-SENTINEL', 'SOURCE-SENTINEL', 'INTERNAL-COMMENT', 'INTERNAL-REASON', 'METADATA-SENTINEL', 'CORRELATION-SENTINEL', 'storage_key', 'actor_user_id', 'snapshot_hash'] as $secret) {
    checkM5A(!str_contains($safe, $secret), 'Private value excluded: ' . $secret);
}
checkM5A(array_keys($review) === ['business_label', 'status', 'status_label', 'revision_number', 'requested_at', 'decided_at', 'preview_available', 'preview_href', 'decision_read_only', 'manager_href', 'profile_href'], 'DTO has exact allowlist');

$cases = [
    'inactive user' => fn ($d) => $d->users[3]['status'] = 'inactive',
    'inactive membership' => fn ($d) => $d->memberships[3][50]['status'] = 'inactive',
    'inactive business' => fn ($d) => $d->businesses[50]['status'] = 'inactive',
    'suspended business' => fn ($d) => $d->businesses[50]['is_suspended'] = 1,
    'business module' => fn ($d) => $d->businesses[50]['module_active'] = 0,
    'module definition' => fn ($d) => $d->moduleActive = false,
    'suspended site' => fn ($d) => $d->base->sites[10]['lifecycle_status'] = 'suspended',
    'archived site' => fn ($d) => $d->base->sites[10]['lifecycle_status'] = 'archived',
    'future state' => fn ($d) => $d->base->sites[10]['lifecycle_status'] = 'active',
    'mismatched site state' => fn ($d) => $d->base->sites[10]['lifecycle_status'] = 'draft',
    'wrong revision site' => fn ($d) => $d->base->revisions[100]['site_id'] = 20,
    'missing revision' => fn ($d) => $d->base->approvals[700]['revision_id'] = 999,
    'duplicate association' => fn ($d) => $d->associations[] = $d->associations[0],
    'extra business association' => fn ($d) => $d->associations[] = array_replace($d->associations[0], ['business_id' => 60]),
    'ambiguous requests' => fn ($d) => $d->base->approvals[701] = WebsitePlatformM5ADatabase::request(701, 100),
    'unreviewed draft' => fn ($d) => $d->base->revisions[100]['lifecycle_status'] = 'draft',
    'nonmaterial issued' => fn ($d) => $d->base->revisions[100]['materiality'] = 'non_material',
    'invalid request state' => fn ($d) => $d->base->approvals[700]['state'] = 'invalid',
    'tampered composition' => fn ($d) => $d->base->revisions[100]['snapshot_hash'] = str_repeat('f', 64),
];
foreach ($cases as $name => $change) {
    $db = WebsitePlatformM5ADatabase::fixture(); $change($db); $before = $db->snapshot();
    denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
    checkM5A($db->snapshot() === $before, 'Denied read has no domain mutation: ' . $name);
}
$db = WebsitePlatformM5ADatabase::fixture();
$db->base->sites[20] = array_replace($db->base->sites[10], ['id' => 20]);
$db->associations[] = array_replace($db->associations[0], ['site_id' => 20]);
denyM5A(fn () => SiteCustomerReviewWorkflow::workspace(3, 50));

foreach (['undetermined', 'non_material', 'material'] as $materiality) {
    $db = WebsitePlatformM5ADatabase::fixture();
    $db->base->revisions[101] = array_replace($db->base->revisions[100], ['id' => 101, 'revision_number' => 2, 'materiality' => $materiality, 'lifecycle_status' => 'draft']);
    $review = SiteCustomerReviewWorkflow::workspace(3, 50);
    checkM5A($materiality === 'material' ? !$review['preview_available'] && $review['revision_number'] === null : $review['revision_number'] === 1, 'Only a newer material revision invalidates issued review');
    if ($materiality === 'material') denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
}
foreach (['approved' => 'customer_approved', 'rejected' => 'changes_requested'] as $state => $revisionState) {
    $db = WebsitePlatformM5ADatabase::fixture();
    $db->base->approvals[700]['state'] = $state; $db->base->approvals[700]['decided_at'] = '2026-09-10 13:00:00';
    $db->base->revisions[100]['lifecycle_status'] = $revisionState;
    $db->base->sites[10]['lifecycle_status'] = $state === 'approved' ? 'pending_internal_review' : 'draft';
    checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['status'] === $revisionState, 'Allowed receipt state');
    if ($state === 'approved') {
        $db->base->revisions[100]['lifecycle_status'] = 'internally_approved';
        $db->base->sites[10]['lifecycle_status'] = 'approved';
        checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['status'] === 'internally_approved', 'Internal receipt state not deployment');
    }
}
foreach (['superseded', 'revoked'] as $state) {
    $db = WebsitePlatformM5ADatabase::fixture(); $db->base->approvals[700]['state'] = $state;
    checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['preview_href'] === null, 'Closed request cannot disclose old preview');
    denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
}
$db = WebsitePlatformM5ADatabase::fixture();
$db->base->approvals[699] = array_replace(WebsitePlatformM5ADatabase::request(699, 100), ['state' => 'superseded']);
checkM5A(str_contains(SiteCustomerReviewWorkflow::workspace(3, 50)['preview_href'], 'request_id=700'), 'Newest same-revision request selected');
denyM5A(fn () => SiteCustomerPreview::render(3, 50, 699));
$db->base->approvals[700]['state'] = 'superseded';
$db->base->approvals[699]['state'] = 'requested';
checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['status'] === 'unavailable', 'Never falls back to older open history');
$db = WebsitePlatformM5ADatabase::fixture(); $db->base->approvals = [];
checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['status'] === 'not_issued', 'No request is a normal state');
$db->associations = [];
checkM5A(SiteCustomerReviewWorkflow::workspace(3, 50)['status'] === 'not_issued', 'No site is a normal state');
denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
foreach (['emd', 'internal_demo'] as $purpose) {
    $db = WebsitePlatformM5ADatabase::fixture(); $db->base->sites[10]['purpose'] = $purpose;
    denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
}
foreach (['membership', 'module', 'association', 'role'] as $race) {
    $db = WebsitePlatformM5ADatabase::fixture();
    $db->beforeTransaction = static function ($d) use ($race): void {
        match ($race) {
            'membership' => $d->memberships[3][50]['status'] = 'inactive',
            'module' => $d->moduleActive = false,
            'association' => $d->associations[0]['business_id'] = 60,
            'role' => $d->users[3]['roles'] = ['Admin'],
        };
    };
    denyM5A(fn () => SiteCustomerPreview::render(3, 50, 700));
}
echo "Website platform M5A behavior: {$assertions} assertions passed.\n";
