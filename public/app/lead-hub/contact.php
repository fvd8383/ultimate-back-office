<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$businessId = (int) $context['business_id'];
$userId = (int) ($context['user']['id'] ?? 0);
$contactId = (int) ($_POST['contact_id'] ?? $_GET['contact_id'] ?? 0);
$detail = null;
$statuses = [];
$sourceOptions = LeadHub::sourceOptions();
$notice = '';
$error = '';

if (($_GET['saved'] ?? '') === '1') {
    $notice = 'Contact saved.';
}
if (($_GET['note_added'] ?? '') === '1') {
    $notice = 'Note added.';
}
if (($_GET['task_created'] ?? '') === '1') {
    $notice = 'Task created.';
}

try {
    if ($businessId > 0) {
        $statuses = LeadHub::statusOptions($businessId);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $businessId > 0 && $userId > 0) {
        $action = (string) ($_POST['action'] ?? 'save_contact');

        if ($action === 'save_contact') {
            $savedContactId = LeadHub::saveContact($businessId, $userId, $_POST, $contactId > 0 ? $contactId : null);
            header('Location: contact.php?business_id=' . urlencode((string) $businessId) . '&contact_id=' . urlencode((string) $savedContactId) . '&saved=1');
            exit;
        }

        if ($action === 'add_note' && $contactId > 0) {
            LeadHub::addNote($businessId, $userId, $contactId, (string) ($_POST['note_body'] ?? ''));
            header('Location: contact.php?business_id=' . urlencode((string) $businessId) . '&contact_id=' . urlencode((string) $contactId) . '&note_added=1');
            exit;
        }

        if ($action === 'create_task' && $contactId > 0) {
            LeadHub::createTask($businessId, $userId, $contactId, (string) ($_POST['task_title'] ?? ''), (string) ($_POST['task_description'] ?? ''));
            header('Location: contact.php?business_id=' . urlencode((string) $businessId) . '&contact_id=' . urlencode((string) $contactId) . '&task_created=1');
            exit;
        }
    }

    if ($businessId > 0 && $contactId > 0) {
        $detail = LeadHub::contactDetail($businessId, $contactId);
        if ($detail !== null && isset($detail['statuses'])) {
            $statuses = $detail['statuses'];
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Contact could not be saved or loaded.';
}

if ($businessId > 0 && count($statuses) === 0) {
    try {
        $statuses = LeadHub::statusOptions($businessId);
    } catch (Throwable $exception) {
        $statuses = [];
    }
}

if ($businessId > 0 && $contactId > 0 && $detail === null) {
    try {
        $detail = LeadHub::contactDetail($businessId, $contactId);
        if ($detail !== null && isset($detail['statuses'])) {
            $statuses = $detail['statuses'];
        }
    } catch (Throwable $exception) {
        $detail = null;
    }
}

$contact = $detail['contact'] ?? [
    'id' => $contactId,
    'first_name' => (string) ($_POST['first_name'] ?? ''),
    'last_name' => (string) ($_POST['last_name'] ?? ''),
    'company_name' => (string) ($_POST['company_name'] ?? ''),
    'email' => (string) ($_POST['email'] ?? ''),
    'phone' => (string) ($_POST['phone'] ?? ''),
    'contact_type' => (string) ($_POST['contact_type'] ?? 'lead'),
    'status_id' => (int) ($_POST['status_id'] ?? 0),
    'source_module_key' => (string) ($_POST['source_module_key'] ?? 'manual'),
    'source_detail' => (string) ($_POST['source_detail'] ?? ''),
    'created_at' => '',
    'updated_at' => '',
];

$isExisting = $detail !== null;
$title = $isExisting ? 'Contact Detail' : 'Add Contact';
$ready = lead_hub_shell_begin($context, 'contacts', $title);
if ($ready): ?>
    <?php if ($notice !== ''): ?>
        <?= ui_alert($notice, 'success') ?>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>

    <?php if ($contactId > 0 && !$isExisting && $error === ''): ?>
        <section class="empty-state">
            <h2>Contact not found</h2>
            <p>No matching contact exists for this business.</p>
            <?= ui_button('Back to Contacts', 'contacts.php?business_id=' . urlencode((string) $businessId), 'secondary') ?>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <div class="button-row secondary-link">
                <?= ui_button('Back to Contacts', 'contacts.php?business_id=' . urlencode((string) $businessId), 'secondary') ?>
                <?= ui_button('Add Contact', 'contact.php?business_id=' . urlencode((string) $businessId), 'secondary') ?>
            </div>
            <h2><?= $isExisting ? e(trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name'])) : 'New Contact' ?></h2>
            <form method="post" action="contact.php" class="form-stack">
                <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                <input type="hidden" name="contact_id" value="<?= e((int) ($contact['id'] ?? 0)) ?>">
                <input type="hidden" name="action" value="save_contact">
                <div class="form-grid">
                    <label>First Name
                        <input name="first_name" maxlength="100" required value="<?= e($contact['first_name'] ?? '') ?>">
                    </label>
                    <label>Last Name
                        <input name="last_name" maxlength="100" value="<?= e($contact['last_name'] ?? '') ?>">
                    </label>
                </div>
                <label>Company
                    <input name="company_name" maxlength="255" value="<?= e($contact['company_name'] ?? '') ?>">
                </label>
                <div class="form-grid">
                    <label>Phone
                        <input name="phone" maxlength="50" value="<?= e($contact['phone'] ?? '') ?>">
                    </label>
                    <label>Email
                        <input type="email" name="email" maxlength="255" value="<?= e($contact['email'] ?? '') ?>">
                    </label>
                </div>
                <div class="form-grid">
                    <label>Type
                        <select name="contact_type">
                            <option value="lead" <?= ($contact['contact_type'] ?? '') === 'lead' ? 'selected' : '' ?>>Lead</option>
                            <option value="contact" <?= ($contact['contact_type'] ?? '') === 'contact' ? 'selected' : '' ?>>Contact</option>
                        </select>
                    </label>
                    <label>Status
                        <select name="status_id">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status['id']) ?>" <?= (int) ($contact['status_id'] ?? 0) === (int) $status['id'] ? 'selected' : '' ?>><?= e($status['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-grid">
                    <label>Source
                        <select name="source_module_key">
                            <?php foreach ($sourceOptions as $sourceKey => $sourceLabel): ?>
                                <option value="<?= e($sourceKey) ?>" <?= ($contact['source_module_key'] ?? 'manual') === $sourceKey ? 'selected' : '' ?>><?= e($sourceLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Source Detail / Service Interest
                        <input name="source_detail" maxlength="255" value="<?= e($contact['source_detail'] ?? '') ?>">
                    </label>
                </div>
                <?php if (!$isExisting): ?>
                    <label>Initial Note
                        <textarea name="note_body" maxlength="2000" rows="4"><?= e($_POST['note_body'] ?? '') ?></textarea>
                    </label>
                    <div class="form-grid">
                        <label>Follow-Up Task
                            <input name="task_title" maxlength="255" value="<?= e($_POST['task_title'] ?? '') ?>">
                        </label>
                        <label>Task Details
                            <textarea name="task_description" maxlength="2000" rows="3"><?= e($_POST['task_description'] ?? '') ?></textarea>
                        </label>
                    </div>
                <?php endif; ?>
                <?= ui_button($isExisting ? 'Save Contact' : 'Create Contact', '', 'primary') ?>
            </form>
        </section>

        <?php if ($isExisting): ?>
            <section class="business-switcher">
                <h2>Add Note</h2>
                <form method="post" action="contact.php" class="form-stack">
                    <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                    <input type="hidden" name="contact_id" value="<?= e($contactId) ?>">
                    <input type="hidden" name="action" value="add_note">
                    <label>Note
                        <textarea name="note_body" maxlength="2000" rows="4" required></textarea>
                    </label>
                    <?= ui_button('Add Note', '', 'primary') ?>
                </form>
            </section>

            <section class="business-switcher">
                <h2>Create Task</h2>
                <form method="post" action="contact.php" class="form-stack">
                    <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                    <input type="hidden" name="contact_id" value="<?= e($contactId) ?>">
                    <input type="hidden" name="action" value="create_task">
                    <label>Task Title
                        <input name="task_title" maxlength="255" required>
                    </label>
                    <label>Task Details
                        <textarea name="task_description" maxlength="2000" rows="3"></textarea>
                    </label>
                    <?= ui_button('Create Task', '', 'primary') ?>
                </form>
            </section>

            <section class="business-switcher">
                <h2>Notes</h2>
                <?php if (count($detail['notes']) === 0): ?>
                    <p class="muted">No notes for this contact yet.</p>
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
                    <p class="muted">No tasks for this contact yet.</p>
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
                    <p class="muted">No activity for this contact yet.</p>
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
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
