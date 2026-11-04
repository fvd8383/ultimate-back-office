<?php
declare(strict_types=1);
/** Real schema orchestration with fake PDO; actual M6C private-file builds, never live SQL/native UID. */
require_once __DIR__.'/support/WebsitePlatformM6ENativeSetup.php';
require_once __DIR__.'/support/WebsitePlatformM6CFixture.php';
require_once __DIR__.'/support/WebsitePlatformM6CSupplementPdf.php';

final class M6EBaseStatement extends PDOStatement {
    public function __construct(private mixed $value){}
    public function fetchColumn(int $column=0):mixed{return $this->value;}
}
final class M6EBaseDatabase extends PDO {
    public array $commands=[];public string $version='8.4.6';public int $seed=1;
    public function __construct(){}
    public function quote(string $string,int $type=PDO::PARAM_STR):string{return "'".str_replace("'","''",$string)."'";}
    public function exec(string $statement):int|false{$this->commands[]=$statement;return 1;}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false {
        $this->commands[]=$query;
        return new M6EBaseStatement(match($query){'SELECT VERSION()'=>$this->version,'SELECT @@server_uuid'=>'22222222-2222-4222-8222-222222222222',
            "SELECT COUNT(*) FROM modules WHERE module_key='247sp'"=>$this->seed,default=>throw new RuntimeException('unexpected_query')});
    }
}
$checks=0;
$check=static function(bool $ok,string $why)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($why);};
$deny=static function(callable $f,string $why)use($check):void{try{$f();}catch(RuntimeException){$check(true,$why);return;}throw new RuntimeException('Expected denial: '.$why);};
$folder=sys_get_temp_dir().DIRECTORY_SEPARATOR.'m6e-bootstrap-'.bin2hex(random_bytes(8));mkdir($folder,0700);$written=[];
try {
    $db=new M6EBaseDatabase();$migrations=0;$admission=['run_id'=>str_repeat('b',32)];
    $write=static function(string $path,string $bytes,int $gid,int $mode)use(&$written,$folder):void {
        $file=$folder.DIRECTORY_SEPARATOR.count($written).'.private';$h=fopen($file,'xb');
        try{if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('private_write');}finally{fclose($h);}
        $written[$path]=['file'=>$file,'gid'=>$gid,'mode'=>$mode,'sha256'=>hash('sha256',$bytes)];
    };
    $identity=m6eSchemaData($db,$admission,$write,static function(PDO $actual)use($db,&$migrations,$check):void {
        $check($actual===$db,'canonical migration adapter receives same session');$migrations++;
    });
    $check($migrations===1&&$identity['migrations']===range(1,25),'single canonical migration boundary');
    $check($identity['seed_247sp']===1&&$identity['schema']==='ubo_m6e_'.str_repeat('b',32),'observed canonical schema and seed');
    $commands=implode("\n",$db->commands);$schema='`'.$identity['schema'].'`';
    $check(str_contains($commands,"GRANT SELECT ON $schema.* TO 'm6e_observer'@'127.0.0.1'"),'observer SELECT only');
    $observer=array_values(array_filter($db->commands,static fn($s)=>str_starts_with($s,'GRANT ')&&str_contains($s,'m6e_observer')));
    $check(count($observer)===1&&!str_contains($observer[0],'_foreign'),'foreign schema not granted');
    $check(str_contains($commands,"GRANT SELECT, INSERT, UPDATE, DELETE ON $schema.* TO 'm6e_worker'"),'separate worker rights');
    $check(str_contains($commands,"GRANT PROCESS ON *.* TO 'm6e_writer'"),'fixture writer observation right');
    $check(count($written)===7,'all generated files accounted (schema baseline measured separately by native wrapper)');
    $snapshot=array_values(array_filter($db->commands,static fn($s)=>str_starts_with($s,'GRANT ')&&str_contains($s,'m6e_snapshot')));
    $check(count($snapshot)===2&&str_contains($snapshot[0],'GRANT SELECT ON `ubo\\_m6e\\_')
        &&str_starts_with($snapshot[1],'GRANT FLUSH_TABLES ON *.*'),'snapshot schema-only read and isolated global read lock');
    $check($written['/run/ubo-m6e-fixture/snapshot-reader.json']['gid']===2000
        &&$written['/run/ubo-m6e-fixture/snapshot-reader.json']['mode']===0640,'snapshot reader credential ownership');
    foreach(['/run/ubo-m6e-fixture/writer.json'=>2106,'/etc/ubo-site-publisher/authority-credentials.json'=>2103,
             '/etc/ubo-site-publisher/application.php'=>2000] as $path=>$gid)
        $check($written[$path]['gid']===$gid&&$written[$path]['mode']===0640,'separate secret role');
    $writer=json_decode(file_get_contents($written['/run/ubo-m6e-fixture/writer.json']['file']),true);
    $observer=json_decode(file_get_contents($written['/etc/ubo-site-publisher/authority-credentials.json']['file']),true);
    $check($writer['password']!==$observer['password'],'distinct credentials');
    $check(!str_contains(json_encode($identity),$writer['password'])&&!str_contains(json_encode($identity),$observer['password']),'safe schema output');
    $check($written['/opt/ubo-m6e/private/config/env.php']['sha256']===hash('sha256',"<?php\nreturn require '/etc/ubo-site-publisher/application.php';\n"),'generated config separate from tracked source');
    $bad=new M6EBaseDatabase();$bad->version='8.0.0';
    $deny(fn()=>m6eSchemaData($bad,$admission,$write,static function(){throw new RuntimeException('migration must not run');}),'wrong server version');
    $bad=new M6EBaseDatabase();$bad->seed=0;
    $deny(fn()=>m6eSchemaData($bad,$admission,$write,static function(){}),'missing canonical seed');

    require_once __DIR__.'/support/WebsitePlatformM6ECommissionFixture.php';
    [$f,$built,$pdf]=m6eLocalServingFixture();$summaries=[];
    foreach($built as $actual){
        $job=$actual['job'];$producer=$actual['producer'];$verified=$actual['verified'];$release=$actual['row'];
        $summary=$actual['summary'];$summaries[]=$summary;
        $check($summary['producer_attempt_id']===$producer['id']&&$summary['build_job_id']===$job['id'],'original producer retained');
        $check($summary['manifest_sha256']===hash_file('sha256',$f->releasePath($job).'/metadata/manifest.json'),'summary actual manifest bytes');
        M6CSupplementPdf::verify($f->releasePath($job),$verified['manifest'],$pdf,$check);
        $bad=$verified;$bad['manifest_hash']=str_repeat('f',64);
        $deny(fn()=>m6eReleaseSummary($release,$job,$producer,$bad),'tampered observed manifest');
        $bad=$producer;$bad['build_job_id']++;
        $deny(fn()=>m6eReleaseSummary($release,$job,$bad,$verified),'wrong original build producer');
        file_put_contents($f->releasePath($job).'/public/index.html','tampered');
        $deny(fn()=>m6eReleaseSummary($release,$job,$producer,$f->store->verifyRelease($job,$producer)),'actual modified artifact');
    }
    $sets=m6eAssetSets($summaries);
    $check(count($sets['shared_assets'])>=2&&count($sets['changed_assets'])>=2&&count($sets['pdf_assets'])===1,'actual shared image/PDF and changed CSS');
    $check($summaries[0]['manifest_sha256']!==$summaries[1]['manifest_sha256'],'distinct actual manifests');
    $deny(fn()=>m6eAssetSets([$summaries[0],$summaries[0]]),'no fabricated changed asset');
    echo "M6E bootstrap fixture controls: $checks assertions PASS (fake PDO; real private M6C artifact bytes; no native identity/service/SQL execution).\n";
} finally {
    WebsitePlatformM6CFixture::cleanup();
    $resolved=realpath($folder);$parent=realpath(sys_get_temp_dir());
    if($resolved===false||dirname($resolved)!==$parent||!preg_match('/^m6e-bootstrap-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('cleanup_scope');
    foreach(new DirectoryIterator($resolved) as $entry)if(!$entry->isDot()){if(!$entry->isFile()||$entry->isLink())throw new RuntimeException('cleanup_entry');unlink($entry->getPathname());}
    rmdir($resolved);
}
