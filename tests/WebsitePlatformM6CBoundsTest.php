<?php

declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
try{
    $f=new WebsitePlatformM6CFixture();$source=$f->source();$f->db->request();$claim=$f->db->claim();[$job,$producer]=$f->rows($claim);
    $identity=['site_key'=>$source['site']['site_key'],'release_key'=>$job['release_key'],'revision_number'=>1];
    $candidate=$f->store->createCandidate($job,$producer,$identity);
    m6cDeny(fn()=>$f->store->writeFile($candidate,'public/index.html',str_repeat('x',1048577)));
    m6cDeny(fn()=>$f->store->writeFile($candidate,'metadata/manifest.json',str_repeat('x',1048577)));
    m6cDeny(fn()=>$f->store->writeFile($candidate,'metadata/validation.json',str_repeat('x',16385)));
    $input=SiteBuildContract::decode($job['input_manifest_json']);$asset=$input['ordered_asset_digests'][0];
    $assetPath='public/assets/'.$asset['sha256'].'.png';
    m6cDeny(fn()=>$f->store->writeFile($candidate,$assetPath,str_repeat('x',20971521)));
    foreach(['public/index.html/x','public/index.html/../x','public/INDEX.html','public/run.cgi','metadata/manifest.json:stream']as$path)m6cDeny(fn()=>$f->store->writeFile($candidate,$path,'x'));
    // Fault doubles drive counters to their edge without writing 100 MiB or 2,000 files.
    $property=new ReflectionProperty(LocalSiteArtifactStore::class,'candidates');$state=$property->getValue($f->store);
    // Deterministically retain a PHP reference, regardless of platform/runtime reference unwrapping.
    $alias=&$state[$candidate];$property->setValue($f->store,$state);
    m6c(ReflectionReference::fromArrayElement($state,$candidate)!==null,'Reference-alias case is exercised');
    $baseline=['bytes'=>(int)$alias['bytes'],'files'=>[]];
    foreach($alias['files']as$name=>$owned)$baseline['files'][$name]=(bool)$owned;
    $lock=$alias['lock'];
    $counters=function(int $bytes,array $files)use($property,$f,$candidate):void{
        $live=$property->getValue($f->store);$live[$candidate]['bytes']=$bytes;$live[$candidate]['files']=$files;
        $property->setValue($f->store,$live);
    };
    foreach([[104857600,[]],[0,array_fill_keys(range(1,2000),true)]]as[$bytes,$files]){
        try{
            $counters($bytes,$files);
            m6cDeny(fn()=>$f->store->writeFile($candidate,'public/index.html','x'));
            m6c($baseline===['bytes'=>0,'files'=>[]],'Injected edge cannot mutate independent saved counters');
            m6c(!file_exists($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'/public'),'Each rejected edge writes no public content');
        }finally{$counters($baseline['bytes'],$baseline['files']);}
        $restored=$property->getValue($f->store)[$candidate];
        m6c($restored['bytes']===0&&$restored['files']===[],'Counters restored before next case');
        m6c($restored['lock']===$lock&&is_resource($lock),'Live lock/ownership state is preserved');
    }
    m6c(!file_exists($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'/public'),'Rejected bounds create no public files');
    $f->store->writeFile($candidate,'public/index.html','owned');
    m6c(file_get_contents($f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'].'/public/index.html')==='owned','Valid write after both rejected edges');
    m6cDeny(fn()=>$f->store->writeFile($candidate,'public/index.html','replacement'));
    $f->store->finish($candidate,true);
    $bad=$source;$bad['composition']['assets']=array_fill(0,2001,$bad['composition']['assets'][0]);m6cDeny(fn()=>$f->assets->descriptors($bad));
    $bad=$source;$bad['composition']['assets']=[];
    for($i=0;$i<6;$i++)$bad['composition']['assets'][]=array_replace($source['composition']['assets'][0],['usage_key'=>'large'.$i,'byte_size'=>20971520]);
    m6cDeny(fn()=>$f->assets->descriptors($bad));
    $input['ordered_asset_digests']=[];for($i=0;$i<2000;$i++)$input['ordered_asset_digests'][]=['sha256'=>hash('sha256',(string)$i),'mime_type'=>'image/png'];
    m6cDeny(fn()=>SiteArtifactValidator::expected($input));
    // Interrupted candidate: unknown files prohibit even ownership-scoped cleanup.
    $next=$producer;$next['id']=999;$candidate=$f->store->createCandidate($job,$next,$identity);$f->store->writeFile($candidate,'public/index.html','owned');
    $directory=$f->root.'/work/'.$job['job_key'].'/attempt-999';file_put_contents($directory.'/unknown','orphan');
    m6cDeny(fn()=>$f->store->finish($candidate,true));m6c(file_get_contents($directory.'/unknown')==='orphan'&&is_file($directory.'/public/index.html'),'Ambiguous cleanup deletes nothing');
    // Worker evidence is pre-resolved: in-lock identity checks perform no storage work.
    $io=$f->io;$f->db->beginTransaction();try{$f->workerIdentity('execution');m6c($f->io===$io,'In-lock worker identity performs no I/O');}finally{$f->db->rollBack();}
    echo "Website platform M6C bounds: $assertions assertions passed (package/file counter edges simulated).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
