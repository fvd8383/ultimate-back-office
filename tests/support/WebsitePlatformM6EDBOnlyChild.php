<?php
declare(strict_types=1);
/** Explicitly admitted disposable-instance child. Never discovered as a local SQL test. */
require_once __DIR__.'/WebsitePlatformM6EEligibility.php';
require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';

const M6E_DB_ROOT='/run/ubo-m6e-db-only';
const M6E_DB_SOURCE='/opt/ubo-m6e-db-only';
const M6E_DB_ROLES=['schema'=>0,'build-a'=>2000,'checkpoint'=>2000,'snapshot'=>2001,'build-b'=>2000,
    'source'=>0,'restore-create'=>0,'restore'=>2107,'restore-seal'=>0,'restore-observe'=>0,
    'eligibility-create'=>0,'eligibility'=>2107,'eligibility-seal'=>0,'before'=>0,'after'=>0,
    'identity-grants'=>0,'denied-dml'=>0,'mutate'=>0,'competing-writer'=>2000,'waiter'=>0,'interrupt'=>0];

function m6eDbRead(string $name):array {
    m6eNeed(preg_match('/^[a-z-]+$/D',$name)===1,'db_only_input_name');
    $path=M6E_DB_ROOT.(str_starts_with($name,'credential-')?'/credentials/'.posix_geteuid():'/inputs').'/'.$name.'.json';$s=lstat($path);
    m6eNeed($s!==false&&($s['mode']&0170000)===0100000&&$s['uid']===0&&($s['mode']&0022)===0&&$s['nlink']===1
        &&$s['size']<=4194304&&realpath($path)===$path,'db_only_protected_input');
    return json_decode(file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
}
function m6eDbWrite(string $name,array $value):void {
    m6eNeed(preg_match('/^[a-z-]+$/D',$name)===1,'db_only_output_name');
    $root=M6E_DB_ROOT.'/outputs/'.posix_geteuid();$raw=CanonicalJson::encode($value);
    m6eNeed(strlen($raw)<=4194304,'db_only_output_bound');$path=$root.'/'.$name.'.json';
    $h=fopen($path,'xb');m6eNeed(is_resource($h),'db_only_exclusive_output');
    try {m6eNeed(chmod($path,0600),'db_only_output_mode');$id=fstat($h);
        m6eNeed($id['uid']===posix_geteuid()&&$id['nlink']===1,'db_only_output_identity');
        for($offset=0;$offset<strlen($raw);){$n=fwrite($h,substr($raw,$offset,65536));m6eNeed(is_int($n)&&$n>0,'db_only_output_write');$offset+=$n;}
        m6eNeed(fflush($h)&&fsync($h),'db_only_output_sync');
    }finally{fclose($h);}
}
function m6eDbConfig(array $a):array {
    return ['work_root'=>'/work/work','artifact_root'=>'/work/sealed','legacy_upload_root'=>'/work/uploads',
        'worker_id'=>'m6e-db-only-worker','builder_code_sha'=>$a['source_sha']];
}
function m6eDbConnect(array $a,string $role,bool $strings=true):PDO {
    $users=['admin'=>'m6e_db_admin','builder'=>'m6e_worker','snapshot'=>'m6e_snapshot','source'=>'m6e_observer',
        'writer'=>'m6e_writer','restorer'=>'m6e_lifecycle_worker','observer'=>'m6e_lifecycle_observer','eligibility'=>M6E_ELIGIBILITY_USER];
    m6eNeed(isset($users[$role]),'db_only_connection_role');$secret=m6eDbRead('credential-'.$role);
    m6eNeed($secret['username']===$users[$role]&&is_string($secret['password'])&&strlen($secret['password'])===64,'db_only_credentials');
    $schema=in_array($role,['restorer','observer','eligibility'],true)?'ubo_m6e_lifecycle_'.$a['run_id']:'ubo_m6e_'.$a['run_id'];
    $dsn='mysql:host=127.0.0.1;port=3306;'.($role==='admin'?'':'dbname='.$schema.';').'charset=utf8mb4';
    $db=new PDO($dsn,$secret['username'],$secret['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>$strings,
        PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_SSL_CA=>M6E_DB_ROOT.'/tls/ca.pem',PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true]);
    m6eNeed(str_starts_with((string)m6eSnapshotScalar($db,'SELECT VERSION()'),'8.4.')
        &&m6eSnapshotScalar($db,'SELECT CURRENT_USER()')===$users[$role].'@127.0.0.1'
        &&m6eSnapshotScalar($db,'SELECT CURRENT_ROLE()')==='NONE'
        &&(m6eSnapshotRows($db,"SHOW SESSION STATUS LIKE 'Ssl_cipher'")[0]['Value']??'')!=='','db_only_connection_identity');
    if($role!=='admin')m6eNeed(m6eSnapshotScalar($db,'SELECT DATABASE()')===$schema,'db_only_connection_schema');
    if(isset($a['server_uuid']))m6eNeed(m6eSnapshotScalar($db,'SELECT @@server_uuid')===$a['server_uuid'],'db_only_connection_server');
    if(in_array($role,['builder','source'],true)){
        $grants=array_map(static fn($r)=>array_values($r)[0],m6eSnapshotRows($db,'SHOW GRANTS'));
        $account='`'.$users[$role].'`@`127.0.0.1`';$rights=$role==='builder'?'SELECT, INSERT, UPDATE, DELETE':'SELECT';
        $expected='GRANT '.$rights.' ON `'.str_replace('_','\\_',$schema).'`.* TO '.$account;
        $actual=array_values(array_filter($grants,static fn($g)=>$g!=='GRANT USAGE ON *.* TO '.$account));
        m6eNeed($actual===[$expected]&&count($grants)===count(array_unique($grants)),'db_only_source_grants');
    }
    m6eRestoreSession($db);return $db;
}
function m6eDbWire(PDO $db,array $a,?array $built=null):void {
    // Existing instrumented M6C lane, with an explicitly admitted real PDO. No application config fallback.
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$db);
    $adapter=new M6CNativeDependencies(m6eDbConfig($a),$built['fixture']['operator']??1);$adapter->wire();
}
function m6eDbInputs(array $a):array {
    $v=[];foreach(['checkpoint','snapshot','schema','request','eligibility'] as $name){
        if(is_file(M6E_DB_ROOT.'/inputs/'.$name.'.json'))$v[$name]=m6eDbRead($name);
    }
    if(isset($v['request'])){
        m6eNeed(CanonicalJson::encode($v['request']['destination'])===CanonicalJson::encode(m6eRestoreDestination($a,$v['checkpoint'],$v['snapshot'])),'db_only_destination');
        foreach(['checkpoint','snapshot','schema'] as $name)m6eNeed($v['request'][$name.'_sha256']===CanonicalJson::hash($v[$name]),'db_only_input_digest');
    }
    return $v;
}
function m6eDbArtifact(array $a,array $cp,callable $pulse):array {
    $config=m6eDbConfig($a);
    // Fault cases use an owned COPY. The completed original A+B store is never mutated.
    if(in_array($a['case'],['before-artifact','after-artifact'],true)&&is_file(M6E_DB_ROOT.'/barriers/artifact-fault'))$config['artifact_root']='/work/fault-sealed';
    $store=m6eArtifactStore($config);return m6eReleaseSummary($cp['observed']['release'],$cp['observed']['job'],
        $cp['observed']['producer'],$store->verifyRelease($cp['observed']['job'],$cp['observed']['producer'],$pulse));
}
function m6eDbRejected(callable $action):void {
    $denied=false;try{$action();}catch(Throwable){$denied=true;}m6eNeed($denied,'db_only_expected_rejection');
}
function m6eDbDeniedDml(PDO $db):array {
    $result=[];
    foreach(['INSERT INTO site_deployments(id) SELECT id FROM site_deployments WHERE 1=0',
        'UPDATE site_deployments SET id=id WHERE 1=0','DELETE FROM site_deployments WHERE 1=0'] as $sql){
        $denied=false;try{$db->exec($sql);}catch(PDOException $e){$denied=($e->errorInfo[1]??null)===1142;}
        m6eNeed($denied,'db_only_actual_dml_denial');$result[]=1142;
    }
    return $result;
}
function m6eDbAdmit(array $a,string $mode):void {
    m6eNeed(PHP_SAPI==='cli'&&PHP_OS_FAMILY==='Linux'&&realpath(dirname(__DIR__,2))===M6E_DB_SOURCE
        &&isset(M6E_DB_ROLES[$mode])&&posix_geteuid()===M6E_DB_ROLES[$mode]&&posix_getegid()===posix_geteuid(),'db_only_process');
    m6eNeed($a['profile']==='m6e-db-only-v1'&&preg_match('/^[a-f0-9]{32}$/D',$a['run_id'])===1
        &&preg_match('/^[a-f0-9]{40}$/D',$a['source_sha'])===1&&time()<$a['expires_at']
        &&$a['machine_id']===trim(file_get_contents('/etc/machine-id'))
        &&$a['boot_id']===trim(file_get_contents('/proc/sys/kernel/random/boot_id'))
        &&$a['owner_pid']===posix_getppid(),'db_only_binding');
    foreach(['user','mnt','pid','net'] as $ns)m6eNeed(readlink('/proc/self/ns/'.$ns)===$a['namespaces'][$ns],'db_only_namespace');
    $status=file_get_contents('/proc/self/status');m6eNeed(preg_match('/^NoNewPrivs:\s+1$/m',$status)===1,'db_only_no_new_privs');
    if(posix_geteuid()!==0)foreach(['CapInh','CapPrm','CapEff','CapAmb'] as $cap)
        m6eNeed(preg_match('/^'.$cap.':\s+0+$/m',$status)===1,'db_only_worker_capabilities');
    M6CNativeGitSource::verify(dirname(__DIR__,2),$a['source_sha'],mode:'m6e-db-only');
}
final class M6EDBOnlySnapshotFailure extends RuntimeException {
    public function __construct(public array $record){parent::__construct('db_only_snapshot_failed');}
}
/** Only the shared algorithm's fixed diagnostic vocabulary may cross the private child boundary. */
function m6eDbSnapshotOutcome(array $v):array {
    $keys=array_keys($v);sort($keys);
    m6eNeed($keys===['capture','first_failure','secondary','transaction_cleanup','verification']
        &&in_array($v['capture'],['FAILED','CAPTURED'],true)&&$v['verification']==='UNREACHED'
        &&in_array($v['transaction_cleanup'],['UNREACHED','RELEASED','UNRESOLVED'],true)
        &&in_array($v['first_failure'],[null,'snapshot_admission','snapshot_before','snapshot_lock','snapshot_schema',
            'snapshot_transaction','snapshot_rows','snapshot_commit','snapshot_unlock','snapshot_after'],true)
        &&is_array($v['secondary'])&&array_is_list($v['secondary'])&&count($v['secondary'])<=2
        &&array_diff($v['secondary'],['snapshot_rollback','snapshot_unlock'])===[],'db_only_snapshot_outcome_shape');
    return $v;
}
/** Actual wrapper, with explicit local-only connection/output/process seams; shared capture is unchanged. */
function m6eDbSnapshot(array $a,array $inputs,array $built,array $invocation,?callable $connect=null,?callable $write=null,?array $process=null):array {
    $connect??=static fn(string $role,bool $strings=true)=>m6eDbConnect($a,$role,$strings);
    $write??=Closure::fromCallable('m6eDbWrite');
    $process??=['pid'=>getmypid(),'uid'=>posix_geteuid(),'gid'=>posix_getegid(),'owner_pid'=>posix_getppid()];
    $binding=array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id']));
    m6eNeed($invocation['profile']==='m6e-db-only-snapshot-invocation-v1'&&$invocation['mode']==='snapshot'
        &&CanonicalJson::encode($invocation['binding'])===CanonicalJson::encode($binding)&&$invocation['case']===$a['case']
        &&preg_match('/^[a-f0-9]{32}$/D',$invocation['invocation_id'])===1&&$invocation['owner_pid']===$process['owner_pid']
        &&$invocation['namespaces']===$a['namespaces']&&$invocation['started_at']<=time()
        &&$invocation['expires_at']===$a['expires_at']&&time()<$a['expires_at'],'db_only_snapshot_invocation');
    $record=['profile'=>'m6e-db-only-snapshot-outcome-v1','invocation'=>$invocation,'process'=>$process,'attempt'=>'UNREACHED',
        'outcome'=>null,'first_failure'=>null,'secondary'=>[],'admission'=>'UNIMPLEMENTED','native_admission'=>'BLOCKED','deployment_authority'=>'NONE'];
    $db=null;$source=null;$outcome=[];$primary=false;$stage='connection';
    try {
        $db=$connect('snapshot');$source=$connect('builder',false);$cp=$inputs['checkpoint'];
        $stage='session_publication';$write('snapshot-session',['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()')]);
        $stage='capture';$record['attempt']='THREW';
        $v=m6eCaptureSnapshot($db,$a,$inputs['schema'],$cp,static fn()=>m6eCheckpointObserve($source,$built['first'],$cp['observed']['database']),
            (int)m6eSnapshotScalar($source,'SELECT CONNECTION_ID()'),$outcome);
        $record['attempt']='RETURNED';$stage='data_publication';$write('snapshot',$v);
    }catch(Throwable $error){$primary=true;$record['first_failure']='db_only_snapshot_'.$stage;
        // Exception trace arguments can retain PDO sessions. Keep only the sanitized outcome, never the original throwable.
        unset($error);}
    finally {
        // Closing references is bounded by the existing owned child deadline. It is NOT a SQL cleanup acknowledgement.
        foreach(['db','source'] as $name)try{$$name=null;}catch(Throwable $error){
            $code='db_only_snapshot_'.$name.'_close';$record['secondary'][]=$code;
            if(!$primary){$primary=true;$record['first_failure']=$code;}unset($error);
        }
        try{$record['outcome']=$outcome===[]?null:m6eDbSnapshotOutcome($outcome);
            if(($record['outcome']['first_failure']??null)!==null)$record['first_failure']=$record['outcome']['first_failure'];
        }catch(Throwable $error){$record['secondary'][]='db_only_snapshot_outcome_invalid';
            if(!$primary){$primary=true;$record['first_failure']='db_only_snapshot_outcome_invalid';}unset($error);}
        try{m6eNeed(strlen(CanonicalJson::encode($record))<=8192,'db_only_snapshot_record_bound');$write('snapshot-outcome',$record);}
        catch(Throwable $error){$record['secondary'][]='db_only_snapshot_outcome_publication';
            if(!$primary){$primary=true;$record['first_failure']='db_only_snapshot_outcome_publication';}unset($error);}
    }
    if($primary)throw new M6EDBOnlySnapshotFailure($record);
    return $outcome;
}
function m6eDbDiagnostic(Throwable $error):array {
    if($error instanceof M6EDBOnlySnapshotFailure)return ['phase'=>'snapshot','first_failure'=>$error->record['first_failure'],
        'secondary'=>$error->record['secondary'],'snapshot'=>$error->record];
    $diagnostic=$error instanceof M6EEligibilityFailure?$error->diagnostic:($error instanceof M6ERestorationFailure?$error->diagnostic:[]);
    return ['phase'=>$diagnostic['phase']??'child','first_failure'=>$diagnostic['first_failure']??'db_only_child','secondary'=>$diagnostic['secondary']??[]];
}
function m6eDbRun(string $mode,array $a):array {
    $deadline=hrtime(true)+($mode==='restore'?230:($mode==='snapshot'?45:60))*1000000000;
    $pulse=static function()use($a,$deadline):void{m6eNeed(time()<$a['expires_at']&&hrtime(true)<$deadline,'db_only_deadline');};$pulse();
    if($mode==='schema'){
        $db=m6eDbConnect($a,'admin');$secrets=[];
        $identity=m6eSchemaData($db,$a,static function():void{},
            static function(PDO $db):void{ob_start();try{m6mysqlMigrate($db,1,25);}finally{ob_end_clean();}},
            static function(string $role,array $value)use(&$secrets):void{
                $secrets[match($role){'worker'=>'builder','observer'=>'source',default=>$role}]=$value;
            });
        // Exact literal source-schema grants, including observers. No wildcard interpretation is retained.
        foreach(['writer'=>'ALL PRIVILEGES','worker'=>'SELECT, INSERT, UPDATE, DELETE','observer'=>'SELECT'] as $role=>$rights){
            $account="'m6e_".$role."'@'127.0.0.1'";
            $db->exec('REVOKE '.$rights.' ON `'.$identity['schema'].'`.* FROM '.$account);
            $db->exec('GRANT '.$rights.' ON `'.str_replace('_','\\_',$identity['schema']).'`.* TO '.$account);
        }
        m6eDbWrite('credentials',$secrets);
        $schema=['profile'=>'m6e-pre-b-schema-v1','binding'=>array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id'])),
            'schema'=>$identity['schema'],'server_uuid'=>$identity['server_uuid'],'migrations'=>m6eSnapshotMigrations(),
            'tables'=>m6eSnapshotSchema($db,$identity['schema'],$pulse)];m6eDbWrite('schema',$schema);
        return ['server_uuid'=>$identity['server_uuid'],'schema'=>$identity['schema'],'account'=>'m6e_db_admin@127.0.0.1'];
    }
    if($mode==='build-a'){
        $db=m6eDbConnect($a,'builder',false);m6eDbWire($db,$a);$built=m6eBuildA($db,m6eDbConfig($a));
        // A deterministic unrelated row makes complete-row preservation/drift observable.
        $business=(int)$db->query('SELECT business_id FROM site_business_associations WHERE site_id='.(int)$built['fixture']['site_id'])->fetchColumn();
        m6nInsert($db,'notes',['business_id'=>$business,'note_body'=>'M6E DB-only baseline']);
        m6eDbWrite('built',$built);return ['release_id'=>$built['first']];
    }
    $inputs=m6eDbInputs($a);$built=m6eDbRead('built');$config=m6eDbConfig($a);
    if($mode==='checkpoint'){
        $db=m6eDbConnect($a,'builder',false);m6eDbWire($db,$a,$built);
        $cp=m6eCaptureCheckpoint($db,m6eArtifactStore($config),$config['artifact_root'],2000,$built['first'],
            ['schema'=>'ubo_m6e_'.$a['run_id'],'server_uuid'=>$a['server_uuid'],'account'=>'m6e_worker@127.0.0.1'],$a,'native-pre-b');
        m6eDbWrite('checkpoint',$cp);return ['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()')];
    }
    if($mode==='snapshot'){
        return m6eDbSnapshot($a,$inputs,$built,m6eDbRead('snapshot-invocation'));
    }
    if($mode==='build-b'){
        $db=m6eDbConnect($a,'builder',false);m6eDbWire($db,$a,$built);m6eSnapshotFinalBoundary($db,$built['first'],$inputs['checkpoint']);
        $second=m6eBuildB($db,$built);$releases=[m6eObserveRelease($db,$config,$built['first']),m6eObserveRelease($db,$config,$second)];
        m6eNeed(CanonicalJson::encode($releases[0])===CanonicalJson::encode($inputs['checkpoint']['summary']),'db_only_original_A');m6eFinalAIneligible($db,$built['first']);
        foreach(['site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks'] as $table)
            m6eNeed((int)m6eSnapshotScalar($db,'SELECT COUNT(*) FROM '.$table)===0,'db_only_no_deployments');
        $v=['releases'=>$releases,'newer_material_B_rejected'=>true]+m6eAssetSets($releases);m6eDbWrite('completed',$v);return $v;
    }
    if($mode==='source'){
        $db=m6eDbConnect($a,'source');$read=m6eRestoreRead($db,'ubo_m6e_'.$a['run_id'],$pulse);$completed=m6eDbRead('completed');
        foreach($completed['releases'] as $r)m6eNeed(CanonicalJson::encode(m6eObserveRelease($db,$config,$r['release_id']))===CanonicalJson::encode($r),'db_only_completed_artifacts');
        return ['sha256'=>CanonicalJson::hash($read),'releases'=>$completed['releases']];
    }
    if($mode==='restore-create')return m6eRestoreCreate(m6eDbConnect($a,'admin'),$a,$inputs,
        static fn(string $leaf,array $v)=>m6eDbWrite(str_starts_with($leaf,'worker')?'credential-restorer':'credential-observer',$v));
    if($mode==='restore'){
        $db=m6eDbConnect($a,'restorer');$outcome=[];
        try{m6eRestoreData($db,$a,$inputs['schema'],$inputs['checkpoint'],$inputs['snapshot'],
            static function(PDO $db):void{ob_start();try{m6mysqlMigrate($db,1,25);}finally{ob_end_clean();}},$pulse,$outcome);}
        finally{$db=null;m6eDbWrite('restoration-outcome',$outcome);}
        return $outcome;
    }
    if($mode==='restore-seal')return m6eRestoreSeal(m6eDbConnect($a,'admin'),$inputs);
    if($mode==='eligibility-create'||$mode==='eligibility-seal'){
        m6eEligibilityIdle(); // Actual PID namespace inventory, in addition to the supervisor's owned-child reap.
        return m6eEligibilityAccount(m6eDbConnect($a,'admin'),$inputs,
            $mode==='eligibility-create',static fn(array $v)=>m6eDbWrite('credential-eligibility',$v));
    }
    if(in_array($mode,['restore-observe','before','after'],true)){
        $db=m6eDbConnect($a,'observer');$r=$inputs['request'];$cp=$inputs['checkpoint'];
        $identity=static fn()=>m6eRestoreAdmit($db,['schema'=>$r['destination']['schema'],'server_uuid'=>$a['server_uuid'],'account'=>'m6e_lifecycle_observer@127.0.0.1'],false);
        if($mode==='restore-observe'){
            $v=['profile'=>'m6e-pre-b-restoration-observation-v1','binding'=>$r['binding'],'request_sha256'=>CanonicalJson::hash($r),
                'database'=>$identity(),'read'=>m6eRestoreRead($db,$r['destination']['schema'],$pulse),'artifact'=>m6eDbArtifact($a,$cp,$pulse),
                'deployment_authority'=>'NONE','admission'=>'UNIMPLEMENTED'];m6eDbWrite('restoration-observation',$v);return ['result'=>'OBSERVED'];
        }
        return m6eEligibilityObserve($inputs,$mode,['pid'=>getmypid(),'uid'=>posix_geteuid(),'gid'=>posix_getegid(),'owner_pid'=>posix_getppid()],
            $identity,static fn()=>m6eRestoreRead($db,$r['destination']['schema'],$pulse),static fn()=>m6eDbArtifact($a,$cp,$pulse),
            static function(array $v)use($mode,$a):void{
                if($a['case']==='capture-cleanup-failure'&&$mode==='after')throw new RuntimeException('db_only_injected_capture_failure');
                m6eDbWrite($mode,$v);
            });
    }
    if($mode==='eligibility'){
        $db=m6eDbConnect($a,'eligibility',false);$outcome=[];$calls=0;
        $checked=static function()use($pulse,$a,&$calls,$db):void{
            $pulse();if($a['case']==='transaction-interrupt'&&++$calls===2){
                m6eDbWrite('gate-session',['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()'),'pid'=>getmypid()]);
                while(!is_file(M6E_DB_ROOT.'/barriers/gate-release')){$pulse();usleep(20000);}
            }
        };
        try{m6eEligibilityTransaction($db,$a,$inputs['checkpoint'],$inputs['eligibility'],$checked,$outcome);}
        finally{$db=null;$outcome+=['request_sha256'=>CanonicalJson::hash($inputs['eligibility']),'invocation_id'=>$inputs['eligibility']['invocation_id'],
            'process'=>['pid'=>getmypid(),'uid'=>posix_geteuid(),'gid'=>posix_getegid(),'owner_pid'=>posix_getppid()],
            'admission'=>'UNIMPLEMENTED','native_admission'=>'BLOCKED','deployment_authority'=>'NONE'];m6eDbWrite('gate-outcome',$outcome);}
        return ['result'=>'AWAITING_INDEPENDENT_OBSERVER'];
    }
    require_once __DIR__.'/WebsitePlatformM6EDBOnlyFaults.php';
    return m6eDbFault($mode,$a,$inputs,$pulse);
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try {
        ini_set('display_errors','0');ini_set('log_errors','0');umask(0077);
        m6eNeed(count($argv)===2,'db_only_arguments');$a=m6eDbRead('admission');$mode=$argv[1];m6eDbAdmit($a,$mode);
        $result=m6eDbRun($mode,$a);echo CanonicalJson::encode(['result'=>'COMPLETED','value'=>$result])."\n";
    }catch(Throwable $error){
        echo CanonicalJson::encode(['result'=>'INCOMPLETE','diagnostic'=>m6eDbDiagnostic($error)])."\n";exit(1);
    }
}
