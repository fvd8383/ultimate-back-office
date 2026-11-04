"""Fixed disposable bootstrap/inspection/teardown. Import and --plan have no host effects."""
import copy
import json
import os
from pathlib import Path
import stat
import sys
import time
from contextlib import contextmanager

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))

from WebsitePlatformM6EAcceptance import ROLES, canonical, decode, digest, need, keys, hash_
from WebsitePlatformM6EProcess import Failure
from WebsitePlatformM6EBootstrapLedger import Ledger
from WebsitePlatformM6EBootstrapHost import NativeHost
import WebsitePlatformM6EBootstrapPlan as p
import WebsitePlatformM6ECheckpoint as checkpoint
import WebsitePlatformM6ESnapshot as snapshot

TERMINAL='FIXTURE BOOTSTRAP READY'

def generated(host,outputs,action):
    """Intent precedes a command that may leave only a prefix of its output files."""
    ledger=host.ledger;rows=[ledger.generated(path,'fixed-command') for path,_,_,_ in outputs]
    primary=None
    try:return action()
    except BaseException as error:primary=error;raise
    finally:
        try:
            for row,(_,uid,gid,mode) in zip(rows,outputs):
                if host.fs.exists(row['path']):
                    info=host.fs.identity(row['path']);need(info['kind']==stat.S_IFREG and info['uid']==0,'generated_identity')
                    ledger.identify(row);host.fs.metadata(row['path'],uid,gid,mode);ledger.identify(row)
                    # Secret files are pinned by digest only; bytes never enter a ledger/report.
                    row['sha256']=digest(host.fs.read(row['path']));ledger.save()
        except BaseException as capture:
            if primary is None:raise
            ledger.failure(primary,'prepare');ledger.failure(capture,'capture')

def trees(host,paths,action):
    ledger=host.ledger;rows=[next(x for x in ledger.state['entries'] if x['path']==path) for path in paths]
    primary=None
    try:return action()
    except BaseException as error:primary=error;raise
    finally:
        try:
            for row in rows:ledger.capture_tree(row,tree_uids(row),final=row['producer'] in ('git','artifact-builder'))
        except BaseException as capture:
            if primary is None:raise
            ledger.failure(primary,'prepare');ledger.failure(capture,'capture')

def tree_uids(row):
    return {'git':{0},'mysql':{0,ROLES['mysql']},'apache':{0,ROLES['apache']},'artifact-builder':{ROLES['worker']}}[row['producer']]

def install_source(host,plan):
    ledger=host.ledger
    ledger.directory(p.CHECKOUT,producer='git')
    def clone():
        host.run('checkout-clone',['/usr/bin/git','clone','--local','--no-hardlinks','--no-checkout',p.SOURCE,p.CHECKOUT],60)
        host.run('checkout-detach',['/usr/bin/git','-C',p.CHECKOUT,'checkout','--detach',plan['config']['source_sha']],30)
        host.protect_checkout(plan['source'])
        need(host.source(p.CHECKOUT,plan['config']['source_sha'])==plan['source'],'installed_source_identity')
    trees(host,[p.CHECKOUT],clone)
    ledger.directory('/usr/local/lib/ubo-site-publisher/php')
    for name in ('ubo_site_publish.py','ubo-site-authority.php'):
        raw=host.fs.read(p.CHECKOUT+'/infrastructure/deployment/'+name)
        ledger.write('/usr/local/lib/ubo-site-publisher/'+name,raw)
    ledger.write(p.LAUNCHER,host.fs.read(p.CHECKOUT+'/infrastructure/deployment/ubo-site-publish'),mode=0o755)
    for name in plan['source']:
        if name.startswith('private/classes/') and name.endswith('.php'):
            # Preserve runtime-relative includes (e.g. domains/DomainManager.php).
            # Flattening basenames loses dependencies observed by the issuer.
            relative=name[len('private/classes/'):]
            destination='/usr/local/lib/ubo-site-publisher/php/'+relative
            parent='/usr/local/lib/ubo-site-publisher/php'
            for part in relative.split('/')[:-1]:
                parent+='/'+part
                if not host.fs.exists(parent):ledger.directory(parent)
                else:ledger.verify(next(row for row in ledger.state['entries'] if row['path']==parent))
            ledger.write(destination,host.fs.read(p.CHECKOUT+'/'+name))

def tls(host):
    ledger=host.ledger;root=p.CONFIG+'/tls';ledger.directory(root)
    for label in ('ca','server','client','wrong'):
        generated(host,[(root+'/'+label+'.key',0,ROLES['mysql'] if label=='server' else ROLES['worker'] if label in ('client','wrong') else 0,
                          0o600 if label=='ca' else 0o640)],
          lambda label=label:host.run('tls-key',['/usr/bin/openssl','genpkey','-algorithm','RSA','-pkeyopt','rsa_keygen_bits:2048','-out',root+'/'+label+'.key']))
    generated(host,[(root+'/ca.pem',0,0,0o644)],lambda:host.run('tls-ca',['/usr/bin/openssl','req','-new','-x509','-key',root+'/ca.key',
        '-out',root+'/ca.pem','-days','1','-subj','/CN=M6E disposable CA']))
    for index,label in enumerate(('server','client','wrong'),2):
        generated(host,[(root+'/'+label+'.csr',0,0,0o644)],lambda label=label:host.run('tls-request',['/usr/bin/openssl','req','-new',
            '-key',root+'/'+label+'.key','-out',root+'/'+label+'.csr','-subj','/CN=m6e-'+label]))
        ext=b'subjectAltName=DNS:active.m6e.invalid,DNS:candidate.m6e.invalid,IP:127.0.0.1,IP:127.0.0.2,IP:127.0.0.3\nextendedKeyUsage=serverAuth\n' if label=='server' else b'extendedKeyUsage=clientAuth\n'
        ledger.write(root+'/'+label+'.ext',ext)
        args=['/usr/bin/openssl','x509','-req','-in',root+'/'+label+'.csr','-out',root+'/'+label+'.pem','-days','1','-extfile',root+'/'+label+'.ext']
        args+=['-signkey',root+'/wrong.key'] if label=='wrong' else ['-CA',root+'/ca.pem','-CAkey',root+'/ca.key','-set_serial',str(index)]
        generated(host,[(root+'/'+label+'.pem',0,0,0o644)],lambda args=args:host.run('tls-certificate',args))

def schema_identity(value,config):
    keys(value,('schema','server_uuid','writer','worker','authority','credentials_sha256','migrations','seed_247sp'))
    need(value['schema']=='ubo_m6e_'+config['run_id'] and value['writer']=='m6e_writer' and value['worker']=='m6e_worker'
         and value['authority']=='m6e_observer' and value['migrations']==list(range(1,26)) and value['seed_247sp']==1,'schema_identity')
    p.template_values(value['server_uuid']);hash_(value['credentials_sha256']);return value

def prepare_owned(host,ledger,plan):
    host.ledger=ledger;config=plan['config'];primary=None
    # Repeated preparation is rejected before entering any failure-cleanup scope.
    need(ledger.state['phase']=='admitted','bootstrap_already_started')
    try:
        host.namespace(config);need(ledger.state['watchdog']=='ARMED','watchdog_first')
        need(host.source(p.SOURCE,config['source_sha'])==plan['source'],'source_changed_after_plan')
        ledger.phase('identities')
        # Apache's read group exists before the helper is assigned its one supplementary group.
        for role in ('coordinator','worker','apache','helper','authority','mysql','writer'):host.create_account(role,config['run_id'])
        ledger.phase('directories')
        for root in p.ROOTS:
            if root!=p.CHECKOUT:ledger.directory(root)
        for name in ('work','sealed','uploads'):ledger.directory(p.STORAGE+'/'+name,ROLES['worker'],ROLES['worker'],0o700,producer='artifact-builder')
        for name in ('mysql','mysql-run'):ledger.directory(p.STORAGE+'/'+name,ROLES['mysql'],ROLES['mysql'],0o700 if name=='mysql' else 0o755,producer='mysql')
        ledger.directory(p.CONTROL+'/apache',producer='apache')
        for name in ('reports','state','component-source','component-served','component-journal'):
            ledger.directory(p.CONTROL+'/'+name,ROLES['coordinator'],ROLES['coordinator'],0o700)
        for role in ('worker','writer'):ledger.directory(p.CONTROL+'/'+role,ROLES[role],ROLES[role],0o700)
        ledger.phase('source');install_source(host,plan)
        ledger.phase('tls');tls(host)
        ledger.write(p.CONTROL+'/bootstrap.json',canonical(config|{'tools':plan['versions']}))
        ledger.write(p.CONFIG+'/mysql.cnf',p.mysql_config())
        mysql_unit=p.fixture_units(plan['templates'],config,'00000000-0000-4000-8000-000000000000')['ubo-m6e-mysql.service']
        ledger.write('/run/systemd/system/ubo-m6e-mysql.service',mysql_unit)
        ledger.phase('database')
        trees(host,[p.STORAGE+'/mysql',p.STORAGE+'/mysql-run'],lambda:host.run('mysql-initialize',
            ['/usr/sbin/mysqld','--defaults-file='+p.CONFIG+'/mysql.cnf','--initialize-insecure'],90))
        host.systemctl('daemon-reload');host.start('ubo-m6e-mysql.service');host.wait_mysql()
        outputs=[(p.CONTROL+'/admin.json',0,0,0o600),(p.CONTROL+'/writer.json',0,ROLES['writer'],0o640),
          (p.CONFIG+'/authority-credentials.json',0,ROLES['authority'],0o640),(p.CONFIG+'/application.php',0,ROLES['worker'],0o640),
          (p.CHECKOUT+'/private/config/env.php',0,ROLES['worker'],0o640),(p.CONTROL+'/database-identity.json',0,0,0o644),
          (snapshot.CREDENTIALS,0,ROLES['worker'],0o640),(snapshot.SCHEMA,0,ROLES['worker'],0o640)]
        database=schema_identity(generated(host,outputs,lambda:host.setup('schema')),config)
        ledger.state['observations']['database']=database;ledger.save()
        ledger.phase('artifacts')
        fixture=p.validate_fixture(trees(host,[p.STORAGE+'/'+x for x in ('work','sealed','uploads')],
            lambda:checkpoint.capture(host,lambda:snapshot.capture(host,lambda:host.setup('build-two','worker')))),config['run_id'])
        checkpoint.archive(host,ledger,plan,fixture)
        snapshot.archive(host,ledger,plan,fixture)
        ledger.write(p.CONTROL+'/fixture.json',canonical(fixture))
        ledger.state['observations']['fixture']=fixture;ledger.save()
        ledger.phase('configuration')
        paths=p.template_values(fixture['site_key']);served=paths['EXACT_SERVED_SITE_ROOT'];journal=paths['EXACT_PRIVATE_JOURNAL_ROOT']
        for root in ('/var/www/ubo-sites/staging','/var/www/ubo-sites/staging/sites','/var/lib/ubo-site-publisher/staging'):ledger.directory(root)
        ledger.directory(served,ROLES['helper'],ROLES['apache'],0o750)
        ledger.directory(journal,ROLES['helper'],ROLES['helper'],0o700)
        for name in ('releases','assets','staging'):ledger.directory(served+'/'+name,ROLES['helper'],ROLES['apache'],0o700 if name=='staging' else 0o750)
        ledger.write(journal+'/state.json',canonical(p.PRISTINE),ROLES['helper'],ROLES['helper'],0o600)
        ledger.write(journal+'/target.lock',b'',ROLES['helper'],ROLES['helper'],0o600)
        configuration=p.configurations(fixture,database,digest(host.fs.read(p.CHECKOUT+'/private/config/env.php')))
        for name,value in configuration.items():ledger.write(p.CONFIG+'/'+name,canonical(value),gid=ROLES['worker'] if name=='worker.json' else 0,mode=0o640 if name=='worker.json' else 0o644)
        apache=p.apache_main(config,plan['templates']['apache'],fixture['site_key'])
        ledger.write(p.CONFIG+'/mime.types',b'# Fixed public MIME types are declared by the reviewed vhost policy.\n')
        ledger.write(p.CONFIG+'/apache.conf',apache);ledger.write(p.CONFIG+'/apache.reviewed.conf',apache)
        for name,raw in p.fixture_units(plan['templates'],config,fixture['site_key']).items():
            if name!='ubo-m6e-mysql.service':ledger.write('/run/systemd/system/'+name,raw)
        # Complete main config is checked only in this later admitted native path, never by desktop controls.
        host.run('apache-configtest',['/usr/sbin/apache2','-t','-f',p.CONFIG+'/apache.conf'])
        host.systemctl('daemon-reload');ledger.phase('services')
        for name in p.STARTED[1:]:
            if name in p.SOCKETS:
                row=ledger.intent(p.SOCKETS[name][0],'socket','systemd')
                try:host.start(name)
                finally:
                    if host.fs.exists(row['path']):ledger.identify(row)
            else:host.start(name)
        ledger.phase('inspection')
        inspected=inspect_base(host,ledger,plan)
        # This protected, measured handoff is an input to future commissioning, not its result.
        handoff=dict(profile=p.PROFILE+'-handoff',version=1,binding=p.base_identity(config),
            configuration_sha256=inspected['configuration_sha256'],database=database,fixture=fixture,
            roles=ROLES,endpoints=p.ENDPOINTS,source_inventory_sha256=digest(canonical(plan['source'])),
            tools=plan['versions'],auxiliary_sha256=config['auxiliary_sha256'],apache_modules=config['apache_modules'])
        ledger.write(p.CONTROL+'/bootstrap-handoff.json',canonical(handoff),mode=0o600)
        record=dict(profile=p.PROFILE,version=1,binding=p.base_identity(config),execution=getattr(host,'lane','native-bootstrap'),
            result=TERMINAL,commissioning='NOT EXECUTED',host_receipt='ABSENT',native_acceptance='NOT EXECUTED',
            inspected=inspected,handoff_sha256=digest(canonical(handoff)),watchdog='ARMED',plan_sha256=plan['plan_sha256'])
        receipt=ledger.publish('bootstrap',record);ledger.publish('bootstrap-publication',receipt)
        ledger.state['observations']['bootstrap_receipt']=receipt;ledger.phase('ready');return record
    except BaseException as error:
        primary=error;ledger.failure(error,'prepare')
        try:ledger.publish('failure-'+str(ledger.state['sequence']),ledger.public())
        except BaseException as capture:ledger.failure(capture,'capture')
        host.owner.deadline=time.monotonic()+p.CLEANUP_SECONDS
        try:teardown(host,ledger,inside_prepare=True)
        except BaseException as cleanup:ledger.failure(cleanup,'cleanup')
        raise primary

def inspect_base(host,ledger,plan):
    config=plan['config'];host.namespace(config)
    need(time.time()<config['expires_at'] and ledger.state['watchdog']=='ARMED','base_expired_or_unarmed')
    need(not host.fs.exists(p.CONFIG+'/host-receipt.json'),'host_receipt_must_be_absent')
    need(host.source(p.CHECKOUT,config['source_sha'])==plan['source'],'inspection_source')
    host.check_pins(config)
    for row in ledger.state['entries']:
        if row['state']=='RELEASED':continue
        ledger.verify(row)
    host.check_access(ledger)
    fixture=ledger.state['observations']['fixture'];database=ledger.state['observations']['database']
    actual=host.setup('inspect','worker');keys(actual,('fixture','database'))
    need(p.validate_fixture(actual['fixture'],config['run_id'])==fixture and schema_identity(actual['database'],config)==database,'independent_fixture')
    services=[host.service_observation(name) for name in p.STARTED]
    services.append(host.service_observation('ubo-site-worker.service',False))
    services.append(host.service_observation('ubo-m6e-watchdog.timer'))
    for observed in services:
        if observed['state']=='INACTIVE':continue
        registered=next(x for x in ledger.state['services'] if x['unit']==observed['unit'])
        need(all(registered[k]==observed[k] for k in ('pid','invocation','cgroup')),'service_invocation_changed')
    listeners=host.listeners()
    expected={('tcp','127.0.0.1',3306),('tcp','127.0.0.2',443),('tcp','127.0.0.3',443)}
    need({(x['protocol'],x['ip'],x['port']) for x in listeners}==expected and len(listeners)==3,'private_listeners')
    host.check_listener_owners(listeners,services)
    paths=p.template_values(fixture['site_key']);served=paths['EXACT_SERVED_SITE_ROOT'];journal=paths['EXACT_PRIVATE_JOURNAL_ROOT']
    for name in ('current','candidate'):need(not host.fs.exists(served+'/'+name),'no_activation')
    need(decode(host.fs.read(journal+'/state.json'))==p.PRISTINE,'pristine_journal')
    need(not host.fs.inventory(served+'/assets') and not host.fs.inventory(served+'/releases') and not host.fs.inventory(served+'/staging'),'unpublished_output')
    configs={name:digest(host.fs.read(p.CONFIG+'/'+name)) for name in ('worker.json','helper.json','authority.json','apache.conf','mysql.cnf','application.php')}
    units={name:digest(host.fs.read('/run/systemd/system/'+name)) for name in p.UNITS}
    return dict(configuration_sha256=digest(canonical(configs)),configurations=configs,units=units,
      services=services,listeners=[{k:x[k] for k in ('protocol','ip','port','uid')} for x in listeners],
      database_sha256=digest(canonical(database)),fixture_sha256=digest(canonical(fixture)),
      pre_b_source_sha256=checkpoint.verify(host,ledger,plan,fixture),
      pre_b_database_sha256=snapshot.verify(host,ledger,plan,fixture),
      source_sha256=digest(canonical(plan['source'])),host_receipt='ABSENT',activation='ABSENT',publisher_operations=0)

def inspect_record(host,ledger,plan):
    """Validate immutable bootstrap history, independently of subsequent lifecycle state."""
    raw=host.fs.read(ledger.root+'/bootstrap.json');receipt=ledger.state['observations']['bootstrap_receipt']
    need(receipt==dict(file='bootstrap.json',bytes=len(raw),sha256=digest(raw))
         and decode(host.fs.read(ledger.root+'/bootstrap-publication.json'))==receipt,'bootstrap_publication')
    record=decode(raw)
    keys(record,('profile','version','binding','execution','result','commissioning','host_receipt','native_acceptance',
                 'inspected','handoff_sha256','watchdog','plan_sha256'))
    need(record['profile']==p.PROFILE and record['version']==1 and record['binding']==p.base_identity(plan['config'])
         and record['execution']==getattr(host,'lane','native-bootstrap') and record['result']==TERMINAL
         and record['commissioning']==record['native_acceptance']=='NOT EXECUTED'
         and record['host_receipt']=='ABSENT' and record['watchdog']=='ARMED'
         and record['plan_sha256']==plan['plan_sha256'],'bootstrap_record')
    need(digest(host.fs.read(p.CONTROL+'/bootstrap-handoff.json'))==record['handoff_sha256'],'handoff_bytes')
    return record

def inspect_ready(host,ledger,plan):
    need(ledger.state['phase']=='ready','base_not_ready')
    record=inspect_record(host,ledger,plan)
    need(inspect_base(host,ledger,plan)==record['inspected'],'bootstrap_state_changed')
    return record

def teardown(host,ledger,inside_prepare=False):
    host.namespace(ledger.config);host.ledger=ledger;errors=[]
    controls={'/run/systemd/system/'+name for name in p.control_units()}
    # Evidence export is already outside every removable root and is never in the resource list.
    try:ledger.publish('before-teardown-'+str(ledger.state['sequence']),ledger.public())
    except BaseException as error:ledger.failure(error,'capture');errors.append(error)
    try:ledger.phase('teardown')
    except BaseException as error:ledger.failure(error,'capture');errors.append(error)
    services_gone=True
    for row in reversed(ledger.state['services']):
        if row['unit']=='ubo-m6e-watchdog.timer':continue  # Guard remains armed through incomplete cleanup.
        if inside_prepare and row['unit']=='ubo-m6e-bootstrap.service':continue
        try:host.stop(row)
        except BaseException as error:ledger.failure(error,'cleanup');errors.append(error);services_gone=False
    try:host.no_role_processes()
    except BaseException as error:ledger.failure(error,'cleanup');errors.append(error);services_gone=False
    if services_gone:
        if 'commission' in ledger.state['observations']:
            try:
                from WebsitePlatformM6ECommissionProbes import cleanup_owned
                cleanup_owned(host,ledger)
            except BaseException as error:ledger.failure(error,'cleanup');errors.append(error);services_gone=False
    if services_gone:
        # Only the recorded exclusive producers may create descendants in these owned trees.
        for row in ledger.state['entries']:
            if row['kind']=='tree' and row['state']!='RELEASED' and host.fs.exists(row['path']):
                try:ledger.capture_tree(row,tree_uids(row),final=True)
                except BaseException as error:ledger.failure(error,'cleanup');errors.append(error);services_gone=False
    if services_gone:
        for row in reversed(ledger.state['entries']):
            if row['path'] in controls:continue
            if time.monotonic()>=host.owner.deadline:
                error=Failure('bootstrap_cleanup_deadline');ledger.failure(error,'cleanup');errors.append(error);break
            try:ledger.release(row)
            except BaseException as error:row['state']='UNRESOLVED';ledger.failure(error,'cleanup');errors.append(error)
        resources_removed=all(x['state']=='RELEASED' for x in ledger.state['entries'] if x['path'] not in controls)
        if resources_removed:
            for account in reversed(ledger.state['accounts']):
                try:host.delete_account(account)
                except BaseException as error:ledger.failure(error,'cleanup');errors.append(error)
        if resources_removed and all(x['state']=='RELEASED' for x in ledger.state['accounts']):
            timer=next((x for x in ledger.state['services'] if x['unit']=='ubo-m6e-watchdog.timer'),None)
            try:
                if timer is not None:host.stop(timer)
                ledger.state['watchdog']='DISARMED';ledger.save()
                for row in reversed(ledger.state['entries']):
                    if row['path'] in controls:ledger.release(row)
            except BaseException as error:ledger.failure(error,'cleanup');errors.append(error)
        try:host.systemctl('daemon-reload')
        except BaseException as error:ledger.failure(error,'cleanup');errors.append(error)
    if all(x['state']=='RELEASED' for x in ledger.state['services'] if not(inside_prepare and x['unit']=='ubo-m6e-bootstrap.service')):
        ledger.state['watchdog']='DISARMED';ledger.save()
    if inside_prepare:
        for row in ledger.state['services']:
            if row['unit']=='ubo-m6e-bootstrap.service':row['state']='SELF_EXIT_PENDING'
        ledger.save()
    result=dict(profile=p.PROFILE+'-teardown',binding=p.base_identity(ledger.config),result='LIMITED' if errors else 'REMOVED',
                watchdog=ledger.state['watchdog'],diagnostic=ledger.public())
    try:
        receipt=ledger.publish('teardown-'+str(ledger.state['sequence']),result)
        ledger.state['observations']['teardown_receipt']=receipt;ledger.save()
    except BaseException as error:ledger.failure(error,'capture');errors.append(error)
    ledger.phase('cleanup-limited' if errors else 'removed')
    if errors:raise Failure('bootstrap_cleanup_limited')
    return result

@contextmanager
def lifecycle_lock(host,ledger,create=False):
    import fcntl
    ledger.check_evidence()
    path=ledger.root+'/lifecycle.lock';fd=os.open(host.fs.path(path),os.O_RDWR|(os.O_CREAT if create else 0)|os.O_NOFOLLOW,0o600)
    try:
        s=os.fstat(fd);need(s.st_uid==0 and s.st_nlink==1 and stat.S_ISREG(s.st_mode),'lifecycle_lock')
        while True:
            try:fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB);break
            except BlockingIOError:
                need(time.monotonic()<host.owner.deadline,'lifecycle_lock_deadline');time.sleep(.05)
        yield
    finally:os.close(fd)

def launch(host):
    plan=host.preflight();ledger=Ledger.create(host.fs,plan['config']);host.ledger=ledger
    ledger.state['commands']=host.pre_commands;ledger.save()
    ledger.publish('plan',plan)
    with lifecycle_lock(host,ledger,create=True):
        try:
            for name,raw in p.control_units().items():ledger.write('/run/systemd/system/'+name,raw)
            host.systemctl('daemon-reload');host.start('ubo-m6e-watchdog.timer')
            ledger.state['watchdog']='ARMED';ledger.save()
            ledger.state['services'].append(dict(unit='ubo-m6e-bootstrap.service',state='STARTING',pid=None,invocation=None,cgroup=None));ledger.save()
            host.run('bootstrap-start',['/usr/bin/systemctl','start','--no-block','ubo-m6e-bootstrap.service'])
        except BaseException as error:
            ledger.failure(error,'launch')
            host.owner.deadline=time.monotonic()+p.CLEANUP_SECONDS
            try:teardown(host,ledger)
            except BaseException as cleanup:ledger.failure(cleanup,'cleanup')
            raise
    # The fixed systemd service owns preparation after disconnect. The client only reads its result.
    deadline=time.monotonic()+p.PREPARE_SECONDS+10
    while time.monotonic()<deadline:
        state=Ledger.load(host.fs,plan['config']).state
        if state['phase']=='ready':return decode(host.fs.read(ledger.root+'/bootstrap.json'))
        if state['phase'] in ('teardown','removed','cleanup-limited'):raise Failure('bootstrap_no_longer_prepared')
        if state['first_failure'] is not None:raise Failure('bootstrap_failed')
        time.sleep(.2)
    raise Failure('bootstrap_client_timeout')

def quiesce(host,ledger):
    """Stop preparation before taking its lock, without overwriting its newer ledger.

    The independent command journal is immutable. Only after the preparation unit
    is gone may the cleanup process merge observed outcomes under the lock.
    """
    prior_readonly=getattr(host,'readonly',False);host.readonly=True
    start=len(host.pre_commands);stopped=[];failures=[];tag=str(time.monotonic_ns())
    try:
        units=('ubo-m6e-bootstrap.service','ubo-m6e-commission.service','ubo-m6e-component-a.service')
        try:ledger.publish('quiesce-intent-'+tag,dict(binding=p.base_identity(ledger.config),units=list(units)))
        except BaseException as error:failures.append(('capture',error))
        for name in units:
            try:
                current=Ledger.load(host.fs,ledger.config)
                row=next((x for x in current.state['services'] if x['unit']==name and x['state']!='RELEASED'),None)
                if row is None:continue
                entry=next(x for x in current.state['entries'] if x['path']=='/run/systemd/system/'+name)
                if entry['state']=='RELEASED':continue  # Final handling verifies the recorded self-exit.
                current.verify(entry);unit=host.unit(name)
                need(unit['FragmentPath']==entry['path'],'quiesce_unit')
                if row.get('invocation') and unit['ActiveState'] in ('active','activating'):need(unit['InvocationID']==row['invocation'],'quiesce_replaced')
                host.systemctl('stop',name,20);after=host.unit(name)
                need(after['ActiveState'] in ('inactive','failed') and after.get('MainPID','0')=='0','quiesce_process')
                if name=='ubo-m6e-component-a.service':
                    procs=host.fs.path('/sys/fs/cgroup/system.slice/'+name+'/cgroup.procs')
                    need(not procs.exists() or not procs.read_bytes().strip(),'component_service_children_remain')
                stopped.append(name)
            except BaseException as error:failures.append(('cleanup',error))
        try:ledger.publish('quiesce-result-'+tag,dict(binding=p.base_identity(ledger.config),commands=host.pre_commands[start:],stopped=stopped))
        except BaseException as error:failures.append(('capture',error))
    finally:host.readonly=prior_readonly
    return stopped,host.pre_commands[start:],failures

def supervised_teardown(host,ledger):
    """The public client waits on the fixed cleanup service; disconnect cannot kill it."""
    entry=next((x for x in ledger.state['entries'] if x['path']=='/run/systemd/system/ubo-m6e-watchdog.service'),None)
    if entry is None or entry['state']=='RELEASED':return None  # An already removed fixture is verified locally.
    ledger.verify(entry);tag=str(time.monotonic_ns());start=len(host.pre_commands)
    host.readonly=True;capture_error=None
    try:
        try:ledger.publish('cleanup-client-intent-'+tag,dict(binding=p.base_identity(ledger.config),unit='ubo-m6e-watchdog.service'))
        except BaseException as error:capture_error=error
        host.systemctl('start','ubo-m6e-watchdog.service',p.CLEANUP_SECONDS+10)
    finally:
        try:ledger.publish('cleanup-client-result-'+tag,dict(binding=p.base_identity(ledger.config),commands=host.pre_commands[start:],capture='FAILED' if capture_error else 'PASS'))
        except BaseException as error:
            capture_error=capture_error or error
        finally:host.readonly=False
    if capture_error is not None:raise Failure('cleanup_client_capture_failed')
    current=Ledger.load(host.fs,ledger.config);receipt=current.state['observations']['teardown_receipt']
    raw=host.fs.read(current.root+'/'+receipt['file'])
    need(receipt['sha256']==digest(raw) and receipt['bytes']==len(raw),'cleanup_receipt')
    result=decode(raw);need(result['result']=='REMOVED','cleanup_limited');return result

def main(argv):
    need(argv in (['--plan'],['--prepare-base'],['--prepare-owned'],['--inspect-base'],['--teardown'],['--watchdog']),'bootstrap_usage')
    if argv==['--plan']:
        print(json.dumps(dict(profile=p.PROFILE,entry='--prepare-base',roles=ROLES,units=p.UNITS,
            budgets=dict(prepare=p.PREPARE_SECONDS,cleanup=p.CLEANUP_SECONDS,lifetime=p.LIFETIME_SECONDS),
            terminal=TERMINAL,commissioning='NOT EXECUTED',host_receipt='ABSENT',native_acceptance='NOT EXECUTED')));return 0
    host=NativeHost()
    if argv==['--prepare-base']:result=launch(host)
    else:
        config=host.config();host.namespace(config);ledger=Ledger.load(host.fs,config);host.ledger=ledger
        plan=decode(host.fs.read(ledger.root+'/plan.json'))
        need(plan['config']==config,'plan_admission')
        stopped,commands,failures=[],[],[]
        if argv==['--teardown']:
            host.owner.deadline=time.monotonic()+p.CLEANUP_SECONDS+30
            result=supervised_teardown(host,ledger)
            if result is not None:print(json.dumps(result,sort_keys=True));return 0
        if argv in (['--teardown'],['--watchdog']):
            host.owner.deadline=time.monotonic()+p.CLEANUP_SECONDS
            stopped,commands,failures=quiesce(host,ledger)
        with lifecycle_lock(host,ledger):
            ledger=Ledger.load(host.fs,config);host.ledger=ledger
            if argv==['--prepare-owned']:
                host.preparer_context();result=prepare_owned(host,ledger,plan)
            elif argv==['--inspect-base']:
                host.readonly=True;result=inspect_ready(host,ledger,plan)
            else:
                for row in ledger.state['services']:
                    if row['unit'] in stopped:row['state']='RELEASED'
                ledger.state['commands'].extend(commands)
                for kind,error in failures:ledger.failure(error,kind)
                ledger.save();result=teardown(host,ledger)
                need(not failures,'quiesce_incomplete')
    print(json.dumps(result,sort_keys=True));return 0

if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except (Exception,KeyboardInterrupt):
        print('{"result":"BOOTSTRAP UNRESOLVED","host_receipt":"NOT VERIFIED","native_acceptance":"NOT EXECUTED"}');raise SystemExit(1)
