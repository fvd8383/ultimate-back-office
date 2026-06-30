<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$notes = [];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $notes = LeadHub::notesForBusiness((int) $context['business_id'], 100);
    } catch (Throwable $exception) {
        $error = 'Notes could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'notes', 'Notes');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif (count($notes) === 0): ?>
        <section class="empty-state">
            <h2>No notes yet</h2>
            <p>Website lead messages and CRM notes will appear here when records are available.</p>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <h2>Lead Notes</h2>
            <div class="activity-list">
                <?php foreach ($notes as $note): ?>
                    <?php $contactName = trim((string) $note['first_name'] . ' ' . (string) $note['last_name']); ?>
                    <article>
                        <strong><?= e($contactName !== '' ? $contactName : 'Note') ?></strong>
                        <p><?= nl2br(e($note['note_body'])) ?></p>
                        <span><?= e($note['created_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
