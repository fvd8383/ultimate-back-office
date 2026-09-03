<?php

declare(strict_types=1);

error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionEditor.php';
require_once __DIR__ . '/../private/classes/SiteAdminPreview.php';

$assertions = 0;
function checkM4B(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
}
function rejectM4B(callable $call, string $classification): void
{
    try { $call(); } catch (SiteServiceException $e) {
        checkM4B($e->classification() === $classification, "Expected $classification, got {$e->classification()}: {$e->getMessage()}");
        return;
    }
    throw new RuntimeException("Expected $classification");
}
// Submit actual HTML successful controls, including unchecked rows and select defaults.
function formM4B(array $schema, mixed $value): array
{
    $html = SiteSchemaForm::render($schema, 'field', $value, 'Fixture');
    checkM4B(!str_contains($html, 'application/json'), 'Forms must have no JSON fallback.');
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8"><form>' . $html . '</form>');
    $query = [];
    foreach ((new DOMXPath($dom))->query('//input|//textarea|//select') as $element) {
        if ($element->tagName === 'input' && $element->getAttribute('type') === 'checkbox' && !$element->hasAttribute('checked')) continue;
        $value = $element->tagName === 'textarea' ? $element->textContent : $element->getAttribute('value');
        if ($element->tagName === 'select') {
            $options = $element->getElementsByTagName('option');
            $value = $options->item(0)?->getAttribute('value') ?? '';
            foreach ($options as $option) if ($option->hasAttribute('selected')) $value = $option->getAttribute('value');
        }
        $query[] = urlencode($element->getAttribute('name')) . '=' . urlencode($value);
    }
    checkM4B(count($query) < 900, 'A configuration form must fit the default PHP input limit.');
    parse_str(implode('&', $query), $post);
    return $post['field'];
}
function fixtureM4B(): WebsitePlatformM3ServiceDatabase
{
    $db = WebsitePlatformM3ServiceDatabase::fixture();
    useWebsitePlatformM3ServiceDatabase($db);
    return $db;
}
function applyM4B(WebsitePlatformM3ServiceDatabase $db, string $operation, array $fields = [], int $id = 100): array
{
    return SiteCompositionEditor::apply(1, $id, ['operation' => $operation,
        'expected_snapshot_hash' => $db->revisions[$id]['snapshot_hash']] + $fields);
}
function sectionM4B(string $key, array $configuration, string $section = 'extra', string $variant = 'default'): array
{
    $d = ComponentRegistry::definition($key, '1.0.0');
    return ['page_key' => 'home', 'section_key' => $section, 'component_identity' => $key . '@1.0.0',
        'variant_key' => $variant, 'configuration' => formM4B($d['configuration_schema'], $configuration), 'assets' => []];
}
function stateM4B(WebsitePlatformM3ServiceDatabase $db): string
{
    return serialize([$db->revisions, $db->sitePages, $db->revisionPages, $db->sections, $db->themes, $db->revisionAssets, $db->events]);
}

$samples = [
    'hero' => ['headline' => 'Draft headline', 'primary_cta' => ['label' => 'Contact', 'action' => 'contact'], 'media_usage_key' => 'hero_image'],
    'statistics' => ['items' => [['value' => 'Draft', 'label' => 'Pending']]],
    'service_grid' => ['heading' => 'Draft', 'services' => [['name' => 'Draft', 'description' => 'Pending', 'path' => 'service']]],
    'service_detail' => ['heading' => 'Draft', 'body' => 'Pending', 'included_items' => ['Draft item']],
    'trust_cards' => ['cards' => [['title' => 'Draft', 'body' => 'Pending']]],
    'about_content' => ['heading' => 'Draft', 'body' => 'Pending', 'highlights' => ['Draft']],
    'contact_content' => ['heading' => 'Draft', 'body' => 'Pending'],
    'cta' => ['label' => 'Contact', 'action' => 'contact'],
    'lead_form' => ['heading' => 'Draft', 'submit_label' => 'Send', 'fields' => ['name', 'email'], 'required_fields' => ['name']],
    'pricing_list' => ['label' => 'Draft', 'document_usage_key' => 'price_pdf'],
    'faq' => ['items' => [['question' => 'Draft?', 'answer' => 'Pending']]],
    'text_block' => ['body' => 'Content & <b>text</b>', 'alignment' => 'left'],
    'site_header' => ['show_phone' => false, 'tagline' => 'Draft'],
    'site_footer' => ['copyright_text' => 'Pending', 'show_navigation' => true, 'show_contact' => false],
    'mobile_cta' => ['label' => 'Contact', 'action' => 'contact'],
];
foreach (ComponentRegistry::manifest() as $definition) {
    if (!$definition['authorable']) continue;
    $sample = $samples[$definition['component_key']];
    $parsed = SiteSchemaForm::parse($definition['configuration_schema'], formM4B($definition['configuration_schema'], $sample));
    foreach ($definition['variants'] as $variant => $metadata) {
        checkM4B(ComponentRegistry::validateConfiguration($definition, $variant, 1, $parsed) === ComponentSchemaValidator::validate($sample, $definition['configuration_schema']), 'All authored section/layout schemas and variants must round trip.');
    }
}
checkM4B(SiteSchemaForm::parse(['type' => 'string', 'nullable' => true], ['value' => '']) === null, 'Nullable fields can be cleared.');
rejectM4B(fn () => SiteSchemaForm::render(['type' => 'object', 'passthrough' => true], 'x', [], 'X'), 'conflict');
rejectM4B(fn () => SiteSchemaForm::parse(['type' => 'boolean'], ['value' => 'yes']), 'invalid_request');
$arraySchema = ComponentRegistry::definition('lead_form', '1.0.0')['configuration_schema']['properties']['fields'];
rejectM4B(fn () => SiteSchemaForm::parse($arraySchema, formM4B($arraySchema, ['name', 'name'])), 'invalid_request');

$db = fixtureM4B();
$before = stateM4B($db);
$workspace = SiteCompositionEditor::workspace(1, 100);
checkM4B($workspace['composition']['composition_state'] === 'empty' && $workspace['composition']['snapshot_hash'] === str_repeat('0', 64), 'Empty workspace preserves exact seed hash.');
checkM4B(str_contains(SiteAdminPreview::render(1, 100), 'No composed preview is available yet.'), 'Empty preview is safe.');
checkM4B(stateM4B($db) === $before && $db->beginCount === 0, 'Workspace and preview GET services do not mutate.');
$db->internalRole = 'Super Admin';
checkM4B(SiteCompositionEditor::workspace(1, 100)['composition']['composition_state'] === 'empty', 'Super Admin authoring allowed.');
$db->internalAdmin = false;
foreach ([fn () => SiteCompositionEditor::workspace(1, 100), fn () => SiteAdminPreview::render(1, 100), fn () => applyM4B($db, 'initialize_new'), fn () => SiteAuthoringCatalog::forActor(1), fn () => SiteAuthoringCatalog::assetsForActor(1, 100)] as $call) rejectM4B($call, 'unauthorized');
$db->internalAdmin = true;
$catalog = SiteAuthoringCatalog::forActor(1);
checkM4B(count($catalog) === 15 && !isset($catalog['legacy_247sp_page@legacy-preview-v1']), 'Catalog exposes only authorable definitions.');
checkM4B($catalog['hero@1.0.0']['configuration_schema'] === ComponentRegistry::definition('hero', '1.0.0')['configuration_schema'], 'Schemas derive from repository.');
$db->definitions['hero@1.0.0']['definition_status'] = 'inactive';
checkM4B(!isset(SiteAuthoringCatalog::forActor(1)['hero@1.0.0']), 'Inactive definition excluded.');
$db->definitions['hero@1.0.0']['definition_status'] = 'active';
$db->definitions['hero@1.0.0']['variants']['split_media']['variant_status'] = 'inactive';
checkM4B(!isset(SiteAuthoringCatalog::forActor(1)['hero@1.0.0']['variants']['split_media']), 'Inactive variant excluded.');
$db->definitions['hero@1.0.0']['variants']['split_media']['variant_status'] = 'active';
checkM4B(!isset(SiteAuthoringCatalog::forActor(1, 'about')['lead_form@1.0.0']), 'Page filter enforced.');
checkM4B(!isset(SiteAuthoringCatalog::forActor(1, 'home', [['component_key' => 'hero']])['hero@1.0.0']), 'Catalog cardinality excludes full component.');

foreach (['247sp', 'emd', 'internal_demo'] as $purpose) {
    $db = fixtureM4B(); $db->sites[10]['purpose'] = $purpose;
    applyM4B($db, 'initialize_new');
    $model = SiteCompositionManager::validatedCompositionForActor(1, 100);
    checkM4B($model['theme']['theme_key'] === 'local_service' && count($model['pages']) === 1, 'Minimum initialization passes purpose rules.');
    checkM4B(str_contains(SiteAdminPreview::render(1, 100), 'Content pending review'), 'Initialization has explicit draft copy.');
    checkM4B(count($db->events) === 1 && $db->commitCount === 1, 'Initialization calls one atomic replacement.');
    rejectM4B(fn () => applyM4B($db, 'initialize_new'), 'conflict');
}
$db = fixtureM4B(); applyM4B($db, 'initialize_new');
$page = ['page_key' => 'about', 'title' => 'Draft about', 'slug' => 'about', 'page_type' => 'about',
    'navigation_label' => 'About', 'seo' => ['title' => 'Draft SEO', 'description' => 'Draft description', 'robots' => 'noindex_follow', 'canonical_policy' => 'none'],
    'presentation' => ['layout_width' => 'wide', 'show_in_navigation' => false]];
applyM4B($db, 'add_page', ['page' => formM4B(SiteCompositionEditor::pageFormSchema(true), $page)]);
checkM4B(count($db->revisionPages) === 2, 'Add page persists minimum valid section.');
$page['title'] = 'Changed';
applyM4B($db, 'update_page', ['page_key' => 'about', 'page' => formM4B(SiteCompositionEditor::pageFormSchema(false), $page)]);
applyM4B($db, 'move_page', ['page_key' => 'about', 'direction' => 'up']);
$model = SiteCompositionManager::compositionForActor(1, 100);
checkM4B($model['pages'][0]['page_key'] === 'about' && array_column($model['pages'], 'sort_order') === [10, 20], 'Page identity stable; deterministic reordering.');
rejectM4B(fn () => applyM4B($db, 'add_page', ['page' => formM4B(SiteCompositionEditor::pageFormSchema(true), $page)]), 'invalid_request');
$page['page_key'] = 'other';
rejectM4B(fn () => applyM4B($db, 'add_page', ['page' => formM4B(SiteCompositionEditor::pageFormSchema(true), $page)]), 'invalid_request');
$invalid = formM4B(SiteCompositionEditor::pageFormSchema(true), $page); $invalid['value']['page_type']['value'] = 'blog';
rejectM4B(fn () => applyM4B($db, 'add_page', ['page' => $invalid]), 'invalid_request');
applyM4B($db, 'remove_page', ['page_key' => 'about']);
rejectM4B(fn () => applyM4B($db, 'remove_page', ['page_key' => 'home']), 'invalid_request');
$hero = sectionM4B('hero', ['headline' => 'Draft <b>heading</b>'], 'hero');
applyM4B($db, 'add_section', $hero + ['implementation_version' => 'forged', 'configuration_schema_version' => 999]);
$model = SiteCompositionManager::compositionForActor(1, 100);
checkM4B($model['pages'][0]['sections'][1]['implementation_version'] === '1.0.0' && $model['pages'][0]['sections'][1]['configuration_schema_version'] === 1, 'Browser hidden versions cannot override server identity.');
$hero2 = $hero; $hero2['section_key'] = 'second-hero';
rejectM4B(fn () => applyM4B($db, 'add_section', $hero2), 'invalid_request');
rejectM4B(fn () => applyM4B($db, 'add_section', $hero), 'invalid_request');
rejectM4B(fn () => applyM4B($db, 'add_section', sectionM4B('service_detail', $samples['service_detail'])), 'invalid_request');
$legacy = $hero; $legacy['component_identity'] = 'legacy_247sp_page@legacy-preview-v1';
rejectM4B(fn () => applyM4B($db, 'add_section', $legacy), 'invalid_request');
applyM4B($db, 'move_section', ['page_key' => 'home', 'section_key' => 'hero', 'direction' => 'up']);
checkM4B(SiteCompositionManager::compositionForActor(1, 100)['pages'][0]['sections'][0]['section_key'] === 'hero', 'Section move keeps stable key.');
$hero['variant_key'] = 'split_media';
rejectM4B(fn () => applyM4B($db, 'update_section', $hero), 'invalid_request');
$hero = sectionM4B('hero', ['headline' => 'Draft <b>heading</b>', 'media_usage_key' => 'hero_image'], 'hero', 'split_media');
$hero['assets'] = [['asset_id' => '1', 'usage_key' => 'hero_image']];
applyM4B($db, 'update_section', $hero);
checkM4B(SiteCompositionManager::compositionForActor(1, 100)['pages'][0]['sections'][0]['variant_key'] === 'split_media', 'Variant and same-site image persist.');
$assetBefore = $db->siteAssets;
foreach ([['site_id', 99], ['business_id', 99], ['rights_classification', 'unknown'], ['rights_classification', 'prohibited'], ['rights_expires_at', '2000-01-01'], ['lifecycle_status', 'pending']] as [$field, $value]) {
    $db->siteAssets[1][$field] = $value;
    checkM4B(SiteAuthoringCatalog::assetsForActor(1, 100) === [], 'Ineligible asset excluded from UI.');
    $before = stateM4B($db);
    rejectM4B(fn () => applyM4B($db, 'update_section', $hero), $field === 'site_id' ? 'invalid_request' : 'conflict');
    checkM4B(stateM4B($db) === $before, 'Invalid asset cannot partially save.');
    $db->siteAssets = $assetBefore;
}
$forged = $hero; $forged['assets'][0]['asset_id'] = '99999';
rejectM4B(fn () => applyM4B($db, 'update_section', $forged), 'invalid_request');
$db->siteAssets[2] = $db->siteAssets[1]; $db->siteAssets[2]['id'] = 2; $db->siteAssets[2]['asset_type'] = 'document';
$price = sectionM4B('pricing_list', $samples['pricing_list'], 'prices', 'link'); $price['assets'] = [['asset_id' => '2', 'usage_key' => 'price_pdf']];
rejectM4B(fn () => applyM4B($db, 'add_section', $price), 'invalid_request');
$db->siteAssets[2]['mime_type'] = 'application/pdf'; applyM4B($db, 'add_section', $price);
$collision = sectionM4B('about_content', ['heading' => 'Draft', 'body' => 'Pending', 'media_usage_key' => 'hero_image'], 'about');
$collision['assets'] = [['asset_id' => '1', 'usage_key' => 'hero_image']];
rejectM4B(fn () => applyM4B($db, 'add_section', $collision), 'invalid_request');
applyM4B($db, 'add_section', sectionM4B('lead_form', $samples['lead_form'], 'lead'));
$previewBefore = stateM4B($db); $html = SiteAdminPreview::render(1, 100);
checkM4B(str_contains($html, '&lt;b&gt;heading&lt;/b&gt;') && str_contains($html, 'data-preview="inert"') && !str_contains($html, '<form'), 'Preview escapes copy and keeps lead forms inert.');
checkM4B(stateM4B($db) === $previewBefore, 'Composed preview causes no mutations.');
$hash = $db->revisions[100]['snapshot_hash']; $db->revisions[100]['snapshot_hash'] = str_repeat('f', 64);
rejectM4B(fn () => SiteAdminPreview::render(1, 100), 'conflict'); $db->revisions[100]['snapshot_hash'] = $hash;
applyM4B($db, 'remove_section', ['page_key' => 'home', 'section_key' => 'prices']);
$before = stateM4B($db);
rejectM4B(fn () => SiteCompositionEditor::apply(1, 100, ['operation' => 'remove_section', 'page_key' => 'home', 'section_key' => 'hero', 'expected_snapshot_hash' => $hash]), 'stale_write');
checkM4B(stateM4B($db) === $before, 'Old H never retries, writes rows, or creates a false event.');
$db->failAfterDeletion = true;
rejectM4B(fn () => applyM4B($db, 'remove_section', ['page_key' => 'home', 'section_key' => 'hero']), 'database_failure');
$db->failAfterDeletion = false;
checkM4B(stateM4B($db) === $before && $db->rollbackCount > 0, 'M4B replacement rolls back failures after deletion.');

$theme = SiteCompositionManager::compositionForActor(1, 100)['theme'];
$theme['primary_color'] = '#abcdef'; $theme['typography']['scale'] = 'large';
$post = ['theme_identity' => 'local_service@1', 'theme' => formM4B(SiteCompositionEditor::themeFormSchema(), $theme), 'layouts' => []];
foreach ($theme['configuration']['layouts'] as $slot => $selection) {
    $post['layouts'][$slot] = ['component_identity' => $slot . '@1.0.0', 'variant_key' => $slot === 'site_header' ? 'centered' : 'default',
        'configuration' => formM4B(ComponentRegistry::definition($slot, '1.0.0')['configuration_schema'], $selection['configuration'])];
}
applyM4B($db, 'update_theme', $post);
$model = SiteCompositionManager::compositionForActor(1, 100);
checkM4B($model['theme']['primary_color'] === '#ABCDEF' && $model['theme']['typography']['scale'] === 'large' && $model['theme']['configuration']['layouts']['site_header']['variant_key'] === 'centered', 'Theme uses M3 color normalization, typography, and layout variants.');
$badTheme = $post; $badTheme['theme_identity'] = 'legacy_247sp_starter@1';
rejectM4B(fn () => applyM4B($db, 'update_theme', $badTheme), 'invalid_request');
$badTheme = $post; $badTheme['theme']['value']['primary_color']['value'] = 'red';
rejectM4B(fn () => applyM4B($db, 'update_theme', $badTheme), 'invalid_request');

// Based-on source is immutable and remains byte-for-byte unchanged.
$db->revisions[100]['lifecycle_status'] = 'published';
$db->revisions[101] = $db->revisions[100];
$db->revisions[101]['id'] = 101; $db->revisions[101]['revision_number'] = 2; $db->revisions[101]['based_on_revision_id'] = 100;
$db->revisions[101]['lifecycle_status'] = 'draft'; $db->revisions[101]['snapshot_hash'] = str_repeat('1', 64); $db->revisions[101]['facts_snapshot_json'] = '{"purpose":"draft successor"}';
$source = SiteCompositionManager::compositionForActor(1, 100);
applyM4B($db, 'initialize_from_based_on', [], 101);
checkM4B(SiteCompositionManager::compositionForActor(1, 100) === $source, 'Source composition/hash unchanged.');
checkM4B($db->revisions[101]['snapshot_hash'] !== $source['snapshot_hash'] && $db->revisions[101]['snapshot_hash'] === SiteRevisionSnapshotHasher::hashStoredRevision($db, 101), 'Target canonical hash uses its own immutable facts.');
$sourceIds = array_column(array_filter($db->revisionPages, fn ($r) => $r['revision_id'] === 100), 'id');
$targetIds = array_column(array_filter($db->revisionPages, fn ($r) => $r['revision_id'] === 101), 'id');
checkM4B(array_intersect($sourceIds, $targetIds) === [], 'Source revision page IDs are never copied.');
rejectM4B(fn () => applyM4B($db, 'remove_page', ['page_key' => 'home'], 100), 'immutable_revision');
foreach (['cross_site', 'empty', 'inactive', 'unknown_version'] as $case) {
    $db = fixtureM4B();
    if ($case !== 'empty') applyM4B($db, 'initialize_new');
    $db->revisions[100]['lifecycle_status'] = 'published';
    $db->revisions[101] = $db->revisions[100]; $db->revisions[101]['id'] = 101; $db->revisions[101]['based_on_revision_id'] = 100;
    $db->revisions[101]['lifecycle_status'] = 'draft'; $db->revisions[101]['snapshot_hash'] = str_repeat('1', 64);
    if ($case === 'cross_site') $db->revisions[100]['site_id'] = 11;
    if ($case === 'inactive') $db->definitions['text_block@1.0.0']['definition_status'] = 'inactive';
    if ($case === 'unknown_version') {
        foreach ($db->variants as &$v) if ($v['component_key'] === 'text_block') $v['implementation_version'] = 'retired-missing';
        unset($v);
    }
    $before = stateM4B($db);
    rejectM4B(fn () => applyM4B($db, 'initialize_from_based_on', [], 101), in_array($case, ['cross_site', 'unknown_version'], true) ? 'invalid_request' : 'conflict');
    checkM4B(stateM4B($db) === $before, 'Rejected based-on initialization leaves source and target untouched.');
}
$db = fixtureM4B(); applyM4B($db, 'initialize_new');
applyM4B($db, 'add_section', $hero);
foreach ($db->revisionAssets as &$reference) $reference['source_reference'] = 'existing-selection-provenance';
unset($reference);
$db->revisions[100]['snapshot_hash'] = SiteRevisionSnapshotHasher::hashStoredRevision($db, 100);
applyM4B($db, 'update_section', $hero);
checkM4B(array_values($db->revisionAssets)[0]['source_reference'] === 'existing-selection-provenance', 'Updating retained assets preserves server-side provenance.');
$db->siteAssets[1]['rights_expires_at'] = '2000-01-01';
applyM4B($db, 'remove_section', ['page_key' => 'home', 'section_key' => 'hero']);
checkM4B($db->revisionAssets === [] && str_contains(SiteAdminPreview::render(1, 100), 'Content pending review'), 'An expired asset can be removed explicitly to repair a draft.');
echo "Website platform M4B behavior: {$assertions} assertions passed.\n";
