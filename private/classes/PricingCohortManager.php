<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class PricingCohortException extends RuntimeException
{
    public function __construct(
        private string $errorType,
        string $message
    ) {
        parent::__construct($message);
    }

    public function errorType(): string
    {
        return $this->errorType;
    }
}

final class PricingCohortManager
{
    private const PRODUCT_KEY = '247sp';
    private const ACTOR_TYPES = ['user', 'system'];
    private const SYSTEM_ACTOR_KEYS = ['247sp_completed_signup'];

    /**
     * Assign the permanent 247SP sequence position and immutable commercial terms.
     *
     * Required command keys: business_id, subscription_id,
     * completed_signup_idempotency_key, signup_completed_at, and actor_type.
     * User actors also provide acting_user_id. System actors provide the allowlisted
     * system_actor_key. correlation_id is optional.
     */
    public static function assignCompletedSignup(array $command): array
    {
        $input = self::normalizeAssignmentCommand($command);
        $connection = Database::connection();
        $ownsTransaction = !$connection->inTransaction();

        try {
            if ($ownsTransaction) {
                $connection->beginTransaction();
            }

            $subscription = self::lockedSubscription($connection, $input['subscription_id']);
            self::assertSubscriptionContext($subscription, $input['business_id']);
            self::authorizeActor($connection, $subscription, $input);

            $existing = self::existingAssignmentByIdempotencyKey(
                $connection,
                (int) $subscription['plan_id'],
                $input['completed_signup_idempotency_key'],
                true
            );
            if ($existing !== null) {
                self::assertExistingAssignmentMatches($existing, $input, $subscription);
                if ($ownsTransaction) {
                    $connection->commit();
                }

                return self::normalizeAssignment($existing);
            }

            $existing = self::existingAssignmentBySubscription(
                $connection,
                $input['subscription_id'],
                true
            );
            if ($existing !== null) {
                self::assertExistingAssignmentMatches($existing, $input, $subscription, false);
                if ($ownsTransaction) {
                    $connection->commit();
                }

                return self::normalizeAssignment($existing);
            }

            $counter = self::lockedSequenceCounter($connection, (int) $subscription['plan_id']);
            $sequence = (int) ($counter['next_sequence_number'] ?? 0);
            if ($sequence <= 0) {
                throw new PricingCohortException(
                    'invalid_configuration',
                    '247SP pricing configuration is not available.'
                );
            }

            $cohorts = self::effectiveCohorts(
                $connection,
                (int) $subscription['plan_id'],
                $input['signup_completed_at']
            );
            $cohort = self::selectCohort($cohorts, $sequence);
            $terms = self::commercialTerms($cohort, $input['signup_completed_at']);
            $assignedAt = $input['signup_completed_at'];

            $allocation = $connection->prepare(
                '/* pricing:insert_allocation */
                 INSERT INTO product_customer_sequence_allocations (
                    plan_id, business_id, subscription_id, pricing_cohort_id,
                    customer_sequence_number, completed_signup_idempotency_key,
                    assigned_at, actor_type, actor_user_id, system_actor_key,
                    correlation_id, created_at, updated_at
                 ) VALUES (
                    :plan_id, :business_id, :subscription_id, :pricing_cohort_id,
                    :customer_sequence_number, :completed_signup_idempotency_key,
                    :assigned_at, :actor_type, :actor_user_id, :system_actor_key,
                    :correlation_id, NOW(), NOW()
                 )'
            );
            $allocation->execute([
                'plan_id' => (int) $subscription['plan_id'],
                'business_id' => $input['business_id'],
                'subscription_id' => $input['subscription_id'],
                'pricing_cohort_id' => (int) $cohort['id'],
                'customer_sequence_number' => $sequence,
                'completed_signup_idempotency_key' => $input['completed_signup_idempotency_key'],
                'assigned_at' => $assignedAt,
                'actor_type' => $input['actor_type'],
                'actor_user_id' => $input['acting_user_id'],
                'system_actor_key' => $input['system_actor_key'],
                'correlation_id' => $input['correlation_id'],
            ]);
            $allocationId = (int) $connection->lastInsertId();
            if ($allocationId <= 0) {
                throw new RuntimeException('Allocation identity was not returned.');
            }

            $snapshot = $connection->prepare(
                '/* pricing:insert_terms */
                 INSERT INTO subscription_commercial_terms (
                    subscription_id, allocation_id, pricing_cohort_id,
                    customer_sequence_number, locked_setup_fee, locked_monthly_fee,
                    currency, locked_free_introductory_months, pricing_assigned_at,
                    business_signup_completed_at, introductory_period_starts_at,
                    introductory_period_expires_at, recurring_billing_starts_at,
                    locked_stripe_recurring_price_ref, locked_stripe_setup_price_ref,
                    configuration_version, correlation_id, created_at, updated_at
                 ) VALUES (
                    :subscription_id, :allocation_id, :pricing_cohort_id,
                    :customer_sequence_number, :locked_setup_fee, :locked_monthly_fee,
                    :currency, :locked_free_introductory_months, :pricing_assigned_at,
                    :business_signup_completed_at, :introductory_period_starts_at,
                    :introductory_period_expires_at, :recurring_billing_starts_at,
                    :locked_stripe_recurring_price_ref, :locked_stripe_setup_price_ref,
                    :configuration_version, :correlation_id, NOW(), NOW()
                 )'
            );
            $snapshot->execute([
                'subscription_id' => $input['subscription_id'],
                'allocation_id' => $allocationId,
                'pricing_cohort_id' => (int) $cohort['id'],
                'customer_sequence_number' => $sequence,
                'locked_setup_fee' => $terms['setup_fee'],
                'locked_monthly_fee' => $terms['monthly_fee'],
                'currency' => $terms['currency'],
                'locked_free_introductory_months' => $terms['free_introductory_months'],
                'pricing_assigned_at' => $assignedAt,
                'business_signup_completed_at' => $input['signup_completed_at'],
                'introductory_period_starts_at' => $terms['introductory_period_starts_at'],
                'introductory_period_expires_at' => $terms['introductory_period_expires_at'],
                'recurring_billing_starts_at' => $terms['recurring_billing_starts_at'],
                'locked_stripe_recurring_price_ref' => $terms['stripe_recurring_price_ref'],
                'locked_stripe_setup_price_ref' => $terms['stripe_setup_price_ref'],
                'configuration_version' => (int) $cohort['version'],
                'correlation_id' => $input['correlation_id'],
            ]);

            $advance = $connection->prepare(
                '/* pricing:advance_counter */
                 UPDATE product_customer_sequence_counters
                 SET next_sequence_number = next_sequence_number + 1,
                     lock_version = lock_version + 1,
                     updated_at = NOW()
                 WHERE plan_id = :plan_id
                   AND next_sequence_number = :allocated_sequence'
            );
            $advance->execute([
                'plan_id' => (int) $subscription['plan_id'],
                'allocated_sequence' => $sequence,
            ]);
            if ($advance->rowCount() !== 1) {
                throw new RuntimeException('Sequence counter did not advance.');
            }

            self::logAssignmentActivity(
                $connection,
                $input,
                $subscription,
                $cohort,
                $terms,
                $sequence
            );

            $result = self::existingAssignmentBySubscription(
                $connection,
                $input['subscription_id'],
                true
            );
            if ($result === null) {
                throw new RuntimeException('Commercial terms could not be read after assignment.');
            }

            if ($ownsTransaction) {
                $connection->commit();
            }

            return self::normalizeAssignment($result);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            if ($exception instanceof PricingCohortException) {
                throw $exception;
            }

            if ($ownsTransaction && self::isDuplicateKeyFailure($exception)) {
                $recovered = self::recoverConcurrentAssignment($input);
                if ($recovered !== null) {
                    return $recovered;
                }
            }

            self::reportFailure('assign_completed_signup', $exception);
            throw new PricingCohortException(
                'database_failure',
                'The 247SP pricing assignment could not be completed.'
            );
        }
    }

    public static function assignmentForSubscription(
        int $businessId,
        int $subscriptionId,
        array $actorContext
    ): ?array {
        $input = self::normalizeActorContext($actorContext);
        $input['business_id'] = $businessId;
        $input['subscription_id'] = $subscriptionId;

        try {
            if ($businessId <= 0 || $subscriptionId <= 0) {
                throw new PricingCohortException('not_found', 'The requested 247SP subscription was not found.');
            }

            $connection = Database::connection();
            $subscription = self::subscription($connection, $subscriptionId, false);
            self::assertSubscriptionContext($subscription, $businessId, true);
            self::authorizeActor($connection, $subscription, $input);
            $assignment = self::existingAssignmentBySubscription($connection, $subscriptionId, false);

            return $assignment === null ? null : self::normalizeAssignment($assignment);
        } catch (PricingCohortException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::reportFailure('read_assignment', $exception);
            throw new PricingCohortException(
                'database_failure',
                'The 247SP pricing assignment could not be read.'
            );
        }
    }

    public static function calendarMonthsAfterUtc(string $utcTimestamp, int $months): string
    {
        if ($months < 0 || $months > 120) {
            throw new InvalidArgumentException('Calendar month offset is invalid.');
        }

        $source = self::parseUtcTimestamp($utcTimestamp);
        $year = (int) $source->format('Y');
        $month = (int) $source->format('n');
        $day = (int) $source->format('j');
        $targetIndex = ($year * 12) + ($month - 1) + $months;
        $targetYear = intdiv($targetIndex, 12);
        $targetMonth = ($targetIndex % 12) + 1;
        $targetMonthStart = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $targetYear, $targetMonth),
            new DateTimeZone('UTC')
        );
        $lastDay = (int) $targetMonthStart->modify('last day of this month')->format('j');
        $targetDay = min($day, $lastDay);

        return sprintf(
            '%04d-%02d-%02d %s',
            $targetYear,
            $targetMonth,
            $targetDay,
            $source->format('H:i:s')
        );
    }

    private static function normalizeAssignmentCommand(array $command): array
    {
        $businessId = (int) ($command['business_id'] ?? 0);
        $subscriptionId = (int) ($command['subscription_id'] ?? 0);
        if ($businessId <= 0 || $subscriptionId <= 0) {
            throw new PricingCohortException('invalid_request', 'A valid business and subscription are required.');
        }

        $idempotencyKey = trim((string) ($command['completed_signup_idempotency_key'] ?? ''));
        if (
            strlen($idempotencyKey) < 8
            || strlen($idempotencyKey) > 191
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $idempotencyKey) !== 1
        ) {
            throw new PricingCohortException(
                'invalid_request',
                'A valid completed-signup idempotency key is required.'
            );
        }

        $completedAt = trim((string) ($command['signup_completed_at'] ?? ''));
        self::parseUtcTimestamp($completedAt);
        $actor = self::normalizeActorContext($command);

        return $actor + [
            'business_id' => $businessId,
            'subscription_id' => $subscriptionId,
            'completed_signup_idempotency_key' => $idempotencyKey,
            'signup_completed_at' => $completedAt,
        ];
    }

    private static function normalizeActorContext(array $context): array
    {
        $actorType = strtolower(trim((string) ($context['actor_type'] ?? '')));
        if (!in_array($actorType, self::ACTOR_TYPES, true)) {
            throw new PricingCohortException('unauthorized', 'The pricing assignment actor is not authorized.');
        }

        $actingUserId = null;
        $systemActorKey = null;
        if ($actorType === 'user') {
            $actingUserId = (int) ($context['acting_user_id'] ?? 0);
            if ($actingUserId <= 0) {
                throw new PricingCohortException('unauthorized', 'The pricing assignment actor is not authorized.');
            }
        } else {
            $systemActorKey = trim((string) ($context['system_actor_key'] ?? ''));
            if (!in_array($systemActorKey, self::SYSTEM_ACTOR_KEYS, true)) {
                throw new PricingCohortException('unauthorized', 'The pricing assignment actor is not authorized.');
            }
        }

        $correlationId = trim((string) ($context['correlation_id'] ?? ''));
        if (strlen($correlationId) > 100 || ($correlationId !== '' && preg_match('/^[A-Za-z0-9._:-]+$/', $correlationId) !== 1)) {
            throw new PricingCohortException('invalid_request', 'The correlation identifier is invalid.');
        }

        return [
            'actor_type' => $actorType,
            'acting_user_id' => $actingUserId,
            'system_actor_key' => $systemActorKey,
            'correlation_id' => $correlationId === '' ? null : $correlationId,
        ];
    }

    private static function lockedSubscription(PDO $connection, int $subscriptionId): array
    {
        return self::subscription($connection, $subscriptionId, true);
    }

    private static function subscription(PDO $connection, int $subscriptionId, bool $lock): array
    {
        $statement = $connection->prepare(
            '/* pricing:load_subscription */
             SELECT s.id AS subscription_id, s.business_id, s.plan_id, s.status AS subscription_status,
                    p.product_key, p.active AS plan_active,
                    b.status AS business_status, b.is_suspended,
                    EXISTS (
                        SELECT 1
                        FROM business_modules bm
                        INNER JOIN modules m ON m.id = bm.module_id
                        WHERE bm.business_id = s.business_id
                          AND bm.status = \'active\'
                          AND m.module_key = p.product_key
                          AND m.is_active = 1
                    ) AS product_access_active
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             INNER JOIN businesses b ON b.id = s.business_id
             WHERE s.id = :subscription_id
             LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['subscription_id' => $subscriptionId]);
        $subscription = $statement->fetch();
        if (!is_array($subscription)) {
            throw new PricingCohortException('not_found', 'The requested 247SP subscription was not found.');
        }

        return $subscription;
    }

    private static function assertSubscriptionContext(
        array $subscription,
        int $businessId,
        bool $allowCancelled = false
    ): void
    {
        if ((int) $subscription['business_id'] !== $businessId) {
            throw new PricingCohortException('unauthorized', 'The subscription does not belong to this business.');
        }
        if ((string) $subscription['product_key'] !== self::PRODUCT_KEY || (int) $subscription['plan_active'] !== 1) {
            throw new PricingCohortException('wrong_product', 'The requested subscription is not eligible for 247SP pricing.');
        }
        if (
            (string) $subscription['business_status'] !== 'active'
            || (int) $subscription['is_suspended'] === 1
            || (int) $subscription['product_access_active'] !== 1
            || (!$allowCancelled && (string) $subscription['subscription_status'] === 'cancelled')
        ) {
            throw new PricingCohortException('unauthorized', 'This business is not eligible for 247SP pricing assignment.');
        }
    }

    private static function authorizeActor(PDO $connection, array $subscription, array $input): void
    {
        if ($input['actor_type'] === 'system') {
            return;
        }

        $actor = $connection->prepare(
            '/* pricing:load_actor */ SELECT status FROM users WHERE id = :acting_user_id LIMIT 1'
        );
        $actor->execute(['acting_user_id' => $input['acting_user_id']]);
        if ($actor->fetchColumn() !== 'active') {
            throw new PricingCohortException('unauthorized', 'The pricing assignment actor is not authorized.');
        }

        $admin = $connection->prepare(
            '/* pricing:is_internal_admin */
             SELECT COUNT(*)
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :acting_user_id
               AND r.scope = \'internal\'
               AND r.name IN (\'Super Admin\', \'Admin\')'
        );
        $admin->execute(['acting_user_id' => $input['acting_user_id']]);
        if ((int) $admin->fetchColumn() > 0) {
            return;
        }

        $membership = $connection->prepare(
            '/* pricing:business_membership */
             SELECT COUNT(*)
             FROM business_users
             WHERE business_id = :business_id
               AND user_id = :acting_user_id
               AND status = \'active\''
        );
        $membership->execute([
            'business_id' => (int) $subscription['business_id'],
            'acting_user_id' => $input['acting_user_id'],
        ]);
        if ((int) $membership->fetchColumn() !== 1) {
            throw new PricingCohortException('unauthorized', 'The pricing assignment actor is not authorized.');
        }
    }

    private static function lockedSequenceCounter(PDO $connection, int $planId): array
    {
        $statement = $connection->prepare(
            '/* pricing:lock_counter */
             SELECT plan_id, next_sequence_number, lock_version
             FROM product_customer_sequence_counters
             WHERE plan_id = :plan_id
             FOR UPDATE'
        );
        $statement->execute(['plan_id' => $planId]);
        $counter = $statement->fetch();
        if (!is_array($counter)) {
            throw new PricingCohortException('invalid_configuration', '247SP pricing configuration is not available.');
        }

        return $counter;
    }

    private static function effectiveCohorts(PDO $connection, int $planId, string $completedAt): array
    {
        $statement = $connection->prepare(
            '/* pricing:effective_cohorts */
             SELECT id, plan_id, cohort_key, display_name, position_start, position_end,
                    setup_fee, monthly_fee, currency, free_introductory_months,
                    effective_from, effective_until, version, is_active,
                    stripe_recurring_price_ref, stripe_setup_price_ref
             FROM pricing_cohorts
             WHERE plan_id = :plan_id
               AND is_active = 1
               AND effective_from <= :completed_at
               AND (effective_until IS NULL OR effective_until > :completed_at_until)
             ORDER BY position_start ASC, id ASC
             FOR UPDATE'
        );
        $statement->execute([
            'plan_id' => $planId,
            'completed_at' => $completedAt,
            'completed_at_until' => $completedAt,
        ]);
        $cohorts = $statement->fetchAll();
        if (!is_array($cohorts) || count($cohorts) === 0) {
            throw new PricingCohortException('missing_cohort', '247SP pricing configuration is not available.');
        }

        self::validateEffectiveCohorts($cohorts, $planId, $completedAt);

        return $cohorts;
    }

    private static function validateEffectiveCohorts(array $cohorts, int $planId, string $completedAt): void
    {
        $currency = null;
        $previousEnd = 0;
        foreach ($cohorts as $cohort) {
            $start = (int) ($cohort['position_start'] ?? 0);
            $end = $cohort['position_end'] === null ? null : (int) $cohort['position_end'];
            $rowCurrency = strtoupper(trim((string) ($cohort['currency'] ?? '')));
            $isEffective = (string) ($cohort['effective_from'] ?? '') <= $completedAt
                && (($cohort['effective_until'] ?? null) === null || (string) $cohort['effective_until'] > $completedAt);

            if (
                (int) ($cohort['plan_id'] ?? 0) !== $planId
                || (int) ($cohort['is_active'] ?? 0) !== 1
                || !$isEffective
                || $start <= 0
                || ($end !== null && $end < $start)
                || (int) ($cohort['version'] ?? 0) <= 0
                || (int) ($cohort['free_introductory_months'] ?? -1) < 0
                || preg_match('/^[A-Z]{3}$/', $rowCurrency) !== 1
            ) {
                throw new PricingCohortException('invalid_configuration', '247SP pricing configuration is invalid.');
            }

            self::normalizeMoney($cohort['setup_fee'] ?? null);
            self::normalizeMoney($cohort['monthly_fee'] ?? null);

            if ($previousEnd === null || $start <= $previousEnd) {
                throw new PricingCohortException('overlapping_cohorts', '247SP pricing configuration is ambiguous.');
            }
            $previousEnd = $end;

            if ($currency !== null && $currency !== $rowCurrency) {
                throw new PricingCohortException('invalid_configuration', '247SP pricing configuration is inconsistent.');
            }
            $currency = $rowCurrency;
        }
    }

    private static function selectCohort(array $cohorts, int $sequence): array
    {
        $matches = [];
        foreach ($cohorts as $cohort) {
            $start = (int) $cohort['position_start'];
            $end = $cohort['position_end'] === null ? null : (int) $cohort['position_end'];
            if ($sequence >= $start && ($end === null || $sequence <= $end)) {
                $matches[] = $cohort;
            }
        }

        if (count($matches) === 0) {
            throw new PricingCohortException('missing_cohort', 'No pricing cohort is configured for this signup.');
        }
        if (count($matches) !== 1) {
            throw new PricingCohortException('overlapping_cohorts', '247SP pricing configuration is ambiguous.');
        }

        return $matches[0];
    }

    private static function commercialTerms(array $cohort, string $completedAt): array
    {
        $recurringPriceRef = self::nullableReference($cohort['stripe_recurring_price_ref'] ?? null);
        $setupPriceRef = self::nullableReference($cohort['stripe_setup_price_ref'] ?? null);
        $setupFee = self::normalizeMoney($cohort['setup_fee']);

        if (!self::validStripePriceReference($recurringPriceRef)
            || ((float) $setupFee > 0 && !self::validStripePriceReference($setupPriceRef))
        ) {
            throw new PricingCohortException(
                'invalid_configuration',
                '247SP provider pricing configuration is not available.'
            );
        }

        $introMonths = (int) $cohort['free_introductory_months'];
        $introStart = null;
        $introExpires = null;
        $recurringStarts = $completedAt;
        if ($introMonths > 0) {
            $introStart = $completedAt;
            $introExpires = self::calendarMonthsAfterUtc($completedAt, $introMonths);
            $recurringStarts = $introExpires;
        }

        return [
            'setup_fee' => $setupFee,
            'monthly_fee' => self::normalizeMoney($cohort['monthly_fee']),
            'currency' => strtoupper((string) $cohort['currency']),
            'free_introductory_months' => $introMonths,
            'introductory_period_starts_at' => $introStart,
            'introductory_period_expires_at' => $introExpires,
            'recurring_billing_starts_at' => $recurringStarts,
            'stripe_recurring_price_ref' => $recurringPriceRef,
            'stripe_setup_price_ref' => (float) $setupFee > 0 ? $setupPriceRef : null,
        ];
    }

    private static function existingAssignmentByIdempotencyKey(
        PDO $connection,
        int $planId,
        string $idempotencyKey,
        bool $lock
    ): ?array {
        return self::existingAssignment(
            $connection,
            'a.plan_id = :plan_id AND a.completed_signup_idempotency_key = :idempotency_key',
            ['plan_id' => $planId, 'idempotency_key' => $idempotencyKey],
            $lock
        );
    }

    private static function existingAssignmentBySubscription(
        PDO $connection,
        int $subscriptionId,
        bool $lock
    ): ?array {
        return self::existingAssignment(
            $connection,
            'a.subscription_id = :subscription_id',
            ['subscription_id' => $subscriptionId],
            $lock
        );
    }

    private static function existingAssignment(PDO $connection, string $where, array $params, bool $lock): ?array
    {
        $statement = $connection->prepare(
            '/* pricing:existing_assignment */
             SELECT a.id AS allocation_id, a.plan_id, a.business_id, a.subscription_id,
                    a.pricing_cohort_id, a.customer_sequence_number,
                    a.completed_signup_idempotency_key, a.assigned_at,
                    a.actor_type, a.actor_user_id, a.system_actor_key, a.correlation_id,
                    p.product_key, pc.cohort_key, pc.display_name,
                    t.locked_setup_fee, t.locked_monthly_fee, t.currency,
                    t.locked_free_introductory_months, t.pricing_assigned_at,
                    t.business_signup_completed_at, t.introductory_period_starts_at,
                    t.introductory_period_expires_at, t.recurring_billing_starts_at,
                    t.locked_stripe_recurring_price_ref, t.locked_stripe_setup_price_ref,
                    t.configuration_version
             FROM product_customer_sequence_allocations a
             INNER JOIN subscription_commercial_terms t ON t.allocation_id = a.id
             INNER JOIN pricing_cohorts pc ON pc.id = a.pricing_cohort_id
             INNER JOIN plans p ON p.id = a.plan_id
             WHERE ' . $where . '
             LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute($params);
        $assignment = $statement->fetch();

        return is_array($assignment) ? $assignment : null;
    }

    private static function assertExistingAssignmentMatches(
        array $existing,
        array $input,
        array $subscription,
        bool $requireIdempotencyKey = true
    ): void {
        if (
            (int) $existing['plan_id'] !== (int) $subscription['plan_id']
            || (int) $existing['business_id'] !== $input['business_id']
            || (int) $existing['subscription_id'] !== $input['subscription_id']
            || ($requireIdempotencyKey
                && !hash_equals(
                    (string) $existing['completed_signup_idempotency_key'],
                    $input['completed_signup_idempotency_key']
                ))
        ) {
            throw new PricingCohortException(
                'idempotency_conflict',
                'The completed-signup request conflicts with an existing pricing assignment.'
            );
        }
    }

    private static function normalizeAssignment(array $row): array
    {
        return [
            'product_key' => (string) $row['product_key'],
            'plan_id' => (int) $row['plan_id'],
            'business_id' => (int) $row['business_id'],
            'subscription_id' => (int) $row['subscription_id'],
            'allocation_id' => (int) $row['allocation_id'],
            'pricing_cohort_id' => (int) $row['pricing_cohort_id'],
            'cohort_key' => (string) $row['cohort_key'],
            'display_name' => (string) $row['display_name'],
            'customer_sequence_number' => (int) $row['customer_sequence_number'],
            'setup_fee' => self::normalizeMoney($row['locked_setup_fee']),
            'monthly_fee' => self::normalizeMoney($row['locked_monthly_fee']),
            'currency' => (string) $row['currency'],
            'free_introductory_months' => (int) $row['locked_free_introductory_months'],
            'pricing_assigned_at' => (string) $row['pricing_assigned_at'],
            'business_signup_completed_at' => (string) $row['business_signup_completed_at'],
            'introductory_period_starts_at' => self::nullableReference($row['introductory_period_starts_at']),
            'introductory_period_expires_at' => self::nullableReference($row['introductory_period_expires_at']),
            'recurring_billing_starts_at' => (string) $row['recurring_billing_starts_at'],
            'stripe_recurring_price_ref' => self::nullableReference($row['locked_stripe_recurring_price_ref']),
            'stripe_setup_price_ref' => self::nullableReference($row['locked_stripe_setup_price_ref']),
            'configuration_version' => (int) $row['configuration_version'],
            'correlation_id' => self::nullableReference($row['correlation_id']),
        ];
    }

    private static function logAssignmentActivity(
        PDO $connection,
        array $input,
        array $subscription,
        array $cohort,
        array $terms,
        int $sequence
    ): void {
        $metadata = json_encode([
            'business_id' => $input['business_id'],
            'subscription_id' => $input['subscription_id'],
            'cohort_key' => (string) $cohort['cohort_key'],
            'customer_sequence_number' => $sequence,
            'setup_fee' => $terms['setup_fee'],
            'monthly_fee' => $terms['monthly_fee'],
            'introductory_period_expires_at' => $terms['introductory_period_expires_at'],
            'correlation_id' => $input['correlation_id'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $statement = $connection->prepare(
            '/* pricing:log_activity */
             INSERT INTO activity_logs (
                business_id, user_id, module_key, activity_type,
                subject, description, metadata_json, created_at
             ) VALUES (
                :business_id, :user_id, :module_key, :activity_type,
                :subject, :description, :metadata_json, NOW()
             )'
        );
        $statement->execute([
            'business_id' => (int) $subscription['business_id'],
            'user_id' => $input['acting_user_id'],
            'module_key' => self::PRODUCT_KEY,
            'activity_type' => '247sp_pricing_assigned',
            'subject' => '247SP pricing assigned',
            'description' => 'Assigned locked 247SP commercial terms.',
            'metadata_json' => $metadata,
        ]);
    }

    private static function recoverConcurrentAssignment(array $input): ?array
    {
        try {
            $connection = Database::connection();
            $subscription = self::subscription($connection, $input['subscription_id'], false);
            self::assertSubscriptionContext($subscription, $input['business_id']);
            self::authorizeActor($connection, $subscription, $input);
            $existing = self::existingAssignmentByIdempotencyKey(
                $connection,
                (int) $subscription['plan_id'],
                $input['completed_signup_idempotency_key'],
                false
            );
            if ($existing === null) {
                return null;
            }

            self::assertExistingAssignmentMatches($existing, $input, $subscription);

            return self::normalizeAssignment($existing);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private static function isDuplicateKeyFailure(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        $errorInfo = $exception->errorInfo;

        return (string) $exception->getCode() === '23000'
            || (is_array($errorInfo) && (int) ($errorInfo[1] ?? 0) === 1062);
    }

    private static function parseUtcTimestamp(string $timestamp): DateTimeImmutable
    {
        $timezone = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d H:i:s') !== $timestamp
        ) {
            throw new PricingCohortException(
                'invalid_request',
                'The completed-signup timestamp must be a valid UTC date and time.'
            );
        }

        return $parsed;
    }

    private static function normalizeMoney($value): string
    {
        $money = trim((string) $value);
        if (preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/', $money, $matches) !== 1) {
            throw new PricingCohortException('invalid_configuration', '247SP pricing configuration is invalid.');
        }

        return $matches[1] . '.' . str_pad($matches[2] ?? '', 2, '0');
    }

    private static function nullableReference($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function validStripePriceReference(?string $value): bool
    {
        return $value !== null && preg_match('/^price_[A-Za-z0-9]+$/', $value) === 1;
    }

    private static function reportFailure(string $operation, Throwable $exception): void
    {
        error_log('[PricingCohortManager] ' . $operation . ' failed: ' . get_class($exception));
    }
}
