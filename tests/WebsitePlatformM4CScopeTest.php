<?php

declare(strict_types=1);

$root = dirname(__DIR__); $assertions = 0;
function checkM4CScope(bool $condition, string $message): void { global $assertions; $assertions++; if (!$condition) throw new RuntimeException($message); }
$route = file_get_contents($root . '/public/app/admin/site-review.php');
$service = file_get_contents($root . '/private/classes/SiteReviewAdminWorkflow.php');
$approval = file_get_contents($root . '/private/classes/SiteApprovalManager.php');
$view = file_get_contents($root . '/private/views/site-review.php');
$detail = file_get_contents($root . '/public/app/admin/site.php');
checkM4CScope(is_file($root . '/public/app/admin/site-review.php'), 'M4C route exists in the app admin root.');
foreach (['admin_bootstrap()', 'SiteAuthorizationPolicy::requireInternalAdmin', 'Csrf::requireValid(', "'admin-site-platform'", 'Csrf::rotate(', 'true, 303'] as $token) checkM4CScope(str_contains($route, $token), "Route contract: $token");
checkM4CScope(strpos($route, 'Csrf::requireValid(') < strpos($route, 'SiteReviewAdminWorkflow::apply(') && strpos($route, 'Csrf::rotate(') > strpos($route, 'SiteReviewAdminWorkflow::apply('), 'CSRF validates before mutation and rotates after success.');
checkM4CScope(!preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $route), 'Route contains no lifecycle or approval SQL.');
foreach (['SiteRevisionManager::classifyMateriality(', 'SiteRevisionManager::markReadyForReview(', "SiteApprovalManager::requestApproval(\n                \$actingUserId, \$revisionId, 'customer'", "SiteApprovalManager::requestApproval(\n                \$actingUserId, \$revisionId, 'internal'", 'SiteApprovalManager::decideApproval('] as $delegation) checkM4CScope(str_contains($service, $delegation), "Workflow delegation: $delegation");
checkM4CScope(substr_count($service, 'SiteApprovalManager::decideApproval(') === 1 && !str_contains($service, 'requireCustomerApproval'), 'Admin workflow can decide only its re-resolved internal request.');
checkM4CScope(str_contains($service, "\$match['approval_type'] !== 'internal'") && str_contains($service, "\$match['state'] !== 'requested'"), 'Internal decision re-resolves type and state server-side.');
checkM4CScope(str_contains($approval, 'effectiveCustomerApproval($connection, $revision)') && str_contains($approval, 'assertInternalApprovalEligible($connection, $revision)'), 'Advisory flags reuse M2 approval helpers.');
checkM4CScope(!preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+(?:sites|site_revisions|site_approvals)|DELETE\s+FROM)\b/i', $service), 'Workflow service adds no lifecycle engine or direct writes.');
checkM4CScope(str_contains($detail, 'site-review.php?revision_id=') && str_contains($detail, 'Customer approval decisions remain owned by M5'), 'Site detail integrates workflow and retains M5 boundary.');
foreach ([$route, $service, $view] as $source) checkM4CScope(!preg_match('/(?:Stripe|Twilio|Retell|Vendasta|Namecheap|DigitalOcean|SiteGenerator|WebsiteManager|DomainManager|LeadHub)::|curl_|file_put_contents\s*\(/i', $source), 'M4C has no provider, legacy runtime, publication, or filesystem work.');
foreach (['production', 'conversion', 'revoke'] as $future) checkM4CScope(!preg_match('/name=["\'](?:action|approval_type)["\'][^>]*value=["\']' . $future . '/i', $view), "No $future UI.");
checkM4CScope(!str_contains($view, 'Approve as Customer') && !str_contains($view, 'Reject as Customer'), 'No customer decision UI.');
foreach (['database/migrations/023_website_platform_foundation.sql', 'database/migrations/024_component_registry_versioning.sql', 'public/app/247sp/website-manager.php', 'private/classes/SiteGenerator.php', 'private/classes/WebsiteManager.php', 'public/marketing'] as $path) {
    exec('git -C ' . escapeshellarg($root) . ' diff --quiet c77f7cbb3d6871c3cb6df1b85fae92beeb4948c3 -- ' . escapeshellarg($path), $out, $status); checkM4CScope($status === 0, "Protected path unchanged: $path");
}
checkM4CScope(glob($root . '/database/migrations/02[5-9]_*.sql') === [], 'No migration 025+ was added.');
echo "Website platform M4C scope: {$assertions} assertions passed.\n";
