"""Desktop controls only. Does not prepare/run a VM, Apache, MySQL or a supervisor."""
import copy
import ast
import importlib
import json
import os
from pathlib import Path
import sys
import subprocess
import tempfile
import time
import unittest
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
import WebsitePlatformM6EAcceptance as a
from WebsitePlatformM6EProcess import Owner, Failure, publish_bytes
from WebsitePlatformM6EPreparation import admit_facts, render, BINARIES
from WebsitePlatformM6EProvision import Provisioner, COMMANDS
from WebsitePlatformM6EDrivers import Recorder, dispatch, DRIVERS, check
from WebsitePlatformM6EApache import sample
from WebsitePlatformM6ECoordinator import coordinate, require_native_ready, native_readiness

def identity():
    return dict(source_sha='a'*40,run_id='b'*32,boot_id='11111111-1111-4111-8111-111111111111',machine_id='c'*32,
        config_sha256='d'*64,tools={k:dict(sha256='e'*64,version='CONTROLLED LOCAL FIXTURE') for k in a.TOOLS},roles=a.ROLES.copy(),
        endpoints=copy.deepcopy(a.ENDPOINTS),database=dict(schema='ubo_m6e_'+'b'*32,server_uuid='22222222-2222-4222-8222-222222222222',
        writer='m6e_writer',authority='m6e_observer'),namespace='vm:'+'c'*32+':11111111-1111-4111-8111-111111111111')

def measured(i,case,name):
    if case in a.PARENTS[-3:]:return copy.deepcopy(report()['cases'][case]['observations'][name])
    events=[]
    for j,stage in enumerate(('baseline','fault','outcome','independent','cleanup')):
        data=[dict(exit=0,state_sha256=a.digest(b'actual local fixture baseline')),dict(boundary=name,count=1),
              dict(observed=1),dict(observed=1),dict(remaining=0,exit=0)][j]
        events.append(a.event(i,case,name,stage,'worker' if j!=3 else 'coordinator',100 if j!=3 else 101,j+1,data))
    return a.observation(i,case,name,events,[check('outcome','observed',1),check('independent','observed',1)],'controlled-local')

_report=None
def report():
    global _report
    if _report is None:
        with tempfile.TemporaryDirectory(prefix='m6e-report-contract-') as folder:
            result=coordinate(identity(),'controlled-local',ControlledBackend,folder)
            if not result['accepted']:raise AssertionError(result['report']['first_failure'])
            _report=copy.deepcopy(result['report'])
    return copy.deepcopy(_report)

class Store:
    def __init__(self): self.live=b'old';self.pending=None;self.cleanup_fail=False
    def snapshot(self):return a.digest(self.live)
    def stage(self,variant):self.pending=b'new' if variant=='valid' else b'invalid'
    def install(self):self.live=self.pending;self.pending=None
    def cleanup_pending(self):
        if self.cleanup_fail:raise Failure('cleanup_model')
        self.pending=None
    def matches_observed(self,v):return self.live==b'new' and v=='new'
    def pending_snapshot(self):return dict(pending_present=int(self.pending is not None),pending_bytes=len(self.pending or b''),
                                           pending_sha256=None if self.pending is None else a.digest(self.pending))


class ControlledBackend:
    """Deterministic adapters, explicitly not TLS, OS identities or native SQL.

    Calls the actual three driver functions and actual provisioning state machine.
    Counters below describe this adapter only and cannot satisfy native acceptance.
    """
    def __init__(self,owner=None,fail_at=None,cleanup_error=False):
        self.calls=[];self.owned=[];self.secondary_errors=[];self.fail_at=fail_at;self.cleanup_error=cleanup_error
        if owner:owner.track('adapter','controlled-model',b'controlled-local',self.cleanup_all)
    def admit(self,phase):self.calls.append(('admit',phase))
    def begin(self,name,progress=None):
        c=dict(name=name,state='baseline',store=Store(),progress_owned=progress is not None)
        if progress is not None:progress.own(c,lambda:self.cleanup(c),lambda:self.diagnostic(c))
        self.owned.append(c);self.calls.append(('begin',name));return c
    http_begin=identity_begin=config_begin=begin
    def diagnostic(self,c):return sample('operator',102,state_sha256=a.digest(c['state'].encode()),owned_contexts=len(self.owned))
    def baseline(self,c):
        self.calls.append(('baseline',c['name']))
        if self.fail_at=='baseline':raise Failure('positive_baseline_failed')
        return sample('worker',101,exit=0,state_sha256=a.digest(b'baseline'))
    http_baseline=identity_baseline=config_baseline=baseline
    def fault(self,c,name):
        self.calls.append(('fault',name))
        if self.fail_at=='fault':raise Failure('fault_not_reached')
        c['state']='faulted'
        return sample('operator',102,boundary=name,count=1)
    http_fault=identity_fault=config_fault=fault
    def observe(self,c,name,progress=None):
        self.calls.append(('observe',name))
        if self.fail_at=='observe':raise Failure('observation_failed')
        if c['state']!='faulted':raise Failure('wrong_boundary')
        out=sample('worker',101,observed=2 if self.fail_at=='assertion' else 1)
        if progress is not None:progress.measured('outcome',out);progress.attempt('independent')
        if self.fail_at=='independent':raise Failure('independent_failed')
        other=sample('operator',102,observed=1)
        if progress is not None:progress.measured('independent',other)
        return out,other,[check('outcome','observed',1),check('independent','observed',1)]
    http_observe=identity_observe=observe
    def cleanup(self,c):
        self.calls.append(('cleanup',c['name']))
        if self.cleanup_error:raise Failure('secondary_cleanup')
        c['state']='baseline';self.owned.remove(c)
        return sample('operator',102,remaining=len(self.owned),exit=0)
    http_cleanup=identity_cleanup=config_cleanup=cleanup
    def cleanup_all(self):
        for c in list(self.owned):
            if not c['progress_owned']:self.cleanup(c)
    def secondary(self,code):self.secondary_errors.append(code)
    def config_storage(self,c):
        if c['name']=='reconcile-served':c['store'].live=b'new'
        return c['store']
    def config_execute(self,c):
        def execute(command):
            self.calls.append(('command',command))
            if c['name']=='invalid-candidate':return dict(exit=1)
            if command==COMMANDS['reload']:
                if c['name']=='unknown-reload':raise Failure('controlled_timeout')
                if c['name']=='failed-reload':return dict(exit=1)
            return dict(exit=0)
        return execute
    def config_observer(self,c):return lambda:'new' if c['store'].live==b'new' else 'old'
    def config_observe(self,c,name,value,actions,progress=None):
        self.calls.append(('observe',name))
        if self.fail_at=='observe':raise Failure('observation_failed')
        if name=='routine-zero-config-actions':expected='VERIFIED';actual='VERIFIED' if value['config_actions']==0 else 'UNKNOWN'
        else:expected='REJECTED' if name=='invalid-candidate' else 'UNKNOWN' if name in ('failed-reload','unknown-reload') else 'VERIFIED';actual=value['result']
        out=sample('worker',101,result='ASSERTION_MISMATCH' if self.fail_at=='assertion' else actual)
        if progress is not None:progress.measured('outcome',out);progress.attempt('independent')
        if self.fail_at=='independent':raise Failure('independent_failed')
        other=sample('operator',102,observed=1)
        if progress is not None:progress.measured('independent',other)
        return out,other,[check('outcome','result',expected),check('independent','observed',1)]
    def routine_publish_restore(self,c):self.calls.append(('routine',c['name']));return dict(config_actions=0,pid=101)
    def component(self,case,record):
        for name in a.OBSERVATIONS[case]:
            c=self.begin(name);base=self.baseline(c);fault=self.fault(c,name);out,other,checks=self.observe(c,name);cleanup=self.cleanup(c)
            record.last=[case,name];record.add(case,name,base,fault,out,other,cleanup,checks)
        return dict(result='PASS',observations={name:record.rows[case,name] for name in a.OBSERVATIONS[case]})


class FileBackend(ControlledBackend):
    """Owned desktop marker files expose ordering; their bytes are never report data."""
    def __init__(self,owner,work,reports,case,mode=None,name=None,cleanup_error=False):
        super().__init__(owner)
        self.work,self.reports=Path(work),Path(reports);self.target=case;self.mode=mode
        self.target_name=name or a.OBSERVATIONS[case][0];self.cleanup_fault=cleanup_error
        self.audit=[];self.pending_audit=[];self.samples=[];self.original_error=None
    def selected(self,c):return c.get('case')==self.target and c['name']==self.target_name
    def mode_for(self,c):self.fail_at=self.mode if self.selected(c) else None
    def begin_file(self,case,name,progress):
        c=super().begin(name,progress);c['case']=case
        c['marker']=self.work/('marker-'+str(a.PARENTS.index(case))+'-'+str(a.OBSERVATIONS[case].index(name)))
        c['marker'].write_bytes(b'constructing' if self.selected(c) and self.mode=='begin' else b'baseline')
        s=c['marker'].stat();c['inode']=(s.st_dev,s.st_ino)
        c['store']=FileStore(c['marker'].with_suffix('.pending'),self,c)
        c['private']='PRIVATE_HANDLE_CREDENTIAL_CANARY'
        if self.selected(c) and self.mode=='begin':
            self.original_error=Failure('PRIVATE_BEGIN_MESSAGE_CANARY');raise self.original_error
        return c
    def http_begin(self,name,progress):return self.begin_file(a.PARENTS[-3],name,progress)
    def config_begin(self,name,progress):return self.begin_file(a.PARENTS[-2],name,progress)
    def identity_begin(self,name,progress):return self.begin_file(a.PARENTS[-1],name,progress)
    def diagnostic(self,c):
        path=c.get('marker');raw=path.read_bytes() if path is not None and path.exists() else None
        return sample('operator',102,marker_present=int(raw is not None),marker_sha256=None if raw is None else a.digest(raw))
    def baseline(self,c):
        self.mode_for(c);value=super().baseline(c)
        if c.get('marker') is not None:value['data']['state_sha256']=a.digest(c['marker'].read_bytes())
        self.samples.append(value);return value
    http_baseline=identity_baseline=config_baseline=baseline
    def fault(self,c,name):
        self.mode_for(c)
        if self.selected(c) and self.mode=='fault':c['marker'].write_bytes(b'partial-fault')
        value=super().fault(c,name)
        if c.get('marker') is not None:
            c['marker'].write_bytes(b'faulted');value['data']['state_sha256']=a.digest(c['marker'].read_bytes())
        self.samples.append(value);return value
    http_fault=identity_fault=config_fault=fault
    def observe(self,c,name,progress=None):
        self.mode_for(c);return super().observe(c,name,progress)
    http_observe=identity_observe=observe
    def config_observe(self,c,name,value,actions,progress=None):
        self.mode_for(c);return super().config_observe(c,name,value,actions,progress)
    def config_execute(self,c):
        execute=super().config_execute(c)
        def command(cmd):
            if self.selected(c) and self.mode=='provision':
                self.original_error=Failure('PRIVATE_PROVISION_MESSAGE_CANARY',{'password':'PRIVATE_ARGUMENT_CANARY'})
                raise self.original_error
            return execute(cmd)
        return command
    def snapshots(self,c,boundary):
        found=[]
        for path in self.reports.glob('cp-*.json'):
            raw=path.read_bytes();value=a.decode(raw)
            if value['case']==c.get('case') and value['name']==c['name'] and value['boundary']==boundary:found.append((path.name,raw))
        return found
    def cleanup(self,c):
        self.cleanup_error=self.selected(c) and self.cleanup_fault
        if c.get('marker') is not None:
            self.audit.append(dict(case=c['case'],name=c['name'],before=c['marker'].read_bytes(),captures=self.snapshots(c,'observation')))
        if self.selected(c) and self.mode=='cleanup-incomplete' and not c.get('cleanup_attempted'):
            c['cleanup_attempted']=True
            return sample('operator',102,remaining=1,exit=1)
        result=super().cleanup(c)
        if c.get('marker') is not None:
            s=c['marker'].stat()
            if (s.st_dev,s.st_ino)!=c['inode']:raise Failure('marker_replaced')
            c['marker'].unlink()
        # A backend retaining references cannot mutate recorded evidence during restoration.
        for value in self.samples:value['data']['state_sha256']='f'*64
        return result
    http_cleanup=identity_cleanup=config_cleanup=cleanup


class FileStore(Store):
    def __init__(self,path,backend,context):super().__init__();self.path=path;self.backend=backend;self.context=context
    def stage(self,variant):
        super().stage(variant)
        with self.path.open('xb') as handle:handle.write(self.pending)
        s=self.path.stat();self.inode=(s.st_dev,s.st_ino)
    def install(self):super().install();self.path.unlink()
    def pending_snapshot(self):
        raw=self.path.read_bytes() if self.path.exists() else None
        return dict(pending_present=int(raw is not None),pending_bytes=len(raw or b''),pending_sha256=None if raw is None else a.digest(raw))
    def cleanup_pending(self):
        if self.path.exists():
            self.backend.pending_audit.append(dict(before=self.path.read_bytes(),captures=self.backend.snapshots(self.context,'provision-pending')))
            s=self.path.stat()
            if (s.st_dev,s.st_ino)!=self.inode:raise Failure('pending_replaced')
            self.path.unlink()
        super().cleanup_pending()

class Controls(unittest.TestCase):
    def lifecycle_run(self,case,mode=None,name=None,cleanup_error=False,capture_error=False):
        folder=tempfile.TemporaryDirectory(prefix='m6e-lifecycle-');self.addCleanup(folder.cleanup)
        root=Path(folder.name);work=root/'work';work.mkdir();reports=root/'reports';reports.mkdir();backends=[]
        def factory(owner):
            backend=FileBackend(owner,work,reports,case,mode,name,cleanup_error);backends.append(backend);return backend
        import WebsitePlatformM6EDrivers as drivers
        real=drivers.publish_bytes
        def publish(directory,stem,raw):
            value=a.decode(raw)
            if capture_error and value['case']==case and value['name']==(name or a.OBSERVATIONS[case][0]) and value['boundary']=='observation':
                raise OSError('PRIVATE_CAPTURE_PATH_CANARY')
            return real(directory,stem,raw)
        with mock.patch.object(drivers,'publish_bytes',side_effect=publish):result=coordinate(identity(),'controlled-local',factory,reports)
        saved=a.decode((reports/'report.json').read_bytes());return result,saved,backends[0],reports

    def assert_checkpoint_at_cleanup(self,backend,case,name,wanted):
        audits=[x for x in backend.audit if x['case']==case and x['name']==name]
        self.assertTrue(audits);audit=audits[0];self.assertEqual(audit['before'],wanted)
        self.assertEqual(len(audit['captures']),1)
        filename,raw=audit['captures'][0];receipt=dict(file=filename,bytes=len(raw),sha256=a.digest(raw))
        value=a.validate_checkpoint(raw,identity(),case,name,'observation',receipt)
        self.assertEqual(value['observation']['lifecycle']['notes'][-1]['event']['data']['marker_sha256'],a.digest(wanted))
        self.assertTrue(any(x['state']!='RELEASED' for x in value['context']['resources']))
        return value['observation']

    def test_lifecycle_failures_preserve_actual_stages_before_marker_removal(self):
        scenarios=(('baseline',[],b'baseline','baseline'),('fault',['baseline'],b'partial-fault','fault'),
                   ('observe',['baseline','fault'],b'faulted','outcome'),
                   ('independent',['baseline','fault','outcome'],b'faulted','independent'),
                   ('assertion',['baseline','fault','outcome','independent'],b'faulted','assertions'))
        for case in DRIVERS:
            for mode,stages,marker,attempted in scenarios:
                with self.subTest(case=case,mode=mode):
                    result,saved,backend,reports=self.lifecycle_run(case,mode)
                    name=a.OBSERVATIONS[case][0];row=saved['cases'][case]['observations'][name]
                    self.assertFalse(result['accepted']);self.assertEqual(row['result'],'FAILED')
                    checkpoint=self.assert_checkpoint_at_cleanup(backend,case,name,marker)
                    self.assertEqual([e['stage'] for e in checkpoint['events']],stages)
                    self.assertEqual(checkpoint['lifecycle']['attempted'],attempted)
                    self.assertEqual(checkpoint['lifecycle']['first_failure']['stage'],attempted)
                    self.assertEqual([e['stage'] for e in row['events']],stages+['cleanup'])
                    self.assertEqual(row['lifecycle']['capture']['observation']['result'],'PASS')
                    a.validate_partial(identity(),case,name,row,'controlled-local')
                    self.assertFalse(list(backend.work.glob('marker-*')))
                    self.assertEqual(len([x for x in backend.audit if x['case']==case and x['name']==name]),1)
                    if stages:self.assertEqual(row['events'][0]['data']['state_sha256'],a.digest(b'baseline'))
                    if mode=='assertion':
                        self.assertEqual(row['lifecycle']['evaluated'],['FAILED']);self.assertEqual(len(row['assertions']),1)
                        actual=next(e['data'] for e in row['events'] if e['stage']=='outcome')
                        self.assertEqual(actual.get('result',actual.get('observed')),'ASSERTION_MISMATCH' if case==a.PARENTS[-2] else 2)
                    else:self.assertEqual(row['assertions'],[])
                    with self.assertRaises(ValueError):a.validate(saved,identity(),native=False)

    def test_cleanup_only_and_combined_capture_cleanup_failures(self):
        for case in DRIVERS:
            for combined in (False,True):
                with self.subTest(case=case,combined=combined):
                    result,saved,backend,reports=self.lifecycle_run(case,'observe' if combined else None,
                                                                  cleanup_error=True,capture_error=combined)
                    name=a.OBSERVATIONS[case][0];row=saved['cases'][case]['observations'][name];life=row['lifecycle']
                    self.assertEqual(life['first_failure']['kind'],'case' if combined else 'cleanup')
                    self.assertEqual(life['first_failure']['stage'],'outcome' if combined else 'cleanup')
                    self.assertEqual(life['capture']['observation']['result'],'FAILED' if combined else 'PASS')
                    self.assertFalse(result['accepted']);self.assertEqual(saved['cleanup'],'FAILED')
                    self.assertTrue(any(r['state']=='UNRESOLVED' for r in saved['resources']))
                    self.assertEqual(len([x for x in backend.audit if x['case']==case and x['name']==name]),2)
                    if combined:
                        self.assertIn('capture',[x['kind'] for x in life['secondary']]);self.assertIn('cleanup',[x['kind'] for x in life['secondary']])
                        self.assertEqual(saved['capture'],'FAILED')
                    else:self.assert_checkpoint_at_cleanup(backend,case,name,b'faulted')
                    a.validate_partial(identity(),case,name,row,'controlled-local')

    def test_incomplete_cleanup_return_stays_owned_and_preserves_first_measurement(self):
        for case in DRIVERS:
            with self.subTest(case=case):
                result,saved,backend,_=self.lifecycle_run(case,'cleanup-incomplete')
                name=a.OBSERVATIONS[case][0];row=saved['cases'][case]['observations'][name]
                self.assert_checkpoint_at_cleanup(backend,case,name,b'faulted')
                self.assertFalse(result['accepted']);self.assertEqual(row['result'],'FAILED')
                self.assertEqual(row['lifecycle']['first_failure']['kind'],'cleanup')
                self.assertEqual(next(e for e in row['events'] if e['stage']=='cleanup')['data']['remaining'],1)
                attempts=[x['result'] for x in row['lifecycle']['cleanup'] if x['boundary']=='observation']
                self.assertEqual(attempts,['FAILED','PASS']);self.assertEqual(saved['cleanup'],'PASS')
                self.assertTrue(all(x['state']=='RELEASED' for x in saved['resources']))
                self.assertFalse(list(backend.work.glob('marker-*')))
                self.assertEqual(len([x for x in backend.audit if x['case']==case and x['name']==name]),2)
                a.validate_partial(identity(),case,name,row,'controlled-local')

    def test_capture_only_failure_blocks_success_but_does_not_skip_cleanup(self):
        for case in DRIVERS:
            with self.subTest(case=case):
                result,saved,backend,_=self.lifecycle_run(case,capture_error=True)
                row=saved['cases'][case]['observations'][a.OBSERVATIONS[case][0]]
                self.assertEqual(row['lifecycle']['first_failure']['kind'],'capture');self.assertEqual(saved['cleanup'],'PASS')
                self.assertFalse(result['accepted']);self.assertFalse(list(backend.work.glob('marker-*')))

    def test_begin_failure_has_owned_capture_and_cleanup_without_returned_handle(self):
        for case in DRIVERS:
            with self.subTest(case=case):
                result,saved,backend,_=self.lifecycle_run(case,'begin');name=a.OBSERVATIONS[case][0]
                row=saved['cases'][case]['observations'][name]
                checkpoint=self.assert_checkpoint_at_cleanup(backend,case,name,b'constructing')
                self.assertEqual(checkpoint['events'],[]);self.assertEqual(row['lifecycle']['first_failure']['stage'],'begin')
                self.assertEqual(row['result'],'FAILED');self.assertEqual(saved['cleanup'],'PASS')
                self.assertTrue(all(r['state']=='RELEASED' for r in saved['resources']));self.assertFalse(result['accepted'])

    def test_multiple_observations_and_snapshot_names_do_not_overwrite(self):
        for case in DRIVERS:
            with self.subTest(case=case):
                names=a.OBSERVATIONS[case];result,saved,backend,reports=self.lifecycle_run(case,'observe',names[1])
                rows=saved['cases'][case]['observations']
                self.assertEqual(rows[names[0]]['result'],'PASS');self.assertEqual(rows[names[1]]['result'],'FAILED')
                self.assertTrue(all(rows[n]['result']=='UNREACHED' for n in names[2:]))
                first=self.assert_checkpoint_at_cleanup(backend,case,names[0],b'faulted')
                second=self.assert_checkpoint_at_cleanup(backend,case,names[1],b'faulted')
                one=rows[names[0]]['lifecycle']['capture']['observation']['receipt'];two=rows[names[1]]['lifecycle']['capture']['observation']['receipt']
                self.assertNotEqual(one['file'],two['file'])
                before=(reports/one['file']).read_bytes()
                with self.assertRaises(FileExistsError):publish_bytes(reports,one['file'][:-5],b'overwrite')
                self.assertEqual((reports/one['file']).read_bytes(),before)

    def test_pending_configuration_is_captured_before_nested_cleanup(self):
        case=a.PARENTS[-2];result,saved,backend,reports=self.lifecycle_run(case,'provision')
        self.assertEqual(len(backend.pending_audit),1);audit=backend.pending_audit[0]
        self.assertEqual(audit['before'],b'new');self.assertEqual(len(audit['captures']),1)
        filename,raw=audit['captures'][0];name=a.OBSERVATIONS[case][0]
        checkpoint=a.validate_checkpoint(raw,identity(),case,name,'provision-pending',dict(file=filename,bytes=len(raw),sha256=a.digest(raw)))
        row=saved['cases'][case]['observations'][name]
        self.assertEqual(checkpoint['observation']['lifecycle']['first_failure']['stage'],'provision')
        note=checkpoint['observation']['lifecycle']['notes'][-1]['event']['data']
        self.assertEqual(note,dict(pending_present=1,pending_bytes=3,pending_sha256=a.digest(b'new')))
        self.assertFalse(list(backend.work.glob('*.pending')));self.assertEqual(row['lifecycle']['capture']['provision-pending']['result'],'PASS')
        self.assertFalse(result['accepted'])
        for path in reports.iterdir():self.assertNotIn(b'PRIVATE_',path.read_bytes())

    def test_primary_exception_survives_capture_and_cleanup_errors_by_identity(self):
        import WebsitePlatformM6EDrivers as drivers
        for case in DRIVERS:
            with self.subTest(case=case),tempfile.TemporaryDirectory(prefix='m6e-primary-') as folder:
                error=Failure('PRIVATE_EXCEPTION_MESSAGE_CANARY',{'token':'PRIVATE_TOKEN_CANARY'})
                backend=ControlledBackend(cleanup_error=True)
                def observe(*args):raise error
                if case==a.PARENTS[-3]:backend.http_observe=observe
                elif case==a.PARENTS[-2]:backend.config_observe=observe
                else:backend.identity_observe=observe
                record=Recorder(identity(),'controlled-local',folder);real=drivers.publish_bytes
                def publish(directory,stem,raw):
                    if a.decode(raw)['boundary']=='observation':raise OSError('PRIVATE_CAPTURE_CANARY')
                    return real(directory,stem,raw)
                with mock.patch.object(drivers,'publish_bytes',side_effect=publish),self.assertRaises(Failure) as caught:dispatch(case,backend,record)
                self.assertIs(caught.exception,error)
                row=record.rows[case,a.OBSERVATIONS[case][0]]
                self.assertEqual(row['lifecycle']['first_failure']['kind'],'case')
                self.assertEqual([x['kind'] for x in row['lifecycle']['secondary']],['capture','cleanup'])
                self.assertNotIn(b'PRIVATE_',a.canonical(row))

    def test_owned_child_is_captured_before_case_cleanup_reaps_it(self):
        for case in DRIVERS:
            with self.subTest(case=case),tempfile.TemporaryDirectory(prefix='m6e-child-checkpoint-') as folder:
                root=Path(folder);work=root/'work';work.mkdir();reports=root/'reports';reports.mkdir();owner=Owner();backends=[]
                class Backend(FileBackend):
                    def begin_file(self,kind,name,progress):
                        c=super().begin_file(kind,name,progress)
                        if self.selected(c):
                            child=subprocess.Popen([sys.executable,'-I','-B','-c','import time;time.sleep(10)'],
                                stdin=subprocess.DEVNULL,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=os.name!='nt')
                            owner.children.append(child)
                            child_context=dict(pid=child.pid,exit=None,signal=0,last_barrier='SPAWNED',reaped=False,
                                               termination_requested=False,exit_before_cleanup=None)
                            owner.context.append(child_context);c['child']=child;c['child_context']=child_context
                        return c
                    def cleanup(self,c):
                        child=c.get('child')
                        if child is not None:
                            ctx=c['child_context'];ctx['exit_before_cleanup']=child.poll();ctx['termination_requested']=True
                            child.kill();child.wait(timeout=5);ctx.update(exit=child.returncode,signal=max(0,-child.returncode),reaped=True)
                        return super().cleanup(c)
                def factory(current):
                    backend=Backend(current,work,reports,case,'observe');backends.append(backend);return backend
                result=coordinate(identity(),'controlled-local',factory,reports,owner)
                backend=backends[0];name=a.OBSERVATIONS[case][0]
                audit=next(x for x in backend.audit if x['case']==case and x['name']==name)
                checkpoint=a.decode(audit['captures'][0][1]);before=checkpoint['context']['children'][-1]
                after=result['report']['children'][-1]
                self.assertEqual(before['pid'],after['pid']);self.assertFalse(before['reaped']);self.assertIsNone(before['exit'])
                self.assertFalse(before['termination_requested']);self.assertTrue(after['reaped']);self.assertTrue(after['termination_requested'])
                self.assertIsNone(after['exit_before_cleanup']);self.assertFalse(result['accepted'])

    def test_diagnostic_privacy_bindings_tampering_and_fabricated_completion(self):
        for case in DRIVERS:
            with self.subTest(case=case):
                result,saved,backend,reports=self.lifecycle_run(case,'begin');name=a.OBSERVATIONS[case][0]
                row=saved['cases'][case]['observations'][name];receipt=row['lifecycle']['capture']['observation']['receipt']
                raw=(reports/receipt['file']).read_bytes()
                for path in reports.iterdir():
                    data=path.read_bytes();self.assertNotIn(b'PRIVATE_',data);self.assertNotIn(str(backend.work).encode(),data)
                with self.assertRaises(ValueError):a.validate_checkpoint(raw+b' ',identity(),case,name,'observation',receipt)
                for key in ('source_sha','run_id','config_sha256'):
                    wrong=identity();wrong[key]='e'*len(wrong[key])
                    with self.assertRaises(ValueError):a.validate_checkpoint(raw,wrong,case,name,'observation',receipt)
                with self.assertRaises(ValueError):a.validate_checkpoint(raw,identity(),case,a.OBSERVATIONS[case][1],'observation',receipt)
                fake=copy.deepcopy(row);fake['result']='PASS'
                with self.assertRaises(ValueError):a.validate_observation(identity(),case,name,fake,'controlled-local')
                with self.assertRaises(ValueError):a.validate_partial(identity(),case,name,row,'native')

    def test_process_exit_context_distinguishes_original_exit_and_cleanup_termination(self):
        owner=Owner();result=owner.capture([sys.executable,'-I','-B','-c','raise SystemExit(7)'])
        self.assertEqual(result['exit'],7);self.assertEqual(owner.context[0]['exit_before_cleanup'],7)
        if os.name=='nt':self.assertFalse(owner.context[0]['termination_requested'])
        with self.assertRaisesRegex(RuntimeError,'child_timeout'):
            owner.capture([sys.executable,'-I','-B','-c','import time;time.sleep(10)'],.2)
        self.assertIsNone(owner.context[1]['exit_before_cleanup']);self.assertTrue(owner.context[1]['termination_requested'])
        owner.cleanup();self.assertTrue(all(c['reaped'] for c in owner.context))

    def test_existing_inventory_stays_exact_without_executing_native_entrypoint(self):
        path=Path(__file__).with_name('WebsitePlatformM6EIsolated.py')
        tree=ast.parse(path.read_text(encoding='utf-8'))
        entry=next(n for n in tree.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='INVENTORY' for t in n.targets))
        self.assertEqual(ast.literal_eval(entry.value),a.PARENTS)

    def test_component_construction_closes_opened_tree_without_adopting_roots(self):
        spec=importlib.util.spec_from_file_location('isolated_controls',Path(__file__).with_name('WebsitePlatformM6EIsolated.py'))
        module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
        first=mock.Mock()
        with mock.patch.object(module.p,'Tree',side_effect=[first,Failure('second_tree_failed')]),mock.patch.object(module.os,'geteuid',return_value=2100,create=True),mock.patch.object(module.os,'getegid',return_value=2100,create=True):
            with self.assertRaisesRegex(RuntimeError,'second_tree_failed'):
                module.Fixture(dict(source_root='unused-source',served_root='unused-served',journal_root='unused-journal'),{})
        self.assertTrue(first.close.called)

    def test_actual_dispatch_all_three_baseline_boundary_cleanup(self):
        for case in DRIVERS:
            with self.subTest(case=case),tempfile.TemporaryDirectory(prefix='m6e-dispatch-') as folder:
                backend=ControlledBackend();r=Recorder(identity(),'controlled-local',folder);row=dispatch(case,backend,r)
                self.assertEqual(row['result'],'PASS');self.assertEqual(list(row['observations']),list(a.OBSERVATIONS[case]))
                for name in a.OBSERVATIONS[case]:
                    selected=[kind for kind,value in backend.calls if value==name]
                    expected=['begin','baseline','fault']+(['routine'] if name=='routine-zero-config-actions' else [])+['observe','cleanup']
                    self.assertEqual(selected,expected)
                self.assertEqual(backend.owned,[])

    def test_driver_failures_cannot_become_pass_and_cleanup_preserves_primary(self):
        for case in DRIVERS:
            for at in ('baseline','fault','observe'):
                with self.subTest(case=case,at=at),tempfile.TemporaryDirectory(prefix='m6e-failure-') as folder:
                    backend=ControlledBackend(fail_at=at,cleanup_error=True);r=Recorder(identity(),'controlled-local',folder)
                    with self.assertRaisesRegex(RuntimeError,{'baseline':'positive_baseline_failed','fault':'fault_not_reached','observe':'observation_failed'}[at]):
                        dispatch(case,backend,r)
                    row=r.rows[case,a.OBSERVATIONS[case][0]]
                    self.assertEqual(row['result'],'FAILED');self.assertTrue(row['lifecycle']['secondary'])
                    self.assertEqual([e['stage'] for e in row['events']],[] if at=='baseline' else ['baseline'] if at=='fault' else ['baseline','fault'])
                    self.assertEqual(row['lifecycle']['capture']['observation']['result'],'PASS')
                    if at=='baseline':self.assertFalse(any(c[0]=='fault' for c in backend.calls))

    def test_coordinator_accepts_only_complete_controlled_result(self):
        with tempfile.TemporaryDirectory(prefix='m6e-coordinator-') as folder:
            result=coordinate(identity(),'controlled-local',ControlledBackend,folder)
            self.assertTrue(result['accepted']);self.assertEqual(result['exit'],0)
            raw=Path(folder,'report.json').read_bytes();receipt=a.decode(Path(folder,'publication.json').read_bytes())
            self.assertTrue(a.validate_published(raw,identity(),receipt,native=False))
            self.assertTrue(Path(folder,'evidence-before-cleanup.json').exists())
            self.assertEqual(a.decode(raw)['resources'][0]['state'],'RELEASED')
            with self.assertRaises(ValueError):a.validate_published(raw+b' ',identity(),receipt,native=False)
            with self.assertRaises(ValueError):a.validate_published(raw,identity(),receipt,native=True)

    def test_partial_construction_capture_precedes_cleanup_and_primary_survives(self):
        with tempfile.TemporaryDirectory(prefix='m6e-partial-') as folder:
            removed=[]
            def factory(owner):
                def cleanup():
                    self.assertTrue(Path(folder,'evidence-before-cleanup.json').exists());removed.append(True)
                    raise Failure('cleanup_failure')
                owner.track('partial','private-files',b'owned identity',cleanup)
                raise Failure('construction_primary')
            result=coordinate(identity(),'controlled-local',factory,folder)
            self.assertFalse(result['accepted']);self.assertEqual(result['exit'],1);self.assertEqual(removed,[True])
            self.assertEqual(result['report']['first_failure']['code'],'control_failure')
            self.assertEqual(result['report']['cleanup'],'FAILED')
            self.assertTrue(all(row['result']=='UNREACHED' for row in result['report']['cases'].values()))

    def test_missing_publication_receipt_cannot_return_zero(self):
        import WebsitePlatformM6ECoordinator as coordinator
        real=coordinator.publish_bytes
        def publish(root,stem,raw):
            if stem=='publication':raise Failure('receipt_write_failed')
            return real(root,stem,raw)
        with tempfile.TemporaryDirectory(prefix='m6e-receipt-') as folder,mock.patch.object(coordinator,'publish_bytes',side_effect=publish):
            result=coordinate(identity(),'controlled-local',ControlledBackend,folder)
            self.assertEqual(result['exit'],1);self.assertFalse(result['accepted'])
            self.assertEqual(result['report']['first_failure']['code'],'control_failure')
            original=Path(folder,'report.json').read_bytes()
            self.assertEqual(a.decode(original)['publication'],'PENDING')
            self.assertFalse(Path(folder,'publication.json').exists())
            with self.assertRaises(ValueError):a.validate_published(original,identity(),{},native=False)

    def test_native_missing_steps_block_before_preparation_effects(self):
        import WebsitePlatformM6EPreparation as preparation
        with mock.patch.object(preparation,'read_preflight',side_effect=AssertionError('preflight read')):
            with self.assertRaisesRegex(RuntimeError,'native_implementation_incomplete'):preparation.prepare()
        with self.assertRaisesRegex(RuntimeError,'native_implementation_incomplete'):require_native_ready()

    def test_native_self_serving_assertions_do_not_override_source_policy(self):
        case='apache_private_health_headers';name='candidate';i=identity();row=measured(i,case,name)
        row['lane']='apache-tls';row['events'][2]['data']={'health':'fail'};row['events'][3]['data']={'status':503}
        row['assertions']=[check('outcome','health','fail'),check('independent','status',503)]
        row['evidence_sha256']=a.digest(a.canonical(row['events']))
        with self.assertRaisesRegex(ValueError,'native_checker_result'):a.validate_observation(i,case,name,row,'native')

    def test_definitions_imports_have_no_runtime_side_effects(self):
        modules=('WebsitePlatformM6EAcceptance','WebsitePlatformM6EDrivers','WebsitePlatformM6EProvision','WebsitePlatformM6EPreparation',
                 'WebsitePlatformM6EProcess','WebsitePlatformM6EApache','WebsitePlatformM6ENativeBackend')
        with mock.patch('socket.socket',side_effect=AssertionError('network')),mock.patch('subprocess.Popen',side_effect=AssertionError('process')):
            for name in modules:
                spec=importlib.util.spec_from_file_location(name+'_definitions',importlib.util.find_spec(name).origin)
                spec.loader.exec_module(importlib.util.module_from_spec(spec))

    def test_inventory_exact_and_no_substituted_counts(self):
        r=report();self.assertTrue(a.validate(r,identity(),native=False))
        self.assertEqual(len(r['cases']),13)
        for mutate in ('missing','unknown','renamed','count','incomplete','duplicate'):
            with self.subTest(mutate=mutate):
                bad=copy.deepcopy(r);case=a.PARENTS[-1];row=bad['cases'][case]['observations'];name=next(iter(row))
                if mutate=='missing':row.pop(name)
                elif mutate=='unknown':row['unknown']=row[name]
                elif mutate=='renamed':row[name+'-renamed']=row.pop(name)
                elif mutate=='count':bad['cases'][case]={'result':'PASS','assertions':9999}
                elif mutate=='incomplete':row[name]={'result':'INCOMPLETE'}
                else:row[name]['events'].append(row[name]['events'][-1])
                with self.assertRaises((ValueError,KeyError,TypeError)):a.validate(bad,identity(),native=False)

    def test_binding_source_run_role_endpoint_config(self):
        for key in ('source_sha','run_id','config_sha256','roles','endpoints'):
            bad=report();bad['binding'][key]=None
            with self.subTest(key=key),self.assertRaises(ValueError):a.validate(bad,identity(),native=False)

    def test_controlled_lane_never_native_acceptance(self):
        with self.assertRaises(ValueError):a.validate(report(),identity())
        bad=report();bad['cases'][a.PARENTS[0]]['observations']['exact-stage']['lane']='linux-component'
        with self.assertRaises(ValueError):a.validate(bad,identity(),native=False)

    def test_baseline_fault_independence_and_tampering(self):
        for part,key,value in ((0,'exit',1),(1,'count',0),(1,'boundary','other'),(4,'remaining',1)):
            bad=report();v=bad['cases'][a.PARENTS[0]]['observations']['exact-stage'];v['events'][part]['data'][key]=value
            v['evidence_sha256']=a.digest(a.canonical(v['events']))
            with self.subTest(part=part,key=key),self.assertRaises(ValueError):a.validate(bad,identity(),native=False)
        bad=report();bad['cases'][a.PARENTS[0]]['observations']['exact-stage']['evidence_sha256']='f'*64
        with self.assertRaises(ValueError):a.validate(bad,identity(),native=False)

    def test_capture_cleanup_publication_and_unreached(self):
        for key in ('capture','cleanup','publication'):
            bad=report();bad[key]='FAILED'
            with self.subTest(key=key),self.assertRaises(ValueError):a.validate(bad,identity(),native=False)
        with self.assertRaises(ValueError):a.validate(a.initial(identity(),'controlled-local'),identity(),native=False)
        bad=report();bad['children']=[dict(pid=12,exit=0,signal=0,last_barrier='EXIT',reaped=False)]
        with self.assertRaises(ValueError):a.validate(bad,identity(),native=False)

    def test_duplicate_json_and_private_data_rejected(self):
        with self.assertRaises(ValueError):a.decode(b'{"x":1,"x":2}')
        with self.assertRaises(ValueError):a.event(identity(),a.PARENTS[0],'exact-stage','outcome','worker',12,1,dict(password='secret'))

    def test_provision_invalid_preserves_and_never_reloads(self):
        store=Store();calls=[]
        def execute(cmd):calls.append(cmd);return dict(exit=1)
        p=Provisioner(store,execute,lambda:'old',lambda:None);old=store.snapshot();r=p.apply('invalid')
        self.assertEqual(r['result'],'REJECTED');self.assertEqual(store.snapshot(),old)
        self.assertEqual(calls,[COMMANDS['validate']]);self.assertIsNone(store.pending)

    def test_provision_success_requires_observed_served_config(self):
        for observed,expected in (('old','UNKNOWN'),('new','VERIFIED')):
            store=Store();p=Provisioner(store,lambda cmd:dict(exit=0),lambda:observed,lambda:None)
            self.assertEqual(p.apply('valid')['result'],expected)

    def test_failed_unknown_reload_never_rolls_back(self):
        for outcome in ('failed','timeout'):
            store=Store()
            def execute(cmd):
                if cmd==COMMANDS['reload']:
                    if outcome=='timeout':raise Failure('child_timeout')
                    return dict(exit=1)
                return dict(exit=0)
            p=Provisioner(store,execute,lambda:'old',lambda:None)
            self.assertEqual(p.apply('valid')['result'],'UNKNOWN');self.assertEqual(store.live,b'new')

    def test_provision_primary_survives_cleanup_and_preflight_first(self):
        store=Store();store.cleanup_fail=True
        p=Provisioner(store,lambda cmd:(_ for _ in ()).throw(Failure('primary')),lambda:'old',lambda:None)
        with self.assertRaisesRegex(Failure,'primary'):p.apply('valid')
        p=Provisioner(store,lambda cmd:self.fail('command'),lambda:self.fail('observe'),lambda:(_ for _ in ()).throw(Failure('admission')))
        with self.assertRaisesRegex(Failure,'admission'):p.apply('valid')

    def test_commands_and_template_do_not_accept_arbitrary_targets(self):
        p=Provisioner(Store(),lambda cmd:self.fail('executed'),lambda:None,lambda:None)
        with self.assertRaises(Failure):p.command('/bin/sh')
        with self.assertRaises(Failure):p.apply('../../etc/apache2')
        with self.assertRaises(ValueError):render('@ROOT@',{'ROOT':'x\nExecStart=/bin/sh'})
        with self.assertRaises(ValueError):render('@UNRESOLVED@',{})

    def test_preparation_rejects_host_and_missing_capabilities(self):
        i=identity();c=dict(version=1,run_id=i['run_id'],source_sha=i['source_sha'],machine_id=i['machine_id'],boot_id=i['boot_id'],tool_sha256={k:'e'*64 for k in BINARIES},expires_at=1001)
        f=dict(platform='linux',uid=0,container='none',virtualization='kvm',hostname='ubo-m6e-'+i['run_id'][:16],machine_id=i['machine_id'],boot_id=i['boot_id'],
            cmdline='ubo.m6e='+i['run_id'],interfaces=['lo'],routes=[],pid1='systemd',filesystems=['ext4'],source_sha=i['source_sha'],source_status='',source_readonly=True,
            tools=c['tool_sha256'],now=1000,docker_socket=False,existing_roots=[],existing_units=[])
        self.assertEqual(admit_facts(c,f),c)
        for key,value in (('platform','win32'),('uid',1000),('container','docker'),('hostname','real-host'),('interfaces',['lo','eth0']),
            ('routes',['default']),('filesystems',['overlay']),('source_readonly',False),('existing_roots',['/etc/ubo-site-publisher']),('tools',{}),('pid1','python')):
            with self.subTest(key=key),self.assertRaises(ValueError):admit_facts(c,f|{key:value})

    def test_bounded_real_child_output_and_timeout(self):
        for code,expected in (("import sys;sys.stdout.write('x'*200000)",'child_output_bound'),("import time;time.sleep(10)",'child_timeout')):
            owner=Owner()
            with self.subTest(expected=expected),self.assertRaisesRegex(RuntimeError,expected):owner.capture([sys.executable,'-I','-B','-c',code],.5,limit=1024)
            owner.cleanup();self.assertTrue(all(x['reaped'] for x in owner.context))

    def test_real_child_success_stderr_limit_and_owned_exit(self):
        owner=Owner();r=owner.capture([sys.executable,'-I','-B','-c',"print('safe')"])
        self.assertEqual((r['exit'],r['stdout']),(0,b'safe\r\n' if os.name=='nt' else b'safe\n'))
        with self.assertRaisesRegex(RuntimeError,'child_output_bound'):owner.capture([sys.executable,'-I','-B','-c',"import sys;sys.stderr.write('x'*9000)"],error_limit=100)
        owner.cleanup();self.assertTrue(all(x['reaped'] for x in owner.context))

    def test_report_original_bytes_and_no_replace(self):
        with tempfile.TemporaryDirectory(prefix='m6e-controls-') as folder:
            raw=a.canonical(report());receipt=publish_bytes(folder,'report',raw)
            self.assertEqual(Path(folder,'report.json').read_bytes(),raw);self.assertEqual(receipt['sha256'],a.digest(raw))
            with self.assertRaises(FileExistsError):publish_bytes(folder,'report',b'changed')
            self.assertEqual(Path(folder,'report.json').read_bytes(),raw)

    def test_publication_failure_never_claims_success(self):
        with tempfile.TemporaryDirectory(prefix='m6e-controls-') as folder:
            with mock.patch('WebsitePlatformM6EProcess.os.fsync',side_effect=OSError('modeled sync failure')):
                with self.assertRaises(OSError):publish_bytes(folder,'report',b'{}')
            self.assertFalse(Path(folder,'report.json').exists());self.assertFalse(Path(folder,'report.pending').exists())

if __name__=='__main__':unittest.main(verbosity=2)
