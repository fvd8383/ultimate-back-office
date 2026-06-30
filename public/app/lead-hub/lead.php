<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$contactId = (int) ($_GET['contact_id'] ?? 0);
$detail = null;
$error = '';

if ((int) $context['business_id'] > 0 && $contactId > 0) {
    try {
        $detail = LeadHub::contactDetail((int) $context['business_id'], $contactId);
    } catch (Throwable $exception) {
        $error = 'Lead detail could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'leads', 'Lead Detail');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif ($detail === null): ?>
        <section class="empty-state">
            <h2>Lead not found</h2>
            <p>No matching lead exists for this business.</p>
            <?= ui_button('Back to Leads', 'leads.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
        </section>
    <?php else: ?>
        <?php
        $contact = $detail['contact'];
        $leadName = trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name']);
        ?>
        <section class="business-switcher">
            <div class="button-row secondary-link">
                <?= ui_button('Back to Leads', 'leads.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
            </div>
            <h2><?= e($leadName !== '' ? $leadName : 'Website lead') ?></h2>
            <div class="summary-list">
                <div><dt>Status</dt><dd><?= e($contact['status_name'] ?: 'New Lead') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($contact['phone'] ?: 'Not provided') ?></dd></div>
                <div><dt>Email</dt><dd><?= e($contact['email'] ?: 'Not provided') ?></dd></div>
                <div><dt>Source</dt><dd><?= e($contact['source_detail'] ?: '247SP website') ?></dd></div>
                <div><dt>Created</dt><dd><?= e($contact['created_at']) ?></dd></div>
                <div><dt>Updated</dt><dd><?= e($contact['updated_at']) ?></dd></div>
            </div>
        </section>

        <section class="business-switcher">
            <h2>Notes</h2>
            <?php if (count($detail['notes']) === 0): ?>
                <p class="muted">No notes for this lead yet.</p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($detail['notes'] as $note): ?>
                        <article>
                            <strong>Note</strong>
                            <p><?= nl2br(e($note['note_body'])) ?></p>
                            <span><?= e($note['created_at']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="business-switcher">
            <h2>Tasks</h2>
            <?php if (count($detail['tasks']) === 0): ?>
                <p class="muted">No tasks for this lead yet.</p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($detail['tasks'] as $task): ?>
                        <article>
                            <strong><?= e($task['title']) ?></strong>
                            <?php if ($task['description']): ?>
                                <p><?= nl2br(e($task['description'])) ?></p>
                            <?php endif; ?>
                            <span><?= e($task['status']) ?> · <?= e($task['created_at']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="business-switcher">
            <h2>Activity</h2>
            <?php if (count($detail['activity']) === 0): ?>
                <p class="muted">No activity for this lead yet.</p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($detail['activity'] as $activity): ?>
                        <article>
                            <strong><?= e($activity['subject'] ?: $activity['activity_type']) ?></strong>
                            <?php if ($activity['description']): ?>
                                <p><?= nl2br(e($activity['description'])) ?></p>
                            <?php endif; ?>
                            <span><?= e($activity['created_at']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
