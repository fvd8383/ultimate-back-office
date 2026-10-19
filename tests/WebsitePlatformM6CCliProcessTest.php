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
function m6cpBootstrap(array $result):array {
    $state=null;$count=0;
    foreach([1,2]as$stream){
        $result[$stream]=preg_replace_callback('/^M6C_BOOTSTRAP_STATE:([^\r\n]+)\r?\n/m',static function($match)use(&$state,&$count):string{
            $state=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);$count++;return '';
        },$result[$stream]);
    }
    if($count!==1||!is_array($state))throw new RuntimeException('Missing actual bootstrap observation');
    return [$result,$state];
}
function m6cpNoFixture(array $state,string $label):void {
    $classes=['WebsitePlatformM6CFixture','WebsitePlatformM6BDatabase','Database','SiteBuildService','SiteBuildRuntime'];
    m6cp($state['classes']===array_fill_keys($classes,false)&&$state['connection']===null&&$state['roots']===null,
        'Actual fixture/SQL/service classes remain unloaded: '.$label);
    m6cp($state['fixture_returned']===false&&$state['trace_ready']===false,'No initialized fixture or execution trace state: '.$label);
}
function m6cpPrerequisite(array $command,string $required,string $label):void {
    global $observed,$fixtureRoots;
    $caught=false;$state=null;$executionTrace=null;
    try{
        m6process($command,[],10000000000,['case'=>$label,'profile'=>'controlled-capability'],static function($r)use(&$state,&$executionTrace):void{
            $executionTrace=str_contains($r[1].$r[2],'M6C_EXECUTION_TRACE:');
            [$r,$state]=m6cpBootstrap($r);m6childTrace($r,'M6C_EXECUTION_TRACE');
        });
    }catch(M6CChildFailure $e){
        $caught=true;$observed[$label]=$e->outcome;$diagnostic=$e->outcome['stdout'].$e->outcome['stderr'];
        m6cp($e->reason==='prerequisite'&&$e->outcome['exit']===78&&str_contains($diagnostic,$required),'Clear actual prerequisite outcome: '.$label);
        m6cp($executionTrace===false,'No manufactured execution trace: '.$label);
        m6cp(is_string($e->evidence)&&is_file($e->evidence)&&json_decode(file_get_contents($e->evidence),true,512,JSON_THROW_ON_ERROR)===$e->outcome,
            'Sanitized actual prerequisite evidence retained: '.$label);
        m6cpNoFixture($state,$label);m6discardExpectedFailure($e);
    }
    m6cp($caught&&m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Preflight precedes owned fixture roots and request/claim/artifact work: '.$label);
}
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

// Strict-types reproduction and the actual pure normalization boundary. This is
// controlled false input, not a claim of native PHP 8.5 execution.
$originalFailure=false;
try{explode(',',false);}catch(TypeError){$originalFailure=true;}
m6cp($originalFailure,'Original unavailable-directive expression throws under strict types');
foreach([[false,[]],['',[]],[" \t\r\n",[]],[', ,,,',[]],['PDO',['pdo']],
    [' PDO, pDoStatement, , DOMDocument ,,, ',['pdo','pdostatement','domdocument']],
    [',pdo,,PDO, ',['pdo','pdo']]]as[$input,$expected]){
    m6cp(m6ExecutionDisabledClasses($input)===$expected,'Actual disabled-class normalization handles false, whitespace, case and empty entries');
}

// Controlled capabilities, not a reproduction of the operator's missing Linux PDO module.
$base=m6ExecutionBaseProfile();
$baseline=null;
m6process([PHP_BINARY,...$base['flags'],$child,'class-directive'],[],10000000000,
    ['case'=>'class-directive-baseline','profile'=>'selected-child-profile'],static function($r)use(&$baseline):void{
        [$baseline]=m6childTrace($r,'M6C_CLASS_DIRECTIVE');if($r[0]!==0)throw new RuntimeException('Class directive baseline failed');
    });
m6cp(($baseline['pdo_effective']??null)===false&&($baseline['dom_effective']??null)===false,'Class directive effects have actual capable selected-child baselines');
$observed['class-directive-baseline']=$baseline;
$directive=null;
m6process([PHP_BINARY,...$base['flags'],'-d','disable_classes=PDO,PDOStatement,DOMDocument,DOMXPath',$child,'class-directive'],[],10000000000,
    ['case'=>'class-directive-probe','profile'=>'actual-process-directive'],static function($r)use(&$directive):void{
        [$directive]=m6childTrace($r,'M6C_CLASS_DIRECTIVE');if($r[0]!==0)throw new RuntimeException('Class directive probe failed');
    });
m6cp(isset($directive['supported'],$directive['pdo_effective'],$directive['dom_effective'])
    &&is_bool($directive['supported'])&&is_bool($directive['pdo_effective'])&&is_bool($directive['dom_effective']),'Actual directive support and class effect reported');
$observed['class-directive']=$directive;$mechanisms=['controlled_false_directive'=>['PDO','DOMDocument'],'actual_directive'=>[]];
foreach(['PDO'=>['PDO,PDOStatement','pdo_effective'],'DOMDocument'=>['DOMDocument,DOMXPath','dom_effective']]as$required=>[$classes,$effective]){
    m6cpPrerequisite([PHP_BINARY,...$base['flags'],$child,'controlled-classes',$required,'no-work'],$required,'controlled-class-'.$required);
    if($directive['supported']&&$directive[$effective]){
        m6cpPrerequisite([PHP_BINARY,...$base['flags'],'-d','disable_classes='.$classes,$child,'observe-execution','no-work'],$required,'actual-disabled-class-'.$required);
        $mechanisms['actual_directive'][]=$required;
    }
}
$observed['class-capability-mechanisms']=$mechanisms;
$caps=null;$state=null;
m6process([PHP_BINARY,...$base['flags'],$child,'controlled-classes','none','--capabilities'],[],10000000000,
    ['case'=>'false-optional-directives','profile'=>'controlled-false-return'],static function($r)use(&$caps,&$state):void{
        [$r,$state]=m6cpBootstrap($r);[$caps]=m6childTrace($r,'M6C_CAPABILITIES');
        if($r[0]!==0)throw new RuntimeException('False directive capability probe failed');
    });
m6cp($caps['missing']===[],'Missing optional directive retains actual capable-class checks');
m6cpNoFixture($state,'false-optional-directives');
m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'False-directive capability report creates no fixture roots');

foreach(['base64_decode','preg_match_all','preg_replace','bin2hex','hash_equals','clearstatcache','lstat','is_link','fstat','feof',
    'fsync','json_encode','fwrite']as$required){
    m6cpPrerequisite([PHP_BINARY,...$base['flags'],'-d','disable_functions='.$required,$child,'observe-execution','no-work'],
        $required,'actual-disabled-function-'.$required);
}

// Restrict the parent of the selected profile, then inspect the actual nested
// execution child. The disabled function is never restored or replaced.
$caps=null;$state=null;$probeExit=null;
m6process([PHP_BINARY,...$base['flags'],'-d','disable_functions=base64_decode',$child,'forward-execution','--capabilities'],[],10000000000,
    ['case'=>'forwarded-base64-capabilities','profile'=>'selected-parent-restriction'],static function($r)use(&$caps,&$state,&$probeExit):void{
        [$r,$state]=m6cpBootstrap($r);$probeExit=$r[0];[$caps]=m6childTrace($r,'M6C_CAPABILITIES');
        if($r[0]!==78)throw new RuntimeException('Forwarded restriction was not reported');
    });
m6cp($probeExit===78&&in_array('base64_decode',$caps['missing'],true),'Selected parent restriction reaches actual child capability probe');
m6cpNoFixture($state,'forwarded-base64-capabilities');
m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Forwarded capability probe creates no fixture roots');
$observed['forwarded-base64-capabilities']=['exit'=>$probeExit,'missing'=>$caps['missing']];
m6cpPrerequisite([PHP_BINARY,...$base['flags'],'-d','disable_functions=base64_decode',$child,'forward-execution','no-work'],
    'base64_decode','forwarded-base64-prerequisite');

// Exact counted source injections are process-only. Observe the actual tracked
// root before shutdown cleanup, then prove its absence after the real child exits.
foreach(['constructor-failure'=>false,'trace-failure'=>true]as$case=>$initialized){
    $caught=false;$state=null;$executionTrace=null;
    try{
        m6process([PHP_BINARY,...$base['flags'],$child,$case],[],10000000000,['case'=>$case,'profile'=>'controlled-fixture-fault'],
            static function($r)use(&$state,&$executionTrace):void{
                $executionTrace=str_contains($r[1].$r[2],'M6C_EXECUTION_TRACE:');
                [$r,$state]=m6cpBootstrap($r);m6childTrace($r,'M6C_EXECUTION_TRACE');
            });
    }catch(M6CChildFailure $e){
        $caught=true;$observed[$case]=$e->outcome;
        m6cp($e->reason==='trace_missing'&&$e->outcome['exit']===255
            &&str_contains($e->outcome['stderr'],'m6cMissingFixtureFunction'),'Primary constructor/trace fatal and actual exit retained: '.$case);
        m6cp($executionTrace===false,'Partial initialization or failed trace does not invent execution output: '.$case);
        m6cp($state['fixture_returned']===$initialized&&$state['trace_ready']===$initialized,'Actual fixture return/readiness observed: '.$case);
        m6cp($state['classes']['WebsitePlatformM6CFixture']&&$state['connection']==='WebsitePlatformM6BDatabase'
            &&is_array($state['roots'])&&count($state['roots'])===1,'Actual constructor reached fake database and tracked one root: '.$case);
        foreach($state['roots']as$root){
            m6cp($root['exists']===true&&realpath(dirname($root['path']))===realpath(sys_get_temp_dir())
                &&preg_match('/^ubo-m6c-[a-f0-9]{24}$/D',basename($root['path']))===1,'Observer saw the child-owned root before cleanup: '.$case);
            m6cp(!file_exists($root['path'])&&!is_link($root['path']),'Early shutdown cleanup removes root even without an instance/trace: '.$case);
        }
        m6cp(is_string($e->evidence)&&is_file($e->evidence)&&json_decode(file_get_contents($e->evidence),true,512,JSON_THROW_ON_ERROR)===$e->outcome,
            'Constructor/trace failure evidence retained before capture cleanup: '.$case);
        m6discardExpectedFailure($e);
    }
    m6cp($caught&&m6cpDirectories('ubo-m6c-')===$fixtureRoots,'No owned partial-fixture roots or captures remain: '.$case);
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
