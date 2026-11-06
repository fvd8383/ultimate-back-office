"""Real UID/Unix socket observations, run only by the admitted disposable operator."""
import errno
import json
import os
from pathlib import Path
import socket
import stat
import struct
import sys
import time

# Isolated Python ignores PYTHONPATH; this path is the fixed source-owned sibling directory.
sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import ROLES, digest, need
from WebsitePlatformM6EPreparation import protected, CONTROL

def main(argv):
    need(sys.platform=='linux' and len(argv)==1,'peer_arguments')
    plan=json.loads(protected(CONTROL/'run.json',2097152));identity=plan['identity'];uid=os.geteuid()
    need(uid in ROLES.values() and identity['boot_id']==Path('/proc/sys/kernel/random/boot_id').read_text().strip()
         and identity['machine_id']==Path('/etc/machine-id').read_text().strip() and time.time()<plan['expires_at'],'peer_admission')
    b=plan['helper']['bindings'][0];source=Path(plan['worker']['artifact_root'])/b['site_key']/plan['fixture']['release']['release_key']/'metadata/manifest.json'
    if argv==['--permissions']:
        paths={'source':(source,os.O_RDONLY),'served':(Path(b['served_root'])/'permission-probe',os.O_WRONLY),
               'journal':(Path(b['journal_root'])/'target.lock',os.O_WRONLY),'public':(Path(b['served_root'])/'current/index.html',os.O_RDONLY)}
        values={}
        for label,(path,flags) in paths.items():
            try:fd=os.open(path,flags|os.O_NOFOLLOW|os.O_NONBLOCK)
            except OSError as error:values[label]={'errno':error.errno};continue
            try:
                s=os.fstat(fd);parent=path.parent.stat()
                need(stat.S_ISREG(s.st_mode) and s.st_nlink==1,'permission_regular')
                values[label]=dict(errno=0,uid=s.st_uid,mode=stat.S_IMODE(s.st_mode),parent_mode=stat.S_IMODE(parent.st_mode))
            finally:os.close(fd)
        print(json.dumps(dict(pid=os.getpid(),uid=uid,permissions=values)));return 0
    if argv==['--config-observe']:
        need(uid==ROLES['helper'],'config_observer')
        link=Path(b['served_root'])/'current';target=os.readlink(link) if link.is_symlink() else ''
        print(json.dumps(dict(pid=os.getpid(),uid=uid,config_sha256=digest(protected('/etc/ubo-site-publisher/apache.conf',131072)),
                             current_sha256=digest(target.encode()))));return 0
    need(argv[0] in ('--wrong-peer','--malformed') and uid==(ROLES['coordinator'] if argv[0]=='--wrong-peer' else ROLES['worker']),'socket_role')
    path='/run/ubo-site-publisher/publish.sock';s=Path(path).lstat()
    need(stat.S_ISSOCK(s.st_mode) and s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o660,'protected_socket')
    with socket.socket(socket.AF_UNIX,socket.SOCK_STREAM) as channel:
        channel.settimeout(5);channel.connect(path)
        pid,peer,gid=struct.unpack('3i',channel.getsockopt(socket.SOL_SOCKET,socket.SO_PEERCRED,12))
        need(peer==0,'supervisor_peer')
        channel.sendall(b'{"version":999}\n')
        try:reply=channel.recv(1025)
        except ConnectionResetError:reply=b''
        need(len(reply)<=1024,'rejection_bound')
        print(json.dumps(dict(pid=os.getpid(),uid=uid,server_uid=peer,connected=1,response_bytes=len(reply),closed=int(reply==b''))))
    return 0

if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except BaseException as error:
        if isinstance(error,SystemExit):raise
        print('m6e_peer_unresolved',file=sys.stderr);raise SystemExit(1)
