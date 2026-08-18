<?php

$config = require __DIR__ . '/config/marketing.php';
require_once __DIR__ . '/includes/components.php';
marketing_document_head($config, [
    'title' => 'Contact and Support | 24/7 Sales Partner',
    'description' => 'Contact and support information for 24/7 Sales Partner.',
    'path' => '/contact.php',
    'page_name' => 'contact',
    'body_class' => 'utility-body',
]);
marketing_header($config, true);
?>
<main id="main-content" class="utility-main">
    <article class="container utility-page">
        <header class="utility-page__header">
            <p class="eyebrow">Contact and support</p>
            <h1>How can we help?</h1>
            <p>24/7 Sales Partner account and subscription access is securely managed through Ultimate Back Office.</p>
        </header>
<?php if (!empty($config['support']['email'])): ?>
        <section class="utility-section">
            <h2>Email support</h2>
            <p><a href="mailto:<?= marketing_e($config['support']['email']) ?>"><?= marketing_e($config['support']['email']) ?></a></p>
        </section>
<?php else: ?>
        <div class="utility-notice" role="note">
            <strong>Final public support contact required before launch</strong>
            No approved public support email or phone number was found in the repository, so none has been invented here.
        </div>
<?php endif; ?>
        <section class="utility-section">
            <h2>Existing customers</h2>
            <p>Sign in to your Ultimate Back Office account to review your business, subscription, billing, and product access.</p>
            <a class="button" href="<?= marketing_e($config['support']['account_url']) ?>">Go to Ultimate Back Office</a>
        </section>
    </article>
</main>
<?php marketing_footer($config); ?>
