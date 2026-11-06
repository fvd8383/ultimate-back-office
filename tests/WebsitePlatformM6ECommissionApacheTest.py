"""Real original M6C/transfer/component/collector controls; Apache/TLS/Unix modeled."""
import copy
import os
from pathlib import Path
import sys
import tempfile
import time
import unittest
from types import SimpleNamespace
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
sys.path[:0]=[str(ROOT/'tests'),str(ROOT/'tests/support')]
from WebsitePlatformM6EComponentTest import ServingLocal
from WebsitePlatformM6ECommissionControlTest import binding
from WebsitePlatformM6EAcceptance import canonical,decode,digest,ROLES
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6ECommissionProbes import NativeProbes
from WebsitePlatformM6EComponentHost import Native,serving_root
import WebsitePlatformM6EComponent as component
import WebsitePlatformM6ECommission as commission
import WebsitePlatformM6ECommissionApache as apache
import WebsitePlatformM6ECommissionEvidence as evidence
import WebsitePlatformM6EBootstrapPlan as plan


class LocalApache(ServingLocal):
    """Bootstrap the original fixture once, then re-observe it through the worker."""
    def __init__(self,root,fault=''):
        super().__init__(root,fault,apache=True)
        self.bootstrapping=True;self.state=dict(resources=[],children=[],observations={})
        self.worker=super().start('worker')
        self.bundle=self.receive(self.worker,'bootstrap');self.bootstrapping=False
        self.plan=component.plan(self.ledger.config,self.bundle['fixture'],apache=True)
        self.ledger.state['observations'].update(fixture=self.bundle['fixture'],database={})
    def start_value(self,role):
        if role=='worker' and self.bootstrapping:return dict(command='bootstrap')
        return super().start_value(role)
    def start(self,role):
        if role=='worker':
            self.worker.send(self.start_value(role));return self.worker
        return super().start(role)
    def configtest(self,note):
        if self.fault=='configtest':
            original=self.configuration_identity
            self.configuration_identity=lambda:(_ for _ in ()).throw(Failure('configtest_injected'))
            try:return super().configtest(note)
            finally:self.configuration_identity=original
        return super().configtest(note)


class ProbeModel(NativeProbes):
    execution='controlled-local'
    def __init__(self,backend,expected):
        self.backend=backend;self.host=backend.host;self.host.fs=backend.fs;self.ledger=backend.ledger
        self.fixture=backend.bundle['fixture'];self.binding=expected;self.partial={};self.participants=[]
        self.worker={'bindings':[{}]};self.plan={'config':self.ledger.config};self.history={}
        self.ledger.state['observations']['commission']=dict(resources=[],binding=expected)
        self.ledger.state['watchdog']='ARMED'
        self.ledger.state['commands'].append(dict(id='unit-start-ubo-m6e-watchdog',started_ns=time.monotonic_ns()))
        self.host.owner.deadline=time.monotonic()+400
        self.host.run=self.module_command
        self.host.setup=self.inspect
    def roots(self):return self.binding['roots_sha256'] # Explicit original Unix root model.
    def module_command(self,*args,**kwargs):
        raw=('\n'.join(name+'_module (shared)' for name in (*plan.MODULES,'core','so','http'))+'\n').encode()
        result=self.host.owner.capture([sys.executable,'-I','-B','-c','import sys;sys.stdout.buffer.write(sys.stdin.buffer.read())'],5,stdin=raw)
        self.ledger.state['commands'].append(dict(id='model-apache-modules',pid=result['pid'],exit=result['exit']))
        return result
    def inspect(self,*args):
        result=self.host.owner.capture([sys.executable,'-I','-B','-c','print("modeled read-only inspection")'],5)
        self.ledger.state['commands'].append(dict(id='model-inspection',pid=result['pid'],exit=result['exit']))
        return dict(fixture=self.fixture,database={})
    def child(self,role,mode,php=False,stdin=b''):
        if mode!='--serving-source':raise AssertionError('unexpected modeled child')
        return dict(sources=copy.deepcopy(self.backend.bundle['sources']))
    def probe(self,gate):
        with mock.patch.object(os,'geteuid',return_value=0,create=True):return super().probe(gate)


class ApacheTests(unittest.TestCase):
    def execute(self,fault='',inspect=None,change=None):
        root='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-apache-',dir=root) as directory:
            local=LocalApache(directory,fault);expected=binding();backend=ProbeModel(local,expected)
            self.addCleanup(backend.host.owner.cleanup)
            original_publish=local.ledger.publish;events=[];captures=[]
            def publish(stem,value):
                events.append(stem)
                if stem=='commission-apache-before-restore':
                    captures.append(copy.deepcopy(value))
                    reader=getattr(local,'reader',None)
                    if reader is not None and local.state['first_failure'] is None:
                        self.assertIsNone(reader['process'].poll())
                        self.assertTrue(local.fs.exists(local.plan['paths']['served']+'/candidate'))
                    if fault=='gate-capture':raise Failure('gate_capture_injected')
                if stem==apache.NAME and fault=='archive-capture':raise Failure('archive_capture_injected')
                return original_publish(stem,value)
            local.ledger.publish=publish
            if change:change(local,backend)
            result=None;error=None
            baseline=lambda binding,mode,path,**kw:dict(status=0 if kw.get('variant')=='unauthorized' else 503)
            try:
                with mock.patch.object(commission,'GATES',('apache',)),mock.patch('WebsitePlatformM6ECommissionProbes.fetch',side_effect=baseline),\
                     mock.patch('WebsitePlatformM6EComponentHost.Native',return_value=local) as factory:
                    try:result=commission.collect(backend.host,local.ledger,backend,expected)
                    except BaseException as caught:error=caught
                    self.assertEqual(factory.call_count,1)
                    self.assertTrue(factory.call_args.kwargs['apache'])
                if inspect:inspect(local,backend,expected,result,error,events,captures)
                self.assertTrue(all(x['process'].poll() is not None for x in local.active))
                self.assertFalse(local.fs.exists(plan.CONFIG+'/host-receipt.json'))
                self.assertNotIn('component_a',local.ledger.state['observations'])
                self.assertEqual(local.operational(),local.before)
                return error
            finally:
                # Test-private teardown only, after assertions. Never a production
                # cleanup fallback; uncertain native resources remain LIMITED.
                if not getattr(getattr(backend,'apache_component',None),'component',SimpleNamespace(finished=True)).finished:
                    try:backend.apache_component.finish()
                    except BaseException:pass
                for item in local.active:
                    process=item['process']
                    if process.poll() is None:process.kill();process.wait(timeout=5)
                backend.host.owner.cleanup()

    def test_original_apache_component_is_captured_alive_then_removed(self):
        def check(local,backend,expected,result,error,events,captures):
            self.assertIsNone(error)
            read=lambda name:local.fs.read(local.ledger.root+'/'+name)
            raw=evidence.published_record(read,expected,'apache',result['apache'],int(time.time()),native=False,complete=False)
            gate=decode(raw);self.assertEqual(gate['result'],'INCOMPLETE')
            self.assertEqual(gate['missing'],['retained-release-static-responses'])
            details=apache.validate_final(read,expected,gate['observations']['data']['apache_sha256'],
                gate['observations']['data']['source_sha256'],'controlled-local')
            measured=details['measurements'];self.assertEqual(measured['http']['requests'],55)
            self.assertEqual(measured['http']['verified_files'],7);self.assertEqual(measured['http']['denials'],40)
            self.assertEqual(measured['source']['producer_attempt_id'],local.bundle['fixture']['releases'][0]['producer_attempt_id'])
            self.assertEqual(measured['source']['source_sha256'],digest(canonical(local.bundle['sources'][0])))
            self.assertLess(events.index('component-apache-a-before-cleanup'),events.index(apache.NAME))
            self.assertLess(events.index(apache.NAME),events.index('commission-apache-before-restore'))
            self.assertLess(events.index('commission-apache-before-restore'),events.index('component-apache-a-owned-before-remove'))
            self.assertLess(events.index('component-apache-a-result'),events.index('commission-apache'))
            self.assertEqual(local.captured['held']['journal']['fence'],1)
            self.assertFalse(local.captured['held']['journal']['settled'])
            self.assertFalse(local.fs.exists(serving_root(local.plan)))
            self.assertNotEqual(component.paths(local.ledger.config['run_id']),local.plan['paths'])
            self.assertEqual(sum(bool(v) for v in evidence.MISSING.values()),6)
            with self.assertRaises(ValueError):evidence.validate_record(raw,expected,'apache',int(time.time()),native=False)
            with self.assertRaises(ValueError):evidence.published_record(read,expected,'apache',result['apache'],int(time.time()),native=True,complete=False)
        self.execute(inspect=check)

    def test_source_binding_failure_precedes_helper_effects(self):
        def change(local,backend):
            original=local.source
            def source(worker):
                value=original(worker);value['summary']['producer_attempt_id']+=1;return value
            local.source=source
        def check(local,backend,expected,result,error,events,captures):
            self.assertEqual(str(error),'apache_original_source_binding')
            self.assertFalse(any(x['role']=='helper' for x in local.active))
            self.assertNotIn('commission-apache',events);self.assertEqual(captures[0]['observations']['progress']['apache-component'],'FAILED')
        self.execute(change=change,inspect=check)

    def test_response_and_access_failures_cannot_publish_gate(self):
        for fault in ('wrong-marker','wrong-mime','wrong-digest','security-header','handler-bypass','auth-bypass'):
            with self.subTest(fault=fault):
                def check(local,backend,expected,result,error,events,captures):
                    self.assertIsNotNone(error);self.assertNotIn('commission-apache',events)
                    state=local.ledger.state['observations']['component_apache_a']
                    self.assertEqual(state['result'],'INCOMPLETE');self.assertEqual(state['cleanup'],'REMOVED')
                    self.assertGreater(state['observations']['serving']['requests'],0)
                self.execute(fault,inspect=check)

    def test_changed_files_and_resources_remain_limited(self):
        for fault in ('candidate-absent','serve-same-inode-substitution','serving-missing','serving-cleanup-replaced'):
            with self.subTest(fault=fault):
                def check(local,backend,expected,result,error,events,captures):
                    self.assertIsNotNone(error);self.assertNotIn('commission-apache',events)
                    self.assertEqual(local.ledger.state['observations']['component_apache_a']['cleanup'],'LIMITED')
                    self.assertTrue(local.fs.exists(local.plan['paths']['journal']+'/state.json'))
                self.execute(fault,inspect=check)

    def test_reader_capture_and_cleanup_failures_never_complete(self):
        for fault in ('reader-death','reader-unknown','capture-failure','archive-capture','gate-capture','cleanup-failure','configtest'):
            with self.subTest(fault=fault):
                def check(local,backend,expected,result,error,events,captures):
                    self.assertIsNotNone(error);self.assertNotIn('commission-apache',events)
                    if fault=='configtest':self.assertNotIn('reader-start',local.events)
                    if fault in ('reader-death','reader-unknown','capture-failure','cleanup-failure'):
                        self.assertEqual(local.ledger.state['observations']['component_apache_a']['cleanup'],'LIMITED')
                self.execute(fault,inspect=check)

    def test_original_environment_change_and_first_failure_are_preserved(self):
        def change(local,backend):
            original=local.serving_checks
            def serving(note):
                original(note);(local.original/'database').write_bytes(b'changed')
            local.serving_checks=serving
        def check(local,backend,expected,result,error,events,captures):
            self.assertEqual(str(error),'component_operational_changed');self.assertNotIn('commission-apache',events)
            self.assertEqual(local.ledger.state['observations']['component_apache_a']['cleanup'],'LIMITED')
            self.assertEqual((local.original/'database').read_bytes(),b'changed')
            local.before=local.operational() # Keep this deliberate test-private mutation for outer inspection.
        self.execute(change=change,inspect=check)

    def test_capture_tampering_and_cleanup_uncertainty_reject_offline(self):
        def check(local,backend,expected,result,error,events,captures):
            self.assertIsNone(error)
            read=lambda name:local.fs.read(local.ledger.root+'/'+name)
            for name in (apache.NAME+'.json','component-apache-a-before-cleanup.json','component-apache-a-result.json'):
                with self.subTest(name=name),self.assertRaises((ValueError,KeyError)):
                    evidence.published_record(lambda path:b'{}' if path==name else read(path),expected,'apache',result['apache'],
                        int(time.time()),native=False,complete=False)
        self.execute(inspect=check)

    def test_fixed_native_names_and_stage_only_authority(self):
        from WebsitePlatformM6EBootstrapControlTest import config,fixture
        cfg=config();original=component.plan(cfg,fixture());owned=component.plan(cfg,fixture(),apache=True)
        self.assertEqual(component.validate_plan(owned,cfg,fixture()),owned)
        self.assertEqual(component.Authority(owned).observe(owned['request'])['permitted'],['stage'])
        self.assertNotEqual(owned['request']['lease']['token'],original['request']['lease']['token'])
        native=Native.__new__(Native);native.plan=owned
        self.assertEqual(native.command('worker')[-1],'--component-apache-a')
        self.assertEqual(native.command('observer')[-1],'--apache-observer')
        self.assertEqual(native.command('http')[-1],'--apache-http')
        for key in ('paths','owner','request'):
            changed=copy.deepcopy(owned);changed[key]=original.get(key)
            with self.subTest(key=key),self.assertRaises((ValueError,TypeError)):component.validate_plan(changed,cfg,fixture())
        self.assertEqual(tuple(evidence.MISSING),('apache','filesystem','ownership','assets','runtime','health','recovery','provisioning'))


if __name__=='__main__':unittest.main()
