<?php

declare(strict_types=1);
$root = dirname(__DIR__);
$baseline = '3280140789a796d6dbf3d77faa9a68e7d1db09e1';
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
checkM5CScope(substr_replace($css, '', $start, $end - $start) === m5cBaseline('public/app/assets/css/design-system.css'), 'Every existing global design-system rule remains byte-equivalent');
$rules = preg_replace('~/\*.*?\*/~s', '', $block);
preg_match_all('/([^{}]+)\{([^{}]*)\}/', $rules, $matches, PREG_SET_ORDER);
checkM5CScope(count($matches) === 5, 'Only four existing customer rules and one announcer rule');
foreach ($matches as $rule) {
    checkM5CScope(str_starts_with(trim($rule[1]), '.site-customer-'), 'Each rule is scoped to customer review surfaces');
    if (trim($rule[1]) === '.site-customer-announcer') {
        checkM5CScope(preg_replace('/\s+/', '', $rule[2]) === 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0;', 'Only the separate announcer uses the exact accessible visually-hidden rule');
        continue;
    }
    checkM5CScope(!preg_match('/overflow\s*:|overflow-x\s*:|hidden|clip|ellipsis|url\s*\(|@import|position\s*:/i', $rule[2]), 'No clipping, hiding, external assets or positioning workaround');
}
$routePath = 'public/app/247sp/website-manager.php';
$expected = str_replace(
    ['echo \'<p>\' . htmlspecialchars($reviewFailure, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\') . \'</p><a href="website-manager.php">Reload Website Manager</a>\';',
        '<section class="hero-panel product-hero product-hero--247sp">'],
    ["require __DIR__ . '/../../../private/views/site-customer-review-error.php';",
        '<section class="hero-panel product-hero product-hero--247sp site-customer-context">'],
    m5cBaseline($routePath)
);
checkM5CScope($expected === str_replace("\r\n", "\n", file_get_contents($root . '/' . $routePath)), 'Route differs only in failure markup and scoped hero class; auth, CSRF, 303 and legacy dispatch unchanged');
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- ' . escapeshellarg($path), $unused, $status);
    checkM5CScope($status === 0, 'Protected security/lifecycle/schema/runtime tree unchanged: ' . $path);
}
checkM5CScope(glob($root . '/database/migrations/025*') === [], 'Migration 025 absent');
$correctionBaseline = '026fbeb07e5700dd87436b30de00ee605abd82be';
foreach (['private/classes', 'database', 'infrastructure', 'public/accounts', 'public/webhooks', 'shared', 'public/app/247sp', 'private/views/site-customer-review-error.php'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($correctionBaseline) . ' -- ' . escapeshellarg($path), $unused, $status);
    checkM5CScope($status === 0, 'Narrator correction preserves failed-validation backend/route/error baseline: ' . $path);
}
$assetPath = 'public/app/assets/js/customer-review-status.js';
checkM5CScope(glob($root . '/public/app/assets/js/*') === [$root . '/' . $assetPath], 'Exactly one dedicated application JS asset');
$js = file_get_contents($root . '/' . $assetPath);
checkM5CScope(!preg_match('/innerHTML|outerHTML|\beval\b|new\s+Function|\.focus\s*\(|fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|https?:|document\.write|\.src\s*=|\.href\s*=/i', $js), 'Announcer performs no HTML execution, focus movement or network operation');
checkM5CScope(str_contains($js, 'announcer.textContent = receipt.textContent'), 'Announcement reads rendered plain text only');
checkM5CScope(str_contains($js, "announcer.textContent === ''"), 'One-time population guard');
$appChanges = [];
exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($correctionBaseline) . ' -- public/app', $appChanges, $status);
checkM5CScope($status === 0 && array_diff($appChanges, ['public/app/assets/css/design-system.css', $assetPath]) === [], 'Correction permits only scoped CSS and the exact announcer asset in public/app');
echo "Website platform M5C scope: $assertions assertions passed.\n";
