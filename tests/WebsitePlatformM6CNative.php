<?php
declare(strict_types=1);
// Explicit destructive/native entry; intentionally NOT named *Test.php.
if(PHP_SAPI==='cli'&&array_slice($argv,1)===['--help']){
    echo "Use bash tests/RunM6CNative.sh --help. This internal coordinator only accepts --container-run SHA TOKEN [PROFILE] in its disposable container.\n";exit(0);
}
require_once __DIR__.'/support/WebsitePlatformM6CNativeContract.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeReport.php';
$profile=$argv[4]??M6CNativeContract::VERSION;
$report=M6CNativeReport::initial($argv[2]??'',$argv[3]??''); // Failure-only envelope until explicit profile validation below.
$current=null;$reportLocation=null;$admin=null;$config=null;$context=null;
function m6nCheck(bool $ok,string $why): void
{global $report;$report['assertions']++;if(!$ok)throw new RuntimeException($why);$report['completed_assertions']++;}
function m6nCase(string $name,string $lane,callable $body): void
{
    global $report,$current;$current=$name;$start=hrtime(true);$count=$report['assertions'];
    $report['cases'][$name]=['status'=>'RUNNING','lane'=>$lane];
    $body();$report['cases'][$name]=['status'=>'PASS','lane'=>$lane,'assertions'=>$report['assertions']-$count,'seconds'=>(hrtime(true)-$start)/1e9];
    echo 'PASS '.$name.' ('.$lane.")\n";
}
function m6nScalar(string $sql,array $params=[]): mixed
{global $admin;$q=$admin->prepare($sql);$q->execute($params);return $q->fetchColumn();}
function m6nJob(array $f): array
{global $admin;return SiteBuildStore::one($admin,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>$f['job_id']]);}
function m6nAttempt(int $id): array
{global $admin;return SiteBuildStore::one($admin,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>$id]);}
function m6nStart(array $f,string $mode='build',int $uid=2000,array $protectedArgs=['--once']): M6CNativeProcess
{
    global $current;
    $arguments=$mode==='--protected'?['--protected',...$protectedArgs]:[$mode,(string)$f['job_id'],(string)$f['operator']];
    $p=new M6CNativeProcess(['/usr/bin/setpriv','--reuid='.$uid,'--regid='.$uid,'--clear-groups','--no-new-privs',
        '--bounding-set=-all','--inh-caps=-all','--ambient-caps=-all',PHP_BINARY,'-d','memory_limit=128M',
        '-d','display_errors=stderr','-d','zend.exception_ignore_args=1',__DIR__.'/support/WebsitePlatformM6CNativeWorker.php',...$arguments],90);
    M6CNativeDiagnostics::context($p,$current,$mode);return $p;
}
function m6nRead(M6CNativeProcess $p,string $expected): array
{
    global $report,$current;
    return M6CNativeDiagnostics::read($p,$expected,$report,$current,'m6nCheck',static fn($line)=>fwrite(STDERR,$line));
}
function m6nFinish(M6CNativeProcess $p,int $exit=0): array
{
    global $report,$current;
    return M6CNativeDiagnostics::finish($p,$exit,$report,$current,'m6nCheck',static fn($line)=>fwrite(STDERR,$line));
}
function m6nResult(M6CNativeProcess $p): array
{ $result=m6nRead($p,'RESULT');m6nFinish($p);return $result; }
function m6nProtected(array $f,int $uid=2000,array $arguments=['--once'],int $exit=0): array
{
    $p=m6nStart($f,'--protected',$uid,$arguments);$p->diagnosticContext['expected_exit']=$exit;$identity=m6nRead($p,'IDENTITY');
    m6nCheck($identity['uid']===$uid&&$identity['gid']===$uid,'actual_worker_uid');
    foreach($identity['capabilities'] as $key=>$value)m6nCheck($key==='NoNewPrivs'?$value==='1':trim($value,'0')==='','actual_worker_capabilities');
    return m6nFinish($p,$exit);
}
function m6nClaim(array $f,string $mode='build'): array
{
    $p=m6nStart($f,$mode);m6nRead($p,'READY');$p->send('CONTINUE');$claim=m6nRead($p,'CLAIM');
    m6nCheck($claim['job_id']===$f['job_id']&&(int)m6nScalar('SELECT execution_count FROM site_build_jobs WHERE id=?',[$f['job_id']])===1,'committed_claim');
    return [$p,$claim];
}
function m6nSeal(array $f,string $mode='build'): array
{[$p,$claim]=m6nClaim($f,$mode);$p->send('CONTINUE');m6nRead($p,'SEALED');return [$p,$claim];}
function m6nKill(M6CNativeProcess $p): void
{
    global $report,$current;$result=$p->stop(9);
    M6CNativeDiagnostics::record($p,$report,$current,'intentional_stop',null,$result,false,static fn($line)=>fwrite(STDERR,$line));
    m6nCheck($result['termination_requested']&&!$result['running']&&$result['signaled']&&$result['signal']===9,'actual_SIGKILL');
}
function m6nExpire(int $id): void
{
    global $admin,$report,$current;
    // Explicit test metadata fault, NOT elapsed time. Preserve the absolute deadline.
    $before=m6nAttempt($id);
    $admin->prepare('UPDATE site_build_attempts SET leased_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 121 SECOND),lease_expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?')->execute([$id]);
    m6nCheck(m6nAttempt($id)['deadline_at']===$before['deadline_at'],'expiry_fault_preserves_deadline');
    $report['controlled_expiry'][]=['case'=>$current,'attempt'=>$id,'method'=>'leased_at/lease_expires_at metadata; no elapsed-lease claim'];
}
function m6nTree(string $root): array
{
    if(!is_dir($root))return [];$out=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
        m6nCheck(!$file->isLink()&&$file->isFile(),'owned_tree_regular');
        $out[substr($file->getPathname(),strlen($root)+1)]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize(),'mtime'=>$file->getMTime()];
        m6nCheck(count($out)<=1024,'owned_tree_bound');
    }
    ksort($out);return $out;
}
function m6nOutput(array $f): array
{
    global $admin,$config;$job=m6nJob($f);$producer=m6nAttempt((int)$job['current_attempt_id']);
    if($producer['attempt_kind']==='recovery')$producer=m6nAttempt((int)$producer['recovery_of_attempt_id']);
    $adapter=new M6CNativeDependencies($config,$f['operator']);
    return [$adapter->store->verifyRelease($job,$producer),$job,$producer];
}
function m6nReleasePath(array $f): string
{global $config;return $config['artifact_root'].'/'.m6nScalar('SELECT site_key FROM sites WHERE id=?',[$f['site_id']]).'/'.m6nJob($f)['release_key'];}
function m6nVerify(array $f): void
{
    global $admin;[$e,$job,$producer]=m6nOutput($f);m6nCheck($e['disposition']==='sealed','independent_real_store_verification');
    $path=m6nReleasePath($f);$manifest=$e['manifest'];$release=SiteBuildStore::one($admin,'SELECT * FROM site_releases WHERE build_job_id=:id',['id'=>$f['job_id']]);
    m6nCheck($job['status']==='succeeded'&&$release!==null,'persisted_success_release');
    m6nCheck($release['manifest_hash']===CanonicalJson::hash($manifest)&&$release['artifact_hash']===CanonicalJson::hash($manifest['files']),'persisted_hash_layers');
    m6nCheck(M6CNativeInput::matches($job,$release),'input_hash');
    m6nCheck(hash_file('sha256',$path.'/metadata/validation.json')===$manifest['validation_hash'],'validation_byte_hash');
    $marker=json_decode(file_get_contents($path.'/public/_release.json'),true,32,JSON_THROW_ON_ERROR);
    m6nCheck($marker['public_payload_hash']===CanonicalJson::hash(array_values(array_filter($manifest['files'],static fn($x)=>$x['relative_path']!=='public/_release.json'))),'payload_hash_excludes_marker');
    m6nCheck($marker['site_key']===$manifest['site_key']&&$marker['release_key']===$job['release_key'],'release_marker_binding');
    foreach($manifest['files'] as $entry){$p=$path.'/'.$entry['relative_path'];m6nCheck(hash_file('sha256',$p)===$entry['sha256']&&filesize($p)===$entry['byte_size'],'observed_file_hash_size');}
    $inventory=SiteArtifactFiles::inventory($path);m6nCheck(count($inventory)===8,'three_pages_asset_css_marker_metadata');
    foreach($inventory as $file){$bytes=file_get_contents($path.'/'.$file);m6nCheck(!str_contains($bytes,'CANARY')&&!str_contains($bytes,'/uploads/'),'private_public_boundary');}
    foreach([['index.html','Home only','Service only'],['roof-cleaning/index.html','Service only','Contact only'],['contact/index.html','Contact only','Home only']] as [$file,$yes,$no]){
        $html=file_get_contents($path.'/public/'.$file);m6nCheck(str_contains($html,$yes)&&!str_contains($html,$no)&&substr_count($html,'<main ')===1,'distinct_page');
        foreach(['/','/contact','/roof-cleaning'] as $url)m6nCheck(str_contains($html,'href="'.$url.'"'),'navigation');
        m6nCheck(str_contains($html,'Synthetic Café')&&str_contains($html,'Content-Security-Policy'),'public_facts_csp');
    }
    $contact=file_get_contents($path.'/public/contact/index.html');
    m6nCheck(!str_contains($contact,'<form')&&str_contains($contact,'name="email" required disabled'),'inert_form');
    m6nCheck(file_get_contents($path.'/public/assets/'.hash_file('sha256',$f['asset_path']).'.png')===file_get_contents($f['asset_path']),'approved_asset_actual_bytes');
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_release_validations WHERE build_attempt_id IN (SELECT id FROM site_build_attempts WHERE build_job_id=?) AND result=?',[$f['job_id'],'pass'])===2,'two_persisted_validation_results');
    m6nCheck((int)m6nScalar("SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type='site_build_succeeded'",[$f['site_id']])===1,'one_success_audit');
}
function m6nRecover(array $f,string $mode='recover'): array
{
    $p=m6nStart($f,$mode);$r=m6nRead($p,'RECOVERY');$p->send('CONTINUE');
    return [$p,$r];
}
function m6nSetup(array $identity): array
{
    global $admin,$context,$report;
    foreach(['pdo_mysql','dom','mbstring','posix','pcntl','fileinfo','filter'] as $extension)m6nCheck(extension_loaded($extension),'runtime_extension_'.$extension);
    foreach(['fsync','getimagesizefromstring','proc_open','pcntl_exec'] as $function)m6nCheck(function_exists($function),'runtime_function_'.$function);
    m6nCheck(PHP_VERSION_ID>=80400&&PHP_VERSION_ID<80600&&ini_get('memory_limit')==='128M','runtime_php_contract');
    $credentials=json_decode(file_get_contents('/input/credentials.json'),true,16,JSON_THROW_ON_ERROR);
    m6nCheck(M6CNativeContract::hex($credentials['root']??'',64)&&M6CNativeContract::hex($credentials['worker']??'',64)
        &&$credentials['root']!==$credentials['worker'],'fresh_separate_credentials');
    $config=['version'=>1,'environment'=>'staging','worker_id'=>'m6c-native-'.$identity['token'],'worker_uid'=>2000,
        'builder_code_sha'=>$identity['sha'],'work_root'=>'/var/lib/ubo-m6c/work','artifact_root'=>'/var/lib/ubo-m6c/sealed',
        'legacy_upload_root'=>'/var/lib/ubo-m6c/uploads'];
    $report['stage']='storage_setup';
    return M6CNativeSetup::beforeDatabase(function()use($identity,$credentials,$config):array{
        global $admin,$context,$report;
        $report['stage']='configuration_setup';
        $context=$identity;$name='ubo_m6c_'.$identity['token'];
        $env=['DB_HOST'=>$identity['db_ip'],'DB_PORT'=>3306,'DB_NAME'=>$name,'DB_USER'=>'m6c_worker','DB_PASSWORD'=>$credentials['worker'],
            'APP_ENV'=>'staging','APP_DEBUG'=>false,'APP_BASE_URL'=>'https://m6c.invalid','ACCOUNTS_BASE_URL'=>'https://m6c.invalid'];
        foreach(['/opt/ubo/private/config/env.php','/etc/ubo-site-build-worker.json','/run/m6c/context.json'] as $path)
            m6nCheck(!is_link($path)&&!file_exists($path),'no_operational_configuration');
        $bytes="<?php\nreturn ".var_export($env,true).";\n";
        m6nCheck(m6nStep('setup_env_write',fn()=>file_put_contents('/opt/ubo/private/config/env.php',$bytes))===strlen($bytes),'setup_env_write');
        m6nStep('setup_env_chmod',fn()=>chmod('/opt/ubo/private/config/env.php',0440));
        m6nStep('setup_env_chgrp',fn()=>chgrp('/opt/ubo/private/config/env.php',2000));
        foreach(['config'=>['/etc/ubo-site-build-worker.json',$config],'context'=>['/run/m6c/context.json',$context]] as $label=>[$path,$data]){
            $bytes=json_encode($data,JSON_THROW_ON_ERROR);
            m6nCheck(m6nStep('setup_'.$label.'_write',fn()=>file_put_contents($path,$bytes))===strlen($bytes),'setup_'.$label.'_write');
            m6nStep('setup_'.$label.'_chmod',fn()=>chmod($path,0444));
        }
        foreach(['/opt/ubo/private/config/env.php'=>[2000,0440],'/etc/ubo-site-build-worker.json'=>[0,0444],'/run/m6c/context.json'=>[0,0444]] as $path=>[$gid,$mode]){
            clearstatcache(true,$path);$s=m6nStep('setup_configuration_stat',fn()=>lstat($path));
            m6nCheck(($s['mode']&0170000)===0100000&&$s['uid']===0&&$s['gid']===$gid&&($s['mode']&07777)===$mode,'setup_configuration_metadata');
        }
        $report['stage']='database_setup';
        $deadline=hrtime(true)+55000000000;
        do{
            try{$admin=new PDO('mysql:host='.$identity['db_ip'].';port=3306;charset=utf8mb4','root',$credentials['root'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>2]);break;
            }catch(PDOException $error){if(hrtime(true)>=$deadline)throw $error;usleep(100000);}
        }while(true);
        $server=$admin->query('SELECT @@server_uuid AS uuid,@@hostname AS hostname,@@version AS version')->fetch();
        m6nCheck($server['uuid']===$identity['server_uuid']&&$server['hostname']==='m6c-db-'.$identity['token']&&str_starts_with($server['version'],'8.4.'),'exact_disposable_server_before_SQL_mutation');
        m6nCheck(m6nScalar('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$name])===false,'new_owned_schema_only');
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$admin->exec('USE `'.$name.'`');
        $admin->exec("SET time_zone='+00:00'");$admin->exec('SET SESSION innodb_lock_wait_timeout=5');
        require_once __DIR__.'/support/WebsitePlatformM6BMigrations.php';
        m6mysqlMigrate($admin,1,25);
        $admin->exec("CREATE USER 'm6c_worker'@'%' IDENTIFIED BY '".$credentials['worker']."'");
        $admin->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `".$name."`.* TO 'm6c_worker'@'%'");
        $grants=$admin->query("SHOW GRANTS FOR 'm6c_worker'@'%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach($grants as $grant)m6nCheck(!preg_match('/ALL PRIVILEGES|GRANT OPTION|CREATE|ALTER|DROP/',$grant),'worker_has_no_schema_or_admin_privileges');
        $report['server']=$server;$report['database']=$name;$report['runtime']=['php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'setup_uid'=>posix_geteuid(),'worker_uid'=>2000];
        unset($credentials,$env);return $config;
    },'m6nCheck');
}

try{
    $report=M6CNativeReport::initial($argv[2]??'',$argv[3]??'',$profile);
    M6CNativeContract::need(PHP_SAPI==='cli'&&in_array(count($argv),[4,5],true)&&$argv[1]==='--container-run'
        &&M6CNativeContract::hex($argv[2],40)&&M6CNativeContract::hex($argv[3],32),'explicit_container_arguments');
    M6CNativeContract::need(PHP_OS_FAMILY==='Linux'&&function_exists('posix_geteuid')&&posix_geteuid()===0
        &&realpath(dirname(__DIR__))==='/opt/ubo'&&gethostname()==='m6c-runtime-'.$argv[3],'namespace_setup_identity');
    M6CNativeContract::need(is_file('/input/identity.json')&&fileowner('/input/identity.json')===0
        &&filesize('/input/identity.json')<=8192&&is_dir('/run/m6c')&&fileowner('/run/m6c')===0,'launcher_attestation');
    $identity=json_decode(file_get_contents('/input/identity.json'),true,32,JSON_THROW_ON_ERROR);
    M6CNativeContract::need(($identity['version']??'')===M6CNativeContract::VERSION&&($identity['sha']??'')===$argv[2]
        &&($identity['token']??'')===$argv[3]&&($identity['worker_uid']??0)===2000&&M6CNativeContract::privateIp($identity['db_ip']??''),'run_binding');
    M6CNativeContract::need(($identity['profile']??M6CNativeContract::VERSION)===$profile,'profile_binding');
    foreach(['db_id','runtime_id','network_id'] as $key)M6CNativeContract::need(M6CNativeContract::hex($identity[$key]??'',64),'run_resource_id');
    M6CNativeContract::need(preg_match('/^[a-f0-9-]{36}$/D',$identity['server_uuid']??'')===1,'server_uuid');
    $reportLocation=M6CNativeReport::location();
    $report['stage']='source_verification';
    $git=M6CNativeProcess::capture(['/usr/bin/git','-C','/opt/ubo','rev-parse','HEAD'],10);
    m6nCheck($git['exit']===0&&trim($git['stdout'])===$identity['sha'],'exact_source_commit');
    require_once __DIR__.'/support/WebsitePlatformM6CNativeSource.php';
    $index=M6CNativeProcess::capture(['/usr/bin/git','-C','/opt/ubo','ls-files','--stage','-z'],10,1048576);
    m6nCheck($index['exit']===0,'source_index');$files=M6CNativeSource::verify('/opt/ubo',$index['stdout']);
    foreach(array_keys($files) as $file)m6nCheck(fileowner('/opt/ubo/'.$file)===0&&(fileperms('/opt/ubo/'.$file)&0022)===0,'source_root_owner_protected_mode');
    m6nCheck(count(glob('/opt/ubo/database/migrations/*.sql'))===25,'exact_migration_inventory');
    $report['tracked_files_verified']=count($files);unset($index,$files);
    m6nCheck(hash_file('sha256','/opt/ubo/database/migrations/025_site_build_deployment.sql')==='dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0','canonical_025');
    require_once __DIR__.'/support/WebsitePlatformM6CNativeFixture.php';
    require_once __DIR__.'/support/WebsitePlatformM6CNativeInput.php';
    $report['stage']='runtime_setup';
    $config=m6nSetup($identity);
    // The application connection uses the restricted worker account, even for request setup.
    $worker=Database::connection();$worker->exec("SET time_zone='+00:00'");$worker->exec('SET SESSION innodb_lock_wait_timeout=5');
    m6nCheck($worker->query('SELECT @@server_uuid')->fetchColumn()===$identity['server_uuid'],'application_connection_same_disposable_server');
    $report['stage']='native_cases';
    if($profile===M6CSupplementContract::VERSION){
        require __DIR__.'/support/WebsitePlatformM6CSupplementCases.php';
    }else{
    $first=m6nFixture($admin,$config);
    m6nCase('protected-startup-denials','protected',function()use($first,$config):void{
        $before=[m6nTree($config['work_root']),m6nTree($config['artifact_root'])];$file='/etc/ubo-site-build-worker.json';$original=file_get_contents($file);
        foreach(['uid','config-mode','checkout-owner','source-sha'] as $fault){
            try{
                if($fault==='config-mode')m6nStep('denial_config_chmod',fn()=>chmod($file,0666));
                if($fault==='checkout-owner')m6nStep('denial_checkout_chown',fn()=>chown('/opt/ubo/scripts/site-build-worker.php',2000));
                if($fault==='source-sha'){$bad=$config;$bad['builder_code_sha']=str_repeat('0',40);$bytes=json_encode($bad,JSON_THROW_ON_ERROR);
                    m6nCheck(m6nStep('denial_config_write',fn()=>file_put_contents($file,$bytes))===strlen($bytes),'denial_config_write');}
                $r=m6nProtected($first,$fault==='uid'?0:2000,['--once'],1);
                m6nCheck(str_contains($r['stderr'],'Build-only invocation stopped: builder_unavailable.'),'actual_factory_denial_'.$fault);
                m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_build_attempts')===0&&m6nJob($first)['status']==='requested','denial_no_claim');
                m6nCheck([m6nTree($config['work_root']),m6nTree($config['artifact_root'])]===$before,'denial_no_artifact');
            }finally{
                // The checkout owner is restored via CHOWN; config always remains root-owned.
                m6nStep('denial_restore_owner',fn()=>chown('/opt/ubo/scripts/site-build-worker.php',0));
                m6nCheck(m6nStep('denial_restore_write',fn()=>file_put_contents($file,$original))===strlen($original),'denial_restore_write');
                m6nStep('denial_restore_mode',fn()=>chmod($file,0444));
            }
        }
    });
    m6nCase('protected-multipage-build','protected',function()use($first):void{
        $r=m6nProtected($first);m6nCheck(str_contains($r['stdout'],'Build-only outcome: succeeded.'),'unchanged_cli_success');m6nVerify($first);
    });
    m6nCase('protected-replay','protected',function()use($first,$config):void{
        $before=m6nTree('/var/lib/ubo-m6c');$events=(int)m6nScalar('SELECT COUNT(*) FROM site_events');
        $r=m6nProtected($first);m6nCheck(str_contains($r['stdout'],'Build-only outcome: no_work_claimed.'),'successful_queue_replay_no_render');
        (new M6CNativeDependencies($config,$first['operator']))->wire();
        $again=SiteBuildService::requestBuild($first['requester'],['site_id'=>$first['site_id'],'revision_id'=>$first['revision_id'],'expected_snapshot_hash'=>$first['hash'],'build_profile'=>SiteBuildContract::PROFILE]);
        m6nCheck($again['existing']&&$again['id']===$first['job_id'],'exact_request_replay');
        m6nCheck(m6nTree('/var/lib/ubo-m6c')===$before&&(int)m6nScalar('SELECT COUNT(*) FROM site_events')===$events
            &&(int)m6nScalar('SELECT COUNT(*) FROM site_releases')===1&&(int)m6nScalar('SELECT COUNT(*) FROM site_build_attempts')===1,'replay_no_duplicate_effects');
    });
    // Remaining cases are defined below, after the protected startup/build path.
    require __DIR__.'/support/WebsitePlatformM6CNativeCases.php';
    }
    M6CNativeProcess::cleanup();$report['status']='PASS';
    if($profile===M6CSupplementContract::VERSION)M6CSupplementContract::evidence($report,$identity['sha'],$identity['token']);
    else M6CNativeContract::evidence($report,$identity['sha'],$identity['token']);
    $report['stage']='completed';
}catch(Throwable $e){
    M6CNativeReport::failure($report,$e,$current);
}finally{
    M6CNativeReport::finish($report,$reportLocation,[M6CNativeProcess::class,'cleanup'],static fn(string $line)=>fwrite(STDERR,$line));
}
echo 'M6C native '.$report['status'].'; '.$report['assertions']." assertions; protected and instrumented lanes remain distinct.\n";
exit($report['status']==='PASS'?0:1);
