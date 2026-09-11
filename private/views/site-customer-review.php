<?php
/** $customerReview is an allowlisted DTO or null; never render raw service errors. */
?>
<section class="business-switcher" aria-labelledby="website-revision-heading">
    <h2 id="website-revision-heading">Website Revision Review</h2>
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
            <p>Review is currently view-only. Decision controls are not yet available.</p>
        <?php endif; ?>
        <p><a href="<?= e($customerReview['profile_href']) ?>">Business Profile</a> remains the place to update your business facts.</p>
    <?php endif; ?>
    <p>Approval does not publish your website. Existing website settings do not change the revision presented here.</p>
</section>
