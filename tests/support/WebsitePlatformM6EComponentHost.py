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

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need
from WebsitePlatformM6EProcess import Failure
import WebsitePlatformM6EBootstrapPlan as b
import WebsitePlatformM6EComponent as c

def allowed(plan,manifest):
    """Closed output vocabulary. Interrupted random scratch is deliberately uncertain."""
    p=plan['paths'];out={}
    def add(path,kind,uid,gid,mode):out[path]=(kind,uid,gid,mode)
    for key in ('control','data'):add(p[key],stat.S_IFDIR,0,0,0o711)
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

def snapshot(fs,plan,manifest,info=identity):
    permitted=allowed(plan,manifest);found={}
    for key in ('control','data'):
        root=plan['paths'][key]
        if not fs.exists(root):continue
        names=[root]
        for directory,dirs,files in os.walk(fs.path(root),followlinks=False):
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
    execution='native-component-authored'
    info=staticmethod(identity)
    def __init__(self,host,ledger):
        self.host,self.ledger,self.fs=host,ledger,host.fs
        self.plan=c.plan(ledger.config,ledger.state['observations']['fixture']);self.module=c.publisher_module()
        c.validate_plan(self.plan,ledger.config,ledger.state['observations']['fixture'])
        need(os.geteuid()==0,'component_operator')
        need(hasattr(os,'pidfd_open') and hasattr(signal,'pidfd_send_signal'),'component_pidfd_required')
        self.deadline=min(host.owner.deadline,time.monotonic()+c.SECONDS)
        self.active=[];self.rows=[];self.manifest=None;self.stopped=False
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
        row=self.register(self.ledger.intent(path,'tree' if tree else 'directory','component-stage-a'))
        self.fs.mkdir(path,0o700);self.ledger.identify(row);self.fs.metadata(path,uid,gid,mode);self.ledger.identify(row)
    def write(self,path,raw,uid,gid,mode):
        row=self.register(self.ledger.intent(path,'file','component-stage-a'))
        self.fs.create(path,raw,lambda fd:self.ledger.identify(row,fd));self.fs.metadata(path,uid,gid,mode);self.ledger.identify(row)
        row['sha256']=digest(raw);self.ledger.save()
    def prepare(self,state):
        self.state=state;p=self.plan['paths']
        for key in ('control','data'):self.directory(p[key],0,0,0o711)
        self.directory(p['ipc'],2102,2000,0o2710)
        self.directory(p['served'],2102,2104,0o750,True);self.directory(p['journal'],2102,2102,0o700,True)
        for root,name,gid,mode in (('served','releases',2104,0o750),('served','assets',2104,0o750),
                ('served','staging',2102,0o700),('journal','manifests',2102,0o700)):
            self.directory(p[root]+'/'+name,2102,gid,mode)
        self.write(p['served']+'/access-probe',b'component access\n',2102,2102,0o600)
        self.write(p['journal']+'/target.lock',b'',2102,2102,0o600)
        self.write_plan()
        self.socket_row=self.register(self.ledger.generated(p['socket'],'component-stage-a'))
        self.pointer_row=self.register(self.ledger.generated(p['served']+'/candidate','component-stage-a'))
    def write_plan(self):self.write(self.plan['paths']['plan'],canonical(self.plan),0,0,0o644)
    def command(self,role):
        if role=='worker':return ['/usr/bin/php','-d','display_errors=0','-d','log_errors=0','-d','memory_limit=256M',
            b.CHECKOUT+'/tests/support/WebsitePlatformM6ECommissionChild.php','--component-stage-a']
        return ['/usr/bin/python3','-I','-B',b.CHECKOUT+'/tests/support/WebsitePlatformM6EComponentChild.py','--'+role]
    def spawn_options(self,role):
        uid=ROLES['worker' if role=='worker' else 'helper']
        return dict(user=uid,group=uid,extra_groups=[ROLES['apache']] if uid==2102 else [],umask=0o077,
                    env={'PATH':'/usr/bin:/bin','LANG':'C','HOME':'/nonexistent'},start_new_session=True)
    def start_value(self,role):
        value=dict(command='inspect' if role=='worker' else role)
        if role=='helper':value['worker_pid']=next(x['process'].pid for x in self.active if x['role']=='worker')
        return value
    def start(self,role):
        need(role in ('worker','helper','observer') and time.monotonic()<self.deadline,'component_spawn_bound')
        command=dict(id='component-a-'+role,caller='worker' if role=='worker' else 'helper',pid=None,exit=None,
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
    def children(self):
        return [dict(role=x['role'],uid=ROLES['worker' if x['role']=='worker' else 'helper'],
            observed_exit=x['process'].poll(),**copy.deepcopy(x['record'])) for x in self.active]
    def capture(self):
        # Fixed ledger names/identities only; live partial trees are not adopted.
        result=dict(resources=copy.deepcopy(self.rows))
        journal=self.plan['paths']['journal']+'/state.json'
        if self.fs.exists(journal):
            raw=self.fs.read(journal,65536);state=decode(raw)
            result['journal']=dict(sha256=digest(raw),fence=state['fence'],phase=state['phase'],settled=state['settled'])
        return result
    def stop(self):
        failures=[]
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
        if os.name!='nt':
            groups={x['process'].pid for x in self.active};count=0
            for path in Path('/proc').iterdir():
                if not path.name.isdecimal():continue
                count+=1;need(count<65536,'component_process_inventory')
                try:raw=(path/'stat').read_text();fields=raw[raw.rfind(')')+2:].split()
                except FileNotFoundError:continue
                need(int(fields[2]) not in groups and int(fields[3]) not in groups,'component_descendant_alive')
    def owned(self):
        for row in self.rows:
            if row['identity'] is not None:self.ledger.verify(row)
        return snapshot(self.fs,self.plan,self.manifest,self.info)
    def cleanup(self,owned):
        self.quiescent();need(self.owned()==owned,'component_replaced_after_capture')
        pointer=self.plan['paths']['served']+'/candidate'
        if pointer in owned:
            need(self.info(self.fs,pointer)==owned[pointer],'component_pointer_replaced');self.fs.unlink(pointer)
        self.ledger.release(self.pointer_row)
        for row in self.rows:
            if row['kind']=='tree':
                self.ledger.capture_tree(row,{ROLES['helper']},final=True);self.ledger.release(row)
        for row in reversed(self.rows):self.ledger.release(row)
        need(not any(self.fs.exists(self.plan['paths'][key]) for key in ('control','data')),'component_remaining')
