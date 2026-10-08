<?php

declare(strict_types=1);

/** Test assertions only: record order is incidental; scalar types and list order are not. */
final class M6BReplayAssertions
{
    public static function same(mixed $expected,mixed $actual): bool
    {
        if(!is_array($expected)||!is_array($actual))return $expected===$actual;
        if(array_is_list($expected)!==array_is_list($actual)||!self::fields($expected,$actual))return false;
        foreach($expected as$key=>$value)if(!self::same($value,$actual[$key]))return false;
        return true;
    }

    private static function fields(array $expected,array $actual): bool
    {
        return count($expected)===count($actual)&&array_diff_key($expected,$actual)===[];
    }

    public static function summary(mixed $observed,array $expected,array $before,array $after): array
    {
        $workerShape=is_array($observed)&&!array_is_list($observed);
        $reply=$workerShape&&array_key_exists('job',$observed)?$observed['job']:null;
        $replyShape=is_array($reply)&&!array_is_list($reply);
        $expectedJob=$expected;unset($expectedJob['existing'],$expectedJob['replayed'],$expectedJob['release']);
        $actualJob=$replyShape?$reply:[];unset($actualJob['existing'],$actualJob['replayed'],$actualJob['release']);
        // Compute every operand before checking any result. Guard only unsafe field access.
        $conditions=[
            'worker_shape'=>$workerShape,
            'worker_fields'=>$workerShape&&self::fields(['job'=>null,'prepared'=>null,'verified'=>null],$observed),
            'response_shape'=>$replyShape,
            'response_fields'=>$replyShape&&self::fields($expected,$reply),
            'reply_content'=>self::same($expected,$reply),
            'existing_true'=>$replyShape&&array_key_exists('existing',$reply)&&$reply['existing']===true,
            'replayed_true'=>$replyShape&&array_key_exists('replayed',$reply)&&$reply['replayed']===true,
            'job_record'=>$replyShape&&self::same($expectedJob,$actualJob),
            'release_record'=>$replyShape&&array_key_exists('release',$reply)&&self::same($expected['release'],$reply['release']),
            'prepared_zero'=>$workerShape&&array_key_exists('prepared',$observed)&&$observed['prepared']===0,
            'verified_zero'=>$workerShape&&array_key_exists('verified',$observed)&&$observed['verified']===0,
            'snapshot_unchanged'=>$before===$after,
        ];
        // Diagnostic allowlist only; comparison above still checks ALL keys, including unknowns.
        $jobFields=array_fill_keys(['id','site_id','revision_id','job_key','release_key','status','snapshot_hash',
            'build_input_hash','builder_version','build_profile','attempt_count','execution_count','max_execution_attempts',
            'recovery_count','automatic_recovery_count','max_automatic_recoveries','recovery_status','next_attempt_at',
            'next_recovery_at','failure_category','failure_code','safe_summary','started_at','completed_at','created_at',
            'updated_at','correlation_id','release_id','existing','replayed'],null);
        $jobFields['release']=array_fill_keys(['id','site_id','release_key','build_job_id','source_revision_id','source_snapshot_hash',
            'build_input_hash','artifact_hash','manifest_hash','builder_version','builder_code_sha','build_profile',
            'file_count','byte_size','built_at','created_at','correlation_id'],null);
        $differences=[];$truncated=false;
        self::differences(['job'=>$expected,'prepared'=>0,'verified'=>0],$observed,'worker',
            ['job'=>$jobFields,'prepared'=>null,'verified'=>null],$differences,$truncated);
        return ['case'=>'historical_success_obsolete_policy','conditions'=>$conditions,
            'failed_conditions'=>array_keys(array_filter($conditions,static fn($passed)=>!$passed)),
            'differences'=>$differences,'differences_truncated'=>$truncated];
    }

    private static function differences(mixed $expected,mixed $actual,string $path,?array $allowed,array &$out,bool &$truncated): void
    {
        $add=static function(string $category,string $field,array $types=[])use(&$out,&$truncated):void{
            if(count($out)>=64){$truncated=true;return;}
            $out[]=['path'=>$field,'category'=>$category]+$types;
        };
        if(gettype($expected)!==gettype($actual)){
            $add('type',$path,['expected_type'=>gettype($expected),'actual_type'=>gettype($actual)]);return;
        }
        if($allowed===null||!is_array($actual)){
            if(!self::same($expected,$actual))$add('value',$path);
            elseif($expected!==$actual)$add('order-only',$path);
            return;
        }
        if(array_is_list($expected)!==array_is_list($actual)){
            $add('type',$path,['expected_type'=>'record','actual_type'=>'list']);return;
        }
        if(self::same($expected,$actual)&&$expected!==$actual)$add('order-only',$path);
        // Unknown names are never echoed. Aggregate them at the known parent path.
        if(array_diff_key($actual,$expected)!==[])$add('extra',$path);
        if(array_diff_key($expected,$actual,$allowed)!==[])$add('missing',$path);
        foreach($allowed as$key=>$children){
            if(!array_key_exists($key,$expected))continue;
            if(!array_key_exists($key,$actual))$add('missing',$path.'.'.$key);
            else self::differences($expected[$key],$actual[$key],$path.'.'.$key,$children,$out,$truncated);
        }
    }

    public static function check(mixed $observed,array $expected,array $before,array $after,callable $assert): void
    {
        $summary=self::summary($observed,$expected,$before,$after);
        // Existing launcher capture retains this bounded, value-free line even on first failure.
        echo 'M6B_REPLAY_DIAGNOSTIC '.json_encode($summary,JSON_THROW_ON_ERROR)."\n";
        fflush(STDOUT);
        foreach($summary['conditions']as$name=>$passed)$assert($passed,'Native historical replay: '.$name);
    }
}
