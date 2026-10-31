<?php
declare(strict_types=1);
// Pure receipt predicate only. All source/binary identities below are synthetic fixtures.
// No verify(), filesystem admission, helper load, compilation or native execution.
require_once __DIR__.'/support/WebsitePlatformM6CJournalAdmission.php';
$checks=0;
function jr(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function jrDeny(callable $f,string $label):void
{
    try{$f();}catch(RuntimeException $e){jr($e->getMessage()==='journal_helper_receipt',$label);return;}
    throw new LogicException('Missing receipt rejection: '.$label);
}
function jrTypeDeny(callable $f,string $label):void
{
    try{$f();}catch(TypeError $e){jr(str_contains($e->getMessage(),'Argument #4 ($zts) must be of type bool'),$label);return;}
    throw new LogicException('Missing strict argument TypeError: '.$label);
}
$sha=str_repeat('a',40);$sourceHash=str_repeat('c',64);$binaryHash=str_repeat('d',64);
// Literal PHP 8.4 NTS writer shape, not a reconstructed/downloaded operator receipt.
$json='{"version":1,"source_sha":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","php":"8.4.27","zts":false,"source_hash":"cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc","binary_hash":"dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd"}';
$receipt=json_decode($json,true,8,JSON_THROW_ON_ERROR);
jr($receipt['zts']===false&&get_debug_type($receipt['zts'])==='bool','literal JSON boolean false');
M6CJournalAdmission::receipt($receipt,$sha,'8.4.27',false,$sourceHash,$binaryHash);jr(true,'literal PHP 8.4 NTS accepted');
$encoded=json_encode($receipt,JSON_THROW_ON_ERROR);
jr(str_contains($encoded,'"zts":false'),'encoding retains boolean false');
$roundTrip=json_decode($encoded,true,8,JSON_THROW_ON_ERROR);
jr($roundTrip===$receipt&&$roundTrip['zts']===false,'round trip preserves every value and type');
M6CJournalAdmission::receipt($roundTrip,$sha,'8.4.27',false,$sourceHash,$binaryHash);jr(true,'round trip accepted');

$zts=$receipt;$zts['zts']=true;
jrDeny(fn()=>M6CJournalAdmission::receipt($zts,$sha,'8.4.27',true,$sourceHash,$binaryHash),'self-consistent ZTS runtime denied semantically');
foreach(['true','0','1','"0"','"false"','null','[]','{}','[false]','{"zts":false}','0.0','-1','2','""'] as $value){
    $bad=json_decode(str_replace('"zts":false','"zts":'.$value,$json),true,8,JSON_THROW_ON_ERROR);
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'receipt zts '.$value);
}
foreach([0,1,'0','false',null,[],0.0] as $value)
    jrTypeDeny(fn()=>M6CJournalAdmission::receipt($receipt,$sha,'8.4.27',$value,$sourceHash,$binaryHash),'strict runtime argument '.get_debug_type($value));
foreach([0,2,'1',1.0,true,null] as $version){
    $bad=$receipt;$bad['version']=$version;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'version value/type');
}
foreach(['source_sha'=>str_repeat('b',40),'php'=>'8.4.28','source_hash'=>str_repeat('e',64),'binary_hash'=>str_repeat('f',64)] as $field=>$value){
    $bad=$receipt;$bad[$field]=$value;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'independent identity mismatch '.$field);
}
// A mismatch in either expected hash cannot be masked by the other matching hash.
jrDeny(fn()=>M6CJournalAdmission::receipt($receipt,$sha,'8.4.27',false,str_repeat('e',64),$binaryHash),'expected source hash');
jrDeny(fn()=>M6CJournalAdmission::receipt($receipt,$sha,'8.4.27',false,$sourceHash,str_repeat('f',64)),'expected binary hash');
jrDeny(fn()=>M6CJournalAdmission::receipt($receipt,str_repeat('b',40),'8.4.27',false,$sourceHash,$binaryHash),'expected source SHA');
jrDeny(fn()=>M6CJournalAdmission::receipt($receipt,$sha,'8.4.28',false,$sourceHash,$binaryHash),'exact runtime PHP version');
foreach(['','SECRET\x1b',str_repeat('a',63),str_repeat('g',64),str_repeat('A',64),str_repeat('a',64)."\n"] as $hash){
    $bad=$receipt;$bad['source_hash']=$hash;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$hash,$binaryHash),'self-consistent malformed source hash');
    $bad=$receipt;$bad['binary_hash']=$hash;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$hash),'self-consistent malformed binary hash');
}
foreach(['',str_repeat('a',39),str_repeat('g',40),str_repeat('A',40),$sha."\n"] as $source){
    $bad=$receipt;$bad['source_sha']=$source;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$source,'8.4.27',false,$sourceHash,$binaryHash),'self-consistent malformed source SHA');
}
foreach(['8.3.29','8.5.0','9.4.0','8.4','8.4.27-dev','8.4.27 '."\n","8.4.27\n"] as $php){
    $bad=$receipt;$bad['php']=$php;
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,$php,false,$sourceHash,$binaryHash),'unsupported runtime version');
}
foreach(array_keys($receipt) as $field){
    $bad=$receipt;unset($bad[$field]);
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'missing field '.$field);
    $bad=$receipt;$bad[$field]="SECRET\nCANARY\x1b\0";
    jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'private/control data '.$field);
}
$bad=$receipt;$bad['extra']=false;
jrDeny(fn()=>M6CJournalAdmission::receipt($bad,$sha,'8.4.27',false,$sourceHash,$binaryHash),'extra field');
jrDeny(fn()=>M6CJournalAdmission::receipt(array_reverse($receipt,true),$sha,'8.4.27',false,$sourceHash,$binaryHash),'field order');
jrDeny(fn()=>M6CJournalAdmission::receipt([],$sha,'8.4.27',false,$sourceHash,$binaryHash),'empty receipt');

// Small source contract check supplements executable JSON tests; never eval shell/PHP source.
$writer=file_get_contents(__DIR__.'/native/m6c/journal/build.sh');
jr(str_contains($writer,'"php"=>PHP_VERSION,"zts"=>PHP_ZTS'),'writer serializes the actual constants unchanged');
$writerReceipt=['version'=>1,'source_sha'=>$sha,'php'=>PHP_VERSION,'zts'=>PHP_ZTS,'source_hash'=>$sourceHash,'binary_hash'=>$binaryHash];
$writerJson=json_encode($writerReceipt,JSON_THROW_ON_ERROR);
$decoded=json_decode($writerJson,true,8,JSON_THROW_ON_ERROR);
jr($decoded===$writerReceipt&&get_debug_type($decoded['zts'])===get_debug_type(PHP_ZTS),'actual constants survive JSON without conversion');
echo 'Runtime writer: '.PHP_OS_FAMILY.' PHP '.PHP_VERSION.' PHP_ZTS='.json_encode(PHP_ZTS).' type='.get_debug_type(PHP_ZTS)."\n";
echo 'Fixture writer JSON: '.$writerJson."\n";
if(PHP_VERSION_ID>=80400&&PHP_VERSION_ID<80500){
    jr(get_debug_type(PHP_ZTS)==='bool','PHP 8.4 constant is boolean');
    if(PHP_ZTS===true){
        jrDeny(fn()=>M6CJournalAdmission::receipt($decoded,$sha,PHP_VERSION,PHP_ZTS,$sourceHash,$binaryHash),'actual ZTS semantic rejection, not TypeError');
        echo "Actual writer result: RuntimeException journal_helper_receipt (ZTS denied); no Linux NTS acceptance.\n";
    }else{
        M6CJournalAdmission::receipt($decoded,$sha,PHP_VERSION,PHP_ZTS,$sourceHash,$binaryHash);jr(true,'actual PHP 8.4 NTS pure predicate accepted');
        echo "Actual writer result: pure PHP 8.4 NTS receipt accepted; no filesystem admission or load proof.\n";
    }
}elseif(PHP_VERSION_ID<80400){
    jr(get_debug_type(PHP_ZTS)==='int'&&in_array(PHP_ZTS,[0,1],true),'older runtime retains real integer constant');
    jrTypeDeny(fn()=>M6CJournalAdmission::receipt($decoded,$sha,PHP_VERSION,PHP_ZTS,$sourceHash,$binaryHash),'older integer constant outside native contract');
    echo "Actual writer result: older integer PHP_ZTS is outside the PHP 8.4 helper contract; synthetic boolean cases still ran.\n";
}else{
    jr(get_debug_type(PHP_ZTS)==='bool','later runtime boolean constant');
    jrDeny(fn()=>M6CJournalAdmission::receipt($decoded,$sha,PHP_VERSION,PHP_ZTS,$sourceHash,$binaryHash),'unsupported later runtime');
    echo "Actual writer result: unsupported runtime rejected; helper remains PHP 8.4 only.\n";
}
echo "M6C journal receipt: $checks assertions passed; synthetic identities, actual runtime constants; no helper loaded.\n";
