<?php
declare(strict_types=1);
/** Stage A only. This file is never loaded by an operational entry point. */
require_once __DIR__.'/WebsitePlatformM6ECommissionServing.php';
require_once dirname(__DIR__,2).'/private/classes/SitePublisherTransfer.php';

function m6eComponentLine():array {
    $line=fgets(STDIN,2097153);m6eNeed(is_string($line)&&str_ends_with($line,"\n"),'component_control');
    return SiteBuildContract::decode($line,2097152);
}
function m6eComponentEmit(string $kind,array $value):void {
    $raw=CanonicalJson::encode(['kind'=>$kind,'pid'=>getmypid(),'data'=>$value]);
    m6eNeed(strlen($raw)<2097152,'component_output');fwrite(STDOUT,$raw."\n");fflush(STDOUT);
}
function m6eComponentSend(mixed $stream,LocalSiteArtifactStore $store,string $root,array $source,int $uid,array $plan):array {
    m6eNeed(CanonicalJson::encode($source['release'])===CanonicalJson::encode($plan['release'])
        &&CanonicalJson::encode($source['summary'])===CanonicalJson::encode($plan['summary']),'component_original_source');
    $deadline=hrtime(true)+30000000000;
    $io=new SitePublisherTransfer($stream,$deadline,str_repeat('0',32),1);
    $line=static function()use($io):array {
        $raw='';do{$raw.=$io->read(1);m6eNeed(strlen($raw)<=65536,'component_reply_bound');}while(!str_ends_with($raw,"\n"));
        return SiteBuildContract::decode($raw,65536);
    };
    $io->write(CanonicalJson::encode($plan['request'])."\n");$ready=$line();
    SiteDeploymentContract::keys($ready,['version','type','sequence','transfer_version','transfer_id','disposition']);
    m6eNeed($ready['version']===1&&$ready['type']==='transfer_ready'&&$ready['sequence']===1&&$ready['transfer_version']===1
        &&preg_match('/^[a-f0-9]{32}$/D',$ready['transfer_id'])===1&&$ready['disposition']==='receive','component_no_replay');
    $exporter=new Ubo\M6E\SitePublisherArtifactExport($store,$root,$uid);
    (new SitePublisherTransfer($stream,$deadline,$ready['transfer_id'],1))->send($exporter,$source['release']);
    $ack=$line();SiteDeploymentContract::keys($ack,['profile','namespace_sha256','manifest_sha256','artifact_sha256','stage_sha256']);
    m6eNeed($ack['profile']==='m6e-release-a-component-v1'&&$ack['namespace_sha256']===hash('sha256',CanonicalJson::encode($plan['paths']))
        &&$ack['manifest_sha256']===$source['release']['manifest_hash']&&$ack['artifact_sha256']===$source['release']['artifact_hash']
        &&preg_match('/^[a-f0-9]{64}$/D',$ack['stage_sha256'])===1,'component_ack');
    return $ack;
}
function m6eComponentWorker(array $admission):void {
    m6eNeed(m6eComponentLine()===['command'=>'inspect'],'component_start');
    $base='/run/ubo-m6e-fixture/component-a-'.$admission['run_id'];
    $plan=SiteBuildContract::decode(SitePublisherHostContract::protectedFile($base.'/plan.json',1048576),1048576);
    $fixture=SiteBuildContract::decode(SitePublisherHostContract::protectedFile('/run/ubo-m6e-fixture/fixture.json',1048576),1048576);
    m6eNeed($plan['profile']==='m6e-release-a-component-v1'&&$plan['authority']==='SYNTHETIC COMPONENT ONLY'
        &&$plan['binding']['run_id']===$admission['run_id']&&$plan['expires_at']<= $admission['expires_at']&&$plan['expires_at']>time()
        &&$plan['summary']==$fixture['releases'][0]&&$fixture['unpublished']===true,'component_plan');
    $config=m6eBuildConfig($admission);$db=Database::connection();$store=m6eArtifactStore($config);
    $pulse=static function()use($plan):void{m6eNeed(time()<$plan['expires_at'],'component_expired');SiteDeploymentService::guardExternal();};
    $inspect=static function()use($db,$config,$store,$plan,$pulse):array {
        $summary=m6eObserveRelease($db,$config,$plan['release']['release_id']);
        m6eNeed($summary==$plan['summary'],'component_producer');
        return m6eServingSource($store,$config['artifact_root'],$plan['release'],$summary,2000,$pulse);
    };
    m6ePristine($db,$fixture['site_id'],$fixture['target_id']);$source=$inspect();
    $denied=[];
    foreach(['served'=>'/access-probe','journal'=>'/target.lock'] as $name=>$leaf) {
        $path='/var/lib/ubo-m6e-fixture/component-a-'.$admission['run_id'].'/'.$name.$leaf;
        $fd=@fopen($path,'r+');$denied[$name]=(int)($fd===false);if(is_resource($fd))fclose($fd);
        m6eNeed($denied[$name]===1,'component_worker_write');
    }
    m6eComponentEmit('source',['uid'=>posix_geteuid(),'source'=>$source,'write_denied'=>$denied]);
    m6eNeed(m6eComponentLine()===['command'=>'transfer']&&$inspect()===$source,'component_source_changed');
    $socket=$base.'/ipc/publish.sock';$s=lstat($socket);
    m6eNeed($s!==false&&($s['mode']&0177777)===0140660&&$s['uid']===2102&&$s['gid']===2000,'component_socket');
    $stream=stream_socket_client('unix://'.$socket,$errno,$error,2,STREAM_CLIENT_CONNECT);m6eNeed(is_resource($stream),'component_connect');
    try{$ack=m6eComponentSend($stream,$store,$config['artifact_root'],$source,2000,$plan);}finally{fclose($stream);}
    m6ePristine($db,$fixture['site_id'],$fixture['target_id']);m6eComponentEmit('transfer',['ack'=>$ack,'database_pristine'=>true]);
}
