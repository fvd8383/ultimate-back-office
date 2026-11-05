"""Local controls only. Engine/Linux/SQL facts are MODELS; bounded files and subprocesses are real."""
import copy
import contextlib
import io
import importlib.util
import os
from pathlib import Path, PurePosixPath
import sys
import subprocess
import tempfile
import threading
import time
import unittest
from types import SimpleNamespace
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import canonical,decode,digest
import WebsitePlatformM6EDBOnlyContract as c
import WebsitePlatformM6EDBOnlyCampaign as campaign
import WebsitePlatformM6EDBOnlyRuntime as runtime
import WebsitePlatformM6EBootstrapPlan as full_plan
import WebsitePlatformM6EAcceptance as full_acceptance
import WebsitePlatformM6EPreparation as preparation
import WebsitePlatformM6EEligibility as eligibility
import WebsitePlatformM6EEligibilityTest as existing
from WebsitePlatformM6EProcess import Owner,Failure,publish_bytes
import WebsitePlatformM6EDBOnly as operator


def request():
    return dict(profile=c.PROFILE,source_sha='a'*40,source_inventory_sha256='b'*64,runtime_image='sha256:'+'c'*64,
        mysql_image='sha256:'+'d'*64,run_id='e'*32,case='positive',review_receipt_sha256='f'*64,limits=copy.deepcopy(c.LIMITS))


def container(req,role):
    uid='0:0' if role=='runtime' else '999:999';mounts=[]
    if role=='runtime':mounts=[dict(Type='bind',Source=c.EXPORTS+'/'+req['source_sha'],Destination=c.SOURCE,RW=False)]
    argv=c.create_args(req,role,'1'*64);idx=argv.index(req[role+'_image'])
    return dict(Id=('1' if role=='runtime' else '2')*64,Name='/'+c.names(req)[role],Image=req[role+'_image'],
        Config=dict(Labels={c.LABEL:req['run_id'],c.LABEL+'.request':digest(canonical(req))},User=uid,
            Entrypoint=[argv[argv.index('--entrypoint')+1]],Cmd=argv[idx+1:]),Mounts=mounts,
        HostConfig=dict(ReadonlyRootfs=True,Privileged=False,NetworkMode='none' if role=='runtime' else 'container:'+'1'*64,
            Memory=c.LIMITS[role+'_memory'],MemorySwap=c.LIMITS[role+'_memory'],NanoCpus=c.LIMITS['cpu'],PidsLimit=c.LIMITS[role+'_pids'],
            CapDrop=['ALL'],CapAdd=['CHOWN','DAC_OVERRIDE','SETUID','SETGID','KILL'] if role=='runtime' else [],
            SecurityOpt=['no-new-privileges'],PortBindings={},Devices=[],VolumesFrom=None,Binds=None,PidMode='',UsernsMode='',
            RestartPolicy=dict(Name='no'),LogConfig=dict(Type='none'),
            Tmpfs={p:'rw,nosuid,nodev,noexec,size='+str(size) for p,size in c.mount_limits(role).items()}))


HOST_NS={n:n+':[10]' for n in ('user','net','pid','mnt')}
def measurement(role):
    return dict(uid=0 if role=='runtime' else 999,gid=0 if role=='runtime' else 999,
        memory_max=c.LIMITS[role+'_memory'],swap_max=0,pids_max=c.LIMITS[role+'_pids'],cpu_max=[100000,100000],
        uid_map=[[0,1000,1],[1,100000,65536]],gid_map=[[0,1000,1],[1,100000,65536]],no_new_privs=1,
        capabilities=227 if role=='runtime' else 0,interfaces=['lo'],
        source_root=dict(dev=123,ino=456,uid=0,gid=0,mode=0o555) if role=='runtime' else None,
        namespaces={n:n+':[20]' for n in HOST_NS},source_readonly=True,storage={p:dict(type='tmpfs',bytes=size,size_option=size,
        options=['rw','nosuid','nodev','noexec']) for p,size in c.mount_limits(role).items()})


class Contract(unittest.TestCase):
    def test_exact_source_image_run_and_version_bindings(self):
        req=request();self.assertEqual(c.request(req),req)
        for key,bad in [('source_sha','main'),('runtime_image','php:latest'),('mysql_image',req['runtime_image']),
            ('run_id','../working'),('profile','m6e-bootstrap-v1'),('source_inventory_sha256',''),('review_receipt_sha256',''),('case','deployment')]:
            value=copy.deepcopy(req);value[key]=bad
            with self.subTest(key=key),self.assertRaises(ValueError):c.request(value)
        value=copy.deepcopy(req);value['database']='working_staging'
        with self.assertRaises(ValueError):c.request(value)

    def test_every_limit_is_fixed_and_not_boolean(self):
        for key in c.LIMITS:
            value=request();value['limits'][key]+=1
            with self.subTest(key=key),self.assertRaises(ValueError):c.request(value)
        value=request();value['limits']['snapshot_seconds']=True
        with self.assertRaises(ValueError):c.request(value)
        self.assertEqual((c.LIMITS['snapshot_seconds'],c.LIMITS['restoration_seconds'],c.LIMITS['eligibility_seconds']),(45,240,120))

    def test_only_private_loopback_topology_and_bounded_tmpfs(self):
        req=request();a=c.create_args(req,'runtime');b=c.create_args(req,'mysql','1'*64)
        self.assertEqual(a[a.index('--network')+1],'none');self.assertEqual(b[b.index('--network')+1],'container:'+'1'*64)
        for args in (a,b):
            for forbidden in ('--privileged','--publish','--volume','--volumes-from','--pid','--userns'):self.assertNotIn(forbidden,args)
            self.assertNotIn('/var/run/docker.sock',' '.join(args));self.assertNotIn('/var/www/ubo-repo',' '.join(args))
            self.assertEqual(args[args.index('--memory')+1],args[args.index('--memory-swap')+1])
        self.assertNotIn('--hostname',b)
        with self.assertRaises(ValueError):c.create_args(req,'mysql','host')

    def test_container_config_and_foreign_resource_rejection(self):
        req=request()
        for role in ('runtime','mysql'):
            value=container(req,role);self.assertEqual(c.container(value,req,role,'1'*64),value['Id'])
            for key,bad in [('NetworkMode','host'),('Privileged',True),('ReadonlyRootfs',False),('Memory',0),('MemorySwap',-1),
                ('PidsLimit',0),('CapAdd',['SYS_ADMIN']),('PidMode','host'),('VolumesFrom',['foreign']),('LogConfig',dict(Type='json-file'))]:
                v=copy.deepcopy(value);v['HostConfig'][key]=bad
                with self.subTest(role=role,key=key),self.assertRaises(ValueError):c.container(v,req,role,'1'*64)
            for key in ('Id','Name','Image'):
                v=copy.deepcopy(value);v[key]='foreign'
                with self.subTest(role=role,key=key),self.assertRaises(ValueError):c.container(v,req,role,'1'*64)
            v=copy.deepcopy(value);v['Config']['Labels'][c.LABEL]='a'*32
            with self.assertRaises(ValueError):c.container(v,req,role,'1'*64)
            v=copy.deepcopy(value);v['Mounts'].append(dict(Type='volume'))
            with self.assertRaises(ValueError):c.container(v,req,role,'1'*64)

    def test_actual_limits_and_namespace_maps_required(self):
        for role in ('runtime','mysql'):
            value=measurement(role);self.assertEqual(c.measurements(value,role,HOST_NS),value)
            for key,bad in [('memory_max',2**63),('swap_max',1024),('pids_max',999),('cpu_max',[200000,100000]),
                ('uid_map',[[0,0,4294967295]]),('gid_map',[[0,0,4294967295]]),('no_new_privs',0),('uid',1000)]:
                v=copy.deepcopy(value);v[key]=bad
                with self.subTest(role=role,key=key),self.assertRaises(ValueError):c.measurements(v,role,HOST_NS)
            for ns in HOST_NS:
                v=copy.deepcopy(value);v['namespaces'][ns]=HOST_NS[ns]
                with self.assertRaises(ValueError):c.measurements(v,role,HOST_NS)
            for path in value['storage']:
                for key,bad in [('type','overlay'),('bytes',2**40),('size_option',2**40),('options',['rw'])]:
                    v=copy.deepcopy(value);v['storage'][path][key]=bad
                    with self.subTest(path=path,key=key),self.assertRaises(ValueError):c.measurements(v,role,HOST_NS)

    def test_db_only_does_not_satisfy_full_host_contracts(self):
        for value in (request(),dict(profile=c.PROFILE,result='DB-ONLY COMPONENT VALIDATION PASS',**c.AUTHORITY)):
            with self.assertRaises((ValueError,KeyError)):full_plan.admission(value)
            with self.assertRaises((ValueError,KeyError)):full_acceptance.validate(value,{},native=True)
        config=dict(version=1,run_id='a'*32,source_sha='b'*40,machine_id='c'*32,
            boot_id='11111111-1111-4111-8111-111111111111',tool_sha256={k:'d'*64 for k in preparation.BINARIES},expires_at=int(time.time())+900)
        with self.assertRaisesRegex(ValueError,'vm_root_only'):
            preparation.admit_facts(config,dict(platform='linux',uid=0,container='docker'))


class Retention(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(prefix='m6e-db-only-control-');self.path=Path(self.temp.name)/'evidence'
        # Windows does not expose POSIX 0600; only that metadata predicate is modeled here.
        self.mode=mock.patch.object(c.stat,'S_IMODE',lambda value:0o600) if os.name=='nt' else mock.patch.object(c.stat,'S_IMODE',c.stat.S_IMODE)
        self.mode.start()
    def tearDown(self):self.mode.stop();self.temp.cleanup()

    def test_fixed_capacity_immutable_records_and_readonly_status(self):
        store=c.Retained(self.path,create=True)
        try:
            for i in range(3):store.append(dict(sequence=i,digest='a'*64))
            self.assertEqual(self.path.stat().st_size,c.LIMITS['retained'])
            self.assertEqual(len(store.read()),3)
            with self.assertRaises(FileExistsError):c.Retained(self.path,create=True)
        finally:store.close()
        before=self.path.read_bytes();store=c.Retained(self.path,readonly=True)
        try:
            with self.assertRaises(ValueError):store.append(dict(foreign=True))
        finally:store.close()
        self.assertEqual(self.path.read_bytes(),before)

    def test_oversized_write_and_partial_unknown_record_reject(self):
        store=c.Retained(self.path,create=True)
        try:
            store.append(dict(original=True));before=self.path.read_bytes()
            with self.assertRaises(ValueError):store.append(dict(payload='x'*c.SLOT))
            self.assertEqual(self.path.read_bytes(),before)
            with self.path.open('r+b') as f:f.seek(c.SLOT);f.write(b'torn')
            with self.assertRaises(ValueError):store.read()
        finally:store.close()

    def test_replaced_retained_file_is_not_adopted(self):
        store=c.Retained(self.path,create=True)
        try:
            if os.name=='nt':
                # Windows prevents rename of an open fd; real in-place truncation still rejects.
                with self.path.open('r+b') as f:f.truncate(20)
            else:
                self.path.rename(self.path.with_suffix('.original'));self.path.write_bytes(b'foreign')
            with self.assertRaises(ValueError):store.append(dict(should_not_write=True))
        finally:store.close()


class Evidence(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        existing.Integrity.setUpClass();cls.base=existing.Integrity

    def test_honest_db_owner_reuses_comparison_but_not_full_host_owner(self):
        b=self.base
        rt=type('Model',(),dict(a=b.config|dict(namespaces=HOST_NS),source=b.source))()
        with mock.patch.object(campaign.Path,'read_text',return_value='0::/docker/model\n'):
            req=campaign.eligibility_request(rt,b.cp_raw,b.snapshot_raw,b.schema_raw,decode(b.restoration_raw))
        self.assertEqual(req['profile'],c.PROFILE+'-eligibility');self.assertEqual(req['owner']['cgroup'],['0::/docker/model'])
        with self.assertRaises(ValueError):eligibility.request(b.config,b.source,b.cp_raw,b.snapshot_raw,b.schema_raw,b.restoration_raw,req['owner'],'d'*32,int(time.time()))

    def test_full_orchestration_model_orders_capture_before_B_and_preserves_original(self):
        b=self.base;test=self
        for drift in (False,True):
            with self.subTest(original_drift=drift),tempfile.TemporaryDirectory(prefix='m6e-db-chain-') as directory:
                class Model:
                    def __init__(self):
                        self.req=request();self.a=b.config|dict(namespaces=HOST_NS);self.source=b.source
                        self.owner=Owner(time.monotonic()+600);self.receipts={};self.inputs={};self.outputs={}
                        self.trace=[];self.secondary=[];self.first=None;self.phase='model';self.source_reads=0
                    def put(self,name,value):self.inputs[name]=copy.deepcopy(value)
                    def credential(self,*args):pass  # Explicitly modeled role transport; no native credentials.
                    def read_output(self,name,uid,promote=False):
                        value=self.outputs[name]
                        if promote:self.put(name,value)
                        return canonical(value)
                    def publish(self,name,value):self.receipts[name]=publish_bytes(directory,name,canonical(value))
                    def verify_outputs(self):pass
                    def failure(self,error,kind):
                        if self.first is None:self.first=dict(kind=kind)
                        else:self.secondary.append(dict(kind=kind))
                    def value(self,mode,seconds=60):return self.command(mode,seconds)[0]['value']
                    def command(self,mode,seconds=60,**kwargs):
                        self.trace.append(mode);self.phase=mode;value={}
                        if mode=='schema':
                            self.outputs['schema']=decode(b.schema_raw)
                            self.outputs['credentials']={n:dict(username=n,password='0'*64) for n in ('builder','snapshot','source','writer')}
                        elif mode=='build-a':self.outputs['built']=dict(modeled=True)
                        elif mode=='checkpoint':self.outputs['checkpoint']=decode(b.cp_raw)
                        elif mode=='snapshot':
                            test.assertIn('checkpoint',self.inputs);self.outputs['snapshot']=decode(b.snapshot_raw)
                            value=dict(capture='CAPTURED',transaction_cleanup='RELEASED')
                        elif mode=='build-b':
                            test.assertIn('snapshot-verification',self.receipts);self.outputs['completed']=dict(modeled=True)
                        elif mode=='source':
                            self.source_reads+=1;value=dict(sha256=('changed' if drift and self.source_reads>1 else 'original'))
                        elif mode=='restore-create':
                            for role in ('restorer','observer'):self.outputs['credential-'+role]=dict(username=role,password='0'*64)
                        elif mode=='restore':self.outputs['restoration-outcome']=dict(result='AWAITING_INDEPENDENT_VERIFICATION',transaction='COMMITTED',secondary=[])
                        elif mode=='restore-seal':value=b.accounts
                        elif mode=='restore-observe':self.outputs['restoration-observation']=copy.deepcopy(b.observation['observation'])
                        elif mode=='eligibility-create':self.outputs['credential-eligibility']=dict(username='modeled',password='0'*64)
                        elif mode=='eligibility-seal':value=b.seal
                        elif mode in ('before','after'):
                            req=self.inputs['eligibility'];v=copy.deepcopy(b.observation);v['phase']=mode
                            v['request_sha256']=digest(canonical(req));v['process']['owner_pid']=req['owner']['pid']
                            v['observation']['database']['connection_id']=80 if mode=='before' else 82
                            v['diagnostic']=eligibility.observer_diagnostic(v,mode,req,b.cp_raw,b.snapshot_raw,b.schema_raw);self.outputs[mode]=v
                        elif mode=='eligibility':
                            req=self.inputs['eligibility'];v=copy.deepcopy(b.gate);v['request_sha256']=digest(canonical(req));v['invocation_id']=req['invocation_id']
                            v['process']['owner_pid']=req['owner']['pid'];self.outputs['gate-outcome']=v
                        else:raise AssertionError('unmodeled phase '+mode)
                        return dict(result='COMPLETED',value=value),900,0
                rt=Model();real_validate=campaign.snapshot.validate;real_request=campaign.eligibility_request
                def local_validate(raw,schema,cp,config,source,execution,now):
                    return real_validate(raw,schema,cp,config,source,'controlled-local',now)
                def local_request(*args):
                    with mock.patch.object(campaign.Path,'read_text',return_value='0::/docker/model\n'):return real_request(*args)
                with mock.patch.object(campaign.snapshot,'validate',side_effect=local_validate),mock.patch.object(campaign,'eligibility_request',side_effect=local_request),mock.patch.object(campaign,'source_inventory_equal',return_value=True):
                    if drift:
                        with self.assertRaises(ValueError):campaign.run_chain(rt)
                        self.assertEqual(rt.first['kind'],'final-integrity')
                    else:
                        value=campaign.run_chain(rt);self.assertTrue(value['original_preserved']);self.assertEqual(value['deployment_operations'],0)
                ordered=['build-a','checkpoint','snapshot','build-b','source','restore-create','restore','restore-seal','restore-observe',
                         'eligibility-create','before','eligibility','eligibility-seal','after']
                self.assertEqual([rt.trace.index(n) for n in ordered],sorted(rt.trace.index(n) for n in ordered))
                self.assertIn('observer-before',rt.receipts);self.assertIn('observer-after',rt.receipts)

    def test_actual_observer_recomputation_and_publication_precedes_failure(self):
        b=self.base
        for phase in ('before','after'):
            for case in ('unchanged','schema','notes','artifact','missing','publication-failure','capture-cleanup-failure'):
                with self.subTest(phase=phase,case=case),tempfile.TemporaryDirectory(prefix='m6e-db-observer-') as directory:
                    value=copy.deepcopy(b.observation);value['phase']=phase
                    if case=='schema':value['observation']['read']['definitions']['notes']['definition']+=' /* drift */'
                    if case in ('notes','capture-cleanup-failure'):value['observation']['read']['tables']['notes']['rows']=[]
                    if case=='artifact':
                        value['observation']['artifact']=None;value['progress']['artifact']='FAILED'
                    value['diagnostic']=eligibility.observer_diagnostic(value,phase,b.req,b.cp_raw,b.snapshot_raw,b.schema_raw)
                    writes=[]
                    class Model:
                        req=dict(case=case)
                        a=b.config;source=b.source;receipts={};secondary=[];first=None
                        def command(self,*args,**kwargs):return {'result':'COMPLETED'},900,0
                        def read_output(self,*args,**kwargs):
                            if case in ('missing','capture-cleanup-failure'):raise FileNotFoundError('PRIVATE_PATH')
                            return canonical(value)
                        def verify_outputs(self):pass
                        def failure(self,error,kind):self.first=self.first or dict(kind=kind)
                        def publish(self,name,body):
                            writes.append((name,body));self.receipts[name]=publish_bytes(directory,name,canonical(body))
                    rt=Model()
                    # Execution discriminator is explicitly modeled; use real independent comparison.
                    real=eligibility.verify_observer
                    def local(raw,phase,req,cp,snap,schema,config,source,execution,accounts,now):
                        return real(raw,phase,req,cp,snap,schema,config,source,'controlled-local',accounts,now)
                    should_fail=case!='unchanged' and not(case=='publication-failure' and phase=='before')
                    with mock.patch.object(eligibility,'verify_observer',side_effect=local):
                        if should_fail:
                            with self.assertRaises(Exception):campaign.observe(rt,phase,b.req,b.cp_raw,b.snapshot_raw,b.schema_raw,b.accounts)
                        else:campaign.observe(rt,phase,b.req,b.cp_raw,b.snapshot_raw,b.schema_raw,b.accounts)
                    self.assertTrue(writes);raw=canonical(writes[0][1]);self.assertNotIn(b'PRIVATE_PATH',raw)
                    diagnostic=writes[0][1].get('diagnostic') or writes[0][1].get('evidence',{}).get('diagnostic')
                    if case in ('schema','notes'):self.assertIn('notes',diagnostic['affected_tables'])
                    if case=='notes':self.assertEqual(diagnostic['tables']['notes']['rows_observed'],0)
                    if case=='artifact':self.assertIsNotNone(diagnostic['measurements']['data']['observed'])
                    if case in ('missing','capture-cleanup-failure'):self.assertEqual(writes[0][1]['capture'],'INCOMPLETE')


class Ownership(unittest.TestCase):
    def test_active_socket_activation_unit_rejects_before_engine_contact(self):
        replies=[dict(ActiveState='active',SubState='running',MainPID='123',Delegate='yes'),
                 dict(LoadState='loaded',ActiveState='active')]
        with mock.patch.object(operator,'user_service_query',side_effect=replies),self.assertRaisesRegex(ValueError,'db_only_socket_activation'):
            operator.engine_active(None)

    def test_readonly_inspection_never_contacts_inactive_engine_or_creates_state(self):
        class PathModel:
            def __init__(self,value):self.value=str(value)
            def lstat(self):return SimpleNamespace(st_mode=0o40700,st_uid=1000,st_dev=1 if self.value=='/' else 2)
            stat=lstat
            def resolve(self):return self
            def __eq__(self,other):return isinstance(other,PathModel) and self.value==other.value
            def read_text(self):
                self_check=self.value=='/proc/meminfo'
                if not self_check:raise AssertionError('unexpected read')
                return 'MemAvailable: 8000000 kB\n'
            def mkdir(self,*args,**kwargs):raise AssertionError('inspection created state')
        with contextlib.ExitStack() as stack:
            stack.enter_context(mock.patch.dict(sys.modules,{'pwd':SimpleNamespace(getpwuid=lambda uid:SimpleNamespace(pw_name='codex-validation'))}))
            stack.enter_context(mock.patch.object(operator.sys,'platform','linux'))
            stack.enter_context(mock.patch.object(operator,'Path',PathModel))
            for name,value in [('getuid',lambda:1000),('geteuid',lambda:1000),('getgid',lambda:1000),('getegid',lambda:1000),('uname',lambda:SimpleNamespace(nodename='ubo-stage-app')),
                ('sched_getaffinity',lambda pid:{0,1,2,3}),('statvfs',lambda p:SimpleNamespace(f_bavail=20*1024**3,f_frsize=1)),
                ('readlink',lambda p:p.rsplit('/',1)[1]+':[10]')]:
                stack.enter_context(mock.patch.object(operator.os,name,value,create=True))
            stack.enter_context(mock.patch.object(operator,'engine_active',return_value=(False,dict(ActiveState='inactive'))))
            api=stack.enter_context(mock.patch.object(operator,'engine',side_effect=AssertionError('socket activation risk')))
            value=operator.host_readiness(None,None,source=False)
            self.assertEqual(value['images'],'UNVERIFIED');api.assert_not_called()

    def test_export_rejects_ignored_credentials_and_unreviewed_git_config(self):
        sha='a'*40
        class GitModel:
            def capture(self,argv,*args,**kwargs):
                output=sha.encode() if argv[-1]=='HEAD' else (b'a.php\0' if argv[-1]=='-z' else b'')
                return dict(exit=0,stderr=b'',stdout=output)
        with tempfile.TemporaryDirectory(prefix='m6e-db-export-') as directory:
            root=Path(directory);(root/'.git').mkdir();(root/'a.php').write_bytes(b'<?php // reviewed fixture')
            (root/'.git/HEAD').write_bytes((sha+'\n').encode())
            (root/'.git/config').write_bytes(b'[core]\n\trepositoryformatversion = 0\n\tfilemode = true\n\tbare = false\n\tlogallrefupdates = false\n')
            rows=runtime.source_inventory(root,sha,GitModel());self.assertEqual(set(rows),{'a.php'})
            (root/'.env').write_bytes(b'PRIVATE_CANARY')
            with self.assertRaisesRegex(ValueError,'db_only_export_no_ignored_files'):runtime.source_inventory(root,sha,GitModel())
            (root/'.env').unlink();(root/'.git/config').write_bytes(b'[include]\npath=/foreign/config\n')
            with self.assertRaisesRegex(ValueError,'db_only_export_git_config'):runtime.source_inventory(root,sha,GitModel())

    def test_capture_and_owned_cleanup_failures_retain_first_failure(self):
        with tempfile.TemporaryDirectory(prefix='m6e-db-fault-') as directory:
            class Model:
                req=request()|dict(case='capture-cleanup-failure');root=Path(directory)
                owner=Owner(time.monotonic()+30);overall=time.monotonic()+30;started=time.monotonic()
                receipts={};first=None;secondary=[];trace=[];commands=[];phase='observer-after'
                def failure(self,error,kind):
                    if self.first is None:self.first=dict(kind=kind,code='original_capture_failure')
                    else:self.secondary.append(dict(kind=kind))
                def publish(self,name,value):self.receipts[name]=publish_bytes(directory,name,canonical(value))
            rt=Model()
            def failed_chain(value):
                value.failure(ValueError('original_capture_failure'),'observer-capture');raise campaign.ExpectedFault('capture-cleanup-failure')
            with mock.patch.object(campaign,'Runtime',return_value=rt),mock.patch.object(campaign,'run_chain',side_effect=failed_chain),contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(campaign.campaign({}),0)
            before=decode((Path(directory)/'before-cleanup.json').read_bytes());after=decode((Path(directory)/'report.json').read_bytes())
            self.assertEqual(before['first_failure'],after['first_failure']);self.assertEqual(after['first_failure']['code'],'original_capture_failure')
            self.assertTrue(any(x['kind']=='owned-cleanup' for x in after['secondary']))
            self.assertEqual(after['context']['resources'][0]['state'],'UNRESOLVED');self.assertTrue((Path(directory)/'case-cleanup-probe').exists())

    def test_interrupted_real_child_is_reaped_and_unknown_output_not_accepted(self):
        owner=Owner(time.monotonic()+5)
        try:
            with self.assertRaises(Failure):owner.capture([sys.executable,'-I','-B','-c','import time;time.sleep(5)'],.1)
            self.assertTrue(owner.context[0]['reaped']);self.assertIsNotNone(owner.context[0]['exit'])
        finally:owner.cleanup()

    def test_exact_cleanup_foreign_resource_preservation(self):
        req=request();records=[];store=type('Store',(),dict(append=lambda self,value:records.append(value)))()
        op=operator.Operator(req,store,Owner(time.monotonic()+20));op.ids={'runtime':'1'*64,'mysql':'2'*64};op.intents={'runtime','mysql'}
        commands=[]
        def engine(owner,args,**kwargs):
            commands.append(args)
            if args[0]=='inspect':
                role='mysql' if args[1]=='2'*64 else 'runtime';v=container(req,role)
                if role=='mysql':v['Config']['Labels'][c.LABEL]='f'*32
                return dict(stdout=canonical([v]),exit=0)
            return dict(stdout=b'',exit=0)
        with mock.patch.object(operator,'engine',side_effect=engine),self.assertRaises(ValueError):op.cleanup()
        self.assertNotIn(['rm','--force','2'*64],commands);self.assertIn(['rm','--force','1'*64],commands)
        self.assertEqual(records[-1]['kind'],'cleanup-incomplete')

    def test_deadline_scope_restores_outer_budget_without_renewing_it(self):
        rt=type('Model',(),dict(owner=Owner(time.monotonic()+10)))();original=rt.owner.deadline
        def action():self.assertLess(rt.owner.deadline,original);raise ValueError('injected')
        with self.assertRaises(ValueError):campaign.bounded_phase(rt,1,action)
        self.assertEqual(rt.owner.deadline,original)

    def test_competing_snapshot_allows_either_atomic_commit_order_but_never_partial_rows(self):
        def notes(values):return dict(columns=['note_body'],rows=[[v.encode().hex()] for v in values],row_count=len(values))
        self.assertEqual(campaign.competing_rows(notes(['M6E DB-only baseline'])),'BEFORE_WRITER_COMMIT')
        self.assertEqual(campaign.competing_rows(notes(['M6E bounded competing writer','M6E same transaction'])),'AFTER_WRITER_COMMIT')
        for values in ([],['M6E bounded competing writer'],['M6E DB-only baseline','M6E same transaction']):
            with self.assertRaisesRegex(ValueError,'db_only_competing_atomic_values'):campaign.competing_rows(notes(values))


class UserServiceEnvironment(unittest.TestCase):
    MINIMAL=dict(PATH='/usr/bin:/bin',LANG='C',HOME='/nonexistent')
    ADDITIONS=dict(XDG_RUNTIME_DIR='/run/user/1000',DBUS_SESSION_BUS_ADDRESS='unix:path=/run/user/1000/systemd/private')
    CANARIES=dict(PRIVATE_SENTINEL='PRIVATE_SECRET',LD_PRELOAD='PRIVATE_LOADER',LD_LIBRARY_PATH='PRIVATE_LIBRARY',
        PYTHONPATH='PRIVATE_PYTHON',PYTHONHOME='PRIVATE_HOME',DOCKER_HOST='tcp://untrusted:2375',DOCKER_CONTEXT='foreign',
        DOCKER_CONFIG='/foreign/docker',GIT_DIR='/foreign/git',GIT_CONFIG_GLOBAL='/foreign/config',GIT_CONFIG_COUNT='1',
        SYSTEMCTL_FORCE_BUS='1',SYSTEMD_BUS_ADDRESS='tcp:host=untrusted,port=1')
    INACTIVE=b'ActiveState=inactive\nSubState=dead\nMainPID=0\nDelegate=yes\n'
    ACTIVE=b'ActiveState=active\nSubState=running\nMainPID=123\nDelegate=yes\n'
    SOCKET=b'LoadState=not-found\nActiveState=inactive\n'
    CHILD='''import json,os,sys,time
expected=json.loads(sys.argv[1]);actual=dict(os.environ)
# Python may coerce LANG=C on POSIX; that runtime-created locale is not inherited.
if os.name!='nt' and actual.get('LC_CTYPE')=='C.UTF-8':actual.pop('LC_CTYPE')
if actual!=expected:sys.exit(97)
time.sleep(float(sys.argv[5]))
sys.stdout.buffer.write(bytes.fromhex(sys.argv[2]));sys.stderr.buffer.write(bytes.fromhex(sys.argv[3]))
if len(sys.argv)>6:sys.stdout.buffer.write(b'x'*int(sys.argv[6]))
sys.exit(int(sys.argv[4]))
'''

    @contextlib.contextmanager
    def host(self,identity=None,changes=None):
        """Only unavailable Linux identity/path facts are modeled; no actual manager or bus is queried."""
        facts=dict(uid=1000,euid=1000,gid=1000,egid=1000,account='codex-validation',host='ubo-stage-app',platform='linux')
        facts.update(identity or {});changes=changes or {};reads=[];original_path=operator.Path
        export=c.EXPORTS+'/'+request()['source_sha'];inventory={'fixture.php':dict(sha256='b'*64)}
        req=request()|dict(source_inventory_sha256=digest(canonical(inventory)))
        def metadata(name):
            reads.append(name)
            if name in changes and changes[name] is None:raise FileNotFoundError('PRIVATE_MISSING')
            if name in ('/run/user/1000','/run/user/1000/systemd'):mode=0o40700 if name.endswith('1000') else 0o40755
            elif name in ('/run/user/1000/systemd/private','/run/user/1000/docker.sock'):mode=0o140700
            elif name=='/proc/123':mode=0o40555
            elif name in (c.BASE,c.EVIDENCE,c.EXPORTS):mode=0o40700
            elif name==export:mode=0o40555
            elif name=='/':mode=0o40755
            else:raise AssertionError('unmodeled metadata path')
            values=dict(st_mode=mode,st_uid=1000,st_gid=1000,st_nlink=1,st_dev=1 if name=='/' else 2,st_ino=42)
            values.update({k:v for k,v in changes.get(name,{}).items() if k!='resolved'})
            return SimpleNamespace(**values)
        class HostPath:
            def __init__(self,value):self.value=str(value)
            def __truediv__(self,part):return HostPath(self.value+'/'+str(part))
            def __str__(self):return self.value
            def __eq__(self,other):return isinstance(other,HostPath) and self.value==other.value
            def lstat(self):return metadata(self.value)
            stat=lstat
            def resolve(self):return HostPath((changes.get(self.value) or {}).get('resolved',self.value))
            @property
            def parents(self):return [HostPath(str(p)) for p in PurePosixPath(self.value).parents]
            def rglob(self,pattern):test.assertEqual(self.value,export);return []
            def read_text(self):test.assertEqual(self.value,'/proc/meminfo');return 'MemAvailable: 8000000 kB\n'
        test=self
        def path(value):
            value=str(value)
            if value==operator.__file__:return HostPath(export+'/tests/WebsitePlatformM6EDBOnly.py')
            return HostPath(value) if value.startswith('/') else original_path(value)
        with contextlib.ExitStack() as stack:
            stack.enter_context(mock.patch.dict(sys.modules,{'pwd':SimpleNamespace(getpwuid=lambda uid:SimpleNamespace(pw_name=facts['account']))}))
            stack.enter_context(mock.patch.object(operator.sys,'platform',facts['platform']))
            stack.enter_context(mock.patch.object(operator,'Path',side_effect=path))
            for name in ('uid','euid','gid','egid'):
                stack.enter_context(mock.patch.object(operator.os,'get'+name,lambda key=name:facts[key],create=True))
            for name,value in [('uname',lambda:SimpleNamespace(nodename=facts['host'])),('sched_getaffinity',lambda pid:{0,1,2,3}),
                ('statvfs',lambda p:SimpleNamespace(f_bavail=20*1024**3,f_frsize=1)),('readlink',lambda p:p.rsplit('/',1)[1]+':[10]')]:
                stack.enter_context(mock.patch.object(operator.os,name,value,create=True))
            stack.enter_context(mock.patch.object(operator,'source_inventory',return_value=inventory))
            stack.enter_context(mock.patch.object(operator,'load_request',return_value=req))
            yield reads,req

    @contextlib.contextmanager
    def children(self,replies=None,active=False,delay=0,extra_stdout=0):
        """Model env/systemctl executables at Popen; keep the actual Owner, pipes, limits and real local children."""
        records=[];original=subprocess.Popen;replies=replies or {};test=self
        def popen(command,**kwargs):
            test.assertEqual(kwargs['env'],self.MINIMAL);test.assertFalse(kwargs.get('shell',False))
            additions={};args=None
            if command[0]=='/usr/bin/env':
                test.assertEqual(command[:3],['/usr/bin/env',*['='.join(pair) for pair in self.ADDITIONS.items()]])
                additions=dict(part.split('=',1) for part in command[1:3]);args=command[3:]
                test.assertEqual(args[:3],['/usr/bin/systemctl','--user','show'])
                unit=args[3];test.assertIn(unit,operator.USER_SERVICE_PROPERTIES)
                test.assertEqual(args[4:],["--property="+','.join(operator.USER_SERVICE_PROPERTIES[unit])])
                out,err,exit_=replies.get(unit,(self.ACTIVE if active and unit=='docker.service' else self.INACTIVE if unit=='docker.service' else self.SOCKET,b'',0))
            else:
                test.assertEqual(command[:len(c.CLIENT)],c.CLIENT);args=command[len(c.CLIENT):]
                req=request()
                if args==['info','--format','{{json .}}']:value=dict(CgroupVersion='2',CgroupDriver='systemd',SecurityOptions=['name=rootless'])
                else:
                    test.assertEqual(args[:2],['image','inspect']);test.assertIn(args[2],(req['runtime_image'],req['mysql_image']))
                    value=[dict(Id=args[2],Os='linux',Architecture='amd64',Config=dict(Volumes={}))]
                out,err,exit_=canonical(value),b'',0
            records.append(dict(command=command.copy(),environment=kwargs['env'].copy(),additions=additions))
            # Windows has no /usr/bin/env or systemctl. Apply only the verified literal env assignments,
            # then require the real harmless child to observe that exact environment before returning its model response.
            child_env={**kwargs['env'],**additions};kwargs={**kwargs,'env':child_env}
            return original([sys.executable,'-I','-B','-c',self.CHILD,canonical(child_env).decode(),out.hex(),err.hex(),str(exit_),str(delay),str(extra_stdout)],**kwargs)
        with mock.patch.object(subprocess,'Popen',side_effect=popen):yield records

    def test_original_owner_and_nonservice_run_omit_parent_connection_and_secret_variables(self):
        parent={**self.CANARIES,**self.ADDITIONS}
        with mock.patch.dict(os.environ,parent):
            before=dict(os.environ);owner=Owner(time.monotonic()+10)
            try:
                result=operator.run(owner,[sys.executable,'-I','-B','-c',self.CHILD,canonical(self.MINIMAL).decode(),b'{}\n'.hex(),'','0','0'])
                self.assertEqual(result['stdout'],b'{}\n');self.assertTrue(owner.context[0]['reaped'])
            finally:owner.cleanup()
            self.assertEqual(dict(os.environ),before)

    def test_fixed_private_route_ignores_missing_and_hostile_parent_connection_values(self):
        for bus in (None,'tcp:host=untrusted,port=1','unix:abstract=foreign','autolaunch:',
                    'unix:path=/foreign/bus','unix:path=/foreign/a;unix:path=/foreign/b'):
            with self.subTest(bus=bus),mock.patch.dict(os.environ,self.CANARIES,clear=True):
                if bus is not None:os.environ.update(XDG_RUNTIME_DIR='/foreign/runtime',DBUS_SESSION_BUS_ADDRESS=bus)
                before=dict(os.environ);owner=Owner(time.monotonic()+10)
                try:
                    with self.host() as (reads,req),self.children() as records:
                        self.assertFalse(operator.engine_active(owner)[0]);self.assertEqual(len(records),1)
                        self.assertEqual(records[0]['additions'],self.ADDITIONS);self.assertNotIn('/run/user/1000/bus',reads)
                    self.assertTrue(owner.context[0]['reaped']);self.assertEqual(dict(os.environ),before)
                finally:owner.cleanup()

    def test_parent_is_unchanged_on_service_query_failure_and_stderr_is_not_exposed(self):
        with mock.patch.dict(os.environ,{**self.CANARIES,**self.ADDITIONS}):
            before=dict(os.environ);owner=Owner(time.monotonic()+10)
            try:
                with self.host(),self.children({'docker.service':(self.INACTIVE,b'PRIVATE_STDERR',1)}),self.assertRaisesRegex(ValueError,'^db_only_user_service_query$'):
                    operator.engine_active(owner)
                self.assertEqual(dict(os.environ),before);self.assertTrue(owner.context[0]['reaped'])
            finally:owner.cleanup()

    def test_operator_identity_precedes_runtime_path_selection(self):
        for key,value in [('uid',0),('euid',0),('gid',1001),('egid',1001),('account','foreign'),('host','foreign'),('platform','win32')]:
            with self.subTest(key=key),self.host(identity={key:value}) as (reads,req),mock.patch.object(Owner,'capture') as capture:
                with self.assertRaisesRegex(ValueError,'db_only_host_operator'):operator.user_service_command('docker.service')
                self.assertEqual(reads,[]);capture.assert_not_called()

    def test_unsafe_or_missing_endpoint_metadata_rejects_before_spawn(self):
        runtime='/run/user/1000';manager=runtime+'/systemd';socket=manager+'/private'
        cases=[(runtime,dict(st_uid=0)),(runtime,dict(st_gid=1001)),(runtime,dict(st_mode=0o40755)),
            (runtime,dict(resolved='/foreign/runtime')),(runtime,dict(st_mode=0o120700)),
            (manager,dict(st_mode=0o40777)),(manager,dict(st_uid=0)),(manager,dict(resolved='/foreign/manager')),
            (socket,dict(st_uid=0)),(socket,dict(st_gid=1001)),(socket,dict(st_mode=0o140777)),
            (socket,dict(st_mode=0o100700)),(socket,dict(st_nlink=2)),(socket,dict(resolved='/foreign/socket')),
            (runtime,None),(manager,None),(socket,None)]
        for path,value in cases:
            with self.subTest(path=path,value=value),self.host(changes={path:value}),mock.patch.object(Owner,'capture') as capture:
                with self.assertRaisesRegex(ValueError,'db_only_user_service_endpoint'):operator.user_service_command('docker.service')
                capture.assert_not_called()

    def test_only_existing_fixed_show_queries_are_constructible(self):
        for unit in ('foreign.service','start','docker.service --host=foreign','docker.service;start','docker.socket/../foreign'):
            with self.subTest(unit=unit),mock.patch.object(operator,'host_operator') as identity:
                with self.assertRaisesRegex(ValueError,'db_only_user_service_unit'):operator.user_service_command(unit)
                identity.assert_not_called()

    def test_both_service_queries_use_bounded_owner_and_private_endpoint(self):
        owner=Owner(time.monotonic()+20);deadline=owner.deadline
        try:
            with self.host(),self.children(active=True) as records,mock.patch.object(operator,'run',wraps=operator.run) as run:
                self.assertTrue(operator.engine_active(owner)[0]);self.assertEqual(len(records),2)
                self.assertEqual([call.args[2] for call in run.call_args_list],[10,10])
                self.assertEqual([row['additions'] for row in records],[self.ADDITIONS]*2)
            self.assertEqual(owner.deadline,deadline);self.assertTrue(all(row['reaped'] for row in owner.context))
        finally:owner.cleanup()

    def test_inactive_and_active_inspection_entrypoints_share_connection_and_api_policy(self):
        for active in (False,True):
            for source in (False,True):
                with self.subTest(active=active,request=source),self.host(),self.children(active=active) as records,contextlib.redirect_stdout(io.StringIO()) as output:
                    args=['--inspect','/modeled/protected-request.json'] if source else ['--inspect-host']
                    self.assertEqual(operator.main(args),0);value=decode(output.getvalue().encode())
                    service=[r for r in records if r['command'][0]=='/usr/bin/env'];docker=[r for r in records if r['command'][0]==c.CLIENT[0]]
                    self.assertEqual(len(service),2 if active else 1);self.assertEqual(len(docker),3 if active and source else 0)
                    for row in docker:self.assertEqual(row['environment'],self.MINIMAL);self.assertEqual(row['additions'],{})
                    if not active:self.assertEqual(value['images'],'UNVERIFIED');self.assertEqual(value['result'],'ENGINE INACTIVE; IMAGE AVAILABILITY UNVERIFIED')
                    self.assertNotIn('PRIVATE_',output.getvalue());self.assertEqual(value['deployment_authority'],'NONE')

    def test_socket_activation_is_rejected_even_with_inconsistent_not_found_state(self):
        for loaded in ('loaded','not-found'):
            owner=Owner(time.monotonic()+10)
            try:
                with self.host() as (reads,req),self.children({'docker.socket':(('LoadState='+loaded+'\nActiveState=active\n').encode(),b'',0)},active=True) as records:
                    with self.assertRaisesRegex(ValueError,'db_only_socket_activation'):operator.host_readiness(owner,req)
                    self.assertEqual(len(records),2);self.assertTrue(all(r['command'][0]=='/usr/bin/env' for r in records))
            finally:owner.cleanup()

    def test_active_engine_still_requires_owned_pid_delegation_and_docker_socket(self):
        for changes,reply in [({'/proc/123':dict(st_uid=0)},self.ACTIVE),
            ({'/run/user/1000/docker.sock':dict(st_uid=0)},self.ACTIVE),
            ({'/run/user/1000/docker.sock':dict(st_mode=0o100600)},self.ACTIVE),({},self.ACTIVE.replace(b'Delegate=yes',b'Delegate=no'))]:
            owner=Owner(time.monotonic()+10)
            try:
                with self.host(changes=changes),self.children({'docker.service':(reply,b'',0)},active=True),self.assertRaises(ValueError):operator.engine_active(owner)
            finally:owner.cleanup()

    def test_service_errors_and_malformed_responses_never_become_inactive(self):
        cases=[(b'',b'',0),(self.INACTIVE+b'ActiveState=inactive\n',b'',0),(self.INACTIVE.replace(b'Delegate=yes\n',b''),b'',0),
            (self.INACTIVE+b'Other=private\n',b'',0),(b'\xff',b'',0),(b'garbage',b'PRIVATE_ERROR',1),
            (self.INACTIVE,b'PRIVATE_ERROR',1),(self.INACTIVE,b'',2),(self.ACTIVE.replace(b'MainPID=123',b'MainPID=bad'),b'',0),
            (self.INACTIVE.replace(b'ActiveState=inactive',b'ActiveState=unknown'),b'',0),
            (self.INACTIVE.replace(b'MainPID=0',b'MainPID=123'),b'',0)]
        for reply in cases:
            owner=Owner(time.monotonic()+10)
            try:
                with self.subTest(exit=reply[2]),self.host() as (reads,req),self.children({'docker.service':reply}) as records:
                    with self.assertRaises(ValueError) as caught:operator.host_readiness(owner,req)
                    self.assertNotIn('PRIVATE_',str(caught.exception));self.assertEqual(len(records),1)
            finally:owner.cleanup()

    def test_missing_socket_unit_exit_is_distinct_from_connection_error(self):
        for response,accepted in [(self.SOCKET,True),(b'LoadState=loaded\nActiveState=inactive\n',False),(b'',False)]:
            owner=Owner(time.monotonic()+10)
            try:
                with self.host(),self.children({'docker.socket':(response,b'',1)},active=True):
                    if accepted:self.assertTrue(operator.engine_active(owner)[0])
                    else:
                        with self.assertRaises(ValueError):operator.engine_active(owner)
            finally:owner.cleanup()

    def test_query_timeout_keeps_owned_child_reaping_and_parent_isolation(self):
        with mock.patch.dict(os.environ,{**self.CANARIES,**self.ADDITIONS}):
            before=dict(os.environ);owner=Owner(time.monotonic()+.3)
            try:
                with self.host(),self.children(delay=5),self.assertRaises(Failure):operator.engine_active(owner)
                self.assertTrue(owner.context[0]['reaped']);self.assertTrue(owner.context[0]['termination_requested'])
                self.assertEqual(dict(os.environ),before)
            finally:owner.cleanup()

    def test_service_query_preserves_existing_output_bound(self):
        owner=Owner(time.monotonic()+10)
        try:
            with self.host(),self.children(extra_stdout=1048577),self.assertRaises(Failure):operator.engine_active(owner)
            self.assertTrue(owner.context[0]['reaped'])
        finally:owner.cleanup()

    def test_inventory_entrypoint_needs_no_operator_or_user_service(self):
        with self.host(identity=dict(uid=999,account='foreign')) as (reads,req),\
             mock.patch.object(operator,'user_service_command',side_effect=AssertionError('inventory contacted user manager')) as query,\
             contextlib.redirect_stdout(io.StringIO()) as output:
            self.assertEqual(operator.main(['--inventory',req['source_sha']]),0)
            self.assertEqual(decode(output.getvalue().encode())['source_inventory_sha256'],req['source_inventory_sha256'])
            query.assert_not_called();self.assertEqual(reads,[])


class SnapshotDiagnostics(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        p=subprocess.run(['php',str(Path(__file__).with_suffix('.php')),'--snapshot-bundle'],capture_output=True,timeout=30)
        if p.returncode:raise AssertionError(p.stderr.decode(errors='replace')+p.stdout.decode(errors='replace'))
        cls.bundle=decode(p.stdout)

    @contextlib.contextmanager
    def lane(self,case='positive',publish_fail=False,metadata=(2001,2001,0o600)):
        with tempfile.TemporaryDirectory(prefix='m6e-snapshot-diagnostic-') as directory:
            base=Path(directory);root=base/'runtime';root.mkdir();evidence=root/'evidence';evidence.mkdir()
            for name in ('inputs','outputs/2001','barriers'):(root/name).mkdir(parents=True)
            rt=runtime.Runtime.__new__(runtime.Runtime);rt.root=root;rt.req=request()|dict(case=case)
            rt.a=dict(run_id=rt.req['run_id'],source_sha=rt.req['source_sha'],machine_id='c'*32,boot_id='1'*36,
                namespaces=HOST_NS,owner_pid=os.getpid(),expires_at=int(time.time())+60)
            rt.owner=Owner(time.monotonic()+30);rt.overall=rt.owner.deadline;rt.started=time.monotonic()
            rt.outputs={};rt.receipts={};rt.trace=[];rt.commands=[];rt.first=None;rt.secondary=[];rt.phase='snapshot'
            original_identity=runtime.file_identity;original_fstat=os.fstat
            def identity(path):
                value=original_identity(path);return (*value[:2],*metadata,value[5])
            def fstat(fd):
                s=original_fstat(fd);return SimpleNamespace(st_dev=s.st_dev,st_ino=s.st_ino,st_uid=2001,st_gid=2001,
                    st_mode=(s.st_mode&~0o777)|0o600,st_size=s.st_size,st_nlink=s.st_nlink)
            def read(name,uid,promote=False,max_bytes=4194304):
                # Only unavailable namespace UID/GID/mode facts are modeled. Bytes, fd identity, links and bounds are real.
                with mock.patch.object(runtime,'file_identity',side_effect=identity),mock.patch.object(runtime.os,'fstat',side_effect=fstat):
                    return runtime.Runtime.read_output(rt,name,uid,promote,max_bytes)
            rt.read_output=read
            def publish(directory,name,raw):
                if publish_fail and name=='snapshot-diagnostic':raise OSError('PRIVATE_PUBLICATION_PASSWORD')
                return publish_bytes(evidence,name,raw)
            with mock.patch.object(runtime,'publish_bytes',side_effect=publish):yield rt,base,evidence

    def capture(self,rt,case='combined',file_mutator=None,protocol_bad=False,interrupted=False):
        item=copy.deepcopy(self.bundle[case]);test=self
        def action(owner,command,*args,**kwargs):
            pid=12345
            record=item['record'];record['invocation']=copy.deepcopy(rt.snapshot_invocation)
            record['process']=dict(pid=pid,uid=2001,gid=2001,owner_pid=os.getpid())
            output=rt.root/'outputs/2001/snapshot-outcome.json'
            if item['file'] is not None:
                file_record=copy.deepcopy(item['file']);file_record['invocation']=record['invocation'];file_record['process']=record['process']
                output.write_bytes(canonical(file_record))
            if file_mutator:file_mutator(output,record)
            exit_=0 if case=='success' else 1
            owner.context.append(dict(pid=pid,exit=-9 if interrupted else exit_,signal=9 if interrupted else 0,last_barrier='SPAWNED' if interrupted else 'EXITED',
                reaped=True,termination_requested=interrupted,exit_before_cleanup=None if interrupted else exit_))
            if interrupted:raise Failure('child_timeout')
            protocol=dict(result='COMPLETED',value=record['outcome']) if exit_==0 else dict(result='INCOMPLETE',
                diagnostic=dict(phase='snapshot',first_failure=record['first_failure'],secondary=record['secondary'],snapshot=record))
            return dict(pid=pid,exit=exit_,stderr=b'',stdout=b'PRIVATE_MALFORMED_SQL' if protocol_bad else canonical(protocol))
        return action

    def invoke(self,rt,background):
        if background:return campaign.Background(rt,'snapshot',2001,15).join()[0]['value']
        return rt.value('snapshot',15)

    def export_cleanup(self,rt,base,evidence,export_fail=False):
        path=base/'retained';foreign=base/'foreign';foreign.write_bytes(b'untouched');removed=[]
        with mock.patch.object(c.stat,'S_IMODE',return_value=0o600):
            store=c.Retained(path,create=True);op=operator.Operator(rt.req,store,Owner(time.monotonic()+20))
            op.ids={'runtime':'1'*64,'mysql':'2'*64};op.intents=set(op.ids)
            before={p.stem:p.read_bytes() for p in evidence.glob('*.json')}
            def engine(owner,args,**kwargs):
                if args[0]=='inspect':return dict(exit=0,stdout=canonical([container(rt.req,'mysql' if args[1]=='2'*64 else 'runtime')]))
                if args[0]=='rm':
                    # Real operator ownership/config checks ran before this modeled container removal.
                    if not export_fail:self.assertTrue(any(r.get('kind')=='component-evidence' for r in store.records))
                    removed.append(args[-1])
                    if args[-1]=='1'*64:
                        for p in evidence.glob('*.json'):p.unlink()
                        for p in (rt.root/'outputs/2001').glob('*.json'):p.unlink()
                return dict(exit=0,stdout=b'')
            def read(args,**kwargs):
                if args==['--evidence-index']:return dict(exit=0,stdout=canonical(sorted(before)))
                if export_fail:raise OSError('PRIVATE_EXPORT_TOKEN')
                return dict(exit=0,stdout=(evidence/(args[1]+'.json')).read_bytes())
            try:
                with mock.patch.object(operator,'engine',side_effect=engine),mock.patch.object(op,'runtime',side_effect=read):
                    if export_fail:
                        with self.assertRaises(OSError):op.evidence()
                        op.record('export-failed',kind_code='component-capture')
                    else:op.evidence()
                    op.cleanup()
                records=store.read()
                if not export_fail:
                    for row in records:
                        if row['kind']=='component-evidence':
                            self.assertEqual(canonical(row['value']),before[row['name']]);self.assertEqual(row['sha256'],digest(before[row['name']]))
                self.assertEqual(removed,['2'*64,'1'*64]);self.assertEqual(foreign.read_bytes(),b'untouched')
                self.assertFalse(list(evidence.glob('*.json')))
            finally:store.close()
            with_store=c.Retained(path,readonly=True)
            try:self.assertEqual(with_store.records,records)
            finally:with_store.close()
        return records

    def test_actual_php_outcomes_survive_both_paths_export_and_teardown(self):
        for background in (False,True):
            for case,item in self.bundle.items():
                with self.subTest(background=background,case=case),self.lane('snapshot-concurrency' if background else 'positive') as (rt,base,evidence):
                    with mock.patch.object(Owner,'capture',new=self.capture(rt,case)):
                        if case=='success':self.invoke(rt,background)
                        else:
                            with self.assertRaises((ValueError,Failure)):self.invoke(rt,background)
                    raw=(evidence/'snapshot-diagnostic.json').read_bytes();value=decode(raw)
                    self.assertEqual(value['record']['outcome'],item['record']['outcome'])
                    self.assertEqual(value['record']['first_failure'],item['record']['first_failure'])
                    self.assertEqual(value['record']['secondary'],item['record']['secondary'])
                    self.assertEqual(value['result'],'DIAGNOSTIC_ONLY');self.assertEqual(value['deployment_authority'],'NONE')
                    for private in (b'PRIVATE_',b'SELECT ',b'password',b'private-fixture'):self.assertNotIn(private,raw)
                    if case!='success':self.assertEqual(rt.first['code'],item['record']['first_failure'])
                    if case=='combined':self.assertEqual(rt.secondary,[])  # A failed snapshot is not a failed diagnostic collection.
                    self.assertIn('snapshot-diagnostic',rt.receipts)
                    self.export_cleanup(rt,base,evidence)
                    rt.owner.cleanup()

    def test_missing_malformed_oversized_stale_and_wrong_process_outputs_reject(self):
        def change(key,value):
            def mutate(path,record):
                copy_=copy.deepcopy(record)
                if key in copy_['invocation']:copy_['invocation'][key]=value
                else:copy_['process'][key]=value
                path.write_bytes(canonical(copy_))
            return mutate
        cases={'missing':lambda p,r:p.unlink(),'malformed':lambda p,r:p.write_bytes(b'PRIVATE_INVALID'),
            'oversized':lambda p,r:p.write_bytes(b'x'*8193),'stale':change('invocation_id','f'*32),
            'case':change('case','after-notes'),'mode':change('mode','eligibility'),'pid':change('pid',999),'uid':change('uid',0)}
        for background in (False,True):
            for name,mutate in cases.items():
                with self.subTest(background=background,fault=name),self.lane() as (rt,base,evidence):
                    with mock.patch.object(Owner,'capture',new=self.capture(rt,file_mutator=mutate,protocol_bad=True)),self.assertRaises(ValueError):self.invoke(rt,background)
                    v=decode((evidence/'snapshot-diagnostic.json').read_bytes())
                    self.assertEqual(v['capture'],'UNAVAILABLE');self.assertIsNone(v['record']);self.assertEqual(len(v['capture_errors']),2)
                    self.assertNotIn(b'PRIVATE_',canonical(v));rt.owner.cleanup()

    def test_replaced_and_multilink_owned_outputs_reject(self):
        for kind in ('replaced','hardlink'):
            with self.subTest(kind=kind),self.lane() as (rt,base,evidence):
                def mutate(path,record):
                    if kind=='hardlink':os.link(path,base/'foreign-link')
                    else:
                        rt.read_output('snapshot-outcome',2001,max_bytes=8192)
                        path.rename(path.with_suffix('.original'));path.write_bytes(canonical(record))
                with mock.patch.object(Owner,'capture',new=self.capture(rt,file_mutator=mutate,protocol_bad=True)),self.assertRaises(ValueError):self.invoke(rt,False)
                self.assertIsNone(rt.snapshot_evidence['record'])

    def test_valid_outcome_is_collected_despite_malformed_command_protocol(self):
        for background in (False,True):
            with self.lane() as (rt,base,evidence):
                with mock.patch.object(Owner,'capture',new=self.capture(rt,protocol_bad=True)),self.assertRaises(ValueError):self.invoke(rt,background)
                self.assertEqual(rt.snapshot_evidence['record']['outcome']['first_failure'],'snapshot_commit')
                self.assertEqual(rt.snapshot_evidence['record']['outcome']['transaction_cleanup'],'UNRESOLVED')

    def test_real_child_interruption_records_unavailable_without_inventing_sql_cleanup(self):
        original=Owner.capture
        for background in (False,True):
            with self.lane() as (rt,base,evidence):
                def capture(owner,*args,**kwargs):return original(owner,[sys.executable,'-I','-B','-c','import time;time.sleep(5)'],.1)
                with mock.patch.object(Owner,'capture',new=capture),self.assertRaises(Failure):self.invoke(rt,background)
                v=rt.snapshot_evidence;self.assertIsNone(v['record']);self.assertEqual(v['capture'],'UNAVAILABLE')
                self.assertTrue(v['process']['reaped']);self.assertTrue(v['process']['termination_requested']);self.assertIsNotNone(v['process']['exit'])
                self.export_cleanup(rt,base,evidence);rt.owner.cleanup()

    def test_existing_outcome_survives_capture_exception_after_process_exit(self):
        for background in (False,True):
            with self.lane() as (rt,base,evidence):
                action=self.capture(rt)
                def capture(*args,**kwargs):action(*args,**kwargs);raise Failure('child_output_bound')
                with mock.patch.object(Owner,'capture',new=capture),self.assertRaises(Failure):self.invoke(rt,background)
                self.assertEqual(rt.snapshot_evidence['record']['outcome'],self.bundle['combined']['record']['outcome'])

    def test_diagnostic_publication_and_owned_cleanup_failures_preserve_original(self):
        for background in (False,True):
            with self.lane(publish_fail=True) as (rt,base,evidence):
                foreign=base/'foreign';foreign.write_bytes(b'preserve')
                def cleanup():raise RuntimeError('PRIVATE_CLEANUP_SECRET')
                rt.owner.track('owned-probe','file',b'owned-only',cleanup)
                with mock.patch.object(Owner,'capture',new=self.capture(rt)),mock.patch.object(campaign,'Runtime',return_value=rt),\
                     mock.patch.object(campaign,'run_chain',side_effect=lambda value:self.invoke(value,background)),contextlib.redirect_stdout(io.StringIO()):
                    self.assertEqual(campaign.campaign({}),1)
                before=decode((evidence/'before-cleanup.json').read_bytes());report=decode((evidence/'report.json').read_bytes())
                self.assertEqual(before['first_failure']['code'],'snapshot_commit');self.assertEqual(report['first_failure'],before['first_failure'])
                self.assertEqual(report['snapshot_diagnostic']['record']['outcome'],self.bundle['combined']['record']['outcome'])
                self.assertTrue(any(x['kind']=='snapshot-diagnostic-publication' for x in report['secondary']))
                self.assertTrue(any(x['kind']=='owned-cleanup' for x in report['secondary']))
                self.assertEqual(foreign.read_bytes(),b'preserve');self.assertEqual(report['result'],'INCOMPLETE')
                self.export_cleanup(rt,base,evidence)

    def test_export_failure_does_not_change_owned_cleanup_policy(self):
        with self.lane() as (rt,base,evidence):
            with mock.patch.object(Owner,'capture',new=self.capture(rt)),self.assertRaises(ValueError):self.invoke(rt,False)
            original=copy.deepcopy(rt.snapshot_evidence);self.export_cleanup(rt,base,evidence,export_fail=True)
            self.assertEqual(original['record']['outcome'],self.bundle['combined']['record']['outcome'])

    def test_outcome_file_owner_group_and_mode_are_required(self):
        for metadata in ((0,2001,0o600),(2001,0,0o600),(2001,2001,0o644)):
            with self.subTest(metadata=metadata),self.lane(metadata=metadata) as (rt,base,evidence):
                with mock.patch.object(Owner,'capture',new=self.capture(rt,protocol_bad=True)),self.assertRaises(ValueError):self.invoke(rt,False)
                self.assertIsNone(rt.snapshot_evidence['record'])

    def test_outcome_record_vocabulary_bounds_and_privacy_are_strict(self):
        original=copy.deepcopy(self.bundle['combined']['record']);inv=original['invocation'];pid=original['process']['pid']
        self.assertEqual(c.snapshot_record(canonical(original),inv,pid),original)
        mutations=[lambda v:v.update(password='PRIVATE_CANARY'),lambda v:v.update(profile='m6e-bootstrap-v1'),
            lambda v:v.update(first_failure='snapshot_PRIVATE_PASSWORD'),lambda v:v.update(secondary=['db_only_snapshot_outcome_publication']*5),
            lambda v:v['outcome'].update(rows=['PRIVATE_ROW']),lambda v:v['outcome'].update(secondary=['snapshot_PRIVATE_TOKEN']),
            lambda v:v.update(admission='READY'),lambda v:v['invocation']['binding'].update(source_sha='f'*40)]
        for mutate in mutations:
            v=copy.deepcopy(original);mutate(v)
            with self.assertRaises((ValueError,TypeError)):c.snapshot_record(canonical(v),inv,pid)

    def test_concurrency_barrier_failure_still_collects_background_snapshot(self):
        with self.lane(case='snapshot-concurrency') as (rt,base,evidence):
            background=campaign.Background
            class Writer:
                def join(self):return dict(value=dict(transaction='COMMITTED')),12344,0
            def launch(value,mode,uid,seconds):
                return Writer() if mode=='competing-writer' else background(value,mode,uid,seconds)
            def barrier(value,name,uid):
                if name=='snapshot-session':raise ValueError('modeled_barrier_failure')
                return b'{}'
            real_command=rt.command
            def command(mode,*args,**kwargs):
                if mode=='waiter':return dict(value=dict(writer_transaction='OPEN')),12343,0
                return real_command(mode,*args,**kwargs)
            with mock.patch.object(Owner,'capture',new=self.capture(rt)),mock.patch.object(campaign,'Background',side_effect=launch),\
                 mock.patch.object(campaign,'wait_file',side_effect=barrier),mock.patch.object(rt,'command',side_effect=command),self.assertRaises(ValueError):
                campaign.capture_snapshot(rt)
            self.assertEqual(rt.snapshot_evidence['record']['outcome'],self.bundle['combined']['record']['outcome'])
            self.export_cleanup(rt,base,evidence);rt.owner.cleanup()

    def test_actual_operator_execute_exports_before_cleanup_even_when_export_fails(self):
        for export_fail in (False,True):
            with self.subTest(export_fail=export_fail),self.lane() as (rt,base,evidence):
                protocol=io.StringIO()
                with mock.patch.object(Owner,'capture',new=self.capture(rt)),mock.patch.object(campaign,'Runtime',return_value=rt),\
                     mock.patch.object(campaign,'run_chain',side_effect=lambda value:self.invoke(value,False)),contextlib.redirect_stdout(protocol):
                    self.assertEqual(campaign.campaign({}),1)
                before={p.stem:p.read_bytes() for p in evidence.glob('*.json')};containers={};removed=[];exported=[]
                host=base/'host-evidence';host.mkdir();foreign=base/'foreign';foreign.write_bytes(b'keep')
                mysql=measurement('mysql');mysql['namespaces']['pid']='pid:[21]'
                def engine(owner,args,**kwargs):
                    if args[0]=='create':
                        role='runtime' if rt.req['runtime_image'] in args else 'mysql';v=container(rt.req,role)
                        v['Config']['Cmd']=args[args.index(rt.req[role+'_image'])+1:];containers[v['Id']]=v
                        return dict(exit=0,stdout=v['Id'].encode())
                    if args[0]=='inspect':return dict(exit=0,stdout=canonical([containers[args[1]]]))
                    if args[0]=='exec':
                        for mode in ('--measure','--tls','--campaign','--evidence-index','--evidence-read'):
                            if mode not in args:continue
                            if mode=='--measure':value=measurement('runtime')
                            elif mode=='--tls':value={n:'PRIVATE_SYNTHETIC_TLS' for n in ('ca.pem','server.pem','server-key.pem')}
                            elif mode=='--campaign':return dict(exit=1,stdout=protocol.getvalue().encode())
                            elif mode=='--evidence-index':value=sorted(before)
                            else:
                                name=args[-1]
                                if export_fail and name=='snapshot-diagnostic':raise OSError('PRIVATE_EXPORT_ERROR')
                                exported.append(name);return dict(exit=0,stdout=before[name])
                            return dict(exit=0,stdout=canonical(value))
                        if '-Nse' in args:return dict(exit=0,stdout=b'22222222-2222-4222-8222-222222222222')
                    if args[0]=='rm':
                        self.assertIn('before-cleanup',exported);self.assertIn('report',exported)
                        if not export_fail:self.assertIn('snapshot-diagnostic',exported)
                        removed.append(args[-1])
                        if args[-1]=='1'*64:
                            for p in evidence.glob('*.json'):p.unlink()
                    return dict(exit=0,stdout=b'')
                resource=SimpleNamespace(RLIMIT_FSIZE=1,setrlimit=lambda key,value:self.assertEqual(value,(c.LIMITS['retained'],)*2))
                with mock.patch.dict(sys.modules,{'resource':resource}),mock.patch.object(c,'EVIDENCE',str(host)),\
                     mock.patch.object(c.stat,'S_IMODE',return_value=0o600),mock.patch.object(operator,'host_readiness',return_value=dict(images={},host_namespaces=HOST_NS)),\
                     mock.patch.object(operator,'mysql_probe',return_value=mysql),mock.patch.object(operator,'engine',side_effect=engine),contextlib.redirect_stdout(io.StringIO()):
                    self.assertEqual(operator.execute(rt.req),1)
                    saved=c.Retained(host/('m6e-db-only-'+rt.req['run_id']+'.evidence'),readonly=True)
                    try:records=saved.records
                    finally:saved.close()
                self.assertEqual(removed,['2'*64,'1'*64]);self.assertEqual(foreign.read_bytes(),b'keep')
                final=records[-1];self.assertEqual(final['result'],'INCOMPLETE')
                self.assertEqual(any(x['kind']=='component-capture' for x in final['secondary']),export_fail)
                for row in records:
                    if row['kind']=='component-evidence':self.assertEqual(canonical(row['value']),before[row['name']])
                report=next(x['value'] for x in records if x.get('name')=='report')
                self.assertEqual(report['first_failure']['code'],'snapshot_commit')


class SnapshotOrdering(unittest.TestCase):
    """Real campaign/background/owner ordering; SQL outcomes and namespace identities are modeled."""
    lane=SnapshotDiagnostics.lane
    capture=SnapshotDiagnostics.capture
    export_cleanup=SnapshotDiagnostics.export_cleanup
    setUpClass=classmethod(SnapshotDiagnostics.setUpClass.__func__)

    def exercise(self,boundary,earlier=False,publish_fail=False,cleanup_fail=False,reap_fail=False,reader_case=None):
        with self.lane(case='snapshot-concurrency',publish_fail=publish_fail) as (rt,base,evidence):
            ready=threading.Event();reader_release=threading.Event();diagnostic_started=threading.Event();diagnostic_release=threading.Event()
            backgrounds={};events=[];propagated=[];observed=[];test=self
            initiating=Failure('PRIVATE_WRITER') if boundary=='writer-join' else ValueError('PRIVATE_INITIATING')
            prior=None
            if earlier:
                rt.failure(TimeoutError('PRIVATE_EARLIER'),'earlier-phase');prior=copy.deepcopy(rt.first)
            original_capture=Owner.capture;original_popen=subprocess.Popen;original_background=campaign.Background
            original_cleanup=campaign.Background.cleanup;original_join=campaign.Background.join
            original_diagnostic=rt.snapshot_diagnostic;original_publish=rt.publish;original_failure=rt.failure
            child_code='import sys,time;sys.stdout.buffer.write(b"ready");sys.stdout.buffer.flush();time.sleep(20)'

            class ReadyPipe:
                def __init__(self,stream):self.stream=stream
                def read(self,size):
                    value=self.stream.read(size)
                    if value==b'ready':ready.set()
                    return value
                def close(self):self.stream.close()

            def popen(command,**kwargs):
                child=original_popen(command,**kwargs)
                if command[-1]==child_code:child.stdout=ReadyPipe(child.stdout)
                return child

            modeled_capture=self.capture(rt,reader_case) if reader_case else None
            def capture(owner,command,*args,**kwargs):
                if command[-1]=='competing-writer':
                    result=original_capture(owner,[sys.executable,'-I','-B','-c',
                        'print(\'{"result":"COMPLETED","value":{"transaction":"COMMITTED"}}\')'],10)
                    if boundary=='writer-join':
                        test.assertTrue(ready.wait(5));raise initiating
                    return result
                if modeled_capture:
                    ready.set();test.assertTrue(reader_release.wait(5))
                    return modeled_capture(owner,command,*args,**kwargs)
                return original_capture(owner,[sys.executable,'-I','-B','-c',child_code],15)

            def diagnostic(*args):
                diagnostic_started.set()
                if cleanup_fail:test.assertTrue(diagnostic_release.wait(5))
                events.append(('diagnostic-start',copy.deepcopy(rt.first)))
                return original_diagnostic(*args)

            def background(value,mode,uid,seconds):
                obj=original_background(value,mode,uid,seconds);backgrounds[mode]=obj
                if mode=='snapshot' and cleanup_fail:
                    owned_cleanup=obj.owner.cleanup;thread_join=obj.thread.join
                    def failed_cleanup():
                        owned_cleanup();test.assertTrue(diagnostic_started.wait(5))
                        raise Failure('PRIVATE_OWNED_CLEANUP')
                    def join_thread(timeout):
                        diagnostic_release.set();thread_join(timeout)
                        if reap_fail:raise RuntimeError('PRIVATE_REAP_OBSERVATION')
                    obj.owner.cleanup=failed_cleanup;obj.thread.join=join_thread
                return obj

            def barrier(value,name,uid):
                if name=='snapshot-session':
                    test.assertTrue(ready.wait(5));reader=backgrounds['snapshot']
                    test.assertTrue(reader.thread.is_alive())
                    if not reader_case:test.assertIsNone(reader.owner.children[0].poll())
                    if boundary=='reader-before-barrier':
                        reader_release.set();reader.thread.join(5);test.assertFalse(reader.thread.is_alive())
                    if boundary in ('barrier','reader-before-barrier'):raise initiating
                return b'{}'

            def join(obj,*args,**kwargs):
                if obj.mode=='snapshot':reader_release.set()
                return original_join(obj,*args,**kwargs)

            def cleanup(obj):
                if obj.mode=='snapshot':events.append(('cleanup-start',copy.deepcopy(rt.first)))
                return original_cleanup(obj)

            def failure(error,kind):
                original_failure(error,kind);events.append(('failure-'+kind,copy.deepcopy(rt.first)))

            def publish(name,value):
                if name=='snapshot-writer-commit' and boundary=='writer-publication':raise initiating
                return original_publish(name,value)

            def chain(value):
                try:
                    result=campaign.capture_snapshot(value);observed.append(result);return result
                except BaseException as error:propagated.append(error);raise

            with mock.patch.object(Owner,'capture',new=capture),mock.patch.object(subprocess,'Popen',side_effect=popen),\
                 mock.patch.object(campaign,'Background',side_effect=background),mock.patch.object(original_background,'join',new=join),\
                 mock.patch.object(original_background,'cleanup',new=cleanup),mock.patch.object(campaign,'wait_file',side_effect=barrier),\
                 mock.patch.object(rt,'command',return_value=(dict(value=dict(writer_transaction='OPEN')),12343,0)),\
                 mock.patch.object(rt,'snapshot_diagnostic',side_effect=diagnostic),mock.patch.object(rt,'failure',side_effect=failure),\
                 mock.patch.object(rt,'publish',side_effect=publish),mock.patch.object(campaign,'Runtime',return_value=rt),\
                 mock.patch.object(campaign,'run_chain',side_effect=chain),contextlib.redirect_stdout(io.StringIO()):
                try:exit_=campaign.campaign({})
                finally:
                    reader_release.set();diagnostic_release.set()
                    # Always reap this test's actual children even when an assertion fails.
                    for obj in backgrounds.values():
                        Owner.cleanup(obj.owner);threading.Thread.join(obj.thread,5)
                        self.assertFalse(obj.thread.is_alive())
            before=decode((evidence/'before-cleanup.json').read_bytes());report=decode((evidence/'report.json').read_bytes())
            self.assertIsNotNone(report['snapshot_diagnostic'])
            expected=prior or (dict(phase='snapshot',kind='snapshot',code='snapshot_commit') if reader_case=='combined' else
                dict(phase='snapshot',kind='snapshot-concurrency',type=type(initiating).__name__,
                     code='control_failure' if boundary=='writer-join' else 'validation_failed'))
            if reader_case=='success':
                self.assertEqual(exit_,0);self.assertEqual(observed,[self.bundle['success']['record']['outcome']])
                self.assertEqual(report['result'],'DB-ONLY COMPONENT VALIDATION PASS');self.assertIsNone(report['first_failure'])
                self.assertEqual(report['secondary'],[]);self.assertEqual(propagated,[])
            else:
                self.assertEqual(exit_,1);self.assertEqual(report['result'],'INCOMPLETE');self.assertIsNone(report['injected'])
                self.assertEqual(report['first_failure'],expected);self.assertEqual(before['first_failure'],expected)
                self.assertEqual(len(propagated),1)
                self.assertIs(propagated[0],backgrounds['snapshot'].error if boundary=='reader' else initiating)
                if not reader_case:
                    self.assertTrue(any(name=='cleanup-start' for name,first in events))
                    for name,first in events:
                        if name in ('cleanup-start','diagnostic-start'):self.assertEqual(first,expected)
                    failure_index=next(i for i,(name,_) in enumerate(events) if name=='failure-snapshot-concurrency')
                    cleanup_index=next(i for i,(name,_) in enumerate(events) if name=='cleanup-start')
                    self.assertLess(failure_index,cleanup_index)
                    self.assertTrue(any(x.get('code')=='db_only_snapshot_unconfirmed' for x in report['secondary']))
                if earlier:self.assertTrue(any(x['kind']=='snapshot-concurrency' for x in report['secondary']))
            diagnostic_value=report['snapshot_diagnostic'];self.assertIsNotNone(diagnostic_value)
            self.assertEqual(before['snapshot_diagnostic'],diagnostic_value)
            if reader_case:self.assertEqual(diagnostic_value['record']['outcome'],self.bundle[reader_case]['record']['outcome'])
            else:
                self.assertEqual(diagnostic_value['capture'],'UNAVAILABLE');self.assertIsNone(diagnostic_value['record'])
                self.assertTrue(diagnostic_value['process']['reaped']);self.assertTrue(diagnostic_value['process']['termination_requested'])
                self.assertIsNotNone(diagnostic_value['process']['exit']);self.assertEqual(len(diagnostic_value['capture_errors']),2)
            kinds={x['kind'] for x in report['secondary']}
            if publish_fail:
                self.assertIn('snapshot-diagnostic-publication',kinds);self.assertNotIn('snapshot-diagnostic',rt.receipts)
            if cleanup_fail:
                self.assertIn('snapshot-owned-cleanup',kinds);self.assertIn('owned-cleanup',kinds)
                self.assertEqual(next(r['state'] for r in report['context']['resources'] if r['id']=='child-snapshot'),'UNRESOLVED')
            if reap_fail:self.assertIn('background-reap',kinds)
            for private in (b'PRIVATE_',b'SELECT ',b'password',b'private-fixture'):self.assertNotIn(private,canonical(report))
            records=self.export_cleanup(rt,base,evidence)
            self.assertEqual(next(x['value'] for x in records if x.get('name')=='report'),report)
            self.assertEqual(report['deployment_authority'],'NONE');self.assertEqual(report['native_admission'],'BLOCKED')

    def test_barrier_failure_precedes_real_reader_cleanup_and_export(self):self.exercise('barrier')
    def test_writer_join_failure_precedes_real_reader_cleanup_and_export(self):self.exercise('writer-join')
    def test_writer_publication_failure_precedes_real_reader_cleanup(self):self.exercise('writer-publication')
    def test_earlier_recorded_failure_remains_primary(self):self.exercise('barrier',earlier=True)
    def test_reader_own_failure_remains_primary(self):self.exercise('reader',reader_case='combined')
    def test_reader_failure_recorded_before_barrier_remains_primary(self):self.exercise('reader-before-barrier',reader_case='combined')
    def test_cleanup_diagnostic_publication_failure_is_secondary(self):self.exercise('barrier',publish_fail=True)
    def test_cleanup_error_still_joins_diagnostic_before_immutable_capture(self):self.exercise('barrier',cleanup_fail=True)
    def test_cleanup_and_reap_errors_both_survive(self):self.exercise('writer-join',cleanup_fail=True,reap_fail=True)
    def test_successful_concurrent_snapshot(self):self.exercise('success',reader_case='success')


if __name__=='__main__':unittest.main()
