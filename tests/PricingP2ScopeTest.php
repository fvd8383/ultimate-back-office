<?php

declare(strict_types=1);

function pricingP2ScopeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$paths = [
    'manager' => __DIR__ . '/../private/classes/PricingCohortManager.php',
    'business' => __DIR__ . '/../private/classes/BusinessFoundation.php',
    'billing' => __DIR__ . '/../private/classes/BillingFoundation.php',
    'stripe' => __DIR__ . '/../private/classes/StripeBilling.php',
    'signup' => __DIR__ . '/../public/accounts/business-create.php',
    'checkout' => __DIR__ . '/../public/accounts/checkout.php',
    'customer_billing' => __DIR__ . '/../public/accounts/billing.php',
    'customer_subscriptions' => __DIR__ . '/../public/accounts/subscriptions.php',
    'admin_billing' => __DIR__ . '/../public/app/admin/billing.php',
    'webhook' => __DIR__ . '/../private/stripe-webhook-endpoint.php',
    'utility' => __DIR__ . '/../scripts/configure-247sp-stripe-prices.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $source[$key] = file_get_contents($path);
    pricingP2ScopeAssert(is_string($source[$key]), "{$key} source must be readable.");
}

pricingP2ScopeAssert(str_contains($source['manager'], '$ownsTransaction'), 'Pricing manager must explicitly track transaction ownership.');
pricingP2ScopeAssert(str_contains($source['business'], 'PricingCohortManager::assignCompletedSignup'), 'Business completion must call the authoritative pricing boundary.');
pricingP2ScopeAssert(str_contains($source['business'], 'onboarding:lock_business') && str_contains($source['business'], 'FOR UPDATE'), 'Completed signup must lock its local business/subscription context.');
pricingP2ScopeAssert(
    strpos($source['business'], 'PricingCohortManager::assignCompletedSignup')
        < strpos($source['business'], 'onboarding:mark_complete'),
    'Pricing assignment must occur before the business is marked complete in the shared transaction.'
);
pricingP2ScopeAssert(str_contains($source['signup'], 'Csrf::requireValid') && str_contains($source['signup'], 'Csrf::input'), 'Pricing-critical onboarding must use reusable CSRF protection.');
pricingP2ScopeAssert(!str_contains($source['signup'], 'Debug exception:'), 'Onboarding must not expose raw debug errors.');

$newTables = [
    'pricing_cohorts',
    'product_customer_sequence_counters',
    'product_customer_sequence_allocations',
    'subscription_commercial_terms',
];
foreach (['signup', 'checkout', 'customer_billing', 'customer_subscriptions', 'admin_billing'] as $route) {
    foreach ($newTables as $table) {
        pricingP2ScopeAssert(!str_contains($source[$route], $table), "{$route} must not contain direct {$table} SQL.");
    }
}

pricingP2ScopeAssert(substr_count($source['billing'], 'terms.locked_setup_fee ELSE p.setup_fee') >= 2, '247SP setup reads must prefer the immutable snapshot.');
pricingP2ScopeAssert(substr_count($source['billing'], 'terms.locked_monthly_fee ELSE p.monthly_fee') >= 2, '247SP monthly reads must prefer the immutable snapshot.');
pricingP2ScopeAssert(str_contains($source['billing'], "WHEN p.product_key = '247sp' THEN COALESCE(terms.locked_monthly_fee, 0)"), 'Admin MRR must use locked recurring terms.');
pricingP2ScopeAssert(str_contains($source['billing'], "WHEN s.status <> 'active' THEN 0"), 'Trials and non-active subscriptions must not count in MRR.');
pricingP2ScopeAssert(!preg_match('/mrr[\s\S]{0,300}setup_fee/i', $source['billing']), 'Setup fees must never enter MRR logic.');
pricingP2ScopeAssert(str_contains($source['billing'], 'FROM subscriptions s') && str_contains($source['billing'], 'LEFT JOIN subscription_commercial_terms terms'), 'Admin history must retain cancelled subscriptions and locked terms.');
pricingP2ScopeAssert(str_contains($source['billing'], 'LEFT JOIN business_modules access_bm'), 'Inactive module access must not hide admin subscription history.');
pricingP2ScopeAssert(str_contains($source['billing'], 'INNER JOIN business_users bu') && str_contains($source['billing'], 'bu.user_id = :user_id'), 'Customer billing reads must preserve tenant membership filtering.');

foreach (['checkout', 'customer_billing', 'customer_subscriptions', 'admin_billing'] as $route) {
    pricingP2ScopeAssert(
        preg_match('/\b(?:79|97|147|197|250)(?:\.00)?\b/', $source[$route]) !== 1,
        "{$route} must not hard-code cohort amounts."
    );
}
pricingP2ScopeAssert(!str_contains($source['customer_billing'], 'locked_stripe_'), 'Customer billing must not expose provider Price references.');
pricingP2ScopeAssert(!str_contains($source['customer_subscriptions'], 'locked_stripe_'), 'Customer subscriptions must not expose provider Price references.');
pricingP2ScopeAssert(str_contains($source['customer_billing'], "str_ends_with(\$host, '.stripe.com')"), 'Customer invoice links must be restricted to Stripe HTTPS hosts.');
pricingP2ScopeAssert(str_contains($source['admin_billing'], 'locked_stripe_recurring_price_ref'), 'Admin billing may show the locked recurring provider reference.');
pricingP2ScopeAssert(!preg_match('/name="(?:cohort|customer_sequence_number|configuration_version|locked_)/', $source['customer_billing'] . $source['customer_subscriptions'] . $source['admin_billing']), 'Pricing snapshot mutation controls must be absent.');

pricingP2ScopeAssert(str_contains($source['checkout'], "\$isPost") && str_contains($source['checkout'], 'Csrf::requireValid'), 'Checkout provider mutation must be POST-only and CSRF-protected.');
pricingP2ScopeAssert(str_contains($source['customer_billing'], '<form method="post" action="checkout.php">'), 'Billing retry must submit a POST form.');
pricingP2ScopeAssert(str_contains($source['customer_subscriptions'], '<form method="post" action="checkout.php">'), 'Subscription retry must submit a POST form.');
pricingP2ScopeAssert(str_contains($source['admin_billing'], 'Csrf::requireValid') && str_contains($source['admin_billing'], 'Csrf::input'), 'Admin billing mutations must use CSRF.');
pricingP2ScopeAssert(!str_contains($source['webhook'], "\$exception->getMessage()"), 'Webhook responses must not expose provider or database details.');
pricingP2ScopeAssert(!str_contains($source['stripe'], "STRIPE_247SP_PRICE_ID'"), 'Cohort Checkout must not read the legacy recurring Price setting.');
pricingP2ScopeAssert(!str_contains($source['stripe'], "STRIPE_247SP_SETUP_FEE_PRICE_ID'"), 'Cohort Checkout must not read the legacy setup Price setting.');
pricingP2ScopeAssert(str_contains($source['utility'], "PHP_SAPI !== 'cli'"), 'Price configuration must be CLI-only.');
pricingP2ScopeAssert(str_contains($source['utility'], 'subscription_commercial_terms') === false, 'Price configuration must never mutate locked snapshots.');
pricingP2ScopeAssert(str_contains($source['utility'], 'allocation_count'), 'Price replacement must detect consumed cohort versions.');

$migration023 = glob(__DIR__ . '/../database/migrations/023*');
pricingP2ScopeAssert(
    is_array($migration023)
        && count($migration023) === 1
        && basename($migration023[0]) === '023_website_platform_foundation.sql',
    'Reserved migration 023 may exist only as the Sprint 8.8 website-platform foundation.'
);
pricingP2ScopeAssert(
    !str_contains(implode("\n", $source), '023_website_platform_foundation'),
    'Pricing P2 runtime and utility files must not reference or repurpose migration 023.'
);

echo "Pricing P2 scope tests passed.\n";
