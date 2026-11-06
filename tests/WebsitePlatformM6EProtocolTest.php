<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/classes/SiteDeploymentService.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
$assertions=0;
$check=static function(bool $ok,string $why)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($why);};
$target=['site_id'=>10,'target_id'=>20,'environment'=>'staging','publisher_key'=>'apache-digitalocean-v1','binding_key'=>'fixture','binding_version'=>1];
$b=['site_id'=>10,'target_id'=>20,'binding_key'=>'fixture','binding_version'=>1];
$class=new ReflectionClass(SitePublisherHostContract::class);$host=$class->newInstanceWithoutConstructor();
$class->getConstructor()->invoke($host,['worker_id'=>'fixture-worker','bindings'=>[$b]]);
$lease=['site_id'=>10,'target_id'=>20,'deployment_id'=>100,'attempt_id'=>101,'attempt_kind'=>'execution','worker_id'=>'fixture-worker',
    'environment'=>'staging','token'=>str_repeat('a',64),'fence_epoch'=>1];
try {
    foreach(['valid','sequence','malformed','oversize','eof'] as $mode) {
        $child=new M6CNativeProcess([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0',__DIR__.'/support/WebsitePlatformM6ESocketChild.php',$mode],15,4096);
        $endpoint=$child->line(5);$check(preg_match('/^127\.0\.0\.1:[1-9][0-9]{0,4}$/D',$endpoint)===1,'Owned private loopback endpoint');
        $stream=stream_socket_client('tcp://'.$endpoint,$errno,$error,2);stream_set_timeout($stream,3);
        $session=new SitePublisherSession($host);$session->bind($lease);
        (new ReflectionProperty(SitePublisherSession::class,'stream'))->setValue($session,$stream);
        $error=null;
        try{$result=$session->request('inspect',$target);}catch(Throwable $e){$error=$e;}
        if($mode==='valid')$check($error===null&&$result===['fixture'=>true],'Actual bounded transport valid response');
        else {$check($error instanceof SiteServiceException,'Invalid response fails closed '.$mode);
            $check(!str_contains($error->getMessage(),'CANARY')&&!str_contains($error->getMessage(),$lease['token']),'Primary safe error '.$mode);}
        $session->close();$child->closeInput();$r=$child->finish();
        $check($r['exit']===0&&$r['stderr']===''&&!$r['signaled']&&str_contains($r['stdout'],'owned-child-complete'),'Owned child completed/cleaned '.$mode);
    }
    echo "M6E protocol: $assertions assertions PASS (actual bounded private socket/children; synthetic helper responses).\n";
}catch(Throwable $primary){M6CNativeProcess::observeFailure();throw $primary;}
finally{M6CNativeProcess::cleanup();}
