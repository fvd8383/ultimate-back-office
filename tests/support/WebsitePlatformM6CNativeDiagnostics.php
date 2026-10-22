<?php
declare(strict_types=1);

/** Harness child evidence only: fixed forms, bounded prefixes, no raw command/config/JSON. */
final class M6CNativeDiagnostics
{
    public static function summary(array $result):array
    {
        $out=[];
        foreach(['pid','exit','signal'] as $key)$out[$key]=isset($result[$key])&&is_int($result[$key])&&$result[$key]>=0?$result[$key]:null;
        foreach(['signaled','termination_requested','running'] as $key)$out[$key]=is_bool($result[$key]??null)?$result[$key]:null;
        foreach(['stdout','stderr'] as $key){
            $raw=(string)($result[$key]??'');$prefix=substr($raw,0,65536);
            $bytes=max(strlen($raw),(int)($result['stream_bytes'][$key]??0));$known=[];
            foreach(explode("\n",$prefix) as $line){
                $line=rtrim($line,"\r");
                if(preg_match('/^Build-only (?:outcome: (?:succeeded|failed|retry_wait|reconciliation_required|no_work_claimed)|invocation stopped: (?:builder_unavailable|artifact_invalid|database_failure|conflict|unauthorized|invalid_request|future_gate_required|source_not_eligible|requester_not_authorized))\.$/D',$line))$known[]=$line;
                if(count($known)===2)break;
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
            $entry=['case'=>in_array($case,M6CNativeContract::CASES,true)?$case:null,
                'phase'=>$phase,'last_barrier'=>$p->lastBarrier,'expected_exit'=>$expected,'unexpected'=>$unexpected]
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
        try{
            $line=$p->line();
            if(!str_starts_with($line,$expected.' ')){
                self::record($p,$report,$case,'barrier_'.$expected,null,null,true,$write);
                $check(false,'barrier_'.$expected);
            }
            $check(true,'barrier_'.$expected);
            $data=json_decode(substr($line,strlen($expected)+1),true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new RuntimeException('native_barrier_json');
            $p->lastBarrier=$expected;return $data;
        }catch(Throwable $error){
            // Wrong-prefix assertion already emitted; all other barrier failures emit here.
            if($error->getMessage()!=='barrier_'.$expected)self::record($p,$report,$case,'barrier_'.$expected,null,null,true,$write);
            throw $error;
        }
    }
    public static function finish(M6CNativeProcess $p,int $expected,array &$report,?string $case,callable $check,callable $write):array
    {
        try{$result=$p->finish();}
        catch(Throwable $error){self::record($p,$report,$case,'finish',$expected,null,true,$write);throw $error;}
        $ok=$result['exit']===$expected&&!$result['running'];
        self::record($p,$report,$case,'finish',$expected,$result,!$ok,$write);
        $check($ok,'actual_child_exit');return $result;
    }
}
