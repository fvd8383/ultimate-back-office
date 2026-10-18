<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';

// Metadata-only projection fixtures. Hashes name synthetic inventory entries, not verified bytes.
function admissionSource(WebsitePlatformM6CFixture $f,int $pages,int $assets,?int $distinct=null):array {
    $source=$f->source();$page=$source['composition']['pages'][0];$page['sections']=[$page['sections'][0]];
    $source['composition']['pages']=[];
    for($i=0;$i<$pages;$i++)$source['composition']['pages'][]=array_replace($page,
        ['page_key'=>'p'.$i,'slug'=>$i===0?'':'p'.$i,'page_type'=>$i===0?'home':'standard','sort_order'=>$i+1]);
    $source['composition']['theme']['configuration']['layouts']['site_header']['configuration']['logo_usage_key']=null;
    $asset=$source['composition']['assets'][0];$source['composition']['assets']=[];
    for($i=0;$i<$assets;$i++)$source['composition']['assets'][]=array_replace($asset,
        ['usage_key'=>'a'.$i,'checksum_sha256'=>hash('sha256','inventory-'.($i%($distinct??$assets)))]);
    return $source;
}

// Author through the existing composition owner, preserving eligibility, schemas and stored hashes.
// Use 80/100 pages with one hero each, respecting component cardinality and 25 usages per section.
function admissionInstall(WebsitePlatformM6CFixture $f,int $pages,int $assets):void {
    $base=$f->db->read->base;$input=WebsitePlatformM6CFixture::composition($base->revisions[100]['snapshot_hash']);
    $template=$input['pages'][0];$input['pages']=[];$input['theme']['assets']=[];
    $input['theme']['configuration']['layouts']['site_header']['configuration']['logo_usage_key']=null;
    $next=0;
    for($p=0;$p<$pages;$p++){
        $page=array_replace($template,['page_key'=>'p'.$p,'slug'=>$p===0?'':'p'.$p,
            'page_type'=>$p===0?'home':'standard','sort_order'=>$p+1,'sections'=>[]]);
        $count=min((int)ceil($assets/$pages),$assets-$next);$sections=max(1,(int)ceil($count/25));
        for($s=0;$s<$sections;$s++){
            $section=WebsitePlatformM6CFixture::selection('hero',['headline'=>'Inventory'])
                +['section_key'=>'s'.$s,'sort_order'=>$s+1,'assets'=>[]];
            for($a=0;$a<min(25,$count-$s*25);$a++){
                $i=$next++;$id=$i+2;
                $base->siteAssets[$id]=array_replace($base->siteAssets[1],['id'=>$id,
                    'storage_key'=>'/uploads/inventory-'.$i.'.png','checksum_sha256'=>hash('sha256','inventory-'.$i)]);
                $section['assets'][]=['asset_id'=>$id,'usage_key'=>'a'.$i];
            }
            $page['sections'][]=$section;
        }
        $input['pages'][]=$page;
    }
    if($next!==$assets)throw new RuntimeException('Fixture asset distribution');
    $base->revisions[100]['lifecycle_status']='draft';useWebsitePlatformM3ServiceDatabase($base);
    SiteCompositionManager::replaceDraftComposition(1,100,$input);
    $base->revisions[100]['lifecycle_status']='internally_approved';
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);
}

function admissionStopReads(WebsitePlatformM6CFixture $f,int &$reads):void {
    (new ReflectionProperty(SiteArtifactAssetResolver::class,'guard'))->setValue($f->assets,
        function()use($f,&$reads):never{
            if($f->db->inTransaction())throw new LogicException('Asset validation inside SQL');
            $reads++;throw new SiteServiceException('conflict','inventory-byte-boundary');
        });
}
function admissionPreparation(callable $call):string {
    try{$call();return 'prepared';}
    catch(SiteArtifactException $e){return $e->failureCode;}
    catch(RuntimeException $e){if($e->getMessage()!=='inventory-byte-boundary')throw $e;return 'byte-boundary';}
}

// Assertions: source/inventory gates only unless a real build is explicitly named below.
try{
    foreach([[1,1995,true],[1,1996,false],[2,1994,true],[2,1995,false],[100,1896,true],[100,1897,false],[1,0,true]]as[$pages,$assets,$accept]){
        $f=new WebsitePlatformM6CFixture();$source=admissionSource($f,$pages,$assets);
        $projection=SitePublicRenderContext::project($source)+['ordered_asset_digests'=>$f->assets->descriptors($source)];
        $identity=SiteBuildContract::input($source,$projection,$f->identity);
        m6c(strlen($identity['input_manifest_json'])<1048576,'Separate input-size/schema gate passes '.$pages.'/'.$assets);
        if($accept){$expected=SiteArtifactValidator::expected($projection);m6c(count($expected)+2===$pages+$assets+4,'Complete inventory includes private metadata');}
        else m6cDeny(fn()=>SiteArtifactValidator::expected($projection));
        $reads=0;admissionStopReads($f,$reads);$result=admissionPreparation(fn()=>$f->builder->prepareInput($source));
        m6c($result===($accept?($assets===0?'prepared':'byte-boundary'):'artifact_invalid'),'Actual preparation boundary '.$pages.'/'.$assets);
        m6c($reads===($accept&&$assets>0?1:0),'Oversized inventory fails before asset-byte validation');
        m6c(SiteArtifactFiles::inventory($f->root.'/work')===[]&&SiteArtifactFiles::inventory($f->root.'/sealed')===[],'Metadata gate creates no output');
    }
    $f=new WebsitePlatformM6CFixture();$source=admissionSource($f,1,1997,1);
    $projection=SitePublicRenderContext::project($source)+['ordered_asset_digests'=>$f->assets->descriptors($source)];
    m6c(count($projection['ordered_asset_digests'])===1997&&count(SiteArtifactValidator::expected($projection))+2===6,'Usage descriptors deduplicate to one physical asset path');
    m6c(strlen(SiteBuildContract::input($source,$projection,$f->identity)['input_manifest_json'])<1048576,'Deduplication preserves bounded input');
    $reads=0;admissionStopReads($f,$reads);
    m6c(admissionPreparation(fn()=>$f->builder->prepareInput($source))==='byte-boundary'&&$reads===1,'Deduplicated inventory passes admission only, not byte integrity');
    $source=admissionSource($f,1,1998,1);$reads=0;
    m6c(admissionPreparation(fn()=>$f->builder->prepareInput($source))==='artifact_invalid'&&$reads===0,'Separate descriptor bound is unchanged even for duplicate paths');
    $projection['public_facts']['oversized']=str_repeat('x',1048576);
    m6cDeny(fn()=>SiteBuildContract::input($source,$projection,$f->identity));

    foreach([[80,1917],[100,1897]]as[$pages,$assets]){
        $f=new WebsitePlatformM6CFixture();admissionInstall($f,$pages,$assets);$source=$f->source();
        m6c(count($source['composition']['pages'])===$pages&&count($source['composition']['assets'])===$assets,'Actual source eligibility passes with bounded sections/assets');
        $projection=SitePublicRenderContext::project($source)+['ordered_asset_digests'=>$f->assets->descriptors($source)];
        m6c(strlen(SiteBuildContract::input($source,$projection,$f->identity)['input_manifest_json'])<1048576,'Eligible source satisfies independent input limit');
        $before=$f->db->snapshot();$queries=count($f->db->queries);$reads=0;admissionStopReads($f,$reads);
        m6c(admissionPreparation(fn()=>$f->db->request())==='artifact_invalid'&&$reads===0,'Actual new request denies inventory without asset reads');
        m6c($f->db->snapshot()===$before&&$f->db->tables['site_build_jobs']===[]&&$f->db->tables['site_build_attempts']===[],'No job, attempt, event, budget or recovery allocation');
        m6c($f->db->events('site_build_requested')===0&&$f->db->events('site_build_started')===0,'No request/start events');
        m6c(count(array_filter(array_slice($f->db->queries,$queries),fn($q)=>preg_match('/^(INSERT|UPDATE|DELETE) /i',trim($q))))===0,'Request performs no SQL mutations');
        m6c(count(scandir($f->root.'/work'))===2&&count(scandir($f->root.'/sealed'))===2&&$f->builds===0,'No candidate, journal, lock file, seal or execution');
    }
    // Small real-filesystem build: duplicate usages share one output; public file_count excludes metadata.
    $f=new WebsitePlatformM6CFixture();[, $claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
    $done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);$e=$f->store->verifyRelease($job,$producer);
    m6c($e['disposition']==='sealed'&&$e['manifest']['file_count']===6&&count(SiteArtifactFiles::inventory($f->releasePath($job)))===8,'Real sealed package preserves public versus total file count');
    m6c($done['release']['file_count']===6&&CanonicalJson::hash($e['manifest']['files'])===$e['artifact_hash'],'DTO and artifact hash contract unchanged');
    $before=$f->db->snapshot();$io=$f->io;$reads=0;admissionStopReads($f,$reads);
    $f->db->onSql=static function(string $sql):void{if(str_contains($sql,'site-m6:build-assets'))throw new RuntimeException('History must precede preparation eligibility');};
    $replay=$f->db->request(2);
    m6c($replay['existing']&&$replay['replayed']&&$replay['release']['id']===$done['release']['id'],'Exact authorized successful-history replay retained');
    m6c($f->db->snapshot()===$before&&$f->io===$io&&$reads===0&&$f->builds===1,'History has no new preparation/I/O/events/attempts');
    echo "Website platform M6C admission: $assertions assertions passed (metadata boundaries, eligible fake-SQL requests, one small real sealed package; no native build claim).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
