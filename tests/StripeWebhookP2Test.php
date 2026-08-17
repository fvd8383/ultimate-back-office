<?php

declare(strict_types=1);

final class StripeWebhookTestStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private StripeWebhookTestConnection $connection, private string $query)
    {
    }

    public function execute(?array $params = null): bool
    {
        [$this->rows, $this->affected] = $this->connection->executeQuery($this->query, $params ?? []);
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
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

final class StripeWebhookTestConnection extends PDO
{
    public array $events = [];
    public array $subscriptions = [];
    public array $payments = [];
    private bool $transaction = false;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new StripeWebhookTestStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function executeQuery(string $query, array $params): array
    {
        if (str_contains($query, 'INSERT IGNORE INTO stripe_webhook_events')) {
            $id = (string) $params['event_id'];
            if (isset($this->events[$id])) {
                return [[], 0];
            }
            $this->events[$id] = ['status' => 'processing', 'event_type' => $params['event_type']];
            return [[], 1];
        }
        if (str_contains($query, 'SELECT status') && str_contains($query, 'stripe_webhook_events')) {
            $status = $this->events[(string) $params['event_id']]['status'] ?? null;
            return [$status === null ? [] : [['status' => $status]], 0];
        }
        if (str_contains($query, 'COALESCE(updated_at, created_at)')) {
            $id = (string) $params['event_id'];
            if (($this->events[$id]['status'] ?? '') !== 'processing' || empty($this->events[$id]['stale'])) {
                return [[], 0];
            }
            $this->events[$id]['stale'] = false;
            return [[], 1];
        }
        if (str_contains($query, 'AND status = :failed_status')) {
            $id = (string) $params['event_id'];
            if (($this->events[$id]['status'] ?? '') !== 'failed') {
                return [[], 0];
            }
            $this->events[$id]['status'] = 'processing';
            return [[], 1];
        }
        if (str_contains($query, 'processed_at = IF')) {
            $id = (string) $params['event_id'];
            $this->events[$id]['status'] = (string) $params['status'];
            return [[], 1];
        }
        if (str_contains($query, 'FROM subscriptions s') && str_contains($query, 's.id = :subscription_id')) {
            $subscription = $this->subscriptions[(int) $params['subscription_id']] ?? null;
            return [$subscription === null ? [] : [$subscription], 0];
        }
        if (str_starts_with(ltrim($query), 'UPDATE subscriptions')) {
            $id = (int) $params['subscription_id'];
            if (!isset($this->subscriptions[$id])) {
                return [[], 0];
            }
            foreach ($params as $key => $value) {
                if ($key !== 'subscription_id') {
                    $this->subscriptions[$id][$key] = $value;
                }
            }
            return [[], 1];
        }
        if (str_contains($query, 'INSERT INTO payments') && str_contains($query, 'ON DUPLICATE KEY UPDATE')) {
            $invoiceId = (string) $params['stripe_invoice_id'];
            $existing = $this->payments[$invoiceId] ?? null;
            $status = (string) $params['status'];
            if (is_array($existing) && $existing['status'] === 'paid' && $status === 'failed') {
                $status = 'paid';
            }
            $this->payments[$invoiceId] = $params + ['status' => $status];
            $this->payments[$invoiceId]['status'] = $status;
            return [[], 1];
        }
        if (str_contains($query, 'FROM payments') && str_contains($query, 'stripe_invoice_id = :stripe_invoice_id')) {
            $payment = $this->payments[(string) $params['stripe_invoice_id']] ?? null;
            return [$payment === null ? [] : [['status' => $payment['status']]], 0];
        }
        throw new RuntimeException('Unexpected webhook test SQL.');
    }
}

function stripeWebhookAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../private/classes/StripeBilling.php';

$secret = 'whsec_test_value';
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
    'STRIPE_WEBHOOK_SECRET' => $secret,
]);
$connection = new StripeWebhookTestConnection();
$database = new ReflectionProperty(Database::class, 'connection');
$database->setValue(null, $connection);
$providerSubscriptions = [];
$providerUnavailable = [];
$providerRequests = [];
$providerTransport = new ReflectionProperty(StripeBilling::class, 'providerTransport');
$providerTransport->setValue(null, static function (
    string $method,
    string $path,
    array $params,
    ?string $idempotencyKey
) use (&$providerSubscriptions, &$providerUnavailable, &$providerRequests): array {
    $providerRequests[] = compact('method', 'path', 'params', 'idempotencyKey');
    if ($method !== 'GET' || !str_starts_with($path, '/subscriptions/')) {
        throw new RuntimeException('Unexpected provider request in webhook test.');
    }
    $id = rawurldecode(substr($path, strlen('/subscriptions/')));
    if (!empty($providerUnavailable[$id])) {
        throw new StripeProviderException('Provider unavailable.');
    }
    if (!isset($providerSubscriptions[$id])) {
        throw new StripeProviderException('Subscription not found.', 404);
    }
    return $providerSubscriptions[$id];
});

$signed = static function (string $eventId, string $eventType = 'customer.created', array $object = []) use ($secret): array {
    $payload = json_encode([
        'id' => $eventId,
        'type' => $eventType,
        'data' => ['object' => $object],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    return [$payload, 't=' . $timestamp . ',v1=' . $signature];
};

[$payload, $signature] = $signed('evt_new');
$first = StripeBilling::handleWebhook($payload, $signature);
$replay = StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($first['status'] === 'processed', 'A valid signed event must process.');
stripeWebhookAssert($replay['status'] === 'already_processed', 'A processed replay must be harmless.');

$connection->events['evt_processing'] = ['status' => 'processing', 'event_type' => 'customer.created'];
[$payload, $signature] = $signed('evt_processing');
$processingRejected = false;
try {
    StripeBilling::handleWebhook($payload, $signature);
} catch (RuntimeException $exception) {
    $processingRejected = true;
}
stripeWebhookAssert($processingRejected, 'A concurrent processing duplicate must request a later retry without running twice.');

$connection->events['evt_stale'] = ['status' => 'processing', 'event_type' => 'customer.created', 'stale' => true];
[$payload, $signature] = $signed('evt_stale');
$stale = StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($stale['status'] === 'processed', 'An abandoned processing lease must be reclaimable.');

$connection->events['evt_failed'] = ['status' => 'failed', 'event_type' => 'customer.created'];
[$payload, $signature] = $signed('evt_failed');
$retried = StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($retried['status'] === 'processed', 'A previously failed event must be reclaimable.');

$subscriptionFixture = static function (
    int $id,
    string $status,
    string $paymentMethodStatus,
    int $freeMonths = 0,
    ?string $introExpires = null,
    ?string $periodEnd = null
): array {
    return [
        'id' => $id,
        'business_id' => $id - 90,
        'product_key' => '247sp',
        'commercial_terms_id' => $id + 200,
        'allocation_id' => $id + 100,
        'configuration_version' => 1,
        'status' => $status,
        'payment_method_status' => $paymentMethodStatus,
        'free_introductory_months' => $freeMonths,
        'introductory_period_expires_at' => $introExpires,
        'current_period_end' => $periodEnd,
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'stripe_checkout_session_id' => null,
        'stripe_latest_invoice_id' => null,
    ];
};
$metadata = static fn (array $subscription): array => [
    'subscription_id' => (string) $subscription['id'],
    'business_id' => (string) $subscription['business_id'],
    'allocation_id' => (string) $subscription['allocation_id'],
    'product_key' => '247sp',
    'configuration_version' => (string) $subscription['configuration_version'],
];
$currentProviderSubscription = static function (
    array $subscription,
    string $providerId,
    string $status,
    int $periodEnd,
    ?string $defaultPaymentMethod = 'pm_current'
) use ($metadata): array {
    return [
        'id' => $providerId,
        'object' => 'subscription',
        'metadata' => $metadata($subscription),
        'status' => $status,
        'customer' => 'cus_current' . $subscription['id'],
        'latest_invoice' => 'in_current' . $subscription['id'],
        'default_payment_method' => $defaultPaymentMethod,
        'current_period_start' => $periodEnd - (30 * 86400),
        'current_period_end' => $periodEnd,
        'cancel_at_period_end' => false,
    ];
};

$connection->subscriptions[100] = $subscriptionFixture(100, 'pending_payment', 'pending');
$providerSubscriptions['sub_orderguard'] = $currentProviderSubscription(
    $connection->subscriptions[100],
    'sub_orderguard',
    'active',
    time() + (30 * 86400)
);
$invoice = [
    'id' => 'in_order_guard',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[100]),
    'amount_paid' => 14700,
    'amount_due' => 14700,
    'customer' => 'cus_order_guard',
    'subscription' => 'sub_orderguard',
];
[$payload, $signature] = $signed('evt_invoice_paid', 'invoice.payment_succeeded', $invoice);
StripeBilling::handleWebhook($payload, $signature);
[$payload, $signature] = $signed('evt_invoice_late_failure', 'invoice.payment_failed', $invoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->payments['in_order_guard']['status'] === 'paid', 'A late failure must not regress a paid invoice row.');
stripeWebhookAssert($connection->subscriptions[100]['status'] === 'active', 'Current provider state must keep the subscription active after a late failure.');

$oldFailedInvoice = $invoice;
$oldFailedInvoice['id'] = 'in_old_failure';
[$payload, $signature] = $signed('evt_distinct_old_failure', 'invoice.payment_failed', $oldFailedInvoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->payments['in_old_failure']['status'] === 'failed', 'A distinct failed invoice must retain its own payment evidence.');
stripeWebhookAssert($connection->subscriptions[100]['status'] === 'active', 'A distinct old failed invoice must not override current active provider state.');

$introExpires = gmdate('Y-m-d H:i:s', time() + (30 * 86400));
$connection->subscriptions[101] = $subscriptionFixture(101, 'trial', 'complete', 6, $introExpires);
$providerSubscriptions['sub_alphazero'] = $currentProviderSubscription(
    $connection->subscriptions[101],
    'sub_alphazero',
    'trialing',
    time() + (30 * 86400),
    null
);
$alphaInvoice = [
    'id' => 'in_alpha_zero',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[101]),
    'amount_paid' => 0,
    'amount_due' => 0,
    'subscription' => 'sub_alphazero',
];
[$payload, $signature] = $signed('evt_alpha_zero', 'invoice.payment_succeeded', $alphaInvoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[101]['status'] === 'trial', 'A current Alpha zero-dollar invoice must remain trial.');
stripeWebhookAssert($connection->subscriptions[101]['payment_method_status'] === 'complete', 'Alpha must retain its collected payment method during the free period.');

$connection->subscriptions[102] = $subscriptionFixture(102, 'cancelled', 'cancelled');
$providerSubscriptions['sub_cancelledlate'] = $currentProviderSubscription(
    $connection->subscriptions[102],
    'sub_cancelledlate',
    'active',
    time() + (30 * 86400)
);
$cancelledInvoice = [
    'id' => 'in_cancelled_late',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[102]),
    'amount_paid' => 19700,
    'amount_due' => 19700,
    'subscription' => 'sub_cancelledlate',
];
[$payload, $signature] = $signed('evt_cancelled_late_invoice', 'invoice.payment_succeeded', $cancelledInvoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[102]['status'] === 'cancelled', 'A late paid invoice must not reopen a cancelled subscription.');
stripeWebhookAssert($connection->subscriptions[102]['payment_method_status'] === 'cancelled', 'A cancelled subscription must retain cancelled payment state.');

$samePeriodEnd = time() + (60 * 86400);
$storedPeriodEnd = gmdate('Y-m-d H:i:s', $samePeriodEnd);
$connection->subscriptions[103] = $subscriptionFixture(103, 'active', 'complete', 0, null, $storedPeriodEnd);
$providerSubscriptions['sub_sameperiod'] = $currentProviderSubscription(
    $connection->subscriptions[103],
    'sub_sameperiod',
    'active',
    $samePeriodEnd
);
$staleSubscription = [
    'id' => 'sub_sameperiod',
    'object' => 'subscription',
    'metadata' => $metadata($connection->subscriptions[103]),
    'status' => 'past_due',
    'current_period_start' => time() - 86400,
    'current_period_end' => $samePeriodEnd,
];
[$payload, $signature] = $signed('evt_stale_subscription', 'customer.subscription.updated', $staleSubscription);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[103]['status'] === 'active', 'A stale same-period payload must yield current active provider status.');
stripeWebhookAssert($connection->subscriptions[103]['current_period_end'] === $storedPeriodEnd, 'Current same-period provider dates must remain authoritative.');

$connection->subscriptions[104] = $subscriptionFixture(104, 'active', 'complete');
$providerSubscriptions['sub_currentpastdue'] = $currentProviderSubscription(
    $connection->subscriptions[104],
    'sub_currentpastdue',
    'past_due',
    time() + (30 * 86400),
    null
);
$deliveredActive = $providerSubscriptions['sub_currentpastdue'];
$deliveredActive['status'] = 'active';
[$payload, $signature] = $signed('evt_delivered_active_current_past_due', 'customer.subscription.updated', $deliveredActive);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[104]['status'] === 'past_due', 'Current provider past-due state must override an older delivered active payload.');

$connection->subscriptions[105] = $subscriptionFixture(105, 'active', 'complete');
$providerUnavailable['sub_unavailable'] = true;
$unavailablePayload = [
    'id' => 'sub_unavailable',
    'object' => 'subscription',
    'metadata' => $metadata($connection->subscriptions[105]),
    'status' => 'past_due',
    'current_period_start' => time() - 86400,
    'current_period_end' => time() + (30 * 86400),
];
[$payload, $signature] = $signed('evt_provider_unavailable', 'customer.subscription.updated', $unavailablePayload);
$unavailableFailed = false;
try {
    StripeBilling::handleWebhook($payload, $signature);
} catch (StripeProviderException $exception) {
    $unavailableFailed = true;
}
stripeWebhookAssert($unavailableFailed, 'Provider retrieval failure must leave the webhook retryable.');
stripeWebhookAssert($connection->events['evt_provider_unavailable']['status'] === 'failed', 'Provider retrieval failure must mark the event failed.');
stripeWebhookAssert($connection->subscriptions[105]['status'] === 'active', 'Provider retrieval failure must not apply the delivered stale lifecycle state.');

$connection->subscriptions[106] = $subscriptionFixture(106, 'active', 'complete');
$providerUnavailable['sub_invoiceunavailable'] = true;
$unavailableInvoice = [
    'id' => 'in_provider_unavailable',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[106]),
    'amount_due' => 19700,
    'subscription' => 'sub_invoiceunavailable',
];
[$payload, $signature] = $signed('evt_invoice_provider_unavailable', 'invoice.payment_failed', $unavailableInvoice);
$unavailableInvoiceFailed = false;
try {
    StripeBilling::handleWebhook($payload, $signature);
} catch (StripeProviderException $exception) {
    $unavailableInvoiceFailed = true;
}
stripeWebhookAssert($unavailableInvoiceFailed, 'Invoice lifecycle reconciliation failure must remain retryable.');
stripeWebhookAssert($connection->payments['in_provider_unavailable']['status'] === 'failed', 'The individual invoice may be recorded idempotently before lifecycle reconciliation retries.');
stripeWebhookAssert($connection->subscriptions[106]['status'] === 'active', 'Failed invoice provider retrieval must not regress local lifecycle from the delivered payload.');
stripeWebhookAssert($connection->events['evt_invoice_provider_unavailable']['status'] === 'failed', 'Invoice provider retrieval failure must leave the event reclaimable.');
stripeWebhookAssert(count(array_filter($providerRequests, static fn (array $request): bool => $request['method'] === 'GET' && $request['idempotencyKey'] === null)) >= 1, 'Subscription reconciliation GETs must not carry idempotency keys.');

$invalidRejected = false;
try {
    StripeBilling::handleWebhook($payload, 't=' . time() . ',v1=invalid');
} catch (RuntimeException $exception) {
    $invalidRejected = true;
}
stripeWebhookAssert($invalidRejected, 'Invalid Stripe signatures must remain rejected.');

echo "Stripe webhook P2 tests passed.\n";
