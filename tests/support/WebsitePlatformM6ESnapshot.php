<?php
declare(strict_types=1);
/** Fixture-only logical capture. No restore, deployment, lease or admission entrypoint. */
require_once __DIR__.'/WebsitePlatformM6ECheckpoint.php';

final class M6ESnapshotFailure extends RuntimeException {
    public function __construct(public readonly array $outcome){parent::__construct('snapshot_unresolved');}
}

function m6eSnapshotProfile():array {
    return json_decode(file_get_contents(__DIR__.'/WebsitePlatformM6ESnapshotProfile.json'),true,32,JSON_THROW_ON_ERROR);
}
function m6eSnapshotRows(PDO $db,string $sql,array $params=[]):array {
    $s=$db->prepare($sql);$s->execute($params);
    try{return $s->fetchAll(PDO::FETCH_ASSOC);}finally{$s->closeCursor();}
}
function m6eSnapshotScalar(PDO $db,string $sql,array $params=[]):mixed {
    $rows=m6eSnapshotRows($db,$sql,$params);m6eNeed(count($rows)===1,'snapshot_scalar');return array_values($rows[0])[0];
}
function m6eSnapshotName(string $name):string {
    m6eNeed(preg_match('/^[a-z0-9_]+$/D',$name)===1,'snapshot_identifier');return '`'.$name.'`';
}
function m6eSnapshotMigrations():array {
    $files=glob(dirname(__DIR__,2).'/database/migrations/*.sql');sort($files);m6eNeed(count($files)===25,'snapshot_migrations');$result=[];
    foreach($files as $i=>$path){m6eNeed(str_starts_with(basename($path),sprintf('%03d_',$i+1)),'snapshot_migration_order');
        $result['database/migrations/'.basename($path)]=hash_file('sha256',$path);}
    return $result;
}
/** Schema metadata is scoped to the fixed database; no user tables in another schema are read. */
function m6eSnapshotSchema(PDO $db,string $schema,callable $pulse):array {
    $tables=m6eSnapshotRows($db,'SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME',[$schema]);
    usort($tables,static fn($a,$b)=>strcmp($a['TABLE_NAME'],$b['TABLE_NAME']));
    m6eNeed(array_column($tables,'TABLE_NAME')===m6eSnapshotProfile()['tables'],'snapshot_table_inventory');$result=[];
    foreach($tables as $table){
        $pulse();$name=$table['TABLE_NAME'];$quoted=m6eSnapshotName($name);
        m6eNeed($table['ENGINE']==='InnoDB'&&$table['TABLE_TYPE']==='BASE TABLE','snapshot_engine');
        $columns=m6eSnapshotRows($db,'SHOW FULL COLUMNS FROM '.$quoted);
        $names=array_column($columns,'Field');m6eNeed(count($names)>0&&count($names)<=128,'snapshot_columns');
        // The immutable schema contains exact numeric/temporal/string/JSON values, no floating point.
        foreach($columns as $column)m6eNeed(!preg_match('/^(float|double|real|geometry|point|bit)\b/i',$column['Type']),'snapshot_column_type');
        $indexes=m6eSnapshotRows($db,'SHOW INDEX FROM '.$quoted);$primary=[];
        foreach($indexes as $index)if($index['Key_name']==='PRIMARY')$primary[(int)$index['Seq_in_index']]=$index['Column_name'];
        ksort($primary);m6eNeed($primary!==[],'snapshot_primary');
        $foreign=m6eSnapshotRows($db,'SELECT CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',[$schema,$name]);
        $fks=[];foreach($foreign as $f){
            m6eNeed($f['REFERENCED_TABLE_SCHEMA']===$schema&&in_array($f['REFERENCED_TABLE_NAME'],m6eSnapshotProfile()['tables'],true),'snapshot_foreign_schema');
            $key=$f['CONSTRAINT_NAME'];$fks[$key]??=['table'=>$f['REFERENCED_TABLE_NAME'],'columns'=>[],'references'=>[]];
            $fks[$key]['columns'][]=$f['COLUMN_NAME'];$fks[$key]['references'][]=$f['REFERENCED_COLUMN_NAME'];
        }
        $ddl=m6eSnapshotRows($db,'SHOW CREATE TABLE '.$quoted)[0]['Create Table'];
        m6eNeed(strlen($ddl)<=65536,'snapshot_ddl_bound');
        // Retain the full SHOW CREATE separately, but exclude the data-dependent counter from schema equality.
        $definition=preg_replace('/\) ENGINE=InnoDB AUTO_INCREMENT=[0-9]+ /',') ENGINE=InnoDB ',$ddl);
        $result[$name]=['columns'=>$names,'primary'=>array_values($primary),'foreign_keys'=>array_values($fks),'definition'=>$definition];
    }
    m6eNeed(strlen(CanonicalJson::encode($result))<1048576,'snapshot_schema_bound');return $result;
}
/** Called by the existing root schema producer immediately after canonical migrations, before fixture DML. */
function m6eSnapshotBaseline(PDO $db,array $a):void {
    m6eNeed(posix_geteuid()===0&&!$db->inTransaction(),'snapshot_baseline_owner');
    $schema='ubo_m6e_'.$a['run_id'];m6eNeed(m6eSnapshotScalar($db,'SELECT DATABASE()')===$schema,'snapshot_baseline_schema');
    $v=['profile'=>'m6e-pre-b-schema-v1','binding'=>array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id'])),
        'schema'=>$schema,'server_uuid'=>m6eSnapshotScalar($db,'SELECT @@server_uuid'),'migrations'=>m6eSnapshotMigrations(),
        'tables'=>m6eSnapshotSchema($db,$schema,static function():void {})];
    m6eWrite('/run/ubo-m6e-fixture/snapshot-schema.json',CanonicalJson::encode($v),2000,0640);
}
function m6eSnapshotAdmit(PDO $reader,array $identity,int $builderConnection):array {
    m6eNeed(!$reader->inTransaction()&&m6eCheckpointDatabase($reader)===$identity,'snapshot_reader_identity');
    $connection=(int)m6eSnapshotScalar($reader,'SELECT CONNECTION_ID()');
    m6eNeed($connection>0&&$connection!==$builderConnection,'snapshot_independent_reader');
    $version=m6eSnapshotScalar($reader,'SELECT VERSION()');m6eNeed(str_starts_with($version,'8.4.'),'snapshot_mysql_version');
    m6eNeed(m6eSnapshotScalar($reader,'SELECT CURRENT_ROLE()')==='NONE','snapshot_reader_roles');
    $tls=m6eSnapshotRows($reader,"SHOW SESSION STATUS LIKE 'Ssl_cipher'");m6eNeed(($tls[0]['Value']??'')!=='','snapshot_reader_tls');
    $grants=array_map(static fn($row)=>array_values($row)[0],m6eSnapshotRows($reader,'SHOW GRANTS'));
    $account='`m6e_snapshot`@`127.0.0.1`';$schema='`'.str_replace('_','\\_',$identity['schema']).'`';
    $required=['GRANT FLUSH_TABLES ON *.* TO '.$account,'GRANT SELECT ON '.$schema.'.* TO '.$account];
    $allowed=[...$required,'GRANT USAGE ON *.* TO '.$account];
    m6eNeed(count($grants)===count(array_unique($grants))&&array_diff($required,$grants)===[]&&array_diff($grants,$allowed)===[],'snapshot_reader_grants');
    return ['connection_id'=>$connection,'builder_connection_id'=>$builderConnection,'account'=>$identity['account'],'version'=>$version,
        'tls'=>true,'grants_sha256'=>CanonicalJson::hash($grants)];
}
/** Match full original rows, including inherited approvals, without re-running locking reads on the reader. */
function m6eSnapshotBindings(array $tables,array $observed):void {
    $expected=['sites'=>[$observed['source']['site']],'site_revisions'=>[$observed['source']['revision']],
        'site_releases'=>[$observed['release']],'site_build_jobs'=>[$observed['job']],'site_build_attempts'=>[$observed['producer']],
        'site_approvals'=>array_values($observed['approvals'])];
    foreach($expected as $table=>$rows)foreach($rows as $expectedRow){
        $cols=$tables[$table]['columns'];$id=array_search('id',$cols,true);$found=[];
        foreach($tables[$table]['rows'] as $row)if(hex2bin($row[$id])===(string)$expectedRow['id'])$found[]=$row;
        m6eNeed(count($found)===1,'snapshot_original_row');$row=$found[0];
        foreach($expectedRow as $key=>$value){$offset=array_search($key,$cols,true);m6eNeed($offset!==false,'snapshot_original_column');
            m6eNeed($row[$offset]===($value===null?null:strtoupper(bin2hex((string)$value))),'snapshot_original_value');}
    }
    foreach($observed['absence'] as $table=>$count)m6eNeed($count===0&&$tables[$table]['row_count']===0,'snapshot_pre_b_operations');
    $cols=$tables['site_revisions']['columns'];$site=array_search('site_id',$cols,true);$number=array_search('revision_number',$cols,true);
    foreach($tables['site_revisions']['rows'] as $row)m6eNeed((int)hex2bin($row[$site])!==(int)$observed['source']['site']['id']
        ||(int)hex2bin($row[$number])<=(int)$observed['source']['revision']['revision_number'],'snapshot_pre_b_revision');
}
/** One SQL read boundary. The observer callback must use a DIFFERENT, ordinary read/write session. */
function m6eCaptureSnapshot(PDO $reader,array $a,array $baseline,array $checkpoint,callable $observe,int $builderConnection,array &$outcome):array {
    $profile=m6eSnapshotProfile();$start=time();$deadline=hrtime(true)+$profile['max_seconds']*1000000000;
    $pulse=static function()use($deadline,$a):void {m6eNeed(hrtime(true)<$deadline&&time()<$a['expires_at'],'snapshot_deadline');};
    $outcome=['capture'=>'FAILED','verification'=>'UNREACHED','transaction_cleanup'=>'UNREACHED','first_failure'=>null,'secondary'=>[]];
    $locked=false;$transaction=false;$primary=null;$result=null;$stage='admission';
    try {
        $pulse();m6eNeed($checkpoint['database_snapshot']==='UNIMPLEMENTED'&&$checkpoint['deployment_authority']==='NONE','snapshot_checkpoint_authority');
        $binding=array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id']));
        m6eNeed($baseline['profile']==='m6e-pre-b-schema-v1'&&$baseline['binding']===$binding&&$checkpoint['binding']===$binding
            &&$checkpoint['expires_at']===$a['expires_at']&&$checkpoint['captured_at']<=$start,'snapshot_binding');
        $identity=['schema'=>'ubo_m6e_'.$a['run_id'],'server_uuid'=>$baseline['server_uuid'],'account'=>'m6e_snapshot@127.0.0.1'];
        m6eNeed($baseline['schema']===$identity['schema']&&$checkpoint['observed']['database']['schema']===$identity['schema']
            &&$checkpoint['observed']['database']['server_uuid']===$identity['server_uuid'],'snapshot_source_database');
        m6eNeed($baseline['migrations']===m6eSnapshotMigrations(),'snapshot_migration_identity');
        $readerInfo=m6eSnapshotAdmit($reader,$identity,$builderConnection);
        $stage='before';$before=$observe();m6eNeed(CanonicalJson::encode($before)===CanonicalJson::encode($checkpoint['observed']),'snapshot_source_changed');
        foreach(["SET SESSION time_zone='+00:00'",'SET SESSION lock_wait_timeout=5','SET SESSION max_execution_time=5000',
            'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ','SET SESSION TRANSACTION READ ONLY'] as $sql)$reader->exec($sql);
        $stage='lock';$reader->exec('FLUSH TABLES WITH READ LOCK');$locked=true;$pulse();
        $stage='schema';$schema=m6eSnapshotSchema($reader,$identity['schema'],$pulse);
        m6eNeed(CanonicalJson::encode($schema)===CanonicalJson::encode($baseline['tables']),'snapshot_schema_changed');
        $stage='transaction';$reader->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$transaction=true;
        m6eNeed($reader->inTransaction()&&m6eSnapshotRows($reader,'SHOW WARNINGS')===[],'snapshot_start');
        m6eNeed(m6eSnapshotScalar($reader,'SELECT @@session.transaction_isolation')==='REPEATABLE-READ'
            &&(int)m6eSnapshotScalar($reader,'SELECT @@session.transaction_read_only')===1,'snapshot_isolation');
        $tables=[];$totalRows=0;$totalBytes=0;$stage='rows';
        foreach($schema as $name=>$definition){
            $pulse();m6eNeed($reader->inTransaction(),'snapshot_transaction_lost');$quoted=m6eSnapshotName($name);$cols=$definition['columns'];
            $count=(int)m6eSnapshotScalar($reader,'SELECT COUNT(*) FROM '.$quoted);$totalRows+=$count;
            m6eNeed($count<=$profile['max_rows_per_table']&&$totalRows<=$profile['max_rows'],'snapshot_row_bound');
            $sizes=array_map(static fn($c)=>'COALESCE(OCTET_LENGTH(CAST('.m6eSnapshotName($c).' AS BINARY)),0)',$cols);
            $size=m6eSnapshotRows($reader,'SELECT COALESCE(SUM('.implode('+',$sizes).'),0) AS bytes, COALESCE(MAX(GREATEST('.implode(',',$sizes).')),0) AS cell FROM '.$quoted)[0];
            $totalBytes+=(int)$size['bytes'];m6eNeed($totalBytes<=$profile['max_value_bytes']&&(int)$size['cell']<=$profile['max_cell_bytes'],'snapshot_byte_bound');
            $sql='SELECT '.implode(',',array_map(static fn($c)=>'HEX(CAST('.m6eSnapshotName($c).' AS BINARY)) AS '.m6eSnapshotName($c),$cols))
                .' FROM '.$quoted.' ORDER BY '.implode(',',array_map('m6eSnapshotName',$definition['primary'])).' LIMIT '.($profile['max_rows_per_table']+1);
            $rows=array_map('array_values',m6eSnapshotRows($reader,$sql));m6eNeed(count($rows)===$count,'snapshot_row_count');
            $ddl=m6eSnapshotRows($reader,'SHOW CREATE TABLE '.$quoted)[0]['Create Table'];
            $tables[$name]=['columns'=>$cols,'rows'=>$rows,'row_count'=>$count,'rows_sha256'=>CanonicalJson::hash($rows),'create_table'=>$ddl];
        }
        m6eSnapshotBindings($tables,$before);
        // This existing validator uses ordinary SELECTs, unlike the locking eligibility gate.
        // Its snapshot hash binds all composition children/assets to the original source identity.
        $composition=SiteCompositionValidator::validateStoredRevision($reader,$before['source']['site'],$before['source']['revision'],SiteCompositionValidator::MODE_RENDER_READ);
        m6eNeed(CanonicalJson::encode($composition)===CanonicalJson::encode($before['source']['composition']),'snapshot_composition_changed');
        $pulse();$stage='commit';m6eNeed($reader->commit(),'snapshot_commit');$transaction=false;
        $stage='unlock';$reader->exec('UNLOCK TABLES');$locked=false;$outcome['transaction_cleanup']='RELEASED';
        $stage='after';$after=$observe();m6eNeed(CanonicalJson::encode($before)===CanonicalJson::encode($after),'snapshot_source_changed');$pulse();
        $result=['profile'=>$profile['profile'],'execution'=>$checkpoint['execution'],'binding'=>$binding,'database'=>$identity,
            'checkpoint_sha256'=>CanonicalJson::hash($checkpoint),'schema_sha256'=>CanonicalJson::hash($baseline),'reader'=>$readerInfo,
            'transaction'=>['isolation'=>'REPEATABLE READ','read_only'=>true,'consistent_snapshot'=>true,'ddl_guard'=>'GLOBAL READ LOCK','boundaries'=>1,'closed'=>true],
            'creation_point'=>$checkpoint['creation_point'],'started_at'=>$start,'captured_at'=>time(),'expires_at'=>$a['expires_at'],
            'tables'=>$tables,'deployment_authority'=>'NONE','restore'=>'UNIMPLEMENTED','admission'=>'UNIMPLEMENTED'];
        m6eNeed(strlen(CanonicalJson::encode($result))<=$profile['max_artifact_bytes'],'snapshot_serialization_bound');$outcome['capture']='CAPTURED';
    } catch(Throwable $error){$primary=$error;$outcome['first_failure']='snapshot_'.$stage;}
    finally {
        if($transaction)try{m6eNeed($reader->rollBack(),'snapshot_rollback');}catch(Throwable){$outcome['secondary'][]='snapshot_rollback';}
        if($locked)try{$reader->exec('UNLOCK TABLES');}catch(Throwable){$outcome['secondary'][]='snapshot_unlock';}
        if($outcome['transaction_cleanup']!=='RELEASED')$outcome['transaction_cleanup']=$outcome['secondary']===[]?'RELEASED':'UNRESOLVED';
    }
    if($primary!==null)throw $primary;return $result;
}
/** Exclusive leaf under the fixed private worker directory. Partial files remain owned for coordinator cleanup. */
function m6eSnapshotWrite(string $path,string $raw,array &$secondary=[]):array {
    m6eNeed(in_array($path,['/run/ubo-m6e-fixture/worker/pre-b-database.json','/run/ubo-m6e-fixture/worker/pre-b-database-outcome.json'],true),'snapshot_output_path');
    $parent=dirname($path);$s=lstat($parent);m6eNeed($s!==false&&($s['mode']&0177777)===0040700&&$s['uid']===$s['gid']&&$s['uid']===2000,'snapshot_output_parent');
    foreach(['/run','/run/ubo-m6e-fixture'] as $ancestor){$s=lstat($ancestor);
        m6eNeed($s!==false&&($s['mode']&0170000)===0040000&&($s['mode']&0022)===0&&$s['uid']===0,'snapshot_output_ancestor');}
    $h=fopen($path,'xb');m6eNeed(is_resource($h),'snapshot_exclusive');$primary=null;
    try {
        $opened=fstat($h);m6eNeed($opened['nlink']===1&&$opened['uid']===2000,'snapshot_created_identity');m6eNeed(chmod($path,0600),'snapshot_mode');
        for($at=0;$at<strlen($raw);){$n=fwrite($h,substr($raw,$at,65536));m6eNeed(is_int($n)&&$n>0,'snapshot_write');$at+=$n;}
        m6eNeed(fflush($h)&&fsync($h),'snapshot_sync');clearstatcache(true,$path);$named=lstat($path);
        m6eNeed($named['dev']===$opened['dev']&&$named['ino']===$opened['ino']&&$named['nlink']===1
            &&file_get_contents($path)===$raw,'snapshot_write_readback');
    } catch(Throwable $e){$primary=$e;throw $e;}
    finally {try{m6eNeed(fclose($h),'snapshot_close');}catch(Throwable $error){if($primary===null)throw $error;$secondary[]='snapshot_close';}}
    $dir=fopen($parent,'r');m6eNeed(is_resource($dir),'snapshot_directory_open');
    try{m6eNeed(fsync($dir),'snapshot_directory_sync');}
    catch(Throwable $error){$primary=$error;throw $error;}
    finally{try{m6eNeed(fclose($dir),'snapshot_directory_close');}catch(Throwable $error){if($primary===null)throw $error;$secondary[]='snapshot_directory_close';}}
    return ['dev'=>$opened['dev'],'ino'=>$opened['ino']];
}
function m6eNativeSnapshot(PDO $builder,array $a,int $releaseId):void {
    m6eNeed(posix_geteuid()===2000&&!$builder->inTransaction(),'snapshot_committed_builder');$outcome=[];$primary=null;$reader=null;
    try {
        $baseline=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/snapshot-schema.json',1048576),true,64,JSON_THROW_ON_ERROR);
        $credentials=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/snapshot-reader.json',8192),true,16,JSON_THROW_ON_ERROR);
        m6eNeed($credentials['username']==='m6e_snapshot','snapshot_credentials');
        $checkpoint=json_decode(file_get_contents('/run/ubo-m6e-fixture/worker/pre-b-source.json'),true,64,JSON_THROW_ON_ERROR);
        $reader=new PDO('mysql:host=127.0.0.1;port=3306;dbname=ubo_m6e_'.$a['run_id'].';charset=utf8mb4',$credentials['username'],$credentials['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
             PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_SSL_CA=>'/etc/ubo-site-publisher/tls/ca.pem']);
        $capture=m6eCaptureSnapshot($reader,$a,$baseline,$checkpoint,
            static fn()=>m6eCheckpointObserve($builder,$releaseId,$checkpoint['observed']['database']),
            (int)m6eCheckpointScalar($builder,'SELECT CONNECTION_ID()'),$outcome);
        $reader=null; // No locks/session survive into file verification or B creation.
        $created=m6eSnapshotWrite('/run/ubo-m6e-fixture/worker/pre-b-database.json',CanonicalJson::encode($capture),$outcome['secondary']);
        require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';
        $r=M6CNativeProcess::capture(['/usr/bin/python3','-I','-B',__DIR__.'/WebsitePlatformM6ESnapshot.py','--verify-worker'],15,1024);
        m6eNeed($r['exit']===0&&$r['stdout']==="snapshot_verified\n"&&$r['stderr']==='','snapshot_independent_verification');
        clearstatcache(true,'/run/ubo-m6e-fixture/worker/pre-b-database.json');$named=lstat('/run/ubo-m6e-fixture/worker/pre-b-database.json');
        m6eNeed($created===['dev'=>$named['dev'],'ino'=>$named['ino']],'snapshot_verification_inode');
        m6eSnapshotFinalBoundary($builder,$releaseId,$checkpoint);
        $outcome['verification']='VERIFIED';
    } catch(Throwable $error){$primary=$error;$outcome+=['capture'=>'FAILED','verification'=>'FAILED','transaction_cleanup'=>'UNRESOLVED','first_failure'=>'snapshot_native','secondary'=>[]];
        $outcome['first_failure']??='snapshot_file_verification';}
    finally {
        $reader=null; // Also close the separate connection after rollback/unlock failure.
        try {m6eSnapshotWrite('/run/ubo-m6e-fixture/worker/pre-b-database-outcome.json',CanonicalJson::encode($outcome),$outcome['secondary']);}
        catch(Throwable $error){if($primary===null){$primary=$error;$outcome['first_failure']='snapshot_outcome_write';}
            else $outcome['secondary'][]='snapshot_outcome_write';}
    }
    if($primary!==null)throw new M6ESnapshotFailure($outcome); // Safe stage codes only; never chain SQL/data/credential diagnostics.
}
function m6eSnapshotFinalBoundary(PDO $builder,int $releaseId,array $checkpoint):void {
    $after=m6eCheckpointObserve($builder,$releaseId,$checkpoint['observed']['database']);
    m6eNeed(time()<$checkpoint['expires_at']&&CanonicalJson::encode($after)===CanonicalJson::encode($checkpoint['observed']),'snapshot_changed_before_completion');
}
