<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CStoredInputFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeInput.php';

$exit=0;
try {
    $f=m6cStoredFixture();[$request,$claim,$receipt]=$f->run();
    SiteBuildService::completeBuildSuccess($claim['lease'],$receipt);
    [$job]=$f->rows($claim);$release=reset($f->db->tables['site_releases']);
    $canonical=$job['input_manifest_json'];$input=SiteBuildContract::decode($canonical);
    m6c($job['status']==='succeeded'&&$release['build_job_id']===$job['id'],'Real local artifact build, fake-SQL persisted success/release');
    m6c($input['public_facts']['business']['display_name']==='Synthetic Café'
        &&array_column($input['public_composition']['pages'],'slug')===['','roof-cleaning','contact'],'UTF-8 multipage fixture preserved');
    $encodings=['canonical'=>$canonical,'json-column'=>m6cStoredJson($input),
        'reordered-pretty'=>json_encode(array_reverse($input,true),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)];
    $accepted=[];
    foreach($encodings as $name=>$json){
        $variant=array_replace($job,['input_manifest_json'=>$json]);
        m6c(CanonicalJson::encode(SiteBuildContract::decode($json))===$canonical,'Decoded values, scalar types and list order preserved');
        m6c($variant['build_input_hash']===$job['build_input_hash']&&$release['build_input_hash']===$job['build_input_hash'],'Recorded digest and independent release/job binding unchanged');
        $canonicalMatches=CanonicalJson::hash(SiteBuildContract::decode($json))===$job['build_input_hash'];
        $rawMatches=hash('sha256',$json)===$job['build_input_hash'];
        m6c($canonicalMatches,'Canonical identity matches recorded digest');
        m6c($rawMatches===($name==='canonical'),'Noncanonical raw bytes have a different digest');
        $accepted[$name]=M6CNativeInput::matches($variant,$release);
        echo json_encode(['representation'=>$name,'canonical_matches'=>$canonicalMatches,'raw_matches'=>$rawMatches,
            'release_binding'=>true,'native_input_hash'=>$accepted[$name]],JSON_THROW_ON_ERROR)."\n";
    }
    foreach($accepted as $name=>$ok)m6c($ok,'Native input_hash accepts equivalent '.$name);

    foreach(['content','scalar-type','list-order','job-hash','release-hash','malformed','oversized'] as $fault){
        $badJob=$job;$badRelease=$release;$changed=$input;
        if($fault==='content')$changed['public_facts']['business']['display_name']='Altered';
        if($fault==='scalar-type')$changed['ordered_asset_digests'][0]['byte_size']=(string)$changed['ordered_asset_digests'][0]['byte_size'];
        if($fault==='list-order')$changed['public_composition']['pages']=array_reverse($changed['public_composition']['pages']);
        $badJob['input_manifest_json']=m6cStoredJson($changed);
        if($fault==='job-hash')$badJob['build_input_hash']=str_repeat('0',64);
        if($fault==='release-hash'){
            $badRelease['build_input_hash']=str_repeat('0',64);
            m6c(CanonicalJson::hash(SiteBuildContract::decode($badJob['input_manifest_json']))===$badJob['build_input_hash'],
                'Release-binding negative has a correct canonical input/job digest');
        }
        if($fault==='malformed')$badJob['input_manifest_json']='{';
        if($fault==='oversized')$badJob['input_manifest_json']=str_repeat(' ',1048577);
        $invalidJson=in_array($fault,['malformed','oversized'],true);
        try{
            $result=M6CNativeInput::matches($badJob,$badRelease);
            m6c(!$invalidJson&&$result===false,'Native input_hash rejects '.$fault);
        }catch(SiteServiceException $e){m6c($invalidJson&&$e->classification()==='invalid_request','Existing bounded decoder rejects '.$fault);}
        echo "Native input_hash rejected $fault.\n";
    }
    m6c($f->db->tables['site_build_jobs'][$job['id']]===$job&&reset($f->db->tables['site_releases'])===$release,
        'Predicate never rewrites persisted input or either expected digest');
}catch(Throwable $e){
    // Assertion messages are fixed local test labels; no trace, row contents or native output.
    echo 'Native input regression FAILED: '.($e::class===RuntimeException::class?$e->getMessage():get_class($e))."\n";$exit=1;
}finally{
    $roots=WebsitePlatformM6CFixture::$roots;WebsitePlatformM6CFixture::cleanup();
    foreach($roots as $root)m6c(!file_exists($root),'Owned fixture removed');
    echo 'Observed cleanup: '.count($roots)." owned fixture roots removed.\n";
}
if($exit===0)echo "Website platform M6C native input: $assertions assertions passed; fake SQL and modeled serialization, no native coordinator.\n";
exit($exit);
