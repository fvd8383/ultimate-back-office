<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCompositionManager.php';

/** Read-only authoring choices. M3 resolves identities again inside every write. */
final class SiteAuthoringCatalog
{
    public static function forActor(int $actorId, ?string $pageType = null, array $sections = []): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actorId);
        return SiteServiceSupport::read(static function (object $connection) use ($pageType, $sections): array {
            $catalog = [];
            $counts = array_count_values(array_column($sections, 'component_key'));
            foreach (ComponentRegistry::manifest() as $identity => $definition) {
                if (!$definition['authorable'] || $definition['component_key'] === 'legacy_247sp_page') {
                    continue;
                }
                if ($pageType !== null && ($definition['scope'] !== 'section'
                    || !in_array($pageType, $definition['allowed_page_types'], true)
                    || ($counts[$definition['component_key']] ?? 0) >= $definition['cardinality'])) {
                    continue;
                }
                $variants = [];
                foreach ($definition['variants'] as $key => $variant) {
                    try {
                        ComponentRegistry::resolve($connection, $definition['component_key'],
                            $definition['implementation_version'], $key,
                            $definition['configuration_schema_version'], $definition['scope'], true);
                        $variants[$key] = $variant;
                    } catch (SiteServiceException $exception) {
                        if ($exception->classification() !== 'conflict') {
                            throw $exception;
                        }
                    }
                }
                if ($variants === []) {
                    continue;
                }
                $safe = array_intersect_key($definition, array_flip([
                    'component_key', 'implementation_version', 'label', 'category', 'scope',
                    'allowed_page_types', 'cardinality', 'configuration_schema_version',
                    'configuration_schema', 'asset_requirements',
                ]));
                $catalog[$identity] = $safe + ['variants' => $variants];
            }
            return $catalog;
        });
    }

    public static function themes(): array
    {
        return array_filter(ThemeRegistry::manifest(), static fn (array $theme): bool => $theme['authorable']);
    }

    public static function assetsForActor(int $actorId, int $revisionId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actorId);
        $revision = SiteRevisionManager::revisionForActor($actorId, $revisionId);
        return SiteServiceSupport::read(static function (object $connection) use ($revision): array {
            $statement = $connection->prepare(
                '/* site-m4b:asset-catalog */
                 SELECT a.id, a.asset_type, a.mime_type, a.byte_size, a.rights_classification,
                        a.rights_expires_at, a.lifecycle_status, a.business_id,
                        s.purpose, sba.business_id AS active_business_id
                 FROM site_assets a
                 INNER JOIN sites s ON s.id = a.site_id
                 LEFT JOIN site_business_associations sba ON sba.site_id = s.id
                   AND sba.association_role = :role AND sba.status = :status
                 WHERE a.site_id = :site_id ORDER BY a.id'
            );
            $statement->execute(['role' => 'customer', 'status' => 'active', 'site_id' => (int) $revision['site_id']]);
            $assets = [];
            foreach ($statement->fetchAll() as $row) {
                if ($row['lifecycle_status'] !== 'ready'
                    || !in_array($row['rights_classification'], SiteCompositionValidator::RIGHTS_ALLOWED, true)
                    || ($row['rights_expires_at'] !== null && strtotime($row['rights_expires_at']) <= time())
                    || ($row['purpose'] === '247sp'
                        && in_array($row['rights_classification'], ['customer_owned', 'customer_licensed_for_site'], true)
                        && ((int) $row['active_business_id'] < 1 || (int) $row['business_id'] !== (int) $row['active_business_id']))) {
                    continue;
                }
                $assets[] = ['asset_id' => (int) $row['id'], 'asset_type' => $row['asset_type'],
                    'mime_type' => $row['mime_type'], 'byte_size' => (int) $row['byte_size']];
            }
            return $assets;
        });
    }
}
