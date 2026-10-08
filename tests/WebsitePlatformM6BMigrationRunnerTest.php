<?php

declare(strict_types=1);
require_once __DIR__ . '/support/WebsitePlatformM6BMigrations.php';

$assertions = 0;
function m6mr(bool $ok, string $why): void
{
    global $assertions;
    $assertions++;
    if (!$ok) throw new RuntimeException($why);
}
function m6mrException(string $state = 'HY000', int $code = 2014): PDOException
{
    $error = new PDOException('SECRET-ERROR mysql:host=SECRET-DSN password=SECRET-PASSWORD');
    $error->errorInfo = [$state, $code, 'SECRET-DRIVER-MESSAGE'];
    return $error;
}

/** A protocol simulation, with no driver, socket, database or SQL execution. */
final class M6MigrationConnection extends PDO
{
    public array $calls = [];
    public array $events = [];
    public array $handles = [];
    public ?M6MigrationStatement $pending = null;
    public array $info = ['00000', null, null];
    public function __construct(public array $plans = []) {}
    public function errorInfo(): array { return $this->info; }
    public function errorCode(): ?string { return $this->info[0]; }
    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_ERRMODE ? PDO::ERRMODE_EXCEPTION : false;
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->calls[] = $query;
        $number = count($this->calls);
        $this->events[] = 'query:' . $number;
        if ($this->pending !== null) throw m6mrException();
        $plan = $this->plans[$number] ?? [];
        if (($plan['execution'] ?? '') === 'throw') throw m6mrException('42000', 1064);
        if (($plan['execution'] ?? '') === 'false') {
            $this->info = ['42000', 1064, 'SECRET-DRIVER-MESSAGE'];
            return false;
        }
        $sets = $plan['sets'] ?? (str_starts_with($query, 'EXECUTE ')
            ? [[1, [['SECRET-ROW'], [0], [null]]]] : [[0, []]]);
        $statement = new M6MigrationStatement($this, $number, $sets, $plan);
        $this->handles[] = $statement;
        return $this->pending = $statement;
    }
    public function exec(string $statement): int|false
    {
        // Model the old exec-only runner: non-result commands finish, EXECUTE leaves results.
        $handle = $this->query($statement);
        if ($handle !== false && $handle->columnCount() === 0) $this->pending = null;
        return 0;
    }
}
final class M6MigrationStatement extends PDOStatement
{
    public int $set = 0;
    public int $row = 0;
    public int $closed = 0;
    public int $fetched = 0;
    public bool $atEnd = false;
    public array $info = ['00000', null, null];
    public function __construct(public M6MigrationConnection $connection, public int $number,
        public array $sets, public array $plan) {}
    public function errorInfo(): array { return $this->info; }
    public function errorCode(): ?string { return $this->info[0]; }
    private function fault(string $stage): bool
    {
        if (!isset($this->plan[$stage])) return false;
        $this->info = $stage === 'close' ? ['HY001', 2008, 'SECRET-CLEANUP'] : ['HY000', 2013, 'SECRET-RESULT'];
        if ($this->plan[$stage] === 'throw') throw m6mrException($this->info[0], $this->info[1]);
        if ($this->plan[$stage] === 'unknown') $this->info = [null, null, 'SECRET-UNKNOWN'];
        if ($this->plan[$stage] === 'unsafe') $this->info = ['SECRET-STATE', 'SECRET-CODE', 'SECRET-RESULT'];
        return true;
    }
    public function columnCount(): int { return $this->sets[$this->set][0]; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
    {
        m6mr($mode === PDO::FETCH_NUM, 'Rows are incrementally discarded in numeric mode');
        m6mr($this->columnCount() > 0, 'No fetch from a no-column result');
        if ($this->fault('fetch')) return false;
        $this->fetched++;
        return $this->sets[$this->set][1][$this->row++] ?? false;
    }
    public function nextRowset(): bool
    {
        m6mr($this->row >= count($this->sets[$this->set][1]), 'Every row consumed before advancement');
        if ($this->fault('advance')) return false;
        if (!isset($this->sets[$this->set + 1])) { $this->atEnd = true; return false; }
        $this->set++;
        $this->row = 0;
        return true;
    }
    public function closeCursor(): bool
    {
        $this->closed++;
        $this->connection->events[] = 'close:' . $this->number;
        if ($this->fault('close')) return false;
        $this->connection->pending = null;
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        throw new LogicException('fetchAll must not be used');
    }
    public function rowCount(): int { throw new LogicException('rowCount must not be used'); }
}
function m6mrRun(M6MigrationConnection $db, int $first, int $last): array
{
    ob_start();
    try { m6mysqlMigrate($db, $first, $last); $failure = null; }
    catch (Throwable $error) { $failure = $error; }
    finally { $output = ob_get_clean(); }
    return [$failure, $output];
}

$file = dirname(__DIR__) . '/database/migrations/015_rename_legacy_website_integrations.sql';
$canonical = m6bSqlStatements(file_get_contents($file));
m6mr(count($canonical) === 5, 'Canonical 015 has five statements');
foreach (['SET ', 'SET ', 'PREPARE ', 'EXECUTE ', 'DEALLOCATE PREPARE '] as $index => $prefix) {
    m6mr(str_starts_with($canonical[$index], $prefix), 'Canonical statement ' . ($index + 1));
}
m6mr(str_contains($canonical[1], "'SELECT 1'"), 'Canonical no-op branch produces a result');
$db = new M6MigrationConnection([4 => ['sets' => [[1, [[1]]]]]]);
[$failure, $output] = m6mrRun($db, 15, 15);
if ($failure !== null) throw new RuntimeException('Canonical 015 result-lifecycle regression: ' . $failure->getMessage());
m6mr($db->calls === $canonical, 'Exactly once and canonical order on the same connection');
m6mr($db->pending === null, 'No pending results after DEALLOCATE');
m6mr($output === 'Applied local disposable migration ' . basename($file) . "\n", 'Only one completion after all statements, no returned values');
foreach ($db->handles as $handle) {
    m6mr($handle->closed === 1 && $handle->atEnd, 'All result sets advanced and cursor closed once');
}
m6mr(array_search('close:4', $db->events, true) < array_search('query:5', $db->events, true),
    'Result-producing EXECUTE settled before DEALLOCATE');
m6mr(!str_contains($output, 'SECRET-'), 'Returned rows never printed');

// The obsolete exec-only pattern must fail at DEALLOCATE with pending EXECUTE results.
$old = new M6MigrationConnection();
try {
    foreach ($canonical as $sql) $old->exec($sql);
    throw new LogicException('Old runner unexpectedly succeeded');
} catch (PDOException $error) {
    m6mr(count($old->calls) === 5 && $error->errorInfo[1] === 2014, 'Old pattern reproduces statement 5 / 2014');
}

// Include DDL, DML, SET, empty SELECT, no columns, multiple results and a following command.
$db = new M6MigrationConnection([6 => ['sets' => [[1, []], [0, []], [1, [[0], [null], ['SECRET-ROW']]], [1, []]]]]);
[$failure, $output] = m6mrRun($db, 14, 15);
m6mr($failure === null, 'DDL/DML/SET and mixed empty/multiple results succeed');
$expected = array_merge(m6bSqlStatements(file_get_contents(glob(dirname(__DIR__) . '/database/migrations/014_*.sql')[0])), $canonical);
m6mr($db->calls === $expected, 'Fresh 014-to-015 order preserved without batching');
m6mr(!str_contains($output, 'SECRET-'), 'Multiple result values never printed');
foreach ($db->handles as $handle) m6mr($handle->closed === 1 && $handle->atEnd, 'Every mixed result closed after end');

$commands = ['CREATE TABLE synthetic (id INT)', 'UPDATE synthetic SET id = 0 WHERE 1 = 0',
    'SET @synthetic = 0', 'SELECT 1 WHERE 1 = 0', 'SELECT 1'];
$db = new M6MigrationConnection([4 => ['sets' => [[1, []]]], 5 => ['sets' => [[1, [[1]]]]]]);
foreach ($commands as $index => $sql) m6mysqlMigrationStatement($db, $sql, 'synthetic.sql', $index + 1);
m6mr($db->calls === $commands && $db->pending === null, 'Zero affected rows and empty SELECT permit next command');
m6mr($db->handles[3]->closed === 1 && $db->handles[3]->fetched === 1, 'Empty SELECT is fetched to EOF and closed');

foreach ([19, 20] as $number) {
    $db = new M6MigrationConnection();
    [$failure, $output] = m6mrRun($db, $number, $number);
    $sql = m6bSqlStatements(file_get_contents(glob(dirname(__DIR__) . '/database/migrations/' . sprintf('%03d', $number) . '_*.sql')[0]));
    m6mr($failure === null && $db->calls === $sql && $db->pending === null, 'Later dynamic migration uses same lifecycle');
    m6mr(count(array_filter($sql, static fn($s) => str_starts_with($s, 'EXECUTE '))) > 1, 'Multiple later dynamic cycles covered');
}

foreach (['execution' => 'statement execution', 'fetch' => 'result consumption', 'advance' => 'result advancement', 'close' => 'cursor cleanup'] as $fault => $stage) {
    foreach (['throw', 'false'] as $kind) {
        $db = new M6MigrationConnection([4 => [$fault => $kind]]);
        [$failure, $output] = m6mrRun($db, 15, 16);
        m6mr($failure instanceof RuntimeException, 'Failure stops migration');
        $message = $failure->getMessage();
        m6mr(str_contains($message, basename($file) . ' statement 4') && str_contains($message, $stage), 'Filename/index/stage retained');
        $code = $fault === 'execution' ? '1064' : ($fault === 'close' ? '2008' : '2013');
        $state = $fault === 'execution' ? '42000' : ($fault === 'close' ? 'HY001' : 'HY000');
        m6mr(str_contains($message, 'SQLSTATE ' . $state) && str_contains($message, 'driver ' . $code), 'Safe SQLSTATE/driver retained');
        m6mr(strlen($message) < 256, 'Failure diagnostic length bounded');
        m6mr(count($db->calls) === 4 && $output === '', 'No later statement/migration or completion message');
        m6mr(!str_contains($message . $output, 'SECRET-') && $failure->getPrevious() === null, 'Raw errors/rows/credentials not exposed through exception chains');
        m6mr(count($db->handles) === ($fault === 'execution' ? 3 : 4), 'No statement handle fabricated after query failure');
        foreach ($db->handles as $handle) m6mr($handle->closed === 1, 'Acquired handles closed once, including failures');
    }
}
foreach (['fetch', 'advance'] as $fault) {
    foreach (['throw', 'false'] as $kind) {
        $db = new M6MigrationConnection([4 => [$fault => $kind, 'close' => $kind]]);
        [$failure, $output] = m6mrRun($db, 15, 16);
        $message = $failure->getMessage();
        m6mr(str_contains($message, 'driver 2013; secondary cursor cleanup') && str_contains($message, 'driver 2008'),
            'Original result failure precedes secondary cleanup diagnostic');
        m6mr(count($db->calls) === 4 && $output === '' && $db->handles[3]->closed === 1, 'Double failure stops after one cleanup attempt');
        m6mr(!str_contains($message, 'SECRET-'), 'Double failure is secret safe');
    }
    foreach (['unknown', 'unsafe'] as $kind) {
        $db = new M6MigrationConnection([4 => [$fault => $kind]]);
        [$failure, $output] = m6mrRun($db, 15, 16);
        m6mr($failure !== null && count($db->calls) === 4 && $output === '', 'False without successful error status is not treated as end');
        m6mr(!str_contains($failure->getMessage(), 'SECRET-'), 'Invalid status fields are not logged');
    }
}
echo "Website platform M6B migration runner: $assertions assertions passed; simulated lifecycle only, no database.\n";
