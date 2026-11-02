<?php
declare(strict_types=1);
/** Test-only commissioning child. No protected worker boot or dependency override. */
$mode=$argv[1]??'';
$authority=in_array($mode,['--inventory-authority','--runtime'],true);
$classes=$authority?'/usr/local/lib/ubo-site-publisher/php':dirname(__DIR__,2).'/private/classes';
require_once $classes.'/SitePublisherHostContract.php';
function commissionNeed(bool $value):void {if(!$value)throw new RuntimeException('commission_child');}
function commissionAdmission(int $uid):array {
    commissionNeed(PHP_SAPI==='cli'&&PHP_OS_FAMILY==='Linux'&&posix_geteuid()===$uid
        &&realpath(__DIR__)==='/opt/ubo-m6e/tests/support');
    $a=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/bootstrap.json',65536),65536);
    commissionNeed($a['expires_at']>time()&&$a['machine_id']===trim(file_get_contents('/etc/machine-id'))
        &&$a['boot_id']===trim(file_get_contents('/proc/sys/kernel/random/boot_id'))
        &&gethostname()==='ubo-m6e-'.substr($a['run_id'],0,16)
        &&in_array('ubo.m6e='.$a['run_id'],explode(' ',trim(file_get_contents('/proc/cmdline'))),true));
    return $a;
}
try {
    commissionNeed(count($argv)===2);
    if($mode==='--format') {
        // Pure schema compatibility is available on the desktop. It cannot load a
        // host, choose a receipt pathname, open SQL, or create a capability.
        $raw=stream_get_contents(STDIN,196609);commissionNeed(strlen($raw)<=196608);
        $v=SiteBuildContract::decode($raw,196608);
        SitePublisherHostContract::validate($v['worker'],$v['receipt'],$v['worker_sha256'],$v['now']);
        echo "{\"result\":\"FORMAT VALID\"}\n";exit(0);
    }
    $a=commissionAdmission($authority?2103:2000);
    if(in_array($mode,['--inventory-worker','--admit-receipt','--serving-source'],true)) {
        require_once $classes.'/SiteDeploymentService.php';
        require_once __DIR__.'/WebsitePlatformM6ECommissionServing.php';
        commissionNeed(Database::config('APP_ENV')==='staging');
        if($mode==='--serving-source') {
            echo CanonicalJson::encode(m6eServingSources($a))."\n";exit(0);
        }
        if($mode==='--admit-receipt') {
            SitePublisherHostContract::load();
            echo "{\"result\":\"WORKER RECEIPT ADMITTED\"}\n";exit(0);
        }
    } elseif($mode==='--inventory-authority') {
        require_once $classes.'/SitePublisherAuthority.php';
    } elseif($mode==='--runtime') {
        $c=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/etc/ubo-site-publisher/authority.json',16384),16384);
        $raw=SitePublisherHostContract::protectedFile('/etc/ubo-site-publisher/authority-credentials.json',8192);
        commissionNeed(hash('sha256',$raw)===$c['credentials_sha256']);
        $credentials=SiteBuildContract::decode($raw,8192);
        $db=new PDO($c['dsn'],$credentials['username'],$credentials['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3,
            PDO::MYSQL_ATTR_SSL_CA=>$c['ca_file'],PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true]);
        $identity=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/database-identity.json',8192),8192);
        commissionNeed($db->query('SELECT DATABASE()')->fetchColumn()===$identity['schema']
            &&$db->query('SELECT @@server_uuid')->fetchColumn()===$identity['server_uuid']
            &&$db->query('SELECT CURRENT_USER()')->fetchColumn()==='m6e_observer@127.0.0.1'
            &&$db->getAttribute(PDO::ATTR_EMULATE_PREPARES)===false);
        $cipher=$db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
        commissionNeed(is_array($cipher)&&$cipher[1]!=='');
        $db->exec("SET SESSION time_zone='+00:00', MAX_EXECUTION_TIME=3000");
        $extensions=get_loaded_extensions();sort($extensions);
        commissionNeed(array_diff(['pdo_mysql','posix','curl','sockets','dom','fileinfo','mbstring','openssl'],array_map('strtolower',$extensions))===[]);
        $s=$db->prepare('SELECT :left_value + :right_value');$s->execute(['left_value'=>2,'right_value'=>3]);$sum=(int)$s->fetchColumn();
        $duplicate=0;
        try{$s=$db->prepare('SELECT :same_value + :same_value');$s->execute(['same_value'=>1]);}
        catch(PDOException $e){commissionNeed($e->getCode()==='HY093');$duplicate=1;}
        $f=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',1048576),1048576);
        $snapshot=static function()use($db,$f):array {
            $s=$db->prepare('SELECT id,current_deployment_id,active_deployment_id,pointer_version,fence_epoch FROM site_deployment_targets WHERE id=? AND site_id=?');
            $s->execute([$f['target_id'],$f['site_id']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
            $s=$db->prepare('SELECT COUNT(*) FROM site_deployments WHERE site_id=?');$s->execute([$f['site_id']]);
            return ['target'=>$rows,'operations'=>(int)$s->fetchColumn()];
        };
        $before=$snapshot();commissionNeed(count($before['target'])===1&&$before['operations']===0);
        $denied=[];
        foreach(['update','foreign'] as $negative) {
            try {
                // No row values and no successful mutation: the observer must lack
                // UPDATE even on a zero-row statement, and foreign SELECT privilege.
                $db->query($negative==='update'?'UPDATE sites SET lock_version=lock_version WHERE 1=0':
                    'SELECT id FROM `ubo_m6e_'.$a['run_id'].'_foreign`.canary');
                $denied[$negative]=0;
            } catch(PDOException $e) {$denied[$negative]=(int)($e->errorInfo[1]??0);}
        }
        $after=$snapshot();
        $out=['php_sha256'=>hash_file('sha256',realpath('/usr/bin/php')),
            'extensions_sha256'=>hash('sha256',CanonicalJson::encode($extensions)),
            'native_prepare_value'=>$sum,'duplicate_parameter_rejected'=>$duplicate,
            'utc_equal'=>(int)$db->query('SELECT NOW(6)=UTC_TIMESTAMP(6)')->fetchColumn(),
            'select_rows'=>count($before['target']),'update_errno'=>$denied['update'],'foreign_errno'=>$denied['foreign'],
            'before_sha256'=>hash('sha256',CanonicalJson::encode($before)),
            'after_sha256'=>hash('sha256',CanonicalJson::encode($after))];
        echo CanonicalJson::encode(['pid'=>getmypid(),'uid'=>posix_geteuid(),'data'=>$out])."\n";exit(0);
    } else {throw new RuntimeException('commission_mode');}
    $files=get_included_files();sort($files);
    echo CanonicalJson::encode(['pid'=>getmypid(),'uid'=>posix_geteuid(),'files'=>$files])."\n";
} catch(Throwable) {fwrite(STDERR,"m6e_commission_child_unresolved\n");exit(1);}
