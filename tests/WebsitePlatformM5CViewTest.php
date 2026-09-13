<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM5CViewFixture.php';
$assertions = 0;
function checkM5C(bool $ok, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$ok) throw new RuntimeException($message);
}
Session::start();
$documents = m5cDocuments();
$initial = m5cDom($documents['initial']);
checkM5C($initial->query('//fieldset/legend')->length === 6, 'Every rendered action has its own legend');
checkM5C($initial->query('//textarea[@required]')->length === 3, 'Feedback, image instructions and changes remain required');
foreach ($initial->query('//textarea|//select') as $control) {
    checkM5C($control->parentNode->nodeName === 'label' && trim($control->parentNode->textContent) !== '', 'Control retains a visible accessible name');
    if ($control->nodeName === 'textarea') {
        $id = $control->getAttribute('aria-describedby');
        checkM5C($id === 'customer-review-guidance' && $initial->query('//*[@id="' . $id . '"]')->length === 1, 'Text limits are associated through a unique static description');
    }
}
foreach (['initial', 'long', 'readonly', 'unavailable', 'preview'] as $state) {
    checkM5C(m5cDom($documents[$state])->query('//*[@autofocus]')->length === 0, 'Normal GET has no forced focus: ' . $state);
}
foreach (['receipt', 'approved', 'changes'] as $state) {
    $dom = m5cDom($documents[$state]);
    checkM5C($dom->query('//*[@role="status" and @tabindex="-1" and @autofocus]')->length === 1, 'One receipt is focusable outside tab order: ' . $state);
}
foreach (['approved', 'changes', 'readonly', 'unavailable'] as $state) {
    checkM5C(m5cDom($documents[$state])->query('//form')->length === 0, 'Read-only/terminal states retain no mutation forms: ' . $state);
}
checkM5C(str_contains($documents['approved'], 'Approved by customer; awaiting internal review.'), 'Approval receipt retains internal review boundary');
checkM5C(str_contains($documents['changes'], 'Changes requested'), 'Changes receipt remains readable');
checkM5C(str_contains($documents['long'], str_repeat('reference', 12)), 'Long accepted prose is preserved without truncation');
$error = m5cDom($documents['error']);
foreach (['//html[@lang="en"]', '//title', '//meta[@name="viewport"]', '//main//h1', '//*[@role="alert" and @tabindex="-1" and @autofocus]', '//a[@href="website-manager.php"]'] as $query) {
    checkM5C($error->query($query)->length === 1, 'Error document provides semantics and recovery: ' . $query);
}
$unsafe = '<img src=x onerror=alert(1)>" autofocus id="customer-text';
foreach ([m5cReview(null, $unsafe), m5cError($unsafe)] as $html) {
    checkM5C(!str_contains($html, '<img') && str_contains($html, '&lt;img'), 'Receipt/error escapes untrusted text');
    checkM5C(m5cDom($html)->query('//*[@autofocus]')->length === 1, 'Text cannot create a second focus target');
}
checkM5C(!str_contains(m5cReview(null, 'Website settings saved.', true), 'autofocus'), 'Legacy save never gains generic receipt focus');
foreach ($documents as $state => $html) {
    foreach (['METADATA-SENTINEL', 'INTERNAL-COMMENT', 'INTERNAL-REASON', 'SOURCE-SENTINEL', 'FACTS-SENTINEL', 'CORRELATION-SENTINEL', 'actor_user_id', 'payload_hash', 'submission_key_hash', 'storage_key', 'private reason'] as $secret) {
        checkM5C(!str_contains($html, $secret), 'No private projection leakage in ' . $state . ': ' . $secret);
    }
}
$preview = m5cDom($documents['preview']);
$frame = $preview->query('//iframe')->item(0);
checkM5C($frame->hasAttribute('sandbox') && $frame->getAttribute('sandbox') === '', 'Preview sandbox remains empty');
checkM5C(!preg_match('/<(?:a|form|script)\b/i', $frame->getAttribute('srcdoc')), 'Preview has no active navigation, forms or scripts');
session_destroy();
echo "Website platform M5C view: $assertions assertions passed.\n";
