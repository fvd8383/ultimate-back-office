"""Real private Apache/TLS observations. No network occurs at import."""
import hashlib
import http.client
import json
import os
from pathlib import Path
import socket
import ssl
import stat
import time

from WebsitePlatformM6EAcceptance import ENDPOINTS, ROLES, canonical, digest, need
from WebsitePlatformM6EDrivers import check
from WebsitePlatformM6EProcess import Failure

SECURITY={'cache-control':'no-store','x-robots-tag':'noindex, nofollow, noarchive','x-content-type-options':'nosniff',
          'referrer-policy':'no-referrer','x-frame-options':'DENY','permissions-policy':'camera=(), microphone=(), geolocation=()'}
CSP="default-src 'none'; script-src 'none'; style-src 'self'; img-src 'self'; connect-src 'none'; object-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
CANARIES=('/private/canary','/metadata/manifest.json','/.m6e-canary','/m6e.php','/m6e.cgi','/m6e.shtml','/empty/','/missing-route','/index')

class Connection(http.client.HTTPSConnection):
    def __init__(self,ip,host,context): super().__init__(host,443,timeout=5,context=context); self.ip=ip
    def connect(self):
        raw=socket.create_connection((self.ip,443),timeout=2)
        try: self.sock=self._context.wrap_socket(raw,server_hostname=self.host)
        except BaseException: raw.close(); raise

def fetch(binding,phase,path,method='GET',variant='none'):
    need(phase in ('active','candidate') and method in ('GET','HEAD','POST','PUT','DELETE','OPTIONS','TRACE'), 'http_request')
    endpoint=binding[phase]
    need([endpoint['host'],endpoint['ip'],endpoint['port']]==ENDPOINTS[phase], 'http_endpoint')
    need(path.startswith('/') and len(path)<=512 and '\n' not in path and '\r' not in path,'http_path')
    need(variant in ('none','unauthorized','bad-host','bad-sni','bad-client','bad-trust'),'tls_variant')
    base=Path('/etc/ubo-site-publisher/tls'); ca=base/('wrong.pem' if variant=='bad-trust' else 'ca.pem')
    context=ssl.create_default_context(cafile=str(ca))
    if variant!='unauthorized':
        cert='wrong' if variant=='bad-client' else 'client'; context.load_cert_chain(str(base/(cert+'.pem')),str(base/(cert+'.key')))
    host='wrong.m6e.invalid' if variant=='bad-sni' else endpoint['host']
    conn=Connection(endpoint['ip'],host,context); started=time.monotonic(); h=hashlib.sha256(); total=0
    try:
        conn.request(method,path,headers={'Host':'wrong.m6e.invalid' if variant=='bad-host' else endpoint['host'],
            'Cache-Control':'no-cache, no-store','Pragma':'no-cache','Accept-Encoding':'identity'})
        response=conn.getresponse(); headers={}; size=0
        for key,value in response.getheaders():
            key=key.lower();size+=len(key)+len(value)
            need(size<=16384 and key not in headers,'header_bound_duplicate');headers[key]=value
        while True:
            part=response.read(65536)
            if not part: break
            total+=len(part);need(total<=20971520 and time.monotonic()-started<6,'http_body_time_bound');h.update(part)
        selected={k:v for k,v in headers.items() if k in set(SECURITY)|{'content-type','content-length','content-security-policy','location','age','x-cache','x-m6e-config','x-powered-by'}}
        return dict(status=response.status,bytes=total,sha256=h.hexdigest(),headers=selected,tls='verified',milliseconds=int((time.monotonic()-started)*1000),pid=os.getpid())
    except (OSError,ssl.SSLError,http.client.HTTPException,TimeoutError):
        return dict(status=0,bytes=total,sha256=h.hexdigest(),headers={},tls='rejected',milliseconds=int((time.monotonic()-started)*1000),pid=os.getpid())
    finally: conn.close()

def sample(role,pid,**data): return dict(role=role,pid=pid,tick=time.monotonic_ns(),data=data)

class ApacheDriver:
    """Mixed into the admitted operator backend; all roots/commands come from its fixed plan."""
    def health(self,phase='candidate',fault='none'):
        result=self.child('worker','WebsitePlatformM6EHealthChild.php',[phase,fault],35)
        need(result['exit']==0,'health_child')
        value=json.loads(result['stdout']);need(value['uid']==ROLES['worker'] and value['lane']=='apache-tls','health_lane')
        return value

    def independent_http(self,path='/',phase='candidate',method='GET',variant='none'):
        return fetch(self.binding,phase,path,method,variant)

    def http_begin(self,name,progress):
        need(name in self.http_names,'http_case')
        context=dict(name=name,files=[],links=[],config=None,stopped=[],canaries=[],requests=0,baseline=None,
                     probe='/',fault_sha256=None)
        self.owned.append(context)  # Before any partial setup, including initial inspection.
        progress.own(context,lambda:self.http_cleanup(context),lambda:self.http_diagnostic(context))
        return context

    def http_diagnostic(self,c):
        return sample('operator',os.getpid(),state_sha256=digest(canonical(self.external_snapshot())),
                      owned_files=len(c['files']),owned_links=len(c['links']),stopped_processes=len(c['stopped']))

    def http_baseline(self,c):
        checker=self.health(); raw=self.independent_http(); c['requests']+=1
        need(checker['evidence']['result']=='pass' and raw['status']==200,'positive_http')
        c['baseline']=self.external_snapshot()
        return sample('worker',checker['pid'],exit=0,state_sha256=digest(canonical(c['baseline'])))

    def change_public(self,c,path,data):
        need(path in self.manifest_paths,'manifest_mutation')
        target=self.public_path('candidate',path); s=target.lstat()
        need(stat.S_ISREG(s.st_mode) and s.st_uid==ROLES['helper'] and s.st_nlink==1,'fixture_file')
        old=target.read_bytes();c['files'].append((target,s,old))
        fd=os.open(target,os.O_WRONLY|os.O_TRUNC|os.O_NOFOLLOW)
        try:
            view=memoryview(data)
            while view:
                n=os.write(fd,view);need(n>0,'fault_write');view=view[n:]
            os.fsync(fd)
        finally: os.close(fd)
        c['probe']='/'+path[7:];c['fault_sha256']=digest(data)

    def http_fault(self,c,name):
        marker='public/_release.json'; home='public/index.html'; changed=0
        if name in ('wrong-site','wrong-release','wrong-marker'):
            data=json.loads(self.public_path('candidate',marker).read_bytes())
            if name=='wrong-marker': data['public_payload_hash']='f'*64
            else: data['site_key' if name=='wrong-site' else 'release_key']='ffffffff-ffff-4fff-8fff-ffffffffffff'
            self.change_public(c,marker,canonical(data));changed+=1
        elif name in ('login-body','generic-200'):
            self.change_public(c,home,b'<html><title>Sign in</title><main>Login</main></html>' if name=='login-body' else b'OK');changed+=1
        elif name in ('css-bytes','media-bytes','size','digest'):
            mime='image/png' if name=='media-bytes' else 'text/css'
            path=next(e['relative_path'] for e in self.manifest['files'] if e['mime_type']==mime)
            old=self.public_path('candidate',path).read_bytes()
            data=old+b'x' if name=='size' else bytes([old[0]^1])+old[1:]
            self.change_public(c,path,data);changed+=1
        elif name in ('mime','redirect','stale-cache'):
            snippets={'mime':'<Location /assets/>\nForceType text/plain\n</Location>\n',
                'redirect':'Redirect 302 / https://active.m6e.invalid/\n','stale-cache':'Header always set Age "100"\nHeader always set X-Cache "HIT"\n'}
            self.http_config_fault(c,snippets[name]);changed+=1
            if name=='mime':c['probe']='/'+next(e['relative_path'][7:] for e in self.manifest['files'] if e['mime_type']=='text/css')
        elif name in ('absence','absence-not-active'):
            c['links'].append((self.site_root/'current',os.readlink(self.site_root/'current')))
            (self.site_root/'current').unlink();changed+=1
        elif name=='timeout':
            c['stopped']=self.apache_stop_barrier();changed=len(c['stopped'])
        elif name in ('bad-trust','bad-host','bad-sni','bad-client'):
            raw=self.independent_http(variant=name);c['requests']+=1
            need(raw['status'] in (0,400,401,403,421),'tls_fault_not_reached');changed=1
        elif name in ('static-handlers','no-fallback-indexes-multiviews','private-dot-executable'):
            public=self.public_path('candidate','public/index.html').parent
            for relative in ('m6e.php','m6e.cgi','m6e.shtml','.m6e-canary'):
                path=public/relative;c['canaries'].append(path)
                fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o440)
                try: os.write(fd,b'M6E-HARMLESS-NOT-EXECUTED');os.fchown(fd,ROLES['helper'],ROLES['apache'])
                finally: os.close(fd)
            path=public/'empty';c['canaries'].append(path);os.mkdir(path,0o550);os.chown(path,ROLES['helper'],ROLES['apache']);changed=len(c['canaries'])
        else:
            # Positive/TLS/method probes reach their boundary through an actual request below.
            raw=self.independent_http();c['requests']+=1;changed=1
        return sample('operator',os.getpid(),boundary=name,count=changed,body_sha256=c['fault_sha256'])

    def http_observe(self,c,name,progress):
        negative=name in ('wrong-site','wrong-release','wrong-marker','login-body','generic-200','css-bytes','media-bytes','mime','size','digest',
            'redirect','bad-trust','bad-host','bad-sni','bad-client','stale-cache','timeout','absence-not-active')
        fault=name if name in ('bad-trust','bad-host','bad-sni','bad-client','absence','absence-not-active') else 'none'
        phase='rollback' if name=='absence' else 'active' if name in ('active-pair','absence-not-active') else 'candidate'
        if name=='restore-retained': self.restore_known_good()
        h=self.health(phase,fault); e=h['evidence']; expected='fail' if negative else 'pass'
        if name=='absence': expected='pass'
        checks=[check('outcome','health',expected)]; details=dict(health=e['result'],status=e['http_status'],duration_ms=e['duration_ms'])
        separation=None
        if name=='active-pair':
            # Retain the first observation even if the second checker call fails.
            progress.note('http-response',sample('worker',h['pid'],**details))
            time.sleep(1);second=self.health('active')['evidence']
            need(second['result']=='pass','second_active');details['pair_count']=2
            separation=self.health_separation(e,second)
        outcome=sample('worker',h['pid'],**details);progress.measured('outcome',outcome)
        progress.attempt('independent')
        raw=self.independent_http(c['probe'],phase='active' if name in ('absence','absence-not-active','active-pair') else 'candidate',
                                  variant=name if name in ('bad-trust','bad-sni','bad-client') else 'none')
        measured=dict(status=raw['status'],bytes=raw['bytes'],body_sha256=raw['sha256'])
        progress.note('http-response',sample('operator',os.getpid(),**measured))
        if name=='mime':
            measured['content_type']=raw['headers'].get('content-type','').split(';')[0]
            checks.append(check('independent','content_type','text/plain'))
        if name=='stale-cache':
            measured['age']=raw['headers'].get('age');measured['cache']=raw['headers'].get('x-cache')
            checks.extend([check('independent','age','100'),check('independent','cache','HIT')])
        if c['fault_sha256'] is not None:
            need(raw['status']==200 and raw['sha256']==c['fault_sha256'],'intended_http_fault_reached')
        if name=='active-pair':
            measured['separation_ms']=separation;checks += [check('outcome','pair_count',2),check('independent','separation_ms',1000,'gte')]
        elif name in ('all-public-files','approved-pdf','restore-retained'):
            entries=self.manifest['files'] if name!='approved-pdf' else [x for x in self.manifest['files'] if x['mime_type']=='application/pdf']
            need(entries,'pdf_required');verified=0
            for entry in entries:
                path='/'+entry['relative_path'][7:];r=self.independent_http(path,phase='active' if name=='restore-retained' else 'candidate')
                need(r['status']==200 and r['sha256']==entry['sha256'] and r['bytes']==entry['byte_size']
                    and r['headers'].get('content-type','').split(';')[0]==entry['mime_type'],'public_http_identity');verified+=1
            measured['files']=verified;checks.append(check('independent','files',len(entries)))
        elif name.startswith('unauthorized-'):
            path='/' if name.endswith('html') else '/_release.json' if name.endswith('marker') else '/'+next(x['relative_path'][7:] for x in self.manifest['files'] if x['mime_type']=='text/css')
            rejected=self.independent_http(path,variant='unauthorized');measured.update(status=rejected['status'],bytes=rejected['bytes'])
            checks.append(check('independent','status',[0,400,401,403],'in'))
        elif name=='get-head':
            head=self.independent_http(method='HEAD');measured.update(head_status=head['status'],head_bytes=head['bytes'])
            checks += [check('independent','head_status',200),check('independent','head_bytes',0)]
        elif name=='methods':
            statuses=[self.independent_http(method=m)['status'] for m in ('POST','PUT','DELETE','OPTIONS','TRACE')]
            measured['denied_methods']=sum(x in (403,405) for x in statuses);checks.append(check('independent','denied_methods',5))
        elif name in ('static-handlers','no-fallback-indexes-multiviews','private-dot-executable'):
            paths=CANARIES[:6] if name!='no-fallback-indexes-multiviews' else CANARIES[6:]
            measured['denied_paths']=sum(self.independent_http(path)['status'] in (403,404) for path in paths)
            checks.append(check('independent','denied_paths',len(paths)))
        elif name=='security-cache-headers':
            count=0
            for path in ('/','/_release.json','/'+next(x['relative_path'][7:] for x in self.manifest['files'] if x['mime_type']=='text/css')):
                r=self.independent_http(path);headers=r['headers']
                need(all(headers.get(k)==v for k,v in SECURITY.items()) and headers.get('content-security-policy')==CSP
                     and 'x-powered-by' not in headers,'served_headers');count+=1
            measured['verified_responses']=count;checks.append(check('independent','verified_responses',3))
        elif name=='binding-separation':
            measured['candidate_sha256']=raw['sha256'];measured['active_sha256']=self.independent_http(phase='active')['sha256']
            checks.append(check('independent','active_sha256',measured['candidate_sha256'],'ne'))
        elif name=='site-scoped-assets':
            foreign=self.foreign_asset_path();r=self.independent_http(foreign)
            measured['foreign_status']=r['status'];checks.append(check('independent','foreign_status',[403,404],'in'))
        elif name=='bad-host':
            r=self.independent_http(variant='bad-host');measured['wrong_host_status']=r['status'];checks.append(check('independent','wrong_host_status',[0,400,403,421],'in'))
        elif name=='timeout': checks.append(check('independent','status',0))
        elif name in ('absence','absence-not-active'): checks.append(check('independent','status',503))
        else:
            statuses=[0,400,401,403] if name in ('bad-trust','bad-sni','bad-client') else [302] if name=='redirect' else [200]
            checks.append(check('independent','status',statuses,'in'))
        independent=sample('operator',os.getpid(),**measured);progress.measured('independent',independent)
        return outcome,independent,checks

    def http_cleanup(self,c):
        primary=None
        try:
            self.apache_resume(c['stopped'])
            for path,s,old in reversed(c['files']):
                now=path.lstat();need((now.st_dev,now.st_ino,now.st_uid)==(s.st_dev,s.st_ino,s.st_uid),'restore_owned_inode')
                fd=os.open(path,os.O_WRONLY|os.O_TRUNC|os.O_NOFOLLOW)
                try:
                    view=memoryview(old)
                    while view:
                        n=os.write(fd,view);need(n>0,'restore_write');view=view[n:]
                    os.fsync(fd)
                finally: os.close(fd)
            for path,target in c['links']: need(not os.path.lexists(path),'restore_link_conflict');os.symlink(target,path)
            for path in reversed(c['canaries']):
                need(path.lstat().st_uid==ROLES['helper'],'canary_owner')
                path.rmdir() if path.is_dir() else path.unlink()
            if c['config'] is not None: self.http_config_restore(c)
            if c['baseline'] is not None: need(self.external_snapshot()==c['baseline'],'fixture_restored')
        except BaseException as error: primary=error;raise
        finally:
            if primary is None:self.owned.remove(c)
        return sample('operator',os.getpid(),remaining=0,exit=0)
