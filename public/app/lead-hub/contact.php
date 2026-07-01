<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$error = '';
$contactType = (string) ($_POST['contact_type'] ?? $_GET['type'] ?? 'lead');
$contactType = $contactType === 'contact' ? 'contact' : 'lead';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $context['business_id'] > 0 && is_array($context['user'])) {
    try {
        $contactId = LeadHub::createManualContact(
            (int) $context['business_id'],
            (int) $context['user']['id'],
            $_POST + ['contact_type' => $contactType]
        );
        header('Location: lead.php?business_id=' . urlencode((string) $context['business_id']) . '&contact_id=' . urlencode((string) $contactId));
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'CRM record could not be saved. Check the details and try again.';
    }
}

$title = $contactType === 'contact' ? 'Add Contact' : 'Add Lead';
$ready = lead_hub_shell_begin($context, $contactType === 'contact' ? 'contacts' : 'leads', $title);
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>

    <form method="post" action="contact.php" class="business-switcher form-stack">
        <input type="hidden" name="business_id" value="<?= e($context['business_id']) ?>">
        <input type="hidden" name="contact_type" value="<?= e($contactType) ?>">

        <label>Name
            <input name="name" required maxlength="150" value="<?= e((string) ($_POST['name'] ?? '')) ?>">
        </label>

        <div class="form-grid">
            <label>Phone
                <input name="phone" type="tel" maxlength="50" value="<?= e((string) ($_POST['phone'] ?? '')) ?>">
            </label>
            <label>Email
                <input name="email" type="email" maxlength="255" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
            </label>
        </div>

        <label>Source
            <input name="source_detail" maxlength="255" value="<?= e((string) ($_POST['source_detail'] ?? 'Manual entry')) ?>">
        </label>

        <label>Notes
            <textarea name="message" rows="5" maxlength="2000"><?= e((string) ($_POST['message'] ?? '')) ?></textarea>
        </label>

        <p class="form-help">Name is required. Add a phone number, email address, or both.</p>

        <div class="button-row">
            <?= ui_button($title, '', 'primary') ?>
            <?= ui_button('Cancel', $contactType === 'contact'
                ? 'contacts.php?business_id=' . urlencode((string) $context['business_id'])
                : 'leads.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
        </div>
    </form>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
