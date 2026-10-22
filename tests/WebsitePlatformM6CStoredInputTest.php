<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CStoredInputFixture.php';

function m6cStoredDeny(callable $call,string $code): void
{
    try{$call();}catch(SiteArtifactException|SiteServiceException $e){
        m6c(($e instanceof SiteArtifactException?$e->failureCode:$e->classification())===$code,'Exact rejection classification');return;
    }
    throw new RuntimeException('Expected stored-input rejection');
}

$exit=0;
try {
    foreach(['canonical','json-column'] as $representation){
        $f=m6cStoredFixture();$request=$f->db->request();
        $canonical=$f->db->tables['site_build_jobs'][$request['id']]['input_manifest_json'];
        if($representation==='json-column'){
            foreach(['input_manifest_json','build_options_json','worker_policy_json'] as $field){
                $before=$f->db->tables['site_build_jobs'][$request['id']][$field];
                $after=m6cStoredJson(SiteBuildContract::decode($before));
                m6c($before!==$after&&CanonicalJson::encode(SiteBuildContract::decode($after))===$before,
                    'Only JSON serialization changes; values, types and list order are preserved');
                $f->db->tables['site_build_jobs'][$request['id']][$field]=$after;
            }
        }
        $claim=$f->db->claim();m6c($claim['claimed'],'Stored input passes claim eligibility');
        [$job,$producer]=$f->rows($claim);$source=$f->source();
        m6c(CanonicalJson::encode(SiteBuildContract::recordedInput($source,$job))===$canonical,'Recorded input contract accepts serialization');
        m6c(hash('sha256',$canonical)===$job['build_input_hash'],'Existing canonical hash retained');
        try{$receipt=SiteBuildService::executeBuild($claim['lease']);}
        catch(SiteArtifactException $original){
            // Private regression evidence: allowlisted code locations only, never raw trace/args/messages.
            $repo=str_replace('\\','/',dirname(__DIR__)).'/';$frames=[];
            $allowed=['private/classes/SiteArtifactFiles.php','private/classes/SiteArtifactValidator.php',
                'private/classes/LocalSiteArtifactStore.php','private/classes/SiteArtifactBuilder.php',
                'private/classes/SiteBuildService.php','tests/support/WebsitePlatformM6CFixture.php',
                'tests/WebsitePlatformM6CStoredInputTest.php'];
            foreach([['file'=>$original->getFile(),'line'=>$original->getLine()],...$original->getTrace()] as $frame){
                $file=str_replace('\\','/',$frame['file']??'');
                if(str_starts_with($file,$repo)&&in_array(substr($file,strlen($repo)),$allowed,true))
                    $frames[]=['file'=>substr($file,strlen($repo)),'line'=>$frame['line']];
                if(count($frames)===8)break;
            }
            $stem=$f->root.'/work/'.$job['job_key'].'/attempt-'.$producer['id'];
            $record=['representation'=>$representation,'failure'=>$original->failureCode,'origin'=>$frames,
                'candidate_files'=>is_dir($stem)?count(SiteArtifactFiles::inventory($stem)):0,
                'seal_proof'=>file_exists($stem.'.seal.json'),'release'=>file_exists($f->releasePath($job)),
                'raw_hash_matches'=>hash('sha256',$job['input_manifest_json'])===$job['build_input_hash'],
                'canonical_hash_matches'=>CanonicalJson::hash(SiteBuildContract::decode($job['input_manifest_json']))===$job['build_input_hash']];
            try{$done=SiteBuildService::completeBuildFailure($claim['lease'],['code'=>$original->failureCode,'receipt_key'=>$job['release_key']]);
                $record['failure_completion']=$done['job']['status'];$record['completion_exception']=false;
            }catch(Throwable){$record['completion_exception']=true;}
            echo json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
            throw $original;
        }
        $e=$f->store->verifyRelease($job,$producer);m6c($e['disposition']==='sealed','Real build seals and verifies');
        $done=SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
        m6c($done['job']['status']==='succeeded','Completion verifies stored JSON');
        $path=$f->releasePath($job);$inventory=SiteArtifactFiles::inventory($path);$digests=[];
        m6c(count($inventory)===8,'Exact eight-file multipage inventory with deduplicated image');
        foreach($inventory as $file){$bytes=file_get_contents($path.'/'.$file);$digests[$file]=hash('sha256',$bytes);
            m6c(!str_contains($bytes,'CANARY')&&!str_contains($bytes,'/uploads/'),'No private facts or source locator');}
        foreach(['index.html','roof-cleaning/index.html','contact/index.html'] as $html){
            $bytes=file_get_contents($path.'/public/'.$html);
            m6c(str_contains($bytes,'Synthetic Café')&&str_contains($bytes,'rel="canonical"'),'UTF-8 and self-canonical links');
            foreach(['/','/roof-cleaning','/contact'] as $url)m6c(str_contains($bytes,'href="'.$url.'"'),'Full navigation');
        }
        $contact=file_get_contents($path.'/public/contact/index.html');
        m6c(str_contains($contact,'name="email" required disabled')&&!str_contains($contact,'<form'),'Inert lead fields remain');
        $identity=['site_key'=>$source['site']['site_key'],'release_key'=>$job['release_key'],'revision_number'=>(int)$source['revision']['revision_number']];
        $input=SiteBuildContract::decode($canonical);
        $encodings=$representation==='canonical'?[$canonical]:[$canonical,m6cStoredJson($input),json_encode($input,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)];
        foreach($encodings as $json){
            $variant=array_replace($job,['input_manifest_json'=>$json]);
            $manifest=SiteArtifactValidator::verify($path,$variant,$identity,static fn()=>null);
            m6c(CanonicalJson::hash($manifest)===$e['manifest_hash'],'Same manifest for equivalent JSON encodings');
        }
        foreach(['content','scalar-type','list-order','hash','malformed','oversized'] as $fault){
            $bad=$job;$changed=$input;
            if($fault==='content')$changed['public_facts']['business']['display_name']='Altered';
            if($fault==='scalar-type')$changed['ordered_asset_digests'][0]['byte_size']=(string)$changed['ordered_asset_digests'][0]['byte_size'];
            if($fault==='list-order')$changed['public_composition']['pages']=array_reverse($changed['public_composition']['pages']);
            $bad['input_manifest_json']=m6cStoredJson($changed);
            if($fault==='hash')$bad['build_input_hash']=str_repeat('0',64);
            if($fault==='malformed')$bad['input_manifest_json']='{';
            if($fault==='oversized')$bad['input_manifest_json']=str_repeat(' ',1048577);
            m6cStoredDeny(fn()=>SiteArtifactValidator::verify($path,$bad,$identity,static fn()=>null),
                in_array($fault,['malformed','oversized'],true)?'invalid_request':'artifact_invalid');
        }
        SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
        $replay=$f->db->request();
        m6c($replay['existing']&&$f->builds===1&&$f->db->events('site_build_succeeded')===1
            &&count($f->db->tables['site_releases'])===1&&count($f->db->tables['site_release_validations'])===2,'Replay creates no duplicate build/release/validation/event');
        foreach($digests as $file=>$digest)m6c(hash_file('sha256',$path.'/'.$file)===$digest,'Verification and replay preserve immutable bytes');
        echo "Stored input $representation: accepted, sealed, verified, completed and replayed.\n";
    }
} catch(Throwable $failure) {
    echo 'Stored input regression FAILED: '.($failure instanceof SiteArtifactException?$failure->failureCode:get_class($failure))."\n";
    $repo=str_replace('\\','/',dirname(__DIR__)).'/';
    foreach([['file'=>$failure->getFile(),'line'=>$failure->getLine()],...$failure->getTrace()] as $frame){
        $file=str_replace('\\','/',$frame['file']??'');
        if(str_starts_with($file,$repo)&&preg_match('~^(?:private/classes/[A-Za-z0-9]+|tests/(?:support/)?WebsitePlatformM6[A-Za-z0-9]+)\.php$~D',substr($file,strlen($repo))))
            echo substr($file,strlen($repo)).':'.$frame['line']."\n";
    }
    $exit=1;
} finally {
    $roots=WebsitePlatformM6CFixture::$roots;WebsitePlatformM6CFixture::cleanup();
    foreach($roots as $root)m6c(!file_exists($root),'Owned private fixture removed');
    echo 'Observed cleanup: '.count($roots)." owned fixture roots removed.\n";
}
if($exit===0)echo "Website platform M6C stored input: $assertions assertions passed.\n";
exit($exit);
