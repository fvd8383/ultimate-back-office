<section class="hero-panel">
    <h1>Private revision preview</h1>
    <p><?= e(SiteCustomerPreview::NOTICE) ?></p>
    <p><?= e(SiteCustomerPreview::MEDIA_NOTICE) ?></p>
    <?php if ($customerPreview !== null): ?>
        <p><?= e($customerPreview['review']['business_label']) ?> · Revision <?= e($customerPreview['review']['revision_number']) ?></p>
        <p><?= e($customerPreview['review']['status_label']) ?></p>
        <p><a href="<?= e($customerPreview['review']['manager_href']) ?>">Back to Website Manager</a></p>
    <?php else: ?>
        <p>The website review is unavailable. Return to Website Manager to check the current review.</p>
        <p><a href="website-manager.php">Website Manager</a></p>
    <?php endif; ?>
</section>
<?php if ($customerPreview !== null): ?>
    <iframe title="Private preview of revision <?= e($customerPreview['review']['revision_number']) ?>"
        sandbox="" style="display:block;width:100%;max-width:100%;min-height:75vh;border:1px solid #ccd3df"
        srcdoc="<?= e($customerPreview['document']) ?>"></iframe>
<?php endif; ?>
