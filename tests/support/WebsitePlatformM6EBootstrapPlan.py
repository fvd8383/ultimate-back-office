"""Definitions-only fixed disposable M6E bootstrap policy; never a host receipt."""
import re
from pathlib import PurePosixPath

from WebsitePlatformM6EAcceptance import ROLES, ENDPOINTS, canonical, digest, need, keys, hash_
from WebsitePlatformM6EPreparation import BINARIES, ROOTS as PREPARATION_ROOTS, render

PROFILE='m6e-fixture-bootstrap-v1'
PREPARE_SECONDS=600
CLEANUP_SECONDS=120
LIFETIME_SECONDS=1200
EVIDENCE='/var/lib/ubo-m6e-evidence'
SOURCE='/opt/ubo'
CHECKOUT='/opt/ubo-m6e'
CONTROL='/run/ubo-m6e-fixture'
CONFIG='/etc/ubo-site-publisher'
STORAGE='/var/lib/ubo-m6e-fixture'
LAUNCHER='/usr/local/sbin/ubo-site-publish'
ROOTS=tuple(x.replace('\\','/') for x in PREPARATION_ROOTS)
AUXILIARIES={k:'/usr/'+v for k,v in {
    'getent':'bin/getent','groupadd':'sbin/groupadd','useradd':'sbin/useradd',
    'groupdel':'sbin/groupdel','userdel':'sbin/userdel','detect-virt':'bin/systemd-detect-virt'}.items()}
MODULES=('mpm_event','authz_core','authz_host','authn_core','access_compat','unixd','log_config',
         'ssl','socache_shmcb','headers','mime','dir','rewrite','alias')
UNITS=('ubo-m6e-bootstrap.service','ubo-m6e-commission.service','ubo-m6e-watchdog.timer','ubo-m6e-watchdog.service',
       'ubo-m6e-mysql.service','ubo-m6e-apache.service','ubo-site-authority@.service',
       'ubo-site-authority.socket','ubo-site-publisher@.service','ubo-site-publisher.socket','ubo-site-worker.service')
STARTED=('ubo-m6e-mysql.service','ubo-site-authority.socket','ubo-site-publisher.socket','ubo-m6e-apache.service')
SOCKETS={'ubo-site-authority.socket':('/run/ubo-site-publisher/authority.sock',ROLES['helper'],ROLES['helper'],0o600),
         'ubo-site-publisher.socket':('/run/ubo-site-publisher/publish.sock',0,ROLES['worker'],0o660)}
PRISTINE=dict(version=1,fence=0,owner=None,session=None,phase=0,current=None,intent=None,settled=True)
TEMPLATE_UNITS=('ubo-site-authority@.service','ubo-site-authority.socket','ubo-site-publisher@.service',
                'ubo-site-publisher.socket','ubo-site-worker.service')

def safe_path(path):
    need(type(path) is str and re.fullmatch(r'/[A-Za-z0-9_./@-]+',path) and '..' not in PurePosixPath(path).parts,'base_path')
    return path

def owned_path(path):
    safe_path(path)
    return any(path==r or path.startswith(r+'/') for r in ROOTS) or path==LAUNCHER or path in ['/run/systemd/system/'+u for u in UNITS]

def admission(config):
    keys(config,('version','run_id','source_sha','machine_id','boot_id','tool_sha256','expires_at','auxiliary_sha256','apache_modules'))
    need(config['version']==2,'base_admission_version')
    for k,n in (('run_id',32),('source_sha',40),('machine_id',32)):hash_(config[k],n)
    need(re.fullmatch('[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}',config['boot_id']) is not None,'base_boot')
    keys(config['tool_sha256'],BINARIES);keys(config['auxiliary_sha256'],AUXILIARIES);keys(config['apache_modules'],MODULES)
    for h in [*config['tool_sha256'].values(),*config['auxiliary_sha256'].values()]:hash_(h)
    for name,item in config['apache_modules'].items():
        keys(item,('path','sha256'))
        if item['path']=='builtin':need(item['sha256']==config['tool_sha256']['apache'],'builtin_pin')
        else:
            safe_path(item['path']);need(item['path'] in ('/usr/lib/apache2/modules/mod_'+name+'.so','/usr/lib64/httpd/modules/mod_'+name+'.so'),'module_location')
            hash_(item['sha256'])
    need(type(config['expires_at']) is int,'base_expiry')
    return config

def base_identity(config):return {k:config[k] for k in ('source_sha','run_id','machine_id','boot_id')}

def service(body):
    return ('[Unit]\nDescription=Owned disposable M6E fixture only\n[Service]\n'+body+
            '\nKillMode=control-group\nTimeoutStopSec=10\nSendSIGKILL=yes\nUMask=0077\nNoNewPrivileges=yes\nTasksMax=96\nMemoryMax=512M\n').encode()

def control_units():
    entry=SOURCE+'/tests/support/WebsitePlatformM6EBootstrap.py'
    return {
      'ubo-m6e-bootstrap.service':service('Type=oneshot\nExecStart=/usr/bin/python3 -I -B '+entry+' --prepare-owned\nTimeoutStartSec='+str(PREPARE_SECONDS)),
      'ubo-m6e-commission.service':service('Type=oneshot\nExecStart=/usr/bin/python3 -I -B '+SOURCE+'/tests/support/WebsitePlatformM6ECommission.py --commission-owned\nTimeoutStartSec=420'),
      'ubo-m6e-watchdog.service':service('Type=oneshot\nExecStart=/usr/bin/python3 -I -B '+entry+' --watchdog\nTimeoutStartSec='+str(CLEANUP_SECONDS)),
      'ubo-m6e-watchdog.timer':('[Unit]\nDescription=Owned M6E lifetime guard\n[Timer]\nOnActiveSec='+str(LIFETIME_SECONDS)+
          '\nAccuracySec=1\nUnit=ubo-m6e-watchdog.service\nRemainAfterElapse=no\n').encode()}

def templates(read):
    result={name:read('infrastructure/deployment/'+name+'.example').decode() for name in TEMPLATE_UNITS}
    socket_policy(result)
    result['apache']=read('infrastructure/apache/site-static-staging.conf.example').decode()
    # Resolve every placeholder before mutation, including future DB-selected site identifiers.
    values=template_values('00000000-0000-4000-8000-000000000000')
    for raw in result.values():render(raw,values)
    return result

def socket_policy(units,values=None):
    """Small fixed-template policy, not systemd syntax or installed-version validation.

    systemd 257: Accept=yes selects the matching @.service; Service= is for
    Accept=no. Reject extra/repeated sections/directives and continuation syntax.
    Check canonical placeholders and, separately, actual substituted bytes.
    """
    need(set(units)==set(TEMPLATE_UNITS),'socket_template_names')
    for name in SOCKETS:
        need(name.replace('.socket','@.service') in units,'socket_service_name')
        text=units[name].decode() if isinstance(units[name],bytes) else units[name]
        sections={};section=None
        for line in text.splitlines():
            line=line.strip()
            if not line or line.startswith(('#',';')):continue
            need('\\' not in line,'socket_policy_continuation')
            if line.startswith('['):
                need(line in ('[Unit]','[Socket]') and line not in sections,'socket_policy_section')
                section=line;sections[section]={};continue
            key,separator,value=line.partition('=');key=key.strip();value=value.strip()
            need(separator and section is not None and key not in sections[section],'socket_policy_directive')
            sections[section][key]=value
        need(set(sections)=={'[Unit]','[Socket]'} and set(sections['[Unit]'])=={'Description'},'socket_policy_sections')
        authority=name=='ubo-site-authority.socket'
        expected=dict(ListenStream=SOCKETS[name][0],Accept='yes',
            SocketUser='@PUBLISHER_USER@' if authority else 'root',
            SocketGroup='@PUBLISHER_GROUP@' if authority else '@DEDICATED_WORKER_GROUP@',
            SocketMode='0600' if authority else '0660',DirectoryMode='0755',MaxConnections='4')
        if values is not None:expected={k:render(v,values).decode() for k,v in expected.items()}
        need(sections['[Socket]']==expected,'socket_policy_values')

def template_values(site):
    need(re.fullmatch('[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}',site),'base_site')
    root='/var/www/ubo-sites/staging/sites/'+site;journal='/var/lib/ubo-site-publisher/staging/'+site
    return dict(AUTHORITY_USER='m6e-authority',AUTHORITY_GROUP='m6e-authority',PUBLISHER_USER='m6e-helper',
      PUBLISHER_GROUP='m6e-helper',APACHE_READ_GROUP='m6e-apache',DEDICATED_WORKER_GROUP='m6e-worker',WORKER_USER='m6e-worker',
      IMMUTABLE_CHECKOUT=CHECKOUT,SEALED_M6C_SOURCE_ROOT=STORAGE+'/sealed',VERIFIED_M6C_WORK_ROOT=STORAGE+'/work',
      WORKER_APPLICATION_CONFIG=CHECKOUT+'/private/config/env.php',EXACT_SERVED_SITE_ROOT=root,EXACT_PRIVATE_JOURNAL_ROOT=journal,
      PRIVATE_LISTEN_IP='127.0.0.2',ACTIVE_PRIVATE_HOST='active.m6e.invalid',CANDIDATE_PRIVATE_HOST='candidate.m6e.invalid',SITE_ROOT=root,
      ACTIVE_CERTIFICATE=CONFIG+'/tls/server.pem',ACTIVE_KEY=CONFIG+'/tls/server.key',CANDIDATE_CERTIFICATE=CONFIG+'/tls/server.pem',
      CANDIDATE_KEY=CONFIG+'/tls/server.key',PROBE_CLIENT_CA=CONFIG+'/tls/ca.pem',APPROVED_PROBE_CIDR='127.0.0.0/8',
      PRIVATE_ACCESS_LOG=CONTROL+'/apache/access.log',PRIVATE_ERROR_LOG=CONTROL+'/apache/error.log')

def apache_main(config,raw,site):
    # The reviewed fragment is policy; this supplies the complete independent main configuration.
    head=['ServerRoot "'+CONFIG+'"','ServerName active.m6e.invalid','PidFile '+CONTROL+'/apache/apache.pid',
          'DefaultRuntimeDir '+CONTROL+'/apache','User m6e-apache','Group m6e-apache','Listen 127.0.0.2:443',
          'TypesConfig '+CONFIG+'/mime.types',
          'Listen 127.0.0.3:443','ServerTokens Prod','ServerSignature Off','Timeout 10','KeepAlive Off',
          'StartServers 1','ServerLimit 1','ThreadLimit 8','ThreadsPerChild 8','MaxRequestWorkers 8',
          'SSLSessionCache none','ErrorLog '+CONTROL+'/apache/error.log',
          '<Directory />','AllowOverride None','Require all denied','</Directory>']
    loads=['LoadModule '+name+'_module "'+item['path']+'"' for name,item in config['apache_modules'].items() if item['path']!='builtin']
    body=render(raw,template_values(site)).decode()
    needle='<VirtualHost 127.0.0.2:443>';need(body.count(needle)==2,'vhost_inventory')
    position=body.rfind(needle);body=body[:position]+body[position:].replace(needle,'<VirtualHost 127.0.0.3:443>',1)
    return ('\n'.join(loads+head)+'\n'+body).encode()

def fixture_units(raw,config,site):
    need(set(raw)==set(TEMPLATE_UNITS)|{'apache'},'socket_template_names')
    socket_policy({name:raw[name] for name in TEMPLATE_UNITS})
    values=template_values(site)
    result={name:render(text,values) for name,text in raw.items() if name!='apache'}
    socket_policy(result,values)
    result['ubo-m6e-mysql.service']=service('Type=exec\nUser=m6e-mysql\nGroup=m6e-mysql\nExecStart=/usr/sbin/mysqld --defaults-file='+CONFIG+'/mysql.cnf\nRuntimeMaxSec='+str(LIFETIME_SECONDS))
    result['ubo-m6e-apache.service']=service('Type=exec\nExecStart=/usr/sbin/apache2 -f '+CONFIG+'/apache.conf -DFOREGROUND\nRuntimeMaxSec='+str(LIFETIME_SECONDS))
    return result

def mysql_config():
    return ('''[mysqld]
user=m6e-mysql
datadir=/var/lib/ubo-m6e-fixture/mysql
socket=/var/lib/ubo-m6e-fixture/mysql-run/mysql.sock
pid-file=/var/lib/ubo-m6e-fixture/mysql-run/mysql.pid
bind-address=127.0.0.1
port=3306
mysqlx=0
skip-name-resolve
ssl-ca=/etc/ubo-site-publisher/tls/ca.pem
ssl-cert=/etc/ubo-site-publisher/tls/server.pem
ssl-key=/etc/ubo-site-publisher/tls/server.key
require-secure-transport=ON
log-error=/var/lib/ubo-m6e-fixture/mysql-run/error.log
max-connections=24
performance-schema=ON
innodb-buffer-pool-size=64M
''').encode()

def validate_fixture(value,run_id):
    keys(value,('version','run_id','site_id','site_key','target_id','requester_id','releases','shared_assets','changed_assets','pdf_assets','foreign_asset','unpublished'))
    need(value['version']==1 and value['run_id']==run_id and value['unpublished'] is True,'fixture_binding')
    template_values(value['site_key'])
    for k in ('site_id','target_id','requester_id'):need(type(value[k]) is int and value[k]>0,'fixture_id')
    need(type(value['releases']) is list and len(value['releases'])==2,'two_releases')
    for r in value['releases']:
        keys(r,('release_id','release_key','build_job_id','producer_attempt_id','manifest_sha256','artifact_sha256','file_count','assets'))
        for k in ('release_id','build_job_id','producer_attempt_id','file_count'):need(type(r[k]) is int and r[k]>0,'release_id')
        template_values(r['release_key']);hash_(r['manifest_sha256']);hash_(r['artifact_sha256'])
        need(type(r['assets']) is list and len(r['assets'])<=2000,'asset_count')
        for asset in r['assets']:
            keys(asset,('sha256','mime_type','bytes'));hash_(asset['sha256']);need(type(asset['bytes']) is int and asset['bytes']>0,'asset_bytes')
            need(asset['mime_type'] in ('text/css','image/png','image/jpeg','image/webp','application/pdf'),'asset_type')
    a,b=value['releases']
    for k in ('release_id','release_key','build_job_id','producer_attempt_id','manifest_sha256','artifact_sha256'):need(a[k]!=b[k],'distinct_release')
    left={x['sha256'] for x in a['assets']};right={x['sha256'] for x in b['assets']}
    need(sorted(left&right)==value['shared_assets'] and left&right,'shared_assets')
    need(sorted(left^right)==value['changed_assets'] and left^right,'changed_assets')
    pdf=sorted({x['sha256'] for r in (a,b) for x in r['assets'] if x['mime_type']=='application/pdf'})
    need(pdf and pdf==value['pdf_assets'],'approved_pdf')
    keys(value['foreign_asset'],('site_id','asset_id','sha256'));hash_(value['foreign_asset']['sha256'])
    need(value['foreign_asset']['site_id']!=value['site_id'] and value['foreign_asset']['asset_id']>0,'foreign_site')
    return value

def configurations(fixture,database,application_hash):
    b={k:fixture[k] for k in ('site_id','site_key','target_id')};b.update(binding_key='m6e-fixture',binding_version=1)
    paths=template_values(b['site_key'])
    worker=dict(version=2,transfer_version=1,environment='staging',worker_id='m6e-native-worker',worker_uid=ROLES['worker'],
      publisher_uid=ROLES['helper'],authority_uid=ROLES['authority'],helper_socket=SOCKETS['ubo-site-publisher.socket'][0],
      application_config_sha256=application_hash,artifact_work_root=STORAGE+'/work',artifact_root=STORAGE+'/sealed',
      bindings=[b|{k:dict(zip(('host','ip','port'),ENDPOINTS[k])) for k in ('candidate','active')}|
                dict(ca_file=CONFIG+'/tls/ca.pem',client_certificate=CONFIG+'/tls/client.pem',client_key=CONFIG+'/tls/client.key')])
    helper=dict(version=2,transfer_version=1,environment='staging',publisher_uid=ROLES['helper'],worker_uid=ROLES['worker'],
      authority_uid=ROLES['authority'],apache_gid=ROLES['apache'],bindings=[b|dict(served_root=paths['EXACT_SERVED_SITE_ROOT'],journal_root=paths['EXACT_PRIVATE_JOURNAL_ROOT'])])
    authority=dict(version=2,authority_uid=ROLES['authority'],publisher_uid=ROLES['helper'],
      dsn='mysql:host=127.0.0.1;port=3306;dbname='+database['schema']+';charset=utf8mb4',
      credentials_sha256=database['credentials_sha256'],ca_file=CONFIG+'/tls/ca.pem',bindings=[b])
    return {'worker.json':worker,'helper.json':helper,'authority.json':authority}
