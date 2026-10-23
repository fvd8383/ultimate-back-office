<?php
declare(strict_types=1);

/** Test-worker evidence only. No bootstrap, raw exception text, paths or database access. */
final class M6CNativeWorkerError
{
    public const PREFIX='M6C WORKER ERROR ';
    public const LIMIT=768;
    public const MODES=['build','asset-loss','asset-corrupt','deny','hold','renew-execution','stale','audit','validation','recover','renew-recovery','unknown'];
    public const PHASES=['context','config','database_binding','database_connect','server_identity','arguments','dependencies','wiring','fault_adapter',
        'wait_READY','claim','wait_CLAIM','execute','wait_SEALED','complete','replay','wait_VERIFY','recovery_claim','wait_RECOVERY',
        'reconcile','recovery_complete','failure_settlement','result','unknown'];
    public const SERVICE=['invalid_request','not_found','unauthorized','invalid_transition','immutable_revision','stale_write','conflict','future_gate_required','database_failure'];
    public const ARTIFACT=['requester_not_authorized','source_not_eligible','input_mismatch','artifact_invalid','builder_unavailable','policy_unsupported',
        'execution_exhausted','storage_unavailable','network_unavailable','lease_expired','outcome_unknown','database_unavailable'];
    public const HARNESS=['native_container_only','native_context','instrumented_arguments','worker_database_binding','worker_server_binding',
        'instrumented_identity','instrumented_worker_uid','instrumented_source_identity','native_barrier_release','unexpected_claim','recovery_not_claimed',
        'native_child_count','native_capture_start','native_child_start','native_child_output_limit','native_child_timeout','native_barrier_write',
        'native_child_before_barrier','native_barrier_timeout','native_capture_cleanup','native_child_survived'];
    private const CLASSES=['RuntimeException','LogicException','JsonException','PDOException','TypeError','ValueError','Error','SiteServiceException','SiteArtifactException','Throwable'];
    public static function sql(mixed $value):?array
    {
        if(!is_array($value)||count($value)!==2||!is_string($value['sqlstate']??null)
            ||!preg_match('/^[A-Z0-9]{5}$/D',$value['sqlstate'])||!is_int($value['driver']??null)
            ||$value['driver']<0||$value['driver']>2147483647)return null;
        return ['sqlstate'=>$value['sqlstate'],'driver'=>$value['driver']];
    }
    public static function record(Throwable $error,string $mode,string $phase):array
    {
        $class=get_class($error);$class=in_array($class,self::CLASSES,true)?$class:'Throwable';
        $code='native_child_failure';
        if($class==='SiteServiceException'&&in_array($error->classification(),self::SERVICE,true))$code=$error->classification();
        elseif($class==='SiteArtifactException'&&in_array($error->failureCode,self::ARTIFACT,true))$code=$error->failureCode;
        elseif($class==='RuntimeException'&&in_array($error->getMessage(),self::HARNESS,true))$code=$error->getMessage();
        $out=['version'=>1,'mode'=>in_array($mode,self::MODES,true)?$mode:'unknown',
            'phase'=>in_array($phase,self::PHASES,true)?$phase:'unknown','class'=>$class,'code'=>$code];
        // At most four existing causes; never parse SQLSTATE or driver text from a message.
        for($cause=$error,$i=0;$cause!==null&&$i<4;$cause=$cause->getPrevious(),$i++){
            if($cause instanceof PDOException){
                $sql=self::sql(['sqlstate'=>$cause->errorInfo[0]??null,'driver'=>$cause->errorInfo[1]??null]);
                if($sql!==null){$out['sql']=$sql;break;}
            }
        }
        return $out;
    }
    public static function line(Throwable $error,string $mode,string $phase):string
    {return self::PREFIX.json_encode(self::record($error,$mode,$phase),JSON_THROW_ON_ERROR)."\n";}
    public static function emit(Throwable $error,string $mode,string $phase,callable $write):void
    {
        // Logging failure cannot change the worker's existing nonzero exit/result decision.
        try{$write(self::line($error,$mode,$phase));}catch(Throwable){}
    }
    public static function parse(string $line):?array
    {
        if(strlen($line)>self::LIMIT||preg_match('/[^\x20-\x7e]/',$line)||!str_starts_with($line,self::PREFIX))return null;
        try{$r=json_decode(substr($line,strlen(self::PREFIX)),true,8,JSON_THROW_ON_ERROR);}catch(Throwable){return null;}
        if(!is_array($r)||array_diff(array_keys($r),['version','mode','phase','class','code','sql'])
            ||count($r)!==(array_key_exists('sql',$r)?6:5)||($r['version']??null)!==1
            ||!in_array($r['mode']??null,self::MODES,true)||!in_array($r['phase']??null,self::PHASES,true)
            ||!in_array($r['class']??null,self::CLASSES,true))return null;
        $codes=match($r['class']){'SiteServiceException'=>[...self::SERVICE,'native_child_failure'],'SiteArtifactException'=>[...self::ARTIFACT,'native_child_failure'],
            'RuntimeException'=>[...self::HARNESS,'native_child_failure'],default=>['native_child_failure']};
        if(!in_array($r['code']??null,$codes,true)||(array_key_exists('sql',$r)&&self::sql($r['sql'])===null))return null;
        // Re-encoding excludes duplicate keys, escaped control data and noncanonical wire forms.
        if(self::PREFIX.json_encode($r,JSON_THROW_ON_ERROR)!==$line)return null;
        return $r;
    }
}
