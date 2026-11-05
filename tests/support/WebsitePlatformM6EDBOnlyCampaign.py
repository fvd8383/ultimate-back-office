"""Real component orchestration; SQL remains in the admitted PDO child. No engine access here."""
import copy
import os
from pathlib import Path
import secrets
import shutil
import threading
import time

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, safe_error
from WebsitePlatformM6EProcess import Owner
import WebsitePlatformM6EDBOnlyContract as c
from WebsitePlatformM6EDBOnlyRuntime import Runtime, file_identity, output_measurements
import WebsitePlatformM6ECheckpoint as checkpoint
import WebsitePlatformM6ESnapshot as snapshot
import WebsitePlatformM6ERestoration as restoration
import WebsitePlatformM6EEligibility as eligibility


class ExpectedFault(Exception):
    """Reached a named, case-scoped injection and independently observed its required rejection."""


def reject(action, code):
    try: action()
    except (ValueError, RuntimeError): return
    raise ValueError(code)


def bounded_phase(rt, seconds, action):
    old=rt.owner.deadline;rt.owner.deadline=min(old,time.monotonic()+seconds)
    try:
        value=action();need(time.monotonic()<rt.owner.deadline,'db_only_phase_deadline');return value
    finally:rt.owner.deadline=old


def wait_file(rt, name, uid, seconds=10):
    end=min(rt.owner.deadline,time.monotonic()+seconds);p=rt.root/'outputs'/str(uid)/(name+'.json')
    while not p.exists():
        need(time.monotonic()<end,'db_only_barrier_timeout');time.sleep(.02)
    # Only after the producer has closed the file, indicated by a complete bounded JSON value.
    while True:
        try:return rt.read_output(name,uid,promote=True)
        except (ValueError,OSError):
            need(time.monotonic()<end,'db_only_barrier_incomplete');time.sleep(.02)


class Background:
    def __init__(self,rt,mode,uid,seconds):
        self.owner=Owner(min(rt.owner.deadline,time.monotonic()+seconds));self.result=None;self.error=None;self.rt=rt;self.mode=mode
        if mode=='snapshot':rt.start_snapshot()
        def run():
            try:self.result=self.owner.capture(['/usr/bin/php','-d','memory_limit=256M','-d','display_errors=0','-d','log_errors=0',
                c.SOURCE+'/tests/support/WebsitePlatformM6EDBOnlyChild.php',mode],seconds,limit=65536,identity=(uid,uid,[]))
            except BaseException as e:self.error=e
            finally:
                if mode=='snapshot':
                    try:
                        failed=rt.snapshot_diagnostic(self.owner,0,self.result,self.error)
                        if failed and self.error is None:self.error=ValueError('db_only_snapshot_failed')
                    except BaseException as e:
                        if self.error is None:self.error=e
                        else:rt.failure(e,'snapshot-diagnostic')
        rt.owner.track('child-'+mode,'process',canonical(dict(mode=mode)),self.cleanup)
        self.thread=threading.Thread(target=run,daemon=True);self.thread.start()

    def join(self,allowed=(0,)):
        self.thread.join(max(.001,self.owner.deadline-time.monotonic()+5));need(not self.thread.is_alive(),'db_only_background_timeout')
        if self.error:raise self.error
        r=self.result;need(r is not None and not r['stderr'] and r['exit'] in allowed,'db_only_background_result')
        v=decode(r['stdout']);self.rt.commands.append(dict(mode=self.mode,pid=r['pid'],exit=r['exit'],
            **({'diagnostic':v['diagnostic']} if r['exit'] else {})))
        need(v['result']==('COMPLETED' if r['exit']==0 else 'INCOMPLETE'),'db_only_background_protocol')
        return v,r['pid'],r['exit']

    def cleanup(self):
        self.owner.cleanup();self.thread.join(5);need(not self.thread.is_alive(),'db_only_background_unreaped')


def capture_snapshot(rt):
    if rt.req['case']!='snapshot-concurrency':return rt.value('snapshot',45)
    writer=Background(rt,'competing-writer',2000,20);wait_file(rt,'writer-session',2000)
    # Observe a real open writer transaction before starting the reader. FTWRL does
    # not promise to wait for every idle InnoDB transaction; do not require that fiction.
    end=min(rt.owner.deadline,time.monotonic()+8);observed=None
    while time.monotonic()<end:
        v,_,exit_=rt.command('waiter',5,allowed=(0,1))
        if exit_==0:observed=v['value'];break
        time.sleep(.05)
    need(observed is not None,'db_only_writer_transaction_unobserved');rt.publish('snapshot-competing-writer',observed)
    reader=Background(rt,'snapshot',2001,45);primary=None
    try:
        wait_file(rt,'snapshot-session',2001)
        (rt.root/'barriers/writer-release').touch(exist_ok=False)
        written,_,_=writer.join();rt.publish('snapshot-writer-commit',written['value'])
        v,_,_=reader.join();return v['value']
    except BaseException as e:primary=e;raise
    finally:
        # A failed barrier/writer must still reap the owned reader and collect its available outcome.
        if reader.thread.is_alive():
            try:reader.cleanup()
            except BaseException as e:
                rt.failure(e,'snapshot-owned-cleanup')
                if primary is None:raise


def competing_rows(notes):
    col=notes['columns'].index('note_body');values=[bytes.fromhex(r[col]).decode() for r in notes['rows']]
    need(notes['row_count']==len(values) and values in (['M6E DB-only baseline'],
         ['M6E bounded competing writer','M6E same transaction']),'db_only_competing_atomic_values')
    return 'BEFORE_WRITER_COMMIT' if len(values)==1 else 'AFTER_WRITER_COMMIT'


def eligibility_request(rt,cp_raw,snapshot_raw,schema_raw,proof):
    """Honest DB-only owner; NEVER call the full-host request constructor with invented cgroup facts."""
    req=restoration.request(rt.a,cp_raw,snapshot_raw,schema_raw)
    need(proof['result']=='RESTORED AND INDEPENDENTLY VERIFIED' and proof['binding']==req['binding'],'db_only_restoration_proof')
    return dict(profile=c.PROFILE+'-eligibility',binding=req['binding'],schema=req['destination']['schema'],server_uuid=req['server_uuid'],
        **{k:req[k] for k in ('checkpoint_sha256','snapshot_sha256','schema_sha256')},
        restoration_sha256=digest(canonical(proof)),restoration_observation_sha256=proof['observation_sha256'],
        source_files={n:rt.source[n]['sha256'] for n in eligibility.SOURCE_FILES},invocation_id=secrets.token_hex(16),
        owner=dict(pid=os.getpid(),profile=c.PROFILE,namespaces=rt.a['namespaces'],cgroup=Path('/proc/self/cgroup').read_text().splitlines()),
        started_at=int(time.time()),expires_at=rt.a['expires_at'],**c.AUTHORITY)


def observe(rt,phase,req,cp_raw,snapshot_raw,schema_raw,accounts):
    """Same producer, independent diagnostic recomputation and immutable publication before failure escapes."""
    primary=None;capture=None;diagnostic=None;raw=None;verified=None;pid=None
    try:
        v,pid,exit_=rt.command(phase,60,allowed=(0,1))
        if exit_:primary=ValueError('db_only_observer_child_failed')
    except BaseException as e:primary=e
    try:
        raw=rt.read_output(phase,0);v=decode(raw)
        need(v['process']['pid']==pid,'db_only_observer_pid')
        diagnostic=eligibility.observer_diagnostic(v,phase,req,cp_raw,snapshot_raw,schema_raw)
        try:verified=eligibility.verify_observer(raw,phase,req,cp_raw,snapshot_raw,schema_raw,rt.a,rt.source,'native-pre-b',accounts,int(time.time()))
        except BaseException as e:primary=primary or e
        need(v['diagnostic']==diagnostic,'db_only_observer_diagnostic')
        rt.verify_outputs()
    except BaseException as e:capture=e;primary=primary or e
    if primary:rt.failure(primary,'observer')
    if capture:rt.secondary.append(dict(kind='observer-capture',**safe_error(capture)))
    value=dict(profile='m6e-eligibility-observer-evidence-v1',binding=req['binding'],request_sha256=digest(canonical(req)),
        invocation_id=req['invocation_id'],phase=phase,diagnostic=diagnostic,capture='INCOMPLETE' if capture else 'COMPLETE',
        observation_sha256=None if raw is None else digest(raw),result='DIAGNOSTIC_ONLY',**c.AUTHORITY)
    try:
        if rt.req['case']=='publication-failure' and phase=='after':
            # A fixed case-scoped API failure, not a claimed filesystem/kernel failure.
            raise OSError('db_only_injected_publication_failure')
        rt.publish('observer-'+phase,value)
    except BaseException as e:
        primary=primary or e;rt.failure(e,'observer-publication')
        # Separate immutable diagnostic remains available; never invent the failed publication receipt.
        rt.publish('observer-'+phase+'-publication-failure',dict(failed_publication='observer-'+phase,evidence=value,**c.AUTHORITY))
    if primary:raise primary
    return verified


def artifact_fault(rt,cp):
    target=Path('/work/fault-sealed');need(not target.exists(),'db_only_fault_copy_exists')
    shutil.copytree('/work/sealed',target,symlinks=False)
    # Preserve genuine store modes/ownership for the verifier, including its original UID contract.
    for p in [target,*target.rglob('*')]:
        need(not p.is_symlink(),'db_only_fault_copy_link');os.chown(p,2000,2000)
    from WebsitePlatformM6EAcceptance import decode
    candidates=list(target.rglob('manifest.json'));need(len(candidates)==2,'db_only_fault_copy_inventory')
    wanted=[]
    for p in candidates:
        if digest(p.read_bytes())==cp['summary']['manifest_sha256']:wanted.append(p)
    need(len(wanted)==1,'db_only_fault_copy_A');p=wanted[0]
    with p.open('ab') as f:f.write(b' ');f.flush();os.fsync(f.fileno())
    (rt.root/'barriers/artifact-fault').touch(exist_ok=False)
    rt.publish('artifact-injection',dict(injection='case-owned COPY of original A sealed store',file_sha256=digest(p.read_bytes()),**c.AUTHORITY))


def run_chain(rt):
    case=rt.req['case'];rt.value('schema',180)
    secrets_=decode(rt.read_output('credentials',0))
    rt.credential('builder',secrets_['builder'],[2000,2001]);rt.credential('snapshot',secrets_['snapshot'],[2001,0])
    rt.credential('source',secrets_['source'],[0]);rt.credential('writer',secrets_['writer'],[0])
    schema_raw=rt.read_output('schema',0,promote=True)
    rt.value('build-a',180);rt.read_output('built',2000,promote=True)
    rt.value('checkpoint',60);cp_raw=rt.read_output('checkpoint',2000,promote=True);cp=decode(cp_raw)
    def snapshot_stage():
        outcome=capture_snapshot(rt);need(outcome['capture']=='CAPTURED' and outcome['transaction_cleanup']=='RELEASED','db_only_snapshot_outcome')
        raw=rt.read_output('snapshot',2001,promote=True)
        proof=snapshot.validate(raw,schema_raw,cp_raw,rt.a,rt.source,'native-pre-b',int(time.time()))
        rt.publish('snapshot-verification',proof);return raw
    snapshot_raw=bounded_phase(rt,45,snapshot_stage)
    if case=='snapshot-concurrency':
        rt.publish('snapshot-concurrency',dict(observed=competing_rows(decode(snapshot_raw)['tables']['notes']),
            consistency='ONE_COMPLETE_TRANSACTION_STATE',**c.AUTHORITY))
    if case in ('snapshot-corrupt','snapshot-incomplete'):
        bad=decode(snapshot_raw)
        if case=='snapshot-corrupt':bad['tables']['notes']['rows'][0][0]='FF'
        else:del bad['tables']['notes']
        reject(lambda:snapshot.validate(canonical(bad),schema_raw,cp_raw,rt.a,rt.source,'native-pre-b',int(time.time())),'db_only_corrupt_snapshot_accepted')
        rt.publish('snapshot-injection',dict(case=case,injection='case-owned serialized COPY',rejected=True,
            original=digest(snapshot_raw),mutated=digest(canonical(bad)),**c.AUTHORITY))
    rt.value('build-b',180);rt.read_output('completed',2000,promote=True)
    original=rt.value('source',60);rt.publish('completed-original',original)
    req=restoration.request(rt.a,cp_raw,snapshot_raw,schema_raw);rt.put('request',req)
    def restore():
        rt.value('restore-create',20)
        for role,uid in [('restorer',2107),('observer',0)]:
            raw=rt.read_output('credential-'+role,0);rt.credential(role,decode(raw),[uid])
        rt.value('restore',230);out=decode(rt.read_output('restoration-outcome',2107))
        need(out['result']=='AWAITING_INDEPENDENT_VERIFICATION' and out['transaction']=='COMMITTED' and not out['secondary'],'db_only_restore_outcome')
        accounts=rt.value('restore-seal',20);rt.publish('restoration-seal',accounts)
        if case=='restoration-corrupt':rt.value('mutate')
        rt.value('restore-observe',60);raw=rt.read_output('restoration-observation',0)
        if case=='restoration-corrupt':
            reject(lambda:restoration.verify_observation(raw,req,snapshot_raw,schema_raw,cp_raw,rt.a,rt.source,'native-pre-b',int(time.time()),accounts),'db_only_restore_corruption_accepted')
            actual=decode(raw)['read']['tables']['notes'];expected=decode(snapshot_raw)['tables']['notes']
            rt.publish('restoration-rejection',dict(table='notes',expected_sha256=digest(canonical(expected)),observed_sha256=digest(canonical(actual)),
                expected_rows=expected['row_count'],observed_rows=actual['row_count'],injection='case-scoped destination mutation',**c.AUTHORITY))
            need(rt.value('source')==original,'db_only_original_modified');raise ExpectedFault('restoration-corrupt')
        proof=restoration.verify_observation(raw,req,snapshot_raw,schema_raw,cp_raw,rt.a,rt.source,'native-pre-b',int(time.time()),accounts)
        rt.publish('restoration-verification',proof);return accounts,proof
    accounts,restored=bounded_phase(rt,240,restore)
    ereq=eligibility_request(rt,cp_raw,snapshot_raw,schema_raw,restored);rt.put('eligibility',ereq)
    account_state=dict(prepared=False,sealed=False)
    def preflight():
        account_state['prepared']=True
        rt.value('eligibility-create',20);rt.credential('eligibility',decode(rt.read_output('credential-eligibility',0)),[2107,0])
        if case in ('identity-grants','denied-dml'):rt.publish(case,rt.value(case))
        if case.startswith('before-'):
            if case.endswith('artifact'):artifact_fault(rt,cp)
            else:rt.value('mutate')
        before=None
        try:before=observe(rt,'before',ereq,cp_raw,snapshot_raw,schema_raw,accounts)
        except BaseException:
            if case.startswith('before-'):
                record=decode((Path('/evidence')/'observer-before.json').read_bytes());d=record['diagnostic']
                need(record['capture']=='COMPLETE' and (d['classification']=='artifact_failed' if case.endswith('artifact') else
                     'notes' in d['affected_tables'] and case.split('-')[1] in ('schema','notes')),'db_only_before_fault_observation')
                raise ExpectedFault(case)
            raise
        if case in ('source-hash','approval'):rt.value('mutate')
        if case=='transaction-interrupt':
            worker=Background(rt,'eligibility',2107,65);wait_file(rt,'gate-session',2107)
            rt.publish('transaction-interruption',rt.value('interrupt'))
            (rt.root/'barriers/gate-release').touch(exist_ok=False);v,pid,exit_=worker.join((1,))
        else:v,pid,exit_=rt.command('eligibility',65,allowed=(0,1))
        seal=rt.value('eligibility-seal',20);account_state['sealed']=True;rt.publish('eligibility-seal',seal)
        raw=rt.read_output('gate-outcome',2107);out=decode(raw)
        if case in ('source-hash','approval','transaction-interrupt'):
            need(exit_==1 and out['result']=='INCOMPLETE' and out['first_failure'] is not None,'db_only_gate_negative')
            if case=='transaction-interrupt':need(out['transaction']!='ROLLED_BACK' or out['closed'] is False,'db_only_interruption_unknown')
            else:need(out['transaction']=='ROLLED_BACK' and out['closed'] is True,'db_only_negative_rollback')
            rt.publish('gate-rejection',{k:out[k] for k in ('result','phase','first_failure','secondary','transaction','closed','gate_returned')})
            if rt.first is None:rt.first=dict(phase='eligibility',kind='injected-gate',code=out['first_failure'])
            if case=='transaction-interrupt':
                after=observe(rt,'after',ereq,cp_raw,snapshot_raw,schema_raw,accounts)
                need(before['tables']==after['tables'] and before['artifact']==after['artifact'],'db_only_interruption_observation')
                rt.publish('interruption-state',dict(sql_session='GONE',rollback_acknowledgement='UNCONFIRMED',independent_state='UNCHANGED',**c.AUTHORITY))
            else:
                try:observe(rt,'after',ereq,cp_raw,snapshot_raw,schema_raw,accounts)
                except BaseException:
                    record=decode((Path('/evidence')/'observer-after.json').read_bytes())
                    table='site_build_jobs' if case=='source-hash' else 'site_approvals'
                    need(record['capture']=='COMPLETE' and table in record['diagnostic']['affected_tables'],'db_only_gate_mutation_observation')
                else:raise ValueError('db_only_gate_mutation_not_observed')
            raise ExpectedFault(case)
        need(exit_==0,'db_only_gate_exit');gate=eligibility.verify_gate(raw,ereq,cp_raw,pid,seal,int(time.time()));rt.publish('gate',gate)
        if case.startswith('after-') or case=='capture-cleanup-failure':
            if case.endswith('artifact'):artifact_fault(rt,cp)
            else:rt.value('mutate')
        try:after=observe(rt,'after',ereq,cp_raw,snapshot_raw,schema_raw,accounts)
        except BaseException:
            if case.startswith('after-'):
                record=decode((Path('/evidence')/'observer-after.json').read_bytes());d=record['diagnostic']
                need(record['capture']=='COMPLETE' and (d['classification']=='artifact_failed' if case.endswith('artifact') else 'notes' in d['affected_tables']),'db_only_after_fault_observation')
            elif case=='publication-failure':need('observer-after-publication-failure' in rt.receipts and 'observer-after' not in rt.receipts,'db_only_publication_injection')
            elif case=='capture-cleanup-failure':
                record=decode((Path('/evidence')/'observer-after.json').read_bytes());need(record['capture']=='INCOMPLETE' and record['diagnostic'] is None,'db_only_capture_injection')
            else:raise
            raise ExpectedFault(case)
        need(len({before['destination']['connection_id'],gate['identity']['connection_id'],after['destination']['connection_id']})==3,'db_only_independent_connections')
        need(before['tables']==after['tables'] and before['artifact']==after['artifact'],'db_only_before_after')
    def complete_preflight():
        primary=None
        try:preflight()
        except BaseException as e:
            primary=e
            if rt.first is None:rt.failure(e,'preflight')
        finally:
            if account_state['prepared'] and not account_state['sealed']:
                try:
                    rt.publish('eligibility-failure-seal',rt.value('eligibility-seal',20));account_state['sealed']=True
                except BaseException as e:
                    rt.failure(e,'account-seal');rt.unresolved=True
                    if primary is None:primary=e
            try:
                need(rt.value('source')==original,'db_only_original_fixture_preserved')
                rt.verify_outputs();need(source_inventory_equal(rt),'db_only_source_final')
            except BaseException as e:
                rt.failure(e,'final-integrity');rt.unresolved=True
                if primary is None:primary=e
        if primary is not None:raise primary
    bounded_phase(rt,120,complete_preflight)
    return dict(original_preserved=True,newer_material_B_rejected=True,gate='UNCHANGED FALSE / ROLLED BACK',deployment_operations=0)


def source_inventory_equal(rt):
    from WebsitePlatformM6EDBOnlyRuntime import source_inventory
    return source_inventory(c.SOURCE,rt.req['source_sha'],rt.owner)==rt.source


def campaign(config):
    rt=None;result='INCOMPLETE';summary={};injected=None
    try:
        rt=Runtime(config)
        if rt.req['case']=='capture-cleanup-failure':
            p=rt.root/'case-cleanup-probe';p.touch(exist_ok=False);identity=file_identity(p)
            def cleanup_fault():
                need(file_identity(p)==identity,'db_only_cleanup_probe_replaced')
                raise RuntimeError('db_only_injected_owned_cleanup_failure')
            rt.owner.track('case-cleanup-probe','file',canonical(dict(dev=identity[0],ino=identity[1])),cleanup_fault)
        summary=run_chain(rt);result='DB-ONLY COMPONENT VALIDATION PASS'
    except ExpectedFault as e:
        injected=str(e);result='INCOMPLETE' if getattr(rt,'unresolved',False) else 'EXPECTED INJECTED FAILURE OBSERVED'
    except BaseException as e:
        if rt:rt.failure(e,'campaign')
    finally:
        if rt:
            # Capture the first failure BEFORE any child/resource cleanup, including injection failures.
            try:rt.publish('before-cleanup',dict(profile=c.PROFILE,result=result,injected=injected,first_failure=rt.first,
                secondary=rt.secondary,trace=rt.trace,commands=rt.commands,receipts=rt.receipts.copy(),
                files=output_measurements(getattr(rt,'outputs',{})),snapshot_diagnostic=getattr(rt,'snapshot_evidence',None),**c.AUTHORITY))
            except BaseException as e:rt.failure(e,'before-cleanup-capture');result='INCOMPLETE'
            rt.owner.deadline=min(rt.overall,time.monotonic()+c.LIMITS['cleanup_seconds'])
            try:rt.owner.cleanup()
            except BaseException as e:
                rt.secondary.append(dict(kind='owned-cleanup',**safe_error(e)))
                if injected!='capture-cleanup-failure':result='INCOMPLETE'
            try:
                if injected=='capture-cleanup-failure':need(any(x['kind']=='owned-cleanup' for x in rt.secondary),'db_only_cleanup_fault_unreached')
                report=dict(profile=c.PROFILE,purpose='DB-ONLY COMPONENT VALIDATION',request=rt.req,result=result,injected=injected,
                    first_failure=rt.first,secondary=rt.secondary,summary=summary,context=rt.owner.snapshot(),receipts=rt.receipts.copy(),
                    snapshot_diagnostic=getattr(rt,'snapshot_evidence',None),
                    component_seconds=time.monotonic()-rt.started,host_cleanup='PENDING_EXACT_CONTAINER_REMOVAL',**c.AUTHORITY)
                rt.publish('report',report)
                print(canonical(dict(profile=c.PROFILE,result=result,receipts=rt.receipts,**c.AUTHORITY)).decode())
            except BaseException:
                print(canonical(dict(profile=c.PROFILE,result='INCOMPLETE',failure='db_only_final_capture',**c.AUTHORITY)).decode());result='INCOMPLETE'
    return 0 if result!='INCOMPLETE' else 1
