<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = dirname(__DIR__);
$manager = file_get_contents($root . '/private/classes/SiteCompositionManager.php');
$validator = file_get_contents($root . '/private/classes/SiteCompositionValidator.php');
$registry = file_get_contents($root . '/private/classes/ComponentRegistry.php');
$hasher = file_get_contents($root . '/private/classes/SiteRevisionSnapshotHasher.php');
$revisions = file_get_contents($root . '/private/classes/SiteRevisionManager.php');
if (!is_string($manager) || !is_string($validator) || !is_string($registry) || !is_string($hasher) || !is_string($revisions)) {
    throw new RuntimeException('M3 database sources must be readable.');
}

$assertions = 0;
function assertM3Database(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertM3Database(str_contains($manager, 'SiteServiceSupport::transaction'), 'Composition replacement must own one short transaction.');
assertM3Database(str_contains($manager, 'SiteRevisionManager::lockMutableRevisionForComposition'), 'Composition replacement must use the M2 locked mutability boundary.');
assertM3Database(strpos($manager, 'lockMutableRevisionForComposition') < strpos($manager, 'deleteRevisionComposition'), 'M2 mutability lock must precede composition deletion.');
assertM3Database(str_contains($manager, "array_key_exists('expected_snapshot_hash'"), 'Expected snapshot hash must be required.');
assertM3Database(str_contains($manager, "'stale_write'"), 'Stale snapshot writes must have a stable classification.');
assertM3Database(str_contains($manager, "hash_equals((string) \$revision['snapshot_hash'], \$expectedHash)"), 'Expected hash comparison must occur under the revision lock.');
assertM3Database(str_contains($manager, "['site_revision_assets', 'site_page_sections', 'site_revision_pages', 'site_themes']"), 'Only revision-owned composition tables may be replaced.');
assertM3Database(!preg_match('/(?:DELETE FROM|INSERT INTO)\s+site_assets\b/i', $manager), 'M3 must not mutate or delete site_assets.');
assertM3Database(!preg_match('/DELETE FROM\s+site_pages\b/i', $manager), 'Omitted stable pages must not be deleted.');
assertM3Database(str_contains($manager, 'retired_at') && str_contains($manager, 'A retired logical page cannot be reused.'), 'Retired logical pages must be rejected.');
assertM3Database(str_contains($manager, 'SiteRevisionSnapshotHasher::hashStoredRevision'), 'Revision hash must be calculated from actual stored rows.');
assertM3Database(str_contains($manager, 'SiteRevisionManager::applyCompositionSnapshotHash'), 'Revision hash must update through the narrow M2 owner.');
assertM3Database(strpos($manager, 'insertAssets(') < strpos($manager, 'hashStoredRevision('), 'All stored references must precede canonical revision hashing.');
assertM3Database(str_contains($manager, "'site_revision_composition_replaced'"), 'Successful replacement must write a safe site event.');
assertM3Database(strpos($manager, 'applyCompositionSnapshotHash') < strpos($manager, "'site_revision_composition_replaced'"), 'Hash update and event must share the writing transaction.');

assertM3Database(str_contains($revisions, 'SiteCompositionValidator::validateStoredRevision'), 'M2 markReadyForReview must invoke the M3 stored validator.');
assertM3Database(strpos($revisions, 'SiteCompositionValidator::validateStoredRevision') < strpos($revisions, "'ready_for_review', true"), 'M3 validation must precede review lifecycle transition.');
assertM3Database(str_contains($revisions, 'applyCompositionSnapshotHash'), 'SiteRevisionManager must own the narrow hash updater.');
assertM3Database(str_contains($revisions, 'caller owns the transaction') || str_contains($revisions, 'Caller owns the transaction'), 'Hash updater must document caller-owned transaction.');
assertM3Database(!str_contains(substr($revisions, strpos($revisions, 'applyCompositionSnapshotHash'), 2000), 'beginTransaction'), 'Hash updater must not open a nested transaction.');

foreach (['component_key', 'implementation_version', 'variant_key'] as $identityField) {
    assertM3Database(str_contains($registry, ':' . $identityField), "Registry query must bind {$identityField}.");
}
assertM3Database(str_contains($registry, 'cd.component_key = :component_key') && str_contains($registry, 'cd.implementation_version = :implementation_version'), 'Registry must resolve exact component key/version identity.');
assertM3Database(!preg_match('/(?:renderer|class|template|php|javascript)_?(?:name|path)?\s+AS/i', $registry), 'DB metadata query must not select executable mappings.');
assertM3Database(str_contains($validator, 'a.id = :asset_id AND a.site_id = :site_id'), 'Assets must resolve by ID and same-site ownership.');
assertM3Database(str_contains($validator, "!== 'ready'"), 'Asset ready lifecycle must be enforced.');
assertM3Database(str_contains($validator, "'unknown'") || str_contains($validator, 'RIGHTS_ALLOWED'), 'Asset rights allowlist must be enforced.');
assertM3Database(str_contains($validator, 'rights_expires_at'), 'Asset rights expiry must be enforced.');
assertM3Database(str_contains($validator, 'active_business_id'), '247SP customer asset business context must be enforced.');
assertM3Database(str_contains($registry, "'application/pdf'"), 'Pricing documents must require PDF MIME.');

foreach (['page_key', 'component_key', 'implementation_version', 'variant_key', 'configuration_schema_version', 'content_hash'] as $field) {
    assertM3Database(str_contains($hasher, "'{$field}'"), "Revision canonical representation must include {$field}.");
}
foreach (['asset_type', 'storage_key', 'checksum_sha256', 'mime_type', 'byte_size', 'usage_key', 'source_reference', 'section_key'] as $field) {
    assertM3Database(str_contains($hasher, "'{$field}'"), "Revision canonical assets must include {$field}.");
}
assertM3Database(!preg_match("/'id'\s*=>/", $hasher), 'Canonical representation must exclude database row IDs.');
assertM3Database(str_contains($hasher, "'generation_brief' => \$generationBrief"), 'Generic revisions must support nullable generation briefs.');
assertM3Database(str_contains($hasher, 'ORDER BY rp.sort_order, rp.id'), 'Page ordering must remain deterministic and M1 compatible.');
assertM3Database(str_contains($hasher, 'ORDER BY s.sort_order, s.id'), 'Section ordering must remain deterministic and M1 compatible.');
assertM3Database(str_contains($hasher, 'ORDER BY ra.usage_key, a.storage_key, p.page_key'), 'Asset ordering must remain deterministic and M1 compatible.');

$forbiddenTransactionWork = ['curl_', 'file_get_contents(', 'mail(', 'DomainManager', 'LeadHub', 'Stripe', 'deploy', 'publisher'];
foreach ($forbiddenTransactionWork as $forbidden) {
    assertM3Database(!str_contains($manager, $forbidden), "Composition transaction must not perform {$forbidden} work.");
}
assertM3Database(substr_count($registry, 'cd.implementation_version = :implementation_version') === 1, 'Registry native prepare must bind each named placeholder once per statement.');

echo "Website platform M3 database contract: {$assertions} assertions passed.\n";
