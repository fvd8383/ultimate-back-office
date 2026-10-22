<?php
declare(strict_types=1);

/** Bounded test-child ownership. No application bootstrap or implicit execution. */
final class M6CNativeProcess
{
    private static array $live=[];
    private mixed $process;
    private array $pipes=[];
    private string $buffer='';
    private ?array $stopped=null;
    private ?array $interrupted=null;
    private string $captureDirectory;
    public string $stdout='';
    public string $stderr='';
    public bool $terminationRequested=false;
    public readonly int $pid;
    private int $started;
    private array $streamBytes=['stdout'=>0,'stderr'=>0];
    public ?string $lastBarrier=null;
    public function __construct(public readonly array $command,private int $seconds=60,private int $limit=65536)
    {
        $active=0;foreach(self::$live as $p)if($p->running())$active++;
        if($active>=2)throw new RuntimeException('native_child_count');
        // File capture avoids Windows anonymous-pipe reads that can ignore nonblocking mode.
        $this->captureDirectory=sys_get_temp_dir().'/ubo-m6c-native-child-'.bin2hex(random_bytes(12));
        if(!mkdir($this->captureDirectory,0700))throw new RuntimeException('native_capture_start');
        foreach(['stdout','stderr'] as $stream){file_put_contents($this->captureDirectory.'/'.$stream,'');chmod($this->captureDirectory.'/'.$stream,0600);}
        $this->process=proc_open($command,[0=>['pipe','r'],1=>['file',$this->captureDirectory.'/stdout','ab'],
            2=>['file',$this->captureDirectory.'/stderr','ab']],$this->pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($this->process))throw new RuntimeException('native_child_start');
        $s=proc_get_status($this->process);$this->pid=$s['pid'];$this->started=hrtime(true);self::$live[]=$this;
    }
    public function running(): bool
    {
        if(!is_resource($this->process)||$this->stopped!==null)return false;
        $s=proc_get_status($this->process);if(!$s['running'])$this->stopped=$s;return $s['running'];
    }
    private function pump(): void
    {
        $this->readCapture();
        if(strlen($this->stdout)>$this->limit||strlen($this->stderr)>$this->limit){$this->interrupted=$this->stop();throw new RuntimeException('native_child_output_limit');}
        if(hrtime(true)-$this->started>$this->seconds*1000000000){$this->interrupted=$this->stop();throw new RuntimeException('native_child_timeout');}
    }
    private function readCapture(): void
    {
        if(!is_dir($this->captureDirectory))return;
        foreach(['stdout','stderr'] as $stream){clearstatcache(true,$this->captureDirectory.'/'.$stream);
            $this->streamBytes[$stream]=(int)filesize($this->captureDirectory.'/'.$stream);}
        $stdout=(string)file_get_contents($this->captureDirectory.'/stdout',false,null,0,$this->limit+1);
        $this->buffer.=substr($stdout,strlen($this->stdout));$this->stdout=$stdout;
        $this->stderr=(string)file_get_contents($this->captureDirectory.'/stderr',false,null,0,$this->limit+1);
    }
    public function send(string $line): void
    {
        if(strlen($line)>8192||str_contains($line,"\n")||!$this->running())throw new RuntimeException('native_barrier_write');
        if(fwrite($this->pipes[0],$line."\n")!==strlen($line)+1||!fflush($this->pipes[0]))throw new RuntimeException('native_barrier_write');
    }
    public function closeInput(): void {if(is_resource($this->pipes[0]))fclose($this->pipes[0]);}
    public function line(int $seconds=15): string
    {
        $end=hrtime(true)+$seconds*1000000000;
        do{
            $this->pump();$at=strpos($this->buffer,"\n");
            if($at!==false){$line=substr($this->buffer,0,$at);$this->buffer=substr($this->buffer,$at+1);return rtrim($line,"\r");}
            if(!$this->running())throw new RuntimeException('native_child_before_barrier');
            usleep(10000);
        }while(hrtime(true)<$end);
        $this->interrupted=$this->stop();throw new RuntimeException('native_barrier_timeout');
    }
    public function finish(): array
    {
        if(!is_resource($this->process))return $this->close();
        while($this->running()){$this->pump();usleep(10000);}
        $this->pump();return $this->close();
    }
    private function close(): array
    {
        if(is_resource($this->process)){
            foreach($this->pipes as $pipe)if(is_resource($pipe))fclose($pipe);
            $closed=proc_close($this->process);
            if(($this->stopped['exitcode']??-1)<0&&$closed>=0)$this->stopped['exitcode']=$closed;
            foreach(['stdout','stderr'] as $stream)if(!unlink($this->captureDirectory.'/'.$stream))throw new RuntimeException('native_capture_cleanup');
            if(!rmdir($this->captureDirectory))throw new RuntimeException('native_capture_cleanup');
        }
        return ['pid'=>$this->pid,'exit'=>$this->stopped['exitcode']??-1,'signaled'=>$this->stopped['signaled']??false,
            'signal'=>$this->stopped['termsig']??0,'termination_requested'=>$this->terminationRequested,
            'stdout'=>$this->stdout,'stderr'=>$this->stderr,'running'=>false,'stream_bytes'=>$this->streamBytes];
    }
    /** Retained bytes survive close; a live observation never invents a reaped exit. */
    public function observation(): array
    {
        $this->readCapture();$running=$this->running();
        return ['pid'=>$this->pid,'exit'=>$this->stopped['exitcode']??null,'signaled'=>$this->stopped['signaled']??null,
            'signal'=>$this->stopped['termsig']??null,'termination_requested'=>$this->terminationRequested,
            'running'=>$running,'stdout'=>$this->stdout,'stderr'=>$this->stderr,'stream_bytes'=>$this->streamBytes];
    }
    public function stop(int $signal=15): array
    {
        if($this->running()){
            $this->terminationRequested=true;proc_terminate($this->process,$signal);$end=hrtime(true)+2000000000;
            while($this->running()&&hrtime(true)<$end)usleep(10000);
            if($this->running()){proc_terminate($this->process,9);$end=hrtime(true)+2000000000;while($this->running()&&hrtime(true)<$end)usleep(10000);}
            if($this->running())throw new RuntimeException('native_child_survived');
        }
        $this->readCapture();
        $this->stdout=substr($this->stdout,0,$this->limit);$this->stderr=substr($this->stderr,0,$this->limit);
        return $this->close();
    }
    public static function capture(array $command,int $seconds=60,int $limit=65536): array
    { $p=new self($command,$seconds,$limit);$p->closeInput();try{return $p->finish();}finally{if($p->running())$p->stop();} }
    public static function cleanup(): array
    { $out=[];foreach(self::$live as $p){if(is_resource($p->process))$out[]=$p->stop();elseif($p->interrupted!==null)$out[]=$p->interrupted;}self::$live=[];return $out; }
}
