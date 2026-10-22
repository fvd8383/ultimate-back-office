<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeTransport.php';
$assertions=0;$scenarios=0;$sha=str_repeat('1',40);$token=str_repeat('2',32);
function diagnosticCheck(bool $ok,string $code):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($code);}
function diagnosticReject(callable $body,string $code):void
{
    try{$body();}catch(Throwable $e){diagnosticCheck(str_contains($e->getMessage(),$code),'expected '.$code.' got '.$e->getMessage());return;}
    throw new RuntimeException('accepted '.$code);
}
try{
    foreach(["Name:\tphp\nUid:\t0\t1000\t0\t1000\n"=>1000,"Uid:\t0\t0\t0\t0\n"=>0,
        "Name:\tphp\n"=>null,"Uid:\t0\t-1\t0\t0\n"=>null,str_repeat('x',16385)=>null] as $status=>$expected){
        $scenarios++;
        if($expected===null)diagnosticReject(fn()=>M6CNativeTransport::hostUid($status),'transport_host_uid');
        else diagnosticCheck(M6CNativeTransport::hostUid($status)===$expected,'effective host UID from bounded modeled proc status without POSIX');
    }
    foreach(['unexpected','expected-denial','before-barrier','timeout','overflow','bad-json','diagnostic-false','diagnostic-throw'] as $scenario){
        $scenarios++;$report=M6CNativeReport::initial($sha,$token);$diagnostics='';
        $write=static function($line)use(&$diagnostics,$scenario){
            if($scenario==='diagnostic-false')return false;
            if($scenario==='diagnostic-throw')throw new RuntimeException('SECRET-DIAGNOSTIC');
            $diagnostics.=$line;return strlen($line);
        };
        $code='fwrite(STDERR,"Build-only invocation stopped: artifact_invalid.\nSECRET-PASSWORD token=lease-secret\x1b[31m\x00\n");';
        if($scenario==='timeout')$code.='usleep(3000000);';
        elseif($scenario==='overflow')$code.='echo str_repeat("SECRET-CONTROL\x1b",1000);';
        elseif($scenario==='before-barrier')$code.='exit(7);';
        elseif($scenario==='bad-json')$code.='echo "IDENTITY {SECRET-CANARY\n";exit(7);';
        else $code.='echo "IDENTITY {}\n";exit(7);';
        $p=new M6CNativeProcess([PHP_BINARY,'-n','-r',$code],$scenario==='timeout'?1:5,$scenario==='overflow'?256:65536);
        $case='protected-multipage-build';$primary=null;
        try{
            M6CNativeDiagnostics::read($p,'IDENTITY',$report,$case,'diagnosticCheck',$write);
            M6CNativeDiagnostics::finish($p,$scenario==='expected-denial'?7:0,$report,$case,'diagnosticCheck',$write);
        }catch(Throwable $error){$primary=$error->getMessage();}
        if($scenario==='expected-denial')diagnosticCheck($primary===null&&$diagnostics===''&&!$report['children'][0]['unexpected'],'expected nonzero denial is not a failure');
        else diagnosticCheck($primary!==null,'primary child failure retained');
        if(in_array($scenario,['unexpected','diagnostic-false','diagnostic-throw'],true))diagnosticCheck($primary==='actual_child_exit','diagnostics cannot replace primary assertion');
        if(str_starts_with($scenario,'diagnostic-'))diagnosticCheck($report['child_diagnostic_write']==='FAIL','secondary diagnostic failure recorded');
        elseif($scenario!=='expected-denial')diagnosticCheck(str_contains($diagnostics,'M6C CHILD FAILURE'),'failure survives absent JSON');
        $safe=json_encode($report['children']);diagnosticCheck(!str_contains($safe,'SECRET')&&!str_contains($safe,'lease-secret')&&!str_contains($diagnostics,"\x1b")&&!str_contains($diagnostics,"\0"),'secret/control canaries never emitted or stored as excerpts');
        $entry=$report['children'][0];diagnosticCheck($entry['case']===$case&&strlen($entry['stderr']['sha256'])===64,'case and digest retained');
        if($scenario==='unexpected')diagnosticCheck($entry['exit']===7&&$entry['expected_exit']===0&&$entry['last_barrier']==='IDENTITY'
            &&$entry['stderr']['recognized']===['Build-only invocation stopped: artifact_invalid.'],'observed exit and allowlisted text after identity');
        if($scenario==='before-barrier')diagnosticCheck($entry['exit']===7&&$entry['expected_exit']===null,'barrier failure does not invent expected exit');
        if($scenario==='overflow')diagnosticCheck($entry['stdout']['truncated']&&$entry['stdout']['bytes']>$entry['stdout']['captured_bytes'],'overflow length/truncation retained');
        if($scenario==='timeout')diagnosticCheck($entry['termination_requested']===true&&$primary==='native_child_timeout','timeout cause and observed termination retained');
        M6CNativeProcess::cleanup();
    }
    $p=new M6CNativeProcess([PHP_BINARY,'-n','-r','usleep(2000000);'],5);
    $report=M6CNativeReport::initial($sha,$token);$out='';$write=static function($s)use(&$out){$out.=$s;return strlen($s);};
    M6CNativeDiagnostics::record($p,$report,'protected-multipage-build','finish',0,null,true,$write);
    diagnosticCheck($report['children'][0]['running']===true&&$report['children'][0]['exit']===null,'running child exit stays unknown');
    for($i=0;$i<40;$i++)M6CNativeDiagnostics::record($p,$report,'protected-multipage-build','finish',0,null,true,$write);
    diagnosticCheck(strlen($out)<=16384&&$report['child_diagnostics_truncated'],'aggregate diagnostic volume bounded');$p->stop();$scenarios++;
}finally{M6CNativeProcess::cleanup();}

$root=sys_get_temp_dir().'/ubo-m6c-transport-'.bin2hex(random_bytes(12));diagnosticCheck(mkdir($root,0700),'private transport fixture');
try{
    $report=M6CNativeReport::initial($sha,$token);$report['stage']='native_cases';$report['assertions']=560;$report['completed_assertions']=559;
    $report['cases']['protected-startup-denials']=['status'=>'PASS','lane'=>'protected','assertions'=>1];
    $report['cases']['protected-multipage-build']=['status'=>'FAIL','lane'=>'protected'];
    $bytes=json_encode($report,JSON_THROW_ON_ERROR);file_put_contents($root.'/report.json',$bytes);
    foreach([null,'owner','group','mode','link','fifo','hardlink','oversize','path','partial','changed'] as $fault){
        $scenarios++;$opened=false;
        $io=static function($op,...$a)use($root,$fault,&$opened){
            $path=$a[0]??'';
            if($op==='realpath')return $fault==='path'&&$path==='/run/m6c/report.json'?'/different':$path;
            if($op==='lstat'&&$path!=='/run/m6c/report.json'){$s=stat($root);$s['uid']=0;$s['gid']=0;$s['mode']=0040755;return $s;}
            if($op==='lstat'||$op==='fstat'){
                $s=$op==='lstat'?lstat($root.'/report.json'):fstat($a[0]);$s['uid']=0;$s['gid']=0;$s['mode']=0100644;
                if($op==='lstat')switch($fault){
                    case 'owner':$s['uid']=2000;break;case 'group':$s['gid']=2000;break;case 'mode':$s['mode']=0100666;break;
                    case 'link':$s['mode']=0120644;break;case 'fifo':$s['mode']=0010644;break;case 'hardlink':$s['nlink']=2;break;
                    case 'oversize':$s['size']=1048577;break;case 'changed':$s['ino']++;break;
                }
                return $s;
            }
            if($op==='fopen'){$opened=true;$a[0]=$root.'/report.json';}
            if($op==='stream_get_contents'&&$fault==='partial')return substr(stream_get_contents(...$a),0,-1);
            return $op(...$a);
        };
        if($fault===null)diagnosticCheck(M6CNativeTransport::read($sha,$token,$io)===$bytes,'actual fixed reader returns exact FAIL bytes');
        else{
            diagnosticReject(fn()=>M6CNativeTransport::read($sha,$token,$io),$fault==='partial'?'transport_partial_or_grown':($fault==='changed'?'transport_file_changed':'transport_file_contract'));
            if(!in_array($fault,['changed','partial'],true))diagnosticCheck(!$opened,'unsafe file rejected before open');
        }
    }
    foreach(['fail','pass','exact','over','empty','malformed','truncated','sha','token','ack-match','ack-size','ack-hash','ack-source','ack-run'] as $kind){
        $scenarios++;$dir=$root.'/'.$kind;mkdir($dir,0700);$body=$bytes;
        if($kind==='pass'){$pass=$report;$pass['status']='PASS';foreach($pass['cases'] as $name=>&$case)$case=['status'=>'PASS','assertions'=>1,'lane'=>str_starts_with($name,'protected-')||$name==='fresh-process-adoption'?'protected':'instrumented'];unset($case);$body=json_encode($pass);}
        if($kind==='exact'||$kind==='over')$body.=str_repeat(' ',1048576+($kind==='over'?1:0)-strlen($body));
        if($kind==='empty')$body='';if($kind==='malformed')$body='{bad';if($kind==='truncated')$body=substr($body,0,-1);
        if($kind==='sha')$body=str_replace($sha,str_repeat('9',40),$body);if($kind==='token')$body=str_replace($token,str_repeat('9',32),$body);
        if(str_starts_with($kind,'ack-'))file_put_contents($dir.'/child.stderr.txt','M6C REPORT PUBLISHED source='.($kind==='ack-source'?str_repeat('8',40):$sha)
            .' run='.($kind==='ack-run'?str_repeat('8',32):$token).' bytes='.(strlen($body)+($kind==='ack-size'?1:0))
            .' sha256='.($kind==='ack-hash'?str_repeat('0',64):hash('sha256',$body))."\n");
        $input=fopen('php://temp','w+b');fwrite($input,$body);rewind($input);$error=null;
        try{M6CNativeTransport::receive($input,$dir,'normal');$result=M6CNativeTransport::verify($dir,'normal',$sha,$token);}
        catch(Throwable $e){$error=$e;}finally{fclose($input);}
        if(in_array($kind,['fail','pass','exact','ack-match'],true)){
            diagnosticCheck($error===null&&file_get_contents($dir.'/native.json')===$body,'byte-identical valid report '.$kind);
            diagnosticCheck($result['bytes']===strlen($body)&&$result['sha256']===hash('sha256',$body),'verified hash/bytes');
            if($kind==='fail')diagnosticReject(fn()=>M6CNativeContract::evidence($report,$sha,$token),'evidence_result');
        }else diagnosticCheck($error!==null&&!file_exists($dir.'/native.json'),'invalid report never promoted '.$kind);
        if($kind==='over')diagnosticCheck(!file_exists($dir.'/native-normal.pending'),'over-limit never written to host');
    }
    // Diagnostic/acknowledgement sink failures preserve both primary and published bytes.
    foreach(['false','throw'] as $kind){
        $scenarios++;$r=$report;$r['failure']=['code'=>'actual_child_exit'];
        $io=static function($op,...$args)use($root){
            if($op==='lstat')return ['mode'=>0040755,'uid'=>0,'gid'=>0,'dev'=>1,'ino'=>1];
            if($op==='realpath')return $args[0];if($op==='is_link'||$op==='file_exists')return false;
            if($op==='fopen')return fopen('php://temp','w+b');
            if($op==='chmod'||$op==='rename')return true;
            if($op==='fstat'){$s=fstat($args[0]);return array_replace($s,['mode'=>0100644,'uid'=>0,'gid'=>0,'dev'=>1,'nlink'=>1]);}
            return $op(...$args);
        };
        $location=M6CNativeReport::location($io);
        M6CNativeReport::finish($r,$location,fn()=>[],static function()use($kind){if($kind==='throw')throw new RuntimeException('SECRET');return false;},$io);
        diagnosticCheck($r['status']==='FAIL'&&$r['failure']['code']==='actual_child_exit'&&$r['acknowledgement_write']==='FAIL'&&!isset($r['report_failure']),'ack failure remains separate from publication and primary');
    }
}finally{
    $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
    if($resolved===false||dirname($resolved)!==$parent||!preg_match('/^ubo-m6c-transport-[a-f0-9]{24}$/D',basename($resolved)))throw new RuntimeException('fixture_cleanup_boundary');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){
        if($file->isLink())throw new RuntimeException('fixture_link');
        if($file->isDir())rmdir($file->getPathname());else{chmod($file->getPathname(),0600);unlink($file->getPathname());}
    }rmdir($resolved);
}
echo "M6C diagnostics/transport: $assertions assertions / $scenarios scenarios passed; real local children/files, simulated Linux metadata; no Docker/SQL/native execution.\n";
