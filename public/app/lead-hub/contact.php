<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$error = '';
$contactId = (int) ($_POST['contact_id'] ?? $_GET['contact_id'] ?? 0);
$detail = null;
$statuses = [];
$contactType = (string) ($_POST['contact_type'] ?? $_GET['type'] ?? 'lead');
$contactType = $contactType === 'contact' ? 'contact' : 'lead';

if ((int) $context['business_id'] > 0) {
    try {
        $statuses = LeadHub::statusesForBusiness((int) $context['business_id']);
        if ($contactId > 0) {
            $detail = LeadHub::contactDetail((int) $context['business_id'], $contactId);
            if ($detail !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $contactType = (string) ($detail['contact']['contact_type'] ?? 'lead') === 'contact' ? 'contact' : 'lead';
            }
        }
    } catch (Throwable $exception) {
        $error = 'Lead Hub record could not be loaded.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $context['business_id'] > 0 && is_array($context['user'])) {
    try {
        if ($contactId > 0) {
            LeadHub::updateManualContact(
                (int) $context['business_id'],
                (int) $context['user']['id'],
                $contactId,
                $_POST + ['contact_type' => $contactType]
            );
        } else {
            $contactId = LeadHub::createManualContact(
                (int) $context['business_id'],
                (int) $context['user']['id'],
                $_POST + ['contact_type' => $contactType]
            );
        }

        header('Location: lead.php?business_id=' . urlencode((string) $context['business_id']) . '&contact_id=' . urlencode((string) $contactId));
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Lead Hub record could not be saved. Check the details and try again.';
    }
}

$existingContact = is_array($detail) ? $detail['contact'] : [];
$existingName = trim((string) ($existingContact['first_name'] ?? '') . ' ' . (string) ($existingContact['last_name'] ?? ''));
$selectedStatusId = (string) ($_POST['status_id'] ?? ($existingContact['status_id'] ?? ''));
$title = $contactId > 0 ? 'Edit ' . ($contactType === 'contact' ? 'Contact' : 'Lead') : ($contactType === 'contact' ? 'Add Contact' : 'Add Lead');
$ready = lead_hub_shell_begin($context, $contactType === 'contact' ? 'contacts' : 'leads', $title);
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>
    <?php if ($contactId > 0 && $detail === null): ?>
        <section class="empty-state">
            <h2>Record not found</h2>
            <p>No matching lead or contact exists for this business.</p>
            <?= ui_button('Back to Lead Hub', '../dashboard.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
        </section>
    <?php else: ?>

    <form method="post" action="contact.php" class="business-switcher form-stack">
        <input type="hidden" name="business_id" value="<?= e($context['business_id']) ?>">
        <input type="hidden" name="contact_id" value="<?= e($contactId) ?>">
        <input type="hidden" name="contact_type" value="<?= e($contactType) ?>">

        <label>Name
            <input name="name" required maxlength="150" value="<?= e((string) ($_POST['name'] ?? $existingName)) ?>">
        </label>

        <div class="form-grid">
            <label>Phone
                <input name="phone" type="tel" maxlength="50" value="<?= e((string) ($_POST['phone'] ?? ($existingContact['phone'] ?? ''))) ?>">
            </label>
            <label>Email
                <input name="email" type="email" maxlength="255" value="<?= e((string) ($_POST['email'] ?? ($existingContact['email'] ?? ''))) ?>">
            </label>
        </div>

        <label>Status
            <select name="status_id">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status['id']) ?>" <?= (string) $status['id'] === $selectedStatusId ? 'selected' : '' ?>><?= e($status['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Source
            <input name="source_detail" maxlength="255" value="<?= e((string) ($_POST['source_detail'] ?? ($existingContact['source_detail'] ?? 'Manual entry'))) ?>">
        </label>

        <?php if ($contactId <= 0): ?>
        <label>Notes
            <textarea name="message" rows="5" maxlength="2000"><?= e((string) ($_POST['message'] ?? '')) ?></textarea>
        </label>
        <?php endif; ?>

        <p class="form-help">Name is required. Add a phone number, email address, or both.</p>

        <div class="button-row">
            <?= ui_button($contactId > 0 ? 'Save Changes' : $title, '', 'primary') ?>
            <?= ui_button('Cancel', $contactType === 'contact'
                ? 'contacts.php?business_id=' . urlencode((string) $context['business_id'])
                : 'leads.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
        </div>
    </form>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
