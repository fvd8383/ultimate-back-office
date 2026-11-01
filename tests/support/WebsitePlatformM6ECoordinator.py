"""Bounded acceptance coordination. Definitions only; never prepares infrastructure."""
import os
from pathlib import Path
import re
import time

from WebsitePlatformM6EAcceptance import (PARENTS, OBSERVATIONS, initial, canonical, validate,
    validate_published, publication_receipt, need)
from WebsitePlatformM6EDrivers import DRIVERS, Recorder, dispatch
from WebsitePlatformM6EProcess import Failure, Owner, publish_bytes


def failure(error,step):
    code=getattr(error,'code',type(error).__name__)
    if not isinstance(code,str) or re.fullmatch('[a-zA-Z0-9_]{1,80}',code) is None:code=type(error).__name__
    return dict(code=code,step=step,type=type(error).__name__)


def coordinate(identity,execution,factory,report_root,owner=None):
    """factory registers cleanup with owner before construction that can partly succeed.

    The first capture precedes resource cleanup. Final publication uses original
    bytes plus a separately finalized receipt. Namespace destruction is a separate
    operation and is never performed here. No failed case is retried.
    """
    owner=owner or Owner();started=time.monotonic();report=initial(identity,execution)
    record=Recorder(identity,execution);backend=None;publication=None;accepted=False
    def fail(error):
        if report['first_failure'] is None:report['first_failure']=failure(error,record.last)
        report['result']='FAILED'
    try:
        backend=factory(owner)
        for case in PARENTS:
            need(time.monotonic()<owner.deadline,'coordinator_deadline')
            record.last=[case,OBSERVATIONS[case][0]]
            if case in DRIVERS:row=dispatch(case,backend,record)
            else:row=backend.component(case,record)
            need(set(row)=={'result','observations'} and row['result']=='PASS','component_incomplete')
            need(set(row['observations'])==set(OBSERVATIONS[case]),'component_observations')
            report['cases'][case]=row
    except BaseException as error:fail(error)
    finally:
        report['last_step']=record.last
        # Keep completed observations within a failed parent instead of losing them.
        for (case,name),value in record.rows.items():report['cases'][case]['observations'][name]=value
        if report['first_failure'] and record.last is not None:
            report['cases'][record.last[0]]['result']='FAILED'
        report['children']=owner.context;report['resources']=owner.resources
        report['duration_ms']=max(1,int((time.monotonic()-started)*1000))
        try:
            publish_bytes(report_root,'evidence-before-cleanup',canonical(report));report['capture']='PASS'
        except BaseException as error:report['capture']='FAILED';fail(error)
        try:owner.cleanup();report['cleanup']='PASS'
        except BaseException as error:report['cleanup']='FAILED';fail(error)
        report['children']=owner.context;report['resources']=owner.resources
        report['duration_ms']=max(1,int((time.monotonic()-started)*1000))
        report['publication']='PENDING'
        if report['first_failure'] is None:
            report['result']='PASS'
            try:validate(report,identity,native=execution=='native')
            except BaseException as error:fail(error)
        raw=canonical(report)
        try:
            publication=publish_bytes(report_root,'report',raw)
            receipt=publication_receipt(raw,identity)
            publish_bytes(report_root,'publication',canonical(receipt))
            if report['first_failure'] is None:
                need(time.monotonic()<owner.deadline,'coordinator_deadline')
                validate_published(raw,identity,receipt,native=execution=='native');accepted=True
        except BaseException as error:
            fail(error)  # Original published bytes, if any, are never rewritten.
    return dict(accepted=accepted,report=report,publication=publication,exit=0 if accepted else 1)


def native_readiness(backend_type):
    """Executable capability inventory, not an operator-supplied success flag."""
    required=('commission_prerequisites','install_supervision','issue_prerequisite_receipt','load_identity',
              'component','identity_begin','identity_baseline','identity_fault','identity_observe','identity_cleanup',
              'command_audit','run_watchdog','finalize_owned_resources')
    return [name for name in required if not callable(getattr(backend_type,name,None))]


def require_native_ready():
    from WebsitePlatformM6ENativeBackend import NativeBackend
    missing=native_readiness(NativeBackend)
    if missing:raise Failure('native_implementation_incomplete',{'missing':missing})
    return NativeBackend


def native_main():
    need(os.name=='posix' and hasattr(os,'geteuid'),'linux_required')
    backend_type=require_native_ready()  # Before reading a receipt or creating resources.
    identity=backend_type.load_identity()
    result=coordinate(identity,'native',backend_type,Path('/run/ubo-m6e-fixture/reports'))
    return result['exit']
