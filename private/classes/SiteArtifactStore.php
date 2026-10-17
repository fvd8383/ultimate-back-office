<?php

declare(strict_types=1);

/** Private storage API. Opaque candidates cannot be supplied by a web request. */
interface SiteArtifactStore
{
    /** Holds a nonblocking per-job lock until finish(); returns a random in-process ownership handle. */
    public function createCandidate(array $job, array $producer, array $identity): string;
    public function writeFile(string $candidate, string $relativePath, string $bytes): void;
    /** Reverify every byte, record producer evidence, then atomic rename without overwrite. */
    public function sealIfAbsent(string $candidate, callable $pulse): array;
    /** No generation, materialization or candidate cleanup during verification/recovery. */
    public function verifyRelease(array $job, array $producer): array;
    public function inspectManifest(array $job, array $producer): array;
    public function openVerifiedFile(array $job, array $producer, string $relativePath): string;
    /** Release lock; discard only this handle's exact tracked candidate, never sealed output. */
    public function finish(string $candidate, bool $discard = false): void;
}
