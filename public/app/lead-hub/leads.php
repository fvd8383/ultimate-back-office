<?php

require __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/LeadHub.php';

$context = lead_hub_bootstrap();
$leads = [];
$error = '';

if ((int) $context['business_id'] > 0) {
    try {
        $leads = LeadHub::recent247spWebsiteLeads((int) $context['business_id'], 50);
    } catch (Throwable $exception) {
        $error = 'Leads could not be loaded.';
    }
}

$ready = lead_hub_shell_begin($context, 'leads', 'Leads');
if ($ready): ?>
    <?php if ($error !== ''): ?>
        <?= ui_alert($error, 'error') ?>
    <?php elseif (count($leads) === 0): ?>
        <section class="empty-state">
            <h2>No website leads yet</h2>
            <p>247SP website form submissions will appear here after visitors send a request.</p>
        </section>
    <?php else: ?>
        <section class="business-switcher">
            <h2>247SP Website Submissions</h2>
            <div class="activity-list">
                <?php foreach ($leads as $lead): ?>
                    <?php $leadName = trim((string) $lead['first_name'] . ' ' . (string) $lead['last_name']); ?>
                    <article>
                        <strong><a href="lead.php?business_id=<?= e($context['business_id']) ?>&contact_id=<?= e($lead['id']) ?>"><?= e($leadName !== '' ? $leadName : 'Website lead') ?></a></strong>
                        <p><?= e($lead['source_detail'] ?: '247SP website') ?></p>
                        <p><?= e(trim(implode(' · ', array_filter([(string) $lead['phone'], (string) $lead['email'], (string) $lead['status_name']])))) ?></p>
                        <span><?= e($lead['submitted_at'] ?: $lead['updated_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php lead_hub_shell_end(); ?>
