<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DNativeFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeExpansion.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeControl.php';

/** Statement/data spy only. Does not implement MySQL constraint selection. */
final class M6DSchemaPDO extends PDO
{
    public array $calls=[];
    public array $rows=[];
    public int $begins=0,$rollbacks=0,$commits=0;
    public bool $transaction=false,$rollbackThrows=false,$rollbackFalse=false;
    public ?PDOException $error=null;
    private array $backup=[];
    public function __construct(?M6DDatabase $service=null) { if($service!==null) $this->rows=&$service->tables; }
    public function beginTransaction(): bool { $this->begins++; $this->backup=$this->rows; return $this->transaction=true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function commit(): bool { $this->commits++; throw new LogicException('unexpected_schema_commit'); }
    public function rollBack(): bool
    {
        $this->rollbacks++;
        if($this->rollbackThrows) throw new PDOException('PRIVATE_ROLLBACK_SQL_DSN_TOKEN');
        if($this->rollbackFalse) return false;
        $this->rows=$this->backup; $this->transaction=false; return true;
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $this->calls[]=['prepare',$query];
        return new class($this,$query) extends PDOStatement {
            private array $result=[]; private int $affected=0;
            public function __construct(private M6DSchemaPDO $db,private string $sql) {}
            public function execute(?array $params=null): bool
            {
                $this->db->calls[]=['execute',$this->sql,$params];
                if($this->db->error!==null) throw $this->db->error;
                if(preg_match('/^SELECT (\*|id) FROM (site_deployment\w+) WHERE (\w+) = :(\w+) AND site_id = :site_id$/D',$this->sql,$m)) {
                    foreach($this->db->rows[$m[2]] ?? [] as $row) {
                        if(($row[$m[3]] ?? null)===$params[$m[4]] && $row['site_id']===$params['site_id'])
                            $this->result[]=$m[1]==='*' ? $row : ['id'=>$row['id']];
                    }
                } elseif(preg_match('/^UPDATE (site_deployment\w+) SET (.+) WHERE id\s*=\s*:(owned_id|id) AND site_id\s*=\s*:(owned_site_id|site_id)$/D',$this->sql,$m)) {
                    $values=[];
                    foreach(explode(',',$m[2]) as $set) {
                        if(!preg_match('/^\s*(\w+)\s*=\s*:(\w+)\s*$/D',$set,$field)) throw new LogicException('unmodeled_assignment');
                        $values[$field[1]]=$params[$field[2]];
                    }
                    foreach($this->db->rows[$m[1]] as &$row) {
                        if($row['id']===$params[$m[3]] && $row['site_id']===$params[$m[4]]) {
                            $row=array_replace($row,$values); $this->affected++;
                        }
                    }
                    unset($row);
                } else throw new LogicException('unmodeled_schema_sql');
                return true;
            }
            public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return array_shift($this->result) ?? false; }
            public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->result; }
            public function rowCount(): int { return $this->affected; }
        };
    }
}
$assertions=0;
$check=static function(bool $ok,string $code) use(&$assertions): void { $assertions++; if(!$ok) throw new RuntimeException($code); };
$deny=static function(callable $call,string $class,string $code) use($check): Throwable {
    try { $call(); } catch(Throwable $e) { $check(get_class($e)===$class && $e->getMessage()===$code,'exact_rejection_'.$code); return $e; }
    throw new RuntimeException('missing_expected_rejection_'.$code);
};
$pdoError=static function(?array $info): PDOException {
    $e=new PDOException('PRIVATE_SQL_VALUES_DSN_PASSWORD_LEASE_TOKEN'); $e->errorInfo=$info; return $e;
};
$fk=static fn(string $id): string=>'Cannot add or update a child row: CONSTRAINT `'.$id.'` FOREIGN KEY (`private_column`) REFERENCES `private_table` (`id`)';
$writes=[
    ['site_deployment_targets',['current_deployment_id'=>99],'UPDATE site_deployment_targets SET current_deployment_id=:current_deployment_id WHERE id=:id AND site_id=:site_id'],
    ['site_deployment_targets',['active_deployment_id'=>99],'UPDATE site_deployment_targets SET active_deployment_id=:active_deployment_id WHERE id=:id AND site_id=:site_id'],
    ['site_deployment_approvals',['consumed_by_deployment_id'=>99,'consumed_at'=>'2026-01-01 00:00:00.000000'],'UPDATE site_deployment_approvals SET consumed_by_deployment_id=:consumed_by_deployment_id, consumed_at=:consumed_at WHERE id=:id AND site_id=:site_id'],
];
foreach([false,true] as $transaction) foreach($writes as [$table,$values,$sql]) {
    $observer=new M6DSchemaPDO(); $service=new M6DSchemaPDO(); $observer->transaction=$transaction;
    $observer->rows[$table]=[['id'=>7,'site_id'=>10],['id'=>7,'site_id'=>20],['id'=>8,'site_id'=>20]];
    $before=$observer->rows;
    $deny(fn()=>SiteDeploymentStore::update($observer,$table,['id'=>7,'site_id'=>10],$values),LogicException::class,'Not a deployment-owned table.');
    $check($observer->calls===[] && $observer->rows===$before,'old_path_rejected_before_sql');
    m6dNativeSchemaWrite($observer,$table,$values,7,10);
    $check($observer->calls===[['prepare',$sql],['execute',$sql,$values+['id'=>7,'site_id'=>10]]],'exact_literal_scoped_bound_statement');
    $check($observer->rows[$table][0]===array_replace($before[$table][0],$values)
        && array_slice($observer->rows[$table],1)===array_slice($before[$table],1),'only_exact_owned_row_changes');
    $before=$observer->rows;
    foreach([8,999] as $id) $deny(fn()=>m6dNativeSchemaWrite($observer,$table,$values,$id,10),RuntimeException::class,'schema_negative_owned_row');
    $check($observer->rows===$before,'foreign_and_missing_id_zero_mutation');
    $check($observer->transaction===$transaction && $observer->begins+$observer->rollbacks+$observer->commits===0
        && $service->calls===[],'observer_only_no_transaction_management');
}
foreach([
    ['sites',['active_deployment_id'=>1]],['site_deployments',['target_id'=>1]],['site_deployment_targets WHERE 1=1',['active_deployment_id'=>1]],
    ['site_deployment_targets',[]],['site_deployment_targets',['id'=>1]],['site_deployment_targets',['site_id'=>20]],
    ['site_deployment_targets',['active_deployment_id'=>1,'current_deployment_id'=>1]],
    ['site_deployment_targets',['active_deployment_id=:x --'=>1]],['site_deployment_approvals',['consumed_by_deployment_id'=>1]],
    ['site_deployment_approvals',['consumed_by_deployment_id'=>1,'consumed_at'=>'now','release_id'=>1]],
] as [$table,$values]) {
    $observer=new M6DSchemaPDO();
    $deny(fn()=>m6dNativeSchemaWrite($observer,$table,$values,7,10),LogicException::class,'unsupported_schema_negative_write');
    $check($observer->calls===[] && $observer->begins+$observer->commits+$observer->rollbacks===0,'unsupported_write_before_sql');
}

// Finite existing inventory, tied to canonical names and the actual switch (not new native requirements).
$probes=[
    'target-owner'=>[1452,'fk_sd_target'],'release-owner'=>[1452,'fk_sd_release'],'expected-target'=>[1452,'fk_sd_expected'],
    'restore-target'=>[1452,'fk_sd_restore'],'current-target'=>[1452,'fk_sdt_current'],'active-target'=>[1452,'fk_sdt_active'],
    'request-unique'=>[1062,'uq_sd_request'],'attempt-parent'=>[1452,'fk_sda_parent'],'attempt-original'=>[1452,'fk_sda_original'],
    'current-attempt'=>[1452,'fk_sd_current_attempt'],'execution-four'=>[3819,'chk_sda_shape'],'execution-shape'=>[3819,'chk_sda_shape'],
    'recovery-shape'=>[3819,'chk_sda_shape'],'counter-shape'=>[3819,'chk_sd_counters'],'actor-fk'=>[1452,'fk_sda_actor'],
    'history-restrict'=>[1451,'fk_sd_current_attempt'],'health-attempt'=>[1452,'fk_sdhc_attempt'],'grant-consumption'=>[1452,'fk_sdap_consumed'],
];
$check(array_keys($probes)===M6DNativeContract::subcases('deployment-schema'),'all_18_existing_probes');
$source=file_get_contents(__DIR__.'/support/WebsitePlatformM6DNativeSchemaCases.php');
$schema=file_get_contents(dirname(__DIR__).'/database/migrations/025_site_build_deployment.sql');
$deploymentSchema=substr($schema,strpos($schema,'CREATE TABLE site_deployment_targets'));
preg_match_all('/(?:CONSTRAINT|UNIQUE KEY) (\w+)/',$deploymentSchema,$identifiers);
$check($identifiers[1]===M6DNativeConstraintFailure::IDENTIFIERS,'canonical_identifier_allowlist_exact');
preg_match_all("/case '([^']+)':(.*?)(?=case '|default:)/s",$source,$branches,PREG_SET_ORDER);
$check(array_column($branches,1)===array_keys($probes),'source_exact_finite_schema_dispatch');
foreach($branches as $branch) {
    [$driver,$name]=$probes[$branch[1]];
    $check(str_contains($branch[2],"\$constraint='$name'") && ($driver===1452 || str_contains($branch[2],"\$driver=$driver;")),'source_exact_constraint_'.$branch[1]);
    if(in_array($branch[1],['current-target','active-target','grant-consumption'],true))
        $check(str_contains($branch[2],'m6dNativeSchemaWrite($observer,') && str_contains($branch[2],"\$f['site_id']")
            && !str_contains($branch[2],'$update('),'actual_cross_owner_dispatch_uses_observer');
    $message=match($driver) {1062=>"Duplicate entry 'PRIVATE_VALUE' for key 'site_deployments.$name'",3819=>"Check constraint '$name' is violated.",default=>$fk($name)};
    $observer=new M6DSchemaPDO(); $observer->error=$pdoError([$driver===3819 ? 'HY000' : '23000',$driver,$message]);
    m6dNativeConstraint($observer,fn()=>m6dNativeSchemaWrite($observer,...[$writes[0][0],$writes[0][1],7,10]),$driver,$name,$branch[1]);
    $check(count($observer->calls)===2 && $observer->begins===1 && $observer->rollbacks===1 && !$observer->inTransaction() && $observer->commits===0,'exact_tuple_and_rollback_'.$branch[1]);
}

$failures=[
    'wrong_driver'=>['23000',1451,$fk('fk_sd_target')],
    'wrong_state'=>['HY000',1452,$fk('fk_sd_target')],
    'wrong_constraint'=>['23000',1452,$fk('fk_sd_release')],
    'competing_reference_MODEL'=>['23000',1451,$fk('fk_sdt_active')],
    'missing_metadata'=>null,'missing_message'=>['23000',1452],
    'unrelated_pdo'=>['42000',1064,'PRIVATE_SQL_SYNTAX_VALUES'],
    'unrecognized'=>['23000',1452,$fk('private_arbitrary_identifier')],
    'bare_name'=>['23000',1452,'PRIVATE fk_sd_target'],
    'wrong_name_with_expected_in_value'=>['23000',1452,$fk('fk_sdt_active').' PRIVATE fk_sd_target'],
    'multiple_names'=>['23000',1452,$fk('fk_sdt_active').' '.$fk('fk_sd_target')],
    'oversize'=>['23000',1452,str_repeat('x',4097).$fk('fk_sd_target')],
    'string_driver'=>['23000','1452',$fk('fk_sd_target')],
    'malformed_fields'=>[['PRIVATE'],['PRIVATE'],['PRIVATE']],
    'object_fields'=>[(object)['PRIVATE'=>1],(object)['PRIVATE'=>1],(object)['PRIVATE'=>1]],
    'control_fields'=>["23000\nPRIVATE",1452,"CONSTRAINT `fk_sd_target\nPRIVATE` FOREIGN KEY"],
    'large_driver'=>['23000',PHP_INT_MAX,$fk('fk_sd_target')],
];
foreach($failures as $label=>$info) {
    $observer=new M6DSchemaPDO(); $observer->error=$pdoError($info); $before=$observer->rows;
    $e=$deny(fn()=>m6dNativeConstraint($observer,fn()=>m6dNativeSchemaWrite($observer,$writes[0][0],$writes[0][1],7,10),1452,'fk_sd_target','target-owner'),
        M6DNativeConstraintFailure::class,'exact_constraint_fk_sd_target');
    $safe=M6DNativeContract::safeFailure($e); $diag=$safe['constraint_diagnostic']; $json=json_encode($safe,JSON_THROW_ON_ERROR);
    $check(!str_contains(strtolower($json),'private') && !str_contains($json,'CONSTRAINT') && strlen($json)<900,'bounded_private_diagnostic_'.$label);
    $check($diag['case']==='deployment-schema' && $diag['subcase']==='target-owner' && $diag['expected']===['sqlstate'=>'23000','driver'=>1452,'identifier'=>'fk_sd_target']
        && $diag['invalid_sql_attempted'] && !$diag['unexpected_success'] && $diag['rollback']==='PASS','exact_expected_context_'.$label);
    $check($observer->rows===$before && !$observer->transaction && $observer->rollbacks===1 && $observer->commits===0,'failure_rollback_'.$label);
    if($label==='competing_reference_MODEL') {
        $check($diag['observed']===['sqlstate'=>'23000','driver'=>1451,'identifier'=>'fk_sdt_active','identifier_status'=>'recognized'],'competing_reference_never_equivalent');
        echo 'EXPECTED NEGATIVE MODEL: '.json_encode($safe,JSON_THROW_ON_ERROR)."\n";
    }
    if($label==='wrong_driver') $check($diag['observed']['driver']===1451,'observed_driver_not_expected');
    if($label==='wrong_state') $check($diag['observed']['sqlstate']==='HY000','observed_state_not_expected');
    if($label==='wrong_constraint') $check($diag['observed']['identifier']==='fk_sd_release','observed_constraint_not_expected');
    if($label==='missing_metadata') $check($diag['observed']['sqlstate']===null && $diag['observed']['driver']===null,'missing_tuple_not_expected');
    if(in_array($label,['missing_metadata','missing_message'],true)) $check($diag['observed']['identifier_status']==='missing','missing_identifier_explicit');
    if(in_array($label,['unrecognized','bare_name','multiple_names','oversize','malformed_fields','object_fields','control_fields'],true))
        $check($diag['observed']['identifier']===null && $diag['observed']['identifier_status']==='unrecognized','unknown_identifier_not_expected');
}
$observer=new M6DSchemaPDO(); $observer->rows['site_deployment_targets']=[['id'=>7,'site_id'=>10]]; $before=$observer->rows;
$e=$deny(fn()=>m6dNativeConstraint($observer,fn()=>m6dNativeSchemaWrite($observer,$writes[0][0],$writes[0][1],7,10),1452,'fk_sd_target','target-owner'),
    M6DNativeConstraintFailure::class,'constraint_not_enforced');
$check($e->diagnostic()['unexpected_success'] && $e->diagnostic()['observed']['identifier']===null && $observer->rows===$before,'successful_invalid_dml_fails_and_rolls_back');
$copy=$e->diagnostic(); $copy['observed']=['PRIVATE'=>'PRIVATE']; $copy['subcase']='PRIVATE';
$check(!str_contains(json_encode(M6DNativeContract::safeFailure($e)),'PRIVATE'),'diagnostic_copy_cannot_inject_fields');

foreach(['throw','false'] as $mode) {
    $observer=new M6DSchemaPDO(); $observer->rollbackThrows=$mode==='throw'; $observer->rollbackFalse=$mode==='false';
    $observer->error=$pdoError(['23000',1451,$fk('fk_sdt_active')]);
    $e=$deny(fn()=>m6dNativeConstraint($observer,fn()=>m6dNativeSchemaWrite($observer,$writes[0][0],$writes[0][1],7,10),1452,'fk_sd_target','target-owner'),
        M6DNativeConstraintFailure::class,'exact_constraint_fk_sd_target');
    $report=M6DNativeContract::initial(str_repeat('a',40),'sha256:'.str_repeat('b',64),str_repeat('c',32));
    $report['primary_failure']=$primary=M6DNativeContract::safeFailure($e);
    M6DNativeControl::cleanup($report,true,static fn()=>[],static fn()=>throw new RuntimeException('PRIVATE_SCHEMA_CLEANUP'));
    M6DNativeControl::publish($report,static fn()=>throw new RuntimeException('PRIVATE_REPORTING_SQL'));
    $check($report['primary_failure']===$primary && $primary['constraint_diagnostic']['observed']['identifier']==='fk_sdt_active'
        && $primary['constraint_diagnostic']['rollback']==='FAIL' && $primary['constraint_diagnostic']['rollback_failure']['code']==='constraint_rollback_failed','primary_survives_secondary_failures');
    $check($report['status']==='FAIL' && $report['schema_cleanup']==='FAIL' && $report['publication']==='FAIL'
        && !M6DNativeContract::complete($report) && !str_contains(json_encode($report),'PRIVATE'),'secondary_errors_separate_incomplete_never_accepted');
    $check($observer->rollbacks===1 && $observer->commits===0,'rollback_attempt_guaranteed');
}
$observer=new M6DSchemaPDO(); $observer->transaction=true;
$deny(fn()=>m6dNativeConstraint($observer,static fn()=>null,1452,'fk_sd_target'),LogicException::class,'constraint_requires_own_transaction');
$check($observer->begins+$observer->rollbacks===0 && $observer->transaction,'caller_transaction_untouched');
foreach([[1452,'PRIVATE'],[123,'fk_sd_target']] as [$driver,$constraint]) {
    $observer=new M6DSchemaPDO();
    $deny(fn()=>m6dNativeConstraint($observer,static fn()=>null,$driver,$constraint),LogicException::class,'unsupported_constraint_expectation');
    $check($observer->begins===0,'bad_expectation_before_transaction');
}
$deny(fn()=>m6dNativeConstraint(new M6DSchemaPDO(),static fn()=>null,1452,'fk_sd_target','PRIVATE'),LogicException::class,'unsupported_constraint_subcase');

// Construct through the actual service, independently of invalid mutation. Existing active history stays intact.
$service=new M6DDatabase(); $existing=$service->request(); $lease=$service->claim()['lease'];
$observer=new M6DSchemaPDO($service); $f=['site_id'=>10,'target_id'=>20,'release_id'=>$service->releaseId(),'requester'=>1];
$activeBefore=$service->tables['site_deployment_targets'][20]; $attemptsBefore=$service->tables['site_deployment_attempts'];
$unclaimed=m6dNativeUnclaimedRequest($service,$observer,$f); $id=$unclaimed['deployment_id'];
$check($id!==$existing['deployment_id'] && $service->tables['site_deployments'][$id]['status']==='requested','dedicated_service_created_request');
$check($service->tables['site_deployment_targets'][20]===$activeBefore && $service->tables['site_deployment_attempts']===$attemptsBefore
    && $activeBefore['active_deployment_id']===$existing['deployment_id'],'existing_active_slot_and_history_untouched');
$baseline=$service->snapshot(); $queriesBefore=count($service->queries); $observer->calls=[];
$observer->error=$pdoError(['23000',1452,$fk('fk_sd_target')]);
m6dNativeConstraint($observer,fn()=>SiteDeploymentStore::update($observer,'site_deployments',['id'=>$id,'site_id'=>10],['target_id'=>999]),1452,'fk_sd_target','target-owner');
$check($service->snapshot()===$baseline && count($service->queries)===$queriesBefore,'target_mutation_only_on_observer_rolled_back');
$check($observer->calls[1]===['execute','UPDATE site_deployments SET target_id = :target_id WHERE id = :owned_id AND site_id = :owned_site_id',
    ['target_id'=>999,'owned_id'=>$id,'owned_site_id'=>10]],'only_target_tuple_deliberately_invalid');
$observer->error=null;
$incoming=[
    'site_deployment_targets'=>['current_deployment_id','active_deployment_id'],
    'site_deployments'=>['previous_deployment_id','expected_current_deployment_id','restored_from_deployment_id'],
    'site_deployment_attempts'=>['deployment_id'],'site_deployment_health_checks'=>['deployment_id'],
    'site_deployment_approvals'=>['expected_current_deployment_id','consumed_by_deployment_id'],
];
foreach($incoming as $table=>$columns) foreach($columns as $column) {
    $saved=$observer->rows;
    $observer->rows[$table][99999]=['id'=>99999,'site_id'=>10,$column=>$id];
    $deny(fn()=>m6dNativeUnreferencedRequest($observer,$f,$id),RuntimeException::class,'target_owner_no_incoming_'.$table.'_'.$column);
    $observer->rows=$saved;
}
foreach(['current_attempt_id','expected_current_deployment_id','previous_deployment_id','restored_from_deployment_id','deployment_approval_id'] as $column) {
    $observer->rows['site_deployments'][$id][$column]=999;
    $deny(fn()=>m6dNativeUnreferencedRequest($observer,$f,$id),RuntimeException::class,'target_owner_no_outgoing_'.$column);
    $observer->rows['site_deployments'][$id][$column]=null;
}
$deny(fn()=>m6dNativeUnreferencedRequest($observer,$f,$existing['deployment_id']),RuntimeException::class,'target_owner_valid_unclaimed_request');
// Derive the incoming-reference columns from canonical DDL to keep the finite isolation guard complete.
preg_match_all('/(?:CREATE TABLE (\w+)|ALTER TABLE (\w+)|CONSTRAINT (\w+) FOREIGN KEY \(([^)]+)\) REFERENCES site_deployments \()/',$schema,$ddl,PREG_SET_ORDER);
$actualIncoming=[]; $table=null;
foreach($ddl as $entry) {
    if(($entry[1] ?? '')!=='') $table=$entry[1];
    elseif(($entry[2] ?? '')!=='') $table=$entry[2];
    else $actualIncoming[$table][]=explode(',',$entry[4])[0];
}
ksort($incoming); ksort($actualIncoming);
foreach($incoming as &$columns) sort($columns); unset($columns);
foreach($actualIncoming as &$columns) sort($columns); unset($columns);
$check($incoming===$actualIncoming,'all_canonical_incoming_references_guarded');
echo "M6D native schema: $assertions assertions PASS (synthetic PDO/data only; MySQL NOT EXECUTED).\n";
