<?php

declare(strict_types=1);

final class DomainManagerDnsRecordsQueryTestStatement extends PDOStatement
{
    public ?array $parameters = null;

    public function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->parameters = $params;
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return [];
    }
}

final class DomainManagerDnsRecordsQueryTestConnection extends PDO
{
    public string $preparedSql = '';
    public DomainManagerDnsRecordsQueryTestStatement $statement;

    public function __construct()
    {
        $this->statement = new DomainManagerDnsRecordsQueryTestStatement();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql = $query;
        return $this->statement;
    }
}

function assertDomainManagerQuery(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../private/classes/domains/DomainManager.php';

$connection = new DomainManagerDnsRecordsQueryTestConnection();
$databaseConnection = new ReflectionProperty(Database::class, 'connection');
$databaseConnection->setValue(null, $connection);

$records = DomainManager::dnsRecordsForRequest(42);
$expectedOrder = "FIELD(status, 'pending', 'planned', 'synced', 'verified'), record_type ASC, host ASC, id ASC";

assertDomainManagerQuery($records === [], 'The query result should be returned unchanged.');
assertDomainManagerQuery(
    $connection->statement->parameters === ['request_id' => 42],
    'The request ID must remain parameterized.'
);
assertDomainManagerQuery(
    str_contains($connection->preparedSql, 'FROM domain_dns_records'),
    'The query must read from domain_dns_records.'
);
assertDomainManagerQuery(
    str_contains($connection->preparedSql, $expectedOrder),
    'DNS status values must use SQL string literals while preserving the existing FIELD ordering.'
);
assertDomainManagerQuery(
    !preg_match('/FIELD\s*\([^)]*"(?:pending|planned|synced|verified)"/i', $connection->preparedSql),
    'Double-quoted DNS status values are treated as identifiers when ANSI_QUOTES is enabled.'
);

echo "DomainManager DNS record query test passed.\n";
