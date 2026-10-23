<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';

function executionFixture():array{
    $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();return [$f,$claim];
}
function executionError(callable $call):string{
    try{$call();}catch(SiteServiceException $e){return $e->classification();}
    catch(SiteArtifactException $e){return $e->failureCode;}
    throw new RuntimeException('Expected execution rejection');
}
function executionEmpty(WebsitePlatformM6CFixture $f,int $io):void{
    m6c($f->io===$io&&$f->builds===0,'No execution artifact reads or construction');
    m6c(scandir($f->root.'/work')===['.','..']&&scandir($f->root.'/sealed')===['.','..'],'No journal, candidate, or seal');
    m6c($f->db->tables['site_releases']===[],'No release accepted');
}
try{
    foreach(['revoked','missing','deleted','role','source','builder-unavailable','builder-mismatch','builder-malformed','input','policy']as$case){
        [$f,$claim]=executionFixture();[$job,$attempt]=$f->rows($claim);$io=$f->io;$expected='requester_not_authorized';
        if($case==='revoked')$f->db->read->users[1]['status']='inactive';
        if(in_array($case,['missing','deleted'],true))unset($f->db->read->users[1]);
        if($case==='deleted')$f->db->tables['site_build_jobs'][$job['id']]['requested_by_user_id']=null; // Existing FK SET NULL behavior.
        if($case==='role')$f->db->read->users[1]['roles']=[];
        if($case==='source'){$f->db->read->base->approvals[700]['revoked_at']=$f->db->now;$expected='source_not_eligible';}
        if(str_starts_with($case,'builder-')){
            $expected='builder_unavailable';
            if($case==='builder-unavailable')$f->duringIdentity=fn()=>SiteArtifactFiles::fail('builder_unavailable');
            elseif($case==='builder-mismatch')$f->identity['builder_code_sha']=str_repeat('b',40);
            else $f->identity=[];
        }
        if($case==='input'){$f->db->tables['site_build_jobs'][$job['id']]['input_manifest_json']='{}';$expected='input_mismatch';}
        if($case==='policy'){$f->db->tables['site_build_jobs'][$job['id']]['worker_policy_version']='unsupported';$expected='policy_unsupported';}
        $requester=$f->db->tables['site_build_jobs'][$job['id']]['requested_by_user_id'];
        $result=SiteBuildService::executeBuild($claim['lease']);[$after,$ended]=$f->rows($claim);
        m6c($result['completed']['job']['status']==='failed'&&$after['failure_code']===$expected,'Exact committed denial '.$case);
        m6c($after['failure_category']===SiteBuildContract::FAILURES[$expected][0]&&$ended['failure_code']===$expected&&$ended['status']==='failed'&&$ended['completed_at']!==null,'Consistent failure classification '.$case);
        m6c($after['execution_count']===1&&$after['attempt_count']===1&&$after['recovery_count']===0&&count($f->db->tables['site_build_attempts'])===1,'Claim counters retained '.$case);
        m6c($after['requested_by_user_id']===$requester&&$after['current_attempt_id']===$attempt['id']&&$after['started_at']===$job['started_at'],'Original claim identity retained '.$case);
        m6c($after['next_attempt_at']===null&&$after['next_recovery_at']===null&&$after['recovery_status']==='none','No retry or needless recovery '.$case);
        m6c($f->db->events('site_build_started')===1&&$f->db->events('site_build_failed')===1&&$f->db->events('site_build_cancelled')===0,'One outcome and original start '.$case);
        $events=array_values(array_filter($f->db->read->base->events,fn($e)=>$e['event_type']==='site_build_failed'));
        m6c($events[0]['actor_type']==='system'&&array_key_exists('actor_user_id',$events[0])&&$events[0]['actor_user_id']===null,'System records denial, never revoked actor '.$case);
        executionEmpty($f,$io);$snapshot=$f->db->snapshot();
        $f->duringIdentity=fn()=>throw new RuntimeException('History must skip builder');$f->db->advance(1000);
        $replay=SiteBuildService::executeBuild($claim['lease']);
        m6c($replay['completed']['replayed']&&$f->db->snapshot()===$snapshot,'Expired settled lease replays without writes '.$case);
        $replay=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$expected,'receipt_key'=>$job['release_key']]);
        m6c($replay['replayed']&&$f->db->snapshot()===$snapshot&&$f->io===$io,'Existing completion fingerprint interoperates without I/O '.$case);
    }
    foreach(['token','owner','worker','environment','expired','deadline','identity-worker','identity-expired','identity-owner','database']as$case){
        [$f,$claim]=executionFixture();[$job,$attempt]=$f->rows($claim);$io=$f->io;$lease=$claim['lease'];
        $f->db->read->users[1]['status']='inactive';
        if($case==='token')$lease['token']=str_repeat('c',64);
        if($case==='owner')$f->db->tables['site_build_jobs'][$job['id']]['current_attempt_id']=999;
        if($case==='worker')$f->workerOverride=['worker_id'=>'untrusted-worker','environment'=>'local'];
        if($case==='environment')$f->workerOverride=['worker_id'=>'synthetic-filesystem-worker','environment'=>'staging'];
        if($case==='expired')$f->db->advance(121);
        if($case==='deadline')$f->db->tables['site_build_attempts'][$attempt['id']]['deadline_at']=$f->db->now;
        if($case==='identity-worker')$f->duringIdentity=fn()=> $f->workerOverride=['worker_id'=>'changed-worker','environment'=>'local'];
        if($case==='identity-expired')$f->duringIdentity=fn()=> $f->db->advance(121);
        if($case==='identity-owner')$f->duringIdentity=function()use($f,$job):void{$f->db->tables['site_build_jobs'][$job['id']]['current_attempt_id']=999;};
        if($case==='database')$f->db->failAuthorization=true;
        m6c(in_array(executionError(fn()=>SiteBuildService::executeBuild($lease)),['conflict','unauthorized','database_failure'],true),'No authority to settle '.$case);
        [$after,$ended]=$f->rows($claim);
        m6c($after['status']==='running'&&$ended['status']==='running'&&$f->db->events('site_build_failed')===0,'Unauthorized/uncertain denial leaves attempt intact '.$case);
        executionEmpty($f,$io);
    }
    // Actual PHP service calls interleaved by deterministic callbacks, not native races.
    foreach(['identity','entry','journal']as$point){
        [$f,$claim]=executionFixture();$observed=false;
        $reenter=function()use($f,$claim,&$observed):void{
            if($observed)return;$observed=true;$snapshot=$f->db->snapshot();
            m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='conflict','Same attempt cannot reenter');
            m6c($f->db->snapshot()===$snapshot,'Rejected invocation cannot settle active owner');
        };
        if($point==='identity')$f->duringIdentity=$reenter;
        if($point==='entry')$f->duringBuild=$reenter;
        if($point==='journal')$f->duringProgress=function($pulse)use($f,$claim,$reenter):void{
            [$job,$producer]=$f->rows($claim);
            if(is_file($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'.json')){
                $f->db->read->users[1]['status']='inactive';$reenter();$f->db->read->users[1]['status']='active';
            }$pulse();
        };
        $receipt=SiteBuildService::executeBuild($claim['lease']);$done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
        m6c($observed&&$f->builds===1&&$done['job']['status']==='succeeded'&&$f->db->events('site_build_failed')===0,'One constructor survives interleaving '.$point);
    }
    // Lost process-local authority cannot be reconstructed from a valid token/missing journal.
    foreach(['absent','fork','runtime','connection']as$case){
        [$f,$claim]=executionFixture();$io=$f->io;$property=new ReflectionProperty(SiteBuildService::class,'freshExecutions');$entries=$property->getValue();$key=CanonicalJson::hash($claim['lease']);
        if($case==='absent')unset($entries[$key]);
        if($case==='fork')$entries[$key]['process']=-1;
        if($case==='runtime')$entries[$key]['runtime']=new stdClass();
        if($case==='connection')$entries[$key]['connection']=new stdClass();
        $property->setValue(null,$entries);$f->db->read->users[1]['status']='inactive';$snapshot=$f->db->snapshot();
        m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='conflict'&&$snapshot===$f->db->snapshot(),'No fabricated non-entry authority '.$case);
        executionEmpty($f,$io);
    }
    // Entry is irrevocable even if it throws before creating a journal or after partial output.
    foreach(['before-journal','partial']as$case){
        [$f,$claim]=executionFixture();[$job,$producer]=$f->rows($claim);
        $f->duringBuild=function()use($f,$job,$producer,$case):void{
            if($case==='partial'){
                $source=$f->source();$c=$f->store->createCandidate($job,$producer,['site_key'=>$source['site']['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1]);
                $f->store->writeFile($c,'public/index.html','partial');$f->store->finish($c);
            }SiteArtifactFiles::fail('storage_unavailable');
        };
        m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='storage_unavailable','Entered failure '.$case);
        $f->db->read->users[1]['status']='inactive';$snapshot=$f->db->snapshot();
        m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='conflict'&&$f->db->snapshot()===$snapshot,'Prior invocation cannot become safe non-entry '.$case);
        $done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'storage_unavailable','receipt_key'=>$job['release_key']]);
        m6c($done['job']['status']===($case==='partial'?'failed':'reconciliation_required'),'Independent evidence still governs entered failure '.$case);
        if($case==='partial')m6c(file_get_contents($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'/public/index.html')==='partial','Partial output retained privately');
    }
    [$f,$claim]=executionFixture();$f->db->read->users[1]['status']='inactive';$snapshot=$f->db->snapshot();$io=$f->io;$f->db->failTable='site_events';
    m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='database_failure','Audit failure surfaces safely');
    m6c($f->db->snapshot()===$snapshot,'Audit failure rolls back attempt/job/event together');
    $f->db->failTable=null;
    m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='conflict'&&$f->db->snapshot()===$snapshot,'Rollback never reissues consumed entry authority');
    executionEmpty($f,$io);
    [$f,$claim]=executionFixture();$f->db->read->users[1]['status']='inactive';$io=$f->io;
    $f->db->onSql=function($sql,$p,$db):void{if(str_starts_with($sql,'INSERT INTO site_events')&&($p['event_type']??null)==='site_build_failed')$db->loseCommitAck=true;};
    m6c(executionError(fn()=>SiteBuildService::executeBuild($claim['lease']))==='database_failure','Lost commit acknowledgement is not reported as settled');
    $snapshot=$f->db->snapshot();$f->db->onSql=null;$f->duringIdentity=fn()=>throw new RuntimeException('No new identity on replay');
    $replay=SiteBuildService::executeBuild($claim['lease']);
    m6c($replay['completed']['replayed']&&$replay['completed']['job']['status']==='failed'&&$f->db->snapshot()===$snapshot&&$f->db->events('site_build_failed')===1,'Authoritative read resolves lost acknowledgement exactly once');
    executionEmpty($f,$io);
    $f=new WebsitePlatformM6CFixture();$f->db->request();$property=new ReflectionProperty(SiteBuildService::class,'freshExecutions');$before=$property->getValue();
    $f->db->onSql=function($sql,$p,$db):void{if(str_starts_with($sql,'INSERT INTO site_events')&&($p['event_type']??null)==='site_build_started')$db->loseCommitAck=true;};
    m6c(executionError(fn()=>$f->db->claim())==='database_failure'&&$property->getValue()===$before,'Unacknowledged claim never issues construction authority');
    m6c(count($f->db->tables['site_build_attempts'])===1&&$f->db->events('site_build_started')===1,'Ambiguous claim is not compensated or reallocated');
    $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();$done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    $f->db->read->users[1]['status']='inactive';$f->db->read->base->approvals[700]['revoked_at']=$f->db->now;
    $f->duringIdentity=fn()=>throw new RuntimeException('Success history must skip builder');$f->db->advance(1000);$snapshot=$f->db->snapshot();$io=$f->io;
    $replay=SiteBuildService::executeBuild($claim['lease']);
    m6c($replay['completed']['replayed']&&$replay['completed']['release']===$done['release']&&$snapshot===$f->db->snapshot()&&$io===$f->io,'Committed success precedes gates, heartbeats, and I/O');
    echo "Website platform M6C execution: $assertions assertions passed (fake SQL, controlled interleavings, real private filesystem; no native concurrency proof).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
