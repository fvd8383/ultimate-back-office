<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/Csrf.php';
require_once __DIR__ . '/../../../private/classes/SiteReviewAdminWorkflow.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';
$context = admin_bootstrap();
try {
    SiteAuthorizationPolicy::requireInternalAdmin((int) $context['user']['id']);
} catch (SiteServiceException) {
    http_response_code(403); admin_access_denied($context); exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
$actorId = (int) $context['user']['id'];
$revisionId = (int) ($_POST['revision_id'] ?? $_GET['revision_id'] ?? 0);
$error = '';
$workspace = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::requireValid($_POST['csrf_token'] ?? null, SITE_PLATFORM_CSRF_SCOPE);
        SiteReviewAdminWorkflow::apply($actorId, $revisionId, (string) ($_POST['action'] ?? ''), $_POST);
        Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
        header('Location: site-review.php?revision_id=' . urlencode((string) $revisionId) . '&updated=1', true, 303);
        exit;
    }
    $workspace = SiteReviewAdminWorkflow::workspace($actorId, $revisionId);
} catch (SiteServiceException | CsrfException | InvalidArgumentException $exception) {
    http_response_code($exception instanceof CsrfException ? 403 : 400);
    $error = $exception->getMessage();
    try { $workspace = SiteReviewAdminWorkflow::workspace($actorId, $revisionId); } catch (Throwable) { $workspace = null; }
} catch (Throwable) {
    http_response_code(400); $error = 'The review workflow could not be loaded or updated.';
}
header('Cache-Control: private, no-store');
admin_begin('Review Workflow', 'sites', $context);
if (isset($_GET['updated'])) echo ui_alert('Review workflow updated.', 'success');
if ($error !== '') echo ui_alert($error, 'error');
if ($workspace !== null) require __DIR__ . '/../../../private/views/site-review.php';
admin_end();
