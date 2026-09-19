<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BScope.php';

$root = dirname(__DIR__);
$assertions = 0;
function checkM5AScope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
}
function m5aGitQuiet(string $root, string $baseline, string $path): bool
{
    $output = [];
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet ' . escapeshellarg($baseline) . ' -- ' . escapeshellarg($path), $output, $status);
    return $status === 0;
}

$baseline = '2b110466e6cf0ef0e456a1c3ea874f7622daab95';
$workflow = file_get_contents($root . '/private/classes/SiteCustomerReviewWorkflow.php');
$previewService = file_get_contents($root . '/private/classes/SiteCustomerPreview.php');
$manager = file_get_contents($root . '/public/app/247sp/website-manager.php');
$previewRoute = file_get_contents($root . '/public/app/247sp/website-review-preview.php');
$reviewView = file_get_contents($root . '/private/views/site-customer-review.php');
$previewView = file_get_contents($root . '/private/views/site-customer-preview.php');
$renderers = file_get_contents($root . '/private/classes/SiteComponentRenderers.php');
foreach ([$workflow, $previewService, $manager, $previewRoute, $reviewView, $previewView, $renderers] as $source) {
    checkM5AScope(is_string($source), 'Every M5A source must be readable.');
}

$output = [];
exec('git -C ' . escapeshellarg($root) . ' rev-parse ' . escapeshellarg($baseline), $output, $status);
checkM5AScope($status === 0 && trim(implode("\n", $output)) === $baseline, 'The authoritative post-PR-114 M5A baseline must exist.');
foreach (['database/migrations/023_website_platform_foundation.sql', 'database/migrations/024_component_registry_versioning.sql',
    'private/classes/SiteGenerator.php', 'private/classes/WebsiteManager.php', 'public/marketing', 'public/accounts',
    'public/app/admin', 'public/app/247sp/site-preview.php',
    'public/app/247sp/lead-submit.php', 'public/app/247sp/onboarding.php', 'public/app/247sp/review.php',
    'public/app/247sp/business-profile.php', 'infrastructure', 'private/classes/domains'] as $path) {
    checkM5AScope(m5aGitQuiet($root, $baseline, $path), 'M5A protected path remains unchanged: ' . $path);
}
checkM5AScope(m6bOnlyMigration025($root), 'Only the separately authorized M6B migration 025 may exist after 024.');

checkM5AScope(str_contains($manager, 'SiteCustomerReviewWorkflow::workspaceWithForms('), 'Website Manager delegates generic customer reads to the M5A workflow.');
checkM5AScope(str_contains($manager, 'WebsiteManager::saveWebsiteManager(') && str_contains($manager, 'SiteGenerator::websiteForBusiness('), 'Website Manager retains the legacy save and generated-site readers.');
checkM5AScope(!preg_match('/TwentyFourSevenSalesPartner::(?:approveWebsiteLaunch|requestWebsiteChanges|websiteLaunchApproval)/', $manager), 'Generic review remains separate from legacy activity-based launch approval.');
checkM5AScope(str_contains($manager, "\$_SERVER['REQUEST_METHOD'] === 'GET'"), 'Generic customer workflow is dispatched only on GET.');
checkM5AScope(str_contains($manager, "!is_string(\$_POST['action'] ?? null)") && str_contains($manager, "\$_POST['action'] === 'legacy_save'"), 'Explicit M5B actions prevent generic POST fallthrough to the legacy save.');
checkM5AScope(!preg_match('/SiteApprovalManager::|SiteReviewAdminWorkflow::|SiteCompositionEditor::/', $manager), 'Customer manager contains no generic approval or composition mutation.');
foreach (['Csrf::requireValid(', "'customer-website-manager'", 'Csrf::rotate(', 'true, 303', "Csrf::input('customer-website-manager')"] as $token) {
    checkM5AScope(str_contains($manager, $token), 'Retained legacy manager POST security: ' . $token);
}
checkM5AScope(strpos($manager, 'Csrf::requireValid(') < strpos($manager, 'WebsiteManager::saveWebsiteManager(')
    && strrpos($manager, 'Csrf::rotate(') > strpos($manager, 'WebsiteManager::saveWebsiteManager('), 'CSRF validates before the retained save and rotates only after success.');
checkM5AScope(str_contains($manager, 'catch (CsrfException') && str_contains($manager, 'http_response_code(403)'), 'Invalid retained-form CSRF fails with a safe 403.');

checkM5AScope(str_contains($previewRoute, "\$_SERVER['REQUEST_METHOD'] !== 'GET'") && str_contains($previewRoute, 'http_response_code(405)') && str_contains($previewRoute, "header('Allow: GET')"), 'Customer preview route is GET only with safe 405 behavior.');
checkM5AScope(str_contains($previewRoute, 'Session::requireAuth(') && str_contains($previewRoute, 'Auth::currentUser()'), 'Customer preview route requires the ordinary authenticated session.');
checkM5AScope(substr_count($previewRoute, 'SiteCustomerPreview::headers()') === 2
    && substr_count($manager, 'SiteCustomerPreview::headers()') === 2, 'Private response headers are reasserted after session startup.');
checkM5AScope(str_contains($previewRoute, "array_diff(array_keys(\$_GET), ['business_id', 'request_id'])"), 'Customer preview route accepts only its bounded GET parameters.');
checkM5AScope(!str_contains($previewRoute, '->prepare('), 'Customer preview route contains no SQL.');
checkM5AScope(str_contains($previewRoute, 'SiteCustomerPreview::render(') && str_contains($previewRoute, 'SiteCustomerReviewWorkflow::positiveId('), 'Customer preview uses the dedicated service and strict IDs.');

foreach (['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer',
    'Content-Type' => 'text/html; charset=UTF-8', 'X-Frame-Options' => 'SAMEORIGIN', 'Content-Security-Policy' => "frame-ancestors 'self'"] as $name => $value) {
    checkM5AScope(str_contains($previewService, "'{$name}'") && str_contains($previewService, $value), 'Review shell header is fixed: ' . $name);
}
checkM5AScope(str_contains($previewView, 'sandbox=""') && str_contains($previewView, 'srcdoc="<?= e(') && str_contains($previewView, 'title="Private preview of revision'), 'Preview uses escaped srcdoc, an empty sandbox, and a descriptive title.');
checkM5AScope(str_contains($previewService, 'SiteCompositionManager::validatedCompositionForActor(') || str_contains($workflow, 'SiteCompositionManager::validatedCompositionForActor('), 'Only the validated M3 composition boundary supplies preview data.');
checkM5AScope(str_contains($previewService, "['preview_mode' => true]"), 'Renderer receives an explicit inert preview mode.');
checkM5AScope(str_contains($renderers, "\$context = ['preview_mode' => true, 'navigation'"), 'Preview mode discards all action and asset URL context.');

$m5aSources = implode("\n", [$workflow, $previewService, $previewRoute, $reviewView, $previewView]);
checkM5AScope(!preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+(?:sites|site_|business)|DELETE\s+FROM|REPLACE\s+INTO|ALTER\s+TABLE|CREATE\s+TABLE|DROP\s+TABLE)\b/i', $m5aSources), 'M5A services/routes/views contain no direct mutation SQL.');
checkM5AScope(!preg_match('/(?:requestApproval|revokeApproval|markReadyForReview|createAuthoredDraftRevision|replaceDraftComposition)\s*\(/', $m5aSources), 'M5B delegates only customer decisions and feedback, with no authoring or internal requests.');
checkM5AScope(str_contains($workflow, 'SiteApprovalManager::decideApproval(') && str_contains($workflow, 'SiteApprovalManager::recordCustomerFeedback('), 'M5B reuses the authoritative approval manager.');
checkM5AScope(!preg_match('/(?:Stripe|Twilio|Retell|Vendasta|Namecheap|DigitalOcean|DomainManager|LeadHub|SiteGenerator|WebsiteManager)::|\b(?:curl_|fsockopen|stream_socket_client|file_put_contents|mkdir|rename|unlink|copy)\s*\(/i', $m5aSources), 'M5A customer boundary has no provider, legacy runtime, network, domain, lead, or filesystem side effects.');
checkM5AScope(!preg_match('/class\s+(?:SiteBuildService|SitePublisher|SiteDeploymentManager)|->\s*(?:publish|deploy|build|restore)\s*\(/i', $m5aSources), 'M6 build, deployment, publishing, and restore work has not begun.');
checkM5AScope(str_contains($reviewView, "!empty(\$customerReview['submission'])"), 'M5B mutation forms require an eligible server-issued presentation.');

echo "Website platform M5A scope: {$assertions} assertions passed.\n";
