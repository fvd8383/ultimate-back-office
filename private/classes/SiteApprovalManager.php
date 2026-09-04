<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteRevisionManager.php';

final class SiteApprovalManager
{
    public const IMPLEMENTED_TYPES = ['customer', 'internal'];
    public const FUTURE_GATED_TYPES = ['production', 'conversion'];

    public static function requestApproval(
        int $actingUserId,
        int $revisionId,
        string $approvalType,
        ?string $comment = null,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        self::assertImplementedType($approvalType);
        $comment = SiteServiceSupport::optionalComment($comment);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actor, $revisionId, $approvalType, $comment, $correlationId
        ): array {
            $revisionProbe = self::revisionIdentity($connection, $revisionId);
            $site = SiteManager::lockSite($connection, (int) $revisionProbe['site_id']);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = SiteRevisionManager::lockRevision($connection, $revisionId);
            if ((int) $revision['site_id'] !== (int) $site['id']) {
                throw new SiteServiceException('conflict', 'Revision ownership is inconsistent.');
            }
            SiteServiceSupport::assertNoNewerMaterialRevision($connection, $revision);
            if ($approvalType === 'customer') {
                if ((string) $revision['materiality'] !== 'material'
                    || (string) $revision['lifecycle_status'] !== 'ready_for_review') {
                    throw new SiteServiceException('invalid_transition', 'Customer approval requires a material review-ready revision.');
                }
                $targetSiteStatus = 'pending_customer';
            } else {
                self::assertInternalApprovalEligible($connection, $revision);
                $targetSiteStatus = 'pending_internal_review';
            }
            $open = $connection->prepare(
                'SELECT id, correlation_id
                 FROM site_approvals
                 WHERE revision_id = :revision_id
                   AND approval_type = :approval_type
                   AND state = :state
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $open->execute([
                'revision_id' => $revisionId,
                'approval_type' => $approvalType,
                'state' => 'requested',
            ]);
            $existing = $open->fetch();
            if ($existing) {
                return [
                    'approval_id' => (int) $existing['id'],
                    'revision_id' => $revisionId,
                    'site_id' => (int) $revision['site_id'],
                    'approval_type' => $approvalType,
                    'state' => 'requested',
                    'idempotent' => true,
                    'correlation_id' => (string) ($existing['correlation_id'] ?: $correlationId),
                ];
            }

            $metadata = [
                'requested_by_user_id' => (int) $actor['acting_user_id'],
                'requested_by_actor_type' => (string) $actor['actor_type'],
            ];
            $insert = $connection->prepare(
                'INSERT INTO site_approvals (
                    site_id, revision_id, approval_type, state, actor_user_id, actor_type,
                    comments, requested_at, correlation_id, metadata_json, created_at
                 ) VALUES (
                    :site_id, :revision_id, :approval_type, :state, :actor_user_id, :actor_type,
                    :comments, NOW(), :correlation_id, :metadata_json, NOW()
                 )'
            );
            $insert->execute([
                'site_id' => (int) $revision['site_id'],
                'revision_id' => $revisionId,
                'approval_type' => $approvalType,
                'state' => 'requested',
                'actor_user_id' => (int) $actor['acting_user_id'],
                'actor_type' => (string) $actor['actor_type'],
                'comments' => $comment,
                'correlation_id' => $correlationId,
                'metadata_json' => SiteServiceSupport::metadata($metadata),
            ]);
            $approvalId = (int) $connection->lastInsertId();
            SiteManager::applyLifecycleTransition(
                $connection, $site, $targetSiteStatus, null, $actor, $correlationId,
                $approvalType . '_approval_requested', true
            );
            SiteServiceSupport::event(
                $connection, (int) $revision['site_id'], $revisionId, $actor,
                'site_approval_requested', $correlationId, null,
                ['approval_id' => $approvalId, 'approval_type' => $approvalType]
            );
            return [
                'approval_id' => $approvalId,
                'revision_id' => $revisionId,
                'site_id' => (int) $revision['site_id'],
                'approval_type' => $approvalType,
                'state' => 'requested',
                'idempotent' => false,
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function decideApproval(
        int $actingUserId,
        int $approvalId,
        string $decision,
        ?string $comment = null,
        ?string $correlationId = null
    ): array {
        SiteServiceSupport::positiveId($approvalId, 'Approval ID');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new SiteServiceException('invalid_request', 'The approval decision must be approved or rejected.');
        }
        $identity = self::approvalIdentity($approvalId);
        self::assertImplementedType((string) $identity['approval_type']);
        $actor = (string) $identity['approval_type'] === 'customer'
            ? SiteAuthorizationPolicy::requireCustomerApproval($actingUserId, (int) $identity['site_id'])
            : SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $comment = SiteServiceSupport::optionalComment($comment);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $approvalId, $decision, $comment, $actor, $correlationId
        ): array {
            $probe = self::approvalIdentityWithConnection($connection, $approvalId);
            $site = SiteManager::lockSite($connection, (int) $probe['site_id']);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = SiteRevisionManager::lockRevision($connection, (int) $probe['revision_id']);
            SiteServiceSupport::assertNoNewerMaterialRevision($connection, $revision);
            $approval = self::lockApproval($connection, $approvalId);
            if ((string) $approval['state'] !== 'requested') {
                throw new SiteServiceException('conflict', 'Only a requested approval can be decided.');
            }
            if ((int) $approval['site_id'] !== (int) $revision['site_id']) {
                throw new SiteServiceException('conflict', 'Approval ownership is inconsistent.');
            }
            $approvalType = (string) $approval['approval_type'];
            if ($decision === 'approved') {
                self::assertApprovalDecisionGate($connection, $revision, $approvalType);
                $supersedesApprovalId = $approvalType === 'customer'
                    ? self::priorCustomerDecision($connection, $revision)
                    : null;
                $update = $connection->prepare(
                    'UPDATE site_approvals
                     SET state = :state, actor_user_id = :actor_user_id, actor_type = :actor_type,
                         comments = :comments, supersedes_approval_id = :supersedes_approval_id,
                         decided_at = NOW()
                     WHERE id = :approval_id AND state = :requested_state'
                );
                $update->execute([
                    'state' => 'approved',
                    'actor_user_id' => (int) $actor['acting_user_id'],
                    'actor_type' => (string) $actor['actor_type'],
                    'comments' => $comment,
                    'supersedes_approval_id' => $supersedesApprovalId,
                    'approval_id' => $approvalId,
                    'requested_state' => 'requested',
                ]);
                if ($update->rowCount() !== 1) {
                    throw new SiteServiceException('conflict', 'The approval was already decided.');
                }
                $revisionTarget = $approvalType === 'customer' ? 'customer_approved' : 'internally_approved';
                $siteTarget = $approvalType === 'customer' ? 'pending_internal_review' : 'approved';
                SiteRevisionManager::applyLifecycleTransition($connection, $revision, $revisionTarget);
                SiteManager::applyLifecycleTransition(
                    $connection, $site, $siteTarget, null, $actor, $correlationId,
                    $approvalType . '_approval_approved', true
                );
                SiteServiceSupport::event(
                    $connection, (int) $approval['site_id'], (int) $approval['revision_id'], $actor,
                    'site_approval_approved', $correlationId, null,
                    ['approval_id' => $approvalId, 'approval_type' => $approvalType,
                        'supersedes_approval_id' => $supersedesApprovalId]
                );
            } else {
                $update = $connection->prepare(
                    'UPDATE site_approvals
                     SET state = :state, actor_user_id = :actor_user_id, actor_type = :actor_type,
                         comments = :comments, decided_at = NOW()
                     WHERE id = :approval_id AND state = :requested_state'
                );
                $update->execute([
                    'state' => 'rejected',
                    'actor_user_id' => (int) $actor['acting_user_id'],
                    'actor_type' => (string) $actor['actor_type'],
                    'comments' => $comment,
                    'approval_id' => $approvalId,
                    'requested_state' => 'requested',
                ]);
                if ($update->rowCount() !== 1) {
                    throw new SiteServiceException('conflict', 'The approval was already decided.');
                }
                self::supersedeCurrentRevisionApprovals(
                    $connection, $approval, $actor, $correlationId, 'approval_rejected'
                );
                SiteRevisionManager::applyLifecycleTransition($connection, $revision, 'changes_requested');
                SiteManager::applyLifecycleTransition(
                    $connection, $site, 'draft', null, $actor, $correlationId, 'approval_rejected', true
                );
                SiteServiceSupport::event(
                    $connection, (int) $approval['site_id'], (int) $approval['revision_id'], $actor,
                    'site_revision_changes_requested', $correlationId, 'approval_rejected',
                    ['approval_id' => $approvalId, 'approval_type' => $approvalType]
                );
                SiteServiceSupport::event(
                    $connection, (int) $approval['site_id'], (int) $approval['revision_id'], $actor,
                    'site_approval_rejected', $correlationId, null,
                    ['approval_id' => $approvalId, 'approval_type' => $approvalType]
                );
            }
            return [
                'approval_id' => $approvalId,
                'revision_id' => (int) $approval['revision_id'],
                'site_id' => (int) $approval['site_id'],
                'approval_type' => $approvalType,
                'state' => $decision,
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function revokeApproval(
        int $actingUserId,
        int $approvalId,
        string $reason,
        ?string $correlationId = null
    ): array {
        SiteServiceSupport::positiveId($approvalId, 'Approval ID');
        $reason = SiteServiceSupport::reason($reason);
        $identity = self::approvalIdentity($approvalId);
        self::assertImplementedType((string) $identity['approval_type']);
        $actor = (string) $identity['approval_type'] === 'customer'
            ? SiteAuthorizationPolicy::requireCustomerApproval($actingUserId, (int) $identity['site_id'])
            : SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $approvalId, $reason, $actor, $correlationId
        ): array {
            $probe = self::approvalIdentityWithConnection($connection, $approvalId);
            $site = SiteManager::lockSite($connection, (int) $probe['site_id']);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = SiteRevisionManager::lockRevision($connection, (int) $probe['revision_id']);
            $approval = self::lockApproval($connection, $approvalId);
            if ((int) $approval['site_id'] !== (int) $site['id']
                || (int) $approval['revision_id'] !== (int) $revision['id']
                || (int) $revision['site_id'] !== (int) $site['id']) {
                throw new SiteServiceException('conflict', 'Approval ownership is inconsistent.');
            }
            if ((string) $approval['state'] !== 'approved' || $approval['revoked_at'] !== null) {
                throw new SiteServiceException('conflict', 'Only a current approved approval can be revoked.');
            }
            $approvalType = (string) $approval['approval_type'];
            self::assertApprovalRevocationGate($revision, $approvalType);

            $update = $connection->prepare(
                'UPDATE site_approvals
                 SET state = :state, revoked_at = NOW(), reason = :reason,
                     actor_user_id = :actor_user_id, actor_type = :actor_type
                 WHERE id = :approval_id AND state = :approved_state AND revoked_at IS NULL'
            );
            $update->execute([
                'state' => 'revoked',
                'reason' => $reason,
                'actor_user_id' => (int) $actor['acting_user_id'],
                'actor_type' => (string) $actor['actor_type'],
                'approval_id' => $approvalId,
                'approved_state' => 'approved',
            ]);
            if ($update->rowCount() !== 1) {
                throw new SiteServiceException('conflict', 'The approval changed concurrently.');
            }

            if ($approvalType === 'customer') {
                self::supersedeRequestedInternalApprovals(
                    $connection,
                    $approval,
                    $correlationId,
                    'customer_approval_revoked'
                );
                if ((string) $revision['lifecycle_status'] === 'internally_approved') {
                    self::supersedeApprovedInternalApproval(
                        $connection,
                        $approval,
                        $correlationId,
                        'customer_approval_revoked'
                    );
                }
                $revisionTarget = 'ready_for_review';
                $siteTarget = 'pending_customer';
                $fallbackCause = 'customer_approval_revoked';
            } elseif ((string) $revision['materiality'] === 'material'
                && SiteServiceSupport::effectiveCustomerApproval($connection, $revision) !== null) {
                $revisionTarget = 'customer_approved';
                $siteTarget = 'pending_internal_review';
                $fallbackCause = 'internal_approval_revoked';
            } else {
                $revisionTarget = 'ready_for_review';
                $siteTarget = 'pending_internal_review';
                $fallbackCause = 'internal_approval_revoked';
            }
            SiteRevisionManager::applyApprovalInvalidationFallback(
                $connection, $revision, $revisionTarget, $fallbackCause
            );
            $siteResult = SiteManager::applyLifecycleTransition(
                $connection, $site, $siteTarget, null, $actor, $correlationId,
                $approvalType . '_approval_revoked', true
            );
            SiteServiceSupport::event(
                $connection, (int) $approval['site_id'], (int) $approval['revision_id'], $actor,
                'site_approval_revoked', $correlationId, $reason,
                ['approval_id' => $approvalId, 'approval_type' => $approvalType]
            );
            return [
                'approval_id' => $approvalId,
                'revision_id' => (int) $approval['revision_id'],
                'site_id' => (int) $approval['site_id'],
                'approval_type' => $approvalType,
                'state' => 'revoked',
                'revision_status' => $revisionTarget,
                'site_status' => (string) $siteResult['lifecycle_status'],
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function approvalsForRevision(int $actingUserId, int $revisionId): array
    {
        $revision = SiteRevisionManager::revisionForActor($actingUserId, $revisionId);
        return SiteServiceSupport::read(static function (object $connection) use ($revisionId, $revision): array {
            $statement = $connection->prepare(
                'SELECT id, site_id, revision_id, approval_type, state, actor_user_id, actor_type,
                        comments, reason, supersedes_approval_id, requested_at, decided_at,
                        revoked_at, correlation_id, metadata_json, created_at
                 FROM site_approvals
                 WHERE revision_id = :revision_id AND site_id = :site_id
                 ORDER BY requested_at ASC, id ASC'
            );
            $statement->execute(['revision_id' => $revisionId, 'site_id' => (int) $revision['site_id']]);
            return $statement->fetchAll();
        });
    }

    /**
     * Advisory read model for admin workflow presentation. Mutation methods still
     * perform every authorization, lock, lifecycle, and approval check themselves.
     */
    public static function eligibilityForRevision(int $actingUserId, int $revisionId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        $siteId = SiteRevisionManager::revisionForActor($actingUserId, $revisionId)['site_id'];

        return SiteServiceSupport::transaction(static function (object $connection) use ($revisionId, $siteId): array {
            $site = SiteManager::lockSite($connection, (int) $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = SiteRevisionManager::lockRevision($connection, $revisionId);
            if ((int) $revision['site_id'] !== (int) $site['id']) {
                throw new SiteServiceException('conflict', 'Revision ownership is inconsistent.');
            }

            $stale = false;
            try {
                SiteServiceSupport::assertNoNewerMaterialRevision($connection, $revision);
            } catch (SiteServiceException $exception) {
                if ($exception->classification() !== 'invalid_transition') {
                    throw $exception;
                }
                $stale = true;
            }

            $effective = null;
            if (in_array((string) $revision['materiality'], ['material', 'non_material'], true)) {
                $effective = SiteServiceSupport::effectiveCustomerApproval($connection, $revision);
            } elseif ((string) $revision['materiality'] === 'undetermined') {
                $candidate = $revision;
                $candidate['materiality'] = 'non_material';
                $effective = SiteServiceSupport::effectiveCustomerApproval($connection, $candidate);
            }

            $canRequestInternal = false;
            if (!$stale) {
                try {
                    self::assertInternalApprovalEligible($connection, $revision);
                    $canRequestInternal = true;
                } catch (SiteServiceException $exception) {
                    if (!in_array($exception->classification(), ['invalid_transition', 'conflict'], true)) {
                        throw $exception;
                    }
                }
            }

            return [
                'effective_customer_approval_id' => $effective,
                'has_effective_customer_baseline' => $effective !== null,
                'has_newer_material_revision' => $stale,
                'can_request_customer_review' => !$stale
                    && (string) $revision['materiality'] === 'material'
                    && (string) $revision['lifecycle_status'] === 'ready_for_review',
                'can_request_internal_review' => $canRequestInternal,
            ];
        });
    }

    private static function assertImplementedType(string $approvalType): void
    {
        if (in_array($approvalType, self::FUTURE_GATED_TYPES, true)) {
            throw new SiteServiceException('future_gate_required', 'That approval type requires a later website-platform milestone.');
        }
        if (!in_array($approvalType, self::IMPLEMENTED_TYPES, true)) {
            throw new SiteServiceException('invalid_request', 'The approval type is invalid.');
        }
    }

    private static function approvalIdentity(int $approvalId): array
    {
        return SiteServiceSupport::read(
            static fn (object $connection): array => self::approvalIdentityWithConnection($connection, $approvalId)
        );
    }

    private static function approvalIdentityWithConnection(object $connection, int $approvalId): array
    {
        $statement = $connection->prepare(
            'SELECT id, site_id, revision_id, approval_type FROM site_approvals WHERE id = :approval_id LIMIT 1'
        );
        $statement->execute(['approval_id' => $approvalId]);
        $approval = $statement->fetch();
        if (!$approval) {
            throw new SiteServiceException('not_found', 'The approval was not found.');
        }
        return $approval;
    }

    private static function revisionIdentity(object $connection, int $revisionId): array
    {
        $statement = $connection->prepare(
            'SELECT id, site_id FROM site_revisions WHERE id = :revision_id LIMIT 1'
        );
        $statement->execute(['revision_id' => $revisionId]);
        $revision = $statement->fetch();
        if (!$revision) {
            throw new SiteServiceException('not_found', 'The revision was not found.');
        }
        return $revision;
    }

    private static function lockApproval(object $connection, int $approvalId): array
    {
        $statement = $connection->prepare(
            'SELECT id, site_id, revision_id, approval_type, state, actor_user_id, actor_type,
                    comments, reason, supersedes_approval_id, requested_at, decided_at,
                    revoked_at, correlation_id, metadata_json
             FROM site_approvals WHERE id = :approval_id FOR UPDATE'
        );
        $statement->execute(['approval_id' => $approvalId]);
        $approval = $statement->fetch();
        if (!$approval) {
            throw new SiteServiceException('not_found', 'The approval was not found.');
        }
        return $approval;
    }

    private static function assertApprovalDecisionGate(object $connection, array $revision, string $approvalType): void
    {
        if ($approvalType === 'customer') {
            if ((string) $revision['materiality'] !== 'material'
                || (string) $revision['lifecycle_status'] !== 'ready_for_review') {
                throw new SiteServiceException('invalid_transition', 'Customer approval requires a material review-ready revision.');
            }
            return;
        }
        if ((string) $revision['materiality'] === 'material') {
            self::assertInternalApprovalEligible($connection, $revision);
            return;
        }
        self::assertInternalApprovalEligible($connection, $revision);
    }

    private static function assertInternalApprovalEligible(object $connection, array $revision): void
    {
        if ((string) $revision['materiality'] === 'material') {
            if ((string) $revision['lifecycle_status'] !== 'customer_approved'
                || SiteServiceSupport::effectiveCustomerApproval($connection, $revision) === null) {
                throw new SiteServiceException('invalid_transition', 'Material revisions require current customer approval first.');
            }
            return;
        }
        if ((string) $revision['materiality'] !== 'non_material'
            || (string) $revision['lifecycle_status'] !== 'ready_for_review'
            || SiteServiceSupport::effectiveCustomerApproval($connection, $revision) === null) {
            throw new SiteServiceException(
                'invalid_transition',
                'A non-material revision requires an existing customer-approved public baseline.'
            );
        }
    }

    private static function assertApprovalRevocationGate(array $revision, string $approvalType): void
    {
        if ((string) $revision['lifecycle_status'] === 'published') {
            throw new SiteServiceException('future_gate_required', 'Published approval history requires the later deployment rollback workflow.');
        }
        if ($approvalType === 'customer'
            && !in_array((string) $revision['lifecycle_status'], ['customer_approved', 'internally_approved'], true)) {
            throw new SiteServiceException('invalid_transition', 'Customer approval can only be revoked before publication.');
        }
        if ($approvalType === 'internal' && (string) $revision['lifecycle_status'] !== 'internally_approved') {
            throw new SiteServiceException('invalid_transition', 'The revision is not currently internally approved.');
        }
    }

    private static function priorCustomerDecision(object $connection, array $revision): ?int
    {
        $statement = $connection->prepare(
            '/* site-m2:prior-customer-decision */
             SELECT sa.id
             FROM site_approvals sa
             INNER JOIN site_revisions sr ON sr.id = sa.revision_id AND sr.site_id = sa.site_id
             WHERE sa.site_id = :site_id
               AND sr.revision_number < :current_revision_number
               AND sa.approval_type = :approval_type
               AND sa.state IN (:superseded_state, :revoked_state)
             ORDER BY COALESCE(sa.revoked_at, sa.decided_at, sa.requested_at) DESC, sa.id DESC
             LIMIT 1'
        );
        $statement->execute([
            'site_id' => (int) $revision['site_id'],
            'current_revision_number' => (int) $revision['revision_number'],
            'approval_type' => 'customer',
            'superseded_state' => 'superseded',
            'revoked_state' => 'revoked',
        ]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private static function supersedeRequestedInternalApprovals(
        object $connection,
        array $customerApproval,
        string $correlationId,
        string $reason
    ): void {
        $statement = $connection->prepare(
            'SELECT id
             FROM site_approvals
             WHERE revision_id = :revision_id AND site_id = :site_id
               AND approval_type = :approval_type AND state = :state
             FOR UPDATE'
        );
        $statement->execute([
            'revision_id' => (int) $customerApproval['revision_id'],
            'site_id' => (int) $customerApproval['site_id'],
            'approval_type' => 'internal',
            'state' => 'requested',
        ]);
        foreach ($statement->fetchAll() as $requested) {
            $update = $connection->prepare(
                'UPDATE site_approvals SET state = :state, reason = :reason, decided_at = NOW()
                 WHERE id = :approval_id AND state = :requested_state'
            );
            $update->execute([
                'state' => 'superseded',
                'reason' => $reason,
                'approval_id' => (int) $requested['id'],
                'requested_state' => 'requested',
            ]);
            if ($update->rowCount() !== 1) {
                throw new SiteServiceException('conflict', 'The dependent internal request changed concurrently.');
            }
            SiteServiceSupport::event(
                $connection, (int) $customerApproval['site_id'], (int) $customerApproval['revision_id'],
                SiteServiceSupport::systemActor(), 'site_approval_superseded', $correlationId, $reason,
                ['approval_id' => (int) $requested['id'], 'approval_type' => 'internal']
            );
        }
    }

    private static function supersedeApprovedInternalApproval(
        object $connection,
        array $customerApproval,
        string $correlationId,
        string $reason
    ): void {
        $statement = $connection->prepare(
            '/* site-m2:supersede-dependent-internal-approval */
             SELECT id
             FROM site_approvals
             WHERE revision_id = :revision_id AND site_id = :site_id
               AND approval_type = :approval_type
               AND state = :state
               AND revoked_at IS NULL
             FOR UPDATE'
        );
        $statement->execute([
            'revision_id' => (int) $customerApproval['revision_id'],
            'site_id' => (int) $customerApproval['site_id'],
            'approval_type' => 'internal',
            'state' => 'approved',
        ]);
        $rows = $statement->fetchAll();
        if (count($rows) !== 1) {
            throw new SiteServiceException('conflict', 'The dependent internal approval is missing or ambiguous.');
        }
        $internalApprovalId = (int) $rows[0]['id'];
        $update = $connection->prepare(
            'UPDATE site_approvals
             SET state = :state, reason = :reason
             WHERE id = :approval_id AND state = :approved_state AND revoked_at IS NULL'
        );
        $update->execute([
            'state' => 'superseded',
            'reason' => $reason,
            'approval_id' => $internalApprovalId,
            'approved_state' => 'approved',
        ]);
        if ($update->rowCount() !== 1) {
            throw new SiteServiceException('conflict', 'The dependent internal approval changed concurrently.');
        }
        SiteServiceSupport::event(
            $connection, (int) $customerApproval['site_id'], (int) $customerApproval['revision_id'],
            SiteServiceSupport::systemActor(), 'site_approval_superseded', $correlationId, $reason,
            ['approval_id' => $internalApprovalId, 'approval_type' => 'internal']
        );
    }

    private static function supersedeCurrentRevisionApprovals(
        object $connection,
        array $rejectedApproval,
        array $actor,
        string $correlationId,
        string $reason
    ): void {
        $statement = $connection->prepare(
            'SELECT id, approval_type
             FROM site_approvals
             WHERE revision_id = :revision_id AND site_id = :site_id
               AND state = :state AND revoked_at IS NULL
             FOR UPDATE'
        );
        $statement->execute([
            'revision_id' => (int) $rejectedApproval['revision_id'],
            'site_id' => (int) $rejectedApproval['site_id'],
            'state' => 'approved',
        ]);
        foreach ($statement->fetchAll() as $approval) {
            $update = $connection->prepare(
                'UPDATE site_approvals SET state = :state, reason = :reason
                 WHERE id = :approval_id AND state = :approved_state'
            );
            $update->execute([
                'state' => 'superseded',
                'reason' => $reason,
                'approval_id' => (int) $approval['id'],
                'approved_state' => 'approved',
            ]);
            SiteServiceSupport::event(
                $connection, (int) $rejectedApproval['site_id'], (int) $rejectedApproval['revision_id'],
                $actor, 'site_approval_superseded', $correlationId, $reason,
                ['approval_id' => (int) $approval['id'], 'approval_type' => (string) $approval['approval_type']]
            );
        }
    }
}
