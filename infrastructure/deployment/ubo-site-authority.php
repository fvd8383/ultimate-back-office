#!/usr/bin/php
<?php
declare(strict_types=1);
/** Fixed socket-activated, read-only authority responder. No application credential fallback. */
require_once '/usr/local/lib/ubo-site-publisher/php/SitePublisherHostContract.php';
require_once '/usr/local/lib/ubo-site-publisher/php/SitePublisherAuthority.php';
try {
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||count($argv)!==1||!extension_loaded('pdo_mysql')||!extension_loaded('sockets'))SitePublisherHostContract::deny();
    $config=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/etc/ubo-site-publisher/authority.json',16384),16384);
    SiteDeploymentContract::keys($config,['version','authority_uid','publisher_uid','dsn','credentials_sha256','ca_file','bindings']);
    if($config['version']!==2||posix_geteuid()!==$config['authority_uid']||$config['authority_uid']===$config['publisher_uid'])SitePublisherHostContract::deny();
    $receipt=SiteBuildContract::decode(SitePublisherHostContract::protectedFile(SitePublisherHostContract::RECEIPT,65536),65536);
    if($receipt['version']!==2||$receipt['profile']!=='m6e-host-v2'||$receipt['expires_at']<=time()||$receipt['verified_at']>time())SitePublisherHostContract::deny();
    foreach(array_merge(get_included_files(),['/etc/ubo-site-publisher/authority.json']) as $p) {
        $h=$receipt['installed_files'][$p]??null;
        if(!is_string($h)||hash('sha256',SitePublisherHostContract::protectedFile($p,2097152))!==$h)SitePublisherHostContract::deny();
    }
    if(!isset($receipt['installed_files']['/etc/ubo-site-publisher/authority.json']))SitePublisherHostContract::deny();
    // Socket pathname is protected by root-owned ancestors and 0600 publisher ownership.
    // PHP does not expose a portable struct ucred; do not misinterpret its integer socket option.
    $socketPath='/run/ubo-site-publisher/authority.sock';$s=lstat($socketPath);$parent=lstat(dirname($socketPath));
    if(($s['mode']&0170000)!==0140000||($s['mode']&0777)!==0600||$s['uid']!==$config['publisher_uid']
        ||$parent['uid']!==0||($parent['mode']&0022)!==0)SitePublisherHostContract::deny();
    foreach(get_included_files() as $path)if(!isset($receipt['installed_files'][$path]))SitePublisherHostContract::deny();
    stream_set_timeout(STDIN,5);$line=fgets(STDIN,16386);
    if($line===false||strlen($line)>16384||!str_ends_with($line,"\n"))SitePublisherHostContract::deny();
    $request=SiteBuildContract::decode($line,16384);$binding=null;
    foreach($config['bindings'] as $b)if($b['target_id']===$request['target']['target_id']&&$b['site_id']===$request['target']['site_id']) {
        if($binding!==null)SitePublisherHostContract::deny();$binding=$b;
    }
    if($binding===null||!str_starts_with($config['dsn'],'mysql:'))SitePublisherHostContract::deny();
    SitePublisherHostContract::protectedFile($config['ca_file'],1048576);
    $secretPath='/etc/ubo-site-publisher/authority-credentials.json';
    $secret=SitePublisherHostContract::protectedFile($secretPath,8192);
    if(hash('sha256',$secret)!==$config['credentials_sha256']||(fileperms($secretPath)&0007)!==0)SitePublisherHostContract::deny();
    $credentials=SiteBuildContract::decode($secret,8192);SiteDeploymentContract::keys($credentials,['username','password']);
    $db=new PDO($config['dsn'],$credentials['username'],$credentials['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3,PDO::MYSQL_ATTR_SSL_CA=>$config['ca_file'],PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true]);
    $db->exec("SET SESSION time_zone = '+00:00'");
    $db->exec('SET SESSION MAX_EXECUTION_TIME=3000');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try{$e=SitePublisherAuthority::observe($db,$request,$binding);$db->commit();}
    catch(Throwable $primary){try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}throw $primary;}
    // Never return a snapshot when commit/read completion is uncertain.
    $out=CanonicalJson::encode(['version'=>1,'sequence'=>$request['sequence'],'result'=>'ok','evidence'=>$e])."\n";
    if(strlen($out)>1048576||fwrite(STDOUT,$out)!==strlen($out))exit(1);
}catch(Throwable){fwrite(STDERR,"publisher_authority_unavailable\n");exit(1);}
