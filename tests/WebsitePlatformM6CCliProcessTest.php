<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CCliProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CExecutionProfile.php';
// Give directory-boundary assertions their own process-only temp root so other
// concurrently running suites cannot affect cleanup observations.
if(($argv[1]??'')!=='--owned-temp'){
    $temp=m6privateDirectory('ubo-m6c-cli-regression-');$profile=m6ExecutionBaseProfile();
    try{
        [$exit,$out,$err]=m6process([PHP_BINARY,...$profile['flags'],'-d','sys_temp_dir='.$temp,__FILE__,'--owned-temp'],[],30000000000,
            ['case'=>'process-regressions','profile'=>$profile['label']],static function($r):void{if($r[0]!==0)throw new RuntimeException('Process regression failed');});
        echo $out;fwrite(STDERR,$err);
    }finally{if(scandir($temp)===['.','..'])rmdir($temp);}
    exit($exit);
}
$assertions=0;$observed=[];$child=__DIR__.'/support/WebsitePlatformM6CCliProcessChild.php';
function m6cp(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function m6cpDirectories(string $prefix):array{$dirs=glob(sys_get_temp_dir().'/'.$prefix.'*')?:[];sort($dirs);return $dirs;}
$captures=m6cpDirectories('ubo-m6c-cli-capture-');$failures=m6cpDirectories('ubo-m6c-cli-failure-');
$fixtureRoots=m6cpDirectories('ubo-m6c-');
$flags=['-n','-d','display_errors=stderr','-d','log_errors=0','-d','html_errors=0','-d','memory_limit=64M','-d','max_execution_time=10','-d','zend.exception_ignore_args=1'];
foreach(['startup'=>'trace_missing','missing'=>'trace_missing','malformed'=>'trace_malformed','nonzero'=>'validation','timeout'=>'timeout','overflow'=>'output_limit']as$case=>$reason){
    $start=hrtime(true);$caught=false;
    try{
        m6process([PHP_BINARY,...$flags,$child,$case,'PRIVATE-ARG-CANARY'],[], $case==='timeout'?1000000000:10000000000,
            ['case'=>$case,'profile'=>'controlled-no-ini'],static function($r):void{
                m6childTrace($r,'M6C_EXECUTION_TRACE');if($r[0]!==0)throw new RuntimeException('Unexpected child exit');
            });
    }catch(M6CChildFailure $e){
        $caught=true;$r=$e->outcome;$observed[$case]=$r;
        m6cp($e->reason===$reason,'Distinct failure reason: '.$case);
        m6cp($r['running']===false&&hrtime(true)-$start<5000000000,'Bounded termination: '.$case);
        m6cp(is_string($e->evidence)&&is_file($e->evidence),'Failure evidence retained before cleanup: '.$case);
        $saved=file_get_contents($e->evidence);$decoded=json_decode($saved,true,512,JSON_THROW_ON_ERROR);
        m6cp($decoded===$r&&strlen($saved)<12000,'Actual bounded outcome retained: '.$case);
        m6cp(!str_contains($saved.$e->getMessage(),'PRIVATE')&&!str_contains($saved,'command')&&!str_contains($saved,'environment'),'No raw payload/arguments/environment or spoofed engine identifiers: '.$case);
        m6cp(realpath(dirname(dirname($e->evidence)))===realpath(sys_get_temp_dir())&&!str_starts_with(realpath($e->evidence),realpath(dirname(__DIR__)).DIRECTORY_SEPARATOR),'Evidence outside checkout: '.$case);
        if(PHP_OS_FAMILY!=='Windows')m6cp((fileperms($e->evidence)&0777)===0600&&(fileperms(dirname($e->evidence))&0777)===0700,'Private POSIX evidence modes');
        if($case==='startup')m6cp($r['exit']===255&&str_contains($r['stderr'],'M6CMissingFixtureDependency'),'Real startup fatal retained');
        if($case==='malformed')m6cp($r['exit']===255&&str_contains($r['stderr'],'m6cMissingFixtureFunction'),'Malformed trace preserves original fatal');
        if($case==='missing')m6cp($r['exit']===23&&str_contains($r['stderr'],'controlled_failure'),'Missing trace preserves original nonzero/error');
        if($case==='nonzero')m6cp($r['exit']===31,'A parseable trace never suppresses nonzero exit');
        if($case==='overflow')m6cp($r['stdout_bytes']+$r['stderr_bytes']>65536&&strlen($r['stdout'])<=4096&&strlen($r['stderr'])<=4096,'Aggregate cap and bounded retained output');
        if(in_array($case,['timeout','overflow'],true))m6cp($r['termination_requested']&&($r['exit']!==null||$r['signal']!==null)&&str_contains($r['stderr'],'controlled_'.$case),'Actual termination outcome and diagnostics');
        m6cp(m6cpDirectories('ubo-m6c-cli-capture-')===$captures,'Raw capture cleanup after retention');
        $path=$e->evidence;m6discardExpectedFailure($e);m6cp(!file_exists($path)&&!is_dir(dirname($path)),'Only consumed negative-case evidence removed');
    }
    m6cp($caught,'Expected child fault was not skipped: '.$case);
}
$caught=false;
try{
    m6process([PHP_BINARY,...$flags,$child,'startup'],[],10000000000,['case'=>'evidence-writer-failure','profile'=>'controlled-no-ini'],
        static function($r):void{m6childTrace($r,'M6C_EXECUTION_TRACE');},static function($path,$json):void{file_put_contents($path,'partial');throw new RuntimeException('PRIVATE-WRITER-CANARY');});
}catch(M6CChildFailure $e){
    $caught=true;m6cp($e->reason==='trace_missing'&&$e->outcome['exit']===255&&$e->evidence===null,'Evidence failure preserves primary child failure');
    m6cp(str_contains($e->getMessage(),'M6CMissingFixtureDependency')&&!str_contains($e->getMessage(),'PRIVATE-WRITER'),'Useful sanitized summary survives evidence failure');
}
m6cp($caught&&m6cpDirectories('ubo-m6c-cli-failure-')===$failures,'Partial failed evidence cleaned');
[$exit,$out,$err]=m6process([PHP_BINARY,...$flags,$child,'success']);
m6cp([$exit,$out,$err]===[0,'ok',''],'Successful output/exit unchanged');
m6cp(m6cpDirectories('ubo-m6c-cli-capture-')===$captures&&m6cpDirectories('ubo-m6c-cli-failure-')===$failures,'Successful and expected-failure captures do not accumulate');

// Controlled capabilities, not a reproduction of the operator's missing Linux PDO module.
$base=m6ExecutionBaseProfile();
foreach(['disable_classes=PDO,PDOStatement'=>'PDO','disable_classes=DOMDocument,DOMXPath'=>'DOMDocument','disable_functions=fsync'=>'fsync',
    'disable_functions=json_encode'=>'json_encode','disable_functions=fwrite'=>'fwrite']as$directive=>$required){
    $caught=false;
    try{
        m6process([PHP_BINARY,...$base['flags'],'-d',$directive,__DIR__.'/support/WebsitePlatformM6CExecutionChild.php','no-work'],[],10000000000,
            ['case'=>'missing-'.$required,'profile'=>'controlled-capability'],static function($r):void{m6childTrace($r,'M6C_EXECUTION_TRACE');});
    }catch(M6CChildFailure $e){
        $caught=true;$observed['missing-'.$required]=$e->outcome;
        $diagnostic=$e->outcome['stdout'].$e->outcome['stderr'];
        m6cp($e->reason==='prerequisite'&&$e->outcome['exit']===78&&str_contains($diagnostic,$required),'Clear capability prerequisite failure');
        m6cp(!str_contains($diagnostic,'M6C_EXECUTION_TRACE')&&($e->outcome['stdout']===''||str_starts_with($e->outcome['stdout'],'M6C_PREREQUISITE:')),'No manufactured execution trace');
        m6discardExpectedFailure($e);
    }
    m6cp($caught&&m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Preflight fails before fixture root/construction');
}

// An owned, disposable process-only INI verifies intentional parent -c and -d.
$profileDir=m6privateDirectory('ubo-m6c-cli-profile-');$ini=$profileDir.'/child.ini';
try{
    file_put_contents($ini,"memory_limit=48M\nauto_prepend_file=unavailable-test-prepend.php\n");chmod($ini,0600);
    $profileTrace=null;
    m6process([PHP_BINARY,'-c',$ini,'-d','sys_temp_dir='.sys_get_temp_dir(),'-d','auto_prepend_file=','-d','memory_limit=64M','-d','disable_functions=fsync',$child,'profile'],[],10000000000,
        ['case'=>'parent-c-d','profile'=>'temporary-parent-ini'],static function($r)use(&$profileTrace):void{[$profileTrace]=m6childTrace($r,'M6C_PROFILE');if($r[0]!==0)throw new RuntimeException('Profile failed');});
    m6cp($profileTrace['ini_loaded']&&$profileTrace['memory_limit']==='67108864'&&$profileTrace['max_execution_time']==='10','Parent INI and bounded memory/time forwarded');
    m6cp(!$profileTrace['fsync']&&$profileTrace['auto_prepend_file']===''&&$profileTrace['binary']===PHP_BINARY,'Relevant parent directive, no prepend, same executable');
}finally{unlink($ini);rmdir($profileDir);}
m6cp(!in_array('-n',$base['flags'],true),'Execution profile does not force no INI');
m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'All owned fault/profile captures cleaned; unrelated evidence unchanged');
echo 'Controlled process outcomes: '.json_encode($observed,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
echo "Website platform M6C CLI process: $assertions assertions passed; controlled faults/capabilities only; no live database or host configuration.\n";
