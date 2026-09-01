<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    throw new RuntimeException('Repository root is unavailable.');
}

$assertions = 0;
function assertM2Scope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$baseline = '2feeb181594f9eb320c26dd117cffd0714780fe6';
$migration = file_get_contents($root . '/database/migrations/023_website_platform_foundation.sql');
if (!is_string($migration)) {
    throw new RuntimeException('Migration 023 must be readable.');
}

$output = [];
exec('git -C ' . escapeshellarg($root) . ' rev-parse ' . escapeshellarg($baseline), $output, $status);
assertM2Scope($status === 0 && trim(implode("\n", $output)) === $baseline, 'The authoritative M2 baseline must exist.');

$output = [];
exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- database/migrations/023_website_platform_foundation.sql', $output, $status);
assertM2Scope($status === 0, 'Migration 023 must remain unchanged.');
assertM2Scope(count(glob($root . '/database/migrations/024_*.sql') ?: []) === 0, 'Migration 024 must remain absent.');

foreach ([
    'private/classes/SiteAuthorizationPolicy.php',
    'private/classes/SiteManager.php',
    'private/classes/SiteRevisionManager.php',
    'private/classes/SiteApprovalManager.php',
    'private/classes/SiteServiceSupport.php',
] as $service) {
    assertM2Scope(is_file($root . '/' . $service), "{$service} must exist.");
}

foreach ([
    'public/accounts', 'public/app',
    'private/classes/domains/DomainManager.php',
    'private/classes/LeadHub.php',
    'private/classes/StripeBilling.php',
    'private/classes/SiteGenerator.php',
    'private/classes/AdminPortal.php',
] as $protectedPath) {
    $output = [];
    exec(
        'git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline)
        . ' -- ' . escapeshellarg($protectedPath),
        $output,
        $status
    );
    assertM2Scope($status === 0, "M2 must not change {$protectedPath}.");
}

$serviceSources = '';
foreach (glob($root . '/private/classes/Site*.php') ?: [] as $path) {
    $contents = file_get_contents($path);
    if (is_string($contents)) {
        $serviceSources .= $contents;
    }
}
assertM2Scope(!preg_match('/Stripe|Namecheap|Twilio|Retell|Vendasta|DataForSEO|Apache/i', $serviceSources), 'M2 services must not invoke providers or Apache.');
assertM2Scope(!preg_match('/\b(?:curl_|file_get_contents\s*\(\s*["\']https?:|fsockopen|stream_socket_client)\b/i', $serviceSources), 'M2 services must not perform HTTP/network calls.');
assertM2Scope(!preg_match('/\b(?:file_put_contents|mkdir|rename|unlink|copy)\s*\(/i', $serviceSources), 'M2 services must not perform filesystem mutations.');
assertM2Scope(!preg_match('/class\s+(?:ComponentRegistry|SiteBuildService|SitePublisher)\b/', $serviceSources), 'M3+ classes must not begin.');
assertM2Scope(!preg_match('/INSERT INTO component_(?:definitions|variants)|INSERT INTO site_page_sections/', file_get_contents($root . '/private/classes/SiteManager.php') ?: ''), 'SiteManager must not begin component authoring.');

assertM2Scope(str_contains($serviceSources, "'future_gate_required'"), 'M2 must retain explicit future gates.');
assertM2Scope(!preg_match('/lifecycle_status\s*=\s*["\']published["\']/', $serviceSources), 'M2 must not make published state reachable.');
assertM2Scope(!preg_match('/lifecycle_status\s*=\s*["\']active["\']/', $serviceSources), 'M2 must not make active state reachable.');

echo "Website platform M2 scope: {$assertions} assertions passed.\n";
