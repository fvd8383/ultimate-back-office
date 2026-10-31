<?php
declare(strict_types=1);

/** Definitions only. Distinct from every M6B/M6C report; no runtime bootstrap. */
final class M6DNativeContract
{
    public const PROFILE='m6d-db-authority-v1';
    public const CASES=[
        'publish-request-history','restore-request-history','publish-payload-fields','restore-payload-fields',
        'scoped-current-access','concurrent-request-identity','concurrent-target-claims','result-audit-rollback',
        'production-grant-boundaries','production-role-lock','staging-production-lifecycle','known-good-restore',
        'stale-lease-fence','third-execution-recovery','recovery-budget-operator','committed-ack-history',
        'unavailable-result-no-compensation','deleted-recovery-actor',
    ];
    public static function need(bool $value,string $code): void { if(!$value) throw new RuntimeException($code); }
    public static function binding(array $a,string $sha,string $image,string $token): void
    {
        $keys=['profile','source_sha','image_id','run_token','database_name','database_user','database_hostname',
            'runtime_hostname','mysql_server_uuid','worker_uid','deadline_seconds','memory_bytes','pids','storage_bytes','network_isolated'];
        self::need(array_diff(array_keys($a),$keys)===[] && array_diff($keys,array_keys($a))===[],'admission_shape');
        self::need((bool)preg_match('/^[a-f0-9]{40}$/D',$sha) && (bool)preg_match('/^sha256:[a-f0-9]{64}$/D',$image)
            && (bool)preg_match('/^[a-f0-9]{32}$/D',$token),'source_image_token');
        self::need($a['profile']===self::PROFILE && $a['source_sha']===$sha && $a['image_id']===$image && $a['run_token']===$token,'profile_source_image');
        self::need($a['database_name']==='ubo_m6d_'.$token && $a['database_user']==='m6d_'.substr($token,0,16)
            && $a['database_hostname']==='m6d-db-'.substr($token,0,16) && $a['runtime_hostname']==='m6d-run-'.substr($token,0,16)
            && (bool)preg_match('/^[a-f0-9-]{36}$/D',$a['mysql_server_uuid']),'disposable_identity');
        self::need($a['worker_uid']===2000 && $a['deadline_seconds']===600 && $a['memory_bytes']===536870912
            && $a['pids']===32 && $a['storage_bytes']===134217728 && $a['network_isolated']===true,'resource_binding');
    }
    public static function initial(string $sha,string $image,string $token): array
    {
        return ['profile'=>self::PROFILE,'source_sha'=>$sha,'image_id'=>$image,'run_token'=>$token,'status'=>'FAIL',
            'native'=>true,'cases'=>array_fill_keys(self::CASES,['status'=>'UNREACHED']),
            'primary_failure'=>null,'capture'=>'NOT_ATTEMPTED','cleanup'=>'NOT_ATTEMPTED','publication'=>'NOT_ATTEMPTED',
            'limitations'=>['Publisher and artifact verification are explicit DB-only models.',
                'Commit acknowledgement fault is injected after a real PDO commit; not a transport-loss test.',
                'Lease expiry uses scoped SQL metadata faults; not elapsed-clock proof.',
                'No helper, Apache, network health, symlink activation or browser evidence.']];
    }
    public static function complete(array $r): bool
    {
        if(($r['profile'] ?? null)!==self::PROFILE || array_keys($r['cases'] ?? [])!==self::CASES || $r['primary_failure']!==null
            || $r['cleanup']!=='PASS' || $r['capture']!=='PASS') return false;
        foreach($r['cases'] as $c) if(($c['status'] ?? null)!=='PASS' || ($c['assertions'] ?? 0)<1) return false;
        foreach(['concurrent-request-identity','concurrent-target-claims','production-role-lock'] as $key) {
            if(($r['cases'][$key]['lock_wait_observed'] ?? false)!==true) return false;
        }
        return true;
    }
    public static function safeFailure(Throwable $e): array
    {
        $r=['class'=>get_class($e),'code'=>preg_match('/^[a-z0-9_]{1,80}$/D',$e->getMessage()) ? $e->getMessage() : 'native_failure'];
        if($e instanceof PDOException) $r+=['sqlstate'=>$e->errorInfo[0] ?? null,'driver'=>$e->errorInfo[1] ?? null];
        return $r;
    }
}
