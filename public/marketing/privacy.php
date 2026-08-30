<?php

$config = require __DIR__ . '/config/marketing.php';
require_once __DIR__ . '/includes/components.php';
marketing_document_head($config, [
    'title' => 'Privacy Policy | 24/7 Sales Partner',
    'description' => 'Privacy information for the 24/7 Sales Partner marketing website.',
    'path' => '/privacy.php',
    'page_name' => 'privacy',
    'body_class' => 'utility-body',
]);
marketing_header($config, true);
?>
<main id="main-content" class="utility-main">
    <article class="container utility-page">
        <header class="utility-page__header">
            <p class="eyebrow">Legal information</p>
            <h1>Privacy Policy</h1>
            <p>This page is reserved for the final privacy policy that will cover the 24/7 Sales Partner marketing experience.</p>
        </header>
        <div class="utility-notice" role="note">
            <strong>Final legal copy required before launch</strong>
            The repository does not currently contain an approved privacy policy that can be confirmed to cover 24/7 Sales Partner. No substantive policy language has been invented for this implementation.
        </div>
        <section class="utility-section">
            <h2>What will be covered</h2>
            <p>The approved policy should address marketing-site analytics, signup handoff to Ultimate Back Office, contact inquiries, and the treatment of information submitted through future marketing forms.</p>
        </section>
    </article>
</main>
<?php marketing_footer($config); ?>
