<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DFixture.php';

/** Test-only memory transport. Real session serialization; no socket/admitted runtime. */
final class M6EWireStream
{
    public mixed $context;
    public static string $written='';
    public static int $writes=0;
    public static int $reads=0;
    private string $reply='';
    private int $offset=0;
    public function stream_open(string $path,string $mode,int $options,?string &$opened):bool
    {self::$written='';self::$writes=0;self::$reads=0;return true;}
    public function stream_write(string $data):int
    {$part=substr($data,0,7);self::$written.=$part;self::$writes++;return strlen($part);}
    public function stream_read(int $count):string
    {
        if($this->reply===''){
            $request=json_decode(self::$written,true,512,JSON_THROW_ON_ERROR);
            $this->reply=($request['operation']==='stage'?CanonicalJson::encode(['version'=>1,'type'=>'transfer_ready','sequence'=>$request['sequence'],
                'transfer_version'=>1,'transfer_id'=>str_repeat('a',32),'disposition'=>'retained'])."\n":'').CanonicalJson::encode(['version'=>1,'sequence'=>$request['sequence'],'result'=>'ok','evidence'=>['fixture'=>true]])."\n";
        }
        self::$reads++;$part=substr($this->reply,$this->offset,min(5,$count));$this->offset+=strlen($part);return $part;
    }
    public function stream_eof():bool {return $this->reply!==''&&$this->offset===strlen($this->reply);}
    public function stream_stat():array {return [];}
    public function stream_set_option(int $option,int $arg1,int $arg2):bool {return true;}
}
stream_wrapper_register('m6ewire',M6EWireStream::class);
$db=new M6DDatabase();$db->request();$claim=$db->claim();$lease=$claim['lease'];
$target=&$db->tables['site_deployment_targets'][20];$target['publisher_key']='apache-digitalocean-v1';
$entry=(new ReflectionMethod(SiteDeploymentService::class,'entry'))->invoke(null,$lease);
$t=SiteDeploymentContract::target($target);
$binding=['site_id'=>$t['site_id'],'target_id'=>$t['target_id'],'binding_key'=>$t['binding_key'],'binding_version'=>$t['binding_version']];
$class=new ReflectionClass(SitePublisherHostContract::class);$host=$class->newInstanceWithoutConstructor();
$class->getConstructor()->invoke($host,['worker_id'=>$lease['worker_id'],'bindings'=>[$binding]]);
$context=static fn(array $e,string $phase,?array $owned=null):array=>(new ReflectionMethod(SiteDeploymentService::class,'context'))->invoke(null,$e,$owned??$lease,$phase);
$expected=static fn(bool $previous):array=>(new ReflectionMethod(SiteDeploymentService::class,'expected'))->invoke(null,$entry,$previous);
$stageContext=$context($entry,'stage');
$staged=SiteDeploymentContract::receipt($db->runtime->publisher->stageRelease(SiteDeploymentContract::release($entry['release'],$entry['site']),$t,$stageContext),$stageContext,'stage');
$observed=SiteDeploymentContract::observation($db->runtime->publisher->inspectCurrentRelease($t));
$cases=[];
$emit=static function(string $label,string $operation,array $payload=[],?array $owned=null)use(&$cases,$host,$lease,$t):void {
    $session=new SitePublisherSession($host);$session->bind($owned??$lease);
    $stream=fopen('m6ewire://fixture','r+');
    (new ReflectionProperty(SitePublisherSession::class,'stream'))->setValue($session,$stream);
    $result=$session->request($operation,$t,$payload);
    if($result!==['fixture'=>true])throw new RuntimeException('memory_reply');
    $cases[]=['label'=>$label,'wire'=>M6EWireStream::$written,'writes'=>M6EWireStream::$writes,'reads'=>M6EWireStream::$reads];
    $session->close();
};
$emit('inspect','inspect');$emit('settle','settle');
$release=SiteDeploymentContract::release($entry['release'],$entry['site']);
$emit('stage','stage',['release'=>$release,'context'=>$stageContext]);
$emit('activate','activate',['staged'=>$staged,'expected'=>$expected(true),'context'=>$context($entry,'activation')]);
$restore=$entry;$restore['operation']['operation']='restore';
$emit('restore','restore',['staged'=>$staged,'expected'=>$expected(true),'context'=>$context($restore,'activation')]);
$emit('probe','probe',['expected'=>$expected(false),'context'=>$context($entry,'active')]);
$emit('probe-absent','probe',['expected'=>$expected(true),'context'=>$context($entry,'rollback')]);
foreach(['uuid'=>'11111111-1111-4111-8111-111111111111','custom'=>'Trace:Batch_17.a-Z','max'=>str_repeat('A',100)] as $name=>$value){
    $e=$entry;$e['operation']['correlation_id']=SiteServiceSupport::correlationId($value);
    $emit('correlation-'.$name,'stage',['release'=>$release,'context'=>$context($e,'stage')]);
}
$db->runtime->publisher->unknown=true;
SiteDeploymentService::executeDeployment($lease);$db->advance(121);
$recovery=SiteDeploymentService::claimDeploymentRecovery($lease['deployment_id'],[])['lease'];
$re=(new ReflectionMethod(SiteDeploymentService::class,'entry'))->invoke(null,$recovery);
$db->runtime->publisher->unknown=false;
$observed=SiteDeploymentContract::observation($db->runtime->publisher->inspectCurrentRelease($t));
$emit('compensate','compensate',['intent'=>['action'=>'compensate','original_execution_id'=>(int)$re['producer']['id'],
    'observed'=>$observed,'expected_previous'=>$expected(true),'activation_fence'=>(int)$re['operation']['activation_fence']],
    'context'=>$context($re,'compensation',$recovery)],$recovery);
// Type preservation belongs to serialization, not acceptance of extra protocol fields.
$emit('types-serializer-only','probe',['context'=>$stageContext,'fixture_types'=>[1,1.25,true,false,null,'1',[],['n'=>null]]]);
$session=new SitePublisherSession($host);$session->bind($lease);
(new ReflectionProperty(SitePublisherSession::class,'stream'))->setValue($session,fopen('m6ewire://fixture','r+'));
$oversize=false;
try{$session->request('probe',$t,['oversize'=>str_repeat('x',16384)]);}
catch(SiteServiceException $e){$oversize=$e->classification()==='invalid_request'&&M6EWireStream::$written==='';}
$session->close();
echo json_encode(['profile'=>'m6e-wire-fixture-v1','cases'=>$cases,'default_correlation'=>$entry['operation']['correlation_id'],
    'oversize_denied_before_write'=>$oversize],JSON_THROW_ON_ERROR)."\n";
