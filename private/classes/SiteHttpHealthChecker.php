<?php
declare(strict_types=1);
require_once __DIR__.'/SiteArtifactValidator.php';
require_once __DIR__.'/SiteDeploymentContract.php';

/** One observation per call. M6D owns the separated pair; there is no nested retry loop. */
final class SiteHttpHealthChecker
{
    private array $deadlines=[];
    private function __construct(private array $binding,private Closure $transport,private Closure $clock) {}
    public static function forBinding(array $binding): self
    {
        $wall=microtime(true);$tick=hrtime(true);
        return new self($binding,Closure::fromCallable([self::class,'https']),static fn():float=>$wall+(hrtime(true)-$tick)/1000000000);
    }
    public function check(array $expected,array $target,array $context,array $origin): array
    {
        SiteDeploymentService::guardExternal();
        $start=($this->clock)();$key=$context['attempt_id'].':'.$context['fence_epoch'].':'.$context['phase'];
        $deadline=$this->deadlines[$key]??=($start+30);
        $absent=$expected['release_key']===null;
        $e=['check_key'=>SiteServiceSupport::uuidV4(),'phase'=>$context['phase'],'probe_profile'=>$absent?'unavailable-v1':'release-v1',
            'site_key'=>$expected['site_key'],'release_key'=>$expected['release_key'],'artifact_hash'=>$expected['artifact_hash'],
            'binding_key'=>$target['binding_key'],'binding_version'=>$target['binding_version'],'attempt_id'=>$context['attempt_id'],
            'fence_epoch'=>$context['fence_epoch'],'expected_absent'=>$absent,'http_status'=>0,'duration_ms'=>0,
            'checked_at'=>'','result'=>'fail','marker_digest'=>false,'home_marker'=>false,'page_sanity'=>false,
            'css_integrity'=>false,'media_integrity'=>false,'origin_integrity'=>false,'tls_verified'=>false,'redirected'=>false,
            'cache_bypassed'=>false,'no_store'=>false,'no_current_link'=>false];
        try {
            if($expected['site_key']!==$this->binding['site_key']||$target['site_id']!==$this->binding['site_id']
                ||$target['target_id']!==$this->binding['target_id']||$target['environment']!=='staging')throw new RuntimeException('binding');
            $endpoint=$this->binding[$context['phase']==='candidate'?'candidate':'active'];
            $observations=[];
            $get=function(string $path,int $max) use($deadline,$endpoint,&$observations):array {
                if(($this->clock)()>=$deadline||!preg_match('~^/(?:[a-z0-9_-]+/)*(?:[a-z0-9_.-]+)?$~D',$path)||str_contains($path,'..'))throw new RuntimeException('probe');
                $r=($this->transport)($endpoint,$path,$max,$deadline-($this->clock)(),$this->binding);
                if(!is_array($r)||!isset($r['status'],$r['body'],$r['headers'],$r['tls_verified'],$r['destination_verified'],$r['cache_request']))throw new RuntimeException('transport');
                if(!is_string($r['body'])||strlen($r['body'])>$max||($this->clock)()>$deadline)throw new RuntimeException('bounds');
                $observations[]=$r;return $r;
            };
            $home=$get('/',SiteArtifactFiles::MAX_HTML);$e['http_status']=$home['status'];
            if($absent) {
                $marker=$get('/_release.json',1024);
                $e['no_current_link']=($origin['no_current_link']??false)===true;
                $e['home_marker']=str_contains(strtolower($home['body']),'site-release');
                $absentValid=in_array($context['phase'],['rollback','reconcile'],true)&&$e['no_current_link']
                    &&$home['status']===503&&$marker['status']===503&&!$e['home_marker']
                    &&!str_contains($home['body'],'public_payload_hash')&&!str_contains($home['body'],'release_key')
                    &&!str_contains(strtolower($marker['body']),'site-release')
                    &&!str_contains($marker['body'],'public_payload_hash')&&!str_contains($marker['body'],'release_key');
            } else {
                SiteDeploymentContract::keys($origin,['site_key','release_key','artifact_hash','manifest_hash','public_payload_hash','files','no_current_link']);
                $files=$origin['files'];
                if(!is_array($files)||count($files)<3||count($files)>SiteArtifactFiles::MAX_FILES)throw new RuntimeException('inventory');
                $map=[];$bytes=0;
                foreach($files as $f) {
                    SiteDeploymentContract::keys($f,['relative_path','sha256','byte_size','mime_type']);
                    SiteArtifactFiles::path($f['relative_path']);SiteBuildContract::hash($f['sha256']);
                    if(!str_starts_with($f['relative_path'],'public/')||isset($map[$f['relative_path']])||!is_int($f['byte_size'])
                        ||$f['byte_size']<1||$f['byte_size']>SiteArtifactFiles::MAX_ASSET)throw new RuntimeException('inventory');
                    $map[$f['relative_path']]=$f;$bytes+=$f['byte_size'];
                }
                $payload=array_values(array_filter($files,static fn($f)=>$f['relative_path']!=='public/_release.json'));
                $e['origin_integrity']=$origin['site_key']===$expected['site_key']&&$origin['release_key']===$expected['release_key']
                    &&$origin['artifact_hash']===$expected['artifact_hash']&&CanonicalJson::hash($files)===$expected['artifact_hash']
                    &&CanonicalJson::hash($payload)===$origin['public_payload_hash']&&$origin['no_current_link']===false
                    &&$bytes<=SiteArtifactFiles::MAX_PACKAGE;
                $matches=static function(array $r,array $f):bool {
                    $type=strtolower(trim(explode(';',$r['headers']['content-type']??'')[0]));
                    return $r['status']===200&&$type===$f['mime_type']&&strlen($r['body'])===$f['byte_size']
                        &&hash_equals($f['sha256'],hash('sha256',$r['body']));
                };
                $marker=$get('/_release.json',1024);
                $markerBytes=CanonicalJson::encode(['contract_version'=>SiteArtifactValidator::CONTRACT,'site_key'=>$expected['site_key'],
                    'release_key'=>$expected['release_key'],'public_payload_hash'=>$origin['public_payload_hash']]);
                $e['marker_digest']=isset($map['public/_release.json'])&&$matches($marker,$map['public/_release.json'])&&$marker['body']===$markerBytes;
                $prior=libxml_use_internal_errors(true);
                try{$dom=new DOMDocument();$dom->resolveExternals=false;$dom->substituteEntities=false;
                    $parsed=$dom->loadHTML($home['body'],LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
                }finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
                $xp=new DOMXPath($dom);$markers=$xp->query('//meta[@name="site-release"]');
                $e['home_marker']=$parsed&&$markers->length===1&&$markers->item(0)->getAttribute('content')===$expected['site_key'].'/'.$expected['release_key'];
                $e['page_sanity']=isset($map['public/index.html'])&&$matches($home,$map['public/index.html'])&&$parsed
                    &&$xp->query('//title')->length===1&&trim($xp->query('//title')->item(0)->textContent)!==''
                    &&$xp->query('//main[@data-page-key]')->length===1&&$xp->query('//script|//form|//iframe')->length===0;
                // One additional page when present, and every referenced home CSS/media (bounded by the phase).
                foreach($files as $f)if($f['mime_type']==='text/html'&&$f['relative_path']!=='public/index.html') {
                    $path='/'.substr($f['relative_path'],7,-10);
                    $e['page_sanity']=$e['page_sanity']&&$matches($get($path,SiteArtifactFiles::MAX_HTML),$f);break;
                }
                $css=0;$media=0;$cssOk=true;$mediaOk=true;
                foreach($xp->query('//link[@rel="stylesheet"]|//img[@src]|//a[@href]') as $node) {
                    $url=$node->getAttribute($node->hasAttribute('src')?'src':'href');
                    if(!str_starts_with($url,'/assets/'))continue;
                    $f=$map['public'.$url]??null;
                    if($f===null)throw new RuntimeException('asset');
                    $ok=$matches($get($url,$f['byte_size']),$f);
                    if($f['mime_type']==='text/css'){$css++;$cssOk=$cssOk&&$ok;}else{$media++;$mediaOk=$mediaOk&&$ok;}
                }
                $e['css_integrity']=$css===1&&$cssOk;
                // Empty media is valid only when the validated home references none; full origin covers all files.
                $e['media_integrity']=$mediaOk&&($media>0||$xp->query('//img')->length===0);
            }
            $e['tls_verified']=count($observations)>0;$e['cache_bypassed']=true;$e['no_store']=true;
            foreach($observations as $r) {
                $h=$r['headers'];$e['tls_verified']=$e['tls_verified']&&$r['tls_verified']===true&&$r['destination_verified']===true;
                $e['redirected']=$e['redirected']||($r['status']>=300&&$r['status']<400)||isset($h['location']);
                $e['cache_bypassed']=$e['cache_bypassed']&&$r['cache_request']===true
                    &&(!isset($h['age'])||$h['age']==='0')&&!preg_match('/hit/i',$h['x-cache']??'');
                $e['no_store']=$e['no_store']&&preg_match('/(?:^|,)\s*no-store\s*(?:,|$)/i',$h['cache-control']??'')===1;
            }
            $valid=$e['tls_verified']&&!$e['redirected']&&$e['cache_bypassed']&&$e['no_store'];
            if($absent)$valid=$valid&&$absentValid;
            else foreach(['origin_integrity','marker_digest','home_marker','page_sanity','css_integrity','media_integrity'] as $k)$valid=$valid&&$e[$k];
            if($valid)$e['result']='pass';
        }catch(Throwable){/* Safe bounded failed evidence; never retain bodies, URLs or exception strings. */}
        $end=($this->clock)();$e['duration_ms']=min(30000,max(0,(int)round(($end-$start)*1000)));
        $e['checked_at']=DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$end))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        return $e;
    }

    private static function https(array $endpoint,string $path,int $limit,float $remaining,array $binding): array
    {
        if(!extension_loaded('curl')||$remaining<=0)throw new RuntimeException('transport');
        $c=curl_init();$body='';$headers=[];$headerBytes=0;
        $host=$endpoint['host'];$url='https://'.$host.$path.'?ubo_probe='.bin2hex(random_bytes(16));
        curl_setopt_array($c,[CURLOPT_URL=>$url,CURLOPT_RESOLVE=>[$host.':443:'.$endpoint['ip']],CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CAINFO=>$binding['ca_file'],
            CURLOPT_SSLCERT=>$binding['client_certificate'],CURLOPT_SSLKEY=>$binding['client_key'],
            CURLOPT_CONNECTTIMEOUT_MS=>min(2000,(int)($remaining*1000)),CURLOPT_TIMEOUT_MS=>min(5000,max(1,(int)($remaining*1000))),
            CURLOPT_HTTPHEADER=>['Cache-Control: no-cache, no-store','Pragma: no-cache','Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$headers,&$headerBytes):int {
                $headerBytes+=strlen($line);if($headerBytes>16384)return 0;
                if(str_starts_with($line,'HTTP/'))$headers=[];
                elseif(str_contains($line,':')){[$k,$v]=explode(':',$line,2);$k=strtolower(trim($k));
                    if(isset($headers[$k]))return 0;$headers[$k]=trim($v);}
                return strlen($line);
            },CURLOPT_WRITEFUNCTION=>static function($ch,string $part)use(&$body,$limit):int {
                if(strlen($body)+strlen($part)>$limit)return 0;$body.=$part;return strlen($part);
            }]);
        try {
            $ok=curl_exec($c);$info=curl_getinfo($c);
            if($ok===false||curl_errno($c)!==0)throw new RuntimeException('transport');
            return ['status'=>$info['http_code'],'body'=>$body,'headers'=>$headers,
                'tls_verified'=>$info['ssl_verify_result']===0,'destination_verified'=>$info['primary_ip']===$endpoint['ip']
                    &&$info['primary_port']===443,'cache_request'=>true];
        }finally{curl_close($c);}
    }
}
