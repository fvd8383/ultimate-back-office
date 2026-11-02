"""Actual ledger/save/teardown fault controls; native metadata/services remain modeled."""
import copy
import os
from pathlib import Path
import stat
import sys
import unittest
from contextlib import contextmanager,ExitStack
from unittest import mock

sys.path.insert(0,str(Path(__file__).resolve().parent))
import WebsitePlatformM6EBootstrapControlTest as control
from WebsitePlatformM6EBootstrapControlTest import Ledger,b,p,a,Failure

PRIMARY='LEDGER_SECRET_PRIMARY'
SECONDARY='LEDGER_SECRET_SECONDARY'

@contextmanager
def fault(fs,pending,point,close_failure=False):
    """Intercept syscalls around the actual Files.create; prefix bytes really reach disk."""
    create=fs.create;write=os.write;sync=os.fsync;close=os.close
    state=dict(fd=None,writes=0,bytes=None)
    error=OSError(PRIMARY);secondary=OSError(SECONDARY)
    def creating(path,raw,identified,outcome=None):
        def opened(fd):
            if path==pending:state['fd']=fd
            identified(fd)
            if path==pending and point=='empty':
                state['bytes']=fs.path(path).read_bytes();raise error
        return create(path,raw,opened,outcome)
    def writing(fd,raw):
        if fd!=state['fd']:return write(fd,raw)
        state['writes']+=1
        if point=='prefix':
            if state['writes']==1:
                n=write(fd,raw[:13]);state['bytes']=fs.path(pending).read_bytes();return n
            raise error
        if point=='zero':state['bytes']=fs.path(pending).read_bytes();return 0
        return write(fd,raw)
    def syncing(fd):
        if fd==state['fd'] and point=='sync':state['bytes']=fs.path(pending).read_bytes();raise error
        return sync(fd)
    def closing(fd):
        target=fd==state['fd'];close(fd)
        if target:
            state['fd']=None
            if close_failure:raise secondary
            if point=='close':raise error
    with ExitStack() as stack:
        for target,name,action in ((fs,'create',creating),(os,'write',writing),(os,'fsync',syncing),(os,'close',closing)):
            stack.enter_context(mock.patch.object(target,name,side_effect=action))
        yield state,error

class Recovery(unittest.TestCase):
    host=control.Controls.host
    armed=control.Controls.armed
    ready=control.Controls.ready

    def fresh(self):
        host=self.host();ledger=Ledger.create(host.fs,host.cfg);host.ledger=ledger
        return host,ledger

    def assert_private(self,ledger):
        raw=a.canonical(ledger.public())
        for text in (PRIMARY,SECONDARY,'SECRET_ADMIN_CANARY','SECRET_WRITER_CANARY','SECRET_AUTHORITY_CANARY'):
            self.assertNotIn(text.encode(),raw)
        self.assertLessEqual(len(ledger.state['secondary']),16)

    def test_successful_replace_and_bound_reload(self):
        host,ledger=self.fresh();old=ledger.disk.copy();ledger.phase('measured')
        self.assertNotEqual(old,ledger.disk);self.assertEqual(ledger.disk,host.fs.identity(ledger.path))
        loaded=Ledger.load(host.fs,host.cfg);self.assertEqual(loaded.state,ledger.state)
        self.assertFalse(host.fs.exists(ledger.path+'.pending'))
        for key in ('source_sha','run_id','machine_id','boot_id'):
            bad=copy.deepcopy(host.cfg);bad[key]='0'*len(bad[key])
            with self.assertRaises((ValueError,FileNotFoundError)):Ledger.load(host.fs,bad)

    def test_failure_after_creation_has_zero_bytes_and_later_save_recovers(self):
        self.creation_failure('empty')

    def test_real_prefix_zero_progress_and_file_sync_failures_recover(self):
        for point in ('prefix','zero','sync'):
            with self.subTest(point=point):self.creation_failure(point)

    def creation_failure(self,point):
        host,ledger=self.fresh();pending=ledger.path+'.pending';old=host.fs.read(ledger.path);identity=ledger.disk.copy()
        ledger.state['phase']='failed-attempt'
        with fault(host.fs,pending,point) as (observed,error):
            with self.assertRaises((OSError,ValueError)) as caught:ledger.save()
        if point!='zero':self.assertIs(caught.exception,error)
        self.assertEqual(observed['bytes'],b'' if point in ('empty','zero') else observed['bytes'])
        if point=='prefix':self.assertEqual(len(observed['bytes']),13);self.assertGreaterEqual(observed['writes'],2)
        if point=='sync':self.assertEqual(a.decode(observed['bytes'])['phase'],'failed-attempt')
        self.assertEqual(host.fs.read(ledger.path),old);self.assertEqual(ledger.disk,identity)
        self.assertFalse(host.fs.exists(pending));self.assertIsNotNone(ledger.state['first_failure'])
        ledger.phase('deliberate-recovery');loaded=Ledger.load(host.fs,host.cfg)
        self.assertEqual(loaded.state['phase'],'deliberate-recovery');self.assertEqual(loaded.state['binding'],p.base_identity(host.cfg))
        self.assertIsNotNone(loaded.state['first_failure']);self.assert_private(loaded)

    def test_close_error_alone_and_secondary_close_error_preserve_primary(self):
        for point,double in (('close',False),('prefix',True)):
            with self.subTest(point=point):
                host,ledger=self.fresh();old=host.fs.read(ledger.path)
                with fault(host.fs,ledger.path+'.pending',point,double) as (_,error):
                    with self.assertRaises(OSError) as caught:ledger.save()
                self.assertIs(caught.exception,error);self.assertEqual(host.fs.read(ledger.path),old)
                self.assertFalse(host.fs.exists(ledger.path+'.pending'))
                if double:self.assertIn('close',[x.get('stage') for x in ledger.state['secondary']])
                ledger.save();self.assert_private(Ledger.load(host.fs,host.cfg))

    def test_pending_cleanup_failure_is_secondary_and_never_success(self):
        host,ledger=self.fresh();pending=ledger.path+'.pending';old=host.fs.read(ledger.path)
        with fault(host.fs,pending,'prefix') as (_,error),mock.patch.object(host.fs,'unlink',side_effect=OSError(SECONDARY)):
            with self.assertRaises(OSError) as caught:ledger.save()
        self.assertIs(caught.exception,error);self.assertEqual(len(host.fs.read(pending)),13)
        self.assertEqual(host.fs.read(ledger.path),old)
        self.assertIn('pending-cleanup',[x.get('stage') for x in ledger.state['secondary']])
        with self.assertRaises(FileExistsError):ledger.save()
        self.assertEqual(len(host.fs.read(pending)),13);self.assert_private(ledger)

    def test_preexisting_pending_regular_directory_and_hardlink_are_preserved(self):
        for kind in ('regular','directory','hardlink'):
            with self.subTest(kind=kind):
                host,ledger=self.fresh();path=host.fs.path(ledger.path+'.pending');old=host.fs.read(ledger.path)
                if kind=='directory':path.mkdir()
                elif kind=='hardlink':os.link(host.fs.path(ledger.path),path)
                else:path.write_bytes(b'FOREIGN_PENDING')
                identity=host.fs.identity(ledger.path+'.pending')
                with self.assertRaises((FileExistsError,PermissionError,ValueError)):ledger.save()
                self.assertEqual(host.fs.identity(ledger.path+'.pending'),identity)
                self.assertEqual(host.fs.path(ledger.path).read_bytes(),old)

    def test_pending_substitution_or_unsafe_shape_is_preserved(self):
        for kind in ('replaced','hardlink','directory','socket','mode'):
            with self.subTest(kind=kind):
                host,ledger=self.fresh();pending=ledger.path+'.pending';old=host.fs.read(ledger.path);create=host.fs.create
                def created(path,raw,identified,outcome=None):
                    result=create(path,raw,identified,outcome)
                    if path==pending:
                        target=host.fs.path(path)
                        if kind in ('replaced','directory'):
                            target.rename(target.with_name('owned-retained'))
                            target.mkdir() if kind=='directory' else target.write_bytes(b'FOREIGN_REPLACEMENT')
                        elif kind=='hardlink':os.link(target,target.with_name('foreign-link'))
                        elif kind=='mode':host.fs.metadata(path,0,0,0o644)
                        else:
                            s=target.stat();host.fs.meta[s.st_dev,s.st_ino]=dict(uid=0,gid=0,mode=0o600,kind=stat.S_IFSOCK)
                    return result
                with mock.patch.object(host.fs,'create',side_effect=created),self.assertRaises(ValueError):ledger.save()
                self.assertTrue(host.fs.exists(pending));self.assertEqual(host.fs.read(ledger.path),old)
                self.assertIn('pending-cleanup',[x.get('stage') for x in ledger.state['secondary']])

    def test_descriptor_named_entry_disagreement_is_not_adopted(self):
        host,ledger=self.fresh();pending=ledger.path+'.pending';create=host.fs.create
        foreign=host.fs.path(ledger.root+'/foreign-descriptor');foreign.write_bytes(b'FOREIGN')
        def creating(path,raw,identified,outcome=None):
            def swapped(fd):
                # Windows forbids renaming this open writer. Model a mismatched handoff
                # using another real descriptor/inode; post-close swaps are tested above.
                if path==pending:
                    with foreign.open('rb') as other:identified(other.fileno())
                else:identified(fd)
            return create(path,raw,swapped,outcome)
        with mock.patch.object(host.fs,'create',side_effect=creating),self.assertRaisesRegex(ValueError,'created_descriptor_identity'):ledger.save()
        self.assertEqual(host.fs.read(pending),b'');self.assertEqual(foreign.read_bytes(),b'FOREIGN')

    def test_final_replacement_before_rename_is_preserved_even_with_same_json(self):
        host,ledger=self.fresh();old=host.fs.read(ledger.path);identity=ledger.disk.copy();create=host.fs.create
        def created(path,raw,identified,outcome=None):
            result=create(path,raw,identified,outcome)
            target=host.fs.path(ledger.path);target.rename(target.with_name('original-retained'));target.write_bytes(old)
            return result
        with mock.patch.object(host.fs,'create',side_effect=created),self.assertRaises(ValueError):ledger.save()
        self.assertEqual(host.fs.read(ledger.path),old);self.assertEqual(ledger.disk,identity)
        self.assertNotEqual(host.fs.identity(ledger.path),identity);self.assertFalse(host.fs.exists(ledger.path+'.pending'))

    def test_replace_failure_keeps_old_but_post_replace_failures_reconcile_exact_bytes(self):
        for point in ('rename','directory-sync','rename-ack','read-ack'):
            with self.subTest(point=point):
                host,ledger=self.fresh();old=host.fs.read(ledger.path);identity=ledger.disk.copy()
                ledger.state['phase']='new-complete-state';error=OSError(PRIMARY);replace=os.replace;read=host.fs.read;seen=[]
                def replaced(*args):
                    if point=='rename-ack':replace(*args)
                    raise error
                def reading(path,*args):
                    if path==ledger.path and not seen:seen.append(True);raise error
                    return read(path,*args)
                target,name,action=(host.fs,'sync_dir',mock.Mock(side_effect=error)) if point=='directory-sync' else (host.fs,'read',reading) if point=='read-ack' else (os,'replace',replaced)
                with mock.patch.object(target,name,side_effect=action),self.assertRaises(OSError) as caught:ledger.save()
                self.assertIs(caught.exception,error);self.assertFalse(host.fs.exists(ledger.path+'.pending'))
                observation=ledger.state['observations']['ledger_save_failure']
                self.assertEqual(observation['installed'],point!='rename');self.assertEqual(observation['durability'],'UNCONFIRMED')
                if point=='rename':self.assertEqual(host.fs.read(ledger.path),old);self.assertEqual(ledger.disk,identity)
                else:
                    self.assertEqual(a.decode(host.fs.read(ledger.path))['phase'],'new-complete-state')
                    self.assertNotEqual(ledger.disk,identity);self.assertEqual(ledger.disk,host.fs.identity(ledger.path))
                ledger.phase('later-save');self.assertEqual(Ledger.load(host.fs,host.cfg).state['phase'],'later-save')

    def test_post_replace_foreign_inode_or_content_is_never_reconciled(self):
        for kind in ('inode','content','hardlink'):
            with self.subTest(kind=kind):
                host,ledger=self.fresh();old=ledger.disk.copy();error=OSError(PRIMARY)
                def syncing(path):
                    target=host.fs.path(ledger.path)
                    if kind=='inode':
                        raw=target.read_bytes();target.rename(target.with_name('created-retained'));target.write_bytes(raw)
                    elif kind=='hardlink':os.link(target,target.with_name('foreign-link'))
                    else:target.write_bytes(b'FOREIGN_CONTENT')
                    raise error
                with mock.patch.object(host.fs,'sync_dir',side_effect=syncing),self.assertRaises(OSError) as caught:ledger.save()
                self.assertIs(caught.exception,error);self.assertEqual(ledger.disk,old)
                self.assertFalse(ledger.state['observations']['ledger_save_failure']['installed'])
                before=host.fs.path(ledger.path).read_bytes()
                with self.assertRaises(ValueError):ledger.save()
                self.assertEqual(host.fs.path(ledger.path).read_bytes(),before)

    def test_transient_save_then_real_journaled_teardown_and_repeated_replacement_denial(self):
        for point in ('empty','prefix'):
            with self.subTest(point=point):
                host,ledger,plan,_=self.ready();pending=ledger.path+'.pending'
                host.put('/var/lib/unrelated-proof',b'FOREIGN_UNTOUCHED')
                with fault(host.fs,pending,point),self.assertRaises(OSError):ledger.save()
                primary=copy.deepcopy(ledger.state['first_failure']);ledger.save()
                ledger=Ledger.load(host.fs,host.cfg);host.ledger=ledger;self.assertEqual(ledger.state['first_failure'],primary)
                execute=host.execute;stops=[];audit=len(host.fs.audit)
                def controlled(args,*rest,**kwargs):
                    if args[:2]==['/usr/bin/systemctl','stop']:
                        reports=list(host.fs.path(ledger.root).glob('before-teardown-*.json'));self.assertTrue(reports)
                        self.assertEqual(a.decode(reports[0].read_bytes())['first_failure'],primary)
                        if args[2] in p.STARTED:self.assertEqual(len(host.fs.audit),audit)
                        stops.append(args[2])
                    return execute(args,*rest,**kwargs)
                host.execute=controlled;result=b.teardown(host,ledger)
                self.assertEqual(result['result'],'REMOVED');self.assertEqual(stops,[*reversed(p.STARTED),'ubo-m6e-watchdog.timer'])
                self.assertFalse(host.accounts);self.assertTrue(all(x['state']=='RELEASED' for x in ledger.state['entries']))
                self.assertEqual(host.fs.read('/var/lib/unrelated-proof'),b'FOREIGN_UNTOUCHED')
                self.assertEqual(Ledger.load(host.fs,host.cfg).state['first_failure'],primary)
                audit=len(host.fs.audit);b.teardown(host,ledger);self.assertEqual(len(host.fs.audit),audit)
                host.put(p.CONFIG+'/mysql.cnf',b'REPLACEMENT')
                with self.assertRaises(Failure):b.teardown(host,ledger)
                self.assertEqual(host.fs.read(p.CONFIG+'/mysql.cnf'),b'REPLACEMENT');self.assert_private(ledger)

    def test_persistent_save_failure_limits_cleanup_without_bypassing_journaling(self):
        host,ledger,plan,_=self.ready();calls=len(host.calls);units=host.units.copy();pending=ledger.path+'.pending'
        with fault(host.fs,pending,'empty'),self.assertRaises(OSError):b.teardown(host,ledger)
        self.assertEqual(host.calls[calls:],[]);self.assertEqual(host.units,units)
        self.assertEqual(ledger.state['watchdog'],'ARMED');self.assertEqual(ledger.state['phase'],'cleanup-limited')
        self.assertTrue(host.accounts);self.assertTrue(all(x['state']!='RELEASED' for x in ledger.state['entries']))
        reports=list(host.fs.path(ledger.root).glob('teardown-*.json'));self.assertTrue(reports)
        self.assertTrue(all(a.decode(x.read_bytes())['result']=='LIMITED' for x in reports))
        self.assertFalse(host.fs.exists(pending));self.assert_private(ledger)

if __name__=='__main__':unittest.main(verbosity=2)
