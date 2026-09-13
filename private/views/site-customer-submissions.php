<?php if ($customerSubmissions !== []): ?>
<section class="site-customer-submissions" aria-label="Customer submissions">
    <h3>Customer submissions</h3>
    <ol>
    <?php foreach ($customerSubmissions as $entry): ?>
        <li><p><?= e(match ($entry['kind']) {
            'feedback' => 'Feedback', 'presentation_preference' => 'Presentation preference',
            'image_replacement_request' => 'Image replacement request', default => 'Submission',
        }) ?> · <?= e($entry['created_at']) ?></p>
        <?php if ($entry['kind'] === 'presentation_preference'): ?><p><?= e($entry['target']) ?>: <?= e($entry['value']) ?></p><?php endif; ?>
        <?php if ($entry['kind'] === 'image_replacement_request'): ?><p><?= e($entry['target']) ?></p><?php endif; ?>
        <p><?= nl2br(e($entry['text'])) ?></p></li>
    <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>
