<?php
declare(strict_types=1);

/** Test-only, bounded constraint evidence. Raw PDO messages/arguments are never retained. */
final class M6DNativeConstraintFailure extends RuntimeException
{
    public const IDENTIFIERS=[
        'uq_sdt_site_environment','uq_sdt_binding','uq_sdt_id_site','chk_sdt_environment','chk_sdt_enabled',
        'chk_sdt_reconciliation','fk_site_deployment_targets_site','uq_sd_deployment_key','uq_sd_request',
        'uq_sd_id_site','uq_sd_id_target_site','fk_sd_target','fk_sd_release','fk_sd_previous','fk_sd_expected',
        'fk_sd_restore','fk_sd_requester','chk_sd_operation','chk_sd_status','chk_sd_counters','chk_sd_recovery',
        'fk_site_deployments_site','chk_sda_receipt','uq_sda_number','uq_sda_execution','uq_sda_recovery',
        'uq_sda_operator','uq_sda_id_site','uq_sda_id_parent_site','fk_sda_parent','fk_sda_original','fk_sda_actor',
        'chk_sda_number','chk_sda_lease','chk_sda_status','chk_sda_shape','fk_site_deployment_attempts_site',
        'uq_sdhc_check_key','uq_sdhc_id_site','fk_sdhc_deployment','fk_sdhc_attempt','fk_sdhc_release',
        'chk_sdhc_phase','chk_sdhc_result','chk_sdhc_expected','chk_sdhc_summary','fk_site_deployment_health_checks_site',
        'uq_sdap_approval','uq_sdap_id_site','uq_sdap_binding','fk_sdap_approval','fk_sdap_release','fk_sdap_target',
        'fk_sdap_expected','chk_sdap_operation','chk_sdap_consumed','fk_site_deployment_approvals_site',
        'fk_sdt_current','fk_sdt_active','fk_sd_current_attempt','fk_sd_approval','fk_sdap_consumed',
    ];
    private array $diagnostic;

    public static function expected(int $driver,string $constraint): array
    {
        if(!in_array($driver,[1062,1451,1452,3819],true) || !in_array($constraint,self::IDENTIFIERS,true))
            throw new LogicException('unsupported_constraint_expectation');
        return ['sqlstate'=>$driver===3819 ? 'HY000' : '23000','driver'=>$driver,'identifier'=>$constraint];
    }
    public static function observed(PDOException $e): array
    {
        $info=$e->errorInfo ?? []; $state=$info[0] ?? null; $driver=$info[1] ?? null; $message=$info[2] ?? null;
        $r=['sqlstate'=>is_string($state) && preg_match('/^[A-Z0-9]{5}$/D',$state) ? $state : null,
            'driver'=>is_int($driver) && $driver>=0 && $driver<=65535 ? $driver : null,
            'identifier'=>null,'identifier_status'=>$message===null || $message==='' ? 'missing' : 'unrecognized'];
        // Recognize diagnostic grammar, not an arbitrary occurrence in SQL, values or server prose.
        if(!is_string($message) || strlen($message)>4096) return $r;
        $patterns=[
            '/\bCONSTRAINT `([a-z0-9_]{1,64})` FOREIGN KEY\b/',
            "/\bCheck constraint '([a-z0-9_]{1,64})' is violated\./",
            "/\bfor key '(?:[a-z0-9_]{1,64}\\.)?([a-z0-9_]{1,64})'$/D",
        ];
        $ids=[];
        foreach($patterns as $pattern) {
            preg_match_all($pattern,$message,$matches);
            foreach($matches[1] as $id) $ids[]=$id;
        }
        if(count($ids)===1 && in_array($ids[0],self::IDENTIFIERS,true))
            [$r['identifier'],$r['identifier_status']]=[$ids[0],'recognized'];
        return $r;
    }
    public function __construct(int $driver,string $constraint,?PDOException $observed,?string $sub=null)
    {
        $expected=self::expected($driver,$constraint);
        if($sub!==null && !in_array($sub,M6DNativeContract::subcases('deployment-schema'),true))
            throw new LogicException('unsupported_constraint_subcase');
        parent::__construct($observed===null ? 'constraint_not_enforced' : 'exact_constraint_'.$constraint);
        $this->diagnostic=['case'=>'deployment-schema','subcase'=>$sub,'expected'=>$expected,
            'observed'=>$observed===null ? ['sqlstate'=>null,'driver'=>null,'identifier'=>null,'identifier_status'=>'missing'] : self::observed($observed),
            'invalid_sql_attempted'=>true,'unexpected_success'=>$observed===null,'rollback'=>'NOT_ATTEMPTED'];
    }
    public function rolledBack(bool $success): void
    {
        $this->diagnostic['rollback']=$success ? 'PASS' : 'FAIL';
        if(!$success) $this->diagnostic['rollback_failure']=['code'=>'constraint_rollback_failed'];
    }
    public function diagnostic(): array { return $this->diagnostic; }
}
