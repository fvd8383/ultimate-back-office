<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/023_website_platform_foundation.sql');
$policy = file_get_contents($root . '/private/classes/SiteAuthorizationPolicy.php');
$sites = file_get_contents($root . '/private/classes/SiteManager.php');
$revisions = file_get_contents($root . '/private/classes/SiteRevisionManager.php');
$approvals = file_get_contents($root . '/private/classes/SiteApprovalManager.php');
$support = file_get_contents($root . '/private/classes/SiteServiceSupport.php');
if (!is_string($migration) || !is_string($policy) || !is_string($sites)
    || !is_string($revisions) || !is_string($approvals) || !is_string($support)) {
    throw new RuntimeException('M2 database contract sources must be readable.');
}

$assertions = 0;
function assertM2Database(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'lock_version INT UNSIGNED NOT NULL DEFAULT 0',
    'fk_sites_current_published_revision',
    'fk_site_revisions_based_on_site',
    'fk_site_revisions_restored_from_site',
    'fk_site_revisions_brief_site',
    'fk_site_revision_pages_revision_site',
    'fk_site_revision_pages_page_site',
    'fk_site_page_sections_revision_page_site',
    'fk_site_revision_assets_revision_site',
    'fk_site_revision_assets_asset_site',
    'fk_site_revision_assets_page_site',
    'fk_site_revision_assets_section_site',
    'fk_site_approvals_revision_site',
    'fk_site_approvals_supersedes_site',
    'uq_site_revisions_number (site_id, revision_number)',
    'uq_site_revisions_published (published_site_id)',
    'uq_site_approvals_current (current_approved_revision_id, approval_type)',
] as $contract) {
    assertM2Database(str_contains($migration, $contract), "Migration 023 must preserve {$contract}.");
}

foreach ([
    "purpose IN ('247sp', 'emd', 'internal_demo')",
    "'active', 'suspended', 'cancellation_pending', 'conversion_pending', 'archived'",
    "'customer_approved', 'internally_approved', 'published', 'superseded', 'restored'",
    "materiality IN ('material', 'non_material', 'undetermined')",
    "approval_type IN ('customer', 'internal', 'production', 'conversion')",
    "state IN ('requested', 'approved', 'rejected', 'revoked', 'superseded')",
] as $check) {
    assertM2Database(str_contains($migration, $check), "Migration CHECK contract must preserve {$check}.");
}

assertM2Database(str_contains($migration, 'CASE WHEN state = \'approved\' AND revoked_at IS NULL THEN revision_id ELSE NULL END'), 'Current approval uniqueness must be revision-specific.');
assertM2Database(!str_contains($migration, 'current_approved_site_id'), 'Approval uniqueness must not become global per site/type.');
assertM2Database(str_contains($revisions, 'sr.revision_number < :revision_number'), 'Material successor logic must explicitly target older revisions.');
assertM2Database(str_contains($revisions, "\$materiality === 'material'"), 'Only material successors may supersede prior customer approval.');

assertM2Database(str_contains($support, '$connection->beginTransaction()'), 'Every mutation wrapper must begin one transaction.');
assertM2Database(str_contains($support, '$connection->commit()'), 'Successful mutations must commit.');
assertM2Database(str_contains($support, '$connection->rollBack()'), 'Failed mutations must roll back.');
assertM2Database(strpos($support, '$result = $callback($connection)') < strpos($support, '$connection->commit()'), 'Mutation and success event callback must precede commit.');
assertM2Database(str_contains($support, 'INSERT INTO site_events'), 'Generic site audit must use site_events.');
assertM2Database(!str_contains($support, 'facts_snapshot_json') && !str_contains($support, 'source_references_json'), 'Audit support must not store snapshot payloads.');

assertM2Database(substr_count($sites, 'FOR UPDATE') >= 3, 'Site creation/lifecycle must use row locks.');
assertM2Database(
    strpos($sites, 'self::lockEligible247spBusiness($connection, (int) $businessId)') < strpos($sites, 'INSERT INTO sites')
        && str_contains($sites, 'FROM businesses b WHERE b.id = :business_id FOR UPDATE'),
    'Business lock must precede 247SP site insertion.'
);
assertM2Database(strpos($revisions, 'SiteManager::lockSite($connection, $siteId)') < strpos($revisions, 'nextRevisionNumber($connection, $siteId)'), 'Site lock must serialize revision numbering.');
assertM2Database(str_contains($revisions, 'FOR UPDATE') && str_contains($revisions, "materiality = :undetermined"), 'Materiality writes must lock and compare write-once state.');
assertM2Database(str_contains($approvals, 'LIMIT 1 FOR UPDATE'), 'Approval requests must lock duplicate open requests.');
assertM2Database(str_contains($approvals, 'WHERE id = :approval_id FOR UPDATE'), 'Approval decisions must lock the request row.');
assertM2Database(str_contains($approvals, "AND state = :requested_state"), 'Approval decisions must compare requested state.');
assertM2Database(str_contains($sites, 'WHERE id = :site_id AND lock_version = :lock_version'), 'Site lifecycle update must compare lock_version atomically.');

assertM2Database(str_contains($policy, "roles r ON r.id = ur.role_id AND r.scope = :internal_scope"), 'Internal roles must be scope-limited.');
assertM2Database(str_contains($policy, "'Super Admin'") && str_contains($policy, "'Admin'"), 'Admin and Super Admin must be recognized explicitly.');
foreach (['Support', 'Marketing Staff', 'Sales Staff', 'Bookkeeping Staff', 'Account Manager', 'Domain/Email Admin'] as $forbiddenRole) {
    assertM2Database(!str_contains($policy, "'{$forbiddenRole}'"), "{$forbiddenRole} must not receive administrative authority.");
}
foreach ([
    'sba.association_role = :association_role', 'sba.status = :association_status',
    'b.status = :business_status', 'b.is_suspended = 0',
    'bu.user_id = :user_id', 'bu.status = :membership_status',
    'bm.status = :module_status', 'm.module_key = :module_key', 'm.is_active = 1',
    's.purpose = :purpose',
] as $tenantGate) {
    assertM2Database(str_contains($policy, $tenantGate), "Customer access must enforce {$tenantGate}.");
}
assertM2Database(str_contains($policy, "['Owner', 'Admin']"), 'Customer approval must be Owner/Admin-only.');
assertM2Database(str_contains($policy, "(int) \$context['is_owner'] !== 1"), 'Customer owners must receive approval authority.');
assertM2Database(str_contains($policy, 'Internal administrators cannot act as customer approvers.'), 'Admin customer impersonation must be rejected.');

echo "Website platform M2 database contract: {$assertions} assertions passed.\n";
