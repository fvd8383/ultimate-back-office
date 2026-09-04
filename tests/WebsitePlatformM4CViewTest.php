<?php

declare(strict_types=1);

require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionEditor.php';
require_once __DIR__ . '/../private/classes/SiteReviewAdminWorkflow.php';
require_once __DIR__ . '/../private/classes/Csrf.php';
require_once __DIR__ . '/../private/classes/AdminPortal.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ui_button(string $label): string { return '<button type="submit">' . e($label) . '</button>'; }
function ui_alert(string $message, string $type): string { return '<div class="' . e($type) . '">' . $message . '</div>'; }
function renderM4C(array $workspace): string { ob_start(); require __DIR__ . '/../private/views/site-review.php'; return (string) ob_get_clean(); }
$assertions = 0;
function checkM4CView(bool $condition, string $message): void { global $assertions; $assertions++; if (!$condition) throw new RuntimeException($message); }
function formsM4C(string $html): array { $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); return iterator_to_array($dom->getElementsByTagName('form')); }

$db = WebsitePlatformM3ServiceDatabase::fixture(); useWebsitePlatformM3ServiceDatabase($db); $db->sites[10]['lifecycle_status'] = 'draft'; $db->revisions[100]['materiality'] = 'undetermined';
SiteCompositionEditor::apply(1, 100, ['operation' => 'initialize_new', 'expected_snapshot_hash' => $db->revisions[100]['snapshot_hash']]);
Session::start(); $_SESSION = []; $token = Csrf::token(SITE_PLATFORM_CSRF_SCOPE);
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, 'Classify materiality') && str_contains($html, 'name="reason"'), 'Materiality form and required reason render while eligible.');
checkM4CView(str_contains($html, 'Non-material') && str_contains($html, 'No effective customer-approved baseline'), 'Non-material semantics and missing-baseline warning render.');
checkM4CView(str_contains($html, 'Edit Composition') && str_contains($html, 'Preview'), 'Mutable composed revision links to composer and preview.');
checkM4CView(str_contains($html, 'Approval does not publish this site.'), 'Publication boundary is explicit.');
checkM4CView(str_contains($html, 'Based on revision</dt><dd>None') && str_contains($html, 'Restored from revision</dt><dd>None'), 'Absent ancestry renders safe None values.');
checkM4CView(str_contains($html, 'Review ready</dt><dd>Not yet'), 'Absent review-ready timestamp renders Not yet.');
foreach (formsM4C($html) as $form) checkM4CView((new DOMXPath($form->ownerDocument))->query('.//input[@name="csrf_token"]', $form)->length === 1, 'Every POST form contains CSRF.');
$db->revisions[100]['based_on_revision_id'] = 77; $db->revisions[100]['restored_from_revision_id'] = 66; $db->revisions[100]['review_ready_at'] = '2026-09-04 12:34:56';
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, 'Based on revision</dt><dd>77') && str_contains($html, 'Restored from revision</dt><dd>66'), 'Stored ancestry values render from the revision read model.');
checkM4CView(str_contains($html, 'Review ready</dt><dd>2026-09-04 12:34:56'), 'Stored review-ready timestamp renders.');

Csrf::requireValid($token, SITE_PLATFORM_CSRF_SCOPE); SiteReviewAdminWorkflow::apply(1, 100, 'classify_materiality', ['materiality' => 'material', 'reason' => 'Launch']);
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(!str_contains($html, 'Classify Materiality') && str_contains($html, 'Submit for Review'), 'Write-once classification gives way to review submission.');
SiteReviewAdminWorkflow::apply(1, 100, 'submit_for_review', []); $html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, 'Request Customer Review') && !str_contains($html, 'Approve as Customer') && !str_contains($html, 'Reject as Customer'), 'Eligible material revision exposes only customer request action.');
checkM4CView(!str_contains($html, 'Edit Composition'), 'Immutable review state has no composer link.');
$customer = SiteReviewAdminWorkflow::apply(1, 100, 'request_customer_review', ['comment' => '<script>alert(1)</script>']);
$db->approvals[$customer['approval_id']]['reason'] = '<b>escaped reason</b>';
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, 'pending customer action') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert'), 'Customer pending state keeps the approval comment escaped.');
checkM4CView(str_contains($html, 'Reason: &lt;b&gt;escaped reason&lt;/b&gt;') && !str_contains($html, '<b>escaped reason</b>'), 'Approval reason renders and remains HTML-escaped.');
$db->approvals[$customer['approval_id']]['state'] = 'approved'; $db->revisions[100]['lifecycle_status'] = 'customer_approved'; $db->sites[10]['lifecycle_status'] = 'pending_internal_review';
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100)); checkM4CView(str_contains($html, 'Request Internal Review'), 'Current customer approval enables internal request.');
$internal = SiteReviewAdminWorkflow::apply(1, 100, 'request_internal_review', []); $html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, '>Approve<') && str_contains($html, '>Reject<') && substr_count($html, 'name="approval_id"') === 2, 'Only requested internal approval exposes approve/reject controls.');
foreach (formsM4C($html) as $form) checkM4CView((new DOMXPath($form->ownerDocument))->query('.//input[@name="csrf_token"]', $form)->length === 1, 'Decision/request form contains CSRF.');
SiteReviewAdminWorkflow::apply(1, 100, 'decide_internal_approval', ['approval_id' => $internal['approval_id'], 'decision' => 'rejected']);
$html = renderM4C(SiteReviewAdminWorkflow::workspace(1, 100));
checkM4CView(str_contains($html, 'Changes requested') && str_contains($html, 'successor authored revision'), 'Rejected state gives immutable successor guidance.');
checkM4CView(!str_contains($html, 'metadata_json'), 'Raw metadata JSON is never rendered.');
$old = Csrf::token(SITE_PLATFORM_CSRF_SCOPE); Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE); checkM4CView(!Csrf::validate($old, SITE_PLATFORM_CSRF_SCOPE), 'Successful-action rotation invalidates the prior token.');
echo "Website platform M4C views: {$assertions} assertions passed.\n";
