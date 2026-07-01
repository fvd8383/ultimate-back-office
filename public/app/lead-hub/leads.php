<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$leads = [];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $leads = LeadHub::leadsForBusiness((int) $context['business_id'], 100);
    } catch (Throwable $exception) {
        $error = 'Leads could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'leads', 'Leads');
if ($ready): ?>
    <section class="business-switcher">
        <p class="muted">Website leads appear here automatically. You can also add leads manually when someone calls, emails, or walks in.</p>
        <div class="button-row">
            <?= ui_button('Add Lead', 'contact.php?business_id=' . urlencode((string) $context['business_id']) . '&type=lead', 'primary') ?>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif (count($leads) === 0): ?>
        <section class="empty-state">
            <h2>No leads yet</h2>
            <p>Website form submissions will appear here automatically. Add a manual lead for phone, email, or in-person inquiries.</p>
            <?= ui_button('Add Lead', 'contact.php?business_id=' . urlencode((string) $context['business_id']) . '&type=lead', 'primary') ?>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <h2>Lead Records</h2>
            <div class="activity-list">
                <?php foreach ($leads as $lead): ?>
                    <?php $leadName = trim((string) $lead['first_name'] . ' ' . (string) $lead['last_name']); ?>
                    <article>
                        <strong><a href="lead.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($lead['id']) ?>"><?= e($leadName !== '' ? $leadName : 'Lead') ?></a></strong>
                        <p><?= e($lead['source_detail'] ?: 'Manual entry') ?></p>
                        <p><?= e(trim(implode(' · ', array_filter([(string) $lead['phone'], (string) $lead['email'], (string) $lead['status_name']])))) ?></p>
                        <span><?= e($lead['updated_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
