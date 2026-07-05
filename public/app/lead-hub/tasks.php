<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$tasks = [];
$contacts = [];
$error = '';
$notice = isset($_GET['saved']) ? 'Task saved.' : '';
$selectedContactId = (string) ($_POST['contact_id'] ?? $_GET['contact_id'] ?? '');

if ((int) $context['business_id'] > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($context['user'])) {
        try {
            $action = (string) ($_POST['action'] ?? 'create_task');
            if ($action === 'update_status') {
                LeadHub::updateTaskStatus(
                    (int) $context['business_id'],
                    (int) $context['user']['id'],
                    (int) ($_POST['task_id'] ?? 0),
                    (string) ($_POST['status'] ?? 'open')
                );
            } else {
                LeadHub::createManualTask((int) $context['business_id'], (int) $context['user']['id'], $_POST);
            }

            header('Location: tasks.php?business_id=' . urlencode((string) $context['business_id']) . '&saved=1');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'Task could not be saved.';
        }
    }

    try {
        $tasks = LeadHub::tasksForBusiness((int) $context['business_id'], 100);
        $contacts = LeadHub::contactsForBusiness((int) $context['business_id'], 200);
    } catch (Throwable $exception) {
        if ($error === '') {
            $error = 'Tasks could not be loaded.';
        }
    }
}

$ready = lead_hub_shell_begin($context, 'tasks', 'Tasks');
if ($ready): ?>
    <?php if ($notice !== ''): ?>
        <?= ui_alert($notice, 'success') ?>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>

    <section class="business-switcher">
        <h2>Add Task</h2>
        <form method="post" action="tasks.php" class="form-stack">
            <input type="hidden" name="business_id" value="<?= e($context['business_id']) ?>">
            <input type="hidden" name="action" value="create_task">

            <label>Link to contact or lead
                <select name="contact_id">
                    <option value="">General business task</option>
                    <?php foreach ($contacts as $contact): ?>
                        <?php
                            $contactName = trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name']);
                            $contactLabel = $contactName !== '' ? $contactName : 'Contact #' . (string) $contact['id'];
                        ?>
                        <option value="<?= e($contact['id']) ?>" <?= $selectedContactId === (string) $contact['id'] ? 'selected' : '' ?>>
                            <?= e($contactLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Task Title
                <input name="title" maxlength="255" required value="<?= e((string) ($_POST['title'] ?? '')) ?>">
            </label>

            <label>Description
                <textarea name="description" rows="4" maxlength="2000"><?= e((string) ($_POST['description'] ?? '')) ?></textarea>
            </label>

            <div class="form-grid">
                <label>Due Date
                    <input name="due_date" type="date" value="<?= e((string) ($_POST['due_date'] ?? '')) ?>">
                </label>
                <label>Priority
                    <select name="priority">
                        <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($_POST['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($_POST['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="button-row">
                <?= ui_button('Add Task', '', 'primary') ?>
            </div>
        </form>
    </section>

    <?php if (count($tasks) === 0): ?>
        <section class="empty-state">
            <h2>No tasks yet</h2>
            <p>Add a follow-up task for a lead, contact, or general business action.</p>
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
                            <p><a href="lead.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($task['contact_id']) ?>"><?= e($contactName) ?></a></p>
                        <?php endif; ?>
                        <?php if ($task['description']): ?>
                            <p><?= nl2br(e($task['description'])) ?></p>
                        <?php endif; ?>
                        <form method="post" action="tasks.php" class="button-row">
                            <input type="hidden" name="business_id" value="<?= e($context['business_id']) ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="task_id" value="<?= e($task['id']) ?>">
                            <label>Status
                                <select name="status">
                                    <?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= (string) $task['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?= ui_button('Update', '', 'secondary') ?>
                        </form>
                        <span><?= e($task['priority'] ?: 'normal') ?><?= $task['due_date'] ? ' · Due ' . e($task['due_date']) : '' ?> · <?= e($task['created_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
