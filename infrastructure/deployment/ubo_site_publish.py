"""M6E restricted Linux publisher. Standard library only; never shells out or writes SQL.

All mutating paths are relative to pinned directory descriptors. The OS supervisor
owns the process lifetime; flock spans staging, active health and database settlement.
No EOF/timeout/error path rolls back a website. Recovery requires fresh DB authority.
"""
import ctypes
import errno
import hashlib
import json
import os
import re
import select
import socket
import stat
import struct
import sys
import time

MAX_REQUEST = 16384
MAX_RESPONSE = 1048576
MAX_PACKAGE = 104857600
UUID = re.compile(r'^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
HASH = re.compile(r'^[0-9a-f]{64}$')
KEY = re.compile(r'^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,190}$')
CORRELATION = re.compile(r'[A-Za-z0-9._:-]{1,100}')
PHASES = {'stage': 1, 'activate': 2, 'restore': 2, 'compensate': 3}
CONFIG = '/etc/ubo-site-publisher/helper.json'


class Denied(Exception):
    """Never emit underlying paths, DB diagnostics, tokens or content."""


def require(ok):
    if not ok:
        raise Denied('publisher_unresolved')


def keys(value, names):
    require(isinstance(value, dict) and set(value) == set(names.split()))


def canonical(value):
    # Inventories contain only ASCII keys/paths and integers. Raw M6C manifest
    # bytes are hashed directly, avoiding a second cross-language serializer.
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()


def digest(data):
    return hashlib.sha256(data).hexdigest()


def decode(data, limit=MAX_RESPONSE):
    require(isinstance(data, bytes) and len(data) <= limit)
    def pairs(items):
        out = {}
        for k, v in items:
            require(k not in out)
            out[k] = v
        return out
    try:
        return json.loads(data, object_pairs_hook=pairs, parse_constant=lambda _: require(False))
    except (ValueError, UnicodeError):
        raise Denied('publisher_unresolved') from None


def positive(value):
    require(type(value) is int and 0 < value <= 9007199254740991)


def machine(value, pattern):
    require(isinstance(value, str) and pattern.fullmatch(value) is not None)


def relative(path):
    require(isinstance(path, str) and len(path) <= 512 and not path.startswith('/')
            and all(re.fullmatch(r'[a-zA-Z0-9_-][a-zA-Z0-9_.-]*', p) and p not in ('.', '..') for p in path.split('/')))
    return path.split('/')


def request(data):
    r = decode(data, MAX_REQUEST)
    keys(r, 'version sequence operation target lease payload')
    require(r['version'] == 1 and r['operation'] in ('stage', 'activate', 'restore', 'inspect', 'probe', 'compensate', 'settle'))
    positive(r['sequence'])
    t, lease = r['target'], r['lease']
    keys(t, 'site_id target_id environment publisher_key binding_key binding_version')
    keys(lease, 'site_id target_id deployment_id attempt_id attempt_kind worker_id environment token fence_epoch')
    for k in ('site_id', 'target_id', 'binding_version'):
        positive(t[k])
    for k in ('site_id', 'target_id', 'deployment_id', 'attempt_id', 'fence_epoch'):
        positive(lease[k])
    require(t['environment'] == lease['environment'] == 'staging' and t['publisher_key'] == 'apache-digitalocean-v1'
            and t['site_id'] == lease['site_id'] and t['target_id'] == lease['target_id']
            and lease['attempt_kind'] in ('execution', 'recovery'))
    machine(t['binding_key'], KEY)
    machine(lease['worker_id'], KEY)
    machine(lease['token'], HASH)
    shapes = {'stage': 'release context', 'activate': 'staged expected context', 'restore': 'staged expected context',
              'inspect': '', 'probe': 'expected context', 'compensate': 'intent context', 'settle': ''}
    keys(r['payload'], shapes[r['operation']])
    if 'context' in r['payload']:
        c = r['payload']['context']
        keys(c, 'site_id target_id deployment_id deployment_key attempt_id attempt_kind producer_attempt_id worker_id environment '
                'lease_token lease_expires_at deadline_at fence_epoch binding_version release_key artifact_hash operation phase '
                'phase_timeout_seconds connect_timeout_seconds request_timeout_seconds correlation_id')
        for k in ('site_id', 'target_id', 'deployment_id', 'attempt_id', 'attempt_kind', 'worker_id', 'environment', 'fence_epoch'):
            require(c[k] == lease[k])
        require(c['lease_token'] == lease['token'] and c['binding_version'] == t['binding_version']
                and c['phase_timeout_seconds'] == 30 and c['connect_timeout_seconds'] == 2 and c['request_timeout_seconds'] == 5)
        for k in ('deployment_key', 'release_key'):
            machine(c[k], UUID)
        machine(c['correlation_id'], CORRELATION)
        machine(c['artifact_hash'], HASH)
        require(c['operation'] in ('publish', 'restore'))
        phases = {'stage': ('stage',), 'activate': ('activation',), 'restore': ('activation',),
                  'compensate': ('compensation',), 'probe': ('candidate', 'active', 'rollback', 'reconcile')}
        require(c['phase'] in phases[r['operation']])
    p = r['payload']
    if 'release' in p:
        keys(p['release'], 'site_id site_key release_id release_key artifact_hash manifest_hash build_profile')
    if 'staged' in p:
        keys(p['staged'], 'phase result deployment_id attempt_id fence_epoch binding_version release_key artifact_hash previous_release_key external_reference')
        require(p['staged']['phase'] == 'stage' and p['staged']['result'] == 'pass')
        for k in ('deployment_id', 'attempt_id', 'fence_epoch', 'binding_version', 'release_key', 'artifact_hash'):
            require(p['staged'][k] == p['context'][k])
    if 'expected' in p:
        keys(p['expected'], 'site_key release_id release_key artifact_hash deployment_id')
    if 'intent' in p:
        keys(p['intent'], 'action original_execution_id observed expected_previous activation_fence')
        require(p['intent']['action'] == 'compensate')
        keys(p['intent']['observed'], 'state site_id target_id binding_version release_key artifact_hash deployment_id producer_attempt_id fence_epoch integrity external_reference')
        keys(p['intent']['expected_previous'], 'site_key release_id release_key artifact_hash deployment_id')
    return r


class Tree:
    """Linux openat/no-follow capability tree. Ancestors cannot be caller-writable."""
    def __init__(self, root, owner, writable=False, group=None):
        require(sys.platform == 'linux' and isinstance(root, str) and root.startswith('/') and root != '/')
        parts = relative(root[1:])
        fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC)
        try:
            for i, part in enumerate(parts):
                nxt = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=fd)
                s = os.fstat(nxt)
                expected_owner = owner if i == len(parts) - 1 else 0
                require(s.st_uid == expected_owner and s.st_mode & 0o022 == 0)
                os.close(fd)
                fd = nxt
            self.fd, self.owner, self.writable, self.group = fd, owner, writable, group
            self.device = os.fstat(fd).st_dev
        except BaseException:
            os.close(fd)
            raise

    def close(self):
        if self.fd is not None:
            os.close(self.fd)
            self.fd = None

    def directory(self, path='', create=False):
        fd = os.dup(self.fd)
        try:
            for part in relative(path) if path else []:
                if create:
                    require(self.writable)
                    try:
                        os.mkdir(part, 0o700, dir_fd=fd)
                        os.fsync(fd)
                    except FileExistsError:
                        pass
                nxt = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=fd)
                s = os.fstat(nxt)
                require(s.st_dev == self.device and s.st_uid == self.owner and s.st_mode & 0o022 == 0)
                os.close(fd)
                fd = nxt
            return fd
        except BaseException:
            os.close(fd)
            raise

    def parent(self, path, create=False):
        parts = relative(path)
        return self.directory('/'.join(parts[:-1]), create), parts[-1]

    def exists(self, path):
        parent, name = self.parent(path)
        try:
            try:
                os.stat(name, dir_fd=parent, follow_symlinks=False)
                return True
            except FileNotFoundError:
                return False
        finally:
            os.close(parent)

    def read(self, path, maximum):
        parent, name = self.parent(path)
        fd = None
        try:
            fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK | os.O_CLOEXEC, dir_fd=parent)
            before = os.fstat(fd)
            require(stat.S_ISREG(before.st_mode) and before.st_nlink == 1 and before.st_uid == self.owner
                    and before.st_dev == self.device and before.st_size <= maximum and before.st_mode & 0o022 == 0)
            chunks, length = [], 0
            while True:
                part = os.read(fd, min(65536, maximum + 1 - length))
                if not part:
                    break
                chunks.append(part)
                length += len(part)
                require(length <= maximum)
            after = os.fstat(fd)
            named = os.stat(name, dir_fd=parent, follow_symlinks=False)
            stable = ('st_dev', 'st_ino', 'st_mode', 'st_nlink', 'st_uid', 'st_gid', 'st_size', 'st_mtime_ns', 'st_ctime_ns')
            require(all(getattr(before, k) == getattr(after, k) == getattr(named, k) for k in stable) and length == before.st_size)
            return b''.join(chunks)
        finally:
            if fd is not None:
                os.close(fd)
            os.close(parent)

    def write_new(self, path, data, served=False):
        require(self.writable and len(data) <= MAX_PACKAGE)
        parent, name = self.parent(path, True)
        fd = None
        try:
            fd = os.open(name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC, 0o600, dir_fd=parent)
            view = memoryview(data)
            while view:
                n = os.write(fd, view)
                require(n > 0)
                view = view[n:]
            if served:
                require(self.group is not None)
                os.fchown(fd, -1, self.group)
                os.fchmod(fd, 0o440)
            os.fsync(fd)
            os.fsync(parent)
        finally:
            if fd is not None:
                os.close(fd)
            os.close(parent)

    def replace_json(self, path, value):
        require('/' not in path)
        temporary = path + '.pending-' + os.urandom(12).hex()
        self.write_new(temporary, canonical(value))
        os.replace(temporary, path, src_dir_fd=self.fd, dst_dir_fd=self.fd)
        os.fsync(self.fd)

    def _immutable_replay(self, path, parent, name, data, served):
        before = os.stat(name, dir_fd=parent, follow_symlinks=False)
        require(self.read(path, len(data)) == data)
        fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK | os.O_CLOEXEC, dir_fd=parent)
        primary = None
        try:
            after = os.fstat(fd)
            require(all(getattr(before, k) == getattr(after, k) for k in
                        ('st_dev', 'st_ino', 'st_mode', 'st_nlink', 'st_uid', 'st_gid', 'st_size', 'st_mtime_ns', 'st_ctime_ns')))
            require(stat.S_IMODE(after.st_mode) == (0o440 if served else 0o600)
                    and (not served or after.st_gid == self.group))
            os.fsync(fd)  # Exact replay after a prior uncertain acknowledgement still checks durability.
        except BaseException as error:
            primary = error
            raise
        finally:
            try:
                os.close(fd)
            except OSError:
                if primary is None:
                    raise

    def publish_immutable(self, path, data, served=False):
        """Only retained assets/manifests: complete private write, then atomic no-replace.

        A failed post-rename sync is unresolved, never cleanup of the final name.
        No SIGKILL/power-loss cleanup claim; only proven-owned normal-path temps are removed.
        """
        require(self.writable and isinstance(data, bytes) and 0 < len(data) <= MAX_PACKAGE)
        if served:
            require(self.group is not None and re.fullmatch(r'assets/[0-9a-f]{64}\.(css|png|jpg|webp|pdf)', path)
                    and path.split('/')[1].split('.')[0] == digest(data))
        else:
            require(re.fullmatch(r'manifests/[0-9a-f-]{36}\.json', path))
            machine(path.split('/')[1][:-5], UUID)
        parent = private = fd = owned = None
        temporary = 'immutable-' + os.urandom(16).hex() + '.pending'
        published = False
        primary = None
        try:
            parent, name = self.parent(path, True)
            private = self.directory('staging') if served else os.dup(self.fd)
            for directory in (parent, private):
                s = os.fstat(directory)
                require(stat.S_ISDIR(s.st_mode) and s.st_uid == self.owner and s.st_dev == self.device and s.st_mode & 0o022 == 0)
            require(stat.S_IMODE(os.fstat(private).st_mode) == 0o700)
            try:
                self._immutable_replay(path, parent, name, data, served)
                os.fsync(parent)
                return
            except FileNotFoundError:
                pass
            fd = os.open(temporary, os.O_RDWR | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC, 0o600, dir_fd=private)
            owned = os.fstat(fd)
            require(stat.S_ISREG(owned.st_mode) and owned.st_nlink == 1 and owned.st_uid == self.owner and owned.st_dev == self.device)
            view = memoryview(data)
            while view:
                n = os.write(fd, view)
                require(0 < n <= len(view))
                view = view[n:]
            require(os.fstat(fd).st_size == len(data))
            os.lseek(fd, 0, os.SEEK_SET)
            observed, length = hashlib.sha256(), 0
            while part := os.read(fd, 65536):
                length += len(part)
                require(length <= len(data))
                observed.update(part)
            require(length == len(data) and observed.hexdigest() == digest(data))
            if served:
                os.fchown(fd, -1, self.group)
            os.fchmod(fd, 0o440 if served else 0o600)
            final = os.fstat(fd)
            require(stat.S_ISREG(final.st_mode) and final.st_nlink == 1 and final.st_uid == self.owner
                    and final.st_dev == owned.st_dev and final.st_ino == owned.st_ino and final.st_size == len(data)
                    and stat.S_IMODE(final.st_mode) == (0o440 if served else 0o600)
                    and (not served or final.st_gid == self.group))
            os.fsync(fd)
            closing, fd = fd, None  # Do not retry close on an uncertain/reused descriptor.
            os.close(closing)
            named = os.stat(temporary, dir_fd=private, follow_symlinks=False)
            require(all(getattr(final, k) == getattr(named, k) for k in
                        ('st_dev', 'st_ino', 'st_mode', 'st_nlink', 'st_uid', 'st_gid', 'st_size', 'st_mtime_ns', 'st_ctime_ns')))
            try:
                self._rename_noreplace(private, temporary, parent, name)
                published = True
            except FileExistsError:
                self._immutable_replay(path, parent, name, data, served)
            os.fsync(private)
            os.fsync(parent)
        except BaseException as error:
            primary = error
            raise
        finally:
            cleanup_error = None
            if fd is not None:
                try:
                    os.close(fd)
                except OSError as error:
                    cleanup_error = error
            if owned is not None and not published:
                try:
                    try:
                        s = os.stat(temporary, dir_fd=private, follow_symlinks=False)
                    except FileNotFoundError:
                        s = None
                    if s is not None:
                        require(stat.S_ISREG(s.st_mode) and s.st_uid == self.owner and s.st_nlink == 1
                                and s.st_dev == owned.st_dev and s.st_ino == owned.st_ino)
                        os.unlink(temporary, dir_fd=private)
                        os.fsync(private)
                except BaseException as error:
                    cleanup_error = cleanup_error or error
            for directory in (private, parent):
                if directory is not None:
                    try:
                        os.close(directory)
                    except OSError as error:
                        cleanup_error = cleanup_error or error
            if primary is None and cleanup_error is not None:
                raise cleanup_error

    def inventory(self, path):
        names, dirs = [], []
        def walk(prefix):
            fd = self.directory(prefix)
            try:
                entries = sorted(os.listdir(fd))
                require(bool(entries))
                for name in entries:
                    relative(name)
                    child = prefix + '/' + name if prefix else name
                    s = os.stat(name, dir_fd=fd, follow_symlinks=False)
                    require(s.st_uid == self.owner and s.st_dev == self.device and s.st_mode & 0o022 == 0)
                    if stat.S_ISDIR(s.st_mode):
                        dirs.append(child)
                        require(len(dirs) <= 2000)
                        walk(child)
                    else:
                        require(stat.S_ISREG(s.st_mode) and s.st_nlink == 1)
                        names.append(child)
                        require(len(names) <= 2002)
            finally:
                os.close(fd)
        walk(path)
        return names, dirs

    def seal_directories(self, path):
        _, directories = self.inventory(path)
        for d in sorted(directories + [path], key=len, reverse=True):
            fd = self.directory(d)
            try:
                os.fchown(fd, -1, self.group)
                os.fchmod(fd, 0o550)
                os.fsync(fd)
            finally:
                os.close(fd)

    def rename_new(self, source, destination):
        src, sn = self.parent(source)
        dst, dn = self.parent(destination, True)
        try:
            self._rename_noreplace(src, sn, dst, dn)
            os.fsync(src)
            os.fsync(dst)
        finally:
            os.close(src)
            os.close(dst)

    @staticmethod
    def _rename_noreplace(src, sn, dst, dn):
        # Linux renameat2(RENAME_NOREPLACE); shared by sealed directories and immutable files.
        libc = ctypes.CDLL(None, use_errno=True)
        rename = libc.renameat2
        rename.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
        rename.restype = ctypes.c_int
        if rename(src, os.fsencode(sn), dst, os.fsencode(dn), 1) != 0:
            raise OSError(ctypes.get_errno(), 'exclusive_rename_failed')

    def link(self, name='current'):
        require(name in ('current', 'candidate'))
        try:
            s = os.stat(name, dir_fd=self.fd, follow_symlinks=False)
        except FileNotFoundError:
            return None
        require(stat.S_ISLNK(s.st_mode) and s.st_uid == self.owner and s.st_nlink == 1 and s.st_dev == self.device)
        target = os.readlink(name, dir_fd=self.fd)
        require(re.fullmatch(r'releases/[0-9a-f-]{36}/public', target) is not None)
        machine(target.split('/')[1], UUID)
        return target.split('/')[1]

    def switch(self, expected, release, name='current'):
        require(self.writable and name in ('current', 'candidate') and self.link(name) == expected)
        if release is None:
            # Absence compensation only, under owned lock and exclusive publisher tree.
            require(name == 'current' and expected is not None)
            os.unlink(name, dir_fd=self.fd)
        else:
            machine(release, UUID)
            fd = self.directory('releases/' + release + '/public')
            os.close(fd)
            temporary = 'pointer-' + os.urandom(16).hex()
            os.symlink('releases/' + release + '/public', temporary, dir_fd=self.fd)
            os.fsync(self.fd)
            require(self.link(name) == expected)
            os.replace(temporary, name, src_dir_fd=self.fd, dst_dir_fd=self.fd)
        os.fsync(self.fd)


def verify_package(tree, path, release, public_only=False, manifest=None):
    """Reuse the sealed M6C manifest and its non-circular hashes; never render."""
    if manifest is None:
        raw = tree.read(path + '/metadata/manifest.json', MAX_RESPONSE)
        require(digest(raw) == release['manifest_hash'])
        manifest = decode(raw)
        validation = tree.read(path + '/metadata/validation.json', 16384)
        require(digest(validation) == manifest['validation_hash'])
    require(manifest['contract_version'] == 'ubo-static-site-v1' and manifest['schema_version'] == 1
            and manifest['site_key'] == release['site_key'] and manifest['release_key'] == release['release_key']
            and manifest['artifact_hash'] == release['artifact_hash'] and manifest['build_profile'] == 'static-review-v1')
    entries = manifest['files']
    require(isinstance(entries, list) and 3 <= len(entries) <= 2000)
    observed, total, names = [], 0, []
    for entry in entries:
        keys(entry, 'relative_path sha256 byte_size mime_type')
        p, mime = entry['relative_path'], entry['mime_type']
        relative(p)
        machine(entry['sha256'], HASH)
        require(type(entry['byte_size']) is int and entry['byte_size'] > 0 and p not in names)
        if mime == 'text/html':
            require(p == 'public/index.html' or re.fullmatch(r'public/(?:[a-z0-9_-]+/)+index.html', p))
            limit = 1048576
        elif mime == 'application/json':
            require(p == 'public/_release.json')
            limit = 1024
        else:
            extensions = {'text/css': 'css', 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'application/pdf': 'pdf'}
            require(mime in extensions and p == 'public/assets/' + entry['sha256'] + '.' + extensions[mime])
            limit = 20971520
        data = tree.read(path + '/' + p, limit)
        require(len(data) == entry['byte_size'] and digest(data) == entry['sha256'])
        total += len(data)
        require(total <= MAX_PACKAGE)
        observed.append(entry)
        names.append(p)
    require(names == sorted(names) and 'public/index.html' in names and 'public/_release.json' in names)
    payload = [f for f in observed if f['relative_path'] != 'public/_release.json']
    require(digest(canonical(observed)) == release['artifact_hash'] and digest(canonical(payload)) == manifest['public_payload_hash']
            and manifest['byte_size'] == total and manifest['file_count'] == len(observed))
    marker = {'contract_version': 'ubo-static-site-v1', 'site_key': release['site_key'], 'release_key': release['release_key'],
              'public_payload_hash': manifest['public_payload_hash']}
    require(tree.read(path + '/public/_release.json', 1024) == canonical(marker))
    files, dirs = tree.inventory(path)
    wanted = [path + '/' + p for p in names]
    if not public_only:
        wanted += [path + '/metadata/manifest.json', path + '/metadata/validation.json']
    require(sorted(files) == sorted(wanted))
    expected_dirs = set()
    for p in wanted:
        parent = p.rsplit('/', 1)[0]
        while parent != path:
            expected_dirs.add(parent)
            parent = parent.rsplit('/', 1)[0]
    require(set(dirs) == expected_dirs)
    return manifest


def identity(authority, previous=False):
    if previous:
        return authority['previous']
    r = authority['release']
    return {'release_id': r['release_id'], 'release_key': r['release_key'], 'artifact_hash': r['artifact_hash'], 'manifest_hash': r['manifest_hash'],
            'site_key': r['site_key'], 'deployment_id': authority['deployment_id'],
            'producer_attempt_id': authority['producer_attempt_id'], 'fence_epoch': authority['activation_fence'] or authority['fence_epoch']}


def fence(state, authority, operation, session):
    """Pure admission, separately tested with controlled authority/fence fixtures."""
    keys(state, 'version fence owner session phase current intent settled')
    require(state['version'] == 1 and type(state['fence']) is int and state['fence'] >= 0
            and state['phase'] in (0, 1, 2, 3) and type(state['settled']) is bool)
    if state['fence'] == 0:
        require(state == dict(version=1, fence=0, owner=None, session=None, phase=0, current=None, intent=None, settled=True))
    else:
        require(isinstance(state['owner'], list) and len(state['owner']) == 3)
        for n in state['owner']:
            positive(n)
        require(isinstance(state['session'], str) and re.fullmatch(r'[0-9a-f]{32}', state['session']))
    def record(c):
        if c is None:
            return
        keys(c, 'release_id release_key artifact_hash manifest_hash site_key deployment_id producer_attempt_id fence_epoch')
        for k in ('release_id', 'deployment_id', 'producer_attempt_id', 'fence_epoch'):
            positive(c[k])
        for k in ('release_key', 'site_key'):
            machine(c[k], UUID)
        for k in ('artifact_hash', 'manifest_hash'):
            machine(c[k], HASH)
        require(c['site_key'] == authority['site_key'])
    record(state['current'])
    if state['intent'] is not None:
        keys(state['intent'], 'operation before after')
        require(state['intent']['operation'] in ('activate', 'restore', 'compensate') and not state['settled'])
        record(state['intent']['before'])
        record(state['intent']['after'])
    incoming = authority['fence_epoch']
    owner = [authority['deployment_id'], authority['attempt_id'], authority['producer_attempt_id']]
    require(incoming >= state['fence'])
    if incoming == state['fence']:
        require(state['owner'] == owner and state['session'] == session and not state['settled'])
        if operation in PHASES:
            require(PHASES[operation] >= state['phase'])
    elif not state['settled']:
        require(authority['attempt_kind'] == 'recovery' and state['owner'][0] == authority['deployment_id']
                and state['owner'][2] == authority['producer_attempt_id'])
    elif incoming > state['fence']:
        state = dict(state, phase=0)
    if authority['attempt_kind'] == 'recovery':
        require(operation not in ('stage', 'activate', 'restore'))
    if operation == 'compensate':
        require(authority['attempt_kind'] == 'recovery' and authority['outcome'] == 'uncommitted'
                and authority['activation_fence'] is not None)
    if operation != 'settle':
        require(authority['live'] is True and operation in authority['permitted'])
    return dict(state, fence=incoming, owner=owner, session=session, settled=False)


class Authority:
    def __init__(self, uid):
        self.uid = uid

    def observe(self, r):
        s = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        try:
            s.settimeout(5)
            s.connect('/run/ubo-site-publisher/authority.sock')
            # systemd socket is root-owned; the connected responder must be the configured authority UID
            # (for Accept=yes root socket activation, peer is root; root-owned service config is attested).
            _, uid, _ = struct.unpack('3i', s.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))
            require(uid in (0, self.uid))
            s.sendall(canonical(r) + b'\n')
            data = bytearray()
            while b'\n' not in data:
                part = s.recv(min(65536, MAX_RESPONSE + 1 - len(data)))
                require(part and len(data) + len(part) <= MAX_RESPONSE)
                data.extend(part)
            require(data.endswith(b'\n') and data.count(b'\n') == 1)
            result = decode(bytes(data))
            keys(result, 'version sequence result evidence')
            require(result['version'] == 1 and result['sequence'] == r['sequence'] and result['result'] == 'ok')
            return result['evidence']
        finally:
            s.close()


class Publisher:
    def __init__(self, source, served, journal, authority, binding):
        self.source, self.served, self.journal = source, served, journal
        self.authority, self.binding = authority, binding
        self.session = os.urandom(16).hex()
        self.state = None
        self.lock = None
        self.started = time.monotonic()
        self.bound = None
        self.sequence = 0

    def acquire(self):
        import fcntl
        # Provisioned lock and initial journal are mandatory; absence is unknown, never a fresh target.
        fd = os.open('target.lock', os.O_RDWR | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=self.journal.fd)
        try:
            s = os.fstat(fd)
            require(stat.S_ISREG(s.st_mode) and s.st_nlink == 1 and s.st_uid == self.journal.owner
                    and s.st_dev == self.journal.device and s.st_mode & 0o077 == 0)
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.state = decode(self.journal.read('state.json', 65536))
            self.lock = fd
        except BaseException:
            os.close(fd)
            raise

    def observe(self, r):
        a = self.authority.observe(r)
        keys(a, 'site_id target_id binding_key binding_version site_key deployment_key deployment_id attempt_id producer_attempt_id '
                'attempt_kind fence_epoch operation status outcome live permitted activation_fence release previous expires_in')
        for k in ('site_id', 'target_id', 'binding_key', 'binding_version'):
            require(a[k] == r['target'][k] == self.binding[k])
        for k in ('deployment_id', 'attempt_id', 'attempt_kind', 'fence_epoch'):
            require(a[k] == r['lease'][k])
        require(a['site_key'] == self.binding['site_key'] and type(a['live']) is bool)
        machine(a['deployment_key'], UUID)
        if 'context' in r['payload']:
            c = r['payload']['context']
            for k in ('producer_attempt_id', 'deployment_key', 'operation'):
                require(c[k] == a[k])
            require(c['release_key'] == a['release']['release_key'] and c['artifact_hash'] == a['release']['artifact_hash'])
        return a

    def persist(self):
        self.journal.replace_json('state.json', self.state)

    def current(self):
        actual = self.served.link()
        candidates = [self.state['current']]
        if self.state['intent'] is not None:
            candidates += [self.state['intent']['before'], self.state['intent']['after']]
        matches = [c for c in candidates if (None if c is None else c['release_key']) == actual]
        require(bool(matches) and len({canonical(c) for c in matches}) == 1)
        # A journal cannot prove bytes; verify the observed link target and its private pinned manifest.
        current = matches[-1]
        if current is not None:
            self.verify_served(current)
        return current

    def verify_served(self, release):
        raw = self.journal.read('manifests/' + release['release_key'] + '.json', MAX_RESPONSE)
        require(digest(raw) == release['manifest_hash'])
        manifest = decode(raw)
        return verify_package(self.served, 'releases/' + release['release_key'], release, True, manifest)

    def stage(self, a, r):
        release = a['release']
        require(r['payload']['release'] == release)
        path = release['site_key'] + '/' + release['release_key']
        manifest = verify_package(self.source, path, release)
        raw_manifest = self.source.read(path + '/metadata/manifest.json', MAX_RESPONSE)
        require(digest(raw_manifest) == release['manifest_hash'])
        target = 'releases/' + release['release_key']
        try:
            self.verify_served(release)
        except FileNotFoundError:
            # Any preexisting target conflicts, even if metadata is missing.
            require(not self.served.exists(target))
            stage = 'staging/' + a['deployment_key'] + '-' + str(a['attempt_id']) + '-' + os.urandom(8).hex()
            for entry in manifest['files']:
                data = self.source.read(path + '/' + entry['relative_path'], entry['byte_size'])
                require(digest(data) == entry['sha256'])
                self.served.write_new(stage + '/' + entry['relative_path'], data, served=True)
                if entry['relative_path'].startswith('public/assets/'):
                    pool = entry['relative_path'][7:]
                    self.served.publish_immutable(pool, data, served=True)
            verify_package(self.served, stage, release, True, manifest)
            self.served.seal_directories(stage)
            self.journal.publish_immutable('manifests/' + release['release_key'] + '.json', raw_manifest)
            self.served.rename_new(stage, target)
        self.verify_served(release)
        # Candidate is a separate private vhost pointer; it never changes active current.
        latest = self.observe(r)
        require(latest['live'] and 'stage' in latest['permitted'] and latest['fence_epoch'] == a['fence_epoch'])
        self.served.switch(self.served.link('candidate'), release['release_key'], 'candidate')
        self.state['phase'] = 1
        self.persist()

    def switch(self, a, r, compensation=False):
        before = self.current()
        after = identity(a, compensation)
        wanted = a['previous']
        expected = r['payload']['intent']['expected_previous'] if compensation else r['payload']['expected']
        for k in ('release_id', 'release_key', 'artifact_hash', 'deployment_id'):
            require(expected[k] == (None if wanted is None else wanted[k]))
        require(expected['site_key'] == a['site_key'])
        if self.state['phase'] == PHASES[r['operation']] and before == after and self.state['intent'] is None:
            return  # Exact same session/operation/phase replay; fence() already checked ownership.
        if compensation:
            intent = r['payload']['intent']
            require(before == identity(a) and intent['original_execution_id'] == a['producer_attempt_id']
                    and intent['activation_fence'] == a['activation_fence'])
            for k in ('release_key', 'artifact_hash', 'deployment_id', 'producer_attempt_id'):
                require(intent['observed'][k] == before[k])
        else:
            require(before == a['previous'] and a['attempt_kind'] == 'execution'
                    and r['operation'] == ('restore' if a['operation'] == 'restore' else 'activate'))
            require(self.state['phase'] == 1)
        if after is not None:
            self.verify_served(after)
        self.state['intent'] = {'operation': r['operation'], 'before': before, 'after': after}
        self.state['phase'] = PHASES[r['operation']]
        self.persist()  # Durable intent and high-water fence precede current mutation.
        latest = self.observe(r)
        require({k: v for k, v in latest.items() if k != 'expires_in'} == {k: v for k, v in a.items() if k != 'expires_in'})
        require(latest['live'] and latest['expires_in'] > 0)
        self.served.switch(None if before is None else before['release_key'], None if after is None else after['release_key'])
        self.state['current'] = after
        self.state['intent'] = None
        self.persist()

    def dispatch(self, r):
        require(time.monotonic() - self.started < 300 and r['sequence'] == self.sequence + 1)
        self.sequence = r['sequence']
        binding = [r['lease'][k] for k in ('site_id', 'target_id', 'deployment_id', 'attempt_id', 'fence_epoch', 'token')]
        require(self.bound is None or self.bound == binding)
        self.bound = binding
        a = self.observe(r)
        if self.lock is None:
            self.acquire()
        previous_fence = self.state['fence']
        self.state = fence(self.state, a, r['operation'], self.session)
        if previous_fence != self.state['fence']:
            self.persist()
        op = r['operation']
        if op == 'stage':
            self.stage(a, r)
        elif op in ('activate', 'restore', 'compensate'):
            self.switch(a, r, op == 'compensate')
        elif op == 'inspect':
            c = self.current()
            return {'state': 'absent' if c is None else 'present', 'site_id': a['site_id'], 'target_id': a['target_id'],
                    'binding_version': a['binding_version'], 'release_key': None if c is None else c['release_key'],
                    'artifact_hash': None if c is None else c['artifact_hash'], 'deployment_id': None if c is None else c['deployment_id'],
                    'producer_attempt_id': None if c is None else c['producer_attempt_id'], 'fence_epoch': self.state['fence'],
                    'integrity': 'pass', 'external_reference': a['deployment_key']}
        elif op == 'probe':
            expected = r['payload']['expected']
            candidate = r['payload']['context']['phase'] == 'candidate'
            c = identity(a) if candidate else self.current()
            require(expected['site_key'] == a['site_key'] and expected['release_key'] == (None if c is None else c['release_key'])
                    and expected['artifact_hash'] == (None if c is None else c['artifact_hash']))
            for k in ('release_id', 'deployment_id'):
                require(expected[k] == (None if c is None else c[k]))
            if c is None:
                return {'no_current_link': True}
            if candidate:
                require(self.served.link('candidate') == c['release_key'])
            m = self.verify_served(c)
            return {k: m[k] for k in ('site_key', 'release_key', 'artifact_hash', 'public_payload_hash', 'files')} | {
                'manifest_hash': c['manifest_hash'], 'no_current_link': False}
        elif op == 'settle':
            c = self.current()
            settled = (a['outcome'] == 'succeeded' and c == identity(a)) or (a['outcome'] == 'failed' and c == a['previous'])
            # Unavailable/ambiguous/uncommitted remains unresolved; no blind compensation.
            self.state['settled'] = settled
            self.state['current'] = c
            if settled:
                self.state['intent'] = None
            self.persist()
            return {'settled': settled}
        return {'phase': 'stage' if op == 'stage' else ('compensation' if op == 'compensate' else 'activation'),
                'result': 'pass', 'deployment_id': a['deployment_id'], 'attempt_id': a['attempt_id'], 'fence_epoch': a['fence_epoch'],
                'binding_version': a['binding_version'], 'release_key': a['release']['release_key'], 'artifact_hash': a['release']['artifact_hash'],
                'previous_release_key': None if a['previous'] is None else a['previous']['release_key'], 'external_reference': a['deployment_key']}

    def close(self):
        # Closing the descriptor releases flock. The un-settled durable record blocks
        # ordinary execution; only a greater recovery fence may resolve it.
        primary = None
        if self.lock is not None:
            try:
                os.close(self.lock)
            except OSError as error:
                primary = error
            self.lock = None
        for tree in (self.source, self.served, self.journal):
            try:
                tree.close()
            except OSError as error:
                primary = primary or error
        if primary:
            raise primary


def protected(path, maximum):
    tree = Tree(os.path.dirname(path), 0)
    try:
        return tree.read(os.path.basename(path), maximum)
    finally:
        tree.close()


def local_mount(path):
    # Kernel mount facts, independent of a config flag. Unknown/network/overlay
    # filesystems are not operationally admitted by this first adapter.
    with open('/proc/self/mountinfo', 'rb') as h:
        raw = h.read(1048577)
    require(len(raw) <= 1048576)
    matches = []
    for line in raw.decode('ascii').splitlines():
        before, after = line.split(' - ', 1)
        mount = before.split()[4]
        require('\\' not in mount)  # No ambiguous escaped mount path in this adapter.
        if path == mount or path.startswith(mount.rstrip('/') + '/'):
            matches.append((len(mount), after.split()[0], before.split()[5]))
    require(matches)
    _, kind, flags = max(matches)
    require(kind in ('ext4', 'xfs', 'btrfs') and 'rw' in flags.split(','))


def load_config():
    require(sys.platform == 'linux' and len(sys.argv) == 1 and hasattr(os, 'O_NOFOLLOW'))
    c = decode(protected(CONFIG, 65536))
    keys(c, 'version environment publisher_uid worker_uid authority_uid apache_gid source_owner source_root bindings')
    require(c['version'] == 1 and c['environment'] == 'staging' and os.geteuid() == c['publisher_uid'] > 0
            and len({c['publisher_uid'], c['worker_uid'], c['authority_uid']}) == 3)
    for k in ('worker_uid', 'authority_uid', 'apache_gid', 'source_owner'):
        positive(c[k])
    receipt = decode(protected('/etc/ubo-site-publisher/host-receipt.json', 65536))
    keys(receipt, 'version profile worker_config_sha256 verified_at expires_at gates installed_files')
    require(receipt['profile'] == 'm6e-host-v1' and receipt['verified_at'] <= time.time() < receipt['expires_at']
            and receipt['expires_at'] - receipt['verified_at'] <= 86400)
    require(set(receipt['gates']) == {'apache', 'filesystem', 'ownership', 'assets', 'runtime', 'health', 'recovery', 'provisioning'})
    for g in receipt['gates'].values():
        require(g['result'] == 'pass')
        machine(g['evidence_sha256'], HASH)
    require(receipt['installed_files'].get(CONFIG) == digest(protected(CONFIG, 65536)))
    require({'/usr/local/lib/ubo-site-publisher/ubo_site_publish.py', '/usr/local/sbin/ubo-site-publish',
             '/etc/ubo-site-publisher/apache.conf', '/etc/ubo-site-publisher/authority.json',
             '/usr/local/lib/ubo-site-publisher/ubo-site-authority.php'}.issubset(receipt['installed_files']))
    for p, h in receipt['installed_files'].items():
        machine(h, HASH)
        require(digest(protected(p, 2097152)) == h)
    require(receipt['worker_config_sha256'] == digest(protected('/etc/ubo-site-publisher/worker.json', 65536)))
    worker = decode(protected('/etc/ubo-site-publisher/worker.json', 65536))
    require(all(worker[k] == c[k] for k in ('worker_uid', 'publisher_uid', 'authority_uid'))
            and worker['artifact_root'] == c['source_root'] and c['source_owner'] == c['worker_uid'])
    require(isinstance(c['bindings'], list) and 1 <= len(c['bindings']) <= 32)
    seen, roots = set(), [c['source_root']]
    for b in c['bindings']:
        keys(b, 'site_id target_id site_key binding_key binding_version served_root journal_root')
        for k in ('site_id', 'target_id', 'binding_version'):
            positive(b[k])
        machine(b['site_key'], UUID)
        machine(b['binding_key'], KEY)
        require(b['target_id'] not in seen)
        seen.add(b['target_id'])
        require(b['served_root'] == '/var/www/ubo-sites/staging/sites/' + b['site_key']
                and b['journal_root'] == '/var/lib/ubo-site-publisher/staging/' + b['site_key'])
        roots += [b['served_root'], b['journal_root']]
        require(sum(all(w[k] == b[k] for k in ('site_id', 'target_id', 'site_key', 'binding_key', 'binding_version')) for w in worker['bindings']) == 1)
    require(len(roots) == len(set(roots)))
    for x in roots:
        require(isinstance(x, str) and x.startswith('/'))
        for y in roots:
            require(x == y or not x.startswith(y + '/'))
    return c


def main():
    publisher = None
    exit_code = 1
    try:
        c = load_config()  # Before accepting a request, source I/O or authority network.
        incoming = socket.socket(fileno=os.dup(0))
        _, uid, _ = struct.unpack('3i', incoming.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))
        require(uid == c['worker_uid'])
        incoming.settimeout(30)
        started, data, sequence = time.monotonic(), bytearray(), 0
        while time.monotonic() - started < 300:
            part = incoming.recv(4096)
            if not part:
                break
            data.extend(part)
            require(len(data) <= MAX_REQUEST)
            if b'\n' not in data:
                continue
            line, _, tail = data.partition(b'\n')
            require(not tail)  # No request pipelining.
            data.clear()
            r = request(bytes(line))
            sequence = r['sequence']
            if publisher is None:
                matches = [b for b in c['bindings'] if all(b[k] == r['target'][k] for k in ('site_id', 'target_id', 'binding_key', 'binding_version'))]
                require(len(matches) == 1)
                b = matches[0]
                keys(b, 'site_id target_id site_key binding_key binding_version served_root journal_root')
                machine(b['site_key'], UUID)
                source = Tree(c['source_root'], c['source_owner'])
                local_mount(b['served_root'])
                local_mount(b['journal_root'])
                served = Tree(b['served_root'], c['publisher_uid'], True, c['apache_gid'])
                journal = Tree(b['journal_root'], c['publisher_uid'], True)
                require(source.fd != served.fd and b['served_root'] != b['journal_root'])
                served_stat, journal_stat = os.fstat(served.fd), os.fstat(journal.fd)
                require(served_stat.st_gid == c['apache_gid'] and stat.S_IMODE(served_stat.st_mode) == 0o750
                        and stat.S_IMODE(journal_stat.st_mode) == 0o700)
                for name in ('releases', 'assets', 'staging'):
                    fd = served.directory(name)
                    try:
                        st = os.fstat(fd)
                        require(st.st_gid == c['apache_gid'] and stat.S_IMODE(st.st_mode) == (0o700 if name == 'staging' else 0o750))
                    finally:
                        os.close(fd)
                publisher = Publisher(source, served, journal, Authority(c['authority_uid']), b)
            evidence = publisher.dispatch(r)
            encoded = canonical({'version': 1, 'sequence': sequence, 'result': 'ok', 'evidence': evidence}) + b'\n'
            require(len(encoded) <= MAX_RESPONSE)
            incoming.sendall(encoded)
            if r['operation'] == 'settle':
                break
        exit_code = 0
    except BaseException:
        # A single fixed diagnostic; no exception interpolation, tracebacks or lease data.
        try:
            os.write(2, b'publisher_unresolved\n')
        except OSError:
            pass
    finally:
        if publisher is not None:
            try:
                publisher.close()
            except BaseException:
                exit_code = 1
                try:
                    os.write(2, b'publisher_cleanup_unresolved\n')
                except OSError:
                    pass
    return exit_code
