<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$notes = [];
$contacts = [];
$error = '';
$notice = isset($_GET['saved']) ? 'Note saved.' : '';
$selectedContactId = (string) ($_POST['contact_id'] ?? $_GET['contact_id'] ?? '');

if ((int) $context['business_id'] > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($context['user'])) {
        try {
            LeadHub::createManualNote((int) $context['business_id'], (int) $context['user']['id'], $_POST);
            header('Location: notes.php?business_id=' . urlencode((string) $context['business_id']) . '&saved=1');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'Note could not be saved.';
        }
    }

    try {
        $notes = LeadHub::notesForBusiness((int) $context['business_id'], 100);
        $contacts = LeadHub::contactsForBusiness((int) $context['business_id'], 200);
    } catch (Throwable $exception) {
        if ($error === '') {
            $error = 'Notes could not be loaded.';
        }
    }
}

$ready = lead_hub_shell_begin($context, 'notes', 'Notes');
if ($ready): ?>
    <?php if ($notice !== ''): ?>
        <?= ui_alert($notice, 'success') ?>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>

    <section class="business-switcher">
        <h2>Add Note</h2>
        <form method="post" action="notes.php" class="form-stack">
            <input type="hidden" name="business_id" value="<?= e($context['business_id']) ?>">

            <label>Link to contact or lead
                <select name="contact_id">
                    <option value="">General business note</option>
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

            <label>Note
                <textarea name="note_body" rows="5" maxlength="2000" required><?= e((string) ($_POST['note_body'] ?? '')) ?></textarea>
            </label>

            <p class="form-help">Leave the contact field set to General business note when the note is not tied to a specific lead or contact.</p>

            <div class="button-row">
                <?= ui_button('Add Note', '', 'primary') ?>
            </div>
        </form>
    </section>

    <?php if (count($notes) === 0): ?>
        <section class="empty-state">
            <h2>No notes yet</h2>
            <p>Add a general business note or link a note to an existing lead or contact.</p>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <h2>Recent Notes</h2>
            <div class="activity-list">
                <?php foreach ($notes as $note): ?>
                    <?php $contactName = trim((string) $note['first_name'] . ' ' . (string) $note['last_name']); ?>
                    <article>
                        <strong>
                            <?php if ((int) ($note['contact_id'] ?? 0) > 0): ?>
                                <a href="lead.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($note['contact_id']) ?>"><?= e($contactName !== '' ? $contactName : 'Linked contact') ?></a>
                            <?php else: ?>
                                <?= e('General business note') ?>
                            <?php endif; ?>
                        </strong>
                        <p><?= nl2br(e($note['note_body'])) ?></p>
                        <span><?= e($note['created_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
