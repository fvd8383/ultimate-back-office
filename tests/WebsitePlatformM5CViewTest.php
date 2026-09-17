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
$normalTitle = '247SP Website Manager - Ultimate Back Office';
foreach (['initial', 'long', 'readonly', 'unavailable', 'legacy', 'approved-get', 'changes-get', 'hostile'] as $state) {
    checkM5C(m5cDom($documents[$state])->query('//title')->item(0)->textContent === $normalTitle, 'Normal/later/legacy/unknown receipt GET retains exact original title: ' . $state);
}
foreach (['receipt' => 'Feedback sent', 'approved' => 'Customer approval recorded; internal review pending', 'changes' => 'Changes requested', 'hostile-feedback' => 'Feedback sent'] as $state => $prefix) {
    $dom = m5cDom($documents[$state]);
    checkM5C($dom->query('//title')->length === 1 && $dom->query('//title')->item(0)->textContent === $prefix . ' - ' . $normalTitle, 'One escaped title with exact allowlisted prefix and existing product identity: ' . $state);
    checkM5C($dom->query('//title')->item(0)->textContent !== $dom->query('//template[@data-customer-review-receipt-source]')->item(0)->textContent, 'Concise title and detailed receipt remain distinct: ' . $state);
}
foreach ([null, '', 'Website settings saved.', m5cHostileReceipt(), 'Feedback sent', 'Changes requested', 'Changes requested. extra', ' Sent for consideration; this does not change your preview.', [], ['state' => 'approved']] as $untrusted) {
    checkM5C(m5cManagerTitle($untrusted) === $normalTitle, 'Unrecognized or hostile receipt cannot supply title text');
}
foreach (['Sent for consideration; this does not change your preview.', 'Approved by customer; awaiting internal review.', 'Changes requested.'] as $message) {
    checkM5C(m5cManagerTitle($message, true) === $normalTitle, 'Legacy saved flag excludes customer title orientation');
    checkM5C(m5cManagerTitle($message, false, 'POST') === $normalTitle, 'Only receipt-bearing GET gets orientation');
}
$priorGet = $_GET; $priorPost = $_POST;
try {
    $_GET = ['status' => 'approved', 'receipt' => 'Changes requested.', 'saved' => '1'];
    $_POST = ['action' => 'approve_revision', 'text' => m5cHostileReceipt()];
    checkM5C(m5cManagerTitle() === $normalTitle, 'URL and POST data cannot spoof a title prefix without trusted flash');
} finally { $_GET = $priorGet; $_POST = $priorPost; }
$hostileFeedback = m5cDom($documents['hostile-feedback']);
checkM5C($hostileFeedback->query('//title//*|//*[@onerror]|//script[not(@src)]')->length === 0, 'Hostile feedback projection cannot create title markup, attributes or script');
checkM5C(str_contains($documents['hostile-feedback'], e(m5cHostileReceipt())), 'Hostile feedback remains escaped visible history, never title source');
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
foreach (['initial', 'long', 'readonly', 'unavailable', 'preview', 'legacy', 'approved-get', 'changes-get'] as $state) {
    $dom = m5cDom($documents[$state]);
    checkM5C($dom->query('//*[@autofocus]')->length === 0, 'Normal GET has no forced focus: ' . $state);
    checkM5C($dom->query('//*[@data-customer-review-receipt or @role="status"]')->length === 0, 'No generic announcement on normal/legacy GET: ' . $state);
    checkM5C($dom->query('//script[contains(@src,"customer-review-status")]')->length === 0, 'No announcement script on normal/legacy GET: ' . $state);
}
foreach (['receipt', 'approved', 'changes', 'hostile'] as $state) {
    $dom = m5cDom($documents[$state]);
    $receipts = $dom->query('//p[@data-customer-review-receipt and @class="site-customer-receipt" and @role="status" and @aria-live="polite" and @aria-atomic="true"]');
    checkM5C($receipts->length === 1, 'Visible receipt itself is the polite atomic status: ' . $state);
    foreach (['role="status"', 'aria-live="polite"', 'aria-atomic="true"'] as $attribute) {
        checkM5C($dom->query('//*[@' . $attribute . ']')->length === 1, 'Exactly one ' . $attribute . ': ' . $state);
    }
    checkM5C($receipts->item(0)->textContent === '' && !$receipts->item(0)->hasChildNodes(), 'Receipt starts empty with no server-rendered message: ' . $state);
    $sources = $dom->query('//template[@data-customer-review-receipt-source]');
    checkM5C($sources->length === 1 && $sources->item(0)->textContent !== '', 'Exactly one inert text source: ' . $state);
    $fallback = $dom->query('//noscript/p[@class="site-customer-receipt"]');
    checkM5C($fallback->length === 1 && $fallback->item(0)->textContent === $sources->item(0)->textContent, 'No-JS fallback retains the same message: ' . $state);
    checkM5C($dom->query('//*[contains(@class,"site-customer-announcer")]')->length === 0, 'No separate announcer: ' . $state);
    checkM5C($dom->query('//*[@tabindex]')->length === 0, 'Success has no tabindex: ' . $state);
    checkM5C($dom->query('//*[@autofocus]')->length === 0, 'Success never forces focus: ' . $state);
    checkM5C($dom->query('//script[@src="../assets/js/customer-review-status.js" and @defer]')->length === 1, 'One static same-origin deferred script: ' . $state);
    if ($state !== 'hostile') {
        $message = $sources->item(0)->textContent;
        checkM5C($dom->query('//text()[not(ancestor::template) and not(ancestor::noscript) and contains(.,"' . $message . '")]')->length === 0, 'No ordinary duplicate receipt, including terminal status label: ' . $state);
    }
}
$message = 'Sent for consideration; this does not change your preview.';
$receiptDom = m5cDom($documents['receipt']);
checkM5C($receiptDom->query('//template')->item(0)->textContent === $message, 'Expected success text is preserved in inert source');
checkM5C($receiptDom->query('//text()[not(ancestor::template) and not(ancestor::noscript) and contains(.,"' . $message . '")]')->length === 0, 'No ordinary accessible copy of the receipt before JS');
checkM5C($initial->query('//form//p[text()="A preference request is advisory and does not change your preview."]')->length === 2, 'Preference guidance retains advisory/preview boundary without repeating receipt');
foreach (['approved', 'changes', 'readonly', 'unavailable', 'approved-get', 'changes-get'] as $state) {
    checkM5C(m5cDom($documents[$state])->query('//form')->length === 0, 'Read-only/terminal states retain no mutation forms: ' . $state);
}
checkM5C(str_contains($documents['approved'], 'Approved by customer; awaiting internal review.'), 'Approval receipt retains internal review boundary');
checkM5C(m5cDom($documents['approved'])->query('//template[@data-customer-review-receipt-source]')->item(0)->textContent === 'Approved by customer; awaiting internal review.', 'Detailed approval receipt remains exactly unchanged');
checkM5C(str_contains($documents['changes'], 'Changes requested'), 'Changes receipt remains readable');
checkM5C(str_contains($documents['long'], str_repeat('reference', 12)), 'Long accepted prose is preserved without truncation');
$error = m5cDom($documents['error']);
foreach (['//html[@lang="en"]', '//title', '//meta[@name="viewport"]', '//main//h1', '//*[@role="alert" and @tabindex="-1" and @autofocus]', '//a[@href="website-manager.php"]'] as $query) {
    checkM5C($error->query($query)->length === 1, 'Error document provides semantics and recovery: ' . $query);
}
$unsafe = '<img src=x onerror=alert(1)>" autofocus id="customer-text';
foreach ([m5cReview(null, $unsafe), m5cError($unsafe)] as $index => $html) {
    checkM5C(!str_contains($html, '<img') && str_contains($html, '&lt;img'), 'Receipt/error escapes untrusted text');
    checkM5C(m5cDom($html)->query('//*[@autofocus]')->length === $index, 'Text cannot create a focus target');
    checkM5C(m5cDom($html)->query('//*[@id="customer-text" or @onerror]')->length === 0, 'Text cannot create attributes');
}
foreach (['approved-get' => 'Approved by customer; awaiting internal review.', 'changes-get' => 'Changes requested.'] as $state => $label) {
    checkM5C(str_contains($documents[$state], $label), 'Persistent terminal label remains on later GET: ' . $state);
}
checkM5C(m5cDom($documents['hostile'])->query('//*[@data-customer-review-receipt]')->length === 1, 'Hostile text cannot change selector or create another marker');
checkM5C(m5cDom($documents['hostile'])->query('//script[not(@src)]')->length === 0, 'No receipt text is embedded in executable script');
$hostileDom = m5cDom($documents['hostile']);
foreach (['//template[@data-customer-review-receipt-source]', '//noscript/p[@class="site-customer-receipt"]'] as $query) {
    $node = $hostileDom->query($query)->item(0);
    checkM5C($node->textContent === m5cHostileReceipt(), 'Hostile message survives server escaping exactly in source/fallback');
    checkM5C($hostileDom->query('.//*', $node)->length === 0, 'Escaped source/fallback contains only text, never executable elements');
}
checkM5C(str_contains($documents['hostile'], e(m5cHostileReceipt())), 'Raw source contains escaped hostile receipt');
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
