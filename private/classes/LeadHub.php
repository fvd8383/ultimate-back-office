<?php

require_once __DIR__ . '/Database.php';

final class LeadHub
{
    private const SOURCE_247SP_WEBSITE = '247sp_website';

    public static function capture247spWebsiteSubmission(array $input, array $server = []): array
    {
        $businessId = (int) ($input['business_id'] ?? 0);
        $websiteId = (int) ($input['website_id'] ?? 0);
        $pageId = (int) ($input['page_id'] ?? 0);

        if ($businessId <= 0 || $websiteId <= 0) {
            throw new InvalidArgumentException('This request could not be matched to a website.');
        }

        $name = self::cleanText($input['name'] ?? '', 150);
        $phone = self::cleanText($input['phone'] ?? '', 50);
        $email = strtolower(self::cleanText($input['email'] ?? '', 255));
        $message = self::cleanTextArea($input['message'] ?? '', 2000);
        $honeypot = trim((string) ($input['company_website'] ?? ''));

        if ($honeypot !== '' || self::looksAutomated($name, $email, $phone, $message)) {
            throw new InvalidArgumentException('This request could not be accepted. Please call or try again.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Please enter your name.');
        }

        if ($phone === '' && $email === '') {
            throw new InvalidArgumentException('Please enter a phone number or email address.');
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }

        $context = self::websiteSubmissionContext($businessId, $websiteId, $pageId);
        $sourcePage = self::cleanText((string) ($context['page_title'] ?: ($input['source_page'] ?? '')), 150);
        $sourceSlug = self::cleanText((string) ($context['page_slug'] ?: ($input['source_slug'] ?? '')), 150);
        $service = self::cleanText((string) ($context['service_name'] ?: ($input['service_name'] ?? '')), 150);
        $statusId = self::newLeadStatusId($businessId);

        $metadata = [
            'source' => self::SOURCE_247SP_WEBSITE,
            'website_id' => $websiteId,
            'page_id' => $pageId > 0 ? $pageId : null,
            'source_page' => $sourcePage,
            'source_slug' => $sourceSlug,
            'service' => $service,
            'ip_address' => self::cleanText($server['REMOTE_ADDR'] ?? '', 45),
            'user_agent' => self::cleanText($server['HTTP_USER_AGENT'] ?? '', 500),
        ];

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $existingContact = self::matchingContact($businessId, $email, $phone);
            $nameParts = self::splitName($name);
            $sourceDetail = self::sourceDetail($sourcePage, $service);

            if ($existingContact === null) {
                $contactId = self::insertContact($businessId, $nameParts, $email, $phone, $statusId, $sourceDetail);
                $created = true;
            } else {
                $contactId = (int) $existingContact['id'];
                self::updateContact($contactId, $nameParts, $email, $phone, $statusId, $sourceDetail);
                $created = false;
            }

            if ($message !== '') {
                self::insertNote($businessId, $contactId, $message, $sourcePage, $service);
            }

            self::insertTask($businessId, $contactId, $name, $sourcePage, $service, $message);
            self::insertActivity($businessId, $contactId, $name, $email, $phone, $message, $metadata, $created);

            $connection->commit();

            return [
                'contact_id' => $contactId,
                'created' => $created,
            ];
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public static function recent247spWebsiteLeads(int $businessId, int $limit = 25): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    c.source_detail,
                    c.created_at,
                    c.updated_at,
                    cs.name AS status_name,
                    latest_activity.created_at AS submitted_at,
                    latest_activity.description AS latest_message
             FROM contacts c
             LEFT JOIN contact_statuses cs ON cs.id = c.status_id
             LEFT JOIN (
                 SELECT al.contact_id, al.created_at, al.description
                 FROM activity_logs al
                 INNER JOIN (
                     SELECT contact_id, MAX(id) AS max_id
                     FROM activity_logs
                     WHERE business_id = :activity_business_id
                       AND module_key = '247sp'
                       AND activity_type = '247sp_website_lead_submitted'
                       AND contact_id IS NOT NULL
                     GROUP BY contact_id
                 ) latest ON latest.max_id = al.id
             ) latest_activity ON latest_activity.contact_id = c.id
             WHERE c.business_id = :business_id
               AND c.source_module_key = :source
             ORDER BY COALESCE(latest_activity.created_at, c.updated_at, c.created_at) DESC, c.id DESC
             LIMIT :limit"
        );
        $statement->bindValue('activity_business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue('business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue('source', self::SOURCE_247SP_WEBSITE);
        $statement->bindValue('limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function contactsForBusiness(int $businessId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.*, cs.name AS status_name
             FROM contacts c
             LEFT JOIN contact_statuses cs ON cs.id = c.status_id
             WHERE c.business_id = :business_id
             ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min($limit, 200)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function contactDetail(int $businessId, int $contactId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.*, cs.name AS status_name
             FROM contacts c
             LEFT JOIN contact_statuses cs ON cs.id = c.status_id
             WHERE c.business_id = :business_id
               AND c.id = :contact_id
             LIMIT 1'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);
        $contact = $statement->fetch();

        if (!$contact) {
            return null;
        }

        return [
            'contact' => $contact,
            'notes' => self::notesForContact($businessId, $contactId),
            'tasks' => self::tasksForContact($businessId, $contactId),
            'activity' => self::activityForContact($businessId, $contactId),
        ];
    }

    public static function tasksForBusiness(int $businessId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.*, c.first_name, c.last_name
             FROM tasks t
             LEFT JOIN contacts c ON c.id = t.contact_id
             WHERE t.business_id = :business_id
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min($limit, 200)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function notesForBusiness(int $businessId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT n.*, c.first_name, c.last_name
             FROM notes n
             LEFT JOIN contacts c ON c.id = n.contact_id
             WHERE n.business_id = :business_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min($limit, 200)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private static function websiteSubmissionContext(int $businessId, int $websiteId, int $pageId): array
    {
        $sql = 'SELECT gw.id AS website_id,
                       gw.business_id,
                       gp.id AS page_id,
                       gp.title AS page_title,
                       gp.slug AS page_slug,
                       gp.page_type,
                       gp.content_json
                FROM `247sp_generated_websites` gw
                LEFT JOIN `247sp_generated_pages` gp ON gp.website_id = gw.id';
        $params = [
            'business_id' => $businessId,
            'website_id' => $websiteId,
        ];

        if ($pageId > 0) {
            $sql .= ' AND gp.id = :page_id';
            $params['page_id'] = $pageId;
        }

        $sql .= ' WHERE gw.id = :website_id
                    AND gw.business_id = :business_id
                  LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $context = $statement->fetch();

        if (!$context || ($pageId > 0 && (int) ($context['page_id'] ?? 0) <= 0)) {
            throw new InvalidArgumentException('This request could not be matched to a website page.');
        }

        $content = json_decode((string) ($context['content_json'] ?? ''), true);
        if (!is_array($content)) {
            $content = [];
        }

        $context['service_name'] = ($context['page_type'] ?? '') === 'service'
            ? (string) ($content['service_name'] ?? $context['page_title'] ?? '')
            : '';

        return $context;
    }

    private static function matchingContact(int $businessId, string $email, string $phone): ?array
    {
        $conditions = [];
        $params = ['business_id' => $businessId];

        if ($email !== '') {
            $conditions[] = 'email = :email';
            $params['email'] = $email;
        }

        if ($phone !== '') {
            $conditions[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        if (count($conditions) === 0) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT *
             FROM contacts
             WHERE business_id = :business_id
               AND (' . implode(' OR ', $conditions) . ')
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute($params);
        $contact = $statement->fetch();

        return $contact ?: null;
    }

    private static function insertContact(int $businessId, array $nameParts, string $email, string $phone, ?int $statusId, string $sourceDetail): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO contacts (
                business_id, first_name, last_name, email, phone, contact_type,
                status_id, source_module_key, source_detail, created_at, updated_at
             ) VALUES (
                :business_id, :first_name, :last_name, :email, :phone, :contact_type,
                :status_id, :source_module_key, :source_detail, NOW(), NOW()
             )'
        );
        $statement->execute([
            'business_id' => $businessId,
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'contact_type' => 'lead',
            'status_id' => $statusId,
            'source_module_key' => self::SOURCE_247SP_WEBSITE,
            'source_detail' => $sourceDetail,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private static function updateContact(int $contactId, array $nameParts, string $email, string $phone, ?int $statusId, string $sourceDetail): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE contacts
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = COALESCE(:email, email),
                 phone = COALESCE(:phone, phone),
                 contact_type = :contact_type,
                 status_id = :status_id,
                 source_module_key = :source_module_key,
                 source_detail = :source_detail,
                 updated_at = NOW()
             WHERE id = :contact_id'
        );
        $statement->execute([
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'contact_type' => 'lead',
            'status_id' => $statusId,
            'source_module_key' => self::SOURCE_247SP_WEBSITE,
            'source_detail' => $sourceDetail,
            'contact_id' => $contactId,
        ]);
    }

    private static function insertNote(int $businessId, int $contactId, string $message, string $sourcePage, string $service): void
    {
        $prefix = '247SP website submission';
        if ($sourcePage !== '') {
            $prefix .= ' from ' . $sourcePage;
        }
        if ($service !== '') {
            $prefix .= ' for ' . $service;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO notes (business_id, contact_id, created_by_user_id, note_body, created_at, updated_at)
             VALUES (:business_id, :contact_id, NULL, :note_body, NOW(), NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
            'note_body' => $prefix . "\n\n" . $message,
        ]);
    }

    private static function insertTask(int $businessId, int $contactId, string $name, string $sourcePage, string $service, string $message): void
    {
        $description = 'New 247SP website lead';
        if ($sourcePage !== '') {
            $description .= ' from ' . $sourcePage;
        }
        if ($service !== '') {
            $description .= ' for ' . $service;
        }
        if ($message !== '') {
            $description .= ".\n\nMessage: " . $message;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO tasks (
                business_id, contact_id, assigned_to_user_id, created_by_user_id,
                title, description, due_date, status, priority, created_at, updated_at
             ) VALUES (
                :business_id, :contact_id, NULL, NULL,
                :title, :description, NULL, :status, :priority, NOW(), NOW()
             )'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
            'title' => self::cleanText('Follow up with ' . $name, 255),
            'description' => $description,
            'status' => 'open',
            'priority' => 'normal',
        ]);
    }

    private static function insertActivity(
        int $businessId,
        int $contactId,
        string $name,
        string $email,
        string $phone,
        string $message,
        array $metadata,
        bool $created
    ): void {
        $descriptionParts = [];
        if ($phone !== '') {
            $descriptionParts[] = 'Phone: ' . $phone;
        }
        if ($email !== '') {
            $descriptionParts[] = 'Email: ' . $email;
        }
        if ($message !== '') {
            $descriptionParts[] = 'Message: ' . $message;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (
                business_id, user_id, contact_id, module_key, activity_type,
                subject, description, metadata_json, created_at
             ) VALUES (
                :business_id, NULL, :contact_id, :module_key, :activity_type,
                :subject, :description, :metadata_json, NOW()
             )'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
            'module_key' => '247sp',
            'activity_type' => '247sp_website_lead_submitted',
            'subject' => ($created ? 'New' : 'Updated') . ' 247SP website lead: ' . $name,
            'description' => implode("\n", $descriptionParts),
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private static function notesForContact(int $businessId, int $contactId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM notes
             WHERE business_id = :business_id
               AND contact_id = :contact_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);

        return $statement->fetchAll();
    }

    private static function tasksForContact(int $businessId, int $contactId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM tasks
             WHERE business_id = :business_id
               AND contact_id = :contact_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);

        return $statement->fetchAll();
    }

    private static function activityForContact(int $businessId, int $contactId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM activity_logs
             WHERE business_id = :business_id
               AND contact_id = :contact_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);

        return $statement->fetchAll();
    }

    private static function newLeadStatusId(int $businessId): ?int
    {
        $statement = Database::connection()->prepare(
            "SELECT id
             FROM contact_statuses
             WHERE is_active = 1
               AND status_key IN ('new', 'new_lead')
               AND (business_id = :business_id OR business_id IS NULL)
             ORDER BY business_id DESC, is_default DESC, sort_order ASC
             LIMIT 1"
        );
        $statement->execute(['business_id' => $businessId]);
        $statusId = $statement->fetchColumn();

        return $statusId ? (int) $statusId : null;
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = self::cleanText((string) array_shift($parts), 100);
        $lastName = self::cleanText(implode(' ', $parts), 100);

        if ($firstName === '') {
            $firstName = 'Website';
            $lastName = 'Lead';
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    private static function sourceDetail(string $sourcePage, string $service): string
    {
        $parts = ['247SP website'];
        if ($sourcePage !== '') {
            $parts[] = $sourcePage;
        }
        if ($service !== '') {
            $parts[] = $service;
        }

        return self::cleanText(implode(' - ', $parts), 255);
    }

    private static function cleanText($value, int $maxLength): string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return substr((string) $value, 0, $maxLength);
    }

    private static function cleanTextArea($value, int $maxLength): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        $value = preg_replace("/[ \t]+/", ' ', (string) $value);
        $value = preg_replace("/\n{3,}/", "\n\n", (string) $value);

        return substr((string) $value, 0, $maxLength);
    }

    private static function looksAutomated(string $name, string $email, string $phone, string $message): bool
    {
        $combined = strtolower($name . ' ' . $email . ' ' . $phone . ' ' . $message);

        if (trim($combined) === '') {
            return true;
        }

        if ($name !== '' && preg_match('/[a-z0-9]/i', $name) !== 1) {
            return true;
        }

        if (preg_match('/https?:\/\/|www\.|\[url=|<a\s/i', $name . ' ' . $email . ' ' . $phone) === 1) {
            return true;
        }

        preg_match_all('/https?:\/\/|www\./i', $message, $matches);

        return count($matches[0]) > 2;
    }
}
