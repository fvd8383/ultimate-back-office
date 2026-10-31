<?php
declare(strict_types=1);
// Synthetic permission model + actual private Windows/POSIX files. Never native acceptance.
require_once __DIR__.'/support/WebsitePlatformM6CNativeReport.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
$assertions=0;$scenarios=0;
function setupCheck(bool $ok,string $why):void
{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function setupReject(callable $call,string $code):void
{
    try{$call();}catch(RuntimeException $e){setupCheck($e->getMessage()===$code,'expected '.$code.', got '.$e->getMessage());return;}
    throw new RuntimeException('accepted '.$code);
}
/** Explicit Linux capability MODEL: coordinator UID0, CHOWN + DAC_OVERRIDE, no FOWNER. */
final class SetupPermissionModel
{
    public array $nodes=[],$operations=[];
    public ?Closure $fault=null;
    public function __construct()
    {
        foreach(['/','/var','/var/lib','/var/lib/ubo-m6c'] as $path)
            $this->nodes[$path]=['mode'=>0040755,'uid'=>0,'gid'=>0,'dev'=>1];
    }
    public function io(string $op,...$a):mixed
    {
        $this->operations[]=[$op,...$a];$path=$op==='clearstatcache'?$a[1]:$a[0];
        if($this->fault!==null){$r=($this->fault)($op,$path,$this);if($r!==null)return $r;}
        switch($op){
            case 'clearstatcache':return null;
            case 'realpath':return $path;
            case 'lstat':return $this->nodes[$path]??false;
            case 'is_link':return isset($this->nodes[$path])&&($this->nodes[$path]['mode']&0170000)===0120000;
            case 'file_exists':return isset($this->nodes[$path]);
            case 'mkdir':if(isset($this->nodes[$path]))return false;
                $this->nodes[$path]=['mode'=>0040000|($a[1]&0700),'uid'=>0,'gid'=>0,'dev'=>1];return true;
            case 'chmod':
                if($this->nodes[$path]['uid']!==0)throw new RuntimeException('modeled_EPERM_no_FOWNER');
                $this->nodes[$path]['mode']=($this->nodes[$path]['mode']&0170000)|$a[1];return true;
            case 'chgrp':$this->nodes[$path]['gid']=$a[1];return true;
            case 'chown':$this->nodes[$path]['uid']=$a[1];return true;
        }
        throw new RuntimeException('unexpected model operation');
    }
    public function mutations():array
    {return array_values(array_filter($this->operations,fn($x)=>in_array($x[0],['mkdir','chmod','chgrp','chown'],true)));}
}
$base='/var/lib/ubo-m6c';
$old=new SetupPermissionModel();$old->io('mkdir',$base.'/work',0700);$old->io('chown',$base.'/work',2000);$old->io('chgrp',$base.'/work',2000);
setupReject(fn()=>$old->io('chmod',$base.'/work',0700),'modeled_EPERM_no_FOWNER');$scenarios++;
$model=new SetupPermissionModel();$continued=[];
$result=M6CNativeSetup::beforeDatabase(function()use(&$continued):array{$continued=['config','connection','worker'];return ['configured'=>true];},'setupCheck',[$model,'io']);
setupCheck($result===['configured'=>true]&&count($continued)===3,'only complete storage reaches continuation');
foreach(['work','sealed'] as $dir){
    setupCheck($model->nodes[$base.'/'.$dir]===['mode'=>0040700,'uid'=>2000,'gid'=>2000,'dev'=>1],'final worker metadata');
    $ops=array_values(array_filter($model->mutations(),fn($x)=>$x[1]===$base.'/'.$dir));
    setupCheck(array_column($ops,0)===['mkdir','chmod','chgrp','chown'],'mode before group before owner');
}
setupCheck($model->nodes[$base.'/uploads']===['mode'=>0040755,'uid'=>0,'gid'=>0,'dev'=>1],'uploads remain root-owned and readable');$scenarios++;
foreach(['work','sealed','uploads'] as $dir)foreach($dir==='uploads'?['mkdir','chmod']:['mkdir','chmod','chgrp','chown'] as $operation){
    foreach(['false','warning'] as $failure){
        $model=new SetupPermissionModel();$entered=false;
        $model->fault=static function($op,$path)use($base,$dir,$operation,$failure){
            if($op!==$operation||$path!==$base.'/'.$dir)return null;
            if($failure==='warning')trigger_error('SECRET-CONFIG-CANARY',E_USER_WARNING);
            return false;
        };
        setupReject(fn()=>M6CNativeSetup::beforeDatabase(function()use(&$entered):array{$entered=true;return [];},'setupCheck',[$model,'io']),
            'setup_storage_'.$dir.'_'.$operation);
        setupCheck(!$entered,'partial setup never reaches config/connection/worker continuation');
        setupCheck(end($model->operations)[0]===$operation,'no operations after failed mutation');$scenarios++;
    }
}
foreach(['uid'=>0,'gid'=>0,'mode'=>0040755,'dev'=>2,'type'=>0120700,'stat'=>false] as $key=>$value){
    $model=new SetupPermissionModel();$entered=false;
    $model->fault=static function($op,$path,$m)use($base,$key,$value){
        if($op==='lstat'&&$path===$base.'/work'&&($m->nodes[$path]['uid']??0)===2000){
            if($key==='stat')return false;
            return array_replace($m->nodes[$path],[$key==='type'?'mode':$key=>$value]);
        }return null;
    };
    setupReject(fn()=>M6CNativeSetup::beforeDatabase(function()use(&$entered):array{$entered=true;return [];},'setupCheck',[$model,'io']),
        'setup_storage_work_final'.($key==='stat'?'_stat':''));
    setupCheck(!$entered,'bad final metadata blocks all later work');$scenarios++;
}
foreach(['work','sealed','uploads'] as $dir)foreach([0040700,0120777] as $type){
    $model=new SetupPermissionModel();$model->nodes[$base.'/'.$dir]=['mode'=>$type,'uid'=>2000,'gid'=>2000,'dev'=>1];$before=$model->nodes;
    setupReject(fn()=>M6CNativeSetup::beforeDatabase(fn()=>[],'setupCheck',[$model,'io']),'setup_storage_'.$dir.'_exists');
    setupCheck($model->mutations()===[]&&$model->nodes===$before,'existing directory/link never repaired or followed');$scenarios++;
}
foreach(['uid'=>2000,'gid'=>2000,'mode'=>0040777,'link'=>0120755] as $key=>$value){
    $model=new SetupPermissionModel();$model->nodes[$base][$key==='link'?'mode':$key]=$value;
    setupReject(fn()=>M6CNativeSetup::beforeDatabase(fn()=>[],'setupCheck',[$model,'io']),'setup_storage_ancestor');
    setupCheck($model->mutations()===[],'unsafe ancestor never mutated');$scenarios++;
}
foreach(['created','group'] as $phase){
    $model=new SetupPermissionModel();
    $model->fault=static function($op,$path,$m)use($phase,$base){
        if($path!==$base.'/work')return null;
        if($phase==='group'&&$op==='chgrp')return true; // A lying/no-op transition must be detected.
        if($phase==='created'&&$op==='lstat')return array_replace($m->nodes[$path],['dev'=>2]);
        return null;
    };
    setupReject(fn()=>M6CNativeSetup::beforeDatabase(fn()=>[],'setupCheck',[$model,'io']),'setup_storage_work_'.$phase);$scenarios++;
}

// Real exclusive creation, writes, flush, stat, chmod and rename in one private local tree.
// Only Linux ownership/mode metadata are modeled on Windows; no Linux semantic claim.
$root=sys_get_temp_dir().'/ubo-m6c-setup-'.bin2hex(random_bytes(12));
setupCheck(mkdir($root,0700),'private local fixture');
function reportIo(string $root,?string $fault,array &$operations):Closure
{
    $mode=0600;
    return static function(string $op,...$a)use($root,$fault,&$operations,&$mode){
        $operations[]=$op;
        if($op===$fault){if($op==='fclose')fclose($a[0]);return false;}
        if($op==='fwrite'&&$fault==='short_write')return fwrite($a[0],substr($a[1],0,3));
        if($op==='rename'&&$fault==='rename_warning'){trigger_error('SECRET-REPORT-CANARY',E_USER_WARNING);return false;}
        if($op==='realpath')return $a[0]; // Fixed virtual ancestors; actual temp location is locally owned.
        if($op==='lstat')return ['mode'=>0040755,'uid'=>0,'gid'=>0,'dev'=>stat($root)['dev'],'ino'=>7];
        if($op==='fstat'){
            $s=fstat($a[0]);$s['uid']=0;$s['gid']=0;$s['mode']=0100000|$mode;
            if($fault==='metadata')$s['uid']=2000;
            return $s;
        }
        foreach($a as &$arg)if(is_string($arg)&&str_starts_with($arg,'/run/m6c/'))$arg=$root.'/'.basename($arg);
        unset($arg);
        if($op==='chmod')$mode=$a[1];
        return $op(...$a);
    };
}
try{
    $storage=$root.'/storage';setupCheck(mkdir($storage,0700),'real local storage parent');
    $model=new SetupPermissionModel();
    $realStorage=static function($op,...$a)use($storage,$base,$model){
        $path=$op==='clearstatcache'?$a[1]:$a[0];
        if(str_starts_with($path,$base.'/')){
            $local=$storage.'/'.basename($path);
            if($op==='mkdir'||$op==='chmod')setupCheck($op($local,$a[1]),'actual local '.$op);
            if($op==='file_exists')return file_exists($local);
            if($op==='is_link')return is_link($local);
            if($op==='clearstatcache')clearstatcache(true,$local);
            if($op==='lstat')setupCheck((lstat($local)['mode']&0170000)===0040000,'actual local directory type');
        }
        return $model->io($op,...$a); // UID/GID/mode transitions explicitly modeled, never native.
    };
    M6CNativeSetup::beforeDatabase(fn()=>[],'setupCheck',$realStorage);
    setupCheck(is_dir($storage.'/work')&&is_dir($storage.'/sealed')&&is_dir($storage.'/uploads'),'actual three local roots');
    $before=$model->mutations();
    setupReject(fn()=>M6CNativeSetup::beforeDatabase(fn()=>[],'setupCheck',$realStorage),'setup_storage_work_exists');
    setupCheck($model->mutations()===$before,'actual existing roots not repaired');$scenarios+=2;
    foreach([null,'fopen','fwrite','short_write','fflush','chmod','fstat','metadata','fclose','rename','rename_warning','oversize','serialization','preexisting','symlink_model','location_changed','cleanup'] as $fault){
        $scenarios++;$directory=$root.'/case-'.$scenarios;setupCheck(mkdir($directory,0700),'local report case');
        $operations=[];$io=reportIo($directory,$fault,$operations);$location=M6CNativeReport::location($io);
        $report=M6CNativeReport::initial(str_repeat('1',40),str_repeat('2',32));$report['stage']='storage_setup';
        $model=new SetupPermissionModel();$entered=false;
        $model->fault=static function($op){return $op==='chmod'?false:null;};
        $check=static function($ok,$code)use(&$report){$report['assertions']++;if(!$ok)throw new RuntimeException($code);$report['completed_assertions']++;};
        try{M6CNativeSetup::beforeDatabase(function()use(&$entered):array{$entered=true;return [];},$check,[$model,'io']);}
        catch(Throwable $error){M6CNativeReport::failure($report,$error,null);}
        setupCheck(!$entered,'actual failing setup gates report test continuation');$primary=$report['failure'];$completed=$report['completed_assertions'];
        if($fault==='oversize')$report['oversize']=str_repeat('x',1048577);
        if($fault==='serialization')$report['invalid_utf8']="\xff";
        if($fault==='preexisting')file_put_contents($directory.'/report.json','existing canary');
        if($fault==='symlink_model'){$real=$io;$io=static fn($op,...$a)=>$op==='is_link'?true:$real($op,...$a);}
        if($fault==='location_changed')$location['ino']=9;
        $diagnostic='';
        M6CNativeReport::finish($report,$location,static function()use($fault):array{
            if($fault==='cleanup')throw new RuntimeException('SECRET-CLEANUP');return [];
        },static function($line)use(&$diagnostic){$diagnostic.=$line;},$io);
        setupCheck($report['status']==='FAIL'&&$report['failure']===$primary,'primary failure preserved independently');
        setupCheck(!str_contains($diagnostic,'SECRET')&&strlen($diagnostic)<512,'bounded sanitized diagnostics');
        setupCheck(str_contains($diagnostic,'setup_storage_work_chmod')&&str_contains($diagnostic,'stage=storage_setup'),'primary setup diagnostic retained');
        $final=$directory.'/report.json';
        if($fault===null||$fault==='cleanup'){
            setupCheck(is_file($final),'expected published report: '.$diagnostic);
            $stored=json_decode(file_get_contents($final),true,32,JSON_THROW_ON_ERROR);
            setupCheck(!file_exists($final.'.pending'),'atomic rename consumed pending file');
            setupCheck(str_contains($diagnostic,'M6C REPORT PUBLISHED source='.$report['sha'].' run='.$report['token']
                .' bytes='.filesize($final).' sha256='.hash_file('sha256',$final)), 'publication acknowledgement binds exact bytes');
            setupCheck($stored['status']==='FAIL'&&$stored['stage']==='storage_setup'&&$stored['completed_assertions']===$completed,'early FAIL counters and stage published');
            setupCheck(count($stored['cases'])===20&&array_unique(array_column($stored['cases'],'status'))===['UNREACHED'],'all twenty cases remain unreached');
            setupReject(fn()=>M6CNativeContract::evidence($stored,str_repeat('1',40),str_repeat('2',32)),'M6C native: evidence_result');
            if($fault==='cleanup')setupCheck($stored['cleanup']==='FAILED','cleanup failure is secondary');
        }else{
            setupCheck(!str_contains($diagnostic,'M6C REPORT PUBLISHED'),'no acknowledgement before successful publication');
            setupCheck(isset($report['report_failure'])&&str_contains($diagnostic,'Native report publication FAIL:'),'distinct publication failure');
            setupCheck($fault==='preexisting'?file_get_contents($final)==='existing canary':!file_exists($final),'failed pending bytes never completed evidence');
            if($fault==='symlink_model'||$fault==='location_changed')setupCheck(!in_array('fopen',$operations,true),'untrusted report target never opened');
        }
    }
    $operations=[];$io=reportIo($root,null,$operations);$report=M6CNativeReport::initial('untrusted','untrusted');
    M6CNativeReport::failure($report,new RuntimeException('secret /arbitrary/path'),null);
    M6CNativeReport::finish($report,null,fn()=>[],fn($line)=>null,$io);
    setupCheck($operations===[]&&$report['failure']['code']==='native_failure','unattested invocation has no filesystem report operations');$scenarios++;
    $result=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/WebsitePlatformM6CNative.php','--container-run',str_repeat('1',40),str_repeat('2',32),'--report',$root.'/forbidden.json']);
    setupCheck($result['exit']===1&&!file_exists($root.'/forbidden.json'),'actual coordinator rejects arbitrary report argument');
    setupCheck(str_contains($result['stdout'],'M6C native FAIL; 0 assertions'),'direct invocation cannot reach setup');$scenarios++;
    $result=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/WebsitePlatformM6CNative.php','--container-run',str_repeat('1',40),str_repeat('2',32)]);
    setupCheck($result['exit']===1&&str_contains($result['stdout'],'0 assertions'),'actual unattested coordinator fails before setup');$scenarios++;
    // Static integration guards complement the behavioral helper tests, not replace them.
    $coordinator=file_get_contents(__DIR__.'/WebsitePlatformM6CNative.php');
    $launcher=file_get_contents(__DIR__.'/RunM6CNative.sh');$contract=file_get_contents(__DIR__.'/support/WebsitePlatformM6CNativeContract.php');
    setupCheck(str_contains($coordinator,'return M6CNativeSetup::beforeDatabase(function()')&&str_contains($coordinator,"},'m6nCheck');"),'actual coordinator uses tested continuation gate');
    setupCheck(str_contains($coordinator,'M6CNativeReport::failure($report,$e,$current)')&&str_contains($coordinator,'M6CNativeReport::finish($report,$reportLocation'),'actual catch/finally uses tested reporter');
    preg_match_all('/--cap-add ([A-Z_]+)/',$launcher,$caps);
    setupCheck($caps[1]===['CHOWN','DAC_OVERRIDE','KILL','SETUID','SETGID','SETPCAP'],'unchanged exact setup capabilities');
    setupCheck(str_contains($contract,"['CHOWN','DAC_OVERRIDE','KILL','SETGID','SETPCAP','SETUID']"),'inspection enforces exact capabilities');
    setupCheck(str_contains($coordinator,"'--bounding-set=-all','--inh-caps=-all','--ambient-caps=-all'")&&str_contains($coordinator,'int $uid=2000'),'worker capability drop and default UID intact');
    $runtime=file_get_contents(dirname(__DIR__).'/private/classes/SiteBuildRuntime.php');
    setupCheck(str_contains($runtime,'0077')&&str_contains($runtime,'worker_uid'),'protected runtime private-path contract retained');
}finally{
    M6CNativeProcess::cleanup();
    // Delete only this exact random private fixture tree; never follow links.
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){
        setupCheck(!$f->isLink(),'no fixture link traversal');
        if($f->isDir())setupCheck(rmdir($f->getPathname()),'fixture directory cleanup');
        else{chmod($f->getPathname(),0600);setupCheck(unlink($f->getPathname()),'fixture file cleanup');}
    }
    setupCheck(rmdir($root),'private fixture cleanup');
}
echo "M6C native setup/report: $assertions assertions / $scenarios scenarios passed; simulated Linux permissions, actual local file publication; NO native/container/SQL execution.\n";
