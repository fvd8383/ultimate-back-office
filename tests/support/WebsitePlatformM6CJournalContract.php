<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeContract.php';
require_once __DIR__.'/WebsitePlatformM6CJournalFault.php';

/** Three additional cases; neither the core20 nor the existing supplement17 is replaced. */
final class M6CJournalContract
{
    public const VERSION='m6c-native-journal-v1';
    public const CASES=['producer-journal-flush-failure','producer-journal-fsync-failure','producer-journal-publication-failure'];
    public static function initial(string $sha,string $token):array
    {
        return ['version'=>self::VERSION,'native'=>true,'sha'=>$sha,'token'=>$token,'status'=>'FAIL','stage'=>'attestation',
            'assertions'=>0,'completed_assertions'=>0,'cases'=>array_fill_keys(self::CASES,['status'=>'UNREACHED']),
            'children'=>[],'limitations'=>['Injected PHP API false return, not a failed kernel syscall or power-loss durability test.',
                'Recovery due timestamp is controlled; no elapsed-time claim.',
                'Separate instrumented journal profile; does not satisfy core20 or supplement17 acceptance.']];
    }
    public static function need(bool $ok):void{if(!$ok)throw new RuntimeException('journal_invalid_evidence');}
    public static function envelope(array $r,string $sha,string $token):void
    {
        self::need(M6CNativeContract::hex($sha,40)&&M6CNativeContract::hex($token,32)
            &&($r['version']??null)===self::VERSION&&($r['sha']??null)===$sha&&($r['token']??null)===$token
            &&($r['native']??null)===true&&in_array($r['status']??null,['PASS','FAIL'],true)
            &&is_int($r['assertions']??null)&&$r['assertions']>=0&&is_array($r['cases']??null)
            &&array_keys($r['cases'])===self::CASES);
        foreach($r['cases'] as $c)self::need(is_array($c)&&in_array($c['status']??null,['PASS','FAIL','UNREACHED'],true));
    }
    public static function decode(string $bytes,string $sha,string $token):array
    {
        self::need(strlen($bytes)>0&&strlen($bytes)<=1048576);$r=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        self::need(is_array($r)&&$bytes===json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));self::envelope($r,$sha,$token);return $r;
    }
    public static function observation(array $v,string $operation,string $token):void
    {
        self::need(array_keys($v)===['operation','target_role','run','target_digest','producer','calls','accepted','bytes','matched','injected','off_target','complete']
            &&$v['operation']===$operation&&$v['target_role']==='producer_pending_journal'&&$v['run']===$token
            &&M6CNativeContract::hex($v['target_digest'],64)&&is_int($v['producer'])&&$v['producer']>0
            &&$v['complete']===true&&is_int($v['bytes'])&&$v['bytes']>0&&$v['bytes']<=4096
            &&is_int($v['off_target'])&&$v['off_target']>=7&&$v['off_target']<=4096);
        $calls=match($operation){'flush'=>[1,1,1,0,1,0,0],'fsync'=>[1,1,1,1,1,0,0],default=>[1,1,1,1,1,1,1]};
        $accepted=$calls;$index=match($operation){'flush'=>2,'fsync'=>3,'publication'=>6,default=>null};
        if($index!==null)$accepted[$index]=0;
        self::need($v['calls']===array_combine(M6CJournalFault::CALLS,$calls)&&$v['accepted']===array_combine(M6CJournalFault::CALLS,$accepted)
            &&$v['matched']===array_sum($calls)&&$v['injected']===($operation==='control'?0:1));
    }
    public static function evidence(array $r,string $sha,string $token):void
    {
        self::envelope($r,$sha,$token);self::need($r['status']==='PASS'&&($r['completed_assertions']??null)===$r['assertions']);$sum=0;
        foreach(self::CASES as $i=>$name){$c=$r['cases'][$name];$operation=['flush','fsync','publication'][$i];
            self::need(($c['status']??null)==='PASS'&&($c['lane']??null)==='instrumented'&&is_int($c['assertions']??null)&&$c['assertions']>=12
                &&($c['evidence_level']??null)==='injected_php_api_return'&&is_array($c['fault']??null)&&is_array($c['control']??null));
            self::observation($c['fault'],$operation,$token);self::observation($c['control'],'control',$token);
            self::need($c['fault']['target_digest']!==$c['control']['target_digest']
                &&($c['caller_error']??null)===($operation==='publication'?'outcome_unknown':'storage_unavailable')
                &&($c['state']??null)===['pending'=>true,'journal'=>false,'candidate'=>false,'release'=>false,'inspection'=>'unknown',
                    'job'=>'reconciliation_required','recovery'=>'blocked','attempts'=>2,'executions'=>1,'recoveries'=>1,'builds'=>1,
                    'validations'=>0,'success_events'=>0,'pending_unchanged'=>true,'cleanup'=>'owned_controls_removed']
                &&is_int($c['recovery_attempt']??null)&&$c['recovery_attempt']>0&&$c['recovery_attempt']!==$c['fault']['producer']
                &&($c['control_success']??null)===true);$sum+=$c['assertions'];
        }
        self::need($r['assertions']>=$sum&&is_array($r['children']??null)&&array_is_list($r['children'])&&count($r['children'])===9);
        foreach($r['children'] as $i=>$c){$caseIndex=intdiv($i,3);$mode=['control',['flush','fsync','publication'][$caseIndex],'recover'][$i%3];
            self::need(is_array($c)&&($c['journal_case']??null)===self::CASES[$caseIndex]&&($c['mode']??null)===$mode
                &&($c['unexpected']??true)===false&&($c['exit']??null)===0&&($c['running']??null)===false&&($c['termination_requested']??null)===false);
        }
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{if(array_slice($argv,1)===['--help']){echo "Pure three-case journal contract; no runtime or helper load.\n";exit(0);}
        if(count($argv)!==4||$argv[1]!=='evidence')throw new RuntimeException('journal_arguments');
        $r=M6CJournalContract::decode(stream_get_contents(STDIN,1048577),$argv[2],$argv[3]);
        M6CJournalContract::evidence($r,$argv[2],$argv[3]);echo "accepted\n";
    }catch(Throwable){fwrite(STDERR,"M6C journal: invalid_evidence\n");exit(2);}
}
