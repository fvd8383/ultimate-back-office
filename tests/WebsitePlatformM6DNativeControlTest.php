<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DNativeContract.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeControl.php';
$assertions=0;
function m6dnAssert(bool $ok,string $code): void { $GLOBALS['assertions']++; if(!$ok) throw new RuntimeException($code); }
function m6dnDeny(callable $call,string $expected): void { try{$call();}catch(RuntimeException $e){m6dnAssert($e->getMessage()===$expected,'exact_control_rejection');return;}throw new RuntimeException('missing_rejection'); }
$sha=str_repeat('a',40); $image='sha256:'.str_repeat('b',64); $token=str_repeat('c',32);
$a=['profile'=>M6DNativeContract::PROFILE,'source_sha'=>$sha,'image_id'=>$image,'run_token'=>$token,
    'database_name'=>'ubo_m6d_'.$token,'database_user'=>'m6d_'.substr($token,0,16),'database_hostname'=>'m6d-db-'.substr($token,0,16),
    'runtime_hostname'=>'m6d-run-'.substr($token,0,16),'mysql_server_uuid'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'worker_uid'=>2000,'deadline_seconds'=>600,'memory_bytes'=>536870912,'pids'=>32,'storage_bytes'=>134217728,'network_isolated'=>true];
M6DNativeContract::binding($a,$sha,$image,$token); m6dnAssert(true,'exact_admission');
foreach($a as $key=>$value) {
    $changed=$a; $changed[$key]=is_int($value) ? $value+1 : (is_bool($value) ? false : 'wrong');
    $expected=in_array($key,['profile','source_sha','image_id','run_token'],true) ? 'profile_source_image'
        : (in_array($key,['database_name','database_user','database_hostname','runtime_hostname','mysql_server_uuid'],true) ? 'disposable_identity' : 'resource_binding');
    m6dnDeny(fn()=>M6DNativeContract::binding($changed,$sha,$image,$token),$expected);
}
m6dnDeny(fn()=>M6DNativeContract::binding($a+['dsn'=>'mysql:host=production'],$sha,$image,$token),'admission_shape');
$r=M6DNativeContract::initial($sha,$image,$token);
m6dnAssert(!M6DNativeContract::complete($r),'unexecuted_cannot_pass');
foreach(M6DNativeContract::CASES as $case) {
    $r['cases'][$case]=['status'=>'PASS','assertions'=>count(M6DNativeContract::subcases($case)),'subcases'=>[]];
    foreach(M6DNativeContract::subcases($case) as $sub) {
        $r['cases'][$case]['subcases'][$sub]=['status'=>'PASS','assertions'=>1];
        if(($order=M6DNativeContract::ordering($case,$sub))!==null) $r['cases'][$case]['subcases'][$sub]['ordering']=[
            'kind'=>$order,'observed'=>true,'first_connection'=>1,'second_connection'=>2,'first_committed'=>true,'second_completed'=>true];
    }
}
$r['cleanup']='PASS'; $r['capture']='PASS'; m6dnAssert(M6DNativeContract::complete($r),'synthetic_complete_report');
foreach(M6DNativeContract::CASES as $case) {
    $bad=$r; $bad['cases'][$case]['status']='BLOCKED'; m6dnAssert(!M6DNativeContract::complete($bad),'missing_native_case_not_skipped');
    foreach(M6DNativeContract::subcases($case) as $sub) {
        foreach(['UNREACHED','RUNNING','FAIL','SKIP'] as $state) {
            $bad=$r; $bad['cases'][$case]['subcases'][$sub]['status']=$state; m6dnAssert(!M6DNativeContract::complete($bad),'partial_subcase_rejected');
        }
        $bad=$r; unset($bad['cases'][$case]['subcases'][$sub]); m6dnAssert(!M6DNativeContract::complete($bad),'missing_subcase_rejected');
        $bad=$r; $bad['cases'][$case]['subcases'][$sub]['assertions']=0; m6dnAssert(!M6DNativeContract::complete($bad),'no_assertions_not_pass');
        if(M6DNativeContract::ordering($case,$sub)!==null) foreach(['observed'=>false,'first_committed'=>false,'second_completed'=>false,'second_connection'=>1,'kind'=>'wrong'] as $k=>$v) {
            $bad=$r; $bad['cases'][$case]['subcases'][$sub]['ordering'][$k]=$v; m6dnAssert(!M6DNativeContract::complete($bad),'each_race_requires_own_ordering');
        }
    }
}
foreach(['profile'=>'m6c-native-v1','cleanup'=>'FAIL','capture'=>'FAIL','primary_failure'=>['code'=>'fail']] as $key=>$value) {
    $bad=$r; $bad[$key]=$value; m6dnAssert(!M6DNativeContract::complete($bad),'independent_result_component');
}
foreach(['source_sha'=>str_repeat('d',40),'image_id'=>'sha256:'.str_repeat('d',64),'run_token'=>str_repeat('d',32)] as $k=>$v) {
    $bad=$r; $bad[$k]=$v; m6dnAssert(!M6DNativeContract::complete($bad,$a),'exact_run_identity');
}
$bad=$r; $bad['profile']='m6d-db-authority-v1'; m6dnAssert(!M6DNativeContract::complete($bad),'old_incomplete_gate_rejected');
$bad=$r; $bad['cases']=array_values($bad['cases']); m6dnAssert(!M6DNativeContract::complete($bad),'duplicate_list_not_inventory');
$bad=$r; $bad['cases']['extra']=$bad['cases'][M6DNativeContract::CASES[0]]; m6dnAssert(!M6DNativeContract::complete($bad),'extra_case_rejected');
$bad=$r; $bad['cases'][M6DNativeContract::CASES[0]]['subcases']['extra']=['status'=>'PASS','assertions'=>1]; m6dnAssert(!M6DNativeContract::complete($bad),'extra_subcase_rejected');
$published=$r; $published['status']='PASS'; $published['publication']='PASS';
$bytes=json_encode($published,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
m6dnAssert(M6DNativeContract::acceptPublished($bytes,$a)===$published,'exact_published_report_accepted');
$duplicate=preg_replace('/"status": "PASS"/','"status": "FAIL", "status": "PASS"',$bytes,1);
m6dnDeny(fn()=>M6DNativeContract::acceptPublished($duplicate,$a),'report_canonical_encoding');
$duplicate=preg_replace('/"service-observations": \{/','"service-observations": {}, "service-observations": {',$bytes,1);
m6dnDeny(fn()=>M6DNativeContract::acceptPublished($duplicate,$a),'report_canonical_encoding');
$badIdentity=$a; $badIdentity['source_sha']=str_repeat('d',40);
m6dnDeny(fn()=>M6DNativeContract::acceptPublished($bytes,$badIdentity),'report_not_accepted');
$e=new PDOException('password=PRIVATE SQL /sensitive/path'); $e->errorInfo=['23000',1062,'PRIVATE'];
$safe=M6DNativeContract::safeFailure($e);
m6dnAssert(!str_contains(json_encode($safe),'PRIVATE') && $safe['driver']===1062,'bounded_pdo_diagnostics');
$e=new PDOException("control\x1b\nPRIVATE"); $e->errorInfo=["bad\nstate","PRIVATE",'SQL PRIVATE'];
$safe=M6DNativeContract::safeFailure($e); m6dnAssert($safe['code']==='native_failure' && $safe['sqlstate']===null && $safe['driver']===null,'control_character_diagnostics_redacted');
m6dnAssert(M6DNativeControl::ownedSchema($a)==='ubo_m6d_'.$token,'exact_owned_schema');
$foreign=$a; $foreign['database_name']='ultimate_back_office'; m6dnDeny(fn()=>M6DNativeControl::ownedSchema($foreign),'disposable_identity');
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
$combined=$r; $combined['primary_failure']=['class'=>'RuntimeException','code'=>'first_failure'];
M6DNativeControl::cleanup($combined,true,static fn()=>[],static fn()=>throw new RuntimeException('secondary_cleanup'));
M6DNativeControl::publish($combined,static fn()=>throw new RuntimeException('secondary_publication'));
m6dnAssert($combined['primary_failure']['code']==='first_failure' && $combined['cleanup_failure']['code']==='secondary_cleanup'
    && $combined['publication_failure']['code']==='secondary_publication' && $combined['status']==='FAIL','three_independent_failure_records');
echo "M6D native control: $assertions assertions PASS (synthetic admission/report records; native NOT EXECUTED).\n";
