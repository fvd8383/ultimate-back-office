<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteBuildContract.php';

/** Bounded values only; neither a grant ID nor an observation authenticates its caller. */
final class SiteDeploymentContract
{
    public const POLICY_VERSION = 'deployment-worker-v1';
    public const POLICY = ['lease_seconds'=>120,'heartbeat_seconds'=>30,'execution_timeout_seconds'=>300,
        'recovery_timeout_seconds'=>300,'execution_retry_delays_seconds'=>[30,120],
        'automatic_recovery_delays_seconds'=>[30,120]];
    public const REASONS = ['publish_approved_release','restore_known_good','repair_verified_release'];
    public const RECOVERY_REASONS = ['inspect_unknown_outcome','retry_recovery','verify_compensation','inspect_external_drift'];
    public const FAILURES = ['source_not_eligible'=>'eligibility','grant_not_valid'=>'authorization',
        'artifact_invalid'=>'integrity','publisher_unavailable'=>'configuration','storage_unavailable'=>'transient_storage',
        'network_unavailable'=>'transient_network','lease_expired'=>'lease_lost','outcome_unknown'=>'external_unknown',
        'health_failed'=>'health_failed','database_unavailable'=>'database_unavailable','execution_exhausted'=>'configuration'];

    public static function invalid(): never { throw new SiteServiceException('invalid_request', 'The deployment input is invalid.'); }
    public static function keys(array $v, array $keys, ?array $required = null): void
    {
        if (array_diff(array_keys($v),$keys) || array_diff($required ?? $keys,array_keys($v))) self::invalid();
    }
    public static function version(mixed $v): int
    {
        if (!is_int($v) || $v < 0) self::invalid();
        return $v;
    }
    public static function nullableId(mixed $v): ?int { return $v === null ? null : SiteBuildContract::id($v); }
    public static function request(array $v, string $operation): array
    {
        $keys = ['site_id','target_id','release_id','request_key','expected_current_deployment_id','expected_pointer_version',
            'restored_from_deployment_id','deployment_approval_id','binding_version','reason_code','correlation_id'];
        self::keys($v,$keys,array_diff($keys,['restored_from_deployment_id','deployment_approval_id','correlation_id']));
        foreach (['site_id','target_id','release_id','binding_version'] as $k) $v[$k]=SiteBuildContract::id($v[$k]);
        foreach (['expected_current_deployment_id','restored_from_deployment_id','deployment_approval_id'] as $k) $v[$k]=self::nullableId($v[$k] ?? null);
        $v['expected_pointer_version']=self::version($v['expected_pointer_version']);
        $v['request_key']=SiteBuildContract::uuid($v['request_key']);
        if (!in_array($v['reason_code'],self::REASONS,true)
            || ($operation === 'publish') !== ($v['restored_from_deployment_id'] === null)) self::invalid();
        $v['operation']=$operation;
        $v['correlation_id']=SiteServiceSupport::correlationId($v['correlation_id'] ?? null);
        return $v;
    }
    public static function payload(array $v, string $environment): array
    {
        $p=['request_contract_version'=>1,'environment'=>$environment];
        foreach (['site_id','target_id','release_id','operation','expected_current_deployment_id','expected_pointer_version',
            'restored_from_deployment_id','deployment_approval_id','binding_version','reason_code'] as $k) $p[$k]=$v[$k];
        return $p;
    }
    public static function policy(array $op): void
    {
        if ($op['worker_policy_version'] !== self::POLICY_VERSION
            || CanonicalJson::hash(SiteBuildContract::decode($op['worker_policy_json'],4096)) !== CanonicalJson::hash(self::POLICY)
            || !in_array((int)$op['max_execution_attempts'],[1,2,3],true)
            || !in_array((int)$op['max_automatic_recoveries'],[0,1,2],true)) {
            throw new SiteServiceException('conflict','The deployment policy is unsupported.');
        }
    }
    public static function claimActor(array $attempt, ?array $actor): void
    {
        if ($attempt['attempt_kind'] === 'execution') {
            foreach (['recovery_trigger','operator_request_key','recovery_authorized_by_user_id','recovery_actor_type','recovery_reason_code'] as $k) {
                if ($attempt[$k] !== null) self::invalid();
            }
            if ($actor !== null) self::invalid();
        } elseif ($attempt['recovery_trigger'] === 'automatic') {
            if ($actor !== null || $attempt['recovery_authorized_by_user_id'] !== null || $attempt['operator_request_key'] !== null
                || $attempt['recovery_actor_type'] !== 'system') self::invalid();
        } elseif ($attempt['recovery_trigger'] === 'operator') {
            if ($actor === null || !$actor['is_internal_admin'] || $actor['acting_user_id'] !== $attempt['recovery_authorized_by_user_id']
                || $actor['actor_type'] !== $attempt['recovery_actor_type']) throw new SiteServiceException('unauthorized','A current authenticated recovery operator is required.');
            SiteBuildContract::uuid($attempt['operator_request_key']);
        } else self::invalid();
    }
    public static function lease(array $v, ?string $kind = null): array
    {
        self::keys($v,['site_id','target_id','deployment_id','attempt_id','attempt_kind','worker_id','environment','token','fence_epoch']);
        foreach (['site_id','target_id','deployment_id','attempt_id','fence_epoch'] as $k) SiteBuildContract::id($v[$k]);
        SiteBuildContract::hash($v['token']); SiteBuildContract::key($v['worker_id']);
        if (!in_array($v['environment'],['staging','production'],true) || !in_array($v['attempt_kind'],['execution','recovery'],true)
            || ($kind !== null && $v['attempt_kind'] !== $kind)) self::invalid();
        return $v;
    }
    public static function release(array $release, array $site): array
    {
        return ['site_id'=>(int)$release['site_id'],'site_key'=>SiteBuildContract::uuid($site['site_key']),
            'release_id'=>(int)$release['id'],'release_key'=>SiteBuildContract::uuid($release['release_key']),
            'artifact_hash'=>SiteBuildContract::hash($release['artifact_hash']),
            'manifest_hash'=>SiteBuildContract::hash($release['manifest_hash']),'build_profile'=>$release['build_profile']];
    }
    public static function target(array $target): array
    {
        return ['site_id'=>(int)$target['site_id'],'target_id'=>(int)$target['id'],'environment'=>$target['environment'],
            'publisher_key'=>SiteBuildContract::key($target['publisher_key'],64),
            'binding_key'=>SiteBuildContract::key($target['binding_key']), 'binding_version'=>(int)$target['binding_version']];
    }
    public static function artifact(array $e, array $release): array
    {
        self::keys($e,['release_id','release_key','artifact_hash','manifest_hash','result']);
        if (CanonicalJson::encode($e) !== CanonicalJson::encode(['release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],
            'artifact_hash'=>$release['artifact_hash'],'manifest_hash'=>$release['manifest_hash'],'result'=>'pass'])) {
            throw new SiteServiceException('conflict','The immutable release could not be verified.');
        }
        return $e;
    }
    public static function observation(array $e): array
    {
        self::keys($e,['state','site_id','target_id','binding_version','release_key','artifact_hash','deployment_id',
            'producer_attempt_id','fence_epoch','integrity','external_reference']);
        if (!in_array($e['state'],['present','absent','unknown'],true) || !in_array($e['integrity'],['pass','fail','unknown'],true)) self::invalid();
        foreach (['site_id','target_id','binding_version'] as $k) SiteBuildContract::id($e[$k]);
        self::version($e['fence_epoch']);
        foreach (['deployment_id','producer_attempt_id'] as $k) self::nullableId($e[$k]);
        if ($e['release_key'] !== null) SiteBuildContract::uuid($e['release_key']);
        if ($e['artifact_hash'] !== null) SiteBuildContract::hash($e['artifact_hash']);
        if ($e['external_reference'] !== null) SiteBuildContract::key($e['external_reference'],191);
        if ($e['state'] === 'absent' && ($e['release_key'] !== null || $e['artifact_hash'] !== null
            || $e['deployment_id'] !== null || $e['producer_attempt_id'] !== null)) self::invalid();
        if ($e['state'] === 'present' && ($e['release_key'] === null || $e['artifact_hash'] === null
            || $e['deployment_id'] === null || $e['producer_attempt_id'] === null)) self::invalid();
        return $e;
    }
    public static function receipt(array $e, array $context, string $phase): array
    {
        self::keys($e,['phase','result','deployment_id','attempt_id','fence_epoch','binding_version','release_key',
            'artifact_hash','previous_release_key','external_reference']);
        if ($e['phase'] !== $phase || !in_array($e['result'],['pass','conflict','unknown','fail'],true)) self::invalid();
        foreach (['deployment_id','attempt_id','fence_epoch','binding_version','release_key','artifact_hash'] as $k) {
            if ($e[$k] !== $context[$k]) throw new SiteServiceException('conflict','Publisher receipt identity does not match.');
        }
        if ($e['previous_release_key'] !== null) SiteBuildContract::uuid($e['previous_release_key']);
        SiteBuildContract::key($e['external_reference'],191);
        return $e;
    }
    public static function health(array $e, array $expected, array $target, array $context, string $now): array
    {
        $keys=['check_key','phase','probe_profile','site_key','release_key','artifact_hash','binding_key','binding_version',
            'attempt_id','fence_epoch','expected_absent','http_status','duration_ms','checked_at','result',
            'marker_digest','home_marker','page_sanity','css_integrity','media_integrity','origin_integrity',
            'tls_verified','redirected','cache_bypassed','no_store','no_current_link'];
        self::keys($e,$keys); SiteBuildContract::uuid($e['check_key']);
        if ($e['phase'] !== $context['phase'] || $e['probe_profile'] !== ($expected['release_key'] === null ? 'unavailable-v1' : 'release-v1')
            || $e['attempt_id'] !== $context['attempt_id'] || $e['fence_epoch'] !== $context['fence_epoch']
            || $e['binding_key'] !== $target['binding_key'] || $e['binding_version'] !== $target['binding_version']
            || $e['site_key'] !== $expected['site_key'] || !is_int($e['duration_ms']) || $e['duration_ms'] < 0 || $e['duration_ms'] > 30000
            || !is_int($e['http_status']) || !in_array($e['result'],['pass','fail'],true)
            || !is_string($e['checked_at']) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D',$e['checked_at'])
            || $e['checked_at'] > $now || $e['checked_at'] < SiteBuildStore::after($now,-30)) self::invalid();
        foreach (array_slice($keys,15) as $key) if (!is_bool($e[$key])) self::invalid();
        if (!is_bool($e['expected_absent'])) self::invalid();
        $absent=$expected['release_key'] === null;
        $valid=$e['release_key'] === $expected['release_key'] && $e['artifact_hash'] === $expected['artifact_hash']
            && $e['expected_absent'] === $absent && $e['tls_verified'] && !$e['redirected'] && $e['cache_bypassed']
            && ($target['environment'] !== 'staging' || $e['no_store']);
        if ($absent) $valid=$valid && in_array($context['phase'],['rollback','reconcile'],true)
            && $e['http_status'] === 503 && $e['no_current_link'] && !$e['home_marker'];
        else {
            $valid=$valid && $e['http_status'] === 200 && !$e['no_current_link'];
            foreach (['marker_digest','home_marker','page_sanity','css_integrity','media_integrity','origin_integrity'] as $k) $valid=$valid && $e[$k];
        }
        if (!$valid) $e['result']='fail';
        return $e;
    }
}
