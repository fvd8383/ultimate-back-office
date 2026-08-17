<?php

declare(strict_types=1);

error_reporting(E_ALL);

function stripeP2Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function stripeP2Config(array $overrides = []): void
{
    $config = [
        'DB_HOST' => 'localhost',
        'DB_PORT' => 3306,
        'DB_NAME' => 'test',
        'DB_USER' => 'test',
        'DB_PASSWORD' => 'test',
        'APP_ENV' => 'testing',
        'APP_DEBUG' => false,
        'APP_BASE_URL' => 'https://app.example.test',
        'ACCOUNTS_BASE_URL' => 'https://accounts.example.test',
        'STRIPE_MODE' => 'test',
        'STRIPE_SECRET_KEY' => 'sk_test_example',
        'STRIPE_SUCCESS_URL' => 'https://accounts.example.test/billing.php?checkout=success&business_id={BUSINESS_ID}&checkout_session_id={CHECKOUT_SESSION_ID}',
        'STRIPE_CANCEL_URL' => 'https://accounts.example.test/billing.php?checkout=cancelled&business_id={BUSINESS_ID}',
        'STRIPE_247SP_PRICE_ID' => 'price_LegacyRecurring999',
        'STRIPE_247SP_SETUP_FEE_PRICE_ID' => 'price_LegacySetup999',
    ];
    $config = array_replace($config, $overrides);
    $property = new ReflectionProperty(Database::class, 'config');
    $property->setValue(null, $config);
}

function stripeP2Subscription(string $cohort): array
{
    $terms = [
        'alpha' => ['0.00', '79.00', 6, 'price_AlphaRecurring1', null],
        'beta' => ['0.00', '97.00', 0, 'price_BetaRecurring1', null],
        'founding' => ['100.00', '147.00', 0, 'price_FoundingRecurring1', 'price_FoundingSetup1'],
        'standard' => ['250.00', '197.00', 0, 'price_StandardRecurring1', 'price_StandardSetup1'],
    ][$cohort];

    return [
        'id' => 100,
        'business_id' => 10,
        'product_key' => '247sp',
        'commercial_terms_id' => 300,
        'allocation_id' => 200,
        'configuration_version' => 1,
        'cohort_key' => $cohort,
        'setup_fee' => $terms[0],
        'monthly_fee' => $terms[1],
        'free_introductory_months' => $terms[2],
        'locked_stripe_recurring_price_ref' => $terms[3],
        'locked_stripe_setup_price_ref' => $terms[4],
        'introductory_period_expires_at' => $terms[2] > 0 ? '2027-02-16 12:34:56' : null,
        'recurring_billing_starts_at' => $terms[2] > 0 ? '2027-02-16 12:34:56' : '2026-08-16 12:34:56',
    ];
}

require_once __DIR__ . '/../private/classes/StripeBilling.php';
stripeP2Config();
$business = ['id' => 10, 'business_name' => 'Example'];

$alpha = StripeBilling::buildCheckoutSessionRequest($business, stripeP2Subscription('alpha'), 'cus_alpha');
stripeP2Assert(count($alpha['line_items']) === 1, 'Alpha must have no setup line.');
stripeP2Assert($alpha['line_items'][0]['price'] === 'price_AlphaRecurring1', 'Alpha must use its locked recurring Price.');
stripeP2Assert($alpha['payment_method_collection'] === 'always', 'Alpha must always collect a payment method.');
stripeP2Assert(
    $alpha['subscription_data']['trial_end'] === (new DateTimeImmutable('2027-02-16 12:34:56', new DateTimeZone('UTC')))->getTimestamp(),
    'Alpha must use the exact stored trial expiration.'
);
stripeP2Assert($alpha['subscription_data']['trial_settings']['end_behavior']['missing_payment_method'] === 'cancel', 'Alpha must define safe missing-payment behavior.');

$beta = StripeBilling::buildCheckoutSessionRequest($business, stripeP2Subscription('beta'), 'cus_beta');
stripeP2Assert(count($beta['line_items']) === 1 && $beta['line_items'][0]['price'] === 'price_BetaRecurring1', 'Beta must use one locked recurring line.');
stripeP2Assert(!isset($beta['subscription_data']['trial_end']), 'Beta must not receive a trial.');

$founding = StripeBilling::buildCheckoutSessionRequest($business, stripeP2Subscription('founding'), 'cus_founding');
stripeP2Assert(
    array_column($founding['line_items'], 'price') === ['price_FoundingRecurring1', 'price_FoundingSetup1'],
    'Founding must contain exactly one recurring and one setup line.'
);
stripeP2Assert(!isset($founding['subscription_data']['trial_end']), 'Founding must not receive a trial.');

$standard = StripeBilling::buildCheckoutSessionRequest($business, stripeP2Subscription('standard'), 'cus_standard');
stripeP2Assert(
    array_column($standard['line_items'], 'price') === ['price_StandardRecurring1', 'price_StandardSetup1'],
    'Standard must contain exactly one recurring and one setup line.'
);

$allPayloads = json_encode([$alpha, $beta, $founding, $standard], JSON_THROW_ON_ERROR);
stripeP2Assert(!str_contains($allPayloads, 'Legacy'), 'Assigned Checkout must never use legacy global Price IDs.');

$subscription = stripeP2Subscription('founding');
$key = StripeBilling::providerOperationIdentity($subscription, 'checkout_session');
stripeP2Assert($key === StripeBilling::providerOperationIdentity($subscription, 'checkout_session'), 'Provider operation identity must be stable.');
stripeP2Assert(
    StripeBilling::providerOperationIdentity($subscription, 'checkout_session', 'cs_expired_1')
        === StripeBilling::providerOperationIdentity($subscription, 'checkout_session', 'cs_expired_1'),
    'Replacement Checkout identity must be stable for the same expired session.'
);
stripeP2Assert(substr_count($allPayloads, 'price_FoundingSetup1') === 1, 'A Founding retry payload must contain exactly one setup semantic.');
stripeP2Assert(substr_count($allPayloads, 'price_StandardSetup1') === 1, 'A Standard retry payload must contain exactly one setup semantic.');

stripeP2Config(['APP_ENV' => 'staging', 'STRIPE_MODE' => 'live', 'STRIPE_SECRET_KEY' => 'sk_live_example']);
$issues = StripeBilling::checkoutConfigurationIssues(stripeP2Subscription('alpha'));
stripeP2Assert(in_array('STRIPE_MODE_ENVIRONMENT_MISMATCH', $issues, true), 'Staging must reject live Stripe mode.');
stripeP2Config(['APP_ENV' => 'production', 'STRIPE_MODE' => 'test', 'STRIPE_SECRET_KEY' => 'sk_test_example']);
$issues = StripeBilling::checkoutConfigurationIssues(stripeP2Subscription('alpha'));
stripeP2Assert(in_array('STRIPE_MODE_ENVIRONMENT_MISMATCH', $issues, true), 'Production must reject test Stripe mode.');

$stripeSource = file_get_contents(__DIR__ . '/../private/classes/StripeBilling.php');
$billingSource = file_get_contents(__DIR__ . '/../private/classes/BillingFoundation.php');
$checkoutSource = file_get_contents(__DIR__ . '/../public/accounts/checkout.php');
stripeP2Assert(is_string($stripeSource) && is_string($billingSource) && is_string($checkoutSource), 'P2 billing sources must be readable.');
stripeP2Assert(str_contains($stripeSource, "'Idempotency-Key: '"), 'Every Stripe POST must carry the supported idempotency header.');
stripeP2Assert(str_contains($stripeSource, "if (\$method === 'POST')"), 'Idempotency enforcement must apply to all provider mutations.');
stripeP2Assert(str_contains($billingSource, 'INSERT IGNORE INTO stripe_webhook_events'), 'Concurrent webhook duplicates must use an atomic claim.');
stripeP2Assert(str_contains($billingSource, "if (\$status === 'processing')"), 'A processing webhook must not be reclaimed concurrently.');
stripeP2Assert(str_contains($billingSource, 'INTERVAL 5 MINUTE'), 'An abandoned webhook processing lease must be recoverable.');
stripeP2Assert(str_contains($billingSource, "if (\$status !== 'failed')"), 'Only failed webhook events may be reclaimed.');
stripeP2Assert(str_contains($billingSource, 'ON DUPLICATE KEY UPDATE'), 'Invoice recording must be replay-idempotent.');
stripeP2Assert(str_contains($billingSource, "status = \\'paid\\' AND VALUES(status) = \\'failed\\'"), 'A late failure must not regress a paid invoice.');
stripeP2Assert(str_contains($stripeSource, 'statusWithoutCheckoutRegression'), 'Late Checkout completion must use a non-regression guard.');
stripeP2Assert(str_contains($stripeSource, 'reconcileCurrentSubscription'), 'Invoice and subscription events must reconcile current provider state.');
stripeP2Assert(str_contains($stripeSource, "apiRequest('GET', '/subscriptions/'"), 'Webhook lifecycle reconciliation must retrieve the current Stripe subscription.');
stripeP2Assert(!str_contains($checkoutSource, 'createCheckoutSession($user, $business, $subscription);\n        header'), 'Checkout GET must not directly create a provider session.');
stripeP2Assert(str_contains($checkoutSource, "\$isPost") && str_contains($checkoutSource, 'Csrf::requireValid'), 'Checkout mutation must be POST-only with CSRF.');

echo "Stripe billing P2 tests passed.\n";
