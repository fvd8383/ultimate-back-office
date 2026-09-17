<?php

declare(strict_types=1);
$root = dirname(__DIR__);
$baseline = '3c20b113a42fe2d94b27a1dd0fbc687a21b171c4';
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
$withoutAnnouncer = preg_replace('/\.site-customer-announcer \{\n[^}]+\}\n\n/', '', $baselineCss, 1, $removed);
checkM5CScope($removed === 1 && $css === $withoutAnnouncer, 'Only obsolete announcer CSS removed; every other rule remains byte-equivalent');
$rules = preg_replace('~/\*.*?\*/~s', '', $block);
preg_match_all('/([^{}]+)\{([^{}]*)\}/', $rules, $matches, PREG_SET_ORDER);
checkM5CScope(count($matches) === 4, 'Only four existing customer rules remain');
foreach ($matches as $rule) {
    checkM5CScope(str_starts_with(trim($rule[1]), '.site-customer-'), 'Each rule is scoped to customer review surfaces');
    checkM5CScope(!preg_match('/overflow\s*:|overflow-x\s*:|hidden|clip|ellipsis|url\s*\(|@import|position\s*:/i', $rule[2]), 'No clipping, hiding, external assets or positioning workaround');
}
$routePath = 'public/app/247sp/website-manager.php';
checkM5CScope(m5cBaseline($routePath) === str_replace("\r\n", "\n", file_get_contents($root . '/' . $routePath)), 'Entire route unchanged: auth, CSRF, POST dispatch, 303, errors and legacy behavior');
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- ' . escapeshellarg($path), $unused, $status);
    checkM5CScope($status === 0, 'Protected security/lifecycle/schema/runtime tree unchanged: ' . $path);
}
checkM5CScope(glob($root . '/database/migrations/025*') === [], 'Migration 025 absent');
$correctionBaseline = $baseline;
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared', 'public/app/247sp', 'private/views/site-customer-review-error.php'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($correctionBaseline) . ' -- ' . escapeshellarg($path), $unused, $status);
    checkM5CScope($status === 0, 'Narrator correction preserves failed-validation backend/route/error baseline: ' . $path);
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
exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($correctionBaseline) . ' -- . ":(exclude)tests" ":(exclude)docs"', $appChanges, $status);
checkM5CScope($status === 0 && array_diff($appChanges, ['private/views/site-customer-review.php', 'public/app/assets/css/design-system.css', $assetPath]) === [], 'Only the review view, receipt asset and obsolete CSS may change outside tests/docs');
echo "Website platform M5C scope: $assertions assertions passed.\n";
