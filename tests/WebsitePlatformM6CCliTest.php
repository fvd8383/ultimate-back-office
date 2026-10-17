<?php
declare(strict_types=1);
$assertions=0;$root=dirname(__DIR__);$script=$root.'/scripts/site-build-worker.php';$child=__DIR__.'/support/WebsitePlatformM6CCliChild.php';
function m6cli(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function m6process(array $command,array $env=[],int $timeoutNanoseconds=10000000000):array{
    // Windows anonymous pipes can block despite stream_set_blocking(false).
    // Private capture files let the parent enforce time/output bounds while the child runs.
    $stdout=tempnam(sys_get_temp_dir(),'ubo-m6c-out-');$stderr=tempnam(sys_get_temp_dir(),'ubo-m6c-err-');
    if($stdout===false||$stderr===false)throw new RuntimeException('Capture unavailable');
    try{
        $p=proc_open($command,[0=>['pipe','r'],1=>['file',$stdout,'wb'],2=>['file',$stderr,'wb']],$pipes,null,$env===[]?null:$env);
        if(!is_resource($p))throw new RuntimeException('Process unavailable');fclose($pipes[0]);$start=hrtime(true);
        do{
            $status=proc_get_status($p);clearstatcache();
            if(hrtime(true)-$start>$timeoutNanoseconds||filesize($stdout)+filesize($stderr)>65536){proc_terminate($p);proc_close($p);throw new RuntimeException('Bounded child failed');}
            if($status['running'])usleep(1000);
        }while($status['running']);
        $closed=proc_close($p);return [$status['exitcode']>=0?$status['exitcode']:$closed,file_get_contents($stdout),file_get_contents($stderr)];
    }finally{unlink($stdout);unlink($stderr);}
}
function isolatedCli(string $case,array $args,array $profile=['-n']):array{
    global $child;
    [$exit,$out,$err]=m6process(array_merge([PHP_BINARY],$profile,[$child,$case],$args));
    m6cli(preg_match('/\nM6C_TRACE:(.+)\n$/D',$err,$match)===1,'Child reports actual trace: '.$case.' '.$err);
    $trace=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);$err=substr($err,0,-strlen($match[0]));
    $GLOBALS['child_results'][]=['case'=>$case,'args'=>$args,'profile'=>$profile,'exit'=>$exit,'stdout'=>$out,'stderr'=>$err,'trace'=>$trace];
    m6cli($trace['host_config_reads']===0&&$trace['db']===0&&$trace['claims']===0&&$trace['file_output']===0&&!$trace['global_db_initialized'],'No host config/DB/queue/file output: '.$case);
    return [$exit,$out,$err,$trace];
}
$invalid=[[],['--help','--once'],['--actor','1'],['--dsn','mysql:host=bad'],['--environment','production'],['--root','/'],['--recover','0'],['--recover','1','--once']];
$start=hrtime(true);$timedOut=false;
try{m6process([PHP_BINARY,'-n',$child,'timeout','--help'],[],1000000000);}catch(RuntimeException $e){$timedOut=$e->getMessage()==='Bounded child failed';}
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
    m6cli($exit===1&&$out===''&&$err==='Build-only invocation stopped: '.$code.".\n",'Exact isolated denial '.$case.' '.$err);
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
echo 'Isolated child results: '.json_encode($GLOBALS['child_results'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
echo "Website platform M6C CLI: $assertions assertions passed; $web actual CGI invocation; 32 isolated valid-argument denials; Linux/runtime branches simulated.\n";
