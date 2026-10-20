<?php
declare(strict_types=1);
// Ordinary discovery is synthetic only: pure contracts, fake commands and local child processes.
require_once __DIR__.'/support/WebsitePlatformM6CNativeContract.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6CNativeSource.php';
$assertions=0;$scenarios=0;$root=null;$savedEvidence=getenv('M6C_NATIVE_TEST_EVIDENCE')?:null;
function nativeCheck(bool $ok,string $why): void {global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function nativeReject(callable $call,string $code): void
{
    try{$call();}catch(RuntimeException $e){nativeCheck(str_contains($e->getMessage(),$code),'wrong rejection '.$code.': '.$e->getMessage());return;}
    throw new RuntimeException('accepted '.$code);
}
function nativeFields(string $path): array
{
    $fields=[];
    foreach(file($path,FILE_IGNORE_NEW_LINES) as $line){
        nativeCheck(str_contains($line,'='),'key/value evidence '.$path);
        [$key,$value]=explode('=',$line,2);nativeCheck(!isset($fields[$key]),'unique evidence key '.$key);$fields[$key]=$value;
    }
    return $fields;
}
function nativeFinalEvidence(string $dir,array $r): array
{
    $status=nativeFields($dir.'/evidence/status.txt');
    nativeCheck((int)$status['final_exit']===$r['exit'],'status agrees with actual exit');
    nativeCheck(str_contains($r['stdout'],'primary='.$status['primary_exit'].' interruption='.$status['interruption_exit'].' cleanup='.$status['cleanup'].' evidence-publication='.$status['publication'].' final='.$status['final_exit'].';'),'console agrees with status');
    if($status['publication']==='PASS'){
        $receipt=nativeFields($dir.'/evidence/PUBLICATION');
        foreach($status as $key=>$value)nativeCheck(($receipt[$key]??null)===$value,'receipt/status agreement '.$key);
        nativeCheck(hash_file('sha256',$dir.'/evidence/SHA256SUMS')===$receipt['manifest_sha256'],'receipt binds finalized manifest');
        $covered=[];
        foreach(file($dir.'/evidence/SHA256SUMS',FILE_IGNORE_NEW_LINES) as $line){
            nativeCheck(preg_match('/^([a-f0-9]{64}) [ *]\.\/([a-zA-Z0-9.-]+)$/D',$line,$m)===1,'safe manifest path');
            nativeCheck(!isset($covered[$m[2]])&&hash_file('sha256',$dir.'/evidence/'.$m[2])===$m[1],'manifest covers finalized bytes '.$m[2]);$covered[$m[2]]=true;
        }
        foreach([...glob($dir.'/evidence/*.txt'),...glob($dir.'/evidence/*.json')] as $file)nativeCheck(isset($covered[basename($file)]),'all evidence covered');
        nativeCheck(isset($covered['status.txt']),'final status covered');
    }else nativeCheck(!file_exists($dir.'/evidence/PUBLICATION'),'failed publication has no completion receipt');
    return $status;
}
function nativeFixture(string $role): array
{
    global $token,$dbId,$runtimeId,$networkId,$dbImage,$runtimeImage,$password;
    $db=$role==='db';$id=$db?$dbId:$runtimeId;$image=$db?$dbImage:$runtimeImage;
    $tmp=$db?['/var/lib/mysql'=>'rw,nosuid,nodev,size=1073741824,uid=999,gid=999,mode=0700',
        '/var/run/mysqld'=>'rw,nosuid,nodev,size=16777216,uid=999,gid=999,mode=0755','/tmp'=>'rw,nosuid,nodev,noexec,size=16777216,mode=1777']:
        ['/opt/ubo'=>'rw,nosuid,nodev,size=268435456,mode=0755','/var/lib/ubo-m6c'=>'rw,nosuid,nodev,noexec,size=268435456,mode=0755',
        '/run/m6c'=>'rw,nosuid,nodev,noexec,size=16777216,mode=0755','/tmp'=>'rw,nosuid,nodev,noexec,size=16777216,mode=1777'];
    return ['Id'=>$id,'Name'=>'/ubo-m6c-'.$role.'-'.$token,'Image'=>$image['Id'],'Platform'=>'linux',
        'Config'=>['User'=>$db?'999:999':'0:0','Hostname'=>'m6c-'.$role.'-'.$token,
            'Entrypoint'=>$db?($image['Config']['Entrypoint']??[]):['/bin/sleep'],'Cmd'=>$db?['--skip-log-bin']:['1200'],'Healthcheck'=>['Test'=>['NONE']],
            'Labels'=>['ubo.m6c.owner'=>$token,'ubo.m6c.role'=>$role,'ubo.m6c.contract'=>M6CNativeContract::VERSION],
            'Env'=>$db?[...$image['Config']['Env'],'MYSQL_ROOT_PASSWORD='.$password,'MYSQL_ROOT_HOST=%']:$image['Config']['Env']],
        'State'=>['Running'=>true],
        'HostConfig'=>['NetworkMode'=>$networkId,'PidMode'=>'','IpcMode'=>'private','UTSMode'=>'','UsernsMode'=>'','CgroupnsMode'=>'private',
            'Privileged'=>false,'PublishAllPorts'=>false,'RestartPolicy'=>['Name'=>'no'],'SecurityOpt'=>['no-new-privileges=true'],'CapDrop'=>['ALL'],
            'CapAdd'=>$db?[]:['CHOWN','DAC_OVERRIDE','KILL','SETUID','SETGID','SETPCAP'],'NanoCpus'=>1000000000,
            'Memory'=>$db?1610612736:1073741824,'MemorySwap'=>$db?1610612736:1073741824,'PidsLimit'=>$db?128:48,
            'LogConfig'=>['Type'=>'local','Config'=>['max-size'=>'1m','max-file'=>'2','compress'=>'false']],
            'Tmpfs'=>$tmp,'ReadonlyRootfs'=>$db,'Mounts'=>$db?[]:[['Type'=>'bind','Source'=>'/synthetic/input','Target'=>'/input','ReadOnly'=>true,'BindOptions'=>['Propagation'=>'rprivate']]]],
        'Mounts'=>$db?[]:[['Type'=>'bind','Source'=>'/synthetic/input','Destination'=>'/input','RW'=>false,'Propagation'=>'rprivate']],
        'NetworkSettings'=>['Ports'=>['3306/tcp'=>null],'Networks'=>['ubo-m6c-'.$token=>['NetworkID'=>$networkId,'IPAddress'=>$db?'172.20.0.2':'172.20.0.3']]]];
}
function nativeContainer(array $c,string $role): array
{global $token,$dbId,$runtimeId,$networkId,$dbImage,$runtimeImage,$password;$db=$role==='db';$image=$db?$dbImage:$runtimeImage;return M6CNativeContract::container($c,$image,$role,$token,$db?$dbId:$runtimeId,$image['Id'],$networkId,'/synthetic/input',$password);}
try{
    $token='0123456789abcdef0123456789abcdef';$sha=str_repeat('1',40);$dbId=str_repeat('a',64);$runtimeId=str_repeat('e',64);$networkId=str_repeat('d',64);
    $password=str_repeat('f',64);$digest='sha256:'.str_repeat('2',64);$base='sha256:'.str_repeat('3',64);
    $dbImage=['Id'=>'sha256:'.str_repeat('b',64),'RepoDigests'=>['mysql@'.$digest],'Os'=>'linux','Architecture'=>'amd64','Size'=>1024,'Config'=>['Env'=>['PATH=/usr/bin:/bin','MYSQL_MAJOR=8.4']]];
    $runtimeImage=['Id'=>'sha256:'.str_repeat('c',64),'Os'=>'linux','Architecture'=>'amd64','Size'=>1024,'Config'=>['Env'=>['PATH=/usr/local/bin:/usr/bin:/bin'],'Labels'=>[
        'org.ubo.m6c.contract'=>M6CNativeContract::VERSION,'org.ubo.m6c.source'=>$sha,'org.ubo.m6c.php-base'=>'php@'.$base]]];
    $target=['os'=>'Linux','host'=>'ubo-stage-app','user'=>'codex-validation','uid'=>1000,'socket'=>'/run/user/1000/docker.sock','socket_uid'=>1000,'socket_type'=>'socket',
        'uuid'=>M6CNativeContract::VOLUME,'mount'=>'/mnt/ubo_stage_testdata','volume_device'=>2,'root_device'=>1,'repo'=>M6CNativeContract::BASE.'/checkouts/m6c','clean'=>true,'sha'=>$sha,'actual_sha'=>$sha];
    M6CNativeContract::target($target);nativeCheck(true,'fixed target');
    foreach(['os'=>'Windows','host'=>'production','user'=>'root','uid'=>0,'socket'=>'tcp://remote:2375','socket_uid'=>0,'socket_type'=>'file','overrides'=>['DOCKER_HOST'=>'remote'],
        'uuid'=>str_repeat('a',36),'mount'=>'/','volume_device'=>1,'repo'=>'/var/www/app','clean'=>false,'actual_sha'=>str_repeat('0',40)] as $key=>$value){
        $bad=$target;$bad[$key]=$value;nativeReject(fn()=>M6CNativeContract::target($bad),in_array($key,['os','host','user','uid'],true)?'host_user':(in_array($key,['socket','socket_uid','socket_type','overrides'],true)?'local_socket':(in_array($key,['uuid','mount','volume_device'],true)?'volume_identity':'source_identity')));
    }
    $resource=['cpus'=>4,'memory'=>4563402752,'root_free'=>1073741824,'volume_free'=>10737418240];
    M6CNativeContract::resources($resource);nativeCheck(true,'exact admission floors');
    foreach(['cpus'=>'cpu_gap','memory'=>'memory_gap','root_free'=>'root_space_gap','volume_free'=>'volume_space_gap'] as $key=>$code){$bad=$resource;$bad[$key]--;nativeReject(fn()=>M6CNativeContract::resources($bad),$code);}
    M6CNativeContract::resources(['cpus'=>4,'memory'=>1610612736,'root_free'=>1073741824,'volume_free'=>4294967296],false);nativeCheck(true,'ongoing floors');
    $engine=['OSType'=>'linux','Architecture'=>'amd64','SecurityOptions'=>['name=rootless'],'CgroupVersion'=>'2','CgroupDriver'=>'systemd',
        'Warnings'=>[],'NCPU'=>4,'MemTotal'=>8*1024**3,'Driver'=>'overlay2','DriverStatus'=>[], 'DockerRootDir'=>M6CNativeContract::BASE.'/docker'];
    nativeCheck(M6CNativeContract::engine($engine)===M6CNativeContract::BASE.'/docker','volume backed rootless engine');
    foreach(['Driver'=>'overlayfs','DockerRootDir'=>'/var/lib/docker','CgroupVersion'=>'1','SecurityOptions'=>[],'Warnings'=>['ignored limit']] as $key=>$value){$bad=$engine;$bad[$key]=$value;nativeReject(fn()=>M6CNativeContract::engine($bad),match($key){'Driver'=>'volume_backed_classic_image_store','DockerRootDir'=>'daemon_volume_root','CgroupVersion'=>'cgroup_v2_systemd_required','SecurityOptions'=>'rootless_required',default=>'engine_warnings'});}
    nativeCheck(M6CNativeContract::mysqlImage($dbImage,$digest)===$dbImage['Id'],'pinned mysql');
    $bad=$dbImage;$bad['Config']['Env']=['MYSQL_MAJOR=9.0'];nativeReject(fn()=>M6CNativeContract::mysqlImage($bad,$digest),'mysql_84_image');
    nativeReject(fn()=>m6linuxImage($dbImage,$base,'linux/amd64'),'official_image_digest');
    nativeCheck(M6CNativeContract::runtimeImage($runtimeImage,$runtimeImage['Id'],$sha,$base)===$runtimeImage['Id'],'pinned runtime');
    foreach(['Id'=>'sha256:'.str_repeat('9',64),'Os'=>'windows','Architecture'=>'arm64','Size'=>2147483649] as $key=>$value){$bad=$runtimeImage;$bad[$key]=$value;nativeReject(fn()=>M6CNativeContract::runtimeImage($bad,$runtimeImage['Id'],$sha,$base),$key==='Size'?'runtime_image_size':'runtime_image_identity');}
    foreach(['org.ubo.m6c.source','org.ubo.m6c.php-base','org.ubo.m6c.contract'] as $key){$bad=$runtimeImage;$bad['Config']['Labels'][$key]='wrong';nativeReject(fn()=>M6CNativeContract::runtimeImage($bad,$runtimeImage['Id'],$sha,$base),'runtime_image_provenance');}
    $network=['Id'=>$networkId,'Name'=>'ubo-m6c-'.$token,'Labels'=>['ubo.m6c.owner'=>$token,'ubo.m6c.contract'=>M6CNativeContract::VERSION],
        'Driver'=>'bridge','Scope'=>'local','Internal'=>true,'EnableIPv6'=>false,'Attachable'=>false,'Ingress'=>false,'Containers'=>[]];
    M6CNativeContract::network($network,$token,$networkId,[]);nativeCheck(true,'private network');
    foreach(['Internal'=>false,'Driver'=>'host','EnableIPv6'=>true,'Attachable'=>true,'Ingress'=>true] as $key=>$value){$bad=$network;$bad[$key]=$value;nativeReject(fn()=>M6CNativeContract::network($bad,$token,$networkId,[]),'private_network');}
    $bad=$network;$bad['Containers'][$dbId]=['IPv4Address'=>'172.20.0.2/16'];nativeReject(fn()=>M6CNativeContract::network($bad,$token,$networkId,[]),'network_members');
    foreach(['127.0.0.1','8.8.8.8','::1','172.32.0.1','169.254.1.2'] as $ip)nativeCheck(!M6CNativeContract::privateIp($ip),'private address rejection');
    foreach(['db','runtime'] as $role){
        $c=nativeFixture($role);nativeCheck(nativeContainer($c,$role)['role']===$role,'valid '.$role);
        foreach(['NetworkMode'=>'host','PidMode'=>'host','IpcMode'=>'host','UTSMode'=>'host','UsernsMode'=>'host','CgroupnsMode'=>'host','Privileged'=>true,
            'Binds'=>['/etc:/host'],'Devices'=>[['PathOnHost'=>'/dev/sda']],'PortBindings'=>['3306/tcp'=>[['HostPort'=>'3306']]],'PublishAllPorts'=>true] as $key=>$value){$bad=$c;$bad['HostConfig'][$key]=$value;nativeReject(fn()=>nativeContainer($bad,$role),'container_isolation');}
        foreach(['Memory','MemorySwap','NanoCpus','PidsLimit'] as $key){$bad=$c;$bad['HostConfig'][$key]=0;nativeReject(fn()=>nativeContainer($bad,$role),'resource_request');}
        foreach(['CapDrop','CapAdd'] as $key){$bad=$c;$bad['HostConfig'][$key]=['SYS_ADMIN'];nativeReject(fn()=>nativeContainer($bad,$role),'setup_capabilities');}
        $bad=$c;$bad['Config']['User']='1000';nativeReject(fn()=>nativeContainer($bad,$role),'setup_process_user');
        $bad=$c;$bad['HostConfig']['SecurityOpt']=[];nativeReject(fn()=>nativeContainer($bad,$role),'no_new_privileges');
        $bad=$c;$bad['Config']['Cmd']=['foreign'];nativeReject(fn()=>nativeContainer($bad,$role),'container_command');
        $bad=$c;$bad['HostConfig']['Tmpfs']['/host']='rw';nativeReject(fn()=>nativeContainer($bad,$role),'tmpfs_budget');
        $bad=$c;$bad['Mounts'][]=['Type'=>'volume','Destination'=>'/foreign'];nativeReject(fn()=>nativeContainer($bad,$role),'unexpected_mount');
        foreach(['Mounts','HostConfig'] as $where){$bad=$c;if($where==='Mounts')$bad['Mounts']=new stdClass();else $bad['HostConfig']['Mounts']=new stdClass();nativeReject(fn()=>nativeContainer($bad,$role),$where==='Mounts'?'mount_shape':'mount_requests');}
        $bad=$c;$bad['Config']['Env'][]='DB_PASSWORD=secret';nativeReject(fn()=>nativeContainer($bad,$role),$role==='db'?'container_environment':'runtime_environment');
        foreach(['Id','Image','Name'] as $key){$bad=$c;$bad[$key]='foreign';nativeReject(fn()=>nativeContainer($bad,$role),'container_owner');}
    }
    $report=['version'=>M6CNativeContract::VERSION,'sha'=>$sha,'token'=>$token,'native'=>true,'status'=>'PASS','assertions'=>20,'cases'=>[]];
    foreach(M6CNativeContract::CASES as $name)$report['cases'][$name]=['status'=>'PASS','lane'=>str_starts_with($name,'protected-')||$name==='fresh-process-adoption'?'protected':'instrumented','assertions'=>1];
    M6CNativeContract::evidence($report,$sha,$token);nativeCheck(true,'complete report');
    foreach(M6CNativeContract::CASES as $name){$bad=$report;$bad['cases'][$name]['status']='UNREACHED';nativeReject(fn()=>M6CNativeContract::evidence($bad,$sha,$token),'case_incomplete');}
    $bad=$report;unset($bad['cases']['no-publication']);nativeReject(fn()=>M6CNativeContract::evidence($bad,$sha,$token),'evidence_case_inventory');
    $bad=$report;$bad['cases']['protected-multipage-build']['lane']='instrumented';nativeReject(fn()=>M6CNativeContract::evidence($bad,$sha,$token),'case_incomplete');
    foreach(['status'=>'FAIL','native'=>false,'assertions'=>0,'sha'=>str_repeat('0',40),'token'=>str_repeat('0',32)] as $key=>$value){$bad=$report;$bad[$key]=$value;nativeReject(fn()=>M6CNativeContract::evidence($bad,$sha,$token),in_array($key,['sha','token'],true)?'evidence_identity':'evidence_result');}
    // New bounded helper regressions; the completed historical CLI/process suites are not invoked.
    $r=M6CNativeProcess::capture([PHP_BINARY,'-n','-r','fwrite(STDERR,"synthetic failure");exit(7);'],5);nativeCheck($r['exit']===7&&$r['stderr']==='synthetic failure','actual failed child');
    $p=new M6CNativeProcess([PHP_BINARY,'-n','-r','echo "READY {}\n";flush();if(fgets(STDIN)!=="CONTINUE\n")exit(2);echo "done";'],5);
    nativeCheck($p->line()==='READY {}','real bounded barrier');$p->send('CONTINUE');nativeCheck($p->finish()['exit']===0,'barrier released');
    nativeReject(fn()=>M6CNativeProcess::capture([PHP_BINARY,'-n','-r','usleep(3000000);'],1),'native_child_timeout');
    nativeReject(fn()=>M6CNativeProcess::capture([PHP_BINARY,'-n','-r','echo str_repeat("x",8192);'],5,1024),'native_child_output_limit');
    $stopped=M6CNativeProcess::cleanup();nativeCheck(count($stopped)===2&&array_reduce($stopped,fn($ok,$r)=>$ok&&!$r['running'],true),'timeout/output diagnostics retained');
    foreach(['malformed'=>'{broken','missing'=>'{}','valid'=>json_encode($report,JSON_THROW_ON_ERROR)] as $label=>$json){
        $p=new M6CNativeProcess([PHP_BINARY,'-n',__DIR__.'/support/WebsitePlatformM6CNativeContract.php','evidence',$sha,$token],5);
        $p->send($json);$p->closeInput();$r=$p->finish();nativeCheck($r['exit']===($label==='valid'?0:2),'actual evidence JSON boundary '.$label);
        nativeCheck(!str_contains($r['stderr'],'Stack trace')&&strlen($r['stderr'])<128,'safe guard diagnostic '.$label);
    }
    $root=sys_get_temp_dir().'/ubo-m6c-native-test-'.bin2hex(random_bytes(8));nativeCheck(mkdir($root,0700),'new synthetic root');
    $bytes="exact raw bytes\r\n";file_put_contents($root.'/blob',$bytes);$entry='100644 '.sha1('blob '.strlen($bytes)."\0".$bytes)." 0\tblob\0";
    nativeCheck(count(M6CNativeSource::verify($root,$entry))===1,'raw source bytes');file_put_contents($root.'/blob',str_replace("\r\n","\n",$bytes));nativeReject(fn()=>M6CNativeSource::verify($root,$entry),'source_raw_blob');
    $bash=getenv('M6C_NATIVE_TEST_BASH')?: (PHP_OS_FAMILY==='Windows'?'C:/Git/bin/bash.exe':'/bin/bash');nativeCheck(is_file($bash),'available Bash prerequisite');
    $expected=['help'=>0,'check-only'=>0,'missing-prerequisite'=>2,'unavailable-image'=>2,'cached-images'=>0,'uncached-mysql'=>2,'uncached-runtime'=>2,'oversized-images'=>2,'success'=>0,'create-failure'=>2,'lost-create-reply'=>2,
        'start-failure'=>2,'inspection-mismatch'=>2,'mount-loss'=>2,'timeout'=>124,'signal'=>143,'failed-child'=>17,'failed-child-missing'=>17,
        'missing-evidence'=>2,'malformed-evidence'=>2,'foreign-cleanup'=>2,'stop-failure'=>2,'primary-and-cleanup-failure'=>17,'publication-failure'=>2,'source-pack'=>0];
    $expected+=['leaf-status'=>17,'inspect-failure'=>2,'list-failure'=>2,'lost-create-stop'=>2,'status-failure'=>2];
    foreach(['status','hash','rename','publish','receipthash','receiptwrite','commitstatus','hash-timeout','commit-timeout','primarypublish'] as $fault)$expected['fault-'.$fault]=$fault==='primarypublish'?17:2;
    $plans=[];
    foreach(['INT'=>130,'TERM'=>143] as $sig=>$code){
        foreach(['operation','runtime','inspection','db','network','status','hash','boundary','frozen','receipt'] as $phase){
            $name='interrupt-'.$sig.'-'.$phase;$accepted=!in_array($phase,['frozen','receipt'],true);
            $expected[$name]=$accepted?$code:0;$plans[$name]=[$sig,$phase,'',$accepted?$code:0];
        }
        foreach(['primary','cleanup','primarycleanup','publish','primarypublish','hash','rename','receipthash','receiptwrite','commitstatus','status'] as $fault){
            $name='interrupt-'.$sig.'-runtime-'.$fault;
            $expected[$name]=str_starts_with($fault,'primary')?17:$code;$plans[$name]=[$sig,'runtime',$fault,$code];
        }
    }
    foreach(['INT_INT'=>130,'TERM_TERM'=>143,'INT_TERM_INT'=>130,'TERM_INT_TERM'=>143] as $sequence=>$code){
        $name='interrupt-'.$sequence.'-runtime';$expected[$name]=$code;$plans[$name]=[$sequence,'runtime','',$code];
    }
    foreach($expected as $scenario=>$exit){
        echo 'Synthetic scenario '.$scenario."\n";
        $dir=$root.'/'.$scenario;mkdir($dir,0700);
        $foreign=nativeFixture('runtime');$foreign['Image']='sha256:'.str_repeat('9',64);
        $occupied=$network;$occupied['Containers']=[$dbId=>['IPv4Address'=>'172.20.0.2/16'],$runtimeId=>['IPv4Address'=>'172.20.0.3/16']];
        foreach(['db'=>[nativeFixture('db')],'runtime'=>[nativeFixture('runtime')],'foreign'=>[$foreign],'network'=>[$network],
            'network-occupied'=>[$occupied],'db-image'=>[$dbImage],'runtime-image'=>[$runtimeImage],'report'=>$report] as $file=>$data)file_put_contents($dir.'/'.$file.'.json',json_encode($data,JSON_THROW_ON_ERROR));
        $command=[$bash,__DIR__.'/support/WebsitePlatformM6CNativeLauncherFixture.sh',$scenario,$dir,PHP_BINARY];$begin=hrtime(true);
        if(isset($plans[$scenario])){
            [$sequence,$phase,$fault,$signal]=$plans[$scenario];$p=new M6CNativeProcess($command,35,65536);
            foreach(explode('_',$sequence) as $sig){
                $line=$p->line(10);nativeCheck(preg_match('/^READY ([1-9][0-9]*) (INT|TERM) ([a-z]+)$/D',$line,$m)===1,'verified signal checkpoint '.$scenario.' '.$line);
                nativeCheck($m[1]===trim(file_get_contents($dir.'/owner'))&&$m[2]===$sig&&$m[3]===$phase,'owned child and expected phase');
                $p->send('GO '.$m[1].' '.$sig.' '.$phase);
            }
            $p->closeInput();$r=$p->finish();
        }else $r=M6CNativeProcess::capture($command,35,65536);
        $scenarios++;$r['seconds']=(hrtime(true)-$begin)/1e9;file_put_contents($dir.'/result.json',json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        nativeCheck($r['exit']===$exit,$scenario.' exit expected '.$exit.' got '.$r['exit'].' '.$r['stdout'].' '.$r['stderr']);
        $log=file_get_contents($dir.'/commands');nativeCheck(!str_contains($log,'FORBIDDEN')&&($scenario==='leaf-status'||!str_contains($log,'UNEXPECTED')),$scenario.' no infrastructure calls');
        if($scenario==='leaf-status'){
            preg_match_all('/leaf=([^ ]+) expected=(\d+) actual=(\d+)/',$r['stdout'],$leaves,PREG_SET_ORDER);
            nativeCheck(count($leaves)===34,'all fake success/failure leaves inside actual EXIT trap');
            foreach($leaves as $leaf)nativeCheck($leaf[2]===$leaf[3],'trap leaf '.$leaf[1]);
            nativeCheck(str_contains($r['stdout'],'operation=17')&&str_contains($r['stdout'],'verified_absent=0')&&str_contains($r['stdout'],'uncertain_absent=1'),'nonzero EXIT verified absence vs uncertainty');
            foreach(['uncertain_present=1','foreign_owner=1','foreign_marker_retained=1','prepare_reserve=124','commit_reserve=124','expired_deadline=124'] as $fact)nativeCheck(str_contains($r['stdout'],$fact),'trap identity/deadline result '.$fact);
        }
        if(file_exists($dir.'/evidence/status.txt')){
            $status=nativeFinalEvidence($dir,$r);
            if(in_array($scenario,['lost-create-reply','create-failure','failed-child','failed-child-missing','start-failure','inspection-mismatch'],true))nativeCheck($status['cleanup']==='PASS',$scenario.' primary failure with successful owned cleanup');
            if(in_array($scenario,['foreign-cleanup','inspect-failure','list-failure','stop-failure','lost-create-stop','primary-and-cleanup-failure'],true))nativeCheck($status['cleanup']==='FAIL',$scenario.' uncertainty or cleanup failure retained');
            nativeCheck(substr_count($log,'rm '.$runtimeId)<=1&&substr_count($log,'rm '.$dbId)<=1&&substr_count($log,'network rm')<=1,'no duplicate removals');
            nativeCheck(count(file($dir.'/boundary'))===1,'one finalization boundary');
        }
        if(isset($plans[$scenario])){
            nativeCheck((int)$status['primary_exit']===(str_starts_with($fault,'primary')?17:0),'primary operation remains separate');
            nativeCheck((int)$status['interruption_exit']===$signal,'first accepted signal preserved');
            $observed=file_exists($dir.'/signals')?file($dir.'/signals',FILE_IGNORE_NEW_LINES):[];
            nativeCheck(count($observed)===($signal===0?0:count(explode('_',$sequence))),'actual handler observation count');
            nativeCheck(count(file($dir.'/deliveries'))===count(explode('_',$sequence)),'actual signal deliveries at both sides of boundary');
            foreach($observed as $line){
                nativeCheck(str_contains($line,'first='.$signal.' '),'mixed/repeated signals retain first');
                if(!str_contains($line,'deadline=operation'))nativeCheck(str_ends_with(trim(file_get_contents($dir.'/boundary')),substr($line,strpos($line,'deadline='))),'cleanup deadline never restarted');
            }
            nativeCheck($status['cleanup']===(in_array($fault,['cleanup','primarycleanup'],true)?'FAIL':'PASS'),'independent cleanup result');
            nativeCheck($status['publication']===(in_array($fault,['publish','primarypublish','hash','rename','receipthash','receiptwrite','commitstatus','status'],true)?'FAIL':'PASS'),'independent publication result');
            nativeCheck(json_decode(file_get_contents($dir.'/evidence/native.json'),true,32,JSON_THROW_ON_ERROR)===$report,'coordinator observed PASS is unchanged');
            if($status['cleanup']==='PASS')nativeCheck(!file_exists($dir.'/runtime')&&!file_exists($dir.'/db')&&!file_exists($dir.'/network'),'owned synthetic markers removed after interruption');
            if(in_array($phase,['status','hash','boundary'],true))nativeCheck(str_contains(file_get_contents($dir.'/evidence/status.provisional'),'final_exit=0')&&(int)$status['final_exit']!==0,'provisional success replaced before receipt');
        }
        if(in_array($scenario,['help','check-only','missing-prerequisite','unavailable-image'],true))nativeCheck($log==='',$scenario.' no mutation commands');
        if($scenario==='foreign-cleanup')nativeCheck(file_exists($dir.'/runtime')&&!str_contains($log,'rm '.$runtimeId),'foreign container retained');
        if(in_array($scenario,['foreign-cleanup','stop-failure'],true))nativeCheck(file_exists($dir.'/network'),'occupied network retained');
        if(in_array($scenario,['success','failed-child','failed-child-missing','lost-create-reply','start-failure','inspection-mismatch'],true))nativeCheck(!file_exists($dir.'/db')&&!file_exists($dir.'/runtime'),$scenario.' owned cleanup');
        if(in_array($scenario,['failed-child-missing','primary-and-cleanup-failure'],true))nativeCheck(str_contains(file_get_contents($dir.'/evidence/status.txt'),'primary_exit=17'),$scenario.' primary preserved');
        if($scenario==='publication-failure')nativeCheck(!file_exists($dir.'/evidence/PUBLICATION'),'failed capture cannot publish PASS');
    }
    foreach(['WebsitePlatformM6CNative.php','support/WebsitePlatformM6CNativeWorker.php'] as $path){
        $r=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/'.$path,'--help'],5);nativeCheck($r['exit']===0&&$r['stderr']==='','help without bootstrap');
        nativeCheck(!str_ends_with($path,'Test.php'),'native entry outside discovery');
    }
    foreach(glob(__DIR__.'/*Test.php') as $test){
        if(realpath($test)===__FILE__)continue;$s=file_get_contents($test);
        nativeCheck(!str_contains($s,'RunM6CNative.sh')&&!str_contains($s,'WebsitePlatformM6CNative.php'),'ordinary suites do not invoke native entry');
    }
    echo "Website platform M6C native launcher: $assertions assertions passed; $scenarios fake-command scenarios; native execution NOT PERFORMED.\n";
}finally{
    M6CNativeProcess::cleanup();
    if($root!==null){
        $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
        if($resolved===false||$parent===false||dirname($resolved)!==$parent||!preg_match('/^ubo-m6c-native-test-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('synthetic_cleanup_boundary');
        // Optional explicit local evidence destination; never overwrite an earlier run.
        if($savedEvidence!==null){
            if(!is_dir($savedEvidence))throw new RuntimeException('evidence_destination_missing');
            $save=$savedEvidence.'/'.basename($resolved);if(!mkdir($save,0700))throw new RuntimeException('evidence_destination_exists');
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $f){
                $dest=$save.'/'.substr($f->getPathname(),strlen($resolved)+1);if($f->isDir())mkdir($dest,0700);else copy($f->getPathname(),$dest);
            }
            echo 'Retained synthetic evidence: '.$save."\n";
        }
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){
            if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else{chmod($f->getPathname(),0600);unlink($f->getPathname());}
        }
        rmdir($resolved);
    }
}
