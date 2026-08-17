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

$connection->subscriptions[100] = $subscriptionFixture(100, 'pending_payment', 'pending');
$invoice = [
    'id' => 'in_order_guard',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[100]),
    'amount_paid' => 14700,
    'amount_due' => 14700,
    'customer' => 'cus_order_guard',
    'subscription' => 'sub_order_guard',
];
[$payload, $signature] = $signed('evt_invoice_paid', 'invoice.payment_succeeded', $invoice);
StripeBilling::handleWebhook($payload, $signature);
[$payload, $signature] = $signed('evt_invoice_late_failure', 'invoice.payment_failed', $invoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->payments['in_order_guard']['status'] === 'paid', 'A late failure must not regress a paid invoice row.');
stripeWebhookAssert($connection->subscriptions[100]['status'] === 'active', 'A late failure must not regress an active subscription backed by a paid invoice.');

$introExpires = gmdate('Y-m-d H:i:s', time() + (30 * 86400));
$connection->subscriptions[101] = $subscriptionFixture(101, 'trial', 'complete', 6, $introExpires);
$alphaInvoice = [
    'id' => 'in_alpha_zero',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[101]),
    'amount_paid' => 0,
    'amount_due' => 0,
];
[$payload, $signature] = $signed('evt_alpha_zero', 'invoice.payment_succeeded', $alphaInvoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[101]['status'] === 'trial', 'A current Alpha zero-dollar invoice must remain trial.');
stripeWebhookAssert($connection->subscriptions[101]['payment_method_status'] === 'complete', 'Alpha must retain its collected payment method during the free period.');

$connection->subscriptions[102] = $subscriptionFixture(102, 'cancelled', 'cancelled');
$cancelledInvoice = [
    'id' => 'in_cancelled_late',
    'object' => 'invoice',
    'metadata' => $metadata($connection->subscriptions[102]),
    'amount_paid' => 19700,
    'amount_due' => 19700,
];
[$payload, $signature] = $signed('evt_cancelled_late_invoice', 'invoice.payment_succeeded', $cancelledInvoice);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[102]['status'] === 'cancelled', 'A late paid invoice must not reopen a cancelled subscription.');
stripeWebhookAssert($connection->subscriptions[102]['payment_method_status'] === 'cancelled', 'A cancelled subscription must retain cancelled payment state.');

$storedPeriodEnd = gmdate('Y-m-d H:i:s', time() + (60 * 86400));
$connection->subscriptions[103] = $subscriptionFixture(103, 'active', 'complete', 0, null, $storedPeriodEnd);
$staleSubscription = [
    'id' => 'sub_stale_period',
    'object' => 'subscription',
    'metadata' => $metadata($connection->subscriptions[103]),
    'status' => 'past_due',
    'current_period_start' => time() - 86400,
    'current_period_end' => time() + 86400,
];
[$payload, $signature] = $signed('evt_stale_subscription', 'customer.subscription.updated', $staleSubscription);
StripeBilling::handleWebhook($payload, $signature);
stripeWebhookAssert($connection->subscriptions[103]['status'] === 'active', 'An older subscription period must not regress current status.');
stripeWebhookAssert($connection->subscriptions[103]['current_period_end'] === $storedPeriodEnd, 'An older subscription period must not replace the stored period.');

$invalidRejected = false;
try {
    StripeBilling::handleWebhook($payload, 't=' . time() . ',v1=invalid');
} catch (RuntimeException $exception) {
    $invalidRejected = true;
}
stripeWebhookAssert($invalidRejected, 'Invalid Stripe signatures must remain rejected.');

echo "Stripe webhook P2 tests passed.\n";
