<?php
declare(strict_types=1);
require_once __DIR__.'/SitePublisherArtifactExport.php';

/** Stage-only UBT1 frames inside the authenticated session. Never a public upload API. */
final class SitePublisherTransfer
{
    public const CONTROL=4096;
    public const CHUNK=65536;
    public const CONTROL_TOTAL=(SiteArtifactFiles::MAX_FILES*2+2)*self::CONTROL;
    private int $controls=0;
    private int $frames=0;
    private int $dataFrames=0;
    private int $dataBytes=0;
    public function __construct(private mixed $stream,private int $deadline,private string $id,private int $sequence) {}

    public static function fail(string $phase='frame'): never
    {throw new SiteServiceException('conflict','Publisher transfer unresolved: '.$phase.'.');}

    public function pulse(): void
    {
        SiteDeploymentService::guardExternal();$left=$this->deadline-hrtime(true);
        if($left<=0)self::fail('deadline');
        $seconds=intdiv($left,1000000000);$microseconds=intdiv($left%1000000000,1000);
        if(!stream_set_timeout($this->stream,$seconds,max(1,$microseconds)))self::fail('transport');
    }
    public function write(string $bytes): void
    {
        $offset=0;
        while($offset<strlen($bytes)){$this->pulse();$n=@fwrite($this->stream,substr($bytes,$offset,self::CHUNK));if(!$n)self::fail('write');$offset+=$n;}
        $this->pulse();
    }
    public function read(int $length): string
    {
        if($length<1||$length>self::CHUNK)self::fail();$data='';
        while(strlen($data)<$length){$this->pulse();$chunk=@fread($this->stream,$length-strlen($data));if($chunk===false||$chunk==='')self::fail('read');$data.=$chunk;}
        $this->pulse();return $data;
    }
    private function count(string $kind,int $length): void
    {
        if(++$this->frames>SiteArtifactFiles::MAX_FILES*4+intdiv(SiteArtifactFiles::MAX_PACKAGE,self::CHUNK)+64)self::fail('budget');
        if($kind==='C'){$this->controls+=$length;if($length>self::CONTROL||$this->controls>self::CONTROL_TOTAL)self::fail('budget');}
        else{$this->dataFrames++;$this->dataBytes+=$length;if($length>self::CHUNK
            ||$this->dataBytes>SiteArtifactFiles::MAX_PACKAGE+SiteArtifactFiles::MAX_MANIFEST+SiteArtifactFiles::MAX_VALIDATION)self::fail('budget');}
    }
    private function frame(string $kind,string $bytes): void
    {$this->count($kind,strlen($bytes));$this->write('UBT1'.$kind.pack('N',strlen($bytes)));$this->write($bytes);}
    private function control(array $value): void
    {$this->frame('C',CanonicalJson::encode(['version'=>1,'transfer_id'=>$this->id,'sequence'=>$this->sequence]+$value));}
    private function ack(int $index): void
    {
        $header=$this->read(9);$length=unpack('N',substr($header,5))[1];
        if(substr($header,0,5)!=='UBT1C'||$length<1||$length>self::CONTROL)self::fail('ack');
        $this->count('C',$length);$a=SiteBuildContract::decode($this->read($length),self::CONTROL);
        SiteDeploymentContract::keys($a,['version','transfer_id','sequence','type','index']);
        if($a!==CanonicalJson::canonicalize(['version'=>1,'transfer_id'=>$this->id,'sequence'=>$this->sequence,'type'=>'file_ok','index'=>$index]))self::fail('ack');
    }

    public function send(Ubo\M6E\SitePublisherArtifactExport $exporter,array $release): void
    {
        $pulse=Closure::fromCallable([$this,'pulse']);
        $entries=$exporter->prepare($release,$pulse);$public=0;$metadata=0;
        foreach($entries as $i=>$entry){
            $size=$entry['byte_size'];if(!is_int($size)||$size<1)self::fail('size');
            if($i<2)$metadata+=$size;else $public+=$size;
            if($public>SiteArtifactFiles::MAX_PACKAGE||$metadata>SiteArtifactFiles::MAX_MANIFEST+SiteArtifactFiles::MAX_VALIDATION)self::fail('budget');
            $this->control(['type'=>'file','index'=>$i,'path'=>$entry['relative_path'],'length'=>$size,'sha256'=>$entry['sha256']]);
            $exporter->sendFile($i,fn(string $data)=>$this->frame('D',$data),$pulse);
            $this->ack($i); // No queue/pipeline; receiver has checked this exact file.
        }
        $exporter->finish($pulse); // Original verifier and stable inventory before completion, not EOF.
        $this->control(['type'=>'end','files'=>count($entries),'public_bytes'=>$public,'metadata_bytes'=>$metadata,
            'data_frames'=>$this->dataFrames,'manifest_hash'=>$release['manifest_hash']]);
    }
}
