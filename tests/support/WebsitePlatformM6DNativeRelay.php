<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(array_slice($argv,1)===['--help']) { echo "Internal admitted M6D single-worker relay; native execution receipt required.\n"; exit; }
require_once __DIR__.'/WebsitePlatformM6DNativeWire.php';
$listener=$client=$server=null;
set_error_handler(static fn()=>throw new RuntimeException('wire_io'));
try {
    [$a,$job]=M6DNativeWire::job($argv); $b=$job['binding']; $mode=M6DNativeWire::mode($b['subcase'],$b['leg']);
    $pump=new M6DNativeWirePump($mode); $end=hrtime(true)+45000000000;
    // No socket or credential access precedes full M6D admission AND the EXECUTE receipt.
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN);
    M6DNativeContract::need(is_resource($listener),'wire_listen');
    $port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);
    M6DNativeWire::emit('LISTEN',$b,['relay_pid'=>getmypid(),'port'=>$port]);
    M6DNativeWire::release('ACCEPT');
    $client=stream_socket_accept($listener,10,$peer); fclose($listener); $listener=null;
    M6DNativeContract::need(is_resource($client) && preg_match('/^127\.0\.0\.1:[0-9]+$/D',$peer)===1,'wire_client');
    // Fixed admitted disposable hostname only. The PDO worker verifies backend identity through this route.
    $server=stream_socket_client('tcp://'.$a['database_hostname'].':3306',$errno,$error,3);
    M6DNativeContract::need(is_resource($server),'wire_backend');
    foreach([$client,$server,STDIN] as $s)stream_set_blocking($s,false);
    $control=''; $armed=false;
    while(hrtime(true)<$end) {
        $read=[STDIN,$client,$server]; $write=[];
        foreach(['client'=>$client,'server'=>$server] as $side=>$s)if($pump->pending[$side]!=='')$write[]=$s;
        $except=[]; M6DNativeContract::need(stream_select($read,$write,$except,0,100000)!==false,'wire_select');
        foreach($write as $s) $pump->write($s===$client ? 'client' : 'server',static fn($bytes)=>fwrite($s,$bytes));
        foreach($read as $s) {
            if($s===STDIN) {
                $bytes=fread(STDIN,33); M6DNativeContract::need($bytes!==false && !($bytes==='' && feof(STDIN)),'wire_control_eof');
                $control.=$bytes; M6DNativeContract::need(strlen($control)<=32,'wire_control_bound');
                if(!str_contains($control,"\n"))continue;
                if($control==="STOP\n") {
                    M6DNativeContract::need($mode==='unarmed' && !$armed && $pump->pending===['client'=>'','server'=>''],'wire_stop_phase');
                    M6DNativeWire::emit('STOPPED',$b,['unarmed'=>true]); $exit=0; break 2;
                }
                M6DNativeContract::need($control==="ARM\n" && !$armed,'wire_control'); $control='';
                $pump->arm(); $armed=true; M6DNativeWire::emit('ARMED',$b,['mode'=>$mode]); continue;
            }
            $bytes=fread($s,65536);
            M6DNativeContract::need($bytes!==false && !($bytes==='' && feof($s)),'wire_early_eof');
            $pump->feed($s===$client ? 'client' : 'server',$bytes);
            if($pump->wire->fault!==null) {
                $fault=$pump->fault(); fclose($client); $client=null; fclose($server); $server=null;
                M6DNativeWire::emit('FAULT',$b,$fault); $exit=0; break 2;
            }
        }
    }
    M6DNativeContract::need(isset($exit),'wire_deadline');
} catch(Throwable $e) { fwrite(STDERR,json_encode(M6DNativeContract::safeFailure($e),JSON_THROW_ON_ERROR)."\n"); $exit=1; }
finally { foreach([$client,$server,$listener] as $s)if(is_resource($s))fclose($s); }
exit($exit);
