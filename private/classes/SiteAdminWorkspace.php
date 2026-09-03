<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteAuthorizationPolicy.php';
require_once __DIR__ . '/SiteRevisionManager.php';

final class SiteAdminWorkspace
{
    private const LIFECYCLES = [
        'draft', 'demo', 'pending_customer', 'pending_internal_review', 'approved',
        'active', 'suspended', 'cancellation_pending', 'conversion_pending', 'archived',
    ];

    public static function listSites(int $actingUserId, array $filters = []): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $purpose = trim((string) ($filters['purpose'] ?? ''));
        $lifecycle = trim((string) ($filters['lifecycle_status'] ?? ''));
        if ($purpose !== '' && !in_array($purpose, SiteManager::PURPOSES, true)) {
            throw new SiteServiceException('invalid_request', 'The site purpose filter is invalid.');
        }
        if ($lifecycle !== '' && !in_array($lifecycle, self::LIFECYCLES, true)) {
            throw new SiteServiceException('invalid_request', 'The site lifecycle filter is invalid.');
        }

        return SiteServiceSupport::read(static function (object $connection) use ($purpose, $lifecycle): array {
            $where = [];
            $parameters = [
                'association_role' => 'customer',
                'association_status' => 'active',
                'mutable_draft' => 'draft',
                'mutable_validation_failed' => 'validation_failed',
            ];
            if ($purpose !== '') {
                $where[] = 's.purpose = :purpose';
                $parameters['purpose'] = $purpose;
            }
            if ($lifecycle !== '') {
                $where[] = 's.lifecycle_status = :lifecycle_status';
                $parameters['lifecycle_status'] = $lifecycle;
            }
            $statement = $connection->prepare(
                'SELECT s.id, s.site_key, s.purpose, s.lifecycle_status, s.lock_version,
                        s.current_published_revision_id, s.created_at, s.updated_at,
                        sba.business_id, b.business_name,
                        (SELECT COUNT(*) FROM site_revisions sr WHERE sr.site_id = s.id) AS revision_count,
                        (SELECT COUNT(*) FROM site_generation_briefs sgb WHERE sgb.site_id = s.id) AS brief_count,
                        (SELECT sr2.id FROM site_revisions sr2
                         WHERE sr2.site_id = s.id
                           AND sr2.lifecycle_status IN (:mutable_draft, :mutable_validation_failed)
                         ORDER BY sr2.revision_number DESC LIMIT 1) AS mutable_revision_id
                 FROM sites s
                 LEFT JOIN site_business_associations sba
                   ON sba.site_id = s.id
                  AND sba.association_role = :association_role
                  AND sba.status = :association_status
                 LEFT JOIN businesses b ON b.id = sba.business_id'
                . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) .
                ' ORDER BY s.updated_at DESC, s.id DESC'
            );
            $statement->execute($parameters);
            return array_map([self::class, 'siteSummary'], $statement->fetchAll());
        });
    }

    public static function eligible247spBusinesses(int $actingUserId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        return SiteServiceSupport::read(static function (object $connection): array {
            $statement = $connection->prepare(
                'SELECT b.id, b.business_name, b.email
                 FROM businesses b
                 INNER JOIN business_modules bm
                   ON bm.business_id = b.id AND bm.status = :module_status
                 INNER JOIN modules m
                   ON m.id = bm.module_id AND m.module_key = :module_key AND m.is_active = 1
                 WHERE b.status = :business_status
                   AND b.is_suspended = 0
                   AND NOT EXISTS (
                       SELECT 1
                       FROM site_business_associations existing_sba
                       INNER JOIN sites existing_site
                         ON existing_site.id = existing_sba.site_id
                        AND existing_site.purpose = :site_purpose
                       WHERE existing_sba.business_id = b.id
                         AND existing_sba.association_role = :association_role
                         AND existing_sba.status = :association_status
                   )
                 ORDER BY b.business_name ASC, b.id ASC'
            );
            $statement->execute([
                'module_status' => 'active',
                'module_key' => '247sp',
                'business_status' => 'active',
                'site_purpose' => '247sp',
                'association_role' => 'customer',
                'association_status' => 'active',
            ]);
            return array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'business_name' => (string) $row['business_name'],
                'email' => (string) ($row['email'] ?? ''),
            ], $statement->fetchAll());
        });
    }

    public static function siteDetail(int $actingUserId, int $siteId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');

        return SiteServiceSupport::read(static function (object $connection) use ($siteId): array {
            $siteStatement = $connection->prepare(
                'SELECT s.id, s.site_key, s.purpose, s.lifecycle_status,
                        s.current_published_revision_id, s.lock_version, s.suspended_at,
                        s.archived_at, s.created_at, s.updated_at,
                        sba.id AS association_id, sba.business_id, sba.association_role,
                        sba.status AS association_status, sba.effective_at, b.business_name
                 FROM sites s
                 LEFT JOIN site_business_associations sba
                   ON sba.site_id = s.id
                  AND sba.association_role = :association_role
                  AND sba.status = :association_status
                 LEFT JOIN businesses b ON b.id = sba.business_id
                 WHERE s.id = :site_id LIMIT 1'
            );
            $siteStatement->execute([
                'association_role' => 'customer',
                'association_status' => 'active',
                'site_id' => $siteId,
            ]);
            $site = $siteStatement->fetch();
            if (!$site) {
                throw new SiteServiceException('not_found', 'The site was not found.');
            }

            $briefStatement = $connection->prepare(
                'SELECT id, site_id, brief_version, state, brief_json, source_type,
                        superseded_at, content_hash, created_at
                 FROM site_generation_briefs
                 WHERE site_id = :site_id ORDER BY brief_version DESC, id DESC'
            );
            $briefStatement->execute(['site_id' => $siteId]);

            $revisionStatement = $connection->prepare(
                'SELECT id, site_id, revision_number, lifecycle_status, based_on_revision_id,
                        restored_from_revision_id, generation_brief_id, materiality,
                        snapshot_hash, review_ready_at, published_at, superseded_at,
                        created_at, updated_at,
                        EXISTS (SELECT 1 FROM site_revision_pages srp WHERE srp.revision_id = site_revisions.id) AS has_composition,
                        CASE WHEN lifecycle_status IN (:mutable_draft, :mutable_validation_failed)
                             THEN :composition_mutable ELSE :composition_immutable END AS composition_access
                 FROM site_revisions
                 WHERE site_id = :site_id ORDER BY revision_number DESC, id DESC'
            );
            $revisionStatement->execute([
                'mutable_draft' => 'draft',
                'mutable_validation_failed' => 'validation_failed',
                'composition_mutable' => 'mutable',
                'composition_immutable' => 'immutable',
                'site_id' => $siteId,
            ]);
            $revisions = array_map([self::class, 'revisionSummary'], $revisionStatement->fetchAll());

            $approvalStatement = $connection->prepare(
                'SELECT sa.id, sa.site_id, sa.revision_id, sa.approval_type, sa.state,
                        sa.requested_at, sa.decided_at, sa.revoked_at,
                        sr.revision_number
                 FROM site_approvals sa
                 INNER JOIN site_revisions sr
                   ON sr.id = sa.revision_id AND sr.site_id = sa.site_id
                 WHERE sa.site_id = :site_id
                 ORDER BY sa.requested_at DESC, sa.id DESC'
            );
            $approvalStatement->execute(['site_id' => $siteId]);

            $briefs = [];
            foreach ($briefStatement->fetchAll() as $row) {
                $brief = json_decode((string) $row['brief_json'], true, 512, JSON_THROW_ON_ERROR);
                $briefs[] = [
                    'id' => (int) $row['id'],
                    'site_id' => (int) $row['site_id'],
                    'brief_version' => (int) $row['brief_version'],
                    'state' => (string) $row['state'],
                    'source_type' => (string) $row['source_type'],
                    'content_hash' => (string) $row['content_hash'],
                    'summary' => is_array($brief) ? (string) ($brief['summary'] ?? 'Historical imported brief') : 'Historical brief',
                    'superseded_at' => $row['superseded_at'],
                    'created_at' => $row['created_at'],
                ];
            }

            $mutable = null;
            foreach ($revisions as $revision) {
                if (in_array($revision['lifecycle_status'], SiteRevisionManager::MUTABLE_COMPOSITION_STATES, true)) {
                    $mutable = $revision;
                    break;
                }
            }

            return [
                'site' => self::siteSummary($site),
                'business_association' => $site['business_id'] === null ? null : [
                    'id' => (int) $site['association_id'],
                    'business_id' => (int) $site['business_id'],
                    'business_name' => (string) $site['business_name'],
                    'association_role' => (string) $site['association_role'],
                    'status' => (string) $site['association_status'],
                    'effective_at' => $site['effective_at'],
                ],
                'briefs' => $briefs,
                'revisions' => $revisions,
                'approvals' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'revision_id' => (int) $row['revision_id'],
                    'revision_number' => (int) $row['revision_number'],
                    'approval_type' => (string) $row['approval_type'],
                    'state' => (string) $row['state'],
                    'requested_at' => $row['requested_at'],
                    'decided_at' => $row['decided_at'],
                    'revoked_at' => $row['revoked_at'],
                ], $approvalStatement->fetchAll()),
                'mutable_revision' => $mutable,
                'runtime' => [
                    'publicly_deployed' => false,
                    'authority' => 'Legacy 247SP website runtime remains authoritative.',
                ],
            ];
        });
    }

    public static function mutableRevision(int $actingUserId, int $siteId): ?array
    {
        $detail = self::siteDetail($actingUserId, $siteId);
        return $detail['mutable_revision'];
    }

    private static function siteSummary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'site_key' => (string) $row['site_key'],
            'purpose' => (string) $row['purpose'],
            'lifecycle_status' => (string) $row['lifecycle_status'],
            'current_published_revision_id' => $row['current_published_revision_id'] === null
                ? null : (int) $row['current_published_revision_id'],
            'lock_version' => (int) $row['lock_version'],
            'business_id' => isset($row['business_id']) && $row['business_id'] !== null ? (int) $row['business_id'] : null,
            'business_name' => isset($row['business_name']) && $row['business_name'] !== null ? (string) $row['business_name'] : null,
            'revision_count' => isset($row['revision_count']) ? (int) $row['revision_count'] : null,
            'brief_count' => isset($row['brief_count']) ? (int) $row['brief_count'] : null,
            'mutable_revision_id' => isset($row['mutable_revision_id']) && $row['mutable_revision_id'] !== null
                ? (int) $row['mutable_revision_id'] : null,
            'suspended_at' => $row['suspended_at'] ?? null,
            'archived_at' => $row['archived_at'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private static function revisionSummary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'site_id' => (int) $row['site_id'],
            'revision_number' => (int) $row['revision_number'],
            'lifecycle_status' => (string) $row['lifecycle_status'],
            'based_on_revision_id' => $row['based_on_revision_id'] === null ? null : (int) $row['based_on_revision_id'],
            'restored_from_revision_id' => $row['restored_from_revision_id'] === null ? null : (int) $row['restored_from_revision_id'],
            'generation_brief_id' => $row['generation_brief_id'] === null ? null : (int) $row['generation_brief_id'],
            'materiality' => (string) $row['materiality'],
            'snapshot_hash' => (string) $row['snapshot_hash'],
            'composition_access' => (string) $row['composition_access'],
            'has_composition' => (bool) ($row['has_composition'] ?? false),
            'review_ready_at' => $row['review_ready_at'],
            'published_at' => $row['published_at'],
            'superseded_at' => $row['superseded_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
