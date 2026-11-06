"""Actual PHP session -> bounded Python parser child. No admitted host/socket/SQL."""
import copy
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', ROOT / 'infrastructure/deployment/ubo_site_publish.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


def parsed(wires):
    child = subprocess.run([sys.executable, '-I', '-B', __file__, '--parse'], input=json.dumps(wires).encode(),
                           stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=15)
    if child.returncode or child.stderr or len(child.stdout) > 16384:
        raise AssertionError('bounded_parser_child_failed')
    return json.loads(child.stdout)


class WireTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        child = subprocess.run(['php', '-d', 'memory_limit=256M', str(ROOT / 'tests/support/WebsitePlatformM6EWireChild.php')],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)
        if child.returncode or child.stderr or len(child.stdout) > 1048576:
            raise AssertionError('bounded_php_producer_failed:' + child.stderr[:500].decode('utf-8', 'replace'))
        cls.fixture = json.loads(child.stdout)
        cls.cases = {c['label']: c for c in cls.fixture['cases']}

    def mutate(self, label, edit):
        r = json.loads(self.cases[label]['wire'])
        edit(r)
        return json.dumps(r)

    def test_actual_seven_operations_and_partial_transport(self):
        cases = [c for name, c in self.cases.items() if name != 'types-serializer-only']
        self.assertEqual({json.loads(c['wire'])['operation'] for c in cases},
                         {'stage', 'activate', 'restore', 'inspect', 'probe', 'compensate', 'settle'})
        self.assertEqual(parsed([c['wire'] for c in cases]), [True] * len(cases))
        for c in cases:
            self.assertTrue(c['wire'].endswith('\n') and c['wire'].count('\n') == 1)
            self.assertGreater(c['writes'], 1)
            self.assertGreater(c['reads'], 1)
            self.assertLessEqual(len(c['wire'].encode()), p.MAX_REQUEST)

    def test_empty_object_only(self):
        bad = []
        for name in ('inspect', 'settle'):
            self.assertEqual(json.loads(self.cases[name]['wire'])['payload'], {})
            for value in ([], None, 0, False, '', {'unexpected': None}):
                bad.append(self.mutate(name, lambda r, value=value: r.update(payload=value)))
        self.assertEqual(parsed(bad), [False] * len(bad))

    def test_service_correlation_contract(self):
        self.assertRegex(self.fixture['default_correlation'], r'^site:[0-9a-f]{32}$')
        self.assertEqual(parsed([self.cases[n]['wire'] for n in ('stage', 'correlation-uuid', 'correlation-custom', 'correlation-max')]), [True]*4)
        invalid = ['', 'a'*101, '\x00', '\n', ' trace', 'trace ', 'a b', 'a\tb', 'a/b', 'a@b', 1, None, True]
        wires = [self.mutate('stage', lambda r, value=value: r['payload']['context'].update(correlation_id=value)) for value in invalid]
        self.assertEqual(parsed(wires), [False]*len(wires))

    def test_identity_uuid_shape_and_bounds_remain_strict(self):
        self.assertTrue(self.fixture['oversize_denied_before_write'])
        self.assertEqual(json.loads(self.cases['compensate']['wire'])['lease']['attempt_kind'], 'recovery')
        edits = [lambda r: r['payload']['context'].update(deployment_key='site:trace'),
                 lambda r: r['payload']['context'].update(release_key='site:trace'),
                 lambda r: r['lease'].update(site_id=11), lambda r: r['lease'].update(token='wrong'),
                 lambda r: r['payload']['context'].update(attempt_id=999), lambda r: r['target'].update(environment='production'),
                 lambda r: r['target'].update(site_id=True), lambda r: r.update(sequence=0), lambda r: r.update(version=2),
                 lambda r: r.update(extra=True), lambda r: r['payload'].update(extra=[]),
                 lambda r: r['payload']['context'].pop('deadline_at'), lambda r: r.update(operation='shell')]
        wires = [self.mutate('stage', e) for e in edits]
        wires += ['{', '{"version":1,"version":1}', self.cases['stage']['wire'] + ' ' * p.MAX_REQUEST]
        self.assertEqual(parsed(wires), [False]*len(wires))

    def test_nullable_values_and_legitimate_lists_keep_types(self):
        nulls = json.loads(self.cases['probe-absent']['wire'])['payload']['expected']
        self.assertTrue(all(nulls[k] is None for k in ('release_id', 'release_key', 'artifact_hash', 'deployment_id')))
        types = json.loads(self.cases['types-serializer-only']['wire'])['payload']['fixture_types']
        self.assertEqual([type(x).__name__ for x in types], ['int', 'float', 'bool', 'bool', 'NoneType', 'str', 'list', 'dict'])
        self.assertEqual(types[-2:], [[], {'n': None}])
        self.assertEqual(parsed([self.cases['types-serializer-only']['wire']]), [False])


if __name__ == '__main__':
    if sys.argv[1:] == ['--parse']:
        raw = sys.stdin.buffer.read(1048577)
        p.require(len(raw) <= 1048576)
        wires = json.loads(raw)
        p.require(isinstance(wires, list) and len(wires) <= 256)
        results = []
        for wire in wires:
            try:
                p.request(wire.encode())
                results.append(True)
            except (p.Denied, TypeError, KeyError):
                results.append(False)
        print(json.dumps(results))
    else:
        unittest.main(verbosity=2)
