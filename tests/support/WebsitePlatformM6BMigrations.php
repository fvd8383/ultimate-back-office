<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM6BSql.php';

/** Keep protocol identifiers, never driver messages (which may contain SQL/data/secrets). */
function m6mysqlMigrationError(PDO|PDOStatement $source, Throwable $error): string
{
    $info = $error instanceof PDOException ? $error->errorInfo : null;
    if (!is_array($info)) {
        try { $info = $source->errorInfo(); }
        catch (Throwable) { $info = []; }
    }
    $state = $info[0] ?? ($error instanceof PDOException ? $error->getCode() : null);
    $driver = $info[1] ?? null;
    $state = is_string($state) && preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : 'unavailable';
    $driver = (is_int($driver) || is_string($driver)) && preg_match('/^[0-9]{1,10}$/D', (string) $driver)
        ? (string) $driver : 'unavailable';
    return 'SQLSTATE ' . $state . ', driver ' . $driver;
}

/** One execution on the caller's session; completion requires all results AND cursor cleanup. */
function m6mysqlMigrationStatement(PDO $db, string $sql, string $file, int $index): void
{
    $statement = null;
    $failure = null;
    $stage = 'statement execution';
    try {
        // query() supplies a handle for both result and non-result commands. Keep the existing
        // PDO settings; SQL-level PREPARE/EXECUTE/DEALLOCATE go through the driver's supported path.
        $statement = $db->query($sql);
        if ($statement === false) throw new RuntimeException('Statement unavailable.');
        do {
            $stage = 'result consumption';
            if ($statement->columnCount() > 0) {
                while ($statement->fetch(PDO::FETCH_NUM) !== false) { /* Discard one row at a time. */ }
                if ($statement->errorCode() !== '00000') throw new RuntimeException('Result consumption failed.');
            }
            $stage = 'result advancement';
            $more = $statement->nextRowset();
            // false means EOF only with a successful status; exception mode is retained as well.
            if ($statement->errorCode() !== '00000') throw new RuntimeException('Result advancement failed.');
        } while ($more);
    } catch (Throwable $error) {
        // Capture the primary diagnostic before closeCursor can overwrite driver error state.
        $failure = $stage . ': ' . m6mysqlMigrationError($statement instanceof PDOStatement ? $statement : $db, $error);
    } finally {
        if ($statement instanceof PDOStatement) {
            try {
                if (!$statement->closeCursor()) throw new RuntimeException('Cursor cleanup failed.');
            } catch (Throwable $cleanup) {
                $diagnostic = 'cursor cleanup: ' . m6mysqlMigrationError($statement, $cleanup);
                $failure = $failure === null ? $diagnostic : $failure . '; secondary ' . $diagnostic;
            }
        }
    }
    if ($failure !== null) {
        // Do not chain the raw exception: its message/trace may disclose SQL values or credentials.
        throw new RuntimeException('Migration ' . basename($file) . ' statement ' . $index . ' failed during ' . $failure);
    }
}

function m6mysqlMigrate(PDO $db, int $first, int $last): void
{
    for ($number = $first; $number <= $last; $number++) {
        $files = glob(dirname(__DIR__, 2) . '/database/migrations/' . sprintf('%03d', $number) . '_*.sql');
        if (count($files) !== 1) throw new RuntimeException('Canonical migration identity mismatch.');
        foreach (m6bSqlStatements(file_get_contents($files[0])) as $index => $sql) {
            if (preg_match('/(?:foreign_key_checks|check_constraint_checks)\s*=\s*0/i', $sql)) throw new RuntimeException('Constraint bypass refused.');
            m6mysqlMigrationStatement($db, $sql, $files[0], $index + 1);
        }
        echo 'Applied local disposable migration ' . basename($files[0]) . "\n";
    }
}
