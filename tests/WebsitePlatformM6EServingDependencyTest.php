<?php
declare(strict_types=1);
/** Cold-process include/store controls. No fixture, autoloader or native operation. */
$checks=0;
function dependencyCheck(bool $ok,string $why):void {
    global $checks;$checks++;if(!$ok)throw new RuntimeException($why);
}

if(($argv[1]??'')==='--cold') {
    try {
        dependencyCheck(count($argv)===4,'cold_arguments');
        $entry=match($argv[2]) {
            'serving'=>__DIR__.'/support/WebsitePlatformM6ECommissionServing.php',
            'setup'=>__DIR__.'/support/WebsitePlatformM6ENativeSetup.php',
            default=>throw new RuntimeException('cold_entry'),
        };
        dependencyCheck(!class_exists('SiteBuildRuntime',false),'runtime_initially_absent');
        dependencyCheck(!class_exists('Database',false),'database_initially_absent');
        dependencyCheck(spl_autoload_functions()===[],'no_preloaded_autoloader');
        dependencyCheck(count(get_included_files())===1,'only_probe_preloaded');
        $root=realpath($argv[3]);
        dependencyCheck($root!==false&&dirname($root)===realpath(sys_get_temp_dir())
            &&preg_match('/^m6e-dependency-[a-f0-9]{24}$/D',basename($root))===1,'private_test_root');
        $config=['work_root'=>realpath($root.'/work'),'artifact_root'=>realpath($root.'/sealed')];
        $snapshot=static function()use($root,$config):array {
            $out=[];
            foreach([$root,...array_values($config)] as $dir) {
                clearstatcache(true,$dir);$s=lstat($dir);
                dependencyCheck($s!==false&&($s['mode']&0170000)===0040000,'private_directory');
                $out[$dir]=['dev'=>$s['dev'],'ino'=>$s['ino'],'mode'=>$s['mode'],'uid'=>$s['uid'],'gid'=>$s['gid'],'names'=>scandir($dir)];
            }
            return $out;
        };
        $before=$snapshot();$session=session_status();
        require $entry; // Real entry graph only; never manually require the runtime here.
        dependencyCheck(class_exists('SiteBuildRuntime',false),'runtime_loaded_by_entry');
        dependencyCheck((new ReflectionClass(SiteBuildRuntime::class))->getFileName()===realpath(__DIR__.'/../private/classes/SiteBuildRuntime.php'),'actual_runtime_class');
        dependencyCheck(spl_autoload_functions()===[],'no_added_autoloader');
        dependencyCheck(!class_exists('WebsitePlatformM6CFixture',false)&&!class_exists('M6CNativeDependencies',false),'no_fixture_masking');
        $loaded=array_map('basename',get_included_files());
        dependencyCheck(!in_array('env.php',$loaded,true)&&!in_array('WebsitePlatformM6CFixture.php',$loaded,true)
            &&!in_array('WebsitePlatformM6CNativeFixture.php',$loaded,true),'no_config_or_fixture_include');
        $connection=new ReflectionProperty(Database::class,'connection');$configuration=new ReflectionProperty(Database::class,'config');
        dependencyCheck($connection->getValue()===null&&$configuration->getValue()===null,'include_does_not_open_database');
        dependencyCheck($snapshot()===$before,'include_does_not_mutate_private_files');
        $store=m6eArtifactStore($config); // Same callback-building function used by both native callers.
        dependencyCheck($store instanceof LocalSiteArtifactStore,'actual_store_constructed');
        $guard=(new ReflectionProperty(LocalSiteArtifactStore::class,'guard'))->getValue($store);
        dependencyCheck($guard instanceof Closure,'actual_guard_closure');
        $method=new ReflectionFunction($guard);
        dependencyCheck($method->getName()==='guard'&&$method->getClosureScopeClass()?->getName()===SiteBuildRuntime::class
            &&$method->getFileName()===(new ReflectionClass(SiteBuildRuntime::class))->getFileName(),'original_guard_method');
        $guard();
        dependencyCheck($connection->getValue()===null&&$configuration->getValue()===null,'guard_does_not_open_database');
        dependencyCheck($snapshot()===$before&&session_status()===$session,'store_is_read_only');
        if(class_exists('SiteDeploymentService',false))
            dependencyCheck((new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->getValue()===null,'no_publisher_runtime_factory');
        // An already initialized, transaction-active PDO model proves the real
        // guard is executed by the constructor. Its constructor opens no SQL.
        $db=new class extends PDO {
            public int $calls=0;
            public function __construct(){}
            public function inTransaction():bool {$this->calls++;return true;}
        };
        $connection->setValue(null,$db);$denied=false;
        try {m6eArtifactStore($config);}
        catch(SiteServiceException $e) {$denied=$e->getMessage()==='External artifact work cannot run in a transaction.';}
        finally {$connection->setValue(null,null);}
        dependencyCheck($denied&&$db->calls===1,'real_guard_rejects_transaction');
        dependencyCheck($snapshot()===$before&&$configuration->getValue()===null,'denial_has_no_effects');
        echo json_encode(['entry'=>$argv[2],'pid'=>getmypid(),'checks'=>$checks,'runtime_initially_loaded'=>false,
            'autoloaders'=>count(spl_autoload_functions()),'guard_scope'=>$method->getClosureScopeClass()->getName(),
            'guard_method'=>$method->getName(),'transaction_denials'=>$db->calls,'database_opened'=>false,
            'files_unchanged'=>true,'native_admission'=>'UNEXECUTED'],JSON_THROW_ON_ERROR)."\n";
        exit(0);
    } catch(Throwable $error) {fwrite(STDERR,'dependency_child_failed:'.get_class($error)."\n");exit(1);}
}

require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
$root=sys_get_temp_dir().'/m6e-dependency-'.bin2hex(random_bytes(12));$created=[];$cold=[];
$php=[PHP_BINARY,'-d','auto_prepend_file=','-d','auto_append_file=','-d','display_errors=0','-d','log_errors=0','-d','memory_limit=128M'];
try {
    foreach([$root,$root.'/work',$root.'/sealed'] as $dir) {
        dependencyCheck(mkdir($dir,0700),'private_mkdir');$created[]=$dir;
    }
    foreach(['serving','setup'] as $entry) {
        $result=M6CNativeProcess::capture([...$php,__FILE__,'--cold',$entry,$root],15,16384);
        dependencyCheck($result['exit']===0&&!$result['signaled']&&!$result['termination_requested']&&$result['stderr']==='','cold_child_success');
        $value=json_decode($result['stdout'],true,16,JSON_THROW_ON_ERROR);
        dependencyCheck($value['entry']===$entry&&$value['pid']===$result['pid']&&$value['pid']!==getmypid(),'new_php_process');
        dependencyCheck($value['runtime_initially_loaded']===false&&$value['autoloaders']===0
            &&$value['guard_scope']==='SiteBuildRuntime'&&$value['guard_method']==='guard','cold_callback');
        dependencyCheck($value['transaction_denials']===1&&$value['database_opened']===false&&$value['files_unchanged']===true,'cold_boundary');
        dependencyCheck(is_int($value['checks'])&&$value['checks']>20,'cold_assertions');
        $checks+=$value['checks'];$cold[]=$value;
    }
    $hostDenials=0;
    if(PHP_OS_FAMILY!=='Linux') {
        foreach([['--serving-source'],[]] as $args) {
            $result=M6CNativeProcess::capture([...$php,__DIR__.'/support/WebsitePlatformM6ECommissionChild.php',...$args],5,4096);
            dependencyCheck($result['exit']===1&&!$result['signaled']&&!$result['termination_requested']
                &&$result['stdout']===''&&$result['stderr']==="m6e_commission_child_unresolved\n",'unsupported_host_or_arguments_denied');
            $hostDenials++;
        }
    }
    // A Linux checkout must not accidentally execute the protected branch. Its
    // UID/bootstrap/expiry/machine checks stay unmodified and native-unexecuted.
    echo json_encode(['cold_processes'=>$cold,'unsupported_host_denials'=>$hostDenials,'native_admission'=>'UNEXECUTED'],JSON_THROW_ON_ERROR)."\n";
} finally {
    M6CNativeProcess::cleanup();
    $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
    if($created!==[]) {
        dependencyCheck($resolved!==false&&dirname($resolved)===$parent
            &&preg_match('/^m6e-dependency-[a-f0-9]{24}$/D',basename($resolved))===1,'cleanup_scope');
        foreach(array_reverse($created) as $dir) {
            dependencyCheck(realpath($dir)===$resolved||dirname(realpath($dir))===$resolved,'cleanup_containment');
            dependencyCheck(!is_link($dir)&&scandir($dir)===['.','..'],'cleanup_empty_owned_directory');
            dependencyCheck(rmdir($dir),'cleanup_directory');
        }
    }
}
echo "M6E serving dependency: $checks assertions PASS (two cold PHP processes; real store/guard; native admission unexecuted).\n";
