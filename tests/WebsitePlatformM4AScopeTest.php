<?php

declare(strict_types=1);

error_reporting(E_ALL);

$assertions = 0;
function assertM4AScope(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$sitesRoute = file_get_contents($root . '/public/app/admin/sites.php');
$siteRoute = file_get_contents($root . '/public/app/admin/site.php');
$navigation = file_get_contents($root . '/public/app/admin/_common.php');
$policy = file_get_contents($root . '/private/classes/SiteAuthorizationPolicy.php');
$revisionManager = file_get_contents($root . '/private/classes/SiteRevisionManager.php');
foreach ([$sitesRoute, $siteRoute, $navigation, $policy, $revisionManager] as $source) {
    assertM4AScope(is_string($source), 'M4A scope source must be readable.');
}

assertM4AScope(is_file($root . '/public/app/admin/sites.php'), 'Generic site list route must exist.');
assertM4AScope(is_file($root . '/public/app/admin/site.php'), 'Generic site detail route must exist.');
assertM4AScope(str_contains($navigation, "['label' => 'Websites', 'href' => 'websites.php'"), 'Legacy Websites navigation must remain.');
assertM4AScope(str_contains($navigation, "['label' => 'Site Platform', 'href' => 'sites.php'"), 'Separate Site Platform navigation must exist.');

foreach (['sites.php' => $sitesRoute, 'site.php' => $siteRoute] as $name => $route) {
    assertM4AScope(str_contains($route, 'admin_bootstrap()'), "{$name} must require an authenticated admin session.");
    assertM4AScope(str_contains($route, "if (!\$context['is_admin'])"), "{$name} must reject non-admin routes.");
    assertM4AScope(str_contains($route, 'SiteAuthorizationPolicy::requireInternalAdmin'), "{$name} must use Site authorization contracts.");
    assertM4AScope(str_contains($route, 'Csrf::requireValid('), "{$name} must validate CSRF on POST.");
    assertM4AScope(str_contains($route, "'admin-site-platform'"), "{$name} must use the dedicated Site Platform CSRF scope.");
    assertM4AScope(str_contains($route, 'Csrf::rotate('), "{$name} must rotate CSRF after success.");
    assertM4AScope(preg_match('/header\s*\([\s\S]*?true\s*,\s*303\s*\)/', $route) === 1, "{$name} must use a 303 PRG redirect after success.");
    assertM4AScope(!preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+(?:sites|site_revisions|site_approvals)|DELETE\s+FROM)\b/i', $route), "{$name} must contain no direct lifecycle SQL.");
    assertM4AScope(!preg_match('/SiteGenerator|DomainManager|LeadHub|Stripe|Twilio|Retell|Vendasta|Namecheap|DigitalOcean/i', $route), "{$name} must not invoke legacy runtime, provider, domain, or lead services.");
}

assertM4AScope(str_contains($sitesRoute, 'SiteManager::createSite('), 'Site creation route must use SiteManager.');
assertM4AScope(str_contains($sitesRoute, 'SiteAdminWorkspace::eligible247spBusinesses'), 'Site list route must load server-eligible businesses.');
assertM4AScope(!str_contains($sitesRoute, 'SiteGenerator::'), 'Generic site creation must not invoke the legacy generator.');
assertM4AScope(str_contains($siteRoute, 'SiteGenerationBriefManager::createBrief'), 'Detail route must use the brief service.');
assertM4AScope(str_contains($siteRoute, 'SiteRevisionManager::createAuthoredDraftRevision'), 'Detail route must use server-owned authored revision creation.');
foreach (['facts_snapshot_json', 'source_references_json', 'snapshot_hash', 'revision_number', 'lifecycle_status', 'restored_from_revision_id'] as $serverOwned) {
    assertM4AScope(!preg_match('/name=["\']' . preg_quote($serverOwned, '/') . '["\']/', $siteRoute), "Browser forms must not control {$serverOwned}.");
}
assertM4AScope(!str_contains($siteRoute, 'raw JSON') && !preg_match('/<textarea[^>]+json/i', $siteRoute), 'Generation briefs must not use a raw JSON editor.');
assertM4AScope(str_contains($siteRoute, 'site-composer.php?revision_id='), 'M4B replaces the M4A placeholder with a revision-scoped composer link.');
assertM4AScope(!preg_match('/markReadyForReview|requestApproval|decideApproval|requireCustomerApproval\(/', $siteRoute), 'M4A routes must not submit review or make approval decisions.');
assertM4AScope(str_contains($policy, 'Internal administrators cannot act as customer approvers.'), 'Customer impersonation denial must remain unchanged.');

$immutableHashes = [
    'database/migrations/023_website_platform_foundation.sql' => 'f0912bafc947eab8cc5b2dd5d534466d6b3675f991cac2f6849b1f84819db302',
    'database/migrations/024_component_registry_versioning.sql' => '95093d67a3319c561f28588b563d7f23a126b44a501b9cb80f9d791b988e3950',
    'public/app/admin/websites.php' => 'e2160f0340c30b5bfae6691c9df922c284a2ee54410da1da7547cccfebeb53c9',
    'public/app/admin/website.php' => '59694e3f9b905b9a39920c0e2792d64177aedccf8d689325a629106617d512ca',
    'public/app/admin/website-editor.php' => '86741713ceb1c036109e0b1989cdefb0ec7c3aa821c5a1f124649cbad0047409',
    'public/app/247sp/website-manager.php' => '8b397373dec38c0e3d5b9d120a370e727ab45dd8fe5b2d943234dbb3d26b9edf',
];
foreach ($immutableHashes as $path => $expectedHash) {
    $contents = file_get_contents($root . '/' . $path);
    $canonical = is_string($contents) ? str_replace(["\r\n", "\r"], "\n", $contents) : '';
    assertM4AScope(hash('sha256', $canonical) === $expectedHash, "{$path} must remain unchanged.");
}
assertM4AScope(glob($root . '/database/migrations/025*') === [], 'M4A must add no migration 025 or later.');
assertM4AScope(!str_contains($siteRoute, 'SiteCompositionRenderer::render'), 'M4A detail delegates preview to the separate M4B route.');
assertM4AScope(!is_file($root . '/public/app/admin/site-editor.php'), 'M4A must add no generic composition editor route.');

echo "Website platform M4A scope: {$assertions} assertions passed.\n";
