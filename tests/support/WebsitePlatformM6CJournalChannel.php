<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CJournalContract.php';
require_once __DIR__.'/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/WebsitePlatformM6CNativeDiagnostics.php';

final class M6CJournalChannel
{
    public static function payload(array $v,string $mode,string $token):void
    {
        M6CJournalContract::need(in_array($mode,[...M6CJournalFault::OPERATIONS,'recover'],true)&&($v['mode']??null)===$mode
            &&is_int($v['job']??null)&&$v['job']>0&&is_int($v['producer']??null)&&$v['producer']>0);
        if($mode==='recover'){
            M6CJournalContract::need(array_keys($v)===['mode','job','producer','recovery_attempt','status','disposition','builds']
                &&is_int($v['recovery_attempt'])&&$v['recovery_attempt']>0&&$v['recovery_attempt']!==$v['producer']
                &&$v['status']==='reconciliation_required'&&$v['disposition']==='blocked'&&$v['builds']===0);return;
        }
        M6CJournalContract::need(array_keys($v)===['mode','job','producer','observation','caller_error','status','inspection','pending_digest','builds','cleanup']
            &&is_array($v['observation'])&&$v['observation']['producer']===$v['producer']&&$v['builds']===1&&$v['cleanup']==='owned_controls_removed');
        M6CJournalContract::observation($v['observation'],$mode,$token);
        M6CJournalContract::need($v['caller_error']===match($mode){'control'=>null,'publication'=>'outcome_unknown',default=>'storage_unavailable'}
            &&$v['status']===($mode==='control'?'succeeded':'reconciliation_required')&&$v['inspection']===($mode==='control'?'sealed':'unknown')
            &&($mode==='control'?$v['pending_digest']===null:M6CNativeContract::hex($v['pending_digest'],64)));
    }
    public static function result(M6CNativeProcess $p,array &$report,string $case,string $mode,string $token):array
    {
        if(!in_array($case,M6CJournalContract::CASES,true))throw new RuntimeException('journal_case');
        try{
            $line=$p->line(30);if(strlen($line)>4104||!str_starts_with($line,'JOURNAL '))throw new RuntimeException('journal_packet');
            $v=json_decode(substr($line,8),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($v)||$line!=='JOURNAL '.json_encode($v,JSON_THROW_ON_ERROR))throw new RuntimeException('journal_packet');
            self::payload($v,$mode,$token);$actual=$p->finish();
            if($actual['exit']!==0||$actual['running']||$actual['termination_requested']||$actual['signaled'])throw new RuntimeException('journal_child_exit');
            $report['children'][]=['journal_case'=>$case,'mode'=>$mode,'unexpected'=>false]+M6CNativeDiagnostics::summary($actual);return $v;
        }catch(Throwable $e){
            M6CNativeProcess::observeFailure();
            try{$report['children'][]=['journal_case'=>$case,'mode'=>$mode,'unexpected'=>true]+M6CNativeDiagnostics::summary($p->observation());}
            catch(Throwable){$report['journal_secondary_diagnostic']='FAIL';}throw $e;
        }
    }
}
