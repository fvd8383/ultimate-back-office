<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeContract.php';

/** Fixed supplemental inventory, never an extension of m6c-native-v1. No bootstrap. */
final class M6CSupplementContract
{
    public const VERSION='m6c-native-supplement-v1';
    public const CASES=[
        'acceptance-revocation-first','acceptance-success-first','third-execution-adoption',
        'third-revoked-recovery-restart','third-deleted-inspection','automatic-budget-operator',
        'completion-not-committed','completion-ack-dropped','authoritative-outcome-unavailable',
        'candidate-process-kill','proof-process-kill','artifact-write-permission',
        'artifact-space-exhaustion','artifact-rename-permission','foreign-target-collision',
        'filesystem-admission','nonmaterial-approved-pdf',
    ];
    public static function version(string $selection):string
    {
        return match($selection){'core'=>M6CNativeContract::VERSION,'supplement-v1'=>self::VERSION,
            default=>throw new RuntimeException('supplement_profile')};
    }
    public static function initial(string $sha,string $token):array
    {
        return ['version'=>self::VERSION,'native'=>true,'sha'=>$sha,'token'=>$token,'status'=>'FAIL',
            'stage'=>'attestation','assertions'=>0,'completed_assertions'=>0,
            'cases'=>array_fill_keys(self::CASES,['status'=>'UNREACHED']), 'children'=>[],
            'limitations'=>['Instrumented real service/files/PDO; not protected factory coverage.',
                'Controlled expiry/backoff is not elapsed-time proof; process death is not power-loss proof.',
                'Real flush/fsync and producer-journal publication failure injection remain unimplemented; this inventory does not close every G3 item.']];
    }
    public static function envelope(array $r,string $sha,string $token):void
    {
        M6CNativeContract::need(M6CNativeContract::hex($sha,40)&&M6CNativeContract::hex($token,32)
            &&($r['version']??null)===self::VERSION&&($r['sha']??null)===$sha&&($r['token']??null)===$token,'supplement_identity');
        M6CNativeContract::need(($r['native']??null)===true&&in_array($r['status']??null,['PASS','FAIL'],true)
            &&is_int($r['assertions']??null)&&$r['assertions']>=0,'supplement_result');
        M6CNativeContract::need(array_keys($r['cases']??[])===self::CASES,'supplement_inventory');
        foreach($r['cases'] as $c)M6CNativeContract::need(is_array($c)
            &&in_array($c['status']??null,['PASS','FAIL','UNREACHED'],true),'supplement_case_status');
    }
    public static function decode(string $bytes,string $sha,string $token):array
    {
        M6CNativeContract::need(strlen($bytes)>0&&strlen($bytes)<=1048576,'supplement_size');
        $r=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        // The shared writer has ONE specified serialization. This rejects duplicate JSON
        // members (including escaped spellings), which json_decode alone would discard.
        M6CNativeContract::need(is_array($r)&&$bytes===json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),'supplement_serialization');
        self::envelope($r,$sha,$token);return $r;
    }
    public static function evidence(array $r,string $sha,string $token):void
    {
        self::envelope($r,$sha,$token);
        M6CNativeContract::need($r['status']==='PASS'&&$r['assertions']>0
            &&($r['completed_assertions']??null)===$r['assertions'],'supplement_incomplete');
        $sum=0;
        foreach($r['cases'] as $c){
            M6CNativeContract::need($c['status']==='PASS'&&($c['lane']??null)==='instrumented'
                &&is_int($c['assertions']??null)&&$c['assertions']>0
                &&($c['observed']??null)===true,'supplement_case_incomplete');
            $sum+=$c['assertions'];
        }
        M6CNativeContract::need($sum<=$r['assertions'],'supplement_accounting');
        M6CNativeContract::need(is_array($r['children']??null)&&array_is_list($r['children']),'supplement_children');
        foreach($r['children'] as $child)M6CNativeContract::need(is_array($child)&&($child['unexpected']??true)===false,'supplement_child_failure');
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{
        if(array_slice($argv,1)===['--help']){echo "Pure supplemental contract; evidence SHA TOKEN on stdin. No SQL or runtime.\n";exit(0);}
        if(count($argv)!==4||$argv[1]!=='evidence')throw new RuntimeException('supplement_arguments');
        $bytes=stream_get_contents(STDIN,1048577);$r=M6CSupplementContract::decode($bytes,$argv[2],$argv[3]);
        M6CSupplementContract::evidence($r,$argv[2],$argv[3]);echo "accepted\n";
    }catch(Throwable){fwrite(STDERR,"M6C supplement: invalid_evidence\n");exit(2);}
}
