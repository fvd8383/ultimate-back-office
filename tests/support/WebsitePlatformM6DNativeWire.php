<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeRuntime.php';
require_once __DIR__.'/WebsitePlatformM6DNativeProtocol.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementWire.php';

/** M6D-only admission/control. Definitions have no sockets, credentials or database side effects. */
final class M6DNativeWire
{
    private static array $transcripts=[];
    public const SUBCASES=['unarmed-success','commit-not-delivered','ack-withheld','authoritative-unavailable'];
    public static function mode(string $sub,string $leg): string
    {
        M6DNativeContract::need(in_array($sub,self::SUBCASES,true) && in_array($leg,['result','reread'],true)
            && ($leg==='result' || $sub==='authoritative-unavailable'),'wire_case');
        return $leg==='reread' ? 'drop-query' : match($sub) {'unarmed-success'=>'unarmed','commit-not-delivered'=>'before-commit',default=>'drop-ack'};
    }
    public static function execution(array $a): void
    {
        $path=M6DNativeRuntime::ROOT.'/EXECUTE';
        M6DNativeContract::need(trim(self::file($path,0,0644,128))===$a['run_token'].':'.M6DNativeContract::PROFILE,'separate_execution_authorization');
    }
    public static function file(string $path,int $uid,int $mode,int $limit): string
    {
        $s=lstat($path);
        M6DNativeContract::need($s!==false && ($s['mode']&0170000)===0100000 && $s['uid']===$uid && ($s['mode']&0777)===$mode
            && $s['nlink']===1 && $s['size']<=$limit && realpath($path)===$path,'wire_protected_file');
        $v=file_get_contents($path); M6DNativeContract::need(is_string($v) && strlen($v)===$s['size'],'wire_file_changed'); return $v;
    }
    public static function jobPath(int $site,string $sub,string $leg): string
    { self::mode($sub,$leg); M6DNativeContract::need($site>0,'wire_site'); return M6DNativeRuntime::ROOT.'/state/wire-'.$site.'-'.$sub.'-'.$leg.'.json'; }
    public static function job(array $argv): array
    {
        M6DNativeContract::need(count($argv)===7 && preg_match('/^[1-9][0-9]{0,9}$/D',$argv[4])===1,'wire_arguments');
        $a=M6DNativeRuntime::admit($argv[1],$argv[2],$argv[3]); self::execution($a);
        pcntl_async_signals(true); pcntl_signal(SIGALRM,static fn()=>throw new RuntimeException('wire_child_deadline')); pcntl_alarm(45);
        $job=json_decode(self::file(self::jobPath((int)$argv[4],$argv[5],$argv[6]),2000,0600,8192),true,32,JSON_THROW_ON_ERROR);
        M6DNativeContract::need($job['binding']['identity']===M6DNativeProtocol::identity($a) && $job['binding']['site_id']===(int)$argv[4]
            && $job['binding']['case']==='result-commit-transport' && $job['binding']['subcase']===$argv[5] && $job['binding']['leg']===$argv[6]
            && preg_match('/^[a-f0-9]{32}$/D',$job['binding']['channel'])===1 && $job['coordinator_pid']===posix_getppid(),'wire_job_binding');
        self::mode($argv[5],$argv[6]); return [$a,$job];
    }
    public static function frame(string $phase,array $binding,array $value): string
    {
        M6DNativeContract::need(in_array($phase,['LISTEN','ARMED','FAULT','STOPPED','WORKER','READY','COMMIT','READ','OUTCOME'],true),'wire_phase');
        $json=json_encode(['phase'=>$phase,'binding'=>$binding,'value'=>$value],JSON_THROW_ON_ERROR);
        M6DNativeContract::need(strlen($json)<=8192 && !preg_match('/[\x00-\x1f\x7f]/',$json),'wire_frame'); return $json;
    }
    public static function decode(string $line,string $phase,array $binding): array
    {
        M6DNativeContract::need(strlen($line)<=8192 && !preg_match('/[\x00-\x1f\x7f]/',$line),'wire_frame');
        try { $r=json_decode($line,true,16,JSON_THROW_ON_ERROR); } catch(JsonException) { throw new RuntimeException('wire_json'); }
        M6DNativeContract::need(is_array($r) && array_keys($r)===['phase','binding','value'] && json_encode($r,JSON_THROW_ON_ERROR)===$line
            && $r['phase']===$phase && $r['binding']===$binding && is_array($r['value']),'wire_frame_binding');
        // Apply the existing privacy/type validator to the payload, including escaped control characters.
        M6DNativeProtocol::decode(M6DNativeProtocol::frame($binding['identity'],$r['value']),$binding['identity']);
        return $r['value'];
    }
    public static function emit(string $phase,array $binding,array $value): void { echo self::frame($phase,$binding,$value)."\n"; flush(); }
    public static function read(M6CNativeProcess $p,string $phase,array $binding): array
    {
        $line=$p->line(10); $v=self::decode($line,$phase,$binding); self::$transcripts[$p->pid][]=$line; $p->lastBarrier=$phase;
        $GLOBALS['m6d_wire_observations'][]=['pid'=>$p->pid,'phase'=>$phase,'binding'=>$binding,'value'=>$v]; return $v;
    }
    public static function release(string $expected): void
    { $line=fgets(STDIN,8194); M6DNativeContract::need($line===$expected."\n",'wire_release'); }
    public static function endpoint(array $value,int $relayPid): int
    {
        M6DNativeContract::need(array_keys($value)===['relay_pid','port'] && $value['relay_pid']===$relayPid && $relayPid>0
            && is_int($value['port']) && $value['port']>=1024 && $value['port']<=65535,'wire_endpoint'); return $value['port'];
    }
    public static function connect(array $a,array $job,array $endpoint): PDO
    {
        self::execution($a); $binding=$job['binding'];
        $record=json_decode(self::file(self::jobPath($binding['site_id'],$binding['subcase'],$binding['leg']).'.endpoint',2000,0600,8192),true,16,JSON_THROW_ON_ERROR);
        M6DNativeContract::need($record===['binding'=>$binding,'endpoint'=>$endpoint] && posix_kill($endpoint['relay_pid'],0),'wire_endpoint_owner');
        $port=self::endpoint($endpoint,$endpoint['relay_pid']);
        $c=json_decode(self::file(M6DNativeRuntime::ROOT.'/database.json',2000,0600,4096),true,16,JSON_THROW_ON_ERROR);
        M6DNativeContract::need(array_keys($c)===['password'] && is_string($c['password']) && strlen($c['password'])>=32 && strlen($c['password'])<=128,'disposable_credentials');
        $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$a['database_name'].';charset=utf8mb4',$a['database_user'],$c['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
        $r=$db->query('SELECT DATABASE() AS db, @@hostname AS host, @@server_uuid AS uuid, VERSION() AS version, CURRENT_USER() AS account')->fetch();
        self::backend($r,$a);
        $db->exec("SET SESSION time_zone = '+00:00', innodb_lock_wait_timeout = 10"); return $db;
    }
    public static function backend(array $r,array $a): void
    {
        M6DNativeContract::need($r['db']===$a['database_name'] && $r['host']===$a['database_hostname'] && $r['uuid']===$a['mysql_server_uuid']
            && str_starts_with($r['version'],'8.4.') && str_starts_with($r['account'],$a['database_user'].'@'),'wire_mysql_identity');
    }
    public static function drain(PDO $db): void
    {
        // A reply to a text-protocol no-op drains prior COM_STMT_CLOSE packets. Unlike
        // a prepared probe it leaves no statement destructor/close after the barrier.
        M6DNativeContract::need($db->exec('DO 0')===0,'wire_prearm_drain');
    }
    public static function process(array $r): array
    {
        return ['pid'=>$r['pid'],'exit'=>is_int($r['exit'] ?? null) && $r['exit']>=0 ? $r['exit'] : null,
            'signaled'=>$r['signaled'],'signal'=>$r['signal'],'termination_requested'=>$r['termination_requested'],'running'=>$r['running']];
    }
    public static function finish(M6CNativeProcess $p,int $pdoDiagnostics=0): array
    {
        $r=$p->finish(); $safe=self::process($r); $GLOBALS['m6d_native_child_results'][]=$safe+['context'=>$p->diagnosticContext,'last_barrier'=>$p->lastBarrier];
        M6DNativeContract::need($safe['exit']===0 && !$safe['signaled'] && !$safe['termination_requested'] && !$safe['running'],'wire_child_completion');
        M6DNativeContract::need($r['stderr']===str_repeat("Website M2 database operation failed: PDOException\n",$pdoDiagnostics),'wire_child_diagnostics');
        M6DNativeContract::need($r['stdout']===implode("\n",self::$transcripts[$p->pid] ?? [])."\n",'wire_extra_output');
        unset(self::$transcripts[$p->pid]);
        return $safe;
    }
}

/** Stream queues reuse the reviewed recognizer without implementing any SQL parser. */
final class M6DNativeWirePump
{
    public M6CSupplementWire $wire;
    public array $pending=['client'=>'','server'=>''];
    private bool $armed=false;
    public function __construct(public readonly string $mode) { $this->wire=new M6CSupplementWire($mode==='unarmed' ? 'drop-ack' : $mode); }
    public function arm(): void
    {
        M6DNativeContract::need($this->mode!=='unarmed' && !$this->armed && $this->pending===['client'=>'','server'=>''],'wire_arm_phase');
        $this->wire->arm(); $this->armed=true;
    }
    public function feed(string $side,string $bytes): void
    {
        $other=$side==='client' ? 'server' : 'client'; $this->pending[$other].=$this->wire->feed($side,$bytes);
        M6DNativeContract::need(strlen($this->pending[$other])<=1048580,'wire_queue_bound');
    }
    public function write(string $side,callable $writer): void
    {
        $n=$writer($this->pending[$side]);
        M6DNativeContract::need(is_int($n) && $n>=0 && $n<=strlen($this->pending[$side]),'wire_write');
        $this->pending[$side]=substr($this->pending[$side],$n);
    }
    public function fault(): array
    {
        $f=$this->wire->fault;
        M6DNativeContract::need($this->armed && $f===['mode'=>$this->mode,'forwarded'=>$this->mode==='drop-ack','ok_received'=>$this->mode==='drop-ack']
            && $this->pending===['client'=>'','server'=>''],'wire_fault');
        return $f+['ack_suppressed'=>$this->mode==='drop-ack'];
    }
}
