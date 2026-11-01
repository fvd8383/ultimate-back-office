<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeContract.php';
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';

/** Same bounded framing is used by admitted workers and local synthetic children. */
final class M6DNativeProtocol
{
    public static function identity(array $a): array
    { return array_intersect_key($a,array_flip(['profile','source_sha','image_id','run_token'])); }
    public static function ready(M6CNativeProcess $p): int
    {
        $line=$p->line(10); M6DNativeContract::need((bool)preg_match('/^READY ([1-9][0-9]{0,9})$/D',$line,$m),'child_ready');
        return (int)$m[1];
    }
    public static function barrier(string $marker): void
    {
        $release=match($marker) {'LOCKED'=>'RELEASE','COMMITTED','BOUNDARY'=>'CONTINUE','ROLLED_BACK'=>'RETRY',default=>throw new RuntimeException('unknown_barrier')};
        echo $marker."\n"; flush();
        M6DNativeContract::need(trim((string)fgets(STDIN))===$release,'barrier_release');
    }
    public static function frame(array $identity,mixed $value): string
    {
        $json=json_encode(['identity'=>$identity,'value'=>$value],JSON_THROW_ON_ERROR);
        M6DNativeContract::need(strlen($json)<=8192,'child_result_bound');
        return 'RESULT '.$json;
    }
    public static function decode(string $line,array $identity): mixed
    {
        M6DNativeContract::need(strlen($line)<=8199 && !preg_match('/[\x00-\x1f\x7f]/',$line) && str_starts_with($line,'RESULT '),'child_result_shape');
        try { $r=json_decode(substr($line,7),true,16,JSON_THROW_ON_ERROR); }
        catch(JsonException) { throw new RuntimeException('child_result_json'); }
        M6DNativeContract::need(json_encode($r,JSON_THROW_ON_ERROR)===substr($line,7),'child_result_encoding');
        M6DNativeContract::need(is_array($r) && array_keys($r)===['identity','value'] && $r['identity']===$identity,'child_result_binding');
        self::safeValue($r['value']); return $r['value'];
    }
    private static function safeValue(mixed $v): void
    {
        if(is_array($v)) foreach($v as $key=>$value) {
            M6DNativeContract::need(!is_string($key) || (preg_match('/^[a-z_]{1,64}$/D',$key) && !preg_match('/token|password|secret|^lease$/',$key)),'child_private_key');
            self::safeValue($value);
        }
        elseif(is_string($v)) M6DNativeContract::need(strlen($v)<=200 && !preg_match('/[\x00-\x1f\x7f]/',$v),'child_private_value');
        else M6DNativeContract::need($v===null || is_bool($v) || is_int($v),'child_value_type');
    }
    public static function result(M6CNativeProcess $p,array $identity,int $pdoDiagnostics=0): mixed
    {
        $line=$p->line(10); $done=$p->line(10); $r=$p->finish();
        $GLOBALS['m6d_native_child_results'][]=array_intersect_key($r,array_flip(['pid','exit','signaled','signal','termination_requested','running','stream_bytes']));
        M6DNativeContract::need($r['exit']===0 && !$r['signaled'] && !$r['termination_requested'] && $done==='DONE'
            && str_ends_with($r['stdout'],"\nDONE\n"),'native_child_completion');
        M6DNativeContract::need($r['stderr']===str_repeat("Website M2 database operation failed: PDOException\n",$pdoDiagnostics),'native_child_diagnostics');
        return self::decode($line,$identity);
    }
    public static function privateRecord(string $path,array $value): void
    {
        $json=json_encode($value,JSON_THROW_ON_ERROR); M6DNativeContract::need(strlen($json)<=8192,'fixture_record_bound');
        $old=umask(0077); $f=fopen($path,'xb'); umask($old); M6DNativeContract::need(is_resource($f),'fixture_record');
        try { M6DNativeContract::need(fwrite($f,$json)===strlen($json) && fflush($f),'fixture_record_write'); }
        finally { fclose($f); }
    }
}
