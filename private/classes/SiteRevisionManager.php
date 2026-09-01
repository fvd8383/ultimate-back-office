<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteManager.php';

final class SiteRevisionManager
{
    public const TRANSITIONS = [
        'draft' => ['validation_failed', 'ready_for_review'],
        'validation_failed' => ['draft', 'ready_for_review'],
        'restored' => ['ready_for_review'],
        'ready_for_review' => ['changes_requested', 'customer_approved', 'internally_approved'],
        'customer_approved' => ['changes_requested', 'internally_approved'],
        'internally_approved' => ['changes_requested'],
        'published' => ['superseded'],
        'changes_requested' => [],
        'superseded' => [],
    ];
    public const MUTABLE_COMPOSITION_STATES = ['draft', 'validation_failed'];
    private const RESTORABLE_STATES = ['published', 'superseded', 'internally_approved'];

    public static function createRevision(int $actingUserId, array $input): array
    {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $siteId = SiteServiceSupport::positiveId((int) ($input['site_id'] ?? 0), 'Site ID');
        $correlationId = SiteServiceSupport::correlationId($input['correlation_id'] ?? null);
        $snapshot = self::validatedSnapshotInput($input);
        $basedOn = self::nullablePositiveId($input['based_on_revision_id'] ?? null, 'Based-on revision ID');

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actingUserId, $actor, $siteId, $correlationId, $snapshot, $basedOn
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            self::assertSameSiteRevision($connection, $siteId, $basedOn, 'The based-on revision is not part of this site.');
            self::assertSameSiteBrief($connection, $siteId, $snapshot['generation_brief_id']);
            $revisionNumber = self::nextRevisionNumber($connection, $siteId);
            $insert = $connection->prepare(
                'INSERT INTO site_revisions (
                    site_id, revision_number, lifecycle_status, based_on_revision_id,
                    restored_from_revision_id, generation_brief_id, materiality,
                    snapshot_schema_version, facts_snapshot_json, source_references_json,
                    snapshot_hash, created_by_user_id, correlation_id, created_at, updated_at
                 ) VALUES (
                    :site_id, :revision_number, :status, :based_on_revision_id,
                    NULL, :generation_brief_id, :materiality,
                    :snapshot_schema_version, :facts_snapshot_json, :source_references_json,
                    :snapshot_hash, :actor_id, :correlation_id, NOW(), NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId,
                'revision_number' => $revisionNumber,
                'status' => 'draft',
                'based_on_revision_id' => $basedOn,
                'generation_brief_id' => $snapshot['generation_brief_id'],
                'materiality' => 'undetermined',
                'snapshot_schema_version' => $snapshot['snapshot_schema_version'],
                'facts_snapshot_json' => $snapshot['facts_snapshot_json'],
                'source_references_json' => $snapshot['source_references_json'],
                'snapshot_hash' => $snapshot['snapshot_hash'],
                'actor_id' => $actingUserId,
                'correlation_id' => $correlationId,
            ]);
            $revisionId = (int) $connection->lastInsertId();
            SiteServiceSupport::event(
                $connection, $siteId, $revisionId, $actor, 'site_revision_created', $correlationId,
                null, ['revision_number' => $revisionNumber, 'based_on_revision_id' => $basedOn]
            );
            return [
                'revision_id' => $revisionId,
                'site_id' => $siteId,
                'revision_number' => $revisionNumber,
                'lifecycle_status' => 'draft',
                'materiality' => 'undetermined',
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function updateDraftSnapshot(
        int $actingUserId,
        int $revisionId,
        array $changes,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        if ($changes === []) {
            throw new SiteServiceException('invalid_request', 'At least one snapshot field is required.');
        }
        $allowed = ['generation_brief_id', 'snapshot_schema_version', 'facts_snapshot_json', 'source_references_json', 'snapshot_hash'];
        if (array_diff(array_keys($changes), $allowed) !== []) {
            throw new SiteServiceException('invalid_request', 'Only mutable snapshot fields may be updated.');
        }
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        $siteId = self::revisionSiteId($revisionId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $revisionId, $changes, $actor, $correlationId, $siteId
        ): array {
            $revision = self::lockMutableRevisionForComposition($connection, $siteId, $revisionId);

            $values = [
                'generation_brief_id' => $revision['generation_brief_id'] === null ? null : (int) $revision['generation_brief_id'],
                'snapshot_schema_version' => (int) $revision['snapshot_schema_version'],
                'facts_snapshot_json' => (string) $revision['facts_snapshot_json'],
                'source_references_json' => (string) $revision['source_references_json'],
                'snapshot_hash' => (string) $revision['snapshot_hash'],
            ];
            if (array_key_exists('generation_brief_id', $changes)) {
                $values['generation_brief_id'] = self::nullablePositiveId($changes['generation_brief_id'], 'Generation brief ID');
                self::assertSameSiteBrief($connection, $siteId, $values['generation_brief_id']);
            }
            if (array_key_exists('snapshot_schema_version', $changes)) {
                $values['snapshot_schema_version'] = (int) $changes['snapshot_schema_version'];
                if ($values['snapshot_schema_version'] < 1) {
                    throw new SiteServiceException('invalid_request', 'Snapshot schema version must be positive.');
                }
            }
            if (array_key_exists('facts_snapshot_json', $changes)) {
                $values['facts_snapshot_json'] = SiteServiceSupport::snapshotJson($changes['facts_snapshot_json'], 'Facts snapshot');
            }
            if (array_key_exists('source_references_json', $changes)) {
                $values['source_references_json'] = SiteServiceSupport::snapshotJson($changes['source_references_json'], 'Source references');
            }
            if (array_key_exists('snapshot_hash', $changes)) {
                $values['snapshot_hash'] = SiteServiceSupport::assertSnapshotHash((string) $changes['snapshot_hash']);
            }
            $update = $connection->prepare(
                'UPDATE site_revisions
                 SET generation_brief_id = :generation_brief_id,
                     snapshot_schema_version = :snapshot_schema_version,
                     facts_snapshot_json = :facts_snapshot_json,
                     source_references_json = :source_references_json,
                     snapshot_hash = :snapshot_hash,
                     updated_at = NOW()
                 WHERE id = :revision_id'
            );
            $update->execute($values + ['revision_id' => $revisionId]);
            SiteServiceSupport::event(
                $connection, $siteId, $revisionId, $actor, 'site_revision_snapshot_updated',
                $correlationId, null, ['updated_fields' => array_values(array_keys($changes))]
            );
            return ['revision_id' => $revisionId, 'site_id' => $siteId, 'correlation_id' => $correlationId];
        });
    }

    public static function assertRevisionMutableForComposition(int $actingUserId, int $revisionId): array
    {
        $revision = self::revisionForActor($actingUserId, $revisionId);
        self::assertRevisionMutableForCompositionRow($revision);
        return $revision;
    }

    public static function markValidationFailed(
        int $actingUserId,
        int $revisionId,
        string $reason,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $reason = SiteServiceSupport::reason($reason);
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        $siteId = self::revisionSiteId($revisionId);
        return SiteServiceSupport::transaction(static function (object $connection) use (
            $revisionId, $actor, $reason, $correlationId, $siteId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = self::lockRevision($connection, $revisionId);
            self::assertLockedRevisionSite($revision, $siteId);
            $updated = self::applyLifecycleTransition($connection, $revision, 'validation_failed');
            SiteServiceSupport::event(
                $connection, (int) $revision['site_id'], $revisionId, $actor,
                'site_revision_validation_failed', $correlationId, $reason
            );
            return $updated + ['correlation_id' => $correlationId];
        });
    }

    public static function returnValidationFailedToDraft(
        int $actingUserId,
        int $revisionId,
        string $reason,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $reason = SiteServiceSupport::reason($reason);
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        $siteId = self::revisionSiteId($revisionId);
        return SiteServiceSupport::transaction(static function (object $connection) use (
            $revisionId, $actor, $reason, $correlationId, $siteId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = self::lockRevision($connection, $revisionId);
            self::assertLockedRevisionSite($revision, $siteId);
            $updated = self::applyLifecycleTransition($connection, $revision, 'draft');
            SiteServiceSupport::event(
                $connection, (int) $revision['site_id'], $revisionId, $actor,
                'site_revision_snapshot_updated', $correlationId, $reason, ['transition' => 'validation_failed_to_draft']
            );
            return $updated + ['correlation_id' => $correlationId];
        });
    }

    public static function classifyMateriality(
        int $actingUserId,
        int $revisionId,
        string $materiality,
        string $reason,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        if (!in_array($materiality, ['material', 'non_material'], true)) {
            throw new SiteServiceException('invalid_request', 'Materiality must be material or non-material.');
        }
        $reason = SiteServiceSupport::reason($reason);
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        $siteId = self::revisionSiteId($revisionId);
        return SiteServiceSupport::transaction(static function (object $connection) use (
            $revisionId, $materiality, $reason, $actor, $correlationId, $siteId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = self::lockRevision($connection, $revisionId);
            self::assertLockedRevisionSite($revision, $siteId);
            if ((string) $revision['materiality'] !== 'undetermined') {
                throw new SiteServiceException('conflict', 'Revision materiality is write-once.');
            }
            if (!in_array((string) $revision['lifecycle_status'], self::MUTABLE_COMPOSITION_STATES, true)
                && (string) $revision['lifecycle_status'] !== 'restored') {
                throw new SiteServiceException('immutable_revision', 'Reviewed revision materiality cannot be changed.');
            }
            $update = $connection->prepare(
                'UPDATE site_revisions SET materiality = :materiality, updated_at = NOW()
                 WHERE id = :revision_id AND materiality = :undetermined'
            );
            $update->execute([
                'materiality' => $materiality,
                'revision_id' => $revisionId,
                'undetermined' => 'undetermined',
            ]);
            if ($update->rowCount() !== 1) {
                throw new SiteServiceException('conflict', 'Revision materiality was already classified.');
            }

            $superseded = $materiality === 'material'
                ? self::supersedeOlderCustomerApprovals(
                    $connection,
                    (int) $revision['site_id'],
                    (int) $revision['revision_number'],
                    $revisionId,
                    $actor,
                    $correlationId
                )
                : [];
            SiteServiceSupport::event(
                $connection, (int) $revision['site_id'], $revisionId, $actor,
                'site_revision_materiality_classified', $correlationId, $reason,
                ['materiality' => $materiality, 'superseded_approval_ids' => $superseded]
            );
            return [
                'revision_id' => $revisionId,
                'materiality' => $materiality,
                'superseded_approval_ids' => $superseded,
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function markReadyForReview(
        int $actingUserId,
        int $revisionId,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        $siteId = self::revisionSiteId($revisionId);
        return SiteServiceSupport::transaction(static function (object $connection) use (
            $revisionId, $actor, $correlationId, $siteId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $revision = self::lockRevision($connection, $revisionId);
            self::assertLockedRevisionSite($revision, $siteId);
            if ((string) $revision['materiality'] === 'undetermined') {
                throw new SiteServiceException('conflict', 'Revision materiality must be classified before review.');
            }
            SiteServiceSupport::assertSnapshotHash((string) $revision['snapshot_hash']);
            self::assertReviewStructure($connection, $revision);
            $updated = self::applyLifecycleTransition($connection, $revision, 'ready_for_review', true);
            SiteServiceSupport::event(
                $connection, (int) $revision['site_id'], $revisionId, $actor,
                'site_revision_ready_for_review', $correlationId, null,
                ['materiality' => (string) $revision['materiality']]
            );
            return $updated + ['correlation_id' => $correlationId];
        });
    }

    public static function createRestoreCandidate(
        int $actingUserId,
        int $siteId,
        int $sourceRevisionId,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        SiteServiceSupport::positiveId($sourceRevisionId, 'Source revision ID');
        $correlationId = SiteServiceSupport::correlationId($correlationId);
        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actingUserId, $actor, $siteId, $sourceRevisionId, $correlationId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);
            $source = self::lockRevision($connection, $sourceRevisionId);
            if ((int) $source['site_id'] !== $siteId) {
                throw new SiteServiceException('not_found', 'The source revision is not part of this site.');
            }
            if (!in_array((string) $source['lifecycle_status'], self::RESTORABLE_STATES, true)) {
                throw new SiteServiceException('invalid_transition', 'That revision is not eligible as a restore source.');
            }
            $latest = $connection->prepare(
                'SELECT id FROM site_revisions WHERE site_id = :site_id ORDER BY revision_number DESC LIMIT 1'
            );
            $latest->execute(['site_id' => $siteId]);
            $basedOn = $latest->fetchColumn();
            $basedOn = $basedOn === false ? null : (int) $basedOn;
            $revisionNumber = self::nextRevisionNumber($connection, $siteId);
            $insert = $connection->prepare(
                'INSERT INTO site_revisions (
                    site_id, revision_number, lifecycle_status, based_on_revision_id,
                    restored_from_revision_id, generation_brief_id, materiality,
                    snapshot_schema_version, facts_snapshot_json, source_references_json,
                    snapshot_hash, created_by_user_id, correlation_id, created_at, updated_at
                 ) VALUES (
                    :site_id, :revision_number, :status, :based_on_revision_id,
                    :restored_from_revision_id, :generation_brief_id, :materiality,
                    :snapshot_schema_version, :facts_snapshot_json, :source_references_json,
                    :snapshot_hash, :actor_id, :correlation_id, NOW(), NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId,
                'revision_number' => $revisionNumber,
                'status' => 'restored',
                'based_on_revision_id' => $basedOn,
                'restored_from_revision_id' => $sourceRevisionId,
                'generation_brief_id' => $source['generation_brief_id'],
                'materiality' => 'material',
                'snapshot_schema_version' => (int) $source['snapshot_schema_version'],
                'facts_snapshot_json' => $source['facts_snapshot_json'],
                'source_references_json' => $source['source_references_json'],
                'snapshot_hash' => $source['snapshot_hash'],
                'actor_id' => $actingUserId,
                'correlation_id' => $correlationId,
            ]);
            $revisionId = (int) $connection->lastInsertId();
            self::copyComposition($connection, $siteId, $sourceRevisionId, $revisionId);
            $superseded = self::supersedeOlderCustomerApprovals(
                $connection,
                $siteId,
                $revisionNumber,
                $revisionId,
                $actor,
                $correlationId
            );
            SiteServiceSupport::event(
                $connection, $siteId, $revisionId, $actor, 'site_restore_candidate_created',
                $correlationId, null,
                [
                    'source_revision_id' => $sourceRevisionId,
                    'revision_number' => $revisionNumber,
                    'superseded_approval_ids' => $superseded,
                ]
            );
            return [
                'revision_id' => $revisionId,
                'site_id' => $siteId,
                'revision_number' => $revisionNumber,
                'lifecycle_status' => 'restored',
                'materiality' => 'material',
                'restored_from_revision_id' => $sourceRevisionId,
                'based_on_revision_id' => $basedOn,
                'correlation_id' => $correlationId,
            ];
        });
    }

    /** @internal Central revision transition owner used by approval service. */
    public static function applyLifecycleTransition(
        object $connection,
        array $revision,
        string $targetStatus,
        bool $setReviewReadyAt = false
    ): array {
        $current = (string) $revision['lifecycle_status'];
        if ($targetStatus === 'published' || ($current === 'published' && $targetStatus === 'superseded')) {
            throw new SiteServiceException('future_gate_required', 'Publication lifecycle changes require the M6 deployment gate.');
        }
        if (!array_key_exists($current, self::TRANSITIONS)
            || !in_array($targetStatus, self::TRANSITIONS[$current], true)) {
            throw new SiteServiceException('invalid_transition', 'The requested revision lifecycle transition is not allowed.');
        }
        $statement = $connection->prepare(
            'UPDATE site_revisions
             SET lifecycle_status = :target_status,
                 review_ready_at = CASE WHEN :set_review_ready = 1 THEN NOW() ELSE review_ready_at END,
                 updated_at = NOW()
             WHERE id = :revision_id AND lifecycle_status = :current_status'
        );
        $statement->execute([
            'target_status' => $targetStatus,
            'set_review_ready' => $setReviewReadyAt ? 1 : 0,
            'revision_id' => (int) $revision['id'],
            'current_status' => $current,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new SiteServiceException('conflict', 'The revision lifecycle changed concurrently.');
        }
        return [
            'revision_id' => (int) $revision['id'],
            'site_id' => (int) $revision['site_id'],
            'lifecycle_status' => $targetStatus,
        ];
    }

    /** @internal */
    public static function lockRevision(object $connection, int $revisionId): array
    {
        $statement = $connection->prepare(
            'SELECT id, site_id, revision_number, lifecycle_status, based_on_revision_id,
                    restored_from_revision_id, generation_brief_id, materiality,
                    snapshot_schema_version, facts_snapshot_json, source_references_json,
                    snapshot_hash, created_by_user_id, review_ready_at, published_at
             FROM site_revisions WHERE id = :revision_id FOR UPDATE'
        );
        $statement->execute(['revision_id' => $revisionId]);
        $revision = $statement->fetch();
        if (!$revision) {
            throw new SiteServiceException('not_found', 'The revision was not found.');
        }
        return $revision;
    }

    /**
     * @internal M3 composition writers must call this inside their own write transaction
     * before changing composition so mutability is checked under the same site/revision locks.
     */
    public static function lockMutableRevisionForComposition(
        object $connection,
        int $siteId,
        int $revisionId
    ): array {
        if (!method_exists($connection, 'inTransaction') || !$connection->inTransaction()) {
            throw new SiteServiceException('conflict', 'Composition mutability must be checked inside the writing transaction.');
        }
        $site = SiteManager::lockSite($connection, $siteId);
        SiteServiceSupport::assertSiteOperational($site);
        $revision = self::lockRevision($connection, $revisionId);
        self::assertLockedRevisionSite($revision, $siteId);
        self::assertRevisionMutableForCompositionRow($revision);
        return $revision;
    }

    /** @internal Narrow approval-invalidation fallback; it never makes composition mutable. */
    public static function applyApprovalInvalidationFallback(
        object $connection,
        array $revision,
        string $targetStatus,
        string $cause
    ): array {
        $current = (string) $revision['lifecycle_status'];
        $allowed = [
            'customer_approval_revoked' => [
                'customer_approved' => ['ready_for_review'],
                'internally_approved' => ['ready_for_review'],
            ],
            'internal_approval_revoked' => [
                'internally_approved' => ['customer_approved', 'ready_for_review'],
            ],
        ];
        if (!isset($allowed[$cause][$current])
            || !in_array($targetStatus, $allowed[$cause][$current], true)) {
            throw new SiteServiceException('invalid_transition', 'The approval invalidation fallback is not allowed.');
        }
        $statement = $connection->prepare(
            '/* site-m2:approval-invalidation-fallback */
             UPDATE site_revisions
             SET lifecycle_status = :target_status, updated_at = NOW()
             WHERE id = :revision_id AND lifecycle_status = :current_status'
        );
        $statement->execute([
            'target_status' => $targetStatus,
            'revision_id' => (int) $revision['id'],
            'current_status' => $current,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new SiteServiceException('conflict', 'The revision lifecycle changed concurrently.');
        }
        return [
            'revision_id' => (int) $revision['id'],
            'site_id' => (int) $revision['site_id'],
            'lifecycle_status' => $targetStatus,
            'invalidation_cause' => $cause,
        ];
    }

    public static function revisionForActor(int $actingUserId, int $revisionId): array
    {
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        return SiteServiceSupport::read(static function (object $connection) use ($actingUserId, $revisionId): array {
            $statement = $connection->prepare(
                'SELECT id, site_id, revision_number, lifecycle_status, based_on_revision_id,
                        restored_from_revision_id, generation_brief_id, materiality,
                        snapshot_schema_version, facts_snapshot_json, source_references_json,
                        snapshot_hash, created_by_user_id, review_ready_at, published_at, created_at, updated_at
                 FROM site_revisions WHERE id = :revision_id LIMIT 1'
            );
            $statement->execute(['revision_id' => $revisionId]);
            $revision = $statement->fetch();
            if (!$revision) {
                throw new SiteServiceException('not_found', 'The revision was not found.');
            }
            SiteAuthorizationPolicy::requireSiteRead($actingUserId, (int) $revision['site_id']);
            return $revision;
        });
    }

    public static function latestRevision(int $actingUserId, int $siteId): ?array
    {
        SiteAuthorizationPolicy::requireSiteRead($actingUserId, $siteId);
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): ?array {
            $statement = $connection->prepare(
                'SELECT id, site_id, revision_number, lifecycle_status, materiality, snapshot_hash,
                        review_ready_at, published_at, created_at, updated_at
                 FROM site_revisions WHERE site_id = :site_id ORDER BY revision_number DESC LIMIT 1'
            );
            $statement->execute(['site_id' => $siteId]);
            return $statement->fetch() ?: null;
        });
    }

    public static function revisionsForSite(int $actingUserId, int $siteId): array
    {
        SiteAuthorizationPolicy::requireSiteRead($actingUserId, $siteId);
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): array {
            $statement = $connection->prepare(
                'SELECT id, site_id, revision_number, lifecycle_status, based_on_revision_id,
                        restored_from_revision_id, materiality, snapshot_hash, review_ready_at,
                        published_at, created_at, updated_at
                 FROM site_revisions WHERE site_id = :site_id ORDER BY revision_number DESC'
            );
            $statement->execute(['site_id' => $siteId]);
            return $statement->fetchAll();
        });
    }

    private static function validatedSnapshotInput(array $input): array
    {
        $version = (int) ($input['snapshot_schema_version'] ?? 0);
        if ($version < 1) {
            throw new SiteServiceException('invalid_request', 'Snapshot schema version must be positive.');
        }
        if (!array_key_exists('facts_snapshot_json', $input) || !array_key_exists('source_references_json', $input)) {
            throw new SiteServiceException('invalid_request', 'Facts and source reference snapshots are required.');
        }
        return [
            'generation_brief_id' => self::nullablePositiveId($input['generation_brief_id'] ?? null, 'Generation brief ID'),
            'snapshot_schema_version' => $version,
            'facts_snapshot_json' => SiteServiceSupport::snapshotJson($input['facts_snapshot_json'], 'Facts snapshot'),
            'source_references_json' => SiteServiceSupport::snapshotJson($input['source_references_json'], 'Source references'),
            'snapshot_hash' => SiteServiceSupport::assertSnapshotHash((string) ($input['snapshot_hash'] ?? '')),
        ];
    }

    private static function revisionSiteId(int $revisionId): int
    {
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        return SiteServiceSupport::read(static function (object $connection) use ($revisionId): int {
            $statement = $connection->prepare(
                'SELECT site_id FROM site_revisions WHERE id = :revision_id LIMIT 1'
            );
            $statement->execute(['revision_id' => $revisionId]);
            $siteId = $statement->fetchColumn();
            if ($siteId === false) {
                throw new SiteServiceException('not_found', 'The revision was not found.');
            }
            return (int) $siteId;
        });
    }

    private static function assertLockedRevisionSite(array $revision, int $siteId): void
    {
        if ((int) $revision['site_id'] !== $siteId) {
            throw new SiteServiceException('conflict', 'Revision ownership changed unexpectedly.');
        }
    }

    private static function nullablePositiveId(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        return SiteServiceSupport::positiveId($value, $label);
    }

    private static function assertSameSiteRevision(object $connection, int $siteId, ?int $revisionId, string $message): void
    {
        if ($revisionId === null) {
            return;
        }
        $statement = $connection->prepare(
            'SELECT 1 FROM site_revisions WHERE id = :revision_id AND site_id = :site_id LIMIT 1'
        );
        $statement->execute(['revision_id' => $revisionId, 'site_id' => $siteId]);
        if (!$statement->fetchColumn()) {
            throw new SiteServiceException('invalid_request', $message);
        }
    }

    private static function assertSameSiteBrief(object $connection, int $siteId, ?int $briefId): void
    {
        if ($briefId === null) {
            return;
        }
        $statement = $connection->prepare(
            'SELECT 1 FROM site_generation_briefs WHERE id = :brief_id AND site_id = :site_id LIMIT 1'
        );
        $statement->execute(['brief_id' => $briefId, 'site_id' => $siteId]);
        if (!$statement->fetchColumn()) {
            throw new SiteServiceException('invalid_request', 'The generation brief is not part of this site.');
        }
    }

    private static function nextRevisionNumber(object $connection, int $siteId): int
    {
        $statement = $connection->prepare(
            'SELECT COALESCE(MAX(revision_number), 0) + 1 FROM site_revisions WHERE site_id = :site_id'
        );
        $statement->execute(['site_id' => $siteId]);
        return (int) $statement->fetchColumn();
    }

    private static function assertRevisionMutableForCompositionRow(array $revision): void
    {
        if (!in_array((string) $revision['lifecycle_status'], self::MUTABLE_COMPOSITION_STATES, true)) {
            throw new SiteServiceException('immutable_revision', 'The revision is immutable after review begins.');
        }
    }

    private static function assertReviewStructure(object $connection, array $revision): void
    {
        $revisionId = (int) $revision['id'];
        $siteId = (int) $revision['site_id'];
        $pages = $connection->prepare(
            'SELECT srp.id, COUNT(sps.id) AS section_count
             FROM site_revision_pages srp
             LEFT JOIN site_page_sections sps
               ON sps.revision_page_id = srp.id
              AND sps.revision_id = srp.revision_id
              AND sps.site_id = srp.site_id
             WHERE srp.revision_id = :revision_id AND srp.site_id = :site_id
             GROUP BY srp.id'
        );
        $pages->execute(['revision_id' => $revisionId, 'site_id' => $siteId]);
        $pageRows = $pages->fetchAll();
        if ($pageRows === []) {
            throw new SiteServiceException('conflict', 'At least one revision page is required.');
        }
        foreach ($pageRows as $page) {
            if ((int) $page['section_count'] < 1) {
                throw new SiteServiceException('conflict', 'Every revision page requires at least one section.');
            }
        }
        $theme = $connection->prepare(
            'SELECT COUNT(*) FROM site_themes WHERE revision_id = :revision_id AND site_id = :site_id'
        );
        $theme->execute(['revision_id' => $revisionId, 'site_id' => $siteId]);
        if ((int) $theme->fetchColumn() !== 1) {
            throw new SiteServiceException('conflict', 'Exactly one same-site revision theme is required.');
        }
        $assets = $connection->prepare(
            'SELECT COUNT(*)
             FROM site_revision_assets sra
             LEFT JOIN site_revision_pages srp
               ON srp.id = sra.site_revision_page_id
              AND srp.revision_id = sra.revision_id
              AND srp.site_id = sra.site_id
             LEFT JOIN site_page_sections sps
               ON sps.id = sra.site_page_section_id
              AND sps.revision_id = sra.revision_id
              AND sps.site_id = sra.site_id
             WHERE sra.revision_id = :revision_id AND sra.site_id = :site_id
               AND (
                    (sra.site_revision_page_id IS NOT NULL AND srp.id IS NULL)
                 OR (sra.site_page_section_id IS NOT NULL AND sps.id IS NULL)
                 OR (sra.site_revision_page_id IS NOT NULL AND sra.site_page_section_id IS NOT NULL
                     AND sps.revision_page_id <> sra.site_revision_page_id)
               )'
        );
        $assets->execute(['revision_id' => $revisionId, 'site_id' => $siteId]);
        if ((int) $assets->fetchColumn() !== 0) {
            throw new SiteServiceException('conflict', 'Revision asset page and section ownership is inconsistent.');
        }
    }

    private static function supersedeOlderCustomerApprovals(
        object $connection,
        int $siteId,
        int $successorRevisionNumber,
        int $successorRevisionId,
        array $actor,
        string $correlationId
    ): array {
        $olderRevisionStatement = $connection->prepare(
            '/* site-m2:material-successor-older-revisions */
             SELECT id
             FROM site_revisions
             WHERE site_id = :site_id AND revision_number < :revision_number
             ORDER BY revision_number ASC'
        );
        $olderRevisionStatement->execute([
            'site_id' => $siteId,
            'revision_number' => $successorRevisionNumber,
        ]);
        $olderRevisionIds = array_map('intval', array_column($olderRevisionStatement->fetchAll(), 'id'));
        if ($olderRevisionIds === []) {
            return [];
        }
        $parameters = [
            'site_id' => $siteId,
            'customer_type' => 'customer',
            'approved_state' => 'approved',
            'requested_state' => 'requested',
            'internal_type' => 'internal',
            'requested_internal_state' => 'requested',
        ];
        $revisionPlaceholders = [];
        foreach ($olderRevisionIds as $index => $olderRevisionId) {
            $name = 'older_revision_id_' . $index;
            $revisionPlaceholders[] = ':' . $name;
            $parameters[$name] = $olderRevisionId;
        }
        $prior = $connection->prepare(
            '/* site-m2:material-successor-invalidation */
             SELECT sa.id, sa.revision_id, sa.approval_type, sa.state
             FROM site_approvals sa
             WHERE sa.site_id = :site_id
               AND sa.revision_id IN (' . implode(', ', $revisionPlaceholders) . ')
               AND (
                    (sa.approval_type = :customer_type
                     AND sa.state IN (:approved_state, :requested_state))
                 OR (sa.approval_type = :internal_type
                     AND sa.state = :requested_internal_state)
               )
             FOR UPDATE'
        );
        $prior->execute($parameters);
        $rows = $prior->fetchAll();
        if ($rows === []) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $supersede = $connection->prepare(
                'UPDATE site_approvals
                 SET state = :superseded_state,
                     reason = :reason,
                     decided_at = CASE WHEN state = :requested_state THEN NOW() ELSE decided_at END
                 WHERE id = :approval_id AND state = :previous_state'
            );
            $supersede->execute([
                'superseded_state' => 'superseded',
                'reason' => 'material_successor_revision',
                'requested_state' => 'requested',
                'approval_id' => (int) $row['id'],
                'previous_state' => (string) $row['state'],
            ]);
            if ($supersede->rowCount() !== 1) {
                throw new SiteServiceException('conflict', 'An older approval workflow changed concurrently.');
            }
            $ids[] = (int) $row['id'];
            SiteServiceSupport::event(
                $connection, $siteId, (int) $row['revision_id'], $actor,
                'site_approval_superseded', $correlationId, 'material_successor_revision',
                [
                    'approval_id' => (int) $row['id'],
                    'approval_type' => (string) $row['approval_type'],
                    'successor_revision_id' => $successorRevisionId,
                ]
            );
        }
        return $ids;
    }

    private static function copyComposition(object $connection, int $siteId, int $sourceRevisionId, int $revisionId): void
    {
        $pageMap = [];
        $sectionMap = [];
        $pages = $connection->prepare(
            'SELECT id, site_page_id, title, slug, page_type, navigation_label, sort_order,
                    seo_json, presentation_json, content_hash
             FROM site_revision_pages WHERE revision_id = :revision_id AND site_id = :site_id
             ORDER BY sort_order ASC, id ASC'
        );
        $pages->execute(['revision_id' => $sourceRevisionId, 'site_id' => $siteId]);
        foreach ($pages->fetchAll() as $page) {
            $insert = $connection->prepare(
                'INSERT INTO site_revision_pages (
                    site_id, revision_id, site_page_id, title, slug, page_type,
                    navigation_label, sort_order, seo_json, presentation_json, content_hash, created_at
                 ) VALUES (
                    :site_id, :revision_id, :site_page_id, :title, :slug, :page_type,
                    :navigation_label, :sort_order, :seo_json, :presentation_json, :content_hash, NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId, 'revision_id' => $revisionId,
                'site_page_id' => $page['site_page_id'], 'title' => $page['title'],
                'slug' => $page['slug'], 'page_type' => $page['page_type'],
                'navigation_label' => $page['navigation_label'], 'sort_order' => $page['sort_order'],
                'seo_json' => $page['seo_json'], 'presentation_json' => $page['presentation_json'],
                'content_hash' => $page['content_hash'],
            ]);
            $pageMap[(int) $page['id']] = (int) $connection->lastInsertId();
        }

        $sections = $connection->prepare(
            'SELECT id, revision_page_id, section_key, component_variant_id, sort_order,
                    configuration_schema_version, configuration_json, content_hash
             FROM site_page_sections WHERE revision_id = :revision_id AND site_id = :site_id
             ORDER BY revision_page_id ASC, sort_order ASC, id ASC'
        );
        $sections->execute(['revision_id' => $sourceRevisionId, 'site_id' => $siteId]);
        foreach ($sections->fetchAll() as $section) {
            $oldPageId = (int) $section['revision_page_id'];
            if (!isset($pageMap[$oldPageId])) {
                throw new SiteServiceException('conflict', 'Restore source composition is inconsistent.');
            }
            $insert = $connection->prepare(
                'INSERT INTO site_page_sections (
                    site_id, revision_id, revision_page_id, section_key, component_variant_id,
                    sort_order, configuration_schema_version, configuration_json, content_hash, created_at
                 ) VALUES (
                    :site_id, :revision_id, :revision_page_id, :section_key, :component_variant_id,
                    :sort_order, :configuration_schema_version, :configuration_json, :content_hash, NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId, 'revision_id' => $revisionId,
                'revision_page_id' => $pageMap[$oldPageId], 'section_key' => $section['section_key'],
                'component_variant_id' => $section['component_variant_id'], 'sort_order' => $section['sort_order'],
                'configuration_schema_version' => $section['configuration_schema_version'],
                'configuration_json' => $section['configuration_json'], 'content_hash' => $section['content_hash'],
            ]);
            $sectionMap[(int) $section['id']] = (int) $connection->lastInsertId();
        }

        $theme = $connection->prepare(
            'INSERT INTO site_themes (
                site_id, revision_id, theme_key, theme_version, primary_color, secondary_color,
                typography_json, configuration_json, content_hash, created_at
             )
             SELECT site_id, :new_revision_id, theme_key, theme_version, primary_color, secondary_color,
                    typography_json, configuration_json, content_hash, NOW()
             FROM site_themes WHERE revision_id = :source_revision_id AND site_id = :site_id'
        );
        $theme->execute([
            'new_revision_id' => $revisionId,
            'source_revision_id' => $sourceRevisionId,
            'site_id' => $siteId,
        ]);

        $assets = $connection->prepare(
            'SELECT asset_id, usage_key, site_revision_page_id, site_page_section_id, source_reference
             FROM site_revision_assets WHERE revision_id = :revision_id AND site_id = :site_id
             ORDER BY id ASC'
        );
        $assets->execute(['revision_id' => $sourceRevisionId, 'site_id' => $siteId]);
        foreach ($assets->fetchAll() as $asset) {
            $oldPageId = $asset['site_revision_page_id'] === null ? null : (int) $asset['site_revision_page_id'];
            $oldSectionId = $asset['site_page_section_id'] === null ? null : (int) $asset['site_page_section_id'];
            if (($oldPageId !== null && !isset($pageMap[$oldPageId]))
                || ($oldSectionId !== null && !isset($sectionMap[$oldSectionId]))) {
                throw new SiteServiceException('conflict', 'Restore source asset references are inconsistent.');
            }
            $insert = $connection->prepare(
                'INSERT INTO site_revision_assets (
                    site_id, revision_id, asset_id, usage_key, site_revision_page_id,
                    site_page_section_id, source_reference, created_at
                 ) VALUES (
                    :site_id, :revision_id, :asset_id, :usage_key, :revision_page_id,
                    :section_id, :source_reference, NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId, 'revision_id' => $revisionId,
                'asset_id' => $asset['asset_id'], 'usage_key' => $asset['usage_key'],
                'revision_page_id' => $oldPageId === null ? null : $pageMap[$oldPageId],
                'section_id' => $oldSectionId === null ? null : $sectionMap[$oldSectionId],
                'source_reference' => $asset['source_reference'],
            ]);
        }
    }
}
