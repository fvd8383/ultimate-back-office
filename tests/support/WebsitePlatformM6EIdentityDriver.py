"""AUTHORED / NOT EXECUTED: four preprovisioned unprivileged Linux fixture agents.

No identity switching, installation, credentials, SQL connection or service control.
The authority agent runs actual PHP observer logic over an independently protected
PDO fixture snapshot. This cannot complete installed DB/HTTP/service acceptance.
"""
import errno
import importlib.util
import os
from pathlib import Path
import select
import socket
import stat
import struct
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('identity_publisher', ROOT/'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)
ADMISSION = '/run/ubo-m6e-identity/admission.json'
ROLES = ('worker', 'helper', 'authority', 'apache')


def admission():
    p.require(sys.platform == 'linux' and os.geteuid() != 0)
    c = p.decode(p.protected(ADMISSION, 65536))
    p.keys(c, 'profile source_sha expires_at coordinator_uid identities fixture_root bindings probes installed_files')
    p.require(c['profile'] == 'm6e-transfer-identities-v1' and time.time() < c['expires_at'] <= time.time()+600)
    p.keys(c['identities'], 'worker helper authority apache')
    p.require(len(set(c['identities'].values()) | {c['coordinator_uid']}) == 5)
    for uid in list(c['identities'].values())+[c['coordinator_uid']]: p.positive(uid)
    p.require(c['fixture_root'].startswith('/srv/ubo-m6e-identity/') and '..' not in c['fixture_root'])
    p.require(set(c['bindings']) == {'valid', 'interrupted'})
    for b in c['bindings'].values():
        p.keys(b, 'site_id target_id site_key binding_key binding_version served_root journal_root')
        for root in ('served_root', 'journal_root'):
            p.require(b[root].startswith(c['fixture_root']+'/') and '..' not in b[root])
    for file in (Path(__file__), ROOT/'tests/support/WebsitePlatformM6EIdentityChild.php', ROOT/'infrastructure/deployment/ubo_site_publish.py'):
        p.require(c['installed_files'].get(str(file)) == p.digest(p.protected(str(file), 2097152)))
    return c


def connect(c, role, seconds=30):
    path = '/run/ubo-m6e-identity/'+role+'/probe.sock'
    parent, entry = os.stat(os.path.dirname(path), follow_symlinks=False), os.stat(path, follow_symlinks=False)
    p.require(parent.st_uid == entry.st_uid == c['identities'][role] and stat.S_ISDIR(parent.st_mode)
              and parent.st_mode & 0o022 == 0 and stat.S_ISSOCK(entry.st_mode) and entry.st_mode & 0o007 == 0)
    sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    sock.settimeout(seconds)
    sock.connect(path)
    _, uid, _ = struct.unpack('3i', sock.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))
    p.require(uid == c['identities'][role])
    return sock


def rpc(c, role, value, seconds=35):
    with connect(c, role, seconds) as sock:
        channel = p.Channel(sock, time.monotonic()+seconds)
        channel.reply(value)
        return p.decode(channel.line(p.MAX_RESPONSE))


def permissions(c, role):
    result = {}
    # All paths are fixed in root-owned admission; no request-selected filesystem paths.
    for label, path in c['probes'].items():
        p.require(label in ('source', 'served', 'journal', 'public') and path.startswith(c['fixture_root']+'/') and '..' not in path)
        flags = os.O_RDONLY if label in ('source', 'public') else os.O_WRONLY
        try:
            fd = os.open(path, flags | os.O_NOFOLLOW | os.O_NONBLOCK)
        except OSError as error:
            result[label] = {'errno': error.errno}
            continue
        try:
            s, parent = os.fstat(fd), os.stat(os.path.dirname(path), follow_symlinks=False)
            p.require(stat.S_ISREG(s.st_mode) and s.st_nlink == 1)
            result[label] = dict(errno=0, uid=s.st_uid, mode=stat.S_IMODE(s.st_mode), parent_mode=stat.S_IMODE(parent.st_mode))
        finally:
            os.close(fd)  # Writable probe open never modifies/truncates its bytes.
    return result


def child(case, role, data=None):
    # Snapshot path is fixed by the PHP child and private to this identity.
    cmd = ['/usr/bin/php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=256M',
           str(ROOT/'tests/support/WebsitePlatformM6EIdentityChild.php'), role, case]
    if role == 'authority':
        r = subprocess.run(cmd, input=p.canonical(data)+b'\n', capture_output=True, timeout=5)
        p.require(r.returncode == 0 and not r.stderr and len(r.stdout) <= p.MAX_RESPONSE)
        return p.decode(r.stdout)
    return subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, bufsize=0)


class ChildPipe:
    def __init__(self, process): self.process, self.timeout = process, 30
    def settimeout(self, seconds): self.timeout = seconds
    def recv(self, count):
        p.require(select.select([self.process.stdout], [], [], self.timeout)[0])
        return os.read(self.process.stdout.fileno(), count)
    def send(self, data):
        p.require(select.select([], [self.process.stdin], [], self.timeout)[1])
        return os.write(self.process.stdin.fileno(), data)


def transfer(c, case):
    process = child(case, 'worker')
    try:
        with connect(c, 'helper') as sock:
            deadline = time.monotonic()+30
            worker, helper = p.Channel(ChildPipe(process), deadline), p.Channel(sock, deadline)
            request = worker.line()
            helper.write(request)
            ready = helper.line()
            p.require(p.decode(ready)['disposition'] == 'receive')
            worker.write(ready)
            interrupted = False
            while True:
                header = worker.read(9)
                magic, kind, length = struct.unpack('!4scI', header)
                p.require(magic == b'UBT1' and kind == b'C' and 0 < length <= p.TRANSFER_CONTROL)
                control = worker.read(length)
                helper.write(header+control)
                frame = p.decode(control)
                if frame['type'] == 'end':
                    worker.write(helper.line(p.MAX_RESPONSE))
                    break
                left = frame['length']
                while left:
                    header = worker.read(9)
                    magic, kind, length = struct.unpack('!4scI', header)
                    p.require(magic == b'UBT1' and kind == b'D' and length == min(left,p.TRANSFER_CHUNK))
                    helper.write(header)
                    if case == 'interrupted':
                        helper.write(worker.read(min(3,length)))
                        interrupted = True
                        break
                    helper.write(worker.read(length))
                    left -= length
                if interrupted: break
                header = helper.read(9)
                magic, kind, length = struct.unpack('!4scI', header)
                p.require(magic == b'UBT1' and kind == b'C' and 0 < length <= p.TRANSFER_CONTROL)
                worker.write(header+helper.read(length))
        process.stdin.close()
        process.wait(timeout=5)
        error = process.stderr.read(16385)
        p.require(len(error) <= 16384 and process.returncode == (9 if interrupted else 0)
                  and error == (b'identity_child_unresolved\n' if interrupted else b''))
        return {'exit': process.returncode, 'interrupted': interrupted}
    finally:
        if process.poll() is None: process.kill(); process.wait(timeout=5)
        for stream in (process.stdin,process.stdout,process.stderr): stream.close()


def serve_connection(c, role, sock):
    _, uid, _ = struct.unpack('3i', sock.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))
    channel = p.Channel(sock, time.monotonic()+35)
    value = p.decode(channel.line())
    if role == 'helper' and 'operation' in value:
        p.accept_peer(sock,c['identities']['worker'])
        r = p.request(p.canonical(value))
        matches = [b for b in c['bindings'].values() if all(b[k] == r['target'][k] for k in ('site_id','target_id','binding_key','binding_version'))]
        p.require(len(matches)==1)
        b = matches[0]
        class IndependentFixtureAuthority:
            def observe(self, request):
                remaining=(getattr(self,'deadline',None) or (time.monotonic()+5))-time.monotonic()
                p.require(remaining>0)
                return rpc(c,'authority',request,min(5,remaining))
        served = p.Tree(b['served_root'],os.geteuid(),True,os.getgid())
        journal = p.Tree(b['journal_root'],os.geteuid(),True)
        publisher = p.Publisher(served,journal,IndependentFixtureAuthority(),b)
        try:
            result = publisher.dispatch(r,channel)
            channel.reply(dict(version=1,sequence=r['sequence'],result='ok',evidence=result))
        finally: publisher.close()
        return
    if role == 'authority' and 'operation' in value:
        p.require(uid == c['identities']['helper'])
        matches = [name for name,b in c['bindings'].items() if b['target_id']==value['target']['target_id']]
        p.require(len(matches)==1)
        channel.reply(child(matches[0],'authority',value))
        return
    p.require(uid == c['coordinator_uid'])
    if value == {'type':'permissions'}: channel.reply(permissions(c,role))
    elif role == 'worker' and value.get('type') == 'transfer' and value.get('case') in c['bindings']:
        channel.reply(transfer(c,value['case']))
    elif role == 'helper' and value == {'type':'inventory'}:
        result = {}
        for name,b in c['bindings'].items():
            tree = p.Tree(b['served_root'],os.geteuid(),True,os.getgid())
            try:
                files=[]
                for prefix in ('releases','assets','staging'):
                    fd=tree.directory(prefix)
                    try: nonempty=bool(os.listdir(fd))
                    finally: os.close(fd)
                    if nonempty: files += tree.inventory(prefix)[0]
                result[name]=dict(current=tree.link(),candidate=tree.link('candidate'),files=files)
            finally: tree.close()
        channel.reply(result)
    else: p.require(False)


def observe(source_sha):
    if not Path(ADMISSION).exists(): return {'result':'INCOMPLETE','reason':'separate_identity_fixture_missing'}
    c=admission()
    p.require(c['source_sha']==source_sha and os.geteuid()==c['coordinator_uid'])
    result={'permissions':{role:rpc(c,role,{'type':'permissions'}) for role in ROLES}}
    for role in ('helper','authority','apache'): p.require(result['permissions'][role]['source']['errno']==errno.EACCES)
    for label in ('served','journal'): p.require(result['permissions']['worker'][label]['errno']==errno.EACCES)
    for label in ('served','journal'): p.require(result['permissions']['helper'][label]['errno']==0)
    p.require(result['permissions']['apache']['journal']['errno']==errno.EACCES)
    source=result['permissions']['worker']['source']
    p.require(source['errno']==0 and source['mode']==0o600 and source['parent_mode']==0o700
              and result['permissions']['apache']['public']['errno']==0)
    before=rpc(c,'helper',{'type':'inventory'})
    p.require(all(v==dict(current=None,candidate=None,files=[]) for v in before.values()))
    # Coordinator UID is deliberately wrong for a stage session; actual peer check must close it.
    with connect(c,'helper') as wrong:
        channel=p.Channel(wrong,time.monotonic()+5)
        channel.reply({'operation':'stage'})
        p.require(wrong.recv(1)==b'')
    p.require(rpc(c,'helper',{'type':'inventory'})==before)
    result['valid']=rpc(c,'worker',{'type':'transfer','case':'valid'})
    result['interrupted']=rpc(c,'worker',{'type':'transfer','case':'interrupted'})
    after=rpc(c,'helper',{'type':'inventory'})
    p.require(after['valid']['candidate'] is not None and after['valid']['current'] is None
              and after['interrupted']['candidate'] is None and after['interrupted']['current'] is None
              and not after['interrupted']['files'])
    result['observed']=after
    result['result']='TRANSFER_COMPONENT_PASS'  # Only after the executable observations above.
    return result


def main():
    c=admission()
    p.require(sys.argv[1:]==['--serve'])
    roles=[role for role,uid in c['identities'].items() if uid==os.geteuid()]
    p.require(len(roles)==1)
    role=roles[0]
    # A separately approved launcher supplies this UID's prebound listening Unix FD.
    # Its socket has fixed protected ancestors and its creator UID is verified by connect().
    listener=socket.socket(fileno=os.dup(0))
    listener.settimeout(max(.01,c['expires_at']-time.time()))
    count=0
    while time.time()<c['expires_at'] and count<200:
        sock,_=listener.accept();count+=1
        with sock:
            try: serve_connection(c,role,sock)
            except BaseException: os.write(2,b'identity_fixture_unresolved\n')
    return 0


if __name__=='__main__':
    raise SystemExit(main())
