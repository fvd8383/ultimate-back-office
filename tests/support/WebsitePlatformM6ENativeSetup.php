<?php
declare(strict_types=1);
/** AUTHORED ONLY. Fixed disposable VM schema/build preparation; never application startup. */
require_once dirname(__DIR__,2).'/private/classes/SitePublisherHostContract.php';
require_once dirname(__DIR__,2).'/private/classes/SiteBuildRuntime.php';

function m6eNeed(bool $condition,string $code):void {if(!$condition)throw new RuntimeException($code);}
function m6eBootstrap():array {
    m6eNeed(PHP_SAPI==='cli'&&PHP_OS_FAMILY==='Linux'&&realpath(dirname(__DIR__,2))==='/opt/ubo-m6e','fixture_runtime');
    $a=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/bootstrap.json',65536),true,32,JSON_THROW_ON_ERROR);
    m6eNeed(preg_match('/^[a-f0-9]{32}$/D',$a['run_id'])===1&&preg_match('/^[a-f0-9]{40}$/D',$a['source_sha'])===1
        &&$a['machine_id']===trim(file_get_contents('/etc/machine-id'))&&$a['boot_id']===trim(file_get_contents('/proc/sys/kernel/random/boot_id'))
        &&gethostname()==='ubo-m6e-'.substr($a['run_id'],0,16)&&$a['expires_at']>time()
        &&in_array('ubo.m6e='.$a['run_id'],explode(' ',trim(file_get_contents('/proc/cmdline'))),true),'fixture_identity');
    return $a;
}
function m6eWrite(string $path,string $bytes,int $gid,int $mode):void {
    m6eNeed(str_starts_with($path,'/etc/ubo-site-publisher/')||str_starts_with($path,'/run/ubo-m6e-fixture/')
        ||$path==='/opt/ubo-m6e/private/config/env.php','fixed_write');
    $h=fopen($path,'xb');m6eNeed(is_resource($h),'exclusive_write');
    try{$off=0;while($off<strlen($bytes)){$n=fwrite($h,substr($bytes,$off,65536));m6eNeed(is_int($n)&&$n>0,'write');$off+=$n;}
        m6eNeed(fflush($h)&&fsync($h),'sync');m6eNeed(chgrp($path,$gid)&&chmod($path,$mode),'mode');}
    finally{fclose($h);}
}
function m6eSchema(array $a):void {
    m6eNeed(posix_geteuid()===0&&extension_loaded('pdo_mysql'),'schema_operator');
    require_once __DIR__.'/WebsitePlatformM6BMigrations.php';
    $db=new PDO('mysql:unix_socket=/var/lib/ubo-m6e-fixture/mysql-run/mysql.sock;charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $result=m6eSchemaData($db,$a,Closure::fromCallable('m6eWrite'),static function(PDO $db):void {
        ob_start();try{m6mysqlMigrate($db,1,25);}finally{ob_end_clean();}
    });
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}
/** Actual schema orchestration, with explicit test-only I/O seams; never a production admission path. */
function m6eSchemaData(PDO $db,array $a,callable $write,callable $migrate):array {
    $name='ubo_m6e_'.$a['run_id'];$foreign=$name.'_foreign';
    m6eNeed(str_starts_with((string)$db->query('SELECT VERSION()')->fetchColumn(),'8.4.'),'mysql_version');
    $uuid=$db->query('SELECT @@server_uuid')->fetchColumn();
    $db->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('CREATE DATABASE `'.$foreign.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `'.$foreign.'`');$db->exec('CREATE TABLE canary(id INT PRIMARY KEY)');$db->exec('INSERT INTO canary VALUES (1)');
    $db->exec('USE `'.$name.'`');
    // Canonical helper output is not the command protocol. Never expose SQL/driver diagnostics.
    $migrate($db);
    m6eNeed((int)$db->query("SELECT COUNT(*) FROM modules WHERE module_key='247sp'")->fetchColumn()===1,'canonical_seed');
    $accounts=[];
    foreach(['writer','worker','observer'] as $role){
        $user='m6e_'.$role;$password=bin2hex(random_bytes(32));$account=$db->quote($user)."@'127.0.0.1'";
        $db->exec('CREATE USER '.$account.' IDENTIFIED BY '.$db->quote($password).' REQUIRE SSL');
        $rights=match($role){'writer'=>'ALL PRIVILEGES','worker'=>'SELECT, INSERT, UPDATE, DELETE','observer'=>'SELECT'};
        $db->exec('GRANT '.$rights.' ON `'.$name.'`.* TO '.$account);
        // Fixture writer observes actual in-flight worker transactions. The
        // independent authority account retains SELECT on this schema only.
        if($role==='writer')$db->exec('GRANT PROCESS ON *.* TO '.$account);
        $accounts[$role]=['username'=>$user,'password'=>$password];
    }
    $admin=bin2hex(random_bytes(32));$db->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$db->quote($admin));
    $write('/run/ubo-m6e-fixture/admin.json',json_encode(['password'=>$admin],JSON_THROW_ON_ERROR),0,0600);
    $write('/run/ubo-m6e-fixture/writer.json',json_encode($accounts['writer'],JSON_THROW_ON_ERROR),2106,0640);
    $secret=json_encode($accounts['observer'],JSON_THROW_ON_ERROR);
    $write('/etc/ubo-site-publisher/authority-credentials.json',$secret,2103,0640);
    $config=['DB_HOST'=>'127.0.0.1','DB_PORT'=>3306,'DB_NAME'=>$name,'DB_USER'=>$accounts['worker']['username'],
        'DB_PASSWORD'=>$accounts['worker']['password'],'DB_SSL_MODE'=>'REQUIRED','DB_SSL_CA'=>'/etc/ubo-site-publisher/tls/ca.pem',
        'APP_ENV'=>'staging','APP_DEBUG'=>false,'APP_BASE_URL'=>'https://active.m6e.invalid','ACCOUNTS_BASE_URL'=>'https://active.m6e.invalid'];
    $write('/etc/ubo-site-publisher/application.php',"<?php\nreturn ".var_export($config,true).";\n",2000,0640);
    $write('/opt/ubo-m6e/private/config/env.php',"<?php\nreturn require '/etc/ubo-site-publisher/application.php';\n",2000,0640);
    $identity=['schema'=>$name,'server_uuid'=>$uuid,'writer'=>'m6e_writer','worker'=>'m6e_worker',
        'authority'=>'m6e_observer','credentials_sha256'=>hash('sha256',$secret),'migrations'=>range(1,25),'seed_247sp'=>1];
    $write('/run/ubo-m6e-fixture/database-identity.json',json_encode($identity,JSON_THROW_ON_ERROR),0,0644);
    return $identity;
}
function m6eBuildConfig(array $a):array {
    return ['work_root'=>'/var/lib/ubo-m6e-fixture/work','artifact_root'=>'/var/lib/ubo-m6e-fixture/sealed',
        'legacy_upload_root'=>'/var/lib/ubo-m6e-fixture/uploads','worker_id'=>'m6e-native-worker','builder_code_sha'=>$a['source_sha']];
}
function m6eReleaseSummary(array $release,array $job,array $producer,array $e):array {
    m6eNeed($e['disposition']==='sealed'&&$e['manifest_hash']===$release['manifest_hash']
        &&$e['artifact_hash']===$release['artifact_hash']&&(int)$job['id']===(int)$release['build_job_id']
        &&$producer['attempt_kind']==='execution'&&(int)$producer['build_job_id']===(int)$job['id']
        &&(int)$e['producer_attempt_id']===(int)$producer['id'],'release_crosscheck');
    $assets=[];
    foreach($e['manifest']['files'] as $file)if(str_starts_with($file['relative_path'],'public/assets/'))
        $assets[]=['sha256'=>$file['sha256'],'mime_type'=>$file['mime_type'],'bytes'=>$file['byte_size']];
    return ['release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],'build_job_id'=>(int)$job['id'],
        'producer_attempt_id'=>(int)$producer['id'],'manifest_sha256'=>$e['manifest_hash'],'artifact_sha256'=>$e['artifact_hash'],
        'file_count'=>$e['file_count'],'assets'=>$assets];
}
/** Construct only the artifact store; never invoke the protected runtime factory. */
function m6eArtifactStore(array $config):LocalSiteArtifactStore {
    return new LocalSiteArtifactStore($config['work_root'],$config['artifact_root'],Closure::fromCallable([SiteBuildRuntime::class,'guard']));
}
function m6eObserveRelease(PDO $db,array $config,int $id):array {
    $release=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id=:id',['id'=>$id]);m6eNeed($release!==null,'release_missing');
    $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>(int)$release['build_job_id']]);
    $producer=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>(int)$job['current_attempt_id']]);
    $store=m6eArtifactStore($config);
    return m6eReleaseSummary($release,$job,$producer,$store->verifyRelease($job,$producer));
}
function m6eFinishBuild(PDO $db,int $jobId):int {
    $claim=SiteBuildService::claimBuild([]);m6eNeed($claim!==null&&(int)$claim['lease']['job_id']===$jobId,'build_claim');
    SiteBuildService::completeBuildSuccess($claim['lease'],SiteBuildService::executeBuild($claim['lease']));
    $release=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE build_job_id=:id',['id'=>$jobId]);
    m6eNeed($release!==null,'completed_build_release');return (int)$release['id'];
}
function m6eAssetSets(array $releases):array {
    $a=array_column($releases[0]['assets'],'sha256');$b=array_column($releases[1]['assets'],'sha256');
    $shared=array_values(array_unique(array_intersect($a,$b)));sort($shared);
    $changed=array_values(array_unique(array_merge(array_diff($a,$b),array_diff($b,$a))));sort($changed);
    $pdf=[];foreach($releases as $r)foreach($r['assets'] as $asset)if($asset['mime_type']==='application/pdf')$pdf[]=$asset['sha256'];
    $pdf=array_values(array_unique($pdf));sort($pdf);m6eNeed($shared!==[]&&$changed!==[]&&$pdf!==[],'two_release_assets');
    return ['shared_assets'=>$shared,'changed_assets'=>$changed,'pdf_assets'=>$pdf];
}
function m6ePristine(PDO $db,int $site,int $target):void {
    $t=SiteBuildStore::one($db,'SELECT * FROM site_deployment_targets WHERE id=:id AND site_id=:site',['id'=>$target,'site'=>$site]);
    m6eNeed($t!==null&&$t['current_deployment_id']===null&&$t['active_deployment_id']===null
        &&(int)$t['pointer_version']===0&&(int)$t['fence_epoch']===0,'unpublished_target');
    $s=$db->prepare('SELECT COUNT(*) FROM site_deployments WHERE site_id=?');$s->execute([$site]);
    m6eNeed((int)$s->fetchColumn()===0,'no_publisher_operations');
}
function m6eBuild(array $a):void {
    m6eNeed(posix_geteuid()===2000,'build_worker');
    require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
    require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';
    require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentContract.php';
    $db=Database::connection();$db->exec("SET SESSION time_zone='+00:00'");
    $config=m6eBuildConfig($a);$pdfAsset=0;
    // Synthetic approvals/data setup only, not protected installed-worker acceptance.
    $f=m6nFixture($db,$config,static function(PDO $db,array $config,array $f)use(&$pdfAsset):void {
        $pdf=M6CSupplementPdf::bytes();$path=$config['legacy_upload_root'].'/approved.pdf';
        m6eNeed(file_put_contents($path,$pdf)===strlen($pdf)&&chmod($path,0600),'pdf_file');
        $asset=m6nInsert($db,'site_assets',['site_id'=>$f['site_id'],'business_id'=>(int)$db->query('SELECT business_id FROM site_business_associations WHERE site_id='.(int)$f['site_id'])->fetchColumn(),
            'asset_key'=>SiteServiceSupport::uuidV4(),'asset_type'=>'document','storage_key'=>'/uploads/approved.pdf','checksum_sha256'=>hash('sha256',$pdf),
            'mime_type'=>'application/pdf','byte_size'=>strlen($pdf),'source'=>'legacy_247sp','rights_classification'=>'customer_owned','lifecycle_status'=>'ready']);
        $pdfAsset=$asset;m6eNeed(chmod($f['asset_path'],0600),'source_asset_private');
        $db->prepare("UPDATE site_revisions SET lifecycle_status='draft' WHERE id=?")->execute([$f['revision_id']]);
        $hash=(string)$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.(int)$f['revision_id'])->fetchColumn();
        $composition=m6nComposition($hash,$f['asset_id']);$composition['pages'][0]['sections'][]=M6CSupplementPdf::section($asset);
        SiteCompositionManager::replaceDraftComposition($f['requester'],$f['revision_id'],$composition);
        $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved' WHERE id=?")->execute([$f['revision_id']]);
    });
    $first=m6eFinishBuild($db,$f['job_id']);
    require_once __DIR__.'/WebsitePlatformM6ECheckpoint.php';
    m6eNativeCheckpoint($db,$config,$a,$first);
    $original=SiteBuildStore::one($db,'SELECT * FROM site_revisions WHERE id=:id',['id'=>$f['revision_id']]);
    $next=SiteRevisionManager::createRevision($f['requester'],['site_id'=>$f['site_id'],'based_on_revision_id'=>$f['revision_id'],
        'snapshot_schema_version'=>1,'facts_snapshot_json'=>$original['facts_snapshot_json'],
        'source_references_json'=>$original['source_references_json'],'snapshot_hash'=>str_repeat('0',64)]);
    $revision=$next['revision_id'];
    SiteCompositionEditor::apply($f['requester'],$revision,['operation'=>'initialize_new','expected_snapshot_hash'=>str_repeat('0',64)]);
    $hash=(string)$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.(int)$revision)->fetchColumn();
    $composition=m6nComposition($hash,$f['asset_id']);$composition['theme']['primary_color']='#654321';
    $composition['pages'][0]['sections'][]=M6CSupplementPdf::section($pdfAsset);
    SiteCompositionManager::replaceDraftComposition($f['requester'],$revision,$composition);
    $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved',materiality='material',review_ready_at=UTC_TIMESTAMP() WHERE id=?")->execute([$revision]);
    foreach(['customer','internal'] as $type)m6nInsert($db,'site_approvals',['site_id'=>$f['site_id'],'revision_id'=>$revision,'approval_type'=>$type,
        'actor_type'=>$type==='customer'?'customer':'internal_admin','state'=>'approved','requested_at'=>'2026-09-19 00:00:00',
        'decided_at'=>'2026-09-19 00:00:00','metadata_json'=>'{}']);
    $hash=(string)$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.(int)$revision)->fetchColumn();
    $request=SiteBuildService::requestBuild($f['requester'],['site_id'=>$f['site_id'],'revision_id'=>$revision,
        'expected_snapshot_hash'=>$hash,'build_profile'=>SiteBuildContract::PROFILE]);
    $second=m6eFinishBuild($db,(int)$request['id']);
    $site=SiteBuildStore::one($db,'SELECT * FROM sites WHERE id=:id',['id'=>$f['site_id']]);
    $target=m6nInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'staging','publisher_key'=>'apache-digitalocean-v1',
        'binding_key'=>'m6e-fixture','binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
    $foreignSite=m6nInsert($db,'sites',['site_key'=>SiteServiceSupport::uuidV4(),'purpose'=>'247sp','lifecycle_status'=>'draft']);
    $asset=SiteBuildStore::one($db,'SELECT * FROM site_assets WHERE id=:id',['id'=>$f['asset_id']]);
    m6nInsert($db,'site_business_associations',['site_id'=>$foreignSite,'business_id'=>(int)$asset['business_id'],
        'association_role'=>'customer','status'=>'active','effective_at'=>'2026-09-19 00:00:00']);
    $foreignAsset=m6nInsert($db,'site_assets',['site_id'=>$foreignSite,'business_id'=>(int)$asset['business_id'],
        'asset_key'=>SiteServiceSupport::uuidV4(),'asset_type'=>'image','storage_key'=>$asset['storage_key'],
        'checksum_sha256'=>$asset['checksum_sha256'],'mime_type'=>$asset['mime_type'],'byte_size'=>(int)$asset['byte_size'],
        'source'=>'legacy_247sp','rights_classification'=>'customer_owned','lifecycle_status'=>'ready']);
    $releases=[m6eObserveRelease($db,$config,$first),m6eObserveRelease($db,$config,$second)];
    m6ePristine($db,$f['site_id'],$target);
    $result=['version'=>1,'run_id'=>$a['run_id'],'site_id'=>$f['site_id'],'site_key'=>$site['site_key'],'target_id'=>$target,
        'requester_id'=>$f['requester'],'releases'=>$releases]+m6eAssetSets($releases)+
        ['foreign_asset'=>['site_id'=>$foreignSite,'asset_id'=>$foreignAsset,'sha256'=>$asset['checksum_sha256']],'unpublished'=>true];
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}
function m6eInspect(array $a):void {
    m6eNeed(posix_geteuid()===2000,'inspect_worker');
    require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
    require_once __DIR__.'/WebsitePlatformM6ECheckpoint.php';
    $db=Database::connection();$f=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',1048576),true,64,JSON_THROW_ON_ERROR);
    $identity=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/database-identity.json',8192),true,32,JSON_THROW_ON_ERROR);
    m6eNeed($db->query('SELECT DATABASE()')->fetchColumn()===$identity['schema']&&$db->query('SELECT @@server_uuid')->fetchColumn()===$identity['server_uuid']
        &&$db->query('SELECT CURRENT_USER()')->fetchColumn()==='m6e_worker@127.0.0.1','inspection_database');
    foreach($f['releases'] as $i=>$release)$f['releases'][$i]=m6eObserveRelease($db,m6eBuildConfig($a),$release['release_id']);
    $f=array_replace($f,m6eAssetSets($f['releases']));m6ePristine($db,$f['site_id'],$f['target_id']);
    m6eFinalAIneligible($db,$f['releases'][0]['release_id']);
    $foreign=SiteBuildStore::one($db,'SELECT * FROM site_assets WHERE id=:id',['id'=>$f['foreign_asset']['asset_id']]);
    m6eNeed((int)$foreign['site_id']===$f['foreign_asset']['site_id']&&$foreign['checksum_sha256']===$f['foreign_asset']['sha256'],'foreign_asset_binding');
    echo json_encode(['fixture'=>$f,'database'=>$identity],JSON_THROW_ON_ERROR)."\n";
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{$a=m6eBootstrap();m6eNeed(count($argv)===2,'arguments');
        match($argv[1]){'--schema'=>m6eSchema($a),'--build-two'=>m6eBuild($a),'--inspect'=>m6eInspect($a),default=>throw new RuntimeException('mode')};
    }catch(Throwable){fwrite(STDERR,"m6e_fixture_setup_unresolved\n");exit(1);}
}
