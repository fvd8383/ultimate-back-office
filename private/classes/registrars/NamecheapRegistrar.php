<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../domains/RegistrarInterface.php';

final class NamecheapRegistrar implements RegistrarInterface
{
    private const SANDBOX_URL = 'https://api.sandbox.namecheap.com/xml.response';
    private const PRODUCTION_URL = 'https://api.namecheap.com/xml.response';

    public function checkAvailability(string $domainName): array
    {
        $response = $this->apiRequest('namecheap.domains.check', [
            'DomainList' => $domainName,
        ]);
        $attributes = $this->firstAttributes($response['xml'], 'DomainCheckResult');

        return [
            'available' => strtolower((string) ($attributes['Available'] ?? 'false')) === 'true',
            'domain' => (string) ($attributes['Domain'] ?? $domainName),
            'premium' => strtolower((string) ($attributes['IsPremiumName'] ?? 'false')) === 'true',
            'premium_price' => $attributes['PremiumRegistrationPrice'] ?? null,
            'raw' => $response,
        ];
    }

    public function registerDomain(string $domainName, array $contact, array $options = []): array
    {
        $years = max(1, min(10, (int) ($options['years'] ?? 1)));
        $params = [
            'DomainName' => $domainName,
            'Years' => $years,
            'AddFreeWhoisguard' => 'no',
            'WGEnabled' => 'no',
        ] + $this->contactParams($contact);

        $response = $this->apiRequest('namecheap.domains.create', $params, 'POST');
        $attributes = $this->firstAttributes($response['xml'], 'DomainCreateResult');

        return [
            'registered' => strtolower((string) ($attributes['Registered'] ?? 'false')) === 'true',
            'domain' => (string) ($attributes['Domain'] ?? $domainName),
            'charged_amount' => $attributes['ChargedAmount'] ?? null,
            'domain_id' => $attributes['DomainID'] ?? null,
            'order_id' => $attributes['OrderID'] ?? null,
            'transaction_id' => $attributes['TransactionID'] ?? null,
            'raw' => $response,
        ];
    }

    public function transferDomain(string $domainName, array $transferDetails = []): array
    {
        if (trim((string) ($transferDetails['auth_code'] ?? '')) === '') {
            return [
                'supported' => true,
                'status' => 'awaiting_auth_code',
                'message' => 'A transfer authorization code is required before transfer can start.',
            ];
        }

        $response = $this->apiRequest('namecheap.domains.transfer.create', [
            'DomainName' => $domainName,
            'EPPCode' => (string) $transferDetails['auth_code'],
            'Years' => max(1, min(10, (int) ($transferDetails['years'] ?? 1))),
        ], 'POST');

        return ['status' => 'submitted', 'raw' => $response];
    }

    public function getDomain(string $domainName): array
    {
        $response = $this->apiRequest('namecheap.domains.getInfo', [
            'DomainName' => $domainName,
        ]);
        $attributes = $this->firstAttributes($response['xml'], 'DomainGetInfoResult');
        $details = $this->firstChildValues($response['xml'], 'DomainDetails');

        return [
            'domain' => (string) ($attributes['DomainName'] ?? $domainName),
            'status' => (string) ($attributes['Status'] ?? ''),
            'domain_id' => $attributes['ID'] ?? null,
            'is_owner' => strtolower((string) ($attributes['IsOwner'] ?? 'false')) === 'true',
            'created_date' => $details['CreatedDate'] ?? null,
            'expired_date' => $details['ExpiredDate'] ?? null,
            'raw' => $response,
        ];
    }

    public function getDnsRecords(string $domainName): array
    {
        [$sld, $tld] = $this->splitDomain($domainName);
        $response = $this->apiRequest('namecheap.domains.dns.getHosts', [
            'SLD' => $sld,
            'TLD' => $tld,
        ]);

        $records = [];
        foreach ($this->allAttributes($response['xml'], 'Host') as $host) {
            $records[] = [
                'host' => (string) ($host['Name'] ?? ''),
                'type' => (string) ($host['Type'] ?? ''),
                'value' => (string) ($host['Address'] ?? ''),
                'priority' => isset($host['MXPref']) ? (int) $host['MXPref'] : null,
                'ttl' => isset($host['TTL']) ? (int) $host['TTL'] : 1800,
            ];
        }

        return ['records' => $records, 'raw' => $response];
    }

    public function setDnsRecords(string $domainName, array $records): array
    {
        [$sld, $tld] = $this->splitDomain($domainName);
        $params = [
            'SLD' => $sld,
            'TLD' => $tld,
        ];

        $index = 1;
        foreach ($records as $record) {
            $host = trim((string) ($record['host'] ?? ''));
            $type = strtoupper(trim((string) ($record['type'] ?? $record['record_type'] ?? '')));
            $value = trim((string) ($record['value'] ?? ''));

            if ($host === '' || $type === '' || $value === '') {
                continue;
            }

            $params['HostName' . $index] = $host;
            $params['RecordType' . $index] = $type;
            $params['Address' . $index] = $value;
            $params['TTL' . $index] = (int) ($record['ttl'] ?? 1800);

            if ($type === 'MX' && isset($record['priority'])) {
                $params['MXPref' . $index] = (int) $record['priority'];
            }

            $index++;
        }

        if ($index === 1) {
            throw new InvalidArgumentException('At least one DNS record is required.');
        }

        $response = $this->apiRequest('namecheap.domains.dns.setHosts', $params, 'POST');
        $attributes = $this->firstAttributes($response['xml'], 'DomainDNSSetHostsResult');

        return [
            'domain' => (string) ($attributes['Domain'] ?? $domainName),
            'updated' => strtolower((string) ($attributes['IsSuccess'] ?? 'false')) === 'true',
            'raw' => $response,
        ];
    }

    public function verifyOwnership(string $domainName): array
    {
        $domain = $this->getDomain($domainName);

        return [
            'verified' => (bool) ($domain['is_owner'] ?? false),
            'domain' => $domainName,
            'raw' => $domain['raw'] ?? null,
        ];
    }

    public function enableAutoRenew(string $domainName): array
    {
        return $this->autoRenewPlaceholder($domainName, true);
    }

    public function disableAutoRenew(string $domainName): array
    {
        return $this->autoRenewPlaceholder($domainName, false);
    }

    public function renewDomain(string $domainName, int $years = 1): array
    {
        $response = $this->apiRequest('namecheap.domains.renew', [
            'DomainName' => $domainName,
            'Years' => max(1, min(10, $years)),
        ], 'POST');
        $attributes = $this->firstAttributes($response['xml'], 'DomainRenewResult');

        return [
            'renewed' => strtolower((string) ($attributes['Renew'] ?? 'false')) === 'true',
            'domain' => (string) ($attributes['DomainName'] ?? $domainName),
            'charged_amount' => $attributes['ChargedAmount'] ?? null,
            'order_id' => $attributes['OrderID'] ?? null,
            'transaction_id' => $attributes['TransactionID'] ?? null,
            'raw' => $response,
        ];
    }

    public function getStatus(string $domainName): array
    {
        $domain = $this->getDomain($domainName);
        $dns = $this->getDnsRecords($domainName);

        return [
            'domain' => $domainName,
            'domain_status' => $domain['status'] ?? '',
            'is_owner' => $domain['is_owner'] ?? false,
            'expires_on' => $domain['expired_date'] ?? null,
            'dns_records' => $dns['records'] ?? [],
            'raw' => [
                'domain' => $domain['raw'] ?? null,
                'dns' => $dns['raw'] ?? null,
            ],
        ];
    }

    private function apiRequest(string $command, array $params = [], string $method = 'GET'): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Namecheap API requires the PHP cURL extension.');
        }

        $baseParams = $this->baseParams($command);
        $allParams = $baseParams + $params;
        $url = $this->serviceUrl();
        $curl = curl_init($url . (strtoupper($method) === 'GET' ? '?' . http_build_query($allParams) : ''));

        if ($curl === false) {
            throw new RuntimeException('Namecheap request could not be initialized.');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($allParams));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $body = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Namecheap request failed: ' . $error);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Namecheap returned HTTP ' . $statusCode . '.');
        }

        $xml = simplexml_load_string((string) $body);
        if ($xml === false) {
            throw new RuntimeException('Namecheap returned an invalid XML response.');
        }

        $status = strtoupper((string) ($xml['Status'] ?? ''));
        if ($status !== 'OK') {
            $errors = [];
            foreach ($xml->xpath('//*[local-name()="Error"]') ?: [] as $node) {
                $errors[] = trim((string) $node);
            }
            throw new RuntimeException(implode(' ', $errors) ?: 'Namecheap request failed.');
        }

        return [
            'status' => $status,
            'command' => $command,
            'xml' => $xml,
            'raw_xml' => (string) $body,
        ];
    }

    private function baseParams(string $command): array
    {
        $required = [
            'NAMECHEAP_API_USER',
            'NAMECHEAP_API_KEY',
            'NAMECHEAP_USERNAME',
            'NAMECHEAP_CLIENT_IP',
        ];

        $missing = [];
        foreach ($required as $key) {
            if (trim((string) Database::config($key, '')) === '') {
                $missing[] = $key;
            }
        }

        if (count($missing) > 0) {
            throw new RuntimeException('Namecheap is missing required configuration: ' . implode(', ', $missing));
        }

        return [
            'ApiUser' => (string) Database::config('NAMECHEAP_API_USER'),
            'ApiKey' => (string) Database::config('NAMECHEAP_API_KEY'),
            'UserName' => (string) Database::config('NAMECHEAP_USERNAME'),
            'ClientIp' => (string) Database::config('NAMECHEAP_CLIENT_IP'),
            'Command' => $command,
        ];
    }

    private function serviceUrl(): string
    {
        return (bool) Database::config('NAMECHEAP_SANDBOX', true)
            ? self::SANDBOX_URL
            : self::PRODUCTION_URL;
    }

    private function contactParams(array $contact): array
    {
        $params = [];
        foreach (['Registrant', 'Tech', 'Admin', 'AuxBilling'] as $prefix) {
            $params += [
                $prefix . 'FirstName' => (string) $contact['first_name'],
                $prefix . 'LastName' => (string) $contact['last_name'],
                $prefix . 'Address1' => (string) $contact['address_line_1'],
                $prefix . 'Address2' => (string) ($contact['address_line_2'] ?? ''),
                $prefix . 'City' => (string) $contact['city'],
                $prefix . 'StateProvince' => (string) $contact['state'],
                $prefix . 'PostalCode' => (string) $contact['postal_code'],
                $prefix . 'Country' => (string) ($contact['country_code'] ?? 'US'),
                $prefix . 'Phone' => (string) $contact['phone'],
                $prefix . 'EmailAddress' => (string) $contact['email'],
                $prefix . 'OrganizationName' => (string) ($contact['organization'] ?? ''),
            ];
        }

        return $params;
    }

    private function splitDomain(string $domainName): array
    {
        $parts = explode('.', strtolower(trim($domainName)));
        if (count($parts) < 2) {
            throw new InvalidArgumentException('Enter a valid domain name.');
        }

        $sld = array_shift($parts);
        return [$sld, implode('.', $parts)];
    }

    private function firstAttributes(SimpleXMLElement $xml, string $elementName): array
    {
        $all = $this->allAttributes($xml, $elementName);
        return $all[0] ?? [];
    }

    private function allAttributes(SimpleXMLElement $xml, string $elementName): array
    {
        $attributes = [];
        foreach ($xml->xpath('//*[local-name()="' . $elementName . '"]') ?: [] as $node) {
            $row = [];
            foreach ($node->attributes() as $key => $value) {
                $row[(string) $key] = (string) $value;
            }
            $attributes[] = $row;
        }

        return $attributes;
    }

    private function firstChildValues(SimpleXMLElement $xml, string $elementName): array
    {
        $nodes = $xml->xpath('//*[local-name()="' . $elementName . '"]') ?: [];
        if (!isset($nodes[0])) {
            return [];
        }

        $values = [];
        foreach ($nodes[0]->xpath('./*') ?: [] as $child) {
            $values[$child->getName()] = trim((string) $child);
        }

        return $values;
    }

    private function autoRenewPlaceholder(string $domainName, bool $enabled): array
    {
        return [
            'domain' => $domainName,
            'auto_renew' => $enabled,
            'status' => 'manual_confirmation_required',
            'message' => 'Auto-renew status should be confirmed in Namecheap.',
        ];
    }
}
