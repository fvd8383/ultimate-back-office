<?php
declare(strict_types=1);
/** Fixture-only pre-admission. No runtime factory, deployment request, target or publisher entry. */
require_once __DIR__.'/WebsitePlatformM6ERestoration.php';

const M6E_ELIGIBILITY_USER='m6e_lifecycle_eligibility';
const M6E_ELIGIBILITY_SOURCES=['tests/support/WebsitePlatformM6EEligibility.php','tests/support/WebsitePlatformM6EEligibility.py',
    'private/classes/SiteRevisionManager.php','private/classes/SiteApprovalManager.php','private/classes/SiteManager.php',
    'private/classes/SiteServiceSupport.php','private/classes/SiteCompositionValidator.php','private/classes/SiteRevisionSnapshotHasher.php',
    'private/classes/ComponentRegistry.php','private/classes/ThemeRegistry.php','private/classes/SiteDeploymentService.php',
    'private/classes/SiteDeploymentContract.php','private/classes/SiteDeploymentRuntime.php'];
const M6E_ELIGIBILITY_TABLES=['business_modules','businesses','component_definitions','component_variants','modules',
    'site_approvals','site_assets','site_build_attempts','site_build_jobs','site_business_associations',
    'site_deployment_approvals','site_deployment_attempts','site_deployment_health_checks','site_deployment_targets','site_deployments',
    'site_generation_briefs','site_page_sections','site_pages','site_releases','site_revision_assets','site_revision_pages',
    'site_revisions','site_themes','sites'];
final class M6EEligibilityFailure extends RuntimeException {
    public function __construct(public readonly array $diagnostic){parent::__construct('eligibility_incomplete');}
}
function m6eEligibilityGrants(array $grants,string $schema):void {
    $account='`'.M6E_ELIGIBILITY_USER.'`@`127.0.0.1`';
    $expected=['GRANT LOCK TABLES ON `'.str_replace('_','\\_',$schema).'`.* TO '.$account];
    foreach(M6E_ELIGIBILITY_TABLES as $table)$expected[]='GRANT SELECT ON `'.$schema.'`.`'.$table.'` TO '.$account;
    m6eNeed(count($grants)===count(array_unique($grants)),'eligibility_duplicate_grant');
    $actual=array_values(array_filter($grants,static fn($g)=>$g!=='GRANT USAGE ON *.* TO '.$account));
    sort($actual);sort($expected);m6eNeed($actual===$expected,'eligibility_grants');
}
function m6eEligibilityAdmit(PDO $db,array $expected):array {
    m6eNeed(!$db->inTransaction()&&m6eCheckpointDatabase($db)===$expected,'eligibility_database');
    m6eNeed(str_starts_with((string)m6eSnapshotScalar($db,'SELECT VERSION()'),'8.4.')
        &&m6eSnapshotScalar($db,'SELECT CURRENT_ROLE()')==='NONE','eligibility_server_role');
    m6eNeed((m6eSnapshotRows($db,"SHOW SESSION STATUS LIKE 'Ssl_cipher'")[0]['Value']??'')!=='','eligibility_tls');
    $grants=array_map(static fn($r)=>array_values($r)[0],m6eSnapshotRows($db,'SHOW GRANTS'));
    m6eEligibilityGrants($grants,$expected['schema']);
    return $expected+['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()'),'grants_sha256'=>CanonicalJson::hash($grants)];
}
/** The unchanged service owns the eligibility predicate and all its locking reads. */
function m6eEligibilityTransaction(PDO $db,array $a,array $cp,array $request,callable $pulse,array &$outcome):array {
    $outcome=['result'=>'INCOMPLETE','phase'=>'identity','first_failure'=>null,'secondary'=>[],
        'transaction'=>'UNREACHED','closed'=>false,'gate_returned'=>false];$primary=null;$owned=false;
    try {
        $pulse();$d=m6eLifecycleBinding($a['run_id']);
        m6eNeed(CanonicalJson::encode($cp['binding'])===CanonicalJson::encode($request['binding'])
            &&CanonicalJson::encode($request['binding'])===CanonicalJson::encode(array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id'])))
            &&$request['schema']===$d['schema']&&$request['server_uuid']===$cp['observed']['database']['server_uuid']
            &&$request['checkpoint_sha256']===CanonicalJson::hash($cp),'eligibility_binding');
        $expected=['schema'=>$d['schema'],'server_uuid'=>$request['server_uuid'],'account'=>M6E_ELIGIBILITY_USER.'@127.0.0.1'];
        $identity=m6eEligibilityAdmit($db,$expected);$outcome['identity']=$identity;
        $outcome['phase']='session';m6eRestoreSession($db);
        $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET SESSION TRANSACTION READ WRITE');
        m6eNeed(m6eSnapshotScalar($db,'SELECT @@session.transaction_isolation')==='REPEATABLE-READ'
            &&(string)m6eSnapshotScalar($db,'SELECT @@session.transaction_read_only')==='0'
            &&(string)m6eSnapshotScalar($db,'SELECT @@session.autocommit')==='1','eligibility_transaction_mode');
        $outcome['phase']='begin';$outcome['transaction']='UNKNOWN';$owned=true;
        m6eNeed($db->beginTransaction()&&$db->inTransaction(),'eligibility_begin');$outcome['transaction']='OPEN';
        $outcome['phase']='gate';
        $release=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id=:id FOR UPDATE',['id'=>(int)$cp['summary']['release_id']]);
        m6eNeed($release!==null,'eligibility_release');
        // false is the ordinary publish source policy, NOT the historical-restore bypass.
        $source=SiteRevisionManager::lockDeploymentEligibility($db,$release,false);$outcome['gate_returned']=true;$pulse();
        $outcome['phase']='original';
        $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id FOR UPDATE',['id'=>(int)$release['build_job_id']]);
        m6eNeed($job!==null&&$job['status']==='succeeded'&&$job['completed_at']!==null
            &&$job['builder_code_sha']===$a['source_sha']&&(int)$job['business_id']===$source['business_id']
            &&(int)$job['association_id']===$source['association_id'],'eligibility_job');
        $producer=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id FOR UPDATE',['id'=>(int)$job['current_attempt_id']]);
        m6eNeed($producer!==null&&$producer['status']==='succeeded'&&$producer['completed_at']!==null
            &&$producer['attempt_kind']==='execution','eligibility_producer');
        $newer=m6eSnapshotRows($db,'/* site-m6:build-successors */ SELECT id, materiality, lifecycle_status FROM site_revisions
            WHERE site_id=:site_id AND revision_number>:revision_number ORDER BY revision_number FOR UPDATE',
            ['site_id'=>(int)$release['site_id'],'revision_number'=>(int)$source['revision']['revision_number']]);
        m6eNeed($newer===[]&&$source['site']['current_published_revision_id']===null,'eligibility_pre_b');
        $absence=[];
        foreach(['site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks'] as $table){
            $absence[$table]=(int)m6eSnapshotScalar($db,'SELECT COUNT(*) FROM '.$table);
            m6eNeed($absence[$table]===0,'eligibility_no_effects');
        }
        // Zero targets also satisfies the unchanged unique (site_id, environment) constraint.
        $all=m6eSnapshotRows($db,'/* site-m6:build-approvals */ SELECT * FROM site_approvals WHERE site_id=:site_id ORDER BY id FOR UPDATE',
            ['site_id'=>(int)$release['site_id']]);$approvals=[];
        foreach($source['approvals'] as $kind=>$id){$rows=array_values(array_filter($all,static fn($v)=>(int)$v['id']===(int)$id));
            m6eNeed(count($rows)===1,'eligibility_approval');$approvals[$kind]=$rows[0];}
        $observed=['database'=>$expected,'source'=>$source,'approvals'=>$approvals,'release'=>$release,'job'=>$job,'producer'=>$producer,
            'absence'=>$absence,'newer_revisions'=>[]];$original=$cp['observed'];$original['database']=$expected;
        m6eNeed(CanonicalJson::encode($observed)===CanonicalJson::encode($original),'eligibility_original_identity');
        $pulse();m6eNeed($db->inTransaction(),'eligibility_transaction_lost');
        $outcome['phase']='rollback';$outcome['transaction']='UNKNOWN';
        m6eNeed($db->rollBack()&&!$db->inTransaction(),'eligibility_rollback');
        $outcome['transaction']='ROLLED_BACK';$outcome['closed']=true;
        m6eNeed(m6eCheckpointDatabase($db)===$expected,'eligibility_final_identity');$pulse();
        $outcome['observed_sha256']=CanonicalJson::hash($observed);$outcome['absence']=$absence;
        $outcome['phase']='complete';$outcome['result']='AWAITING_INDEPENDENT_OBSERVER';return $outcome;
    } catch(Throwable $error){$primary=$error;$outcome['first_failure']='eligibility_'.$outcome['phase'];}
    finally {
        if($owned&&$outcome['transaction']!=='ROLLED_BACK')try{
            if($db->inTransaction()){
                m6eNeed($db->rollBack()&&!$db->inTransaction(),'eligibility_cleanup_rollback');
                if($outcome['transaction']==='OPEN'){$outcome['transaction']='ROLLED_BACK';$outcome['closed']=true;}
            } elseif($outcome['transaction']==='OPEN')$outcome['transaction']='UNKNOWN';
        } catch(Throwable){$outcome['secondary'][]='eligibility_cleanup_rollback';}
        if($primary!==null)$outcome['result']='INCOMPLETE';
    }
    throw new M6EEligibilityFailure($outcome);
}
function m6eEligibilityInputs(array $a):array {
    $inputs=m6eRestoreInputs($a);$root=$inputs['request']['destination']['control_root'].'/eligibility';
    $request=json_decode(SitePublisherHostContract::protectedFile($root.'/request.json',65536),true,64,JSON_THROW_ON_ERROR);
    $proof=SitePublisherHostContract::protectedFile($root.'/restoration-verification.json',1048576);
    m6eNeed($request['profile']==='m6e-eligibility-preflight-v1'&&$request['schema']===$inputs['request']['destination']['schema']
        &&$request['server_uuid']===$inputs['request']['server_uuid']&&$request['expires_at']===$a['expires_at']
        &&CanonicalJson::encode($request['binding'])===CanonicalJson::encode($inputs['checkpoint']['binding'])
        &&preg_match('/^[a-f0-9]{32}$/D',$request['invocation_id'])===1
        &&$request['started_at']<=time()&&time()<$request['expires_at']
        &&$request['admission']==='UNIMPLEMENTED'&&$request['native_admission']==='BLOCKED'
        &&$request['deployment_authority']==='NONE','eligibility_request');
    foreach(['checkpoint','snapshot','schema'] as $name)m6eNeed($request[$name.'_sha256']===$inputs['request'][$name.'_sha256'],'eligibility_input_digest');
    m6eNeed($request['restoration_sha256']===hash('sha256',$proof),'eligibility_restoration_digest');
    $restored=json_decode($proof,true,64,JSON_THROW_ON_ERROR);
    m6eNeed($restored['observation_sha256']===$request['restoration_observation_sha256']
        &&CanonicalJson::encode($restored['binding'])===CanonicalJson::encode($request['binding'])
        &&$restored['result']==='RESTORED AND INDEPENDENTLY VERIFIED'
        &&$restored['admission']==='UNIMPLEMENTED'&&$restored['deployment_authority']==='NONE','eligibility_restoration');
    $code=[];foreach(M6E_ELIGIBILITY_SOURCES as $path)$code[$path]=hash_file('sha256',dirname(__DIR__,2).'/'.$path);
    m6eNeed(CanonicalJson::encode($request['source_files'])===CanonicalJson::encode($code),'eligibility_source');
    return $inputs+['eligibility'=>$request];
}
function m6eEligibilityWrite(array $a,string $leaf,array $value,array &$secondary=[]):void {
    m6eNeed(in_array($leaf,['credentials.json','worker/outcome.json','before.json','after.json'],true),'eligibility_output');
    $d=m6eLifecycleBinding($a['run_id']);$worker=$leaf==='worker/outcome.json';
    $path=$d[in_array($leaf,['before.json','after.json'],true)?'storage_root':'control_root'].'/eligibility/'.$leaf;
    $uid=$worker?M6E_RESTORE_UID:0;m6eNeed(posix_geteuid()===$uid,'eligibility_output_uid');
    foreach([dirname($path),dirname(dirname($path))] as $parent){$s=lstat($parent);
        m6eNeed($s!==false&&($s['mode']&0170000)===0040000&&($s['mode']&0022)===0
            &&$s['uid']===($worker&&$parent===dirname($path)?M6E_RESTORE_UID:0),'eligibility_output_parent');}
    $raw=CanonicalJson::encode($value);m6eNeed(strlen($raw)<=4194304,'eligibility_output_bound');
    $h=fopen($path,'xb');m6eNeed(is_resource($h),'eligibility_output_exclusive');
    $primary=null;try {
        $id=fstat($h);m6eNeed($id['uid']===$uid&&$id['nlink']===1&&chmod($path,0600),'eligibility_output_identity');
        if($leaf==='credentials.json')m6eNeed(chgrp($path,M6E_RESTORE_UID)&&chmod($path,0640),'eligibility_credentials_mode');
        for($off=0;$off<strlen($raw);){$n=fwrite($h,substr($raw,$off,65536));m6eNeed(is_int($n)&&$n>0,'eligibility_output_write');$off+=$n;}
        m6eNeed(fflush($h)&&fsync($h),'eligibility_output_sync');clearstatcache(true,$path);$named=lstat($path);
        m6eNeed($named['dev']===$id['dev']&&$named['ino']===$id['ino']&&$named['nlink']===1&&file_get_contents($path)===$raw,'eligibility_output_readback');
    } catch(Throwable $error){$primary=$error;throw $error;}
    finally {try{m6eNeed(fclose($h),'eligibility_output_close');}
        catch(Throwable $error){if($primary===null)throw $error;$secondary[]='eligibility_output_close';}}
    $dir=fopen(dirname($path),'r');m6eNeed(is_resource($dir),'eligibility_directory');
    try{m6eNeed(fsync($dir),'eligibility_directory_sync');}
    catch(Throwable $error){$primary=$error;throw $error;}
    finally{try{m6eNeed(fclose($dir),'eligibility_directory_close');}
        catch(Throwable $error){if($primary===null)throw $error;$secondary[]='eligibility_directory_close';}}
}
function m6eEligibilityIdle():void {
    $paths=glob('/proc/[0-9]*',GLOB_ONLYDIR);m6eNeed(is_array($paths)&&$paths!==[],'eligibility_process_inventory');
    foreach($paths as $path){$s=@stat($path);
        m6eNeed($s!==false,'eligibility_process_identity_unknown');
        m6eNeed($s['uid']!==M6E_RESTORE_UID,'eligibility_restorer_process_active');}
}
function m6eEligibilityAccounts(PDO $admin,array $inputs):array {
    $d=$inputs['request']['destination'];$result=[];
    foreach(['worker','observer'] as $role){$user=$d[$role];
        $rows=m6eSnapshotRows($admin,"SELECT account_locked, ssl_type FROM mysql.user WHERE User=? AND Host='127.0.0.1'",[$user]);
        m6eNeed($rows===[['account_locked'=>$role==='worker'?'Y':'N','ssl_type'=>'ANY']],'eligibility_restoration_account');
        $grants=array_map(static fn($r)=>array_values($r)[0],m6eSnapshotRows($admin,'SHOW GRANTS FOR '.$admin->quote($user)."@'127.0.0.1'"));
        m6eRestoreGrants($grants,$d['schema'],$user,$role==='worker');
        $result[$role]=['account'=>$user.'@127.0.0.1','grants_sha256'=>CanonicalJson::hash($grants),'locked'=>$role==='worker'];
    }
    m6eNeed((int)m6eSnapshotScalar($admin,"SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='m6e_lifecycle_worker'")===0,'eligibility_restoration_session');
    return $result;
}
/** Only digests/counts and fixed identities leave the private observation. No rows or exception text. */
function m6eEligibilityObservationDiagnostic(array $inputs,array $v):array {
    $r=$inputs['eligibility'];$read=$v['observation']['read'];$artifact=$v['observation']['artifact'];$tables=[];$affected=[];
    foreach(m6eSnapshotProfile()['tables'] as $name){
        $expected=$inputs['snapshot']['tables'][$name];$observed=$read['tables'][$name]??null;
        $row=['schema_expected'=>CanonicalJson::hash($inputs['schema']['tables'][$name]),
            'schema_observed'=>isset($read['definitions'][$name])?CanonicalJson::hash($read['definitions'][$name]):null,
            'data_expected'=>CanonicalJson::hash($expected),'data_observed'=>$observed===null?null:CanonicalJson::hash($observed),
            'rows_expected'=>count($expected['rows']),'rows_observed'=>$observed===null?null:count($observed['rows'])];
        $tables[$name]=$row;
        if($read!==null&&($row['schema_expected']!==$row['schema_observed']||$row['data_expected']!==$row['data_observed']))$affected[]=$name;
    }
    $pairs=[];foreach(['schema','data','artifact'] as $kind){
        $expected=match($kind){'schema'=>$inputs['schema']['tables'],'data'=>$inputs['snapshot']['tables'],'artifact'=>$inputs['checkpoint']['summary']};
        $observed=match($kind){'schema'=>$read['definitions']??null,'data'=>$read['tables']??null,'artifact'=>$artifact};
        $pairs[$kind]=['expected'=>CanonicalJson::hash($expected),'observed'=>$observed===null?null:CanonicalJson::hash($observed)];
    }
    $categories=[];foreach($pairs as $kind=>$pair)if($pair['observed']!==null&&$pair['expected']!==$pair['observed'])$categories[]=$kind;
    $failure=null;foreach($v['progress'] as $kind=>$status)if($status==='FAILED'){$failure=$kind.'_failed';break;}
    $result=['profile'=>'m6e-eligibility-observer-diagnostic-v1','phase'=>$v['phase'],'binding'=>$r['binding'],
        'request_sha256'=>CanonicalJson::hash($r),'invocation_id'=>$r['invocation_id'],'process'=>$v['process'],
        'database'=>$v['observation']['database'],'progress'=>$v['progress'],'measurements'=>$pairs,'tables'=>$tables,
        'affected_tables'=>$affected,'categories'=>$categories,'classification'=>$failure??($categories!==[]?'drift':'unchanged'),
        'result'=>'DIAGNOSTIC_ONLY','admission'=>'UNIMPLEMENTED','native_admission'=>'BLOCKED','deployment_authority'=>'NONE'];
    m6eNeed(strlen(CanonicalJson::encode($result))<=65536,'eligibility_diagnostic_bound');return $result;
}
/** Narrow local seam: production supplies the unchanged readers and protected exclusive writer. */
function m6eEligibilityObserve(array $inputs,string $phase,array $process,callable $identify,callable $readDatabase,callable $readArtifact,callable $capture):array {
    $r=$inputs['eligibility'];$identity=null;$read=null;$artifact=null;$primary=null;$secondary=[];
    $progress=['identity'=>'NOT_REACHED','read'=>'NOT_REACHED','artifact'=>'NOT_REACHED'];$stage='identity';
    try{
        $progress[$stage]='FAILED';$identity=$identify();$progress[$stage]='COMPLETED';
        $stage='read';$progress[$stage]='FAILED';$read=$readDatabase();$progress[$stage]='COMPLETED';
        $stage='artifact';$progress[$stage]='FAILED';$artifact=$readArtifact();$progress[$stage]='COMPLETED';
        $stage='drift';
        $matches=CanonicalJson::encode($read['definitions'])===CanonicalJson::encode($inputs['schema']['tables'])
            &&CanonicalJson::encode($read['tables'])===CanonicalJson::encode($inputs['snapshot']['tables'])
            &&CanonicalJson::encode($artifact)===CanonicalJson::encode($inputs['checkpoint']['summary']);
        // Record the pending mismatch, then capture before raising it to the CLI/coordinator.
        if(!$matches)$primary=new RuntimeException('eligibility_observer_drift');
    }catch(Throwable $error){$primary=$error;
        if($error instanceof M6ERestorationFailure&&($error->diagnostic['secondary']??[])!==[])$secondary[]='eligibility_observer_read_cleanup';}
    $observation=['profile'=>'m6e-pre-b-restoration-observation-v1','binding'=>$r['binding'],
        'request_sha256'=>CanonicalJson::hash($inputs['request']),'database'=>$identity,'read'=>$read,'artifact'=>$artifact,
        'deployment_authority'=>'NONE','admission'=>'UNIMPLEMENTED'];
    try{
        $v=['request_sha256'=>CanonicalJson::hash($r),'phase'=>$phase,'process'=>$process,'progress'=>$progress,'observation'=>$observation];
        $v['diagnostic']=m6eEligibilityObservationDiagnostic($inputs,$v);$capture($v);
    }catch(Throwable $error){$secondary[]='eligibility_observer_capture';if($primary===null){$primary=$error;$stage='capture';}}
    if($primary!==null)throw new M6EEligibilityFailure(['phase'=>'observer-'.$stage,
        'first_failure'=>'eligibility_observer_'.$stage,'secondary'=>$secondary]);
    return ['result'=>'OBSERVED'];
}
/** Shared SQL account operations; each launcher independently admits its operator/process owner. */
function m6eEligibilityAccount(PDO $admin,array $inputs,bool $prepare,callable $write):array {
    $d=$inputs['request']['destination'];$accounts=m6eEligibilityAccounts($admin,$inputs);
    $account="'".M6E_ELIGIBILITY_USER."'@'127.0.0.1'";
    if($prepare){
        m6eNeed(m6eSnapshotRows($admin,'SELECT User FROM mysql.user WHERE User=?',[M6E_ELIGIBILITY_USER])===[],'eligibility_account_exists');
        $secret=bin2hex(random_bytes(32));$admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($secret).' REQUIRE SSL WITH MAX_USER_CONNECTIONS 1');
        $admin->exec('GRANT LOCK TABLES ON `'.str_replace('_','\\_',$d['schema']).'`.* TO '.$account);
        foreach(M6E_ELIGIBILITY_TABLES as $table)$admin->exec('GRANT SELECT ON '.m6eSnapshotName($d['schema']).'.'.m6eSnapshotName($table).' TO '.$account);
        $write(['username'=>M6E_ELIGIBILITY_USER,'password'=>$secret]);
        return ['restoration_accounts'=>$accounts,'eligibility_account'=>M6E_ELIGIBILITY_USER.'@127.0.0.1','created'=>'EXCLUSIVE'];
    }
    $admin->exec('ALTER USER '.$account.' ACCOUNT LOCK');
    m6eNeed((int)m6eSnapshotScalar($admin,"SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='".M6E_ELIGIBILITY_USER."'")===0,'eligibility_session_active');
    $grants=array_map(static fn($v)=>array_values($v)[0],m6eSnapshotRows($admin,'SHOW GRANTS FOR '.$account));m6eEligibilityGrants($grants,$d['schema']);
    $policy=m6eSnapshotRows($admin,"SELECT account_locked, ssl_type, max_user_connections FROM mysql.user WHERE User=? AND Host='127.0.0.1'",[M6E_ELIGIBILITY_USER]);
    m6eNeed($policy===[['account_locked'=>'Y','ssl_type'=>'ANY','max_user_connections'=>'1']],'eligibility_account_policy');
    return ['account'=>M6E_ELIGIBILITY_USER.'@127.0.0.1','locked'=>true,'grants_sha256'=>CanonicalJson::hash($grants),
        'sessions'=>0,'restorer_processes'=>0,'restoration_accounts'=>$accounts];
}
function m6eEligibilityNative(string $mode):array {
    $a=m6eBootstrap();m6eNeed(posix_geteuid()===($mode==='--run'?M6E_RESTORE_UID:0),'eligibility_native_uid');
    $inputs=m6eEligibilityInputs($a);$r=$inputs['eligibility'];$d=$inputs['request']['destination'];
    $deadline=hrtime(true)+60000000000;$pulse=static function()use($a,$deadline):void{
        m6eNeed(time()<$a['expires_at']&&hrtime(true)<$deadline,'eligibility_deadline');};$pulse();
    $cgroup=trim(file_get_contents('/proc/self/cgroup'));$owner=$r['owner'];
    m6eNeed($cgroup==='0::/system.slice/ubo-m6e-bootstrap.service'&&$owner['cgroup']===[$cgroup]
        &&posix_getppid()===$owner['pid'],'eligibility_process_owner');
    if($mode==='--prepare'||$mode==='--seal'){
        m6eEligibilityIdle();$admin=m6eRestoreConnect($a,$inputs,'admin');
        return m6eEligibilityAccount($admin,$inputs,$mode==='--prepare',static fn(array $v)=>m6eEligibilityWrite($a,'credentials.json',$v));
    }
    if($mode==='--run'){
        $outcome=['result'=>'INCOMPLETE','phase'=>'connection','first_failure'=>null,'secondary'=>[],
            'transaction'=>'UNREACHED','closed'=>false,'gate_returned'=>false];$error=null;
        try {
            $credentials=json_decode(SitePublisherHostContract::protectedFile($d['control_root'].'/eligibility/credentials.json',8192),true,16,JSON_THROW_ON_ERROR);
            m6eNeed($credentials['username']===M6E_ELIGIBILITY_USER,'eligibility_credentials');
            $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$d['schema'].';charset=utf8mb4',M6E_ELIGIBILITY_USER,$credentials['password'],
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
                    PDO::ATTR_STRINGIFY_FETCHES=>false,PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_SSL_CA=>'/etc/ubo-site-publisher/tls/ca.pem']);
            m6eEligibilityTransaction($db,$a,$inputs['checkpoint'],$r,$pulse,$outcome);
        } catch(Throwable $e){$error=$e;$outcome['result']='INCOMPLETE';$outcome['first_failure']??='eligibility_connection';}
        finally {
            $db=null;$outcome+=['request_sha256'=>CanonicalJson::hash($r),'invocation_id'=>$r['invocation_id'],
                'process'=>['pid'=>getmypid(),'uid'=>posix_geteuid(),'gid'=>posix_getegid(),'owner_pid'=>posix_getppid()],
                'admission'=>'UNIMPLEMENTED','native_admission'=>'BLOCKED','deployment_authority'=>'NONE'];
            try{m6eEligibilityWrite($a,'worker/outcome.json',$outcome,$outcome['secondary']);}
            catch(Throwable){$outcome['secondary'][]='eligibility_outcome_write';$error??=new RuntimeException('eligibility_outcome_write');}
        }
        if($error!==null)throw new M6EEligibilityFailure($outcome);return ['result'=>'AWAITING_INDEPENDENT_OBSERVER'];
    }
    m6eNeed(in_array($mode,['--before','--after'],true),'eligibility_mode');
    $db=null;$cp=$inputs['checkpoint'];
    return m6eEligibilityObserve($inputs,substr($mode,2),['pid'=>getmypid(),'uid'=>posix_geteuid(),'gid'=>posix_getegid(),'owner_pid'=>posix_getppid()],
        static function()use(&$db,$a,$inputs,$d,$r):array{$db=m6eRestoreConnect($a,$inputs,'observer');
            return m6eRestoreAdmit($db,['schema'=>$d['schema'],'server_uuid'=>$r['server_uuid'],'account'=>$d['observer'].'@127.0.0.1'],false);},
        static function()use(&$db,$d,$pulse):array{return m6eRestoreRead($db,$d['schema'],$pulse);},
        static function()use($a,$cp,$pulse):array{$store=m6eArtifactStore(m6eBuildConfig($a));return m6eReleaseSummary(
            $cp['observed']['release'],$cp['observed']['job'],$cp['observed']['producer'],$store->verifyRelease($cp['observed']['job'],$cp['observed']['producer'],$pulse));},
        static function(array $v)use($a,$mode):void{m6eEligibilityWrite($a,substr($mode,2).'.json',$v);});
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{m6eNeed(count($argv)===2&&in_array($argv[1],['--prepare','--run','--seal','--before','--after'],true),'eligibility_usage');
        echo CanonicalJson::encode(m6eEligibilityNative($argv[1]))."\n";}
    catch(Throwable $error){$v=$error instanceof M6EEligibilityFailure?$error->diagnostic:[];
        echo CanonicalJson::encode(['result'=>'INCOMPLETE','diagnostic'=>['phase'=>$v['phase']??'native',
            'first_failure'=>$v['first_failure']??'eligibility_native','secondary'=>$v['secondary']??[]]])."\n";exit(1);}
}
