<?php

declare(strict_types=1);

error_reporting(E_ALL);

final class CompletedSignupStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private CompletedSignupConnection $connection, public readonly string $query)
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

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->rows;
        $this->rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class CompletedSignupConnection extends PDO
{
    public array $businesses = [];
    public array $subscriptions = [];
    public array $cohorts = [];
    public array $allocations = [];
    public array $terms = [];
    public array $activities = [];
    public int $counter = 1;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    public bool $active247spModule = true;
    public ?string $failOn = null;
    private bool $transaction = false;
    private array $backup = [];
    private int $lastId = 0;

    public function __construct()
    {
    }

    public static function fixture(): self
    {
        $connection = new self();
        $connection->businesses[10] = [
            'id' => 10,
            'user_id' => 1,
            'status' => 'active',
            'is_suspended' => 0,
            'setup_status' => 'incomplete',
            'setup_step' => 'confirmation',
        ];
        $connection->subscriptions[100] = self::subscription(100, 10);
        $connection->cohorts = [
            self::cohort(1, 'alpha', 1, 5, '0.00', '79.00', 6, null),
            self::cohort(2, 'beta', 6, 10, '0.00', '97.00', 0, null),
            self::cohort(3, 'founding', 11, 25, '100.00', '147.00', 0, 'price_FoundingSetup1'),
            self::cohort(4, 'standard', 26, null, '250.00', '197.00', 0, 'price_StandardSetup1'),
        ];
        return $connection;
    }

    public static function subscription(int $id, int $businessId): array
    {
        return [
            'id' => $id,
            'subscription_id' => $id,
            'business_id' => $businessId,
            'plan_id' => 1,
            'status' => 'trial',
            'subscription_status' => 'trial',
            'product_key' => '247sp',
            'plan_active' => 1,
            'business_status' => 'active',
            'is_suspended' => 0,
            'product_access_active' => 1,
        ];
    }

    private static function cohort(
        int $id,
        string $key,
        int $start,
        ?int $end,
        string $setup,
        string $monthly,
        int $months,
        ?string $setupRef
    ): array {
        return [
            'id' => $id,
            'plan_id' => 1,
            'cohort_key' => $key,
            'display_name' => ucfirst($key),
            'position_start' => $start,
            'position_end' => $end,
            'setup_fee' => $setup,
            'monthly_fee' => $monthly,
            'currency' => 'USD',
            'free_introductory_months' => $months,
            'effective_from' => '2026-08-01 00:00:00',
            'effective_until' => null,
            'version' => 1,
            'is_active' => 1,
            'stripe_recurring_price_ref' => 'price_' . ucfirst($key) . 'Recurring1',
            'stripe_setup_price_ref' => $setupRef,
        ];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new CompletedSignupStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('Nested transaction.');
        }
        $this->backup = [
            'businesses' => $this->businesses,
            'counter' => $this->counter,
            'allocations' => $this->allocations,
            'terms' => $this->terms,
            'activities' => $this->activities,
            'last_id' => $this->lastId,
        ];
        $this->transaction = true;
        $this->beginCount++;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        $this->backup = [];
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        $this->businesses = $this->backup['businesses'];
        $this->counter = $this->backup['counter'];
        $this->allocations = $this->backup['allocations'];
        $this->terms = $this->backup['terms'];
        $this->activities = $this->backup['activities'];
        $this->lastId = $this->backup['last_id'];
        $this->transaction = false;
        $this->backup = [];
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function executeQuery(string $query, array $params): array
    {
        if (str_contains($query, 'onboarding:lock_business')) {
            $business = $this->businesses[(int) $params['business_id']] ?? null;
            if ($business === null || (int) $business['user_id'] !== (int) $params['user_id']) {
                return [[], 0];
            }
            return [[$business], 0];
        }
        if (str_contains($query, 'onboarding:lock_247sp_subscription')) {
            $rows = array_values(array_filter(
                $this->subscriptions,
                static fn (array $row): bool => (int) $row['business_id'] === (int) $params['business_id']
            ));
            if ($rows === [] && $this->active247spModule) {
                $rows[] = [
                    'id' => null,
                    'business_id' => (int) $params['business_id'],
                    'plan_id' => 1,
                    'status' => null,
                    'product_key' => '247sp',
                ];
            }
            return [$rows, 0];
        }
        if (str_contains($query, 'pricing:load_subscription')) {
            $row = $this->subscriptions[(int) $params['subscription_id']] ?? null;
            return [$row === null ? [] : [$row], 0];
        }
        if (str_contains($query, 'pricing:existing_assignment')) {
            foreach ($this->allocations as $allocation) {
                $matches = isset($params['idempotency_key'])
                    ? $allocation['completed_signup_idempotency_key'] === $params['idempotency_key']
                    : (int) $allocation['subscription_id'] === (int) $params['subscription_id'];
                if ($matches) {
                    return [[$this->joined($allocation)], 0];
                }
            }
            return [[], 0];
        }
        if (str_contains($query, 'pricing:lock_counter')) {
            return [[['plan_id' => 1, 'next_sequence_number' => $this->counter, 'lock_version' => 0]], 0];
        }
        if (str_contains($query, 'pricing:effective_cohorts')) {
            return [$this->cohorts, 0];
        }
        if (str_contains($query, 'pricing:insert_allocation')) {
            $this->fail('pricing_allocation');
            $this->lastId++;
            $params['id'] = $this->lastId;
            $this->allocations[$this->lastId] = $params;
            return [[], 1];
        }
        if (str_contains($query, 'pricing:insert_terms')) {
            $this->terms[(int) $params['allocation_id']] = $params;
            return [[], 1];
        }
        if (str_contains($query, 'pricing:advance_counter')) {
            $this->counter++;
            return [[], 1];
        }
        if (str_contains($query, 'pricing:log_activity')) {
            $this->activities[] = ['type' => 'pricing', 'params' => $params];
            return [[], 1];
        }
        if (str_contains($query, 'onboarding:mark_complete')) {
            $this->fail('mark_complete');
            $this->businesses[(int) $params['business_id']]['setup_status'] = 'complete';
            $this->businesses[(int) $params['business_id']]['setup_step'] = 'completed';
            return [[], 1];
        }
        if (str_contains($query, 'INSERT INTO activity_logs')) {
            $this->activities[] = ['type' => 'onboarding', 'params' => $params];
            return [[], 1];
        }
        throw new RuntimeException('Unexpected SQL in completed-signup test.');
    }

    private function joined(array $allocation): array
    {
        $term = $this->terms[(int) $allocation['id']];
        $cohort = $this->cohorts[(int) $allocation['pricing_cohort_id'] - 1];
        return [
            'allocation_id' => $allocation['id'],
            'product_key' => '247sp',
            'cohort_key' => $cohort['cohort_key'],
            'display_name' => $cohort['display_name'],
        ] + $allocation + $term;
    }

    private function fail(string $stage): void
    {
        if ($this->failOn === $stage) {
            throw new RuntimeException('Injected local failure.');
        }
    }
}

function completedSignupAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function completedSignupUse(CompletedSignupConnection $connection): void
{
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $connection);
}

require_once __DIR__ . '/../private/classes/BusinessFoundation.php';

$connection = CompletedSignupConnection::fixture();
completedSignupUse($connection);
$result = BusinessFoundation::completeOnboarding(10, 1);
completedSignupAssert($connection->businesses[10]['setup_status'] === 'complete', 'Successful signup must complete the business.');
completedSignupAssert(count($connection->allocations) === 1 && $connection->counter === 2, 'Successful signup must allocate exactly one position.');
completedSignupAssert($connection->beginCount === 1 && $connection->commitCount === 1, 'Signup and pricing must share one owner transaction.');
completedSignupAssert($result['pricing_assignment']['cohort_key'] === 'alpha', 'The first completed signup must lock Alpha.');

$again = BusinessFoundation::completeOnboarding(10, 1);
completedSignupAssert($again['pricing_assignment']['allocation_id'] === $result['pricing_assignment']['allocation_id'], 'Retry must return the same assignment.');
completedSignupAssert($connection->counter === 2 && count($connection->activities) === 2, 'Retry must create no pricing or onboarding success duplicates.');
completedSignupAssert(
    BusinessFoundation::completedSignupIdempotencyIdentity(10, 100)
        === BusinessFoundation::completedSignupIdempotencyIdentity(10, 100),
    'Completed-signup identity must be stable.'
);

foreach (['pricing_allocation', 'mark_complete'] as $failure) {
    $connection = CompletedSignupConnection::fixture();
    $connection->failOn = $failure;
    completedSignupUse($connection);
    try {
        BusinessFoundation::completeOnboarding(10, 1);
        throw new RuntimeException('Expected completed-signup failure.');
    } catch (Throwable $exception) {
        completedSignupAssert($connection->businesses[10]['setup_status'] === 'incomplete', 'Failure must leave the business incomplete.');
        completedSignupAssert($connection->counter === 1 && $connection->allocations === [], 'Failure must consume no sequence.');
        completedSignupAssert($connection->activities === [], 'Failure must roll back success activity.');
    }
}

$connection = CompletedSignupConnection::fixture();
$connection->businesses[11] = $connection->businesses[10] + ['id' => 11];
$connection->businesses[11]['id'] = 11;
$connection->subscriptions[101] = CompletedSignupConnection::subscription(101, 11);
completedSignupUse($connection);
$first = BusinessFoundation::completeOnboarding(10, 1);
$second = BusinessFoundation::completeOnboarding(11, 1);
completedSignupAssert(
    [$first['pricing_assignment']['customer_sequence_number'], $second['pricing_assignment']['customer_sequence_number']] === [1, 2],
    'One owner completing independent businesses must consume independent positions.'
);

$connection = CompletedSignupConnection::fixture();
$connection->subscriptions = [];
$connection->active247spModule = false;
completedSignupUse($connection);
$non247 = BusinessFoundation::completeOnboarding(10, 1);
completedSignupAssert($non247['pricing_assignment'] === null && $connection->counter === 1, 'A non-247SP flow must not allocate 247SP pricing.');

$connection = CompletedSignupConnection::fixture();
$connection->subscriptions = [];
completedSignupUse($connection);
try {
    BusinessFoundation::completeOnboarding(10, 1);
    throw new RuntimeException('Expected an active 247SP module without a subscription to fail.');
} catch (Throwable $exception) {
    completedSignupAssert($connection->businesses[10]['setup_status'] === 'incomplete', 'An active 247SP module without a subscription must not complete signup.');
    completedSignupAssert($connection->counter === 1 && $connection->allocations === [], 'Broken local subscription state must consume no position.');
}

$businessSource = file_get_contents(__DIR__ . '/../private/classes/BusinessFoundation.php');
$routeSource = file_get_contents(__DIR__ . '/../public/accounts/business-create.php');
completedSignupAssert(is_string($businessSource) && is_string($routeSource), 'Completed-signup sources must be readable.');
completedSignupAssert(str_contains($businessSource, "'system_actor_key' => '247sp_completed_signup'"), 'The trusted system actor must be server constructed.');
completedSignupAssert(!preg_match('/\$_POST\[[^\]]*(?:actor|cohort|price|sequence|timestamp|idempotency)/i', $routeSource), 'Browser input must not supply pricing command fields.');

echo "Pricing P2 completed signup tests passed.\n";
