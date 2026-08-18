<?php

$config = require __DIR__ . '/config/marketing.php';
require_once __DIR__ . '/includes/components.php';
marketing_document_head($config, [
    'title' => 'Terms of Service | 24/7 Sales Partner',
    'description' => 'Terms information for 24/7 Sales Partner.',
    'path' => '/terms.php',
    'page_name' => 'terms',
    'body_class' => 'utility-body',
]);
marketing_header($config, true);
?>
<main id="main-content" class="utility-main">
    <article class="container utility-page">
        <header class="utility-page__header">
            <p class="eyebrow">Legal information</p>
            <h1>Terms of Service</h1>
            <p>This page is reserved for the final terms governing the 24/7 Sales Partner offer and its relationship to Ultimate Back Office.</p>
        </header>
        <div class="utility-notice" role="note">
            <strong>Final legal and commercial copy required before launch</strong>
            The repository does not currently contain approved public terms, a refund policy, or finalized cancellation terms. No substantive terms have been invented for this implementation.
        </div>
        <section class="utility-section">
            <h2>Review still required</h2>
            <p>Final terms should cover the service relationship, pricing and introductory periods, cancellation, refunds, communication services, website delivery, account responsibilities, and any approved usage policies.</p>
        </section>
    </article>
</main>
<?php marketing_footer($config); ?>
