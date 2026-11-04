"""Local comparison/ownership controls. SQL, accounts and services are explicitly MODELED."""
import copy
from pathlib import Path
import shutil
import subprocess
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
import WebsitePlatformM6ESnapshot as s
import WebsitePlatformM6ERestoration as r
import WebsitePlatformM6ERestorationModel as model
from WebsitePlatformM6EProcess import Failure
ROOT=Path(__file__).resolve().parents[1]

class Integrity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        child=subprocess.run([shutil.which('php'),'-d','display_errors=0','-d','log_errors=0',str(ROOT/'tests/WebsitePlatformM6ESnapshotTest.php'),'--bundle'],
            cwd=ROOT,capture_output=True,timeout=60,check=True)
        bundle=a.decode(child.stdout);cls.cp=bundle['checkpoint'];cls.snapshot=bundle['capture'];cls.baseline=bundle['baseline'];cls.config=bundle['config']
        paths=[*sorted((ROOT/'database/migrations').glob('*.sql')),*[ROOT/n for n in (*s.SOURCE_FILES,*c.SOURCE_FILES,*r.SOURCE_FILES)]]
        cls.source={p.relative_to(ROOT).as_posix():dict(sha256=a.digest(p.read_bytes())) for p in paths}
        cls.accounts=model.accounts()
        cls.req=r.request(cls.config,a.canonical(cls.cp),a.canonical(cls.snapshot),a.canonical(cls.baseline))
        cls.observation=dict(profile='m6e-pre-b-restoration-observation-v1',binding=cls.req['binding'],request_sha256=a.digest(a.canonical(cls.req)),
            database=dict(schema=cls.req['destination']['schema'],server_uuid=cls.req['server_uuid'],account=cls.accounts['observer']['account'],
                connection_id=72,grants_sha256=cls.accounts['observer']['grants_sha256']),
            read=dict(definitions=cls.baseline['tables'],tables=cls.snapshot['tables'],collation='utf8mb4_unicode_ci'),artifact=cls.cp['summary'],
            deployment_authority='NONE',admission='UNIMPLEMENTED')

    def validate(self,value=None,**overrides):
        args=dict(req=self.req,snapshot_raw=a.canonical(self.snapshot),schema_raw=a.canonical(self.baseline),cp_raw=a.canonical(self.cp),
            config=self.config,source=self.source,execution='controlled-local',now=int(time.time()),accounts=self.accounts)
        args.update(overrides)
        return r.verify_observation(a.canonical(self.observation if value is None else value),**args)

    def test_complete_original_binding_and_no_authority(self):
        value=self.validate();self.assertEqual(len(value['tables']),90)
        self.assertEqual(value['original']['release_id'],self.cp['summary']['release_id'])
        self.assertEqual(value['original']['producer_attempt_id'],self.cp['summary']['producer_attempt_id'])
        self.assertEqual(value['admission'],'UNIMPLEMENTED');self.assertEqual(value['deployment_authority'],'NONE')
        self.assertNotIn('private-fixture',a.canonical(value).decode());self.assertNotIn('rows',value['original'])

    def test_fixed_destination_comes_only_from_original_run(self):
        self.assertEqual(self.req['destination']['schema'],'ubo_m6e_lifecycle_'+self.config['run_id'])
        for field in ('run_id','source_sha','machine_id','boot_id'):
            cp=copy.deepcopy(self.cp);cp['binding'][field]='wrong'
            with self.subTest(field=field),self.assertRaises(ValueError):r.request(self.config,a.canonical(cp),a.canonical(self.snapshot),a.canonical(self.baseline))
        req=copy.deepcopy(self.req);req['destination']['schema']='working_staging'
        with self.assertRaises(ValueError):self.validate(req=req)

    def test_wrong_database_account_or_server(self):
        for key in ('schema','server_uuid','account','grants_sha256','connection_id'):
            v=copy.deepcopy(self.observation);v['database'][key]='wrong'
            with self.subTest(key=key),self.assertRaises(ValueError):self.validate(v)

    def test_worker_must_be_locked_and_observer_readonly_binding(self):
        for role in ('worker','observer'):
            for key in ('account','locked'):
                accounts=copy.deepcopy(self.accounts);accounts[role][key]='wrong'
                with self.subTest(role=role,key=key),self.assertRaises(ValueError):self.validate(accounts=accounts)

    def test_missing_extra_and_altered_tables(self):
        for kind in ('missing','extra','definition','counter','collation'):
            v=copy.deepcopy(self.observation)
            if kind=='missing':del v['read']['tables']['notes']
            elif kind=='extra':v['read']['tables']['unexpected']=v['read']['tables']['notes']
            elif kind=='definition':v['read']['definitions']['notes']['definition']+=' altered'
            elif kind=='counter':v['read']['tables']['notes']['create_table']+=' AUTO_INCREMENT=999'
            else:v['read']['collation']='utf8mb4_bin'
            with self.subTest(kind=kind),self.assertRaises(ValueError):self.validate(v)

    def test_missing_extra_changed_values_and_primary_keys(self):
        for kind in ('missing','extra','value','primary','null','empty','row_count'):
            v=copy.deepcopy(self.observation);table=v['read']['tables']['notes']
            if kind=='missing':table['rows']=[]
            elif kind=='extra':table['rows'].append(copy.deepcopy(table['rows'][0]))
            elif kind=='row_count':table['row_count']+=1
            else:
                column={'value':'body','primary':'id','null':'nullable','empty':'empty'}[kind]
                table['rows'][0][table['columns'].index(column)]='414C5445524544'
            table['rows_sha256']=a.digest(a.canonical(table['rows']))
            with self.subTest(kind=kind),self.assertRaises(ValueError):self.validate(v)

    def test_original_approval_producer_and_release_are_not_replaceable(self):
        for table in ('sites','site_approvals','site_build_attempts','site_releases','site_business_associations'):
            v=copy.deepcopy(self.observation);v['read']['tables'][table]['rows']=[]
            with self.subTest(table=table),self.assertRaises(ValueError):self.validate(v)

    def test_b_and_operational_rows_are_denied(self):
        for table in (*c.TABLES,'site_revisions'):
            v=copy.deepcopy(self.observation);v['read']['tables'][table]['rows'].append([])
            with self.subTest(table=table),self.assertRaises(ValueError):self.validate(v)

    def test_corrupt_inputs_and_original_artifact(self):
        for name in ('snapshot_raw','schema_raw','cp_raw'):
            with self.subTest(name=name),self.assertRaises((ValueError,KeyError)):self.validate(**{name:b'{}'})
        v=copy.deepcopy(self.observation);v['artifact']['artifact_sha256']='0'*64
        with self.assertRaises(ValueError):self.validate(v)
        v=copy.deepcopy(self.snapshot);v['tables']['notes']['rows_sha256']='0'*64
        with self.assertRaises(ValueError):self.validate(snapshot_raw=a.canonical(v))

    def test_worker_success_is_not_observation(self):
        with self.assertRaises(ValueError):self.validate(dict(result='RESTORED AND INDEPENDENTLY VERIFIED',success=True))
        for key in ('deployment_authority','admission'):
            v=copy.deepcopy(self.observation);v[key]='GRANTED'
            with self.subTest(key=key),self.assertRaises(ValueError):self.validate(v)

    def test_stale_and_model_native_mislabel_rejected(self):
        with self.assertRaises(ValueError):self.validate(now=self.config['expires_at'])
        with self.assertRaises(ValueError):self.validate(execution='native-pre-b')

class Ownership(unittest.TestCase):
    def armed(self):
        folder=tempfile.TemporaryDirectory(prefix='m6e-restoration-control-');self.addCleanup(folder.cleanup)
        host=controls.ModelHost(folder.name);ledger,plan=controls.Controls.armed(self,host)
        return host,ledger,plan

    def test_complete_orchestration_private_evidence_and_owned_cleanup(self):
        host,ledger,plan=self.armed();result=b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['restoration'];d=state['destination'];calls=[row['id'] for row in ledger.state['commands']]
        self.assertEqual(state['result'],'RESTORED AND INDEPENDENTLY VERIFIED')
        self.assertLess(calls.index('restoration-restore'),calls.index('restoration-seal'))
        self.assertLess(calls.index('restoration-seal'),calls.index('restoration-verify'))
        self.assertIn('pre_b_restoration_sha256',result['inspected'])
        self.assertEqual(state['source_before'],state['source_after']);self.assertEqual(state['admission'],'UNIMPLEMENTED')
        with mock.patch.object(r.time,'time',return_value=plan['config']['expires_at']),self.assertRaisesRegex(ValueError,'restore_not_live'):
            r.checked(host,ledger,plan)
        with mock.patch.dict(ledger.state,first_failure=dict(kind='later-failure')),self.assertRaisesRegex(ValueError,'restore_not_live'):
            r.checked(host,ledger,plan)
        with mock.patch.dict(state,binding={}),self.assertRaisesRegex(ValueError,'restore_destination_binding'):
            r.checked(host,ledger,plan)
        for row in host.fs.path(ledger.root).glob('*.json'):
            raw=row.read_bytes();self.assertNotIn(b'SECRET_RESTORE',raw);self.assertNotIn(b'private-fixture',raw)
        original=host.fs.read(ledger.root+'/restoration-verification.json')
        b.teardown(host,ledger)
        self.assertFalse(host.fs.exists(d['control_root']));self.assertFalse(host.fs.exists(d['storage_root']))
        self.assertEqual(host.fs.read(ledger.root+'/restoration-verification.json'),original)
        with self.assertRaises(ValueError):r.checked(host,ledger,plan)

    def test_unknown_writer_completion_preserves_first_error_and_cleanup_error(self):
        host,ledger,plan=self.armed();host.failure=lambda args:args[-1]=='--restore';host.cleanup_fault=True
        with self.assertRaisesRegex(Failure,'SECRET_COMMAND_FAILURE_CANARY'):b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['restoration']
        self.assertEqual(state['result'],'INCOMPLETE');self.assertEqual(state['stage'],'worker')
        self.assertEqual(state['worker']['transaction'],'COMMITTED')  # A lost acknowledgement still cannot become ready.
        self.assertEqual(ledger.state['first_failure']['kind'],'restoration');self.assertTrue(ledger.state['secondary'])
        self.assertEqual(ledger.state['watchdog'],'ARMED');self.assertEqual(ledger.state['phase'],'cleanup-limited')
        self.assertTrue(host.fs.exists(state['destination']['control_root']))
        with self.assertRaises(ValueError):r.checked(host,ledger,plan)

    def test_separate_observer_rejects_partial_destination_despite_worker_success(self):
        host,ledger,plan=self.armed();real=model.execute
        def changed(host,mode):
            if mode=='--verify':host.restored['tables']['notes']['rows']=[]
            return real(host,mode)
        with mock.patch.object(model,'execute',side_effect=changed),self.assertRaisesRegex(ValueError,'restore_exact_database'):
            b.prepare_owned(host,ledger,plan)
        state=ledger.state['observations']['restoration']
        self.assertEqual(state['stage'],'independent-observation');self.assertEqual(state['result'],'INCOMPLETE')
        self.assertEqual(state['worker']['transaction'],'COMMITTED');self.assertEqual(ledger.state['phase'],'removed')

    def test_original_database_mutation_is_detected(self):
        host,ledger,plan=self.armed();real=model.execute;count=0
        def changed(host,mode):
            nonlocal count
            if mode=='--source':
                count+=1
                if count==2:host.built['site_id']+=1
            return real(host,mode)
        with mock.patch.object(model,'execute',side_effect=changed),self.assertRaisesRegex(ValueError,'restore_source_mutated'):
            b.prepare_owned(host,ledger,plan)
        self.assertEqual(ledger.state['observations']['restoration']['result'],'INCOMPLETE')
        self.assertEqual(ledger.state['observations']['restoration']['stage'],'source-after')

    def test_protected_observation_replacement_and_input_replacement_deny(self):
        host,ledger,plan=self.armed();b.prepare_owned(host,ledger,plan);d=ledger.state['observations']['restoration']['destination']
        for path in (d['storage_root']+'/observation.json',d['control_root']+'/snapshot.json'):
            with self.subTest(path=path):
                original=host.fs.read(path);p=host.fs.path(path);replacement=p.with_suffix('.replacement')
                replacement.write_bytes(original);old=host.fs.identity(path)
                replacement.replace(p)
                self.assertNotEqual((old['dev'],old['ino']),(p.stat().st_dev,p.stat().st_ino))
                with self.assertRaises(ValueError):r.checked(host,ledger,plan)

if __name__=='__main__':unittest.main(verbosity=2)
