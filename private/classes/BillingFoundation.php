<?php

require_once __DIR__ . '/Database.php';

final class BillingFoundation
{
    public const STATUSES = [
        'trial',
        'pending_payment',
        'active',
        'past_due',
        'cancelled',
    ];

    public static function ensureSubscriptionForBusiness(int $businessId, string $productKey = '247sp', string $status = 'trial'): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'trial';
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO subscriptions (business_id, plan_id, status, started_at, created_at)
             SELECT :business_id, p.id, :status, NOW(), NOW()
             FROM plans p
             WHERE p.product_key = :product_key
               AND p.active = 1
             ON DUPLICATE KEY UPDATE
                started_at = COALESCE(started_at, VALUES(started_at))'
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => $status,
            'product_key' => $productKey,
        ]);
    }

    public static function customerSubscriptionsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id AS business_id,
                    b.business_name,
                    s.id AS subscription_id,
                    s.status AS subscription_status,
                    s.stripe_customer_id,
                    s.stripe_subscription_id,
                    s.stripe_checkout_session_id,
                    s.stripe_latest_invoice_id,
                    s.payment_method_status,
                    s.current_period_start,
                    s.current_period_end,
                    s.cancel_at_period_end,
                    s.started_at,
                    s.cancelled_at,
                    p.product_key,
                    p.name AS plan_name,
                    p.setup_fee,
                    p.monthly_fee,
                    CASE
                        WHEN p.product_key IS NULL THEN NULL
                        WHEN access_bm.id IS NULL THEN 0
                        ELSE 1
                    END AS module_access_active,
                    access_module.name AS module_name
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             LEFT JOIN subscriptions s ON s.business_id = b.id
             LEFT JOIN plans p ON p.id = s.plan_id
             LEFT JOIN modules access_module ON access_module.module_key = p.product_key
             LEFT JOIN business_modules access_bm ON access_bm.business_id = b.id
                AND access_bm.module_id = access_module.id
                AND access_bm.status = :module_status
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.business_name ASC, s.created_at DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
            'module_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function customerPaymentsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT payments.id,
                    payments.subscription_id,
                    payments.payment_type,
                    payments.amount,
                    payments.status,
                    payments.transaction_reference,
                    payments.stripe_invoice_id,
                    payments.stripe_payment_intent_id,
                    payments.stripe_checkout_session_id,
                    payments.invoice_url,
                    payments.created_at,
                    b.id AS business_id,
                    b.business_name,
                    p.product_key,
                    p.name AS plan_name
             FROM payments
             INNER JOIN subscriptions s ON s.id = payments.subscription_id
             INNER JOIN businesses b ON b.id = s.business_id
             INNER JOIN business_users bu ON bu.business_id = b.id
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY payments.created_at DESC, payments.id DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function activePlans(): array
    {
        return Database::connection()->query(
            'SELECT product_key, name, setup_fee, monthly_fee
             FROM plans
             WHERE active = 1
             ORDER BY name ASC'
        )->fetchAll();
    }

    public static function subscriptionForBusiness(int $businessId, string $productKey = '247sp'): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, p.product_key, p.name AS plan_name, p.setup_fee, p.monthly_fee
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.business_id = :business_id
               AND p.product_key = :product_key
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 1'
        );
        $statement->execute([
            'business_id' => $businessId,
            'product_key' => $productKey,
        ]);
        $subscription = $statement->fetch();

        return $subscription ?: null;
    }

    public static function adminSubscriptions(): array
    {
        return Database::connection()->query(
            "SELECT s.id,
                    s.business_id,
                    s.status,
                    s.stripe_customer_id,
                    s.stripe_subscription_id,
                    s.stripe_checkout_session_id,
                    s.stripe_latest_invoice_id,
                    s.payment_method_status,
                    s.current_period_start,
                    s.current_period_end,
                    s.cancel_at_period_end,
                    s.started_at,
                    s.cancelled_at,
                    latest_payment.status AS latest_payment_status,
                    latest_payment.payment_type AS latest_payment_type,
                    latest_payment.stripe_invoice_id AS latest_stripe_invoice_id,
                    latest_payment.stripe_payment_intent_id AS latest_stripe_payment_intent_id,
                    latest_payment.created_at AS latest_payment_at,
                    b.business_name,
                    p.product_key,
                    p.name AS plan_name,
                    p.setup_fee,
                    p.monthly_fee,
                    CASE
                        WHEN access_bm.id IS NULL THEN 0
                        ELSE 1
                    END AS module_access_active,
                    access_module.name AS module_name
             FROM subscriptions s
             INNER JOIN businesses b ON b.id = s.business_id
             INNER JOIN plans p ON p.id = s.plan_id
             LEFT JOIN modules access_module ON access_module.module_key = p.product_key
             LEFT JOIN business_modules access_bm ON access_bm.business_id = b.id
                AND access_bm.module_id = access_module.id
                AND access_bm.status = 'active'
             LEFT JOIN payments latest_payment ON latest_payment.id = (
                SELECT pmt.id
                FROM payments pmt
                WHERE pmt.subscription_id = s.id
                ORDER BY pmt.created_at DESC, pmt.id DESC
                LIMIT 1
             )
             ORDER BY s.created_at DESC, s.id DESC"
        )->fetchAll();
    }

    public static function adminMetrics(): array
    {
        $statement = Database::connection()->query(
            "SELECT
                SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_subscriptions,
                SUM(CASE WHEN s.status = 'trial' THEN 1 ELSE 0 END) AS trial_accounts,
                SUM(CASE WHEN s.status = 'past_due' THEN 1 ELSE 0 END) AS past_due_accounts,
                SUM(CASE WHEN s.status = 'active' THEN p.monthly_fee ELSE 0 END) AS mrr
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id"
        );
        $metrics = $statement->fetch() ?: [];

        return [
            'active_subscriptions' => (int) ($metrics['active_subscriptions'] ?? 0),
            'trial_accounts' => (int) ($metrics['trial_accounts'] ?? 0),
            'past_due_accounts' => (int) ($metrics['past_due_accounts'] ?? 0),
            'mrr' => (float) ($metrics['mrr'] ?? 0),
        ];
    }

    public static function setSubscriptionStatus(int $subscriptionId, int $adminUserId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported subscription status.');
        }

        $subscription = self::adminSubscription($subscriptionId);
        if ($subscription === null) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        $statement = Database::connection()->prepare(
            'UPDATE subscriptions
             SET status = :status,
                 payment_method_status = :payment_method_status,
                 started_at = IF(started_at IS NULL AND :status_for_started <> :cancelled_status, NOW(), started_at),
                 cancelled_at = IF(:status_for_cancelled = :cancelled_status_check, NOW(), NULL),
                 updated_at = NOW()
             WHERE id = :subscription_id'
        );
        $statement->execute([
            'status' => $status,
            'payment_method_status' => self::paymentMethodStatusForSubscriptionStatus($status),
            'status_for_started' => $status,
            'cancelled_status' => 'cancelled',
            'status_for_cancelled' => $status,
            'cancelled_status_check' => 'cancelled',
            'subscription_id' => $subscriptionId,
        ]);

        self::logActivity((int) $subscription['business_id'], $adminUserId, 'admin_subscription_status_updated', 'Admin updated subscription status to ' . $status);
    }

    public static function adminSubscription(int $subscriptionId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, p.product_key, p.name AS plan_name
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.id = :subscription_id
             LIMIT 1'
        );
        $statement->execute(['subscription_id' => $subscriptionId]);
        $subscription = $statement->fetch();

        return $subscription ?: null;
    }

    public static function subscriptionByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, p.product_key, p.name AS plan_name, p.setup_fee, p.monthly_fee
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.stripe_subscription_id = :stripe_subscription_id
             LIMIT 1'
        );
        $statement->execute(['stripe_subscription_id' => $stripeSubscriptionId]);
        $subscription = $statement->fetch();

        return $subscription ?: null;
    }

    public static function subscriptionByStripeCheckoutSessionId(string $stripeCheckoutSessionId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, p.product_key, p.name AS plan_name, p.setup_fee, p.monthly_fee
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.stripe_checkout_session_id = :stripe_checkout_session_id
             LIMIT 1'
        );
        $statement->execute(['stripe_checkout_session_id' => $stripeCheckoutSessionId]);
        $subscription = $statement->fetch();

        return $subscription ?: null;
    }

    public static function subscriptionByStripeCustomerId(string $stripeCustomerId, string $productKey = '247sp'): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, p.product_key, p.name AS plan_name, p.setup_fee, p.monthly_fee
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.stripe_customer_id = :stripe_customer_id
               AND p.product_key = :product_key
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 1'
        );
        $statement->execute([
            'stripe_customer_id' => $stripeCustomerId,
            'product_key' => $productKey,
        ]);
        $subscription = $statement->fetch();

        return $subscription ?: null;
    }

    public static function updateSubscriptionBillingState(int $subscriptionId, array $fields): void
    {
        $allowed = [
            'status',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_checkout_session_id',
            'stripe_latest_invoice_id',
            'payment_method_status',
            'current_period_start',
            'current_period_end',
            'cancel_at_period_end',
        ];

        $sets = [];
        $params = ['subscription_id' => $subscriptionId];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            if ($field === 'status') {
                $status = (string) $fields[$field];
                if (!in_array($status, self::STATUSES, true)) {
                    throw new InvalidArgumentException('Unsupported subscription status.');
                }
                $params[$field] = $status;
            } else {
                $params[$field] = $fields[$field];
            }

            $sets[] = $field . ' = :' . $field;
        }

        if (count($sets) === 0) {
            return;
        }

        if (($fields['status'] ?? '') === 'active') {
            $sets[] = 'started_at = COALESCE(started_at, NOW())';
            $sets[] = 'cancelled_at = NULL';
        } elseif (($fields['status'] ?? '') === 'cancelled') {
            $sets[] = 'cancelled_at = COALESCE(cancelled_at, NOW())';
        } elseif (array_key_exists('status', $fields)) {
            $sets[] = 'cancelled_at = NULL';
        }

        $sets[] = 'updated_at = NOW()';

        $statement = Database::connection()->prepare(
            'UPDATE subscriptions
             SET ' . implode(', ', $sets) . '
             WHERE id = :subscription_id'
        );
        $statement->execute($params);
    }

    public static function recordStripePayment(int $subscriptionId, array $payment): void
    {
        $stripeInvoiceId = trim((string) ($payment['stripe_invoice_id'] ?? ''));

        if ($stripeInvoiceId !== '') {
            $existing = Database::connection()->prepare(
                'SELECT id FROM payments WHERE stripe_invoice_id = :stripe_invoice_id LIMIT 1'
            );
            $existing->execute(['stripe_invoice_id' => $stripeInvoiceId]);
            $existingPaymentId = (int) ($existing->fetchColumn() ?: 0);

            if ($existingPaymentId > 0) {
                $statement = Database::connection()->prepare(
                    'UPDATE payments
                     SET payment_type = :payment_type,
                         amount = :amount,
                         status = :status,
                         transaction_reference = :transaction_reference,
                         stripe_payment_intent_id = :stripe_payment_intent_id,
                         stripe_checkout_session_id = :stripe_checkout_session_id,
                         stripe_event_id = :stripe_event_id,
                         invoice_url = :invoice_url,
                         updated_at = NOW()
                     WHERE id = :payment_id'
                );
                $statement->execute([
                    'payment_type' => (string) ($payment['payment_type'] ?? 'stripe_invoice'),
                    'amount' => (float) ($payment['amount'] ?? 0),
                    'status' => (string) ($payment['status'] ?? 'pending'),
                    'transaction_reference' => (string) ($payment['transaction_reference'] ?? $stripeInvoiceId),
                    'stripe_payment_intent_id' => self::nullableString($payment['stripe_payment_intent_id'] ?? null),
                    'stripe_checkout_session_id' => self::nullableString($payment['stripe_checkout_session_id'] ?? null),
                    'stripe_event_id' => self::nullableString($payment['stripe_event_id'] ?? null),
                    'invoice_url' => self::nullableString($payment['invoice_url'] ?? null),
                    'payment_id' => $existingPaymentId,
                ]);
                return;
            }
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO payments (
                subscription_id, payment_type, amount, status, transaction_reference,
                stripe_invoice_id, stripe_payment_intent_id, stripe_checkout_session_id,
                stripe_event_id, invoice_url, created_at, updated_at
             ) VALUES (
                :subscription_id, :payment_type, :amount, :status, :transaction_reference,
                :stripe_invoice_id, :stripe_payment_intent_id, :stripe_checkout_session_id,
                :stripe_event_id, :invoice_url, NOW(), NOW()
             )'
        );
        $statement->execute([
            'subscription_id' => $subscriptionId,
            'payment_type' => (string) ($payment['payment_type'] ?? 'stripe_invoice'),
            'amount' => (float) ($payment['amount'] ?? 0),
            'status' => (string) ($payment['status'] ?? 'pending'),
            'transaction_reference' => (string) ($payment['transaction_reference'] ?? ''),
            'stripe_invoice_id' => $stripeInvoiceId !== '' ? $stripeInvoiceId : null,
            'stripe_payment_intent_id' => self::nullableString($payment['stripe_payment_intent_id'] ?? null),
            'stripe_checkout_session_id' => self::nullableString($payment['stripe_checkout_session_id'] ?? null),
            'stripe_event_id' => self::nullableString($payment['stripe_event_id'] ?? null),
            'invoice_url' => self::nullableString($payment['invoice_url'] ?? null),
        ]);
    }

    public static function stripeWebhookEventExists(string $eventId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT status FROM stripe_webhook_events WHERE event_id = :event_id LIMIT 1'
        );
        $statement->execute(['event_id' => $eventId]);

        return (string) ($statement->fetchColumn() ?: '') === 'processed';
    }

    public static function recordStripeWebhookEvent(string $eventId, string $eventType, string $payload): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO stripe_webhook_events (event_id, event_type, status, payload_json, created_at, updated_at)
             VALUES (:event_id, :event_type, :status, :payload_json, NOW(), NOW())'
            . ' ON DUPLICATE KEY UPDATE event_type = VALUES(event_type), status = VALUES(status), payload_json = VALUES(payload_json), error_message = NULL, updated_at = NOW()'
        );
        $statement->execute([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'status' => 'processing',
            'payload_json' => $payload,
        ]);
    }

    public static function markStripeWebhookEvent(string $eventId, string $status, string $errorMessage = ''): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE stripe_webhook_events
             SET status = :status,
                 error_message = :error_message,
                 processed_at = IF(:status_for_processed = :processed_status, NOW(), processed_at),
                 updated_at = NOW()
             WHERE event_id = :event_id'
        );
        $statement->execute([
            'status' => $status,
            'error_message' => $errorMessage !== '' ? $errorMessage : null,
            'status_for_processed' => $status,
            'processed_status' => 'processed',
            'event_id' => $eventId,
        ]);
    }

    private static function logActivity(int $businessId, int $adminUserId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, module_key, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :module_key, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $adminUserId,
            'module_key' => 'billing',
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }

    private static function paymentMethodStatusForSubscriptionStatus(string $status): string
    {
        switch ($status) {
            case 'active':
                return 'complete';
            case 'pending_payment':
                return 'pending';
            case 'past_due':
                return 'failed';
            case 'cancelled':
                return 'cancelled';
            default:
                return 'not_on_file';
        }
    }

    private static function nullableString($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
