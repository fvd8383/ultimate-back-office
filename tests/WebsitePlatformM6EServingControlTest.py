"""Real two-release PHP/UBT1/storage controls; HTTP, Unix identity and pointers modeled.

No live SQL, native infrastructure, operational receipt or deployment success.
"""
import base64
import copy
from contextlib import contextmanager
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import types
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
sys.path[:0]=[str(ROOT/'tests'),str(ROOT/'tests/support')]
import WebsitePlatformM6ETransferTest as transfer
from WebsitePlatformM6EAcceptance import canonical, digest
from WebsitePlatformM6EApache import SECURITY,CSP
from WebsitePlatformM6ECommissionServing import sources,ServingChecks,response
from WebsitePlatformM6ECommissionProbes import NativeProbes
import WebsitePlatformM6ECommissionEvidence as evidence

p=transfer.p

def component(source,sequence=1):
    """Explicit test-only authority DTO, never obtained from/fabricated in live SQL."""
    release=source['release'];site=release['site_id']
    target=dict(site_id=site,target_id=20,environment='staging',publisher_key='apache-digitalocean-v1',binding_key='m6e-fixture',binding_version=1)
    lease=dict(site_id=site,target_id=20,deployment_id=900,attempt_id=901,attempt_kind='execution',worker_id='fixture-component',
        environment='staging',token='a'*64,fence_epoch=1)
    context={k:v for k,v in lease.items() if k!='token'}|dict(deployment_key='11111111-1111-4111-8111-111111111111',
        producer_attempt_id=901,lease_token=lease['token'],lease_expires_at='2030-01-01 00:00:00',deadline_at='2030-01-01 00:00:00',
        binding_version=1,release_key=release['release_key'],artifact_hash=release['artifact_hash'],operation='publish',phase='stage',
        phase_timeout_seconds=30,connect_timeout_seconds=2,request_timeout_seconds=5,correlation_id='fixture-component')
    r=p.request(p.canonical(dict(version=1,sequence=sequence,operation='stage',target=target,lease=lease,payload=dict(release=release,context=context))))
    binding={k:target[k] for k in ('site_id','target_id','binding_key','binding_version')}|dict(site_key=release['site_key'])
    a=binding|{k:lease[k] for k in ('deployment_id','attempt_id','attempt_kind','fence_epoch')}|dict(deployment_key=context['deployment_key'],
        producer_attempt_id=901,operation='publish',status='running',outcome=None,live=True,permitted=['stage'],activation_fence=None,
        release=release,previous=None,expires_in=30)
    return r,a,binding

class HttpModel:
    """Reads actual independently published files; only transport/headers are modeled."""
    def __init__(self,publisher):self.pub=publisher;self.fault=None;self.history=[]
    def __call__(self,phase,path,method,variant):
        self.history.append((phase,path,method,variant));headers=dict(SECURITY)|{'content-security-policy':CSP}
        status=200;raw=b'';tls='verified'
        if variant!='none':status=403 if variant=='bad-host' else 0;tls='verified' if status else 'rejected'
        elif method not in ('GET','HEAD'):status=405
        else:
            release=self.pub.served.link('current' if phase=='active' else 'candidate')
            if release is None:status=503
            else:
                manifest=p.decode(self.pub.journal.read('manifests/'+release+'.json',p.MAX_MANIFEST))
                route='/index.html' if path=='/' else path
                entry=next((f for f in manifest['files'] if '/'+f['relative_path'][7:]==route),None)
                if route.startswith('/assets/'):
                    # Retained pool may legitimately hold A-only CSS when B is active.
                    for name in self.pub.journal.inventory('manifests')[0]:
                        other=p.decode(self.pub.journal.read(name,p.MAX_MANIFEST))
                        entry=entry or next((f for f in other['files'] if '/'+f['relative_path'][7:]==route),None)
                if entry is None:status=404
                else:
                    actual=route[1:] if route.startswith('/assets/') else 'releases/'+release+'/'+entry['relative_path']
                    raw=self.pub.served.read(actual,entry['byte_size']);headers.update({'content-type':entry['mime_type'],'content-length':str(len(raw))})
        value=dict(status=status,bytes=len(raw) if method!='HEAD' else 0,sha256=digest(raw if method!='HEAD' else b''),
            headers=headers,tls=tls,milliseconds=1,pid=os.getpid())
        if self.fault:self.fault(value)
        return value

class Controls(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        child=subprocess.Popen(['php','-d','display_errors=0','-d','log_errors=0',str(ROOT/'tests/support/WebsitePlatformM6ETransferChild.php'),'two-release'],
            stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,bufsize=0)
        pipe=transfer.Pipe(child);cls.transcripts=[]
        try:
            channel=p.Channel(pipe,time.monotonic()+30);cls.bundle=json.loads(channel.line())
            fixture=cls.bundle['fixture'];values=cls.bundle['sources'];sources(fixture,values)
            r,a,binding=component(values[0])
            with transfer.TransferTests('run').fixture(dict(authority=a,binding=binding)) as (pub,ops,base):
                pub.acquire();original_state=pub.journal.read('state.json',65536)
                for i,slot in enumerate((0,0,1)):
                    r,a,_=component(values[slot],i+1);pub.authority.value=a
                    channel.write(p.canonical(dict(slot=slot))+b'\n');start=len(pipe.received)
                    before={str(f.relative_to(base)):f.stat().st_ino for f in (base/'served').rglob('*') if f.is_file()}
                    pub.stage(a,r,channel)
                    cls.transcripts.append(bytes(pipe.received[start:]))
                    after={str(f.relative_to(base)):f.stat().st_ino for f in (base/'served').rglob('*') if f.is_file()}
                    if i==1:
                        if cls.transcripts[-1] or before!=after:raise AssertionError('replay_wrote_or_transferred')
                    channel.write(p.canonical(dict(observed=True))+b'\n')
                http=HttpModel(pub);checker=ServingChecks({},fixture,values,request=http)
                pub.served.switch(None,values[0]['release']['release_key'])
                checker.files(0,'active');checker.files(1,'candidate');checker.policy();checker.retained(pub)
                pub.served.switch(values[0]['release']['release_key'],values[1]['release']['release_key'])
                pub.served.switch(values[1]['release']['release_key'],values[0]['release']['release_key'],'candidate')
                checker.files(1,'active');checker.files(0,'candidate');checker.files(0,'active',assets_only=True);checker.retained(pub)
                cls.measured=copy.deepcopy(checker.observed)
                # Modeled alias restoration only. Not a native ledger/cleanup proof.
                pub.served.switch(values[1]['release']['release_key'],None)
                pub.served.switch(values[0]['release']['release_key'],None,'candidate')
                if http('active','/','GET','none')['status']!=503 or http('candidate','/','GET','none')['status']!=503:raise AssertionError('restore_absence')
                cls.component_journal_changed=original_state!=pub.journal.read('state.json',65536)
                channel.write(p.canonical(dict(stop=True))+b'\n')
            child.wait(timeout=10);err=child.stderr.read(16385)
            if child.returncode or err:raise AssertionError('two_release_child_failed:'+repr((child.returncode,err)))
        finally:
            pipe.stop.set()
            if child.poll() is None:child.kill();child.wait(timeout=5)
            for stream in (child.stdin,child.stdout,child.stderr):stream.close()
            pipe.thread.join(timeout=2)

    @contextmanager
    def staged(self,slots=(0,1),mutate=None):
        values=self.bundle['sources'];r,a,binding=component(values[0])
        with transfer.TransferTests('run').fixture(dict(authority=a,binding=binding)) as (pub,ops,base):
            pub.acquire()
            for slot in slots:
                self.stage(pub,ops,slot,mutate=mutate)
            yield pub,ops,base

    def stage(self,pub,ops,slot,mutate=None):
        raw=self.transcripts[0 if slot==0 else 2];items=transfer.frames(raw)
        identifier=items[0][1]['transfer_id'];sequence=items[0][1]['sequence']
        if mutate:mutate(items)
        channel=p.Channel(transfer.Memory(transfer.encode(items),fragment=193),time.monotonic()+30)
        r,a,_=component(self.bundle['sources'][slot],sequence);pub.authority.value=a
        with mock.patch.object(ops,'urandom',side_effect=lambda n:bytes.fromhex(identifier) if n==16 else os.urandom(n),create=True):
            pub.stage(a,r,channel)
        return channel

    def test_actual_two_release_transfer_replay_pdf_and_retention(self):
        fixture=self.bundle['fixture'];self.assertEqual(len(fixture['releases']),2)
        for k in ('release_id','release_key','build_job_id','producer_attempt_id','manifest_sha256'):
            self.assertNotEqual(fixture['releases'][0][k],fixture['releases'][1][k])
        self.assertGreaterEqual(len(fixture['shared_assets']),2);self.assertEqual(len(fixture['changed_assets']),2)
        self.assertEqual(len(fixture['pdf_assets']),1);self.assertEqual(self.transcripts[1],b'')
        self.assertGreater(self.measured['verified_files'],28);self.assertEqual(self.measured['verified_files'],self.measured['verified_heads'])
        self.assertGreater(self.measured['denials'],40)
        self.assertTrue(self.component_journal_changed) # Cannot be passed off as pristine native journal preservation.

    def test_original_source_bindings_and_producers(self):
        for target,key in (('release','site_id'),('release','release_key'),('summary','producer_attempt_id')):
            changed=copy.deepcopy(self.bundle['sources']);changed[0][target][key]=999 if isinstance(changed[0][target][key],int) else '0'*36
            with self.subTest(key=key),self.assertRaises(Exception):sources(self.bundle['fixture'],changed)
        changed=copy.deepcopy(self.bundle['sources']);changed[0]['manifest_base64']=base64.b64encode(b'{}').decode()
        with self.assertRaises(Exception):sources(self.bundle['fixture'],changed)

    def test_foreign_source_rejected_before_serving(self):
        foreign=copy.deepcopy(self.bundle['fixture']);foreign['site_id']=foreign['foreign_asset']['site_id']
        with self.assertRaises(Exception):sources(foreign,self.bundle['sources'])

    def test_native_probe_shares_and_rechecks_original_source_identity(self):
        backend=NativeProbes.__new__(NativeProbes);backend.fixture=self.bundle['fixture'];backend.gate='apache'
        backend.partial=dict(data={},progress={});backend.child=lambda *args:dict(sources=copy.deepcopy(self.bundle['sources']))
        first=backend.serving_sources();self.assertEqual(backend.partial['data'],first)
        backend.gate='assets';backend.partial=dict(data={},progress={})
        self.assertEqual(backend.serving_sources(),first)
        bad=copy.deepcopy(self.bundle['sources']);bad[0]['summary']['producer_attempt_id']+=1
        backend.child=lambda *args:dict(sources=bad);backend.partial=dict(data={},progress={})
        with self.assertRaises(Exception):backend.serving_sources()
        self.assertEqual(backend.partial['data']['source_sha256'],digest(canonical(bad)))
        self.assertEqual(backend.partial['progress']['serving-source-binding'],'FAILED')

    def test_child_identity_failure_keeps_obtained_source_hash(self):
        backend=NativeProbes.__new__(NativeProbes);backend.gate='apache';backend.participants=[]
        backend.partial=dict(data={},progress={});backend.ledger=types.SimpleNamespace(state=dict(commands=[]))
        raw=canonical(dict(pid=998,uid=2000,sources=self.bundle['sources']))
        backend.host=types.SimpleNamespace(run=lambda *a,**k:dict(pid=999,exit=0,stdout=raw,stderr=b''))
        with self.assertRaises(Exception):backend.child('worker','--serving-source',True)
        self.assertEqual(backend.partial['data']['source_sha256'],digest(canonical(self.bundle['sources'])))
        self.assertEqual(backend.partial['participants'][0]['pid'],999)
        self.assertEqual(backend.partial['progress']['child-worker-identity'],'FAILED')

    def test_wrong_binding_and_old_authority_producer_reject_before_effects(self):
        for field in ('site_id','site_key','binding_version','producer_attempt_id'):
            with self.subTest(field=field),self.staged(slots=()) as (pub,ops,base):
                r,a,_=component(self.bundle['sources'][0])
                pub.authority.value=copy.deepcopy(a)
                pub.authority.value[field]=a[field]+1 if type(a[field]) is int else 'ffffffff-ffff-4fff-8fff-ffffffffffff'
                with self.assertRaises(p.Denied):pub.stage(a,r,p.Channel(transfer.Memory(b''),time.monotonic()+5))
                for directory in ('releases','assets','staging'):self.assertEqual(list((base/'served'/directory).iterdir()),[])
                self.assertIsNone(pub.served.link('candidate'))

    def test_headers_body_mime_redirect_cache_tls_fail_before_restore(self):
        with self.staged() as (pub,ops,base):
            pub.served.switch(None,self.bundle['sources'][0]['release']['release_key']);http=HttpModel(pub)
            mutations=[lambda v:v.update(status=302),lambda v:v.update(sha256='f'*64),lambda v:v.update(bytes=v['bytes']+1),
                lambda v:v.update(tls='rejected'),lambda v:v['headers'].update({'content-type':'text/plain'}),
                lambda v:v['headers'].update({'location':'https://invalid.test/'}),lambda v:v['headers'].update({'age':'0'}),
                lambda v:v['headers'].update({'x-cache':'MISS'}),lambda v:v['headers'].update({'x-powered-by':'PHP'}),
                lambda v:v['headers'].update({'content-security-policy':CSP+'; script-src *'}),
                lambda v:v['headers'].update({'content-length':False})]
            mutations += [lambda v,k=k:v['headers'].pop(k) for k in SECURITY]
            for change in mutations:
                with self.subTest(change=mutations.index(change)):
                    records=[];http.fault=change;checker=ServingChecks({},self.bundle['fixture'],self.bundle['sources'],request=http,note=records.append)
                    with self.assertRaises(Exception):checker.files(0,'active')
                    self.assertTrue(records);self.assertEqual(records[-1]['requests'],1)
                    self.assertEqual(pub.served.link(),self.bundle['sources'][0]['release']['release_key'])
                    self.assertNotIn('headers',records[-1]);self.assertNotIn('https://',canonical(records).decode())

    def test_wrong_active_candidate_binding(self):
        with self.staged() as (pub,ops,base):
            pub.served.switch(None,self.bundle['sources'][1]['release']['release_key']);http=HttpModel(pub)
            checker=ServingChecks({},self.bundle['fixture'],self.bundle['sources'],request=http)
            with self.assertRaises(Exception):checker.files(0,'active')

    def test_collision_and_corrupt_retained_pool(self):
        with self.staged() as (pub,ops,base):
            checker=ServingChecks({},self.bundle['fixture'],self.bundle['sources'],request=HttpModel(pub))
            entry=next(f for f in checker.manifests[0]['files'] if f['mime_type']=='application/pdf');name=entry['relative_path'][7:]
            raw=pub.served.read(name,entry['byte_size']);inode=(base/'served'/name).stat().st_ino
            with self.assertRaises(p.Denied):pub.served.publish_immutable(name,b'conflict',served=True)
            self.assertEqual(pub.served.read(name,len(raw)),raw);self.assertEqual((base/'served'/name).stat().st_ino,inode)
            (base/'served'/name).write_bytes(b'corrupt')
            with self.assertRaises(Exception):checker.retained(pub)

    def test_incomplete_interrupted_and_malicious_transfer_publish_nothing(self):
        mutations=[lambda items:items.pop(),lambda items:items.__delitem__(slice(3,None)),
            lambda items:items[0][1].update(path='../private/secret'),
            lambda items:items[0][1].update(path='metadata/extra.json'),
            lambda items:items[0][1].update(sha256='f'*64)]
        for mutate in mutations:
            with self.subTest(mutation=mutations.index(mutate)),self.staged(slots=()) as (pub,ops,base):
                with self.assertRaises(Exception):self.stage(pub,ops,0,mutate)
                self.assertIsNone(pub.served.link());self.assertIsNone(pub.served.link('candidate'))
                for directory in ('releases','assets','staging'):self.assertEqual(list((base/'served'/directory).iterdir()),[])

    def test_unlisted_and_linked_retained_object_rejected(self):
        with self.staged() as (pub,ops,base):
            checker=ServingChecks({},self.bundle['fixture'],self.bundle['sources'],request=HttpModel(pub))
            release=base/'served/releases'/self.bundle['sources'][0]['release']['release_key']
            extra=release/'public/unlisted.txt';extra.write_bytes(b'not listed')
            with self.assertRaises(Exception):checker.retained(pub)
            extra.unlink()
            entry=next(f for f in checker.manifests[0]['files'] if f['mime_type']=='application/pdf');leaf=base/'served'/entry['relative_path'][7:]
            other=base/'served/foreign';other.write_bytes(leaf.read_bytes());leaf.unlink();os.link(other,leaf)
            with self.assertRaises(Exception):checker.retained(pub)

    def test_receipt_policy_still_blocks_all_six_incomplete_gates(self):
        self.assertEqual({k for k,v in evidence.MISSING.items() if v},{'apache','assets','runtime','health','recovery','provisioning'})
        for gate in ('apache','assets'):
            self.assertTrue(evidence.MISSING[gate])
        for gate,missing in dict(runtime='supervised-helper-lock-fence',health='candidate-active-fault-restoration',
                recovery='retained-state-interruption-authority-recovery',provisioning='owned-uncertain-reload-reconciliation').items():
            self.assertEqual(evidence.MISSING[gate],(missing,))

if __name__=='__main__':unittest.main()
