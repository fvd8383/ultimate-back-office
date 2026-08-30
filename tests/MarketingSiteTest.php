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
marketingTestAssert(str_contains($html, 'href="/assets/css/marketing.css"'), 'Marketing CSS must use a document-root-safe URL.');
marketingTestAssert(str_contains($html, 'src="/assets/js/marketing.js"'), 'Marketing JavaScript must use a document-root-safe URL.');
marketingTestAssert(str_contains($html, 'src="/assets/brand/247sp-logo.svg"'), 'Marketing brand assets must use document-root-safe URLs.');
marketingTestAssert(str_contains($html, 'href="/assets/brand/favicon.svg"'), 'Marketing favicon must use a document-root-safe URL.');
marketingTestAssert(!preg_match('/(?:href|src)="assets\//', $html), 'Marketing assets must not use campaign-path-relative URLs.');
marketingTestAssert(marketing_asset_url('assets/img/example.png') === '/assets/img/example.png', 'Marketing asset helper must normalize to the document root.');

foreach (['privacy.php', 'terms.php', 'contact.php', 'assets/css/marketing.css', 'assets/js/marketing.js', 'assets/brand/247sp-logo.svg', 'assets/brand/favicon.svg', 'assets/img/og-247sp.png'] as $path) {
    marketingTestAssert(is_file($marketingRoot . '/' . $path), "Required marketing asset missing: {$path}");
}

marketingTestAssert(trim((string) file_get_contents($marketingRoot . '/assets/brand/247sp-logo.svg')) === trim((string) file_get_contents($root . '/public/assets/brands/247sp/logo.svg')), 'Marketing logo must match the approved 247SP logo.');
marketingTestAssert(trim((string) file_get_contents($marketingRoot . '/assets/brand/favicon.svg')) === trim((string) file_get_contents($root . '/public/assets/brands/247sp/favicon.svg')), 'Marketing favicon must match the approved 247SP favicon.');

marketingTestAssert(SignupContext::normalize('247SP') === '247sp', 'Supported product context must normalize.');
foreach (['stripe', 'alpha', '247sp-pro', 'lead_hub', '', null] as $unsupportedProduct) {
    marketingTestAssert(SignupContext::normalize($unsupportedProduct) === null, 'Unsupported product context must be rejected.');
}
marketingTestAssert(SignupContext::destination('247sp') === 'business-create.php?product=247sp', '247SP login must continue to business setup.');
marketingTestAssert(SignupContext::destination(null) === 'dashboard.php', 'Generic login behavior must remain unchanged.');
marketingTestAssert(SignupContext::query('247sp', ['step' => 'services', 'business_id' => 10]) === '?step=services&business_id=10&product=247sp', 'Allowlisted context must survive onboarding navigation.');
marketingTestAssert(SignupContext::query(null, ['step' => 'services', 'business_id' => 10]) === '?step=services&business_id=10', 'Generic onboarding navigation must remain unchanged.');

$accountSources = file_get_contents($root . '/public/accounts/signup.php')
    . file_get_contents($root . '/public/accounts/login.php')
    . file_get_contents($root . '/public/accounts/verify.php');
marketingTestAssert(substr_count($accountSources, 'SignupContext::') >= 9, 'Account creation and OTP flow must preserve allowlisted product context.');

$businessOnboardingSource = (string) file_get_contents($root . '/public/accounts/business-create.php');
marketingTestAssert(substr_count($businessOnboardingSource, '<input type="hidden" name="product"') === 4, 'Every onboarding POST form must preserve the allowlisted product context.');
foreach (['business_info', 'services', 'modules', 'confirmation'] as $onboardingStep) {
    marketingTestAssert(str_contains($businessOnboardingSource, "business_onboarding_url('{$onboardingStep}'"), "Onboarding navigation must preserve context for {$onboardingStep}.");
}
marketingTestAssert(substr_count($businessOnboardingSource, 'header(\'Location: \' . business_onboarding_url(') === 3, 'Every internal onboarding redirect must preserve product context.');
marketingTestAssert(substr_count($businessOnboardingSource, 'SignupContext::is247sp($productContext)') >= 3, '247SP onboarding presentation must remain active throughout preserved steps.');

$browserContextSources = $accountSources . $businessOnboardingSource;
marketingTestAssert(!preg_match('/\$_(?:GET|POST)\[[^\]]*(?:cohort|price|sequence|stripe|allocation)/i', $browserContextSources), 'Browser product context must not accept pricing, allocation, or provider commands.');

$marketingSource = $html . file_get_contents($marketingRoot . '/config/marketing.php');
marketingTestAssert(!str_contains($marketingSource, 'PricingCohortManager'), 'Public marketing must not call the pricing manager.');
marketingTestAssert(!preg_match('/sequence_counter|customer_sequence_number|subscription_commercial_terms|pricing_cohort_allocations|pricing_sequence_counters/i', $marketingSource), 'Public marketing must not integrate with pricing allocation storage.');

echo "Marketing site tests passed.\n";
