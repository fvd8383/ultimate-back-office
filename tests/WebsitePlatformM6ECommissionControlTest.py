"""Controlled-local commissioning tests. No native infrastructure or operational receipt.

Real private files, subprocess capture, publication and validators; Unix identities,
tool/VM/service observations and probe results are explicitly modeled. Six mandatory
native producers remain incomplete, so no positive native issuance test is claimed.
"""
import copy
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import stat
import sys
import tempfile
import time
import types
import unittest
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent))
sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
from WebsitePlatformM6EBootstrapControlTest import PrivateFiles, ModelHost, config, fixture
from WebsitePlatformM6EAcceptance import GATES, ROLES, canonical, decode, digest
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EProcess import Owner, Failure
from WebsitePlatformM6ECoordinator import require_native_ready
import WebsitePlatformM6EBootstrap as bootstrap
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECommission as c
import WebsitePlatformM6ECommissionEvidence as e
import WebsitePlatformM6ECommissionReceipt as r
from WebsitePlatformM6ECommissionProbes import NativeProbes
import WebsitePlatformM6ECommissionProbes as probes

ROOT=Path(__file__).resolve().parents[1]
PHP=shutil.which('php')

def load_helper():
    spec=importlib.util.spec_from_file_location('controlled_admission_helper',ROOT/'infrastructure/deployment/ubo_site_publish.py')
    helper=importlib.util.module_from_spec(spec);spec.loader.exec_module(helper);return helper

class MappedFiles(PrivateFiles):
    def path(self,path):return self.root if str(path)=='/' else super().path(path)

def binding():
    value={name:'a'*64 for name in ('bootstrap_sha256','handoff_sha256','configuration_sha256','source_inventory_sha256',
        'tools_sha256','modules_sha256','roots_sha256','installed_sha256')}
    return value|p.base_identity(config())|dict(roles_sha256=digest(canonical(ROLES)),expires_at=int(time.time())+600)

def data(gate):
    # Synthetic observations are solely for controlled parsing/schema tests.
    value={name:('a'*64 if name.endswith('sha256') else 'REJECTED' if name=='result' else 0) for name in e.FIELDS[gate]}
    if gate in ('apache','assets'):value.update(source_releases=2,source_files=14,source_assets=6,source_bytes=12345)
    if gate=='apache':value.update(active_status=503,candidate_status=503)
    if gate=='assets':value.update(release_count=2,asset_count=6,foreign_site_id=11)
    if gate=='filesystem':value.update(device=1,journal_device=2,conflict_rejected=1,link_rejected=1,replacement_rejected=1,
        reader_pid=os.getpid(),old_observations=4,new_observations=7,sync_calls=8,
        read_sha256=digest(b'M6E nonsecret commissioning publication canary\n'),replay_sha256=digest(b'M6E nonsecret commissioning publication canary\n'))
    if gate=='ownership':value.update(worker_source_errno=0,helper_source_errno=13,apache_source_errno=13,worker_served_errno=13,
        worker_journal_errno=13,apache_public_errno=0,apache_write_errno=13,source_mode=0o600,source_parent_mode=0o700,public_mode=0o440,
        public_sha256=digest(b'M6E public access canary\n'))
    if gate=='runtime':value.update(native_prepare_value=5,duplicate_parameter_rejected=1,utc_equal=1,select_rows=1,update_errno=1142,foreign_errno=1044)
    if gate=='provisioning':value.update(candidate_exit=1)
    return value

class ModelBackend(NativeProbes):
    execution='controlled-local'
    def __init__(self,host,ledger,expected):
        self.host,self.ledger,self.binding=host,ledger,expected;self.partial={};self.participants=[]
        self.fixture=ledger.state['observations'].setdefault('fixture',{})
        database=ledger.state['observations'].setdefault('database',{})
        self.events=[];self.fail_gate=None;self.fail_cleanup=False;self.values={}
        ledger.state['observations']['commission']=dict(resources=[],binding=expected)
        def setup(mode,role):
            value=self.host.owner.capture([sys.executable,'-I','-B','-c','print("{}")'],3)
            self.ledger.state['commands'].append(dict(id='modeled-inspection',pid=value['pid'],exit=value['exit']))
            return dict(fixture=self.fixture,database=database)
        host.setup=setup
        for gate in GATES:setattr(self,'probe_'+gate,lambda gate=gate:self.measure(gate))
    def stage_seconds(self,gate):return 1
    def roots(self):return self.binding['roots_sha256']
    def probe(self,gate):
        with mock.patch.object(os,'geteuid',return_value=0,create=True):return super().probe(gate)
    def measure(self,gate):
        self.events.append(('probe',gate))
        if self.fail_gate==gate:raise OSError('SECRET_PRIMARY_CANARY')
        # Actual bounded child parsing, using explicitly synthetic data, never a
        # native producer executable or native mode.
        out=self.host.owner.capture([sys.executable,'-I','-B','-c','import sys;sys.stdout.buffer.write(sys.stdin.buffer.read())'],3,stdin=canonical(self.values.get(gate,data(gate))))
        self.participants.append(dict(role='operator',uid=0,pid=out['pid'],exit=out['exit']))
        return decode(out['stdout'])
    def cleanup(self,gate):
        self.events.append(('cleanup',gate))
        path=self.ledger.root+'/commission-'+gate+'-before-restore.json'
        if not self.host.fs.exists(path):raise AssertionError('missing_before_restore')
        if self.fail_cleanup:raise OSError('SECRET_CLEANUP_CANARY')
        return dict(remaining=0,state_sha256=self.roots())

class Controls(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(prefix='m6e-commission-controlled-');self.addCleanup(self.tmp.cleanup)
        self.files=MappedFiles(self.tmp.name)
        for name in ('/var/lib','/etc/ubo-site-publisher'):self.files.makedirs(name)
        self.ledger=Ledger.create(self.files,config());self.ledger.state['watchdog']='ARMED'
        self.ledger.state['commands']=[dict(id='unit-start-ubo-m6e-watchdog',started_ns=time.monotonic_ns())]
        self.host=types.SimpleNamespace(fs=self.files,owner=Owner(time.monotonic()+500))
        self.addCleanup(self.host.owner.cleanup);self.expected=binding()
    def collector(self):
        backend=ModelBackend(self.host,self.ledger,self.expected)
        receipts=c.collect(self.host,self.ledger,backend,self.expected)
        return backend,receipts
    def row(self,gate='filesystem'):
        observed=dict(pid=os.getpid(),uid=0,data=data(gate),participants=[dict(role='helper',uid=2102,pid=os.getpid(),exit=0)],
            independent=dict(pid=os.getpid(),state_sha256=self.expected['roots_sha256'],roles_sha256=self.expected['roles_sha256']))
        before=dict(profile=e.PROFILE+'-before-restore',binding=self.expected,gate=gate,execution='controlled-local',observations=observed,first_failure=None)
        capture=self.ledger.publish('commission-'+gate+'-before-restore',before)
        return dict(profile=e.PROFILE,version=1,gate=gate,producer=e.PRODUCERS[gate],execution='controlled-local',binding=self.expected,
            started_at=int(time.time()),finished_at=int(time.time()),observations=observed,observations_sha256=digest(canonical(observed)),
            capture=capture,cleanup=dict(remaining=0,state_sha256=self.expected['roots_sha256']),
            result='INCOMPLETE' if e.MISSING[gate] else 'MEASURED',missing=list(e.MISSING[gate]))
    def inventory(self):
        loaded={}
        for role,prefix,main in (('worker','/opt/ubo-m6e/private/classes/','SiteDeploymentService.php'),
                               ('authority','/usr/local/lib/ubo-site-publisher/php/','SitePublisherAuthority.php')):
            loaded[role]=[prefix+name for name in ('SitePublisherHostContract.php','SiteDeploymentContract.php',main)]
        loaded['worker'].append('/opt/ubo-m6e/tests/support/WebsitePlatformM6ECommissionChild.php')
        for name in set(r.REQUIRED)|set(loaded['worker'])|set(loaded['authority']):
            self.files.makedirs(str(Path(name).parent).replace('\\','/'))
            self.files.create(name,('controlled dependency '+name).encode(),lambda fd:None)
            self.files.metadata(name,0,0,0o644)
        return loaded,r.installed_inventory(self.files,loaded)
    def candidate(self):
        loaded,installed=self.inventory()
        cfgs=p.configurations(fixture(),dict(schema='ubo_m6e_'+'b'*32,credentials_sha256='b'*64),'c'*64)
        worker=canonical(cfgs['worker.json'])
        for name in ('worker.json','helper.json'):
            path=p.CONFIG+'/'+name
            self.files.path(path).write_bytes(canonical(cfgs[name]))
        installed=r.installed_inventory(self.files,loaded)
        now=int(time.time());gates={gate:dict(result='pass',evidence_sha256='a'*64) for gate in GATES}
        result=e.format_receipt(worker,installed,gates,now,self.expected['expires_at'])
        return cfgs,result,loaded
    def publish(self,raw,ack=lambda raw:None,rename=r.rename_new):
        return r.publish(self.files,self.ledger,raw,self.expected,ack,rename)

    def test_ordered_actual_dispatch_parsing_and_capture_before_cleanup(self):
        backend,receipts=self.collector()
        self.assertEqual(list(receipts),list(GATES))
        self.assertEqual(backend.events,[(step,gate) for gate in GATES for step in ('probe','cleanup')])
        for gate in GATES:
            raw=self.files.read(self.ledger.root+'/'+receipts[gate]['file'])
            e.validate_record(raw,self.expected,gate,int(time.time()),native=False,complete=False)
            self.assertEqual(decode(raw)['execution'],'controlled-local')
        self.assertFalse(self.files.exists(r.RECEIPT))

    def test_controlled_evidence_never_authorizes_native_issuance(self):
        row=self.row()
        with self.assertRaisesRegex(ValueError,'modeled_commissioning'):e.validate_record(canonical(row),self.expected,'filesystem',int(time.time()))

    def test_incomplete_native_gate_cannot_be_promoted(self):
        for gate in GATES:
            if not e.MISSING[gate]:continue
            with self.subTest(gate=gate):
                row=self.row(gate);row['execution']='native-components'
                with self.assertRaisesRegex(ValueError,'native_producer_incomplete'):e.validate_record(canonical(row),self.expected,gate,int(time.time()))
                row.update(result='MEASURED',missing=[])
                with self.assertRaisesRegex(ValueError,'producer_completeness'):e.validate_record(canonical(row),self.expected,gate,int(time.time()))

    def test_missing_failed_partial_constant_pass_renamed_wrong_hash(self):
        row=self.row()
        for change in ('constant','gate','hash','producer','missing-data','failed','cleanup','boolean-version','boolean-exit'):
            bad=copy.deepcopy(row)
            if change=='constant':bad={'result':'pass','evidence_sha256':'a'*64}
            elif change=='gate':bad['gate']='assets'
            elif change=='hash':bad['observations_sha256']='0'*64
            elif change=='producer':bad['producer']='commission-ownership-v1'
            elif change=='missing-data':del bad['observations']['data']['reader_exit']
            elif change=='failed':bad['result']='FAILED'
            elif change=='cleanup':bad['cleanup']['remaining']=1
            elif change=='boolean-version':bad['version']=True
            else:
                bad['observations']['participants'][0]['exit']=False
                bad['observations_sha256']=digest(canonical(bad['observations']))
            with self.subTest(change=change),self.assertRaises(ValueError):e.validate_record(canonical(bad),self.expected,'filesystem',int(time.time()),native=False)

    def test_all_binding_dimensions_reject(self):
        row=self.row()
        for key in self.expected:
            bad=copy.deepcopy(row);bad['binding'][key]=0 if key=='expires_at' else '0'*len(str(bad['binding'][key]))
            with self.subTest(key=key),self.assertRaises(ValueError):e.validate_record(canonical(bad),self.expected,'filesystem',int(time.time()),native=False)
        for key,value in (('uid',2000),('role','worker'),('exit',1)):
            bad=copy.deepcopy(row);bad['observations']['participants'][0][key]=value
            bad['observations_sha256']=digest(canonical(bad['observations']))
            with self.subTest(child=key),self.assertRaises(ValueError):e.validate_record(canonical(bad),self.expected,'filesystem',int(time.time()),native=False)

    def test_expiry_stale_and_reserve_reject(self):
        row=self.row()
        with self.assertRaises(ValueError):e.validate_record(canonical(row),self.expected,'filesystem',self.expected['expires_at'],native=False)
        row['started_at']-=601
        with self.assertRaisesRegex(ValueError,'stale'):e.validate_record(canonical(row),self.expected,'filesystem',int(time.time()),native=False)
        self.ledger.state['commands'][0]['started_ns']-=int(1100*1e9)
        with self.assertRaisesRegex(ValueError,'reserve'):c.budget(self.host,self.ledger,1)

    def test_measurements_are_not_edited_by_cleanup(self):
        backend=ModelBackend(self.host,self.ledger,self.expected);original=backend.cleanup
        def cleanup(gate):
            backend.partial.clear();return original(gate)
        backend.cleanup=cleanup;c.collect(self.host,self.ledger,backend,self.expected)
        raw=decode(self.files.read(self.ledger.root+'/commission-filesystem.json'))
        before=decode(self.files.read(self.ledger.root+'/commission-filesystem-before-restore.json'))
        self.assertEqual(before['observations'],raw['observations'])

    def test_primary_survives_cleanup_and_capture_failures(self):
        backend=ModelBackend(self.host,self.ledger,self.expected);backend.fail_gate='apache';backend.fail_cleanup=True
        with mock.patch.object(self.ledger,'publish',side_effect=OSError('SECRET_CAPTURE_CANARY')):
            with self.assertRaisesRegex(OSError,'SECRET_PRIMARY_CANARY'):c.collect(self.host,self.ledger,backend,self.expected)
        public=canonical(self.ledger.public());self.assertNotIn(b'SECRET_',public)
        self.assertIsNotNone(self.ledger.state['first_failure'])
        self.assertEqual(self.ledger.state['first_failure']['kind'],'commission-probe')

    def test_failure_captures_partial_before_cleanup(self):
        backend=ModelBackend(self.host,self.ledger,self.expected);backend.fail_gate='apache'
        with self.assertRaises(OSError):c.collect(self.host,self.ledger,backend,self.expected)
        value=decode(self.files.read(self.ledger.root+'/commission-apache-before-restore.json'))
        self.assertEqual(value['observations']['data'],{});self.assertEqual(value['observations']['participants'],[])
        self.assertEqual(value['observations']['progress']['producer'],'FAILED');self.assertIsNotNone(value['first_failure'])
        self.assertEqual(backend.events,[('probe','apache'),('cleanup','apache')])

    def test_missing_duplicate_publication_and_bootstrap_only_reject(self):
        for value in ({},{gate:dict(file='bootstrap.json',bytes=1,sha256='a'*64) for gate in GATES}):
            with self.assertRaises(ValueError):e.finalized_evidence(lambda _:b'{}',self.expected,value,int(time.time()))
        with self.assertRaisesRegex(ValueError,'duplicate_key'):e.decode(b'{"gate":1,"gate":2}')

    def test_source_config_include_inventory_changes_reject(self):
        loaded,installed=self.inventory();r.verify_inventory(self.files,installed,loaded)
        for name in (r.REQUIRED[0],p.CONFIG+'/worker.json',loaded['worker'][0]):
            before=self.files.read(name);self.files.path(name).write_bytes(before+b'x')
            with self.subTest(name=name),self.assertRaises(ValueError):r.verify_inventory(self.files,installed,loaded)
            self.files.path(name).write_bytes(before)
        bad=dict(installed);bad.pop(r.REQUIRED[0])
        with self.assertRaises(ValueError):r.verify_inventory(self.files,bad,loaded)
        missing=copy.deepcopy(loaded);missing['worker'].remove('/opt/ubo-m6e/private/classes/SiteDeploymentService.php')
        with self.assertRaises(ValueError):r.installed_inventory(self.files,missing)

    def test_actual_component_include_graph_is_pinned_without_wildcard_support(self):
        loaded,_=self.inventory()
        # Cold actual PHP include graph, with no SQL, config or native admission.
        entry=str(ROOT/'tests/support/WebsitePlatformM6EComponent.php').replace('\\','/')
        result=self.host.owner.capture([PHP,'-d','display_errors=0','-r',
            'require '+json.dumps(entry)+'; echo json_encode(get_included_files());'],10)
        self.assertEqual(result['exit'],0);self.assertEqual(result['stderr'],b'')
        paths=['/opt/ubo-m6e/'+Path(name).resolve().relative_to(ROOT).as_posix() for name in decode(result['stdout'])]
        loaded['worker']=sorted(set(paths)|{'/opt/ubo-m6e/tests/support/WebsitePlatformM6ECommissionChild.php'})
        self.assertTrue(set(r.WORKER_SUPPORT)<=set(paths))
        for name in paths:
            if not self.files.exists(name):
                self.files.makedirs(str(PurePosixPath(name).parent))
                self.files.create(name,b'private mapped dependency',lambda fd:None);self.files.metadata(name,0,0,0o644)
        pins=r.installed_inventory(self.files,loaded)
        self.assertTrue(set(r.WORKER_SUPPORT)<=set(pins))
        for name in (*r.WORKER_SUPPORT,'/opt/ubo-m6e/tests/support/Unapproved.php'):
            if name in r.WORKER_SUPPORT:
                self.files.path(name).write_bytes(b'changed')
                with self.assertRaises(ValueError):r.verify_inventory(self.files,pins,loaded)
                self.files.path(name).write_bytes(b'private mapped dependency')
            else:
                invalid=copy.deepcopy(loaded);invalid['worker'].append(name)
                with self.assertRaisesRegex(ValueError,'included_path'):r.installed_inventory(self.files,invalid)

    def test_unsafe_modes_hardlinks_and_ancestors_reject(self):
        loaded,installed=self.inventory();name=r.REQUIRED[0]
        self.files.metadata(name,0,0,0o666)
        with self.assertRaises(ValueError):r.verify_inventory(self.files,installed,loaded)
        self.files.metadata(name,0,0,0o644)
        os.link(self.files.path(name),self.files.path(name+'.link'))
        with self.assertRaises(ValueError):r.verify_inventory(self.files,installed,loaded)
        self.files.path(name+'.link').unlink()
        self.files.metadata('/opt',2000,2000,0o755)
        with self.assertRaises(ValueError):r.verify_inventory(self.files,installed,loaded)

    def test_exact_actual_php_and_python_format_validators(self):
        cfgs,candidate,_=self.candidate();now=int(time.time())
        payload=dict(worker=cfgs['worker.json'],receipt=candidate,worker_sha256=digest(canonical(cfgs['worker.json'])),now=now)
        self.assertIsNotNone(PHP)
        result=self.host.owner.capture([PHP,'-d','display_errors=0','-d','log_errors=0',str(ROOT/'tests/support/WebsitePlatformM6ECommissionChild.php'),'--format'],10,stdin=canonical(payload))
        self.assertEqual(result['exit'],0,result['stderr']);self.assertEqual(decode(result['stdout']),{'result':'FORMAT VALID'})
        helper=load_helper()
        def private_read(name,limit):return canonical(candidate) if name==r.RECEIPT else self.files.read(name,limit)
        # Explicit modeled OS/protected-file boundaries; execute the real function's
        # complete schema/pinning/identity checks. No operational path is written.
        with mock.patch.object(helper,'protected',side_effect=private_read),mock.patch.object(helper,'sys',types.SimpleNamespace(platform='linux',argv=['helper'])),\
             mock.patch.object(helper,'os',types.SimpleNamespace(geteuid=lambda:2102,O_NOFOLLOW=0)):
            self.assertEqual(helper.load_config(),cfgs['helper.json'])
            candidate['installed_files'][p.CONFIG+'/helper.json']='0'*64
            with self.assertRaises(helper.Denied):helper.load_config()

    def rejected_checkpoint(self,backend,gate,kind=Exception):
        with self.assertRaises(kind) as failure:c.collect(self.host,self.ledger,backend,self.expected)
        raw=self.files.read(self.ledger.root+'/commission-'+gate+'-before-restore.json');value=decode(raw)
        self.assertEqual(value['first_failure'],c.safe_error(failure.exception))
        self.assertIn(('cleanup',gate),backend.events)
        self.assertFalse(self.files.exists(self.ledger.root+'/commission-'+gate+'.json'))
        self.assertFalse(self.files.exists(r.RECEIPT))
        with self.assertRaises(ValueError):e.validate_record(raw,self.expected,gate,int(time.time()),native=False)
        with self.assertRaises(ValueError):e.finalized_evidence(lambda name:self.files.read(self.ledger.root+'/'+name),
            self.expected,self.ledger.state['observations']['commission'].get('publications',{}),int(time.time()))
        return value,failure.exception

    def native_backend(self,gate):
        backend=ModelBackend(self.host,self.ledger,self.expected)
        backend.fixture=fixture();self.ledger.state['observations']['fixture']=backend.fixture
        backend.helper={'served_root':'/var/www/ubo-sites/staging/sites/'+backend.fixture['site_key'],
                        'journal_root':'/var/lib/ubo-site-publisher/staging/'+backend.fixture['site_key']}
        backend.worker={'bindings':[{}]};backend.plan={'config':{'tool_sha256':{'php':'b'*64}}}
        # Infrastructure effects are explicitly intercepted; actual probe bodies,
        # child parsing, collector, validation and private publication still run.
        backend.own=mock.Mock();setattr(backend,'probe_'+gate,lambda:getattr(NativeProbes,'probe_'+gate)(backend))
        return backend

    def child_adapter(self,value,exit_code=0,malformed=False):
        def run(label,args,seconds=30,role='operator',**kwargs):
            payload=value(role) if callable(value) else value
            script='import sys,json,os;v=json.load(sys.stdin);v["pid"]=os.getpid();print(json.dumps(v));sys.exit('+str(exit_code)+')'
            if malformed:script='import sys;sys.stdout.write("{SECRET_INVALID_JSON");sys.exit('+str(exit_code)+')'
            result=self.host.owner.capture([sys.executable,'-I','-B','-c',script],3,
                stdin=canonical(dict(data=payload,uid=ROLES[role])))
            self.ledger.state['commands'].append(dict(id=label,caller=role,pid=result['pid'],exit=result['exit']))
            if result['exit'] not in kwargs.get('allowed',(0,)):raise Failure('controlled_child_exit')
            return result
        self.host.run=run

    def test_failed_filesystem_return_survives_cleanup_and_keeps_prior_evidence(self):
        backend=ModelBackend(self.host,self.ledger,self.expected)
        backend.values['filesystem']=dict(data('filesystem'),replacement_rejected=0)
        original=backend.cleanup;prior=[];snapshots=[]
        def cleanup(gate):
            if gate=='filesystem':
                prior.append(self.files.read(self.ledger.root+'/commission-apache.json'))
                raw=self.files.read(self.ledger.root+'/commission-filesystem-before-restore.json');snapshots.append(raw)
                self.assertEqual(decode(raw)['observations']['data']['replacement_rejected'],0)
                self.assertEqual(backend.partial['data']['replacement_rejected'],0)
                backend.values[gate]['replacement_rejected']=1;backend.partial.clear()
            return original(gate)
        backend.cleanup=cleanup;before,error=self.rejected_checkpoint(backend,'filesystem',ValueError)
        self.assertEqual(str(error),'filesystem_negatives')
        self.assertEqual(before['observations']['data']['replacement_rejected'],0)
        self.assertEqual(before['observations']['progress']['predicate'],'FAILED')
        self.assertNotIn('independent',before['observations'])
        self.assertEqual(self.files.read(self.ledger.root+'/commission-filesystem-before-restore.json'),snapshots[0])
        self.assertEqual(self.files.read(self.ledger.root+'/commission-apache.json'),prior[0])
        self.assertEqual(backend.events,[('probe','apache'),('cleanup','apache'),('probe','filesystem'),('cleanup','filesystem')])

    def test_valid_filesystem_return_reaches_independent_publication(self):
        backend,receipts=self.collector()
        raw=e.published_record(lambda name:self.files.read(self.ledger.root+'/'+name),self.expected,
            'filesystem',receipts['filesystem'],int(time.time()),native=False)
        value=decode(raw);self.assertEqual(value['observations']['data']['replacement_rejected'],1)
        self.assertGreater(value['observations']['independent']['pid'],0)
        self.assertEqual(value['result'],'MEASURED');self.assertEqual(value['cleanup']['remaining'],0)

    def test_other_invalid_returned_numbers_and_status_remain_actual(self):
        for gate,field,invalid in (('filesystem','reader_exit',9),('ownership','helper_source_errno',0),
                                   ('runtime','utc_equal',0),('provisioning','result','VERIFIED')):
            with self.subTest(gate=gate):
                case=Controls();case.setUp()
                try:
                    backend=ModelBackend(case.host,case.ledger,case.expected);backend.values[gate]=dict(data(gate),**{field:invalid})
                    before,_=case.rejected_checkpoint(backend,gate,ValueError)
                    self.assertEqual(before['observations']['data'][field],invalid)
                finally:case.doCleanups()

    def test_native_runtime_internal_pin_failure_retains_child_measurements(self):
        backend=self.native_backend('runtime');self.child_adapter(data('runtime'))
        before,error=self.rejected_checkpoint(backend,'runtime',ValueError)
        self.assertEqual(str(error),'runtime_php_pin');observed=before['observations']
        self.assertEqual(observed['data']['php_sha256'],'a'*64)
        self.assertEqual(observed['progress']['child-authority-parse'],'COMPLETED')
        self.assertEqual(observed['progress']['runtime-php-pin'],'FAILED')
        self.assertEqual(observed['participants'],[dict(role='authority',uid=ROLES['authority'],
            pid=self.host.owner.context[-1]['pid'],exit=0)])
        self.assertNotIn('independent',observed)

    def test_native_child_decode_failure_retains_executed_participant(self):
        backend=self.native_backend('runtime');self.child_adapter({},malformed=True)
        before,_=self.rejected_checkpoint(backend,'runtime');observed=before['observations']
        self.assertEqual(observed['data'],{});self.assertEqual(observed['progress']['child-authority-parse'],'FAILED')
        self.assertEqual(observed['participants'][0]['pid'],self.host.owner.context[-1]['pid'])
        self.assertNotIn(b'SECRET_',canonical(before))

    def test_native_command_error_retains_recorded_pid_and_nonzero_exit(self):
        backend=self.native_backend('runtime');self.child_adapter({},exit_code=7)
        before,_=self.rejected_checkpoint(backend,'runtime',Failure);observed=before['observations']
        self.assertEqual(observed['data'],{});self.assertEqual(observed['participants'][0]['exit'],7)
        self.assertEqual(observed['participants'][0]['pid'],self.host.owner.context[-1]['pid'])
        self.assertEqual(observed['progress']['child-authority'],'FAILED')
        self.assertNotIn('child-authority-parse',observed['progress'])

    def test_independent_failure_preserves_measurements_without_inventing_independence(self):
        backend=ModelBackend(self.host,self.ledger,self.expected);setup=self.host.setup
        def failed(mode,role):
            result=setup(mode,role)
            if backend.gate=='filesystem':raise OSError('SECRET_INDEPENDENT')
            return result
        self.host.setup=failed
        before,_=self.rejected_checkpoint(backend,'filesystem',OSError);observed=before['observations']
        self.assertEqual(observed['data'],data('filesystem'));self.assertNotIn('independent',observed)
        self.assertEqual(observed['progress']['independent'],'FAILED')
        self.assertEqual(observed['participants'][-1]['pid'],self.host.owner.context[-1]['pid'])
        self.assertNotIn(b'SECRET_',canonical(before))

    def test_probe_capture_cleanup_failures_remain_distinct_and_owned(self):
        backend=ModelBackend(self.host,self.ledger,self.expected);backend.values['filesystem']=dict(data('filesystem'),replacement_rejected=0)
        row=self.ledger.write(p.CONFIG+'/controlled-owned',b'nonsecret owned bytes')
        original_publish=self.ledger.publish;attempted=[]
        def publish(stem,value):
            if stem=='commission-filesystem-before-restore':attempted.append(copy.deepcopy(value));raise OSError('SECRET_CAPTURE')
            return original_publish(stem,value)
        original_cleanup=backend.cleanup
        def cleanup(gate):
            if gate=='filesystem':
                backend.events.append(('cleanup',gate));backend.partial.clear();raise RuntimeError('SECRET_CLEANUP')
            return original_cleanup(gate)
        backend.cleanup=cleanup
        with mock.patch.object(self.ledger,'publish',side_effect=publish),self.assertRaisesRegex(ValueError,'filesystem_negatives'):
            c.collect(self.host,self.ledger,backend,self.expected)
        self.assertEqual(attempted[0]['observations']['data']['replacement_rejected'],0)
        self.assertEqual(self.ledger.state['first_failure']['type'],'ValueError')
        self.assertTrue({'commission-capture','commission-cleanup'}<={x['kind'] for x in self.ledger.state['secondary']})
        self.assertIn(('cleanup','filesystem'),backend.events)
        self.assertNotEqual(row['state'],'RELEASED');self.assertTrue(self.files.exists(row['path']))
        self.assertFalse(self.files.exists(r.RECEIPT));self.assertNotIn(b'SECRET_',canonical(self.ledger.public()))

    def test_private_and_malformed_returned_data_is_bounded_and_redacted(self):
        backend=ModelBackend(self.host,self.ledger,self.expected)
        value=data('filesystem');value.update(replacement_rejected='SECRET_TOKEN',device={'secret':'SECRET_PASSWORD'},
            read_sha256='/private/SECRET_PATH',reader_exit=True,sync_calls=2**100)
        value['SECRET_UNKNOWN_FIELD']=['SECRET_BODY']*10000;backend.values['filesystem']=value
        before,_=self.rejected_checkpoint(backend,'filesystem',ValueError);observed=before['observations']
        self.assertEqual(observed['data']['replacement_rejected'],{'invalid':'string'})
        self.assertEqual(observed['data']['device'],{'invalid':'object'})
        self.assertEqual(observed['data']['reader_exit'],{'invalid':'boolean'})
        self.assertEqual(observed['data']['sync_calls'],{'invalid':'integer'})
        self.assertEqual(observed['unexpected_fields'],1)
        self.assertLess(len(canonical(before)),8192);self.assertNotIn(b'SECRET_',canonical(before))
        # No inspection of arbitrary object contents or class names is allowed.
        class PrivateObject:
            def __repr__(self):raise AssertionError('must not stringify')
        self.assertEqual(NativeProbes.diagnostic('device',PrivateObject()),{'invalid':'unsupported'})

    def test_native_ownership_keeps_first_role_before_later_child_failure(self):
        backend=self.native_backend('ownership')
        first=dict(source=dict(errno=0,mode=0o600,parent_mode=0o700),served=dict(errno=13),journal=dict(errno=13))
        def payload(role):
            if role=='helper':raise OSError('SECRET_LATER_ROLE')
            return first
        self.child_adapter(payload)
        before,_=self.rejected_checkpoint(backend,'ownership',OSError);observed=before['observations']
        self.assertEqual(observed['data']['worker_source_errno'],0)
        self.assertEqual(observed['data']['source_mode'],0o600)
        self.assertNotIn('helper_source_errno',observed['data'])
        self.assertEqual(observed['participants'][0]['role'],'worker')
        self.assertEqual(observed['participants'][0]['pid'],self.host.owner.context[-1]['pid'])

    def test_native_http_first_response_survives_later_failure(self):
        for gate in ('apache','health'):
            with self.subTest(gate=gate):
                case=Controls();case.setUp()
                try:
                    backend=case.native_backend(gate)
                    modules='\n'.join(name+'_module (shared)' for name in (*p.MODULES,'core','so','http')).encode()
                    # Apache module command is an explicit controlled boundary;
                    # no Apache binary or HTTP request is executed.
                    case.host.run=lambda *args,**kwargs:dict(stdout=modules,pid=os.getpid(),exit=0)
                    with mock.patch.object(probes,'fetch',side_effect=[dict(status=503,body='SECRET_BODY'),OSError('SECRET_HTTP')]):
                        before,_=case.rejected_checkpoint(backend,gate,OSError)
                    self.assertEqual(before['observations']['data']['active_status'],503)
                    self.assertNotIn('candidate_status',before['observations']['data']);self.assertNotIn(b'SECRET_',canonical(before))
                finally:case.doCleanups()

    def test_native_inventory_and_journal_progress_before_later_failure(self):
        for gate in ('assets','recovery'):
            with self.subTest(gate=gate):
                case=Controls();case.setUp()
                try:
                    backend=case.native_backend(gate)
                    if gate=='assets':
                        with mock.patch.object(case.files,'inventory',side_effect=OSError('controlled inventory failure')):
                            before,_=case.rejected_checkpoint(backend,gate,OSError)
                        self.assertEqual(before['observations']['data']['release_count'],2)
                        self.assertNotIn('served_assets',before['observations']['data'])
                    else:
                        raw=b'{SECRET_INVALID_JOURNAL';root=backend.helper['journal_root'];case.files.makedirs(root)
                        case.files.create(root+'/state.json',raw,lambda fd:None)
                        before,_=case.rejected_checkpoint(backend,gate)
                        self.assertEqual(before['observations']['data'],dict(journal_sha256=digest(raw)))
                        self.assertNotIn(b'SECRET_',canonical(before))
                finally:case.doCleanups()

    def test_native_provisioning_retains_configtest_before_internal_rejection(self):
        backend=self.native_backend('provisioning');self.child_adapter({})
        self.host.namespace=lambda config:None
        # FixedStorage I/O is mapped explicitly; never touch real /etc paths.
        self.files.makedirs(backend.helper['journal_root'])
        self.files.create(backend.helper['journal_root']+'/state.json',b'{}',lambda fd:None)
        with mock.patch.object(probes.FixedStorage,'safe_read',return_value=b'controlled config'),\
             mock.patch.object(probes.FixedStorage,'stage',return_value=None):
            before,error=self.rejected_checkpoint(backend,'provisioning',Failure)
        self.assertEqual(str(error),'invalid_config_accepted');observed=before['observations']['data']
        self.assertEqual(observed['baseline_exit'],0);self.assertEqual(observed['candidate_exit'],0)
        self.assertEqual(observed['before_sha256'],digest(b'controlled config'))
        self.assertNotIn('result',observed)

    def test_native_filesystem_dispatch_preserves_valid_and_invalid_child_values(self):
        for rejected in (0,1):
            with self.subTest(replacement_rejected=rejected):
                case=Controls();case.setUp()
                try:
                    backend=case.native_backend('filesystem');case.child_adapter(dict(data('filesystem'),replacement_rejected=rejected))
                    if rejected==0:
                        before,_=case.rejected_checkpoint(backend,'filesystem',ValueError)
                        self.assertEqual(before['observations']['data']['replacement_rejected'],0)
                    else:
                        receipts=c.collect(case.host,case.ledger,backend,case.expected)
                        value=decode(e.published_record(lambda name:case.files.read(case.ledger.root+'/'+name),case.expected,
                            'filesystem',receipts['filesystem'],int(time.time()),native=False))
                        self.assertEqual(value['observations']['data']['replacement_rejected'],1)
                        self.assertEqual(value['result'],'MEASURED')
                finally:case.doCleanups()

    def test_native_ownership_missing_or_scalar_sections_never_satisfy_predicates(self):
        for variant in ('valid','scalar','missing'):
            with self.subTest(variant=variant):
                case=Controls();case.setUp()
                try:
                    backend=case.native_backend('ownership')
                    rows={'worker':dict(source=dict(errno=0,mode=0o600,parent_mode=0o700),served=dict(errno=13),journal=dict(errno=13)),
                          'helper':dict(source=dict(errno=13)),
                          'apache':dict(source=dict(errno=13),public=dict(errno=0,mode=0o440,sha256=digest(b'M6E public access canary\n')),public_write=dict(errno=13))}
                    if variant=='scalar':rows['helper']['source']=13
                    if variant=='missing':del rows['worker']['source']['mode']
                    case.child_adapter(lambda role:rows[role])
                    if variant=='valid':
                        receipts=c.collect(case.host,case.ledger,backend,case.expected)
                        value=decode(e.published_record(lambda name:case.files.read(case.ledger.root+'/'+name),case.expected,
                            'ownership',receipts['ownership'],int(time.time()),native=False))
                        self.assertEqual(value['observations']['data'],data('ownership'))
                    else:
                        before,_=case.rejected_checkpoint(backend,'ownership',ValueError)
                        if variant=='scalar':self.assertEqual(before['observations']['data']['helper_source_errno'],{'invalid':'section'})
                        else:self.assertNotIn('source_mode',before['observations']['data'])
                finally:case.doCleanups()

    def test_non_linux_denial_precedes_every_protected_read(self):
        helper=load_helper();uid=mock.Mock(side_effect=AssertionError('UID reached'))
        with mock.patch.object(helper,'sys',types.SimpleNamespace(platform='controlled-non-linux',argv=['helper'])),\
             mock.patch.object(helper,'os',types.SimpleNamespace(geteuid=uid,O_NOFOLLOW=0)),\
             mock.patch.object(helper,'protected',side_effect=AssertionError('operational read reached')) as protected:
            with self.assertRaises(helper.Denied):helper.load_config()
            protected.assert_not_called();uid.assert_not_called()

    def test_linux_wrong_uid_denial_reads_only_synthetic_configuration(self):
        helper=load_helper();cfg=p.configurations(fixture(),dict(schema='ubo_m6e_'+'b'*32,credentials_sha256='b'*64),'c'*64)['helper.json']
        def read(name,limit):
            self.assertEqual((name,limit),(helper.CONFIG,65536));return canonical(cfg)
        uid=mock.Mock(return_value=cfg['publisher_uid']+1)
        with mock.patch.object(helper,'sys',types.SimpleNamespace(platform='linux',argv=['helper'])),\
             mock.patch.object(helper,'os',types.SimpleNamespace(geteuid=uid,O_NOFOLLOW=0)),\
             mock.patch.object(helper,'protected',side_effect=read) as protected:
            with self.assertRaises(helper.Denied):helper.load_config()
            protected.assert_called_once_with(helper.CONFIG,65536);uid.assert_called_once_with()

    def test_missing_protected_configuration_is_distinct_from_uid_denial(self):
        helper=load_helper();uid=mock.Mock(side_effect=AssertionError('UID reached'));missing=FileNotFoundError('controlled missing config')
        with mock.patch.object(helper,'sys',types.SimpleNamespace(platform='linux',argv=['helper'])),\
             mock.patch.object(helper,'os',types.SimpleNamespace(geteuid=uid,O_NOFOLLOW=0)),\
             mock.patch.object(helper,'protected',side_effect=missing) as protected:
            with self.assertRaises(FileNotFoundError) as failure:helper.load_config()
            self.assertIs(failure.exception,missing);protected.assert_called_once_with(helper.CONFIG,65536);uid.assert_not_called()

    def test_private_publication_and_exact_readonly_reconciliation(self):
        _,candidate,_=self.candidate();raw=canonical(candidate);acks=[]
        self.assertEqual(self.publish(raw,acks.append)['result'],'RECEIPT ISSUED');self.assertEqual(acks,[raw])
        before=self.files.read(self.ledger.path);sequence=self.ledger.state['sequence']
        observed=r.reconcile(self.files,self.ledger,self.expected,int(time.time()))
        self.assertEqual(observed['result'],'EXACT RECEIPT PRESENT');self.assertEqual(before,self.files.read(self.ledger.path))
        self.assertEqual(sequence,self.ledger.state['sequence'])
        self.assertEqual(self.files.identity(r.RECEIPT)['mode'],0o644)
        self.assertEqual(self.files.path(r.RECEIPT).stat().st_nlink,1)
        with self.assertRaises(ValueError):r.reconcile(self.files,self.ledger,self.expected,self.expected['expires_at'])

    def test_preexisting_receipt_not_overwritten(self):
        _,candidate,_=self.candidate();self.files.create(r.RECEIPT,b'foreign',lambda fd:None)
        with self.assertRaisesRegex(ValueError,'preexisting'):self.publish(canonical(candidate))
        self.assertEqual(self.files.read(r.RECEIPT),b'foreign')

    def test_partial_write_failure_keeps_receipt_absent(self):
        _,candidate,_=self.candidate();original=self.files.create
        def broken(name,raw,identified,outcome=None):
            if name==r.PENDING:
                def stop(fd):identified(fd);os.write(fd,raw[:13]);raise OSError('write_fault')
                return original(name,raw,stop,outcome)
            return original(name,raw,identified,outcome)
        with mock.patch.object(self.files,'create',side_effect=broken),self.assertRaisesRegex(OSError,'write_fault'):self.publish(canonical(candidate))
        self.assertFalse(self.files.exists(r.RECEIPT));self.assertFalse(self.files.exists(r.PENDING))

    def test_file_sync_failure_keeps_receipt_absent(self):
        _,candidate,_=self.candidate();sync=os.fsync
        def broken(fd):
            if self.files.exists(r.PENDING) and os.fstat(fd).st_ino==self.files.path(r.PENDING).stat().st_ino:raise OSError('sync_fault')
            return sync(fd)
        with mock.patch.object(os,'fsync',side_effect=broken),self.assertRaisesRegex(OSError,'sync_fault'):self.publish(canonical(candidate))
        self.assertFalse(self.files.exists(r.RECEIPT))

    def test_rename_failure_never_claims_absence_or_deletes_conflict(self):
        _,candidate,_=self.candidate()
        def conflict(files,source,destination):
            files.create(destination,b'foreign',lambda fd:None);raise FileExistsError('conflict')
        with self.assertRaisesRegex(Failure,'uncertain'):self.publish(canonical(candidate),rename=conflict)
        self.assertEqual(self.files.read(r.RECEIPT),b'foreign')
        with self.assertRaises(ValueError):r.reconcile(self.files,self.ledger,self.expected,int(time.time()))

    def test_post_rename_sync_failure_retains_complete_object(self):
        _,candidate,_=self.candidate();raw=canonical(candidate);sync=self.files.sync_dir
        def broken(name):
            if name==p.CONFIG and self.files.exists(r.RECEIPT):raise OSError('dir_sync_fault')
            return sync(name)
        with mock.patch.object(self.files,'sync_dir',side_effect=broken),self.assertRaisesRegex(Failure,'uncertain'):self.publish(raw)
        self.assertEqual(self.files.read(r.RECEIPT),raw)
        self.assertEqual(r.reconcile(self.files,self.ledger,self.expected,int(time.time()))['result'],'EXACT RECEIPT PRESENT')

    def test_rename_error_after_effect_retains_exact_issuance(self):
        _,candidate,_=self.candidate();raw=canonical(candidate)
        def after_effect(files,source,destination):
            r.rename_new(files,source,destination);raise OSError('rename_ack_lost')
        with self.assertRaisesRegex(Failure,'uncertain'):self.publish(raw,rename=after_effect)
        self.assertEqual(self.files.read(r.RECEIPT),raw)
        self.assertEqual(r.reconcile(self.files,self.ledger,self.expected,int(time.time()))['result'],'EXACT RECEIPT PRESENT')
        wrong=dict(self.expected,run_id='0'*32)
        with self.assertRaises(ValueError):r.reconcile(self.files,self.ledger,wrong,int(time.time()))

    def test_last_dependency_recheck_blocks_publication(self):
        _,candidate,loaded=self.candidate();installed=candidate['installed_files']
        def changed():
            name=p.CONFIG+'/worker.json';self.files.path(name).write_bytes(self.files.read(name)+b' ')
            r.verify_inventory(self.files,installed,loaded)
        with self.assertRaisesRegex(ValueError,'changed_or_omitted'):
            r.publish(self.files,self.ledger,canonical(candidate),self.expected,lambda raw:None,recheck=changed)
        self.assertFalse(self.files.exists(r.RECEIPT));self.assertFalse(self.files.exists(r.PENDING))

    def test_ack_failure_preserves_exact_object_and_original_failure(self):
        _,candidate,_=self.candidate();raw=canonical(candidate)
        def fail(raw):raise RuntimeError('SECRET_ACK_CANARY')
        with self.assertRaisesRegex(Failure,'uncertain'):self.publish(raw,fail)
        self.assertEqual(self.files.read(r.RECEIPT),raw);self.assertNotIn(b'SECRET_',canonical(self.ledger.public()))
        self.files.path(r.RECEIPT).write_bytes(raw+b' ')
        with self.assertRaises(ValueError):r.reconcile(self.files,self.ledger,self.expected,int(time.time()))

    def test_no_issuance_before_validation_and_cleanup(self):
        backend,publications=self.collector()
        with mock.patch.object(r,'publish') as publishing:
            with self.assertRaises(ValueError):c.issue(self.host,self.ledger,backend,self.expected,publications,{}, {},{}, {})
            publishing.assert_not_called()

    def test_no_override_or_full_native_acceptance(self):
        for args in (['--force'],['--commission','--root','/tmp'],['--commission','--modeled']):
            with self.subTest(args=args),mock.patch.object(c,'NativeHost') as host,self.assertRaises(ValueError):c.main(args)
            host.assert_not_called()
        with self.assertRaises(Failure):require_native_ready()
        self.assertEqual(e.MISSING['filesystem'],());self.assertEqual(e.MISSING['ownership'],())
        self.assertEqual(sum(bool(v) for v in e.MISSING.values()),6)

    def scratch(self):
        self.ledger.state['observations']['fixture']=fixture()
        self.ledger.state['observations']['commission']=dict(binding=self.expected,resources=[])
        root='/var/www/ubo-sites/staging/sites/'+fixture()['site_key']+'/staging'
        self.files.makedirs(root)
        backend=NativeProbes.__new__(NativeProbes);backend.host=self.host;backend.ledger=self.ledger
        row=backend.own('filesystem',root+'/commission-filesystem','scratch',2102,2104)
        self.files.create(row['path']+'/object',b'original',lambda fd:None)
        resource=self.ledger.state['observations']['commission']['resources'][0]
        return row,resource

    def test_owned_scratch_capture_precedes_actual_cleanup(self):
        row,resource=self.scratch()
        # Explicit Linux UID boundary model. Inodes, names, bytes, ordering and
        # cleanup are real. This does not claim Windows enforces helper UID 2102.
        with mock.patch.object(probes,'ROLES',dict(ROLES,helper=self.files.path(row['path']).stat().st_uid)):
            probes.cleanup_owned(self.host,self.ledger)
        self.assertFalse(self.files.exists(row['path']));self.assertEqual(row['state'],'RELEASED')
        exports=list(self.files.path(self.ledger.root).glob('commission-scratch-before-cleanup-*.json'))
        self.assertEqual(len(exports),1);self.assertIn('object',decode(exports[0].read_bytes())['children'])

    def test_scratch_foreign_names_and_replaced_inode_are_preserved(self):
        row,resource=self.scratch();mapped_roles=dict(ROLES,helper=self.files.path(row['path']).stat().st_uid)
        with mock.patch.object(probes,'ROLES',mapped_roles):
            resource['children']=probes.scratch_inventory(self.host,self.ledger,resource)
            self.files.create(row['path']+'/foreign',b'foreign',lambda fd:None)
            with self.assertRaisesRegex(ValueError,'unowned_name'):probes.cleanup_owned(self.host,self.ledger)
            self.assertEqual(self.files.read(row['path']+'/foreign'),b'foreign');self.files.unlink(row['path']+'/foreign')
            alternate=Path(self.tmp.name)/'replacement';alternate.write_bytes(b'original')
            self.assertNotEqual(alternate.stat().st_ino,self.files.path(row['path']+'/object').stat().st_ino)
            os.replace(alternate,self.files.path(row['path']+'/object'))
            with self.assertRaisesRegex(ValueError,'scratch_replaced'):probes.cleanup_owned(self.host,self.ledger)
            self.assertEqual(self.files.read(row['path']+'/object'),b'original')

    def test_bootstrap_record_survives_dedicated_postinspection(self):
        with tempfile.TemporaryDirectory(prefix='m6e-history-controlled-') as folder:
            host=ModelHost(folder);plan=host.preflight();ledger=Ledger.create(host.fs,plan['config']);host.ledger=ledger
            # Existing controlled bootstrap path; no native command is dispatched.
            for name,raw in p.control_units().items():ledger.write('/run/systemd/system/'+name,raw)
            host.start('ubo-m6e-watchdog.timer');ledger.state['watchdog']='ARMED';ledger.save()
            bootstrap.prepare_owned(host,ledger,plan)
            original=host.fs.read(ledger.root+'/bootstrap.json');history=bootstrap.inspect_record(host,ledger,plan)
            ledger.phase('commission-incomplete');root=c.current(host,ledger,plan,history)
            self.assertEqual(len(root),64);self.assertEqual(host.fs.read(ledger.root+'/bootstrap.json'),original)
            with self.assertRaisesRegex(ValueError,'base_not_ready'):bootstrap.inspect_ready(host,ledger,plan)
            self.assertIn('ubo-m6e-commission.service',p.control_units())
            self.assertIn(b'TimeoutStartSec=420',p.control_units()['ubo-m6e-commission.service'])

    def test_complete_controlled_commission_attempt_and_readonly_inspection(self):
        with tempfile.TemporaryDirectory(prefix='m6e-complete-controlled-') as folder:
            host=ModelHost(folder)
            original_path=host.fs.path
            host.fs.path=lambda name:host.fs.root if str(name)=='/' else original_path(name)
            extras=['scripts/site-deployment-worker.php','scripts/reconcile-site-jobs.php',
                'tests/support/WebsitePlatformM6ECommissionChild.php','private/classes/SiteDeploymentService.php',
                'private/classes/SiteDeploymentContract.php','private/classes/SitePublisherAuthority.php',
                'private/classes/domains/DomainManager.php']
            for name in extras:
                raw=(ROOT/name).read_bytes();host.source_files[name]=raw;host.inventory[name]=dict(mode='100644',sha256=digest(raw))
            plan=host.preflight();ledger=Ledger.create(host.fs,plan['config']);host.ledger=ledger
            for name,raw in p.control_units().items():ledger.write('/run/systemd/system/'+name,raw)
            host.start('ubo-m6e-watchdog.timer');ledger.state['watchdog']='ARMED';ledger.save()
            bootstrap.prepare_owned(host,ledger,plan)
            nested='/usr/local/lib/ubo-site-publisher/php/domains/DomainManager.php'
            self.assertEqual(host.fs.read(nested),(ROOT/'private/classes/domains/DomainManager.php').read_bytes())
            original=host.fs.read(ledger.root+'/bootstrap.json')
            ledger.state['services'].append(dict(unit=c.UNIT,state='STARTING',pid=None,invocation=None,cgroup=None));ledger.save()
            class FullModel(ModelBackend):
                def __init__(self,host,ledger,plan,history):super().__init__(host,ledger,binding())
                def loaded_files(self):
                    return {
                        'worker':['/opt/ubo-m6e/private/classes/'+name for name in ('SitePublisherHostContract.php','SiteDeploymentContract.php','SiteDeploymentService.php')]
                            +['/opt/ubo-m6e/tests/support/WebsitePlatformM6ECommissionChild.php'],
                        'authority':['/usr/local/lib/ubo-site-publisher/php/'+name for name in ('SitePublisherHostContract.php','SiteDeploymentContract.php','SitePublisherAuthority.php')],
                    }
            result=c.commission(host,ledger,plan,FullModel)
            self.assertEqual(result['result'],'COMMISSIONING INCOMPLETE');self.assertEqual(result['host_receipt'],'ABSENT')
            self.assertEqual(list(ledger.state['observations']['commission']['publications']),list(GATES))
            before=host.fs.read(ledger.path)
            self.assertEqual(c.inspect(host,ledger,plan)['host_receipt'],'ABSENT')
            self.assertEqual(host.fs.read(ledger.path),before);self.assertEqual(host.fs.read(ledger.root+'/bootstrap.json'),original)
            published=ledger.root+'/commission-apache.json';saved=host.fs.read(published)
            host.fs.path(published).write_bytes(saved+b' ')
            with self.assertRaisesRegex(ValueError,'publication_bytes'):c.inspect(host,ledger,plan)
            host.fs.path(published).write_bytes(saved)
            host.units[c.UNIT]=True
            with self.assertRaisesRegex(ValueError,'inspection_service'):c.inspect(host,ledger,plan)
            host.units[c.UNIT]=False
            with self.assertRaisesRegex(ValueError,'already_attempted'):c.commission(host,ledger,plan,FullModel)
            with self.assertRaises(Failure):require_native_ready()

if __name__=='__main__':unittest.main()
