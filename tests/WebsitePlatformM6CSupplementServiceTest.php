<?php
declare(strict_types=1);
// Actual services/artifact implementation + fake PDO/clocks. Never native DB or kernel proof.
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6CSupplementPdf.php';
require_once __DIR__.'/support/WebsitePlatformM6CSupplementDependencies.php';
function ssCounts(WebsitePlatformM6CFixture $f,int $total,int $exec,int $recovery):void
{$j=array_values($f->db->tables['site_build_jobs'])[0];m6c([$j['attempt_count'],$j['execution_count'],$j['recovery_count']]===[$total,$exec,$recovery],'Service created monotonic attempts');}
function ssThird(WebsitePlatformM6CFixture $f):array
{
    $job=$f->db->request();
    for($i=1;$i<=2;$i++){
        $c=$f->db->claim();$j=$f->db->tables['site_build_jobs'][$job['id']];$stem=$f->root.'/work/'.$j['job_key'].'/attempt-'.$c['lease']['attempt_id'];$observed=false;
        $f->duringProgress=static function($pulse,bool $force)use($stem,&$observed):void{if(glob($stem.'/public/assets/*.css')){$observed=true;SiteArtifactFiles::fail('storage_unavailable');}$pulse($force);};
        try{SiteBuildService::executeBuild($c['lease']);throw new RuntimeException('Missing transient fault');}
        catch(SiteArtifactException $e){m6c($observed&&$e->failureCode==='storage_unavailable','Actual partial bytes before modeled transient fault: '.$e->failureCode.' observed='.(int)$observed.' execution='.$i.' attempt='.$c['lease']['attempt_id'].' throwline='.($e->getTrace()[0]['line']??0));}
        finally{$f->duringProgress=null;}
        $r=SiteBuildService::completeBuildFailure($c['lease'],['code'=>'storage_unavailable','receipt_key'=>$j['release_key']]);
        m6c($r['job']['status']==='retry_wait'&&$f->db->tables['site_releases']===[],'Quarantined transient output safely retried');ssCounts($f,$i,$i,0);
        $f->db->advance($i===1?30:120);
    }
    $c=$f->db->claim();$receipt=SiteBuildService::executeBuild($c['lease']);ssCounts($f,3,3,0);return [$job,$c,$receipt];
}
function ssRecovery(WebsitePlatformM6CFixture $f,int $job):array
{
    $f->db->advance(121);$probe=SiteBuildService::claimBuildRecovery($job,[]);m6c(!($probe['claimed']??false),'Expired claim schedules recovery');
    $j=$f->db->tables['site_build_jobs'][$job];if($j['recovery_status']==='blocked')return $probe;
    $delta=strtotime($j['next_recovery_at'])-strtotime($f->db->now);$f->db->advance(max(0,$delta));
    return SiteBuildService::claimBuildRecovery($job,[]);
}
function ssPdfFixture():WebsitePlatformM6CFixture
{
    $f=new WebsitePlatformM6CFixture();$base=$f->db->read->base;$pdf=M6CSupplementPdf::bytes();
    file_put_contents($f->root.'/uploads/approved.pdf',$pdf);chmod($f->root.'/uploads/approved.pdf',0600);
    $base->siteAssets[2]=array_replace($base->siteAssets[1],['id'=>2,'asset_type'=>'document','storage_key'=>'/uploads/approved.pdf',
        'checksum_sha256'=>hash('sha256',$pdf),'mime_type'=>'application/pdf','byte_size'=>strlen($pdf)]);
    $base->revisions[99]=array_replace($base->revisions[100],['id'=>99,'revision_number'=>1]);
    $base->revisions[100]['revision_number']=2;$base->revisions[100]['materiality']='non_material';$base->revisions[100]['lifecycle_status']='draft';
    $base->approvals[700]['revision_id']=99;
    $c=WebsitePlatformM6CFixture::composition($base->revisions[100]['snapshot_hash']);$c['pages'][0]['sections'][]=M6CSupplementPdf::section(2);
    useWebsitePlatformM3ServiceDatabase($base);SiteCompositionManager::replaceDraftComposition(1,100,$c);
    $base->revisions[100]['lifecycle_status']='internally_approved';(new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);return $f;
}
try{
    foreach(['adopt','revoked','deleted','restart'] as $case){
        $f=new WebsitePlatformM6CFixture();[$job,$c,$receipt]=ssThird($f);[$j,$producer]=$f->rows($c);$path=$f->releasePath($j);
        $hash=hash_file('sha256',$path.'/metadata/manifest.json');
        if($case==='revoked'||$case==='restart')$f->db->read->users[1]['status']='inactive';
        if($case==='deleted'){unset($f->db->read->users[1]);$f->db->tables['site_build_jobs'][$job['id']]['requested_by_user_id']=null;} // Modeled FK only.
        $r=ssRecovery($f,$job['id']);ssCounts($f,4,3,1);$old=$r;
        if($case==='restart'){
            $inspection=SiteBuildService::reconcileBuild($r['lease']);m6c($inspection['disposition']==='adopted','Inspection is independent of success authority');
            $r=ssRecovery($f,$job['id']);ssCounts($f,5,3,2);
            m6c($r['lease']['token']!==$old['lease']['token']&&$r['attempt']['recovery_of_attempt_id']===$old['attempt']['recovery_of_attempt_id'],'Restart fresh ownership original producer');
            m6cDeny(fn()=>SiteBuildService::renewBuildLease($old['lease']));
            m6cDeny(fn()=>SiteBuildService::completeBuildRecovery($old['lease'],$inspection));
        }
        $inspection=SiteBuildService::reconcileBuild($r['lease']);$done=SiteBuildService::completeBuildRecovery($r['lease'],$inspection);
        m6c($done['job']['status']===($case==='adopt'?'succeeded':'reconciliation_required'),'Exact adoption authority '.$case);
        m6c($f->builds===3&&hash_file('sha256',$path.'/metadata/manifest.json')===$hash,'Recovery did not render execution four or rewrite bytes');
        m6c($f->db->tables['site_build_attempts'][$producer['id']]['status']==='expired','Expired original producer retained');
        if($case!=='adopt')m6c($f->db->tables['site_releases']===[]&&$f->db->events('site_build_succeeded')===0,'Denied adoption no release/event');
    }
    $f=new WebsitePlatformM6CFixture();[$job,$c]=ssThird($f);[$j,$producer]=$f->rows($c);
    file_put_contents($f->releasePath($j).'/metadata/manifest.json','{}');
    $r=ssRecovery($f,$job['id']);m6c(SiteBuildService::reconcileBuild($r['lease'])['disposition']==='blocked','Unknown artifact remains unknown');
    $r=ssRecovery($f,$job['id']);m6c(SiteBuildService::reconcileBuild($r['lease'])['disposition']==='blocked','Second inspection does not repair evidence');
    $blocked=ssRecovery($f,$job['id']);m6c($blocked['job']['recovery_status']==='blocked'&&$blocked['job']['automatic_recovery_count']===2,'Two automatic expirations block');ssCounts($f,5,3,2);
    $request=['request_key'=>SiteServiceSupport::uuidV4(),'reason_code'=>'inspect_unknown_outcome'];
    $f->db->read->users[2]['status']='inactive';$before=$f->db->snapshot();m6cDeny(fn()=>SiteBuildService::claimBuildRecovery($job['id'],[],$request));m6c($f->db->snapshot()===$before,'Unauthorized operator no persistence');$f->db->read->users[2]['status']='active';
    $r=SiteBuildService::claimBuildRecovery($job['id'],[],$request);ssCounts($f,6,3,3);
    $inspection=SiteBuildService::reconcileBuild($r['lease']);SiteBuildService::completeBuildRecovery($r['lease'],$inspection);$before=$f->db->snapshot();
    $again=SiteBuildService::claimBuildRecovery($job['id'],[],$request);m6c(($again['replayed']??false)&&!isset($again['lease'])&&$before===$f->db->snapshot(),'Operator key idempotent; no reusable lease');

    // Exercise the actual test PDO decorator and real artifact completion. The fault
    // below is expressly fake PDO/post-commit behavior, NOT the native TCP relay.
    $f=new WebsitePlatformM6CFixture();[$request,$c,$receipt]=$f->run();$wrapper=new M6CSupplementConnection($f->db);
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$wrapper);$releaseHooks=0;$commitHooks=0;
    $wrapper->afterRelease=static function()use(&$releaseHooks):void{$releaseHooks++;};
    $wrapper->beforeCommit=static function()use($f,&$commitHooks):void{$commitHooks++;$f->db->loseCommitAck=true;};
    m6cDeny(fn()=>SiteBuildService::completeBuildSuccess($c['lease'],$receipt));
    m6c($releaseHooks===1&&$commitHooks===1&&$f->db->tables['site_build_jobs'][$request['id']]['status']==='succeeded','Decorator observes final release transaction only');
    $f->db->read->users[1]['status']='inactive';$before=$f->db->snapshot();$r=SiteBuildService::completeBuildSuccess($c['lease'],$receipt);
    m6c($r['job']['status']==='succeeded'&&$before===$f->db->snapshot()&&$f->builds===1,'Modeled lost-result replay does not reauthorize success');

    foreach(['candidate','proof'] as $point){
        $f=new WebsitePlatformM6CFixture();$job=$f->db->request();$c=$f->db->claim();[$j,$producer]=$f->rows($c);$stem=$f->root.'/work/'.$j['job_key'].'/attempt-'.$producer['id'];$seen=false;
        $f->duringProgress=static function($pulse,bool $force)use($stem,$point,&$seen):void{
            if(($point==='candidate'&&glob($stem.'/public/assets/*.css'))||($point==='proof'&&$force&&is_file($stem.'.seal.json'))){$seen=true;SiteArtifactFiles::fail('storage_unavailable');}$pulse($force);
        };
        m6cDeny(fn()=>SiteBuildService::executeBuild($c['lease']));$f->duringProgress=null;
        m6c($seen&&!is_dir($f->releasePath($j)),'Observed actual private-file '.$point.' boundary; modeled exception not SIGKILL');
        m6c($f->store->verifyRelease($j,$producer)['disposition']===($point==='candidate'?'quarantined':'unknown'),'Boundary classifies safely');
    }
    $pdf=M6CSupplementPdf::bytes();preg_match('/startxref\n([0-9]+)\n%%EOF\n$/D',$pdf,$match);m6c(substr($pdf,(int)$match[1],4)==='xref','PDF actual xref offset');
    preg_match_all('/^([0-9]{10}) 00000 n $/m',$pdf,$offsets);foreach($offsets[1] as $i=>$offset)m6c(str_starts_with(substr($pdf,(int)$offset),($i+1).' 0 obj'),'PDF actual object offset');
    $f=ssPdfFixture();[$request,$c,$receipt]=$f->run();[$j,$producer]=$f->rows($c);$done=SiteBuildService::completeBuildSuccess($c['lease'],$receipt);
    $verified=$f->store->verifyRelease($j,$producer);m6c($verified['disposition']==='sealed'&&$done['job']['status']==='succeeded','Positive non-material approved PDF integration');
    M6CSupplementPdf::verify($f->releasePath($j),$verified['manifest'],$pdf,'m6c');
    m6c(CanonicalJson::hash($verified['manifest'])===$done['release']['manifest_hash']&&$f->db->events('site_build_succeeded')===1,'PDF result canonical hash and one audit');
    foreach(['rights','ownership','digest','ambiguous-approval'] as $bad){
        $f=ssPdfFixture();$base=$f->db->read->base;
        if($bad==='rights')$base->siteAssets[2]['rights_classification']='unknown';
        if($bad==='ownership')$base->siteAssets[2]['business_id']=60;
        if($bad==='digest')file_put_contents($f->root.'/uploads/approved.pdf',$pdf.'X');
        if($bad==='ambiguous-approval')$base->approvals[702]=array_replace($base->approvals[700],['id'=>702]);
        m6cDeny(fn()=>$f->db->request());m6c($f->db->tables['site_build_jobs']===[],'PDF negative before job: '.$bad);
    }
    echo "M6C supplement service/PDF: $assertions assertions passed; real files, fake PDO/clocks, modeled faults; NO native SQL/kernel proof.\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
