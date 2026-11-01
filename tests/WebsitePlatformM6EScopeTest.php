<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
/** Current M6E guard; closed M6C/M6D policies are never broadened. */
$base='303b073658d7eb08e6086237a70dd4d285dc121c';$assertions=0;
$allowed=[
    'private/classes/ApacheDigitalOceanSitePublisher.php','private/classes/SiteHttpHealthChecker.php',
    'private/classes/SitePublisherAuthority.php','private/classes/SitePublisherHostContract.php','private/classes/SitePublisherSession.php',
    'private/classes/SitePublisherFactory.php','private/classes/SiteDeploymentRuntime.php',
    'scripts/site-deployment-worker.php','scripts/reconcile-site-jobs.php',
    'infrastructure/deployment/ubo-site-publish','infrastructure/deployment/ubo_site_publish.py',
    'infrastructure/deployment/ubo-site-authority.php','infrastructure/deployment/site-publisher-host-contract.json.example',
    'infrastructure/deployment/ubo-site-publisher.socket.example','infrastructure/deployment/ubo-site-publisher@.service.example',
    'infrastructure/deployment/ubo-site-authority.socket.example','infrastructure/deployment/ubo-site-authority@.service.example',
    'infrastructure/deployment/ubo-site-worker.service.example',
    'infrastructure/apache/site-static-staging.conf.example',
    'tests/WebsitePlatformM6EHostTest.php','tests/WebsitePlatformM6EHealthTest.php','tests/WebsitePlatformM6EAuthorityTest.php',
    'tests/WebsitePlatformM6EHelperTest.py','tests/WebsitePlatformM6EProtocolTest.php','tests/WebsitePlatformM6EScopeTest.php',
    'tests/WebsitePlatformM6EIsolated.py','tests/support/WebsitePlatformM6EArtifactChild.php','tests/support/WebsitePlatformM6ESocketChild.php',
    'tests/WebsitePlatformM6EWireTest.py','tests/WebsitePlatformM6EPublicationTest.py',
    'tests/support/WebsitePlatformM6EWireChild.php','tests/support/WebsitePlatformM6EFilesystem.py',
    'private/classes/SitePublisherArtifactExport.php','private/classes/SitePublisherTransfer.php',
    'tests/WebsitePlatformM6ETransferTest.py','tests/support/WebsitePlatformM6ETransferChild.php',
    'tests/support/WebsitePlatformM6EIdentityChild.php','tests/support/WebsitePlatformM6EIdentityDriver.py',
    'tests/WebsitePlatformM6EIntegrationControlTest.py','tests/support/WebsitePlatformM6EAcceptance.py',
    'tests/support/WebsitePlatformM6EApache.py','tests/support/WebsitePlatformM6ECoordinator.py',
    'tests/support/WebsitePlatformM6EDrivers.py','tests/support/WebsitePlatformM6EHealthChild.php',
    'tests/support/WebsitePlatformM6ENativeBackend.py','tests/support/WebsitePlatformM6ENativePeer.py',
    'tests/support/WebsitePlatformM6ENativeSetup.php','tests/support/WebsitePlatformM6ENativeSql.php',
    'tests/support/WebsitePlatformM6ENativeWorker.php','tests/support/WebsitePlatformM6EPreparation.py',
    'tests/support/WebsitePlatformM6EProcess.py','tests/support/WebsitePlatformM6EProvision.py',
    'docs/sprint-8.8-m6e-local-implementation.md','docs/sprint-8.8-m6e-isolated-validation.md',
    'docs/sprint-8.8-m6-implementation-plan.md','docs/codex-handoff.md','docs/sprint-8.8.md',
    'docs/sprint-8.8-m6d-native-acceptance.md','docs/sprint-8.8-m6d-local-implementation.md',
];
$check=static function(bool $ok,string $why)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($why);};
$git=static function(array $args)use($check):string {
    $r=M6CNativeProcess::capture(['git','--no-optional-locks','-c','core.fsmonitor=false','-C',dirname(__DIR__),...$args],30,1048576);
    $check($r['exit']===0&&!$r['signaled']&&!$r['termination_requested'],'Git '.implode(' ',$args));return $r['stdout'];
};
$paths=static function(string $raw,string $view)use($allowed,$check):void {
    foreach(explode("\0",rtrim($raw,"\0")) as $f)if($f!=='')$check(in_array($f,$allowed,true),'M6E exact path '.$view.': '.$f);
};
try {
    $check(trim($git(['rev-parse',$base.'^{commit}']))===$base,'Exact preservation baseline');
    $git(['merge-base','--is-ancestor',$base,'HEAD']);
    foreach(['HEAD'=>[$base,'HEAD'],'index'=>['--cached',$base],'working'=>[$base]] as $view=>$args) {
        $paths($git(['diff','--no-ext-diff','--no-textconv','--name-only','-z',...$args]),$view);
        // All preexisting non-allowlisted files, including migrations, pins, M6C storage,
        // M6D state machine/native reports/policies, and transaction corrections, are pinned.
        $excluded=array_map(static fn($p)=>':(exclude)'.$p,$allowed);
        $check($git(['diff','--no-ext-diff','--no-textconv','--raw',...$args,'--','.',...$excluded])==='','Preserved closed code/content/modes '.$view);
    }
    $paths($git(['ls-files','--others','--exclude-standard','-z']),'untracked');
    $plan='docs/sprint-8.8-m6-implementation-plan.md';
    $rows=static function(string $s):array{preg_match_all('/^\| (?:B|D|R|C|S|U)\d{2} \|.*$/m',str_replace("\r\n","\n",$s),$m);return $m[0];};
    $original=$rows($git(['show',$base.':'.$plan]));$check(count($original)===77,'77 baseline definitions');
    foreach(['HEAD:'.$plan,':'.$plan] as $object)$check($rows($git(['show',$object]))===$original,'Unchanged definitions '.$object);
    $check($rows(file_get_contents(dirname(__DIR__).'/'.$plan))===$original,'Unchanged working definitions');
    $migrations=array_filter(explode("\n",trim($git(['ls-tree','-r','--name-only',$base,'database/migrations']))),static fn($p)=>str_ends_with($p,'.sql'));
    $check(count($migrations)===25,'All 25 immutable migration sources preserved');
    $check(count($allowed)===count(array_unique($allowed)),'No duplicate scope entries');
    echo "M6E scope: $assertions assertions PASS (exact baseline; working/index/HEAD; 25 migrations, 77 definitions and closed source preserved).\n";
}finally{M6CNativeProcess::cleanup();}
