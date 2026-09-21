<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BScope.php';
$root=dirname(__DIR__);$baseline='5baae28c9af68cca7694a912d7f35c87c50c9dc5';$assertions=0;
function m6scope(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
$changes=[];exec('git -C '.escapeshellarg($root).' diff --name-only '.$baseline.' -- . ":(exclude)docs" ":(exclude)tests"',$changes,$status);
$untracked=[];exec('git -C '.escapeshellarg($root).' ls-files --others --exclude-standard',$untracked,$untrackedStatus);
$untracked=array_values(array_filter($untracked,static fn($p)=>!str_starts_with($p,'docs/')&&!str_starts_with($p,'tests/')));
m6scope($status===0&&$untrackedStatus===0&&array_diff(array_merge($changes,$untracked),m6bApplicationPaths())===[],'Exact application file boundary');
foreach(['public','infrastructure','scripts','shared','apps','private/config']as$path){
    exec('git -C '.escapeshellarg($root).' diff --quiet '.$baseline.' -- '.escapeshellarg($path),$unused,$status);
    m6scope($status===0,'No web routes/UI/publisher/provider/wrapper/configuration changes '.$path);
}
foreach(['private/classes/SiteRevisionManager.php'=>'lockBuildEligibility','private/classes/SiteApprovalManager.php'=>'lockedBuildApprovals']as$path=>$method){
    $diff=shell_exec('git -C '.escapeshellarg($root).' diff --unified=0 '.$baseline.' -- '.escapeshellarg($path));
    m6scope(is_string($diff)&&!preg_match('/^-(?!--)/m',$diff),'Existing application file is addition-only: '.$path);
    preg_match_all('/^\+\s+(?:public|private) static function (\w+)/m',$diff,$methods);
    m6scope($methods[1]===[$method],'Exactly one narrow owner method: '.$path);
}
foreach(glob($root.'/database/migrations/*.sql')as$file){
    if((int)basename($file)>=25)continue;
    $relative='database/migrations/'.basename($file);
    $blob=trim((string)shell_exec('git -C '.escapeshellarg($root).' rev-parse '.escapeshellarg($baseline.':'.$relative)));
    $current=trim((string)shell_exec('git -C '.escapeshellarg($root).' hash-object '.escapeshellarg($file)));
    m6scope($blob!==''&&$blob===$current,'Historical canonical migration unchanged: '.basename($file));
}
m6scope(m6bOnlyMigration025($root),'Only migration025 added');
$service=file_get_contents($root.'/private/classes/SiteBuildService.php');
m6scope(!preg_match('/INSERT INTO site_approvals|UPDATE sites|UPDATE site_revisions|UPDATE site_deployments|UPDATE site_deployment_targets|INSERT INTO site_deployments/i',$service),'No publication/deployment/approval SQL in build owner');
m6scope(!preg_match('/curl_|file_put_contents|mkdir\s*\(|rename\s*\(|copy\s*\(|exec\s*\(|shell_exec\s*\(/i',$service),'No rendering/storage/provider/publisher implementation');
m6scope(str_contains($service,'private static ?SiteBuildDependencies $dependencies = null;')&&!preg_match('/public static function (?:set|configure|wire)/',$service),'Runtime remains unwired with no public bypass setter');
foreach(['SiteDeploymentService.php','SitePublisher.php','ApacheDigitalOceanSitePublisher.php']as$file)m6scope(!file_exists($root.'/private/classes/'.$file),'Later orchestrator absent '.$file);
echo "Website platform M6B scope: $assertions assertions passed.\n";
