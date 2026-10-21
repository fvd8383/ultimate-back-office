<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeContract.php';
require_once __DIR__.'/WebsitePlatformM6CNativeSetup.php';

/** Coordinator diagnostics only; no application bootstrap, CLI or report-path argument. */
final class M6CNativeReport
{
    public static function initial(string $sha,string $token): array
    {
        return ['version'=>M6CNativeContract::VERSION,'native'=>true,'sha'=>$sha,'token'=>$token,
            'status'=>'FAIL','stage'=>'attestation','assertions'=>0,'completed_assertions'=>0,
            'cases'=>array_fill_keys(M6CNativeContract::CASES,['status'=>'UNREACHED']),
            'children'=>[],'limitations'=>['Direct synthetic identity/approval seeding is not UI approval coverage.',
                'Instrumented request/fault adapters are not protected factory evidence.',
                'File fsync and SIGKILL are not power-loss durability proof.']];
    }
    public static function failure(array &$report,Throwable $error,?string $current): void
    {
        if($current!==null)$report['cases'][$current]['status']='FAIL';
        $report['status']='FAIL';
        $report['failure']=['class'=>get_class($error),'case'=>$current,'stage'=>$report['stage'],
            'code'=>preg_match('/^[a-zA-Z0-9_]{1,100}$/D',$error->getMessage())?$error->getMessage():'native_failure'];
        if($error instanceof PDOException)$report['failure']['sql']=['sqlstate'=>$error->errorInfo[0]??null,'driver'=>$error->errorInfo[1]??null];
        elseif(preg_match('/SQLSTATE ([A-Z0-9]{5}), driver ([0-9]{1,10})/',$error->getMessage(),$sql))
            $report['failure']['sql']=['sqlstate'=>$sql[1],'driver'=>(int)$sql[2]];
    }
    /** Called only AFTER namespace and launcher attestation. Fixed location in real use. */
    public static function location(?callable $io=null): array
    {
        $io??=static fn(string $op,...$args)=>$op(...$args);
        foreach(['/','/run','/run/m6c'] as $path){
            $io('clearstatcache',true,$path);
            $s=m6nStep('report_location_stat',fn()=>$io('lstat',$path));
            if(($s['mode']&0170000)!==0040000||$s['uid']!==0||$s['gid']!==0
                ||($s['mode']&0022)!==0||$io('realpath',$path)!==$path)throw new RuntimeException('report_location');
        }
        return $s;
    }
    public static function finish(array &$report,?array $location,callable $cleanup,callable $diagnostic,?callable $io=null): void
    {
        try{$report['terminated_children']=$cleanup();}
        catch(Throwable){$report['status']='FAIL';$report['cleanup']='FAILED';}
        if(isset($report['failure']))$diagnostic('Native primary FAIL: '.$report['failure']['code'].'; stage='.$report['stage']
            .'; assertions='.$report['assertions'].'; completed='.$report['completed_assertions'].".\n");
        if($location===null)return; // Untrusted invocation never attempts any report write.
        try{self::publish($report,$location,$io);}
        catch(Throwable $error){
            $report['status']='FAIL';
            $code=preg_match('/^report_[a-z_]{1,60}$/D',$error->getMessage())?$error->getMessage():'report_failure';
            $report['report_failure']=$code;
            $diagnostic('Native report publication FAIL: '.$code.".\n");
        }
    }
    private static function publish(array $report,array $location,?callable $io): void
    {
        $io??=static fn(string $op,...$args)=>$op(...$args);
        $current=self::location($io);
        foreach(['dev','ino','uid','gid','mode'] as $key)
            if($current[$key]!==$location[$key])throw new RuntimeException('report_location_changed');
        $final='/run/m6c/report.json';$pending=$final.'.pending';
        foreach([$final,$pending] as $path){
            $io('clearstatcache',true,$path);
            if($io('is_link',$path)||$io('file_exists',$path))throw new RuntimeException('report_exists');
        }
        $json=m6nStep('report_serialize',fn()=>json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        if(strlen($json)>1048576)throw new RuntimeException('report_size');
        // One exclusive pending file, one bounded write and one final rename; no retries.
        $stream=m6nStep('report_open',fn()=>$io('fopen',$pending,'xb'));
        try{
            if(m6nStep('report_write',fn()=>$io('fwrite',$stream,$json))!==strlen($json))throw new RuntimeException('report_write');
            m6nStep('report_flush',fn()=>$io('fflush',$stream));
            m6nStep('report_chmod',fn()=>$io('chmod',$pending,0644));
            $s=m6nStep('report_stat',fn()=>$io('fstat',$stream));
            if(($s['mode']&0170000)!==0100000||($s['mode']&07777)!==0644||$s['uid']!==0||$s['gid']!==0
                ||$s['dev']!==$location['dev']||$s['nlink']!==1||$s['size']!==strlen($json))throw new RuntimeException('report_metadata');
        }catch(Throwable $error){
            // Close failure is secondary to the failed publication step. Never promote pending bytes.
            try{m6nStep('report_close',fn()=>$io('fclose',$stream));}catch(Throwable){}
            throw $error;
        }
        m6nStep('report_close',fn()=>$io('fclose',$stream));
        m6nStep('report_rename',fn()=>$io('rename',$pending,$final));
    }
}
