<?php
declare(strict_types=1);

// Deterministic process faults and isolated fixture controls only; no host config.
$case=$argv[1]??'';
if($case==='class-directive'){
    fwrite(STDERR,"\nM6C_CLASS_DIRECTIVE:".json_encode(['supported'=>ini_get('disable_classes')!==false,
        'pdo_effective'=>!method_exists('PDO','getAvailableDrivers'),'dom_effective'=>!method_exists('DOMDocument','loadXML')],JSON_THROW_ON_ERROR)."\n");exit(0);
}
if($case==='forward-execution'){
    require_once __DIR__.'/WebsitePlatformM6CCliProcess.php';
    require_once __DIR__.'/WebsitePlatformM6CExecutionProfile.php';
    $profile=m6ExecutionBaseProfile();
    $result=m6process([PHP_BINARY,...$profile['flags'],__FILE__,'observe-execution',$argv[2]??'no-work'],[],10000000000,
        ['case'=>'execution-forwarding','profile'=>$profile['label']]);
    echo $result[1];fwrite(STDERR,$result[2]);exit($result[0]);
}
if(in_array($case,['observe-execution','controlled-classes','constructor-failure','trace-failure','trace-then-fatal'],true)){
    // Observe actual load/root state before the execution child's later cleanup.
    // This is a separate observation, never an execution trace with invented counts.
    register_shutdown_function(static function():void{
        global $f,$traceReady;
        $classes=[];foreach(['WebsitePlatformM6CFixture','WebsitePlatformM6BDatabase','Database','SiteBuildService','SiteBuildRuntime']as$name)$classes[$name]=class_exists($name,false);
        $connection=$classes['Database']?(new ReflectionProperty('Database','connection'))->getValue():null;
        $roots=$classes['WebsitePlatformM6CFixture']?array_map(static fn($root)=>['path'=>$root,'exists'=>is_dir($root)],WebsitePlatformM6CFixture::$roots):null;
        $returned=isset($f)&&$f instanceof WebsitePlatformM6CFixture;$ready=$traceReady??false;
        $state=['classes'=>$classes,'connection'=>is_object($connection)?get_class($connection):null,
            'roots'=>$roots,'fixture_returned'=>$returned,'trace_ready'=>$ready];
        if(function_exists('json_encode'))$json=json_encode($state,JSON_THROW_ON_ERROR);
        else{
            // json_encode itself is a negative prerequisite case. Encode only
            // the observed unloaded shape; never substitute it for loaded state.
            if($connection!==null||$roots!==null)throw new RuntimeException('Unexpected loaded primitive-fault state');
            $fields=[];foreach($classes as$name=>$loaded)$fields[]='"'.$name.'":'.($loaded?'true':'false');
            $json='{"classes":{'.implode(',',$fields).'},"connection":null,"roots":null,"fixture_returned":'.($returned?'true':'false').',"trace_ready":'.($ready?'true':'false').'}';
        }
        echo "\nM6C_BOOTSTRAP_STATE:".$json."\n";
    });
    if($case==='observe-execution'){
        $argv=[__FILE__,$argv[2]??'no-work'];require __DIR__.'/WebsitePlatformM6CExecutionChild.php';exit(99);
    }
    $strip=static function(string $source):string{
        $source=preg_replace('/^<\?php\s*declare\(strict_types=1\);\s*/','',$source,1,$count);
        if($count!==1)throw new RuntimeException('Controlled source header changed');
        return 'declare(strict_types=1); '.$source;
    };
    $replace=static function(string $source,string $from,string $to):string{
        $source=str_replace($from,$to,$source,$count);
        if($count!==1)throw new RuntimeException('Controlled source boundary changed');return $source;
    };
    $source=$strip(file_get_contents(__DIR__.'/WebsitePlatformM6CExecutionChild.php'));
    if($case==='controlled-classes'){
        $denied=match($argv[2]??''){ 'PDO'=>['PDO','PDOStatement'],'DOMDocument'=>['DOMDocument','DOMXPath'],'none'=>[],default=>throw new RuntimeException('Unknown class control') };
        $profile=$strip(file_get_contents(__DIR__.'/WebsitePlatformM6CExecutionProfile.php'));
        // Source-local replacements only: unavailable directives plus genuinely
        // denied class decisions, while every other class uses real class_exists.
        $prefix='namespace M6CCapabilityControl; function ini_get(string $key):string|false { return in_array($key,["disable_classes","memory_limit","extension_dir"],true)?false:\\ini_get($key); } '
            .'function class_exists(string $name,bool $autoload=true):bool { return !in_array($name,'.var_export($denied,true).',true)&&\\class_exists($name,$autoload); } ';
        $profile=$replace($profile,'declare(strict_types=1);','declare(strict_types=1); '.$prefix);
        eval($profile);
        // Also traverse the nearby optional memory-limit/extension-dir copying path.
        $controlledProfile=\M6CCapabilityControl\m6ExecutionBaseProfile();
        if(!in_array('memory_limit=268435456',$controlledProfile['flags'],true))throw new RuntimeException('False optional profile limit');
        $source=$replace($source,'$missing=m6ExecutionMissingCapabilities();','$missing=\\M6CCapabilityControl\\m6ExecutionMissingCapabilities();');
        $executionCase=$argv[3]??'no-work';
    }else{
        $executionCase='no-work';
        if($case==='constructor-failure'){
            $fixtureSource=$strip(file_get_contents(__DIR__.'/WebsitePlatformM6CFixture.php'));
            $fixtureSource=$replace($fixtureSource,'self::$roots[] = $this->root;','self::$roots[] = $this->root; m6cMissingFixtureFunction();');
            $source=$replace($source,"require_once __DIR__.'/WebsitePlatformM6CFixture.php';",'eval($fixtureSource);');
        }elseif($case==='trace-then-fatal'){
            // The actual trace is already written; fail inside the try so its
            // finally still performs the unchanged tracked-root cleanup.
            $source=$replace($source,'    }finally{',"        m6cMissingFixtureFunction();\n    }finally{");
        }else $source=$replace($source,'if(!$traceReady||$f===null)return;','if(!$traceReady||$f===null)return; m6cMissingFixtureFunction();');
    }
    $argv=[__FILE__,$executionCase];eval($source);exit(99);
}
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
