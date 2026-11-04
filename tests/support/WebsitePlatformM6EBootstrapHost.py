"""Linux boundaries for the fixed M6E fixture. No effects at import; no generic installer."""
import hashlib
import json
import os
from pathlib import Path
import re
import socket
import stat
import time

from WebsitePlatformM6EAcceptance import ROLES, canonical, digest, need, decode
from WebsitePlatformM6EPreparation import BINARIES, BOOTSTRAP, protected, read_preflight
from WebsitePlatformM6EProcess import Owner, Failure
from WebsitePlatformM6EBootstrapLedger import Files
import WebsitePlatformM6EBootstrapPlan as p


class NativeHost:
    def __init__(self,files=None,execute=None):
        self.fs=files or Files();self.owner=Owner(time.monotonic()+p.PREPARE_SECONDS+60)
        self.execute=execute or self.owner.capture;self.ledger=None;self.pre_commands=[]

    def run(self,label,args,seconds=30,role='operator',allowed=(0,),stdin=b'',return_result=False):
        need(re.fullmatch('[a-z][a-z0-9-]{0,63}',label) and role in ROLES,'base_command_id')
        need(args[0] in [*BINARIES.values(),*p.AUXILIARIES.values()],'base_executable')
        entry=dict(id=label,caller=role,pid=None,exit=None,started_ns=time.monotonic_ns(),finished_ns=None)
        writing=self.ledger is not None and not getattr(self,'readonly',False)
        journal=self.ledger.state['commands'] if writing else self.pre_commands
        need(len(journal)<2048,'command_journal_bound');journal.append(entry)
        if writing:self.ledger.save()
        primary=None;context_start=len(self.owner.context)
        try:
            uid=ROLES[role];identity=None if role=='operator' else (uid,uid,[ROLES['apache']] if role=='helper' else [])
            result=self.execute(args,seconds,stdin=stdin,identity=identity,limit=2097152,error_limit=16384)
            entry.update(pid=result['pid'],exit=result['exit'])
            need(result['exit'] in allowed,'base_command_failed')
            return result if return_result else result['stdout']
        except BaseException as error:primary=error;raise
        finally:
            # Owner retains the real final child context even when capture throws.
            if len(self.owner.context)==context_start+1:
                child=self.owner.context[-1];entry.update(pid=child['pid'],exit=child['exit'])
            entry['finished_ns']=time.monotonic_ns()
            if writing:
                try:self.ledger.save()
                except BaseException as capture:
                    if primary is None:raise
                    self.ledger.failure(primary,'command');self.ledger.failure(capture,'capture')

    def systemctl(self,verb,unit=None,seconds=30):
        need(verb in ('start','stop','show','daemon-reload'),'base_service_verb')
        need((verb=='daemon-reload' and unit is None) or unit in p.UNITS,'base_service_name')
        args=['/usr/bin/systemctl',verb]
        if unit:args.append(unit)
        if verb=='show':args+=['--no-pager','--property=Id,LoadState,ActiveState,SubState,MainPID,InvocationID,ControlGroup,FragmentPath,ExecMainStatus,Listen,Triggers,Accept,NConnections,NAccepted,NRefused,User,Group,SupplementaryGroups,DropInPaths,NextElapseUSecMonotonic']
        return self.run('unit-'+verb+('-'+unit.split('.')[0] if unit else ''),args,seconds)

    @staticmethod
    def parse_unit(raw):
        value={}
        for line in raw.decode('utf-8').splitlines():
            need('=' in line,'unit_parse');key,text=line.split('=',1);need(key not in value and len(text)<=2048,'unit_parse')
            value[key]=text
        for key in ('Id','LoadState','ActiveState','SubState','FragmentPath','DropInPaths'):need(key in value,'unit_fields')
        need(value['LoadState']=='loaded' and value['DropInPaths']=='','unit_loaded')
        return value

    def unit(self,name):return self.parse_unit(self.systemctl('show',name))

    def component_context(self):
        return dict(pid=os.getpid(),cgroup=Path('/proc/self/cgroup').read_text().splitlines(),
                    invocation=os.environ.get('INVOCATION_ID',''))

    def config(self):return p.admission(decode(protected(BOOTSTRAP)))

    def namespace(self,config):
        need(os.name=='posix' and hasattr(os,'geteuid') and os.geteuid()==0,'base_linux_root')
        need(Path('/etc/machine-id').read_text().strip()==config['machine_id']
             and Path('/proc/sys/kernel/random/boot_id').read_text().strip()==config['boot_id']
             and socket.gethostname()=='ubo-m6e-'+config['run_id'][:16]
             and 'ubo.m6e='+config['run_id'] in Path('/proc/cmdline').read_text().split()
             and sorted(x.name for x in Path('/sys/class/net').iterdir())==['lo'],'base_namespace')

    def preparer_context(self):
        need('0::/system.slice/ubo-m6e-bootstrap.service' in Path('/proc/self/cgroup').read_text().splitlines(),'fixed_bootstrap_supervisor')

    def source(self,root,sha):
        args=['/usr/bin/git','--no-optional-locks','-c','core.fsmonitor=false','-c','safe.directory='+root,'-C',root]
        need(self.run('source-head',args+['rev-parse','HEAD']).decode().strip()==sha,'base_source_head')
        need(self.run('source-status',args+['status','--porcelain','--untracked-files=all'])==b'','base_source_dirty')
        raw=self.run('source-index',args+['ls-files','--stage','-z']);items={}
        for line in raw.rstrip(b'\0').split(b'\0'):
            prefix,path=line.decode().split('\t',1);mode,object_id,stage=prefix.split()
            need(mode in ('100644','100755') and stage=='0' and not path.startswith('/') and '..' not in path.split('/') and '\\' not in path,'base_source_mode')
            data=protected(Path(root)/path,32*1024*1024)
            need(hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()==object_id,'base_source_bytes')
            actual=self.fs.identity(root+'/'+path)
            need(actual['mode']==(0o755 if mode=='100755' else 0o644),'base_source_exec_mode')
            items[path]=dict(mode=mode,sha256=digest(data));need(len(items)<=12000,'source_inventory_bound')
        need(items,'base_empty_source');return items

    def preflight(self):
        config=self.config();self.namespace(config)
        legacy={k:v for k,v in config.items() if k not in ('auxiliary_sha256','apache_modules')};legacy['version']=1
        host=self
        class PreflightCommands:
            def capture(self,args,seconds,**kwargs):
                return host.run('preflight-'+Path(args[0]).name,args,seconds,allowed=(0,1,2),return_result=True)
        _,versions=read_preflight(PreflightCommands(),legacy,inventory_roots=(*p.ROOTS,p.LAUNCHER),inventory_units=p.UNITS)
        need(config['expires_at']>=int(time.time())+p.LIFETIME_SECONDS+p.CLEANUP_SECONDS,'base_lifetime')
        for key,path in p.AUXILIARIES.items():need(digest(protected(Path(path).resolve(strict=True),128*1024*1024))==config['auxiliary_sha256'][key],'auxiliary_pin')
        shell=Path('/usr/sbin/nologin').lstat();need(stat.S_ISREG(shell.st_mode) and shell.st_uid==0 and shell.st_mode&0o111 and not shell.st_mode&0o022,'nonlogin_shell')
        compiled=self.run('apache-builtins',['/usr/sbin/apache2','-l']).decode()
        for name,module in config['apache_modules'].items():
            if module['path']=='builtin':
                names=('mod_'+name+'.c',name+'.c','event.c' if name=='mpm_event' else name+'.c')
                need(any(x in compiled.split() for x in names),'apache_builtin')
            else:need(digest(protected(module['path'],16777216))==module['sha256'],'apache_module_pin')
        php=set(self.run('php-modules',['/usr/bin/php','-m']).decode().lower().split())
        need({'pdo_mysql','posix','curl','sockets','dom','fileinfo','mbstring','openssl'}<=php,'php_modules')
        import pwd,grp
        for role,uid in ROLES.items():
            if role=='operator':continue
            for lookup,key in ((pwd.getpwnam,'m6e-'+role),(pwd.getpwuid,uid),(grp.getgrnam,'m6e-'+role),(grp.getgrgid,uid)):
                try:lookup(key)
                except KeyError:continue
                raise Failure('existing_fixture_account')
        for path in (p.SOURCE+'/private/config/env.php','/etc/ubo-site-build-worker.json',p.CONFIG+'/host-receipt.json'):
            need(not self.fs.exists(path),'operational_configuration')
        need(not self.listeners(),'preexisting_listeners')
        loaded=self.run('unit-collisions',['/usr/bin/systemctl','list-units','--all','--plain','--no-legend','--no-pager']).decode()
        need(not any(line.split() and line.split()[0].startswith(('ubo-m6e-','ubo-site-')) for line in loaded.splitlines()),'loaded_fixture_units')
        mounts=Path('/proc/self/mountinfo').read_text().splitlines()
        for line in mounts:
            fields=line.split();kind=fields[fields.index('-')+1];mount=fields[4]
            need(kind not in ('nfs','nfs4','cifs','9p','vboxsf','fuse.sshfs') and not mount.startswith(('/srv/','/var/www/')),'operational_mount')
        for directory in ('/var/lib','/var/www','/run','/etc','/opt','/usr/local/lib','/usr/local/sbin','/run/systemd/system'):
            s=Path(directory).lstat();need(stat.S_ISDIR(s.st_mode) and s.st_uid==0 and not s.st_mode&0o022,'base_parent')
        for directory in ('/var/lib','/var/www'):
            capacity=os.statvfs(directory);need(capacity.f_bavail*capacity.f_frsize>=2*1024**3 and capacity.f_favail>=20000,'base_capacity')
        memory=int(next(x.split()[1] for x in Path('/proc/meminfo').read_text().splitlines() if x.startswith('MemAvailable:')))
        need(memory>=1024*1024 and Path('/sys/fs/cgroup/cgroup.controllers').exists(),'base_resource_policy')
        source=self.source(p.SOURCE,config['source_sha'])
        required=['tests/support/WebsitePlatformM6E'+name for name in ('Bootstrap.py','BootstrapHost.py','BootstrapLedger.py','BootstrapPlan.py',
                    'Preparation.py','NativeSetup.php','Process.py','Acceptance.py','Checkpoint.php','Checkpoint.py',
                    'Snapshot.php','Snapshot.py','SnapshotProfile.json','Restoration.php','Restoration.py')]
        need(all(name in source for name in required),'bootstrap_source_complete')
        self.run('setup-syntax',['/usr/bin/php','-l',p.SOURCE+'/tests/support/WebsitePlatformM6ENativeSetup.php'])
        self.run('restore-syntax',['/usr/bin/php','-l',p.SOURCE+'/tests/support/WebsitePlatformM6ERestoration.php'])
        self.run('bootstrap-syntax',['/usr/bin/python3','-I','-B','-c',
            'import ast,pathlib; [ast.parse(pathlib.Path(p).read_bytes()) for p in '+repr([p.SOURCE+'/'+name for name in required if name.endswith('.py')])+']'])
        templates=p.templates(lambda name:protected(p.SOURCE+'/'+name,262144))
        # Parse/render every fixed unit and complete Apache configuration before any account/service mutation.
        dummy='00000000-0000-4000-8000-000000000000'
        units=p.control_units()|p.fixture_units(templates,config,dummy)
        need(set(units)==set(p.UNITS),'base_unit_plan')
        p.apache_main(config,templates['apache'],dummy)
        return dict(config=config,versions=versions,source=source,templates=templates,
                    plan_sha256=digest(canonical(dict(units={k:digest(v) for k,v in units.items()},source=source,config=config))))

    def account(self,role):
        import pwd,grp
        name='m6e-'+role;user=group=None
        try:
            u=pwd.getpwnam(name);user=dict(name=u.pw_name,uid=u.pw_uid,gid=u.pw_gid,home=u.pw_dir,shell=u.pw_shell,comment=u.pw_gecos)
        except KeyError:pass
        try:
            g=grp.getgrnam(name);group=dict(name=g.gr_name,gid=g.gr_gid)
        except KeyError:pass
        return dict(user=user,group=group)

    def create_account(self,role,run_id):
        uid=ROLES[role];need(role!='operator' and self.account(role)==dict(user=None,group=None),'base_account_collision')
        row=dict(role=role,state='INTENT',identity=None);self.ledger.state['accounts'].append(row);self.ledger.save()
        primary=None
        try:
            self.run('group-create',['/usr/sbin/groupadd','--gid',str(uid),'m6e-'+role])
            row['identity']=self.account(role);self.ledger.save()
            args=['/usr/sbin/useradd','--uid',str(uid),'--gid',str(uid),'--no-create-home','--home-dir','/nonexistent',
                  '--shell','/usr/sbin/nologin','--comment','M6E:'+run_id]
            if role=='helper':args+=['--groups','m6e-apache']
            self.run('user-create',args+['m6e-'+role]);row['identity']=self.account(role);row['state']='CREATED';self.ledger.save()
        except BaseException as error:primary=error;raise
        finally:
            try:
                actual=self.account(role)
                # Only this run's comment and exact fixed group may identify partial creation.
                if actual['user'] is not None:need(actual['user']['comment']=='M6E:'+run_id and actual['user']['uid']==uid,'partial_user_identity')
                if actual['group'] is not None:need(actual['group']['gid']==uid,'partial_group_identity')
                row['identity']=actual;self.ledger.save()
            except BaseException as capture:
                if primary is None:raise
                self.ledger.failure(primary,'prepare');self.ledger.failure(capture,'capture')

    def delete_account(self,row):
        actual=self.account(row['role']);expected=row['identity']
        if row['state']=='RELEASED':need(actual==dict(user=None,group=None),'replacement_account');return
        need(expected is not None and actual==expected,'owned_account_replaced')
        if actual['user'] is not None:
            try:self.run('user-delete',['/usr/sbin/userdel','m6e-'+row['role']])
            finally:
                current=self.account(row['role'])
                if current['user'] is None and current['group'] in (None,expected['group']):
                    row['identity']=current;self.ledger.save()
        # userdel can remove the matching private group; observe, never assume.
        current=self.account(row['role'])
        if current['group'] is not None:
            need(current['group']==expected['group'],'owned_group_replaced')
            try:self.run('group-delete',['/usr/sbin/groupdel','m6e-'+row['role']])
            finally:
                if self.account(row['role'])==dict(user=None,group=None):row['identity']=dict(user=None,group=None);row['state']='RELEASED';self.ledger.save()
        need(self.account(row['role'])==dict(user=None,group=None),'account_still_present');row['state']='RELEASED';self.ledger.save()

    def no_role_processes(self,exclude=()):
        forbidden=set(ROLES.values())-{0}
        for entry in Path('/proc').iterdir():
            if not entry.name.isdecimal() or int(entry.name) in exclude:continue
            try:uid=entry.stat().st_uid
            except FileNotFoundError:continue
            need(uid not in forbidden,'owned_role_process_remains')

    def listeners(self):
        result=[]
        for name in ('tcp','tcp6','udp','udp6'):
            for line in Path('/proc/net/'+name).read_text().splitlines()[1:]:
                row=line.split()
                if name.startswith('tcp') and row[3]!='0A':continue
                address,port=row[1].split(':');port=int(port,16)
                if port==0:continue
                ip=socket.inet_ntoa(bytes.fromhex(address)[::-1]) if name in ('tcp','udp') else socket.inet_ntop(socket.AF_INET6,bytes.fromhex(address))
                result.append(dict(protocol=name,ip=ip,port=port,uid=int(row[7]),inode=int(row[9])))
        return result

    def service_observation(self,name,active=True):
        unit=self.unit(name)
        need(unit['Id']==name and unit['FragmentPath']=='/run/systemd/system/'+name,'unit_fragment')
        row=next(x for x in self.ledger.state['entries'] if x['path']==unit['FragmentPath']);self.ledger.verify(row)
        need(unit['ActiveState']==('active' if active else 'inactive'),'unit_activity')
        if not active:
            need(unit.get('MainPID','0')=='0','inactive_process');return dict(unit=name,state='INACTIVE',pid=0,invocation=None,cgroup=None)
        invocation=unit.get('InvocationID','');need(re.fullmatch('[a-f0-9]{32}',invocation),'unit_invocation')
        pid=int(unit.get('MainPID','0'));group=unit.get('ControlGroup','')
        if name.endswith('.service'):
            need(pid>0 and group=='/system.slice/'+name,'service_process')
            self.check_process(pid,group,ROLES['mysql'] if name=='ubo-m6e-mysql.service' else 0)
        if name in p.SOCKETS:
            path,uid,gid,mode=p.SOCKETS[name];info=self.fs.identity(path)
            need(info['kind']==stat.S_IFSOCK and (info['uid'],info['gid'],info['mode'])==(uid,gid,mode),'socket_access')
            # Accept=yes links concrete instances only after a connection; this
            # bootstrap must not exercise the uncommissioned publisher/authority.
            need(unit.get('Listen')==path+' (Stream)' and unit.get('Accept')=='yes'
                 and unit.get('Triggers')=='' and all(unit.get(k)=='0' for k in ('NConnections','NAccepted','NRefused')),'socket_unit')
            template=next(x for x in self.ledger.state['entries'] if x['path']=='/run/systemd/system/'+name.replace('.socket','@.service'))
            self.ledger.verify(template)
        if name=='ubo-m6e-watchdog.timer':
            need(unit.get('NextElapseUSecMonotonic','') not in ('','0','infinity'),'watchdog_not_armed')
        return dict(unit=name,state='ACTIVE',pid=pid,invocation=invocation,cgroup=group or None)

    def start(self,name):
        need(name in (*p.STARTED,'ubo-m6e-watchdog.timer','ubo-m6e-bootstrap.service'),'base_start_unit')
        row=dict(unit=name,state='STARTING',pid=None,invocation=None,cgroup=None)
        self.ledger.state['services'].append(row);self.ledger.save()
        self.systemctl('start',name,p.PREPARE_SECONDS+10 if name=='ubo-m6e-bootstrap.service' else 30)
        if name!='ubo-m6e-bootstrap.service':row.update(self.service_observation(name));self.ledger.save()

    def stop(self,row):
        if row['state']=='RELEASED':return
        name=row['unit']
        entry=next(x for x in self.ledger.state['entries'] if x['path']=='/run/systemd/system/'+name)
        if entry['state']=='RELEASED':
            need(not self.fs.exists(entry['path']),'removed_unit_replaced')
            raw=self.systemctl('show',name);values=dict(line.split('=',1) for line in raw.decode().splitlines())
            need(values.get('ActiveState') in ('inactive','failed') and values.get('MainPID','0')=='0'
                 and not values.get('DropInPaths') and values.get('FragmentPath','') in ('',entry['path']),'self_exit_pending')
            row['state']='RELEASED';self.ledger.save();return
        unit=self.unit(name);self.ledger.verify(entry)
        need(unit['FragmentPath']==entry['path'],'stop_fragment')
        if row.get('invocation') and unit['ActiveState']=='active':need(unit.get('InvocationID')==row['invocation'],'service_replaced')
        self.systemctl('stop',name,20);after=self.unit(name)
        need(after['ActiveState'] in ('inactive','failed') and after.get('MainPID','0')=='0','service_not_stopped')
        group=row.get('cgroup')
        if group:
            procs=Path('/sys/fs/cgroup'+group+'/cgroup.procs')
            need(not procs.exists() or not procs.read_text().strip(),'service_children_remain')
        row['state']='RELEASED';self.ledger.save()

    def setup(self,mode,role='operator'):
        need(mode in ('schema','build-two','inspect'),'setup_mode')
        result=self.run('fixture-'+mode,['/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','zend.exception_ignore_args=1',
            '-d','memory_limit=256M',p.CHECKOUT+'/tests/support/WebsitePlatformM6ENativeSetup.php','--'+mode],180,role,allowed=(0,1),return_result=True)
        if result['exit']!=0:
            primary=Failure('base_command_failed');prefix=b'm6e_snapshot_outcome:'
            if mode=='build-two' and result['stderr'].startswith(prefix) and self.ledger is not None:
                try:
                    from WebsitePlatformM6ESnapshot import outcome_value
                    self.ledger.state['observations']['pre_b_database_outcome']=outcome_value(result['stderr'][len(prefix):])
                    self.ledger.save()
                except BaseException as error:
                    self.ledger.failure(primary,'command');self.ledger.failure(error,'snapshot-diagnostic')
            raise primary
        value=decode(result['stdout']);need(type(value) is dict,'setup_result');return value

    def protect_checkout(self,source):
        root=self.fs.path(p.CHECKOUT)
        for directory,dirs,files in os.walk(root,followlinks=False):
            relative=Path(directory).relative_to(root).as_posix()
            logical=p.CHECKOUT+('' if relative=='.' else '/'+relative)
            self.fs.metadata(logical,0,0,0o755)
            for name in files:
                path=Path(directory)/name;rel=path.relative_to(root).as_posix();s=path.lstat()
                need(stat.S_ISREG(s.st_mode) and s.st_nlink==1,'checkout_object')
                mode=(0o755 if source[rel]['mode']=='100755' else 0o644) if rel in source else (0o755 if s.st_mode&0o111 else 0o644)
                self.fs.metadata(p.CHECKOUT+'/'+rel,0,0,mode)

    def wait_mysql(self):
        deadline=time.monotonic()+20
        while time.monotonic()<deadline:
            path=p.STORAGE+'/mysql-run/mysql.sock'
            if self.fs.exists(path):
                info=self.fs.identity(path)
                need(info['kind']==stat.S_IFSOCK and info['uid']==ROLES['mysql'],'mysql_socket')
                return
            time.sleep(.05)
        raise Failure('mysql_readiness_timeout')

    def check_pins(self,config):
        for names,binaries in ((config['tool_sha256'],BINARIES),(config['auxiliary_sha256'],p.AUXILIARIES)):
            for key,value in names.items():need(digest(protected(Path(binaries[key]).resolve(strict=True),128*1024*1024))==value,'inspection_tool_pin')
        for item in config['apache_modules'].values():
            if item['path']!='builtin':need(digest(protected(item['path'],16777216))==item['sha256'],'inspection_module_pin')

    def check_access(self,ledger):
        for root in ('/run/systemd/system','/etc/systemd/system','/usr/lib/systemd/system','/lib/systemd/system'):
            for name in p.UNITS:
                need(not self.fs.exists(root+'/'+name+'.d'),'unexpected_unit_dropin')
                if root!='/run/systemd/system':need(not self.fs.exists(root+'/'+name),'unexpected_unit_override')
        for role,uid in ROLES.items():
            if role=='operator':continue
            actual=self.account(role);expected=next(x for x in ledger.state['accounts'] if x['role']==role)['identity']
            need(actual==expected and actual['user']['uid']==uid and actual['user']['gid']==uid
                 and actual['user']['home']=='/nonexistent' and actual['user']['shell']=='/usr/sbin/nologin'
                 and actual['user']['comment']=='M6E:'+ledger.config['run_id'],'inspection_account')
            groups=self.groups(role)
            need(groups==({uid,ROLES['apache']} if role=='helper' else {uid}),'inspection_groups')
        for name in ('work','sealed','uploads'):
            path=p.STORAGE+'/'+name;root=self.fs.identity(path)
            need((root['uid'],root['gid'],root['mode'])==(ROLES['worker'],ROLES['worker'],0o700),'private_artifact_root')
            for child in self.fs.inventory(path).values():
                need(child['uid']==ROLES['worker'] and child['gid']==ROLES['worker'] and
                     child['mode']==(0o700 if child['kind']==stat.S_IFDIR else 0o600),'private_artifact_object')
        expected={p.CONFIG+'/authority-credentials.json':(ROLES['authority'],0o640),p.CONTROL+'/writer.json':(ROLES['writer'],0o640),
          p.CONTROL+'/snapshot-reader.json':(ROLES['worker'],0o640),p.CONTROL+'/snapshot-schema.json':(ROLES['worker'],0o640),
          p.CONTROL+'/admin.json':(0,0o600),p.CONFIG+'/application.php':(ROLES['worker'],0o640),
          p.CHECKOUT+'/private/config/env.php':(ROLES['worker'],0o640),p.CONFIG+'/tls/ca.key':(0,0o600),
          p.CONFIG+'/tls/client.key':(ROLES['worker'],0o640),p.CONFIG+'/tls/server.key':(ROLES['mysql'],0o640)}
        for path,(gid,mode) in expected.items():
            info=self.fs.identity(path);need((info['uid'],info['gid'],info['mode'])==(0,gid,mode),'secret_access')

    def groups(self,role):
        import grp
        return {g.gr_gid for g in grp.getgrall() if 'm6e-'+role in g.gr_mem}|{ROLES[role]}

    def check_process(self,pid,group,uid):
        need(Path('/proc/'+str(pid)).stat().st_uid==uid,'service_uid')
        need('0::'+group in Path('/proc/'+str(pid)+'/cgroup').read_text().splitlines(),'service_cgroup')

    def check_listener_owners(self,listeners,services):
        for listener in listeners:
            unit='ubo-m6e-mysql.service' if listener['port']==3306 else 'ubo-m6e-apache.service'
            group=next(x['cgroup'] for x in services if x['unit']==unit)
            pids=Path('/sys/fs/cgroup'+group+'/cgroup.procs').read_text().split();owned=False
            for pid in pids:
                for descriptor in Path('/proc/'+pid+'/fd').iterdir():
                    try:target=os.readlink(descriptor)
                    except FileNotFoundError:continue
                    if target=='socket:['+str(listener['inode'])+']':owned=True
            need(owned,'listener_owning_cgroup')
