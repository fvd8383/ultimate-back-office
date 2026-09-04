<?php

declare(strict_types=1);

require_once __DIR__ . '/support/WebsitePlatformM3ServiceDatabase.php';
require_once __DIR__ . '/../private/classes/SiteCompositionEditor.php';
require_once __DIR__ . '/../private/classes/Csrf.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ui_button(string $label): string { return '<button type="submit">' . e($label) . '</button>'; }
function renderM4BView(array $workspace, array $query): string
{
    $actorId = 1; $revisionId = 100; $_GET = $query;
    ob_start();
    require __DIR__ . '/../private/views/site-composer.php';
    return (string) ob_get_clean();
}
function submitM4BForm(DOMElement $form): array
{
    $pairs = [];
    foreach ((new DOMXPath($form->ownerDocument))->query('.//input|.//textarea|.//select', $form) as $element) {
        if ($element->tagName === 'input' && $element->getAttribute('type') === 'checkbox' && !$element->hasAttribute('checked')) continue;
        $value = $element->tagName === 'textarea' ? $element->textContent : $element->getAttribute('value');
        if ($element->tagName === 'select') {
            $options = $element->getElementsByTagName('option');
            $value = $options->item(0)?->getAttribute('value') ?? '';
            foreach ($options as $option) if ($option->hasAttribute('selected')) $value = $option->getAttribute('value');
        }
        $pairs[] = urlencode($element->getAttribute('name')) . '=' . urlencode($value);
    }
    if (count($pairs) >= 1000) throw new RuntimeException('Form exceeds PHP default input limit.');
    parse_str(implode('&', $pairs), $post);
    return $post;
}
$db = WebsitePlatformM3ServiceDatabase::fixture(); useWebsitePlatformM3ServiceDatabase($db);
$assertions = 0;
function checkM4BView(bool $condition, string $message): void
{
    global $assertions; $assertions++;
    if (!$condition) throw new RuntimeException($message);
}
Session::start(); $_SESSION = [];
$token = Csrf::token(SITE_PLATFORM_CSRF_SCOPE);
checkM4BView(!Csrf::validate($token, 'other-scope'), 'Composer CSRF token cannot cross scopes.');
foreach ([[], ['edit' => 'page', 'page_key' => 'home'], ['edit' => 'section', 'page_key' => 'home', 'section_key' => 'draft-content'], ['edit' => 'theme']] as $query) {
    $workspace = SiteCompositionEditor::workspace(1, 100);
    $html = renderM4BView($workspace, $query);
    checkM4BView(!str_contains($html, 'This selection cannot be edited'), 'View must render without a swallowed exception.');
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $saved = false;
    foreach ($dom->getElementsByTagName('form') as $form) {
        if (strtolower($form->getAttribute('method')) !== 'post') continue;
        $post = submitM4BForm($form);
        checkM4BView($post['expected_snapshot_hash'] === $workspace['composition']['snapshot_hash'], 'Rendered form carries exact revision hash.');
        checkM4BView($post['revision_id'] === '100', 'Rendered form is revision-scoped.');
        Csrf::requireValid($post['csrf_token'], SITE_PLATFORM_CSRF_SCOPE);
        if (!$saved && in_array($post['operation'], ['initialize_new', 'update_page', 'update_section', 'update_theme'], true)) {
            $before = count($db->events);
            SiteCompositionEditor::apply(1, 100, $post);
            checkM4BView(count($db->events) === $before + 1, 'Actual rendered form executes one successful composition mutation.');
            $saved = true;
        }
    }
    checkM4BView($saved, 'Each edit view must expose a usable save form.');
}
$workspace = SiteCompositionEditor::workspace(1, 100);
foreach ([['edit' => 'add_page'], ['edit' => 'add_section', 'page_key' => 'home', 'component' => 'faq@1.0.0'], ['edit' => 'add_section', 'page_key' => 'home', 'component' => 'hero@1.0.0']] as $query) {
    $html = renderM4BView($workspace, $query);
    checkM4BView(!str_contains($html, 'This selection cannot be edited') && str_contains($html, 'expected_snapshot_hash'), 'New page and section forms render.');
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    foreach ($dom->getElementsByTagName('form') as $form) submitM4BForm($form);
}
$old = Csrf::token(SITE_PLATFORM_CSRF_SCOPE); Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
checkM4BView(!Csrf::validate($old, SITE_PLATFORM_CSRF_SCOPE), 'Successful mutation rotation invalidates old token.');
echo "Website platform M4B views: {$assertions} assertions passed.\n";
