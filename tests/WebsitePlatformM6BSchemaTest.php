<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BSql.php';
require_once __DIR__ . '/support/WebsitePlatformM6BScope.php';
$root=dirname(__DIR__); $assertions=0;
function m6s(bool $ok,string $why): void {global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
$sql=file_get_contents($root.'/database/migrations/025_site_build_deployment.sql');
$statements=m6bSqlStatements($sql); $tables=[];
foreach($statements as $statement)if(preg_match('/^CREATE TABLE (\w+)/',$statement,$m))$tables[$m[1]]=$statement;
$expected=['site_build_jobs','site_build_attempts','site_releases','site_release_validations','site_deployment_targets',
    'site_deployments','site_deployment_attempts','site_deployment_health_checks','site_deployment_approvals'];
m6s(array_keys($tables)===$expected,'Exact nine-table dependency order');
m6s(m6bOnlyMigration025($root),'Only authorized migration025');
m6s(count($statements)===15,'Nine CREATEs, two existing ownership indexes, four deferred FK ALTERs');
foreach($tables as $name=>$ddl){
    m6s(str_contains($ddl,'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY') && str_contains($ddl,'site_id BIGINT UNSIGNED NOT NULL'),'Exact ID types '.$name);
    m6s(str_contains($ddl,'created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'),'Microsecond history '.$name);
    m6s(str_contains($ddl,'FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT'),'Site owner '.$name);
    m6s(!preg_match('/ON DELETE CASCADE|ON UPDATE CASCADE|ENUM\(/i',$ddl),'History not cascaded '.$name);
}
foreach(['site_build_jobs','site_deployments']as$name){
    foreach(['attempt_count = execution_count + recovery_count','max_execution_attempts BETWEEN 1 AND 3',
        'execution_count <= max_execution_attempts','automatic_recovery_count <= max_automatic_recoveries',
        'automatic_recovery_count <= recovery_count','max_automatic_recoveries BETWEEN 0 AND 2']as$check){
        m6s(str_contains($tables[$name],$check),'Separate counters '.$name.' '.$check);
    }
    m6s(!preg_match('/attempt_count\s*<=\s*3/',$tables[$name]),'No total-attempt cap');
}
foreach(['site_build_attempts','site_deployment_attempts']as$name){
    $ddl=$tables[$name];
    m6s(str_contains($ddl,'FOREIGN KEY (recovery_authorized_by_user_id) REFERENCES users (id) ON DELETE SET NULL'),'Historical actor nullable FK '.$name);
    preg_match_all('/CONSTRAINT\s+\w+\s+CHECK\s*\(/',$ddl,$matches,PREG_OFFSET_CAPTURE);
    foreach($matches[0]as[$start,$offset]){
        $i=$offset+strlen($start);$depth=1;$expression='';
        while($depth>0){$c=$ddl[$i++];if($c==='(')$depth++;if($c===')')$depth--;if($depth>0)$expression.=$c;}
        m6s(!str_contains($expression,'recovery_authorized_by_user_id'),'Actor excluded from EVERY CHECK '.$name);
    }
    foreach(['execution_number IS NOT NULL','recovery_number IS NOT NULL','recovery_of_attempt_id IS NOT NULL',
        'recovery_trigger IS NOT NULL','recovery_actor_type IS NOT NULL','recovery_reason_code IS NOT NULL',
        'operator_request_key IS NOT NULL','operator_request_key IS NULL']as$check)m6s(str_contains($ddl,$check),'Explicit non-UNKNOWN shape '.$name);
}
m6s(str_contains($tables['site_deployment_targets'],'enabled TINYINT(1) NOT NULL DEFAULT 0'),'Targets disabled by default');
m6s(!preg_match('/^\s*(?:INSERT|UPDATE|DELETE|TRUNCATE|DROP|SET\s+(?:FOREIGN_KEY_CHECKS|CHECK_CONSTRAINT_CHECKS))\b/im',implode("\n",$statements)),'Additive unseeded schema with enforcement intact');
m6s(str_contains($tables['site_deployments'],'UNIQUE KEY uq_sd_request (site_id, target_id, request_key)')
    && !preg_match('/UNIQUE[^\n]+request_payload_hash/',$tables['site_deployments']),'Request identity distinct from fingerprint');
m6s(str_contains($sql,'ADD UNIQUE KEY uq_site_business_id_site_business (id, site_id, business_id)')
    && str_contains($sql,'ADD UNIQUE KEY uq_site_approvals_id_revision_site (id, revision_id, site_id)'),'Both reviewed existing ownership indexes');
foreach(glob($root.'/database/migrations/*.sql')as$file)m6s(count(m6bSqlStatements(file_get_contents($file)))>0,'Canonical SQL splitter '.$file);
m6s(m6bSqlStatements("SELECT 'a;b'; -- comment;\n SELECT 'it''s'; /* c; */ SELECT \"z;\";")===["SELECT 'a;b'","SELECT 'it''s'",'SELECT "z;"'],'SQL scanner respects quoted semicolons/comments');
echo "Website platform M6B schema contract (not MySQL execution): $assertions assertions passed.\n";
