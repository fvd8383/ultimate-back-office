<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/WebsitePlatformM6CNativeWorkerError.php';

/** Definitions-only source verification shared by the instrumented adapter and local probes. */
final class M6CNativeGitSource
{
    /** Exact protected-runtime profile. Replacement, never a merge with the worker environment. */
    public static function environment():array
    {return ['PATH'=>'/usr/bin:/bin','HOME'=>'/nonexistent','GIT_CONFIG_NOSYSTEM'=>'1','LANG'=>'C'];}
    public static function verify(string $repo,string $sha,array $git=['/usr/bin/git'],?array $environment=null,
        string $mode='unknown',?callable $write=null):void
    {
        $environment??=self::environment();$write??=static fn($line)=>fwrite(STDERR,$line);
        foreach([['rev-parse','HEAD'],$status=['status','--porcelain','--untracked-files=all']] as $args){
            $expected=$args===$status?'':$sha;$r=null;
            try{
                $r=M6CNativeProcess::capture([...$git,'--no-optional-locks','-c','core.fsmonitor=false','-c','safe.directory='.$repo,'-C',$repo,...$args],10,65536,$environment);
                if($r['exit']!==0||$r['stderr']!==''||trim($r['stdout'])!==$expected)throw new RuntimeException('instrumented_source_identity');
            }catch(Throwable $error){
                M6CNativeWorkerError::emitSource($error,$mode,$args===$status?'source_status':'source_head',$r,$expected,$write);
                throw $error;
            }
        }
    }
}
