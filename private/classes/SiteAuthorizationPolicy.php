<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteServiceSupport.php';

final class SiteAuthorizationPolicy
{
    private const INTERNAL_ADMIN_ROLES = ['Super Admin', 'Admin'];
    private const CUSTOMER_APPROVER_ROLES = ['Owner', 'Admin'];

    public static function actorContext(int $actingUserId, ?object $lockedConnection = null): array
    {
        SiteServiceSupport::positiveId($actingUserId, 'Acting user ID');
        $resolve = static function (object $connection) use ($actingUserId, $lockedConnection): array {
            if ($lockedConnection !== null) {
                if (!$connection->inTransaction()) throw new SiteServiceException('conflict', 'Current authorization requires the owning transaction.');
                // Lock the parent before the role range. The FK also blocks new grants
                // while this user lock is held, including under READ COMMITTED.
                $user = $connection->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
                $user->execute(['user_id' => $actingUserId]);
                $user->fetchAll();
                $grants = $connection->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id ORDER BY role_id FOR UPDATE');
                $grants->execute(['user_id' => $actingUserId]);
                $grants->fetchAll();
                // Protect classification changes to already assigned role definitions,
                // including a scope change from a currently non-internal role.
                $roles = $connection->prepare('SELECT r.id FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :user_id ORDER BY r.id FOR SHARE');
                $roles->execute(['user_id' => $actingUserId]);
                $roles->fetchAll();
            }
            $statement = $connection->prepare(
                'SELECT u.id, u.status, r.name AS role_name
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id AND r.scope = :internal_scope
                 WHERE u.id = :user_id' . ($lockedConnection === null ? '' : ' FOR SHARE')
            );
            $statement->execute(['user_id' => $actingUserId, 'internal_scope' => 'internal']);
            $rows = $statement->fetchAll();
            if ($rows === [] || (string) $rows[0]['status'] !== 'active') {
                throw new SiteServiceException('unauthorized', 'An active user is required.');
            }

            $roles = array_values(array_filter(array_column($rows, 'role_name'), 'is_string'));
            $isSuperAdmin = in_array('Super Admin', $roles, true);
            $isInternalAdmin = array_intersect(self::INTERNAL_ADMIN_ROLES, $roles) !== [];
            return [
                'acting_user_id' => $actingUserId,
                'actor_type' => $isSuperAdmin ? 'super_admin' : ($isInternalAdmin ? 'internal_admin' : 'customer'),
                'is_super_admin' => $isSuperAdmin,
                'is_internal_admin' => $isInternalAdmin,
            ];
        };
        return $lockedConnection === null ? SiteServiceSupport::read($resolve) : $resolve($lockedConnection);
    }

    public static function requireInternalAdmin(int $actingUserId): array
    {
        $actor = self::actorContext($actingUserId);
        if (!$actor['is_internal_admin']) {
            throw new SiteServiceException('unauthorized', 'Internal administrator access is required.');
        }
        return $actor;
    }

    public static function requireSuperAdmin(int $actingUserId): array
    {
        $actor = self::actorContext($actingUserId);
        if (!$actor['is_super_admin']) {
            throw new SiteServiceException('unauthorized', 'Super Admin access is required.');
        }
        return $actor;
    }

    public static function requireSiteRead(int $actingUserId, int $siteId): array
    {
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        $actor = self::actorContext($actingUserId);
        if ($actor['is_internal_admin']) {
            return $actor + ['site_id' => $siteId, 'business_id' => null];
        }
        return $actor + self::customerSiteContext($actingUserId, $siteId, false);
    }

    public static function requireCustomerApproval(int $actingUserId, int $siteId, ?object $lockedConnection = null): array
    {
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        $actor = self::actorContext($actingUserId, $lockedConnection);
        if ($actor['is_internal_admin']) {
            throw new SiteServiceException('unauthorized', 'Internal administrators cannot act as customer approvers.');
        }
        return $actor + self::customerSiteContext($actingUserId, $siteId, true, $lockedConnection);
    }

    private static function customerSiteContext(int $actingUserId, int $siteId, bool $approvalRequired, ?object $lockedConnection = null): array
    {
        $resolve = static function (object $connection) use (
            $actingUserId, $siteId, $approvalRequired, $lockedConnection
        ): array {
            $statement = $connection->prepare(
                'SELECT s.id AS site_id, sba.business_id, bu.is_owner, r.name AS business_role
             FROM sites s
             INNER JOIN site_business_associations sba
                ON sba.site_id = s.id
               AND sba.association_role = :association_role
               AND sba.status = :association_status
             INNER JOIN businesses b
                ON b.id = sba.business_id
               AND b.status = :business_status
               AND b.is_suspended = 0
             INNER JOIN business_users bu
                ON bu.business_id = b.id
               AND bu.user_id = :user_id
               AND bu.status = :membership_status
             LEFT JOIN roles r ON r.id = bu.role_id AND r.scope = :business_scope
             INNER JOIN business_modules bm
                ON bm.business_id = b.id
               AND bm.status = :module_status
             INNER JOIN modules m
                ON m.id = bm.module_id
               AND m.module_key = :module_key
               AND m.is_active = 1
             WHERE s.id = :site_id
               AND s.purpose = :purpose
                 LIMIT 1' . ($lockedConnection === null ? '' : ' FOR SHARE')
            );
            $statement->execute([
                'association_role' => 'customer',
                'association_status' => 'active',
                'business_status' => 'active',
                'user_id' => $actingUserId,
                'membership_status' => 'active',
                'business_scope' => 'business',
                'module_status' => 'active',
                'module_key' => '247sp',
                'site_id' => $siteId,
                'purpose' => '247sp',
            ]);
            $context = $statement->fetch();
            if (!$context) {
                throw new SiteServiceException('unauthorized', 'The site is not available to this user.');
            }
            if ($approvalRequired
                && (int) $context['is_owner'] !== 1
                && !in_array((string) ($context['business_role'] ?? ''), self::CUSTOMER_APPROVER_ROLES, true)) {
                throw new SiteServiceException('unauthorized', 'Customer Owner or Admin authority is required.');
            }
            return [
                'site_id' => (int) $context['site_id'],
                'business_id' => (int) $context['business_id'],
                'is_owner' => (int) $context['is_owner'] === 1,
                'business_role' => $context['business_role'],
            ];
        };
        return $lockedConnection === null ? SiteServiceSupport::read($resolve) : $resolve($lockedConnection);
    }
}
