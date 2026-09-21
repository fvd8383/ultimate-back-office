<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteServiceSupport.php';

/** Small private SQL helper for the build owner; never a generic job platform. */
final class SiteBuildStore
{
    public static function rows(object $db, string $sql, array $params = []): array
    {
        $statement = $db->prepare($sql); $statement->execute($params);
        return $statement->fetchAll();
    }
    public static function one(object $db, string $sql, array $params = []): ?array
    {
        return self::rows($db, $sql, $params)[0] ?? null;
    }
    public static function now(object $db): string
    {
        return (string) self::one($db, 'SELECT UTC_TIMESTAMP(6) AS build_now')['build_now'];
    }
    public static function after(string $now, int $seconds): string
    {
        return (new DateTimeImmutable($now, new DateTimeZone('UTC')))->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s.u');
    }
    public static function insert(object $db, string $table, array $values): int
    {
        self::table($table);
        $columns = array_keys($values);
        $statement = $db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns)
            . ') VALUES (:' . implode(', :', $columns) . ')');
        $statement->execute($values);
        return (int) $db->lastInsertId();
    }
    public static function update(object $db, string $table, array $row, array $values): array
    {
        self::table($table);
        $assignments = array_map(static fn (string $key): string => $key . ' = :' . $key, array_keys($values));
        $statement = $db->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $assignments)
            . ' WHERE id = :owned_id AND site_id = :owned_site_id');
        $statement->execute($values + ['owned_id' => (int) $row['id'], 'owned_site_id' => (int) $row['site_id']]);
        return array_replace($row, $values);
    }
    private static function table(string $table): void
    {
        if (!in_array($table, ['site_build_jobs','site_build_attempts','site_releases','site_release_validations'], true)) {
            throw new LogicException('Not a build-owned table.');
        }
    }
    /** Retry only known rolled-back conflicts, never an uncertain commit.
     * An optional request-winner lookup runs AFTER rollback and BEFORE retrying gates. */
    public static function transaction(callable $callback, bool $duplicateRetry = false, ?callable $afterRollback = null): mixed
    {
        for ($try = 0; ; $try++) {
            $retry = false;
            try {
                return SiteServiceSupport::transaction(static function (object $db) use ($callback, &$retry, $duplicateRetry): mixed {
                    try { return $callback($db); }
                    catch (PDOException $e) {
                        $retry = in_array((int) ($e->errorInfo[1] ?? 0), $duplicateRetry ? [1205,1213,1062] : [1205,1213], true);
                        throw $e;
                    }
                });
            } catch (SiteServiceException $e) {
                if (!$retry && $e->classification() !== 'stale_write') throw $e;
                if ($afterRollback !== null && ($recorded = $afterRollback()) !== null) return $recorded;
                if ($try >= 2) throw $e;
            }
        }
    }
}
