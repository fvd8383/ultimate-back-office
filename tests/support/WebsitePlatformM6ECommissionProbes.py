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

    @staticmethod
    def diagnostic(name,value):
        # Only known scalar measurements cross the diagnostic boundary. In
        # particular, never stringify unexpected objects, names or private text.
        if type(value) is int and -(2**63)<=value<2**63:return value
        if type(value) is str:
            if name.endswith('sha256') and len(value)==64 and re.fullmatch('[a-f0-9]{64}',value):return value
            if name=='result' and value in ('REJECTED','UNKNOWN','VERIFIED','FAILED','PASS'):return value
            if name=='role' and value in ROLES:return value
        kind={int:'integer',bool:'boolean',float:'number',str:'string',bytes:'bytes',
              dict:'object',list:'array',tuple:'array',type(None):'null'}.get(type(value),'unsupported')
        return dict(invalid=kind)

    def remember(self,value):
        if getattr(self,'gate',None) is None:return
        if type(value) is not dict:
            self.partial['data_invalid']=self.diagnostic('',value);return
        fields=evidence.FIELDS[self.gate]
        for name in fields:
            if name in value:self.partial['data'][name]=self.diagnostic(name,value[name])
        extra=len(value)-sum(name in value for name in fields)
        if extra:self.partial['unexpected_fields']=min(extra,256)

    def remember_participants(self):
        if getattr(self,'gate',None) is None:return
        self.partial['participants']=[{name:self.diagnostic(name,row[name])
            for name in ('role','uid','pid','exit') if name in row}
            if type(row) is dict else dict(invalid='participant') for row in self.participants[:16]]

    def step(self,name,action):
        # Names come only from the finite call sites below, never command/output
        # strings. This is bounded progress, not an alternate evidence schema.
        active=getattr(self,'gate',None) is not None
        if active:
            need(len(self.partial['progress'])<64 and re.fullmatch('[a-z-]{1,64}',name),'commission_progress_bound')
            self.partial['stage']=name;self.partial['progress'][name]='ATTEMPTED'
        try:value=action()
        except BaseException:
            if active:self.partial['progress'][name]='FAILED'
            raise
        if active:self.partial['progress'][name]='COMPLETED'
        return value

    def command(self,name,role,action):
        start=len(self.ledger.state['commands']);result=None
        try:
            result=self.step(name,action);return result
        finally:
            # NativeHost retains child context even if execution/capture throws.
            # Only a new command from this call, or its actual returned result,
            # may supply a PID/exit; never synthesize them from planned argv.
            commands=self.ledger.state['commands']
            row=commands[-1] if len(commands)==start+1 else result
            if type(row) is dict and row.get('pid') is not None:
                self.participants.append(dict(role=role,uid=ROLES[role],
                    **{key:row[key] for key in ('pid','exit') if key in row}))
                self.remember_participants()

    def access_data(self,role,value):
        mapping={
            'worker':{'worker_source_errno':('source','errno'),'worker_served_errno':('served','errno'),
                      'worker_journal_errno':('journal','errno'),'source_mode':('source','mode'),'source_parent_mode':('source','parent_mode')},
            'helper':{'helper_source_errno':('source','errno')},
            'apache':{'apache_source_errno':('source','errno'),'apache_public_errno':('public','errno'),
                      'apache_write_errno':('public_write','errno'),'public_mode':('public','mode'),'public_sha256':('public','sha256')},
        }
        found={}
        if type(value) is dict:
            for field,(section,key) in mapping[role].items():
                if section in value:
                    part=value[section]
                    if type(part) is dict:
                        if key in part:found[field]=part[key]
                    elif getattr(self,'gate',None) is not None:
                        # A scalar section cannot become a valid errno merely
                        # because its numeric value matches the predicate.
                        self.partial['data'][field]=dict(invalid='section')
        else:self.remember(value)
        self.remember(found);return found

    def child(self,role,mode,php=False,stdin=b''):
        name='WebsitePlatformM6ECommissionChild.'+('php' if php else 'py')
        args=(['/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','zend.exception_ignore_args=1','-d','memory_limit=256M']
              if php else ['/usr/bin/python3','-I','-B'])+[p.CHECKOUT+'/tests/support/'+name,mode]
        r=self.command('child-'+role,role,lambda:self.host.run('commission-'+mode[2:],args,30,role,stdin=stdin,return_result=True))
        def parse():
            need(not r['stderr'],'commission_child_diagnostic')
            value=decode(r['stdout']);need(type(value) is dict,'commission_child_json');return value
        value=self.step('child-'+role+'-parse',parse)
        if getattr(self,'gate',None) is not None and 'data' in value:
            if mode=='--access':self.access_data(role,value['data'])
            elif mode in ('--filesystem','--runtime'):self.remember(value['data'])
        if 'pid' in value:
            self.step('child-'+role+'-identity',lambda:need(value['pid']==r['pid'] and value['uid']==ROLES[role],'commission_child_process'))
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
        need(gate in evidence.PRODUCERS,'commission_gate');self.participants=[];self.gate=gate
        self.partial=dict(data={},participants=[],progress={});primary=None
        try:
            data=self.step('producer',lambda:getattr(self,'probe_'+gate)())
            self.remember(data);self.remember_participants()
            self.step('predicate',lambda:evidence.measurements(gate,data))
            # Independent worker inspection begins only after the strict predicate.
            actual=self.command('independent','worker',lambda:self.host.setup('inspect','worker'))
            self.step('independent-binding',lambda:need(actual==dict(fixture=self.fixture,
                database=self.ledger.state['observations']['database']),'commission_independent_fixture'))
            command=self.ledger.state['commands'][-1]
            roots=self.step('independent-roots',self.roots)
            return dict(pid=os.getpid(),uid=os.geteuid(),data=data,participants=copy.deepcopy(self.participants),
                independent=dict(pid=command['pid'],state_sha256=roots,roles_sha256=digest(canonical(ROLES))))
        except BaseException as error:primary=error;raise
        finally:
            self.remember_participants()
            try:
                for resource in self.ledger.state['observations']['commission']['resources']:
                    if resource['gate']==gate and resource['kind']=='scratch':
                        resource['children']=scratch_inventory(self.host,self.ledger,resource);self.ledger.save()
            except BaseException as error:
                if primary is None:raise
                self.ledger.failure(primary,'commission-probe');self.ledger.failure(error,'commission-capture')
            finally:self.gate=None

    def cleanup(self,gate):
        cleanup_owned(self.host,self.ledger,gate)
        resources=self.ledger.state['observations']['commission']['resources']
        return dict(remaining=sum(self.ledger.state['entries'][r['entry']]['state']!='RELEASED' for r in resources if r['gate']==gate),
                    state_sha256=self.roots())

    def probe_filesystem(self):
        self.step('filesystem-scratch',lambda:self.own('filesystem',self.helper['served_root']+'/staging/commission-filesystem',
            'scratch',ROLES['helper'],ROLES['apache']))
        return self.child('helper','--filesystem')['data']

    def probe_ownership(self):
        root=self.helper['served_root'];uid=ROLES['helper'];gid=ROLES['apache'];canary=b'M6E public access canary\n'
        for name in ('releases/commission-permissions','releases/commission-permissions/public'):
            self.step('ownership-directory',lambda:self.own('ownership',root+'/'+name,uid=uid,gid=gid,mode=0o750))
        self.step('ownership-canary',lambda:self.own('ownership',root+'/releases/commission-permissions/public/index.html',
            'file',uid,gid,0o440,canary))
        measured={}
        for role in ('worker','helper','apache'):
            self.step('ownership-intent',lambda:self.own('ownership',root+'/commission-write-'+str(ROLES[role]),'access-attempt',ROLES[role]))
            # Each role's obtained access fields survive a later role's failure.
            measured.update(self.access_data(role,self.child(role,'--access')['data']))
        self.step('ownership-public',lambda:need(measured.get('public_sha256')==digest(canary),'public_read_bytes'))
        return measured

    def probe_apache(self):
        # Unavailable responses remain prerequisites, never static-serving PASS.
        b=self.worker['bindings'][0]
        result=self.command('apache-modules','operator',lambda:self.host.run('commission-apache-modules',
            ['/usr/sbin/apache2','-M','-f',p.CONFIG+'/apache.conf'],return_result=True))
        modules=result['stdout'];measured=dict(modules_sha256=digest(modules));self.remember(measured)
        def check_modules():
            lines=[line.strip().split()[0] for line in modules.decode('ascii').splitlines() if '_module ' in line]
            need(set(lines)=={name+'_module' for name in p.MODULES}|{'core_module','so_module','http_module'},'commission_apache_module_inventory')
        self.step('apache-module-check',check_modules)
        for field,mode,variant in (('active_status','active','valid'),('candidate_status','candidate','valid'),
                                   ('unauthorized_status','active','unauthorized')):
            request=lambda:fetch(b,mode,'/') if variant=='valid' else fetch(b,mode,'/',variant=variant)
            measured[field]=self.step('apache-'+mode+'-'+variant,request)['status'];self.remember({field:measured[field]})
        self.step('apache-boundary',lambda:need(measured['active_status']==measured['candidate_status']==503
            and measured['unauthorized_status'] in (0,400,401,403),'bootstrap_http_boundary'))
        return measured

    def probe_assets(self):
        measured={}
        for field,read in (('release_count',lambda:len(self.fixture['releases'])),
                           ('asset_count',lambda:sum(len(r['assets']) for r in self.fixture['releases'])),
                           ('foreign_site_id',lambda:self.fixture['foreign_asset']['site_id'])):
            measured[field]=read();self.remember({field:measured[field]})
        measured['served_assets']=len(self.step('assets-inventory',lambda:self.host.fs.inventory(self.helper['served_root']+'/assets')))
        self.remember({'served_assets':measured['served_assets']});return measured

    def probe_runtime(self):
        value=self.child('authority','--runtime',True)['data']
        self.step('runtime-php-pin',lambda:need(value['php_sha256']==self.plan['config']['tool_sha256']['php'],'runtime_php_pin'))
        return value

    def probe_health(self):
        # Still no release-health success or extra retry budget.
        b=self.worker['bindings'][0];root=self.helper['served_root'];measured={}
        for mode in ('active','candidate'):
            measured[mode+'_status']=self.step('health-'+mode,lambda:fetch(b,mode,'/'))['status']
            self.remember({mode+'_status':measured[mode+'_status']})
        for name in ('current','candidate'):
            measured[name+'_present']=int(self.step('health-'+name+'-pointer',lambda:self.host.fs.exists(root+'/'+name)))
            self.remember({name+'_present':measured[name+'_present']})
        return measured

    def probe_recovery(self):
        raw=self.step('recovery-journal',lambda:self.host.fs.read(self.helper['journal_root']+'/state.json'))
        measured=dict(journal_sha256=digest(raw));self.remember(measured)
        state=self.step('recovery-decode',lambda:decode(raw));root=self.helper['served_root']
        for name in ('settled','fence'):
            measured[name]=state[name];self.remember({name:measured[name]})
        measured['settled']=int(measured['settled'])
        for name in ('current','candidate'):
            measured[name+'_present']=int(self.step('recovery-'+name,lambda:self.host.fs.exists(root+'/'+name)))
            self.remember({name+'_present':measured[name+'_present']})
        return measured

    def probe_provisioning(self):
        # Exercise the actual restricted Provisioner at its fixed path. Invalid
        # candidate preservation is implemented; uncertain reload remains blocked.
        row=self.ledger.generated(p.CONFIG+'/apache.pending.conf','commission-provisioning')
        resource=dict(gate='provisioning',entry=row['id'],kind='file',uid=0,children=None)
        self.ledger.state['observations']['commission']['resources'].append(resource);self.ledger.save()
        backend=self
        class CapturedStorage(FixedStorage):
            def snapshot(self):
                raw=backend.step('provision-snapshot',super().snapshot)
                field='after_sha256' if 'before_sha256' in backend.partial.get('data',{}) else 'before_sha256'
                backend.remember({field:raw});return raw
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
        def execute(cmd):
            result=self.command('provision-command','operator',lambda:self.host.run('commission-configtest',cmd,15,allowed=(0,1),return_result=True))
            field='baseline_exit' if cmd==COMMANDS['live-validate'] else 'candidate_exit'
            self.remember({field:result['exit']});return result
        def observe():return dict(config_sha256=storage.snapshot(),journal_sha256=digest(self.host.fs.read(self.helper['journal_root']+'/state.json')))
        baseline=execute(COMMANDS['live-validate'])
        self.step('provision-baseline',lambda:need(baseline['exit']==0,'commission_config_baseline'))
        tool=Provisioner(storage,execute,observe,lambda:self.host.namespace(self.plan['config']))
        value=self.step('provision-apply',lambda:tool.apply('invalid'))
        measured=dict(baseline_exit=baseline['exit'],candidate_exit=value['configtest_exit'],reload_count=tool.actions.count('reload'),
                      result=value['result'],before_sha256=value['before'])
        self.remember(measured);measured['after_sha256']=storage.snapshot();return measured

    def validate_format(self,candidate,now):
        payload=dict(worker=self.worker,receipt=candidate,worker_sha256=digest(self.host.fs.read(p.CONFIG+'/worker.json')),now=now)
        need(self.child('worker','--format',True,canonical(payload))=={'result':'FORMAT VALID'},'php_receipt_format')

    def acknowledge(self,raw):
        need(self.child('worker','--admit-receipt',True)=={'result':'WORKER RECEIPT ADMITTED'},'php_receipt_admission')
        need(self.child('helper','--admit-receipt')['data']=={'result':'HELPER RECEIPT ADMITTED'},'python_receipt_admission')

    def finish(self):
        self.host.owner.cleanup();cleanup_owned(self.host,self.ledger)
