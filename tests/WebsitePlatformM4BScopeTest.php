<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assertions = 0;
function checkM4BScope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
}
$composer = file_get_contents($root . '/public/app/admin/site-composer.php');
$preview = file_get_contents($root . '/public/app/admin/site-preview.php');
$editor = file_get_contents($root . '/private/classes/SiteCompositionEditor.php');
$previewService = file_get_contents($root . '/private/classes/SiteAdminPreview.php');
$view = file_get_contents($root . '/private/views/site-composer.php');
$catalog = file_get_contents($root . '/private/classes/SiteAuthoringCatalog.php');
$forms = file_get_contents($root . '/private/classes/SiteSchemaForm.php');
foreach ([$composer, $preview] as $route) {
    checkM4BScope(str_contains($route, 'admin_bootstrap()'), 'Routes require an authenticated session.');
    checkM4BScope(str_contains($route, 'SiteAuthorizationPolicy::requireInternalAdmin'), 'Routes require internal admin authority.');
    checkM4BScope(str_contains($route, 'Cache-Control: private, no-store'), 'Admin drafts must not be cached.');
    checkM4BScope(!str_contains($route, '->prepare('), 'Routes contain no SQL.');
    checkM4BScope(str_contains($route, 'catch (Throwable'), 'Unexpected exceptions fail safely.');
}
foreach (['Csrf::requireValid(', "'admin-site-platform'", 'Csrf::rotate(', 'true, 303', 'SiteCompositionEditor::apply('] as $token) {
    checkM4BScope(str_contains($composer, $token), 'Composer CSRF/PRG/service contract: ' . $token);
}
checkM4BScope(strpos($composer, 'Csrf::requireValid(') < strpos($composer, 'SiteCompositionEditor::apply('), 'CSRF precedes mutation.');
checkM4BScope(strpos($composer, 'Csrf::rotate(') > strpos($composer, 'SiteCompositionEditor::apply('), 'Rotation follows successful mutation.');
checkM4BScope(str_contains($preview, "\$_SERVER['REQUEST_METHOD'] !== 'GET'") && str_contains($preview, 'http_response_code(405)'), 'Preview is GET only.');
checkM4BScope(str_contains($preview, 'sandbox=""'), 'Preview is isolated and cannot submit forms.');
checkM4BScope(str_contains($previewService, 'SiteCompositionManager::validatedCompositionForActor(') && str_contains($previewService, 'SiteCompositionRenderer::render($validated)'), 'Only validated model reaches renderer.');
checkM4BScope(!str_contains($previewService, 'lead_form_action'), 'No lead form action supplied.');
checkM4BScope(substr_count($editor, 'SiteCompositionManager::replaceDraftComposition(') === 1, 'All successful operations converge on exactly one M3 replacement.');
checkM4BScope(str_contains($view, "'expected_snapshot_hash' => \$composition['snapshot_hash']"), 'Every operation form includes the exact loaded hash.');
checkM4BScope(str_contains($editor, "\$input['expected_snapshot_hash'] = \$expected"), 'Submitted hash survives reconstruction.');
foreach ([$editor, $composer, $preview, $previewService, $view, $catalog, $forms] as $source) {
    checkM4BScope(!preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+(?:site_|sites)|DELETE\s+FROM)\b/i', $source), 'No direct composition/lifecycle writes.');
    checkM4BScope(!preg_match('/classifyMateriality\(|markReadyForReview\(|requestApproval\(|decideApproval\(|revokeApproval\(/', $source), 'No M4C actions.');
    checkM4BScope(!preg_match('/(?:Stripe|Twilio|Namecheap|SiteGenerator|WebsiteManager|DomainManager|LeadHub)::|curl_|file_put_contents\(/', $source), 'No legacy/provider calls.');
}
checkM4BScope(!str_contains($forms, 'json_decode('), 'No raw JSON configuration fallback.');
checkM4BScope(str_contains($catalog, 'ComponentRegistry::manifest()') && str_contains($catalog, 'ComponentRegistry::resolve('), 'Catalog uses repository and active DB identity.');
checkM4BScope(str_contains($catalog, 'ThemeRegistry::manifest()'), 'Theme choices derive from registry.');
$baseline = 'e848012415b06a4e122933e9368b905c9d7f0c44';
foreach (['database/migrations', 'public/marketing', 'private/classes/SiteGenerator.php', 'private/classes/WebsiteManager.php',
    'public/app/admin/websites.php', 'public/app/admin/website.php', 'public/app/admin/website-editor.php',
    'public/app/247sp/website-manager.php', 'infrastructure', 'private/classes/domains', 'public/accounts'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . $baseline . ' -- ' . escapeshellarg($path), $output, $status);
    checkM4BScope($status === 0, 'Protected path unchanged: ' . $path);
}
echo "Website platform M4B scope: {$assertions} assertions passed.\n";
