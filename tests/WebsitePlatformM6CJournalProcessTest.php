<?php
declare(strict_types=1);
// Real bounded children delivering explicitly synthetic journal packets. No native helper loaded.
require_once __DIR__.'/support/WebsitePlatformM6CJournalChannel.php';
require_once __DIR__.'/support/WebsitePlatformM6CJournalSynthetic.php';
$checks=0;$token=str_repeat('b',32);$case=M6CJournalContract::CASES[0];
function jp(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
try{
    foreach(['valid','early','timeout','wrong','duplicate','lost','wrong-run','load-failure'] as $scenario){
        $v=journalSyntheticPacket();$prefix=[];
        if($scenario==='wrong')$v['caller_error']="SECRET-CANARY\x1b\n";
        if($scenario==='wrong-run')$v['observation']['run']=str_repeat('a',32);
        if($scenario==='lost')unset($v['observation']);
        if($scenario==='duplicate')$v['observation']['injected']=2;
        $line='JOURNAL '.json_encode($v,JSON_THROW_ON_ERROR)."\n";
        $code='echo '.var_export($line,true).';';
        if($scenario==='early')$code='fwrite(STDERR,"SECRET-CANARY\\n");exit(7);';
        if($scenario==='timeout')$code='usleep(3000000);'; // Deadline regression only, never boundary evidence.
        if($scenario==='load-failure'){$prefix=['-d','extension=missing-journal-SECRET-CANARY'];$code='exit(7);';}
        $p=new M6CNativeProcess([PHP_BINARY,'-n',...$prefix,'-r',$code],$scenario==='timeout'?1:5);$p->closeInput();$r=['children'=>[]];$caught=null;
        try{$result=M6CJournalChannel::result($p,$r,$case,'flush',$token);}catch(Throwable $e){$caught=$e;}
        jp(($caught===null)===($scenario==='valid'),'expected classification '.$scenario);
        jp(count($r['children'])===1&&$r['children'][0]['unexpected']===($scenario!=='valid'),'failure evidence '.$scenario);
        jp(!str_contains(json_encode($r),'SECRET-CANARY'),'private diagnostic '.$scenario);
        foreach(M6CNativeProcess::cleanup() as $entry)jp(!$entry['running'],'owned child cleanup');
    }
    $p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CJournalWorker.php'],5);$p->closeInput();$r=['children'=>[]];$caught=false;
    try{M6CJournalChannel::result($p,$r,$case,'flush',$token);}catch(Throwable){$caught=true;}
    jp($caught&&$r['children'][0]['unexpected']&&$r['children'][0]['exit']===1,'unsupported context cannot pass');
    $primary=new RuntimeException('original');$r=['children'=>[]];$diagnostics=0;
    try{M6CJournalFault::primary(static fn()=>throw $primary,static function()use(&$diagnostics):void{$diagnostics++;throw new RuntimeException("SECRET-CANARY");});}
    catch(Throwable $e){jp($e===$primary&&$diagnostics===1,'secondary diagnostic failure preserves exact primary');}
    echo "M6C journal process: $checks assertions passed; real local children, synthetic packets; Linux helper NOT loaded.\n";
}finally{foreach(M6CNativeProcess::cleanup() as $entry)jp(!$entry['running'],'final child cleanup');}
