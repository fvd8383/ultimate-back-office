<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BLinuxGuard.php';
$assertions=0;
function launcherCheck(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function launcherReject(callable $call,string $code):void{
    try{$call();throw new LogicException('Accepted '.$code);}catch(RuntimeException $e){launcherCheck(str_contains($e->getMessage(),$code),'Wrong guard '.$code);}
}
$token='0123456789abcdef0123456789abcdef';$id=str_repeat('a',64);$imageId='sha256:'.str_repeat('b',64);$digest='sha256:'.str_repeat('d',64);
$engine=['OSType'=>'linux','Architecture'=>'x86_64','SecurityOptions'=>['name=rootless'],'CgroupVersion'=>'2','CgroupDriver'=>'systemd','Warnings'=>[], 'NCPU'=>2,'MemTotal'=>4*1024**3,'DockerRootDir'=>'/home/codex-validation/.local/share/docker'];
launcherCheck(m6linuxEngine($engine,'linux/amd64')===$engine['DockerRootDir'],'Rootless engine');
foreach([['SecurityOptions',[],'rootless_required'],['CgroupVersion','1','cgroup_v2_systemd_required'],['CgroupDriver','cgroupfs','cgroup_v2_systemd_required'],['Warnings',['ignored'],'engine_warnings'],['NCPU',1,'host_resources'],['Architecture','arm64','engine_platform']]as[$key,$value,$code]){
    $bad=$engine;$bad[$key]=$value;launcherReject(fn()=>m6linuxEngine($bad,'linux/amd64'),$code);
}
$image=['RepoDigests'=>['mysql@'.$digest],'Os'=>'linux','Architecture'=>'amd64','Id'=>$imageId,'Size'=>1024];
launcherCheck(m6linuxImage($image,$digest,'linux/amd64')===$imageId,'Cached official image');
launcherReject(fn()=>m6linuxImage($image,'sha256:'.str_repeat('e',64),'linux/amd64'),'official_image_digest');
launcherReject(fn()=>m6linuxImage($image,$digest,'linux/arm64'),'image_platform');
$bad=$image;$bad['RepoDigests']=['untrusted/mysql@'.$digest];launcherReject(fn()=>m6linuxImage($bad,$digest,'linux/amd64'),'official_image_digest');
$container=['Id'=>$id,'Name'=>'/ubo-m6b-'.$token,'Image'=>$imageId,'Platform'=>'linux','Config'=>['Labels'=>['ubo.m6b.owner'=>$token,'ubo.m6b.launcher'=>'linux-v1']],
    'State'=>['Running'=>true],'HostConfig'=>['Privileged'=>false,'NetworkMode'=>'bridge','PidMode'=>'','IpcMode'=>'private','UTSMode'=>'','UsernsMode'=>'','CgroupnsMode'=>'private','SecurityOpt'=>['no-new-privileges=true'],
        'NanoCpus'=>1000000000,'Memory'=>1610612736,'MemorySwap'=>1610612736,'PidsLimit'=>128,'Tmpfs'=>['/var/lib/mysql'=>'rw,nosuid,size=1073741824'],
        'LogConfig'=>['Type'=>'local','Config'=>['max-size'=>'1m','max-file'=>'2','compress'=>'false']]],
    'Mounts'=>[['Type'=>'tmpfs','Destination'=>'/var/lib/mysql']],'NetworkSettings'=>['Ports'=>['3306/tcp'=>[['HostIp'=>'127.0.0.1','HostPort'=>'33306']],'33060/tcp'=>null]]];
$password='abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
$image['Config']['Env']=['PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin','MYSQL_MAJOR=8.4','PINNED_IMAGE_DEFAULT=legitimate'];
$container['Config']['Env']=array_merge($image['Config']['Env'],['MYSQL_ROOT_PASSWORD='.$password,'MYSQL_ROOT_HOST=%']);
m6linuxEnvironment($container,$image,$password);launcherCheck(true,'Pinned image defaults and exact additions accepted');
foreach(['HTTP_PROXY=PROXY_CREDENTIAL_SENTINEL','https_proxy=PROXY_CREDENTIAL_SENTINEL','DB_PASSWORD=PROXY_CREDENTIAL_SENTINEL','MYSQL_ROOT_HOST=foreign','PATH=changed']as$entry){
    $bad=$container;$bad['Config']['Env'][]=$entry;launcherReject(fn()=>m6linuxEnvironment($bad,$image,$password),'container_environment');
}
$bad=$container;array_shift($bad['Config']['Env']);launcherReject(fn()=>m6linuxEnvironment($bad,$image,$password),'container_environment');
$bad=$container;$bad['Config']['Env']=array_reverse($bad['Config']['Env']);m6linuxEnvironment($bad,$image,$password);launcherCheck(true,'Environment ordering is immaterial');
launcherCheck(m6linuxContainer($container,$token,$imageId,$id)==='33306','Complete isolation policy');
foreach(['Privileged'=>true,'NetworkMode'=>'host','PidMode'=>'host','Devices'=>[['PathOnHost'=>'/dev/sda']],'Binds'=>['/var/run/docker.sock:/socket'],'Memory'=>0,'MemorySwap'=>-1,'PidsLimit'=>0,'NanoCpus'=>0]as$key=>$value){
    $bad=$container;$bad['HostConfig'][$key]=$value;
    launcherReject(fn()=>m6linuxContainer($bad,$token,$imageId,$id),in_array($key,['Memory','MemorySwap','PidsLimit','NanoCpus'],true)?'container_resource_request':'container_isolation');
}
$bad=$container;$bad['NetworkSettings']['Ports']['3306/tcp'][0]['HostIp']='0.0.0.0';launcherReject(fn()=>m6linuxContainer($bad,$token,$imageId,$id),'loopback_binding');
$bad=$container;$bad['Mounts'][]=['Type'=>'bind','Destination'=>'/host'];launcherReject(fn()=>m6linuxContainer($bad,$token,$imageId,$id),'container_mounts');
launcherReject(fn()=>m6linuxOwner($container,str_repeat('f',32),$imageId,$id),'container_ownership');
launcherReject(fn()=>m6linuxOwner($container,$token,$imageId,str_repeat('f',64)),'container_ownership');
launcherReject(fn()=>m6linuxOwner($container,$token,'sha256:'.str_repeat('f',64),$id),'container_ownership');

$bash=PHP_OS_FAMILY==='Windows'?'C:/Git/bin/bash.exe':'/bin/bash';
if(!is_file($bash))throw new RuntimeException('Bash fixture layer unavailable; install nothing, report NOT EXECUTED.');
$cases=['identity-valid'=>0,'identity-host'=>2,'identity-user'=>2,'identity-root'=>2,'identity-stage-default'=>2,'identity-shared'=>0,'identity-shared-user'=>2,'identity-production'=>2,'identity-production-default'=>2,
    'checkout-valid'=>0,'checkout-sha'=>2,'checkout-dirty'=>2,'checkout-untracked'=>2,'checkout-hash'=>2,'checkout-deployed'=>2,'checkout-webroot'=>2,'checkout-worktree'=>2,'checkout-history'=>2,
    'endpoint-valid'=>0,'endpoint-remote'=>2,'endpoint-ambiguous'=>2,'endpoint-conflict'=>2,'endpoint-context'=>2,'endpoint-rootful'=>2,'delegation-valid'=>0,'delegation-controller'=>2,'delegation-ignored'=>2,
    'headroom-dedicated'=>0,'headroom-shared'=>0,'headroom-memory'=>2,'headroom-disk'=>2,'headroom-cpu'=>2,'cgroup-valid'=>0,'cgroup-ignored'=>2,'check-only'=>0,
    'cleanup-valid'=>0,'cleanup-foreign'=>2,'cleanup-unknown'=>2,'cleanup-failed'=>2,'exit-test'=>17,'exit-timeout'=>124,'exit-interrupt'=>130,'partial-start'=>2,'redact'=>0,'output-bound'=>0,
    'run-success'=>0,'run-partial-start'=>2,'run-timeout'=>124,'run-test-failure'=>17,'run-resource'=>2,'run-cleanup-failure'=>2,'run-foreign'=>2,'run-supervisor-limit'=>2,
    'run-unload'=>0,'run-headroom'=>2,'run-interrupt'=>143,'run-mount-loss'=>2,'run-env-injected'=>2,
    'unit-normal'=>0,'unit-unload'=>0,'unit-unload-removed'=>0,'unit-absent'=>0,'unit-foreign'=>2,'unit-replaced'=>2,'unit-manager-inaccessible'=>2,'unit-arbitrary-failure'=>2,'unit-still-running'=>2,'unit-populated-descendant'=>2,'unit-unreadable'=>2,'unit-stop-failure'=>2,
    'client-isolated'=>0,'client-create'=>0,'client-signal'=>143,'client-cleanup-failure'=>2,
    'volume-correct'=>0,'volume-missing'=>2,'volume-wrong-uuid'=>2,'volume-unsafe-marker'=>2,'volume-unsafe-directory'=>2,'volume-wrong-docker-root'=>2,'volume-symlink'=>2,'volume-mount-loss'=>2,
    'space-boundary'=>0,'space-cpu-below'=>2,'space-memory-below'=>2,'space-boot-below'=>2,'space-volume-below'=>2,'space-reserve-boundary'=>0,'space-reserve-memory'=>2,'space-reserve-boot'=>2,'space-reserve-volume'=>2];
$root=sys_get_temp_dir().'/ubo-m6b-launcher-'.bin2hex(random_bytes(8));mkdir($root,0700);
foreach($cases as$case=>$expected){
    $dir=$root.'/'.$case;mkdir($dir,0700);$row=$container;if($case==='cleanup-foreign')$row['Config']['Labels']['ubo.m6b.owner']='foreign';
    file_put_contents($dir.'/container.json',json_encode([$row],JSON_THROW_ON_ERROR));
    $inspected=$container;if($case==='run-env-injected')$inspected['Config']['Env'][]='HTTP_PROXY=PROXY_CREDENTIAL_SENTINEL';
    file_put_contents($dir.'/inspection.json',json_encode([$inspected,$image],JSON_THROW_ON_ERROR));
    $foreign=$container;$foreign['Config']['Labels']['ubo.m6b.owner']='foreign';file_put_contents($dir.'/foreign.json',json_encode([$foreign],JSON_THROW_ON_ERROR));
    $process=proc_open([$bash,__DIR__.'/support/WebsitePlatformM6BLinuxLauncherFixture.sh',$case,$dir,str_replace('\\','/',PHP_BINARY)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Bash fixture unavailable');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    launcherCheck($exit===$expected,$case.' exit '.$exit.' expected '.$expected.' '.substr($error,0,300));
    $commands=is_file($dir.'/commands')?file_get_contents($dir.'/commands'):'';
    launcherCheck(!str_contains($commands,'UNEXPECTED'),$case.' unexpected command');
    if($case==='check-only'){
        launcherCheck($commands==="prerequisites\n",'Check-only must never enter run');
        launcherCheck(glob($dir.'/runtime/ubo-m6b-cli.*')===[],'Check-only cleans its private client config');
    }
    if(in_array($case,['cleanup-foreign','cleanup-unknown'],true))launcherCheck(!str_contains($commands,"rm\n"),'Foreign/unknown ownership must prevent deletion');
    if(in_array($case,['cleanup-valid','exit-test','exit-timeout','exit-interrupt','partial-start'],true))launcherCheck(str_contains($commands,"rm\n"),'Owned cleanup after '.$case);
    $evidence=str_starts_with($case,'run-')?(glob($dir.'/evidence/run-*')[0]??$dir.'/evidence'):$dir.'/evidence';
    $report=file_get_contents($evidence.'/report.txt');$tail=file_get_contents($evidence.'/mysql-tail.txt');
    $diagnostics=$out.$error.$report.$tail;
    launcherCheck(!str_contains($diagnostics,$token)&&!str_contains($diagnostics,substr($token,0,16))&&!str_contains($diagnostics,'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789'),'Secret-safe '.$case);
    launcherCheck(!str_contains($diagnostics,'PROXY_CREDENTIAL_SENTINEL'),'Proxy-safe diagnostics '.$case);
    if(str_starts_with($case,'client-')){
        launcherCheck(str_contains(file_get_contents($dir.'/user-docker/config.json'),'PROXY_CREDENTIAL_SENTINEL'),'Existing client config preserved');
        launcherCheck($case==='client-cleanup-failure'?glob($dir.'/runtime/ubo-m6b-cli.*')!==[]:glob($dir.'/runtime/ubo-m6b-cli.*')===[],'Client config cleanup '.$case);
    }
    if(str_starts_with($case,'cleanup-')||str_starts_with($case,'exit-')||$case==='partial-start'){
        launcherCheck(is_file($dir.'/evidence/SHA256SUMS'),'Evidence manifest '.$case);
        launcherCheck(!is_dir($dir.'/runtime/ubo-m6b.test'),'Scratch cleanup '.$case);
        if(in_array($case,['cleanup-foreign','cleanup-unknown','cleanup-failed'],true))launcherCheck(str_contains($report,'Test exit: 0')&&str_contains($report,'Overall: NON_SUCCESS'),'Cleanup failure overrides SQL success');
    }
    if(str_starts_with($case,'run-')){
        launcherCheck($case==='run-mount-loss'?!is_file($evidence.'/SHA256SUMS'):is_file($evidence.'/SHA256SUMS'),'Full lifecycle manifest '.$case);
        launcherCheck(glob($dir.'/runtime/ubo-m6b.*')===[],'Full lifecycle scratch cleanup '.$case);
        launcherCheck($case==='run-foreign'?!str_contains($commands,"rm\n"):str_contains($commands,"rm\n"),'Full lifecycle ownership cleanup '.$case);
        if($case==='run-mount-loss'){
            $emergency=array_values(array_filter(glob($dir.'/runtime/ubo-m6b-failure.*'),static fn(string $path):bool=>!str_ends_with($path,'.sha256')));
            launcherCheck(count($emergency)===1&&is_file($emergency[0].'.sha256')&&str_contains(file_get_contents($emergency[0]),'infrastructure_volume_identity')&&!str_contains($report,'Overall:'),'Mount loss preserves old evidence and writes only runtime diagnostic with checksum');
        }else launcherCheck(str_contains($report,in_array($case,['run-success','run-unload'],true)?'Overall: PASS':'Overall: NON_SUCCESS'),'Full lifecycle verdict '.$case);
        $output=file_get_contents($evidence.'/test-output.txt');launcherCheck(!str_contains($output,$token)&&!str_contains($output,'abcdef0123456789abcdef'),'Full lifecycle redaction '.$case);
    }
}
echo "PASS: $assertions Linux launcher guard assertions; ".count($cases)." isolated Bash scenarios. Fake commands only; Linux/container/MySQL NOT EXECUTED.\n";
// Retain only small local fixture evidence on failure. Remove our successful test's exact temporary tree.
if(!str_starts_with(realpath($root),realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'ubo-m6b-launcher-'))throw new RuntimeException('Temporary cleanup boundary');
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($iterator as$file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($root);
