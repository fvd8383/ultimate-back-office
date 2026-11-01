<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DNativeWire.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeWireConnection.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeWireTestFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeControl.php';
require_once __DIR__.'/support/WebsitePlatformM6DFixture.php';
$assertions=0;
$check=static function(bool $ok,string $why) use(&$assertions): void { $assertions++; if(!$ok)throw new RuntimeException($why); };
$deny=static function(callable $call,string $code) use($check): void {
    try { $call(); } catch(RuntimeException $e) { $check(get_class($e)===RuntimeException::class && $e->getMessage()===$code,'expected_'.$code.'_got_'.$e->getMessage()); return; }
    throw new RuntimeException('missing_'.$code);
};
$identity=['profile'=>M6DNativeContract::PROFILE,'source_sha'=>str_repeat('a',40),'image_id'=>'sha256:'.str_repeat('b',64),'run_token'=>str_repeat('c',32)];
$handshake=M6CSupplementWire::packet(pack('V',512).str_repeat("\0",28),1); $ok=M6CSupplementWire::packet("\0\0\0\x02\0\0\0",1);
$new=static function(string $mode) use($handshake,$check): M6DNativeWirePump {
    $p=new M6DNativeWirePump($mode); foreach(str_split($handshake) as $b)$p->feed('client',$b);
    $sent=''; $p->write('server',static function($bytes) use(&$sent): int { $sent.=$bytes; return strlen($bytes); });
    $check($sent===$handshake,'handshake_forwarded_exactly'); return $p;
};
/** Disconnection/transaction semantics below are explicitly modeled, never mysqlnd evidence. */
final class M6DWireTestPDO extends PDO
{
    public bool $armed=false;
    public int $selectedCommits=0;
    public function __construct(public M6DDatabase $fake,public M6DNativeWirePump $pump) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { return $this->fake->prepare($query,$options); }
    public function beginTransaction(): bool { return $this->fake->beginTransaction(); }
    public function inTransaction(): bool { return $this->fake->inTransaction(); }
    public function rollBack(): bool { return $this->fake->rollBack(); }
    public function lastInsertId(?string $name=null): string|false { return $this->fake->lastInsertId($name); }
    public function commit(): bool
    {
        if(!$this->armed)return $this->fake->commit();
        $this->selectedCommits++; $this->pump->feed('client',M6CSupplementWire::packet("\x03COMMIT "));
        if($this->pump->mode==='before-commit')$this->fake->rollBack();
        else {
            $this->pump->write('server',static fn($s)=>strlen($s)); $this->fake->commit();
            $this->pump->feed('server',M6CSupplementWire::packet("\0\0\0\x02\0\0\0",1));
        }
        $e=new PDOException('modeled_disconnect'); $e->errorInfo=['HY000',2013,'PRIVATE']; throw $e;
    }
}
final class M6DWireDrainPDO extends PDO
{
    public array $calls=[];
    public function __construct(private int|false $result) {}
    public function exec(string $statement): int|false { $this->calls[]=$statement; return $this->result; }
}
try {
    $drain=new M6DWireDrainPDO(0); M6DNativeWire::drain($drain); $check($drain->calls===['DO 0'],'acknowledged_prearm_no_statement_handle');
    foreach([false,1] as $result)$deny(fn()=>M6DNativeWire::drain(new M6DWireDrainPDO($result)),'wire_prearm_drain');
    foreach(['unarmed','before-commit','drop-ack'] as $mode)foreach(["\x03COMMIT","\x03COMMIT "] as $payload)foreach([1,3,65536] as $fragment) {
        $p=$new($mode); $command=M6CSupplementWire::packet($payload);
        if($mode!=='unarmed')$p->arm();
        foreach(str_split($command,$fragment) as $b)$p->feed('client',$b);
        $stream=fopen('php://memory','w+b');
        try {
            // Model backpressure and partial writes with the exact production queue helper.
            $before=$p->pending['server']; $p->write('server',static fn()=>0); $check($p->pending['server']===$before,'zero_write_preserves_queue');
            while($p->pending['server']!=='')$p->write('server',static fn($v)=>fwrite($stream,substr($v,0,2)));
            rewind($stream); $check(stream_get_contents($stream)===($mode==='before-commit' ? '' : $command),'original_command_bytes');
        } finally { fclose($stream); }
        if($mode!=='before-commit')foreach(str_split($ok,$fragment) as $b)$p->feed('server',$b);
        $check($p->pending['client']===($mode==='unarmed' ? $ok : ''),'response_forwarding_or_suppression');
        if($mode==='unarmed') { $check($p->wire->fault===null,'unarmed_no_fault'); $deny(fn()=>$p->arm(),'wire_arm_phase'); }
        else {
            $check($p->fault()===['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack','ack_suppressed'=>$mode==='drop-ack'],'one_fault_exact');
            $deny(fn()=>$p->arm(),'wire_arm_phase'); $deny(fn()=>$p->feed('client',$command),'wire_closed');
        }
    }
    $p=$new('drop-query'); $p->arm(); $p->feed('client',M6CSupplementWire::packet("\x17prepared-read"));
    $check($p->fault()['mode']==='drop-query' && $p->pending['server']==='','prepared_read_intercepted');
    foreach([0x800,0x20,1<<26,1<<27] as $flags) {
        $p=new M6DNativeWirePump('drop-ack'); $deny(fn()=>$p->feed('client',M6CSupplementWire::packet(pack('V',$flags).str_repeat("\0",28),1)),'wire_unsupported_capabilities');
    }
    foreach([M6CSupplementWire::packet("\xfferror",1),M6CSupplementWire::packet("\0",1),M6CSupplementWire::packet("\0\0\0\x01\0\0\0",1)] as $bad) {
        $p=$new('drop-ack'); $p->arm(); $p->feed('client',M6CSupplementWire::packet("\x03COMMIT")); $p->write('server',static fn($s)=>strlen($s));
        $deny(fn()=>$p->feed('server',$bad),'wire_commit_not_ok'); $deny(fn()=>$p->fault(),'wire_fault');
    }
    $p=$new('drop-ack'); $p->arm(); $deny(fn()=>$p->feed('client',M6CSupplementWire::packet("\x03SELECT 1")),'wire_expected_commit');
    foreach([1,3,6] as $n) { $p=$new('before-commit'); $p->arm(); $p->feed('client',substr(M6CSupplementWire::packet("\x03COMMIT"),0,$n)); $deny(fn()=>$p->fault(),'wire_fault'); }
    $p=$new('drop-ack'); $p->feed('client',M6CSupplementWire::packet("\x03SELECT 1")); $deny(fn()=>$p->arm(),'wire_arm_phase');
    $deny(fn()=>$p->write('server',static fn()=>false),'wire_write');

    // Real deployment service/audit seam over the existing sequential PDO model.
    $fake=new M6DDatabase(); $op=$fake->request(); $lease=$fake->claim()['lease']; $phases=[];
    $connection=new M6DNativeWireConnection($fake,$lease,'unarmed',static function($phase) use(&$phases,$fake,$lease,$check): void {
        $phases[]=$phase; $check($fake->inTransaction() && $fake->tables['site_deployments'][$lease['deployment_id']]['status']==='succeeded'
            && $fake->events('site_deployment_succeeded')===1,'actual_service_result_transaction_target');
    });
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$connection);
    $receipt=SiteDeploymentService::executeDeployment($lease); $check($phases===[],'activation_and_health_commits_not_selected');
    $done=SiteDeploymentService::completeDeploymentSuccess($lease,$receipt);
    $check($done['operation_status']==='succeeded' && $phases===['COMMIT'] && $connection->commitReturned,'real_inner_commit_invoked_once');
    $check(SiteDeploymentService::completeDeploymentSuccess($lease,$receipt)['operation_status']==='succeeded' && $phases===['COMMIT'],'history_no_second_arm');
    foreach(['before-commit','drop-ack'] as $mode) {
        $fake=new M6DDatabase(); $op=$fake->request(); $lease=$fake->claim()['lease']; $receipt=SiteDeploymentService::executeDeployment($lease);
        $raw=new M6DWireTestPDO($fake,$new($mode)); $boundaries=0;
        $c=new M6DNativeWireConnection($raw,$lease,$mode,static function($phase) use($raw,&$boundaries,$check): void {
            $check($phase==='COMMIT' && !$raw->armed,'modeled_final_barrier'); $boundaries++; $raw->pump->arm(); $raw->armed=true;
        });
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$c);
        try { SiteDeploymentService::completeDeploymentSuccess($lease,$receipt); throw new RuntimeException('unexpected_service_success'); }
        catch(SiteServiceException $e) { $check($e->classification()==='database_failure','actual_service_failure_returned'); }
        $check($boundaries===1 && $raw->selectedCommits===1 && !$c->commitReturned && $c->transportFailure===['sqlstate'=>'HY000','driver'=>2013],'inner_commit_failure_retained');
        $check($raw->pump->fault()['forwarded']===($mode==='drop-ack'),'modeled_fault_matches_database_model');
        $check($fake->tables['site_deployments'][$lease['deployment_id']]['status']===($mode==='drop-ack' ? 'succeeded' : 'health_check_pending')
            && $fake->events('site_deployment_succeeded')===($mode==='drop-ack' ? 1 : 0),'model_result_commit_distinction');
    }
    $fake=new M6DDatabase(); $op=$fake->request(); $lease=$fake->claim()['lease']; $phase=[];
    $connection=new M6DNativeWireConnection($fake,$lease,'drop-query',static function($p) use(&$phase): void { $phase[]=$p; });
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$connection);
    SiteDeploymentService::executeDeployment($lease); $check($phase===['READ'],'exact_first_service_prepared_site_read');
    foreach(['site_id','target_id','deployment_id','attempt_id'] as $key) {
        $fake=new M6DDatabase(); $op=$fake->request(); $lease=$fake->claim()['lease']; $receipt=SiteDeploymentService::executeDeployment($lease);
        $wrong=$lease; $wrong[$key]++; $called=false;
        $c=new M6DNativeWireConnection($fake,$wrong,'unarmed',static function() use(&$called): void { $called=true; });
        $fake->beginTransaction();
        $params=['site_id'=>$lease['site_id'],'event_type'=>'site_deployment_succeeded','metadata_json'=>json_encode(['target_id'=>$lease['target_id'],'deployment_id'=>$lease['deployment_id'],'attempt_id'=>$lease['attempt_id']])];
        $deny(fn()=>$c->afterStatement('INSERT INTO site_events',$params),'wire_result_target'); $fake->rollBack(); $check(!$called,'foreign_result_no_barrier');
    }
    // Every field of every required leg/authority observation is mandatory.
    foreach(M6DNativeWire::SUBCASES as $sub) {
        $e=m6dWireSyntheticEvidence($identity,$sub); $check(M6DNativeWireEvidence::valid($e,$sub,$identity,1),'synthetic_shape_valid');
        foreach($e as $key=>$v) { $bad=$e; unset($bad[$key]); $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'required_transport_'.$key); }
        foreach(['legs','authoritative'] as $group)foreach($e[$group] as $leg=>$v)foreach($v as $key=>$value) {
            $bad=$e; unset($bad[$group][$leg][$key]); $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'required_'.$group.'_'.$key);
        }
        foreach(['identity','site_id','target_id','deployment_id','attempt_id','channel','subcase','leg'] as $key) {
            $bad=$e; $bad['legs']['result']['binding'][$key]=null; $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'foreign_binding_'.$key);
        }
        foreach(['worker','relay'] as $child)foreach(['exit'=>null,'signaled'=>true,'signal'=>9,'running'=>true,'termination_requested'=>true] as $key=>$v) {
            $bad=$e; $bad['legs']['result'][$child][$key]=$v; $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'invalid_child_outcome');
        }
        $bad=$e; $bad['legs']['result']['sequence'][]='FAULT'; $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'duplicate_fault_rejected');
        $bad=$e; $bad['legs']['result']['drained_before_arm']=false; $check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'missing_prearm_drain');
        $bad=$e; $bad['legs']['result']['fault']=null; if($sub!=='unarmed-success')$check(!M6DNativeWireEvidence::valid($bad,$sub,$identity,1),'missing_fault_rejected');
    }
    $b=m6dWireSyntheticEvidence($identity,'ack-withheld')['legs']['result']['binding'];
    foreach(['pass','early','partial','wrong','phase','duplicate','release'] as $mode) {
        $p=new M6CNativeProcess([PHP_BINARY,__DIR__.'/support/WebsitePlatformM6DNativeWireSyntheticChild.php',$mode],5,16384);
        $p->diagnosticContext=['case'=>'result-commit-transport','subcase'=>'ack-withheld','leg'=>'result']; $p->send(json_encode($b));
        if(in_array($mode,['early','partial','wrong'],true)) {
            $deny(fn()=>M6DNativeWire::read($p,'LISTEN',$b),$mode==='wrong' ? 'wire_frame_binding' : 'native_child_before_barrier');
        } else {
            $v=M6DNativeWire::read($p,'LISTEN',$b); $check(M6DNativeWire::endpoint($v,$p->pid)===12345,'owned_endpoint');
            $p->send($mode==='release' ? 'WRONG' : 'ARM');
            if($mode==='phase' || $mode==='release')$deny(fn()=>M6DNativeWire::read($p,'ARMED',$b),$mode==='phase' ? 'wire_frame_binding' : 'native_child_before_barrier');
            else {
                $check(M6DNativeWire::read($p,'ARMED',$b)===['mode'=>'drop-ack'],'synthetic_armed'); $p->send('CONTINUE');
                $check(M6DNativeWire::read($p,'FAULT',$b)['ok_received']===true,'synthetic_fault');
                if($mode==='duplicate')$deny(fn()=>M6DNativeWire::finish($p),'wire_extra_output'); else $check(M6DNativeWire::finish($p)['exit']===0,'synthetic_exact_completion');
            }
        }
        M6CNativeProcess::observeFailure(); $report=['primary_failure'=>['code'=>'original']];
        M6DNativeControl::cleanup($report,true,static fn()=>M6CNativeProcess::cleanup(),static function(): void {});
        $check($report['primary_failure']===['code'=>'original'] && $report['children_cleanup']==='PASS','failure_retained_owned_children_reaped');
    }
    foreach([['relay_pid'=>1,'port'=>12345],['relay_pid'=>2,'port'=>3306,'host'=>'remote'],['relay_pid'=>2,'port'=>65536],['relay_pid'=>2,'port'=>'12345']] as $bad)$deny(fn()=>M6DNativeWire::endpoint($bad,2),'wire_endpoint');
    foreach([['password'=>'PRIVATE'],['value'=>"\x1bPRIVATE"],['value'=>"x\ny"]] as $v)$deny(fn()=>M6DNativeWire::decode(M6DNativeWire::frame('FAULT',$b,$v),'FAULT',$b),isset($v['password']) ? 'child_private_key' : 'child_private_value');
    $valid=M6DNativeWire::frame('ARMED',$b,['mode'=>'drop-ack']);
    $deny(fn()=>M6DNativeWire::decode(str_replace('"mode":"drop-ack"','"mode":"before-commit","mode":"drop-ack"',$valid),'ARMED',$b),'wire_frame_binding');
    $a=['database_name'=>'ubo_m6d_test','database_hostname'=>'m6d-db-test','mysql_server_uuid'=>'safe-uuid','database_user'=>'m6d_test'];
    $backend=['db'=>'ubo_m6d_test','host'=>'m6d-db-test','uuid'=>'safe-uuid','version'=>'8.4.0','account'=>'m6d_test@internal'];
    M6DNativeWire::backend($backend,$a); $check(true,'backend_identity_same_route');
    foreach(array_keys($backend) as $key) { $wrong=$backend; $wrong[$key]='foreign'; $deny(fn()=>M6DNativeWire::backend($wrong,$a),'wire_mysql_identity'); }
    foreach(['WebsitePlatformM6DNativeRelay.php','WebsitePlatformM6DNativeWireWorker.php'] as $file) {
        $r=M6CNativeProcess::capture([PHP_BINARY,__DIR__.'/support/'.$file,'--help'],5,8192);
        $check($r['exit']===0 && $r['stderr']==='' && str_contains($r['stdout'],'native execution receipt required'),'help_before_bootstrap');
        if(PHP_OS_FAMILY==='Windows') {
            $r=M6CNativeProcess::capture([PHP_BINARY,__DIR__.'/support/'.$file,$identity['source_sha'],$identity['image_id'],$identity['run_token'],'1','ack-withheld','result'],5,8192);
            $error=json_decode(trim($r['stderr']),true);
            $check($r['exit']===1 && $r['stdout']==='' && $error===['class'=>'RuntimeException','code'=>'linux_isolated_worker'],'windows_denied_before_credentials_or_socket');
        }
    }
    $safe=M6DNativeWire::process(['pid'=>1,'exit'=>-1,'signaled'=>true,'signal'=>9,'termination_requested'=>true,'running'=>false]);
    $check($safe['exit']===null && $safe['signaled'] && $safe['signal']===9,'unknown_exit_not_success_signal_separate');
} finally { M6CNativeProcess::cleanup(); }
echo "M6D native wire: $assertions assertions PASS (synthetic packets/streams, fake PDO and bounded children; NO network/database).\n";
