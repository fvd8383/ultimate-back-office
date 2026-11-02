"""Real bootstrap/ledger/render/parser controls; all Linux/SQL/service/UID boundaries modeled."""
import ast
import copy
import json
import os
import re
from pathlib import Path
import shutil
import stat
import sys
import tempfile
import time
import unittest
from unittest import mock
from contextlib import contextmanager, redirect_stdout
import io

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
import WebsitePlatformM6EAcceptance as a
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6EBootstrap as b
from WebsitePlatformM6EBootstrapLedger import Files,Ledger
from WebsitePlatformM6EBootstrapHost import NativeHost
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6ECoordinator import require_native_ready

ROOT=Path(__file__).resolve().parents[1]

def config():
    return dict(version=2,run_id='b'*32,source_sha='a'*40,machine_id='c'*32,boot_id='11111111-1111-4111-8111-111111111111',
        expires_at=int(time.time())+3500,tool_sha256={k:'d'*64 for k in p.BINARIES},auxiliary_sha256={k:'e'*64 for k in p.AUXILIARIES},
        apache_modules={name:dict(path='/usr/lib/apache2/modules/mod_'+name+'.so',sha256='f'*64) for name in p.MODULES})

def fixture():
    shared=a.digest(b'actual model shared asset');pdf=a.digest(b'actual model approved pdf')
    releases=[]
    for i in (1,2):
        releases.append(dict(release_id=i,release_key=str(i)*8+'-1111-4111-8111-'+str(i)*12,build_job_id=i+10,producer_attempt_id=i+20,
            manifest_sha256=a.digest(('manifest'+str(i)).encode()),artifact_sha256=a.digest(('artifact'+str(i)).encode()),file_count=5,
            assets=[dict(sha256=shared,mime_type='image/png',bytes=20),dict(sha256=pdf,mime_type='application/pdf',bytes=30),
                    dict(sha256=a.digest(('css'+str(i)).encode()),mime_type='text/css',bytes=40)]))
    return dict(version=1,run_id='b'*32,site_id=1,site_key='11111111-1111-4111-8111-111111111111',target_id=2,requester_id=3,
       releases=releases,shared_assets=sorted([shared,pdf]),changed_assets=sorted([r['assets'][-1]['sha256'] for r in releases]),
       pdf_assets=[pdf],foreign_asset=dict(site_id=9,asset_id=11,sha256=shared),unpublished=True)

class PrivateFiles(Files):
    """Real private bytes/inodes; Linux owner/mode/socket types explicitly modeled on Windows."""
    def __init__(self,root):self.root=Path(root);self.meta={};self.fail_write=None;self.audit=[]
    def path(self,path):
        p.safe_path(str(path));return self.root/str(path).lstrip('/')
    def identity(self,path,fd=None):
        value=super().identity(path,fd);key=(value['dev'],value['ino'])
        value.update(self.meta.get(key,dict(uid=0,gid=0,mode=0o600 if value['kind']==stat.S_IFREG else 0o755)))
        return value
    def metadata(self,path,uid,gid,mode):
        s=self.path(path).lstat();self.meta[s.st_dev,s.st_ino]=dict(uid=uid,gid=gid,mode=mode)
    def mkdir(self,path,mode):
        super().mkdir(path,mode);self.metadata(path,0,0,mode)
    def create(self,path,raw,identified,outcome=None):
        def initialized(fd):
            # Only a successful exclusive create establishes a new cache entry.
            # Old metadata may remain for a recycled inode; never reset on lookup.
            s=os.fstat(fd)
            self.meta[s.st_dev,s.st_ino]=dict(uid=0,gid=0,mode=0o600,kind=stat.S_IFREG)
            identified(fd)
        if self.fail_write==path:
            self.fail_write=None
            def interrupted(fd):initialized(fd);raise OSError('SECRET_PARTIAL_WRITE_CANARY')
            return super().create(path,raw[:3],interrupted,outcome)
        return super().create(path,raw,initialized,outcome)
    def socket(self,path,uid,gid,mode):
        self.create(path,b'',lambda fd:None);self.metadata(path,uid,gid,mode)
        s=self.path(path).stat();self.meta[s.st_dev,s.st_ino]['kind']=stat.S_IFSOCK
    def unlink(self,path):self.audit.append(('unlink',path));super().unlink(path)
    def rmdir(self,path):self.audit.append(('rmdir',path));super().rmdir(path)

@contextmanager
def stale_creation(files,path):
    """Model recycled metadata after real exclusive open; never invent stat results."""
    opened=os.open;observed=[]
    def opening(name,flags,*args,**kwargs):
        fd=opened(name,flags,*args,**kwargs)
        if os.fspath(name)==os.fspath(files.path(path)) and flags&os.O_EXCL:
            s=os.fstat(fd);stale=dict(uid=0,gid=2102,mode=0o660,kind=stat.S_IFSOCK)
            files.meta[s.st_dev,s.st_ino]=stale.copy()
            observed.append(dict(dev=s.st_dev,ino=s.st_ino,kind=stat.S_IFMT(s.st_mode),stale=stale))
        return fd
    with mock.patch.object(os,'open',side_effect=opening):yield observed

class ModelHost(NativeHost):
    lane='controlled-local'
    def __init__(self,folder):
        fs=PrivateFiles(folder);super().__init__(fs,self.controlled_execute)
        self.cfg=config();self.accounts={};self.units={};self.calls=[];self.failure=None;self.inspection_fault=None;self.cleanup_fault=False
        self.expected_namespace=p.base_identity(self.cfg);self.processes_gone=True
        for path in ('/var/lib','/var/www','/etc','/run/systemd/system','/opt','/usr/local/lib','/usr/local/sbin'):
            fs.path(path).mkdir(parents=True,exist_ok=True)
        names=['infrastructure/deployment/ubo-site-publish','infrastructure/deployment/ubo_site_publish.py',
               'infrastructure/deployment/ubo-site-authority.php','private/classes/SitePublisherHostContract.php']
        self.source_files={name:(ROOT/name).read_bytes() for name in names}
        self.source_files['private/config/env.example.php']=b'<?php return [];\n'
        self.inventory={name:dict(mode='100755' if name.endswith('ubo-site-publish') else '100644',sha256=a.digest(raw)) for name,raw in self.source_files.items()}
        self.tpl=p.templates(lambda name:(ROOT/name).read_bytes())
        self.plan=dict(config=self.cfg,versions={k:dict(sha256=v,version='CONTROLLED MODEL') for k,v in self.cfg['tool_sha256'].items()},
            source=self.inventory,templates=self.tpl,plan_sha256=a.digest(a.canonical(self.inventory)))
        self.db=dict(schema='ubo_m6e_'+self.cfg['run_id'],server_uuid='22222222-2222-4222-8222-222222222222',writer='m6e_writer',worker='m6e_worker',
            authority='m6e_observer',credentials_sha256=a.digest(b'SECRET_AUTHORITY_CANARY'),migrations=list(range(1,26)),seed_247sp=1)
        self.built=fixture()
    def namespace(self,c):a.need(p.base_identity(c)==self.expected_namespace,'base_namespace')
    def preflight(self):
        p.admission(self.cfg);self.namespace(self.cfg)
        a.need(self.cfg['tool_sha256']==config()['tool_sha256'],'tools')
        a.need(not any(self.fs.exists(x) for x in (*p.ROOTS,p.LAUNCHER,*['/run/systemd/system/'+u for u in p.UNITS])),'collision')
        a.need(not self.accounts,'accounts');return copy.deepcopy(self.plan)
    def source(self,root,sha):
        a.need(sha==self.expected_namespace['source_sha'],'source')
        if root==p.CHECKOUT:
            for name,value in self.inventory.items():
                a.need(a.digest(self.fs.read(root+'/'+name))==value['sha256'],'source_bytes')
                a.need(self.fs.identity(root+'/'+name)['mode']==(0o755 if value['mode']=='100755' else 0o644),'source_mode')
        return copy.deepcopy(self.inventory)
    def account(self,role):return copy.deepcopy(self.accounts.get(role,dict(user=None,group=None)))
    def groups(self,role):return {a.ROLES[role],a.ROLES['apache']} if role=='helper' else {a.ROLES[role]}
    def check_pins(self,c):a.need(c==self.cfg,'pins')
    def check_process(self,pid,group,uid):a.need(pid>0 and group.startswith('/system.slice/ubo-m6e-'),'modeled_process')
    def no_role_processes(self,exclude=()):a.need(self.processes_gone,'owned_role_process_remains')
    def check_listener_owners(self,listeners,services):a.need(all(x['inode']>0 for x in listeners),'listener_inode')
    def listeners(self):
        if self.inspection_fault=='listeners':return []
        rows=[]
        if self.units.get('ubo-m6e-mysql.service'):rows.append(dict(protocol='tcp',ip='127.0.0.1',port=3306,uid=2105,inode=1))
        if self.units.get('ubo-m6e-apache.service'):
            rows += [dict(protocol='tcp',ip=ip,port=443,uid=0,inode=2+i) for i,ip in enumerate(('127.0.0.2','127.0.0.3'))]
        return rows
    def put(self,path,raw=b'modeled private bytes',uid=0,gid=0,mode=0o600):
        self.fs.path(path).parent.mkdir(parents=True,exist_ok=True);self.fs.create(path,raw,lambda fd:None);self.fs.metadata(path,uid,gid,mode)
    def controlled_execute(self,args,seconds,stdin=b'',identity=None,limit=None,error_limit=None):
        self.calls.append((copy.deepcopy(args),identity));out=b''
        command=args[0]
        if command.endswith('groupadd'):
            role=args[-1][4:];self.accounts[role]=dict(user=None,group=dict(name=args[-1],gid=int(args[2])))
        elif command.endswith('useradd'):
            role=args[-1][4:];uid=int(args[args.index('--uid')+1]);self.accounts[role]['user']=dict(name=args[-1],uid=uid,gid=uid,
                home='/nonexistent',shell='/usr/sbin/nologin',comment=args[args.index('--comment')+1])
        elif command.endswith('userdel'):self.accounts[args[-1][4:]]['user']=None
        elif command.endswith('groupdel'):self.accounts.pop(args[-1][4:])
        elif command.endswith('/git'):
            if 'clone' in args:
                for name,raw in self.source_files.items():self.put(p.CHECKOUT+'/'+name,raw)
                self.put(p.CHECKOUT+'/.git/config',b'[core]\n')
        elif command.endswith('/openssl'):
            self.put(args[args.index('-out')+1],b'SECRET_TLS_CANARY' if args[1]=='genpkey' else b'CONTROLLED_CERTIFICATE')
        elif command.endswith('/mysqld'):
            self.put(p.STORAGE+'/mysql/auto.cnf',b'CONTROLLED_UUID',2105,2105)
        elif command.endswith('/php'):
            mode=args[-1]
            if mode=='--schema':
                for path,raw in ((p.CONTROL+'/admin.json',b'SECRET_ADMIN_CANARY'),(p.CONTROL+'/writer.json',b'SECRET_WRITER_CANARY'),
                  (p.CONFIG+'/authority-credentials.json',b'SECRET_AUTHORITY_CANARY'),(p.CONFIG+'/application.php',b'SECRET_WORKER_CANARY'),
                  (p.CHECKOUT+'/private/config/env.php',b'<?php return require "/etc/ubo-site-publisher/application.php";'),
                  (p.CONTROL+'/database-identity.json',a.canonical(self.db))):self.put(path,raw)
                out=a.canonical(self.db)
            elif mode=='--build-two':
                for i in (1,2):self.put(p.STORAGE+'/sealed/release-'+str(i),a.canonical(self.built['releases'][i-1]),2000,2000)
                self.put(p.STORAGE+'/work/build-proof',b'WORKER_PRIVATE',2000,2000)
                self.put(p.STORAGE+'/uploads/shared.png',b'SOURCE_PRIVATE',2000,2000)
                out=a.canonical(self.built)
            elif mode=='--inspect':
                value=copy.deepcopy(self.built)
                if self.inspection_fault=='fixture':value['releases'][0]['producer_attempt_id']+=1
                out=a.canonical(dict(fixture=value,database=self.db))
            else:raise AssertionError(args)
        elif command.endswith('/apache2'):
            raw=self.fs.read(p.CONFIG+'/apache.conf');a.need(b'Listen 127.0.0.3:443' in raw and b'LoadModule' in raw,'main_config')
        elif command.endswith('/systemctl'):
            verb=args[1];name=next((x for x in args[2:] if x in p.UNITS),None)
            if verb=='start':
                self.units[name]=True
                if name in p.SOCKETS:
                    path,uid,gid,mode=p.SOCKETS[name];self.fs.socket(path,uid,gid,mode)
                if name=='ubo-m6e-mysql.service':self.fs.socket(p.STORAGE+'/mysql-run/mysql.sock',2105,2105,0o777)
                if name=='ubo-m6e-apache.service':self.put(p.CONTROL+'/apache/apache.pid',b'502')
            elif verb=='stop':
                if self.cleanup_fault:return dict(stdout=b'',stderr=b'SECRET_CLEANUP_CANARY',pid=900,exit=1)
                self.units[name]=False
            elif verb=='show':
                active=self.units.get(name,False)
                if self.inspection_fault==name:active=False
                service=name in ('ubo-m6e-mysql.service','ubo-m6e-apache.service')
                fields=dict(Id=name,LoadState='loaded',ActiveState='active' if active else 'inactive',SubState='running' if active else 'dead',
                    MainPID=str(501 if name=='ubo-m6e-mysql.service' else 502) if service and active else '0',
                    InvocationID=a.digest(name.encode())[:32] if active else '',ControlGroup='/system.slice/'+name if service and active else '',
                    FragmentPath='/run/systemd/system/'+name,DropInPaths='',NextElapseUSecMonotonic='123000')
                if name in p.SOCKETS:fields.update(Listen=p.SOCKETS[name][0]+' (Stream)',Triggers='',Accept='yes',NConnections='0',NAccepted='0',NRefused='0')
                out=('\n'.join(k+'='+v for k,v in fields.items())+'\n').encode()
            elif verb!='daemon-reload':raise AssertionError(args)
        else:raise AssertionError(args)
        if self.failure is not None and self.failure(args):
            self.failure=None;raise Failure('SECRET_COMMAND_FAILURE_CANARY')
        return dict(stdout=out,stderr=b'',pid=900,exit=0)

class Controls(unittest.TestCase):
    def test_new_creation_metadata_precedes_identification_and_failure(self):
        for point in ('normal','callback','injected'):
            with self.subTest(point=point):
                host=self.host();path='/var/lib/new-object';seen=[]
                def identified(fd):
                    seen.append(host.fs.identity(path,fd))
                    self.assertEqual({k:seen[-1][k] for k in ('uid','gid','mode','kind')},
                        dict(uid=0,gid=0,mode=0o600,kind=stat.S_IFREG))
                    if point=='callback':raise OSError('CONTROLLED_CALLBACK_FAILURE')
                if point=='injected':host.fs.fail_write=path
                with stale_creation(host.fs,path) as seeded:
                    if point=='normal':host.fs.create(path,b'new',identified)
                    else:
                        with self.assertRaisesRegex(OSError,'CONTROLLED_CALLBACK_FAILURE|SECRET_PARTIAL_WRITE_CANARY'):
                            host.fs.create(path,b'new',identified)
                self.assertEqual(len(seeded),1);self.assertEqual(len(seen),1)
                self.assertEqual(seeded[0]['kind'],stat.S_IFREG)
                self.assertEqual((seen[0]['dev'],seen[0]['ino']),(seeded[0]['dev'],seeded[0]['ino']))
                self.assertEqual(host.fs.read(path),b'new' if point=='normal' else b'')

    def test_existing_metadata_survives_lookup_collision_rename_and_hardlinks(self):
        host=self.host();path='/var/lib/existing';moved='/var/lib/renamed';link='/var/lib/linked'
        host.fs.socket(path,2103,2102,0o640);identity=host.fs.identity(path);cache=copy.deepcopy(host.fs.meta)
        self.assertEqual(host.fs.identity(path),identity)
        with self.assertRaises(FileExistsError):host.fs.create(path,b'overwrite',lambda fd:self.fail('collision callback'))
        self.assertEqual(host.fs.meta,cache);self.assertEqual(host.fs.path(path).read_bytes(),b'')
        host.fs.path(path).rename(host.fs.path(moved));self.assertEqual(host.fs.identity(moved),identity)
        os.link(host.fs.path(moved),host.fs.path(link))
        self.assertEqual(host.fs.identity(link),identity);self.assertEqual(host.fs.path(link).stat().st_nlink,2)
        with self.assertRaisesRegex(ValueError,'base_read'):host.fs.read(moved)
        host.fs.unlink(moved);self.assertEqual(host.fs.identity(link),identity)
        self.assertEqual(host.fs.meta,cache);self.assertEqual(host.fs.path(link).stat().st_nlink,1)
        # mkdir initializes its successfully created entry; socket sets its role after create.
        directory='/var/lib/directory';host.fs.mkdir(directory,0o700)
        self.assertEqual(host.fs.identity(directory)['kind'],stat.S_IFDIR)
        self.assertEqual(host.fs.identity(directory)['mode'],0o700)

    def host(self):
        folder=tempfile.TemporaryDirectory(prefix='m6e-bootstrap-controls-');self.addCleanup(folder.cleanup)
        return ModelHost(folder.name)
    def armed(self,host):
        plan=host.preflight();ledger=Ledger.create(host.fs,plan['config']);host.ledger=ledger
        ledger.publish('plan',plan)
        for name,raw in p.control_units().items():ledger.write('/run/systemd/system/'+name,raw)
        host.systemctl('daemon-reload');host.start('ubo-m6e-watchdog.timer');ledger.state['watchdog']='ARMED';ledger.save()
        return ledger,plan
    def ready(self):
        host=self.host();ledger,plan=self.armed(host);result=b.prepare_owned(host,ledger,plan);return host,ledger,plan,result

    def test_complete_ordered_real_orchestration_and_readonly_inspection_then_teardown(self):
        host,ledger,plan,result=self.ready()
        self.assertEqual(result['result'],b.TERMINAL);self.assertEqual(result['execution'],'controlled-local')
        self.assertEqual(result['commissioning'],'NOT EXECUTED');self.assertEqual(result['host_receipt'],'ABSENT')
        cmds=[x['id'] for x in ledger.state['commands']]
        self.assertLess(cmds.index('unit-start-ubo-m6e-watchdog'),cmds.index('mysql-initialize'))
        self.assertLess(cmds.index('fixture-schema'),cmds.index('fixture-build-two'))
        self.assertLess(cmds.index('fixture-build-two'),cmds.index('apache-configtest'))
        before={x.name:a.digest(x.read_bytes()) for x in host.fs.path(ledger.root).iterdir() if x.is_file()}
        host.readonly=True;b.inspect_base(host,ledger,plan);b.inspect_base(host,ledger,plan);host.readonly=False
        after={x.name:a.digest(x.read_bytes()) for x in host.fs.path(ledger.root).iterdir() if x.is_file()}
        self.assertEqual(before,after)
        report=host.fs.read(ledger.root+'/bootstrap.json');self.assertEqual(a.decode(report),result)
        removed=b.teardown(host,ledger);self.assertEqual(removed['result'],'REMOVED')
        self.assertEqual(host.fs.read(ledger.root+'/bootstrap.json'),report)
        self.assertTrue(all(x['state']=='RELEASED' for x in ledger.state['entries']))
        count=len(host.fs.audit);self.assertEqual(b.teardown(host,ledger)['result'],'REMOVED');self.assertEqual(len(host.fs.audit),count)
        self.assertFalse(host.accounts)

    def test_wrong_binding_tools_and_paths_reject_before_mutation(self):
        for key,value in (('run_id','a'*32),('machine_id','d'*32),('source_sha','e'*40),('boot_id','bad'),('tool_sha256',{}),('auxiliary_sha256',{})):
            with self.subTest(key=key):
                host=self.host();host.cfg[key]=value
                with self.assertRaises((ValueError,KeyError)):host.preflight()
                self.assertFalse(host.calls);self.assertFalse(host.fs.exists(p.EVIDENCE))
        c=config();c['apache_modules']['ssl']['path']='/tmp/module.so'
        with self.assertRaises(ValueError):p.admission(c)
        for path in ('/etc/passwd','/opt/ubo','/run/systemd/system/other.service','/var/lib/ubo-m6e-fixture/../other'):
            host=self.host();ledger,_=self.armed(host)
            with self.assertRaises(ValueError):ledger.intent(path,'file')

    def test_collisions_all_fixed_roots_units_and_accounts_are_not_adopted(self):
        for path in (*p.ROOTS,p.LAUNCHER,*['/run/systemd/system/'+u for u in p.UNITS]):
            with self.subTest(path=path):
                host=self.host();host.put(path,b'FOREIGN')
                with self.assertRaises(ValueError):host.preflight()
                self.assertEqual(host.fs.read(path),b'FOREIGN');self.assertFalse(host.calls)
        host=self.host();host.accounts['worker']={}
        with self.assertRaises(ValueError):host.preflight()

    def test_partial_command_creation_is_owned_and_cleaned_with_original_error(self):
        predicates=(lambda x:x[0].endswith('useradd'),lambda x:'clone' in x,lambda x:x[0].endswith('openssl'),
                    lambda x:'--initialize-insecure' in x,lambda x:x[-1]=='--schema',lambda x:x[-1]=='--build-two',
                    lambda x:x[:3]==['/usr/bin/systemctl','start','ubo-site-publisher.socket'])
        for predicate in predicates:
            with self.subTest(index=predicates.index(predicate)):
                host=self.host();ledger,plan=self.armed(host);host.failure=predicate
                with self.assertRaisesRegex(Failure,'SECRET_COMMAND_FAILURE_CANARY'):b.prepare_owned(host,ledger,plan)
                self.assertEqual(ledger.state['first_failure']['kind'],'prepare')
                self.assertTrue(all(x['state']=='RELEASED' for x in ledger.state['entries']),ledger.public())
                self.assertFalse(host.accounts)
                exported=b'\n'.join(x.read_bytes() for x in host.fs.path(ledger.root).glob('failure-*.json'))
                self.assertNotIn(b'SECRET',exported)

    def test_partial_file_write_has_identity_before_failure(self):
        host=self.host();ledger,plan=self.armed(host);host.fs.fail_write=p.CONFIG+'/mysql.cnf'
        with self.assertRaisesRegex(OSError,'SECRET_PARTIAL_WRITE_CANARY'):b.prepare_owned(host,ledger,plan)
        row=next(x for x in ledger.state['entries'] if x['path']==p.CONFIG+'/mysql.cnf')
        self.assertIsNotNone(row['identity']);self.assertEqual(row['state'],'RELEASED')

    def test_source_modes_private_secrets_and_configuration_handoff(self):
        host,ledger,plan,result=self.ready()
        self.assertEqual(host.fs.identity(p.CHECKOUT+'/infrastructure/deployment/ubo-site-publish')['mode'],0o755)
        host.check_access(ledger)
        handoff=a.decode(host.fs.read(p.CONTROL+'/bootstrap-handoff.json'))
        self.assertEqual(handoff['fixture'],host.built);self.assertEqual(result['handoff_sha256'],a.digest(a.canonical(handoff)))
        for path in host.fs.path(ledger.root).glob('*.json'):
            self.assertNotIn(b'SECRET_',path.read_bytes(),path.name)
        for args,identity in host.calls:self.assertNotIn('SECRET_',' '.join(args))
        self.assertTrue(any(args[-1]=='--build-two' and identity==(2000,2000,[]) for args,identity in host.calls))
        self.assertFalse(any(args[:3]==['/usr/bin/systemctl','start','ubo-site-worker.service'] for args,_ in host.calls))

    def test_fixture_crosscheck_service_socket_and_receipt_fail_closed(self):
        for fault in ('fixture','listeners','ubo-m6e-apache.service','ubo-site-authority.socket'):
            with self.subTest(fault=fault):
                host=self.host();ledger,plan=self.armed(host);host.inspection_fault=fault
                with self.assertRaises(ValueError):b.prepare_owned(host,ledger,plan)
                self.assertFalse(host.fs.exists(ledger.root+'/bootstrap.json'))
        host,ledger,plan,_=self.ready()
        path=p.SOCKETS['ubo-site-publisher.socket'][0];host.fs.metadata(path,0,0,0o666)
        with self.assertRaises(ValueError):b.inspect_base(host,ledger,plan)
        host,ledger,plan,_=self.ready();host.put(p.CONFIG+'/host-receipt.json',b'{}')
        with self.assertRaisesRegex(ValueError,'host_receipt_must_be_absent'):b.inspect_base(host,ledger,plan)

    def test_capture_failure_cleanup_failure_preserve_primary_and_do_not_claim_ready(self):
        host=self.host();ledger,plan=self.armed(host);host.failure=lambda x:x[-1]=='--build-two';host.cleanup_fault=True
        original=ledger.publish
        def publish(stem,value):
            if stem.startswith(('failure-','before-teardown-')):raise OSError('SECRET_CAPTURE_CANARY')
            return original(stem,value)
        with mock.patch.object(ledger,'publish',side_effect=publish):
            with self.assertRaisesRegex(Failure,'SECRET_COMMAND_FAILURE_CANARY'):b.prepare_owned(host,ledger,plan)
        self.assertEqual(ledger.state['first_failure']['kind'],'prepare')
        self.assertIn('capture',[x['kind'] for x in ledger.state['secondary']]);self.assertIn('cleanup',[x['kind'] for x in ledger.state['secondary']])
        self.assertTrue(any(args[1]=='stop' for args,_ in host.calls if args[0].endswith('systemctl')))
        self.assertFalse(host.fs.exists(ledger.root+'/bootstrap.json'))

    def test_snapshot_bytes_precede_restoration_and_replacement_is_preserved(self):
        host,ledger,plan,_=self.ready();marker=p.CONFIG+'/mysql.cnf';before=host.fs.read(marker)
        original=ledger.publish;captured=[]
        def publish(stem,value):
            receipt=original(stem,value)
            if stem.startswith('before-teardown-'):
                self.assertEqual(host.fs.read(marker),before);captured.append(host.fs.read(ledger.root+'/'+receipt['file']))
            return receipt
        with mock.patch.object(ledger,'publish',side_effect=publish):b.teardown(host,ledger)
        self.assertEqual(len(captured),1);self.assertFalse(host.fs.exists(marker))
        host.put(marker,b'REPLACEMENT')
        with self.assertRaises(Failure):b.teardown(host,ledger)
        self.assertEqual(host.fs.read(marker),b'REPLACEMENT')

    def test_unknown_process_or_replaced_resource_prevents_destructive_cleanup(self):
        host,ledger,plan,_=self.ready();host.processes_gone=False
        before=host.fs.read(p.CONFIG+'/mysql.cnf')
        with self.assertRaises(Failure):b.teardown(host,ledger)
        self.assertEqual(host.fs.read(p.CONFIG+'/mysql.cnf'),before);self.assertTrue(host.accounts)
        host.processes_gone=True;path=p.CONFIG+'/mysql.cnf';old=host.fs.path(path).with_suffix('.old');host.fs.path(path).rename(old)
        host.put(path,b'REPLACED')
        with self.assertRaises(Failure):b.teardown(host,ledger)
        self.assertEqual(host.fs.read(path),b'REPLACED')

    def test_bootstrap_never_native_acceptance_and_full_entry_still_blocked(self):
        host,ledger,plan,result=self.ready()
        with self.assertRaises(ValueError):a.validate(result,{},native=True)
        with self.assertRaisesRegex(Failure,'native_implementation_incomplete'):require_native_ready()
        with mock.patch.object(b,'NativeHost',side_effect=AssertionError('host constructed')),redirect_stdout(io.StringIO()):
            self.assertEqual(b.main(['--plan']),0)

    def test_defined_launcher_runs_complete_supervised_body_with_controlled_dispatch(self):
        host=self.host()
        @contextmanager
        def lock(adapter,ledger,create=False):
            self.assertTrue(create)
            yield
            # Model only the systemd dispatch/lock handoff; execute the actual supervised body.
            if adapter.units.get('ubo-m6e-bootstrap.service') and Ledger.load(adapter.fs,adapter.cfg).state['phase']=='admitted':
                active=Ledger.load(adapter.fs,adapter.cfg)
                b.prepare_owned(adapter,active,host.plan)
                adapter.units['ubo-m6e-bootstrap.service']=False
        with mock.patch.object(b,'lifecycle_lock',side_effect=lock):result=b.launch(host)
        self.assertEqual(result['result'],b.TERMINAL)
        loaded=Ledger.load(host.fs,host.cfg)
        self.assertEqual(loaded.state['phase'],'ready')
        ids={x['id'] for x in loaded.state['commands']}
        self.assertTrue({'group-create','user-create','checkout-clone','checkout-detach','tls-key','tls-ca','tls-request','tls-certificate',
            'mysql-initialize','fixture-schema','fixture-build-two','apache-configtest','fixture-inspect','bootstrap-start'}<=ids)
        self.assertFalse(host.fs.exists(p.CONFIG+'/host-receipt.json'))

    def test_prepare_repeated_rejects_without_restoration_or_writes(self):
        host,ledger,plan,_=self.ready();before=host.fs.read(ledger.path);count=len(host.fs.audit)
        with self.assertRaisesRegex(ValueError,'bootstrap_already_started'):b.prepare_owned(host,ledger,plan)
        with self.assertRaises(ValueError):b.launch(host)
        self.assertEqual(host.fs.read(ledger.path),before);self.assertEqual(len(host.fs.audit),count)

    def test_watchdog_quiesce_does_not_overwrite_newer_preparation_ledger(self):
        host,ledger,plan,_=self.ready()
        ledger.state['services'].append(dict(unit='ubo-m6e-bootstrap.service',state='STARTING',pid=None,invocation=None,cgroup=None));ledger.save()
        host.units['ubo-m6e-bootstrap.service']=True
        old_sequence=ledger.state['sequence'];original=host.execute
        changed=[]
        def concurrent(args,*rest,**kwargs):
            if args[:2]==['/usr/bin/systemctl','stop'] and not changed:
                fresh=Ledger.load(host.fs,host.cfg);fresh.state['phase']='newer-observed-phase';fresh.save();changed.append(True)
            return original(args,*rest,**kwargs)
        host.execute=concurrent
        stopped,commands,failures=b.quiesce(host,ledger)
        self.assertFalse(failures);self.assertIn('ubo-m6e-bootstrap.service',stopped)
        self.assertTrue(host.units['ubo-m6e-watchdog.timer'])
        current=Ledger.load(host.fs,host.cfg)
        self.assertEqual(current.state['phase'],'newer-observed-phase');self.assertGreater(current.state['sequence'],old_sequence)
        self.assertTrue(commands);self.assertTrue(list(host.fs.path(ledger.root).glob('quiesce-result-*.json')))

    def test_unsafe_account_socket_and_source_observations_reject(self):
        for kind in ('account','source','private-mode'):
            with self.subTest(kind=kind):
                host,ledger,plan,_=self.ready()
                if kind=='account':host.accounts['worker']['user']['uid']=1234
                elif kind=='source':host.fs.path(p.CHECKOUT+'/private/classes/SitePublisherHostContract.php').write_bytes(b'changed')
                else:host.fs.metadata(p.STORAGE+'/sealed/release-1',2000,2000,0o644)
                with self.assertRaises(ValueError):b.inspect_base(host,ledger,plan)

    def test_two_release_and_schema_results_cannot_be_completed_from_expected_values(self):
        for change in ('producer','shared','changed','pdf','unpublished'):
            value=fixture()
            if change=='producer':value['releases'][1]['producer_attempt_id']=value['releases'][0]['producer_attempt_id']
            elif change=='unpublished':value['unpublished']=False
            else:value[change+'_assets']=[]
            with self.subTest(change=change),self.assertRaises(ValueError):p.validate_fixture(value,'b'*32)
        host=self.host()
        for key,value in (('migrations',list(range(1,25))),('seed_247sp',0),('schema','customer'),('worker','m6e_writer')):
            with self.subTest(key=key),self.assertRaises(ValueError):b.schema_identity(host.db|{key:value},host.cfg)

    def test_incomplete_tree_capture_keeps_original_command_exception(self):
        host=self.host();ledger,plan=self.armed(host);host.failure=lambda args:'clone' in args
        original=ledger.capture_tree
        def broken(row,allowed,final=False):
            if row['producer']=='git':raise OSError('SECRET_SNAPSHOT_FAILURE')
            return original(row,allowed,final=final)
        with mock.patch.object(ledger,'capture_tree',side_effect=broken):
            with self.assertRaisesRegex(Failure,'SECRET_COMMAND_FAILURE_CANARY'):b.prepare_owned(host,ledger,plan)
        self.assertEqual(ledger.state['first_failure']['kind'],'prepare')
        self.assertIn('capture',[x['kind'] for x in ledger.state['secondary']])

    def test_checked_bootstrap_handoff_and_runtime_invocation_cannot_be_replaced(self):
        host,ledger,plan,result=self.ready();host.readonly=True
        self.assertEqual(b.inspect_ready(host,ledger,plan),result)
        raw=host.fs.read(ledger.root+'/bootstrap.json');host.fs.path(ledger.root+'/bootstrap.json').write_bytes(raw+b' ')
        with self.assertRaisesRegex(ValueError,'bootstrap_publication'):b.inspect_ready(host,ledger,plan)
        host.fs.path(ledger.root+'/bootstrap.json').write_bytes(raw)
        original=host.unit
        def unit(name):
            result=original(name)
            if name=='ubo-m6e-mysql.service':result['InvocationID']='f'*32
            return result
        with mock.patch.object(host,'unit',side_effect=unit):
            with self.assertRaisesRegex(ValueError,'service_invocation_changed'):b.inspect_ready(host,ledger,plan)

    def test_retired_or_new_children_are_not_adopted_after_producer_is_sealed(self):
        host=self.host();ledger,_=self.armed(host)
        row=ledger.directory(p.STORAGE,2000,2000,0o700,producer='artifact-builder')
        host.put(p.STORAGE+'/owned',b'first',2000,2000)
        ledger.capture_tree(row,{2000},final=True);host.fs.unlink(p.STORAGE+'/owned');ledger.capture_tree(row,{2000},final=True)
        host.put(p.STORAGE+'/owned',b'replacement',2000,2000)
        with self.assertRaisesRegex(ValueError,'retired_child_replaced'):ledger.capture_tree(row,{2000},final=True)
        host.fs.unlink(p.STORAGE+'/owned');host.put(p.STORAGE+'/new',b'unknown',2000,2000)
        with self.assertRaisesRegex(ValueError,'unowned_new_child'):ledger.capture_tree(row,{2000},final=True)

    def test_partial_account_removal_is_persisted_and_retry_skips_removed_account(self):
        host,ledger,_,_=self.ready();host.failure=lambda args:args[0].endswith('userdel')
        with self.assertRaises(Failure):b.teardown(host,ledger)
        row=next(x for x in ledger.state['accounts'] if x['role']=='writer')
        self.assertIsNone(row['identity']['user']);self.assertIsNotNone(row['identity']['group'])
        count=sum(args[0].endswith('userdel') and args[-1]=='m6e-writer' for args,_ in host.calls)
        self.assertEqual(b.teardown(host,ledger)['result'],'REMOVED')
        self.assertEqual(sum(args[0].endswith('userdel') and args[-1]=='m6e-writer' for args,_ in host.calls),count)

    def test_public_teardown_dispatches_fixed_service_without_stale_ledger_writes(self):
        host,ledger,_,_=self.ready();original=host.execute;dispatched=[]
        def execute(args,*rest,**kwargs):
            if args[:3]==['/usr/bin/systemctl','start','ubo-m6e-watchdog.service']:
                self.assertTrue(host.readonly);dispatched.append(True)
                child=copy.copy(host);child.readonly=False;child.execute=original;child.pre_commands=[]
                current=Ledger.load(host.fs,host.cfg);child.ledger=current
                b.teardown(child,current)
                return dict(stdout=b'',stderr=b'',pid=901,exit=0)
            return original(args,*rest,**kwargs)
        host.execute=execute
        self.assertEqual(b.supervised_teardown(host,ledger)['result'],'REMOVED');self.assertEqual(dispatched,[True])
        current=Ledger.load(host.fs,host.cfg)
        self.assertEqual(current.state['phase'],'removed');self.assertEqual(current.state['watchdog'],'DISARMED')
        self.assertTrue(all(x['state']=='RELEASED' for x in current.state['entries']))

    def test_failed_phase_capture_still_cleans_and_command_error_survives_journal_failure(self):
        host,ledger,plan,_=self.ready();original=ledger.phase
        def phase(name):
            if name=='teardown':raise OSError('PRIVATE_PHASE_FAILURE')
            return original(name)
        with mock.patch.object(ledger,'phase',side_effect=phase),self.assertRaisesRegex(Failure,'bootstrap_cleanup_limited'):
            b.teardown(host,ledger)
        self.assertFalse(host.accounts);self.assertFalse(host.fs.exists(p.CHECKOUT))
        self.assertEqual(ledger.state['first_failure']['kind'],'capture')
        host=self.host();ledger,plan=self.armed(host);save=ledger.save;calls=[]
        def interrupted():
            calls.append(True)
            if len(calls)>1:raise OSError('PRIVATE_JOURNAL_FAILURE')
            return save()
        host.execute=lambda *args,**kwargs:(_ for _ in ()).throw(Failure('PRIVATE_ORIGINAL_FAILURE'))
        with mock.patch.object(ledger,'save',side_effect=interrupted),self.assertRaisesRegex(Failure,'PRIVATE_ORIGINAL_FAILURE'):
            host.run('test-command',['/usr/bin/php','-v'])
        self.assertEqual(ledger.state['first_failure']['kind'],'command')
        self.assertEqual(ledger.state['secondary'][0]['kind'],'capture')

    def test_evidence_directory_replacement_or_access_change_is_rejected(self):
        host=self.host();ledger,plan=self.armed(host)
        original=host.fs.path(ledger.root);preserved=original.with_name('retained-original');original.rename(preserved)
        original.mkdir();shutil.copyfile(preserved/'ownership.json',original/'ownership.json')
        host.fs.metadata(ledger.root,0,0,0o700)
        with self.assertRaisesRegex(ValueError,'evidence_identity'):Ledger.load(host.fs,host.cfg)
        with self.assertRaisesRegex(ValueError,'evidence_identity'):ledger.publish('must-not-publish',{})

    def test_render_complete_main_fixed_units_and_adapter_argument_rejection(self):
        host=self.host();text=p.apache_main(host.cfg,host.tpl['apache'],fixture()['site_key']).decode()
        for wanted in ('Listen 127.0.0.2:443','Listen 127.0.0.3:443','<VirtualHost 127.0.0.3:443>','SSLVerifyClient require','Require all denied','LoadModule ssl_module'):
            self.assertIn(wanted,text)
        self.assertNotRegex(text,r'@[A-Z0-9_]+@')
        units=p.fixture_units(host.tpl,host.cfg,fixture()['site_key'])|p.control_units();self.assertEqual(set(units),set(p.UNITS))
        for name in p.SOCKETS:
            self.assertIn(b'Accept=yes',units[name]);self.assertNotIn(b'\nService=',units[name])
        self.assertIn(b'TimeoutStartSec=600',units['ubo-m6e-bootstrap.service']);self.assertIn(b'OnActiveSec=1200',units['ubo-m6e-watchdog.timer'])
        for verb,unit in (('restart','ubo-m6e-mysql.service'),('start','ssh.service'),('start','x;reboot')):
            with self.assertRaises(ValueError):host.systemctl(verb,unit)
        with self.assertRaises(ValueError):host.setup('free-command')
        with self.assertRaises(ValueError):host.run('bad',['/bin/sh','-c','true'])
        with self.assertRaises(ValueError):NativeHost.parse_unit(b'Id=x\nId=y\n')

    def socket_template_copy(self):
        folder=tempfile.TemporaryDirectory(prefix='m6e-socket-policy-');self.addCleanup(folder.cleanup)
        root=Path(folder.name)
        for name in (*p.SOCKETS,*[x.replace('.socket','@.service') for x in p.SOCKETS],'ubo-site-worker.service'):
            path='infrastructure/deployment/'+name+'.example';target=root/path
            target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes((ROOT/path).read_bytes())
        path='infrastructure/apache/site-static-staging.conf.example';target=root/path
        target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes((ROOT/path).read_bytes())
        return root

    def test_socket_canonical_templates_render_without_policy_repairs(self):
        root=self.socket_template_copy();raw=p.templates(lambda name:(root/name).read_bytes())
        values=p.template_values(fixture()['site_key']);units=p.fixture_units(raw,config(),fixture()['site_key'])
        for name,(path,user,group,mode) in {
            'ubo-site-authority.socket':('/run/ubo-site-publisher/authority.sock','m6e-helper','m6e-helper','0600'),
            'ubo-site-publisher.socket':('/run/ubo-site-publisher/publish.sock','root','m6e-worker','0660')}.items():
            with self.subTest(name=name):
                self.assertEqual(units[name],p.render(raw[name],values))
                lines=units[name].decode().splitlines();socket=lines.index('[Socket]')
                expected=['Accept=yes','ListenStream='+path,'SocketUser='+user,'SocketGroup='+group,
                          'SocketMode='+mode,'DirectoryMode=0755','MaxConnections=4']
                for line in expected:self.assertEqual(lines.count(line),1);self.assertGreater(lines.index(line),socket)
                self.assertFalse(any(line.startswith('Service=') for line in lines));self.assertNotIn('[Install]',lines)
                self.assertNotRegex(units[name].decode(),r'@[A-Z0-9_]+@')
                service=name.replace('.socket','@.service')
                self.assertTrue((root/'infrastructure/deployment'/ (service+'.example')).is_file())
                self.assertEqual(units[service],p.render(raw[service],values))
                for directive in ('StandardInput=socket','StandardOutput=socket','StandardError=journal','Type=exec',
                                  'NoNewPrivileges=yes','CapabilityBoundingSet=','ProtectSystem=strict','ProtectHome=yes',
                                  'PrivateTmp=yes','PrivateDevices=yes','KillMode=control-group','UMask=0077','TasksMax=4'):
                    self.assertEqual(units[service].decode().splitlines().count(directive),1)
                authority=name=='ubo-site-authority.socket'
                for directive in (('User=m6e-authority','Group=m6e-authority','RuntimeMaxSec=10','TimeoutStopSec=2','MemoryMax=128M',
                                   'RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6') if authority else
                                  ('User=m6e-helper','Group=m6e-helper','SupplementaryGroups=m6e-apache','RuntimeMaxSec=300',
                                   'TimeoutStopSec=5','MemoryMax=256M','RestrictAddressFamilies=AF_UNIX','AmbientCapabilities=',
                                   'SendSIGKILL=yes','RestrictSUIDSGID=yes','LockPersonality=yes')):
                    self.assertIn(directive,units[service].decode().splitlines())
                if not authority:
                    for directive in ('InaccessiblePaths=','ReadWritePaths='):
                        self.assertEqual(sum(line.startswith(directive) for line in units[service].decode().splitlines()),1)

    def test_socket_policy_rejects_private_template_mutations_without_repair(self):
        for name in p.SOCKETS:
            original=(ROOT/'infrastructure/deployment'/(name+'.example')).read_text()
            mutations={
                'old-service':original+'\nService='+name.replace('.socket','@.service')+'\n',
                'wrong-accept':original.replace('Accept=yes','Accept=no'),
                'duplicate-accept':original+'\nAccept=yes\n',
                'duplicate-user':original+'\nSocketUser=root\n',
                'duplicate-group':original+'\nSocketGroup=root\n',
                'wide-mode':re.sub(r'SocketMode=0[0-7]+','SocketMode=0666',original),
                'wrong-listener':re.sub(r'ListenStream=.*','ListenStream=/run/foreign.sock',original),
                'wrong-owner':re.sub(r'SocketUser=.*','SocketUser=nobody',original),
                'wrong-group':re.sub(r'SocketGroup=.*','SocketGroup=nobody',original),
                'connection-limit':original.replace('MaxConnections=4','MaxConnections=400'),
                'wide-directory':original.replace('DirectoryMode=0755','DirectoryMode=0777'),
                'wrong-section':original.replace('[Socket]','[Install]'),
                'extra-section':original+'\n[Socket]\nSocketMode=0666\n',
                'extra-listener':original+'\nListenDatagram=127.0.0.1:1234\n',
            }
            for label,changed in mutations.items():
                with self.subTest(name=name,mutation=label):
                    root=self.socket_template_copy();path=root/'infrastructure/deployment'/(name+'.example')
                    path.write_text(changed);before=path.read_bytes()
                    with self.assertRaises(ValueError):p.templates(lambda n:(root/n).read_bytes())
                    # Direct rendering must reject the same private invalid input, never silently repair it.
                    raw=p.templates(lambda n:(ROOT/n).read_bytes());raw[name]=path.read_text()
                    with self.assertRaises(ValueError):p.fixture_units(raw,config(),fixture()['site_key'])
                    self.assertEqual(path.read_bytes(),before)

    def test_socket_policy_rejects_changed_names_and_rendered_access(self):
        root=self.socket_template_copy();raw=p.templates(lambda name:(root/name).read_bytes())
        for name in p.SOCKETS:
            for wrong in (name,name.replace('.socket','@.service')):
                with self.subTest(name=wrong):
                    changed=dict(raw);changed['renamed-'+wrong]=changed.pop(wrong)
                    with self.assertRaises(ValueError):p.fixture_units(changed,config(),fixture()['site_key'])
            values=p.template_values(fixture()['site_key']);units=p.fixture_units(raw,config(),fixture()['site_key'])
            rendered={key:units[key] for key in raw if key!='apache'}
            path=root/'rendered.socket';path.write_bytes(rendered[name]+b'\nSocketGroup=root\n')
            rendered[name]=path.read_bytes()
            with self.assertRaises(ValueError):p.socket_policy(rendered,values)
        service=root/'infrastructure/deployment/ubo-site-authority@.service.example'
        service.rename(service.with_name('wrong@.service.example'))
        with self.assertRaises(FileNotFoundError):p.templates(lambda name:(root/name).read_bytes())

    def test_idle_accepting_sockets_have_no_triggered_instances_or_connections(self):
        host,ledger,plan,_=self.ready();unit=host.unit;name='ubo-site-publisher.socket'
        for key,value in (('Accept','no'),('NAccepted','1'),('NConnections','1'),('NRefused','1'),
                          ('Triggers','ubo-site-publisher@0.service'),('Listen','/tmp/foreign (Stream)')):
            with self.subTest(key=key),mock.patch.object(host,'unit',side_effect=lambda n:unit(n)|({key:value} if n==name else {})):
                with self.assertRaisesRegex(ValueError,'socket_unit'):host.service_observation(name)

    def test_failed_child_context_and_account_capture_preserve_original_failure(self):
        host=self.host();ledger,plan=self.armed(host)
        def execute(*args,**kwargs):
            host.owner.context.append(dict(pid=8123,exit=-9));raise Failure('PRIVATE_CHILD_FAILURE')
        host.execute=execute
        with self.assertRaisesRegex(Failure,'PRIVATE_CHILD_FAILURE'):host.run('context-test',['/usr/bin/php','-v'])
        self.assertEqual((ledger.state['commands'][-1]['pid'],ledger.state['commands'][-1]['exit']),(8123,-9))
        account=host.account;reads=[]
        def observe(role):
            reads.append(True)
            if len(reads)>1:raise OSError('PRIVATE_ACCOUNT_CAPTURE_FAILURE')
            return account(role)
        with mock.patch.object(host,'account',side_effect=observe),self.assertRaisesRegex(Failure,'PRIVATE_CHILD_FAILURE'):
            host.create_account('worker',host.cfg['run_id'])
        self.assertEqual(ledger.state['first_failure']['kind'],'prepare')
        self.assertEqual(ledger.state['secondary'][0]['kind'],'capture')

if __name__=='__main__':unittest.main(verbosity=2)
