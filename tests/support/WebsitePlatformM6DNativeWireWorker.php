<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(array_slice($argv,1)===['--help']) { echo "Internal admitted M6D result-transaction worker; native execution receipt required.\n"; exit; }
require_once __DIR__.'/WebsitePlatformM6DNativeWire.php';
require_once __DIR__.'/WebsitePlatformM6DNativeWireConnection.php';
set_error_handler(static fn()=>throw new RuntimeException('wire_io'));
try {
    [$a,$job]=M6DNativeWire::job($argv); $b=$job['binding']; $mode=M6DNativeWire::mode($b['subcase'],$b['leg']);
    M6DNativeWire::emit('WORKER',$b,['worker_pid'=>getmypid()]);
    $endpoint=M6DNativeWire::decode(rtrim((string)fgets(STDIN,8194),"\n"),'LISTEN',$b);
    $raw=M6DNativeWire::connect($a,$job,$endpoint);
    $connection=(int)$raw->query('SELECT CONNECTION_ID()')->fetchColumn();
    require_once __DIR__.'/WebsitePlatformM6DNativeFixture.php';
    $db=new M6DNativeWireConnection($raw,$b,$mode,static function(string $phase) use($b,$connection,$raw): void {
        M6DNativeWire::drain($raw);
        M6DNativeWire::emit($phase,$b,['connection'=>$connection,'drained'=>true]); M6DNativeWire::release('CONTINUE');
    });
    $d=new M6DNativeDependencies($db,$job['fixture']); $d->production=true; $d->environment='production'; $d->wire();
    M6DNativeContract::need($job['lease']['site_id']===$b['site_id'] && $job['lease']['target_id']===$b['target_id']
        && $job['lease']['deployment_id']===$b['deployment_id'] && $job['lease']['attempt_id']===$b['attempt_id'],'wire_lease_binding');
    M6DNativeWire::emit('READY',$b,['connection'=>$connection,'backend_verified'=>true]); M6DNativeWire::release('START');
    // Preserve the actual service result/error, including an unexpected failure. No invented unknown DTO.
    try {
        $r=SiteDeploymentService::completeDeploymentSuccess($job['lease'],$job['receipt']);
        $outcome=['returned'=>$r['operation_status'],'error'=>null];
    } catch(Throwable $e) {
        $outcome=['returned'=>null,'error'=>['class'=>get_class($e),
            'classification'=>$e instanceof SiteServiceException ? $e->classification() : null,'safe'=>M6DNativeContract::safeFailure($e)]];
    }
    $outcome+=['commit_returned'=>$db->commitReturned,'transport_failure'=>$db->transportFailure];
    M6DNativeWire::emit('OUTCOME',$b,$outcome); M6DNativeWire::release('CLOSE'); exit(0);
} catch(Throwable $e) { fwrite(STDERR,json_encode(M6DNativeContract::safeFailure($e),JSON_THROW_ON_ERROR)."\n"); exit(1); }
