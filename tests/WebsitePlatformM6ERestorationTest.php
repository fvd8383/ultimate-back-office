<?php
declare(strict_types=1);
/** SQL/catalog/transactions are MODELS. M6C sealed file verification uses real local bytes. */
define('M6E_SNAPSHOT_CLASSES_ONLY',true);
require_once __DIR__.'/WebsitePlatformM6ESnapshotTest.php';
require_once __DIR__.'/support/WebsitePlatformM6ERestoration.php';

final class RestorationStatement extends PDOStatement {
    private array $result=[];
    public function __construct(private RestorationDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool{$this->result=$this->db->rows($this->sql,$params??[]);return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->result;}
    public function fetchColumn(int $column=0):mixed{return $this->result===[]?false:array_values(array_shift($this->result))[$column];}
    public function closeCursor():bool{return true;}
}
final class RestorationDatabase extends PDO {
    public array $schema=[],$tables=[],$ddl=[],$trace=[],$grants=[],$fail=[],$backup=[];
    public bool $transaction=false,$foreign=true,$unknownCommit=false;public array $identity;public int $connection=71;
    public function __construct(array $snapshot,bool $writer=true){
        $this->identity=['schema'=>'ubo_m6e_lifecycle_'.$snapshot['binding']['run_id'],'server_uuid'=>$snapshot['database']['server_uuid'],
            'account'=>'m6e_lifecycle_'.($writer?'worker':'observer').'@127.0.0.1'];
        $this->grants=['GRANT '.implode(', ',$writer?M6E_RESTORE_RIGHTS:['SELECT']).' ON `'.str_replace('_','\\_',$this->identity['schema']).'`.* TO `'.explode('@',$this->identity['account'])[0].'`@`127.0.0.1`'];
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new RestorationStatement($this,$query);}
    public function inTransaction():bool{return $this->transaction;}
    public function beginTransaction():bool{$this->trace[]='BEGIN';$this->backup=$this->tables;$this->transaction=true;return true;}
    public function commit():bool{
        $this->trace[]='COMMIT';if($this->unknownCommit){$this->transaction=false;throw new RuntimeException('model_unknown_commit');}
        if(in_array('COMMIT',$this->fail,true))throw new RuntimeException('model_commit');$this->transaction=false;return true;
    }
    public function rollBack():bool{
        $this->trace[]='ROLLBACK';if(in_array('ROLLBACK',$this->fail,true))throw new RuntimeException('model_rollback');
        if(!$this->transaction)return false;$this->tables=$this->backup;$this->transaction=false;return true;
    }
    public function exec(string $sql):int|false {
        $this->trace[]=$sql;if(in_array($sql,$this->fail,true))throw new RuntimeException('model_exec');
        if(preg_match('/^DELETE FROM `([a-z0-9_]+)`$/D',$sql,$m)){
            m6eNeed($this->transaction&&!$this->foreign,'model_destination_delete_boundary');$this->tables[$m[1]]=[];
        } elseif(preg_match('/^ALTER TABLE `([a-z0-9_]+)` AUTO_INCREMENT=([0-9]+)$/D',$sql,$m)){
            m6eNeed(!$this->transaction,'model_ddl_outside_data');$this->ddl[$m[1]]=str_replace(') ENGINE=InnoDB ',') ENGINE=InnoDB AUTO_INCREMENT='.$m[2].' ',$this->schema[$m[1]]['definition']);
        } elseif($sql==='START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')$this->transaction=true;
        elseif(str_starts_with($sql,'SET SESSION ')){
            if(str_starts_with($sql,'SET SESSION foreign_key_checks='))$this->foreign=str_ends_with($sql,'=1');
        } else throw new RuntimeException('unexpected_model_mutation');return 0;
    }
    public function rows(string $sql,array $params):array {
        $this->trace[]=$sql;if(in_array($sql,$this->fail,true))throw new RuntimeException('model_query');
        foreach(['SELECT DATABASE()'=>'schema','SELECT @@server_uuid'=>'server_uuid','SELECT CURRENT_USER()'=>'account'] as $q=>$key)
            if($sql===$q)return [[$key=>$this->identity[$key]]];
        if($sql==='SELECT VERSION()')return [['version'=>'8.4.model']];
        if($sql==='SELECT CURRENT_ROLE()')return [['role'=>'NONE']];
        if($sql==='SELECT CONNECTION_ID()')return [['id'=>$this->connection]];
        if($sql==="SHOW SESSION STATUS LIKE 'Ssl_cipher'")return [['Value'=>'MODEL_TLS']];
        if($sql==='SHOW GRANTS')return array_map(static fn($g)=>['grant'=>$g],$this->grants);
        if($sql==='SHOW WARNINGS')return [];
        if(str_contains($sql,'FROM information_schema.SCHEMATA'))return [['DEFAULT_CHARACTER_SET_NAME'=>'utf8mb4','DEFAULT_COLLATION_NAME'=>'utf8mb4_unicode_ci']];
        if(str_contains($sql,'FROM information_schema.TABLES'))return array_map(static fn($n)=>['TABLE_NAME'=>$n,'ENGINE'=>'InnoDB','TABLE_TYPE'=>'BASE TABLE'],array_keys($this->schema));
        if(str_contains($sql,'FROM information_schema.KEY_COLUMN_USAGE')){
            $rows=[];foreach($this->schema[$params[1]]['foreign_keys'] as $i=>$fk)foreach($fk['columns'] as $j=>$c)$rows[]=
                ['CONSTRAINT_NAME'=>'fk_'.$i,'COLUMN_NAME'=>$c,'ORDINAL_POSITION'=>$j+1,'REFERENCED_TABLE_SCHEMA'=>$params[0],
                'REFERENCED_TABLE_NAME'=>$fk['table'],'REFERENCED_COLUMN_NAME'=>$fk['references'][$j]];return $rows;
        }
        if(preg_match('/^SHOW (FULL COLUMNS FROM|INDEX FROM|CREATE TABLE) `([a-z0-9_]+)`$/D',$sql,$m)){
            $s=$this->schema[$m[2]];
            if($m[1]==='CREATE TABLE')return [['Create Table'=>$this->ddl[$m[2]]??$s['definition']]];
            if($m[1]==='INDEX FROM')return array_map(static fn($c,$i)=>['Key_name'=>'PRIMARY','Seq_in_index'=>$i+1,'Column_name'=>$c],$s['primary'],array_keys($s['primary']));
            return array_map(static fn($c)=>['Field'=>$c,'Type'=>match($c){'amount'=>'decimal(10,4)','temporal'=>'datetime(6)','document'=>'json',default=>'text'},
                'Collation'=>in_array($c,['amount','temporal','document'],true)?null:'utf8mb4_unicode_ci','Extra'=>$c==='generated_hash'?'STORED GENERATED':($c==='id'&&$m[2]==='notes'?'auto_increment':'')],$s['columns']);
        }
        if(preg_match('/^INSERT INTO `([a-z0-9_]+)` \(([^)]+)\) VALUES /',$sql,$m)){
            m6eNeed($this->transaction&&!$this->foreign,'model_insert_boundary');preg_match_all('/`([a-z0-9_]+)`/',$m[2],$columns);
            $row=array_combine($columns[1],array_map(static fn($v)=>$v===null?null:hex2bin($v),$params));
            if($m[1]==='notes'){$row['generated_hash']=hash('sha256',$row['body']);m6eNeed(!in_array('generated_hash',$columns[1],true),'model_generated_insert');}
            foreach($this->tables[$m[1]] as $old){$same=true;foreach($this->schema[$m[1]]['primary'] as $c)$same=$same&&$old[$c]===$row[$c];
                if($same)throw new RuntimeException('model_duplicate_key');}
            $this->tables[$m[1]][]=$row;return [];
        }
        if(preg_match('/ FROM `([a-z0-9_]+)`/',$sql,$m)){
            $rows=$this->tables[$m[1]];$cols=$this->schema[$m[1]]['columns'];
            if(str_contains($sql,' AND NOT EXISTS ')){
                preg_match('/FROM `([a-z0-9_]+)` p WHERE/',$sql,$parent);preg_match_all('/p.`([a-z0-9_]+)`=c.`([a-z0-9_]+)`/',$sql,$matches,PREG_SET_ORDER);
                $missing=0;foreach($rows as $row){$nullable=false;$found=false;
                    foreach($matches as $pair)if($row[$pair[2]]===null)$nullable=true;
                    foreach($this->tables[$parent[1]] as $p){$match=true;foreach($matches as $pair)$match=$match&&(string)$p[$pair[1]]===(string)$row[$pair[2]];$found=$found||$match;}
                    if(!$nullable&&!$found)$missing++;}return [['count'=>$missing]];
            }
            if(str_starts_with($sql,'SELECT COUNT(*)'))return [['count'=>count($rows)]];
            if(str_starts_with($sql,'SELECT COALESCE')){$sizes=[];foreach($rows as $row)foreach($cols as $c)$sizes[]=strlen((string)($row[$c]??''));return [['bytes'=>array_sum($sizes),'cell'=>$sizes?max($sizes):0]];}
            if(str_starts_with($sql,'SELECT HEX('))return array_map(static function($row)use($cols){$out=[];foreach($cols as $c)$out[$c]=($row[$c]??null)===null?null:strtoupper(bin2hex((string)$row[$c]));return $out;},$rows);
        }
        throw new RuntimeException('unexpected_model_query');
    }
}
$checks=0;$check=static function(bool $v,string $why)use(&$checks):void{$checks++;if(!$v)throw new RuntimeException($why);};
$reject=static function(callable $f,string $why)use($check):void{try{$f();}catch(Throwable){$check(true,$why);return;}throw new RuntimeException('not rejected: '.$why);};
$a=['run_id'=>str_repeat('b',32),'source_sha'=>str_repeat('a',40),'machine_id'=>str_repeat('c',32),'boot_id'=>'11111111-1111-4111-8111-111111111111','expires_at'=>time()+3000];
try {
    m6eLocalServingFixture(false,static function($f,$first)use($a,$check,$reject):void{
        $source=new M6ECheckpointDatabase($f->db);$id=(int)$first['row']['id'];
        $cp=m6eCaptureCheckpoint($source,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],$id,$source->identity,$a,'controlled-local');
        $reader=new M6ESnapshotDatabase($source,$cp);
        $reader->tables['notes'][0]+=['temporal'=>'2030-02-03 04:05:06.123456','document'=>'{"num": 1, "text": "Café"}'];
        $reader->tables['notes'][0]['generated_hash']=hash('sha256',$reader->tables['notes'][0]['body']);
        $reader->schema['notes']['columns']=array_keys($reader->tables['notes'][0]);
        $reader->schema['notes']['definition']='CREATE TABLE `notes` (modeled typed columns) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $reader->schema['site_build_jobs']['foreign_keys'][]=['table'=>'site_build_attempts','columns'=>['current_attempt_id','id','site_id'],'references'=>['id','build_job_id','site_id']];
        $baseline=['profile'=>'m6e-pre-b-schema-v1','binding'=>$cp['binding'],'schema'=>$source->identity['schema'],'server_uuid'=>$source->identity['server_uuid'],'migrations'=>m6eSnapshotMigrations(),'tables'=>$reader->schema];
        $captureOutcome=[];$snapshot=m6eCaptureSnapshot($reader,$a,$baseline,$cp,static fn()=>m6eCheckpointObserve($source,$id,$source->identity),1,$captureOutcome);
        $snapshot['tables']['notes']['create_table']=str_replace(') ENGINE=InnoDB ',') ENGINE=InnoDB AUTO_INCREMENT=99 ',$snapshot['tables']['notes']['create_table']);
        $migrate=static function(RestorationDatabase $db)use($baseline):void{
            $db->schema=$baseline['tables'];$db->tables=array_fill_keys(array_keys($db->schema),[]);
            $db->tables['notes']=[['id'=>'1','body'=>'seed must disappear']];
        };
        $run=static function($db,$value=null,$migration=null)use($a,$baseline,$cp,$snapshot,$migrate):array{
            $outcome=[];try{$identity=m6eRestoreData($db,$a,$baseline,$cp,$value??$snapshot,$migration??$migrate,static function():void{},$outcome);}
            catch(Throwable $error){$GLOBALS['restore_failed']=$outcome;throw new RuntimeException('model_restore_failure: '.$outcome['first_failure'],0,$error);}return [$identity,$outcome];
        };
        $before=$f->db->snapshot();$db=new RestorationDatabase($snapshot);[$writer,$outcome]=$run($db);
        $check($outcome['result']==='AWAITING_INDEPENDENT_VERIFICATION'&&$outcome['transaction']==='COMMITTED','writer cannot assert ready');
        $observer=new RestorationDatabase($snapshot,false);$observer->connection=72;$observer->schema=&$db->schema;$observer->tables=&$db->tables;$observer->ddl=&$db->ddl;
        $admitted=m6eRestoreAdmit($observer,$observer->identity,false);$read=m6eRestoreRead($observer,$observer->identity['schema'],static function():void{});
        $check(CanonicalJson::encode($read['tables'])===CanonicalJson::encode($snapshot['tables']),'all 90 tables and exact values');
        $check($read['definitions']===$baseline['tables']&&count($read['tables'])===90,'exact schema inventory');
        $check($f->db->snapshot()===$before,'original database unchanged');
        $check($writer['connection_id']!==$admitted['connection_id'],'independent observer session');
        $check(count(array_filter($db->trace,static fn($s)=>str_starts_with($s,'DELETE FROM ')))===90,'destination-only seed reconciliation');
        $check($db->tables['notes'][0]['id']==='1'&&$db->tables['notes'][0]['body']!== 'seed must disappear','original primary key replaces migration seed');
        $check($db->tables['notes'][0]['temporal']==='2030-02-03 04:05:06.123456'&&$db->tables['notes'][0]['amount']==='123.4500','temporal and decimal text');
        $check(str_contains(implode("\n",$db->trace),'CONVERT(UNHEX(?) USING utf8mb4)'),'JSON/text explicit charset conversion');
        $check($db->ddl['notes']===$snapshot['tables']['notes']['create_table'],'original auto increment counter');
        $check(!array_filter($observer->trace,static fn($q)=>preg_match('/^(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|GRANT) /',$q)),'observer has no mutation path');
        $check(m6eReleaseSummary($first['row'],$first['job'],$first['producer'],$f->store->verifyRelease($first['job'],$first['producer']))===$cp['summary'],'real original M6C sealed artifact');
        // Native inputs cross the protected canonical JSON boundary, whose object key order differs.
        $wire=static fn($value)=>json_decode(CanonicalJson::encode($value),true,128,JSON_THROW_ON_ERROR);
        $wireDb=new RestorationDatabase($snapshot);$wireOutcome=[];
        m6eRestoreData($wireDb,$wire($a),$wire($baseline),$wire($cp),$wire($snapshot),$migrate,static function():void{},$wireOutcome);
        $check($wireOutcome['result']==='AWAITING_INDEPENDENT_VERIFICATION','canonical protected input round trip');
        $asset=$f->root.'/sealed/'.$first['row']['storage_key'].'/public/index.html';$saved=file_get_contents($asset);
        try{file_put_contents($asset,$saved.'corrupt');$check($f->store->verifyRelease($first['job'],$first['producer'])['disposition']!=='sealed','corrupt original artifact rejected');}
        finally{file_put_contents($asset,$saved);}
        foreach(['schema','server_uuid','account'] as $key){$bad=new RestorationDatabase($snapshot);$bad->identity[$key]='wrong';$reject(static fn()=>$run($bad),'wrong '.$key);$check($bad->tables===[],'identity denial before writes');}
        $bad=new RestorationDatabase($snapshot);$bad->grants[]='GRANT SELECT ON *.* TO `m6e_lifecycle_worker`@`127.0.0.1`';$reject(static fn()=>$run($bad),'global privileges');
        $bad=new RestorationDatabase($snapshot);$bad->schema['notes']=$baseline['tables']['notes'];$reject(static fn()=>$run($bad),'existing even empty destination');
        foreach(['missing','extra','altered'] as $kind){$bad=new RestorationDatabase($snapshot);$migration=static function($d)use($migrate,$kind):void{$migrate($d);
            if($kind==='missing')unset($d->schema['notes']);elseif($kind==='extra')$d->schema['unexpected']=$d->schema['notes'];else $d->schema['notes']['definition'].=' changed';};
            $reject(static fn()=>$run($bad,null,$migration),'schema '.$kind);}
        $bad=$snapshot;$bad['binding']['run_id']=str_repeat('a',32);$reject(static fn()=>$run(new RestorationDatabase($snapshot),$bad),'original run binding');
        $bad=$snapshot;$bad['checkpoint_sha256']=str_repeat('0',64);$reject(static fn()=>$run(new RestorationDatabase($snapshot),$bad),'corrupt checkpoint');
        $bad=$snapshot;$bad['tables']['notes']['rows_sha256']=str_repeat('0',64);$reject(static fn()=>$run(new RestorationDatabase($snapshot),$bad),'corrupt rows');
        $bad=$snapshot;$bad['tables']['notes']['rows'][]=$bad['tables']['notes']['rows'][0];$bad['tables']['notes']['row_count']++;
        $bad['tables']['notes']['rows_sha256']=CanonicalJson::hash($bad['tables']['notes']['rows']);$reject(static fn()=>$run(new RestorationDatabase($snapshot),$bad),'duplicate key');
        $reject(static fn()=>$run(new RestorationDatabase($snapshot),null,static function($d)use($migrate):void{$migrate($d);throw new RuntimeException('ddl_interrupted');}),'interrupted DDL');
        $check($GLOBALS['restore_failed']['result']==='INCOMPLETE'&&$GLOBALS['restore_failed']['transaction']==='UNREACHED','DDL cannot roll back into ready');
        $bad=new RestorationDatabase($snapshot);$bad->fail=['DELETE FROM `notes`'];$reject(static fn()=>$run($bad),'interrupted DML');
        $check($GLOBALS['restore_failed']['transaction']==='ROLLED_BACK'&&$bad->tables['notes'][0]['body']==='seed must disappear','DML rollback preserves unready seed schema');
        $bad=new RestorationDatabase($snapshot);$bad->unknownCommit=true;$reject(static fn()=>$run($bad),'unknown commit');
        $check($GLOBALS['restore_failed']['transaction']==='UNKNOWN'&&$GLOBALS['restore_failed']['result']==='INCOMPLETE','unknown commit cannot assert readiness');
        $bad=new RestorationDatabase($snapshot);$bad->fail=['COMMIT','ROLLBACK'];$reject(static fn()=>$run($bad),'commit and rollback failure');
        $check($GLOBALS['restore_failed']['first_failure']==='restore_commit'&&$GLOBALS['restore_failed']['secondary']===['restore_rollback'],'first failure and secondary cleanup');
        $db->tables['site_build_attempts'][0]['build_job_id']='99999';
        $reject(static fn()=>m6eRestoreRead($observer,$observer->identity['schema'],static function():void{}),'composite cyclic FK closure independently checked');
        $reject(static fn()=>SiteDeploymentContract::request($outcome,'publish'),'restoration is not deployment request');
        $reject(static fn()=>SiteDeploymentContract::lease($outcome),'restoration is not operational lease');
        $check(m6eRestoreCounter('CREATE TABLE `x` (id BIGINT) ENGINE=InnoDB AUTO_INCREMENT=18446744073709551615 DEFAULT CHARSET=utf8mb4')==='18446744073709551615','unsigned counter fidelity');
        $reject(static fn()=>m6eRestoreCounter('CREATE TABLE `x` (id BIGINT) ENGINE=InnoDB AUTO_INCREMENT=18446744073709551616 DEFAULT CHARSET=utf8mb4'),'counter overflow');
    });
    echo "M6E restoration: $checks assertions PASS (SQL/identity/transaction MODEL; real M6C artifact bytes; native SQL UNEXECUTED).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
