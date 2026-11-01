<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';
require_once dirname(__DIR__).'/private/classes/SitePublisherAuthority.php';
$assertions=0;
$check=static function(bool $ok,string $why)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($why);};
$deny=static function(Closure $call,string $why)use($check):void{try{$call();}catch(SiteServiceException){$check(true,$why);return;}$check(false,$why);};
$db=new M6DDatabase();$db->request();$claim=$db->claim();$lease=$claim['lease'];
$target=&$db->tables['site_deployment_targets'][20];$target['publisher_key']='apache-digitalocean-v1';
$binding=['site_id'=>10,'target_id'=>20,'binding_key'=>$target['binding_key'],'binding_version'=>1,'site_key'=>$db->inner->read->base->sites[10]['site_key']];
$request=['version'=>1,'sequence'=>1,'operation'=>'stage','target'=>SiteDeploymentContract::target($target),'lease'=>$lease,'payload'=>[]];
$before=$db->snapshot();$a=SitePublisherAuthority::observe($db,$request,$binding);
$check($a['live']&&in_array('stage',$a['permitted'],true)&&$a['outcome']==='uncommitted','Committed running execution authorized');
$check($db->snapshot()===$before,'Authority observation has no table effects');
$check(!str_contains(CanonicalJson::encode($a),$lease['token']),'Lease secret absent in observer reply');
foreach(['token'=>str_repeat('0',64),'attempt_id'=>99999,'fence_epoch'=>999,'environment'=>'production'] as $k=>$v){$bad=$request;$bad['lease'][$k]=$v;$deny(fn()=>SitePublisherAuthority::observe($db,$bad,$binding),'Wrong lease '.$k);}
$op=&$db->tables['site_deployments'][$lease['deployment_id']];$op['status']='activation_attempted';$op['activation_fence']=$lease['fence_epoch'];
$request['operation']='activate';$a=SitePublisherAuthority::observe($db,$request,$binding);$check(in_array('activate',$a['permitted'],true),'Committed activation phase authorized');
$target['pointer_version']++;$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Changed expected pointer denied');$target['pointer_version']--;
$target['fence_epoch']++;$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Fresh authority supersedes old snapshot');$target['fence_epoch']--;
$op['status']='succeeded';$request['operation']='settle';$a=SitePublisherAuthority::observe($db,$request,$binding);$check($a['outcome']==='succeeded'&&!in_array('compensate',$a['permitted'],true),'Committed success cannot compensate');
$original=$db->tables['site_deployment_attempts'][$lease['attempt_id']];$recoveryId=5001;
$db->tables['site_deployment_attempts'][$recoveryId]=array_replace($original,['id'=>$recoveryId,'attempt_kind'=>'recovery',
    'recovery_of_attempt_id'=>$lease['attempt_id'],'fence_epoch'=>$lease['fence_epoch']+1,'lease_token_hash'=>hash('sha256',str_repeat('b',64)),'status'=>'running']);
$op['status']='reconciliation_required';$op['current_attempt_id']=$recoveryId;$target['fence_epoch']=$lease['fence_epoch']+1;$target['enabled']=0;
$request['lease']=array_replace($lease,['attempt_id'=>$recoveryId,'attempt_kind'=>'recovery','fence_epoch'=>$lease['fence_epoch']+1,'token'=>str_repeat('b',64)]);
$request['operation']='compensate';$a=SitePublisherAuthority::observe($db,$request,$binding);
$check($a['producer_attempt_id']===$lease['attempt_id']&&$a['outcome']==='uncommitted'&&in_array('compensate',$a['permitted'],true),'Fresh recovery preserves original execution, even after target disabled');
$request['operation']='stage';$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Recovery cannot stage new candidate');
$request['operation']='activate';$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Recovery cannot activate new candidate');
$request['operation']='compensate';$op['status']='succeeded';$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Recovery cannot undo committed success');
$op['status']='reconciliation_required';$target['current_deployment_id']=9999;$target['pointer_version']++;
$deny(fn()=>SitePublisherAuthority::observe($db,$request,$binding),'Later committed pointer blocks compensation');
$request['operation']='settle';$a=SitePublisherAuthority::observe($db,$request,$binding);$check($a['outcome']==='uncommitted','Missing acknowledgement never becomes failed or succeeded');
$db->unavailable=true;$failed=false;try{SitePublisherAuthority::observe($db,$request,$binding);}catch(Throwable){$failed=true;}$check($failed,'Unavailable authority emits no decision');
echo "M6E authority: $assertions assertions PASS (real read-only observer with sequential PDO fixture; no database connection).\n";
