<?php
declare(strict_types=1);

// Check the complete fixture prerequisites BEFORE any PDO-derived class loads.
require_once __DIR__.'/WebsitePlatformM6CExecutionProfile.php';
$case=$argv[1]??'';
$missing=m6ExecutionMissingCapabilities();
// Even diagnostic primitives can be disabled by a configured child profile.
// Echo is a language construct, so this prerequisite can still be reported.
if(!function_exists('json_encode')||!function_exists('fwrite')){
    echo 'M6C_PREREQUISITE: missing '.implode(', ',$missing).".\n";exit(78);
}
if($case==='--capabilities'){
    fwrite(STDERR,"\nM6C_CAPABILITIES:".json_encode(['missing'=>$missing,'ini_loaded'=>php_ini_loaded_file()!==false,
        'memory_limit'=>ini_get('memory_limit'),'max_execution_time'=>ini_get('max_execution_time')],JSON_THROW_ON_ERROR)."\n");
    exit($missing===[]?0:78);
}
if(!in_array($case,['revoked','builder-unavailable','builder-mismatch','no-work'],true))exit(2);
if($missing!==[]){fwrite(STDERR,'M6C_PREREQUISITE: missing '.implode(', ',$missing).".\n");exit(78);}
// Actual entry/service with isolated fake SQL and private temporary artifacts only.
require_once __DIR__.'/WebsitePlatformM6CFixture.php';
$f=null;$io=0;$traceReady=false;
// Static tracked roots survive a constructor that never returns an instance.
// Register responsibility before either construction or request preparation.
register_shutdown_function(function()use(&$f,&$io,&$traceReady):void{
    try{
        if(!$traceReady||$f===null)return;
        $jobs=array_map(static fn($j)=>array_intersect_key($j,array_flip(['status','failure_code','execution_count','attempt_count','recovery_count','requested_by_user_id'])),$f->db->tables['site_build_jobs']);
        $attempts=array_map(static fn($a)=>array_intersect_key($a,array_flip(['status','failure_code','completed_at'])),$f->db->tables['site_build_attempts']);
        $files=[];foreach(['work','sealed']as$dir)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f->root.'/'.$dir,FilesystemIterator::SKIP_DOTS))as$file)$files[]=substr($file->getPathname(),strlen($f->root)+1);
        fwrite(STDERR,"\nM6C_EXECUTION_TRACE:".json_encode(['jobs'=>array_values($jobs),'attempts'=>array_values($attempts),
            'started'=>$f->db->events('site_build_started'),'failed'=>$f->db->events('site_build_failed'),'releases'=>count($f->db->tables['site_releases']),
            'builds'=>$f->builds,'artifact_io'=>$f->io-$io,'files'=>$files,'host_config_reads'=>0,'live_database_connections'=>0],JSON_THROW_ON_ERROR)."\n");
    }finally{
        // Trace failures must not strand owned roots. Never search for other roots.
        WebsitePlatformM6CFixture::cleanup();
    }
});
$f=new WebsitePlatformM6CFixture();
if($case!=='no-work')$f->db->request();
$io=$f->io;$identityCalls=0;
$f->duringIdentity=function()use($f,$case,&$identityCalls):void{
    if(++$identityCalls!==2)return;
    if($case==='revoked')$f->db->read->users[1]['status']='inactive';
    if($case==='builder-unavailable')SiteArtifactFiles::fail('builder_unavailable');
    if($case==='builder-mismatch')$f->identity['builder_code_sha']=str_repeat('b',40);
};
$traceReady=true;
$source=file_get_contents(dirname(__DIR__,2).'/scripts/site-build-worker.php');
$source=preg_replace('/^<\?php\s*declare\(strict_types=1\);\s*/','',$source,1,$headers);
$source=preg_replace('/^require_once .*;\r?$/m','',$source,-1,$includes);
if($headers!==1||$includes!==2)throw new RuntimeException('Fixture bootstrap boundary changed');
$argv=['isolated-worker','--once'];eval($source);
