"""Fixed privileged operator inside the admitted disposable VM. Never imported by production."""
import datetime
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import signal
import socket
import stat
import time

from WebsitePlatformM6EAcceptance import ROLES, OBSERVATIONS, ENDPOINTS, binding, canonical, digest, need
from WebsitePlatformM6EApache import ApacheDriver, sample
from WebsitePlatformM6EDrivers import check, APACHE
from WebsitePlatformM6EPreparation import CONTROL, CONFIG, CHECKOUT, STORAGE, protected
from WebsitePlatformM6EProcess import Owner, Failure
from WebsitePlatformM6EProvision import FixedStorage, COMMANDS

class NativeBackend(ApacheDriver):
    def __init__(self,owner,commissioning=False):
        self.owner=owner;self.commissioning=commissioning;self.owned=[];self.secondary_errors=[]
        self.plan=json.loads(protected(CONTROL/'run.json',2097152));self.identity=self.plan['identity'];binding(self.identity)
        self.binding=self.plan['worker']['bindings'][0];self.manifest=self.plan['manifest'];self.manifest_paths={e['relative_path'] for e in self.manifest['files']}
        self.site_root=Path(self.plan['helper']['bindings'][0]['served_root']);self.journal_root=Path(self.plan['helper']['bindings'][0]['journal_root'])
        self.http_names=OBSERVATIONS[APACHE];self.admit('commissioning' if commissioning else 'installed')

    def admit(self,phase):
        need(os.geteuid()==0 and phase in ('commissioning','apache','provisioning','installed'),'operator_boundary')
        need(Path('/etc/machine-id').read_text().strip()==self.identity['machine_id']
             and Path('/proc/sys/kernel/random/boot_id').read_text().strip()==self.identity['boot_id']
             and socket.gethostname()=='ubo-m6e-'+self.identity['run_id'][:16]
             and 'ubo.m6e='+self.identity['run_id'] in Path('/proc/cmdline').read_text().split()
             and sorted(p.name for p in Path('/sys/class/net').iterdir())==['lo'],'owned_vm')
        need(time.monotonic()<self.owner.deadline and time.time()<self.plan['expires_at'],'fixture_deadline')
        if phase=='installed':
            receipt=json.loads(protected(CONFIG/'host-receipt.json'))
            need(receipt['worker_config_sha256']==digest(protected(CONFIG/'worker.json')),'installed_receipt')

    def child(self,role,name,args=(),seconds=30,stdin=b''):
        allowed={'WebsitePlatformM6EHealthChild.php','WebsitePlatformM6ENativeSetup.php','WebsitePlatformM6ENativeWorker.php',
                 'WebsitePlatformM6ENativeSql.php','WebsitePlatformM6ENativePeer.py'}
        need(role in ROLES and name in allowed,'fixed_child')
        binary='/usr/bin/php' if name.endswith('.php') else '/usr/bin/python3'
        options=['-d','display_errors=0','-d','log_errors=0','-d','zend.exception_ignore_args=1','-d','memory_limit=256M'] if name.endswith('.php') else ['-I','-B']
        uid=ROLES[role]
        groups=[ROLES['apache']] if role=='helper' else []
        return self.owner.capture([binary,*options,str(CHECKOUT/'tests/support'/name),*args],seconds,stdin=stdin,identity=(uid,uid,groups))

    def command(self,cmd,seconds=15):
        return self.owner.capture(cmd,seconds,limit=65536)

    def secondary(self,code): self.secondary_errors.append(code)

    def external_snapshot(self):
        result={}
        for name in ('current','candidate'):
            path=self.site_root/name;result[name]=os.readlink(path) if path.is_symlink() else None
        result['journal_sha256']=digest((self.journal_root/'state.json').read_bytes())
        result['config_sha256']=digest((CONFIG/'apache.conf').read_bytes())
        return result

    def public_path(self,phase,path):
        need(phase in ('candidate','active') and path in self.manifest_paths,'public_path')
        alias=self.site_root/('current' if phase=='active' else 'candidate');target=os.readlink(alias)
        need(target.startswith('releases/') and target.endswith('/public') and '..' not in target,'alias_target')
        resolved=self.site_root/target/path[7:]
        need(resolved.is_relative_to(self.site_root) and not resolved.is_symlink(),'alias_containment')
        return resolved

    def http_config_fault(self,c,snippet):
        path=CONFIG/'apache.conf';old=protected(path,131072);c['config']=old
        # Fixed internally selected snippets, only inside candidate vhost; never caller text.
        text=old.decode();position=text.rfind('</VirtualHost>');need(position>=0,'candidate_vhost')
        raw=(text[:position]+snippet+text[position:]).encode()
        self.replace_owned_config(raw)
        r=self.command(['/usr/sbin/apache2','-t','-f',str(path)]);need(r['exit']==0,'fault_configtest')
        r=self.command(['/usr/bin/systemctl','reload','ubo-m6e-apache.service']);need(r['exit']==0,'fault_reload')

    def replace_owned_config(self,raw):
        path=CONFIG/'apache.conf';s=path.lstat();need(s.st_uid==0 and stat.S_ISREG(s.st_mode) and s.st_nlink==1,'config_identity')
        tmp=CONFIG/'apache.fault.pending';fd=os.open(tmp,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
        try:
            view=memoryview(raw)
            while view:
                n=os.write(fd,view);need(n>0,'config_write');view=view[n:]
            os.fsync(fd)
        finally:os.close(fd)
        os.replace(tmp,path)
        fd=os.open(CONFIG,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)

    def http_config_restore(self,c):
        # Exact owned fixture restoration, followed by real configtest/reload/HTTPS. Not a production rollback policy.
        self.replace_owned_config(c['config'])
        for cmd in (COMMANDS['live-validate'],COMMANDS['reload']):need(self.command(cmd)['exit']==0,'fixture_config_restore')
        need(self.independent_http()['status']==200,'restored_served')

    def apache_stop_barrier(self):
        r=self.command(['/usr/bin/systemctl','show','ubo-m6e-apache.service','--property=ControlGroup','--value'])
        need(r['exit']==0,'apache_cgroup');group=r['stdout'].decode().strip()
        need(group.startswith('/system.slice/ubo-m6e-apache.service'),'owned_cgroup')
        path=Path('/sys/fs/cgroup')/group.lstrip('/')/'cgroup.procs'
        pids=[int(x) for x in path.read_text().split()];need(pids,'apache_pids')
        for pid in pids:os.kill(pid,signal.SIGSTOP)
        end=time.monotonic()+2
        while True:
            states=[Path('/proc')/str(pid)/'status' for pid in pids]
            if all('\nState:\tT' in x.read_text() for x in states):return pids
            need(time.monotonic()<end,'apache_stop_barrier');time.sleep(.01)

    def apache_resume(self,pids):
        for pid in pids:
            try:os.kill(pid,signal.SIGCONT)
            except ProcessLookupError:pass

    @staticmethod
    def health_separation(first,second):
        parse=lambda value:datetime.datetime.strptime(value,'%Y-%m-%d %H:%M:%S.%f')
        return int((parse(second['checked_at'])-parse(first['checked_at'])).total_seconds()*1000)

    def foreign_asset_path(self):return '/assets/'+self.plan['foreign_asset_sha256']+'.css'

    def restore_known_good(self):
        if self.commissioning:
            raise Failure('commissioning_recovery_not_implemented')
        else:
            result=self.child('worker','WebsitePlatformM6ENativeWorker.php',['request-restore'],35)
            need(result['exit']==0,'restore_request')
            result=self.command(['/usr/bin/systemctl','start','ubo-site-worker.service'],60)
        need(result['exit']==0,'known_good_restore')

    def config_begin(self,name):
        c=dict(name=name,original=protected(CONFIG/'apache.conf',131072),snapshot=self.external_snapshot(),stopped=[],pid_backup=None)
        self.owned.append(c);return c

    def config_baseline(self,c):
        h=self.health('active');need(h['evidence']['result']=='pass','config_positive')
        return sample('worker',h['pid'],exit=0,state_sha256=digest(canonical(c['snapshot'])))

    def config_fault(self,c,name):
        if name=='failed-reload':
            path=CONTROL/'apache.pid';c['pid_backup']=path.read_bytes();path.unlink()
        if name=='unknown-reload':c['stopped']=self.apache_stop_barrier()
        return sample('operator',os.getpid(),boundary=name,count=1)

    def config_storage(self,c):return FixedStorage(self.identity)
    def config_execute(self,c):
        def execute(cmd):
            r=self.command(cmd,8 if cmd==COMMANDS['reload'] else 15)
            return r
        return execute
    def config_observer(self,c):
        def observe():
            r=self.independent_http(phase='active');h=self.health('active')
            return dict(config_id=r['headers'].get('x-m6e-config'),health=h['evidence']['result'],status=r['status'],body_sha256=r['sha256'])
        return observe
    def config_observe(self,c,name,value,actions):
        expected='REJECTED' if name=='invalid-candidate' else 'UNKNOWN' if name in ('unknown-reload','failed-reload') else 'VERIFIED'
        if name=='routine-zero-config-actions':
            outcome=sample('worker',value['pid'],config_actions=value['config_actions'])
            observed=sample('operator',os.getpid(),config_sha256=digest(protected(CONFIG/'apache.conf')))
            return outcome,observed,[check('outcome','config_actions',0),check('independent','config_sha256',digest(c['original']))]
        independent=self.external_snapshot()
        if name=='invalid-candidate':
            need(independent==c['snapshot'],'invalid_config_effects')
        out=sample('operator',os.getpid(),result=value['result'],configtest_count=actions.count('validate'),reload_count=actions.count('reload'))
        r=self.child('helper','WebsitePlatformM6ENativePeer.py',['--config-observe'],10)
        need(r['exit']==0,'independent_config');peer=json.loads(r['stdout'])
        other=sample('helper',peer['pid'],config_sha256=peer['config_sha256'],current_sha256=peer['current_sha256'])
        checks=[check('outcome','result',expected),check('independent','config_sha256',independent['config_sha256'])]
        if name=='invalid-candidate':checks.extend([check('outcome','reload_count',0),check('independent','config_sha256',digest(c['original']))])
        return out,other,checks
    def config_cleanup(self,c):
        self.apache_resume(c['stopped'])
        if c['pid_backup'] is not None:
            path=CONTROL/'apache.pid';need(not path.exists(),'pid_replaced');path.write_bytes(c['pid_backup'])
        # First reconcile actual installed/served identity. Only the isolated operator can then
        # restabilize the fixture to its exact captured baseline, and must verify that reload.
        self.config_observer(c)()
        self.replace_owned_config(c['original'])
        for cmd in (COMMANDS['live-validate'],COMMANDS['reload']):need(self.command(cmd)['exit']==0,'config_restabilize')
        need(self.independent_http(phase='active')['status']==200 and self.external_snapshot()==c['snapshot'],'restabilized')
        self.owned.remove(c);return sample('operator',os.getpid(),remaining=0,exit=0)

    def routine_publish_restore(self,c):
        before=self.config_action_counter()
        for command in ('request-publish','request-restore'):
            result=self.child('worker','WebsitePlatformM6ENativeWorker.php',[command],35);need(result['exit']==0,'routine_request')
            result=self.command(['/usr/bin/systemctl','start','ubo-site-worker.service'],60);need(result['exit']==0,'routine_worker')
        return dict(pid=result['pid'],config_actions=self.config_action_counter()-before)

    def config_action_counter(self):
        # An operator-written all-zero counter is not executed syscall evidence.
        # No native success can be emitted until an owned supervisor command audit exists.
        raise Failure('supervisor_command_audit_not_implemented')
