"""AUTHORED ONLY. Fixed disposable-VM preparation; no import-time operations.

The VM image must already contain the pinned tools. This is not an image builder,
package installer, host provisioner or production admission override.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))

from WebsitePlatformM6EAcceptance import ROLES, ENDPOINTS, canonical, digest, need, keys, hash_
from WebsitePlatformM6EProcess import Owner, Failure, publish_bytes

SOURCE=Path('/opt/ubo')
CHECKOUT=Path('/opt/ubo-m6e')
CONTROL=Path('/run/ubo-m6e-fixture')
STORAGE=Path('/var/lib/ubo-m6e-fixture')
CONFIG=Path('/etc/ubo-site-publisher')
BOOTSTRAP=Path('/run/ubo-m6e-bootstrap.json')
BINARIES={'php':'/usr/bin/php','python':'/usr/bin/python3','apache':'/usr/sbin/apache2','mysql':'/usr/sbin/mysqld',
          'openssl':'/usr/bin/openssl','systemd':'/usr/bin/systemctl','git':'/usr/bin/git'}
VERSIONS={'php':['-v'],'python':['--version'],'apache':['-v'],'mysql':['--version'],
          'openssl':['version'],'systemd':['--version'],'git':['--version']}
ROOTS=(str(CONTROL),str(STORAGE),str(CONFIG),str(CHECKOUT),'/var/www/ubo-sites','/var/lib/ubo-site-publisher',
       '/usr/local/lib/ubo-site-publisher','/run/ubo-site-publisher')
UNITS=('ubo-m6e-mysql.service','ubo-m6e-apache.service','ubo-site-authority@.service','ubo-site-authority.socket',
       'ubo-site-publisher@.service','ubo-site-publisher.socket','ubo-site-worker.service','ubo-m6e-operator.service')

def protected(path,limit=65536):
    path=Path(path)
    for item in (path,*path.parents):
        s=item.lstat()
        need(s.st_uid==0 and not s.st_mode&0o022 and not stat.S_ISLNK(s.st_mode),'protected_ancestor')
    s=path.lstat(); need(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_size<=limit,'protected_file')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW)
    try:
        before=os.fstat(fd); raw=os.read(fd,limit+1); after=os.fstat(fd)
        stable=lambda x:(x.st_dev,x.st_ino,x.st_mode,x.st_uid,x.st_gid,x.st_nlink,x.st_size,x.st_mtime_ns,x.st_ctime_ns)
        need((before.st_dev,before.st_ino)==(s.st_dev,s.st_ino) and stable(before)==stable(after) and len(raw)==s.st_size,'protected_identity')
        return raw
    finally: os.close(fd)

def admit_facts(config,facts):
    """Pure preflight policy also used by desktop control tests; no boolean attestations."""
    keys(config,('version','run_id','source_sha','machine_id','boot_id','tool_sha256','expires_at'))
    need(config['version']==1,'bootstrap_version'); hash_(config['run_id'],32); hash_(config['source_sha'],40)
    hash_(config['machine_id'],32)
    need(re.fullmatch('[a-f0-9-]{36}',config['boot_id']) is not None,'boot_id')
    keys(config['tool_sha256'],BINARIES)
    for value in config['tool_sha256'].values(): hash_(value)
    need(facts['platform']=='linux' and facts['uid']==0 and facts['container']=='none','vm_root_only')
    need(facts['virtualization'] in ('kvm','qemu','vmware','microsoft','oracle','xen'),'dedicated_vm')
    need(facts['hostname']=='ubo-m6e-'+config['run_id'][:16] and facts['machine_id']==config['machine_id']
         and facts['boot_id']==config['boot_id'] and ('ubo.m6e='+config['run_id']) in facts['cmdline'].split(),'vm_identity')
    need(facts['interfaces']==['lo'] and facts['routes']==[] and facts['pid1']=='systemd','private_network_supervisor')
    need(facts['filesystems'] and all(x in ('ext4','xfs','btrfs') for x in facts['filesystems']),'local_filesystem')
    need(facts['source_sha']==config['source_sha'] and facts['source_status']=='' and facts['source_readonly'],'source')
    need(facts['tools']==config['tool_sha256'] and facts['now']<config['expires_at']<=facts['now']+3600,'tools_expiry')
    need(not facts['docker_socket'] and facts['existing_roots']==[] and facts['existing_units']==[],'foreign_resources')
    return config

def read_preflight(owner):
    need(sys.platform=='linux' and os.geteuid()==0,'linux_vm_required')
    config=json.loads(protected(BOOTSTRAP))
    def run(cmd,allowed=(0,)):
        r=owner.capture(cmd,10,limit=65536)
        need(r['exit'] in allowed and not r['stderr'],'preflight_command')
        return r['stdout'].decode().strip()
    mounts=Path('/proc/self/mountinfo').read_text().splitlines()
    def mount(path):
        choices=[line.split() for line in mounts if path==line.split()[4] or path.startswith(line.split()[4].rstrip('/')+'/')]
        need(choices,'mount'); row=max(choices,key=lambda x:len(x[4])); return row[row.index('-')+1],row[5].split(',')
    import socket
    facts=dict(platform=sys.platform,uid=os.geteuid(),container=run(['/usr/bin/systemd-detect-virt','--container'],(0,1)),
        virtualization=run(['/usr/bin/systemd-detect-virt','--vm'],(0,1)),hostname=socket.gethostname(),
        machine_id=Path('/etc/machine-id').read_text().strip(),boot_id=Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
        cmdline=Path('/proc/cmdline').read_text(),interfaces=sorted(p.name for p in Path('/sys/class/net').iterdir()),
        routes=[line for line in Path('/proc/net/route').read_text().splitlines()[1:] if line.strip()],
        pid1=Path('/proc/1/comm').read_text().strip(),filesystems=[mount(x)[0] for x in ('/var/lib','/var/www')],
        source_sha=run(['/usr/bin/git','-C',str(SOURCE),'rev-parse','HEAD']),
        source_status=run(['/usr/bin/git','-C',str(SOURCE),'status','--porcelain','--untracked-files=all']),
        source_readonly='ro' in mount(str(SOURCE))[1],
        tools={key:digest(protected(Path(path).resolve(strict=True),128*1024*1024)) for key,path in BINARIES.items()},now=int(time.time()),
        docker_socket=Path('/var/run/docker.sock').exists(),existing_roots=[x for x in ROOTS if os.path.lexists(x)],
        existing_units=[str(Path(root)/x) for root in ('/run/systemd/system','/etc/systemd/system','/usr/lib/systemd/system','/lib/systemd/system')
                        for x in UNITS if os.path.lexists(Path(root)/x) or os.path.lexists(Path(root)/(x+'.d'))])
    admit_facts(config,facts)
    versions={key:dict(sha256=facts['tools'][key],version=run([path,*VERSIONS[key]])[:256]) for key,path in BINARIES.items()}
    need('8.4.' in versions['mysql']['version'],'mysql_84')
    return config,versions

class Ledger:
    def __init__(self,owner): self.owner=owner; self.entries=[]; self.accounts=[]; self.units=[]
    def intent(self,path,kind):
        path=Path(path)
        need(any(str(path)==r or str(path).startswith(r+'/') for r in ROOTS) or (path.parent==Path('/run/systemd/system') and path.name in UNITS)
             or str(path)=='/usr/local/sbin/ubo-site-publish','fixed_ledger_path')
        need(not os.path.lexists(path),'preexisting_name')
        row=dict(path=str(path),kind=kind,identity=None,state='INTENT'); self.entries.append(row); return row
    def identify(self,row):
        s=Path(row['path']).lstat(); row.update(identity=[s.st_dev,s.st_ino,s.st_uid,stat.S_IFMT(s.st_mode)],state='CREATED')
    def mkdir(self,path,uid=0,gid=0,mode=0o755):
        row=self.intent(path,'directory'); os.mkdir(path,mode); self.identify(row)
        os.chmod(path,mode); os.chown(path,uid,gid); self.identify(row)
    def write(self,path,data,uid=0,gid=0,mode=0o644):
        row=self.intent(path,'file'); fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
        self.identify(row)
        try:
            view=memoryview(data)
            while view:
                n=os.write(fd,view[:65536]); need(n>0,'write'); view=view[n:]
            os.fchmod(fd,mode); os.fchown(fd,uid,gid); os.fsync(fd)
        finally: os.close(fd)
        self.identify(row)
    def command(self,cmd,seconds=30,stdin=b'',identity=None):
        r=self.owner.capture(cmd,seconds,stdin=stdin,identity=identity)
        if r['exit']!=0: raise Failure('preparation_command',{'pid':r['pid'],'exit':r['exit']})
        return r
    def unit(self,name,raw):
        need(name in UNITS,'unit_name'); self.units.append(name)  # Track before install/start can partly succeed.
        self.write('/run/systemd/system/'+name,raw)
    def checkpoint(self):
        # Root-only raw ledger stays in the VM; public reports contain identity hashes, not secrets.
        raw=canonical(dict(entries=self.entries,accounts=self.accounts,units=self.units))
        path=CONTROL/'ownership.json'
        if not path.exists(): self.write(path,raw,mode=0o600)
        else:
            fd=os.open(path,os.O_WRONLY|os.O_TRUNC|os.O_NOFOLLOW)
            try:
                view=memoryview(raw)
                while view:
                    n=os.write(fd,view); need(n>0,'ledger_write'); view=view[n:]
                os.fsync(fd)
            finally: os.close(fd)

def render(text,values):
    names=set(re.findall(r'@([A-Z0-9_]+)@',text))
    need(names<=set(values),'template_values')
    for name in names:
        value=values[name]; need(type(value) is str and '\n' not in value and '\r' not in value and '"' not in value,'template_value')
        text=text.replace('@'+name+'@',value)
    need(not re.search(r'@[A-Z0-9_]+@',text),'template_unresolved'); return text.encode()

def prepare():
    # Do not construct half a VM fixture and leave the operator an unwritten
    # commissioning step. Desktop controls test this barrier without running it.
    from WebsitePlatformM6ECoordinator import require_native_ready
    require_native_ready()
    owner=Owner(time.monotonic()+600); config,versions=read_preflight(owner); ledger=Ledger(owner); primary=None
    try:
        for root in ROOTS:
            if root!=str(CHECKOUT): ledger.mkdir(root)
        for name,uid in ROLES.items():
            if name=='operator': continue
            account='m6e-'+name
            # Never adopt an existing account or UID. getent nonzero is the required baseline.
            for selector in (account,str(uid)):
                need(owner.capture(['/usr/bin/getent','passwd',selector],5)['exit']==2,'existing_uid')
                need(owner.capture(['/usr/bin/getent','group',selector],5)['exit']==2,'existing_gid')
            ledger.accounts.append(dict(name=account,uid=uid))
            ledger.command(['/usr/sbin/groupadd','--gid',str(uid),account])
            ledger.command(['/usr/sbin/useradd','--uid',str(uid),'--gid',str(uid),'--no-create-home','--home-dir','/nonexistent','--shell','/usr/sbin/nologin',account])
        ledger.checkpoint()
        ledger.mkdir(CHECKOUT)
        ledger.command(['/usr/bin/git','clone','--local','--no-hardlinks','--no-checkout',str(SOURCE),str(CHECKOUT)])
        ledger.command(['/usr/bin/git','-C',str(CHECKOUT),'checkout','--detach',config['source_sha']])
        for directory,dirs,files in os.walk(CHECKOUT):
            os.chmod(directory,0o755)
            for name in files:
                path=Path(directory)/name; need(not path.is_symlink(),'checkout_link'); os.chmod(path,0o644)
        for name in ('work','sealed','uploads'):
            ledger.mkdir(STORAGE/name,ROLES['worker'],ROLES['worker'],0o700)
        for name in ('mysql','mysql-run'):
            ledger.mkdir(STORAGE/name,ROLES['mysql'],ROLES['mysql'],0o700 if name=='mysql' else 0o755)
        for name in ('reports','state','component-source','component-served','component-journal'):
            ledger.mkdir(CONTROL/name,ROLES['coordinator'],ROLES['coordinator'],0o700)
        for role in ('worker','writer'):
            ledger.mkdir(CONTROL/role,ROLES[role],ROLES[role],0o700)
        ledger.mkdir(CONFIG/'tls',mode=0o755)
        # Keys never enter argv/output. Fixed private CA and SANs; no DNS/ACME/provider calls.
        openssl='/usr/bin/openssl'
        for label in ('ca','server','client','wrong'):
            ledger.intent(CONFIG/'tls'/f'{label}.key','file')
            ledger.command([openssl,'genpkey','-algorithm','RSA','-pkeyopt','rsa_keygen_bits:2048','-out',str(CONFIG/'tls'/f'{label}.key')])
            ledger.identify(ledger.entries[-1]); os.chmod(CONFIG/'tls'/f'{label}.key',0o600)
        ledger.command([openssl,'req','-new','-x509','-key',str(CONFIG/'tls/ca.key'),'-out',str(CONFIG/'tls/ca.pem'),'-days','1','-subj','/CN=M6E disposable CA'])
        # The containing directory was recorded before OpenSSL can create any output; teardown checks every descendant.
        for label in ('server','client','wrong'):
            ledger.command([openssl,'req','-new','-key',str(CONFIG/'tls'/f'{label}.key'),'-out',str(CONFIG/'tls'/f'{label}.csr'),'-subj','/CN=m6e-'+label])
            ext=b'subjectAltName=DNS:active.m6e.invalid,DNS:candidate.m6e.invalid,IP:127.0.0.1,IP:127.0.0.2,IP:127.0.0.3\nextendedKeyUsage=serverAuth\n' if label=='server' else b'extendedKeyUsage=clientAuth\n'
            ledger.write(CONFIG/'tls'/f'{label}.ext',ext)
            args=[openssl,'x509','-req','-in',str(CONFIG/'tls'/f'{label}.csr'),'-out',str(CONFIG/'tls'/f'{label}.pem'),'-days','1','-extfile',str(CONFIG/'tls'/f'{label}.ext')]
            args+=['-signkey',str(CONFIG/'tls/wrong.key')] if label=='wrong' else ['-CA',str(CONFIG/'tls/ca.pem'),'-CAkey',str(CONFIG/'tls/ca.key'),'-set_serial','2' if label=='server' else '3']
            ledger.command(args)
        os.chown(CONFIG/'tls/client.key',0,ROLES['worker']); os.chmod(CONFIG/'tls/client.key',0o640)
        os.chown(CONFIG/'tls/wrong.key',0,ROLES['worker']); os.chmod(CONFIG/'tls/wrong.key',0o640)
        os.chown(CONFIG/'tls/server.key',0,ROLES['mysql']); os.chmod(CONFIG/'tls/server.key',0o640)
        ledger.write(CONTROL/'bootstrap.json',canonical(config|{'tools':versions}),mode=0o644)
        mysql_conf=('''[mysqld]
user=m6e-mysql
datadir=/var/lib/ubo-m6e-fixture/mysql
socket=/var/lib/ubo-m6e-fixture/mysql-run/mysql.sock
pid-file=/var/lib/ubo-m6e-fixture/mysql-run/mysql.pid
bind-address=127.0.0.1
port=3306
mysqlx=0
skip-name-resolve
ssl-ca=/etc/ubo-site-publisher/tls/ca.pem
ssl-cert=/etc/ubo-site-publisher/tls/server.pem
ssl-key=/etc/ubo-site-publisher/tls/server.key
require-secure-transport=ON
log-error=/var/lib/ubo-m6e-fixture/mysql-run/error.log
max-connections=24
performance-schema=ON
innodb-buffer-pool-size=64M
''').encode()
        ledger.write(CONFIG/'mysql.cnf',mysql_conf)
        ledger.command(['/usr/sbin/mysqld','--defaults-file='+str(CONFIG/'mysql.cnf'),'--initialize-insecure'],90)
        ledger.unit('ubo-m6e-mysql.service',b'''[Service]
Type=simple
User=m6e-mysql
Group=m6e-mysql
ExecStart=/usr/sbin/mysqld --defaults-file=/etc/ubo-site-publisher/mysql.cnf
TimeoutStartSec=30
TimeoutStopSec=10
RuntimeMaxSec=1200
MemoryMax=512M
TasksMax=64
NoNewPrivileges=yes
''')
        ledger.command(['/usr/bin/systemctl','daemon-reload'])
        ledger.command(['/usr/bin/systemctl','start','ubo-m6e-mysql.service'])
        # A bounded readiness wait only; no failed acceptance case is retried.
        deadline=time.monotonic()+20
        while not (STORAGE/'mysql-run/mysql.sock').exists():
            need(time.monotonic()<deadline,'mysql_start'); time.sleep(.05)
        ledger.command(['/usr/bin/php','-d','display_errors=0',str(CHECKOUT/'tests/support/WebsitePlatformM6ENativeSetup.php'),'--schema'],90)
        ledger.checkpoint()
        print(json.dumps({'result':'PREPARED','run_id':config['run_id'],'next':'commission'}))
        return 0
    except BaseException as error:
        primary=error
        # Capture before any storage destruction. A failed prepare never starts commissioning.
        if (CONTROL/'reports').exists():
            try: publish_bytes(CONTROL/'reports','prepare-failure',canonical(dict(result='FAILED',phase='prepare',code=type(error).__name__,children=owner.context)))
            except BaseException: pass
        raise
    finally:
        try: owner.cleanup()
        except BaseException:
            if primary is None: raise
        if CONTROL.exists():
            try:ledger.checkpoint()
            except BaseException:
                if primary is None:raise
        if primary is not None:
            try:teardown_ledger(config,ledger,owner)
            except BaseException:print('{"secondary":"preparation_cleanup_unresolved"}',file=sys.stderr)

def teardown_ledger(config,ledger,owner):
    """Only this boot's recorded roots/accounts/units. Refuse replacement or foreign contents."""
    need(sys.platform=='linux' and os.geteuid()==0 and Path('/etc/machine-id').read_text().strip()==config['machine_id']
         and Path('/proc/sys/kernel/random/boot_id').read_text().strip()==config['boot_id']
         and 'ubo.m6e='+config['run_id'] in Path('/proc/cmdline').read_text().split(),'teardown_namespace')
    # Capture original report bytes before any private storage is removed. Export stays inside the VM.
    export=Path('/var/lib/ubo-m6e-evidence')
    if not export.exists():os.mkdir(export,0o700)
    s=export.lstat();need(stat.S_ISDIR(s.st_mode) and s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o700,'export_root')
    destination=export/config['run_id'];os.mkdir(destination,0o700)
    captures=[]
    if (CONTROL/'reports').exists():
        for path in sorted((CONTROL/'reports').iterdir()):
            s=path.lstat();need(stat.S_ISREG(s.st_mode) and s.st_uid in ROLES.values() and s.st_nlink==1 and s.st_size<=4*1024*1024,'report_capture')
            captures.append(publish_bytes(destination,'capture-'+str(len(captures)),path.read_bytes()))
    first=None;cleanup=[]
    for unit in reversed(ledger.units):
        try:
            path=Path('/run/systemd/system')/unit
            if path.exists():
                row=next(r for r in ledger.entries if r['path']==str(path));s=path.lstat()
                need(row['identity'] is not None and [s.st_dev,s.st_ino,s.st_uid,stat.S_IFMT(s.st_mode)]==row['identity'],'owned_unit')
                instances=[unit]
                if '@.' in unit:
                    query=owner.capture(['/usr/bin/systemctl','list-units','--all','--no-legend','--plain',unit.replace('@.','@*.')],10,limit=16384)
                    need(query['exit']==0,'unit_instances')
                    instances=[line.split()[0] for line in query['stdout'].decode().splitlines() if line.split()]
                    prefix=unit.split('@')[0]+'@'
                    need(len(instances)<=64 and all(re.fullmatch(re.escape(prefix)+r'[A-Za-z0-9_.:@\\-]+\.service',x) for x in instances),'owned_instance_names')
                for instance in instances:
                    result=owner.capture(['/usr/bin/systemctl','stop',instance],15,limit=65536)
                    need(result['exit']==0,'unit_stop')
                path.unlink()
            cleanup.append(dict(resource=unit,result='PASS'))
        except BaseException as error:
            first=first or error;cleanup.append(dict(resource=unit,result='FAILED'))
    owner.cleanup()
    if first is None:
        launcher=Path('/usr/local/sbin/ubo-site-publish')
        row=next((r for r in ledger.entries if r['path']==str(launcher)),None)
        if row is not None and os.path.lexists(launcher):
            s=launcher.lstat()
            need(row['identity']==[s.st_dev,s.st_ino,s.st_uid,stat.S_IFMT(s.st_mode)] and stat.S_ISREG(s.st_mode),'owned_launcher')
            launcher.unlink();row['state']='RELEASED'
    if first is None:
        for root in reversed(ROOTS):
            path=Path(root)
            row=next((r for r in ledger.entries if r['path']==root),None)
            if row is None or not os.path.lexists(path):continue
            try:
                s=path.lstat();need(row['identity'] is not None and [s.st_dev,s.st_ino,s.st_uid,stat.S_IFMT(s.st_mode)]==row['identity'],'owned_root')
                need(str(path.resolve(strict=True))==root and stat.S_ISDIR(s.st_mode),'root_containment')
                inventory=[]
                for directory,dirs,files in os.walk(path,topdown=False,followlinks=False):
                    for name in files+dirs:
                        child=Path(directory)/name;cs=child.lstat()
                        need(cs.st_uid in ROLES.values() and cs.st_dev==s.st_dev,'foreign_cleanup_entry')
                        inventory.append((child,cs))
                for child,before in inventory:
                    now=child.lstat();need((now.st_dev,now.st_ino,now.st_uid,stat.S_IFMT(now.st_mode))==
                        (before.st_dev,before.st_ino,before.st_uid,stat.S_IFMT(before.st_mode)),'cleanup_replaced')
                    child.rmdir() if stat.S_ISDIR(now.st_mode) else child.unlink()
                path.rmdir();row['state']='RELEASED';cleanup.append(dict(resource=root,result='PASS'))
            except BaseException as error:first=first or error;cleanup.append(dict(resource=root,result='FAILED'));break
    if first is None:
        import pwd,grp
        for account in reversed(ledger.accounts):
            name=account['name'];uid=account['uid']
            try:user=pwd.getpwnam(name)
            except KeyError:user=None
            if user is not None:
                need(user.pw_uid==uid and user.pw_gid==uid and user.pw_dir=='/nonexistent','owned_account')
                need(owner.capture(['/usr/sbin/userdel',name],5)['exit']==0,'account_cleanup')
            try:group=grp.getgrnam(name)
            except KeyError:group=None
            if group is not None:
                need(group.gr_gid==uid,'owned_group');need(owner.capture(['/usr/sbin/groupdel',name],5)['exit']==0,'group_cleanup')
    result=dict(result='PASS' if first is None else 'FAILED',run_id=config['run_id'],captures=captures,resources=cleanup,
                first_failure=None if first is None else type(first).__name__)
    publish_bytes(destination,'teardown',canonical(result))
    if first is not None:raise Failure('namespace_teardown')
    return result

def main(argv):
    need(argv in (['--prepare'],['--teardown']),'usage')
    if argv==['--prepare']:return prepare()
    owner=Owner(time.monotonic()+60);config=json.loads(protected(CONTROL/'bootstrap.json'))
    raw=json.loads(protected(CONTROL/'ownership.json',1048576));ledger=Ledger(owner)
    ledger.entries,ledger.accounts,ledger.units=raw['entries'],raw['accounts'],raw['units']
    teardown_ledger(config,ledger,owner);return 0

if __name__=='__main__':
    try: raise SystemExit(main(sys.argv[1:]))
    except BaseException as error:
        if isinstance(error,SystemExit): raise
        print(json.dumps({'result':'BLOCKED','code':type(error).__name__})); raise SystemExit(1)
