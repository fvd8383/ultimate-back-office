<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$summary = [
    'recent_contacts' => [],
    'new_leads_count' => 0,
    'open_tasks_count' => 0,
    'status_summary' => [],
];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $summary = LeadHub::dashboardSummary((int) $context['business_id']);
    } catch (Throwable $exception) {
        $error = 'Lead Hub dashboard could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'dashboard', 'Dashboard');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php endif; ?>

    <section class="business-switcher">
        <div class="button-row secondary-link">
            <?= ui_button('Add Contact', 'contact.php?business_id=' . urlencode((string) $context['business_id']), 'primary') ?>
            <?= ui_button('View Contacts', 'contacts.php?business_id=' . urlencode((string) $context['business_id']), 'secondary') ?>
        </div>
    </section>

    <section class="metrics-grid" aria-label="Lead Hub CRM summary">
        <article>
            <span>Recent Contacts</span>
            <strong><?= e(count($summary['recent_contacts'])) ?></strong>
        </article>
        <article>
            <span>New Leads</span>
            <strong><?= e($summary['new_leads_count']) ?></strong>
        </article>
        <article>
            <span>Open Tasks</span>
            <strong><?= e($summary['open_tasks_count']) ?></strong>
        </article>
    </section>

    <section class="business-switcher">
        <h2>Recent Contacts and Leads</h2>
        <?php if (count($summary['recent_contacts']) === 0): ?>
            <p class="muted">No contacts yet. Add a contact manually or submit a 247SP website request.</p>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($summary['recent_contacts'] as $contact): ?>
                    <?php $contactName = trim((string) $contact['first_name'] . ' ' . (string) $contact['last_name']); ?>
                    <article>
                        <strong><a href="contact.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($contact['id']) ?>"><?= e($contactName !== '' ? $contactName : 'Contact') ?></a></strong>
                        <p><?= e(trim(implode(' · ', array_filter([(string) $contact['phone'], (string) $contact['email'], (string) $contact['status_name']])))) ?></p>
                        <span><?= e($contact['source_detail'] ?: ($contact['source_module_key'] ?: 'Manual')) ?> · <?= e($contact['updated_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="business-switcher">
        <h2>Status Summary</h2>
        <?php if (count($summary['status_summary']) === 0): ?>
            <p class="muted">No contact status activity yet.</p>
        <?php else: ?>
            <div class="pill-list">
                <?php foreach ($summary['status_summary'] as $status): ?>
                    <?= ui_badge((string) $status['status_name'] . ': ' . (string) $status['contact_count'], 'status') ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="business-switcher">
        <h2>Shared CRM Foundation</h2>
        <p class="muted">Lead Hub contacts are the shared CRM records for 247SP website leads, future SSP invoice customers, EMD leads, and TUHWD review contacts.</p>
    </section>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
