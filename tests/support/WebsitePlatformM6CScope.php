<?php

declare(strict_types=1);

/** Exact authorized M6C executable additions/integrations; no directory-wide exemptions. */
function m6cApplicationPaths():array
{
    return ['private/classes/SiteArtifactBuilder.php','private/classes/SiteArtifactValidator.php',
        'private/classes/SiteArtifactStore.php','private/classes/LocalSiteArtifactStore.php',
        'private/classes/SitePublicRenderContext.php','private/classes/SiteArtifactFiles.php',
        'private/classes/SiteArtifactAssetResolver.php','private/classes/SiteBuildRuntime.php',
        'private/classes/SiteBuildService.php','private/classes/SiteBuildDependencies.php',
        'private/classes/SiteCompositionRenderer.php','private/classes/SiteRevisionManager.php',
        'private/site-static/static-review-v1.css','scripts/site-build-worker.php'];
}
function m6cScopeExclusions():string
{
    return ' '.implode(' ',array_map(static fn($p)=>escapeshellarg(':(exclude)'.$p),m6cApplicationPaths()));
}
