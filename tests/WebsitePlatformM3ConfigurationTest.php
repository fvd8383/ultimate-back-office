<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/ComponentRegistry.php';

$assertions = 0;
function assertM3Config(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function validM3Config(string $key, string $variant, array $configuration): array
{
    return ComponentRegistry::validateConfiguration(ComponentRegistry::definition($key, '1.0.0'), $variant, 1, $configuration);
}
function rejectM3Config(callable $callback): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM3Config(in_array($exception->classification(), ['invalid_request', 'conflict'], true), 'Config rejection must be safe.');
        return;
    }
    throw new RuntimeException('Invalid component configuration was accepted.');
}

$minimums = [
    ['hero', 'default', ['headline' => 'Local expertise']],
    ['statistics', 'default', ['items' => [['value' => '24/7', 'label' => 'Service']]]],
    ['service_grid', 'cards', ['heading' => 'Services', 'services' => [['name' => 'Repair', 'description' => 'Careful repairs']]]],
    ['service_detail', 'default', ['heading' => 'Repair', 'body' => 'Detailed service', 'included_items' => []]],
    ['trust_cards', 'default', ['cards' => [['title' => 'Licensed', 'body' => 'Qualified team']]]],
    ['about_content', 'default', ['heading' => 'About us', 'body' => 'Local team']],
    ['contact_content', 'default', ['heading' => 'Contact', 'body' => 'We respond promptly']],
    ['cta', 'banner', ['label' => 'Call now', 'action' => 'call']],
    ['lead_form', 'default', ['heading' => 'Request service', 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => ['name']]],
    ['pricing_list', 'link', ['label' => 'Pricing', 'document_usage_key' => 'pricing_pdf']],
    ['faq', 'accordion', ['items' => [['question' => 'When?', 'answer' => 'Today.']]]],
    ['text_block', 'default', ['body' => 'Plain content', 'alignment' => 'left']],
    ['site_header', 'standard', ['show_phone' => true]],
    ['site_footer', 'default', ['copyright_text' => 'Example LLC', 'show_navigation' => true, 'show_contact' => true]],
    ['mobile_cta', 'default', ['label' => 'Contact', 'action' => 'contact']],
];
foreach ($minimums as [$key, $variant, $configuration]) {
    $normalized = validM3Config($key, $variant, $configuration);
    assertM3Config(is_array($normalized), "{$key} minimum configuration must pass.");
}

$fullHero = validM3Config('hero', 'split_media', [
    'headline' => 'Trusted & local', 'eyebrow' => 'Since 1999', 'body' => 'Fast and careful.',
    'primary_cta' => ['label' => 'Call', 'action' => 'call'],
    'secondary_cta' => ['label' => 'Email', 'action' => 'email'],
    'media_alt' => 'Team at work', 'media_usage_key' => 'hero_photo',
]);
assertM3Config($fullHero['media_usage_key'] === 'hero_photo', 'Representative full hero configuration must pass.');
assertM3Config(validM3Config('service_grid', 'cards', [
    'heading' => 'Services', 'intro' => 'Choose one',
    'services' => [['name' => 'Repair', 'description' => 'Description', 'path' => 'services/repair']],
])['services'][0]['path'] === 'services/repair', 'Validated relative paths must pass.');

rejectM3Config(static fn () => validM3Config('hero', 'default', []));
rejectM3Config(static fn () => validM3Config('hero', 'default', ['headline' => 'Hello', 'html' => '<b>bad</b>']));
rejectM3Config(static fn () => validM3Config('hero', 'default', ['headline' => 123]));
rejectM3Config(static fn () => validM3Config('hero', 'default', ['headline' => str_repeat('x', 161)]));
rejectM3Config(static fn () => validM3Config('cta', 'banner', ['label' => 'Go', 'action' => 'javascript:alert(1)']));
rejectM3Config(static fn () => validM3Config('hero', 'split_media', ['headline' => 'No media']));
rejectM3Config(static fn () => validM3Config('statistics', 'default', ['items' => []]));
rejectM3Config(static fn () => validM3Config('statistics', 'default', ['items' => array_fill(0, 5, ['value' => '1', 'label' => 'Too many'])]));
rejectM3Config(static fn () => validM3Config('faq', 'accordion', ['items' => [['question' => 'Q', 'answer' => 'A', 'onclick' => 'bad']]]));
rejectM3Config(static fn () => validM3Config('text_block', 'default', ['body' => '<script>alert(1)</script>', 'alignment' => 'left']));
rejectM3Config(static fn () => validM3Config('text_block', 'default', ['body' => '<?php echo 1;', 'alignment' => 'left']));
rejectM3Config(static fn () => validM3Config('text_block', 'default', ['body' => '<img onerror=alert(1)>', 'alignment' => 'left']));
rejectM3Config(static fn () => validM3Config('service_grid', 'cards', [
    'heading' => 'Services', 'services' => [['name' => 'Bad', 'description' => 'Bad', 'path' => '../secret']],
]));
rejectM3Config(static fn () => validM3Config('lead_form', 'default', [
    'heading' => 'Form', 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => ['email'],
]));
foreach (['business_id', 'site_id', 'routing_target', 'endpoint', 'webhook', 'action'] as $field) {
    rejectM3Config(static fn () => validM3Config('lead_form', 'default', [
        'heading' => 'Form', 'submit_label' => 'Send', 'fields' => ['name'], 'required_fields' => [], $field => 'bad',
    ]));
}
rejectM3Config(static fn () => ComponentRegistry::validateConfiguration(ComponentRegistry::definition('hero', '1.0.0'), 'unknown', 1, ['headline' => 'X']));
rejectM3Config(static fn () => ComponentRegistry::validateConfiguration(ComponentRegistry::definition('hero', '1.0.0'), 'default', 2, ['headline' => 'X']));

assertM3Config(CanonicalJson::hash(['b' => 1, 'a' => ['z' => 2, 'y' => 3]]) === CanonicalJson::hash(['a' => ['y' => 3, 'z' => 2], 'b' => 1]), 'Associative key order must not change canonical hashes.');
assertM3Config(CanonicalJson::hash(['a', 'b']) !== CanonicalJson::hash(['b', 'a']), 'List order must change canonical hashes.');
assertM3Config(preg_match('/^[a-f0-9]{64}$/', CanonicalJson::hash(['safe' => true])) === 1, 'Canonical hashes must be lowercase SHA-256.');

echo "Website platform M3 configuration: {$assertions} assertions passed.\n";
