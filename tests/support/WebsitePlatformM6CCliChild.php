<?php
declare(strict_types=1);
namespace M6CIsolatedCli;
use Closure;
use DOMDocument;
use ReflectionProperty;
use Throwable;
use RuntimeException;

// Test-only namespaced source copies and deny-only doubles. No operational selector.
require_once dirname(__DIR__,2).'/private/classes/SiteArtifactFiles.php';
require_once dirname(__DIR__,2).'/private/classes/SiteBuildDependencies.php';
foreach(['SiteServiceException','SiteArtifactException','SiteBuildContract','SiteBuildDependencies']as$name) \class_alias($name,__NAMESPACE__.'\\'.$name);
$GLOBALS['case']=$argv[1]??'missing-config';$case=$GLOBALS['case'];
$GLOBALS['trace']=['bootstrap'=>0,'virtual_config_reads'=>0,'host_config_reads'=>0,'db'=>0,'claims'=>0,'file_output'=>0,'blocked_processes'=>0,'http_status'=>null];
\define(__NAMESPACE__.'\\PHP_OS_FAMILY',$case==='unsupported-os'?'Windows':'Linux');
\define(__NAMESPACE__.'\\PHP_SAPI',$case==='web'?'cgi-fcgi':'cli');
$_SERVER['SCRIPT_FILENAME']=$case==='wrong-entry'?'/wrong-entry.php':'/fixture/repo/scripts/site-build-worker.php';
function realpath(string $path): string|false {
    if($path==='/etc/ubo-site-build-worker.json'&&$GLOBALS['case']==='missing-config')return false;
    if($path==='/wrong-entry.php')return $path;
    if(str_ends_with($path,'/scripts/site-build-worker.php'))return '/fixture/repo/scripts/site-build-worker.php';
    if(str_starts_with($path,'/etc')||str_starts_with($path,'/fixture'))return $path;
    return '/fixture/repo';
}
function dirname(string $path,int $levels=1): string {
    if(!str_starts_with($path,'/'))return \dirname($path,$levels);
    while($levels-->0){$at=strrpos(rtrim($path,'/'),'/');$path=$at===false||$at===0?'/':substr($path,0,$at);}
    return $path;
}
function function_exists(string $name): bool {return $GLOBALS['case']!==('missing-'.$name);}
function class_exists(string $name): bool {return !($GLOBALS['case']==='missing-dom'&&$name===DOMDocument::class);}
function posix_geteuid(): int {return $GLOBALS['case']==='wrong-uid'?3000:2000;}
function http_response_code(int $code): int {$GLOBALS['trace']['http_status']=$code;return $code;}
function proc_open(array $command,array $descriptors,?array &$pipes=null,...$rest): never {$GLOBALS['trace']['blocked_processes']++;throw new RuntimeException('External processes forbidden');}
function bootstrap(): void {$GLOBALS['trace']['bootstrap']++;}
final class Database {
    private static $connection=null;
    public static function config(...$args): never {$GLOBALS['trace']['db']++;throw new RuntimeException('Operational config forbidden');}
    public static function connection(): never {$GLOBALS['trace']['db']++;throw new RuntimeException('Database forbidden');}
}
final class SiteArtifactFiles {
    public static function fail(string $code='artifact_invalid'): never {throw new SiteArtifactException($code);}
    public static function regular(string $path,bool $directory=false): array {
        $case=$GLOBALS['case'];
        return ['uid'=>($case==='wrong-owner'&&str_ends_with($path,'.json'))||($case==='ancestor-owner'&&$path==='/etc')?2000:0,
            'mode'=>$case==='writable-config'&&str_ends_with($path,'.json')?0666:($directory?0755:0600)];
    }
    public static function read(string $path,int $max): string {
        if($path!=='/etc/ubo-site-build-worker.json')throw new RuntimeException('Unexpected read forbidden');
        $GLOBALS['trace']['virtual_config_reads']++;
        if($GLOBALS['case']==='invalid-json')return '{';
        $config=['version'=>1,'environment'=>'staging','worker_id'=>'fixture-worker','worker_uid'=>2000,
            'builder_code_sha'=>str_repeat('a',40),'work_root'=>'/fixture/work','artifact_root'=>'/fixture/sealed','legacy_upload_root'=>null];
        if($GLOBALS['case']==='invalid-config')$config['environment']='production';
        if($GLOBALS['case']==='root-worker')$config['worker_uid']=0;
        return json_encode($config,JSON_THROW_ON_ERROR);
    }
}
final class SiteBuildService {
    public static function claimBuild(array $context): never {
        SiteBuildRuntime::fromTrustedCli();$GLOBALS['trace']['claims']++;throw new RuntimeException('Queue access forbidden');
    }
    public static function claimBuildRecovery(int $id,array $context): never {self::claimBuild($context);}
}
function isolatedSource(string $path,int $expectedIncludes,bool $entry=false): string {
    $source=\file_get_contents($path);
    $source=preg_replace('/^<\?php\s*declare\(strict_types=1\);\s*/','',$source,1,$headers);
    $source=preg_replace('/^require_once .*;\r?$/m',$entry?'bootstrap();':'',$source,-1,$includes);
    if($headers!==1||$includes!==$expectedIncludes)throw new RuntimeException('Fixture source boundary changed');
    return 'namespace '.__NAMESPACE__.'; use \\Closure; use \\DOMDocument; use \\ReflectionProperty; use \\Throwable;'.$source;
}
\register_shutdown_function(static function():void {
    $GLOBALS['trace']['global_db_initialized']=(new ReflectionProperty(\Database::class,'connection'))->getValue()!==null;
    fwrite(STDERR,"\nM6C_TRACE:".json_encode($GLOBALS['trace'],JSON_THROW_ON_ERROR)."\n");
});
if($case==='timeout')while(true)usleep(10000); // Parent timeout regression; no bootstrap or host operations.
eval(isolatedSource(dirname(__DIR__,2).'/private/classes/SiteBuildRuntime.php',3));
$argv=array_merge(['isolated-worker'],array_slice($argv,2));
eval(isolatedSource(dirname(__DIR__,2).'/scripts/site-build-worker.php',2,true));
