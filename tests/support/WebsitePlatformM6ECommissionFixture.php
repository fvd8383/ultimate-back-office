<?php
declare(strict_types=1);
/** Shared LOCAL control fixture: original M6C builder/store, fake lifecycle SQL. */
require_once __DIR__.'/WebsitePlatformM6CFixture.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';
require_once __DIR__.'/WebsitePlatformM6ECommissionServing.php';

function m6eLocalServingFixture(bool $onlyA=false):array {
    $f=new WebsitePlatformM6CFixture();$base=$f->db->read->base;$pdf=M6CSupplementPdf::bytes();
    file_put_contents($f->root.'/uploads/approved.pdf',$pdf);chmod($f->root.'/uploads/approved.pdf',0600);
    $base->siteAssets[2]=array_replace($base->siteAssets[1],['id'=>2,'asset_type'=>'document','storage_key'=>'/uploads/approved.pdf',
        'checksum_sha256'=>hash('sha256',$pdf),'mime_type'=>'application/pdf','byte_size'=>strlen($pdf)]);
    $base->sites[11]=array_replace($base->sites[10],['id'=>11,'site_key'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);
    $base->siteAssets[3]=array_replace($base->siteAssets[1],['id'=>3,'site_id'=>11]);
    $base->revisions[99]=array_replace($base->revisions[100],['id'=>99,'revision_number'=>1]);
    $base->revisions[100]['revision_number']=2;$base->revisions[100]['materiality']='non_material';$base->approvals[700]['revision_id']=99;
    $built=[];
    foreach($onlyA?['#123456']:['#123456','#654321'] as $color) {
        $base->revisions[100]['lifecycle_status']='draft';
        $composition=WebsitePlatformM6CFixture::composition($base->revisions[100]['snapshot_hash']);
        $composition['theme']['primary_color']=$color;$composition['pages'][0]['sections'][]=M6CSupplementPdf::section(2);
        useWebsitePlatformM3ServiceDatabase($base);SiteCompositionManager::replaceDraftComposition(1,100,$composition);
        $base->revisions[100]['lifecycle_status']='internally_approved';(new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);
        [$request,$claim,$receipt]=$f->run();SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
        [$job,$producer]=$f->rows($claim);$verified=$f->store->verifyRelease($job,$producer);
        $rows=array_values(array_filter($f->db->tables['site_releases'],static fn($r)=>(int)$r['build_job_id']===(int)$job['id']));
        m6eNeed(count($rows)===1,'local_release');$row=$rows[0];
        $built[]=['row'=>$row,'job'=>$job,'producer'=>$producer,'verified'=>$verified,
            'release'=>SiteDeploymentContract::release($row,$base->sites[10]),
            'summary'=>m6eReleaseSummary($row,$job,$producer,$verified)];
    }
    return [$f,$built,$pdf];
}

/** Private owned-child stdio only; actual sender, no deployment session/DB rows. */
function m6eLocalServingTransfer():void {
    [$f,$built,$pdf]=m6eLocalServingFixture();$sources=[];
    foreach($built as $r)$sources[]=m6eServingSource($f->store,realpath($f->root.'/sealed'),$r['release'],$r['summary'],
        lstat($f->root)['uid'],static function():void{});
    $fixture=['version'=>1,'run_id'=>str_repeat('b',32),'site_id'=>10,'site_key'=>$built[0]['release']['site_key'],
        'target_id'=>20,'requester_id'=>1,'releases'=>array_column($built,'summary')]+m6eAssetSets(array_column($built,'summary'))+
        ['foreign_asset'=>['site_id'=>11,'asset_id'=>3,'sha256'=>hash_file('sha256',$f->root.'/uploads/approved.png')],'unpublished'=>true];
    fwrite(STDOUT,CanonicalJson::encode(['fixture'=>$fixture,'sources'=>$sources])."\n");fflush(STDOUT);
    $stream=fopen('m6etransfer://owned-child','r+');
    try {
        for($n=0;$n<8;$n++) {
            $line=fgets(STDIN,4096);m6eNeed(is_string($line),'local_command');$command=json_decode($line,true,16,JSON_THROW_ON_ERROR);
            if($command===['stop'=>true])break;
            m6eNeed(array_keys($command)===['slot']&&in_array($command['slot'],[0,1],true),'local_slot');
            $r=$built[$command['slot']];$ready=json_decode(fgets(STDIN,4096),true,16,JSON_THROW_ON_ERROR);
            m6eNeed($ready['type']==='transfer_ready'&&$ready['transfer_version']===1,'local_ready');
            if($ready['disposition']==='receive') {
                $exporter=new Ubo\M6E\SitePublisherArtifactExport($f->store,realpath($f->root.'/sealed'),lstat($f->root)['uid']);
                (new SitePublisherTransfer($stream,hrtime(true)+30000000000,$ready['transfer_id'],$ready['sequence']))->send($exporter,$r['release']);
            } else m6eNeed($ready['disposition']==='retained','local_disposition');
            // Parent acknowledgement prevents any next message being mistaken
            // for trailing transfer bytes. It is NOT a deployment receipt.
            m6eNeed(json_decode(fgets(STDIN,4096),true,8,JSON_THROW_ON_ERROR)===['observed'=>true],'local_observed');
        }
        m6eNeed(!isset($f->db->tables['site_deployments']),'local_no_deployments');
    } finally {fclose($stream);}
}
