"""Independent private snapshot verification/ownership. Never a restore or authority API."""
import os
from pathlib import Path
import re
import stat
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys, integer
from WebsitePlatformM6EBootstrapLedger import Files
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECheckpoint as checkpoint

PROFILE=decode(Path(__file__).with_name('WebsitePlatformM6ESnapshotProfile.json').read_bytes())
PATH=p.CONTROL+'/worker/pre-b-database.json'
RECORD=p.CONTROL+'/worker/pre-b-database-verification.json'
OUTCOME=p.CONTROL+'/worker/pre-b-database-outcome.json'
SCHEMA=p.CONTROL+'/snapshot-schema.json'
CREDENTIALS=p.CONTROL+'/snapshot-reader.json'
PATHS=(PATH,RECORD,OUTCOME)
SOURCE_FILES=tuple('tests/support/'+n for n in ('WebsitePlatformM6ESnapshot.php','WebsitePlatformM6ESnapshot.py','WebsitePlatformM6ESnapshotProfile.json'))

def outcome_value(raw):
    need(len(raw)<=4096,'snapshot_outcome_bound');outcome=decode(raw)
    keys(outcome,('capture','verification','transaction_cleanup','first_failure','secondary'))
    need(outcome['capture'] in ('FAILED','CAPTURED') and outcome['verification'] in ('UNREACHED','FAILED','VERIFIED')
        and outcome['transaction_cleanup'] in ('UNREACHED','UNRESOLVED','RELEASED')
        and (outcome['first_failure'] is None or re.fullmatch('snapshot_[a-z_]{1,64}',outcome['first_failure']))
        and type(outcome['secondary']) is list and len(outcome['secondary'])<=8
        and all(re.fullmatch('snapshot_[a-z_]{1,64}',x) for x in outcome['secondary']),'snapshot_outcome_fields')
    return outcome

def definition(ddl):
    return re.sub(r'\) ENGINE=InnoDB AUTO_INCREMENT=[0-9]+ ',') ENGINE=InnoDB ',ddl)

def validate(raw,baseline_raw,cp_raw,config,source,execution,now):
    """Independent decoding, whole inventory, row digests, PK/FK closure and original identity checks.

    This verifies a capture record; only separately authorized native tests can prove SQL isolation.
    """
    need(len(raw)<=PROFILE['max_artifact_bytes'] and len(baseline_raw)<=1048576,'snapshot_size')
    v=decode(raw);baseline=decode(baseline_raw);cp=decode(cp_raw)
    keys(v,('profile','execution','binding','database','checkpoint_sha256','schema_sha256','reader','transaction',
        'creation_point','started_at','captured_at','expires_at','tables','deployment_authority','restore','admission'))
    keys(baseline,('profile','binding','schema','server_uuid','migrations','tables'))
    need(v['profile']==PROFILE['profile'] and v['execution']==execution and v['binding']==baseline['binding']==p.base_identity(config),'snapshot_binding')
    need(v['checkpoint_sha256']==digest(cp_raw) and v['schema_sha256']==digest(baseline_raw),'snapshot_inputs')
    need(baseline['profile']=='m6e-pre-b-schema-v1' and baseline['schema']=='ubo_m6e_'+config['run_id'],'snapshot_schema')
    migration_names=sorted(k for k in source if re.fullmatch(r'database/migrations/[0-9]{3}_[a-z0-9_]+\.sql',k))
    need(len(migration_names)==25 and [int(Path(n).name[:3]) for n in migration_names]==list(range(1,26)),'snapshot_migrations')
    need(baseline['migrations']=={n:source[n]['sha256'] for n in migration_names},'snapshot_migration_identity')
    need(v['database']==dict(schema=baseline['schema'],server_uuid=baseline['server_uuid'],account='m6e_snapshot@127.0.0.1'),'snapshot_database')
    need(v['deployment_authority']=='NONE' and v['restore']==v['admission']=='UNIMPLEMENTED','snapshot_not_authority')
    need(v['transaction']==dict(isolation='REPEATABLE READ',read_only=True,consistent_snapshot=True,
        ddl_guard='GLOBAL READ LOCK',boundaries=1,closed=True),'snapshot_transaction')
    need(all(v['transaction'][k] is True for k in ('read_only','consistent_snapshot','closed'))
        and type(v['transaction']['boundaries']) is int,'snapshot_transaction_types')
    r=v['reader'];keys(r,('connection_id','builder_connection_id','account','version','tls','grants_sha256'))
    integer(r['connection_id'],1);integer(r['builder_connection_id'],1)
    need(r['connection_id']!=r['builder_connection_id'] and r['account']==v['database']['account']
        and r['version'].startswith('8.4.') and r['tls'] is True and re.fullmatch('[a-f0-9]{64}',r['grants_sha256']),'snapshot_reader')
    for k in ('started_at','captured_at','expires_at'):integer(v[k],1)
    need(cp['captured_at']<=v['started_at']<=v['captured_at']<=now<v['expires_at']==config['expires_at']
        and v['captured_at']-v['started_at']<=PROFILE['max_seconds'],'snapshot_stale')
    need(v['creation_point']==cp['creation_point']=='after-A-completeBuildSuccess-before-B-createRevision','snapshot_point')
    original=dict(run_id=config['run_id'],site_id=int(cp['observed']['source']['site']['id']),
        site_key=cp['observed']['source']['site']['site_key'],releases=[cp['summary']])
    checkpoint.validate(cp_raw,config,baseline,original,source,execution,now)
    need(sorted(v['tables'])==sorted(baseline['tables'])==PROFILE['tables'],'snapshot_table_inventory')
    decoded={};total_rows=0;total_bytes=0;inventory={}
    for name in PROFILE['tables']:
        table=v['tables'][name];schema=baseline['tables'][name]
        keys(table,('columns','rows','row_count','rows_sha256','create_table'))
        keys(schema,('columns','primary','foreign_keys','definition'))
        cols=table['columns'];need(cols==schema['columns'] and 0<len(cols)<=128 and len(set(cols))==len(cols),'snapshot_columns')
        need(all(re.fullmatch('[a-z0-9_]+',c) for c in cols),'snapshot_column_names')
        need(type(table['create_table']) is str and len(table['create_table'].encode())<=65536
            and definition(table['create_table'])==schema['definition'],'snapshot_definition')
        rows=table['rows'];integer(table['row_count'],0)
        need(type(rows) is list and len(rows)==table['row_count']<=PROFILE['max_rows_per_table']
            and digest(canonical(rows))==table['rows_sha256'],'snapshot_rows')
        primary=schema['primary'];need(primary and len(set(primary))==len(primary) and set(primary)<=set(cols),'snapshot_primary')
        seen=set();decoded[name]=[]
        for row in rows:
            need(type(row) is list and len(row)==len(cols),'snapshot_row_shape');values=[]
            for cell in row:
                need(cell is None or (type(cell) is str and len(cell)<=2*PROFILE['max_cell_bytes']
                    and re.fullmatch('(?:[0-9A-F]{2})*',cell) is not None),'snapshot_cell')
                value=None if cell is None else bytes.fromhex(cell);values.append(value);total_bytes+=0 if value is None else len(value)
            value=dict(zip(cols,values));pk=tuple(value[c] for c in primary)
            need(None not in pk and pk not in seen,'snapshot_duplicate_primary');seen.add(pk);decoded[name].append(value)
        total_rows+=len(rows);need(total_rows<=PROFILE['max_rows'] and total_bytes<=PROFILE['max_value_bytes'],'snapshot_total_bound')
        inventory[name]=dict(rows=len(rows),sha256=table['rows_sha256'],schema_sha256=digest(canonical(schema)))
    for name,schema in baseline['tables'].items():
        for fk in schema['foreign_keys']:
            keys(fk,('table','columns','references'));target=fk['table']
            need(target in decoded and 0<len(fk['columns'])==len(fk['references'])
                and set(fk['columns'])<=set(schema['columns']) and set(fk['references'])<=set(baseline['tables'][target]['columns']),'snapshot_foreign_scope')
            parents={tuple(row[c] for c in fk['references']) for row in decoded[target]}
            for row in decoded[name]:
                key=tuple(row[c] for c in fk['columns']);need(None in key or key in parents,'snapshot_foreign_closure')
    o=cp['observed'];s=o['source']
    def original_row(name,row):
        found=[r for r in decoded[name] if r['id']==str(row['id']).encode()]
        need(len(found)==1,'snapshot_original_row')
        for k,value in row.items():
            if k=='rights_current':continue  # Computed eligibility observation, not a stored column.
            need(k in found[0] and found[0][k]==(None if value is None else str(value).encode()),'snapshot_original_value')
        return found[0]
    for name,row in (('sites',s['site']),('site_revisions',s['revision']),('site_releases',o['release']),('site_build_jobs',o['job']),('site_build_attempts',o['producer'])):
        original_row(name,row)
    for row in o['approvals'].values():original_row('site_approvals',row)
    for row in s['asset_metadata']:original_row('site_assets',row)
    association=original_row('site_business_associations',dict(id=s['association_id'],site_id=s['site']['id'],business_id=s['business_id']))
    need(association['status']==b'active' and association['association_role']==b'customer','snapshot_association')
    original_row('businesses',dict(id=s['business_id']))
    need(all(not decoded[t] for t in checkpoint.TABLES),'snapshot_operations')
    need(all(r['site_id']!=str(s['site']['id']).encode() or int(r['revision_number'])<=int(s['revision']['revision_number'])
        for r in decoded['site_revisions']),'snapshot_B_present')
    return dict(profile=PROFILE['profile']+'-verification',binding=v['binding'],execution=execution,
        checkpoint_sha256=v['checkpoint_sha256'],schema_sha256=v['schema_sha256'],snapshot_sha256=digest(raw),snapshot_bytes=len(raw),
        database=v['database'],reader=v['reader'],transaction=v['transaction'],tables=inventory,
        original=dict(site_id=int(s['site']['id']),revision_id=int(s['revision']['id']),business_id=s['business_id'],association_id=s['association_id'],
            approvals=s['approvals'],release_id=int(o['release']['id']),build_job_id=int(o['job']['id']),producer_attempt_id=int(o['producer']['id'])),
        creation_point=v['creation_point'],started_at=v['started_at'],captured_at=v['captured_at'],expires_at=v['expires_at'],
        source_files={n:source[n]['sha256'] for n in SOURCE_FILES},capture='CAPTURED',verification='VERIFIED',
        cleanup='PENDING_OWNED_TEARDOWN',deployment_authority='NONE',restore='UNIMPLEMENTED',admission='UNIMPLEMENTED')

def worker_verify():
    need(os.name=='posix' and os.geteuid()==2000 and Path(__file__).resolve().parents[2]==Path(p.CHECKOUT),'snapshot_worker_runtime')
    fs=Files()
    for name in (SCHEMA,p.CONTROL+'/bootstrap.json'):
        identity=fs.identity(name);need(identity['uid']==0 and identity['kind']==stat.S_IFREG and identity['mode']&0o022==0,'snapshot_protected_input')
    for name in (PATH,checkpoint.PATH):
        identity=fs.identity(name);need(identity['uid']==identity['gid']==2000 and identity['mode']==0o600,'snapshot_worker_input')
    config=decode(fs.read(p.CONTROL+'/bootstrap.json'));source={}
    for path in [*Path(p.CHECKOUT+'/database/migrations').glob('*.sql'),*[Path(p.CHECKOUT+'/'+n) for n in (*SOURCE_FILES,*checkpoint.SOURCE_FILES)]]:
        source[path.relative_to(p.CHECKOUT).as_posix()]=dict(sha256=digest(fs.read(str(path))))
    identity=fs.identity(PATH);raw=fs.read(PATH)
    record=validate(raw,fs.read(SCHEMA),fs.read(checkpoint.PATH),config,source,'native-pre-b',int(time.time()))
    record['file_identity']={k:identity[k] for k in ('dev','ino')}
    need(fs.identity(PATH)==identity,'snapshot_replaced_during_verification')
    # Directory durability also covers the PHP-created snapshot before B is allowed.
    fs.sync_dir(str(Path(PATH).parent));created=[];encoded=canonical(record)
    fs.create(RECORD,encoded,lambda fd:created.append(fs.identity(RECORD,fd)))
    fs.sync_dir(str(Path(RECORD).parent))
    need(fs.identity(RECORD)==created[0] and fs.read(RECORD)==encoded,'snapshot_verification_readback')

def capture(host,action):
    ledger=host.ledger;rows=[ledger.generated(path,'pre-b-database') for path in PATHS];primary=None
    try:return action()
    except BaseException as error:primary=error;raise
    finally:
        errors=[]
        for row in rows:
            try:
                if host.fs.exists(row['path']):
                    identity=host.fs.identity(row['path'])
                    need(identity['kind']==stat.S_IFREG and identity['uid']==identity['gid']==2000 and identity['mode']==0o600,'snapshot_file_owner')
                    ledger.identify(row);host.fs.metadata(row['path'],0,0,0o600);ledger.identify(row)
                    row['sha256']=digest(host.fs.read(row['path']));ledger.save();host.fs.sync_dir(str(Path(row['path']).parent).replace('\\','/'))
            except BaseException as error:errors.append(error)
        if host.fs.exists(OUTCOME):
            try:
                outcome=outcome_value(host.fs.read(OUTCOME,4096))
                # Preserve SQL rollback/unlock failures even when the builder exits unsuccessfully.
                ledger.state['observations'].setdefault('pre_b_database_outcome',outcome);ledger.save()
            except BaseException as error:errors.append(error)
        if errors:
            ledger.failure(primary or errors[0],'prepare')
            for error in errors if primary else errors[1:]:ledger.failure(error,'snapshot-capture')
            if primary is None:raise errors[0]

def checked(host,ledger,plan,fixture):
    checkpoint.checked(host,ledger,plan,fixture)
    for path in (*PATHS,SCHEMA):
        rows=[r for r in ledger.state['entries'] if r['path']==path]
        need(len(rows)==1 and rows[0]['state']=='CREATED' and rows[0]['sha256'] is not None,'snapshot_missing')
        ledger.verify(rows[0])
    outcome=decode(host.fs.read(OUTCOME))
    need(outcome==dict(capture='CAPTURED',verification='VERIFIED',transaction_cleanup='RELEASED',first_failure=None,secondary=[]),'snapshot_incomplete')
    need(outcome==ledger.state['observations'].get('pre_b_database_outcome'),'snapshot_outcome_mismatch')
    lane='native-pre-b' if getattr(host,'lane','native-bootstrap')=='native-bootstrap' else 'controlled-local'
    result=validate(host.fs.read(PATH),host.fs.read(SCHEMA),host.fs.read(checkpoint.PATH),plan['config'],plan['source'],lane,int(time.time()))
    identity=host.fs.identity(PATH);result['file_identity']={k:identity[k] for k in ('dev','ino')}
    need(host.fs.read(RECORD)==canonical(result),'snapshot_verification_changed');return result

def archive(host,ledger,plan,fixture):
    result=checked(host,ledger,plan,fixture)
    receipt=ledger.publish('pre-b-database-verification',result)
    # Private row bytes stay only in the owned removable leaf. Reports contain digests/identities.
    ledger.state['observations']['pre_b_database']=receipt;ledger.save()

def verify(host,ledger,plan,fixture):
    result=checked(host,ledger,plan,fixture);raw=canonical(result)
    need(ledger.state['observations'].get('pre_b_database')==dict(file='pre-b-database-verification.json',bytes=len(raw),sha256=digest(raw))
        and host.fs.read(ledger.root+'/pre-b-database-verification.json')==raw,'snapshot_not_published')
    return digest(raw)

if __name__=='__main__':
    try:
        need(sys.argv[1:]==['--verify-worker'],'snapshot_usage');worker_verify();print('snapshot_verified')
    except BaseException:
        # Never print data, credentials, SQL, exception text or a traceback.
        print('snapshot_verification_failed',file=sys.stderr);sys.exit(1)
