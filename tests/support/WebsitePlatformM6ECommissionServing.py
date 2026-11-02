"""Shared source/serving observations; no fixture mutation or operational admission.

Source inspection is wired into the native probes. ServingChecks requires an
already owned, staged fixture; its lifecycle adapter is deliberately NOT claimed
complete. Local controls exercise it against real transferred immutable bytes and
explicitly modeled HTTPS. No operator PASS values enter these predicates.
"""
import base64
import re

from WebsitePlatformM6EAcceptance import canonical, decode, digest, need, keys, hash_, integer
from WebsitePlatformM6EApache import fetch, SECURITY, CSP, CANARIES
from WebsitePlatformM6EBootstrapPlan import validate_fixture

SOURCE_FIELDS=('source_releases','source_files','source_assets','source_bytes',
               'source_sha256','manifests_sha256','retained_sha256')

def source_measurements(sources):
    """Bounded diagnostics before binding predicates; never copy bodies to evidence."""
    need(type(sources) is list and len(sources)<=4,'serving_source_bound')
    return dict(source_releases=len(sources),source_sha256=digest(canonical(sources)))

def sources(fixture, values):
    validate_fixture(fixture,fixture['run_id'])
    source_measurements(values);need(len(values)==2,'serving_source_count');manifests=[]
    for expected,observed in zip(fixture['releases'],values):
        keys(observed,('release','summary','manifest_base64'))
        need(observed['summary']==expected,'serving_original_producer')
        release=observed['release']
        keys(release,('site_id','site_key','release_id','release_key','artifact_hash','manifest_hash','build_profile'))
        integer(release['site_id'],1);integer(release['release_id'],1)
        need(release==dict(site_id=fixture['site_id'],site_key=fixture['site_key'],release_id=expected['release_id'],
             release_key=expected['release_key'],artifact_hash=expected['artifact_sha256'],
             manifest_hash=expected['manifest_sha256'],build_profile='static-review-v1'),'serving_release_binding')
        text=observed['manifest_base64'];need(type(text) is str and len(text)<=1398104,'serving_manifest_bound')
        raw=base64.b64decode(text,validate=True);need(digest(raw)==expected['manifest_sha256'],'serving_original_manifest')
        m=decode(raw)
        need(m['site_key']==fixture['site_key'] and m['release_key']==release['release_key']
             and m['artifact_hash']==release['artifact_hash'] and m['build_profile']==release['build_profile']
             and m['contract_version']=='ubo-static-site-v1' and type(m['schema_version']) is int and m['schema_version']==1,'serving_manifest_binding')
        files=m['files'];need(type(files) is list and 3<=len(files)<=2000,'serving_files')
        names=[];assets=[]
        for f in files:
            keys(f,('relative_path','sha256','byte_size','mime_type'));hash_(f['sha256']);integer(f['byte_size'],1,20971520)
            name=f['relative_path'];mime=f['mime_type']
            # These are HTTP route constraints, not a replacement artifact validator.
            need(type(name) is str and len(name)<=480,'serving_route_bound')
            if mime=='text/html':need(name=='public/index.html' or re.fullmatch(r'public/(?:[a-z0-9_-]+/)+index.html',name),'serving_html_route')
            elif mime=='application/json':need(name=='public/_release.json','serving_marker_route')
            else:
                ext={'text/css':'css','image/png':'png','image/jpeg':'jpg','image/webp':'webp','application/pdf':'pdf'}
                need(mime in ext and name=='public/assets/'+f['sha256']+'.'+ext[mime],'serving_asset_route')
                assets.append(dict(sha256=f['sha256'],mime_type=mime,bytes=f['byte_size']))
            names.append(name)
        need(names==sorted(set(names)) and 'public/index.html' in names and 'public/_release.json' in names,'serving_inventory')
        integer(m['file_count'],3,2000);integer(m['byte_size'],1,104857600)
        need(assets==expected['assets'] and len(files)==expected['file_count']==m['file_count'],'serving_asset_identity')
        need(digest(canonical(files))==m['artifact_hash'] and sum(f['byte_size'] for f in files)==m['byte_size'],'serving_manifest_files')
        payload=digest(canonical([f for f in files if f['relative_path']!='public/_release.json']))
        marker=canonical(dict(contract_version='ubo-static-site-v1',site_key=fixture['site_key'],release_key=release['release_key'],public_payload_hash=payload))
        entry=next(f for f in files if f['relative_path']=='public/_release.json')
        need(payload==m['public_payload_hash'] and entry['sha256']==digest(marker) and entry['byte_size']==len(marker),'serving_marker_identity')
        manifests.append(m)
    data=source_measurements(values)|dict(source_files=sum(m['file_count'] for m in manifests),
        source_assets=sum(len(r['assets']) for r in fixture['releases']),source_bytes=sum(m['byte_size'] for m in manifests),
        manifests_sha256=digest(canonical([r['manifest_sha256'] for r in fixture['releases']])),
        retained_sha256=digest(canonical({k:fixture[k] for k in ('shared_assets','changed_assets','pdf_assets','foreign_asset')})))
    return manifests,data

def response(value,entry=None,method='GET'):
    """Exact original bytes + strict static response policy. Does not follow redirects."""
    need(type(value) is dict,'serving_response')
    keys(value,('status','bytes','sha256','headers','tls','milliseconds','pid'))
    integer(value['status'],0,599);integer(value['bytes'],0,20971520);hash_(value['sha256'])
    integer(value['milliseconds'],0,6000);integer(value['pid'],1)
    need(value['tls']=='verified' and value['status']==200,'serving_status_tls')
    headers=value['headers'];need(type(headers) is dict and len(headers)<=20,'serving_headers')
    need(all(headers.get(k)==v for k,v in SECURITY.items()) and headers.get('content-security-policy')==CSP,'serving_security')
    need(not any(k in headers for k in ('location','age','x-cache','x-powered-by')),'serving_cache_handler')
    if entry is not None:
        mime=headers.get('content-type');length=headers.get('content-length')
        need(type(mime) is str and mime.split(';')[0].strip()==entry['mime_type'],'serving_mime')
        need(type(length) is str and length==str(entry['byte_size']),'serving_content_length')
        need(value['bytes']==(0 if method=='HEAD' else entry['byte_size'])
             and value['sha256']==(digest(b'') if method=='HEAD' else entry['sha256']),'serving_bytes')

class ServingChecks:
    """Same checks for future native owned fixture and deterministic local transport.

    Every response is captured before its predicate. Only bounded scalar summaries
    leave this class; malformed header values and exception text are never copied.
    Caller must capture diagnostics before restoring its owned aliases.
    """
    def __init__(self,binding,fixture,observed,request=None,note=lambda value:None):
        self.manifests,self.source=sources(fixture,observed);self.fixture=fixture
        self.request=request if request is not None else lambda phase,path,method,variant:fetch(binding,phase,path,method,variant)
        self.note=note;self.observed=dict(requests=0,verified_files=0,verified_heads=0,denials=0,
            response_sha256=digest(b''),last_status=0,last_bytes=0,last_sha256=digest(b''),
            headers_sha256=digest(b''),security_matches=0,tls_verified=0)
    def get(self,phase,path,method='GET',variant='none'):
        value=self.request(phase,path,method,variant)
        need(self.observed['requests']<24000,'serving_request_bound');self.observed['requests']+=1
        safe={k:value.get(k) if type(value) is dict else None for k in ('status','bytes','sha256')}
        # The source-defined diagnostic sanitizer handles invalid scalar values.
        from WebsitePlatformM6ECommissionProbes import NativeProbes
        safe={k:NativeProbes.diagnostic('last_'+k,v) for k,v in safe.items()}
        self.observed.update({'last_'+k:v for k,v in safe.items()})
        headers=value.get('headers') if type(value) is dict else None
        # Header contents are private diagnostic inputs. Fixed matches and their
        # digest survive failure; untrusted response strings never enter records.
        self.observed['headers_sha256']=digest(canonical(headers))
        self.observed['security_matches']=sum(headers.get(k)==v for k,v in (SECURITY|{'content-security-policy':CSP}).items()) if type(headers) is dict else 0
        self.observed['tls_verified']=int(type(value) is dict and value.get('tls')=='verified')
        self.observed['response_sha256']=digest(canonical([self.observed['response_sha256'],phase,digest(path.encode()),method,variant,safe,
            self.observed['headers_sha256'],self.observed['tls_verified']]))
        self.note(dict(self.observed));return value
    def files(self,index,phase,assets_only=False):
        for entry in self.manifests[index]['files']:
            if assets_only and not entry['relative_path'].startswith('public/assets/'):continue
            route='/'+entry['relative_path'][7:]
            for method in ('GET','HEAD'):
                value=self.get(phase,route,method);response(value,entry,method)
                self.observed['verified_heads' if method=='HEAD' else 'verified_files']+=1
                self.note(dict(self.observed))
    def deny(self,phase,path,method='GET',variant='none',statuses=(403,404)):
        value=self.get(phase,path,method,variant)
        need(type(value) is dict and type(value.get('status')) is int and value['status'] in statuses,'serving_denial')
        if value['status']==0:need(value.get('tls')=='rejected','serving_rejected_tls')
        else:need(value.get('tls')=='verified','serving_denial_tls')
        self.observed['denials']+=1;self.note(dict(self.observed))
    def policy(self):
        for phase in ('active','candidate'):
            for method in ('POST','PUT','DELETE','OPTIONS','TRACE'):self.deny(phase,'/',method,statuses=(403,405))
            for path in (*CANARIES,'/%2e%2e/private/canary','/assets/../metadata/manifest.json',
                         '/assets/%2e%2e/metadata/manifest.json','/index.html/extra','/assets/'+'0'*64+'.pdf'):
                self.deny(phase,path,statuses=(400,403,404))
            for variant in ('unauthorized','bad-host','bad-sni','bad-client','bad-trust'):
                self.deny(phase,'/',variant=variant,statuses=(0,) if variant in ('bad-sni','bad-trust') else (0,400,401,403,421))
            asset=next(f for f in self.manifests[0]['files'] if f['relative_path'].startswith('public/assets/'))
            for path in ('/_release.json','/'+asset['relative_path'][7:]):
                self.deny(phase,path,variant='unauthorized',statuses=(0,400,401,403))
            # The bootstrap foreign identity deliberately shares valid same-site
            # bytes. Deny a foreign-qualified route, not that legitimate bare hash.
            self.deny(phase,'/sites/'+str(self.fixture['foreign_asset']['site_id'])+'/assets/'+self.fixture['foreign_asset']['sha256']+'.png')
    def retained(self,publisher):
        """Independent descriptor reads via actual production validators/pool Tree."""
        for expected,manifest in zip(self.fixture['releases'],self.manifests):
            release=dict(site_id=self.fixture['site_id'],site_key=self.fixture['site_key'],release_id=expected['release_id'],
                release_key=expected['release_key'],artifact_hash=expected['artifact_sha256'],manifest_hash=expected['manifest_sha256'],build_profile='static-review-v1')
            need(publisher.verify_served(release)==manifest,'serving_retained_manifest')
            for entry in manifest['files']:
                if not entry['relative_path'].startswith('public/assets/'):continue
                raw=publisher.served.read(entry['relative_path'][7:],entry['byte_size'])
                need(len(raw)==entry['byte_size'] and digest(raw)==entry['sha256'],'serving_retained_pool')
        return self.source['retained_sha256']
