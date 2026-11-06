"""Namespace-root supervisor for the separate DB-only lane. No full-host receipt is read or issued."""
import os
from pathlib import Path
import re
import secrets
import signal
import stat
import sys
import time

if __name__ == '__main__': sys.path.insert(0, str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, safe_error
from WebsitePlatformM6EProcess import Owner, publish_bytes
import WebsitePlatformM6EDBOnlyContract as c


def source_inventory(root, sha, owner):
    root = Path(root)
    # Minimal detached export metadata: no hooks, alternates, includes, remote credentials,
    # ignored application files or linkage back to a working checkout.
    expected_config=b'[core]\n\trepositoryformatversion = 0\n\tfilemode = true\n\tbare = false\n\tlogallrefupdates = false\n'
    git_files=[];ordinary=[]
    for p in root.rglob('*'):
        s=p.lstat();need(p.resolve()==p and (stat.S_ISDIR(s.st_mode) or stat.S_ISREG(s.st_mode)), 'db_only_export_entry')
        if not stat.S_ISREG(s.st_mode):continue
        name=p.relative_to(root).as_posix()
        if name.startswith('.git/'):
            need(re.fullmatch(r'\.git/(?:HEAD|config|index|shallow|objects/[a-f0-9]{2}/[a-f0-9]{38}|objects/pack/pack-[a-f0-9]{40}\.(?:pack|idx|rev))',name),'db_only_export_git_inventory')
            git_files.append(name)
        else:ordinary.append(name)
    need((root/'.git/config').stat().st_size<=4096 and (root/'.git/HEAD').stat().st_size==41
         and (root/'.git/config').read_bytes()==expected_config and (root/'.git/HEAD').read_bytes()==(sha+'\n').encode(),'db_only_export_git_config')
    git=['/usr/bin/env','GIT_CONFIG_NOSYSTEM=1','/usr/bin/git']
    for args, expected in ((['rev-parse', 'HEAD'], sha.encode()), (['status', '--porcelain', '--untracked-files=all'], b'')):
        v = owner.capture([*git, '--no-optional-locks', '-c', 'core.fsmonitor=false', '-c', 'safe.directory='+str(root), '-C', str(root), *args], 15)
        need(v['exit'] == 0 and not v['stderr'] and v['stdout'].strip() == expected, 'db_only_source_git')
    need(not (root/'private/config/env.php').exists(), 'db_only_no_application_config')
    v = owner.capture([*git, '--no-optional-locks', '-c', 'safe.directory='+str(root), '-C', str(root), 'ls-files', '-z'], 15)
    need(v['exit'] == 0 and not v['stderr'], 'db_only_source_inventory')
    names = v['stdout'].decode().split('\0'); need(names.pop() == '', 'db_only_source_names')
    need(sorted(ordinary)==sorted(names) and len(names)==len(set(names)), 'db_only_export_no_ignored_files')
    rows = {}
    for name in names:
        need(not Path(name).is_absolute() and '..' not in Path(name).parts, 'db_only_source_path')
        p = root/name; s = p.lstat()
        need(stat.S_ISREG(s.st_mode) and s.st_nlink == 1 and p.resolve() == p, 'db_only_source_regular')
        rows[name] = dict(sha256=digest(p.read_bytes()))
    return rows


class MeasurementFailure(ValueError):
    def __init__(self, diagnostic):
        super().__init__('db_only_measurement_failed')
        self.diagnostic=c.measurement_diagnostic(diagnostic)


def measure(role):
    """Same actual readings; preserve only completed, schema-checked observations."""
    values=dict.fromkeys(c.MEASUREMENT_FIELDS);phase,check='platform','linux'
    def remember(key,value):
        nonlocal values
        candidate={**values,key:value};c.measurement_values(candidate,partial=True);values=candidate
    try:
        need(sys.platform=='linux' and role=='runtime','db_only_linux')
        proc=Path('/proc/self');cg=Path('/sys/fs/cgroup')
        phase,check='process','identity'
        for key,read in [('uid',os.geteuid),('gid',os.getegid),('pid',os.getpid),('owner_pid',os.getppid)]:remember(key,read())
        check='status';status=dict(line.split(':',1) for line in (proc/'status').read_text().splitlines() if ':' in line)
        remember('no_new_privs',int(status['NoNewPrivs']));remember('capabilities',int(status['CapEff'].strip(),16))
        check='stat';remember('start_ticks',int((proc/'stat').read_text().rsplit(')',1)[1].split()[19]))
        for field in ('uid_map','gid_map'):
            check=field.replace('_','-');remember(field,[list(map(int,x.split())) for x in (proc/field).read_text().splitlines()])
        for check,field,name in [('memory','memory_max','memory.max'),('swap','swap_max','memory.swap.max'),('pids','pids_max','pids.max'),('cpu','cpu_max','cpu.max')]:
            phase='cgroup';raw=(cg/name).read_text();remember(field,list(map(int,raw.split())) if field=='cpu_max' else int(raw))
        for check in ('user','net','mnt','pid'):
            phase='namespace';ns=dict.fromkeys(('user','net','mnt','pid')) if values['namespaces'] is None else values['namespaces'].copy()
            ns[check]=os.readlink(proc/'ns'/check);remember('namespaces',ns)
        phase,check='mount','read';raw=(proc/'mountinfo').read_text()
        check='parse';mounts=[]
        for line in raw.splitlines():
            left,right=line.split(' - ',1);a=left.split();b=right.split()
            mounts.append(dict(path=a[4],type=b[0],options=a[5].split(',')+b[2].split(',')))
        for check,path in zip(('control','work','evidence','tmp'),c.mount_limits(role)):
            phase='filesystem';found=[m for m in mounts if m['path']==path];need(len(found)==1,'db_only_mount_measurement')
            m=found[0];sizes=[x[5:] for x in m['options'] if x.startswith('size=')];need(len(sizes)==1,'db_only_size_option')
            size=sizes[0];scale={'k':1024,'m':1024**2,'g':1024**3}
            size=int(size[:-1])*scale[size[-1].lower()] if size[-1].lower() in scale else int(size)
            fs=os.statvfs(path);s=os.stat(path)
            storage=dict.fromkeys(c.mount_limits(role)) if values['storage'] is None else values['storage'].copy()
            storage[path]=dict(type=m['type'],options=sorted(set(m['options'])),bytes=fs.f_blocks*fs.f_frsize,size_option=size,dev=s.st_dev,ino=s.st_ino)
            remember('storage',storage)
        phase,check='source','mount';source=[m for m in mounts if m['path']==c.SOURCE]
        remember('source_readonly',len(source)==1 and 'ro' in source[0]['options'])
        check='stat';s=Path(c.SOURCE).stat()
        remember('source_root',dict(dev=s.st_dev,ino=s.st_ino,uid=s.st_uid,gid=s.st_gid,mode=stat.S_IMODE(s.st_mode)))
        phase,check='interfaces','read';remember('interfaces',sorted(p.name for p in Path('/sys/class/net').iterdir()))
        return c.measurement_values(values)
    except Exception as error:
        raise MeasurementFailure(dict(profile=c.PROFILE+'-measurement-diagnostic-v1',result='DIAGNOSTIC_ONLY',
            phase=phase,check=check,error=c.diagnostic_error(error),measurements=values,**c.AUTHORITY)) from None


def file_identity(path):
    p = Path(path); s = p.lstat()
    need(stat.S_ISREG(s.st_mode) and s.st_nlink == 1 and p.resolve() == p, 'db_only_file_identity')
    return (s.st_dev, s.st_ino, s.st_uid, s.st_gid, stat.S_IMODE(s.st_mode), s.st_size)


def output_measurements(outputs):
    rows=[]
    for path,(identity,sha) in sorted(outputs.items()):
        if 'credential' in Path(path).stem:continue
        dev,ino,uid,gid,mode,size=identity
        rows.append(dict(name=Path(path).name,dev=dev,ino=ino,uid=uid,gid=gid,mode=mode,bytes=size,sha256=sha))
    need(len(rows)<=32,'db_only_output_inventory_bound');return rows


class Runtime:
    def __init__(self, config):
        self.req = c.request(config['request']); self.config = config
        self.root = Path(c.ROOT); self.outputs = {}; self.phase = 'admission'; self.first = None; self.secondary = []
        self.started = time.monotonic();self.overall=self.started+config['expires_at']-time.time()
        self.deadline = self.overall-c.LIMITS['cleanup_seconds']
        self.owner = Owner(self.deadline); self.receipts = {}; self.trace = []; self.commands = []
        measured = c.measurements(measure('runtime'), 'runtime', config['host_namespaces'])
        need(measured['namespaces']['net'] == config['mysql_measurements']['namespaces']['net'], 'db_only_real_loopback_namespace')
        need(os.geteuid() == os.getegid() == 0 and Path(__file__).resolve().parents[2] == Path(c.SOURCE), 'db_only_supervisor')
        need(time.time() < config['expires_at'] and config['expires_at']-time.time() <= c.LIMITS['seconds'], 'db_only_expiry')
        self.source = source_inventory(c.SOURCE, self.req['source_sha'], self.owner)
        need(digest(canonical(self.source)) == self.req['source_inventory_sha256'], 'db_only_source_digest')
        self.a = dict(profile=c.PROFILE, run_id=self.req['run_id'], source_sha=self.req['source_sha'],
            machine_id=Path('/etc/machine-id').read_text().strip(), boot_id=Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
            expires_at=config['expires_at'], server_uuid=config['server_uuid'], owner_pid=os.getpid(),
            namespaces=measured['namespaces'], case=self.req['case'])
        need(re.fullmatch('[a-f0-9]{32}', self.a['machine_id']) and re.fullmatch('[a-f0-9-]{36}', self.a['boot_id'])
             and re.fullmatch('[a-f0-9-]{36}', self.a['server_uuid']), 'db_only_actual_system_identity')
        for path, uid, mode in [('inputs',0,0o755),('outputs',0,0o755),('credentials',0,0o711),('barriers',0,0o755)]:
            p=self.root/path; p.mkdir(mode=mode); os.chown(p,uid,uid)
        for uid in (0,2000,2001,2107):
            for kind, mode in (('outputs',0o700),('credentials',0o750)):
                p=self.root/kind/str(uid);p.mkdir(mode=mode);os.chown(p,uid if kind=='outputs' else 0,uid)
        # Top-level private tmpfs is traversable only after this admitted namespace exists.
        os.chmod(self.root,0o711)
        for leaf in ('work','sealed','uploads'):
            p=Path('/work')/leaf;p.mkdir(mode=0o700);os.chown(p,2000,2000)
        os.chmod('/work',0o711)
        self.put('admission',self.a)
        self.credential('admin',config['admin'],[0])
        self.publish('admission',dict(profile=c.PROFILE, purpose='DB-ONLY COMPONENT VALIDATION', request=self.req,
            measured=measured, mysql=config['mysql_measurements'], server_uuid=self.a['server_uuid'],
            container_root='rootless namespace root, NOT host root', **c.AUTHORITY))

    def put(self, name, value):
        need(re.fullmatch('[a-z-]+',name),'db_only_input_name');p=self.root/'inputs'/(name+'.json')
        raw=canonical(value);need(len(raw)<=4194304,'db_only_input_bound')
        with p.open('xb') as f:f.write(raw);f.flush();os.fsync(f.fileno())
        os.chmod(p,0o444);return p

    def credential(self,role,value,uids):
        need(set(value)=={'username','password'} and re.fullmatch('[a-f0-9]{64}',value['password']), 'db_only_secret_shape')
        for uid in uids:
            p=self.root/'credentials'/str(uid)/('credential-'+role+'.json')
            with p.open('xb') as f:f.write(canonical(value));f.flush();os.fsync(f.fileno())
            os.chown(p,0,uid);os.chmod(p,0o640)

    def publish(self,name,value):
        need(name not in self.receipts,'db_only_duplicate_publication')
        receipt=publish_bytes('/evidence',name,canonical(value));self.receipts[name]=receipt
        return receipt

    def read_output(self,name,uid,promote=False,max_bytes=4194304):
        p=self.root/'outputs'/str(uid)/(name+'.json');before=file_identity(p)
        need(before[2:5]==(uid,uid,0o600) and before[5]<=max_bytes,'db_only_output_owner')
        fd=os.open(p,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_BINARY',0))
        try:
            s=os.fstat(fd);opened=(s.st_dev,s.st_ino,s.st_uid,s.st_gid,stat.S_IMODE(s.st_mode),s.st_size)
            need(opened==before and s.st_nlink==1,'db_only_output_opened_identity')
            with os.fdopen(fd,'rb',closefd=False) as f:raw=f.read(max_bytes+1)
            need(len(raw)==before[5] and file_identity(p)==before,'db_only_output_changed')
        finally:os.close(fd)
        prior=self.outputs.get(str(p));need(prior is None or prior==(before,digest(raw)), 'db_only_output_replaced')
        value=decode(raw);self.outputs[str(p)]=(before,digest(raw))
        if promote:self.put(name,value)
        return raw

    def start_snapshot(self):
        need(not hasattr(self,'snapshot_invocation'),'db_only_snapshot_single_use')
        self.snapshot_invocation=dict(profile='m6e-db-only-snapshot-invocation-v1',
            binding={k:self.a[k] for k in ('run_id','source_sha','machine_id','boot_id')},case=self.req['case'],mode='snapshot',
            invocation_id=secrets.token_hex(16),owner_pid=self.a['owner_pid'],namespaces=self.a['namespaces'],
            started_at=int(time.time()),expires_at=self.a['expires_at'])
        self.put('snapshot-invocation',self.snapshot_invocation)

    def snapshot_diagnostic(self,owner,start,result,error):
        from WebsitePlatformM6EAcceptance import validate_context
        process=None;file_record=None;protocol_record=None;issues=[];protocol_ok=False
        context=owner.context[start:];validate_context(dict(children=context,resources=[]))
        need(len(context)<=1,'db_only_snapshot_process_inventory')
        if context:process=context[0].copy()
        pid=None if process is None else process['pid']
        try:
            raw=self.read_output('snapshot-outcome',2001,max_bytes=8192)
            file_record=c.snapshot_record(raw,self.snapshot_invocation,pid)
        except BaseException as e:issues.append(dict(kind='outcome-capture',**safe_error(e)))
        try:
            need(result is not None and not result['stderr'] and result['pid']==pid,'db_only_snapshot_protocol_missing')
            value=decode(result['stdout'])
            if result['exit']==0:
                need(set(value)=={'result','value'} and value['result']=='COMPLETED' and file_record is not None
                     and value['value']==file_record['outcome'],'db_only_snapshot_success_protocol')
            else:
                need(set(value)=={'result','diagnostic'} and value['result']=='INCOMPLETE','db_only_snapshot_failure_protocol')
                diagnostic=value['diagnostic'];need(set(diagnostic)=={'phase','first_failure','secondary','snapshot'}
                     and diagnostic['phase']=='snapshot','db_only_snapshot_failure_fields')
                protocol_record=c.snapshot_record(canonical(diagnostic['snapshot']),self.snapshot_invocation,pid)
                need(diagnostic['first_failure']==protocol_record['first_failure'] and diagnostic['secondary']==protocol_record['secondary'],
                     'db_only_snapshot_failure_consistency')
            protocol_ok=True
        except BaseException as e:issues.append(dict(kind='outcome-protocol',**safe_error(e)))
        record=protocol_record or file_record
        if file_record and protocol_record:
            # Publication can fail after installing a file. Preserve both immutable observations.
            if file_record['outcome']!=protocol_record['outcome']:
                record=None;issues.append(dict(kind='outcome-substitution',type='ValueError',code='validation_failed'))
        self.snapshot_evidence=dict(profile='m6e-db-only-snapshot-diagnostic-v1',invocation=self.snapshot_invocation,
            process=process,command_error=None if error is None else safe_error(error),capture='AVAILABLE' if record else 'UNAVAILABLE',
            record=record,file_record=file_record,protocol_record=protocol_record,capture_errors=issues,result='DIAGNOSTIC_ONLY',**c.AUTHORITY)
        failed=error is not None or not protocol_ok or bool(issues) or result is None or result['exit']!=0 or record is None
        if record:
            failed=failed or record['first_failure'] is not None or bool(record['secondary']) or record['attempt']!='RETURNED' or record['outcome']!=dict(
                capture='CAPTURED',verification='UNREACHED',transaction_cleanup='RELEASED',first_failure=None,secondary=[])
        if failed:
            failure=dict(phase='snapshot',kind='snapshot',code=record['first_failure'] if record and record['first_failure'] else 'db_only_snapshot_unconfirmed')
            if self.first is None:self.first=failure
            else:self.secondary.append(failure)
            self.secondary.extend(issues)
        # Always retain the sanitized value itself; no pointer into subsequently removed tmpfs.
        try:self.publish('snapshot-diagnostic',self.snapshot_evidence)
        except BaseException as e:self.failure(e,'snapshot-diagnostic-publication');raise
        return failed

    def command(self,mode,seconds=60,allowed=(0,),limit=None):
        if mode!='snapshot':return self._command(mode,seconds,allowed,limit)
        self.start_snapshot();self.snapshot_result=None;start=len(self.owner.context);primary=None
        try:return self._command(mode,seconds,allowed,limit)
        except BaseException as e:primary=e;raise
        finally:
            try:failed=self.snapshot_diagnostic(self.owner,start,self.snapshot_result,primary)
            except BaseException as e:
                if primary is None:raise
                self.failure(e,'snapshot-diagnostic')
            else:
                if primary is None:need(not failed,'db_only_snapshot_failed')

    def _command(self,mode,seconds=60,allowed=(0,),limit=None):
        need(mode in CHILD_ROLES,'db_only_child_mode');uid=CHILD_ROLES[mode]
        self.trace.append(mode);self.phase=mode
        result=self.owner.capture(['/usr/bin/php','-d','memory_limit=256M','-d','display_errors=0','-d','log_errors=0',
            c.SOURCE+'/tests/support/WebsitePlatformM6EDBOnlyChild.php',mode],seconds,
            limit=limit or 65536,identity=(uid,uid,[]))
        if mode=='snapshot':self.snapshot_result=result
        record=dict(mode=mode,pid=result['pid'],exit=result['exit']);self.commands.append(record)
        need(not result['stderr'],'db_only_child_stderr');v=decode(result['stdout'])
        if result['exit']==0:need(set(v)=={'result','value'} and v['result']=='COMPLETED','db_only_child_protocol')
        else:
            need(set(v)=={'result','diagnostic'} and v['result']=='INCOMPLETE','db_only_child_protocol')
            if mode!='snapshot':record['diagnostic']=v['diagnostic']
        need(result['exit'] in allowed,'db_only_child_failed')
        return v,result['pid'],result['exit']

    def value(self,mode,seconds=60):
        return self.command(mode,seconds)[0]['value']

    def failure(self,error,kind):
        v=dict(phase=self.phase,kind=kind,**safe_error(error))
        if self.first is None:self.first=v
        else:self.secondary.append(v)

    def verify_outputs(self):
        for path,(identity,sha) in self.outputs.items():
            need(file_identity(path)==identity and digest(Path(path).read_bytes())==sha,'db_only_retained_output_changed')


CHILD_ROLES = dict(schema=0,checkpoint=2000,snapshot=2001,source=0,restore=2107,eligibility=2107,before=0,after=0,mutate=0,waiter=0,interrupt=0,
    **{'build-a':2000,'build-b':2000,'restore-create':0,'restore-seal':0,'restore-observe':0,
       'eligibility-create':0,'eligibility-seal':0,'identity-grants':0,'denied-dml':0,'competing-writer':2000})


def tls(owner):
    """Synthetic private CA and IP SAN certificate, only in the bounded private tmpfs."""
    root=Path(c.ROOT)/'tls';root.mkdir(mode=0o755)
    commands=[['req','-x509','-newkey','rsa:2048','-nodes','-keyout','ca-key.pem','-out','ca.pem','-days','1','-subj','/CN=M6E DB-only CA'],
        ['req','-newkey','rsa:2048','-nodes','-keyout','server-key.pem','-out','server.csr','-subj','/CN=127.0.0.1'],
        ['x509','-req','-in','server.csr','-CA','ca.pem','-CAkey','ca-key.pem','-set_serial','1','-out','server.pem','-days','1','-extfile','extensions']]
    (root/'extensions').write_text('subjectAltName=IP:127.0.0.1\nextendedKeyUsage=serverAuth\n')
    old=os.getcwd()
    try:
        os.chdir(root)
        for argv in commands:
            result=owner.capture(['/usr/bin/openssl',*argv],15,limit=16384,error_limit=16384)
            need(result['exit']==0,'db_only_tls_generation')
        for name in ('ca-key.pem','server-key.pem'):os.chmod(root/name,0o600)
        for name in ('ca.pem','server.pem'):os.chmod(root/name,0o444)
    finally:os.chdir(old)
    return {name:(root/name).read_text() for name in ('ca.pem','server.pem','server-key.pem')}


def main(argv):
    if len(argv)==2 and argv[0]=='--hold':
        remaining=int(argv[1])-time.time()
        need(os.geteuid()==0 and 0<remaining<=c.LIMITS['seconds'],'db_only_holder_uid_expiry');time.sleep(remaining);return 0
    if argv==['--measure']:
        print(canonical(measure('runtime')).decode());return 0
    if argv==['--tls']:
        print(canonical(tls(Owner(time.monotonic()+60))).decode());return 0
    if argv==['--evidence-index']:
        need(os.geteuid()==0,'db_only_evidence_reader')
        names=sorted(p.stem for p in Path('/evidence').glob('*.json'))
        need(len(names)<=40 and all(re.fullmatch('[a-z-]{1,80}',n) for n in names),'db_only_evidence_inventory')
        print(canonical(names).decode());return 0
    if len(argv)==2 and argv[0]=='--evidence-read':
        need(os.geteuid()==0 and re.fullmatch('[a-z-]{1,80}',argv[1]),'db_only_evidence_reader')
        p=Path('/evidence')/(argv[1]+'.json');identity=file_identity(p)
        need(identity[2:5]==(0,0,0o600) and identity[5]<=120000,'db_only_evidence_file')
        raw=p.read_bytes();need(file_identity(p)==identity,'db_only_evidence_replaced');decode(raw)
        sys.stdout.buffer.write(raw);return 0
    need(argv==['--campaign'],'db_only_runtime_arguments')
    config=decode(sys.stdin.buffer.read(65537));need(len(canonical(config))<=65536,'db_only_runtime_config')
    from WebsitePlatformM6EDBOnlyCampaign import campaign
    return campaign(config)


def entrypoint(argv):
    """Import/interpreter failures precede this boundary; operator facts cover them."""
    try:return main(argv)
    except MeasurementFailure as error:
        print(canonical(error.diagnostic).decode());return 1
    except Exception as error:
        if argv==['--measure']:
            value=c.measurement_diagnostic(dict(profile=c.PROFILE+'-measurement-diagnostic-v1',result='DIAGNOSTIC_ONLY',
                phase='entrypoint',check='measure',error=c.diagnostic_error(error),measurements=dict.fromkeys(c.MEASUREMENT_FIELDS),**c.AUTHORITY))
        else:
            # The finite phase is selected here, never from an exception message or argv body.
            phase=next((name for name in ('hold','tls','campaign','evidence-index','evidence-read') if argv and argv[0]=='--'+name),'arguments')
            value=dict(profile=c.PROFILE,result='INCOMPLETE',failure='db_only_runtime',
                diagnostic=dict(result='DIAGNOSTIC_ONLY',phase=phase,error=c.diagnostic_error(error)),**c.AUTHORITY)
        print(canonical(value).decode());return 1


if __name__=='__main__':sys.exit(entrypoint(sys.argv[1:]))
