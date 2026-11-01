<?php
declare(strict_types=1);
/** AUTHORED ONLY. Fixed disposable VM schema/build preparation; never application startup. */
require_once dirname(__DIR__,2).'/private/classes/SitePublisherHostContract.php';

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
    $name='ubo_m6e_'.$a['run_id'];$foreign=$name.'_foreign';
    m6eNeed(str_starts_with((string)$db->query('SELECT VERSION()')->fetchColumn(),'8.4.'),'mysql_version');
    $uuid=$db->query('SELECT @@server_uuid')->fetchColumn();
    $db->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('CREATE DATABASE `'.$foreign.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `'.$foreign.'`');$db->exec('CREATE TABLE canary(id INT PRIMARY KEY)');$db->exec('INSERT INTO canary VALUES (1)');
    $db->exec('USE `'.$name.'`');m6mysqlMigrate($db,1,25); // Unchanged canonical SQL/parser; never invoked on desktop.
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
    m6eWrite('/run/ubo-m6e-fixture/admin.json',json_encode(['password'=>$admin],JSON_THROW_ON_ERROR),0,0600);
    m6eWrite('/run/ubo-m6e-fixture/writer.json',json_encode($accounts['writer'],JSON_THROW_ON_ERROR),2106,0640);
    $secret=json_encode($accounts['observer'],JSON_THROW_ON_ERROR);
    m6eWrite('/etc/ubo-site-publisher/authority-credentials.json',$secret,2103,0640);
    $config=['DB_HOST'=>'127.0.0.1','DB_PORT'=>3306,'DB_NAME'=>$name,'DB_USER'=>$accounts['worker']['username'],
        'DB_PASSWORD'=>$accounts['worker']['password'],'DB_SSL_MODE'=>'REQUIRED','DB_SSL_CA'=>'/etc/ubo-site-publisher/tls/ca.pem',
        'APP_ENV'=>'staging','APP_DEBUG'=>false,'APP_BASE_URL'=>'https://active.m6e.invalid','ACCOUNTS_BASE_URL'=>'https://active.m6e.invalid'];
    m6eWrite('/opt/ubo-m6e/private/config/env.php',"<?php\nreturn ".var_export($config,true).";\n",2000,0640);
    m6eWrite('/run/ubo-m6e-fixture/database-identity.json',json_encode(['schema'=>$name,'server_uuid'=>$uuid,'writer'=>'m6e_writer',
        'authority'=>'m6e_observer','credentials_sha256'=>hash('sha256',$secret)],JSON_THROW_ON_ERROR),0,0644);
    echo "schema_prepared\n";
}
function m6eBuild(array $a):void {
    m6eNeed(posix_geteuid()===2000,'build_worker');
    require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
    require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';
    require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentContract.php';
    $db=Database::connection();$db->exec("SET SESSION time_zone='+00:00'");
    $config=['work_root'=>'/var/lib/ubo-m6e-fixture/work','artifact_root'=>'/var/lib/ubo-m6e-fixture/sealed',
        'legacy_upload_root'=>'/var/lib/ubo-m6e-fixture/uploads','worker_id'=>'m6e-native-worker','builder_code_sha'=>$a['source_sha']];
    $f=m6nFixture($db,$config,static function(PDO $db,array $config,array $f):void {
        $pdf=M6CSupplementPdf::bytes();$path=$config['legacy_upload_root'].'/approved.pdf';
        m6eNeed(file_put_contents($path,$pdf)===strlen($pdf)&&chmod($path,0600),'pdf_file');
        $asset=m6nInsert($db,'site_assets',['site_id'=>$f['site_id'],'business_id'=>(int)$db->query('SELECT business_id FROM site_business_associations WHERE site_id='.(int)$f['site_id'])->fetchColumn(),
            'asset_key'=>SiteServiceSupport::uuidV4(),'asset_type'=>'document','storage_key'=>'/uploads/approved.pdf','checksum_sha256'=>hash('sha256',$pdf),
            'mime_type'=>'application/pdf','byte_size'=>strlen($pdf),'source'=>'legacy_247sp','rights_classification'=>'customer_owned','lifecycle_status'=>'ready']);
        $db->prepare("UPDATE site_revisions SET lifecycle_status='draft' WHERE id=?")->execute([$f['revision_id']]);
        $hash=(string)$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.(int)$f['revision_id'])->fetchColumn();
        $composition=m6nComposition($hash,$f['asset_id']);$composition['pages'][0]['sections'][]=M6CSupplementPdf::section($asset);
        SiteCompositionManager::replaceDraftComposition($f['requester'],$f['revision_id'],$composition);
        $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved' WHERE id=?")->execute([$f['revision_id']]);
    });
    $claim=SiteBuildService::claimBuild([]);m6eNeed($claim!==null,'build_claim');
    SiteBuildService::completeBuildSuccess($claim['lease'],SiteBuildService::executeBuild($claim['lease']));
    $release=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE build_job_id=:id',['id'=>$f['job_id']]);
    $site=SiteBuildStore::one($db,'SELECT * FROM sites WHERE id=:id',['id'=>$f['site_id']]);
    $target=m6nInsert($db,'site_deployment_targets',['site_id'=>$f['site_id'],'environment'=>'staging','publisher_key'=>'apache-digitalocean-v1',
        'binding_key'=>'m6e-fixture','binding_version'=>1,'enabled'=>1,'updated_at'=>SiteBuildStore::now($db)]);
    $dto=SiteDeploymentContract::release($release,$site);
    echo json_encode(['fixture'=>$f,'target_id'=>$target,'release'=>$dto,'producer_attempt_id'=>$claim['lease']['attempt_id']],JSON_THROW_ON_ERROR)."\n";
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{$a=m6eBootstrap();m6eNeed(count($argv)===2,'arguments');
        match($argv[1]){'--schema'=>m6eSchema($a),'--build'=>m6eBuild($a),default=>throw new RuntimeException('mode')};
    }catch(Throwable){fwrite(STDERR,"m6e_fixture_setup_unresolved\n");exit(1);}
}
