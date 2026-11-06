"""Commissioning evidence policy. No I/O, host admission, or native success by assertion.

This is deliberately a separate result from m6e-isolated-v2 acceptance. Incomplete
native producers remain explicit blockers, even if their available measurements pass.
"""
import copy
from WebsitePlatformM6EAcceptance import GATES, ROLES, canonical, decode, digest, need, keys, hash_, integer
from WebsitePlatformM6ECommissionServing import SOURCE_FIELDS

PROFILE = 'm6e-commissioning-v1'
PRODUCERS = {gate: 'commission-' + gate + '-v1' for gate in GATES}
# Executable steps still missing from the retained integration components. These
# are code policy, never an operator-provided list or a native-readiness stub.
MISSING = {
    'apache': ('retained-release-static-responses',),
    'filesystem': (),
    'ownership': (),
    'assets': ('two-release-transfer-retention-conflict',),
    'runtime': ('supervised-helper-lock-fence',),
    'health': ('candidate-active-fault-restoration',),
    'recovery': ('retained-state-interruption-authority-recovery',),
    'provisioning': ('owned-uncertain-reload-reconciliation',),
}
FIELDS = {
    'apache': ('active_status','candidate_status','unauthorized_status','modules_sha256','apache_sha256')+SOURCE_FIELDS,
    'filesystem': ('device','journal_device','mount_sha256','read_sha256','replay_sha256',
                   'conflict_rejected','link_rejected','replacement_rejected','reader_pid',
                   'reader_exit','old_observations','new_observations','other_observations','sync_calls'),
    'ownership': ('worker_source_errno','helper_source_errno','apache_source_errno',
                  'worker_served_errno','worker_journal_errno','apache_public_errno',
                  'apache_write_errno','source_mode','source_parent_mode','public_mode','public_sha256'),
    'assets': ('release_count','asset_count','foreign_site_id','served_assets')+SOURCE_FIELDS,
    'runtime': ('php_sha256','extensions_sha256','native_prepare_value','duplicate_parameter_rejected',
                'utc_equal','select_rows','update_errno','foreign_errno','before_sha256','after_sha256'),
    'health': ('active_status','candidate_status','current_present','candidate_present'),
    'recovery': ('journal_sha256','current_present','candidate_present','settled','fence'),
    'provisioning': ('baseline_exit','candidate_exit','reload_count','result','before_sha256','after_sha256'),
}

def measurements(gate, value):
    keys(value, FIELDS[gate])
    for name, item in value.items():
        if name.endswith('sha256'): hash_(item)
        elif name == 'result': need(item in ('REJECTED','UNKNOWN','VERIFIED'), 'provision_result')
        else: integer(item, 0)
    # Fixed predicates examine observations, not supplied PASS booleans or an
    # arbitrary assertion list. Partial gates still cannot authorize issuance.
    if gate in ('apache','assets'):
        need(value['source_releases']==2 and 6<=value['source_files']<=4000
             and 4<=value['source_assets']<value['source_files'] and value['source_bytes']>0,'serving_source_measurements')
        if gate=='apache':need(value['active_status']==value['candidate_status']==503
             and value['unauthorized_status'] in (0,400,401,403),'serving_absent_boundary')
        else:need(value['release_count']==2 and value['asset_count']==value['source_assets']
             and value['foreign_site_id']>0 and value['served_assets']==0,'serving_pristine_assets')
    elif gate == 'filesystem':
        need(value['device'] > 0 and value['journal_device'] > 0, 'measured_devices')
        need(value['read_sha256'] == value['replay_sha256'], 'immutable_replay')
        need(value['read_sha256']==digest(b'M6E nonsecret commissioning publication canary\n'),'filesystem_baseline_bytes')
        need(all(value[k] == 1 for k in ('conflict_rejected','link_rejected','replacement_rejected')), 'filesystem_negatives')
        need(value['reader_pid'] > 0 and value['reader_exit'] == value['other_observations'] == 0
             and value['old_observations'] > 0 and value['new_observations'] > 0 and value['sync_calls'] >= 2, 'atomic_observations')
    elif gate == 'ownership':
        need(value['worker_source_errno'] == value['apache_public_errno'] == 0, 'access_baseline')
        need(all(value[k] in (1,13) for k in ('helper_source_errno','apache_source_errno',
             'worker_served_errno','worker_journal_errno','apache_write_errno')), 'role_access_boundary')
        need((value['source_mode'],value['source_parent_mode'],value['public_mode']) == (0o600,0o700,0o440), 'access_modes')
        need(value['public_sha256']==digest(b'M6E public access canary\n'),'public_baseline_bytes')
    elif gate == 'runtime':
        need(value['native_prepare_value'] == 5 and value['duplicate_parameter_rejected'] == value['utc_equal'] == 1
             and value['select_rows'] == 1 and value['update_errno'] in (1044,1142)
             and value['foreign_errno'] in (1044,1142) and value['before_sha256'] == value['after_sha256'], 'runtime_measurements')
    elif gate == 'provisioning':
        need(value['baseline_exit'] == 0 and value['candidate_exit'] != 0 and value['reload_count'] == 0
             and value['result'] == 'REJECTED' and value['before_sha256'] == value['after_sha256'], 'failed_config_preservation')
    return value

def validate_binding(value):
    keys(value, ('source_sha','run_id','machine_id','boot_id','bootstrap_sha256','handoff_sha256',
                 'configuration_sha256','source_inventory_sha256','tools_sha256','modules_sha256',
                 'roles_sha256','roots_sha256','installed_sha256','expires_at'))
    for name, item in value.items():
        if name.endswith('sha256'): hash_(item)
    hash_(value['source_sha'],40); hash_(value['run_id'],32); hash_(value['machine_id'],32)
    import re
    need(type(value['boot_id']) is str and re.fullmatch('[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}',value['boot_id']), 'commission_boot')
    need(value['roles_sha256'] == digest(canonical(ROLES)), 'commission_roles')
    integer(value['expires_at'],1)

def validate_record(raw, expected, gate, now, native=True, complete=True):
    need(gate in GATES and type(raw) is bytes and len(raw) <= 65536, 'commission_record_bound')
    validate_binding(expected); value = decode(raw)
    keys(value, ('profile','version','gate','producer','execution','binding','started_at','finished_at',
                 'observations','observations_sha256','capture','cleanup','result','missing'))
    need(value['profile'] == PROFILE and type(value['version']) is int and value['version'] == 1 and value['gate'] == gate
         and value['producer'] == PRODUCERS[gate] and value['binding'] == expected, 'commission_record_binding')
    need(value['execution'] in ('native-components','controlled-local') and
         (not native or value['execution'] == 'native-components'), 'modeled_commissioning')
    integer(value['started_at'],1); integer(value['finished_at'],value['started_at'])
    need(value['finished_at'] <= now < expected['expires_at'] and now-value['started_at'] <= 600, 'stale_commissioning')
    need(value['observations_sha256'] == digest(canonical(value['observations'])), 'observation_bytes')
    keys(value['observations'], ('pid','uid','data','independent','participants'))
    observed=value['observations']; integer(observed['pid'],1); integer(observed['uid'])
    need(observed['uid'] == 0, 'collector_role')
    need(type(observed['participants']) is list and 1<=len(observed['participants'])<=16,'probe_participants')
    for child in observed['participants']:
        keys(child,('role','uid','pid','exit'))
        integer(child['uid']);integer(child['exit'])
        need(child['role'] in ROLES and child['uid']==ROLES[child['role']] and child['exit']==0,'probe_child_role')
        integer(child['pid'],1)
    measurements(gate,observed['data'])
    keys(observed['independent'], ('pid','state_sha256','roles_sha256'))
    integer(observed['independent']['pid'],1); hash_(observed['independent']['state_sha256'])
    need(observed['independent']['roles_sha256'] == expected['roles_sha256'], 'independent_roles')
    need(observed['independent']['state_sha256']==expected['roots_sha256'],'independent_roots')
    keys(value['capture'], ('file','bytes','sha256')); integer(value['capture']['bytes'],1,65536); hash_(value['capture']['sha256'])
    need(value['capture']['file'] == 'commission-'+gate+'-before-restore.json', 'capture_name')
    keys(value['cleanup'], ('remaining','state_sha256')); integer(value['cleanup']['remaining']); hash_(value['cleanup']['state_sha256'])
    need(value['cleanup']['remaining'] == 0, 'commission_cleanup')
    need(value['cleanup']['state_sha256']==expected['roots_sha256'],'cleanup_roots')
    need(value['missing'] == list(MISSING[gate]) and value['result'] == ('INCOMPLETE' if MISSING[gate] else 'MEASURED'), 'producer_completeness')
    if complete: need(not MISSING[gate], 'native_producer_incomplete_'+gate)
    return value

def published_record(read,expected,gate,receipt,now,native=True,complete=True):
    keys(receipt,('file','bytes','sha256'))
    need(receipt['file'] == 'commission-'+gate+'.json', 'gate_publication_name')
    raw=read(receipt['file'])
    need(receipt['bytes'] == len(raw) and receipt['sha256'] == digest(raw), 'gate_publication_bytes')
    value=validate_record(raw,expected,gate,now,native=native,complete=False)
    capture=value['capture']; original=read(capture['file'])
    need(len(original)==capture['bytes'] and digest(original)==capture['sha256'], 'pre_restore_publication')
    before=decode(original)
    keys(before,('profile','binding','gate','execution','observations','first_failure'))
    need(before['profile']==PROFILE+'-before-restore' and before['binding']==expected
         and before['gate']==gate and before['execution']==value['execution']
         and before['observations']==value['observations'] and before['first_failure'] is None, 'pre_restore_measurements')
    if gate=='apache':
        from WebsitePlatformM6ECommissionApache import validate_final
        data=value['observations']['data']
        validate_final(read,expected,data['apache_sha256'],data['source_sha256'],value['execution'])
    if complete:need(not MISSING[gate],'native_producer_incomplete_'+gate)
    return raw

def finalized_evidence(read, expected, receipts, now, native=True):
    keys(receipts,GATES); gates={}
    for gate in GATES:
        raw=published_record(read,expected,gate,receipts[gate],now,native=native)
        gates[gate]=dict(result='pass',evidence_sha256=digest(raw))
    return gates

def format_receipt(worker_raw, installed, gates, now, expires_at):
    """Pure format construction only. Never evidence authorization or an admitted runtime."""
    keys(gates,GATES); integer(now,1); integer(expires_at,now+1,now+86400)
    need(type(installed) is dict and 1 <= len(installed) <= 256, 'installed_bound')
    for name, value in installed.items():
        need(type(name) is str and name.startswith('/') and '..' not in name and '\\' not in name, 'installed_path'); hash_(value)
    for value in gates.values():
        keys(value,('result','evidence_sha256')); need(value['result']=='pass','gate_result'); hash_(value['evidence_sha256'])
    return dict(version=2,profile='m6e-host-v2',worker_config_sha256=digest(worker_raw),verified_at=now,
                expires_at=expires_at,gates=copy.deepcopy(gates),installed_files=copy.deepcopy(installed))
