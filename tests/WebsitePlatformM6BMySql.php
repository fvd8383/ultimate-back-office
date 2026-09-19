<?php

declare(strict_types=1);
// Explicit local harness, excluded from ordinary *Test.php discovery.
// Uses canonical 001-025 SQL, native PDO, synthetic data and independent processes.
require_once __DIR__ . '/support/WebsitePlatformM6BMySqlSupport.php';
$assertions=0;$owned=[];$admin=null;$children=[];
function mysqlCheck(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function mysqlReject(PDO $db,callable $mutation,array $codes=[1452,3819,1048,1062]):void{
    $outer=$db->inTransaction();
    if($outer)$db->exec('SAVEPOINT m6_rejection');else $db->beginTransaction();
    try{$mutation();throw new RuntimeException('Expected actual MySQL rejection.');}
    catch(PDOException $e){mysqlCheck(in_array((int)($e->errorInfo[1]??0),$codes,true),'Unexpected SQL rejection code: '.(int)($e->errorInfo[1]??0));}
    finally{if($outer){$db->exec('ROLLBACK TO SAVEPOINT m6_rejection');$db->exec('RELEASE SAVEPOINT m6_rejection');}elseif($db->inTransaction())$db->rollBack();}
}
function mysqlStart(string $database,array $payload):array{
    global $children;
    $args=[PHP_BINARY];
    // Child inherits command-line extension settings without modifying php.ini.
    if(!in_array('mysql',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO MySQL unavailable');
    if(PHP_OS_FAMILY==='Windows'){
        $args=array_merge($args,['-d','extension_dir='.dirname(PHP_BINARY).'/ext','-d','extension=pdo_mysql']);
    }
    $args=array_merge($args,[__DIR__.'/support/WebsitePlatformM6BMySqlWorker.php',$database,base64_encode(json_encode($payload,JSON_THROW_ON_ERROR))]);
    $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Independent PHP worker unavailable.');
    stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $child=['process'=>$process,'pipes'=>$pipes];$children[]=$child;
    $ready=mysqlLine($child);
    if(!preg_match('/^READY (\d+)$/D',$ready,$m))throw new RuntimeException('Worker connection barrier failed.');
    $child['connection_id']=(int)$m[1];return $child;
}
function mysqlSend(array $child,string $message):void{fwrite($child['pipes'][0],$message."\n");fflush($child['pipes'][0]);}
function mysqlLine(array $child):string{
    $deadline=microtime(true)+25;$buffer='';
    do{
        $line=fgets($child['pipes'][1]);
        if($line!==false){$buffer.=$line;if(str_ends_with($buffer,"\n"))return trim($buffer);}
        if(!proc_get_status($child['process'])['running']&&feof($child['pipes'][1]))throw new RuntimeException('Worker exited before its barrier/result.');
        usleep(10000);
    }while(microtime(true)<$deadline);
    throw new RuntimeException('Worker barrier timed out.');
}
function mysqlResult(array $child):mixed{
    $line=mysqlLine($child);
    if(!str_starts_with($line,'RESULT '))throw new RuntimeException('Independent worker failed: '.substr($line,0,80));
    return json_decode(substr($line,7),true,64,JSON_THROW_ON_ERROR);
}
function mysqlWaitLock(PDO $monitor,int $requesting,int $blocking):void{
    $query=$monitor->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w
        JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID
        JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID
        WHERE r.PROCESSLIST_ID=:requesting AND b.PROCESSLIST_ID=:blocking');
    $end=microtime(true)+6;
    do{$query->execute(['requesting'=>$requesting,'blocking'=>$blocking]);if((int)$query->fetchColumn()>0)return;usleep(20000);}while(microtime(true)<$end);
    throw new RuntimeException('Expected real InnoDB lock wait was not observed.');
}
function mysqlSchema(PDO $db):void{
    $sql=file_get_contents(dirname(__DIR__).'/database/migrations/025_site_build_deployment.sql');
    $tables=[];
    foreach(m6bSqlStatements($sql)as$statement){
        if(!preg_match('/^CREATE TABLE (\w+)/',$statement,$m))continue;
        $table=$m[1];$tables[]=$table;
        $columns=SiteBuildStore::rows($db,'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, CHARACTER_SET_NAME, COLLATION_NAME, DATETIME_PRECISION
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name',['table_name'=>$table]);
        $byName=array_column($columns,null,'COLUMN_NAME');
        preg_match_all('/^    (\w+) ((?:BIGINT|INT|TINYINT|SMALLINT|CHAR|VARCHAR|DATETIME|JSON)(?:\(\d+\))?(?: UNSIGNED)?)([^\n,]*)/m',$statement,$definitions,PREG_SET_ORDER);
        mysqlCheck(count($byName)===count($definitions),'Every real column accounted for '.$table);
        foreach($definitions as$d){
            $column=$byName[$d[1]]??null;
            $type=strtolower($d[2]);if($type==='tinyint(1)')$type='tinyint';
            $actual=$column['COLUMN_TYPE']??'';if($actual==='tinyint(1)')$actual='tinyint';
            mysqlCheck($column!==null&&$actual===$type,'Actual column type '.$table.'.'.$d[1]);
            $nullable=!str_contains($d[3],'NOT NULL')&&!str_contains($d[3],'PRIMARY KEY');
            mysqlCheck($column['IS_NULLABLE']===($nullable?'YES':'NO'),'Actual nullability '.$table.'.'.$d[1]);
            if(str_contains($d[3],'CHARACTER SET ascii'))mysqlCheck($column['CHARACTER_SET_NAME']==='ascii'&&$column['COLLATION_NAME']==='ascii_bin','Binary ASCII identity '.$table.'.'.$d[1]);
        }
    }
    mysqlCheck(count($tables)===9,'Nine real tables');
    $foreign=SiteBuildStore::rows($db,'SELECT k.CONSTRAINT_NAME,k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,
        k.ORDINAL_POSITION,r.DELETE_RULE,r.UNIQUE_CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION');
    preg_match_all('/CONSTRAINT (\w+) FOREIGN KEY \(([^)]+)\)\s+REFERENCES (\w+) \(([^)]+)\) ON DELETE (RESTRICT|SET NULL)/',$sql,$expected,PREG_SET_ORDER);
    foreach($expected as$f){
        $actual=array_values(array_filter($foreign,static fn($row)=>$row['CONSTRAINT_NAME']===$f[1]));
        mysqlCheck(implode(',',array_column($actual,'COLUMN_NAME'))===str_replace(' ','',$f[2])
            &&implode(',',array_column($actual,'REFERENCED_COLUMN_NAME'))===str_replace(' ','',$f[4])
            &&$actual[0]['REFERENCED_TABLE_NAME']===$f[3]&&$actual[0]['DELETE_RULE']===$f[5],'Real ownership/FK deletion contract '.$f[1]);
        $index=SiteBuildStore::rows($db,'SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND INDEX_NAME=:index_name ORDER BY SEQ_IN_INDEX',
            ['table_name'=>$f[3],'index_name'=>$actual[0]['UNIQUE_CONSTRAINT_NAME']]);
        mysqlCheck($index!==[]&&(int)$index[0]['NON_UNIQUE']===0&&implode(',',array_column($index,'COLUMN_NAME'))===str_replace(' ','',$f[4]),'Explicit unique referenced tuple '.$f[1]);
    }
    $checks=SiteBuildStore::rows($db,"SELECT t.TABLE_NAME,c.CHECK_CLAUSE,t.ENFORCED FROM information_schema.TABLE_CONSTRAINTS t
        JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME
        WHERE t.CONSTRAINT_SCHEMA=DATABASE() AND t.CONSTRAINT_TYPE='CHECK'");
    foreach($checks as$c)if(in_array($c['TABLE_NAME'],$tables,true)){
        mysqlCheck($c['ENFORCED']==='YES','Real CHECK enabled '.$c['TABLE_NAME']);
        mysqlCheck(!str_contains($c['CHECK_CLAUSE'],'recovery_authorized_by_user_id'),'Nullable historical actor excluded from every real CHECK');
    }
    mysqlCheck((int)$db->query('SELECT @@foreign_key_checks')->fetchColumn()===1,'Foreign keys remain enabled');
}
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
    if(!in_array('mysql',PDO::getAvailableDrivers(),true))throw new RuntimeException('NOT EXECUTED: PHP PDO MySQL, local Docker and mysql:8.4 are required.');
    $identity=m6mysqlIdentity();$admin=m6mysqlConnect($identity,null,true);
    $server=$admin->query('SELECT @@version AS version,@@transaction_isolation AS isolation_level')->fetch();
    echo 'Verified new local container '.$identity['id'].'; native PDO; server '.json_encode($server)."\n";
    $prefix='ubo_m6b_'.substr($identity['token'],0,16);
    foreach(['fresh','upgrade']as$mode){
        $name=$prefix.'_'.$mode;
        $exists=SiteBuildStore::one($admin,'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:name',['name'=>$name]);
        if($exists!==null)throw new RuntimeException('Refusing a preexisting database.');
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$owned[]=$name;
        $db=m6mysqlConnect($identity,$name);
        if($mode==='fresh')m6mysqlMigrate($db,1,25);
        else{
            m6mysqlMigrate($db,1,24);$fixture=m6mysqlFixture($db);$before=m6mysqlSnapshot($db);
            m6mysqlMigrate($db,25,25);$after=m6mysqlSnapshot($db);
            foreach($before as$table=>$hash)mysqlCheck($after[$table]===$hash,'Upgrade preserves canonical preexisting content/relationships '.$table);
            mysqlCheck(count($after)===count($before)+9,'Upgrade adds only nine tables');
        }
        mysqlSchema($db);
        foreach(['site_build_jobs','site_releases','site_deployment_targets','site_deployment_approvals']as$table)mysqlCheck((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===0,'Migration has no operational seeds '.$table);
    }
    $database=$prefix.'_fresh';$db=m6mysqlConnect($identity,$database);$monitor=m6mysqlConnect($identity,$database);
    $fixture=m6mysqlFixture($db);$runtime=m6mysqlWire($db);$runtime->operator=$fixture['operator'];
    $a=mysqlStart($database,['action'=>'request','fixture'=>$fixture,'actor'=>$fixture['requester']]);
    $b=mysqlStart($database,['action'=>'request','fixture'=>$fixture,'actor'=>$fixture['operator']]);
    mysqlSend($a,'GO');mysqlSend($b,'GO');$ja=mysqlResult($a);$jb=mysqlResult($b);
    mysqlCheck($ja['id']===$jb['id'],'B06 independent concurrent requests yield one job');
    $jobId=(int)$ja['id'];
    mysqlCheck((int)$db->query("SELECT COUNT(*) FROM site_events WHERE event_type='site_build_requested'")->fetchColumn()===1,'B06 one requested event');
    $a=mysqlStart($database,['action'=>'claim','fixture'=>$fixture]);$b=mysqlStart($database,['action'=>'claim','fixture'=>$fixture]);
    mysqlSend($a,'GO');mysqlSend($b,'GO');$ca=mysqlResult($a);$cb=mysqlResult($b);
    mysqlCheck(($ca===null)!==($cb===null),'Independent claimers allocate exactly one lease');
    $claim=$ca??$cb;$job=m6mysqlJob($db,$jobId);
    $hint=$runtime->receipt($job,$claim['lease']['attempt_id'],'sealed');
    $success=SiteBuildService::completeBuildSuccess($claim['lease'],$hint);
    mysqlCheck($success['job']['status']==='succeeded','Real atomic synthetic success transaction');
    $immutable=m6mysqlSnapshot($db);SiteBuildService::completeBuildSuccess($claim['lease'],$hint);
    mysqlCheck($immutable===m6mysqlSnapshot($db),'Completion replay has no DB effects');

    // Actual constraint rejection and actor SET NULL in BOTH attempt tables.
    require __DIR__.'/support/WebsitePlatformM6BMySqlSchemaCases.php';

    foreach(['deactivate','delete','grant','scope','name']as$revocation){
        foreach(['revocation_first','claim_first']as$order){
            $f=m6mysqlFixture($db);$runtime=m6mysqlWire($db);$runtime->operator=$f['operator'];$j=m6mysqlRequest($f);
            if($order==='revocation_first'){
                $db->beginTransaction();m6mysqlRevoke($db,$f,$revocation);
                $worker=mysqlStart($database,['action'=>'claim','fixture'=>$f]);mysqlSend($worker,'GO');
                mysqlWaitLock($monitor,$worker['connection_id'],(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn());
                $db->commit();$result=mysqlResult($worker);
                $row=m6mysqlJob($db,(int)$j['id']);
                mysqlCheck($result===null&&$row['status']==='cancelled'&&(int)$row['execution_count']===0
                    &&(int)$row['attempt_count']===0,'B22 revoked first: '.$revocation);
            }else{
                $worker=mysqlStart($database,['action'=>'claim_hold','fixture'=>$f]);mysqlSend($worker,'GO');
                mysqlCheck(mysqlLine($worker)==='CLAIM_LOCKED','Claim has authorization locks before commit');
                $revoker=mysqlStart($database,['action'=>'revoke','fixture'=>$f,'kind'=>$revocation]);mysqlSend($revoker,'GO');
                mysqlCheck(mysqlLine($revoker)==='REVOCATION_START','Independent revoker started');
                mysqlWaitLock($monitor,$revoker['connection_id'],$worker['connection_id']);
                mysqlSend($worker,'RELEASE');$claimed=mysqlResult($worker);mysqlResult($revoker);
                mysqlCheck($claimed!==null,'B22 claim-first execution remains historical: '.$revocation);
                $row=m6mysqlJob($db,(int)$j['id']);$hint=$runtime->receipt($row,$claimed['lease']['attempt_id'],'sealed');
                $denied=SiteBuildService::completeBuildSuccess($claimed['lease'],$hint);
                mysqlCheck($denied['job']['status']==='reconciliation_required'&&$denied['job']['failure_code']==='requester_not_authorized',
                    'B23 result rechecks current authority: '.$revocation);
            }
        }
    }
    // Native FK/CHECK failure after prior result writes proves transaction rollback, including audit.
    foreach(['site_release_validations','site_events']as$fault){
        $f=m6mysqlFixture($db);$runtime=m6mysqlWire($db);$j=m6mysqlRequest($f);$c=SiteBuildService::claimBuild([]);
        $wrapper=new M6NativeConnection($db);$wrapper->faultTable=$fault;
        $runtime=m6mysqlWire($wrapper);$hint=$runtime->receipt(m6mysqlJob($db,(int)$j['id']),$c['lease']['attempt_id'],'sealed');
        $before=m6mysqlSnapshot($db);
        try{SiteBuildService::completeBuildSuccess($c['lease'],$hint);throw new RuntimeException('Expected real constraint failure');}
        catch(SiteServiceException $e){mysqlCheck($e->classification()==='database_failure','Safe database error classification');}
        mysqlCheck($before===m6mysqlSnapshot($db),'Real SQL rollback after '.$fault.' failure');
        $runtime=m6mysqlWire($db);
        SiteBuildService::completeBuildFailure($c['lease'],['code'=>'artifact_invalid']+$runtime->receipt(m6mysqlJob($db,(int)$j['id']),$c['lease']['attempt_id'],'safe_absence'));
    }
    // Persisted counters at execution three, current FK ownership, bounded recovery.
    $f=m6mysqlFixture($db);$runtime=m6mysqlWire($db);$runtime->operator=$f['operator'];$j=m6mysqlRequest($f);
    for($i=1;$i<=3;$i++){
        $c=SiteBuildService::claimBuild([]);mysqlCheck($c['attempt']['execution_number']===$i,'Native execution numbering');
        SiteBuildService::completeBuildFailure($c['lease'],['code'=>$i===3?'outcome_unknown':'storage_unavailable']
            +$runtime->receipt(m6mysqlJob($db,(int)$j['id']),$c['lease']['attempt_id'],$i===3?'unknown':'safe_absence'));
        $db->prepare('UPDATE site_build_jobs SET next_attempt_at=UTC_TIMESTAMP(6)-INTERVAL 1 SECOND WHERE id=?')->execute([$j['id']]);
    }
    m6mysqlRevoke($db,$f,'delete');
    $db->prepare('UPDATE site_build_jobs SET next_recovery_at=UTC_TIMESTAMP(6)-INTERVAL 1 SECOND WHERE id=?')->execute([$j['id']]);
    $r=SiteBuildService::claimBuildRecovery((int)$j['id'],[]);
    mysqlCheck($r['attempt']['attempt_number']===4&&$r['attempt']['recovery_number']===1,'B24 native attempt four at execution budget three');
    $resolved=SiteBuildService::completeBuildRecovery($r['lease'],['disposition'=>'safely_failed']
        +$runtime->receipt(m6mysqlJob($db,(int)$j['id']),$c['lease']['attempt_id'],'quarantined'));
    mysqlCheck($resolved['job']['status']==='failed'&&(int)$resolved['job']['execution_count']===3,'Safe recovery after requester deletion');
    mysqlCheck((int)$db->query('SELECT COUNT(*) FROM sites WHERE current_published_revision_id IS NOT NULL')->fetchColumn()===0
        &&(int)$db->query('SELECT COUNT(*) FROM site_deployment_targets WHERE enabled<>0 OR current_deployment_id IS NOT NULL OR active_deployment_id IS NOT NULL')->fetchColumn()===0,
        'No production/global publication/target pointer changes');
    echo "LOCAL MYSQL PASS: $assertions assertions; canonical fresh+upgrade; independent process/barrier concurrency. Synthetic evidence only.\n";
}catch(Throwable $e){
    fwrite(STDERR,($e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");$failed=true;
}finally{
    foreach($children as$child){
        if(is_resource($child['process'])){
            if(proc_get_status($child['process'])['running'])proc_terminate($child['process']);
            foreach($child['pipes']as$pipe)if(is_resource($pipe))fclose($pipe);
            proc_close($child['process']);
        }
    }
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    // DDL is not transactional. Only databases successfully created by THIS run are dropped.
    if($admin instanceof PDO)foreach($owned as$name){
        if(!preg_match('/^ubo_m6b_'.substr($identity['token'],0,16).'_(fresh|upgrade)$/D',$name))continue;
        $admin->exec('DROP DATABASE `'.$name.'`');echo 'Removed owned disposable database '.$name."\n";
    }
}
exit(isset($failed)?2:0);
