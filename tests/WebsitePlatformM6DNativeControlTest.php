<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DNativeContract.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeControl.php';
$assertions=0;
function m6dnAssert(bool $ok,string $code): void { $GLOBALS['assertions']++; if(!$ok) throw new RuntimeException($code); }
function m6dnDeny(callable $call): void { try{$call();}catch(RuntimeException){m6dnAssert(true,'expected_rejection');return;}throw new RuntimeException('missing_rejection'); }
$sha=str_repeat('a',40); $image='sha256:'.str_repeat('b',64); $token=str_repeat('c',32);
$a=['profile'=>M6DNativeContract::PROFILE,'source_sha'=>$sha,'image_id'=>$image,'run_token'=>$token,
    'database_name'=>'ubo_m6d_'.$token,'database_user'=>'m6d_'.substr($token,0,16),'database_hostname'=>'m6d-db-'.substr($token,0,16),
    'runtime_hostname'=>'m6d-run-'.substr($token,0,16),'mysql_server_uuid'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'worker_uid'=>2000,'deadline_seconds'=>600,'memory_bytes'=>536870912,'pids'=>32,'storage_bytes'=>134217728,'network_isolated'=>true];
M6DNativeContract::binding($a,$sha,$image,$token); m6dnAssert(true,'exact_admission');
foreach($a as $key=>$value) {
    $changed=$a; $changed[$key]=is_int($value) ? $value+1 : (is_bool($value) ? false : 'wrong');
    m6dnDeny(fn()=>M6DNativeContract::binding($changed,$sha,$image,$token));
}
m6dnDeny(fn()=>M6DNativeContract::binding($a+['dsn'=>'mysql:host=production'],$sha,$image,$token));
$r=M6DNativeContract::initial($sha,$image,$token);
m6dnAssert(!M6DNativeContract::complete($r),'unexecuted_cannot_pass');
foreach(M6DNativeContract::CASES as $case) $r['cases'][$case]=['status'=>'PASS','assertions'=>1,'lock_wait_observed'=>true];
$r['cleanup']='PASS'; $r['capture']='PASS'; m6dnAssert(M6DNativeContract::complete($r),'synthetic_complete_report');
foreach(M6DNativeContract::CASES as $case) {
    $bad=$r; $bad['cases'][$case]['status']='BLOCKED'; m6dnAssert(!M6DNativeContract::complete($bad),'missing_native_case_not_skipped');
}
foreach(['profile'=>'m6c-native-v1','cleanup'=>'FAIL','capture'=>'FAIL','primary_failure'=>['code'=>'fail']] as $key=>$value) {
    $bad=$r; $bad[$key]=$value; m6dnAssert(!M6DNativeContract::complete($bad),'independent_result_component');
}
foreach(['concurrent-request-identity','concurrent-target-claims','production-role-lock'] as $case) {
    $bad=$r; $bad['cases'][$case]['lock_wait_observed']=false; m6dnAssert(!M6DNativeContract::complete($bad),'sequential_fake_not_lock_proof');
}
$e=new PDOException('password=PRIVATE SQL /sensitive/path'); $e->errorInfo=['23000',1062,'PRIVATE'];
$safe=M6DNativeContract::safeFailure($e);
m6dnAssert(!str_contains(json_encode($safe),'PRIVATE') && $safe['driver']===1062,'bounded_pdo_diagnostics');
m6dnAssert(M6DNativeControl::ownedSchema($a)==='ubo_m6d_'.$token,'exact_owned_schema');
$foreign=$a; $foreign['database_name']='ultimate_back_office'; m6dnDeny(fn()=>M6DNativeControl::ownedSchema($foreign));
foreach(['primary','cleanup','child','publication','unowned'] as $fault) {
    $result=$r; $calls=[];
    if($fault==='primary') $result['primary_failure']=['class'=>'RuntimeException','code'=>'original_failure'];
    M6DNativeControl::cleanup($result,$fault!=='unowned',static function() use($fault): array {
        return [['pid'=>10,'exit'=>-1,'signaled'=>true,'signal'=>9,'running'=>$fault==='child','termination_requested'=>true,'stderr'=>'PRIVATE']];
    },static function() use(&$calls,$fault): void { $calls[]='exact-schema'; if($fault==='cleanup')throw new RuntimeException('cleanup_failure'); });
    m6dnAssert(($fault==='child' || $fault==='unowned') ? $calls===[] : $calls===['exact-schema'],'cleanup_admission_order');
    M6DNativeControl::publish($result,static function(array &$report) use($fault): void {
        if($fault==='publication')throw new RuntimeException('publication_failure'); $report['publication']='PASS';
    });
    m6dnAssert($result['status']==='FAIL','failure_components_never_pass');
    m6dnAssert(!str_contains(json_encode($result),'PRIVATE'),'safe_child_cleanup_summary');
    if($fault==='primary')m6dnAssert($result['primary_failure']['code']==='original_failure','primary_preserved_after_cleanup_publication');
}
echo "M6D native control: $assertions assertions PASS (synthetic admission/report records; native NOT EXECUTED).\n";
