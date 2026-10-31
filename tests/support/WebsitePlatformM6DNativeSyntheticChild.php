<?php
declare(strict_types=1);
// Local protocol fixture only. No runtime admission, credentials, PDO or network.
require_once __DIR__.'/WebsitePlatformM6DNativeProtocol.php';
$identity=['profile'=>M6DNativeContract::PROFILE,'source_sha'=>str_repeat('a',40),'image_id'=>'sha256:'.str_repeat('b',64),'run_token'=>str_repeat('c',32)];
$mode=$argv[1] ?? '';
if($mode==='early') exit(7);
echo "READY 41\n"; flush();
if(trim((string)fgets(STDIN))!=='START') exit(8);
if($mode==='barriers') {
    try { foreach(['LOCKED','COMMITTED','BOUNDARY','ROLLED_BACK'] as $marker) M6DNativeProtocol::barrier($marker); }
    catch(Throwable $e) { fwrite(STDERR,json_encode(M6DNativeContract::safeFailure($e))."\n"); exit(1); }
}
if($mode==='timeout') { usleep(3000000); exit(9); }
if($mode==='overflow') { echo str_repeat('x',5000); exit(0); }
if($mode==='wrong') $identity['run_token']=str_repeat('d',32);
if($mode==='diagnostic') fwrite(STDERR,"unexplained diagnostic\n");
echo $mode==='malformed' ? "RESULT {broken\n" : M6DNativeProtocol::frame($identity,['denied'=>'unauthorized'])."\n";
if($mode!=='partial') echo "DONE\n";
