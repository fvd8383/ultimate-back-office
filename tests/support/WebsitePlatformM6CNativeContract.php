<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6BLinuxGuard.php';

/** Pure native-launcher contracts. No application/configuration/SQL bootstrap. */
final class M6CNativeContract
{
    public const VERSION = 'm6c-native-v1';
    public const VOLUME = '7d255792-0283-4773-b7a1-20596ed0d8fa';
    public const BASE = '/mnt/ubo_stage_testdata/codex-validation';
    public const WORKER_UID = 2000;
    public const DEADLINE = 1200;
    public const CASES = [
        'protected-startup-denials','protected-multipage-build','protected-replay','concurrent-claims',
        'requester-denied-after-claim','source-denied-after-claim','asset-loss-after-claim','asset-corruption-after-claim',
        'execution-verification-renewal','recovery-verification-renewal','stale-completion',
        'killed-before-journal','killed-after-seal','fresh-process-adoption',
        'missing-evidence','tampered-evidence','ambiguous-evidence','audit-rollback','validation-rollback','no-publication',
    ];
    public static function need(bool $ok,string $code): void
    { if (!$ok) throw new RuntimeException('M6C native: '.$code); }
    public static function hex(string $value,int $length): bool
    { return preg_match('/^[a-f0-9]{'.$length.'}$/D',$value)===1; }
    public static function digest(string $value): bool
    { return preg_match('/^sha256:[a-f0-9]{64}$/D',$value)===1; }
    public static function target(array $v): void
    {
        self::need(($v['os']??'')==='Linux'&&($v['host']??'')==='ubo-stage-app'
            &&($v['user']??'')==='codex-validation'&&($v['uid']??0)===1000,'host_user');
        self::need(($v['socket']??'')==='/run/user/1000/docker.sock'&&($v['socket_uid']??0)===1000
            &&($v['socket_type']??'')==='socket'&&empty($v['overrides']),'local_socket');
        self::need(($v['uuid']??'')===self::VOLUME&&($v['mount']??'')==='/mnt/ubo_stage_testdata'
            &&($v['volume_device']??null)!==($v['root_device']??null),'volume_identity');
        self::need(str_starts_with($v['repo']??'',self::BASE.'/checkouts/')
            &&!preg_match('~/(?:www|public_html)/~',$v['repo'])&&($v['clean']??false)
            &&self::hex($v['sha']??'',40)&&($v['actual_sha']??'')===$v['sha'],'source_identity');
    }
    public static function resources(array $v,bool $initial=true): void
    {
        self::need(($v['cpus']??0)>=4,'cpu_gap');
        self::need(($v['memory']??0)>=($initial?4563402752:1610612736),'memory_gap');
        self::need(($v['root_free']??0)>=1073741824,'root_space_gap');
        self::need(($v['volume_free']??0)>=($initial?10737418240:4294967296),'volume_space_gap');
    }
    public static function runtimeImage(array $image,string $id,string $sha,string $base): string
    {
        self::need(self::digest($id)&&self::digest($base)&&self::hex($sha,40),'image_arguments');
        self::need(($image['Id']??'')===$id&&($image['Os']??'')==='linux'&&($image['Architecture']??'')==='amd64','runtime_image_identity');
        self::need(($image['Size']??PHP_INT_MAX)<=2147483648,'runtime_image_size');
        $labels=$image['Config']['Labels']??[];
        self::need(($labels['org.ubo.m6c.contract']??'')===self::VERSION
            &&($labels['org.ubo.m6c.source']??'')===$sha
            &&($labels['org.ubo.m6c.php-base']??'')==='php@'.$base,'runtime_image_provenance');
        return $id;
    }
    public static function engine(array $info): string
    {
        $root=m6linuxEngine($info,'linux/amd64');
        self::need(in_array($info['Driver']??'',['overlay2','fuse-overlayfs'],true),'volume_backed_classic_image_store');
        foreach($info['DriverStatus']??[] as $row)self::need(!str_contains(implode(' ', $row),'containerd'),'separate_containerd_store_unverified');
        self::need($root===self::BASE.'/docker','daemon_volume_root');return $root;
    }
    public static function mysqlImage(array $image,string $digest): string
    {
        $id=m6linuxImage($image,$digest,'linux/amd64');
        self::need(in_array('MYSQL_MAJOR=8.4',$image['Config']['Env']??[],true),'mysql_84_image');return $id;
    }
    public static function network(array $n,string $token,string $id,array $members): void
    {
        self::need(self::hex($token,32)&&self::hex($id,64)&&($n['Id']??'')===$id
            &&($n['Name']??'')==='ubo-m6c-'.$token&&($n['Labels']['ubo.m6c.owner']??'')===$token
            &&($n['Labels']['ubo.m6c.contract']??'')===self::VERSION,'network_owner');
        self::need(($n['Driver']??'')==='bridge'&&($n['Scope']??'')==='local'&&($n['Internal']??false)===true
            &&($n['EnableIPv6']??true)===false&&!($n['Attachable']??true)&&!($n['Ingress']??true),'private_network');
        $actual=array_keys($n['Containers']??[]);sort($actual);sort($members);
        self::need($actual===$members,'network_members');
        foreach($n['Containers']??[] as $member) self::need(self::privateIp(explode('/',$member['IPv4Address']??'')[0]),'network_address');
    }
    public static function privateIp(string $ip): bool
    {
        if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false)return false;
        $p=array_map('intval',explode('.',$ip));
        return $p[0]===10||($p[0]===172&&$p[1]>=16&&$p[1]<=31)||($p[0]===192&&$p[1]===168);
    }
    public static function owner(array $c,string $role,string $token,string $id,string $image): void
    {
        self::need(in_array($role,['db','runtime'],true)&&self::hex($token,32)&&self::hex($id,64)
            &&($c['Id']??'')===$id&&($c['Name']??'')==='/ubo-m6c-'.$role.'-'.$token
            &&($c['Image']??'')===$image&&($c['Config']['Labels']['ubo.m6c.owner']??'')===$token
            &&($c['Config']['Labels']['ubo.m6c.role']??'')===$role
            &&($c['Config']['Labels']['ubo.m6c.contract']??'')===self::VERSION,'container_owner');
    }
    /** Inspect actual image, process user, namespace, environment and every mount before work. */
    public static function container(array $c,array $image,string $role,string $token,string $id,string $imageId,
        string $network,string $input,?string $password=null): array
    {
        self::owner($c,$role,$token,$id,$imageId);$h=$c['HostConfig']??[];
        self::need(($image['Id']??'')===$imageId&&($c['Platform']??'')==='linux'&&($c['State']['Running']??false),'container_running');
        self::need(($c['Config']['User']??'')===($role==='db'?'999:999':'0:0'),'setup_process_user');
        self::need(($c['Config']['Hostname']??'')==='m6c-'.$role.'-'.$token,'container_hostname');
        self::need(($c['Config']['Entrypoint']??[])===($role==='db'?($image['Config']['Entrypoint']??[]):['/bin/sleep'])
            &&($c['Config']['Cmd']??[])===($role==='db'?['--skip-log-bin']:['1200'])
            &&($c['Config']['Healthcheck']['Test']??[])===['NONE']&&($h['RestartPolicy']['Name']??'')==='no','container_command');
        self::need(($h['NetworkMode']??'')===$network&&($h['PidMode']??'')===''&&($h['IpcMode']??'')==='private'
            &&($h['UTSMode']??'')===''&&($h['UsernsMode']??'')!=='host'&&($h['CgroupnsMode']??'')==='private'
            &&!($h['Privileged']??true)&&empty($h['Devices'])&&empty($h['DeviceRequests'])&&empty($h['VolumesFrom'])
            &&empty($h['Binds'])&&empty($h['ExtraHosts'])&&empty($h['Dns'])&&empty($h['Links'])
            &&empty($h['PortBindings'])&&!($h['PublishAllPorts']??true),'container_isolation');
        self::need(($h['SecurityOpt']??[])===['no-new-privileges=true'],'no_new_privileges');
        $caps=static function(array $v):array{$v=array_map(static fn($x)=>str_replace('CAP_','',strtoupper($x)),$v);sort($v);return $v;};
        self::need($caps($h['CapDrop']??[])===['ALL']
            &&$caps($h['CapAdd']??[])===($role==='db'?[]:['CHOWN','DAC_OVERRIDE','KILL','SETGID','SETPCAP','SETUID']),'setup_capabilities');
        $memory=$role==='db'?1610612736:1073741824;$pids=$role==='db'?128:48;
        self::need(($h['NanoCpus']??0)===1000000000&&($h['Memory']??0)===$memory
            &&($h['MemorySwap']??0)===$memory&&($h['PidsLimit']??0)===$pids,'resource_request');
        $logs=$h['LogConfig']['Config']??[];ksort($logs);
        self::need(($h['LogConfig']['Type']??'')==='local'&&$logs===[
            'compress'=>'false','max-file'=>'2','max-size'=>'1m'],'bounded_logs');
        $tmp=$role==='db'?[
            '/var/lib/mysql'=>'rw,nosuid,nodev,size=1073741824,uid=999,gid=999,mode=0700',
            '/var/run/mysqld'=>'rw,nosuid,nodev,size=16777216,uid=999,gid=999,mode=0755',
            '/tmp'=>'rw,nosuid,nodev,noexec,size=16777216,mode=1777',
        ]:[
            '/opt/ubo'=>'rw,nosuid,nodev,size=268435456,mode=0755',
            '/var/lib/ubo-m6c'=>'rw,nosuid,nodev,noexec,size=268435456,mode=0755',
            '/run/m6c'=>'rw,nosuid,nodev,noexec,size=16777216,mode=0755',
            '/tmp'=>'rw,nosuid,nodev,noexec,size=16777216,mode=1777',
        ];
        $observed=$h['Tmpfs']??[];ksort($observed);ksort($tmp);self::need($observed===$tmp,'tmpfs_budget');
        self::need(($h['ReadonlyRootfs']??false)===($role==='db'),'root_filesystem');
        $requests=$h['Mounts']??[];
        self::need(is_array($requests)&&array_is_list($requests)&&count($requests)===($role==='db'?0:1),'mount_requests');
        if($role==='runtime'){
            $m=$requests[0];self::need(($m['Type']??'')==='bind'&&($m['Source']??'')===$input
                &&($m['Target']??'')==='/input'&&($m['ReadOnly']??false)
                &&($m['BindOptions']['Propagation']??'rprivate')==='rprivate','input_mount_request');
        }
        self::need(is_array($c['Mounts']??null)&&array_is_list($c['Mounts']),'mount_shape');$binds=0;
        foreach($c['Mounts'] as $m){
            if(($m['Type']??'')==='tmpfs')self::need(isset($tmp[$m['Destination']??'']),'unexpected_tmpfs');
            else{self::need($role==='runtime'&&($m['Type']??'')==='bind'&&($m['Source']??'')===$input
                &&($m['Destination']??'')==='/input'&&!($m['RW']??true)&&($m['Propagation']??'')==='rprivate','unexpected_mount');$binds++;}
        }
        self::need($binds===($role==='runtime'?1:0),'input_mount');
        $nets=$c['NetworkSettings']['Networks']??[];self::need(count($nets)===1,'additional_network');
        $net=array_values($nets)[0];self::need(($net['NetworkID']??'')===$network&&self::privateIp($net['IPAddress']??''),'network_binding');
        foreach($c['NetworkSettings']['Ports']??[] as $binding)self::need($binding===null||$binding===[],'published_port');
        if($role==='db')m6linuxEnvironment($c,$image,(string)$password);
        else self::need(($c['Config']['Env']??[])===($image['Config']['Env']??[]),'runtime_environment');
        return ['id'=>$id,'role'=>$role,'image'=>$imageId,'network'=>$network,'ip'=>$net['IPAddress'],
            'setup_uid'=>$role==='db'?999:0,'memory'=>$memory,'pids'=>$pids,'running'=>true];
    }
    public static function captureRuntime(array $c,string $token,string $id,string $image,string $network):void
    {
        self::owner($c,'runtime',$token,$id,$image);
        self::need(self::hex($network,64)&&($c['HostConfig']['NetworkMode']??'')===$network,'capture_network');
        $nets=$c['NetworkSettings']['Networks']??[];
        self::need(is_array($nets)&&count($nets)===1&&(array_values($nets)[0]['NetworkID']??'')===$network,'capture_network');
        self::need(($c['State']['Running']??false)===true,'capture_runtime_unavailable');
    }
    /** Transport envelope accepts failed evidence; acceptance still uses evidence(). */
    public static function envelope(array $report,string $sha,string $token):void
    {
        self::need(self::hex($sha,40)&&self::hex($token,32)&&($report['version']??'')===self::VERSION
            &&($report['sha']??'')===$sha&&($report['token']??'')===$token,'envelope_identity');
        self::need(($report['native']??false)===true&&in_array($report['status']??'', ['PASS','FAIL'],true)
            &&is_int($report['assertions']??null)&&$report['assertions']>=0,'envelope_result');
        self::need(array_keys($report['cases']??[])===self::CASES,'envelope_cases');
        foreach($report['cases'] as $case)self::need(is_array($case)&&in_array($case['status']??'', ['PASS','FAIL','UNREACHED'],true),'envelope_case_status');
    }
    public static function evidence(array $report,string $sha,string $token): void
    {
        self::need(($report['version']??'')===self::VERSION&&($report['sha']??'')===$sha&&($report['token']??'')===$token,'evidence_identity');
        self::need(($report['status']??'')==='PASS'&&($report['native']??false)===true
            &&is_int($report['assertions']??null)&&$report['assertions']>0,'evidence_result');
        self::need(array_keys($report['cases']??[])===self::CASES,'evidence_case_inventory');
        foreach($report['cases'] as $name=>$r)self::need(($r['status']??'')==='PASS'
            &&($r['lane']??'')===(str_starts_with($name,'protected-')||$name==='fresh-process-adoption'?'protected':'instrumented')
            &&is_int($r['assertions']??null)&&$r['assertions']>0,'case_incomplete');
    }
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{
        $mode=$argv[1]??'';
        if($mode==='--help'){echo "Pure M6C native inspection contracts; JSON stdin, explicit action arguments. No Docker or SQL.\n";exit(0);}
        $raw=stream_get_contents(STDIN,1048577);M6CNativeContract::need(strlen($raw)<=1048576,'inspection_bound');
        $data=in_array($mode,['engine','evidence','target','resources'],true)?json_decode($raw,true,32,JSON_THROW_ON_ERROR):m6linuxDecodeInspection($raw);$out='accepted';
        switch($mode){
            case 'engine':$out=M6CNativeContract::engine($data);break;
            case 'mysql-image':$out=M6CNativeContract::mysqlImage($data[0],$argv[2]);break;
            case 'target':M6CNativeContract::target($data);break;
            case 'resources':M6CNativeContract::resources($data,($argv[2]??'')==='1');break;
            case 'runtime-image':$out=M6CNativeContract::runtimeImage($data[0],$argv[2],$argv[3],$argv[4]);break;
            case 'network':M6CNativeContract::network($data[0],$argv[2],$argv[3],$argv[4]===''?[]:explode(',',$argv[4]));break;
            case 'owner':M6CNativeContract::owner($data[0],...array_slice($argv,2));break;
            case 'capture-owner':M6CNativeContract::captureRuntime($data[0],...array_slice($argv,2));break;
            case 'container':$out=json_encode(M6CNativeContract::container($data[0],$data[1],$argv[2],$argv[3],$argv[4],$argv[5],$argv[6],$argv[7],getenv('MYSQL_ROOT_PASSWORD')?:null),JSON_THROW_ON_ERROR);break;
            case 'evidence':M6CNativeContract::evidence($data,$argv[2],$argv[3]);break;
            default:throw new RuntimeException('M6C native: unknown_contract');
        }
        echo $out."\n";
    }catch(Throwable $e){fwrite(STDERR,preg_match('/^(?:M6C native:|M6B guard:) [a-z_]+$/D',$e->getMessage())?$e->getMessage()."\n":"M6C native: malformed_inspection\n");exit(2);}
}
