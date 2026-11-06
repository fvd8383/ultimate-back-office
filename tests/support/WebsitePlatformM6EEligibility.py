"""One owned, non-authorizing source-eligibility preflight. Native admission stays blocked."""
import re
import secrets
import time

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys, integer, safe_error
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECheckpoint as c
import WebsitePlatformM6ESnapshot as s
import WebsitePlatformM6ERestoration as r

PROFILE='m6e-eligibility-preflight-v1'
SECONDS=120
ACCOUNT='m6e_lifecycle_eligibility@127.0.0.1'
SOURCE_FILES=('tests/support/WebsitePlatformM6EEligibility.php','tests/support/WebsitePlatformM6EEligibility.py',
    *('private/classes/'+name+'.php' for name in ('SiteRevisionManager','SiteApprovalManager','SiteManager',
      'SiteServiceSupport','SiteCompositionValidator','SiteRevisionSnapshotHasher','ComponentRegistry','ThemeRegistry',
      'SiteDeploymentService','SiteDeploymentContract','SiteDeploymentRuntime')))

def request(config,source,cp_raw,snapshot_raw,schema_raw,restoration_raw,owner,invocation,now):
    restore=r.request(config,cp_raw,snapshot_raw,schema_raw);proof=decode(restoration_raw)
    need(proof['binding']==p.base_identity(config) and proof['result']=='RESTORED AND INDEPENDENTLY VERIFIED'
         and proof['snapshot_sha256']==restore['snapshot_sha256'] and proof['checkpoint_sha256']==restore['checkpoint_sha256']
         and proof['schema_sha256']==restore['schema_sha256'] and proof['admission']=='UNIMPLEMENTED'
         and proof['deployment_authority']=='NONE','eligibility_restoration')
    keys(owner,('pid','cgroup','invocation'));integer(owner['pid'],1)
    need(owner['cgroup']==['0::/system.slice/ubo-m6e-bootstrap.service'] and re.fullmatch('[a-f0-9]{32}',owner['invocation'])
         and re.fullmatch('[a-f0-9]{32}',invocation) and now<config['expires_at'],'eligibility_owner_or_expiry')
    return dict(profile=PROFILE,binding=p.base_identity(config),schema=restore['destination']['schema'],server_uuid=restore['server_uuid'],
        **{k:restore[k] for k in ('checkpoint_sha256','snapshot_sha256','schema_sha256')},restoration_sha256=digest(restoration_raw),
        restoration_observation_sha256=proof['observation_sha256'],source_files={n:source[n]['sha256'] for n in SOURCE_FILES},
        invocation_id=invocation,owner=owner,started_at=now,expires_at=config['expires_at'],
        admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')

def verify_gate(raw,req,cp_raw,pid,seal,now):
    need(len(raw)<=65536 and req['started_at']<=now<req['expires_at'],'eligibility_stale');v=decode(raw)
    need(req['checkpoint_sha256']==digest(cp_raw),'eligibility_checkpoint_changed')
    keys(v,('result','phase','first_failure','secondary','transaction','closed','gate_returned','identity','observed_sha256',
        'absence','request_sha256','invocation_id','process','admission','native_admission','deployment_authority'))
    need(v['request_sha256']==digest(canonical(req)) and v['invocation_id']==req['invocation_id'],'eligibility_invocation')
    need(v['result']=='AWAITING_INDEPENDENT_OBSERVER' and v['phase']=='complete' and v['first_failure'] is None
         and v['secondary']==[] and v['transaction']=='ROLLED_BACK' and v['closed'] is True and v['gate_returned'] is True,'eligibility_outcome')
    need(v['admission']=='UNIMPLEMENTED' and v['native_admission']=='BLOCKED' and v['deployment_authority']=='NONE','eligibility_authority')
    identity=v['identity'];keys(identity,('schema','server_uuid','account','connection_id','grants_sha256'))
    need(identity['schema']==req['schema'] and identity['server_uuid']==req['server_uuid'] and identity['account']==ACCOUNT,'eligibility_database')
    integer(identity['connection_id'],1);integer(pid,1)
    need(type(identity['grants_sha256']) is str and re.fullmatch('[a-f0-9]{64}',identity['grants_sha256']),'eligibility_grants_digest')
    need(v['process']==dict(pid=pid,uid=r.RESTORER,gid=r.RESTORER,owner_pid=req['owner']['pid']),'eligibility_process')
    keys(seal,('account','locked','grants_sha256','sessions','restorer_processes','restoration_accounts'))
    integer(seal['sessions'],0);integer(seal['restorer_processes'],0)
    need(seal['account']==ACCOUNT and seal['locked'] is True and seal['sessions']==seal['restorer_processes']==0
         and seal['grants_sha256']==identity['grants_sha256'],'eligibility_cleanup')
    original=decode(cp_raw)['observed'];original['database']={k:identity[k] for k in ('schema','server_uuid','account')}
    need(v['observed_sha256']==digest(canonical(original)) and v['absence']==dict.fromkeys(c.TABLES,0),'eligibility_original')
    return dict(identity=identity,observed_sha256=v['observed_sha256'],outcome_sha256=digest(raw),process=v['process'],
        transaction=dict(access='READ WRITE',isolation='REPEATABLE READ',outcome='ROLLED_BACK',closed=True),
        gate=dict(method='SiteRevisionManager::lockDeploymentEligibility',restore=False,returned=True),absence=v['absence'])

def observer_diagnostic(v,phase,req,cp_raw,snapshot_raw,schema_raw):
    """Recompute measurements from owned bytes; never trust the producer's digest assertions."""
    need(len(canonical(v))<=4194304,'eligibility_observation_bound')
    keys(v,('request_sha256','phase','process','progress','observation','diagnostic'))
    need(v['request_sha256']==digest(canonical(req)) and v['phase']==phase,'eligibility_observer_binding')
    process=v['process'];keys(process,('pid','uid','gid','owner_pid'));integer(process['pid'],1)
    need(process['uid']==process['gid']==0 and type(process['uid']) is int and type(process['gid']) is int
         and process['owner_pid']==req['owner']['pid'],'eligibility_observer_process')
    progress=v['progress'];keys(progress,('identity','read','artifact'));blocked=False
    for kind in ('identity','read','artifact'):
        status=progress[kind];need(status in ('COMPLETED','FAILED','NOT_REACHED'),'eligibility_observer_progress')
        need(not blocked or status=='NOT_REACHED','eligibility_observer_order');blocked=blocked or status!='COMPLETED'
    o=v['observation'];keys(o,('profile','binding','request_sha256','database','read','artifact','deployment_authority','admission'))
    need(o['binding']==req['binding'] and o['deployment_authority']=='NONE' and o['admission']=='UNIMPLEMENTED','eligibility_observer_binding')
    for kind,key in (('identity','database'),('read','read'),('artifact','artifact')):
        need((o[key] is not None)==(progress[kind]=='COMPLETED'),'eligibility_observer_measurement_missing')
    db=o['database']
    if db is not None:
        keys(db,('schema','server_uuid','account','connection_id','grants_sha256'));integer(db['connection_id'],1)
        need(db['schema']==req['schema'] and db['server_uuid']==req['server_uuid'] and db['account']=='m6e_lifecycle_observer@127.0.0.1'
             and type(db['grants_sha256']) is str and re.fullmatch('[a-f0-9]{64}',db['grants_sha256']),'eligibility_observer_identity')
    expected_schema=decode(schema_raw)['tables'];expected_data=decode(snapshot_raw)['tables'];expected_artifact=decode(cp_raw)['summary']
    names=s.PROFILE['tables'];need(sorted(expected_schema)==sorted(expected_data)==names,'eligibility_diagnostic_inventory')
    read=o['read']
    if read is not None:
        keys(read,('definitions','tables','collation'))
        need(type(read['definitions']) is dict and type(read['tables']) is dict
             and sorted(read['definitions'])==sorted(read['tables'])==names,'eligibility_diagnostic_inventory')
        need(all(type(read['tables'][n]) is dict and type(read['tables'][n].get('rows')) is list
             and len(read['tables'][n]['rows'])<=2048 for n in names)
             and sum(len(read['tables'][n]['rows']) for n in names)<=8192,'eligibility_diagnostic_rows')
    pairs={k:dict(expected=digest(canonical(x)),observed=None if y is None else digest(canonical(y))) for k,x,y in (
        ('schema',expected_schema,None if read is None else read['definitions']),
        ('data',expected_data,None if read is None else read['tables']),('artifact',expected_artifact,o['artifact']))}
    tables={}
    for n in names:
        tables[n]=dict(schema_expected=digest(canonical(expected_schema[n])),schema_observed=None if read is None else digest(canonical(read['definitions'][n])),
            data_expected=digest(canonical(expected_data[n])),data_observed=None if read is None else digest(canonical(read['tables'][n])),
            rows_expected=len(expected_data[n]['rows']),rows_observed=None if read is None else len(read['tables'][n]['rows']))
    affected=[n for n,t in tables.items() if read is not None and (t['schema_expected']!=t['schema_observed'] or t['data_expected']!=t['data_observed'])]
    categories=[k for k,pair in pairs.items() if pair['observed'] is not None and pair['observed']!=pair['expected']]
    classification=next((k+'_failed' for k in ('identity','read','artifact') if progress[k]=='FAILED'),'drift' if categories else 'unchanged')
    value=dict(profile='m6e-eligibility-observer-diagnostic-v1',phase=phase,binding=req['binding'],request_sha256=digest(canonical(req)),
        invocation_id=req['invocation_id'],process=process,database=db,progress=progress,measurements=pairs,tables=tables,
        affected_tables=affected,categories=categories,classification=classification,result='DIAGNOSTIC_ONLY',
        admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')
    need(len(canonical(value))<=65536,'eligibility_diagnostic_bound');return value

def verify_observer(raw,phase,req,cp_raw,snapshot_raw,schema_raw,config,source,execution,accounts,now):
    need(len(raw)<=4194304,'eligibility_observation_bound');v=decode(raw)
    observer_diagnostic(v,phase,req,cp_raw,snapshot_raw,schema_raw)
    return r.verify_observation(canonical(v['observation']),r.request(config,cp_raw,snapshot_raw,schema_raw),
        snapshot_raw,schema_raw,cp_raw,config,source,execution,now,accounts)

def command(host,mode):
    need(mode in ('prepare','run','seal','before','after'),'eligibility_command')
    result=host.run('eligibility-'+mode,['/usr/bin/php','-d','display_errors=0','-d','log_errors=0',
        p.CHECKOUT+'/tests/support/WebsitePlatformM6EEligibility.php','--'+mode],
        65 if mode in ('run','before','after') else 20,role='restorer' if mode=='run' else 'operator',allowed=(0,1),return_result=True)
    need(not result['stderr'] and len(result['stdout'])<=16384,'eligibility_protocol');value=decode(result['stdout'])
    if result['exit']!=0:
        keys(value,('result','diagnostic'));d=value['diagnostic'];keys(d,('phase','first_failure','secondary'))
        need(value['result']=='INCOMPLETE' and re.fullmatch('[a-z-]{1,40}',d['phase'])
             and re.fullmatch('eligibility_[a-z_-]{1,64}',d['first_failure']) and type(d['secondary']) is list
             and len(d['secondary'])<=8 and all(re.fullmatch('eligibility_[a-z_]{1,64}',x) for x in d['secondary']),'eligibility_diagnostic')
        host.ledger.state['observations']['eligibility'].setdefault('native_failures',[]).append(dict(mode=mode,**d));host.ledger.save()
        raise ValueError('eligibility_native_incomplete')
    return value,result['pid']

def preflight(host,ledger,plan,fixture):
    config=plan['config'];host.namespace(config)
    need(ledger.state['phase']=='artifacts' and ledger.state['first_failure'] is None and ledger.state['watchdog']=='ARMED'
         and 'eligibility' not in ledger.state['observations'],'eligibility_already_started_or_failed')
    need(time.time()+SECONDS+p.CLEANUP_SECONDS<config['expires_at'] and time.monotonic()+SECONDS<host.owner.deadline,'eligibility_budget')
    need(host.source(p.CHECKOUT,config['source_sha'])==plan['source'],'eligibility_source_changed')
    s.checked(host,ledger,plan,fixture);restoration_digest=r.checked(host,ledger,plan)
    cp_raw=host.fs.read(c.PATH);snapshot_raw=host.fs.read(s.PATH);schema_raw=host.fs.read(s.SCHEMA)
    restoration_raw=host.fs.read(ledger.root+'/restoration-verification.json');need(digest(restoration_raw)==restoration_digest,'eligibility_restoration_publication')
    req=request(config,plan['source'],cp_raw,snapshot_raw,schema_raw,restoration_raw,host.eligibility_context(),secrets.token_hex(16),int(time.time()))
    d=c.lifecycle(config['run_id']);control=d['control_root']+'/eligibility';storage=d['storage_root']+'/eligibility'
    state=dict(profile=PROFILE,binding=req['binding'],invocation_id=req['invocation_id'],request_sha256=digest(canonical(req)),
        result='INCOMPLETE',stage='preparation',first_failure=None,secondary=[],cleanup='UNREACHED',fixture_cleanup='PENDING_OWNED_TEARDOWN',
        admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')
    ledger.state['observations']['eligibility']=state;ledger.save();state['preparation']=ledger.publish('eligibility-preparation',req);ledger.save()
    old_deadline=host.owner.deadline;host.owner.deadline=min(old_deadline,time.monotonic()+SECONDS);prepared=False;sealed=False
    try:
        ledger.directory(control,gid=r.RESTORER,mode=0o750);ledger.directory(control+'/worker',r.RESTORER,r.RESTORER,0o700)
        ledger.directory(storage,mode=0o700)
        ledger.write(control+'/request.json',canonical(req),gid=r.RESTORER,mode=0o640)
        ledger.write(control+'/restoration-verification.json',restoration_raw,gid=r.RESTORER,mode=0o640)
        original=r.command(host,'source');need(original==ledger.state['observations']['restoration']['source_after'],'eligibility_original_drift')
        state['stage']='prepare-account';ledger.save();prepared=True
        (created,_)=r.generated(host,[(control+'/credentials.json',0,r.RESTORER,0o640)],lambda:command(host,'prepare'))
        need(created==dict(restoration_accounts=ledger.state['observations']['restoration']['accounts'],eligibility_account=ACCOUNT,created='EXCLUSIVE'),'eligibility_creation')
        accounts=created['restoration_accounts'];execution='native-pre-b' if getattr(host,'lane','native-bootstrap')=='native-bootstrap' else 'controlled-local'
        def observe(phase):
            state['stage']=phase;ledger.save()
            path=storage+'/'+phase+'.json';primary=None;capture_error=None;diagnostic=None;raw=None;verified=None;pid=None
            try:(_,pid)=r.generated(host,[(path,0,0,0o600)],lambda:command(host,phase))
            except BaseException as error:primary=error
            try:
                row=next(x for x in ledger.state['entries'] if x['path']==path)
                ledger.verify(row);raw=host.fs.read(path,4194304);ledger.verify(row);v=decode(raw)
                child=next(x for x in reversed(ledger.state['commands']) if x['id']=='eligibility-'+phase)
                integer(child['pid'],1);need(v['process']['pid']==child['pid'],'eligibility_observer_pid')
                diagnostic=observer_diagnostic(v,phase,req,cp_raw,snapshot_raw,schema_raw)
                try:verified=verify_observer(raw,phase,req,cp_raw,snapshot_raw,schema_raw,config,plan['source'],execution,accounts,int(time.time()))
                except BaseException as error:primary=primary or error
                need(type(v['diagnostic']) is dict and len(canonical(v['diagnostic']))<=65536
                     and v['diagnostic']==diagnostic,'eligibility_diagnostic_protocol')
                ledger.verify(row)  # Never re-identify a replaced producer output.
            except BaseException as error:
                capture_error=error;primary=primary or error
            if primary is not None:ledger.failure(primary,'eligibility')
            if capture_error is not None:
                state['secondary'].append(dict(kind='observer-capture',**safe_error(capture_error)))
                ledger.failure(capture_error,'eligibility-observer-capture')
            try:
                # Only sanitized measurements or a fixed unavailable marker reach retained evidence.
                value=dict(profile='m6e-eligibility-observer-evidence-v1',binding=req['binding'],request_sha256=digest(canonical(req)),
                    invocation_id=req['invocation_id'],phase=phase,diagnostic=diagnostic,
                    capture='COMPLETE' if capture_error is None else 'INCOMPLETE',
                    observation_sha256=None if raw is None else digest(raw),result='DIAGNOSTIC_ONLY',
                    admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')
                receipt=ledger.publish('eligibility-observer-'+phase,value)
                state.setdefault('observer_evidence',{})[phase]=receipt;ledger.save()
            except BaseException as error:
                if primary is None:primary=error;ledger.failure(primary,'eligibility')
                state['secondary'].append(dict(kind='observer-publication',**safe_error(error)))
                ledger.failure(error,'eligibility-observer-publication')
            if primary is not None:raise primary
            return verified
        before=observe('before');state['stage']='transaction';ledger.save()
        (_,pid)=r.generated(host,[(control+'/worker/outcome.json',r.RESTORER,r.RESTORER,0o600)],lambda:command(host,'run'))
        state['stage']='seal';ledger.save();seal,_=command(host,'seal');sealed=True
        need(seal['restoration_accounts']==accounts,'eligibility_restoration_seal')
        gate=verify_gate(host.fs.read(control+'/worker/outcome.json'),req,cp_raw,pid,seal,int(time.time()))
        state['cleanup']='ROLLED_BACK_CHILD_REAPED_ACCOUNT_LOCKED';state['gate']=gate;state['seal']=seal;ledger.save()
        after=observe('after')
        need(len({before['destination']['connection_id'],gate['identity']['connection_id'],after['destination']['connection_id']})==3,'eligibility_independent_connections')
        need(before['tables']==after['tables'] and before['original']==after['original'] and before['artifact']==after['artifact'],'eligibility_observer_changed')
        state['stage']='final-integrity';ledger.save();need(r.command(host,'source')==original,'eligibility_original_mutated')
        s.checked(host,ledger,plan,fixture);need(r.checked(host,ledger,plan)==restoration_digest,'eligibility_restoration_changed')
        need(host.source(p.CHECKOUT,config['source_sha'])==plan['source'],'eligibility_source_changed')
        proof=dict(profile=PROFILE+'-verification',execution=execution,binding=req['binding'],request_sha256=state['request_sha256'],
            invocation_id=req['invocation_id'],owner=req['owner'],started_at=req['started_at'],completed_at=int(time.time()),expires_at=req['expires_at'],
            inputs={k:req[k] for k in ('checkpoint_sha256','snapshot_sha256','schema_sha256','restoration_sha256','restoration_observation_sha256')},
            source_files=req['source_files'],gate=gate,seal=seal,original=after['original'],artifact=after['artifact'],
            observer=dict(before=before['observation_sha256'],after=after['observation_sha256'],database=after['destination'],tables=after['tables']),
            cleanup=state['cleanup'],single_target_per_site_environment='SATISFIED_ZERO_TARGETS',result='ELIGIBILITY PREFLIGHT VERIFIED',
            admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')
        need(proof['completed_at']<proof['expires_at'],'eligibility_expired');state['verification']=ledger.publish('eligibility-verification',proof)
        state['result']='ELIGIBILITY PREFLIGHT VERIFIED';state['stage']='complete';ledger.save();ledger.publish('eligibility-result',state)
        return state
    except BaseException as error:
        state['result']='INCOMPLETE';state['first_failure']=dict(stage=state['stage'],**safe_error(error));ledger.failure(error,'eligibility')
        if prepared and not sealed:
            try:state['seal'],_=command(host,'seal');state['cleanup']='ACCOUNT_LOCKED_OUTCOME_UNCONFIRMED'
            except BaseException as secondary:
                state['cleanup']='INCOMPLETE';state['secondary'].append(dict(kind='account-seal',**safe_error(secondary)));ledger.failure(secondary,'eligibility-cleanup')
        try:
            rows=[x for x in ledger.state['entries'] if x['path']==control+'/worker/outcome.json' and x['state']=='CREATED']
            if rows:
                ledger.verify(rows[0]);raw=host.fs.read(rows[0]['path'],65536);v=decode(raw)
                state['failed_worker']={k:v[k] for k in ('result','phase','first_failure','secondary','transaction','closed','gate_returned')}
                state['failed_worker']['sha256']=digest(raw)
            ledger.save();ledger.publish('eligibility-result',state)
        except BaseException as secondary:
            state['secondary'].append(safe_error(secondary));ledger.failure(secondary,'eligibility-capture')
        raise
    finally:host.owner.deadline=old_deadline

def checked(host,ledger,plan):
    r.checked(host,ledger,plan)
    state=ledger.state['observations'].get('eligibility')
    need(type(state) is dict and state['result']=='ELIGIBILITY PREFLIGHT VERIFIED' and state['first_failure'] is None
         and state['cleanup']=='ROLLED_BACK_CHILD_REAPED_ACCOUNT_LOCKED' and state['fixture_cleanup']=='PENDING_OWNED_TEARDOWN'
         and state['binding']==p.base_identity(plan['config']) and state['admission']=='UNIMPLEMENTED'
         and state['native_admission']=='BLOCKED' and state['deployment_authority']=='NONE','eligibility_incomplete')
    raw=host.fs.read(ledger.root+'/eligibility-verification.json');v=decode(raw)
    prepared=host.fs.read(ledger.root+'/eligibility-preparation.json');req=decode(prepared)
    need(state['preparation']==dict(file='eligibility-preparation.json',bytes=len(prepared),sha256=digest(prepared))
         and digest(prepared)==state['request_sha256'] and req['binding']==state['binding']
         and req['source_files']=={n:plan['source'][n]['sha256'] for n in SOURCE_FILES},'eligibility_preparation_changed')
    need(state['verification']==dict(file='eligibility-verification.json',bytes=len(raw),sha256=digest(raw))
         and v['request_sha256']==state['request_sha256'] and v['invocation_id']==state['invocation_id']
         and time.time()<v['expires_at']==plan['config']['expires_at'],'eligibility_publication')
    need(set(state.get('observer_evidence',{}))=={'before','after'},'eligibility_observer_publications')
    for phase,receipt in state['observer_evidence'].items():
        evidence=host.fs.read(ledger.root+'/eligibility-observer-'+phase+'.json',70000)
        need(receipt==dict(file='eligibility-observer-'+phase+'.json',bytes=len(evidence),sha256=digest(evidence)),
             'eligibility_observer_publication_changed')
    need(decode(host.fs.read(ledger.root+'/eligibility-result.json'))==state,'eligibility_result_changed');return digest(raw)

def before_cleanup(host,ledger):
    state=ledger.state['observations'].get('eligibility')
    if state is not None:ledger.publish('eligibility-before-cleanup-'+str(ledger.state['sequence']),state)

def cleanup_result(host,ledger):
    state=ledger.state['observations'].get('eligibility')
    if state is None:return
    removed=ledger.state['observations']['restoration']['cleanup']=='OWNED_INSTANCE_AND_NAMESPACE_REMOVED'
    removed=removed and all(x['state']=='RELEASED' for x in ledger.state['accounts'])
    state['fixture_cleanup']='OWNED_FIXTURE_REMOVED' if removed else 'INCOMPLETE'
    ledger.save();ledger.publish('eligibility-cleanup-'+str(ledger.state['sequence']),dict(binding=state['binding'],
        invocation_id=state['invocation_id'],result=state['result'],cleanup=state['cleanup'],fixture_cleanup=state['fixture_cleanup'],
        admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE'))
