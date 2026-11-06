<?php
declare(strict_types=1);
/** Fixed, case-scoped native controls. Every connection is the admitted disposable server. */
function m6eDbFault(string $mode,array $a,array $inputs,callable $pulse):array {
    $cp=$inputs['checkpoint'];$destination='ubo_m6e_lifecycle_'.$a['run_id'];
    if($mode==='denied-dml'){
        $result=[];
        foreach(['snapshot','source','observer','eligibility'] as $role){
            $db=m6eDbConnect($a,$role);$result[$role]=m6eDbDeniedDml($db);$db=null;
        }
        return ['actual_denials'=>$result,'mysql_error'=>1142];
    }
    if($mode==='identity-grants'){
        $snapshot=m6eDbConnect($a,'snapshot');$identity=['schema'=>'ubo_m6e_'.$a['run_id'],'server_uuid'=>$a['server_uuid'],'account'=>'m6e_snapshot@127.0.0.1'];
        $baseline=m6eSnapshotAdmit($snapshot,$identity,0);$checks=[];
        foreach(['schema'=>'ubo_m6e_'.$a['run_id'].'_foreign','server_uuid'=>'00000000-0000-0000-0000-000000000000','account'=>'m6e_worker@127.0.0.1'] as $key=>$bad){
            $wrong=$identity;$wrong[$key]=$bad;m6eDbRejected(static fn()=>m6eSnapshotAdmit($snapshot,$wrong,0));$checks[]=$key;
        }
        $admin=m6eDbConnect($a,'admin');$account="'m6e_snapshot'@'127.0.0.1'";
        $admin->exec('GRANT PROCESS ON *.* TO '.$account);
        try{m6eDbRejected(static fn()=>m6eSnapshotAdmit($snapshot,$identity,0));$checks[]='snapshot-excess-grants';}
        finally{$admin->exec('REVOKE PROCESS ON *.* FROM '.$account);}
        m6eSnapshotAdmit($snapshot,$identity,0);
        $gate=m6eDbConnect($a,'eligibility',false);$expected=['schema'=>$destination,'server_uuid'=>$a['server_uuid'],'account'=>M6E_ELIGIBILITY_USER.'@127.0.0.1'];
        m6eEligibilityAdmit($gate,$expected);$account="'".M6E_ELIGIBILITY_USER."'@'127.0.0.1'";
        $admin->exec('GRANT UPDATE ON `'.str_replace('_','\\_',$destination).'`.* TO '.$account);
        try{m6eDbRejected(static fn()=>m6eEligibilityAdmit($gate,$expected));$checks[]='eligibility-excess-grants';}
        finally{$admin->exec('REVOKE UPDATE ON `'.str_replace('_','\\_',$destination).'`.* FROM '.$account);}
        m6eEligibilityAdmit($gate,$expected);
        return ['rejected'=>$checks,'baseline'=>$baseline,'injection'=>'case-scoped SQL grant changes'];
    }
    if($mode==='mutate'){
        $case=$a['case'];m6eNeed(in_array($case,['source-hash','approval','restoration-corrupt','before-schema','after-schema','before-notes','after-notes','capture-cleanup-failure'],true),'db_only_fixed_mutation');
        $db=m6eDbConnect($a,'admin');$db->exec('USE '.m6eSnapshotName($destination));
        if($case==='source-hash')$db->prepare('UPDATE site_build_jobs SET builder_code_sha=? WHERE id=?')->execute([str_repeat('f',40),$cp['summary']['build_job_id']]);
        elseif($case==='approval')$db->prepare("UPDATE site_approvals SET state='revoked',revoked_at=UTC_TIMESTAMP() WHERE id=?")->execute([$cp['observed']['source']['approvals']['internal_approval_id']]);
        elseif(str_ends_with($case,'schema'))$db->exec('ALTER TABLE notes ADD COLUMN m6e_fault INT NULL');
        else $db->exec('DELETE FROM notes');
        return ['injection'=>'case-scoped destination SQL mutation','case'=>$case,'schema'=>$destination];
    }
    if($mode==='competing-writer'){
        m6eNeed($a['case']==='snapshot-concurrency','db_only_writer_case');$db=m6eDbConnect($a,'builder',false);$db->beginTransaction();
        try{
            $db->exec("UPDATE notes SET note_body='M6E bounded competing writer' WHERE id=1");
            $db->exec("INSERT INTO notes(business_id,note_body) SELECT business_id,'M6E same transaction' FROM notes WHERE id=1");
            m6eDbWrite('writer-session',['connection_id'=>(int)m6eSnapshotScalar($db,'SELECT CONNECTION_ID()')]);
            while(!is_file(M6E_DB_ROOT.'/barriers/writer-release')){$pulse();usleep(20000);}
            m6eNeed($db->commit(),'db_only_competing_commit');
            m6eNeed($db->query('SELECT note_body FROM notes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)===
                ['M6E bounded competing writer','M6E same transaction'],'db_only_competing_committed_rows');
        }finally{if($db->inTransaction())$db->rollBack();}
        return ['transaction'=>'COMMITTED','injection'=>'bounded competing disposable writer'];
    }
    if($mode==='waiter'||$mode==='interrupt'){
        $target=m6eDbRead($mode==='waiter'?'writer-session':'gate-session');$id=$target['connection_id'];
        m6eNeed(is_int($id)&&$id>0,'db_only_connection_id');$admin=m6eDbConnect($a,'admin');
        $user=$mode==='waiter'?'m6e_worker':M6E_ELIGIBILITY_USER;$schema=$mode==='waiter'?'ubo_m6e_'.$a['run_id']:$destination;
        $rows=m6eSnapshotRows($admin,'SELECT ID, USER, HOST, DB, COMMAND, STATE, INFO FROM information_schema.PROCESSLIST WHERE ID=?',[$id]);
        m6eNeed(count($rows)===1&&$rows[0]['USER']===$user&&$rows[0]['DB']===$schema&&str_starts_with($rows[0]['HOST'],'127.0.0.1:'),'db_only_owned_sql_session');
        if($mode==='waiter'){
            $transactions=m6eSnapshotRows($admin,'SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?',[$id]);
            m6eNeed($a['case']==='snapshot-concurrency'&&$rows[0]['COMMAND']==='Sleep'
                &&count($transactions)===1&&$transactions[0]['trx_state']==='RUNNING','db_only_actual_writer_transaction');
            return ['connection_id'=>$id,'writer_transaction'=>'OPEN_WITH_UNCOMMITTED_CHANGES',
                'injection'=>'bounded case-owned writer paused before commit'];
        }
        m6eNeed($a['case']==='transaction-interrupt'&&$rows[0]['COMMAND']==='Sleep','db_only_interrupt_boundary');
        $admin->exec('KILL CONNECTION '.$id);
        return ['connection_id'=>$id,'injection'=>'KILL CONNECTION of recorded case-owned eligibility session'];
    }
    throw new RuntimeException('db_only_unknown_mode');
}
