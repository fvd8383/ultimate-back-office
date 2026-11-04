"""Owned pre-B restoration, inside the existing bootstrap supervisor. Never lifecycle admission."""
import stat
import re
import time

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys, safe_error
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECheckpoint as checkpoint
import WebsitePlatformM6ESnapshot as snapshot

PROFILE='m6e-pre-b-restoration-v1'
RESTORER=2107
SECONDS=240
SOURCE_FILES=('tests/support/WebsitePlatformM6ERestoration.php','tests/support/WebsitePlatformM6ERestoration.py')

def request(config,cp_raw,snapshot_raw,schema_raw):
    cp=decode(cp_raw);v=decode(snapshot_raw)
    need(cp['binding']==v['binding']==p.base_identity(config),'restore_binding')
    d=checkpoint.lifecycle(config['run_id'])
    need(cp['lifecycle']==d and v['database']['schema']=='ubo_m6e_'+config['run_id']
         and v['database']['server_uuid']==cp['observed']['database']['server_uuid'],'restore_destination')
    return dict(profile=PROFILE,binding=p.base_identity(config),destination=d,server_uuid=v['database']['server_uuid'],
        checkpoint_sha256=digest(cp_raw),snapshot_sha256=digest(snapshot_raw),schema_sha256=digest(schema_raw),expires_at=config['expires_at'])

def verify_observation(raw,req,snapshot_raw,schema_raw,cp_raw,config,source,execution,now,accounts):
    """Independent comparison of observer-produced values, not the writer's completion flag."""
    original=snapshot.validate(snapshot_raw,schema_raw,cp_raw,config,source,execution,now)
    need(req==request(config,cp_raw,snapshot_raw,schema_raw),'restore_request_changed')
    need(len(raw)<=4194304,'restore_observation_bound');v=decode(raw)
    keys(v,('profile','binding','request_sha256','database','read','artifact','deployment_authority','admission'))
    need(v['profile']==PROFILE.replace('-v1','-observation-v1') and v['binding']==req['binding']
         and v['request_sha256']==digest(canonical(req)) and v['deployment_authority']=='NONE'
         and v['admission']=='UNIMPLEMENTED','restore_observation_binding')
    db=v['database'];keys(db,('schema','server_uuid','account','connection_id','grants_sha256'))
    need(db['schema']==req['destination']['schema'] and db['server_uuid']==req['server_uuid']
         and db['account']=='m6e_lifecycle_observer@127.0.0.1' and type(db['connection_id']) is int
         and db['connection_id']>0,'restore_observer_identity')
    keys(accounts,('worker','observer'))
    for role in accounts:
        row=accounts[role];keys(row,('account','grants_sha256','locked'))
        need(row['account']=='m6e_lifecycle_'+role+'@127.0.0.1' and row['locked'] is (role=='worker'),'restore_account_binding')
    need(db['grants_sha256']==accounts['observer']['grants_sha256'],'restore_observer_grants')
    read=v['read'];keys(read,('definitions','tables','collation'))
    need(read['definitions']==decode(schema_raw)['tables'] and read['tables']==decode(snapshot_raw)['tables']
         and read['collation']=='utf8mb4_unicode_ci','restore_exact_database')
    # Equality covers complete rows/PKs, generated values, counters, FK tuples, no B and zero targets/operations.
    need(v['artifact']==decode(cp_raw)['summary'],'restore_artifact')
    return dict(profile=PROFILE+'-verification',binding=req['binding'],destination=db,accounts=accounts,
        snapshot_sha256=digest(snapshot_raw),checkpoint_sha256=digest(cp_raw),schema_sha256=digest(schema_raw),
        observation_sha256=digest(raw),tables=original['tables'],original=original['original'],artifact=v['artifact'],
        source_files={name:source[name]['sha256'] for name in SOURCE_FILES},
        result='RESTORED AND INDEPENDENTLY VERIFIED',deployment_authority='NONE',admission='UNIMPLEMENTED')

def command(host,mode):
    need(mode in ('prepare','restore','seal','source','verify'),'restore_command')
    result=host.run('restoration-'+mode,['/usr/bin/php','-d','display_errors=0','-d','log_errors=0',
        p.CHECKOUT+'/tests/support/WebsitePlatformM6ERestoration.php','--'+mode],
        125 if mode in ('restore','source','verify') else 20,role='restorer' if mode=='restore' else 'operator',allowed=(0,1),return_result=True)
    need(not result['stderr'] and len(result['stdout'])<=16384,'restore_command_protocol');value=decode(result['stdout'])
    if result['exit']!=0:
        keys(value,('result','diagnostic'));d=value['diagnostic'];keys(d,('phase','first_failure','secondary'))
        need(value['result']=='INCOMPLETE' and re.fullmatch('[a-z-]{1,40}',d['phase'])
             and (d['first_failure'] is None or re.fullmatch('restore_[a-z_-]{1,64}',d['first_failure']))
             and type(d['secondary']) is list and len(d['secondary'])<=8
             and all(re.fullmatch('restore_[a-z_]{1,64}',s) for s in d['secondary']),'restore_safe_diagnostic')
        host.ledger.state['observations']['restoration'].setdefault('native_failures',[]).append(dict(mode=mode,**d));host.ledger.save()
        raise ValueError('restore_native_incomplete')
    return value

def generated(host,outputs,action):
    """Journal leaves before starting their sole producer; capture partial/failure output too."""
    ledger=host.ledger;entries=[ledger.generated(path,'pre-b-restoration') for path,_,_,_ in outputs];primary=None
    try:return action()
    except BaseException as error:primary=error;raise
    finally:
        errors=[]
        for row,(_,uid,gid,mode) in zip(entries,outputs):
            try:
                if not host.fs.exists(row['path']):continue
                identity=host.fs.identity(row['path'])
                need(identity['kind']==stat.S_IFREG and (identity['uid'],identity['gid'],identity['mode'])==(uid,gid,mode),'restore_output_owner')
                ledger.identify(row)
                if uid==RESTORER:
                    host.fs.metadata(row['path'],0,0,0o600);ledger.identify(row)
                    parent=row['path'].rsplit('/',1)[0]
                    parent_row=next(x for x in ledger.state['entries'] if x['path']==parent)
                    ledger.verify(parent_row);host.fs.metadata(parent,0,0,0o700);ledger.identify(parent_row)
                row['sha256']=digest(host.fs.read(row['path']));ledger.save()
                host.fs.sync_dir(row['path'].rsplit('/',1)[0])
            except BaseException as error:errors.append(error)
        if errors:
            ledger.failure(primary or errors[0],'restoration')
            for error in errors if primary else errors[1:]:ledger.failure(error,'restoration-capture')
            if primary is None:raise errors[0]

def restore(host,ledger,plan,fixture):
    """Called only after protected original checkpoint/snapshot publication, before bootstrap READY."""
    config=plan['config'];host.namespace(config)
    need(ledger.state['phase']=='artifacts' and ledger.state['first_failure'] is None
         and ledger.state['watchdog']=='ARMED' and 'restoration' not in ledger.state['observations'],'restore_preparation_state')
    need(time.time()+SECONDS+p.CLEANUP_SECONDS<config['expires_at'] and time.monotonic()+SECONDS<host.owner.deadline,'restore_budget')
    snapshot.checked(host,ledger,plan,fixture)
    cp_raw=host.fs.read(checkpoint.PATH);snapshot_raw=host.fs.read(snapshot.PATH);schema_raw=host.fs.read(snapshot.SCHEMA)
    req=request(config,cp_raw,snapshot_raw,schema_raw);d=req['destination'];control=d['control_root'];storage=d['storage_root']
    state=dict(profile=PROFILE,binding=req['binding'],destination=d,result='INCOMPLETE',stage='preparation',
        cleanup='PENDING_OWNED_INSTANCE_TEARDOWN',first_failure=None,secondary=[],deployment_authority='NONE',admission='UNIMPLEMENTED')
    ledger.state['observations']['restoration']=state;ledger.save();ledger.publish('restoration-preparation',req)
    primary=None;original_deadline=host.owner.deadline
    host.owner.deadline=min(original_deadline,time.monotonic()+SECONDS)
    try:
        for name in SOURCE_FILES:need(name in plan['source'],'restore_source_complete')
        ledger.directory(control,gid=RESTORER,mode=0o750);ledger.directory(storage,mode=0o700)
        ledger.directory(control+'/worker',RESTORER,RESTORER,0o700)
        for name,raw in (('request',canonical(req)),('checkpoint',cp_raw),('snapshot',snapshot_raw),('schema',schema_raw)):
            ledger.write(control+'/'+name+'.json',raw,gid=RESTORER,mode=0o640)
        state['stage']='source-before';ledger.save();before=command(host,'source');state['source_before']=before;ledger.save()
        state['stage']='destination-ddl';ledger.save()
        created=generated(host,[(control+'/worker-credentials.json',0,RESTORER,0o640),(control+'/observer-credentials.json',0,0,0o600)],lambda:command(host,'prepare'))
        need(created==dict(schema=d['schema'],server_uuid=req['server_uuid'],created='EXCLUSIVE'),'restore_creation')
        state['created']=created;state['stage']='worker';ledger.save()
        generated(host,[(control+'/worker/outcome.json',RESTORER,RESTORER,0o600)],lambda:command(host,'restore'))
        worker=decode(host.fs.read(control+'/worker/outcome.json'))
        need(worker['result']=='AWAITING_INDEPENDENT_VERIFICATION' and worker['first_failure'] is None
             and worker['secondary']==[] and worker['transaction']=='COMMITTED','restore_worker_incomplete')
        state['worker']=worker;state['stage']='seal-worker';ledger.save();accounts=command(host,'seal')
        need(worker['identity']['account']==accounts['worker']['account']
             and worker['identity']['grants_sha256']==accounts['worker']['grants_sha256'],'restore_worker_grants')
        state['accounts']=accounts;state['stage']='independent-observation';ledger.save()
        generated(host,[(storage+'/observation.json',0,0,0o600)],lambda:command(host,'verify'))
        lane='native-pre-b' if getattr(host,'lane','native-bootstrap')=='native-bootstrap' else 'controlled-local'
        observed=verify_observation(host.fs.read(storage+'/observation.json'),req,snapshot_raw,schema_raw,cp_raw,
            config,plan['source'],lane,int(time.time()),accounts)
        need(worker['identity']['connection_id']!=observed['destination']['connection_id'],'restore_independent_connection')
        state['stage']='source-after';ledger.save();after=command(host,'source')
        need(before==after and before['schema']=='ubo_m6e_'+config['run_id'] and before['server_uuid']==req['server_uuid'],'restore_source_mutated')
        snapshot.checked(host,ledger,plan,fixture)
        state['verification']=ledger.publish('restoration-verification',observed);state['source_after']=after
        state['result']='RESTORED AND INDEPENDENTLY VERIFIED';state['stage']='complete';ledger.save()
        ledger.publish('restoration-result',state);return state
    except BaseException as error:
        primary=error;state['result']='INCOMPLETE';state['first_failure']=dict(stage=state['stage'],**safe_error(error))
        ledger.failure(error,'restoration')
        # Preserve the writer's safe transaction/cleanup outcomes even after a failed child exit.
        try:
            path=control+'/worker/outcome.json'
            rows=[row for row in ledger.state['entries'] if row['path']==path and row['state']=='CREATED']
            if rows:
                ledger.verify(rows[0]);state['worker']=decode(host.fs.read(path,16384))
            ledger.save();ledger.publish('restoration-result',state)
        except BaseException as secondary:
            state['secondary'].append(safe_error(secondary));ledger.failure(secondary,'restoration-capture')
        if 'source_before' in state:
            try:
                state['source_after']=command(host,'source')
                need(state['source_before']==state['source_after'],'restore_source_mutated')
                ledger.save()
            except BaseException as secondary:
                state['secondary'].append(safe_error(secondary));ledger.failure(secondary,'restoration-source-after')
        raise primary
    finally:host.owner.deadline=original_deadline

def checked(host,ledger,plan):
    need(ledger.state['phase'] in ('artifacts','inspection','ready') and ledger.state['watchdog']=='ARMED'
         and ledger.state['first_failure'] is None and time.time()<plan['config']['expires_at'],'restore_not_live')
    state=ledger.state['observations'].get('restoration')
    need(type(state) is dict and state['result']=='RESTORED AND INDEPENDENTLY VERIFIED'
         and state['first_failure'] is None and state['admission']=='UNIMPLEMENTED'
         and state['deployment_authority']=='NONE','restore_incomplete')
    d=checkpoint.lifecycle(plan['config']['run_id'])
    need(state['destination']==d and state['binding']==p.base_identity(plan['config'])
         and state['cleanup']=='PENDING_OWNED_INSTANCE_TEARDOWN','restore_destination_binding')
    for row in ledger.state['entries']:
        if row['path'] in (d['control_root'],d['storage_root']) or row['path'].startswith((d['control_root']+'/',d['storage_root']+'/')):ledger.verify(row)
    raw=host.fs.read(ledger.root+'/restoration-verification.json');receipt=state['verification']
    need(receipt==dict(file='restoration-verification.json',bytes=len(raw),sha256=digest(raw)),'restore_publication')
    need(decode(host.fs.read(ledger.root+'/restoration-result.json'))==state,'restore_result_changed')
    return digest(raw)

def before_cleanup(host,ledger):
    state=ledger.state['observations'].get('restoration')
    if state is not None:
        # DDL/COMMIT uncertainty is retained; no DROP/retry guesses. Remove only the whole owned instance,
        # after its recorded processes stop, through the existing inode-bound datadir teardown.
        ledger.publish('restoration-before-cleanup-'+str(ledger.state['sequence']),state)

def cleanup_result(host,ledger):
    state=ledger.state['observations'].get('restoration')
    if state is None:return
    d=checkpoint.lifecycle(ledger.config['run_id'])
    roots=[p.STORAGE+'/mysql',d['control_root'],d['storage_root']]
    rows=[x for x in ledger.state['entries'] if x['path'] in roots]
    mysql=[x for x in ledger.state['services'] if x['unit']=='ubo-m6e-mysql.service']
    removed=all(not host.fs.exists(path) for path in roots) and all(x['state']=='RELEASED' for x in rows+mysql)
    state['cleanup']='OWNED_INSTANCE_AND_NAMESPACE_REMOVED' if removed else 'INCOMPLETE'
    ledger.save();ledger.publish('restoration-cleanup-'+str(ledger.state['sequence']),dict(binding=state['binding'],
        result=state['result'],cleanup=state['cleanup'],admission='UNIMPLEMENTED',deployment_authority='NONE'))
