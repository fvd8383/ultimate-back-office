<?php

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

final class PricingCohortTestStatement extends PDOStatement
{
    private array $rows = [];
    private int $affectedRows = 0;

    public function __construct(
        private PricingCohortTestConnection $connection,
        public readonly string $query
    ) {
    }

    public function execute(?array $params = null): bool
    {
        [$this->rows, $this->affectedRows] = $this->connection->executeQuery($this->query, $params ?? []);

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
        if (!is_array($row)) {
            return false;
        }

        return array_values($row)[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

final class PricingCohortTestConnection extends PDO
{
    public array $subscriptions = [];
    public array $users = [];
    public array $memberships = [];
    public array $internalAdmins = [];
    public array $cohorts = [];
    public array $counters = [];
    public array $allocations = [];
    public array $terms = [];
    public array $activities = [];
    public array $preparedSql = [];
    public ?string $failOn = null;
    private bool $transaction = false;
    private array $transactionBackup = [];
    private int $lastId = 0;

    public function __construct()
    {
    }

    public static function fixture(): self
    {
        $connection = new self();
        $connection->users = [1 => 'active', 2 => 'active', 3 => 'inactive'];
        $connection->memberships = ['10:1' => true];
        $connection->subscriptions = [
            100 => self::subscriptionRow(100, 10, 1, '247sp'),
        ];
        $connection->cohorts = self::approvedCohorts();
        $connection->counters = [1 => 1];

        return $connection;
    }

    public static function subscriptionRow(
        int $subscriptionId,
        int $businessId,
        int $planId,
        string $productKey,
        string $status = 'trial'
    ): array {
        return [
            'subscription_id' => $subscriptionId,
            'business_id' => $businessId,
            'plan_id' => $planId,
            'subscription_status' => $status,
            'product_key' => $productKey,
            'plan_active' => 1,
            'business_status' => 'active',
            'is_suspended' => 0,
            'product_access_active' => 1,
        ];
    }

    public static function approvedCohorts(): array
    {
        return [
            self::cohortRow(1, 'alpha', 'Alpha', 1, 5, '0.00', '79.00', 6),
            self::cohortRow(2, 'beta', 'Beta', 6, 10, '0.00', '97.00', 0),
            self::cohortRow(3, 'founding', 'Founding', 11, 25, '100.00', '147.00', 0),
            self::cohortRow(4, 'standard', 'Standard', 26, null, '250.00', '197.00', 0),
        ];
    }

    public static function cohortRow(
        int $id,
        string $key,
        string $name,
        int $start,
        ?int $end,
        string $setup,
        string $monthly,
        int $introMonths,
        int $planId = 1,
        int $active = 1
    ): array {
        return [
            'id' => $id,
            'plan_id' => $planId,
            'cohort_key' => $key,
            'display_name' => $name,
            'position_start' => $start,
            'position_end' => $end,
            'setup_fee' => $setup,
            'monthly_fee' => $monthly,
            'currency' => 'USD',
            'free_introductory_months' => $introMonths,
            'effective_from' => '2026-08-11 00:00:00',
            'effective_until' => null,
            'version' => 1,
            'is_active' => $active,
            'stripe_recurring_price_ref' => null,
            'stripe_setup_price_ref' => null,
        ];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new PricingCohortTestStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('A transaction is already active.');
        }
        $this->transactionBackup = [
            'counters' => $this->counters,
            'allocations' => $this->allocations,
            'terms' => $this->terms,
            'activities' => $this->activities,
            'last_id' => $this->lastId,
        ];
        $this->transaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        $this->transactionBackup = [];

        return true;
    }

    public function rollBack(): bool
    {
        $this->counters = $this->transactionBackup['counters'];
        $this->allocations = $this->transactionBackup['allocations'];
        $this->terms = $this->transactionBackup['terms'];
        $this->activities = $this->transactionBackup['activities'];
        $this->lastId = $this->transactionBackup['last_id'];
        $this->transaction = false;
        $this->transactionBackup = [];

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
        if (str_contains($query, 'pricing:load_subscription')) {
            $row = $this->subscriptions[(int) $params['subscription_id']] ?? null;

            return [$row === null ? [] : [$row], 0];
        }
        if (str_contains($query, 'pricing:load_actor')) {
            $status = $this->users[(int) $params['acting_user_id']] ?? null;

            return [$status === null ? [] : [['status' => $status]], 0];
        }
        if (str_contains($query, 'pricing:is_internal_admin')) {
            $count = !empty($this->internalAdmins[(int) $params['acting_user_id']]) ? 1 : 0;

            return [[['count' => $count]], 0];
        }
        if (str_contains($query, 'pricing:business_membership')) {
            $key = (int) $params['business_id'] . ':' . (int) $params['acting_user_id'];

            return [[['count' => !empty($this->memberships[$key]) ? 1 : 0]], 0];
        }
        if (str_contains($query, 'pricing:existing_assignment')) {
            $allocation = null;
            foreach ($this->allocations as $candidate) {
                if (isset($params['idempotency_key'])) {
                    if (
                        (int) $candidate['plan_id'] === (int) $params['plan_id']
                        && $candidate['completed_signup_idempotency_key'] === $params['idempotency_key']
                    ) {
                        $allocation = $candidate;
                        break;
                    }
                } elseif ((int) $candidate['subscription_id'] === (int) $params['subscription_id']) {
                    $allocation = $candidate;
                    break;
                }
            }

            return [$allocation === null ? [] : [$this->joinedAssignment($allocation)], 0];
        }
        if (str_contains($query, 'pricing:lock_counter')) {
            $planId = (int) $params['plan_id'];
            if (!array_key_exists($planId, $this->counters)) {
                return [[], 0];
            }

            return [[[
                'plan_id' => $planId,
                'next_sequence_number' => $this->counters[$planId],
                'lock_version' => 0,
            ]], 0];
        }
        if (str_contains($query, 'pricing:effective_cohorts')) {
            $rows = array_values(array_filter($this->cohorts, static function (array $cohort) use ($params): bool {
                return (int) $cohort['plan_id'] === (int) $params['plan_id']
                    && (int) $cohort['is_active'] === 1
                    && $cohort['effective_from'] <= $params['completed_at']
                    && ($cohort['effective_until'] === null || $cohort['effective_until'] > $params['completed_at_until']);
            }));
            usort($rows, static fn (array $left, array $right): int => [$left['position_start'], $left['id']] <=> [$right['position_start'], $right['id']]);

            return [$rows, 0];
        }
        if (str_contains($query, 'pricing:insert_allocation')) {
            $this->throwIfRequested('insert_allocation');
            foreach ($this->allocations as $existing) {
                if (
                    ((int) $existing['plan_id'] === (int) $params['plan_id']
                        && (int) $existing['customer_sequence_number'] === (int) $params['customer_sequence_number'])
                    || (int) $existing['subscription_id'] === (int) $params['subscription_id']
                    || ((int) $existing['plan_id'] === (int) $params['plan_id']
                        && $existing['completed_signup_idempotency_key'] === $params['completed_signup_idempotency_key'])
                ) {
                    $exception = new PDOException('Duplicate entry for secret_table', 1062);
                    $exception->errorInfo = ['23000', 1062, 'Duplicate entry'];
                    throw $exception;
                }
            }
            $this->lastId++;
            $params['id'] = $this->lastId;
            $this->allocations[$this->lastId] = $params;

            return [[], 1];
        }
        if (str_contains($query, 'pricing:insert_terms')) {
            $this->throwIfRequested('insert_terms');
            $this->terms[(int) $params['allocation_id']] = $params;

            return [[], 1];
        }
        if (str_contains($query, 'pricing:advance_counter')) {
            $this->throwIfRequested('advance_counter');
            $planId = (int) $params['plan_id'];
            if (($this->counters[$planId] ?? null) !== (int) $params['allocated_sequence']) {
                return [[], 0];
            }
            $this->counters[$planId]++;

            return [[], 1];
        }
        if (str_contains($query, 'pricing:log_activity')) {
            $this->throwIfRequested('log_activity');
            $this->activities[] = $params;

            return [[], 1];
        }

        throw new RuntimeException('Unexpected test SQL marker.');
    }

    private function joinedAssignment(array $allocation): array
    {
        $term = $this->terms[(int) $allocation['id']] ?? null;
        if ($term === null) {
            throw new RuntimeException('Fixture allocation is missing terms.');
        }
        $cohort = null;
        foreach ($this->cohorts as $candidate) {
            if ((int) $candidate['id'] === (int) $allocation['pricing_cohort_id']) {
                $cohort = $candidate;
                break;
            }
        }
        if ($cohort === null) {
            throw new RuntimeException('Fixture allocation is missing its cohort.');
        }

        return [
            'allocation_id' => (int) $allocation['id'],
            'plan_id' => (int) $allocation['plan_id'],
            'business_id' => (int) $allocation['business_id'],
            'subscription_id' => (int) $allocation['subscription_id'],
            'pricing_cohort_id' => (int) $allocation['pricing_cohort_id'],
            'customer_sequence_number' => (int) $allocation['customer_sequence_number'],
            'completed_signup_idempotency_key' => $allocation['completed_signup_idempotency_key'],
            'assigned_at' => $allocation['assigned_at'],
            'actor_type' => $allocation['actor_type'],
            'actor_user_id' => $allocation['actor_user_id'],
            'system_actor_key' => $allocation['system_actor_key'],
            'correlation_id' => $allocation['correlation_id'],
            'product_key' => '247sp',
            'cohort_key' => $cohort['cohort_key'],
            'display_name' => $cohort['display_name'],
        ] + $term;
    }

    private function throwIfRequested(string $stage): void
    {
        if ($this->failOn === $stage) {
            throw new RuntimeException('SQL failure near secret_table.password_hash');
        }
    }
}

$pricingAssertions = 0;
$pricingTests = 0;

function assertPricing(bool $condition, string $message): void
{
    global $pricingAssertions;
    $pricingAssertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertPricingThrows(callable $callback, string $errorType, string $message): PricingCohortException
{
    try {
        $callback();
    } catch (PricingCohortException $exception) {
        assertPricing($exception->errorType() === $errorType, $message . ' Expected ' . $errorType . ', got ' . $exception->errorType() . '.');

        return $exception;
    }

    throw new RuntimeException($message . ' Expected PricingCohortException.');
}

function pricingTest(string $name, callable $callback): void
{
    global $pricingTests;
    $callback();
    $pricingTests++;
    echo "PASS {$name}\n";
}

function usePricingConnection(PricingCohortTestConnection $connection): void
{
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $connection);
}

function pricingUserCommand(int $businessId = 10, int $subscriptionId = 100, string $key = 'signup-0001'): array
{
    return [
        'business_id' => $businessId,
        'subscription_id' => $subscriptionId,
        'completed_signup_idempotency_key' => $key,
        'signup_completed_at' => '2026-08-11 15:30:00',
        'actor_type' => 'user',
        'acting_user_id' => 1,
        'correlation_id' => 'corr-0001',
        'cohort_key' => 'standard',
        'customer_sequence_number' => 999,
    ];
}

function pricingSystemCommand(int $businessId = 10, int $subscriptionId = 100, string $key = 'signup-0001'): array
{
    $command = pricingUserCommand($businessId, $subscriptionId, $key);
    $command['actor_type'] = 'system';
    unset($command['acting_user_id']);
    $command['system_actor_key'] = '247sp_completed_signup';

    return $command;
}

require_once __DIR__ . '/../private/classes/PricingCohortManager.php';

pricingTest('cohort boundaries and caller-controlled values ignored', static function (): void {
    $cases = [
        1 => 'alpha', 5 => 'alpha', 6 => 'beta', 10 => 'beta',
        11 => 'founding', 25 => 'founding', 26 => 'standard', 50000 => 'standard',
    ];
    foreach ($cases as $sequence => $expected) {
        $connection = PricingCohortTestConnection::fixture();
        $connection->counters[1] = $sequence;
        usePricingConnection($connection);
        $result = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
        assertPricing($result['cohort_key'] === $expected, "Sequence {$sequence} must select {$expected}.");
        assertPricing($result['customer_sequence_number'] === $sequence, 'The service must ignore a caller-provided sequence.');
    }
});

pricingTest('approved prices and introductory months', static function (): void {
    $cases = [
        1 => ['alpha', '0.00', '79.00', 6],
        6 => ['beta', '0.00', '97.00', 0],
        11 => ['founding', '100.00', '147.00', 0],
        26 => ['standard', '250.00', '197.00', 0],
    ];
    foreach ($cases as $sequence => [$key, $setup, $monthly, $months]) {
        $connection = PricingCohortTestConnection::fixture();
        $connection->counters[1] = $sequence;
        usePricingConnection($connection);
        $result = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
        assertPricing(
            [$result['cohort_key'], $result['setup_fee'], $result['monthly_fee'], $result['free_introductory_months']]
                === [$key, $setup, $monthly, $months],
            "Sequence {$sequence} must lock the approved commercial terms."
        );
    }
});

pricingTest('Alpha calendar-month date policy', static function (): void {
    $cases = [
        '2026-08-11 15:30:00' => '2027-02-11 15:30:00',
        '2026-08-31 15:30:00' => '2027-02-28 15:30:00',
        '2027-08-31 15:30:00' => '2028-02-29 15:30:00',
        '2026-01-31 23:59:59' => '2026-02-28 23:59:59',
    ];
    foreach ($cases as $source => $expected) {
        $months = str_starts_with($source, '2026-01') ? 1 : 6;
        assertPricing(PricingCohortManager::calendarMonthsAfterUtc($source, $months) === $expected, 'Calendar month calculation must clamp and preserve UTC time.');
    }

    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    $result = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    assertPricing($result['introductory_period_starts_at'] === '2026-08-11 15:30:00', 'Alpha intro must start at signup completion.');
    assertPricing($result['introductory_period_expires_at'] === '2027-02-11 15:30:00', 'Alpha intro must expire six calendar months later.');
    assertPricing($result['recurring_billing_starts_at'] === $result['introductory_period_expires_at'], 'Alpha recurring billing must start at expiration.');
});

pricingTest('non-Alpha date representation', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->counters[1] = 6;
    usePricingConnection($connection);
    $result = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    assertPricing($result['introductory_period_starts_at'] === null, 'Beta must not invent an introductory start.');
    assertPricing($result['introductory_period_expires_at'] === null, 'Beta must not invent an introductory expiration.');
    assertPricing($result['recurring_billing_starts_at'] === '2026-08-11 15:30:00', 'Non-Alpha recurring billing starts at signup completion.');
});

pricingTest('idempotent signup and subscription retries', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    $first = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $sameKey = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $sameSubscription = PricingCohortManager::assignCompletedSignup(pricingUserCommand(10, 100, 'signup-0002'));
    assertPricing($first === $sameKey && $first === $sameSubscription, 'Retries must reuse the stored assignment.');
    assertPricing($connection->counters[1] === 2, 'Retries must not advance the counter.');
    assertPricing(count($connection->allocations) === 1 && count($connection->terms) === 1, 'Retries must not duplicate evidence.');
    assertPricing(count($connection->activities) === 1, 'Retries must not duplicate success activity.');
});

pricingTest('idempotency key cannot cross subscriptions', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->subscriptions[101] = PricingCohortTestConnection::subscriptionRow(101, 10, 1, '247sp');
    usePricingConnection($connection);
    PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    assertPricingThrows(
        static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand(10, 101, 'signup-0001')),
        'idempotency_conflict',
        'One completed-signup key must not be reused by another subscription.'
    );
    assertPricing($connection->counters[1] === 2 && count($connection->allocations) === 1, 'An idempotency conflict must consume no additional sequence.');
});

pricingTest('rollback removes every transactional effect', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->failOn = 'insert_terms';
    usePricingConnection($connection);
    $exception = assertPricingThrows(
        static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand()),
        'database_failure',
        'A failure after the counter lock must be safe.'
    );
    assertPricing($connection->counters[1] === 1, 'Rollback must preserve the counter.');
    assertPricing($connection->allocations === [] && $connection->terms === [], 'Rollback must remove allocation and snapshot writes.');
    assertPricing($connection->activities === [], 'Rollback must remove success activity.');
    assertPricing(!str_contains($exception->getMessage(), 'secret_table') && !str_contains($exception->getMessage(), 'SQL'), 'External errors must not leak SQL details.');
    assertPricing($exception->getPrevious() === null, 'External errors must not expose the raw database exception chain.');
});

pricingTest('cancellation retains allocation and never reuses sequence', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    $first = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $connection->subscriptions[100]['subscription_status'] = 'cancelled';
    $stored = PricingCohortManager::assignmentForSubscription(10, 100, ['actor_type' => 'user', 'acting_user_id' => 1]);
    $connection->subscriptions[101] = PricingCohortTestConnection::subscriptionRow(101, 10, 1, '247sp');
    $second = PricingCohortManager::assignCompletedSignup(pricingUserCommand(10, 101, 'signup-0002'));
    assertPricing($stored === $first, 'Cancelled subscription evidence must remain readable.');
    assertPricing($second['customer_sequence_number'] === 2, 'Cancellation must not reopen sequence 1.');
});

pricingTest('one owner can independently assign two businesses', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->memberships['11:1'] = true;
    $connection->subscriptions[101] = PricingCohortTestConnection::subscriptionRow(101, 11, 1, '247sp');
    usePricingConnection($connection);
    $first = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $second = PricingCohortManager::assignCompletedSignup(pricingUserCommand(11, 101, 'signup-0002'));
    assertPricing([$first['customer_sequence_number'], $second['customer_sequence_number']] === [1, 2], 'Each business signup must consume a distinct position.');
});

pricingTest('locked terms survive configuration changes', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    $assigned = PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $connection->cohorts[0]['monthly_fee'] = '999.00';
    $connection->cohorts[0]['version'] = 2;
    $reread = PricingCohortManager::assignmentForSubscription(10, 100, ['actor_type' => 'user', 'acting_user_id' => 1]);
    assertPricing($reread['monthly_fee'] === '79.00', 'Stored monthly terms must not be recomputed.');
    assertPricing($reread['configuration_version'] === 1, 'Stored configuration version must not change.');
    assertPricing($reread['introductory_period_expires_at'] === $assigned['introductory_period_expires_at'], 'Stored dates must be reused on reread.');
});

pricingTest('system actor path and bounded audit metadata', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    $result = PricingCohortManager::assignCompletedSignup(pricingSystemCommand());
    $activity = $connection->activities[0];
    $metadata = json_decode($activity['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
    assertPricing($result['cohort_key'] === 'alpha', 'The approved system signup path must succeed.');
    assertPricing($activity['activity_type'] === '247sp_pricing_assigned' && $activity['user_id'] === null, 'System activity must use the canonical event without a fake user.');
    assertPricing(!isset($metadata['system_actor_key']) && !isset($metadata['idempotency_key']), 'Audit metadata must remain bounded.');
});

pricingTest('authorization and tenant isolation', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand(11)), 'unauthorized', 'Cross-business assignment must fail.');
    assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand(10, 999)), 'not_found', 'Invalid subscription must fail.');
    $unauthorized = pricingUserCommand();
    $unauthorized['acting_user_id'] = 2;
    assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup($unauthorized), 'unauthorized', 'Unlinked actors must fail.');
    $invalidActor = pricingSystemCommand();
    $invalidActor['system_actor_key'] = 'browser';
    assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup($invalidActor), 'unauthorized', 'Unapproved system actors must fail.');
});

pricingTest('invalid identifiers and UTC timestamps are rejected', static function (): void {
    assertPricingThrows(
        static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand(0)),
        'invalid_request',
        'Invalid business identifiers must fail before database access.'
    );
    $invalidDate = pricingUserCommand();
    $invalidDate['signup_completed_at'] = '2026-02-30 15:30:00';
    assertPricingThrows(
        static fn (): array => PricingCohortManager::assignCompletedSignup($invalidDate),
        'invalid_request',
        'Invalid UTC calendar dates must fail before database access.'
    );
});

pricingTest('internal administrator authorization', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->internalAdmins[2] = true;
    usePricingConnection($connection);
    $command = pricingUserCommand();
    $command['acting_user_id'] = 2;
    $result = PricingCohortManager::assignCompletedSignup($command);
    assertPricing($result['cohort_key'] === 'alpha', 'An active internal admin may use the service boundary.');
});

pricingTest('configuration failures are safe and atomic', static function (): void {
    $cases = [
        'missing_cohort' => static function (PricingCohortTestConnection $connection): void { $connection->cohorts = []; },
        'inactive_cohort' => static function (PricingCohortTestConnection $connection): void { foreach ($connection->cohorts as &$row) { $row['is_active'] = 0; } },
        'missing_applicable_cohort' => static function (PricingCohortTestConnection $connection): void { $connection->cohorts[0]['position_end'] = 4; $connection->counters[1] = 5; },
        'overlapping_cohorts' => static function (PricingCohortTestConnection $connection): void { $connection->cohorts[1]['position_start'] = 5; },
        'invalid_configuration' => static function (PricingCohortTestConnection $connection): void { $connection->cohorts[0]['position_end'] = 0; },
    ];
    foreach ($cases as $case => $mutate) {
        $connection = PricingCohortTestConnection::fixture();
        $mutate($connection);
        $expectedCounter = $connection->counters[1];
        usePricingConnection($connection);
        $expected = in_array($case, ['inactive_cohort', 'missing_applicable_cohort'], true) ? 'missing_cohort' : $case;
        assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand()), $expected, "{$case} must fail safely.");
        assertPricing($connection->counters[1] === $expectedCounter && $connection->allocations === [] && $connection->activities === [], "{$case} must roll back.");
    }
});

pricingTest('wrong product fails before allocation', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    $connection->subscriptions[100]['product_key'] = 'ssp';
    usePricingConnection($connection);
    assertPricingThrows(static fn (): array => PricingCohortManager::assignCompletedSignup(pricingUserCommand()), 'wrong_product', 'A non-247SP subscription must fail.');
    assertPricing($connection->counters[1] === 1 && $connection->allocations === [], 'Wrong product must consume nothing.');
});

pricingTest('locking and database uniqueness assumptions are exercised', static function (): void {
    $connection = PricingCohortTestConnection::fixture();
    usePricingConnection($connection);
    PricingCohortManager::assignCompletedSignup(pricingUserCommand());
    $sql = implode("\n", $connection->preparedSql);
    assertPricing(str_contains($sql, 'product_customer_sequence_counters') && str_contains($sql, 'FOR UPDATE'), 'The product counter must use a locking read.');
    assertPricing(str_contains($sql, 'AND next_sequence_number = :allocated_sequence'), 'Counter advancement must be compare-and-swap guarded.');
});

echo "PricingCohortManager: {$pricingTests} tests, {$pricingAssertions} assertions passed.\n";
