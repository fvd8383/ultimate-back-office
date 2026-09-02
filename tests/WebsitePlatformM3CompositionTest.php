<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/support/WebsitePlatformM3FakeDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionValidator.php';

$assertions = 0;
function assertM3Composition(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejectM3Composition(callable $callback, string $classification = 'invalid_request'): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM3Composition($exception->classification() === $classification, "Expected {$classification}; got {$exception->classification()}.");
        return;
    }
    throw new RuntimeException('Invalid composition was accepted.');
}
function m3Theme(): array
{
    return [
        'theme_key' => 'local_service', 'theme_version' => 1,
        'primary_color' => '#123456', 'secondary_color' => '#ABCDEF',
        'typography' => ['heading_family' => 'system_sans', 'body_family' => 'system_serif', 'scale' => 'standard'],
        'configuration' => [
            'section_spacing' => 'standard', 'corner_style' => 'soft', 'button_style' => 'rounded',
            'layouts' => [
                'site_header' => [
                    'component_key' => 'site_header', 'implementation_version' => '1.0.0', 'variant_key' => 'standard',
                    'configuration_schema_version' => 1, 'configuration' => ['show_phone' => true],
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
        'assets' => [],
    ];
}
function m3CompositionInput(): array
{
    return [
        'expected_snapshot_hash' => str_repeat('0', 64),
        'pages' => [[
            'page_key' => 'home', 'title' => 'Home', 'slug' => '', 'page_type' => 'home',
            'navigation_label' => 'Home', 'sort_order' => 10,
            'seo' => ['title' => 'Local service', 'description' => 'Trusted help', 'robots' => 'index_follow', 'canonical_policy' => 'self'],
            'presentation' => ['layout_width' => 'wide', 'show_in_navigation' => true],
            'sections' => [
                [
                    'section_key' => 'hero', 'component_key' => 'hero', 'implementation_version' => '1.0.0',
                    'variant_key' => 'split_media', 'configuration_schema_version' => 1, 'sort_order' => 10,
                    'configuration' => ['headline' => 'Trusted service', 'media_usage_key' => 'hero_photo', 'media_alt' => 'Team'],
                    'assets' => [['asset_id' => 1, 'usage_key' => 'hero_photo', 'source_reference' => 'selected-media']],
                ],
                [
                    'section_key' => 'pricing', 'component_key' => 'pricing_list', 'implementation_version' => '1.0.0',
                    'variant_key' => 'link', 'configuration_schema_version' => 1, 'sort_order' => 20,
                    'configuration' => ['label' => 'Pricing', 'document_usage_key' => 'pricing_pdf'],
                    'assets' => [['asset_id' => 2, 'usage_key' => 'pricing_pdf']],
                ],
            ],
        ]],
        'theme' => m3Theme(),
    ];
}

$database = new WebsitePlatformM3FakeDatabase();
$site = ['id' => 10, 'purpose' => '247sp'];
$normalized = SiteCompositionValidator::normalizeForAuthoring($database, $site, m3CompositionInput());
assertM3Composition(count($normalized['pages']) === 1 && count($normalized['pages'][0]['sections']) === 2, 'Valid full replacement must normalize.');
assertM3Composition(count($normalized['assets']) === 2, 'Existing asset references must normalize.');
assertM3Composition(preg_match('/^[a-f0-9]{64}$/', $normalized['pages'][0]['content_hash']) === 1, 'Page hashes must be deterministic SHA-256.');
assertM3Composition(preg_match('/^[a-f0-9]{64}$/', $normalized['pages'][0]['sections'][0]['content_hash']) === 1, 'Section hashes must be deterministic SHA-256.');
assertM3Composition(preg_match('/^[a-f0-9]{64}$/', $normalized['theme']['content_hash']) === 1, 'Theme hashes must be deterministic SHA-256.');
$again = SiteCompositionValidator::normalizeForAuthoring($database, $site, m3CompositionInput());
assertM3Composition(CanonicalJson::encode($normalized) === CanonicalJson::encode($again), 'Equivalent composition must normalize deterministically.');

$invalid = m3CompositionInput();
$invalid['pages'][0]['page_key'] = '../bad';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][0]['slug'] = 'Bad/Slug';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][] = $invalid['pages'][0];
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][0]['sections'][] = $invalid['pages'][0]['sections'][0];
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][0]['sections'] = [];
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][0]['page_type'] = 'service';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['pages'][0]['sections'][0]['component_key'] = 'site_header';
$invalid['pages'][0]['sections'][0]['variant_key'] = 'standard';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));

$emd = m3CompositionInput();
$emd['pages'][0]['page_type'] = 'landing';
$emd['pages'][0]['sections'][0]['variant_key'] = 'default';
$emd['pages'][0]['sections'][0]['configuration'] = ['headline' => 'Entry'];
$emd['pages'][0]['sections'][0]['assets'] = [];
$emd['pages'][0]['sections'][1]['component_key'] = 'lead_form';
$emd['pages'][0]['sections'][1]['variant_key'] = 'default';
$emd['pages'][0]['sections'][1]['configuration'] = ['heading' => 'Lead', 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => []];
$emd['pages'][0]['sections'][1]['assets'] = [];
assertM3Composition(count(SiteCompositionValidator::normalizeForAuthoring($database, ['id' => 10, 'purpose' => 'emd'], $emd)['pages']) === 1, 'EMD permits exactly one landing entry page.');
$emd['pages'][] = $emd['pages'][0];
$emd['pages'][1]['page_key'] = 'home';
$emd['pages'][1]['slug'] = 'home';
$emd['pages'][1]['page_type'] = 'home';
$emd['pages'][1]['sort_order'] = 20;
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, ['id' => 10, 'purpose' => 'emd'], $emd));

$invalid = m3CompositionInput();
$invalid['theme']['primary_color'] = 'red';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['theme']['typography']['scale'] = 'gigantic';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['theme']['configuration']['css'] = 'body{}';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['theme']['configuration']['layouts']['site_header']['component_key'] = 'hero';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));
$invalid = m3CompositionInput();
$invalid['theme']['theme_key'] = 'legacy_247sp_starter';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $invalid));

$crossSite = new WebsitePlatformM3FakeDatabase();
$crossSite->assets[1]['site_id'] = 11;
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($crossSite, $site, m3CompositionInput()));
$notReady = new WebsitePlatformM3FakeDatabase();
$notReady->assets[1]['lifecycle_status'] = 'processing';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($notReady, $site, m3CompositionInput()), 'conflict');
$unknownRights = new WebsitePlatformM3FakeDatabase();
$unknownRights->assets[1]['rights_classification'] = 'unknown';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($unknownRights, $site, m3CompositionInput()), 'conflict');
$expired = new WebsitePlatformM3FakeDatabase();
$expired->assets[1]['rights_expires_at'] = '2000-01-01 00:00:00';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($expired, $site, m3CompositionInput()), 'conflict');
$wrongBusiness = new WebsitePlatformM3FakeDatabase();
$wrongBusiness->assets[1]['business_id'] = 51;
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($wrongBusiness, $site, m3CompositionInput()), 'conflict');
$wrongDocument = new WebsitePlatformM3FakeDatabase();
$wrongDocument->assets[2]['asset_type'] = 'image';
$wrongDocument->assets[2]['mime_type'] = 'image/png';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($wrongDocument, $site, m3CompositionInput()));
$duplicateUsage = m3CompositionInput();
$duplicateUsage['pages'][0]['sections'][1]['assets'][0]['usage_key'] = 'hero_photo';
rejectM3Composition(static fn () => SiteCompositionValidator::normalizeForAuthoring($database, $site, $duplicateUsage));

$changed = m3CompositionInput();
$changed['pages'][0]['sections'][1]['variant_key'] = 'link';
$changed['theme']['configuration']['corner_style'] = 'rounded';
$changedNormalized = SiteCompositionValidator::normalizeForAuthoring($database, $site, $changed);
assertM3Composition($changedNormalized['theme']['content_hash'] !== $normalized['theme']['content_hash'], 'Theme token changes must change theme hash.');
$reordered = m3CompositionInput();
$reordered['pages'][0]['sections'][0]['sort_order'] = 20;
$reordered['pages'][0]['sections'][1]['sort_order'] = 10;
$reorderedNormalized = SiteCompositionValidator::normalizeForAuthoring($database, $site, $reordered);
assertM3Composition($reorderedNormalized['pages'][0]['content_hash'] !== $normalized['pages'][0]['content_hash'], 'Section order changes must change page hash.');

echo "Website platform M3 composition: {$assertions} assertions passed.\n";
