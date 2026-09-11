<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCompositionManager.php';

/** Customer read boundary. No request creation, decisions, or viewed events. */
final class SiteCustomerReviewWorkflow
{
    public static function positiveId(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value))
            || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new SiteServiceException('invalid_request', 'The website review is unavailable.');
        }
        return (int) $value;
    }

    public static function workspace(int $actorId, ?int $businessId = null): array
    {
        return self::load($actorId, $businessId, null, false)['review'];
    }

    /** Server-only validated render input; customer views receive only the review DTO. */
    public static function validatedPreview(int $actorId, int $businessId, int $requestId): array
    {
        return self::load($actorId, $businessId, self::positiveId($requestId), true);
    }

    private static function load(int $actorId, ?int $businessId, ?int $expectedRequest, bool $preview): array
    {
        self::requireCustomer($actorId);
        if ($businessId !== null) self::positiveId($businessId);

        // Discovery is advisory. Re-resolve after the site lock, before any projection.
        $business = self::business(Database::connection(), $actorId, $businessId);
        $businessId = (int) $business['id'];
        $candidates = self::sites(Database::connection(), $businessId);
        if (count($candidates) > 1) self::unavailable();
        $siteId = $candidates === [] ? null : (int) $candidates[0]['id'];

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actorId, $businessId, $siteId, $expectedRequest, $preview
        ): array {
            // Existing M2 writers serialize on the site. All subsequent plain reads
            // share the transaction snapshot; no domain rows are changed by this read.
            $site = $siteId === null ? null : SiteManager::lockSite($connection, $siteId);
            self::requireCustomer($actorId);
            $business = self::business($connection, $actorId, $businessId);
            $currentSites = self::sites($connection, $businessId);
            if (count($currentSites) > 1
                || ($siteId === null && $currentSites !== [])
                || ($siteId !== null && (count($currentSites) !== 1 || (int) $currentSites[0]['id'] !== $siteId))) {
                self::unavailable();
            }
            $review = self::emptyReview($business);
            if ($site === null) return self::result($review, null, $preview);
            $context = SiteAuthorizationPolicy::requireSiteRead($actorId, $siteId);
            if ((int) $context['business_id'] !== $businessId || $site['purpose'] !== '247sp') self::unavailable();

            // Detect inconsistent extra customer associations, not just LIMIT 1 policy reads.
            $association = $connection->prepare(
                '/* site-m5a:associations */ SELECT business_id FROM site_business_associations
                 WHERE site_id = :site_id AND association_role = :role AND status = :status'
            );
            $association->execute(['site_id' => $siteId, 'role' => 'customer', 'status' => 'active']);
            $rows = $association->fetchAll();
            if (count($rows) !== 1 || (int) $rows[0]['business_id'] !== $businessId) self::unavailable();
            if (!in_array($site['lifecycle_status'], ['draft', 'pending_customer', 'pending_internal_review', 'approved'], true)) {
                self::unavailable();
            }

            $requests = $connection->prepare(
                '/* site-m5a:issued-reviews */
                 SELECT sa.id, sa.site_id, sa.revision_id, sa.state, sa.requested_at, sa.decided_at,
                        sa.revoked_at, sr.site_id AS revision_site_id, sr.revision_number
                 FROM site_approvals sa
                 LEFT JOIN site_revisions sr ON sr.id = sa.revision_id
                 WHERE sa.site_id = :site_id AND sa.approval_type = :type
                 ORDER BY sr.revision_number DESC, sa.requested_at DESC, sa.id DESC'
            );
            $requests->execute(['site_id' => $siteId, 'type' => 'customer']);
            $issued = $requests->fetchAll();
            $open = 0;
            foreach ($issued as $row) {
                if ((int) ($row['revision_site_id'] ?? 0) !== $siteId) self::unavailable();
                if ($row['state'] === 'requested') $open++;
            }
            if ($open > 1) self::unavailable();
            if ($issued === []) return self::result($review, null, $preview);
            $request = $issued[0];
            if ($expectedRequest !== null && (int) $request['id'] !== $expectedRequest) self::unavailable();
            $revision = SiteRevisionManager::lockRevision($connection, (int) $request['revision_id']);
            if ((int) $revision['site_id'] !== $siteId) self::unavailable();

            $review['status'] = 'unavailable';
            $review['status_label'] = 'Review is no longer available. A new review will be provided.';
            if (in_array($request['state'], ['superseded', 'revoked'], true)) return self::result($review, null, $preview);
            try {
                SiteServiceSupport::assertNoNewerMaterialRevision($connection, $revision);
            } catch (SiteServiceException $exception) {
                if ($exception->classification() !== 'invalid_transition') throw $exception;
                return self::result($review, null, $preview);
            }
            if ($revision['materiality'] !== 'material') self::unavailable();
            $status = (string) $revision['lifecycle_status'];
            $effective = SiteServiceSupport::effectiveCustomerApproval($connection, $revision);
            $valid = match ($status) {
                'ready_for_review' => $request['state'] === 'requested' && $effective === null && $request['decided_at'] === null,
                'customer_approved', 'internally_approved' => $request['state'] === 'approved' && $effective === (int) $request['id'] && $request['decided_at'] !== null,
                // An internal rejection supersedes the earlier customer approval.
                // Such a request is handled above as unavailable, not resurrected.
                'changes_requested' => $request['state'] === 'rejected' && $effective === null && $request['decided_at'] !== null,
                default => false,
            };
            $expectedSiteStatus = match ($status) {
                'ready_for_review' => 'pending_customer',
                'customer_approved' => 'pending_internal_review',
                'internally_approved' => 'approved',
                'changes_requested' => 'draft',
                default => null,
            };
            if (!$valid || $request['revoked_at'] !== null || $site['lifecycle_status'] !== $expectedSiteStatus) self::unavailable();
            $composition = SiteCompositionManager::validatedCompositionForActor($actorId, (int) $revision['id']);
            if ((int) $composition['site_id'] !== $siteId || (int) $composition['revision_id'] !== (int) $revision['id']) self::unavailable();

            $review['status'] = $status;
            $review['status_label'] = match ($status) {
                'ready_for_review' => 'Revision ready for review',
                'customer_approved' => 'Customer approved — awaiting internal review',
                'internally_approved' => 'Internal review complete. Approval does not publish your website.',
                'changes_requested' => 'Changes requested',
            };
            $review['revision_number'] = (int) $revision['revision_number'];
            $review['requested_at'] = (string) $request['requested_at'];
            $review['decided_at'] = $request['decided_at'];
            $review['preview_available'] = true;
            $review['preview_href'] = 'website-review-preview.php?business_id=' . $businessId . '&request_id=' . (int) $request['id'];
            return self::result($review, $composition, $preview);
        });
    }

    private static function requireCustomer(int $actorId): void
    {
        if (SiteAuthorizationPolicy::actorContext($actorId)['is_internal_admin']) self::unavailable();
    }

    private static function business(object $connection, int $actorId, ?int $businessId): array
    {
        return SiteServiceSupport::read(static function () use ($connection, $actorId, $businessId): array {
            $statement = $connection->prepare(
                '/* site-m5a:business */
                 SELECT b.id, b.business_name, b.status, b.is_suspended, bu.is_owner, r.name AS business_role,
                        EXISTS (SELECT 1 FROM business_modules bm INNER JOIN modules m ON m.id = bm.module_id
                          WHERE bm.business_id = b.id AND bm.status = :module_status
                            AND m.module_key = :module_key AND m.is_active = 1) AS module_active
                 FROM businesses b
                 INNER JOIN business_users bu ON bu.business_id = b.id AND bu.user_id = :actor_id AND bu.status = :membership_status
                 LEFT JOIN roles r ON r.id = bu.role_id AND r.scope = :business_scope
                 WHERE ' . ($businessId === null ? 'b.status = :default_status' : 'b.id = :business_id') . '
                 ORDER BY b.created_at ASC, b.business_name ASC, b.id ASC LIMIT 1'
            );
            $parameters = ['actor_id' => $actorId, 'membership_status' => 'active', 'business_scope' => 'business',
                'module_status' => 'active', 'module_key' => '247sp'];
            $parameters[$businessId === null ? 'default_status' : 'business_id'] = $businessId ?? 'active';
            $statement->execute($parameters);
            $business = $statement->fetch();
            if (!$business || $business['status'] !== 'active' || (int) $business['is_suspended'] !== 0 || (int) $business['module_active'] !== 1) self::unavailable();
            return $business;
        });
    }

    private static function sites(object $connection, int $businessId): array
    {
        return SiteServiceSupport::read(static function () use ($connection, $businessId): array {
            $statement = $connection->prepare(
                '/* site-m5a:sites */ SELECT s.id FROM sites s
                 INNER JOIN site_business_associations sba ON sba.site_id = s.id
                 WHERE sba.business_id = :business_id AND sba.association_role = :role
                   AND sba.status = :status AND s.purpose = :purpose ORDER BY s.id'
            );
            $statement->execute(['business_id' => $businessId, 'role' => 'customer', 'status' => 'active', 'purpose' => '247sp']);
            return $statement->fetchAll();
        });
    }

    private static function emptyReview(array $business): array
    {
        return [
            'business_label' => (string) $business['business_name'],
            'status' => 'not_issued', 'status_label' => 'No website revision is currently waiting for your review.',
            'revision_number' => null, 'requested_at' => null, 'decided_at' => null,
            'preview_available' => false, 'preview_href' => null,
            'decision_read_only' => (int) $business['is_owner'] !== 1 && !in_array($business['business_role'], ['Owner', 'Admin'], true),
            'manager_href' => 'website-manager.php?business_id=' . (int) $business['id'],
            'profile_href' => 'business-profile.php?business_id=' . (int) $business['id'],
        ];
    }

    private static function result(array $review, ?array $composition, bool $preview): array
    {
        if ($preview && $composition === null) self::unavailable();
        return $preview ? ['review' => $review, 'composition' => $composition] : ['review' => $review];
    }

    private static function unavailable(): never
    {
        throw new SiteServiceException('not_found', 'The website review is unavailable.');
    }
}
