<?php
declare(strict_types=1);
// Local protocol fixture only. Never opens a socket or PDO connection.
require_once __DIR__.'/WebsitePlatformM6DNativeWire.php';
$mode=$argv[1] ?? ''; $line=fgets(STDIN,8194);
$b=json_decode((string)$line,true,16,JSON_THROW_ON_ERROR);
if($mode==='early')exit(7);
if($mode==='wrong')$b['subcase']='foreign';
if($mode==='partial') { echo '{'; exit(8); }
M6DNativeWire::emit('LISTEN',$b,['relay_pid'=>getmypid(),'port'=>12345]);
try {
    M6DNativeWire::release('ARM');
    M6DNativeWire::emit($mode==='phase' ? 'FAULT' : 'ARMED',$b,['mode'=>'drop-ack']);
    M6DNativeWire::release('CONTINUE');
    $fault=['mode'=>'drop-ack','forwarded'=>true,'ok_received'=>true,'ack_suppressed'=>true];
    M6DNativeWire::emit('FAULT',$b,$fault);
    if($mode==='duplicate')M6DNativeWire::emit('FAULT',$b,$fault);
} catch(Throwable $e) { fwrite(STDERR,json_encode(M6DNativeContract::safeFailure($e))."\n"); exit(1); }
