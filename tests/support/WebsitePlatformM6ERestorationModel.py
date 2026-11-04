"""Synthetic SQL/identity responses for Windows orchestration controls. Never a native oracle."""
import copy
from WebsitePlatformM6EAcceptance import canonical, decode, digest, need
import WebsitePlatformM6ERestoration as r
import WebsitePlatformM6ECheckpoint as cp

def accounts():
    return {role:dict(account='m6e_lifecycle_'+role+'@127.0.0.1',grants_sha256=digest(role.encode()),locked=role=='worker') for role in ('worker','observer')}

def execute(host,mode):
    d=cp.lifecycle(host.cfg['run_id']);root=d['control_root'];req=decode(host.fs.read(root+'/request.json'))
    snapshot=decode(host.fs.read(root+'/snapshot.json'));baseline=decode(host.fs.read(root+'/schema.json'))
    checkpoint=decode(host.fs.read(root+'/checkpoint.json'));a=accounts()
    def identity(role):
        return dict(schema=d['schema'],server_uuid=req['server_uuid'],account=a[role]['account'],
            connection_id=71 if role=='worker' else 72,grants_sha256=a[role]['grants_sha256'])
    if mode=='--prepare':
        need(not hasattr(host,'restored'),'restore_destination_exists');host.restored={}
        host.put(root+'/worker-credentials.json',b'SECRET_RESTORE_CANARY',0,r.RESTORER,0o640)
        host.put(root+'/observer-credentials.json',b'SECRET_RESTORE_OBSERVER_CANARY')
        return dict(schema=d['schema'],server_uuid=req['server_uuid'],created='EXCLUSIVE')
    if mode=='--source':return dict(schema=host.db['schema'],server_uuid=req['server_uuid'],sha256=digest(canonical(dict(database=host.db,fixture=host.built))))
    if mode=='--restore':
        host.restored=copy.deepcopy(dict(definitions=baseline['tables'],tables=snapshot['tables'],collation='utf8mb4_unicode_ci'))
        host.put(root+'/worker/outcome.json',canonical(dict(result='AWAITING_INDEPENDENT_VERIFICATION',phase='worker-finished',
            first_failure=None,secondary=[],transaction='COMMITTED',identity=identity('worker'))),r.RESTORER,r.RESTORER)
        return dict(result='AWAITING_INDEPENDENT_VERIFICATION')
    if mode=='--seal':return a
    need(mode=='--verify','restore_model_mode')
    host.put(d['storage_root']+'/observation.json',canonical(dict(profile='m6e-pre-b-restoration-observation-v1',binding=req['binding'],
        request_sha256=digest(canonical(req)),database=identity('observer'),read=host.restored,artifact=checkpoint['summary'],
        deployment_authority='NONE',admission='UNIMPLEMENTED')))
    return dict(result='OBSERVED')
