<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../registrars/NamecheapRegistrar.php';

final class DomainManager
{
    public const STATUSES = [
        'requested',
        'awaiting_customer',
        'pending_purchase',
        'pending_dns',
        'pending_verification',
        'ssl_pending',
        'ready',
        'live',
        'error',
        'active',
        'transferred',
        'expired',
        'cancelled',
    ];

    public const DNS_STATUSES = [
        'not_started',
        'planned',
        'pending',
        'pending_verification',
        'verified',
        'error',
    ];

    public const SSL_STATUSES = [
        'pending',
        'issued',
        'renewed',
        'failed',
    ];

    public const PUBLISH_STATUSES = [
        'draft',
        'ready',
        'published',
    ];

    public static function ensureRequestForBusiness(int $businessId, string $domainName = '', string $requestType = 'purchase'): ?array
    {
        $domainName = self::normalizeDomain($domainName);
        if ($domainName === '') {
            $onboardingDomain = self::latestOnboardingDomain($businessId);
            $domainName = $onboardingDomain['domain_name'];
            $requestType = $onboardingDomain['selection_type'] ?: $requestType;
        }

        if ($domainName === '') {
            return null;
        }

        $requestType = self::normalizeRequestType($requestType);
        $status = $requestType === 'existing' ? 'awaiting_customer' : 'requested';
        $nextAction = $requestType === 'existing'
            ? 'Update DNS with the records shown here so your website can be verified.'
            : 'Domain request received. Availability check and purchase are next.';

        $statement = Database::connection()->prepare(
            'INSERT INTO domain_requests (
                business_id, requested_domain, request_type, domain_status, dns_status, ssl_status, next_action, created_at, updated_at
             ) VALUES (
                :business_id, :requested_domain, :request_type, :domain_status, :dns_status, :ssl_status, :next_action, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                request_type = VALUES(request_type),
                next_action = COALESCE(next_action, VALUES(next_action)),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'requested_domain' => $domainName,
            'request_type' => $requestType,
            'domain_status' => $status,
            'dns_status' => 'planned',
            'ssl_status' => 'pending',
            'next_action' => $nextAction,
        ]);

        $request = self::requestForBusinessDomain($businessId, $domainName);
        if ($request !== null) {
            self::syncPlannedDnsRecords($request);
        }

        return $request;
    }

    public static function createCustomerRequest(int $businessId, int $userId, string $domainName, string $requestType): array
    {
        $request = self::ensureRequestForBusiness($businessId, $domainName, $requestType);
        if ($request === null) {
            throw new InvalidArgumentException('Enter a valid domain name.');
        }

        self::logDomainEvent($request, $userId, 'customer_domain_requested', 'recorded', 'Domain request submitted.');
        self::logActivity((int) $request['business_id'], $userId, 'domain_requested', 'Domain request submitted');

        return $request;
    }

    public static function customerDomainsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT dr.*,
                    b.business_name,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
                    da.ssl_status AS assignment_ssl_status,
                    wd.publish_status
             FROM domain_requests dr
             INNER JOIN businesses b ON b.id = dr.business_id
             INNER JOIN business_users bu ON bu.business_id = b.id
             LEFT JOIN domain_assignments da ON da.business_id = b.id
             LEFT JOIN website_domains wd ON wd.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY dr.created_at DESC, dr.id DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        $domains = $statement->fetchAll();
        foreach ($domains as &$domain) {
            $domain['progress'] = self::progressForDomain($domain);
        }

        return $domains;
    }

    public static function customerBusinessesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id, b.business_name
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.business_name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function adminDomainRequests(): array
    {
        $domains = Database::connection()->query(
            'SELECT dr.*,
                    b.business_name,
                    da.id AS assignment_id,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
                    da.ssl_status AS assignment_ssl_status,
                    wd.publish_status,
                    gw.id AS website_id,
                    gw.status AS website_status
             FROM domain_requests dr
             INNER JOIN businesses b ON b.id = dr.business_id
             LEFT JOIN domain_assignments da ON da.business_id = b.id
             LEFT JOIN website_domains wd ON wd.business_id = b.id
             LEFT JOIN `247sp_generated_websites` gw ON gw.business_id = b.id
             ORDER BY dr.created_at DESC, dr.id DESC'
        )->fetchAll();

        foreach ($domains as &$domain) {
            $domain['progress'] = self::progressForDomain($domain);
        }

        return $domains;
    }

    public static function domainEvents(int $requestId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT de.*, u.first_name, u.last_name
             FROM domain_events de
             LEFT JOIN users u ON u.id = de.user_id
             WHERE de.domain_request_id = :request_id
             ORDER BY de.created_at DESC, de.id DESC
             LIMIT 25'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    public static function dnsRecordsForRequest(int $requestId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM domain_dns_records
             WHERE domain_request_id = :request_id
             ORDER BY FIELD(status, "pending", "planned", "synced", "verified"), record_type ASC, host ASC, id ASC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    public static function currentDomainForBusiness(int $businessId): ?array
    {
        self::ensureRequestForBusiness($businessId);

        $statement = Database::connection()->prepare(
            'SELECT dr.*,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
                    da.ssl_status AS assignment_ssl_status,
                    wd.publish_status
             FROM domain_requests dr
             LEFT JOIN domain_assignments da ON da.business_id = dr.business_id
             LEFT JOIN website_domains wd ON wd.business_id = dr.business_id
             WHERE dr.business_id = :business_id
             ORDER BY dr.created_at DESC, dr.id DESC
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $domain = $statement->fetch();

        if (!$domain) {
            return null;
        }

        $domain['progress'] = self::progressForDomain($domain);
        return $domain;
    }

    public static function syncWebsiteDomainForBusiness(int $businessId): void
    {
        $statement = Database::connection()->prepare(
            "SELECT *
             FROM domain_assignments
             WHERE business_id = :business_id
               AND status IN ('active', 'transferred', 'ready', 'live')
             ORDER BY assigned_at DESC, id DESC
             LIMIT 1"
        );
        $statement->execute(['business_id' => $businessId]);
        $assignment = $statement->fetch();

        if (!$assignment) {
            return;
        }

        self::upsertWebsiteDomain($businessId, (int) $assignment['id'], (string) $assignment['domain_name'], (string) $assignment['status']);
    }

    public static function updateDomainRequest(int $requestId, int $adminUserId, string $status, array $input): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported domain status.');
        }

        $request = self::request($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Domain request not found.');
        }

        $metadata = [
            'request_type' => self::normalizeRequestType($input['request_type'] ?? $request['request_type'] ?? 'purchase'),
            'registrar' => self::nullableText($input['registrar'] ?? null, 100),
            'annual_cost' => self::nullableMoney($input['annual_cost'] ?? null),
            'purchase_date' => self::nullableDate($input['purchase_date'] ?? null),
            'expiration_date' => self::nullableDate($input['expiration_date'] ?? null),
            'dns_status' => self::normalizeDnsStatus($input['dns_status'] ?? $request['dns_status'] ?? 'not_started'),
            'ssl_status' => self::normalizeSslStatus($input['ssl_status'] ?? $request['ssl_status'] ?? 'pending'),
            'next_action' => self::nullableText($input['next_action'] ?? null, 255),
        ];

        Database::connection()->beginTransaction();

        try {
            self::updateRequestFields($requestId, $metadata + [
                'domain_status' => $status,
                'last_error' => null,
            ]);

            $updatedRequest = self::request($requestId) ?: $request;
            if (self::isAssignable($status, $metadata['dns_status'], $metadata['ssl_status'])) {
                $assignment = self::upsertAssignment($updatedRequest, $status);
                self::upsertWebsiteDomain((int) $request['business_id'], (int) $assignment['id'], (string) $assignment['domain_name'], $status);
            } elseif (in_array($status, ['expired', 'cancelled', 'error'], true)) {
                self::updateAssignmentStatus((int) $request['business_id'], $status);
                self::setWebsitePublishStatus((int) $request['business_id'], 'draft');
            }

            self::logDomainEvent($updatedRequest, $adminUserId, 'admin_domain_status_updated', 'recorded', 'Admin updated domain status to ' . $status);
            self::logActivity((int) $request['business_id'], $adminUserId, 'domain_request_updated', 'Domain request updated to ' . $status);
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function checkAvailability(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        $registrar = self::registrarForRequest($request);
        $result = $registrar->checkAvailability((string) $request['requested_domain']);
        $available = (bool) ($result['available'] ?? false);

        self::updateRequestFields($requestId, [
            'domain_status' => $available ? 'pending_purchase' : 'awaiting_customer',
            'registrar' => self::registrarKey($request),
            'registrar_response_json' => self::jsonForStorage($result),
            'next_action' => $available ? 'Domain is available. Purchase is ready for admin review.' : 'Choose a different domain or connect a domain you already own.',
            'last_error' => $available ? null : 'Domain is not available.',
            'last_checked_at' => self::now(),
        ]);
        self::logDomainEvent($request, $adminUserId, 'registrar_availability_checked', $available ? 'success' : 'error', $available ? 'Domain is available.' : 'Domain is not available.', null, $result);
    }

    public static function purchaseDomain(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        if ((string) ($request['request_type'] ?? 'purchase') !== 'purchase') {
            throw new InvalidArgumentException('Only purchase requests can be purchased through a registrar.');
        }

        $business = self::businessWithOwner((int) $request['business_id']);
        if ($business === null) {
            throw new InvalidArgumentException('Business contact information could not be found.');
        }

        $registrar = self::registrarForRequest($request);
        $contact = self::registrarContactFromBusiness($business);
        $result = $registrar->registerDomain((string) $request['requested_domain'], $contact, ['years' => 1]);

        if (!(bool) ($result['registered'] ?? false)) {
            self::markError($request, $adminUserId, 'registrar_purchase_failed', 'Domain purchase did not complete.', $result);
            throw new RuntimeException('Domain purchase did not complete.');
        }

        $expirationDate = self::dateOneYearFromNow();
        Database::connection()->beginTransaction();
        try {
            self::updateRequestFields($requestId, [
                'domain_status' => 'pending_dns',
                'registrar' => self::registrarKey($request),
                'registrar_domain_id' => self::nullableText($result['domain_id'] ?? null, 100),
                'registrar_order_id' => self::nullableText($result['order_id'] ?? null, 100),
                'registrar_transaction_id' => self::nullableText($result['transaction_id'] ?? null, 100),
                'registrar_response_json' => self::jsonForStorage($result),
                'annual_cost' => self::nullableMoney($result['charged_amount'] ?? null),
                'purchase_date' => date('Y-m-d'),
                'expiration_date' => $expirationDate,
                'dns_status' => 'planned',
                'ssl_status' => 'pending',
                'next_action' => 'DNS records are ready to sync.',
                'last_error' => null,
                'last_checked_at' => self::now(),
            ]);
            $updatedRequest = self::request($requestId) ?: $request;
            self::syncPlannedDnsRecords($updatedRequest);
            self::upsertAssignment($updatedRequest, 'pending_dns');
            self::logDomainEvent($updatedRequest, $adminUserId, 'registrar_domain_purchased', 'success', 'Domain purchased through registrar.', null, $result);
            self::logActivity((int) $request['business_id'], $adminUserId, 'domain_purchased', 'Domain purchased through registrar');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function syncDnsRecords(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        self::syncPlannedDnsRecords($request);
        $records = self::dnsRecordsForRequest($requestId);
        $registrarRecords = [];
        foreach ($records as $record) {
            if (!in_array((string) $record['status'], ['planned', 'pending', 'synced', 'verified'], true)) {
                continue;
            }
            if ((string) $record['value'] === '') {
                continue;
            }
            $registrarRecords[] = [
                'host' => $record['host'],
                'type' => $record['record_type'],
                'value' => $record['value'],
                'priority' => $record['priority'],
                'ttl' => $record['ttl'],
            ];
        }

        $registrar = self::registrarForRequest($request);
        $result = $registrar->setDnsRecords((string) $request['requested_domain'], $registrarRecords);
        $synced = (bool) ($result['updated'] ?? false);

        Database::connection()->beginTransaction();
        try {
            self::markDnsRecords($requestId, $synced ? 'synced' : 'pending');
            self::updateRequestFields($requestId, [
                'domain_status' => $synced ? 'pending_verification' : 'pending_dns',
                'dns_status' => $synced ? 'pending_verification' : 'pending',
                'registrar_response_json' => self::jsonForStorage($result),
                'next_action' => $synced ? 'DNS records are syncing. Verification is next.' : 'DNS records still need attention.',
                'last_error' => $synced ? null : 'DNS records were not confirmed by the registrar.',
                'last_checked_at' => self::now(),
            ]);
            self::logDomainEvent($request, $adminUserId, 'registrar_dns_updated', $synced ? 'success' : 'error', $synced ? 'DNS records sent to registrar.' : 'DNS update did not complete.', $registrarRecords, $result);
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function verifyDns(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        $registrar = self::registrarForRequest($request);
        $result = $registrar->getDnsRecords((string) $request['requested_domain']);
        $verified = self::recordsMatch($requestId, $result['records'] ?? []);

        Database::connection()->beginTransaction();
        try {
            self::markDnsRecords($requestId, $verified ? 'verified' : 'pending');
            self::updateRequestFields($requestId, [
                'domain_status' => $verified ? 'ssl_pending' : 'pending_verification',
                'dns_status' => $verified ? 'verified' : 'pending_verification',
                'dns_verified_at' => $verified ? self::now() : null,
                'next_action' => $verified ? 'DNS is verified. SSL issuance is next.' : 'DNS is still updating. Check again after propagation.',
                'last_error' => $verified ? null : 'DNS records do not match the expected launch records yet.',
                'last_checked_at' => self::now(),
                'registrar_response_json' => self::jsonForStorage($result),
            ]);
            self::logDomainEvent($request, $adminUserId, 'registrar_dns_verified', $verified ? 'success' : 'pending', $verified ? 'DNS records verified.' : 'DNS records are not verified yet.', null, $result);
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function refreshRegistrarStatus(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        $registrar = self::registrarForRequest($request);
        $result = $registrar->getStatus((string) $request['requested_domain']);

        self::updateRequestFields($requestId, [
            'registrar_response_json' => self::jsonForStorage($result),
            'last_checked_at' => self::now(),
            'last_error' => null,
            'expiration_date' => self::dateFromRegistrar($result['expires_on'] ?? null) ?: self::nullableDate($request['expiration_date'] ?? null),
        ]);
        self::logDomainEvent($request, $adminUserId, 'registrar_status_refreshed', 'success', 'Registrar status refreshed.', null, $result);
    }

    public static function updateSslStatus(int $requestId, int $adminUserId, string $sslStatus): void
    {
        $request = self::requireRequest($requestId);
        $sslStatus = self::normalizeSslStatus($sslStatus);
        $domainStatus = in_array($sslStatus, ['issued', 'renewed'], true) && (string) ($request['dns_status'] ?? '') === 'verified'
            ? 'ready'
            : ((string) ($request['domain_status'] ?? 'ssl_pending'));

        Database::connection()->beginTransaction();
        try {
            self::updateRequestFields($requestId, [
                'domain_status' => $domainStatus,
                'ssl_status' => $sslStatus,
                'ssl_updated_at' => self::now(),
                'next_action' => $domainStatus === 'ready' ? 'Domain is ready for website launch.' : 'SSL status updated.',
                'last_error' => $sslStatus === 'failed' ? 'SSL provisioning needs attention.' : null,
            ]);
            $updatedRequest = self::request($requestId) ?: $request;
            if ($domainStatus === 'ready') {
                $assignment = self::upsertAssignment($updatedRequest, 'ready');
                self::upsertWebsiteDomain((int) $request['business_id'], (int) $assignment['id'], (string) $assignment['domain_name'], 'ready');
            }
            self::logDomainEvent($updatedRequest, $adminUserId, 'ssl_status_updated', 'recorded', 'SSL status updated to ' . $sslStatus . '.');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function markLive(int $requestId, int $adminUserId): void
    {
        $request = self::requireRequest($requestId);
        if (!in_array((string) ($request['domain_status'] ?? ''), ['ready', 'live'], true)) {
            throw new InvalidArgumentException('Domain must be ready before it can be marked live.');
        }

        Database::connection()->beginTransaction();
        try {
            self::updateRequestFields($requestId, [
                'domain_status' => 'live',
                'next_action' => 'Domain is live.',
                'last_error' => null,
            ]);
            $updatedRequest = self::request($requestId) ?: $request;
            $assignment = self::upsertAssignment($updatedRequest, 'live');
            self::upsertWebsiteDomain((int) $request['business_id'], (int) $assignment['id'], (string) $assignment['domain_name'], 'live');
            self::setWebsitePublishStatus((int) $request['business_id'], 'published');
            self::logDomainEvent($updatedRequest, $adminUserId, 'domain_marked_live', 'success', 'Domain marked live.');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function statusLabel(?string $status): string
    {
        $status = (string) $status;
        if ($status === '') {
            return 'Not Requested';
        }

        $labels = [
            'requested' => 'Requested',
            'awaiting_customer' => 'Awaiting Customer',
            'pending_purchase' => 'Pending Purchase',
            'pending_dns' => 'Pending DNS',
            'pending_verification' => 'Pending Verification',
            'ssl_pending' => 'SSL Pending',
            'ready' => 'Ready',
            'live' => 'Live',
            'error' => 'Error',
            'active' => 'Ready',
            'transferred' => 'Ready',
        ];

        return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public static function nextActionForDomain(array $domain): string
    {
        $nextAction = trim((string) ($domain['next_action'] ?? ''));
        if ($nextAction !== '') {
            return $nextAction;
        }

        switch ((string) ($domain['domain_status'] ?? '')) {
            case 'awaiting_customer':
                return 'Update your domain DNS records so we can verify the connection.';
            case 'pending_purchase':
                return 'Your domain is ready for admin purchase.';
            case 'pending_dns':
                return 'DNS records are being prepared.';
            case 'pending_verification':
                return 'DNS is being verified. This can take time after records are updated.';
            case 'ssl_pending':
                return 'SSL is being prepared after DNS verification.';
            case 'ready':
                return 'Your domain is ready for launch.';
            case 'live':
                return 'Your website is live on this domain.';
            case 'error':
                return 'Domain setup needs support review.';
            default:
                return 'Domain setup has started.';
        }
    }

    public static function timingForDomain(array $domain): string
    {
        switch ((string) ($domain['domain_status'] ?? '')) {
            case 'pending_purchase':
                return 'Domain purchase is usually completed during admin review.';
            case 'pending_dns':
            case 'pending_verification':
                return 'DNS changes often verify within a few hours, but can take up to 24-48 hours.';
            case 'ssl_pending':
                return 'SSL is usually issued after DNS verifies.';
            case 'ready':
            case 'live':
                return 'No additional domain timing is expected right now.';
            default:
                return 'Timing appears here as each step moves forward.';
        }
    }

    public static function launchReady(array $domain): bool
    {
        return in_array((string) ($domain['domain_status'] ?? ''), ['ready', 'live', 'active', 'transferred'], true)
            && in_array((string) ($domain['dns_status'] ?? ''), ['verified', ''], true)
            && in_array((string) ($domain['ssl_status'] ?? ''), ['issued', 'renewed', ''], true);
    }

    private static function registrarForRequest(array $request): RegistrarInterface
    {
        $registrar = self::registrarKey($request);
        if ($registrar === 'namecheap') {
            return new NamecheapRegistrar();
        }

        throw new RuntimeException('Configured domain registrar is not supported yet.');
    }

    private static function registrarKey(array $request): string
    {
        $registrar = strtolower(trim((string) ($request['registrar'] ?? '')));
        if ($registrar === '') {
            $registrar = strtolower(trim((string) Database::config('DOMAIN_DEFAULT_REGISTRAR', 'namecheap')));
        }

        return $registrar ?: 'namecheap';
    }

    private static function requireRequest(int $requestId): array
    {
        $request = self::request($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Domain request not found.');
        }

        return $request;
    }

    private static function request(int $requestId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM domain_requests
             WHERE id = :request_id
             LIMIT 1'
        );
        $statement->execute(['request_id' => $requestId]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    private static function requestForBusinessDomain(int $businessId, string $domainName): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM domain_requests
             WHERE business_id = :business_id
               AND requested_domain = :requested_domain
             LIMIT 1'
        );
        $statement->execute([
            'business_id' => $businessId,
            'requested_domain' => $domainName,
        ]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    private static function latestOnboardingDomain(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT selection_type, domain_name
             FROM `247sp_domain_selections`
             WHERE business_id = :business_id
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $row = $statement->fetch() ?: [];

        return [
            'selection_type' => self::normalizeRequestType($row['selection_type'] ?? 'purchase'),
            'domain_name' => self::normalizeDomain((string) ($row['domain_name'] ?? '')),
        ];
    }

    private static function businessWithOwner(int $businessId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.*, u.first_name, u.last_name, u.email AS owner_email, u.phone AS owner_phone
             FROM businesses b
             LEFT JOIN users u ON u.id = b.owner_user_id
             WHERE b.id = :business_id
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $business = $statement->fetch();

        return $business ?: null;
    }

    private static function registrarContactFromBusiness(array $business): array
    {
        $firstName = trim((string) ($business['first_name'] ?? ''));
        $lastName = trim((string) ($business['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            $parts = preg_split('/\s+/', trim((string) ($business['business_name'] ?? 'Business Owner'))) ?: [];
            $firstName = $parts[0] ?? 'Business';
            $lastName = $parts[count($parts) - 1] ?? 'Owner';
        }

        $phone = self::namecheapPhone((string) ($business['phone'] ?: ($business['owner_phone'] ?? '')));
        if ($phone === '') {
            throw new InvalidArgumentException('Business phone number is required before registrar purchase.');
        }

        $email = trim((string) ($business['email'] ?: ($business['owner_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Business email is required before registrar purchase.');
        }

        foreach (['address_line_1', 'city', 'state', 'postal_code'] as $field) {
            if (trim((string) ($business[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Business address must be complete before registrar purchase.');
            }
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName ?: 'Owner',
            'organization' => (string) ($business['legal_name'] ?: $business['business_name']),
            'address_line_1' => (string) $business['address_line_1'],
            'address_line_2' => (string) ($business['address_line_2'] ?? ''),
            'city' => (string) $business['city'],
            'state' => (string) $business['state'],
            'postal_code' => (string) $business['postal_code'],
            'country_code' => self::countryCode((string) ($business['country'] ?? 'US')),
            'phone' => $phone,
            'email' => $email,
        ];
    }

    private static function desiredDnsRecords(array $request): array
    {
        $records = [];
        $ipv4 = trim((string) Database::config('DOMAIN_TARGET_IPV4', ''));
        if ($ipv4 !== '') {
            $records[] = ['type' => 'A', 'host' => '@', 'value' => $ipv4, 'priority' => null, 'ttl' => 1800, 'status' => 'planned'];
        }

        $ipv6 = trim((string) Database::config('DOMAIN_TARGET_IPV6', ''));
        if ($ipv6 !== '') {
            $records[] = ['type' => 'AAAA', 'host' => '@', 'value' => $ipv6, 'priority' => null, 'ttl' => 1800, 'status' => 'planned'];
        }

        $www = trim((string) Database::config('DOMAIN_WWW_CNAME', ''));
        if ($www === '') {
            $www = (string) $request['requested_domain'];
        }
        $records[] = ['type' => 'CNAME', 'host' => 'www', 'value' => $www, 'priority' => null, 'ttl' => 1800, 'status' => 'planned'];

        $txtName = trim((string) Database::config('DOMAIN_TXT_VERIFICATION_NAME', ''));
        $txtValue = trim((string) Database::config('DOMAIN_TXT_VERIFICATION_VALUE', ''));
        if ($txtName !== '' && $txtValue !== '') {
            $records[] = ['type' => 'TXT', 'host' => $txtName, 'value' => $txtValue, 'priority' => null, 'ttl' => 1800, 'status' => 'planned'];
        }

        $mx = trim((string) Database::config('DOMAIN_MAIL_MX_HOST', ''));
        if ($mx !== '') {
            $records[] = ['type' => 'MX', 'host' => '@', 'value' => $mx, 'priority' => 10, 'ttl' => 1800, 'status' => 'planned'];
        }

        return $records;
    }

    private static function syncPlannedDnsRecords(array $request): void
    {
        foreach (self::desiredDnsRecords($request) as $record) {
            self::upsertDnsRecord($request, $record);
        }
    }

    private static function upsertDnsRecord(array $request, array $record): void
    {
        $assignment = self::assignmentForBusiness((int) $request['business_id']);
        $statement = Database::connection()->prepare(
            'INSERT INTO domain_dns_records (
                business_id, domain_request_id, domain_assignment_id, domain_name,
                record_type, host, value, priority, ttl, provider, status, created_at, updated_at
             ) VALUES (
                :business_id, :domain_request_id, :domain_assignment_id, :domain_name,
                :record_type, :host, :value, :priority, :ttl, :provider, :status, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                domain_request_id = VALUES(domain_request_id),
                domain_assignment_id = VALUES(domain_assignment_id),
                priority = VALUES(priority),
                ttl = VALUES(ttl),
                provider = VALUES(provider),
                status = IF(status = "verified", status, VALUES(status)),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => (int) $request['business_id'],
            'domain_request_id' => (int) $request['id'],
            'domain_assignment_id' => $assignment ? (int) $assignment['id'] : null,
            'domain_name' => (string) $request['requested_domain'],
            'record_type' => (string) $record['type'],
            'host' => (string) $record['host'],
            'value' => (string) $record['value'],
            'priority' => $record['priority'],
            'ttl' => (int) $record['ttl'],
            'provider' => self::registrarKey($request),
            'status' => (string) $record['status'],
        ]);
    }

    private static function markDnsRecords(int $requestId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE domain_dns_records
             SET status = :status,
                 last_synced_at = NOW(),
                 updated_at = NOW()
             WHERE domain_request_id = :request_id'
        );
        $statement->execute([
            'status' => $status,
            'request_id' => $requestId,
        ]);
    }

    private static function recordsMatch(int $requestId, array $registrarRecords): bool
    {
        $expected = self::dnsRecordsForRequest($requestId);
        $remote = [];
        foreach ($registrarRecords as $record) {
            $key = strtoupper((string) ($record['type'] ?? '')) . '|' . strtolower((string) ($record['host'] ?? '')) . '|' . trim((string) ($record['value'] ?? ''));
            $remote[$key] = true;
        }

        foreach ($expected as $record) {
            if ((string) $record['value'] === '') {
                continue;
            }
            $key = strtoupper((string) $record['record_type']) . '|' . strtolower((string) $record['host']) . '|' . trim((string) $record['value']);
            if (!isset($remote[$key])) {
                return false;
            }
        }

        return count($expected) > 0;
    }

    private static function updateRequestFields(int $requestId, array $fields): void
    {
        $allowed = [
            'request_type',
            'domain_status',
            'registrar',
            'registrar_domain_id',
            'registrar_order_id',
            'registrar_transaction_id',
            'registrar_response_json',
            'annual_cost',
            'purchase_date',
            'expiration_date',
            'dns_status',
            'dns_verified_at',
            'ssl_status',
            'ssl_updated_at',
            'next_action',
            'last_error',
            'last_checked_at',
        ];

        $sets = [];
        $params = ['request_id' => $requestId];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            $sets[] = $field . ' = :' . $field;
            $params[$field] = $fields[$field];
        }

        if (count($sets) === 0) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $statement = Database::connection()->prepare(
            'UPDATE domain_requests
             SET ' . implode(', ', $sets) . '
             WHERE id = :request_id'
        );
        $statement->execute($params);
    }

    private static function upsertAssignment(array $request, string $status): array
    {
        $assignmentStatus = in_array($status, ['ready', 'live', 'active', 'transferred'], true) ? $status : 'active';
        $ownershipType = (string) ($request['request_type'] ?? 'purchase') === 'existing' ? 'customer_owned' : 'fdv_owned';
        $statement = Database::connection()->prepare(
            'INSERT INTO domain_assignments (
                business_id, domain_request_id, domain_name, registrar, registrar_domain_id,
                ownership_type, auto_renew, expiration_date, ssl_status, status, assigned_at, created_at, updated_at
             ) VALUES (
                :business_id, :domain_request_id, :domain_name, :registrar, :registrar_domain_id,
                :ownership_type, :auto_renew, :expiration_date, :ssl_status, :status, NOW(), NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                domain_request_id = VALUES(domain_request_id),
                domain_name = VALUES(domain_name),
                registrar = VALUES(registrar),
                registrar_domain_id = VALUES(registrar_domain_id),
                ownership_type = VALUES(ownership_type),
                auto_renew = VALUES(auto_renew),
                expiration_date = VALUES(expiration_date),
                ssl_status = VALUES(ssl_status),
                status = VALUES(status),
                assigned_at = COALESCE(assigned_at, VALUES(assigned_at)),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => (int) $request['business_id'],
            'domain_request_id' => (int) $request['id'],
            'domain_name' => (string) $request['requested_domain'],
            'registrar' => self::registrarKey($request),
            'registrar_domain_id' => self::nullableText($request['registrar_domain_id'] ?? null, 100),
            'ownership_type' => $ownershipType,
            'auto_renew' => $ownershipType === 'fdv_owned' ? 1 : 0,
            'expiration_date' => self::nullableDate($request['expiration_date'] ?? null),
            'ssl_status' => self::normalizeSslStatus($request['ssl_status'] ?? 'pending'),
            'status' => $assignmentStatus,
        ]);

        return self::assignmentForBusiness((int) $request['business_id']) ?: [];
    }

    private static function assignmentForBusiness(int $businessId): ?array
    {
        $assignment = Database::connection()->prepare(
            'SELECT *
             FROM domain_assignments
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $assignment->execute(['business_id' => $businessId]);
        $row = $assignment->fetch();

        return $row ?: null;
    }

    private static function updateAssignmentStatus(int $businessId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE domain_assignments
             SET status = :status,
                 updated_at = NOW()
             WHERE business_id = :business_id'
        );
        $statement->execute([
            'status' => $status,
            'business_id' => $businessId,
        ]);
    }

    private static function upsertWebsiteDomain(int $businessId, int $assignmentId, string $domainName, string $domainStatus): void
    {
        $website = Database::connection()->prepare(
            'SELECT id
             FROM `247sp_generated_websites`
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $website->execute(['business_id' => $businessId]);
        $websiteId = (int) ($website->fetchColumn() ?: 0);

        if ($websiteId <= 0) {
            return;
        }

        $publishStatus = $domainStatus === 'live' ? 'published' : 'ready';
        $statement = Database::connection()->prepare(
            'INSERT INTO website_domains (website_id, business_id, domain_assignment_id, domain_name, publish_status, created_at, updated_at)
             VALUES (:website_id, :business_id, :domain_assignment_id, :domain_name, :publish_status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                domain_assignment_id = VALUES(domain_assignment_id),
                domain_name = VALUES(domain_name),
                publish_status = VALUES(publish_status),
                updated_at = NOW()'
        );
        $statement->execute([
            'website_id' => $websiteId,
            'business_id' => $businessId,
            'domain_assignment_id' => $assignmentId,
            'domain_name' => $domainName,
            'publish_status' => $publishStatus,
        ]);
    }

    private static function setWebsitePublishStatus(int $businessId, string $publishStatus): void
    {
        if (!in_array($publishStatus, self::PUBLISH_STATUSES, true)) {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE website_domains
             SET publish_status = :publish_status,
                 updated_at = NOW()
             WHERE business_id = :business_id'
        );
        $statement->execute([
            'publish_status' => $publishStatus,
            'business_id' => $businessId,
        ]);
    }

    private static function isAssignable(string $domainStatus, string $dnsStatus, string $sslStatus): bool
    {
        return in_array($domainStatus, ['pending_dns', 'pending_verification', 'ssl_pending', 'ready', 'live', 'active', 'transferred'], true)
            || $dnsStatus === 'verified'
            || in_array($sslStatus, ['issued', 'renewed'], true);
    }

    private static function progressForDomain(array $domain): array
    {
        return [
            ['label' => 'Domain selected', 'complete' => trim((string) ($domain['requested_domain'] ?? '')) !== ''],
            ['label' => 'Purchased or connected', 'complete' => in_array((string) ($domain['domain_status'] ?? ''), ['pending_dns', 'pending_verification', 'ssl_pending', 'ready', 'live', 'active', 'transferred'], true)],
            ['label' => 'DNS verified', 'complete' => (string) ($domain['dns_status'] ?? '') === 'verified'],
            ['label' => 'SSL issued', 'complete' => in_array((string) ($domain['ssl_status'] ?? ''), ['issued', 'renewed'], true)],
            ['label' => 'Live', 'complete' => (string) ($domain['domain_status'] ?? '') === 'live' || (string) ($domain['publish_status'] ?? '') === 'published'],
        ];
    }

    private static function markError(array $request, int $userId, string $eventType, string $message, array $response = []): void
    {
        self::updateRequestFields((int) $request['id'], [
            'domain_status' => 'error',
            'last_error' => $message,
            'next_action' => 'Support is reviewing the domain setup.',
            'registrar_response_json' => self::jsonForStorage($response),
            'last_checked_at' => self::now(),
        ]);
        self::logDomainEvent($request, $userId, $eventType, 'error', $message, null, $response);
    }

    private static function logDomainEvent(array $request, ?int $userId, string $eventType, string $status, string $message, ?array $requestPayload = null, ?array $responsePayload = null): void
    {
        $assignment = self::assignmentForBusiness((int) $request['business_id']);
        $statement = Database::connection()->prepare(
            'INSERT INTO domain_events (
                business_id, domain_request_id, domain_assignment_id, user_id, registrar,
                event_type, status, message, request_json, response_json, created_at
             ) VALUES (
                :business_id, :domain_request_id, :domain_assignment_id, :user_id, :registrar,
                :event_type, :status, :message, :request_json, :response_json, NOW()
             )'
        );
        $statement->execute([
            'business_id' => (int) $request['business_id'],
            'domain_request_id' => (int) $request['id'],
            'domain_assignment_id' => $assignment ? (int) $assignment['id'] : null,
            'user_id' => $userId ?: null,
            'registrar' => self::registrarKey($request),
            'event_type' => $eventType,
            'status' => $status,
            'message' => $message,
            'request_json' => $requestPayload !== null ? self::jsonForStorage($requestPayload) : null,
            'response_json' => $responsePayload !== null ? self::jsonForStorage($responsePayload) : null,
        ]);
    }

    private static function logActivity(int $businessId, int $userId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, module_key, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :module_key, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'module_key' => '247sp',
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }

    private static function nullableText($value, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, $maxLength);
    }

    private static function nullableMoney($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Annual cost must be a positive number.');
        }

        return round((float) $value, 2);
    }

    private static function nullableDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
        }

        return $value;
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string) $domain);

        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return '';
        }

        return $domain;
    }

    private static function normalizeRequestType($value): string
    {
        $value = (string) $value;
        return in_array($value, ['existing', 'purchase'], true) ? $value : 'purchase';
    }

    private static function normalizeDnsStatus($value): string
    {
        $value = (string) $value;
        return in_array($value, self::DNS_STATUSES, true) ? $value : 'not_started';
    }

    private static function normalizeSslStatus($value): string
    {
        $value = (string) $value;
        return in_array($value, self::SSL_STATUSES, true) ? $value : 'pending';
    }

    private static function namecheapPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10) {
            return '+1.' . $digits;
        }

        if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
            return '+1.' . substr($digits, 1);
        }

        return '+' . substr($digits, 0, 3) . '.' . substr($digits, 3);
    }

    private static function countryCode(string $country): string
    {
        $country = strtoupper(trim($country));
        if ($country === '' || $country === 'USA' || $country === 'UNITED STATES') {
            return 'US';
        }

        return substr($country, 0, 2);
    }

    private static function dateOneYearFromNow(): string
    {
        return gmdate('Y-m-d', strtotime('+1 year'));
    }

    private static function dateFromRegistrar($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private static function jsonForStorage(array $data): string
    {
        return json_encode(self::jsonSafe($data), JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function jsonSafe($value)
    {
        if ($value instanceof SimpleXMLElement) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $item) {
            $safe[$key] = self::jsonSafe($item);
        }

        return $safe;
    }
}
