<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeReport.php';
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeFixture.php';
$checks=0;$scenarios=0;$childCount=0;$observations=[];
function observationCheck(bool $ok,string $why):void
{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function observationReport():array
{return M6CNativeReport::initial(str_repeat('1',40),str_repeat('2',32));}
function observationChild(string $code,string $mode='build',int $seconds=5,int $limit=65536):M6CNativeProcess
{
    global $childCount;$childCount++;
    $prefix='require '.var_export(__DIR__.'/support/WebsitePlatformM6CNativeWorkerError.php',true).';';
    $p=new M6CNativeProcess([PHP_BINARY,'-n','-d','zend.exception_ignore_args=1','-r',$prefix.$code],$seconds,$limit);
    M6CNativeDiagnostics::context($p,'concurrent-claims',$mode);return $p;
}
function observationRead(M6CNativeProcess $p,array $types,array &$report,?callable $sink=null):array
{return M6CNativeDiagnostics::readAllowed($p,$types,$report,'concurrent-claims','observationCheck',$sink??static fn($s)=>strlen($s));}
function observationFinish(M6CNativeProcess $p,array &$report):array
{return M6CNativeDiagnostics::finish($p,0,$report,'concurrent-claims','observationCheck',static fn($s)=>strlen($s));}
function observationCleanup(array &$report):void
{M6CNativeReport::finish($report,null,[M6CNativeProcess::class,'cleanup'],static fn($s)=>strlen($s));}
function observationExit(M6CNativeProcess $p):void
{
    $end=hrtime(true)+3000000000;while($p->running()&&hrtime(true)<$end)usleep(1000);
    observationCheck(!$p->running(),'bounded synthetic child exited');
}
function observationReject(callable $body):Throwable
{try{$body();}catch(Throwable $e){return $e;}throw new RuntimeException('accepted invalid protocol');}
$error='M6CNativeWorkerError::emit(new RuntimeException("worker_database_binding"),"build","database_binding",static fn($s)=>fwrite(STDERR,$s));exit(1);';
try{
    foreach(M6CNativeWorkerError::HARNESS as $code){
        $scenarios++;$r=M6CNativeWorkerError::parse(rtrim(M6CNativeWorkerError::line(new RuntimeException($code),'build','context'),"\n"));
        observationCheck($r['code']===$code&&$r['class']==='RuntimeException','known literal check preserved');
        observationCheck(!isset($r['sql']),'non-PDO error has no invented SQL diagnostics');
    }
    $canary="SECRET SELECT * FROM credentials /private/path DSN=password token=lease\x1b\0\r\n";
    foreach([new RuntimeException($canary),new class($canary) extends RuntimeException{},new TypeError($canary),new JsonException($canary),
        new SiteServiceException('conflict',$canary),new SiteArtifactException('artifact_invalid'),new SiteArtifactException($canary)] as $errorObject){
        $scenarios++;$line=M6CNativeWorkerError::line($errorObject,'SECRET','SECRET');$r=M6CNativeWorkerError::parse(rtrim($line,"\n"));
        observationCheck($r!==null&&$r['mode']==='unknown'&&$r['phase']==='unknown','unknown mode and phase bounded');
        observationCheck(!str_contains($line,'SECRET')&&!str_contains($line,'credentials')&&!str_contains($line,'private')&&!str_contains($line,'token='),'message/class/path/SQL/token canaries excluded');
    }
    $pdo=new PDOException($canary);$pdo->errorInfo=['23000',1452,$canary];
    foreach([$pdo,new RuntimeException($canary,0,$pdo)] as $errorObject){
        $scenarios++;$r=M6CNativeWorkerError::record($errorObject,'audit','complete');
        observationCheck($r['sql']===['sqlstate'=>'23000','driver'=>1452],'typed PDO evidence from existing bounded cause');
        observationCheck(!str_contains(json_encode($r),'SECRET'),'PDO message excluded');
    }
    $deep=$pdo;for($i=0;$i<4;$i++)$deep=new RuntimeException($canary,0,$deep);
    observationCheck(!isset(M6CNativeWorkerError::record($deep,'audit','complete')['sql']),'bounded exception-chain traversal');$scenarios++;
    foreach([['23000','1452'],["23000\n",1452],['secret',1452],['23000',-1],['23000',2147483648],[null,null]] as $badSql){
        $scenarios++;$pdo->errorInfo=$badSql;observationCheck(!isset(M6CNativeWorkerError::record($pdo,'audit','complete')['sql']),'invalid SQL metadata omitted');
    }
    $base=M6CNativeWorkerError::record(new RuntimeException('native_context'),'build','context');
    $badRecords=[];
    foreach(['mode','phase','class','code','version'] as $field){$r=$base;$r[$field]='SECRET';$badRecords[]=$r;$r=$base;unset($r[$field]);$badRecords[]=$r;}
    $badRecords[]=$base+['extra'=>$canary];$badRecords[]=$base+['sql'=>['sqlstate'=>'23000','driver'=>'1452']];
    $badRecords[]=$base+['sql'=>['sqlstate'=>'23000','driver'=>1452,'message'=>$canary]];
    $badRecords[]=$base+['sql'=>null];$badRecords[]=array_replace($base,['phase'=>"context\x00"]);
    $badLines=['{}','{"error":"RuntimeException","code":"native_context"}',M6CNativeWorkerError::PREFIX.'{',
        M6CNativeWorkerError::PREFIX.str_repeat('x',769),M6CNativeWorkerError::PREFIX.json_encode($base)."\x1b",M6CNativeWorkerError::PREFIX.json_encode($base)."\r",
        M6CNativeWorkerError::PREFIX.'{"version":1,"version":1,"mode":"build","phase":"context","class":"RuntimeException","code":"native_context"}'];
    foreach($badRecords as $r)$badLines[]=M6CNativeWorkerError::PREFIX.json_encode($r);
    foreach($badLines as $line){
        $scenarios++;observationCheck(M6CNativeWorkerError::parse($line)===null,'strict schema rejects neighboring record');
        $summary=M6CNativeDiagnostics::summary(['stderr'=>$line."\n"]);
        observationCheck($summary['worker_error']===null&&$summary['stderr']['recognized']===[]&&!str_contains(json_encode($summary),'SECRET'),'untrusted JSON is digest metadata only');
    }
    $scenarios++;$summary=M6CNativeDiagnostics::summary(['stderr'=>"Build-only outcome: succeeded.\n".M6CNativeWorkerError::line(new RuntimeException('native_context'),'build','context')]);
    observationCheck($summary['stderr']['recognized']===['Build-only outcome: succeeded.']&&$summary['worker_error']['code']==='native_context','production text and strict test record retained together');
    foreach(['return false;','throw new RuntimeException("SECRET");','return 1;'] as $sinkBody){
        $scenarios++;$p=observationChild('M6CNativeWorkerError::emit(new RuntimeException("native_context"),"build","context",static function($s){'.$sinkBody.'});exit(17);');
        $r=$p->finish();observationCheck($r['exit']===17&&$r['stderr']==='','worker diagnostic sink failure preserves caller exit');M6CNativeProcess::cleanup();
    }
    foreach([['VERIFY',[]],['RECOVERY',['job_id'=>1,'attempt_id'=>2]],['RESULT',['status'=>'failed','failure'=>'artifact_invalid','builds'=>1]],
        ['RESULT',['status'=>'succeeded','disposition'=>'adopted','builds'=>0]],['RESULT',['status'=>'failed','disposition'=>'safely_failed','builds'=>0]],
        ['RESULT',['status'=>'reconciliation_required','disposition'=>'blocked','builds'=>0]],['RESULT',['claimed'=>true,'status'=>'failed','builds'=>0]]] as [$tag,$payload]){
        $scenarios++;$report=observationReport();$p=observationChild('echo '.var_export($tag.' '.json_encode($payload)."\n",true).';');
        [, $actual]=observationRead($p,[$tag],$report);observationCheck($actual===$payload,'existing protocol result shape retained');observationFinish($p,$report);observationCleanup($report);
    }
    foreach(['pre-ready','post-ready','waiting-peer','both-exit','sink-false','sink-throw','sink-short'] as $scenario){
        $scenarios++;$report=observationReport();$output='';$peer=null;
        $sink=static function($s)use($scenario,&$output){if($scenario==='sink-false')return false;if($scenario==='sink-throw')throw new RuntimeException('SECRET');if($scenario==='sink-short')return 1;$output.=$s;return strlen($s);};
        $post=$scenario!=='pre-ready';$p=observationChild(($post?'echo "READY []\n";fgets(STDIN);':'').$error);
        if(in_array($scenario,['waiting-peer','both-exit'],true))$peer=observationChild('echo "READY []\n";fgets(STDIN);'.($scenario==='both-exit'?$error:'fgets(STDIN);'));
        $primary=null;
        try{
            observationRead($p,['READY'],$report,$sink);
            if($peer!==null)observationRead($peer,['READY'],$report,$sink);
            foreach(array_filter([$p,$peer]) as $child){$child->diagnosticContext['expected_messages']=['CLAIM','RESULT'];$child->send('CONTINUE');}
            observationRead($p,['CLAIM','RESULT'],$report,$sink);
        }catch(Throwable $e){$primary=$e;M6CNativeReport::failure($report,$e,'concurrent-claims');}
        observationCheck($primary?->getMessage()==='native_child_before_barrier','original before-barrier exception retained');
        $entry=$report['children'][0];
        observationCheck($entry['mode']==='build'&&$entry['case']==='concurrent-claims'&&$entry['pid']===$p->pid,'established child identity retained');
        observationCheck($entry['exit']===1&&$entry['expected_exit']===0&&$entry['last_barrier']===($post?'READY':null),'actual exit distinct from expected and last successful barrier');
        observationCheck($entry['expected_messages']===($post?['CLAIM','RESULT']:['READY'])&&$entry['worker_error']['code']==='worker_database_binding','expected alternatives and worker error retained');
        if(str_starts_with($scenario,'sink-'))observationCheck($report['child_diagnostic_write']==='FAIL','sink failure remains secondary');
        else observationCheck(str_contains($output,'M6C CHILD FAILURE')&&strlen($output)<=2048,'bounded diagnostic emitted before exception reached caller');
        if($scenario==='both-exit')observationExit($peer);
        observationCleanup($report);$clean=$report['terminated_children'];
        observationCheck($clean[0]['case']==='concurrent-claims'&&$clean[0]['mode']==='build'&&$clean[0]['expected_exit']===0
            &&$clean[0]['last_barrier']===($post?'READY':null)&&$clean[0]['worker_error']['code']==='worker_database_binding','cleanup preserves established context');
        if($scenario==='waiting-peer'){
            observationCheck($clean[1]['failure_observation']['running']===true&&$clean[1]['failure_observation']['exit']===null,'peer initially running with unknown exit');
            observationCheck($clean[1]['cleanup_observation']['running']===true&&$clean[1]['termination_requested']===true
                &&$clean[1]['expected_messages']===['CLAIM','RESULT']&&$clean[1]['worker_error']===null,'peer cleanup termination is not root-cause evidence');
        }
        if($scenario==='both-exit')foreach($clean as $child)observationCheck($child['exit']===1&&!$child['termination_requested']
            &&$child['cleanup_observation']['running']===false&&$child['worker_error']['code']==='worker_database_binding','both self-exited children retained');
        observationCheck($report['status']==='FAIL'&&$report['cases']['concurrent-claims']['status']==='FAIL'
            &&$report['cases']['no-publication']['status']==='UNREACHED','diagnostics cannot manufacture native PASS');
        observationCheck(M6CNativeProcess::cleanup()===[],'no duplicate child cleanup');$observations[$scenario]=$report;
    }
    // Both children reach READY before either is released, then both are released before reading results.
    $scenarios++;$report=observationReport();
    $winner=observationChild('echo "READY []\n";fgets(STDIN);echo "CLAIM {\"job_id\":1,\"attempt_id\":2}\n";fgets(STDIN);echo "SEALED {\"job_id\":1,\"attempt_id\":2}\n";fgets(STDIN);echo "RESULT {\"claimed\":true,\"status\":\"succeeded\",\"replayed\":true,\"builds\":1}\n";');
    $loser=observationChild('echo "READY []\n";fgets(STDIN);echo "RESULT {\"claimed\":false,\"builds\":0}\n";');
    foreach([$winner,$loser] as $p)observationRead($p,['READY'],$report);
    foreach([$winner,$loser] as $p)$p->send('CONTINUE');
    [$tag,$claim]=observationRead($winner,['CLAIM','RESULT'],$report);observationCheck($tag==='CLAIM'&&$claim===['job_id'=>1,'attempt_id'=>2],'valid winning CLAIM');
    [$tag,$result]=observationRead($loser,['CLAIM','RESULT'],$report);observationCheck($tag==='RESULT'&&$result===['claimed'=>false,'builds'=>0],'normal losing RESULT');observationFinish($loser,$report);
    $winner->send('CONTINUE');observationRead($winner,['SEALED'],$report);$winner->send('CONTINUE');[$tag,$result]=observationRead($winner,['RESULT'],$report);
    observationCheck($result['status']==='succeeded'&&$result['replayed']===true&&$result['builds']===1,'synthetic exact completion sequence');observationFinish($winner,$report);
    observationCheck(!array_filter($report['children'],static fn($r)=>$r['unexpected']),'normal children not mislabeled');observationCleanup($report);$observations['normal']=$report;
    $invalid=[['READY','READY {"extra":1}'],['CLAIM','CLAIM {}'],['CLAIM','CLAIM {"job_id":"1","attempt_id":2}'],['CLAIM','CLAIM {"job_id":1,"attempt_id":false}'],
        ['CLAIM','CLAIM {"job_id":1,"attempt_id":2,"secret":"SECRET"}'],['RESULT','RESULT {"claimed":false,"builds":1}'],['RESULT','RESULT {"claimed":true,"builds":0}'],
        ['RESULT','RESULT {"claimed":false,"builds":"0"}'],['RESULT','RESULT {"error":"SiteServiceException","code":"conflict"}'],
        ['READY','READY'],['READY','READY {'],['READY','READY null'],['CLAIM','RESULT {"claimed":false,"builds":0}'],['READY',str_repeat('x',8193)],['IDENTITY','IDENTITY {}']];
    foreach($invalid as [$type,$line]){
        $scenarios++;$report=observationReport();$p=observationChild('echo '.var_export($line."\n",true).';');
        $e=observationReject(function()use($p,$type,&$report){observationRead($p,[$type],$report);});
        observationCheck($p->lastBarrier===null&&count($report['children'])===1&&$report['children'][0]['unexpected'],'invalid shape never advances last observed message');
        observationCheck(!str_contains(json_encode($report),'SECRET'),'untrusted protocol payload not excerpted');observationCleanup($report);
    }
    foreach(['audit','validation','stale'] as $mode){
        $scenarios++;$report=observationReport();$r=['error'=>'SiteServiceException','code'=>$mode==='stale'?'conflict':'database_failure'];
        if($mode!=='stale')$r['sql']=['sqlstate'=>$mode==='audit'?'23000':'HY000','driver'=>$mode==='audit'?1452:3819];
        $p=observationChild('echo '.var_export('RESULT '.json_encode($r)."\n",true).';',$mode);
        [, $actual]=observationRead($p,['RESULT'],$report);observationFinish($p,$report);
        observationCheck($actual===$r&&!$report['children'][0]['unexpected'],'expected injected fault remains an exit-zero RESULT');observationCleanup($report);
    }
    foreach(['missing','timeout','overflow','final-buffered','failed-loser'] as $scenario){
        $scenarios++;$report=observationReport();$code=match($scenario){'missing'=>'exit(0);','timeout'=>'usleep(3000000);',
            'overflow'=>'echo str_repeat("x",1000);','failed-loser'=>'echo "RESULT {\"claimed\":false,\"builds\":0}\n";exit(1);',
            default=>'echo "RESULT {\"claimed\":false,\"builds\":0}\n";'};
        $p=observationChild($code,'build',$scenario==='timeout'?1:5,$scenario==='overflow'?256:65536);
        if($scenario==='final-buffered'){
            observationExit($p);[, $r]=observationRead($p,['RESULT'],$report);observationCheck($r===['claimed'=>false,'builds'=>0],'complete final buffered line survives exit');observationFinish($p,$report);
        }else{
            $e=observationReject(function()use($p,&$report){observationRead($p,['RESULT'],$report);observationFinish($p,$report);});
            observationCheck($e->getMessage()===match($scenario){'missing'=>'native_child_before_barrier','timeout'=>'native_child_timeout','overflow'=>'native_child_output_limit',default=>'actual_child_exit'},'original process failure retained');
            if($scenario==='overflow')observationCheck($report['children'][0]['stdout']['truncated'],'overflow remains bounded');
        }
        observationCleanup($report);
    }
    $scenarios++;$report=observationReport();$p=observationChild('echo "READY []\n";fgets(STDIN);');observationRead($p,['READY'],$report);
    M6CNativeDiagnostics::record($p,$report,'concurrent-claims','finish',0,null,true,static fn($s)=>strlen($s));
    observationCheck($report['children'][0]['running']===true&&$report['children'][0]['exit']===null,'live observation does not invent reaped exit');
    $unknown=M6CNativeDiagnostics::cleanupSummary(['exit'=>-1,'running'=>false,'context'=>['expected_exit'=>0]]);
    observationCheck($unknown['exit']===null&&$unknown['expected_exit']===0,'unknown terminal exit remains unknown');observationCleanup($report);
    // Construction/wiring uses existing fake PDO and private artifact roots; no UID/source/DB admission bypass.
    $scenarios++;$f=new WebsitePlatformM6CFixture();
    $config=['work_root'=>realpath($f->root.'/work'),'artifact_root'=>realpath($f->root.'/sealed'),'legacy_upload_root'=>realpath($f->root.'/uploads')];
    $adapter=new M6CNativeDependencies($config,1);$adapter->wire();
    observationCheck((new ReflectionProperty(SiteBuildService::class,'dependencies'))->getValue()===$adapter&&$adapter->builds===0,'actual instrumented dependency constructor and wiring succeed with private roots');
    observationCheck($adapter->operatorId()===1,'instrumented operator wiring retained');
}finally{
    M6CNativeProcess::cleanup();$roots=WebsitePlatformM6CFixture::$roots;WebsitePlatformM6CFixture::cleanup();
    foreach($roots as $root)observationCheck(!file_exists($root),'owned adapter fixture removed');
}
echo "Website platform M6C native observation: $checks assertions / $scenarios scenarios / $childCount real synthetic children passed; no native or live database execution.\n";
