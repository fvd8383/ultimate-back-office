"""Controlled-local commissioning tests. No native infrastructure or operational receipt.

Real private files, subprocess capture, publication and validators; Unix identities,
tool/VM/service observations and probe results are explicitly modeled. Six mandatory
native producers remain incomplete, so no positive native issuance test is claimed.
"""
import copy
import importlib.util
import json
import os
from pathlib import Path
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

class MappedFiles(PrivateFiles):
    def path(self,path):return self.root if str(path)=='/' else super().path(path)

def binding():
    value={name:'a'*64 for name in ('bootstrap_sha256','handoff_sha256','configuration_sha256','source_inventory_sha256',
        'tools_sha256','modules_sha256','roots_sha256','installed_sha256')}
    return value|p.base_identity(config())|dict(roles_sha256=digest(canonical(ROLES)),expires_at=int(time.time())+600)

def data(gate):
    # Synthetic observations are solely for controlled parsing/schema tests.
    value={name:('a'*64 if name.endswith('sha256') else 'REJECTED' if name=='result' else 0) for name in e.FIELDS[gate]}
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
        self.events=[];self.fail_gate=None;self.fail_cleanup=False
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
        out=self.host.owner.capture([sys.executable,'-I','-B','-c','import sys;sys.stdout.buffer.write(sys.stdin.buffer.read())'],3,stdin=canonical(data(gate)))
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
        self.assertEqual(value['observations'],{});self.assertIsNotNone(value['first_failure'])
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
        spec=importlib.util.spec_from_file_location('format_helper',ROOT/'infrastructure/deployment/ubo_site_publish.py')
        helper=importlib.util.module_from_spec(spec);spec.loader.exec_module(helper)
        def private_read(name,limit):return canonical(candidate) if name==r.RECEIPT else self.files.read(name,limit)
        # Explicit modeled OS/protected-file boundaries; execute the real function's
        # complete schema/pinning/identity checks. No operational path is written.
        with mock.patch.object(helper,'protected',side_effect=private_read),mock.patch.object(helper.sys,'platform','linux'),\
             mock.patch.object(helper.sys,'argv',['helper']),mock.patch.object(helper.os,'geteuid',return_value=2102,create=True),\
             mock.patch.object(helper.os,'O_NOFOLLOW',getattr(os,'O_NOFOLLOW',0),create=True):
            self.assertEqual(helper.load_config(),cfgs['helper.json'])
            candidate['installed_files'][p.CONFIG+'/helper.json']='0'*64
            with self.assertRaises(helper.Denied):helper.load_config()
        with self.assertRaises(helper.Denied):helper.load_config()

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
