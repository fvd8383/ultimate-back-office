<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteGenerationBriefManager.php';
require_once __DIR__ . '/../private/classes/SiteRevisionSnapshotBuilder.php';

$assertions = 0;
function assertM4AService(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expectM4AServiceError(callable $callback, string $classification): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM4AService($exception->classification() === $classification, "Expected {$classification}; got {$exception->classification()}.");
        return;
    }
    throw new RuntimeException("Expected {$classification}.");
}

$input = [
    'summary' => 'Create a trustworthy local-service presentation.',
    'target_audience' => 'Homeowners planning a repair.',
    'tone_notes' => 'Clear, calm, and direct.',
    'design_notes' => 'Use generous spacing and a restrained blue palette.',
    'conversion_notes' => 'Prioritize calls and estimate requests.',
    'page_notes' => "Home: establish trust.\nServices: explain available work.",
];
$brief = SiteGenerationBriefManager::validateBrief($input);
assertM4AService(array_keys($brief) === array_keys(SiteGenerationBriefManager::FIELD_LIMITS), 'Authored briefs must use the exact bounded M4A schema.');
assertM4AService($brief === $input, 'Valid plain creative direction must normalize without semantic changes.');
assertM4AService(SiteGenerationBriefManager::SOURCE_TYPE === 'admin_manual', 'Authored briefs must use admin_manual.');
assertM4AService(SiteGenerationBriefManager::AUTHORED_STATE === 'authored', 'New manual brief versions must use the immutable authored state.');
assertM4AService(CanonicalJson::hash($brief) === CanonicalJson::hash(array_reverse($brief, true)), 'Brief content hashes must be canonical.');
assertM4AService(strlen(CanonicalJson::hash($brief)) === 64, 'Brief content hashes must be SHA-256.');

expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief($input + ['business_name' => 'Duplicated fact']), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => ''])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => '<strong>markup</strong>'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => 'javascript:alert(1)'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => '<?php echo 1; ?>'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => '{{ template.value }}'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => '../private/config/env.php'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => 'api_key=do-not-store-this'])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => "unsafe\x01control"])), 'invalid_request');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => str_repeat('a', 2001)])), 'invalid_request');
$unicodeBoundary = SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => str_repeat('é', 2000)]));
assertM4AService($unicodeBoundary['summary'] === str_repeat('é', 2000), 'Brief limits must count UTF-8 characters rather than bytes.');
expectM4AServiceError(static fn () => SiteGenerationBriefManager::validateBrief(array_replace($input, ['summary' => str_repeat('é', 2001)])), 'invalid_request');

$snapshot = [
    'snapshot_schema_version' => 1,
    'site_id' => 10,
    'site_key' => '11111111-1111-4111-8111-111111111111',
    'purpose' => '247sp',
    'business_id' => 20,
    'facts_snapshot' => [
        'services' => ['selected' => [['name' => 'Leak Repair', 'category_name' => 'Plumbing']]],
        'business' => ['display_name' => 'Example Plumbing'],
    ],
    'source_references' => [
        'business' => ['business_id' => 20, 'table' => 'businesses'],
        'selected_services' => ['sub_service_ids' => [3], 'table' => 'business_sub_services'],
    ],
];
$briefRow = ['brief_version' => 2, 'content_hash' => CanonicalJson::hash($brief), 'source_type' => 'admin_manual'];
$prior = ['revision_number' => 1, 'snapshot_hash' => str_repeat('a', 64)];
$hash = SiteRevisionSnapshotBuilder::seedHash($snapshot, $briefRow, $prior);
$reordered = $snapshot;
$reordered['facts_snapshot'] = array_reverse($reordered['facts_snapshot'], true);
$reordered['source_references'] = array_reverse($reordered['source_references'], true);
assertM4AService($hash === SiteRevisionSnapshotBuilder::seedHash($reordered, array_reverse($briefRow, true), array_reverse($prior, true)), 'Seed hashes must be deterministic under associative key reordering.');
assertM4AService($hash === SiteRevisionSnapshotBuilder::seedHash($snapshot, $briefRow, $prior), 'Identical authoritative input must produce an identical seed hash.');
assertM4AService($hash !== SiteRevisionSnapshotBuilder::seedHash($snapshot, $briefRow, null), 'Based-on evidence must participate in the seed hash.');
assertM4AService($hash !== SiteRevisionSnapshotBuilder::seedHash($snapshot, array_replace($briefRow, ['brief_version' => 3]), $prior), 'Brief version must participate in the seed hash.');
assertM4AService(preg_match('/^[a-f0-9]{64}$/', $hash) === 1, 'Seed hashes must be lowercase SHA-256.');
assertM4AService(SiteRevisionSnapshotBuilder::SNAPSHOT_SCHEMA_VERSION === 1, 'M4A snapshot schema version must be one.');

$builderSource = file_get_contents(__DIR__ . '/../private/classes/SiteRevisionSnapshotBuilder.php');
assertM4AService(is_string($builderSource), 'Snapshot builder source must be readable.');
assertM4AService(str_contains($builderSource, 'SharedBusinessProfile::getProfileForBusiness'), '247SP snapshots must consume Shared Business Profile.');
assertM4AService(str_contains($builderSource, 'business_sub_services') && str_contains($builderSource, 'business_custom_services'), 'Selected and custom service authority must be explicit.');
assertM4AService(str_contains($builderSource, '247sp_website_configurations'), 'The current approved service-area source must be explicit.');
assertM4AService(!str_contains($builderSource, '247sp_generated_pages') && !str_contains($builderSource, 'content_json'), 'Legacy generated JSON must not be authoritative snapshot input.');
assertM4AService(!preg_match('/stripe|password|otp|provider_credentials|internal_notes/i', $builderSource), 'Snapshot output must not include provider, billing, auth, or internal-note secrets.');
assertM4AService(str_contains($builderSource, "'business' => null") && str_contains($builderSource, "'customer_business_fabricated' => false"), 'EMD/internal-demo snapshots must not fabricate a customer business.');

echo "Website platform M4A services: {$assertions} assertions passed.\n";
