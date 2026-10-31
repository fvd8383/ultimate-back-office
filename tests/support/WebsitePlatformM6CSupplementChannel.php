<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/WebsitePlatformM6CNativeDiagnostics.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementContract.php';

/** Bounded test-child protocol. No raw SQL, tokens, paths or exception messages. */
final class M6CSupplementChannel
{
    private const ERRORS=['supplement_container_only','supplement_attestation','supplement_capabilities','supplement_arguments',
        'supplement_database_binding','supplement_server_binding','supplement_claim','supplement_lease_identity','supplement_lease_mode',
        'stale_owner_accepted','admission_roots','admission_distinct_devices_unavailable','cross_device_accepted','fifo_capability_unavailable','fifo_accepted',
        'fault_chmod','pressure_tmpfs_required','pressure_create','pressure_bound','proof_boundary_unreached','rename_chmod','collision_parent','collision_target',
        'actual_permission_fault_unobserved','actual_space_fault_unobserved','fault_permission_restore','low_space_accepted','pressure_cleanup','foreign_target_changed',
        'probe_create','probe_space_before','probe_unknown_unobserved','probe_cleanup',
        'supplement_payload','supplement_output','supplement_barrier_release',
        'proxy_context','proxy_attestation','proxy_listen','proxy_accept','proxy_backend','proxy_select','proxy_write','proxy_control','proxy_early_eof','proxy_queue_bound','proxy_deadline',
        'wire_mode','wire_already_armed','wire_closed','wire_bound','wire_packet_bound','wire_handshake','wire_unsupported_capabilities','wire_expected_commit','wire_second_command','wire_commit_not_ok'];
    public static function error(Throwable $e,string $phase):void
    {
        // Literal test failures supplement (never replace) the existing privacy-bounded record.
        if(get_class($e)===RuntimeException::class&&in_array($e->getMessage(),self::ERRORS,true))
            fwrite(STDERR,'M6C SUPPLEMENT ERROR '.$e->getMessage()."\n");
        M6CNativeWorkerError::emit($e,'build',$phase,static fn($line)=>fwrite(STDERR,$line));
    }
    private static function errorCode(array $result):?string
    {
        foreach(explode("\n",$result['stderr']??'') as $line){
            if(str_starts_with($line,'M6C SUPPLEMENT ERROR ')){
                $code=substr($line,21);if(in_array($code,self::ERRORS,true))return $code;
            }
        }
        return null;
    }
    public static function payload(string $kind,array $v):bool
    {
        $keys=match($kind){
            'READY','VERIFY','OBSERVE'=>[], 'CLAIM','SEALED','RECOVERY'=>['job_id','attempt_id'],
            'COMMIT','LOCKED','START'=>['connection'], 'LISTEN'=>['port'],
            'ARMED'=>['mode'], 'FAULT'=>['mode','forwarded','ok_received'],
            'BOUNDARY'=>['point','files'], 'SPACE'=>['low','unknown'], 'RESULT'=>['status','builds'],
            default=>null};
        if($keys===null||array_keys($v)!==$keys)return false;
        foreach($v as $key=>$value){
            if(in_array($key,['job_id','attempt_id','connection','port'],true)&&(!is_int($value)||$value<1||($key==='port'&&$value>65535)))return false;
            if(in_array($key,['files','builds'],true)&&(!is_int($value)||$value<0||$value>2000))return false;
            if(in_array($key,['forwarded','ok_received','low','unknown'],true)&&!is_bool($value))return false;
            if($key==='mode'&&!in_array($value,['before-commit','drop-ack','drop-query'],true))return false;
            if($key==='point'&&!in_array($value,['candidate','proof','permission','space','rename','collision'],true))return false;
            if($key==='status'&&!in_array($value,['succeeded','failed','retry_wait','reconciliation_required','blocked','no_claim','unknown','stale_rejected','revoked'],true))return false;
        }
        return true;
    }
    public static function emit(string $kind,array $data=[]):void
    {
        if(!self::payload($kind,$data))throw new RuntimeException('supplement_payload');
        $line=$kind.' '.json_encode($data,JSON_THROW_ON_ERROR)."\n";
        if(fwrite(STDOUT,$line)!==strlen($line)||!fflush(STDOUT))throw new RuntimeException('supplement_output');
    }
    public static function barrier(string $kind,array $data=[]):void
    {
        self::emit($kind,$data);stream_set_timeout(STDIN,15);
        if(fgets(STDIN,8193)!=="CONTINUE\n")throw new RuntimeException('supplement_barrier_release');
    }
    public static function record(M6CNativeProcess $p,array &$report,string $case,?array $result,bool $unexpected):void
    {
        if(!in_array($case,M6CSupplementContract::CASES,true))throw new RuntimeException('supplement_case');
        if($unexpected)M6CNativeProcess::observeFailure();
        $result??=$p->observation();$connection=$p->diagnosticContext['mysql_connection']??null;
        $report['children'][]=['supplement_case'=>$case,'last_barrier'=>$p->lastBarrier,'unexpected'=>$unexpected,
            'connection'=>is_int($connection)&&$connection>0?$connection:null,'supplement_error'=>self::errorCode($result),
            'failure_observation'=>$p->failureObservation]+M6CNativeDiagnostics::summary($result);
    }
    public static function read(M6CNativeProcess $p,string $expected,array &$report,string $case,int $seconds=15):array
    {
        $p->diagnosticContext['supplement_case']=$case;
        try{
            $line=$p->line($seconds);if(strlen($line)>8192)throw new RuntimeException('supplement_barrier_size');
            $parts=explode(' ',$line,2);$v=count($parts)===2?json_decode($parts[1],true,8,JSON_THROW_ON_ERROR):null;
            if($parts[0]!==$expected||!is_array($v)||!self::payload($expected,$v)
                ||$line!==$expected.' '.json_encode($v,JSON_THROW_ON_ERROR))throw new RuntimeException('supplement_barrier_shape');
            $p->lastBarrier=$expected;
            $report['observations'][]=['case'=>$case,'pid'=>$p->pid,'kind'=>$expected,'data'=>$v]; // Only the strict, private-data-free payload above.
            return $v;
        }catch(Throwable $e){self::record($p,$report,$case,null,true);throw $e;}
    }
    public static function cleanupAfterFailure(array &$report):array
    {
        M6CNativeProcess::observeFailure();
        $results=M6CNativeProcess::cleanup();
        foreach($results as $r){
            $case=$r['context']['supplement_case']??null;
            if(!in_array($case,M6CSupplementContract::CASES,true))continue;
            $report['children'][]=['supplement_case'=>$case,'last_barrier'=>$r['context']['last_barrier']??null,
                'unexpected'=>true,'cleanup_after_failure'=>true,'supplement_error'=>self::errorCode($r),
                'failure_observation'=>$r['failure_observation']??null,'cleanup_observation'=>$r['cleanup_observation']??null]
                +M6CNativeDiagnostics::summary($r);
        }
        return $results;
    }
    public static function finish(M6CNativeProcess $p,array &$report,string $case,int $exit=0):array
    {
        try{$r=$p->finish();}catch(Throwable $e){self::record($p,$report,$case,null,true);throw $e;}
        $bad=$r['exit']!==$exit||$r['signaled']||$r['termination_requested'];
        self::record($p,$report,$case,$r,$bad);
        if($bad)throw new RuntimeException('supplement_child_exit');return $r;
    }
}
