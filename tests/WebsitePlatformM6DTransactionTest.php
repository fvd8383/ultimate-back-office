<?php
declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6DFixture.php';
require_once __DIR__ . '/support/WebsitePlatformTransactionPDO.php';
require_once __DIR__ . '/support/WebsitePlatformM6DNativeContract.php';
require_once __DIR__ . '/support/WebsitePlatformM6DNativeWireConnection.php';

// The actual completion service, result selector and existing fixture; no native driver/relay.
$assertions = 0;
$check = static function (bool $ok, string $why) use (&$assertions): void {
    $assertions++;
    if (!$ok) throw new RuntimeException($why);
};
$log = tempnam(sys_get_temp_dir(), 'ubo-m6d-transaction-');
$oldLog = ini_set('error_log', $log);
try {
    foreach (['success', 'rollback', 'rollback-false', 'rollback-throws', 'state-throws', 'commit-applied', 'commit-false'] as $case) {
        $fake = new M6DDatabase(); $fake->request(); $lease = $fake->claim()['lease'];
        $receipt = SiteDeploymentService::executeDeployment($lease);
        $primary = WebsitePlatformTransactionPDO::error('PRIMARY', 2006);
        $secondary = WebsitePlatformTransactionPDO::error('SECONDARY', 1213, '40001');
        $pdo = new WebsitePlatformTransactionPDO($fake);
        $phases = []; $boundaryEffects = []; $boundaryQueries = 0; $resultCallbacks = 0;
        $fake->hook = static function (string $sql, array $params) use (&$resultCallbacks): void {
            if (str_starts_with($sql, 'INSERT INTO site_events') && ($params['event_type'] ?? '') === 'site_deployment_succeeded') $resultCallbacks++;
        };
        $wire = new M6DNativeWireConnection($pdo, $lease, 'unarmed',
            static function (string $phase) use ($pdo, $fake, $primary, $secondary, $case, &$phases, &$boundaryEffects, &$boundaryQueries): void {
                $phases[] = $phase;
                $boundaryEffects = $fake->runtime->publisher->calls; $boundaryQueries = count($fake->queries);
                $pdo->operations = []; $pdo->commits = 0; $pdo->rollbacks = 0;
                if ($case !== 'success') $pdo->script['commit'] = [$primary];
                if ($case === 'rollback-false') $pdo->script['rollback'] = [false];
                if ($case === 'rollback-throws') $pdo->script['rollback'] = [$secondary];
                if ($case === 'state-throws') $pdo->script['state'] = [$secondary];
                if ($case === 'commit-applied') $pdo->script['commit'] = [static function () use ($fake, $primary): bool {
                    $fake->commit(); throw $primary;
                }];
                if ($case === 'commit-false') $pdo->script['commit'] = [false];
            });
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $wire);
        $offset = strlen((string) file_get_contents($log)); $failure = null; $returned = null;
        try { $returned = SiteDeploymentService::completeDeploymentSuccess($lease, $receipt); }
        catch (SiteServiceException $e) { $failure = $e; }
        // A raw PDOException is deliberately NOT caught: it fails this executable regression.
        $check($phases === ['COMMIT'] && $resultCallbacks === 1, $case . ': actual result callback and selected commit exactly once');
        $check($pdo->commits === 1, $case . ': no automatic commit/callback replay');
        $expectedOperations = $case === 'success' ? ['commit'] : (in_array($case, ['state-throws', 'commit-applied'], true)
            ? ['commit', 'state'] : ['commit', 'state', 'rollback']);
        $check($pdo->operations === $expectedOperations, $case . ': exact commit/cleanup sequence, no failed-handle query');
        $check($pdo->rollbacks === count(array_keys($expectedOperations, 'rollback')), $case . ': exact rollback attempts');
        $check($fake->runtime->publisher->calls === $boundaryEffects && count($fake->queries) === $boundaryQueries,
            $case . ': no implicit observation, publisher action, compensation or slot write after failure');
        if ($case === 'success') {
            $check($failure === null && $returned['operation_status'] === 'succeeded' && $wire->commitReturned, 'Ordinary committed service result');
            $check($fake->events('site_deployment_succeeded') === 1, 'One success audit');
        } else {
            $check($returned === null && $failure instanceof SiteServiceException && $failure->classification() === 'database_failure', 'Native-required exact service failure classification');
            $check(!$failure->transactionRetrySafe() && !$wire->commitReturned, 'Every uncertain COMMIT is non-retryable');
            $d = $failure->transactionFailure();
            $cleanup = match ($case) {
                'rollback-false' => 'rollback_false', 'rollback-throws' => 'rollback_failed',
                'state-throws' => 'state_failed', 'commit-applied' => 'inactive', default => 'rolled_back',
            };
            $check($d['phase'] === 'commit' && $d['cleanup'] === $cleanup, 'Cleanup recorded truthfully, without outcome assertion');
            $check($d['primary']['driver'] === ($case === 'commit-false' ? null : 2006)
                && ($d['secondary']['driver'] ?? null) === (in_array($case, ['state-throws', 'rollback-throws'], true) ? 1213 : null), 'Primary/secondary origin preserved');
            $check($wire->transportFailure === ($case === 'commit-false' ? null : ['sqlstate' => 'HY000', 'driver' => 2006]),
                'Existing observational decorator retains primary COMMIT tuple without manufacturing classification');
            $check(M6DNativeContract::safeFailure($failure) === ['class' => 'SiteServiceException', 'code' => 'native_failure'], 'Native safe error protocol unchanged');
            $check($failure->getPrevious() === null && !str_contains(json_encode([$failure->getMessage(), $d, $failure]), 'PRIVATE-'), 'No raw driver exception/message in public data');
            if (in_array($case, ['rollback', 'commit-false'], true)) {
                $check($fake->tables['site_deployments'][$lease['deployment_id']]['status'] === 'health_check_pending'
                    && $fake->events('site_deployment_succeeded') === 0, 'Successful modeled rollback restores pending result');
            }
        }
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr((string) file_get_contents($log), $offset))));
        $check(count($lines) === ($case === 'success' ? 0 : 1), 'No extra secondary diagnostic lines in child protocol');
        foreach ($lines as $line) $check(str_ends_with($line, 'Website M2 database operation failed: '
            . ($case === 'commit-false' ? 'RuntimeException' : 'PDOException')), 'Existing primary diagnostic wording');
        if ($case === 'commit-applied') {
            $check($fake->tables['site_deployments'][$lease['deployment_id']]['status'] === 'succeeded'
                && $fake->events('site_deployment_succeeded') === 1, 'Applied-but-unacknowledged model still raised database_failure');
            // An explicit subsequent call on the healthy fixture represents separate history
            // resolution, not reconnection/replay inside the failed transaction helper.
            (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $fake);
            $before = $fake->snapshot();
            $done = SiteDeploymentService::completeDeploymentSuccess($lease, $receipt);
            $check($done['operation_status'] === 'succeeded' && $fake->snapshot() === $before
                && $fake->runtime->publisher->calls === $boundaryEffects && $resultCallbacks === 1,
                'Existing explicit committed-history path resolves without effects or result callback replay');
        }
    }
    $check(!str_contains((string) file_get_contents($log), 'PRIVATE-'), 'Primary and secondary log privacy canaries absent');
    echo "M6D transaction completion: $assertions assertions PASS (synthetic service fixture; native verification pending).\n";
} finally {
    ini_set('error_log', $oldLog);
    unlink($log);
}
