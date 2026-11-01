<?php
declare(strict_types=1);
require_once __DIR__.'/SiteDeploymentContract.php';
require_once __DIR__.'/SiteBuildStore.php';

/** Read-only committed-state observer. Separate protected responder/account, never worker assertions.
 * Caller starts a fresh READ ONLY transaction; return only after it has committed.
 */
final class SitePublisherAuthority
{
    public static function observe(object $db,array $request,array $binding): array
    {
        SiteDeploymentContract::keys($request,['version','sequence','operation','target','lease','payload']);
        if($request['version']!==1||!in_array($request['operation'],['stage','activate','restore','inspect','probe','compensate','settle'],true))self::deny();
        $lease=SiteDeploymentContract::lease($request['lease']);$t=$request['target'];
        if($lease['environment']!=='staging'||$t['environment']!=='staging'||$t['publisher_key']!=='apache-digitalocean-v1')self::deny();
        foreach(['site_id','target_id','binding_key','binding_version'] as $k)if($t[$k]!==$binding[$k])self::deny();
        $row=static function(string $table,int $id)use($db,$lease):array {
            if(!in_array($table,['site_deployments','site_deployment_attempts','site_releases','site_deployment_targets'],true))self::deny();
            return SiteBuildStore::one($db,'SELECT * FROM '.$table.' WHERE id = :id AND site_id = :site_id',
                ['id'=>$id,'site_id'=>$lease['site_id']])??self::deny();
        };
        $now=SiteBuildStore::now($db);
        $target=$row('site_deployment_targets',$lease['target_id']);$op=$row('site_deployments',$lease['deployment_id']);
        $attempt=$row('site_deployment_attempts',$lease['attempt_id']);$release=$row('site_releases',(int)$op['release_id']);
        $site=SiteBuildStore::one($db,'SELECT id, site_key FROM sites WHERE id = :site_id LIMIT 1',['site_id'=>$lease['site_id']])??self::deny();
        if($site['site_key']!==$binding['site_key']||(int)$op['target_id']!==$lease['target_id']
            ||(int)$attempt['deployment_id']!==$lease['deployment_id']||$attempt['attempt_kind']!==$lease['attempt_kind']
            ||$attempt['worker_id']!==$lease['worker_id']||(int)$attempt['fence_epoch']!==$lease['fence_epoch']
            ||!hash_equals($attempt['lease_token_hash'],hash('sha256',$lease['token'])))self::deny();
        SiteDeploymentContract::policy($op);
        if($target['environment']!=='staging'||$target['publisher_key']!=='apache-digitalocean-v1'
            ||$target['binding_key']!==$binding['binding_key']||(int)$target['binding_version']!==$binding['binding_version']
            ||(int)$op['binding_version']!==$binding['binding_version'])self::deny();
        $producer=$attempt['attempt_kind']==='execution'?$attempt:$row('site_deployment_attempts',(int)$attempt['recovery_of_attempt_id']);
        if($producer['attempt_kind']!=='execution'||(int)$producer['deployment_id']!==$lease['deployment_id'])self::deny();
        $previous=null;
        if($op['expected_current_deployment_id']!==null) {
            $p=$row('site_deployments',(int)$op['expected_current_deployment_id']);$pr=$row('site_releases',(int)$p['release_id']);
            if($p['status']!=='succeeded'||(int)$p['target_id']!==$lease['target_id']||$p['activation_fence']===null)self::deny();
            $pa=$row('site_deployment_attempts',(int)$p['current_attempt_id']);
            if($pa['attempt_kind']==='recovery')$pa=$row('site_deployment_attempts',(int)$pa['recovery_of_attempt_id']);
            if($pa['attempt_kind']!=='execution'||(int)$pa['deployment_id']!==(int)$p['id'])self::deny();
            $previous=['release_id'=>(int)$pr['id'],'site_key'=>$site['site_key'],'release_key'=>$pr['release_key'],'artifact_hash'=>$pr['artifact_hash'],
                'manifest_hash'=>$pr['manifest_hash'],'deployment_id'=>(int)$p['id'],'producer_attempt_id'=>(int)$pa['id'],'fence_epoch'=>(int)$p['activation_fence']];
        }
        $live=(int)$op['current_attempt_id']===$lease['attempt_id']&&(int)$target['active_deployment_id']===$lease['deployment_id']
            &&(int)$target['fence_epoch']===$lease['fence_epoch']&&in_array($attempt['status'],['leased','running'],true)
            &&$attempt['lease_expires_at']>$now&&$attempt['deadline_at']>$now;
        $outcome=$op['status']==='succeeded'?'succeeded':(in_array($op['status'],['failed','retry_wait','cancelled'],true)?'failed':'uncommitted');
        $pointer=($target['current_deployment_id']===null?null:(int)$target['current_deployment_id'])===($op['expected_current_deployment_id']===null?null:(int)$op['expected_current_deployment_id'])
            &&(int)$target['pointer_version']===(int)$op['expected_pointer_version'];
        $permitted=['inspect','probe'];
        if($live&&$attempt['attempt_kind']==='execution'&&(int)$target['enabled']===1&&$pointer) {
            if($op['status']==='running')$permitted[]='stage';
            if($op['status']==='activation_attempted'&&(int)$op['activation_fence']===$lease['fence_epoch'])$permitted[]=$op['operation']==='restore'?'restore':'activate';
        }
        if($live&&$attempt['attempt_kind']==='recovery'&&$outcome==='uncommitted'&&$pointer&&$op['activation_fence']!==null
            &&in_array($op['status'],['reconciliation_required','activation_attempted','health_check_pending'],true))$permitted[]='compensate';
        if($request['operation']!=='settle'&&(!$live||!in_array($request['operation'],$permitted,true)))self::deny();
        return ['site_id'=>$lease['site_id'],'target_id'=>$lease['target_id'],'binding_key'=>$binding['binding_key'],
            'binding_version'=>$binding['binding_version'],'site_key'=>$site['site_key'],'deployment_key'=>$op['deployment_key'],
            'deployment_id'=>$lease['deployment_id'],'attempt_id'=>$lease['attempt_id'],'producer_attempt_id'=>(int)$producer['id'],
            'attempt_kind'=>$attempt['attempt_kind'],'fence_epoch'=>$lease['fence_epoch'],'operation'=>$op['operation'],'status'=>$op['status'],
            'outcome'=>$outcome,'live'=>$live,'permitted'=>$permitted,'activation_fence'=>$op['activation_fence']===null?null:(int)$op['activation_fence'],
            'release'=>SiteDeploymentContract::release($release,$site),'previous'=>$previous,
            'expires_in'=>max(0,min(strtotime($attempt['lease_expires_at'].' UTC'),strtotime($attempt['deadline_at'].' UTC'))-strtotime($now.' UTC'))];
    }
    private static function deny(): never {throw new SiteServiceException('unauthorized','Publisher authority unavailable.');}
}
