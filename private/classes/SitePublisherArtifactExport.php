<?php
declare(strict_types=1);
namespace Ubo\M6E;

require_once __DIR__.'/LocalSiteArtifactStore.php';
require_once __DIR__.'/SiteDeploymentArtifacts.php';

use Closure;
use LocalSiteArtifactStore;
use SiteArtifactFiles;
use SiteBuildContract;
use SiteBuildStore;
use SiteDeploymentContract;
use SiteDeploymentService;
use SiteServiceException;
use SiteServiceSupport;
use Throwable;

/** Worker-only adapter. Original M6C validation runs twice, never once per file. */
final class SitePublisherArtifactExport
{
    private array $job=[];
    private array $producer=[];
    private array $evidence=[];
    private array $identities=[];
    private array $entries=[];
    private string $package='';

    public function __construct(private LocalSiteArtifactStore $store,private string $root,private int $owner) {}

    private static function deny(): never
    {throw new SiteServiceException('conflict','Artifact export integrity is unresolved.');}

    public function prepare(array $release,Closure $pulse): array
    {
        $pulse(); SiteDeploymentService::guardExternal();
        // Scoped committed reads only. No filesystem work in this callback/transaction.
        [$this->job,$this->producer]=SiteServiceSupport::read(static function(object $db)use($release):array {
            $row=SiteBuildStore::one($db,'SELECT * FROM site_releases WHERE id = :id AND site_id = :site_id',
                ['id'=>$release['release_id'],'site_id'=>$release['site_id']])??self::deny();
            $site=SiteBuildStore::one($db,'SELECT id, site_key FROM sites WHERE id = :site_id LIMIT 1',
                ['site_id'=>$release['site_id']])??self::deny();
            if(SiteDeploymentContract::release($row,$site)!==$release)self::deny();
            $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id = :id AND site_id = :site_id',
                ['id'=>(int)$row['build_job_id'],'site_id'=>$release['site_id']])??self::deny();
            if($job['status']!=='succeeded'||$job['release_key']!==$release['release_key'])self::deny();
            $producer=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$job['current_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>$release['site_id']])??self::deny();
            if($producer['attempt_kind']==='recovery')$producer=SiteBuildStore::one($db,
                'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$producer['recovery_of_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>$release['site_id']])??self::deny();
            if($producer['attempt_kind']!=='execution'||(int)$producer['build_job_id']!==(int)$job['id']
                ||(int)$producer['site_id']!==$release['site_id'])self::deny();
            return [$job,$producer];
        });
        $pulse();
        $e=$this->store->verifyRelease($this->job,$this->producer,$pulse);
        $key=$release['site_key'].'/'.$release['release_key'];
        if(($e['disposition']??null)!=='sealed'||$e['artifact_hash']!==$release['artifact_hash']
            ||$e['manifest_hash']!==$release['manifest_hash']||$e['storage_backend']!=='local'||$e['storage_key']!==$key)self::deny();
        $this->evidence=$e;
        $this->package=SiteArtifactFiles::contained($this->root,$key,true);
        $this->identities=$this->snapshot($pulse);
        $metadata=[];
        foreach(['metadata/manifest.json'=>$release['manifest_hash'],'metadata/validation.json'=>$e['manifest']['validation_hash']] as $path=>$hash){
            $size=$this->identities[$this->package.'/'.$path]['size'];
            if($size<1||$size>($path==='metadata/manifest.json'?SiteArtifactFiles::MAX_MANIFEST:SiteArtifactFiles::MAX_VALIDATION))self::deny();
            $metadata[]=['relative_path'=>$path,'sha256'=>$hash,'byte_size'=>$size];
        }
        $this->entries=array_merge($metadata,$e['manifest']['files']);
        if(count($this->entries)>SiteArtifactFiles::MAX_FILES)self::deny();
        return $this->entries;
    }

    private function owned(string $path,bool $directory): array
    {
        clearstatcache(true,$path); $s=lstat($path);
        if($s===false||$s['uid']!==$this->owner||($s['mode']&0170000)!==($directory?0040000:0100000)
            ||($s['mode']&0777)!==($directory?0700:0600)||(!$directory&&$s['nlink']!==1))self::deny();
        return array_intersect_key($s,array_flip(['dev','ino','mode','uid','gid','nlink','size','mtime','ctime']));
    }

    private function snapshot(Closure $pulse): array
    {
        $names=SiteArtifactFiles::inventory($this->package,$pulse);
        $wanted=array_merge(['metadata/manifest.json','metadata/validation.json'],array_column($this->evidence['manifest']['files'],'relative_path'));
        sort($wanted,SORT_STRING);if($names!==$wanted)self::deny();
        $out=[];
        foreach($names as $name){
            $pulse();$path=SiteArtifactFiles::contained($this->package,$name);
            $out[$path]=$this->owned($path,false);
            for($dir=dirname($path);; $dir=dirname($dir)){
                if(!isset($out[$dir]))$out[$dir]=$this->owned($dir,true);
                if($dir===$this->root)break;
                if(strlen($dir)<strlen($this->root))self::deny();
            }
        }
        ksort($out,SORT_STRING);return $out;
    }

    /** Incremental original-byte read. No metadata reserialization or package buffering. */
    public function sendFile(int $index,Closure $consume,Closure $pulse): void
    {
        $pulse();$entry=$this->entries[$index]??self::deny();
        $path=SiteArtifactFiles::contained($this->package,$entry['relative_path']);
        $before=$this->identities[$path]??self::deny();
        if($before!==$this->owned($path,false)||$before['size']!==$entry['byte_size'])self::deny();
        for($dir=dirname($path);; $dir=dirname($dir)){
            if(($this->identities[$dir]??null)!==$this->owned($dir,true))self::deny();
            if($dir===$this->root)break;
        }
        $h=@fopen($path,'rb');if($h===false)self::deny();$primary=null;
        try{
            if(array_intersect_key(fstat($h),$before)!==$before)self::deny();
            $hash=hash_init('sha256');$remaining=$entry['byte_size'];
            while($remaining>0){
                $pulse();$wanted=min(65536,$remaining);$chunk='';
                while(strlen($chunk)<$wanted){$pulse();$part=@fread($h,$wanted-strlen($chunk));if($part===false||$part==='')self::deny();$chunk.=$part;}
                hash_update($hash,$chunk);$consume($chunk);$remaining-=strlen($chunk);$pulse();
            }
            if(@fread($h,1)!==''||hash_final($hash)!==$entry['sha256']
                ||array_intersect_key(fstat($h),$before)!==$before||$this->owned($path,false)!==$before)self::deny();
        }catch(Throwable $e){$primary=$e;throw $e;}
        finally{if(!@fclose($h)&&$primary===null)self::deny();}
    }

    public function finish(Closure $pulse): void
    {
        $pulse();if($this->snapshot($pulse)!==$this->identities)self::deny();
        $e=$this->store->verifyRelease($this->job,$this->producer,$pulse);
        if($e!==$this->evidence||$this->snapshot($pulse)!==$this->identities)self::deny();
        $pulse();
    }
}
