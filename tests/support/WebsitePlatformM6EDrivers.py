"""Actual driver control logic and bounded pre-restoration observation capture."""
import copy
import time

from WebsitePlatformM6EAcceptance import (OBSERVATIONS, PARENTS, BOUNDARIES, STAGES, binding, event, observation,
    need, canonical, digest, lane, safe_error, assertion_shape, evaluate_assertion, validate_observation, validate_partial,
    checkpoint_stem, validate_checkpoint)
from WebsitePlatformM6EProcess import Failure, Owner, publish_bytes
from WebsitePlatformM6EProvision import Provisioner

APACHE='apache_private_health_headers'
CONFIG='config_failure_preservation'
INSTALLED='worker_helper_authority_integration'


class Recorder:
    def __init__(self,identity,execution,report_root=None,owner=None):
        binding(identity);need(execution in ('controlled-local','native'),'execution')
        self.identity=copy.deepcopy(identity);self.execution=execution;self.report_root=report_root
        self.owner=owner or Owner();self.rows={};self.last=None

    def start(self,case,name):
        need(name in OBSERVATIONS[case] and (case,name) not in self.rows,'duplicate_observation')
        self.last=[case,name]
        row=dict(result='INCOMPLETE',lane='controlled-local' if self.execution=='controlled-local' else lane(case,name),
                 events=[],assertions=[],evidence_sha256=digest(canonical([])),lifecycle=dict(version=1,attempted='admit',last_completed=None,
                 first_failure=None,secondary=[],capture={b:dict(result='UNREACHED',receipt=None) for b in BOUNDARIES},
                 cleanup=[],notes=[],evaluated=[]))
        self.rows[case,name]=row
        return Progress(self,case,name,row)

    def add(self,case,name,baseline,fault,outcome,independent,cleanup,assertions):
        # Retained component adapter API. The three integration drivers use start().
        need(case not in PARENTS[-3:] and (case,name) not in self.rows,'incremental_driver_required')
        self.last=[case,name]
        events=[self.measurement(case,name,stage,sample) for stage,sample in zip(
            ('baseline','fault','outcome','independent','cleanup'),(baseline,fault,outcome,independent,cleanup))]
        self.rows[case,name]=copy.deepcopy(observation(self.identity,case,name,events,assertions,self.execution))
        return copy.deepcopy(self.rows[case,name])

    def measurement(self,case,name,stage,sample):
        need(set(sample)=={'role','pid','data','tick'},'sample')
        return copy.deepcopy(event(self.identity,case,name,stage,sample['role'],sample['pid'],sample['tick'],sample['data']))


class Progress:
    def __init__(self,record,case,name,row):
        self.record,self.case,self.name,self.row=record,case,name,row
        self.cleanup_action=None;self.diagnose=None;self.resource=None;self.primary=None

    @property
    def life(self):return self.row['lifecycle']

    def attempt(self,stage):
        need(stage in STAGES,'attempt_stage');self.life['attempted']=stage

    def measured(self,stage,sample):
        value=self.record.measurement(self.case,self.name,stage,sample)
        existing=next((e for e in self.row['events'] if e['stage']==stage),None)
        if existing is not None:
            need(value==existing,'measurement_replaced');return
        need(not self.row['events'] or value['tick']>=self.row['events'][-1]['tick'],'measurement_order')
        self.row['events'].append(value);self.row['evidence_sha256']=digest(canonical(self.row['events']))
        self.life['last_completed']=stage

    def note(self,label,sample):
        need(label in ('diagnostic','provision','http-response') and len(self.life['notes'])<16,'note_bound')
        self.life['notes'].append(dict(label=label,event=self.record.measurement(self.case,self.name,'independent',sample)))

    def fail(self,error,kind='case',stage=None):
        failure=dict(kind=kind,stage=stage or self.life['attempted'],**safe_error(error))
        self.row['result']='FAILED'
        if self.primary is None:
            self.primary=error;self.life['first_failure']=failure
        elif error is not self.primary and len(self.life['secondary'])<16:self.life['secondary'].append(failure)

    def own(self,context,cleanup,diagnose=None):
        """Called by begin BEFORE operations that can partially create resources."""
        need(self.resource is None,'observation_owner')
        self.cleanup_action=cleanup;self.diagnose=diagnose
        identity=canonical([self.record.identity,self.case,self.name])
        self.resource=self.record.owner.track('observation-'+str(PARENTS.index(self.case))+'-'+str(OBSERVATIONS[self.case].index(self.name)),
                                             'observation',identity,self.cleanup)
        return context

    def checkpoint(self,boundary='observation',diagnose=None):
        capture=self.life['capture'][boundary]
        try:
            need(capture['result']=='UNREACHED','checkpoint_duplicate')
            capture['result']='PENDING'
            need(time.monotonic()<self.record.owner.deadline,'checkpoint_deadline')
            callback=diagnose or self.diagnose
            if callback is not None:self.note('diagnostic',callback())
            validate_partial(self.record.identity,self.case,self.name,self.row,self.record.execution)
            context=self.record.owner.snapshot()
            value=dict(profile='m6e-observation-checkpoint-v1',binding={k:self.record.identity[k] for k in ('source_sha','run_id','config_sha256')},
                       case=self.case,name=self.name,boundary=boundary,observation=copy.deepcopy(self.row),context=context)
            raw=canonical(value);need(len(raw)<=65536,'checkpoint_bound')
            need(time.monotonic()<self.record.owner.deadline,'checkpoint_deadline')
            if self.record.report_root is None:raise Failure('checkpoint_unconfigured')
            receipt=publish_bytes(self.record.report_root,checkpoint_stem(self.record.identity,self.case,self.name,boundary),raw)
            validate_checkpoint(raw,self.record.identity,self.case,self.name,boundary,receipt)
            capture.update(result='PASS',receipt=copy.deepcopy(receipt))
        except BaseException as error:
            capture.update(result='FAILED',receipt=None);self.fail(error,'capture');raise

    def evaluate(self,checks):
        self.attempt('assertions');need(type(checks) is list and len(checks)<=64,'assertion_bound');used=set()
        for check in checks:
            # Persist only evaluated assertions, including the actual failed check.
            assertion_shape(check)
            self.row['assertions'].append(copy.deepcopy(check))
            try:evaluate_assertion(self.row['events'],check,used)
            except BaseException:
                self.life['evaluated'].append('FAILED');raise
            self.life['evaluated'].append('PASS')
        self.life['last_completed']='assertions'

    def nested_cleanup(self,result):
        self.life['cleanup'].append(dict(boundary='provision-pending',result=result))

    def cleanup(self):
        if self.resource is None or self.resource['state']=='RELEASED':return
        self.attempt('cleanup')
        try:
            sample=self.cleanup_action()
            complete=sample['data'].get('remaining')==0 and sample['data'].get('exit')==0
            # Only an explicit complete return releases ownership. Invalid evidence
            # after that release must not trigger destructive cleanup a second time.
            if complete:self.resource['state']='RELEASED'
            if any(e['stage']=='cleanup' for e in self.row['events']):self.note('diagnostic',sample)
            else:self.measured('cleanup',sample)
            need(complete,'cleanup_incomplete')
            self.life['cleanup'].append(dict(boundary='observation',result='PASS'))
        except BaseException as error:
            if self.resource['state']!='RELEASED':self.resource['state']='UNRESOLVED'
            self.life['cleanup'].append(dict(boundary='observation',result='FAILED'))
            self.fail(error,'cleanup');raise

    def finish(self):
        if self.primary is not None:raise self.primary
        self.attempt('complete');self.row['result']='PASS'
        try:validate_observation(self.record.identity,self.case,self.name,self.row,self.record.execution)
        except BaseException as error:self.fail(error);raise


def check(stage,key,expected,op='eq'):return dict(stage=stage,key=key,op=op,expected=expected)


def _config(backend,owned,name,progress):
    progress.attempt('provision')
    provision=Provisioner(backend.config_storage(owned),backend.config_execute(owned),backend.config_observer(owned),
                          lambda:backend.admit('provisioning'),progress=progress)
    if name=='routine-zero-config-actions':
        value=backend.routine_publish_restore(owned)
        need(provision.actions==[] and value['config_actions']==0,'routine_configuration_action')
    elif name=='reconcile-served':value=provision.reconcile()
    else:value=provision.apply('invalid' if name=='invalid-candidate' else 'valid')
    progress.attempt('outcome')
    return backend.config_observe(owned,name,value,provision.actions,progress)


def _drive(case,phase,prefix,backend,record):
    for index,name in enumerate(OBSERVATIONS[case]):
        progress=record.start(case,name)
        try:
            if index==0:backend.admit(phase)
            progress.attempt('begin');owned=getattr(backend,prefix+'_begin')(name,progress)
            need(progress.resource is not None,'begin_cleanup_unregistered')
            for stage in ('baseline','fault'):
                progress.attempt(stage)
                sample=getattr(backend,prefix+'_'+stage)(owned,*([name] if stage=='fault' else []))
                progress.measured(stage,sample)
                if stage=='baseline':need(sample['data']['exit']==0,'positive_baseline')
            progress.attempt('outcome')
            values=_config(backend,owned,name,progress) if case==CONFIG else getattr(backend,prefix+'_observe')(owned,name,progress)
            outcome,independent,checks=values
            progress.measured('outcome',outcome);progress.measured('independent',independent)
            progress.evaluate(checks)
        except BaseException as error:progress.fail(error)
        finally:
            try:progress.checkpoint()
            except BaseException:pass  # checkpoint already retained its safe failure
            try:progress.cleanup()
            except BaseException:pass  # first failure and cleanup failure remain distinct
        progress.finish()


def apache_private_health_headers(backend,record):_drive(APACHE,'apache','http',backend,record)
def config_failure_preservation(backend,record):_drive(CONFIG,'provisioning','config',backend,record)
def worker_helper_authority_integration(backend,record):_drive(INSTALLED,'installed','identity',backend,record)

DRIVERS={APACHE:apache_private_health_headers,CONFIG:config_failure_preservation,INSTALLED:worker_helper_authority_integration}

def dispatch(name,backend,record):
    need(name in DRIVERS,'unknown_driver');DRIVERS[name](backend,record)
    need([n for c,n in record.rows if c==name]==list(OBSERVATIONS[name]),'driver_completeness')
    return dict(result='PASS',observations={n:copy.deepcopy(record.rows[name,n]) for n in OBSERVATIONS[name]})
