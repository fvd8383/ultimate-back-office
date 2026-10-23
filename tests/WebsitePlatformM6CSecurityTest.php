<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6CFixture.php';
$linkCases=0;
try {
    $f=new WebsitePlatformM6CFixture();$source=$f->source();$projection=$f->prepareInput($source);
    $json=CanonicalJson::encode($projection);
    m6c(!str_contains($json,'CANARY')&&!str_contains($json,'SENTINEL')&&!str_contains($json,'storage_key'),'Explicit recursive projection');
    foreach(['Synthetic Café','+1 555 123 4567','hello@example.test','Approved description','Roof care','Test City']as$text)m6c(str_contains($json,$text),'Intended public facts preserved');
    foreach(['rights_classification'=>'unknown','site_id'=>20,'business_id'=>60,'rights_current'=>0,'mime_type'=>'text/html','byte_size'=>1,'checksum_sha256'=>str_repeat('f',64),'storage_key'=>'/uploads/../secret']as$field=>$value){
        $bad=$source;$bad['asset_metadata'][0][$field]=$value;m6cDeny(fn()=>$f->prepareInput($bad));
    }
    $bad=$source;foreach($bad['asset_metadata']as&$a)$a['source']='spaces';unset($a);m6cDeny(fn()=>$f->prepareInput($bad));
    $bad=$source;$bad['composition']['assets'][0]['byte_size']=20971521;m6cDeny(fn()=>$f->prepareInput($bad));
    $bad=$source;$bad['composition']['pages']=array_fill(0,101,$bad['composition']['pages'][0]);m6cDeny(fn()=>SitePublicRenderContext::project($bad));
    $bad=$source;$bad['composition']['pages'][1]['slug']='';m6cDeny(fn()=>SitePublicRenderContext::project($bad));
    $bad=$source;$bad['composition']['pages'][0]['sections'][0]['configuration']['private_instruction']='CANARY';m6cDeny(fn()=>SitePublicRenderContext::project($bad));
    $bad=$source;$bad['composition']['legacy_compatibility']=true;m6cDeny(fn()=>SitePublicRenderContext::project($bad));
    foreach(['../x','/abs','C:/abs','x\\y','%2e%2e/x','a//b','a/../b','.htaccess','CON','aux.txt','nul','lpt1.pdf','a.','a:stream']as$p)m6cDeny(fn()=>SiteArtifactFiles::path($p));
    foreach(['assets','metadata','PUBLIC','_release.json','index','a.php','x?y','x#y','%61']as$p)m6cDeny(fn()=>SiteArtifactFiles::route($p));
    foreach(['<svg xmlns="http://www.w3.org/2000/svg"/>','<?php echo 1; ?>','not an image']as$bytes){$a=['sha256'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes),'mime_type'=>'image/png'];m6cDeny(fn()=>SiteArtifactValidator::asset($bytes,$a));}
    m6cDeny(fn()=>SiteArtifactValidator::extension('image/svg+xml'));
    foreach(["%PDF-1.4\n/JavaScript (bad)\n%%EOF","%PDF-1.4\n/ObjStm\n%%EOF"]as$pdf)m6cDeny(fn()=>SiteArtifactValidator::asset($pdf,['sha256'=>hash('sha256',$pdf),'byte_size'=>strlen($pdf),'mime_type'=>'application/pdf']));
    [$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);$path=$f->releasePath($job);
    foreach(SiteArtifactFiles::inventory($path)as$file){
        $original=file_get_contents($path.'/'.$file);file_put_contents($path.'/'.$file,$original.'X');
        m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Tampered file: '.$file);
        file_put_contents($path.'/'.$file,$original);
        unlink($path.'/'.$file);m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Missing file: '.$file);file_put_contents($path.'/'.$file,$original);
    }
    foreach(['public/extra.txt','public/payload.php','public/.htaccess','metadata/extra.json']as$file){file_put_contents($path.'/'.$file,'extra');m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Extra file: '.$file);unlink($path.'/'.$file);}
    mkdir($path.'/public/empty');m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Unexpected empty directory');rmdir($path.'/public/empty');
    $html=file_get_contents($path.'/public/index.html');$input=SiteBuildContract::decode($job['input_manifest_json']);
    $identity=['site_key'=>$source['site']['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1];
    $htmlCheck=new ReflectionMethod(SiteArtifactValidator::class,'html');
    foreach(['<script src="/x"></script>','<iframe src="https://evil.test"></iframe>','<img src="https://evil.test/x">',
        '<p onclick="bad()">Bad</p>','<a href="javascript:bad()">bad</a>','<svg onload="bad()"></svg>',
        '<form action="/legacy" method="post"></form>','<meta http-equiv="refresh" content="0;url=https://evil.test">',
        '<link rel="stylesheet" href="//evil.test/x">','<img src="/missing.png">','<div style="display:none">bad</div>']as$payload){
        $bad=str_replace('</body>',$payload.'</body>',$html);
        m6cDeny(fn()=>$htmlCheck->invoke(null,$bad,$input['public_composition']['pages'][0],$input,$identity,SiteArtifactValidator::expected($input)));
    }
    $bad=str_replace(SiteComponentRenderers::escape(SiteArtifactValidator::CSP),"default-src *",$html);
    m6cDeny(fn()=>$htmlCheck->invoke(null,$bad,$input['public_composition']['pages'][0],$input,$identity,SiteArtifactValidator::expected($input)));
    $bad=str_replace('/contact','/missing',$html);
    m6cDeny(fn()=>$htmlCheck->invoke(null,$bad,$input['public_composition']['pages'][0],$input,$identity,SiteArtifactValidator::expected($input)));
    $proof=$f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'.seal.json';$original=file_get_contents($proof);
    file_put_contents($proof,str_replace('"producer_attempt_id":'.$producer['id'],'"producer_attempt_id":999',$original));
    m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Journal binds original producer');file_put_contents($proof,$original);
    $foreign=$producer;$foreign['id']=999;m6c($f->store->verifyRelease($job,$foreign)['disposition']==='unknown','Receipt hint cannot select another producer');
    $assetPath=$path.'/public/assets/'.hash_file('sha256',$f->root.'/uploads/approved.png').'.png';
    if(@link($assetPath,$f->root.'/hardlink.png')){$linkCases++;m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Hardlink rejected');unlink($f->root.'/hardlink.png');}
    if(@symlink($f->root.'/uploads/approved.png',$path.'/public/link.png')){$linkCases++;m6c($f->store->verifyRelease($job,$producer)['disposition']==='unknown','Symlink rejected');unlink($path.'/public/link.png');}
    m6c($f->store->verifyRelease($job,$producer)['disposition']==='sealed','Original bytes restored after fault tests');
    m6cDeny(fn()=>new LocalSiteArtifactStore(dirname(__DIR__),realpath($f->root.'/sealed'),fn()=>null));
    m6cDeny(fn()=>new LocalSiteArtifactStore(realpath($f->root.'/work'),realpath($f->root.'/work'),fn()=>null));
    $f->db->beginTransaction();try{m6cDeny(fn()=>SiteBuildRuntime::guard());}finally{$f->db->rollBack();}
    echo "Website platform M6C security: $assertions assertions passed; $linkCases native local link cases executed (of 2).\n";
} finally {WebsitePlatformM6CFixture::cleanup();}
