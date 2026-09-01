<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteApprovalManager.php';

$assertions = 0;
$tests = 0;

function assertM2Invariant(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function m2InvariantTest(string $name, callable $callback): void
{
    global $tests;
    $callback();
    $tests++;
    echo "PASS {$name}\n";
}

function expectM2InvariantError(callable $callback, string $classification): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM2Invariant(
            $exception->classification() === $classification,
            "Expected {$classification}; got {$exception->classification()}."
        );
        return;
    }
    throw new RuntimeException("Expected {$classification} service error.");
}

function invokeM2Private(string $class, string $method, array $arguments): mixed
{
    return (new ReflectionMethod($class, $method))->invokeArgs(null, $arguments);
}

final class M2InvariantStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private M2InvariantConnection $connection, private string $sql)
    {
    }

    public function execute(array $parameters = []): bool
    {
        [$this->rows, $this->affected] = $this->connection->executeSql($this->sql, $parameters);
        return true;
    }

    public function fetch(): array|false
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(): array
    {
        return $this->rows;
    }

    public function fetchColumn(): mixed
    {
        if ($this->rows === []) {
            return false;
        }
        return array_values($this->rows[0])[0] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class M2InvariantConnection
{
    public array $sites = [];
    public array $revisions = [];
    public array $approvals = [];
    public array $events = [];
    public bool $transaction = true;

    public function prepare(string $sql): M2InvariantStatement
    {
        return new M2InvariantStatement($this, $sql);
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function executeSql(string $sql, array $parameters): array
    {
        if (str_contains($sql, 'site-m2:effective-customer-approval')) {
            $rows = [];
            foreach ($this->approvals as $approval) {
                if ((int) $approval['site_id'] !== (int) $parameters['site_id']
                    || $approval['approval_type'] !== 'customer'
                    || $approval['state'] !== 'approved'
                    || $approval['revoked_at'] !== null) {
                    continue;
                }
                $revision = $this->revisions[(int) $approval['revision_id']];
                $matches = isset($parameters['target_revision_id'])
                    ? (int) $revision['id'] === (int) $parameters['target_revision_id']
                    : (int) $revision['revision_number'] < (int) $parameters['target_revision_number'];
                if ($matches) {
                    $rows[] = ['id' => (int) $approval['id'], 'revision_number' => (int) $revision['revision_number']];
                }
            }
            usort($rows, static fn (array $a, array $b): int => $b['revision_number'] <=> $a['revision_number']);
            return [array_map(static fn (array $row): array => ['id' => $row['id']], $rows), 0];
        }

        if (str_contains($sql, 'site-m2:no-newer-material-revision')) {
            foreach ($this->revisions as $revision) {
                if ((int) $revision['site_id'] === (int) $parameters['site_id']
                    && (int) $revision['revision_number'] > (int) $parameters['revision_number']
                    && $revision['materiality'] === 'material') {
                    return [[['id' => (int) $revision['id']]], 0];
                }
            }
            return [[], 0];
        }

        if (str_contains($sql, 'site-m2:material-successor-older-revisions')) {
            $rows = [];
            foreach ($this->revisions as $revision) {
                if ((int) $revision['site_id'] === (int) $parameters['site_id']
                    && (int) $revision['revision_number'] < (int) $parameters['revision_number']) {
                    $rows[] = ['id' => (int) $revision['id']];
                }
            }
            return [$rows, 0];
        }

        if (str_contains($sql, 'site-m2:material-successor-invalidation')) {
            $olderRevisionIds = [];
            foreach ($parameters as $name => $value) {
                if (str_starts_with((string) $name, 'older_revision_id_')) {
                    $olderRevisionIds[] = (int) $value;
                }
            }
            $rows = [];
            foreach ($this->approvals as $approval) {
                $eligible = $approval['approval_type'] === 'customer'
                    ? in_array($approval['state'], ['approved', 'requested'], true)
                    : $approval['approval_type'] === 'internal' && $approval['state'] === 'requested';
                if ((int) $approval['site_id'] === (int) $parameters['site_id']
                    && in_array((int) $approval['revision_id'], $olderRevisionIds, true)
                    && $eligible) {
                    $rows[] = [
                        'id' => (int) $approval['id'],
                        'revision_id' => (int) $approval['revision_id'],
                        'approval_type' => $approval['approval_type'],
                        'state' => $approval['state'],
                    ];
                }
            }
            return [$rows, 0];
        }

        if (str_contains($sql, 'site-m2:prior-customer-decision')) {
            $rows = [];
            foreach ($this->approvals as $approval) {
                $revision = $this->revisions[(int) $approval['revision_id']];
                if ((int) $approval['site_id'] === (int) $parameters['site_id']
                    && (int) $revision['revision_number'] < (int) $parameters['current_revision_number']
                    && $approval['approval_type'] === 'customer'
                    && in_array($approval['state'], ['superseded', 'revoked'], true)) {
                    $rows[] = ['id' => (int) $approval['id']];
                }
            }
            usort($rows, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);
            return [$rows === [] ? [] : [$rows[0]], 0];
        }

        if (str_contains($sql, 'site-m2:supersede-dependent-internal-approval')) {
            $rows = [];
            foreach ($this->approvals as $approval) {
                if ((int) $approval['revision_id'] === (int) $parameters['revision_id']
                    && (int) $approval['site_id'] === (int) $parameters['site_id']
                    && $approval['approval_type'] === 'internal'
                    && $approval['state'] === 'approved'
                    && $approval['revoked_at'] === null) {
                    $rows[] = ['id' => (int) $approval['id']];
                }
            }
            return [$rows, 0];
        }

        if (str_contains($sql, 'site-m2:approval-invalidation-fallback')) {
            $revisionId = (int) $parameters['revision_id'];
            if (($this->revisions[$revisionId]['lifecycle_status'] ?? null) !== $parameters['current_status']) {
                return [[], 0];
            }
            $this->revisions[$revisionId]['lifecycle_status'] = $parameters['target_status'];
            return [[], 1];
        }

        if (str_contains($sql, 'AS internal_ok')) {
            $candidates = array_filter(
                $this->revisions,
                static fn (array $revision): bool => (int) $revision['site_id'] === (int) $parameters['site_id']
                    && $revision['lifecycle_status'] === 'internally_approved'
            );
            usort($candidates, static fn (array $a, array $b): int => $b['revision_number'] <=> $a['revision_number']);
            if ($candidates === []) {
                return [[], 0];
            }
            $revision = $candidates[0];
            $internalOk = false;
            foreach ($this->approvals as $approval) {
                $internalOk = $internalOk || ((int) $approval['revision_id'] === (int) $revision['id']
                    && $approval['approval_type'] === 'internal'
                    && $approval['state'] === 'approved'
                    && $approval['revoked_at'] === null);
            }
            return [[[$revision + ['internal_ok' => $internalOk ? 1 : 0]][0]], 0];
        }

        if (str_contains($sql, 'FROM sites WHERE id = :site_id FOR UPDATE')) {
            $site = $this->sites[(int) $parameters['site_id']] ?? null;
            return [$site === null ? [] : [$site], 0];
        }

        if (str_contains($sql, 'FROM site_revisions WHERE id = :revision_id FOR UPDATE')) {
            $revision = $this->revisions[(int) $parameters['revision_id']] ?? null;
            return [$revision === null ? [] : [$revision], 0];
        }

        if (str_contains($sql, 'FROM site_revisions sr')
            && str_contains($sql, 'sr.lifecycle_status = :revision_status')
            && isset($parameters['materiality'])) {
            foreach ($this->revisions as $revision) {
                if ((int) $revision['site_id'] === (int) $parameters['site_id']
                    && $revision['lifecycle_status'] === $parameters['revision_status']
                    && $revision['materiality'] === $parameters['materiality']) {
                    return [[['1' => 1]], 0];
                }
            }
            return [[], 0];
        }

        if (str_contains($sql, 'UPDATE sites') && isset($parameters['lock_version'])) {
            $siteId = (int) $parameters['site_id'];
            if (($this->sites[$siteId]['lock_version'] ?? -1) !== (int) $parameters['lock_version']) {
                return [[], 0];
            }
            $this->sites[$siteId]['lifecycle_status'] = $parameters['target_status'];
            $this->sites[$siteId]['lock_version']++;
            return [[], 1];
        }

        if (str_contains($sql, 'UPDATE site_approvals')) {
            $approvalId = (int) $parameters['approval_id'];
            if (!isset($this->approvals[$approvalId])) {
                return [[], 0];
            }
            $previous = $parameters['previous_state'] ?? $parameters['approved_state'] ?? $parameters['requested_state'] ?? null;
            if ($previous !== null && $this->approvals[$approvalId]['state'] !== $previous) {
                return [[], 0];
            }
            $this->approvals[$approvalId]['state'] = $parameters['superseded_state'] ?? $parameters['state'];
            $this->approvals[$approvalId]['reason'] = $parameters['reason'] ?? null;
            if (($parameters['previous_state'] ?? null) === 'requested'
                || isset($parameters['requested_state'])) {
                $this->approvals[$approvalId]['decided_at'] = 'now';
            }
            return [[], 1];
        }

        if (str_contains($sql, 'INSERT INTO site_events')) {
            $this->events[] = $parameters;
            return [[], 1];
        }

        throw new RuntimeException('Unhandled invariant-test SQL: ' . preg_replace('/\s+/', ' ', trim($sql)));
    }
}

function m2Site(int $statusVersion = 0, string $status = 'draft'): array
{
    return [
        'id' => 1,
        'site_key' => '00000000-0000-4000-8000-000000000001',
        'purpose' => '247sp',
        'lifecycle_status' => $status,
        'current_published_revision_id' => null,
        'lock_version' => $statusVersion,
    ];
}

function m2Revision(int $id, int $number, string $materiality, string $status): array
{
    return [
        'id' => $id,
        'site_id' => 1,
        'revision_number' => $number,
        'lifecycle_status' => $status,
        'based_on_revision_id' => null,
        'restored_from_revision_id' => null,
        'generation_brief_id' => null,
        'materiality' => $materiality,
        'snapshot_schema_version' => 1,
        'facts_snapshot_json' => '{}',
        'source_references_json' => '{}',
        'snapshot_hash' => str_repeat('a', 64),
        'created_by_user_id' => 1,
        'review_ready_at' => null,
        'published_at' => null,
    ];
}

function m2Approval(int $id, int $revisionId, string $type, string $state): array
{
    return [
        'id' => $id,
        'site_id' => 1,
        'revision_id' => $revisionId,
        'approval_type' => $type,
        'state' => $state,
        'revoked_at' => $state === 'revoked' ? 'now' : null,
        'decided_at' => $state === 'requested' ? null : 'earlier',
        'requested_at' => 'earlier',
        'reason' => null,
    ];
}

$internalGate = new ReflectionMethod(SiteApprovalManager::class, 'assertInternalApprovalEligible');
$revocationGate = new ReflectionMethod(SiteApprovalManager::class, 'assertApprovalRevocationGate');
$approvalGate = new ReflectionMethod(SiteManager::class, 'assertApprovalGate');
$materialSuccessor = new ReflectionMethod(SiteRevisionManager::class, 'applyMaterialSuccessorInvalidation');

m2InvariantTest('first non-material revision cannot bypass customer approval', static function () use ($internalGate): void {
    $connection = new M2InvariantConnection();
    $revision = m2Revision(1, 1, 'non_material', 'ready_for_review');
    $connection->revisions = [1 => $revision];
    expectM2InvariantError(
        static fn () => $internalGate->invoke(null, $connection, $revision),
        'invalid_transition'
    );
});

m2InvariantTest('non-material revision inherits a valid older customer approval', static function () use ($internalGate): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [
        1 => m2Revision(1, 1, 'material', 'customer_approved'),
        2 => m2Revision(2, 2, 'non_material', 'ready_for_review'),
    ];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'approved')];
    $internalGate->invoke(null, $connection, $connection->revisions[2]);
    assertM2Invariant(SiteServiceSupport::effectiveCustomerApproval($connection, $connection->revisions[2]) === 10, 'The older approval must be inherited.');
});

m2InvariantTest('ambiguous inherited customer approval fails closed', static function (): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [
        1 => m2Revision(1, 1, 'material', 'customer_approved'),
        2 => m2Revision(2, 2, 'material', 'customer_approved'),
        3 => m2Revision(3, 3, 'non_material', 'ready_for_review'),
    ];
    $connection->approvals = [
        10 => m2Approval(10, 1, 'customer', 'approved'),
        11 => m2Approval(11, 2, 'customer', 'approved'),
    ];
    expectM2InvariantError(
        static fn () => SiteServiceSupport::effectiveCustomerApproval($connection, $connection->revisions[3]),
        'conflict'
    );
});

m2InvariantTest('revoked inherited approval invalidates an internal decision', static function () use ($internalGate): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'customer_approved'), 2 => m2Revision(2, 2, 'non_material', 'ready_for_review')];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'revoked')];
    expectM2InvariantError(static fn () => $internalGate->invoke(null, $connection, $connection->revisions[2]), 'invalid_transition');
});

m2InvariantTest('superseded inherited approval invalidates an internal decision', static function () use ($internalGate): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'customer_approved'), 2 => m2Revision(2, 2, 'non_material', 'ready_for_review')];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'superseded')];
    expectM2InvariantError(static fn () => $internalGate->invoke(null, $connection, $connection->revisions[2]), 'invalid_transition');
});

m2InvariantTest('material successor closes an older requested customer workflow', static function (): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'ready_for_review'), 2 => m2Revision(2, 2, 'material', 'draft')];
    $connection->approvals = [11 => m2Approval(11, 1, 'customer', 'requested')];
    $method = new ReflectionMethod(SiteRevisionManager::class, 'supersedeOlderCustomerApprovals');
    $ids = $method->invoke(null, $connection, 1, 2, 2, ['acting_user_id' => 7, 'actor_type' => 'internal_admin'], 'test:material');
    assertM2Invariant($ids === [11], 'The older customer request must be invalidated.');
    assertM2Invariant($connection->approvals[11]['state'] === 'superseded', 'The older customer request must become superseded.');
    assertM2Invariant($connection->approvals[11]['decided_at'] === 'now', 'Superseded requested rows must close their decision timestamp.');
    expectM2InvariantError(static fn () => SiteServiceSupport::assertNoNewerMaterialRevision($connection, $connection->revisions[1]), 'invalid_transition');
});

m2InvariantTest('material successor closes an older dependent internal request', static function (): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [
        1 => m2Revision(1, 1, 'material', 'customer_approved'),
        2 => m2Revision(2, 2, 'non_material', 'ready_for_review'),
        3 => m2Revision(3, 3, 'material', 'draft'),
    ];
    $connection->approvals = [
        10 => m2Approval(10, 1, 'customer', 'approved'),
        20 => m2Approval(20, 2, 'internal', 'requested'),
    ];
    $method = new ReflectionMethod(SiteRevisionManager::class, 'supersedeOlderCustomerApprovals');
    $method->invoke(null, $connection, 1, 3, 3, ['acting_user_id' => 7, 'actor_type' => 'internal_admin'], 'test:material');
    assertM2Invariant($connection->approvals[10]['state'] === 'superseded', 'Older customer baseline approval must be superseded.');
    assertM2Invariant($connection->approvals[20]['state'] === 'superseded', 'Dependent internal request must be superseded.');
    expectM2InvariantError(static fn () => SiteServiceSupport::assertNoNewerMaterialRevision($connection, $connection->revisions[2]), 'invalid_transition');
});

m2InvariantTest('stale older requested row cannot be decided when a newer material revision exists', static function (): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'ready_for_review'), 2 => m2Revision(2, 2, 'material', 'draft')];
    $connection->approvals = [11 => m2Approval(11, 1, 'customer', 'requested')];
    expectM2InvariantError(static fn () => SiteServiceSupport::assertNoNewerMaterialRevision($connection, $connection->revisions[1]), 'invalid_transition');
    assertM2Invariant($connection->approvals[11]['state'] === 'requested', 'The stale gate must reject without approving the row.');
});

m2InvariantTest('customer supersession linkage cannot point to a newer revision', static function (): void {
    $connection = new M2InvariantConnection();
    $connection->revisions = [
        1 => m2Revision(1, 1, 'material', 'changes_requested'),
        2 => m2Revision(2, 2, 'material', 'ready_for_review'),
        3 => m2Revision(3, 3, 'material', 'changes_requested'),
    ];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'superseded'), 99 => m2Approval(99, 3, 'customer', 'revoked')];
    $id = invokeM2Private(SiteApprovalManager::class, 'priorCustomerDecision', [$connection, $connection->revisions[2]]);
    assertM2Invariant($id === 10, 'Only an older revision decision may be linked.');
});

m2InvariantTest('customer approval revocation before internal approval retains ready fallback', static function () use ($revocationGate): void {
    $connection = new M2InvariantConnection();
    $revision = m2Revision(1, 1, 'material', 'customer_approved');
    $connection->revisions = [1 => $revision];
    $revocationGate->invoke(null, $revision, 'customer');
    $result = SiteRevisionManager::applyApprovalInvalidationFallback($connection, $revision, 'ready_for_review', 'customer_approval_revoked');
    assertM2Invariant($result['lifecycle_status'] === 'ready_for_review', 'Customer-approved fallback must remain ready for review.');
});

m2InvariantTest('customer revocation after internal approval invalidates dependent approval', static function () use ($revocationGate): void {
    $connection = new M2InvariantConnection();
    $connection->sites = [1 => m2Site(0, 'approved')];
    $revision = m2Revision(1, 1, 'material', 'internally_approved');
    $connection->revisions = [1 => $revision];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'revoked'), 20 => m2Approval(20, 1, 'internal', 'approved')];
    $revocationGate->invoke(null, $revision, 'customer');
    invokeM2Private(SiteApprovalManager::class, 'supersedeApprovedInternalApproval', [$connection, $connection->approvals[10], 'test:revoke', 'customer_approval_revoked']);
    SiteRevisionManager::applyApprovalInvalidationFallback($connection, $revision, 'ready_for_review', 'customer_approval_revoked');
    $siteResult = SiteManager::applyLifecycleTransition(
        $connection,
        $connection->sites[1],
        'pending_customer',
        null,
        ['acting_user_id' => 8, 'actor_type' => 'customer'],
        'test:revoke',
        'customer_approval_revoked',
        true
    );
    assertM2Invariant($connection->approvals[20]['state'] === 'superseded', 'Dependent internal approval must be superseded.');
    assertM2Invariant($connection->approvals[20]['decided_at'] === 'earlier', 'Original internal decision timestamp must be preserved.');
    assertM2Invariant($connection->revisions[1]['lifecycle_status'] === 'ready_for_review', 'Revision must return to immutable review-ready state.');
    assertM2Invariant($siteResult['lifecycle_status'] === 'pending_customer', 'Ordinary site fallback must await customer approval.');
});

m2InvariantTest('published customer approval revocation remains future-gated', static function () use ($revocationGate): void {
    expectM2InvariantError(
        static fn () => $revocationGate->invoke(null, m2Revision(1, 1, 'material', 'published'), 'customer'),
        'future_gate_required'
    );
});

m2InvariantTest('suspension dominates every approval workflow target', static function (): void {
    foreach (['pending_customer', 'pending_internal_review', 'approved', 'draft'] as $target) {
        $connection = new M2InvariantConnection();
        $site = m2Site(4, 'suspended');
        $connection->sites = [1 => $site];
        $result = SiteManager::applyLifecycleTransition(
            $connection,
            $site,
            $target,
            null,
            ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
            'test:suspended',
            'approval_workflow',
            true
        );
        assertM2Invariant($result['lifecycle_status'] === 'suspended', "Suspended site must ignore workflow target {$target}.");
        assertM2Invariant($result['lock_version'] === 4, 'Suspension preservation must not increment lock_version.');
        assertM2Invariant($connection->events === [], 'Suspension preservation must not emit a false lifecycle change.');
    }
});

m2InvariantTest('explicit administrator resume from suspension still applies normal gates', static function () use ($approvalGate): void {
    $connection = new M2InvariantConnection();
    $connection->sites = [1 => m2Site(2, 'suspended')];
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'internally_approved')];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'approved'), 20 => m2Approval(20, 1, 'internal', 'approved')];
    $approvalGate->invoke(null, $connection, 1);
    $result = SiteManager::applyLifecycleTransition(
        $connection,
        $connection->sites[1],
        'approved',
        2,
        ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
        'test:resume',
        'explicit_admin_resume'
    );
    assertM2Invariant($result['lifecycle_status'] === 'approved', 'Explicit admin resume must remain available.');
    assertM2Invariant($result['lock_version'] === 3, 'Explicit resume must increment lock_version.');
});

m2InvariantTest('archived sites are operationally terminal but retain readable state', static function (): void {
    $archived = m2Site(5, 'archived');
    foreach (['create_revision', 'restore', 'request', 'decision', 'revoke'] as $operation) {
        expectM2InvariantError(static fn () => SiteServiceSupport::assertSiteOperational($archived), 'invalid_transition');
    }
    assertM2Invariant($archived['lifecycle_status'] === 'archived' && $archived['id'] === 1, 'Historical archived state must remain readable and unchanged.');
});

m2InvariantTest('M3 lock helper enforces same-transaction mutable states', static function (): void {
    foreach (['draft', 'validation_failed'] as $status) {
        $connection = new M2InvariantConnection();
        $connection->sites = [1 => m2Site()];
        $connection->revisions = [1 => m2Revision(1, 1, 'undetermined', $status)];
        $locked = SiteRevisionManager::lockMutableRevisionForComposition($connection, 1, 1);
        assertM2Invariant($locked['lifecycle_status'] === $status, "{$status} must be mutable under lock.");
    }
    $reviewed = new M2InvariantConnection();
    $reviewed->sites = [1 => m2Site()];
    $reviewed->revisions = [1 => m2Revision(1, 1, 'material', 'ready_for_review')];
    expectM2InvariantError(static fn () => SiteRevisionManager::lockMutableRevisionForComposition($reviewed, 1, 1), 'immutable_revision');

    $outsideTransaction = new M2InvariantConnection();
    $outsideTransaction->transaction = false;
    $outsideTransaction->sites = [1 => m2Site()];
    $outsideTransaction->revisions = [1 => m2Revision(1, 1, 'undetermined', 'draft')];
    expectM2InvariantError(static fn () => SiteRevisionManager::lockMutableRevisionForComposition($outsideTransaction, 1, 1), 'conflict');
});

m2InvariantTest('material successor resets approved site and invalidates inherited launch approval', static function () use ($approvalGate, $materialSuccessor): void {
    $connection = new M2InvariantConnection();
    $connection->sites = [1 => m2Site(7, 'approved')];
    $connection->revisions = [
        1 => m2Revision(1, 1, 'material', 'internally_approved'),
        2 => m2Revision(2, 2, 'non_material', 'internally_approved'),
        3 => m2Revision(3, 3, 'material', 'draft'),
    ];
    $connection->approvals = [
        10 => m2Approval(10, 1, 'customer', 'approved'),
        11 => m2Approval(11, 1, 'internal', 'approved'),
        20 => m2Approval(20, 2, 'internal', 'approved'),
    ];
    $ids = $materialSuccessor->invoke(
        null,
        $connection,
        $connection->sites[1],
        3,
        3,
        'material',
        ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
        'test:material'
    );
    assertM2Invariant($ids === [10], 'The material successor must invalidate the older customer approval basis.');
    assertM2Invariant($connection->approvals[10]['state'] === 'superseded', 'The older customer approval must be superseded.');
    assertM2Invariant($connection->sites[1]['lifecycle_status'] === 'draft', 'An approved pre-publication site must return to draft.');
    assertM2Invariant($connection->sites[1]['lock_version'] === 8, 'The actual site lifecycle change must increment lock_version.');
    expectM2InvariantError(static fn () => $approvalGate->invoke(null, $connection, 1), 'invalid_transition');
    assertM2Invariant($connection->revisions[3]['published_at'] === null, 'Material successor classification must not publish.');
    $lifecycleEvents = array_values(array_filter(
        $connection->events,
        static fn (array $event): bool => $event['event_type'] === 'site_lifecycle_changed'
    ));
    assertM2Invariant(count($lifecycleEvents) === 1, 'The site downgrade must emit exactly one lifecycle event.');
    assertM2Invariant($lifecycleEvents[0]['reason'] === 'material_successor_revision', 'The lifecycle event must use the material-successor reason.');
    assertM2Invariant(
        json_decode((string) $lifecycleEvents[0]['metadata_json'], true) === ['from' => 'approved', 'to' => 'draft', 'lock_version' => 8],
        'The lifecycle event must record the approved-to-draft transition.'
    );
});

m2InvariantTest('material successor resets pending approval workflows', static function () use ($materialSuccessor): void {
    foreach (['pending_customer', 'pending_internal_review'] as $siteStatus) {
        $connection = new M2InvariantConnection();
        $connection->sites = [1 => m2Site(2, $siteStatus)];
        $connection->revisions = [
            1 => m2Revision(1, 1, 'material', 'customer_approved'),
            2 => m2Revision(2, 2, 'non_material', 'ready_for_review'),
            3 => m2Revision(3, 3, 'material', 'draft'),
        ];
        $connection->approvals = $siteStatus === 'pending_customer'
            ? [10 => m2Approval(10, 1, 'customer', 'requested')]
            : [10 => m2Approval(10, 1, 'customer', 'approved'), 20 => m2Approval(20, 2, 'internal', 'requested')];
        $materialSuccessor->invoke(
            null,
            $connection,
            $connection->sites[1],
            3,
            3,
            'material',
            ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
            'test:pending'
        );
        assertM2Invariant($connection->sites[1]['lifecycle_status'] === 'draft', "{$siteStatus} must return to draft.");
        assertM2Invariant($connection->sites[1]['lock_version'] === 3, 'A pending workflow reset must increment lock_version.');
        foreach ($connection->approvals as $approval) {
            assertM2Invariant($approval['state'] === 'superseded', 'Every stale requested/baseline approval must be superseded.');
        }
    }
});

m2InvariantTest('material successor preserves suspended site while invalidating approvals', static function () use ($materialSuccessor): void {
    $connection = new M2InvariantConnection();
    $connection->sites = [1 => m2Site(5, 'suspended')];
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'customer_approved'), 2 => m2Revision(2, 2, 'material', 'draft')];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'approved')];
    $materialSuccessor->invoke(
        null,
        $connection,
        $connection->sites[1],
        2,
        2,
        'material',
        ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
        'test:suspended-material'
    );
    assertM2Invariant($connection->approvals[10]['state'] === 'superseded', 'Suspension must not prevent approval invalidation.');
    assertM2Invariant($connection->sites[1]['lifecycle_status'] === 'suspended', 'Material successor must not unsuspend the site.');
    assertM2Invariant($connection->sites[1]['lock_version'] === 5, 'Suspension preservation must not increment lock_version.');
    assertM2Invariant(
        array_filter($connection->events, static fn (array $event): bool => $event['event_type'] === 'site_lifecycle_changed') === [],
        'Suspension preservation must not emit a lifecycle event.'
    );
});

m2InvariantTest('non-material successor preserves approved site and customer baseline', static function () use ($materialSuccessor): void {
    $connection = new M2InvariantConnection();
    $connection->sites = [1 => m2Site(4, 'approved')];
    $connection->revisions = [1 => m2Revision(1, 1, 'material', 'customer_approved'), 2 => m2Revision(2, 2, 'non_material', 'draft')];
    $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'approved')];
    $ids = $materialSuccessor->invoke(
        null,
        $connection,
        $connection->sites[1],
        2,
        2,
        'non_material',
        ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
        'test:non-material'
    );
    assertM2Invariant($ids === [], 'Non-material classification must not invalidate approval rows.');
    assertM2Invariant($connection->approvals[10]['state'] === 'approved', 'The earlier customer baseline must remain current.');
    assertM2Invariant($connection->sites[1]['lifecycle_status'] === 'approved', 'Non-material classification must not downgrade the site.');
    assertM2Invariant($connection->sites[1]['lock_version'] === 4, 'Non-material classification must not change the site lock version.');
    assertM2Invariant($connection->events === [], 'Non-material classification must not emit invalidation events.');
});

m2InvariantTest('material restore resets approved site and preserves suspended site', static function () use ($materialSuccessor): void {
    foreach (['approved' => 'draft', 'suspended' => 'suspended'] as $initial => $expected) {
        $connection = new M2InvariantConnection();
        $connection->sites = [1 => m2Site(6, $initial)];
        $connection->revisions = [
            1 => m2Revision(1, 1, 'material', 'internally_approved'),
            2 => m2Revision(2, 2, 'material', 'restored'),
        ];
        $connection->approvals = [10 => m2Approval(10, 1, 'customer', 'approved')];
        $materialSuccessor->invoke(
            null,
            $connection,
            $connection->sites[1],
            2,
            2,
            'material',
            ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
            'test:restore'
        );
        assertM2Invariant($connection->approvals[10]['state'] === 'superseded', 'A material restore must invalidate the prior customer approval.');
        assertM2Invariant($connection->sites[1]['lifecycle_status'] === $expected, "Restore must leave {$initial} site as {$expected}.");
        assertM2Invariant($connection->revisions[2]['lifecycle_status'] === 'restored', 'Restore candidate must remain in restored state.');
        assertM2Invariant($connection->revisions[2]['published_at'] === null, 'Restore candidate must remain unpublished.');
        assertM2Invariant($connection->sites[1]['lock_version'] === ($initial === 'approved' ? 7 : 6), 'Restore must change lock_version only for an actual lifecycle change.');
    }
});

m2InvariantTest('material successor leaves non-workflow and future site states unchanged', static function () use ($materialSuccessor): void {
    foreach (['draft', 'demo', 'active', 'cancellation_pending', 'conversion_pending'] as $siteStatus) {
        $connection = new M2InvariantConnection();
        $connection->sites = [1 => m2Site(9, $siteStatus)];
        $connection->revisions = [1 => m2Revision(1, 1, 'material', 'draft')];
        $materialSuccessor->invoke(
            null,
            $connection,
            $connection->sites[1],
            1,
            1,
            'material',
            ['acting_user_id' => 7, 'actor_type' => 'internal_admin'],
            'test:unchanged'
        );
        assertM2Invariant($connection->sites[1]['lifecycle_status'] === $siteStatus, "{$siteStatus} must not be downgraded.");
        assertM2Invariant($connection->sites[1]['lock_version'] === 9, 'Unchanged material-successor state must preserve lock_version.');
        assertM2Invariant($connection->events === [], 'Unchanged material-successor state must not emit events.');
    }
});

echo "Website platform M2 approval invariants: {$tests} tests, {$assertions} assertions passed.\n";
