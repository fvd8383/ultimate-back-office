"""Apache's own original-A observation, captured while its reader is still alive.

No operational authority or native PASS is supplied here. The fixed commissioning
collector alone owns cleanup; assets keeps its later, separate component attempt.
"""
import copy
import time

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, keys, hash_
import WebsitePlatformM6EComponent as component

PROFILE='m6e-commission-apache-serving-v1'
NAME='commission-apache-serving'


def facts(state):
    """Reject partial diagnostics; these are observations of the existing checks."""
    need(state['profile']==component.PROFILE and state['owner']=='commission-apache' and state['first_failure'] is None
         and state['cleanup']=='UNATTEMPTED','apache_component_incomplete')
    observed=state['observations'];source=observed['source'];independent=observed['observer']
    need(source['release_id']==independent['release_id'] and source['release_key']==independent['release_key']
         and source['manifest_sha256']==independent['manifest_sha256']
         and source['artifact_sha256']==independent['artifact_sha256']
         and source['files']==len(independent['files']),'apache_component_source')
    need(independent['fence']==independent['phase']==1 and independent['current_present']==0
         and independent['candidate_present']==1,'apache_component_stage_only')
    for name in ('serving_before','serving_after'):
        value=observed[name]
        need({k:v for k,v in value.items() if k!='serving_inventory'}==independent,'apache_component_observer')
    need(observed['serving_before']==observed['serving_after'],'apache_component_serving_identity')
    inventory=observed['serving_before']['serving_inventory'];need(type(inventory) is dict and inventory,'apache_component_resources')
    reader=observed['serving_reader_before'];need(reader==observed['serving_reader_after'],'apache_component_reader')
    need(reader['uid']==reader['gid']==[ROLES['apache']]*4 and reader['capabilities']==0,'apache_component_reader_role')
    readers=[r for r in state['children'] if r['role']=='reader']
    need(len(readers)==1 and readers[0]['pid']==reader['pid'] and readers[0]['observed_exit'] is None
         and not readers[0]['reaped'],'apache_component_reader_alive')
    checked=observed['configtest']
    need(checked['classification']=='VALIDATED' and len(checked['children'])==1
         and checked['children'][0]['exit']==0 and checked['children'][0]['reaped'],'apache_component_configtest')
    mode='native' if state['execution']=='native-component-authored' else 'modeled'
    need(state['execution'] in ('native-component-authored','controlled-local')
         and observed['serving_execution']==dict(http=mode,tls=mode,apache=mode)
         and checked['execution']==mode,'apache_component_execution')
    http=observed['serving']
    need(http['verified_files']==http['verified_heads']==source['files'] and http['denials']==40
         and http['requests']==2*source['files']+41,'apache_component_http_complete')
    need({'text/html','application/json','text/css','image/png','application/pdf'}<=
         {entry['mime_type'] for entry in independent['files']},'apache_component_artifact_kinds')
    need(observed['bootstrap_before']==observed['bootstrap_after_stage'],'apache_component_operational')
    return dict(source=copy.deepcopy(source),inventory_sha256=independent['inventory_sha256'],
        resources_sha256=digest(canonical(inventory)),http=copy.deepcopy(http),
        configtest_sha256=digest(canonical(checked)),reader_sha256=digest(canonical(reader)),
        operational_sha256=digest(canonical(observed['bootstrap_before'])))


class ApacheObservation:
    def __init__(self,backend,binding,source,source_bundle_sha256):
        need(backend.plan.get('owner')=='commission-apache' and backend.serving_enabled,'apache_owned_component')
        self.component=component.Observation(backend)
        self.binding=copy.deepcopy(binding);self.source=copy.deepcopy(source);self.bundle=source_bundle_sha256
        self.publication=None
        self.owner=backend.host.owner;self.owner_deadline=self.owner.deadline
        self.owner.deadline=min(self.owner.deadline,backend.deadline)

    def measure(self):
        observation=self.component;backend=observation.backend
        # Bind the worker's original bytes before the helper is started. No
        # component response can replace the earlier independent source bundle.
        original_source=backend.source
        def checked_source(worker):
            value=original_source(worker)
            need(value==self.source,'apache_original_source_binding');return value
        backend.source=checked_source
        try:
            observation.measure()
            observation.state['children']=backend.children()
            measured=facts(observation.state)
            need(measured['source']['source_sha256']==digest(canonical(self.source)),'apache_source_digest')
            capture=observation.checkpoint()
            value=dict(profile=PROFILE,binding=self.binding,execution=backend.execution,
                source_bundle_sha256=self.bundle,plan_sha256=digest(canonical(backend.plan)),
                measurements=measured,capture=capture)
            self.publication=observation.ledger.publish(NAME,value)
            return self.publication['sha256']
        except BaseException as error:
            if observation.primary is None:observation.fail(error,'apache-component')
            raise
        finally:backend.source=original_source

    def finish(self):
        observation=self.component
        try:need(time.monotonic()<observation.backend.deadline,'apache_component_deadline')
        except BaseException as error:observation.fail(error,'apache-deadline')
        # Restore only the original absolute deadline for the existing cleanup
        # reserve; the reader/measurement budget is never refreshed.
        self.owner.deadline=self.owner_deadline
        if self.publication is not None:
            try:
                read=lambda name:observation.backend.fs.read(observation.ledger.root+'/'+name)
                validate_capture(read,self.binding,self.publication['sha256'],self.bundle)
            except BaseException as error:observation.fail(error,'apache-evidence')
        return observation.finish()


def validate_capture(read,binding,sha256,source_bundle_sha256):
    """Re-open the immutable evidence, never a component path after its cleanup."""
    raw=read(NAME+'.json');need(len(raw)<=65536 and digest(raw)==sha256,'apache_evidence_bytes')
    value=decode(raw);keys(value,('profile','binding','execution','source_bundle_sha256','plan_sha256','measurements','capture'))
    need(value['profile']==PROFILE and value['binding']==binding
         and value['source_bundle_sha256']==source_bundle_sha256,'apache_evidence_binding');hash_(value['plan_sha256'])
    capture=value['capture'];keys(capture,('file','bytes','sha256'))
    need(capture['file']=='component-apache-a-before-cleanup.json','apache_capture_owner')
    original=read(capture['file'])
    need(len(original)==capture['bytes'] and digest(original)==capture['sha256'],'apache_capture_bytes')
    state=decode(original)
    need(state['binding']=={k:binding[k] for k in ('source_sha','run_id','machine_id','boot_id')}
         and state['plan_sha256']==value['plan_sha256'],'apache_capture_binding')
    need(state['execution']==value['execution'] and facts(state)==value['measurements'],'apache_capture_measurements')
    return value


def validate_final(read,binding,sha256,source_bundle_sha256,execution):
    value=validate_capture(read,binding,sha256,source_bundle_sha256)
    lane='native-component-authored' if execution=='native-components' else 'controlled-local'
    need(value['execution']==lane,'apache_evidence_execution')
    result=decode(read('component-apache-a-result.json'))
    keys(result,('profile','execution','result','cleanup','first_failure','children','before_cleanup'))
    need(result['profile']==component.PROFILE and result['execution']==lane and result['result']=='STAGED_AND_REMOVED'
         and result['cleanup']=='REMOVED' and result['first_failure'] is None
         and result['before_cleanup']==value['capture'],'apache_evidence_cleanup')
    need(type(result['children']) is list and result['children']
         and all(r['reaped'] and r['exit']==r['observed_exit']==0 for r in result['children']),
         'apache_evidence_quiescence')
    return value
