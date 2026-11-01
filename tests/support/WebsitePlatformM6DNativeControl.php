<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6DNativeContract.php';

/** Small coordinator control seams, exercised with synthetic callbacks locally. */
final class M6DNativeControl
{
    public static function ownedSchema(array $a): string
    {
        M6DNativeContract::binding($a,$a['source_sha'],$a['image_id'],$a['run_token']);
        return $a['database_name'];
    }
    public static function cleanup(array &$report,bool $owned,callable $children,callable $schema): void
    {
        try {
            $observations=$children();
            $report['children']=array_map(static function(array $r): array {
                $safe=array_intersect_key($r,array_flip(['pid','exit','signal','signaled','running','termination_requested']));
                if(!is_int($safe['exit'] ?? null) || $safe['exit']<0)$safe['exit']=null;
                foreach(['context','failure_observation','cleanup_observation'] as $key) {
                    $allowed=$key==='context' ? ['case','subcase','leg','site_id','deployment_id','attempt_id','last_barrier'] : ['exit','signal','signaled','running','termination_requested'];
                    $safe[$key]=array_intersect_key($r[$key] ?? [],array_flip($allowed));
                    foreach($safe[$key] as $k=>$v)if((is_string($v) && !preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$v))
                        || (!is_string($v) && !is_int($v) && !is_bool($v) && $v!==null))unset($safe[$key][$k]);
                    if(array_key_exists('exit',$safe[$key]) && (!is_int($safe[$key]['exit']) || $safe[$key]['exit']<0))$safe[$key]['exit']=null;
                }
                return $safe;
            },$observations);
            foreach($observations as $r) M6DNativeContract::need(($r['running'] ?? true)===false,'child_cleanup');
            $report['children_cleanup']='PASS';
        } catch(Throwable $e) {
            $report['children_cleanup']='FAIL'; $report['schema_cleanup']='NOT_ATTEMPTED'; $report['cleanup']='FAIL';
            $report['cleanup_failure']=$report['children_cleanup_failure']=M6DNativeContract::safeFailure($e); return;
        }
        try {
            if($owned) { $schema(); $report['schema_cleanup']=$report['cleanup']='PASS'; }
            else $report['schema_cleanup']=$report['cleanup']='NOT_OWNED';
        } catch(Throwable $e) { $report['schema_cleanup']=$report['cleanup']='FAIL'; $report['cleanup_failure']=$report['schema_cleanup_failure']=M6DNativeContract::safeFailure($e); }
    }
    public static function publish(array &$report,callable $publisher,?array $identity=null): void
    {
        $report['status']=M6DNativeContract::complete($report,$identity) ? 'PASS' : 'FAIL';
        try { $publisher($report); }
        catch(Throwable $e) { $report['publication']='FAIL'; $report['status']='FAIL'; $report['publication_failure']=M6DNativeContract::safeFailure($e); }
    }
}
