<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteApprovalManager.php';
require_once __DIR__ . '/SiteCompositionManager.php';

final class SiteReviewAdminWorkflow
{
    public static function workspace(int $actingUserId, int $revisionId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        $revision = SiteRevisionManager::revisionForActor($actingUserId, $revisionId);
        $site = SiteManager::siteForActor($actingUserId, (int) $revision['site_id']);
        $composition = SiteCompositionManager::compositionForActor($actingUserId, $revisionId);
        $approvals = SiteApprovalManager::approvalsForRevision($actingUserId, $revisionId);
        $eligibility = SiteApprovalManager::eligibilityForRevision($actingUserId, $revisionId);

        $openCustomer = null;
        $openInternal = null;
        foreach ($approvals as $approval) {
            if ((string) $approval['state'] !== 'requested') {
                continue;
            }
            if ((string) $approval['approval_type'] === 'customer') {
                $openCustomer = $approval;
            } elseif ((string) $approval['approval_type'] === 'internal') {
                $openInternal = $approval;
            }
        }
        $mutable = in_array((string) $revision['lifecycle_status'], SiteRevisionManager::MUTABLE_COMPOSITION_STATES, true);
        $canClassify = (string) $revision['materiality'] === 'undetermined'
            && ($mutable || (string) $revision['lifecycle_status'] === 'restored');
        $canSubmit = (string) $revision['materiality'] !== 'undetermined'
            && ($mutable || (string) $revision['lifecycle_status'] === 'restored');

        return [
            'site' => $site,
            'revision' => $revision,
            'composition' => $composition,
            'approvals' => $approvals,
            'open_customer_request' => $openCustomer,
            'open_internal_request' => $openInternal,
            'capabilities' => $eligibility + [
                'can_classify_materiality' => $canClassify,
                'can_submit_for_review' => $canSubmit,
                'can_decide_internal_approval' => $openInternal !== null && !$eligibility['has_newer_material_revision'],
                'can_edit_composition' => $mutable,
            ],
            'links' => [
                'site' => 'site.php?site_id=' . (int) $site['id'],
                'composer' => 'site-composer.php?revision_id=' . $revisionId,
                'preview' => 'site-preview.php?revision_id=' . $revisionId,
            ],
            'next_step' => self::nextStep($revision, $openCustomer, $openInternal, $eligibility, $canClassify, $canSubmit),
            'publicly_deployed' => false,
        ];
    }

    public static function apply(int $actingUserId, int $revisionId, string $action, array $input): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($revisionId, 'Revision ID');
        return match ($action) {
            'classify_materiality' => SiteRevisionManager::classifyMateriality(
                $actingUserId, $revisionId, (string) ($input['materiality'] ?? ''), (string) ($input['reason'] ?? '')
            ),
            'submit_for_review' => SiteRevisionManager::markReadyForReview($actingUserId, $revisionId),
            'request_customer_review' => SiteApprovalManager::requestApproval(
                $actingUserId, $revisionId, 'customer', self::comment($input)
            ),
            'request_internal_review' => SiteApprovalManager::requestApproval(
                $actingUserId, $revisionId, 'internal', self::comment($input)
            ),
            'decide_internal_approval' => self::decideInternal($actingUserId, $revisionId, $input),
            default => throw new InvalidArgumentException('The requested review workflow action is invalid.'),
        };
    }

    private static function decideInternal(int $actingUserId, int $revisionId, array $input): array
    {
        $approvalId = SiteServiceSupport::positiveId((int) ($input['approval_id'] ?? 0), 'Approval ID');
        $decision = (string) ($input['decision'] ?? '');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('The internal decision must be approved or rejected.');
        }
        $match = null;
        foreach (SiteApprovalManager::approvalsForRevision($actingUserId, $revisionId) as $approval) {
            if ((int) $approval['id'] === $approvalId) {
                $match = $approval;
                break;
            }
        }
        if ($match === null || (string) $match['approval_type'] !== 'internal'
            || (string) $match['state'] !== 'requested') {
            throw new SiteServiceException('invalid_request', 'Only this revision’s requested internal approval can be decided here.');
        }
        return SiteApprovalManager::decideApproval($actingUserId, $approvalId, $decision, self::comment($input));
    }

    private static function comment(array $input): ?string
    {
        $comment = trim((string) ($input['comment'] ?? ''));
        return $comment === '' ? null : $comment;
    }

    private static function nextStep(array $revision, ?array $customer, ?array $internal, array $eligibility, bool $classify, bool $submit): string
    {
        if ($classify) return 'Classify this revision’s materiality.';
        if ($submit) return 'Submit the stored composition through the review gate.';
        if ($customer !== null) return 'Customer review is pending. Customer decisions arrive in M5.';
        if ($internal !== null) return 'Complete the requested internal review.';
        if ($eligibility['can_request_customer_review']) return 'Request customer review.';
        if ($eligibility['can_request_internal_review']) return 'Request internal review.';
        return match ((string) $revision['lifecycle_status']) {
            'internally_approved' => 'Internal approval is complete. Approval does not publish this site.',
            'changes_requested' => 'Create a successor authored revision from the site detail page.',
            default => 'No review action is currently available.',
        };
    }
}
