<?php
declare(strict_types=1);
require_once __DIR__.'/SiteDeploymentContract.php';

/** Operator-owned startup snapshot. No request/environment overrides, and no I/O in resolve(). */
final class SitePublisherHostContract
{
    public const PROVIDER='apache-digitalocean-v1';
    public const CONFIG='/etc/ubo-site-publisher/worker.json';
    public const RECEIPT='/etc/ubo-site-publisher/host-receipt.json';
    public const GATES=['apache','filesystem','ownership','assets','runtime','health','recovery','provisioning'];
    private function __construct(private array $config) {}

    public static function deny(): never
    { throw new SiteServiceException('future_gate_required','Verified staging publisher host contract is required.'); }

    public static function load(): self
    {
        if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||!extension_loaded('curl')
            ||!extension_loaded('sockets')||!function_exists('fsync')) self::deny();
        SiteDeploymentService::guardExternal();
        $raw=self::protectedFile(self::CONFIG,65536);
        $config=SiteBuildContract::decode($raw,65536);
        $receipt=SiteBuildContract::decode(self::protectedFile(self::RECEIPT,65536),65536);
        self::validate($config,$receipt,hash('sha256',$raw),time());
        if(posix_geteuid()!==$config['worker_uid'])self::deny();
        $application=dirname(__DIR__).'/config/env.php';
        if(hash('sha256',self::protectedFile($application,65536))!==$config['application_config_sha256']
            ||(fileperms($application)&0007)!==0)self::deny();
        foreach(['artifact_work_root','artifact_root'] as $key)self::protectedDirectory($config[$key],$config['worker_uid']);
        foreach($receipt['installed_files'] as $path=>$digest) {
            if(!is_string($path)||!is_string($digest)||hash('sha256',self::protectedFile($path,2097152))!==$digest)self::deny();
        }
        foreach(get_included_files() as $path) {
            if(!isset($receipt['installed_files'][$path]))self::deny();
        }
        foreach($config['bindings'] as $b) {
            self::protectedFile($b['ca_file'],1048576);
            self::protectedFile($b['client_certificate'],1048576);
            self::protectedFile($b['client_key'],65536);
            if((fileperms($b['client_key'])&0007)!==0)self::deny();
        }
        return new self($config);
    }

    /** Pure schema/receipt admission; calling it does not produce an admitted runtime. */
    public static function validate(array $c,array $r,string $digest,int $now): void
    {
        SiteDeploymentContract::keys($c,['version','environment','worker_id','worker_uid','publisher_uid','authority_uid',
            'helper_socket','application_config_sha256','artifact_work_root','artifact_root','bindings']);
        if($c['version']!==1||$c['environment']!=='staging'||$c['helper_socket']!=='/run/ubo-site-publisher/publish.sock')self::deny();
        SiteBuildContract::key($c['worker_id']);
        SiteBuildContract::hash($c['application_config_sha256']);
        foreach(['worker_uid','publisher_uid','authority_uid'] as $k)SiteBuildContract::id($c[$k]);
        if(count(array_unique([$c['worker_uid'],$c['publisher_uid'],$c['authority_uid']]))!==3)self::deny();
        foreach(['artifact_work_root','artifact_root'] as $k)self::absolute($c[$k]);
        if(!is_array($c['bindings'])||!array_is_list($c['bindings'])||count($c['bindings'])<1||count($c['bindings'])>32)self::deny();
        $seen=[];
        foreach($c['bindings'] as $b) {
            SiteDeploymentContract::keys($b,['site_id','site_key','target_id','binding_key','binding_version','candidate','active',
                'ca_file','client_certificate','client_key']);
            foreach(['site_id','target_id','binding_version'] as $k)SiteBuildContract::id($b[$k]);
            SiteBuildContract::uuid($b['site_key']);SiteBuildContract::key($b['binding_key']);
            $key=$b['binding_key'].':'.$b['binding_version'];
            if(isset($seen[$key])||isset($seen['target:'.$b['target_id']]))self::deny();
            $seen[$key]=true;$seen['target:'.$b['target_id']]=true;
            foreach(['candidate','active'] as $k)self::endpoint($b[$k]);
            if($b['candidate']===$b['active'])self::deny();
            foreach(['ca_file','client_certificate','client_key'] as $k)self::absolute($b[$k]);
        }
        SiteDeploymentContract::keys($r,['version','profile','worker_config_sha256','verified_at','expires_at','gates','installed_files']);
        if($r['version']!==1||$r['profile']!=='m6e-host-v1'||$r['worker_config_sha256']!==$digest
            ||!is_int($r['verified_at'])||!is_int($r['expires_at'])||$r['verified_at']>$now||$r['expires_at']<=$now
            ||$r['expires_at']-$r['verified_at']>86400)self::deny();
        SiteDeploymentContract::keys($r['gates'],self::GATES);
        foreach($r['gates'] as $g) {
            SiteDeploymentContract::keys($g,['result','evidence_sha256']);
            if($g['result']!=='pass')self::deny();SiteBuildContract::hash($g['evidence_sha256']);
        }
        $required=['/usr/local/lib/ubo-site-publisher/ubo_site_publish.py','/usr/local/sbin/ubo-site-publish',
            '/etc/ubo-site-publisher/helper.json','/etc/ubo-site-publisher/apache.conf','/etc/ubo-site-publisher/authority.json',
            '/usr/local/lib/ubo-site-publisher/ubo-site-authority.php'];
        if(!is_array($r['installed_files'])||count($r['installed_files'])>256||array_diff($required,array_keys($r['installed_files'])))self::deny();
        foreach($r['installed_files'] as $p=>$h){self::absolute($p);SiteBuildContract::hash($h);}
    }

    private static function absolute(mixed $p): void
    { if(!is_string($p)||!preg_match('~^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+$~D',$p)||str_contains($p,'..'))self::deny(); }
    private static function endpoint(array $e): void
    {
        SiteDeploymentContract::keys($e,['host','ip','port']);
        if(!is_string($e['host'])||!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)+$/D',$e['host'])
            ||filter_var($e['ip'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false||$e['port']!==443)self::deny();
    }
    public static function protectedFile(string $path,int $limit): string
    {
        self::absolute($path);
        for($p=$path;$p!=='/';$p=dirname($p)) {
            clearstatcache(true,$p);$s=@lstat($p);
            if($s===false||$s['uid']!==0||($s['mode']&0022)!==0
                ||($s['mode']&0170000)!==($p===$path?0100000:0040000)||($p===$path&&$s['nlink']!==1))self::deny();
        }
        $h=@fopen($path,'rb');if($h===false)self::deny();
        try {
            $before=fstat($h);$name=lstat($path);
            if($before['dev']!==$name['dev']||$before['ino']!==$name['ino']||$before['size']>$limit)self::deny();
            $bytes=stream_get_contents($h,$limit+1);$after=fstat($h);
            if($bytes===false||strlen($bytes)!==$before['size'])self::deny();
            foreach(['dev','ino','mode','nlink','uid','gid','size','mtime','ctime'] as $key)if($before[$key]!==$after[$key])self::deny();
            return $bytes;
        } finally {fclose($h);}
    }
    private static function protectedDirectory(string $path,int $owner): void
    {
        self::absolute($path);
        for($p=$path;$p!=='/';$p=dirname($p)) {
            clearstatcache(true,$p);$s=@lstat($p);
            if($s===false||($s['mode']&0170000)!==0040000||($s['mode']&0022)!==0||$s['uid']!==($p===$path?$owner:0))self::deny();
        }
    }
    public function resolve(array $target): array
    {
        if(($target['environment']??null)!=='staging'||($target['publisher_key']??null)!==self::PROVIDER)self::deny();
        foreach($this->config['bindings'] as $b)if($b['site_id']===($target['site_id']??null)
            &&$b['target_id']===($target['target_id']??null)&&$b['binding_key']===($target['binding_key']??null)
            &&$b['binding_version']===($target['binding_version']??null))return $b;
        self::deny();
    }
    public function worker(): array {return ['worker_id'=>$this->config['worker_id'],'environment'=>'staging'];}
    public function configuration(): array {return $this->config;}
}
