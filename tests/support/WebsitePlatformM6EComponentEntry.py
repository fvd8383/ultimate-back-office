"""One supervised original-A request. No gate collector or receipt issuance path."""
import copy
import json
from pathlib import Path
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import decode, digest, need, hash_, safe_error
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6EBootstrapHost import NativeHost
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EBootstrap import inspect_ready, lifecycle_lock
from WebsitePlatformM6ECommission import budget, binding, current
import WebsitePlatformM6EBootstrapPlan as b
import WebsitePlatformM6EComponent as component

UNIT='ubo-m6e-component-a.service'
PROFILE='m6e-supervised-component-a-v1'
SECONDS=420

def unit_identity(host,ledger):
    entries=[r for r in ledger.state['entries'] if r['path']=='/run/systemd/system/'+UNIT]
    need(len(entries)==1,'component_unit_ownership');entry=entries[0];ledger.verify(entry)
    need(entry['state']!='RELEASED' and host.fs.read(entry['path'])==b.control_units()[UNIT],'component_unit_source')
    unit=host.unit(UNIT)
    need(unit['Id']==UNIT and unit['FragmentPath']==entry['path'] and not unit['DropInPaths'],'component_unit_identity')
    return unit

def supervisor(host,ledger):
    """Re-read the live identity, never accept a caller-provided admission flag."""
    context=host.component_context();group='/system.slice/'+UNIT
    need(context['cgroup']==['0::'+group],'component_supervisor_cgroup')
    rows=[r for r in ledger.state['services'] if r['unit']==UNIT]
    need(len(rows)==1,'component_service_admission');row=rows[0]
    unit=unit_identity(host,ledger)
    hash_(unit.get('InvocationID'),32)
    need(unit['ControlGroup']==group and unit['ActiveState'] in ('activating','active')
         and int(unit['MainPID'])==context['pid']>0 and context['invocation']==unit['InvocationID'],
         'component_supervisor_identity')
    need(row['state'] in ('DISPATCHED','ACTIVE') and row['pid']==context['pid']
         and row['invocation']==unit['InvocationID'] and row['cgroup']==group,'component_service_binding')
    request=ledger.state['observations'].get('component_request')
    need(type(request) is dict and request['state'] in ('DISPATCHED','RUNNING')
         and request['service']==dict(pid=row['pid'],invocation=row['invocation'],cgroup=group),
         'component_request_admission')
    commands=[r for r in ledger.state['commands'] if r['id']=='component-a-dispatch']
    need(len(commands)==1 and commands[0]==request['command'] and commands[0]['caller']=='operator'
         and type(commands[0]['pid']) is int and commands[0]['pid']>0 and commands[0]['exit']==0
         and type(commands[0]['finished_ns']) is int,'component_dispatch_command')
    budget(host,ledger,component.SECONDS)
    return row

def pristine(host,ledger,plan):
    need(plan['config']==ledger.config,'component_plan_admission')
    need('commission' not in ledger.state['observations'] and 'component_a' not in ledger.state['observations']
         and not any(r['unit']=='ubo-m6e-commission.service' for r in ledger.state['services']),
         'component_lifecycle_conflict')
    history=inspect_ready(host,ledger,plan)
    roots=current(host,ledger,plan,history)
    expected=binding(host,ledger,plan,history,{},roots)
    budget(host,ledger,component.SECONDS)
    return history,expected

def dispatch(host,ledger,plan):
    with lifecycle_lock(host,ledger):
        ledger=Ledger.load(host.fs,ledger.config);host.ledger=ledger
        need('component_request' not in ledger.state['observations']
             and not any(r['unit']==UNIT for r in ledger.state['services']),'component_already_dispatched')
        _,expected=pristine(host,ledger,plan);unit=unit_identity(host,ledger)
        need(unit['ActiveState']=='inactive' and unit.get('MainPID','0')=='0','component_service_not_idle')
        row=dict(unit=UNIT,state='STARTING',pid=None,invocation=None,cgroup='/system.slice/'+UNIT)
        ledger.state['services'].append(row)
        request=dict(profile=PROFILE,state='REQUESTED',binding=expected)
        ledger.state['observations']['component_request']=request;ledger.save()
        # The child waits on the SAME lifecycle lock while its invocation is bound.
        host.run('component-a-dispatch',['/usr/bin/systemctl','start','--no-block',UNIT])
        until=min(host.owner.deadline,time.monotonic()+10)
        while True:
            unit=unit_identity(host,ledger)
            if int(unit.get('MainPID','0'))>0:break
            need(time.monotonic()<until and unit['ActiveState'] in ('inactive','activating'),'component_dispatch_timeout')
            time.sleep(.02)
        hash_(unit.get('InvocationID'),32)
        need(unit['ControlGroup']==row['cgroup'] and unit['ActiveState'] in ('activating','active'),'component_dispatch_identity')
        row.update(pid=int(unit['MainPID']),invocation=unit['InvocationID'],state='DISPATCHED')
        request.update(state='DISPATCHED',service={k:row[k] for k in ('pid','invocation','cgroup')},
            command=copy.deepcopy(next(r for r in ledger.state['commands'] if r['id']=='component-a-dispatch')))
        ledger.save();ledger.publish('component-a-request',request)
    return request

def execute_owned(host,ledger,plan,backend_type=None):
    # The internal entry takes the lock too; direct calls cannot skip it or admission.
    with lifecycle_lock(host,ledger):
        ledger=Ledger.load(host.fs,ledger.config);host.ledger=ledger
        row=supervisor(host,ledger)
        request=ledger.state['observations']['component_request']
        need(request['state']=='DISPATCHED','component_already_executed')
        history,expected=pristine(host,ledger,plan)
        need(request['binding']==expected,'component_request_binding')
        need(decode(host.fs.read(ledger.root+'/component-a-request.json'))==request,'component_request_publication')
        request['state']='RUNNING';row['state']='ACTIVE';ledger.phase('component-running')
        if backend_type is None:
            from WebsitePlatformM6EComponentHost import Native
            backend_type=Native
        primary=None;outcome=None
        try:
            outcome=component.run(backend_type(host,ledger))
        except BaseException as error:
            primary=error;ledger.failure(error,'component-entry')
        preserved=False
        try:
            need(current(host,ledger,plan,history)==expected['roots_sha256'],'component_final_operational_state')
            preserved=True
        except BaseException as error:
            ledger.failure(error,'component-final-state');primary=primary or error
        state=ledger.state['observations'].get('component_a',{})
        captures={}
        for name in ('component-a-before-cleanup','component-a-owned-before-remove','component-a-result'):
            path=ledger.root+'/'+name+'.json'
            if host.fs.exists(path):
                raw=host.fs.read(path);captures[name]=dict(file=name+'.json',bytes=len(raw),sha256=digest(raw))
        result=dict(profile=PROFILE,execution=getattr(host,'lane','native-bootstrap'),binding=expected,
            result='ORIGINAL A VERIFIED AND REMOVED' if primary is None else 'COMPONENT INCOMPLETE',
            component=outcome,captures=captures,cleanup=state.get('cleanup','UNATTEMPTED'),first_failure=None if primary is None else safe_error(primary),
            operational_state_preserved=preserved,commissioning='NOT EXECUTED',host_receipt='ABSENT' if preserved else 'NOT VERIFIED',
            installed_workflow='NOT EXECUTED',native_acceptance='NOT EXECUTED')
        publication=ledger.publish('component-a-execution-result',result)
        request.update(state='COMPLETE' if primary is None else 'INCOMPLETE',result=result,publication=publication)
        ledger.phase('component-complete' if primary is None else 'component-incomplete')
        return result

def main(argv):
    need(argv in (['--component-a'],['--component-a-owned']),'component_entry_usage')
    host=NativeHost();config=host.config();host.namespace(config)
    ledger=Ledger.load(host.fs,config);host.ledger=ledger
    plan=decode(host.fs.read(ledger.root+'/plan.json'));host.owner.deadline=time.monotonic()+SECONDS
    if argv==['--component-a-owned']:result=execute_owned(host,ledger,plan)
    else:
        dispatch(host,ledger,plan)
        while time.monotonic()<host.owner.deadline:
            state=Ledger.load(host.fs,config).state
            if state['phase'] in ('component-complete','component-incomplete'):
                request=state['observations']['component_request'];result=request['result']
                raw=host.fs.read(ledger.root+'/component-a-execution-result.json')
                need(request['publication']==dict(file='component-a-execution-result.json',bytes=len(raw),sha256=digest(raw))
                     and decode(raw)==result,'component_result_publication');break
            need(state['phase'] in ('ready','component-running'),'component_fixture_removed')
            time.sleep(.2)
        else:raise Failure('component_client_timeout')
    print(json.dumps(result,sort_keys=True));return 0 if result['result']=='ORIGINAL A VERIFIED AND REMOVED' else 1

if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except (Exception,KeyboardInterrupt):
        print('{"result":"COMPONENT UNRESOLVED","host_receipt":"NOT VERIFIED","native_acceptance":"NOT EXECUTED"}')
        raise SystemExit(1)
