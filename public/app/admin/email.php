<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/EmailProvisioningFoundation.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$notice = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        EmailProvisioningFoundation::updateMailboxRequest(
            (int) ($_POST['request_id'] ?? 0),
            (int) $context['user']['id'],
            (string) ($_POST['status'] ?? ''),
            (string) ($_POST['mailbox_type'] ?? ''),
            (string) ($_POST['notes'] ?? '')
        );
        $notice = 'Mailbox request updated.';
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Mailbox request could not be updated.';
}

$loadError = '';
$metrics = [];
$requests = [];
$activity = [];

try {
    $metrics = EmailProvisioningFoundation::adminMetrics();
    $requests = EmailProvisioningFoundation::adminMailboxRequests();
    $activity = EmailProvisioningFoundation::adminMailboxActivity();
} catch (Throwable $exception) {
    $loadError = 'Mailbox data could not be loaded. Run the Sprint 8 migration and check the database setup.';
}

function admin_email_status_option_label(string $status): string
{
    $labels = [
        'requested' => 'Requested',
        'pending_setup' => 'Approve / Pending Setup',
        'active' => 'Activate Mailbox',
        'suspended' => 'Suspend Mailbox',
        'cancelled' => 'Cancel Mailbox',
    ];

    return $labels[$status] ?? EmailProvisioningFoundation::statusLabel($status);
}

admin_begin('Email', 'email', $context);
?>
<section class="hero-panel">
    <p class="eyebrow">Email</p>
    <h1>Email provisioning foundation</h1>
    <p class="muted">Track 24/7 Sales Partner mailbox requests, assignment status, mailbox type, and lifecycle notes. No Microsoft 365, Google Workspace, Roundcube, SMTP, IMAP, DNS mail record, password, payment, or automatic mailbox provisioning runs here.</p>
</section>

<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="metrics-grid admin-metrics" aria-label="Email metrics">
        <article><span>Total Requested</span><strong><?= e($metrics['requested_count']) ?></strong></article>
        <article><span>Total Pending Setup</span><strong><?= e($metrics['pending_setup_count']) ?></strong></article>
        <article><span>Total Active</span><strong><?= e($metrics['active_count']) ?></strong></article>
        <article><span>Total Suspended</span><strong><?= e($metrics['suspended_count']) ?></strong></article>
        <article><span>Total Cancelled</span><strong><?= e($metrics['cancelled_count']) ?></strong></article>
    </section>

    <section class="business-switcher">
        <div class="admin-table admin-table--email">
            <div class="admin-table__head">
                <span>Business</span><span>Email Address</span><span>Status</span><span>Mailbox Type</span><span>Request Date</span><span>Controls</span>
            </div>
            <?php foreach ($requests as $request): ?>
                <div class="admin-table__row">
                    <span><a href="business.php?business_id=<?= e($request['business_id']) ?>"><?= e($request['business_name']) ?></a></span>
                    <span>
                        <strong><?= e($request['requested_email']) ?></strong>
                        <small><?= e($request['display_name'] ?: 'No display name') ?></small>
                    </span>
                    <span><?= ui_badge(EmailProvisioningFoundation::statusLabel($request['status']), in_array((string) $request['status'], ['suspended', 'cancelled'], true) ? 'role' : 'status') ?></span>
                    <span><?= e(EmailProvisioningFoundation::statusLabel($request['mailbox_type'] ?: 'Not assigned')) ?></span>
                    <span><?= e($request['created_at']) ?></span>
                    <span>
                        <form method="post" action="email.php" class="email-status-form">
                            <input type="hidden" name="request_id" value="<?= e($request['id']) ?>">
                            <label>
                                <span>Status</span>
                                <select name="status" aria-label="Mailbox status for <?= e($request['requested_email']) ?>">
                                    <?php foreach (EmailProvisioningFoundation::STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $request['status'] === $status ? 'selected' : '' ?>>
                                            <?= e(admin_email_status_option_label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Mailbox Type</span>
                                <select name="mailbox_type" aria-label="Mailbox type for <?= e($request['requested_email']) ?>">
                                    <?php foreach (EmailProvisioningFoundation::MAILBOX_TYPES as $type): ?>
                                        <?php $selectedType = (string) ($request['mailbox_type'] ?: 'included'); ?>
                                        <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                                            <?= e(EmailProvisioningFoundation::statusLabel($type)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Activity Notes</span>
                                <input type="text" name="notes" maxlength="500" placeholder="Optional lifecycle note">
                            </label>
                            <?= ui_button('Save', '', 'secondary', ['class' => 'ubo-dashboard-action']) ?>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (count($requests) === 0): ?>
                <p class="muted">No mailbox requests have been created yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Recent Mailbox Activity</h2>
        <?php if (count($activity) === 0): ?>
            <p class="muted">No mailbox activity has been logged yet.</p>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($activity as $entry): ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e(EmailProvisioningFoundation::statusLabel($entry['activity_type'])) ?></h3>
                            <p class="muted"><?= e($entry['business_name'] ?: 'Business unavailable') ?> · <?= e($entry['email_address'] ?: $entry['requested_email']) ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Notes</dt><dd><?= e($entry['notes'] ?: 'No notes') ?></dd></div>
                            <div><dt>Logged At</dt><dd><?= e($entry['created_at']) ?></dd></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
