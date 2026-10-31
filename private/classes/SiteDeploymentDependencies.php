<?php

declare(strict_types=1);
require_once __DIR__ . '/SitePublisher.php';

/** Trusted process dependencies, never request DTOs. No public service setter.
 * Identity and readiness are pre-resolved in memory and may be checked under locks.
 * Artifact verification and publisher operations MUST use the external guard.
 */
interface SiteDeploymentDependencies
{
    public function workerIdentity(string $capability): array;
    public function operatorId(): int;
    public function assertReady(array $target, array $release): void;
    public function publisher(array $target): SitePublisher;
    public function verifyArtifact(array $release): array;
    public function waitForNextProbe(): void;
}
