<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCompositionManager.php';
require_once __DIR__ . '/SiteCompositionRenderer.php';

/** Called within the owning approval transaction, after site/revision/request locks. */
final class SiteCustomerReviewGuard
{
    public static function context(array $context): array
    {
        $keys = ['actor_user_id', 'business_id', 'site_id', 'revision_id', 'request_id', 'snapshot_hash', 'lock_version'];
        if (count($context) !== count($keys) || array_diff(array_keys($context), $keys) !== []) self::stale();
        foreach (array_slice($keys, 0, 5) as $key) if (!is_int($context[$key]) || $context[$key] < 1) self::stale();
        if (!is_int($context['lock_version']) || $context['lock_version'] < 0
            || !is_string($context['snapshot_hash']) || preg_match('/^[a-f0-9]{64}$/D', $context['snapshot_hash']) !== 1) self::stale();
        return $context;
    }

    public static function check(object $connection, int $actorId, array $site, array $revision, array $approval, array $expected, bool $receipt = false): array
    {
        self::context($expected);
        $actor = SiteAuthorizationPolicy::requireCustomerApproval($actorId, (int) $site['id'], $connection);
        if ($expected['actor_user_id'] !== $actorId || $expected['business_id'] !== (int) $actor['business_id']
            || $expected['site_id'] !== (int) $site['id'] || $expected['revision_id'] !== (int) $revision['id']
            || $expected['request_id'] !== (int) $approval['id'] || (int) $revision['site_id'] !== (int) $site['id']
            || (int) $approval['site_id'] !== (int) $site['id'] || (int) $approval['revision_id'] !== (int) $revision['id']
            || $approval['approval_type'] !== 'customer' || $site['purpose'] !== '247sp'
            || !in_array($site['lifecycle_status'], ['draft', 'pending_customer', 'pending_internal_review', 'approved'], true)
            || !hash_equals($expected['snapshot_hash'], (string) $revision['snapshot_hash'])) self::stale();
        $associations = $connection->prepare('SELECT business_id FROM site_business_associations WHERE site_id = :site_id AND association_role = :role AND status = :status FOR UPDATE');
        $associations->execute(['site_id' => (int) $site['id'], 'role' => 'customer', 'status' => 'active']);
        $rows = $associations->fetchAll();
        if (count($rows) !== 1 || (int) $rows[0]['business_id'] !== $expected['business_id']) self::stale();
        $business = $connection->prepare('SELECT id FROM businesses WHERE id = :business_id FOR UPDATE');
        $business->execute(['business_id' => $expected['business_id']]); $business->fetchAll();
        $sites = $connection->prepare('/* site-m5b:business-sites */ SELECT s.id, s.purpose, sba.association_role, sba.status FROM site_business_associations sba INNER JOIN sites s ON s.id = sba.site_id WHERE sba.business_id = :business_id ORDER BY s.id FOR SHARE');
        $sites->execute(['business_id' => $expected['business_id']]);
        $currentSites = array_values(array_filter($sites->fetchAll(), static fn (array $row): bool => $row['purpose'] === '247sp' && $row['association_role'] === 'customer' && $row['status'] === 'active'));
        if (count($currentSites) !== 1 || (int) $currentSites[0]['id'] !== (int) $site['id']) self::stale();
        // A matching durable receipt is allowed after close, but never bypasses
        // current actor/tenant eligibility. It causes no new write or event.
        if ($receipt) return ['actor' => $actor];
        if ($expected['lock_version'] !== (int) $site['lock_version'] || $approval['state'] !== 'requested'
            || $approval['revoked_at'] !== null || $approval['decided_at'] !== null
            || $revision['materiality'] !== 'material' || $revision['lifecycle_status'] !== 'ready_for_review'
            || $site['lifecycle_status'] !== 'pending_customer') self::stale();

        // Lock current eligibility inputs before the first consistent read. M2/M4
        // serialize composition and successor writers on the site lock.
        $assets = $connection->prepare('SELECT a.id FROM site_assets a INNER JOIN site_revision_assets sra ON sra.asset_id = a.id WHERE sra.revision_id = :revision_id ORDER BY a.id FOR UPDATE');
        $assets->execute(['revision_id' => (int) $revision['id']]); $assets->fetchAll();
        $registry = $connection->prepare('SELECT cd.id, cv.id AS variant_id FROM component_definitions cd INNER JOIN component_variants cv ON cv.component_definition_id = cd.id ORDER BY cd.id, cv.id FOR SHARE');
        $registry->execute(); $registry->fetchAll();
        $requests = $connection->prepare('/* site-m5b:issued-reviews */ SELECT sa.id, sa.state FROM site_approvals sa INNER JOIN site_revisions sr ON sr.id = sa.revision_id AND sr.site_id = sa.site_id WHERE sa.site_id = :site_id AND sa.approval_type = :type ORDER BY sr.revision_number DESC, sa.requested_at DESC, sa.id DESC FOR UPDATE');
        $requests->execute(['site_id' => (int) $site['id'], 'type' => 'customer']);
        $issued = $requests->fetchAll();
        if ($issued === [] || (int) $issued[0]['id'] !== (int) $approval['id']
            || count(array_filter($issued, static fn (array $row): bool => $row['state'] === 'requested')) !== 1) self::stale();
        SiteServiceSupport::assertNoNewerMaterialRevision($connection, $revision);
        if (SiteServiceSupport::effectiveCustomerApproval($connection, $revision) !== null) self::stale();
        $composition = SiteCompositionManager::validatedCompositionForActor($actorId, (int) $revision['id']);
        if (!hash_equals($expected['snapshot_hash'], $composition['snapshot_hash'])) self::stale();
        SiteCompositionRenderer::render($composition, ['preview_mode' => true]);
        return ['actor' => $actor, 'composition' => $composition];
    }

    public static function images(array $composition): array
    {
        $images = [];
        foreach ($composition['assets'] as $asset) {
            if ($asset['asset_type'] !== 'image') continue;
            $key = hash('sha256', $composition['revision_id'] . ':' . $asset['usage_key']);
            $pageLabel = 'Site layout';
            foreach ($composition['pages'] as $page) {
                if ($page['page_key'] !== $asset['page_key']) continue;
                $pageLabel = $page['title'];
                foreach ($page['sections'] as $section) {
                    if ($section['section_key'] !== $asset['section_key']) continue;
                    $description = $section['configuration']['media_alt'] ?? $section['configuration']['headline'] ?? $section['configuration']['heading'] ?? '';
                    if (is_string($description) && $description !== '') $pageLabel .= ' — ' . $description;
                }
                break;
            }
            $images[$key] = $pageLabel . ' — image ' . (count($images) + 1);
        }
        return $images;
    }

    private static function stale(): never
    {
        throw new SiteServiceException('conflict', 'This review changed. Reload and review before submitting again.');
    }
}
