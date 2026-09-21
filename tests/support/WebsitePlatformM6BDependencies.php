<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/private/classes/SiteBuildService.php';

/** Synthetic, process-isolated DB evidence ONLY. No file bytes are inspected. */
final class WebsitePlatformM6BDependencies implements SiteBuildDependencies
{
    public bool $trusted = true;
    public bool $clean = true;
    public int $operator = 2;
    public string $worker = 'local-fixture-worker';
    public array $receipts = [];
    public int $prepared = 0;
    public int $verified = 0;
    public array $builderOverrides = [];
    public array $projectionOverrides = [];
    public $duringVerify = null;
    public $duringPrepare = null;
    public static function wire(): self
    {
        $runtime = new self();
        (new ReflectionProperty(SiteBuildService::class, 'dependencies'))->setValue(null, $runtime);
        return $runtime;
    }
    public function workerIdentity(string $capability): array
    {
        if (!$this->trusted) throw new SiteServiceException('unauthorized', 'Fixture worker is untrusted.');
        return ['worker_id' => $this->worker, 'environment' => 'local'];
    }
    public function operatorId(): int { return $this->operator; }
    public function builderIdentity(): array
    {
        if (!$this->clean) throw new SiteServiceException('conflict', 'Fixture builder is unidentified.');
        return array_replace(['builder_version' => 'synthetic-m6b-v1', 'builder_code_sha' => str_repeat('a', 40),
            'registry_manifest_digest' => CanonicalJson::hash(ComponentRegistry::manifest()),
            'toolchain_contract' => ['canonicalization' => 1, 'runtime' => 'synthetic-php8', 'static_bundle' => str_repeat('b',64)]], $this->builderOverrides);
    }
    public function prepareInput(array $lockedSource): array
    {
        if (Database::connection()->inTransaction()) throw new LogicException('External work inside transaction');
        $this->prepared++;
        if ($this->duringPrepare !== null) ($this->duringPrepare)();
        $assets = array_map(static fn ($a): array => ['usage_key' => $a['usage_key'], 'sha256' => $a['checksum_sha256'],
            'byte_size' => $a['byte_size'], 'mime_type' => $a['mime_type']], $lockedSource['composition']['assets']);
        usort($assets, static fn ($a, $b): int => strcmp($a['usage_key'], $b['usage_key']));
        // A deterministic synthetic projection, not the M6C public projector.
        return array_replace(['public_facts' => ['name' => 'Synthetic café'], 'public_composition' => ['synthetic' => true],
            'ordered_asset_digests' => $assets], $this->projectionOverrides);
    }
    public function verifyOutcome(array $job, array $producerAttempt, array $hint): array
    {
        if (Database::connection()->inTransaction()) throw new LogicException('External work inside transaction');
        $this->verified++;
        if ($this->duringVerify !== null) { $hook = $this->duringVerify; $this->duringVerify = null; $hook(); }
        if (!isset($this->receipts[$hint['receipt_key']])) throw new SiteServiceException('conflict', 'Unknown synthetic receipt.');
        return $this->receipts[$hint['receipt_key']];
    }
    public function inspectManifest(array $release): array
    {
        if (Database::connection()->inTransaction()) throw new LogicException('External work inside transaction');
        return ['integrity_status' => 'pass', 'files' => [['path' => 'index.html', 'sha256' => str_repeat('d',64),
            'byte_size' => 20, 'mime_type' => 'text/html']]];
    }
    public function receipt(array $job, int $producerId, string $disposition): array
    {
        $key = SiteServiceSupport::uuidV4();
        $evidence = ['job_id' => (int) $job['id'], 'producer_attempt_id' => $producerId, 'release_key' => $job['release_key'],
            'build_input_hash' => $job['build_input_hash'], 'disposition' => $disposition];
        if ($disposition === 'sealed') {
            $manifest = ['synthetic' => true, 'release_key' => $job['release_key'], 'files' => [['path' => 'index.html','sha256' => str_repeat('d',64),'byte_size' => 20]]];
            $evidence += ['artifact_hash' => CanonicalJson::hash(['synthetic_files' => $manifest['files']]),
                'manifest_hash' => CanonicalJson::hash($manifest), 'manifest' => $manifest,
                'storage_backend' => 'synthetic', 'storage_key' => 'synthetic/' . $job['release_key'],
                'validation_summary' => ['synthetic_db_evidence_only' => true], 'file_count' => 1, 'byte_size' => 20,
                'validator_version' => 'synthetic-v1'];
        }
        $this->receipts[$key] = $evidence;
        return ['receipt_key' => $key];
    }
}
