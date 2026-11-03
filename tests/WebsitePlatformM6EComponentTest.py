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
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
sys.path[:0]=[str(ROOT/'tests'),str(ROOT/'tests/support')]
from WebsitePlatformM6EBootstrapControlTest import PrivateFiles,config,fixture
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EAcceptance import canonical,decode,digest,need,ROLES
from WebsitePlatformM6EProcess import Owner,Failure
from WebsitePlatformM6EComponentHost import Native,snapshot,serving_root,serving_config
from WebsitePlatformM6EComponentModel import Model,Mapped,info
from WebsitePlatformM6ECoordinator import require_native_ready
import WebsitePlatformM6EComponent as c
import WebsitePlatformM6EBootstrapPlan as b
import WebsitePlatformM6ECommissionEvidence as evidence

class Local(Native):
    serving_enabled=False # The baseline fault campaign remains stage-only.
    execution='controlled-local'
    def admit(self):pass # Explicit OS/supervisor model; never native evidence.
    def __init__(self,root,fault='',apache=False):
        self.root=Path(root);self.fs=PrivateFiles(root);cfg=config()
        for name in (b.CONTROL,b.STORAGE,b.EVIDENCE):self.fs.makedirs(name)
        self.fs.metadata(b.EVIDENCE,0,0,0o700)
        self.ledger=Ledger.create(self.fs,cfg);self.host=SimpleNamespace(owner=Owner(time.monotonic()+60))
        self.apache=apache;self.plan=c.plan(cfg,fixture(),apache=apache);self.module=c.publisher_module();self.deadline=time.monotonic()+45
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
            if stem==c.stem(self.plan)+'-before-cleanup':
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
        self.plan=c.plan(self.ledger.config,local,self.plan['expires_at'],apache=self.apache)
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

class ServingLocal(Local):
    serving_enabled=True
    def info(self,fs,path):
        if path==serving_root(self.plan) or path.startswith(serving_root(self.plan)+'/'):
            return fs.identity(path)|dict(nlink=fs.path(path).lstat().st_nlink)
        return super().info(fs,path)
    def configtest(self,note):
        # A real bounded private process; Linux Apache/config syntax is modeled.
        from WebsitePlatformM6EBootstrapHost import NativeHost
        self.host=NativeHost(self.fs,lambda args,seconds,**kwargs:self.host.owner.capture(
            [sys.executable,'-I','-B','-c','print("modeled configtest")'],seconds))
        self.host.ledger=self.ledger;self.host.check_pins=lambda config:None
        self.events.append('configtest');return super().configtest(note)
    def serving_checks(self,note):
        from WebsitePlatformM6ECommissionServing import CandidateChecks
        reader=dict(pid=self.reader['process'].pid,uid=[2104]*4,gid=[2104]*4,
            groups_sha256=digest(canonical([2104])),capabilities=0)
        note('serving_reader_before',reader) # Explicit modeled Unix credentials.
        CandidateChecks(self.manifest,self.serving_request,lambda value:note('serving',value)).run()
        note('serving_reader_after',reader)
    def sync_serving_model(self):
        data=decode(self.model.read_bytes())
        for row in self.serving_rows:
            if row['identity'] is not None:
                item=self.fs.identity(row['path'])
                data['meta'][str(self.fs.path(row['path']))]=[item['mode'],item['uid'],item['gid']]
        self.model.write_bytes(canonical(data))
    def serving_prepare(self):
        super().serving_prepare();self.sync_serving_model();self.requests=0
    def start_value(self,role):
        value=super().start_value(role)
        if role=='reader':value['manifest']=self.manifest
        if self.fault=='response-and-after-failure':value['fault']='wrong-mime'
        return value
    def reader_start(self):
        self.reader_admission();self.reader_attempted=True;self.events.append('reader-start')
        child=self.start('reader');self.reader=self.active[-1]
        value=self.receive(child,'reader');need(value['uid']==2104,'modeled_reader_role')
        self.fs.create(self.pid_row['path'],str(child.process.pid).encode()+b'\n',lambda fd:self.ledger.identify(self.pid_row,fd))
        self.fs.metadata(self.pid_row['path'],0,0,0o644);self.ledger.identify(self.pid_row);self.sync_serving_model()
    def serving_request(self,phase,path,method,variant):
        self.requests+=1
        if self.requests==1:
            public=self.plan['paths']['served']+'/releases/'+self.plan['release']['release_key']+'/public/index.html'
            output=self.fs.path(public)
            if self.fault in ('serve-same-inode-substitution','serve-corrupt','serve-missing'):
                raw=output.read_bytes();self.old_public=self.info(self.fs,public)
                output.rename(self.root/'held-public')
                if self.fault!='serve-missing':output.write_bytes(raw if self.fault=='serve-same-inode-substitution' else b'corrupt')
                if output.exists():self.new_public=self.info(self.fs,public)
            if self.fault=='candidate-absent':self.fs.path(self.plan['paths']['served']+'/candidate').unlink()
            if self.fault=='marker-missing':output.with_name('_release.json').unlink()
            if self.fault=='candidate-corrupt':
                data=decode(self.model.read_bytes());data['links'][str(self.fs.path(self.plan['paths']['served']+'/candidate'))]='wrong';self.model.write_bytes(canonical(data))
            if self.fault=='serving-extra':(self.fs.path(serving_root(self.plan))/'unknown').write_bytes(b'unknown')
            if self.fault=='serving-missing':self.fs.path(serving_root(self.plan)+'/canaries/m6e.php').unlink()
        child=self.reader['child']
        if self.fault=='reader-timeout':self.deadline=time.monotonic()+.15
        child.send(dict(command='request',path=path,method=method,variant=variant))
        return self.receive(child,'http')
    def observer(self):
        value=super().observer()
        if getattr(self,'serving_rows',None):
            if self.fault=='serving-inventory-missing':value.pop('serving_inventory',None)
            if self.fault=='serving-inventory-malformed':value['serving_inventory']=[]
            if 'serving_before' in self.state['observations']:
                if self.fault=='serving-after-missing':value.pop('serving_inventory',None)
                if self.fault in ('serving-after-malformed','response-and-after-failure'):value['serving_inventory']=[]
        return value
    def stop_reader(self):
        child=self.reader['child'];need(child.process.poll() is None,'component_reader_unknown_exit')
        child.send(dict(command='stop'))
        # Cleanup has its existing bounded reserve, independent of HTTP expiry.
        value=child.message(time.monotonic()+3);need(value['kind']=='stopped','component_reader_stop_ack')
        child.process.wait(timeout=3);need(child.process.returncode==0,'component_reader_exit')
        self.reader_clean=self.fault!='reader-unknown'
    def cleanup(self,owned):
        if self.fault=='serving-cleanup-replaced':
            output=self.fs.path(serving_root(self.plan)+'/apache.conf');raw=output.read_bytes()
            output.rename(self.root/'held-config');output.write_bytes(raw)
        super().cleanup(owned)
    def operational(self):
        value=super().operational()
        if getattr(self,'fault','')=='restoration-observation' and 'remove' in getattr(self,'events',[]) and not getattr(self,'restoration_fault',False):
            self.restoration_fault=True;return value|dict(restoration='incomplete')
        return value

class ReviewLocal(Local):
    """Inject only AFTER real observation, retaining the old inode outside output."""
    def __init__(self,root,fault):
        super().__init__(root,fault);self.injected=False;self.cleanup_errors=[];self.removals=[]
        original_owned=self.owned
        def owned():
            try:return original_owned()
            except BaseException as error:self.cleanup_errors.append(str(error));raise
        self.owned=owned
        for method in ('unlink','rmdir'):
            original=getattr(self.fs,method)
            def removed(path,original=original):self.removals.append(path);return original(path)
            setattr(self.fs,method,removed)
        original_capture=self.ledger.capture_tree
        def capture(row,*args,**kwargs):
            if fault=='capture-race' and row['path']==self.plan['paths']['served']:self.inject('same')
            return original_capture(row,*args,**kwargs)
        self.ledger.capture_tree=capture
        original_inventory=self.fs.inventory;self.tree_scans=0;self.removing=False
        def inventory(path):
            value=original_inventory(path)
            if path==self.plan['paths']['served']:
                self.tree_scans+=1
                if self.tree_scans==2:
                    self.removing=True
                    if fault=='delete-race':self.inject('same')
            return value
        self.fs.inventory=inventory
        original_identity=self.fs.identity
        def identity(path,fd=None):
            value=original_identity(path,fd)
            if fault=='delete-hardlink' and self.removing and path==getattr(self,'output',None) and not self.injected:
                os.link(self.fs.path(path),self.root/'held-alias');self.injected=True
            return value
        self.fs.identity=identity
    def inject(self,kind):
        need(not self.injected,'duplicate_test_fault');self.injected=True
        path=self.fs.path(self.output);self.before_identity=self.info(self.fs,self.output)
        raw=path.read_bytes();self.before_bytes=digest(raw)
        if kind in ('same','different','missing'):
            path.rename(self.root/'held-original-index') # Never depend on inode reuse.
            if kind!='missing':path.write_bytes(raw if kind=='same' else b'different public bytes')
        elif kind in ('owner','group','mode'):
            data=decode(self.model.read_bytes());column={'owner':1,'group':2,'mode':0}[kind]
            data['meta'][str(path)][column]=0o640 if kind=='mode' else 999;self.model.write_bytes(canonical(data))
        elif kind=='link':
            candidate=str(self.fs.path(self.plan['paths']['served']+'/candidate'));data=decode(self.model.read_bytes())
            data['links'][candidate]='releases/00000000-0000-4000-8000-000000000000/public';self.model.write_bytes(canonical(data))
        elif kind in ('extra','control-extra'):
            parent=path.parent if kind=='extra' else self.fs.path(self.plan['paths']['control'])
            (parent/'unknown').write_bytes(b'unknown owned-test input')
        self.load_model()
        if kind!='missing':
            self.after_identity=self.info(self.fs,self.output);self.after_bytes=digest(path.read_bytes())
        if kind in ('same','different'):
            need(self.before_identity['ino']!=self.after_identity['ino'],'test_requires_distinct_inode')
            need({k:v for k,v in self.before_identity.items() if k!='ino'}==
                 {k:v for k,v in self.after_identity.items() if k!='ino'},'test_preserves_metadata')
    def observer(self):
        if self.fault=='no-observer':raise Failure('observer_not_completed')
        value=super().observer();self.independent=copy.deepcopy(value)
        self.output=self.plan['paths']['served']+'/releases/'+self.plan['release']['release_key']+'/public/index.html'
        self.observed_inventory=snapshot(self.fs,self.plan,self.manifest,self.info)
        need(digest(canonical(self.observed_inventory))==value['inventory_sha256'],'test_original_observer_inventory')
        if self.fault.startswith('post-'):self.inject(self.fault[5:])
        if self.fault=='missing-hash':value.pop('inventory_sha256')
        if self.fault=='invalid-hash':value['inventory_sha256']='not-a-sha256'
        if self.fault=='no-control-list':
            walk=os.walk
            def restricted(path,*args,**kwargs):
                if Path(path)==self.fs.path(self.plan['paths']['control']):raise PermissionError(13,'control_not_readable')
                return walk(path,*args,**kwargs)
            with mock.patch.object(os,'walk',restricted):
                observed=snapshot(self.fs,self.plan,self.manifest,self.info,observer=True)
            need(digest(canonical(observed))==value['inventory_sha256'],'test_fixed_control_inventory')
        return value

class ComponentTests(unittest.TestCase):
    def test_candidate_serving_original_bytes_and_owned_reader(self):
        def check(backend,state,error):
            self.assertIsNone(error);self.assertEqual(state['cleanup'],'REMOVED')
            obs=state['observations'];served=obs['serving']
            self.assertEqual(served['verified_files'],obs['source']['files'])
            self.assertEqual(served['verified_heads'],served['verified_files']);self.assertGreater(served['denials'],30)
            self.assertEqual(obs['observer']['inventory_sha256'],obs['serving_after']['inventory_sha256'])
            self.assertEqual(obs['serving_before']['serving_inventory'],obs['serving_after']['serving_inventory'])
            self.assertEqual(obs['serving_execution'],dict(http='modeled',tls='modeled',apache='modeled'))
            self.assertFalse(backend.fs.exists(serving_root(backend.plan)))
            self.assertLess(backend.events.index('component-a-before-cleanup'),backend.events.index('stop'))
            self.assertLess(backend.events.index('stop'),backend.events.index('remove'))
            reader=next(x for x in backend.captured['children'] if x['role']=='reader')
            self.assertIsNone(reader['observed_exit']);self.assertTrue(backend.reader_clean)
            config=serving_config(backend.ledger.config,backend.plan).decode()
            self.assertEqual(backend.module.transfer_entries(canonical(backend.manifest),backend.plan['release']),backend.manifest)
            self.assertIn('Listen 127.0.0.4:8443',config);self.assertNotIn('/current',config)
            self.assertIn(backend.plan['paths']['served']+'/candidate',config)
            self.assertIn('SSLStrictSNIVHostCheck On',config)
            self.assertIn('<Location "/">\n        AuthMerging And',config)
            self.assertIn('Mutex pthread default',config)
        self.execute(backend_type=ServingLocal,check=check)
    def test_candidate_serving_response_faults_keep_after_observation(self):
        for fault in ('wrong-marker','wrong-mime','wrong-digest','security-header','handler-bypass','auth-bypass'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault,backend_type=ServingLocal)
                self.assertIsNotNone(error);self.assertEqual(state['cleanup'],'REMOVED')
                self.assertEqual(state['result'],'INCOMPLETE');self.assertIn('serving_after',state['observations'])
                self.assertGreater(state['observations']['serving']['requests'],0)
    def test_candidate_serving_changed_artifacts_are_retained(self):
        for fault in ('candidate-absent','candidate-corrupt','marker-missing','serve-same-inode-substitution','serve-corrupt','serve-missing',
                      'serving-extra','serving-missing','serving-inventory-missing','serving-inventory-malformed',
                      'serving-after-missing','serving-after-malformed','serving-cleanup-replaced'):
            with self.subTest(fault=fault):
                def check(backend,state,error):
                    self.assertIsNotNone(error);self.assertEqual(state['cleanup'],'LIMITED')
                    self.assertTrue(backend.fs.exists(backend.plan['paths']['data']))
                    self.assertTrue(backend.fs.exists(serving_root(backend.plan)))
                    if fault=='serve-same-inode-substitution':
                        self.assertNotEqual(backend.old_public['ino'],backend.new_public['ino'])
                        self.assertEqual(state['observations']['serving']['verified_files'],state['observations']['source']['files'])
                self.execute(fault,backend_type=ServingLocal,check=check)
    def test_candidate_serving_reader_capture_and_restoration_failures(self):
        for fault in ('reader-death','reader-timeout','reader-unknown','capture-failure','restoration-observation'):
            with self.subTest(fault=fault):
                state,error=self.execute(fault,backend_type=ServingLocal)
                self.assertIsNotNone(error);self.assertEqual(state['cleanup'],'LIMITED')
                self.assertEqual(state['result'],'INCOMPLETE');self.assertIsNotNone(state['first_failure'])
    def test_candidate_serving_first_failure_is_not_overwritten(self):
        def check(backend,state,error):
            self.assertEqual(str(error),'serving_mime');self.assertEqual(state['cleanup'],'LIMITED')
            self.assertEqual(backend.ledger.state['first_failure']['kind'],'component-serving')
            self.assertIn('component-after-serving',[x['kind'] for x in backend.ledger.state['secondary']])
            self.assertIn('component-cleanup',[x['kind'] for x in backend.ledger.state['secondary']])
        self.execute('response-and-after-failure',backend_type=ServingLocal,check=check)
    def execute(self,fault='',check=None,backend_type=Local):
        root='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-ca-',dir=root) as directory:
            backend=backend_type(directory,fault);error=None
            try:c.run(backend)
            except BaseException as caught:error=caught
            state=backend.ledger.state['observations']['component_a']
            if check:check(backend,state,error)
            self.assertEqual(backend.operational(),backend.before)
            self.assertTrue(all(x['process'].poll() is not None for x in backend.active))
            return copy.deepcopy(state),error
    def test_tree_constructor_directory_read_and_private_child_access(self):
        root='\\\\?\\'+tempfile.gettempdir() if os.name=='nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-ca-',dir=root) as directory:
            x=Local(directory);x.prepare(dict(resources=[]));x.save_model();ops=Model(directory,x.model);m=c.publisher_module();m.os=ops
            data=x.fs.path(x.plan['paths']['data']);control=x.fs.path(x.plan['paths']['control'])
            self.assertEqual(x.fs.identity(x.plan['paths']['data'])['mode'],0o755)
            self.assertEqual(x.fs.identity(x.plan['paths']['control'])['mode'],0o711)
            for key,group in (('served',2104),('journal',2102)):
                tree=ops.tree(m,x.fs.path(x.plan['paths'][key]),group);tree.close()
            self.assertTrue(any(v['path']==str(data) and v['required']==4 for v in ops.access))
            ops.meta[str(data)][0]=0o711;ops.permit(data,1) # Search succeeds, directory read does not.
            with self.assertRaises(PermissionError) as failed:ops.tree(m,x.fs.path(x.plan['paths']['served']),2104)
            self.assertEqual(failed.exception.errno,13);self.assertEqual(failed.exception.filename,str(data))
            ops.meta[str(data)][0]=0o755;ops.permit(control,1)
            with self.assertRaises(PermissionError):ops.permit(control,4)
            for key,leaf in (('served','access-probe'),('journal','target.lock')):
                ops.uid=2000;ops.groups={2000}
                parent=ops.open(x.plan['paths']['data'],ops.O_DIRECTORY|os.O_RDONLY);ops.close(parent)
                with self.assertRaises(PermissionError):ops.open(x.plan['paths'][key]+'/'+leaf,os.O_RDWR)
            sealed=Path(directory)/'sealed';sealed.mkdir();source=sealed/'source';source.write_bytes(b'private')
            ops.meta[str(sealed)]=[0o700,2000,2000];ops.meta[str(source)]=[0o600,2000,2000];ops.uid=2102;ops.groups={2102,2104}
            with self.assertRaises(PermissionError):ops.open(source,os.O_RDONLY)
    def test_post_observer_same_content_replacement_is_retained(self):
        def check(x,s,error):
            self.assertEqual(str(error),'component_observer_inventory_changed');self.assertTrue(x.injected)
            self.assertEqual(x.before_bytes,x.after_bytes);self.assertNotEqual(x.before_identity['ino'],x.after_identity['ino'])
            self.assertEqual(s['observations']['observer'],x.independent)
            self.assertEqual(s['cleanup'],'LIMITED');self.assertEqual(s['result'],'INCOMPLETE');self.assertEqual(x.removals,[])
            self.assertTrue(x.fs.exists(x.output));self.assertEqual(x.fs.read(x.plan['paths']['journal']+'/state.json'),x.journal_before)
            self.assertNotIn('component-a-owned-before-remove',x.events)
        self.execute('post-same',check,ReviewLocal)
    def test_post_observer_changed_namespace_rejects_before_deletion(self):
        for fault,reason in (('different','component_observer_inventory_changed'),('owner','component_object_metadata'),
                ('group','component_object_metadata'),('mode','component_object_metadata'),('link','component_pointer_target'),
                ('extra','component_unknown_path'),('control-extra','component_unknown_path'),('missing','component_observer_inventory_changed')):
            with self.subTest(fault=fault):
                def check(x,s,error):
                    self.assertEqual(str(error),reason);self.assertTrue(x.injected);self.assertEqual(x.removals,[])
                    self.assertEqual(s['observations']['observer'],x.independent);self.assertEqual(s['cleanup'],'LIMITED')
                    self.assertTrue(x.fs.exists(x.plan['paths']['journal']+'/state.json'))
                self.execute('post-'+fault,check,ReviewLocal)
    def test_observer_inventory_is_required_without_adopting_output(self):
        for fault,reason in (('missing-hash','component_observer_inventory_required'),('invalid-hash','hash'),
                              ('no-observer','observer_not_completed')):
            with self.subTest(fault=fault):
                def check(x,s,error):
                    self.assertEqual(str(error),reason);self.assertEqual(s['cleanup'],'LIMITED');self.assertEqual(x.removals,[])
                    self.assertTrue(x.fs.exists(x.plan['paths']['served']+'/candidate'))
                    if fault=='no-observer':self.assertIn('component_observer_inventory_required',x.cleanup_errors)
                self.execute(fault,check,ReviewLocal)
    def test_generated_identity_remains_bound_through_capture_and_delete(self):
        for fault,reason in (('capture-race','owned_child_replaced'),('delete-race','cleanup_child_replaced'),
                              ('delete-hardlink','component_removal_identity')):
            with self.subTest(fault=fault):
                def check(x,s,error):
                    self.assertEqual(str(error),reason);self.assertTrue(x.injected)
                    self.assertEqual(s['cleanup'],'LIMITED');self.assertEqual(s['result'],'INCOMPLETE')
                    self.assertIn('component-a-owned-before-remove',x.events)
                    self.assertTrue(x.fs.exists(x.output));self.assertNotIn(x.output,x.removals)
                    self.assertEqual(x.fs.read(x.plan['paths']['journal']+'/state.json'),x.journal_before)
                    self.assertEqual(s['observations']['observer'],x.independent)
                self.execute(fault,check,ReviewLocal)
    def test_restricted_control_ancestor_inventory_matches_complete_operator_walk(self):
        def check(x,s,error):
            if error:raise error
            self.assertEqual(s['cleanup'],'REMOVED');self.assertEqual(s['result'],'STAGED_AND_REMOVED')
        self.execute('no-control-list',check,ReviewLocal)
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
                def check(x,state,error):
                    self.assertIsNotNone(error);self.assertEqual(state['result'],'INCOMPLETE')
                    if fault=='startup':self.assertIsInstance(error,FileNotFoundError);self.assertEqual(state['children'],[])
                    else:
                        helper=next(ch for ch in state['children'] if ch['role']=='helper')
                        self.assertEqual(helper['observed_exit'],17 if fault=='helper-death' else 18)
                        self.assertIn('source',state['observations']);self.assertNotIn('observer',state['observations'])
                    if fault=='helper-stage-death':
                        self.assertEqual(state['cleanup'],'LIMITED');self.assertEqual(decode(x.journal_before)['fence'],1)
                        self.assertEqual(decode(x.journal_before)['phase'],0)
                self.execute(fault,check)
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
