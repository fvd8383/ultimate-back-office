<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = dirname(__DIR__);
$migration023 = $root . '/database/migrations/023_website_platform_foundation.sql';
$migration024 = $root . '/database/migrations/024_component_registry_versioning.sql';
$migration023Contents = file_get_contents($migration023);
if (!is_string($migration023Contents)) {
    throw new RuntimeException('Migration 023 must be readable.');
}
$canonicalMigration023 = str_replace(["\r\n", "\r"], "\n", $migration023Contents);
$sql = file_get_contents($migration024);
if (!is_string($sql)) {
    throw new RuntimeException('Migration 024 must be readable.');
}

$assertions = 0;
function assertM3Migration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertM3Migration(hash('sha256', $canonicalMigration023) === 'f0912bafc947eab8cc5b2dd5d534466d6b3675f991cac2f6849b1f84819db302', 'Historical migration 023 canonical repository content must remain unchanged.');
assertM3Migration(basename($migration024) === '024_component_registry_versioning.sql', 'Migration 024 must use the authorized filename.');
assertM3Migration(count(glob($root . '/database/migrations/024_*.sql') ?: []) === 1, 'Exactly one migration 024 may exist.');
assertM3Migration(count(glob($root . '/database/migrations/02[5-9]_*.sql') ?: []) === 0, 'Migration 025+ must remain absent.');
assertM3Migration(str_contains($sql, 'DROP INDEX uq_component_definitions_key'), 'Old component-key uniqueness must be removed.');
assertM3Migration(str_contains($sql, 'UNIQUE KEY uq_component_definitions_key_version (component_key, implementation_version)'), 'Component key/version must be the immutable unique identity.');
assertM3Migration(!preg_match('/DELETE\s+FROM\s+component_(?:definitions|variants)|TRUNCATE|DROP\s+TABLE/i', $sql), 'Existing component rows and IDs must be preserved.');
assertM3Migration(!preg_match('/UPDATE\s+component_definitions/i', $sql), 'Historical implementation versions must never be updated in place.');
assertM3Migration(!str_contains($sql, 'legacy_247sp_page') && !str_contains($sql, 'legacy-preview-v1'), 'Migration 024 must leave legacy definition and variants untouched.');
assertM3Migration(str_contains(file_get_contents($migration023), 'UNIQUE KEY uq_component_variants_definition_key (component_definition_id, variant_key)'), 'Variant uniqueness within immutable definition must remain in migration 023.');
assertM3Migration(substr_count($sql, "'1.0.0'") >= 3, 'Authored definitions and variants must select implementation version 1.0.0 deterministically.');

$definitions = ['hero', 'statistics', 'service_grid', 'service_detail', 'trust_cards', 'about_content', 'contact_content', 'cta', 'lead_form', 'pricing_list', 'faq', 'text_block', 'site_header', 'site_footer', 'mobile_cta'];
foreach ($definitions as $definition) {
    assertM3Migration(str_contains($sql, "'{$definition}'"), "Migration must seed {$definition}.");
}
$variants = ['split_media', 'cards', 'banner', 'inline', 'link', 'accordion', 'standard', 'centered'];
foreach ($variants as $variant) {
    assertM3Migration(str_contains($sql, "'{$variant}'"), "Migration must seed variant {$variant}.");
}
assertM3Migration(substr_count($sql, 'INSERT INTO component_definitions') === 1, 'Definition seed must be one forward-reviewable insert.');
assertM3Migration(substr_count($sql, 'INSERT INTO component_variants') === 1, 'Variant seed must be one forward-reviewable insert.');
assertM3Migration(str_contains($sql, "JSON_OBJECT('scope', seeded.scope, 'authorable', TRUE, 'manifest_version', 1)"), 'Definition metadata must contain only safe manifest metadata.');
assertM3Migration(!preg_match('/renderer|class_name|php|javascript|include_path|template_path|filesystem/i', $sql), 'Migration metadata must contain no executable selector.');
assertM3Migration(!preg_match('/<\?(?:php|=)|<script\b|javascript\s*:/i', $sql), 'Migration must contain no executable payload.');
foreach (['site_build_jobs', 'site_deployments', 'site_domain_associations', 'site_routing_assignments', 'site_conversion_events', 'domains'] as $futureTable) {
    assertM3Migration(!str_contains($sql, 'CREATE TABLE ' . $futureTable), "Migration 024 must not add {$futureTable}.");
}
assertM3Migration(!str_contains($sql, 'CREATE TABLE'), 'Migration 024 must add no unrelated tables.');

echo "Website platform M3 migration: {$assertions} assertions passed.\n";
