<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeWireEvidence.php';
require_once __DIR__.'/WebsitePlatformM6DNativeConstraintFailure.php';

/** Definitions only. Distinct from every M6B/M6C report; no runtime bootstrap. */
final class M6DNativeContract
{
    public const PROFILE='m6d-db-authority-v3';
    public const CASES=[
        'publish-request-history','restore-request-history','publish-payload-fields','restore-payload-fields',
        'scoped-current-access','concurrent-request-identity','concurrent-target-claims','result-audit-rollback',
        'production-grant-boundaries','production-role-lock','staging-production-lifecycle','known-good-restore',
        'stale-lease-fence','third-execution-recovery','recovery-budget-operator','committed-ack-history',
        'unavailable-result-no-compensation','deleted-recovery-actor',
        'authority-boundary-orders','independent-targets','publish-request-outcomes','restore-request-outcomes',
        'grant-field-bindings','grant-phase-denials','restore-eligibility','health-compensation',
        'deployment-schema','deployment-history-access','result-commit-transport',
    ];
    /** Ordered finite acceptance inventory. v1/v2 cannot satisfy the v3 transport gate. */
    public static function subcases(string $case): array
    {
        self::need(in_array($case,self::CASES,true),'unknown_case');
        return match($case) {
            'result-commit-transport'=>['unarmed-success','commit-not-delivered','ack-withheld','authoritative-unavailable'],
            'publish-payload-fields'=>['release','operation','approval','binding','pointer','version','reason'],
            'restore-payload-fields'=>['release','operation','restore-source','approval','binding','pointer','version','reason'],
            'authority-boundary-orders'=>[
                'requester-claim-denial-first','requester-claim-work-first',
                'requester-activation-denial-first','requester-activation-work-first',
                'requester-success-denial-first','requester-success-work-first',
                'approver-claim-denial-first','approver-claim-work-first',
                'approver-activation-denial-first','approver-activation-work-first',
                'approver-success-denial-first','approver-success-work-first',
                'grant-claim-denial-first','grant-claim-work-first','grant-activation-denial-first','grant-activation-work-first',
                'grant-success-denial-first','grant-success-work-first'],
            'independent-targets'=>['different-sites','same-site-environments','occupied-target-progress'],
            'publish-request-outcomes','restore-request-outcomes'=>['failed-history','cancelled-history','reconciliation-history',
                'lost-response','duplicate-winner-pending','duplicate-winner-advanced','access-loss'],
            'grant-field-bindings'=>['site','release','source','artifact','target','environment','operation','binding',
                'expected-pointer','expected-version','missing','consumed-history'],
            'grant-phase-denials'=>['expiry-claim','expiry-activation','expiry-success','revocation-claim','revocation-activation',
                'revocation-success','approver-claim','approver-activation','approver-success'],
            'restore-eligibility'=>['never-successful','foreign-site','wrong-target','artifact-identity','tenant-prohibited','business-prohibited','asset-rights','target-disabled',
                'superseded-approval','production-fresh-grant'],
            'health-compensation'=>['candidate-failure','active-health-failure','third-null-absence','compensation-failed',
                'compensation-unknown','newer-winner','activation-audit-grant','result-audit-grant','third-grant-expired','third-grant-revoked',
                'health-status','health-site','health-release','health-artifact','health-marker','health-page','health-css',
                'health-media','health-origin','health-tls','health-redirect','health-cache','health-no-store','health-timeout'],
            'deployment-schema'=>['target-owner','release-owner','expected-target','restore-target','current-target','active-target',
                'request-unique','attempt-parent','attempt-original','current-attempt','execution-four','execution-shape',
                'recovery-shape','counter-shape','actor-fk','history-restrict','health-attempt','grant-consumption'],
            'deployment-history-access'=>['operator-deletion','queue-index','history-index'],
            default=>['service-observations'],
        };
    }
    public static function ordering(string $case,string $subcase): ?string
    {
        if($case==='authority-boundary-orders') return str_ends_with($subcase,'denial-first') ? 'denial-before-boundary' : 'boundary-before-denial';
        if(in_array($case,['concurrent-request-identity','concurrent-target-claims','production-role-lock'],true)) return 'lock-serialized';
        if($case==='independent-targets' && $subcase==='different-sites') return 'independent-progress';
        return null;
    }
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
        $cases=[]; foreach(self::CASES as $case) $cases[$case]=['status'=>'UNREACHED',
            'subcases'=>array_fill_keys(self::subcases($case),['status'=>'UNREACHED'])];
        return ['profile'=>self::PROFILE,'source_sha'=>$sha,'image_id'=>$image,'run_token'=>$token,'status'=>'FAIL',
            'native'=>true,'cases'=>$cases,
            'primary_failure'=>null,'capture'=>'NOT_ATTEMPTED','cleanup'=>'NOT_ATTEMPTED','publication'=>'NOT_ATTEMPTED',
            'children_cleanup'=>'NOT_ATTEMPTED','schema_cleanup'=>'NOT_ATTEMPTED',
            'limitations'=>['Publisher and artifact verification are explicit DB-only models.',
                'committed-ack-history is post-success-PDO API loss; result-commit-transport separately requires wire evidence.',
                'Lease expiry uses scoped SQL metadata faults; not elapsed-clock proof.',
                'No helper, Apache, network health, symlink activation or browser evidence.']];
    }
    public static function complete(array $r,?array $identity=null): bool
    {
        if(($r['profile'] ?? null)!==self::PROFILE || array_keys($r['cases'] ?? [])!==self::CASES || !array_key_exists('primary_failure',$r) || $r['primary_failure']!==null
            || ($r['cleanup'] ?? null)!=='PASS' || ($r['children_cleanup'] ?? null)!=='PASS' || ($r['schema_cleanup'] ?? null)!=='PASS'
            || ($r['capture'] ?? null)!=='PASS' || ($r['native'] ?? null)!==true || ($identity!==null && ($identity['profile'] ?? null)!==self::PROFILE)) return false;
        foreach(['source_sha'=>'/^[a-f0-9]{40}$/D','image_id'=>'/^sha256:[a-f0-9]{64}$/D','run_token'=>'/^[a-f0-9]{32}$/D'] as $key=>$pattern) {
            if(!is_string($r[$key] ?? null) || !preg_match($pattern,$r[$key]) || ($identity!==null && $r[$key]!==($identity[$key] ?? null))) return false;
        }
        foreach($r['cases'] as $key=>$c) {
            if(($c['status'] ?? null)!=='PASS' || !is_int($c['assertions'] ?? null) || $c['assertions']<1
                || array_keys($c['subcases'] ?? [])!==self::subcases($key)) return false;
            $total=0;
            foreach($c['subcases'] as $sub=>$s) {
                if(($s['status'] ?? null)!=='PASS' || !is_int($s['assertions'] ?? null) || $s['assertions']<1) return false;
                $total+=$s['assertions']; $order=self::ordering($key,$sub);
                if($order!==null && !self::ordered($s['ordering'] ?? [],$order)) return false;
                if($key==='result-commit-transport' && (!is_int($s['site_id'] ?? null) || $s['site_id']<1
                    || !M6DNativeWireEvidence::valid($s['transport'] ?? null,$sub,array_intersect_key($r,array_flip(['profile','source_sha','image_id','run_token'])),$s['site_id'])))return false;
            }
            if($total!==$c['assertions']) return false;
        }
        return true;
    }
    public static function ordered(array $r,string $order): bool
    {
        return array_keys($r)===['kind','observed','first_connection','second_connection','first_committed','second_completed']
            && $r['kind']===$order && $r['observed']===true && is_int($r['first_connection']) && $r['first_connection']>0
            && is_int($r['second_connection']) && $r['second_connection']>0 && $r['first_connection']!==$r['second_connection']
            && $r['first_committed']===true && $r['second_completed']===true;
    }
    /** Published bytes have one canonical pretty-JSON encoding, so duplicate keys cannot disappear during parsing. */
    public static function acceptPublished(string $json,array $identity): array
    {
        self::need(strlen($json)<=1048576,'report_bound');
        try { $r=json_decode($json,true,32,JSON_THROW_ON_ERROR); } catch(JsonException) { throw new RuntimeException('report_json'); }
        self::need(is_array($r) && json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)===$json,'report_canonical_encoding');
        self::need(($r['status'] ?? null)==='PASS' && ($r['publication'] ?? null)==='PASS' && self::complete($r,$identity),'report_not_accepted');
        return $r;
    }
    public static function safeFailure(Throwable $e): array
    {
        if($e instanceof M6DNativeConstraintFailure) return ['class'=>M6DNativeConstraintFailure::class,
            'code'=>$e->getMessage(),'constraint_diagnostic'=>$e->diagnostic()];
        $class=get_class($e); $r=['class'=>preg_match('/^[A-Za-z][A-Za-z0-9_\\\\]{0,100}$/D',$class) ? $class : 'Throwable',
            'code'=>preg_match('/^[a-z0-9_]{1,80}$/D',$e->getMessage()) ? $e->getMessage() : 'native_failure'];
        if($e instanceof PDOException) $r+=['sqlstate'=>preg_match('/^[A-Z0-9]{5}$/D',(string)($e->errorInfo[0] ?? '')) ? $e->errorInfo[0] : null,
            'driver'=>is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : null];
        return $r;
    }
}
