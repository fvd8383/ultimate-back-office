<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BDatabase.php';
$assertions=0;
function m6c(bool $ok,string $why): void { global $assertions; $assertions++; if(!$ok) throw new RuntimeException($why); }
function m6cd(callable $call): void { try{$call();}catch(SiteServiceException){m6c(true,'safe denial');return;}throw new RuntimeException('Expected denial'); }
$value=['z'=>null,'a'=>['é'=>2,'a'=>1],'list'=>[2,1]];
m6c(CanonicalJson::encode($value)==='{"a":{"a":1,"é":2},"list":[2,1],"z":null}','Golden canonical UTF8/integer/NULL/map/list encoding');
m6c(CanonicalJson::hash($value)===hash('sha256','{"a":{"a":1,"é":2},"list":[2,1],"z":null}'),'Golden canonical digest');
$db=WebsitePlatformM6BDatabase::fixture();
$source=SiteBuildStore::transaction(fn($pdo)=>SiteRevisionManager::lockBuildEligibility($pdo,10,100,$db->read->base->revisions[100]['snapshot_hash']));
$builder=$db->runtime->builderIdentity(); $projection=$db->runtime->prepareInput($source);
$identity=SiteBuildContract::input($source,$projection,$builder);
$permuted=array_reverse($projection,true);
m6c($identity===SiteBuildContract::input($source,$permuted,array_reverse($builder,true)),'Key order cannot change build identity');
$builder['builder_code_sha']=str_repeat('b',40);
m6c($identity['idempotency_key']!==SiteBuildContract::input($source,$projection,$builder)['idempotency_key'],'Reviewed code SHA changes identity');
$source['revision']['id']=101;
m6c($identity['idempotency_key']!==SiteBuildContract::input($source,$projection,$db->runtime->builderIdentity())['idempotency_key'],'Revision identity participates');
$projection['ordered_asset_digests']=[
    ['usage_key'=>'z','sha256'=>str_repeat('a',64),'byte_size'=>1,'mime_type'=>'image/png'],
    ['usage_key'=>'a','sha256'=>str_repeat('b',64),'byte_size'=>2,'mime_type'=>'image/png']];
m6cd(fn()=>SiteBuildContract::input($source,$projection,$builder));

$execution=['attempt_kind'=>'execution','recovery_trigger'=>null,'operator_request_key'=>null,
    'recovery_authorized_by_user_id'=>null,'recovery_actor_type'=>null,'recovery_reason_code'=>null];
$auto=array_replace($execution,['attempt_kind'=>'recovery','recovery_trigger'=>'automatic',
    'recovery_actor_type'=>'system','recovery_reason_code'=>'inspect_unknown_outcome']);
$actor=['acting_user_id'=>2,'actor_type'=>'super_admin','is_internal_admin'=>true];
$operator=array_replace($auto,['recovery_trigger'=>'operator','operator_request_key'=>SiteServiceSupport::uuidV4(),
    'recovery_authorized_by_user_id'=>2,'recovery_actor_type'=>'super_admin']);
SiteBuildContract::claimActor($execution,null); SiteBuildContract::claimActor($auto,null); SiteBuildContract::claimActor($operator,$actor);
m6c(true,'Shared build/deployment future claim helper accepts strict insertion shapes');
foreach ([$execution,$auto] as $fields) m6cd(fn()=>SiteBuildContract::claimActor(array_replace($fields,['recovery_authorized_by_user_id'=>2]),null));
m6cd(fn()=>SiteBuildContract::claimActor(array_replace($operator,['recovery_authorized_by_user_id'=>null]),$actor));
m6cd(fn()=>SiteBuildContract::claimActor($operator,null));
m6cd(fn()=>SiteBuildContract::claimActor($operator,array_replace($actor,['is_internal_admin'=>false])));
foreach ([['site_id'=>10.0],['revision_id'=>'100'],['expected_snapshot_hash'=>str_repeat('a',63)],['builder_code_sha'=>str_repeat('a',40)],['build_options_json'=>[]],['build_profile'=>'production'],['requested_by_user_id'=>2]] as $input) {
    m6cd(fn()=>$db->request(1,$input));
}
$job=$db->request(); $claim=$db->claim(); $before=$db->snapshot();
m6cd(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],['verified'=>true,'artifact_hash'=>str_repeat('a',64)]));
m6cd(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],['receipt_key'=>SiteServiceSupport::uuidV4()]));
m6c($before===$db->snapshot(),'Arbitrary receipts/digests/flags cannot authorize output');
(new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,null);
m6cd(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],['receipt_key'=>SiteServiceSupport::uuidV4()]));
m6c($before===$db->snapshot(),'Missing artifact verifier fails closed before any success persistence');
$row=$db->tables['site_build_jobs'][$job['id']];
$row['safe_summary']='PRIVATE-SQL-ERROR'; $row['input_manifest_json']='PRIVATE-SNAPSHOT'; $row['lease_token_hash']='PRIVATE-TOKEN';
$safe=json_encode(SiteBuildContract::job($row));
m6c(!str_contains($safe,'PRIVATE-'),'DTO ignores private keys and stored free-text summary');
// One pure compatibility classifier drives both queue disposition and strict execution denial.
$supported=['worker_policy_version'=>SiteBuildContract::POLICY_VERSION,'worker_policy_json'=>CanonicalJson::encode(SiteBuildContract::POLICY),
    'max_execution_attempts'=>3,'max_automatic_recoveries'=>2];
m6c(SiteBuildContract::policyFailure($supported)===null&&SiteBuildContract::policy($supported)===SiteBuildContract::POLICY,'Supported policy has no failure and retains strict execution contract');
foreach([1,2,3,'1','2','3']as$limit){
    $row=array_replace($supported,['max_execution_attempts'=>$limit,'max_automatic_recoveries'=>'0',
        'worker_policy_json'=>json_encode(array_reverse(SiteBuildContract::POLICY,true),JSON_THROW_ON_ERROR)]);
    m6c(SiteBuildContract::policyFailure($row)===null,'PDO integer/string limits and reordered semantic policy stay supported');
}
foreach([['worker_policy_version'=>'historical'],['worker_policy_json'=>'null'],['worker_policy_json'=>'{}'],
    ['worker_policy_json'=>'{"value":1e400}'],['worker_policy_json'=>str_repeat(' ',4097).'{}'],
    ['max_execution_attempts'=>4],['max_automatic_recoveries'=>3],['max_execution_attempts'=>1.5],
    ['max_automatic_recoveries'=>'garbage'],['worker_policy_json'=>null]]as$override){
    $row=array_replace($supported,$override);$before=$row;
    m6c(SiteBuildContract::policyFailure($row)==='policy_unsupported'&&$row===$before,'Pure classification preserves unsupported persisted evidence');
    m6cd(fn()=>SiteBuildContract::policy($row));
}
foreach(['policy_unsupported','execution_exhausted']as$code){
    $failure=SiteBuildContract::failure($code);
    m6c($failure['failure_category']==='configuration'&&$failure['failure_code']===$code&&strlen($failure['safe_summary'])<100,'Fixed bounded configuration failure cannot become transient retry');
}
$db=WebsitePlatformM6BDatabase::fixture();$job=$db->request();$claim=$db->claim();$receipt=$db->receipt($claim,'sealed');
$db->tables['site_build_jobs'][$job['id']]['worker_policy_json']='{}';$before=$db->snapshot();
m6cd(fn()=>SiteBuildService::renewBuildLease($claim['lease']));
m6cd(fn()=>SiteBuildService::completeBuildSuccess($claim['lease'],$receipt));
m6cd(fn()=>SiteBuildService::claimBuildRecovery($job['id'],[]));
m6c($before===$db->snapshot(),'Renewal, completion and recovery cannot substitute default policy or mutate ownership/history');
echo "Website platform M6B contract: $assertions assertions passed.\n";
