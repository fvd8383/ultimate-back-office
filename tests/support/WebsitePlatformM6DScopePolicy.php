<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';

/** Current M6D scope only; no application bootstrap or native runtime. */
final class WebsitePlatformM6DScopePolicy
{
    public const BASELINE='1134c65c5f6cdfea804d634fad73e2d8a354d065';
    public const APPROVED_CORRECTION='7cdbbeef639d670208d7bdd9318bc7097649a043';
    // Exact reviewed/native-tested correction, never a moving HEAD or directory exception.
    public const PINNED=[
        'private/classes/SiteServiceSupport.php','private/classes/SiteBuildStore.php',
        'tests/WebsitePlatformTransactionTest.php','tests/support/WebsitePlatformTransactionPDO.php',
    ];
    private const PROTECTED=[
        'database','public','apps','shared','infrastructure','private/config','private/site-static',
        'private/classes/SiteBuildService.php','private/classes/SiteBuildContract.php',
        'private/classes/SiteBuildDependencies.php','private/classes/SiteArtifact*','private/classes/LocalSiteArtifactStore.php',
        'private/classes/CanonicalJson.php','private/classes/SiteRevisionSnapshotHasher.php','tests/*M6C*','tests/support/*M6C*','tests/native',
    ];
    private const ALLOWED=[
        'private/classes/SiteManager.php','private/classes/SiteRevisionManager.php','private/classes/SiteApprovalManager.php',
        'private/classes/SiteBuildRuntime.php','docs/codex-handoff.md','docs/sprint-8.8.md','docs/sprint-8.8-m6-implementation-plan.md',
        'private/classes/SitePublisher.php','private/classes/SitePublisherFactory.php',
        'scripts/site-deployment-worker.php','scripts/reconcile-site-jobs.php',
        'docs/sprint-8.8-m6d-local-implementation.md','docs/sprint-8.8-m6d-native-validation.md',
        // Source-specific acceptance plus existing status references, never docs/*.
        'docs/sprint-8.8-m6d-native-acceptance.md',
    ];

    public static function verify(string $root): int
    {
        $assertions=0;
        $check=static function(bool $ok,string $why) use(&$assertions): void {
            $assertions++;if(!$ok)throw new RuntimeException($why);
        };
        $git=static function(array $args) use($root): array {
            return M6CNativeProcess::capture(['git','--no-optional-locks','-c','core.fsmonitor=false','-C',$root,...$args],15,262144);
        };
        $read=static function(array $args,string $why) use($git,$check): string {
            $r=$git($args);$check($r['exit']===0&&!$r['signaled']&&!$r['termination_requested'],$why);return $r['stdout'];
        };
        $approved=$read(['rev-parse','--verify',self::APPROVED_CORRECTION.'^{commit}'],'Approved correction reference unavailable');
        $check(trim($approved)===self::APPROVED_CORRECTION,'Immutable approved correction reference');
        $base=$read(['rev-parse','--verify',self::BASELINE.'^{commit}'],'Original baseline unavailable');
        $check(trim($base)===self::BASELINE,'Immutable original baseline');
        $entries=static function(string $raw,bool $index=false) use($check): array {
            $out=[];
            foreach(explode("\0",rtrim($raw,"\0")) as $entry){
                if($entry==='')continue;
                [$meta,$path]=explode("\t",$entry,2);$parts=explode(' ',$meta);
                $check(count($parts)===3&&($index?$parts[2]==='0':$parts[1]==='blob')&&!isset($out[$path]),'Regular unmerged-free pinned entry '.$path);
                $out[$path]=[$parts[0],$parts[$index?1:2]];
            }
            return $out;
        };
        $expected=$entries($read(['ls-tree','-rz',self::APPROVED_CORRECTION,'--',...self::PINNED],'Approved pinned tree'));
        $check(count($expected)===count(self::PINNED),'Complete approved pinned data');
        $head=$entries($read(['ls-tree','-rz','HEAD','--',...self::PINNED],'HEAD pinned tree'));
        $index=$entries($read(['ls-files','--stage','-z','--',...self::PINNED],'Index pinned tree'),true);
        foreach(self::PINNED as $path){
            $check(isset($expected[$path])&&in_array($expected[$path][0],['100644','100755'],true),'Approved regular file '.$path);
            $check(($head[$path]??null)===$expected[$path],'Approved HEAD content/mode '.$path);
            $check(($index[$path]??null)===$expected[$path],'Approved index content/mode '.$path);
            $file=$root.'/'.$path;clearstatcache(true,$file);
            $check(is_file($file)&&!is_link($file)&&filetype($file)==='file','Approved working regular file '.$path);
            // Git applies declared clean/EOL rules without rewriting this file.
            $hash=$read(['hash-object','--path='.$path,'--',$file],'Working pinned hash '.$path);
            $check(trim($hash)===$expected[$path][1],'Approved working content '.$path);
            if(PHP_OS_FAMILY!=='Windows')$check(((fileperms($file)&0111)!==0)===($expected[$path][0]==='100755'),'Approved working mode '.$path);
        }
        $views=['HEAD'=>[self::BASELINE,'HEAD'],'index'=>['--cached',self::BASELINE],'working'=>[self::BASELINE]];
        foreach($views as $view=>$args){
            $flags=['diff','--no-ext-diff','--no-textconv','--name-only','-z'];
            $changed=$read([...$flags,...$args,'--',...self::PROTECTED],'Protected inventory '.$view);
            $check($changed==='','Preserved source '.$view.': '.str_replace("\0",', ',$changed));
            self::checkPaths($read([...$flags,...$args],'Scope inventory '.$view),$check,$view);
        }
        self::checkPaths($read(['ls-files','--others','--exclude-standard','-z'],'Untracked inventory'),$check,'untracked');
        $plan='docs/sprint-8.8-m6-implementation-plan.md';
        $rows=static function(string $text): array {
            preg_match_all('/^\| (?:B|D|R|C|S|U)\d{2} \|.*$/m',str_replace("\r\n","\n",$text),$m);return $m[0];
        };
        $before=$rows($read(['show',self::BASELINE.':'.$plan],'Baseline requirement definitions'));
        $check(count($before)===77,'Original 77 requirement definitions');
        foreach(['HEAD'=>'HEAD:'.$plan,'index'=>':'.$plan] as $view=>$object)
            $check($rows($read(['show',$object],'Requirement source '.$view))===$before,'All 77 requirement definitions preserved '.$view);
        $check(is_file($root.'/'.$plan)&&!is_link($root.'/'.$plan),'Working requirements file');
        $check($rows((string)file_get_contents($root.'/'.$plan))===$before,'All 77 requirement definitions preserved working');
        return $assertions;
    }

    private static function checkPaths(string $raw,Closure $check,string $view): void
    {
        foreach(explode("\0",rtrim($raw,"\0")) as $file){
            if($file==='')continue;
            $check(in_array($file,self::ALLOWED,true)||in_array($file,self::PINNED,true)
                ||str_starts_with($file,'tests/WebsitePlatformM6D')||str_starts_with($file,'tests/support/WebsitePlatformM6D')
                ||str_starts_with($file,'private/classes/SiteDeployment'),'M6D scope '.$view.' '.$file);
        }
    }
}
