<?php

require_once __DIR__ . '/Database.php';

final class EmailProvisioningFoundation
{
    public const STATUSES = [
        'requested',
        'pending_setup',
        'active',
        'suspended',
        'cancelled',
    ];

    public const MAILBOX_TYPES = [
        'included',
        'additional',
        'admin',
    ];

    public static function ensureRequestForBusiness(int $businessId, string $requestedEmail = '', string $displayName = ''): ?array
    {
        $requestedEmail = self::normalizeEmail($requestedEmail);

        if ($requestedEmail === '') {
            $requestedEmail = self::latestOnboardingEmail($businessId);
        }

        if ($requestedEmail === '') {
            return null;
        }

        $displayName = self::nullableText($displayName, 150);
        if ($displayName === null) {
            $displayName = self::displayNameFromEmail($requestedEmail);
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO mailbox_requests (business_id, requested_email, display_name, status, created_at, updated_at)
             VALUES (:business_id, :requested_email, :display_name, :status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                display_name = COALESCE(VALUES(display_name), display_name),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'requested_email' => $requestedEmail,
            'display_name' => $displayName,
            'status' => 'requested',
        ]);

        self::ensureMailboxCounts($businessId);

        return self::requestForBusinessEmail($businessId, $requestedEmail);
    }

    public static function customerBusinessesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id,
                    b.business_name,
                    COALESCE(
                        (
                            SELECT da.domain_name
                            FROM domain_assignments da
                            WHERE da.business_id = b.id
                            ORDER BY da.assigned_at DESC, da.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT dr.requested_domain
                            FROM domain_requests dr
                            WHERE dr.business_id = b.id
                            ORDER BY dr.created_at DESC, dr.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT ds.domain_name
                            FROM `247sp_domain_selections` ds
                            WHERE ds.business_id = b.id
                            ORDER BY ds.updated_at DESC, ds.id DESC
                            LIMIT 1
                        )
                    ) AS domain_name,
                    COALESCE(bmc.included_mailbox_count, 1) AS included_mailbox_count,
                    COALESCE(bmc.additional_mailbox_count, 0) AS additional_mailbox_count,
                    (
                        SELECT COUNT(*)
                        FROM mailbox_requests mr
                        WHERE mr.business_id = b.id
                          AND mr.status = 'requested'
                    ) AS requested_mailbox_count,
                    (
                        SELECT COUNT(*)
                        FROM mailbox_requests mr
                        WHERE mr.business_id = b.id
                          AND mr.status = 'pending_setup'
                    ) AS pending_setup_mailbox_count,
                    (
                        SELECT COUNT(*)
                        FROM mailbox_assignments ma
                        WHERE ma.business_id = b.id
                          AND ma.status = 'active'
                    ) AS active_mailbox_count,
                    (
                        SELECT COUNT(*)
                        FROM mailbox_requests mr
                        WHERE mr.business_id = b.id
                          AND mr.status = 'suspended'
                    ) AS suspended_mailbox_count,
                    (
                        SELECT COUNT(*)
                        FROM mailbox_requests mr
                        WHERE mr.business_id = b.id
                          AND mr.status = 'cancelled'
                    ) AS cancelled_mailbox_count
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             INNER JOIN business_modules bm ON bm.business_id = b.id AND bm.status = :module_status
             INNER JOIN modules m ON m.id = bm.module_id AND m.module_key = :module_key
             LEFT JOIN business_mailbox_counts bmc ON bmc.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.business_name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'module_status' => 'active',
            'module_key' => '247sp',
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function customerMailboxRequestsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT mr.*,
                    b.business_name
             FROM mailbox_requests mr
             INNER JOIN businesses b ON b.id = mr.business_id
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY mr.created_at DESC, mr.id DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function customerMailboxAssignmentsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ma.*,
                    b.business_name
             FROM mailbox_assignments ma
             INNER JOIN businesses b ON b.id = ma.business_id
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
               AND ma.status = :assignment_status
             ORDER BY ma.created_at DESC, ma.id DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
            'assignment_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function createCustomerRequest(int $userId, int $businessId, string $mailboxName, string $displayName): void
    {
        $business = self::customerBusiness($userId, $businessId);
        if ($business === null) {
            throw new InvalidArgumentException('That business could not be found for this account.');
        }

        $domainName = self::normalizeDomain((string) ($business['domain_name'] ?? ''));
        if ($domainName === '') {
            throw new InvalidArgumentException('Choose a domain before requesting a mailbox.');
        }

        $mailboxName = self::normalizeMailboxName($mailboxName);
        if ($mailboxName === '') {
            throw new InvalidArgumentException('Enter a valid mailbox name, such as info, support, or office.');
        }

        $requestedEmail = $mailboxName . '@' . $domainName;
        self::ensureRequestForBusiness($businessId, $requestedEmail, $displayName);
        self::logActivity($businessId, $userId, 'mailbox_requested', 'Mailbox requested: ' . $requestedEmail);
    }

    public static function adminMetrics(): array
    {
        $statement = Database::connection()->query(
            "SELECT
                SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) AS requested_count,
                SUM(CASE WHEN status = 'pending_setup' THEN 1 ELSE 0 END) AS pending_setup_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
             FROM mailbox_requests"
        );
        $metrics = $statement->fetch() ?: [];

        return [
            'requested_count' => (int) ($metrics['requested_count'] ?? 0),
            'pending_setup_count' => (int) ($metrics['pending_setup_count'] ?? 0),
            'active_count' => (int) ($metrics['active_count'] ?? 0),
            'suspended_count' => (int) ($metrics['suspended_count'] ?? 0),
            'cancelled_count' => (int) ($metrics['cancelled_count'] ?? 0),
        ];
    }

    public static function adminMailboxRequests(): array
    {
        return Database::connection()->query(
            'SELECT mr.*,
                    b.business_name,
                    ma.id AS assignment_id,
                    ma.status AS assignment_status,
                    ma.mailbox_type
             FROM mailbox_requests mr
             INNER JOIN businesses b ON b.id = mr.business_id
             LEFT JOIN mailbox_assignments ma ON ma.mailbox_request_id = mr.id
             ORDER BY mr.created_at DESC, mr.id DESC'
        )->fetchAll();
    }

    public static function adminMailboxActivity(int $limit = 25): array
    {
        $statement = Database::connection()->prepare(
            'SELECT mal.*,
                    mr.requested_email,
                    ma.email_address,
                    b.business_name
             FROM mailbox_activity_log mal
             LEFT JOIN mailbox_requests mr ON mr.id = mal.mailbox_request_id
             LEFT JOIN mailbox_assignments ma ON ma.id = mal.mailbox_assignment_id
             LEFT JOIN businesses b ON b.id = COALESCE(mr.business_id, ma.business_id)
             ORDER BY mal.created_at DESC, mal.id DESC
             LIMIT :limit_value'
        );
        $statement->bindValue('limit_value', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function updateMailboxRequest(int $requestId, int $adminUserId, string $status, string $mailboxType, string $notes): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported mailbox status.');
        }

        if (!in_array($mailboxType, self::MAILBOX_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported mailbox type.');
        }

        $request = self::request($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Mailbox request not found.');
        }

        Database::connection()->beginTransaction();

        try {
            if ($mailboxType === 'included' && self::businessHasIncludedAssignment((int) $request['business_id'], $requestId)) {
                $mailboxType = 'additional';
            }

            $statement = Database::connection()->prepare(
                'UPDATE mailbox_requests
                 SET status = :status,
                     updated_at = NOW()
                 WHERE id = :request_id'
            );
            $statement->execute([
                'status' => $status,
                'request_id' => $requestId,
            ]);

            $assignment = null;
            if (in_array($status, ['pending_setup', 'active'], true)) {
                $assignment = self::upsertAssignment($request, $status, $mailboxType);
            } elseif (in_array($status, ['suspended', 'cancelled'], true)) {
                $assignment = self::updateAssignmentStatus((int) $request['business_id'], (string) $request['requested_email'], $status);
            }

            $activityType = self::activityTypeForStatus($status);
            self::logMailboxActivity($assignment ? (int) $assignment['id'] : null, $requestId, $activityType, $notes);
            self::recalculateMailboxCounts((int) $request['business_id']);
            self::logActivity((int) $request['business_id'], $adminUserId, 'mailbox_request_updated', 'Mailbox request updated to ' . $status);

            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function currentEmailStatusForBusiness(int $businessId): string
    {
        self::ensureRequestForBusiness($businessId);

        $statement = Database::connection()->prepare(
            'SELECT status
             FROM mailbox_requests
             WHERE business_id = :business_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);

        return (string) ($statement->fetchColumn() ?: 'not_selected');
    }

    public static function statusLabel(?string $status): string
    {
        $status = (string) $status;

        if ($status === '') {
            return 'Not Requested';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    private static function customerBusiness(int $userId, int $businessId): ?array
    {
        foreach (self::customerBusinessesForUser($userId) as $business) {
            if ((int) $business['id'] === $businessId) {
                return $business;
            }
        }

        return null;
    }

    private static function latestOnboardingEmail(int $businessId): string
    {
        $statement = Database::connection()->prepare(
            'SELECT er.primary_mailbox_name,
                    COALESCE(
                        (
                            SELECT da.domain_name
                            FROM domain_assignments da
                            WHERE da.business_id = er.business_id
                            ORDER BY da.assigned_at DESC, da.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT dr.requested_domain
                            FROM domain_requests dr
                            WHERE dr.business_id = er.business_id
                            ORDER BY dr.created_at DESC, dr.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT ds.domain_name
                            FROM `247sp_domain_selections` ds
                            WHERE ds.business_id = er.business_id
                            ORDER BY ds.updated_at DESC, ds.id DESC
                            LIMIT 1
                        )
                    ) AS domain_name
             FROM `247sp_email_requests` er
             WHERE er.business_id = :business_id
             ORDER BY er.updated_at DESC, er.id DESC
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $row = $statement->fetch();

        if (!$row) {
            return '';
        }

        $mailboxName = self::normalizeMailboxName((string) $row['primary_mailbox_name']);
        $domainName = self::normalizeDomain((string) $row['domain_name']);

        if ($mailboxName === '' || $domainName === '') {
            return '';
        }

        return $mailboxName . '@' . $domainName;
    }

    private static function request(int $requestId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM mailbox_requests
             WHERE id = :request_id
             LIMIT 1'
        );
        $statement->execute(['request_id' => $requestId]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    private static function requestForBusinessEmail(int $businessId, string $requestedEmail): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM mailbox_requests
             WHERE business_id = :business_id
               AND requested_email = :requested_email
             LIMIT 1'
        );
        $statement->execute([
            'business_id' => $businessId,
            'requested_email' => $requestedEmail,
        ]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    private static function upsertAssignment(array $request, string $status, string $mailboxType): array
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO mailbox_assignments (business_id, mailbox_request_id, email_address, display_name, status, mailbox_type, created_at, updated_at)
             VALUES (:business_id, :mailbox_request_id, :email_address, :display_name, :status, :mailbox_type, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                mailbox_request_id = VALUES(mailbox_request_id),
                display_name = VALUES(display_name),
                status = VALUES(status),
                mailbox_type = VALUES(mailbox_type),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => (int) $request['business_id'],
            'mailbox_request_id' => (int) $request['id'],
            'email_address' => (string) $request['requested_email'],
            'display_name' => self::nullableText($request['display_name'] ?? null, 150),
            'status' => $status,
            'mailbox_type' => $mailboxType,
        ]);

        $assignment = Database::connection()->prepare(
            'SELECT *
             FROM mailbox_assignments
             WHERE business_id = :business_id
               AND email_address = :email_address
             LIMIT 1'
        );
        $assignment->execute([
            'business_id' => (int) $request['business_id'],
            'email_address' => (string) $request['requested_email'],
        ]);

        return $assignment->fetch() ?: [];
    }

    private static function businessHasIncludedAssignment(int $businessId, int $excludeRequestId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM mailbox_assignments
             WHERE business_id = :business_id
               AND mailbox_type = 'included'
               AND status <> 'cancelled'
               AND (mailbox_request_id IS NULL OR mailbox_request_id <> :request_id)"
        );
        $statement->execute([
            'business_id' => $businessId,
            'request_id' => $excludeRequestId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function updateAssignmentStatus(int $businessId, string $emailAddress, string $status): ?array
    {
        $statement = Database::connection()->prepare(
            'UPDATE mailbox_assignments
             SET status = :status,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND email_address = :email_address'
        );
        $statement->execute([
            'status' => $status,
            'business_id' => $businessId,
            'email_address' => $emailAddress,
        ]);

        $assignment = Database::connection()->prepare(
            'SELECT *
             FROM mailbox_assignments
             WHERE business_id = :business_id
               AND email_address = :email_address
             LIMIT 1'
        );
        $assignment->execute([
            'business_id' => $businessId,
            'email_address' => $emailAddress,
        ]);
        $row = $assignment->fetch();

        return $row ?: null;
    }

    private static function ensureMailboxCounts(int $businessId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO business_mailbox_counts (business_id, included_mailbox_count, additional_mailbox_count, created_at, updated_at)
             VALUES (:business_id, 1, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE business_id = business_id'
        );
        $statement->execute(['business_id' => $businessId]);
    }

    private static function recalculateMailboxCounts(int $businessId): void
    {
        self::ensureMailboxCounts($businessId);

        $statement = Database::connection()->prepare(
            "SELECT
                SUM(CASE WHEN mailbox_type = 'included' AND status <> 'cancelled' THEN 1 ELSE 0 END) AS included_count,
                SUM(CASE WHEN mailbox_type = 'additional' AND status <> 'cancelled' THEN 1 ELSE 0 END) AS additional_count
             FROM mailbox_assignments
             WHERE business_id = :business_id"
        );
        $statement->execute(['business_id' => $businessId]);
        $counts = $statement->fetch() ?: [];

        $includedCount = max(1, (int) ($counts['included_count'] ?? 0));
        $additionalCount = (int) ($counts['additional_count'] ?? 0);

        $update = Database::connection()->prepare(
            'UPDATE business_mailbox_counts
             SET included_mailbox_count = :included_count,
                 additional_mailbox_count = :additional_count,
                 updated_at = NOW()
             WHERE business_id = :business_id'
        );
        $update->execute([
            'included_count' => $includedCount,
            'additional_count' => $additionalCount,
            'business_id' => $businessId,
        ]);
    }

    private static function logMailboxActivity(?int $assignmentId, ?int $requestId, string $activityType, string $notes): void
    {
        $notes = trim($notes);
        $statement = Database::connection()->prepare(
            'INSERT INTO mailbox_activity_log (mailbox_assignment_id, mailbox_request_id, activity_type, notes, created_at)
             VALUES (:mailbox_assignment_id, :mailbox_request_id, :activity_type, :notes, NOW())'
        );
        $statement->execute([
            'mailbox_assignment_id' => $assignmentId,
            'mailbox_request_id' => $requestId,
            'activity_type' => $activityType,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }

    private static function activityTypeForStatus(string $status): string
    {
        $types = [
            'requested' => 'created',
            'pending_setup' => 'approved',
            'active' => 'activated',
            'suspended' => 'suspended',
            'cancelled' => 'cancelled',
        ];

        return $types[$status] ?? 'updated';
    }

    private static function normalizeMailboxName(string $mailboxName): string
    {
        $mailboxName = strtolower(trim($mailboxName));

        if ($mailboxName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $mailboxName)) {
            return '';
        }

        return $mailboxName;
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

    private static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }

    private static function displayNameFromEmail(string $email): string
    {
        $localPart = strtok($email, '@') ?: $email;

        return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    }

    private static function nullableText($value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, $maxLength);
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
}
