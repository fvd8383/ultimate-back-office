"""Durable, identity-checked fixture ownership. Evidence lives outside removed roots."""
import copy
import os
from pathlib import Path, PurePosixPath
import stat

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need
from WebsitePlatformM6EProcess import publish_bytes
from WebsitePlatformM6EBootstrapPlan import EVIDENCE, owned_path, base_identity, PROFILE


class Files:
    """Native filesystem boundary. Tests inject a mapped private filesystem explicitly."""
    def path(self,path):return Path(path)
    def exists(self,path):return os.path.lexists(self.path(path))
    def read(self,path,limit=4194304):
        p=self.path(path);s=p.lstat()
        need(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_size<=limit,'base_read')
        fd=os.open(p,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_BINARY',0))
        try:
            before=os.fstat(fd);raw=os.read(fd,limit+1);after=os.fstat(fd)
            need((s.st_dev,s.st_ino)==(before.st_dev,before.st_ino) and self.signature(before)==self.signature(after)
                 and len(raw)==s.st_size,'base_read_replaced')
            return raw
        finally:os.close(fd)
    @staticmethod
    def signature(s):return [s.st_dev,s.st_ino,s.st_uid,s.st_gid,s.st_mode,s.st_size,s.st_mtime_ns,s.st_ctime_ns]
    def identity(self,path):
        s=self.path(path).lstat()
        need(not stat.S_ISLNK(s.st_mode),'base_symlink')
        return dict(dev=s.st_dev,ino=s.st_ino,uid=s.st_uid,gid=s.st_gid,kind=stat.S_IFMT(s.st_mode),mode=stat.S_IMODE(s.st_mode))
    def metadata(self,path,uid,gid,mode):
        os.chown(self.path(path),uid,gid,follow_symlinks=False);os.chmod(self.path(path),mode,follow_symlinks=False)
    def mkdir(self,path,mode):os.mkdir(self.path(path),mode)
    def unlink(self,path):self.path(path).unlink()
    def rmdir(self,path):self.path(path).rmdir()
    def sync_dir(self,path):
        if os.name=='nt':return  # Windows controls do not assert Linux directory durability.
        fd=os.open(self.path(path),os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
        try:os.fsync(fd)
        finally:os.close(fd)
    def create(self,path,raw,identified):
        fd=os.open(self.path(path),os.O_WRONLY|os.O_CREAT|os.O_EXCL|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_BINARY',0),0o600)
        try:
            identified()  # Durable identity before content writes can partially fail.
            view=memoryview(raw)
            while view:
                n=os.write(fd,view[:65536]);need(n>0,'base_write');view=view[n:]
            os.fsync(fd)
        finally:os.close(fd)
    def replace(self,path,raw,expected):
        pending=path+'.pending';created=None
        try:
            self.create(pending,raw,lambda:None);created=self.identity(pending)
            need(self.identity(path)==expected,'ledger_replaced')
            os.replace(self.path(pending),self.path(path));self.sync_dir(str(PurePosixPath(path).parent))
        finally:
            if created is not None and self.exists(pending) and self.identity(pending)==created:self.unlink(pending)
    def inventory(self,path):
        root=self.path(path);items={};base=self.identity(path)
        for directory,dirs,files in os.walk(root,followlinks=False):
            for name in dirs+files:
                p=Path(directory)/name;relative=p.relative_to(root).as_posix();full=path+'/'+relative
                info=self.identity(full)
                need(info['dev']==base['dev'] and info['kind'] in (stat.S_IFDIR,stat.S_IFREG,stat.S_IFSOCK),'base_tree_type')
                if info['kind']==stat.S_IFREG:need(p.lstat().st_nlink==1,'base_tree_hardlink')
                items[relative]=info;need(len(items)<=12000,'base_tree_bound')
        return items


class Ledger:
    def __init__(self,files,config,state=None):
        self.fs=files;self.config=config;self.root=EVIDENCE+'/'+config['run_id'];self.path=self.root+'/ownership.json'
        self.state=state or dict(profile=PROFILE+'-ownership',version=1,binding=base_identity(config),phase='admitted',
            entries=[],accounts=[],services=[],commands=[],watchdog='UNARMED',first_failure=None,secondary=[],sequence=0,
            admission=config,observations={})
        self.disk=None

    @classmethod
    def create(cls,files,config):
        ledger=cls(files,config)
        if not files.exists(EVIDENCE):files.mkdir(EVIDENCE,0o700)
        root=files.identity(EVIDENCE)
        need(root['kind']==stat.S_IFDIR and root['uid']==0 and root['mode']==0o700,'evidence_parent')
        need(not files.exists(ledger.root),'existing_fixture_evidence')
        files.mkdir(ledger.root,0o700)
        ledger.state['evidence_identity']=files.identity(ledger.root)
        files.create(ledger.path,canonical(ledger.state),lambda:None)
        ledger.disk=files.identity(ledger.path);files.sync_dir(ledger.root)
        return ledger

    @classmethod
    def load(cls,files,config):
        ledger=cls(files,config);state=decode(files.read(ledger.path))
        need(state['profile']==PROFILE+'-ownership' and state['binding']==base_identity(config)
             and state['admission']==config,'ledger_binding')
        ledger.state=state;ledger.disk=files.identity(ledger.path)
        ledger.check_evidence()
        need(ledger.disk['uid']==0 and ledger.disk['mode']==0o600,'ledger_access')
        need(len(state['entries'])<=2048 and len(state['commands'])<=2048,'ledger_bound')
        for row in state['entries']:need(owned_path(row['path']),'ledger_path')
        return ledger

    def check_evidence(self):
        parent=self.fs.identity(EVIDENCE);root=self.fs.identity(self.root)
        need(parent['kind']==stat.S_IFDIR and parent['uid']==0 and parent['mode']==0o700
             and root==self.state['evidence_identity'] and root['uid']==0 and root['mode']==0o700,'evidence_identity')

    def save(self):
        self.check_evidence()
        self.state['sequence']+=1;raw=canonical(self.state);need(len(raw)<=4194304,'ledger_bound')
        self.fs.replace(self.path,raw,self.disk);self.disk=self.fs.identity(self.path)

    def phase(self,name):self.state['phase']=name;self.save()

    def intent(self,path,kind,producer=None):
        need(owned_path(path) and not self.fs.exists(path) and not any(x['path']==path for x in self.state['entries']),'base_collision')
        row=dict(id=len(self.state['entries']),path=path,kind=kind,state='INTENT',identity=None,sha256=None,children={},retired={},sealed=False,producer=producer)
        self.state['entries'].append(row);self.save();return row

    def identify(self,row):
        row.update(identity=self.fs.identity(row['path']),state='CREATED');self.save()

    def directory(self,path,uid=0,gid=0,mode=0o755,producer=None):
        row=self.intent(path,'tree' if producer else 'directory',producer)
        self.fs.mkdir(path,0o700);self.identify(row)
        self.fs.metadata(path,uid,gid,mode);self.identify(row);return row

    def write(self,path,raw,uid=0,gid=0,mode=0o644):
        row=self.intent(path,'file')
        self.fs.create(path,raw,lambda:self.identify(row))
        self.fs.metadata(path,uid,gid,mode);self.identify(row)
        need(self.fs.read(path)==raw,'base_write_readback');row['sha256']=digest(raw);self.save();return row

    def generated(self,path,producer):return self.intent(path,'file',producer)

    def capture_tree(self,row,allowed_uids,final=False):
        need(self.fs.identity(row['path'])==row['identity'],'owned_tree_root')
        found=self.fs.inventory(row['path'])
        for relative,old in row['children'].items():
            # Disappearing runtime files may be removed by their recorded producer; replacements may not.
            if relative in found:need(found[relative]==old,'owned_child_replaced')
            else:row['retired'][relative]=old
        explicit={x['path'][len(row['path'])+1:]:x['identity'] for x in self.state['entries'] if x['path'].startswith(row['path']+'/') and x['state']=='CREATED'}
        for relative,identity in found.items():
            need(relative not in row['retired'],'retired_child_replaced')
            if row['sealed'] and relative not in row['children']:
                need(explicit.get(relative)==identity,'unowned_new_child')
        need(all(x['uid'] in allowed_uids for x in found.values()),'foreign_tree_object')
        row['children']=found;row['sealed']=row['sealed'] or final;self.save()

    def verify(self,row,content=True):
        need(row['identity'] is not None and self.fs.identity(row['path'])==row['identity'],'owned_resource_replaced')
        if content and row['sha256'] is not None:need(digest(self.fs.read(row['path']))==row['sha256'],'owned_content_changed')

    def release(self,row):
        if row['state']=='RELEASED':
            need(not self.fs.exists(row['path']),'replacement_after_release');return
        if not self.fs.exists(row['path']):row['state']='RELEASED';self.save();return
        self.verify(row)
        if row['kind']=='tree':
            actual=self.fs.inventory(row['path'])
            removed={x['path'][len(row['path'])+1:] for x in self.state['entries'] if x['state']=='RELEASED' and x['path'].startswith(row['path']+'/')}
            expected={k:v for k,v in row['children'].items() if k not in removed}
            need(actual==expected,'unidentified_tree_objects')
            for relative,identity in sorted(actual.items(),key=lambda x:x[0].count('/'),reverse=True):
                path=row['path']+'/'+relative;need(self.fs.identity(path)==identity,'cleanup_child_replaced')
                self.fs.rmdir(path) if identity['kind']==stat.S_IFDIR else self.fs.unlink(path)
        if row['kind'] in ('tree','directory'):self.fs.rmdir(row['path'])
        else:self.fs.unlink(row['path'])
        row['state']='RELEASED';self.save()

    def public(self):
        return dict(profile=PROFILE+'-diagnostic',binding=self.state['binding'],phase=self.state['phase'],
            first_failure=copy.deepcopy(self.state['first_failure']),secondary=copy.deepcopy(self.state['secondary']),
            watchdog=self.state['watchdog'],resources=[dict(id=r['id'],kind=r['kind'],state=r['state'],
                identity_sha256=digest(canonical(r['identity'])),children=len(r['children'])) for r in self.state['entries']],
            observations=copy.deepcopy(self.state['observations']),
            accounts=[{k:a[k] for k in ('role','state')} for a in self.state['accounts']],
            services=copy.deepcopy(self.state['services']),commands=copy.deepcopy(self.state['commands']))

    def publish(self,stem,value):
        self.check_evidence();return publish_bytes(self.fs.path(self.root),stem,canonical(value))

    def failure(self,error,kind):
        from WebsitePlatformM6EAcceptance import safe_error
        value=dict(kind=kind,phase=self.state['phase'],**safe_error(error))
        if self.state['first_failure'] is None:self.state['first_failure']=value
        elif len(self.state['secondary'])<16:self.state['secondary'].append(value)
        try:self.save()
        except BaseException:pass  # In-memory primary still survives a disk failure.
