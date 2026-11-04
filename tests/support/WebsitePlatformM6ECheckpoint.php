<?php
declare(strict_types=1);
/** Fixture-only observation. No snapshot, grant, deployment request or runtime admission. */
require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentService.php';
require_once dirname(__DIR__,2).'/private/classes/SitePublisherArtifactExport.php';

function m6eCheckpointScalar(PDO $db,string $sql):mixed {
    $s=$db->prepare($sql);$s->execute();return $s->fetchColumn();
}
function m6eCheckpointDatabase(PDO $db):array {
    return ['schema'=>m6eCheckpointScalar($db,'SELECT DATABASE()'),
        'server_uuid'=>m6eCheckpointScalar($db,'SELECT @@server_uuid'),
        'account'=>m6eCheckpointScalar($db,'SELECT CURRENT_USER()')];
}
function m6eLifecycleBinding(string $run):array {
    m6eNeed(preg_match('/^[a-f0-9]{32}$/D',$run)===1,'checkpoint_run');
    return ['schema'=>'ubo_m6e_lifecycle_'.$run,'control_root'=>'/run/ubo-m6e-lifecycle/'.$run,
        'storage_root'=>'/var/lib/ubo-m6e-lifecycle/'.$run,'worker'=>'m6e_lifecycle_worker',
        'observer'=>'m6e_lifecycle_observer','restore'=>'UNIMPLEMENTED','admission'=>'UNIMPLEMENTED'];
}
/** Same locked source gate used by requestDeployment; transaction commits before artifact I/O. */
function m6eCheckpointObserve(PDO $db,int $releaseId,array $expected):array {
    m6eNeed(!$db->inTransaction(),'checkpoint_committed_boundary');
    $db->beginTransaction();
    try {
        $identity=m6eCheckpointDatabase($db);m6eNeed($identity===$expected,'checkpoint_database');
        $r=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id=:id FOR UPDATE',['id'=>$releaseId]);
        m6eNeed($r!==null,'checkpoint_release');
        $source=SiteRevisionManager::lockDeploymentEligibility($db,$r,false);
        $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id FOR UPDATE',['id'=>(int)$r['build_job_id']]);
        m6eNeed($job!==null&&$job['status']==='succeeded'&&$job['completed_at']!==null,'checkpoint_completed_job');
        m6eNeed((int)$job['business_id']===$source['business_id']&&(int)$job['association_id']===$source['association_id'],'checkpoint_original_association');
        $producer=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id FOR UPDATE',['id'=>(int)$job['current_attempt_id']]);
        m6eNeed($producer!==null&&$producer['status']==='succeeded'&&$producer['completed_at']!==null
            &&$producer['attempt_kind']==='execution'&&(int)$producer['build_job_id']===(int)$job['id']
            &&(int)$job['revision_id']===(int)$r['source_revision_id']&&$job['snapshot_hash']===$r['source_snapshot_hash']
            &&(int)$job['site_id']===(int)$r['site_id']&&(int)$producer['site_id']===(int)$r['site_id'],'checkpoint_producer');
        $s=$db->prepare('/* site-m6:build-successors */ SELECT id, materiality, lifecycle_status FROM site_revisions
            WHERE site_id=:site_id AND revision_number>:revision_number ORDER BY revision_number FOR UPDATE');
        $s->execute(['site_id'=>(int)$r['site_id'],'revision_number'=>(int)$source['revision']['revision_number']]);
        m6eNeed($s->fetchAll()===[]&&$source['site']['current_published_revision_id']===null,'checkpoint_pre_b');
        $absence=[];
        // The native fixture has no target yet. Whole-schema zeroes include every tenant and lease/attempt.
        foreach(['site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks'] as $table){
            $absence[$table]=(int)m6eCheckpointScalar($db,'SELECT COUNT(*) FROM '.$table);
            m6eNeed($absence[$table]===0,'checkpoint_no_operations');
        }
        $s=$db->prepare('/* site-m6:build-approvals */ SELECT * FROM site_approvals WHERE site_id=:site_id ORDER BY id FOR UPDATE');
        $s->execute(['site_id'=>(int)$r['site_id']]);$all=$s->fetchAll();$approvals=[];
        foreach($source['approvals'] as $kind=>$id){
            $rows=array_values(array_filter($all,static fn($v)=>(int)$v['id']===(int)$id));
            m6eNeed(count($rows)===1,'checkpoint_approval');$approvals[$kind]=$rows[0];
        }
        $result=['database'=>$identity,'source'=>$source,'approvals'=>$approvals,'release'=>$r,'job'=>$job,'producer'=>$producer,
            'absence'=>$absence,'newer_revisions'=>[]];
        m6eNeed($db->commit(),'checkpoint_commit');return $result;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function m6eCaptureCheckpoint(PDO $db,LocalSiteArtifactStore $store,string $root,int $uid,int $releaseId,array $identity,array $a,string $execution):array {
    $started=time();m6eNeed($started<$a['expires_at']&&in_array($execution,['native-pre-b','controlled-local'],true),'checkpoint_expired');
    $before=m6eCheckpointObserve($db,$releaseId,$identity);
    m6eNeed($before['job']['builder_code_sha']===$a['source_sha'],'checkpoint_source');
    $verified=$store->verifyRelease($before['job'],$before['producer']);
    $summary=m6eReleaseSummary($before['release'],$before['job'],$before['producer'],$verified);
    $release=SiteDeploymentContract::release($before['release'],$before['source']['site']);
    $export=new Ubo\M6E\SitePublisherArtifactExport($store,$root,$uid);
    $pulse=static function()use($a,$db):void {m6eNeed(!$db->inTransaction()&&time()<$a['expires_at'],'checkpoint_io_boundary');};
    $entries=$export->prepare($release,$pulse);$manifest='';
    $export->sendFile(0,static function(string $bytes)use(&$manifest):void {$manifest.=$bytes;
        m6eNeed(strlen($manifest)<=SiteArtifactFiles::MAX_MANIFEST,'checkpoint_manifest_bound');},$pulse);
    // finish independently reopens and hashes every immutable original file, including public assets.
    $export->finish($pulse);
    m6eNeed(hash('sha256',$manifest)===$summary['manifest_sha256']
        &&CanonicalJson::encode(array_slice($entries,2))===CanonicalJson::encode($verified['manifest']['files']),'checkpoint_artifacts');
    $after=m6eCheckpointObserve($db,$releaseId,$identity);
    m6eNeed(CanonicalJson::encode($before)===CanonicalJson::encode($after),'checkpoint_changed_during_capture');
    $code=[];foreach(['WebsitePlatformM6ENativeSetup.php','WebsitePlatformM6ECheckpoint.php'] as $name)
        $code['tests/support/'.$name]=hash_file('sha256',__DIR__.'/'.$name);
    $result=['profile'=>'m6e-pre-b-source-v1','execution'=>$execution,'creation_point'=>'after-A-completeBuildSuccess-before-B-createRevision',
        'binding'=>array_intersect_key($a,array_flip(['run_id','source_sha','machine_id','boot_id'])),
        'started_at'=>$started,'captured_at'=>time(),'expires_at'=>$a['expires_at'],'source_files'=>$code,
        'observed'=>$before,'observed_sha256'=>CanonicalJson::hash($before),'summary'=>$summary,
        'manifest_base64'=>base64_encode($manifest),'files'=>$verified['manifest']['files'],
        'database_snapshot'=>'UNIMPLEMENTED','deployment_authority'=>'NONE','lifecycle'=>m6eLifecycleBinding($a['run_id'])];
    m6eNeed($result['captured_at']<$a['expires_at']&&strlen(CanonicalJson::encode($result))<2097152,'checkpoint_bound');return $result;
}
/** Only called synchronously by the fixed native builder, before it can create B. */
function m6eNativeCheckpoint(PDO $builder,array $config,array $a,int $releaseId):void {
    m6eNeed(!$builder->inTransaction()&&posix_geteuid()===2000,'checkpoint_builder');
    $identity=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/database-identity.json',8192),true,32,JSON_THROW_ON_ERROR);
    $expected=['schema'=>'ubo_m6e_'.$a['run_id'],'server_uuid'=>$identity['server_uuid'],'account'=>'m6e_worker@127.0.0.1'];
    m6eNeed($identity['schema']===$expected['schema']&&m6eCheckpointDatabase($builder)===$expected,'checkpoint_original_database');
    $c=Database::config();m6eNeed($c['DB_HOST']==='127.0.0.1'&&(int)$c['DB_PORT']===3306&&$c['DB_NAME']===$expected['schema']
        &&$c['DB_USER']==='m6e_worker'&&$c['DB_SSL_MODE']==='REQUIRED'&&$c['DB_SSL_CA']==='/etc/ubo-site-publisher/tls/ca.pem','checkpoint_connection');
    // Fresh session proves build/source rows were committed, not merely visible to their writer.
    $observer=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$expected['schema'].';charset=utf8mb4',$c['DB_USER'],$c['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::MYSQL_ATTR_SSL_CA=>$c['DB_SSL_CA']]);
    $observer->exec("SET SESSION time_zone='+00:00'");
    m6eNeed(m6eCheckpointScalar($observer,'SELECT CONNECTION_ID()')!==m6eCheckpointScalar($builder,'SELECT CONNECTION_ID()'),'checkpoint_independent_session');
    $s=$observer->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");$tls=$s->fetch();m6eNeed(is_array($tls)&&($tls['Value']??'')!=='','checkpoint_tls');
    $result=m6eCaptureCheckpoint($observer,m6eArtifactStore($config),$config['artifact_root'],2000,$releaseId,$expected,$a,'native-pre-b');
    $raw=CanonicalJson::encode($result);$path='/run/ubo-m6e-fixture/worker/pre-b-source.json';
    m6eWrite($path,$raw,2000,0600);
    m6eNeed(file_get_contents($path)===$raw,'checkpoint_write_readback');
}
/** Read-only final-fixture control: no request, target reservation or artifact rewrite. */
function m6eFinalAIneligible(PDO $db,int $releaseId):void {
    m6eNeed(!$db->inTransaction(),'checkpoint_final_boundary');$db->beginTransaction();$denied=false;
    try {
        $r=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id=:id FOR UPDATE',['id'=>$releaseId]);
        m6eNeed($r!==null,'checkpoint_final_release');
        try {SiteRevisionManager::lockDeploymentEligibility($db,$r,false);}
        catch(SiteServiceException $e){if($e->classification()!=='invalid_transition')throw $e;$denied=true;}
        m6eNeed($denied,'checkpoint_final_A_must_reject');m6eNeed($db->commit(),'checkpoint_final_commit');
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
