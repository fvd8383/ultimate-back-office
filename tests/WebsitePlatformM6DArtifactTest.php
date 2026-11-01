<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
require_once dirname(__DIR__).'/private/classes/SiteDeploymentArtifacts.php';
try {
    $f=new WebsitePlatformM6CFixture(); [$request,$claim,$receipt]=$f->run();
    SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    $release=array_values($f->db->tables['site_releases'])[0];
    $e=SiteDeploymentArtifacts::verify($f->store,$release);
    m6c($e['result']==='pass' && $e['artifact_hash']===$release['artifact_hash'],'Real immutable M6C store integration');
    $f->db->read->users[1]['status']='inactive';
    m6c(SiteDeploymentArtifacts::verify($f->store,$release)===$e,'Build requester loss is not deployment artifact invalidation');
    $f->db->beginTransaction(); m6cDeny(fn()=>SiteDeploymentArtifacts::verify($f->store,$release)); $f->db->rollBack();
    [$job,$producer]=$f->rows($claim); $path=$f->releasePath($job).'/public/index.html';
    file_put_contents($path,'tampered'); m6cDeny(fn()=>SiteDeploymentArtifacts::verify($f->store,$release));
    echo "M6D artifact: $assertions assertions PASS (actual private files, unchanged M6C store; fake PDO).\n";
} finally { WebsitePlatformM6CFixture::cleanup(); }
