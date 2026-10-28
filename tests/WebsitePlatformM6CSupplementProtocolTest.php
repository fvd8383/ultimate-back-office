<?php
declare(strict_types=1);
// Pure profile/packet contracts and real bounded local children. NO MySQL or native launch.
require_once __DIR__.'/support/WebsitePlatformM6CSupplementChannel.php';
require_once __DIR__.'/support/WebsitePlatformM6CSupplementWire.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeTransport.php';
$assertions=0;
function sp(bool $ok,string $label):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($label);}
function spDeny(callable $f,string $label):void{try{$f();}catch(Throwable){sp(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
$sha=str_repeat('a',40);$token=str_repeat('b',32);$case=M6CSupplementContract::CASES[0];
$r=M6CSupplementContract::initial($sha,$token);$r['status']='PASS';$r['assertions']=$r['completed_assertions']=17;
foreach($r['cases'] as &$c)$c=['status'=>'PASS','lane'=>'instrumented','assertions'=>1,'observed'=>true];unset($c);
M6CSupplementContract::evidence($r,$sha,$token);sp(true,'exact supplement accepted');
$json=json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);sp(M6CNativeTransport::envelope($json,$sha,$token,M6CSupplementContract::VERSION)===$r,'selected transport');
spDeny(fn()=>M6CNativeTransport::envelope($json,$sha,$token),'core rejects supplement');
spDeny(fn()=>M6CSupplementContract::version('environment-override'),'explicit profile only');
sp(M6CSupplementContract::version('core')===M6CNativeContract::VERSION&&count(M6CNativeContract::CASES)===20,'core profile preserved');
foreach(['version'=>M6CNativeContract::VERSION,'sha'=>str_repeat('c',40),'token'=>str_repeat('c',32),'native'=>false,'status'=>'FAIL','completed_assertions'=>16,'assertions'=>0] as $k=>$v){$bad=$r;$bad[$k]=$v;spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'wrong '.$k);}
foreach(M6CSupplementContract::CASES as $name){foreach(['status'=>'UNREACHED','lane'=>'protected','assertions'=>0,'observed'=>false] as $k=>$v){$bad=$r;$bad['cases'][$name][$k]=$v;spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'incomplete '.$name);}}
$bad=$r;unset($bad['cases'][$case]);spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'missing case');
$bad=$r;$bad['cases']['extra']=$c??[];spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'extra case');
$bad=$r;$bad['cases']=array_reverse($bad['cases'],true);spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'inventory order');
$duplicate=str_replace('"version": "'.M6CSupplementContract::VERSION.'",','"version": "wrong", "version": "'.M6CSupplementContract::VERSION.'",',$json);
spDeny(fn()=>M6CSupplementContract::decode($duplicate,$sha,$token),'duplicate JSON member');
$bad=$r;$bad['children'][]=['unexpected'=>true];spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'unexpected child cannot PASS');
$bad=$r;$bad['cases'][$case]['status']='FAIL';spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'failed case cannot PASS');
foreach([null,'not-an-array'] as $children){$bad=$r;$bad['children']=$children;spDeny(fn()=>M6CSupplementContract::evidence($bad,$sha,$token),'malformed child inventory');}
$core=['version'=>M6CNativeContract::VERSION,'sha'=>$sha,'token'=>$token,'status'=>'PASS','native'=>true,'assertions'=>20,'cases'=>[]];
foreach(M6CNativeContract::CASES as $name)$core['cases'][$name]=['status'=>'PASS','lane'=>str_starts_with($name,'protected-')||$name==='fresh-process-adoption'?'protected':'instrumented','assertions'=>1];
M6CNativeContract::evidence($core,$sha,$token);spDeny(fn()=>M6CSupplementContract::decode(json_encode($core,JSON_PRETTY_PRINT),$sha,$token),'supplement rejects core');

$handshake=M6CSupplementWire::packet(pack('V',512).str_repeat("\0",28),1);
$commit=M6CSupplementWire::packet("\x03COMMIT");$ok=M6CSupplementWire::packet("\0\0\0\x02\0\0\0",1);
foreach(['before-commit','drop-ack','drop-query'] as $mode){
    $wire=new M6CSupplementWire($mode);$out='';foreach(str_split($handshake) as $byte)$out.=$wire->feed('client',$byte);sp($out===$handshake,'fragmented handshake forwarded');
    sp($wire->feed('client',M6CSupplementWire::packet("\x03SELECT 1"))===M6CSupplementWire::packet("\x03SELECT 1"),'unarmed query untouched');
    $wire->arm();spDeny(fn()=>$wire->arm(),'double arming refused');$packet=$mode==='drop-query'?M6CSupplementWire::packet("\x16SELECT id FROM fixture"):$commit;
    $out='';foreach(str_split($packet) as $byte)$out.=$wire->feed('client',$byte);
    if($mode==='drop-ack'){sp($out===$commit&&$wire->fault===null,'COMMIT forwarded but outcome still unknown');$out='';foreach(str_split($ok) as $byte)$out.=$wire->feed('server',$byte);sp($out==='','OK not delivered');}
    else sp($out==='','target command not forwarded');
    sp($wire->fault===['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack'],'precise fault fact');spDeny(fn()=>$wire->feed('client','x'),'fault cannot be reused');
}
foreach([0x800,0x20,1<<26,1<<27] as $flag){$wire=new M6CSupplementWire('drop-ack');spDeny(fn()=>$wire->feed('client',M6CSupplementWire::packet(pack('V',$flag).str_repeat("\0",28),1)),'unsupported encrypted/compressed/attribute protocol fails closed');}
$wire=new M6CSupplementWire('drop-ack');$wire->feed('client',$handshake);$wire->arm();spDeny(fn()=>$wire->feed('client',M6CSupplementWire::packet("\x03ROLLBACK")),'only exact COMMIT targeted');
foreach([M6CSupplementWire::packet("\xfferror",1),M6CSupplementWire::packet("\0\0\0\x01\0\0\0",1),M6CSupplementWire::packet("\0",1)] as $response){$wire=new M6CSupplementWire('drop-ack');$wire->feed('client',$handshake);$wire->arm();$wire->feed('client',$commit);spDeny(fn()=>$wire->feed('server',$response),'not a committed OK');sp($wire->fault===null,'unknown never becomes successful fault proof');}

try{
    foreach(['normal','source','worker','capability','exit7','early','timeout','wrong'] as $mode){
        $report=['children'=>[]];$p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CSupplementProbe.php',$mode],5);
        if(in_array($mode,['early','timeout','wrong'],true)){
            spDeny(function()use($p,&$report,$case){M6CSupplementChannel::read($p,'READY',$report,$case,1);},'observed protocol failure '.$mode);
            $p->stop();sp(count($report['children'])===1&&$report['children'][0]['unexpected'],'failure evidence retained');
            sp(!str_contains(json_encode($report),'SECRET-CANARY'),'unsafe child data not exposed');
        }else{
            M6CSupplementChannel::read($p,'READY',$report,$case);$p->send('CONTINUE');$data=M6CSupplementChannel::read($p,'RESULT',$report,$case);
            sp($data===['status'=>'unknown','builds'=>0],'unavailable outcome remains unknown');
            if($mode==='exit7'){spDeny(function()use($p,&$report,$case){M6CSupplementChannel::finish($p,$report,$case);},'unexpected exit preserved');sp($report['children'][0]['exit']===7,'actual exit seven');}
            else{$result=M6CSupplementChannel::finish($p,$report,$case);sp($result['exit']===0,'actual successful child');
                if($mode==='source')sp($report['children'][0]['source_error']['source']===['exit'=>0,'stderr_nonempty'=>true,'matched'=>true],'source_error retained; strict nonempty stderr is not hidden');
                if($mode==='capability')sp($report['children'][0]['supplement_error']==='wire_unsupported_capabilities','precise missing native capability retained');
                if($mode==='worker')sp($report['children'][0]['worker_error']['code']==='native_child_failure'&&!str_contains(json_encode($report),'PRIVATE-CANARY'),'worker_error bounded');}
        }
    }
    // Explicit expected nonzero is distinct from an unexpected child failure.
    $p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CSupplementProbe.php','early'],5);$p->closeInput();$report=['children'=>[]];
    M6CSupplementChannel::finish($p,$report,$case,7);sp(!$report['children'][0]['unexpected']&&$report['children'][0]['exit']===7,'expected negative exit');
    foreach(M6CNativeProcess::cleanup() as $entry)sp(!$entry['running'],'earlier timeout children reaped');
    foreach([['CLAIM',''],['SEALED',''],['RECOVERY',''],['VERIFY',''],['COMMIT',''],['OBSERVE',''],['SPACE',''],
        ...array_map(fn($point)=>['BOUNDARY',$point],['candidate','proof','permission','space','rename','collision'])] as [$kind,$point]){
        $report=['children'=>[]];$p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CSupplementProbe.php','stage-fault',$kind,$point],5);
        M6CSupplementChannel::read($p,'READY',$report,$case);$p->send('CONTINUE');M6CSupplementChannel::read($p,$kind,$report,$case);$p->send('CONTINUE');
        spDeny(function()use($p,&$report,$case){M6CSupplementChannel::read($p,'RESULT',$report,$case);},'fault after observed '.$kind.' '.$point);
        $entry=$report['children'][0];sp($entry['last_barrier']===$kind&&$entry['exit']===7&&$entry['supplement_error']==='proxy_deadline','stage failure and original exit retained');
        sp($report['observations'][1]['kind']===$kind&&$report['observations'][1]['pid']===$p->pid,'typed observed boundary retained with child identity');
        $cleanup=M6CSupplementChannel::cleanupAfterFailure($report);sp(count($cleanup)===1&&!$cleanup[0]['running']&&$cleanup[0]['exit']===7,'stage cleanup preserves failure');
        sp($report['children'][1]['cleanup_after_failure']&&$report['children'][1]['supplement_error']==='proxy_deadline','literal fault retained through secondary cleanup');
    }
    $report=['children'=>[]];$p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CSupplementProbe.php','normal'],5);
    M6CSupplementChannel::read($p,'READY',$report,$case);$actual=$p->stop(9);M6CSupplementChannel::record($p,$report,$case,$actual,false);
    sp($actual['termination_requested']&&!$actual['running'],'owned process stopped after observed boundary');
    sp($report['children'][0]['signal']===$actual['signal']&&$report['children'][0]['exit']===$actual['exit'],'platform actual signal/exit retained without assuming POSIX on Windows');
}finally{$clean=M6CNativeProcess::cleanup();foreach($clean as $entry)sp(!$entry['running'],'owned children reaped');}
echo "M6C supplement protocol: $assertions assertions passed; synthetic packets and bounded local processes only; NO native SQL.\n";
