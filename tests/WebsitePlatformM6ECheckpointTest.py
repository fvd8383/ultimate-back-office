"""Real PHP artifact bytes + durable local files. SQL, Unix ownership and services are MODELS."""
import copy
from pathlib import Path
import shutil
import subprocess
import sys
import time
import unittest
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
sys.path.insert(0,str(Path(__file__).resolve().parent))
import WebsitePlatformM6EAcceptance as a
import WebsitePlatformM6ECheckpoint as c
import WebsitePlatformM6EBootstrap as b
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6EBootstrapControlTest as bootstrap_controls
from WebsitePlatformM6EProcess import Failure

ROOT=Path(__file__).resolve().parents[1]

class Integrity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        result=subprocess.run([shutil.which('php'),str(ROOT/'tests/WebsitePlatformM6ECheckpointTest.php'),'--descriptor'],
            cwd=ROOT,capture_output=True,timeout=60,check=True)
        data=a.decode(result.stdout);cls.v=data['checkpoint'];cls.fixture=data['fixture']
        cls.config=cls.v['binding']|dict(expires_at=cls.v['expires_at'])
        cls.database=cls.v['observed']['database']
        cls.source={name:dict(sha256=a.digest((ROOT/name).read_bytes())) for name in c.SOURCE_FILES}

    def validate(self,value=None,**kw):
        args=dict(config=self.config,database=self.database,fixture=self.fixture,source=self.source,execution='controlled-local',now=int(time.time()))
        args.update(kw);return c.validate(a.canonical(self.v if value is None else value),**args)

    def test_real_original_artifact_descriptor_round_trip(self):
        self.assertEqual(self.validate(),self.v)
        self.assertGreater(len(self.v['files']),3)
        self.assertTrue(any(f['mime_type']=='application/pdf' for f in self.v['files']))
        self.assertEqual(self.v['summary'],self.fixture['releases'][0])
        self.assertNotEqual(self.v['summary']['artifact_sha256'],self.fixture['releases'][1]['artifact_sha256'])

    def test_cold_native_include_has_dependencies_without_runtime_or_database(self):
        r=subprocess.run([shutil.which('php'),str(ROOT/'tests/WebsitePlatformM6ECheckpointTest.php'),'--cold'],capture_output=True,timeout=15,check=True)
        self.assertEqual(r.stdout.strip(),b'checkpoint cold include PASS')
        self.assertEqual(r.stderr,b'')

    def test_identity_substitutions_reject_even_with_rehashed_observation(self):
        for path,value in ((('database','schema'),'other'),(('database','server_uuid'),'other'),(('database','account'),'other'),
            (('source','site','id'),11),(('source','site','current_published_revision_id'),100),
            (('source','revision','id'),101),(('source','revision','snapshot_hash'),'0'*64),
            (('source','revision','source_references_json'),'{"business":{"business_id":51}}'),
            (('source','approvals','internal_approval_id'),999),(('approvals','internal_approval_id','revoked_at'),'revoked'),
            (('approvals','customer_approval_id','site_id'),11),(('release','id'),999),(('release','release_key'),'substituted'),
            (('release','artifact_hash'),'0'*64),(('job','builder_code_sha'),'f'*40),(('producer','id'),999),
            (('newer_revisions',),[101]),(('absence','site_deployment_attempts'),1)):
            with self.subTest(path=path):
                v=copy.deepcopy(self.v);node=v['observed']
                for key in path[:-1]:node=node[key]
                node[path[-1]]=value;v['observed_sha256']=a.digest(a.canonical(v['observed']))
                with self.assertRaises((ValueError,TypeError,KeyError)):self.validate(v)

    def test_artifacts_source_and_original_fixture_substitutions_reject(self):
        for change in (lambda v:v['files'][0].update(sha256='0'*64),lambda v:v['summary'].update(release_id=999),
            lambda v:v.update(manifest_base64='e30='),lambda v:v['source_files'].update({c.SOURCE_FILES[0]:'f'*64}),
            lambda v:v['binding'].update(source_sha='f'*40),lambda v:v.update(creation_point='after-B')):
            v=copy.deepcopy(self.v);change(v)
            with self.assertRaises((ValueError,TypeError,KeyError)):self.validate(v)
        wrong=copy.deepcopy(self.fixture);wrong['releases'].reverse()
        with self.assertRaises(ValueError):self.validate(fixture=wrong)
        # Consistently rewriting every SQL reference still cannot change the original sealed manifest.
        v=copy.deepcopy(self.v);o=v['observed'];o['source']['revision']['id']=101;o['release']['source_revision_id']=101
        o['job']['revision_id']=101;o['approvals']['internal_approval_id']['revision_id']=101
        v['observed_sha256']=a.digest(a.canonical(o))
        with self.assertRaisesRegex(ValueError,'checkpoint_manifest_source'):self.validate(v)

    def test_absent_stale_incomplete_ambiguous_and_false_native_claims(self):
        for key in self.v:
            v=copy.deepcopy(self.v);del v[key]
            with self.subTest(key=key),self.assertRaises(ValueError):self.validate(v)
        with self.assertRaises(ValueError):self.validate(now=self.v['expires_at'])
        with self.assertRaises(ValueError):self.validate(now=self.v['started_at']-1)
        with self.assertRaises(ValueError):self.validate(execution='native-pre-b')
        with self.assertRaises(ValueError):a.decode(b'{"profile":1,"profile":2}')
        with self.assertRaises(ValueError):c.validate(b'{}',self.config,self.database,self.fixture,self.source,'controlled-local',int(time.time()))

    def test_descriptor_cannot_assert_restoration_or_authority(self):
        for change in (lambda v:v.update(database_snapshot='COMPLETE'),lambda v:v.update(deployment_authority='APPROVED'),
            lambda v:v['lifecycle'].update(restore='COMPLETE'),lambda v:v['lifecycle'].update(schema=self.database['schema'])):
            v=copy.deepcopy(self.v);change(v)
            with self.assertRaises(ValueError):self.validate(v)
        self.assertEqual(self.v['lifecycle']['restore'],'UNIMPLEMENTED')

class Ownership(unittest.TestCase):
    host=bootstrap_controls.Controls.host
    armed=bootstrap_controls.Controls.armed
    ready=bootstrap_controls.Controls.ready

    def verify(self,host,ledger,plan):return c.verify(host,ledger,plan,host.built,ready=True)

    def test_ready_original_fixture_and_no_publisher_effects(self):
        host,ledger,plan,record=self.ready()
        self.assertEqual(self.verify(host,ledger,plan),record['inspected']['pre_b_source_sha256'])
        self.assertEqual(a.decode(host.fs.read(p.CONTROL+'/fixture.json')),host.built)
        self.assertEqual(record['inspected']['publisher_operations'],0)
        self.assertEqual(record['host_receipt'],'ABSENT')
        self.assertFalse(host.fs.exists(p.CONFIG+'/host-receipt.json'))
        journal=p.template_values(host.built['site_key'])['EXACT_PRIVATE_JOURNAL_ROOT']
        self.assertEqual(a.decode(host.fs.read(journal+'/state.json')),p.PRISTINE)
        from WebsitePlatformM6ECommissionEvidence import MISSING
        self.assertEqual({k for k,v in MISSING.items() if v},{'apache','assets','runtime','health','recovery','provisioning'})
        self.assertEqual(a.GATES,('apache','filesystem','ownership','assets','runtime','health','recovery','provisioning'))

    def test_missing_replaced_and_changed_checkpoint_reject(self):
        host,ledger,plan,_=self.ready();path=host.fs.path(c.PATH);raw=path.read_bytes()
        path.write_bytes(raw+b' ')
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)
        path.write_bytes(raw);self.verify(host,ledger,plan)
        original=host.fs.identity(c.PATH)
        replacement=c.PATH+'.replacement'
        host.put(replacement,raw)
        allocated=host.fs.identity(replacement)
        self.assertNotEqual((original['dev'],original['ino']),(allocated['dev'],allocated['ino']))
        self.assertEqual({k:v for k,v in original.items() if k not in ('dev','ino')},
            {k:v for k,v in allocated.items() if k not in ('dev','ino')})
        host.fs.unlink(c.PATH)
        with self.assertRaises(FileNotFoundError):self.verify(host,ledger,plan)
        host.fs.path(replacement).replace(path)
        installed=host.fs.identity(c.PATH)
        self.assertEqual((installed['dev'],installed['ino']),(allocated['dev'],allocated['ino']))
        self.assertNotEqual((installed['dev'],installed['ino']),(original['dev'],original['ino']))
        self.assertEqual(path.read_bytes(),raw)
        with self.assertRaisesRegex(ValueError,'owned_resource_replaced'):self.verify(host,ledger,plan)

    def test_archive_tampering_and_incomplete_publication_reject(self):
        host,ledger,plan,_=self.ready();path=host.fs.path(ledger.root+'/pre-b-source.json');raw=path.read_bytes()
        path.write_bytes(raw+b' ')
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)
        path.write_bytes(raw);saved=ledger.state['observations'].pop('pre_b_source')
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)
        ledger.state['observations']['pre_b_source']=saved;ledger.state['phase']='inspection'
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)

    def test_interrupted_worker_capture_is_owned_and_never_complete(self):
        host=self.host();ledger,plan=self.armed(host);put=host.put
        def partial(path,raw=b'',*args,**kwargs):
            if path==c.PATH:
                put(path,raw[:17],*args,**kwargs);raise Failure('checkpoint_interrupted_writer')
            return put(path,raw,*args,**kwargs)
        with mock.patch.object(host,'put',side_effect=partial),self.assertRaisesRegex(Failure,'checkpoint_interrupted_writer'):
            b.prepare_owned(host,ledger,plan)
        self.assertFalse(host.fs.exists(c.PATH));self.assertFalse(host.fs.exists(ledger.root+'/pre-b-source.json'))
        self.assertEqual(next(r for r in ledger.state['entries'] if r['path']==c.PATH)['state'],'RELEASED')
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)

    def test_absent_worker_capture_fails_before_bootstrap_ready(self):
        host=self.host();ledger,plan=self.armed(host);put=host.put
        with mock.patch.object(host,'put',side_effect=lambda path,*args,**kw:None if path==c.PATH else put(path,*args,**kw)),self.assertRaises(ValueError):
            b.prepare_owned(host,ledger,plan)
        self.assertFalse(host.fs.exists(ledger.root+'/bootstrap.json'))
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)

    def test_uncertain_archive_and_failed_cleanup_cannot_claim_completed_evidence(self):
        host=self.host();ledger,plan=self.armed(host);publish=ledger.publish
        def interrupted(stem,value):
            result=publish(stem,value)
            if stem=='pre-b-source':raise Failure('checkpoint_archive_ack_lost')
            return result
        host.cleanup_fault=True
        with mock.patch.object(ledger,'publish',side_effect=interrupted),self.assertRaisesRegex(Failure,'checkpoint_archive_ack_lost'):
            b.prepare_owned(host,ledger,plan)
        self.assertTrue(host.fs.exists(ledger.root+'/pre-b-source.json'))
        self.assertEqual(ledger.state['phase'],'cleanup-limited');self.assertNotIn('pre_b_source',ledger.state['observations'])
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)

    def test_completed_cleanup_preserves_history_without_live_readiness(self):
        host,ledger,plan,_=self.ready();raw=host.fs.read(ledger.root+'/pre-b-source.json')
        self.assertEqual(b.teardown(host,ledger)['result'],'REMOVED')
        self.assertEqual(host.fs.read(ledger.root+'/pre-b-source.json'),raw)
        self.assertFalse(host.fs.exists(c.PATH))
        with self.assertRaises(ValueError):self.verify(host,ledger,plan)

if __name__=='__main__':unittest.main()
