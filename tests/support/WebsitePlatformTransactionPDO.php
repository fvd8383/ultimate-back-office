<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/private/classes/SiteBuildStore.php';

/** Scripted PDO only: no parent constructor, driver, configuration or native connection. */
final class WebsitePlatformTransactionPDO extends PDO
{
    public array $operations = [];
    public array $script = [];
    public bool $active = false;
    public int $callbacks = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public function __construct(public ?PDO $inner = null) {}
    private function step(string $operation, Closure $normal): bool
    {
        $this->operations[] = $operation;
        if (($this->script[$operation] ?? []) !== []) {
            $action = array_shift($this->script[$operation]);
            if ($action instanceof Throwable) throw $action;
            if ($action instanceof Closure) return $action($this);
            if ($action !== null) return $action;
        }
        return $normal();
    }
    public function beginTransaction(): bool
    {
        return $this->step('begin', function (): bool {
            $this->active = true;
            return $this->inner?->beginTransaction() ?? true;
        });
    }
    public function inTransaction(): bool
    {
        return $this->step('state', fn(): bool => $this->inner?->inTransaction() ?? $this->active);
    }
    public function commit(): bool
    {
        $this->commits++;
        return $this->step('commit', function (): bool {
            $this->active = false;
            return $this->inner?->commit() ?? true;
        });
    }
    public function rollBack(): bool
    {
        $this->rollbacks++;
        return $this->step('rollback', function (): bool {
            $this->active = false;
            return $this->inner?->rollBack() ?? true;
        });
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->operations[] = 'prepare';
        return $this->inner?->prepare($query, $options) ?? throw new LogicException('Unexpected query on synthetic PDO.');
    }
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->inner?->lastInsertId($name) ?? throw new LogicException('Unexpected insert on synthetic PDO.');
    }
    public function callback(?Throwable $error = null): string
    {
        $this->callbacks++;
        $this->operations[] = 'callback';
        if ($error !== null) throw $error;
        return 'completed';
    }
    public function install(): void
    {
        (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $this);
    }
    public static function error(string $origin, int $driver = 2006, string $state = 'HY000'): PDOException
    {
        $e = new PDOException('PRIVATE-' . $origin . '-SQL-password-DSN-token-row');
        $e->errorInfo = [$state, $driver, 'PRIVATE-' . $origin . '-DRIVER-MESSAGE'];
        return $e;
    }
}
