"""Bounded child ownership/capture and exact report publication; no import-time effects."""
import hashlib
import copy
import json
import os
from pathlib import Path
import queue
import re
import signal
import stat
import subprocess
import threading
import time

class Failure(RuntimeError):
    def __init__(self,code,context=None):
        super().__init__(code); self.code=code; self.context=context or {}

class Owner:
    def __init__(self,deadline=None):
        self.deadline=deadline if deadline is not None else time.monotonic()+600
        self.children=[]; self.context=[]; self.resources=[]; self.finalizers=[]; self.secondary=[]

    def track(self,name,kind,identity,cleanup):
        """Register an intention BEFORE partially successful construction/mutation."""
        if any(not isinstance(x,str) or re.fullmatch('[A-Za-z0-9_-]{1,160}',x) is None for x in (name,kind)):raise Failure('resource_label')
        if any(r['id']==name for r in self.resources): raise Failure('duplicate_resource')
        record=dict(id=name,kind=kind,identity_sha256=hashlib.sha256(identity).hexdigest(),state='INTENT')
        self.resources.append(record);self.finalizers.append((record,cleanup))
        return record

    def snapshot(self):
        """Safe bounded metadata only; never handles, argv, pipes, paths or bodies."""
        if len(self.context)>256 or len(self.resources)>256:raise Failure('context_bound')
        children=[]
        for c in self.context:
            child={k:c[k] for k in ('pid','exit','signal','last_barrier','reaped')}
            for key in ('termination_requested','exit_before_cleanup'):
                if key in c:child[key]=c[key]
            if not isinstance(child['last_barrier'],str) or re.fullmatch('[A-Za-z0-9_-]{1,80}',child['last_barrier']) is None:raise Failure('barrier_label')
            children.append(child)
        resources=[{k:r[k] for k in ('id','kind','identity_sha256','state')} for r in self.resources]
        snapshot=copy.deepcopy(dict(children=children,resources=resources))
        from WebsitePlatformM6EAcceptance import validate_context
        validate_context(snapshot);return snapshot

    def capture(self,command,seconds=30,stdin=b'',limit=1048576,error_limit=16384,identity=None):
        if time.monotonic()>=self.deadline: raise Failure('coordinator_deadline')
        if not isinstance(command,list) or not command or any(type(x) is not str for x in command): raise Failure('command')
        kwargs=dict(stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,bufsize=0,
                    env={'PATH':'/usr/bin:/bin','LANG':'C','HOME':'/nonexistent'},start_new_session=os.name!='nt')
        if identity is not None:
            if os.name=='nt': raise Failure('identity_unavailable')
            uid,gid,groups=identity; kwargs.update(user=uid,group=gid,extra_groups=groups,umask=0o077)
        child=subprocess.Popen(command,**kwargs)
        self.children.append(child)  # Before pipes/threads can fail.
        record=dict(pid=child.pid,exit=None,signal=0,last_barrier='SPAWNED',reaped=False,termination_requested=False,exit_before_cleanup=None)
        self.context.append(record); events=queue.Queue(maxsize=4); stop=threading.Event()
        def drain(stream,side):
            try:
                while not stop.is_set():
                    part=stream.read(8192)
                    while not stop.is_set():
                        try: events.put((side,part),timeout=.05); break
                        except queue.Full: pass
                    if not part: break
            except (OSError,ValueError): pass
        def feed():
            try:
                view=memoryview(stdin)
                while view and not stop.is_set():
                    n=child.stdin.write(view[:8192])
                    if not n: break
                    view=view[n:]
            except (BrokenPipeError,OSError,ValueError): pass
            finally:
                try: child.stdin.close()
                except OSError: pass
        threads=[threading.Thread(target=drain,args=(child.stdout,'out'),daemon=True),
                 threading.Thread(target=drain,args=(child.stderr,'err'),daemon=True),threading.Thread(target=feed,daemon=True)]
        data={'out':bytearray(),'err':bytearray()}; ended=set(); primary=None
        try:
            for thread in threads: thread.start()
            end=min(self.deadline,time.monotonic()+seconds)
            while len(ended)<2:
                if time.monotonic()>=end: raise Failure('child_timeout',record.copy())
                try: side,part=events.get(timeout=min(.05,max(.001,end-time.monotonic())))
                except queue.Empty: continue
                if not part: ended.add(side); continue
                if len(data[side])+len(part)>(limit if side=='out' else error_limit): raise Failure('child_output_bound',record.copy())
                data[side].extend(part)
            child.wait(timeout=max(.001,end-time.monotonic()))
            record['last_barrier']='EXITED'
            return dict(exit=child.returncode,stdout=bytes(data['out']),stderr=bytes(data['err']),pid=child.pid)
        except BaseException as error:
            primary=error; raise
        finally:
            stop.set()
            try:
                record['exit_before_cleanup']=child.poll()
                if os.name=='nt':
                    if child.poll() is None:record['termination_requested']=True;child.kill()
                else:
                    # A successful leader exit does not establish that its owned
                    # descendants exited; close the whole private process group.
                    try: os.killpg(child.pid,signal.SIGKILL);record['termination_requested']=True
                    except ProcessLookupError: pass
                child.wait(timeout=5)
                record.update(exit=child.returncode,signal=-child.returncode if child.returncode<0 else 0,reaped=True)
            except BaseException:
                if primary is None: raise Failure('child_reap',record.copy())
            for stream in (child.stdin,child.stdout,child.stderr):
                try: stream.close()
                except OSError: pass
            for thread in threads:
                if thread.ident is not None: thread.join(timeout=.2)

    def cleanup(self):
        failures=[]
        for child,record in zip(self.children,self.context):
            try:
                if child.poll() is None:
                    record['exit_before_cleanup']=None;record['termination_requested']=True
                    if os.name=='nt': child.kill()
                    else: os.killpg(child.pid,signal.SIGKILL)
                child.wait(timeout=5)
                record.update(exit=child.returncode,signal=max(0,-child.returncode),reaped=True)
            except BaseException: failures.append(child.pid)
        for record,cleanup in reversed(self.finalizers):
            if record['state']=='RELEASED': continue
            try: cleanup();record['state']='RELEASED'
            except BaseException as error:
                if record['state']!='RELEASED':record['state']='UNRESOLVED'
                self.secondary.append(type(error).__name__);failures.append(record['id'])
        if failures: raise Failure('owned_cleanup',{'resources':failures})

def publish_bytes(directory,stem,raw):
    """Never overwrite a report; preserve original bytes and a separately checked receipt."""
    if len(raw)>4*1024*1024 or not stem.replace('-','').isalnum(): raise Failure('report_bound')
    root=Path(directory); before=root.lstat()
    if not stat.S_ISDIR(before.st_mode) or root.is_symlink(): raise Failure('report_root')
    path=root/(stem+'.json'); pending=root/(stem+'.pending'); fd=None; created=None; installed=False; primary=None
    try:
        fd=os.open(pending,os.O_WRONLY|os.O_CREAT|os.O_EXCL|getattr(os,'O_NOFOLLOW',0),0o600); created=os.fstat(fd)
        view=memoryview(raw)
        while view:
            n=os.write(fd,view[:65536])
            if not n: raise Failure('report_write')
            view=view[n:]
        os.fsync(fd); closed,fd=fd,None; os.close(closed)
        now=root.lstat()
        if (now.st_dev,now.st_ino)!=(before.st_dev,before.st_ino): raise Failure('report_root_replaced')
        if (pending.lstat().st_dev,pending.lstat().st_ino)!=(created.st_dev,created.st_ino): raise Failure('report_identity')
        # link is atomic no-replace for a regular report on the same filesystem.
        os.link(pending,path,follow_symlinks=False); installed=True
        pending.unlink()
        if os.name!='nt':
            parent=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
            try: os.fsync(parent)
            finally: os.close(parent)
        s=path.lstat()
        if (s.st_dev,s.st_ino)!=(created.st_dev,created.st_ino) or s.st_nlink!=1 or not stat.S_ISREG(s.st_mode): raise Failure('report_final_identity')
        if path.read_bytes()!=raw: raise Failure('report_readback')
        return dict(file=path.name,bytes=len(raw),sha256=hashlib.sha256(raw).hexdigest())
    except BaseException as error:
        primary=error; raise
    finally:
        try:
            if fd is not None: os.close(fd)
            if created is not None and pending.exists():
                now=pending.lstat()
                if (now.st_dev,now.st_ino)==(created.st_dev,created.st_ino): pending.unlink()
        except BaseException:
            if primary is None: raise
