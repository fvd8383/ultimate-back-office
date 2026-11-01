<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeContract.php';
require_once __DIR__.'/WebsitePlatformM6CNativeGitSource.php';

final class M6DNativeRuntime
{
    public const ROOT='/run/ubo-m6d';
    private static function protectedFile(string $path,int $owner,int $mode,int $limit): string
    {
        $s=lstat($path);
        M6DNativeContract::need($s!==false && ($s['mode']&0170000)===0100000 && ($s['mode']&0777)===$mode
            && $s['uid']===$owner && $s['nlink']===1 && $s['size']<=$limit && realpath($path)===$path,'protected_file');
        $v=file_get_contents($path); M6DNativeContract::need(is_string($v) && strlen($v)<=$limit,'protected_read'); return $v;
    }
    /** Read-only prerequisites: no SQL, credentials read, fixture writes or publisher calls. */
    public static function admit(string $sha,string $image,string $token): array
    {
        M6DNativeContract::need(PHP_SAPI==='cli' && PHP_OS_FAMILY==='Linux' && function_exists('posix_geteuid') && posix_geteuid()===2000,'linux_isolated_worker');
        M6DNativeContract::need(extension_loaded('pdo_mysql') && function_exists('posix_kill') && function_exists('pcntl_alarm'),'native_capability_missing');
        foreach(['/','/run',self::ROOT] as $p) {
            $s=lstat($p); M6DNativeContract::need($s!==false && ($s['mode']&0170000)===0040000 && $s['uid']===0
                && ($s['mode']&0022)===0 && realpath($p)===$p,'protected_ancestor');
        }
        $a=json_decode(self::protectedFile(self::ROOT.'/admission.json',0,0644,8192),true,32,JSON_THROW_ON_ERROR);
        M6DNativeContract::binding($a,$sha,$image,$token);
        M6DNativeContract::need(gethostname()===$a['runtime_hostname'] && ini_get('memory_limit')==='256M','runtime_identity');
        $status=file_get_contents('/proc/self/status');
        M6DNativeContract::need((bool)preg_match('/^NoNewPrivs:\s+1$/m',$status),'no_new_privileges');
        foreach(['CapInh','CapPrm','CapEff','CapBnd','CapAmb'] as $key) M6DNativeContract::need((bool)preg_match('/^'.$key.':\s+0+$/m',$status),'capabilities');
        M6DNativeContract::need(trim(file_get_contents('/sys/fs/cgroup/memory.max'))==='536870912'
            && trim(file_get_contents('/sys/fs/cgroup/pids.max'))==='32','actual_cgroup_limits');
        $mounts=file_get_contents('/proc/mounts');
        M6DNativeContract::need((bool)preg_match('~\S+ /run/ubo-m6d/state tmpfs [^\n]*size=131072k~',$mounts),'actual_tmpfs_bound');
        foreach(['state','reports'] as $dir) {
            $s=lstat(self::ROOT.'/'.$dir); M6DNativeContract::need($s!==false && ($s['mode']&0170000)===0040000
                && $s['uid']===2000 && ($s['mode']&0777)===0700 && realpath(self::ROOT.'/'.$dir)===self::ROOT.'/'.$dir,'private_owned_root');
        }
        $repo=realpath(dirname(__DIR__,2));
        M6DNativeContract::need((bool)preg_match('~\S+ '.preg_quote($repo,'~').' \S+ ro[, ]~',$mounts),'readonly_source_mount');
        M6CNativeGitSource::verify($repo,$sha,mode:'m6d-admission');
        // Host preparer attests the internal network and exact image after read-only inspection.
        // Namespace binding + root-owned attestation is required; caller JSON/ENV is not accepted.
        return $a;
    }
    public static function connect(array $admission): PDO
    {
        $c=json_decode(self::protectedFile(self::ROOT.'/database.json',2000,0600,4096),true,16,JSON_THROW_ON_ERROR);
        M6DNativeContract::need(array_keys($c)===['password'] && is_string($c['password']) && strlen($c['password'])>=32 && strlen($c['password'])<=128,'disposable_credentials');
        $db=new PDO('mysql:host='.$admission['database_hostname'].';port=3306;dbname='.$admission['database_name'].';charset=utf8mb4',
            $admission['database_user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
        $r=$db->query('SELECT DATABASE() AS db, @@hostname AS host, @@server_uuid AS uuid, VERSION() AS version, CURRENT_USER() AS account')->fetch();
        M6DNativeContract::need($r['db']===$admission['database_name'] && $r['host']===$admission['database_hostname']
            && $r['uuid']===$admission['mysql_server_uuid'] && str_starts_with($r['version'],'8.4.')
            && str_starts_with($r['account'],$admission['database_user'].'@'),'mysql_identity');
        $db->exec("SET SESSION time_zone = '+00:00', innodb_lock_wait_timeout = 10");
        return $db;
    }
    public static function publish(array &$report): void
    {
        $path=self::ROOT.'/reports/report.json';
        M6DNativeContract::need(!file_exists($path) && !is_link($path),'report_exists');
        $report['publication']='PASS'; $json=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        M6DNativeContract::need(strlen($json)<=1048576,'report_bound');
        $old=umask(0077); $file=fopen($path.'.pending','xb'); umask($old);
        M6DNativeContract::need(is_resource($file),'report_open');
        try {
            M6DNativeContract::need(fwrite($file,$json)===strlen($json) && fflush($file) && fsync($file),'report_write');
        } finally { fclose($file); }
        M6DNativeContract::need(rename($path.'.pending',$path),'report_publish');
        echo 'M6D report sha256='.hash_file('sha256',$path)."\n";
    }
}
