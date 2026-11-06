"""Protected no-replace host-receipt publication, separate from evidence authorization.

All operational paths are fixed. Desktop controls use a private mapped Files object;
they exercise publication failures, not native admission or a usable capability.
"""
import os
from pathlib import PurePosixPath
import stat

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys
from WebsitePlatformM6EBootstrapPlan import CONFIG
from WebsitePlatformM6EProcess import Failure

RECEIPT=CONFIG+'/host-receipt.json'
PENDING=CONFIG+'/commission-receipt.pending'
REQUIRED=(
    '/opt/ubo-m6e/scripts/site-deployment-worker.php', '/opt/ubo-m6e/scripts/reconcile-site-jobs.php',
    '/opt/ubo-m6e/private/config/env.php', CONFIG+'/application.php', CONFIG+'/worker.json',
    CONFIG+'/helper.json', CONFIG+'/authority.json', CONFIG+'/apache.conf',
    '/usr/local/sbin/ubo-site-publish', '/usr/local/lib/ubo-site-publisher/ubo_site_publish.py',
    '/usr/local/lib/ubo-site-publisher/ubo-site-authority.php',
    '/run/systemd/system/ubo-site-worker.service',
    '/run/systemd/system/ubo-site-publisher.socket', '/run/systemd/system/ubo-site-authority.socket',
)
WORKER_SUPPORT=tuple('/opt/ubo-m6e/tests/support/WebsitePlatformM6E'+name for name in
    ('CommissionServing.php','NativeSetup.php','Component.php'))

def protected_file(files,path,limit=2097152,mode=None):
    for parent in reversed(PurePosixPath(path).parents):
        # The mapped root is inspected too; native '/' is never assumed safe.
        item=files.identity(str(parent))
        need(item['kind']==stat.S_IFDIR and item['uid']==0 and not item['mode']&0o022, 'receipt_ancestor')
    item=files.identity(path); shape=files.path(path).lstat()
    need(item['kind']==stat.S_IFREG and item['uid']==0 and not item['mode']&0o022
         and shape.st_nlink==1 and (mode is None or item['mode']==mode), 'receipt_file_shape')
    raw=files.read(path,limit)
    need(files.identity(path)==item,'receipt_file_changed')
    return raw,item

def installed_inventory(files,loaded):
    """Loaded PHP dependencies come from actual isolated PHP include observations.

    Only the two runtime trees and fixed commissioning verifier are allowed. Never
    hash the entire repository or accept a caller's subset as sufficient.
    """
    keys(loaded,('worker','authority'))
    result=set(REQUIRED)
    verifier='/opt/ubo-m6e/tests/support/WebsitePlatformM6ECommissionChild.php'
    for role,paths in loaded.items():
        need(type(paths) is list and paths and len(paths)==len(set(paths)), 'included_inventory')
        prefix='/opt/ubo-m6e/private/classes/' if role=='worker' else '/usr/local/lib/ubo-site-publisher/php/'
        must={prefix+'SitePublisherHostContract.php',prefix+'SiteDeploymentContract.php'}
        must.add(prefix+('SiteDeploymentService.php' if role=='worker' else 'SitePublisherAuthority.php'))
        need(must<=set(paths), 'included_required')
        for name in paths:
            need(name==verifier or (role=='worker' and name in WORKER_SUPPORT)
                 or name in (CONFIG+'/application.php','/opt/ubo-m6e/private/config/env.php')
                 or (name.startswith(prefix) and name.endswith('.php') and
                     all(part and part not in ('.','..') and part.replace('_','').replace('-','').replace('.','').isalnum()
                         for part in name[len(prefix):].split('/'))), 'included_path')
            result.add(name)
    need(verifier in result and len(result)<=256, 'installed_inventory_bound')
    return {name:digest(protected_file(files,name)[0]) for name in sorted(result)}

def verify_inventory(files,installed,loaded):
    need(installed_inventory(files,loaded)==installed, 'installed_changed_or_omitted')

def rename_new(files,source,destination):
    # Windows controls use real no-replace rename in a private directory. Linux
    # uses the same renameat2 primitive as the production immutable publisher.
    if os.name=='nt': os.rename(files.path(source),files.path(destination)); return
    import ctypes
    root=files.path(CONFIG)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        libc=ctypes.CDLL(None,use_errno=True);rename=libc.renameat2
        rename.argtypes=[ctypes.c_int,ctypes.c_char_p,ctypes.c_int,ctypes.c_char_p,ctypes.c_uint]
        rename.restype=ctypes.c_int
        if rename(fd,PurePosixPath(source).name.encode(),fd,PurePosixPath(destination).name.encode(),1):
            raise OSError(ctypes.get_errno(),'receipt_exclusive_rename')
    finally:os.close(fd)

def reconcile(files,ledger,expected_binding,now):
    """Read-only exact-issuance reconciliation. Does not refresh expiry or durability."""
    intent=decode(files.read(ledger.root+'/commission-issuance-intent.json',65536))
    keys(intent,('binding','receipt_sha256','receipt_bytes','identity','expires_at'))
    need(intent['binding']==expected_binding and now<intent['expires_at'],'issuance_binding_or_expiry')
    raw,item=protected_file(files,RECEIPT,65536,0o644)
    need(item==intent['identity'] and digest(raw)==intent['receipt_sha256'] and len(raw)==intent['receipt_bytes'], 'issuance_identity')
    value=decode(raw)
    need(value['expires_at']==intent['expires_at'] and value['verified_at']<=now<value['expires_at'], 'issuance_expired')
    return dict(result='EXACT RECEIPT PRESENT',sha256=digest(raw),bytes=len(raw),durability='NOT REESTABLISHED')

def publish(files,ledger,raw,binding,acknowledge,rename=rename_new,recheck=lambda:None):
    """Internal primitive: caller MUST validate all native evidence before calling.

    The immutable intent binds the complete candidate and its exclusive inode before
    publication. A raised rename/sync/ack is uncertain; the final object is never
    removed. Only a separately called read-only reconcile may confirm its identity.
    """
    need(len(raw)<=65536 and not files.exists(RECEIPT) and not files.exists(PENDING),'receipt_preexisting')
    parent=files.identity(CONFIG)
    need(parent['kind']==stat.S_IFDIR and parent['uid']==0 and not parent['mode']&0o022,'receipt_parent')
    candidate=decode(raw)
    need(candidate['expires_at']<=binding['expires_at'],'receipt_lifetime')
    pending=ledger.generated(PENDING,'commission-receipt')
    final=ledger.generated(RECEIPT,'commission-receipt')
    created=None;attempted=False;primary=None
    try:
        files.create(PENDING,raw,lambda fd:ledger.identify(pending,fd))
        files.metadata(PENDING,0,0,0o644);ledger.identify(pending)
        actual,created=protected_file(files,PENDING,65536,0o644)
        need(actual==raw and files.identity(CONFIG)==parent,'receipt_candidate')
        # chmod is part of the candidate, and must be synchronized before rename.
        fd=os.open(files.path(PENDING),os.O_RDWR|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_BINARY',0))
        try:os.fsync(fd)
        finally:os.close(fd)
        pending['sha256']=digest(raw);ledger.save()
        ledger.publish('commission-issuance-intent',dict(binding=binding,receipt_sha256=digest(raw),
            receipt_bytes=len(raw),identity=created,expires_at=candidate['expires_at']))
        recheck()  # Last source/configuration/tool/time check, after candidate effects.
        need(files.identity(CONFIG)==parent and protected_file(files,PENDING,65536,0o644)[0]==raw,'receipt_pre_rename')
        attempted=True;rename(files,PENDING,RECEIPT)
        final.update(identity=created,state='CREATED',sha256=digest(raw))
        pending['state']='RELEASED';ledger.save()
        files.sync_dir(CONFIG)
        checked,item=protected_file(files,RECEIPT,65536,0o644)
        need(checked==raw and item==created,'receipt_acknowledgement')
        acknowledge(raw)  # Actual consumer validators; no normal worker activation.
        ledger.publish('commission-issuance-ack',dict(binding=binding,sha256=digest(raw),bytes=len(raw)))
        return dict(result='RECEIPT ISSUED',sha256=digest(raw),bytes=len(raw))
    except BaseException as error:
        primary=error;ledger.failure(error,'commission-issuance')
        # A post-effect save failure is not permission to remove a final receipt.
        if attempted:
            ledger.state['observations']['commission_issuance']='UNCERTAIN'
            try:
                if files.exists(RECEIPT):
                    checked,item=protected_file(files,RECEIPT,65536,0o644)
                    if item==created and checked==raw:final.update(identity=item,state='CREATED',sha256=digest(raw))
                if not files.exists(PENDING):pending['state']='RELEASED'
                ledger.save()
            except BaseException as secondary:ledger.failure(secondary,'commission-reconcile')
            raise Failure('receipt_publication_uncertain') from error
        raise
    finally:
        # On pre-rename failures only, remove the descriptor-bound pending object.
        # No final-path deletion, replacement or retry exists here.
        if not attempted:
            try:
                if pending['identity'] is not None:ledger.release(pending)
                if not files.exists(RECEIPT):final['state']='RELEASED';ledger.save()
            except BaseException as error:
                ledger.failure(error,'commission-cleanup')
                if primary is None:raise
