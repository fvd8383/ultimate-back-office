<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CFixture.php';
// Test-only export of a real M6C artifact into bounded private child output.
try {
    $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();
    SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    [$job,$producer]=$f->rows($claim);$e=$f->store->verifyRelease($job,$producer);
    if($e['disposition']!=='sealed')throw new RuntimeException('fixture');
    $release=array_values($f->db->tables['site_releases'])[0];$files=[];
    foreach(SiteArtifactFiles::inventory($f->releasePath($job)) as $p)$files[$p]=base64_encode(file_get_contents($f->releasePath($job).'/'.$p));
    echo CanonicalJson::encode(['release'=>['site_id'=>(int)$release['site_id'],'site_key'=>$e['manifest']['site_key'],
        'release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],'artifact_hash'=>$release['artifact_hash'],
        'manifest_hash'=>$release['manifest_hash'],'build_profile'=>$release['build_profile']],'files'=>$files]);
}finally{WebsitePlatformM6CFixture::cleanup();}
