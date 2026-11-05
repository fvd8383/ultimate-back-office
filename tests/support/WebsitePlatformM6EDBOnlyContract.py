"""M6E DB-only component contract. Definitions only; no engine, SQL or host effects."""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import struct

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys

PROFILE = 'm6e-db-only-v1'
SOURCE = '/opt/ubo-m6e-db-only'
ROOT = '/run/ubo-m6e-db-only'
BASE = '/mnt/ubo_stage_testdata/codex-validation'
EXPORTS = BASE + '/checkouts/m6e-db-only'
EVIDENCE = BASE + '/evidence'
CLIENT = ['/usr/bin/docker', '--config', '/run/user/1000/ubo-m6c-client',
          '--host', 'unix:///run/user/1000/docker.sock']
LABEL = 'org.ubo.m6e.db-only'
AUTHORITY = dict(admission='UNIMPLEMENTED', native_admission='BLOCKED', deployment_authority='NONE')
LIMITS = dict(seconds=1200, cleanup_seconds=120, snapshot_seconds=45,
              restoration_seconds=240, eligibility_seconds=120, cpu=1000000000,
              runtime_memory=512*1024**2, runtime_pids=32, mysql_memory=1536*1024**2,
              mysql_pids=128, mysql_data=512*1024**2, work=128*1024**2,
              control=32*1024**2, evidence=16*1024**2, tmp=16*1024**2,
              mysql_tmp=64*1024**2, mysql_run=16*1024**2, mysql_tls=1024**2,
              retained=32*1024**2, php_memory=256*1024**2)
CASES = ('positive', 'snapshot-concurrency', 'identity-grants', 'denied-dml',
         'source-hash', 'approval', 'snapshot-corrupt', 'snapshot-incomplete',
         'restoration-corrupt', 'transaction-interrupt',
         'before-schema', 'before-notes', 'before-artifact',
         'after-schema', 'after-notes', 'after-artifact',
         'publication-failure', 'capture-cleanup-failure')
# Every case gets its own instance, schemas, artifacts, invocation and immutable captures.
ROLES = {'operator': (0, 0), 'builder': (2000, 2000), 'snapshot': (2001, 2001),
         'restorer': (2107, 2107), 'eligibility': (2107, 2107), 'observer': (0, 0)}


def request(value):
    keys(value, ('profile', 'source_sha', 'source_inventory_sha256', 'runtime_image',
                 'mysql_image', 'run_id', 'case', 'review_receipt_sha256', 'limits'))
    need(value['profile'] == PROFILE and value['case'] in CASES, 'db_only_profile_case')
    for key, pattern in (('source_sha', '[a-f0-9]{40}'), ('run_id', '[a-f0-9]{32}'),
                         ('source_inventory_sha256', '[a-f0-9]{64}'), ('review_receipt_sha256', '[a-f0-9]{64}'),
                         ('runtime_image', 'sha256:[a-f0-9]{64}'), ('mysql_image', 'sha256:[a-f0-9]{64}')):
        need(type(value[key]) is str and re.fullmatch(pattern, value[key]), 'db_only_binding')
    need(value['runtime_image'] != value['mysql_image'] and value['limits'] == LIMITS, 'db_only_limits')
    need(all(type(v) is int for v in value['limits'].values()), 'db_only_limit_types')
    return value


def names(req):
    request(req)
    return {role: 'ubo-m6e-db-' + req['run_id'] + '-' + role for role in ('runtime', 'mysql')}


SNAPSHOT_FAILURES = tuple('snapshot_'+s for s in ('admission','before','lock','schema','transaction','rows','commit','unlock','after'))
SNAPSHOT_WRAPPER_FAILURES = tuple('db_only_snapshot_'+s for s in ('connection','session_publication','capture','data_publication',
    'db_close','source_close','outcome_invalid','outcome_publication'))


def snapshot_record(raw, invocation, pid):
    """Diagnostic only, including after expiry; exact original invocation never renews authority."""
    import WebsitePlatformM6ESnapshot as snapshot
    need(len(raw)<=8192,'db_only_snapshot_record_bound');v=decode(raw)
    keys(v,('profile','invocation','process','attempt','outcome','first_failure','secondary',*AUTHORITY))
    need(v['profile']=='m6e-db-only-snapshot-outcome-v1' and v['invocation']==invocation,'db_only_snapshot_record_binding')
    need(type(pid) is int and 0<pid<2**31 and v['process']==dict(pid=pid,uid=2001,gid=2001,owner_pid=invocation['owner_pid'])
         and all(type(n) is int for n in v['process'].values()),'db_only_snapshot_record_process')
    need(all(v[k]==value for k,value in AUTHORITY.items()),'db_only_snapshot_record_authority')
    need(v['attempt'] in ('UNREACHED','THREW','RETURNED') and v['first_failure'] in (None,*SNAPSHOT_FAILURES,*SNAPSHOT_WRAPPER_FAILURES)
         and type(v['secondary']) is list and len(v['secondary'])<=4
         and all(s in SNAPSHOT_WRAPPER_FAILURES for s in v['secondary']),'db_only_snapshot_record_fields')
    if v['outcome'] is not None:
        outcome=snapshot.outcome_value(canonical(v['outcome']))  # Existing shared bounded outcome validator.
        need(outcome['verification']=='UNREACHED' and outcome['first_failure'] in (None,*SNAPSHOT_FAILURES)
             and len(outcome['secondary'])<=2 and all(x in ('snapshot_rollback','snapshot_unlock') for x in outcome['secondary']),
             'db_only_snapshot_outcome_vocabulary')
        need(v['attempt']!='UNREACHED' and (outcome['first_failure'] is None or v['first_failure']==outcome['first_failure']),
             'db_only_snapshot_original_failure')
        if outcome['capture']=='CAPTURED':
            need(v['attempt']=='RETURNED' and outcome['first_failure'] is None and not outcome['secondary']
                 and outcome['transaction_cleanup']=='RELEASED','db_only_snapshot_returned_outcome')
        else:need(v['attempt']=='THREW' and outcome['first_failure'] in SNAPSHOT_FAILURES,'db_only_snapshot_failed_outcome')
    else:need(v['attempt']!='RETURNED','db_only_snapshot_missing_return')
    need(v['first_failure'] is not None or v['attempt']=='RETURNED' and not v['secondary'],'db_only_snapshot_failure_state')
    return v


def mount_limits(role):
    if role == 'runtime':
        return {ROOT: LIMITS['control'], '/work': LIMITS['work'], '/evidence': LIMITS['evidence'], '/tmp': LIMITS['tmp']}
    need(role == 'mysql', 'db_only_role')
    return {'/var/lib/mysql': LIMITS['mysql_data'], '/tmp': LIMITS['mysql_tmp'],
            '/run/mysqld': LIMITS['mysql_run'], '/tls': LIMITS['mysql_tls']}


def create_args(req, role, runtime_id=None, expires_at=1):
    """Fixed argv only. No user command, network, mount, port, volume or credential arguments."""
    request(req); need(role in ('runtime', 'mysql') and type(expires_at) is int and 0<expires_at<2**63, 'db_only_role_expiry')
    args = ['create', '--name', names(req)[role], '--label', LABEL+'='+req['run_id'],
            '--label', LABEL+'.request='+digest(canonical(req)), '--read-only', '--log-driver', 'none',
            '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges', '--cpus', '1',
            '--memory', str(LIMITS[role+'_memory']), '--memory-swap', str(LIMITS[role+'_memory']),
            '--pids-limit', str(LIMITS[role+'_pids']), '--restart', 'no', '--ulimit', 'core=0:0']
    for path, size in mount_limits(role).items():
        uid = 999 if role == 'mysql' else 0
        mode = '1777' if path == '/tmp' else '0700'
        args += ['--tmpfs', f'{path}:rw,nosuid,nodev,noexec,size={size},mode={mode},uid={uid},gid={uid}']
    if role == 'runtime':
        # Namespace-root supervisor only. All non-root PHP workers must measure zero capabilities.
        for cap in ('CHOWN', 'DAC_OVERRIDE', 'SETUID', 'SETGID', 'KILL'):
            args += ['--cap-add', cap]
        args += ['--network', 'none', '--user', '0:0', '--mount',
                 'type=bind,src='+EXPORTS+'/'+req['source_sha']+',dst='+SOURCE+',readonly',
                 '--entrypoint', '/usr/bin/python3', req['runtime_image'], '-I', '-B',
                 SOURCE+'/tests/support/WebsitePlatformM6EDBOnlyRuntime.py', '--hold', str(expires_at)]
    else:
        need(type(runtime_id) is str and re.fullmatch('[a-f0-9]{64}', runtime_id), 'db_only_network_owner')
        args += ['--network', 'container:'+runtime_id, '--user', '999:999', '--entrypoint', '/bin/sh',
                 req['mysql_image'], '-c', MYSQL_START, 'm6e-db-only', str(expires_at)]
    return args


# Fixed program; no request strings, SQL, paths or credentials are interpolated here.
# Independent in-container lifetime prevents a lost operator client from leaving a running DB.
MYSQL_BODY = '''set -eu
test "$(id -u mysql)" = 999
test "$(id -g mysql)" = 999
test -x /usr/bin/timeout
timeout -s TERM -k 10 150 /bin/sh -c 'until test -f /tls/ready; do sleep 1; done'
timeout -s TERM -k 10 120 mysqld --no-defaults --initialize-insecure --datadir=/var/lib/mysql --log-error=/tmp/init.log
exec mysqld --no-defaults --datadir=/var/lib/mysql --socket=/run/mysqld/mysql.sock --pid-file=/run/mysqld/mysql.pid --bind-address=127.0.0.1 --port=3306 --skip-name-resolve --mysqlx=OFF --require-secure-transport=ON --ssl-ca=/tls/ca.pem --ssl-cert=/tls/server.pem --ssl-key=/tls/server-key.pem --innodb-buffer-pool-size=134217728 --innodb-redo-log-capacity=33554432 --max-connections=12 --temptable-max-ram=33554432 --tmp-table-size=16777216 --max-heap-table-size=16777216 --skip-log-bin --log-error=/tmp/mysql.log
'''
import shlex
MYSQL_START = 'set -eu\nremaining=$(($1 - $(date +%s)))\ntest "$remaining" -gt 0\ntest "$remaining" -le 1200\nexec timeout -s TERM -k 10 "$remaining" /bin/sh -c '+shlex.quote(MYSQL_BODY)


RUNTIME_CAPABILITIES = ('CHOWN', 'DAC_OVERRIDE', 'SETUID', 'SETGID', 'KILL')


def capability_additions(value):
    """Compare Docker's optional CAP_ spelling without changing inspection evidence."""
    if value is None:return []
    need(type(value) is list and len(value)<=len(RUNTIME_CAPABILITIES), 'db_only_caps')
    normalized=[]
    for name in value:
        need(type(name) is str, 'db_only_caps')
        if name.startswith('CAP_'):name=name[4:]
        need(name in RUNTIME_CAPABILITIES and name not in normalized, 'db_only_caps')
        normalized.append(name)
    return sorted(normalized)


def container(value, req, role, runtime_id=None, expires_at=1):
    """Inspect exact owned IDs before use/removal, never names alone or a label-only match."""
    request(req); need(role in ('runtime', 'mysql'), 'db_only_role')
    need(re.fullmatch('[a-f0-9]{64}', value['Id']) and value['Name'] == '/'+names(req)[role], 'db_only_container_id')
    c = value['Config']; h = value['HostConfig']
    argv=create_args(req,role,runtime_id,expires_at);image_at=argv.index(req[role+'_image'])
    need(c['Entrypoint']==[argv[argv.index('--entrypoint')+1]] and c['Cmd']==argv[image_at+1:], 'db_only_container_program')
    need(value['Image'] == req[role+'_image'] and c['Labels'].get(LABEL) == req['run_id']
         and c['Labels'].get(LABEL+'.request') == digest(canonical(req)), 'db_only_container_binding')
    need(h['ReadonlyRootfs'] is True and h['Privileged'] is False and h['NetworkMode'] ==
         ('none' if role == 'runtime' else 'container:'+runtime_id), 'db_only_container_isolation')
    need(h['Memory'] == h['MemorySwap'] == LIMITS[role+'_memory'] and h['NanoCpus'] == LIMITS['cpu']
         and h['PidsLimit'] == LIMITS[role+'_pids'], 'db_only_container_resources')
    need(h['CapDrop'] == ['ALL'] and capability_additions(h.get('CapAdd')) ==
         (sorted(RUNTIME_CAPABILITIES) if role == 'runtime' else []), 'db_only_caps')
    need(h['SecurityOpt'] == ['no-new-privileges'] and not h.get('PortBindings')
         and not h.get('Devices') and not h.get('VolumesFrom') and not h.get('Binds')
         and h['PidMode'] == '' and h.get('UsernsMode', '') == ''
         and h['RestartPolicy']['Name'] == 'no' and h['LogConfig']['Type'] == 'none', 'db_only_host_exposure')
    mounts = value['Mounts']; binds = [m for m in mounts if m['Type'] == 'bind']
    need(len(binds) == (1 if role == 'runtime' else 0) and all(m['Type'] in ('bind', 'tmpfs') for m in mounts), 'db_only_mounts')
    if binds:
        need(binds[0]['Source'] == EXPORTS+'/'+req['source_sha'] and binds[0]['Destination'] == SOURCE
             and binds[0]['RW'] is False, 'db_only_source_mount')
    need(set(h['Tmpfs']) == set(mount_limits(role)), 'db_only_tmpfs_inventory')
    for path, size in mount_limits(role).items():
        options = set(h['Tmpfs'][path].split(','))
        need({'rw', 'nosuid', 'nodev', 'noexec', 'size='+str(size)} <= options, 'db_only_tmpfs_limit')
    need(c['User'] == ('0:0' if role == 'runtime' else '999:999'), 'db_only_container_user')
    return value['Id']


def measurements(v, role, host_namespaces):
    """Actual kernel measurements, in addition to Docker configuration claims."""
    need(v['memory_max'] == LIMITS[role+'_memory'] and v['swap_max'] == 0
         and v['pids_max'] == LIMITS[role+'_pids'] and v['cpu_max'] == [100000, 100000], 'db_only_cgroup_limits')
    mapping = v['uid_map']; gmap = v['gid_map']
    need(len(mapping) == len(gmap) == 2 and mapping[0] == gmap[0] == [0, 1000, 1]
         and mapping[1][0] == gmap[1][0] == 1 and mapping[1][1] >= 65536 and gmap[1][1] >= 65536
         and mapping[1][2] >= 65536 and gmap[1][2] >= 65536, 'db_only_rootless_mapping')
    need(v['uid'] == v['gid'] == (0 if role == 'runtime' else 999) and v['no_new_privs'] == 1, 'db_only_measured_user')
    need(v['capabilities']==(227 if role=='runtime' else 0) and v['interfaces']==['lo'], 'db_only_measured_caps_network')
    need(set(v['namespaces']) == set(host_namespaces) == {'user', 'net', 'mnt', 'pid'}, 'db_only_namespaces')
    for kind, identity in v['namespaces'].items():
        need(type(identity) is str and re.fullmatch(kind+r':\[[0-9]+\]', identity)
             and identity != host_namespaces[kind], 'db_only_host_namespace')
    need(set(v['storage']) == set(mount_limits(role)), 'db_only_storage_inventory')
    for path, size in mount_limits(role).items():
        m = v['storage'][path]
        need(m['type'] == 'tmpfs' and m['bytes'] == size and m['size_option'] == size
             and {'rw', 'nosuid', 'nodev', 'noexec'} <= set(m['options']), 'db_only_actual_storage')
    need(v['source_readonly'] is True if role == 'runtime' else True, 'db_only_actual_source_mount')
    if role=='runtime':
        root=v['source_root'];need(root['uid']==root['gid']==0 and root['mode']==0o555 and root['ino']>0,'db_only_source_inode_owner')
    return v


# One exclusive fixed-size host file is the ENTIRE per-run retained store. No sidecar logs,
# growing journals, temporary copies or directory trees. Slots are write-once, hash chained.
SLOT = 128*1024
HEADER = struct.Struct('!8sII32s32s')
MAGIC = b'M6EDB001'


class Retained:
    def __init__(self, path, create=False, readonly=False):
        self.path = Path(path)
        need(not(create and readonly),'db_only_retained_mode');self.readonly=readonly
        flags = (os.O_RDONLY if readonly else os.O_RDWR) | getattr(os, 'O_NOFOLLOW', 0) | getattr(os, 'O_BINARY', 0)
        if create: flags |= os.O_CREAT | os.O_EXCL
        self.fd = os.open(self.path, flags, 0o600)
        try:
            if os.name=='posix' and not readonly:
                import fcntl
                fcntl.flock(self.fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
            self.id = os.fstat(self.fd)
            need(stat.S_ISREG(self.id.st_mode) and self.id.st_nlink == 1
                 and stat.S_IMODE(self.id.st_mode) == 0o600, 'db_only_retained_identity')
            if create:
                need(self.id.st_size == 0, 'db_only_retained_empty')
                os.ftruncate(self.fd, LIMITS['retained'])
                # Linux execution must allocate the ceiling up front; unsupported storage rejects.
                if os.name == 'posix': os.posix_fallocate(self.fd, 0, LIMITS['retained'])
                os.fsync(self.fd)
                if os.name == 'posix':
                    parent=os.open(self.path.parent,os.O_RDONLY|os.O_DIRECTORY)
                    try:os.fsync(parent)
                    finally:os.close(parent)
            need(os.fstat(self.fd).st_size == LIMITS['retained'], 'db_only_retained_size')
            self.records = self.read(); self.verify()
        except BaseException:
            os.close(self.fd); raise

    def verify(self):
        s = self.path.lstat(); f = os.fstat(self.fd)
        need((s.st_dev, s.st_ino) == (f.st_dev, f.st_ino) == (self.id.st_dev, self.id.st_ino)
             and s.st_uid==self.id.st_uid and stat.S_IMODE(s.st_mode)==0o600
             and s.st_nlink == 1 and s.st_size == LIMITS['retained'], 'db_only_retained_replaced')

    def read(self):
        rows = []; previous = bytes(32); empty = False
        for slot in range(LIMITS['retained']//SLOT):
            os.lseek(self.fd, slot*SLOT, os.SEEK_SET); raw = os.read(self.fd, SLOT)
            need(len(raw) == SLOT, 'db_only_retained_read')
            if not any(raw): empty = True; continue
            need(not empty, 'db_only_retained_gap')
            magic, number, length, sha, parent = HEADER.unpack(raw[:HEADER.size])
            need(magic == MAGIC and number == slot and 0 < length <= SLOT-HEADER.size and parent == previous, 'db_only_retained_header')
            body = raw[HEADER.size:HEADER.size+length]
            need(hashlib.sha256(body).digest() == sha and not any(raw[HEADER.size+length:]), 'db_only_retained_digest')
            rows.append(decode(body)); previous = sha
        return rows

    def append(self, record):
        need(not self.readonly,'db_only_retained_readonly')
        self.verify(); need(self.read() == self.records, 'db_only_retained_changed')
        raw = canonical(record); i = len(self.records)
        need(len(raw) <= SLOT-HEADER.size and i < LIMITS['retained']//SLOT, 'db_only_retained_full')
        parent = bytes(32) if not self.records else hashlib.sha256(canonical(self.records[-1])).digest()
        encoded = HEADER.pack(MAGIC, i, len(raw), hashlib.sha256(raw).digest(), parent)+raw
        need(i*SLOT+len(encoded) <= LIMITS['retained'], 'db_only_retained_ceiling')
        os.lseek(self.fd, i*SLOT, os.SEEK_SET)
        view = memoryview(encoded)
        while view:
            n = os.write(self.fd, view); need(n > 0, 'db_only_retained_write'); view = view[n:]
        os.fsync(self.fd); self.verify()
        need(self.read() == self.records+[record], 'db_only_retained_readback'); self.records.append(record)

    def close(self):
        os.close(self.fd)
