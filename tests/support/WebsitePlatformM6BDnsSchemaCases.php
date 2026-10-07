<?php

declare(strict_types=1);

/** Native-only DNS assertions, called after guarded canonical milestones in owned databases. */
function mysqlDnsSchema(PDO $db,string $milestone): void
{
    $columns=$db->query("SELECT COLUMN_NAME,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_SET_NAME,COLLATION_NAME,
        IS_NULLABLE,COLUMN_DEFAULT,EXTRA,ORDINAL_POSITION,GENERATION_EXPRESSION FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_dns_records' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
    mysqlCheck(count($columns)===16,'DNS table exists with canonical column count: '.$milestone);
    foreach(['domain_name'=>255,'record_type'=>20,'host'=>255,'value'=>500]as$name=>$length){
        $column=$columns[$name];
        mysqlCheck($column['DATA_TYPE']==='varchar'&&(int)$column['CHARACTER_MAXIMUM_LENGTH']===$length
            &&$column['CHARACTER_SET_NAME']==='utf8mb4'&&$column['COLLATION_NAME']==='utf8mb4_unicode_ci'
            &&$column['IS_NULLABLE']==='NO','DNS original full-length utf8mb4 column: '.$name.' '.$milestone);
    }
    $hash=$columns['record_hash'];
    mysqlCheck($hash['DATA_TYPE']==='char'&&(int)$hash['CHARACTER_MAXIMUM_LENGTH']===64
        &&$hash['EXTRA']==='STORED GENERATED'&&$hash['GENERATION_EXPRESSION']!==''
        &&(int)$hash['ORDINAL_POSITION']===(int)$columns['value']['ORDINAL_POSITION']+1,'DNS generated STORED hash after value: '.$milestone);
    mysqlCheck((string)$columns['ttl']['COLUMN_DEFAULT']==='1800'&&$columns['status']['COLUMN_DEFAULT']==='planned'
        &&$columns['priority']['IS_NULLABLE']==='YES','DNS defaults/nullability: '.$milestone);
    $indexes=$db->query("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_dns_records' ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll();
    $unique=array_values(array_filter($indexes,static fn($index)=>$index['INDEX_NAME']==='uq_domain_dns_record_hash'));
    mysqlCheck(count($unique)===1&&(int)$unique[0]['NON_UNIQUE']===0&&$unique[0]['COLUMN_NAME']==='record_hash'
        &&$unique[0]['SUB_PART']===null,'DNS unique full hash index: '.$milestone);
    mysqlCheck(array_filter($indexes,static fn($index)=>$index['INDEX_NAME']==='uq_domain_dns_record')===[],
        'DNS oversized composite index absent: '.$milestone);
    $foreign=$db->query("SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
        WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='domain_dns_records' ORDER BY k.COLUMN_NAME")->fetchAll();
    mysqlCheck($foreign===[
        ['COLUMN_NAME'=>'business_id','REFERENCED_TABLE_NAME'=>'businesses','REFERENCED_COLUMN_NAME'=>'id','DELETE_RULE'=>'CASCADE'],
        ['COLUMN_NAME'=>'domain_assignment_id','REFERENCED_TABLE_NAME'=>'domain_assignments','REFERENCED_COLUMN_NAME'=>'id','DELETE_RULE'=>'SET NULL'],
        ['COLUMN_NAME'=>'domain_request_id','REFERENCED_TABLE_NAME'=>'domain_requests','REFERENCED_COLUMN_NAME'=>'id','DELETE_RULE'=>'SET NULL'],
    ],'DNS original foreign-key relationships/deletion rules: '.$milestone);
    mysqlCheck(!$db->getAttribute(PDO::ATTR_EMULATE_PREPARES),'DNS assertions retain native prepares: '.$milestone);
}

function mysqlDnsIdentityCases(PDO $db,int $business): void
{
    // DML only in this transaction; rollback never claims to undo the fixture DDL below.
    $db->beginTransaction();
    try{
        $values=['business_id'=>$business,'domain_name'=>str_repeat('D',255),'record_type'=>str_repeat('t',20),
            'host'=>str_repeat('H',255),'value'=>str_repeat("\u{1F642}",499).'A'];
        $first=m6mysqlInsert($db,'domain_dns_records',$values);
        $row=SiteBuildStore::one($db,'SELECT domain_name,record_type,host,value,record_hash,CHAR_LENGTH(value) AS value_length FROM domain_dns_records WHERE id=:id',['id'=>$first]);
        foreach(['domain_name','record_type','host','value']as$column)mysqlCheck($row[$column]===$values[$column],'DNS full-length value round-trips: '.$column);
        mysqlCheck((int)$row['value_length']===500,'DNS 500 characters including utf8mb4 preserved');
        $expected=hash('sha256',strtolower($values['domain_name']).'|'.strtoupper($values['record_type']).'|'.strtolower($values['host']).'|'.$values['value']);
        mysqlCheck($row['record_hash']===$expected,'DNS generated hash follows existing 020 expression');
        mysqlReject($db,fn()=>m6mysqlInsert($db,'domain_dns_records',$values),[1062]);
        $normalized=$values;$normalized['domain_name']=strtolower($values['domain_name']);
        $normalized['record_type']=strtoupper($values['record_type']);$normalized['host']=strtolower($values['host']);
        mysqlReject($db,fn()=>m6mysqlInsert($db,'domain_dns_records',$normalized),[1062]);
        $different=$values;$different['value']=str_repeat("\u{1F642}",499).'B';
        $second=m6mysqlInsert($db,'domain_dns_records',$different);
        $other=SiteBuildStore::one($db,'SELECT value,record_hash FROM domain_dns_records WHERE id=:id',['id'=>$second]);
        mysqlCheck($other['value']===$different['value']&&$other['record_hash']!==$row['record_hash'],
            'DNS records differing after 499 shared characters remain distinct');
        $caseSensitive=$values;$caseSensitive['value']=str_repeat("\u{1F642}",499).'a';
        $third=m6mysqlInsert($db,'domain_dns_records',$caseSensitive);
        mysqlCheck($third!==$first,'DNS value remains case-sensitive in the adopted 020 hash identity');
    }finally{if($db->inTransaction())$db->rollBack();}
}

function mysqlDnsRepairFixtures(PDO $db): void
{
    // Separate repair-path coverage after the owned upgrade sequence/snapshot assertions.
    // The fresh database used by all business/concurrency acceptance cases is not touched.
    mysqlCheck((int)mysqlMigrationScalar($db,'SELECT COUNT(*) FROM domain_dns_records')===0,'Repair fixtures require empty owned DNS table');
    $before=m6mysqlSnapshot($db);
    echo "Separate DNS repair fixtures after canonical upgrade; not canonical sequence evidence.\n";
    m6mysqlMigrationStatement($db,'DROP TABLE domain_dns_records','native-dns-repair-fixture',1);
    mysqlCheck((int)mysqlMigrationScalar($db,"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_dns_records'")===0,
        '019 repair fixture starts with DNS table absent');
    m6mysqlMigrate($db,19,19);
    mysqlDnsSchema($db,'separate 019 table-absent creation');
    m6mysqlMigrationStatement($db,'ALTER TABLE domain_dns_records DROP INDEX uq_domain_dns_record_hash, DROP COLUMN record_hash','native-dns-repair-fixture',2);
    mysqlCheck((int)mysqlMigrationScalar($db,"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_dns_records' AND COLUMN_NAME='record_hash'")===0,
        '020 repair fixture has no generated hash column');
    mysqlCheck((int)mysqlMigrationScalar($db,"SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_dns_records' AND INDEX_NAME='uq_domain_dns_record_hash'")===0,
        '020 repair fixture has no hash index');
    m6mysqlMigrate($db,20,20);
    mysqlDnsSchema($db,'separate 020 deficient-table repair');
    mysqlCheck(m6mysqlSnapshot($db)===$before,'Repair fixtures restore canonical table inventory and preserve all upgrade fixture data');
    // Any DDL failure exits to the existing ownership-limited database cleanup; no DDL rollback.
}
