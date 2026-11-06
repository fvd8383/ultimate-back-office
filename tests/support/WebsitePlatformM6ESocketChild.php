<?php
declare(strict_types=1);
// Test-only loopback responder. It does not load the publisher/runtime or connect to a database.
$mode=$argv[1]??'';
if(!in_array($mode,['valid','sequence','malformed','oversize','eof'],true))exit(2);
$server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if($server===false)exit(3);
echo stream_socket_get_name($server,false)."\n";flush();
$peer=stream_socket_accept($server,10);if($peer===false)exit(4);
stream_set_timeout($peer,5);$line=fgets($peer,16386);$request=json_decode($line,true,32,JSON_THROW_ON_ERROR);
if(strlen($line)>16384||$request['version']!==1||$request['operation']!=='inspect')exit(5);
if($mode==='valid'||$mode==='sequence')fwrite($peer,json_encode(['version'=>1,'sequence'=>$request['sequence']+($mode==='sequence'?1:0),
    'result'=>'ok','evidence'=>['fixture'=>true]],JSON_THROW_ON_ERROR)."\n");
elseif($mode==='malformed')fwrite($peer,"PRIVATE-ERROR-CANARY\n");
elseif($mode==='oversize')@fwrite($peer,str_repeat('x',1048577)."\n");
fclose($peer);fclose($server);
echo "owned-child-complete\n";
