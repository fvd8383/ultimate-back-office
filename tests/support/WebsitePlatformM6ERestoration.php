<?php
declare(strict_types=1);
/** Disposable fixture restoration only. No application factory, deployment or admission entry. */
require_once __DIR__.'/WebsitePlatformM6ENativeSetup.php';
require_once __DIR__.'/WebsitePlatformM6ESnapshot.php';
require_once __DIR__.'/WebsitePlatformM6BMigrations.php';

const M6E_RESTORE_UID=2107;
const M6E_RESTORE_RIGHTS=['SELECT','INSERT','UPDATE','DELETE','CREATE','DROP','REFERENCES','INDEX','ALTER'];
final class M6ERestorationFailure extends RuntimeException {
    public function __construct(public readonly array $diagnostic){parent::__construct('restoration_incomplete');}
}

function m6eRestoreDestination(array $a,array $cp,array $snapshot):array {
    $binding=array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id']));
    m6eNeed(CanonicalJson::encode($cp['binding']??null)===CanonicalJson::encode($binding)
        &&CanonicalJson::encode($snapshot['binding']??null)===CanonicalJson::encode($binding),'restore_binding');
    $destination=m6eLifecycleBinding($a['run_id']);
    m6eNeed(CanonicalJson::encode($cp['lifecycle'])===CanonicalJson::encode($destination)&&$cp['deployment_authority']==='NONE'
        &&$snapshot['deployment_authority']==='NONE'&&$snapshot['restore']===$snapshot['admission']
        &&$snapshot['restore']==='UNIMPLEMENTED','restore_authority');
    m6eNeed($snapshot['database']['schema']==='ubo_m6e_'.$a['run_id']
        &&$snapshot['database']['schema']===$cp['observed']['database']['schema']
        &&$snapshot['database']['server_uuid']===$cp['observed']['database']['server_uuid'],'restore_source');
    return $destination;
}
function m6eRestoreGrants(array $grants,string $schema,string $user,bool $writer):void {
    $account='`'.$user.'`@`127.0.0.1`';$target='`'.str_replace('_','\\_',$schema).'`.*';$found=false;
    m6eNeed(count($grants)===count(array_unique($grants)),'restore_duplicate_grant');
    foreach($grants as $grant){
        if($grant==='GRANT USAGE ON *.* TO '.$account)continue;
        $suffix=' ON '.$target.' TO '.$account;
        m6eNeed(!$found&&str_starts_with($grant,'GRANT ')&&str_ends_with($grant,$suffix),'restore_grant_scope');
        $rights=explode(', ',substr($grant,6,-strlen($suffix)));$expected=$writer?M6E_RESTORE_RIGHTS:['SELECT'];
        sort($rights);sort($expected);m6eNeed($rights===$expected,'restore_grant_rights');$found=true;
    }
    m6eNeed($found,'restore_grant_missing');
}
function m6eRestoreAdmit(PDO $db,array $expected,bool $writer):array {
    m6eNeed(!$db->inTransaction()&&m6eCheckpointDatabase($db)===$expected,'restore_database');
    m6eNeed(str_starts_with((string)m6eSnapshotScalar($db,'SELECT VERSION()'),'8.4.')
        &&m6eSnapshotScalar($db,'SELECT CURRENT_ROLE()')==='NONE','restore_server_role');
    m6eNeed((m6eSnapshotRows($db,"SHOW SESSION STATUS LIKE 'Ssl_cipher'")[0]['Value']??'')!=='','restore_tls');
    $grants=array_map(static fn($r)=>array_values($r)[0],m6eSnapshotRows($db,'SHOW GRANTS'));
    m6eRestoreGrants($grants,$expected['schema'],explode('@',$expected['account'])[0],$writer);
    return $expected+['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()'),'grants_sha256'=>CanonicalJson::hash($grants)];
}
function m6eRestoreSession(PDO $db):void {
    foreach(["SET SESSION time_zone='+00:00'",'SET SESSION lock_wait_timeout=5','SET SESSION innodb_lock_wait_timeout=5',
        'SET SESSION max_execution_time=5000',"SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION'",
        'SET SESSION foreign_key_checks=1'] as $sql)$db->exec($sql);
}
function m6eRestoreCounter(string $ddl):?string {
    if(!preg_match('/\) ENGINE=InnoDB AUTO_INCREMENT=([1-9][0-9]{0,19}) /D',$ddl,$m)){
        m6eNeed(!preg_match('/\) ENGINE=InnoDB AUTO_INCREMENT=/',$ddl),'restore_counter');return null;
    }
    // Counters stay decimal strings, including BIGINT UNSIGNED values above PHP_INT_MAX.
    m6eNeed(strlen($m[1])<20||strcmp($m[1],'18446744073709551615')<=0,'restore_counter_bound');return $m[1];
}
/** The snapshot is DATA, never executable DDL. Only canonical migrations establish the schema. */
function m6eRestoreData(PDO $db,array $a,array $baseline,array $cp,array $snapshot,callable $migrate,callable $pulse,array &$outcome):array {
    $outcome=['result'=>'INCOMPLETE','phase'=>'admission','first_failure'=>null,'secondary'=>[],'transaction'=>'UNREACHED'];
    $primary=null;$transaction=false;$checksOff=false;
    try {
        $outcome['phase']='binding';$destination=m6eRestoreDestination($a,$cp,$snapshot);$pulse();$outcome['phase']='identity';
        $identity=m6eRestoreAdmit($db,['schema'=>$destination['schema'],'server_uuid'=>$snapshot['database']['server_uuid'],
            'account'=>$destination['worker'].'@127.0.0.1'],true);
        $outcome['phase']='inputs';m6eNeed($baseline['migrations']===m6eSnapshotMigrations()&&$snapshot['schema_sha256']===CanonicalJson::hash($baseline)
            &&$snapshot['checkpoint_sha256']===CanonicalJson::hash($cp),'restore_inputs');
        $names=array_keys($snapshot['tables']);sort($names);m6eNeed($names===m6eSnapshotProfile()['tables'],'restore_table_inventory');
        $outcome['phase']='empty-destination';m6eNeed(m6eSnapshotRows($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$destination['schema']])===[],'restore_empty_destination');
        $outcome['phase']='session';m6eRestoreSession($db);
        $outcome['phase']='ddl';$migrate($db);$pulse();m6eNeed(!$db->inTransaction(),'restore_ddl_boundary');
        $schema=m6eSnapshotSchema($db,$destination['schema'],$pulse);
        m6eNeed(CanonicalJson::encode($schema)===CanonicalJson::encode($baseline['tables']),'restore_schema');
        $columns=[];$auto=[];
        foreach($schema as $name=>$definition){
            $meta=m6eSnapshotRows($db,'SHOW FULL COLUMNS FROM '.m6eSnapshotName($name));$columns[$name]=[];$auto[$name]=false;
            foreach($meta as $column){
                if(str_contains($column['Extra'],'auto_increment'))$auto[$name]=true;
                // Defaults and ON UPDATE expressions are not invoked for restored stored columns.
                if(preg_match('/\b(?:STORED|VIRTUAL) GENERATED\b/',$column['Extra']))continue;
                $type=strtolower($column['Type']);$charset=$column['Collation']===null?null:explode('_',$column['Collation'])[0];
                m6eNeed($charset===null||in_array($charset,['utf8mb4','ascii'],true),'restore_charset');
                m6eNeed(preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|numeric|date|datetime|timestamp|time|year|char|varchar|tinytext|text|mediumtext|longtext|binary|varbinary|tinyblob|blob|mediumblob|longblob|json|enum|set)\b/',$type)===1,'restore_column_type');
                $columns[$name][$column['Field']]=$type==='json'?'CONVERT(UNHEX(?) USING utf8mb4)':($charset===null?'UNHEX(?)':'CONVERT(UNHEX(?) USING '.$charset.')');
            }
            m6eNeed($columns[$name]!==[],'restore_stored_columns');
        }
        // One transaction removes ONLY this newly migrated destination's seeds and imports all rows.
        // Cyclic references require relaxed insertion order. This is NOT relational verification.
        $outcome['phase']='seed-reconciliation';$db->exec('SET SESSION foreign_key_checks=0');$checksOff=true;
        $outcome['transaction']='UNKNOWN';m6eNeed($db->beginTransaction(),'restore_begin');$transaction=true;$outcome['transaction']='OPEN';
        foreach($schema as $name=>$definition){$pulse();$db->exec('DELETE FROM '.m6eSnapshotName($name));}
        foreach($schema as $name=>$definition)m6eNeed((int)m6eSnapshotScalar($db,'SELECT COUNT(*) FROM '.m6eSnapshotName($name))===0,'restore_seed_remaining');
        $outcome['phase']='rows';$total=0;
        foreach($schema as $name=>$definition){
            $pulse();$table=$snapshot['tables'][$name];m6eNeed($table['columns']===$definition['columns']
                &&$table['row_count']===count($table['rows'])&&$table['row_count']<=m6eSnapshotProfile()['max_rows_per_table']
                &&$table['rows_sha256']===CanonicalJson::hash($table['rows']),'restore_rows');
            $names=array_keys($columns[$name]);$offsets=array_map(static fn($c)=>array_search($c,$table['columns'],true),$names);
            $statement=$db->prepare('INSERT INTO '.m6eSnapshotName($name).' ('.implode(',',array_map('m6eSnapshotName',$names)).') VALUES ('.implode(',',$columns[$name]).')');
            try {foreach($table['rows'] as $row){
                $pulse();m6eNeed($db->inTransaction()&&count($row)===count($table['columns']),'restore_transaction_or_shape');
                foreach($row as $cell)m6eNeed($cell===null||(is_string($cell)&&strlen($cell)<=524288&&preg_match('/^(?:[0-9A-F]{2})*$/D',$cell)===1),'restore_cell');
                $statement->execute(array_map(static fn($i)=>$row[$i],$offsets));
                m6eNeed(m6eSnapshotRows($db,'SHOW WARNINGS')===[],'restore_value_warning');
                m6eNeed(++$total<=m6eSnapshotProfile()['max_rows'],'restore_row_bound');
            }} finally {$statement->closeCursor();}
        }
        $outcome['phase']='commit';$outcome['transaction']='UNKNOWN';m6eNeed($db->commit(),'restore_commit');$transaction=false;$outcome['transaction']='COMMITTED';
        $db->exec('SET SESSION foreign_key_checks=1');$checksOff=false;
        // AUTO_INCREMENT is DDL and cannot be part of the data transaction or its rollback claim.
        $outcome['phase']='counters';
        foreach($snapshot['tables'] as $name=>$table){$pulse();$counter=m6eRestoreCounter($table['create_table']);
            m6eNeed($counter===null||$auto[$name],'restore_counter_column');
            if($counter===null&&$auto[$name])$counter='1';
            if($counter!==null)$db->exec('ALTER TABLE '.m6eSnapshotName($name).' AUTO_INCREMENT='.$counter);}
        $outcome['phase']='worker-finished';$outcome['result']='AWAITING_INDEPENDENT_VERIFICATION';return $identity;
    } catch(Throwable $error){$primary=$error;$outcome['first_failure']='restore_'.$outcome['phase'];throw new RuntimeException('restore_incomplete');}
    finally {
        if($transaction||$db->inTransaction())try{m6eNeed($db->rollBack(),'restore_rollback');if($outcome['transaction']!=='UNKNOWN')$outcome['transaction']='ROLLED_BACK';}
            catch(Throwable){$outcome['secondary'][]='restore_rollback';}
        if($checksOff)try{$db->exec('SET SESSION foreign_key_checks=1');}catch(Throwable){$outcome['secondary'][]='restore_fk_reset';}
        if($primary!==null)$outcome['result']='INCOMPLETE';
    }
}

/** Fresh nonlocking SELECTs on a separate SELECT-only account; all values include generated columns. */
function m6eRestoreRead(PDO $db,string $schema,callable $pulse):array {
    m6eNeed(!$db->inTransaction()&&m6eSnapshotScalar($db,'SELECT DATABASE()')===$schema,'restore_read_schema');
    $db->exec("SET SESSION time_zone='+00:00'");$db->exec('SET SESSION max_execution_time=5000');
    $db->exec('SET SESSION lock_wait_timeout=5');$db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $result=[];$bytes=0;$count=0;
    $failure=null;$diagnostic=['phase'=>'read','first_failure'=>null,'secondary'=>[]];
    try {
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        m6eNeed($db->inTransaction()&&m6eSnapshotRows($db,'SHOW WARNINGS')===[],'restore_read_boundary');
        $definitions=m6eSnapshotSchema($db,$schema,$pulse);
        foreach($definitions as $name=>$definition){
            $pulse();$quoted=m6eSnapshotName($name);$cols=$definition['columns'];
            $sizes=array_map(static fn($c)=>'COALESCE(OCTET_LENGTH(CAST('.m6eSnapshotName($c).' AS BINARY)),0)',$cols);
            $size=m6eSnapshotRows($db,'SELECT COALESCE(SUM('.implode('+',$sizes).'),0) AS bytes, COALESCE(MAX(GREATEST('.implode(',',$sizes).')),0) AS cell FROM '.$quoted)[0];
            $bytes+=(int)$size['bytes'];m6eNeed($bytes<=1048576&&(int)$size['cell']<=262144,'restore_read_bytes');
            $rows=array_map('array_values',m6eSnapshotRows($db,'SELECT '.implode(',',array_map(static fn($c)=>'HEX(CAST('.m6eSnapshotName($c).' AS BINARY)) AS '.m6eSnapshotName($c),$cols))
                .' FROM '.$quoted.' ORDER BY '.implode(',',array_map('m6eSnapshotName',$definition['primary'])).' LIMIT 2049'));
            $count+=count($rows);m6eNeed(count($rows)<=2048&&$count<=8192,'restore_read_count');
            foreach($definition['foreign_keys'] as $fk){
                $notNull=[];$join=[];
                foreach($fk['columns'] as $i=>$col){$notNull[]='c.'.m6eSnapshotName($col).' IS NOT NULL';$join[]='p.'.m6eSnapshotName($fk['references'][$i]).'=c.'.m6eSnapshotName($col);}
                $sql='SELECT COUNT(*) FROM '.$quoted.' c WHERE '.implode(' AND ',$notNull).' AND NOT EXISTS (SELECT 1 FROM '.m6eSnapshotName($fk['table']).' p WHERE '.implode(' AND ',$join).')';
                m6eNeed((int)m6eSnapshotScalar($db,$sql)===0,'restore_foreign_closure');
            }
            $result[$name]=['columns'=>$cols,'rows'=>$rows,'row_count'=>count($rows),'rows_sha256'=>CanonicalJson::hash($rows),
                'create_table'=>m6eSnapshotRows($db,'SHOW CREATE TABLE '.$quoted)[0]['Create Table']];
        }
        $diagnostic['phase']='read-commit';m6eNeed($db->commit(),'restore_read_commit');
    } catch(Throwable $error){$failure=$error;$diagnostic['first_failure']='restore_read';}
    finally {if($db->inTransaction())try{m6eNeed($db->rollBack(),'restore_read_rollback');}
        catch(Throwable){$diagnostic['secondary'][]='restore_read_rollback';$failure??=new RuntimeException('restore_read_rollback');}}
    if($failure!==null)throw new M6ERestorationFailure($diagnostic);
    $database=m6eSnapshotRows($db,'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$schema]);
    m6eNeed($database===[['DEFAULT_CHARACTER_SET_NAME'=>'utf8mb4','DEFAULT_COLLATION_NAME'=>'utf8mb4_unicode_ci']],'restore_database_collation');
    return ['definitions'=>$definitions,'tables'=>$result,'collation'=>'utf8mb4_unicode_ci'];
}

function m6eRestoreInputs(array $a):array {
    $root=m6eLifecycleBinding($a['run_id'])['control_root'];$values=[];$raw=[];
    foreach(['request'=>65536,'checkpoint'=>2097152,'snapshot'=>4194304,'schema'=>1048576] as $name=>$limit){
        $raw[$name]=SitePublisherHostContract::protectedFile($root.'/'.$name.'.json',$limit);
        $values[$name]=json_decode($raw[$name],true,128,JSON_THROW_ON_ERROR);
    }
    $d=m6eRestoreDestination($a,$values['checkpoint'],$values['snapshot']);$r=$values['request'];
    m6eNeed($r['profile']==='m6e-pre-b-restoration-v1'&&CanonicalJson::encode($r['destination'])===CanonicalJson::encode($d)
        &&CanonicalJson::encode($r['binding'])===CanonicalJson::encode($values['checkpoint']['binding'])
        &&$r['server_uuid']===$values['snapshot']['database']['server_uuid']&&$r['expires_at']===$a['expires_at'],'restore_request');
    foreach(['checkpoint','snapshot','schema'] as $name)m6eNeed($r[$name.'_sha256']===hash('sha256',$raw[$name]),'restore_input_digest');
    m6eNeed($values['schema']['migrations']===m6eSnapshotMigrations(),'restore_migration_source');return $values;
}
function m6eRestoreWrite(array $a,string $leaf,array $value,array &$secondary=[]):void {
    $d=m6eLifecycleBinding($a['run_id']);$worker=$leaf==='worker/outcome.json';
    m6eNeed(in_array($leaf,['worker-credentials.json','observer-credentials.json','worker/outcome.json','observation.json'],true),'restore_output');
    $path=($leaf==='observation.json'?$d['storage_root']:$d['control_root']).'/'.$leaf;
    $uid=$worker?M6E_RESTORE_UID:0;m6eNeed(posix_geteuid()===$uid,'restore_output_uid');
    foreach([dirname($path),dirname(dirname($path))] as $parent){$s=lstat($parent);
        m6eNeed($s!==false&&($s['mode']&0170000)===0040000&&($s['mode']&0022)===0
            &&$s['uid']===($worker&&$parent===dirname($path)?M6E_RESTORE_UID:0),'restore_output_parent');}
    $raw=CanonicalJson::encode($value);m6eNeed(strlen($raw)<=4194304,'restore_output_bound');
    $h=fopen($path,'xb');m6eNeed(is_resource($h),'restore_exclusive');
    $primary=null;
    try {
        $id=fstat($h);m6eNeed($id['uid']===$uid&&$id['nlink']===1,'restore_created_identity');
        m6eNeed(chmod($path,0600),'restore_output_mode');
        if($leaf==='worker-credentials.json')m6eNeed(chgrp($path,M6E_RESTORE_UID)&&chmod($path,0640),'restore_credentials_mode');
        for($offset=0;$offset<strlen($raw);){$n=fwrite($h,substr($raw,$offset,65536));m6eNeed(is_int($n)&&$n>0,'restore_write');$offset+=$n;}
        m6eNeed(fflush($h)&&fsync($h),'restore_file_sync');clearstatcache(true,$path);$named=lstat($path);
        m6eNeed($named['dev']===$id['dev']&&$named['ino']===$id['ino']&&$named['nlink']===1&&file_get_contents($path)===$raw,'restore_readback');
    } catch(Throwable $error){$primary=$error;throw $error;}
    finally {try{m6eNeed(fclose($h),'restore_close');}catch(Throwable $error){if($primary===null)throw $error;$secondary[]='restore_close';}}
    $dir=fopen(dirname($path),'r');m6eNeed(is_resource($dir),'restore_directory');
    try{m6eNeed(fsync($dir),'restore_directory_sync');}
    catch(Throwable $error){$primary=$error;throw $error;}
    finally{try{m6eNeed(fclose($dir),'restore_directory_close');}catch(Throwable $error){if($primary===null)throw $error;$secondary[]='restore_directory_close';}}
}
function m6eRestoreConnect(array $a,array $inputs,string $role):PDO {
    $d=$inputs['request']['destination'];
    if($role==='admin'){
        $credentials=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/admin.json',8192),true,16,JSON_THROW_ON_ERROR);
        $db=new PDO('mysql:unix_socket=/var/lib/ubo-m6e-fixture/mysql-run/mysql.sock;charset=utf8mb4','root',$credentials['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true]);
        m6eNeed(m6eSnapshotScalar($db,'SELECT CURRENT_USER()')==='root@localhost','restore_admin');
    } else {
        m6eNeed(in_array($role,['worker','observer','source'],true),'restore_connection_role');
        $path=$role==='source'?'/etc/ubo-site-publisher/authority-credentials.json':$d['control_root'].'/'.$role.'-credentials.json';
        $credentials=json_decode(SitePublisherHostContract::protectedFile($path,8192),true,16,JSON_THROW_ON_ERROR);
        $schema=$role==='source'?'ubo_m6e_'.$a['run_id']:$d['schema'];
        m6eNeed($credentials['username']===($role==='source'?'m6e_observer':$d[$role]),'restore_credentials');
        $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$schema.';charset=utf8mb4',$credentials['username'],$credentials['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_SSL_CA=>'/etc/ubo-site-publisher/tls/ca.pem']);
        m6eNeed(m6eCheckpointDatabase($db)===['schema'=>$schema,'server_uuid'=>$inputs['request']['server_uuid'],
            'account'=>$credentials['username'].'@127.0.0.1'],'restore_connection_identity');
    }
    m6eNeed(m6eSnapshotScalar($db,'SELECT @@server_uuid')===$inputs['request']['server_uuid']
        &&str_starts_with((string)m6eSnapshotScalar($db,'SELECT VERSION()'),'8.4.'),'restore_instance');return $db;
}
function m6eRestoreCreate(PDO $db,array $a,array $inputs,?callable $write=null):array {
    $write??=static fn(string $leaf,array $value)=>m6eRestoreWrite($a,$leaf,$value);
    $d=$inputs['request']['destination'];
    m6eNeed(m6eSnapshotRows($db,'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$d['schema']])===[],'restore_destination_exists');
    m6eNeed(m6eSnapshotRows($db,'SELECT User FROM mysql.user WHERE User IN (?,?)',[$d['worker'],$d['observer']])===[],'restore_account_exists');
    $db->exec('CREATE DATABASE '.m6eSnapshotName($d['schema']).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach(['worker','observer'] as $role){
        $user=$d[$role];$secret=bin2hex(random_bytes(32));$account=$db->quote($user)."@'127.0.0.1'";
        $db->exec('CREATE USER '.$account.' IDENTIFIED BY '.$db->quote($secret).' REQUIRE SSL');
        $rights=$role==='worker'?implode(', ',M6E_RESTORE_RIGHTS):'SELECT';
        $db->exec('GRANT '.$rights.' ON `'.str_replace('_','\\_',$d['schema']).'`.* TO '.$account);
        $write($role.'-credentials.json',['username'=>$user,'password'=>$secret]);
    }
    return ['schema'=>$d['schema'],'server_uuid'=>$inputs['request']['server_uuid'],'created'=>'EXCLUSIVE'];
}
function m6eRestoreSeal(PDO $db,array $inputs):array {
    $d=$inputs['request']['destination'];$result=[];
    // The bounded writer child has exited. Locking the account prevents all later connections.
    $db->exec("ALTER USER 'm6e_lifecycle_worker'@'127.0.0.1' ACCOUNT LOCK");
    m6eNeed((int)m6eSnapshotScalar($db,"SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='m6e_lifecycle_worker'")===0,'restore_worker_still_connected');
    foreach(['worker','observer'] as $role){
        $user=$d[$role];$grants=array_map(static fn($r)=>array_values($r)[0],m6eSnapshotRows($db,'SHOW GRANTS FOR '.$db->quote($user)."@'127.0.0.1'"));
        m6eRestoreGrants($grants,$d['schema'],$user,$role==='worker');
        $row=m6eSnapshotRows($db,"SELECT account_locked, ssl_type FROM mysql.user WHERE User=? AND Host='127.0.0.1'",[$user]);
        m6eNeed($row===[['account_locked'=>$role==='worker'?'Y':'N','ssl_type'=>'ANY']],'restore_account_policy');
        $result[$role]=['account'=>$user.'@127.0.0.1','grants_sha256'=>CanonicalJson::hash($grants),'locked'=>$role==='worker'];
    }
    return $result;
}
function m6eRestoreNative(string $mode):array {
    $a=m6eBootstrap();m6eNeed(posix_geteuid()===($mode==='--restore'?M6E_RESTORE_UID:0),'restore_native_uid');
    $inputs=m6eRestoreInputs($a);$r=$inputs['request'];$deadline=hrtime(true)+120000000000;
    $pulse=static function()use($a,$deadline):void{m6eNeed(time()<$a['expires_at']&&hrtime(true)<$deadline,'restore_deadline');};$pulse();
    if($mode==='--prepare')return m6eRestoreCreate(m6eRestoreConnect($a,$inputs,'admin'),$a,$inputs);
    if($mode==='--seal')return m6eRestoreSeal(m6eRestoreConnect($a,$inputs,'admin'),$inputs);
    if($mode==='--source'){
        $db=m6eRestoreConnect($a,$inputs,'source');$read=m6eRestoreRead($db,'ubo_m6e_'.$a['run_id'],$pulse);
        return ['schema'=>'ubo_m6e_'.$a['run_id'],'server_uuid'=>$r['server_uuid'],'sha256'=>CanonicalJson::hash($read)];
    }
    if($mode==='--restore'){
        $outcome=['result'=>'INCOMPLETE','phase'=>'connection','first_failure'=>null,'secondary'=>[],'transaction'=>'UNREACHED'];$primary=null;
        try {
            $db=m6eRestoreConnect($a,$inputs,'worker');
            $identity=m6eRestoreData($db,$a,$inputs['schema'],$inputs['checkpoint'],$inputs['snapshot'],
                static function(PDO $db):void{ob_start();try{m6mysqlMigrate($db,1,25);}finally{ob_end_clean();}},$pulse,$outcome);
            $outcome['identity']=$identity;
        } catch(Throwable $e){$primary=$e;$outcome['result']='INCOMPLETE';$outcome['first_failure']??='restore_connection';}
        finally {
            $db=null;
            try{m6eRestoreWrite($a,'worker/outcome.json',$outcome,$outcome['secondary']);}
            catch(Throwable){if($primary===null){$primary=new RuntimeException('restore_outcome_write');$outcome['first_failure']='restore_outcome_write';}
                else $outcome['secondary'][]='restore_outcome_write';$outcome['result']='INCOMPLETE';}
        }
        if($primary!==null)throw new M6ERestorationFailure(['phase'=>$outcome['phase'],'first_failure'=>$outcome['first_failure'],'secondary'=>$outcome['secondary']]);
        return ['result'=>'AWAITING_INDEPENDENT_VERIFICATION'];
    }
    m6eNeed($mode==='--verify','restore_mode');$db=m6eRestoreConnect($a,$inputs,'observer');$d=$r['destination'];
    $identity=m6eRestoreAdmit($db,['schema'=>$d['schema'],'server_uuid'=>$r['server_uuid'],'account'=>$d['observer'].'@127.0.0.1'],false);
    $read=m6eRestoreRead($db,$d['schema'],$pulse);
    m6eNeed(CanonicalJson::encode($read['definitions'])===CanonicalJson::encode($inputs['schema']['tables'])
        &&CanonicalJson::encode($read['tables'])===CanonicalJson::encode($inputs['snapshot']['tables']),'restore_destination_mismatch');
    $cp=$inputs['checkpoint'];$store=m6eArtifactStore(m6eBuildConfig($a));
    $artifact=m6eReleaseSummary($cp['observed']['release'],$cp['observed']['job'],$cp['observed']['producer'],
        $store->verifyRelease($cp['observed']['job'],$cp['observed']['producer'],$pulse));
    m6eNeed(CanonicalJson::encode($artifact)===CanonicalJson::encode($cp['summary']),'restore_original_artifact');$pulse();
    m6eRestoreWrite($a,'observation.json',['profile'=>'m6e-pre-b-restoration-observation-v1','binding'=>$r['binding'],
        'request_sha256'=>CanonicalJson::hash($r),'database'=>$identity,'read'=>$read,'artifact'=>$artifact,
        'deployment_authority'=>'NONE','admission'=>'UNIMPLEMENTED']);
    return ['result'=>'OBSERVED'];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try {
        m6eNeed(count($argv)===2&&in_array($argv[1],['--prepare','--restore','--seal','--source','--verify'],true),'restore_usage');
        echo CanonicalJson::encode(m6eRestoreNative($argv[1]))."\n";
    } catch(Throwable $error){
        $diagnostic=$error instanceof M6ERestorationFailure?$error->diagnostic:['phase'=>'native','first_failure'=>'restore_native','secondary'=>[]];
        echo CanonicalJson::encode(['result'=>'INCOMPLETE','diagnostic'=>$diagnostic])."\n";exit(1);
    }
}
