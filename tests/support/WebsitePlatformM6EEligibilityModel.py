"""Explicit synthetic process/SQL preflight responses, only for local orchestration controls."""
import copy
from WebsitePlatformM6EAcceptance import canonical, decode, digest, need
import WebsitePlatformM6ECheckpoint as c
import WebsitePlatformM6EEligibility as e
import WebsitePlatformM6ERestoration as r
import WebsitePlatformM6ERestorationModel as rm

def execute(host,mode):
    d=c.lifecycle(host.cfg['run_id']);control=d['control_root']+'/eligibility';storage=d['storage_root']+'/eligibility'
    req=decode(host.fs.read(control+'/request.json'));cp=decode(host.fs.read(d['control_root']+'/checkpoint.json'))
    accounts=rm.accounts();identity=dict(schema=req['schema'],server_uuid=req['server_uuid'],account=e.ACCOUNT,
        connection_id=81,grants_sha256=digest(b'MODELED_ELIGIBILITY_GRANTS'))
    if mode=='--prepare':
        need(not hasattr(host,'eligibility_created'),'eligibility_account_exists');host.eligibility_created=True
        host.put(control+'/credentials.json',b'SECRET_ELIGIBILITY_CANARY',0,r.RESTORER,0o640)
        return dict(restoration_accounts=accounts,eligibility_account=e.ACCOUNT,created='EXCLUSIVE')
    if mode=='--run':
        original=copy.deepcopy(cp['observed']);original['database']={k:identity[k] for k in ('schema','server_uuid','account')}
        host.put(control+'/worker/outcome.json',canonical(dict(result='AWAITING_INDEPENDENT_OBSERVER',phase='complete',first_failure=None,
            secondary=[],transaction='ROLLED_BACK',closed=True,gate_returned=True,identity=identity,observed_sha256=digest(canonical(original)),
            absence=dict.fromkeys(c.TABLES,0),request_sha256=digest(canonical(req)),invocation_id=req['invocation_id'],
            process=dict(pid=900,uid=r.RESTORER,gid=r.RESTORER,owner_pid=req['owner']['pid']),
            admission='UNIMPLEMENTED',native_admission='BLOCKED',deployment_authority='NONE')),r.RESTORER,r.RESTORER)
        return dict(result='AWAITING_INDEPENDENT_OBSERVER')
    if mode=='--seal':return dict(account=e.ACCOUNT,locked=True,grants_sha256=identity['grants_sha256'],sessions=0,
        restorer_processes=0,restoration_accounts=accounts)
    need(mode in ('--before','--after'),'eligibility_model_mode')
    restore_req=decode(host.fs.read(d['control_root']+'/request.json'))
    observation=dict(profile='m6e-pre-b-restoration-observation-v1',binding=req['binding'],request_sha256=digest(canonical(restore_req)),
        database=dict(schema=req['schema'],server_uuid=req['server_uuid'],account=accounts['observer']['account'],
            connection_id=80 if mode=='--before' else 82,grants_sha256=accounts['observer']['grants_sha256']),
        read=copy.deepcopy(host.restored),artifact=cp['summary'],deployment_authority='NONE',admission='UNIMPLEMENTED')
    v=dict(request_sha256=digest(canonical(req)),phase=mode[2:],observation=observation,
        process=dict(pid=900,uid=0,gid=0,owner_pid=req['owner']['pid']),
        progress=dict(identity='COMPLETED',read='COMPLETED',artifact='COMPLETED'),diagnostic=None)
    v['diagnostic']=e.observer_diagnostic(v,mode[2:],req,host.fs.read(d['control_root']+'/checkpoint.json'),
        host.fs.read(d['control_root']+'/snapshot.json'),host.fs.read(d['control_root']+'/schema.json'))
    host.put(storage+'/'+mode[2:]+'.json',canonical(v))
    return dict(result='OBSERVED')
