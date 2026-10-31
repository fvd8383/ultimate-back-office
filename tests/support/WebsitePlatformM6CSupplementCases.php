<?php
declare(strict_types=1);
// Included only after the shared coordinator's source, namespace, DB and profile attestation.
if(!isset($admin,$config,$identity,$profile)||$profile!==M6CSupplementContract::VERSION
    ||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo')throw new RuntimeException('supplement_internal_only');
require_once __DIR__.'/WebsitePlatformM6CSupplementChannel.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementPdf.php';

function m6sStart(array $f,string $mode='build',int $port=0,int $attempt=0,string $key='-'):M6CNativeProcess
{
    global $current,$report;
    if(in_array($mode,['replay','observe-unavailable'],true))$key=m6nJob($f)['release_key']; // Receipt hint only; the child must establish DB outcome itself.
    $p=new M6CNativeProcess(['/usr/bin/setpriv','--reuid=2000','--regid=2000','--clear-groups','--no-new-privs',
        '--bounding-set=-all','--inh-caps=-all','--ambient-caps=-all',PHP_BINARY,'-d','memory_limit=128M',
        '-d','display_errors=stderr','-d','zend.exception_ignore_args=1',__DIR__.'/WebsitePlatformM6CSupplementWorker.php',
        $mode,(string)$f['job_id'],(string)$f['operator'],(string)$port,(string)$attempt,$key],90);
    $p->diagnosticContext=['mode'=>'build','expected_exit'=>0];
    $data=M6CSupplementChannel::read($p,'START',$report,$current);$p->diagnosticContext['mysql_connection']=$data['connection'];return $p;
}
function m6sRead(M6CNativeProcess $p,string $kind):array
{global $current,$report;return M6CSupplementChannel::read($p,$kind,$report,$current);}
function m6sResultOf(M6CNativeProcess $p):array
{global $current,$report;$r=m6sRead($p,'RESULT');M6CSupplementChannel::finish($p,$report,$current);return $r;}
function m6sKill(M6CNativeProcess $p):void
{global $current,$report;$r=$p->stop(9);$ok=$r['termination_requested']&&!$r['running']&&$r['signaled']&&$r['signal']===9;
    M6CSupplementChannel::record($p,$report,$current,$r,!$ok);m6nCheck($ok,'supplement_actual_SIGKILL');}
function m6sClaim(array $f,string $mode='build',int $number=1,int $port=0):array
{
    $p=m6sStart($f,$mode,$port);m6sRead($p,'READY');$p->send('CONTINUE');$c=m6sRead($p,'CLAIM');
    $a=m6nAttempt($c['attempt_id']);m6nCheck($c['job_id']===$f['job_id']&&(int)$a['execution_number']===$number
        &&(int)m6nJob($f)['execution_count']===$number,'service_created_execution_number');return [$p,$c];
}
function m6sSeal(array $f,string $mode='build',int $number=1,int $port=0):array
{[$p,$c]=m6sClaim($f,$mode,$number,$port);$p->send('CONTINUE');m6sRead($p,'SEALED');return [$p,$c];}
function m6sDue(array $f,string $field):void
{
    global $admin,$report,$current;if(!in_array($field,['next_attempt_at','next_recovery_at'],true))throw new RuntimeException('supplement_due_field');
    $before=m6nJob($f);m6nCheck($before[$field]!==null,'controlled_backoff_was_scheduled');
    $admin->prepare('UPDATE site_build_jobs SET '.$field.'=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?')->execute([$f['job_id']]);
    $after=m6nJob($f);unset($before[$field],$after[$field]);m6nCheck($before===$after,'controlled_backoff_changes_only_due_time');
    $report['controlled_backoff'][]=['case'=>$current,'field'=>$field,'method'=>'due timestamp only; no elapsed wait'];
}
function m6sThird(array $f,string $mode='build',int $port=0):array
{
    for($i=1;$i<3;$i++){
        [$p,$c]=m6sClaim($f,'transient',$i);$p->send('CONTINUE');$b=m6sRead($p,'BOUNDARY');$r=m6sResultOf($p);
        m6nCheck($b['point']==='candidate'&&$b['files']>0&&$r===['status'=>'retry_wait','builds'=>1],'actual_partial_output_transient_settlement');
        m6sNoSuccess($f);m6sDue($f,'next_attempt_at');
    }
    return m6sSeal($f,$mode,3,$port);
}
function m6sCounts(array $f,int $total,int $execution,int $recovery):void
{$j=m6nJob($f);m6nCheck([(int)$j['attempt_count'],(int)$j['execution_count'],(int)$j['recovery_count']]===[$total,$execution,$recovery],'exact_total_execution_recovery_counts');}
function m6sNoSuccess(array $f):void
{
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_releases WHERE build_job_id=?',[$f['job_id']])===0,'no_partial_release');
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_release_validations WHERE build_attempt_id IN (SELECT id FROM site_build_attempts WHERE build_job_id=?)',[$f['job_id']])===0,'no_partial_validation');
    m6nCheck((int)m6nScalar("SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type='site_build_succeeded'",[$f['site_id']])===0,'no_partial_success_event');
}
function m6sProbeRecovery(array $f):void
{
    $p=m6sStart($f,'recover');m6nCheck(m6sResultOf($p)===['status'=>'no_claim','builds'=>0],'expiry_records_without_immediate_automatic_claim');
    if(m6nJob($f)['recovery_status']==='required')m6sDue($f,'next_recovery_at');
}
function m6sRecovery(array $f,string $mode='recover',string $key='-'):array
{$p=m6sStart($f,$mode,0,0,$key);$c=m6sRead($p,'RECOVERY');return [$p,$c];}
function m6sRecoverDone(array $f):array
{[$p,$c]=m6sRecovery($f);$p->send('CONTINUE');$r=m6sResultOf($p);m6nCheck($r['builds']===0,'recovery_no_render');return [$r,$c];}
function m6sState(array $f,?PDO $db=null):array
{
    global $admin;$db??=$admin;$out=[];
    foreach(['job'=>'SELECT * FROM site_build_jobs WHERE id=?','attempts'=>'SELECT * FROM site_build_attempts WHERE build_job_id=? ORDER BY id',
        'releases'=>'SELECT * FROM site_releases WHERE build_job_id=? ORDER BY id',
        'validations'=>'SELECT * FROM site_release_validations WHERE build_attempt_id IN (SELECT id FROM site_build_attempts WHERE build_job_id=?) ORDER BY id'] as $name=>$sql){$q=$db->prepare($sql);$q->execute([$f['job_id']]);$out[$name]=$q->fetchAll();}
    $q=$db->prepare('SELECT * FROM site_events WHERE site_id=? ORDER BY id');$q->execute([$f['site_id']]);$out['events']=$q->fetchAll();return $out;
}
function m6sFresh():PDO
{
    global $identity;$env=Database::config();
    $p=new PDO('mysql:host='.$identity['db_ip'].';port=3306;dbname='.$env['DB_NAME'].';charset=utf8mb4',$env['DB_USER'],$env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>2]);
    m6nCheck($p->query('SELECT @@server_uuid')->fetchColumn()===$identity['server_uuid'],'fresh_authoritative_connection_identity');return $p;
}
function m6sLockWait(int $waiting,int $blocking):void
{
    global $admin,$report,$current;$end=hrtime(true)+3000000000;
    do{$q=$admin->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?');$q->execute([$waiting,$blocking]);
        if((int)$q->fetchColumn()>0){$report['lock_waits'][]=['case'=>$current,'waiting_connection'=>$waiting,'blocking_connection'=>$blocking];m6nCheck(true,'observed_independent_mysql_lock_wait');return;}usleep(10000);
    }while(hrtime(true)<$end);throw new RuntimeException('acceptance_lock_wait_unreached');
}
function m6sProxy(string $mode):array
{
    global $current,$report;
    $p=new M6CNativeProcess(['/usr/bin/setpriv','--reuid=2000','--regid=2000','--clear-groups','--no-new-privs','--bounding-set=-all','--inh-caps=-all','--ambient-caps=-all',
        PHP_BINARY,'-d','memory_limit=128M','-d','display_errors=stderr','-d','zend.exception_ignore_args=1',__DIR__.'/WebsitePlatformM6CSupplementProxy.php',$mode],90);
    $p->diagnosticContext=['mode'=>'build','expected_exit'=>0];$v=m6sRead($p,'LISTEN');return [$p,$v['port']];
}
function m6sArm(M6CNativeProcess $p,string $mode):void
{$p->send('ARM');m6nCheck(m6sRead($p,'ARMED')===['mode'=>$mode],'one_target_connection_armed');}
function m6sFault(M6CNativeProcess $p,string $mode):void
{
    global $current,$report;$v=m6sRead($p,'FAULT');m6nCheck($v===['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack'],'observed_wire_fault_boundary');
    $report['wire_faults'][]=['case'=>$current]+$v;M6CSupplementChannel::finish($p,$report,$current);
}
function m6sCase(string $name,callable $body):void
{
    global $current,$report;$current=$name;$start=hrtime(true);$before=$report['assertions'];
    if(!in_array($name,M6CSupplementContract::CASES,true)||$report['cases'][$name]['status']!=='UNREACHED')throw new RuntimeException('supplement_case_inventory');
    $report['cases'][$name]=['status'=>'RUNNING','lane'=>'instrumented'];
    try{$body();}catch(Throwable $primary){
        // A peer may fail before the current child's next barrier (for example a
        // rejected relay capability). Retain its bounded literal error at cleanup too.
        try{M6CSupplementChannel::cleanupAfterFailure($report);}catch(Throwable){$report['supplement_cleanup_failure']=true;}
        throw $primary;
    }
    $report['cases'][$name]=['status'=>'PASS','lane'=>'instrumented','observed'=>true,'assertions'=>$report['assertions']-$before,'seconds'=>(hrtime(true)-$start)/1e9];
    echo 'PASS '.$name." (instrumented supplement)\n";
}

foreach(['revocation','success'] as $winner)m6sCase('acceptance-'.$winner.'-first',function()use($winner,$admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$c]=m6sSeal($f,$winner==='success'?'completion-hold':'build');$tree=m6nTree(m6nReleasePath($f));
    if($winner==='revocation'){
        $r=m6sStart($f,'revoke-hold');m6sRead($r,'LOCKED');$p->send('CONTINUE');
        m6sLockWait($p->diagnosticContext['mysql_connection'],$r->diagnosticContext['mysql_connection']);
        $r->send('CONTINUE');m6nCheck(m6sResultOf($r)['status']==='revoked','revocation_committed');
        m6nCheck(m6sResultOf($p)['status']==='reconciliation_required','revocation_denies_new_success');m6sNoSuccess($f);
    }else{
        $p->send('CONTINUE');m6sRead($p,'LOCKED');$r=m6sStart($f,'revoke-hold');
        m6sLockWait($r->diagnosticContext['mysql_connection'],$p->diagnosticContext['mysql_connection']);
        $p->send('CONTINUE');m6nCheck(m6sResultOf($p)['status']==='succeeded','success_committed_first');m6sRead($r,'LOCKED');$r->send('CONTINUE');m6sResultOf($r);
        $before=m6sState($f);m6nCheck(m6sResultOf(m6sStart($f,'replay',0,$c['attempt_id']))['status']==='succeeded'&&m6sState($f)===$before,'revoked_committed_history_effect_free');m6nVerify($f);
    }
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'acceptance_preserves_sealed_bytes');m6sCounts($f,1,1,0);
});
m6sCase('third-execution-adoption',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$c]=m6sThird($f);$tree=m6nTree(m6nReleasePath($f));m6sCounts($f,3,3,0);m6sKill($p);m6sNoSuccess($f);
    m6nExpire($c['attempt_id']);m6sProbeRecovery($f);[$r,$recovery]=m6sRecoverDone($f);
    m6nCheck($r['status']==='succeeded','third_execution_adopted');m6sCounts($f,4,3,1);
    m6nCheck((int)m6nAttempt($recovery['attempt_id'])['recovery_of_attempt_id']===$c['attempt_id']&&m6nAttempt($c['attempt_id'])['status']==='expired','original_producer_not_revived');
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'third_adoption_exact_bytes');m6nVerify($f);
});
m6sCase('third-revoked-recovery-restart',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$c]=m6sThird($f);$tree=m6nTree(m6nReleasePath($f));m6sKill($p);m6nExpire($c['attempt_id']);
    $admin->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);m6sProbeRecovery($f);
    [$p,$r4]=m6sRecovery($f,'recover-interrupt');m6sCounts($f,4,3,1);$p->send('CONTINUE');m6sRead($p,'VERIFY');m6sKill($p);m6nExpire($r4['attempt_id']);m6sProbeRecovery($f);
    [$r,$r5]=m6sRecoverDone($f);m6sCounts($f,5,3,2);$a=m6nAttempt($r4['attempt_id']);$b=m6nAttempt($r5['attempt_id']);
    m6nCheck($r['status']==='reconciliation_required'&&(int)$a['recovery_of_attempt_id']===$c['attempt_id']&&(int)$b['recovery_of_attempt_id']===$c['attempt_id']
        &&$a['lease_token_hash']!==$b['lease_token_hash']&&$b['deadline_at']>$a['deadline_at'],'fresh_recovery_ownership_without_adoption_authority');
    $before=m6sState($f);m6nCheck(m6sResultOf(m6sStart($f,'stale',0,$r4['attempt_id']))['status']==='stale_rejected'&&m6sState($f)===$before,'old_recovery_renew_and_result_rejected');
    m6sNoSuccess($f);m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'revoked_restart_preserves_output');
});
m6sCase('third-deleted-inspection',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$c]=m6sThird($f);$tree=m6nTree(m6nReleasePath($f));m6sKill($p);m6nExpire($c['attempt_id']);
    $admin->prepare('DELETE FROM users WHERE id=?')->execute([$f['requester']]);m6nCheck(m6nJob($f)['requested_by_user_id']===null,'actual_requester_FK_null');
    m6sProbeRecovery($f);[$r,$rc]=m6sRecoverDone($f);m6sCounts($f,4,3,1);
    m6nCheck($r['status']==='reconciliation_required'&&(int)m6nAttempt($rc['attempt_id'])['recovery_of_attempt_id']===$c['attempt_id'],'deleted_requester_inspected_not_adopted');
    m6sNoSuccess($f);m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'deleted_requester_output_retained');
});
m6sCase('automatic-budget-operator',function()use($admin,$config):void{
    global $current,$report;$f=m6nFixture($admin,$config);[$p,$c]=m6sThird($f);m6sKill($p);m6nExpire($c['attempt_id']);
    $stem=$config['work_root'].'/'.m6nJob($f)['job_key'].'/attempt-'.$c['attempt_id'];file_put_contents($stem.'.seal.json','{}');$tree=m6nTree(m6nReleasePath($f));
    for($i=1;$i<=2;$i++){m6sProbeRecovery($f);[$p,$r]=m6sRecovery($f,'recover-interrupt');m6sCounts($f,3+$i,3,$i);$p->send('CONTINUE');m6sRead($p,'VERIFY');m6sKill($p);m6nExpire($r['attempt_id']);}
    m6sProbeRecovery($f);m6nCheck(m6nJob($f)['recovery_status']==='blocked'&&(int)m6nJob($f)['automatic_recovery_count']===2,'two_automatic_claims_exhausted');
    $key=SiteServiceSupport::uuidV4();$before=m6sState($f);$bad=$f;$bad['operator']=$f['owner'];$p=m6sStart($bad,'operator',0,0,$key);
    $r=M6CSupplementChannel::finish($p,$report,$current,1);$diagnostic=M6CNativeDiagnostics::summary($r);
    m6nCheck(($diagnostic['worker_error']['code']??null)==='unauthorized'&&m6sState($f)===$before,'unauthorized_operator_no_claim');
    [$p,$r]=m6sRecovery($f,'operator',$key);$p->send('CONTINUE');m6nCheck(m6sResultOf($p)['status']==='reconciliation_required','operator_unknown_stays_blocked');m6sCounts($f,6,3,3);
    $before=m6sState($f);m6nCheck(m6sResultOf(m6sStart($f,'operator',0,0,$key))===['status'=>'no_claim','builds'=>0]&&m6sState($f)===$before,'operator_key_idempotent');
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree&&m6nAttempt($c['attempt_id'])['status']==='expired','budget_no_rerender_or_producer_revival');m6sNoSuccess($f);
});
$committedFixture=null;$committedAttempt=null;
foreach(['before-commit'=>'completion-not-committed','drop-ack'=>'completion-ack-dropped'] as $fault=>$name)m6sCase($name,function()use($fault,$admin,$config,&$committedFixture,&$committedAttempt):void{
    global $report,$current;$f=m6nFixture($admin,$config);
    // Consume the first two attempts before starting the one-connection relay; max two children.
    for($i=1;$i<=2;$i++){[$p]=m6sClaim($f,'transient',$i);$p->send('CONTINUE');m6sRead($p,'BOUNDARY');m6nCheck(m6sResultOf($p)['status']==='retry_wait','commit_fixture_transient_attempt');m6sDue($f,'next_attempt_at');}
    [$proxy,$port]=m6sProxy($fault);[$p,$c]=m6sSeal($f,'commit-fault',3,$port);$tree=m6nTree(m6nReleasePath($f));$p->send('CONTINUE');$boundary=m6sRead($p,'COMMIT');
    m6nCheck($boundary['connection']===$p->diagnosticContext['mysql_connection'],'targeted_completion_connection');m6sArm($proxy,$fault);$p->send('CONTINUE');m6sFault($proxy,$fault);
    $report['completion_targets'][]=['case'=>$current,'job_id'=>$f['job_id'],'attempt_id'=>$c['attempt_id'],'connection'=>$boundary['connection']];
    m6nCheck(m6sResultOf($p)===['status'=>'unknown','builds'=>1],'PDO_completion_outcome_not_delivered');
    $fresh=m6sFresh();$observed=m6sState($f,$fresh);$report['authoritative_before_replay'][]=['case'=>$current,'connection'=>(int)$fresh->query('SELECT CONNECTION_ID()')->fetchColumn(),
        'job_id'=>$f['job_id'],'status'=>$observed['job'][0]['status'],'release_count'=>count($observed['releases']),'state_sha256'=>hash('sha256',json_encode($observed,JSON_THROW_ON_ERROR))];
    if($fault==='before-commit'){
        m6sNoSuccess($f);m6nCheck($observed['job'][0]['status']!=='succeeded','unforwarded_COMMIT_not_committed');m6nExpire($c['attempt_id']);m6sProbeRecovery($f);[$r]=m6sRecoverDone($f);m6nCheck($r['status']==='succeeded','known_uncommitted_bytes_adopted');m6sCounts($f,4,3,1);
    }else{
        m6nCheck($observed['job'][0]['status']==='succeeded'&&count($observed['releases'])===1,'fresh_connection_proves_commit_before_replay');
        $admin->prepare('DELETE FROM users WHERE id=?')->execute([$f['requester']]);$before=m6sState($f);
        m6nCheck(m6sResultOf(m6sStart($f,'replay',0,$c['attempt_id']))===['status'=>'succeeded','builds'=>0]&&m6sState($f)===$before,'lost_ack_deleted_requester_immutable_replay');m6sCounts($f,3,3,0);
        $committedFixture=$f;$committedAttempt=$c['attempt_id'];
    }
    m6nCheck(m6nTree(m6nReleasePath($f))===$tree,'commit_fault_preserves_bytes');m6nVerify($f);
});
m6sCase('authoritative-outcome-unavailable',function()use(&$committedFixture,&$committedAttempt):void{
    $f=$committedFixture;if($f===null)throw new RuntimeException('commit_prerequisite');$before=m6sState($f);$tree=m6nTree(m6nReleasePath($f));
    [$proxy,$port]=m6sProxy('drop-query');$p=m6sStart($f,'observe-unavailable',$port,$committedAttempt);m6sRead($p,'OBSERVE');m6sArm($proxy,'drop-query');$p->send('CONTINUE');m6sFault($proxy,'drop-query');
    m6nCheck(m6sResultOf($p)===['status'=>'unknown','builds'=>0]&&m6sState($f)===$before&&m6nTree(m6nReleasePath($f))===$tree,'unavailable_authority_no_claim_compensation_or_guessed_success');
    m6nCheck(m6sResultOf(m6sStart($f,'replay',0,$committedAttempt))===['status'=>'succeeded','builds'=>0]&&m6sState($f)===$before,'restored_authority_recognizes_history');
});
foreach(['candidate','proof'] as $point)m6sCase($point.'-process-kill',function()use($point,$admin,$config):void{
    $f=m6nFixture($admin,$config);[$p,$c]=m6sClaim($f,$point);$p->send('CONTINUE');$b=m6sRead($p,'BOUNDARY');m6nCheck($b['point']===$point&&$b['files']>0,'observed_files_before_SIGKILL');
    m6sKill($p);$stem=$config['work_root'].'/'.m6nJob($f)['job_key'].'/attempt-'.$c['attempt_id'];$tree=m6nTree(dirname($stem));
    m6nCheck(!file_exists(m6nReleasePath($f))&&is_file($stem.'.seal.json')===($point==='proof'),'exact_pre_rename_boundary');m6nExpire($c['attempt_id']);m6sProbeRecovery($f);[$r,$rc]=m6sRecoverDone($f);
    m6nCheck($r['status']===($point==='candidate'?'failed':'reconciliation_required')&&m6nTree(dirname($stem))===$tree,'incomplete_or_ambiguous_output_not_resumed');m6sCounts($f,2,1,1);m6sNoSuccess($f);
    $recovery=m6nAttempt($rc['attempt_id']);m6nCheck($rc['attempt_id']!==$c['attempt_id']&&$recovery['attempt_kind']==='recovery'
        &&(int)$recovery['recovery_of_attempt_id']===$c['attempt_id']&&m6nAttempt($c['attempt_id'])['status']==='expired','crash_producer_and_recovery_ownership_distinct');
});
foreach(['permission'=>'artifact-write-permission','space'=>'artifact-space-exhaustion','rename'=>'artifact-rename-permission','collision'=>'foreign-target-collision'] as $mode=>$name)m6sCase($name,function()use($mode,$admin,$config):void{
    $f=m6nFixture($admin,$config);$beforeFree=disk_free_space($config['work_root']);[$p]=m6sClaim($f,$mode);$p->send('CONTINUE');
    if($mode==='space')m6nCheck(m6sRead($p,'SPACE')===['low'=>true,'unknown'=>true],'actual_low_and_unavailable_free_space_refused');
    $b=m6sRead($p,'BOUNDARY');$r=m6sResultOf($p);
    m6nCheck($b['point']===$mode&&$r['builds']===1&&$r['status']===(in_array($mode,['permission','space'],true)?'retry_wait':'reconciliation_required'),'actual_store_fault_disposition');m6sNoSuccess($f);
    m6nCheck(disk_free_space($config['work_root'])>$beforeFree-1048576,'bounded_pressure_removed_before_next_case');
    if($r['status']==='retry_wait'){
        // Dispose only this scheduled fixture via the real authorization gate so it cannot
        // become due and steal a later case's claim. No counter/status reset or fake attempt.
        $admin->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);m6sDue($f,'next_attempt_at');
        $p=m6sStart($f,'build');m6sRead($p,'READY');$p->send('CONTINUE');m6nCheck(m6sResultOf($p)['status']==='no_claim'&&m6nJob($f)['status']==='cancelled','fixture_queue_settled_by_service');
    }
});
m6sCase('filesystem-admission',function()use($admin,$config):void{
    $f=m6nFixture($admin,$config);m6nCheck(m6sResultOf(m6sStart($f,'admission'))===['status'=>'succeeded','builds'=>0],'real_cross_device_and_FIFO_denied');
    $admin->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$f['requester']]);$p=m6sStart($f);m6sRead($p,'READY');$p->send('CONTINUE');m6nCheck(m6sResultOf($p)['status']==='no_claim','admission_fixture_no_execution');m6sCounts($f,0,0,0);m6sNoSuccess($f);
});
m6sCase('nonmaterial-approved-pdf',function()use($admin,$config):void{
    $pdf=M6CSupplementPdf::bytes();$f=m6nFixture($admin,$config,static function(PDO $db,array $config,array $f)use($pdf):void{
        $q=$db->prepare('SELECT * FROM site_revisions WHERE id=?');$q->execute([$f['revision_id']]);$earlier=$q->fetch();unset($earlier['id'],$earlier['published_site_id']); // Generated column is never an INSERT input.
        $db->prepare("UPDATE site_revisions SET revision_number=2,materiality='non_material',lifecycle_status='draft' WHERE id=?")->execute([$f['revision_id']]);
        $old=m6nInsert($db,'site_revisions',$earlier);$db->prepare("UPDATE site_approvals SET revision_id=? WHERE revision_id=? AND approval_type='customer'")->execute([$old,$f['revision_id']]);
        $q=$db->prepare('SELECT business_id FROM site_assets WHERE id=?');$q->execute([$f['asset_id']]);$business=(int)$q->fetchColumn();$name=bin2hex(random_bytes(8)).'.pdf';
        SiteArtifactFiles::writeNew($config['legacy_upload_root'].'/'.$name,$pdf,4096);
        if(!chmod($config['legacy_upload_root'].'/'.$name,0444))throw new RuntimeException('pdf_fixture_mode');
        $asset=m6nInsert($db,'site_assets',['site_id'=>$f['site_id'],'business_id'=>$business,'asset_key'=>SiteServiceSupport::uuidV4(),'asset_type'=>'document',
            'storage_key'=>'/uploads/'.$name,'checksum_sha256'=>hash('sha256',$pdf),'mime_type'=>'application/pdf','byte_size'=>strlen($pdf),'source'=>'legacy_247sp',
            'rights_classification'=>'customer_owned','lifecycle_status'=>'ready']);
        $c=m6nComposition($earlier['snapshot_hash'],$f['asset_id']);$c['pages'][0]['sections'][]=M6CSupplementPdf::section($asset);
        SiteCompositionManager::replaceDraftComposition($f['requester'],$f['revision_id'],$c);
        $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved',review_ready_at=UTC_TIMESTAMP() WHERE id=?")->execute([$f['revision_id']]);
    });
    [$p]=m6sSeal($f);$p->send('CONTINUE');m6nCheck(m6sResultOf($p)['status']==='succeeded','approved_nonmaterial_pdf_build_success');
    [$e,$j]=m6nOutput($f);m6nCheck($e['disposition']==='sealed','pdf_package_verified');M6CSupplementPdf::verify(m6nReleasePath($f),$e['manifest'],$pdf,'m6nCheck');
    m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_releases WHERE build_job_id=?',[$f['job_id']])===1,'pdf_one_release');
    m6nCheck((int)m6nScalar("SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type='site_build_succeeded'",[$f['site_id']])===1,'pdf_one_success');
});
// No publication and no deployment implementation is part of this supplement.
m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM sites WHERE current_published_revision_id IS NOT NULL')===0,'supplement_no_publication');
