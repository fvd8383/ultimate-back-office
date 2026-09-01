<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteAuthorizationPolicy.php';

final class SiteManager
{
    public const PURPOSES = ['247sp', 'emd', 'internal_demo'];
    public const FUTURE_GATED_STATES = ['active', 'cancellation_pending', 'conversion_pending'];
    public const TRANSITIONS = [
        'draft' => ['demo', 'pending_customer', 'pending_internal_review', 'approved', 'suspended', 'archived'],
        'demo' => ['draft', 'suspended', 'archived'],
        'pending_customer' => ['draft', 'pending_internal_review', 'suspended', 'archived'],
        'pending_internal_review' => ['draft', 'pending_customer', 'approved', 'suspended', 'archived'],
        'approved' => ['draft', 'pending_customer', 'pending_internal_review', 'suspended', 'archived'],
        'suspended' => ['draft', 'demo', 'pending_customer', 'pending_internal_review', 'approved', 'archived'],
        'archived' => [],
    ];

    public static function createSite(
        int $actingUserId,
        string $purpose,
        ?int $businessId = null,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $purpose = trim($purpose);
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new SiteServiceException('invalid_request', 'The site purpose is invalid.');
        }
        if ($purpose === '247sp' && ($businessId === null || $businessId < 1)) {
            throw new SiteServiceException('invalid_request', 'A valid business is required for a 247SP site.');
        }
        if ($purpose !== '247sp' && $businessId !== null) {
            throw new SiteServiceException('invalid_request', 'This site purpose does not accept a customer business association.');
        }
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actor, $actingUserId, $purpose, $businessId, $correlationId
        ): array {
            if ($purpose === '247sp') {
                self::lockEligible247spBusiness($connection, (int) $businessId);
                $existing = $connection->prepare(
                    'SELECT s.id
                     FROM site_business_associations sba
                     INNER JOIN sites s ON s.id = sba.site_id AND s.purpose = :purpose
                     WHERE sba.business_id = :business_id
                       AND sba.association_role = :association_role
                       AND sba.status = :association_status
                     FOR UPDATE'
                );
                $existing->execute([
                    'purpose' => '247sp',
                    'business_id' => $businessId,
                    'association_role' => 'customer',
                    'association_status' => 'active',
                ]);
                if ($existing->fetch()) {
                    throw new SiteServiceException('conflict', 'The business already has an active customer-associated 247SP site.');
                }
            }

            $siteKey = SiteServiceSupport::uuidV4();
            $insert = $connection->prepare(
                'INSERT INTO sites (
                    site_key, purpose, lifecycle_status, created_by_user_id,
                    lock_version, created_at, updated_at
                 ) VALUES (:site_key, :purpose, :status, :actor_id, 0, NOW(), NOW())'
            );
            $insert->execute([
                'site_key' => $siteKey,
                'purpose' => $purpose,
                'status' => 'draft',
                'actor_id' => $actingUserId,
            ]);
            $siteId = (int) $connection->lastInsertId();

            if ($purpose === '247sp') {
                $association = $connection->prepare(
                    'INSERT INTO site_business_associations (
                        site_id, business_id, association_role, status, effective_at,
                        created_by_user_id, correlation_id, created_at, updated_at
                     ) VALUES (
                        :site_id, :business_id, :association_role, :status, NOW(),
                        :actor_id, :correlation_id, NOW(), NOW()
                     )'
                );
                $association->execute([
                    'site_id' => $siteId,
                    'business_id' => $businessId,
                    'association_role' => 'customer',
                    'status' => 'active',
                    'actor_id' => $actingUserId,
                    'correlation_id' => $correlationId,
                ]);
            }

            SiteServiceSupport::event(
                $connection,
                $siteId,
                null,
                $actor,
                'site_created',
                $correlationId,
                null,
                ['purpose' => $purpose, 'business_id' => $businessId]
            );
            return [
                'site_id' => $siteId,
                'site_key' => $siteKey,
                'purpose' => $purpose,
                'lifecycle_status' => 'draft',
                'lock_version' => 0,
                'business_id' => $businessId,
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function transitionLifecycle(
        int $actingUserId,
        int $siteId,
        string $targetStatus,
        int $expectedLockVersion,
        string $reason,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        if ($expectedLockVersion < 0) {
            throw new SiteServiceException('invalid_request', 'Expected lock version cannot be negative.');
        }
        $reason = SiteServiceSupport::reason($reason);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $siteId, $targetStatus, $expectedLockVersion, $reason, $actor, $correlationId
        ): array {
            $site = self::lockSite($connection, $siteId);
            $updated = self::applyLifecycleTransition(
                $connection,
                $site,
                $targetStatus,
                $expectedLockVersion,
                $actor,
                $correlationId,
                $reason
            );
            return $updated + ['correlation_id' => $correlationId];
        });
    }

    /** @internal Used by revision/approval services while they own the surrounding transaction. */
    public static function applyLifecycleTransition(
        object $connection,
        array $site,
        string $targetStatus,
        ?int $expectedLockVersion,
        array $actor,
        string $correlationId,
        string $reason
    ): array {
        $siteId = (int) $site['id'];
        $current = (string) $site['lifecycle_status'];
        $targetStatus = trim($targetStatus);
        if (in_array($targetStatus, self::FUTURE_GATED_STATES, true)) {
            throw new SiteServiceException('future_gate_required', 'That site state requires a later website-platform milestone.');
        }
        if ($expectedLockVersion !== null && (int) $site['lock_version'] !== $expectedLockVersion) {
            throw new SiteServiceException('stale_write', 'The site changed after it was loaded.');
        }
        if ($targetStatus === $current && in_array($targetStatus, ['pending_customer', 'pending_internal_review'], true)) {
            if ($targetStatus === 'pending_customer') {
                self::assertPendingCustomerGate($connection, $siteId);
            } else {
                self::assertPendingInternalGate($connection, $siteId);
            }
            return [
                'site_id' => $siteId,
                'lifecycle_status' => $current,
                'lock_version' => (int) $site['lock_version'],
            ];
        }
        if (!array_key_exists($current, self::TRANSITIONS)
            || !in_array($targetStatus, self::TRANSITIONS[$current], true)) {
            throw new SiteServiceException('invalid_transition', 'The requested site lifecycle transition is not allowed.');
        }
        if ($targetStatus === 'demo' && (string) $site['purpose'] !== 'internal_demo') {
            throw new SiteServiceException('invalid_transition', 'Only an internal demo site can enter demo state.');
        }
        if ($targetStatus === 'archived' && $site['current_published_revision_id'] !== null) {
            throw new SiteServiceException('future_gate_required', 'A published site requires later deployment retirement before archive.');
        }
        if ($targetStatus === 'pending_customer') {
            self::assertPendingCustomerGate($connection, $siteId);
        }
        if ($targetStatus === 'pending_internal_review') {
            self::assertPendingInternalGate($connection, $siteId);
        }
        if ($targetStatus === 'approved') {
            self::assertApprovalGate($connection, $siteId);
        }

        $update = $connection->prepare(
            'UPDATE sites
             SET lifecycle_status = :target_status,
                 suspended_at = CASE WHEN :target_status_for_suspend = :suspended THEN NOW() ELSE suspended_at END,
                 archived_at = CASE WHEN :target_status_for_archive = :archived THEN NOW() ELSE archived_at END,
                 lock_version = lock_version + 1,
                 updated_at = NOW()
             WHERE id = :site_id AND lock_version = :lock_version'
        );
        $update->execute([
            'target_status' => $targetStatus,
            'target_status_for_suspend' => $targetStatus,
            'suspended' => 'suspended',
            'target_status_for_archive' => $targetStatus,
            'archived' => 'archived',
            'site_id' => $siteId,
            'lock_version' => (int) $site['lock_version'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new SiteServiceException('stale_write', 'The site changed after it was loaded.');
        }
        SiteServiceSupport::event(
            $connection,
            $siteId,
            null,
            $actor,
            'site_lifecycle_changed',
            $correlationId,
            $reason,
            ['from' => $current, 'to' => $targetStatus, 'lock_version' => (int) $site['lock_version'] + 1]
        );
        return [
            'site_id' => $siteId,
            'lifecycle_status' => $targetStatus,
            'lock_version' => (int) $site['lock_version'] + 1,
        ];
    }

    /** @internal */
    public static function lockSite(object $connection, int $siteId): array
    {
        $statement = $connection->prepare(
            'SELECT id, site_key, purpose, lifecycle_status, current_published_revision_id, lock_version
             FROM sites WHERE id = :site_id FOR UPDATE'
        );
        $statement->execute(['site_id' => $siteId]);
        $site = $statement->fetch();
        if (!$site) {
            throw new SiteServiceException('not_found', 'The site was not found.');
        }
        return $site;
    }

    public static function siteForActor(int $actingUserId, int $siteId): array
    {
        SiteAuthorizationPolicy::requireSiteRead($actingUserId, $siteId);
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): array {
            $statement = $connection->prepare(
                'SELECT id, site_key, purpose, lifecycle_status, current_published_revision_id,
                        created_by_user_id, suspended_at, archived_at, lock_version, created_at, updated_at
                 FROM sites WHERE id = :site_id LIMIT 1'
            );
            $statement->execute(['site_id' => $siteId]);
            $site = $statement->fetch();
            if (!$site) {
                throw new SiteServiceException('not_found', 'The site was not found.');
            }
            return $site;
        });
    }

    public static function activeBusinessAssociation(int $actingUserId, int $siteId): ?array
    {
        SiteAuthorizationPolicy::requireSiteRead($actingUserId, $siteId);
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): ?array {
            $statement = $connection->prepare(
                'SELECT id, site_id, business_id, association_role, status, effective_at, ended_at
                 FROM site_business_associations
                 WHERE site_id = :site_id AND association_role = :role AND status = :status
                 LIMIT 1'
            );
            $statement->execute(['site_id' => $siteId, 'role' => 'customer', 'status' => 'active']);
            return $statement->fetch() ?: null;
        });
    }

    public static function sitesForBusiness(int $actingUserId, int $businessId): array
    {
        SiteServiceSupport::positiveId($businessId, 'Business ID');
        $actor = SiteAuthorizationPolicy::actorContext($actingUserId);
        return SiteServiceSupport::read(static function (object $connection) use (
            $actor, $actingUserId, $businessId
        ): array {
            if (!$actor['is_internal_admin']) {
                $access = $connection->prepare(
                    'SELECT s.id
                     FROM sites s
                     INNER JOIN site_business_associations sba ON sba.site_id = s.id
                     WHERE sba.business_id = :business_id AND sba.status = :status
                     ORDER BY s.id ASC'
                );
                $access->execute(['business_id' => $businessId, 'status' => 'active']);
                $ids = array_map('intval', array_column($access->fetchAll(), 'id'));
                if ($ids === []) {
                    throw new SiteServiceException('unauthorized', 'The business sites are not available to this user.');
                }
                foreach ($ids as $siteId) {
                    SiteAuthorizationPolicy::requireSiteRead($actingUserId, $siteId);
                }
            }
            $statement = $connection->prepare(
                'SELECT s.id, s.site_key, s.purpose, s.lifecycle_status, s.lock_version,
                        s.current_published_revision_id
                 FROM site_business_associations sba
                 INNER JOIN sites s ON s.id = sba.site_id
                 WHERE sba.business_id = :business_id AND sba.status = :status
                 ORDER BY s.id ASC'
            );
            $statement->execute(['business_id' => $businessId, 'status' => 'active']);
            return $statement->fetchAll();
        });
    }

    private static function lockEligible247spBusiness(object $connection, int $businessId): void
    {
        $statement = $connection->prepare(
            'SELECT b.id, b.status, b.is_suspended,
                    EXISTS (
                        SELECT 1 FROM business_modules bm
                        INNER JOIN modules m ON m.id = bm.module_id
                        WHERE bm.business_id = b.id
                          AND bm.status = :module_status
                          AND m.module_key = :module_key
                          AND m.is_active = 1
                    ) AS has_module
             FROM businesses b WHERE b.id = :business_id FOR UPDATE'
        );
        $statement->execute([
            'business_id' => $businessId,
            'module_status' => 'active',
            'module_key' => '247sp',
        ]);
        $business = $statement->fetch();
        if (!$business) {
            throw new SiteServiceException('not_found', 'The business was not found.');
        }
        if ((string) $business['status'] !== 'active' || (int) $business['is_suspended'] === 1) {
            throw new SiteServiceException('conflict', 'The business is not eligible for site creation.');
        }
        if ((int) $business['has_module'] !== 1) {
            throw new SiteServiceException('conflict', 'Active 247SP module access is required.');
        }
    }

    private static function assertApprovalGate(object $connection, int $siteId): void
    {
        $statement = $connection->prepare(
            'SELECT sr.id, sr.materiality,
                    EXISTS (
                        SELECT 1 FROM site_approvals ia
                        WHERE ia.revision_id = sr.id AND ia.approval_type = :internal_type
                          AND ia.state = :approved_state AND ia.revoked_at IS NULL
                    ) AS internal_ok,
                    EXISTS (
                        SELECT 1 FROM site_approvals ca
                        WHERE ca.revision_id = sr.id AND ca.approval_type = :customer_type
                          AND ca.state = :approved_state_2 AND ca.revoked_at IS NULL
                    ) AS customer_ok
             FROM site_revisions sr
             WHERE sr.site_id = :site_id AND sr.lifecycle_status = :revision_status
             ORDER BY sr.revision_number DESC LIMIT 1'
        );
        $statement->execute([
            'internal_type' => 'internal',
            'approved_state' => 'approved',
            'customer_type' => 'customer',
            'approved_state_2' => 'approved',
            'site_id' => $siteId,
            'revision_status' => 'internally_approved',
        ]);
        $revision = $statement->fetch();
        if (!$revision || (int) $revision['internal_ok'] !== 1
            || ((string) $revision['materiality'] === 'material' && (int) $revision['customer_ok'] !== 1)) {
            throw new SiteServiceException('invalid_transition', 'An eligible internally approved revision is required.');
        }
    }

    private static function assertPendingCustomerGate(object $connection, int $siteId): void
    {
        $statement = $connection->prepare(
            'SELECT 1
             FROM site_revisions sr
             WHERE sr.site_id = :site_id
               AND sr.lifecycle_status = :revision_status
               AND sr.materiality = :materiality
             ORDER BY sr.revision_number DESC LIMIT 1'
        );
        $statement->execute([
            'site_id' => $siteId,
            'revision_status' => 'ready_for_review',
            'materiality' => 'material',
        ]);
        if (!$statement->fetchColumn()) {
            throw new SiteServiceException('invalid_transition', 'A material revision awaiting customer decision is required.');
        }
    }

    private static function assertPendingInternalGate(object $connection, int $siteId): void
    {
        $statement = $connection->prepare(
            'SELECT 1
             FROM site_revisions sr
             WHERE sr.site_id = :site_id
               AND (
                    (sr.materiality = :material
                     AND sr.lifecycle_status = :customer_approved)
                 OR (sr.materiality = :non_material
                     AND sr.lifecycle_status = :ready_for_review)
               )
             ORDER BY sr.revision_number DESC LIMIT 1'
        );
        $statement->execute([
            'site_id' => $siteId,
            'material' => 'material',
            'customer_approved' => 'customer_approved',
            'non_material' => 'non_material',
            'ready_for_review' => 'ready_for_review',
        ]);
        if (!$statement->fetchColumn()) {
            throw new SiteServiceException('invalid_transition', 'An eligible revision awaiting internal review is required.');
        }
    }
}
