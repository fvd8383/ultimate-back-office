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
$normalizedSql = preg_replace('/\s+/', ' ', trim($connection->preparedSql));
$expectedOrder = "ORDER BY CASE status WHEN 'pending' THEN 1 WHEN 'planned' THEN 2 "
    . "WHEN 'synced' THEN 3 WHEN 'verified' THEN 4 ELSE 5 END ASC, "
    . 'status ASC, record_type ASC, host ASC, id ASC';

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
    str_contains($normalizedSql, $expectedOrder),
    'DNS statuses must use explicit ranks, followed by deterministic status, record type, host, and ID ordering.'
);
assertDomainManagerQuery(
    !str_contains($normalizedSql, 'FIELD('),
    'FIELD ordering places unlisted statuses first because they receive rank zero.'
);
assertDomainManagerQuery(
    !preg_match('/WHEN\s+"(?:pending|planned|synced|verified)"\s+THEN/i', $normalizedSql),
    'Double-quoted DNS status values are treated as identifiers when ANSI_QUOTES is enabled.'
);

preg_match_all("/WHEN '([^']+)' THEN ([0-9]+)/", $normalizedSql, $rankMatches, PREG_SET_ORDER);
$rankByStatus = [];
foreach ($rankMatches as $rankMatch) {
    $rankByStatus[$rankMatch[1]] = (int) $rankMatch[2];
}
preg_match('/ELSE ([0-9]+) END/', $normalizedSql, $elseMatch);
$unlistedRank = isset($elseMatch[1]) ? (int) $elseMatch[1] : 0;

assertDomainManagerQuery(
    $rankByStatus === ['pending' => 1, 'planned' => 2, 'synced' => 3, 'verified' => 4]
        && $unlistedRank === 5,
    'The listed statuses must rank first in the required order and all unlisted statuses must rank afterward.'
);

$fixtureRows = [
    ['id' => 31, 'status' => 'error', 'record_type' => 'TXT', 'host' => 'z'],
    ['id' => 22, 'status' => 'verified', 'record_type' => 'A', 'host' => 'a'],
    ['id' => 10, 'status' => 'pending', 'record_type' => 'TXT', 'host' => 'z'],
    ['id' => 40, 'status' => 'pending_verification', 'record_type' => 'A', 'host' => 'a'],
    ['id' => 21, 'status' => 'synced', 'record_type' => 'A', 'host' => 'a'],
    ['id' => 9, 'status' => 'pending', 'record_type' => 'A', 'host' => 'b'],
    ['id' => 30, 'status' => 'error', 'record_type' => 'A', 'host' => 'b'],
    ['id' => 20, 'status' => 'planned', 'record_type' => 'A', 'host' => 'a'],
    ['id' => 8, 'status' => 'pending', 'record_type' => 'A', 'host' => 'a'],
];

usort($fixtureRows, static function (array $left, array $right) use ($rankByStatus, $unlistedRank): int {
    $leftKey = [
        $rankByStatus[$left['status']] ?? $unlistedRank,
        $left['status'],
        $left['record_type'],
        $left['host'],
        $left['id'],
    ];
    $rightKey = [
        $rankByStatus[$right['status']] ?? $unlistedRank,
        $right['status'],
        $right['record_type'],
        $right['host'],
        $right['id'],
    ];

    return $leftKey <=> $rightKey;
});

assertDomainManagerQuery(
    array_column($fixtureRows, 'id') === [8, 9, 10, 20, 21, 22, 30, 31, 40],
    'Listed statuses, unlisted statuses, and tie rows must follow the complete deterministic ordering contract.'
);

echo "DomainManager DNS record query test passed.\n";
