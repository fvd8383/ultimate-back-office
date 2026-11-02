"""Explicit local OS adapters for the real component children; never native admission.

Actual private files, PHP/Python processes and loopback TCP carry the original UBT1
bytes. Unix UID/mode/dirfd/pointer/flock/rename semantics are controlled models.
The operational helper and native child do not import this module.
"""
import json
import os
from pathlib import Path
import socket
import stat
import sys
from types import SimpleNamespace

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EFilesystem import FilesystemModel
from WebsitePlatformM6EBootstrapLedger import Files
from WebsitePlatformM6EAcceptance import canonical,decode,need,safe_error
from WebsitePlatformM6EComponentChild import helper,observer,emit
import WebsitePlatformM6EComponent as c

class Model(FilesystemModel):
    def __init__(self,root,state):
        super().__init__(root);self.state=Path(state);data=decode(self.state.read_bytes())
        self.meta=data['meta'];self.links=data['links'];self.locks={}
        self.uid=2102;self.groups={2102,2104};self.access=[]
        for name in list(self.meta):
            for parent in Path(name).parents:
                if parent==self.root or parent.is_relative_to(self.root):
                    self.meta.setdefault(str(parent),[0o755,0,0])
    def save(self):self.state.write_bytes(canonical(dict(meta=self.meta,links=self.links)))
    def path(self,name,dir_fd=None):
        if dir_fd is not None:return self.dirs[dir_fd]/name
        path=Path(name)
        return self.root/str(name).lstrip('/') if str(name).startswith('/') and not path.is_relative_to(self.root) else path
    def permit(self,path,bits):
        value=self.metadata(path,path.lstat());mode=value.st_mode
        granted=(mode>>6 if value.st_uid==self.uid else mode>>3 if value.st_gid in self.groups else mode)&7
        self.access.append(dict(path=str(path),uid=self.uid,required=bits,granted=granted))
        if self.uid!=0 and granted&bits!=bits:raise PermissionError(13,'modeled_access',str(path))
    def open(self,name,flags,mode=0o777,*,dir_fd=None):
        path=self.path(name,dir_fd);fresh=not path.exists()
        need(str(path) not in self.links,'modeled_nofollow')
        # Traverse needs search; O_RDONLY directory handles additionally need
        # read. Exercise the original Tree constructor, not __new__ bypass.
        for parent in reversed(path.parents):
            if parent==self.root or parent.is_relative_to(self.root):self.permit(parent,1)
        if fresh and flags&os.O_CREAT:self.permit(path.parent,2)
        elif not fresh:
            access=flags&os.O_ACCMODE
            self.permit(path,4 if access==os.O_RDONLY else 6 if access==os.O_RDWR else 2)
        fd=super().open(name,flags,mode,dir_fd=dir_fd)
        if fresh:self.meta[str(path)]=[mode,2102,2102]
        return fd
    def mkdir(self,name,mode,*,dir_fd=None):
        super().mkdir(name,mode,dir_fd=dir_fd);self.meta[str(self.path(name,dir_fd))]=[mode,2102,2102]
    def metadata(self,path,actual):
        value=super().metadata(path,actual)
        if str(path) in self.links:value.st_mode=stat.S_IFLNK|0o777
        if path==getattr(self,'socket_path',None):value.st_mode=stat.S_IFSOCK|0o660
        return value
    def symlink(self,target,name,*,dir_fd=None):
        path=self.path(name,dir_fd)
        with path.open('xb') as f:f.write(target.encode())
        self.links[str(path)]=target;self.meta[str(path)]=[0o777,2102,2102]
    def readlink(self,name,*,dir_fd=None):return self.links[str(self.path(name,dir_fd))]
    def replace(self,source,destination,*,src_dir_fd=None,dst_dir_fd=None):
        before=str(self.path(source,src_dir_fd));after=str(self.path(destination,dst_dir_fd))
        super().replace(source,destination,src_dir_fd=src_dir_fd,dst_dir_fd=dst_dir_fd)
        self.links.pop(after,None)
        if before in self.links:self.links[after]=self.links.pop(before)
    def unlink(self,name,*,dir_fd=None):
        super().unlink(name,dir_fd=dir_fd);self.links.pop(str(self.path(name,dir_fd)),None)
    def flock(self,fd,operation):
        # Explicit cross-process exclusive lock model, outside the owned tree.
        need(operation==6,'modeled_lock_operation')
        lock=Path(str(self.state)+'.lock');held=os.open(lock,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
        self.locks[fd]=(held,lock)
    def close(self,fd):
        if fd in self.locks:
            held,lock=self.locks.pop(fd);os.close(held);lock.unlink()
        super().close(fd)
    def tree(self,module,path,group):
        module.sys=SimpleNamespace(platform='linux') # Explicit OS adapter only.
        return module.Tree('/'+Path(path).relative_to(self.root).as_posix(),2102,True,group)

class Mapped(Files):
    def __init__(self,root,ops):self.root=Path(root);self.ops=ops
    def path(self,path):return self.root/path.lstrip('/')
    def identity(self,path,fd=None):
        s=self.ops.stat(self.path(path));return dict(dev=s.st_dev,ino=s.st_ino,uid=s.st_uid,gid=s.st_gid,
            kind=stat.S_IFMT(s.st_mode),mode=stat.S_IMODE(s.st_mode))

def info(fs,path):
    s=fs.path(path).lstat();value=fs.identity(path)|dict(nlink=s.st_nlink)
    if value['kind']==stat.S_IFLNK:value['target']=fs.ops.links[str(fs.path(path))]
    return value

class DamagedSocket:
    """Faults in the transport adapter; framing and receiver stay production code."""
    def __init__(self,sock,fault):self.sock,self.fault,self.count=sock,fault,0;self.changed=False
    def __getattr__(self,name):return getattr(self.sock,name)
    def recv(self,count,flags=0):
        data=self.sock.recv(count,flags)
        if flags:return data
        self.count+=len(data)
        if count>100 and self.fault=='helper-stage-death':os._exit(18)
        # Channel requests binary bodies in chunks > 9; JSON request/control
        # headers are read separately. Corrupt the actual original manifest body.
        if not self.changed and count>100 and self.fault in ('manifest-corrupt','truncated','body-corrupt'):
            if self.fault=='body-corrupt' and b'<html' not in data and b'<!doctype' not in data.lower():return data
            self.changed=True
            if self.fault=='truncated':return b''
            return bytes([data[0]^1])+data[1:] if data else data
        return data

def main():
    need(len(sys.argv)==2 and sys.argv[1] in ('helper','observer'),'local_component_mode')
    start=decode(sys.stdin.buffer.readline(2097153));plan=start['plan'];ops=Model(start['root'],start['model'])
    fs=Mapped(start['root'],ops);ops.socket_path=fs.path(plan['paths']['socket']);m=c.publisher_module();m.os=ops
    m.Tree._rename_noreplace=staticmethod(ops.rename_noreplace)
    sys.modules['fcntl']=SimpleNamespace(flock=ops.flock,LOCK_EX=2,LOCK_NB=4)
    served=ops.tree(m,fs.path(plan['paths']['served']),2104);journal=ops.tree(m,fs.path(plan['paths']['journal']),2102)
    try:
        if sys.argv[1]=='observer':observer(m,plan,served,journal,fs,info);return
        source=start['source'];need(Path(source['path']).is_file(),'local_original_source')
        source_errno=13 if source['uid']!=2102 and source['mode']&0o004==0 else 0
        need(source_errno==13,'component_helper_source_access')
        listener=socket.socket(socket.AF_INET,socket.SOCK_STREAM);listener.bind(('127.0.0.1',0));listener.listen(1)
        endpoint='tcp://127.0.0.1:'+str(listener.getsockname()[1])
        leaf=fs.path(plan['paths']['socket'])
        with leaf.open('xb') as f:f.write(b'controlled Unix socket identity')
        ops.meta[str(leaf)]=[0o660,2102,2000];ops.save()
        if start['fault']=='helper-death':os._exit(17)
        def peer(incoming):return dict(uid=2001 if start['fault']=='peer-uid' else 2000,
            pid=start['worker_pid']+(1 if start['fault']=='peer-pid' else 0))
        original_accept=listener
        class Listener:
            def settimeout(self,value):original_accept.settimeout(value)
            def accept(self):
                sock,address=original_accept.accept();return DamagedSocket(sock,start['fault']),address
            def close(self):original_accept.close()
        helper(m,plan,served,journal,Listener(),peer,source_errno,start['worker_pid'],dict(endpoint=endpoint))
    finally:
        served.close();journal.close();ops.save()

if __name__=='__main__':
    try:main()
    except BaseException as error:emit('failure',safe_error(error));sys.exit(1)
