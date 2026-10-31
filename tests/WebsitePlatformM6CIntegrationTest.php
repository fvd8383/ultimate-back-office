<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6CFixture.php';
try {
    foreach(['stale','requester','successor','audit','lost-ack']as$case){
        $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
        $before=$f->db->read->base->sites[10];
        if($case==='stale'){$f->db->advance(121);m6cDeny(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$receipt));}
        elseif($case==='requester'){$f->db->read->users[1]['status']='inactive';$result=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);m6c($result['job']['status']==='reconciliation_required','Lost requester prevents adoption');}
        elseif($case==='successor'){$f->db->read->base->revisions[101]=array_replace($f->db->read->base->revisions[100],['id'=>101,'revision_number'=>2,'materiality'=>'material']);$r=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);m6c($r['job']['status']==='reconciliation_required','Successor prevents success');}
        elseif($case==='audit'){$f->db->failTable='site_events';m6cDeny(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$receipt));}
        else{$f->afterVerify=fn()=> $f->db->loseCommitAck=true;m6cDeny(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$receipt));$r=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);m6c($r['job']['status']==='succeeded'&&$f->db->events('site_build_succeeded')===1,'Lost ack returns committed history');}
        if($case!=='lost-ack')m6c($f->db->tables['site_releases']===[],'Denied result creates no release: '.$case);
        m6c($before===$f->db->read->base->sites[10],'No publication effects: '.$case);
        m6c($f->store->verifyRelease($job,$producer)['disposition']==='sealed','Result denial preserves sealed evidence');
    }
    foreach(['adopt','revoked','missing','tampered']as$case){
        $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
        // Fault double models an exhausted third execution; real MySQL counter constraints stay a native gate.
        $id=$job['id'];$f->db->tables['site_build_jobs'][$id]['execution_count']=3;$f->db->tables['site_build_jobs'][$id]['attempt_count']=3;
        if($case==='revoked')$f->db->read->users[1]['status']='inactive';
        if($case==='missing')unlink($f->releasePath($job).'/public/index.html');
        if($case==='tampered')file_put_contents($f->releasePath($job).'/metadata/manifest.json','{}');
        $f->db->advance(121);SiteBuildService::claimBuildRecovery($id,[]);$f->db->advance(30);
        $recovery=SiteBuildService::claimBuildRecovery($id,[]);m6c($recovery['claimed'],'Recovery claim at exhausted execution budget');
        $r=SiteBuildService::reconcileBuild($recovery['lease']);$done=SiteBuildService::completeBuildRecovery($recovery['lease'],$r);
        m6c($f->builds===1,'Recovery never rerenders');
        m6c($f->db->tables['site_build_jobs'][$id]['execution_count']===3&&$f->db->tables['site_build_jobs'][$id]['attempt_count']===4,'Separate recovery counters');
        m6c($f->db->tables['site_build_attempts'][$producer['id']]['status']==='expired','Original producer stays expired');
        m6c($done['job']['status']===($case==='adopt'?'succeeded':'reconciliation_required'),'Recovery result '.$case);
        m6cDeny(fn()=>SiteBuildService::executeBuild($recovery['lease']));
    }
    $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);$hash=hash_file('sha256',$f->releasePath($job).'/metadata/manifest.json');
    m6cDeny(fn()=>SiteBuildService::executeBuild($claim['lease']));
    m6c($hash===hash_file('sha256',$f->releasePath($job).'/metadata/manifest.json'),'Retry cannot overwrite a sealed release');
    $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);$source=$f->source();
    $identity=['site_key'=>$source['site']['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1];
    $candidate=$f->store->createCandidate($job,$producer,$identity);$f->store->writeFile($candidate,'public/index.html','partial');
    m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Active writer cannot be adopted');
    $f->store->finish($candidate);m6c($f->store->verifyRelease($job,$producer)['disposition']==='quarantined','Interrupted pre-seal output remains private, never filled by recovery');
    $failed=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>'storage_unavailable','receipt_key'=>$job['release_key']]);
    m6c($failed['job']['status']==='retry_wait','Verified private quarantine permits bounded transient retry');
    m6cDeny(fn()=>$f->store->createCandidate($job,$producer,$identity));
    $next=$producer;$next['id']=999;$next['attempt_number']=2;
    $candidate=$f->store->createCandidate($job,$next,$identity);$f->store->writeFile($candidate,'public/index.html','owned');
    $other=$f->root.'/work/'.$job['job_key'].'/unknown-orphan';mkdir($other);file_put_contents($other.'/keep','unknown');
    $f->store->finish($candidate,true);m6c(file_get_contents($other.'/keep')==='unknown','Cleanup retains unknown orphan and other attempt');
    m6c(is_file($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'/public/index.html'),'Cleanup preserves original attempt');
    $f=new WebsitePlatformM6CFixture();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);$source=$f->source();
    $pulse=function(bool $force=false)use($f,$claim):void{if($force){$f->db->advance(121);SiteBuildService::renewBuildLease($claim['lease']);}};
    m6cDeny(fn()=>$f->builder->build($job,$producer,$source,$pulse));
    m6c(!file_exists($f->releasePath($job))&&$f->store->verifyRelease($job,$producer)['disposition']==='unknown','Expired lease immediately before rename cannot seal; proof-only interruption blocks');
    echo "Website platform M6C integration: $assertions assertions passed (fake SQL plus real filesystem).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
