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
    return ' ' . implode(' ', array_map(static fn ($path): string => escapeshellarg(':(exclude)' . $path), m6bApplicationPaths()));
}
function m6bOnlyMigration025(string $root): bool
{
    $later = array_values(array_filter(glob($root . '/database/migrations/*.sql') ?: [],
        static fn ($path): bool => (int) substr(basename($path), 0, 3) >= 25));
    return $later === [$root . '/database/migrations/025_site_build_deployment.sql'];
}
