<?php
declare(strict_types=1);
/** Real original M6C files; explicit PDO metadata/transaction MODEL, never native SQL proof. */
define('M6E_CHECKPOINT_CLASSES_ONLY',true);
require_once __DIR__.'/WebsitePlatformM6ECheckpointTest.php';
require_once __DIR__.'/support/WebsitePlatformM6ESnapshot.php';

final class M6ESnapshotStatement extends PDOStatement {
    private array $result=[];
    public function __construct(private M6ESnapshotDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool {$this->result=$this->db->rows($this->sql,$params??[]);return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->result;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->result)??false;}
    public function fetchColumn(int $column=0):mixed{$r=$this->fetch();return $r===false?false:array_values($r)[$column];}
    public function closeCursor():bool{return true;}
}
final class M6ESnapshotDatabase extends PDO {
    public bool $transaction=false,$lock=false;public array $trace=[],$identity,$tables,$schema,$fail=[];
    public string $isolation='REPEATABLE-READ',$version='8.4.model',$role='NONE',$tls='MODEL_TLS';public int $readOnly=1,$connection=2;public $onRows=null;public array $extraGrants=[];
    public function __construct(public M6ECheckpointDatabase $observer,array $cp){
        $this->identity=array_replace($observer->identity,['account'=>'m6e_snapshot@127.0.0.1']);
        $this->tables=array_fill_keys(m6eSnapshotProfile()['tables'],[]);$o=$cp['observed'];$s=$o['source'];
        foreach(['sites'=>'site','site_revisions'=>'revision'] as $table=>$key)$this->tables[$table]=[$s[$key]];
        foreach(['site_releases'=>'release','site_build_jobs'=>'job','site_build_attempts'=>'producer'] as $table=>$key)$this->tables[$table]=[$o[$key]];
        $this->tables['site_revisions'][]=$observer->inner->read->base->revisions[99];
        $this->tables['site_approvals']=array_values($o['approvals']);
        $assets=[];foreach($s['asset_metadata'] as $row){unset($row['rights_current']);$assets[$row['id']]=$row;}
        $this->tables['site_assets']=array_values($assets); // Eligibility joins may select one asset for several usages.
        $this->tables['site_business_associations']=[['id'=>$s['association_id'],'business_id'=>$s['business_id'],'site_id'=>$s['site']['id'],'status'=>'active','association_role'=>'customer']];
        $this->tables['businesses']=[['id'=>$s['business_id'],'status'=>'active','is_suspended'=>0]];
        $this->tables['notes']=[['id'=>1,'body'=>"private-fixture-\0-Café",'amount'=>'123.4500','nullable'=>null,'empty'=>'']];
        foreach($this->tables as $name=>$rows){
            $cols=$rows===[]?['id','value']:array_keys($rows[0]);sort($cols);$fks=[];
            foreach(['site_id'=>'sites','revision_id'=>'site_revisions','source_revision_id'=>'site_revisions','build_job_id'=>'site_build_jobs'] as $col=>$target)
                if(in_array($col,$cols,true))$fks[]=['table'=>$target,'columns'=>[$col],'references'=>['id']];
            $this->schema[$name]=['columns'=>$cols,'primary'=>['id'],'foreign_keys'=>$fks,
                'definition'=>'CREATE TABLE `'.$name.'` ('.implode(', ',array_map(static fn($c)=>'`'.$c.'` text',$cols)).') ENGINE=InnoDB'];
        }
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new M6ESnapshotStatement($this,$query);}
    public function inTransaction():bool{return $this->transaction;}
    public function exec(string $statement):int|false {
        $this->trace[]=$statement;if(in_array($statement,$this->fail,true))throw new RuntimeException('injected_control_failure');
        if($statement==='FLUSH TABLES WITH READ LOCK')$this->lock=true;
        elseif($statement==='UNLOCK TABLES')$this->lock=false;
        elseif($statement==='START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'){
            m6eNeed($this->lock&&!$this->transaction,'model_start_order');$this->transaction=true;
        }elseif(!str_starts_with($statement,'SET SESSION '))throw new RuntimeException('unexpected_control');return 0;
    }
    public function commit():bool{$this->trace[]='COMMIT';if(in_array('COMMIT',$this->fail,true))throw new RuntimeException('commit_failure');$this->transaction=false;return true;}
    public function rollBack():bool{$this->trace[]='ROLLBACK';if(in_array('ROLLBACK',$this->fail,true))throw new RuntimeException('rollback_failure');$this->transaction=false;return true;}
    public function rows(string $sql,array $params):array {
        $this->trace[]=$sql;
        foreach(['SELECT DATABASE()'=>'schema','SELECT @@server_uuid'=>'server_uuid','SELECT CURRENT_USER()'=>'account'] as $q=>$key)
            if($sql===$q)return [[$key=>$this->identity[$key]]];
        if($sql==='SELECT CONNECTION_ID()')return [['id'=>$this->connection]];
        if($sql==='SELECT VERSION()')return [['version'=>$this->version]];
        if($sql==='SELECT CURRENT_ROLE()')return [['role'=>$this->role]];
        if($sql==="SHOW SESSION STATUS LIKE 'Ssl_cipher'")return [['Value'=>$this->tls]];
        if($sql==='SHOW GRANTS')return array_map(static fn($v)=>['grant'=>$v],[
            'GRANT SELECT ON `'.str_replace('_','\\_',$this->identity['schema']).'`.* TO `m6e_snapshot`@`127.0.0.1`',
            'GRANT FLUSH_TABLES ON *.* TO `m6e_snapshot`@`127.0.0.1`',...$this->extraGrants]);
        if($sql==='SHOW WARNINGS')return [];
        if($sql==='SELECT @@session.transaction_isolation')return [['isolation'=>$this->isolation]];
        if($sql==='SELECT @@session.transaction_read_only')return [['ro'=>$this->readOnly]];
        if(str_contains($sql,'FROM information_schema.TABLES'))return array_map(static fn($n)=>['TABLE_NAME'=>$n,'ENGINE'=>'InnoDB','TABLE_TYPE'=>'BASE TABLE'],array_keys($this->schema));
        if(str_contains($sql,'FROM information_schema.KEY_COLUMN_USAGE')){
            $result=[];foreach($this->schema[$params[1]]['foreign_keys'] as $i=>$fk)foreach($fk['columns'] as $j=>$col)$result[]=
                ['CONSTRAINT_NAME'=>'fk_'.$i,'COLUMN_NAME'=>$col,'ORDINAL_POSITION'=>$j+1,'REFERENCED_TABLE_SCHEMA'=>$params[0],
                 'REFERENCED_TABLE_NAME'=>$fk['table'],'REFERENCED_COLUMN_NAME'=>$fk['references'][$j]];return $result;
        }
        if(preg_match('/^SHOW (FULL COLUMNS FROM|INDEX FROM|CREATE TABLE) `([a-z0-9_]+)`$/D',$sql,$m)){
            $s=$this->schema[$m[2]];
            return match($m[1]){'FULL COLUMNS FROM'=>array_map(static fn($c)=>['Field'=>$c,'Type'=>'text'],$s['columns']),
                'INDEX FROM'=>[['Key_name'=>'PRIMARY','Seq_in_index'=>1,'Column_name'=>'id']],
                'CREATE TABLE'=>[['Create Table'=>$s['definition']]]};
        }
        if(preg_match('/ FROM `([a-z0-9_]+)`/',$sql,$m)){
            m6eNeed($this->transaction&&$this->lock,'model_rows_inside_boundary');$rows=$this->tables[$m[1]];$cols=$this->schema[$m[1]]['columns'];
            if(str_starts_with($sql,'SELECT COUNT(*)'))return [['count'=>count($rows)]];
            if(str_starts_with($sql,'SELECT COALESCE')){
                $sizes=[];foreach($rows as $row)foreach($cols as $c)$sizes[]=strlen((string)($row[$c]??''));
                return [['bytes'=>array_sum($sizes),'cell'=>$sizes===[]?0:max($sizes)]];
            }
            if(str_starts_with($sql,'SELECT HEX(')){
                if($this->onRows!==null){$hook=$this->onRows;$this->onRows=null;$hook();}
                return array_map(static function($row)use($cols){$r=[];foreach($cols as $c)$r[$c]=($row[$c]??null)===null?null:strtoupper(bin2hex((string)$row[$c]));return $r;},$rows);
            }
        }
        m6eNeed($this->transaction&&!str_contains($sql,'FOR UPDATE')&&!str_contains($sql,'FOR SHARE'),'model_nonlocking_validator');
        return $this->observer->inner->executeSql($sql,$params)[0];
    }
}
$checks=0;$bundle=null;
$check=static function(bool $ok,string $why)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $f,string $why)use($check):void{try{$f();}catch(Throwable){$check(true,$why);return;}throw new RuntimeException('not rejected: '.$why);};
$a=['run_id'=>str_repeat('b',32),'source_sha'=>str_repeat('a',40),'machine_id'=>str_repeat('c',32),
    'boot_id'=>'11111111-1111-4111-8111-111111111111','expires_at'=>time()+3000];
try {
    [$f,$built]=m6eLocalServingFixture(false,static function($f,$first)use($a,$check,$reject,&$bundle):void {
        $observer=new M6ECheckpointDatabase($f->db);$id=(int)$first['row']['id'];
        $cp=m6eCaptureCheckpoint($observer,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],$id,$observer->identity,$a,'controlled-local');
        $reader=new M6ESnapshotDatabase($observer,$cp);$baseline=['profile'=>'m6e-pre-b-schema-v1','binding'=>$cp['binding'],
            'schema'=>$observer->identity['schema'],'server_uuid'=>$observer->identity['server_uuid'],'migrations'=>m6eSnapshotMigrations(),'tables'=>$reader->schema];
        $observe=static fn()=>m6eCheckpointObserve($observer,$id,$observer->identity);$outcome=[];
        $run=static function()use($reader,$a,$baseline,$cp,$observe,&$outcome){return m6eCaptureSnapshot($reader,$a,$baseline,$cp,$observe,1,$outcome);};
        $before=$f->db->snapshot();$capture=$run();
        $check($f->builds===1,'capture completes synchronously while only A exists');
        $check($before===$f->db->snapshot(),'capture leaves source fixture unchanged');
        $check(count($capture['tables'])===90&&$capture['checkpoint_sha256']===CanonicalJson::hash($cp),'full inventory and original checkpoint');
        $check($outcome===['capture'=>'CAPTURED','verification'=>'UNREACHED','transaction_cleanup'=>'RELEASED','first_failure'=>null,'secondary'=>[]],'transaction cleanup recorded');
        $check(count(array_filter($reader->trace,static fn($q)=>str_starts_with($q,'START TRANSACTION')))===1,'one consistent boundary');
        $check(!array_filter($reader->trace,static fn($q)=>preg_match('/\b(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|FOR SHARE)\b/',$q)&&!str_starts_with($q,'SHOW CREATE')),'reader never mutates or runs locking eligibility');
        $check(!$reader->inTransaction()&&!$reader->lock,'reader released before B');
        $check($capture['tables']['site_deployments']['rows']===[]&&$capture['tables']['site_approvals']['row_count']===2,'empty and populated tables');
        $check($capture['deployment_authority']==='NONE'&&$capture['restore']===$capture['admission']&&$capture['restore']==='UNIMPLEMENTED','authority remains closed');
        $reject(static fn()=>SiteDeploymentContract::request($capture,'publish'),'capture cannot request deployment');
        $reject(static fn()=>SiteDeploymentContract::lease($capture),'capture cannot claim lease');
        foreach(['schema','server_uuid','account'] as $key){$old=$reader->identity[$key];$reader->identity[$key]='wrong';$reject($run,'reader identity '.$key);$reader->identity[$key]=$old;}
        $reader->version='8.0.0';$reject($run,'wrong server');$reader->version='8.4.model';
        $reader->connection=1;$reject($run,'reader cannot reuse builder connection');$reader->connection=2;
        $reader->role='unreviewed_role';$reject($run,'no inherited role');$reader->role='NONE';
        $reader->tls='';$reject($run,'reader requires TLS');$reader->tls='MODEL_TLS';
        $reader->extraGrants=['GRANT SELECT ON *.* TO `m6e_snapshot`@`127.0.0.1`'];$reject($run,'cross schema privilege refused');$reader->extraGrants=[];
        $reader->isolation='READ-COMMITTED';$reject($run,'wrong isolation');$reader->isolation='REPEATABLE-READ';
        $reader->readOnly=0;$reject($run,'read/write transaction');$reader->readOnly=1;
        $old=$reader->schema['notes'];unset($reader->schema['notes']);$reject($run,'missing table');$reader->schema['notes']=$old;ksort($reader->schema);
        $reader->schema['notes']['definition'].=' CHANGED';$reject($run,'schema drift');$reader->schema['notes']=$old;
        foreach(['site_releases','site_approvals','site_build_attempts'] as $table){$old=$reader->tables[$table];$reader->tables[$table]=[];$reject($run,'missing original row');
            $reader->tables[$table]=[...$old,$old[0]];$reject($run,'duplicate original row');$reader->tables[$table]=$old;}
        $old=$reader->tables['site_revisions'];$reader->tables['site_revisions'][]=array_replace($old[0],['id'=>999,'revision_number'=>99]);$reject($run,'B before completion');$reader->tables['site_revisions']=$old;
        $old=$reader->tables['notes'];$reader->tables['notes']=array_fill(0,2049,$old[0]);$reject($run,'row limit');
        $reader->tables['notes']=$old;$reader->tables['notes'][0]['body']=str_repeat('x',262145);$reject($run,'cell limit');$reader->tables['notes']=$old;
        $base=$f->db->read->base;$saved=$base->approvals;
        $reader->onRows=static function()use($base):void{$base->approvals[701]['actor_user_id']=777;};$reject($run,'source changed during capture');$base->approvals=$saved;
        $base->revisions[999]=array_replace($base->revisions[100],['id'=>999,'revision_number'=>99,'materiality'=>'material']);
        $reject(static fn()=>m6eSnapshotFinalBoundary($observer,$id,$cp),'B created during file verification');unset($base->revisions[999]);
        m6eSnapshotFinalBoundary($observer,$id,$cp);$check(true,'final unchanged source check after file verification');
        $f->db->beginTransaction();$reject($run,'uncommitted A boundary');$f->db->rollBack();
        $reader->fail=['COMMIT','ROLLBACK','UNLOCK TABLES'];$reject($run,'interrupted capture preserves primary');
        $check($outcome['first_failure']==='snapshot_commit'&&$outcome['secondary']===['snapshot_rollback','snapshot_unlock']
            &&$outcome['transaction_cleanup']==='UNRESOLVED','first failure and cleanup secondary errors');
        $reader->fail=[];$reader->transaction=$reader->lock=false;
        $bad=$a;$bad['expires_at']=time()-1;
        $reject(static function()use($reader,$bad,$baseline,$cp,$observe,&$outcome){m6eCaptureSnapshot($reader,$bad,$baseline,$cp,$observe,1,$outcome);},'expired capture');
        $bundle=['capture'=>$capture,'baseline'=>$baseline,'checkpoint'=>$cp,'config'=>$a];
    });
    $check(count($built)===2&&$f->builds===2,'original A+B fixture still completes');
    foreach($built as $r)$check(m6eReleaseSummary($r['row'],$r['job'],$r['producer'],$f->store->verifyRelease($r['job'],$r['producer']))===$r['summary'],'original artifact bytes preserved');
    $text=file_get_contents(__DIR__.'/support/WebsitePlatformM6ENativeSetup.php');
    $check(strpos($text,'m6eNativeCheckpoint($db,$config,$a,$first);')<strpos($text,'m6eNativeSnapshot($db,$a,$first);')
        &&strpos($text,'m6eNativeSnapshot($db,$a,$first);')<strpos($text,'$next=SiteRevisionManager::createRevision'),'synchronous native pre-B ordering');
    if(($argv[1]??'')==='--bundle')echo CanonicalJson::encode($bundle)."\n"; // Private parent/child pipe only, never test logs.
    else echo "M6E snapshot: $checks assertions PASS (SQL boundary/metadata model; real A/B artifact bytes; native SQL UNEXECUTED).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
