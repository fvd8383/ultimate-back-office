"""AUTHORED ONLY. Fixed disposable-VM preparation; no import-time operations.

The VM image must already contain the pinned tools. This is not an image builder,
package installer, host provisioner or production admission override.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys
import time

if __name__=='__main__':sys.path.insert(0,str(Path(__file__).resolve().parent))

from WebsitePlatformM6EAcceptance import ROLES, ENDPOINTS, canonical, digest, need, keys, hash_
from WebsitePlatformM6EProcess import Owner, Failure, publish_bytes

SOURCE=Path('/opt/ubo')
CHECKOUT=Path('/opt/ubo-m6e')
CONTROL=Path('/run/ubo-m6e-fixture')
STORAGE=Path('/var/lib/ubo-m6e-fixture')
CONFIG=Path('/etc/ubo-site-publisher')
BOOTSTRAP=Path('/run/ubo-m6e-bootstrap.json')
BINARIES={'php':'/usr/bin/php','python':'/usr/bin/python3','apache':'/usr/sbin/apache2','mysql':'/usr/sbin/mysqld',
          'openssl':'/usr/bin/openssl','systemd':'/usr/bin/systemctl','git':'/usr/bin/git'}
VERSIONS={'php':['-v'],'python':['--version'],'apache':['-v'],'mysql':['--version'],
          'openssl':['version'],'systemd':['--version'],'git':['--version']}
ROOTS=(str(CONTROL),str(STORAGE),str(CONFIG),str(CHECKOUT),'/var/www/ubo-sites','/var/lib/ubo-site-publisher',
       '/usr/local/lib/ubo-site-publisher','/run/ubo-site-publisher')
UNITS=('ubo-m6e-mysql.service','ubo-m6e-apache.service','ubo-site-authority@.service','ubo-site-authority.socket',
       'ubo-site-publisher@.service','ubo-site-publisher.socket','ubo-site-worker.service','ubo-m6e-operator.service')

def protected(path,limit=65536):
    path=Path(path)
    for item in (path,*path.parents):
        s=item.lstat()
        need(s.st_uid==0 and not s.st_mode&0o022 and not stat.S_ISLNK(s.st_mode),'protected_ancestor')
    s=path.lstat(); need(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_size<=limit,'protected_file')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW)
    try:
        before=os.fstat(fd); raw=os.read(fd,limit+1); after=os.fstat(fd)
        stable=lambda x:(x.st_dev,x.st_ino,x.st_mode,x.st_uid,x.st_gid,x.st_nlink,x.st_size,x.st_mtime_ns,x.st_ctime_ns)
        need((before.st_dev,before.st_ino)==(s.st_dev,s.st_ino) and stable(before)==stable(after) and len(raw)==s.st_size,'protected_identity')
        return raw
    finally: os.close(fd)

def admit_facts(config,facts):
    """Pure preflight policy also used by desktop control tests; no boolean attestations."""
    keys(config,('version','run_id','source_sha','machine_id','boot_id','tool_sha256','expires_at'))
    need(config['version']==1,'bootstrap_version'); hash_(config['run_id'],32); hash_(config['source_sha'],40)
    hash_(config['machine_id'],32)
    need(re.fullmatch('[a-f0-9-]{36}',config['boot_id']) is not None,'boot_id')
    keys(config['tool_sha256'],BINARIES)
    for value in config['tool_sha256'].values(): hash_(value)
    need(facts['platform']=='linux' and facts['uid']==0 and facts['container']=='none','vm_root_only')
    need(facts['virtualization'] in ('kvm','qemu','vmware','microsoft','oracle','xen'),'dedicated_vm')
    need(facts['hostname']=='ubo-m6e-'+config['run_id'][:16] and facts['machine_id']==config['machine_id']
         and facts['boot_id']==config['boot_id'] and ('ubo.m6e='+config['run_id']) in facts['cmdline'].split(),'vm_identity')
    need(facts['interfaces']==['lo'] and facts['routes']==[] and facts['pid1']=='systemd','private_network_supervisor')
    need(facts['filesystems'] and all(x in ('ext4','xfs','btrfs') for x in facts['filesystems']),'local_filesystem')
    need(facts['source_sha']==config['source_sha'] and facts['source_status']=='' and facts['source_readonly'],'source')
    need(facts['tools']==config['tool_sha256'] and facts['now']<config['expires_at']<=facts['now']+3600,'tools_expiry')
    need(not facts['docker_socket'] and facts['existing_roots']==[] and facts['existing_units']==[],'foreign_resources')
    return config

def read_preflight(owner,config=None,inventory_roots=ROOTS,inventory_units=UNITS):
    need(sys.platform=='linux' and os.geteuid()==0,'linux_vm_required')
    config=json.loads(protected(BOOTSTRAP)) if config is None else config
    def run(cmd,allowed=(0,)):
        r=owner.capture(cmd,10,limit=65536)
        need(r['exit'] in allowed and not r['stderr'],'preflight_command')
        return r['stdout'].decode().strip()
    mounts=Path('/proc/self/mountinfo').read_text().splitlines()
    def mount(path):
        choices=[line.split() for line in mounts if path==line.split()[4] or path.startswith(line.split()[4].rstrip('/')+'/')]
        need(choices,'mount'); row=max(choices,key=lambda x:len(x[4])); return row[row.index('-')+1],row[5].split(',')
    import socket
    facts=dict(platform=sys.platform,uid=os.geteuid(),container=run(['/usr/bin/systemd-detect-virt','--container'],(0,1)),
        virtualization=run(['/usr/bin/systemd-detect-virt','--vm'],(0,1)),hostname=socket.gethostname(),
        machine_id=Path('/etc/machine-id').read_text().strip(),boot_id=Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
        cmdline=Path('/proc/cmdline').read_text(),interfaces=sorted(p.name for p in Path('/sys/class/net').iterdir()),
        routes=[line for line in Path('/proc/net/route').read_text().splitlines()[1:] if line.strip()],
        pid1=Path('/proc/1/comm').read_text().strip(),filesystems=[mount(x)[0] for x in ('/var/lib','/var/www')],
        source_sha=run(['/usr/bin/git','-C',str(SOURCE),'rev-parse','HEAD']),
        source_status=run(['/usr/bin/git','-C',str(SOURCE),'status','--porcelain','--untracked-files=all']),
        source_readonly='ro' in mount(str(SOURCE))[1],
        tools={key:digest(protected(Path(path).resolve(strict=True),128*1024*1024)) for key,path in BINARIES.items()},now=int(time.time()),
        docker_socket=Path('/var/run/docker.sock').exists(),existing_roots=[x for x in inventory_roots if os.path.lexists(x)],
        existing_units=[str(Path(root)/x) for root in ('/run/systemd/system','/etc/systemd/system','/usr/lib/systemd/system','/lib/systemd/system')
                        for x in inventory_units if os.path.lexists(Path(root)/x) or os.path.lexists(Path(root)/(x+'.d'))])
    admit_facts(config,facts)
    versions={key:dict(sha256=facts['tools'][key],version=run([path,*VERSIONS[key]])[:256]) for key,path in BINARIES.items()}
    need('8.4.' in versions['mysql']['version'],'mysql_84')
    return config,versions

def render(text,values):
    names=set(re.findall(r'@([A-Z0-9_]+)@',text))
    need(names<=set(values),'template_values')
    for name in names:
        value=values[name]; need(type(value) is str and '\n' not in value and '\r' not in value and '"' not in value,'template_value')
        text=text.replace('@'+name+'@',value)
    need(not re.search(r'@[A-Z0-9_]+@',text),'template_unresolved'); return text.encode()

def prepare():
    # The legacy full-integration command retains its reviewed readiness barrier.
    from WebsitePlatformM6ECoordinator import require_native_ready
    require_native_ready()
    raise Failure('full_native_preparation_unimplemented')


def main(argv):
    if argv==['--prepare']:return prepare()
    if argv in (['--commission'],['--inspect-commissioning']):
        from WebsitePlatformM6ECommission import main as commission_main
        return commission_main(argv)
    if argv==['--component-a']:
        from WebsitePlatformM6EComponentEntry import main as component_main
        return component_main(argv)
    need(argv in (['--plan'],['--prepare-base'],['--inspect-base'],['--teardown']),'usage')
    from WebsitePlatformM6EBootstrap import main as bootstrap_main
    return bootstrap_main(argv)


if __name__=='__main__':
    try:raise SystemExit(main(sys.argv[1:]))
    except (Exception,KeyboardInterrupt):
        print('{"result":"BLOCKED","host_receipt":"NOT VERIFIED","native_acceptance":"NOT EXECUTED"}')
        raise SystemExit(1)
