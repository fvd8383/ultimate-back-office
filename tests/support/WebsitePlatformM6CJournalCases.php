<?php
declare(strict_types=1);
if(!isset($admin,$config,$identity,$profile)||$profile!==M6CJournalContract::VERSION
    ||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo')throw new RuntimeException('journal_internal_only');
require_once __DIR__.'/WebsitePlatformM6CJournalChannel.php';
require_once __DIR__.'/WebsitePlatformM6CJournalAdmission.php';
M6CJournalAdmission::verify($identity['sha']);
m6nCheck(!extension_loaded('m6c_journal'),'journal_coordinator_unintercepted');

function m6jRun(array $f,string $mode,int $producer=0):array
{
    global $report,$current,$identity;
    $load=$mode==='recover'?[]:['-d','extension='.M6CJournalAdmission::LIBRARY];
    $p=new M6CNativeProcess(['/usr/bin/setpriv','--reuid=2000','--regid=2000','--clear-groups','--no-new-privs',
        '--bounding-set=-all','--inh-caps=-all','--ambient-caps=-all',PHP_BINARY,'-d','memory_limit=128M',
        '-d','display_errors=stderr','-d','zend.exception_ignore_args=1','-d','opcache.enable_cli=0','-d','opcache.jit=0',...$load,
        __DIR__.'/WebsitePlatformM6CJournalWorker.php',$mode,(string)$f['job_id'],(string)$f['operator'],(string)$producer],90);
    $p->closeInput();return M6CJournalChannel::result($p,$report,$current,$mode,$identity['token']);
}
foreach(M6CJournalContract::CASES as $i=>$name){
    $operation=['flush','fsync','publication'][$i];$saved=null;
    m6nCase($name,'instrumented',function()use($operation,&$saved,$config,$admin,$identity):void{
        // Same extension/load path, distinct service-created job/producer; actual success required.
        $controlFixture=m6nFixture($admin,$config);$control=m6jRun($controlFixture,'control');
        m6nCheck($control['job']===$controlFixture['job_id']&&$control['status']==='succeeded','journal_positive_control');
        m6nCheck((int)m6nScalar('SELECT COUNT(*) FROM site_releases WHERE build_job_id=?',[$controlFixture['job_id']])===1,'journal_control_release');
        m6nCheck($control['observation']['injected']===0&&$control['observation']['off_target']>=7,'journal_control_passthrough');
        $f=m6nFixture($admin,$config);$fault=m6jRun($f,$operation);$j=m6nJob($f);$producer=m6nAttempt($fault['producer']);
        m6nCheck($fault['job']===$f['job_id']&&(int)$producer['build_job_id']===$f['job_id']&&$producer['attempt_kind']==='execution','journal_producer_identity');
        m6nCheck($fault['observation']['target_digest']===hash('sha256',$identity['token'].'|'.$j['job_key'].'|'.$fault['producer']),'journal_target_binding');
        m6nCheck($fault['observation']['injected']===1,'journal_exactly_once');
        m6nCheck($fault['caller_error']===($operation==='publication'?'outcome_unknown':'storage_unavailable'),'journal_immediate_error');
        $stem=$config['work_root'].'/'.$j['job_key'].'/attempt-'.$fault['producer'];
        m6nCheck(is_file($stem.'.json.pending')&&!is_link($stem.'.json.pending'),'journal_pending_retained');
        m6nCheck(!file_exists($stem.'.json')&&!is_link($stem.'.json'),'journal_not_published');
        m6nCheck(!file_exists($stem)&&!is_link($stem)&&!file_exists($stem.'.seal.json'),'journal_no_candidate_or_seal');
        $digest=hash_file('sha256',$stem.'.json.pending');m6nCheck($digest===$fault['pending_digest'],'journal_pending_digest');
        m6nCheck($j['status']==='reconciliation_required'&&$j['next_recovery_at']!==null,'journal_service_requires_recovery');
        // Controlled due timestamp only, preserving every other field/counter and no elapsed-time claim.
        $admin->prepare('UPDATE site_build_jobs SET next_recovery_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?')->execute([$f['job_id']]);
        $after=m6nJob($f);unset($j['next_recovery_at'],$after['next_recovery_at']);m6nCheck($j===$after,'journal_only_backoff_controlled');
        $recovery=m6jRun($f,'recover',$fault['producer']);$j=m6nJob($f);$attempt=m6nAttempt($recovery['recovery_attempt']);
        m6nCheck($recovery['job']===$f['job_id']&&$recovery['producer']===$fault['producer']
            &&(int)$attempt['recovery_of_attempt_id']===$fault['producer']&&$attempt['attempt_kind']==='recovery','journal_distinct_recovery_original_producer');
        m6nCheck($recovery['disposition']==='blocked'&&$recovery['builds']===0,'journal_unknown_stays_blocked_without_render');
        m6nCheck([(int)$j['attempt_count'],(int)$j['execution_count'],(int)$j['recovery_count']] === [2,1,1],'journal_service_accounting');
        $releases=(int)m6nScalar('SELECT COUNT(*) FROM site_releases WHERE build_job_id=?',[$f['job_id']]);
        $validations=(int)m6nScalar('SELECT COUNT(*) FROM site_release_validations WHERE build_attempt_id IN (SELECT id FROM site_build_attempts WHERE build_job_id=?)',[$f['job_id']]);
        $events=(int)m6nScalar("SELECT COUNT(*) FROM site_events WHERE site_id=? AND event_type='site_build_succeeded'",[$f['site_id']]);
        m6nCheck($releases===0&&$validations===0&&$events===0,'journal_no_false_success');
        $unchanged=hash_file('sha256',$stem.'.json.pending')===$digest;m6nCheck($unchanged,'journal_recovery_retains_pending');
        $saved=['evidence_level'=>'injected_php_api_return','fault'=>$fault['observation'],'control'=>$control['observation'],
            'caller_error'=>$fault['caller_error'],'recovery_attempt'=>$recovery['recovery_attempt'],'control_success'=>$control['status']==='succeeded',
            'state'=>['pending'=>is_file($stem.'.json.pending'),'journal'=>file_exists($stem.'.json'),'candidate'=>file_exists($stem),
                'release'=>$releases>0,'inspection'=>$fault['inspection'],'job'=>$j['status'],'recovery'=>$j['recovery_status'],
                'attempts'=>(int)$j['attempt_count'],'executions'=>(int)$j['execution_count'],'recoveries'=>(int)$j['recovery_count'],
                'builds'=>$fault['builds'],'validations'=>$validations,'success_events'=>$events,'pending_unchanged'=>$unchanged,'cleanup'=>$fault['cleanup']]];
    });
    $report['cases'][$name]+=$saved;
}
