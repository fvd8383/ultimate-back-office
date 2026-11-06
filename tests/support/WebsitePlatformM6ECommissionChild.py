"""Fixed Linux component child. Never starts the installed publishing worker/helper."""
import importlib.util
import json
import os
from pathlib import Path
import signal
import socket
import stat
import sys
import time

sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need
from WebsitePlatformM6EPreparation import protected
import WebsitePlatformM6EBootstrapPlan as p

def admission():
    need(sys.platform=='linux' and str(Path(__file__).resolve().parent)==p.CHECKOUT+'/tests/support','commission_child_linux')
    config=decode(protected(p.CONTROL+'/bootstrap.json'));uid=os.geteuid()
    need(uid in (ROLES['worker'],ROLES['helper'],ROLES['apache']) and time.time()<config['expires_at']
         and Path('/etc/machine-id').read_text().strip()==config['machine_id']
         and Path('/proc/sys/kernel/random/boot_id').read_text().strip()==config['boot_id']
         and socket.gethostname()=='ubo-m6e-'+config['run_id'][:16]
         and 'ubo.m6e='+config['run_id'] in Path('/proc/cmdline').read_text().split(),'commission_child_binding')
    c=decode(protected(p.CONFIG+'/helper.json'));f=decode(protected(p.CONTROL+'/fixture.json'))
    b=c['bindings'][0]
    need(b['site_key']==f['site_key'] and b['site_id']==f['site_id'] and b['target_id']==f['target_id'],'commission_child_fixture')
    spec=importlib.util.spec_from_file_location('commission_publisher','/usr/local/lib/ubo-site-publisher/ubo_site_publish.py')
    publisher=importlib.util.module_from_spec(spec);spec.loader.exec_module(publisher)
    return config,b,f,publisher

def access(b,f):
    root=Path(b['served_root']);source=Path(p.STORAGE)/'sealed'/f['site_key']/f['releases'][0]['release_key']/'metadata/manifest.json'
    public=root/'releases/commission-permissions/public/index.html'
    cases={
        'source':(source,os.O_RDONLY),
        'served':(root/('commission-write-'+str(os.geteuid())),os.O_WRONLY|os.O_CREAT|os.O_EXCL),
        'journal':(Path(b['journal_root'])/'target.lock',os.O_WRONLY),
        'public':(public,os.O_RDONLY),
        'public_write':(public,os.O_WRONLY),
    }
    result={}
    for name,(target,flags) in cases.items():
        try:fd=os.open(target,flags|os.O_NOFOLLOW|os.O_NONBLOCK,0o600)
        except OSError as error:result[name]=dict(errno=error.errno);continue
        try:
            s=os.fstat(fd);need(stat.S_ISREG(s.st_mode) and s.st_nlink==1,'access_regular')
            result[name]=dict(errno=0,mode=stat.S_IMODE(s.st_mode),parent_mode=stat.S_IMODE(target.parent.stat().st_mode),
                              sha256=digest(os.read(fd,1048576)) if flags==os.O_RDONLY else digest(b''))
        finally:os.close(fd)
    return result

def filesystem(b,m):
    need(os.geteuid()==ROLES['helper'],'filesystem_role')
    # Open the actual configured root; a nested Tree would violate the real
    # root-owned-ancestor policy. Scratch stays on this root's measured device.
    m.local_mount(b['served_root']);m.local_mount(b['journal_root'])
    tree=m.Tree(b['served_root'],ROLES['helper'],True,ROLES['apache'])
    prefix='staging/commission-filesystem';folder=Path(b['served_root'])/prefix
    need(not list(folder.iterdir()),'filesystem_scratch_not_empty')
    # Narrow an already admitted descriptor through the actual no-follow walk.
    # The component's pointer/manifest names remain inside its owned scratch.
    narrowed=tree.directory(prefix);os.close(tree.fd);tree.fd=narrowed
    data=b'M6E nonsecret commissioning publication canary\n';syncs=[]
    real_sync=m.os.fsync;real_read=m.os.read;child=None
    def sync(fd):
        real_sync(fd);syncs.append(os.fstat(fd).st_dev)
    def rejected(action):
        try:action()
        except (m.Denied,OSError):return 1
        return 0
    try:
        m.os.fsync=sync
        manifest='manifests/'+b['site_key']+'.json'
        tree.publish_immutable(manifest,data)
        one=tree.read(manifest,1024);tree.publish_immutable(manifest,data)
        two=tree.read(manifest,1024)
        conflict=rejected(lambda:tree.publish_immutable(manifest,b'conflicting bytes'))
        os.symlink('/etc/passwd',folder/'escape')
        link=rejected(lambda:tree.read('escape',1024))
        tree.write_new('object',data);tree.write_new('replacement',data)
        replaced=False
        def changing_read(fd,n):
            nonlocal replaced
            chunk=real_read(fd,n)
            if not replaced:
                replaced=True;os.replace(folder/'replacement',folder/'object')
            return chunk
        m.os.read=changing_read
        race=rejected(lambda:tree.read('object',1024));m.os.read=real_read
        # Independent child reads through each atomic sibling replacement. The
        # two forced endpoints prove both values were observed; every concurrent
        # observation is retained as a count, including failures/unknown values.
        for name in ('old','new'):tree.write_new(name,name.encode())
        os.symlink('old',folder/'pointer')
        ready_r,ready_w=os.pipe();go_r,go_w=os.pipe();out_r,out_w=os.pipe()
        child=os.fork()
        if child==0:
            try:
                for fd in (ready_r,go_w,out_r):os.close(fd)
                counts={'old_observations':0,'new_observations':0,'other_observations':0}
                def observe():
                    try:value=(folder/'pointer').read_bytes()
                    except OSError:value=b'ERROR'
                    key='old_observations' if value==b'old' else 'new_observations' if value==b'new' else 'other_observations'
                    counts[key]+=1
                observe();os.write(ready_w,b'R');os.read(go_r,1)
                for _ in range(256):observe()
                os.write(ready_w,b'D');os.read(go_r,1);observe()
                os.write(out_w,canonical(counts));os._exit(0)
            except BaseException:os._exit(91)
        for fd in (ready_w,go_r,out_w):os.close(fd)
        import select
        def barrier(expected):
            need(select.select([ready_r],[],[],3)[0] and os.read(ready_r,1)==expected,'pointer_reader_barrier')
        barrier(b'R');os.write(go_w,b'G')
        for index in range(64):
            os.symlink('new' if index%2==0 else 'old',folder/'next');os.replace(folder/'next',folder/'pointer')
        barrier(b'D');os.symlink('new',folder/'next');os.replace(folder/'next',folder/'pointer');real_sync(tree.fd)
        os.write(go_w,b'F');need(select.select([out_r],[],[],3)[0],'pointer_reader_result')
        counts=decode(os.read(out_r,4096));pid,status=os.waitpid(child,0);child=None
        for fd in (ready_r,go_w,out_r):os.close(fd)
        out=dict(device=tree.device,journal_device=os.stat(b['journal_root']).st_dev,
            mount_sha256=digest(Path('/proc/self/mountinfo').read_bytes()),read_sha256=digest(one),replay_sha256=digest(two),
            conflict_rejected=conflict,link_rejected=link,replacement_rejected=race,reader_pid=pid,
            reader_exit=os.waitstatus_to_exitcode(status),sync_calls=len(syncs),**counts)
        # Raw observations reach the parent before it captures/restores scratch.
        return out
    finally:
        m.os.fsync=real_sync;m.os.read=real_read
        if child is not None:
            try:os.kill(child,signal.SIGKILL)
            except ProcessLookupError:pass
            os.waitpid(child,0)
        tree.close()

def main(argv):
    need(argv in (['--filesystem'],['--access'],['--admit-receipt']),'commission_child_usage')
    config,b,f,m=admission()
    if argv==['--filesystem']:out=filesystem(b,m)
    elif argv==['--access']:out=access(b,f)
    else:
        need(os.geteuid()==ROLES['helper'],'helper_admission_role')
        sys.argv=[sys.argv[0]];m.load_config();out={'result':'HELPER RECEIPT ADMITTED'}
    print(json.dumps(dict(pid=os.getpid(),uid=os.geteuid(),data=out),sort_keys=True));return 0

if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except Exception:
        print('m6e_commission_child_unresolved',file=sys.stderr);raise SystemExit(1)
