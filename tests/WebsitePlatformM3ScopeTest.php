<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    throw new RuntimeException('Repository root is unavailable.');
}
$baseline = '4235f5a622b106f3e7983f670e46882002168e92';
$assertions = 0;
function assertM3Scope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function m3GitQuiet(string $root, string $baseline, string $path): bool
{
    $output = [];
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- ' . escapeshellarg($path), $output, $status);
    return $status === 0;
}

$output = [];
exec('git -C ' . escapeshellarg($root) . ' rev-parse ' . escapeshellarg($baseline), $output, $status);
assertM3Scope($status === 0 && trim(implode("\n", $output)) === $baseline, 'The authoritative M3 baseline must exist.');
assertM3Scope(m3GitQuiet($root, $baseline, 'database/migrations/023_website_platform_foundation.sql'), 'Migration 023 must remain unchanged.');
assertM3Scope(is_file($root . '/database/migrations/024_component_registry_versioning.sql'), 'Migration 024 must be present.');
assertM3Scope(count(glob($root . '/database/migrations/024_*.sql') ?: []) === 1, 'Only one migration 024 may exist.');
assertM3Scope(count(glob($root . '/database/migrations/02[5-9]_*.sql') ?: []) === 0, 'Migration 025+ must remain absent.');

foreach ([
    'public/accounts',
    'private/classes/domains/DomainManager.php', 'private/classes/LeadHub.php',
    'private/classes/StripeBilling.php', 'private/classes/SiteGenerator.php',
    'private/classes/AdminPortal.php', 'infrastructure',
] as $protectedPath) {
    assertM3Scope(m3GitQuiet($root, $baseline, $protectedPath), "M3 must not change {$protectedPath}.");
}

$output = [];
exec(
    'git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($baseline) . ' -- public/app',
    $output,
    $status
);
$allowedLaterPublicAppChanges = [
    'public/app/admin/_common.php',
    'public/app/admin/site.php',
    'public/app/admin/sites.php',
    'public/app/admin/site-composer.php',
    'public/app/admin/site-preview.php',
];
assertM3Scope(
    $status === 0 && array_diff($output, $allowedLaterPublicAppChanges) === [],
    'M3 protected public/app paths must remain unchanged outside the authorized later M4A/M4B admin workspace.'
);

foreach ([
    'private/classes/CanonicalJson.php', 'private/classes/ComponentSchemaValidator.php',
    'private/classes/ComponentRegistry.php', 'private/classes/ThemeRegistry.php',
    'private/classes/SiteRevisionSnapshotHasher.php', 'private/classes/SiteCompositionValidator.php',
    'private/classes/SiteCompositionManager.php', 'private/classes/SiteCompositionRenderer.php',
    'private/classes/SiteComponentRenderers.php', 'scripts/verify-component-registry.php',
] as $expected) {
    assertM3Scope(is_file($root . '/' . $expected), "M3 service file {$expected} must exist.");
}

$sources = '';
foreach ([
    'private/classes/ComponentRegistry.php', 'private/classes/ThemeRegistry.php',
    'private/classes/SiteCompositionValidator.php', 'private/classes/SiteCompositionManager.php',
    'private/classes/SiteCompositionRenderer.php', 'private/classes/SiteComponentRenderers.php',
] as $source) {
    $sources .= file_get_contents($root . '/' . $source) ?: '';
}
assertM3Scope(!preg_match('/Stripe|Namecheap|Twilio|Retell|Vendasta|DataForSEO|Apache/i', $sources), 'M3 services must not invoke providers or Apache.');
assertM3Scope(!preg_match('/\b(?:curl_|fsockopen|stream_socket_client)\b/i', $sources), 'M3 services must not perform network calls.');
assertM3Scope(!preg_match('/\b(?:file_put_contents|mkdir|rename|unlink|copy)\s*\(/i', $sources), 'M3 services must not mutate the filesystem.');
assertM3Scope(!preg_match('/class\s+(?:SiteBuildService|SitePublisher|SiteDeploymentManager)\b/', $sources), 'M6 build/deployment implementation must not begin.');
assertM3Scope(!preg_match('/(?:LeadHub|DomainManager|StripeBilling)::/', $sources), 'M3 must not integrate LeadHub, domain, or Stripe runtime behavior.');

$generator = file_get_contents($root . '/private/classes/SiteGenerator.php') ?: '';
assertM3Scope(str_contains($generator, 'websiteForBusiness') && str_contains($generator, 'pagesForWebsite'), 'Legacy SiteGenerator reader must remain authoritative.');
assertM3Scope(!str_contains($generator, 'SiteCompositionRenderer'), 'Generic rendering must not cut over the public runtime.');

echo "Website platform M3 scope: {$assertions} assertions passed.\n";
