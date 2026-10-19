<?php
declare(strict_types=1);

// Test-only prerequisites. PDO supplies types/constants to fake SQL; no driver/DSN.
function m6ExecutionDisabledClasses(string|false $value):array {
    if($value===false)return [];
    return array_values(array_filter(array_map('strtolower',array_map('trim',explode(',',$value))),static fn($name):bool=>$name!==''));
}
function m6ExecutionMissingCapabilities():array {
    $missing=[];$disabled=m6ExecutionDisabledClasses(ini_get('disable_classes'));
    foreach(['PDO','PDOStatement','PDOException','ReflectionClass','ReflectionProperty','DateTimeImmutable','DateTimeZone',
        'DirectoryIterator','RecursiveDirectoryIterator','RecursiveIteratorIterator','FilesystemIterator',
        'DOMDocument','DOMXPath','DOMElement','JsonException']as$name){
        if(!class_exists($name,false)||in_array(strtolower($name),$disabled,true))$missing[]=$name;
    }
    foreach(['json_encode','json_decode','hash','random_bytes','preg_match','array_is_list','ctype_digit','filter_var',
        'base64_decode','preg_match_all','preg_replace','bin2hex','hash_equals','clearstatcache','lstat','is_link','fstat','feof',
        'getimagesizefromstring','fsync','libxml_use_internal_errors','fopen','fread','fwrite','fflush','flock','fclose',
        'mkdir','chmod','rename','unlink','rmdir','file_get_contents','file_put_contents','scandir','stat','realpath']as$name){
        if(!function_exists($name))$missing[]=$name;
    }
    foreach(['PDO::FETCH_DEFAULT','PDO::FETCH_ASSOC','LIBXML_DOTTED_VERSION']as$name)if(!defined($name))$missing[]=$name;
    return $missing;
}

function m6ExecutionBaseProfile():array {
    // Keep the selected executable. Reuse explicit parent -c; otherwise let that
    // executable load its installed configuration, even when the parent used -n.
    $ini=php_ini_loaded_file();$flags=$ini===false?[]:['-c',$ini];
    // Arbitrary parent -d options do not propagate. Carry only relevant directives;
    // never propagate auto-prepend/append code or an unbounded memory/time setting.
    foreach(['extension_dir','disable_functions','disable_classes','sys_temp_dir']as$key){
        $value=ini_get($key);if($value!==false&&$value!=='')$flags=array_merge($flags,['-d',$key.'='.$value]);
    }
    $memory=ini_get('memory_limit');$bytes=268435456;
    if($memory!==false&&preg_match('/^([0-9]+)([KMG]?)$/i',$memory,$m)){
        $size=(int)$m[1]*(1024**(int)array_search(strtoupper($m[2]),['','K','M','G'],true));
        if($size>0)$bytes=min($bytes,$size);
    }
    foreach(['memory_limit'=>(string)$bytes,'max_execution_time'=>'10','auto_prepend_file'=>'','auto_append_file'=>'',
        'zend.exception_ignore_args'=>'1','display_errors'=>'stderr','html_errors'=>'0','log_errors'=>'0']as$key=>$value){
        $flags=array_merge($flags,['-d',$key.'='.$value]);
    }
    return ['flags'=>$flags,'label'=>($ini===false?'configured-default':'parent-ini').';bounded;parent-directives'];
}

function m6ExecutionProfile():array {
    $profile=m6ExecutionBaseProfile();$child=__DIR__.'/WebsitePlatformM6CExecutionChild.php';
    $probe=static function(array $profile)use($child):array{
        $caps=null;
        m6process([PHP_BINARY,...$profile['flags'],$child,'--capabilities'],[],10000000000,
            ['case'=>'execution-capabilities','profile'=>$profile['label']],function($result)use(&$caps):void{
                [$caps]=m6childTrace($result,'M6C_CAPABILITIES');
                if(!isset($caps['missing'])||!is_array($caps['missing'])||$result[0]!==($caps['missing']===[]?0:78))throw new RuntimeException('Capability probe failed');
            });
        return $caps;
    };
    $caps=$probe($profile);$added=[];$extensionDir=ini_get('extension_dir');
    // Parent -d extension additions may not be in its INI. Carry only missing
    // fixture modules already loaded by the parent, from its existing extension_dir.
    // No PDO driver, package install, alternate binary or global configuration edit.
    foreach(['pdo'=>['PDO','PDOStatement'],'dom'=>['DOMDocument','DOMXPath','DOMElement'],
        'ctype'=>['ctype_digit'],'filter'=>['filter_var']]as$module=>$needs){
        if(array_intersect($needs,$caps['missing'])===[]||!extension_loaded($module)||$extensionDir===false||$extensionDir==='')continue;
        $file=rtrim($extensionDir,'/\\').DIRECTORY_SEPARATOR.(PHP_OS_FAMILY==='Windows'?'php_'.$module.'.dll':$module.'.so');
        if(is_file($file)&&is_readable($file)){$profile['flags']=array_merge($profile['flags'],['-d','extension='.realpath($file)]);$added[]=$module;}
    }
    if($added!==[]){$profile['label'].=';parent-modules='.implode(',',$added);$caps=$probe($profile);}
    if($caps['missing']!==[]){
        // Run the normal preflight once: its real exit and diagnostic are retained.
        m6process([PHP_BINARY,...$profile['flags'],$child,'no-work'],[],10000000000,
            ['case'=>'execution-prerequisite','profile'=>$profile['label']],static function($r):void{m6childTrace($r,'M6C_EXECUTION_TRACE');});
        throw new RuntimeException('M6C execution prerequisites unavailable');
    }
    return $profile;
}
