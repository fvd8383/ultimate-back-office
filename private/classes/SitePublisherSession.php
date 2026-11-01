<?php
declare(strict_types=1);
require_once __DIR__.'/SitePublisherHostContract.php';

/** One private socket per CLI invocation; the supervised helper owns flock, not PHP. */
final class SitePublisherSession
{
    private mixed $stream=null;
    private int $sequence=0;
    private int $started;
    private ?array $lease=null;
    public function __construct(private SitePublisherHostContract $host) {$this->started=hrtime(true);}
    public function bind(array $lease): void
    {
        SiteDeploymentService::guardExternal();SiteDeploymentContract::lease($lease);
        if($lease['environment']!=='staging'||$lease['worker_id']!==$this->host->worker()['worker_id'])SitePublisherHostContract::deny();
        if($this->lease!==null&&$this->lease!==$lease)throw new SiteServiceException('conflict','Publisher session already owns an attempt.');
        $this->lease=$lease;
    }
    public function request(string $operation,array $target,array $payload=[]): array
    {
        SiteDeploymentService::guardExternal();$this->host->resolve($target);
        if($this->lease===null||$target['site_id']!==$this->lease['site_id']||$target['target_id']!==$this->lease['target_id']
            ||!in_array($operation,['stage','activate','restore','inspect','probe','compensate','settle'],true))SitePublisherHostContract::deny();
        if(hrtime(true)-$this->started>300000000000)throw new SiteServiceException('conflict','Publisher session expired.');
        if(!is_resource($this->stream)) {
            $c=$this->host->configuration();$path=$c['helper_socket'];$s=@lstat($path);$parent=@lstat(dirname($path));
            if($s===false||($s['mode']&0170000)!==0140000||$s['uid']!==0||($s['mode']&0007)!==0
                ||$parent===false||$parent['uid']!==0||($parent['mode']&0022)!==0)SitePublisherHostContract::deny();
            $this->stream=@stream_socket_client('unix://'.$path,$errno,$error,2,STREAM_CLIENT_CONNECT);
            if(!is_resource($this->stream))throw new SiteServiceException('future_gate_required','Publisher supervisor unavailable.');
            // Root-owned socket and parent bind the fixed systemd Accept service. Helper also verifies SO_PEERCRED.
            stream_set_timeout($this->stream,30);stream_set_blocking($this->stream,true);
        }
        $id=++$this->sequence;
        // Only this wire object has an empty shape; nested lists retain their types.
        $line=CanonicalJson::encode(['version'=>1,'sequence'=>$id,'operation'=>$operation,'target'=>$target,'lease'=>$this->lease,
            'payload'=>$payload===[]?(object)[]:$payload])."\n";
        if(strlen($line)>16384)SiteDeploymentContract::invalid();
        $offset=0;
        while($offset<strlen($line)){$n=@fwrite($this->stream,substr($line,$offset));if(!$n)$this->broken();$offset+=$n;}
        $line=@fgets($this->stream,1048578);
        if($line===false||!str_ends_with($line,"\n")||strlen($line)>1048576)$this->broken();
        try{$r=SiteBuildContract::decode($line,1048576);}catch(Throwable){$this->broken();}
        SiteDeploymentContract::keys($r,['version','sequence','result','evidence']);
        if($r['version']!==1||$r['sequence']!==$id||$r['result']!=='ok'||!is_array($r['evidence']))$this->broken();
        return $r['evidence'];
    }
    private function broken(): never
    { $this->close();throw new SiteServiceException('conflict','Publisher outcome is unresolved.'); }
    public function close(): void {if(is_resource($this->stream))fclose($this->stream);$this->stream=null;}
    public function __destruct() {$this->close();}
}
