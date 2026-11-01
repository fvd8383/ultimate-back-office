<?php
declare(strict_types=1);

/** Definitions-only strict transport evidence validator; no native bootstrap. */
final class M6DNativeWireEvidence
{
    private static function keys(mixed $v,array $keys): bool { return is_array($v) && array_keys($v)===$keys; }
    private static function positive(mixed $v): bool { return is_int($v) && $v>0; }
    private static function process(mixed $v): bool
    {
        return self::keys($v,['pid','exit','signaled','signal','termination_requested','running']) && self::positive($v['pid'])
            && $v['exit']===0 && $v['signaled']===false && $v['signal']===0 && $v['termination_requested']===false && $v['running']===false;
    }
    public static function valid(mixed $e,string $sub,array $identity,int $site): bool
    {
        if(!self::keys($e,['legs','authoritative','unknown','history','recovery','final_disposition']) || $e['final_disposition']!=='succeeded')return false;
        $names=$sub==='authoritative-unavailable' ? ['result','reread'] : ['result'];
        if(!self::keys($e['legs'],$names) || !self::keys($e['authoritative'],$names))return false;
        $connections=[]; $pids=[]; $channels=[]; $target=null;
        foreach($names as $leg) {
            $l=$e['legs'][$leg]; $mode=$leg==='reread' ? 'drop-query' : match($sub) {'unarmed-success'=>'unarmed','commit-not-delivered'=>'before-commit',default=>'drop-ack'};
            if(!self::keys($l,['binding','mode','endpoint','connection','backend_verified','drained_before_arm','sequence','fault','outcome','worker','relay','last_barrier','model_unchanged_after_boundary','effects']))return false;
            $b=$l['binding'];
            if(!self::keys($b,['identity','case','subcase','leg','site_id','target_id','deployment_id','attempt_id','channel'])
                || $b['identity']!==$identity || $b['case']!=='result-commit-transport' || $b['subcase']!==$sub || $b['leg']!==$leg || $b['site_id']!==$site
                || !self::positive($b['target_id']) || !self::positive($b['deployment_id']) || !self::positive($b['attempt_id'])
                || !is_string($b['channel']) || !preg_match('/^[a-f0-9]{32}$/D',$b['channel']) || in_array($b['channel'],$channels,true))return false;
            $channels[]=$b['channel']; $tuple=[$b['site_id'],$b['target_id'],$b['deployment_id'],$b['attempt_id']];
            if($target!==null && $target!==$tuple)return false; $target=$tuple;
            if($l['mode']!==$mode || !self::positive($l['connection']) || in_array($l['connection'],$connections,true) || $l['backend_verified']!==true || $l['drained_before_arm']!==true)return false;
            $connections[]=$l['connection']; $phase=$leg==='reread' ? 'READ' : 'COMMIT';
            if($l['sequence']!==($mode==='unarmed' ? ['LISTEN','WORKER','READY',$phase,'CONTINUE','OUTCOME','STOPPED'] : ['LISTEN','WORKER','READY',$phase,'ARMED','CONTINUE','FAULT','OUTCOME'])
                || $l['fault']!==($mode==='unarmed' ? null : ['mode'=>$mode,'forwarded'=>$mode==='drop-ack','ok_received'=>$mode==='drop-ack','ack_suppressed'=>$mode==='drop-ack'])
                || $l['last_barrier']!==['worker'=>'OUTCOME','relay'=>$mode==='unarmed' ? 'STOPPED' : 'FAULT'] || $l['model_unchanged_after_boundary']!==true)return false;
            foreach(['worker','relay'] as $key) {
                if(!self::process($l[$key]) || in_array($l[$key]['pid'],$pids,true))return false; $pids[]=$l[$key]['pid'];
            }
            if(!self::keys($l['endpoint'],['relay_pid','port']) || $l['endpoint']['relay_pid']!==$l['relay']['pid']
                || !is_int($l['endpoint']['port']) || $l['endpoint']['port']<1024 || $l['endpoint']['port']>65535)return false;
            if(!self::keys($l['effects'],['stage','activate','restore','compensate','health']))return false;
            foreach($l['effects'] as $n)if(!is_int($n) || $n<0)return false;
            if($l['effects']['stage']!==1 || $l['effects']['activate']!==1 || $l['effects']['restore']!==0 || $l['effects']['compensate']!==0 || $l['effects']['health']<3)return false;
            $o=$l['outcome'];
            if(!self::keys($o,['returned','error','commit_returned','transport_failure']))return false;
            if($mode==='unarmed') { if($o!==['returned'=>'succeeded','error'=>null,'commit_returned'=>true,'transport_failure'=>null])return false; }
            else {
                if($o['returned']!==null || $o['commit_returned']!==false || !self::keys($o['transport_failure'],['sqlstate','driver'])
                    || !in_array($o['transport_failure']['driver'],[2006,2013],true) || $o['transport_failure']['sqlstate']!=='HY000'
                    || !self::keys($o['error'],['class','classification','safe']) || $o['error']['class']!=='SiteServiceException'
                    || $o['error']['classification']!=='database_failure'
                    || $o['error']['safe']!==['class'=>'SiteServiceException','code'=>'native_failure'])return false;
            }
        }
        foreach($names as $leg) {
            $a=$e['authoritative'][$leg]; $committed=$sub!=='commit-not-delivered';
            if(!self::keys($a,['connection','before_replay','state_sha256','status','committed','success_events','active_health','pointer_committed','lifecycle_committed',
                'slot_settled','activation_retained','grant_retained','execution_count','attempt_count','recovery_count'])
                || !self::positive($a['connection']) || in_array($a['connection'],$connections,true) || $a['before_replay']!==true
                || !is_string($a['state_sha256']) || !preg_match('/^[a-f0-9]{64}$/D',$a['state_sha256'])
                || $a['status']!==($committed ? 'succeeded' : 'health_check_pending') || $a['committed']!==$committed
                || $a['success_events']!==($committed ? 1 : 0) || $a['active_health']!==($committed ? 2 : 0)
                || $a['pointer_committed']!==$committed || $a['lifecycle_committed']!==$committed || $a['slot_settled']!==$committed
                || $a['activation_retained']!==true || $a['grant_retained']!==true || $a['execution_count']!==3 || $a['attempt_count']!==3 || $a['recovery_count']!==0)return false;
            $connections[]=$a['connection'];
        }
        if($sub==='authoritative-unavailable') {
            if($e['unknown']!==['read_intercepted'=>true,'service_returned'=>false,'database_unchanged'=>true,'model_unchanged'=>true]
                || $e['authoritative']['result']['state_sha256']!==$e['authoritative']['reread']['state_sha256'])return false;
        } elseif($e['unknown']!==null)return false;
        if($sub==='ack-withheld') {
            $h=$e['history'];
            if(!self::keys($h,['later_deployment','old_success_retained','no_effects','grant_changed','actor_denied','authorized_history'])
                || !self::positive($h['later_deployment']) || $h['later_deployment']===$target[2])return false;
            foreach(array_slice($h,1) as $v)if($v!==true)return false;
        } elseif($e['history']!==null)return false;
        if($sub==='commit-not-delivered') {
            $r=$e['recovery'];
            if(!self::keys($r,['original_attempt','recovery_attempt','attempt_count','execution_count','recovery_count','automatic_recovery_count','stale_owner_denied','new_candidate_effects','success_events'])
                || $r['original_attempt']!==$target[3] || !self::positive($r['recovery_attempt']) || $r['recovery_attempt']===$target[3]
                || $r['attempt_count']!==4 || $r['execution_count']!==3 || $r['recovery_count']!==1 || $r['automatic_recovery_count']!==1
                || $r['stale_owner_denied']!==true || $r['new_candidate_effects']!==0 || $r['success_events']!==1)return false;
        } elseif($e['recovery']!==null)return false;
        return true;
    }
}
