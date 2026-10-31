<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CGitFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeGitSource.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeDiagnostics.php';
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
$checks=0;$scenarios=0;$g=null;$savedEnvironment=getenv();
function sourceCheck(bool $ok,string $label):void
{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function sourceReject(callable $body,string $code='instrumented_source_identity'):Throwable
{
    try{$body();}catch(Throwable $error){sourceCheck($error->getMessage()===$code,'original source/process exception retained');return $error;}
    throw new RuntimeException('expected source verification rejection');
}
function sourceVerify(M6CGitFixture $g,?string $sha=null,?string $repo=null,?array $git=null,string $mode='build',?callable $write=null):void
{M6CNativeGitSource::verify($repo??$g->repo,$sha??$g->sha,$git??[$g->git],$g->platformEnvironment(),$mode,$write??static fn($line)=>strlen($line));}
function sourceRecord(string $line,string $phase,?int $exit,?bool $stderr,?bool $matched,string $mode='build'):array
{
    $record=M6CNativeWorkerError::parse(rtrim($line,"\n"));
    sourceCheck($record!==null&&$record['phase']===$phase&&$record['mode']===$mode,'source operation and actual mode retained');
    sourceCheck($record['source']===['exit'=>$exit,'stderr_nonempty'=>$stderr,'matched'=>$matched],'observed source facts retained exactly');
    return $record;
}
function sourceScript(M6CGitFixture $g,string $name,string $code):array
{
    $path=$g->root.'/'.$name.'.php';sourceCheck(file_put_contents($path,"<?php\n".$code)!==false,'owned synthetic Git script');
    return [PHP_BINARY,'-n','-d','zend.exception_ignore_args=1',$path];
}
try{
    $g=new M6CGitFixture();$scenarios++;
    sourceCheck(M6CNativeGitSource::environment()===['PATH'=>'/usr/bin:/bin','HOME'=>'/nonexistent','GIT_CONFIG_NOSYSTEM'=>'1','LANG'=>'C'],'exact native production environment profile');
    sourceCheck($g->platformEnvironment()['HOME']!==M6CNativeGitSource::environment()['HOME'],'local Windows/host profile distinguished from native Linux contract');
    $g->environment([],fn()=>sourceVerify($g));sourceCheck(true,'clean exact real Git repository accepted');
    $contaminations=[
        'home'=>['HOME'=>$g->root.'/bad-home'],
        'xdg'=>['XDG_CONFIG_HOME'=>$g->root.'/bad-xdg'],
        'global'=>['GIT_CONFIG_GLOBAL'=>$g->root.'/bad-global'],
        'system'=>['GIT_CONFIG_SYSTEM'=>$g->root.'/bad-global','GIT_CONFIG_NOSYSTEM'=>'0'],
        'parameters'=>['GIT_CONFIG_PARAMETERS'=>'INVALID-SECRET-CONFIG'],
        'count'=>['GIT_CONFIG_COUNT'=>'1','GIT_CONFIG_KEY_0'=>'core.excludesFile','GIT_CONFIG_VALUE_0'=>$g->root.'/ignore'],
    ];
    foreach($contaminations as $environment){$scenarios++;$g->environment($environment,fn()=>sourceVerify($g));sourceCheck(true,'explicit child ignores owned inherited configuration contamination');}

    $scenarios++;$line='';$sink=static function($value)use(&$line){$line.=$value;return strlen($value);};
    sourceReject(fn()=>sourceVerify($g,str_repeat('0',40),write:$sink));sourceRecord($line,'source_head',0,false,false);
    file_put_contents($g->repo.'/tracked.txt',"dirty target\n");$line='';$scenarios++;
    sourceReject(fn()=>sourceVerify($g,write:$sink));sourceRecord($line,'source_status',0,false,false);
    $scenarios++;$line='';
    $g->environment(['GIT_DIR'=>$g->foreign.'/.git','GIT_WORK_TREE'=>$g->foreign,'GIT_INDEX_FILE'=>$g->foreign.'/.git/index'],
        fn()=>sourceReject(fn()=>sourceVerify($g,write:$sink)));
    sourceRecord($line,'source_status',0,false,false);
    file_put_contents($g->repo.'/tracked.txt',"known tracked content\n");
    file_put_contents($g->repo.'/untracked-canary.txt',"genuine untracked fixture\n");$scenarios++;$line='';
    $g->environment($contaminations['count'],fn()=>sourceReject(fn()=>sourceVerify($g,write:$sink)));
    sourceRecord($line,'source_status',0,false,false);unlink($g->repo.'/untracked-canary.txt');

    // A second commit makes foreign HEAD and index contents observably different.
    $g->environment([],function()use($g):void{
        file_put_contents($g->foreign.'/tracked.txt',"foreign tracked content\n");$g->run(['-C',$g->foreign,'add','tracked.txt']);
        $g->run(['-C',$g->foreign,'-c','user.name=M6C fixture','-c','user.email=fixture@example.invalid','commit','--quiet','-m','foreign fixture']);
    });
    foreach([['GIT_DIR'=>$g->foreign.'/.git','GIT_WORK_TREE'=>$g->foreign],['GIT_INDEX_FILE'=>$g->foreign.'/.git/index'],
        ['GIT_DIR'=>$g->foreign.'/.git','GIT_WORK_TREE'=>$g->foreign,'GIT_INDEX_FILE'=>$g->foreign.'/.git/index']] as $environment){
        $scenarios++;$g->environment($environment,fn()=>sourceVerify($g));sourceCheck(true,'foreign repository/worktree/index cannot redirect verification');
    }
    $scenarios++;$line='';sourceReject(fn()=>sourceVerify($g,repo:$g->root.'/absent-repository',write:$sink));
    $record=M6CNativeWorkerError::parse(rtrim($line,"\n"));
    sourceCheck($record!==null&&$record['phase']==='source_head'&&$record['source']['exit']>0&&$record['source']['stderr_nonempty']===true,'actual installed Git nonzero failure retained safely');

    // Exact command and exact native environment forwarding through the actual caller-used helper.
    $scenarios++;$commandCheck=sourceScript($g,'native-profile',
        '$head=in_array("rev-parse",$argv,true);$base='.var_export(['--no-optional-locks','-c','core.fsmonitor=false','-c','safe.directory='.$g->repo,'-C',$g->repo],true).';'.
        '$expected=[...$base,...($head?["rev-parse","HEAD"]:["status","--porcelain","--untracked-files=all"])];'.
        'if(array_slice($argv,1)!==$expected)exit(41);'.
        'foreach(["PATH"=>"/usr/bin:/bin","HOME"=>"/nonexistent","GIT_CONFIG_NOSYSTEM"=>"1","LANG"=>"C"] as $key=>$value)if(getenv($key)!==$value)exit(42);'.
        'foreach(getenv() as $key=>$value)if((str_starts_with(strtoupper($key),"GIT_")&&strtoupper($key)!=="GIT_CONFIG_NOSYSTEM")||str_starts_with(strtoupper($key),"XDG_"))exit(43);'.
        'if($head)echo '.var_export($g->sha."\n",true).';');
    $g->environment(['HOME'=>$g->root.'/bad-home','XDG_CONFIG_HOME'=>$g->root.'/bad-xdg','GIT_TEST_PRIVATE_CANARY'=>'SECRET-ENV',
        'GIT_DIR'=>$g->foreign.'/.git','GIT_CONFIG_COUNT'=>'1','GIT_CONFIG_KEY_0'=>'core.excludesFile','GIT_CONFIG_VALUE_0'=>$g->root.'/ignore'],
        fn()=>M6CNativeGitSource::verify($g->repo,$g->sha,$commandCheck,null,'build',static fn($line)=>strlen($line)));
    sourceCheck(true,'default exact Linux environment and unchanged arguments forwarded to both real synthetic children');

    $scenarios++;$g->environment(['GIT_TEST_PRIVATE_CANARY'=>'SECRET-ENV'],function()use($g):void{
        $command=[PHP_BINARY,'-n','-r','echo getenv("GIT_TEST_PRIVATE_CANARY")===false?"absent":"inherited";'];
        $inherited=M6CNativeProcess::capture($command,5);$isolated=M6CNativeProcess::capture($command,5,65536,M6CNativeGitSource::environment());
        sourceCheck($inherited['exit']===0&&$inherited['stdout']==='inherited','ordinary caller default null environment still inherits');
        sourceCheck($isolated['exit']===0&&$isolated['stdout']==='absent','explicit replacement environment excludes parent overrides');
    });
    $secret="SECRET-PATH-CONFIG /private/example token=canary\x1b\0\r\n";
    foreach(['head','status'] as $operation){
        $scenarios++;$line='';$script=sourceScript($g,'stderr-'.$operation,
            '$head=in_array("rev-parse",$argv,true);if($head)echo '.var_export($g->sha."\n",true).';'.
            'if($head==='.($operation==='head'?'true':'false').')fwrite(STDERR,'.var_export($secret,true).');');
        sourceReject(fn()=>sourceVerify($g,git:$script,write:$sink));sourceRecord($line,'source_'.$operation,0,true,true);
        sourceCheck(!str_contains($line,'SECRET')&&!str_contains($line,'private')&&!str_contains($line,$g->root)&&!str_contains($line,"\x1b")&&!str_contains($line,"\0"),'raw synthetic stderr and path/configuration/control canaries excluded');
    }
    $scenarios++;$line='';$nonzero=sourceScript($g,'nonzero','echo '.var_export($g->sha."\n",true).';exit(17);');
    sourceReject(fn()=>sourceVerify($g,git:$nonzero,write:$sink));sourceRecord($line,'source_head',17,false,true);
    foreach(['overflow'=>'echo str_repeat("SECRET-OVERFLOW",6000);','timeout'=>'usleep(11000000);'] as $name=>$code){
        $scenarios++;$line='';$script=sourceScript($g,$name,$code);$begin=hrtime(true);
        $expected=$name==='timeout'?'native_child_timeout':'native_child_output_limit';
        sourceReject(fn()=>sourceVerify($g,git:$script,write:$sink),$expected);
        $r=sourceRecord($line,'source_head',null,null,null);sourceCheck($r['code']===$expected,'original bounded subprocess classification survives');
        if($name==='timeout')sourceCheck((hrtime(true)-$begin)/1e9<15,'unchanged ten-second timeout and termination bounds');
        foreach(M6CNativeProcess::cleanup() as $closed)sourceCheck($closed['running']===false,'owned bounded subprocess stopped');
        sourceCheck(!str_contains($line,'SECRET'),'overflow content excluded');
    }
    foreach(['false','short','throws'] as $failure){
        $scenarios++;$failedSink=static function($line)use($failure){if($failure==='throws')throw new LogicException('SECRET-SINK');return $failure==='false'?false:1;};
        sourceReject(fn()=>sourceVerify($g,str_repeat('0',40),write:$failedSink));
    }

    // Public production claim path + existing fake PDO: dependency evidence survives generic wrapping.
    $scenarios++;$f=new WebsitePlatformM6CFixture();$f->db->queries=[];$line='';
    $f->duringIdentity=static fn()=>sourceVerify($g,str_repeat('0',40),write:$sink);
    try{SiteBuildService::claimBuild([]);throw new RuntimeException('expected generic conflict');}
    catch(SiteServiceException $error){
        sourceCheck($error->classification()==='conflict'&&$error->getPrevious()===null,'actual production wrapper remains generic conflict without fabricated cause');
        sourceCheck($f->db->queries===[],'source failure precedes claim candidate query');
        $summary=M6CNativeDiagnostics::summary(['stderr'=>$line.M6CNativeWorkerError::line($error,'build','claim')]);
        sourceCheck($summary['source_error']['phase']==='source_head'&&$summary['source_error']['code']==='instrumented_source_identity'
            &&$summary['worker_error']['class']==='SiteServiceException'&&$summary['worker_error']['code']==='conflict','separate dependency observation and outer conflict both retained');
        sourceCheck(!str_contains(json_encode($summary),'SECRET')&&!str_contains(json_encode($summary),$g->root),'summary excludes path/configuration/secret canaries');
        foreach([['stdout'=>$line,'stderr'=>M6CNativeWorkerError::line($error,'build','claim')],
            ['stdout'=>M6CNativeWorkerError::line($error,'build','claim'),'stderr'=>$line],
            ['stderr'=>M6CNativeWorkerError::line($error,'build','claim').$line]] as $streams){
            $scenarios++;$both=M6CNativeDiagnostics::summary($streams);
            sourceCheck($both['source_error']===$summary['source_error']&&$both['worker_error']===$summary['worker_error'],'source and outer observations retained independent of stream/order');
        }
    }
    foreach(['coordinator','unknown','SECRET-MODE'] as $mode){
        $scenarios++;$line='';sourceReject(fn()=>sourceVerify($g,str_repeat('0',40),mode:$mode,write:$sink));
        sourceRecord($line,'source_head',0,false,false,$mode==='SECRET-MODE'?'unknown':$mode);
    }

    // Strict source-record neighboring schema/privacy cases, including modeled pre-start failure.
    $scenarios++;$start=M6CNativeWorkerError::sourceLine(new RuntimeException('native_child_start'),'build','source_head',null,$g->sha);
    $r=sourceRecord($start,'source_head',null,null,null);sourceCheck($r['code']==='native_child_start','modeled start failure uses existing bounded classification');
    $scenarios++;$unknown=M6CNativeWorkerError::sourceLine(new RuntimeException($secret),'SECRET-MODE','source_head',null,$secret);
    $unknownRecord=sourceRecord($unknown,'source_head',null,null,null,'unknown');
    sourceCheck($unknownRecord['code']==='native_child_failure'&&!str_contains($unknown,'SECRET'),'unknown exception and expected-value canaries become bounded classification');
    $base=M6CNativeWorkerError::parse(rtrim($start,"\n"));$invalid=[];
    foreach(['exit'=>'SECRET','stderr_nonempty'=>1,'matched'=>'true'] as $key=>$value){$bad=$base;$bad['source'][$key]=$value;$invalid[]=$bad;}
    $bad=$base;$bad['source']['exit']=-1;$invalid[]=$bad;
    $bad=$base;$bad['source']['raw']=$secret;$invalid[]=$bad;
    $bad=$base;$bad['source']=null;$invalid[]=$bad;
    $bad=$base;unset($bad['source']['matched']);$invalid[]=$bad;
    $bad=$base;$bad['phase']='claim';$invalid[]=$bad;
    $bad=$base;unset($bad['source']);$invalid[]=$bad;
    $bad=$base;$bad['sql']=['sqlstate'=>'SECRET','driver'=>1];$invalid[]=$bad;
    $bad=$base;$bad['extra']=$secret;$invalid[]=$bad;
    foreach($invalid as $bad){
        $scenarios++;$wire=M6CNativeWorkerError::PREFIX.json_encode($bad);
        sourceCheck(M6CNativeWorkerError::parse($wire)===null,'strict source record rejects malformed neighboring schema');
        $summary=M6CNativeDiagnostics::summary(['stderr'=>$wire."\n"]);
        sourceCheck($summary['source_error']===null&&$summary['worker_error']===null&&!str_contains(json_encode($summary),'SECRET'),'malformed source record remains bounded metadata only');
    }
    foreach(["\r","\x1b","\0",str_repeat('x',769)] as $suffix){
        $scenarios++;$wire=rtrim($start,"\n").$suffix;
        sourceCheck(M6CNativeWorkerError::parse($wire)===null,'control/overflow source wire rejected');
    }
}finally{
    M6CNativeProcess::cleanup();$roots=WebsitePlatformM6CFixture::$roots;WebsitePlatformM6CFixture::cleanup();
    foreach($roots as $root)sourceCheck(!file_exists($root),'owned artifact fixture removed');
    if($g!==null){$g->cleanup();sourceCheck(!file_exists($g->root),'owned Git repositories and synthetic scripts removed');}
    sourceCheck(getenv()==$savedEnvironment,'parent process environment keys and values restored exactly');
}
echo "Website platform M6C native Git source: $checks assertions / $scenarios scenarios passed; installed Git plus synthetic subprocesses; no native or live database execution.\n";
