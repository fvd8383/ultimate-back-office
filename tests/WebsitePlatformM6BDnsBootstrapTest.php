<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BSql.php';
require_once __DIR__ . '/support/WebsitePlatformM6BScope.php';
$root=dirname(__DIR__);$assertions=0;
function dnsCheck(bool $ok,string $why):void{global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why);}
function dnsOriginal(string $path):string{
    global $root;
    $process=proc_open(['git','-C',$root,'show','5baae28c9af68cca7694a912d7f35c87c50c9dc5:'.$path],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$sql=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);dnsCheck(proc_close($process)===0&&$error==='','Baseline source available');return $sql;
}
function dnsCreate(array $statements):array{
    $matches=array_filter($statements,static fn($sql)=>preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?domain_dns_records \(/',$sql));
    dnsCheck(count($matches)===1,'Exactly one canonical DNS CREATE');return $matches;
}
$referencePath='database/migrations/020_repair_domain_dns_records.sql';
$reference=str_replace("\r\n","\n",file_get_contents($root.'/'.$referencePath));
dnsCheck($reference===dnsOriginal($referencePath),'020 is byte-identical canonical reference');
$referenceTable=array_values(dnsCreate(m6bSqlStatements($reference)))[0];
preg_match('/    record_hash CHAR\(64\) GENERATED ALWAYS AS \(\n.*\n    \) STORED,\n/',$reference,$matches);
dnsCheck(count($matches)===1,'Exact three-line generated STORED definition');$hashColumn=$matches[0];
$oldKey='UNIQUE KEY uq_domain_dns_record (domain_name, record_type, host, value)';
$newKey='UNIQUE KEY uq_domain_dns_record_hash (record_hash)';
$originals=[];
foreach(m6bApprovedDnsMigrationBlobs()as$path=>$blob){
    $old=dnsOriginal($path);$originals[$path]=$old;
    $new=str_replace("\r\n","\n",file_get_contents($root.'/'.$path));
    $oldStatements=m6bSqlStatements($old);$newStatements=m6bSqlStatements($new);
    $oldTable=dnsCreate($oldStatements);$index=array_key_first($oldTable);$oldTable=$oldTable[$index];
    if(str_starts_with(basename($path),'017'))dnsCheck($index===2&&count($oldStatements)===5,'017 statement 3 / five statements');
    $lengths=[];
    foreach(['domain_name','record_type','host','value']as$column){
        dnsCheck(preg_match('/\b'.$column.' VARCHAR\(([0-9]+)\) NOT NULL/',$oldTable,$length)===1,'Original full-length column '.$column);
        $lengths[]=(int)$length[1];
    }
    dnsCheck($lengths===[255,20,255,500]&&array_sum($lengths)*4===4120&&4120>3072,'Original utf8mb4 composite width exceeds standard InnoDB limit');
    dnsCheck(str_contains($oldTable,'CHARSET=utf8mb4')&&str_contains($oldTable,$oldKey),'Width applies to actual original unique index');
    $expected=str_replace("    value VARCHAR(500) NOT NULL,\n","    value VARCHAR(500) NOT NULL,\n".$hashColumn,$old,$added);
    $expected=str_replace($oldKey,$newKey,$expected,$replaced);
    dnsCheck($added===1&&$replaced===1&&$new===$expected,'Only exact column/key correction; all other file bytes preserved');
    dnsCheck(substr($newStatements[$index],strpos($newStatements[$index],'('))===substr($referenceTable,strpos($referenceTable,'(')),
        'Entire DNS table body matches 020: expression/position/types/lengths/defaults/charset/indexes/FKs');
    dnsCheck(!str_contains($newStatements[$index],$oldKey),'Oversized composite key absent');
    dnsCheck(count($oldStatements)===count($newStatements),'Statement count stable');
    foreach($oldStatements as$i=>$sql)if($i!==$index)dnsCheck($sql===$newStatements[$i],'Unrelated statement/order unchanged');
    dnsCheck(sha1('blob '.strlen($new)."\0".$new)===$blob,'Corrected file matches exact approved Git blob');
    $launcher=file_get_contents($root.'/tests/RunM6BMySql.sh');
    dnsCheck(str_contains($launcher,basename($path).') blob='.$blob.';;'),'Linux launcher pins same exact filename/blob');
    echo basename($path).' statements='.count($newStatements).' DNS statement='.($index+1)."\n";
    echo 'Original blob='.sha1('blob '.strlen($old)."\0".$old).' SHA256='.hash('sha256',$old)."\n";
    echo 'Corrected blob='.$blob.' SHA256='.hash('sha256',$new)."\n";
}
dnsCheck(m6bCanonicalMigrationsValid($root),'Only approved canonical sources accepted');
$temp=sys_get_temp_dir().'/ubo-m6b-dns-integrity-'.bin2hex(random_bytes(8));
mkdir($temp.'/database/migrations',0700,true);
try{
    foreach(glob($root.'/database/migrations/*.sql')as$file)copy($file,$temp.'/database/migrations/'.basename($file));
    dnsCheck(m6bCanonicalMigrationsValid($temp),'Exact copied inventory accepted');
    foreach(['001_create_platform_foundation.sql','017_domain_services_automation.sql','019_repair_domain_services_schema.sql',
        '020_repair_domain_dns_records.sql','025_site_build_deployment.sql']as$file){
        $path=$temp.'/database/migrations/'.$file;$before=file_get_contents($path);
        file_put_contents($path,$before."\n-- unapproved change\n");
        dnsCheck(!m6bCanonicalMigrationsValid($temp),'Changed migration rejected: '.$file);
        file_put_contents($path,$before);
    }
    foreach($originals as$relative=>$old){
        $path=$temp.'/'.$relative;$before=file_get_contents($path);file_put_contents($path,$old);
        dnsCheck(!m6bCanonicalMigrationsValid($temp),'Original defective content is not an approved exception');file_put_contents($path,$before);
    }
    $path=$temp.'/database/migrations/017_domain_services_automation.sql';$before=file_get_contents($path);unlink($path);
    dnsCheck(!m6bCanonicalMigrationsValid($temp),'Missing corrected migration rejected');file_put_contents($path,$before);
    rename($path,$temp.'/database/migrations/017_renamed.sql');
    dnsCheck(!m6bCanonicalMigrationsValid($temp),'Same-count renamed migration rejected');rename($temp.'/database/migrations/017_renamed.sql',$path);
    foreach(['026_unapproved.sql','019_duplicate.sql']as$file){
        file_put_contents($temp.'/database/migrations/'.$file,'SELECT 1;');
        dnsCheck(!m6bCanonicalMigrationsValid($temp),'Unexpected additional migration rejected');unlink($temp.'/database/migrations/'.$file);
    }
    dnsCheck(m6bCanonicalMigrationsValid($temp),'Only exact restored patch accepted after negative cases');
}finally{
    // Only this freshly created fixture's flat SQL directory, never the repository.
    dnsCheck(str_starts_with(realpath($temp),realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'ubo-m6b-dns-integrity-'),'Owned fixture cleanup boundary');
    foreach(glob($temp.'/database/migrations/*.sql')as$file)unlink($file);
    rmdir($temp.'/database/migrations');rmdir($temp.'/database');rmdir($temp);
}
echo "Website platform M6B DNS bootstrap: $assertions assertions passed; static source/integrity only, no SQL execution.\n";
