<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';

$assertions = 0;
function assertM3Service(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expectM3Service(callable $callback, string $classification): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM3Service($exception->classification() === $classification, "Expected {$classification}; got {$exception->classification()}.");
        return;
    }
    throw new RuntimeException("Expected {$classification}.");
}
function m3ServiceTheme(): array
{
    return [
        'theme_key' => 'local_service', 'theme_version' => 1,
        'primary_color' => '#123456', 'secondary_color' => '#abcdef',
        'typography' => ['heading_family' => 'system_sans', 'body_family' => 'system_serif', 'scale' => 'standard'],
        'configuration' => [
            'section_spacing' => 'standard', 'corner_style' => 'soft', 'button_style' => 'rounded',
            'layouts' => [
                'site_header' => [
                    'component_key' => 'site_header', 'implementation_version' => '1.0.0', 'variant_key' => 'standard',
                    'configuration_schema_version' => 1, 'configuration' => ['show_phone' => true, 'logo_usage_key' => 'brand_logo'],
                ],
                'site_footer' => [
                    'component_key' => 'site_footer', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
                    'configuration_schema_version' => 1,
                    'configuration' => ['copyright_text' => 'Example LLC', 'show_navigation' => true, 'show_contact' => true],
                ],
                'mobile_cta' => [
                    'component_key' => 'mobile_cta', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
                    'configuration_schema_version' => 1, 'configuration' => ['label' => 'Call', 'action' => 'call'],
                ],
            ],
        ],
        'assets' => [['asset_id' => 1, 'usage_key' => 'brand_logo', 'source_reference' => 'selected-logo']],
    ];
}
function m3ServiceInput(string $expectedHash, string $variant = 'banner', bool $includeAbout = false): array
{
    $pages = [[
        'page_key' => 'home', 'title' => 'Home', 'slug' => '', 'page_type' => 'home',
        'navigation_label' => 'Home', 'sort_order' => 10,
        'seo' => ['title' => 'Home', 'description' => null, 'robots' => 'index_follow', 'canonical_policy' => 'self'],
        'presentation' => ['layout_width' => 'wide', 'show_in_navigation' => true],
        'sections' => [[
            'section_key' => 'cta', 'component_key' => 'cta', 'implementation_version' => '1.0.0',
            'variant_key' => $variant, 'configuration_schema_version' => 1, 'sort_order' => 10,
            'configuration' => ['heading' => 'Ready?', 'body' => null, 'label' => 'Contact', 'action' => 'contact'],
            'assets' => [],
        ]],
    ]];
    if ($includeAbout) {
        $pages[] = [
            'page_key' => 'about', 'title' => 'About', 'slug' => 'about', 'page_type' => 'about',
            'navigation_label' => 'About', 'sort_order' => 20,
            'seo' => ['title' => null, 'description' => null, 'robots' => 'index_follow', 'canonical_policy' => 'self'],
            'presentation' => ['layout_width' => 'standard', 'show_in_navigation' => true],
            'sections' => [[
                'section_key' => 'copy', 'component_key' => 'text_block', 'implementation_version' => '1.0.0',
                'variant_key' => 'default', 'configuration_schema_version' => 1, 'sort_order' => 10,
                'configuration' => ['heading' => null, 'body' => 'About us', 'alignment' => 'left'], 'assets' => [],
            ]],
        ];
    }
    return ['expected_snapshot_hash' => $expectedHash, 'pages' => $pages, 'theme' => m3ServiceTheme()];
}

$database = WebsitePlatformM3ServiceDatabase::fixture();
useWebsitePlatformM3ServiceDatabase($database);
$empty = SiteCompositionManager::compositionForActor(1, 100);
assertM3Service($empty['composition_state'] === 'empty' && $empty['pages'] === [] && $empty['theme'] === null && $empty['assets'] === [], 'Empty mutable drafts must have an explicit editor read state.');
expectM3Service(static fn () => SiteCompositionManager::validatedCompositionForActor(1, 100), 'conflict');

$database->internalAdmin = false;
expectM3Service(static fn () => SiteCompositionManager::compositionForActor(1, 100), 'unauthorized');
$database->internalAdmin = true;

$siteAssetsBefore = $database->siteAssets;
$first = SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput(str_repeat('0', 64), 'banner', true), '11111111-1111-4111-8111-111111111111');
assertM3Service($first['page_count'] === 2 && $first['section_count'] === 2 && $first['asset_reference_count'] === 1, 'Actual composition manager must replace pages, sections, theme, and assets as one unit.');
assertM3Service($database->siteAssets === $siteAssetsBefore, 'Composition replacement must not mutate site_assets.');
assertM3Service($first['snapshot_hash'] === SiteRevisionSnapshotHasher::hashStoredRevision($database, 100), 'Stored revision hash must equal the canonical stored representation.');
assertM3Service(count($database->events) === 1 && array_values($database->events)[0]['event_type'] === 'site_revision_composition_replaced', 'Successful replacement must emit one event.');
$stableIds = array_column($database->sitePages, 'id', 'page_key');

$editor = SiteCompositionManager::compositionForActor(1, 100);
assertM3Service($editor['composition_state'] === 'composed' && count($editor['pages']) === 2, 'Editor read must return composed pages and theme.');
assertM3Service(!str_contains(CanonicalJson::encode($editor['assets']), 'storage_key') && !str_contains(CanonicalJson::encode($editor['assets']), 'source_reference'), 'Editor asset usages must omit storage paths and private source evidence.');
$validated = SiteCompositionManager::validatedCompositionForActor(1, 100);
assertM3Service($validated['validated_for_rendering'] === true && $validated['composition_state'] === 'composed', 'Validated read must return an explicit safe render model.');
assertM3Service(!str_contains(CanonicalJson::encode($validated), 'storage_key'), 'Validated render model must not expose storage paths.');
$storedHash = $database->revisions[100]['snapshot_hash'];
$database->revisions[100]['snapshot_hash'] = str_repeat('f', 64);
expectM3Service(static fn () => SiteCompositionManager::validatedCompositionForActor(1, 100), 'conflict');
$database->revisions[100]['snapshot_hash'] = $storedHash;

$same = m3ServiceInput($first['snapshot_hash'], 'banner', true);
$same['pages'][0]['sections'][0]['configuration'] = ['action' => 'contact', 'label' => 'Contact', 'body' => null, 'heading' => 'Ready?'];
$second = SiteCompositionManager::replaceDraftComposition(1, 100, $same);
assertM3Service($second['snapshot_hash'] === $first['snapshot_hash'], 'Associative key ordering must not change the canonical hash.');
assertM3Service(array_column($database->sitePages, 'id', 'page_key') === $stableIds, 'Stable logical page IDs must be reused.');

$withoutAbout = SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($second['snapshot_hash'], 'banner', false));
assertM3Service(isset(array_column($database->sitePages, 'id', 'page_key')['about']), 'Omitted pages must retain their stable logical identity.');
expectM3Service(static fn () => SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($second['snapshot_hash'])), 'stale_write');
assertM3Service(count($database->revisionPages) === 1, 'Stale writes must fail before composition mutation.');

$inline = SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($withoutAbout['snapshot_hash'], 'inline'));
assertM3Service($inline['snapshot_hash'] !== $withoutAbout['snapshot_hash'], 'Variant identity must affect canonical snapshot hashes.');
expectM3Service(static fn () => SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($withoutAbout['snapshot_hash'], 'banner')), 'stale_write');

$beforeFailure = serialize([$database->revisionPages, $database->sections, $database->themes, $database->revisionAssets, $database->events, $database->revisions[100]['snapshot_hash']]);
$database->failAfterDeletion = true;
expectM3Service(static fn () => SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($inline['snapshot_hash'], 'banner')), 'database_failure');
$database->failAfterDeletion = false;
assertM3Service(serialize([$database->revisionPages, $database->sections, $database->themes, $database->revisionAssets, $database->events, $database->revisions[100]['snapshot_hash']]) === $beforeFailure, 'Failure after deletion must roll back rows, hash, and event atomically.');
assertM3Service($database->rollbackCount >= 1, 'Injected replacement failure must execute transaction rollback.');

$homeId = array_column($database->sitePages, 'id', 'page_key')['home'];
$database->sitePages[$homeId]['retired_at'] = '2026-09-02';
$eventsBeforeRetired = count($database->events);
expectM3Service(static fn () => SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($inline['snapshot_hash'], 'banner')), 'conflict');
assertM3Service(count($database->events) === $eventsBeforeRetired, 'Retired-page rejection must roll back without an event.');
$database->sitePages[$homeId]['retired_at'] = null;

$review = SiteRevisionManager::markReadyForReview(1, 100, '22222222-2222-4222-8222-222222222222');
assertM3Service($review['lifecycle_status'] === 'ready_for_review' && $database->revisions[100]['review_ready_at'] !== null, 'Actual review service must validate and transition a valid stored composition.');
expectM3Service(static fn () => SiteCompositionManager::replaceDraftComposition(1, 100, m3ServiceInput($inline['snapshot_hash'])), 'immutable_revision');
expectM3Service(static fn () => SiteRevisionManager::applyLifecycleTransition($database, $database->revisions[100], 'published'), 'future_gate_required');

echo "Website platform M3 service behavior: {$assertions} assertions passed.\n";
