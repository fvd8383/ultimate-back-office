<?php
declare(strict_types=1);

/** Test-harness operations only. Warnings and false returns become bounded step codes. */
function m6nStep(string $code,callable $operation): mixed
{
    set_error_handler(static function()use($code):never{throw new RuntimeException($code);});
    try{
        $result=$operation();
        if($result===false)throw new RuntimeException($code);
        return $result;
    }catch(Throwable){throw new RuntimeException($code);}
    finally{restore_error_handler();}
}

/** Fixed disposable-container roots; the optional operation recorder is test-only. */
final class M6CNativeSetup
{
    public static function beforeDatabase(callable $configure,callable $check,?callable $io=null): array
    {
        $io??=static fn(string $op,...$args)=>$op(...$args);
        $base='/var/lib/ubo-m6c';
        foreach(['/','/var','/var/lib',$base] as $ancestor){
            $io('clearstatcache',true,$ancestor);
            $s=m6nStep('setup_storage_ancestor_stat',fn()=>$io('lstat',$ancestor));
            $check(($s['mode']&0170000)===0040000&&$s['uid']===0&&$s['gid']===0
                &&($s['mode']&0022)===0&&$io('realpath',$ancestor)===$ancestor,'setup_storage_ancestor');
        }
        $device=$s['dev'];
        // Reject all pre-existing names, including dangling links, before any mutation.
        foreach(['work','sealed','uploads'] as $dir){
            $path=$base.'/'.$dir;$io('clearstatcache',true,$path);
            $check(!$io('is_link',$path)&&!$io('file_exists',$path),'setup_storage_'.$dir.'_exists');
        }
        foreach(['work','sealed','uploads'] as $dir){
            $path=$base.'/'.$dir;$code='setup_storage_'.$dir;$mode=$dir==='uploads'?0755:0700;
            m6nStep($code.'_mkdir',fn()=>$io('mkdir',$path,$mode));
            $s=self::metadata($path,$code,$io);
            $check(($s['mode']&0170000)===0040000&&$s['uid']===0&&$s['gid']===0
                &&($s['mode']&07777&~$mode)===0&&$s['dev']===$device,$code.'_created');
            // chmod requires ownership or FOWNER. DAC_OVERRIDE is not that authority.
            m6nStep($code.'_chmod',fn()=>$io('chmod',$path,$mode));
            self::verify($path,$code.'_mode',0,0,$mode,$device,$check,$io);
            if($dir!=='uploads'){
                m6nStep($code.'_chgrp',fn()=>$io('chgrp',$path,2000));
                self::verify($path,$code.'_group',0,2000,0700,$device,$check,$io);
                // Last mutation: no coordinator chmod after worker ownership is set.
                m6nStep($code.'_chown',fn()=>$io('chown',$path,2000));
            }
            self::verify($path,$code.'_final',$dir==='uploads'?0:2000,$dir==='uploads'?0:2000,
                $mode,$device,$check,$io);
        }
        // Configuration writes, SQL connections and subsequent worker work are gated here.
        return $configure();
    }
    private static function metadata(string $path,string $code,callable $io): array
    {
        $io('clearstatcache',true,$path);
        return m6nStep($code.'_stat',fn()=>$io('lstat',$path));
    }
    private static function verify(string $path,string $code,int $uid,int $gid,int $mode,mixed $device,
        callable $check,callable $io): void
    {
        $s=self::metadata($path,$code,$io);
        $check(($s['mode']&0170000)===0040000&&$s['uid']===$uid&&$s['gid']===$gid
            &&($s['mode']&07777)===$mode&&$s['dev']===$device,$code);
    }
}
