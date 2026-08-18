<?php

require_once __DIR__ . '/../private/classes/SignupContext.php';

function marketingTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$marketingRoot = $root . '/public/marketing';
$config = require $marketingRoot . '/config/marketing.php';

marketingTestAssert($config['promoted_pricing']['cohort_key'] === 'alpha', 'Alpha must be the centralized promoted cohort.');
marketingTestAssert($config['promoted_pricing']['monthly_price'] === '$79/month', 'Approved Alpha monthly presentation is required.');
marketingTestAssert(str_contains($config['signup']['url'], 'signup.php?product=247sp'), 'Signup CTA must preserve the 247SP context.');
marketingTestAssert($config['vsl']['video_url'] === null && $config['vsl']['placeholder'] === true, 'Missing VSL must remain an explicit placeholder.');
marketingTestAssert($config['support']['email'] === null, 'An unapproved support address must not be invented.');

ob_start();
include $marketingRoot . '/index.php';
$html = (string) ob_get_clean();

foreach (['Never Miss Another Lead.', 'See 24/7 Sales Partner in Action', 'How It Works', 'Everything works together to help you capture the next opportunity.', 'More Than a Website. More Than an Answering Service.', 'Customers #1–5', 'First 6 calendar months free', '$79/month'] as $expected) {
    marketingTestAssert(str_contains($html, $expected), "Homepage must include: {$expected}");
}

marketingTestAssert(substr_count($html, 'data-signup-cta') >= 5, 'Major conversion sections must use centralized signup CTAs.');
marketingTestAssert(str_contains($html, 'data-placeholder="vsl"'), 'The missing VSL must render a marked placeholder.');
marketingTestAssert(str_contains($html, 'data-content-placeholder="cancellation-policy"'), 'Unfinalized cancellation content must remain marked.');
marketingTestAssert(!preg_match('/testimonial|aggregateRating|customer logos|guaranteed leads/i', $html), 'The page must not introduce disallowed social proof or guarantees.');

foreach (['privacy.php', 'terms.php', 'contact.php', 'assets/css/marketing.css', 'assets/js/marketing.js', 'assets/brand/247sp-logo.svg', 'assets/brand/favicon.svg', 'assets/img/og-247sp.png'] as $path) {
    marketingTestAssert(is_file($marketingRoot . '/' . $path), "Required marketing asset missing: {$path}");
}

marketingTestAssert(trim((string) file_get_contents($marketingRoot . '/assets/brand/247sp-logo.svg')) === trim((string) file_get_contents($root . '/public/assets/brands/247sp/logo.svg')), 'Marketing logo must match the approved 247SP logo.');
marketingTestAssert(trim((string) file_get_contents($marketingRoot . '/assets/brand/favicon.svg')) === trim((string) file_get_contents($root . '/public/assets/brands/247sp/favicon.svg')), 'Marketing favicon must match the approved 247SP favicon.');

marketingTestAssert(SignupContext::normalize('247SP') === '247sp', 'Supported product context must normalize.');
marketingTestAssert(SignupContext::normalize('stripe') === null, 'Unsupported product context must be rejected.');
marketingTestAssert(SignupContext::destination('247sp') === 'business-create.php?product=247sp', '247SP login must continue to business setup.');
marketingTestAssert(SignupContext::destination(null) === 'dashboard.php', 'Generic login behavior must remain unchanged.');

$accountSources = file_get_contents($root . '/public/accounts/signup.php')
    . file_get_contents($root . '/public/accounts/login.php')
    . file_get_contents($root . '/public/accounts/verify.php');
marketingTestAssert(substr_count($accountSources, 'SignupContext::') >= 9, 'Account creation and OTP flow must preserve allowlisted product context.');
marketingTestAssert(!preg_match('/\$_(?:GET|POST)\[[^\]]*(?:cohort|price|sequence|stripe)/i', $accountSources), 'Marketing context must not accept pricing or provider commands.');

$marketingSource = $html . file_get_contents($marketingRoot . '/config/marketing.php');
marketingTestAssert(!str_contains($marketingSource, 'PricingCohortManager'), 'Public marketing must not call the pricing manager.');
marketingTestAssert(!preg_match('/sequence_counter|customer_sequence_number|subscription_commercial_terms/i', $marketingSource), 'Public marketing must not integrate with pricing allocation storage.');

echo "Marketing site tests passed.\n";
