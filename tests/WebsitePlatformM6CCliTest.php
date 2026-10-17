<?php

declare(strict_types=1);
$assertions=0;$root=dirname(__DIR__);$script=$root.'/scripts/site-build-worker.php';
function m6cli(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function m6process(array $command,array $env=[]):array{
    $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env===[]?null:$env);
    if(!is_resource($p))throw new RuntimeException('Process unavailable');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
}
foreach([[],['--help','--once'],['--actor','1'],['--dsn','mysql:host=bad'],['--environment','production'],['--root','/'],['--recover','0'],['--recover','1','--once']]as$args){
    [$exit,$out,$err]=m6process(array_merge([PHP_BINARY,'-n',$script],$args));m6cli($exit===2&&$out===''&&$err==="Invalid arguments. Use --help.\n",'Reject untrusted args before bootstrap');
}
[$exit,$out,$err]=m6process([PHP_BINARY,'-n',$script,'--help']);m6cli($exit===0&&str_contains($out,'Build only')&&$err==='','Help has no bootstrap or jobs');
[$exit,$out,$err]=m6process([PHP_BINARY,'-n',$script,'--once']);
m6cli($exit===1&&$out===''&&$err==="Build-only invocation stopped: future_gate_required.\n",'Untrusted Windows invocation rejected before database configuration');
require_once $root.'/private/classes/SiteBuildRuntime.php';
try{SiteBuildRuntime::fromTrustedCli();throw new RuntimeException('Unexpected authority');}catch(SiteServiceException $e){m6cli($e->classification()==='future_gate_required','Including runtime cannot grant CLI identity');}
// Existing CGI binary exercises the web SAPI denial; no server or socket is started.
$cgi=dirname(PHP_BINARY).DIRECTORY_SEPARATOR.(PHP_OS_FAMILY==='Windows'?'php-cgi.exe':'php-cgi');
$web=0;if(is_file($cgi)){
    [$exit,$out,$err]=m6process([$cgi,'-n','-d','cgi.force_redirect=0','-f',$script],['REDIRECT_STATUS'=>'1','REQUEST_METHOD'=>'GET','SCRIPT_FILENAME'=>$script,'QUERY_STRING'=>'once=1','SystemRoot'=>getenv('SystemRoot')?:'']);
    m6cli(str_contains($out,'Status: 404')&&!str_contains($out,'Build-only')&&$err==='','Web invocation has no worker bootstrap');$web=1;
}
echo "Website platform M6C CLI: $assertions assertions passed; $web CGI invocation executed.\n";
