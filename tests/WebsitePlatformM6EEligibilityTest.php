<?php
declare(strict_types=1);
/** Actual unchanged eligibility service and real sealed bytes; SQL/locks/privileges remain MODELS. */
define('M6E_CHECKPOINT_CLASSES_ONLY',true);
require_once __DIR__.'/WebsitePlatformM6ECheckpointTest.php';
require_once __DIR__.'/support/WebsitePlatformM6EEligibility.php';

final class EligibilityStatement extends PDOStatement {
    private array $rows=[];
    public function __construct(private EligibilityDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool{$this->rows=$this->db->rows($this->sql,$params??[]);return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->rows)??false;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{$r=$this->rows;$this->rows=[];return $r;}
    public function fetchColumn(int $column=0):mixed{$r=array_shift($this->rows);return $r===null?false:array_values($r)[$column];}
    public function closeCursor():bool{return true;}
}
function eligibilityCopyDatabase(WebsitePlatformM6BDatabase $original):WebsitePlatformM6BDatabase {
    $db=new WebsitePlatformM6BDatabase();foreach(get_object_vars($original) as $k=>$v)$db->$k=$v;
    $db->read=new WebsitePlatformM5ADatabase();foreach(get_object_vars($original->read) as $k=>$v)$db->read->$k=$v;
    $db->read->base=new WebsitePlatformM3ServiceDatabase();foreach(get_object_vars($original->read->base) as $k=>$v)$db->read->base->$k=$v;
    return $db;
}
final class EligibilityDatabase extends PDO {
    public array $identity,$grants=[],$trace=[],$counts=[];
    public bool $unknownRollback=false,$failRollback=false,$readOnly=false;public ?string $failSql=null;
    public function __construct(public WebsitePlatformM6BDatabase $inner,array $cp){
        $this->identity=['schema'=>'ubo_m6e_lifecycle_'.$cp['binding']['run_id'],'server_uuid'=>$cp['observed']['database']['server_uuid'],
            'account'=>M6E_ELIGIBILITY_USER.'@127.0.0.1'];
        $account='`'.M6E_ELIGIBILITY_USER.'`@`127.0.0.1`';
        $this->grants=['GRANT LOCK TABLES ON `'.str_replace('_','\\_',$this->identity['schema']).'`.* TO '.$account];
        foreach(M6E_ELIGIBILITY_TABLES as $table)$this->grants[]='GRANT SELECT ON `'.$this->identity['schema'].'`.`'.$table.'` TO '.$account;
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new EligibilityStatement($this,$query);}
    public function inTransaction():bool{return $this->inner->inTransaction();}
    public function beginTransaction():bool{$this->trace[]='BEGIN';return $this->inner->beginTransaction();}
    public function commit():bool{throw new RuntimeException('FORBIDDEN_MODEL_COMMIT');}
    public function rollBack():bool{
        $this->trace[]='ROLLBACK';if($this->failRollback)throw new PDOException('PRIVATE_ROLLBACK');
        $value=$this->inner->rollBack();if($this->unknownRollback)throw new PDOException('PRIVATE_UNKNOWN_ACK');return $value;
    }
    public function exec(string $sql):int|false{
        m6eNeed(str_starts_with($sql,'SET SESSION '),'model_mutation');$this->trace[]=$sql;return 0;
    }
    public function rows(string $sql,array $params):array {
        $this->trace[]=$sql;if($this->failSql!==null&&str_contains($sql,$this->failSql))throw new PDOException('PRIVATE_SQL_CANARY');
        foreach(['SELECT DATABASE()'=>'schema','SELECT @@server_uuid'=>'server_uuid','SELECT CURRENT_USER()'=>'account'] as $q=>$k)
            if($sql===$q)return [[$k=>$this->identity[$k]]];
        if($sql==='SELECT VERSION()')return [['version'=>'8.4.model']];
        if($sql==='SELECT CURRENT_ROLE()')return [['role'=>'NONE']];
        if($sql==='SELECT CONNECTION_ID()')return [['id'=>81]];
        if($sql==='SELECT @@session.transaction_isolation')return [['isolation'=>'REPEATABLE-READ']];
        if($sql==='SELECT @@session.transaction_read_only')return [['readonly'=>$this->readOnly?'1':'0']];
        if($sql==='SELECT @@session.autocommit')return [['autocommit'=>'1']];
        if($sql==="SHOW SESSION STATUS LIKE 'Ssl_cipher'")return [['Value'=>'MODELED_TLS']];
        if($sql==='SHOW GRANTS')return array_map(static fn($g)=>['grant'=>$g],$this->grants);
        if(preg_match('/^SELECT COUNT\(\*\) FROM (site_deployment\w+)$/D',$sql,$m))return [['count'=>$this->counts[$m[1]]??0]];
        $query=preg_replace('~/\*.*?\*/~s','',$sql);
        m6eNeed(preg_match('/^\s*SELECT\b/i',$query)===1&&!preg_match('/;|\bINTO\b/i',$query),'model_non_select_denied');
        preg_match_all('/\b(?:FROM|JOIN)\s+([a-z0-9_]+)/i',$query,$tables);
        foreach($tables[1] as $table)m6eNeed(in_array($table,M6E_ELIGIBILITY_TABLES,true),'model_select_privilege');
        if(str_contains($sql,'FOR UPDATE')||str_contains($sql,'FOR SHARE'))m6eNeed($this->inTransaction()&&!$this->readOnly,'model_lock_boundary');
        return $this->inner->executeSql($sql,$params)[0];
    }
}
$checks=0;$check=static function(bool $v,string $why)use(&$checks):void{$checks++;if(!$v)throw new RuntimeException($why);};
$reject=static function(callable $f,string $why)use($check):void{try{$f();}catch(Throwable){$check(true,$why);return;}throw new RuntimeException('Not rejected: '.$why);};
$a=['run_id'=>str_repeat('b',32),'source_sha'=>str_repeat('a',40),'machine_id'=>str_repeat('c',32),
    'boot_id'=>'11111111-1111-4111-8111-111111111111','expires_at'=>time()+3000];
try {
    [$fixture,$built]=m6eLocalServingFixture(false,static function($f,$first)use($a,$check,$reject):void {
        $source=new M6ECheckpointDatabase($f->db);
        $cp=m6eCaptureCheckpoint($source,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],(int)$first['row']['id'],$source->identity,$a,'controlled-local');
        $frozen=eligibilityCopyDatabase($f->db);$original=$f->db->snapshot();
        $req=['binding'=>$cp['binding'],'schema'=>'ubo_m6e_lifecycle_'.$a['run_id'],
            'server_uuid'=>$source->identity['server_uuid'],'checkpoint_sha256'=>CanonicalJson::hash($cp)];
        $fresh=static fn()=>new EligibilityDatabase(eligibilityCopyDatabase($frozen),$cp);
        $run=static function($db,$r=null,$pulse=null)use($a,$cp,$req):array {
            $outcome=[];
            try{m6eEligibilityTransaction($db,$a,$cp,$r??$req,$pulse??static function():void{},$outcome);}
            finally{$GLOBALS['eligibility_outcome']=$outcome;}return $outcome;
        };
        $db=$fresh();$before=$db->inner->snapshot();$value=$run($db);
        $check($value['gate_returned']&&$value['result']==='AWAITING_INDEPENDENT_OBSERVER','actual ordinary gate accepted original A');
        $check($value['transaction']==='ROLLED_BACK'&&$value['closed']&&!$db->inTransaction(),'rollback confirmed');
        $check($db->inner->snapshot()===$before&&$f->db->snapshot()===$original,'destination and original model unchanged');
        $check(in_array('SET SESSION TRANSACTION READ WRITE',$db->trace,true)&&in_array('ROLLBACK',$db->trace,true),'correct access mode and closure');
        $check(count(array_filter($db->trace,static fn($q)=>str_contains($q,'FOR UPDATE')))>5,'original locks retained');
        $check(count(M6E_ELIGIBILITY_TABLES)===24&&!array_filter($db->grants,static fn($g)=>preg_match('/GRANT (?:UPDATE|DELETE|INSERT|ALL|CREATE)/',$g)),'nonmutating grants on exact 24 read tables');
        $expected=$cp['observed'];$expected['database']=$db->identity;
        $check($value['observed_sha256']===CanonicalJson::hash($expected),'all original eligibility associations and hashes');
        $check($value['absence']===array_fill_keys(['site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks'],0),'zero targets and effects');
        $check(m6eReleaseSummary($first['row'],$first['job'],$first['producer'],$f->store->verifyRelease($first['job'],$first['producer']))===$cp['summary'],'real original sealed bytes independently verified');
        foreach(['schema','server_uuid','account'] as $key){$bad=$fresh();$bad->identity[$key]='wrong';$reject(static fn()=>$run($bad),'wrong '.$key);}
        $bad=$fresh();array_shift($bad->grants);$reject(static fn()=>$run($bad),'SELECT alone cannot satisfy gate privilege');
        $bad=$fresh();$bad->grants[]='GRANT SELECT ON *.* TO `m6e_lifecycle_eligibility`@`127.0.0.1`';$reject(static fn()=>$run($bad),'broad grant');
        $bad=$fresh();$bad->readOnly=true;$reject(static fn()=>$run($bad),'READ ONLY forbidden for locking gate');
        $bad=$fresh();$bad->beginTransaction();$reject(static fn()=>$run($bad),'caller transaction forbidden');
        $check($bad->inTransaction(),'unowned caller transaction is not rolled back');$bad->rollBack();
        $bad=$req;$bad['checkpoint_sha256']=str_repeat('0',64);$reject(static fn()=>$run($fresh(),$bad),'stale checkpoint');
        $bad=$fresh();$bad->inner->read->base->revisions[100]['snapshot_hash']=str_repeat('0',64);$reject(static fn()=>$run($bad),'changed source hash');
        foreach([700,701] as $id){$bad=$fresh();unset($bad->inner->read->base->approvals[$id]);$reject(static fn()=>$run($bad),'missing approval');}
        $bad=$fresh();$bad->inner->read->base->approvals[701]['revoked_at']='2026-11-04 00:00:00';$reject(static fn()=>$run($bad),'revoked approval');
        $bad=$fresh();$bad->inner->read->associations[0]['business_id']=60;$reject(static fn()=>$run($bad),'foreign business');
        $bad=$fresh();unset($bad->inner->tables['site_build_attempts'][$cp['summary']['producer_attempt_id']]);$reject(static fn()=>$run($bad),'missing producer');
        $bad=$fresh();unset($bad->inner->tables['site_releases'][$cp['summary']['release_id']]);$reject(static fn()=>$run($bad),'missing release');
        $bad=$fresh();$bad->inner->read->base->revisions[101]=array_replace($bad->inner->read->base->revisions[100],['id'=>101,'revision_number'=>3,'materiality'=>'material']);
        $reject(static fn()=>$run($bad),'newer material B');
        foreach(array_keys($value['absence']) as $table){$bad=$fresh();$bad->counts[$table]=1;$reject(static fn()=>$run($bad),'operational row or target fence '.$table);}
        $bad=$fresh();$bad->unknownRollback=true;$reject(static fn()=>$run($bad),'unknown rollback acknowledgement');
        $check($GLOBALS['eligibility_outcome']['transaction']==='UNKNOWN'&&!$GLOBALS['eligibility_outcome']['closed'],'unknown closure cannot become proof');
        $bad=$fresh();$bad->failSql='FROM site_build_jobs';$bad->failRollback=true;$reject(static fn()=>$run($bad),'failed SQL and cleanup');
        $check($GLOBALS['eligibility_outcome']['first_failure']==='eligibility_original'
            &&$GLOBALS['eligibility_outcome']['secondary']===['eligibility_cleanup_rollback'],'first failure and rollback cleanup preserved');
        $reject(static fn()=>$run($fresh(),null,static function():void{throw new RuntimeException('expired');}),'expired execution');
        $reject(static fn()=>SiteDeploymentContract::request($value,'publish'),'preflight cannot be request');
        $reject(static fn()=>SiteDeploymentContract::lease($value),'preflight cannot be lease');
        $asset=$f->root.'/sealed/'.$first['row']['storage_key'].'/public/index.html';$bytes=file_get_contents($asset);
        try{file_put_contents($asset,$bytes.'corrupt');$reject(static fn()=>m6eReleaseSummary($first['row'],$first['job'],$first['producer'],
            $f->store->verifyRelease($first['job'],$first['producer'])),'unverified artifact rejected');}
        finally{file_put_contents($asset,$bytes);}
        $check($f->db->snapshot()===$original,'all negatives leave original fixture intact');
    });
    $final=new M6ECheckpointDatabase($fixture->db);$before=$fixture->db->snapshot();m6eFinalAIneligible($final,(int)$built[0]['row']['id']);
    $check($fixture->db->snapshot()===$before,'completed A+B still rejects A without effects');
    echo "M6E eligibility: $checks assertions PASS (actual unchanged source gate; SQL/lock/privilege MODEL; real M6C bytes; native UNEXECUTED).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
