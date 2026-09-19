<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteRevisionManager.php';
require_once __DIR__ . '/SiteBuildDependencies.php';
require_once __DIR__ . '/SiteBuildContract.php';
require_once __DIR__ . '/SiteBuildStore.php';

/**
 * M6B database owner. No routes, rendering, filesystem, deployment or scheduling.
 * Private dependency slot intentionally has no runtime adapter or public setter.
 * Worker arrays cannot authenticate: only future trusted internal wiring can.
 */
final class SiteBuildService
{
    private static ?SiteBuildDependencies $dependencies = null;

    /** @return array Safe BuildJob DTO with existing flag. */
    public static function requestBuild(int $actingUserId, array $input): array
    {
        SiteBuildContract::keys($input, ['site_id','revision_id','expected_snapshot_hash','build_profile','correlation_id'],
            ['site_id','revision_id','expected_snapshot_hash','build_profile']);
        $siteId = SiteBuildContract::id($input['site_id']);
        $revisionId = SiteBuildContract::id($input['revision_id']);
        $hash = SiteBuildContract::hash($input['expected_snapshot_hash']);
        if ($input['build_profile'] !== SiteBuildContract::PROFILE) SiteBuildContract::invalid();
        $correlation = self::correlation($input['correlation_id'] ?? null);
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $builder = self::external(static fn (): array => SiteBuildContract::builder(self::dependencies()->builderIdentity()));
        $identity = null;
        $replay = static function () use ($actingUserId, $siteId, $revisionId, $hash, $builder, &$identity): ?array {
            return self::successfulRequest($actingUserId, $siteId, $revisionId, $hash, $builder, $identity);
        };
        if (($existing = $replay()) !== null) return $existing;
        try {
            return self::newBuildRequest($actingUserId, $siteId, $revisionId, $hash, $builder, $correlation, $replay, $identity);
        } catch (SiteServiceException $e) {
            // A winner may commit between the history preflight and an eligibility transaction.
            // Resolve it under fresh locks before returning a new-build gate denial; DB errors propagate.
            if (in_array($e->classification(), ['invalid_transition','conflict','stale_write'], true)
                && ($existing = $replay()) !== null) return $existing;
            throw $e;
        }
    }

    private static function newBuildRequest(int $actingUserId, int $siteId, int $revisionId, string $hash,
        array $builder, string $correlation, callable $replay, ?array &$identity): array
    {
        // Read immutable source under owner locks, then project outside the intent transaction.
        $source = SiteBuildStore::transaction(static function (object $db) use ($siteId, $revisionId, $hash, $actingUserId): array {
            $source = SiteRevisionManager::lockBuildEligibility($db, $siteId, $revisionId, $hash);
            self::actors($db, [$actingUserId], true);
            return $source;
        });
        $projection = self::external(static fn (): array => self::dependencies()->prepareInput($source));
        $identity = SiteBuildContract::input($source, $projection, $builder);
        return SiteBuildStore::transaction(static function (object $db) use ($source, $identity, $builder, $actingUserId, $correlation): array {
            $locked = SiteRevisionManager::lockBuildEligibility($db, (int) $source['site']['id'],
                (int) $source['revision']['id'], $source['revision']['snapshot_hash']);
            $actor = self::actors($db, [$actingUserId], true)[$actingUserId];
            if (self::sourceIdentity($locked) !== self::sourceIdentity($source)) {
                throw new SiteServiceException('stale_write', 'The build input changed during preparation.');
            }
            $existing = SiteBuildStore::one($db, 'SELECT * FROM site_build_jobs WHERE idempotency_key = :identity FOR UPDATE',
                ['identity' => $identity['idempotency_key']]);
            if ($existing !== null) {
                $manifest = SiteBuildContract::recordedInput($locked, $existing);
                if (!SiteBuildContract::builderMatches($existing, $manifest, $builder)) {
                    throw new SiteServiceException('conflict', 'The recorded build does not match this request.');
                }
                return $existing['status'] === 'succeeded' ? self::successfulRequestDTO($db, $existing)
                    : self::jobDTO($db, $existing) + ['existing' => true, 'replayed' => true];
            }
            $now = SiteBuildStore::now($db);
            $job = $identity + [
                'site_id' => (int) $locked['site']['id'], 'revision_id' => (int) $locked['revision']['id'],
                'job_key' => SiteServiceSupport::uuidV4(), 'release_key' => SiteServiceSupport::uuidV4(),
                'business_id' => $locked['business_id'], 'association_id' => $locked['association_id'],
                'snapshot_hash' => $locked['revision']['snapshot_hash'], 'builder_version' => $builder['builder_version'],
                'builder_code_sha' => $builder['builder_code_sha'], 'build_profile' => SiteBuildContract::PROFILE,
                'build_options_json' => CanonicalJson::encode(SiteBuildContract::OPTIONS),
                'requested_by_user_id' => $actingUserId, 'actor_type' => $actor['actor_type'], 'correlation_id' => $correlation,
                'status' => 'requested', 'next_attempt_at' => $now, 'current_attempt_id' => null, 'lock_version' => 0,
                'attempt_count' => 0, 'execution_count' => 0, 'recovery_count' => 0, 'automatic_recovery_count' => 0,
                'max_execution_attempts' => 3, 'max_automatic_recoveries' => 2, 'recovery_status' => 'none',
                'next_recovery_at' => null, 'worker_policy_version' => SiteBuildContract::POLICY_VERSION,
                'worker_policy_json' => CanonicalJson::encode(SiteBuildContract::POLICY),
                'failure_category' => null, 'failure_code' => null, 'safe_summary' => null,
                'started_at' => null, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
            $job['id'] = SiteBuildStore::insert($db, 'site_build_jobs', $job);
            self::event($db, $job, 'site_build_requested', $actor);
            return self::jobDTO($db, $job) + ['existing' => false, 'replayed' => false];
        }, true, $replay);
    }

    /** History-only lookup. Stored canonical evidence must establish ONE deterministic input. */
    private static function successfulRequest(int $actingUserId, int $siteId, int $revisionId, string $hash,
        array $builder, ?array $expectedIdentity): ?array
    {
        return SiteBuildStore::transaction(static function (object $db) use (
            $actingUserId, $siteId, $revisionId, $hash, $builder, $expectedIdentity
        ): ?array {
            $site = SiteManager::lockSite($db, $siteId);
            $revision = SiteRevisionManager::lockRevision($db, $revisionId);
            self::actors($db, [$actingUserId], true);
            if ((int) $revision['site_id'] !== $siteId || $revision['snapshot_hash'] !== $hash) {
                throw new SiteServiceException('conflict', 'The requested source identity does not match.');
            }
            $source = ['site' => $site, 'revision' => $revision];
            // Current locking read: no pre-lock consistent snapshot, no latest-success fallback.
            // Include non-success rows so inconsistent deterministic inputs cannot hide behind status.
            $jobs = SiteBuildStore::rows($db, 'SELECT * FROM site_build_jobs WHERE site_id = :site_id
                AND revision_id = :revision_id AND snapshot_hash = :snapshot_hash AND build_profile = :build_profile
                AND builder_version = :builder_version AND builder_code_sha = :builder_code_sha ORDER BY id LIMIT 101 FOR UPDATE',
                ['site_id' => $siteId, 'revision_id' => $revisionId, 'snapshot_hash' => $hash,
                    'build_profile' => SiteBuildContract::PROFILE, 'builder_version' => $builder['builder_version'],
                    'builder_code_sha' => $builder['builder_code_sha']]);
            if (count($jobs) > 100) throw new SiteServiceException('conflict', 'Build history cannot be matched unambiguously.');
            $matches = [];
            foreach ($jobs as $job) {
                try { $manifest = SiteBuildContract::recordedInput($source, $job); }
                catch (SiteServiceException) { throw new SiteServiceException('conflict', 'Stored build identity is inconsistent.'); }
                if (SiteBuildContract::builderMatches($job, $manifest, $builder)) $matches[] = $job;
            }
            if (count($matches) > 1) throw new SiteServiceException('conflict', 'Build input history is ambiguous.');
            if ($matches === [] || $matches[0]['status'] !== 'succeeded') return null;
            $job = $matches[0];
            if ($expectedIdentity !== null && ($job['idempotency_key'] !== $expectedIdentity['idempotency_key']
                || $job['build_input_hash'] !== $expectedIdentity['build_input_hash'])) {
                throw new SiteServiceException('conflict', 'Prepared input conflicts with recorded input.');
            }
            // The owning site/revision locks protect the immutable aggregate. Both established
            // snapshot representations are supported; this does not check current rights/approvals.
            if (SiteRevisionSnapshotHasher::hashStoredRevision($db, $revisionId) !== $hash
                && SiteRevisionSnapshotHasher::hashStoredRevision($db, $revisionId, SiteRevisionSnapshotHasher::MODE_LEGACY_M1) !== $hash) {
                throw new SiteServiceException('conflict', 'Stored source content does not match its snapshot.');
            }
            return self::successfulRequestDTO($db, $job);
        });
    }

    private static function successfulRequestDTO(object $db, array $job): array
    {
        $release = self::releaseRow($db, $job);
        if ($release === null || (int) $release['source_revision_id'] !== (int) $job['revision_id']) {
            throw new SiteServiceException('conflict', 'The recorded release identity is inconsistent.');
        }
        foreach (['release_key','build_input_hash','builder_version','builder_code_sha','build_profile'] as $key) {
            if ($release[$key] !== $job[$key]) throw new SiteServiceException('conflict', 'The recorded release identity is inconsistent.');
        }
        if ($release['source_snapshot_hash'] !== $job['snapshot_hash']) {
            throw new SiteServiceException('conflict', 'The recorded release source is inconsistent.');
        }
        return self::jobDTO($db, $job) + ['existing' => true, 'replayed' => true, 'release' => SiteBuildContract::release($release)];
    }

    /** @return ?array Execution lease and immutable private BuildInput, after commit only. */
    public static function claimBuild(array $workerContext): ?array
    {
        if ($workerContext !== []) SiteBuildContract::invalid();
        $worker = self::worker('execution');
        $builder = self::external(static fn (): array => SiteBuildContract::builder(self::dependencies()->builderIdentity()));
        $candidates = SiteServiceSupport::read(static fn (object $db): array => SiteBuildStore::rows($db,
            "SELECT * FROM site_build_jobs WHERE status IN ('requested','retry_wait')
             AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP(6)) ORDER BY id LIMIT 20"));
        foreach ($candidates as $candidate) {
            $claim = SiteBuildStore::transaction(static function (object $db) use ($candidate, $worker, $builder): ?array {
                [$job, $attempt, $source, $actors, $sourceError] = self::lockedContext($db, $candidate, true, []);
                self::confirmWorker($worker, 'execution');
                $now = SiteBuildStore::now($db);
                if (!in_array($job['status'], ['requested','retry_wait'], true)
                    || ($job['next_attempt_at'] !== null && $job['next_attempt_at'] > $now)) return null;
                if (!self::safeQueue($job, $attempt)) {
                    self::requireRecovery($db, $job, $now, 'outcome_unknown');
                    return null;
                }
                if (!self::requesterAllowed($job, $actors)) {
                    $oldStatus = $job['status'];
                    $job = self::updateJob($db, $job, SiteBuildContract::failure('requester_not_authorized') + [
                        'status' => 'cancelled', 'next_attempt_at' => null, 'completed_at' => $now], $now);
                    self::event($db, $job, 'site_build_cancelled', SiteServiceSupport::systemActor(), [
                        'previous_status' => $oldStatus, 'next_status' => 'cancelled'], 'requester_not_authorized');
                    return null;
                }
                if ($sourceError !== null) {
                    $job = self::updateJob($db, $job, SiteBuildContract::failure('source_not_eligible') + [
                        'status' => 'failed', 'next_attempt_at' => null, 'completed_at' => $now], $now);
                    self::event($db, $job, 'site_build_failed', SiteServiceSupport::systemActor(), [], 'source_not_eligible');
                    return null;
                }
                $incompatibility = self::inputFailure($job, $source, $builder) ?? SiteBuildContract::policyFailure($job);
                if ($incompatibility === null && (int) $job['execution_count'] >= (int) $job['max_execution_attempts']) {
                    $incompatibility = 'execution_exhausted';
                }
                if ($incompatibility !== null) {
                    $oldStatus = $job['status'];
                    $job = self::updateJob($db, $job, SiteBuildContract::failure($incompatibility) + [
                        'status' => 'failed', 'next_attempt_at' => null, 'completed_at' => $now], $now);
                    self::event($db, $job, 'site_build_failed', SiteServiceSupport::systemActor(), [
                        'previous_status' => $oldStatus, 'next_status' => 'failed'], $incompatibility);
                    return null;
                }
                return self::allocate($db, $job, 'execution', $worker, $now, null, null);
            });
            if ($claim !== null) return $claim;
        }
        return null;
    }

    /** @param array{job_id:int,attempt_id:int,attempt_kind:string,token:string} $lease */
    public static function renewBuildLease(array $lease): array
    {
        $lease = self::leaseInput($lease);
        $worker = self::worker($lease['attempt_kind']);
        $identity = self::jobIdentity($lease['job_id']);
        return SiteBuildStore::transaction(static function (object $db) use ($identity, $lease, $worker): array {
            SiteManager::lockSite($db, (int) $identity['site_id']);
            $job = self::lockJob($db, $identity);
            $attempt = self::attempt($db, $job, $lease['attempt_id']);
            $now = SiteBuildStore::now($db);
            self::assertLease($job, $attempt, $lease, $worker, $now);
            self::confirmWorker($worker, $lease['attempt_kind']);
            $policy = SiteBuildContract::policy($job);
            $expiry = min(SiteBuildStore::after($now, $policy['lease_seconds']), $attempt['deadline_at']);
            SiteBuildStore::update($db, 'site_build_attempts', $attempt, ['lease_expires_at' => $expiry, 'heartbeat_at' => $now]);
            return ['lease_expires_at' => $expiry, 'deadline_at' => $attempt['deadline_at'],
                'heartbeat_seconds' => $policy['heartbeat_seconds']];
        });
    }

    /** Hint is an opaque verified-store lookup, never an artifact receipt supplied as proof. */
    public static function completeBuildSuccess(array $lease, array $receipt): array
    {
        $lease = self::leaseInput($lease, 'execution');
        $hint = SiteBuildContract::hint($receipt);
        return self::complete($lease, $hint, 'success', null);
    }

    public static function completeBuildFailure(array $lease, array $failure): array
    {
        $lease = self::leaseInput($lease, 'execution');
        SiteBuildContract::keys($failure, ['code','receipt_key'], ['code','receipt_key']);
        if (!is_string($failure['code'])) SiteBuildContract::invalid();
        SiteBuildContract::failure($failure['code']);
        return self::complete($lease, SiteBuildContract::hint(['receipt_key' => $failure['receipt_key']]), 'failure', $failure['code']);
    }

    public static function retryBuild(int $actingUserId, int $jobId, string $correlationId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $correlationId = self::correlation($correlationId);
        $identity = self::jobIdentity(SiteBuildContract::id($jobId));
        return SiteBuildStore::transaction(static function (object $db) use ($identity, $actingUserId, $correlationId): array {
            [$job, $attempt, $source, $actors, $sourceError] = self::lockedContext($db, $identity, true, [$actingUserId]);
            if (!($actors[$actingUserId]['is_internal_admin'] ?? false) || !self::requesterAllowed($job, $actors)) {
                throw new SiteServiceException('unauthorized', 'Current internal authority is required for both users.');
            }
            if ($sourceError !== null) throw $sourceError;
            self::assertInput($job, $source);
            SiteBuildContract::policy($job);
            if ($job['status'] !== 'failed' || !self::safeQueue($job, $attempt)
                || !in_array($job['failure_category'], ['transient_storage','transient_network'], true)
                || (int) $job['execution_count'] >= (int) $job['max_execution_attempts']) {
                throw new SiteServiceException('invalid_transition', 'This build cannot be retried.');
            }
            $now = SiteBuildStore::now($db);
            $delay = SiteBuildContract::POLICY['execution_retry_delays_seconds'][max(0, (int) $job['execution_count'] - 1)];
            $job = self::updateJob($db, $job, ['status' => 'retry_wait',
                'next_attempt_at' => SiteBuildStore::after($now, $delay), 'completed_at' => null], $now);
            self::event($db, $job, 'site_build_retry_requested', $actors[$actingUserId], [], null, $correlationId);
            return self::jobDTO($db, $job);
        });
    }

    /** Operator identity is resolved from authenticated invocation wiring, never this array.
     * @param ?array{request_key:string,reason_code:string} $operatorRequest */
    public static function claimBuildRecovery(int $jobId, array $workerContext, ?array $operatorRequest = null): array
    {
        if ($workerContext !== []) SiteBuildContract::invalid();
        $worker = self::worker('recovery');
        $operatorId = null;
        if ($operatorRequest !== null) {
            SiteBuildContract::keys($operatorRequest, ['request_key','reason_code'], ['request_key','reason_code']);
            $operatorRequest['request_key'] = SiteBuildContract::uuid($operatorRequest['request_key']);
            if (!in_array($operatorRequest['reason_code'], SiteBuildContract::RECOVERY_REASONS, true)) SiteBuildContract::invalid();
            $operatorId = SiteBuildContract::id(self::external(static fn (): int => self::dependencies()->operatorId()));
        }
        $identity = self::jobIdentity(SiteBuildContract::id($jobId));
        return SiteBuildStore::transaction(static function (object $db) use ($identity, $worker, $operatorId, $operatorRequest): array {
            SiteManager::lockSite($db, (int) $identity['site_id']);
            $operator = $operatorId === null ? null : self::actors($db, [$operatorId], true)[$operatorId];
            $job = self::lockJob($db, $identity);
            $current = self::currentAttempt($db, $job);
            self::confirmWorker($worker, 'recovery');
            $producer = self::producer($db, $job, $current);
            if ($operatorRequest !== null) {
                $prior = SiteBuildStore::one($db, 'SELECT * FROM site_build_attempts
                    WHERE build_job_id = :job_id AND site_id = :site_id AND operator_request_key = :request_key FOR UPDATE',
                    ['job_id' => (int) $job['id'], 'site_id' => (int) $job['site_id'], 'request_key' => $operatorRequest['request_key']]);
                if ($prior !== null) {
                    if ($prior['recovery_reason_code'] !== $operatorRequest['reason_code']
                        || (int) $prior['recovery_of_attempt_id'] !== (int) ($producer['id'] ?? 0)) {
                        throw new SiteServiceException('conflict', 'The operator request conflicts with its original intent.');
                    }
                    return ['replayed' => true, 'job' => self::jobDTO($db, $job), 'attempt' => SiteBuildContract::attempt($prior)];
                }
            }
            // Resolve the authoritative DB result before allocating any recovery.
            if ($job['status'] === 'succeeded') return self::recordedResult($db, $job);
            SiteBuildContract::policy($job);
            $now = SiteBuildStore::now($db);
            if ($current !== null && in_array($current['status'], ['leased','running'], true)) {
                if ($current['lease_expires_at'] > $now && $current['deadline_at'] > $now) {
                    return ['claimed' => false, 'job' => self::jobDTO($db, $job)];
                }
                SiteBuildStore::update($db, 'site_build_attempts', $current,
                    SiteBuildContract::failure('lease_expired') + ['status' => 'expired', 'completed_at' => $now]);
                $job = self::requireRecovery($db, $job, $now, 'lease_expired', $current['recovery_trigger'] === 'operator');
            }
            if ($producer === null || !in_array($job['recovery_status'], ['required','blocked'], true)) {
                return ['claimed' => false, 'job' => self::jobDTO($db, $job)];
            }
            if ($operatorRequest === null) {
                if ($job['recovery_status'] === 'blocked') return ['claimed' => false, 'job' => self::jobDTO($db, $job)];
                if ((int) $job['automatic_recovery_count'] >= (int) $job['max_automatic_recoveries']) {
                    $job = self::requireRecovery($db, $job, $now, 'outcome_unknown', true);
                    return ['claimed' => false, 'job' => self::jobDTO($db, $job)];
                }
                if ($job['next_recovery_at'] === null || $job['next_recovery_at'] > $now) {
                    return ['claimed' => false, 'job' => self::jobDTO($db, $job)];
                }
            }
            return self::allocate($db, $job, 'recovery', $worker, $now, $producer,
                $operatorRequest === null ? null : $operatorRequest + ['actor' => $operator]);
        });
    }

    public static function completeBuildRecovery(array $lease, array $result): array
    {
        $lease = self::leaseInput($lease, 'recovery');
        SiteBuildContract::keys($result, ['disposition','receipt_key'], ['disposition','receipt_key']);
        if (!in_array($result['disposition'], ['recorded_success','adopted','safely_failed','retryable_recovery_failure','blocked'], true)) {
            SiteBuildContract::invalid();
        }
        return self::complete($lease, SiteBuildContract::hint(['receipt_key' => $result['receipt_key']]), $result['disposition'], null);
    }

    /** Runtime artifact execution and reconciliation remain absent in M6B. */
    public static function executeBuild(array $lease): never
    {
        self::leaseInput($lease, 'execution');
        throw new SiteServiceException('future_gate_required', 'Artifact construction requires M6C.');
    }
    public static function reconcileBuild(array $lease): never
    {
        self::leaseInput($lease, 'recovery');
        throw new SiteServiceException('future_gate_required', 'Artifact reconciliation requires M6C.');
    }

    public static function buildJobForActor(int $actingUserId, int $jobId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        $identity = self::jobIdentity(SiteBuildContract::id($jobId));
        return SiteBuildStore::transaction(static function (object $db) use ($identity, $actingUserId): array {
            SiteManager::lockSite($db, (int) $identity['site_id']);
            self::actors($db, [$actingUserId], true);
            $job = self::lockJob($db, $identity);
            $attempts = SiteBuildStore::rows($db, 'SELECT * FROM site_build_attempts WHERE build_job_id = :job_id
                AND site_id = :site_id ORDER BY attempt_number DESC LIMIT 100',
                ['job_id' => (int) $job['id'], 'site_id' => (int) $job['site_id']]);
            return self::jobDTO($db, $job) + ['attempts' => array_map([SiteBuildContract::class, 'attempt'], $attempts)];
        });
    }

    public static function releasesForSite(int $actingUserId, int $siteId, array $cursor = []): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteBuildContract::id($siteId);
        SiteBuildContract::keys($cursor, ['created_at','id','limit']);
        $limit = $cursor['limit'] ?? 25;
        if (!is_int($limit) || $limit < 1 || $limit > 100) SiteBuildContract::invalid();
        $id = $cursor['id'] ?? null; $time = $cursor['created_at'] ?? null;
        if (($id === null) !== ($time === null)) SiteBuildContract::invalid();
        if ($id !== null) {
            SiteBuildContract::id($id);
            if (!is_string($time) || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\.\d{6}$/D', $time) !== 1) SiteBuildContract::invalid();
        }
        return SiteBuildStore::transaction(static function (object $db) use ($actingUserId, $siteId, $limit, $id, $time): array {
            SiteManager::lockSite($db, $siteId);
            self::actors($db, [$actingUserId], true);
            $sql = 'SELECT * FROM site_releases WHERE site_id = :site_id';
            $p = ['site_id' => $siteId];
            if ($id !== null) {
                $sql .= ' AND (created_at < :cursor_time OR (created_at = :same_time AND id < :cursor_id))';
                $p += ['cursor_time' => $time, 'same_time' => $time, 'cursor_id' => $id];
            }
            $rows = SiteBuildStore::rows($db, $sql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit, $p);
            return array_map([SiteBuildContract::class, 'release'], $rows);
        });
    }

    public static function releaseManifestForActor(int $actingUserId, int $releaseId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteBuildContract::id($releaseId);
        $identity = SiteServiceSupport::read(static fn (object $db): ?array =>
            SiteBuildStore::one($db, 'SELECT * FROM site_releases WHERE id = :id', ['id' => $releaseId]));
        if ($identity === null) throw new SiteServiceException('not_found', 'The release was not found.');
        $release = SiteBuildStore::transaction(static function (object $db) use ($identity, $actingUserId): array {
            SiteManager::lockSite($db, (int) $identity['site_id']);
            self::actors($db, [$actingUserId], true);
            return SiteBuildStore::one($db, 'SELECT * FROM site_releases WHERE id = :id AND site_id = :site_id',
                ['id' => (int) $identity['id'], 'site_id' => (int) $identity['site_id']]);
        });
        $manifest = self::external(static fn (): array => self::dependencies()->inspectManifest($release));
        SiteBuildContract::keys($manifest, ['integrity_status','files'], ['integrity_status','files']);
        if (!in_array($manifest['integrity_status'], ['pass','fail'], true) || !is_array($manifest['files'])
            || !array_is_list($manifest['files']) || count($manifest['files']) > 2000) SiteBuildContract::invalid();
        foreach ($manifest['files'] as $file) {
            if (!is_array($file)) SiteBuildContract::invalid();
            SiteBuildContract::keys($file, ['path','sha256','byte_size','mime_type'], ['path','sha256','byte_size','mime_type']);
            if (!is_string($file['path']) || strlen($file['path']) > 500 || str_contains($file['path'], '..')
                || preg_match('~^[a-zA-Z0-9_-]+(?:[a-zA-Z0-9_./-]*[a-zA-Z0-9])?$~D', $file['path']) !== 1
                || !is_int($file['byte_size']) || $file['byte_size'] < 0 || $file['byte_size'] > 20971520
                || !in_array($file['mime_type'], ['text/html','text/css','image/png','image/jpeg','image/webp','image/svg+xml','application/pdf'], true)) {
                SiteBuildContract::invalid();
            }
            SiteBuildContract::hash($file['sha256']);
        }
        SiteBuildContract::json($manifest, 1048576);
        // Recheck current reader after external work, before disclosing a manifest.
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        return ['release' => SiteBuildContract::release($release)] + $manifest;
    }

    private static function allocate(object $db, array $job, string $kind, array $worker, string $now, ?array $producer, ?array $operator): array
    {
        $policy = SiteBuildContract::policy($job);
        $isRecovery = $kind === 'recovery';
        $actor = $operator['actor'] ?? SiteServiceSupport::systemActor();
        $authority = [
            'attempt_kind' => $kind, 'recovery_trigger' => $isRecovery ? ($operator === null ? 'automatic' : 'operator') : null,
            'operator_request_key' => $operator['request_key'] ?? null,
            'recovery_authorized_by_user_id' => $operator['actor']['acting_user_id'] ?? null,
            'recovery_actor_type' => $isRecovery ? $actor['actor_type'] : null,
            'recovery_reason_code' => $isRecovery ? ($operator['reason_code'] ?? 'inspect_unknown_outcome') : null,
        ];
        SiteBuildContract::claimActor($authority, $operator['actor'] ?? null);
        if ($isRecovery && ($producer === null || $producer['attempt_kind'] !== 'execution'
            || (int) $producer['build_job_id'] !== (int) $job['id'] || (int) $producer['site_id'] !== (int) $job['site_id'])) {
            throw new SiteServiceException('conflict', 'An exact original execution is required.');
        }
        $token = bin2hex(random_bytes(32));
        $attempt = $authority + [
            'site_id' => (int) $job['site_id'], 'build_job_id' => (int) $job['id'],
            'attempt_number' => (int) $job['attempt_count'] + 1,
            'execution_number' => $isRecovery ? null : (int) $job['execution_count'] + 1,
            'recovery_number' => $isRecovery ? (int) $job['recovery_count'] + 1 : null,
            'recovery_of_attempt_id' => $isRecovery ? (int) $producer['id'] : null,
            'worker_id' => $worker['worker_id'], 'lease_token_hash' => hash('sha256', $token),
            'correlation_id' => $job['correlation_id'], 'status' => 'running',
            'leased_at' => $now, 'heartbeat_at' => $now, 'started_at' => $now,
            'deadline_at' => SiteBuildStore::after($now, $policy[$isRecovery ? 'recovery_timeout_seconds' : 'execution_timeout_seconds']),
            'lease_expires_at' => SiteBuildStore::after($now, $policy['lease_seconds']),
            'completed_at' => null, 'candidate_storage_key' => null, 'candidate_artifact_hash' => null,
            'external_reference' => null, 'failure_category' => null, 'failure_code' => null, 'safe_summary' => null, 'created_at' => $now,
        ];
        $attempt['id'] = SiteBuildStore::insert($db, 'site_build_attempts', $attempt);
        $job = self::updateJob($db, $job, [
            'attempt_count' => $attempt['attempt_number'],
            'execution_count' => (int) $job['execution_count'] + ($isRecovery ? 0 : 1),
            'recovery_count' => (int) $job['recovery_count'] + ($isRecovery ? 1 : 0),
            'automatic_recovery_count' => (int) $job['automatic_recovery_count'] + ($isRecovery && $operator === null ? 1 : 0),
            'current_attempt_id' => $attempt['id'], 'status' => $isRecovery ? $job['status'] : 'running',
            'recovery_status' => $isRecovery ? 'running' : $job['recovery_status'],
            'next_attempt_at' => null, 'next_recovery_at' => null, 'completed_at' => null,
            'started_at' => $job['started_at'] ?? $now,
        ], $now);
        $metadata = ['attempt_id' => $attempt['id'], 'attempt_kind' => $kind,
            'recovery_of_attempt_id' => $attempt['recovery_of_attempt_id'], 'recovery_trigger' => $attempt['recovery_trigger'],
            'worker_policy_version' => $job['worker_policy_version'], 'deadline_at' => $attempt['deadline_at']];
        if ($operator !== null) self::event($db, $job, 'site_recovery_requested', $actor, $metadata, $operator['reason_code']);
        self::event($db, $job, $isRecovery ? 'site_recovery_started' : 'site_build_started', $actor, $metadata);
        $result = ['claimed' => true, 'lease' => ['job_id' => (int) $job['id'], 'attempt_id' => $attempt['id'],
            'attempt_kind' => $kind, 'token' => $token], 'attempt' => SiteBuildContract::attempt($attempt),
            'heartbeat_seconds' => $policy['heartbeat_seconds']];
        if (!$isRecovery) {
            $result['input'] = ['site_id' => (int) $job['site_id'], 'revision_id' => (int) $job['revision_id'],
                'release_key' => $job['release_key'], 'build_input_hash' => $job['build_input_hash'],
                'builder_version' => $job['builder_version'], 'builder_code_sha' => $job['builder_code_sha'],
                'manifest' => SiteBuildContract::decode($job['input_manifest_json'])];
        }
        return $result;
    }

    private static function requireRecovery(object $db, array $job, string $now, string $code, bool $forceBlocked = false): array
    {
        $blocked = $forceBlocked || $job['recovery_status'] === 'blocked'
            || (int) $job['automatic_recovery_count'] >= (int) $job['max_automatic_recoveries'];
        $state = $blocked ? 'blocked' : 'required';
        // Detection is durable: repeated polls do not move the due time or repeat audit.
        $status = $job['status'] === 'succeeded' ? 'succeeded' : 'reconciliation_required';
        if ($job['recovery_status'] === $state && $job['status'] === $status && $job['next_attempt_at'] === null
            && ($blocked || $job['next_recovery_at'] !== null)) return $job;
        $delay = SiteBuildContract::POLICY['automatic_recovery_delays_seconds'][min(1, (int) $job['automatic_recovery_count'])];
        $due = $job['recovery_status'] === 'required' && $job['next_recovery_at'] !== null
            ? $job['next_recovery_at'] : SiteBuildStore::after($now, $delay);
        $job = self::updateJob($db, $job, SiteBuildContract::failure($code) + [
            'status' => $status,
            'recovery_status' => $state, 'next_attempt_at' => null,
            'next_recovery_at' => $blocked ? null : $due,
            'completed_at' => $job['status'] === 'succeeded' ? $job['completed_at'] : null,
        ], $now);
        self::event($db, $job, $blocked ? 'site_recovery_blocked' : 'site_reconciliation_required',
            SiteServiceSupport::systemActor(), [], $code);
        return $job;
    }

    private static function complete(array $lease, array $hint, string $mode, ?string $failureCode): array
    {
        $worker = self::worker($lease['attempt_kind']);
        $identity = self::jobIdentity($lease['job_id']);
        $fingerprint = 'result:' . CanonicalJson::hash(['mode' => $mode === 'recorded_success' ? 'adopted' : $mode,
            'failure_code' => $failureCode, 'hint' => $hint]);
        // First resolve DB commitment; history replay must precede requester/content checks and external I/O.
        $preflight = SiteBuildStore::transaction(static function (object $db) use ($identity, $lease, $worker, $fingerprint, $mode): array {
            SiteManager::lockSite($db, (int) $identity['site_id']);
            $job = self::lockJob($db, $identity);
            $attempt = self::attempt($db, $job, $lease['attempt_id']);
            self::token($attempt, $lease, $worker);
            self::confirmWorker($worker, $lease['attempt_kind']);
            if ($mode === 'recorded_success' && $job['status'] !== 'succeeded') {
                throw new SiteServiceException('conflict', 'No committed build success was found.');
            }
            if ($attempt['completed_at'] !== null) {
                if ($attempt['external_reference'] !== $fingerprint) {
                    throw new SiteServiceException('conflict', 'Completion conflicts with the recorded result.');
                }
                return ['replay' => self::completionDTO($db, $job)];
            }
            self::assertLease($job, $attempt, $lease, $worker, SiteBuildStore::now($db));
            if ($job['status'] === 'succeeded') return ['replay' => self::recordedResult($db, $job)];
            return ['job' => $job, 'attempt' => $attempt, 'producer' => self::producer($db, $job, $attempt)];
        });
        if (isset($preflight['replay'])) return $preflight['replay'] + ['replayed' => true];
        $verified = self::external(static fn (): array => self::dependencies()->verifyOutcome($preflight['job'], $preflight['producer'], $hint));
        try { $evidence = SiteBuildContract::evidence($verified, $preflight['job'], $preflight['producer']); }
        catch (SiteServiceException) {
            // Conflicting trusted inspection evidence blocks; never adopt or persist its untrusted payload.
            return SiteBuildStore::transaction(static function (object $db) use ($identity, $lease, $worker, $fingerprint): array {
                SiteManager::lockSite($db, (int) $identity['site_id']);
                $job = self::lockJob($db, $identity); $attempt = self::attempt($db, $job, $lease['attempt_id']);
                self::confirmWorker($worker, $lease['attempt_kind']);
                $now = SiteBuildStore::now($db); self::assertLease($job, $attempt, $lease, $worker, $now);
                SiteBuildStore::update($db, 'site_build_attempts', $attempt, SiteBuildContract::failure('artifact_invalid') + [
                    'status' => $lease['attempt_kind'] === 'execution' ? 'abandoned' : 'failed',
                    'completed_at' => $now, 'external_reference' => $fingerprint]);
                $job = self::requireRecovery($db, $job, $now, 'artifact_invalid', true);
                return self::completionDTO($db, $job);
            });
        }
        $newSuccess = in_array($mode, ['success','adopted'], true);
        $builder = $newSuccess ? self::external(static fn (): array => SiteBuildContract::builder(self::dependencies()->builderIdentity())) : null;
        return SiteBuildStore::transaction(static function (object $db) use (
            $identity, $lease, $worker, $fingerprint, $evidence, $mode, $failureCode, $newSuccess, $builder
        ): array {
            [$job, $current, $source, $actors, $sourceError] = self::lockedContext($db, $identity, $newSuccess, []);
            $attempt = self::attempt($db, $job, $lease['attempt_id']);
            self::token($attempt, $lease, $worker);
            self::confirmWorker($worker, $lease['attempt_kind']);
            if ($attempt['completed_at'] !== null) {
                if ($attempt['external_reference'] !== $fingerprint) throw new SiteServiceException('conflict', 'Completion conflicts with the recorded result.');
                return self::completionDTO($db, $job) + ['replayed' => true];
            }
            if ($job['status'] === 'succeeded') return self::recordedResult($db, $job);
            $now = SiteBuildStore::now($db);
            self::assertLease($job, $attempt, $lease, $worker, $now);
            SiteBuildContract::policy($job);
            $producer = self::producer($db, $job, $attempt);
            SiteBuildContract::evidence($evidence, $job, $producer);
            $isRecovery = $lease['attempt_kind'] === 'recovery';
            if ($mode === 'recorded_success') {
                // Only a committed DB release proves recorded success; external claims do not.
                throw new SiteServiceException('conflict', 'No committed build success was found.');
            }
            $code = $failureCode ?? 'outcome_unknown';
            if ($newSuccess) {
                if ($evidence['disposition'] !== 'sealed') throw new SiteServiceException('conflict', 'A verified sealed result is required.');
                if (!self::requesterAllowed($job, $actors)) $code = 'requester_not_authorized';
                elseif ($sourceError !== null) $code = 'source_not_eligible';
                else {
                    self::assertInput($job, $source, $builder);
                    return self::acceptSuccess($db, $job, $attempt, $producer, $evidence, $fingerprint, $now);
                }
            } elseif (!self::requesterAllowed($job, $actors) && !$isRecovery) {
                $code = 'requester_not_authorized';
            }
            $safe = in_array($evidence['disposition'], ['safe_absence','quarantined'], true);
            if ($mode === 'safely_failed' && !$safe) throw new SiteServiceException('conflict', 'Verified safe settlement is required.');
            if ($isRecovery && !in_array($mode, ['safely_failed','retryable_recovery_failure','blocked','adopted'], true)) {
                SiteBuildContract::invalid();
            }
            $settled = !$newSuccess && $safe && (!$isRecovery || $mode === 'safely_failed');
            $attemptValues = SiteBuildContract::failure($code) + [
                'status' => $settled ? ($isRecovery ? 'succeeded' : 'failed') : ($isRecovery ? 'failed' : 'abandoned'),
                'completed_at' => $now, 'external_reference' => $fingerprint,
            ];
            if ($evidence['disposition'] === 'sealed') $attemptValues += [
                'candidate_storage_key' => $evidence['storage_key'], 'candidate_artifact_hash' => $evidence['artifact_hash']];
            SiteBuildStore::update($db, 'site_build_attempts', $attempt, $attemptValues);
            if ($settled) {
                $retry = !$isRecovery && in_array(SiteBuildContract::FAILURES[$code][0], ['transient_storage','transient_network'], true)
                    && (int) $job['execution_count'] < (int) $job['max_execution_attempts'];
                $delay = SiteBuildContract::POLICY['execution_retry_delays_seconds'][min(1, max(0, (int) $job['execution_count'] - 1))];
                // Preserve an earlier authorization failure across safe recovery settlement.
                if ($isRecovery && $job['failure_category'] === 'authorization') $code = 'requester_not_authorized';
                elseif ($isRecovery && in_array($job['failure_code'], array_keys(SiteBuildContract::FAILURES), true)) $code = $job['failure_code'];
                $job = self::updateJob($db, $job, SiteBuildContract::failure($code) + [
                    'status' => $retry ? 'retry_wait' : 'failed', 'completed_at' => $retry ? null : $now,
                    'next_attempt_at' => $retry ? SiteBuildStore::after($now, $delay) : null,
                    'recovery_status' => $isRecovery ? 'resolved' : $job['recovery_status'], 'next_recovery_at' => null,
                ], $now);
                self::event($db, $job, $isRecovery ? 'site_recovery_completed' : 'site_build_failed',
                    SiteServiceSupport::systemActor(), ['attempt_id' => (int) $attempt['id'], 'disposition' => 'safely_failed'], $code);
                if ($isRecovery) self::event($db, $job, 'site_build_failed', SiteServiceSupport::systemActor(),
                    ['attempt_id' => (int) $attempt['id'], 'recovery_of_attempt_id' => (int) $producer['id'],
                        'disposition' => 'safely_failed'], $code);
            } else {
                $canAutoRetry = !$isRecovery || ($mode === 'retryable_recovery_failure' && $attempt['recovery_trigger'] === 'automatic');
                $job = self::requireRecovery($db, $job, $now, $code, !$canAutoRetry);
            }
            return self::completionDTO($db, $job);
        });
    }

    private static function acceptSuccess(object $db, array $job, array $attempt, array $producer, array $evidence, string $fingerprint, string $now): array
    {
        $release = [
            'site_id' => (int) $job['site_id'], 'release_key' => $job['release_key'], 'build_job_id' => (int) $job['id'],
            'source_revision_id' => (int) $job['revision_id'], 'source_snapshot_hash' => $job['snapshot_hash'],
            'build_input_hash' => $job['build_input_hash'], 'artifact_hash' => $evidence['artifact_hash'],
            'manifest_hash' => $evidence['manifest_hash'], 'builder_version' => $job['builder_version'],
            'builder_code_sha' => $job['builder_code_sha'], 'build_profile' => $job['build_profile'],
            'storage_backend' => $evidence['storage_backend'], 'storage_key' => $evidence['storage_key'],
            'manifest_json' => SiteBuildContract::json($evidence['manifest'], 1048576),
            'validation_summary_json' => SiteBuildContract::json($evidence['validation_summary'], 16000),
            'file_count' => $evidence['file_count'], 'byte_size' => $evidence['byte_size'], 'built_at' => $now,
            'correlation_id' => $job['correlation_id'], 'created_at' => $now,
        ];
        $release['id'] = SiteBuildStore::insert($db, 'site_releases', $release);
        foreach (['build','seal'] as $phase) {
            SiteBuildStore::insert($db, 'site_release_validations', [
                'site_id' => (int) $job['site_id'], 'release_id' => $phase === 'build' ? null : $release['id'],
                'build_attempt_id' => (int) ($phase === 'build' ? $producer['id'] : $attempt['id']),
                'validation_key' => SiteServiceSupport::uuidV4(), 'validation_phase' => $phase,
                'validator_version' => $evidence['validator_version'], 'artifact_hash' => $evidence['artifact_hash'],
                'result' => 'pass', 'summary_json' => $release['validation_summary_json'], 'checked_at' => $now,
                'correlation_id' => $job['correlation_id'], 'created_at' => $now,
            ]);
        }
        SiteBuildStore::update($db, 'site_build_attempts', $attempt, ['status' => 'succeeded', 'completed_at' => $now,
            'candidate_storage_key' => $evidence['storage_key'], 'candidate_artifact_hash' => $evidence['artifact_hash'],
            'external_reference' => $fingerprint]);
        $isRecovery = $attempt['attempt_kind'] === 'recovery';
        $job = self::updateJob($db, $job, ['status' => 'succeeded', 'completed_at' => $now, 'next_attempt_at' => null,
            'recovery_status' => $isRecovery ? 'resolved' : $job['recovery_status'], 'next_recovery_at' => null,
            'failure_category' => null, 'failure_code' => null, 'safe_summary' => null], $now);
        self::event($db, $job, 'site_build_succeeded', SiteServiceSupport::systemActor(),
            ['release_id' => $release['id'], 'attempt_id' => (int) $attempt['id'], 'artifact_hash' => $release['artifact_hash']]);
        if ($isRecovery) self::event($db, $job, 'site_recovery_completed', SiteServiceSupport::systemActor(),
            ['attempt_id' => (int) $attempt['id'], 'recovery_of_attempt_id' => (int) $producer['id'], 'disposition' => 'adopted']);
        return ['job' => self::jobDTO($db, $job), 'release' => SiteBuildContract::release($release)];
    }
    private static function completionDTO(object $db, array $job): array
    {
        return $job['status'] === 'succeeded' ? self::recordedResult($db, $job) : ['job' => self::jobDTO($db, $job)];
    }

    private static function dependencies(): SiteBuildDependencies
    {
        if (self::$dependencies === null) {
            throw new SiteServiceException('future_gate_required', 'Trusted build infrastructure requires M6C wiring.');
        }
        return self::$dependencies;
    }
    private static function external(callable $call): mixed
    {
        return SiteServiceSupport::read(static function (object $db) use ($call): mixed {
            if ($db->inTransaction()) throw new SiteServiceException('conflict', 'External verification cannot run in a transaction.');
            try { return $call(); }
            catch (SiteServiceException $e) { throw $e; }
            catch (Throwable $e) {
                error_log('Website build dependency failed: ' . get_class($e));
                throw new SiteServiceException('conflict', 'Build verification is unavailable.');
            }
        });
    }
    private static function worker(string $capability): array
    {
        $worker = self::external(static fn (): array => self::dependencies()->workerIdentity($capability));
        SiteBuildContract::keys($worker, ['worker_id','environment'], ['worker_id','environment']);
        SiteBuildContract::key($worker['worker_id']);
        if (!in_array($worker['environment'], ['local','staging','production'], true)) {
            throw new SiteServiceException('unauthorized', 'A trusted environment-bound worker is required.');
        }
        return $worker;
    }
    private static function confirmWorker(array $expected, string $capability): void
    {
        // Authentication is an in-memory capability check, not an external operation.
        $current = self::dependencies()->workerIdentity($capability);
        if ($current !== $expected) throw new SiteServiceException('unauthorized', 'Worker identity changed.');
    }
    private static function correlation(mixed $value): string
    {
        if ($value !== null && !is_string($value)) SiteBuildContract::invalid();
        return SiteServiceSupport::correlationId($value);
    }
    private static function sourceIdentity(array $source): string
    {
        return CanonicalJson::hash(['snapshot_hash' => $source['revision']['snapshot_hash'],
            'business_id' => $source['business_id'], 'association_id' => $source['association_id'],
            'composition' => $source['composition']]);
    }
    private static function actors(object $db, array $ids, bool $required): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id): bool => $id !== null)));
        sort($ids, SORT_NUMERIC); $actors = [];
        foreach ($ids as $id) {
            try { $actors[$id] = SiteAuthorizationPolicy::actorContext((int) $id, $db); }
            catch (SiteServiceException $e) {
                if ($e->classification() !== 'unauthorized') throw $e;
                $actors[$id] = null;
            }
            if ($required && !($actors[$id]['is_internal_admin'] ?? false)) {
                throw new SiteServiceException('unauthorized', 'Current internal administrator authority is required.');
            }
        }
        return $actors;
    }
    private static function requesterAllowed(array $job, array $actors): bool
    {
        return $job['requested_by_user_id'] !== null && ($actors[(int) $job['requested_by_user_id']]['is_internal_admin'] ?? false);
    }
    private static function jobIdentity(int $id): array
    {
        return SiteServiceSupport::read(static function (object $db) use ($id): array {
            $job = SiteBuildStore::one($db, 'SELECT * FROM site_build_jobs WHERE id = :id', ['id' => $id]);
            if ($job === null) throw new SiteServiceException('not_found', 'The build was not found.');
            return $job;
        });
    }
    private static function lockJob(object $db, array $identity): array
    {
        $job = SiteBuildStore::one($db, 'SELECT * FROM site_build_jobs WHERE id = :id AND site_id = :site_id FOR UPDATE',
            ['id' => (int) $identity['id'], 'site_id' => (int) $identity['site_id']]);
        if ($job === null) throw new SiteServiceException('not_found', 'The build was not found.');
        foreach (['site_id','revision_id','snapshot_hash','idempotency_key','release_key','build_input_hash'] as $key) {
            if ((string) $job[$key] !== (string) $identity[$key]) throw new SiteServiceException('conflict', 'Build identity changed.');
        }
        return $job;
    }
    private static function attempt(object $db, array $job, int $id): array
    {
        $attempt = SiteBuildStore::one($db, 'SELECT * FROM site_build_attempts WHERE id = :id
            AND build_job_id = :job_id AND site_id = :site_id FOR UPDATE',
            ['id' => $id, 'job_id' => (int) $job['id'], 'site_id' => (int) $job['site_id']]);
        if ($attempt === null) throw new SiteServiceException('conflict', 'Build attempt ownership does not match.');
        return $attempt;
    }
    private static function currentAttempt(object $db, array $job): ?array
    {
        return $job['current_attempt_id'] === null ? null : self::attempt($db, $job, (int) $job['current_attempt_id']);
    }
    private static function producer(object $db, array $job, ?array $attempt): ?array
    {
        if ($attempt === null) return null;
        if ($attempt['attempt_kind'] === 'execution') return $attempt;
        $original = self::attempt($db, $job, (int) $attempt['recovery_of_attempt_id']);
        if ($original['attempt_kind'] !== 'execution' || (int) $original['attempt_number'] >= (int) $attempt['attempt_number']) {
            throw new SiteServiceException('conflict', 'Recovery must reference its original execution.');
        }
        return $original;
    }
    /** Site -> input -> ascending authorization -> job -> attempt. */
    private static function lockedContext(object $db, array $identity, bool $sourceRequired, array $callerIds): array
    {
        SiteManager::lockSite($db, (int) $identity['site_id']);
        $source = null; $error = null;
        if ($sourceRequired) {
            try { $source = SiteRevisionManager::lockBuildEligibility($db, (int) $identity['site_id'],
                (int) $identity['revision_id'], $identity['snapshot_hash']); }
            catch (SiteServiceException $e) {
                if (!in_array($e->classification(), ['invalid_transition','conflict','invalid_request','future_gate_required'], true)) throw $e;
                $error = $e;
            }
        }
        $read = SiteBuildStore::one($db, 'SELECT requested_by_user_id FROM site_build_jobs WHERE id = :id AND site_id = :site_id',
            ['id' => (int) $identity['id'], 'site_id' => (int) $identity['site_id']]);
        $requester = $read['requested_by_user_id'] === null ? null : (int) $read['requested_by_user_id'];
        $actors = self::actors($db, array_merge($callerIds, [$requester]), false);
        $job = self::lockJob($db, $identity);
        if (($job['requested_by_user_id'] === null ? null : (int) $job['requested_by_user_id']) !== $requester) {
            throw new SiteServiceException('stale_write', 'Requester identity changed; reload the build.');
        }
        return [$job, self::currentAttempt($db, $job), $source, $actors, $error];
    }
    private static function assertInput(array $job, array $source, ?array $builder = null): void
    {
        if (self::inputFailure($job, $source, $builder) !== null) {
            throw new SiteServiceException('conflict', 'The stored build input or reviewed builder does not match.');
        }
    }
    /** Pure stored-value comparison only: database/global dependency failures never become job failures. */
    private static function inputFailure(array $job, array $source, ?array $builder): ?string
    {
        try { $manifest = SiteBuildContract::recordedInput($source, $job); }
        catch (SiteServiceException) { return 'input_mismatch'; }
        if ((int) $job['association_id'] !== $source['association_id'] || (int) $job['business_id'] !== $source['business_id']) {
            return 'input_mismatch';
        }
        return $builder !== null && !SiteBuildContract::builderMatches($job, $manifest, $builder) ? 'builder_unavailable' : null;
    }
    private static function safeQueue(array $job, ?array $attempt): bool
    {
        if (!in_array($job['recovery_status'], ['none','resolved'], true)) return false;
        if ($attempt === null) return (int) $job['attempt_count'] === 0;
        return ($attempt['attempt_kind'] === 'execution' && $attempt['status'] === 'failed')
            || ($attempt['attempt_kind'] === 'recovery' && $attempt['status'] === 'succeeded' && $job['recovery_status'] === 'resolved');
    }
    private static function leaseInput(array $lease, ?string $kind = null): array
    {
        // A lease response has additional informational fields; mutations accept only its lease member.
        SiteBuildContract::keys($lease, ['job_id','attempt_id','attempt_kind','token'], ['job_id','attempt_id','attempt_kind','token']);
        SiteBuildContract::id($lease['job_id']); SiteBuildContract::id($lease['attempt_id']);
        SiteBuildContract::hash($lease['token']);
        if (!in_array($lease['attempt_kind'], ['execution','recovery'], true) || ($kind !== null && $kind !== $lease['attempt_kind'])) {
            SiteBuildContract::invalid();
        }
        return $lease;
    }
    private static function token(array $attempt, array $lease, array $worker): void
    {
        if ($attempt['attempt_kind'] !== $lease['attempt_kind'] || (int) $attempt['id'] !== $lease['attempt_id']
            || $attempt['worker_id'] !== $worker['worker_id']
            || !hash_equals($attempt['lease_token_hash'], hash('sha256', $lease['token']))) {
            throw new SiteServiceException('conflict', 'The build lease is not owned by this worker.');
        }
    }
    private static function assertLease(array $job, array $attempt, array $lease, array $worker, string $now): void
    {
        self::token($attempt, $lease, $worker);
        if ((int) $job['current_attempt_id'] !== $lease['attempt_id']
            || !in_array($attempt['status'], ['leased','running'], true)
            || !in_array($job['status'], ['running','reconciliation_required','succeeded'], true)
            || ($attempt['attempt_kind'] === 'recovery' && $job['recovery_status'] !== 'running')
            || $attempt['lease_expires_at'] <= $now || $attempt['deadline_at'] <= $now) {
            throw new SiteServiceException('conflict', 'The build lease has expired or been replaced.');
        }
    }
    private static function updateJob(object $db, array $job, array $values, string $now): array
    {
        return SiteBuildStore::update($db, 'site_build_jobs', $job, $values + [
            'lock_version' => (int) $job['lock_version'] + 1, 'updated_at' => $now]);
    }
    private static function jobDTO(object $db, array $job): array
    {
        $release = $job['status'] === 'succeeded' ? self::releaseRow($db, $job) : null;
        return SiteBuildContract::job($job + ['release_id' => $release === null ? null : (int) $release['id']]);
    }
    private static function releaseRow(object $db, array $job): ?array
    {
        return SiteBuildStore::one($db, 'SELECT * FROM site_releases WHERE build_job_id = :job_id AND site_id = :site_id',
            ['job_id' => (int) $job['id'], 'site_id' => (int) $job['site_id']]);
    }
    private static function recordedResult(object $db, array $job): array
    {
        $release = self::releaseRow($db, $job);
        if ($release === null) throw new SiteServiceException('conflict', 'The committed build result is indeterminate.');
        return ['claimed' => false, 'recorded_success' => true, 'job' => self::jobDTO($db, $job),
            'release' => SiteBuildContract::release($release)];
    }
    private static function event(object $db, array $job, string $type, array $actor, array $metadata = [], ?string $reason = null, ?string $correlation = null): void
    {
        SiteServiceSupport::event($db, (int) $job['site_id'], (int) $job['revision_id'], $actor, $type,
            $correlation ?? $job['correlation_id'], $reason, $metadata + [
                'build_job_id' => (int) $job['id'], 'attempt_count' => (int) $job['attempt_count'],
                'execution_count' => (int) $job['execution_count'], 'recovery_count' => (int) $job['recovery_count'],
                'recovery_status' => $job['recovery_status']]);
    }
}
