<?php
declare(strict_types=1);
/** Test-only instrumented component lane. Normal CLI acceptance invokes scripts/ directly. */
require_once __DIR__.'/WebsitePlatformM6ENativeSetup.php';
require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentService.php';
require_once dirname(__DIR__,2).'/private/classes/ApacheDigitalOceanSitePublisher.php';

final class M6ENativeDependencies implements SiteDeploymentDependencies {
    public SitePublisherSession $session;public LocalSiteArtifactStore $store;public ?array $target=null;
    public bool $barriers=false;public int $healthWaits=0;public int $externalChecks=0;
    public function __construct(public SitePublisherHostContract $host){
        $c=$host->configuration();$this->store=new LocalSiteArtifactStore($c['artifact_work_root'],$c['artifact_root'],Closure::fromCallable([SiteDeploymentService::class,'guardExternal']));
        $this->session=new SitePublisherSession($host,new Ubo\M6E\SitePublisherArtifactExport($this->store,$c['artifact_root'],$c['worker_uid']));
    }
    public function workerIdentity(string $capability):array{m6eNeed(in_array($capability,['execution','recovery'],true),'capability');return $this->host->worker();}
    public function operatorId():int{throw new RuntimeException('operator_request_not_enabled');}
    public function assertReady(array $target,array $release):void{$this->host->resolve(SiteDeploymentContract::target($target));m6eNeed($release['build_profile']===SiteBuildContract::PROFILE,'profile');}
    public function publisher(array $target):SitePublisher{
        $this->external();$this->target=$target;
        return new ApacheDigitalOceanSitePublisher($this->session,$target,SiteHttpHealthChecker::forBinding($this->host->resolve($target)));
    }
    public function verifyArtifact(array $release):array{$this->external();return SiteDeploymentArtifacts::verify($this->store,$release);}
    public function waitForNextProbe():void{$this->external();$this->healthWaits++;if($this->barriers&&$this->healthWaits===1)m6eBarrier('ACTIVE_HEALTH');usleep(1000000);}
    public function external():void{m6eNeed(!Database::connection()->inTransaction(),'external_transaction');SiteDeploymentService::guardExternal();$this->externalChecks++;}
    public function wire():void{(new ReflectionProperty(SiteDeploymentService::class,'dependencies'))->setValue(null,$this);}
    public function finish():void{if($this->target!==null)$this->session->request('settle',$this->target);$this->session->close();}
}
function m6eBarrier(string $phase,array $safe=[]):void{
    echo CanonicalJson::encode(['phase'=>$phase,'pid'=>getmypid(),'uid'=>posix_geteuid()]+$safe)."\n";fflush(STDOUT);
    stream_set_timeout(STDIN,10);$line=fgets(STDIN,64);m6eNeed($line==="CONTINUE\n",'barrier_release');
}
function m6eControlFile(array $state):void{
    $path='/run/ubo-m6e-fixture/worker/control.json';$raw=CanonicalJson::encode($state);m6eNeed(strlen($raw)<=2097152,'control_bound');
    $h=fopen($path.'.pending','xb');m6eNeed(is_resource($h),'control_create');
    try{m6eNeed(fwrite($h,$raw)===strlen($raw)&&fflush($h)&&fsync($h),'control_write');}finally{fclose($h);}
    chmod($path.'.pending',0600);m6eNeed(rename($path.'.pending',$path),'control_publish');
}
function m6eReadControl():array{
    $path='/run/ubo-m6e-fixture/worker/control.json';$s=lstat($path);
    m6eNeed($s['uid']===2000&&($s['mode']&0777)===0600&&$s['nlink']===1&&$s['size']<=2097152&&!is_link($path),'control_identity');
    return json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
}
function m6eWorkerInput(PDO $db,array $f,int $release,string $kind):array{
    $target=SiteBuildStore::one($db,'SELECT * FROM site_deployment_targets WHERE id=:id',['id'=>$f['target_id']]);
    $prior=$target['current_deployment_id']===null?null:(int)$target['current_deployment_id'];$restore=null;
    if($kind==='restore')$restore=(int)$db->query('SELECT id FROM site_deployments WHERE site_id='.(int)$f['fixture']['site_id'].' AND target_id='.(int)$f['target_id'].' AND release_id='.$release." AND status='succeeded' ORDER BY id LIMIT 1")->fetchColumn();
    return ['site_id'=>$f['fixture']['site_id'],'target_id'=>$f['target_id'],'release_id'=>$release,'request_key'=>SiteServiceSupport::uuidV4(),
        'expected_current_deployment_id'=>$prior,'expected_pointer_version'=>(int)$target['pointer_version'],'binding_version'=>1,
        'reason_code'=>$kind==='restore'?'restore_known_good':'publish_approved_release','restored_from_deployment_id'=>$restore,
        'deployment_approval_id'=>null];
}
try{
    $a=m6eBootstrap();m6eNeed(posix_geteuid()===2000&&count($argv)===2,'worker_arguments');
    $mode=$argv[1];m6eNeed(in_array($mode,['request-publish','request-restore','stage-only','execute-barrier','renew','replay','recover-component','session-hold'],true),'worker_mode');
    $host=SitePublisherHostContract::load();$runtime=new M6ENativeDependencies($host);$runtime->wire();
    $db=Database::connection();$db->exec("SET SESSION time_zone='+00:00', innodb_lock_wait_timeout=5");
    $f=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',2097152),true,64,JSON_THROW_ON_ERROR);
    if(str_starts_with($mode,'request-')){
        $restore=$mode==='request-restore';$release=$restore?$f['release']['release_id']:($f['next_release']['release_id']??$f['release']['release_id']);
        $input=m6eWorkerInput($db,$f,$release,$restore?'restore':'publish');
        $result=$restore?SiteDeploymentService::requestRestore($f['fixture']['requester'],$input):SiteDeploymentService::requestDeployment($f['fixture']['requester'],$input);
        m6eControlFile(['request'=>$result]);echo CanonicalJson::encode(['pid'=>getmypid(),'deployment_id'=>$result['deployment_id'],'result'=>'requested'])."\n";exit(0);
    }
    if(in_array($mode,['renew','replay','recover-component'],true)){
        $s=m6eReadControl();$lease=$s['lease'];
        if($mode==='renew')$result=SiteDeploymentService::renewDeploymentLease($lease);
        elseif($mode==='replay')$result=SiteDeploymentService::completeDeploymentSuccess($lease,$s['receipt']);
        else{
            $claim=SiteDeploymentService::claimDeploymentRecovery($lease['deployment_id'],[]);
            m6eNeed($claim['claimed']??false,'recovery_claim');$runtime->session->bind($claim['lease']);
            $result=SiteDeploymentService::completeDeploymentRecovery($claim['lease'],SiteDeploymentService::reconcileDeployment($claim['lease']));
            $runtime->finish();
        }
        echo CanonicalJson::encode(['pid'=>getmypid(),'result'=>$result['operation_status']??'renewed','external_checks'=>$runtime->externalChecks])."\n";exit(0);
    }
    $claim=SiteDeploymentService::claimDeployment([]);m6eNeed($claim!==null,'claim');$lease=$claim['lease'];
    $runtime->session->bind($lease);m6eControlFile(['lease'=>$lease]);
    if($mode==='stage-only'||$mode==='session-hold'){
        $entry=(new ReflectionMethod(SiteDeploymentService::class,'entry'))->invoke(null,$lease);
        $target=SiteDeploymentContract::target($entry['target']);$release=SiteDeploymentContract::release($entry['release'],$entry['site']);
        $context=(new ReflectionMethod(SiteDeploymentService::class,'context'))->invoke(null,$entry,$lease,'stage');
        $publisher=$runtime->publisher($target);$stage=$publisher->stageRelease($release,$target,$context);
        m6eControlFile(['lease'=>$lease,'target'=>$target,'release'=>$release,'context'=>$context,'staged'=>$stage]);
        if($mode==='session-hold')m6eBarrier('STAGED');
        echo CanonicalJson::encode(['pid'=>getmypid(),'result'=>'staged','fence'=>$lease['fence_epoch'],'external_checks'=>$runtime->externalChecks])."\n";
        $runtime->session->close();exit(0); // Explicit EOF test, never settles an uncommitted result.
    }
    $runtime->barriers=true;$receipt=SiteDeploymentService::executeDeployment($lease);
    m6eControlFile(['lease'=>$lease,'receipt'=>$receipt]);m6eBarrier('RESULT_READY',['external_checks'=>$runtime->externalChecks]);
    $result=$receipt['completed']??SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
    m6eBarrier('RESULT_COMMITTED',['operation_status'=>$result['operation_status']]);$runtime->finish();
    echo CanonicalJson::encode(['phase'=>'DONE','pid'=>getmypid(),'result'=>$result['operation_status'],'external_checks'=>$runtime->externalChecks])."\n";
}catch(Throwable $error){
    $code=$error instanceof SiteServiceException?$error->classification():'fixture_unresolved';
    fwrite(STDERR,'m6e_native_worker:'.$code."\n");exit(1);
}
