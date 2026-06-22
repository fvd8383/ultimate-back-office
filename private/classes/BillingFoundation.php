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
            'SELECT s.id,
                    s.business_id,
                    s.status,
                    s.started_at,
                    s.cancelled_at,
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
             ORDER BY s.created_at DESC, s.id DESC'
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
                 started_at = IF(started_at IS NULL AND :status_for_started <> :cancelled_status, NOW(), started_at),
                 cancelled_at = IF(:status_for_cancelled = :cancelled_status_check, NOW(), NULL)
             WHERE id = :subscription_id'
        );
        $statement->execute([
            'status' => $status,
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
}
