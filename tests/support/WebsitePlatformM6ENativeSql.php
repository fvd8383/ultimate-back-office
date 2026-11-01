<?php
declare(strict_types=1);
/** Actual independent disposable MySQL observations; only fixed, scoped fixture operations. */
require_once __DIR__.'/WebsitePlatformM6ENativeSetup.php';

function m6eSqlConnect(array $a,string $role):PDO{
    m6eNeed(in_array($role,['writer','authority'],true)&&posix_geteuid()===($role==='writer'?2106:2103),'sql_identity');
    $path=$role==='writer'?'/run/ubo-m6e-fixture/writer.json':'/etc/ubo-site-publisher/authority-credentials.json';
    $secret=json_decode(SitePublisherHostContract::protectedFile($path,8192),true,16,JSON_THROW_ON_ERROR);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=ubo_m6e_'.$a['run_id'].';charset=utf8mb4',$secret['username'],$secret['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
         PDO::ATTR_TIMEOUT=>3,PDO::MYSQL_ATTR_SSL_CA=>'/etc/ubo-site-publisher/tls/ca.pem',PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true]);
    $db->exec("SET SESSION time_zone='+00:00', innodb_lock_wait_timeout=5");
    $identity=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/database-identity.json',8192),true,16,JSON_THROW_ON_ERROR);
    m6eNeed($db->query('SELECT DATABASE()')->fetchColumn()===$identity['schema']&&$db->query('SELECT @@server_uuid')->fetchColumn()===$identity['server_uuid']
        &&str_starts_with((string)$db->query('SELECT CURRENT_USER()')->fetchColumn(),($role==='writer'?'m6e_writer':'m6e_observer').'@')
        &&$db->getAttribute(PDO::ATTR_EMULATE_PREPARES)===false,'sql_binding');
    $tls=$db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch();m6eNeed($tls['Value']!=='','sql_tls');
    return $db;
}
function m6eSqlSnapshot(PDO $db,array $f):array{
    $query=static function(string $sql,array $args)use($db):array{$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll();};
    $site=$f['fixture']['site_id'];$target=$f['target_id'];
    $t=$query('SELECT id,current_deployment_id,active_deployment_id,pointer_version,fence_epoch,reconciliation_status FROM site_deployment_targets WHERE id=? AND site_id=?',[$target,$site]);
    $ops=$query('SELECT id,status,current_attempt_id,execution_count,recovery_count,activation_fence,expected_current_deployment_id FROM site_deployments WHERE site_id=? AND target_id=? ORDER BY id',[$site,$target]);
    $attempts=$query('SELECT id,deployment_id,attempt_kind,status,fence_epoch,recovery_of_attempt_id,lease_expires_at,deadline_at FROM site_deployment_attempts WHERE site_id=? ORDER BY id',[$site]);
    $health=$query('SELECT deployment_id,phase,result,checked_at FROM site_deployment_health_checks WHERE site_id=? ORDER BY id',[$site]);
    return ['target'=>$t,'operations'=>$ops,'attempts'=>$attempts,'health'=>$health];
}
try{
    $a=m6eBootstrap();m6eNeed(count($argv)===2,'sql_arguments');$mode=$argv[1];
    m6eNeed(in_array($mode,['snapshot','select-only','foreign-schema','revoke','restore-requester','clear-slot','restore-slot','expiry','external-transactions'],true),'sql_mode');
    $role=in_array($mode,['select-only','foreign-schema'],true)?'authority':'writer';$db=m6eSqlConnect($a,$role);
    $f=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',2097152),true,64,JSON_THROW_ON_ERROR);
    $before=m6eSqlSnapshot($db,$f);$out=['pid'=>getmypid(),'uid'=>posix_geteuid(),'connection_id'=>(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn(),
        'before_sha256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR)),'fault'=>'none','errno'=>0];
    if(in_array($mode,['select-only','foreign-schema'],true)){
        // The successful scoped SELECT baseline precedes each permission probe.
        m6eNeed(count($before['target'])===1,'sql_positive');
        try{
            if($mode==='select-only'){$s=$db->prepare('UPDATE sites SET lock_version=lock_version WHERE id=?');$s->execute([$f['fixture']['site_id']]);}
            else $db->query('SELECT id FROM `ubo_m6e_'.$a['run_id'].'_foreign`.canary');
            throw new RuntimeException('sql_permission_not_denied');
        }catch(PDOException $error){$out['errno']=(int)($error->errorInfo[1]??0);m6eNeed(in_array($out['errno'],[1044,1142],true),'sql_denial_reason');}
        $out['fault']=$mode;
    }elseif(in_array($mode,['revoke','restore-requester'],true)){
        $s=$db->prepare('UPDATE users SET status=? WHERE id=?');$s->execute([$mode==='revoke'?'inactive':'active',$f['fixture']['requester']]);
        m6eNeed($s->rowCount()===1,'requester_mutation_reached');$out['fault']=$mode;
    }elseif(in_array($mode,['clear-slot','restore-slot'],true)){
        $file='/run/ubo-m6e-fixture/writer/slot.json';
        if($mode==='clear-slot'){
            m6eNeed($before['target'][0]['active_deployment_id']!==null,'occupied_baseline');
            $h=fopen($file,'xb');m6eNeed(is_resource($h),'slot_ledger');$raw=json_encode($before['target'][0],JSON_THROW_ON_ERROR);
            try{m6eNeed(fwrite($h,$raw)===strlen($raw)&&fflush($h)&&fsync($h),'slot_ledger_write');}finally{fclose($h);}chmod($file,0600);
            $s=$db->prepare('UPDATE site_deployment_targets SET active_deployment_id=NULL WHERE id=? AND site_id=?');$s->execute([$f['target_id'],$f['fixture']['site_id']]);
        }else{
            $old=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
            m6eNeed($before['target'][0]['active_deployment_id']===null&&$old['id']===$before['target'][0]['id'],'slot_restore_identity');
            $s=$db->prepare('UPDATE site_deployment_targets SET active_deployment_id=? WHERE id=? AND site_id=?');
            $s->execute([$old['active_deployment_id'],$f['target_id'],$f['fixture']['site_id']]);unlink($file);
        }
        m6eNeed($s->rowCount()===1,'slot_mutation_reached');$out['fault']=$mode;
    }elseif($mode==='expiry'){
        $last=end($before['attempts']);m6eNeed(is_array($last),'expiry_attempt');
        $s=$db->prepare('SELECT UTC_TIMESTAMP(6) AS now, lease_expires_at, UTC_TIMESTAMP(6)>=lease_expires_at AS expired FROM site_deployment_attempts WHERE id=? AND site_id=?');
        $s->execute([$last['id'],$f['fixture']['site_id']]);$out['clock']=$s->fetch(); // Actual server clock; no metadata expiry rewrite.
    }elseif($mode==='external-transactions'){
        // Independent observer sees worker connection(s), not a fabricated guard counter.
        $s=$db->query("SELECT COUNT(*) FROM information_schema.innodb_trx t JOIN information_schema.processlist p ON p.ID=t.trx_mysql_thread_id WHERE p.USER='m6e_worker'");
        $out['open_worker_transactions']=(int)$s->fetchColumn();
    }
    $after=m6eSqlSnapshot($db,$f);$out['after_sha256']=hash('sha256',json_encode($after,JSON_THROW_ON_ERROR));
    if(in_array($mode,['select-only','foreign-schema'],true))m6eNeed($before===$after,'readonly_no_effect');
    $out['state']=$after;echo json_encode($out,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable){fwrite(STDERR,"m6e_native_sql_unresolved\n");exit(1);}
