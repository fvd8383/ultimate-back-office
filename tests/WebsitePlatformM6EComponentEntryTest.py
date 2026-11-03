"""Local private-file/process controls; systemd, Linux identity and Apache are modeled."""
import copy
from contextlib import contextmanager
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
sys.path[:0]=[str(ROOT/'tests'),str(ROOT/'tests/support')]
from WebsitePlatformM6EBootstrapControlTest import ModelHost
import WebsitePlatformM6EBootstrapControlTest as bootstrap_controls
from WebsitePlatformM6EComponentTest import Local,ServingLocal
from WebsitePlatformM6EBootstrapHost import NativeHost
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EComponentHost import Native,serving_root,serving_inventory
from WebsitePlatformM6EAcceptance import canonical,decode,digest,need
from WebsitePlatformM6EProcess import Failure
import WebsitePlatformM6EComponent as c
import WebsitePlatformM6EComponentEntry as e
import WebsitePlatformM6EBootstrap as bootstrap
import WebsitePlatformM6EBootstrapPlan as b

class ServiceHost(ModelHost):
    """Explicit supervisor model; all original bootstrap file inspection is real."""
    def __init__(self,folder):
        super().__init__(folder);self.context=dict(pid=os.getpid(),cgroup=['0::/system.slice/'+e.UNIT],invocation='d'*32)
        self.unit_fault={};self.locked=False
    def component_context(self):return copy.deepcopy(self.context)
    def unit(self,name):
        value=super().unit(name)
        if name==e.UNIT and self.units.get(name):
            value.update(MainPID=str(os.getpid()),InvocationID='d'*32,ControlGroup='/system.slice/'+name,ActiveState='activating')
        if name==e.UNIT:value.update(self.unit_fault)
        return value

@contextmanager
def modeled_lock(host,ledger):
    need(not host.locked,'modeled_lifecycle_conflict');host.locked=True
    try:yield
    finally:host.locked=False

class Controls(unittest.TestCase):
    def temp(self):
        base='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        folder=tempfile.TemporaryDirectory(prefix='ubo-entry-',dir=base);self.addCleanup(folder.cleanup);return folder.name
    def ready(self):
        host=ServiceHost(self.temp());ledger,plan=bootstrap_controls.Controls.armed(self,host)
        bootstrap.prepare_owned(host,ledger,plan)
        return host,ledger,plan
    def dispatched(self):
        host,ledger,plan=self.ready()
        with mock.patch.object(e,'lifecycle_lock',modeled_lock):request=e.dispatch(host,ledger,plan)
        return host,Ledger.load(host.fs,ledger.config),plan,request
    def test_fixed_public_route_units_and_budgets(self):
        import WebsitePlatformM6EPreparation as preparation
        with mock.patch.object(e,'main',return_value=7) as main:
            self.assertEqual(preparation.main(['--component-a']),7);main.assert_called_once_with(['--component-a'])
        with self.assertRaisesRegex(ValueError,'usage'):preparation.main(['--component-a-owned'])
        raw=b.control_units()[e.UNIT]
        self.assertIn(b'--component-a-owned',raw);self.assertIn(b'TimeoutStartSec=420',raw)
        self.assertIn(b'KillMode=control-group',raw);self.assertIn(e.UNIT,b.UNITS)
        self.assertEqual((c.SECONDS,e.SECONDS,b.LIFETIME_SECONDS,b.CLEANUP_SECONDS),(90,420,1200,120))
    def test_valid_admitted_dispatch_and_supervisor(self):
        host,ledger,plan,request=self.dispatched()
        self.assertEqual(e.supervisor(host,ledger)['state'],'DISPATCHED')
        calls=[args for args,_ in host.calls if args[:3]==['/usr/bin/systemctl','start','--no-block']]
        self.assertEqual(calls,[['/usr/bin/systemctl','start','--no-block',e.UNIT]])
        self.assertEqual(decode(host.fs.read(ledger.root+'/component-a-request.json')),request)
        self.assertNotIn('commission',ledger.state['observations'])
    def test_direct_protected_entry_and_internal_runner_reject(self):
        host,ledger,plan=self.ready();host.context['cgroup']=['0::/user.slice/direct']
        with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex(ValueError,'component_supervisor_cgroup'):
            e.execute_owned(host,ledger,plan,lambda *_:self.fail('backend reached'))
        backend=Native.__new__(Native);backend.host=host;backend.ledger=ledger
        with self.assertRaisesRegex(ValueError,'component_supervisor_cgroup'):c.run(backend)
        self.assertNotIn('component_a',ledger.state['observations'])
        for mode in ('--component-a-owned','--component-a'):
            child=subprocess.run([sys.executable,'-I','-B',str(ROOT/'tests/support/WebsitePlatformM6EComponentEntry.py'),mode],capture_output=True,timeout=5)
            self.assertNotEqual(child.returncode,0);self.assertIn(b'COMPONENT UNRESOLVED',child.stdout)
    def test_existing_commission_supervisor_admission_stays_distinct(self):
        host,ledger,_=self.ready();unit='ubo-m6e-commission.service';group='/system.slice/'+unit
        host.context=dict(pid=os.getpid(),cgroup=['0::'+group],invocation='e'*32)
        ledger.state['observations']['commission']={};ledger.state['phase']='commissioning'
        row=dict(unit=unit,state='ACTIVE',pid=os.getpid(),invocation='e'*32,cgroup=group)
        ledger.state['services'].append(row)
        host.unit=lambda name:dict(MainPID=str(os.getpid()),InvocationID='e'*32,ControlGroup=group,
            FragmentPath='/run/systemd/system/'+unit,DropInPaths='')
        backend=Native.__new__(Native);backend.host=host;backend.ledger=ledger
        Native.admit(backend);self.assertNotIn('component_request',ledger.state['observations'])
        row['invocation']='f'*32
        with self.assertRaisesRegex(ValueError,'component_commission_admission'):Native.admit(backend)
    def test_supervisor_identity_faults_reach_boundary_without_component_effects(self):
        host,ledger,plan,_=self.dispatched();saved=copy.deepcopy(ledger.state)
        faults=[('cgroup','component_supervisor_cgroup'),('MainPID','component_supervisor_identity'),
            ('FragmentPath','component_unit_identity'),('ControlGroup','component_supervisor_identity'),
            ('InvocationID','component_supervisor_identity'),('environment','component_supervisor_identity'),
            ('admission','component_service_admission'),('state','component_service_binding'),
            ('command','component_dispatch_command')]
        for fault,code in faults:
            with self.subTest(fault=fault):
                ledger=Ledger.load(host.fs,ledger.config)
                ledger.state=copy.deepcopy(saved);host.unit_fault={};host.context=dict(pid=os.getpid(),cgroup=['0::/system.slice/'+e.UNIT],invocation='d'*32)
                if fault=='cgroup':host.context['cgroup']=['0::/system.slice/wrong.service']
                elif fault=='environment':host.context['invocation']='e'*32
                elif fault=='admission':ledger.state['services']=[r for r in ledger.state['services'] if r['unit']!=e.UNIT]
                elif fault=='state':next(r for r in ledger.state['services'] if r['unit']==e.UNIT)['state']='STARTING'
                elif fault=='command':next(r for r in ledger.state['commands'] if r['id']=='component-a-dispatch')['exit']=1
                else:host.unit_fault[fault]={'MainPID':'999999','FragmentPath':'/tmp/foreign.service','ControlGroup':'/user.slice','InvocationID':'e'*32}[fault]
                ledger.save()
                with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex(ValueError,code):
                    e.execute_owned(host,ledger,plan,lambda *_:self.fail('backend reached'))
                self.assertNotIn('component_a',Ledger.load(host.fs,ledger.config).state['observations'])
    def test_source_handoff_expiry_watchdog_and_conflicting_lifecycle(self):
        for fault,code in [('source','source'),('unknown-source','source'),('expiry','base_expired_or_unarmed'),
            ('watchdog','commission_cleanup_reserve'),('missing-watchdog','commission_watchdog_identity'),
            ('handoff','handoff_bytes'),('missing-handoff',''),('conflict','component_lifecycle_conflict')]:
            with self.subTest(fault=fault):
                host,ledger,plan=self.ready()
                if fault in ('source','unknown-source'):host.expected_namespace['source_sha']='f'*40;host.namespace=lambda _:None
                elif fault=='expiry':plan['config']['expires_at']=int(time.time())-1;ledger.config=plan['config'];ledger.state['admission']=ledger.config
                elif fault=='watchdog':next(r for r in ledger.state['commands'] if r['id']=='unit-start-ubo-m6e-watchdog')['started_ns']-=int(1000e9)
                elif fault=='missing-watchdog':ledger.state['commands']=[r for r in ledger.state['commands'] if r['id']!='unit-start-ubo-m6e-watchdog']
                elif fault=='handoff':host.fs.path(b.CONTROL+'/bootstrap-handoff.json').write_bytes(b'{}')
                elif fault=='missing-handoff':host.fs.path(b.CONTROL+'/bootstrap-handoff.json').unlink()
                else:ledger.state['observations']['commission']={}
                ledger.save()
                with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex((ValueError,FileNotFoundError),code):e.dispatch(host,ledger,plan)
                self.assertFalse(any(args[:3]==['/usr/bin/systemctl','start','--no-block'] for args,_ in host.calls))
    def test_repeat_and_lifecycle_lock_reject(self):
        host,ledger,plan,_=self.dispatched()
        with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex(ValueError,'component_already_dispatched'):e.dispatch(host,ledger,plan)
        host.locked=True
        with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex(ValueError,'modeled_lifecycle_conflict'):e.execute_owned(host,ledger,plan)
    def test_watchdog_stops_and_accounts_component_service(self):
        host,ledger,plan,_=self.dispatched()
        stopped,commands,failures=bootstrap.quiesce(host,ledger)
        self.assertEqual(failures,[]);self.assertIn(e.UNIT,stopped)
        self.assertTrue(any(x['id']=='unit-stop-ubo-m6e-component-a' for x in commands))
        self.assertTrue(host.units['ubo-m6e-watchdog.timer'])
    def test_watchdog_retains_nonempty_component_cgroup(self):
        host,ledger,_,_=self.dispatched()
        host.put('/sys/fs/cgroup/system.slice/'+e.UNIT+'/cgroup.procs',b'99999\n')
        stopped,_,failures=bootstrap.quiesce(host,ledger)
        self.assertNotIn(e.UNIT,stopped);self.assertTrue(any(str(error)=='component_service_children_remain' for _,error in failures))
    def backend(self,host,ledger):
        x=ServingLocal(self.temp());x.root=host.fs.root;x.fs=host.fs;x.ledger=ledger;x.host=host
        x.model=x.root/'model.json';x.plan=c.plan(ledger.config,ledger.state['observations']['fixture'])
        x.admit=lambda:e.supervisor(host,ledger)
        original=x.operational;observed=SimpleNamespace(host=host,fs=host.fs,ledger=ledger)
        x.operational=lambda:dict(local=original(),fixture=Native.operational(observed))
        publish=ledger.publish
        def capture(name,value):x.events.append(name);return publish(name,value)
        ledger.publish=capture;self.executed=x;return x
    def test_owned_entry_runs_original_lifecycle_and_isolated_evidence(self):
        host,ledger,plan,_=self.dispatched()
        historical={name:host.fs.read(ledger.root+'/'+name) for name in ('bootstrap.json','bootstrap-publication.json')}
        with mock.patch.object(e,'lifecycle_lock',modeled_lock):result=e.execute_owned(host,ledger,plan,self.backend)
        self.assertEqual(result['result'],'ORIGINAL A VERIFIED AND REMOVED',result);self.assertEqual(result['cleanup'],'REMOVED')
        self.assertEqual(result['commissioning'],'NOT EXECUTED');self.assertEqual(result['host_receipt'],'ABSENT')
        self.assertEqual(len(result['captures']),3)
        current=Ledger.load(host.fs,ledger.config)
        self.assertNotIn('commission',current.state['observations']);self.assertEqual(current.state['phase'],'component-complete')
        state=current.state['observations']['component_a'];observed=state['observations']
        self.assertEqual(observed['bootstrap_before'],observed['bootstrap_after_cleanup'])
        self.assertEqual(observed['observer']['inventory_sha256'],observed['serving_after']['inventory_sha256'])
        self.assertEqual(observed['configtest']['classification'],'VALIDATED')
        self.assertLess(self.executed.events.index('configtest'),self.executed.events.index('reader-start'))
        self.assertLess(self.executed.events.index('component-a-before-cleanup'),self.executed.events.index('stop'))
        self.assertEqual({name:host.fs.read(ledger.root+'/'+name) for name in historical},historical)
        for receipt in result['captures'].values():self.assertEqual(digest(host.fs.read(ledger.root+'/'+receipt['file'])),receipt['sha256'])
        with mock.patch.object(e,'lifecycle_lock',modeled_lock),self.assertRaisesRegex(ValueError,'component_request_admission|component_already_executed'):
            e.execute_owned(host,current,plan,self.backend)
    def test_original_operational_mutation_cannot_report_success_or_restore_it(self):
        host,ledger,plan,_=self.dispatched();injected=[]
        path=b.template_values(ledger.state['observations']['fixture']['site_key'])['EXACT_PRIVATE_JOURNAL_ROOT']+'/state.json'
        original=host.fs.read(path)
        def backend(host,ledger):
            x=self.backend(host,ledger);cleanup=x.cleanup
            def changed(owned):
                cleanup(owned);host.fs.path(path).write_bytes(canonical(b.PRISTINE|dict(fence=99)));injected.append(True)
            x.cleanup=changed;return x
        with mock.patch.object(e,'lifecycle_lock',modeled_lock):result=e.execute_owned(host,ledger,plan,backend)
        self.assertEqual(injected,[True]);self.assertEqual(result['result'],'COMPONENT INCOMPLETE')
        self.assertFalse(result['operational_state_preserved']);self.assertEqual(result['cleanup'],'LIMITED')
        self.assertNotEqual(host.fs.read(path),original);self.assertEqual(decode(host.fs.read(path))['fence'],99)
        self.assertEqual(result['host_receipt'],'NOT VERIFIED')
    def test_failed_configtest_lifecycle_captures_and_removes_only_owned_resources(self):
        host,ledger,plan,_=self.dispatched()
        def backend(host,ledger):
            x=self.backend(host,ledger)
            def rejected(note):
                x.host=NativeHost(x.fs);x.host.ledger=ledger;x.host.check_pins=lambda _:None
                x.host.execute=lambda args,seconds,**kwargs:x.host.owner.capture(
                    [sys.executable,'-I','-B','-c','import sys;sys.stderr.write("PRIVATE_FAILURE");sys.exit(1)'],seconds)
                return Native.configtest(x,note)
            x.configtest=rejected;return x
        with mock.patch.object(e,'lifecycle_lock',modeled_lock):result=e.execute_owned(host,ledger,plan,backend)
        self.assertEqual(result['result'],'COMPONENT INCOMPLETE');self.assertEqual(result['cleanup'],'REMOVED')
        self.assertTrue(result['operational_state_preserved']);self.assertNotIn('reader-start',self.executed.events)
        before=decode(host.fs.read(ledger.root+'/component-a-before-cleanup.json'))
        self.assertEqual(before['observations']['configtest']['classification'],'REJECTED')
        self.assertEqual(before['observations']['configtest']['stderr_sha256'],digest(b'PRIVATE_FAILURE'))
        self.assertNotIn(b'PRIVATE_FAILURE',canonical(before));self.assertEqual(before['held']['journal']['fence'],1)
    def configured(self):
        x=Local(self.temp());x.state=dict(resources=[],observations={});x.ledger.state['observations']['component_a']=x.state
        x.host=NativeHost(x.fs);x.host.ledger=x.ledger;x.host.check_pins=lambda _:None
        x.calls=[]
        def execute(args,seconds,**kwargs):
            x.calls.append(args)
            # Verify durable intent at the actual process boundary.
            disk=Ledger.load(x.fs,x.ledger.config)
            need(disk.state['commands'][-1]['id']=='component-a-configtest' and disk.state['commands'][-1]['pid'] is None,'test_command_intent')
            return x.host.owner.capture([sys.executable,'-I','-B','-c','import sys;print("MODEL");sys.stderr.write("PRIVATE_DIAGNOSTIC")'],seconds)
        x.host.execute=execute;Native.serving_prepare(x)
        self.addCleanup(x.host.owner.cleanup)
        return x
    def note(self,x):return lambda k,v:x.state['observations'].__setitem__(k,copy.deepcopy(v))
    def test_configtest_fixed_order_hashes_and_exact_identity(self):
        x=self.configured();owner_deadline=x.host.owner.deadline
        Native.configtest(x,self.note(x));Native.reader_admission(x)
        self.assertEqual(x.host.owner.deadline,owner_deadline)
        self.assertEqual(x.calls,[['/usr/sbin/apache2','-t','-f',serving_root(x.plan)+'/apache.conf']])
        evidence=x.state['observations']['configtest']
        self.assertEqual(evidence['classification'],'VALIDATED');self.assertEqual(evidence['execution'],'modeled')
        self.assertEqual(evidence['stderr_sha256'],digest(b'PRIVATE_DIAGNOSTIC'))
        self.assertNotIn(b'PRIVATE_DIAGNOSTIC',canonical(evidence));self.assertTrue(evidence['children'][0]['reaped'])
        self.assertEqual(Native.serving_owned(x),x.serving_prepared)
    def test_missing_malformed_and_replaced_config_reject_before_command(self):
        for fault in ('missing','malformed','replaced'):
            with self.subTest(fault=fault):
                x=self.configured();path=x.fs.path(serving_root(x.plan)+'/apache.conf')
                if fault=='missing':path.unlink()
                elif fault=='malformed':path.write_bytes(b'invalid apache')
                else:raw=path.read_bytes();path.rename(x.root/'held-config');path.write_bytes(raw)
                with self.assertRaises((ValueError,FileNotFoundError)):Native.configtest(x,self.note(x))
                self.assertEqual(x.calls,[])
                with self.assertRaisesRegex(ValueError,'component_configtest_required'):Native.reader_admission(x)
    def test_failed_timeout_unknown_and_output_bound_configtest_block_reader(self):
        cases={'failure':('import sys;print("private");sys.exit(1)','component_configtest_failed'),
            'timeout':('import time;time.sleep(5)','child_timeout'),
            'unknown':('', 'base_command_failed'),
            'output':('import sys;sys.stderr.write("x"*17000)','child_output_bound')}
        for fault,(script,code) in cases.items():
            with self.subTest(fault=fault):
                x=self.configured()
                owner_deadline=x.host.owner.deadline
                if fault=='unknown':x.host.execute=lambda *a,**kw:dict(pid=123,exit=None,stdout=b'',stderr=b'')
                else:x.host.execute=lambda args,seconds,script=script,**kw:x.host.owner.capture([sys.executable,'-I','-B','-c',script],.1 if fault=='timeout' else seconds,error_limit=16384)
                with self.assertRaisesRegex((ValueError,Failure),code):Native.configtest(x,self.note(x))
                self.assertEqual(x.host.owner.deadline,owner_deadline)
                with mock.patch('WebsitePlatformM6EComponentHost.subprocess.Popen') as spawn,self.assertRaisesRegex(ValueError,'component_configtest_required'):Native.reader_start(x)
                spawn.assert_not_called();self.assertIsNone(x.reader)
                self.assertEqual(x.state['observations']['configtest']['classification'],'REJECTED')
                if fault=='unknown':
                    with self.assertRaisesRegex(ValueError,'component_configtest_not_quiescent'):Native.cleanup_serving(x)
                    self.assertTrue(x.fs.exists(serving_root(x.plan)))
    def test_changed_config_or_command_after_validation_blocks_spawn(self):
        for fault in ('bytes','inode','missing-command','changed-command','deadline'):
            with self.subTest(fault=fault):
                x=self.configured();Native.configtest(x,self.note(x));path=x.fs.path(serving_root(x.plan)+'/apache.conf')
                if fault=='bytes':path.write_bytes(b'changed')
                elif fault=='inode':raw=path.read_bytes();path.rename(x.root/'held-config');path.write_bytes(raw)
                elif fault=='missing-command':x.ledger.state['commands'].clear()
                elif fault=='changed-command':x.ledger.state['commands'][0]['pid']+=1
                else:x.deadline=time.monotonic()-1
                with mock.patch('WebsitePlatformM6EComponentHost.subprocess.Popen') as spawn,self.assertRaises(ValueError):Native.reader_start(x)
                spawn.assert_not_called()
    def test_failed_configtest_cleanup_requires_original_identity_and_quiescence(self):
        for fault in ('normal','changed','uncertain'):
            with self.subTest(fault=fault):
                x=self.configured();Native.configtest(x,self.note(x))
                if fault=='changed':x.fs.path(serving_root(x.plan)+'/apache.conf').write_bytes(b'changed')
                if fault=='uncertain':x.configtest_children[0]['reaped']=False
                if fault=='normal':Native.cleanup_serving(x);self.assertFalse(x.fs.exists(serving_root(x.plan)))
                else:
                    with self.assertRaises(ValueError):Native.cleanup_serving(x)
                    self.assertTrue(x.fs.exists(serving_root(x.plan)))

if __name__=='__main__':unittest.main(verbosity=2)
