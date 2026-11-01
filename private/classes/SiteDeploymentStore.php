<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteBuildStore.php';

/** Deployment SQL only. Shared transaction retries preserve rollback-before-reread. */
final class SiteDeploymentStore
{
    public static function insert(object $db, string $table, array $values): int
    {
        self::table($table);
        $columns = array_keys($values);
        $s = $db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns)
            . ') VALUES (:' . implode(', :', $columns) . ')');
        $s->execute($values);
        return (int) $db->lastInsertId();
    }
    public static function update(object $db, string $table, array $row, array $values): array
    {
        self::table($table);
        $s = $db->prepare('UPDATE ' . $table . ' SET ' . implode(', ', array_map(
            static fn(string $key): string => $key . ' = :' . $key, array_keys($values)))
            . ' WHERE id = :owned_id AND site_id = :owned_site_id');
        $s->execute($values + ['owned_id' => (int)$row['id'], 'owned_site_id' => (int)$row['site_id']]);
        return array_replace($row, $values);
    }
    private static function table(string $table): void
    {
        if (!in_array($table, ['site_deployments','site_deployment_attempts','site_deployment_health_checks'], true)) {
            throw new LogicException('Not a deployment-owned table.');
        }
    }
    public static function row(object $db, string $table, int $siteId, int $id, bool $lock = false): array
    {
        if (!in_array($table, ['site_deployments','site_deployment_attempts','site_deployment_targets','site_releases'], true)) {
            throw new LogicException('Unsupported deployment read.');
        }
        return SiteBuildStore::one($db, 'SELECT * FROM ' . $table . ' WHERE id = :id AND site_id = :site_id' . ($lock ? ' FOR UPDATE' : ''),
            ['id'=>$id,'site_id'=>$siteId]) ?? throw new SiteServiceException('not_found', 'The deployment record was not found.');
    }
}
