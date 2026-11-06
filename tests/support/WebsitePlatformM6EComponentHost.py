"""Fixed component process/ownership boundary inside the admitted commission service."""
import copy
import os
from pathlib import Path, PurePosixPath
import queue
import signal
import stat
import subprocess
import threading
import time

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, hash_, safe_error
from WebsitePlatformM6EProcess import Failure
import WebsitePlatformM6EBootstrapPlan as b
import WebsitePlatformM6EComponent as c

def serving_root(plan):
    prefix='component-apache-https-' if plan.get('owner')=='commission-apache' else 'component-https-'
    return b.STORAGE+'/'+prefix+plan['binding']['run_id']

def serving_config(config,plan):
    """Reuse the reviewed candidate policy; own one separate private listener."""
    raw=(Path(__file__).resolve().parents[2]/'infrastructure/apache/site-static-staging.conf.example').read_text()
    values=b.template_values(plan['release']['site_key']);root=serving_root(plan)
    values.update(SITE_ROOT=plan['paths']['served'],PRIVATE_ACCESS_LOG='/dev/null',PRIVATE_ERROR_LOG='/dev/null')
    rendered=b.render(raw,values).decode();body=rendered[rendered.rfind('<VirtualHost '):]
    need(body.count('<VirtualHost ')==1 and '/current' not in body,'component_candidate_config')
    body=body.replace('127.0.0.2:443','127.0.0.4:8443')
    # Apply the SAME static directory policy to real harmless denial canaries.
    start=body.index('    <Directory "'+plan['paths']['served']+'">')
    policy=body[start:body.index('    </Directory>',start)+len('    </Directory>')]
    policy=policy.replace(plan['paths']['served'],root+'/canaries').replace('Require all denied','Require all granted')
    extra='\n    Alias "/__component_denial__/" "'+root+'/canaries/"\n'+policy+'\n'
    body=body.replace('</VirtualHost>',extra+'</VirtualHost>')
    # Location authorization must not replace earlier directory/FilesMatch
    # denials. This fixture-only conjunction narrows the rendered access policy.
    body=body.replace('    <Location "/">','    <Location "/">\n        AuthMerging And')
    body=body.replace('    RewriteEngine On',
        '    SSLStrictSNIVHostCheck On\n    RewriteEngine On\n'
        '    RewriteCond %{HTTP_HOST} !^candidate\\.m6e\\.invalid(?::8443)?$ [NC,OR]\n'
        '    RewriteCond %{SSL:SSL_TLS_SNI} !^candidate\\.m6e\\.invalid$ [NC]\n'
        '    RewriteRule ^ - [R=403,END]')
    loads=['LoadModule '+name+'_module "'+item['path']+'"' for name,item in config['apache_modules'].items() if item['path']!='builtin']
    head=['ServerRoot "'+root+'"','ServerName candidate.m6e.invalid','PidFile '+root+'/runtime/apache.pid',
        'DefaultRuntimeDir '+root+'/runtime','User m6e-apache','Group m6e-apache','Listen 127.0.0.4:8443 https',
        'TypesConfig '+b.CONFIG+'/mime.types','ServerTokens Prod','ServerSignature Off','Timeout 5','KeepAlive Off',
        'StartServers 1','ServerLimit 1','ThreadLimit 8','ThreadsPerChild 8','MaxRequestWorkers 8',
        'SSLSessionCache none','Mutex pthread default','ErrorLog /dev/null','<Directory />','AllowOverride None','Require all denied','</Directory>']
    return ('\n'.join(loads+head)+'\n'+body).encode()

def serving_inventory(fs,plan,info= None):
    """Independent closed resource walk, including content, inode and link count."""
    info=info or identity;root=serving_root(plan)
    if not fs.exists(root):return None
    from WebsitePlatformM6ECommissionServing import CANDIDATE_CANARIES
    names={root,root+'/runtime',root+'/apache.conf',root+'/runtime/apache.pid',root+'/canaries'}
    for relative in CANDIDATE_CANARIES:
        name=root+'/canaries/'+relative;names.add(name)
        names.update(str(p) for p in PurePosixPath(name).parents if str(p).startswith(root+'/'))
    found={}
    def failed(error):raise error
    paths=[root]
    for directory,dirs,files in os.walk(fs.path(root),followlinks=False,onerror=failed):
        paths.extend(root+'/'+(Path(directory)/n).relative_to(fs.path(root)).as_posix() for n in dirs+files)
        need(len(paths)<=64,'component_serving_inventory_bound')
    for name in paths:
        need(name in names,'component_serving_unknown_path');item=info(fs,name)
        need(item['kind'] in (stat.S_IFREG,stat.S_IFDIR) and item['uid']==0 and item['gid']==0
            and item['dev']==info(fs,root)['dev'],'component_serving_metadata')
        if item['kind']==stat.S_IFREG:
            expected_mode=0o755 if name==root+'/canaries/m6e.cgi' else 0o644
            need(item['nlink']==1 and item['mode']==expected_mode,'component_serving_file_metadata')
            item['sha256']=digest(fs.read(name,65536))
        else:need(item['mode']==0o755,'component_serving_directory_metadata')
        found[name]=item
    return found

def allowed(plan,manifest):
    """Closed output vocabulary. Interrupted random scratch is deliberately uncertain."""
    p=plan['paths'];out={}
    def add(path,kind,uid,gid,mode):out[path]=(kind,uid,gid,mode)
    add(p['control'],stat.S_IFDIR,0,0,0o711)
    add(p['data'],stat.S_IFDIR,0,0,0o755)
    add(p['plan'],stat.S_IFREG,0,0,0o644);add(p['ipc'],stat.S_IFDIR,2102,2000,0o2710)
    add(p['socket'],stat.S_IFSOCK,2102,2000,0o660)
    for key,group,mode in (('served',2104,0o750),('journal',2102,0o700)):
        add(p[key],stat.S_IFDIR,2102,group,mode)
    for name,mode,group in (('releases',0o750,2104),('assets',0o750,2104),('staging',0o700,2102)):
        add(p['served']+'/'+name,stat.S_IFDIR,2102,group,mode)
    add(p['served']+'/access-probe',stat.S_IFREG,2102,2102,0o600)
    add(p['served']+'/candidate',stat.S_IFLNK,2102,2102,0o777)
    add(p['journal']+'/manifests',stat.S_IFDIR,2102,2102,0o700)
    for name in ('target.lock','state.json','manifests/'+plan['release']['release_key']+'.json'):
        add(p['journal']+'/'+name,stat.S_IFREG,2102,2102,0o600)
    prefix=p['served']+'/releases/'+plan['release']['release_key']
    for entry in (manifest or {}).get('files',[]):
        path=prefix+'/'+entry['relative_path'];add(path,stat.S_IFREG,2102,2104,0o440)
        parent=str(PurePosixPath(path).parent)
        while parent.startswith(prefix):
            add(parent,stat.S_IFDIR,2102,2104,0o550);parent=str(PurePosixPath(parent).parent)
        if entry['relative_path'].startswith('public/assets/'):
            add(p['served']+'/'+entry['relative_path'][7:],stat.S_IFREG,2102,2104,0o440)
    return out

def identity(fs,path):
    s=fs.path(path).lstat()
    if stat.S_ISLNK(s.st_mode):
        return dict(dev=s.st_dev,ino=s.st_ino,uid=s.st_uid,gid=s.st_gid,kind=stat.S_IFLNK,
                    mode=stat.S_IMODE(s.st_mode),nlink=s.st_nlink,target=os.readlink(fs.path(path)))
    return fs.identity(path)|dict(nlink=s.st_nlink)

def snapshot(fs,plan,manifest,info=identity,observer=False):
    permitted=allowed(plan,manifest);found={}
    for key in ('control','data'):
        root=plan['paths'][key]
        if not fs.exists(root):continue
        names=[root];walk=root
        if observer and key=='control':
            # The 0711 root permits stat of fixed names, not directory listing.
            # Its root-owned children are fixed; IPC is helper-readable. The
            # operator's later full walk must match this independent inventory,
            # so an unknown control-root child also prevents cleanup.
            names=[plan['paths'][name] for name in ('control','plan','ipc','socket')];walk=plan['paths']['ipc']
        def failed(error):raise error
        for directory,dirs,files in os.walk(fs.path(walk),followlinks=False,onerror=failed):
            names.extend(root+'/'+(Path(directory)/name).relative_to(fs.path(root)).as_posix() for name in dirs+files)
            need(len(names)<=12000,'component_inventory_bound')
        device=info(fs,root)['dev']
        for name in names:
            item=info(fs,name);need(name in permitted,'component_unknown_path')
            need(tuple(item[k] for k in ('kind','uid','gid','mode'))==permitted[name]
                 and item['dev']==device and (item['kind']==stat.S_IFDIR or item['nlink']==1),'component_object_metadata')
            if item['kind']==stat.S_IFLNK:
                need(item['target']=='releases/'+plan['release']['release_key']+'/public','component_pointer_target')
            found[name]=item
    return found

class CleanupFiles:
    """Extra identity checks at the existing ledger's removal boundary."""
    def __init__(self,fs,owned,info):self.fs,self.owned,self.info=fs,owned,info
    def __getattr__(self,name):return getattr(self.fs,name)
    def remove(self,path,directory):
        need(path in self.owned,'component_unobserved_removal')
        before=self.owned[path];now=self.info(self.fs,path)
        # Removing owned child directories legitimately changes the parent's
        # link count. All other directory fields, and every file field, remain.
        fields=set(before)-({'nlink'} if directory else set())
        need(set(now)==set(before) and all(now[k]==before[k] for k in fields),'component_removal_identity')
        self.fs.rmdir(path) if directory else self.fs.unlink(path)
    def unlink(self,path):self.remove(path,False)
    def rmdir(self,path):self.remove(path,True)

class Child:
    """Bounded report-only pipes. Artifact bytes use a different private endpoint."""
    def __init__(self,process,record,command):
        self.process,self.record,self.command=process,record,command
        self.queue=queue.Queue(16);self.threads=[];self.stopping=threading.Event();self.errors=[]
        def read(stream,side):
            total=0
            try:
                while not self.stopping.is_set():
                    line=stream.readline(2097153);total+=len(line)
                    if total>4194304 or len(line)>2097152:raise Failure('component_output_bound')
                    while not self.stopping.is_set():
                        try:self.queue.put((side,line),timeout=.05);break
                        except queue.Full:pass
                    if not line:break
            except BaseException:self.errors.append('component_pipe')
        for stream,side in ((process.stdout,'out'),(process.stderr,'err')):
            thread=threading.Thread(target=read,args=(stream,side),daemon=True);self.threads.append(thread);thread.start()
    def send(self,value):
        self.process.stdin.write(canonical(value)+b'\n');self.process.stdin.flush()
    def message(self,deadline):
        while time.monotonic()<deadline:
            need(not self.errors,'component_pipe')
            if getattr(self,'peer',None) is not None:
                need(self.peer.poll() in (None,0),'component_peer_failed')
            try:side,line=self.queue.get(timeout=.05)
            except queue.Empty:continue
            if not line:
                if side=='out':raise Failure('component_child_eof')
                continue
            need(side=='out','component_child_diagnostic')
            value=decode(line);need(set(value)=={'kind','pid','data'} and value['pid']==self.process.pid,'component_child_identity')
            return value
        raise Failure('component_child_timeout')

class Native:
    serving_enabled=True
    execution='native-component-authored'
    info=staticmethod(identity)
    def __init__(self,host,ledger,apache=False):
        self.host,self.ledger,self.fs=host,ledger,host.fs
        self.plan=c.plan(ledger.config,ledger.state['observations']['fixture'],apache=apache);self.module=c.publisher_module()
        c.validate_plan(self.plan,ledger.config,ledger.state['observations']['fixture'])
        need(os.geteuid()==0,'component_operator')
        need(hasattr(os,'pidfd_open') and hasattr(signal,'pidfd_send_signal'),'component_pidfd_required')
        self.deadline=min(host.owner.deadline,time.monotonic()+c.SECONDS)
        self.active=[];self.rows=[];self.manifest=None;self.stopped=False
    def admit(self):
        from WebsitePlatformM6EComponentEntry import supervisor
        from WebsitePlatformM6ECommission import UNIT, budget
        self.host.namespace(self.ledger.config);self.host.check_pins(self.ledger.config)
        if 'component_request' in self.ledger.state['observations']:
            need(self.plan.get('owner') is None,'component_apache_commission_only')
            supervisor(self.host,self.ledger)
            need(self.ledger.state['phase']=='component-running','component_running_admission')
        else:
            # Preserve the existing collector route, but never a direct runner bypass.
            context=self.host.component_context();group='/system.slice/'+UNIT
            need(context['cgroup']==['0::'+group],'component_supervisor_cgroup')
            unit=self.host.unit(UNIT);hash_(unit.get('InvocationID'),32)
            rows=[r for r in self.ledger.state['services'] if r['unit']==UNIT]
            need(len(rows)==1 and rows[0]['state']=='ACTIVE' and rows[0]['pid']==context['pid']
                 and rows[0]['invocation']==context['invocation']==unit['InvocationID']
                 and rows[0]['cgroup']==unit['ControlGroup']==group and int(unit['MainPID'])==context['pid']
                 and unit['FragmentPath']=='/run/systemd/system/'+UNIT and not unit['DropInPaths']
                 and self.ledger.state['phase']=='commissioning' and 'commission' in self.ledger.state['observations'],
                 'component_commission_admission')
            budget(self.host,self.ledger,c.SECONDS)
    def operational(self):
        f=self.ledger.state['observations']['fixture'];p=b.template_values(f['site_key'])
        root=p['EXACT_SERVED_SITE_ROOT'];journal=p['EXACT_PRIVATE_JOURNAL_ROOT']
        # Real read-only worker/database observation, never an inserted deployment.
        value=self.host.setup('inspect','worker')
        need(value==dict(fixture=f,database=self.ledger.state['observations']['database']),'component_bootstrap_database')
        need(decode(self.fs.read(journal+'/state.json'))==b.PRISTINE,'component_bootstrap_journal')
        need(not any(self.fs.exists(root+'/'+n) for n in ('current','candidate'))
             and not self.fs.exists(b.CONFIG+'/host-receipt.json'),'component_bootstrap_pointer')
        need(all(not self.fs.inventory(root+'/'+n) for n in ('assets','releases','staging')),'component_bootstrap_pool')
        return dict(database_sha256=digest(canonical(value)),journal_sha256=digest(self.fs.read(journal+'/state.json')),
            roots_sha256=digest(canonical([self.fs.identity(root),self.fs.identity(journal)])),current=0,candidate=0,receipt=0)
    def register(self,row):
        self.rows.append(row);self.state['resources'].append(row['id']);self.ledger.save();return row
    def directory(self,path,uid,gid,mode,tree=False):
        row=self.register(self.ledger.intent(path,'tree' if tree else 'directory',c.producer(self.plan)))
        self.fs.mkdir(path,0o700);self.ledger.identify(row);self.fs.metadata(path,uid,gid,mode);self.ledger.identify(row)
    def write(self,path,raw,uid,gid,mode):
        row=self.register(self.ledger.intent(path,'file',c.producer(self.plan)))
        self.fs.create(path,raw,lambda fd:self.ledger.identify(row,fd));self.fs.metadata(path,uid,gid,mode);self.ledger.identify(row)
        row['sha256']=digest(raw);self.ledger.save()
    def prepare(self,state):
        self.state=state;p=self.plan['paths']
        self.directory(p['control'],0,0,0o711)
        self.directory(p['data'],0,0,0o755)
        self.directory(p['ipc'],2102,2000,0o2710)
        self.directory(p['served'],2102,2104,0o750,True);self.directory(p['journal'],2102,2102,0o700,True)
        for root,name,gid,mode in (('served','releases',2104,0o750),('served','assets',2104,0o750),
                ('served','staging',2102,0o700),('journal','manifests',2102,0o700)):
            self.directory(p[root]+'/'+name,2102,gid,mode)
        self.write(p['served']+'/access-probe',b'component access\n',2102,2102,0o600)
        self.write(p['journal']+'/target.lock',b'',2102,2102,0o600)
        self.write_plan()
        self.socket_row=self.register(self.ledger.generated(p['socket'],c.producer(self.plan)))
        self.pointer_row=self.register(self.ledger.generated(p['served']+'/candidate',c.producer(self.plan)))
    def write_plan(self):self.write(self.plan['paths']['plan'],canonical(self.plan),0,0,0o644)
    def command(self,role):
        apache=self.plan.get('owner')=='commission-apache'
        if role=='worker':return ['/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','memory_limit=256M',
            b.CHECKOUT+'/tests/support/WebsitePlatformM6ECommissionChild.php','--component-apache-a' if apache else '--component-stage-a']
        return ['/usr/bin/python3','-I','-B',b.CHECKOUT+'/tests/support/WebsitePlatformM6EComponentChild.py','--'+('apache-' if apache else '')+role]
    def spawn_options(self,role):
        uid=ROLES['worker' if role in ('worker','http') else 'helper']
        return dict(user=uid,group=uid,extra_groups=[ROLES['apache']] if uid==2102 else [],umask=0o077,
                    env={'PATH':'/usr/bin:/bin','LANG':'C','HOME':'/nonexistent'},start_new_session=True)
    def start_value(self,role):
        value=dict(command='inspect' if role=='worker' else role)
        if role=='helper':value['worker_pid']=next(x['process'].pid for x in self.active if x['role']=='worker')
        if role=='http':value['manifest']=self.manifest
        return value
    def start(self,role):
        need(role in ('worker','helper','observer','reader','http') and time.monotonic()<self.deadline,'component_spawn_bound')
        command=dict(id=c.stem(self.plan)+'-'+role,caller='apache' if role=='reader' else 'worker' if role in ('worker','http') else 'helper',pid=None,exit=None,
                     started_ns=time.monotonic_ns(),finished_ns=None)
        self.ledger.state['commands'].append(command);self.ledger.save() # intent precedes spawn
        process=subprocess.Popen(self.command(role),stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,bufsize=0,**self.spawn_options(role))
        record=dict(pid=process.pid,exit=None,signal=0,last_barrier='SPAWNED',reaped=False,termination_requested=False,exit_before_cleanup=None)
        self.host.owner.children.append(process);self.host.owner.context.append(record)
        # Retain handles immediately, including constructor/ledger failure paths.
        item=dict(process=process,record=record,command=command,child=None,role=role,pidfd=None);self.active.append(item)
        if os.name!='nt':item['pidfd']=os.pidfd_open(process.pid)
        command['pid']=process.pid;self.state['children']=self.children();self.ledger.save()
        child=Child(process,record,command);item['child']=child
        child.send(self.start_value(role));return child
    def receive(self,child,kind):
        message=child.message(self.deadline)
        if message['kind']=='failure':
            value=message['data'];need(type(value) is dict and set(value)=={'type','code'},'component_failure_shape')
            # Never persist arbitrary child diagnostics. Hash the bounded safe
            # classification, retaining the actual PID and expected stage.
            self.state['observations'].setdefault('child_failures',[]).append(dict(pid=child.process.pid,
                stage=kind,classification_sha256=digest(canonical(value))))
            self.ledger.save()
        need(message['kind']==kind,'component_message_kind');return message['data']
    def source(self,worker):
        value=self.receive(worker,'source');need(value['uid']==ROLES['worker'] and value['write_denied']==dict(served=1,journal=1),'component_worker_permissions')
        self.state['observations']['worker_access']=dict(uid=value['uid'],write_denied=value['write_denied']);self.ledger.save()
        return value['source']
    def ready(self,helper):
        value=self.receive(helper,'ready');need(value['uid']==ROLES['helper'] and value['source_errno']==13,'component_helper_permissions');return value
    def identify_socket(self,ready):
        info=self.fs.identity(self.plan['paths']['socket'])
        need(tuple(info[k] for k in ('kind','uid','gid','mode'))==(stat.S_IFSOCK,2102,2000,0o660),'component_private_socket')
        self.ledger.identify(self.socket_row)
    def transfer(self,worker,helper,note):
        helper.peer=worker.process;worker.peer=helper.process
        worker.send(dict(command='transfer'))
        for kind in ('receiver','helper'):
            value=self.receive(helper,kind);note(kind,value)
            if kind=='helper':need(value['peer']==dict(uid=2000,pid=worker.process.pid),'component_actual_peer')
        transfer=self.receive(worker,'transfer');need(transfer['database_pristine'] is True,'component_no_deployment')
        need(transfer['ack']['stage_sha256']==self.state['observations']['helper']['stage_sha256'],'component_ack_binding')
        self.completed(helper);self.completed(worker);return transfer
    def completed(self,child):
        child.process.wait(timeout=max(.001,min(5,self.deadline-time.monotonic())))
        need(child.process.returncode==0,'component_child_failed')
        # Drain terminal pipes: no ignored diagnostics/trailing reports.
        for thread in child.threads:thread.join(timeout=1)
        need(not child.errors and all(not t.is_alive() for t in child.threads),'component_pipe_completion')
        while not child.queue.empty():need(not child.queue.get_nowait()[1],'component_extra_report')
        child.record.update(exit=0,reaped=True,last_barrier='EXITED');child.command.update(exit=0,finished_ns=time.monotonic_ns());self.ledger.save()
    def observer(self):
        child=self.start('observer');value=self.receive(child,'observer');self.completed(child);return value
    def serving_prepare(self):
        from WebsitePlatformM6ECommissionServing import CANDIDATE_CANARIES
        self.serving_rows=[];self.reader=None;self.reader_attempted=False;self.reader_clean=False;self.serving_verified_after=False;root=serving_root(self.plan)
        def register(row):
            self.serving_rows.append(row);self.state['resources'].append(row['id']);self.ledger.save()
        directories={root,root+'/runtime',root+'/canaries'}
        files={root+'/apache.conf':serving_config(self.ledger.config,self.plan)}
        for name,raw in CANDIDATE_CANARIES.items():
            target=root+'/canaries/'+name;files[target]=raw
            directories.update(str(p) for p in PurePosixPath(target).parents if str(p).startswith(root+'/'))
        for name in sorted(directories,key=lambda p:(p.count('/'),p)):
            register(self.ledger.directory(name))
        for name,raw in sorted(files.items()):
            register(self.ledger.write(name,raw,mode=0o755 if name==root+'/canaries/m6e.cgi' else 0o644))
        self.pid_row=self.ledger.generated(root+'/runtime/apache.pid','component-private-apache');register(self.pid_row)
        self.serving_prepared=serving_inventory(self.fs,self.plan,self.info)
    def configuration_identity(self):
        path=serving_root(self.plan)+'/apache.conf'
        rows=[r for r in self.serving_rows if r['path']==path]
        need(len(rows)==1,'component_config_ownership');row=rows[0];self.ledger.verify(row)
        raw=self.fs.read(path,65536);info=self.info(self.fs,path)
        need(info['kind']==stat.S_IFREG and info['uid']==info['gid']==0 and info['mode']==0o644
             and info['nlink']==1 and digest(raw)==row['sha256']
             and raw==serving_config(self.ledger.config,self.plan),'component_config_identity')
        return dict(identity=info,sha256=digest(raw))
    def configtest(self,note):
        """Fixed pinned executable, exact owned config, durable command before exec."""
        self.configtest_validated=None;self.configtest_children=[];self.configtest_started=False
        before=self.configuration_identity();self.host.check_pins(self.ledger.config)
        name=c.stem(self.plan)+'-configtest'
        need(not any(r['id']==name for r in self.ledger.state['commands']),'component_configtest_repeated')
        need(time.monotonic()<self.deadline,'component_configtest_deadline')
        owner_deadline=self.host.owner.deadline
        self.host.owner.deadline=min(owner_deadline,self.deadline)
        start=len(self.host.owner.context);result=None;primary=None
        diagnostic=dict(execution='native' if self.execution=='native-component-authored' else 'modeled',
            configuration=before,classification='UNRESOLVED',stdout_sha256=None,stderr_sha256=None)
        try:
            self.configtest_started=True
            result=self.host.run(name,['/usr/sbin/apache2','-t','-f',serving_root(self.plan)+'/apache.conf'],
                min(10,max(.001,self.deadline-time.monotonic())),allowed=(0,1,2),return_result=True)
            diagnostic.update(stdout_sha256=digest(result['stdout']),stderr_sha256=digest(result['stderr']))
            need(type(result['exit']) is int and result['exit']==0,'component_configtest_failed')
            need(self.configuration_identity()==before,'component_configtest_changed')
            rows=[r for r in self.ledger.state['commands'] if r['id']==name]
            need(len(rows)==1 and rows[0]['caller']=='operator' and rows[0]['pid']==result['pid']
                 and type(result['pid']) is int and result['pid']>0 and rows[0]['exit']==0
                 and type(rows[0]['finished_ns']) is int and rows[0]['finished_ns']>=rows[0]['started_ns'],
                 'component_configtest_command')
            self.configtest_validated=dict(configuration=before,command=copy.deepcopy(rows[0]))
            diagnostic['classification']='VALIDATED'
        except BaseException as error:
            primary=error;diagnostic.update(classification='REJECTED',failure=safe_error(error));raise
        finally:
            # Restore the preexisting absolute owner deadline for cleanup/readback,
            # never a fresh timeout or an extension of watchdog/admission expiry.
            self.host.owner.deadline=owner_deadline
            self.configtest_children=self.host.owner.context[start:]
            diagnostic['children']=copy.deepcopy(self.configtest_children)
            try:note('configtest',diagnostic)
            except BaseException as error:
                self.configtest_validated=None
                if primary is None:raise
                self.ledger.failure(error,'component-configtest-capture')
    def configtest_quiescent(self):
        if not getattr(self,'configtest_started',False):return
        rows=[r for r in self.ledger.state['commands'] if r['id']==c.stem(self.plan)+'-configtest']
        children=self.configtest_children
        need(len(rows)==len(children)==1 and children[0]['reaped'] and children[0]['exit'] is not None
             and children[0]['pid']==rows[0]['pid'] and children[0]['exit']==rows[0]['exit'],
             'component_configtest_not_quiescent')
    def reader_admission(self):
        value=getattr(self,'configtest_validated',None)
        need(value is not None,'component_configtest_required')
        rows=[r for r in self.ledger.state['commands'] if r['id']==c.stem(self.plan)+'-configtest']
        need(rows==[value['command']],'component_configtest_command_changed')
        children=self.configtest_children
        need(len(children)==1 and children[0]['pid']==value['command']['pid'] and children[0]['reaped']
             and children[0]['exit']==0,'component_configtest_process')
        need(self.configuration_identity()==value['configuration'],'component_configtest_changed')
        need(time.monotonic()<self.deadline,'component_reader_deadline')
        self.host.check_pins(self.ledger.config)
        need(self.configuration_identity()==value['configuration'],'component_configtest_changed')
        need(time.monotonic()<self.deadline,'component_reader_deadline')
    def reader_start(self):
        # Fixed test process, not an installed service or operational publisher.
        need(time.monotonic()<self.deadline,'component_reader_deadline')
        command=dict(id=c.stem(self.plan)+'-reader',caller='operator',pid=None,exit=None,started_ns=time.monotonic_ns(),finished_ns=None)
        self.ledger.state['commands'].append(command);self.ledger.save()
        self.reader_admission();self.reader_attempted=True
        process=subprocess.Popen(['/usr/sbin/apache2','-X','-f',serving_root(self.plan)+'/apache.conf'],
            stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,bufsize=0,
            start_new_session=True,umask=0o022,env={'PATH':'/usr/bin:/bin','LANG':'C','HOME':'/nonexistent'})
        record=dict(pid=process.pid,exit=None,signal=0,last_barrier='SPAWNED',reaped=False,termination_requested=False,exit_before_cleanup=None)
        self.host.owner.children.append(process);self.host.owner.context.append(record)
        self.reader=dict(process=process,record=record,command=command,child=None,role='reader',pidfd=None)
        self.active.append(self.reader);self.reader['pidfd']=os.pidfd_open(process.pid)
        command['pid']=process.pid;self.ledger.save()
        # Drain bounded diagnostic pipes without storing Apache diagnostics.
        self.reader['child']=Child(process,record,command)
        until=min(self.deadline,time.monotonic()+3)
        while not self.fs.exists(self.pid_row['path']) and process.poll() is None and time.monotonic()<until:time.sleep(.01)
        need(process.poll() is None and self.fs.exists(self.pid_row['path']),'component_reader_start')
        need(self.fs.read(self.pid_row['path'],32).strip()==str(process.pid).encode(),'component_reader_pid')
        self.ledger.identify(self.pid_row)
    def serving_checks(self,note):
        # A separate unprivileged HTTP client keeps socket/TLS stalls inside the
        # existing absolute component deadline, even during a slow header/body.
        def identity():
            process=self.reader['process'];need(process.poll() is None,'component_reader_interrupted')
            raw=Path('/proc',str(process.pid),'status').read_text();need(len(raw)<=16384,'component_reader_status_bound')
            fields=dict(line.split(':',1) for line in raw.splitlines() if ':' in line)
            uid=[int(x) for x in fields['Uid'].split()];gid=[int(x) for x in fields['Gid'].split()]
            groups=[int(x) for x in fields['Groups'].split()]
            need(uid==[ROLES['apache']]*4 and gid==[ROLES['apache']]*4
                 and set(groups)<={ROLES['apache']} and int(fields['CapEff'],16)==0
                 and int(fields['CapPrm'],16)==0 and process.poll() is None,'component_reader_identity')
            return dict(pid=process.pid,uid=uid,gid=gid,groups_sha256=digest(canonical(groups)),capabilities=0)
        note('serving_reader_before',identity())
        child=self.start('http');child.peer=self.reader['process']
        for _ in range(48002):
            message=child.message(self.deadline)
            need(self.reader['process'].poll() is None,'component_reader_interrupted')
            diagnostic=self.reader['child'];need(not diagnostic.errors,'component_reader_diagnostic')
            while not diagnostic.queue.empty():need(not diagnostic.queue.get_nowait()[1],'component_reader_diagnostic')
            if message['kind']=='http-done':
                need(message['data']==dict(uid=ROLES['worker']),'component_http_identity')
                observed=self.state['observations'].get('serving',{})
                need(observed.get('verified_files')==len(self.manifest['files'])
                     and observed.get('verified_heads')==len(self.manifest['files']),'component_http_completion')
                self.completed(child);note('serving_reader_after',identity());return
            need(message['kind']=='http-progress','component_http_child')
            note('serving',message['data'])
        raise Failure('component_http_report_bound')
    def serving_verify(self,value):
        original=self.state['observations']['observer']
        need(type(value) is dict and value.get('inventory_sha256')==original.get('inventory_sha256'),'component_serving_original_inventory')
        found=value.get('serving_inventory');need(type(found) is dict,'component_serving_inventory_required')
        need(set(found)=={r['path'] for r in self.serving_rows},'component_serving_inventory_missing')
        for row in self.serving_rows:
            self.ledger.verify(row);item=found[row['path']]
            need(all(item.get(k)==v for k,v in row['identity'].items()),'component_serving_identity')
            if row.get('sha256') is not None:need(item.get('sha256')==row['sha256'],'component_serving_content')
        need(found[self.pid_row['path']]['sha256']==digest(str(self.reader['process'].pid).encode()+b'\n'),'component_serving_pid_content')
        return found
    def serve(self,note):
        primary=None
        try:
            need(digest(canonical(snapshot(self.fs,self.plan,self.manifest,self.info)))==self.state['observations']['observer']['inventory_sha256'],
                 'component_serving_original_inventory')
            self.serving_prepare();self.configtest(note);self.reader_start()
            before=self.observer();note('serving_before',before);self.serving_verify(before)
            note('serving_execution',dict(http='native' if self.execution=='native-component-authored' else 'modeled',
                tls='native' if self.execution=='native-component-authored' else 'modeled',apache='native' if self.execution=='native-component-authored' else 'modeled'))
            self.serving_checks(note)
        except BaseException as error:
            primary=error;self.ledger.failure(error,'component-serving');raise
        finally:
            try:
                if getattr(self,'reader',None) is not None:
                    after=self.observer();note('serving_after',after);found=self.serving_verify(after)
                    before=self.state['observations'].get('serving_before',{}).get('serving_inventory')
                    need(found==before,'component_serving_inventory_changed')
                    self.serving_verified_after=True
            except BaseException as error:
                self.ledger.failure(error,'component-after-serving')
                if primary is None:raise
    def stop_reader(self):
        process=self.reader['process']
        need(process.poll() is None,'component_reader_unknown_exit')
        signal.pidfd_send_signal(self.reader['pidfd'],signal.SIGTERM)
        process.wait(timeout=3);need(process.returncode==0,'component_reader_exit')
        self.reader_clean=True
    def serving_owned(self):
        self.configtest_quiescent()
        if self.reader is None:
            need(not self.reader_attempted,'component_reader_start_uncertain')
            found=serving_inventory(self.fs,self.plan,self.info)
            need(found==getattr(self,'serving_prepared',None) and found is not None,'component_unstarted_serving_changed')
            need(not self.fs.exists(self.pid_row['path']),'component_unstarted_pid')
            return found
        need(self.reader is not None and self.reader_clean,'component_reader_not_quiescent')
        need(self.serving_verified_after,'component_after_serving_required')
        before=self.state['observations'].get('serving_before',{}).get('serving_inventory')
        need(type(before) is dict,'component_serving_inventory_required')
        found=serving_inventory(self.fs,self.plan,self.info);expected=copy.deepcopy(before)
        # Apache removes its own PID file on verified graceful exit. No other
        # missing or replaced resource is accepted; static rows remain pinned.
        if self.pid_row['path'] not in (found or {}):expected.pop(self.pid_row['path'])
        need(found==expected,'component_serving_cleanup_inventory')
        return found
    def cleanup_serving(self):
        found=self.serving_owned();original=self.ledger.fs
        self.ledger.fs=CleanupFiles(self.fs,{p:{k:v for k,v in x.items() if k!='sha256'} for p,x in found.items()},self.info)
        try:
            for row in reversed(self.serving_rows):self.ledger.release(row)
        finally:self.ledger.fs=original
        need(not self.fs.exists(serving_root(self.plan)),'component_serving_remaining')
    def children(self):
        return [dict(role=x['role'],uid=ROLES['apache' if x['role']=='reader' else 'worker' if x['role'] in ('worker','http') else 'helper'],
            observed_exit=x['process'].poll(),**copy.deepcopy(x['record'])) for x in self.active]
    def capture(self):
        # Fixed ledger names/identities only; live partial trees are not adopted.
        result=dict(resources=copy.deepcopy(self.rows),serving_resources=copy.deepcopy(getattr(self,'serving_rows',[])))
        journal=self.plan['paths']['journal']+'/state.json'
        if self.fs.exists(journal):
            raw=self.fs.read(journal,65536);state=decode(raw)
            result['journal']=dict(sha256=digest(raw),fence=state['fence'],phase=state['phase'],settled=state['settled'])
        return result
    def stop(self):
        failures=[]
        if getattr(self,'reader',None) is not None:
            try:self.stop_reader()
            except BaseException:
                self.reader_clean=False;failures.append(self.reader['process'].pid)
        for item in self.active:
            process,record=item['process'],item['record'];record['exit_before_cleanup']=process.poll()
            try:
                if os.name=='nt':
                    if process.poll() is None:process.kill();record['termination_requested']=True
                elif process.poll() is None:
                    # Stable process capability, never signal a recycled numeric
                    # PID/group. Any surviving descendants make cleanup LIMITED.
                    need(item['pidfd'] is not None,'component_process_capability')
                    signal.pidfd_send_signal(item['pidfd'],signal.SIGKILL);record['termination_requested']=True
                process.wait(timeout=3);record.update(exit=process.returncode,signal=max(0,-process.returncode),reaped=True)
                item['command'].update(exit=process.returncode,finished_ns=time.monotonic_ns())
            except BaseException:failures.append(process.pid)
            child=item['child']
            if child is not None:
                child.stopping.set()
                for thread in child.threads:thread.join(timeout=.2)
            for stream in (process.stdin,process.stdout,process.stderr):
                try:stream.close()
                except OSError:failures.append(process.pid)
            if item['pidfd'] is not None:
                os.close(item['pidfd']);item['pidfd']=None
        self.stopped=False;self.ledger.save();need(not failures,'component_stop_failed');self.stopped=True
    def quiescent(self):
        need(self.stopped and all(x['process'].poll() is not None and x['record']['reaped'] for x in self.active),'component_live_process')
        self.configtest_quiescent()
        if os.name!='nt':
            groups={x['process'].pid for x in self.active}|{x['pid'] for x in getattr(self,'configtest_children',[])};count=0
            for path in Path('/proc').iterdir():
                if not path.name.isdecimal():continue
                count+=1;need(count<65536,'component_process_inventory')
                try:raw=(path/'stat').read_text();fields=raw[raw.rfind(')')+2:].split()
                except FileNotFoundError:continue
                need(int(fields[2]) not in groups and int(fields[3]) not in groups,'component_descendant_alive')
    def owned(self):
        self.quiescent()
        if getattr(self,'serving_rows',None):self.serving_owned()
        for row in self.rows:
            if row['identity'] is not None:self.ledger.verify(row)
        found=snapshot(self.fs,self.plan,self.manifest,self.info)
        observations=self.state['observations']
        # A partial transfer may leave only its mutable state file alongside
        # explicit construction rows. Published/generated output needs the
        # earlier independent observer; it cannot be adopted by pathname.
        generated=set(found)-{row['path'] for row in self.rows}-{self.plan['paths']['journal']+'/state.json'}
        if 'observer' in observations or 'helper' in observations or generated:
            observed=observations.get('observer')
            need(type(observed) is dict and 'inventory_sha256' in observed,'component_observer_inventory_required')
            expected=observed['inventory_sha256'];hash_(expected)
            need(digest(canonical(found))==expected,'component_observer_inventory_changed')
        return found
    def cleanup(self,owned):
        self.quiescent();need(self.owned()==owned,'component_replaced_after_capture')
        if getattr(self,'serving_rows',None):self.cleanup_serving()
        pointer=self.plan['paths']['served']+'/candidate';trees={}
        # Seed the ledger from the observer-bound snapshot BEFORE capture_tree.
        # That call must verify these identities, never establish a later baseline.
        for row in self.rows:
            if row['kind']=='tree':
                prefix=row['path']+'/'
                expected={name[len(prefix):]:{k:item[k] for k in ('dev','ino','uid','gid','kind','mode')}
                    for name,item in owned.items() if name.startswith(prefix) and name!=pointer}
                need(not row['children'] and not row['retired'] and not row['sealed'],'component_tree_already_captured')
                trees[row['id']]=expected;row['children']=copy.deepcopy(expected);row['sealed']=True
        self.ledger.save()
        original=self.ledger.fs;checked=CleanupFiles(self.fs,owned,self.info);self.ledger.fs=checked
        try:
            if pointer in owned:checked.unlink(pointer)
            self.ledger.release(self.pointer_row)
            for row in self.rows:
                if row['kind']=='tree':
                    self.ledger.capture_tree(row,{ROLES['helper']},final=True)
                    need(row['children']==trees[row['id']] and not row['retired'],'component_tree_inventory_changed')
                    self.ledger.release(row)
            for row in reversed(self.rows):self.ledger.release(row)
        finally:self.ledger.fs=original
        need(not any(self.fs.exists(self.plan['paths'][key]) for key in ('control','data')),'component_remaining')
