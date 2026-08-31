<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    throw new RuntimeException('Repository root is unavailable.');
}

$assertions = 0;
function assertWebsiteScope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$importer = file_get_contents($root . '/private/classes/LegacyWebsitePlatformImporter.php');
$migration = file_get_contents($root . '/database/migrations/023_website_platform_foundation.sql');
$cli = file_get_contents($root . '/scripts/import-legacy-websites.php');
if (!is_string($importer) || !is_string($migration) || !is_string($cli)) {
    throw new RuntimeException('M1 implementation files must be readable.');
}

assertWebsiteScope(!preg_match('/\b(?:UPDATE|DELETE FROM)\s+`?247sp_(?:generated_websites|generated_pages|business_content|service_pages|website_)/i', $importer), 'Importer must never mutate legacy website data.');
assertWebsiteScope(str_contains($importer, 'FOR UPDATE'), 'Importer must lock each legacy import unit and mapping.');
assertWebsiteScope(str_contains($importer, 'MAX_BATCH_SIZE = 100'), 'Importer must enforce a bounded maximum batch.');
assertWebsiteScope(str_contains($importer, 'beginTransaction()') && str_contains($importer, 'rollBack()'), 'Importer must own per-unit transactions and rollback.');
assertWebsiteScope(
    strpos($importer, 'collectAssetEvidence($preflightSource)') < strpos($importer, '$connection->beginTransaction()'),
    'Asset evidence collection must precede the write transaction.'
);
preg_match('/private static function importAssets\(.*?\R    }\R\R    private static function collectAssetEvidence/s', $importer, $importAssetMethod);
assertWebsiteScope(
    isset($importAssetMethod[0]) && !preg_match('/\b(?:realpath|hash_file|filesize|finfo_file|file_get_contents)\s*\(/', $importAssetMethod[0]),
    'The transactional asset writer must consume preflight evidence without filesystem inspection.'
);
preg_match('/private static function inspectAsset\(string \$publicPath\): array\s*\{.*?\R    \}/s', $importer, $productionInspectionMethod);
preg_match('/private static function inspectAssetWithinRoot\(string \$publicPath, string \$publicRoot\): array\s*\{.*?\R    \}/s', $importer, $rootInspectionMethod);
assertWebsiteScope(
    isset($productionInspectionMethod[0])
        && str_contains($productionInspectionMethod[0], "dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'app'")
        && str_contains($productionInspectionMethod[0], 'self::inspectAssetWithinRoot($publicPath, $root)'),
    'Production asset inspection must derive repository public/app internally.'
);
assertWebsiteScope(
    isset($rootInspectionMethod[0])
        && str_contains($rootInspectionMethod[0], 'realpath($publicRoot)')
        && str_contains($rootInspectionMethod[0], '!str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)')
        && !str_contains($rootInspectionMethod[0], 'Database::'),
    'The private inspection seam must preserve realpath containment within its supplied root.'
);
assertWebsiteScope(substr_count($importer, 'inspectAssetWithinRoot(') === 2, 'Only production inspectAsset may call the private explicit-root inspection seam.');
assertWebsiteScope(
    !preg_match('/\bgetenv\s*\(|\$_(?:ENV|SERVER)\b/', $importer)
        && !preg_match('/(?:public|asset|filesystem)[_-]?root/i', $cli),
    'Environment and CLI configuration must not select the importer filesystem root.'
);
assertWebsiteScope(!preg_match('/\$_(?:GET|POST|REQUEST|FILES|COOKIE)\b/', $importer), 'Browser/request input must not select the importer filesystem root.');
assertWebsiteScope(str_contains($importer, 'source_changed_during_import'), 'Locked DB source drift must produce an explicit retryable result.');
assertWebsiteScope(str_contains($importer, 'source_changed'), 'A changed imported source must be quarantined instead of overwritten.');
assertWebsiteScope(str_contains($importer, "'quarantine_evidence' =>") && str_contains($importer, "'persistence_failed'"), 'Quarantine durability must be explicit in returned results.');
assertWebsiteScope(!str_contains($importer, "'eligible_legacy_count'"), 'A structural candidate count must not be labeled as exact eligibility.');
assertWebsiteScope(str_contains($importer, "'candidate_legacy_count'") && str_contains($importer, "'unmapped_candidate_count'"), 'Reconciliation reporting must use accurate candidate terminology.');
assertWebsiteScope(str_contains($importer, "'lifecycle_status' => 'draft'"), 'Imported generic sites must remain draft.');
assertWebsiteScope(!preg_match('/lifecycle_status[^\n]+(?:active|published)/i', $importer), 'Importer must not activate or publish a generic site.');
assertWebsiteScope(!str_contains($importer, 'site_approvals'), 'Legacy launch activity must not become a generic approval.');
assertWebsiteScope(!preg_match('/DomainManager|DomainAutomation|website_domains/', $importer), 'Domain state must not become M1 publication authority.');
assertWebsiteScope(!preg_match('/Stripe|Namecheap|DataForSEO|Twilio|Retell|Vendasta/i', $importer . $cli), 'M1 importer must not call external providers.');
assertWebsiteScope(!preg_match('/\b(?:curl_|file_get_contents\s*\(\s*["\']https?:|stream_socket_client|fsockopen)\b/i', $importer . $cli), 'M1 importer must contain no network client.');
assertWebsiteScope(!preg_match('/\b(?:include|require)(?:_once)?\s*\([^;]*(?:configuration_json|metadata_json|storage_key)/i', $importer), 'Database content must never select an include path.');
assertWebsiteScope(str_contains($importer, 'containsExecutableMarker'), 'Legacy content must be checked for executable markers before import.');
assertWebsiteScope(str_contains($migration, "'snapshot_only', TRUE"), 'Seeded component metadata must explicitly remain snapshot-only.');

$legacyReaders = [
    'private/classes/SiteGenerator.php' => '`247sp_generated_websites`',
    'public/app/247sp/site-preview.php' => 'SiteGenerator::pagesForWebsite',
    'private/classes/AdminPortal.php' => '`247sp_generated_websites`',
];
foreach ($legacyReaders as $path => $needle) {
    $contents = file_get_contents($root . '/' . $path);
    assertWebsiteScope(is_string($contents) && str_contains($contents, $needle), "{$path} must remain on the legacy runtime.");
    assertWebsiteScope(is_string($contents) && !str_contains($contents, 'site_revisions'), "{$path} must not switch to generic revisions in M1.");
}

foreach ([
    'private/classes/PricingCohortManager.php',
    'private/classes/BillingFoundation.php',
    'private/classes/StripeBilling.php',
    'private/classes/domains/DomainManager.php',
    'private/classes/LeadHub.php',
    'public/marketing/index.php',
    'private/classes/SignupContext.php',
] as $protectedPath) {
    $command = 'git diff --quiet origin/main -- ' . escapeshellarg($protectedPath);
    exec($command, $output, $status);
    assertWebsiteScope($status === 0, "M1 must not change {$protectedPath}.");
}

assertWebsiteScope(!preg_match('/CREATE TABLE site_(?:build|deployment|domain|routing|conversion)/', $migration), 'No M2+ operational schema may be introduced.');
assertWebsiteScope(!preg_match('/publish|deploy|apache|release|restore/i', $cli), 'The CLI must remain import/reconciliation-only.');

echo "Website platform M1 scope: {$assertions} assertions passed.\n";
