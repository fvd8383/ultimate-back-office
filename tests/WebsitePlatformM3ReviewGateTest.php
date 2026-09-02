<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionRenderer.php';

$assertions = 0;
function assertM3Review(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejectM3Review(WebsitePlatformM3ServiceDatabase $database, string $message): void
{
    $events = count($database->events);
    try {
        SiteRevisionManager::markReadyForReview(1, 100);
    } catch (SiteServiceException $exception) {
        assertM3Review(in_array($exception->classification(), ['conflict', 'invalid_request'], true), $message . ' must fail closed.');
        assertM3Review($database->revisions[100]['lifecycle_status'] !== 'ready_for_review', $message . ' must not transition lifecycle.');
        assertM3Review(count($database->events) === $events, $message . ' must not emit a success event.');
        return;
    }
    throw new RuntimeException($message . ' was accepted.');
}
function m3ReviewTheme(): array
{
    return [
        'theme_key' => 'local_service', 'theme_version' => 1, 'primary_color' => '#123456', 'secondary_color' => '#abcdef',
        'typography' => ['heading_family' => 'system_sans', 'body_family' => 'system_sans', 'scale' => 'standard'],
        'configuration' => [
            'section_spacing' => 'standard', 'corner_style' => 'square', 'button_style' => 'square',
            'layouts' => [
                'site_header' => ['component_key' => 'site_header', 'implementation_version' => '1.0.0', 'variant_key' => 'standard', 'configuration_schema_version' => 1, 'configuration' => ['show_phone' => false]],
                'site_footer' => ['component_key' => 'site_footer', 'implementation_version' => '1.0.0', 'variant_key' => 'default', 'configuration_schema_version' => 1, 'configuration' => ['copyright_text' => 'Example', 'show_navigation' => true, 'show_contact' => false]],
                'mobile_cta' => ['component_key' => 'mobile_cta', 'implementation_version' => '1.0.0', 'variant_key' => 'default', 'configuration_schema_version' => 1, 'configuration' => ['label' => 'Call', 'action' => 'call']],
            ],
        ], 'assets' => [],
    ];
}
function m3ReviewInput(bool $withAsset = false): array
{
    $section = $withAsset ? [
        'section_key' => 'hero', 'component_key' => 'hero', 'implementation_version' => '1.0.0',
        'variant_key' => 'default', 'configuration_schema_version' => 1, 'sort_order' => 10,
        'configuration' => ['headline' => 'Home', 'media_usage_key' => 'hero_photo'],
        'assets' => [['asset_id' => 1, 'usage_key' => 'hero_photo', 'source_reference' => 'selected']],
    ] : [
        'section_key' => 'copy', 'component_key' => 'text_block', 'implementation_version' => '1.0.0',
        'variant_key' => 'default', 'configuration_schema_version' => 1, 'sort_order' => 10,
        'configuration' => ['heading' => null, 'body' => 'Home', 'alignment' => 'left'], 'assets' => [],
    ];
    return [
        'expected_snapshot_hash' => str_repeat('0', 64),
        'pages' => [[
            'page_key' => 'home', 'title' => 'Home', 'slug' => '', 'page_type' => 'home',
            'navigation_label' => 'Home', 'sort_order' => 10,
            'seo' => ['title' => null, 'description' => null, 'robots' => 'index_follow', 'canonical_policy' => 'self'],
            'presentation' => ['layout_width' => 'standard', 'show_in_navigation' => true], 'sections' => [$section],
        ]],
        'theme' => m3ReviewTheme(),
    ];
}
function composedM3ReviewDatabase(bool $withAsset = false): WebsitePlatformM3ServiceDatabase
{
    $database = WebsitePlatformM3ServiceDatabase::fixture();
    useWebsitePlatformM3ServiceDatabase($database);
    SiteCompositionManager::replaceDraftComposition(1, 100, m3ReviewInput($withAsset));
    return $database;
}
function legacyM3ReviewDatabase(bool $withUnknownAsset = true): WebsitePlatformM3ServiceDatabase
{
    $database = WebsitePlatformM3ServiceDatabase::fixture();
    useWebsitePlatformM3ServiceDatabase($database);
    $configuration = ['headline' => 'Legacy <home>'];
    $contentHash = CanonicalJson::hash($configuration);
    $legacyVariant = $database->definitions['legacy_247sp_page@legacy-preview-v1']['variants']['home']['variant_id'];
    $database->sitePages[2001] = ['id' => 2001, 'site_id' => 10, 'page_key' => 'home', 'retired_at' => null];
    $database->revisionPages[2002] = [
        'id' => 2002, 'site_id' => 10, 'revision_id' => 100, 'site_page_id' => 2001,
        'title' => 'Home', 'slug' => '', 'page_type' => 'home', 'navigation_label' => 'Home', 'sort_order' => 10,
        'seo_json' => '{}', 'presentation_json' => '{}', 'content_hash' => $contentHash,
    ];
    $database->sections[2003] = [
        'id' => 2003, 'site_id' => 10, 'revision_id' => 100, 'revision_page_id' => 2002,
        'section_key' => 'legacy-page-snapshot', 'component_variant_id' => $legacyVariant,
        'sort_order' => 10, 'configuration_schema_version' => 1,
        'configuration_json' => CanonicalJson::encode($configuration), 'content_hash' => $contentHash,
    ];
    $themeConfiguration = ['template_key' => 'starter_local_service'];
    $database->themes[2004] = [
        'id' => 2004, 'site_id' => 10, 'revision_id' => 100,
        'theme_key' => 'legacy_247sp_starter', 'theme_version' => 1,
        'primary_color' => null, 'secondary_color' => null, 'typography_json' => null,
        'configuration_json' => CanonicalJson::encode($themeConfiguration),
        'content_hash' => CanonicalJson::hash(['primary_color' => null, 'secondary_color' => null, 'configuration' => $themeConfiguration]),
    ];
    if ($withUnknownAsset) {
        $database->siteAssets[1] = [
            'id' => 1, 'site_id' => 10, 'business_id' => 50, 'active_business_id' => 50,
            'asset_type' => 'image', 'storage_key' => 'legacy/copied.jpg',
            'checksum_sha256' => str_repeat('d', 64), 'mime_type' => 'image/jpeg', 'byte_size' => 99,
            'source' => 'legacy_247sp', 'rights_classification' => 'unknown',
            'rights_metadata_json' => '{"legacy_reference":true,"review_required":true}',
            'rights_expires_at' => null, 'lifecycle_status' => 'ready',
        ];
        $database->revisionAssets[2005] = [
            'id' => 2005, 'site_id' => 10, 'revision_id' => 100, 'asset_id' => 1,
            'usage_key' => 'legacy_hero', 'revision_page_id' => 2002, 'section_id' => 2003,
            'source_reference' => 'copied/source/hero.jpg',
        ];
    }
    $database->revisions[100]['lifecycle_status'] = 'restored';
    $database->revisions[100]['restored_from_revision_id'] = 99;
    $database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100, SiteRevisionSnapshotHasher::MODE_LEGACY_M1);
    return $database;
}

$database = composedM3ReviewDatabase();
$sectionId = array_key_first($database->sections);
$database->sections[$sectionId]['configuration_schema_version'] = 2;
rejectM3Review($database, 'Schema-version drift');

$database = composedM3ReviewDatabase();
$sectionId = array_key_first($database->sections);
$database->sections[$sectionId]['configuration_json'] = '{"heading":null,"body":"Home","alignment":"left","unknown":"bad"}';
rejectM3Review($database, 'Configuration tampering');

$database = composedM3ReviewDatabase();
$sectionId = array_key_first($database->sections);
$database->variants[9999] = ['variant_id' => 9999, 'variant_key' => 'default', 'variant_schema_version' => 1, 'component_key' => 'unknown_component', 'implementation_version' => '1.0.0'];
$database->sections[$sectionId]['component_variant_id'] = 9999;
rejectM3Review($database, 'Unknown component identity');

$database = composedM3ReviewDatabase();
$sectionId = array_key_first($database->sections);
$serviceVariant = $database->definitions['service_detail@1.0.0']['variants']['default']['variant_id'];
$database->sections[$sectionId]['component_variant_id'] = $serviceVariant;
$database->sections[$sectionId]['configuration_json'] = '{"heading":"Service","body":"Body","included_items":[]}';
rejectM3Review($database, 'Page placement drift');

$database = composedM3ReviewDatabase(true);
$editorAssetRead = SiteCompositionManager::compositionForActor(1, 100);
assertM3Review($editorAssetRead['assets'][0]['asset_id'] === 1, 'Editor composition read must include asset_id for safe round trips.');
$database->siteAssets[1]['rights_classification'] = 'unknown';
rejectM3Review($database, 'Unknown rights on a normal composition');

$database = composedM3ReviewDatabase(true);
$database->siteAssets[1]['site_id'] = 999;
rejectM3Review($database, 'Cross-site asset ownership');

$database = composedM3ReviewDatabase();
$database->revisions[100]['snapshot_hash'] = str_repeat('f', 64);
rejectM3Review($database, 'Revision hash tampering');

$database = composedM3ReviewDatabase();
$database->sections[array_key_first($database->sections)]['content_hash'] = str_repeat('e', 64);
$database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100);
rejectM3Review($database, 'Section content hash tampering');

$database = composedM3ReviewDatabase();
$database->revisionPages[array_key_first($database->revisionPages)]['content_hash'] = str_repeat('e', 64);
$database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100);
rejectM3Review($database, 'Page content hash tampering');

$database = composedM3ReviewDatabase();
$database->themes[array_key_first($database->themes)]['content_hash'] = str_repeat('e', 64);
$database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100);
rejectM3Review($database, 'Theme content hash tampering');

$database = composedM3ReviewDatabase(true);
$existingSection = $database->sections[array_key_first($database->sections)];
$existingSection['id'] = 9001;
$existingSection['section_key'] = 'second-hero';
$existingSection['sort_order'] = 20;
$existingSection['configuration_json'] = '{"headline":"Second"}';
$existingSection['content_hash'] = CanonicalJson::hash([
    'component_key' => 'hero', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
    'configuration_schema_version' => 1, 'configuration' => ['headline' => 'Second'],
]);
$database->sections[9001] = $existingSection;
$database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100);
rejectM3Review($database, 'Stored cardinality violation');

$database = composedM3ReviewDatabase();
$database->revisions[100]['lifecycle_status'] = 'restored';
$database->revisions[100]['restored_from_revision_id'] = 99;
useWebsitePlatformM3ServiceDatabase($database);
$restoredAuthored = SiteRevisionManager::markReadyForReview(1, 100);
assertM3Review($restoredAuthored['lifecycle_status'] === 'ready_for_review', 'A restored known renderable authored version must pass exact stored validation.');

$database = composedM3ReviewDatabase(true);
$database->revisions[100]['lifecycle_status'] = 'restored';
$database->revisions[100]['restored_from_revision_id'] = 99;
$database->siteAssets[1]['rights_classification'] = 'unknown';
rejectM3Review($database, 'Unknown rights on a nonlegacy restore');

$database = legacyM3ReviewDatabase();
useWebsitePlatformM3ServiceDatabase($database);
$accepted = SiteRevisionManager::markReadyForReview(1, 100);
assertM3Review($accepted['lifecycle_status'] === 'ready_for_review', 'Exact restored M1 legacy snapshot with evidenced unknown rights must pass review.');
assertM3Review(count($database->events) === 1, 'Accepted restored legacy review must emit one event.');
$legacyRead = SiteCompositionManager::validatedCompositionForActor(1, 100);
assertM3Review($legacyRead['historical'] === true && $legacyRead['legacy_compatibility'] === true, 'Validated legacy read must carry the narrow server-produced compatibility marker after review transition.');
$legacyHtml = SiteCompositionRenderer::render($legacyRead);
assertM3Review(str_contains($legacyHtml, 'legacy-snapshot--home') && str_contains($legacyHtml, 'Legacy &lt;home&gt;'), 'Reviewed legacy restore must render meaningful escaped content through the top-level renderer.');
$database->revisions[100]['lifecycle_status'] = 'customer_approved';
$laterLegacyRead = SiteCompositionManager::validatedCompositionForActor(1, 100);
assertM3Review($laterLegacyRead['legacy_compatibility'] === true && str_contains(SiteCompositionRenderer::render($laterLegacyRead), 'Legacy &lt;home&gt;'), 'Durable restore provenance must preserve legacy rendering after later lifecycle transitions.');

$database = legacyM3ReviewDatabase(false);
useWebsitePlatformM3ServiceDatabase($database);
$exactLegacy = SiteRevisionManager::markReadyForReview(1, 100);
assertM3Review($exactLegacy['lifecycle_status'] === 'ready_for_review', 'An exact one-section restored legacy snapshot must pass without assets.');

$database = legacyM3ReviewDatabase(false);
$database->revisions[100]['lifecycle_status'] = 'draft';
$database->revisions[100]['restored_from_revision_id'] = null;
$database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100, SiteRevisionSnapshotHasher::MODE_LEGACY_M1);
rejectM3Review($database, 'Legacy component in a draft');

$database = legacyM3ReviewDatabase(false);
$database->revisions[100]['restored_from_revision_id'] = null;
rejectM3Review($database, 'Original imported legacy baseline');
try {
    SiteCompositionManager::validatedCompositionForActor(1, 100);
    throw new RuntimeException('Original imported baseline rendered through M3.');
} catch (SiteServiceException $exception) {
    assertM3Review($exception->classification() === 'conflict', 'Original imported baseline must be ineligible for validated M3 rendering.');
}

$database = composedM3ReviewDatabase();
useWebsitePlatformM3ServiceDatabase($database);
SiteRevisionManager::markReadyForReview(1, 100);
$database->definitions['text_block@1.0.0']['definition_status'] = 'inactive';
$database->definitions['text_block@1.0.0']['variants']['default']['variant_status'] = 'inactive';
$historicalAuthoredRead = SiteCompositionManager::validatedCompositionForActor(1, 100);
assertM3Review($historicalAuthoredRead['legacy_compatibility'] === false, 'Ordinary historical authored reads must never receive legacy compatibility.');
assertM3Review(str_contains(SiteCompositionRenderer::render($historicalAuthoredRead), 'Home'), 'Inactive exact authored versions must remain renderable after review.');

$database = composedM3ReviewDatabase();
$database->definitions['text_block@1.0.0']['definition_status'] = 'inactive';
$database->definitions['text_block@1.0.0']['variants']['default']['variant_status'] = 'inactive';
$inactiveDraftRead = SiteCompositionManager::validatedCompositionForActor(1, 100);
assertM3Review(str_contains(SiteCompositionRenderer::render($inactiveDraftRead), 'Home'), 'An already-stored draft may preview its inactive exact known version.');
rejectM3Review($database, 'Inactive authored version entering ordinary review');

foreach ([
    'wrong section key' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->sections[array_key_first($db->sections)]['section_key'] = 'other';
    },
    'wrong sort order' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->sections[array_key_first($db->sections)]['sort_order'] = 20;
    },
    'variant/page mismatch' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->revisionPages[array_key_first($db->revisionPages)]['page_type'] = 'about';
    },
    'duplicate section' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $copy = $db->sections[array_key_first($db->sections)];
        $copy['id'] = 3000;
        $db->sections[3000] = $copy;
    },
] as $message => $mutate) {
    $database = legacyM3ReviewDatabase(false);
    $mutate($database);
    $database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100, SiteRevisionSnapshotHasher::MODE_LEGACY_M1);
    rejectM3Review($database, 'Legacy ' . $message);
}

$database = legacyM3ReviewDatabase();
$database->siteAssets[1]['site_id'] = 999;
rejectM3Review($database, 'Cross-site legacy asset');

foreach ([
    'missing evidence flags' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->siteAssets[1]['rights_metadata_json'] = '{}';
    },
    'wrong source' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->siteAssets[1]['source'] = 'upload';
    },
    'prohibited rights' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->siteAssets[1]['rights_classification'] = 'prohibited';
    },
    'tampered checksum evidence' => static function (WebsitePlatformM3ServiceDatabase $db): void {
        $db->siteAssets[1]['checksum_sha256'] = 'bad';
    },
] as $message => $mutate) {
    $database = legacyM3ReviewDatabase();
    $mutate($database);
    $database->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($database, 100, SiteRevisionSnapshotHasher::MODE_LEGACY_M1);
    rejectM3Review($database, 'Legacy unknown-rights ' . $message);
}

echo "Website platform M3 review gate: {$assertions} assertions passed.\n";
