<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$loadError = '';
$websites = [];

try {
    $websites = AdminPortal::websites();
} catch (Throwable $exception) {
    $loadError = 'Websites could not be loaded.';
}

admin_begin('Websites', 'websites', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Websites</p>
    <h1>Website management</h1>
    <p class="muted">View generated website records, templates, statuses, and generation dates.</p>
</section>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="business-switcher">
        <div class="admin-table admin-table--websites">
            <div class="admin-table__head">
                <span>Website Name</span><span>Business</span><span>Template</span><span>Website Status</span><span>Generated Date</span><span></span>
            </div>
            <?php foreach ($websites as $website): ?>
                <div class="admin-table__row">
                    <span><?= e($website['business_name']) ?></span>
                    <span><a href="business.php?business_id=<?= e($website['business_id']) ?>"><?= e($website['business_name']) ?></a></span>
                    <span><?= e($website['template_name']) ?></span>
                    <span><?= ui_badge(AdminPortal::statusLabel($website['status']), 'status') ?></span>
                    <span><?= e($website['generated_at']) ?></span>
                    <span>
                        <a href="website.php?website_id=<?= e($website['id']) ?>">Open</a>
                        ·
                        <a href="website-editor.php?business_id=<?= e($website['business_id']) ?>">Edit Site</a>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (count($websites) === 0): ?>
                <p class="muted">No websites have been generated yet.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
