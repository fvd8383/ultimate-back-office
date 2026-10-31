<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6CFixture.php';
try {
    $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
    $e=$f->store->verifyRelease($job,$producer);
    m6c($e['disposition']==='sealed','Real files verify');
    $done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    m6c($done['job']['status']==='succeeded'&&count($f->db->tables['site_release_validations'])===2,'Real output accepted atomically');
    m6c($f->db->events('site_build_succeeded')===1&&$f->builds===1,'One build and result event');
    $path=$f->releasePath($job);$inventory=SiteArtifactFiles::inventory($path);
    m6c(count($inventory)===8,'Three pages, deduplicated image, CSS, marker, two metadata files');
    $home=file_get_contents($path.'/public/index.html');$service=file_get_contents($path.'/public/roof-cleaning/index.html');$contact=file_get_contents($path.'/public/contact/index.html');
    foreach([[$home,'Home only','Service only'],[$service,'Service only','Contact only'],[$contact,'Contact only','Home only']]as[$html,$yes,$no]){
        m6c(str_contains($html,$yes)&&!str_contains($html,$no)&&substr_count($html,'<main ')===1,'Distinct complete page');
        foreach(['/','/contact','/roof-cleaning']as$url)m6c(str_contains($html,'href="'.$url.'"'),'Complete navigation');
        m6c(str_contains($html,'Synthetic Café')&&str_contains($html,'Content-Security-Policy'),'Public identity and static CSP');
    }
    m6c(str_contains($contact,'name="email" required disabled')&&str_contains($contact,'Ask us')&&!str_contains($contact,'<form'),'Inert configured form');
    m6c(str_contains($service,'/assets/'.hash_file('sha256',$f->root.'/uploads/approved.png').'.png'),'Nested page asset resolves at content hash');
    foreach($inventory as$file){$bytes=file_get_contents($path.'/'.$file);m6c(!str_contains($bytes,'CANARY')&&!str_contains($bytes,'SENTINEL')&&!str_contains($bytes,'/uploads/'),'Private canaries and locators excluded: '.$file);}
    $manifest=$e['manifest'];$marker=json_decode(file_get_contents($path.'/public/_release.json'),true);
    m6c(array_keys($marker)===['contract_version','public_payload_hash','release_key','site_key'],'Minimal marker');
    m6c(CanonicalJson::hash($manifest)===$e['manifest_hash'],'Manifest exact canonical hash');
    m6c(CanonicalJson::hash($manifest['files'])===$manifest['artifact_hash'],'Artifact hash includes marker');
    m6c(CanonicalJson::hash(array_values(array_filter($manifest['files'],fn($x)=>$x['relative_path']!=='public/_release.json')))===$marker['public_payload_hash'],'Payload hash excludes marker');
    m6c(hash_file('sha256',$path.'/metadata/validation.json')===$manifest['validation_hash'],'Validation exact-byte hash');
    m6c($manifest['created_at']===$job['created_at']&&!isset($manifest['manifest_hash']),'Stable job time and no self hash');
    m6c($manifest['unavailable_capabilities']===['leadhub_forms','production_publication','deployed_http_headers'],'No publishable profile claims');
    m6c($f->store->openVerifiedFile($job,$producer,'public/index.html')===$home,'Verified file access');
    m6cDeny(fn()=>$f->store->openVerifiedFile($job,$producer,'metadata/manifest.json'));
    $f->db->read->base->approvals[700]['revoked_at']='2026-10-17';
    $history=$f->db->request(2);m6c($history['existing']&&$f->builds===1,'Historical replay does not prepare or inspect assets');
    m6c($f->db->read->base->sites[10]['current_published_revision_id']===null,'No publication pointer');

    // Same fixed job/input/producer, separate private store: every byte reproduces.
    $f2=new WebsitePlatformM6CFixture();$source=$f2->source();
    $f2->builder->build($job,$producer,$source,fn()=>null);
    $e2=$f2->store->verifyRelease($job,$producer);
    m6c($e2['manifest_hash']===$e['manifest_hash']&&$e2['artifact_hash']===$e['artifact_hash'],'Same-job deterministic bytes');
    $f3=new WebsitePlatformM6CFixture();$retryProducer=$producer;$retryProducer['id']=999;$retryProducer['attempt_number']=2;
    $f3->builder->build($job,$retryProducer,$f3->source(),fn()=>null);$retry=$f3->store->verifyRelease($job,$retryProducer);
    m6c($retry['manifest_hash']===$e['manifest_hash'],'Different producer attempt preserves same-job package metadata');
    $different=$job;$different['release_key']='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';$different['job_key']='cccccccc-cccc-4ccc-8ccc-cccccccccccc';$different['id']=91;
    $differentProducer=$producer;$differentProducer['build_job_id']=91;
    $f2->builder->build($different,$differentProducer,$source,fn()=>null);$other=$f2->store->verifyRelease($different,$differentProducer);
    m6c($other['artifact_hash']!==$e['artifact_hash']&&$other['manifest_hash']!==$e['manifest_hash'],'Different reserved release changes markers, not input identity');
    m6c(CanonicalJson::encode(['z'=>null,'a'=>['é',1]])==='{"a":["é",1],"z":null}','Canonical golden bytes: order, UTF-8, integer and NULL');
    m6c(hash('sha256',CanonicalJson::encode(['z'=>null,'a'=>['é',1]]))==='38e60ef7eb38c1c8bcdc86e1d9ebf0841aaa82485a25d76760bbb3cbd30420ab','Canonical golden digest independently checked with .NET SHA256');
    echo "Website platform M6C artifact: $assertions assertions passed.\n";
} finally { WebsitePlatformM6CFixture::cleanup(); }
