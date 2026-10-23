<?php
declare(strict_types=1);
// Included only after the coordinator has verified the disposable server and source.
if(!isset($admin,$config,$identity)||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo')throw new RuntimeException('native_cases_internal_only');
function m6nNoSuccess(array $f): void
{
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_releases WHERE build_job_id=?',[$f['job_id']])===0,'no_partial_release');
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_release_validations WHERE build_attempt_id IN (SELECT id FROM site_build_attempts WHERE build_job_id=?)',[$f['job_id']])===0,'no_partial_validation');
    m6nCheck((int)m6nScalar("SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type='site_build_succeeded'",[$f['site_id']])===0,'no_partial_success_event');
}
function m6nStem(array $f,int $attempt): string
{global $config;return $config['work_root'].'/'.m6nJob($f)['job_key'].'/attempt-'.$attempt;}
function m6nBlocked(array $f,int $attempt): void
{
    m6nExpire($attempt);[$p]=m6nRecover($f);$r=m6nResult($p);
    m6nCheck($r['disposition']==='blocked'&&$r['builds']===0,'uncertain_evidence_never_regenerated');
    $job=m6nJob($f);m6nCheck($job['status']==='reconciliation_required'&&$job['recovery_status']==='blocked','persisted_blocked_recovery');
    m6nNoSuccess($f);
}
function m6nWaitDatabaseTime(callable $ready,int $seconds,string $purpose): void
{
    global $report;$start=hrtime(true);$end=$start+$seconds*1000000000;
    while(!$ready()){
        if(hrtime(true)>=$end)throw new RuntimeException('elapsed_lease_deadline');
        usleep(250000); // Bounded observation of the real server clock, not an expiry mutation.
    }
    $report['real_elapsed_waits'][]=['purpose'=>$purpose,'seconds'=>(hrtime(true)-$start)/1e9];
}
m6nCase('concurrent-claims','instrumented',function()use($admin,$config):void{
    global $report,$current;
    $f=m6nFixture($admin,$config);$children=[m6nStart($f),m6nStart($f)];
    foreach($children as $p)m6nRead($p,'READY');
    foreach($children as $p){$p->diagnosticContext['expected_messages']=['CLAIM','RESULT'];$p->send('CONTINUE');}
    $winners=[];
    foreach($children as $p){
        [$kind,$r]=M6CNativeDiagnostics::readAllowed($p,['CLAIM','RESULT'],$report,$current,'m6nCheck',static fn($line)=>fwrite(STDERR,$line));
        if($kind==='CLAIM'){m6nCheck($r['job_id']===$f['job_id'],'concurrent_claim_identity');$winners[]=$p;}
        else{m6nCheck($kind==='RESULT'&&$r['claimed']===false&&$r['builds']===0,'concurrent_loser_no_authority');m6nFinish($p);}
    }
    m6nCheck(count($winners)===1,'one_concurrent_owner');$p=$winners[0];$p->send('CONTINUE');m6nRead($p,'SEALED');$p->send('CONTINUE');$r=m6nResult($p);
    m6nCheck($r['status']==='succeeded'&&$r['builds']===1&&$r['replayed']===true,'one_render_and_exact_completion_replay');
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_build_attempts WHERE build_job_id=?',[$f['job_id']])===1,'one_concurrent_attempt');m6nVerify($f);
});
foreach(['requester','source'] as $denial)m6nCase($denial.'-denied-after-claim','instrumented',function()use($admin,$config,$denial):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nClaim($f,'deny');
    if($denial==='requester')$admin->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);
    else $admin->prepare("UPDATE site_revisions SET lifecycle_status='draft' WHERE id=?")->execute([$f['revision_id']]);
    $p->send('CONTINUE');$r=m6nResult($p);$job=m6nJob($f);$attempt=m6nAttempt($claim['attempt_id']);
    m6nCheck($r['status']==='failed'&&$r['builds']===0&&$attempt['status']==='failed'&&(int)$job['execution_count']===1,'denial_preserves_and_settles_claim');
    m6nCheck($job['failure_code']===($denial==='requester'?'requester_not_authorized':'source_not_eligible'),'specific_preoutput_denial');
    m6nCheck(!file_exists($config['work_root'].'/'.$job['job_key'])&&!file_exists(m6nReleasePath($f)),'preoutput_denial_no_files');
    foreach(['site_build_started','site_build_failed'] as $event)m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type=?',[$f['site_id'],$event])===1,'atomic_denial_audit');
    m6nNoSuccess($f);
});
foreach(['loss','corruption'] as $fault)m6nCase('asset-'.$fault.'-after-claim','instrumented',function()use($admin,$config,$fault):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nClaim($f,$fault==='loss'?'asset-loss':'asset-corrupt');
    if($fault==='loss')m6nCheck(unlink($f['asset_path']),'remove_owned_fixture_asset');
    else{chmod($f['asset_path'],0644);file_put_contents($f['asset_path'],'invalid approved bytes');chmod($f['asset_path'],0444);}
    $p->send('CONTINUE');$r=m6nResult($p);
    m6nCheck($r['status']==='failed'&&$r['failure']==='artifact_invalid'&&$r['builds']===1,'asset_integrity_settlement');
    [$e,$job,$producer]=m6nOutput($f);$journal=json_decode(file_get_contents(m6nStem($f,$claim['attempt_id']).'.json'),true,32,JSON_THROW_ON_ERROR);
    m6nCheck($journal['binding']['job_id']===$f['job_id']&&$journal['binding']['producer_attempt_id']===$claim['attempt_id'],'real_durable_producer_journal');
    m6nCheck($e['disposition']==='quarantined'&&$producer['status']==='failed'&&$job['failure_category']==='integrity','asset_failure_quarantine_policy');m6nNoSuccess($f);
});
m6nCase('execution-verification-renewal','instrumented',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nSeal($f,'renew-execution');$p->send('CONTINUE');m6nRead($p,'VERIFY');
    $id=$claim['attempt_id'];$before=m6nAttempt($id);
    $admin->prepare('UPDATE site_build_attempts SET lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 SECOND) WHERE id=?')->execute([$id]);$short=m6nAttempt($id)['lease_expires_at'];
    $p->send('CONTINUE');$r=m6nResult($p);$after=m6nAttempt($id);
    m6nCheck($r['status']==='succeeded'&&$after['lease_expires_at']>$short&&$after['heartbeat_at']>=$before['heartbeat_at']&&$after['deadline_at']===$before['deadline_at'],'forced_verification_pulse_renews_execution_only');m6nVerify($f);
});
m6nCase('recovery-verification-renewal','instrumented',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nSeal($f);m6nKill($p);m6nExpire($claim['attempt_id']);$producer=m6nAttempt($claim['attempt_id']);
    [$p,$recovery]=m6nRecover($f,'renew-recovery');m6nRead($p,'VERIFY');$id=$recovery['attempt_id'];$before=m6nAttempt($id);
    $admin->prepare('UPDATE site_build_attempts SET lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 SECOND) WHERE id=?')->execute([$id]);$short=m6nAttempt($id)['lease_expires_at'];
    $p->send('CONTINUE');$r=m6nResult($p);$after=m6nAttempt($id);
    m6nCheck($r['disposition']==='adopted'&&$r['builds']===0&&$after['lease_expires_at']>$short&&$after['deadline_at']===$before['deadline_at'],'forced_verification_pulse_renews_recovery');
    m6nCheck(m6nAttempt($claim['attempt_id'])['lease_expires_at']===$producer['lease_expires_at'],'producer_lease_not_revived');m6nVerify($f);
});
m6nCase('stale-completion','instrumented',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nSeal($f,'stale');$tree=m6nTree(m6nReleasePath($f));m6nExpire($claim['attempt_id']);
    $p->send('CONTINUE');$r=m6nResult($p);m6nCheck(($r['code']??'')==='conflict','stale_owner_cannot_complete');
    m6nNoSuccess($f);m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'stale_completion_preserves_immutable_files');
});
m6nCase('killed-before-journal','instrumented',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nClaim($f,'hold');m6nKill($p);
    m6nCheck(!file_exists(m6nStem($f,$claim['attempt_id']).'.json'),'terminated_before_journal');m6nBlocked($f,$claim['attempt_id']);
    m6nCheck(!file_exists(m6nStem($f,$claim['attempt_id']).'.json')&&!file_exists(m6nReleasePath($f)),'recovery_does_not_invent_producer_evidence');
});
$interruptedFixture=null;$interruptedClaim=null;$interruptedTree=null;$interruptedProducer=null;
m6nCase('killed-after-seal','instrumented',function()use($admin,$config,&$interruptedFixture,&$interruptedClaim,&$interruptedTree,&$interruptedProducer):void{
    $interruptedFixture=m6nFixture($admin,$config);[$p,$interruptedClaim]=m6nSeal($interruptedFixture);
    $interruptedProducer=m6nAttempt($interruptedClaim['attempt_id']);$interruptedTree=m6nTree(m6nReleasePath($interruptedFixture));m6nCheck(count($interruptedTree)===8,'sealed_before_actual_kill');
    m6nKill($p);m6nNoSuccess($interruptedFixture);
});
m6nCase('fresh-process-adoption','protected',function()use(&$interruptedFixture,&$interruptedClaim,&$interruptedTree,&$interruptedProducer):void{
    $f=$interruptedFixture;$id=$interruptedClaim['attempt_id'];
    m6nWaitDatabaseTime(static fn()=>(int)m6nScalar('SELECT lease_expires_at<=UTC_TIMESTAMP(6) FROM site_build_attempts WHERE id=?',[$id])===1,125,'real producer lease expiry; no timestamp mutation');
    $r=m6nProtected($f,2000,['--recover',(string)$f['job_id']]);m6nCheck(str_contains($r['stdout'],'no_work_claimed'),'automatic_recovery_backoff');
    m6nWaitDatabaseTime(static fn()=>(int)m6nScalar('SELECT next_recovery_at<=UTC_TIMESTAMP(6) FROM site_build_jobs WHERE id=?',[$f['job_id']])===1,35,'real automatic recovery backoff');
    $r=m6nProtected($f,2000,['--recover',(string)$f['job_id']]);m6nCheck(str_contains($r['stdout'],'outcome: succeeded.'),'fresh_protected_recovery_adopts');
    $producer=m6nAttempt($id);m6nCheck($producer['status']==='expired'&&$producer['lease_expires_at']===$interruptedProducer['lease_expires_at']&&$producer['heartbeat_at']===$interruptedProducer['heartbeat_at'],'no_producer_revival');
    m6nCheck((int)m6nJob($f)['execution_count']===1&&(int)m6nScalar('SELECT COUNT(*) FROM site_build_attempts WHERE build_job_id=?',[$f['job_id']])===2,'one_execution_one_recovery');
    m6nCheck(m6nTree(m6nReleasePath($f))===$interruptedTree,'adoption_without_rerender');m6nVerify($f);
});
foreach(['missing','tampered','ambiguous'] as $fault)m6nCase($fault.'-evidence','instrumented',function()use($admin,$config,$fault):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nSeal($f);m6nKill($p);$stem=m6nStem($f,$claim['attempt_id']);
    if($fault==='missing')m6nCheck(unlink($stem.'.json'),'remove_owned_journal');
    elseif($fault==='ambiguous'){file_put_contents($stem.'.json.pending','ambiguous synthetic interrupted write');chmod($stem.'.json.pending',0600);}
    else{
        // Namespace setup DAC_OVERRIDE permits this explicit owned-fixture byte fault;
        // preserve the worker's ownership/mode (no CAP_FOWNER is needed or granted).
        $path=m6nReleasePath($f).'/public/index.html';$bytes='tampered owned synthetic artifact';
        m6nCheck(file_put_contents($path,$bytes)===strlen($bytes),'tamper_owned_artifact_bytes');
    }
    $tree=m6nTree(m6nReleasePath($f));$work=m6nTree(dirname($stem));m6nBlocked($f,$claim['attempt_id']);
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree&&m6nTree(dirname($stem))===$work,'blocked_recovery_does_not_repair_evidence');
});
foreach(['audit','validation'] as $fault)m6nCase($fault.'-rollback','instrumented',function()use($admin,$config,$fault):void{
    $f=m6nFixture($admin,$config);[$p,$claim]=m6nSeal($f,$fault);$tree=m6nTree(m6nReleasePath($f));$before=m6nJob($f);
    $p->send('CONTINUE');$r=m6nResult($p);
    m6nCheck(($r['code']??'')==='database_failure'&&($r['sql']['sqlstate']??'')===($fault==='audit'?'23000':'HY000')
        &&($r['sql']['driver']??0)===($fault==='audit'?1452:3819),'real_mysql_constraint_failure');
    global $report;$report['sql_faults'][]=['fault'=>$fault,'sql'=>$r['sql']];
    m6nNoSuccess($f);m6nCheck(m6nJob($f)===$before&&m6nAttempt($claim['attempt_id'])['completed_at']===null,'completion_transaction_rolled_back');
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'rollback_preserves_sealed_files');
});
m6nCase('no-publication','instrumented',function():void{
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM sites WHERE current_published_revision_id IS NOT NULL')===0,'no_public_revision_pointer');
    foreach(['site_deployments','site_deployment_targets','site_deployment_health_checks'] as $table)
        m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM '.$table)===0,'no_publication_rows_'.$table);
    m6nCheck((int)m6nScalar("SELECT COUNT(*) FROM site_approvals WHERE approval_type IN ('production','conversion')")===0,'no_publication_approval');
});
