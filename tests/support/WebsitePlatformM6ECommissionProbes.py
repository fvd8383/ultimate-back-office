"""Native component producers for the current bootstrap handoff (never run locally).

Filesystem and role access have complete producers. Other gates retain actual
bounded prerequisite measurements and explicit missing executable steps. They cannot
issue a host receipt or satisfy the full native readiness inventory.
"""
import copy
import os
from pathlib import PurePosixPath
import re
import stat
import time

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, keys
from WebsitePlatformM6EApache import fetch
from WebsitePlatformM6EProvision import Provisioner, FixedStorage, COMMANDS
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECommissionEvidence as evidence
from WebsitePlatformM6ECommissionReceipt import protected_file

def scratch_inventory(host,ledger,resource):
    row=ledger.state['entries'][resource['entry']];ledger.verify(row)
    root=host.fs.path(row['path']);found={};site=ledger.state['observations']['fixture']['site_key']
    allowed={'manifests','manifests/'+site+'.json','object','replacement','old','new','pointer','next','escape'}
    for directory,dirs,files in os.walk(root,followlinks=False):
        for name in dirs+files:
            path=type(root)(directory)/name;relative=path.relative_to(root).as_posix();s=path.lstat()
            need(relative in allowed or re.fullmatch(r'immutable-[a-f0-9]{32}\.pending',relative),'scratch_unowned_name')
            need(s.st_uid==ROLES['helper'] and s.st_dev==row['identity']['dev']
                 and (stat.S_ISDIR(s.st_mode) or s.st_nlink==1),'scratch_object')
            kind=stat.S_IFMT(s.st_mode);target=os.readlink(path) if kind==stat.S_IFLNK else None
            need(kind in (stat.S_IFDIR,stat.S_IFREG,stat.S_IFLNK),'scratch_kind')
            if target is not None:need((relative in ('pointer','next') and target in ('old','new'))
                                     or (relative=='escape' and target=='/etc/passwd'),'scratch_link')
            found[relative]=dict(dev=s.st_dev,ino=s.st_ino,uid=s.st_uid,gid=s.st_gid,mode=s.st_mode,target=target)
            need(len(found)<=16,'scratch_bound')
    return found

def cleanup_owned(host,ledger,gate=None):
    """Also used by the existing watchdog after owned services have stopped.

    Never follows a scratch symlink. Unknown/replaced objects leave cleanup LIMITED.
    Original ledger entries/metadata rules are unchanged.
    """
    state=ledger.state['observations'].get('commission',{})
    for resource in reversed(state.get('resources',[])):
        if gate is not None and resource['gate']!=gate:continue
        row=ledger.state['entries'][resource['entry']]
        if resource['kind']=='scratch' and row['state']!='RELEASED':
            observed=scratch_inventory(host,ledger,resource)
            if resource.get('children') is None:
                # Recovery after a dead, exclusively owned component producer.
                # Capture exact original observations before removal; no adoption
                # of arbitrary paths/owners or unexpected scratch names.
                resource['children']=observed;ledger.save()
                ledger.publish('commission-scratch-before-cleanup-'+str(ledger.state['sequence']),
                    dict(binding=state['binding'],children=observed))
            need(observed==resource['children'],'scratch_replaced')
            for name,item in sorted(observed.items(),key=lambda x:x[0].count('/'),reverse=True):
                path=host.fs.path(row['path']+'/'+name);s=path.lstat()
                need((s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)==
                     (item['dev'],item['ino'],item['mode'],item['uid'],item['gid']),'scratch_cleanup_identity')
                path.rmdir() if stat.S_ISDIR(s.st_mode) else path.unlink()
                # Persist progress so a bounded cleanup retry need not adopt a replacement.
                del resource['children'][name];ledger.save()
        if row['identity'] is None and host.fs.exists(row['path']):
            # Only an exclusive role-create attempt whose fixed name was registered
            # before spawning may acquire a newly created leaf (never a directory).
            need(resource['kind']=='access-attempt','commission_unidentified_resource')
            info=host.fs.identity(row['path'])
            need(info['kind']==stat.S_IFREG and info['uid']==resource['uid'] and info['mode']==0o600
                 and host.fs.path(row['path']).lstat().st_nlink==1,'access_attempt_identity')
            ledger.identify(row)
        ledger.release(row)

class NativeProbes:
    execution='native-components'
    def __init__(self,host,ledger,plan,history):
        self.host,self.ledger,self.plan,self.history=host,ledger,plan,history
        self.fixture=ledger.state['observations']['fixture']
        self.worker=decode(host.fs.read(p.CONFIG+'/worker.json'))
        self.helper=decode(host.fs.read(p.CONFIG+'/helper.json'))['bindings'][0]
        self.partial={};self.participants=[];self.binding=None

    def stage_seconds(self,gate):return 45 if gate in ('apache','runtime','ownership','filesystem','provisioning') else 20

    def child(self,role,mode,php=False,stdin=b''):
        name='WebsitePlatformM6ECommissionChild.'+('php' if php else 'py')
        args=(['/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','zend.exception_ignore_args=1','-d','memory_limit=256M']
              if php else ['/usr/bin/python3','-I','-B'])+[p.CHECKOUT+'/tests/support/'+name,mode]
        r=self.host.run('commission-'+mode[2:],args,30,role,stdin=stdin,return_result=True)
        need(not r['stderr'],'commission_child_diagnostic')
        value=decode(r['stdout']);need(type(value) is dict,'commission_child_json')
        if 'pid' in value:
            need(value['pid']==r['pid'] and value['uid']==ROLES[role],'commission_child_process')
        self.participants.append(dict(role=role,uid=ROLES[role],pid=r['pid'],exit=r['exit']))
        return value

    def loaded_files(self):
        return {role:self.child(role,'--inventory-'+role,True)['files'] for role in ('worker','authority')}

    def roots(self):
        names=(self.helper['served_root'],self.helper['journal_root'],p.CONFIG,p.CHECKOUT,p.STORAGE+'/sealed')
        return digest(canonical({name:self.host.fs.identity(name) for name in names}))

    def own(self,gate,path,kind='directory',uid=None,gid=None,mode=0o700,raw=None):
        # Persist the resource association before creation/command effects.
        row=self.ledger.intent(path,'directory' if kind in ('directory','scratch') else 'file','commission-'+gate)
        resource=dict(gate=gate,entry=row['id'],kind=kind,uid=uid,children=None)
        self.ledger.state['observations']['commission']['resources'].append(resource);self.ledger.save()
        if kind=='access-attempt':return row
        if kind in ('directory','scratch'):self.host.fs.mkdir(path,0o700);self.ledger.identify(row)
        else:self.host.fs.create(path,raw,lambda fd:self.ledger.identify(row,fd))
        self.host.fs.metadata(path,uid,gid,mode);self.ledger.identify(row)
        if raw is not None:row['sha256']=digest(raw);self.ledger.save()
        return row

    def probe(self,gate):
        need(gate in evidence.PRODUCERS,'commission_gate');self.participants=[]
        primary=None
        try:
            data=getattr(self,'probe_'+gate)()
            evidence.measurements(gate,data);self.partial['data']=copy.deepcopy(data)
            # Independent worker process verifies unchanged fixture bytes/producer
            # identity and the actual zero-deployment database pointer.
            actual=self.host.setup('inspect','worker')
            need(actual==dict(fixture=self.fixture,database=self.ledger.state['observations']['database']),'commission_independent_fixture')
            command=self.ledger.state['commands'][-1]
            self.participants.append(dict(role='worker',uid=ROLES['worker'],pid=command['pid'],exit=command['exit']))
            return dict(pid=os.getpid(),uid=os.geteuid(),data=data,participants=copy.deepcopy(self.participants),
                independent=dict(pid=command['pid'],state_sha256=self.roots(),roles_sha256=digest(canonical(ROLES))))
        except BaseException as error:primary=error;raise
        finally:
            try:
                for resource in self.ledger.state['observations']['commission']['resources']:
                    if resource['gate']==gate and resource['kind']=='scratch':
                        resource['children']=scratch_inventory(self.host,self.ledger,resource);self.ledger.save()
            except BaseException as error:
                if primary is None:raise
                self.ledger.failure(primary,'commission-probe');self.ledger.failure(error,'commission-capture')

    def cleanup(self,gate):
        cleanup_owned(self.host,self.ledger,gate)
        resources=self.ledger.state['observations']['commission']['resources']
        return dict(remaining=sum(self.ledger.state['entries'][r['entry']]['state']!='RELEASED' for r in resources if r['gate']==gate),
                    state_sha256=self.roots())

    def probe_filesystem(self):
        self.own('filesystem',self.helper['served_root']+'/staging/commission-filesystem','scratch',ROLES['helper'],ROLES['apache'])
        return self.child('helper','--filesystem')['data']

    def probe_ownership(self):
        root=self.helper['served_root'];uid=ROLES['helper'];gid=ROLES['apache'];canary=b'M6E public access canary\n'
        for name in ('releases/commission-permissions','releases/commission-permissions/public'):
            self.own('ownership',root+'/'+name,uid=uid,gid=gid,mode=0o750)
        self.own('ownership',root+'/releases/commission-permissions/public/index.html','file',uid,gid,0o440,canary)
        rows={}
        for role in ('worker','helper','apache'):
            # Only worker/Apache write attempts are needed. The helper's successful
            # create is separately owned too, and does not repair public permissions.
            self.own('ownership',root+'/commission-write-'+str(ROLES[role]),'access-attempt',ROLES[role])
            rows[role]=self.child(role,'--access')['data']
        w,h,a=(rows[k] for k in ('worker','helper','apache'))
        need(a['public'].get('sha256')==digest(canary),'public_read_bytes')
        return dict(worker_source_errno=w['source']['errno'],helper_source_errno=h['source']['errno'],apache_source_errno=a['source']['errno'],
            worker_served_errno=w['served']['errno'],worker_journal_errno=w['journal']['errno'],apache_public_errno=a['public']['errno'],
            apache_write_errno=a['public_write']['errno'],source_mode=w['source'].get('mode',0),
            source_parent_mode=w['source'].get('parent_mode',0),public_mode=a['public'].get('mode',0),public_sha256=a['public'].get('sha256',digest(b'')))

    def probe_apache(self):
        # These are actual bootstrap binding/TLS observations, explicitly not
        # static-serving success. A missing retained-release producer blocks gate.
        b=self.worker['bindings'][0]
        modules=self.host.run('commission-apache-modules',['/usr/sbin/apache2','-M','-f',p.CONFIG+'/apache.conf'])
        lines=[line.strip().split()[0] for line in modules.decode('ascii').splitlines() if '_module ' in line]
        need(set(lines)=={name+'_module' for name in p.MODULES}|{'core_module','so_module','http_module'},'commission_apache_module_inventory')
        a=fetch(b,'active','/');c=fetch(b,'candidate','/');denied=fetch(b,'active','/',variant='unauthorized')
        need(a['status']==c['status']==503 and denied['status'] in (0,400,401,403),'bootstrap_http_boundary')
        return dict(active_status=a['status'],candidate_status=c['status'],unauthorized_status=denied['status'],modules_sha256=digest(modules))

    def probe_assets(self):
        count=sum(len(release['assets']) for release in self.fixture['releases'])
        return dict(release_count=len(self.fixture['releases']),asset_count=count,foreign_site_id=self.fixture['foreign_asset']['site_id'],
                    served_assets=len(self.host.fs.inventory(self.helper['served_root']+'/assets')))

    def probe_runtime(self):
        value=self.child('authority','--runtime',True)['data']
        need(value['php_sha256']==self.plan['config']['tool_sha256']['php'],'runtime_php_pin')
        return value

    def probe_health(self):
        # Record the actual unavailable HTTPS endpoints. They cannot establish
        # candidate/active release health or substitute for SiteHttpHealthChecker.
        b=self.worker['bindings'][0];root=self.helper['served_root']
        return dict(active_status=fetch(b,'active','/')['status'],candidate_status=fetch(b,'candidate','/')['status'],
                    current_present=int(self.host.fs.exists(root+'/current')),candidate_present=int(self.host.fs.exists(root+'/candidate')))

    def probe_recovery(self):
        raw=self.host.fs.read(self.helper['journal_root']+'/state.json');state=decode(raw);root=self.helper['served_root']
        return dict(journal_sha256=digest(raw),current_present=int(self.host.fs.exists(root+'/current')),
                    candidate_present=int(self.host.fs.exists(root+'/candidate')),settled=int(state['settled']),fence=state['fence'])

    def probe_provisioning(self):
        # Exercise the actual restricted Provisioner at its fixed path. Invalid
        # candidate preservation is implemented; uncertain reload remains blocked.
        storage=FixedStorage(self.binding)
        row=self.ledger.generated(p.CONFIG+'/apache.pending.conf','commission-provisioning')
        resource=dict(gate='provisioning',entry=row['id'],kind='file',uid=0,children=None)
        self.ledger.state['observations']['commission']['resources'].append(resource);self.ledger.save()
        backend=self
        class CapturedStorage(FixedStorage):
            def stage(self,variant):
                primary=None
                try:super().stage(variant)
                except BaseException as error:primary=error;raise
                finally:
                    try:
                        if self.pending_identity is not None:backend.ledger.identify(row)
                    except BaseException as error:
                        if primary is None:raise
                        backend.ledger.failure(primary,'commission-provisioning');backend.ledger.failure(error,'commission-capture')
            def cleanup_pending(self):
                # Defer restoration until the collector's immutable capture.
                pass
        storage=CapturedStorage(self.binding)
        def execute(cmd):return self.host.run('commission-configtest',cmd,15,allowed=(0,1),return_result=True)
        def observe():return dict(config_sha256=storage.snapshot(),journal_sha256=digest(self.host.fs.read(self.helper['journal_root']+'/state.json')))
        baseline=execute(COMMANDS['live-validate']);need(baseline['exit']==0,'commission_config_baseline')
        tool=Provisioner(storage,execute,observe,lambda:self.host.namespace(self.plan['config']))
        value=tool.apply('invalid')
        return dict(baseline_exit=baseline['exit'],candidate_exit=value['configtest_exit'],reload_count=tool.actions.count('reload'),
                    result=value['result'],before_sha256=value['before'],after_sha256=storage.snapshot())

    def validate_format(self,candidate,now):
        payload=dict(worker=self.worker,receipt=candidate,worker_sha256=digest(self.host.fs.read(p.CONFIG+'/worker.json')),now=now)
        need(self.child('worker','--format',True,canonical(payload))=={'result':'FORMAT VALID'},'php_receipt_format')

    def acknowledge(self,raw):
        need(self.child('worker','--admit-receipt',True)=={'result':'WORKER RECEIPT ADMITTED'},'php_receipt_admission')
        need(self.child('helper','--admit-receipt')['data']=={'result':'HELPER RECEIPT ADMITTED'},'python_receipt_admission')

    def finish(self):
        self.host.owner.cleanup();cleanup_owned(self.host,self.ledger)
