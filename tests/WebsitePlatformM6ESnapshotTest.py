"""Private cross-language data controls + real local file ownership. Native SQL remains unexecuted."""
import copy
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
import unittest
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
sys.path.insert(0,str(Path(__file__).resolve().parent))
import WebsitePlatformM6EAcceptance as a
import WebsitePlatformM6ESnapshot as s
import WebsitePlatformM6ECheckpoint as c
import WebsitePlatformM6EBootstrap as b
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6EBootstrapControlTest as controls
from WebsitePlatformM6EProcess import Failure

ROOT=Path(__file__).resolve().parents[1]

class Integrity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        child=subprocess.run([shutil.which('php'),'-d','display_errors=0','-d','log_errors=0',str(ROOT/'tests/WebsitePlatformM6ESnapshotTest.php'),'--bundle'],
            cwd=ROOT,capture_output=True,timeout=60,check=True)
        cls.bundle=a.decode(child.stdout);cls.v=cls.bundle['capture'];cls.baseline=cls.bundle['baseline'];cls.cp=cls.bundle['checkpoint'];cls.config=cls.bundle['config']
        paths=[*list((ROOT/'database/migrations').glob('*.sql')),*[ROOT/n for n in (*s.SOURCE_FILES,*c.SOURCE_FILES)]]
        cls.source={path.relative_to(ROOT).as_posix():dict(sha256=a.digest(path.read_bytes())) for path in paths}

    def validate(self,v=None,baseline=None,cp=None,**kwargs):
        args=dict(config=self.config,source=self.source,execution='controlled-local',now=int(time.time()));args.update(kwargs)
        return s.validate(a.canonical(self.v if v is None else v),a.canonical(self.baseline if baseline is None else baseline),a.canonical(self.cp if cp is None else cp),**args)

    @staticmethod
    def rehash(table):table.update(row_count=len(table['rows']),rows_sha256=a.digest(a.canonical(table['rows'])))

    def cell(self,v,table,column,value,row=0):
        t=v['tables'][table];t['rows'][row][t['columns'].index(column)]=None if value is None else str(value).encode().hex().upper();self.rehash(t)

    def test_original_checkpoint_artifacts_and_relational_bindings(self):
        record=self.validate();original=record['original']
        self.assertEqual(original['approvals'],self.cp['observed']['source']['approvals'])
        self.assertEqual(original['producer_attempt_id'],self.cp['summary']['producer_attempt_id'])
        self.assertEqual(original['release_id'],self.cp['summary']['release_id'])
        self.assertEqual(len(record['tables']),90)
        self.assertEqual(record['capture'],'CAPTURED');self.assertEqual(record['execution'],'controlled-local')
        self.assertEqual(self.cp['database_snapshot'],'UNIMPLEMENTED')
        self.assertNotIn('private-fixture',a.canonical(record).decode())

    def test_fixed_inventory_matches_immutable_migration_create_inventory(self):
        names=set()
        for path in (ROOT/'database/migrations').glob('*.sql'):
            names.update(re.findall(r'CREATE TABLE (?:IF NOT EXISTS )?`?([a-zA-Z0-9_]+)',path.read_text()))
        self.assertEqual(sorted(names),s.PROFILE['tables'])
        self.assertEqual(len(self.baseline['migrations']),25)

    def test_exact_null_empty_unicode_decimal_and_binary_values(self):
        t=self.v['tables']['notes'];row=dict(zip(t['columns'],t['rows'][0]))
        self.assertIsNone(row['nullable']);self.assertEqual(row['empty'],'')
        self.assertEqual(bytes.fromhex(row['amount']),b'123.4500')
        self.assertEqual(bytes.fromhex(row['body']),'private-fixture-\x00-Café'.encode())
        self.assertEqual(self.v['tables']['site_deployments']['rows'],[])

    def test_missing_extra_and_malformed_tables(self):
        for change in (lambda v:v['tables'].pop('notes'),lambda v:v['tables'].update(foreign=v['tables']['notes']),
            lambda v:v['tables']['notes']['columns'].append('id'),lambda v:v['tables']['notes'].update(create_table='wrong')):
            v=copy.deepcopy(self.v);change(v)
            with self.assertRaises(ValueError):self.validate(v)

    def test_missing_and_duplicate_rows(self):
        for table in ('site_approvals','site_releases','site_build_jobs','site_build_attempts'):
            for duplicate in (False,True):
                v=copy.deepcopy(self.v);t=v['tables'][table]
                if duplicate:t['rows'].append(t['rows'][0])
                else:t['rows'].pop()
                self.rehash(t)
                with self.subTest(table=table,duplicate=duplicate),self.assertRaises(ValueError):self.validate(v)

    def test_foreign_closure_and_scope(self):
        v=copy.deepcopy(self.v);self.cell(v,'site_approvals','revision_id',999)
        with self.assertRaisesRegex(ValueError,'snapshot_foreign_closure'):self.validate(v)
        baseline=copy.deepcopy(self.baseline);baseline['tables']['notes']['foreign_keys']=[dict(table='foreign.canary',columns=['id'],references=['id'])]
        v=copy.deepcopy(self.v);v['schema_sha256']=a.digest(a.canonical(baseline))
        with self.assertRaisesRegex(ValueError,'snapshot_foreign_scope'):self.validate(v,baseline=baseline)

    def test_original_approval_source_producer_and_association_changes(self):
        for table,column,value in (('site_approvals','actor_type','other'),('site_revisions','snapshot_hash','0'*64),
            ('site_build_attempts','status','running'),('site_business_associations','business_id',999),('site_assets','checksum_sha256','f'*64)):
            v=copy.deepcopy(self.v);self.cell(v,table,column,value)
            with self.subTest(table=table,column=column),self.assertRaises(ValueError):self.validate(v)

    def test_B_cannot_be_present_and_no_target_or_deployment(self):
        v=copy.deepcopy(self.v);t=v['tables']['site_revisions'];row=t['rows'][0].copy();t['rows'].append(row)
        self.cell(v,'site_revisions','id',999,len(t['rows'])-1);self.cell(v,'site_revisions','revision_number',99,len(t['rows'])-1)
        with self.assertRaisesRegex(ValueError,'snapshot_B_present'):self.validate(v)
        for name in c.TABLES:
            v=copy.deepcopy(self.v);t=v['tables'][name];t['rows']=[['31','78']];self.rehash(t)
            with self.assertRaisesRegex(ValueError,'snapshot_operations'):self.validate(v)

    def test_corruption_truncation_count_and_digest(self):
        for change in (lambda v:v['tables']['notes'].update(rows_sha256='0'*64),lambda v:v['tables']['notes'].update(row_count=99),
            lambda v:v.update(checkpoint_sha256='0'*64),lambda v:v.update(schema_sha256='0'*64)):
            v=copy.deepcopy(self.v);change(v)
            with self.assertRaises(ValueError):self.validate(v)
        with self.assertRaises(ValueError):s.validate(b'{',b'{}',b'{}',self.config,self.source,'controlled-local',int(time.time()))
        with self.assertRaises(ValueError):a.decode(b'{"profile":1,"profile":2}')

    def test_identity_and_transaction_substitution(self):
        for change in (lambda v:v['database'].update(schema='foreign'),lambda v:v['database'].update(server_uuid='other'),
            lambda v:v['reader'].update(account='m6e_worker@127.0.0.1'),lambda v:v['reader'].update(connection_id=1),
            lambda v:v['reader'].update(tls=False),lambda v:v['transaction'].update(boundaries=90),
            lambda v:v['transaction'].update(isolation='READ COMMITTED'),lambda v:v['transaction'].update(read_only=False),
            lambda v:v['transaction'].update(ddl_guard='NONE'),lambda v:v['transaction'].update(closed=False),lambda v:v.update(execution='native-pre-b')):
            v=copy.deepcopy(self.v);change(v)
            with self.subTest(change=change),self.assertRaises(ValueError):self.validate(v)

    def test_stale_incomplete_and_authority_claims(self):
        with self.assertRaisesRegex(ValueError,'snapshot_stale'):self.validate(now=self.v['expires_at'])
        for key in ('deployment_authority','restore','admission'):
            v=copy.deepcopy(self.v);v[key]='PASS'
            with self.assertRaisesRegex(ValueError,'snapshot_not_authority'):self.validate(v)
        for key in self.v:
            v=copy.deepcopy(self.v);del v[key]
            with self.assertRaises(ValueError):self.validate(v)

    def test_migration_substitution(self):
        source=copy.deepcopy(self.source);name=next(iter(self.baseline['migrations']));source[name]['sha256']='f'*64
        with self.assertRaisesRegex(ValueError,'snapshot_migration_identity'):self.validate(source=source)

    def test_generated_values_and_original_auto_increment_definition_retained(self):
        baseline=copy.deepcopy(self.baseline);v=copy.deepcopy(self.v);schema=baseline['tables']['notes'];table=v['tables']['notes']
        schema['columns'].append('generated_value');table['columns'].append('generated_value')
        schema['definition']=schema['definition'].replace(') ENGINE=InnoDB',", `generated_value` int GENERATED ALWAYS AS (1) STORED) ENGINE=InnoDB")
        table['create_table']=schema['definition'].replace(') ENGINE=InnoDB',') ENGINE=InnoDB AUTO_INCREMENT=7 ')
        # Real SHOW CREATE has options after the counter; keep that shape in this model.
        schema['definition']+=' DEFAULT CHARSET=utf8mb4';table['create_table']+='DEFAULT CHARSET=utf8mb4'
        for row in table['rows']:row.append('31')
        self.rehash(table);v['schema_sha256']=a.digest(a.canonical(baseline))
        record=self.validate(v,baseline=baseline)
        self.assertEqual(record['tables']['notes']['rows'],1)
        self.assertIn('AUTO_INCREMENT=7',v['tables']['notes']['create_table'])

    def test_serialization_and_cell_limits(self):
        v=copy.deepcopy(self.v);t=v['tables']['notes'];t['rows'][0][0]='FF'*(s.PROFILE['max_cell_bytes']+1);self.rehash(t)
        with self.assertRaisesRegex(ValueError,'snapshot_cell'):self.validate(v)
        with self.assertRaisesRegex(ValueError,'snapshot_size'):
            s.validate(b'x'*(s.PROFILE['max_artifact_bytes']+1),b'{}',b'{}',self.config,self.source,'controlled-local',int(time.time()))

class Ownership(unittest.TestCase):
    host=controls.Controls.host
    armed=controls.Controls.armed
    ready=controls.Controls.ready

    def test_failed_outcome_write_preserves_safe_primary_and_secondary_from_child(self):
        host=self.host();ledger,plan=self.armed(host)
        outcome=dict(capture='FAILED',verification='UNREACHED',transaction_cleanup='UNRESOLVED',
            first_failure='snapshot_rows',secondary=['snapshot_rollback','snapshot_unlock','snapshot_outcome_write'])
        host.execute=lambda *args,**kwargs:dict(exit=1,pid=900,stdout=b'',stderr=b'm6e_snapshot_outcome:'+a.canonical(outcome)+b'\n')
        with self.assertRaisesRegex(Failure,'base_command_failed'):s.capture(host,lambda:host.setup('build-two','worker'))
        self.assertEqual(ledger.state['observations']['pre_b_database_outcome'],outcome)
        self.assertNotIn('pre_b_database',ledger.state['observations'])

    def test_arbitrary_child_diagnostics_never_enter_evidence(self):
        host=self.host();ledger,plan=self.armed(host)
        host.execute=lambda *args,**kwargs:dict(exit=1,pid=900,stdout=b'PRIVATE_DATABASE_CANARY',stderr=b'm6e_snapshot_outcome:{"private":"PRIVATE_DATABASE_CANARY"}')
        with self.assertRaisesRegex(Failure,'base_command_failed'):host.setup('build-two','worker')
        self.assertNotIn('pre_b_database_outcome',ledger.state['observations'])
        self.assertNotIn(b'PRIVATE_DATABASE_CANARY',a.canonical(ledger.public()))
        self.assertTrue(ledger.state['secondary'])

    def test_bootstrap_verification_preserves_fixture_and_closed_authority(self):
        host,ledger,plan,record=self.ready()
        self.assertEqual(s.verify(host,ledger,plan,host.built),record['inspected']['pre_b_database_sha256'])
        self.assertEqual(a.decode(host.fs.read(p.CONTROL+'/fixture.json')),host.built)
        self.assertEqual(record['inspected']['publisher_operations'],0);self.assertEqual(record['host_receipt'],'ABSENT')
        for path in s.PATHS:
            identity=host.fs.identity(path);self.assertEqual((identity['uid'],identity['gid'],identity['mode']),(0,0,0o600))
        saved=copy.deepcopy(ledger.state['observations']['pre_b_database_outcome'])
        ledger.state['observations']['pre_b_database_outcome']['first_failure']='snapshot_outcome_write'
        with self.assertRaisesRegex(ValueError,'snapshot_outcome_mismatch'):s.verify(host,ledger,plan,host.built)
        ledger.state['observations']['pre_b_database_outcome']=saved
        public=a.canonical(ledger.public());self.assertNotIn(b'private-fixture',public);self.assertNotIn(b'SECRET_SNAPSHOT_CANARY',public)
        from WebsitePlatformM6ECommissionEvidence import MISSING
        self.assertEqual({k for k,v in MISSING.items() if v},{'apache','assets','runtime','health','recovery','provisioning'})

    def test_snapshot_substitution_even_with_identical_bytes(self):
        host,ledger,plan,_=self.ready();path=host.fs.path(s.PATH);raw=path.read_bytes();old=host.fs.identity(s.PATH)
        host.put(s.PATH+'.replacement',raw);new=host.fs.identity(s.PATH+'.replacement')
        self.assertNotEqual((old['dev'],old['ino']),(new['dev'],new['ino']))
        host.fs.unlink(s.PATH);host.fs.path(s.PATH+'.replacement').replace(path)
        with self.assertRaisesRegex(ValueError,'owned_resource_replaced'):s.verify(host,ledger,plan,host.built)

    def test_corrupt_missing_and_unverified_files(self):
        host,ledger,plan,_=self.ready()
        for name in s.PATHS:
            path=host.fs.path(name);raw=path.read_bytes();path.write_bytes(raw+b' ')
            with self.assertRaises(ValueError):s.verify(host,ledger,plan,host.built)
            path.write_bytes(raw)
        host.fs.unlink(s.RECORD)
        with self.assertRaises((ValueError,FileNotFoundError)):s.verify(host,ledger,plan,host.built)

    def test_interrupted_private_capture_is_owned_and_removed(self):
        host=self.host();ledger,plan=self.armed(host);put=host.put
        def partial(path,raw=b'',*args,**kwargs):
            if path==s.PATH:
                put(path,raw[:17],*args,**kwargs);raise Failure('snapshot_interrupted')
            return put(path,raw,*args,**kwargs)
        with mock.patch.object(host,'put',side_effect=partial),self.assertRaisesRegex(Failure,'snapshot_interrupted'):b.prepare_owned(host,ledger,plan)
        self.assertFalse(host.fs.exists(s.PATH));self.assertFalse(host.fs.exists(ledger.root+'/bootstrap.json'))
        self.assertEqual(next(r for r in ledger.state['entries'] if r['path']==s.PATH)['state'],'RELEASED')

    def test_failure_record_never_becomes_verified(self):
        host=self.host();ledger,plan=self.armed(host);put=host.put
        def failed(path,raw=b'',*args,**kwargs):
            if path==s.OUTCOME:raw=a.canonical(dict(capture='FAILED',verification='UNREACHED',transaction_cleanup='UNRESOLVED',first_failure='snapshot_rows',secondary=['snapshot_unlock']))
            return put(path,raw,*args,**kwargs)
        with mock.patch.object(host,'put',side_effect=failed),self.assertRaisesRegex(ValueError,'snapshot_incomplete'):b.prepare_owned(host,ledger,plan)
        self.assertNotIn('pre_b_database',ledger.state['observations']);self.assertFalse(host.fs.exists(ledger.root+'/bootstrap.json'))

    def test_uncertain_publication_keeps_first_failure_and_cleanup_errors(self):
        host=self.host();ledger,plan=self.armed(host);publish=ledger.publish
        def uncertain(stem,value):
            result=publish(stem,value)
            if stem=='pre-b-database-verification':raise Failure('snapshot_archive_ack_lost')
            return result
        host.cleanup_fault=True
        with mock.patch.object(ledger,'publish',side_effect=uncertain),self.assertRaisesRegex(Failure,'snapshot_archive_ack_lost'):b.prepare_owned(host,ledger,plan)
        self.assertEqual(ledger.state['phase'],'cleanup-limited');self.assertIsNotNone(ledger.state['first_failure']);self.assertTrue(ledger.state['secondary'])
        self.assertNotIn('pre_b_database',ledger.state['observations'])

    def test_cleanup_removes_private_data_retains_only_verification_history(self):
        host,ledger,plan,_=self.ready();history=host.fs.read(ledger.root+'/pre-b-database-verification.json')
        self.assertEqual(b.teardown(host,ledger)['result'],'REMOVED')
        for path in (*s.PATHS,s.CREDENTIALS,s.SCHEMA):self.assertFalse(host.fs.exists(path))
        self.assertEqual(host.fs.read(ledger.root+'/pre-b-database-verification.json'),history)
        with self.assertRaises((ValueError,FileNotFoundError)):s.verify(host,ledger,plan,host.built)

if __name__=='__main__':unittest.main()
