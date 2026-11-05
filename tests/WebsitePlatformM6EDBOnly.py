#!/usr/bin/env python3
"""Bounded DB-only operator. --inspect is read-only; --execute requires separate host authorization."""
import os
from pathlib import Path
import re
import stat
import sys
import time

sys.path.insert(0,str(Path(__file__).resolve().parent/'support'))
from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, safe_error
from WebsitePlatformM6EProcess import Owner
import WebsitePlatformM6EDBOnlyContract as c
from WebsitePlatformM6EDBOnlyRuntime import source_inventory


def run(owner,argv,seconds=20,stdin=b'',limit=1048576,allowed=(0,)):
    value=owner.capture(argv,seconds,stdin=stdin,limit=limit,error_limit=16384)
    need(value['exit'] in allowed,'db_only_operator_command')
    # stderr never enters retained evidence (SQL/engine errors can contain private material).
    return value


def engine(owner,args,**kwargs):
    return run(owner,[*c.CLIENT,*args],**kwargs)


def load_request(path):
    p=Path(path);s=p.lstat()
    need(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_size<=65536 and p.resolve()==p.absolute(),'db_only_request_file')
    if sys.platform=='linux':need(s.st_uid==1000 and stat.S_IMODE(s.st_mode)==0o600,'db_only_request_owner')
    raw=p.read_bytes();after=p.lstat()
    need((after.st_dev,after.st_ino,after.st_size)==(s.st_dev,s.st_ino,s.st_size),'db_only_request_replaced')
    return c.request(decode(raw))


USER_SERVICE_PROPERTIES = {'docker.service': ('ActiveState','SubState','MainPID','Delegate'),
                           'docker.socket': ('LoadState','ActiveState')}


def host_operator():
    need(sys.platform=='linux','db_only_host_operator')
    import pwd
    need(os.getuid()==os.geteuid()==os.getgid()==os.getegid()==1000
         and pwd.getpwuid(1000).pw_name=='codex-validation' and os.uname().nodename=='ubo-stage-app','db_only_host_operator')


def user_service_command(unit):
    """Only the two fixed read-only queries, through this operator's existing private manager socket."""
    need(unit in USER_SERVICE_PROPERTIES,'db_only_user_service_unit');host_operator()
    try:
        for name,modes,socket in [('/run/user/1000',(0o700,),False),
                                  ('/run/user/1000/systemd',(0o700,0o755),False),
                                  ('/run/user/1000/systemd/private',(0o700,),True)]:
            p=Path(name);s=p.lstat()
            need(p.resolve()==p and s.st_uid==s.st_gid==1000 and stat.S_IMODE(s.st_mode) in modes
                 and (stat.S_ISSOCK(s.st_mode) and s.st_nlink==1 if socket else stat.S_ISDIR(s.st_mode)),
                 'db_only_user_service_endpoint')
    except (OSError,RuntimeError):raise ValueError('db_only_user_service_endpoint') from None
    # `show` uses the private manager via XDG_RUNTIME_DIR. Pin older systemd's
    # default-user-bus fallback to that SAME endpoint; never redirect to /bus or autolaunch.
    return ['/usr/bin/env','XDG_RUNTIME_DIR=/run/user/1000',
            'DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/systemd/private',
            '/usr/bin/systemctl','--user','show',unit,'--property='+','.join(USER_SERVICE_PROPERTIES[unit])]


def user_service_query(owner,unit):
    result=run(owner,user_service_command(unit),10,allowed=(0,1))
    try:
        rows=[line.split('=',1) for line in result['stdout'].decode().splitlines()]
        fields=dict(rows)
    except (UnicodeError,ValueError):raise ValueError('db_only_user_service_response') from None
    need(len(rows)==len(fields)==len(USER_SERVICE_PROPERTIES[unit]) and set(fields)==set(USER_SERVICE_PROPERTIES[unit]),
         'db_only_user_service_response')
    need(result['exit']==0 or unit=='docker.socket' and fields==dict(LoadState='not-found',ActiveState='inactive'),
         'db_only_user_service_query')
    return fields


def engine_active(owner):
    fields=user_service_query(owner,'docker.service')
    need(fields['ActiveState'] in ('active','inactive','failed') and fields['Delegate'] in ('yes','no')
         and re.fullmatch('[0-9]{1,10}',fields['MainPID']) is not None and int(fields['MainPID'])<2**31,'db_only_engine_service')
    if fields['ActiveState']!='active':
        need(fields['MainPID']=='0' and fields['SubState']==('dead' if fields['ActiveState']=='inactive' else 'failed'),'db_only_engine_service')
        return False,fields
    need(fields['SubState']=='running' and fields['Delegate']=='yes' and int(fields['MainPID'])>0,'db_only_engine_service')
    socket_fields=user_service_query(owner,'docker.socket')
    need(socket_fields['LoadState'] in ('not-found','loaded','masked') and socket_fields['ActiveState']=='inactive','db_only_socket_activation')
    need(Path('/proc/'+fields['MainPID']).stat().st_uid==1000,'db_only_engine_operator')
    socket=Path('/run/user/1000/docker.sock').lstat()
    need(stat.S_ISSOCK(socket.st_mode) and socket.st_uid==1000,'db_only_engine_socket')
    return True,fields


def host_readiness(owner,req,source=True):
    """No directory creation, evidence writes, daemon start or socket use while inactive."""
    host_operator()
    paths=[Path(c.BASE),Path(c.EVIDENCE)]+([Path(c.EXPORTS)] if source else [])
    for p in paths:
        s=p.lstat();need(stat.S_ISDIR(s.st_mode) and p.resolve()==p and s.st_uid==1000 and stat.S_IMODE(s.st_mode)==0o700,'db_only_host_roots')
    need(Path(c.BASE).stat().st_dev!=Path('/').stat().st_dev,'db_only_test_volume')
    memory={k:int(v.split()[0])*1024 for k,v in (line.split(':',1) for line in Path('/proc/meminfo').read_text().splitlines())}
    capacity=dict(cpus=len(os.sched_getaffinity(0)),memory=memory['MemAvailable'],
        root_free=os.statvfs('/').f_bavail*os.statvfs('/').f_frsize,
        volume_free=os.statvfs(c.BASE).f_bavail*os.statvfs(c.BASE).f_frsize)
    if source:need(capacity['cpus']>=4 and capacity['memory']>=4563402752 and capacity['root_free']>=1024**3 and capacity['volume_free']>=10*1024**3,'db_only_host_capacity')
    value=dict(profile=c.PROFILE,host='ubo-stage-app',operator_uid=1000,capacity=capacity,
        host_namespaces={n:os.readlink('/proc/self/ns/'+n) for n in ('user','net','mnt','pid')},
        test_volume_dev=Path(c.BASE).stat().st_dev,request_sha256=None if req is None else digest(canonical(req)),**c.AUTHORITY)
    if source:
        export=Path(c.EXPORTS)/req['source_sha'];need(export.resolve()==export,'db_only_export_path')
        need(Path(__file__).resolve()==export/'tests/WebsitePlatformM6EDBOnly.py','db_only_operator_source')
        for p in [export,*export.rglob('*')]:
            s=p.lstat();need((stat.S_ISREG(s.st_mode) or stat.S_ISDIR(s.st_mode)) and not s.st_mode&0o222
                 and s.st_uid==s.st_gid==1000 and stat.S_IMODE(s.st_mode) in ((0o555,) if stat.S_ISDIR(s.st_mode) else (0o444,0o555))
                 and (not stat.S_ISREG(s.st_mode) or s.st_nlink==1),'db_only_immutable_export')
        inventory=source_inventory(export,req['source_sha'],owner)
        need(digest(canonical(inventory))==req['source_inventory_sha256'],'db_only_export_inventory')
        value['source_inventory_sha256']=req['source_inventory_sha256']
    active,service=engine_active(owner);value['engine']=service
    if not active:
        value.update(result='ENGINE INACTIVE; IMAGE AVAILABILITY UNVERIFIED',images='UNVERIFIED')
        return value
    if not source:
        value.update(result='READ-ONLY HOST/ENGINE STATE; IMAGES NOT INSPECTED',images='UNVERIFIED');return value
    # Only now is it safe to contact the already-running socket; inspection cannot activate it.
    info=decode(engine(owner,['info','--format','{{json .}}'])['stdout'])
    need(info['CgroupVersion']=='2' and info['CgroupDriver']=='systemd' and 'name=rootless' in info['SecurityOptions'],'db_only_rootless_cgroup')
    images={}
    for role in ('runtime','mysql'):
        image=decode(engine(owner,['image','inspect',req[role+'_image']])['stdout'])
        need(len(image)==1 and image[0]['Id']==req[role+'_image'],'db_only_image_identity')
        need(image[0]['Os']=='linux' and set(image[0]['Config'].get('Volumes') or {})<=set(c.mount_limits(role)), 'db_only_image_os_volumes')
        images[role]=dict(id=image[0]['Id'],architecture=image[0]['Architecture'])
    value.update(result='READ-ONLY PREREQUISITES OBSERVED; RUNTIME CAPABILITIES UNVERIFIED',images=images)
    return value


# MySQL images need not contain Python. This fixed read-only probe uses standard image utilities.
def mysql_probe(owner,cid):
    paths=c.mount_limits('mysql')
    script='set -eu\n'
    for label,path in [('uid-map','/proc/self/uid_map'),('gid-map','/proc/self/gid_map'),('status','/proc/self/status'),('mounts','/proc/self/mountinfo'),
        ('memory','/sys/fs/cgroup/memory.max'),('swap','/sys/fs/cgroup/memory.swap.max'),('pids','/sys/fs/cgroup/pids.max'),('cpu','/sys/fs/cgroup/cpu.max')]:
        script+=f"printf '\\n@@{label}\\n'; cat {path}\n"
    for ns in ('user','net','mnt','pid'):script+=f"printf '\\n@@ns-{ns}\\n'; readlink /proc/self/ns/{ns}\n"
    script+="printf '\\n@@interfaces\\n'; ls -1 /sys/class/net\n"
    for i,path in enumerate(paths):script+=f"printf '\\n@@fs-{i}\\n'; stat -f -c '%S %b' {path}; stat -c '%d %i' {path}\n"
    raw=engine(owner,['exec',cid,'/bin/sh','-c',script],limit=65536)['stdout'].decode()
    parts=re.split(r'\n@@([a-z0-9-]+)\n',raw);fields={parts[i]:parts[i+1].strip() for i in range(1,len(parts),2)}
    status=dict(line.split(':',1) for line in fields['status'].splitlines() if ':' in line);storage={}
    for i,path in enumerate(paths):
        found=[]
        for line in fields['mounts'].splitlines():
            left,right=line.split(' - ',1);a=left.split();b=right.split()
            if a[4]==path:found.append((b[0],a[5].split(',')+b[2].split(',')))
        need(len(found)==1,'db_only_mysql_mount');kind,options=found[0]
        size=[o[5:] for o in options if o.startswith('size=')];need(len(size)==1,'db_only_mysql_mount_size')
        text=size[0];units={'k':1024,'m':1024**2,'g':1024**3};size=int(text[:-1])*units[text[-1].lower()] if text[-1].lower() in units else int(text)
        values=list(map(int,fields['fs-'+str(i)].split()))
        storage[path]=dict(type=kind,options=sorted(set(options)),size_option=size,bytes=values[0]*values[1],dev=values[2],ino=values[3])
    return dict(uid=int(status['Uid'].split()[1]),gid=int(status['Gid'].split()[1]),
        uid_map=[list(map(int,x.split())) for x in fields['uid-map'].splitlines()],gid_map=[list(map(int,x.split())) for x in fields['gid-map'].splitlines()],
        namespaces={n:fields['ns-'+n] for n in ('user','net','mnt','pid')},memory_max=int(fields['memory']),swap_max=int(fields['swap']),
        pids_max=int(fields['pids']),cpu_max=list(map(int,fields['cpu'].split())),no_new_privs=int(status['NoNewPrivs']),
        capabilities=int(status['CapEff'].strip(),16),interfaces=sorted(fields['interfaces'].splitlines()),storage=storage,source_readonly=False)


class Operator:
    def __init__(self,req,store,owner):
        self.req=c.request(req);self.store=store;self.owner=owner;self.ids={};self.intents=set();self.first=None;self.secondary=[];self.expires_at=1

    def record(self,kind,**value):
        self.store.append(dict(kind=kind,request_sha256=digest(canonical(self.req)),at=int(time.time()),**value))

    def inspect(self,role,identifier):
        rows=decode(engine(self.owner,['inspect',identifier],allowed=(0,1))['stdout'] or b'[]')
        need(len(rows)==1,'db_only_owned_container_missing');value=rows[0]
        c.container(value,self.req,role,self.ids.get('runtime'),self.expires_at)
        if role in self.ids:need(value['Id']==self.ids[role],'db_only_owned_container_replaced')
        return value

    def create(self,role):
        self.record('create-intent',role=role,name=c.names(self.req)[role]);self.intents.add(role)
        r=engine(self.owner,c.create_args(self.req,role,self.ids.get('runtime'),self.expires_at),seconds=30)
        cid=r['stdout'].decode().strip();need(re.fullmatch('[a-f0-9]{64}',cid),'db_only_create_receipt')
        value=self.inspect(role,cid);self.ids[role]=cid;self.record('created',role=role,id=cid,identity=digest(canonical(value)))
        engine(self.owner,['start',cid],seconds=20)
        return cid

    def runtime(self,args,**kwargs):
        cid=self.ids['runtime'];self.inspect('runtime',cid)
        return engine(self.owner,['exec','-i',cid,'/usr/bin/python3','-I','-B',c.SOURCE+'/tests/support/WebsitePlatformM6EDBOnlyRuntime.py',*args],**kwargs)

    def evidence(self):
        """Copy only fixed-schema, sanitized component publications into the bounded retained file."""
        cid=self.ids.get('runtime')
        if cid is None:return
        self.inspect('runtime',cid)
        # The helper emits one bounded object at a time; no tar, arbitrary path or raw private state export.
        result=self.runtime(['--evidence-index'],limit=65536,allowed=(0,1))
        if result['exit']!=0:raise ValueError('db_only_evidence_unavailable')
        index=decode(result['stdout']);need(type(index) is list and len(index)<=40,'db_only_evidence_inventory')
        for name in index:
            need(re.fullmatch('[a-z-]{1,80}',name),'db_only_evidence_name')
            raw=self.runtime(['--evidence-read',name],limit=120000)['stdout']
            value=decode(raw);need(len(raw)<=120000,'db_only_evidence_bound')
            self.record('component-evidence',name=name,sha256=digest(raw),value=value)

    def cleanup(self):
        failures=[]
        for role in ('mysql','runtime'):
            if role not in self.intents:continue
            try:
                cid=self.ids.get(role)
                if cid is None:
                    # Resolve a lost create acknowledgement ONLY against the durable exact intent and complete config.
                    result=engine(self.owner,['inspect',c.names(self.req)[role]],allowed=(0,1))
                    if result['exit']!=0:
                        # Absence is verified by a successful exact-name inventory, never an arbitrary inspect error.
                        inventory=engine(self.owner,['ps','-a','--no-trunc','--filter','name=^/'+c.names(self.req)[role]+'$','--format','{{.ID}}'])
                        need(not inventory['stdout'].strip(),'db_only_uncertain_create')
                        self.record('removed',role=role,id=None,absent=True);continue
                    value=self.inspect(role,c.names(self.req)[role]);cid=value['Id'];self.ids[role]=cid
                    self.record('recovered-create',role=role,id=cid)
                result=engine(self.owner,['inspect',cid],allowed=(0,1))
                if result['exit']!=0:
                    remaining=engine(self.owner,['ps','-a','--no-trunc','--filter','id='+cid,'--format','{{.ID}}'])
                    need(not remaining['stdout'].strip(),'db_only_uncertain_removal')
                    self.record('removed',role=role,id=cid,absent=True);continue
                self.inspect(role,cid);self.record('remove-intent',role=role,id=cid)
                engine(self.owner,['rm','--force',cid],seconds=30)
                remaining=engine(self.owner,['ps','-a','--no-trunc','--filter','id='+cid,'--format','{{.ID}}'])
                need(not remaining['stdout'].strip(),'db_only_container_not_removed')
                self.record('removed',role=role,id=cid,absent=True)
            except BaseException as e:failures.append(dict(role=role,**safe_error(e)))
        if failures:
            self.record('cleanup-incomplete',failures=failures);raise ValueError('db_only_owned_cleanup_incomplete')


def execute(req):
    overall=time.monotonic()+c.LIMITS['seconds'];owner=Owner(overall-c.LIMITS['cleanup_seconds'])
    ready=host_readiness(owner,req);need(type(ready['images']) is dict,'db_only_engine_must_already_be_active')
    # A kernel per-file ceiling supplements the fixed-offset aggregate ceiling. No privileged quota setup.
    import resource
    resource.setrlimit(resource.RLIMIT_FSIZE,(c.LIMITS['retained'],c.LIMITS['retained']))
    path=Path(c.EVIDENCE)/('m6e-db-only-'+req['run_id']+'.evidence')
    store=c.Retained(path,create=True);op=Operator(req,store,owner);result='INCOMPLETE';expires=int(time.time()+max(0,overall-time.monotonic()));op.expires_at=expires
    try:
        op.record('admission',request=req,readiness=ready,expires_at=expires)
        runtime=op.create('runtime')
        measured=decode(op.runtime(['--measure'],limit=65536)['stdout']);op.record('runtime-measurement',value=measured)
        c.measurements(measured,'runtime',ready['host_namespaces'])
        tls=decode(op.runtime(['--tls'],seconds=60,limit=16384)['stdout'])
        need(set(tls)=={'ca.pem','server.pem','server-key.pem'},'db_only_tls_protocol')
        mysql=op.create('mysql');sqlmeasure=mysql_probe(owner,mysql);op.record('mysql-measurement',value=sqlmeasure)
        c.measurements(sqlmeasure,'mysql',ready['host_namespaces'])
        need(measured['namespaces']['net']==sqlmeasure['namespaces']['net'] and measured['namespaces']['pid']!=sqlmeasure['namespaces']['pid'],'db_only_shared_private_network')
        for name in ('ca.pem','server.pem','server-key.pem'):
            engine(owner,['exec','-i',mysql,'/bin/sh','-c','umask 077; set -C; cat > /tls/'+name],stdin=tls[name].encode(),limit=1024)
        engine(owner,['exec',mysql,'/bin/sh','-c','umask 077; set -C; : > /tls/ready'],limit=1024)
        # Wait for this exact new server's private socket, with an absolute deadline.
        deadline=min(owner.deadline,time.monotonic()+150)
        while True:
            r=engine(owner,['exec',mysql,'mysql','--no-defaults','--protocol=socket','--socket=/run/mysqld/mysql.sock','-uroot','-Nse','SELECT @@server_uuid'],seconds=5,limit=4096,allowed=(0,1))
            if r['exit']==0:break
            need(time.monotonic()<deadline,'db_only_mysql_start_timeout');time.sleep(.2)
        uuid=r['stdout'].decode().strip();need(re.fullmatch('[a-f0-9-]{36}',uuid),'db_only_server_uuid')
        import secrets
        password=secrets.token_hex(32)
        # The secret is sent on stdin only to the exact owned socket client. It never enters argv or evidence.
        sql="CREATE USER 'm6e_db_admin'@'127.0.0.1' IDENTIFIED BY '"+password+"' REQUIRE SSL; GRANT ALL PRIVILEGES ON *.* TO 'm6e_db_admin'@'127.0.0.1' WITH GRANT OPTION;\n"
        engine(owner,['exec','-i',mysql,'mysql','--no-defaults','--protocol=socket','--socket=/run/mysqld/mysql.sock','-uroot'],stdin=sql.encode(),limit=4096)
        op.record('database-identity',server_uuid=uuid,account='m6e_db_admin@127.0.0.1',tls_ca_sha256=digest(tls['ca.pem'].encode()))
        config=dict(request=req,expires_at=expires,server_uuid=uuid,admin=dict(username='m6e_db_admin',password=password),
            host_namespaces=ready['host_namespaces'],mysql_measurements=sqlmeasure)
        r=op.runtime(['--campaign'],stdin=canonical(config),seconds=max(1,owner.deadline-time.monotonic()),limit=65536,allowed=(0,1))
        v=decode(r['stdout']);need(v.get('profile')==c.PROFILE and v.get('result') in ('INCOMPLETE','DB-ONLY COMPONENT VALIDATION PASS','EXPECTED INJECTED FAILURE OBSERVED'),'db_only_campaign_protocol')
        result=v['result'];need((r['exit']==0)==(result!='INCOMPLETE'),'db_only_campaign_exit')
    except BaseException as e:
        op.first=dict(**safe_error(e));result='INCOMPLETE'
    finally:
        # Expiry is never renewed. Cleanup reserve is separately bounded and cannot run new cases.
        owner.deadline=min(overall,time.monotonic()+c.LIMITS['cleanup_seconds'])
        try:op.record('before-host-cleanup',result=result,first_failure=op.first)
        except BaseException as e:op.secondary.append(dict(kind='capture',**safe_error(e)));result='INCOMPLETE'
        try:op.evidence()
        except BaseException as e:op.secondary.append(dict(kind='component-capture',**safe_error(e)));result='INCOMPLETE'
        try:op.cleanup()
        except BaseException as e:op.secondary.append(dict(kind='cleanup',**safe_error(e)));result='INCOMPLETE'
        try:op.record('final',result=result,first_failure=op.first,secondary=op.secondary,engine_state='LEFT_ACTIVE_AS_OBSERVED',**c.AUTHORITY)
        finally:store.close();owner.cleanup()
    print(canonical(dict(profile=c.PROFILE,result=result,evidence=str(path),**c.AUTHORITY)).decode())
    return 0 if result!='INCOMPLETE' else 1


def status_or_cleanup(run_id,cleanup=False):
    need(re.fullmatch('[a-f0-9]{32}',run_id),'db_only_run_id')
    path=Path(c.EVIDENCE)/('m6e-db-only-'+run_id+'.evidence');store=c.Retained(path,readonly=not cleanup)
    try:
        records=store.records;need(records and records[0]['kind']=='admission','db_only_saved_admission');req=c.request(records[0]['request'])
        need(req['run_id']==run_id,'db_only_saved_run')
        if not cleanup:
            print(canonical(dict(profile=c.PROFILE,records=len(records),last=records[-1],evidence=str(path),**c.AUTHORITY)).decode());return 0
        owner=Owner(time.monotonic()+c.LIMITS['cleanup_seconds']);ready=host_readiness(owner,req,source=False)
        need(ready['engine']['ActiveState']=='active','db_only_cleanup_engine_inactive');op=Operator(req,store,owner);op.expires_at=records[0]['expires_at']
        for record in records:
            if record['kind']=='create-intent':op.intents.add(record['role'])
            if record['kind'] in ('created','recovered-create'):op.ids[record['role']]=record['id']
            if record['kind']=='removed':op.intents.discard(record['role'])
        # Capture before explicit recovery cleanup where the running runtime still owns evidence.
        if 'runtime' in op.intents:
            try:op.evidence()
            except BaseException as e:op.record('recovery-capture-incomplete',failure=safe_error(e))
        op.cleanup();op.record('recovery-cleanup-complete',**c.AUTHORITY);owner.cleanup();return 0
    finally:store.close()


def main(argv):
    if argv==['--help']:
        print('M6E DB-ONLY COMPONENT VALIDATION — native execution requires separate authorization.\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --inspect-host\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --inventory SOURCE_SHA\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --inspect /absolute/request.json\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --execute /absolute/request.json\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --status RUN_ID\n'
              'python3 -I -B tests/WebsitePlatformM6EDBOnly.py --cleanup RUN_ID\n'
              '--inspect writes nothing and never starts/activates Docker. No command pulls images or starts/stops the engine.');return 0
    if argv==['--inspect-host']:
        owner=Owner(time.monotonic()+30)
        try:print(canonical(host_readiness(owner,None,source=False)).decode());return 0
        finally:owner.cleanup()
    need(len(argv)==2,'db_only_arguments')
    if argv[0]=='--inventory':
        sha=argv[1];need(re.fullmatch('[a-f0-9]{40}',sha),'db_only_inventory_sha')
        need(Path(__file__).resolve().parents[1]==Path(c.EXPORTS)/sha,'db_only_inventory_export')
        owner=Owner(time.monotonic()+60)
        try:
            inventory=source_inventory(Path(c.EXPORTS)/sha,sha,owner)
            print(canonical(dict(source_sha=sha,source_inventory_sha256=digest(canonical(inventory)),files=len(inventory))).decode());return 0
        finally:owner.cleanup()
    if argv[0] in ('--status','--cleanup'):return status_or_cleanup(argv[1],argv[0]=='--cleanup')
    need(argv[0] in ('--inspect','--execute'),'db_only_mode');req=load_request(argv[1])
    if argv[0]=='--execute':return execute(req)
    owner=Owner(time.monotonic()+60)
    try:print(canonical(host_readiness(owner,req)).decode());return 0
    finally:owner.cleanup()


if __name__=='__main__':
    try:sys.exit(main(sys.argv[1:]))
    except Exception:
        print(canonical(dict(profile=c.PROFILE,result='INCOMPLETE',failure='db_only_operator',**c.AUTHORITY)).decode());sys.exit(1)
