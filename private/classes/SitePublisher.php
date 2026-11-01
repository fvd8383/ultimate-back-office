<?php

declare(strict_types=1);

/** Provider boundary. Arrays are strictly checked by SiteDeploymentContract.
 * Implementations never write lifecycle, grant, job or pointer tables.
 * Every call is outside SQL transactions. Unknown outcomes remain unknown.
 */
interface SitePublisher
{
    public function stageRelease(array $release, array $target, array $context): array;
    public function activateRelease(array $staged, array $expectedCurrent, array $context): array;
    public function healthCheck(array $expected, array $target, array $context): array;
    public function inspectCurrentRelease(array $target): array;
    public function restoreRelease(array $stagedKnownGood, array $expectedCurrent, array $context): array;
    public function reconcileExternalState(array $intent, array $target, array $context): array;
}
