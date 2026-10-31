<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';

/** Owned real-Git repositories and temporary process environment for local source checks only. */
final class M6CGitFixture
{
    public readonly string $root;
    public readonly string $repo;
    public readonly string $foreign;
    public readonly string $git;
    public string $sha;
    public int $commands=0;
    public function __construct()
    {
        $this->git=PHP_OS_FAMILY==='Windows'?'C:/Git/cmd/git.exe':'/usr/bin/git';
        if(!is_file($this->git))throw new RuntimeException('fixture_git_missing');
        $this->root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'ubo-m6c-git-'.bin2hex(random_bytes(12));
        if(!mkdir($this->root,0700))throw new RuntimeException('fixture_root');
        try{
        $this->repo=$this->root.'/repo';$this->foreign=$this->root.'/foreign';
        foreach(['home','xdg','bad-home','bad-xdg','temp'] as $dir)mkdir($this->root.'/'.$dir,0700);
        mkdir($this->root.'/bad-xdg/git',0700);
        file_put_contents($this->root.'/bad-home/.gitconfig',"[INVALID-SECRET-CONFIG\n");
        file_put_contents($this->root.'/bad-xdg/git/config',"[INVALID-SECRET-CONFIG\n");
        file_put_contents($this->root.'/bad-global',"[INVALID-SECRET-CONFIG\n");
        file_put_contents($this->root.'/ignore',"untracked-canary.txt\n");
        $this->environment([],function():void{
            $this->run(['-c','init.defaultBranch=fixture','init','--quiet',$this->repo]);
            file_put_contents($this->repo.'/tracked.txt',"known tracked content\n");
            $this->run(['-C',$this->repo,'add','tracked.txt']);
            $this->run(['-C',$this->repo,'-c','user.name=M6C fixture','-c','user.email=fixture@example.invalid','commit','--quiet','-m','owned fixture']);
            $this->sha=trim($this->run(['-C',$this->repo,'rev-parse','HEAD'])['stdout']);
            $this->run(['clone','--quiet','--no-hardlinks',$this->repo,$this->foreign]);
        });
        }catch(Throwable $error){$this->cleanup();throw $error;}
    }
    public function run(array $args):array
    {
        $this->commands++;
        $r=M6CNativeProcess::capture([$this->git,'--no-optional-locks','-c','core.autocrlf=false',...$args],10);
        if($r['exit']!==0||$r['stderr']!=='')throw new RuntimeException('fixture_git_command');
        return $r;
    }
    /** Never reads a user config: all Git overrides are removed and HOME/XDG are owned. */
    public function environment(array $changes,callable $body):mixed
    {
        $selected=static fn(string $key):bool=>(bool)preg_match('/^(?:GIT_.*|HOME|XDG_CONFIG_HOME|XDG_CONFIG_DIRS|LANG)$/iD',$key);
        $saved=[];foreach(getenv() as $key=>$value)if($selected($key)){$saved[$key]=$value;putenv($key);}
        try{
            foreach(['HOME'=>$this->root.'/home','XDG_CONFIG_HOME'=>$this->root.'/xdg','GIT_CONFIG_NOSYSTEM'=>'1','LANG'=>'C'] as $key=>$value)putenv($key.'='.$value);
            foreach($changes as $key=>$value){if(!$selected($key))throw new RuntimeException('fixture_environment_key');putenv($value===null?$key:$key.'='.$value);}
            return $body();
        }finally{
            foreach(getenv() as $key=>$value)if($selected($key))putenv($key);
            foreach($saved as $key=>$value)putenv($key.'='.$value);
        }
    }
    /** Windows local test profile, deliberately distinct from the native Linux profile. */
    public function platformEnvironment():array
    {return ['PATH'=>dirname($this->git),'HOME'=>$this->root.'/home','GIT_CONFIG_NOSYSTEM'=>'1','LANG'=>'C'];}
    public function cleanup():void
    {
        $resolved=realpath($this->root);$parent=realpath(sys_get_temp_dir());
        if($resolved===false||$parent===false||dirname($resolved)!==$parent||!preg_match('/^ubo-m6c-git-[a-f0-9]{24}$/D',basename($resolved)))throw new RuntimeException('fixture_cleanup_boundary');
        $remove=function(string $dir)use(&$remove):void{
            foreach(new DirectoryIterator($dir) as $entry){if($entry->isDot())continue;$path=$entry->getPathname();
                if($entry->isDir()&&!$entry->isLink())$remove($path);else{chmod($path,0600);if(!unlink($path))throw new RuntimeException('fixture_cleanup_file');}}
            if(!rmdir($dir))throw new RuntimeException('fixture_cleanup_directory');
        };$remove($resolved);
    }
}
