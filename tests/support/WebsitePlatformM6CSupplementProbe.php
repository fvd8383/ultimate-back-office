<?php
declare(strict_types=1);
// Local synthetic child ONLY. No native coordinator, PDO connection or host configuration.
require_once __DIR__.'/WebsitePlatformM6CSupplementChannel.php';
$mode=$argv[1]??'';$code=0;
try{
    if(!in_array($mode,['normal','early','timeout','wrong','source','worker','capability','exit7','stage-fault'],true))throw new RuntimeException('probe_arguments');
    if($mode==='early'){fwrite(STDERR,"Synthetic early child exit.\n");$code=7;}
    elseif($mode==='timeout'){sleep(3);}
    elseif($mode==='wrong')echo "READY {\"private\":\"SECRET-CANARY\"}\n";
    else{
        M6CSupplementChannel::barrier('READY');
        if($mode==='stage-fault'){
            $kind=$argv[2]??'';$data=match($kind){'CLAIM','SEALED','RECOVERY'=>['job_id'=>1,'attempt_id'=>2],
                'COMMIT'=>['connection'=>3],'SPACE'=>['low'=>true,'unknown'=>true],'BOUNDARY'=>['point'=>$argv[3]??'','files'=>1],default=>[]};
            M6CSupplementChannel::barrier($kind,$data);
            M6CSupplementChannel::error(new RuntimeException('proxy_deadline'),'context');exit(7);
        }
        if($mode==='source')fwrite(STDERR,M6CNativeWorkerError::sourceLine(new RuntimeException('instrumented_source_identity'),'build','source_head',
            ['exit'=>0,'stderr'=>'nonempty modeled diagnostic','stdout'=>str_repeat('a',40)],str_repeat('a',40)));
        if($mode==='worker')fwrite(STDERR,M6CNativeWorkerError::line(new RuntimeException('PRIVATE-CANARY'),'build','execute'));
        if($mode==='capability')M6CSupplementChannel::error(new RuntimeException('wire_unsupported_capabilities'),'context');
        M6CSupplementChannel::emit('RESULT',['status'=>'unknown','builds'=>0]);$code=$mode==='exit7'?7:0;
    }
}catch(Throwable $e){M6CNativeWorkerError::emit($e,'build','context',static fn($line)=>fwrite(STDERR,$line));$code=1;}
exit($code);
