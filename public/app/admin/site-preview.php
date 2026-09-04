<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/SiteAdminPreview.php';

$context = admin_bootstrap();
try {
    SiteAuthorizationPolicy::requireInternalAdmin((int) $context['user']['id']);
} catch (SiteServiceException) {
    http_response_code(403);
    admin_access_denied($context);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$revisionId = (int) ($_GET['revision_id'] ?? 0);
try {
    $html = SiteAdminPreview::render((int) $context['user']['id'], $revisionId);
} catch (Throwable) {
    http_response_code(409);
    $html = '<p>This composition could not be validated. No preview is available.</p>';
}
admin_begin('Composition Preview', 'sites', $context);
?>
<section class="hero-panel"><h1>Internal composition preview</h1>
    <p>Read-only component preview. Lead forms and contact actions are inactive. Media without a resolved asset URL is omitted.</p>
    <a href="sites.php">Site Platform</a>
</section>
<!-- Sandboxing keeps repository navigation and relative links inside this inert preview. -->
<iframe title="Validated composition preview" sandbox="" style="width:100%;min-height:75vh;border:1px solid #ccd3df"
    srcdoc="<?= e('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font:16px system-ui;margin:24px;color:#172b4d}section,header,footer{padding:20px;border-bottom:1px solid #dde3ed}img{max-width:100%}nav{display:flex;gap:16px;flex-wrap:wrap}a{pointer-events:none}input{display:block}p{overflow-wrap:anywhere}main{margin-bottom:32px}</style></head><body>' . $html . '</body></html>') ?>"></iframe>
<?php admin_end(); ?>
