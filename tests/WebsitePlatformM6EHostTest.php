<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/classes/SiteDeploymentService.php';
$assertions=0;
$check=static function(bool $ok,string $why)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($why);};
$deny=static function(Closure $call,string $why)use($check):void{try{$call();}catch(Throwable){$check(true,$why);return;}$check(false,$why);};
$b=['site_id'=>10,'site_key'=>'11111111-1111-4111-8111-111111111111','target_id'=>20,'binding_key'=>'fixture-staging','binding_version'=>1,
    'candidate'=>['host'=>'candidate.example.test','ip'=>'127.0.0.1','port'=>443],'active'=>['host'=>'active.example.test','ip'=>'127.0.0.1','port'=>443],
    'ca_file'=>'/etc/ubo-site-publisher/ca.pem','client_certificate'=>'/etc/ubo-site-publisher/client.pem','client_key'=>'/etc/ubo-site-publisher/key.pem'];
$c=['version'=>1,'environment'=>'staging','worker_id'=>'fixture-worker','worker_uid'=>1001,'publisher_uid'=>1002,'authority_uid'=>1003,
    'helper_socket'=>'/run/ubo-site-publisher/publish.sock','application_config_sha256'=>str_repeat('d',64),
    'artifact_work_root'=>'/srv/fixture-work','artifact_root'=>'/srv/fixture-artifact','bindings'=>[$b]];
$r=['version'=>1,'profile'=>'m6e-host-v1','worker_config_sha256'=>str_repeat('a',64),'verified_at'=>100,'expires_at'=>200,'gates'=>[],
    'installed_files'=>array_fill_keys(['/usr/local/lib/ubo-site-publisher/ubo_site_publish.py','/usr/local/sbin/ubo-site-publish',
        '/etc/ubo-site-publisher/helper.json','/etc/ubo-site-publisher/apache.conf','/etc/ubo-site-publisher/authority.json',
        '/usr/local/lib/ubo-site-publisher/ubo-site-authority.php'],str_repeat('b',64))];
foreach(SitePublisherHostContract::GATES as $g)$r['gates'][$g]=['result'=>'pass','evidence_sha256'=>str_repeat('c',64)];
SitePublisherHostContract::validate($c,$r,str_repeat('a',64),150);$check(true,'Pure valid receipt baseline');
foreach(['production','identity','flag','path','endpoint','binding'] as $case){$bad=$c;
    match($case){'production'=>$bad['environment']='production','identity'=>$bad['publisher_uid']=1001,'flag'=>$bad['enabled']=true,
        'path'=>$bad['artifact_root']='/srv/../root','endpoint'=>$bad['bindings'][0]['active']['host']='https://evil.invalid/',
        'binding'=>$bad['bindings'][]=$b};
    $deny(fn()=>SitePublisherHostContract::validate($bad,$r,str_repeat('a',64),150),$case);
}
foreach(['missing','expired','hash','gate','installed'] as $case){$bad=$r;
    if($case==='missing')unset($bad['gates']['ownership']);
    elseif($case==='expired')$bad['expires_at']=149;
    elseif($case==='hash')$bad['worker_config_sha256']=str_repeat('0',64);
    elseif($case==='gate')$bad['gates']['filesystem']['result']='unknown';
    else $bad['installed_files']=[];
    $deny(fn()=>SitePublisherHostContract::validate($c,$bad,str_repeat('a',64),150),$case);
}
$class=new ReflectionClass(SitePublisherHostContract::class);$host=$class->newInstanceWithoutConstructor();$class->getConstructor()->invoke($host,$c);
$t=['site_id'=>10,'target_id'=>20,'environment'=>'staging','publisher_key'=>'apache-digitalocean-v1','binding_key'=>'fixture-staging','binding_version'=>1];
$check($host->resolve($t)===$b,'Fixed binding resolution');
foreach(['site_id'=>11,'target_id'=>21,'binding_version'=>2,'publisher_key'=>'stdClass','environment'=>'production','binding_key'=>'unknown'] as $k=>$v){$bad=$t;$bad[$k]=$v;$deny(fn()=>$host->resolve($bad),'Isolated '.$k);}
$deny(fn()=>SitePublisherHostContract::load(),'No protected desktop host contract');
$deny(fn()=>SiteDeploymentRuntime::boot(),'No arbitrary boot entry');
$deny(fn()=>SitePublisherFactory::forTarget($t),'Default factory denied');
$deny(fn()=>(new SiteDeploymentRuntime())->assertReady(['id'=>20]+$t,['build_profile'=>SiteBuildContract::PROFILE]),'Default readiness denied');
$deny(fn()=>SitePublisherHostContract::protectedFile(__FILE__,100000),'Unsafe desktop file denied');
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';
$db=new M6DDatabase();$session=new SitePublisherSession($host);
$publisher=new ApacheDigitalOceanSitePublisher($session,$t,SiteHttpHealthChecker::forBinding($b));
$db->beginTransaction();$queries=count($db->queries);
try {
    foreach([
        fn()=>$publisher->stageRelease([],$t,[]),fn()=>$publisher->activateRelease([],[],[]),
        fn()=>$publisher->restoreRelease([],[],[]),fn()=>$publisher->inspectCurrentRelease($t),
        fn()=>$publisher->healthCheck([],$t,[]),fn()=>$publisher->reconcileExternalState([],$t,[]),
    ] as $call) {
        try{$call();$check(false,'Missing SQL guard');}
        catch(SiteServiceException $e){$check($e->classification()==='conflict','Actual SQL guard precedes unbound-session admission');}
    }
    $check(count($db->queries)===$queries&&$db->inTransaction(),'External guards do not query or settle caller transaction');
}finally{$db->rollBack();}
echo "M6E host: $assertions assertions PASS (pure admission fixtures, real default-deny; no installed host verification).\n";
