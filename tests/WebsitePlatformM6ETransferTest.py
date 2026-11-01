"""Actual PHP exporter/session -> Python receiver/publication, private bounded child pipes.

Unix metadata/dirfd/rename/fsync/peer/lock/pointers are explicit Windows models.
No installed runtime, database, authority service or native acceptance.
"""
import copy
from contextlib import contextmanager
import importlib.util
import json
import os
from pathlib import Path
import queue
import struct
import subprocess
import sys
import tempfile
import threading
import time
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tests/support'))
from WebsitePlatformM6EFilesystem import FilesystemModel
spec = importlib.util.spec_from_file_location('transfer_publisher', ROOT / 'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


class Pipe:
    """Bounded read-ahead (two 8KiB blocks), with absolute Channel deadlines."""
    def __init__(self, child):
        self.child, self.pending, self.timeout = child, bytearray(), 10
        self.queue, self.stop = queue.Queue(2), threading.Event()
        self.received, self.sent = bytearray(), bytearray()
        def reader():
            while not self.stop.is_set():
                data = os.read(child.stdout.fileno(), 8192)
                while not self.stop.is_set():
                    try:
                        self.queue.put(data, timeout=.1)
                        break
                    except queue.Full:
                        pass
                if not data:
                    break
        self.thread = threading.Thread(target=reader, daemon=True)
        self.thread.start()

    def settimeout(self, seconds):
        self.timeout = seconds

    def recv(self, count, flags=0):
        if flags:
            if not self.pending:
                try:
                    self.pending.extend(self.queue.get_nowait())
                except queue.Empty:
                    raise BlockingIOError()
            return bytes(self.pending[:count])
        if not self.pending:
            self.pending.extend(self.queue.get(timeout=self.timeout))
        part = bytes(self.pending[:min(count, 173)])
        del self.pending[:len(part)]
        self.received.extend(part)
        if len(self.received) > 1048576:
            raise RuntimeError('fixture_capture_bound')
        return part

    def send(self, data):
        part = bytes(data[:157])
        self.child.stdin.write(part)
        self.child.stdin.flush()
        self.sent.extend(part)
        return len(part)


class Memory:
    def __init__(self, data, fragment=65536):
        self.data, self.offset, self.fragment, self.sent = data, 0, fragment, bytearray()
    def settimeout(self, seconds):
        self.timeout = seconds
    def recv(self, count, flags=0):
        if flags:
            if self.offset >= len(self.data):
                raise BlockingIOError()
            return self.data[self.offset:self.offset+count]
        part = self.data[self.offset:self.offset + min(count, self.fragment)]
        self.offset += len(part)
        return part
    def send(self, data):
        part = bytes(data[:self.fragment])
        self.sent.extend(part)
        if getattr(self, 'after_ready', None) and self.sent.endswith(b'\n'):
            callback, self.after_ready = self.after_ready, None
            callback()
        return len(part)


class PointerTree(p.Tree):
    # All other Tree methods (read/write/inventory/seal/immutable publication) are real.
    def link(self, name='current'):
        return self.links.get(name)
    def switch(self, before, after, name='current'):
        p.require(self.link(name) == before)
        self.links[name] = after


class Publisher(p.Publisher):
    def acquire(self):
        self.state = p.decode(self.journal.read('state.json', 65536))
        self.lock = 'MODELED-flock'
    def close(self):
        self.lock = None
        self.served.close()
        self.journal.close()


class Authority:
    def __init__(self, value):
        self.value, self.calls, self.hook = copy.deepcopy(value), 0, None
    def observe(self, request):
        self.calls += 1
        if self.hook:
            self.hook(self, request)
        return copy.deepcopy(self.value)


def frames(raw):
    result, offset = [], 0
    while offset < len(raw):
        magic, kind, length = struct.unpack('!4scI', raw[offset:offset + 9])
        assert magic == b'UBT1'
        data = raw[offset + 9:offset + 9 + length]
        assert len(data) == length
        result.append([kind, json.loads(data) if kind == b'C' else data])
        offset += 9 + length
    return result


def encode(items):
    out = bytearray()
    for kind, item in items:
        data = p.canonical(item) if kind == b'C' else item
        out.extend(struct.pack('!4scI', b'UBT1', kind, len(data)) + data)
    return bytes(out)


class TransferTests(unittest.TestCase):
    @contextmanager
    def fixture(self, bootstrap):
        root = '\\\\?\\' + tempfile.gettempdir() if os.name == 'nt' else tempfile.gettempdir()
        with tempfile.TemporaryDirectory(prefix='ubo-transfer-', dir=root) as directory:
            base = Path(directory)
            for name in ('served', 'journal', 'served/staging', 'served/assets', 'served/releases', 'journal/manifests'):
                (base / name).mkdir()
            ops = FilesystemModel(base)
            with mock.patch.object(p, 'os', ops), mock.patch.object(p.Tree, '_rename_noreplace', staticmethod(ops.rename_noreplace)):
                served, journal = ops.tree(p, base / 'served'), ops.tree(p, base / 'journal')
                served.__class__ = PointerTree
                served.links = {'current': None, 'candidate': None}
                journal.write_new('state.json', p.canonical(dict(version=1, fence=0, owner=None, session=None, phase=0, current=None, intent=None, settled=True)))
                authority = Authority(bootstrap['authority'])
                pub = Publisher(served, journal, authority, bootstrap['binding'])
                try:
                    yield pub, ops, base
                finally:
                    pub.close()

    def live(self, mode='valid', lost_ack=False):
        child = subprocess.Popen(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=256M',
                                  str(ROOT / 'tests/support/WebsitePlatformM6ETransferChild.php'), mode],
                                 stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, bufsize=0)
        pipe = Pipe(child)
        observed, failure, published = {}, None, False
        try:
            channel = p.Channel(pipe, time.monotonic() + 30)
            bootstrap = json.loads(channel.line())
            start = len(pipe.received)
            r = p.request(channel.line())
            original = p.Ingress.receive
            def receive(ingress, *args):
                value = original(ingress, *args)
                for path in bootstrap['files']:
                    data = ingress.tree.read('package/' + path, p.MAX_ASSET)
                    observed[path] = dict(sha256=p.digest(data), bytes=len(data))
                return value
            with self.fixture(bootstrap) as (pub, ops, base), mock.patch.object(p.Ingress, 'receive', receive):
                try:
                    receipt = pub.dispatch(r, channel)
                    published = True
                    self.assertEqual(pub.served.link('candidate'), bootstrap['release']['release_key'])
                    self.assertIsNone(pub.served.link())
                    self.assertEqual(observed, bootstrap['files'])
                    self.assertFalse(list((base / 'served/staging').glob('transfer-*')))
                    if not lost_ack:
                        channel.reply(dict(version=1, sequence=r['sequence'], result='ok', evidence=receipt))
                    if mode == 'replay':
                        second = p.request(channel.line())
                        before = len(pipe.received)
                        receipt2 = pub.dispatch(second, channel)
                        self.assertEqual(len(pipe.received), before)  # No file frames expected on retained handshake.
                        channel.reply(dict(version=1, sequence=second['sequence'], result='ok', evidence=receipt2))
                except (p.Denied, OSError, queue.Empty) as error:
                    failure = type(error).__name__ + ":" + str(getattr(error, "phase", "")) + ":" + str(getattr(error, "index", ""))
                    self.assertIsNone(pub.served.link())
                    self.assertIsNone(pub.served.link('candidate'))
                    self.assertFalse(list((base / 'served/releases').iterdir()))
                    self.assertFalse(list((base / 'served/staging').glob('transfer-*')))
                transcript = bytes(pipe.received[start:])
                self.assertFalse(hasattr(pub, 'source'))
            child.stdin.close()
            child.wait(timeout=10)
            stderr = child.stderr.read(16385)
            self.assertLessEqual(len(stderr), 16384)
            self.assertNotIn(b'CANARY', stderr)
            self.assertNotIn(r['lease']['token'].encode(), stderr)
            return dict(bootstrap=bootstrap, request=r, transcript=transcript, observed=observed, published=published,
                        failure=failure, exit=child.returncode, stderr=stderr, sent=bytes(pipe.sent))
        finally:
            pipe.stop.set()
            if child.poll() is None:
                child.kill()
                child.wait(timeout=5)
            for stream in (child.stdin, child.stdout, child.stderr):
                if not stream.closed:
                    stream.close()
            pipe.thread.join(timeout=2)

    @classmethod
    def setUpClass(cls):
        # Retain one actual producer transcript for independent corruption/receiver tests.
        cls.record = cls('run').live()
        if cls.record['exit'] != 0 or cls.record['failure']:
            raise RuntimeError('positive_transfer_failed:' + repr({k: cls.record[k] for k in ('exit', 'stderr', 'failure')}))
        _, cls.body = cls.record['transcript'].split(b'\n', 1)
        cls.items = frames(cls.body)
        cls.transfer_id = cls.items[0][1]['transfer_id']

    def replay(self, body=None, mutate=None, fault=None, fragment=65536, hook=None, cleanup=False):
        bootstrap, request = copy.deepcopy(self.record['bootstrap']), copy.deepcopy(self.record['request'])
        if mutate:
            mutate(bootstrap, request)
        wire = Memory(self.body if body is None else body, fragment)
        channel = p.Channel(wire, time.monotonic() + 30)
        with self.fixture(bootstrap) as (pub, ops, base):
            if hook:
                pub.authority.hook = lambda a, r: hook(a, r, pub, ops)
            def arm():
                ops.fault, ops.cleanup_fault = fault, cleanup
            wire.after_ready = arm  # Faults reach ingress/publication, not initial fence persistence.
            original_random = os.urandom
            with mock.patch.object(ops, 'urandom', side_effect=lambda n: bytes.fromhex(self.transfer_id) if n == 16 else original_random(n)):
                try:
                    receipt = pub.dispatch(p.request(p.canonical(request)), channel)
                    result = 'ok'
                except (p.Denied, OSError, ValueError, KeyError) as error:
                    result = type(error).__name__
                    if isinstance(error, p.TransferFailure):
                        ops.events.append(('primary_phase', error.phase, error.index))
                    self.assertIsNone(pub.served.link('candidate'))
                self.assertIsNone(pub.served.link())
                files = [f.relative_to(base).as_posix() for f in base.rglob('*') if f.is_file()]
                return result, wire, files, list(ops.events)

    def test_real_multi_page_css_media_bytes(self):
        r = self.record
        self.assertEqual(r['exit'], 0)
        self.assertEqual(r['stderr'], b'transfer_child_complete\n')
        self.assertEqual(len(r['observed']), 8)
        self.assertEqual(sum(n.endswith('.html') for n in r['observed']), 3)
        self.assertTrue(any(n.endswith('.png') for n in r['observed']))
        self.assertTrue(any(n.endswith('.css') for n in r['observed']))
        self.assertEqual(r['bootstrap']['limits'], [p.MAX_FILES, p.MAX_PACKAGE, p.MAX_MANIFEST, p.MAX_VALIDATION, p.MAX_ASSET, p.MAX_HTML])

    def test_actual_approved_pdf(self):
        r = self.live('pdf')
        self.assertEqual((r['exit'], r['failure']), (0, None))
        self.assertTrue(any(n.endswith('.pdf') for n in r['observed']))
        self.assertTrue(any(v['bytes'] > p.TRANSFER_CHUNK for k, v in r['observed'].items() if k.endswith('.pdf')))

    def test_retained_handshake_never_exports_or_deadlocks(self):
        result = self.live('replay')
        self.assertEqual((result['exit'], result['failure']), (0, None))

    def test_fragmented_and_coalesced_actual_frames(self):
        for size in (1, 7, 173, 65536):
            with self.subTest(fragment=size):
                self.assertEqual(self.replay(fragment=size)[0], 'ok')

    def test_truncated_metadata_file_and_end(self):
        boundaries = [1, 8, 9, 50, len(encode(self.items[:2])) - 1, len(encode(self.items[:-1])), len(self.body) - 1]
        for cut in boundaries:
            with self.subTest(cut=cut):
                result, _, files, _ = self.replay(self.body[:cut])
                self.assertNotEqual(result, 'ok')
                self.assertEqual(files, ['journal/state.json'])

    def test_header_identity_order_and_shapes(self):
        for field, value in [('index', 1), ('index', True), ('type', 'end'), ('path', '../secret'), ('path', 'public/index.html'),
                             ('length', 0), ('length', -1), ('length', True), ('length', 2**64), ('length', p.MAX_MANIFEST+1),
                             ('sha256', 'b'*64), ('version', 2), ('version', True), ('sequence', 2), ('transfer_id', 'b'*32)]:
            with self.subTest(field=field, value=value):
                bad = copy.deepcopy(self.items)
                bad[0][1][field] = value
                self.assertNotEqual(self.replay(encode(bad))[0], 'ok')
        for bad in (self.items[2:], self.items[:2] + self.items[:2] + self.items[2:], self.items[:2] + self.items[4:6] + self.items[2:4] + self.items[6:]):
            self.assertNotEqual(self.replay(encode(bad))[0], 'ok')

    def test_binary_cannot_be_control_and_oversized_frames(self):
        for body in (struct.pack('!4scI', b'UBT1', b'C', p.TRANSFER_CONTROL+1),
                     struct.pack('!4scI', b'UBT1', b'D', 2**32-1), b'{"operation":"activate"}\n',
                     encode(self.items[:1]) + struct.pack('!4scI', b'UBT1', b'C', 3) + b'{}\n'):
            self.assertNotEqual(self.replay(body)[0], 'ok')

    def test_changed_metadata_public_bytes_and_completion(self):
        for index in (1, 3, 5):
            bad = copy.deepcopy(self.items)
            bad[index][1] = b'x' + bad[index][1][1:]
            self.assertNotEqual(self.replay(encode(bad))[0], 'ok')
        for field, value in [('files', 1), ('public_bytes', 0), ('metadata_bytes', 0), ('data_frames', True), ('manifest_hash', 'b'*64), ('type', 'file')]:
            bad = copy.deepcopy(self.items)
            bad[-1][1][field] = value
            self.assertNotEqual(self.replay(encode(bad))[0], 'ok')

    def test_actual_exporter_source_mutation_and_producer_negatives(self):
        for mode in ('source-corrupt', 'source-replaced', 'source-extra', 'source-owner', 'producer'):
            with self.subTest(mode=mode):
                r = self.live(mode)
                self.assertEqual(r['exit'], 9)
                self.assertFalse(r['published'])
                self.assertEqual(r['stderr'], b'transfer_child_unresolved\n')

    def test_authority_request_identity_and_recovery_denied(self):
        for key in ('site_id', 'target_id', 'attempt_id', 'fence_epoch'):
            def mutate(b, r, key=key):
                r['lease'][key] += 1
            self.assertNotEqual(self.replay(mutate=mutate)[0], 'ok')
        def recovery(b, r):
            r['lease']['attempt_kind'] = r['payload']['context']['attempt_kind'] = b['authority']['attempt_kind'] = 'recovery'
        self.assertNotEqual(self.replay(mutate=recovery)[0], 'ok')
        for field, value in [('live', False), ('expires_in', 0), ('release', self.record['bootstrap']['release'] | {'manifest_hash': 'b'*64})]:
            self.assertNotEqual(self.replay(mutate=lambda b, r, f=field, v=value: b['authority'].update({f:v}))[0], 'ok')
        self.assertNotEqual(self.replay(hook=lambda a, r, pub, ops: a.value.update(live=False) if a.calls >= 3 else None)[0], 'ok')

    def test_revocation_and_expiry_during_received_bytes(self):
        for field, value in (('live',False), ('expires_in',0)):
            reached=[]
            def revoke(a, r, pub, ops):
                if any(e[0]=='file_sync' and 'transfer-' in e[1] for e in ops.events):
                    reached.append(True)
                    a.value[field]=value
            result, _, files, _=self.replay(hook=revoke)
            self.assertTrue(reached)
            self.assertNotEqual(result,'ok')
            self.assertFalse(any('/releases/' in f or '/assets/' in f for f in files))

    def test_storage_failure_and_cleanup_primary(self):
        for fault in ('receive_open', 'partial', 'zero', 'write', 'file_sync', 'close', 'chmod', 'chown', 'publish', 'directory_sync'):
            with self.subTest(fault=fault):
                result, _, files, events = self.replay(fault=fault)
                self.assertNotEqual(result, 'ok')
                self.assertIn(('injected', fault), events)
                self.assertFalse(any('/releases/' in f for f in files))
                if fault == 'receive_open':
                    self.assertEqual(files, ['journal/state.json'])
        result, _, files, events = self.replay(fault='partial', cleanup=True)
        self.assertEqual(result, 'TransferFailure')
        self.assertTrue(any('transfer-' in f for f in files))
        self.assertIn(('injected', 'cleanup'), events)
        self.assertIn(('diagnostic', 'transfer_cleanup_unresolved\n'), events)
        self.assertIn(('primary_phase', 'file_write', 0), events)

    def test_conflicting_retained_release_never_receives_or_repairs(self):
        with self.fixture(self.record['bootstrap']) as (pub, ops, base):
            request=copy.deepcopy(self.record['request'])
            original_random=os.urandom
            with mock.patch.object(ops,'urandom',side_effect=lambda n: bytes.fromhex(self.transfer_id) if n==16 else original_random(n)):
                pub.dispatch(request,p.Channel(Memory(self.body),time.monotonic()+30))
            release=base/'served/releases'/request['payload']['release']['release_key']/'public/index.html'
            release.write_bytes(b'conflicting-retained-bytes')
            candidate=pub.served.link('candidate')
            request['sequence']=2
            wire=Memory(b'')
            with self.assertRaises(p.Denied): pub.dispatch(request,p.Channel(wire,time.monotonic()+30))
            self.assertEqual(release.read_bytes(),b'conflicting-retained-bytes')
            self.assertEqual(pub.served.link('candidate'),candidate)
            self.assertEqual(wire.offset,0)
            self.assertEqual(wire.sent,b'')

    def test_manifest_identity_inventory_and_aggregate_admission(self):
        def oversized(m):
            m['files'][0]['byte_size'] = p.MAX_ASSET + 1
        def aggregate(m):
            prototype = next(e for e in m['files'] if e['mime_type'] == 'text/html')
            m['files'] = [dict(prototype, relative_path='public/p'+str(i)+'/index.html', byte_size=p.MAX_HTML) for i in range(101)]
        for change in (lambda m: m.update(site_key='11111111-1111-4111-8111-111111111111'),
                       lambda m: m.update(release_key='11111111-1111-4111-8111-111111111111'),
                       lambda m: m.update(build_profile='production'), lambda m: m['files'].reverse(),
                       lambda m: m['files'].append(m['files'][0]), lambda m: m['files'].pop(),
                       lambda m: m['files'][0].update(relative_path='public/../secret'), oversized, aggregate):
            bad = copy.deepcopy(self.items)
            manifest = json.loads(bad[1][1])
            change(manifest)
            raw = p.canonical(manifest)
            bad[0][1].update(length=len(raw), sha256=p.digest(raw))
            bad[1][1] = raw
            def admitted_hash(b, r):
                b['authority']['release']['manifest_hash'] = r['payload']['release']['manifest_hash'] = p.digest(raw)
            result, _, files, _ = self.replay(encode(bad), mutate=admitted_hash)
            self.assertNotEqual(result, 'ok')
            self.assertEqual(files, ['journal/state.json'])
        self.assertNotEqual(self.replay(self.body + encode(self.items[:2]))[0], 'ok')

    def test_frame_budgets_are_separate_from_public_bytes(self):
        f = p.TransferFrames(p.Channel(Memory(b''), 30), 'a'*32, 1)
        f.controls = p.TRANSFER_CONTROLS
        with self.assertRaises(p.Denied): f.count(b'C', 1)
        f = p.TransferFrames(None, 'a'*32, 1)
        f.frames = p.TRANSFER_FRAMES
        with self.assertRaises(p.Denied): f.count(b'D', 1)
        f = p.TransferFrames(None, 'a'*32, 1)
        f.data_bytes = p.MAX_PACKAGE + p.MAX_MANIFEST + p.MAX_VALIDATION
        with self.assertRaises(p.Denied): f.count(b'D', 1)

    @mock.patch.object(p.socket, 'SO_PEERCRED', 17, create=True)
    def test_peer_identity_before_any_transfer(self):
        for uid in (0, 1001, 1002, 1004):
            peer = mock.Mock()
            peer.getsockopt.return_value = struct.pack('3i', 77, uid, 9)
            with self.assertRaises(p.Denied): p.accept_peer(peer, 1003)
        peer.getsockopt.return_value = struct.pack('3i', 77, 1003, 9)
        p.accept_peer(peer, 1003)

    @mock.patch.object(p.socket, 'SO_PEERCRED', 17, create=True)
    def test_production_entry_inspect_probe_recovery_settle_without_source(self):
        b = copy.deepcopy(self.record['bootstrap'])
        requests = []
        for sequence, operation in enumerate(('inspect', 'probe', 'settle'), 1):
            r = copy.deepcopy(self.record['request'])
            r.update(sequence=sequence, operation=operation, payload={})
            r['lease']['attempt_kind'] = 'recovery'
            if operation == 'probe':
                context = copy.deepcopy(self.record['request']['payload']['context'])
                context.update(attempt_kind='recovery', phase='rollback')
                r['payload'] = dict(context=context, expected=dict(site_key=b['release']['site_key'], release_id=None,
                    release_key=None, artifact_hash=None, deployment_id=None))
            requests.append(r)
        b['authority'].update(attempt_kind='recovery', outcome='failed', permitted=['inspect', 'probe'])
        wire = Memory(b''.join(p.canonical(r)+b'\n' for r in requests), 7)
        wire.getsockopt = lambda *args: struct.pack('3i', 77, 1003, 9)
        with self.fixture(b) as (pub, ops, base):
            pub.journal.write_new('target.lock', b'')
            for name in ('served', 'served/releases', 'served/assets'):
                ops.meta[str(base/name)] = [0o750, 1001, 1002]
            opened = []
            def tree(root, owner, writable=False, group=None):
                opened.append(root)
                return pub.served if root == str(base/'served') else pub.journal
            def acquire(owner):
                owner.state = p.decode(owner.journal.read('state.json', 65536))
                owner.lock = ops.open('target.lock', os.O_RDWR, dir_fd=owner.journal.fd)
            config = dict(worker_uid=1003, publisher_uid=1001, authority_uid=1004, apache_gid=1002,
                          bindings=[b['binding'] | dict(served_root=str(base/'served'), journal_root=str(base/'journal'))])
            with mock.patch.object(p, 'load_config', return_value=config), mock.patch.object(p, 'local_mount'), \
                 mock.patch.object(p.socket, 'socket', return_value=wire), mock.patch.object(p, 'Tree', side_effect=tree), \
                 mock.patch.object(p, 'Authority', return_value=pub.authority), mock.patch.object(p.Publisher, 'acquire', acquire), \
                 mock.patch.object(ops, 'dup', wraps=ops.dup) as duplicate:
                # The inherited stdin descriptor is a model; production still performs peer verification.
                duplicate.side_effect = lambda fd: 0 if fd == 0 else FilesystemModel.dup(ops, fd)
                self.assertEqual(p.main(), 0)
            self.assertEqual(opened, [str(base/'served'), str(base/'journal')])
            replies = [json.loads(line) for line in wire.sent.splitlines()]
            self.assertEqual(replies[0]['evidence']['state'], 'absent')
            self.assertEqual(replies[1]['evidence'], {'no_current_link': True})
            self.assertEqual(replies[2]['evidence'], {'settled': True})
            # Production main closed both capabilities; avoid test double-closing them.
            pub.served.fd = pub.journal.fd = None

    def test_config_v2_pins_only_helper_readable_files(self):
        b = self.record['bootstrap']['binding']
        config = dict(version=2, transfer_version=1, environment='staging', publisher_uid=1001, worker_uid=1003,
                      authority_uid=1004, apache_gid=1002, bindings=[b | dict(
                          served_root='/var/www/ubo-sites/staging/sites/'+b['site_key'],
                          journal_root='/var/lib/ubo-site-publisher/staging/'+b['site_key'])])
        required = ['/usr/local/lib/ubo-site-publisher/ubo_site_publish.py', '/usr/local/sbin/ubo-site-publish',
                    '/etc/ubo-site-publisher/apache.conf', '/etc/ubo-site-publisher/authority.json',
                    '/usr/local/lib/ubo-site-publisher/ubo-site-authority.php']
        for mutation in (None, 'old', 'source', 'wire', 'pin'):
            c = copy.deepcopy(config)
            if mutation == 'old': c['version'] = 1
            if mutation == 'source': c['source_root'] = '/private-worker-source'
            if mutation == 'wire': c['transfer_version'] = 2
            raw = p.canonical(c)
            pins = dict.fromkeys(required, p.digest(b'fixed-code')) | {p.CONFIG: p.digest(raw)}
            if mutation == 'pin': pins[p.CONFIG] = 'b'*64
            receipt = dict(version=2, profile='m6e-host-v2', verified_at=90, expires_at=110,
                           worker_config_sha256='a'*64, installed_files=pins,
                           gates={k:dict(result='pass', evidence_sha256='a'*64) for k in
                                  ('apache','filesystem','ownership','assets','runtime','health','recovery','provisioning')})
            reads = []
            def protected(path, maximum):
                reads.append(path)
                if path == p.CONFIG: return raw
                if path.endswith('host-receipt.json'): return p.canonical(receipt)
                self.assertIn(path, required[:3])
                return b'fixed-code'
            with mock.patch.object(p.sys, 'platform', 'linux'), mock.patch.object(p.sys, 'argv', ['helper']), \
                 mock.patch.object(p.os, 'geteuid', return_value=1001, create=True), \
                 mock.patch.object(p.os, 'O_NOFOLLOW', 0, create=True), mock.patch.object(p.time, 'time', return_value=100), \
                 mock.patch.object(p, 'protected', protected):
                if mutation is None: self.assertEqual(p.load_config(), c)
                else:
                    with self.assertRaises(p.Denied): p.load_config()
            self.assertNotIn('/etc/ubo-site-publisher/worker.json', reads)

    def test_lost_final_ack_retains_complete_publication(self):
        r = self.live(lost_ack=True)
        self.assertTrue(r['published'])
        self.assertEqual(r['exit'], 9)
        self.assertEqual(r['stderr'], b'transfer_child_unresolved\n')

    def test_absolute_deadline_does_not_reset_on_progress(self):
        wire = Memory(b'abc', 1)
        channel = p.Channel(wire, 2)
        with mock.patch.object(p.time, 'monotonic', side_effect=[0, 1, 3]):
            with self.assertRaises(p.Denied):
                channel.read(3)
        self.assertEqual(wire.offset, 2)


if __name__ == '__main__':
    unittest.main(verbosity=2)
