<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
require_once dirname(__DIR__).'/private/classes/SiteDeploymentService.php';
require_once dirname(__DIR__).'/private/classes/SiteHttpHealthChecker.php';
$assertions=0;
$check=static function(bool $ok,string $why)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($why);};
try {
    $f=new WebsitePlatformM6CFixture();[$request,$claim,$receipt]=$f->run();[$job,$producer]=$f->rows($claim);
    $sealed=$f->store->verifyRelease($job,$producer);$m=$sealed['manifest'];$files=[];
    foreach($m['files'] as $entry)$files[$entry['relative_path']]=['body'=>file_get_contents($f->releasePath($job).'/'.$entry['relative_path']),
        'headers'=>['content-type'=>$entry['mime_type'],'cache-control'=>'no-store'],'status'=>200,
        'tls_verified'=>true,'destination_verified'=>true,'cache_request'=>true];
    $target=['site_id'=>10,'target_id'=>20,'environment'=>'staging','publisher_key'=>'apache-digitalocean-v1','binding_key'=>'fixture-staging','binding_version'=>1];
    $binding=['site_id'=>10,'site_key'=>$m['site_key'],'target_id'=>20,'binding_key'=>'fixture-staging','binding_version'=>1,
        'candidate'=>['host'=>'candidate.example.test','ip'=>'127.0.0.1','port'=>443],
        'active'=>['host'=>'active.example.test','ip'=>'127.0.0.1','port'=>443]];
    $expected=['site_key'=>$m['site_key'],'release_key'=>$job['release_key'],'artifact_hash'=>$m['artifact_hash'],'release_id'=>1,'deployment_id'=>100];
    $context=['attempt_id'=>101,'fence_epoch'=>1,'phase'=>'active'];
    $origin=array_intersect_key($m,array_flip(['site_key','release_key','artifact_hash','public_payload_hash','files']))+
        ['manifest_hash'=>$sealed['manifest_hash'],'no_current_link'=>false];
    $now=1793563200.123456;$calls=0;$fault=null;
    $transport=static function(array $endpoint,string $path,int $limit,float $remaining,array $b)use(&$calls,&$fault,&$now,$files):array {
        $calls++;$now+=0.01;
        $file=$path==='/'?'public/index.html':(str_ends_with($path,'/')?'public'. $path.'index.html':'public'.$path);
        $r=$files[$file]??throw new RuntimeException('unexpected path '.$path);
        if($fault!==null)$r=$fault($r,$path,$now);
        return $r;
    };
    $make=static function()use($binding,$transport,&$now):SiteHttpHealthChecker {
        $class=new ReflectionClass(SiteHttpHealthChecker::class);$checker=$class->newInstanceWithoutConstructor();
        $class->getConstructor()->invoke($checker,$binding,$transport,static function()use(&$now):float{return $now;});return $checker;
    };
    $pass=$make()->check($expected,$target,$context,$origin);
    $check($pass['result']==='pass','Real M6C bytes with synthetic HTTPS observations pass');
    $check($calls>=5&&$calls<=8,'One observation, bounded file requests, no hidden retry');
    $check($pass['marker_digest']&&$pass['origin_integrity'],'Distinct marker/full artifact identities checked');
    $faults=[
        'wrong-site'=>static function($r,$p){if($p==='/')$r['body']=str_replace('site-release','other-release',$r['body']);return $r;},
        'redirect'=>static function($r){$r['status']=302;$r['headers']['location']='https://private.invalid';return $r;},
        'tls'=>static function($r){$r['tls_verified']=false;return $r;},
        'destination'=>static function($r){$r['destination_verified']=false;return $r;},
        'cache'=>static function($r){$r['headers']['age']='4';return $r;},
        'cache-hit'=>static function($r){$r['headers']['x-cache']='HIT';return $r;},
        'no-store'=>static function($r){$r['headers']['cache-control']='private';return $r;},
        'marker'=>static function($r,$p){if($p==='/_release.json')$r['body']='{}';return $r;},
        'mime'=>static function($r,$p){if(str_ends_with($p,'.css'))$r['headers']['content-type']='text/html';return $r;},
        'asset-digest'=>static function($r,$p){if(str_ends_with($p,'.png'))$r['body']='bad';return $r;},
        'login'=>static function($r,$p){if($p==='/')$r['body']='<html><title>Login</title><form>Password</form></html>';return $r;},
        'size'=>static function($r,$p){if($p==='/_release.json')$r['body']=str_repeat('x',1025);return $r;},
        'timeout'=>static function($r,$p,&$clock){$clock+=31;return $r;},
        'network'=>static function(){throw new RuntimeException('PRIVATE-URL-AND-CREDENTIAL-CANARY');},
    ];
    foreach($faults as $name=>$fault){$result=$make()->check($expected,$target,$context,$origin);$check($result['result']==='fail',$name.' rejected after valid baseline');
        $safe=CanonicalJson::encode($result);$check(!str_contains($safe,'PRIVATE-')&&!str_contains($safe,'private.invalid')&&!str_contains($safe,'Synthetic'),'safe evidence '.$name);}
    $fault=null;
    foreach(['site_key','release_key','artifact_hash'] as $k){$bad=$expected;$bad[$k]=str_repeat('0',64);$check($make()->check($bad,$target,$context,$origin)['result']==='fail','wrong expected '.$k);}
    $bad=$origin;$bad['artifact_hash']=str_repeat('0',64);$check($make()->check($expected,$target,$context,$bad)['result']==='fail','independent origin mismatch');
    $checker=$make();$one=$checker->check($expected,$target,$context,$origin);$now+=1;$two=$checker->check($expected,$target,$context,$origin);
    $check($one['result']==='pass'&&$two['result']==='pass'&&$one['check_key']!==$two['check_key'],'Separated independent active observations');
    $now+=31;$before=$calls;$check($checker->check($expected,$target,$context,$origin)['result']==='fail'&&$calls===$before,'Shared 30 second phase budget');
    $absent=$expected;foreach(['release_id','release_key','artifact_hash','deployment_id'] as $k)$absent[$k]=null;
    $fault=static function($r){$r['status']=503;$r['body']='Temporarily unavailable';return $r;};
    $context['phase']='rollback';$abs=$make()->check($absent,$target,$context,['no_current_link'=>true]);
    $check($abs['result']==='pass'&&!$abs['home_marker']&&$abs['no_current_link'],'Separate 503 absence profile');
    $check($make()->check($absent,$target,$context,['no_current_link'=>false])['result']==='fail','HTTP absence cannot prove missing link');
    $context['phase']='active';$check($make()->check($absent,$target,$context,['no_current_link'=>true])['result']==='fail','Absence cannot satisfy active success');
    $context['phase']='rollback';$fault=static function($r){$r['status']=503;$r['body']='site-release public_payload_hash release_key';return $r;};
    $check($make()->check($absent,$target,$context,['no_current_link'=>true])['result']==='fail','503 marker masquerade rejected');
    $fault=static function($r,$p){$r['status']=503;$r['body']=$p==='/_release.json'?'site-release':'Temporarily unavailable';return $r;};
    $check($make()->check($absent,$target,$context,['no_current_link'=>true])['result']==='fail','Marker endpoint cannot hide release evidence in absence profile');
    $f->db->beginTransaction();try{$make()->check($expected,$target,$context,$origin);$check(false,'SQL guard');}catch(SiteServiceException){$check(true,'SQL guard before transport');}finally{$f->db->rollBack();}
    echo "M6E health: $assertions assertions PASS (real M6C bytes; synthetic HTTP/TLS/cache/clock; no network or Apache PASS).\n";
}finally{WebsitePlatformM6CFixture::cleanup();}
