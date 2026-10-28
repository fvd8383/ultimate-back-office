<?php
declare(strict_types=1);
if(PHP_SAPI==='cli'&&array_slice($argv,1)===['--help']){echo "Internal supplemental worker; verified disposable context only.\n";exit(0);}
require_once __DIR__.'/WebsitePlatformM6CSupplementChannel.php';
$phase='context';$pressure=null;$restore=[];
function m6sResult(string $status,int $builds=0):never
{M6CSupplementChannel::emit('RESULT',['status'=>$status,'builds'=>$builds]);exit(0);}
function m6sLeasePath(int $job,int $attempt):string
{if($job<1||$attempt<1)throw new RuntimeException('supplement_lease_identity');return '/var/lib/ubo-m6c/work/lease-'.$job.'-'.$attempt.'.json';}
function m6sSaveLease(array $lease):void
{$p=m6sLeasePath($lease['job_id'],$lease['attempt_id']);SiteArtifactFiles::writeNew($p,json_encode($lease,JSON_THROW_ON_ERROR),4096);}
function m6sLoadLease(int $job,int $attempt):array
{$p=m6sLeasePath($job,$attempt);$s=SiteArtifactFiles::regular($p);if($s['uid']!==2000||($s['mode']&0777)!==0600)throw new RuntimeException('supplement_lease_mode');return json_decode(SiteArtifactFiles::read($p,4096),true,16,JSON_THROW_ON_ERROR);}
try{
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo'
        ||!function_exists('posix_geteuid')||posix_geteuid()!==2000||count($argv)!==7)throw new RuntimeException('supplement_container_only');
    $context=json_decode(file_get_contents('/run/m6c/context.json'),true,32,JSON_THROW_ON_ERROR);
    if(fileowner('/run/m6c/context.json')!==0||($context['profile']??'')!==M6CSupplementContract::VERSION)throw new RuntimeException('supplement_attestation');
    foreach(['CapEff','CapBnd','CapAmb'] as $cap){if(!preg_match('/^'.$cap.':\s*0+$/m',file_get_contents('/proc/self/status')))throw new RuntimeException('supplement_capabilities');}
    umask(0077); // New worker-owned lease/pressure files only; never change host-path permissions.
    $mode=$argv[1];$jobId=filter_var($argv[2],FILTER_VALIDATE_INT);$operator=filter_var($argv[3],FILTER_VALIDATE_INT);
    $port=filter_var($argv[4],FILTER_VALIDATE_INT);$attempt=filter_var($argv[5],FILTER_VALIDATE_INT);$key=$argv[6];
    if($jobId===false||$jobId<1||$operator===false||$operator<1||$port===false||$port<0||$port>65535||$attempt===false||$attempt<0
        ||!in_array($mode,['build','transient','completion-hold','commit-fault','revoke-hold','recover','recover-interrupt','operator',
            'stale','replay','observe-unavailable','candidate','proof','permission','space','rename','collision','admission'],true))throw new RuntimeException('supplement_arguments');
    require_once __DIR__.'/WebsitePlatformM6CSupplementDependencies.php';
    $phase='config';$config=json_decode(file_get_contents('/etc/ubo-site-build-worker.json'),true,32,JSON_THROW_ON_ERROR);$env=Database::config();
    if($config['builder_code_sha']!==$context['sha']||$env['DB_HOST']!==$context['db_ip']
        ||$env['DB_NAME']!=='ubo_m6c_'.$context['token']||$env['DB_USER']!=='m6c_worker')throw new RuntimeException('supplement_database_binding');
    $phase='database_connect';
    if($port){$pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$env['DB_NAME'].';charset=utf8mb4',$env['DB_USER'],$env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>2]);
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$pdo);
    }else $pdo=Database::connection();
    $server=$pdo->query('SELECT @@server_uuid AS uuid,@@hostname AS hostname,DATABASE() AS db,CONNECTION_ID() AS connection_id')->fetch();
    if($server['uuid']!==$context['server_uuid']||$server['hostname']!=='m6c-db-'.$context['token']||$server['db']!==$env['DB_NAME'])throw new RuntimeException('supplement_server_binding');
    $pdo->exec("SET time_zone='+00:00'");$pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    M6CSupplementChannel::emit('START',['connection'=>(int)$server['connection_id']]);
    $adapter=new M6CSupplementDependencies($config,$operator);$adapter->wire();
    $connection=new M6CSupplementConnection($pdo);(new ReflectionProperty(Database::class,'connection'))->setValue(null,$connection);
    if($mode==='revoke-hold'){
        $pdo->beginTransaction();$q=$pdo->prepare("UPDATE users SET status='inactive' WHERE id=(SELECT requested_by_user_id FROM site_build_jobs WHERE id=?)");$q->execute([$jobId]);
        M6CSupplementChannel::barrier('LOCKED',['connection'=>(int)$server['connection_id']]);$pdo->commit();m6sResult('revoked');
    }
    if(in_array($mode,['stale','replay','observe-unavailable'],true)){
        $lease=m6sLoadLease($jobId,$attempt);
        if($mode==='stale'){
            foreach([fn()=>SiteBuildService::renewBuildLease($lease),fn()=>SiteBuildService::completeBuildRecovery($lease,['disposition'=>'blocked','receipt_key'=>SiteBuildStore::one($pdo,'SELECT release_key FROM site_build_jobs WHERE id=:id',['id'=>$jobId])['release_key']])] as $call){
                try{$call();throw new RuntimeException('stale_owner_accepted');}catch(SiteServiceException $e){if($e->classification()!=='conflict')throw $e;}}
            m6sResult('stale_rejected');
        }
        $receiptKey=SiteBuildContract::uuid($key); // No pre-fault job/result lookup by this observer.
        if($mode==='observe-unavailable')M6CSupplementChannel::barrier('OBSERVE');
        try{$done=SiteBuildService::completeBuildSuccess($lease,['receipt_key'=>$receiptKey]);}
        catch(Throwable $e){
            $transport=$e instanceof PDOException&&in_array($e->errorInfo[1]??null,[2006,2013],true);
            if($mode!=='observe-unavailable'||!($transport||($e instanceof SiteServiceException&&$e->classification()==='database_failure')))throw $e;
            M6CNativeWorkerError::emit($e,'build','complete',static fn($line)=>fwrite(STDERR,$line));m6sResult('unknown');
        }
        m6sResult($done['job']['status']);
    }
    if($mode==='admission'){
        $a=$config['work_root'].'/admission-'.bin2hex(random_bytes(8));$b='/tmp/ubo-m6c-admission-'.bin2hex(random_bytes(8));
        if(!mkdir($a,0700)||!mkdir($b,0700))throw new RuntimeException('admission_roots');
        try{
            if(stat($a)['dev']===stat($b)['dev'])throw new RuntimeException('admission_distinct_devices_unavailable');
            try{new LocalSiteArtifactStore($a,$b,fn()=>SiteBuildRuntime::guard());throw new RuntimeException('cross_device_accepted');}catch(SiteArtifactException $e){if($e->failureCode!=='builder_unavailable')throw $e;}
            if(!function_exists('posix_mkfifo')||!posix_mkfifo($a.'/fifo',0600))throw new RuntimeException('fifo_capability_unavailable');
            try{SiteArtifactFiles::inventory($a);throw new RuntimeException('fifo_accepted');}catch(SiteArtifactException $e){if($e->failureCode!=='artifact_invalid')throw $e;}
        }finally{if(file_exists($a.'/fifo'))unlink($a.'/fifo');rmdir($a);rmdir($b);}
        m6sResult('succeeded');
    }
    if(in_array($mode,['recover','recover-interrupt','operator'],true)){
        $request=$mode==='operator'?['request_key'=>SiteBuildContract::uuid($key),'reason_code'=>'inspect_unknown_outcome']:null;
        $phase='recovery_claim';$claim=SiteBuildService::claimBuildRecovery($jobId,[],$request);
        if(!($claim['claimed']??false))m6sResult(($claim['job']['status']??'')==='succeeded'?'succeeded':'no_claim');
        m6sSaveLease($claim['lease']);M6CSupplementChannel::barrier('RECOVERY',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
        if($mode==='recover-interrupt')$adapter->inspect=static function()use($adapter):void{$adapter->inspect=null;M6CSupplementChannel::barrier('VERIFY');};
        $phase='reconcile';$inspection=SiteBuildService::reconcileBuild($claim['lease']);
        $done=SiteBuildService::completeBuildRecovery($claim['lease'],$inspection);m6sResult($done['job']['status']);
    }
    M6CSupplementChannel::barrier('READY');$phase='claim';$claim=SiteBuildService::claimBuild([]);
    if($claim===null)m6sResult('no_claim');
    if($claim['lease']['job_id']!==$jobId)throw new RuntimeException('supplement_claim');
    m6sSaveLease($claim['lease']);M6CSupplementChannel::barrier('CLAIM',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
    $job=SiteBuildStore::one($pdo,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>$jobId]);
    $stem=$config['work_root'].'/'.$job['job_key'].'/attempt-'.$claim['lease']['attempt_id'];
    $site=SiteBuildStore::one($pdo,'SELECT site_key FROM sites WHERE id=:id',['id'=>$job['site_id']]);$parent=$config['artifact_root'].'/'.$site['site_key'];$target=$parent.'/'.$job['release_key'];
    $fired=false;$kernel=[];
    set_error_handler(static function(int $code,string $message)use(&$kernel):bool{
        foreach(['Permission denied'=>'permission','No space left on device'=>'space'] as $needle=>$kind)if(str_contains($message,$needle)){$kernel[$kind]=true;return true;}return false;
    });
    $adapter->progress=function($job,$producer,bool $force)use($mode,$stem,$parent,$target,&$fired,&$restore,&$pressure,&$kernel):void{
        if($fired)return;$files=glob($stem.'/public/assets/*')?:[];
        if(in_array($mode,['candidate','transient','permission','space'],true)&&$files&&!is_file($stem.'/metadata/manifest.json')){
            $fired=true;
            if($mode==='candidate')M6CSupplementChannel::barrier('BOUNDARY',['point'=>'candidate','files'=>count($files)]);
            elseif($mode==='transient')SiteArtifactFiles::fail('storage_unavailable');
            elseif($mode==='permission'){$p=$stem.'/public/assets';if(!chmod($p,0500))throw new RuntimeException('fault_chmod');$restore[$p]=0700;}
            else{
                // Only the attested 256-MiB run-owned tmpfs, never a host filesystem.
                if(!preg_match('~ /var/lib/ubo-m6c .* - tmpfs ~',file_get_contents('/proc/self/mountinfo')))throw new RuntimeException('pressure_tmpfs_required');
                $pressure=dirname($stem).'/pressure';$h=fopen($pressure,'xb');if(!$h)throw new RuntimeException('pressure_create');
                try{$chunk=str_repeat('x',1048576);$total=0;while($total<=268435456){$n=@fwrite($h,$chunk);if($n===false||$n===0)break;$total+=$n;}
                    if($total>268435456||disk_free_space(dirname($stem))>4096)throw new RuntimeException('pressure_bound');}
                finally{fclose($h);}
                unset($kernel['space']); // The actual subsequent store write must fail too.
            }
        }
        if($force&&in_array($mode,['proof','rename'],true)){
            if(!is_file($stem.'.seal.json')||file_exists($target))throw new RuntimeException('proof_boundary_unreached');$fired=true;
            if($mode==='proof')M6CSupplementChannel::barrier('BOUNDARY',['point'=>'proof','files'=>count(SiteArtifactFiles::inventory($stem))]);
            else{if(!chmod($parent,0500))throw new RuntimeException('rename_chmod');$restore[$parent]=0700;}
        }
    };
    if($mode==='collision')$adapter->guard=static function()use($stem,$parent,$target,&$fired):void{
        if(!$fired&&is_file($stem.'/metadata/manifest.json')&&!file_exists($stem.'.seal.json')){
            $fired=true;if(!is_dir($parent)&&!mkdir($parent,0700))throw new RuntimeException('collision_parent');
            if(!mkdir($target,0700))throw new RuntimeException('collision_target');}
    };
    $phase='execute';
    try{$receipt=SiteBuildService::executeBuild($claim['lease']);}
    catch(SiteArtifactException $e){
        if(!in_array($mode,['transient','permission','space','rename','collision'],true)||!$fired)throw $e;
        if(in_array($mode,['permission','rename'],true)&&!isset($kernel['permission']))throw new RuntimeException('actual_permission_fault_unobserved');
        if($mode==='space'&&!isset($kernel['space']))throw new RuntimeException('actual_space_fault_unobserved');
        M6CNativeWorkerError::emit($e,'build','execute',static fn($line)=>fwrite(STDERR,$line));
        foreach($restore as $p=>$bits){if(!chmod($p,$bits))throw new RuntimeException('fault_permission_restore');} $restore=[];
        if($pressure!==null){
            // After builder finally released its writer lock, independently exercise the
            // real free-space admission refusal before restoring this run-owned tmpfs.
            $producer=SiteBuildStore::one($pdo,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>$claim['lease']['attempt_id']]);
            try{$adapter->base->store->createCandidate($job,$producer,['site_key'=>$site['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1]);throw new RuntimeException('low_space_accepted');}
            catch(SiteArtifactException $low){if($low->failureCode!=='storage_unavailable')throw $low;}
            if(!unlink($pressure))throw new RuntimeException('pressure_cleanup');$pressure=null;
            // A new, disposable nested store permits an actual unavailable statvfs
            // probe without touching the shared work/sealed roots. The existing guard
            // runs after acquiring the writer lock and immediately before disk_free_space.
            $probe=$config['work_root'].'/space-probe-'.bin2hex(random_bytes(8));$probeWork=$probe.'/work';$probeSealed=$probe.'/sealed';
            if(!mkdir($probe,0700)||!mkdir($probeWork,0700)||!mkdir($probeSealed,0700))throw new RuntimeException('probe_create');
            $blocked=false;$probePrimary=null;$probeJob=$probeWork.'/'.$job['job_key'];unset($kernel['permission']);
            try{
                $probeStore=new LocalSiteArtifactStore($probeWork,$probeSealed,static function()use($probe,$probeJob,&$blocked):void{
                    SiteBuildRuntime::guard();clearstatcache(true,$probeJob.'/writer.lock');
                    if(!$blocked&&is_file($probeJob.'/writer.lock')){
                        if(!chmod($probe,0000))throw new RuntimeException('fault_chmod');$blocked=true;clearstatcache();
                    }
                });
                $available=disk_free_space($probeWork);
                if($available===false||$available<SiteArtifactFiles::MAX_PACKAGE+SiteArtifactFiles::MAX_MANIFEST+SiteArtifactFiles::MAX_VALIDATION)throw new RuntimeException('probe_space_before');
                try{$probeStore->createCandidate($job,$producer,['site_key'=>$site['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1]);throw new RuntimeException('probe_unknown_unobserved');}
                catch(SiteArtifactException $unknown){if(!$blocked||!isset($kernel['permission'])||$unknown->failureCode!=='storage_unavailable')throw new RuntimeException('probe_unknown_unobserved');}
            }catch(Throwable $failure){$probePrimary=$failure;}finally{$restored=@chmod($probe,0700);}
            if($probePrimary!==null)throw $probePrimary;
            if(!$restored)throw new RuntimeException('fault_permission_restore');
            if(!unlink($probeJob.'/writer.lock')||!rmdir($probeJob)||!rmdir($probeWork)||!rmdir($probeSealed)||!rmdir($probe))throw new RuntimeException('probe_cleanup');
            M6CSupplementChannel::emit('SPACE',['low'=>true,'unknown'=>true]);
        }
        $adapter->guard=null;$adapter->progress=null;
        $done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$e->failureCode,'receipt_key'=>$job['release_key']]);
        if($mode==='collision'&&(!is_dir($target)||count(scandir($target))!==2))throw new RuntimeException('foreign_target_changed');
        M6CSupplementChannel::emit('BOUNDARY',['point'=>match($mode){'transient'=>'candidate',default=>$mode},'files'=>count(glob($stem.'/public/assets/*')?:[])]);
        m6sResult($done['job']['status'],$adapter->builds);
    }
    M6CSupplementChannel::barrier('SEALED',['job_id'=>$jobId,'attempt_id'=>$claim['lease']['attempt_id']]);
    if($mode==='completion-hold')$connection->afterRelease=static function()use($connection,$server):void{$connection->afterRelease=null;M6CSupplementChannel::barrier('LOCKED',['connection'=>(int)$server['connection_id']]);};
    if($mode==='commit-fault')$connection->beforeCommit=static fn()=>M6CSupplementChannel::barrier('COMMIT',['connection'=>(int)$server['connection_id']]);
    $phase='complete';
    try{$done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);}
    catch(Throwable $e){
        $transport=$e instanceof PDOException&&in_array($e->errorInfo[1]??null,[2006,2013],true);
        if($mode!=='commit-fault'||!($transport||($e instanceof SiteServiceException&&$e->classification()==='database_failure')))throw $e;
        M6CNativeWorkerError::emit($e,'build','complete',static fn($line)=>fwrite(STDERR,$line));m6sResult('unknown',$adapter->builds);
    }
    m6sResult($done['job']['status'],$adapter->builds);
}catch(Throwable $e){M6CSupplementChannel::error($e,$phase);$exit=1;}
finally{
    foreach($restore as $p=>$bits)@chmod($p,$bits);
    if($pressure!==null&&is_file($pressure))unlink($pressure);
}
exit($exit??0);
