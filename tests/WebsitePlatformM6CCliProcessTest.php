<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CCliProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CExecutionProfile.php';
// Give directory-boundary assertions their own process-only temp root so other
// concurrently running suites cannot affect cleanup observations.
if(($argv[1]??'')!=='--owned-temp'){
    $temp=m6privateDirectory('ubo-m6c-cli-regression-');$profile=m6ExecutionBaseProfile();$total=0;$start=hrtime(true);
    try{
        // Each phase keeps the same 10-second PHP profile. The capable parent
        // gives all phases one shared 30-second wall-clock budget.
        foreach(['process','classes','cleanup','capabilities','fixtures']as$phase){
            $remaining=30000000000-(hrtime(true)-$start);
            if($remaining<=0)throw new RuntimeException('Process regression aggregate deadline');
            $summary=null;
            [$exit,$out,$err]=m6process([PHP_BINARY,...$profile['flags'],'-d','sys_temp_dir='.$temp,__FILE__,'--owned-temp',$phase],[],$remaining,
                ['case'=>'process-regressions-'.$phase,'profile'=>$profile['label']],static function($r)use(&$summary,$phase):void{
                    [$summary,$cleanErr]=m6childTrace($r,'M6C_PHASE_RESULT');
                    if($r[0]!==0||$cleanErr!==''||($summary['phase']??null)!==$phase||!is_int($summary['assertions']??null)||$summary['assertions']<1)throw new RuntimeException('Process regression phase failed');
                });
            if(hrtime(true)-$start>=30000000000)throw new RuntimeException('Process regression aggregate deadline');
            $total+=$summary['assertions'];echo $out;
        }
        if(scandir($temp)!==['.','..']||!rmdir($temp))throw new RuntimeException('Process regression parent cleanup failed');
        if(hrtime(true)-$start>=30000000000)throw new RuntimeException('Process regression aggregate deadline');
    }finally{if(is_dir($temp)&&@scandir($temp)===['.','..'])@rmdir($temp);}
    echo "Website platform M6C CLI process: $total assertions passed; controlled faults/capabilities only; no live database or host configuration.\n";
    exit(0);
}
$phase=$argv[2]??'all';
if(!in_array($phase,['process','classes','cleanup','capabilities','fixtures','all'],true))throw new RuntimeException('Unknown regression phase');
$runProcess=$phase==='process'||$phase==='all';
$runClasses=$phase==='classes'||$phase==='all';
$runCleanup=$phase==='cleanup'||$phase==='all';
$runCapabilities=$phase==='capabilities'||$phase==='all';$runFixtures=$phase==='fixtures'||$phase==='all';
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
function m6cpPrerequisite(array $command,string|array $required,string $label,bool $observe=true):void {
    global $observed,$fixtureRoots;
    $caught=false;$state=null;$executionTrace=null;
    try{
        m6process($command,[],10000000000,['case'=>$label,'profile'=>$observe?'controlled-capability':'direct-child-no-observer'],static function($r)use(&$state,&$executionTrace,$observe):void{
            $executionTrace=str_contains($r[1].$r[2],'M6C_EXECUTION_TRACE:');
            if($observe)[$r,$state]=m6cpBootstrap($r);m6childTrace($r,'M6C_EXECUTION_TRACE');
        });
    }catch(M6CChildFailure $e){
        $caught=true;$observed[$label]=$e->outcome;$diagnostic=$e->outcome['stdout'].$e->outcome['stderr'];
        $identified=true;foreach((array)$required as$name)$identified=$identified&&str_contains($diagnostic,$name);
        m6cp($e->reason==='prerequisite'&&$e->outcome['exit']===78&&$identified,'Clear actual prerequisite outcome naming every restricted function: '.$label);
        m6cp($executionTrace===false,'No manufactured execution trace: '.$label);
        m6cp(is_string($e->evidence)&&is_file($e->evidence)&&json_decode(file_get_contents($e->evidence),true,512,JSON_THROW_ON_ERROR)===$e->outcome,
            'Sanitized actual prerequisite evidence retained: '.$label);
        if($observe)m6cpNoFixture($state,$label);
        else $observed[$label]['observation_limit']='Actual exit/diagnostic/trace/filesystem only; disabled observer primitive prevents a class-state report.';
        m6discardExpectedFailure($e);
    }
    m6cp($caught&&m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Prerequisite rejection leaves no new fixture roots or artifact output: '.$label);
}
$withoutSentinels=m6cpDirectories('ubo-m6c-');
$sentinelRoot=m6privateDirectory('ubo-m6c-');$sentinelFile=$sentinelRoot.'/unrelated-owned-sentinel';
file_put_contents($sentinelFile,"M6C unrelated owned sentinel\n");chmod($sentinelFile,0600);$sentinelHash=hash_file('sha256',$sentinelFile);
$sentinelFailure=null;
try{
    m6process([PHP_BINARY,'-n',$child,'missing'],[],10000000000,['case'=>'retained-sentinel','profile'=>'controlled-no-ini'],
        static function($r):void{m6childTrace($r,'M6C_EXECUTION_TRACE');});
}catch(M6CChildFailure $e){$sentinelFailure=$e;}
m6cp($sentinelFailure instanceof M6CChildFailure&&$sentinelFailure->outcome['exit']===23&&is_string($sentinelFailure->evidence),
    'A separate actual failed-child evidence record is retained during the regressions');
$sentinelEvidenceHash=hash_file('sha256',$sentinelFailure->evidence);
$captures=m6cpDirectories('ubo-m6c-cli-capture-');$failures=m6cpDirectories('ubo-m6c-cli-failure-');
$fixtureRoots=m6cpDirectories('ubo-m6c-');
$flags=['-n','-d','display_errors=stderr','-d','log_errors=0','-d','html_errors=0','-d','memory_limit=64M','-d','max_execution_time=10','-d','zend.exception_ignore_args=1'];
$base=m6ExecutionBaseProfile();
// Source-audited initialization inventory, independent of the prerequisite list.
// Read only these fixed call sites as text; do not discover/include application code.
$initializationSites=[
    'str_contains'=>['tests/support/WebsitePlatformM3ServiceDatabase.php','str_contains($n,'],
    'usort'=>['tests/support/WebsitePlatformM3ServiceDatabase.php','usort($rows, fn ($a, $b)'],
    'str_replace'=>['private/classes/ComponentRegistry.php','ucwords(str_replace('],
    'array_shift'=>['tests/support/WebsitePlatformM3ServiceDatabase.php','array_shift($this->rows)'],
    'array_unique'=>['tests/support/WebsitePlatformM6BDatabase.php','array_unique($matches[1])'],
    'array_slice'=>['tests/support/WebsitePlatformM6BDatabase.php','array_slice($rows, 0, (int) $limit[1])'],
    'ucwords'=>['private/classes/ComponentRegistry.php','ucwords(str_replace('],
    'array_fill_keys'=>['private/classes/ComponentRegistry.php',"array_fill_keys(['home', 'service', 'about', 'contact']"],
    'array_count_values'=>['private/classes/SiteAuthoringCatalog.php','array_count_values(array_column($sections,'],
    'array_column'=>['private/classes/SiteAuthorizationPolicy.php','array_column($rows,'],
    'array_intersect'=>['private/classes/SiteAuthorizationPolicy.php','array_intersect(self::INTERNAL_ADMIN_ROLES, $roles)'],
    'is_int'=>['private/classes/ComponentSchemaValidator.php','is_int($value)'],
    'is_bool'=>['private/classes/ComponentSchemaValidator.php','is_bool($value)'],
    'strtoupper'=>['private/classes/ThemeRegistry.php','strtoupper($primary)'],
    'strcmp'=>['private/classes/SiteBuildContract.php','strcmp($last, $key)'],
    'is_scalar'=>['private/classes/SitePublicRenderContext.php','is_scalar($v)'],
    'str_ends_with'=>['private/classes/SiteArtifactFiles.php','str_ends_with($part,'],
    'chr'=>['private/classes/SiteServiceSupport.php','chr((ord($bytes[6])'],
    'ord'=>['private/classes/SiteServiceSupport.php','ord($bytes[6])'],
    'implode'=>['private/classes/SiteBuildStore.php',"implode(', ', \$columns)"],
    'sort'=>['private/classes/SiteBuildService.php','sort($ids, SORT_NUMERIC)'],
    'method_exists'=>['private/classes/SiteRevisionManager.php','method_exists($connection,'],
    'array_key_first'=>['private/classes/SiteCompositionEditor.php','array_key_first($definition['],
    'array_push'=>['private/classes/SiteCompositionEditor.php','array_push($selections, ...$page['],
];
if($runProcess){
foreach($initializationSites as$name=>[$path,$call]){
    m6cp(str_contains(file_get_contents(dirname(__DIR__).'/'.$path),$call),'Audited initialization call site still exists: '.$name);
    $diagnostic='M6C_PREREQUISITE: missing '.$name.'.';
    m6cp(m6diagnostic($diagnostic)===$diagnostic,'Audited function has an exact safe diagnostic identity: '.$name);
}
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
}

// Controlled capabilities, not a reproduction of the operator's missing Linux PDO module.
if($runClasses){
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
$caps=null;$state=null;$probeExit=null;
m6process([PHP_BINARY,...$base['flags'],'-d','disable_functions=ctype_digit',$child,'controlled-classes','PDO','--capabilities'],[],10000000000,
    ['case'=>'combined-function-class-capabilities','profile'=>'actual-function-controlled-class'],static function($r)use(&$caps,&$state,&$probeExit):void{
        [$r,$state]=m6cpBootstrap($r);$probeExit=$r[0];[$caps]=m6childTrace($r,'M6C_CAPABILITIES');
        if($r[0]!==78)throw new RuntimeException('Combined function/class restrictions were not reported');
    });
m6cp($probeExit===78&&in_array('ctype_digit',$caps['missing'],true)&&in_array('PDO',$caps['missing'],true)
    &&in_array('PDOStatement',$caps['missing'],true),'Missing function preserves the complete class-capability report for module carry');
m6cpNoFixture($state,'combined-function-class-capabilities');
m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Combined function/class capability report creates no fixture roots');
$observed['combined-function-class-capabilities']=['exit'=>$probeExit,'missing'=>$caps['missing']];
}

if($runCapabilities){
foreach(['base64_decode','fsync','json_encode','fwrite']as$required){
    m6cpPrerequisite([PHP_BINARY,...$base['flags'],'-d','disable_functions='.$required,$child,'observe-execution','no-work'],
        $required,'actual-disabled-function-'.$required);
}
}

// The capable controller keeps capture/cleanup functions available. Only each
// real execution child is restricted, including all eight cleanup functions.
$cleanupFunctions=['basename','dirname','sys_get_temp_dir','realpath','preg_match','chmod','unlink','rmdir'];
$traceFunctions=['array_intersect_key','array_flip','substr','strlen','count'];
$initializationFunctions=['array_replace','str_repeat','ucfirst','rtrim','str_starts_with','extension_loaded','is_array','ksort',
    'array_key_exists','array_diff','array_keys','is_string','min','preg_match_all','preg_replace','bin2hex','hash_equals','clearstatcache','lstat','is_link','fstat','feof'];
$bootstrapFunctions=['ini_get','explode','trim','strtolower','array_map','array_filter','array_values','class_exists','in_array','defined','php_ini_loaded_file'];
$executionChild=__DIR__.'/support/WebsitePlatformM6CExecutionChild.php';
if($runCleanup){
foreach([...$cleanupFunctions,'register_shutdown_function']as$required){
    // The observer itself needs shutdown registration. Run the actual child
    // directly for that case and record the narrower observation honestly.
    $observe=$required!=='register_shutdown_function';
    $command=[PHP_BINARY,...$base['flags'],'-d','disable_functions='.$required];
    $command=array_merge($command,$observe?[$child,'observe-execution','no-work']:[$executionChild,'no-work']);
    m6cpPrerequisite($command,$required,'actual-required-function-'.$required,$observe);
}
}

// function_exists is the single inspection primitive the preflight itself needs.
// Its real absence fails before fixture load; do not relabel that fatal as exit78.
if($runCapabilities){
$caught=false;$executionTrace=null;
try{
    m6process([PHP_BINARY,...$base['flags'],'-d','disable_functions=function_exists',$executionChild,'no-work'],[],10000000000,
        ['case'=>'missing-preflight-inspection','profile'=>'direct-child-no-observer'],static function($r)use(&$executionTrace):void{
            $executionTrace=str_contains($r[1].$r[2],'M6C_EXECUTION_TRACE:');m6childTrace($r,'M6C_EXECUTION_TRACE');
        });
}catch(M6CChildFailure $e){
    $caught=true;$observed['missing-preflight-inspection']=$e->outcome;
    m6cp($e->reason==='trace_missing'&&$e->outcome['exit']===255&&str_contains($e->outcome['stderr'],'function_exists'),
        'Unavoidable inspection-primitive fatal retains actual exit and useful identity');
    m6cp($executionTrace===false,'Inspection-primitive fatal emits no execution trace');
    m6discardExpectedFailure($e);
}
m6cp($caught&&m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Inspection-primitive failure creates no fixture roots');

// Batch these adjacent prerequisites to keep the controller's original 10-second
// PHP limit and 30-second parent bound. Every member is truly disabled together;
// assert the complete reported set, without claiming individual process cases.
foreach([[$initializationFunctions,'batched-initialization',true],[array_keys($initializationSites),'batched-supporting-initialization',true],[$traceFunctions,'batched-trace',true],
    [$bootstrapFunctions,'batched-bootstrap',false]]as[$required,$label,$observe]){
    $caps=null;$state=null;
    $command=[PHP_BINARY,...$base['flags'],'-d','disable_functions='.implode(',',$required)];
    $probe=array_merge($command,$observe?[$child,'observe-execution','--capabilities']:[$executionChild,'--capabilities']);
    m6process($probe,[],10000000000,['case'=>$label.'-capabilities','profile'=>'actual-batched-restrictions'],static function($r)use(&$caps,&$state,$observe):void{
            if($observe)[$r,$state]=m6cpBootstrap($r);[$caps]=m6childTrace($r,'M6C_CAPABILITIES');
            if($r[0]!==78)throw new RuntimeException('Batched missing functions not rejected');
        });
    $reported=$caps['missing'];$expected=$required;sort($reported,SORT_STRING);sort($expected,SORT_STRING);
    m6cp($reported===$expected,'Capability report identifies the entire actual disabled-function set: '.$label);
    if($observe)m6cpNoFixture($state,$label);
    else m6cp($caps['memory_limit']===null&&$caps['max_execution_time']===null&&$caps['ini_loaded']===null,
        'Batched bootstrap report preserves unavailable metadata as null without invoking disabled functions');
    m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Batched capability report leaves no new roots');
    $observed[$label.'-capabilities']=['exit'=>78,'missing'=>$caps['missing'],'class_state_observed'=>$observe];
    m6cpPrerequisite(array_merge($command,$observe?[$child,'observe-execution','no-work']:[$executionChild,'no-work']),$required,$label.'-prerequisite',$observe);
}
}

// Restrict the parent of the selected profile, then inspect the actual nested
// execution child. The disabled function is never restored or replaced.
// Keep each reviewer-named restriction independent. Run their selected-profile
// probes in the process phase, leaving the committed five-phase/deadline structure.
if($runFixtures||$runProcess){
foreach([...($runProcess?['str_contains','usort','str_replace']:[]),...($runFixtures?['base64_decode','basename']:[])]as$required){
    $caps=null;$state=null;$probeExit=null;
    m6process([PHP_BINARY,...$base['flags'],'-d','disable_functions='.$required,$child,'forward-execution','--capabilities'],[],10000000000,
        ['case'=>'forwarded-'.$required.'-capabilities','profile'=>'selected-parent-restriction'],static function($r)use(&$caps,&$state,&$probeExit):void{
            [$r,$state]=m6cpBootstrap($r);$probeExit=$r[0];[$caps]=m6childTrace($r,'M6C_CAPABILITIES');
            if($r[0]!==78)throw new RuntimeException('Forwarded restriction was not reported');
        });
    m6cp($probeExit===78&&$caps['missing']===[$required],'Selected parent restriction reports exactly the individually disabled function: '.$required);
    m6cpNoFixture($state,'forwarded-'.$required.'-capabilities');
    m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'Forwarded capability probe creates no fixture roots');
    $observed['forwarded-'.$required.'-capabilities']=['exit'=>$probeExit,'missing'=>$caps['missing']];
    m6cpPrerequisite([PHP_BINARY,...$base['flags'],'-d','disable_functions='.$required,$child,'forward-execution','no-work'],
        $required,'forwarded-'.$required.'-prerequisite');
}
}

// Exact counted source injections are process-only. Observe the actual tracked
// root before shutdown cleanup, then prove its absence after the real child exits.
if($runFixtures){
foreach(['constructor-failure'=>false,'trace-failure'=>true,'trace-then-fatal'=>true]as$case=>$initialized){
    $caught=false;$state=null;$executionTrace=null;$emittedTrace=null;
    try{
        m6process([PHP_BINARY,...$base['flags'],$child,$case],[],10000000000,['case'=>$case,'profile'=>'controlled-fixture-fault'],
            static function($r)use(&$state,&$executionTrace,&$emittedTrace,$case):void{
                $executionTrace=str_contains($r[1].$r[2],'M6C_EXECUTION_TRACE:');
                [$r,$state]=m6cpBootstrap($r);
                if($case==='trace-then-fatal'){
                    if(preg_match('/\nM6C_EXECUTION_TRACE:([^\r\n]+)\r?\n/',$r[2],$match)===1)$emittedTrace=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
                    if($r[0]!==0)throw new RuntimeException('Actual traced child exited unsuccessfully');
                }
                m6childTrace($r,'M6C_EXECUTION_TRACE');
            });
    }catch(M6CChildFailure $e){
        $caught=true;$observed[$case]=$e->outcome;
        m6cp($e->reason===($case==='trace-then-fatal'?'validation':'trace_missing')&&$e->outcome['exit']===255
            &&str_contains($e->outcome['stderr'],'m6cMissingFixtureFunction'),'Primary constructor/trace fatal and actual exit retained: '.$case);
        if($case==='trace-then-fatal'){
            m6cp($executionTrace===true&&is_array($emittedTrace)&&$emittedTrace['jobs']===[],'Actual parseable execution trace cannot override the later fatal exit');
            $observed[$case]['execution_trace_present']=true;
        }else m6cp($executionTrace===false,'Partial initialization or failed trace does not invent execution output: '.$case);
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
}

// An owned, disposable process-only INI verifies intentional parent -c and -d.
if($runClasses){
$profileDir=m6privateDirectory('ubo-m6c-cli-profile-');$ini=$profileDir.'/child.ini';
try{
    file_put_contents($ini,"memory_limit=48M\nauto_prepend_file=unavailable-test-prepend.php\n");chmod($ini,0600);
    $profileTrace=null;
    m6process([PHP_BINARY,'-c',$ini,'-d','sys_temp_dir='.sys_get_temp_dir(),'-d','auto_prepend_file=','-d','memory_limit=64M','-d','disable_functions=fsync',$child,'profile'],[],10000000000,
        ['case'=>'parent-c-d','profile'=>'temporary-parent-ini'],static function($r)use(&$profileTrace):void{[$profileTrace]=m6childTrace($r,'M6C_PROFILE');if($r[0]!==0)throw new RuntimeException('Profile failed');});
    m6cp($profileTrace['ini_loaded']&&$profileTrace['memory_limit']==='67108864'&&$profileTrace['max_execution_time']==='10','Parent INI and bounded memory/time forwarded');
    m6cp(!$profileTrace['fsync']&&$profileTrace['auto_prepend_file']===''&&$profileTrace['binary']===PHP_BINARY,'Relevant parent directive, no prepend, same executable');
}finally{unlink($ini);rmdir($profileDir);}
}
m6cp(!in_array('-n',$base['flags'],true),'Execution profile does not force no INI');
m6cp(m6cpDirectories('ubo-m6c-')===$fixtureRoots,'All owned fault/profile captures cleaned; unrelated evidence unchanged');
m6cp(is_dir($sentinelRoot)&&hash_file('sha256',$sentinelFile)===$sentinelHash,'Other tracked-pattern root remains byte-for-byte untouched');
m6cp(is_file($sentinelFailure->evidence)&&hash_file('sha256',$sentinelFailure->evidence)===$sentinelEvidenceHash,
    'Unrelated retained actual-failure evidence remains byte-for-byte untouched');
// Remove only the two sentinels created by this controller after proving they
// survived every child. Never enumerate roots to choose deletion targets.
m6cp(realpath(dirname($sentinelRoot))===realpath(sys_get_temp_dir())
    &&preg_match('/^ubo-m6c-[a-f0-9]{24}$/D',basename($sentinelRoot))===1
    &&!is_link($sentinelRoot)&&!is_link($sentinelFile),'Sentinel cleanup ownership verified');
$sentinelEvidence=$sentinelFailure->evidence;
if(!unlink($sentinelFile)||!rmdir($sentinelRoot))throw new RuntimeException('Owned sentinel cleanup failed');
m6discardExpectedFailure($sentinelFailure);
m6cp(!file_exists($sentinelRoot)&&!file_exists($sentinelEvidence)
    &&m6cpDirectories('ubo-m6c-')===$withoutSentinels,
    'Only controller-owned sentinels removed; successful regression data does not accumulate');
echo 'Controlled process outcomes ('.$phase.'): '.json_encode($observed,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
fwrite(STDERR,"\nM6C_PHASE_RESULT:".json_encode(['phase'=>$phase,'assertions'=>$assertions],JSON_THROW_ON_ERROR)."\n");
