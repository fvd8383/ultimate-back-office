<?php
declare(strict_types=1);
/** Read-only original-worker observation. No publisher session, lease or receipt. */
require_once __DIR__.'/WebsitePlatformM6ENativeSetup.php';
require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentService.php';
require_once dirname(__DIR__,2).'/private/classes/SitePublisherArtifactExport.php';

function m6eServingSource(LocalSiteArtifactStore $store,string $root,array $release,array $summary,int $uid,Closure $pulse):array {
    $exporter=new Ubo\M6E\SitePublisherArtifactExport($store,$root,$uid);
    $entries=$exporter->prepare($release,$pulse);
    $manifest='';
    // Original metadata bytes through the same descriptor/inode-checked exporter
    // used by UBT1. Never reconstruct a manifest from the handoff's expectations.
    $exporter->sendFile(0,static function(string $part)use(&$manifest):void {
        m6eNeed(strlen($manifest)+strlen($part)<=SiteArtifactFiles::MAX_MANIFEST,'source_manifest_bound');$manifest.=$part;
    },$pulse);
    $exporter->finish($pulse);
    $m=SiteBuildContract::decode($manifest,SiteArtifactFiles::MAX_MANIFEST);
    m6eNeed($entries[0]['relative_path']==='metadata/manifest.json'
        &&hash('sha256',$manifest)===$summary['manifest_sha256']
        &&$release['manifest_hash']===$summary['manifest_sha256']
        &&$release['artifact_hash']===$summary['artifact_sha256']
        &&$release['release_id']===$summary['release_id']&&$release['release_key']===$summary['release_key']
        &&$m['file_count']===$summary['file_count']
        &&CanonicalJson::encode(array_slice($entries,2))===CanonicalJson::encode($m['files']),'source_binding');
    return ['release'=>$release,'summary'=>$summary,'manifest_base64'=>base64_encode($manifest)];
}

function m6eServingSources(array $admission):array {
    m6eNeed(posix_geteuid()===2000,'source_worker');
    $fixture=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',1048576),1048576);
    m6eNeed(count($fixture['releases'])===2&&$fixture['run_id']===$admission['run_id']&&$fixture['unpublished']===true,'source_fixture');
    $config=m6eBuildConfig($admission);$db=Database::connection();
    $store=new LocalSiteArtifactStore($config['work_root'],$config['artifact_root'],Closure::fromCallable([SiteBuildRuntime::class,'guard']));
    $pulse=static function()use($admission):void {m6eNeed(time()<$admission['expires_at'],'source_expired');SiteDeploymentService::guardExternal();};
    $result=[];m6ePristine($db,$fixture['site_id'],$fixture['target_id']);
    foreach($fixture['releases'] as $expected) {
        $summary=m6eObserveRelease($db,$config,$expected['release_id']);
        m6eNeed(CanonicalJson::encode($summary)===CanonicalJson::encode($expected),'source_original_producer');
        $row=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id=:id AND site_id=:site',
            ['id'=>$expected['release_id'],'site'=>$fixture['site_id']]);m6eNeed($row!==null,'source_release');
        $release=SiteDeploymentContract::release($row,['site_key'=>$fixture['site_key']]);
        $result[]=m6eServingSource($store,$config['artifact_root'],$release,$summary,2000,$pulse);
    }
    m6ePristine($db,$fixture['site_id'],$fixture['target_id']);
    $out=['pid'=>getmypid(),'uid'=>posix_geteuid(),'sources'=>$result];
    m6eNeed(strlen(CanonicalJson::encode($out))<2097152,'source_output_bound');return $out;
}
