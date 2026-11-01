<?php
declare(strict_types=1);

/** Explicitly synthetic accepted-shape examples for validator mutations. Never native evidence. */
function m6dWireSyntheticEvidence(array $identity,string $sub): array
{
    $names=$sub==='authoritative-unavailable' ? ['result','reread'] : ['result']; $legs=[]; $authoritative=[];
    foreach($names as $i=>$leg) {
        $mode=$leg==='reread' ? 'drop-query' : match($sub) {'unarmed-success'=>'unarmed','commit-not-delivered'=>'before-commit',default=>'drop-ack'};
        $b=['identity'=>$identity,'case'=>'result-commit-transport','subcase'=>$sub,'leg'=>$leg,'site_id'=>1,'target_id'=>2,'deployment_id'=>3,'attempt_id'=>4,'channel'=>str_repeat((string)($i+1),32)];
        $process=['pid'=>100+$i*2,'exit'=>0,'signaled'=>false,'signal'=>0,'termination_requested'=>false,'running'=>false];
        $phase=$leg==='reread' ? 'READ' : 'COMMIT'; $committed=$sub!=='commit-not-delivered';
        $legs[$leg]=['binding'=>$b,'mode'=>$mode,'endpoint'=>['relay_pid'=>$process['pid']+1,'port'=>12345],'connection'=>10+$i,'backend_verified'=>true,'drained_before_arm'=>true,
            'sequence'=>$mode==='unarmed' ? ['LISTEN','WORKER','READY',$phase,'CONTINUE','OUTCOME','STOPPED'] : ['LISTEN','WORKER','READY',$phase,'ARMED','CONTINUE','FAULT','OUTCOME'],
            'fault'=>$mode==='unarmed' ? null : ['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack','ack_suppressed'=>$mode==='drop-ack'],
            'outcome'=>$mode==='unarmed' ? ['returned'=>'succeeded','error'=>null,'commit_returned'=>true,'transport_failure'=>null]
                : ['returned'=>null,'error'=>['class'=>'SiteServiceException','classification'=>'database_failure','safe'=>['class'=>'SiteServiceException','code'=>'native_failure']],
                    'commit_returned'=>false,'transport_failure'=>['sqlstate'=>'HY000','driver'=>2013]],
            'worker'=>$process,'relay'=>array_replace($process,['pid'=>$process['pid']+1]),'last_barrier'=>['worker'=>'OUTCOME','relay'=>$mode==='unarmed' ? 'STOPPED' : 'FAULT'],
            'model_unchanged_after_boundary'=>true,'effects'=>['stage'=>1,'activate'=>1,'restore'=>0,'compensate'=>0,'health'=>3]];
        $authoritative[$leg]=['connection'=>20+$i,'before_replay'=>true,'state_sha256'=>str_repeat('a',64),'status'=>$committed ? 'succeeded' : 'health_check_pending',
            'committed'=>$committed,'success_events'=>$committed ? 1 : 0,'active_health'=>$committed ? 2 : 0,'pointer_committed'=>$committed,'lifecycle_committed'=>$committed,
            'slot_settled'=>$committed,'activation_retained'=>true,'grant_retained'=>true,'execution_count'=>3,'attempt_count'=>3,'recovery_count'=>0];
    }
    return ['legs'=>$legs,'authoritative'=>$authoritative,
        'unknown'=>$sub==='authoritative-unavailable' ? ['read_intercepted'=>true,'service_returned'=>false,'database_unchanged'=>true,'model_unchanged'=>true] : null,
        'history'=>$sub==='ack-withheld' ? ['later_deployment'=>5,'old_success_retained'=>true,'no_effects'=>true,'grant_changed'=>true,'actor_denied'=>true,'authorized_history'=>true] : null,
        'recovery'=>$sub==='commit-not-delivered' ? ['original_attempt'=>4,'recovery_attempt'=>5,'attempt_count'=>4,'execution_count'=>3,'recovery_count'=>1,'automatic_recovery_count'=>1,
            'stale_owner_denied'=>true,'new_candidate_effects'=>0,'success_events'=>1] : null,'final_disposition'=>'succeeded'];
}
