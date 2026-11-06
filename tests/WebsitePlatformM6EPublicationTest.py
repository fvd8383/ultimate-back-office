"""Production Tree publication with real private file I/O and explicit Windows syscall models."""
import importlib.util
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tests/support'))
from WebsitePlatformM6EFilesystem import FilesystemModel
spec = importlib.util.spec_from_file_location('publisher', ROOT / 'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


class PublicationTests(unittest.TestCase):
    def setUp(self):
        temp_root = '\\\\?\\' + tempfile.gettempdir() if os.name == 'nt' else tempfile.gettempdir()
        self.temp = tempfile.TemporaryDirectory(prefix='ubo-m6e-publication-', dir=temp_root)
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.ops = FilesystemModel(self.root)
        patch = mock.patch.object(p, 'os', self.ops)
        patch.start(); self.addCleanup(patch.stop)
        rename = mock.patch.object(p.Tree, '_rename_noreplace', staticmethod(self.ops.rename_noreplace))
        rename.start(); self.addCleanup(rename.stop)
        self.number = 0

    def fixture(self, served):
        self.number += 1
        root = self.root / str(self.number)
        root.mkdir()
        for name in ('assets', 'staging', 'manifests'):
            (root / name).mkdir()
        for name in ('current', 'candidate'):
            (root / name).write_bytes(b'unchanged-pointer-sentinel')
        tree = self.ops.tree(p, root)
        self.addCleanup(tree.close)
        data = b'body { color: #123456; }\n' if served else b'{"fixture":"complete immutable manifest"}'
        path = 'assets/' + p.digest(data) + '.css' if served else 'manifests/11111111-1111-4111-8111-111111111111.json'
        return tree, root, path, data

    def assert_pointers(self, root):
        for name in ('current', 'candidate'):
            self.assertEqual((root / name).read_bytes(), b'unchanged-pointer-sentinel')

    def leftovers(self, root):
        return list(root.rglob('immutable-*.pending'))

    def insert_winner(self, destination, data, served):
        destination.write_bytes(data)
        self.ops.meta[str(destination)] = [0o440 if served else 0o600, 1001, 1002]

    def test_complete_private_publication_and_exact_replay(self):
        for served in (True, False):
            with self.subTest(served=served):
                tree, root, path, data = self.fixture(served)
                def inspect(source, destination):
                    self.assertEqual(source.parent, root / 'staging' if served else root)
                    self.assertNotIn(source, list((root / 'assets').iterdir()))
                    self.assertEqual(source.read_bytes(), data)
                    self.assertFalse(destination.exists())
                    self.assertEqual(self.ops.meta[str(source)][0], 0o440 if served else 0o600)
                self.ops.before_publish = inspect
                tree.publish_immutable(path, data, served)
                self.ops.before_publish = None
                self.assertEqual(tree.read(path, len(data)), data)
                inode = (root / path).stat().st_ino
                tree.publish_immutable(path, data, served)
                self.assertEqual((root / path).stat().st_ino, inode)
                self.assertEqual(self.leftovers(root), [])
                self.assert_pointers(root)

    def test_existing_conflicting_or_partial_objects_are_never_repaired(self):
        for served in (True, False):
            for bytes_ in (b'wrong-complete-object', b'par'):
                with self.subTest(served=served, bytes_=bytes_):
                    tree, root, path, data = self.fixture(served)
                    self.insert_winner(root / path, bytes_, served)
                    with self.assertRaises(p.Denied):
                        tree.publish_immutable(path, data, served)
                    self.assertEqual((root / path).read_bytes(), bytes_)
                    self.assertEqual(self.leftovers(root), [])
                    self.assert_pointers(root)

    def test_prepublication_faults_leave_final_absent_and_clean_only_owned_temp(self):
        for served in (True, False):
            for fault in ('partial', 'zero', 'write', 'file_sync', 'chmod', 'publish', 'close') + (('chown',) if served else ()):
                with self.subTest(served=served, fault=fault):
                    tree, root, path, data = self.fixture(served)
                    baseline = b'baseline-' + data
                    baseline_path = 'assets/' + p.digest(baseline) + '.css' if served else 'manifests/22222222-2222-4222-8222-222222222222.json'
                    tree.publish_immutable(baseline_path, baseline, served)
                    baseline_inode = (root / baseline_path).stat().st_ino
                    self.ops.fault = fault
                    with self.assertRaises((OSError, p.Denied)):
                        tree.publish_immutable(path, data, served)
                    self.ops.fault = None
                    self.assertIn(('injected', fault), self.ops.events)
                    self.assertEqual((root / baseline_path).read_bytes(), baseline)
                    self.assertEqual((root / baseline_path).stat().st_ino, baseline_inode)
                    self.assertFalse((root / path).exists())
                    self.assertEqual(self.leftovers(root), [])
                    self.assert_pointers(root)
                    # A fresh exact object insertion succeeds; there is no poisoned permanent name.
                    tree.publish_immutable(path, data, served)
                    self.assertEqual((root / path).read_bytes(), data)

    def test_postpublication_directory_sync_failure_retains_complete_object(self):
        for served in (True, False):
            with self.subTest(served=served):
                tree, root, path, data = self.fixture(served)
                self.ops.fault = 'directory_sync'
                with self.assertRaisesRegex(OSError, 'injected_directory_sync'):
                    tree.publish_immutable(path, data, served)
                self.assertEqual((root / path).read_bytes(), data)
                self.assertEqual(self.leftovers(root), [])
                self.assert_pointers(root)
                self.ops.fault = None
                tree.publish_immutable(path, data, served)
                self.assertEqual((root / path).read_bytes(), data)

    def test_matching_and_conflicting_no_replace_winners(self):
        for served in (True, False):
            for matches in (True, False):
                with self.subTest(served=served, matches=matches):
                    tree, root, path, data = self.fixture(served)
                    winner = data if matches else b'foreign-winner'
                    self.ops.before_publish = lambda src, dst: self.insert_winner(dst, winner, served)
                    if matches:
                        tree.publish_immutable(path, data, served)
                    else:
                        with self.assertRaises(p.Denied):
                            tree.publish_immutable(path, data, served)
                    self.ops.before_publish = None
                    self.assertEqual((root / path).read_bytes(), winner)
                    self.assertEqual(self.leftovers(root), [])
                    self.assert_pointers(root)

    def test_primary_write_failure_survives_temporary_cleanup_failure(self):
        for served in (True, False):
            with self.subTest(served=served):
                tree, root, path, data = self.fixture(served)
                self.ops.fault = 'partial'; self.ops.cleanup_fault = True
                with self.assertRaisesRegex(OSError, '^injected_partial_write$'):
                    tree.publish_immutable(path, data, served)
                self.ops.fault = None; self.ops.cleanup_fault = False
                self.assertFalse((root / path).exists())
                leftovers = self.leftovers(root)
                self.assertEqual(len(leftovers), 1)
                self.assertEqual(leftovers[0].read_bytes(), data[:3])
                self.assertEqual(leftovers[0].parent, root / 'staging' if served else root)
                self.assert_pointers(root)
                tree.publish_immutable(path, data, served)
                self.assertEqual((root / path).read_bytes(), data)
                self.assertTrue(leftovers[0].exists())  # No broad prune of an earlier invocation.

    def test_wrong_identity_mode_and_private_directory_reject(self):
        tree, root, path, data = self.fixture(True)
        with self.assertRaises(p.Denied):
            tree.publish_immutable(path, b'wrong-digest', True)
        self.ops.meta[str(root / 'staging')] = [0o750, 1001, 1002]
        with self.assertRaises(p.Denied):
            tree.publish_immutable(path, data, True)
        self.ops.meta[str(root / 'staging')][0] = 0o700
        self.insert_winner(root / path, data, True)
        for field, value in ((0, 0o600), (1, 999), (2, 999)):
            self.ops.meta[str(root / path)] = [0o440, 1001, 1002]
            self.ops.meta[str(root / path)][field] = value
            with self.assertRaises(p.Denied):
                tree.publish_immutable(path, data, True)
            self.assertEqual((root / path).read_bytes(), data)
        self.assert_pointers(root)

    def test_cleanup_refuses_a_replaced_temporary_identity(self):
        for served in (True, False):
            with self.subTest(served=served):
                tree, root, path, data = self.fixture(served)
                replaced = []
                def swap(source, destination):
                    source.rename(root / 'held-original-inode')
                    source.write_bytes(b'foreign-private-temp')
                    replaced.append(source)
                    raise OSError('injected_publish_after_swap')
                self.ops.before_publish = swap
                with self.assertRaisesRegex(OSError, '^injected_publish_after_swap$'):
                    tree.publish_immutable(path, data, served)
                self.ops.before_publish = None
                self.assertFalse((root / path).exists())
                self.assertEqual(replaced[0].read_bytes(), b'foreign-private-temp')
                self.assertEqual((root / 'held-original-inode').read_bytes(), data)
                self.assert_pointers(root)


if __name__ == '__main__':
    unittest.main(verbosity=2)
