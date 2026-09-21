<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM6BDependencies.php';
require_once __DIR__ . '/WebsitePlatformM6BMigrations.php';
require_once __DIR__ . '/WebsitePlatformM6BLinuxGuard.php';
require_once dirname(__DIR__,2) . '/private/classes/SiteCompositionEditor.php';

function m6mysqlProcess(array $command): string
{
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Local process unavailable.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $out='';$bytes=0;$end=microtime(true)+15;
    do{
        $out.=stream_get_contents($pipes[1]);$bytes+=strlen(stream_get_contents($pipes[2]));
        $status=proc_get_status($process);
        if(strlen($out)+$bytes>1048576||microtime(true)>$end){
            proc_terminate($process,9);foreach([1,2]as$i)fclose($pipes[$i]);proc_close($process);
            throw new RuntimeException('Local Docker inspection exceeded its time/output bound.');
        }
        if(!$status['running']&&feof($pipes[1])&&feof($pipes[2]))break;
        usleep(10000);
    }while(true);
    fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);
    if(($status['exitcode']>=0?$status['exitcode']:$closed)!==0)throw new RuntimeException('Local Docker identity inspection failed.');
    return $out;
}
/** Verify local engine AND exact newly created container BEFORE any SQL/connection. */
function m6mysqlIdentity(): array
{
    $token=getenv('M6B_MYSQL_RUN_TOKEN');$id=getenv('M6B_MYSQL_CONTAINER_ID');$port=getenv('M6B_MYSQL_PORT');
    if(!is_string($token)||!preg_match('/^[a-f0-9]{32}$/D',$token)||!is_string($id)||!preg_match('/^[a-f0-9]{64}$/D',$id)
        ||!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535||!getenv('M6B_MYSQL_PASSWORD')){
        throw new RuntimeException('NOT EXECUTED: use the explicit tests/RunM6BMySql.ps1 or .sh launcher with local Docker and PDO MySQL.');
    }
    if(getenv('M6B_LINUX_RUN')==='1'){
        $socket=(string)getenv('M6B_DOCKER_SOCKET');$platform=(string)getenv('M6B_IMAGE_PLATFORM');$digest=(string)getenv('M6B_IMAGE_DIGEST');
        m6linuxRequire(PHP_OS_FAMILY==='Linux'&&function_exists('posix_geteuid')&&posix_geteuid()!==0,'linux_user');
        m6linuxRequire($socket==='/run/user/'.posix_geteuid().'/docker.sock'&&realpath($socket)===$socket
            &&filetype($socket)==='socket'&&fileowner($socket)===posix_geteuid(),'socket_owner');
        m6linuxRequire(!getenv('DOCKER_HOST')&&!getenv('DOCKER_CONTEXT')&&!getenv('DOCKER_TLS_VERIFY')&&!getenv('DOCKER_CERT_PATH'),'docker_override');
        m6linuxRequire(ini_get('memory_limit')==='256M','php_memory_limit');
        $clientConfig=(string)getenv('M6B_DOCKER_CONFIG');
        m6linuxRequire(!getenv('DOCKER_CONFIG'),'inherited_client_config');
        m6linuxClientConfig($clientConfig,posix_geteuid());
        $command=['docker','--config',$clientConfig,'--host','unix://'.$socket];
        $inspect=static fn(array $args):array=>m6linuxDecodeInspection(m6mysqlProcess(array_merge($command,$args)),$args[0]==='info');
        m6linuxEngine($inspect(['info','--format','{{json .}}']),$platform);
        $image=$inspect(['image','inspect','docker.io/library/mysql@'.$digest])[0];
        m6linuxRequire(m6linuxImage($image,$digest,$platform)===getenv('M6B_MYSQL_IMAGE_ID'),'image_id_changed');
        $container=$inspect(['inspect',$id])[0];
        return m6linuxMySqlIdentity($container,$image,$token,(string)getenv('M6B_MYSQL_IMAGE_ID'),$id,(string)getenv('M6B_MYSQL_PASSWORD'),$port);
    }
    $override=getenv('DOCKER_HOST');
    if($override!==false&&$override!==''&&!preg_match('~^(npipe://|unix://)~',$override))throw new RuntimeException('Remote DOCKER_HOST override refused.');
    $context=json_decode(m6mysqlProcess(['docker','context','inspect']),true,512,JSON_THROW_ON_ERROR)[0];
    if(!preg_match('~^(npipe://|unix://)~',$context['Endpoints']['docker']['Host']??''))throw new RuntimeException('Remote Docker endpoint refused.');
    $container=json_decode(m6mysqlProcess(['docker','inspect',$id]),true,512,JSON_THROW_ON_ERROR)[0];
    $bindings=$container['NetworkSettings']['Ports']['3306/tcp']??[];
    if($container['Id']!==$id||($container['Config']['Labels']['ubo.m6b.owner']??'')!==$token
        ||$container['Name']!=='/ubo-m6b-'.$token||$container['Image']!==getenv('M6B_MYSQL_IMAGE_ID')
        ||!($container['State']['Running']??false)||count($bindings)!==1
        ||$bindings[0]['HostIp']!=='127.0.0.1'||$bindings[0]['HostPort']!==$port
        ||!isset($container['HostConfig']['Tmpfs']['/var/lib/mysql'])
        ||array_filter($container['Mounts']??[],static fn($mount)=>$mount['Type']!=='tmpfs'||$mount['Destination']!=='/var/lib/mysql')!==[]){
        throw new RuntimeException('Disposable loopback container identity/ownership mismatch.');
    }
    return ['token'=>$token,'id'=>$id,'port'=>(int)$port,'hostname'=>$container['Config']['Hostname']];
}
function m6mysqlConnect(array $identity,?string $database=null,bool $wait=false): PDO
{
    if($database!==null&&!preg_match('/^ubo_m6b_'.substr($identity['token'],0,16).'_(fresh|upgrade)$/D',$database))throw new RuntimeException('Database ownership mismatch.');
    $dsn='mysql:host=127.0.0.1;port='.$identity['port'].($database===null?'':';dbname='.$database).';charset=utf8mb4';
    $deadline=microtime(true)+($wait?55:0);
    do{
        try{$pdo=new PDO($dsn,'root',getenv('M6B_MYSQL_PASSWORD'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>2]);break;
        }catch(PDOException $e){if(microtime(true)>=$deadline)throw new RuntimeException('Disposable local MySQL connection unavailable.');usleep(200000);}
    }while(true);
    $server=$pdo->query('SELECT @@hostname AS hostname, @@version AS version, @@transaction_isolation AS isolation_level')->fetch();
    if($server['hostname']!==$identity['hostname']||!str_starts_with($server['version'],'8.4.'))throw new RuntimeException('Only the verified disposable MySQL 8.4 server is accepted.');
    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
    if($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))throw new RuntimeException('Native PDO prepares are required.');
    return $pdo;
}
function m6mysqlWire(PDO $pdo): WebsitePlatformM6BDependencies
{
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$pdo);
    return WebsitePlatformM6BDependencies::wire();
}
function m6mysqlInsert(PDO $db,string $table,array $values): int
{
    if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Invalid fixture table');
    $query=$db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES (:'.implode(',:',array_keys($values)).')');
    $query->execute($values);return (int)$db->lastInsertId();
}
function m6mysqlFixture(PDO $db): array
{
    $runtime=m6mysqlWire($db);$key=bin2hex(random_bytes(8));
    $ids=[];
    foreach(['requester','operator','owner']as$type)$ids[$type]=m6mysqlInsert($db,'users',[
        'first_name'=>'Synthetic','last_name'=>$type,'email'=>$key.'-'.$type.'@example.invalid','status'=>'active']);
    $role=m6mysqlInsert($db,'roles',['name'=>'Admin','scope'=>'internal']);
    $operatorRole=m6mysqlInsert($db,'roles',['name'=>'Super Admin','scope'=>'internal']);
    m6mysqlInsert($db,'user_roles',['user_id'=>$ids['requester'],'role_id'=>$role]);
    m6mysqlInsert($db,'user_roles',['user_id'=>$ids['operator'],'role_id'=>$operatorRole]);
    $business=m6mysqlInsert($db,'businesses',['business_name'=>'M6B synthetic '.$key,'owner_user_id'=>$ids['owner'],
        'phone'=>'5550100000','email'=>$key.'@example.invalid','address_line_1'=>'1 Test St','city'=>'Test',
        'state'=>'NY','postal_code'=>'10000','country'=>'US']);
    $module=(int)$db->query("SELECT id FROM modules WHERE module_key='247sp'")->fetchColumn();
    m6mysqlInsert($db,'business_modules',['business_id'=>$business,'module_id'=>$module,'status'=>'active']);
    $site=m6mysqlInsert($db,'sites',['site_key'=>SiteServiceSupport::uuidV4(),'purpose'=>'247sp','lifecycle_status'=>'draft']);
    $association=m6mysqlInsert($db,'site_business_associations',['site_id'=>$site,'business_id'=>$business,
        'association_role'=>'customer','status'=>'active','effective_at'=>'2026-09-19 00:00:00']);
    $revision=m6mysqlInsert($db,'site_revisions',['site_id'=>$site,'revision_number'=>1,'materiality'=>'material',
        'snapshot_schema_version'=>1,'facts_snapshot_json'=>'{"business":{"display_name":"Synthetic business"},"private":"PRIVATE-CANARY"}',
        'source_references_json'=>CanonicalJson::encode(['business'=>['business_id'=>$business]]),'snapshot_hash'=>str_repeat('0',64)]);
    SiteCompositionEditor::apply($ids['requester'],$revision,['operation'=>'initialize_new','expected_snapshot_hash'=>str_repeat('0',64)]);
    $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved', review_ready_at=UTC_TIMESTAMP() WHERE id=?")->execute([$revision]);
    $db->prepare("UPDATE sites SET lifecycle_status='approved' WHERE id=?")->execute([$site]);
    foreach(['customer','internal']as$type)m6mysqlInsert($db,'site_approvals',['site_id'=>$site,'revision_id'=>$revision,
        'approval_type'=>$type,'actor_type'=>$type==='customer'?'customer':'internal_admin','state'=>'approved',
        'requested_at'=>'2026-09-19 00:00:00','decided_at'=>'2026-09-19 00:00:00','metadata_json'=>'{}']);
    $hash=SiteBuildStore::one($db,'SELECT snapshot_hash FROM site_revisions WHERE id=:id',['id'=>$revision])['snapshot_hash'];
    $runtime->operator=$ids['operator'];
    return $ids+['site_id'=>$site,'revision_id'=>$revision,'hash'=>$hash,'business_id'=>$business,'association_id'=>$association,'role_id'=>$role];
}
function m6mysqlRequest(array $fixture,?int $actor=null): array
{
    return SiteBuildService::requestBuild($actor??$fixture['requester'],['site_id'=>$fixture['site_id'],
        'revision_id'=>$fixture['revision_id'],'expected_snapshot_hash'=>$fixture['hash'],'build_profile'=>SiteBuildContract::PROFILE]);
}
function m6mysqlJob(PDO $db,int $id): array {return SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>$id]);}
function m6mysqlRevoke(PDO $db,array $fixture,string $kind): void
{
    match($kind){
        'deactivate'=>$db->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$fixture['requester']]),
        'delete'=>$db->prepare('DELETE FROM users WHERE id=?')->execute([$fixture['requester']]),
        'grant'=>$db->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$fixture['requester']]),
        'scope'=>$db->prepare("UPDATE roles SET scope='business' WHERE id=?")->execute([$fixture['role_id']]),
        'name'=>$db->prepare("UPDATE roles SET name='Support' WHERE id=?")->execute([$fixture['role_id']]),
        default=>throw new RuntimeException('Unknown synthetic revocation'),
    };
}
function m6mysqlSnapshot(PDO $db): array
{
    $snapshot=[];
    foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$table){
        $rows=$db->query('SELECT * FROM `'.str_replace('`','``',$table).'`')->fetchAll();
        $canonical=array_map([CanonicalJson::class,'encode'],$rows);sort($canonical,SORT_STRING);
        $snapshot[$table]=hash('sha256',implode("\n",$canonical));
    }
    return $snapshot;
}

/** Native PDO adapter supplies process barriers/faults while forwarding all real SQL. */
final class M6NativeStatement extends PDOStatement
{
    public function __construct(private M6NativeConnection $owner,private PDOStatement $inner,private string $sql){}
    public function execute(?array $params=null):bool{
        if($this->owner->faultTable!==null&&str_starts_with($this->sql,'INSERT INTO '.$this->owner->faultTable)){
            if($this->owner->faultTable==='site_events')$params['actor_user_id']=9223372036854775807;
            else $params['result']='invalid';
        }
        $result=$this->inner->execute($params);
        if($this->owner->holdQueue&&str_contains($this->sql,"status IN ('requested','retry_wait')")){
            $this->owner->holdQueue=false;echo "QUEUE_SELECTED\n";flush();
            if(trim((string)fgets(STDIN))!=='RELEASE_QUEUE')throw new RuntimeException('Missing queue barrier release.');
        }
        if(str_starts_with($this->sql,'INSERT INTO site_events')&&($params['event_type']??'')==='site_build_started')$this->owner->claimed=true;
        if(str_starts_with($this->sql,'INSERT INTO site_events')&&($params['event_type']??'')==='site_build_failed'
            &&($params['reason']??'')==='policy_unsupported')$this->owner->retired=true;
        return $result;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->inner->fetch($mode,$cursorOrientation,$cursorOffset);}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->inner->fetchAll($mode,...$args);}
    public function fetchColumn(int $column=0):mixed{return $this->inner->fetchColumn($column);}
    public function rowCount():int{return $this->inner->rowCount();}
}
final class M6NativeConnection extends PDO
{
    public bool $claimed=false; public bool $holdClaim=false; public bool $holdQueue=false; public ?string $faultTable=null;
    public bool $retired=false; public bool $holdRetirement=false;
    public function __construct(public PDO $inner){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new M6NativeStatement($this,$this->inner->prepare($query,$options),$query);}
    public function beginTransaction():bool{return $this->inner->beginTransaction();}
    public function inTransaction():bool{return $this->inner->inTransaction();}
    public function rollBack():bool{return $this->inner->rollBack();}
    public function commit():bool{
        if($this->holdRetirement&&$this->retired){echo "RETIREMENT_LOCKED\n";flush();
            if(trim((string)fgets(STDIN))!=='RELEASE_RETIREMENT')throw new RuntimeException('Missing retirement barrier release.');$this->holdRetirement=false;}
        if($this->holdClaim&&$this->claimed){echo "CLAIM_LOCKED\n";flush();if(trim((string)fgets(STDIN))!=='RELEASE')throw new RuntimeException('Missing claim barrier release.');$this->holdClaim=false;}
        return $this->inner->commit();
    }
    public function lastInsertId(?string $name=null):string|false{return $this->inner->lastInsertId($name);}
}
