<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteBuildDependencies.php';
require_once __DIR__ . '/SiteArtifactBuilder.php';
require_once __DIR__ . '/LocalSiteArtifactStore.php';

/** Protected Linux invocation only. No dependency setter, environment switch or web bootstrap. */
final class SiteBuildRuntime implements SiteBuildDependencies
{
    private function __construct(private array $worker, private array $builder, private SiteArtifactStore $store,
        private SiteArtifactBuilder $artifactBuilder, private string $repository) {}

    public static function fromTrustedCli(): self
    {
        $entry = realpath(dirname(__DIR__,2) . '/scripts/site-build-worker.php');
        if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== $entry
            || PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) {
            throw new SiteServiceException('future_gate_required','A protected build-worker invocation is required.');
        }
        if (!class_exists(DOMDocument::class) || !function_exists('getimagesizefromstring') || !function_exists('fsync')
            || !function_exists('filter_var')) SiteArtifactFiles::fail('builder_unavailable');
        self::guard();
        $configPath = '/etc/ubo-site-build-worker.json';
        self::protectedPath($configPath,0,false);
        $config = SiteBuildContract::decode(SiteArtifactFiles::read($configPath,8192),8192);
        $keys = ['version','environment','worker_id','worker_uid','builder_code_sha','work_root','artifact_root','legacy_upload_root'];
        SiteBuildContract::keys($config,$keys,$keys);
        if ($config['version'] !== 1 || $config['environment'] !== 'staging' || !is_int($config['worker_uid'])
            || $config['worker_uid'] <= 0 || posix_geteuid() !== $config['worker_uid']) SiteArtifactFiles::fail('builder_unavailable');
        $repo = realpath(dirname(__DIR__,2));
        self::protectedPath($repo,0,true);
        self::protectedPath($repo . '/.git',0,true);
        self::protectedPath($repo . '/.git/config',0,false);
        $sha = trim(self::git($repo,['rev-parse','HEAD']));
        if ($sha !== SiteBuildContract::hash($config['builder_code_sha'],40)
            || trim(self::git($repo,['status','--porcelain','--untracked-files=all'])) !== '') SiteArtifactFiles::fail('builder_unavailable');
        foreach (explode("\0",rtrim(self::git($repo,['ls-files','-z']),"\0")) as $file) {
            if ($file === '' || str_contains($file,'..') || str_contains($file,'\\') || str_starts_with($file,'/')) SiteArtifactFiles::fail('builder_unavailable');
            self::protectedPath($repo . '/' . $file,0,false);
        }
        // Ignored application credentials are trusted host configuration, never CLI/ENV overrides.
        self::protectedPath($repo . '/private/config/env.php',0,false);
        if (Database::config('APP_ENV') !== $config['environment']) SiteArtifactFiles::fail('builder_unavailable');
        foreach (['work_root','artifact_root'] as $key) self::protectedPath($config[$key],$config['worker_uid'],true,true);
        if ($config['legacy_upload_root'] !== null) self::protectedPath($config['legacy_upload_root'],null,true);
        $guard = Closure::fromCallable([self::class,'guard']);
        $store = new LocalSiteArtifactStore($config['work_root'],$config['artifact_root'],$guard);
        $assets = new SiteArtifactAssetResolver($config['legacy_upload_root'],$guard);
        $builder = self::identity($sha);
        return new self(['worker_id'=>SiteBuildContract::key($config['worker_id']),'environment'=>$config['environment']],
            $builder,$store,new SiteArtifactBuilder($store,$assets),$repo);
    }

    private static function protectedPath(string $path, ?int $owner, bool $directory, bool $private = false): void
    {
        if (realpath($path) !== $path || !str_starts_with($path,'/') || $path === '/') SiteArtifactFiles::fail('builder_unavailable');
        $s = SiteArtifactFiles::regular($path,$directory);
        if (($owner !== null && $s['uid'] !== $owner) || ($s['mode'] & ($private ? 0077 : 0022)) !== 0) SiteArtifactFiles::fail('builder_unavailable');
        $ancestor = dirname($path);
        while ($ancestor !== '/') {
            $a = SiteArtifactFiles::regular($ancestor,true);
            // A non-root ancestor owner could chmod/rename a protected child after startup.
            if ($a['uid'] !== 0 || ($a['mode'] & 0022) !== 0) SiteArtifactFiles::fail('builder_unavailable');
            $ancestor = dirname($ancestor);
        }
    }

    private static function git(string $repo,array $args): string
    {
        // The separately verified root-owned checkout is intentionally read by a non-root worker.
        $process = proc_open(array_merge(['/usr/bin/git','--no-optional-locks','-c','core.fsmonitor=false',
            '-c','safe.directory=' . $repo,'-C',$repo],$args),
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,
            ['PATH'=>'/usr/bin:/bin','HOME'=>'/nonexistent','GIT_CONFIG_NOSYSTEM'=>'1','LANG'=>'C']);
        if (!is_resource($process)) SiteArtifactFiles::fail('builder_unavailable');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $out = ''; $start = hrtime(true); $status = null;
        do {
            $out .= stream_get_contents($pipes[1],1048577 - strlen($out));
            $error = stream_get_contents($pipes[2],8192);
            if (strlen($out)>1048576 || $error !== '' || hrtime(true)-$start>10000000000) {
                proc_terminate($process); fclose($pipes[1]); fclose($pipes[2]); proc_close($process); SiteArtifactFiles::fail('builder_unavailable');
            }
            $status = proc_get_status($process);
            if ($status['running']) usleep(10000);
        } while ($status['running']);
        $out .= stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        if ($status['exitcode'] !== 0 || strlen($out)>1048576) SiteArtifactFiles::fail('builder_unavailable');
        return $out;
    }

    /** Exact checkout SHA is supplied only by the private verified factory; this method grants no authority. */
    public static function identity(string $sha): array
    {
        self::guard();
        return SiteBuildContract::builder(['builder_version'=>'ubo-static-builder-v1','builder_code_sha'=>SiteBuildContract::hash($sha,40),
            'registry_manifest_digest'=>CanonicalJson::hash(['components'=>ComponentRegistry::manifest(),'themes'=>ThemeRegistry::manifest()]),
            'toolchain_contract'=>['canonicalization'=>1,'renderer'=>'per-page-v1','php'=>PHP_VERSION,'libxml'=>LIBXML_DOTTED_VERSION,
                'mbstring'=>extension_loaded('mbstring'),'filter'=>extension_loaded('filter'),
                'static_bundle'=>hash('sha256',SiteArtifactFiles::read(dirname(__DIR__) . '/site-static/static-review-v1.css',1048576))]]);
    }

    public static function guard(): void
    {
        // Read an already initialized connection only; do not open a database merely to test the boundary.
        $property = new ReflectionProperty(Database::class,'connection');
        $db = $property->getValue();
        if ($db !== null && $db->inTransaction()) throw new SiteServiceException('conflict','External artifact work cannot run in a transaction.');
    }
    public function workerIdentity(string $capability): array
    {
        if (!in_array($capability,['execution','recovery'],true)) throw new SiteServiceException('unauthorized','Unsupported worker capability.');
        return $this->worker; // Pre-resolved in memory; safe inside lifecycle locks.
    }
    public function operatorId(): int { throw new SiteServiceException('future_gate_required','Operator identity requires a later authenticated control integration.'); }
    public function builderIdentity(): array
    {
        self::guard();
        // This method is called only through external(), never confirmWorker under locks.
        if (trim(self::git($this->repository,['rev-parse','HEAD'])) !== $this->builder['builder_code_sha']
            || trim(self::git($this->repository,['status','--porcelain','--untracked-files=all'])) !== ''
            || self::identity($this->builder['builder_code_sha']) !== $this->builder) SiteArtifactFiles::fail('builder_unavailable');
        return $this->builder;
    }
    public function prepareInput(array $lockedSource): array { self::guard(); return $this->artifactBuilder->prepareInput($lockedSource); }
    public function execute(array $job,array $producerAttempt,array $lockedSource,callable $pulse): array
    {
        self::guard(); return $this->artifactBuilder->build($job,$producerAttempt,$lockedSource,$pulse);
    }
    public function verifyOutcome(array $job,array $producerAttempt,array $hint,callable $pulse): array
    {
        self::guard(); SiteBuildContract::hint($hint);
        return $this->store->verifyRelease($job,$producerAttempt,$pulse);
    }
    public function inspectManifest(array $release): array
    {
        self::guard();
        [$job,$producer] = SiteServiceSupport::read(static function(object $db) use ($release): array {
            $job = SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id = :id AND site_id = :site_id',
                ['id'=>(int)$release['build_job_id'],'site_id'=>(int)$release['site_id']]);
            if ($job === null) SiteArtifactFiles::fail();
            $attempt = SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$job['current_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>(int)$job['site_id']]);
            if ($attempt !== null && $attempt['attempt_kind'] === 'recovery') $attempt = SiteBuildStore::one($db,
                'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$attempt['recovery_of_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>(int)$job['site_id']]);
            if ($attempt === null) SiteArtifactFiles::fail();
            return [$job,$attempt];
        });
        $e = $this->store->verifyRelease($job,$producer);
        if ($e['disposition'] !== 'sealed' || $e['manifest_hash'] !== $release['manifest_hash'] || $e['artifact_hash'] !== $release['artifact_hash']) {
            return ['integrity_status'=>'fail','files'=>[]];
        }
        return $this->store->inspectManifest($job,$producer);
    }
}
