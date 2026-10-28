<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeReport.php';

/** Fixed native-report transport only. No engine, application, SQL or worker bootstrap. */
final class M6CNativeTransport
{
    public const LIMIT=1048576;
    /** Core PHP only: host helpers run with -n, so POSIX need not be loaded. */
    public static function hostUid(?string $status=null):int
    {
        $status??=m6nStep('transport_host_uid',fn()=>file_get_contents('/proc/self/status',false,null,0,16385));
        if(strlen($status)>16384||!preg_match('/^Uid:[ \t]+[0-9]{1,10}[ \t]+([0-9]{1,10})[ \t]+[0-9]{1,10}[ \t]+[0-9]{1,10}[ \t]*$/mD',$status,$m))
            throw new RuntimeException('transport_host_uid');
        return (int)$m[1];
    }
    public static function envelope(string $bytes,string $sha,string $token,string $profile=M6CNativeContract::VERSION):array
    {
        if($bytes===''||strlen($bytes)>self::LIMIT)throw new RuntimeException('transport_size');
        if($profile===M6CSupplementContract::VERSION)return M6CSupplementContract::decode($bytes,$sha,$token);
        if($profile!==M6CNativeContract::VERSION)throw new RuntimeException('transport_profile');
        $report=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($report))throw new RuntimeException('transport_json');
        M6CNativeContract::envelope($report,$sha,$token);return $report;
    }
    public static function read(string $sha,string $token,?callable $io=null,string $profile=M6CNativeContract::VERSION):string
    {
        $io??=static fn($op,...$args)=>$op(...$args);
        $location=M6CNativeReport::location($io);$path='/run/m6c/report.json';
        $io('clearstatcache',true,$path);
        $s=m6nStep('transport_stat',fn()=>$io('lstat',$path));
        if(($s['mode']&0170000)!==0100000||($s['mode']&07777)!==0644||$s['uid']!==0||$s['gid']!==0
            ||$s['nlink']!==1||$s['dev']!==$location['dev']||$s['size']<1||$s['size']>self::LIMIT
            ||$io('realpath',$path)!==$path)throw new RuntimeException('transport_file_contract');
        $stream=m6nStep('transport_open',fn()=>$io('fopen',$path,'rb'));
        try{
            $opened=m6nStep('transport_fstat',fn()=>$io('fstat',$stream));
            foreach(['dev','ino','mode','uid','gid','size','nlink'] as $key)
                if($opened[$key]!==$s[$key])throw new RuntimeException('transport_file_changed');
            $bytes=m6nStep('transport_read',fn()=>$io('stream_get_contents',$stream,self::LIMIT+1));
            if(strlen($bytes)!==$s['size'])throw new RuntimeException('transport_partial_or_grown');
            self::envelope($bytes,$sha,$token,$profile);
        }finally{m6nStep('transport_close',fn()=>$io('fclose',$stream));}
        return $bytes;
    }
    private static function host(string $directory,string $attempt):string
    {
        if(!in_array($attempt,['normal','fallback'],true)||is_link($directory)||!is_dir($directory))throw new RuntimeException('transport_host');
        clearstatcache(true,$directory);$s=lstat($directory);
        if(PHP_OS_FAMILY==='Linux'&&($s['uid']!==self::hostUid()||($s['mode']&07777)!==0700))throw new RuntimeException('transport_host_owner_mode');
        return $directory.'/native-'.$attempt.'.pending';
    }
    public static function receive(mixed $input,string $directory,string $attempt):void
    {
        $path=self::host($directory,$attempt);
        // Read at most limit+1 from the pipe; oversized transport never writes an oversized host file.
        $bytes=m6nStep('transport_receive',fn()=>stream_get_contents($input,self::LIMIT+1));
        if($bytes===''||strlen($bytes)>self::LIMIT)throw new RuntimeException('transport_size');
        $stream=m6nStep('transport_pending_open',fn()=>fopen($path,'xb'));
        try{
            if(m6nStep('transport_pending_write',fn()=>fwrite($stream,$bytes))!==strlen($bytes))throw new RuntimeException('transport_pending_write');
            m6nStep('transport_pending_flush',fn()=>fflush($stream));
            m6nStep('transport_pending_mode',fn()=>chmod($path,0600));
        }finally{m6nStep('transport_pending_close',fn()=>fclose($stream));}
    }
    public static function verify(string $directory,string $attempt,string $sha,string $token,string $profile=M6CNativeContract::VERSION):array
    {
        $pending=self::host($directory,$attempt);$final=$directory.'/native.json';
        if(is_link($pending)||!is_file($pending)||file_exists($final)||is_link($final))throw new RuntimeException('transport_pending_contract');
        clearstatcache(true,$pending);$s=lstat($pending);
        if($s['nlink']!==1||$s['size']<1||$s['size']>self::LIMIT||
            (PHP_OS_FAMILY==='Linux'&&($s['uid']!==self::hostUid()||($s['mode']&07777)!==0600)))throw new RuntimeException('transport_pending_contract');
        $bytes=m6nStep('transport_pending_read',fn()=>file_get_contents($pending,false,null,0,self::LIMIT+1));
        if(strlen($bytes)!==$s['size'])throw new RuntimeException('transport_pending_changed');
        $report=self::envelope($bytes,$sha,$token,$profile);$digest=hash('sha256',$bytes);$ack='ABSENT';
        $log=$directory.'/child.stderr.txt';
        if(is_file($log)&&!is_link($log)){
            $text=m6nStep('transport_ack_read',fn()=>file_get_contents($log,false,null,0,self::LIMIT+1));
            if(strlen($text)>self::LIMIT)throw new RuntimeException('transport_ack_size');
            $lines=array_values(array_filter(explode("\n",$text),fn($line)=>str_starts_with($line,'M6C REPORT PUBLISHED')));
            if($lines!==[]){
                if(count($lines)!==1||!preg_match('/^M6C REPORT PUBLISHED source=([a-f0-9]{40}) run=([a-f0-9]{32}) bytes=([1-9][0-9]{0,6}) sha256=([a-f0-9]{64})\r?$/D',$lines[0],$m)
                    ||$m[1]!==$sha||$m[2]!==$token||(int)$m[3]!==strlen($bytes)||$m[4]!==$digest)throw new RuntimeException('transport_ack_mismatch');
                $ack='MATCH';
            }
        }
        m6nStep('transport_promote',fn()=>rename($pending,$final));
        return ['capture'=>'PASS','native_status'=>$report['status'],'bytes'=>strlen($bytes),'sha256'=>$digest,'acknowledgement'=>$ack];
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    set_error_handler(static function():never{throw new RuntimeException('transport_io');});
    try{
        $mode=$argv[1]??'';
        if($mode==='read'&&in_array(count($argv),[4,5],true)){
            $bytes=M6CNativeTransport::read($argv[2],$argv[3],null,$argv[4]??M6CNativeContract::VERSION);
            if(fwrite(STDOUT,$bytes)!==strlen($bytes)||!fflush(STDOUT))throw new RuntimeException('transport_stdout');
        }elseif($mode==='receive'&&count($argv)===4)M6CNativeTransport::receive(STDIN,$argv[2],$argv[3]);
        elseif($mode==='verify'&&in_array(count($argv),[6,7],true))echo json_encode(M6CNativeTransport::verify($argv[2],$argv[3],$argv[4],$argv[5],$argv[6]??M6CNativeContract::VERSION),JSON_THROW_ON_ERROR)."\n";
        else throw new RuntimeException('transport_arguments');
    }catch(Throwable $e){
        $code=preg_match('/^(?:transport|report)_[a-z_]+$/D',$e->getMessage())?$e->getMessage():'transport_invalid_envelope';
        fwrite(STDERR,'M6C report transport FAIL: '.$code."\n");exit(2);
    }
}
