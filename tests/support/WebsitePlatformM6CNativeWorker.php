<?php
declare(strict_types=1);
// Every entry is explicit, fixed to the disposable container. Never ordinary discovery.
if(PHP_SAPI==='cli'&&($argv[1]??'')==='--help'){
    echo "Internal M6C container child; launched only by WebsitePlatformM6CNative.php.\n";exit(0);
}
require_once __DIR__.'/WebsitePlatformM6CNativeWorkerError.php';
$phase='context'; // Attempted operation only, never evidence of a committed claim.
function m6nBarrier(string $name,array $data=[]): void
{
    global $phase;$prior=$phase;$phase='wait_'.$name;
    echo $name.' '.json_encode($data,JSON_THROW_ON_ERROR)."\n";flush();
    stream_set_timeout(STDIN,15);$line=fgets(STDIN,8193);
    if($line!=="CONTINUE\n")throw new RuntimeException('native_barrier_release');
    $phase=$prior;
}
function m6nWorkerResult(array $result): never
{global $phase;$phase='result';echo 'RESULT '.json_encode($result,JSON_THROW_ON_ERROR)."\n";exit(0);}
try{
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo'
        ||!is_file('/run/m6c/context.json')||fileowner('/run/m6c/context.json')!==0)throw new RuntimeException('native_container_only');
    $context=json_decode(file_get_contents('/run/m6c/context.json'),true,32,JSON_THROW_ON_ERROR);
    if(($context['version']??'')!=='m6c-native-v1')throw new RuntimeException('native_context');
    $mode=$argv[1]??'';
    if($mode==='--protected'){
        // exec replaces this PHP image. SCRIPT_FILENAME and production bootstrap are
        // established by PHP itself; no source rewriting/reflection/server mutation.
        $args=array_slice($argv,2);
        if($args!==['--once']&&!(count($args)===2&&$args[0]==='--recover'&&ctype_digit($args[1])))throw new RuntimeException('protected_arguments');
        $status=file_get_contents('/proc/self/status');$caps=[];
        foreach(['CapInh','CapPrm','CapEff','CapBnd','CapAmb','NoNewPrivs'] as $key){
            if(!preg_match('/^'.$key.':\s*([a-f0-9]+)$/m',$status,$m))throw new RuntimeException('worker_process_status');
            $caps[$key]=$m[1];
            if($key==='NoNewPrivs'?$m[1]!=='1':trim($m[1],'0')!=='')throw new RuntimeException('worker_capability_leak');
        }
        echo 'IDENTITY '.json_encode(['uid'=>posix_geteuid(),'gid'=>posix_getegid(),'pid'=>getmypid(),'capabilities'=>$caps],JSON_THROW_ON_ERROR)."\n";flush();
        pcntl_exec(PHP_BINARY,['-d','memory_limit=128M','-d','display_errors=stderr','-d','zend.exception_ignore_args=1',
            '/opt/ubo/scripts/site-build-worker.php',...$args]);
        throw new RuntimeException('protected_exec_failed');
    }
    $phase='config';$config=json_decode(file_get_contents('/etc/ubo-site-build-worker.json'),true,32,JSON_THROW_ON_ERROR);
    if($config['builder_code_sha']!==$context['sha'])throw new RuntimeException('native_context');
    if(posix_geteuid()!==2000||!in_array($mode,['build','asset-loss','asset-corrupt','deny','hold','renew-execution','stale',
        'audit','validation','recover','renew-recovery'],true))throw new RuntimeException('instrumented_arguments');
    require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
    $phase='database_binding';$env=Database::config();
    if($env['DB_HOST']!==$context['db_ip']||$env['DB_NAME']!=='ubo_m6c_'.$context['token']||$env['DB_USER']!=='m6c_worker')throw new RuntimeException('worker_database_binding');
    $phase='database_connect';$pdo=Database::connection();
    $phase='server_identity';$server=$pdo->query('SELECT @@server_uuid AS uuid,@@hostname AS hostname,DATABASE() AS db')->fetch();
    if($server['uuid']!==$context['server_uuid']||$server['hostname']!=='m6c-db-'.$context['token']||$server['db']!==$env['DB_NAME'])throw new RuntimeException('worker_server_binding');
    $phase='arguments';$jobId=filter_var($argv[2]??null,FILTER_VALIDATE_INT);$operator=filter_var($argv[3]??null,FILTER_VALIDATE_INT);
    if(!$jobId||!$operator)throw new RuntimeException('instrumented_identity');
    $phase='dependencies';$adapter=new M6CNativeDependencies($config,$operator,$mode);
    $phase='wiring';$adapter->wire();
    $connection=null;
    if(in_array($mode,['audit','validation'],true)){
        $phase='fault_adapter';$connection=new M6CNativeFaultConnection($pdo);
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$connection);
    }
    $verifyFirst=true;
    if(in_array($mode,['renew-execution','renew-recovery'],true))$adapter->verification=static function($pulse)use(&$verifyFirst):void{
        if($verifyFirst){$verifyFirst=false;m6nBarrier('VERIFY');$pulse(true);}else $pulse();
    };
    if(in_array($mode,['recover','renew-recovery'],true)){
        $phase='recovery_claim';$claim=SiteBuildService::claimBuildRecovery($jobId,[],['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'inspect_unknown_outcome']);
        if(!($claim['claimed']??false))throw new RuntimeException('recovery_not_claimed');
        m6nBarrier('RECOVERY',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
        $phase='reconcile';$inspection=SiteBuildService::reconcileBuild($claim['lease']);
        $phase='recovery_complete';$done=SiteBuildService::completeBuildRecovery($claim['lease'],$inspection);
        m6nWorkerResult(['status'=>$done['job']['status'],'disposition'=>$inspection['disposition'],'builds'=>$adapter->builds]);
    }
    m6nBarrier('READY');
    $phase='claim';$claim=SiteBuildService::claimBuild([]);
    if($claim===null)m6nWorkerResult(['claimed'=>false,'builds'=>0]);
    if($claim['lease']['job_id']!==$jobId)throw new RuntimeException('unexpected_claim');
    m6nBarrier('CLAIM',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
    $phase='execute';$receipt=SiteBuildService::executeBuild($claim['lease']);
    if(isset($receipt['completed']))m6nWorkerResult(['claimed'=>true,'status'=>$receipt['completed']['job']['status'],'builds'=>$adapter->builds]);
    m6nBarrier('SEALED',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
    if($connection!==null)$connection->fault=$mode==='audit'?'site_events':'site_release_validations';
    $phase='complete';$done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    $phase='replay';$again=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    m6nWorkerResult(['claimed'=>true,'status'=>$done['job']['status'],'replayed'=>$again['replayed']??false,'builds'=>$adapter->builds]);
}catch(Throwable $e){
    // Production artifact failures retain real producer evidence before service settlement.
    if(isset($claim['lease'],$adapter)&&$e instanceof SiteArtifactException&&in_array($mode,['asset-loss','asset-corrupt'],true)){
        try{
            $phase='failure_settlement';$done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$e->failureCode,'receipt_key'=>$claim['input']['release_key']]);
            m6nWorkerResult(['status'=>$done['job']['status'],'failure'=>$e->failureCode,'builds'=>$adapter->builds]);
        }catch(Throwable $secondary){$e=$secondary;}
    }
    $result=['error'=>get_class($e),'code'=>$e instanceof SiteServiceException?$e->classification():($e instanceof SiteArtifactException?$e->failureCode:'native_child_failure')];
    if(isset($connection)&&$connection!==null)$result['sql']=$connection->diagnostic;
    if(isset($mode)&&in_array($mode,['audit','validation','stale'],true)&&$e instanceof SiteServiceException)m6nWorkerResult($result);
    if(($argv[1]??'')==='--protected')fwrite(STDERR,json_encode($result,JSON_THROW_ON_ERROR)."\n");
    else M6CNativeWorkerError::emit($e,$argv[1]??'',$phase,static fn($line)=>fwrite(STDERR,$line));
    exit(1);
}

/** Real PDO forwarding: only the selected final INSERT gets an invalid value.
 * MySQL itself rejects it; transaction/rollback and all other SQL remain real. */
final class M6CNativeFaultStatement extends PDOStatement
{
    public function __construct(private M6CNativeFaultConnection $owner,private PDOStatement $inner,private string $sql){}
    public function execute(?array $params=null): bool
    {
        if($this->owner->fault!==null&&str_starts_with($this->sql,'INSERT INTO '.$this->owner->fault)){
            if($this->owner->fault==='site_events')$params['actor_user_id']=9223372036854775807;else $params['result']='invalid';
        }
        try{return $this->inner->execute($params);}catch(PDOException $e){
            $this->owner->diagnostic=['sqlstate'=>$e->errorInfo[0]??null,'driver'=>$e->errorInfo[1]??null];throw $e;
        }
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed{return $this->inner->fetch($mode,$cursorOrientation,$cursorOffset);}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array{return $this->inner->fetchAll($mode,...$args);}
    public function fetchColumn(int $column=0): mixed{return $this->inner->fetchColumn($column);}
    public function rowCount(): int{return $this->inner->rowCount();}
}
final class M6CNativeFaultConnection extends PDO
{
    public ?string $fault=null;public ?array $diagnostic=null;
    public function __construct(private PDO $inner){}
    public function prepare(string $query,array $options=[]): PDOStatement|false{return new M6CNativeFaultStatement($this,$this->inner->prepare($query,$options),$query);}
    public function beginTransaction(): bool{return $this->inner->beginTransaction();}
    public function inTransaction(): bool{return $this->inner->inTransaction();}
    public function commit(): bool{return $this->inner->commit();}
    public function rollBack(): bool{return $this->inner->rollBack();}
    public function lastInsertId(?string $name=null): string|false{return $this->inner->lastInsertId($name);}
}
