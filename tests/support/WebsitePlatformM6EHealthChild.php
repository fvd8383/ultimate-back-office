<?php
declare(strict_types=1);
/** Real curl/TLS component lane. Does not claim protected deployment-worker admission. */
require_once __DIR__.'/WebsitePlatformM6ENativeSetup.php';
require_once dirname(__DIR__,2).'/private/classes/SiteDeploymentService.php';
require_once dirname(__DIR__,2).'/private/classes/SiteHttpHealthChecker.php';
try{
    $a=m6eBootstrap();m6eNeed(posix_geteuid()===2000&&count($argv)===3,'health_identity');
    $phase=$argv[1];$fault=$argv[2];
    m6eNeed(in_array($phase,['candidate','active','rollback'],true)&&in_array($fault,['none','bad-trust','bad-host','bad-sni','bad-client','absence','absence-not-active'],true),'health_arguments');
    $job=json_decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/health-input.json',2097152),true,64,JSON_THROW_ON_ERROR);
    m6eNeed($job['run_id']===$a['run_id']&&$job['source_sha']===$a['source_sha'],'health_binding');
    m6eNeed(isset($job['phases'][$phase]),'health_phase_input');
    $input=$job['phases'][$phase];
    $b=$job['binding'];$expected=$input['expected'];$origin=$input['origin'];$context=$input['context'];$context['phase']=$phase;
    if($fault==='bad-trust')$b['ca_file']='/etc/ubo-site-publisher/tls/wrong.pem';
    if($fault==='bad-client'){$b['client_certificate']='/etc/ubo-site-publisher/tls/wrong.pem';$b['client_key']='/etc/ubo-site-publisher/tls/wrong.key';}
    if(in_array($fault,['bad-host','bad-sni'],true))$b[$phase==='candidate'?'candidate':'active']['host']='wrong.m6e.invalid';
    if(in_array($fault,['absence','absence-not-active'],true)){
        foreach(['release_key','artifact_hash','release_id','deployment_id'] as $key)$expected[$key]=null;
        $origin=['no_current_link'=>true];
        $context['phase']=$fault==='absence'?'rollback':'active';
    }
    $checker=SiteHttpHealthChecker::forBinding($b);
    $e=$checker->check($expected,$job['target'],$context,$origin);
    echo CanonicalJson::encode(['pid'=>getmypid(),'uid'=>posix_geteuid(),'lane'=>'apache-tls','evidence'=>$e])."\n";
}catch(Throwable){fwrite(STDERR,"m6e_native_health_unresolved\n");exit(1);}
