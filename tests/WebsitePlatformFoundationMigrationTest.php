<?php

declare(strict_types=1);

error_reporting(E_ALL);

$path = __DIR__ . '/../database/migrations/023_website_platform_foundation.sql';
$migration023Contents = file_get_contents($path);
if (!is_string($migration023Contents)) {
    throw new RuntimeException('Migration 023 must be readable.');
}
$canonicalMigration023 = str_replace(["\r\n", "\r"], "\n", $migration023Contents);
$sql = $canonicalMigration023;

$assertions = 0;
function assertWebsiteMigration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertWebsiteMigration(basename($path) === '023_website_platform_foundation.sql', 'Migration 023 must use the reserved filename.');
assertWebsiteMigration(
    hash('sha256', $canonicalMigration023) === 'f0912bafc947eab8cc5b2dd5d534466d6b3675f991cac2f6849b1f84819db302',
    'Historical migration 023 canonical repository content must remain unchanged.'
);
assertWebsiteMigration(count(glob(__DIR__ . '/../database/migrations/024_*.sql') ?: []) === 1, 'M3 must add exactly one authorized migration 024.');

$tables = [
    'sites', 'site_business_associations', 'site_pages', 'site_generation_briefs',
    'site_revisions', 'component_definitions', 'component_variants', 'site_revision_pages',
    'site_page_sections', 'site_themes', 'site_assets', 'site_revision_assets',
    'site_approvals', 'legacy_site_mappings', 'legacy_site_page_mappings', 'site_events',
];
$last = -1;
foreach ($tables as $table) {
    $needle = 'CREATE TABLE ' . $table . ' (';
    assertWebsiteMigration(substr_count($sql, $needle) === 1, "Migration must create {$table} exactly once.");
    $position = strpos($sql, $needle);
    assertWebsiteMigration($position !== false && $position > $last, "{$table} must be created in dependency order.");
    $last = (int) $position;
}

assertWebsiteMigration(substr_count($sql, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') === count($tables), 'Every M1 table must use InnoDB/utf8mb4.');
assertWebsiteMigration(substr_count($sql, 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY') === count($tables), 'Every M1 entity must use the repository BIGINT identity convention.');
assertWebsiteMigration(str_contains($sql, 'site_key CHAR(36) NOT NULL'), 'Generic site identity must have a durable opaque key.');
assertWebsiteMigration(str_contains($sql, 'purpose VARCHAR(32) NOT NULL'), 'Site purpose must be separate.');
assertWebsiteMigration(str_contains($sql, 'lifecycle_status VARCHAR(40) NOT NULL'), 'Site lifecycle must be separate.');
assertWebsiteMigration(!preg_match('/business_id BIGINT UNSIGNED NOT NULL[^;]+CREATE TABLE site_pages/s', $sql), 'The sites table must not require a business.');
assertWebsiteMigration(str_contains($sql, 'UNIQUE KEY uq_site_pages_site_key (site_id, page_key)'), 'Logical page identity must be durable per site.');
assertWebsiteMigration(str_contains($sql, 'UNIQUE KEY uq_site_revisions_number (site_id, revision_number)'), 'Revision numbers must be unique per site.');
assertWebsiteMigration(!str_contains($sql, 'UNIQUE KEY uq_site_revisions_snapshot'), 'Identical snapshot hashes must be permitted for different revisions of the same site.');
assertWebsiteMigration(str_contains($sql, 'INDEX idx_site_revisions_snapshot (site_id, snapshot_hash)'), 'Snapshot hashes must remain indexed for reconciliation diagnostics.');
assertWebsiteMigration(str_contains($sql, 'UNIQUE KEY uq_site_revision_pages_page (revision_id, site_page_id)'), 'A logical page may appear once per revision.');
assertWebsiteMigration(str_contains($sql, 'UNIQUE KEY uq_site_revision_pages_slug (revision_id, slug)'), 'Revision slugs must be unique.');
assertWebsiteMigration(str_contains($sql, 'UNIQUE KEY uq_site_page_sections_order (revision_page_id, sort_order)'), 'Section order must be deterministic.');
assertWebsiteMigration(str_contains($sql, 'source_references_json JSON NOT NULL'), 'Revisions must preserve source references separately from facts.');
assertWebsiteMigration(str_contains($sql, 'facts_snapshot_json JSON NOT NULL'), 'Revisions must provide the approved snapshot/reference foundation.');
assertWebsiteMigration(str_contains($sql, 'snapshot_hash CHAR(64) NOT NULL'), 'Revisions must store a reproducibility hash.');
foreach ([
    "purpose IN ('247sp', 'emd', 'internal_demo')",
    "'cancellation_pending', 'conversion_pending', 'archived'",
    "'customer_approved', 'internally_approved', 'published', 'superseded', 'restored'",
    "materiality IN ('material', 'non_material', 'undetermined')",
    "approval_type IN ('customer', 'internal', 'production', 'conversion')",
    "state IN ('requested', 'approved', 'rejected', 'revoked', 'superseded')",
    "import_status IN ('pending', 'imported', 'quarantined')",
] as $approvedValues) {
    assertWebsiteMigration(str_contains($sql, $approvedValues), "Migration must enforce approved bounded values: {$approvedValues}.");
}

foreach ([
    'fk_sites_current_published_revision',
    'fk_site_revisions_based_on_site', 'fk_site_revisions_restored_from_site',
    'fk_site_revisions_brief_site',
    'fk_site_revision_pages_revision_site', 'fk_site_revision_pages_page_site',
    'fk_site_page_sections_revision_page_site', 'fk_site_revision_assets_revision_site',
    'fk_site_revision_assets_asset_site', 'fk_site_revision_assets_page_site',
    'fk_site_revision_assets_section_site', 'fk_site_approvals_revision_site',
    'fk_site_approvals_supersedes_site', 'fk_legacy_site_mappings_revision_site',
    'fk_legacy_site_page_mappings_mapping_site',
    'fk_legacy_site_page_mappings_page_site', 'fk_legacy_site_page_mappings_revision_page_site',
] as $constraint) {
    assertWebsiteMigration(str_contains($sql, $constraint), "Migration must enforce tenant ownership with {$constraint}.");
}

assertWebsiteMigration(str_contains($sql, 'REFERENCES sites (id) ON DELETE RESTRICT'), 'Site history must use restrictive deletion.');
assertWebsiteMigration(str_contains($sql, 'REFERENCES businesses (id) ON DELETE RESTRICT'), 'Business associations must not cascade-delete site history.');
assertWebsiteMigration(str_contains($sql, 'fk_site_revisions_actor FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL'), 'Optional revision actors must be nullable history references.');
assertWebsiteMigration(str_contains($sql, 'fk_sites_current_published_revision'), 'The published revision pointer must reference a revision.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (current_published_revision_id, id) REFERENCES site_revisions (id, site_id)'), 'The published revision pointer must remain within its site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (based_on_revision_id, site_id) REFERENCES site_revisions (id, site_id)'), 'Revision ancestry must remain within its site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (restored_from_revision_id, site_id) REFERENCES site_revisions (id, site_id)'), 'Revision restoration must remain within its site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (generation_brief_id, site_id) REFERENCES site_generation_briefs (id, site_id)'), 'Revision briefs must remain within their site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (import_revision_id, site_id) REFERENCES site_revisions (id, site_id)'), 'Legacy import revisions must remain within their mapped site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (site_revision_page_id, revision_id, site_id)'), 'Revision asset page references must match both revision and site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (site_page_section_id, revision_id, site_id)'), 'Revision asset section references must match both revision and site.');
assertWebsiteMigration(str_contains($sql, 'FOREIGN KEY (supersedes_approval_id, site_id) REFERENCES site_approvals (id, site_id)'), 'Approval supersession must remain within its site.');
assertWebsiteMigration(!str_contains($sql, 'current_production_deployment_id'), 'M1 must not pre-create an unbacked deployment pointer.');

foreach ([
    'uq_sites_site_key', 'uq_site_business_active_customer', 'uq_site_generation_briefs_version',
    'uq_site_revisions_published', 'uq_component_definitions_key',
    'uq_site_generation_briefs_id_site', 'uq_site_revision_pages_id_revision_site',
    'uq_site_page_sections_id_revision_site', 'uq_site_approvals_id_site',
    'uq_legacy_site_mappings_id_site',
    'uq_component_variants_definition_key', 'uq_site_themes_revision',
    'uq_site_assets_asset_key', 'uq_site_approvals_current',
    'uq_legacy_site_mappings_website', 'uq_legacy_site_mappings_site',
    'uq_legacy_site_page_mappings_page',
] as $key) {
    assertWebsiteMigration(str_contains($sql, $key), "Migration must define {$key}.");
}

assertWebsiteMigration(substr_count($sql, "'legacy_247sp_page'") === 2, 'Only the repository legacy-page component definition should be seeded and referenced.');
foreach (['home', 'service', 'about', 'contact'] as $variant) {
    assertWebsiteMigration(preg_match("/'" . preg_quote($variant, '/') . "'(?: AS variant_key|, 'Legacy)/", $sql) === 1, "The {$variant} preview variant must be seeded once.");
}
assertWebsiteMigration(!preg_match('/(?:component|variant).*(?:php|javascript|include_path|template_path)/i', $sql), 'Component metadata must not contain executable code or filesystem selectors.');
assertWebsiteMigration(!preg_match('/<\?(?:php|=)|<script\b|javascript\s*:/i', $sql), 'Migration seed content must not contain executable payloads.');
assertWebsiteMigration(!preg_match('/(?:secret|api_key|webhook_secret|provider_payload)/i', $sql), 'Migration must not contain credentials or provider payload storage.');

foreach (['site_build_jobs', 'site_deployments', 'site_domain_associations', 'site_routing_assignments', 'site_conversion_events', 'domains'] as $forbidden) {
    assertWebsiteMigration(!str_contains($sql, 'CREATE TABLE ' . $forbidden), "M1 must not create future table {$forbidden}.");
}
assertWebsiteMigration(!preg_match('/\b(?:EXECUTE|PREPARE|DROP|TRUNCATE|DELETE FROM|UPDATE `?247sp_|ALTER TABLE `?247sp_)/i', $sql), 'Migration 023 must not mutate or delete legacy website data.');

$createCount = preg_match_all('/^CREATE TABLE /m', $sql);
$terminatorCount = preg_match_all('/\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;$/m', $sql);
assertWebsiteMigration($createCount === count($tables) && $terminatorCount === count($tables), 'Every CREATE TABLE must have a complete repository table terminator.');
assertWebsiteMigration(substr_count($sql, 'INSERT INTO component_definitions') === 1, 'Component definition seed must be singular.');
assertWebsiteMigration(substr_count($sql, 'INSERT INTO component_variants') === 1, 'Component variant seed must be singular.');

echo "Website platform migration: {$assertions} assertions passed.\n";
