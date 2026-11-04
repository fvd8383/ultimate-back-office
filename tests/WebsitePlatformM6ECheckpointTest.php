<?php
declare(strict_types=1);
namespace Ubo\M6E {
    // Only Unix modes are modeled on Windows; bytes, descriptors and identities stay real.
    function lstat(string $path):array|false {$s=\lstat($path);
        if($s!==false&&PHP_OS_FAMILY==='Windows')$s['mode']=($s['mode']&0170000)|(($s['mode']&0170000)===0040000?0700:0600);
        return $s;}
    function fstat(mixed $h):array|false {$s=\fstat($h);
        if($s!==false&&PHP_OS_FAMILY==='Windows')$s['mode']=($s['mode']&0170000)|0600;return $s;}
}
namespace {
if(($argv[1]??'')==='--cold'){
    require_once __DIR__.'/support/WebsitePlatformM6ENativeSetup.php';
    require_once __DIR__.'/support/WebsitePlatformM6ECheckpoint.php';
    m6eNeed(class_exists('SiteDeploymentService',false)&&class_exists('SiteRevisionManager',false)
        &&!class_exists('WebsitePlatformM6CFixture',false),'checkpoint_cold_dependencies');
    m6eNeed((new ReflectionProperty(Database::class,'connection'))->getValue()===null
        &&(new ReflectionProperty(Database::class,'config'))->getValue()===null
        &&(new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->getValue()===null,'checkpoint_cold_no_runtime');
    SiteDeploymentService::guardExternal();echo "checkpoint cold include PASS\n";exit(0);
}
require_once __DIR__.'/support/WebsitePlatformM6ECommissionFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6ECheckpoint.php';
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';

/** Sequential PDO adapter ONLY. Does not model independent sessions, locks, FKs or TLS. */
final class M6ECheckpointStatement extends PDOStatement {
    private array $rows=[];
    public function __construct(private M6ECheckpointDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool {$this->rows=$this->db->rows($this->sql,$params??[]);return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return array_shift($this->rows)??false;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{$r=$this->rows;$this->rows=[];return $r;}
    public function fetchColumn(int $column=0):mixed{$r=array_shift($this->rows);return $r===null?false:array_values($r)[$column];}
}
final class M6ECheckpointDatabase extends PDO {
    public array $identity;public array $counts=[];public array $queries=[];public $afterCommit=null;
    public function __construct(public WebsitePlatformM6BDatabase $inner){$this->identity=['schema'=>'ubo_m6e_'.str_repeat('b',32),
        'server_uuid'=>'22222222-2222-4222-8222-222222222222','account'=>'m6e_worker@127.0.0.1'];}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new M6ECheckpointStatement($this,$query);}
    public function beginTransaction():bool{return $this->inner->beginTransaction();}
    public function commit():bool{$ok=$this->inner->commit();if($this->afterCommit!==null){$f=$this->afterCommit;$this->afterCommit=null;$f();}return $ok;}
    public function rollBack():bool{return $this->inner->rollBack();}
    public function inTransaction():bool{return $this->inner->inTransaction();}
    public function rows(string $sql,array $params):array{
        $this->queries[]=$sql;
        foreach(['SELECT DATABASE()'=>'schema','SELECT @@server_uuid'=>'server_uuid','SELECT CURRENT_USER()'=>'account'] as $q=>$key)
            if($sql===$q)return [[$key=>$this->identity[$key]]];
        if(preg_match('/^SELECT COUNT\(\*\) FROM (site_deployment\w+)$/D',$sql,$m))return [['count'=>$this->counts[$m[1]]??0]];
        return $this->inner->executeSql($sql,$params)[0];
    }
}
if(defined('M6E_CHECKPOINT_CLASSES_ONLY'))return;
$assertions=0;
$check=static function(bool $yes,string $why)use(&$assertions):void{$assertions++;if(!$yes)throw new RuntimeException($why);};
$reject=static function(Closure $f,string $why)use($check):void{$thrown=false;try{$f();}catch(Throwable){$thrown=true;}$check($thrown,$why);};
$ineligible=static function(Closure $f,string $why)use($check):void{$denied=false;try{$f();}
    catch(SiteServiceException $e){if($e->classification()!=='invalid_transition')throw $e;$denied=true;}$check($denied,$why);};
$a=['run_id'=>str_repeat('b',32),'source_sha'=>str_repeat('a',40),'machine_id'=>str_repeat('c',32),
    'boot_id'=>'11111111-1111-4111-8111-111111111111','expires_at'=>time()+3000];
$checkpoint=null;$original=null;$db=null;$model=null;
try {
    [$f,$built]=m6eLocalServingFixture(false,static function($f,$first)use(&$checkpoint,&$original,&$db,&$model,$a,$check,$reject,$ineligible):void {
        $db=new M6ECheckpointDatabase($f->db);$base=$f->db->read->base;$identity=$db->identity;
        $capture=static fn()=>m6eCaptureCheckpoint($db,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],(int)$first['row']['id'],$identity,$a,'controlled-local');
        $state=$f->db->snapshot();$checkpoint=$capture();$original=$first;
        $check($checkpoint['observed']['source']['revision']['id']===100,'Original A eligible before B');
        $check($state===$f->db->snapshot(),'Capture no database changes, events, attempts or leases');
        $check($checkpoint['summary']===$first['summary']&&$checkpoint['files']===$first['verified']['manifest']['files'],'Real original A bytes and producer');
        $check($checkpoint['observed']['source']['approvals']===['internal_approval_id'=>701,'customer_approval_id'=>700],'Inherited customer approval preserved');
        $check($checkpoint['observed']['approvals']['customer_approval_id']['revision_id']===99,'No approval revision rewriting');
        $check($checkpoint['database_snapshot']==='UNIMPLEMENTED'&&$checkpoint['deployment_authority']==='NONE','Descriptor is not snapshot or authority');
        $check(m6eLifecycleBinding($a['run_id'])['schema']!==$db->identity['schema'],'Separate future schema');
        $check(count(array_filter($db->queries,static fn($s)=>!str_starts_with($s,'SELECT')&&!str_starts_with($s,'/*')))===0,'Only SELECTs');
        $reject(static fn()=>SiteDeploymentContract::request($checkpoint,'publish'),'Checkpoint is not a deployment request');
        $reject(static fn()=>SiteDeploymentContract::lease($checkpoint),'Checkpoint is not a publisher lease');
        $db->beginTransaction();$reject($capture,'Uncommitted capture rejected');$db->rollBack();
        foreach(['schema','server_uuid','account'] as $key){$old=$db->identity[$key];$db->identity[$key]='wrong';$reject($capture,'Database '.$key);$db->identity[$key]=$old;}
        foreach(['site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks'] as $table){
            $db->counts[$table]=1;$reject($capture,'No '.$table);$db->counts[$table]=0;}
        foreach([
            static function()use($base):void{$base->sites[10]['current_published_revision_id']=100;},
            static function()use($base):void{$base->revisions[100]['site_id']=11;},
            static function()use($base):void{$base->revisions[100]['snapshot_hash']=str_repeat('f',64);},
            static function()use($base):void{$base->revisions[100]['source_references_json']='{"business":{"business_id":51}}';},
            static function()use($base):void{$base->approvals[701]['revoked_at']='2026-09-20 00:00:00';},
            static function()use($base):void{unset($base->approvals[700]);},
            static function()use($base):void{$base->approvals[702]=array_replace($base->approvals[701],['id'=>702]);},
            static function()use($base):void{$base->revisions[101]=array_replace($base->revisions[100],['id'=>101,'revision_number'=>3,'materiality'=>'non_material','lifecycle_status'=>'draft']);}
        ] as $change){
            $saved=[$base->sites,$base->revisions,$base->approvals];$change();$reject($capture,'Source mismatch/incomplete/ambiguous/newer');
            [$base->sites,$base->revisions,$base->approvals]=$saved;
        }
        $old=$f->db->read->associations;$f->db->read->associations[]=$old[0];$reject($capture,'Ambiguous customer association');$f->db->read->associations=$old;
        $f->db->read->associations[0]['id']=999;$reject($capture,'Substituted original association');$f->db->read->associations=$old;
        $base->revisions[101]=array_replace($base->revisions[100],['id'=>101,'revision_number'=>3,'materiality'=>'material']);
        $reject($capture,'Native-shaped newer material B rejects A');m6eFinalAIneligible($db,(int)$first['row']['id']);
        $check(true,'Unchanged final source gate rejects newer B');unset($base->revisions[101]);
        $saved=$base->approvals;$db->afterCommit=static function()use($base):void{$base->approvals[701]['actor_user_id']=777;};
        $reject($capture,'State changed between SQL observations');$base->approvals=$saved;
        $saved=$f->db->tables['site_releases'];$f->db->tables['site_releases'][$first['row']['id']]['artifact_hash']=str_repeat('0',64);
        $reject($capture,'Artifact substitution');$f->db->tables['site_releases']=$saved;
        $path=$f->releasePath($first['job']).'/public/index.html';$bytes=file_get_contents($path);
        try {file_put_contents($path,'tampered');$reject($capture,'Actual immutable artifact bytes changed');}
        finally {file_put_contents($path,$bytes);}
        $bad=$a;$bad['source_sha']=str_repeat('f',40);
        $reject(static fn()=>m6eCaptureCheckpoint($db,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],(int)$first['row']['id'],$identity,$bad,'controlled-local'),'Source provenance mismatch');
        $bad=$a;$bad['expires_at']=time()-1;
        $reject(static fn()=>m6eCaptureCheckpoint($db,$f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid'],(int)$first['row']['id'],$identity,$bad,'controlled-local'),'Stale capture');
        // Exercise the actual request owner against fake SQL. It must fail at its
        // source gate, before readiness, artifact I/O or any deployment INSERT.
        $model=new M6DDatabase();$model->inner=$f->db;
        $model->tables['site_deployment_targets'][20]['site_id']=10;
        $base->revisions[101]=array_replace($base->revisions[100],['id'=>101,'revision_number'=>3,'materiality'=>'material']);
        $before=$model->snapshot();$ineligible(static fn()=>$model->request(),'Fresh A deployment rejected after newer B');
        $check($model->snapshot()===$before&&$model->tables['site_deployments']===[],'Rejected request has no effects');
        unset($base->revisions[101]);(new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);
        (new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,$f);
    });
    $check(count($built)===2&&$f->builds===2,'Original two-release builder completes unchanged');
    $sets=m6eAssetSets(array_column($built,'summary'));
    $check($sets['shared_assets']!==[]&&$sets['changed_assets']!==[]&&$sets['pdf_assets']!==[],'Original asset comparisons');
    foreach($built as $r)$check(m6eReleaseSummary($r['row'],$r['job'],$r['producer'],$f->store->verifyRelease($r['job'],$r['producer']))===$r['summary'],'Original sealed release preserved');
    $check($checkpoint['observed']['source']['revision']['snapshot_hash']===$built[0]['row']['source_snapshot_hash']
        &&$checkpoint['observed']['source']['revision']['snapshot_hash']!==$f->db->read->base->revisions[100]['snapshot_hash'],'Checkpoint binds pre-B, not completed local fixture');
    m6eFinalAIneligible($db,(int)$built[0]['row']['id']);$check(true,'Completed original local fixture A-ineligible');
    $before=$model->snapshot();(new ReflectionProperty(Database::class,'connection'))->setValue(null,$model);
    $ineligible(static fn()=>$model->request(),'Completed original local fixture denies fresh A request');
    $check($model->snapshot()===$before,'Completed fixture negative request has no effects');
    // Optional bounded stdout only for Python cross-language integrity controls.
    if(($argv[1]??'')==='--descriptor')echo CanonicalJson::encode(['checkpoint'=>$checkpoint,'fixture'=>[
        'run_id'=>$a['run_id'],'site_id'=>10,'site_key'=>$built[0]['release']['site_key'],'releases'=>array_column($built,'summary')]])."\n";
    else echo "M6E checkpoint: $assertions assertions PASS (real M6C bytes; sequential fake PDO; native SQL/session/restore UNEXECUTED).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
}
