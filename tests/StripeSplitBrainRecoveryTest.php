<?php

declare(strict_types=1);

final class StripeSplitBrainStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private StripeSplitBrainConnection $connection, private string $query)
    {
    }

    public function execute(?array $params = null): bool
    {
        [$this->rows, $this->affected] = $this->connection->executeQuery($this->query, $params ?? []);
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class StripeSplitBrainConnection extends PDO
{
    public array $subscriptions = [];

    public function __construct(array $subscription)
    {
        $this->subscriptions[(int) $subscription['id']] = $subscription;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new StripeSplitBrainStatement($this, $query);
    }

    public function executeQuery(string $query, array $params): array
    {
        if (str_starts_with(ltrim($query), 'UPDATE subscriptions')) {
            $id = (int) $params['subscription_id'];
            foreach ($params as $key => $value) {
                if ($key !== 'subscription_id') {
                    $this->subscriptions[$id][$key] = $value;
                }
            }
            return [[], 1];
        }
        if (str_contains($query, 'FROM subscriptions s') && str_contains($query, 's.id = :subscription_id')) {
            $subscription = $this->subscriptions[(int) $params['subscription_id']] ?? null;
            return [$subscription === null ? [] : [$subscription], 0];
        }
        throw new RuntimeException('Unexpected split-brain test SQL.');
    }
}

function splitBrainAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function splitBrainSubscription(bool $old = false): array
{
    $assignedAt = gmdate('Y-m-d H:i:s', time() - ($old ? 172800 : 300));
    return [
        'id' => 100,
        'business_id' => 10,
        'product_key' => '247sp',
        'commercial_terms_id' => 300,
        'allocation_id' => 200,
        'configuration_version' => 1,
        'cohort_key' => 'beta',
        'setup_fee' => '0.00',
        'monthly_fee' => '97.00',
        'free_introductory_months' => 0,
        'locked_stripe_recurring_price_ref' => 'price_BetaRecurring1',
        'locked_stripe_setup_price_ref' => null,
        'pricing_assigned_at' => $assignedAt,
        'business_signup_completed_at' => $assignedAt,
        'introductory_period_expires_at' => null,
        'recurring_billing_starts_at' => $assignedAt,
        'status' => 'trial',
        'payment_method_status' => 'not_on_file',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'stripe_checkout_session_id' => null,
        'stripe_latest_invoice_id' => null,
        'current_period_start' => null,
        'current_period_end' => null,
    ];
}

function splitBrainMetadata(array $subscription): array
{
    return [
        'business_id' => (string) $subscription['business_id'],
        'subscription_id' => (string) $subscription['id'],
        'allocation_id' => (string) $subscription['allocation_id'],
        'product_key' => '247sp',
        'configuration_version' => (string) $subscription['configuration_version'],
    ];
}

function splitBrainCheckout(
    string $id,
    string $customerId,
    array $subscription,
    string $status,
    int $created = 0
): array {
    return [
        'id' => $id,
        'object' => 'checkout.session',
        'customer' => $customerId,
        'metadata' => splitBrainMetadata($subscription),
        'status' => $status,
        'url' => $status === 'open' ? 'https://checkout.stripe.com/c/pay/' . $id : null,
        'payment_status' => $status === 'complete' ? 'paid' : 'unpaid',
        'subscription' => $status === 'complete' ? 'sub_complete1' : null,
        'created' => $created > 0 ? $created : time(),
    ];
}

require_once __DIR__ . '/../private/classes/StripeBilling.php';

$config = new ReflectionProperty(Database::class, 'config');
$config->setValue(null, [
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
]);

$database = new ReflectionProperty(Database::class, 'connection');
$transportProperty = new ReflectionProperty(StripeBilling::class, 'providerTransport');
$user = ['id' => 1, 'email' => 'owner@example.test'];
$business = ['id' => 10, 'business_name' => 'Example'];

$run = static function (array $subscription, callable $provider, array &$requests) use ($database, $transportProperty, $user, $business): array {
    $connection = new StripeSplitBrainConnection($subscription);
    $database->setValue(null, $connection);
    $requests = [];
    $transportProperty->setValue(null, static function (
        string $method,
        string $path,
        array $params,
        ?string $idempotencyKey
    ) use ($provider, &$requests): array {
        $request = compact('method', 'path', 'params', 'idempotencyKey');
        $requests[] = $request;
        return $provider($request);
    });
    $result = StripeBilling::createCheckoutSession($user, $business, $subscription);
    return [$result, $connection];
};

$subscription = splitBrainSubscription();
$metadata = splitBrainMetadata($subscription);
$requests = [];
[$result, $connection] = $run($subscription, static function (array $request) use ($subscription, $metadata): array {
    if ($request['path'] === '/customers/search') {
        return ['data' => [['id' => 'cus_recovered1', 'object' => 'customer', 'metadata' => $metadata]], 'has_more' => false];
    }
    if ($request['path'] === '/checkout/sessions') {
        return ['data' => [splitBrainCheckout('cs_test_open1', 'cus_recovered1', $subscription, 'open')], 'has_more' => false];
    }
    throw new RuntimeException('Unexpected provider request.');
}, $requests);
splitBrainAssert($result['id'] === 'cs_test_open1' && !empty($result['ubo_reused']), 'Exact Customer and open Checkout metadata must be recovered.');
splitBrainAssert($connection->subscriptions[100]['stripe_customer_id'] === 'cus_recovered1', 'Recovered Customer ID must be persisted.');
splitBrainAssert($connection->subscriptions[100]['stripe_checkout_session_id'] === 'cs_test_open1', 'Recovered Checkout ID must be persisted.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Recovery must not create a duplicate provider object.');
splitBrainAssert(
    str_contains((string) $requests[0]['params']['query'], "metadata['allocation_id']:'200'")
        && str_contains((string) $requests[0]['params']['query'], "metadata['configuration_version']:'1'"),
    'Customer reconciliation search must use immutable allocation and configuration metadata.'
);

$subscription = splitBrainSubscription();
$metadata = splitBrainMetadata($subscription);
$connection = new StripeSplitBrainConnection($subscription);
$database->setValue(null, $connection);
$requests = [];
$transportProperty->setValue(null, static function (string $method, string $path, array $params, ?string $key) use (&$requests, $metadata): array {
    $requests[] = compact('method', 'path', 'params', 'key');
    return ['data' => [
        ['id' => 'cus_conflict1', 'object' => 'customer', 'metadata' => $metadata],
        ['id' => 'cus_conflict2', 'object' => 'customer', 'metadata' => $metadata],
    ], 'has_more' => false];
});
$ambiguousCustomerFailed = false;
try {
    StripeBilling::createCheckoutSession($user, $business, $subscription);
} catch (RuntimeException $exception) {
    $ambiguousCustomerFailed = true;
}
splitBrainAssert($ambiguousCustomerFailed, 'Multiple exact Customers must fail safe.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Ambiguous Customer state must not POST create.');

$subscription = splitBrainSubscription(true);
$connection = new StripeSplitBrainConnection($subscription);
$database->setValue(null, $connection);
$requests = [];
$transportProperty->setValue(null, static function (string $method, string $path, array $params, ?string $key) use (&$requests): array {
    $requests[] = compact('method', 'path', 'params', 'key');
    return ['data' => [], 'has_more' => false];
});
$oldCustomerFailed = false;
try {
    StripeBilling::createCheckoutSession($user, $business, $subscription);
} catch (RuntimeException $exception) {
    $oldCustomerFailed = true;
}
splitBrainAssert($oldCustomerFailed, 'An old unresolved Customer search miss must fail safe.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Old inconclusive Customer state must not POST create.');

$subscription = splitBrainSubscription();
$requests = [];
[$result, $connection] = $run($subscription, static function (array $request) use ($subscription): array {
    if ($request['path'] === '/customers/search') {
        return ['data' => [], 'has_more' => false];
    }
    if ($request['method'] === 'POST' && $request['path'] === '/customers') {
        return ['id' => 'cus_immediate1', 'object' => 'customer', 'metadata' => splitBrainMetadata($subscription)];
    }
    if ($request['method'] === 'POST' && $request['path'] === '/checkout/sessions') {
        return splitBrainCheckout('cs_test_immediate1', 'cus_immediate1', $subscription, 'open');
    }
    if ($request['path'] === '/checkout/sessions') {
        return ['data' => [], 'has_more' => false];
    }
    throw new RuntimeException('Unexpected provider request.');
}, $requests);
$posts = array_values(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST'));
splitBrainAssert(count($posts) === 2, 'An immediate clean attempt may create one Customer and one Checkout Session.');
splitBrainAssert(
    $posts[0]['idempotencyKey'] === StripeBilling::providerOperationIdentity($subscription, 'customer_create')
        && $posts[1]['idempotencyKey'] === StripeBilling::providerOperationIdentity($subscription, 'checkout_session'),
    'Immediate provider creates must retain deterministic idempotency identities.'
);

$subscription = splitBrainSubscription();
$subscription['stripe_customer_id'] = 'cus_known1';
$requests = [];
[$result, $connection] = $run($subscription, static function (array $request) use ($subscription): array {
    if ($request['path'] === '/checkout/sessions') {
        return ['data' => [splitBrainCheckout('cs_test_complete1', 'cus_known1', $subscription, 'complete')], 'has_more' => false];
    }
    throw new RuntimeException('Unexpected provider request.');
}, $requests);
splitBrainAssert(!empty($result['ubo_already_complete']), 'A matching complete Checkout must reconcile instead of creating another Session.');
splitBrainAssert($connection->subscriptions[100]['status'] === 'active', 'Recovered complete Checkout must reconcile local lifecycle state.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Complete Checkout recovery must not POST create.');

$subscription = splitBrainSubscription();
$subscription['stripe_customer_id'] = 'cus_known2';
$connection = new StripeSplitBrainConnection($subscription);
$database->setValue(null, $connection);
$requests = [];
$transportProperty->setValue(null, static function (string $method, string $path, array $params, ?string $key) use (&$requests, $subscription): array {
    $requests[] = compact('method', 'path', 'params', 'key');
    return ['data' => [
        splitBrainCheckout('cs_test_conflict1', 'cus_known2', $subscription, 'open'),
        splitBrainCheckout('cs_test_conflict2', 'cus_known2', $subscription, 'complete'),
    ], 'has_more' => false];
});
$ambiguousSessionFailed = false;
try {
    StripeBilling::createCheckoutSession($user, $business, $subscription);
} catch (RuntimeException $exception) {
    $ambiguousSessionFailed = true;
}
splitBrainAssert($ambiguousSessionFailed, 'Multiple actionable matching Checkout Sessions must fail safe.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Ambiguous Checkout state must not POST create.');

$subscription = splitBrainSubscription(true);
$subscription['stripe_customer_id'] = 'cus_knownexpired';
$subscription['stripe_checkout_session_id'] = 'cs_test_localexpired1';
$requests = [];
[$result, $connection] = $run($subscription, static function (array $request) use ($subscription): array {
    if ($request['path'] === '/checkout/sessions/cs_test_localexpired1') {
        return splitBrainCheckout('cs_test_localexpired1', 'cus_knownexpired', $subscription, 'expired', time() - 172800);
    }
    if ($request['path'] === '/checkout/sessions') {
        return ['data' => [splitBrainCheckout('cs_test_lateopen1', 'cus_knownexpired', $subscription, 'open')], 'has_more' => false];
    }
    throw new RuntimeException('Unexpected provider request.');
}, $requests);
splitBrainAssert($result['id'] === 'cs_test_lateopen1' && !empty($result['ubo_reused']), 'An expired local predecessor must discover a later provider Session after local persistence failure.');
splitBrainAssert($connection->subscriptions[100]['stripe_checkout_session_id'] === 'cs_test_lateopen1', 'Delayed replacement recovery must repair the local Checkout reference.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Delayed replacement recovery must not rely on an expired idempotency key.');

$subscription = splitBrainSubscription(true);
$subscription['stripe_customer_id'] = 'cus_known3';
$requests = [];
[$result, $connection] = $run($subscription, static function (array $request) use ($subscription): array {
    if ($request['method'] === 'POST' && $request['path'] === '/checkout/sessions') {
        return splitBrainCheckout('cs_test_replacement1', 'cus_known3', $subscription, 'open');
    }
    if ($request['path'] === '/checkout/sessions') {
        return ['data' => [splitBrainCheckout('cs_test_expired1', 'cus_known3', $subscription, 'expired', time() - 86400)], 'has_more' => false];
    }
    throw new RuntimeException('Unexpected provider request.');
}, $requests);
$checkoutPost = array_values(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST'))[0];
splitBrainAssert(
    $checkoutPost['idempotencyKey'] === StripeBilling::providerOperationIdentity($subscription, 'checkout_session', 'cs_test_expired1'),
    'Expired Session recovery must retain deterministic predecessor replacement identity.'
);
splitBrainAssert($result['id'] === 'cs_test_replacement1', 'Expired predecessor may create one deterministic replacement.');

$subscription = splitBrainSubscription(true);
$subscription['stripe_customer_id'] = 'cus_known4';
$connection = new StripeSplitBrainConnection($subscription);
$database->setValue(null, $connection);
$requests = [];
$transportProperty->setValue(null, static function (string $method, string $path, array $params, ?string $key) use (&$requests): array {
    $requests[] = compact('method', 'path', 'params', 'key');
    return ['data' => [], 'has_more' => false];
});
$oldSessionFailed = false;
try {
    StripeBilling::createCheckoutSession($user, $business, $subscription);
} catch (RuntimeException $exception) {
    $oldSessionFailed = true;
}
splitBrainAssert($oldSessionFailed, 'An old unresolved Checkout list miss must fail safe.');
splitBrainAssert(count(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST')) === 0, 'Old inconclusive Checkout state must not POST create.');

foreach ($requests as $request) {
    if ($request['method'] === 'GET') {
        splitBrainAssert(($request['idempotencyKey'] ?? $request['key'] ?? null) === null, 'Provider reconciliation GETs must not use idempotency keys.');
    }
}
$transportProperty->setValue(null, null);

echo "Stripe split-brain recovery tests passed.\n";
