"""Release-A disposable component lifecycle. Never operational authority or a gate.

The runner owns orchestration; adapters expose native or explicitly modeled OS I/O.
Transfer, fence, journal and immutable publication remain the production components.
"""
import base64
import copy
import importlib.util
from pathlib import Path
import stat
import time

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, keys, safe_error
from WebsitePlatformM6EProcess import Failure
import WebsitePlatformM6EBootstrapPlan as b

PROFILE='m6e-release-a-component-v1'
SECONDS=90

def publisher_module(path=None):
    path=path or Path(__file__).resolve().parents[2]/'infrastructure/deployment/ubo_site_publish.py'
    spec=importlib.util.spec_from_file_location('component_publisher',path)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module

def paths(run):
    b.hash_(run,32)
    control=b.CONTROL+'/component-a-'+run;data=b.STORAGE+'/component-a-'+run
    return dict(control=control,data=data,plan=control+'/plan.json',ipc=control+'/ipc',socket=control+'/ipc/publish.sock',
                served=data+'/served',journal=data+'/journal')

def request(plan):
    """Synthetic component identifiers only. No deployment service/session/SQL call."""
    release=plan['release'];run=plan['binding']['run_id']
    token=digest(canonical([PROFILE,run,plan['paths']]))
    uuid=run[:8]+'-'+run[8:12]+'-4'+run[13:16]+'-8'+run[17:20]+'-'+run[20:]
    target=dict(site_id=release['site_id'],target_id=plan['target_id'],environment='staging',
                publisher_key='apache-digitalocean-v1',binding_key='m6e-component-a',binding_version=1)
    lease=dict(site_id=release['site_id'],target_id=plan['target_id'],deployment_id=900000001,attempt_id=900000002,
               attempt_kind='execution',worker_id='m6e-component-worker',environment='staging',token=token,fence_epoch=1)
    context={k:v for k,v in lease.items() if k!='token'}|dict(deployment_key=uuid,producer_attempt_id=900000002,
        lease_token=token,lease_expires_at=plan['expires_at'],deadline_at=plan['expires_at'],binding_version=1,
        release_key=release['release_key'],artifact_hash=release['artifact_hash'],operation='publish',phase='stage',
        phase_timeout_seconds=30,connect_timeout_seconds=2,request_timeout_seconds=5,correlation_id='component-release-a')
    return dict(version=1,sequence=1,operation='stage',target=target,lease=lease,payload=dict(release=release,context=context))

def plan(config,fixture,expires=None):
    a=copy.deepcopy(fixture['releases'][0])
    value=dict(profile=PROFILE,binding=b.base_identity(config),expires_at=expires or min(config['expires_at'],int(time.time())+SECONDS),
        authority='SYNTHETIC COMPONENT ONLY',roles=ROLES,paths=paths(config['run_id']),target_id=fixture['target_id'],summary=a,
        release=dict(site_id=fixture['site_id'],site_key=fixture['site_key'],release_id=a['release_id'],release_key=a['release_key'],
                     artifact_hash=a['artifact_sha256'],manifest_hash=a['manifest_sha256'],build_profile='static-review-v1'))
    value['request']=request(value)
    return value

def validate_plan(value,config,fixture):
    b.validate_fixture(fixture,config['run_id'])
    need(type(value) is dict and type(value.get('expires_at')) is int
         and time.time()<value['expires_at']<=config['expires_at'],'component_expiry')
    need(value==plan(config,fixture,value['expires_at']),'component_fixed_plan')
    need(value['paths']['served']!=b.template_values(fixture['site_key'])['EXACT_SERVED_SITE_ROOT'],'component_isolation')
    return value

def source(plan,value,m):
    keys(value,('release','summary','manifest_base64'))
    need(value['release']==plan['release'] and value['summary']==plan['summary'],'component_original_producer')
    text=value['manifest_base64'];need(type(text) is str and len(text)<=1398104,'component_manifest_bound')
    raw=base64.b64decode(text,validate=True)
    manifest=m.transfer_entries(raw,plan['release']);entries=manifest['files']
    need(len(entries)==plan['summary']['file_count'] and manifest['file_count']==len(entries),'component_source_count')
    assets=[dict(sha256=e['sha256'],mime_type=e['mime_type'],bytes=e['byte_size']) for e in entries if e['relative_path'].startswith('public/assets/')]
    need(assets==plan['summary']['assets'] and any(e['mime_type']=='application/pdf' for e in entries),'component_source_assets')
    return manifest,dict(release_id=plan['release']['release_id'],release_key=plan['release']['release_key'],
        build_job_id=plan['summary']['build_job_id'],producer_attempt_id=plan['summary']['producer_attempt_id'],
        manifest_sha256=digest(raw),artifact_sha256=plan['release']['artifact_hash'],files=len(entries),
        public_bytes=sum(e['byte_size'] for e in entries),source_sha256=digest(canonical(value)))

class Authority:
    """Exact one-request, one-release authority; inaccessible to production entry."""
    def __init__(self,plan):self.plan=plan;self.deadline=None
    def observe(self,r):
        need(r==self.plan['request'] and r['operation']=='stage','component_authority_request')
        left=int(self.plan['expires_at']-time.time());need(left>0,'component_authority_expired')
        t=r['target'];lease=r['lease'];c=r['payload']['context']
        return {k:t[k] for k in ('site_id','target_id','binding_key','binding_version')}|dict(
            site_key=self.plan['release']['site_key'],deployment_key=c['deployment_key'],
            **{k:lease[k] for k in ('deployment_id','attempt_id','attempt_kind','fence_epoch')},
            producer_attempt_id=c['producer_attempt_id'],operation='publish',status='running',outcome=None,
            live=True,permitted=['stage'],activation_fence=None,release=self.plan['release'],previous=None,expires_in=left)

def binding(plan):
    return {k:plan['request']['target'][k] for k in ('site_id','target_id','binding_key','binding_version')}|dict(
        site_key=plan['release']['site_key'],served_root=plan['paths']['served'],journal_root=plan['paths']['journal'])

def stage(m,plan,served,journal,channel,peer,note):
    """One actual dispatch. The only intended pointer is component candidate."""
    r=m.request(channel.line());need(r==plan['request'],'component_request')
    need(peer['uid']==ROLES['worker'] and peer['pid']>0,'component_worker_peer')
    pub=m.Publisher(served,journal,Authority(plan),binding(plan))
    original=m.Ingress.receive
    def received(ingress,*args):
        value=original(ingress,*args)
        manifest=m.verify_package(ingress.tree,'package',plan['release'])
        note('receiver',dict(manifest_sha256=digest(ingress.tree.read('package/metadata/manifest.json',m.MAX_MANIFEST)),
            artifact_sha256=manifest['artifact_hash'],files=manifest['file_count'],public_bytes=manifest['byte_size']))
        return value
    # This child owns the imported module; capture around the existing receiver,
    # without replacing framing, validators, writes or error/cleanup behavior.
    m.Ingress.receive=received;primary=None
    try:
        result=pub.dispatch(r,channel)
        need(pub.served.link() is None and pub.served.link('candidate')==plan['release']['release_key'],'component_stage_pointer')
        observed=dict(stage_sha256=digest(canonical(result)),journal_sha256=digest(journal.read('state.json',65536)),
                      fence=pub.state['fence'],phase=pub.state['phase'],peer=peer)
        note('helper',observed)
        channel.reply(dict(profile=PROFILE,namespace_sha256=digest(canonical(plan['paths'])),
            manifest_sha256=plan['release']['manifest_hash'],artifact_sha256=plan['release']['artifact_hash'],stage_sha256=observed['stage_sha256']))
        return observed
    except BaseException as error:primary=error;raise
    finally:
        m.Ingress.receive=original
        try:pub.close()
        except BaseException:
            if primary is None:raise

def observe(m,plan,served,journal,metadata):
    """New observer process, actual original-manifest/package and pool readback."""
    pub=m.Publisher(served,journal,Authority(plan),binding(plan));primary=None
    try:
        pub.acquire() # Independently prove the stage process released its lock.
        need(served.link() is None and served.link('candidate')==plan['release']['release_key'],'component_observer_pointer')
        state=pub.state
        need(state['fence']==1 and state['phase']==1 and state['current'] is None and state['intent'] is None
             and state['settled'] is False and state['owner']==[900000001,900000002,900000002],'component_observer_journal')
        manifest=pub.verify_served(plan['release']);files=[]
        for e in manifest['files']:
            name='releases/'+plan['release']['release_key']+'/'+e['relative_path']
            raw=served.read(name,e['byte_size']);item=metadata('served',name)
            need(item['uid']==ROLES['helper'] and item['gid']==ROLES['apache'] and item['mode']==0o440
                 and item['kind']==stat.S_IFREG and item['nlink']==1,'component_public_metadata')
            files.append(e|dict(observed_sha256=digest(raw),observed_bytes=len(raw),identity=item))
            if e['relative_path'].startswith('public/assets/'):
                asset=e['relative_path'][7:];data=served.read(asset,e['byte_size']);pool=metadata('served',asset)
                need(data==raw and pool['uid']==ROLES['helper'] and pool['gid']==ROLES['apache']
                     and pool['mode']==0o440 and pool['nlink']==1 and pool['kind']==stat.S_IFREG,'component_pool_identity')
        need(any(f['mime_type']=='application/pdf' for f in files),'component_pdf')
        return dict(release_id=plan['release']['release_id'],release_key=plan['release']['release_key'],
            manifest_sha256=digest(journal.read('manifests/'+plan['release']['release_key']+'.json',m.MAX_MANIFEST)),
            artifact_sha256=manifest['artifact_hash'],files=files,journal_sha256=digest(journal.read('state.json',65536)),
            fence=state['fence'],phase=state['phase'],current_present=0,candidate_present=1)
    except BaseException as error:primary=error;raise
    finally:
        try:pub.close()
        except BaseException:
            if primary is None:raise

def run(backend):
    """Same bounded lifecycle in native and controlled-local adapters. No retries."""
    ledger=backend.ledger
    need('component_a' not in ledger.state['observations'],'component_already_attempted')
    state=dict(profile=PROFILE,execution=backend.execution,resources=[],children=[],observations={},first_failure=None,cleanup='UNATTEMPTED')
    ledger.state['observations']['component_a']=state;ledger.save()
    primary=None;capture=None;owned=None
    def note(name,value):
        # Values have fixed producers below; native child parsing validates their
        # shape before admission. Never record raw stdout/stderr/SQL/tokens.
        state['observations'][name]=copy.deepcopy(value);ledger.save()
    try:
        note('bootstrap_before',backend.operational())
        backend.prepare(state)
        worker=backend.start('worker');original=backend.source(worker)
        manifest,safe=source(backend.plan,original,backend.module);note('source',safe)
        backend.manifest=manifest
        helper=backend.start('helper');ready=backend.ready(helper);note('helper_ready',ready)
        backend.identify_socket(ready)
        transferred=backend.transfer(worker,helper,note);note('transfer',transferred)
        independent=backend.observer();note('observer',independent)
        need(independent['manifest_sha256']==safe['manifest_sha256'] and independent['artifact_sha256']==safe['artifact_sha256']
             and len(independent['files'])==safe['files'],'component_independent_binding')
        note('bootstrap_after_stage',backend.operational())
        need(state['observations']['bootstrap_after_stage']==state['observations']['bootstrap_before'],'component_operational_changed')
    except BaseException as error:
        primary=error;state['first_failure']=safe_error(error);ledger.failure(error,'component')
    finally:
        # First immutable capture is BEFORE stopping any live child or removing
        # resources. A second capture records quiescent inode ownership below.
        try:
            state['children']=backend.children()
            state['held']=backend.capture()
            capture=ledger.publish('component-a-before-cleanup',copy.deepcopy(state))
        except BaseException as error:
            ledger.failure(error,'component-capture');primary=primary or error
        try:backend.stop()
        except BaseException as error:
            ledger.failure(error,'component-stop');primary=primary or error
        try:
            backend.quiescent()
            owned=backend.owned()
            ledger.publish('component-a-owned-before-remove',dict(profile=PROFILE,execution=backend.execution,
                objects=owned,children=backend.children(),first_failure=state['first_failure']))
            need(capture is not None,'component_capture_required')
            backend.cleanup(owned)
            after=backend.operational();note('bootstrap_after_cleanup',after)
            need(after==state['observations']['bootstrap_before'],'component_pristine_restoration')
            state['cleanup']='REMOVED'
        except BaseException as error:
            ledger.failure(error,'component-cleanup');primary=primary or error;state['cleanup']='LIMITED'
        state['children']=backend.children()
        state['result']='STAGED_AND_REMOVED' if primary is None else 'INCOMPLETE'
        if state['first_failure'] is None and primary is not None:state['first_failure']=safe_error(primary)
        try:
            ledger.save()
            ledger.publish('component-a-result',dict(profile=PROFILE,execution=backend.execution,result=state['result'],
                cleanup=state['cleanup'],first_failure=state['first_failure'],children=state['children'],before_cleanup=capture))
        except BaseException as error:
            ledger.failure(error,'component-result');primary=primary or error
    if primary is not None:raise primary
    return dict(profile=PROFILE,execution=backend.execution,result=state['result'],cleanup=state['cleanup'])
