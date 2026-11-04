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

def verify_observer(raw,phase,req,cp_raw,snapshot_raw,schema_raw,config,source,execution,accounts,now):
    need(len(raw)<=4194304,'eligibility_observation_bound');v=decode(raw);keys(v,('request_sha256','phase','observation'))
    need(v['request_sha256']==digest(canonical(req)) and v['phase']==phase,'eligibility_observer_binding')
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
            r.generated(host,[(storage+'/'+phase+'.json',0,0,0o600)],lambda:command(host,phase))
            return verify_observer(host.fs.read(storage+'/'+phase+'.json'),phase,req,cp_raw,snapshot_raw,schema_raw,
                config,plan['source'],execution,accounts,int(time.time()))
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
                state['cleanup']='INCOMPLETE';state['secondary'].append(safe_error(secondary));ledger.failure(secondary,'eligibility-cleanup')
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
