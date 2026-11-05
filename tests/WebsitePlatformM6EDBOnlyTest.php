<?php
declare(strict_types=1);
/** Local only: actual builder/store and shared account constructors; PDO/privileges are MODELS. */
require_once __DIR__.'/support/WebsitePlatformM6EDBOnlyChild.php';
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';

final class DbOnlyStatement extends PDOStatement {
    private array $rows=[];
    public function __construct(private DbOnlyDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool{$this->rows=$this->db->rows($this->sql,$params??[]);return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function closeCursor():bool{return true;}
    public function fetchColumn(int $column=0):mixed{return array_values($this->rows[0])[0];}
}
final class DbOnlyDatabase extends PDO {
    public array $trace=[];public int $denial=1142;public bool $denyDml=false;public bool $extra=false;
    public ?WebsitePlatformM6BDatabase $build=null;
    public string $schema;
    public function __construct(){$this->schema='ubo_m6e_lifecycle_'.str_repeat('b',32);}
    public function quote(string $value,int $type=PDO::PARAM_STR):string{return "'".$value."'";}
    public function prepare(string $sql,array $options=[]):PDOStatement|false{return new DbOnlyStatement($this,$sql);}
    public function exec(string $sql):int|false{
        $this->trace[]=$sql;
        if($this->denyDml){$e=new PDOException('PRIVATE_SQL');$e->errorInfo=['42000',$this->denial,'PRIVATE_SQL'];throw $e;}
        return 0;
    }
    public function rows(string $sql,array $params):array{
        $this->trace[]=$sql;
        if($sql==='SELECT * FROM site_releases WHERE build_job_id=:id')return array_values(array_filter($this->build->tables['site_releases'],static fn($r)=>(int)$r['build_job_id']===(int)$params['id']));
        if(str_starts_with($sql,'SELECT SCHEMA_NAME')||str_starts_with($sql,'SELECT User'))return [];
        if(str_starts_with($sql,'SELECT COUNT(*)'))return [['count'=>'0']];
        if(str_starts_with($sql,'SELECT account_locked, ssl_type, max_user_connections'))return [['account_locked'=>'Y','ssl_type'=>'ANY','max_user_connections'=>'1']];
        if(str_starts_with($sql,'SELECT account_locked'))return [['account_locked'=>$params[0]==='m6e_lifecycle_worker'?'Y':'N','ssl_type'=>'ANY']];
        if(str_starts_with($sql,'SHOW GRANTS')){
            if(str_contains($sql,M6E_ELIGIBILITY_USER)){
                $account='`'.M6E_ELIGIBILITY_USER.'`@`127.0.0.1`';
                $g=['GRANT LOCK TABLES ON `'.str_replace('_','\\_',$this->schema).'`.* TO '.$account];
                foreach(M6E_ELIGIBILITY_TABLES as $table)$g[]='GRANT SELECT ON `'.$this->schema.'`.`'.$table.'` TO '.$account;
                if($this->extra)$g[]='GRANT UPDATE ON `'.$this->schema.'`.* TO '.$account;
                return array_map(static fn($s)=>['grant'=>$s],$g);
            }
            $writer=str_contains($sql,'m6e_lifecycle_worker');$user=$writer?'m6e_lifecycle_worker':'m6e_lifecycle_observer';
            return [['grant'=>'GRANT '.($writer?implode(', ',M6E_RESTORE_RIGHTS):'SELECT').' ON `'.str_replace('_','\\_',$this->schema).'`.* TO `'.$user.'`@`127.0.0.1`']];
        }
        throw new RuntimeException('unmodeled_query');
    }
}
$assertions=0;
$check=static function(bool $v,string $why)use(&$assertions):void{$assertions++;if(!$v)throw new RuntimeException($why);};
$reject=static function(callable $f,string $why)use($check):void{$denied=false;try{$f();}catch(Throwable){$denied=true;}$check($denied,$why);};
try {
    $f=new WebsitePlatformM6CFixture();$request=$f->db->request();
    $read=new DbOnlyDatabase();$read->build=$f->db;$releaseId=m6eFinishBuild($read,(int)$request['id']);
    $release=$f->db->tables['site_releases'][$releaseId];$job=$f->db->tables['site_build_jobs'][$release['build_job_id']];
    $producer=$f->db->tables['site_build_attempts'][$job['current_attempt_id']];
    $verified=$f->store->verifyRelease($job,$producer);$summary=m6eReleaseSummary($release,$job,$producer,$verified);
    $check($f->builds===1&&$job['status']==='succeeded'&&$producer['status']==='succeeded','actual shared finishBuild commits genuine builder outcome');
    $check($summary['manifest_sha256']===$release['manifest_hash']&&$summary['artifact_sha256']===$release['artifact_hash'],'real sealed artifact matches committed release');
    $check(!$f->db->inTransaction()&&!isset($f->db->tables['site_deployments']),'no deployment and no open transaction');
    $db=new DbOnlyDatabase();$a=['run_id'=>str_repeat('b',32)];$d=m6eLifecycleBinding($a['run_id']);
    $inputs=['request'=>['destination'=>$d,'server_uuid'=>'22222222-2222-4222-8222-222222222222']];$created=[];
    $v=m6eRestoreCreate($db,$a,$inputs,static function(string $leaf,array $value)use(&$created):void{$created[$leaf]=$value;});
    $check($v['created']==='EXCLUSIVE'&&count($created)===2,'restoration seam retains exact account constructor');
    foreach($created as $secret)$check(strlen($secret['password'])===64&&!str_contains(CanonicalJson::encode($v),$secret['password']),'protected random secrets absent from public result');
    $check(count(array_filter($db->trace,static fn($s)=>str_contains($s,'REQUIRE SSL')))===2,'both restoration identities require TLS');
    $check(count(array_filter($db->trace,static fn($s)=>str_starts_with($s,'GRANT SELECT ON')))===1,'separate SELECT-only observer');
    $secret=null;$db->trace=[];
    $prepared=m6eEligibilityAccount($db,$inputs,true,static function(array $value)use(&$secret):void{$secret=$value;});
    $check($secret['username']===M6E_ELIGIBILITY_USER&&strlen($secret['password'])===64,'dedicated eligibility credentials');
    $grants=array_values(array_filter($db->trace,static fn($s)=>str_starts_with($s,'GRANT ')));
    $check(count($grants)===25,'exact 24 SELECT tables and one schema LOCK TABLES grant');
    foreach($grants as $sql)$check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|GRANT OPTION)\b/',$sql),'eligibility no DML or DDL grants');
    $check(str_contains(implode("\n",$db->trace),'REQUIRE SSL WITH MAX_USER_CONNECTIONS 1'),'eligibility TLS and single connection policy');
    $seal=m6eEligibilityAccount($db,$inputs,false,static function():void{throw new RuntimeException('must not issue new secret on seal');});
    $check($seal['locked']===true&&$seal['sessions']===0&&$seal['restorer_processes']===0,'shared account seal retains SQL observation contract');
    $db->extra=true;$reject(static fn()=>m6eEligibilityAccount($db,$inputs,false,static fn()=>null),'excess grants rejected');
    $db=new DbOnlyDatabase();$db->denyDml=true;$check(m6eDbDeniedDml($db)===[1142,1142,1142],'actual probe demands precise MySQL access-denied codes');
    $db->denial=1146;$reject(static fn()=>m6eDbDeniedDml($db),'missing table cannot masquerade as denied DML');
    $db->denyDml=false;$reject(static fn()=>m6eDbDeniedDml($db),'successful DML cannot masquerade as denied permission');
    $evidence=['profile'=>'m6e-db-only-v1','purpose'=>'DB-ONLY COMPONENT VALIDATION','result'=>'DB-ONLY COMPONENT VALIDATION PASS',
        'admission'=>'UNIMPLEMENTED','native_admission'=>'BLOCKED','deployment_authority'=>'NONE'];
    $reject(static fn()=>SitePublisherHostContract::validate($evidence,$evidence,str_repeat('a',64),time()),'DB-only evidence cannot become installed host receipt');
    $reject(static fn()=>SiteDeploymentContract::request($evidence,'publish'),'DB-only evidence cannot become deployment request');
    $reject(static fn()=>SiteDeploymentContract::lease($evidence),'DB-only evidence cannot become operational lease');
    $reject(static fn()=>SiteBuildRuntime::fromTrustedCli(),'test driver cannot become installed build-worker admission');
    echo "M6E DB-only adapter: $assertions assertions PASS (PDO/privilege MODEL, real builder/store; native UNEXECUTED).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
