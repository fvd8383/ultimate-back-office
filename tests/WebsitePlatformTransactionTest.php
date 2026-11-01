<?php
declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformTransactionPDO.php';

$assertions = 0;
$check = static function (bool $ok, string $why) use (&$assertions): void {
    $assertions++;
    if (!$ok) throw new RuntimeException($why);
};
$log = tempnam(sys_get_temp_dir(), 'ubo-transaction-');
$oldLog = ini_set('error_log', $log);
$connectionProperty = new ReflectionProperty(Database::class, 'connection');
$originalConnection = $connectionProperty->getValue();
$configProperty = new ReflectionProperty(Database::class, 'config');
$originalConfig = $configProperty->getValue();
$run = static function (callable $call, ?string $classification, string $label) use ($check): ?SiteServiceException {
    try {
        $value = $call();
    } catch (SiteServiceException $e) {
        $check($classification !== null && $e->classification() === $classification, $label . ': exact service classification');
        $check($e->getPrevious() === null, $label . ': no raw previous exception');
        // Exercise exposed data and the new diagnostic's generic encodings. Engine stack
        // arguments are not a public DTO (and can contain closures in any exception).
        $public = json_encode([$e->getMessage(), $e->transactionFailure(), $e], JSON_THROW_ON_ERROR)
            . print_r($e->transactionFailure(), true) . serialize($e->transactionFailure());
        $check(!str_contains($public, 'PRIVATE-'), $label . ': no driver message/argument/SQL/DSN/token/row leak');
        return $e;
    }
    $check($classification === null && $value === 'completed', $label . ': exact success');
    return null;
};
$diagnostics = static function (int $offset, int $count, string $class) use ($log, $check): void {
    $lines = array_values(array_filter(preg_split('/\r?\n/', substr((string) file_get_contents($log), $offset))));
    $check(count($lines) === $count, 'Bounded diagnostic count');
    foreach ($lines as $line) {
        $check(str_ends_with($line, 'Website M2 database operation failed: ' . $class), 'Primary class only, existing diagnostic wording');
        $check(!str_contains($line, 'PRIVATE-'), 'No primary or secondary driver details in log');
    }
};

try {
    foreach (['support', 'store'] as $helper) {
        foreach (['success', 'callback-pdo', 'callback-inactive', 'callback-rollback-false', 'callback-rollback-throws',
            'callback-state-throws', 'commit-rollback', 'commit-rollback-false', 'commit-rollback-throws', 'commit-state-throws',
            'commit-applied', 'commit-false', 'commit-false-inactive', 'initial-state-throws', 'outer',
            'begin-false', 'begin-false-active', 'begin-throws', 'begin-state-throws'] as $case) {
            $db = new WebsitePlatformTransactionPDO(); $db->install();
            $primary = WebsitePlatformTransactionPDO::error('PRIMARY', 2006);
            $secondary = WebsitePlatformTransactionPDO::error('SECONDARY', 1213, '40001');
            $callbackError = str_starts_with($case, 'callback') ? $primary : null;
            $classification = $case === 'success' ? null : ($case === 'outer' ? 'conflict' : 'database_failure');
            $phase = str_starts_with($case, 'callback') ? 'callback' : 'commit';
            $cleanup = 'rolled_back'; $ops = ['state', 'begin', 'callback'];
            if (!str_starts_with($case, 'callback')) $ops[] = 'commit';
            if ($case !== 'success') { $ops[] = 'state'; $ops[] = 'rollback'; }
            if (str_starts_with($case, 'commit')) $db->script['commit'] = [$primary];
            if (str_ends_with($case, 'rollback-false')) { $db->script['rollback'] = [false]; $cleanup = 'rollback_false'; }
            if (str_ends_with($case, 'rollback-throws')) { $db->script['rollback'] = [$secondary]; $cleanup = 'rollback_failed'; }
            if (in_array($case, ['callback-state-throws', 'commit-state-throws'], true)) {
                $db->script['state'] = [false, $secondary]; $cleanup = 'state_failed'; array_pop($ops);
            }
            if (in_array($case, ['callback-inactive', 'commit-applied', 'commit-false-inactive'], true)) {
                $cleanup = 'inactive'; array_pop($ops);
                if ($case === 'callback-inactive') $db->script['state'] = [false, false];
                else $db->script['commit'] = [static function ($db) use ($primary, $case): bool {
                    $db->active = false;
                    if ($case === 'commit-applied') throw $primary;
                    return false;
                }];
            }
            if ($case === 'commit-false') $db->script['commit'] = [false];
            if ($case === 'initial-state-throws' || $case === 'outer') {
                $db->script['state'] = [$case === 'outer' ? true : $primary];
                $phase = 'preflight'; $cleanup = 'not_started'; $ops = ['state'];
            }
            if (str_starts_with($case, 'begin')) {
                $phase = 'begin'; $ops = ['state', 'begin', 'state'];
                $db->script['begin'] = [str_starts_with($case, 'begin-false') ? false : $primary];
                $cleanup = 'inactive';
                if ($case === 'begin-false-active') {
                    $db->script['begin'] = [static function ($db): bool { $db->active = true; return false; }];
                    $cleanup = 'rolled_back'; $ops[] = 'rollback';
                }
                if ($case === 'begin-state-throws') { $db->script['state'] = [false, $secondary]; $cleanup = 'state_failed'; }
            }
            $offset = strlen((string) file_get_contents($log));
            $callback = fn() => $db->callback($callbackError);
            $e = $run(fn() => $helper === 'support' ? SiteServiceSupport::transaction($callback)
                : SiteBuildStore::transaction($callback), $classification, $helper . '/' . $case);
            $check($db->operations === $ops, $case . ': exact operation order');
            $check([$db->callbacks, $db->commits, $db->rollbacks] === [count(array_keys($ops, 'callback')),
                count(array_keys($ops, 'commit')), count(array_keys($ops, 'rollback'))], $case . ': exact counts/no replay');
            if ($e !== null) {
                $d = $e->transactionFailure();
                $check($d['phase'] === $phase && $d['cleanup'] === $cleanup, $case . ': actual phase/cleanup result');
                $check($e->transactionRetrySafe() === ($phase === 'callback' && in_array($cleanup, ['rolled_back', 'inactive'], true)), $case . ': explicit retry safety');
                $check(($d['secondary']['driver'] ?? null) === (str_ends_with($case, 'throws') && !in_array($case, ['initial-state-throws', 'begin-throws'], true) ? 1213 : null), $case . ': secondary is separate');
                if (!str_starts_with($case, 'begin-false') && !str_starts_with($case, 'commit-false') && $case !== 'outer') {
                    $check($d['primary']['driver'] === 2006, $case . ': primary connection loss retained');
                }
            }
            $diagnostics($offset, in_array($case, ['success', 'outer'], true) ? 0 : 1,
                str_starts_with($case, 'begin-false') || str_starts_with($case, 'commit-false') ? RuntimeException::class : PDOException::class);
        }
    }

    foreach (['unauthorized', 'conflict', 'stale_write', 'invalid_transition'] as $classification) {
        foreach (['rolled_back', 'rollback_false', 'rollback_failed', 'state_failed'] as $cleanup) {
            $db = new WebsitePlatformTransactionPDO(); $db->install();
            $primary = new SiteServiceException($classification, 'Safe business result.');
            $secondary = WebsitePlatformTransactionPDO::error('SECONDARY', 1213, '40001');
            if ($cleanup === 'rollback_false') $db->script['rollback'] = [false];
            if ($cleanup === 'rollback_failed') $db->script['rollback'] = [$secondary];
            if ($cleanup === 'state_failed') $db->script['state'] = [false, $secondary];
            $offset = strlen((string) file_get_contents($log));
            $e = $run(fn() => SiteServiceSupport::transaction(fn() => $db->callback($primary)),
                $cleanup === 'rolled_back' ? $classification : 'database_failure', 'business/' . $classification . '/' . $cleanup);
            $check(($e === $primary) === ($cleanup === 'rolled_back'), 'Preserve original business exception only after safe handling');
            $check($e->transactionFailure()['primary']['classification'] === $classification, 'Primary business meaning retained');
            $check($db->operations === ($cleanup === 'state_failed' ? ['state', 'begin', 'callback', 'state']
                : ['state', 'begin', 'callback', 'state', 'rollback']), 'Business cleanup exact order');
            $check([$db->callbacks, $db->commits, $db->rollbacks] === [1, 0, $cleanup === 'state_failed' ? 0 : 1], 'Business exact counts');
            $diagnostics($offset, $cleanup === 'rolled_back' ? 0 : 1, SiteServiceException::class);
        }
    }

    // Execute the real store's bounded retry and winner-reread ordering, including the last attempt.
    foreach ([1205, 1213, 1062, 'stale_write'] as $code) foreach (['rolled_back', 'rollback_false', 'rollback_failed', 'state_failed'] as $cleanup) {
        $db = new WebsitePlatformTransactionPDO(); $db->install(); $winnerReads = 0;
        $primary = $code === 'stale_write' ? new SiteServiceException('stale_write', 'Safe stale write.')
            : WebsitePlatformTransactionPDO::error('PRIMARY', $code);
        $secondary = WebsitePlatformTransactionPDO::error('SECONDARY', 2006);
        if ($cleanup === 'rollback_false') $db->script['rollback'] = [false];
        if ($cleanup === 'rollback_failed') $db->script['rollback'] = [$secondary];
        if ($cleanup === 'state_failed') $db->script['state'] = [false, $secondary];
        $winner = static function () use ($db, &$winnerReads, $check): mixed {
            $check(!$db->active && end($db->operations) === 'rollback', 'Winner read strictly after successful rollback');
            $winnerReads++; $db->operations[] = 'winner'; return null;
        };
        $offset = strlen((string) file_get_contents($log));
        $run(fn() => SiteBuildStore::transaction(fn() => $db->callback($primary), true, $winner),
            $code === 'stale_write' && $cleanup === 'rolled_back' ? 'stale_write' : 'database_failure', 'bounded/' . $code . '/' . $cleanup);
        $attempts = $cleanup === 'rolled_back' ? 3 : 1;
        $one = $cleanup === 'state_failed' ? ['state', 'begin', 'callback', 'state'] : ['state', 'begin', 'callback', 'state', 'rollback'];
        if ($cleanup === 'rolled_back') $one[] = 'winner';
        $check($db->operations === array_merge(...array_fill(0, $attempts, $one)), 'Exact retry/reread operation order');
        $check([$db->callbacks, $db->commits, $db->rollbacks, $winnerReads] === [$attempts, 0, $cleanup === 'state_failed' ? 0 : $attempts,
            $cleanup === 'rolled_back' ? 3 : 0], 'Existing three-attempt maximum and no uncertain reread');
        $diagnostics($offset, $code === 'stale_write' && $cleanup === 'rolled_back' ? 0 : $attempts,
            $code === 'stale_write' ? SiteServiceException::class : PDOException::class);
    }

    foreach ([1205, 1213, 1062] as $code) {
        $db = new WebsitePlatformTransactionPDO(); $db->install();
        $primary = WebsitePlatformTransactionPDO::error('PRIMARY', $code); $calls = 0;
        $callback = static function () use ($db, $primary, &$calls): string { return $db->callback(++$calls === 1 ? $primary : null); };
        $run(fn() => SiteBuildStore::transaction($callback, true), null, 'eligible succeeds on second attempt');
        $check($db->operations === ['state', 'begin', 'callback', 'state', 'rollback', 'state', 'begin', 'callback', 'commit'], 'Rollback before next gates');
        $check([$db->callbacks, $db->commits, $db->rollbacks] === [2, 1, 1], 'Safe conflict retried once then committed');
    }
    $db = new WebsitePlatformTransactionPDO(); $db->install();
    $winner = static function () use ($db, $check): string {
        $check($db->operations === ['state', 'begin', 'callback', 'state', 'rollback'] && !$db->active, 'Duplicate winner lookup follows rollback before new gates');
        $db->operations[] = 'winner'; return 'completed';
    };
    $run(fn() => SiteBuildStore::transaction(fn() => $db->callback(WebsitePlatformTransactionPDO::error('PRIMARY', 1062)), true, $winner), null, 'duplicate winner');
    $check([$db->callbacks, $db->commits, $db->rollbacks] === [1, 0, 1], 'Winner returned without callback replay');

    // Server-ended callback conflict is distinct from an inactive handle AFTER COMMIT.
    $db = new WebsitePlatformTransactionPDO(); $db->install(); $calls = 0;
    $callback = static function () use ($db, &$calls): string {
        if (++$calls === 1) { $db->active = false; return $db->callback(WebsitePlatformTransactionPDO::error('PRIMARY', 1213, '40001')); }
        return $db->callback();
    };
    $run(fn() => SiteBuildStore::transaction($callback), null, 'server-ended callback conflict');
    $check($db->operations === ['state', 'begin', 'callback', 'state', 'state', 'begin', 'callback', 'commit'], 'Confirmed inactive callback keeps deadlock retry');

    foreach (['ineligible-primary', 'duplicate-disabled', 'commit-conflict'] as $case) {
        $db = new WebsitePlatformTransactionPDO(); $db->install(); $reads = 0;
        $primary = WebsitePlatformTransactionPDO::error('PRIMARY', $case === 'duplicate-disabled' ? 1062 : ($case === 'commit-conflict' ? 1213 : 2006));
        if ($case === 'commit-conflict') $db->script['commit'] = [$primary];
        if ($case === 'ineligible-primary') $db->script['rollback'] = [WebsitePlatformTransactionPDO::error('SECONDARY', 1213)];
        $e = $run(fn() => SiteBuildStore::transaction(fn() => $db->callback($case === 'commit-conflict' ? null : $primary), false,
            function () use (&$reads) { $reads++; return 'completed'; }), 'database_failure', $case);
        $check([$db->callbacks, $db->commits, $db->rollbacks, $reads] === [1, $case === 'commit-conflict' ? 1 : 0, 1, 0], 'No secondary-code/commit/disabled-duplicate retry');
        $check($e->transactionFailure()['primary']['driver'] === $primary->errorInfo[1], 'Retry decision retains primary code');
    }

    // Connection acquisition fails before PDO construction, with an injected config sentinel only.
    $connectionProperty->setValue(null, null);
    $configProperty->setValue(null, ['DB_HOST' => new class {
        public function __toString(): string { throw WebsitePlatformTransactionPDO::error('ACQUISITION'); }
    }, 'DB_PORT' => 3306, 'DB_NAME' => 'synthetic']);
    $e = $run(fn() => SiteServiceSupport::transaction(static fn() => throw new LogicException('Callback must not run')), 'database_failure', 'acquisition');
    $check($e->transactionFailure()['phase'] === 'connection' && $e->transactionFailure()['cleanup'] === 'not_started'
        && !$e->transactionRetrySafe(), 'Acquisition stays inside safe boundary without configuration bootstrap');

    // Reusing an exception cannot carry safe termination from a prior invocation into preflight.
    $db = new WebsitePlatformTransactionPDO(); $db->install(); $stale = new SiteServiceException('stale_write', 'Safe stale write.');
    $run(fn() => SiteServiceSupport::transaction(fn() => $db->callback($stale)), 'stale_write', 'first stale');
    $db->operations = []; $db->script['state'] = [$stale];
    $e = $run(fn() => SiteBuildStore::transaction(fn() => $db->callback()), 'stale_write', 'reused stale');
    $check(!$e->transactionRetrySafe() && $db->operations === ['state'], 'No stale retry flag across invocations');

    $db = new WebsitePlatformTransactionPDO(); $db->install(); $bad = WebsitePlatformTransactionPDO::error('PRIMARY');
    $bad->errorInfo = ['PRIVATE-SQLSTATE', 'PRIVATE-DRIVER', 'PRIVATE-MESSAGE'];
    $db->script['rollback'] = [$bad];
    $e = $run(fn() => SiteServiceSupport::transaction(fn() => $db->callback($bad)), 'database_failure', 'malformed diagnostics');
    $check($e->transactionFailure()['primary']['sqlstate'] === null && $e->transactionFailure()['secondary']['driver'] === null, 'Malformed metadata discarded');
    $check(!str_contains((string) file_get_contents($log), 'PRIVATE-'), 'All primary/secondary privacy canaries absent from logs');
    echo "Website transaction: $assertions assertions PASS (synthetic PDO only; no native database).\n";
} finally {
    $connectionProperty->setValue(null, $originalConnection);
    $configProperty->setValue(null, $originalConfig);
    ini_set('error_log', $oldLog);
    unlink($log);
}
