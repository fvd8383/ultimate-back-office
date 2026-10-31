<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteArtifactStore.php';
require_once __DIR__ . '/SiteArtifactValidator.php';

/**
 * Cooperating writers in protected same-filesystem roots only. flock + rename
 * establishes process atomicity; this PHP adapter does NOT claim power-loss durability
 * of directory entries. Linux ownership/mount/crash validation remains a host gate.
 */
final class LocalSiteArtifactStore implements SiteArtifactStore
{
    private array $candidates = [];
    public function __construct(private string $workRoot, private string $sealedRoot, private Closure $guard)
    {
        ($this->guard)();
        foreach ([$workRoot,$sealedRoot] as $root) {
            $real = realpath($root);
            if ($real === false || rtrim($root, '/\\') !== $real || dirname($real) === $real) SiteArtifactFiles::fail('builder_unavailable');
            SiteArtifactFiles::regular($root, true);
            $repo = realpath(dirname(__DIR__,2));
            if ($repo !== false && ($real === $repo || str_starts_with($real,$repo . DIRECTORY_SEPARATOR))) SiteArtifactFiles::fail('builder_unavailable');
        }
        if ($workRoot === $sealedRoot || str_starts_with($workRoot,$sealedRoot . DIRECTORY_SEPARATOR)
            || str_starts_with($sealedRoot,$workRoot . DIRECTORY_SEPARATOR)
            || stat($workRoot)['dev'] !== stat($sealedRoot)['dev']) SiteArtifactFiles::fail('builder_unavailable');
    }

    private static function binding(array $job, array $producer): array
    {
        if ($producer['attempt_kind'] !== 'execution' || (int)$producer['build_job_id'] !== (int)$job['id']
            || (int)$producer['site_id'] !== (int)$job['site_id']) SiteArtifactFiles::fail();
        return ['job_id'=>(int)$job['id'],'site_id'=>(int)$job['site_id'],'job_key'=>SiteBuildContract::uuid($job['job_key']),
            'producer_attempt_id'=>SiteBuildContract::id((int)$producer['id']), 'producer_attempt_number'=>(int)$producer['attempt_number'],
            'release_key'=>SiteBuildContract::uuid($job['release_key']),'build_input_hash'=>SiteBuildContract::hash($job['build_input_hash']),
            'snapshot_hash'=>$job['snapshot_hash'],'builder_code_sha'=>$job['builder_code_sha']];
    }

    private function lock(array $job, bool $create = true): mixed
    {
        ($this->guard)();
        $key = SiteBuildContract::uuid($job['job_key']);
        $dir = $this->workRoot . '/' . $key;
        if (!$create && (!is_dir($dir) || !is_file($dir . '/writer.lock'))) SiteArtifactFiles::fail('outcome_unknown');
        if (!file_exists($dir) && !@mkdir($dir,0700) && !is_dir($dir)) SiteArtifactFiles::fail('storage_unavailable');
        SiteArtifactFiles::contained($this->workRoot,$key,true);
        $path = $dir . '/writer.lock';
        if (!file_exists($path)) {
            $h = @fopen($path,'xb');
            if ($h !== false) { fclose($h); if (!@chmod($path,0600)) SiteArtifactFiles::fail('storage_unavailable'); }
        }
        SiteArtifactFiles::regular($path);
        $h = @fopen($path,'r+b');
        if ($h === false || !flock($h, LOCK_EX | LOCK_NB)) {
            if (is_resource($h)) fclose($h);
            SiteArtifactFiles::fail('outcome_unknown');
        }
        return $h;
    }

    public function createCandidate(array $job, array $producer, array $identity): string
    {
        $binding = self::binding($job,$producer);
        SiteBuildContract::uuid($identity['site_key']); SiteBuildContract::id($identity['revision_number']);
        if ($identity['release_key'] !== $job['release_key']) SiteArtifactFiles::fail();
        $lock = $this->lock($job);
        try {
            ($this->guard)();
            $free = @disk_free_space($this->workRoot);
            if ($free === false || $free < SiteArtifactFiles::MAX_PACKAGE + SiteArtifactFiles::MAX_MANIFEST + SiteArtifactFiles::MAX_VALIDATION) {
                SiteArtifactFiles::fail('storage_unavailable');
            }
            $stem = $this->workRoot . '/' . $binding['job_key'] . '/attempt-' . $binding['producer_attempt_id'];
            if (file_exists($stem) || file_exists($stem . '.json') || file_exists($stem . '.json.pending') || file_exists($stem . '.seal.json')) SiteArtifactFiles::fail('outcome_unknown');
            $sealed = $this->sealedRoot . '/' . $identity['site_key'] . '/' . $job['release_key'];
            if (file_exists($sealed)) SiteArtifactFiles::fail('outcome_unknown');
            // Only publish the journal after write/flush/fsync/chmod succeeds. A complete-looking
            // but unsuccessfully synced pending file must never establish safe absence.
            SiteArtifactFiles::writeNew($stem . '.json.pending',CanonicalJson::encode(['binding'=>$binding,'identity'=>$identity]),4096);
            if (!@rename($stem . '.json.pending',$stem . '.json')) SiteArtifactFiles::fail('outcome_unknown');
            SiteArtifactFiles::mkdir($stem);
            $handle = bin2hex(random_bytes(32));
            $this->candidates[$handle] = ['directory'=>$stem,'job'=>$job,'producer'=>$producer,'identity'=>$identity,
                'binding'=>$binding,'lock'=>$lock,'files'=>[],'bytes'=>0,'sealed'=>false];
            return $handle;
        } catch (Throwable $e) { flock($lock,LOCK_UN); fclose($lock); throw $e; }
    }

    public function writeFile(string $candidate, string $relativePath, string $bytes): void
    {
        ($this->guard)();
        if (!isset($this->candidates[$candidate]) || $this->candidates[$candidate]['sealed']) SiteArtifactFiles::fail();
        $c = &$this->candidates[$candidate];
        SiteArtifactFiles::path($relativePath);
        $expected = SiteArtifactValidator::expected(SiteBuildContract::decode($c['job']['input_manifest_json']));
        if (!isset($expected[$relativePath]) && !in_array($relativePath,['metadata/manifest.json','metadata/validation.json'],true)) SiteArtifactFiles::fail();
        $max = match (true) {
            $relativePath === 'metadata/validation.json' => SiteArtifactFiles::MAX_VALIDATION,
            str_ends_with($relativePath,'.html'), $relativePath === 'metadata/manifest.json' => SiteArtifactFiles::MAX_HTML,
            $relativePath === 'public/_release.json' => 1024,
            default => SiteArtifactFiles::MAX_ASSET,
        };
        if (isset($c['files'][$relativePath]) || count($c['files']) >= SiteArtifactFiles::MAX_FILES || strlen($bytes) > $max) SiteArtifactFiles::fail();
        if (str_starts_with($relativePath,'public/')) {
            if ($c['bytes'] + strlen($bytes) > SiteArtifactFiles::MAX_PACKAGE) SiteArtifactFiles::fail();
            $c['bytes'] += strlen($bytes);
        }
        $directory = $c['directory'];
        foreach (array_slice(explode('/',$relativePath),0,-1) as $part) {
            $directory .= '/' . $part;
            if (!file_exists($directory)) SiteArtifactFiles::mkdir($directory);
            SiteArtifactFiles::regular($directory,true);
        }
        $c['files'][$relativePath] = true; // Track partial writes for ownership-limited cleanup.
        SiteArtifactFiles::writeNew($c['directory'] . '/' . $relativePath,$bytes,$max);
    }

    public function sealIfAbsent(string $candidate, callable $pulse): array
    {
        ($this->guard)();
        if (!isset($this->candidates[$candidate])) SiteArtifactFiles::fail();
        $c = &$this->candidates[$candidate];
        $manifest = SiteArtifactValidator::verify($c['directory'],$c['job'],$c['identity'],$pulse);
        $hash = CanonicalJson::hash($manifest);
        SiteArtifactFiles::writeNew($c['directory'] . '.seal.json',CanonicalJson::encode(['binding'=>$c['binding'],'manifest_hash'=>$hash]),4096);
        $siteDir = $this->sealedRoot . '/' . $c['identity']['site_key'];
        if (!file_exists($siteDir) && !@mkdir($siteDir,0700) && !is_dir($siteDir)) SiteArtifactFiles::fail('storage_unavailable');
        SiteArtifactFiles::contained($this->sealedRoot,$c['identity']['site_key'],true);
        $target = $siteDir . '/' . $c['job']['release_key'];
        // The per-job lock serializes all writers for the DB-reserved release identity.
        // A colliding namespace from another identity is never replaced, even if empty.
        if (file_exists($target) || is_link($target)) SiteArtifactFiles::fail('outcome_unknown');
        $pulse(true); ($this->guard)();
        if (!@rename($c['directory'],$target)) SiteArtifactFiles::fail('outcome_unknown');
        $c['sealed'] = true;
        $observed = SiteArtifactValidator::verify($target,$c['job'],$c['identity'],$pulse);
        if (CanonicalJson::hash($observed) !== $hash) SiteArtifactFiles::fail();
        return ['receipt_key'=>$c['job']['release_key']];
    }

    public function verifyRelease(array $job, array $producer, ?callable $pulse = null): array
    {
        $progressFailure = null;
        $progress = function () use ($pulse,&$progressFailure): void {
            try { ($this->guard)(); if ($pulse !== null) $pulse(); }
            catch (Throwable $e) { $progressFailure = $e; throw $e; }
        };
        $progress();
        $binding = self::binding($job,$producer);
        $base = ['job_id'=>(int)$job['id'],'producer_attempt_id'=>(int)$producer['id'],
            'release_key'=>$job['release_key'],'build_input_hash'=>$job['build_input_hash']];
        try { $lock = $this->lock($job,false); }
        catch (SiteArtifactException) { return $base + ['disposition'=>'unknown']; }
        try {
            $stem = $this->workRoot . '/' . $binding['job_key'] . '/attempt-' . $binding['producer_attempt_id'];
            if (file_exists($stem . '.json.pending') || is_link($stem . '.json.pending')) return $base + ['disposition'=>'unknown'];
            // Missing producer journal is ambiguous, including a write interrupted before its fsync.
            if (!file_exists($stem . '.json')) return $base + ['disposition'=>'unknown'];
            $journal = SiteBuildContract::decode(SiteArtifactFiles::read($stem . '.json',4096,$progress),4096);
            if (($journal['binding'] ?? null) !== CanonicalJson::canonicalize($binding)) SiteArtifactFiles::fail();
            $identity = $journal['identity'];
            SiteBuildContract::uuid($identity['site_key']); SiteBuildContract::id($identity['revision_number']);
            $key = $identity['site_key'] . '/' . $job['release_key'];
            $target = $this->sealedRoot . '/' . $key;
            if (!file_exists($stem . '.seal.json') && !file_exists($target) && !is_link($target)) {
                // Exclusive lock proves no producer is still writing. A journaled,
                // never-sealed candidate stays private and cannot be resumed by createCandidate().
                if (!file_exists($stem)) return $base + ['disposition'=>'safe_absence'];
                $actual = SiteArtifactFiles::inventory($stem,$progress);
                $allowed = array_merge(array_keys(SiteArtifactValidator::expected(SiteBuildContract::decode($job['input_manifest_json']))),
                    ['metadata/manifest.json','metadata/validation.json']);
                if (array_diff($actual,$allowed) !== []) SiteArtifactFiles::fail();
                return $base + ['disposition'=>'quarantined'];
            }
            if (!file_exists($stem . '.seal.json') || !file_exists($target)) {
                return $base + ['disposition'=>'unknown']; // Prepared seal without observed final bytes is ambiguous.
            }
            $proof = SiteBuildContract::decode(SiteArtifactFiles::read($stem . '.seal.json',4096,$progress),4096);
            if (($proof['binding'] ?? null) !== CanonicalJson::canonicalize($binding)) SiteArtifactFiles::fail();
            SiteArtifactFiles::contained($this->sealedRoot,$key,true);
            $manifest = SiteArtifactValidator::verify($target,$job,$identity,$progress);
            $hash = CanonicalJson::hash($manifest);
            if (($proof['manifest_hash'] ?? null) !== $hash) SiteArtifactFiles::fail();
            return $base + ['disposition'=>'sealed','artifact_hash'=>$manifest['artifact_hash'],'manifest_hash'=>$hash,
                'storage_backend'=>'local','storage_key'=>$key,'manifest'=>$manifest,'validation_summary'=>$manifest['validation_summary'],
                'file_count'=>$manifest['file_count'],'byte_size'=>$manifest['byte_size'],'validator_version'=>SiteArtifactValidator::VERSION];
        } catch (SiteArtifactException | SiteServiceException $e) {
            // Lost lease/authority is an operation failure, never an integrity disposition.
            if ($progressFailure !== null) throw $progressFailure;
            return $base + ['disposition'=>'unknown'];
        }
        finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    public function inspectManifest(array $job, array $producer): array
    {
        $evidence = $this->verifyRelease($job,$producer);
        if ($evidence['disposition'] !== 'sealed') return ['integrity_status'=>'fail','files'=>[]];
        // The public JSON marker is verified but omitted from the existing M6B MIME-limited inspection DTO.
        return ['integrity_status'=>'pass','files'=>array_values(array_map(static fn ($f) =>
            ['path'=>$f['relative_path'],'sha256'=>$f['sha256'],'byte_size'=>$f['byte_size'],'mime_type'=>$f['mime_type']],
            array_filter($evidence['manifest']['files'],static fn ($f) => $f['mime_type'] !== 'application/json')))];
    }

    public function openVerifiedFile(array $job, array $producer, string $relativePath): string
    {
        $e = $this->verifyRelease($job,$producer);
        if ($e['disposition'] !== 'sealed') SiteArtifactFiles::fail();
        foreach ($e['manifest']['files'] as $file) if ($file['relative_path'] === $relativePath) {
            ($this->guard)();
            $bytes = SiteArtifactFiles::read(SiteArtifactFiles::contained($this->sealedRoot,$e['storage_key'] . '/' . $relativePath),$file['byte_size']);
            if (hash('sha256',$bytes) !== $file['sha256']) SiteArtifactFiles::fail();
            return $bytes;
        }
        SiteArtifactFiles::fail();
    }

    public function finish(string $candidate, bool $discard = false): void
    {
        if (!isset($this->candidates[$candidate])) SiteArtifactFiles::fail();
        $c = $this->candidates[$candidate];
        try {
            ($this->guard)();
            if ($discard && !$c['sealed']) {
                $actual = SiteArtifactFiles::inventory($c['directory']);
                if (array_diff($actual,array_keys($c['files'])) !== []) SiteArtifactFiles::fail();
                $dirs = [];
                foreach ($actual as $file) {
                    $path = SiteArtifactFiles::contained($c['directory'],$file);
                    if (!@unlink($path)) SiteArtifactFiles::fail('storage_unavailable');
                    $dir = dirname($path);
                    while ($dir !== $c['directory']) { $dirs[$dir]=true; $dir=dirname($dir); }
                }
                uksort($dirs,static fn($a,$b)=>strlen($b)<=>strlen($a));
                foreach ($dirs as $dir=>$_) if (!@rmdir($dir)) SiteArtifactFiles::fail('storage_unavailable');
                if (!@rmdir($c['directory'])) SiteArtifactFiles::fail('storage_unavailable');
            }
        } finally { flock($c['lock'],LOCK_UN); fclose($c['lock']); unset($this->candidates[$candidate]); }
    }
}
