<?php
$site = $workspace['site'];
$revision = $workspace['revision'];
$caps = $workspace['capabilities'];
$postForm = static function (string $action, string $label, array $hidden = [], bool $comment = false) use ($revision): void {
?>
<form method="post" action="site-review.php" class="form-stack">
    <?= Csrf::input(SITE_PLATFORM_CSRF_SCOPE) ?>
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <input type="hidden" name="revision_id" value="<?= e($revision['id']) ?>">
    <?php foreach ($hidden as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?>
    <?php if ($comment): ?><label>Comment (optional)<textarea name="comment" maxlength="5000" rows="3"></textarea></label><?php endif; ?>
    <?= ui_button($label) ?>
</form>
<?php }; ?>
<section class="hero-panel">
    <p class="eyebrow">Site Platform · Review Workflow</p>
    <h1>Revision <?= e($revision['revision_number']) ?></h1>
    <p><?= e($workspace['next_step']) ?></p>
    <div class="button-row"><a href="<?= e($workspace['links']['site']) ?>">Site detail</a>
        <?php if ($workspace['composition']['composition_state'] === 'composed'): ?><a href="<?= e($workspace['links']['preview']) ?>">Preview</a><?php endif; ?>
        <?php if ($caps['can_edit_composition']): ?><a href="<?= e($workspace['links']['composer']) ?>">Edit Composition</a><?php endif; ?>
    </div>
</section>
<?= ui_alert('Not publicly deployed. Approval does not publish this site. The legacy 247SP runtime remains authoritative.', 'warning') ?>
<section class="business-switcher"><h2>Authoritative status</h2><div class="summary-list">
    <div><dt>Site</dt><dd>#<?= e($site['id']) ?> · <?= e(AdminPortal::statusLabel($site['purpose'])) ?></dd></div>
    <div><dt>Site lifecycle</dt><dd><?= e(AdminPortal::statusLabel($site['lifecycle_status'])) ?></dd></div>
    <div><dt>Revision lifecycle</dt><dd><?= e(AdminPortal::statusLabel($revision['lifecycle_status'])) ?></dd></div>
    <div><dt>Materiality</dt><dd><?= e(AdminPortal::statusLabel($revision['materiality'])) ?></dd></div>
    <div><dt>Composition</dt><dd><?= e(AdminPortal::statusLabel($workspace['composition']['composition_state'])) ?></dd></div>
    <div><dt>Snapshot</dt><dd><?= e(substr((string) $revision['snapshot_hash'], 0, 12)) ?>…</dd></div>
</div></section>

<?php if ($caps['can_classify_materiality']): ?>
<section class="business-switcher"><h2>Classify materiality</h2>
    <p><strong>Material:</strong> customer-visible or new-launch changes requiring customer review before internal approval.</p>
    <p><strong>Non-material:</strong> internal or technical changes that may bypass a new customer review only when an effective customer-approved baseline exists.</p>
    <?php if (!$caps['has_effective_customer_baseline']): ?><?= ui_alert('No effective customer-approved baseline was found. A non-material revision cannot reach internal approval under the current service contract.', 'warning') ?><?php endif; ?>
    <form method="post" action="site-review.php" class="form-stack">
        <?= Csrf::input(SITE_PLATFORM_CSRF_SCOPE) ?><input type="hidden" name="action" value="classify_materiality"><input type="hidden" name="revision_id" value="<?= e($revision['id']) ?>">
        <label>Classification<select name="materiality" required><option value="material">Material</option><option value="non_material">Non-material</option></select></label>
        <label>Reason<textarea name="reason" maxlength="500" rows="3" required></textarea></label><?= ui_button('Classify Materiality') ?>
    </form>
</section>
<?php endif; ?>

<?php if ($caps['can_submit_for_review']): ?><section class="business-switcher"><h2>Review gate</h2><p>The stored composition, structure, assets, and snapshot integrity will be validated.</p><?php $postForm('submit_for_review', 'Submit for Review'); ?></section><?php endif; ?>
<?php if ($caps['can_request_customer_review'] && $workspace['open_customer_request'] === null): ?><section class="business-switcher"><h2>Customer review</h2><p>This records a customer review request. Only the customer-facing M5 workflow can decide it.</p><?php $postForm('request_customer_review', 'Request Customer Review', [], true); ?></section><?php endif; ?>
<?php if ($workspace['open_customer_request'] !== null): ?><?= ui_alert('Customer review requested / pending customer action. Internal administrators have no customer decision controls.', 'info') ?><?php endif; ?>
<?php if ($caps['can_request_internal_review'] && $workspace['open_internal_request'] === null): ?><section class="business-switcher"><h2>Internal review</h2><?php $postForm('request_internal_review', 'Request Internal Review', [], true); ?></section><?php endif; ?>
<?php if ($workspace['open_internal_request'] !== null && $caps['can_decide_internal_approval']): ?><section class="business-switcher"><h2>Internal approval decision</h2><p>Decide requested internal approval #<?= e($workspace['open_internal_request']['id']) ?>.</p>
    <?php $postForm('decide_internal_approval', 'Approve', ['approval_id' => $workspace['open_internal_request']['id'], 'decision' => 'approved'], true); ?>
    <?php $postForm('decide_internal_approval', 'Reject', ['approval_id' => $workspace['open_internal_request']['id'], 'decision' => 'rejected'], true); ?>
</section><?php endif; ?>

<section class="business-switcher"><h2>Review and approval timeline</h2><div class="activity-list">
<?php foreach ($workspace['approvals'] as $approval): ?><article><strong><?= e(AdminPortal::statusLabel($approval['approval_type'])) ?> · <?= e(AdminPortal::statusLabel($approval['state'])) ?></strong>
<p>Approval #<?= e($approval['id']) ?></p><span>Requested <?= e($approval['requested_at']) ?><?= $approval['decided_at'] ? ' · Decided ' . e($approval['decided_at']) : '' ?><?= $approval['comments'] ? ' · ' . e($approval['comments']) : '' ?></span></article><?php endforeach; ?>
<?php if ($workspace['approvals'] === []): ?><p class="muted">No approval requests or decisions exist.</p><?php endif; ?></div></section>
<?php if ($revision['lifecycle_status'] === 'changes_requested'): ?><?= ui_alert('Changes requested. This immutable revision is not edited in place. Return to site detail to create a successor authored revision.', 'warning') ?><?php endif; ?>
