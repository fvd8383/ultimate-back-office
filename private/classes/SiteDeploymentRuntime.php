<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteDeploymentDependencies.php';
require_once __DIR__ . '/SitePublisherFactory.php';

/** Deliberately closed admission until M6E supplies reviewed protected host bindings.
 * No config, database, environment override or filesystem publisher is selected here.
 */
final class SiteDeploymentRuntime implements SiteDeploymentDependencies
{
    public function workerIdentity(string $capability): array { self::deny(); }
    public function operatorId(): int { self::deny(); }
    public function assertReady(array $target, array $release): void { self::deny(); }
    public function publisher(array $target): SitePublisher { return SitePublisherFactory::forTarget($target); }
    public function verifyArtifact(array $release): array { self::deny(); }
    public function waitForNextProbe(): void { self::deny(); }
    private static function deny(): never
    {
        throw new SiteServiceException('future_gate_required', 'Protected deployment capability is not enabled.');
    }
}
