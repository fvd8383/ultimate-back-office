<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeCases.php';
require_once __DIR__.'/WebsitePlatformM6DNativeAuthorityCases.php';
require_once __DIR__.'/WebsitePlatformM6DNativeDecisionCases.php';
require_once __DIR__.'/WebsitePlatformM6DNativeSchemaCases.php';
require_once __DIR__.'/WebsitePlatformM6DNativeWireCases.php';

function m6dNativeRows(PDO $db,string $sql,array $p=[]): array
{ $s=$db->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function m6dNativeRow(PDO $db,string $table,array $f,int $id): array
{ return SiteDeploymentStore::row($db,$table,$f['site_id'],$id); }
/** Read-only native observations outside the production deployment-store boundary. */
function m6dNativeObservedRow(PDO $db,string $table,array $f,int $id): array
{
    if(!in_array($table,['site_deployment_approvals','site_revisions'],true))
        throw new LogicException('unsupported_native_observation_table');
    $rows=m6dNativeRows($db,'SELECT * FROM '.$table.' WHERE id = :id AND site_id = :site_id',
        ['id'=>$id,'site_id'=>$f['site_id']]);
    if(count($rows)!==1) throw new RuntimeException('native_observation_row_count');
    return $rows[0];
}
function m6dNativeEvents(PDO $db,array $f,string $type,int $id): int
{
    return (int)m6dNativeRows($db,"SELECT COUNT(*) AS n FROM site_events WHERE site_id=? AND event_type=?
        AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.deployment_id')) AS UNSIGNED)=?",[$f['site_id'],$type,$id])[0]['n'];
}
function m6dNativeModel(array $f): array
{
    $path=M6DNativeRuntime::ROOT.'/state/publisher-'.$f['site_id'].'.json';
    return file_exists($path) ? json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR) : ['pointers'=>[],'fences'=>[],'calls'=>[]];
}
function m6dNativeSnapshot(PDO $db,array $f): string
{
    $rows=[];
    foreach(['site_deployments','site_deployment_attempts','site_deployment_targets','site_deployment_health_checks','site_deployment_approvals','site_events'] as $table)
        $rows[$table]=m6dNativeRows($db,'SELECT * FROM '.$table.' WHERE site_id=? ORDER BY id',[$f['site_id']]);
    return hash('sha256',json_encode([$rows,m6dNativeModel($f)],JSON_THROW_ON_ERROR));
}
function m6dNativeRequestKind(string $kind,int $actor,array $v): array
{ return $kind==='restore' ? SiteDeploymentService::requestRestore($actor,$v) : SiteDeploymentService::requestDeployment($actor,$v); }
function m6dNativeThird(PDO $db,array $op): array
{
    $lease=SiteDeploymentService::claimDeployment([])['lease'];
    for($i=1;$i<3;$i++) {
        SiteDeploymentService::completeDeploymentFailure($lease,['code'=>'storage_unavailable']);
        $db->prepare('UPDATE site_deployments SET next_attempt_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=? AND site_id=?')
            ->execute([$op['deployment_id'],$lease['site_id']]);
        $lease=SiteDeploymentService::claimDeployment([])['lease'];
    }
    $counts=m6dNativeRow($db,'site_deployments',['site_id'=>$lease['site_id']],$op['deployment_id']);
    m6dNativeCheck((int)$counts['execution_count']===3 && (int)$counts['attempt_count']===3,'three_service_created_executions');
    return $lease;
}
function m6dNativeGrant(PDO $db,array $f,array $override=[]): array
{
    $v=m6dNativeInput($db,$f); unset($v['request_key'],$v['reason_code'],$v['deployment_approval_id']);
    $current=m6dNativeRows($db,"SELECT id FROM site_approvals WHERE site_id=? AND revision_id=? AND approval_type='production' AND state='approved' AND revoked_at IS NULL",[$f['site_id'],$f['revision_id']]);
    return SiteApprovalManager::approveProductionRelease($f['operator'],array_replace($v,['operation'=>'publish','ttl_seconds'=>300,
        'supersedes_approval_id'=>$current===[] ? null : (int)$current[0]['id'],'correlation_id'=>'native-exact-grant'],$override));
}
function m6dNativeProductionActors(PDO $db,array $f,M6DNativeDependencies $d): array
{
    $role=(int)m6dNativeRows($db,'SELECT role_id FROM user_roles WHERE user_id=?',[$f['operator']])[0]['role_id'];
    m6mysqlInsert($db,'user_roles',['user_id'=>$f['requester'],'role_id'=>$role]);
    return m6dNativeProduction($db,$f,$d);
}
function m6dNativeExpandedCase(string $case,string $sub,M6DNativeConnection $db,PDO $observer,array $a,array $f,M6DNativeDependencies $d): array
{
    M6DNativeContract::need(in_array($sub,M6DNativeContract::subcases($case),true),'unknown_subcase');
    if(in_array($case,['publish-payload-fields','restore-payload-fields'],true)) return m6dNativeCase($case,$db,$observer,$a,$f,$d,$sub);
    if($sub==='service-observations') return m6dNativeCase($case,$db,$observer,$a,$f,$d);
    return match($case) {
        'authority-boundary-orders'=>m6dNativeAuthorityOrder($sub,$db,$observer,$a,$f,$d),
        'independent-targets'=>m6dNativeIndependent($sub,$db,$observer,$a,$f,$d),
        'publish-request-outcomes','restore-request-outcomes'=>m6dNativeRequestOutcome(str_starts_with($case,'restore') ? 'restore' : 'publish',$sub,$db,$observer,$a,$f,$d),
        'grant-field-bindings'=>m6dNativeGrantField($sub,$db,$observer,$f,$d),
        'grant-phase-denials'=>m6dNativeGrantPhase($sub,$db,$observer,$f,$d),
        'restore-eligibility'=>m6dNativeRestoreCase($sub,$db,$observer,$f,$d),
        'health-compensation'=>m6dNativeHealthCase($sub,$db,$observer,$f,$d),
        'deployment-schema'=>m6dNativeSchemaCase($sub,$db,$observer,$f,$d),
        'deployment-history-access'=>m6dNativeHistoryCase($sub,$db,$observer,$f,$d),
        'result-commit-transport'=>m6dNativeWireCase($sub,$db,$observer,$a,$f,$d),
        default=>throw new RuntimeException('unimplemented_subcase'),
    };
}
