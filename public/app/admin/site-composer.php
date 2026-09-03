<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/Csrf.php';
require_once __DIR__ . '/../../../private/classes/SiteCompositionEditor.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';
$context = admin_bootstrap();
try {
    SiteAuthorizationPolicy::requireInternalAdmin((int) $context['user']['id']);
} catch (SiteServiceException) {
    http_response_code(403);
    admin_access_denied($context);
    exit;
}
$actorId = (int) $context['user']['id'];
$revisionId = (int) ($_POST['revision_id'] ?? $_GET['revision_id'] ?? 0);
$error = '';
$workspace = null;
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::requireValid($_POST['csrf_token'] ?? null, SITE_PLATFORM_CSRF_SCOPE);
        SiteCompositionEditor::apply($actorId, $revisionId, $_POST);
        Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
        header('Location: site-composer.php?revision_id=' . $revisionId . '&saved=1', true, 303);
        exit;
    }
    $workspace = SiteCompositionEditor::workspace($actorId, $revisionId);
} catch (SiteServiceException $exception) {
    http_response_code($exception->classification() === 'stale_write' ? 409 : 400);
    $error = $exception->classification() . ': ' . $exception->getMessage();
} catch (CsrfException $exception) {
    http_response_code(403);
    $error = $exception->getMessage();
} catch (Throwable) {
    http_response_code(400);
    $error = 'The composition could not be loaded or saved.';
}
header('Cache-Control: private, no-store');
admin_begin('Composition Editor', 'sites', $context);
?>
<link rel="stylesheet" href="/assets/css/site-composer.css">
<section class="hero-panel">
    <p class="eyebrow">Site Platform</p><h1>Composition editor</h1>
    <p>Each save applies one change to the complete draft. Content marked pending review needs approved copy.</p>
</section>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
    <p>Your operation was not saved. <a href="site-composer.php?revision_id=<?= e($revisionId) ?>">Reload and review the current revision</a> before submitting another change.</p>
<?php elseif ($workspace !== null): ?>
    <?php if (isset($_GET['saved'])): ?><?= ui_alert('Composition saved.', 'success') ?><?php endif; ?>
    <?php require __DIR__ . '/../../../private/views/site-composer.php'; ?>
<?php endif; ?>
<?php admin_end(); ?>
