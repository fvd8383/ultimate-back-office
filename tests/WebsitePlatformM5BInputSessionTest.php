<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/../private/classes/SiteCustomerReviewWorkflow.php';
$assertions = 0;
function checkM5BI(bool $ok, string $message): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($message); }
function denyM5BI(callable $call): void { try { $call(); } catch (SiteServiceException) { checkM5BI(true, 'Safe rejection'); return; } throw new RuntimeException('Expected rejection'); }

checkM5BI(SiteCustomerReviewInput::text("  Café\r\nplease\rthanks  ") === "Café\nplease\nthanks", 'Normalize Unicode plain text and line endings');
foreach ([str_repeat('a', 2000), str_repeat('é', 2000), str_repeat('界', 1000) . str_repeat('é', 1000)] as $text) checkM5BI(SiteCustomerReviewInput::text($text) === $text, 'At Unicode and byte limits');
foreach ([str_repeat('a', 2001), str_repeat('界', 1667), "\xff", [], ['x'], null, "a\x00b", "a\x7fb", "a\u{0085}b", '<b>text</b>', '<script>alert(1)</script>', 'javascript:alert(1)', 'data:text/html,bad', ''] as $value) denyM5BI(fn () => SiteCustomerReviewInput::text($value));
checkM5BI(SiteCustomerReviewInput::text('', false) === '', 'Optional empty comment');
checkM5BI(SiteCustomerReviewInput::text("a\tb\nc") === "a\tb\nc", 'LF and tab permitted');
foreach ([['kind' => 'unknown'], ['kind' => []], ['kind' => 'feedback', 'text' => 'hello', 'provider' => 'x'],
    ['kind' => 'feedback', 'text' => 'hello', 'target' => 'tone'],
    ['kind' => 'presentation_preference', 'target' => 'color', 'value' => 'red'],
    ['kind' => 'presentation_preference', 'target' => 'tone', 'value' => 'trust'],
    ['kind' => 'presentation_preference', 'target' => [], 'value' => 'friendly'],
    ['kind' => 'image_replacement_request', 'target' => '1', 'text' => 'replace'],
    ['kind' => 'image_replacement_request', 'target' => 'https://example.com/image', 'text' => 'replace'],
    ['kind' => 'image_replacement_request', 'target' => str_repeat('a', 64), 'text' => 'https://example.com/image'],
] as $payload) denyM5BI(fn () => SiteCustomerReviewInput::payload($payload));

$context = ['actor_user_id' => 3, 'business_id' => 50, 'site_id' => 10, 'revision_id' => 100, 'request_id' => 700, 'snapshot_hash' => str_repeat('a', 64), 'lock_version' => 0];
$_SESSION = []; $issued = SiteCustomerReviewSession::issue($context);
$resolved = SiteCustomerReviewSession::resolve($issued['handle'], $issued['nonces']['feedback'], 'feedback', 3);
checkM5BI($resolved['context'] === $context, 'Exact tuple resolved from session');
checkM5BI(strlen($issued['handle']) === 64 && count(array_unique($issued['nonces'])) === 5, 'Random opaque handle and distinct action nonces');
foreach ([['bad', $issued['nonces']['feedback'], 'feedback', 3], [$issued['handle'], [], 'feedback', 3],
    [$issued['handle'], $issued['nonces']['feedback'], 'approve_revision', 3],
    [$issued['handle'], $issued['nonces']['feedback'], 'feedback', 4],
    [$issued['handle'], str_repeat('0', 64), 'feedback', 3]] as $args) denyM5BI(fn () => SiteCustomerReviewSession::resolve(...$args));
$_SESSION['customer_reviews'][$issued['handle']]['expires'] = time() - 1;
denyM5BI(fn () => SiteCustomerReviewSession::resolve($issued['handle'], $issued['nonces']['feedback'], 'feedback', 3));
$old = SiteCustomerReviewSession::issue($context);
for ($i = 0; $i < 20; $i++) SiteCustomerReviewSession::issue($context);
checkM5BI(count($_SESSION['customer_reviews']) === 20, 'Retained handles bounded to twenty');
denyM5BI(fn () => SiteCustomerReviewSession::resolve($old['handle'], $old['nonces']['feedback'], 'feedback', 3));
foreach (['actor_user_id', 'business_id', 'site_id', 'revision_id', 'request_id', 'snapshot_hash', 'lock_version'] as $field) {
    $bad = $context; unset($bad[$field]); denyM5BI(fn () => SiteCustomerReviewGuard::context($bad));
    $bad = $context; $bad[$field] = []; denyM5BI(fn () => SiteCustomerReviewGuard::context($bad));
}
denyM5BI(fn () => SiteCustomerReviewGuard::context($context + ['provider' => 'x']));

$payload = ['kind' => 'feedback', 'text' => 'Café & more'];
$entry = $payload + ['entry_id' => SiteServiceSupport::uuidV4(), 'actor_user_id' => 3, 'created_at' => '2026-09-11T12:00:00Z',
    'submission_key_hash' => str_repeat('a', 64), 'payload_hash' => hash('sha256', SiteCustomerReviewInput::encode($payload)), 'correlation_id' => SiteServiceSupport::uuidV4()];
$metadata = ['private' => ['keep' => true], 'customer_review_v1' => ['entries' => [$entry]]];
checkM5BI(SiteCustomerFeedback::metadata(SiteCustomerReviewInput::encode($metadata))->private->keep === true, 'Unrelated metadata preserved');
$projection = SiteCustomerFeedback::projection(SiteCustomerReviewInput::encode($metadata));
checkM5BI(array_keys($projection[0]) === ['kind', 'text', 'created_at'], 'Customer projection excludes all identity and replay fields');
// MySQL JSON storage may reorder object keys. Format validation is order-independent.
ksort($entry); $metadata['customer_review_v1']['entries'] = [$entry];
checkM5BI(count(SiteCustomerFeedback::projection(SiteCustomerReviewInput::encode($metadata))) === 1, 'MySQL key reordering preserves valid history');
foreach (['{', 'null', '"text"', '{"customer_review_v2":{"entries":[]}}', '{"customer_review_v1":null}',
    '{"customer_review_v1":{"version":2,"entries":[]}}', '{"customer_review_v1":{"entries":{}}}',
    '{"customer_review_v1":{"entries":[{}]}}'] as $json) denyM5BI(fn () => SiteCustomerFeedback::metadata($json));
foreach (['payload_hash' => str_repeat('f', 64), 'actor_user_id' => '3', 'text' => 'Changed', 'provider' => 'forbidden'] as $key => $value) {
    $bad = $metadata; $bad['customer_review_v1']['entries'][0][$key] = $value; denyM5BI(fn () => SiteCustomerFeedback::metadata(SiteCustomerReviewInput::encode($bad)));
}
$bad = $metadata; $bad['customer_review_v1']['entries'] = array_fill(0, 21, $entry); denyM5BI(fn () => SiteCustomerFeedback::metadata(SiteCustomerReviewInput::encode($bad)));
$bad = $metadata; $bad['customer_review_v1']['entries'][0]['text'] = str_repeat('x', 131073); denyM5BI(fn () => SiteCustomerFeedback::metadata(SiteCustomerReviewInput::encode($bad)));
echo "Website platform M5B input/session: $assertions assertions passed.\n";
