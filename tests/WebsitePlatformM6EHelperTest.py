"""Real Python publisher/protocol/manifest code, real M6C child and private files.
Windows filesystem mutation/locks are explicitly modeled; never Linux acceptance.
"""
import base64
import copy
import importlib.util
import json
import os
import shutil
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', ROOT / 'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


class Files:
    """Private regular-file operations, with explicit pointer/rename/durability model."""
    def __init__(self, root):
        self.root = Path(root)
        self.root.mkdir()
        self.links = {'current': None, 'candidate': None}
        self.writes = 0
        self.fail = None

    def read(self, name, limit):
        data = (self.root / name).read_bytes()
        p.require(len(data) <= limit)
        return data

    def write_new(self, name, data, served=False):
        if self.fail == 'write':
            raise OSError('synthetic_storage_failure')
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('xb') as f:
            f.write(data)
        self.writes += 1

    def replace_json(self, name, value):
        if self.fail == 'journal':
            raise OSError('synthetic_journal_failure')
        (self.root / name).write_bytes(p.canonical(value))
        self.writes += 1

    def inventory(self, prefix):
        files, dirs = [], []
        for path in sorted((self.root / prefix).rglob('*')):
            rel = path.relative_to(self.root).as_posix()
            (dirs if path.is_dir() else files).append(rel)
        return files, dirs

    def exists(self, name):
        return (self.root / name).exists()

    def seal_directories(self, path):
        # Mode/durability explicitly modeled; native harness uses Tree directly.
        pass

    def rename_new(self, before, after):
        p.require(not self.exists(after))
        target = self.root / after
        target.parent.mkdir(parents=True, exist_ok=True)
        (self.root / before).rename(target)

    def link(self, name='current'):
        return self.links[name]

    def switch(self, before, after, name='current'):
        p.require(self.links[name] == before)
        if self.fail == 'switch':
            raise OSError('synthetic_rename_failure')
        self.links[name] = after
        self.writes += 1

    def close(self):
        pass


class ModelPublisher(p.Publisher):
    def acquire(self):
        self.state = p.decode(self.journal.read('state.json', 65536))
        self.lock = 'MODELED-flock'

    def close(self):
        self.lock = None


class Authority:
    def __init__(self, value):
        self.value = value
        self.calls = 0
        self.change = None

    def observe(self, request):
        self.calls += 1
        if self.change is not None:
            self.change(self, request)
        return copy.deepcopy(self.value)


class HelperTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        child = subprocess.run(['php', str(ROOT / 'tests/support/WebsitePlatformM6EArtifactChild.php')],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=45, check=False)
        if child.returncode != 0 or child.stderr or len(child.stdout) > 1048576:
            raise RuntimeError('M6C fixture child failed: ' + repr((child.returncode, child.stderr[:500])))
        cls.fixture = json.loads(child.stdout)

    def setUp(self):
        temp_root = '\\\\?\\' + tempfile.gettempdir() if os.name == 'nt' else tempfile.gettempdir()
        self.temp = tempfile.TemporaryDirectory(prefix='ubo-m6e-test-', dir=temp_root)
        self.addCleanup(self.temp.cleanup)
        self.source = Files(Path(self.temp.name) / 'source')
        self.served = Files(Path(self.temp.name) / 'served')
        self.journal = Files(Path(self.temp.name) / 'journal')
        self.release = copy.deepcopy(self.fixture['release'])
        self.path = self.release['site_key'] + '/' + self.release['release_key']
        for path, data in self.fixture['files'].items():
            decoded = base64.b64decode(data)
            self.source.write_new(self.path + '/' + path, decoded)
            if path.startswith('public/'):
                self.served.write_new('releases/' + self.release['release_key'] + '/' + path, decoded)
        self.journal.write_new('manifests/' + self.release['release_key'] + '.json',
                               self.source.read(self.path + '/metadata/manifest.json', 1048576))
        self.binding = dict(site_id=self.release['site_id'], target_id=20, site_key=self.release['site_key'], binding_key='fixture-staging', binding_version=1)
        self.target = {k: self.binding[k] for k in ('site_id', 'target_id', 'binding_key', 'binding_version')} | {
            'environment': 'staging', 'publisher_key': 'apache-digitalocean-v1'}
        self.lease = dict(site_id=self.release['site_id'], target_id=20, deployment_id=100, attempt_id=101, attempt_kind='execution',
                          worker_id='fixture-worker', environment='staging', token='a'*64, fence_epoch=1)
        self.a = self.binding | dict(deployment_key='11111111-1111-4111-8111-111111111111', deployment_id=100, attempt_id=101,
             producer_attempt_id=101, attempt_kind='execution', fence_epoch=1, operation='publish', status='running', outcome='uncommitted',
             live=True, permitted=['stage', 'inspect', 'probe'], activation_fence=None, release=self.release, previous=None, expires_in=120)
        self.authority = Authority(self.a)
        self.pub = ModelPublisher(self.source, self.served, self.journal, self.authority, self.binding)
        self.journal.replace_json('state.json', dict(version=1, fence=0, owner=None, session=None, phase=0, current=None, intent=None, settled=True))
        self.seq = 0

    def request(self, operation, payload=None):
        self.seq += 1
        return dict(version=1, sequence=self.seq, operation=operation, target=self.target, lease=copy.deepcopy(self.lease), payload=payload or {})

    def context(self, phase):
        return {k: self.lease[k] for k in ('site_id', 'target_id', 'deployment_id', 'attempt_id', 'attempt_kind', 'worker_id', 'environment', 'fence_epoch')} | {
            'deployment_key': self.a['deployment_key'], 'producer_attempt_id': self.a['producer_attempt_id'], 'lease_token': self.lease['token'],
            'lease_expires_at': '2030-01-01 00:00:00.000000', 'deadline_at': '2030-01-01 00:01:00.000000',
            'binding_version': 1, 'release_key': self.release['release_key'], 'artifact_hash': self.release['artifact_hash'],
            'operation': self.a['operation'], 'phase': phase, 'phase_timeout_seconds': 30, 'connect_timeout_seconds': 2, 'request_timeout_seconds': 5,
            'correlation_id': '22222222-2222-4222-8222-222222222222'}

    def expected(self, absent=False):
        return dict(site_key=self.release['site_key'], release_id=None if absent else self.release['release_id'],
                    release_key=None if absent else self.release['release_key'], artifact_hash=None if absent else self.release['artifact_hash'],
                    deployment_id=None if absent else 100)

    def stage(self):
        return self.pub.dispatch(self.request('stage', dict(release=self.release, context=self.context('stage'))))

    def activate(self):
        staged = self.stage()
        self.a.update(status='activation_attempted', activation_fence=1, permitted=['activate', 'inspect', 'probe'])
        r = self.request('activate', dict(staged=staged, expected=self.expected(True), context=self.context('activation')))
        return self.pub.dispatch(r), r

    def recover(self):
        self.lease.update(attempt_id=102, attempt_kind='recovery', fence_epoch=2, token='b'*64)
        self.a.update(attempt_id=102, attempt_kind='recovery', fence_epoch=2, status='reconciliation_required', permitted=['compensate', 'inspect', 'probe'])
        self.pub = ModelPublisher(self.source, self.served, self.journal, self.authority, self.binding)
        self.seq = 0

    def compensation(self):
        observed = self.pub.dispatch(self.request('inspect'))
        return self.request('compensate', dict(context=self.context('compensation'), intent=dict(action='compensate', original_execution_id=101,
            observed=observed, expected_previous=self.expected(True), activation_fence=1)))

    def test_real_m6c_manifest_and_hash_contract(self):
        m = p.verify_package(self.source, self.path, self.release)
        self.assertNotEqual(m['artifact_hash'], m['public_payload_hash'])
        self.assertEqual(m['artifact_hash'], self.release['artifact_hash'])

    def test_tamper_and_inventory_reject_after_positive_baseline(self):
        p.verify_package(self.source, self.path, self.release)
        for change in ('tamper', 'unlisted', 'foreign', 'manifest', 'artifact-hash'):
            with self.subTest(change=change):
                home = self.source.root / self.path / 'public/index.html'
                original = home.read_bytes()
                release = copy.deepcopy(self.release)
                extra = self.source.root / self.path / 'public/unlisted.txt'
                try:
                    if change == 'tamper': home.write_bytes(b'tamper')
                    elif change == 'unlisted': extra.write_bytes(b'x')
                    elif change == 'foreign': release['site_key'] = '33333333-3333-4333-8333-333333333333'
                    elif change == 'manifest': release['manifest_hash'] = '0'*64
                    else: release['artifact_hash'] = '0'*64
                    with self.assertRaises(p.Denied): p.verify_package(self.source, self.path, release)
                finally:
                    home.write_bytes(original)
                    if extra.exists(): extra.unlink()

    def test_protocol_bounds_identity_secrets(self):
        r = self.request('stage', dict(release=self.release, context=self.context('stage')))
        self.assertEqual(p.request(p.canonical(r)), r)
        for mutate in (lambda x: x.update(command='whoami'), lambda x: x['target'].update(environment='production'),
                       lambda x: x['lease'].update(token='secret'), lambda x: x['payload']['context'].update(attempt_id=500),
                       lambda x: x['payload'].update(path='/tmp/anything')):
            bad = copy.deepcopy(r); mutate(bad)
            with self.assertRaises(p.Denied): p.request(p.canonical(bad))
        with self.assertRaises(p.Denied): p.request(b'x' * 16385)
        with self.assertRaises(p.Denied): p.decode(b'{"x":1,"x":2}')
        self.seq = 0
        self.assertNotIn(self.lease['token'], p.canonical(self.stage()).decode())
        self.assertNotIn(self.lease['token'], (self.journal.root / 'state.json').read_text())

    def test_immutable_existing_release_replay(self):
        before = {str(f): f.read_bytes() for f in self.served.root.rglob('*') if f.is_file()}
        one = self.stage(); two = self.stage()
        self.assertEqual(one, two)
        self.assertEqual(before, {str(f): f.read_bytes() for f in self.served.root.rglob('*') if f.is_file()})
        self.assertIsNone(self.served.link())

    def test_new_stage_copies_only_public_inventory_and_retains_assets(self):
        target = self.served.root / 'releases' / self.release['release_key']
        self.assertTrue(target.is_relative_to(self.served.root))
        shutil.rmtree(target)  # Exact private test-owned release only.
        self.assertEqual(self.stage()['result'], 'pass')
        self.assertFalse((target / 'metadata').exists())
        self.assertEqual(self.served.link('candidate'), self.release['release_key'])
        for entry in p.decode(self.source.read(self.path + '/metadata/manifest.json', 1048576))['files']:
            if entry['relative_path'].startswith('public/assets/'):
                self.assertEqual(self.served.read(entry['relative_path'][7:], entry['byte_size']), self.source.read(self.path + '/' + entry['relative_path'], entry['byte_size']))

    def test_stage_storage_fault_and_conflicting_target(self):
        target = self.served.root / 'releases' / self.release['release_key']
        self.assertTrue(target.is_relative_to(self.served.root))
        shutil.rmtree(target)
        self.served.fail = 'write'
        with self.assertRaises(OSError): self.stage()
        self.assertIsNone(self.served.link('candidate'))
        self.served.fail = None
        target.mkdir(parents=True)
        with self.assertRaises((p.Denied, FileNotFoundError)): self.stage()

    def test_fences_and_conflicting_session(self):
        self.stage(); state = copy.deepcopy(self.pub.state)
        self.assertEqual(p.fence(state, self.a, 'stage', self.pub.session)['fence'], 1)
        for changes in ({'fence_epoch': 0}, {'attempt_id': 444}, {'deployment_id': 444}, {'producer_attempt_id': 444}):
            with self.assertRaises(p.Denied): p.fence(state, self.a | changes, 'stage', self.pub.session)
        with self.assertRaises(p.Denied): p.fence(state, self.a, 'stage', 'another-process')
        recovery = self.a | dict(fence_epoch=2, attempt_id=102, attempt_kind='recovery')
        self.assertEqual(p.fence(state, recovery, 'inspect', 'recovery')['fence'], 2)
        with self.assertRaises(p.Denied): p.fence(state, recovery, 'activate', 'recovery')
        with self.assertRaises(p.Denied): p.fence(state, self.a | dict(fence_epoch=2), 'stage', 'new-execution')

    def test_activation_lock_model_settlement_and_replay(self):
        receipt, r = self.activate()
        self.assertEqual(self.pub.lock, 'MODELED-flock')
        self.assertEqual(self.served.link(), self.release['release_key'])
        replay = copy.deepcopy(r); self.seq += 1; replay['sequence'] = self.seq
        self.assertEqual(self.pub.dispatch(replay), receipt)
        self.a.update(status='succeeded', outcome='succeeded', live=False)
        self.assertTrue(self.pub.dispatch(self.request('settle'))['settled'])

    def test_commit_uncertainty_never_blindly_compensates(self):
        self.activate()
        self.assertFalse(self.pub.dispatch(self.request('settle'))['settled'])
        self.assertEqual(self.served.link(), self.release['release_key'])
        self.recover(); self.a.update(outcome='succeeded')
        with self.assertRaises(p.Denied): self.pub.dispatch(self.compensation())
        self.assertEqual(self.served.link(), self.release['release_key'])

    def test_first_deployment_exact_compensation_and_absence(self):
        self.activate(); self.recover()
        self.assertEqual(self.pub.dispatch(self.compensation())['phase'], 'compensation')
        self.assertIsNone(self.served.link())
        self.assertEqual(self.pub.dispatch(self.request('probe', dict(expected=self.expected(True), context=self.context('rollback')))), {'no_current_link': True})

    def test_known_good_restore_preserves_original_release_bytes(self):
        self.activate()
        previous = p.identity(self.a)
        self.a.update(outcome='succeeded', live=False)
        self.assertTrue(self.pub.dispatch(self.request('settle'))['settled'])
        self.lease.update(deployment_id=200, attempt_id=201, fence_epoch=2, token='b'*64)
        self.a.update(deployment_id=200, attempt_id=201, producer_attempt_id=201, fence_epoch=2, operation='restore', status='running',
                      outcome='uncommitted', live=True, permitted=['stage', 'inspect', 'probe'], previous=previous, activation_fence=None)
        self.pub = ModelPublisher(self.source, self.served, self.journal, self.authority, self.binding)
        self.seq = 0
        staged = self.stage()
        self.a.update(status='activation_attempted', activation_fence=2, permitted=['restore', 'inspect', 'probe'])
        context = self.context('activation'); context['operation'] = 'restore'
        expected = {k: previous[k] for k in ('site_key', 'release_id', 'release_key', 'artifact_hash', 'deployment_id')}
        receipt = self.pub.dispatch(self.request('restore', dict(staged=staged, expected=expected, context=context)))
        self.assertEqual(receipt['result'], 'pass')
        self.assertEqual(self.pub.dispatch(self.request('inspect'))['deployment_id'], 200)
        self.assertEqual(self.pub.verify_served(previous)['artifact_hash'], self.release['artifact_hash'])

    def test_stale_authority_and_pointer_cas(self):
        staged = self.stage()
        self.a.update(status='activation_attempted', activation_fence=1, permitted=['activate'])
        r = self.request('activate', dict(staged=staged, expected=self.expected(True), context=self.context('activation')))
        self.authority.change = lambda a, _: a.value.update(live=False) if a.calls >= 4 else None
        with self.assertRaises(p.Denied): self.pub.dispatch(r)
        self.assertIsNone(self.served.link())
        self.assertIsNotNone(self.pub.state['intent'])

    def test_journal_and_switch_failures_preserve_prior_pointer(self):
        for fault in ('journal', 'switch'):
            with self.subTest(fault=fault):
                self.setUp()
                staged = self.stage()
                self.a.update(status='activation_attempted', activation_fence=1, permitted=['activate'])
                (self.journal if fault == 'journal' else self.served).fail = fault
                with self.assertRaises(OSError):
                    self.pub.dispatch(self.request('activate', dict(staged=staged, expected=self.expected(True), context=self.context('activation'))))
                self.assertIsNone(self.served.link())

    def test_linux_entry_denies_windows_before_effects(self):
        if sys.platform == 'linux': self.skipTest('Windows unsupported-platform assertion only')
        with self.assertRaises(p.Denied): p.load_config()
        with self.assertRaises(p.Denied): p.Tree('/not-a-real-root', 1001)

    def test_malicious_path_grammar(self):
        self.assertEqual(p.relative('public/assets/' + 'a'*64 + '.png')[0], 'public')
        for path in ('../x', '/etc/passwd', 'public//x', 'public/./x', 'public/../x', 'public\\x', 'public/.htaccess', 'public/x\x00'):
            with self.assertRaises(p.Denied): p.relative(path)

    def test_corrupt_journal_and_foreign_pointer_block(self):
        self.stage()
        state = copy.deepcopy(self.pub.state)
        for mutate in (lambda s: s.update(fence=True), lambda s: s.update(owner=None),
                       lambda s: s.update(session='corrupt'), lambda s: s.update(intent={'operation': 'unknown'})):
            broken = copy.deepcopy(state); mutate(broken)
            with self.assertRaises(p.Denied): p.fence(broken, self.a, 'inspect', self.pub.session)
        self.served.links['current'] = '44444444-4444-4444-8444-444444444444'
        with self.assertRaises(p.Denied): self.pub.dispatch(self.request('inspect'))

    def test_primary_error_survives_reporting_and_cleanup_faults(self):
        with mock.patch.object(p, 'load_config', side_effect=p.Denied('PRIMARY-PRIVATE')):
            with mock.patch.object(p.os, 'write', side_effect=OSError('SECONDARY-REPORT')):
                self.assertEqual(p.main(), 1)
        obj = object.__new__(p.Publisher)
        obj.lock = 1234
        obj.source, obj.served, obj.journal = mock.Mock(), mock.Mock(), mock.Mock()
        obj.source.close.side_effect = OSError('SECONDARY-TREE')
        with mock.patch.object(p.os, 'close', side_effect=OSError('PRIMARY-CLOSE')):
            with self.assertRaisesRegex(OSError, 'PRIMARY-CLOSE'): obj.close()
        self.assertIsNone(obj.lock)
        obj.source.close.assert_called_once()
        obj.served.close.assert_called_once()
        obj.journal.close.assert_called_once()


if __name__ == '__main__':
    unittest.main(verbosity=2)
