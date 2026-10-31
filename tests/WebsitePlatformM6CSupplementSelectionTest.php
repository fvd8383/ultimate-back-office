<?php
declare(strict_types=1);
// Executable launcher selection and real private report transport. Native metadata is modeled.
require_once __DIR__.'/support/WebsitePlatformM6CSupplementChannel.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeTransport.php';
$checks=0;$directories=[];$sha=str_repeat('a',40);$token=str_repeat('b',32);
function sx(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function sxDeny(callable $f,string $why):void{try{$f();}catch(Throwable){sx(true,$why);return;}throw new RuntimeException('Missing rejection: '.$why);}
function sxDirectory():string
{global $directories;$p=sys_get_temp_dir().'/ubo-m6c-supplement-'.bin2hex(random_bytes(12));if(!mkdir($p,0700))throw new RuntimeException('test_directory');$directories[]=$p;return $p;}
function sxFile(string $path,string $bytes):void
{$h=fopen($path,'xb');if(!$h||!chmod($path,0600)||fwrite($h,$bytes)!==strlen($bytes)||!fclose($h))throw new RuntimeException('test_file');}
function sxReceive(string $bytes,string $dir):void
{$h=fopen('php://memory','r+');fwrite($h,$bytes);rewind($h);try{M6CNativeTransport::receive($h,$dir,'normal');}finally{fclose($h);}}
try{
    $bash=getenv('M6C_NATIVE_TEST_BASH')?:(PHP_OS_FAMILY==='Windows'?'C:/Git/bin/bash.exe':'/bin/bash');sx(is_file($bash),'Bash prerequisite');
    $fixture=__DIR__.'/support/WebsitePlatformM6CSupplementLauncherFixture.sh';
    $arguments=['--check-only','--expected-sha',$sha,'--mysql-digest','sha256:'.str_repeat('c',64),'--runtime-image','sha256:'.str_repeat('d',64),'--php-base-digest','sha256:'.str_repeat('e',64),'--php',PHP_BINARY];
    foreach([[] ,['--profile','core'],['--profile','supplement-v1'],['--profile','unknown'],['--profile','core','--profile','supplement-v1']] as $i=>$selection){
        $r=M6CNativeProcess::capture([$bash,$fixture,'select',...$arguments,...$selection],10);
        sx($r['exit']===($i<3?0:2),'actual profile parsing exit '.$i.': '.json_encode($r));sx(!str_contains($r['stdout'],'UNEXPECTED-RUN'),'check-only never dispatches execution');
        sx($i<3?str_contains($r['stdout'],'SELECTED='.($i===2?M6CSupplementContract::VERSION:M6CNativeContract::VERSION)):!str_contains($r['stdout'],'SELECTED='),'explicit selection; rejection before admission');
    }
    $r=M6CNativeProcess::capture([$bash,$fixture,'select','--help'],10);sx($r['exit']===0&&!str_contains($r['stdout'],'SELECTED='),'help does not admit');
    foreach(['WebsitePlatformM6CSupplementWorker.php','WebsitePlatformM6CSupplementProxy.php'] as $entry){
        $r=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/support/'.$entry,'--help'],5);sx($r['exit']===0&&$r['stderr']==='','native helper help before bootstrap');
        $r=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/support/'.$entry],5);sx($r['exit']===1&&str_contains($r['stderr'],'M6C SUPPLEMENT ERROR '),'native helper refuses ordinary discovery before DB/socket');
    }
    $report=M6CNativeReport::initial($sha,$token,M6CSupplementContract::VERSION);
    $report['status']='PASS';$report['assertions']=$report['completed_assertions']=count(M6CSupplementContract::CASES);
    foreach($report['cases'] as &$c)$c=['status'=>'PASS','lane'=>'instrumented','assertions'=>1,'observed'=>true];unset($c);
    $bytes=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);$dir=sxDirectory();sxFile($dir.'/source.json',$bytes);
    // Actual file read with explicitly modeled root ownership/native location metadata.
    $stat=['mode'=>0100644,'uid'=>0,'gid'=>0,'nlink'=>1,'dev'=>7,'ino'=>9,'size'=>strlen($bytes)];
    $io=static function(string $op,...$a)use($stat,$dir){return match($op){
        'clearstatcache'=>null,'realpath'=>$a[0],
        'lstat'=>$a[0]==='/run/m6c/report.json'?$stat:array_replace($stat,['mode'=>0040755]),
        'fstat'=>$stat,'fopen'=>fopen($dir.'/source.json','rb'),default=>$op(...$a)};};
    sx(M6CNativeTransport::read($sha,$token,$io,M6CSupplementContract::VERSION)===$bytes,'selected bounded reader exact bytes');
    sxDeny(fn()=>M6CNativeTransport::read($sha,$token,$io),'core reader denies supplement');
    foreach(['good','wrong-source','wrong-run','wrong-profile','duplicate','partial'] as $kind){
        $dir=sxDirectory();$data=$bytes;
        if($kind==='duplicate')$data=str_replace('"native": true,','"native": true, "native": true,',$data);
        if($kind==='partial'){$r=$report;$r['cases'][M6CSupplementContract::CASES[0]]['status']='UNREACHED';$data=json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);}
        sxReceive($data,$dir);
        sxFile($dir.'/child.stderr.txt','M6C REPORT PUBLISHED source='.$sha.' run='.$token.' bytes='.strlen($data).' sha256='.hash('sha256',$data)."\n");
        $verify=fn()=>M6CNativeTransport::verify($dir,'normal',$kind==='wrong-source'?str_repeat('f',40):$sha,$kind==='wrong-run'?str_repeat('f',32):$token,$kind==='wrong-profile'?M6CNativeContract::VERSION:M6CSupplementContract::VERSION);
        if(in_array($kind,['good','partial'],true)){
            $receipt=$verify();sx($receipt['acknowledgement']==='MATCH'&&file_get_contents($dir.'/native.json')===$data,'acknowledged exact transport');
            $r=M6CNativeProcess::capture([$bash,$fixture,'collect',$dir,PHP_BINARY,M6CSupplementContract::VERSION,$sha,$token],10);
            sx($r['exit']===($kind==='good'?0:2)&&!str_contains($r['stdout'],'UNEXPECTED-CORE-GUARD'),'selected launcher gate rejects partial PASS after successful transport');
        }else{
            sxDeny($verify,'invalid transport identity/duplicate');sx(!file_exists($dir.'/native.json')&&is_file($dir.'/native-normal.pending'),'failure retains pending evidence without promotion');
        }
    }
    foreach(['good','wrong-profile','partial'] as $kind){
        $dir=sxDirectory();$r=$report;
        if($kind==='wrong-profile')$r['version']=M6CNativeContract::VERSION;
        if($kind==='partial')$r['cases'][M6CSupplementContract::CASES[0]]['status']='UNREACHED';
        sxFile($dir.'/source.json',json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        $r=M6CNativeProcess::capture([$bash,$fixture,'capture',$dir,PHP_BINARY,M6CSupplementContract::VERSION,$sha,$token],15);
        sx($r['exit']===($kind==='good'?0:2),'actual selected launcher capture path '.$kind.': '.json_encode($r));
        sx(is_file($dir.'/native.json')===($kind!=='wrong-profile'),'transport identity vs final acceptance remain distinct');
    }
    echo "M6C supplement selection/transport: $checks assertions passed; fake launcher admission, modeled native ownership, real private files/processes; NO native launch.\n";
}finally{
    foreach(M6CNativeProcess::cleanup() as $c)sx(!$c['running'],'owned child reaped');
    foreach($directories as $dir){foreach(scandir($dir) as $file)if($file!=='.'&&$file!=='..')unlink($dir.'/'.$file);rmdir($dir);}
}
