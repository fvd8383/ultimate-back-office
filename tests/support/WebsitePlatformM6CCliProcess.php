<?php
declare(strict_types=1);

// Small M6C test helper. Never record command arguments, environment or raw output.
ini_set('zend.exception_ignore_args','1');
final class M6CChildFailure extends RuntimeException {
    public function __construct(public string $reason,public array $outcome,public ?string $evidence){
        parent::__construct('M6C child '.$reason.'; case='.$outcome['case'].'; profile='.$outcome['profile']
            .'; exit='.($outcome['exit']??'unavailable').'; signal='.($outcome['signal']??'none')
            .'; diagnostics='.trim($outcome['stdout'].' '.$outcome['stderr'])
            .'; evidence='.($evidence??'unavailable (primary failure preserved)'));
    }
}
function m6privateDirectory(string $prefix):string {
    $dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.bin2hex(random_bytes(12));
    if(!mkdir($dir,0700))throw new RuntimeException('Private capture unavailable');
    if(!chmod($dir,0700)){@rmdir($dir);throw new RuntimeException('Private capture permissions unavailable');}
    return $dir;
}
function m6diagnostic(string $raw):string {
    // Keep useful engine/prerequisite identities, not arbitrary exception messages,
    // paths, stack arguments, snapshots, credentials or other child payloads.
    $lines=[];$identities=['PDO','PDOStatement','PDOException','ReflectionClass','ReflectionProperty','DateTimeImmutable','DateTimeZone',
        'DirectoryIterator','RecursiveDirectoryIterator','RecursiveIteratorIterator','FilesystemIterator','DOMDocument','DOMXPath','DOMElement','JsonException',
        'json_encode','json_decode','hash','random_bytes','preg_match','array_is_list','ctype_digit','filter_var','getimagesizefromstring','fsync',
        'base64_decode','preg_match_all','preg_replace','bin2hex','hash_equals','clearstatcache','lstat','is_link','fstat','feof',
        'libxml_use_internal_errors','fopen','fread','fwrite','fflush','flock','fclose','mkdir','chmod','rename','unlink','rmdir',
        'file_get_contents','file_put_contents','scandir','stat','realpath','PDO::FETCH_DEFAULT','PDO::FETCH_ASSOC','LIBXML_DOTTED_VERSION',
        'basename','dirname','sys_get_temp_dir','register_shutdown_function','array_intersect_key','array_flip','substr','strlen','count','array_replace','str_repeat','ucfirst',
        'rtrim','str_starts_with','extension_loaded','is_array','ksort','array_key_exists','array_diff','array_keys','is_string','min',
        'str_contains','usort','str_replace','array_shift','array_unique','array_slice','ucwords','array_fill_keys',
        'array_count_values','array_column','array_intersect','is_int','is_bool','strtoupper','strcmp','is_scalar',
        'str_ends_with','chr','ord','implode','sort','method_exists','array_key_first','array_push',
        'function_exists','ini_get','explode','trim','strtolower','array_map','array_filter','array_values','class_exists','in_array','defined','php_ini_loaded_file',
        'PDO::getAvailableDrivers','M6CMissingFixtureDependency','m6cMissingFixtureFunction'];
    foreach(explode("\n",str_replace("\r",'',substr($raw,0,32768)))as$line){
        if(trim($line)==='')continue;
        if(preg_match('/^M6C_PREREQUISITE: missing ([A-Za-z0-9_:, ]+)\.$/D',$line,$m)
            &&array_diff(explode(', ',$m[1]),$identities)===[])$lines[]=$line;
        elseif(preg_match('/^Build-only (?:outcome|invocation stopped): (?:failed|no_work_claimed|builder_unavailable|future_gate_required|invalid_request|controlled_failure|controlled_timeout|controlled_overflow)\.$/D',$line))$lines[]=$line;
        elseif(preg_match('/(?:Class|Interface|Trait) [\'\"]([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'\"] not found/',$line,$m))$lines[]='Class/interface/trait "'.(in_array($m[1],$identities,true)?$m[1]:'[redacted]').'" not found';
        elseif(preg_match('/Call to undefined (?:function|method) ([A-Za-z_\\\\][A-Za-z0-9_\\\\:]*)\(/',$line,$m))$lines[]='Call to undefined function/method '.(in_array($m[1],$identities,true)?$m[1]:'[redacted]').'()';
        elseif(preg_match('/Allowed memory size of \d+ bytes exhausted/',$line,$m))$lines[]=$m[0];
        elseif(str_contains($line,'Unable to load dynamic library'))$lines[]='PHP Startup: Unable to load dynamic library [details redacted]';
        elseif(preg_match('/Uncaught ([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/',$line,$m))$lines[]='Uncaught '.(in_array($m[1],['Error','TypeError','ValueError','RuntimeException','LogicException','SiteServiceException','JsonException','PDOException'],true)?$m[1]:'[redacted type]').' [message/location redacted]';
        elseif(str_contains($line,'Parse error:'))$lines[]='PHP parse error [details redacted]';
        else $lines[]='[redacted output]';
    }
    return substr(implode("\n",array_values(array_unique($lines))),0,4096);
}
function m6retainFailure(array $outcome,?Closure $writer=null):?string {
    $dir=null;
    try{
        $dir=m6privateDirectory('ubo-m6c-cli-failure-');$path=$dir.'/failure.json';
        $json=json_encode($outcome,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if($writer!==null)$writer($path,$json);
        elseif(file_put_contents($path,$json) !== strlen($json))throw new RuntimeException('Evidence write failed');
        if(!is_file($path)||!chmod($path,0600))throw new RuntimeException('Evidence permissions failed');
        return $path;
    }catch(Throwable){
        if($dir!==null){if(is_file($dir.'/failure.json'))@unlink($dir.'/failure.json');@rmdir($dir);}
        return null; // Evidence failure cannot replace the primary child/test failure.
    }
}
function m6discardExpectedFailure(M6CChildFailure $failure):void {
    // Negative regressions consume their own evidence; never sweep other failures.
    if($failure->evidence===null)return;
    $dir=dirname($failure->evidence);
    if(realpath(dirname($dir))!==realpath(sys_get_temp_dir())
        ||!preg_match('/^ubo-m6c-cli-failure-[a-f0-9]{24}$/D',basename($dir))
        ||basename($failure->evidence)!=='failure.json'||is_link($dir)||is_link($failure->evidence))throw new RuntimeException('Unowned failure evidence');
    if(!unlink($failure->evidence)||!rmdir($dir))throw new RuntimeException('Expected failure cleanup failed');
}
function m6childTrace(array $result,string $marker):array {
    [$exit,$out,$err]=$result;
    if($exit===78&&(str_contains($err,'M6C_PREREQUISITE:')||str_contains($out,'M6C_PREREQUISITE:')))throw new RuntimeException('prerequisite');
    if(preg_match('/\n'.preg_quote($marker,'/').':([^\r\n]+)\r?\n$/D',$err,$match)!==1)throw new RuntimeException('trace_missing');
    try{$trace=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('trace_malformed');}
    if(!is_array($trace))throw new RuntimeException('trace_malformed');
    return [$trace,substr($err,0,-strlen($match[0]))];
}
function m6process(array $command,array $env=[],int $timeoutNanoseconds=10000000000,
    array $context=['case'=>'cli','profile'=>'no-ini'],?Closure $check=null,?Closure $evidenceWriter=null):array {
    foreach(['case','profile']as$key)if(!isset($context[$key])||!preg_match('/^[a-zA-Z0-9_.,;= -]{1,160}$/D',$context[$key]))throw new RuntimeException('Invalid capture label');
    $dir=m6privateDirectory('ubo-m6c-cli-capture-');$stdout=$dir.'/stdout';$stderr=$dir.'/stderr';
    $p=null;$status=null;$closed=null;$reason=null;$terminationRequested=false;
    try{
        foreach([$stdout,$stderr]as$file)if(file_put_contents($file,'')!==0||!chmod($file,0600))throw new RuntimeException('capture_start');
        // Files avoid blocking Windows anonymous-pipe reads. The parent monitors
        // wall time/aggregate output, then captures diagnostics before cleanup.
        $p=@proc_open($command,[0=>['pipe','r'],1=>['file',$stdout,'ab'],2=>['file',$stderr,'ab']],$pipes,null,$env===[]?null:$env);
        if(!is_resource($p))throw new RuntimeException('process_start');
        fclose($pipes[0]);$start=hrtime(true);
        do{
            $status=proc_get_status($p);clearstatcache();
            if(filesize($stdout)+filesize($stderr)>65536)$reason='output_limit';
            elseif(hrtime(true)-$start>$timeoutNanoseconds)$reason='timeout';
            if($reason!==null&&$status['running']){
                $terminationRequested=true;proc_terminate($p);$stop=hrtime(true);
                do{$status=proc_get_status($p);if(!$status['running'])break;usleep(1000);}while(hrtime(true)-$stop<250000000);
                if($status['running']){proc_terminate($p,9);$stop=hrtime(true);do{$status=proc_get_status($p);if(!$status['running'])break;usleep(1000);}while(hrtime(true)-$stop<1000000000);}
                break;
            }
            if($status['running'])usleep(1000);
        }while($status['running']);
        // Do not call blocking proc_close while a child is still reported running.
        if(!$status['running']){$closed=proc_close($p);$p=null;}
        $out=(string)file_get_contents($stdout,false,null,0,65536);$err=(string)file_get_contents($stderr,false,null,0,65536);
        $exit=$status['exitcode']>=0?$status['exitcode']:($closed!==null&&$closed>=0?$closed:null);
        $result=[$exit,$out,$err];
        if($reason!==null)throw new RuntimeException($reason);
        if($check!==null)$check($result);
        return $result;
    }catch(Throwable $failure){
        $reason=in_array($failure->getMessage(),['process_start','capture_start','timeout','output_limit','trace_missing','trace_malformed','prerequisite'],true)?$failure->getMessage():'validation';
        $out=is_file($stdout)?(string)file_get_contents($stdout,false,null,0,32768):'';
        $err=is_file($stderr)?(string)file_get_contents($stderr,false,null,0,32768):'';
        $exit=($status['exitcode']??-1)>=0?$status['exitcode']:($closed!==null&&$closed>=0?$closed:null);
        clearstatcache();$outcome=$context+['reason'=>$reason,'exit'=>$exit,'signal'=>($status['signaled']??false)?$status['termsig']:null,
            'running'=>$status['running']??null,'termination_requested'=>$terminationRequested,
            'stdout_bytes'=>is_file($stdout)?filesize($stdout):0,'stderr_bytes'=>is_file($stderr)?filesize($stderr):0,
            'capture_prefix_limit_per_stream'=>32768,'sanitized_limit_per_stream'=>4096,
            'stdout'=>m6diagnostic($out),'stderr'=>m6diagnostic($err)];
        $path=m6retainFailure($outcome,$evidenceWriter);
        throw new M6CChildFailure($reason,$outcome,$path);
    }finally{
        // Only these owned captures are removed. Failed sanitized evidence is
        // retained separately; successful captures never accumulate.
        foreach([$stdout,$stderr]as$file)if(is_file($file))@unlink($file);
        @rmdir($dir);
    }
}
