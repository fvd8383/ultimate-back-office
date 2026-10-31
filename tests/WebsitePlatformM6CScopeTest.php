<?php

declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6BScope.php';
$assertions=0;$root=dirname(__DIR__);$baseline='7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45';
function m6cs(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
$files=[];exec('git -C '.escapeshellarg($root).' diff --name-only '.$baseline.' -- . ":(exclude)tests" ":(exclude)docs"',$files,$status);
$other=[];exec('git -C '.escapeshellarg($root).' ls-files --others --exclude-standard',$other,$otherStatus);
$other=array_filter($other,fn($p)=>!str_starts_with($p,'tests/')&&!str_starts_with($p,'docs/'));
m6cs($status===0&&$otherStatus===0&&array_diff(array_merge($files,$other),m6cApplicationPaths())===[],'Exact M6C executable inventory');
foreach(['public','apps','shared','infrastructure','private/config','database']as$path){exec('git -C '.escapeshellarg($root).' diff --quiet '.$baseline.' -- '.escapeshellarg($path),$unused,$status);m6cs($status===0,'Protected tree '.$path);}
m6cs(m6bCanonicalMigrationsValid($root),'All 25 canonical migration pins retained');
$migrations=[];exec('git -C '.escapeshellarg($root).' ls-tree -r '.$baseline.' -- database/migrations',$migrations,$status);
foreach($migrations as$line){preg_match('~^100644 blob ([a-f0-9]{40})\t(.+)$~',$line,$m);$blob=trim((string)shell_exec('git -C '.escapeshellarg($root).' hash-object '.escapeshellarg($root.'/'.$m[2])));m6cs($blob===$m[1],'Baseline migration blob '.$m[2]);}
foreach(m6cApplicationPaths()as$file){
    $code=file_get_contents($root.'/'.$file);
    if($file==='private/classes/SiteRevisionManager.php'){
        $diff=shell_exec('git -C '.escapeshellarg($root).' diff --unified=0 '.$baseline.' -- '.escapeshellarg($file));
        preg_match_all('/^\+(?!\+)(.*)$/m',(string)$diff,$added);$code=implode("\n",$added[1]);
        m6cs(str_contains($code,"'asset_metadata' => \$assetRows")&&!str_contains($code,'function '),'Only private metadata return added to existing lifecycle owner');
    }
    m6cs(!preg_match('/(?:LeadHub|DomainManager|StripeBilling)::|curl_|fsockopen|stream_socket_client|INSERT INTO site_deployments|UPDATE site_deployment_targets|UPDATE site_approvals/i',$code),'No provider/publication effects '.$file);
}
foreach(['SitePublisher','SiteDeploymentService','ApacheDigitalOceanSitePublisher']as$name)m6cs(!file_exists($root.'/private/classes/'.$name.'.php'),'Future boundary '.$name);
$runtime=file_get_contents($root.'/private/classes/SiteBuildRuntime.php');
m6cs(str_contains($runtime,'private function __construct')&&!preg_match('/getenv\(|\$_(?:GET|POST)|trusted.*true|class_exists\(\$/i',$runtime),'No arbitrary runtime authority');
echo "Website platform M6C scope: $assertions assertions passed.\n";
