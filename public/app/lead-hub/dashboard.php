<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$summary = [
    'total_contacts_count' => 0,
    'recent_contacts' => [],
    'new_leads_count' => 0,
    'open_tasks_count' => 0,
    'status_summary' => [],
];
$error = '';

function lead_hub_dashboard_error(Throwable $exception): string
{
    error_log('Lead Hub dashboard error: ' . $exception->getMessage());

    try {
        $environment = strtolower((string) Database::config('APP_ENV', 'production'));
        $debug = (bool) Database::config('APP_DEBUG', false);
    } catch (Throwable $configException) {
        $environment = 'production';
        $debug = false;
    }

    if ($debug || $environment !== 'production') {
        return 'Lead Hub dashboard could not be loaded: ' . $exception->getMessage();
    }

    return 'Lead Hub dashboard could not be loaded.';
}

if ((int) $context['business_id'] > 0) {
    try {
        $summary = LeadHub::dashboardSummary((int) $context['business_id']);
    } catch (Throwable $exception) {
        $error = lead_hub_dashboard_error($exception);
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
            <span>Total Contacts</span>
            <strong><?= e($summary['total_contacts_count']) ?></strong>
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
        <h2>Your Customer Pipeline</h2>
        <p class="muted">Track new inquiries, customer details, follow-ups, and notes in one place.</p>
    </section>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
