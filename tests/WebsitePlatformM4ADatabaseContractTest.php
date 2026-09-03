<?php

declare(strict_types=1);

error_reporting(E_ALL);

$assertions = 0;
function assertM4ADatabase(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$migration = file_get_contents(__DIR__ . '/../database/migrations/023_website_platform_foundation.sql');
$briefs = file_get_contents(__DIR__ . '/../private/classes/SiteGenerationBriefManager.php');
$revisions = file_get_contents(__DIR__ . '/../private/classes/SiteRevisionManager.php');
$workspace = file_get_contents(__DIR__ . '/../private/classes/SiteAdminWorkspace.php');
foreach ([$migration, $briefs, $revisions, $workspace] as $source) {
    assertM4ADatabase(is_string($source), 'M4A contract source must be readable.');
}

assertM4ADatabase(str_contains($migration, 'UNIQUE KEY uq_site_generation_briefs_version (site_id, brief_version)'), 'Brief versions must be unique per site.');
assertM4ADatabase(str_contains($migration, 'UNIQUE KEY uq_site_generation_briefs_hash (site_id, content_hash)'), 'Brief content hashes must be unique per site.');
assertM4ADatabase(str_contains($migration, 'UNIQUE KEY uq_site_revisions_number (site_id, revision_number)'), 'Revision numbers must be unique per site.');
assertM4ADatabase(str_contains($migration, 'fk_site_revisions_based_on_site'), 'The database must enforce same-site ancestry.');
assertM4ADatabase(str_contains($migration, 'fk_site_revisions_brief_site'), 'The database must enforce same-site brief ownership.');

assertM4ADatabase(strpos($briefs, 'SiteManager::lockSite($connection, $siteId)') < strpos($briefs, 'COALESCE(MAX(brief_version), 0) + 1'), 'The site lock must precede brief version allocation.');
assertM4ADatabase(str_contains($briefs, 'CanonicalJson::encode($brief)') && str_contains($briefs, 'CanonicalJson::hash($brief)'), 'Brief storage and hash material must be canonical.');
assertM4ADatabase(str_contains($briefs, "'source_type' => self::SOURCE_TYPE"), 'Authored brief source type must be service-owned.');
assertM4ADatabase(!preg_match('/UPDATE\s+site_generation_briefs/i', $briefs), 'Authored generation brief versions must be fully append-only.');
assertM4ADatabase(str_contains($briefs, "WHERE site_id = :site_id AND source_type = :source_type") && str_contains($briefs, 'ORDER BY brief_version DESC LIMIT 1'), 'Latest authored brief must be derived by version without rewriting history.');

$authoredStart = strpos($revisions, 'public static function createAuthoredDraftRevision');
$legacyStart = strpos($revisions, 'public static function createRevision', $authoredStart + 1);
$authored = substr($revisions, $authoredStart, $legacyStart - $authoredStart);
assertM4ADatabase(str_contains($authored, 'SiteRevisionSnapshotBuilder::buildForSite'), 'Authored revision facts must be built server-side.');
assertM4ADatabase(str_contains($authored, 'SiteServiceSupport::correlationId(null)'), 'Authored revision correlation IDs must be server-generated.');
assertM4ADatabase(strpos($authored, 'SiteManager::lockSite($connection, $siteId)') < strpos($authored, 'nextRevisionNumber($connection, $siteId)'), 'The site lock must precede authored revision number allocation.');
assertM4ADatabase(str_contains($authored, "lifecycle_status IN (:draft_status, :validation_failed_status)"), 'The one-mutable-admin-draft rule must cover draft and validation_failed.');
assertM4ADatabase(str_contains($authored, 'existing mutable revision (ID '), 'Mutable draft conflicts must identify the existing revision.');
assertM4ADatabase(str_contains($authored, "'status' => 'draft'"), 'M4A must create only normal drafts.');
assertM4ADatabase(str_contains($authored, 'restored_from_revision_id') && str_contains($authored, 'NULL, :generation_brief_id'), 'Authored drafts must store restored_from_revision_id as NULL.');
assertM4ADatabase(str_contains($authored, 'The based-on revision is not part of this site.'), 'Cross-site ancestry must fail closed.');
assertM4ADatabase(str_contains($authored, 'The based-on revision must be immutable.'), 'Mutable revisions must not be ancestry sources.');
assertM4ADatabase(str_contains($authored, 'CanonicalJson::encode($snapshot['), 'Canonical facts and references must be stored.');
assertM4ADatabase(!str_contains($authored, 'copyComposition('), 'M4A authored revision creation must not copy composition.');
assertM4ADatabase(strpos($authored, 'SiteManager::lockSite($connection, $siteId)') < strpos($authored, 'self::assertSnapshotBusinessAssociation($connection, $site, $snapshot)'), 'The site lock must precede the final business eligibility check.');
assertM4ADatabase(strpos($authored, 'self::assertSnapshotBusinessAssociation($connection, $site, $snapshot)') < strpos($authored, 'INSERT INTO site_revisions'), 'The final business eligibility check must precede revision insertion.');
assertM4ADatabase(str_contains($revisions, 'INNER JOIN businesses b ON b.id = sba.business_id') && str_contains($revisions, '(string) $eligibility[\'business_status\'] !== \'active\'') && str_contains($revisions, '(int) $eligibility[\'is_suspended\'] !== 0'), 'The locking association check must revalidate current business status and suspension.');
assertM4ADatabase(str_contains($revisions, 'INNER JOIN modules m ON m.id = bm.module_id') && str_contains($revisions, 'bm.status = :module_status') && str_contains($revisions, 'm.module_key = :module_key') && str_contains($revisions, 'm.is_active = 1'), 'The locking eligibility check must revalidate the active 247SP module assignment.');
assertM4ADatabase(substr_count($revisions, 'LIMIT 1 FOR UPDATE') >= 2, 'Current association, business, and module eligibility must use locking reads.');

assertM4ADatabase(!preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', preg_replace('/\bSELECT\b/i', '', $workspace)), 'SiteAdminWorkspace must be read-only.');
assertM4ADatabase(substr_count($workspace, 'SiteAuthorizationPolicy::requireInternalAdmin') >= 3, 'Every workspace entry point must require internal administration.');
assertM4ADatabase(str_contains($workspace, 'eligible247spBusinesses'), 'Workspace must expose eligible businesses.');
assertM4ADatabase(str_contains($workspace, 'site_generation_briefs') && str_contains($workspace, 'site_revisions') && str_contains($workspace, 'site_approvals'), 'Workspace detail must include brief, revision, and approval history.');
assertM4ADatabase(!str_contains($workspace, 'metadata_json') && !str_contains($workspace, 'comments') && !str_contains($workspace, 'reason'), 'Workspace reads must omit unsafe approval and audit metadata.');

echo "Website platform M4A database contract: {$assertions} assertions passed.\n";
