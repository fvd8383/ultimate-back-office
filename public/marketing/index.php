<?php

$config = require __DIR__ . '/config/marketing.php';
require_once __DIR__ . '/includes/components.php';

$home = $config['homepage'];
$pricing = $config['promoted_pricing'];
$pageDescription = 'Website, business phone, AI receptionist, text, chat, email, and lead management working together to help service businesses capture leads 24/7.';
marketing_document_head($config, [
    'title' => '24/7 Sales Partner | Never Miss Another Lead',
    'description' => $pageDescription,
    'path' => '/',
    'page_name' => 'homepage',
    'social' => true,
]);
marketing_header($config);
?>
<main id="main-content">
    <section class="hero section" aria-labelledby="hero-title">
        <div class="container hero__grid">
            <div class="hero__copy">
                <p class="eyebrow"><?= marketing_e($home['eyebrow']) ?></p>
                <h1 id="hero-title"><?= marketing_e($home['headline']) ?></h1>
                <p class="hero__lede"><?= marketing_e($home['supporting_copy']) ?></p>
                <div class="button-row">
                    <?= marketing_signup_cta($config, 'hero') ?>
                    <a class="button button--secondary" href="#demo" data-analytics-event="hero_demo_scroll">See How It Works</a>
                </div>
                <p class="cta-note"><?= marketing_e($config['signup']['microcopy']) ?></p>
            </div>

            <!-- Replaceable conceptual product visual: intentionally not an actual product screenshot. -->
            <figure class="hero-visual" data-placeholder="conceptual-product-flow">
                <figcaption class="sr-only">Calls, texts, chats, and website inquiries flow through 24/7 Sales Partner into LeadHub for business-owner follow-up.</figcaption>
                <div class="hero-visual__browser" aria-hidden="true">
                    <div class="browser-bar"><i></i><i></i><i></i><span>yourbusiness.com</span></div>
                    <div class="browser-content">
                        <small>Professional business website</small>
                        <strong>Ready when customers need you.</strong>
                        <span>Request service</span>
                    </div>
                </div>
                <div class="hero-visual__channels" aria-hidden="true">
                    <span>Call</span><span>Text</span><span>Chat</span><span>Form</span>
                </div>
                <div class="hero-visual__capture" aria-hidden="true">
                    <span>Captured in</span><strong>LeadHub</strong><small>Ready for your follow-up</small>
                </div>
            </figure>
        </div>
    </section>

    <section class="problem section section--white" aria-labelledby="problem-title">
        <div class="container problem__grid">
            <div>
                <p class="eyebrow">The everyday problem</p>
                <h2 id="problem-title"><?= marketing_e($home['problem_headline']) ?></h2>
                <p class="section-lede">Someone calls while you're working, texts while you're driving, or visits your website after hours. You cannot personally answer every inquiry—and a potential customer may move on when nobody responds.</p>
            </div>
            <ul class="moment-list" aria-label="Times a business owner may miss an inquiry">
                <li><span>01</span><strong>You're on a job</strong><small>The phone rings while your hands are full.</small></li>
                <li><span>02</span><strong>You're already talking</strong><small>Another customer tries to reach you.</small></li>
                <li><span>03</span><strong>It's after hours</strong><small>A prospect is ready to request service.</small></li>
            </ul>
        </div>
    </section>

    <section class="solution section" aria-labelledby="solution-title">
        <div class="container">
            <?= marketing_section_heading('One connected system', $home['solution_headline'], '247SP keeps your lead channels connected so an inquiry has somewhere to go—even when you are busy serving another customer.', 'center') ?>
            <div class="system-flow" role="img" aria-label="Website, phone, text, chat, and forms feed into 24/7 Sales Partner, then LeadHub, then the business owner">
                <div class="system-flow__sources">
                    <span>Website</span><span>Phone</span><span>Text</span><span>Chat</span><span>Forms</span>
                </div>
                <div class="flow-arrow" aria-hidden="true">→</div>
                <div class="system-flow__core"><small>Connected by</small><strong>24/7 Sales Partner</strong></div>
                <div class="flow-arrow" aria-hidden="true">→</div>
                <div class="system-flow__endpoints">
                    <div><small>Organized in</small><strong>LeadHub</strong></div>
                    <div><small>Actioned by</small><strong>Business Owner</strong></div>
                </div>
            </div>
            <p class="flow-caption">All lead channels feed into one place.</p>
        </div>
    </section>

    <section class="demo section section--ink" id="demo" aria-labelledby="demo-title">
        <div class="container demo__grid">
            <div>
                <p class="eyebrow eyebrow--light">See the complete journey</p>
                <h2 id="demo-title">See 24/7 Sales Partner in Action</h2>
                <p>See how a potential customer can go from finding your business to becoming a captured lead—even when you're unavailable.</p>
                <ol class="demo-steps">
                    <li>They find your business and choose how to get in touch.</li>
                    <li>247SP captures the inquiry from web, phone, text, or chat.</li>
                    <li>LeadHub keeps the opportunity organized for follow-up.</li>
                </ol>
            </div>
            <div class="video-shell" data-vsl-container>
<?php if (!empty($config['vsl']['video_url'])): ?>
                <video controls preload="metadata" data-vsl-player<?= !empty($config['vsl']['poster_image']) ? ' poster="' . marketing_e(marketing_asset_url($config['vsl']['poster_image'])) . '"' : '' ?>>
                    <source src="<?= marketing_e($config['vsl']['video_url']) ?>">
                    Your browser does not support embedded video.
                </video>
<?php else: ?>
                <!-- VSL placeholder. Configure vsl.video_url and vsl.poster_image in config/marketing.php. -->
                <div class="video-placeholder" data-placeholder="vsl">
                    <span class="play-treatment" aria-hidden="true"></span>
                    <strong><?= marketing_e($config['vsl']['placeholder_label']) ?></strong>
                    <small>Approved product footage will appear here.</small>
                </div>
<?php endif; ?>
            </div>
        </div>
    </section>

    <section class="steps section" id="how-it-works" aria-labelledby="steps-title">
        <div class="container">
            <?= marketing_section_heading('How it works', 'From first visit to your next follow-up.', 'Four simple steps keep the opportunity moving.', 'center') ?>
            <div class="steps-grid">
                <article><span>1</span><h3>Customers find you</h3><p>Your professionally built website gives prospects somewhere to learn about your business and request service.</p></article>
                <article><span>2</span><h3>They contact you their way</h3><p>Phone, web form, text, or website chat—the prospect chooses what is convenient.</p></article>
                <article><span>3</span><h3>247SP captures the opportunity</h3><p>The connected system helps keep inquiries from disappearing when you cannot respond personally.</p></article>
                <article><span>4</span><h3>You manage the lead</h3><p>LeadHub keeps everything organized so you can follow up and close the job.</p></article>
            </div>
        </div>
    </section>

    <section class="features section section--white" id="included" aria-labelledby="features-title">
        <div class="container">
            <?= marketing_section_heading("What's included", 'Everything works together to help you capture the next opportunity.', 'One connected offer for your website, communication, and lead follow-up.', 'center') ?>
            <div class="feature-grid">
<?php foreach ($config['features'] as $feature): ?>
                <article class="feature-card<?= !empty($feature['featured']) ? ' feature-card--featured' : '' ?>">
                    <span class="feature-card__mark" aria-hidden="true"><?= marketing_e(strtoupper(substr($feature['label'], 0, 1))) ?></span>
                    <div><h3><?= marketing_e($feature['label']) ?></h3><p><?= marketing_e($feature['description']) ?></p></div>
                </article>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="different section" aria-labelledby="different-title">
        <div class="container different__grid">
            <div>
                <p class="eyebrow">Why connection matters</p>
                <h2 id="different-title">More Than a Website. More Than an Answering Service.</h2>
                <p>A website gives customers somewhere to find you. A phone system gives them somewhere to call. A CRM gives you somewhere to store their information.</p>
                <p><strong>24/7 Sales Partner connects the entire journey.</strong></p>
            </div>
            <ol class="journey-list" aria-label="The connected customer journey">
                <li><span>01</span><strong>Get Found</strong></li>
                <li><span>02</span><strong>Get Contacted</strong></li>
                <li><span>03</span><strong>Capture the Lead</strong></li>
                <li><span>04</span><strong>Follow Up</strong></li>
            </ol>
        </div>
    </section>

    <section class="industries section section--white" aria-labelledby="industries-title">
        <div class="container industries__grid">
            <div>
                <p class="eyebrow">Made for local service</p>
                <h2 id="industries-title">Built for businesses that can't sit by the phone all day.</h2>
                <p>247SP is designed around the reality of owner-operators and small teams doing the work their customers hired them to do.</p>
            </div>
            <ul class="industry-list">
<?php foreach ($config['industries'] as $industry): ?>
                <li><?= marketing_e($industry) ?></li>
<?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="pricing section" id="pricing" aria-labelledby="pricing-title">
        <div class="container pricing__grid">
            <div class="pricing__intro">
                <p class="eyebrow">Launch pricing</p>
                <h2 id="pricing-title">Start with the complete 247SP system.</h2>
                <p>Every customer receives the same core launch offer. Alpha is early-customer pricing—not a smaller feature tier.</p>
                <p class="ecosystem-note"><strong><?= marketing_e($config['signup']['ecosystem_copy']) ?></strong></p>
            </div>
            <article class="pricing-card" data-promoted-cohort="<?= marketing_e($pricing['cohort_key']) ?>">
                <header><div><span class="pricing-card__badge"><?= marketing_e($pricing['name']) ?></span><p><?= marketing_e($pricing['audience']) ?></p></div><strong><?= marketing_e($pricing['intro_price']) ?></strong></header>
                <div class="pricing-card__terms">
                    <div><strong><?= marketing_e($pricing['setup_price']) ?></strong><span><?= marketing_e($pricing['setup_label']) ?></span></div>
                    <div><strong><?= marketing_e($pricing['monthly_price']) ?></strong><span><?= marketing_e($pricing['monthly_label']) ?></span></div>
                </div>
                <ul class="included-list">
                    <li>Professionally built business website</li><li>Business phone and AI-receptionist coverage</li><li>Business text messaging and website chat</li><li>Business email</li><li>LeadHub lead management</li>
                </ul>
                <?= marketing_signup_cta($config, 'pricing', 'button button--primary button--wide', $config['analytics_events']['pricing_cta']) ?>
                <p class="cta-note"><?= marketing_e($config['signup']['microcopy']) ?></p>
                <p class="pricing-card__disclaimer"><?= marketing_e($pricing['disclaimer']) ?></p>
            </article>
        </div>
    </section>

    <section class="faq section section--white" id="faq" aria-labelledby="faq-title">
        <div class="container faq__grid">
            <div class="faq__intro">
                <p class="eyebrow">Questions, answered</p>
                <h2 id="faq-title">Know what you're getting before you get started.</h2>
                <p>Plain answers about the website, communication coverage, LeadHub, account setup, and pricing.</p>
            </div>
            <div class="faq-list">
<?php foreach ($config['faq'] as $index => $item): ?>
                <details data-faq-item data-faq-index="<?= marketing_e((string) ($index + 1)) ?>"<?= !empty($item['placeholder']) ? ' data-content-placeholder="cancellation-policy"' : '' ?>>
                    <summary><?= marketing_e($item['question']) ?><span aria-hidden="true"></span></summary>
                    <div><p><?= marketing_e($item['answer']) ?></p></div>
                </details>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="final-cta section" aria-labelledby="final-title">
        <div class="container final-cta__panel">
            <p class="eyebrow eyebrow--light">Make sure your business is ready when they are.</p>
            <h2 id="final-title"><?= marketing_e($home['final_headline']) ?></h2>
            <p>Meet Your 24/7 Sales Partner.</p>
            <?= marketing_signup_cta($config, 'final_cta', 'button button--light') ?>
            <small><?= marketing_e($config['signup']['microcopy']) ?></small>
        </div>
    </section>
</main>

<aside class="mobile-cta" aria-label="Get started">
    <a href="<?= marketing_e(marketing_signup_url($config, 'mobile_sticky')) ?>" data-signup-cta data-analytics-event="<?= marketing_e($config['analytics_events']['primary_cta']) ?>" data-analytics-location="mobile_sticky">Get Started with 247SP</a>
</aside>

<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $config['brand']['name'],
    'description' => $pageDescription,
    'url' => $config['brand']['canonical_url'],
    'category' => 'Lead generation and lead capture system for local service businesses',
    'brand' => ['@type' => 'Brand', 'name' => $config['brand']['name']],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<script>window.marketingAnalyticsEvents = <?= json_encode($config['analytics_events'], JSON_UNESCAPED_SLASHES) ?>;</script>
<?php marketing_footer($config); ?>
