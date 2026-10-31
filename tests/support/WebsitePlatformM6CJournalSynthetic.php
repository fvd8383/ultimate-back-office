<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CJournalContract.php';
/** Local test inputs ONLY. These functions do not load or impersonate a working native helper. */
function journalSyntheticObservation(string $mode,int $producer=1,string $token='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'):array
{
    $job='aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';$policy=new M6CJournalFault($mode,$token,$job,$producer);
    $trace=match($mode){'flush'=>['fopen','fwrite','fflush','fclose'],'fsync'=>['fopen','fwrite','fflush','fsync','fclose'],default=>M6CJournalFault::CALLS};
    $target=match($mode){'flush'=>'fflush','fsync'=>'fsync','publication'=>'rename',default=>null};
    foreach($trace as $op){
        if($policy->observe($op,'before',false,0)!==($op===$target))throw new RuntimeException('synthetic_policy_decision');
        $policy->observe($op,'after',$op!==$target,$op==='fwrite'?128:0);
    }
    return $policy->evidence(['run'=>$token,'job'=>$job,'producer'=>$producer,'matched'=>count($trace),
        'injected'=>$mode==='control'?0:1,'off_target'=>7]);
}
function journalSyntheticReport(string $sha,string $token):array
{
    $r=M6CJournalContract::initial($sha,$token);$r['status']='PASS';$r['assertions']=$r['completed_assertions']=36;
    foreach(M6CJournalContract::CASES as $i=>$name){$op=['flush','fsync','publication'][$i];
        $r['cases'][$name]=['status'=>'PASS','lane'=>'instrumented','assertions'=>12,'evidence_level'=>'injected_php_api_return',
            'fault'=>journalSyntheticObservation($op,1,$token),'control'=>journalSyntheticObservation('control',2,$token),
            'caller_error'=>$op==='publication'?'outcome_unknown':'storage_unavailable','recovery_attempt'=>3,'control_success'=>true,
            'state'=>['pending'=>true,'journal'=>false,'candidate'=>false,'release'=>false,'inspection'=>'unknown',
                'job'=>'reconciliation_required','recovery'=>'blocked','attempts'=>2,'executions'=>1,'recoveries'=>1,'builds'=>1,
                'validations'=>0,'success_events'=>0,'pending_unchanged'=>true,'cleanup'=>'owned_controls_removed']];
        foreach(['control',$op,'recover'] as $mode)$r['children'][]=['journal_case'=>$name,'mode'=>$mode,'unexpected'=>false,
            'exit'=>0,'running'=>false,'termination_requested'=>false];
    }return $r;
}
function journalSyntheticPacket(string $mode='flush'):array
{
    return ['mode'=>$mode,'job'=>1,'producer'=>1,'observation'=>journalSyntheticObservation($mode),'caller_error'=>$mode==='control'?null:($mode==='publication'?'outcome_unknown':'storage_unavailable'),
        'status'=>$mode==='control'?'succeeded':'reconciliation_required','inspection'=>$mode==='control'?'sealed':'unknown',
        'pending_digest'=>$mode==='control'?null:str_repeat('a',64),'builds'=>1,'cleanup'=>'owned_controls_removed'];
}
