"""Fixed fixture/operator configuration boundary; routine publication cannot import it."""
import hashlib
import os
from pathlib import Path
import stat

from WebsitePlatformM6EProcess import Failure

ROOT='/etc/ubo-site-publisher'
LIVE=ROOT+'/apache.conf'
PENDING=ROOT+'/apache.pending.conf'
APACHE='/usr/sbin/apache2'
COMMANDS={
 'validate': [APACHE,'-t','-f',PENDING],
 'live-validate': [APACHE,'-t','-f',LIVE],
 'reload': ['/usr/bin/systemctl','reload','ubo-m6e-apache.service'],
 'inspect': ['/usr/bin/systemctl','show','ubo-m6e-apache.service','--property=MainPID,ActiveState,SubState,InvocationID'],
}

class Provisioner:
    def __init__(self,storage,execute,observe,admit):
        self.storage,self.execute,self.observe,self.admit=storage,execute,observe,admit
        self.actions=[]

    def command(self,name):
        if name not in COMMANDS: raise Failure('provision_command')
        self.actions.append(name)
        return self.execute(COMMANDS[name])

    def apply(self,variant):
        self.admit()  # Before read, staging, configtest or reload; cannot address another namespace.
        if variant not in ('valid','invalid'): raise Failure('provision_variant')
        before=self.storage.snapshot(); prior=self.observe(); installed=False; primary=None
        result=dict(result='UNKNOWN',before=before,observed_before=prior,installed=None,observed_after=None,phase='stage')
        try:
            self.storage.stage(variant)
            result['phase']='configtest'
            checked=self.command('validate')
            result['configtest_exit']=checked['exit']
            if checked['exit']!=0:
                after=self.storage.snapshot(); observed=self.observe()
                if after!=before or observed!=prior: raise Failure('rejected_config_changed')
                result.update(result='REJECTED',installed=after,observed_after=observed)
                return result
            if variant!='valid': raise Failure('invalid_config_accepted')
            self.storage.install(); installed=True
            result.update(installed=self.storage.snapshot(),phase='reload')
            try: reload=self.command('reload'); result['reload_exit']=reload['exit']
            except Failure:
                result['reload_exit']=None
                return result  # Installed config may differ; no blind rollback, no success.
            result['phase']='reconcile'
            result['observed_after']=self.observe()
            if reload['exit']==0 and self.storage.matches_observed(result['observed_after']): result['result']='VERIFIED'
            return result
        except BaseException as error:
            primary=error; raise
        finally:
            try: self.storage.cleanup_pending()
            except BaseException:
                if primary is None: raise Failure('provision_cleanup')

    def reconcile(self):
        self.admit()
        installed=self.storage.snapshot(); observed=self.observe()
        return dict(installed=installed,observed=observed,
                    result='VERIFIED' if self.storage.matches_observed(observed) else 'UNKNOWN')

class FixedStorage:
    def __init__(self,identity):
        self.identity=identity; self.pending_identity=None

    @staticmethod
    def safe_read(path):
        for item in [Path(path),*Path(path).parents]:
            s=item.lstat()
            if s.st_uid!=0 or s.st_mode&0o022 or stat.S_ISLNK(s.st_mode): raise Failure('config_owner')
        raw=Path(path).read_bytes()
        if len(raw)>131072: raise Failure('config_size')
        return raw

    def snapshot(self):
        return hashlib.sha256(self.safe_read(LIVE)).hexdigest()

    def stage(self,variant):
        source=ROOT+'/apache.reviewed.conf'
        raw=self.safe_read(source)
        if variant=='invalid': raw+=b'\nM6EIntentionallyInvalidDirective\n'
        fd=os.open(PENDING,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        self.pending_identity=os.fstat(fd)
        try:
            view=memoryview(raw)
            while view:
                n=os.write(fd,view)
                if n<=0: raise Failure('config_write')
                view=view[n:]
            os.fsync(fd)
        finally: os.close(fd)

    def install(self):
        now=Path(PENDING).lstat()
        if self.pending_identity is None or (now.st_dev,now.st_ino)!=(self.pending_identity.st_dev,self.pending_identity.st_ino):
            raise Failure('config_pending_identity')
        os.chmod(PENDING,0o644); os.replace(PENDING,LIVE); self.pending_identity=None
        fd=os.open(ROOT,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)

    def matches_observed(self,value):
        # Independent HTTPS identity plus a configuration nonce emitted by the isolated vhost.
        raw=self.safe_read(ROOT+'/apache.reviewed.conf')
        return self.snapshot()==hashlib.sha256(raw).hexdigest() and value['config_id']==self.identity['run_id'] and value['health']=='pass'

    def cleanup_pending(self):
        if self.pending_identity is not None:
            now=Path(PENDING).lstat()
            if (now.st_dev,now.st_ino)!=(self.pending_identity.st_dev,self.pending_identity.st_ino): raise Failure('config_cleanup_identity')
            Path(PENDING).unlink(); self.pending_identity=None
