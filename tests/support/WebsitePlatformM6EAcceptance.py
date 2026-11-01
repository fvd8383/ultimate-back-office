"""Definitions-only M6E acceptance policy. Import performs no file, service or network I/O."""
import hashlib
import json
import re

PROFILE = 'm6e-isolated-v2'
PARENTS = ('stage_replay_retention', 'link_hardlink_special_path_rejection', 'fence_and_owned_lock',
           'activation_restore_compensation', 'first_deployment_absence', 'authority_change_before_rename',
           'kill_after_intent', 'kill_after_rename', 'stage_storage_fault', 'journal_fsync_fault',
           'apache_private_health_headers', 'config_failure_preservation', 'worker_helper_authority_integration')
GATES = ('apache', 'filesystem', 'ownership', 'assets', 'runtime', 'health', 'recovery', 'provisioning')
ROLES = {'operator': 0, 'coordinator': 2100, 'worker': 2000, 'helper': 2102, 'authority': 2103, 'apache': 2104, 'mysql': 2105, 'writer': 2106}
ENDPOINTS = {'active': ['active.m6e.invalid', '127.0.0.2', 443],
             'candidate': ['candidate.m6e.invalid', '127.0.0.3', 443], 'mysql': ['127.0.0.1', 3306]}
TOOLS = ('php', 'python', 'apache', 'mysql', 'openssl', 'systemd', 'git')
LANES = ('controlled-local', 'linux-component', 'apache-tls', 'identity-supervisor', 'mysql-authority', 'injected-api', 'process-syscall')

# Subobservations of the existing 13 cases; not a new milestone matrix.
OBSERVATIONS = {
 'stage_replay_retention': ('exact-stage', 'exact-replay', 'retained-assets', 'ownership-device-modes'),
 'link_hardlink_special_path_rejection': ('symlink', 'hardlink', 'fifo', 'directory-replacement', 'file-replacement', 'named-inode-replacement'),
 'fence_and_owned_lock': ('contender', 'stale-fence', 'equal-fence', 'foreign-target', 'corrupt-journal', 'missing-journal'),
 'activation_restore_compensation': ('activate', 'restore', 'compensate', 'original-producer', 'interrupted-recovery'),
 'first_deployment_absence': ('exact-absence',),
 'authority_change_before_rename': ('revoked-before-switch',),
 'kill_after_intent': ('killed', 'journal-link', 'lock-released'),
 'kill_after_rename': ('killed', 'journal-link', 'lock-released'),
 'stage_storage_fault': ('storage-api', 'filesystem-exhaustion', 'syscall-fsync') + tuple(
     kind + '-' + name for kind in ('asset', 'manifest') for name in
     ('complete-replay', 'partial-write', 'file-sync', 'chmod', 'publish', 'matching-winner', 'conflicting-winner',
      'post-rename-directory-sync', 'kill-private-write')) + ('asset-chown',),
 'journal_fsync_fault': ('journal-api', 'shutdown-cleanup', 'timeout-cleanup'),
 'apache_private_health_headers': ('candidate', 'active-pair', 'all-public-files', 'approved-pdf', 'restore-retained', 'absence',
     'wrong-site', 'wrong-release', 'wrong-marker', 'login-body', 'generic-200', 'css-bytes', 'media-bytes', 'mime', 'size', 'digest',
     'redirect', 'bad-trust', 'bad-host', 'bad-sni', 'bad-client', 'stale-cache', 'timeout', 'absence-not-active',
     'unauthorized-html', 'unauthorized-marker', 'unauthorized-assets', 'get-head', 'methods', 'static-handlers',
     'no-fallback-indexes-multiviews', 'private-dot-executable', 'security-cache-headers', 'binding-separation', 'site-scoped-assets'),
 'config_failure_preservation': ('valid-initial', 'invalid-candidate', 'failed-reload', 'unknown-reload', 'reconcile-served',
     'verified-success', 'routine-zero-config-actions'),
 'worker_helper_authority_integration': ('protected-entrypoints', 'private-transfer', 'source-denied', 'worker-write-denied',
     'select-only', 'foreign-schema-denied', 'invalid-peer', 'malformed-wire', 'renewal', 'expiry', 'revocation', 'target-slot',
     'contending-operations', 'stale-resumed-worker', 'fresh-recovery-fence', 'equal-fence-conflict', 'helper-death', 'worker-death',
     'eof', 'session-timeout', 'lock-through-health', 'lock-through-settlement', 'commit-not-delivered', 'ack-withheld',
     'authority-unavailable', 'fresh-outcome-before-compensation', 'committed-success-later-winner', 'recovery-no-candidate',
     'first-compensation-absence', 'transactions-outside-external')}

def need(value, code):
    if not value:
        raise ValueError(code)

def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=False, allow_nan=False).encode()

def digest(value):
    return hashlib.sha256(value).hexdigest()

def keys(value, names):
    need(type(value) is dict and set(value) == set(names), 'keys')

def integer(value, low=0, high=2**63-1):
    need(type(value) is int and low <= value <= high, 'integer')

def hash_(value, length=64):
    need(type(value) is str and re.fullmatch('[a-f0-9]{'+str(length)+'}', value), 'hash')

def decode(raw):
    need(type(raw) is bytes and len(raw) <= 4*1024*1024, 'report_size')
    def pairs(items):
        result = {}
        for key, value in items:
            need(key not in result, 'duplicate_key'); result[key] = value
        return result
    return json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: need(False, 'nonfinite'))

def lane(case, name):
    if case == 'apache_private_health_headers': return 'apache-tls'
    if case == 'config_failure_preservation': return 'identity-supervisor'
    if case == 'worker_helper_authority_integration':
        return 'mysql-authority' if name in ('select-only', 'foreign-schema-denied', 'renewal', 'expiry', 'revocation', 'target-slot',
            'contending-operations', 'stale-resumed-worker', 'fresh-recovery-fence', 'commit-not-delivered', 'ack-withheld',
            'authority-unavailable', 'fresh-outcome-before-compensation', 'committed-success-later-winner', 'transactions-outside-external') else 'identity-supervisor'
    if name in ('storage-api', 'journal-api') or any(name.endswith('-'+s) for s in ('partial-write','file-sync','chmod','chown','publish','post-rename-directory-sync')):
        return 'injected-api'
    return 'process-syscall' if 'kill' in case or name in ('filesystem-exhaustion','syscall-fsync') else 'linux-component'

def binding(value):
    keys(value, ('source_sha','run_id','boot_id','machine_id','config_sha256','tools','roles','endpoints','database','namespace'))
    hash_(value['source_sha'],40); hash_(value['run_id'],32); hash_(value['machine_id'],32); hash_(value['config_sha256'])
    need(re.fullmatch('[a-f0-9-]{36}',value['boot_id']) is not None, 'boot')
    need(value['roles']==ROLES and value['endpoints']==ENDPOINTS, 'roles_endpoints')
    keys(value['tools'], TOOLS)
    for tool in value['tools'].values():
        keys(tool, ('sha256','version')); hash_(tool['sha256'])
        need(type(tool['version']) is str and 0<len(tool['version'])<=256, 'tool_version')
    keys(value['database'], ('schema','server_uuid','writer','authority'))
    need(value['database']['schema']=='ubo_m6e_'+value['run_id'] and value['database']['writer']=='m6e_writer'
         and value['database']['authority']=='m6e_observer', 'database_identity')
    need(re.fullmatch('[a-f0-9-]{36}',value['database']['server_uuid']) is not None, 'database_uuid')
    need(value['namespace']=='vm:'+value['machine_id']+':'+value['boot_id'], 'namespace')

def initial(identity, execution):
    binding(identity); need(execution in ('controlled-local','native'), 'execution')
    return dict(profile=PROFILE, version=2, binding=identity, execution=execution, result='INCOMPLETE',
        cases={case:dict(result='UNREACHED',observations={name:dict(result='UNREACHED') for name in OBSERVATIONS[case]}) for case in PARENTS},
        first_failure=None, last_step=None, children=[], resources=[], duration_ms=0,
        capture='UNREACHED', cleanup='UNREACHED', publication='UNREACHED', teardown='NOT_REQUESTED')

def event(identity, case, name, stage, role, pid, tick, data):
    need(case in PARENTS and name in OBSERVATIONS[case], 'observation')
    need(stage in ('baseline','fault','outcome','independent','cleanup') and role in ROLES, 'stage_role')
    integer(pid,1); integer(tick,1)
    # Safe scalar-only measurements; raw bodies/SQL/passwords/tokens are never report fields.
    need(type(data) is dict and 0<len(data)<=48, 'measurements')
    for k,v in data.items():
        need(re.fullmatch('[a-z][a-z0-9_]{0,47}',k) is not None and k not in ('body','password','token','sql','credentials','private_key'), 'measurement_key')
        need(type(v) in (str,int) or v is None, 'measurement_type')
        if type(v) is str: need(len(v)<=256 and '\n' not in v and '\r' not in v, 'measurement_string')
    return dict(run_id=identity['run_id'], source_sha=identity['source_sha'], config_sha256=identity['config_sha256'],
                case=case,name=name,stage=stage,role=role,uid=ROLES[role],pid=pid,tick=tick,data=data)

def observation(identity, case, name, events, assertions, execution):
    value=dict(result='PASS',lane='controlled-local' if execution=='controlled-local' else lane(case,name),
               events=events, assertions=assertions, evidence_sha256=digest(canonical(events)))
    validate_observation(identity,case,name,value,execution)
    return value

def validate_observation(identity,case,name,value,execution):
    fields=('result','lane','events','assertions','evidence_sha256')
    keys(value, fields+('lifecycle',) if 'lifecycle' in value else fields)
    need(value['result']=='PASS' and value['lane']==('controlled-local' if execution=='controlled-local' else lane(case,name)), 'observation_lane')
    stages=('baseline','fault','outcome','independent','cleanup'); ev=value['events']
    need(type(ev) is list and len(ev)==5, 'five_measured_stages')
    last=0
    for stage,e in zip(stages,ev):
        keys(e, ('run_id','source_sha','config_sha256','case','name','stage','role','uid','pid','tick','data'))
        need(e==event(identity,case,name,stage,e['role'],e['pid'],e['tick'],e['data']) and e['tick']>=last, 'event_binding_order')
        last=e['tick']
    need(ev[0]['data'].get('exit')==0 and ev[0]['data'].get('state_sha256') is not None, 'positive_baseline')
    hash_(ev[0]['data']['state_sha256'])
    need(ev[1]['data'].get('boundary')==name and type(ev[1]['data'].get('count')) is int and ev[1]['data']['count']>0, 'fault_reached')
    need(ev[4]['data'].get('remaining')==0 and ev[4]['data'].get('exit')==0, 'owned_cleanup')
    need(ev[3]['pid']!=ev[2]['pid'], 'independent_observer')
    need(value['evidence_sha256']==digest(canonical(ev)), 'evidence_hash')
    checks=value['assertions']; need(type(checks) is list and 2<=len(checks)<=64, 'assertions')
    used=set()
    for check in checks:
        evaluate_assertion(ev,check,used)
    need(any(s=='outcome' for s,k in used) and any(s=='independent' for s,k in used), 'outcome_and_effect')
    if case in PARENTS[-3:]:
        need('lifecycle' in value,'legacy_observation_lifecycle_missing')
        validate_lifecycle(identity,case,name,value,complete=True)
    if execution=='native': native_predicates(case,name,ev)

def evaluate_assertion(events,check,used):
    assertion_shape(check);stage=check['stage'];key=check['key'];op=check['op'];wanted=check['expected']
    need(stage in ('outcome','independent') and op in ('eq','ne','gte','lte','in') and (stage,key) not in used,'assertion_identity')
    used.add((stage,key));data=next((e['data'] for e in events if e['stage']==stage),{})
    need(key in data,'assertion_measurement');actual=data[key]
    if op=='eq':need(type(actual) is type(wanted) and actual==wanted,'assert_eq')
    elif op=='ne':need(actual!=wanted,'assert_ne')
    elif op=='in':need(type(wanted) is list and actual in wanted,'assert_in')
    else:
        integer(actual);integer(wanted);need(actual>=wanted if op=='gte' else actual<=wanted,'assert_order')

def assertion_shape(check):
    keys(check,('stage','key','op','expected'))
    need(check['stage'] in ('outcome','independent') and check['op'] in ('eq','ne','gte','lte','in'),'assertion_shape')
    need(type(check['key']) is str and re.fullmatch('[a-z][a-z0-9_]{0,47}',check['key']) is not None,'assertion_key')
    need(check['key'] not in ('body','password','token','sql','credentials','private_key'),'assertion_private_key')
    values=check['expected'] if type(check['expected']) is list else [check['expected']]
    need(len(values)<=64 and all(type(v) in (str,int) or v is None for v in values),'assertion_values')
    need(all(type(v) is not str or (len(v)<=256 and '\n' not in v and '\r' not in v) for v in values),'assertion_strings')

# Diagnostic lifecycle is an explicitly versioned extension. Historical v2 rows
# without it are never silently credited with pre-restoration capture.
STAGES=('admit','begin','baseline','fault','provision','outcome','independent','assertions','cleanup','complete')
BOUNDARIES=('provision-pending','observation')

def safe_error(error):
    # Exception messages, args, context and arbitrary user-defined code strings are
    # never diagnostic payloads. Preserve classification, not private text.
    kind=type(error).__name__
    if kind not in ('Failure','ValueError','OSError','PermissionError','FileExistsError','TimeoutError','AssertionError','RuntimeError','TypeError','KeyError'):
        kind='Exception'
    return dict(type=kind,code={'Failure':'control_failure','ValueError':'validation_failed','AssertionError':'assertion_failed',
                              'TimeoutError':'timeout'}.get(kind,'operation_failed'))

def checkpoint_stem(identity,case,name,boundary):
    need(case in PARENTS and name in OBSERVATIONS[case] and boundary in BOUNDARIES,'checkpoint_identity')
    tag=digest(canonical([identity['source_sha'],identity['config_sha256'],case,name]))[:20]
    return 'cp-'+identity['run_id']+'-'+tag+'-'+boundary

def validate_lifecycle(identity,case,name,row,complete=False):
    life=row['lifecycle']
    keys(life,('version','attempted','last_completed','first_failure','secondary','capture','cleanup','notes','evaluated'))
    need(life['version']==1 and life['attempted'] in STAGES and life['last_completed'] in (None,*STAGES),'lifecycle_stage')
    need(type(life['secondary']) is list and len(life['secondary'])<=16,'secondary_bound')
    for error in ([life['first_failure']] if life['first_failure'] is not None else [])+life['secondary']:
        keys(error,('kind','stage','type','code'))
        need(error['kind'] in ('case','capture','cleanup') and error['stage'] in STAGES,'failure_stage')
        need(error['type'] in ('Failure','ValueError','OSError','PermissionError','FileExistsError','TimeoutError','AssertionError','RuntimeError','TypeError','KeyError','Exception')
             and error['code'] in ('control_failure','validation_failed','assertion_failed','timeout','operation_failed'),'failure_classification')
    keys(life['capture'],BOUNDARIES)
    for boundary,capture in life['capture'].items():
        keys(capture,('result','receipt'))
        need(capture['result'] in ('UNREACHED','PENDING','PASS','FAILED'),'capture_state')
        if capture['result']=='PASS':
            receipt=capture['receipt'];keys(receipt,('file','bytes','sha256'));hash_(receipt['sha256']);integer(receipt['bytes'],1,65536)
            need(receipt['file']==checkpoint_stem(identity,case,name,boundary)+'.json','checkpoint_name')
        else:need(capture['receipt'] is None,'capture_receipt')
    need(type(life['cleanup']) is list and len(life['cleanup'])<=4,'cleanup_attempts')
    for cleanup in life['cleanup']:
        keys(cleanup,('boundary','result'));need(cleanup['boundary'] in BOUNDARIES and cleanup['result'] in ('PASS','FAILED'),'cleanup_state')
    need(type(life['notes']) is list and len(life['notes'])<=16,'note_bound')
    for note in life['notes']:
        keys(note,('label','event'));need(note['label'] in ('diagnostic','provision','http-response'),'note_label')
        e=note['event'];need(e==event(identity,case,name,e['stage'],e['role'],e['pid'],e['tick'],e['data']),'note_binding')
    need(type(life['evaluated']) is list and len(life['evaluated'])==len(row['assertions'])<=64,'evaluated_count')
    need(all(x in ('PASS','FAILED') for x in life['evaluated']),'evaluated_result')
    if complete:
        need(life['attempted']=='complete' and life['last_completed']=='cleanup' and life['first_failure'] is None and not life['secondary'],'lifecycle_complete')
        need(life['capture']['observation']['result']=='PASS' and life['cleanup'] and all(x['result']=='PASS' for x in life['cleanup']),'checkpoint_cleanup_required')
        if case==PARENTS[-2] and name not in ('reconcile-served','routine-zero-config-actions'):
            need(life['capture']['provision-pending']['result']=='PASS','pending_checkpoint_required')
        need(all(x=='PASS' for x in life['evaluated']),'assertions_complete')

def validate_partial(identity,case,name,row,execution):
    keys(row,('result','lane','events','assertions','evidence_sha256','lifecycle'))
    need(row['result'] in ('INCOMPLETE','FAILED'),'diagnostic_not_pass')
    need(row['lane']==('controlled-local' if execution=='controlled-local' else lane(case,name)),'diagnostic_lane')
    need(type(row['events']) is list and len(row['events'])<=5,'partial_events')
    seen=[];last=0
    for e in row['events']:
        stage=e['stage'];need(stage not in seen,'partial_duplicate');seen.append(stage)
        need(e==event(identity,case,name,stage,e['role'],e['pid'],e['tick'],e['data']) and e['tick']>=last,'partial_binding');last=e['tick']
    need(row['evidence_sha256']==digest(canonical(row['events'])),'partial_hash')
    validate_lifecycle(identity,case,name,row)
    need((row['result']=='FAILED')==(row['lifecycle']['first_failure'] is not None),'partial_failure')
    used=set()
    for check,result in zip(row['assertions'],row['lifecycle']['evaluated']):
        assertion_shape(check)
        try:evaluate_assertion(row['events'],check,used);actual='PASS'
        except ValueError:actual='FAILED'
        need(actual==result,'partial_assertion_tamper')

def validate_checkpoint(raw,identity,case,name,boundary,receipt):
    need(len(raw)<=65536 and receipt==dict(file=checkpoint_stem(identity,case,name,boundary)+'.json',bytes=len(raw),sha256=digest(raw)),'checkpoint_bytes')
    value=decode(raw);keys(value,('profile','binding','case','name','boundary','observation','context'))
    need(value['profile']=='m6e-observation-checkpoint-v1' and value['binding']=={k:identity[k] for k in ('source_sha','run_id','config_sha256')}
         and value['case']==case and value['name']==name and value['boundary']==boundary,'checkpoint_binding')
    validate_partial(identity,case,name,value['observation'],'controlled-local' if value['observation']['lane']=='controlled-local' else 'native')
    need(value['observation']['lifecycle']['capture'][boundary]['result']=='PENDING','checkpoint_pending')
    validate_context(value['context'])
    return value

def validate_context(context):
    keys(context,('children','resources'))
    need(all(type(context[k]) is list and len(context[k])<=256 for k in context),'context_bound')
    for child in context['children']:
        fields=('pid','exit','signal','last_barrier','reaped')
        keys(child,fields+('termination_requested','exit_before_cleanup') if 'termination_requested' in child else fields)
        integer(child['pid'],1);integer(child['signal'],0,64)
        need(child['exit'] is None or type(child['exit']) is int,'context_exit')
        need(type(child['reaped']) is bool and type(child['last_barrier']) is str and re.fullmatch('[A-Za-z0-9_-]{1,80}',child['last_barrier']) is not None,'context_barrier')
        if 'termination_requested' in child:
            need(type(child['termination_requested']) is bool and (child['exit_before_cleanup'] is None or type(child['exit_before_cleanup']) is int),'context_termination')
    for resource in context['resources']:
        keys(resource,('id','kind','identity_sha256','state'));hash_(resource['identity_sha256'])
        need(all(type(resource[k]) is str and re.fullmatch('[A-Za-z0-9_-]{1,160}',resource[k]) is not None for k in ('id','kind')),'context_resource')
        need(resource['state'] in ('INTENT','CREATED','RELEASED','UNRESOLVED'),'context_state')

def native_predicates(case,name,events):
    """Source-defined predicates cannot be replaced by self-serving report assertions.

    Missing native schemas fail closed. The explicit inventory still lists those
    obligations; controlled-local adapter evidence cannot fill the gap.
    """
    out,other=events[2]['data'],events[3]['data']
    if case=='apache_private_health_headers':
        negative=name in ('wrong-site','wrong-release','wrong-marker','login-body','generic-200','css-bytes','media-bytes','mime','size',
                         'digest','redirect','bad-trust','bad-host','bad-sni','bad-client','stale-cache','timeout','absence-not-active')
        need(out.get('health')==('fail' if negative else 'pass'),'native_checker_result')
        if name in ('wrong-site','wrong-release','wrong-marker','login-body','generic-200','css-bytes','media-bytes','size','digest'):
            hash_(events[1]['data'].get('body_sha256'))
            need(other.get('status')==200 and other.get('body_sha256')==events[1]['data']['body_sha256'],'native_fault_response')
        elif name in ('bad-trust','bad-sni','bad-client') or name.startswith('unauthorized-'):
            need(other.get('status') in (0,400,401,403),'native_tls_denied')
        elif name=='timeout': need(other.get('status')==0,'native_timeout')
        elif name=='bad-host': need(other.get('wrong_host_status') in (0,400,403,421),'native_host_denied')
        elif name in ('absence','absence-not-active'): need(other.get('status')==503,'native_absence')
        elif name=='redirect': need(other.get('status')==302,'native_redirect')
        elif name=='mime':need(other.get('status')==200 and other.get('content_type')=='text/plain','native_wrong_mime')
        elif name=='stale-cache':need(other.get('status')==200 and other.get('age')=='100' and other.get('cache')=='HIT','native_stale_cache')
        elif name=='active-pair':
            need(out.get('pair_count')==2,'native_active_pair');integer(other.get('separation_ms'),1000)
        elif name=='get-head': need(other.get('head_status')==200 and other.get('head_bytes')==0,'native_head')
        elif name=='methods': need(other.get('denied_methods')==5,'native_methods')
        elif name in ('static-handlers','private-dot-executable','no-fallback-indexes-multiviews'):
            need(other.get('denied_paths')==(3 if name=='no-fallback-indexes-multiviews' else 6),'native_static')
        elif name=='security-cache-headers':need(other.get('verified_responses')==3,'native_headers')
        elif name=='binding-separation':
            hash_(other.get('candidate_sha256'));hash_(other.get('active_sha256'))
            need(other['candidate_sha256']!=other['active_sha256'],'native_binding_separation')
        elif name=='site-scoped-assets':need(other.get('foreign_status') in (403,404),'native_site_scope')
        elif name in ('all-public-files','approved-pdf','restore-retained'):
            # Needs a source/config-bound complete manifest and retained-release proof.
            raise ValueError('native_inventory_policy_not_implemented')
        else: need(other.get('status')==200,'native_http_status')
    elif case=='config_failure_preservation':
        if name=='routine-zero-config-actions':
            raise ValueError('native_command_audit_policy_not_implemented')
        expected='REJECTED' if name=='invalid-candidate' else 'UNKNOWN' if name in ('failed-reload','unknown-reload') else 'VERIFIED'
        need(out.get('result')==expected,'native_provision_result');hash_(other.get('config_sha256'));hash_(other.get('current_sha256'))
        if name=='invalid-candidate':need(out.get('reload_count')==0,'native_invalid_reload')
        if expected=='VERIFIED' and name!='reconcile-served':need(out.get('configtest_count')==1 and out.get('reload_count')==1,'native_verified_actions')
    else:
        raise ValueError('native_observation_policy_not_implemented')

def validate(report, expected, native=True):
    keys(report, ('profile','version','binding','execution','result','cases','first_failure','last_step','children','resources',
                  'duration_ms','capture','cleanup','publication','teardown'))
    binding(expected); need(report['binding']==expected and report['profile']==PROFILE and report['version']==2,'report_binding')
    need(report['execution']==('native' if native else 'controlled-local'), 'evidence_lane')
    need(report['first_failure'] is None and report['capture']=='PASS' and report['cleanup']=='PASS','report_failure_cleanup')
    # Original bytes cannot truthfully claim their own later fsync/readback succeeded.
    need(report['publication']=='PENDING', 'publication')
    need(report['teardown'] in ('NOT_REQUESTED','PASS'), 'teardown_separate')
    integer(report['duration_ms'],1,600000)
    keys(report['cases'],PARENTS)
    for case in PARENTS:
        row=report['cases'][case]; keys(row, ('result','observations')); need(row['result']=='PASS','case')
        keys(row['observations'], OBSERVATIONS[case])
        for name in OBSERVATIONS[case]: validate_observation(expected,case,name,row['observations'][name],report['execution'])
    need(report['last_step']==[PARENTS[-1],OBSERVATIONS[PARENTS[-1]][-1]], 'last_reached')
    need(type(report['children']) is list and type(report['resources']) is list, 'ledgers')
    for child in report['children']:
        fields=('pid','exit','signal','last_barrier','reaped')
        keys(child,fields+('termination_requested','exit_before_cleanup') if 'termination_requested' in child else fields)
        if 'termination_requested' in child:
            need(type(child['termination_requested']) is bool and (child['exit_before_cleanup'] is None or type(child['exit_before_cleanup']) is int),'termination_context')
        integer(child['pid'],1); integer(child['signal'],0,64)
        need(child['reaped'] is True and type(child['exit']) is int and type(child['last_barrier']) is str, 'child_cleanup')
    for resource in report['resources']:
        keys(resource, ('id','kind','identity_sha256','state')); hash_(resource['identity_sha256'])
        need(resource['state']=='RELEASED', 'resource_cleanup')
    need(report['result']=='PASS', 'overall_result')
    return True

def publication_receipt(raw,expected):
    return dict(profile=PROFILE+'-publication',version=1,source_sha=expected['source_sha'],run_id=expected['run_id'],
                config_sha256=expected['config_sha256'],report_sha256=digest(raw),report_bytes=len(raw),result='FINALIZED')

def validate_published(raw,expected,receipt,native=True):
    need(receipt==publication_receipt(raw,expected),'publication_receipt')
    validate(decode(raw),expected,native=native)
    return True

def main(argv):
    # Offline command reads only three explicitly supplied files. Never imports runtime.
    from pathlib import Path
    need(len(argv)==4 and argv[0]=='--validate', 'usage')
    raw=Path(argv[1]).read_bytes(); expected=decode(Path(argv[2]).read_bytes())
    validate_published(raw,expected,decode(Path(argv[3]).read_bytes()))
    print(json.dumps(dict(result='ACCEPTED',sha256=digest(raw))))
    return 0

if __name__=='__main__':
    import sys
    try: raise SystemExit(main(sys.argv[1:]))
    except (ValueError,OSError,KeyError,TypeError):
        print('{"result":"REJECTED"}'); raise SystemExit(1)
