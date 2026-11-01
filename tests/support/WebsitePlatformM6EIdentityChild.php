<?php
declare(strict_types=1);
/** AUTHORED ONLY. Separate preprovisioned Linux identity, controlled PDO snapshot, real exporter/session. */
require_once __DIR__.'/WebsitePlatformM6DFixture.php';
require_once dirname(__DIR__,2).'/private/classes/SitePublisherAuthority.php';
try {
    if(PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()===0
        ||count($argv)!==3||!in_array($argv[1],['worker','authority'],true)||!in_array($argv[2],['valid','interrupted'],true))SitePublisherHostContract::deny();
    // Different root-owned 0640 files/groups: worker cannot read the observer's snapshot.
    // They contain controlled PDO rows, not a native database acceptance assertion.
    $snapshot=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-identity/'.$argv[1].'.json',2097152),2097152);
    if($snapshot['profile']!=='m6e-identity-rows-v1'||$snapshot['uid']!==posix_geteuid()||$snapshot['expires_at']<=time())SitePublisherHostContract::deny();
    $case=$snapshot['cases'][$argv[2]];$db=new M6DDatabase();
    $db->inner->tables=$case['build_tables'];$db->tables=$case['deployment_tables'];
    $db->inner->read->base->sites=$case['sites'];$db->inner->now=$case['now'];
    if($argv[1]==='authority'){
        $line=fgets(STDIN,16385);if($line===false||!str_ends_with($line,"\n"))SitePublisherHostContract::deny();
        $r=SiteBuildContract::decode($line,16384);
        $db->beginTransaction();try{$a=SitePublisherAuthority::observe($db,$r,$case['binding']);$db->commit();}
        catch(Throwable $primary){$db->rollBack();throw $primary;}
        fwrite(STDOUT,CanonicalJson::encode($a)."\n");exit(0);
    }
    $lease=$case['lease'];$entry=(new ReflectionMethod(SiteDeploymentService::class,'entry'))->invoke(null,$lease);
    $target=SiteDeploymentContract::target($entry['target']);$release=SiteDeploymentContract::release($entry['release'],$entry['site']);
    $admission=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-identity/admission.json',65536),65536);
    if($argv[2]==='valid'&&$admission['probes']['source']!==$snapshot['artifact_root'].'/'.$release['site_key'].'/'.$release['release_key'].'/metadata/manifest.json')SitePublisherHostContract::deny();
    $context=(new ReflectionMethod(SiteDeploymentService::class,'context'))->invoke(null,$entry,$lease,'stage');
    $class=new ReflectionClass(SitePublisherHostContract::class);$host=$class->newInstanceWithoutConstructor();
    $class->getConstructor()->invoke($host,['worker_id'=>'m6d-fixture','bindings'=>[$case['binding']]]);
    $store=new LocalSiteArtifactStore($snapshot['artifact_work_root'],$snapshot['artifact_root'],Closure::fromCallable([SiteDeploymentService::class,'guardExternal']));
    $exporter=new Ubo\M6E\SitePublisherArtifactExport($store,$snapshot['artifact_root'],posix_geteuid());
    // Test-only stdio duplex, relayed over a real private Unix connection made by this UID.
    class M6EIdentityStream {
        public mixed $context;
        public function stream_open(string $p,string $m,int $o,?string &$opened):bool{return true;}
        public function stream_write(string $data):int{$n=fwrite(STDOUT,$data);fflush(STDOUT);return $n;}
        public function stream_read(int $count):string{return fread(STDIN,1);}
        public function stream_eof():bool{return feof(STDIN);}
        public function stream_stat():array{return [];}
        public function stream_set_option(int $o,int $a,int $b):bool{return true;} // Parent bounds pipe waits by the same absolute deadline.
    }
    stream_wrapper_register('m6eidentity',M6EIdentityStream::class);
    $session=new SitePublisherSession($host,$exporter);$session->bind($lease);
    (new ReflectionProperty(SitePublisherSession::class,'stream'))->setValue($session,fopen('m6eidentity://fixture','r+'));
    SiteDeploymentContract::receipt($session->request('stage',$target,['release'=>$release,'context'=>$context]),$context,'stage');
    $session->close();
}catch(Throwable){fwrite(STDERR,"identity_child_unresolved\n");exit(9);}
