<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/private/classes/SiteBuildContract.php';
require_once __DIR__ . '/support/WebsitePlatformM6BSql.php';
require_once __DIR__ . '/support/WebsitePlatformM6BReplayAssertions.php';
$assertions=0;
function replayCheck(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}

// Complete synthetic row in acceptSuccess() construction order, including private fields
// that the actual application projection must remove. No database/configuration is used.
$release=[
    'site_id'=>10,'release_key'=>'11111111-1111-4111-8111-111111111111','build_job_id'=>20,
    'source_revision_id'=>30,'source_snapshot_hash'=>str_repeat('a',64),'build_input_hash'=>str_repeat('b',64),
    'artifact_hash'=>str_repeat('c',64),'manifest_hash'=>str_repeat('d',64),'builder_version'=>'synthetic-v1',
    'builder_code_sha'=>str_repeat('e',40),'build_profile'=>'static-review-v1',
    'storage_backend'=>'synthetic','storage_key'=>'PRIVATE_STORAGE_SENTINEL',
    'manifest_json'=>'{"private":"MANIFEST_SENTINEL"}','validation_summary_json'=>'{"private":"SUMMARY_SENTINEL"}',
    'file_count'=>1,'byte_size'=>123,'built_at'=>'2026-10-07 23:25:52.000001',
    'correlation_id'=>'CORRELATION_SENTINEL','created_at'=>'2026-10-07 23:25:52.000001',
];
$release['id']=40;
$source=file_get_contents(dirname(__DIR__).'/private/classes/SiteBuildService.php');
preg_match('/private static function acceptSuccess\(.*?\$release = \[(.*?)\n        \];/s',$source,$construction);
preg_match_all("/'([a-z_]+)' =>/",$construction[1],$fields);
replayCheck(array_keys($release)===array_merge($fields[1],['id']),'Fixture matches actual acceptSuccess construction, id appended last');
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/025_site_build_deployment.sql');
$tables=array_values(array_filter(m6bSqlStatements($sql),static fn($statement)=>str_starts_with($statement,'CREATE TABLE site_releases (')));
replayCheck(count($tables)===1,'Canonical release CREATE available');
preg_match_all('/^    ([a-z_]+) (?:BIGINT|CHAR|VARCHAR|JSON|INT|DATETIME)\b/m',$tables[0],$columns);
replayCheck(count($columns[1])===count($release)&&array_diff($columns[1],array_keys($release))===[],'Complete row matches every schema column');
$readback=[];foreach($columns[1]as$key)$readback[$key]=$release[$key];
$completionRelease=SiteBuildContract::release($release);$readbackRelease=SiteBuildContract::release($readback);
replayCheck(array_key_last($completionRelease)==='id'&&array_key_first($readbackRelease)==='id','Actual projection retains completion/readback ordering');
replayCheck(count($completionRelease)===17&&count($readbackRelease)===17,'All 17 release DTO fields retained');
$job=SiteBuildContract::job([
    'id'=>20,'site_id'=>10,'revision_id'=>30,'job_key'=>'22222222-2222-4222-8222-222222222222',
    'release_key'=>$release['release_key'],'status'=>'succeeded','snapshot_hash'=>$release['source_snapshot_hash'],
    'build_input_hash'=>$release['build_input_hash'],'builder_version'=>$release['builder_version'],'build_profile'=>$release['build_profile'],
    'attempt_count'=>1,'execution_count'=>1,'max_execution_attempts'=>3,'recovery_count'=>0,'automatic_recovery_count'=>0,
    'max_automatic_recoveries'=>2,'recovery_status'=>'none','next_attempt_at'=>null,'next_recovery_at'=>null,
    'failure_category'=>null,'failure_code'=>null,'safe_summary'=>null,'started_at'=>$release['built_at'],
    'completed_at'=>$release['built_at'],'created_at'=>$release['created_at'],'updated_at'=>$release['created_at'],
    'correlation_id'=>$release['correlation_id'],'release_id'=>40,
]);
$expected=$job+['existing'=>true,'replayed'=>true,'release'=>$completionRelease];
$observed=['job'=>$job+['existing'=>true,'replayed'=>true,'release'=>$readbackRelease],'prepared'=>0,'verified'=>0];
// Exact worker encoder and coordinator decoder options, not a comparison canonicalizer.
$observed=json_decode(json_encode($observed,JSON_THROW_ON_ERROR),true,64,JSON_THROW_ON_ERROR);
replayCheck($observed['job']!==$expected,'Old native nested strict comparison reproduces false failure');
replayCheck(array_diff_key($completionRelease,$readbackRelease)===[]&&array_diff_key($readbackRelease,$completionRelease)===[],
    'Completion/readback field sets identical before comparison correction');
foreach($completionRelease as$key=>$value)replayCheck($observed['job']['release'][$key]===$value,'Every actual release field value AND type survives ordering and JSON');
replayCheck(M6BReplayAssertions::same($expected,$observed['job']),'Corrected comparison accepts only synthetic key-order difference');
replayCheck(M6BReplayAssertions::same($observed['job'],$expected),'Comparison is symmetric');
$snapshot=['site_build_jobs'=>'SNAPSHOT_SENTINEL','site_releases'=>'RELEASE_SNAPSHOT_SENTINEL'];
$summary=M6BReplayAssertions::summary($observed,$expected,$snapshot,$snapshot);
replayCheck($summary['failed_conditions']===[]&&count($summary['conditions'])===12,'All 12 independent conditions pass');
replayCheck(in_array(['path'=>'worker.job.release','category'=>'order-only'],$summary['differences'],true),'Safe diagnostic identifies nested order-only difference');

// Mutate decoded operands: the assertion itself must not coerce scalar types.
foreach(['id','site_id','revision_id','release_id','job_key','release_key','snapshot_hash','build_input_hash','builder_version','build_profile']as$key){
    $changed=$observed;$value=$changed['job'][$key];$changed['job'][$key]=is_int($value)?$value+1:$value.'changed';
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions']['reply_content']&&!$report['conditions']['job_record'],'Changed job identity/value rejected: '.$key);
}
foreach(['id','site_id','build_job_id','source_revision_id','release_key','source_snapshot_hash','build_input_hash','artifact_hash','manifest_hash','builder_code_sha']as$key){
    $changed=$observed;$value=$changed['job']['release'][$key];$changed['job']['release'][$key]=is_int($value)?$value+1:$value.'changed';
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions']['reply_content']&&!$report['conditions']['release_record'],'Changed release identity/hash rejected: '.$key);
}
foreach(['numeric-string'=>'20','float'=>20.0,'boolean'=>true,'null'=>null]as$label=>$value){
    $changed=$observed;$changed['job']['id']=$value;
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions']['job_record'],'Strict job type rejected: '.$label);
    replayCheck(in_array(['path'=>'worker.job.id','category'=>'type','expected_type'=>'integer','actual_type'=>gettype($value)],$report['differences'],true),
        'Safe type diagnostic: '.$label);
}
foreach(['existing','replayed']as$key)foreach([false,1,'1',null]as$value){
    $changed=$observed;$changed['job'][$key]=$value;
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions'][$key.'_true']&&!$report['conditions']['reply_content'],'Replay flag must be boolean true');
}
foreach(['prepared','verified']as$key)foreach([1,'0',0.0,false,null]as$value){
    $changed=$observed;$changed[$key]=$value;
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions'][$key.'_zero'],'No-effects counter must be integer zero');
}
foreach(['job','release']as$record){
    $changed=$observed;
    if($record==='job')unset($changed['job']['failure_code']);else unset($changed['job']['release']['id']);
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions'][$record.'_record']&&!$report['conditions']['reply_content'],'Missing field rejected, including missing versus explicit null');
    replayCheck(in_array(['path'=>$record==='job'?'worker.job.failure_code':'worker.job.release.id','category'=>'missing'],$report['differences'],true),'Missing field diagnostic');
    $changed=$observed;
    if($record==='job')$changed['job']['SECRET_FIELD_SENTINEL']='SECRET_VALUE_SENTINEL';
    else $changed['job']['release']['SECRET_FIELD_SENTINEL']='SECRET_VALUE_SENTINEL';
    $report=M6BReplayAssertions::summary($changed,$expected,$snapshot,$snapshot);
    replayCheck(!$report['conditions'][$record.'_record'],'Extra record field rejected without projecting it away');
    replayCheck(in_array(['path'=>$record==='job'?'worker.job':'worker.job.release','category'=>'extra'],$report['differences'],true),'Extra field reported at allowlisted parent');
    replayCheck(!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'SECRET_'),'Unknown names and values never logged');
}
$changed=$observed;$changed['job']['failure_code']=false;
replayCheck(!M6BReplayAssertions::same($expected,$changed['job']),'Explicit null is not false');
$changed=$observed;$changed['job']=array_reverse($changed['job'],true);
$changed['job']['release']=array_reverse($changed['job']['release'],true);
replayCheck(M6BReplayAssertions::same($expected,$changed['job']),'Both associative record orders may change');
foreach([
    [[1,2],[2,1],false],[[1,2],[1],false],[[1],[1.0],false],[[1],['1'],false],[[null],[],false],
    [['a'=>1,'b'=>null],['b'=>null,'a'=>1],true],[[['a'=>1,'b'=>2]],[['b'=>2,'a'=>1]],true],
    [[['id'=>1],['id'=>2]],[['id'=>2],['id'=>1]],false],[[1,2],[1=>2,0=>1],false],
]as[$left,$right,$equal]){
    replayCheck(M6BReplayAssertions::same(['nested'=>['items'=>$left]],['nested'=>['items'=>$right]])===$equal,
        'Nested list length/order/types and associative field distinctions retained');
}

// Simultaneous failures must all be diagnosed before the first assertion throws.
$broken=$observed;$broken['job']['id']='SECRET_VALUE_SENTINEL';$broken['job']['release']['artifact_hash']='ARTIFACT_SENTINEL';
$broken['job']['existing']=false;$broken['job']['replayed']=1;$broken['prepared']=1;$broken['verified']=1;
$changedSnapshot=$snapshot;$changedSnapshot['site_build_jobs']='CHANGED_SNAPSHOT_SENTINEL';
$calls=[];$thrown=false;ob_start();
try{M6BReplayAssertions::check($broken,$expected,$snapshot,$changedSnapshot,static function(bool $ok,string $label)use(&$calls):void{
    $calls[]=$label;if(!$ok)throw new RuntimeException('Expected synthetic assertion failure');
});}catch(RuntimeException $e){$thrown=$e->getMessage()==='Expected synthetic assertion failure';}
$output=ob_get_clean();
replayCheck($thrown&&count($calls)===5,'First failed reply check throws only after summary emission');
replayCheck(str_starts_with($output,'M6B_REPLAY_DIAGNOSTIC ')&&substr_count($output,"\n")===1,'One bounded diagnostic line emitted before failure');
$saved=json_decode(substr($output,strlen('M6B_REPLAY_DIAGNOSTIC ')),true,64,JSON_THROW_ON_ERROR);
foreach(['reply_content','existing_true','replayed_true','job_record','release_record','prepared_zero','verified_zero','snapshot_unchanged']as$condition){
    replayCheck($saved['conditions'][$condition]===false&&in_array($condition,$saved['failed_conditions'],true),'Independent failed operand retained: '.$condition);
}
foreach(['PRIVATE_STORAGE_SENTINEL','MANIFEST_SENTINEL','SUMMARY_SENTINEL','CORRELATION_SENTINEL','SECRET_VALUE_SENTINEL',
    'ARTIFACT_SENTINEL','SNAPSHOT_SENTINEL','CHANGED_SNAPSHOT_SENTINEL',$release['release_key'],$release['source_snapshot_hash']]as$sentinel){
    replayCheck(!str_contains($output,$sentinel),'No DTO values, source hashes, payloads or snapshot contents in diagnostics');
}
replayCheck(strlen($output)<16384,'Diagnostic byte bound');
$calls=[];ob_start();
M6BReplayAssertions::check($observed,$expected,$snapshot,$snapshot,static function(bool $ok,string $label)use(&$calls):void{$calls[]=$ok;});
ob_end_clean();
replayCheck(count($calls)===12&&!in_array(false,$calls,true),'Passing case checks every requirement');

// Invalid JSON result shapes must remain safe even with warnings promoted to exceptions.
set_error_handler(static function(int $severity,string $message):never{throw new ErrorException($message,0,$severity);});
try{
    foreach([null,true,17,'SHAPE_SENTINEL',new stdClass(),[],[$observed],['job'=>null],['job'=>'SHAPE_SENTINEL','prepared'=>0,'verified'=>0],
        ['job'=>[],'prepared'=>0,'verified'=>0],['job'=>['release'=>true],'prepared'=>0,'verified'=>0]]as$malformed){
        $report=M6BReplayAssertions::summary($malformed,$expected,$snapshot,$changedSnapshot);
        replayCheck($report['failed_conditions']!==[]&&$report['conditions']['snapshot_unchanged']===false,'Malformed response safely diagnosed alongside snapshot');
        replayCheck(count($report['conditions'])===12&&!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'SHAPE_SENTINEL'),'All conditions retained without raw invalid response');
    }
}finally{restore_error_handler();}
$oversized=$observed;for($i=0;$i<1000;$i++)$oversized['job']['SECRET_KEY_'.$i]=str_repeat('SECRET_PAYLOAD',50);
$report=M6BReplayAssertions::summary($oversized,$expected,$snapshot,$snapshot);$safe=json_encode($report,JSON_THROW_ON_ERROR);
replayCheck(!$report['conditions']['response_fields']&&!$report['conditions']['job_record'],'Unexpected fields cannot bypass comparison');
replayCheck(strlen($safe)<16384&&count($report['differences'])<=64&&!str_contains($safe,'SECRET_'),'Unknown-field flood remains bounded and redacted');
echo "Website platform M6B replay comparison: $assertions assertions passed; actual DTO projection plus worker JSON, no SQL/native replay.\n";
