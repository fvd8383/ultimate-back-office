#!/usr/bin/python3 -I
"""Disposable M6E acceptance coordinator; native execution is not commissioned.

The exact 13-case inventory is retained. Missing native implementation blocks before
infrastructure effects. Component functions below remain explicitly separate evidence.
"""
from contextlib import contextmanager
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import signal
import stat
import subprocess
import sys
import time
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tests/support'))
from WebsitePlatformM6EProcess import Owner
from WebsitePlatformM6EDrivers import DRIVERS, dispatch
spec = importlib.util.spec_from_file_location('m6e', ROOT / 'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)
INVENTORY = ('stage_replay_retention', 'link_hardlink_special_path_rejection', 'fence_and_owned_lock',
             'activation_restore_compensation', 'first_deployment_absence', 'authority_change_before_rename', 'kill_after_intent', 'kill_after_rename',
             'stage_storage_fault', 'journal_fsync_fault', 'apache_private_health_headers', 'config_failure_preservation',
             'worker_helper_authority_integration')
ADMISSION = '/run/ubo-m6e-fixture/admission.json'


def capture(command, timeout=30, expected_stderr=b''):
    """Bounded capture: retain first child result before teardown; no shell."""
    owner=Owner()
    try:
        result=owner.capture(command,timeout)
        p.require(result['stderr']==expected_stderr and result['exit']==0)
        return result['stdout']
    finally:owner.cleanup()


class Authority:
    def __init__(self, value):
        self.value = value
        self.hook = None

    def observe(self, request):
        if self.hook:
            self.hook()
        return json.loads(json.dumps(self.value))


def fork_owned(work):
    pid = os.fork()
    if pid == 0:
        try:
            work()
            os._exit(0)
        except BaseException:
            # Never enter coordinator cleanup from a forked worker.
            os.write(2, b'isolated_child_failed\n')
            os._exit(90)
    return pid


def wait_owned(pid, seconds=30):
    end = time.monotonic() + seconds
    while time.monotonic() < end:
        child, status = os.waitpid(pid, os.WNOHANG)
        if child:
            return status
        time.sleep(0.01)
    raise RuntimeError('owned_child_timeout:' + str(pid))


class ComponentPublisher(p.Publisher):
    def __init__(self, source, served, journal, authority, binding):
        super().__init__(served, journal, authority, binding)
        self.fixture_source = source

    @contextmanager
    def receive_package(self, a, r, channel, transfer_id):
        # Explicit single-identity component adapter, never constructed by production main.
        yield self.fixture_source, a['release']['site_key'] + '/' + a['release']['release_key']


class Fixture:
    def __init__(self, admission, exported):
        self.source=self.served=self.journal=None
        self.may_cleanup=False
        try:self._initialize(admission,exported)
        except BaseException:
            try:self.cleanup()
            except BaseException:pass  # Preserve construction failure; never adopt unknown roots.
            finally:
                for tree in (self.source,self.served,self.journal):
                    if tree is not None:
                        try:tree.close()
                        except BaseException:pass
            raise

    def _initialize(self, admission, exported):
        self.admission, self.exported = admission, exported
        self.ledger = []
        self.children = []
        self.publication_observations = []
        self.publication_step = None
        self.transfer_observations = None
        self.pub = None
        self.seq = 0
        uid, gid = os.geteuid(), os.getegid()
        self.source = p.Tree(admission['source_root'], uid, True, gid)
        self.served = p.Tree(admission['served_root'], uid, True, gid)
        self.journal = p.Tree(admission['journal_root'], uid, True)
        for tree in (self.source, self.served, self.journal):
            p.require(os.listdir(tree.fd) == [])
        self.may_cleanup=True
        self.release = exported['release']
        self.binding = {'site_id': self.release['site_id'], 'target_id': 20, 'site_key': self.release['site_key'], 'binding_key': 'isolated', 'binding_version': 1}
        self.target = {k: self.binding[k] for k in ('site_id', 'target_id', 'binding_key', 'binding_version')} | {
            'environment': 'staging', 'publisher_key': 'apache-digitalocean-v1'}
        self.lease = dict(site_id=self.release['site_id'], target_id=20, deployment_id=100, attempt_id=101, attempt_kind='execution',
                          worker_id='isolated', environment='staging', token='a'*64, fence_epoch=1)
        self.a = self.binding | dict(deployment_key='11111111-1111-4111-8111-111111111111', deployment_id=100, attempt_id=101,
            producer_attempt_id=101, attempt_kind='execution', fence_epoch=1, operation='publish', status='running', outcome='uncommitted', live=True,
            permitted=['stage', 'inspect', 'probe'], activation_fence=None, release=self.release, previous=None, expires_in=120)
        self.authority = Authority(self.a)
        self.source_path = self.release['site_key'] + '/' + self.release['release_key']
        for path, data in exported['files'].items():
            self.source.write_new(self.source_path + '/' + path, base64.b64decode(data))
        for name in ('releases', 'staging', 'assets'):
            os.mkdir(name, 0o700 if name == 'staging' else 0o750, dir_fd=self.served.fd)
        self.journal.write_new('target.lock', b'')
        self.journal.write_new('state.json', p.canonical(dict(version=1, fence=0, owner=None, session=None, phase=0, current=None, intent=None, settled=True)))
        self.pub = ComponentPublisher(self.source, self.served, self.journal, self.authority, self.binding)

    def call(self, op, payload=None):
        self.seq += 1
        return self.pub.dispatch(dict(version=1, sequence=self.seq, operation=op, target=self.target, lease=self.lease.copy(), payload=payload or {}))

    def context(self, phase):
        # Actual Publisher only uses committed authority identity; full wire schema is
        # separately exercised by the desktop protocol suite and installed integration gate.
        return dict(deployment_key=self.a['deployment_key'], producer_attempt_id=self.a['producer_attempt_id'], operation=self.a['operation'],
                    release_key=self.release['release_key'], artifact_hash=self.release['artifact_hash'], phase=phase)

    def expected(self, current=None):
        return {'site_key': self.release['site_key']} | {k: None if current is None else current[k] for k in ('release_id', 'release_key', 'artifact_hash', 'deployment_id')}

    def stage(self):
        return self.call('stage', dict(release=self.release, context=self.context('stage')))

    def activate(self):
        staged = self.stage()
        self.a.update(status='activation_attempted', activation_fence=self.lease['fence_epoch'], permitted=['activate', 'inspect', 'probe'])
        return self.call('activate', dict(staged=staged, expected=self.expected(self.a['previous']), context=self.context('activation')))

    def replacement(self, deployment, attempt, fence, kind, producer):
        if self.pub.lock is not None:
            os.close(self.pub.lock)
        self.pub = ComponentPublisher(self.source, self.served, self.journal, self.authority, self.binding)
        self.seq = 0
        self.lease.update(deployment_id=deployment, attempt_id=attempt, fence_epoch=fence, attempt_kind=kind, token=os.urandom(32).hex())
        self.a.update(deployment_id=deployment, attempt_id=attempt, fence_epoch=fence, attempt_kind=kind, producer_attempt_id=producer, live=True)

    def compensate(self):
        observed = self.call('inspect')
        return self.call('compensate', dict(context=self.context('compensation'), intent=dict(action='compensate',
            original_execution_id=self.a['producer_attempt_id'], observed=observed, expected_previous=self.expected(self.a['previous']),
            activation_fence=self.a['activation_fence'])))

    def cleanup(self):
        # Snapshot the exact ledger before teardown; no glob/root cleanup and no unknown files.
        for child in self.children:
            try:
                pid, status = os.waitpid(child, os.WNOHANG)
                if not pid:
                    os.kill(child, signal.SIGKILL)
                    wait_owned(child, 5)
            except ChildProcessError:
                pass
        if self.pub and self.pub.lock is not None:
            os.close(self.pub.lock)
            self.pub.lock = None
        for name in (('source_root', 'served_root', 'journal_root') if self.may_cleanup else ()):
            root = Path(self.admission[name])
            p.require(root.parent == Path(self.admission['fixture_root']) and not root.is_symlink())
            tree={'source_root':self.source,'served_root':self.served,'journal_root':self.journal}[name]
            pinned=os.fstat(tree.fd);named=root.lstat()
            p.require((pinned.st_dev,pinned.st_ino)==(named.st_dev,named.st_ino))
            inventory = sorted(root.rglob('*'), key=lambda x: len(x.parts), reverse=True)
            self.ledger += [str(f.relative_to(root)) for f in inventory]
            for f in inventory:
                p.require(f.is_relative_to(root) and f.lstat().st_uid == os.geteuid())
                if not f.is_symlink() and f.is_dir():
                    f.chmod(0o700)
            for f in inventory:
                p.require(f.is_relative_to(root) and f.lstat().st_uid == os.geteuid())
                if f.is_symlink() or not f.is_dir():
                    f.unlink()
                else:
                    f.chmod(0o700)
                    f.rmdir()
            p.require(list(root.iterdir()) == [])
        for tree in (self.source, self.served, self.journal):
            if tree is not None:tree.close()


def reject(call):
    try:
        call()
    except (p.Denied, OSError):
        return
    raise AssertionError('negative_probe_did_not_reject')


def immutable_boundaries(f):
    """Nested storage-boundary observations; existing acceptance inventory unchanged.

    Actual Linux file/dirfd/rename/fsync/kill paths with explicitly injected API faults.
    No claim to simulate kernel power loss or the installed identity boundary.
    """
    for served, tree in ((True, f.served), (False, f.journal)):
        kind = 'asset' if served else 'manifest'
        def object_(label):
            f.publication_step = kind + ':' + label
            data = ('synthetic immutable ' + f.publication_step).encode()
            path = 'assets/' + p.digest(data) + '.css' if served else 'manifests/55555555-5555-4555-8555-' + os.urandom(6).hex() + '.json'
            return path, data
        def passed():
            p.require(f.served.link() is None and f.served.link('candidate') is None)
            f.publication_observations.append({'step': f.publication_step, 'result': 'PASS'})
        path, data = object_('complete-replay')
        tree.publish_immutable(path, data, served)
        tree.publish_immutable(path, data, served)
        p.require(tree.read(path, len(data)) == data)
        passed()
        for fault in ('partial-write', 'file-sync', 'chmod', 'publish') + (('chown',) if served else ()):
            path, data = object_(fault)
            reached = [False]
            original_write, original_sync = os.write, os.fsync
            def partial(fd, content):
                if not reached[0]:
                    reached[0] = True
                    return original_write(fd, content[:3])
                raise OSError('injected_partial_write')
            def fail(*args):
                reached[0] = True
                raise OSError('injected_publication_api')
            def sync(fd):
                if stat.S_ISREG(os.fstat(fd).st_mode):
                    fail()
                original_sync(fd)
            owner = p.Tree if fault == 'publish' else os
            attribute = {'partial-write': 'write', 'file-sync': 'fsync', 'chmod': 'fchmod', 'chown': 'fchown', 'publish': '_rename_noreplace'}[fault]
            replacement = partial if fault == 'partial-write' else sync if fault == 'file-sync' else fail
            with mock.patch.object(owner, attribute, replacement):
                reject(lambda: tree.publish_immutable(path, data, served))
            p.require(reached[0] and not tree.exists(path))
            tree.publish_immutable(path, data, served)
            p.require(tree.read(path, len(data)) == data)
            passed()
        for matches in (True, False):
            path, data = object_('matching-winner' if matches else 'conflicting-winner')
            winner = data if matches else b'partial'
            rename = p.Tree._rename_noreplace
            def race(src, sn, dst, dn):
                tree.write_new(path, winner, served)
                rename(src, sn, dst, dn)
            with mock.patch.object(p.Tree, '_rename_noreplace', staticmethod(race)):
                if matches:
                    tree.publish_immutable(path, data, served)
                else:
                    reject(lambda: tree.publish_immutable(path, data, served))
            p.require(tree.read(path, len(data)) == winner)
            passed()
        path, data = object_('post-rename-directory-sync')
        published = [False]
        rename, sync = p.Tree._rename_noreplace, os.fsync
        def publish(src, sn, dst, dn):
            rename(src, sn, dst, dn)
            published[0] = True
        def uncertain(fd):
            if published[0] and stat.S_ISDIR(os.fstat(fd).st_mode):
                raise OSError('injected_post_rename_sync')
            sync(fd)
        with mock.patch.object(p.Tree, '_rename_noreplace', staticmethod(publish)), mock.patch.object(os, 'fsync', uncertain):
            reject(lambda: tree.publish_immutable(path, data, served))
        p.require(published[0] and tree.read(path, len(data)) == data)
        tree.publish_immutable(path, data, served)
        passed()
        path, data = object_('sigkill-private-prefix')
        private = tree.directory('staging') if served else os.dup(tree.fd)
        try:
            before = set(os.listdir(private))
            def interrupted():
                original_write = os.write
                def kill(fd, content):
                    original_write(fd, content[:3])
                    os.kill(os.getpid(), signal.SIGKILL)
                with mock.patch.object(os, 'write', kill):
                    tree.publish_immutable(path, data, served)
            pid = fork_owned(interrupted); f.children.append(pid)
            status = wait_owned(pid)
            p.require(os.WIFSIGNALED(status) and os.WTERMSIG(status) == signal.SIGKILL and not tree.exists(path))
            leftovers = set(os.listdir(private)) - before
            p.require(len(leftovers) == 1 and next(iter(leftovers)).startswith('immutable-'))
            tree.publish_immutable(path, data, served)
            p.require(leftovers.issubset(os.listdir(private)) and tree.read(path, len(data)) == data)
            # Finally cleanup did not run in the killed child. Only fixture teardown owns these leftovers.
        finally:
            os.close(private)
        passed()


def run_case(name, f, backend=None, recorder=None):
    if name in DRIVERS:
        p.require(backend is not None and recorder is not None)
        return dispatch(name,backend,recorder)
    if name == 'stage_replay_retention':
        one = f.stage()
        p.require(f.stage() == one and f.served.link() is None)
        m = f.pub.verify_served(p.identity(f.a))
        for entry in m['files']:
            if entry['relative_path'].startswith('public/assets/'):
                p.require(p.digest(f.served.read(entry['relative_path'][7:], entry['byte_size'])) == entry['sha256'])
    elif name == 'link_hardlink_special_path_rejection':
        p.verify_package(f.source, f.source_path, f.release)  # Valid positive baseline.
        parent = f.source.directory(f.source_path + '/public')
        try:
            os.symlink('/etc/passwd', 'unexpected', dir_fd=parent)
            reject(lambda: p.verify_package(f.source, f.source_path, f.release))
            os.unlink('unexpected', dir_fd=parent)
            os.link('index.html', 'unexpected', src_dir_fd=parent, dst_dir_fd=parent)
            reject(lambda: f.source.read(f.source_path + '/public/index.html', 1048576))
            os.unlink('unexpected', dir_fd=parent)
            os.mkfifo('unexpected', 0o600, dir_fd=parent)
            reject(lambda: p.verify_package(f.source, f.source_path, f.release))
            os.unlink('unexpected', dir_fd=parent)
            p.verify_package(f.source, f.source_path, f.release)
        finally:
            os.close(parent)
    elif name == 'fence_and_owned_lock':
        f.stage()
        def child_work():
            # Drop inherited ownership before proving another descriptor cannot acquire.
            os.close(f.pub.lock)
            child = ComponentPublisher(f.source, f.served, f.journal, f.authority, f.binding)
            try:
                child.acquire()
            except BlockingIOError:
                os._exit(0)
            os._exit(9)
        pid = fork_owned(child_work)
        f.children.append(pid)
        status = wait_owned(pid)
        p.require(os.waitstatus_to_exitcode(status) == 0)
        reject(lambda: p.fence(f.pub.state, f.a | {'fence_epoch': 0}, 'stage', f.pub.session))
        reject(lambda: p.fence(f.pub.state, f.a | {'attempt_id': 999}, 'stage', f.pub.session))
    elif name == 'activation_restore_compensation':
        f.activate()
        p.require(f.served.link() == f.release['release_key'])
        known_good = p.identity(f.a)
        f.a.update(outcome='succeeded', live=False)
        p.require(f.call('settle')['settled'])
        f.replacement(200, 201, 2, 'execution', 201)
        f.a.update(operation='restore', status='running', outcome='uncommitted', permitted=['stage', 'inspect', 'probe'], previous=known_good,
                   deployment_key='33333333-3333-4333-8333-333333333333', activation_fence=None)
        staged = f.stage()
        f.a.update(status='activation_attempted', activation_fence=2, permitted=['restore', 'inspect', 'probe'])
        f.call('restore', dict(staged=staged, expected=f.expected(known_good), context=f.context('activation')))
        p.require(f.call('inspect')['deployment_id'] == 200)
        f.replacement(200, 202, 3, 'recovery', 201)
        f.a.update(status='reconciliation_required', permitted=['compensate', 'inspect', 'probe'])
        f.compensate()
        p.require(f.call('inspect')['deployment_id'] == 100)
    elif name == 'first_deployment_absence':
        f.activate()
        f.replacement(100, 102, 2, 'recovery', 101)
        f.a.update(status='reconciliation_required', permitted=['compensate', 'inspect', 'probe'])
        f.compensate()
        p.require(f.served.link() is None and f.call('inspect')['state'] == 'absent')
    elif name == 'authority_change_before_rename':
        staged = f.stage()
        f.a.update(status='activation_attempted', activation_fence=1, permitted=['activate'])
        calls = [0]
        def revoke():
            calls[0] += 1
            if calls[0] == 2:
                f.a['live'] = False
        f.authority.hook = revoke
        reject(lambda: f.call('activate', dict(staged=staged, expected=f.expected(), context=f.context('activation'))))
        p.require(f.served.link() is None and f.pub.state['intent'] is not None)
    elif name in ('kill_after_intent', 'kill_after_rename'):
        # Child death exercises the actual directory/journal/link window, not power loss.
        def child_work():
            f.stage()
            original = f.pub.persist
            def killpoint():
                original()
                matched = name == 'kill_after_intent' and f.pub.state['phase'] == 2 and f.pub.state['intent'] is not None
                if matched:
                    os.kill(os.getpid(), signal.SIGKILL)
            f.pub.persist = killpoint
            original_switch = f.served.switch
            def switchpoint(before, after, name='current'):
                original_switch(before, after, name)
                if name == 'current':
                    os.kill(os.getpid(), signal.SIGKILL)
            if name == 'kill_after_rename':
                f.served.switch = switchpoint
            f.a.update(status='activation_attempted', activation_fence=1, permitted=['activate'])
            f.call('activate', dict(staged={}, expected=f.expected(), context=f.context('activation')))
            os._exit(9)
        pid = fork_owned(child_work)
        f.children.append(pid)
        status = wait_owned(pid)
        p.require(os.WIFSIGNALED(status) and os.WTERMSIG(status) == signal.SIGKILL)
        state = p.decode(f.journal.read('state.json', 65536))
        p.require(not state['settled'] and state['fence'] == 1)
        p.require(f.served.link() == (None if name == 'kill_after_intent' else f.release['release_key']))
        f.pub.acquire()  # Dead process relinquished its actual flock.
    elif name in ('stage_storage_fault', 'journal_fsync_fault'):
        p.verify_package(f.source, f.source_path, f.release)
        # API fault injection is explicitly distinct from a failed kernel syscall.
        tree = f.served if name == 'stage_storage_fault' else f.journal
        method = 'write_new' if name == 'stage_storage_fault' else 'replace_json'
        original = getattr(tree, method)
        def fail(*args, **kwargs):
            raise OSError('injected-storage-api-fault')
        setattr(tree, method, fail)
        try:
            reject(f.stage)
            p.require(f.served.link() is None)
        finally:
            setattr(tree, method, original)
        if name == 'stage_storage_fault':
            immutable_boundaries(f)
    else:
        raise ValueError('unknown_component_case')
    return 'PASS'


def main():
    from WebsitePlatformM6EAcceptance import PROFILE, OBSERVATIONS
    from WebsitePlatformM6ECoordinator import native_main
    if len(sys.argv) == 2 and sys.argv[1] == '--inventory':
        print(json.dumps({'profile': PROFILE, 'cases': INVENTORY, 'observations':OBSERVATIONS,'execution': 'NOT RUN'}))
        return 0
    p.require(sys.platform == 'linux' and sys.argv[1:] == ['--run'])
    return native_main()


if __name__ == '__main__':
    try:raise SystemExit(main())
    except (ValueError,RuntimeError,OSError) as error:
        # Safe failure code; no raw exception text, paths or private child output.
        print(json.dumps(dict(result='BLOCKED',code=type(error).__name__,missing=getattr(error,'context',{}).get('missing',[]))))
        raise SystemExit(1)
