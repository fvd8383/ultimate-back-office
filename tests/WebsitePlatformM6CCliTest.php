<?php
declare(strict_types=1);
$assertions=0;$root=dirname(__DIR__);$script=$root.'/scripts/site-build-worker.php';$child=__DIR__.'/support/WebsitePlatformM6CCliChild.php';
require_once __DIR__.'/support/WebsitePlatformM6CCliProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CExecutionProfile.php';
function m6cli(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function isolatedCli(string $case,array $args,array $profile=['-n']):array{
    global $child;
    $trace=null;$cleanErr=null;
    [$exit,$out]=m6process(array_merge([PHP_BINARY],$profile,[$child,$case],$args),[],10000000000,
        ['case'=>$case,'profile'=>in_array('-n',$profile,true)?'no-ini':'configured-default'],function($result)use(&$trace,&$cleanErr,$case):void{
            [$trace,$cleanErr]=m6childTrace($result,'M6C_TRACE');
            m6cli(true,'Child reports actual trace: '.$case);
            m6cli($trace['host_config_reads']===0&&$trace['db']===0&&$trace['claims']===0&&$trace['file_output']===0&&!$trace['global_db_initialized'],'No host config/DB/queue/file output: '.$case);
        });
    $err=$cleanErr;
    $GLOBALS['child_results'][]=['case'=>$case,'args'=>$args,'profile'=>$profile,'exit'=>$exit,'stdout'=>$out,'stderr'=>$err,'trace'=>$trace];
    return [$exit,$out,$err,$trace];
}
$invalid=[[],['--help','--once'],['--actor','1'],['--dsn','mysql:host=bad'],['--environment','production'],['--root','/'],['--recover','0'],['--recover','1','--once']];
$start=hrtime(true);$timedOut=false;
try{m6process([PHP_BINARY,'-n',$child,'timeout','--help'],[],1000000000,['case'=>'timeout','profile'=>'no-ini']);}
catch(M6CChildFailure $e){$timedOut=$e->reason==='timeout'&&$e->outcome['running']===false;m6discardExpectedFailure($e);}
m6cli($timedOut&&hrtime(true)-$start<5000000000,'Parent terminates a stalled isolated child without blocking on pipes');
foreach($invalid as$args){
    [$exit,$out,$err]=m6process(array_merge([PHP_BINARY,'-n',$script],$args));
    m6cli($exit===2&&$out===''&&$err==="Invalid arguments. Use --help.\n",'Actual argument denial before bootstrap');
    [$exit,$out,$err,$trace]=isolatedCli('configured-host',$args);
    m6cli($exit===2&&$trace['bootstrap']===0&&$trace['virtual_config_reads']===0&&$trace['blocked_processes']===0,'Invalid arguments never bootstrap on configured-host double');
}
[$exit,$out,$err]=m6process([PHP_BINARY,'-n',$script,'--help']);
m6cli($exit===0&&str_contains($out,'Build only')&&$err==='','Actual help before bootstrap');
[$exit,$out,$err,$trace]=isolatedCli('configured-host',['--help']);
m6cli($exit===0&&str_contains($out,'Build only')&&$err===''&&$trace['bootstrap']===0&&$trace['virtual_config_reads']===0,'Help has no bootstrap/configuration');
$cases=['unsupported-os'=>'future_gate_required','wrong-entry'=>'future_gate_required','missing-posix_geteuid'=>'future_gate_required',
    'missing-dom'=>'builder_unavailable','missing-getimagesizefromstring'=>'builder_unavailable','missing-fsync'=>'builder_unavailable','missing-filter_var'=>'builder_unavailable',
    'missing-config'=>'builder_unavailable','invalid-json'=>'invalid_request','invalid-config'=>'builder_unavailable',
    'wrong-owner'=>'builder_unavailable','writable-config'=>'builder_unavailable','ancestor-owner'=>'builder_unavailable',
    'wrong-uid'=>'builder_unavailable','root-worker'=>'builder_unavailable','configured-host'=>'builder_unavailable'];
foreach($cases as$case=>$code)foreach([['--once'],['--recover','1']]as$args){
    // Valid work arguments go ONLY to the isolated child, never the host entry point.
    [$exit,$out,$err,$trace]=isolatedCli($case,$args);
    m6cli($exit===1&&$out===''&&$err==='Build-only invocation stopped: '.$code.".\n",'Exact isolated denial '.$case);
    m6cli($trace['bootstrap']===2&&$trace['blocked_processes']===($case==='configured-host'?1:0),'Fixed runtime selection / external process denial '.$case);
    if(str_starts_with($case,'missing-')&&$case!=='missing-config')m6cli($trace['virtual_config_reads']===0,'Capabilities checked before configuration');
}
foreach([[],['-n','-d','disable_functions=ctype_digit,mb_strlen']]as$profile){
    [$exit,$out,$err]=isolatedCli('missing-dom',['--once'],$profile);
    m6cli($exit===1&&str_contains($err,'builder_unavailable'),'Invocation profile capability denial');
}
[$exit,$out,$err,$trace]=isolatedCli('web',['--once']);
m6cli($exit===0&&$out===''&&$err===''&&$trace['http_status']===404&&$trace['bootstrap']===0,'Simulated web SAPI has no bootstrap');
require_once $root.'/private/classes/SiteBuildRuntime.php';
try{SiteBuildRuntime::fromTrustedCli();throw new RuntimeException('Unexpected authority');}catch(SiteServiceException $e){m6cli($e->classification()==='future_gate_required','Wrong script identity cannot bootstrap runtime');}
$cgi=dirname(PHP_BINARY).DIRECTORY_SEPARATOR.(PHP_OS_FAMILY==='Windows'?'php-cgi.exe':'php-cgi');
$web=0;if(is_file($cgi)){
    [$exit,$out,$err]=m6process([$cgi,'-n','-d','cgi.force_redirect=0','-f',$script],['REDIRECT_STATUS'=>'1','REQUEST_METHOD'=>'GET','SCRIPT_FILENAME'=>$script,'QUERY_STRING'=>'once=1','SystemRoot'=>getenv('SystemRoot')?:'']);
    m6cli($exit===0&&str_contains($out,'Status: 404')&&!str_contains($out,'Build-only')&&$err==='','Actual CGI denial; no server/socket');$web=1;
}
$executionProfile=m6ExecutionProfile();
foreach(['revoked','builder-unavailable','builder-mismatch','no-work']as$case){
    $trace=null;$err=null;
    [$exit,$out]=m6process([PHP_BINARY,...$executionProfile['flags'],__DIR__.'/support/WebsitePlatformM6CExecutionChild.php',$case],[],10000000000,
        ['case'=>'execution-'.$case,'profile'=>$executionProfile['label']],function($result)use($case,&$trace,&$err):void{
    [$trace,$err]=m6childTrace($result,'M6C_EXECUTION_TRACE');[$exit,$out]=$result;$empty=$case==='no-work';
    m6cli(true,'Execution child reports real fixture trace');
    m6cli($exit===($empty?0:1)&&$out==='Build-only outcome: '.($empty?'no_work_claimed':'failed').".\n"&&$err==='','Claimed failure differs from no work with fixed safe output');
    m6cli($trace['host_config_reads']===0&&$trace['live_database_connections']===0&&$trace['files']===[]&&$trace['artifact_io']===0&&$trace['builds']===0&&$trace['releases']===0,'Isolated execution denial has no artifact/host effects');
    if(!$empty){
        m6cli($trace['jobs'][0]['status']==='failed'&&$trace['attempts'][0]['status']==='failed'&&$trace['started']===1&&$trace['failed']===1,'CLI records atomic failed claim');
        m6cli($trace['jobs'][0]['execution_count']===1&&$trace['jobs'][0]['attempt_count']===1&&$trace['jobs'][0]['recovery_count']===0,'CLI preserves allocated claim counters');
        m6cli($trace['jobs'][0]['failure_code']===($case==='revoked'?'requester_not_authorized':'builder_unavailable'),'CLI exact denial category');
    }
        });
    $GLOBALS['child_results'][]=['case'=>'execution-'.$case,'profile'=>$executionProfile['label'],'exit'=>$exit,'stdout'=>$out,'stderr'=>$err,'trace'=>$trace];
}
echo 'Isolated child results: '.json_encode($GLOBALS['child_results'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
echo "Website platform M6C CLI: $assertions assertions passed; $web actual CGI invocation; 32 isolated valid-argument denials; Linux/runtime branches simulated.\n";
