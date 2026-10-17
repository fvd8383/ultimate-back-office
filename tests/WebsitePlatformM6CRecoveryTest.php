<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
function m6cFailure(callable $call):string {
    try{$call();}catch(SiteArtifactException $e){m6c(strlen($e->getMessage())<300,'Bounded execution failure');return $e->failureCode;}
    throw new RuntimeException('Expected artifact failure');
}
function m6cRenewals(WebsitePlatformM6CFixture $f):int {
    return count(array_filter($f->db->queries,fn($q)=>str_starts_with($q,'UPDATE site_build_attempts SET')&&str_contains($q,'heartbeat_at')));
}
function m6cRecovery(WebsitePlatformM6CFixture $f,array $job):array {
    $f->db->advance(121);SiteBuildService::claimBuildRecovery($job['id'],[]);$f->db->advance(30);
    $claim=SiteBuildService::claimBuildRecovery($job['id'],[]);m6c($claim['claimed'],'Separate recovery lease claimed');return $claim;
}
// Controlled-clock progress forces only simulated elapsed checkpoints through the supplied service pulse.
function m6cSlow(WebsitePlatformM6CFixture $f):Closure {
    $steps=0;
    return function(callable $pulse)use($f,&$steps):void{
        $steps++;$elapsed=$steps%6===0&&$steps<=30;
        if($elapsed)$f->db->advance(25);
        $pulse($elapsed);
    };
}
try{
    $f=new WebsitePlatformM6CFixture();unlink($f->root.'/uploads/approved.png');
    m6cFailure(fn()=>$f->db->request());
    m6c($f->db->tables['site_build_jobs']===[]&&$f->db->tables['site_build_attempts']===[]&&count(scandir($f->root.'/work'))===2,'Request validation creates no producer/attempt/journal');
    foreach(['missing','digest','unreadable','partial-output']as$case){
        $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);
        $stem=$f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'];$asset=$f->root.'/uploads/approved.png';
        $original=file_get_contents($asset);$guardProperty=new ReflectionProperty(SiteArtifactAssetResolver::class,'guard');$guard=$guardProperty->getValue($f->assets);
        if($case==='missing')unlink($asset);
        elseif($case==='digest')file_put_contents($asset,'wrong digest');
        else{$reads=0;$guardProperty->setValue($f->assets,function()use($guard,$case,&$reads):void{$guard();if(++$reads===($case==='unreadable'?1:3))SiteArtifactFiles::fail('storage_unavailable');});}
        $code=m6cFailure(fn()=>SiteBuildService::executeBuild($claim['lease']));
        $journal=json_decode(file_get_contents($stem.'.json'),true,512,JSON_THROW_ON_ERROR);
        m6c($journal['binding']['job_id']===$job['id']&&$journal['binding']['site_id']===$job['site_id']&&$journal['binding']['producer_attempt_id']===$producer['id']&&$journal['binding']['release_key']===$job['release_key'],'Exact durable producer binding before asset failure '.$case);
        m6c($f->store->verifyRelease($job,$producer)['disposition']==='quarantined','Released writer lock proves private quarantine '.$case);
        m6c(($case==='partial-output')===(SiteArtifactFiles::inventory($stem)!==[]),'Expected empty/partial output '.$case);
        $failure=['code'=>$code,'receipt_key'=>$job['release_key']];$done=SiteBuildService::completeBuildFailure($claim['lease'],$failure);
        $transient=in_array($case,['unreadable','partial-output'],true);
        m6c($done['job']['status']===($transient?'retry_wait':'failed')&&$done['job']['failure_category']===($transient?'transient_storage':'integrity'),'Integrity never becomes a transient retry '.$case);
        m6c($f->db->tables['site_build_attempts'][$producer['id']]['status']==='failed'&&$f->db->events('site_build_failed')===1&&$f->db->tables['site_releases']===[],'Atomic failure/attempt/event state '.$case);
        $renewals=m6cRenewals($f);m6c(SiteBuildService::completeBuildFailure($claim['lease'],$failure)['replayed']&&$f->db->events('site_build_failed')===1&&m6cRenewals($f)===$renewals,'Repeat failure precedes verification/heartbeats '.$case);
        m6c(!(SiteBuildService::claimBuildRecovery($job['id'],[])['claimed']??false),'Settled failure needs no recovery '.$case);
        if($transient){
            $guardProperty->setValue($f->assets,$guard);file_put_contents($asset,$original);$f->db->advance(30);$next=$f->db->claim();
            $receipt=SiteBuildService::executeBuild($next['lease']);$success=SiteBuildService::completeBuildSuccess($next['lease'],$receipt);
            m6c($next['lease']['attempt_id']!==$producer['id']&&$success['job']['execution_count']===2&&$f->db->tables['site_build_jobs'][$job['id']]['requested_by_user_id']===$job['requested_by_user_id'],'New authorized attempt preserves budgets/requester');
            m6c($success['release']['release_key']===$job['release_key']&&is_file($stem.'.json'),'Reserved release and prior evidence retained');
        }
    }
    foreach(['before-journal','partial-journal','journal-write','seal-uncertain']as$case){
        $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);
        $stem=$f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'];
        if($case==='seal-uncertain')$f->duringProgress=function($pulse,bool $force):void{if($force)SiteArtifactFiles::fail('storage_unavailable');$pulse();};
        else{
            $p=new ReflectionProperty(LocalSiteArtifactStore::class,'guard');$guard=$p->getValue($f->store);$checks=0;
            $p->setValue($f->store,function()use($guard,$case,$stem,$job,$producer,&$checks):void{
                $guard();if(++$checks===2){
                    if($case==='partial-journal')file_put_contents($stem.'.json','{"binding":');
                    if($case==='journal-write'){
                        $binding=(new ReflectionMethod(LocalSiteArtifactStore::class,'binding'))->invoke(null,$job,$producer);
                        $identity=['site_key'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','release_key'=>$job['release_key'],'revision_number'=>1];
                        file_put_contents($stem.'.json.pending',CanonicalJson::encode(['binding'=>$binding,'identity'=>$identity]));
                    }
                    SiteArtifactFiles::fail('storage_unavailable');
                }
            });
        }
        $code=m6cFailure(fn()=>SiteBuildService::executeBuild($claim['lease']));
        if($case!=='seal-uncertain')$p->setValue($f->store,$guard);
        m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Uncertain journal/seal is not safe absence '.$case);
        $failure=['code'=>$code,'receipt_key'=>$job['release_key']];$done=SiteBuildService::completeBuildFailure($claim['lease'],$failure);
        m6c($done['job']['status']==='reconciliation_required'&&$f->db->tables['site_build_attempts'][$producer['id']]['status']==='abandoned'&&$f->db->events('site_reconciliation_required')===1,'Uncertainty remains attributed and blocks '.$case);
        m6c(SiteBuildService::completeBuildFailure($claim['lease'],$failure)['replayed'],'Uncertain completion replay '.$case);
        $f->db->advance(30);$r=SiteBuildService::claimBuildRecovery($job['id'],[]);$result=SiteBuildService::reconcileBuild($r['lease']);
        $recovered=SiteBuildService::completeBuildRecovery($r['lease'],$result);
        m6c($result['disposition']==='blocked'&&$recovered['job']['recovery_status']==='blocked'&&$f->builds===1&&$f->db->tables['site_releases']===[],'Recovery never fills uncertain output '.$case);
        m6c(SiteBuildService::completeBuildRecovery($r['lease'],$result)['replayed'],'Recovery repeat is history '.$case);
    }
    // Shared cadence is tested against a monotonic double, without sleeping or altering policy.
    $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();$clock=0;
    $pulse=(new ReflectionMethod(SiteBuildService::class,'leasePulse'))->invoke(null,$claim['lease'],function()use(&$clock):int{return $clock;});
    $pulse();for($i=0;$i<100;$i++)$pulse();m6c(m6cRenewals($f)===1,'Tiny checkpoints do not write heartbeat SQL repeatedly');
    $clock=24999999999;$pulse();m6c(m6cRenewals($f)===1,'Cadence before 25 seconds');
    $clock=25000000000;$f->db->advance(25);$pulse();m6c(m6cRenewals($f)===2,'Cadence renews after 25 seconds');
    foreach(['success','failure','recovery']as$mode){
        $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);$lease=$claim['lease'];
        if($mode==='recovery'){$f->db->tables['site_build_jobs'][$job['id']]['execution_count']=3;$r=m6cRecovery($f,$job);$lease=$r['lease'];}
        $producerBefore=$f->db->tables['site_build_attempts'][$producer['id']];$deadline=$f->db->tables['site_build_attempts'][$lease['attempt_id']]['deadline_at'];
        $before=$f->db->now;$f->verificationProgress=m6cSlow($f);
        if($mode==='recovery'){
            $result=SiteBuildService::reconcileBuild($lease);m6c($f->db->now>=SiteBuildStore::after($before,125),'Reconciliation spans more than one lease');
            $f->verificationProgress=m6cSlow($f);$done=SiteBuildService::completeBuildRecovery($lease,$result);
            m6c($f->db->tables['site_build_attempts'][$producer['id']]===$producerBefore&&$done['job']['execution_count']===3,'Expired producer never renewed; no fourth execution');
        }elseif($mode==='success')$done=SiteBuildService::completeBuildSuccess($lease,$receipt);
        else $done=SiteBuildService::completeBuildFailure($lease,['code'=>'storage_unavailable']+$receipt);
        m6c($f->db->now>=SiteBuildStore::after($before,125)&&$f->db->now<$deadline,'Slow verification finishes within original absolute deadline '.$mode);
        m6c($done['job']['status']===($mode==='failure'?'reconciliation_required':'succeeded'),'Owning active lease survives verification '.$mode);
        m6c($f->db->tables['site_build_attempts'][$lease['attempt_id']]['deadline_at']===$deadline&&m6cRenewals($f)>4,'Renewal does not extend deadline '.$mode);
        if($mode!=='failure'){
            $writes=m6cRenewals($f);$io=$f->io;$f->verificationProgress=fn()=>throw new RuntimeException('Replay must not verify');
            $replay=$mode==='success'?SiteBuildService::completeBuildSuccess($lease,$receipt):SiteBuildService::completeBuildRecovery($lease,$result);
            m6c($replay['replayed']&&m6cRenewals($f)===$writes&&$f->io===$io,'Committed history skips verification and renewal '.$mode);
            SiteBuildService::releaseManifestForActor(2,$done['release']['id']);
            m6c(m6cRenewals($f)===$writes,'Read-only manifest inspection allocates/renews no lease');
        }
    }
    foreach(['execution','recovery']as$kind)foreach(['expired','owner','deadline','callback','authorization']as$fault){
        $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
        $lease=$kind==='execution'?$claim['lease']:m6cRecovery($f,$job)['lease'];$checks=0;$before=$f->builds;
        $f->verificationProgress=function($pulse)use($f,$lease,$fault,&$checks):void{
            if(++$checks===5){
                if($fault==='expired')$f->db->advance(121);
                if($fault==='deadline')$f->db->tables['site_build_attempts'][$lease['attempt_id']]['deadline_at']=$f->db->now;
                if($fault==='owner')$f->db->tables['site_build_jobs'][$lease['job_id']]['current_attempt_id']=999;
                if($fault==='callback')throw new SiteServiceException('conflict','Controlled callback failure');
                if($fault==='authorization')$f->db->tables['site_build_attempts'][$lease['attempt_id']]['worker_id']='another-worker';
                $pulse(true);
            }else $pulse();
        };
        m6cDeny(fn()=>$kind==='execution'?SiteBuildService::completeBuildSuccess($lease,$receipt):SiteBuildService::reconcileBuild($lease));
        m6c($checks===5&&$f->db->tables['site_releases']===[]&&$f->db->events('site_build_succeeded')===0&&$f->builds===$before,'Progress failure stops immediately without adoption/render '.$kind.'/'.$fault);
        m6c($f->store->verifyRelease($job,$producer)['disposition']==='sealed','Verification failure releases lock and preserves immutable output');
    }
    // Pre-render validation runs with the active execution pulse and already-written producer journal.
    foreach(['execution','recovery']as$kind){
        $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
        $lease=$kind==='execution'?$claim['lease']:m6cRecovery($f,$job)['lease'];$checks=0;
        $deadline=$f->db->tables['site_build_attempts'][$lease['attempt_id']]['deadline_at'];
        $f->verificationProgress=function($pulse)use($f,&$checks):void{$checks++;$f->db->advance(30);$pulse(true);};
        m6cDeny(fn()=>$kind==='execution'?SiteBuildService::completeBuildSuccess($lease,$receipt):SiteBuildService::completeBuildRecovery($lease,['disposition'=>'adopted']+$receipt));
        m6c($f->db->now===$deadline&&$f->db->tables['site_build_attempts'][$lease['attempt_id']]['deadline_at']===$deadline,'Natural absolute deadline stops renewal '.$kind);
        m6c($f->db->tables['site_releases']===[]&&$f->db->events('site_build_succeeded')===0,'Deadline prevents final adoption '.$kind);
    }
    $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
    $checks=0;$f->verificationProgress=function($pulse)use(&$checks):void{if(++$checks===5)SiteArtifactFiles::fail('storage_unavailable');$pulse();};
    m6c(m6cFailure(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$receipt))==='storage_unavailable'&&$checks===5,'Artifact callback exception propagates instead of becoming unknown/success');
    m6c($f->store->verifyRelease($job,$producer)['disposition']==='sealed','Callback exception releases writer lock');
    // Real bounded reads checkpoint within an asset, not just once around the file.
    $f=new WebsitePlatformM6CFixture();$path=$f->root.'/chunked';file_put_contents($path,str_repeat('x',2097153));$checks=0;
    $bytes=SiteArtifactFiles::read($path,2097153,function()use(&$checks):void{$checks++;});
    m6c(strlen($bytes)===2097153&&$checks>=6,'Read progress at each bounded 1 MiB chunk');
    $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);$steps=0;$before=$f->db->now;
    $f->duringProgress=function($pulse)use($f,$job,$producer,&$steps):void{
        if(++$steps>=2&&$steps<=7){
            m6c(is_file($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'.json'),'Journal precedes validation progress');
            $f->db->advance(25);$pulse(true);
        }else $pulse();
    };
    $receipt=SiteBuildService::executeBuild($claim['lease']);m6c($f->db->now===SiteBuildStore::after($before,150),'Pre-render asset validation spans a lease with progress');
    m6c(SiteBuildService::completeBuildSuccess($claim['lease'],$receipt)['job']['status']==='succeeded','Slow pre-render validation completes normally');
    echo "Website platform M6C recovery/progress: $assertions assertions passed (real private files; fake SQL, clocks and interruption faults).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
