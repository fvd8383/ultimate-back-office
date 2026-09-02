<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteCompositionRenderer.php';

$assertions = 0;
function assertM3Renderer(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertM3Renderer(SiteComponentRenderers::escape('<script>"x" & y') === '&lt;script&gt;&quot;x&quot; &amp; y', 'Text and attribute escaping must use ENT_QUOTES.');
$text = SiteComponentRenderers::render('text_block', 'default', [
    'heading' => 'A < B', 'body' => '<script>alert("x")</script>', 'alignment' => 'left',
]);
assertM3Renderer(!str_contains($text, '<script>') && str_contains($text, '&lt;script&gt;'), 'Script-like text must never render raw.');

$cta = SiteComponentRenderers::render('cta', 'banner', ['heading' => null, 'body' => null, 'label' => 'Call "now"', 'action' => 'call'], [
    'call_href' => 'tel:+15551234567',
]);
assertM3Renderer(str_contains($cta, 'href="tel:+15551234567"'), 'Semantic call action must resolve from safe context.');
assertM3Renderer(str_contains($cta, 'Call &quot;now&quot;'), 'CTA labels must escape quotes.');
$unsafeCta = SiteComponentRenderers::render('cta', 'inline', ['heading' => null, 'body' => null, 'label' => 'Bad', 'action' => 'contact'], [
    'contact_href' => 'javascript:alert(1)',
]);
assertM3Renderer(!str_contains($unsafeCta, 'javascript:') && str_contains($unsafeCta, 'action-unavailable'), 'Unsafe action context must fail closed.');

$form = SiteComponentRenderers::render('lead_form', 'default', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send',
    'fields' => ['name', 'email'], 'required_fields' => ['email'],
]);
assertM3Renderer(!str_contains($form, '<form') && str_contains($form, 'data-preview="inert"'), 'Lead form without registered routing must not emit a form element.');
assertM3Renderer(str_contains($form, 'disabled') && str_contains($form, 'type="button"'), 'Inert lead controls must be disabled and non-submitting.');
assertM3Renderer(!str_contains($form, 'business_id') && !str_contains($form, 'site_id'), 'Lead form must not expose routing identifiers.');
$badAction = SiteComponentRenderers::render('lead_form', 'default', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => [],
], ['lead_form_action' => 'https://evil.example/collect']);
assertM3Renderer(!str_contains($badAction, '<form') && str_contains($badAction, 'data-preview="inert"'), 'External form actions must be rejected without emitting a form.');
$safeForm = SiteComponentRenderers::render('lead_form', 'default', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => [],
], ['lead_form_action' => '/internal/leads']);
assertM3Renderer(str_contains($safeForm, '<form method="post" action="/internal/leads">') && str_contains($safeForm, 'type="submit"'), 'A safe relative action may emit the POST form.');

$heroDefault = SiteComponentRenderers::render('hero', 'default', ['headline' => 'Hero']);
$heroSplit = SiteComponentRenderers::render('hero', 'split_media', ['headline' => 'Hero']);
assertM3Renderer($heroDefault !== $heroSplit && str_contains($heroSplit, 'hero--split-media'), 'Hero variant identity must change repository rendering semantics.');
$ctaBanner = SiteComponentRenderers::render('cta', 'banner', ['heading' => null, 'body' => null, 'label' => 'Go', 'action' => 'contact']);
$ctaInline = SiteComponentRenderers::render('cta', 'inline', ['heading' => null, 'body' => null, 'label' => 'Go', 'action' => 'contact']);
assertM3Renderer($ctaBanner !== $ctaInline && str_contains($ctaInline, 'cta--inline'), 'CTA variant identity must change repository rendering semantics.');
$headerStandard = SiteComponentRenderers::render('site_header', 'standard', ['show_phone' => false]);
$headerCentered = SiteComponentRenderers::render('site_header', 'centered', ['show_phone' => false]);
assertM3Renderer($headerStandard !== $headerCentered && str_contains($headerCentered, 'site-header--centered'), 'Header variant identity must change repository rendering semantics.');

$visibleHeader = SiteComponentRenderers::render('site_header', 'standard', ['show_phone' => true], ['call_href' => 'tel:+15551234567', 'phone_label' => '555-1234']);
$hiddenHeader = SiteComponentRenderers::render('site_header', 'standard', ['show_phone' => false], ['call_href' => 'tel:+15551234567']);
assertM3Renderer(str_contains($visibleHeader, '555-1234') && !str_contains($hiddenHeader, 'tel:'), 'show_phone must truthfully control the safe phone action.');
$visibleFooter = SiteComponentRenderers::render('site_footer', 'default', ['copyright_text' => 'C', 'show_navigation' => false, 'show_contact' => true], ['email_href' => 'mailto:hello@example.com']);
$hiddenFooter = SiteComponentRenderers::render('site_footer', 'default', ['copyright_text' => 'C', 'show_navigation' => false, 'show_contact' => false], ['email_href' => 'mailto:hello@example.com']);
assertM3Renderer(str_contains($visibleFooter, 'mailto:hello@example.com') && !str_contains($hiddenFooter, 'mailto:'), 'show_contact must truthfully control safe contact actions.');

foreach (['home', 'service', 'about', 'contact'] as $legacyVariant) {
    $legacyConfig = match ($legacyVariant) {
        'home' => ['headline' => 'Home <unsafe>', 'business_description' => 'Known body', 'hero_image_path' => 'javascript:bad'],
        'service' => ['service_name' => 'Service <unsafe>', 'service_description' => 'Known body', 'included_items' => ['One']],
        'about' => ['about_heading' => 'About <unsafe>', 'company_description' => 'Known body'],
        'contact' => ['contact_heading' => 'Contact <unsafe>', 'contact_description' => 'Known body'],
    };
    $legacyConfig['unknown_markup'] = '<script>alert(1)</script>';
    $legacy = SiteComponentRenderers::render('legacy_snapshot', $legacyVariant, $legacyConfig);
    assertM3Renderer(str_contains($legacy, 'Known body') && str_contains($legacy, '&lt;unsafe&gt;'), "Legacy {$legacyVariant} must render meaningful escaped known fields.");
    assertM3Renderer(!str_contains($legacy, '<script>') && !str_contains($legacy, 'javascript:bad'), "Legacy {$legacyVariant} must ignore unknown fields and raw asset paths.");
}

$composition = [
    'validated_for_rendering' => true,
    'theme' => [
        'theme_key' => 'local_service',
        'configuration' => ['layouts' => [
            'site_header' => [
                'component_key' => 'site_header', 'implementation_version' => '1.0.0', 'variant_key' => 'standard',
                'configuration' => ['show_phone' => true, 'tagline' => 'Safe & local'],
            ],
            'site_footer' => [
                'component_key' => 'site_footer', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
                'configuration' => ['copyright_text' => 'Example <LLC>', 'show_navigation' => true, 'show_contact' => true],
            ],
            'mobile_cta' => [
                'component_key' => 'mobile_cta', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
                'configuration' => ['label' => 'Call', 'action' => 'call'],
            ],
        ]],
    ],
    'pages' => [
        [
            'page_key' => 'hidden', 'title' => 'Hidden', 'slug' => 'hidden', 'navigation_label' => 'Hidden', 'sort_order' => 20,
            'presentation' => ['show_in_navigation' => false],
            'sections' => [[
                'component_key' => 'text_block', 'implementation_version' => '1.0.0', 'variant_key' => 'default', 'sort_order' => 10,
                'configuration' => ['heading' => null, 'body' => 'Hidden page body', 'alignment' => 'left'],
            ]],
        ],
        [
            'page_key' => 'home', 'title' => 'Home', 'slug' => '', 'navigation_label' => 'Home', 'sort_order' => 10,
            'presentation' => ['show_in_navigation' => true],
            'sections' => [[
                'component_key' => 'hero', 'implementation_version' => '1.0.0', 'variant_key' => 'default', 'sort_order' => 10,
                'configuration' => ['headline' => 'Welcome <home>'],
            ]],
        ],
    ],
];
$context = ['call_href' => 'tel:+15551234567', 'asset_urls' => []];
$first = SiteCompositionRenderer::render($composition, $context);
$second = SiteCompositionRenderer::render($composition, $context);
assertM3Renderer($first === $second, 'The same normalized input must render deterministically.');
assertM3Renderer(strpos($first, 'data-page-key="home"') < strpos($first, 'data-page-key="hidden"'), 'Pages must render in deterministic sort order.');
assertM3Renderer(substr_count($first, '>Home</a>') === 2, 'Visible navigation must appear in header and footer.');
assertM3Renderer(!str_contains($first, '>Hidden</a>'), 'Hidden navigation pages must be omitted.');
assertM3Renderer(str_contains($first, 'Welcome &lt;home&gt;') && str_contains($first, 'Example &lt;LLC&gt;'), 'Page and layout copy must be escaped.');

try {
    SiteCompositionRenderer::render([
        'validated_for_rendering' => true,
        'theme' => $composition['theme'],
        'pages' => [[
            'page_key' => 'bad', 'title' => 'Bad', 'slug' => 'bad', 'navigation_label' => 'Bad', 'sort_order' => 1,
            'presentation' => ['show_in_navigation' => true],
            'sections' => [[
                'component_key' => '../../evil.php', 'implementation_version' => '1.0.0', 'variant_key' => 'default',
                'sort_order' => 1, 'configuration' => [],
            ]],
        ]],
    ]);
    throw new RuntimeException('Arbitrary component path rendered.');
} catch (SiteServiceException $exception) {
    assertM3Renderer($exception->classification() === 'invalid_request', 'Unknown executable identities must fail closed.');
}

try {
    $unvalidated = $composition;
    unset($unvalidated['validated_for_rendering']);
    SiteCompositionRenderer::render($unvalidated);
    throw new RuntimeException('Unvalidated composition rendered.');
} catch (SiteServiceException $exception) {
    assertM3Renderer($exception->classification() === 'invalid_request', 'Renderer must reject models outside the validated read boundary.');
}

$legacySectionWithoutCompatibility = $composition;
$legacySectionWithoutCompatibility['historical'] = true;
$legacySectionWithoutCompatibility['legacy_compatibility'] = false;
$legacySectionWithoutCompatibility['pages'][0]['sections'] = [[
    'component_key' => 'legacy_247sp_page', 'implementation_version' => 'legacy-preview-v1',
    'variant_key' => 'home', 'sort_order' => 10, 'configuration' => ['headline' => 'Legacy'],
]];
try {
    SiteCompositionRenderer::render($legacySectionWithoutCompatibility);
    throw new RuntimeException('Legacy scope leaked into an ordinary page-section render.');
} catch (SiteServiceException $exception) {
    assertM3Renderer($exception->classification() === 'conflict', 'Legacy page-section scope requires the validated compatibility marker.');
}

$legacyLayout = $composition;
$legacyLayout['historical'] = true;
$legacyLayout['legacy_compatibility'] = true;
$legacyLayout['theme']['configuration']['layouts']['site_header'] = [
    'component_key' => 'legacy_247sp_page', 'implementation_version' => 'legacy-preview-v1',
    'variant_key' => 'home', 'configuration' => ['headline' => 'Legacy'],
];
try {
    SiteCompositionRenderer::render($legacyLayout);
    throw new RuntimeException('Legacy scope leaked into a layout render.');
} catch (SiteServiceException $exception) {
    assertM3Renderer($exception->classification() === 'conflict', 'Legacy compatibility must never permit legacy components as layouts.');
}

$rendererSource = file_get_contents(__DIR__ . '/../private/classes/SiteComponentRenderers.php');
assertM3Renderer(str_contains((string) $rendererSource, 'return match ($renderer)'), 'Renderer dispatch must be a repository-owned fixed match.');
assertM3Renderer(!preg_match('/include\s+\$|require\s+\$|call_user_func|\beval\s*\(/i', (string) $rendererSource), 'Renderer must not dynamically execute DB values.');

echo "Website platform M3 renderer: {$assertions} assertions passed.\n";
