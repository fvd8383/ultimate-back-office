<?php
declare(strict_types=1);
// Synthetic API observations + real private pending files + actual service with fake PDO.
// NO native helper load, Linux injection, native SQL or power-loss evidence.
require_once __DIR__.'/support/WebsitePlatformM6CJournalSynthetic.php';
require_once __DIR__.'/support/WebsitePlatformM6CJournalChannel.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeTransport.php';
require_once __DIR__.'/support/WebsitePlatformM6CJournalAdmission.php';
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
$sha=str_repeat('a',40);$token=str_repeat('b',32);$job='aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
function jf(bool $ok,string $label):void{m6c($ok,$label);}
function jfDeny(callable $f,string $label):void{try{$f();}catch(Throwable){jf(true,$label);return;}throw new RuntimeException('Missing rejection: '.$label);}
try{
    foreach(M6CJournalFault::OPERATIONS as $mode){
        $v=journalSyntheticObservation($mode);M6CJournalContract::observation($v,$mode,$token);jf(true,'literal trace accepted');
        jf($v['accepted']['fsync']===($mode==='flush'||$mode==='fsync'?0:1),'flush short circuit and fsync failure distinct');
        jf($v['accepted']['fclose']===1,'finally close observed even after injection');
        jf($v['accepted']['chmod']===($mode==='publication'||$mode==='control'?1:0),'mode only after successful checked flush/fsync');
        foreach(['injected','matched','off_target','bytes'] as $key){$bad=$v;$bad[$key]=0;jfDeny(fn()=>M6CJournalContract::observation($bad,$mode==='control'?'flush':$mode,$token),'missing observation '.$key);}
        $bad=$v;$bad['calls']['fsync']=2;jfDeny(fn()=>M6CJournalContract::observation($bad,$mode,$token),'duplicate call');
        $bad=$v;$bad['accepted']['fflush']=0;jfDeny(fn()=>M6CJournalContract::observation($bad,'publication',$token),'wrong prerequisite');
        $bad=$v;$bad['raw_path']="SECRET\nCANARY";jfDeny(fn()=>M6CJournalContract::observation($bad,$mode,$token),'privacy extra key');
        jfDeny(fn()=>M6CJournalContract::observation($v,$mode,str_repeat('c',32)),'wrong run');
    }
    foreach([['other',$token,$job,1],['flush',"SECRET\nCANARY",$job,1],['flush',$token,'../target',1],['flush',$token,$job,0]] as $args)
        jfDeny(fn()=>new M6CJournalFault(...$args),'arming identity');
    jfDeny(fn()=>m6c_journal_observe('fopen','before',false,0),'unverified arming');
    jf(!m6c_journal_authorize($token,$job,1),'unarmed authorization refused');
    $m6cJournalFault=new M6CJournalFault('flush',$token,$job,1);
    jf(m6c_journal_authorize($token,$job,1),'exact armed identity');
    foreach([[str_repeat('a',32),$job,1],[$token,'cccccccc-cccc-cccc-cccc-cccccccccccc',1],[$token,$job,2]] as $wrong)
        jf(!m6c_journal_authorize(...$wrong),'wrong run target or producer refused');
    unset($m6cJournalFault);
    foreach(['fsync','rename','chmod','fclose'] as $op){$p=new M6CJournalFault('flush',$token,$job,1);jfDeny(fn()=>$p->observe($op,'before',false,0),'wrong first phase');}
    $p=new M6CJournalFault('flush',$token,$job,1);$p->observe('fopen','before',false,0);
    jfDeny(fn()=>$p->observe('fopen','before',false,0),'duplicate before');
    $p=new M6CJournalFault('flush',$token,$job,1);jfDeny(fn()=>$p->observe('fopen','after',true,0),'missing before');
    jfDeny(fn()=>$p->evidence(['run'=>$token,'job'=>$job,'producer'=>1,'matched'=>0,'injected'=>0,'off_target'=>7]),'unreached cannot pass');
    foreach(['accepted-fault','skipped-close','wrong-native-target','duplicate-native-hit'] as $bad){
        $p=new M6CJournalFault('flush',$token,$job,1);
        foreach(['fopen','fwrite'] as $op){$p->observe($op,'before',false,0);$p->observe($op,'after',true,$op==='fwrite'?128:0);}
        jf($p->observe('fflush','before',false,0),'selected actual policy failure decision');
        if($bad==='accepted-fault'){jfDeny(fn()=>$p->observe('fflush','after',true,0),'success cannot be reported as failure');continue;}
        $p->observe('fflush','after',false,0);
        jfDeny(fn()=>$p->observe('fsync','before',false,0),'short-circuited fsync cannot count as reached');
        if($bad!=='skipped-close'){$p->observe('fclose','before',false,0);$p->observe('fclose','after',true,0);}
        $native=['run'=>$token,'job'=>$job,'producer'=>$bad==='wrong-native-target'?2:1,'matched'=>4,'injected'=>$bad==='duplicate-native-hit'?2:1,'off_target'=>7];
        jfDeny(fn()=>$p->evidence($native),'unverified native state '.$bad);
    }
    $sourceHash=str_repeat('c',64);$binaryHash=str_repeat('d',64);
    $p=new M6CJournalFault('flush',$token,$job,1);
    foreach(['fopen','fwrite','fflush','fclose'] as $op){$p->observe($op,'before',false,0);$p->observe($op,'after',in_array($op,['fopen','fwrite'],true),$op==='fwrite'?128:0);}
    $secondary=$p->evidence(['run'=>$token,'job'=>$job,'producer'=>1,'matched'=>4,'injected'=>1,'off_target'=>7]);
    jf($secondary['accepted']['fclose']===0,'secondary close observed without masking pending caller failure');
    jfDeny(fn()=>M6CJournalContract::observation($secondary,'flush',$token),'secondary close failure is non-PASS');
    $receipt=['version'=>1,'source_sha'=>$sha,'php'=>'8.4.27','zts'=>0,'source_hash'=>$sourceHash,'binary_hash'=>$binaryHash];
    M6CJournalAdmission::receipt($receipt,$sha,'8.4.27',0,$sourceHash,$binaryHash);jf(true,'synthetic helper receipt matches exact build identity');
    foreach(array_keys($receipt) as $field){$bad=$receipt;$bad[$field]="SECRET\nCANARY";jfDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',0,$sourceHash,$binaryHash),'helper receipt '.$field);}
    $primary=new RuntimeException('original_failure');
    try{M6CJournalFault::primary(fn()=>throw $primary,fn()=>throw new RuntimeException('secondary_cleanup'));throw new RuntimeException('missing primary');}
    catch(Throwable $e){jf($e===$primary,'same original failure survives secondary cleanup');}
    $r=journalSyntheticReport($sha,$token);M6CJournalContract::evidence($r,$sha,$token);jf(true,'fixed three-case evidence accepted synthetically');
    $bytes=json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    jf(M6CNativeTransport::envelope($bytes,$sha,$token,M6CJournalContract::VERSION)===$r,'actual selected transport envelope');
    foreach([M6CNativeContract::VERSION,M6CSupplementContract::VERSION] as $old)jfDeny(fn()=>M6CNativeTransport::envelope($bytes,$sha,$token,$old),'old profile rejects journal');
    foreach([M6CNativeReport::initial($sha,$token),M6CSupplementContract::initial($sha,$token)] as $old)
        jfDeny(fn()=>M6CJournalContract::decode(json_encode($old,JSON_PRETTY_PRINT),$sha,$token),'journal rejects old profile');
    $first=M6CJournalContract::CASES[0];
    foreach(['missing','extra','order','FAIL','UNREACHED','lane','assertions','completed','injection','target','control','state','recovery','children'] as $fault){
        $bad=$r;
        switch($fault){
            case 'missing':unset($bad['cases'][$first]);break;
            case 'extra':$bad['cases']['unexpected']=['status'=>'PASS'];break;
            case 'order':$bad['cases']=array_reverse($bad['cases'],true);break;
            case 'FAIL':case 'UNREACHED':$bad['cases'][$first]['status']=$fault;break;
            case 'lane':$bad['cases'][$first]['lane']='protected';break;
            case 'assertions':$bad['cases'][$first]['assertions']=0;break;
            case 'completed':$bad['completed_assertions']--;break;
            case 'injection':$bad['cases'][$first]['fault']['injected']=0;break;
            case 'target':$bad['cases'][$first]['fault']['target_digest']=$bad['cases'][$first]['control']['target_digest'];break;
            case 'control':$bad['cases'][$first]['control_success']=false;break;
            case 'state':$bad['cases'][$first]['state']['inspection']='safe_absence';break;
            case 'recovery':$bad['cases'][$first]['recovery_attempt']=1;break;
            case 'children':$bad['children'][0]['unexpected']=true;break;
        }jfDeny(fn()=>M6CJournalContract::evidence($bad,$sha,$token),'strict report '.$fault);
    }
    $duplicate=str_replace('"native": true,','"native": true, "native": true,',$bytes);
    jfDeny(fn()=>M6CJournalContract::decode($duplicate,$sha,$token),'duplicate member rejected');
    $bad=$r;$bad['children'][0]['journal_case']=M6CSupplementContract::CASES[0];jfDeny(fn()=>M6CJournalContract::evidence($bad,$sha,$token),'wrong-profile child');
    $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();$done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    jf($done['job']['status']==='succeeded'&&count($f->db->tables['site_releases'])===1,'real file/service no-fault control with fake PDO; no native helper claim');
    M6CJournalFault::offTarget($f->root.'/control');jf(!file_exists($f->root.'/control'),'actual shared off-target file control and scoped cleanup');
    mkdir($f->root.'/collision',0700);SiteArtifactFiles::writeNew($f->root.'/collision/marker','retain',64);
    jfDeny(fn()=>M6CJournalFault::offTarget($f->root.'/collision'),'preexisting control namespace refused');
    jf(file_get_contents($f->root.'/collision/marker')==='retain','collision never transfers cleanup ownership');
    foreach(['storage_unavailable','outcome_unknown'] as $classification){
        $f=new WebsitePlatformM6CFixture();$request=$f->db->request();$claim=$f->db->claim();[$j,$producer]=$f->rows($claim);
        $stem=$f->root.'/work/'.$j['job_key'].'/attempt-'.$producer['id'];
        $f->duringBuild=static function()use($stem,$classification):void{
            // Explicitly modeled pending-state setup, NOT flush/fsync/rename injection.
            SiteArtifactFiles::mkdir(dirname($stem));SiteArtifactFiles::writeNew(dirname($stem).'/writer.lock','',1);
            SiteArtifactFiles::writeNew($stem.'.json.pending','complete-looking-but-unaccepted',4096);SiteArtifactFiles::fail($classification);
        };
        try{SiteBuildService::executeBuild($claim['lease']);throw new RuntimeException('expected model failure');}
        catch(SiteArtifactException $e){jf($e->failureCode===$classification,'original modeled classification');}
        $digest=hash_file('sha256',$stem.'.json.pending');jf($f->store->verifyRelease($j,$producer)['disposition']==='unknown','real pending inspection never safe absence');
        $done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$classification,'receipt_key'=>$j['release_key']]);
        jf($done['job']['status']==='reconciliation_required','service derives uncertain settlement');$f->duringBuild=null;
        $j=$f->db->tables['site_build_jobs'][$request['id']];$f->db->advance(max(0,strtotime($j['next_recovery_at'])-strtotime($f->db->now)));
        $recovery=SiteBuildService::claimBuildRecovery($request['id'],[]);jf(($recovery['claimed']??false)===true,'service claims recovery');
        $inspection=SiteBuildService::reconcileBuild($recovery['lease']);jf($inspection['disposition']==='blocked','uncertain pending cannot adopt');
        $done=SiteBuildService::completeBuildRecovery($recovery['lease'],$inspection);$j=$f->db->tables['site_build_jobs'][$request['id']];
        jf($j['recovery_status']==='blocked'&&[$j['attempt_count'],$j['execution_count'],$j['recovery_count']] === [2,1,1],'monotonic service accounting');
        jf($recovery['attempt']['recovery_of_attempt_id']===$producer['id']&&$recovery['attempt']['id']!==$producer['id'],'original producer distinct');
        jf($f->builds===1&&$f->db->tables['site_releases']===[]&&$f->db->tables['site_release_validations']===[]&&$f->db->events('site_build_succeeded')===0,'no rerender release validation or success event');
        jf(hash_file('sha256',$stem.'.json.pending')===$digest&&!file_exists($stem)&&!file_exists($stem.'.json'),'pending retained without candidate or journal');
    }
    echo "M6C journal policy/state: $assertions assertions passed; SYNTHETIC API observations, real private files, fake PDO; native helper NOT loaded.\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
