<?php
declare(strict_types=1);
if(PHP_SAPI==='cli'&&array_slice($argv,1)===['--help']){echo "Internal three-case journal worker; no helper load during discovery.\n";exit(0);}
require_once __DIR__.'/WebsitePlatformM6CJournalContract.php';
require_once __DIR__.'/WebsitePlatformM6CNativeWorkerError.php';
$phase='context';$callerFailure=null;
try{
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo'
        ||!function_exists('posix_geteuid')||posix_geteuid()!==2000||count($argv)!==5)throw new RuntimeException('journal_context');
    $mode=$argv[1];$jobId=filter_var($argv[2],FILTER_VALIDATE_INT);$operator=filter_var($argv[3],FILTER_VALIDATE_INT);$producerId=filter_var($argv[4],FILTER_VALIDATE_INT);
    if(!in_array($mode,[...M6CJournalFault::OPERATIONS,'recover'],true)||!is_int($jobId)||$jobId<1
        ||!is_int($operator)||$operator<1||!is_int($producerId)||$producerId<0)throw new RuntimeException('journal_arguments');
    foreach(['CapEff','CapBnd','CapAmb'] as $cap)if(!preg_match('/^'.$cap.':\s*0+$/m',file_get_contents('/proc/self/status')))throw new RuntimeException('journal_capabilities');
    $context=json_decode(file_get_contents('/run/m6c/context.json'),true,32,JSON_THROW_ON_ERROR);
    if(is_link('/run/m6c/context.json')||fileowner('/run/m6c/context.json')!==0||($context['profile']??'')!==M6CJournalContract::VERSION
        ||gethostname()!=='m6c-runtime-'.$context['token'])throw new RuntimeException('journal_attestation');
    if($mode!=='recover'&&(!extension_loaded('m6c_journal')||!function_exists('m6c_journal_arm')||PHP_VERSION_ID<80400||PHP_VERSION_ID>=80500
        ||PHP_ZTS||filter_var(ini_get('opcache.enable_cli'),FILTER_VALIDATE_BOOL)))throw new RuntimeException('journal_helper_unavailable');
    if($mode==='recover'&&extension_loaded('m6c_journal'))throw new RuntimeException('journal_recovery_isolation');
    umask(0077);require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';
    $config=json_decode(file_get_contents('/etc/ubo-site-build-worker.json'),true,32,JSON_THROW_ON_ERROR);$env=Database::config();
    if($config['builder_code_sha']!==$context['sha']||$config['work_root']!=='/var/lib/ubo-m6c/work'
        ||$env['DB_HOST']!==$context['db_ip']||$env['DB_NAME']!=='ubo_m6c_'.$context['token']||$env['DB_USER']!=='m6c_worker')throw new RuntimeException('journal_database_binding');
    $phase='database_connect';$db=Database::connection();$server=$db->query('SELECT @@server_uuid AS uuid,@@hostname AS hostname,DATABASE() AS db')->fetch();
    if($server['uuid']!==$context['server_uuid']||$server['hostname']!=='m6c-db-'.$context['token']||$server['db']!==$env['DB_NAME'])throw new RuntimeException('journal_server_binding');
    $db->exec("SET time_zone='+00:00'");$db->exec('SET SESSION innodb_lock_wait_timeout=5');
    $adapter=new M6CNativeDependencies($config,$operator,$mode==='recover'?'recover':'build');$adapter->wire();$phase='claim';
    $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>$jobId]);
    if($mode==='recover'){
        $claim=SiteBuildService::claimBuildRecovery($jobId,[]);
        if(!($claim['claimed']??false)||$claim['attempt']['recovery_of_attempt_id']!=$producerId)throw new RuntimeException('journal_recovery_claim');
        $inspection=SiteBuildService::reconcileBuild($claim['lease']);
        if($inspection['disposition']!=='blocked')throw new RuntimeException('journal_recovery_not_blocked');
        $done=SiteBuildService::completeBuildRecovery($claim['lease'],$inspection);
        $result=['mode'=>'recover','job'=>$jobId,'producer'=>$producerId,'recovery_attempt'=>$claim['lease']['attempt_id'],
            'status'=>$done['job']['status'],'disposition'=>$inspection['disposition'],'builds'=>$adapter->builds];
    }else{
        $claim=SiteBuildService::claimBuild([]);
        if($claim===null||$claim['lease']['job_id']!==$jobId)throw new RuntimeException('journal_claim');
        $producerId=$claim['lease']['attempt_id'];$producer=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>$producerId]);
        $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>$jobId]);
        $m6cJournalFault=new M6CJournalFault($mode,$context['token'],$job['job_key'],$producerId);
        if($mode==='control')foreach([[str_repeat('0',32),$job['job_key'],$producerId],[$context['token'],'../unsafe',$producerId],
            [$context['token'],$job['job_key'],0],[$context['token'],$job['job_key'],$producerId+1]] as $bad){
            $denied=false;try{m6c_journal_arm(...$bad);}catch(Error){$denied=true;}
            if(!$denied)throw new RuntimeException('journal_invalid_arm_accepted');
        }
        if(!m6c_journal_arm($context['token'],$job['job_key'],$producerId))throw new RuntimeException('journal_arm');
        if($mode==='control'){$denied=false;try{m6c_journal_arm($context['token'],$job['job_key'],$producerId);}catch(Error){$denied=true;}
            if(!$denied)throw new RuntimeException('journal_duplicate_arm_accepted');}
        // A real nonmatching write/flush/fsync/close/chmod/rename under the same loaded and armed helper.
        M6CJournalFault::offTarget($config['work_root'].'/journal-control-'.bin2hex(random_bytes(12)));
        $phase='execute';$error=null;
        try{$receipt=SiteBuildService::executeBuild($claim['lease']);}
        catch(SiteArtifactException $e){$callerFailure=$e;$error=$e->failureCode;}
        $observation=$m6cJournalFault->evidence(m6c_journal_status());
        M6CJournalContract::observation($observation,$mode,$context['token']);
        if($mode==='control'){
            if($error!==null)throw new RuntimeException('journal_control_failure');
            $done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
            if($done['job']['status']!=='succeeded')throw new RuntimeException('journal_control_completion');
            $result=['mode'=>$mode,'job'=>$jobId,'producer'=>$producerId,'observation'=>$observation,'caller_error'=>null,
                'status'=>'succeeded','inspection'=>'sealed','pending_digest'=>null,'builds'=>$adapter->builds,'cleanup'=>'owned_controls_removed'];
        }else{
            if($error!==($mode==='publication'?'outcome_unknown':'storage_unavailable'))throw new RuntimeException('journal_caller_error');
            $stem=$config['work_root'].'/'.$job['job_key'].'/attempt-'.$producerId;
            SiteArtifactFiles::regular($stem.'.json.pending');
            if(file_exists($stem)||is_link($stem)||file_exists($stem.'.json')||is_link($stem.'.json')||file_exists($stem.'.seal.json'))throw new RuntimeException('journal_false_publication');
            $inspection=$adapter->store->verifyRelease($job,$producer);
            if($inspection['disposition']!=='unknown')throw new RuntimeException('journal_false_absence');
            $digest=hash_file('sha256',$stem.'.json.pending');
            $done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$error,'receipt_key'=>$job['release_key']]);
            if($done['job']['status']!=='reconciliation_required'||hash_file('sha256',$stem.'.json.pending')!==$digest)throw new RuntimeException('journal_failure_settlement');
            $result=['mode'=>$mode,'job'=>$jobId,'producer'=>$producerId,'observation'=>$observation,'caller_error'=>$error,
                'status'=>$done['job']['status'],'inspection'=>'unknown','pending_digest'=>$digest,'builds'=>$adapter->builds,'cleanup'=>'owned_controls_removed'];
        }
    }
    $bytes=json_encode($result,JSON_THROW_ON_ERROR);if(strlen($bytes)>4096)throw new RuntimeException('journal_result_size');
    if(fwrite(STDOUT,'JOURNAL '.$bytes."\n")!==strlen($bytes)+9)throw new RuntimeException('journal_result_write');
}catch(Throwable $e){
    // Fixed code plus the existing bounded typed diagnostic; no paths or arbitrary exception text.
    fwrite(STDERR,"M6C JOURNAL ERROR journal_worker_failure\n");
    if($callerFailure!==null)M6CNativeWorkerError::emit($callerFailure,'build','execute',static fn($line)=>fwrite(STDERR,$line));
    M6CNativeWorkerError::emit($e,'build',$phase,static fn($line)=>fwrite(STDERR,$line));$exit=1;
}
exit($exit??0);
