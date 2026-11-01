"""Actual driver control logic, shared by native execution and explicitly modeled local tests."""
import os
import time

from WebsitePlatformM6EAcceptance import OBSERVATIONS, event, observation, need, canonical, digest
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6EProvision import Provisioner

APACHE='apache_private_health_headers'
CONFIG='config_failure_preservation'
INSTALLED='worker_helper_authority_integration'

class Recorder:
    def __init__(self,identity,execution): self.identity,self.execution=identity,execution; self.rows={}; self.last=None
    def add(self,case,name,baseline,fault,outcome,independent,cleanup,assertions):
        need(name in OBSERVATIONS[case] and (case,name) not in self.rows,'duplicate_observation')
        self.last=[case,name]; ev=[]
        for stage,sample in zip(('baseline','fault','outcome','independent','cleanup'),(baseline,fault,outcome,independent,cleanup)):
            need(set(sample)=={'role','pid','data','tick'},'sample')
            ev.append(event(self.identity,case,name,stage,sample['role'],sample['pid'],sample['tick'],sample['data']))
        self.rows[case,name]=observation(self.identity,case,name,ev,assertions,self.execution)
        return self.rows[case,name]

def check(stage,key,expected,op='eq'): return dict(stage=stage,key=key,op=op,expected=expected)

def apache_private_health_headers(backend,record):
    backend.admit('apache')
    for name in OBSERVATIONS[APACHE]:
        record.last=[APACHE,name]; owned=None; primary=None
        try:
            # Every negative is preceded by a checked real candidate/active positive baseline.
            owned=backend.http_begin(name)
            baseline=backend.http_baseline(owned)
            need(baseline['data']['exit']==0,'apache_baseline')
            fault=backend.http_fault(owned,name)
            outcome,independent,assertions=backend.http_observe(owned,name)
        except BaseException as error:
            primary=error; raise
        finally:
            if owned is not None:
                try: cleanup=backend.http_cleanup(owned)
                except BaseException:
                    if primary is None: raise Failure('apache_cleanup')
                    backend.secondary('apache_cleanup')
        record.add(APACHE,name,baseline,fault,outcome,independent,cleanup,assertions)

def config_failure_preservation(backend,record):
    backend.admit('provisioning')
    for name in OBSERVATIONS[CONFIG]:
        record.last=[CONFIG,name]; owned=None; primary=None
        try:
            owned=backend.config_begin(name)
            baseline=backend.config_baseline(owned)
            fault=backend.config_fault(owned,name)
            provision=Provisioner(backend.config_storage(owned),backend.config_execute(owned),backend.config_observer(owned),
                                 lambda:backend.admit('provisioning'))
            if name=='routine-zero-config-actions':
                value=backend.routine_publish_restore(owned)
                need(provision.actions==[] and value['config_actions']==0,'routine_configuration_action')
            elif name=='reconcile-served': value=provision.reconcile()
            else: value=provision.apply('invalid' if name=='invalid-candidate' else 'valid')
            outcome,independent,assertions=backend.config_observe(owned,name,value,provision.actions)
        except BaseException as error:
            primary=error; raise
        finally:
            if owned is not None:
                try: cleanup=backend.config_cleanup(owned)
                except BaseException:
                    if primary is None: raise Failure('config_cleanup')
                    backend.secondary('config_cleanup')
        record.add(CONFIG,name,baseline,fault,outcome,independent,cleanup,assertions)

def worker_helper_authority_integration(backend,record):
    backend.admit('installed')
    for name in OBSERVATIONS[INSTALLED]:
        record.last=[INSTALLED,name]; owned=None; primary=None
        try:
            # Session/process/barrier resources are registered before construction starts.
            owned=backend.identity_begin(name)
            baseline=backend.identity_baseline(owned)
            fault=backend.identity_fault(owned,name)
            outcome,independent,assertions=backend.identity_observe(owned,name)
        except BaseException as error:
            primary=error; raise
        finally:
            if owned is not None:
                try: cleanup=backend.identity_cleanup(owned)
                except BaseException:
                    if primary is None: raise Failure('identity_cleanup')
                    backend.secondary('identity_cleanup')
        record.add(INSTALLED,name,baseline,fault,outcome,independent,cleanup,assertions)

DRIVERS={APACHE:apache_private_health_headers,CONFIG:config_failure_preservation,INSTALLED:worker_helper_authority_integration}

def dispatch(name,backend,record):
    need(name in DRIVERS,'unknown_driver')
    DRIVERS[name](backend,record)
    need([n for c,n in record.rows if c==name]==list(OBSERVATIONS[name]),'driver_completeness')
    return dict(result='PASS',observations={n:record.rows[name,n] for n in OBSERVATIONS[name]})
