<?php
declare(strict_types=1);
// Actual relay parser, synthetic packets only. No PDO, sockets or application bootstrap.
require_once __DIR__.'/support/WebsitePlatformM6CSupplementWire.php';
$assertions=0;$scenarios=0;
function swCheck(bool $ok,string $label):void
{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($label);}
function swError(callable $call,string $error):void
{
    try{$call();}catch(Throwable $e){
        swCheck(get_class($e)===RuntimeException::class&&$e->getMessage()===$error&&$e->getCode()===0,'exact '.$error);return;
    }
    throw new RuntimeException('Expected '.$error);
}
/** Every two-chunk split includes the header, command byte, SQL and final space. */
function swSegments(string $packet):array
{
    $deliveries=['whole'=>[$packet],'bytes'=>str_split($packet)];
    for($i=1;$i<strlen($packet);$i++)$deliveries['split-'.$i]=[substr($packet,0,$i),substr($packet,$i)];
    return $deliveries;
}
function swWire(string $mode):M6CSupplementWire
{
    global $scenarios;$scenarios++;
    $wire=new M6CSupplementWire($mode);
    $handshake=M6CSupplementWire::packet(pack('V',512).str_repeat("\0",28),1);
    $out='';foreach(str_split($handshake) as $byte)$out.=$wire->feed('client',$byte);
    swCheck($out===$handshake&&$wire->fault===null,'fragmented handshake unchanged');return $wire;
}
function swFeedTarget(M6CSupplementWire $wire,array $chunks):string
{
    $out='';foreach($chunks as $i=>$chunk){$out.=$wire->feed('client',$chunk);
        if($i<count($chunks)-1)swCheck($out===''&&$wire->fault===null,'no output or fault before complete target');
    }return $out;
}
function swOk(M6CSupplementWire $wire,array $chunks):void
{
    foreach($chunks as $i=>$chunk){
        swCheck($wire->feed('server',$chunk)==='','server OK withheld');
        if($i<count($chunks)-1)swCheck($wire->fault===null,'incomplete OK cannot prove fault');
    }
    swCheck($wire->fault===['mode'=>'drop-ack','forwarded'=>true,'ok_received'=>true],'validated matching OK fault');
}
// Literal frames independently pin both payload lengths, sequence zero and original bytes.
$commands=['bare'=>"\x07\0\0\0\x03COMMIT",'mysqlnd-default'=>"\x08\0\0\0\x03COMMIT "];
$ok="\x07\0\0\x01\0\0\0\x02\0\0\0"; // Parser fixture, not evidence of a database commit.
foreach(['before-commit','drop-ack'] as $mode)foreach($commands as $command)foreach(swSegments($command) as $chunks){
    $wire=swWire($mode);$wire->arm();swError(fn()=>$wire->arm(),'wire_already_armed');
    $out=swFeedTarget($wire,$chunks);
    if($mode==='before-commit'){
        swCheck($out==='','target suppressed before forwarding');
        swCheck($wire->fault===['mode'=>$mode,'forwarded'=>false,'ok_received'=>false],'before-commit fault');
    }else{
        swCheck($out===$command,'exact original header and payload forwarded');
        swCheck($wire->fault===null,'forwarding alone leaves outcome unknown');swOk($wire,[$ok]);
    }
    swError(fn()=>$wire->arm(),'wire_already_armed');
    foreach(['client','server'] as $side)swError(fn()=>$wire->feed($side,$command),'wire_closed');
}
foreach($commands as $command)foreach(swSegments($ok) as $chunks){
    $wire=swWire('drop-ack');$wire->arm();
    swCheck($wire->feed('client',$command)===$command&&$wire->fault===null,'response split starts after original command');
    swOk($wire,$chunks);
}
foreach(['before-commit','drop-ack'] as $mode)foreach($commands as $command)foreach(swSegments($command) as $chunks){
    $wire=swWire($mode);$out=swFeedTarget($wire,$chunks);
    swCheck($out===$command&&$wire->fault===null,'unarmed supported command unchanged');
    swCheck($wire->feed('server',$ok)===$ok&&$wire->fault===null,'unarmed response unchanged');
}

$neighbors=[
    'rollback'=>"\x03ROLLBACK",'unrelated'=>"\x03SELECT 1",'empty-sql'=>"\x03",'incomplete-sql'=>"\x03COMMI",
    'another-statement'=>"\x03COMMIT; SELECT 1",'chain'=>"\x03COMMIT AND CHAIN",'release'=>"\x03COMMIT RELEASE",
    'no-chain'=>"\x03COMMIT AND NO CHAIN",'no-release'=>"\x03COMMIT NO RELEASE",
    'comment-before'=>"\x03/*x*/COMMIT",'comment-after'=>"\x03COMMIT /*x*/",'line-comment'=>"\x03COMMIT -- x",
    'semicolon'=>"\x03COMMIT;",'space-semicolon'=>"\x03COMMIT ;",'semicolon-space'=>"\x03COMMIT; ",
    'double-space'=>"\x03COMMIT  ",'leading-space'=>"\x03 COMMIT",'internal-space'=>"\x03COM MIT",
    'lowercase'=>"\x03commit",'mixed-case'=>"\x03Commit",'embedded'=>"\x03SELECT 'COMMIT'",
    'prefix'=>"\x03COMMITMENT",'wrong-command'=>"\x16COMMIT",'wrong-command-space'=>"\x16COMMIT ",
    'no-command-byte'=>'COMMIT','space-tab'=>"\x03COMMIT \t",'non-ascii-space'=>"\x03COMMIT\xc2\xa0",
];
foreach([...range(0,31),127] as $control){
    $neighbors['trailing-control-'.$control]="\x03COMMIT".chr($control);
    $neighbors['leading-control-'.$control]="\x03".chr($control).'COMMIT';
}
foreach(['before-commit','drop-ack'] as $mode)foreach($neighbors as $name=>$payload)foreach(['whole','bytes'] as $delivery){
    $wire=swWire($mode);$wire->arm();$packet=M6CSupplementWire::packet($payload);
    $chunks=$delivery==='whole'?[$packet]:str_split($packet);$last=array_pop($chunks);
    foreach($chunks as $chunk)swCheck($wire->feed('client',$chunk)===''&&$wire->fault===null,'negative fragment held: '.$name);
    swError(fn()=>$wire->feed('client',$last),'wire_expected_commit');
    swCheck($wire->fault===null,'rejected neighbor cannot prove a fault: '.$name);
}
foreach(['before-commit','drop-ack'] as $mode){
    foreach([0x800,0x20,1<<26,1<<27] as $flag){
        $wire=new M6CSupplementWire($mode);$scenarios++;
        swError(fn()=>$wire->feed('client',M6CSupplementWire::packet(pack('V',$flag).str_repeat("\0",28),1)),'wire_unsupported_capabilities');
        swCheck($wire->fault===null,'unsupported capabilities cannot prove fault');
    }
    foreach([M6CSupplementWire::packet(str_repeat("\0",32),0),M6CSupplementWire::packet(str_repeat("\0",31),1)] as $bad){
        $wire=new M6CSupplementWire($mode);$scenarios++;
        swError(fn()=>$wire->feed('client',$bad),'wire_handshake');swCheck($wire->fault===null,'bad handshake no fault');
    }
    foreach([['zero',"\0\0\0\0",'wire_packet_bound'],['oversized',"\x01\0\x10\0",'wire_packet_bound'],
        ['buffer',str_repeat('x',1048581),'wire_bound']] as [$label,$bad,$error]){
        $wire=swWire($mode);$wire->arm();swError(fn()=>$wire->feed('client',$bad),$error);
        swCheck($wire->fault===null,'invalid frame no fault: '.$label);
    }
    foreach($commands as $command){
        // Truncation is buffered, never forwarded or turned into a successful observation.
        foreach([1,2,3,4,strlen($command)-1] as $length){
            $wire=swWire($mode);$wire->arm();swCheck($wire->feed('client',substr($command,0,$length))===''&&$wire->fault===null,'truncated command held');
            swCheck($wire->feed('client','')===''&&$wire->fault===null,'no invented completion on empty feed');
        }
        $wire=swWire($mode);$wire->arm();$nonzero=substr_replace($command,"\x02",3,1);
        swCheck($wire->feed('client',$nonzero)===$nonzero&&$wire->fault===null,'existing sequence-zero targeting unchanged');
    }
}
foreach($commands as $command){
    foreach([
        M6CSupplementWire::packet("\xfferror",1),M6CSupplementWire::packet("\0",1),
        M6CSupplementWire::packet("\0\0\0\x01\0\0\0",1),M6CSupplementWire::packet("\0\0\0\x03\0\0\0",1),
        M6CSupplementWire::packet("\0\x01\0\x02\0\0\0",1),M6CSupplementWire::packet("\0\0\x01\x02\0\0\0",1),
        substr_replace($ok,"\0",3,1),substr_replace($ok,"\x02",3,1),
    ] as $response){
        $wire=swWire('drop-ack');$wire->arm();swCheck($wire->feed('client',$command)===$command,'command retained before invalid response');
        $bytes=str_split($response);$last=array_pop($bytes);
        foreach($bytes as $byte)swCheck($wire->feed('server',$byte)===''&&$wire->fault===null,'incomplete response held');
        swError(fn()=>$wire->feed('server',$last),'wire_commit_not_ok');swCheck($wire->fault===null,'invalid response outcome unknown');
    }
    foreach($commands as $second){
        $wire=swWire('drop-ack');$wire->arm();swCheck($wire->feed('client',$command)===$command,'first command exact');
        swError(fn()=>$wire->feed('client',$second),'wire_second_command');swCheck($wire->fault===null,'second command no fault');
    }
}
foreach(["\x16SELECT id FROM fixture","\x03ROLLBACK",...array_map(fn($p)=>substr($p,4),array_values($commands))] as $payload){
    $wire=swWire('drop-query');$wire->arm();swCheck(swFeedTarget($wire,str_split(M6CSupplementWire::packet($payload)))==='','drop-query remains separate');
    swCheck($wire->fault===['mode'=>'drop-query','forwarded'=>false,'ok_received'=>false],'drop-query fault unchanged');
}
echo "M6C supplement wire: $assertions assertions / $scenarios scenarios passed; synthetic packets only; NO database or network.\n";
