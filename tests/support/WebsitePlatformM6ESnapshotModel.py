"""Synthetic schema/data for LOCAL controls only. Not a MySQL/schema acceptance oracle."""
import copy
import time
from WebsitePlatformM6EAcceptance import canonical, digest
import WebsitePlatformM6ESnapshot as s
import WebsitePlatformM6EBootstrapPlan as p

def fixture(cp,config,source):
    o=cp['observed'];src=o['source']
    rows={name:[] for name in s.PROFILE['tables']}
    for name,key in (('sites','site'),('site_revisions','revision')):rows[name]=[copy.deepcopy(src[key])]
    for name,key in (('site_releases','release'),('site_build_jobs','job'),('site_build_attempts','producer')):rows[name]=[copy.deepcopy(o[key])]
    rows['site_approvals']=list(copy.deepcopy(o['approvals']).values())
    for approval in rows['site_approvals']:
        if all(r['id']!=approval['revision_id'] for r in rows['site_revisions']):
            rows['site_revisions'].append(dict(src['revision'],id=approval['revision_id'],revision_number=int(src['revision']['revision_number'])-1))
    rows['site_assets']=list({row['id']:{k:v for k,v in row.items() if k!='rights_current'} for row in src['asset_metadata']}.values())
    rows['site_business_associations']=[dict(id=src['association_id'],site_id=src['site']['id'],business_id=src['business_id'],status='active',association_role='customer')]
    rows['businesses']=[dict(id=src['business_id'],status='active',is_suspended=0)]
    # Binary, Unicode, empty string, null and decimal text all survive without reinterpretation.
    rows['notes']=[dict(id=1,body='private-fixture-\x00-Café',amount='123.4500',nullable=None,empty='')]
    schema={};tables={}
    for name,data in rows.items():
        columns=sorted({key for row in data for key in row}) if data else ['id','value']
        fks=[]
        for column,target in (('site_id','sites'),('revision_id','site_revisions'),('source_revision_id','site_revisions'),('build_job_id','site_build_jobs')):
            if column in columns:fks.append(dict(table=target,columns=[column],references=['id']))
        ddl='CREATE TABLE `'+name+'` ('+', '.join('`'+c+'` text' for c in columns)+') ENGINE=InnoDB'
        schema[name]=dict(columns=columns,primary=['id'],foreign_keys=fks,definition=ddl)
        encoded=[[None if row.get(c) is None else str(row[c]).encode().hex().upper() for c in columns] for row in data]
        tables[name]=dict(columns=columns,rows=encoded,row_count=len(encoded),rows_sha256=digest(canonical(encoded)),create_table=ddl)
    baseline=dict(profile='m6e-pre-b-schema-v1',binding=p.base_identity(config),schema=o['database']['schema'],server_uuid=o['database']['server_uuid'],
        migrations={n:v['sha256'] for n,v in source.items() if n.startswith('database/migrations/')},tables=schema)
    captured=dict(profile=s.PROFILE['profile'],execution='controlled-local',binding=p.base_identity(config),
        database=dict(schema=baseline['schema'],server_uuid=baseline['server_uuid'],account='m6e_snapshot@127.0.0.1'),
        checkpoint_sha256=digest(canonical(cp)),schema_sha256=digest(canonical(baseline)),
        reader=dict(connection_id=2,builder_connection_id=1,account='m6e_snapshot@127.0.0.1',version='8.4.model',tls=True,grants_sha256='a'*64),
        transaction=dict(isolation='REPEATABLE READ',read_only=True,consistent_snapshot=True,ddl_guard='GLOBAL READ LOCK',boundaries=1,closed=True),
        creation_point=cp['creation_point'],started_at=cp['captured_at'],captured_at=int(time.time()),expires_at=config['expires_at'],tables=tables,
        deployment_authority='NONE',restore='UNIMPLEMENTED',admission='UNIMPLEMENTED')
    return baseline,captured

def write(host,cp):
    baseline,captured=fixture(cp,host.cfg,host.inventory)
    host.put(s.PATH,canonical(captured),2000,2000)
    record=s.validate(canonical(captured),canonical(baseline),canonical(cp),host.cfg,host.inventory,'controlled-local',int(time.time()))
    identity=host.fs.identity(s.PATH);record['file_identity']={k:identity[k] for k in ('dev','ino')}
    host.put(s.RECORD,canonical(record),2000,2000)
    host.put(s.OUTCOME,canonical(dict(capture='CAPTURED',verification='VERIFIED',transaction_cleanup='RELEASED',first_failure=None,secondary=[])),2000,2000)
