"""Local preflight integrity and owned-file controls; SQL/accounts/processes are MODELED."""
import copy
from pathlib import Path
import sys
import tempfile
import time
import unittest
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
sys.path.insert(0,str(Path(__file__).resolve().parent))
import WebsitePlatformM6EAcceptance as a
import WebsitePlatformM6EBootstrap as b
import WebsitePlatformM6EBootstrapControlTest as controls
import WebsitePlatformM6ECheckpoint as c
import WebsitePlatformM6EEligibility as e
import WebsitePlatformM6EEligibilityModel as model
import WebsitePlatformM6ERestoration as r
import WebsitePlatformM6ERestorationTest as restoration_tests
from WebsitePlatformM6EProcess import Failure
ROOT=Path(__file__).resolve().parents[1]

class Integrity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        reference=restoration_tests.Integrity;reference.setUpClass()
        cls.config=reference.config;cls.cp_raw=a.canonical(reference.cp);cls.snapshot_raw=a.canonical(reference.snapshot)
        cls.schema_raw=a.canonical(reference.baseline);cls.accounts=reference.accounts
        cls.source=reference.source|{n:dict(sha256=a.digest((ROOT/n).read_bytes())) for n in e.SOURCE_FILES}
        cls.restoration_raw=a.canonical(reference().validate())
        cls.owner=dict(pid=800,cgroup=['0::/system.slice/ubo-m6e-bootstrap.service'],invocation='e'*32)
        cls.req=e.request(cls.config,cls.source,cls.cp_raw,cls.snapshot_raw,cls.schema_raw,cls.restoration_raw,cls.owner,'d'*32,int(time.time()))
        identity=dict(schema=cls.req['schema'],server_uuid=cls.req['server_uuid'],account=e.ACCOUNT,connection_id=81,grants_sha256='a'*64)
        original=a.decode(cls.cp_raw)['observed'];original['database']={k:identity[k] for k in ('schema','server_uuid','account')}
        cls.gate=dict(result='AWAITING_INDEPENDENT_OBSERVER',phase='complete',first_failure=None,secondary=[],transaction='ROLLED_BACK',closed=True,
            gate_returned=True,identity=identity,observed_sha256=a.digest(a.canonical(original)),absence=dict.fromkeys(c.TABLES,0),
            request_sha256=a.digest(a.canonical(cls.req)),invocation_id=cls.req['invocation_id'],process=dict(pid=900,uid=r.RESTORER,gid=r.RESTORER,owner_pid=800),
            admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')
        cls.seal=dict(account=e.ACCOUNT,locked=True,grants_sha256='a'*64,sessions=0,restorer_processes=0,restoration_accounts=cls.accounts)
        cls.observation=dict(request_sha256=a.digest(a.canonical(cls.req)),phase='after',observation=copy.deepcopy(reference.observation))

    def gate_check(self,value=None,**overrides):
        args=dict(req=self.req,cp_raw=self.cp_raw,pid=900,seal=self.seal,now=int(time.time()));args.update(overrides)
        return e.verify_gate(a.canonical(self.gate if value is None else value),**args)

    def observe(self,value=None,**overrides):
        args=dict(phase='after',req=self.req,cp_raw=self.cp_raw,snapshot_raw=self.snapshot_raw,schema_raw=self.schema_raw,
            config=self.config,source=self.source,execution='controlled-local',accounts=self.accounts,now=int(time.time()));args.update(overrides)
        return e.verify_observer(a.canonical(self.observation if value is None else value),**args)

    def test_complete_original_gate_and_separate_observer(self):
        gate=self.gate_check();observed=self.observe()
        self.assertEqual(gate['transaction'],dict(access='READ WRITE',isolation='REPEATABLE READ',outcome='ROLLED_BACK',closed=True))
        self.assertFalse(gate['gate']['restore']);self.assertEqual(len(observed['tables']),90)
        self.assertEqual(self.req['native_admission'],'BLOCKED');self.assertEqual(self.req['admission'],'UNIMPLEMENTED')

    def test_original_source_run_machine_and_boot_binding(self):
        for key in ('source_sha','run_id','machine_id','boot_id'):
            config=dict(self.config);config[key]='0'*len(config[key])
            with self.subTest(key=key),self.assertRaises(ValueError):
                e.request(config,self.source,self.cp_raw,self.snapshot_raw,self.schema_raw,self.restoration_raw,self.owner,'d'*32,int(time.time()))

    def test_wrong_schema_server_account_or_connection(self):
        for key in ('schema','server_uuid','account','connection_id','grants_sha256'):
            v=copy.deepcopy(self.gate);v['identity'][key]='wrong'
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(v)

    def test_open_unknown_committed_and_failed_transactions(self):
        for key,value in (('transaction','OPEN'),('transaction','UNKNOWN'),('transaction','COMMITTED'),('closed',False),
            ('gate_returned',False),('first_failure','eligibility_gate'),('secondary',['eligibility_cleanup_rollback'])):
            v=copy.deepcopy(self.gate);v[key]=value
            with self.subTest(key=key,value=value),self.assertRaises(ValueError):self.gate_check(v)

    def test_stale_replayed_and_wrong_process_evidence(self):
        with self.assertRaises(ValueError):self.gate_check(now=self.req['expires_at'])
        with self.assertRaises(ValueError):self.gate_check(pid=901)
        for key in ('invocation_id','request_sha256'):
            v=copy.deepcopy(self.gate);v[key]='f'*len(v[key])
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(v)
        for key in ('pid','uid','gid','owner_pid'):
            v=copy.deepcopy(self.gate);v['process'][key]+=1
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(v)

    def test_incomplete_account_or_process_cleanup(self):
        for key,value in (('locked',False),('sessions',1),('restorer_processes',1),('sessions',False),('grants_sha256','f'*64)):
            seal=copy.deepcopy(self.seal);seal[key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(seal=seal)

    def test_changed_source_approval_producer_or_release_evidence(self):
        for key in ('source','approvals','producer','release'):
            cp=a.decode(self.cp_raw);cp['observed'][key]={}
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(cp_raw=a.canonical(cp))
        v=copy.deepcopy(self.gate);v['observed_sha256']='f'*64
        with self.assertRaises(ValueError):self.gate_check(v)

    def test_deployment_rows_fences_and_authority_promotion_denied(self):
        for table in c.TABLES:
            v=copy.deepcopy(self.gate);v['absence'][table]=1
            with self.subTest(table=table),self.assertRaises(ValueError):self.gate_check(v)
        for key in ('admission','native_admission','deployment_authority'):
            v=copy.deepcopy(self.gate);v[key]='PASS'
            with self.subTest(key=key),self.assertRaises(ValueError):self.gate_check(v)

    def test_independent_observer_rejects_drift_b_and_unverified_artifacts(self):
        for table in ('site_revisions','site_approvals','site_build_attempts','site_releases',*c.TABLES):
            v=copy.deepcopy(self.observation);v['observation']['read']['tables'][table]['rows'].append([])
            with self.subTest(table=table),self.assertRaises(ValueError):self.observe(v)
        v=copy.deepcopy(self.observation);v['observation']['artifact']['artifact_sha256']='f'*64
        with self.assertRaises(ValueError):self.observe(v)

    def test_observer_is_bound_to_this_phase_and_request(self):
        for key in ('request_sha256','phase'):
            v=copy.deepcopy(self.observation);v[key]='wrong'
            with self.subTest(key=key),self.assertRaises(ValueError):self.observe(v)
        with self.assertRaises(ValueError):self.observe(self.gate)
        with self.assertRaises(ValueError):self.observe(execution='native-pre-b')

    def test_owner_and_restoration_proof_forgery_rejected(self):
        owner=dict(self.owner,cgroup=['0::/wrong.service'])
        with self.assertRaises(ValueError):e.request(self.config,self.source,self.cp_raw,self.snapshot_raw,self.schema_raw,self.restoration_raw,owner,'d'*32,int(time.time()))
        restored=a.decode(self.restoration_raw);restored['result']='WORKER_SUCCESS'
        with self.assertRaises(ValueError):e.request(self.config,self.source,self.cp_raw,self.snapshot_raw,self.schema_raw,a.canonical(restored),self.owner,'d'*32,int(time.time()))

class Ownership(unittest.TestCase):
    def armed(self):
        folder=tempfile.TemporaryDirectory(prefix='m6e-eligibility-control-');self.addCleanup(folder.cleanup)
        host=controls.ModelHost(folder.name);ledger,plan=controls.Controls.armed(self,host);return host,ledger,plan

    def test_complete_single_use_preflight_and_owned_cleanup(self):
        host,ledger,plan=self.armed();result=b.prepare_owned(host,ledger,plan);state=ledger.state['observations']['eligibility']
        self.assertEqual(state['result'],'ELIGIBILITY PREFLIGHT VERIFIED');self.assertEqual(state['native_admission'],'BLOCKED')
        self.assertIn('pre_b_eligibility_sha256',result['inspected']);commands=[x['id'] for x in ledger.state['commands']]
        order=['restoration-verify','eligibility-prepare','eligibility-before','eligibility-run','eligibility-seal','eligibility-after']
        self.assertEqual([commands.index(x) for x in order],sorted(commands.index(x) for x in order))
        count=len(host.calls)
        with self.assertRaisesRegex(ValueError,'already_started'):e.preflight(host,ledger,plan,ledger.state['observations']['fixture'])
        self.assertEqual(len(host.calls),count)
        proof=host.fs.read(ledger.root+'/eligibility-verification.json');self.assertNotIn(b'SECRET_',proof);self.assertNotIn(b'private-fixture',proof)
        self.assertEqual(a.decode(proof)['gate']['transaction']['outcome'],'ROLLED_BACK')
        b.teardown(host,ledger);self.assertEqual(state['fixture_cleanup'],'OWNED_FIXTURE_REMOVED')
        self.assertEqual(host.fs.read(ledger.root+'/eligibility-verification.json'),proof)
        with self.assertRaises(ValueError):e.checked(host,ledger,plan)

    def test_unknown_child_ack_and_cleanup_error_preserve_failure(self):
        host,ledger,plan=self.armed();host.failure=lambda args:args[-1]=='--run';host.cleanup_fault=True
        with self.assertRaises(Failure):b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['eligibility']
        self.assertEqual(state['result'],'INCOMPLETE');self.assertEqual(state['failed_worker']['transaction'],'ROLLED_BACK')
        self.assertEqual(ledger.state['first_failure']['kind'],'eligibility');self.assertTrue(ledger.state['secondary'])
        self.assertEqual(state['fixture_cleanup'],'INCOMPLETE');self.assertEqual(ledger.state['watchdog'],'ARMED')
        with self.assertRaises(ValueError):e.checked(host,ledger,plan)

    def test_unknown_sql_outcome_cannot_become_success(self):
        host,ledger,plan=self.armed();real=model.execute
        def changed(host,mode):
            value=real(host,mode)
            if mode=='--run':
                path=c.lifecycle(host.cfg['run_id'])['control_root']+'/eligibility/worker/outcome.json';v=a.decode(host.fs.read(path))
                v['transaction']='UNKNOWN';v['closed']=False;host.fs.path(path).write_bytes(a.canonical(v))
            return value
        with mock.patch.object(model,'execute',side_effect=changed),self.assertRaisesRegex(ValueError,'eligibility_outcome'):b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['eligibility'];self.assertEqual(state['failed_worker']['transaction'],'UNKNOWN')
        self.assertEqual(state['result'],'INCOMPLETE');self.assertEqual(ledger.state['phase'],'removed')

    def test_independent_after_observer_rejects_partial_state(self):
        host,ledger,plan=self.armed();real=model.execute
        def changed(host,mode):
            if mode=='--after':host.restored['tables']['notes']['rows']=[]
            return real(host,mode)
        with mock.patch.object(model,'execute',side_effect=changed),self.assertRaisesRegex(ValueError,'restore_exact_database'):b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['eligibility'];self.assertEqual(state['result'],'INCOMPLETE')
        self.assertEqual(state['stage'],'after');self.assertEqual(state['gate']['transaction']['outcome'],'ROLLED_BACK')

    def test_original_database_changes_are_detected(self):
        host,ledger,plan=self.armed();real=model.execute
        def changed(host,mode):
            if mode=='--after':host.built['site_id']+=1
            return real(host,mode)
        with mock.patch.object(model,'execute',side_effect=changed),self.assertRaisesRegex(ValueError,'eligibility_original_mutated'):b.prepare_owned(host,ledger,plan)
        self.assertEqual(ledger.state['observations']['eligibility']['result'],'INCOMPLETE')

    def test_private_input_replacement_and_forged_published_evidence_reject(self):
        host,ledger,plan=self.armed();b.prepare_owned(host,ledger,plan)
        path=ledger.root+'/eligibility-verification.json';raw=host.fs.read(path);host.fs.path(path).write_bytes(raw+b' ')
        with self.assertRaises(ValueError):e.checked(host,ledger,plan)
        host.fs.path(path).write_bytes(raw)
        path=c.lifecycle(host.cfg['run_id'])['control_root']+'/eligibility/request.json';raw=host.fs.read(path)
        replacement=host.fs.path(path+'.replacement');replacement.write_bytes(raw);replacement.replace(host.fs.path(path))
        with self.assertRaises(ValueError):e.checked(host,ledger,plan)

    def test_expired_completed_preflight_is_not_live_evidence(self):
        host,ledger,plan=self.armed();b.prepare_owned(host,ledger,plan)
        with mock.patch.object(e.time,'time',return_value=plan['config']['expires_at']),self.assertRaises(ValueError):e.checked(host,ledger,plan)

if __name__=='__main__':unittest.main(verbosity=2)
