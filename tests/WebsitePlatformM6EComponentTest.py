"""One actual runner, distinct PHP worker / Python helper / Python observer.

Windows Unix identities/permissions, sockets, pointer and flock syscalls are
explicit models. Artifact bytes, processes, framing, validation and storage are real.
"""
import copy
import os
from pathlib import Path
import shutil
import stat
import sys
import tempfile
import time
import unittest
from types import SimpleNamespace

ROOT=Path(__file__).resolve().parents[1]
sys.path[:0]=[str(ROOT/'tests'),str(ROOT/'tests/support')]
from WebsitePlatformM6EBootstrapControlTest import PrivateFiles,config,fixture
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EAcceptance import canonical,decode,digest,need,ROLES
from WebsitePlatformM6EProcess import Owner,Failure
from WebsitePlatformM6EComponentHost import Native,snapshot
from WebsitePlatformM6EComponentModel import Model,Mapped,info
from WebsitePlatformM6ECoordinator import require_native_ready
import WebsitePlatformM6EComponent as c
import WebsitePlatformM6EBootstrapPlan as b
import WebsitePlatformM6ECommissionEvidence as evidence

class Local(Native):
    execution='controlled-local'
    def __init__(self,root,fault=''):
        self.root=Path(root);self.fs=PrivateFiles(root);cfg=config()
        for name in (b.CONTROL,b.STORAGE,b.EVIDENCE):self.fs.makedirs(name)
        self.fs.metadata(b.EVIDENCE,0,0,0o700)
        self.ledger=Ledger.create(self.fs,cfg);self.host=SimpleNamespace(owner=Owner(time.monotonic()+60))
        self.plan=c.plan(cfg,fixture());self.module=c.publisher_module();self.deadline=time.monotonic()+45
        self.active=[];self.rows=[];self.manifest=None;self.stopped=False;self.fault=fault;self.events=[]
        self.model=self.root/'model.json';self.scratch=self.root/'php';self.scratch.mkdir()
        self.original=self.root/'operational';self.original.mkdir()
        for name,raw in (('journal',canonical(b.PRISTINE)),('target',canonical(dict(current=None,candidate=None))),
                         ('database',canonical(dict(deployments=[],success=[],target_pointer=None)))):
            (self.original/name).write_bytes(raw)
        self.before=self.operational()
        original_publish=self.ledger.publish
        def publish(stem,value):
            self.events.append(stem)
            if stem=='component-a-before-cleanup':
                self.captured=copy.deepcopy(value)
                journal=self.fs.path(self.plan['paths']['journal']+'/state.json')
                self.journal_before=journal.read_bytes() if journal.exists() else None
                if self.fault=='capture-failure':raise Failure('capture_injected')
            return original_publish(stem,value)
        self.ledger.publish=publish
    def operational(self):
        return {p.name:dict(ino=p.stat().st_ino,sha256=digest(p.read_bytes())) for p in sorted(self.original.iterdir())} if hasattr(self,'original') else {}
    def write_plan(self):pass # Original release is created once in the actual PHP child.
    def command(self,role):
        if self.fault=='startup' and role=='worker':return [str(self.root/'missing-executable')]
        if role=='worker':return [shutil.which('php'),'-d','display_errors=0','-d','log_errors=0','-d','memory_limit=256M',
            '-d','sys_temp_dir='+str(self.scratch).removeprefix('\\\\?\\'),str(ROOT/'tests/support/WebsitePlatformM6ETransferChild.php'),'component-a']
        return [sys.executable,'-I','-B',str(ROOT/'tests/support/WebsitePlatformM6EComponentModel.py'),role]
    def spawn_options(self,role):return dict(start_new_session=os.name!='nt')
    def start_value(self,role):
        if role=='worker':
            access={}
            for key,leaf in (('served','access-probe'),('journal','target.lock')):
                path=self.plan['paths'][key]+'/'+leaf;item=self.fs.identity(path)
                access[key]=dict(path=str(self.fs.path(path)).removeprefix('\\\\?\\'),uid=item['uid'],mode=item['mode'])
            if self.fault=='worker-access':access['served']['mode']|=0o002
            return dict(command='inspect',access=access)
        return dict(command=role,root=str(self.root),model=str(self.model),plan=self.plan,fault=self.fault,
            worker_pid=self.active[0]['process'].pid,source=dict(path=self.source_file,uid=2000,mode=0o604 if self.fault=='helper-access' else 0o600))
    def source(self,worker):
        value=self.receive(worker,'source');self.source_file=value['local_source'];original=value['source']
        local=dict(site_id=original['release']['site_id'],site_key=original['release']['site_key'],target_id=20,releases=[original['summary']])
        self.plan=c.plan(self.ledger.config,local,self.plan['expires_at'])
        self.write(self.plan['paths']['plan'],canonical(self.plan),0,0,0o644)
        need(value['uid']==2000 and value['write_denied']==dict(served=1,journal=1),'component_worker_permissions')
        self.state['observations']['worker_access']=dict(uid=2000,write_denied=value['write_denied']);self.ledger.save()
        self.save_model()
        if self.fault=='producer':original['summary']['producer_attempt_id']+=1
        return original
    def save_model(self):
        meta={}
        for row in self.rows:
            if row['identity'] is None:continue
            value=self.fs.identity(row['path']);meta[str(self.fs.path(row['path']))]=[value['mode'],value['uid'],value['gid']]
        self.model.write_bytes(canonical(dict(meta=meta,links={})))
    def load_model(self):
        if not self.model.exists():return None
        ops=Model(self.root,self.model)
        for path,item in ops.meta.items():
            p=Path(path)
            if p.exists():
                s=p.lstat();self.fs.meta[s.st_dev,s.st_ino]=dict(mode=item[0],uid=item[1],gid=item[2])
        leaf=self.fs.path(self.plan['paths']['socket'])
        if leaf.exists():
            s=leaf.stat();self.fs.meta[s.st_dev,s.st_ino]['kind']=stat.S_IFSOCK
        return ops
    def ready(self,helper):
        value=super().ready(helper);self.endpoint=value['endpoint'];self.load_model();return value
    def transfer(self,worker,helper,note):
        # Same runner and native transport supervision; only control endpoint differs.
        send=worker.send
        worker.send=lambda value:send(value|dict(endpoint=self.endpoint,plan=self.plan,fault=self.fault))
        try:return super().transfer(worker,helper,note)
        finally:self.load_model()
    def observer(self):
        self.load_model()
        if self.fault in ('extra','foreign','hardlink','bytes'):
            path=self.fs.path(self.plan['paths']['served']+'/releases/'+self.plan['release']['release_key']+'/public/index.html')
            if self.fault=='extra':(path.parent/'unknown.txt').write_bytes(b'foreign')
            elif self.fault=='bytes':path.write_bytes(b'changed')
            elif self.fault=='hardlink':os.link(path,path.parent/'alias.html')
            else:
                data=decode(self.model.read_bytes());data['meta'][str(path)][1]=999;self.model.write_bytes(canonical(data))
        return super().observer()
    def stop(self):self.events.append('stop');super().stop();self.load_model()
    def info(self,fs,path):
        ops=Model(self.root,self.model) if self.model.exists() else None
        if ops:
            mapped=Mapped(self.root,ops)
            result=info(mapped,path)
            if path==self.plan['paths']['socket']:result['kind']=stat.S_IFSOCK
            return result
        return fs.identity(path)|dict(nlink=fs.path(path).lstat().st_nlink)
    def cleanup(self,owned):
        self.events.append('remove')
        if self.fault=='cleanup-failure':raise Failure('cleanup_injected')
        if self.fault=='replaced':
            path=self.fs.path(self.plan['paths']['served']+'/access-probe');path.rename(path.with_name('replaced-old'));path.write_bytes(b'component access\n')
        super().cleanup(owned)

class ComponentTests(unittest.TestCase):
    def execute(self,fault='',check=None):
        root='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-ca-',dir=root) as directory:
            backend=Local(directory,fault);error=None
            try:c.run(backend)
            except BaseException as caught:error=caught
            state=backend.ledger.state['observations']['component_a']
            if check:check(backend,state,error)
            self.assertEqual(backend.operational(),backend.before)
            self.assertTrue(all(x['process'].poll() is not None for x in backend.active))
            return copy.deepcopy(state),error
    def test_original_transfer_stage_independent_observer_cleanup(self):
        def check(x,s,error):
            if error:raise error
            self.assertEqual(s['cleanup'],'REMOVED');self.assertEqual(s['result'],'STAGED_AND_REMOVED')
            self.assertEqual(len({ch['pid'] for ch in s['children']}),3)
            self.assertEqual([ch['uid'] for ch in s['children']],[2000,2102,2102])
            self.assertEqual(s['observations']['source']['producer_attempt_id'],x.plan['summary']['producer_attempt_id'])
            observed=s['observations']['observer'];self.assertEqual(observed['fence'],1);self.assertEqual(observed['phase'],1)
            self.assertTrue(any(f['mime_type']=='application/pdf' for f in observed['files']))
            for f in observed['files']:
                self.assertEqual(f['observed_sha256'],f['sha256']);self.assertEqual(f['observed_bytes'],f['byte_size'])
            self.assertEqual(s['observations']['receiver']['manifest_sha256'],observed['manifest_sha256'])
            self.assertLess(x.events.index('component-a-before-cleanup'),x.events.index('stop'))
            self.assertLess(x.events.index('component-a-owned-before-remove'),x.events.index('remove'))
            self.assertTrue(all(r['state']=='RELEASED' for r in x.rows))
            self.assertEqual(decode(x.journal_before)['fence'],1) # discarded, never rewound
            with self.assertRaisesRegex(ValueError,'component_already_attempted'):c.run(x)
        self.execute(check=check)
    def test_original_producer_and_source_changes(self):
        for fault in ('producer','source-change','source-extra'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault);self.assertIsNotNone(error);self.assertEqual(state['result'],'INCOMPLETE')
                self.assertNotIn('observer',state['observations'])
    def test_modeled_access_is_required_in_both_processes(self):
        for fault in ('worker-access','helper-access'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault);self.assertIsNotNone(error);self.assertNotIn('observer',state['observations'])
    def test_corrupt_manifest_body_and_truncated_stream(self):
        for fault in ('manifest-corrupt','body-corrupt','truncated'):
            with self.subTest(fault=fault):
                def check(x,s,error):
                    self.assertIsNotNone(error);self.assertNotIn('observer',s['observations'])
                    self.assertEqual(decode(x.journal_before)['fence'],1)
                    self.assertEqual(decode(x.journal_before)['phase'],0)
                self.execute(fault,check)
    def test_wrong_peer_uid_or_pid_cannot_advance_fence(self):
        for fault in ('peer-uid','peer-pid'):
            with self.subTest(fault=fault):
                def check(x,s,error):
                    self.assertIsNotNone(error);self.assertEqual(decode(x.journal_before),b.PRISTINE)
                    self.assertNotIn('receiver',s['observations'])
                self.execute(fault,check)
    def test_startup_or_helper_death(self):
        for fault in ('startup','helper-death','helper-stage-death'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault);self.assertIsNotNone(error);self.assertEqual(state['result'],'INCOMPLETE')
                if fault=='helper-stage-death':self.assertEqual(state['cleanup'],'LIMITED')
    def test_independent_bytes_inventory_foreign_hardlink_denied(self):
        for fault in ('bytes','extra','foreign','hardlink'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault);self.assertIsNotNone(error);self.assertNotIn('observer',state['observations'])
                if fault!='bytes':self.assertEqual(state['cleanup'],'LIMITED')
    def test_replaced_path_and_cleanup_failure_are_retained(self):
        for fault in ('replaced','cleanup-failure','capture-failure'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault);self.assertIsNotNone(error);self.assertEqual(state['cleanup'],'LIMITED')
    def test_first_error_survives_cleanup_and_result_write_errors(self):
        root='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-ca-',dir=root) as directory:
            x=Local(directory,'producer');original=x.ledger.publish
            x.cleanup=lambda _:(_ for _ in ()).throw(Failure('cleanup_injected'))
            x.ledger.publish=lambda stem,value:(_ for _ in ()).throw(Failure('result_injected')) if stem=='component-a-result' else original(stem,value)
            with self.assertRaisesRegex(ValueError,'component_original_producer'):c.run(x)
            self.assertEqual(x.ledger.state['observations']['component_a']['cleanup'],'LIMITED')
    def test_synthetic_authority_cannot_activate_or_change_release(self):
        plan=c.plan(config(),fixture());authority=c.Authority(plan)
        for key in ('operation','release'):
            request=copy.deepcopy(plan['request'])
            if key=='operation':request['operation']='activate'
            else:request['payload']['release']['release_id']+=1
            with self.assertRaises(ValueError):authority.observe(request)
    def test_controlled_result_cannot_authorize_native_commissioning(self):
        self.assertTrue(all(evidence.MISSING[g] for g in ('apache','assets','runtime','health','recovery','provisioning')))
        with self.assertRaisesRegex(Failure,'native_implementation_incomplete'):require_native_ready()
        import subprocess
        child=subprocess.run([sys.executable,'-I','-B',str(ROOT/'tests/support/WebsitePlatformM6EComponentChild.py'),'--helper'],
            input=b'',stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=5)
        self.assertNotEqual(child.returncode,0)

if __name__=='__main__':unittest.main(verbosity=2)
