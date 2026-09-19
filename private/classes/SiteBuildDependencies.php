<?php

declare(strict_types=1);

/**
 * Internal M6C boundary, deliberately UNWIRED in normal runtime.
 * Implementations are trusted application wiring, never request data. There is
 * no public setter, environment bypass, artifact implementation or worker here.
 * Tests inject a deterministic implementation into SiteBuildService's private
 * slot using Reflection, in their isolated process only.
 */
interface SiteBuildDependencies
{
    /** Authenticate the running process/environment, not a submitted actor array.
     * Must use current trusted in-process wiring only: no I/O, safe under SQL locks.
     * @return array{worker_id:string,environment:string} */
    public function workerIdentity(string $capability): array;

    /** Authenticated operator of the internal invocation; NOT a submitted user ID. */
    public function operatorId(): int;

    /** Identify the clean reviewed toolchain; must fail on dirty/unknown code.
     * @return array{builder_version:string,builder_code_sha:string,registry_manifest_digest:string,toolchain_contract:array} */
    public function builderIdentity(): array;

    /** Public projection/asset-byte validation outside transactions. No live profile.
     * Return only public_composition, public_facts and ordered_asset_digests.
     * Locator handles, if needed by M6C, must be resolved privately from exact input. */
    public function prepareInput(array $lockedSource): array;

    /**
     * Outside SQL: verify exact producer output or safe absence/quarantine.
     * An opaque receipt lookup hint is NOT proof. This implementation must
     * independently verify storage/journal/bytes and bind evidence to job AND
     * producer attempt, including during recovery. No rendering in this method.
     * @return array Verified evidence, validated by SiteBuildContract::evidence.
     */
    public function verifyOutcome(array $job, array $producerAttempt, array $hint): array;

    /** Reverify a release and return sanitized manifest data, outside SQL. */
    public function inspectManifest(array $release): array;
}
