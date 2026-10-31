<?php
declare(strict_types=1);

/** Policy shared by the native interceptor and synthetic local regression inputs. No bootstrap. */
final class M6CJournalFault
{
    public const OPERATIONS=['flush','fsync','publication','control'];
    public const CALLS=['fopen','fwrite','fflush','fsync','fclose','chmod','rename'];
    private int $step=0;private ?string $pending=null;private int $injected=0;private bool $done=false;
    private array $calls;private array $accepted;private int $bytes=0;
    public function __construct(public readonly string $operation,public readonly string $run,
        public readonly string $job,public readonly int $producer)
    {
        if(!in_array($operation,self::OPERATIONS,true)||!preg_match('/^[a-f0-9]{32}$/D',$run)
            ||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$job)||$producer<1)
            throw new RuntimeException('journal_arm_identity');
        $this->calls=$this->accepted=array_fill_keys(self::CALLS,0);
    }
    /** Native bridge supplies actual matched PHP call/return observations. Local tests label inputs synthetic. */
    public function observe(string $call,string $phase,bool $ok,int $bytes):bool
    {
        if($this->done||!in_array($call,self::CALLS,true)||!in_array($phase,['before','after'],true)
            ||$call!==(self::CALLS[$this->step]??null))throw new RuntimeException('journal_wrong_phase');
        $target=match($this->operation){'flush'=>'fflush','fsync'=>'fsync','publication'=>'rename',default=>null};
        if($phase==='before'){
            if($this->pending!==null||++$this->calls[$call]!==1)throw new RuntimeException('journal_duplicate_call');
            $this->pending=$call;
            if($call===$target){if(++$this->injected!==1)throw new RuntimeException('journal_duplicate_fault');return true;}
            return false;
        }
        if($this->pending!==$call)throw new RuntimeException('journal_missing_call');$this->pending=null;
        if($call===$target){
            if($ok||$this->injected!==1)throw new RuntimeException('journal_failure_not_delivered');
            if($call==='rename')$this->done=true;else $this->step=4; // finally fclose, never a fictitious completed fsync.
            return false;
        }
        // A secondary close failure must not replace the caller's already pending exception.
        // The zero accepted-close count still prevents acceptance of this case's evidence.
        if(!$ok&&$call==='fclose'&&$this->injected===1){$this->done=true;return false;}
        if(!$ok)throw new RuntimeException('journal_unexpected_operation_failure');
        $this->accepted[$call]++;
        if($call==='fwrite'){if($bytes<1||$bytes>4096)throw new RuntimeException('journal_write_bound');$this->bytes=$bytes;}
        if($call==='fclose'&&$this->injected===1)$this->done=true;
        elseif(++$this->step===count(self::CALLS))$this->done=true;
        return false;
    }
    public function evidence(array $native):array
    {
        $expected=$this->operation==='control'?0:1;
        if(!$this->done||$this->pending!==null||$this->injected!==$expected
            ||array_keys($native)!==['run','job','producer','matched','injected','off_target']
            ||[$native['run'],$native['job'],$native['producer']]!==[$this->run,$this->job,$this->producer]
            ||$native['matched']!==array_sum($this->calls)||$native['injected']!==$expected
            ||!is_int($native['off_target'])||$native['off_target']<7||$native['off_target']>4096)
            throw new RuntimeException('journal_incomplete_injection');
        return ['operation'=>$this->operation,'target_role'=>'producer_pending_journal','run'=>$this->run,
            'target_digest'=>hash('sha256',$this->run.'|'.$this->job.'|'.$this->producer),'producer'=>$this->producer,
            'calls'=>$this->calls,'accepted'=>$this->accepted,'bytes'=>$this->bytes,'matched'=>$native['matched'],
            'injected'=>$native['injected'],'off_target'=>$native['off_target'],'complete'=>true];
    }
    public static function primary(callable $body,callable $cleanup):mixed
    {
        $primary=null;$secondary=null;$result=null;
        try{$result=$body();}catch(Throwable $e){$primary=$e;}
        try{$cleanup();}catch(Throwable $e){$secondary=$e;}
        if($primary!==null)throw $primary;if($secondary!==null)throw $secondary;return $result;
    }
    /** New private control namespace only; collisions never enter ownership/cleanup. */
    public static function offTarget(string $directory):void
    {
        if(file_exists($directory)||is_link($directory)||!mkdir($directory,0700))throw new RuntimeException('journal_control_collision');
        $real=realpath($directory);$identity=lstat($directory);
        self::primary(static function()use($directory):void{
            SiteArtifactFiles::writeNew($directory.'/input','unrelated-control',4096);
            if(!rename($directory.'/input',$directory.'/final')||file_get_contents($directory.'/final')!=='unrelated-control')
                throw new RuntimeException('journal_off_target');
        },static function()use($directory,$real,$identity):void{
            clearstatcache(true,$directory);$now=lstat($directory);
            if(!$now||is_link($directory)||$real===false||realpath($directory)!==$real
                ||[$now['dev'],$now['ino']]!==[$identity['dev'],$identity['ino']])throw new RuntimeException('journal_cleanup_identity');
            foreach(['input','final'] as $name){$path=$directory.'/'.$name;
                if(is_link($path)||(file_exists($path)&&(!is_file($path)||!unlink($path))))throw new RuntimeException('journal_control_cleanup');
            }
            if(!rmdir($directory))throw new RuntimeException('journal_control_cleanup');
        });
    }
}

// Fixed callback resolved by the test extension; no configurable callback or raw path channel.
function m6c_journal_observe(string $call,string $phase,bool $ok,int $bytes):bool
{
    global $m6cJournalFault;
    if(!isset($m6cJournalFault)||!$m6cJournalFault instanceof M6CJournalFault)throw new RuntimeException('journal_unarmed');
    return $m6cJournalFault->observe($call,$phase,$ok,$bytes);
}
function m6c_journal_authorize(string $run,string $job,int $producer):bool
{
    global $m6cJournalFault;
    return isset($m6cJournalFault)&&$m6cJournalFault instanceof M6CJournalFault
        &&[$run,$job,$producer]===[$m6cJournalFault->run,$m6cJournalFault->job,$m6cJournalFault->producer];
}
