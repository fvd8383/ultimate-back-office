<?php

declare(strict_types=1);

/** Exact later-M6B allowances; historical suite baselines are deliberately unchanged. */
function m6bApplicationPaths(): array
{
    return [
        'database/migrations/025_site_build_deployment.sql',
        'private/classes/SiteApprovalManager.php',
        'private/classes/SiteRevisionManager.php',
        'private/classes/SiteBuildService.php',
        'private/classes/SiteBuildStore.php',
        'private/classes/SiteBuildContract.php',
        'private/classes/SiteBuildDependencies.php',
    ];
}
function m6bScopeExclusions(): string
{
    if (!m6bCanonicalMigrationsValid(dirname(__DIR__, 2))) throw new RuntimeException('Canonical migration integrity failed.');
    $paths = array_merge(m6bApplicationPaths(), array_keys(m6bApprovedDnsMigrationBlobs()));
    return ' ' . implode(' ', array_map(static fn ($path): string => escapeshellarg(':(exclude)' . $path), $paths));
}
/** Only the reviewed DNS bootstrap correction; a filename alone never authorizes new content. */
function m6bApprovedDnsMigrationBlobs(): array
{
    return [
        'database/migrations/017_domain_services_automation.sql' => 'a8345bed9669e2e43b29a3c40de593cbce1f44de',
        'database/migrations/019_repair_domain_services_schema.sql' => 'de2d1ce8c3481ae034383be4b138a446650d5d51',
    ];
}
function m6bCanonicalMigrationsValid(string $root): bool
{
    // Fixed implementation baseline, including its exact filenames. Cache only immutable Git
    // metadata; read the supplied checkout's current bytes on every call (also used by fixtures).
    static $original = null;
    if ($original === null) {
        $entries = [];
        exec('git -C ' . escapeshellarg(dirname(__DIR__, 2))
            . ' ls-tree -r 5baae28c9af68cca7694a912d7f35c87c50c9dc5 -- database/migrations', $entries, $status);
        $entries = array_values(array_filter($entries, static fn($entry) => str_ends_with($entry, '.sql')));
        if ($status !== 0 || count($entries) !== 24) return false;
        $original = [];
        foreach ($entries as $entry) {
            if (!preg_match('~^100644 blob ([a-f0-9]{40})\t(database/migrations/[0-9]{3}_[a-z0-9_]+\.sql)$~D', $entry, $match)) {
                $original = null;
                return false;
            }
            $original[$match[2]] = $match[1];
        }
    }
    $expected = array_replace($original, m6bApprovedDnsMigrationBlobs());
    $expected['database/migrations/025_site_build_deployment.sql'] = null;
    $files = glob($root . '/database/migrations/*') ?: [];
    if (array_map('basename', $files) !== array_map('basename', array_keys($expected))) return false;
    foreach ($expected as $path => $blob) {
        if (!is_file($root . '/' . $path) || is_link($root . '/' . $path)) return false;
        // Match canonical Git LF bytes while retaining the repository's Windows checkout policy.
        $sql = str_replace("\r\n", "\n", file_get_contents($root . '/' . $path));
        if ($blob === null) {
            if (hash('sha256', $sql) !== 'dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0') return false;
        } elseif (sha1('blob ' . strlen($sql) . "\0" . $sql) !== $blob) return false;
    }
    return true;
}
