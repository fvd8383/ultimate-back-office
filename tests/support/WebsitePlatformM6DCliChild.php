<?php
declare(strict_types=1);
// Isolated synthetic configuration. The real developer application config is never consulted.
require_once dirname(__DIR__,2).'/private/classes/Database.php';
final class M6DCliNoSql extends PDO
{
    public int $calls=0;
    public function __construct() {}
    public function inTransaction(): bool { return false; }
    public function beginTransaction(): bool { $this->calls++; throw new RuntimeException('synthetic_cli_sql_forbidden'); }
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->calls++; throw new RuntimeException('synthetic_cli_sql_forbidden'); }
}
$db=new M6DCliNoSql();
(new ReflectionProperty(Database::class,'connection'))->setValue(null,$db);
(new ReflectionProperty(Database::class,'config'))->setValue(null,['APP_ENV'=>'synthetic-denied','DB_HOST'=>'invalid.invalid',
    'DB_NAME'=>'synthetic-only','DB_USER'=>'synthetic-only','DB_PASSWORD'=>'SYNTHETIC-DO-NOT-CONNECT','DB_PORT'=>0]);
$entry=$argv[1] ?? ''; $args=array_slice($argv,2);
if(!in_array($entry,['site-deployment-worker.php','reconcile-site-jobs.php'],true)) exit(2);
$script=dirname(__DIR__,2).'/scripts/'.$entry;
// This is intentionally not the authenticated protected entry identity, on every OS.
$_SERVER['SCRIPT_FILENAME']=__FILE__; $argv=array_merge([$script],$args); $argc=count($argv);
register_shutdown_function(static function() use($db): void {
    echo 'M6D_CLI_TRACE '.json_encode(['sql_calls'=>$db->calls,'synthetic_config'=>true])."\n";
});
require $script;
