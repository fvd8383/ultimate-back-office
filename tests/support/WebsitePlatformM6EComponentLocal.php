<?php
declare(strict_types=1);
/** Controlled Windows worker, selected ONLY from the existing local fixture child. */
require_once __DIR__.'/WebsitePlatformM6ECommissionFixture.php';
require_once __DIR__.'/WebsitePlatformM6EComponent.php';
function m6eLocalComponent():void {
    $start=m6eComponentLine();m6eNeed($start['command']==='inspect','local_component_start');
    [$f,$built]=m6eLocalServingFixture(true);$r=$built[0];$root=realpath($f->root.'/sealed');$uid=lstat($f->root)['uid'];
    $read=static fn()=>m6eServingSource($f->store,$root,$r['release'],$r['summary'],$uid,static function():void{});
    $source=$read();$releasePath=$f->releasePath($r['job']);
    // Explicit Unix ACL model: the controller supplies only measured modes of
    // its private mapped roots. This is never native UID enforcement evidence.
    $denied=[];foreach(['served','journal'] as $name) {
        $entry=$start['access'][$name];m6eNeed(is_file($entry['path']),'local_access_file');
        $denied[$name]=(int)($entry['uid']!==2000&&($entry['mode']&0002)===0);
    }
    m6eComponentEmit('source',['uid'=>2000,'source'=>$source,'write_denied'=>$denied,
        'local_source'=>$releasePath.'/metadata/manifest.json']);
    $next=m6eComponentLine();m6eNeed($next['command']==='transfer','local_component_transfer');
    if($next['fault']==='source-change')file_put_contents($releasePath.'/public/index.html','PRIVATE-CHANGED');
    if($next['fault']==='source-extra')file_put_contents($releasePath.'/private-extra','PRIVATE-EXTRA');
    m6eNeed($read()===$source,'local_component_changed');
    m6eNeed(preg_match('/^tcp:\/\/127\.0\.0\.1:[0-9]{1,5}$/D',$next['endpoint'])===1,'local_endpoint');
    $stream=stream_socket_client($next['endpoint'],$errno,$error,2,STREAM_CLIENT_CONNECT);m6eNeed(is_resource($stream),'local_connect');
    try{$ack=m6eComponentSend($stream,$f->store,$root,$source,$uid,$next['plan']);}finally{fclose($stream);}
    m6eNeed(!isset($f->db->tables['site_deployments']),'local_component_no_deployment');
    m6eComponentEmit('transfer',['ack'=>$ack,'database_pristine'=>true]);
}
