<?php
declare(strict_types=1);

// Deterministic process faults only. Never loads application/fixture configuration.
$case=$argv[1]??'';
if($case==='startup')new M6CMissingFixtureDependency();
if($case==='malformed'){
    register_shutdown_function(static function():void{fwrite(STDERR,"\nM6C_EXECUTION_TRACE:{broken}\n");});
    m6cMissingFixtureFunction();
}
if($case==='missing'){
    fwrite(STDERR,"Build-only invocation stopped: controlled_failure.\npassword=PRIVATE-PROCESS-CANARY\n");
    fwrite(STDERR,"Uncaught PRIVATE_EXCEPTION_CANARY\nClass \"PRIVATE_CLASS_CANARY\" not found\nCall to undefined function PRIVATE_FUNCTION_CANARY()\nM6C_PREREQUISITE: missing PRIVATE_CAPABILITY_CANARY.\n");
    fwrite(STDOUT,"token=PRIVATE-PROCESS-CANARY\n");exit(23);
}
if($case==='nonzero'){
    fwrite(STDERR,"\nM6C_EXECUTION_TRACE:{\"real_child\":true}\n");exit(31);
}
if($case==='timeout'||$case==='overflow'){
    fwrite(STDERR,'Build-only invocation stopped: controlled_'.$case.".\n");
    while(true){if($case==='overflow')fwrite(STDOUT,str_repeat('PRIVATE-PROCESS-CANARY',400));usleep(2000);}
}
if($case==='profile'){
    require_once __DIR__.'/WebsitePlatformM6CCliProcess.php';
    require_once __DIR__.'/WebsitePlatformM6CExecutionProfile.php';
    $profile=m6ExecutionBaseProfile();
    $result=m6process([PHP_BINARY,...$profile['flags'],__FILE__,'inspect'],[],10000000000,
        ['case'=>'profile-forwarding','profile'=>$profile['label']],static function($r):void{m6childTrace($r,'M6C_PROFILE');});
    fwrite(STDERR,$result[2]);exit($result[0]);
}
if($case==='inspect'){
    fwrite(STDERR,"\nM6C_PROFILE:".json_encode(['ini_loaded'=>php_ini_loaded_file()!==false,
        'memory_limit'=>ini_get('memory_limit'),'max_execution_time'=>ini_get('max_execution_time'),
        'fsync'=>function_exists('fsync'),'auto_prepend_file'=>ini_get('auto_prepend_file'),
        'binary'=>PHP_BINARY],JSON_THROW_ON_ERROR)."\n");exit(0);
}
if($case==='success'){fwrite(STDOUT,'ok');exit(0);}
exit(2);
