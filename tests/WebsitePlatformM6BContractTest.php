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
echo "Website platform M6B contract: $assertions assertions passed.\n";
