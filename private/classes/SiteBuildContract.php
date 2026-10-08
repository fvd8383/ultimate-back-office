<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteServiceSupport.php';
require_once __DIR__ . '/CanonicalJson.php';

/** Bounded M6 build values/DTOs. This class cannot authenticate or verify bytes. */
final class SiteBuildContract
{
    public const PROFILE = 'static-review-v1';
    public const POLICY_VERSION = 'build-worker-v1';
    public const POLICY = [
        'lease_seconds' => 120, 'heartbeat_seconds' => 30, 'execution_timeout_seconds' => 900,
        'recovery_timeout_seconds' => 300, 'execution_retry_delays_seconds' => [30, 120],
        'automatic_recovery_delays_seconds' => [30, 120],
    ];
    public const OPTIONS = ['contact_mode' => 'inert'];
    public const RECOVERY_REASONS = ['inspect_unknown_outcome', 'retry_recovery', 'verify_quarantine'];
    public const FAILURES = [
        'requester_not_authorized' => ['authorization', 'Original build requester is not currently authorized.'],
        'source_not_eligible' => ['eligibility', 'The stored source is no longer eligible.'],
        'input_mismatch' => ['input_invalid', 'Build input identity does not match.'],
        'artifact_invalid' => ['integrity', 'Artifact verification failed.'],
        'builder_unavailable' => ['configuration', 'The reviewed builder is unavailable.'],
        'policy_unsupported' => ['configuration', 'The persisted worker policy is unsupported.'],
        'execution_exhausted' => ['configuration', 'The recorded execution limit has been reached.'],
        'storage_unavailable' => ['transient_storage', 'Artifact storage is temporarily unavailable.'],
        'network_unavailable' => ['transient_network', 'Artifact transport is temporarily unavailable.'],
        'lease_expired' => ['lease_lost', 'The worker lease expired.'],
        'outcome_unknown' => ['external_unknown', 'The prior outcome requires recovery.'],
        'database_unavailable' => ['database_unavailable', 'The database result could not be confirmed.'],
    ];

    public static function keys(array $value, array $allowed, array $required = []): void
    {
        if (array_diff(array_keys($value), $allowed) !== [] || array_diff($required, array_keys($value)) !== []) {
            self::invalid();
        }
    }
    public static function invalid(): never { throw new SiteServiceException('invalid_request', 'The build input is invalid.'); }
    public static function id(mixed $value): int
    {
        if (!is_int($value) || $value < 1) self::invalid();
        return $value;
    }
    public static function hash(mixed $value, int $length = 64): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{' . $length . '}$/D', $value) !== 1) self::invalid();
        return $value;
    }
    public static function uuid(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $value) !== 1) self::invalid();
        return strtolower($value);
    }
    public static function key(mixed $value, int $max = 100): string
    {
        if (!is_string($value) || strlen($value) < 1 || strlen($value) > $max
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) self::invalid();
        return $value;
    }
    public static function json(array $value, int $max): string
    {
        try { $json = CanonicalJson::encode($value); } catch (Throwable) { self::invalid(); }
        if (strlen($json) > $max) self::invalid();
        return $json;
    }
    public static function decode(string $value, int $max = 1048576): array
    {
        if (strlen($value) > $max) self::invalid();
        try { $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR); } catch (Throwable) { self::invalid(); }
        if (!is_array($decoded)) self::invalid();
        return $decoded;
    }
    public static function policy(array $job): array
    {
        if (self::policyFailure($job) !== null) {
            throw new SiteServiceException('conflict', 'The persisted worker policy is unsupported.');
        }
        return self::POLICY;
    }
    /** Pure persisted-value validation: no authority, database or dependency errors are classified here. */
    public static function policyFailure(array $job): ?string
    {
        if (($job['worker_policy_version'] ?? null) !== self::POLICY_VERSION
            || !is_string($job['worker_policy_json'] ?? null)
            || !in_array($job['max_execution_attempts'] ?? null, [1,2,3,'1','2','3'], true)
            || !in_array($job['max_automatic_recoveries'] ?? null, [0,1,2,'0','1','2'], true)) {
            return 'policy_unsupported';
        }
        try {
            $digest = CanonicalJson::hash(self::decode($job['worker_policy_json'], 4096));
        } catch (SiteServiceException | JsonException) {
            return 'policy_unsupported';
        }
        return $digest === CanonicalJson::hash(self::POLICY) ? null : 'policy_unsupported';
    }
    public static function builder(array $builder): array
    {
        self::keys($builder, ['builder_version','builder_code_sha','registry_manifest_digest','toolchain_contract'],
            ['builder_version','builder_code_sha','registry_manifest_digest','toolchain_contract']);
        self::key($builder['builder_version'], 64);
        self::hash($builder['builder_code_sha'], 40);
        self::hash($builder['registry_manifest_digest']);
        if (!is_array($builder['toolchain_contract'])) self::invalid();
        self::json($builder['toolchain_contract'], 4096);
        return $builder;
    }
    public static function input(array $source, array $projection, array $builder): array
    {
        self::keys($projection, ['public_composition','public_facts','ordered_asset_digests'],
            ['public_composition','public_facts','ordered_asset_digests']);
        foreach ($projection as $part) if (!is_array($part)) self::invalid();
        if (!array_is_list($projection['ordered_asset_digests']) || count($projection['ordered_asset_digests']) > 2000) self::invalid();
        $last = null;
        foreach ($projection['ordered_asset_digests'] as $asset) {
            if (!is_array($asset)) self::invalid();
            self::keys($asset, ['usage_key','sha256','byte_size','mime_type'], ['usage_key','sha256','byte_size','mime_type']);
            $key = self::key($asset['usage_key']);
            if ($last !== null && strcmp($last, $key) >= 0) self::invalid();
            $last = $key;
            self::hash($asset['sha256']);
            if (!is_int($asset['byte_size']) || $asset['byte_size'] < 0 || $asset['byte_size'] > 20971520
                || !is_string($asset['mime_type']) || strlen($asset['mime_type']) > 100) self::invalid();
        }
        $manifest = ['contract_version' => 1, 'source_snapshot_hash' => self::hash($source['revision']['snapshot_hash']),
            'public_composition' => $projection['public_composition'], 'public_facts' => $projection['public_facts'],
            'ordered_asset_digests' => $projection['ordered_asset_digests'], 'output_profile' => self::PROFILE,
            'output_options' => self::OPTIONS, 'registry_manifest_digest' => $builder['registry_manifest_digest'],
            'toolchain_contract' => $builder['toolchain_contract']];
        $json = self::json($manifest, 1048576);
        $hash = hash('sha256', $json);
        return ['input_manifest_json' => $json, 'build_input_hash' => $hash,
            'idempotency_key' => CanonicalJson::hash(['site_key' => $source['site']['site_key'],
                'revision_id' => (int) $source['revision']['id'], 'build_input_hash' => $hash,
                'builder_version' => $builder['builder_version'], 'builder_code_sha' => $builder['builder_code_sha']])];
    }
    /** Recompute stored canonical evidence, not current public eligibility or file health. */
    public static function recordedInput(array $source, array $job): array
    {
        $manifest = self::decode($job['input_manifest_json']);
        self::keys($manifest, ['contract_version','source_snapshot_hash','public_composition','public_facts',
            'ordered_asset_digests','output_profile','output_options','registry_manifest_digest','toolchain_contract'],
            ['contract_version','source_snapshot_hash','public_composition','public_facts','ordered_asset_digests',
                'output_profile','output_options','registry_manifest_digest','toolchain_contract']);
        $builder = self::builder(['builder_version' => $job['builder_version'], 'builder_code_sha' => $job['builder_code_sha'],
            'registry_manifest_digest' => $manifest['registry_manifest_digest'], 'toolchain_contract' => $manifest['toolchain_contract']]);
        $identity = self::input($source, array_intersect_key($manifest,
            array_flip(['public_composition','public_facts','ordered_asset_digests'])), $builder);
        if ((int) $job['site_id'] !== (int) $source['site']['id'] || (int) $job['revision_id'] !== (int) $source['revision']['id']
            || $job['snapshot_hash'] !== $source['revision']['snapshot_hash'] || $job['build_profile'] !== self::PROFILE
            || CanonicalJson::hash(self::decode($job['build_options_json'], 4096)) !== CanonicalJson::hash(self::OPTIONS)
            || CanonicalJson::encode($manifest) !== $identity['input_manifest_json']
            || $job['build_input_hash'] !== $identity['build_input_hash'] || $job['idempotency_key'] !== $identity['idempotency_key']) {
            throw new SiteServiceException('conflict', 'Stored build identity is inconsistent.');
        }
        return $manifest;
    }
    public static function builderMatches(array $job, array $manifest, array $builder): bool
    {
        return $job['builder_version'] === $builder['builder_version'] && $job['builder_code_sha'] === $builder['builder_code_sha']
            && $manifest['registry_manifest_digest'] === $builder['registry_manifest_digest']
            && CanonicalJson::hash($manifest['toolchain_contract']) === CanonicalJson::hash($builder['toolchain_contract']);
    }
    public static function hint(array $hint): array
    {
        self::keys($hint, ['receipt_key'], ['receipt_key']);
        return ['receipt_key' => self::uuid($hint['receipt_key'])];
    }
    /**
     * Validate already trusted evidence's binding and bounds. NEVER called on a
     * caller receipt as a substitute for the unwired verifyOutcome dependency.
     */
    public static function evidence(array $evidence, array $job, array $producer): array
    {
        $common = ['job_id','producer_attempt_id','release_key','build_input_hash','disposition'];
        $sealed = ['artifact_hash','manifest_hash','storage_backend','storage_key','manifest','validation_summary',
            'file_count','byte_size','validator_version'];
        self::keys($evidence, array_merge($common, $sealed), $common);
        if (self::id($evidence['job_id']) !== (int) $job['id']
            || self::id($evidence['producer_attempt_id']) !== (int) $producer['id']
            || self::uuid($evidence['release_key']) !== $job['release_key']
            || self::hash($evidence['build_input_hash']) !== $job['build_input_hash']
            || !in_array($evidence['disposition'], ['sealed','safe_absence','quarantined','unknown'], true)) {
            throw new SiteServiceException('conflict', 'Verified result identity does not match.');
        }
        if ($evidence['disposition'] !== 'sealed') {
            self::keys($evidence, $common, $common);
            return $evidence;
        }
        self::keys($evidence, array_merge($common, $sealed), array_merge($common, $sealed));
        self::hash($evidence['artifact_hash']); self::hash($evidence['manifest_hash']);
        self::key($evidence['storage_backend'], 32); self::key($evidence['validator_version'], 64);
        if (!is_string($evidence['storage_key']) || strlen($evidence['storage_key']) > 500
            || preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_.-]+)*$~D', $evidence['storage_key']) !== 1
            || str_contains($evidence['storage_key'], '..')
            || !is_int($evidence['file_count']) || $evidence['file_count'] < 1 || $evidence['file_count'] > 2000
            || !is_int($evidence['byte_size']) || $evidence['byte_size'] < 0 || $evidence['byte_size'] > 104857600
            || !is_array($evidence['manifest']) || !is_array($evidence['validation_summary'])) self::invalid();
        if (hash('sha256', self::json($evidence['manifest'], 1048576)) !== $evidence['manifest_hash']) self::invalid();
        self::json($evidence['validation_summary'], 16000);
        return $evidence;
    }
    /** Shared claim-time actor shape, including the field deliberately absent from SQL CHECKs.
     * Only a current locked SiteAuthorizationPolicy actor may be passed as authenticatedActor. */
    public static function claimActor(array $fields, ?array $authenticatedActor): void
    {
        $keys = ['attempt_kind','recovery_trigger','operator_request_key','recovery_authorized_by_user_id',
            'recovery_actor_type','recovery_reason_code'];
        self::keys($fields, $keys, $keys);
        if ($fields['attempt_kind'] === 'execution') {
            foreach (array_slice($keys, 1) as $key) if ($fields[$key] !== null) self::invalid();
            if ($authenticatedActor !== null) self::invalid();
            return;
        }
        if ($fields['attempt_kind'] !== 'recovery' || !in_array($fields['recovery_reason_code'], self::RECOVERY_REASONS, true)) self::invalid();
        if ($fields['recovery_trigger'] === 'automatic') {
            if ($authenticatedActor !== null || $fields['operator_request_key'] !== null
                || $fields['recovery_authorized_by_user_id'] !== null || $fields['recovery_actor_type'] !== 'system') self::invalid();
        } elseif ($fields['recovery_trigger'] === 'operator') {
            self::uuid($fields['operator_request_key']);
            self::id($fields['recovery_authorized_by_user_id']);
            if (!($authenticatedActor['is_internal_admin'] ?? false)
                || $authenticatedActor['acting_user_id'] !== $fields['recovery_authorized_by_user_id']
                || $authenticatedActor['actor_type'] !== $fields['recovery_actor_type']
                || !in_array($fields['recovery_actor_type'], ['internal_admin','super_admin'], true)) {
                throw new SiteServiceException('unauthorized', 'An authenticated current recovery operator is required.');
            }
        } else self::invalid();
    }

    public static function failure(string $code): array
    {
        if (!isset(self::FAILURES[$code])) self::invalid();
        return ['failure_code' => $code, 'failure_category' => self::FAILURES[$code][0], 'safe_summary' => self::FAILURES[$code][1]];
    }
    public static function job(array $row): array
    {
        return self::project($row, ['id','site_id','revision_id','job_key','release_key','status','snapshot_hash',
            'build_input_hash','builder_version','build_profile','attempt_count','execution_count','max_execution_attempts',
            'recovery_count','automatic_recovery_count','max_automatic_recoveries','recovery_status','next_attempt_at',
            'next_recovery_at','failure_category','failure_code','safe_summary','started_at','completed_at','created_at',
            'updated_at','correlation_id','release_id']);
    }
    public static function attempt(array $row): array
    {
        return self::project($row, ['id','attempt_number','attempt_kind','execution_number','recovery_number',
            'recovery_of_attempt_id','recovery_trigger','recovery_actor_type','recovery_reason_code','status','leased_at',
            'deadline_at','lease_expires_at','heartbeat_at','completed_at','failure_category','failure_code','safe_summary']);
    }
    public static function release(array $row): array
    {
        return self::project($row, ['id','site_id','release_key','build_job_id','source_revision_id','source_snapshot_hash',
            'build_input_hash','artifact_hash','manifest_hash','builder_version','builder_code_sha','build_profile',
            'file_count','byte_size','built_at','created_at','correlation_id']);
    }
    private static function project(array $row, array $keys): array
    {
        $safe = array_intersect_key($row, array_flip($keys));
        if (isset($safe['failure_code'])) {
            // Never expose legacy/corrupt free text, even from a stored history row.
            $failure = self::FAILURES[$safe['failure_code']] ?? ['external_unknown','The build requires operator review.'];
            if (!isset(self::FAILURES[$safe['failure_code']])) $safe['failure_code'] = 'outcome_unknown';
            $safe['failure_category'] = $failure[0]; $safe['safe_summary'] = $failure[1];
        } elseif (array_key_exists('safe_summary', $safe)) {
            $safe['safe_summary'] = null; $safe['failure_category'] = null;
        }
        return $safe;
    }
}
