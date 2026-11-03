"""Native helper/independent observer. Fixed Linux admission; no local override."""
import os
from pathlib import Path
import socket
import struct
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, safe_error
from WebsitePlatformM6EPreparation import protected
from WebsitePlatformM6EBootstrapLedger import Files
from WebsitePlatformM6ECommissionChild import admission
from WebsitePlatformM6EComponentHost import snapshot, identity, serving_inventory
import WebsitePlatformM6EComponent as c
import WebsitePlatformM6EBootstrapPlan as b

def emit(kind,data):
    raw=canonical(dict(kind=kind,pid=os.getpid(),data=data));need(len(raw)<2097152,'component_output')
    sys.stdout.buffer.write(raw+b'\n');sys.stdout.buffer.flush()

def helper(m,plan,served,journal,listener,peer,source_errno,expected_pid,extra=None):
    # Exclusively creates this namespace's state. Never initializes over a fence.
    journal.write_new('state.json',canonical(b.PRISTINE))
    emit('ready',dict(uid=2102,source_errno=source_errno)| (extra or {}))
    listener.settimeout(min(30,max(.001,plan['expires_at']-time.time())))
    incoming,_=listener.accept()
    try:
        observed=peer(incoming)
        need(observed==dict(pid=expected_pid,uid=2000),'component_expected_peer')
        c.stage(m,plan,served,journal,m.Channel(incoming,time.monotonic()+30),observed,emit)
    finally:incoming.close();listener.close()

def observer(m,plan,served,journal,fs,info=identity):
    def metadata(root,name):return info(fs,plan['paths'][root]+'/'+name)
    value=c.observe(m,plan,served,journal,metadata)
    # Independent exact namespace inventory, beyond public-package validation.
    raw=fs.read(plan['paths']['journal']+'/manifests/'+plan['release']['release_key']+'.json')
    manifest=m.transfer_entries(raw,plan['release']);objects=snapshot(fs,plan,manifest,info,observer=True)
    value['inventory_sha256']=digest(canonical(objects))
    resources=serving_inventory(fs,plan,info)
    if resources is not None:value['serving_inventory']=resources
    emit('observer',value)

def main():
    need(len(sys.argv)==2 and sys.argv[1] in ('--helper','--observer','--http'),'component_mode')
    mode=sys.argv[1][2:];config,_,fixture,m=admission()
    need(os.geteuid()==ROLES['worker' if mode=='http' else 'helper'],'component_child_uid')
    plan=c.validate_plan(decode(protected(c.paths(config['run_id'])['plan'],1048576)),config,fixture)
    command=decode(sys.stdin.buffer.readline(2097153 if mode=='http' else 1025))
    if mode=='http':
        need(set(command)=={'command','manifest'} and command['command']=='http','component_http_gate')
        manifest=m.transfer_entries(canonical(command['manifest']),plan['release'])
        from WebsitePlatformM6ECommissionServing import CandidateChecks
        from WebsitePlatformM6EApache import candidate_fetch
        def request(phase,path,method,variant):
            need(phase=='candidate' and time.time()<plan['expires_at'],'component_http_deadline')
            return candidate_fetch(path,method,variant)
        CandidateChecks(manifest,request,lambda value:emit('http-progress',value)).run()
        emit('http-done',dict(uid=os.geteuid()));return
    need(command.get('command')==mode and set(command)==({'command','worker_pid'} if mode=='helper' else {'command'}),'component_start_gate')
    if mode=='helper':need(type(command['worker_pid']) is int and command['worker_pid']>0,'component_expected_worker')
    fs=Files();p=plan['paths'];m.local_mount(p['served']);m.local_mount(p['journal'])
    served=m.Tree(p['served'],2102,True,2104);journal=m.Tree(p['journal'],2102,True,2102)
    try:
        if sys.argv[1]=='--observer':observer(m,plan,served,journal,fs);return
        source=b.STORAGE+'/sealed/'+fixture['site_key']+'/'+plan['release']['release_key']+'/metadata/manifest.json'
        try:fd=os.open(source,os.O_RDONLY|os.O_NOFOLLOW)
        except OSError as error:source_errno=error.errno
        else:os.close(fd);source_errno=0
        need(source_errno==13,'component_helper_source_access')
        listener=socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
        try:
            listener.bind(p['socket']);os.chmod(p['socket'],0o660);listener.listen(1)
            def peer(incoming):
                m.accept_peer(incoming,2000)
                pid,uid,_=struct.unpack('3i',incoming.getsockopt(socket.SOL_SOCKET,socket.SO_PEERCRED,12))
                need(pid==command['worker_pid'],'component_expected_peer')
                return dict(pid=pid,uid=uid)
            helper(m,plan,served,journal,listener,peer,source_errno,command['worker_pid'])
        finally:listener.close()
    finally:served.close();journal.close()

if __name__=='__main__':
    try:main()
    except BaseException as error:
        # Fixed failure classifications only; private paths/SQL/bytes never stderr.
        emit('failure',safe_error(error));sys.exit(1)
