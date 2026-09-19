<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BScope.php';
$root = dirname(__DIR__);
$baseline = 'a2288b041ce7ff116fed839c7702fd64b9767770';
$assertions = 0;
function checkM5CScope(bool $ok, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$ok) throw new RuntimeException($message);
}
function m5cBaseline(string $path): string
{
    global $root, $baseline;
    $text = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($baseline . ':' . $path));
    if (!is_string($text)) throw new RuntimeException('Missing authoritative baseline');
    return str_replace("\r\n", "\n", $text);
}
$css = str_replace("\r\n", "\n", file_get_contents($root . '/public/app/assets/css/design-system.css'));
$start = strpos($css, '/* Customer review prose');
$end = strpos($css, '.website-manager-form,', $start === false ? 0 : $start);
checkM5CScope($start !== false && $end !== false, 'Customer CSS block has explicit scope boundaries');
$block = substr($css, $start, $end - $start);
$baselineCss = m5cBaseline('public/app/assets/css/design-system.css');
checkM5CScope($css === $baselineCss, 'All stylesheet rules remain byte-equivalent to approval-title correction baseline');
$rules = preg_replace('~/\*.*?\*/~s', '', $block);
preg_match_all('/([^{}]+)\{([^{}]*)\}/', $rules, $matches, PREG_SET_ORDER);
checkM5CScope(count($matches) === 4, 'Only four existing customer rules remain');
foreach ($matches as $rule) {
    checkM5CScope(str_starts_with(trim($rule[1]), '.site-customer-'), 'Each rule is scoped to customer review surfaces');
    checkM5CScope(!preg_match('/overflow\s*:|overflow-x\s*:|hidden|clip|ellipsis|url\s*\(|@import|position\s*:/i', $rule[2]), 'No clipping, hiding, external assets or positioning workaround');
}
$routePath = 'public/app/247sp/website-manager.php';
$route = str_replace("\r\n", "\n", file_get_contents($root . '/' . $routePath));
$titleStart = strpos($route, '$pageTitle =');
$titleEnd = strpos($route, '$bodyClass =', $titleStart === false ? 0 : $titleStart);
checkM5CScope($titleStart !== false && $titleEnd !== false, 'Page title has narrow presentation boundaries');
$titleBlock = substr($route, $titleStart, $titleEnd - $titleStart);
$oldApprovalArm = "    'Approved by customer; awaiting internal review.' => 'Website approved',";
$newApprovalArm = "    'Approved by customer; awaiting internal review.' => 'Customer approval recorded; internal review pending',";
$restoredRoute = str_replace($newApprovalArm, $oldApprovalArm, $route, $replacements);
checkM5CScope($replacements === 1, 'Exactly one approval-prefix match arm is corrected; matched receipt string is unchanged');
checkM5CScope(m5cBaseline($routePath) === $restoredRoute, 'The entire route differs only by the approval-prefix output string: all other titles, conditions, auth, CSRF, POST dispatch, 303, errors and legacy behavior unchanged');
checkM5CScope(!preg_match('/\$_(?:GET|POST|SESSION)|\$customerReview|\$business|\$user|metadata|feedback\[|header\s*\(|echo\b|require\b|include\b/i', $titleBlock), 'Title block does not read request data, business content or metadata, or affect routing');
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- ' . escapeshellarg($path) . m6bScopeExclusions(), $unused, $status);
    checkM5CScope($status === 0, 'Protected security/lifecycle/schema/runtime tree unchanged: ' . $path);
}
checkM5CScope(m6bOnlyMigration025($root), 'Only the separately authorized M6B migration 025 may exist after 024.');
$correctionBaseline = $baseline;
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared', 'private/views', 'public/app/assets/js', 'public/app/assets/css'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($correctionBaseline) . ' -- ' . escapeshellarg($path) . m6bScopeExclusions(), $unused, $status);
    checkM5CScope($status === 0, 'Approval-title clarification preserves merged backend/view/asset baseline: ' . $path);
}
$assetPath = 'public/app/assets/js/customer-review-status.js';
checkM5CScope(glob($root . '/public/app/assets/js/*') === [$root . '/' . $assetPath], 'Exactly one dedicated application JS asset');
$js = file_get_contents($root . '/' . $assetPath);
checkM5CScope(!preg_match('/innerHTML|outerHTML|\beval\b|new\s+Function|\.focus\s*\(|fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|https?:|document\.write|\.src\s*=|\.href\s*=|localStorage|sessionStorage|cookie|cloneNode|setAttribute|removeAttribute/i', $js), 'Receipt performs no HTML execution, focus, network, storage, cloning or ARIA mutation');
checkM5CScope(str_contains($js, 'receipt.textContent = source.content.textContent'), 'Receipt reads inert template plain text only');
checkM5CScope(str_contains($js, "receipt.textContent === ''"), 'One-time population guard');
checkM5CScope(str_contains($js, 'receipts.length !== 1') && str_contains($js, 'sources.length !== 1'), 'Exactly one receipt and one source required');
checkM5CScope(substr_count($js, 'requestAnimationFrame(') === 1 && substr_count($js, 'setTimeout(') === 1, 'Single rendered-frame/later-task cycle retained');
$appChanges = [];
exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($correctionBaseline) . ' -- . ":(exclude)tests" ":(exclude)docs"' . m6bScopeExclusions(), $appChanges, $status);
checkM5CScope($status === 0 && $appChanges === [$routePath], 'Only the historical Website Manager title change and exact authorized M6B paths may differ');
echo "Website platform M5C scope: $assertions assertions passed.\n";
