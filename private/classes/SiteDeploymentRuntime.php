<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteDeploymentDependencies.php';
require_once __DIR__ . '/SitePublisherFactory.php';
require_once __DIR__ . '/SiteDeploymentArtifacts.php';
require_once __DIR__ . '/LocalSiteArtifactStore.php';

/** Startup-only admission. Construction and assertReady are pure in-memory operations. */
final class SiteDeploymentRuntime implements SiteDeploymentDependencies
{
    private static ?SitePublisherHostContract $host=null;
    private static ?SitePublisherSession $session=null;
    private static ?SiteArtifactStore $store=null;
    private static array $publishers=[];
    private static ?array $lastTarget=null;
    private static bool $recoveryOnly=false;

    public static function boot(): void
    {
        SiteDeploymentService::guardExternal();
        $actual=realpath($_SERVER['SCRIPT_FILENAME']??'');$root=dirname(__DIR__,2);
        $args=array_slice($GLOBALS['argv']??[],1);
        $execution=$actual===realpath($root.'/scripts/site-deployment-worker.php')&&$args===['--once'];
        $recovery=$actual===realpath($root.'/scripts/reconcile-site-jobs.php')&&count($args)===2&&$args[0]==='--deployment'
            &&preg_match('/^[1-9][0-9]{0,9}$/D',$args[1]);
        if(!$execution&&!$recovery)self::deny();
        if(self::$host!==null)self::deny();
        $host=SitePublisherHostContract::load();$c=$host->configuration();
        if(Database::config('APP_ENV')!=='staging')self::deny();
        // Existing M6C verifier/store, with external guard; no new renderer/package format.
        $store=new LocalSiteArtifactStore($c['artifact_work_root'],$c['artifact_root'],Closure::fromCallable([SiteDeploymentService::class,'guardExternal']));
        self::$store=$store;self::$session=new SitePublisherSession($host);self::$host=$host;self::$recoveryOnly=(bool)$recovery;
    }
    public static function bindLease(array $lease): void
    {if(self::$session===null)self::deny();self::$session->bind($lease);}
    public static function finishInvocation(): void
    {
        // Helper resolves committed outcome itself. On failure EOF leaves a durable unresolved journal;
        // no cleanup path can compensate on a missing COMMIT acknowledgement.
        try{if(self::$session!==null&&self::$lastTarget!==null) {
            $result=self::$session->request('settle',self::$lastTarget);
            if($result!==['settled'=>true])throw new SiteServiceException('conflict','Publisher settlement is unresolved.');
        }}
        finally{self::$session?->close();self::$session=null;self::$publishers=[];self::$lastTarget=null;self::$host=null;self::$store=null;}
    }
    public static function approvedPublisher(array $target): SitePublisher
    {
        if(self::$host===null||self::$session===null)self::deny();
        $binding=self::$host->resolve($target);self::$lastTarget=$target;
        $key=$target['target_id'].':'.$target['binding_version'];
        return self::$publishers[$key]??=new ApacheDigitalOceanSitePublisher(self::$session,$target,SiteHttpHealthChecker::forBinding($binding));
    }
    public function workerIdentity(string $capability): array
    {if(self::$host===null||!in_array($capability,self::$recoveryOnly?['recovery']:['execution','recovery'],true))self::deny();return self::$host->worker();}
    public function operatorId(): int { self::deny(); }
    public function assertReady(array $target, array $release): void
    {
        if(self::$host===null||$release['build_profile']!==SiteBuildContract::PROFILE)self::deny();
        self::$host->resolve(SiteDeploymentContract::target($target));
    }
    public function publisher(array $target): SitePublisher { return SitePublisherFactory::forTarget($target); }
    public function verifyArtifact(array $release): array
    {if(self::$store===null)self::deny();return SiteDeploymentArtifacts::verify(self::$store,$release);}
    public function waitForNextProbe(): void
    {if(self::$host===null)self::deny();SiteDeploymentService::guardExternal();usleep(1000000);}
    private static function deny(): never
    {
        throw new SiteServiceException('future_gate_required', 'Protected deployment capability is not enabled.');
    }
}
