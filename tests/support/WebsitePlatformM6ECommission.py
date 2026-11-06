"""Fixed disposable commissioning/inspection entry. Native producers are PARTIAL.

Native receipts remain blocked until every measured producer is implemented. This
module does not add capabilities to the full native acceptance readiness inventory.
"""
import copy
import json
import os
from pathlib import Path
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))
from WebsitePlatformM6EAcceptance import GATES, ROLES, canonical, decode, digest, need, keys, safe_error
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6EBootstrapHost import NativeHost
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EBootstrap import inspect_record, inspect_ready, lifecycle_lock
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECommissionEvidence as evidence
import WebsitePlatformM6ECommissionReceipt as receipt

SECONDS=420
UNIT='ubo-m6e-commission.service'

def budget(host,ledger,seconds):
    starts=[r['started_ns']/1e9 for r in ledger.state['commands'] if r['id']=='unit-start-ubo-m6e-watchdog']
    need(len(starts)==1 and ledger.state['watchdog']=='ARMED','commission_watchdog_identity')
    # Use the earlier command-start timestamp, never timer restart/elapsed guesses.
    end=min(starts[0]+p.LIFETIME_SECONDS,time.monotonic()+ledger.config['expires_at']-time.time())
    need(time.monotonic()+seconds+p.CLEANUP_SECONDS<end,'commission_cleanup_reserve')
    host.owner.deadline=min(host.owner.deadline,end-p.CLEANUP_SECONDS)
    need(time.monotonic()+seconds<host.owner.deadline,'commission_stage_deadline')
    return end

def current(host,ledger,plan,history,allow_receipt=False):
    """Dedicated post-bootstrap inspection: recorded additions may be RELEASED.

    The current partial producers restore the unpublished component state. This is
    not inspect_ready(), and never rewrites or republishes the historical READY.
    """
    host.namespace(plan['config']);host.check_pins(plan['config']);host.check_access(ledger)
    need(host.source(p.SOURCE,plan['config']['source_sha'])==plan['source']
         and host.source(p.CHECKOUT,plan['config']['source_sha'])==plan['source'],'commission_source_changed')
    need(time.time()<plan['config']['expires_at'] and ledger.state['watchdog']=='ARMED','commission_expired')
    for row in ledger.state['entries']:
        if row['state']=='RELEASED':need(not host.fs.exists(row['path']),'commission_replacement_after_cleanup')
        else:ledger.verify(row)
    services=[host.service_observation(name) for name in p.STARTED]
    services += [host.service_observation('ubo-site-worker.service',False),host.service_observation('ubo-m6e-watchdog.timer')]
    need(services==history['inspected']['services'],'commission_service_changed')
    listeners=host.listeners();host.check_listener_owners(listeners,services)
    need([{k:x[k] for k in ('protocol','ip','port','uid')} for x in listeners]==history['inspected']['listeners'],'commission_listeners')
    hashes={name:digest(host.fs.read(p.CONFIG+'/'+name)) for name in history['inspected']['configurations']}
    need(hashes==history['inspected']['configurations'],'commission_configuration_changed')
    need({name:digest(host.fs.read('/run/systemd/system/'+name)) for name in p.UNITS}==history['inspected']['units'],'commission_units_changed')
    f=ledger.state['observations']['fixture'];binding=p.template_values(f['site_key'])
    served=binding['EXACT_SERVED_SITE_ROOT'];journal=binding['EXACT_PRIVATE_JOURNAL_ROOT']
    need(not host.fs.exists(served+'/current') and not host.fs.exists(served+'/candidate')
         and decode(host.fs.read(journal+'/state.json'))==p.PRISTINE,'commission_component_state_unsettled')
    actual=host.setup('inspect','worker')
    need(actual==dict(fixture=f,database=ledger.state['observations']['database']),'commission_database_changed')
    need(allow_receipt or not host.fs.exists(receipt.RECEIPT),'commission_receipt_initially_absent')
    for name in ('assets','releases','staging'):need(not host.fs.inventory(served+'/'+name),'commission_unowned_served_state')
    roots={name:host.fs.identity(name) for name in (served,journal,p.CONFIG,p.CHECKOUT,p.STORAGE+'/sealed')}
    return digest(canonical(roots))

def binding(host,ledger,plan,history,installed,roots):
    handoff=decode(host.fs.read(p.CONTROL+'/bootstrap-handoff.json'))
    keys(handoff,('profile','version','binding','configuration_sha256','database','fixture','roles','endpoints',
                  'source_inventory_sha256','tools','auxiliary_sha256','apache_modules'))
    need(handoff['profile']==p.PROFILE+'-handoff' and handoff['version']==1
         and handoff['binding']==p.base_identity(plan['config']) and handoff['roles']==ROLES
         and handoff['endpoints']==p.ENDPOINTS and handoff['tools']==plan['versions']
         and handoff['source_inventory_sha256']==digest(canonical(plan['source']))
         and handoff['configuration_sha256']==history['inspected']['configuration_sha256']
         and handoff['auxiliary_sha256']==plan['config']['auxiliary_sha256']
         and handoff['apache_modules']==plan['config']['apache_modules']
         and handoff['fixture']==ledger.state['observations']['fixture']
         and handoff['database']==ledger.state['observations']['database'],'commission_handoff')
    value=p.base_identity(plan['config'])|dict(bootstrap_sha256=digest(host.fs.read(ledger.root+'/bootstrap.json')),
        handoff_sha256=history['handoff_sha256'],configuration_sha256=history['inspected']['configuration_sha256'],
        source_inventory_sha256=digest(canonical(plan['source'])),tools_sha256=digest(canonical(plan['config']['tool_sha256'])),
        modules_sha256=digest(canonical(plan['config']['apache_modules'])),roles_sha256=digest(canonical(ROLES)),
        roots_sha256=roots,installed_sha256=digest(canonical(installed)),expires_at=plan['config']['expires_at'])
    # Expiry is shortened below to the absolute watchdog deadline, never extended.
    evidence.validate_binding(value);return value

def collect(host,ledger,backend,expected):
    """Actual ordered collector; capture before any producer restoration, first failure wins."""
    receipts={};primary=None
    for gate in GATES:
        started=int(time.time());observed=None;capture=None;cleaned=None;error=None
        backend.partial={}
        try:
            budget(host,ledger,backend.stage_seconds(gate))
            observed=backend.probe(gate)
            evidence.measurements(gate,observed['data'])
        except BaseException as failure:
            error=failure;ledger.failure(failure,'commission-probe')
        finally:
            # Measurements are copied once. The cleanup callback never receives
            # this object and cannot fill absent results from expected values.
            before=dict(profile=evidence.PROFILE+'-before-restore',binding=expected,gate=gate,
                execution=backend.execution,observations=copy.deepcopy(observed if observed is not None else backend.partial),
                first_failure=None if error is None else safe_error(error))
            try:capture=ledger.publish('commission-'+gate+'-before-restore',before)
            except BaseException as failure:
                if error is None:error=failure
                else:ledger.failure(failure,'commission-capture')
            try:cleaned=backend.cleanup(gate)
            except BaseException as failure:
                if error is None:error=failure
                else:ledger.failure(failure,'commission-cleanup')
        if error is not None:
            primary=error;ledger.failure(primary,'commission-probe');break
        row=dict(profile=evidence.PROFILE,version=1,gate=gate,producer=evidence.PRODUCERS[gate],execution=backend.execution,
            binding=expected,started_at=started,finished_at=int(time.time()),observations=copy.deepcopy(observed),
            observations_sha256=digest(canonical(observed)),capture=capture,cleanup=cleaned,
            result='INCOMPLETE' if evidence.MISSING[gate] else 'MEASURED',missing=list(evidence.MISSING[gate]))
        raw=canonical(row)
        evidence.validate_record(raw,expected,gate,int(time.time()),native=backend.execution!='controlled-local',complete=False)
        receipts[gate]=ledger.publish('commission-'+gate,row)
        ledger.state['observations']['commission'].setdefault('publications',{})[gate]=receipts[gate]
        ledger.save()
    if primary is not None:raise primary
    return receipts

def issue(host,ledger,backend,expected,publications,installed,loaded,history,plan):
    budget(host,ledger,40)
    # This is the only operational caller of the internal publication primitive.
    # Incomplete producers and any controlled-local evidence fail here first.
    gates=evidence.finalized_evidence(lambda name:host.fs.read(ledger.root+'/'+name),expected,publications,int(time.time()))
    need(current(host,ledger,plan,history)==expected['roots_sha256'],'commission_final_roots')
    receipt.verify_inventory(host.fs,installed,loaded)
    need(digest(canonical(installed))==expected['installed_sha256'],'commission_inventory_binding')
    now=int(time.time());need(now<expected['expires_at'],'commission_expired')
    candidate=evidence.format_receipt(host.fs.read(p.CONFIG+'/worker.json'),installed,gates,now,min(now+86400,expected['expires_at']))
    backend.validate_format(candidate,now)
    # Recheck immediately after formatting, before any receipt effects.
    current(host,ledger,plan,history);receipt.verify_inventory(host.fs,installed,loaded);budget(host,ledger,10)
    def recheck():
        host.namespace(plan['config']);host.check_pins(plan['config']);host.check_access(ledger)
        need(host.source(p.SOURCE,plan['config']['source_sha'])==plan['source'] and
             host.source(p.CHECKOUT,plan['config']['source_sha'])==plan['source'],'commission_issuance_source')
        receipt.verify_inventory(host.fs,installed,loaded);budget(host,ledger,10)
        need(int(time.time())<candidate['expires_at'],'commission_issuance_expired')
    return receipt.publish(host.fs,ledger,canonical(candidate),expected,backend.acknowledge,recheck=recheck)

def commission(host,ledger,plan,backend_type=None):
    need(ledger.state['phase']=='ready' and 'commission' not in ledger.state['observations'],'commission_already_attempted')
    host.ledger=ledger;history=inspect_ready(host,ledger,plan)
    budget(host,ledger,SECONDS-20)
    if backend_type is None:
        from WebsitePlatformM6ECommissionProbes import NativeProbes
        backend_type=NativeProbes
    backend=backend_type(host,ledger,plan,history)
    loaded=backend.loaded_files();installed=receipt.installed_inventory(host.fs,loaded)
    roots=current(host,ledger,plan,history)
    expected=binding(host,ledger,plan,history,installed,roots)
    end=budget(host,ledger,30)
    expected['expires_at']=min(expected['expires_at'],int(time.time()+end-time.monotonic()))
    backend.binding=expected
    ledger.state['observations']['commission']=dict(binding=expected,resources=[],publications={},result='COLLECTING')
    ledger.phase('commissioning');ledger.publish('commission-start',dict(binding=expected,installed_files=installed,loaded_files=loaded))
    primary=None;result=None;finished=False
    try:
        publications=collect(host,ledger,backend,expected)
        ledger.state['observations']['commission']['publications']=publications;ledger.save()
        backend.finish();finished=True
        result=issue(host,ledger,backend,expected,publications,installed,loaded,history,plan)
        result.update(prerequisites='HOST PREREQUISITES VERIFIED',installed_workflow='NOT EXECUTED',native_acceptance='NOT EXECUTED')
    except BaseException as error:
        primary=error;ledger.failure(error,'commission')
        uncertain=ledger.state['observations'].get('commission_issuance')=='UNCERTAIN' or host.fs.exists(receipt.RECEIPT)
        result=dict(result='COMMISSIONING INCOMPLETE',host_receipt='UNCERTAIN' if uncertain else 'ABSENT',
                    installed_workflow='NOT EXECUTED',native_acceptance='NOT EXECUTED',
                    missing={gate:list(items) for gate,items in evidence.MISSING.items() if items})
    finally:
        try:
            if not finished:backend.finish()
        except BaseException as error:
            ledger.failure(error,'commission-cleanup')
            primary=primary or error
            if result is not None:result['result']='COMMISSIONING INCOMPLETE'
        # A final acknowledgement may have failed. Never call pristine bootstrap
        # inspection, delete the receipt, or retry issuance from this branch.
        ledger.state['observations']['commission']['result']=result
        try:
            ledger.phase('commission-incomplete' if primary else 'commissioned')
            ledger.publish('commission-result',result)
        except BaseException as error:
            ledger.failure(error,'commission-capture')
            if primary is None:raise
    return result

def inspect(host,ledger,plan):
    host.readonly=True
    history=inspect_record(host,ledger,plan)
    need('commission' in ledger.state['observations'],'commission_not_attempted')
    state=ledger.state['observations']['commission'];start=decode(host.fs.read(ledger.root+'/commission-start.json'))
    unit=host.unit(UNIT)
    need(any(row['unit']==UNIT for row in ledger.state['services']) and
         unit['FragmentPath']=='/run/systemd/system/'+UNIT and unit['ActiveState'] in ('inactive','failed')
         and unit.get('MainPID','0')=='0','commission_inspection_service')
    processes=host.fs.path('/sys/fs/cgroup/system.slice/'+UNIT+'/cgroup.procs')
    need(not processes.exists() or not processes.read_bytes().strip(),'commission_processes_remain')
    need(start['binding']==state['binding'],'commission_start_binding')
    expected=start['binding'];evidence.validate_binding(expected)
    need(current(host,ledger,plan,history,allow_receipt=True)==expected['roots_sha256'],'commission_inspection_roots')
    receipt.verify_inventory(host.fs,start['installed_files'],start['loaded_files'])
    need(binding(host,ledger,plan,history,start['installed_files'],expected['roots_sha256'])|
         {'expires_at':expected['expires_at']}==expected,'commission_inspection_binding')
    need(time.time()<expected['expires_at'],'commission_inspection_expired');budget(host,ledger,0)
    need(set(state['publications'])<=set(GATES),'commission_inspection_gates')
    for gate,publication in state['publications'].items():
        evidence.published_record(lambda name:host.fs.read(ledger.root+'/'+name),expected,gate,publication,int(time.time()),
            native=getattr(host,'lane','native-bootstrap')!='controlled-local',complete=False)
    if host.fs.exists(receipt.RECEIPT):
        evidence.finalized_evidence(lambda name:host.fs.read(ledger.root+'/'+name),expected,state['publications'],int(time.time()))
        status=receipt.reconcile(host.fs,ledger,expected,int(time.time()))
    else:
        need(ledger.state['observations'].get('commission_issuance')!='UNCERTAIN','receipt_issuance_unresolved')
        status=dict(result='COMMISSIONING INCOMPLETE',host_receipt='ABSENT')
    return status|dict(installed_workflow='NOT EXECUTED',native_acceptance='NOT EXECUTED')

def main(argv):
    need(argv in (['--commission'],['--commission-owned'],['--inspect-commissioning']),'commission_usage')
    host=NativeHost();config=host.config();host.namespace(config)
    ledger=Ledger.load(host.fs,config);host.ledger=ledger;plan=decode(host.fs.read(ledger.root+'/plan.json'))
    need(plan['config']==config,'commission_plan');host.owner.deadline=time.monotonic()+SECONDS
    if argv==['--commission']:
        with lifecycle_lock(host,ledger):
            ledger=Ledger.load(host.fs,config);host.ledger=ledger
            need(ledger.state['phase']=='ready' and 'commission' not in ledger.state['observations'],'commission_already_attempted')
            need(not any(row['unit']==UNIT for row in ledger.state['services']),'commission_already_dispatched')
            inspect_ready(host,ledger,plan);budget(host,ledger,SECONDS-20)
            entry=next(r for r in ledger.state['entries'] if r['path']=='/run/systemd/system/'+UNIT);ledger.verify(entry)
            ledger.state['services'].append(dict(unit=UNIT,state='STARTING',pid=None,invocation=None,cgroup=None));ledger.save()
            host.run('commission-start',['/usr/bin/systemctl','start','--no-block',UNIT])
        # The service owns execution; disconnect does not abandon child processes.
        while time.monotonic()<host.owner.deadline:
            current_ledger=Ledger.load(host.fs,config)
            if current_ledger.state['phase'] in ('commissioned','commission-incomplete'):
                result=current_ledger.state['observations']['commission']['result'];break
            need(current_ledger.state['phase'] not in ('teardown','removed','cleanup-limited'),'commission_fixture_removed')
            time.sleep(.2)
        else:raise Failure('commission_client_timeout')
    else:
        with lifecycle_lock(host,ledger):
            ledger=Ledger.load(host.fs,config);host.ledger=ledger
            if argv==['--commission-owned']:
                need('0::/system.slice/'+UNIT in Path('/proc/self/cgroup').read_text().splitlines(),'commission_supervisor')
                unit=host.unit(UNIT);row=next(r for r in ledger.state['services'] if r['unit']==UNIT)
                need(unit['FragmentPath']=='/run/systemd/system/'+UNIT and int(unit['MainPID'])==os.getpid(),'commission_service_identity')
                row.update(pid=os.getpid(),invocation=unit['InvocationID'],cgroup='/system.slice/'+UNIT,state='ACTIVE');ledger.save()
                result=commission(host,ledger,plan)
            else:result=inspect(host,ledger,plan)
    print(json.dumps(result,sort_keys=True));return 0 if result.get('result') in ('RECEIPT ISSUED','EXACT RECEIPT PRESENT') else 1

if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except (Exception,KeyboardInterrupt):
        print('{"result":"COMMISSIONING UNRESOLVED","host_receipt":"NOT VERIFIED","native_acceptance":"NOT EXECUTED"}')
        raise SystemExit(1)
