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
            foreach($observations as $r) M6DNativeContract::need(($r['running'] ?? true)===false,'child_cleanup');
            $report['children']=array_map(static fn($r)=>array_intersect_key($r,array_flip(['pid','exit','signal','signaled','running','termination_requested'])),$observations);
            if($owned) { $schema(); $report['cleanup']='PASS'; }
            else $report['cleanup']='NOT_OWNED';
        } catch(Throwable $e) { $report['cleanup']='FAIL'; $report['cleanup_failure']=M6DNativeContract::safeFailure($e); }
    }
    public static function publish(array &$report,callable $publisher): void
    {
        $report['status']=M6DNativeContract::complete($report) ? 'PASS' : 'FAIL';
        try { $publisher($report); }
        catch(Throwable $e) { $report['publication']='FAIL'; $report['status']='FAIL'; $report['publication_failure']=M6DNativeContract::safeFailure($e); }
    }
}
