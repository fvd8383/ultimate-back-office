"""Desktop controls only. Does not prepare/run a VM, Apache, MySQL or a supervisor."""
import copy
import ast
import importlib
import json
import os
from pathlib import Path
import sys
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
    events=[]
    for j,stage in enumerate(('baseline','fault','outcome','independent','cleanup')):
        data=[dict(exit=0,state_sha256=a.digest(b'actual local fixture baseline')),dict(boundary=name,count=1),
              dict(observed=1),dict(observed=1),dict(remaining=0,exit=0)][j]
        events.append(a.event(i,case,name,stage,'worker' if j!=3 else 'coordinator',100 if j!=3 else 101,j+1,data))
    return a.observation(i,case,name,events,[check('outcome','observed',1),check('independent','observed',1)],'controlled-local')

def report():
    i=identity();r=a.initial(i,'controlled-local')
    for case in a.PARENTS:
        r['cases'][case]=dict(result='PASS',observations={n:measured(i,case,n) for n in a.OBSERVATIONS[case]})
    r.update(result='PASS',last_step=[a.PARENTS[-1],a.OBSERVATIONS[a.PARENTS[-1]][-1]],duration_ms=1,capture='PASS',cleanup='PASS',publication='PENDING')
    return r

class Store:
    def __init__(self): self.live=b'old';self.pending=None;self.cleanup_fail=False
    def snapshot(self):return a.digest(self.live)
    def stage(self,variant):self.pending=b'new' if variant=='valid' else b'invalid'
    def install(self):self.live=self.pending;self.pending=None
    def cleanup_pending(self):
        if self.cleanup_fail:raise Failure('cleanup_model')
        self.pending=None
    def matches_observed(self,v):return self.live==b'new' and v=='new'


class ControlledBackend:
    """Deterministic adapters, explicitly not TLS, OS identities or native SQL.

    Calls the actual three driver functions and actual provisioning state machine.
    Counters below describe this adapter only and cannot satisfy native acceptance.
    """
    def __init__(self,owner=None,fail_at=None,cleanup_error=False):
        self.calls=[];self.owned=[];self.secondary_errors=[];self.fail_at=fail_at;self.cleanup_error=cleanup_error
        if owner:owner.track('adapter','controlled-model',b'controlled-local',self.cleanup_all)
    def admit(self,phase):self.calls.append(('admit',phase))
    def begin(self,name):
        c=dict(name=name,state='baseline',store=Store());self.owned.append(c);self.calls.append(('begin',name));return c
    http_begin=identity_begin=config_begin=begin
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
    def observe(self,c,name):
        self.calls.append(('observe',name))
        if self.fail_at=='observe':raise Failure('observation_failed')
        if c['state']!='faulted':raise Failure('wrong_boundary')
        return sample('worker',101,observed=1),sample('operator',102,observed=1),[check('outcome','observed',1),check('independent','observed',1)]
    http_observe=identity_observe=observe
    def cleanup(self,c):
        self.calls.append(('cleanup',c['name']))
        if self.cleanup_error:raise Failure('secondary_cleanup')
        c['state']='baseline';self.owned.remove(c)
        return sample('operator',102,remaining=len(self.owned),exit=0)
    http_cleanup=identity_cleanup=config_cleanup=cleanup
    def cleanup_all(self):
        for c in list(self.owned):self.cleanup(c)
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
    def config_observe(self,c,name,value,actions):
        self.calls.append(('observe',name))
        if self.fail_at=='observe':raise Failure('observation_failed')
        if name=='routine-zero-config-actions':expected='VERIFIED';actual='VERIFIED' if value['config_actions']==0 else 'UNKNOWN'
        else:expected='REJECTED' if name=='invalid-candidate' else 'UNKNOWN' if name in ('failed-reload','unknown-reload') else 'VERIFIED';actual=value['result']
        return sample('worker',101,result=actual),sample('operator',102,observed=1),[check('outcome','result',expected),check('independent','observed',1)]
    def routine_publish_restore(self,c):self.calls.append(('routine',c['name']));return dict(config_actions=0,pid=101)
    def component(self,case,record):
        for name in a.OBSERVATIONS[case]:
            c=self.begin(name);base=self.baseline(c);fault=self.fault(c,name);out,other,checks=self.observe(c,name);cleanup=self.cleanup(c)
            record.last=[case,name];record.add(case,name,base,fault,out,other,cleanup,checks)
        return dict(result='PASS',observations={name:record.rows[case,name] for name in a.OBSERVATIONS[case]})

class Controls(unittest.TestCase):
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
            with self.subTest(case=case):
                backend=ControlledBackend();r=Recorder(identity(),'controlled-local');row=dispatch(case,backend,r)
                self.assertEqual(row['result'],'PASS');self.assertEqual(list(row['observations']),list(a.OBSERVATIONS[case]))
                for name in a.OBSERVATIONS[case]:
                    selected=[kind for kind,value in backend.calls if value==name]
                    expected=['begin','baseline','fault']+(['routine'] if name=='routine-zero-config-actions' else [])+['observe','cleanup']
                    self.assertEqual(selected,expected)
                self.assertEqual(backend.owned,[])

    def test_driver_failures_cannot_become_pass_and_cleanup_preserves_primary(self):
        for case in DRIVERS:
            for at in ('baseline','fault','observe'):
                with self.subTest(case=case,at=at):
                    backend=ControlledBackend(fail_at=at,cleanup_error=True);r=Recorder(identity(),'controlled-local')
                    with self.assertRaisesRegex(RuntimeError,{'baseline':'positive_baseline_failed','fault':'fault_not_reached','observe':'observation_failed'}[at]):
                        dispatch(case,backend,r)
                    self.assertEqual(r.rows,{});self.assertTrue(backend.secondary_errors)
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
            self.assertEqual(result['report']['first_failure']['code'],'construction_primary')
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
            self.assertEqual(result['report']['first_failure']['code'],'receipt_write_failed')
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
            for name in modules:importlib.reload(importlib.import_module(name))

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
