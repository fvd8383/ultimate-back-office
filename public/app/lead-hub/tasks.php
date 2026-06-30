<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$tasks = [];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $tasks = LeadHub::tasksForBusiness((int) $context['business_id'], 100);
    } catch (Throwable $exception) {
        $error = 'Tasks could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'tasks', 'Tasks');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif (count($tasks) === 0): ?>
        <section class="empty-state">
            <h2>No tasks yet</h2>
            <p>Website lead follow-up tasks will appear here after visitors send a request.</p>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <h2>Follow-Up Tasks</h2>
            <div class="activity-list">
                <?php foreach ($tasks as $task): ?>
                    <?php $contactName = trim((string) $task['first_name'] . ' ' . (string) $task['last_name']); ?>
                    <article>
                        <strong><?= e($task['title']) ?></strong>
                        <?php if ($contactName !== ''): ?>
                            <p><a href="contact.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($task['contact_id']) ?>"><?= e($contactName) ?></a></p>
                        <?php endif; ?>
                        <?php if ($task['description']): ?>
                            <p><?= nl2br(e($task['description'])) ?></p>
                        <?php endif; ?>
                        <span><?= e($task['status']) ?> · <?= e($task['priority'] ?: 'normal') ?> · <?= e($task['created_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
