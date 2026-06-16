<?php

require_once __DIR__ . '/Database.php';

final class DomainAutomation
{
    public const STATUSES = [
        'requested',
        'pending_purchase',
        'active',
        'transferred',
        'expired',
        'cancelled',
    ];

    public const PUBLISH_STATUSES = [
        'draft',
        'ready',
        'published',
    ];

    public static function ensureRequestForBusiness(int $businessId, string $domainName = ''): ?array
    {
        $domainName = self::normalizeDomain($domainName);

        if ($domainName === '') {
            $domainName = self::latestOnboardingDomain($businessId);
        }

        if ($domainName === '') {
            return null;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO domain_requests (business_id, requested_domain, domain_status, created_at, updated_at)
             VALUES (:business_id, :requested_domain, :domain_status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE requested_domain = requested_domain'
        );
        $statement->execute([
            'business_id' => $businessId,
            'requested_domain' => $domainName,
            'domain_status' => 'requested',
        ]);

        return self::requestForBusinessDomain($businessId, $domainName);
    }

    public static function customerDomainsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT dr.*,
                    b.business_name,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
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

        return $statement->fetchAll();
    }

    public static function adminDomainRequests(): array
    {
        return Database::connection()->query(
            'SELECT dr.*,
                    b.business_name,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
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
    }

    public static function currentDomainForBusiness(int $businessId): ?array
    {
        self::ensureRequestForBusiness($businessId);

        $statement = Database::connection()->prepare(
            'SELECT dr.*,
                    da.domain_name AS assigned_domain,
                    da.status AS assignment_status,
                    da.assigned_at,
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

        return $domain ?: null;
    }

    public static function syncWebsiteDomainForBusiness(int $businessId): void
    {
        $statement = Database::connection()->prepare(
            "SELECT *
             FROM domain_assignments
             WHERE business_id = :business_id
               AND status IN ('active', 'transferred')
             ORDER BY assigned_at DESC, id DESC
             LIMIT 1"
        );
        $statement->execute(['business_id' => $businessId]);
        $assignment = $statement->fetch();

        if (!$assignment) {
            return;
        }

        self::upsertWebsiteDomain($businessId, (int) $assignment['id'], (string) $assignment['domain_name']);
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
            'registrar' => self::nullableText($input['registrar'] ?? null, 100),
            'annual_cost' => self::nullableMoney($input['annual_cost'] ?? null),
            'purchase_date' => self::nullableDate($input['purchase_date'] ?? null),
            'expiration_date' => self::nullableDate($input['expiration_date'] ?? null),
        ];

        Database::connection()->beginTransaction();

        try {
            $statement = Database::connection()->prepare(
                'UPDATE domain_requests
                 SET domain_status = :domain_status,
                     registrar = :registrar,
                     annual_cost = :annual_cost,
                     purchase_date = :purchase_date,
                     expiration_date = :expiration_date,
                     updated_at = NOW()
                 WHERE id = :request_id'
            );
            $statement->execute($metadata + [
                'domain_status' => $status,
                'request_id' => $requestId,
            ]);

            if (in_array($status, ['active', 'transferred'], true)) {
                $assignment = self::upsertAssignment($requestId, (int) $request['business_id'], (string) $request['requested_domain'], $status);
                self::upsertWebsiteDomain((int) $request['business_id'], (int) $assignment['id'], (string) $assignment['domain_name']);
            } elseif (in_array($status, ['expired', 'cancelled'], true)) {
                self::updateAssignmentStatus((int) $request['business_id'], $status);
                self::setWebsitePublishStatus((int) $request['business_id'], 'draft');
            }

            self::logActivity((int) $request['business_id'], $adminUserId, 'domain_request_updated', 'Domain request updated to ' . $status);
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

        return ucwords(str_replace('_', ' ', $status));
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

    private static function latestOnboardingDomain(int $businessId): string
    {
        $statement = Database::connection()->prepare(
            'SELECT domain_name
             FROM `247sp_domain_selections`
             WHERE business_id = :business_id
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);

        return self::normalizeDomain((string) ($statement->fetchColumn() ?: ''));
    }

    private static function upsertAssignment(int $requestId, int $businessId, string $domainName, string $status): array
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO domain_assignments (business_id, domain_request_id, domain_name, status, assigned_at, created_at, updated_at)
             VALUES (:business_id, :domain_request_id, :domain_name, :status, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                domain_request_id = VALUES(domain_request_id),
                domain_name = VALUES(domain_name),
                status = VALUES(status),
                assigned_at = COALESCE(assigned_at, VALUES(assigned_at)),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'domain_request_id' => $requestId,
            'domain_name' => $domainName,
            'status' => $status,
        ]);

        $assignment = Database::connection()->prepare(
            'SELECT *
             FROM domain_assignments
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $assignment->execute(['business_id' => $businessId]);

        return $assignment->fetch() ?: [];
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

    private static function upsertWebsiteDomain(int $businessId, int $assignmentId, string $domainName): void
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
            'publish_status' => 'ready',
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

    private static function logActivity(int $businessId, int $adminUserId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, module_key, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :module_key, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $adminUserId,
            'module_key' => '247sp',
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }
}
