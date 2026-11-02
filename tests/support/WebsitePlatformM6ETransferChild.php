<?php
declare(strict_types=1);

namespace Ubo\M6E {
    // Test-only Unix permission model. Native file contents, descriptors and identities remain real.
    function lstat(string $path): array|false {
        $s=\lstat($path);if($s===false)return false;
        if(PHP_OS_FAMILY==='Windows')$s['mode']=($s['mode']&0170000)|(($s['mode']&0170000)===0040000?0700:0600);
        if(str_replace('\\','/',$GLOBALS['m6e_bad_owner']??'')===str_replace('\\','/',$path))$s['uid']++;
        return $s;
    }
    function fstat(mixed $h): array|false {
        $s=\fstat($h);if($s!==false&&PHP_OS_FAMILY==='Windows')$s['mode']=($s['mode']&0170000)|0600;
        return $s;
    }
}
namespace {
require_once __DIR__.'/WebsitePlatformM6CFixture.php';
require_once __DIR__.'/WebsitePlatformM6DFixture.php';
require_once dirname(__DIR__,2).'/private/classes/SitePublisherAuthority.php';

/** Only stdio of this owned child. Never an installed socket/runtime bypass. */
final class M6ETransferStream {
    public mixed $context;
    public static int $written=0;
    public static $hook=null;
    public function stream_open(string $p,string $m,int $o,?string &$opened):bool{return true;}
    public function stream_write(string $data):int {
        if(self::$hook!==null&&str_starts_with($data,'UBT1')){$hook=self::$hook;self::$hook=null;$hook();}
        $n=fwrite(STDOUT,substr($data,0,113));fflush(STDOUT);self::$written+=$n;return $n;
    }
    public function stream_read(int $count):string{return fread(STDIN,1);}
    public function stream_eof():bool{return feof(STDIN);}
    public function stream_stat():array{return [];}
    public function stream_set_option(int $option,int $a,int $b):bool{return true; /* Windows stdio timeout modeled; parent owns the absolute child deadline. */}
}
stream_wrapper_register('m6etransfer',M6ETransferStream::class);
$mode=$argv[1]??'valid';$exit=0;
try {
    if($mode==='two-release') {
        require_once __DIR__.'/WebsitePlatformM6ECommissionFixture.php';
        m6eLocalServingTransfer();WebsitePlatformM6CFixture::cleanup();exit(0);
    }
    $f=new WebsitePlatformM6CFixture();
    if($mode==='pdf'){
        require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';
        $pdf=str_replace('%%EOF',str_repeat("% bounded synthetic padding\n",3000).'%%EOF',M6CSupplementPdf::bytes());
        $base=$f->db->read->base;
        file_put_contents($f->root.'/uploads/approved.pdf',$pdf);chmod($f->root.'/uploads/approved.pdf',0600);
        $base->siteAssets[2]=array_replace($base->siteAssets[1],['id'=>2,'asset_type'=>'document','storage_key'=>'/uploads/approved.pdf',
            'checksum_sha256'=>hash('sha256',$pdf),'mime_type'=>'application/pdf','byte_size'=>strlen($pdf)]);
        $base->revisions[100]['lifecycle_status']='draft';
        useWebsitePlatformM3ServiceDatabase($base);
        $composition=WebsitePlatformM6CFixture::composition($base->revisions[100]['snapshot_hash']);
        $composition['pages'][0]['sections'][]=M6CSupplementPdf::section(2);
        SiteCompositionManager::replaceDraftComposition(1,100,$composition);
        $base->revisions[100]['lifecycle_status']='internally_approved';
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);
    }
    [$request,$claim,$receipt]=$f->run();SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    [$job,$producer]=$f->rows($claim);$path=$f->releasePath($job);$build=$f->db;
    $db=new M6DDatabase();$db->inner=$build;
    $release=array_values($build->tables['site_releases'])[0];$db->runtime->artifacts=[(int)$release['id']=>$release];
    $db->request();$lease=$db->claim()['lease'];
    $db->tables['site_deployment_targets'][20]['publisher_key']='apache-digitalocean-v1';
    $e=(new ReflectionMethod(SiteDeploymentService::class,'entry'))->invoke(null,$lease);
    $target=SiteDeploymentContract::target($e['target']);$release=SiteDeploymentContract::release($e['release'],$e['site']);
    $context=(new ReflectionMethod(SiteDeploymentService::class,'context'))->invoke(null,$e,$lease,'stage');
    $binding=array_intersect_key($target,array_flip(['site_id','target_id','binding_key','binding_version']))+['site_key'=>$release['site_key']];
    $r=['version'=>1,'sequence'=>1,'operation'=>'stage','target'=>$target,'lease'=>$lease,'payload'=>['release'=>$release,'context'=>$context]];
    $db->beginTransaction();try{$authority=SitePublisherAuthority::observe($db,$r,$binding);$db->commit();}catch(Throwable $x){$db->rollBack();throw $x;}
    $files=[];foreach(SiteArtifactFiles::inventory($path) as $name)$files[$name]=['sha256'=>hash_file('sha256',$path.'/'.$name),'bytes'=>filesize($path.'/'.$name)];
    // Private test bootstrap contains synthetic authority/lease DTOs, not source locators or bytes.
    fwrite(STDOUT,CanonicalJson::encode(['release'=>$release,'target'=>$target,'binding'=>$binding,'authority'=>$authority,'files'=>$files,
        'limits'=>[SiteArtifactFiles::MAX_FILES,SiteArtifactFiles::MAX_PACKAGE,SiteArtifactFiles::MAX_MANIFEST,SiteArtifactFiles::MAX_VALIDATION,
            SiteArtifactFiles::MAX_ASSET,SiteArtifactFiles::MAX_HTML]])."\n");fflush(STDOUT);
    $class=new ReflectionClass(SitePublisherHostContract::class);$host=$class->newInstanceWithoutConstructor();
    $class->getConstructor()->invoke($host,['worker_id'=>$lease['worker_id'],'bindings'=>[$binding]]);
    $exporter=new Ubo\M6E\SitePublisherArtifactExport($f->store,realpath($f->root.'/sealed'),lstat($path)['uid']);
    if($mode==='source-corrupt')M6ETransferStream::$hook=static fn()=>file_put_contents($path.'/public/index.html','PRIVATE-SOURCE-CANARY');
    if($mode==='source-replaced')M6ETransferStream::$hook=static function()use($path):void {
        $file=$path.'/public/index.html';$data=file_get_contents($file);rename($file,$file.'.old');file_put_contents($file,$data);chmod($file,0600);
    };
    if($mode==='source-extra')M6ETransferStream::$hook=static fn()=>file_put_contents($path.'/private-secret','PRIVATE-SOURCE-CANARY');
    if($mode==='source-owner')$GLOBALS['m6e_bad_owner']=$path.'/public/index.html';
    if($mode==='producer')$build->tables['site_build_attempts'][$producer['id']]['site_id']=999;
    $session=new SitePublisherSession($host,$exporter);$session->bind($lease);
    (new ReflectionProperty(SitePublisherSession::class,'stream'))->setValue($session,fopen('m6etransfer://owned-child','r+'));
    $result=$session->request('stage',$target,['release'=>$release,'context'=>$context]);
    SiteDeploymentContract::receipt($result,$context,'stage');
    if($mode==='replay'){
        file_put_contents($path.'/public/index.html','PRIVATE-SOURCE-CANARY');
        $result=$session->request('stage',$target,['release'=>$release,'context'=>$context]);
        SiteDeploymentContract::receipt($result,$context,'stage');
    }
    $session->close();
    fwrite(STDERR,"transfer_child_complete\n");
}catch(Throwable $error){
    // Safe result vocabulary; never exception text, argv tokens, files or driver diagnostics.
    fwrite(STDERR,"transfer_child_unresolved\n");$exit=9;
}finally{WebsitePlatformM6CFixture::cleanup();}
exit($exit);
}
