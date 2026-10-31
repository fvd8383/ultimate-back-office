<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6CNativeProcess.php';
require_once __DIR__.'/support/WebsitePlatformM6DNativeContract.php';
$assertions=0;
$check=static function(bool $ok,string $code) use(&$assertions): void { $assertions++; if(!$ok) throw new RuntimeException($code); };
try {
    foreach([
        ['site-deployment-worker.php',['--help'],0],['site-deployment-worker.php',['--once'],1],
        ['site-deployment-worker.php',['--environment','production'],2],['site-deployment-worker.php',['--once','--worker','admin'],2],
        ['reconcile-site-jobs.php',['--help'],0],['reconcile-site-jobs.php',['--build','1'],1],
        ['reconcile-site-jobs.php',['--deployment','1'],1],['reconcile-site-jobs.php',['--deployment','1;whoami'],2],
        ['reconcile-site-jobs.php',['--deployment','-1'],2],['reconcile-site-jobs.php',['--deployment','1','--operator','2'],2],
    ] as [$script,$args,$expected]) {
        $r=M6CNativeProcess::capture([PHP_BINARY,'-d','memory_limit=128M','-d','zend.exception_ignore_args=1',
            __DIR__.'/support/WebsitePlatformM6DCliChild.php',$script,...$args],10,16384);
        $check($r['exit']===$expected && !$r['signaled'],'actual_child_exit');
        $check(str_contains($r['stdout'],'"sql_calls":0,"synthetic_config":true'),'zero_sql_synthetic_config');
        $check(!str_contains($r['stdout'].$r['stderr'],'SYNTHETIC-DO-NOT-CONNECT'),'safe_child_diagnostics');
        if($expected===1) $check(str_contains($r['stderr'],'future_gate_required'),'expected_admission_denial');
        elseif($expected===2) $check($r['stderr']==="Invalid arguments. Use --help.\n",'expected_argument_denial');
        else $check($r['stderr']==='','help_diagnostic');
    }
    foreach(['WebsitePlatformM6DNative.php','support/WebsitePlatformM6DNativeWorker.php'] as $script) {
        $r=M6CNativeProcess::capture([PHP_BINARY,'-n',__DIR__.'/'.$script,'--help'],10,8192);
        $check($r['exit']===0 && $r['stderr']==='' && str_contains($r['stdout'],'M6D'),'native_help_no_extensions_bootstrap');
    }
    // Real bounded synthetic processes, preserving their actual exits/signals.
    $r=M6CNativeProcess::capture([PHP_BINARY,'-n','-r','fwrite(STDERR,"expected-negative\n"); exit(7);'],10,1024);
    $check($r['exit']===7 && $r['stderr']==="expected-negative\n",'negative_stderr_is_not_blanket_failure');
    $r=M6CNativeProcess::capture([PHP_BINARY,'-n','-r','exit(0);'],10,1024);
    $check($r['exit']===0 && !$r['signaled'],'actual_success_exit');
    echo "M6D CLI: $assertions assertions PASS (actual isolated children, synthetic config, no SQL).\n";
} finally { M6CNativeProcess::cleanup(); }
