<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$contacts = [];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $contacts = LeadHub::contactsForBusiness((int) $context['business_id'], 100);
    } catch (Throwable $exception) {
        $error = 'Contacts could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'contacts', 'Contacts');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif (count($contacts) === 0): ?>
        <section class="empty-state">
            <h2>No contacts yet</h2>
            <p>Lead Hub contacts will appear here after website submissions or manual CRM activity.</p>
            <?= ui_button('Add Contact', 'contact.php?business_id=' . urlencode((string) $context['business_id']), 'primary') ?>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <div class="button-row secondary-link">
                <?= ui_button('Add Contact', 'contact.php?business_id=' . urlencode((string) $context['business_id']), 'primary') ?>
            </div>
            <h2>Contact Records</h2>
            <div class="activity-list">
                <?php foreach ($contacts as $contact): ?>
                    <?php $contactName = trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name']); ?>
                    <article>
                        <strong><a href="contact.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($contact['id']) ?>"><?= e($contactName !== '' ? $contactName : 'Contact') ?></a></strong>
                        <p><?= e(trim(implode(' · ', array_filter([(string) $contact['phone'], (string) $contact['email'], (string) $contact['status_name']])))) ?></p>
                        <span><?= e($contact['source_detail'] ?: 'No source detail') ?> · <?= e($contact['updated_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
