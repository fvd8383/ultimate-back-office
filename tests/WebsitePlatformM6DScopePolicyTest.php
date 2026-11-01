<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DScopePolicy.php';

// Actual policy, private local Git clone: never mutate the source application's files.
$source=dirname(__DIR__);$parent=sys_get_temp_dir().'/ubo-m6d-scope-'.bin2hex(random_bytes(12));
if(!mkdir($parent,0700))throw new RuntimeException('scope_fixture_directory');
$owned=realpath($parent);$repo=$parent.'/repo';$assertions=0;$controls=[];
$check=static function(bool $ok,string $why) use(&$assertions): void {$assertions++;if(!$ok)throw new RuntimeException($why);};
$command=static function(array $args): string {
    $r=M6CNativeProcess::capture(['git','--no-optional-locks',...$args],30,262144);
    if($r['exit']!==0||$r['signaled'])throw new RuntimeException('fixture_git: '.$r['stderr']);return $r['stdout'];
};
$git=static fn(array $args)=>$command(['-C',$repo,...$args]);
$restore=static fn(string $path)=>$git(['restore','--source='.WebsitePlatformM6DScopePolicy::APPROVED_CORRECTION,'--staged','--worktree','--',$path]);
$reject=static function(string $name,string $reason,?string $root=null) use($repo,$check,&$controls): void {
    try {WebsitePlatformM6DScopePolicy::verify($root??$repo);}
    catch(RuntimeException $e){$check(str_contains($e->getMessage(),$reason),$name.' rejected for wrong reason: '.$e->getMessage());$controls[$name]=$e->getMessage();return;}
    throw new RuntimeException('scope accepted '.$name);
};
try {
    mkdir($parent.'/empty-template',0700);
    // Shared objects are read locally; all file/index/commit mutations stay in this new clone.
    $command(['clone','--local','--shared','--no-hardlinks','--no-checkout','--template='.$parent.'/empty-template',str_replace('\\','/',$source),str_replace('\\','/',$repo)]);
    $git(['config','core.hooksPath',$parent.'/empty-template']);
    $git(['checkout','--detach',WebsitePlatformM6DScopePolicy::APPROVED_CORRECTION]);
    $check(WebsitePlatformM6DScopePolicy::verify($repo)>0,'Exact approved correction accepted');$controls['approved']='PASS';
    foreach(WebsitePlatformM6DScopePolicy::PINNED as $path){
        file_put_contents($repo.'/'.$path,"\n// Unapproved scope control.\n",FILE_APPEND);
        $reject('working edit '.$path,'Approved working content');$restore($path);
    }
    foreach(array_slice(WebsitePlatformM6DScopePolicy::PINNED,0,2) as $path){
        $original=file_get_contents($repo.'/'.$path);file_put_contents($repo.'/'.$path,$original."\n// Staged scope control.\n");
        $git(['add','--',$path]);file_put_contents($repo.'/'.$path,$original);
        $reject('staged edit masked by working copy '.$path,'Approved index content/mode');$restore($path);
    }
    $path=WebsitePlatformM6DScopePolicy::PINNED[0];unlink($repo.'/'.$path);
    $reject('missing protected file','Approved working regular file');
    mkdir($repo.'/'.$path);$reject('directory replacement','Approved working regular file');rmdir($repo.'/'.$path);$restore($path);
    $git(['update-index','--chmod=+x','--',$path]);$reject('tracked mode change','Approved index content/mode');
    // A bad committed mode cannot be concealed by restoring the index/worktree.
    $git(['-c','user.name=M6D scope fixture','-c','user.email=scope-fixture@example.invalid','commit','-m','negative mode fixture']);
    $restore($path);$reject('HEAD mode masked by index','Approved HEAD content/mode');
    $git(['checkout','--detach',WebsitePlatformM6DScopePolicy::APPROVED_CORRECTION]);$restore($path);
    $migration=glob($repo.'/database/migrations/025_*.sql');$check(count($migration)===1,'Exact migration fixture');
    $relative='database/migrations/'.basename($migration[0]);$original=file_get_contents($migration[0]);
    file_put_contents($migration[0],$original."\n-- Unapproved migration control.\n");$reject('migration working change','Preserved source working');
    $git(['add','--',$relative]);file_put_contents($migration[0],$original);$reject('migration index masked by working copy','Preserved source index');$restore($relative);
    foreach(['private/classes/UnapprovedScopeControl.php','tests/WebsitePlatformUnapprovedScopeTest.php','tests/support/WebsitePlatformUnapprovedScope.php','docs/unapproved-scope-control.md'] as $path){
        file_put_contents($repo.'/'.$path,"scope negative control\n");$reject('unapproved path '.$path,'M6D scope untracked '.$path);unlink($repo.'/'.$path);
    }
    $plan='docs/sprint-8.8-m6-implementation-plan.md';$original=file_get_contents($repo.'/'.$plan);
    $changed=preg_replace('/^(\| D01 \|).*$/m','$1 Changed requirement |',$original,1,$count);$check($count===1,'One definition changed');
    file_put_contents($repo.'/'.$plan,$changed);$reject('changed requirement','All 77 requirement definitions preserved working');
    $git(['add','--',$plan]);file_put_contents($repo.'/'.$plan,$original);$reject('staged requirement masked by working copy','All 77 requirement definitions preserved index');$restore($plan);
    file_put_contents($repo.'/'.$plan,preg_replace('/^\| D01 \|.*\r?\n/m','',$original,1));$reject('missing requirement','All 77 requirement definitions preserved working');$restore($plan);
    mkdir($parent.'/empty',0700);$command(['init','--template='.$parent.'/empty-template',$parent.'/empty']);
    $reject('unresolvable approved reference','Approved correction reference unavailable',$parent.'/empty');
    $check(WebsitePlatformM6DScopePolicy::verify($repo)>0,'Restored fixture accepted');
    $check($git(['status','--porcelain=v1'])==='','Fixture clean after controls');
} catch(Throwable $failure) {
    fwrite(STDERR,'Scope policy primary failure: '.$failure->getMessage()."\n");throw $failure;
} finally {
    M6CNativeProcess::cleanup();
    $check($owned!==false&&realpath($parent)===$owned&&str_starts_with(basename($parent),'ubo-m6d-scope-')&&!is_link($parent),'Exact owned cleanup root');
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($parent,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as $item){
        $path=$item->getPathname();
        if($item->isDir()&&!$item->isLink()){if(!rmdir($path))throw new RuntimeException('fixture_directory_cleanup');}
        else {
            // New fixture files only, never shared object targets; Git objects are read-only on Windows.
            if(!$item->isLink()&&!chmod($path,0600))throw new RuntimeException('fixture_file_permissions');
            if(!unlink($path))throw new RuntimeException('fixture_file_cleanup');
        }
    }
    if(!rmdir($parent))throw new RuntimeException('fixture_root_cleanup');
    $check(!file_exists($parent),'Private fixture removed');
}
foreach($controls as $name=>$result)echo $name.': '.$result."\n";
echo 'M6D scope policy: '.$assertions.' assertions PASS; '.count($controls)." controls; exact private fixture cleanup PASS.\n";
