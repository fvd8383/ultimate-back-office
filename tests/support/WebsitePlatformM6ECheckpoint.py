"""Protected pre-B observations only. No database restore or publisher admission API."""
import base64
import stat
import time

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys, integer
import WebsitePlatformM6EBootstrapPlan as p

PATH=p.CONTROL+'/worker/pre-b-source.json'
SOURCE_FILES=tuple('tests/support/'+name for name in ('WebsitePlatformM6ENativeSetup.php','WebsitePlatformM6ECheckpoint.php'))
TABLES=('site_deployment_targets','site_deployments','site_deployment_attempts','site_deployment_approvals','site_deployment_health_checks')

def lifecycle(run):
    return dict(schema='ubo_m6e_lifecycle_'+run,control_root='/run/ubo-m6e-lifecycle/'+run,
        storage_root='/var/lib/ubo-m6e-lifecycle/'+run,worker='m6e_lifecycle_worker',observer='m6e_lifecycle_observer',
        restore='UNIMPLEMENTED',admission='UNIMPLEMENTED')

def validate(raw,config,database,fixture,source,execution,now):
    """Consistency checks, NOT independent SQL proof and never operational authority."""
    need(len(raw)<2097152,'checkpoint_size');v=decode(raw)
    keys(v,('profile','execution','creation_point','binding','started_at','captured_at','expires_at','source_files',
        'observed','observed_sha256','summary','manifest_base64','files','database_snapshot','deployment_authority','lifecycle'))
    need(v['profile']=='m6e-pre-b-source-v1' and v['execution']==execution
        and v['creation_point']=='after-A-completeBuildSuccess-before-B-createRevision','checkpoint_provenance')
    need(v['binding']==p.base_identity(config) and fixture['run_id']==config['run_id'],'checkpoint_binding')
    for name in ('started_at','captured_at','expires_at'):integer(v[name],1)
    need(v['started_at']<=v['captured_at']<=now<v['expires_at']==config['expires_at'],'checkpoint_stale')
    need(v['source_files']=={name:source[name]['sha256'] for name in SOURCE_FILES},'checkpoint_code')
    need(v['database_snapshot']=='UNIMPLEMENTED' and v['deployment_authority']=='NONE'
        and v['lifecycle']==lifecycle(config['run_id']),'checkpoint_not_authority')
    o=v['observed'];keys(o,('database','source','approvals','release','job','producer','absence','newer_revisions'))
    need(digest(canonical(o))==v['observed_sha256'],'checkpoint_observed_bytes')
    need(o['database']==dict(schema='ubo_m6e_'+config['run_id'],server_uuid=database['server_uuid'],account='m6e_worker@127.0.0.1')
        and o['database']['schema']==database['schema'],'checkpoint_schema')
    need(o['absence']==dict.fromkeys(TABLES,0) and o['newer_revisions']==[],'checkpoint_pre_b')
    s=o['source'];keys(s,('site','revision','composition','business_id','association_id','approvals','asset_metadata'))
    site=s['site'];rev=s['revision'];r=o['release'];job=o['job'];producer=o['producer'];summary=v['summary']
    need(summary==fixture['releases'][0],'checkpoint_original_A')
    need(int(site['id'])==fixture['site_id']==int(rev['site_id'])==int(r['site_id'])==int(job['site_id'])==int(producer['site_id'])
        and site['site_key']==fixture['site_key'] and site['lifecycle_status']=='approved'
        and site['current_published_revision_id'] is None,'checkpoint_site')
    need(int(rev['id'])==int(r['source_revision_id'])==int(job['revision_id'])
        and rev['snapshot_hash']==r['source_snapshot_hash']==job['snapshot_hash']
        and rev['lifecycle_status']=='internally_approved' and rev['materiality'] in ('material','non_material')
        and rev['review_ready_at'] is not None and int(rev['revision_number'])>0,'checkpoint_revision')
    integer(s['business_id'],1);integer(s['association_id'],1)
    need(int(job['business_id'])==s['business_id'] and int(job['association_id'])==s['association_id'],'checkpoint_original_association')
    refs=decode(rev['source_references_json'].encode());facts=decode(rev['facts_snapshot_json'].encode())
    need(refs.get('business',{}).get('business_id',facts.get('business_id'))==s['business_id'],'checkpoint_business')
    keys(s['approvals'],('internal_approval_id','customer_approval_id'));keys(o['approvals'],s['approvals'])
    for kind,id_ in s['approvals'].items():
        row=o['approvals'][kind]
        need(int(row['id'])==id_ and int(row['site_id'])==fixture['site_id'] and row['state']=='approved'
            and row['revoked_at'] is None and row['approval_type']==kind.split('_')[0],'checkpoint_approval')
        if kind=='internal_approval_id':need(int(row['revision_id'])==int(rev['id']),'checkpoint_internal_revision')
    need(int(r['id'])==summary['release_id'] and r['release_key']==summary['release_key']
        and int(r['build_job_id'])==int(job['id'])==summary['build_job_id']
        and int(job['current_attempt_id'])==int(producer['id'])==summary['producer_attempt_id']
        and int(producer['build_job_id'])==int(job['id']) and producer['attempt_kind']=='execution'
        and producer['status']==job['status']=='succeeded' and producer['completed_at'] is not None and job['completed_at'] is not None
        and job['builder_code_sha']==config['source_sha'],'checkpoint_build')
    need(r['manifest_hash']==summary['manifest_sha256'] and r['artifact_hash']==summary['artifact_sha256'],'checkpoint_release_hashes')
    raw_manifest=base64.b64decode(v['manifest_base64'],validate=True);m=decode(raw_manifest)
    need(digest(raw_manifest)==summary['manifest_sha256'] and m['files']==v['files']
        and m['site_key']==fixture['site_key'] and m['release_key']==r['release_key']
        and m['artifact_hash']==summary['artifact_sha256']==digest(canonical(v['files']))
        and len(v['files'])==m['file_count']==summary['file_count'],'checkpoint_manifest')
    need(m['contract_version']=='ubo-static-site-v1' and m['schema_version']==1
        and m['source_revision_id']==int(rev['id']) and m['revision_number']==int(rev['revision_number'])
        and m['source_snapshot_hash']==rev['snapshot_hash'] and m['builder_code_sha']==job['builder_code_sha']
        and m['build_input_hash']==job['build_input_hash'] and m['build_profile']==job['build_profile']=='static-review-v1','checkpoint_manifest_source')
    assets=[dict(sha256=f['sha256'],mime_type=f['mime_type'],bytes=f['byte_size']) for f in v['files'] if f['relative_path'].startswith('public/assets/')]
    need(assets==summary['assets'] and len({f['relative_path'] for f in v['files']})==len(v['files']),'checkpoint_assets')
    # Selected source assets must identify the same verified public bytes (CSS is generated).
    for asset in s['asset_metadata']:
        need(int(asset['site_id'])==fixture['site_id'] and (asset['business_id'] is None or int(asset['business_id'])==s['business_id'])
            and dict(sha256=asset['checksum_sha256'],mime_type=asset['mime_type'],bytes=int(asset['byte_size'])) in assets,'checkpoint_source_asset')
    return v

def capture(host,action):
    """Register the fixed worker leaf before execution, including partial-write cleanup."""
    ledger=host.ledger;row=ledger.generated(PATH,'pre-b-source');primary=None
    try:return action()
    except BaseException as error:primary=error;raise
    finally:
        try:
            if host.fs.exists(PATH):
                info=host.fs.identity(PATH)
                need(info['kind']==stat.S_IFREG and info['uid']==info['gid']==2000 and info['mode']==0o600,'checkpoint_file_owner')
                ledger.identify(row)
                host.fs.metadata(PATH,0,0,0o600);ledger.identify(row)
                row['sha256']=digest(host.fs.read(PATH,2097152));ledger.save()
        except BaseException as error:
            if primary is None:raise
            ledger.failure(primary,'prepare');ledger.failure(error,'checkpoint-capture')

def checked(host,ledger,plan,fixture):
    rows=[r for r in ledger.state['entries'] if r['path']==PATH]
    need(len(rows)==1 and rows[0]['state']=='CREATED' and rows[0]['sha256'] is not None,'checkpoint_missing')
    ledger.verify(rows[0]);raw=host.fs.read(PATH,2097152)
    lane='native-pre-b' if getattr(host,'lane','native-bootstrap')=='native-bootstrap' else 'controlled-local'
    value=validate(raw,plan['config'],ledger.state['observations']['database'],fixture,plan['source'],lane,int(time.time()))
    return raw,value

def archive(host,ledger,plan,fixture):
    raw,value=checked(host,ledger,plan,fixture)
    # Publication follows successful completion of the original build command. This
    # archive still needs the complete bootstrap publication and live owned state.
    receipt=ledger.publish('pre-b-source',value)
    need(receipt['sha256']==digest(raw),'checkpoint_canonical_bytes')
    ledger.state['observations']['pre_b_source']=receipt;ledger.save()

def verify(host,ledger,plan,fixture,ready=False):
    if ready:
        need(ledger.state['phase']=='ready' and ledger.state['first_failure'] is None
            and ledger.state['watchdog']=='ARMED','checkpoint_not_ready')
        from WebsitePlatformM6EBootstrap import inspect_record
        record=inspect_record(host,ledger,plan)
    raw,value=checked(host,ledger,plan,fixture)
    receipt=ledger.state['observations'].get('pre_b_source')
    need(receipt==dict(file='pre-b-source.json',bytes=len(raw),sha256=digest(raw)),'checkpoint_not_published')
    need(host.fs.read(ledger.root+'/pre-b-source.json')==raw,'checkpoint_archive_changed')
    if ready:need(record['inspected']['pre_b_source_sha256']==receipt['sha256'],'checkpoint_bootstrap_binding')
    return receipt['sha256']
