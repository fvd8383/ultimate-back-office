<?php
/** $customerReview is an allowlisted DTO or null; never render raw service errors. */
?>
<section class="business-switcher" aria-labelledby="website-revision-heading">
    <h2 id="website-revision-heading">Website Revision Review</h2>
    <?php if (is_string($reviewReceipt ?? null) && !$saved): ?><p role="status"><?= e($reviewReceipt) ?></p><?php endif; ?>
    <?php if ($customerReview === null): ?>
        <p>Website revision review is temporarily unavailable.</p>
    <?php else: ?>
        <p><?= e($customerReview['status_label']) ?></p>
        <?php if ($customerReview['revision_number'] !== null): ?>
            <p>Revision <?= e($customerReview['revision_number']) ?> · Sent for review <?= e($customerReview['requested_at']) ?></p>
            <?php if ($customerReview['decided_at'] !== null): ?><p>Decision recorded <?= e($customerReview['decided_at']) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($customerReview['preview_available']): ?>
            <p><a href="<?= e($customerReview['preview_href']) ?>">View private revision preview</a></p>
            <?php if (empty($customerReview['submission'])): ?><p>This review is read-only.</p><?php endif; ?>
        <?php endif; ?>
        <p><a href="<?= e($customerReview['profile_href']) ?>">Business Profile</a> remains the place to update your business facts.</p>
        <?php if (!empty($customerReview['decision_comment'])): ?><p><?= nl2br(e($customerReview['decision_comment'])) ?></p><?php endif; ?>
        <?php $customerSubmissions = $customerReview['feedback'] ?? []; require __DIR__ . '/site-customer-submissions.php'; ?>
        <?php if (!empty($customerReview['submission'])):
            $submission = $customerReview['submission'];
            $actions = ['feedback' => 'Send feedback', 'presentation_preference' => 'Request presentation change'];
            if (!empty($customerReview['image_targets'])) $actions['image_replacement_request'] = 'Request image replacement';
            $actions += ['request_changes' => 'Request changes', 'approve_revision' => 'Approve this revision'];
        ?>
            <p>Feedback and preferences are sent for consideration; they do not change your preview. Text is limited to 2,000 characters and 5,000 bytes.</p>
            <?php if (count($customerReview['feedback']) >= 20): ?><p>The feedback limit has been reached. You can still request changes or approve this revision.</p><?php endif; ?>
            <?php foreach ($actions as $action => $label):
                if (count($customerReview['feedback']) >= 20 && in_array($action, SiteCustomerReviewInput::KINDS, true)) continue;
                foreach ($action === 'presentation_preference' ? ['tone', 'emphasis'] : [null] as $preferenceTarget):
            ?>
                <form method="post" action="website-manager.php" class="form-stack">
                    <fieldset><legend><?= e($preferenceTarget === null ? $label : 'Request ' . $preferenceTarget . ' change') ?></legend>
                    <?= Csrf::input('customer-website-manager') ?>
                    <input type="hidden" name="action" value="<?= e($action) ?>">
                    <input type="hidden" name="review_handle" value="<?= e($submission['handle']) ?>">
                    <input type="hidden" name="submission_nonce" value="<?= e($submission['nonces'][$action]) ?>">
                    <?php if ($action === 'presentation_preference'): ?>
                        <input type="hidden" name="target" value="<?= e($preferenceTarget) ?>">
                        <label>Requested <?= e($preferenceTarget) ?><select name="value"><?php foreach ($preferenceTarget === 'tone' ? ['professional', 'friendly', 'concise'] : ['services', 'trust', 'contact'] as $preferenceValue): ?><option value="<?= e($preferenceValue) ?>"><?= e(ucfirst($preferenceValue)) ?></option><?php endforeach; ?></select></label>
                        <p>Sent for consideration; this does not change your preview.</p>
                    <?php elseif ($action === 'image_replacement_request'): ?>
                        <label>Image in this revision<select name="target"><?php foreach ($customerReview['image_targets'] as $target => $imageLabel): ?><option value="<?= e($target) ?>"><?= e($imageLabel) ?></option><?php endforeach; ?></select></label>
                    <?php elseif ($action === 'request_changes'): ?>
                        <p>Requesting changes ends this review. An administrator must prepare a successor revision.</p>
                    <?php elseif ($action === 'approve_revision'): ?>
                        <p>You are approving revision <?= e($customerReview['revision_number']) ?>. Approval does not publish your website. Internal review follows customer approval.</p>
                    <?php endif; ?>
                    <label><?= in_array($action, ['presentation_preference', 'approve_revision'], true) ? 'Comment (optional)' : 'Instructions or feedback (required)' ?><textarea name="text" maxlength="2000" rows="3" <?= in_array($action, ['presentation_preference', 'approve_revision'], true) ? '' : 'required' ?>></textarea></label>
                    <button type="submit"><?= e($label) ?></button>
                    </fieldset>
                </form>
            <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    <p>Approval does not publish your website. Existing website settings do not change the revision presented here.</p>
</section>
