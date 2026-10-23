<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeWorkerError.php';

/** Harness child evidence only: fixed forms, bounded prefixes, no raw command/config/JSON. */
final class M6CNativeDiagnostics
{
    private const MESSAGES=['IDENTITY','READY','CLAIM','SEALED','VERIFY','RECOVERY','RESULT'];
    public static function context(M6CNativeProcess $p,?string $case,string $mode,?int $expected=0):void
    {
        $p->diagnosticContext=['case'=>in_array($case,M6CNativeContract::CASES,true)?$case:null,
            'mode'=>in_array($mode,[...M6CNativeWorkerError::MODES,'--protected'],true)?$mode:'unknown',
            'expected_exit'=>$expected,'expected_messages'=>[$mode==='--protected'?'IDENTITY':(in_array($mode,['recover','renew-recovery'],true)?'RECOVERY':'READY')]];
    }
    private static function contextSummary(array $context):array
    {
        return ['case'=>in_array($context['case']??null,M6CNativeContract::CASES,true)?$context['case']:null,
            'mode'=>in_array($context['mode']??null,[...M6CNativeWorkerError::MODES,'--protected'],true)?$context['mode']:null,
            'expected_exit'=>is_int($context['expected_exit']??null)&&$context['expected_exit']>=0?$context['expected_exit']:null,
            'expected_messages'=>array_values(array_intersect(self::MESSAGES,$context['expected_messages']??[])),
            'last_barrier'=>in_array($context['last_barrier']??null,self::MESSAGES,true)?$context['last_barrier']:null];
    }
    private static function state(array $state):array
    {
        $out=[];
        foreach(['exit','signal'] as $key)$out[$key]=is_int($state[$key]??null)&&$state[$key]>=0?$state[$key]:null;
        foreach(['signaled','running','termination_requested'] as $key)$out[$key]=is_bool($state[$key]??null)?$state[$key]:null;
        return $out;
    }
    public static function cleanupSummary(array $result):array
    {
        return self::contextSummary($result['context']??[])+[
            'failure_observation'=>isset($result['failure_observation'])?self::state($result['failure_observation']):null,
            'cleanup_observation'=>isset($result['cleanup_observation'])?self::state($result['cleanup_observation']):null]+self::summary($result);
    }
    public static function readAllowed(M6CNativeProcess $p,array $allowed,array &$report,?string $case,callable $check,callable $write):array
    {
        if(!$allowed||array_diff($allowed,self::MESSAGES)||count(array_unique($allowed))!==count($allowed))throw new LogicException('native_expected_messages');
        $p->diagnosticContext['expected_messages']=$allowed;$label='barrier_'.implode('_OR_',$allowed);
        try{
            $line=$p->line();
            if(strlen($line)>8192)throw new RuntimeException('native_barrier_size');
            $parts=explode(' ',$line,2);$kind=$parts[0];
            if(count($parts)!==2||!in_array($kind,$allowed,true))throw new RuntimeException($label);
            $data=json_decode($parts[1],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data)||!self::payload($kind,$data,$p->diagnosticContext['mode']??null))throw new RuntimeException('native_barrier_shape');
            $check(true,$label);$p->lastBarrier=$kind;return [$kind,$data];
        }catch(Throwable $error){
            self::record($p,$report,$case,$label,$p->diagnosticContext['expected_exit']??null,null,true,$write);
            throw $error;
        }
    }
    private static function keys(array $data,array $required,array $optional=[]):bool
    {return !array_diff(array_keys($data),[...$required,...$optional])&&!array_diff($required,array_keys($data));}
    private static function payload(string $kind,array $data,?string $mode):bool
    {
        if(in_array($kind,['READY','VERIFY'],true))return $data===[];
        if(in_array($kind,['CLAIM','SEALED','RECOVERY'],true))return self::keys($data,['job_id','attempt_id'])
            &&is_int($data['job_id'])&&$data['job_id']>0&&is_int($data['attempt_id'])&&$data['attempt_id']>0;
        if($kind==='IDENTITY'){
            if(!self::keys($data,['uid','gid','pid','capabilities'])||!is_int($data['uid'])||$data['uid']<0
                ||!is_int($data['gid'])||$data['gid']<0||!is_int($data['pid'])||$data['pid']<1||!is_array($data['capabilities']))return false;
            $caps=$data['capabilities'];
            if(!self::keys($caps,['CapInh','CapPrm','CapEff','CapBnd','CapAmb','NoNewPrivs']))return false;
            foreach($caps as $key=>$value)if(!is_string($value)||!preg_match('/^[a-f0-9]{1,16}$/D',$value))return false;
            return true; // Existing caller checks the actual UID and zero capabilities.
        }
        if(isset($data['error']))return in_array($mode,['audit','validation','stale'],true)
            &&self::keys($data,['error','code'],['sql'])&&$data['error']==='SiteServiceException'
            &&in_array($data['code'],M6CNativeWorkerError::SERVICE,true)
            &&(!isset($data['sql'])||M6CNativeWorkerError::sql($data['sql'])!==null);
        if(($data['claimed']??null)===false)return self::keys($data,['claimed','builds'])&&$data['builds']===0;
        if(!is_int($data['builds']??null)||$data['builds']<0||$data['builds']>1
            ||!in_array($data['status']??null,['succeeded','failed','retry_wait','reconciliation_required'],true))return false;
        if(isset($data['disposition']))return self::keys($data,['status','disposition','builds'])&&$data['builds']===0
            &&in_array($data['disposition'],['adopted','safely_failed','blocked'],true);
        if(isset($data['failure']))return self::keys($data,['status','failure','builds'])&&in_array($data['failure'],M6CNativeWorkerError::ARTIFACT,true);
        return self::keys($data,['claimed','status','builds'],['replayed'])&&$data['claimed']===true
            &&(!array_key_exists('replayed',$data)||is_bool($data['replayed']));
    }
    public static function summary(array $result):array
    {
        $out=['worker_error'=>null,'source_error'=>null];
        foreach(['pid','exit','signal'] as $key)$out[$key]=isset($result[$key])&&is_int($result[$key])&&$result[$key]>=0?$result[$key]:null;
        foreach(['signaled','termination_requested','running'] as $key)$out[$key]=is_bool($result[$key]??null)?$result[$key]:null;
        foreach(['stdout','stderr'] as $key){
            $raw=(string)($result[$key]??'');$prefix=substr($raw,0,65536);
            $bytes=max(strlen($raw),(int)($result['stream_bytes'][$key]??0));$known=[];
            foreach(explode("\n",$prefix) as $line){
                $error=M6CNativeWorkerError::parse($line);
                if($error!==null){$field=isset($error['source'])?'source_error':'worker_error';if($out[$field]===null)$out[$field]=$error;}
                $line=rtrim($line,"\r"); // Existing production text permits CRLF; strict worker records do not.
                if(count($known)<2&&preg_match('/^Build-only (?:outcome: (?:succeeded|failed|retry_wait|reconciliation_required|no_work_claimed)|invocation stopped: (?:builder_unavailable|artifact_invalid|database_failure|conflict|unauthorized|invalid_request|future_gate_required|source_not_eligible|requester_not_authorized))\.$/D',$line))$known[]=$line;
            }
            $out[$key]=['bytes'=>$bytes,'captured_bytes'=>strlen($prefix),'truncated'=>$bytes>strlen($prefix),
                'sha256'=>hash('sha256',$prefix),'digest_scope'=>$bytes>strlen($prefix)?'captured_prefix':'complete_capture','recognized'=>$known];
        }
        return $out;
    }
    public static function emit(array &$report,string $line,callable $write,string $secondary):void
    {
        // Reporting must never replace the primary test exception/outcome.
        try{$n=$write($line);if($n===false||(is_int($n)&&$n!==strlen($line)))throw new RuntimeException();}
        catch(Throwable){$report[$secondary]='FAIL';}
    }
    public static function record(M6CNativeProcess $p,array &$report,?string $case,string $phase,?int $expected,
        ?array $result,bool $unexpected,callable $write):void
    {
        try{
            if($unexpected)M6CNativeProcess::observeFailure();
            $context=$p->diagnosticContext;$context['case']=$case;$context['last_barrier']=$p->lastBarrier;
            $context['expected_exit']=$expected;
            $entry=self::contextSummary($context)+['phase'=>$phase,'unexpected'=>$unexpected,
                'failure_observation'=>$p->failureObservation===null?null:self::state($p->failureObservation)]
                +self::summary($result??$p->observation());
            $report['children'][]=$entry;
            if(!$unexpected)return;
            $line='M6C CHILD FAILURE '.json_encode($entry,JSON_THROW_ON_ERROR)."\n";
            $used=$report['child_diagnostic_bytes']??0;
            if(strlen($line)>2048||$used+strlen($line)>16384){$report['child_diagnostics_truncated']=true;return;}
            $report['child_diagnostic_bytes']=$used+strlen($line);
            self::emit($report,$line,$write,'child_diagnostic_write');
        }catch(Throwable){$report['child_diagnostic_record']='FAIL';}
    }
    public static function read(M6CNativeProcess $p,string $expected,array &$report,?string $case,callable $check,callable $write):array
    {
        return self::readAllowed($p,[$expected],$report,$case,$check,$write)[1];
    }
    public static function finish(M6CNativeProcess $p,int $expected,array &$report,?string $case,callable $check,callable $write):array
    {
        $p->diagnosticContext['expected_exit']=$expected;
        try{$result=$p->finish();}
        catch(Throwable $error){self::record($p,$report,$case,'finish',$expected,null,true,$write);throw $error;}
        $ok=$result['exit']===$expected&&!$result['running'];
        self::record($p,$report,$case,'finish',$expected,$result,!$ok,$write);
        $check($ok,'actual_child_exit');return $result;
    }
}
