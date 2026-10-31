<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
$root=dirname(__DIR__); $base='1134c65c5f6cdfea804d634fad73e2d8a354d065'; $assertions=0;
$check=static function(bool $ok,string $why) use(&$assertions): void { $assertions++; if(!$ok)throw new RuntimeException($why); };
$git=static function(array $args) use($root): array { return M6CNativeProcess::capture(['git','--no-optional-locks','-C',$root,...$args],15,262144); };
try {
    foreach(['database','public','apps','shared','infrastructure','private/config','private/site-static',
        'private/classes/SiteBuildService.php','private/classes/SiteBuildContract.php','private/classes/SiteBuildStore.php',
        'private/classes/SiteBuildDependencies.php','private/classes/SiteArtifact*','private/classes/LocalSiteArtifactStore.php',
        'private/classes/CanonicalJson.php','private/classes/SiteRevisionSnapshotHasher.php','tests/*M6C*','tests/support/*M6C*','tests/native'] as $path) {
        $r=$git(['diff','--quiet',$base,'--',$path]); $check($r['exit']===0,'Preserved source '.$path);
    }
    $r=$git(['diff','--name-only',$base]); $check($r['exit']===0,'Diff inventory');
    $untracked=$git(['ls-files','--others','--exclude-standard']); $check($untracked['exit']===0,'Untracked inventory');
    $allowed=['private/classes/SiteManager.php','private/classes/SiteRevisionManager.php','private/classes/SiteApprovalManager.php',
        'private/classes/SiteBuildRuntime.php','docs/codex-handoff.md','docs/sprint-8.8.md','docs/sprint-8.8-m6-implementation-plan.md'];
    foreach(preg_split('/\r?\n/',trim($r['stdout']."\n".$untracked['stdout'])) as $file) {
        if($file==='')continue;
        $check(in_array($file,$allowed,true) || str_starts_with($file,'tests/WebsitePlatformM6D')
            || str_starts_with($file,'tests/support/WebsitePlatformM6D') || str_starts_with($file,'private/classes/SiteDeployment')
            || in_array($file,['private/classes/SitePublisher.php','private/classes/SitePublisherFactory.php',
                'scripts/site-deployment-worker.php','scripts/reconcile-site-jobs.php',
                'docs/sprint-8.8-m6d-local-implementation.md','docs/sprint-8.8-m6d-native-validation.md'],true),'M6D scope '.$file);
    }
    $old=$git(['show',$base.':docs/sprint-8.8-m6-implementation-plan.md']);
    preg_match_all('/^\| (?:B|D|R|C|S|U)\d{2} \|.*$/m',$old['stdout'],$before);
    preg_match_all('/^\| (?:B|D|R|C|S|U)\d{2} \|.*$/m',file_get_contents($root.'/docs/sprint-8.8-m6-implementation-plan.md'),$after);
    $check(count($before[0])===77 && array_map('trim',$before[0])===array_map('trim',$after[0]),'All 77 requirement definitions preserved');
    echo "M6D scope: $assertions assertions PASS (source/integrity boundaries).\n";
} finally { M6CNativeProcess::cleanup(); }
