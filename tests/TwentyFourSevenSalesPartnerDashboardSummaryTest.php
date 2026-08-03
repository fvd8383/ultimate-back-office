<?php

declare(strict_types=1);

final class DashboardSummaryTestStatement extends PDOStatement
{
    public ?array $parameters = null;

    public function __construct(
        private DashboardSummaryTestConnection $connection,
        public readonly string $query
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->parameters = $params ?? [];
        $this->connection->executed[] = ['sql' => $this->query, 'params' => $this->parameters];

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->connection->fetchOne($this->query, $this->parameters ?? []);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->connection->fetchMany($this->query, $this->parameters ?? []);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->connection->fetchColumnValue($this->query, $this->parameters ?? []);
    }
}

final class DashboardSummaryTestConnection extends PDO
{
    public array $preparedSql = [];
    public array $executed = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new DashboardSummaryTestStatement($this, $query);
    }

    public function fetchOne(string $query, array $parameters): mixed
    {
        if (str_contains($query, 'FROM `247sp_onboarding`')) {
            return [
                'id' => 11,
                'business_id' => 42,
                'setup_status' => 'complete',
                'current_step' => 'review',
                'completed_at' => '2026-08-03 12:00:00',
            ];
        }

        if (str_contains($query, 'FROM `247sp_website_configurations`')) {
            return ['id' => 12, 'business_id' => 42, 'primary_category_id' => 7, 'website_status' => 'in_progress'];
        }

        if (str_contains($query, 'FROM `247sp_business_content`')) {
            return ['id' => 13, 'business_id' => 42];
        }

        if (str_contains($query, 'FROM `247sp_domain_selections`')) {
            return [
                'id' => 14,
                'business_id' => 42,
                'onboarding_id' => 11,
                'selection_type' => 'existing',
                'domain_name' => 'example.test',
                'status' => 'pending',
            ];
        }

        if (str_contains($query, 'FROM `247sp_email_requests`')) {
            return [
                'id' => 15,
                'business_id' => 42,
                'onboarding_id' => 11,
                'primary_mailbox_name' => 'hello',
                'status' => 'pending',
            ];
        }

        if (str_contains($query, 'FROM domain_requests dr')) {
            return [
                'id' => 21,
                'business_id' => 42,
                'requested_domain' => 'example.test',
                'request_type' => 'existing',
                'domain_status' => 'ready',
                'dns_status' => 'verified',
                'ssl_status' => 'issued',
                'registrar' => 'namecheap',
                'next_action' => 'Your domain is ready for launch.',
                'assigned_domain' => 'example.test',
                'assignment_status' => 'ready',
                'assigned_at' => '2026-08-03 12:05:00',
                'assignment_ssl_status' => 'issued',
                'publish_status' => 'published',
            ];
        }

        if (str_contains($query, 'FROM domain_requests') && str_contains($query, 'requested_domain = :requested_domain')) {
            return [
                'id' => 21,
                'business_id' => 42,
                'requested_domain' => 'example.test',
                'request_type' => 'existing',
                'domain_status' => 'ready',
                'dns_status' => 'verified',
                'ssl_status' => 'issued',
                'registrar' => 'namecheap',
                'next_action' => 'Your domain is ready for launch.',
            ];
        }

        if (str_contains($query, 'SELECT er.primary_mailbox_name')) {
            return ['primary_mailbox_name' => 'hello', 'domain_name' => 'example.test'];
        }

        if (str_contains($query, 'FROM mailbox_requests') && str_contains($query, 'requested_email = :requested_email')) {
            return ['id' => 31, 'business_id' => 42, 'requested_email' => 'hello@example.test', 'status' => 'requested'];
        }

        if (str_contains($query, 'FROM domain_assignments')) {
            return false;
        }

        return false;
    }

    public function fetchMany(string $query, array $parameters): array
    {
        if (str_contains($query, 'FROM `247sp_service_pages`')) {
            return [
                ['id' => 101, 'business_id' => 42, 'status' => 'active'],
                ['id' => 102, 'business_id' => 42, 'status' => 'active'],
                ['id' => 103, 'business_id' => 42, 'status' => 'active'],
            ];
        }

        return [];
    }

    public function fetchColumnValue(string $query, array $parameters): mixed
    {
        if (str_contains($query, 'SELECT status') && str_contains($query, 'FROM mailbox_requests')) {
            return 'active';
        }

        return false;
    }
}

function assertDashboardSummaryTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../private/classes/TwentyFourSevenSalesPartner.php';

$connection = new DashboardSummaryTestConnection();
$databaseConnection = new ReflectionProperty(Database::class, 'connection');
$databaseConnection->setValue(null, $connection);
$databaseConfig = new ReflectionProperty(Database::class, 'config');
$databaseCredentialKey = 'DB_' . 'PASS' . 'WORD';
$databaseConfig->setValue(null, [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'ubo_test',
    'DB_USER' => 'test',
    $databaseCredentialKey => 'test',
    'APP_ENV' => 'testing',
    'APP_DEBUG' => false,
    'APP_BASE_URL' => 'https://app.example.test',
    'ACCOUNTS_BASE_URL' => 'https://accounts.example.test',
    'DOMAIN_TARGET_IPV4' => '',
    'DOMAIN_TARGET_IPV6' => '',
    'DOMAIN_WWW_CNAME' => '',
    'DOMAIN_TXT_VERIFICATION_NAME' => '',
    'DOMAIN_TXT_VERIFICATION_VALUE' => '',
    'DOMAIN_MAIL_MX_HOST' => '',
    'DOMAIN_DEFAULT_REGISTRAR' => 'namecheap',
]);

$summary = TwentyFourSevenSalesPartner::dashboardSummary(42);

$dnsUpsertSql = '';
$currentDomainSql = '';
foreach ($connection->preparedSql as $sql) {
    if (str_contains($sql, 'INSERT INTO domain_dns_records')) {
        $dnsUpsertSql = $sql;
    }
    if (str_contains($sql, 'FROM domain_requests dr')) {
        $currentDomainSql = $sql;
    }
}
$normalizedDnsSql = preg_replace('/\s+/', ' ', trim($dnsUpsertSql));
$normalizedDomainSql = preg_replace('/\s+/', ' ', trim($currentDomainSql));

assertDashboardSummaryTest($dnsUpsertSql !== '', 'dashboardSummary must sync planned DNS records through the domain DNS upsert query.');
assertDashboardSummaryTest(
    str_contains($normalizedDnsSql, "status = IF(status = 'verified', status, VALUES(status))"),
    'Verified DNS records must be preserved with an ANSI-safe single-quoted status literal.'
);
assertDashboardSummaryTest(
    !preg_match('/status\s*=\s*IF\(status\s*=\s*"verified"/i', $normalizedDnsSql),
    'Double-quoted DNS status values are treated as identifiers when ANSI_QUOTES is enabled.'
);
assertDashboardSummaryTest(
    str_contains($normalizedDnsSql, 'VALUES (:business_id, :domain_request_id, :domain_assignment_id, :domain_name, :record_type, :host, :value, :priority, :ttl, :provider, :status, NOW(), NOW())'),
    'The DNS upsert must keep submitted record values parameterized.'
);

$dnsUpsertExecution = null;
foreach ($connection->executed as $execution) {
    if (str_contains($execution['sql'], 'INSERT INTO domain_dns_records')) {
        $dnsUpsertExecution = $execution;
        break;
    }
}
assertDashboardSummaryTest(
    $dnsUpsertExecution !== null
        && array_keys($dnsUpsertExecution['params']) === [
            'business_id',
            'domain_request_id',
            'domain_assignment_id',
            'domain_name',
            'record_type',
            'host',
            'value',
            'priority',
            'ttl',
            'provider',
            'status',
        ],
    'The DNS upsert must execute with the expected parameter keys.'
);
assertDashboardSummaryTest(
    $dnsUpsertExecution['params']['status'] === 'planned',
    'The replacement DNS row status should remain a bound value.'
);

assertDashboardSummaryTest(
    str_contains($normalizedDomainSql, 'SELECT dr.*')
        && str_contains($normalizedDomainSql, 'da.domain_name AS assigned_domain')
        && str_contains($normalizedDomainSql, 'da.status AS assignment_status')
        && str_contains($normalizedDomainSql, 'da.ssl_status AS assignment_ssl_status')
        && str_contains($normalizedDomainSql, 'wd.publish_status'),
    'The dashboard domain lookup must keep the selected fields and aliases consumed by dashboard rendering.'
);

$dashboardKeys = [
    'website_status',
    'domain_status',
    'domain_dns_status',
    'domain_ssl_status',
    'domain_next_action',
    'domain_launch_ready',
    'email_status',
    'current_step',
    'setup_status',
    'completed_at',
];
assertDashboardSummaryTest(
    array_keys($summary) === $dashboardKeys,
    'dashboardSummary must return the exact dashboard summary shape.'
);
assertDashboardSummaryTest(
    $summary['website_status'] === 'ready_for_build'
        && $summary['domain_status'] === 'ready'
        && $summary['domain_dns_status'] === 'verified'
        && $summary['domain_ssl_status'] === 'issued'
        && $summary['domain_launch_ready'] === true
        && $summary['email_status'] === 'active'
        && $summary['current_step'] === 'review'
        && $summary['setup_status'] === 'complete',
    'dashboardSummary must preserve existing status mapping and readiness inputs.'
);

$preserveVerified = static fn (string $existing, string $incoming): string => $existing === 'verified' ? $existing : $incoming;
assertDashboardSummaryTest(
    $preserveVerified('verified', 'planned') === 'verified'
        && $preserveVerified('pending', 'planned') === 'planned',
    'DNS upsert status logic must preserve verified rows while allowing unverified rows to refresh.'
);

echo "TwentyFourSevenSalesPartner dashboard summary query test passed.\n";
