<?php
declare(strict_types=1);
if(PHP_SAPI==='cli'&&array_slice($argv,1)===['--help']){echo "Internal single-connection supplemental relay; verified container only.\n";exit(0);}
require_once __DIR__.'/WebsitePlatformM6CSupplementChannel.php';
require_once __DIR__.'/WebsitePlatformM6CSupplementWire.php';
$listener=$client=$server=null;
try{
    if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||realpath(dirname(__DIR__,2))!=='/opt/ubo'
        ||!function_exists('posix_geteuid')||posix_geteuid()!==2000||count($argv)!==2)throw new RuntimeException('proxy_context');
    $context=json_decode(file_get_contents('/run/m6c/context.json'),true,32,JSON_THROW_ON_ERROR);
    if(($context['profile']??'')!==M6CSupplementContract::VERSION||!M6CNativeContract::privateIp($context['db_ip']??'')
        ||fileowner('/run/m6c/context.json')!==0)throw new RuntimeException('proxy_attestation');
    foreach(['CapEff','CapBnd','CapAmb'] as $cap)if(!preg_match('/^'.$cap.':\s*0+$/m',file_get_contents('/proc/self/status')))throw new RuntimeException('supplement_capabilities');
    $wire=new M6CSupplementWire($argv[1]);$end=hrtime(true)+90000000000;
    // One loopback-only listener inside the attested runtime namespace; no host port.
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN);
    if(!$listener)throw new RuntimeException('proxy_listen');
    $port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);
    M6CSupplementChannel::emit('LISTEN',['port'=>$port]);
    $client=stream_socket_accept($listener,15);fclose($listener);$listener=null;
    if(!$client)throw new RuntimeException('proxy_accept');
    $server=stream_socket_client('tcp://'.$context['db_ip'].':3306',$errno,$error,2);
    if(!$server)throw new RuntimeException('proxy_backend');
    foreach([$client,$server,STDIN] as $s)stream_set_blocking($s,false);
    $pending=['client'=>'','server'=>''];$control='';
    while(hrtime(true)<$end){
        $read=[$client,$server,STDIN];$write=[];
        foreach(['client'=>$client,'server'=>$server] as $side=>$s)if($pending[$side]!=='')$write[]=$s;
        $except=[];if(stream_select($read,$write,$except,0,100000)===false)throw new RuntimeException('proxy_select');
        foreach($write as $s){$side=$s===$client?'client':'server';$n=fwrite($s,$pending[$side]);
            if($n===false)throw new RuntimeException('proxy_write');$pending[$side]=substr($pending[$side],$n);}
        foreach($read as $s){
            if($s===STDIN){$control.=fread(STDIN,32);if(strlen($control)>32)throw new RuntimeException('proxy_control');
                if(str_contains($control,"\n")){if($control!=="ARM\n")throw new RuntimeException('proxy_control');$control='';$wire->arm();M6CSupplementChannel::emit('ARMED',['mode'=>$argv[1]]);}continue;}
            $bytes=fread($s,65536);if($bytes===false||($bytes===''&&feof($s)))throw new RuntimeException('proxy_early_eof');
            $side=$s===$client?'client':'server';$other=$side==='client'?'server':'client';
            $pending[$other].=$wire->feed($side,$bytes);if(strlen($pending[$other])>1048580)throw new RuntimeException('proxy_queue_bound');
            if($wire->fault!==null){
                // Drop BEFORE forwarding this response to the PDO connection. No result bytes
                // or credentials are emitted; the fresh coordinator connection observes DB truth.
                fclose($client);$client=null;fclose($server);$server=null;
                M6CSupplementChannel::emit('FAULT',$wire->fault);exit(0);
            }
        }
    }
    throw new RuntimeException('proxy_deadline');
}catch(Throwable $e){M6CSupplementChannel::error($e,'context');$exit=1;}
finally{foreach([$client,$server,$listener] as $s)if(is_resource($s))fclose($s);}
exit($exit??0);
