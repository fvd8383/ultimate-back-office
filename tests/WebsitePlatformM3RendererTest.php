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
$text = SiteComponentRenderers::render('text_block', [
    'heading' => 'A < B', 'body' => '<script>alert("x")</script>', 'alignment' => 'left',
]);
assertM3Renderer(!str_contains($text, '<script>') && str_contains($text, '&lt;script&gt;'), 'Script-like text must never render raw.');

$cta = SiteComponentRenderers::render('cta', ['heading' => null, 'body' => null, 'label' => 'Call "now"', 'action' => 'call'], [
    'call_href' => 'tel:+15551234567',
]);
assertM3Renderer(str_contains($cta, 'href="tel:+15551234567"'), 'Semantic call action must resolve from safe context.');
assertM3Renderer(str_contains($cta, 'Call &quot;now&quot;'), 'CTA labels must escape quotes.');
$unsafeCta = SiteComponentRenderers::render('cta', ['heading' => null, 'body' => null, 'label' => 'Bad', 'action' => 'contact'], [
    'contact_href' => 'javascript:alert(1)',
]);
assertM3Renderer(!str_contains($unsafeCta, 'javascript:') && str_contains($unsafeCta, 'action-unavailable'), 'Unsafe action context must fail closed.');

$form = SiteComponentRenderers::render('lead_form', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send',
    'fields' => ['name', 'email'], 'required_fields' => ['email'],
]);
assertM3Renderer(str_contains($form, 'action="" data-preview="inert"'), 'Lead form without registered routing must be inert.');
assertM3Renderer(!str_contains($form, 'business_id') && !str_contains($form, 'site_id'), 'Lead form must not expose routing identifiers.');
$badAction = SiteComponentRenderers::render('lead_form', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => [],
], ['lead_form_action' => 'https://evil.example/collect']);
assertM3Renderer(str_contains($badAction, 'action="" data-preview="inert"'), 'External form actions must be rejected.');

$composition = [
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

$rendererSource = file_get_contents(__DIR__ . '/../private/classes/SiteComponentRenderers.php');
assertM3Renderer(str_contains((string) $rendererSource, 'return match ($renderer)'), 'Renderer dispatch must be a repository-owned fixed match.');
assertM3Renderer(!preg_match('/include\s+\$|require\s+\$|call_user_func|\beval\s*\(/i', (string) $rendererSource), 'Renderer must not dynamically execute DB values.');

echo "Website platform M3 renderer: {$assertions} assertions passed.\n";
